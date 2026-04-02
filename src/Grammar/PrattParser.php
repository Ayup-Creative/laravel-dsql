<?php

namespace AyupCreative\AdvancedSearch\Grammar;

use AyupCreative\AdvancedSearch\AST\AggregateNode;
use AyupCreative\AdvancedSearch\AST\ArithmeticNode;
use AyupCreative\AdvancedSearch\AST\CastNode;
use AyupCreative\AdvancedSearch\AST\ColumnNode;
use AyupCreative\AdvancedSearch\AST\ConditionNode;
use AyupCreative\AdvancedSearch\AST\DynamicValueNode;
use AyupCreative\AdvancedSearch\AST\LiteralNode;
use AyupCreative\AdvancedSearch\AST\LogicalNode;
use AyupCreative\AdvancedSearch\AST\Node;
use AyupCreative\AdvancedSearch\AST\QueryNode;
use AyupCreative\AdvancedSearch\Exceptions\ParserException;

class PrattParser
{
    protected array $tokens;

    protected int $pos = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function parse(): Node
    {
        $fields = [];
        $criteria = null;
        $sorts = [];
        $limit = null;

        if ($this->peek() && $this->peek()->type === TokenType::T_SELECT) {
            $this->advance();
            $fields = $this->parseFields();
            if ($this->peek() && $this->peek()->type === TokenType::T_WHERE) {
                $this->advance();
                $criteria = $this->parseExpression();
            }
        } else {
            // Check if input is not just SORT or LIMIT
            $token = $this->peek();
            if ($token && $token->type !== TokenType::T_SORT && $token->type !== TokenType::T_LIMIT && $token->type !== TokenType::T_EOF) {
                $criteria = $this->parseExpression();
            }
        }

        while (! $this->isAtEnd()) {
            $token = $this->peek();
            if ($token->type === TokenType::T_SORT) {
                $this->advance();
                $sorts = $this->parseSort();
            } elseif ($token->type === TokenType::T_LIMIT) {
                $this->advance();
                $limit = $this->parseLimit();
            } else {
                break;
            }
        }

        if (! $this->isAtEnd()) {
            throw new ParserException("Unexpected token '{$this->peek()->value}' at position {$this->peek()->position}");
        }

        return new QueryNode($criteria, $sorts, $limit, $fields);
    }

    protected function parseFields(): array
    {
        $fields = [];
        do {
            $expression = $this->parseExpression();
            $alias = null;
            if ($this->peek() && $this->peek()->type === TokenType::T_AS) {
                $this->advance();
                $aliasToken = $this->advance();
                if (! in_array($aliasToken->type, [TokenType::T_IDENTIFIER, TokenType::T_STRING, TokenType::T_LBRACKET])) {
                    throw new ParserException("Expected alias after AS at position {$aliasToken->position}");
                }

                if ($aliasToken->type === TokenType::T_LBRACKET) {
                    $aliasToken = $this->consume(TokenType::T_IDENTIFIER, 'Expected alias identifier');
                    $this->consume(TokenType::T_RBRACKET, "Expected ']'");
                }

                $alias = $aliasToken->value;
            }
            $fields[] = ['expression' => $expression, 'alias' => $alias];

            if ($this->peek() && $this->peek()->type === TokenType::T_COMMA) {
                $this->advance();
            } else {
                break;
            }
        } while (! $this->isAtEnd());

        return $fields;
    }

    protected function parseSort(): array
    {
        $this->consume(TokenType::T_LPAREN, "Expected '(' after sort");
        $sorts = [];
        do {
            $column = $this->consume(TokenType::T_IDENTIFIER, 'Expected column identifier in sort')->value;
            $direction = 'asc';
            if ($this->peek()->type === TokenType::T_COMMA) {
                $this->advance();
                $dirToken = $this->consume(TokenType::T_IDENTIFIER, 'Expected sort direction');
                $direction = strtolower($dirToken->value);
                if (! in_array($direction, ['asc', 'desc'])) {
                    throw new ParserException("Invalid sort direction '$direction'");
                }
            }
            $sorts[] = ['column' => $column, 'direction' => $direction];

            if ($this->peek()->type === TokenType::T_COMMA) {
                $this->advance();
            } else {
                break;
            }
        } while (! $this->isAtEnd());
        $this->consume(TokenType::T_RPAREN, "Expected ')' after sort");

        return $sorts;
    }

