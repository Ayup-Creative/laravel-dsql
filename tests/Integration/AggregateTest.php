<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\Models\ProductLog;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AggregateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_it_handles_exists_condition()
    {
        $p1 = Product::create(['name' => 'Has Logs', 'price' => 100, 'status' => 'active']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 1']);

        Product::create(['name' => 'No Logs', 'price' => 200, 'status' => 'active']);

        $search = app(AdvancedSearch::class);

        // WHERE EXISTS([logs])
        $query = Product::query();
        $search->apply($query, 'EXISTS([logs])');
        $this->assertEquals(1, $query->count());
        $this->assertEquals('Has Logs', $query->first()->name);

        // WHERE NOT EXISTS([logs])
        $query = Product::query();
        $search->apply($query, 'NOT EXISTS([logs])');
        $this->assertEquals(1, $query->count());
        $this->assertEquals('No Logs', $query->first()->name);

        // WHERE EXISTS([logs]):equals false
        $query = Product::query();
        $search->apply($query, 'EXISTS([logs]):equals false');
        $this->assertEquals(1, $query->count());
        $this->assertEquals('No Logs', $query->first()->name);
    }

    public function test_it_handles_count_condition()
    {
        $p1 = Product::create(['name' => 'Product 1', 'price' => 100, 'status' => 'active']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 1']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 2']);

        $p2 = Product::create(['name' => 'Product 2', 'price' => 200, 'status' => 'active']);
        ProductLog::create(['product_id' => $p2->id, 'message' => 'Log 3']);

        $search = app(AdvancedSearch::class);

        // COUNT([logs]):gt 1
        $query = Product::query();
        $search->apply($query, 'COUNT([logs]):gt 1');
        $this->assertEquals(1, $query->count());
        $this->assertEquals('Product 1', $query->first()->name);

        // COUNT([logs]):equals 1
        $query = Product::query();
        $search->apply($query, 'COUNT([logs]):equals 1');
        $this->assertEquals(1, $query->count());
        $this->assertEquals('Product 2', $query->first()->name);
    }

    public function test_it_handles_aggregates_in_selection()
    {
        $p1 = Product::create(['name' => 'Product 1', 'price' => 100, 'status' => 'active']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 1']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 2']);

        $search = app(AdvancedSearch::class);

        // SELECT [name], COUNT([logs]) AS "log_count", EXISTS([logs]) AS "has_logs"
        $dsql = 'SELECT [name], COUNT([logs]) AS "log_count", EXISTS([logs]) AS "has_logs"';
        $query = Product::query();
        $results = $search->apply($query, $dsql)->get();

        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]->log_count);
        $this->assertEquals(1, $results[0]->has_logs);

        // Verify metadata
        $selections = $search->getSelections($dsql, Product::class);
        $this->assertEquals('log_count', $selections[1]['name']);
        $this->assertEquals('COUNT([logs])', $selections[1]['expression']);
        $this->assertEquals('has_logs', $selections[2]['name']);
        $this->assertEquals('EXISTS([logs])', $selections[2]['expression']);

        // Test getSelectionValue via trait
        $this->assertEquals(2, $results[0]->getSelectionValue('log_count'));
        $this->assertEquals(1, $results[0]->getSelectionValue('has_logs'));
    }

    public function test_it_handles_complex_arithmetic_with_aggregates()
    {
        $p1 = Product::create(['name' => 'Product 1', 'price' => 100, 'status' => 'active']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 1']);
        ProductLog::create(['product_id' => $p1->id, 'message' => 'Log 2']);

        $search = app(AdvancedSearch::class);

        // SELECT [name], COUNT([logs]) * 10 AS "score"
        $query = Product::query();
        $results = $search->apply($query, 'SELECT [name], COUNT([logs]) * 10 AS "score"')->get();

        $this->assertCount(1, $results);
        $this->assertEquals(20, $results[0]->score);
    }
}
