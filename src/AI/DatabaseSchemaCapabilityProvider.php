<?php

declare(strict_types=1);

namespace Jengo\Schema\AI;

use Jengo\Base\AI\AiCapableInterface;
use Tatter\Schemas\Drafter\Handlers\DatabaseHandler;

class DatabaseSchemaCapabilityProvider implements AiCapableInterface
{
    public function getCapabilities(): array
    {
        try {
            $db = (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') ? 'tests' : 'default';
            $handler = new DatabaseHandler(null, $db);
            $schemas = service('schemas');
            $schema = $schemas->draft($handler)->get();
        } catch (\Throwable $e) {
            return [];
        }

        $tablesInfo = [];

        foreach ($schema->tables as $tableName => $table) {
            if ($tableName === 'migrations') {
                continue;
            }

            $fields = [];
            foreach ($table->fields as $field) {
                $fields[] = [
                    'name' => $field->name,
                    'type' => $field->type ?? 'string',
                    'primary' => (bool) $field->primary_key,
                ];
            }

            $relations = [];
            foreach ($table->relations as $relation) {
                $relations[] = [
                    'table' => $relation->table,
                    'type' => $relation->type,
                ];
            }

            $tablesInfo[] = [
                'table' => $tableName,
                'fields' => $fields,
                'relations' => $relations,
            ];
        }

        return [
            'name' => 'Database Schema Mapping',
            'description' => 'The current tables, columns, and relationships defined in the database.',
            'tables' => $tablesInfo,
        ];
    }
}