    protected function parseLimit(): int
    {
        $this->consume(TokenType::T_LPAREN, "Expected '(' after limit");
        $limitToken = $this->advance();
        if (! in_array($limitToken->type, [TokenType::T_IDENTIFIER, TokenType::T_NUMBER])) {
            throw new ParserException("Expected limit value at position {$limitToken->position}");
        }
        $limit = $limitToken->value;
        if (! is_numeric($limit)) {
            throw new ParserException('Limit must be numeric');
        }
        $this->consume(TokenType::T_RPAREN, "Expected ')' after limit");

        return (int) $limit;
    }

    protected function parseExpression(int $precedence = 0): Node
    {
        $token = $this->advance();
        $left = $this->nud($token);

        while ($precedence < $this->getPrecedence($this->peek())) {
            $token = $this->advance();
            $left = $this->led($token, $left);
        }

        return $left;
    }

    protected function nud(Token $token): Node
    {
        switch ($token->type) {
            case TokenType::T_LPAREN:
                $expr = $this->parseExpression();
                $this->consume(TokenType::T_RPAREN, "Expected ')'");

                return $expr;

            case TokenType::T_LBRACKET:
                $column = $this->consume(TokenType::T_IDENTIFIER, 'Expected column identifier')->value;
                $this->consume(TokenType::T_RBRACKET, "Expected ']'");

                return new ColumnNode($column);

            case TokenType::T_NUMBER:
                $val = $token->value;

                return new LiteralNode(str_contains($val, '.') ? (float) $val : (int) $val);

            case TokenType::T_STRING:
                return new LiteralNode($token->value);

            case TokenType::T_IDENTIFIER:
                if ($this->peek() && $this->peek()->type === TokenType::T_LPAREN) {
                    $upper = strtoupper($token->value);
                    if ($upper === 'COUNT' || $upper === 'EXISTS') {
                        return $this->parseAggregate($upper);
                    }

                    return $this->parseDynamicValue($token->value);
                }

                return new LiteralNode($token->value);

            case TokenType::T_CAST:
                $this->consume(TokenType::T_LPAREN, "Expected '(' after CAST");
                $expr = $this->parseExpression(0);
                $this->consume(TokenType::T_COMMA, "Expected ',' in CAST");
                $typeToken = $this->consume(TokenType::T_STRING, 'Expected string for cast type');
                $this->consume(TokenType::T_RPAREN, "Expected ')' after CAST type");

                return new CastNode($expr, $typeToken->value);

            case TokenType::T_NOT:
                return new LogicalNode('NOT', $this->parseExpression(30), null);

            default:
                throw new ParserException("Unexpected token '{$token->value}' at position {$token->position}");
        }
    }

    protected function led(Token $token, Node $left): Node
    {
        switch ($token->type) {
            case TokenType::T_AND:
            case TokenType::T_OR:
                $right = $this->parseExpression($this->getPrecedence($token));

                return new LogicalNode($token->value, $left, $right);

            case TokenType::T_COLON:
                $operator = $this->consume(TokenType::T_IDENTIFIER, 'Expected operator identifier')->value;
                $value = $this->parseValue();

                return new ConditionNode($left, $operator, $value);

            case TokenType::T_PLUS:
            case TokenType::T_MINUS:
            case TokenType::T_STAR:
            case TokenType::T_SLASH:
                $right = $this->parseExpression($this->getPrecedence($token));

                return new ArithmeticNode($token->value, $left, $right);

            default:
                throw new ParserException("Unexpected token '{$token->value}'");
        }
    }

