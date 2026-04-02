<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = $this->app->make(AdvancedSearch::class);
        $this->search->columns()->registerFromClass(Product::class);
    }

    public function test_it_filters_products_by_status()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'B', 'status' => 'inactive', 'price' => 200]);

        $query = Product::query();
        $this->search->apply($query, '[status]:equals"active"');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_filters_with_and_logic()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'B', 'status' => 'active', 'price' => 200]);
        Product::create(['name' => 'C', 'status' => 'inactive', 'price' => 100]);

        $query = Product::query();
        $this->search->apply($query, '[status]:equals"active" AND [price]:gt 150');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function test_it_supports_in_operator()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'B', 'status' => 'pending', 'price' => 200]);
        Product::create(['name' => 'C', 'status' => 'closed', 'price' => 300]);

        $query = Product::query();
        $this->search->apply($query, '[status]:in(active, pending)');

        $results = $query->get();
        $this->assertCount(2, $results);
    }

    public function test_it_supports_between_operator()
    {
        Product::create(['name' => 'A', 'price' => 100, 'status' => 'active']);
        Product::create(['name' => 'B', 'price' => 200, 'status' => 'active']);
        Product::create(['name' => 'C', 'price' => 300, 'status' => 'active']);

        $query = Product::query();
        $this->search->apply($query, '[price]:between(150, 250)');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function test_it_supports_column_to_column_comparison()
    {
        Product::create(['name' => 'A', 'price' => 100, 'status' => '100']);
        Product::create(['name' => 'B', 'price' => 200, 'status' => '100']);

        $query = Product::query();
        $this->search->apply($query, '[price]:gt[status]');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function test_it_supports_contains_operator()
    {
        Product::create(['name' => 'Apple', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'Banana', 'status' => 'active', 'price' => 100]);

        $query = Product::query();
        $this->search->apply($query, '[name]:contains"nan"');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Banana', $results[0]->name);
    }

    public function test_it_supports_sorting_and_limit()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 300]);
        Product::create(['name' => 'B', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'C', 'status' => 'active', 'price' => 200]);

        $query = Product::query();
        $this->search->apply($query, '[status]:equals"active" sort(price, asc) limit(2)');

        $results = $query->get();
        $this->assertCount(2, $results);
        $this->assertEquals('B', $results[0]->name);
        $this->assertEquals('C', $results[1]->name);
    }

    public function test_it_can_access_registries()
    {
        $this->assertInstanceOf(AdvancedSearch::class, $this->search);
        $this->assertNotEmpty($this->search->columns());
        $this->assertNotEmpty($this->search->operators());
    }

    public function test_it_supports_equals_with_list_as_in_shortcut()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'B', 'status' => 'pending', 'price' => 200]);

        $query = Product::query();
        $this->search->apply($query, '[status]:equals(active, pending)');

        $results = $query->get();
        $this->assertCount(2, $results);
    }
}
