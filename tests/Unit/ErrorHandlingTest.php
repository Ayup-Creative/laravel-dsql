<?php

namespace AyupCreative\AdvancedSearch\Tests\Unit;

use AyupCreative\AdvancedSearch\Exceptions\LexerException;
use AyupCreative\AdvancedSearch\Exceptions\ParserException;
use AyupCreative\AdvancedSearch\Grammar\Lexer;
use AyupCreative\AdvancedSearch\Grammar\PrattParser;
use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_lexer_throws_on_unclosed_string()
    {
        $this->expectException(LexerException::class);
        $lexer = new Lexer('[name]:equals"John');
        $lexer->tokenize();
    }

    public function test_lexer_throws_on_unexpected_character()
    {
        $this->expectException(LexerException::class);
        $lexer = new Lexer('[name]@equals');
        $lexer->tokenize();
    }

    public function test_parser_throws_on_unexpected_token()
    {
        $this->expectException(ParserException::class);
        $lexer = new Lexer('[name]:equals');
        $parser = new PrattParser($lexer->tokenize());
        $parser->parse();
    }

    public function test_parser_throws_on_missing_rparen_in_list()
    {
        $this->expectException(ParserException::class);
        $lexer = new Lexer('[status]:in(active, pending');
        $parser = new PrattParser($lexer->tokenize());
        $parser->parse();
    }

    public function test_parser_throws_on_invalid_sort_direction()
    {
        $this->expectException(ParserException::class);
        $lexer = new Lexer('[a]:eq 1 sort(id, invalid)');
        $parser = new PrattParser($lexer->tokenize());
        $parser->parse();
    }

    public function test_parser_throws_on_non_numeric_limit()
    {
        $this->expectException(ParserException::class);
        $lexer = new Lexer('[a]:eq 1 limit(abc)');
        $parser = new PrattParser($lexer->tokenize());
        $parser->parse();
    }
}
