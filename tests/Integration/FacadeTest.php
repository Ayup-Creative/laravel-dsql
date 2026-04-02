<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FacadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdvancedSearch::columns()->registerFromClass(Product::class);
    }

    public function test_it_filters_products_using_facade()
    {
        Product::create(['name' => 'A', 'status' => 'active', 'price' => 100]);
        Product::create(['name' => 'B', 'status' => 'inactive', 'price' => 200]);

        $query = Product::query();
        AdvancedSearch::apply($query, '[status]:equals"active"');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_can_access_registries_via_facade()
    {
        $this->assertNotEmpty(AdvancedSearch::columns());
        $this->assertNotEmpty(AdvancedSearch::operators());
        $this->assertNotEmpty(AdvancedSearch::dynamicValues());
        $this->assertNotEmpty(AdvancedSearch::casts());
    }
}
