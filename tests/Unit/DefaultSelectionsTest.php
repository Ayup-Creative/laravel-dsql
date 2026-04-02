<?php

namespace Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;
use AyupCreative\AdvancedSearch\Registry\CastRegistry;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use AyupCreative\AdvancedSearch\Registry\DynamicValueRegistry;
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use PHPUnit\Framework\TestCase;

class DefaultSelectionsTest extends TestCase
{
    protected AdvancedSearch $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = new AdvancedSearch(
            new ColumnRegistry,
            new OperatorRegistry,
            new DynamicValueRegistry,
            new CastRegistry
        );
    }

    public function test_it_returns_default_selections_from_attribute()
    {
        $selections = $this->search->getSelections('', ModelWithAttribute::class);

        $this->assertCount(2, $selections);
        $this->assertEquals('id', $selections[0]['name']);
        $this->assertEquals('name', $selections[1]['name']);
        $this->assertEquals('[id]', $selections[0]['expression']);
    }

    public function test_it_returns_default_selections_from_method()
    {
        $selections = $this->search->getSelections('', ModelWithMethod::class);

        $this->assertCount(1, $selections);
        $this->assertEquals('status', $selections[0]['name']);
    }

    public function test_it_fallbacks_to_fillable()
    {
        $selections = $this->search->getSelections('', ModelWithFillable::class);

        $this->assertCount(2, $selections);
        $this->assertEquals('sku', $selections[0]['name']);
        $this->assertEquals('price', $selections[1]['name']);
    }

    public function test_it_prefers_query_selections_over_defaults()
    {
        $selections = $this->search->getSelections('SELECT [total]', ModelWithAttribute::class);

        $this->assertCount(1, $selections);
        $this->assertEquals('total', $selections[0]['name']);
    }

    public function test_it_returns_defaults_when_where_is_present_but_no_select()
    {
        $selections = $this->search->getSelections('[status]:equals"active"', ModelWithAttribute::class);

        $this->assertCount(2, $selections);
        $this->assertEquals('id', $selections[0]['name']);
        $this->assertEquals('name', $selections[1]['name']);
    }
}

#[DefaultSelections(['id', 'name'])]
class ModelWithAttribute {}

class ModelWithMethod
{
    public static function getAdvancedSearchDefaultSelections(): array
    {
        return ['status'];
    }
}

class ModelWithFillable
{
    public function getFillable(): array
    {
        return ['sku', 'price'];
    }
}
