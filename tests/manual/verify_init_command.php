<?php

// verify_init_command.php

use App\Models\Location;
use App\Models\Product;
use App\Models\Route;
use Illuminate\Support\Facades\Artisan;

// 1. Setup: Clear Global Data (simulate broken state)
echo "--- Simulating Broken World (Clearing DB) ---\n";
Route::truncate();
Location::truncate();
Product::truncate();

echo 'Products: '.Product::count()."\n";
echo 'Locations: '.Location::count()."\n";
echo 'Routes: '.Route::count()."\n";

// 2. Run Command
echo "\n--- Running Command ---\n";
Artisan::call('game:initialize-user', ['email' => 'polycarpus@tuta.io']);
echo Artisan::output();

// 3. Verify Restoration
echo "\n--- Verification ---\n";
echo 'Products: '.Product::count()."\n";
echo 'Locations: '.Location::count()."\n";
echo 'Routes: '.Route::count()."\n";

if (Product::count() > 0 && Location::count() > 0 && Route::count() > 0) {
    echo "\nSUCCESS: World data restored!\n";
} else {
    echo "\nFAILURE: World data still missing.\n";
}
