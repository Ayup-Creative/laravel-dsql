<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class Receipt extends Model
{
    protected $table = 'test_receipts';

    protected $guarded = [];

    #[VirtualColumn('purchase_price')]
    public static function searchPurchasePrice($query, $op, $value): void
    {
        $query->where('amount', $op, $value);
    }
}

class AutoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_succeeds_with_auto_registration()
    {
        $search = $this->app->make(AdvancedSearch::class);

        Schema::create('test_receipts', function ($table) {
            $table->id();
            $table->integer('amount');
            $table->timestamps();
        });

        Receipt::create(['amount' => 500]);
        Receipt::create(['amount' => 100]);

        $query = Receipt::query();

        $search->apply($query, '[purchase_price]:equals 100');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals(100, $results[0]->amount);
    }
}
