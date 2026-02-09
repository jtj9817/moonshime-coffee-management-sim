<?php

use App\Models\SpikeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('spike event causal chain (DAG) persists correctly', function () {
    // 1. Create Root Spike (e.g., Blizzard)
    $root = SpikeEvent::factory()->create([
        'type' => 'blizzard',
        'magnitude' => 1.0,
        'duration' => 3,
        'starts_at_day' => 1,
        'ends_at_day' => 4,
        'is_active' => true,
    ]);

    // 2. Create Symptom Spike (e.g., Route Closure caused by Blizzard)
    $symptom = SpikeEvent::factory()->create([
        'parent_id' => $root->id,
        'type' => 'closure',
        'magnitude' => 0.0,
        'duration' => 2,
        'starts_at_day' => 1,
        'ends_at_day' => 3,
        'is_active' => true,
    ]);

    // 3. Verify Persistence and Relationships
    $freshSymptom = SpikeEvent::find($symptom->id);
    expect($freshSymptom->parent_id)->toBe($root->id);
    expect($freshSymptom->parent->type)->toBe('blizzard');

    $freshRoot = SpikeEvent::find($root->id);
    expect($freshRoot->children)->toHaveCount(1);
    expect($freshRoot->children->first()->type)->toBe('closure');
});
