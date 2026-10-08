<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/jengophp.com/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Schema</h1>

<p align="center">
  <strong>Declarative schema derivation, relational query builder with context-aware pagination clamping, and automated TypeScript definitions generator for CodeIgniter 4.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/schema"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/schema/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/schema/issues"><strong>Issues</strong></a>
</p>

---

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