<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArithmeticTest extends TestCase
{
    use RefreshDatabase;

    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = $this->app->make(AdvancedSearch::class);
        $this->search->columns()->registerFromClass(Product::class);
    }

    public function test_it_supports_simple_arithmetic_on_columns()
    {
        Product::create(['name' => 'A', 'price' => 120, 'status' => 'active']);
        Product::create(['name' => 'B', 'price' => 100, 'status' => 'active']);

        $query = Product::query();
        // [price] / 1.2 :equals 100  => 120 / 1.2 = 100. So 'A' should match.
        $this->search->apply($query, '[price] / 1.2 :equals 100');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_supports_complex_arithmetic()
    {
        Product::create(['name' => 'A', 'price' => 100, 'status' => 'active']); // (100 + 50) * 2 = 300
        Product::create(['name' => 'B', 'price' => 50, 'status' => 'active']);  // (50 + 50) * 2 = 200

        $query = Product::query();
        $this->search->apply($query, '([price] + 50) * 2 :gt 250');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_supports_column_to_column_arithmetic()
    {
        // We'll use price and another numeric column.
        // Our Product model doesn't have many numeric columns besides price.
        // Let's use id as a dummy numeric value for testing.

        $p1 = Product::create(['name' => 'A', 'price' => 100, 'status' => 'active']);
        $p2 = Product::create(['name' => 'B', 'price' => 100, 'status' => 'active']);

        $query = Product::query();
        // [price] + [id] :gt 101.
        // p1.id is likely 1, p2.id is 2.
        // 100 + 1 = 101 (not gt 101)
        // 100 + 2 = 102 (gt 101)
        $this->search->apply($query, '[price] + [id] :gt 101');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function test_it_supports_expression_aliases()
    {
        $this->search->columns()->registerExpression('vat_local', '[price] / 1.2');

        Product::create(['name' => 'A', 'price' => 120, 'status' => 'active']);
        Product::create(['name' => 'B', 'price' => 100, 'status' => 'active']);

        $query = Product::query();
        $this->search->apply($query, '[vat_local]:equals 100');

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_supports_expression_aliases_from_attributes()
    {
        // We need a model with the attribute
        $model = new class extends Product
        {
            use HasFactory;

            protected $table = 'products';

            #[VirtualColumn('vat_attr', expression: '[price] / 1.2')]
            public static function dummy($query, $op, $val)
            {
                $query->whereRaw("price / 1.2 {$op} ?", [$val]);
            }
        };

        Product::create(['name' => 'A', 'price' => 120, 'status' => 'active']);
        Product::create(['name' => 'B', 'price' => 100, 'status' => 'active']);

        $query = $model->newQuery();
        $this->search->apply($query, '[vat_attr]:equals 100');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);
    }

    public function test_it_supports_other_operators_on_calculated_columns()
    {
        $this->search->columns()->registerExpression('vat_local_2', '[price] / 1.2');

        Product::create(['name' => 'A', 'price' => 120, 'status' => 'active']); // vat = 100
        Product::create(['name' => 'B', 'price' => 240, 'status' => 'active']); // vat = 200

        $query = Product::query();
        $this->search->apply($query, '[vat_local_2]:between(90, 110)');
        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results[0]->name);

        $query = Product::query();
        $this->search->apply($query, '[vat_local_2]:in(100, 200)');
        $results = $query->get();
        $this->assertCount(2, $results);
    }
}
