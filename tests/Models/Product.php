<?php

namespace AyupCreative\AdvancedSearch\Tests\Models;

use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Queryable
{
    use Searchable;

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function detail()
    {
        return $this->hasOne(ProductDetail::class);
    }

    public function logs()
    {
        return $this->hasMany(ProductLog::class);
    }

    #[VirtualColumn('status')]
    public static function searchByStatus($query, $op, $val)
    {
        if ($op === 'in') {
            $query->whereIn('status', $val);
        } elseif ($op === '=_column') {
            // In a real app, we would check if $val is a valid column.
            // For the test, we know 'active' is not a column, but 'created_at' is.
            if ($val === 'created_at' || $val === 'updated_at') {
                $query->whereColumn('status', '=', $val);
            } else {
                $query->where('status', '=', $val);
            }
        } else {
            $query->where('status', $op, $val);
        }
    }

    #[VirtualColumn('alias')]
    public static function searchByNameAlias($query, $op, $val)
    {
        $query->where('name', $op, $val);
    }

    #[VirtualColumn('price_alias')]
    public static function searchByPriceAlias($query, $op, $val)
    {
        $query->where('price', $op, $val);
    }

    #[VirtualColumn('name')]
    public static function searchByName($query, $op, $val)
    {
        $query->where('name', $op, $val);
    }

    #[VirtualColumn('stock')]
    public static function searchByStock($query, $op, $val)
    {
        $query->where('stock', $op, $val);
    }

    #[VirtualColumn('price')]
    public static function searchByPrice($query, $op, $val)
    {
        if ($op === '>_column') {
            if (is_numeric($val)) {
                $query->where('price', '>', $val);
            } else {
                $query->whereColumn('price', '>', $val);
            }
        } elseif ($op === '<_column') {
            if (is_numeric($val)) {
                $query->where('price', '<', $val);
            } else {
                $query->whereColumn('price', '<', $val);
            }
        } elseif ($op === 'between') {
            $query->whereBetween('price', $val);
        } else {
            $query->where('price', $op, $val);
        }
    }

    #[VirtualColumn('updated_at')]
    public static function searchByUpdatedAt($query, $op, $val)
    {
        if ($op === '>_column') {
            $query->whereColumn('updated_at', '>', $val);
        } else {
            $query->where('updated_at', $op, $val);
        }
    }

    #[VirtualColumn('category.name')]
    public static function searchByCategoryName($query, $op, $val)
    {
        // No implementation needed for registry tests
    }

    #[VirtualColumn('vat', expression: '[price] * 0.2')]
    public static function searchByVat($query, $op, $val)
    {
        // This won't be called if it's an expression mapping, but it's good practice.
    }
}
