<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Product::create(['name' => 'A', 'price' => 100, 'status' => 'active', 'stock' => 10]);
        Product::create(['name' => 'B', 'price' => 200, 'status' => 'active', 'stock' => 5]);
    }

    public function test_it_can_select_simple_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, 'SELECT [name], [price]');
        $results = $query->get();

        $this->assertCount(2, $results);
        $this->assertEquals('A', $results[0]->name);
        $this->assertEquals(100, $results[0]->price);
        $this->assertNull($results[0]->stock); // Not selected
    }

    public function test_it_can_alias_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, 'SELECT [name] AS "product_name", [price] AS [cost]');
        $results = $query->get();

        $this->assertEquals('A', $results[0]->product_name);
        $this->assertEquals(100, $results[0]->cost);
    }

    public function test_it_can_use_arithmetic_in_select()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, 'SELECT [name], [price] * 1.2 AS "vat_price"');
        $results = $query->get();

        $this->assertEquals(120, $results[0]->vat_price);
    }

    public function test_it_can_combine_select_and_where()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, 'SELECT [name] WHERE [price]:gt 150');
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function test_it_can_use_calculated_virtual_columns_in_select()
    {
        $search = app(AdvancedSearch::class);
        $search->columns()->registerExpression('total_value', '[price] * [stock]', [], Product::class);

        $query = Product::query();
        $search->apply($query, 'SELECT [name], [total_value] AS "value"');
        $results = $query->orderBy('name')->get();

        $this->assertEquals(1000, $results[0]->value); // 100 * 10
        $this->assertEquals(1000, $results[1]->value); // 200 * 5
    }

    public function test_it_can_select_attribute_based_calculated_columns()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, 'SELECT [name], [vat] WHERE [price]:equals 100');
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals(20, $results[0]->vat); // 100 * 0.2
    }

    public function test_it_defaults_to_select_all_if_no_select_provided()
    {
        $search = app(AdvancedSearch::class);
        $query = Product::query();

        $search->apply($query, '[name]:equals"A"');
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
        $this->assertEquals(100, $results[0]->price);
        $this->assertEquals(10, $results[0]->stock);
    }

    public function test_it_can_select_virtual_column_with_resolver_and_expression()
    {
        $search = app(AdvancedSearch::class);
        $query = ProductWithBoth::query();
        ProductWithBoth::$resolverCalled = false;

        $search->apply($query, 'SELECT [both_col] WHERE [both_col]:equals 100');

        $sql = $query->toSql();
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals(100, $results[0]->both_col);
        $this->assertTrue(ProductWithBoth::$resolverCalled);
    }
}

/** @internal */
class ProductWithBoth extends Product
{
    protected $table = 'products';

    public static bool $resolverCalled = false;

    #[VirtualColumn('both_col', expression: '[price]')]
    public static function searchBoth($query, $op, $val)
    {
        static::$resolverCalled = true;
        $query->where('price', $op, $val);
    }
}
