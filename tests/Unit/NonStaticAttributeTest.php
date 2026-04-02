<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use PHPUnit\Framework\TestCase;

class NonStaticAttributeTest extends TestCase
{
    public function test_it_throws_exception_for_non_static_search_column()
    {
        $registry = new ColumnRegistry;

        $this->expectException(AdvancedSearchException::class);
        $this->expectExceptionMessage('Methods used for search columns must be static.');

        $registry->registerFromClass(NonStaticModel::class);
    }
}

class NonStaticModel
{
    #[VirtualColumn('broken')]
    public function searchBroken($query, $op, $val) {}
}
