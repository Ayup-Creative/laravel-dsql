<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\Models\ApplianceModel;
use AyupCreative\AdvancedSearch\Tests\Models\Receipt;
use AyupCreative\AdvancedSearch\Tests\Models\Registration;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VirtualColumnSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_it_handles_virtual_column_selection_with_dotted_expression()
    {
        $appliance = ApplianceModel::create(['name' => 'Washing Machine', 'standard_warranty_years' => 5]);
        $registration = Registration::create(['product_id' => $appliance->id]);
        $receipt = Receipt::create([
            'receipt_number' => 'REC001',
            'amount' => 500,
            'purchased_at' => now()->format('Y-m-d'),
            'registration_id' => $registration->id,
        ]);

        $search = app(AdvancedSearch::class);
        $model = new class extends Receipt
        {
            protected $table = 'receipts';

            #[VirtualColumn('standard_years', expression: '[registration.model.standard_warranty_years]')]
            public static function searchByApplianceStandardYears(Builder $query, $op, $value): void
            {
                // This will be used for WHERE.
                // But now we also have an expression which will be used for SELECT.
            }
        };

        $query = $model::query();
        $search->apply($query, 'SELECT [receipt_number], [registration_id], [standard_years] WHERE [receipt_number]:equals"REC001"');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('REC001', $results[0]->receipt_number);

        // standard_years in SQL will be NULL (because standard_years is not a column)
        // But getSelectionValue should use data_get to fetch it from relationship
        $selections = $results[0]->getSelections('SELECT [receipt_number], [registration_id], [standard_years]');

        $value = $results[0]->getSelectionValue('standard_years', $selections[2]);

        $this->assertEquals(5, $value);
    }
}
