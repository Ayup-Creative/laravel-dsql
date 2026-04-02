<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\ConditionNode;
use AyupCreative\AdvancedSearch\AST\LiteralNode;
use AyupCreative\AdvancedSearch\AST\LogicalNode;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function test_it_parses_simple_condition()
    {
        $lexer = new Lexer('[status]:equals"active"');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertInstanceOf(QueryNode::class, $node);
        $this->assertInstanceOf(ConditionNode::class, $node->criteria);
        $this->assertInstanceOf(ColumnNode::class, $node->criteria->column);
        $this->assertEquals('status', $node->criteria->column->name);
        $this->assertEquals('equals', $node->criteria->operator);
        $this->assertEquals(['type' => 'scalar', 'value' => 'active'], $node->criteria->value);
    }

    public function test_it_parses_column_comparison()
    {
        $lexer = new Lexer('[processed_at]:gt[created_at]');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertEquals(['type' => 'column', 'value' => 'created_at'], $node->criteria->value);
    }

    public function test_it_parses_and_logic()
    {
        $lexer = new Lexer('[status]:equals"active" AND [type]:in(1, 2)');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertInstanceOf(LogicalNode::class, $node->criteria);
        $this->assertEquals('AND', $node->criteria->boolean);
    }

    public function test_it_parses_sort_and_limit()
    {
        $lexer = new Lexer('[status]:equals"active" sort(created_at, desc) limit(5)');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertCount(1, $node->sorts);
        $this->assertEquals('created_at', $node->sorts[0]['column']);
        $this->assertEquals('desc', $node->sorts[0]['direction']);
        $this->assertEquals(5, $node->limit);
    }

    public function test_it_parses_nested_logic_with_parentheses()
    {
        $lexer = new Lexer('([a]:eq 1 OR [b]:eq 2) AND [c]:eq 3');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertInstanceOf(LogicalNode::class, $node->criteria);
        $this->assertEquals('AND', $node->criteria->boolean);
        $this->assertInstanceOf(LogicalNode::class, $node->criteria->left);
        $this->assertEquals('OR', $node->criteria->left->boolean);
    }

    public function test_it_parses_arithmetic_expressions()
    {
        $lexer = new Lexer('[a] + 1 :gt 5');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertInstanceOf(ConditionNode::class, $node->criteria);
        $this->assertInstanceOf(ArithmeticNode::class, $node->criteria->column);
        $this->assertEquals('+', $node->criteria->column->operator);
        $this->assertInstanceOf(ColumnNode::class, $node->criteria->column->left);
        $this->assertInstanceOf(LiteralNode::class, $node->criteria->column->right);
        $this->assertEquals(1, $node->criteria->column->right->value);
    }

    public function test_it_respects_arithmetic_precedence()
    {
        $lexer = new Lexer('[a] + [b] * 2 :eq 10');
        $parser = new PrattParser($lexer->tokenize());
        $node = $parser->parse();

        $this->assertInstanceOf(ArithmeticNode::class, $node->criteria->column);
        $this->assertEquals('+', $node->criteria->column->operator);
        $this->assertInstanceOf(ArithmeticNode::class, $node->criteria->column->right);
        $this->assertEquals('*', $node->criteria->column->right->operator);
    }
}
