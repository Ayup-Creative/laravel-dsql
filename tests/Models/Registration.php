<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    protected $fillable = ['product_id', 'receipt_id'];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ApplianceModel::class, 'product_id');
    }
}
