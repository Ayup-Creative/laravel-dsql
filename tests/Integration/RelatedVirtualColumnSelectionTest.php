<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RelatedVirtualColumnSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('other_names');
            $table->string('last_name');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');
            $table->string('number');
            $table->timestamps();
        });

        $customer = Customer::create([
            'other_names' => 'John',
            'last_name' => 'Doe',
        ]);

        Order::create([
            'customer_id' => $customer->id,
            'number' => 'ORD-001',
        ]);
    }

    public function test_it_can_select_a_virtual_column_through_a_relationship()
    {
        /** @var AdvancedSearch $search */
        $search = app(AdvancedSearch::class);
        $query = Order::query();

        // Query: select [number], [customer.full_name]
        $search->apply($query, 'SELECT [number], [customer.full_name]');

        $results = $query->get();
        $this->assertCount(1, $results);

        $order = $results->first();
        $this->assertEquals('ORD-001', $order->number);

        // Check customer full_name directly first
        $this->assertEquals('John Doe', $order->customer->getSelectionValue('full_name'));

        // This is where it currently fails (returns null)
        $selections = $order->getSelections('SELECT [number], [customer.full_name]');
        $fullNameSelection = collect($selections)->where('name', 'customer.full_name')->first();

        $value = $order->getSelectionValue('customer.full_name', $fullNameSelection);

        $this->assertEquals('John Doe', $value);
    }
}

class Customer extends Model implements Queryable
{
    use Searchable;

    protected $fillable = ['other_names', 'last_name'];

    #[VirtualColumn('full_name', expression: "CONCAT(other_names, ' ', last_name)")]
    public static function searchByFullName($query, $op, $value)
    {
        $query->whereRaw("CONCAT(other_names, ' ', last_name) {$op} ?", [$value]);
    }
}

class Order extends Model implements Queryable
{
    use Searchable;

    protected $fillable = ['customer_id', 'number'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
