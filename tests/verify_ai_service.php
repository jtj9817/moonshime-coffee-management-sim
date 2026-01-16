<?php
/**
 * Verification Script: AI Service (REAL API)
 * Verifies PrismAiService with actual Gemini API.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PrismAiService;
use App\DTOs\InventoryContextDTO;
use App\DTOs\InventoryAdvisoryDTO;

echo "--- Starting REAL API Verification: AI Service ---
";

try {
    echo ">> Creating Context DTO...\n";
    $context = new InventoryContextDTO(
        productId: 'prod_123',
        productName: 'Ethiopian Yirgacheffe',
        locationId: 'loc_main',
        quantity: 10,
        averageDailySales: 5.5,
        leadTimeDays: 7
    );

    echo ">> Instantiating PrismAiService...\n";
    $service = new PrismAiService();
    
    echo ">> Calling Gemini API (model: gemini-3-flash-preview)...
";
    $advisory = $service->generateAdvisory($context);
    
    echo "   - Advisory Generated Successfully!\n";
    echo "   - Suggestion: {$advisory->suggestedAction}\n";
    echo "   - Restock Amount: {$advisory->restockAmount}\n";
    echo "   - Confidence: {$advisory->confidenceScore}\n";
    echo "   - Reasoning: {$advisory->reasoning}\n";
    
    if ($advisory instanceof InventoryAdvisoryDTO) {
        echo "SUCCESS: Received valid advisory from AI.\n";
    }

} catch (\Throwable $e) {
    echo "ERROR: Service execution failed.\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "--- Verification Complete---
";