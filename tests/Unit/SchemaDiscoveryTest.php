<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Category;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class SchemaDiscoveryTest extends TestCase
{
    public function test_it_discovers_available_columns_and_relationships()
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);

        // This is the method we want to implement
        $schema = $search->getSchema(Product::class);

        // Product has:
        // Columns: id, name, price, category_id, created_at, updated_at
        // VirtualColumns: status, alias, price_alias, name (duplicate), stock, price (duplicate), updated_at (duplicate), category.name, vat
        // Relationships: category, detail

        $names = array_column($schema, 'name');

        $this->assertContains('name', $names);
        $this->assertContains('price', $names);
        $this->assertContains('category', $names);
        $this->assertContains('detail', $names);
        $this->assertContains('status', $names);
        $this->assertContains('alias', $names);
        $this->assertContains('stock', $names);
        $this->assertContains('vat', $names);
        $this->assertNotContains('category.name', $names);

        // Verify types
        $category = collect($schema)->firstWhere('name', 'category');
        $this->assertEquals('relationship', $category['type']);
        $this->assertEquals(Category::class, $category['model']);

        $name = collect($schema)->firstWhere('name', 'name');
        $this->assertEquals('column', $name['type']);
    }
}
