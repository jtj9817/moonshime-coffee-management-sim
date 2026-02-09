<?php

namespace App\Http\Requests;

use App\Models\GameState;
use App\Services\LogisticsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('items')) {
            return;
        }

        $items = collect($this->input('items', []))
            ->map(function (array $item) {
                if (isset($item['unit_price'])) {
                    // Frontend sends integer cents directly
                    $item['unit_price'] = (int) $item['unit_price'];
                }

                return $item;
            })
            ->toArray();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'source_location_id' => ['nullable', 'exists:locations,id'],
            // 'route_id' is no longer used
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            // 1. Find Best Path
            $logistics = app(LogisticsService::class);

            // Assume vendor location is the vendor ID for now, or fetch correct location
            // Since we don't have VendorLocation logic easily, we try to use vendor ID as Location ID
            // or fetch the vendor and get its location.
            // But usually the vendor ID and Location ID are different in this seeding.
            // Let's rely on finding a location with type 'vendor' that corresponds to this vendor.
            // However, the GraphSeeder just made vendors locations.
            // For now, let's assume `vendor_id` from request maps to `Location` if we find it,
            // or we search for the Vendor Location.

            $vendorId = $this->input('vendor_id');
            $locationId = $this->input('location_id');
            $sourceLocationId = $this->input('source_location_id');
            $sourceLocation = null;

            if ($sourceLocationId) {
                $sourceLocation = \App\Models\Location::find($sourceLocationId);
                if (! $sourceLocation) {
                    $validator->errors()->add('source_location_id', 'Selected source location is invalid.');

                    return;
                }
            } else {
                // Try to find a location with this ID first (if Vendor ID == Location ID)
                $sourceLocation = \App\Models\Location::where('id', $vendorId)->first();
            }

            // If not found or if the types don't match what we expect, maybe we need to find
            // the location associated with the Vendor model?
            // In CoreGameStateSeeder: Vendor created.
            // In GraphSeeder: Location created with type='vendor'.
            // They are NOT linked in the seeder explicitly!
            // This is a disconnect.
            // BUT, `new-order-dialog.tsx` selects `selectedSourceId` from `vendorLocations`.
            // And sends `vendor_id` as well.
            // Wait, looking at `new-order-dialog.tsx`:
            // It sends `vendor_id` (selected from `vendorOptions`) AND `location_id` (Target).
            // It DOES NOT send `source_location_id` in the form payload used by `post()`.
            // Line 58: { vendor_id, location_id, route_id, items }.
            // So the backend only knows the Vendor ID.
            // We need to find the Location for that Vendor.
            // If the Vendor ID != Location ID, we have a problem finding the start node.

            // Assumption: we pick the first 'vendor' type location that provides the products?
            // Or we assume 1-to-1 mapping?
            // "Bean Baron" Vendor (ID 1) vs "Bean Baron" Location (ID 105).
            // Currently, there is NO LINK.
            // Verify `Vendor` model...
            // `Vendor` model has `products`. It doesn't have `location_id`.
            // `Location` has `type='vendor'`.

            // Fix: We should update the Request to accept `source_location_id` OR
            // we find any vendor location.
            // `InitializeNewGame` just picked `Location::where('type', 'vendor')->first()`.
            // This implies ANY vendor location is fine? That's weird.
            // Realistically, the Vendor should have a location.

            // For now, let's assume we search for a Location with the same name as the Vendor?
            // OR, given the implementation plan didn't address linking Vendor->Location,
            // I should find the best matching location.
            if (! $sourceLocation) {
                $vendor = \App\Models\Vendor::find($vendorId);
                if (! $vendor) {
                    $validator->errors()->add('vendor_id', 'Selected vendor is invalid.');

                    return;
                }

                $sourceLocation = \App\Models\Location::where('type', 'vendor')
                    ->where('name', 'like', '%'.$vendor->name.'%') // Fuzzy match
                    ->first();
            }

            if (! $sourceLocation) {
                // Fallback to any vendor location
                $sourceLocation = \App\Models\Location::where('type', 'vendor')->first();
            }

            if (! $sourceLocation) {
                $validator->errors()->add('vendor_id', 'No distribution center found for this vendor.');

                return;
            }

            $targetLocation = \App\Models\Location::find($locationId);
            if (! $targetLocation) {
                $validator->errors()->add('location_id', 'Selected destination is invalid.');

                return;
            }

            $path = $logistics->findBestRoute($sourceLocation, $targetLocation);

            if (! $path || $path->isEmpty()) {
                $validator->errors()->add('location_id', 'No valid route found to this destination.');

                return;
            }

            // 2. Validate Capacity (Bottleneck)
            $items = collect($this->input('items', []));
            $totalQuantity = $items->sum(fn ($item) => $item['quantity'] ?? 0);
            $minCapacity = $path->min('capacity');

            if ($minCapacity !== null && $totalQuantity > $minCapacity) {
                $validator->errors()->add('items', "Order size ({$totalQuantity}) exceeds route capacity ({$minCapacity}).");
            }

            // 3. Validate Funds
            $shippingCost = (int) $path->sum(fn ($r) => $logistics->calculateCost($r));
            $pricing = app(\App\Services\PricingService::class);
            $user = $this->user();
            $vendorId = $this->input('vendor_id');
            $itemsCost = (int) $items->sum(function ($item) use ($pricing, $user, $vendorId) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $unitPrice = (int) ($item['unit_price'] ?? 0);
                $multiplier = $user
                    ? $pricing->getPriceMultiplierFor($user, $item['product_id'], $vendorId)
                    : 1.0;

                return (int) round($quantity * $unitPrice * $multiplier);
            });
            $totalCost = $itemsCost + $shippingCost;

            $cash = (int) GameState::where('user_id', auth()->id())->value('cash');

            if ($cash < $totalCost) {
                $totalDisplay = number_format($totalCost / 100, 2);
                $cashDisplay = number_format($cash / 100, 2);
                $validator->errors()->add('total', "Insufficient funds. Order total: \${$totalDisplay}, Available: \${$cashDisplay}.");
            }

            // Store the path in the request so the Controller doesn't need to recalculate
            $this->merge(['_calculated_path' => $path]);
            $this->merge(['_source_location' => $sourceLocation]);
        });
    }
}
