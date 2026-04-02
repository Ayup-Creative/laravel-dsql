<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AliasWithPercentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_it_handles_alias_with_percent_symbol()
    {
        Product::create(['name' => 'Widget', 'price' => 100, 'status' => 'active', 'category_id' => 1]);

        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // The issue: "VAT @ 20%" contains a percent symbol
        $input = 'select [price] * 1.2 as "VAT @ 20%" where [price]:equals 100';

        $results = $search->apply($query, $input)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(120, $results->first()->{'VAT @ 20%'});
    }
}
