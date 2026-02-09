<?php

namespace Tests\Feature\Analytics\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_has_unit_price_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('products', 'unit_price'),
            'unit_price column should exist'
        );
    }

    public function test_products_table_has_category_index(): void
    {
        // Check for index existence. PostgreSQL specific check for robustness.
        $hasIndex = count(\DB::select("
            SELECT 1
            FROM pg_indexes
            WHERE tablename = 'products'
            AND indexdef LIKE '%(category)%'
        ")) > 0;

        $this->assertTrue($hasIndex, 'Index on products(category) should exist');
    }

    public function test_daily_reports_table_has_user_id_day_index(): void
    {
        // Check for composite index existence.
        $hasIndex = count(\DB::select("
            SELECT 1
            FROM pg_indexes
            WHERE tablename = 'daily_reports'
            AND indexdef LIKE '%(user_id, day)%'
        ")) > 0;

        $this->assertTrue($hasIndex, 'Index on daily_reports(user_id, day) should exist');
    }
}
