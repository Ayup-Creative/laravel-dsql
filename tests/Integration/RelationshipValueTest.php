<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use AyupCreative\AdvancedSearch\Tests\Models\ApplianceModel;
use AyupCreative\AdvancedSearch\Tests\Models\Registration;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipValueTest extends TestCase
{
    public function test_it_handles_casted_objects_and_complex_expressions_in_relationships()
    {
        $am = ApplianceModelWithCast::create([
            'name' => 'XYZ-123',
            'standard_warranty_years' => 5,
        ]);

        $reg = RegistrationForTest::create([
            'product_id' => $am->id,
        ]);

        $receipt = ReceiptForTest::create([
            'registration_id' => $reg->id,
            'receipt_number' => 'REC-001',
            'amount' => 100,
            'purchased_at' => '2024-01-01',
        ]);

        $search = app(AdvancedSearch::class);
        $search->casts()->register('money', function ($value) {
            return $value instanceof Duration ? "{$value->years} Years" : $value;
        });

        // Test dotted path + CAST
        $queryStr = 'SELECT [registration.model.name], CAST([registration.model.standard_warranty_years], "money") AS "warranty"';
        $results = $search->apply(ReceiptForTest::query(), $queryStr)->get();

        $this->assertCount(1, $results);
        $result = $results[0];

        $selections = $result->getSelections($queryStr);
        $modelNameSelection = collect($selections)->firstWhere('name', 'registration.model.name');
        $warrantySelection = collect($selections)->firstWhere('name', 'warranty');

        $this->assertEquals('XYZ-123', $result->getSelectionValue('registration.model.name', $modelNameSelection));
        $this->assertEquals('5 Years', $result->getSelectionValue('warranty', $warrantySelection));

        // Test arithmetic on relationship columns (using a non-casted field)
        $queryStr2 = 'SELECT [registration.model.id] * 2 AS "double_id"';
        $results2 = $search->apply(ReceiptForTest::query(), $queryStr2)->get();
        $result2 = $results2[0];
        $selections2 = $result2->getSelections($queryStr2);

        $doubleIdSelection = collect($selections2)->firstWhere('name', 'double_id');
        $this->assertEquals(2, $result2->getSelectionValue('double_id', $doubleIdSelection));
    }
}

/** @internal */
class Duration
{
    public function __construct(public int $years) {}
}

/** @internal */
class DurationCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return $value ? new Duration((int) $value) : null;
    }

    public function set($model, $key, $value, $attributes)
    {
        return $value instanceof Duration ? $value->years : $value;
    }
}

/** @internal */
class ApplianceModelWithCast extends ApplianceModel
{
    protected $table = 'appliance_models';

    protected $casts = [
        'standard_warranty_years' => DurationCast::class,
    ];
}

/** @internal */
class RegistrationForTest extends Registration
{
    protected $table = 'registrations';

    protected $guarded = [];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ApplianceModelWithCast::class, 'product_id');
    }
}

/** @internal */
class ReceiptForTest extends Model implements Queryable
{
    use Searchable;

    protected $table = 'receipts';

    protected $guarded = [];

    public function registration()
    {
        return $this->belongsTo(RegistrationForTest::class, 'registration_id');
    }
}
