<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AST\DynamicValueNode;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use AyupCreative\AdvancedSearch\Tests\TestCase;

class DynamicValueParsingTest extends TestCase
{
    public function test_it_parses_simple_dynamic_value()
    {
        $input = '[created_at]:equals now()';
        $lexer = new Lexer($input);
        $parser = new PrattParser($lexer->tokenize());
        $ast = $parser->parse();

        $criteria = $ast->criteria;
        $this->assertEquals('dynamic', $criteria->value['type']);
        $this->assertInstanceOf(DynamicValueNode::class, $criteria->value['value']);
        $this->assertEquals('now', $criteria->value['value']->name);
        $this->assertEmpty($criteria->value['value']->arguments);
        $this->assertNull($criteria->value['value']->next);
    }

    public function test_it_parses_dynamic_value_with_arguments()
    {
        $input = '[created_at]:equals date("2024-01-01")';
        $lexer = new Lexer($input);
        $parser = new PrattParser($lexer->tokenize());
        $ast = $parser->parse();

        $criteria = $ast->criteria;
        $this->assertEquals('dynamic', $criteria->value['type']);
        $this->assertEquals('date', $criteria->value['value']->name);
        $this->assertEquals(['2024-01-01'], $criteria->value['value']->arguments);
    }

    public function test_it_parses_dynamic_value_chain()
    {
        $input = '[created_at]:equals now()->startOfMonth()->addDays(5)';
        $lexer = new Lexer($input);
        $parser = new PrattParser($lexer->tokenize());
        $ast = $parser->parse();

        $criteria = $ast->criteria;
        $node = $criteria->value['value'];

        $this->assertEquals('now', $node->name);
        $this->assertEquals('startOfMonth', $node->next->name);
        $this->assertEquals('addDays', $node->next->next->name);
        $this->assertEquals([5], $node->next->next->arguments);
    }

    public function test_it_parses_dynamic_values_in_between()
    {
        $input = '[created_at]:between(now()->startOfMonth(), now()->endOfMonth())';
        $lexer = new Lexer($input);
        $parser = new PrattParser($lexer->tokenize());
        $ast = $parser->parse();

        $criteria = $ast->criteria;
        $this->assertEquals('list', $criteria->value['type']);
        $list = $criteria->value['value'];
        $this->assertCount(2, $list);
        $this->assertEquals('dynamic', $list[0]['type']);
        $this->assertEquals('now', $list[0]['value']->name);
        $this->assertEquals('startOfMonth', $list[0]['value']->next->name);
    }
}
