<?php

/**
 * Master game data: Products, Categories, and Vendors.
 * This is the single source of truth, mirroring constants.ts.
 */

return [
    /**
     * All 11 product categories used in the game.
     */
    'categories' => [
        'Beans',
        'Milk',
        'Cups',
        'Syrup',
        'Tea',
        'Sugar',
        'Cleaning',
        'Seasonal',
        'Food',
        'Sauce',
        'Pastry',
    ],

    /**
     * Product definitions matching constants.ts ITEMS array.
     */
    'products' => [
        [
            'item_id' => 'item-1',
            'name' => 'Espresso Blend',
            'category' => 'Beans',
            'unit' => 'kg',
            'is_perishable' => false,
            'bulk_threshold' => 50,
            'storage_cost' => 0.50,
            'estimated_shelf_life' => 180,
        ],
        [
            'item_id' => 'item-2',
            'name' => 'Oat Milk',
            'category' => 'Milk',
            'unit' => 'L',
            'is_perishable' => true,
            'bulk_threshold' => 100,
            'storage_cost' => 0.20,
            'estimated_shelf_life' => 21,
        ],
        [
            'item_id' => 'item-3',
            'name' => '12oz Paper Cups',
            'category' => 'Cups',
            'unit' => 'packs (50)',
            'is_perishable' => false,
            'bulk_threshold' => 200,
            'storage_cost' => 0.10,
            'estimated_shelf_life' => 730,
        ],
        [
            'item_id' => 'item-4',
            'name' => 'Vanilla Syrup',
            'category' => 'Syrup',
            'unit' => 'bottle',
            'is_perishable' => false,
            'bulk_threshold' => 20,
            'storage_cost' => 0.30,
            'estimated_shelf_life' => 365,
        ],
        [
            'item_id' => 'item-5',
            'name' => 'Earl Grey Tea',
            'category' => 'Tea',
            'unit' => 'box (50)',
            'is_perishable' => false,
            'bulk_threshold' => 30,
            'storage_cost' => 0.10,
            'estimated_shelf_life' => 365,
        ],
        [
            'item_id' => 'item-6',
            'name' => 'Raw Sugar',
            'category' => 'Sugar',
            'unit' => 'kg',
            'is_perishable' => false,
            'bulk_threshold' => 40,
            'storage_cost' => 0.20,
            'estimated_shelf_life' => 1000,
        ],
        [
            'item_id' => 'item-7',
            'name' => 'Sanitizer Spray',
            'category' => 'Cleaning',
            'unit' => 'bottle',
            'is_perishable' => false,
            'bulk_threshold' => 15,
            'storage_cost' => 0.40,
            'estimated_shelf_life' => 730,
        ],
        [
            'item_id' => 'item-8',
            'name' => 'Pumpkin Spice Sauce',
            'category' => 'Seasonal',
            'unit' => 'jug (1.8L)',
            'is_perishable' => true,
            'bulk_threshold' => 20,
            'storage_cost' => 0.50,
            'estimated_shelf_life' => 14,
        ],
        [
            'item_id' => 'item-9',
            'name' => 'Bacon Gouda Sandwich',
            'category' => 'Food',
            'unit' => 'case (24)',
            'is_perishable' => true,
            'bulk_threshold' => 10,
            'storage_cost' => 1.50,
            'estimated_shelf_life' => 90,
        ],
        [
            'item_id' => 'item-10',
            'name' => 'Dark Mocha Sauce',
            'category' => 'Sauce',
            'unit' => 'jug (1.8L)',
            'is_perishable' => true,
            'bulk_threshold' => 25,
            'storage_cost' => 0.50,
            'estimated_shelf_life' => 30,
        ],
        [
            'item_id' => 'item-11',
            'name' => 'Almond Milk',
            'category' => 'Milk',
            'unit' => 'L',
            'is_perishable' => true,
            'bulk_threshold' => 80,
            'storage_cost' => 0.20,
            'estimated_shelf_life' => 30,
        ],
        [
            'item_id' => 'item-12',
            'name' => 'Butter Croissant',
            'category' => 'Pastry',
            'unit' => 'case (12)',
            'is_perishable' => true,
            'bulk_threshold' => 10,
            'storage_cost' => 1.00,
            'estimated_shelf_life' => 3,
        ],
    ],

    /**
     * Vendor definitions with category assignments.
     * Categories array defines which product categories this vendor sells.
     */
    'vendors' => [
        [
            'id' => 'sup-1',
            'name' => 'BeanCo Global',
            'reliability_score' => 95.00,
            'categories' => ['Beans', 'Cups', 'Tea', 'Sauce'],
            'metrics' => [
                'late_rate' => 0.02,
                'fill_rate' => 0.99,
                'complaint_rate' => 0.01,
            ],
        ],
        [
            'id' => 'sup-2',
            'name' => 'RapidSupplies',
            'reliability_score' => 85.00,
            'categories' => ['Cups', 'Syrup', 'Pastry', 'Cleaning', 'Food'],
            'metrics' => [
                'late_rate' => 0.08,
                'fill_rate' => 0.94,
                'complaint_rate' => 0.03,
            ],
        ],
        [
            'id' => 'sup-3',
            'name' => 'Dairy Direct',
            'reliability_score' => 98.00,
            'categories' => ['Milk'],
            'metrics' => [
                'late_rate' => 0.01,
                'fill_rate' => 0.995,
                'complaint_rate' => 0.005,
            ],
        ],
        [
            'id' => 'sup-4',
            'name' => 'ValueBulk',
            'reliability_score' => 70.00,
            'categories' => ['Beans', 'Cups', 'Syrup', 'Sugar', 'Seasonal'],
            'metrics' => [
                'late_rate' => 0.25,
                'fill_rate' => 0.85,
                'complaint_rate' => 0.12,
            ],
        ],
    ],
];
