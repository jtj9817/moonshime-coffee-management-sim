<?php

namespace App\Http\Middleware;

use App\Models\Alert;
use App\Models\GameState;
use App\Models\Location;
use App\Models\Product;
use App\Models\SpikeEvent;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            // Game state - only for authenticated users
            'game' => fn () => $request->user() ? $this->getGameData($request->user()) : null,
        ];
    }

    /**
     * Get all game-related data for authenticated users.
     *
     * @return array<string, mixed>
     */
    protected function getGameData(User $user): array
    {
        return [
            'state' => $this->getGameState($user),
            'locations' => Location::select('id', 'name', 'address', 'max_storage', 'type')->get(),
            'products' => Product::with('vendors:id,name')
                ->select('id', 'name', 'category', 'is_perishable', 'storage_cost')
                ->get(),
            'vendors' => Vendor::select('id', 'name', 'reliability_score', 'metrics')->get(),
            'alerts' => Alert::where('is_read', false)
                ->latest()
                ->take(10)
                ->get(),
            'currentSpike' => SpikeEvent::where('is_active', true)->first(),
        ];
    }

    /**
     * Get or create the user's game state with calculated properties.
     *
     * @return array<string, mixed>
     */
    protected function getGameState(User $user): array
    {
        $gameState = GameState::firstOrCreate(
            ['user_id' => $user->id],
            ['cash' => 10000.00, 'xp' => 0, 'day' => 1]
        );

        return [
            'cash' => (float) $gameState->cash,
            'xp' => $gameState->xp,
            'day' => $gameState->day,
            'level' => $this->calculateLevel($gameState->xp),
            'reputation' => $this->calculateReputation($user),
            'strikes' => $this->calculateStrikes(),
        ];
    }

    /**
     * Calculate player level from XP.
     */
    protected function calculateLevel(int $xp): int
    {
        return (int) floor($xp / 1000) + 1;
    }

    /**
     * Calculate reputation score based on vendor metrics and performance.
     */
    protected function calculateReputation(User $user): int
    {
        // Start with base reputation
        $reputation = 85;

        // Adjust based on alert count (more alerts = lower reputation)
        $alertCount = Alert::where('is_read', false)->count();
        $reputation -= min(15, $alertCount * 3);

        // Clamp between 0 and 100
        return max(0, min(100, $reputation));
    }

    /**
     * Calculate number of strikes from critical alerts.
     */
    protected function calculateStrikes(): int
    {
        return Alert::where('is_read', false)
            ->where('severity', 'critical')
            ->count();
    }
}
