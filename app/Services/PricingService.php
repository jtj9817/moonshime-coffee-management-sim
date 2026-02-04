<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\User;

class PricingService
{
    /**
     * Calculate the effective unit cost for a product, accounting for active price spikes.
     * 
     * @param Product $product The product to price
     * @param User $user The user placing the order
     * @param int|null $vendorId Optional vendor ID for vendor-specific price spikes
     * @return int Cost in cents
     */
    public function calculateUnitCost(Product $product, User $user, ?string $vendorId = null): int
    {
        // Start with base cost (assumed to be stored in cents)
        // TODO: Replace with actual product pricing logic when available
        $baseCost = 500; // $5.00 default

        // Check for active price spikes matching this product
        $priceMultiplier = $this->resolvePriceMultiplier($user->id, $product->id, $vendorId);

        if ($priceMultiplier > 1.0) {
            \Log::info("Price spike active for product {$product->id}, multiplier: {$priceMultiplier}x");
        }

        return (int) ($baseCost * $priceMultiplier);
    }

    /**
     * Get the active price multiplier for a product.
     */
    public function getPriceMultiplierFor(User $user, string $productId, ?string $vendorId = null): float
    {
        return $this->resolvePriceMultiplier($user->id, $productId, $vendorId);
    }

    /**
     * Get the price multiplier from active price spikes.
     * Returns 1.0 if no active spikes, or the spike's magnitude if one is active.
     */
    protected function resolvePriceMultiplier(int $userId, string $productId, ?string $vendorId = null): float
    {
        $query = SpikeEvent::forUser($userId)
            ->active()
            ->where('type', 'price')
            ->where(function ($q) use ($productId) {
                // Match exact product or global spikes (null product_id)
                $q->where('product_id', $productId)
                    ->orWhereNull('product_id');
            });

        // Optional: vendor-specific price spike scoping via meta
        if ($vendorId !== null) {
            $query->where(function ($q) use ($vendorId) {
                $q->whereJsonContains('meta->vendor_id', $vendorId)
                    ->orWhereNull('meta->vendor_id');
            });
        }

        $spike = $query->first();

        return $spike ? (float) $spike->magnitude : 1.0;
    }
}
