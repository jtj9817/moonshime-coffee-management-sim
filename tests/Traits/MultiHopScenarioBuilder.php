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
                    $l = new Location();
                    $l->id = $uuid;
                    $l->fill([
                        'name' => ucfirst(str_replace('-', ' ', $loc)),
                        'type' => Str::contains($loc, 'hq') ? 'roastery' : 'warehouse',
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

    protected function createRoutes(array $routeConfigs): void
    {
        foreach ($routeConfigs as $config) {
            $originId = $this->resolveId($config['origin'], Location::class);
            $destId = $this->resolveId($config['destination'], Location::class);

            // Route ID is auto-generated usually, we don't map it explicitly
            // But we should check if route exists?
            // "createRoutes" usually implies adding them.
            
            Route::create([
                'source_id' => $originId,
                'target_id' => $destId,
                'transit_days' => $config['days'],
                'cost' => $config['cost'],
                'capacity' => $config['capacity'] ?? 100,
                'transport_mode' => 'truck',
                'weather_vulnerability' => false,
                'is_active' => true,
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
                    'storage_cost' => 0.1,
                    'unit_price' => $pData['price'] ?? 20.0,
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

    protected function createGameState(User $user, float $cash): GameState
    {
        return GameState::updateOrCreate(
            ['user_id' => $user->id],
            [
                'day' => 1,
                'cash' => $cash,
                'reputation' => 100,
                'spike_config' => [],
            ]
        );
    }
}
