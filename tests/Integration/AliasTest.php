<?php

namespace AyupCreative\AdvancedSearch\Tests\Integration;

use AdvancedSearch;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class AliasTest extends TestCase
{
    public function test_it_can_use_alias_from_root_namespace()
    {
        $this->assertTrue(class_exists('AdvancedSearch'));
        $this->assertInstanceOf(
            \AyupCreative\AdvancedSearch\AdvancedSearch::class,
            AdvancedSearch::getFacadeRoot()
        );
    }
}
