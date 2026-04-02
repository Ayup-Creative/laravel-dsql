<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DefaultSelectionApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('amount');
            $table->timestamps();
        });

        Report::create(['name' => 'Report 1', 'amount' => 1000]);
    }

    public function test_it_applies_default_selections_when_no_select_is_in_dsl()
    {
        $search = app(AdvancedSearch::class);
        $query = Report::query();

        // No SELECT in DSL
        $search->apply($query, '[amount]:gt 500');

        $result = $query->first();

        // Should have id, amount, and purchase_price (virtual)
        // name should NOT be selected because it's not in DefaultSelections
        $this->assertArrayHasKey('id', $result->getAttributes());
        $this->assertArrayHasKey('amount', $result->getAttributes());
        $this->assertArrayHasKey('purchase_price', $result->getAttributes());
        $this->assertArrayNotHasKey('name', $result->getAttributes());

        $this->assertEquals(10.00, $result->purchase_price);
    }

    public function test_it_does_not_apply_default_selections_when_explicit_select_is_in_dsl()
    {
        $search = app(AdvancedSearch::class);
        $query = Report::query();

        // Explicit SELECT in DSL
        $search->apply($query, 'SELECT [name] WHERE [amount]:gt 500');

        $result = $query->first();

        $this->assertArrayHasKey('name', $result->getAttributes());
        $this->assertArrayNotHasKey('amount', $result->getAttributes());
        $this->assertArrayNotHasKey('purchase_price', $result->getAttributes());
    }
}

#[DefaultSelections(['id', 'amount', 'purchase_price'])]
class Report extends Model
{
    protected $guarded = [];

    #[VirtualColumn('purchase_price', expression: 'CAST(amount / 100 AS DECIMAL(10,2))')]
    public static function searchPurchasePrice() {}
}
