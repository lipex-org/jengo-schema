# Jengo Schema

Declarative schema-driven querying for CodeIgniter 4.

Jengo Schema brings structured, schema-based querying, relationship derivation, entity hydration, infinite-scrolling cursor pagination, and automated TypeScript definitions generation to CodeIgniter 4 applications.

---

## Core Features

- **Schema-First Declarative Design**: Define structures, relations, field casting, and database model mappings using native PHP 8 Attributes.
- **Fluent Query API**: Build robust queries with automatic relationship derivation and nested joins (e.g. `derive('members.user')`).
- **Cursor & Offset Pagination**: Supports traditional offset-based pagination and modern opaque cursor-based pagination (`after`) for infinite scroll setups (complete with `$hasMore`, `$nextPage`, and `$nextCursor` DTO indicators).
- **Interactive Schema & TypeScript Generator**: Automates writing PHP Schema files and TypeScript definitions directly from database structure metadata.
- **Debug Tools**: Built-in QueryLogger and query plan explainer (`Explain`).

---

## Spark Commands

### 1. Setup Command
Publishes the default `JengoSchema.php` configuration file into the project's `app/Config` directory:
```bash
php spark jengo:schema setup
```

### 2. Generate Command
Automatically scans active PSR-4 namespace mappings to map database tables and generate matching Jengo Schema classes along with optional TypeScript definition files:
```bash
php spark jengo:schema generate [options]
```

**Options:**
- `--table`: Generate schema for a specific table only.
- `--force`: Force overwrite existing schema files.
- `--dbgroup`: Specify the database group to connect to.
- `--namespace`: Specify custom namespace for the generated schema classes (defaults to `App\Schemas`).
- `--dir`: Specify custom directory where schema classes should be saved (defaults to `app/Schemas`).
- `--dry-run`: Simulate generation without creating/modifying files on disk.
- `--with-vendor`: Generate schemas for vendor/system tables (defaults to false, only project tables are generated).
- `--ts`: Generate TypeScript interfaces alongside PHP Schemas.
- `--ts-dir`: Specify custom output directory for TypeScript interfaces (defaults to `resources/js/types/schemas`).

---

## Usage Examples

### Declarative Schema Definition
```php
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Relations\BelongsTo;
use Jengo\Schema\Hydration\Enums\Cast;

#[Model(model: OrderModel::class)]
class OrderSchema 
{
    #[PrimaryKey]
    public int $id;
    
    #[Field(searchable: true)]
    public string $reference;

    #[Field(cast: Cast::JSON)]
    public array $metadata;

    #[BelongsTo(relation: UserSchema::class, foreignKey: 'user_id')]
    public ?UserSchema $user;
}
```

### Querying & Cursor Pagination
```php
use Jengo\Schema\Query\Query;

// Traditional Offset Pagination
$orders = query(OrderSchema::class)
    ->inline()
    ->where('status', 'completed')
    ->paginate(1, 15);

// Cursor-Based Infinite Scrolling (Base64 Cursor)
$nextPage = query(OrderSchema::class)
    ->inline()
    ->after($base64CursorString)
    ->limit(10)
    ->get();
```

---

## Testing

This package includes a complete feature and unit test suite verifying schema reflection, query planning, hydration, cursor computation logic, and command executions:
```bash
./vendor/bin/phpunit
```

---

## License

MIT