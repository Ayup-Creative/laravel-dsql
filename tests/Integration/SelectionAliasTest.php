<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SelectionAliasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Product::create(['name' => 'Widget', 'price' => 100, 'status' => 'active']);
    }

    public function test_it_can_use_aliases_in_selection_arithmetic()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // select [price] * 0.1 as "tax", [tax] * 2 as "double_tax"
        $input = 'SELECT [price] * 0.1 AS "tax", [tax] * 2 AS "double_tax"';

        $search->apply($query, $input);

        $result = $query->first();

        // (100 * 0.1) = 10
        $this->assertEquals(10, $result->tax);
        // (10 * 2) = 20
        $this->assertEquals(20, $result->double_tax);
    }

    public function test_user_example()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // SELECT [price], [price] * 0.3 AS "ws", [price] * 0.3 AS "fl", [price] - [ws] - [fl] AS "balance"
        $input = 'SELECT [price], [price] * 0.3 AS "ws", [price] * 0.3 AS "fl", [price] - [ws] - [fl] AS "balance"';

        $search->apply($query, $input);

        $result = $query->first();

        $this->assertEquals(100, $result->price);
        $this->assertEquals(30, $result->ws);
        $this->assertEquals(30, $result->fl);
        $this->assertEquals(40, $result->balance);
    }

    public function test_with_casts()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // Register a dummy cast
        $search->casts()->register('money', fn ($val) => '$'.number_format($val, 2));

        // SELECT [price], cast([price] * 0.3, 'money') AS "ws", [ws] AS "ws_copy"
        $input = 'SELECT [price], CAST([price] * 0.3, "money") AS "ws", [ws] AS "ws_copy"';

        $search->apply($query, $input);

        $result = $query->first();

        $selections = $result->getSelections($input);
        $wsSelection = collect($selections)->firstWhere('name', 'ws');
        $wsCopySelection = collect($selections)->firstWhere('name', 'ws_copy');

        $this->assertEquals(100, $result->price);
        $this->assertEquals(30, $result->ws);
        $this->assertEquals(30, $result->ws_copy);

        // Verify getSelectionValue uses cast
        $this->assertEquals('$30.00', $result->getSelectionValue('ws', $wsSelection));
        $this->assertEquals('$30.00', $result->getSelectionValue('ws_copy', $wsCopySelection));
    }

    public function test_alias_in_where_clause()
    {
        $search = app(AdvancedSearch::class);

        Product::create(['name' => 'Cheap', 'price' => 50, 'status' => 'active']);
        Product::create(['name' => 'Expensive', 'price' => 500, 'status' => 'active']);

        $query = Product::query();

        // SELECT [name], [price] * 0.1 AS "tax" WHERE [tax]:gt 10
        $input = 'SELECT [name], [price] * 0.1 AS "tax" WHERE [tax]:gt 10';

        $search->apply($query, $input);

        $results = $query->get();

        // Widget (100 -> tax 10) - excluded because 10 is not gt 10
        // Cheap (50 -> tax 5) - excluded
        // Expensive (500 -> tax 50) - included
        $this->assertCount(1, $results);
        $this->assertEquals('Expensive', $results[0]->name);
        $this->assertEquals(50, $results[0]->tax);
    }
}
