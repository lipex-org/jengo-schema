# Jengo Schema

Declarative schema-driven querying, relationship derivation, entity hydration, infinite-scrolling cursor pagination, and automated TypeScript definitions generation for CodeIgniter 4 and the Jengo Framework.

Documentation: https://lipex-org.github.io/jengophp.com/packages/schema

## Installation

```bash
composer require jengo/schema
php spark jengo:schema setup
```

## Quick Start

```php
use App\Schemas\OrderSchema;
use function Jengo\Schema\query;

// Query with automatic relationship derivation and pagination
$result = query(OrderSchema::class)
    ->where('status', 'completed')
    ->derive(['user', 'items'])
    ->sort('created_at', 'DESC')
    ->paginate(page: 1, limit: 15)
    ->get();

$orders = $result->data;
```

## Documentation

For full guides on PHP 8 schema attributes, relationship derivation, cursor pagination, virtual schemas, Open mode, and TypeScript generator options, visit https://lipex-org.github.io/jengophp.com/packages/schema.

## License

Released under the MIT License.