<?php

namespace App\Http\Requests;

use App\Models\GameState;
use App\Models\Route;
use App\Models\SpikeEvent;
use App\Services\LogisticsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'route_id' => ['required', 'exists:routes,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $routeId = $this->input('route_id');
            $route = Route::find($routeId);

            // 1. Validate Route is Active
            if (!$route->is_active) {
                // Check if there's a specific reason from spikes
                $spike = SpikeEvent::where('affected_route_id', $route->id)
                    ->where('is_active', true)
                    ->first();
                
                $message = $spike 
                    ? "Route is blocked: {$spike->type}" 
                    : 'The selected route is currently inactive.';
                
                $validator->errors()->add('route_id', $message);
                return;
            }

            // 2. Validate Capacity
            // Sum of all item quantities
            $totalQuantity = collect($this->input('items'))->sum('quantity');
            
            // Basic capacity check (static capacity)
            // Future improvement: check current load if implemented
            if ($totalQuantity > $route->capacity) {
                $validator->errors()->add('route_id', "Order size ({$totalQuantity}) exceeds route capacity ({$route->capacity}).");
            }

            // 3. Validate Funds
            $logistics = app(LogisticsService::class);
            $shippingCost = $logistics->calculateCost($route);
            
            $itemsCost = collect($this->input('items'))->sum(fn($item) => $item['quantity'] * $item['unit_price']);
            $totalCost = $itemsCost + $shippingCost;

            $cash = GameState::first()->cash;
            
            if ($cash < $totalCost) {
                $validator->errors()->add('total', "Insufficient funds. Order total: \${$totalCost}, Available: \${$cash}.");
            }
        });
    }
}
