<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AyupCreative\AdvancedSearch\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class ModelA extends Model
{
    protected $table = 'model_a';

    protected $guarded = [];

    #[VirtualColumn('status')]
    public static function searchStatus($query, $op, $value): void
    {
        $query->where('type_a', $op, $value);
    }
}

class ModelB extends Model
{
    protected $table = 'model_b';

    protected $guarded = [];

    #[VirtualColumn('status')]
    public static function searchStatus($query, $op, $value): void
    {
        $query->where('type_b', $op, $value);
    }
}

class ModelScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scopes_columns_to_models()
    {
        $search = $this->app->make(AdvancedSearch::class);

        Schema::create('model_a', function ($table) {
            $table->id();
            $table->string('type_a');
            $table->timestamps();
        });

        Schema::create('model_b', function ($table) {
            $table->id();
            $table->string('type_b');
            $table->timestamps();
        });

        ModelA::create(['type_a' => 'active']);
        ModelB::create(['type_b' => 'active']);

        // Search ModelA
        $queryA = ModelA::query();
        $search->apply($queryA, '[status]:equals"active"');
        $this->assertStringContainsString('"type_a" = ?', $queryA->toSql());

        // Search ModelB
        $queryB = ModelB::query();
        $search->apply($queryB, '[status]:equals"active"');
        $this->assertStringContainsString('"type_b" = ?', $queryB->toSql());

        // Search ModelA again to ensure it didn't stay as ModelB
        $queryA2 = ModelA::query();
        $search->apply($queryA2, '[status]:equals"active"');
        $this->assertStringContainsString('"type_a" = ?', $queryA2->toSql());
    }
}
