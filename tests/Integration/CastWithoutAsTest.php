<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CastWithoutAsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_it_handles_cast_without_as_in_select()
    {
        Product::create(['name' => 'Product 1', 'price' => 100, 'status' => 'active']);

        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // This should not throw an error
        $search->apply($query, 'SELECT CAST([price], "money") WHERE [name]:equals"Product 1"');

        $sql = $query->toSql();

        $result = $query->first();
        $this->assertNotNull($result);
        $this->assertEquals(100, $result->price);

        $selections = $search->getSelections('SELECT CAST([price], "money")');
        $this->assertCount(1, $selections);
        $this->assertEquals('price', $selections[0]['name']);
        $this->assertEquals('money', $selections[0]['metadata']['cast'] ?? null);
    }

    public function test_it_handles_complex_cast_without_as()
    {
        $search = app(AdvancedSearch::class);
        $selections = $search->getSelections('SELECT CAST([price] * 1.2, "money")');

        $this->assertCount(1, $selections);
        // What is the name here? Currently it might be empty or a random name.
        $this->assertNotEmpty($selections[0]['name']);
    }

    public function test_it_handles_arithmetic_without_as()
    {
        $search = app(AdvancedSearch::class);
        $selections = $search->getSelections('SELECT [price] * 1.2');

        $this->assertCount(1, $selections);
        $this->assertEquals('([price] * 1.2)', $selections[0]['name']);
    }
}
