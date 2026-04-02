<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Category;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class RelationshipSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Electronics']);
        $p1 = Product::create([
            'name' => 'Smartphone',
            'status' => 'active',
            'price' => 500,
            'category_id' => $category->id,
        ]);
        $detail = $p1->detail()->create(['sku' => 'SM-123']);
        $detail->logs()->create(['message' => 'Logged']);

        $p2 = Product::create([
            'name' => 'Laptop',
            'status' => 'active',
            'price' => 1000,
            'category_id' => $category->id,
        ]);
        $detail2 = $p2->detail()->create(['sku' => 'LP-456']);
        $detail2->logs()->create(['message' => 'Logged LP']);
    }

    public function test_it_can_select_nested_relationship_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // SELECT [detail.logs.message]
        $search->apply($query, 'SELECT [name], [detail.logs.message] WHERE [status]:equals"active"');

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->relationLoaded('detail'));
        // For hasMany, it should be loaded on detail
        $this->assertTrue($results[0]->detail->relationLoaded('logs'));

        $selections = $search->getSelections('SELECT [name], [detail.logs.message] WHERE [status]:equals"active"', Product::class);
        // HasMany might need data_get to access first item?
        // Our Searchable trait uses data_get.
        // For hasMany, data_get(product, 'detail.logs.0.message') would work.
        // But [detail.logs.message] on a hasMany collection might return an array if using data_get?
        // Let's see.
        $val = $results[0]->getSelectionValue('detail.logs.message', $selections[1]);
        $this->assertIsArray($val);
        $this->assertEquals('Logged', $val[0]);
    }

    public function test_it_can_select_has_one_relationship_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // SELECT [detail.sku] (no ID selected explicitly)
        $search->apply($query, 'SELECT [name], [detail.sku] WHERE [status]:equals"active"');

        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->relationLoaded('detail'));

        $selections = $search->getSelections('SELECT [name], [detail.sku] WHERE [status]:equals"active"', Product::class);
        $this->assertEquals('SM-123', $results[0]->getSelectionValue('detail.sku', $selections[1]));
    }

    public function test_it_can_select_relationship_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // SELECT [category.name]
        $search->apply($query, 'SELECT [name], [category.name] WHERE [status]:equals"active"');

        $results = $query->get();

        $this->assertTrue($results[0]->relationLoaded('category'));
        $this->assertCount(2, $results);
        $this->assertEquals('Smartphone', $results[0]->name);

        // Use getSelectionValue to get the relationship value
        $selections = $search->getSelections('SELECT [name], [category.name] WHERE [status]:equals"active"', Product::class);

        $this->assertEquals('Electronics', $results[0]->getSelectionValue('category.name', $selections[1]));
        $this->assertEquals('Electronics', $results[1]->getSelectionValue('category.name', $selections[1]));
    }

    public function test_it_can_alias_relationship_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        // SELECT [category.name] AS "cat"
        $search->apply($query, 'SELECT [name], [category.name] AS "cat" WHERE [status]:equals"active"');

        $results = $query->get();

        $this->assertTrue($results[0]->relationLoaded('category'));

        $selections = $search->getSelections('SELECT [name], [category.name] AS "cat" WHERE [status]:equals"active"', Product::class);

        // The selection name should be "cat"
        $this->assertEquals('cat', $selections[1]['name']);

        // getSelectionValue should resolve "cat" via its expression [category.name]
        $this->assertEquals('Electronics', $results[0]->getSelectionValue('cat', $selections[1]));
    }
}