    protected function parseValue(): mixed
    {
        $token = $this->advance();

        if ($token->type === TokenType::T_LBRACKET) {
            // Column-to-column comparison or just a bracketed value?
            // Description says [processed_at]:gt[created_at]
            $value = $this->consume(TokenType::T_IDENTIFIER, 'Expected identifier')->value;
            $this->consume(TokenType::T_RBRACKET, "Expected ']'");

            return ['type' => 'column', 'value' => $value];
        }

        if ($token->type === TokenType::T_LPAREN) {
            // List of values: in(processed, pending)
            $values = [];
            if ($this->peek()->type !== TokenType::T_RPAREN) {
                do {
                    $values[] = $this->parseValue();
                    if ($this->peek()->type === TokenType::T_COMMA) {
                        $this->advance();
                    } else {
                        break;
                    }
                } while (! $this->isAtEnd());
            }
            $this->consume(TokenType::T_RPAREN, "Expected ')'");

            // Flatten the nested value array returned by parseValue
            $flattenedValues = array_map(function ($v) {
                if (is_array($v) && isset($v['type']) && $v['type'] === 'scalar') {
                    return $v['value'];
                }

                return $v;
            }, $values);

            return ['type' => 'list', 'value' => $flattenedValues];
        }

        if ($token->type === TokenType::T_IDENTIFIER && $this->peek() && $this->peek()->type === TokenType::T_LPAREN) {
            // Function call: now()
            return ['type' => 'dynamic', 'value' => $this->parseDynamicValue($token->value)];
        }

        if ($token->type === TokenType::T_LITERAL_TRUE) {
            return ['type' => 'scalar', 'value' => true];
        }
        if ($token->type === TokenType::T_LITERAL_FALSE) {
            return ['type' => 'scalar', 'value' => false];
        }
        if ($token->type === TokenType::T_LITERAL_NULL) {
            return ['type' => 'scalar', 'value' => null];
        }

        if (in_array($token->type, [TokenType::T_IDENTIFIER, TokenType::T_STRING, TokenType::T_NUMBER])) {
            $val = $token->value;
            if ($token->type === TokenType::T_NUMBER) {
                $val = str_contains($val, '.') ? (float) $val : (int) $val;
            }

            return ['type' => 'scalar', 'value' => $val];
        }

        throw new ParserException("Expected value at position {$token->position}");
    }

    protected function parseAggregate(string $function): AggregateNode
    {
        $this->consume(TokenType::T_LPAREN, "Expected '(' after $function");
        $expression = $this->parseExpression(0);
        $this->consume(TokenType::T_RPAREN, "Expected ')' after $function");

        return new AggregateNode($function, $expression);
    }

    protected function parseDynamicValue(string $name): DynamicValueNode
    {
        $this->consume(TokenType::T_LPAREN, "Expected '('");
        $args = [];
        if ($this->peek()->type !== TokenType::T_RPAREN) {
            do {
                $args[] = $this->parseValue();
                if ($this->peek()->type === TokenType::T_COMMA) {
                    $this->advance();
                } else {
                    break;
                }
            } while (! $this->isAtEnd());
        }
        $this->consume(TokenType::T_RPAREN, "Expected ')'");

        // Flatten args
        $flattenedArgs = array_map(function ($arg) {
            if (is_array($arg) && isset($arg['type']) && $arg['type'] === 'scalar') {
                return $arg['value'];
            }

            return $arg;
        }, $args);

        $next = null;
        if ($this->peek() && $this->peek()->type === TokenType::T_ARROW) {
            $this->advance();
            $nextName = $this->consume(TokenType::T_IDENTIFIER, 'Expected method identifier')->value;
            $next = $this->parseDynamicValue($nextName);
        }

        return new DynamicValueNode($name, $flattenedArgs, $next);
    }

    protected function getPrecedence(?Token $token): int
    {
        if ($token === null) {
            return 0;
        }

        return match ($token->type) {
            TokenType::T_OR => 10,
            TokenType::T_AND => 20,
            TokenType::T_NOT => 30,
            TokenType::T_COLON => 40,
            TokenType::T_PLUS, TokenType::T_MINUS => 50,
            TokenType::T_STAR, TokenType::T_SLASH => 60,
            default => 0,
        };
    }

    protected function peek(): ?Token
    {
        return $this->tokens[$this->pos] ?? null;
    }

    protected function advance(): Token
    {
        return $this->tokens[$this->pos++];
    }

    protected function consume(TokenType $type, string $message): Token
    {
        $token = $this->peek();
        if ($token === null || $token->type !== $type) {
            throw new ParserException("$message at position ".($token ? $token->position : 'end'));
        }

        return $this->advance();
    }

    protected function isAtEnd(): bool
    {
        return $this->peek() === null || $this->peek()->type === TokenType::T_EOF;
    }
}
