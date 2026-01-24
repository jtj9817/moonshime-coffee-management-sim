<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\OrderItem;
use App\Models\Route;
use App\States\Order\Cancelled;
use App\States\Order\Delivered;
use App\States\Order\Draft;
use App\States\Order\Pending;
use App\States\Order\Shipped;
use App\Models\Transfer;
use App\Models\Vendor;
use App\Events\OrderPlaced;
use App\Services\SimulationService;
use App\Actions\InitializeNewGame;
use App\Models\GameState;
use App\Models\DailyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * Display the game dashboard.
     */
    public function dashboard(\App\Services\LogisticsService $logisticsService): Response
    {
        $alerts = Alert::where('is_read', false)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $userId = auth()->id();
        $gameState = GameState::where('user_id', $userId)->first();
        $previousDay = $gameState ? $gameState->day - 1 : 0;
        
        $dailyReport = null;
        if ($previousDay >= 1) {
            $dailyReport = DailyReport::where('user_id', $userId)
                ->where('day', $previousDay)
                ->first();
        }

        return Inertia::render('game/dashboard', [
            'alerts' => $alerts,
            'kpis' => $this->calculateKPIs(),
            'quests' => $this->getActiveQuests(),
            'logistics_health' => $logisticsService->getLogisticsHealth(),
            'active_spikes_count' => SpikeEvent::forUser(auth()->id())->active()->count(),
            'dailyReport' => $dailyReport,
        ]);
    }

    /**
     * Display the inventory page.
     */
    public function inventory(Request $request): Response
    {
        $locationId = $request->get('location', 'all');

        $query = Inventory::with(['product', 'location']);
        if ($locationId !== 'all') {
            $query->where('location_id', $locationId);
        }

        return Inertia::render('game/inventory', [
            'inventory' => $query->get(),
            'currentLocation' => $locationId,
        ]);
    }

    /**
     * Display SKU detail page.
     */
    public function skuDetail(Location $location, Product $sku): Response
    {
        $inventory = Inventory::with(['product', 'location'])
            ->where('location_id', $location->id)
            ->where('product_id', $sku->id)
            ->first();

        return Inertia::render('game/sku-detail', [
            'location' => $location,
            'product' => $sku,
            'inventory' => $inventory,
        ]);
    }

    /**
     * Check order capacity and validation.
     */
    public function capacityCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_id' => 'required|exists:routes,id',
            'items' => 'required|array',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        $route = Route::find($validated['route_id']);
        $totalQuantity = collect($validated['items'])->sum('quantity');
        
        // Logic: if total quantity <= capacity, it's valid.
        // We also want to return the excess if any.
        $excess = max(0, $totalQuantity - $route->capacity);
        
        return response()->json([
            'within_capacity' => $totalQuantity <= $route->capacity,
            'order_quantity' => $totalQuantity,
            'route_capacity' => $route->capacity,
            'excess' => $excess,
            'suggestion' => $excess > 0 ? "Reduce order size by {$excess} units." : null,
        ]);
    }

    /**
     * Display the ordering page.
     */
    public function ordering(): Response
    {
        $orders = Order::with(['vendor', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $vendorProducts = $this->getVendorProducts();

        return Inertia::render('game/ordering', [
            'orders' => $orders,
            'vendorProducts' => $vendorProducts,
        ]);
    }

    /**
     * Display the transfers page.
     */
    public function transfers(): Response
    {
        $transfers = Transfer::with(['sourceLocation', 'targetLocation'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('game/transfers', [
            'transfers' => $transfers,
            'suggestions' => $this->getTransferSuggestions(),
        ]);
    }

    /**
     * Display the vendors list page.
     */
    public function vendors(): Response
    {
        $vendors = Vendor::withCount('orders')
            ->get()
            ->map(function ($vendor) {
                $vendor->orders_avg_total_cost = (float) $vendor->orders()->avg('total_cost');

                return $vendor;
            });

        return Inertia::render('game/vendors', [
            'vendors' => $vendors,
        ]);
    }

    /**
     * Display vendor detail page.
     */
    public function vendorDetail(Vendor $vendor): Response
    {
        $vendor->load(['products', 'orders' => fn ($q) => $q->latest()->take(20)]);

        return Inertia::render('game/vendors/detail', [
            'vendor' => $vendor,
            'metrics' => $this->calculateVendorMetrics($vendor),
        ]);
    }

    /**
     * Display the analytics page.
     */
    public function analytics(): Response
    {
        $userId = auth()->id();
        $gameState = GameState::where('user_id', $userId)->firstOrFail();

        $totalInventoryValue = Inventory::where('user_id', $userId)
            ->with('product')
            ->get()
            ->sum(fn ($inv) => $inv->quantity * ($inv->product->unit_price ?? 0));

        return Inertia::render('game/analytics', [
            'overviewMetrics' => [
                'cash' => (float) $gameState->cash,
                'netWorth' => (float) ($gameState->cash + $totalInventoryValue),
                'revenue7Day' => 0, // Placeholder until revenue logic is implemented
            ],
            'inventoryTrends' => $this->getInventoryTrends(),
            'spendingByCategory' => $this->getSpendingByCategory(),
            'locationComparison' => $this->getLocationComparison(),
            'storageUtilization' => $this->getStorageUtilization(),
            'fulfillmentMetrics' => $this->getOrderFulfillmentMetrics(),
            'spikeImpactAnalysis' => $this->getSpikeImpactAnalysis(),
        ]);
    }

    /**
     * Display the spike history page (War Room).
     */
    public function spikeHistory(): Response
    {
        $userId = auth()->id();
        $gameState = GameState::where('user_id', $userId)->first();
        $currentDay = $gameState ? $gameState->day : 1;
        
        // Get all spikes with enriched playbook data
        $spikes = SpikeEvent::with(['location', 'product', 'affectedRoute'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SpikeEvent $spike) {
                return array_merge($spike->toArray(), [
                    'playbook' => $spike->getPlaybook(),
                ]);
            });

        // Separate active spikes for the Active Events section
        $activeSpikes = $spikes->filter(fn($spike) => $spike['is_active']);

        return Inertia::render('game/spike-history', [
            'spikes' => $spikes,
            'activeSpikes' => $activeSpikes->values(),
            'statistics' => $this->getSpikeStatistics($userId),
            'currentDay' => $currentDay,
        ]);
    }

    /**
     * Display the waste reports page.
     */
    public function wasteReports(): Response
    {
        return Inertia::render('game/reports', [
            'wasteEvents' => [],
            'wasteByCause' => [],
            'wasteByLocation' => [],
        ]);
    }

    /**
     * Display the strategy page.
     */
    public function strategy(): Response
    {
        return Inertia::render('game/strategy', [
            'currentPolicy' => $this->getCurrentPolicy(),
            'policyOptions' => $this->getPolicyOptions(),
        ]);
    }

    /**
     * Advance the game by one day.
     */
    public function advanceDay(SimulationService $simulation): \Illuminate\Http\RedirectResponse
    {
        $simulation->advanceTime();

        return back();
    }

    /**
     * Place a new order.
     */

    public function placeOrder(\App\Http\Requests\StoreOrderRequest $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validated();
        
        return DB::transaction(function () use ($validated, $request) {
            $path = $request->input('_calculated_path');
            // sourceLocation not strictly needed by createOrder if path is provided
            
            $vendor = Vendor::findOrFail($validated['vendor_id']);
            $targetLocation = Location::findOrFail($validated['location_id']);
            
            $items = collect($validated['items'])->map(function($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost_per_unit' => $item['unit_price'],
                ];
            })->toArray();

            $order = app(\App\Services\OrderService::class)->createOrder(
                user: auth()->user(),
                vendor: $vendor,
                targetLocation: $targetLocation,
                items: $items,
                path: $path
            );
            
            event(new OrderPlaced($order));

            return back()->with('success', 'Order placed successfully');
        });
    }

    /**
     * Create a new transfer.
     */
    public function createTransfer(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'source_location_id' => 'required|exists:locations,id',
            'target_location_id' => 'required|exists:locations,id|different:source_location_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Create transfer logic would go here
        // For now, we'll just return back

        return back()->with('success', 'Transfer created successfully');
    }

    /**
     * Update the current policy.
     */
    public function updatePolicy(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'policy' => 'required|string|in:just_in_time,safety_stock',
        ]);

        // Update policy logic would go here
        // For now, we'll just return back

        return back()->with('success', 'Policy updated successfully');
    }

    /**
     * Cancel an existing order.
     */
    public function cancelOrder(Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($order->status instanceof Delivered) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel delivered orders.'], 422);
        }

        if (!$order->status->canTransitionTo(Cancelled::class)) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled in its current state.'], 422);
        }

        try {
            $order->status->transitionTo(Cancelled::class);
            return response()->json(['success' => true, 'message' => 'Order cancelled successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark an alert as read.
     */
    public function markAlertRead(Alert $alert): \Illuminate\Http\RedirectResponse
    {
        $alert->update(['is_read' => true]);

        return back();
    }

    /**
     * Reset the game to Day 1 state.
     */
    public function resetGame(InitializeNewGame $initializer): \Illuminate\Http\RedirectResponse
    {
        DB::transaction(function () use ($initializer) {
            $user = auth()->user();

            // Clear all game data
            Order::where('user_id', $user->id)->delete();
            Transfer::where('user_id', $user->id)->delete();
            Alert::where('user_id', $user->id)->delete();
            SpikeEvent::where('user_id', $user->id)->delete();
            Inventory::where('user_id', $user->id)->delete();
            
            // Reset GameState
            $gameState = GameState::where('user_id', $user->id)->first();
            if ($gameState) {
                $gameState->update([
                    'day' => 1,
                    'cash' => 10000.00,
                    'xp' => 0,
                    // Reset other fields?
                ]);
            }

            // Re-seed
            $initializer->handle($user);
        });

        return to_route('game.dashboard')->with('success', 'Game has been reset to Day 1.');
    }

    // ==================== Helper Methods ====================

    /**
     * Calculate dashboard KPIs.
     */
    protected function calculateKPIs(): array
    {
        $totalInventoryValue = Inventory::with('product')
            ->get()
            ->sum(fn ($inv) => $inv->quantity * ($inv->product->storage_cost ?? 0));

        $lowStockCount = Inventory::where('quantity', '<', 50)->count();
        $pendingOrders = Order::where('status', 'pending')->count();

        return [
            ['label' => 'Inventory Value', 'value' => round($totalInventoryValue, 2)],
            ['label' => 'Low Stock Items', 'value' => $lowStockCount, 'trend' => $lowStockCount > 5 ? 'down' : 'up'],
            ['label' => 'Pending Orders', 'value' => $pendingOrders],
            ['label' => 'Locations', 'value' => Location::count()],
        ];
    }

    /**
     * Get active quests.
     */
    protected function getActiveQuests(): array
    {
        // Placeholder quests - would be fetched from database
        return [
            [
                'id' => '1',
                'type' => 'inventory',
                'title' => 'Stock Champion',
                'description' => 'Maintain at least 100 units of each product',
                'reward' => ['xp' => 100, 'cash' => 5.00],
                'targetValue' => 100,
                'currentValue' => 75,
                'isCompleted' => false,
            ],
        ];
    }

    /**
     * Get vendor products for ordering.
     */
    protected function getVendorProducts(): array
    {
        return Vendor::with('products:id,name,category')
            ->get()
            ->map(fn ($v) => [
                'vendor' => $v->only(['id', 'name', 'reliability_score']),
                'products' => $v->products,
            ])
            ->toArray();
    }

    /**
     * Get transfer suggestions.
     */
    protected function getTransferSuggestions(): array
    {
        // Analyze inventory imbalances and suggest transfers
        return [];
    }

    /**
     * Calculate vendor metrics.
     */
    protected function calculateVendorMetrics(Vendor $vendor): array
    {
        $orders = $vendor->orders;

        return [
            'totalOrders' => $orders->count(),
            'totalSpent' => (float) $orders->sum('total_cost'),
            'avgDeliveryTime' => 2, // Placeholder
            'onTimeDeliveryRate' => 95, // Placeholder
        ];
    }

    /**
     * Get inventory trends for analytics.
     */
    protected function getInventoryTrends(): array
    {
        return DB::table('inventory_history')
            ->where('user_id', auth()->id())
            ->select('day', DB::raw('SUM(quantity) as value'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn($item) => ['day' => (int) $item->day, 'value' => (int) $item->value])
            ->toArray();
    }

    /**
     * Get spending by category for analytics.
     */
    protected function getSpendingByCategory(): array
    {
        return OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.user_id', auth()->id())
            ->select('products.category', DB::raw('SUM(order_items.quantity * order_items.cost_per_unit) as amount'))
            ->groupBy('products.category')
            ->get()
            ->map(fn($item) => [
                'category' => $item->category,
                'amount' => (float) $item->amount
            ])
            ->toArray();
    }

    /**
     * Get location comparison for analytics.
     */
    protected function getLocationComparison(): array
    {
        $locations = Location::with(['inventories' => function ($query) {
            $query->where('user_id', auth()->id())->with('product');
        }])->get();

        return $locations->map(function ($loc) {
            $inventories = $loc->inventories;

            $inventoryValue = $inventories->sum(fn ($inv) => $inv->quantity * $inv->product->unit_price);
            
            $totalQuantity = $inventories->sum('quantity');
            $utilization = $loc->max_storage > 0 
                ? round(($totalQuantity / $loc->max_storage) * 100, 1) 
                : 0;

            $itemCount = $inventories->unique('product_id')->count();

            return [
                'name' => $loc->name,
                'inventoryValue' => (float) $inventoryValue,
                'utilization' => (float) $utilization,
                'itemCount' => (int) $itemCount,
            ];
        })->toArray();
    }

    /**
     * Get storage utilization for analytics.
     */
    protected function getStorageUtilization(): array
    {
        return Location::with(['inventories' => function ($query) {
            $query->where('user_id', auth()->id());
        }])->get()
        ->map(function ($loc) {
            $used = $loc->inventories->sum('quantity');
            $capacity = $loc->max_storage;
            $percentage = $capacity > 0 ? round(($used / $capacity) * 100, 1) : 0;
            
            return [
                'location_id' => $loc->id,
                'name' => $loc->name,
                'capacity' => (int) $capacity,
                'used' => (int) $used,
                'percentage' => (float) $percentage,
            ];
        })->toArray();
    }

    /**
     * Get order fulfillment metrics for analytics.
     */
    protected function getOrderFulfillmentMetrics(): array
    {
        $userId = auth()->id();
        
        $totalOrders = Order::where('user_id', $userId)->count();
        $deliveredOrders = Order::where('user_id', $userId)
            ->whereState('status', Delivered::class)
            ->get();
            
        $deliveredCount = $deliveredOrders->count();
        
        $fulfillmentRate = $totalOrders > 0 
            ? round(($deliveredCount / $totalOrders) * 100, 1) 
            : 0;

        $totalTransitDays = $deliveredOrders->sum(function ($order) {
            return max(0, $order->delivery_day - $order->created_day);
        });
        
        $averageDeliveryTime = $deliveredCount > 0 
            ? round($totalTransitDays / $deliveredCount, 1) 
            : 0;

        return [
            'totalOrders' => $totalOrders,
            'deliveredOrders' => $deliveredCount,
            'fulfillmentRate' => (float) $fulfillmentRate,
            'averageDeliveryTime' => (float) $averageDeliveryTime,
        ];
    }

    /**
     * Get spike impact analysis for analytics.
     */
    protected function getSpikeImpactAnalysis(): array
    {
        $spikes = SpikeEvent::with(['product', 'location'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return $spikes->map(function ($spike) {
            $impact = null;
            
            if ($spike->product_id && $spike->location_id) {
                $history = DB::table('inventory_history')
                    ->where('user_id', auth()->id())
                    ->where('location_id', $spike->location_id)
                    ->where('product_id', $spike->product_id)
                    ->where('day', '>=', $spike->starts_at_day)
                    ->where('day', '<=', $spike->ends_at_day)
                    ->get();
                
                if ($history->isNotEmpty()) {
                    $min = $history->min('quantity');
                    $avg = $history->avg('quantity');
                    
                    $impact = [
                        'min_inventory' => (int) $min,
                        'avg_inventory' => round($avg, 1),
                    ];
                }
            }
            
            return [
                'id' => $spike->id,
                'type' => $spike->type,
                'name' => $spike->name,
                'start_day' => $spike->starts_at_day,
                'end_day' => $spike->ends_at_day,
                'product_name' => $spike->product ? $spike->product->name : 'N/A',
                'location_name' => $spike->location ? $spike->location->name : 'N/A',
                'impact' => $impact,
            ];
        })->toArray();
    }

    /**
     * Get spike statistics.
     */
    protected function getSpikeStatistics(?int $userId = null): array
    {
        $query = SpikeEvent::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return [
            'totalSpikes' => (clone $query)->count(),
            'activeSpikes' => (clone $query)->where('is_active', true)->count(),
            'resolvedSpikes' => (clone $query)->where('is_active', false)->count(),
            'resolvedByPlayer' => (clone $query)->where('resolved_by', 'player')->count(),
        ];
    }

    /**
     * Get current policy settings.
     */
    protected function getCurrentPolicy(): array
    {
        return [
            'name' => 'safety_stock',
            'settings' => [
                'safetyStockMultiplier' => 1.5,
                'reorderPoint' => 50,
            ],
        ];
    }

    /**
     * Get available policy options.
     */
    protected function getPolicyOptions(): array
    {
        return [
            [
                'name' => 'just_in_time',
                'label' => 'Just-In-Time',
                'description' => 'Minimize inventory holding costs with tight ordering',
            ],
            [
                'name' => 'safety_stock',
                'label' => 'Safety Stock',
                'description' => 'Maintain buffer inventory to prevent stockouts',
            ],
        ];
    }
}
