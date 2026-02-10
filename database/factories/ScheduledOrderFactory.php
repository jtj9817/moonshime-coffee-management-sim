<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\ScheduledOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledOrder>
 */
class ScheduledOrderFactory extends Factory
{
    protected $model = ScheduledOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vendor_id' => Vendor::factory(),
            'source_location_id' => Location::factory(),
            'location_id' => Location::factory(),
            'items' => [
                [
                    'product_id' => null,
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
            'next_run_day' => 2,
            'interval_days' => 7,
            'cron_expression' => null,
            'auto_submit' => false,
            'is_active' => true,
            'last_run_day' => null,
            'failure_reason' => null,
        ];
    }
}
