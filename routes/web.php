<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// Public welcome route (no auth required)
Route::get('/', [WelcomeController::class, 'index'])->name('home');
Route::post('/acknowledge', [WelcomeController::class, 'acknowledge'])->name('acknowledge');

// Game routes (authenticated)
Route::middleware(['auth', 'verified'])->prefix('game')->name('game.')->group(function () {
    // Main pages
    Route::get('/dashboard', [GameController::class, 'dashboard'])->name('dashboard');
    Route::get('/inventory', [GameController::class, 'inventory'])->name('inventory');
    Route::get('/sku/{location}/{sku}', [GameController::class, 'skuDetail'])->name('sku-detail');
    Route::get('/ordering', [GameController::class, 'ordering'])->name('ordering');
    Route::get('/transfers', [GameController::class, 'transfers'])->name('transfers');
    Route::get('/vendors', [GameController::class, 'vendors'])->name('vendors');
    Route::get('/vendors/{vendor}', [GameController::class, 'vendorDetail'])->name('vendor-detail');
    Route::get('/analytics', [GameController::class, 'analytics'])->name('analytics');
    Route::get('/spike-history', [GameController::class, 'spikeHistory'])->name('spike-history');
    Route::get('/reports', [GameController::class, 'wasteReports'])->name('reports');
    Route::get('/strategy', [GameController::class, 'strategy'])->name('strategy');

    // Actions
    Route::post('/advance-day', [GameController::class, 'advanceDay'])->name('advance-day');
    Route::post('/orders', [GameController::class, 'placeOrder'])->name('orders.store');
    Route::post('/transfers', [GameController::class, 'createTransfer'])->name('transfers.store');
    Route::put('/policy', [GameController::class, 'updatePolicy'])->name('policy.update');
    Route::post('/alerts/{alert}/read', [GameController::class, 'markAlertRead'])->name('alerts.read');
});

// Legacy dashboard redirect
Route::redirect('dashboard', '/game/dashboard')->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
