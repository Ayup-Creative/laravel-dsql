<?php

namespace AyupCreative\AdvancedSearch\Grammar;

use AyupCreative\AdvancedSearch\Exceptions\LexerException;

class Lexer
{
    protected string $input;

    protected int $cursor = 0;

    protected int $length;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    /**
     * @return Token[]
     *
     * @throws LexerException
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->cursor < $this->length) {
            $char = $this->input[$this->cursor];

            if (ctype_space($char)) {
                $this->cursor++;

                continue;
            }

            if ($char === '[') {
                $tokens[] = new Token(TokenType::T_LBRACKET, '[', $this->cursor++);
            } elseif ($char === ']') {
                $tokens[] = new Token(TokenType::T_RBRACKET, ']', $this->cursor++);
            } elseif ($char === '(') {
                $tokens[] = new Token(TokenType::T_LPAREN, '(', $this->cursor++);
            } elseif ($char === ')') {
                $tokens[] = new Token(TokenType::T_RPAREN, ')', $this->cursor++);
            } elseif ($char === ':') {
                $tokens[] = new Token(TokenType::T_COLON, ':', $this->cursor++);
            } elseif ($char === ',') {
                $tokens[] = new Token(TokenType::T_COMMA, ',', $this->cursor++);
            } elseif ($char === '+') {
                $tokens[] = new Token(TokenType::T_PLUS, '+', $this->cursor++);
            } elseif ($char === '-') {
                if ($this->cursor + 1 < $this->length && $this->input[$this->cursor + 1] === '>') {
                    $tokens[] = new Token(TokenType::T_ARROW, '->', $this->cursor);
                    $this->cursor += 2;
                } else {
                    $tokens[] = new Token(TokenType::T_MINUS, '-', $this->cursor++);
                }
            } elseif ($char === '*') {
                $tokens[] = new Token(TokenType::T_STAR, '*', $this->cursor++);
            } elseif ($char === '/') {
                $tokens[] = new Token(TokenType::T_SLASH, '/', $this->cursor++);
            } elseif ($char === '"' || $char === "'") {
                $tokens[] = $this->lexString($char);
            } elseif (ctype_digit($char)) {
                $tokens[] = $this->lexNumber();
            } else {
                $token = $this->lexIdentifierOrKeyword();
                if ($token) {
                    $tokens[] = $token;
                } else {
                    throw new LexerException("Unexpected character '$char' at position {$this->cursor}");
                }
            }
        }

        $tokens[] = new Token(TokenType::T_EOF, '', $this->cursor);

        return $tokens;
    }

    protected function lexString(string $quote): Token
    {
        $start = $this->cursor;
        $this->cursor++; // Skip quote
        $value = '';
        while ($this->cursor < $this->length && $this->input[$this->cursor] !== $quote) {
            if ($this->input[$this->cursor] === '\\' && $this->cursor + 1 < $this->length) {
                $this->cursor++;
            }
            $value .= $this->input[$this->cursor++];
        }

        if ($this->cursor >= $this->length) {
            throw new LexerException("Unclosed string starting at position $start");
        }

        $this->cursor++; // Skip quote

        return new Token(TokenType::T_STRING, $value, $start);
    }

    protected function lexNumber(): Token
    {
        $start = $this->cursor;
        $value = '';
        while ($this->cursor < $this->length && (ctype_digit($this->input[$this->cursor]) || $this->input[$this->cursor] === '.')) {
            $value .= $this->input[$this->cursor++];
        }

        return new Token(TokenType::T_NUMBER, $value, $start);
    }

    protected function lexIdentifierOrKeyword(): ?Token
    {
        $start = $this->cursor;
        $value = '';
        // Allow identifiers to contain letters, numbers, underscore, dot, hyphen
        while ($this->cursor < $this->length && preg_match('/[a-zA-Z0-9_\.\-]/', $this->input[$this->cursor])) {
            $value .= $this->input[$this->cursor++];
        }

        if ($value === '') {
            return null;
        }

        $upperValue = strtoupper($value);
        if (in_array($upperValue, ['AND', 'OR', 'NOT', 'SELECT', 'WHERE', 'AS', 'CAST', 'TRUE', 'FALSE', 'NULL'])) {
            $tokenType = match ($upperValue) {
                'TRUE' => TokenType::T_LITERAL_TRUE,
                'FALSE' => TokenType::T_LITERAL_FALSE,
                'NULL' => TokenType::T_LITERAL_NULL,
                default => TokenType::from($upperValue),
            };

            return new Token($tokenType, $upperValue, $start);
        }

        if ($value === 'sort') {
            return new Token(TokenType::T_SORT, $value, $start);
        }

        if ($value === 'limit') {
            return new Token(TokenType::T_LIMIT, $value, $start);
        }

        return new Token(TokenType::T_IDENTIFIER, $value, $start);
    }
}
