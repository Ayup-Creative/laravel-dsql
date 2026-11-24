<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use Illuminate\Database\Eloquent\Model;

class CompilationDetail extends Model implements Queryable
{
    use Searchable;

    protected $table = 'product_details';

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(CompilationProduct::class, 'product_id');
    }
}
