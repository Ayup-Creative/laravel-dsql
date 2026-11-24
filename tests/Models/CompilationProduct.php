<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[VirtualColumn('net', expression: '[price] * 0.8')]
class CompilationProduct extends Model implements Queryable
{
    use Searchable, SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(CompilationCategory::class, 'category_id');
    }

    public function detail()
    {
        return $this->hasOne(CompilationDetail::class, 'product_id');
    }

    public function logs()
    {
        return $this->hasMany(ProductLog::class, 'product_id');
    }

    public function parentProduct()
    {
        return $this->belongsTo(self::class, 'stock');
    }

    public function purchasedLogs()
    {
        return $this->hasMany(ProductLog::class, 'product_id')->where('message', 'like', 'Purchased%');
    }

    public function dangerous(): void
    {
        static::query()->delete();
    }
}
