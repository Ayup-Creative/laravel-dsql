<?php

namespace AyupCreative\AdvancedSearch\Contracts;

interface Queryable
{
    /**
     * @return array<int, array{name: string, label: string, is_alias: bool, expression: string, cast: string|null, metadata: array}>
     */
    public function getSelections(string $query = ''): array;

    /**
     * @param  array{cast?: string|null}|null  $selection
     * @return mixed
     */
    public function getSelectionValue(string $column, ?array $selection = null);
}
