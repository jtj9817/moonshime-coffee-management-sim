<?php

namespace Tests\Traits;

use App\Models\GameState;
use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait MultiHopScenarioBuilder
{
    protected array $idMap = [];

    protected function resolveId(string $alias, string $modelClass): string
    {
        if (Str::isUuid($alias)) {
            return $alias;
        }

        if (!isset($this->idMap[$modelClass])) {
            $this->idMap[$modelClass] = [];
        }

        if (!isset($this->idMap[$modelClass][$alias])) {
            $this->idMap[$modelClass][$alias] = (string) Str::uuid();
        }

        return $this->idMap[$modelClass][$alias];
    }

    protected function createVendorPath(array $locations): void
    {
        foreach ($locations as $loc) {
            if (is_string($loc)) {
                $uuid = $this->resolveId($loc, Location::class);
                if (!Location::find($uuid)) {
                    $normalized = Str::lower($loc);
                    $l = new Location();
                    $l->id = $uuid;
                    $l->fill([
                        'name' => ucfirst(str_replace('-', ' ', $loc)),
                        'type' => $this->resolveLocationType($normalized),
                        'address' => 'Test Address ' . $loc,
                        'max_storage' => 1000,
                    ]);
                    $l->save();
                }
            } else {
                // Assume it's a Model instance
            }
        }
    }

    protected function resolveLocationType(string $alias): string
    {
        if (Str::contains($alias, 'vendor')) {
            return 'vendor';
        }

        if (Str::contains($alias, 'store')) {
            return 'store';
        }

        if (Str::contains($alias, 'hub')) {
            return 'hub';
        }

        if (Str::contains($alias, 'warehouse')) {
            return 'warehouse';
        }

        if (Str::contains($alias, 'hq')) {
            return 'warehouse';
        }

        return 'warehouse';
    }

    protected function createRoutes(array $routeConfigs): void
    {
        foreach ($routeConfigs as $config) {
            $originId = $this->resolveId($config['origin'], Location::class);
            $destId = $this->resolveId($config['destination'], Location::class);
            $isActive = $config['active'] ?? $config['is_active'] ?? true;
            $transportMode = $config['transport_mode'] ?? $config['mode'] ?? 'truck';

            // Route ID is auto-generated usually, we don't map it explicitly
            // But we should check if route exists?
            // "createRoutes" usually implies adding them.
            
            Route::create([
                'source_id' => $originId,
                'target_id' => $destId,
                'transit_days' => $config['days'],
                'cost' => $config['cost'],
                'capacity' => $config['capacity'] ?? 100,
                'transport_mode' => $transportMode,
                'weather_vulnerability' => false,
                'is_active' => $isActive,
            ]);
        }
    }

    protected function createProductBundle(array $products): void
    {
        foreach ($products as $pData) {
            $prodId = $this->resolveId($pData['id'], Product::class);

            $product = Product::find($prodId);
            if (!$product) {
                $product = new Product();
                $product->id = $prodId;
                $product->fill([
                    'name' => $pData['name'] ?? ucfirst($pData['id']),
                    'category' => $pData['category'] ?? 'beans',
                    'is_perishable' => false,
                    'storage_cost' => 10, // cents
                    'unit_price' => $pData['price'] ?? 2000, // cents
                ]);
                $product->save();
            }

            if (isset($pData['vendor'])) {
                $vendId = $this->resolveId($pData['vendor']['id'], Vendor::class);

                $vendor = Vendor::find($vendId);
                if (!$vendor) {
                    $vendor = new Vendor();
                    $vendor->id = $vendId;
                    $vendor->fill([
                        'name' => $pData['vendor']['name'] ?? 'Test Vendor',
                        'reliability_score' => 1.0,
                        'metrics' => [],
                    ]);
                    $vendor->save();
                }

                // Attach if not already attached
                if (!$product->vendors()->where('vendor_id', $vendor->id)->exists()) {
                    $product->vendors()->attach($vendor->id);
                }
            }
        }
    }

    protected function createGameState(User $user, int $cash): GameState
    {
        return GameState::updateOrCreate(
            ['user_id' => $user->id],
            [
                'day' => 1,
                'cash' => $cash,
                'xp' => 0,
                'spike_cooldowns' => [],
            ]
        );
    }
}
