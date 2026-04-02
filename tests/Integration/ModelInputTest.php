<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class ModelInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /** @test */
    public function it_can_apply_using_model_fqn()
    {
        Product::create(['name' => 'Widget', 'price' => 100, 'status' => 'active']);
        Product::create(['name' => 'Gadget', 'price' => 200, 'status' => 'active']);

        $search = app(AdvancedSearch::class);
        $input = '[price]:gt 150';

        // Passing Product::class
        $results = $search->apply(Product::class, $input)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Gadget', $results->first()->name);
    }

    /** @test */
    public function it_can_apply_using_model_instance()
    {
        Product::create(['name' => 'Widget', 'price' => 100, 'status' => 'active']);
        Product::create(['name' => 'Gadget', 'price' => 200, 'status' => 'active']);

        $search = app(AdvancedSearch::class);
        $input = '[price]:gt 150';

        // Passing a model instance
        $results = $search->apply(new Product, $input)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Gadget', $results->first()->name);
    }
}
