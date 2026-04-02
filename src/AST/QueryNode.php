<?php

namespace AyupCreative\AdvancedSearch\AST;

class QueryNode extends Node
{
    public function __construct(
        public ?Node $criteria = null,
        public array $sorts = [],
        public ?int $limit = null,
        public array $fields = []
    ) {}

    public function __toString(): string
    {
        $dsl = '';
        if (! empty($this->fields)) {
            $dsl .= 'SELECT '.implode(', ', array_map(function ($f) {
                $expr = (string) $f['expression'];
                if ($f['alias']) {
                    return "$expr AS \"{$f['alias']}\"";
                }

                return $expr;
            }, $this->fields));
        }

        if ($this->criteria) {
            if (! empty($dsl)) {
                $dsl .= ' WHERE ';
            }
            $dsl .= (string) $this->criteria;
        }

        if (! empty($this->sorts)) {
            $sortStrings = array_map(fn ($s) => "{$s['column']}, {$s['direction']}", $this->sorts);
            $dsl .= ' sort('.implode(', ', $sortStrings).')';
        }

        if ($this->limit) {
            $dsl .= " limit({$this->limit})";
        }

        return trim($dsl);
    }
}
