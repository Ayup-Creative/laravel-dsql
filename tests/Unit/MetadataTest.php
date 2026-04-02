<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class MetadataTest extends TestCase
{
    public function test_it_collects_metadata_from_attributes()
    {
        $search = app(AdvancedSearch::class);
        $selections = $search->getSelections('SELECT [price]', MetadataProduct::class);

        $this->assertEquals(['cast' => 'currency'], $selections[0]['metadata']);
    }

    public function test_it_collects_metadata_from_class_level_attributes()
    {
        $search = app(AdvancedSearch::class);
        $selections = $search->getSelections('SELECT [vat]', MetadataProduct::class);

        $this->assertEquals(['type' => 'calculated'], $selections[0]['metadata']);
    }

    public function test_it_includes_metadata_in_default_selections()
    {
        $search = app(AdvancedSearch::class);
        $selections = $search->getSelections('', MetadataProduct::class);

        // Selections are: price, vat
        $this->assertCount(2, $selections);

        $this->assertEquals('price', $selections[0]['name']);
        $this->assertEquals(['cast' => 'currency'], $selections[0]['metadata']);

        $this->assertEquals('vat', $selections[1]['name']);
        $this->assertEquals(['type' => 'calculated'], $selections[1]['metadata']);
    }

    public function test_searchable_trait_provides_convenient_methods()
    {
        $product = new MetadataProduct(['price' => 1000]);

        $selections = $product->getSelections();
        $this->assertCount(2, $selections);

        $this->assertEquals('price', $selections[0]['name']);
        $this->assertEquals('$10.00', $product->getSelectionValue('price'));
    }

    public function test_searchable_trait_allows_custom_formatting()
    {
        $product = new MetadataProduct(['price' => 1000]);

        $this->assertEquals('$10.00', $product->getSelectionValue('price'));
    }
}

#[DefaultSelections(['price', 'vat'])]
#[VirtualColumn('vat', expression: '[price] * 0.2', metadata: ['type' => 'calculated'])]
class MetadataProduct extends Model implements Queryable
{
    use Searchable;

    protected $guarded = [];

    #[VirtualColumn('price', metadata: ['cast' => 'currency'])]
    public static function searchByPrice($query, $op, $val)
    {
        $query->where('price', $op, $val);
    }

    public function formatPriceSearchValue($value)
    {
        return '$'.number_format($value / 100, 2);
    }
}
