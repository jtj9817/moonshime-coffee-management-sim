<?php

namespace App\Http\Controllers;

use App\Models\SpikeEvent;
use App\Services\SpikeResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpikeController extends Controller
{
    public function __construct(
        protected SpikeResolutionService $resolutionService
    ) {}

    /**
     * Resolve a spike early (breakdown/blizzard only).
     * POST /game/spikes/{spike}/resolve
     */
    public function resolve(SpikeEvent $spike): RedirectResponse
    {
        // Authorization: spike must belong to authenticated user
        if ($spike->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->resolutionService->resolveEarly($spike);

            return redirect()->back()->with('success', 'Event resolved successfully! Systems restored.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Log a mitigation action for a spike.
     * POST /game/spikes/{spike}/mitigate
     */
    public function mitigate(Request $request, SpikeEvent $spike): RedirectResponse
    {
        // Authorization: spike must belong to authenticated user
        if ($spike->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'action' => 'required|string|max:255',
        ]);

        try {
            $this->resolutionService->mitigate($spike, $validated['action']);

            return redirect()->back()->with('success', 'Mitigation action logged.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark a spike as acknowledged.
     * POST /game/spikes/{spike}/acknowledge
     */
    public function acknowledge(SpikeEvent $spike): RedirectResponse
    {
        // Authorization: spike must belong to authenticated user
        if ($spike->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $this->resolutionService->acknowledge($spike);

        return redirect()->back();
    }
}
