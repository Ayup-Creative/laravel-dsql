# Developer Guide: Extending Laravel Advanced Search DSL

This guide covers how to extend the DSL with custom operators and advanced column resolvers.

## 🏗️ Architecture Overview

The system follows a modular pipeline:
1. **Lexer**: Tokenises the input string.
2. **Pratt Parser**: Parses tokens into an Abstract Syntax Tree (AST), respecting operator precedence.
3. **Query Compiler**: Traverses the AST and applies logic to the Eloquent `Builder`.
4. **Registries**: Holds the available columns and operators.

The system is primarily accessed through the `AyupCreative\AdvancedSearch\Facade\AdvancedSearch` facade. For Laravel projects, the package automatically registers a global `AdvancedSearch` alias, allowing for root namespace access (e.g., `\AdvancedSearch::apply()`).

## 🛠️ Adding Custom Operators

To add a new operator, implement the `AyupCreative\AdvancedSearch\Operators\Operator` interface:

```php
namespace App\Search\Operators;

use AyupCreative\AdvancedSearch\Operators\Operator;
use Illuminate\Database\Eloquent\Builder;

class SoundexOperator implements Operator
{
    /**
     * @param Builder $query
     * @param callable $columnResolver
     * @param array{type: string, value: mixed} $value
     */
    public function apply(Builder $query, callable $columnResolver, array $value)
    {
        // Custom logic for SOUNDEX comparison
        $columnResolver($query, 'SOUNDEX', $value['value']);
    }
}
```

Then register it in your `AppServiceProvider` or a dedicated provider:

```php
use AyupCreative\AdvancedSearch\Registry\OperatorRegistry;
use App\Search\Operators\SoundexOperator;

$this->app->extend(OperatorRegistry::class, function ($registry) {
    $registry->register('sounds_like', SoundexOperator::class);
    return $registry;
});
```

Usage: `[name]:sounds_like"John"`

## 🛠️ Custom Casts

You can register custom PHP-side casts for use in the selection DSL using the `AdvancedSearch` facade. This is useful for formatting values (e.g., currency, dates) when retrieving report data.

```php
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;
use Illuminate\Database\Eloquent\Model;

AdvancedSearch::casts()->register('money', function ($value, Model $model) {
    return '$' . number_format($value / 100, 2);
});
```

Usage in DSL: `SELECT CAST([price], 'money') AS "formatted_price"`

In your Blade view:
```blade
@foreach($report->getSelections() as $selection)
    <flux:table.cell>{{ $result->getSelectionValue($selection['name'], $selection) }}</flux:table.cell>
@endforeach
```

## 🗓️ Custom Dynamic Values

You can register custom dynamic functions for use in the search DSL via the `AdvancedSearch` facade. This is useful for exposing domain-specific constants or dynamic calculations without allowing arbitrary PHP evaluation.

```php
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;

// Register a custom function that returns a value
AdvancedSearch::dynamicValues()->register('fiscal_year_start', function() {
    return Carbon::now()->month >= 4 
        ? Carbon::now()->month(4)->startOfMonth() 
        : Carbon::now()->subYear()->month(4)->startOfMonth();
});

// usage: [created_at]:gt fiscal_year_start()
```

When a dynamic function returns an object, you can chain methods on it within the DSL (e.g., `fiscal_year_start()->addMonths(1)`).

## 📊 Using Selection Metadata

The DSL provides a way to extract the selections made in a query string, which is useful for building tabular reports or dynamically generating UI components.

### AdvancedSearch::getSelections(string $input, string|Builder|Model|null $model = null)

This method returns an array of metadata for each selected field in the `SELECT` clause. Providing the `model` allows the system to resolve column-specific metadata and handle default selections.

### AdvancedSearch::validate(string $input, string|Builder|Model $model)

This method allows you to efficiently validate a query string against a model before attempting to apply it. It will throw a `LexerException`, `ParserException`, or `AdvancedSearchException` if the query is invalid (e.g., syntax errors, unknown operators, or invalid dynamic values).

```php
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;

try {
    AdvancedSearch::validate($query, Product::class);
    // or
    AdvancedSearch::validate($query, Product::query());
} catch (AdvancedSearchException $e) {
    // Handle validation errors...
}
```

## 🔗 Relationship Aggregates

The DSL supports `COUNT([relationship])` and `EXISTS([relationship])` functions.

