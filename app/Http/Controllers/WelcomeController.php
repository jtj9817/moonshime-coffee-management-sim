<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Display the welcome screen.
     *
     * Auto-redirects authenticated users with the acknowledgement cookie
     * directly to the game dashboard.
     */
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        // Auto-redirect: authenticated users with cookie go straight to dashboard
        if ($request->user() && $request->cookie('game_acknowledged') === 'true') {
            return redirect()->route('game.dashboard');
        }

        return Inertia::render('game/welcome', [
            'isAuthenticated' => (bool) $request->user(),
        ]);
    }

    /**
     * Handle the welcome screen acknowledgement.
     *
     * Sets a persistent cookie and redirects to the dashboard (if authenticated)
     * or to the login page (if not authenticated).
     */
    public function acknowledge(Request $request): \Illuminate\Http\RedirectResponse
    {
        $redirect = $request->user()
            ? redirect()->route('game.dashboard')
            : redirect()->route('login');

        return $redirect->withCookie(cookie()->forever('game_acknowledged', 'true'));
    }
}
