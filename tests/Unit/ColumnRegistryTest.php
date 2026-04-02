<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException;
use AyupCreative\AdvancedSearch\Registry\ColumnRegistry;
use PHPUnit\Framework\TestCase;

class ColumnRegistryTest extends TestCase
{
    public function test_it_throws_on_unknown_column()
    {
        $this->expectException(AdvancedSearchException::class);
        $registry = new ColumnRegistry;
        $registry->resolve('unknown');
    }
}
