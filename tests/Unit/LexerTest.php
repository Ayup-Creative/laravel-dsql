<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\TokenType;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase
{
    public function test_it_tokenizes_simple_expression()
    {
        $lexer = new Lexer('[status]:equals"active"');
        $tokens = $lexer->tokenize();

        $this->assertCount(7, $tokens);
        $this->assertEquals(TokenType::T_LBRACKET, $tokens[0]->type);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[1]->type);
        $this->assertEquals('status', $tokens[1]->value);
        $this->assertEquals(TokenType::T_RBRACKET, $tokens[2]->type);
        $this->assertEquals(TokenType::T_COLON, $tokens[3]->type);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[4]->type);
        $this->assertEquals('equals', $tokens[4]->value);
        $this->assertEquals(TokenType::T_STRING, $tokens[5]->type);
        $this->assertEquals('active', $tokens[5]->value);
        $this->assertEquals(TokenType::T_EOF, $tokens[6]->type);
    }

    public function test_it_tokenizes_with_and_or()
    {
        $lexer = new Lexer('[status]:equals[active] AND [type]:in(1, 2)');
        $tokens = $lexer->tokenize();

        $types = array_map(fn ($t) => $t->type, $tokens);
        $this->assertContains(TokenType::T_AND, $types);
        $this->assertContains(TokenType::T_COMMA, $types);
    }

    public function test_it_handles_numbers()
    {
        $lexer = new Lexer('[price]:gt 100.50');
        $tokens = $lexer->tokenize();

        $this->assertEquals(TokenType::T_NUMBER, $tokens[5]->type);
        $this->assertEquals('100.50', $tokens[5]->value);
    }
}
