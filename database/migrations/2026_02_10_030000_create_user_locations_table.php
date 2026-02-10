<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'location_id']);
        });

        $vendorLocationIds = DB::table('locations')
            ->where('type', 'vendor')
            ->pluck('id');
        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            $inventoryLocationIds = DB::table('inventories')
                ->where('user_id', $userId)
                ->distinct()
                ->pluck('location_id');

            $locationIds = $inventoryLocationIds
                ->merge($vendorLocationIds)
                ->unique()
                ->values();

            if ($locationIds->isEmpty()) {
                continue;
            }

            $now = now();
            $rows = $locationIds->map(fn (string $locationId): array => [
                'user_id' => $userId,
                'location_id' => $locationId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('user_locations')->insertOrIgnore($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};