- **Conditions**: `WHERE COUNT([logs]):gt 5` or `WHERE EXISTS([logs])`
- **Selections**: `SELECT [name], COUNT([logs]) AS "log_count"`
- **Arithmetic**: `SELECT [name], COUNT([logs]) * 10 AS "weighted_score"`

The system automatically translates these into efficient subqueries (for selections and arithmetic) or `has()`/`doesntHave()` calls (for simple conditions).

## 🚀 Extending Syntax

To add new keywords or aggregate functions, you currently need to extend the `PrattParser` and `QueryCompiler` classes. However, the architecture is designed to handle common extensions via registries:

1. **Operators**: Use `OperatorRegistry` to add new comparison logic.
2. **Casts**: Use `CastRegistry` to add new PHP-side value formatters.
3. **Dynamic Values**: Use `DynamicValueRegistry` to add new safe PHP-side functions (e.g., `now()`, `user_id()`).
4. **Virtual Columns**: Use `#[VirtualColumn]` on your models to expose calculated SQL fields or custom filtering logic.

```php
$selections = AdvancedSearch::getSelections('SELECT [id], [total] * 1.2 AS "vat_total"', Order::class);

foreach ($selections as $selection) {
    echo "Name: " . $selection['name'] . "\n";
    echo "Label: " . $selection['label'] . "\n";
    echo "Is Alias: " . ($selection['is_alias'] ? 'Yes' : 'No') . "\n";
    echo "Expression: " . $selection['expression'] . "\n";
    print_r($selection['metadata']); // Custom metadata from #[VirtualColumn]
}
```

**Output:**
1.  **Name**: `id`, **Label**: `id`, **Is Alias**: `No`, **Expression**: `[id]`
2.  **Name**: `vat_total`, **Label**: `vat_total`, **Is Alias**: `Yes`, **Expression**: `([total] * 1.2)`

### Accessing Attributes on the Model

The `name` field in the selection metadata corresponds directly to the attribute name that will be present on the Eloquent model instance after the query is executed.

```php
$query = Order::query();
AdvancedSearch::apply($query, $input);
$results = $query->get();

foreach ($results as $model) {
    foreach ($selections as $selection) {
        // Output the value for each selected column
        echo $model->{$selection['name']};
    }
}
```

### Column Metadata

You can attach custom metadata to your search columns using the `metadata` parameter in the `#[VirtualColumn]` attribute. This is useful for passing UI hints, such as how to format a value (e.g., currency, date).

```php
#[VirtualColumn('price', metadata: ['cast' => 'currency', 'precision' => 2])]
public static function searchPrice($query, $op, $val) { ... }
```

This metadata is included in the array returned by `getSelections()`.

## 📦 Using the Searchable Trait

The `Searchable` trait provides a standardised way to integrate advanced search into your models and handle the rendering of results in your UI.

### 1. Implement the Queryable Contract

```php
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;

class Receipt extends Model implements Queryable
{
    use Searchable;
}
```

### 2. Convenient Metadata Access

The trait provides `getSelections($query)` which automatically uses the current model class:

```php
// In your controller or view
$selections = $receipt->getSelections($userQuery);
```

### 3. Standardised Value Formatting

You can define custom formatters for your search columns in the model using the `format{ColumnName}SearchValue` convention:

```php
public function formatPurchasePriceSearchValue($value)
{
    return '$' . number_format($value / 100, 2);
}
```

Then, in your UI loop, use `getSelectionValue($column)` to get the formatted value:

```blade
@foreach($report->getSelections() as $selection)
    <flux:table.cell>
        {{ $result->getSelectionValue($selection['name']) }}
    </flux:table.cell>
@endforeach
```

If no formatter method is defined, `getSelectionValue()` simply returns the raw attribute value from the model.

## 🏷️ Advanced Column Resolvers

Resolvers are callables that receive the `Builder`, the operator string, and the value.

### Attribute-based Registration

You can use the `#[VirtualColumn]` attribute on **static methods** in your models. You can optionally specify which operators are supported for this column.

**Note:** The system automatically scans models for these attributes when `apply()` is called on a query builder for that model, or when using the autocomplete APIs. Manual registration is rarely needed.

> [!IMPORTANT]
> Methods must be `public static`. Non-static methods will throw an `AdvancedSearchException`.

```php
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;

class User extends Model {
    #[VirtualColumn('status', operators: ['equals', 'in'])]
    public static function searchStatus($query, $op, $val) {
        // ...
    }
}
```

### Manual Registration

You can also register resolvers manually for more control, including passing supported operators and optionally scoping to a model:

