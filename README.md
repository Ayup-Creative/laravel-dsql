# Laravel Advanced Search DSL

[![CI/CD](https://github.com/Ayup-Creative/laravel-dsql/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/ayup-creative/laravel-dsql/actions/workflows/ci-cd.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ayup-creative/laravel-dsql.svg?style=flat-square)](https://packagist.org/packages/ayup-creative/laravel-dsql)
[![Total Downloads](https://img.shields.io/packagist/dt/ayup-creative/laravel-dsql.svg?style=flat-square)](https://packagist.org/packages/ayup-creative/laravel-dsql)
[![License](https://img.shields.io/packagist/l/ayup-creative/laravel-dsql.svg?style=flat-square)](https://packagist.org/packages/ayup-creative/laravel-dsql)

A production-ready domain-specific language (DSL) for Laravel applications. This package allows you to expose complex, user-defined search expressions while abstracting and protecting your underlying database schema.

Designed for reporting tools, analytics dashboards, and advanced filtering APIs, it converts a safe string-based syntax into composable Eloquent queries.

## ✨ Features

- **Standardised DSL Syntax**: Clear and unambiguous syntax for filtering and reporting.
- **Column Selection**: Specify which fields to return with `SELECT [col1], [col2] AS "alias"`.
- **Alias Reuse**: Reuse calculated aliases within the same `SELECT` and `WHERE` clauses.
- **Boolean Logic**: Full support for `AND`, `OR`, and grouped expressions with parentheses.
- **Virtual Columns**: Map safe, user-facing field names to internal database logic using attributes (`#[VirtualColumn]`).
- **Custom Operator Registry**: Easily extend the language with new operators (e.g., `equals`, `in`, `gt`, `between`, `contains`).
- **Column-to-Column Comparisons**: Compare fields safely (e.g., `[processed_at]:gt[created_at]`).
- **Arithmetic Expressions**: Perform calculations directly in the search string (e.g., `[price] / 1.2 :gt 100`).
- **Calculated Virtual Columns**: Define aliases for complex expressions using attributes.
- **Dynamic Values**: Express real-time dates and values like `now()`, `today()`, or `now()->subDays(7)`.
- **Custom Selection Casting**: Apply PHP-side formatting to selected values using `CAST(expr, 'type')`.
- **Default Selections**: Define fallback fields to return when no `SELECT` clause is specified.
- **Column Metadata**: Attach custom metadata (e.g., for UI formatting/casts) to search columns.
- **Searchable Trait**: Simplified model integration with convenient methods for report generation.
- **Relationship Traversal**: Query across Eloquent relationships using dot notation (e.g., `[customer.ref]:equals"ABC123"`).
- **Built-in Sorting & Limits**: Include reporting instructions directly in the query string.
- **Relationship Aggregates**: Perform existence and count checks on relationships (e.g., `EXISTS([orders])`, `COUNT([items]):gt 5`).
- **Security-First**: Uses a lexer and Pratt parser to build a safe abstract syntax tree (AST) before compilation.
- **Autocomplete Ready**: Built-in methods to retrieve searchable columns and blank syntax for UI integration.

## 🚀 Quick Start

### Installation

```bash
composer require ayup-creative/laravel-dsql
```

### Basic Usage

```php
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;
use AyupCreative\AdvancedSearch\Concerns\Searchable;
use AyupCreative\AdvancedSearch\Contracts\Queryable;
use App\Models\Order;

// 1. Define virtual columns and report metadata in your model
#[DefaultSelections(['id', 'status', 'total'])]
class Order extends Model implements Queryable
{
    use Searchable;

    #[VirtualColumn('status', metadata: ['label' => 'Order Status'])]
    public static function searchStatus($query, $op, $val) {
        $query->where('status', $op, $val);
    }
}

// 2. Apply search to your query builder using the Facade
// You can pass the Model FQN, a builder instance, or a model instance
AdvancedSearch::apply(Order::class, '[status]:equals"processed"');

// Or using an existing builder
$query = Order::query()->where('active', true);
AdvancedSearch::apply($query, '[status]:equals"processed"');

// 3. Retrieve results and use selection metadata for UI
// All methods support passing a Model FQN, Builder, or Model instance
$selections = AdvancedSearch::getSelections('[status]:equals"processed"', Order::class);
// Or using the trait: $selections = $order->getSelections();
```

## 🔍 Autocomplete Support

The DSL provides built-in support for generating metadata for your UI via the `AdvancedSearch` facade.

```php
use AyupCreative\AdvancedSearch\Facade\AdvancedSearch;

// 1. Get all available columns and relationships for a given model (one-level deep)
$schema = AdvancedSearch::getSchema(Order::class);
/*
Returns: [
    ['name' => 'id', 'type' => 'column'],
    ['name' => 'status', 'type' => 'column'],
    ['name' => 'customer', 'type' => 'relationship', 'model' => 'App\Models\Customer'],
    ...
]
*/

// 2. Get all registered virtual columns for a given model
$columns = AdvancedSearch::getAutocomplete(Order::class);
// Returns: ['status', 'customer.name', ...]

// 3. Get available operators (optionally for a specific column)
$operators = AdvancedSearch::getAvailableOperators('status');
// Returns: ['equals', 'in', ...]

// 3. Get a blank syntax template for a selected column (optionally with an operator)
$syntax = AdvancedSearch::getBlankSyntax('status'); // '[status]:equals""'
$syntaxIn = AdvancedSearch::getBlankSyntax('status', 'in'); // '[status]:in()'

// 4. Get smart autocomplete suggestions based on partial input
$suggestions = AdvancedSearch::suggest('[stat', Order::class); // ['status']
$suggestions = AdvancedSearch::suggest('[status]:', Order::class); // ['equals', 'in', ...]
$suggestions = AdvancedSearch::suggest('[status]:equals"', Order::class); // [] (inside quotes)

// 5. Get selected columns/expressions from a query string
$selections = AdvancedSearch::getSelections('SELECT [id], [total] * 1.2 AS "vat_total" WHERE [status]:equals"processed"', Order::class);
/*
Returns: [
    ['name' => 'id', 'label' => 'id', 'is_alias' => false, 'expression' => '[id]'],
    ['name' => 'vat_total', 'label' => 'vat_total', 'is_alias' => true, 'expression' => '([total] * 1.2)']
]
*/

// 6. Get default selections when no SELECT is passed
$defaults = AdvancedSearch::getSelections('[status]:equals"processed"', Order::class);
// Returns default fields defined in Order model (or getFillable() fallback)

// 7. Validate a query string against a model
try {
    AdvancedSearch::validate('SELECT [invalid] WHERE [status]:wrong_op"val"', Order::class);
} catch (\AyupCreative\AdvancedSearch\Exceptions\AdvancedSearchException $e) {
    // Handle validation error (unknown operator, etc.)
}
```

## 📖 Syntax Guide

### Column Selection
Specify which fields to return with `SELECT`:
- `SELECT [name], [price] WHERE [status]:equals"active"`
- `SELECT [name] AS "product_name", [price] * 1.2 AS "vat_price"`
- `SELECT CAST([price], "money") AS "formatted_price" WHERE [status]:equals"active"`
- `SELECT CAST([price], "money") WHERE [status]:equals"active"` (Alias defaults to `price`)

### Alias Reuse
Aliases defined in the `SELECT` clause can be reused in subsequent `SELECT` expressions and in the `WHERE` clause:
- `SELECT [price] * 0.3 AS "a", [a] * 2 AS "b" WHERE [a]:gt 10`
- `SELECT CAST([price] * 0.3, "money") AS "a", [a] AS "a_copy"`

If no `SELECT` is specified, the system defaults to selecting all columns (`*`) in the database query.

### Default Selections for UI/Reports

You can define a default set of fields to be returned by `getSelections()` when no `SELECT` clause is provided. This is useful for building dynamic report tables.

```php
use AyupCreative\AdvancedSearch\Attributes\DefaultSelections;

#[DefaultSelections(['id', 'sku', 'price'])]
class Product extends Model { ... }
```

Or by defining a static method:

```php
public static function getAdvancedSearchDefaultSelections(): array
{
    return ['id', 'sku', 'price'];
}
```

If neither is defined, it fallbacks to the model's `getFillable()` list.

### Virtual Columns

Virtual columns allow you to expose internal database logic or complex calculations as safe, user-friendly field names. You can define them using the `#[VirtualColumn]` attribute on your model classes or static methods.

A virtual column can specify:
- How it's handled for **filtering** (`WHERE` clause) via a custom resolver method.
- How it's handled for **selection** (`SELECT` clause) via a SQL `expression`.

```php
use AyupCreative\AdvancedSearch\Attributes\VirtualColumn;

class Receipt extends Model implements Queryable
{
    use Searchable;

    #[VirtualColumn(
        name: 'purchase_price', 
        expression: 'CAST(amount / 100 AS DECIMAL(10,2))'
    )]
    public static function searchPurchasePrice($query, $op, $value)
    {
        // Custom resolver for filtering (WHERE)
        $query->whereRaw("CAST(amount / 100 AS DECIMAL(10,2)) {$op} ?", [$value]);
    }
}
```

If only an `expression` is provided (on the class or a method), it will be used for both selection and filtering. If a method is decorated, it acts as the primary resolver for filtering.

### Fields (Columns)
Fields are always wrapped in square brackets: `[status]`, `[customer.name]`.

### Literals (Values)
- **Strings**: Quoted or unquoted if they don't contain special characters: `"active"`, `'pending'`, `ABC123`.
- **Numbers**: Integers or decimals: `100`, `99.99`.
- **Lists**: Used for `in` or `between` operators: `(active, pending)`, `(10, 20)`.
- **Dynamic Functions**: Express real-time values: `now()`, `today()`, `yesterday()`, `tomorrow()`.
- **Method Chaining**: Call methods on dynamic values: `now()->subDays(7)`, `now()->startOfMonth()`.

### Arithmetic
Full support for `+`, `-`, `*`, `/` on the left-hand side of conditions:
- `[price] / 1.2 :gt 100`
- `([amount] + [shipping]) * 1.1 :lt 500`

### Relationship Aggregates
- **Existence**: `EXISTS([relationship])` (returns boolean)
- **Count**: `COUNT([relationship])` (returns integer)
- **Example**: `SELECT [name], COUNT([orders]) AS "order_count" WHERE EXISTS([orders])`

### Operators
- `equals`: `[status]:equals"active"`
- `in`: `[status]:in(processed, pending)`
- `gt` / `lt`: `[price]:gt 100`, `[price]:lt[discount_price]`
- `between`: `[created_at]:between("2024-01-01", "2024-12-31")`
- `contains`: `[name]:contains"John"`

### Relationship Traversal
The DSL supports querying across Eloquent relationships using dot notation:
- `[customer.name]:equals"John"` (Automatically uses `whereHas('customer', ...)`)
- `[registration.model.standard_warranty_years]:lt 10`

For selection, dotted paths are supported directly in the `SELECT` clause:
- `SELECT [customer.name], [customer.address.city]`

The system automatically detects relationships in the `SELECT` clause, eager-loads them using `$query->with()`, and ensures that necessary foreign keys (for `BelongsTo`) or primary keys (for `HasOne`/`HasMany`) are included in the SQL selection. Plural relationships (e.g., `HasMany`) are automatically resolved into arrays when accessed via `getSelectionValue()`.

### Boolean Logic
`([status]:equals"active" OR [priority]:gt 5) AND [category]:in(books, electronics)`

### Sorting & Limits
- `sort(column, direction)`: `sort(created_at, desc)`
- `limit(number)`: `limit(10)`

## Developer Guide

For detailed information on how to extend the system with custom operators or advanced resolvers, please see the [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md).

## 🛠️ Development

This project uses [pre-commit](https://pre-commit.com/) to maintain code quality and ensure consistent commit messages.

### Pre-commit Hooks

We use `pre-commit` to automatically run:
- **Pint**: Ensures PHP code style follows Laravel standards.
- **PHPUnit**: Runs the test suite to prevent regressions.
- **Conventional Commits**: Validates commit messages.

To set up `pre-commit` locally:

1. [Install pre-commit](https://pre-commit.com/#install) on your machine.
2. Run `pre-commit install` in the project root to set up the git hook scripts.
3. Run `pre-commit install --hook-type commit-msg` to enable commit message linting.

### Conventional Commits

We follow the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification for all commit messages. This allows us to automate our release process and generate consistent changelogs.

Common types:
- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code (white-space, formatting, etc)
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `perf`: A code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to the build process or auxiliary tools and libraries

### CI/CD & Automated Releases

Our GitHub Actions workflow handles the following sequentially:
1. **PHPUnit tests**: Runs tests across multiple PHP versions (8.2, 8.3, 8.4, 8.5).
2. **Commit/PR lint**: Validates that pull request titles follow conventional commit standards.
3. **Release Please**: Automatically creates GitHub releases, tags, and updates the changelog when a PR is merged into the `main` branch.

## Testing

Run the test suite with PHPUnit:

```bash
vendor/bin/phpunit
```

You can also run tests using Composer:

```bash
composer test 
```

For coverage reports (requires Xdebug):

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

Pint is also available for code formatting. Run `pint` to fix any issues.

```bash
composer pint
```

## License

MIT License.
