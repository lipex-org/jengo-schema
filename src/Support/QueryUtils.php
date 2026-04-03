<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use CodeIgniter\Model;
use Jengo\Schema\Metadata\SchemaMetadata;
use RuntimeException;

final class QueryUtils
{

    public static function resolveTableFromSchema(SchemaMetadata $schema): string
    {
        $modelClass = $schema->modelClass;

        // chec if class exists
        if (!class_exists($modelClass)) {
            throw new RuntimeException("Model class {$modelClass} does not exist");
        }

        /** @var Model $modelInstance */
        $modelInstance = new $modelClass();

        if (!$modelInstance instanceof Model) {
            throw new RuntimeException("Model class {$modelClass} is not an instance of CodeIgniter Model");
        }

        return $modelInstance->builder()->getTable();
    }
}
