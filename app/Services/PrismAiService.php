<?php

namespace App\Services;

use App\DTOs\InventoryAdvisoryDTO;
use App\DTOs\InventoryContextDTO;
use App\Interfaces\AiProviderInterface;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

class PrismAiService implements AiProviderInterface
{
    public function generateAdvisory(InventoryContextDTO $context): InventoryAdvisoryDTO
    {
        // Define the schema for the structured output
        $schema = new ObjectSchema(
            name: 'inventory_advisory',
            description: 'Inventory restocking advice',
            properties: [
                new NumberSchema('restockAmount', 'The recommended quantity to restock'),
                new StringSchema('reasoning', 'Explanation for the recommendation'),
                new NumberSchema('confidenceScore', 'Confidence level between 0.0 and 1.0'),
                new StringSchema('suggestedAction', 'Short action suggestion (e.g., reorder, wait)'),
            ],
            requiredFields: ['restockAmount', 'reasoning', 'confidenceScore', 'suggestedAction']
        );

        // Construct the prompt
        $prompt = $this->buildPrompt($context);

        // Generate structured output
        $response = Prism::structured()
            ->using(Provider::Gemini, 'gemini-3-flash-preview') 
            ->withSchema($schema)
            ->withPrompt($prompt)
            ->withClientOptions(['timeout' => 30])
            ->asStructured();
            
        $data = $response->structured;

        return new InventoryAdvisoryDTO(
            restockAmount: (int) $data['restockAmount'],
            reasoning: $data['reasoning'],
            confidenceScore: (float) $data['confidenceScore'],
            suggestedAction: $data['suggestedAction']
        );
    }

    protected function buildPrompt(InventoryContextDTO $context): string
    {
        return <<<EOT
Analyze the following inventory context and provide a restocking recommendation:

Product: {$context->productName}
Current Stock: {$context->quantity}
Average Daily Sales: {$context->averageDailySales}
Lead Time: {$context->leadTimeDays} days
EOT;
    }
}