```php
AdvancedSearch::columns()->register('custom_field', function($query, $op, $val) {
    // Custom complex logic
}, ['equals', 'gt'], Product::class);
```

### Calculated Virtual Columns

You can define virtual columns that are aliases for arithmetic expressions using the `expression` parameter in the `#[VirtualColumn]` attribute.

```php
class Receipt extends Model {
    #[VirtualColumn('vat', expression: '[amount] / 1.2')]
    public static function vat() {}
}
```

Now, `[vat]:gt 100` will be compiled to `(amount / 1.2) > 100`.

You can also register expressions manually:

```php
AdvancedSearch::columns()->registerExpression('total', '[price] + [shipping]', ['equals', 'gt'], Order::class);
```

### Supporting Selection for Virtual Columns

If you define a custom resolver method for a virtual column, it will be used for `WHERE` clauses (filtering). However, if you also want users to be able to `SELECT` that column (e.g., `SELECT [purchase_price]`), you **must** provide a SQL expression in the `expression` parameter of the `#[VirtualColumn]` attribute.

```php
class Receipt extends Model {
    #[VirtualColumn('purchase_price', expression: "CAST(amount / 100 AS DECIMAL(10,2))")]
    public static function searchPurchasePrice(Builder $query, $op, $value): void
    {
        // Custom logic for filtering
        $query->whereRaw("CAST(amount / 100 AS DECIMAL(10,2)) {$op} ?", [$value]);
    }
}
```

When both a resolver and an expression are provided:
1.  The **expression** is used for `SELECT` clauses.
2.  The **resolver method** is used for `WHERE` clauses.

This allows you to provide complex SQL for selection while still having full control over the filtering logic in PHP.

### Column Selection and Arithmetic

When using the `SELECT` keyword, the system uses `selectRaw` to handle both simple columns and complex arithmetic:

- `SELECT [name], [price] AS "cost"` -> `selectRaw("name, price AS cost")`
- `SELECT [price] * 1.2 AS "vat_price"` -> `selectRaw("(price * ?) AS vat_price", [1.2])`

If a virtual column is selected, its underlying expression is used and automatically aliased:

- `SELECT [vat]` -> `selectRaw("(amount / ?) AS vat", [1.2])`

### Relationship Traversal

The DSL provides native support for traversing relationships in both `WHERE` and `SELECT` clauses using dot notation.

#### In WHERE Clauses
- `[customer.name]:equals"John"` (Automatically uses `whereHas('customer', ...)`)
- `[registration.model.years]:lt 10`

The compiler automatically translates dotted paths into `whereHas()` calls, ensuring safe and isolated relationship filtering.

#### In SELECT Clauses
- `SELECT [customer.name], [detail.sku]`

When a dotted path is detected in the `SELECT` clause, the system will:
1.  **Eager Load**: Automatically call `$query->with('relation_path')` for the detected relationships.
2.  **Ensure Keys**: Automatically select the necessary foreign keys (for `BelongsTo`) or local primary keys (for `HasOne`/`HasMany`) on the parent model to ensure Laravel can resolve the relationship data.
3.  **Data Retrieval**: Use `getSelectionValue()` from the `Searchable` trait to fetch the value from the loaded relationship using `data_get()`. This supports both singular and plural relationships (collections).

#### Mapping Virtual Columns to Relationships
You can also define virtual columns that map to dotted relationship paths. This allows you to expose safe, simple names in the DSL while automatically handling relationship traversal and eager-loading.

```php
#[VirtualColumn('standard_years', expression: '[registration.model.standard_warranty_years]')]
public static function searchByApplianceStandardYears($query, $op, $value): void
{
    $query->whereHas('registration.model', function ($q) use ($op, $value) {
        $q->where('standard_warranty_years', $op, $value);
    });
}
```

#### Selection and SQL Functions
If a virtual column is defined with a SQL function like `CONCAT`, the system provides a PHP-side fallback to evaluate these expressions when selection values are retrieved via `getSelectionValue()`.

```php
#[VirtualColumn('full_name', expression: "CONCAT(other_names, ' ', last_name)")]
public static function searchByFullName($query, $op, $value)
{
    $query->whereRaw("CONCAT(other_names, ' ', last_name) {$op} ?", [$value]);
}
```

When a user executes `SELECT [customer.full_name]`, the system will:
1.  **SQL**: Select `NULL AS "customer.full_name"` and eager-load the `customer` relationship.
2.  **PHP**: Resolve the value by descending into the `customer` instance and evaluating the `CONCAT` expression against its `other_names` and `last_name` attributes.

