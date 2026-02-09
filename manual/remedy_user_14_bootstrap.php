<?php

/**
 * Manual Tinker script to remediate User 14's incomplete game state.
 *
 * This script cleans up User 14's incomplete records and reinitializes
 * their game using the proper InitializeNewGame bootstrap action.
 *
 * Usage: ./vendor/bin/sail tinker --execute="include 'manual/remedy_user_14_bootstrap.php';"
 */

// Load user
$user = App\Models\User::find(14);
if (! $user) {
    echo "User 14 not found!\n";
    exit;
}

echo "=== User 14 ({$user->name}) Bootstrap Remediation ===\n\n";

echo "Current state before cleanup:\n";
echo '  Game States: '.App\Models\GameState::where('user_id', $user->id)->count()."\n";
echo '  Inventories: '.App\Models\Inventory::where('user_id', $user->id)->count()."\n";
echo '  Orders: '.App\Models\Order::where('user_id', $user->id)->count()."\n";
echo '  Transfers: '.App\Models\Transfer::where('user_id', $user->id)->count()."\n";
echo '  Spike Events: '.App\Models\SpikeEvent::where('user_id', $user->id)->count()."\n";
echo "\n";

echo "Cleaning up User 14's incomplete game state...\n";

// Delete existing incomplete records (in transaction for safety)
DB::transaction(function () use ($user) {
    App\Models\GameState::where('user_id', $user->id)->delete();
    App\Models\SpikeEvent::where('user_id', $user->id)->delete();
    App\Models\Inventory::where('user_id', $user->id)->delete();
    App\Models\OrderItem::whereIn('order_id', function ($query) use ($user) {
        $query->select('id')->from('orders')->where('user_id', $user->id);
    })->delete();
    App\Models\Order::where('user_id', $user->id)->delete();
    App\Models\Transfer::where('user_id', $user->id)->delete();
});

echo "  ✓ Deleted all existing per-user records\n\n";

echo "Re-initializing game state with proper bootstrap...\n";

// Re-run bootstrap action
$gameState = app(App\Actions\InitializeNewGame::class)->handle($user);

echo "  ✓ Bootstrap action completed\n\n";

echo "=== New Game State ===\n";
echo "Day: {$gameState->day}\n";
echo 'Cash: $'.number_format($gameState->cash, 2)."\n";
echo "XP: {$gameState->xp}\n";
echo "\n";

echo "=== Created Records ===\n";
echo 'Inventories: '.App\Models\Inventory::where('user_id', $user->id)->count()."\n";
echo 'Orders: '.App\Models\Order::where('user_id', $user->id)->count()."\n";
echo 'Transfers: '.App\Models\Transfer::where('user_id', $user->id)->count()."\n";
echo 'Spike Events: '.App\Models\SpikeEvent::where('user_id', $user->id)->count()."\n";
echo "\n";

echo "=== Inventory Breakdown ===\n";
$inventories = App\Models\Inventory::where('user_id', $user->id)->get();
$byLocation = $inventories->groupBy('location_id');
foreach ($byLocation as $locationId => $items) {
    $location = App\Models\Location::find($locationId);
    echo "  Location: {$location->name} ({$location->type})\n";
    foreach ($items as $item) {
        $product = App\Models\Product::find($item->product_id);
        $type = $product->is_perishable ? '[P]' : '[N]';
        echo "    {$type} {$product->name}: {$item->quantity} units\n";
    }
    echo "\n";
}

echo "=== Pipeline Activity ===\n";
$orders = App\Models\Order::where('user_id', $user->id)->get();
foreach ($orders as $order) {
    $vendor = App\Models\Vendor::find($order->vendor_id);
    $location = App\Models\Location::find($order->location_id);
    echo "  Order ID: {$order->id}\n";
    echo "    Vendor: {$vendor->name}\n";
    echo "    Destination: {$location->name}\n";
    echo "    Status: {$order->status}\n";
    echo "    Delivery Day: {$order->delivery_day}\n";
    echo '    Total Cost: $'.number_format($order->total_cost, 2)."\n";
    echo "\n";
}

$transfers = App\Models\Transfer::where('user_id', $user->id)->get();
foreach ($transfers as $transfer) {
    $from = App\Models\Location::find($transfer->source_location_id);
    $to = App\Models\Location::find($transfer->target_location_id);
    $product = App\Models\Product::find($transfer->product_id);
    echo "  Transfer ID: {$transfer->id}\n";
    echo "    From: {$from->name}\n";
    echo "    To: {$to->name}\n";
    echo "    Product: {$product->name}\n";
    echo "    Quantity: {$transfer->quantity}\n";
    echo "    Status: {$transfer->status}\n";
    echo "    Delivery Day: {$transfer->delivery_day}\n";
    echo "\n";
}

echo "=== Spike Events ===\n";
$spikes = App\Models\SpikeEvent::where('user_id', $user->id)->orderBy('starts_at_day')->get();
foreach ($spikes as $spike) {
    echo "  Type: {$spike->type}\n";
    echo "    Days: {$spike->starts_at_day} - {$spike->ends_at_day}\n";
    echo "    Magnitude: {$spike->magnitude}x\n";
    echo '    Active: '.($spike->is_active ? 'Yes' : 'No')."\n";
    echo "\n";
}

echo "=== ✅ Bootstrap Remediation Complete ===\n";
echo "User 14 now has a complete game state with proper inventory,\n";
echo "pipeline activity, and spike events ready for gameplay!\n";
