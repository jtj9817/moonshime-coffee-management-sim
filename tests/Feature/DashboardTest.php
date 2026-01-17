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

test('dashboard includes logistics health KPI', function () {
    $this->actingAs($user = User::factory()->create())
        ->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->withCookie('game_acknowledged', 'true')
        ->get(route('game.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('kpis', 5)
            ->has('kpis.4', fn ($kpi) => $kpi
                ->where('label', 'Logistics Health')
                ->etc()
            )
        );
});
