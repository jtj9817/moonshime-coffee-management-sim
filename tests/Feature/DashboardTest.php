<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create())
        ->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->withCookie('game_acknowledged', 'true')
        ->get(route('game.dashboard'))
        ->assertOk();
});

test('dashboard excludes logistics health from generic kpis array', function () {
    $this->actingAs($user = User::factory()->create())
        ->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->withCookie('game_acknowledged', 'true')
        ->get(route('game.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('kpis', 4)
            ->where('kpis.0.label', 'Inventory Value')
            ->where('kpis.1.label', 'Low Stock Items')
            ->where('kpis.2.label', 'Pending Orders')
            ->where('kpis.3.label', 'Locations')
            ->has('logistics_health')
        );
});
