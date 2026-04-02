<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\Models\Product;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class CastTest extends TestCase
{
    public function test_it_parses_cast_in_selection()
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);

        $query = 'SELECT CAST([price] * 1.2, "money") AS "vat_price" WHERE [price]:gt 100';
        $selections = $search->getSelections($query, Product::class);

        $this->assertCount(1, $selections);
        $this->assertEquals('vat_price', $selections[0]['name']);
        $this->assertEquals('money', $selections[0]['cast']);
        $this->assertTrue($selections[0]['is_alias']);
        $this->assertEquals('CAST(([price] * 1.2), \'money\')', $selections[0]['expression']);
    }

    public function test_it_applies_registered_cast()
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);

        $search->casts()->register('money', function ($value, $model) {
            return '$'.number_format($value, 2);
        });

        $product = new Product(['name' => 'Test', 'price' => 100]);
        $product->vat_price = 120; // Simulated result of selection

        $selection = [
            'name' => 'vat_price',
            'cast' => 'money',
        ];

        $formatted = $product->getSelectionValue('vat_price', $selection);

        $this->assertEquals('$120.00', $formatted);
    }

    public function test_it_compiles_cast_expression_by_unwrapping_it()
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);

        $query = Product::query();
        $dsl = 'SELECT CAST([price] * 0.3, "tax") AS "tax_amount" WHERE [price]:gt 100';

        // We just want to make sure it doesn't crash and compiles the inner expression
        $search->apply($query, $dsl);

        $sql = $query->toSql();
        // Quote styles vary by database
        $this->assertStringContainsString('(price * ?) as tax_amount', strtolower($sql));
        $this->assertEquals([0.3, 100], $query->getBindings());
    }
}
