<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[VirtualColumn('label', expression: '[name]')]
class CompilationCategory extends Model implements Queryable
{
    use Searchable, SoftDeletes;

    protected $table = 'categories';

    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(CompilationProduct::class, 'category_id');
    }

    #[VirtualColumn('normalized_name', expression: 'LOWER(name)')]
    public static function searchNormalizedName($query, $op, $value): void
    {
        $query->whereRaw("LOWER(name) {$op} ?", [strtolower($value)]);
    }
}
