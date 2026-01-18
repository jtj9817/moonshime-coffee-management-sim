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

        return Inertia::render('game/dashboard', [
            'alerts' => $alerts,
            'kpis' => $this->calculateKPIs(),
            'quests' => $this->getActiveQuests(),
            'logistics_health' => $logisticsService->getLogisticsHealth(),
            'active_spikes_count' => SpikeEvent::where('is_active', true)->count(),
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
                $vendor->orders_avg_total_cost = $vendor->orders()->avg('total_cost');

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
        return Inertia::render('game/analytics', [
            'inventoryTrends' => $this->getInventoryTrends(),
            'spendingByCategory' => $this->getSpendingByCategory(),
            'locationComparison' => $this->getLocationComparison(),
        ]);
    }

    /**
     * Display the spike history page.
     */
    public function spikeHistory(): Response
    {
        $spikes = SpikeEvent::orderBy('created_at', 'desc')->get();

        return Inertia::render('game/spike-history', [
            'spikes' => $spikes,
            'statistics' => $this->getSpikeStatistics(),
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
    public function placeOrder(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'location_id' => 'required|exists:locations,id',
            'route_id' => 'required|exists:routes,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $totalCost = collect($validated['items'])->sum(fn($item) => $item['quantity'] * $item['unit_price']);
            
            $route = Route::findOrFail($validated['route_id']);
            $totalCost += $route->cost;

            $order = Order::create([
                'user_id' => auth()->id(),
                'vendor_id' => $validated['vendor_id'],
                'location_id' => $validated['location_id'],
                'route_id' => $validated['route_id'],
                'total_cost' => $totalCost,
                'status' => Draft::class,
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost_per_unit' => $item['unit_price'],
                ]);
            }

            $order->status->transitionTo(Pending::class);
            
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
            ['label' => 'Inventory Value', 'value' => '$'.number_format($totalInventoryValue, 0)],
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
                'reward' => ['xp' => 100, 'cash' => 500],
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
            'totalSpent' => $orders->sum('total_cost'),
            'avgDeliveryTime' => 2, // Placeholder
            'onTimeDeliveryRate' => 95, // Placeholder
        ];
    }

    /**
     * Get inventory trends for analytics.
     */
    protected function getInventoryTrends(): array
    {
        return [
            ['day' => 1, 'value' => 1000],
            ['day' => 2, 'value' => 950],
            ['day' => 3, 'value' => 1100],
        ];
    }

    /**
     * Get spending by category for analytics.
     */
    protected function getSpendingByCategory(): array
    {
        return Product::select('category')
            ->distinct()
            ->pluck('category')
            ->map(fn ($cat) => ['category' => $cat, 'amount' => rand(1000, 5000)])
            ->toArray();
    }

    /**
     * Get location comparison for analytics.
     */
    protected function getLocationComparison(): array
    {
        return Location::all()->map(fn ($loc) => [
            'name' => $loc->name,
            'inventoryValue' => Inventory::where('location_id', $loc->id)
                ->with('product')
                ->get()
                ->sum(fn ($inv) => $inv->quantity * ($inv->product->storage_cost ?? 0)),
        ])->toArray();
    }

    /**
     * Get spike statistics.
     */
    protected function getSpikeStatistics(): array
    {
        return [
            'totalSpikes' => SpikeEvent::count(),
            'activeSpikes' => SpikeEvent::where('is_active', true)->count(),
            'resolvedSpikes' => SpikeEvent::where('is_active', false)->count(),
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
