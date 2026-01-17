<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_path_between_locations()
    {
        $locA = Location::factory()->create(['name' => 'Source']);
        $locB = Location::factory()->create(['name' => 'Target']);

        Route::factory()->create([
            'source_id' => $locA->id,
            'target_id' => $locB->id,
            'is_active' => true,
            'weights' => ['cost' => 10],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('game.logistics.path', [
                'source_id' => $locA->id,
                'target_id' => $locB->id,
            ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reachable' => true,
                'total_cost' => 10,
            ]);
    }

    public function test_returns_error_when_no_path_exists()
    {
        $locA = Location::factory()->create();
        $locB = Location::factory()->create();

        // No routes created

        $response = $this->actingAs($this->user)
            ->getJson(route('game.logistics.path', [
                'source_id' => $locA->id,
                'target_id' => $locB->id,
            ]));

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'reachable' => false,
            ]);
    }

    public function test_can_get_logistics_health()
    {
        Route::factory()->count(3)->create(['is_active' => true]);
        Route::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson(route('game.logistics.health'));

        $response->assertOk()
            ->assertJson([
                'health' => 75.0,
            ]);
    }
}