This ensures that even complex virtual columns remain accessible through relationships without requiring complex joins in every query.

When a user executes `SELECT [standard_years]`, the system will use the same automated eager-loading and key discovery logic mentioned above.

### Best Practices for Relationships
- **Performance**: While the system handles key selection automatically, always be mindful of N+1 issues when manually looping over results if you haven't used `getSelectionValue()`.
- **Subqueries**: For massive datasets, consider using subqueries in the `expression` attribute if you want to select relationship values directly in SQL without extra PHP-side loading.

## 📊 Using Selection Metadata

The system provides tools for building UI components like search builders.

### Model Introspection

Use `getSchema(string|Builder|Model $model)` to retrieve all available columns (database fields, virtual columns) and first-level relationships for a model:

```php
$schema = AdvancedSearch::getSchema(Product::class);
/*
Returns: [
    ['name' => 'id', 'type' => 'column'],
    ['name' => 'name', 'type' => 'column'],
    ['name' => 'category', 'type' => 'relationship', 'model' => 'App\Models\Category'],
    ...
]
*/
```

Use `getAutocomplete(string|Builder|Model $model)` to retrieve all columns registered for a model via attributes:

```php
$columns = AdvancedSearch::getAutocomplete(Product::class);
```

This will automatically trigger a scan of the class if it hasn't been registered yet.

### Operator Autocomplete

Use `getAvailableOperators(?string $column = null, string|Builder|Model|null $model = null)` to get a list of supported operators.

```php
// Get all global operators
$operators = AdvancedSearch::getAvailableOperators();

// Get operators for a specific column (with model context for accurate scoping)
$operators = AdvancedSearch::getAvailableOperators('status', Product::class);
```

### Syntax Templates

Use `getBlankSyntax(string $column, ?string $operator = null)` to get a standard template for the DSL:

```php
$template = AdvancedSearch::getBlankSyntax('status'); // [status]:equals""
$templateIn = AdvancedSearch::getBlankSyntax('status', 'in'); // [status]:in()
```

### Context-Aware Suggestions

The `suggest(string $input, string|Builder|Model $model)` method provides smart, context-aware autocomplete suggestions as the user types.

```php
// If input is '[stat'
$suggestions = AdvancedSearch::suggest('[stat', Product::class); // ['status']

// If input is '[status]:'
$suggestions = AdvancedSearch::suggest('[status]:', Product::class); // ['equals', 'in', ...]

// Handles boolean logic and groups
$suggestions = AdvancedSearch::suggest('([status]:equals"active" ', Product::class); // ['AND', 'OR', 'sort(', 'limit(']
```

### Default Selections for UI and Reports

When building dynamic reporting tables, you often need to know which columns to display if the user hasn't specified a `SELECT` clause in their query.

You can define these default selections on your model using the `#[DefaultSelections]` attribute:

```php
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;

#[DefaultSelections(['id', 'sku', 'price'])]
class Product extends Model { ... }
```

Or by implementing a static method:

```php
class Product extends Model {
    public static function getAdvancedSearchDefaultSelections(): array
    {
        return ['id', 'sku', 'price'];
    }
}
```

You can then retrieve these selections using `getSelections($input, $model)`:

```php
$selections = AdvancedSearch::getSelections('[status]:equals"active"', Product::class);

/*
Returns: [
    ['name' => 'id', 'label' => 'id', 'is_alias' => false, 'expression' => '[id]'],
    ['name' => 'sku', 'label' => 'sku', 'is_alias' => false, 'expression' => '[sku]'],
    ['name' => 'price', 'label' => 'price', 'is_alias' => false, 'expression' => '[price]']
]
*/
```

If neither the attribute nor the method is present, the system will fall back to the model's `getFillable()` attributes.

The method correctly identifies when the user is:
- Typing a column name (inside `[]`).
- Typing an operator name (after `:`).
- Inside a quoted string (returns no suggestions).
- Inside `sort(` or `limit(`.
- After a completed expression (suggests logic or sorting/limits).

## 🧪 Testing Your Extensions

We recommend writing integration tests using the provided `AyupCreative\AdvancedSearch\Tests\TestCase` as a base. Ensure you test:
1. Scalar values.
2. Column-to-column comparisons (if applicable).
3. Null/Edge cases.

## 🎨 Code Style

The project uses [Laravel Pint](https://github.com/laravel/pint). Before submitting changes, run:

```bash
vendor/bin/pint
```
