<?php

declare(strict_types=1);

namespace Tests\Unit\Reflector;

use Jengo\Schema\Reflection\SchemaReflector;
use Jengo\Schema\Support\ArrayUtils;
use Tests\Support\Schemas\UserSchema;
use Jengo\Schema\Metadata\RelationMetadata;
use Tests\Support\Entity\UserFile;
use Tests\Support\Models\UserFileModel;
use Tests\Support\Schemas\UserFileSchema;

final class UserFileSchemaTest extends ReflectorTestCase
{
    public function testUserFileSchema(): void
    {
        $schema = SchemaReflector::reflect(UserFileSchema::class);
        $fieldsArray = ArrayUtils::toArray($schema->fields);
        $relationsArray = ArrayUtils::toArray($schema->relations);
        $fieldNames = array_column($fieldsArray, 'name');

        // classes
        $this->assertSame(UserFileSchema::class, $schema->schemaClass);
        $this->assertSame(UserFileModel::class, $schema->modelClass);
        $this->assertSame(UserFile::class, $schema->entityClass);

        // primary key
        $this->assertSame('id', $schema->primaryKey->name);
        $this->assertFalse($schema->primaryKey->searchable);
        $this->assertFalse($schema->primaryKey->derived);

        // fields
        $this->assertEquals(5, count($schema->fields));
        $this->assertContains('name', $fieldNames);
        $this->assertContains('size', $fieldNames);
        $this->assertContains('path', $fieldNames);
        $this->assertContains('user_id', $fieldNames);
        $this->assertContains('user', $fieldNames);

        // individual fields

        // name
        $nameField = $this->getField('name', $fieldsArray);

        $this->assertTrue($nameField->searchable);
        $this->assertFalse($nameField->derived);

        // size
        $field = $this->getField('size', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);

        // path
        $field = $this->getField('path', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);

        // user_id
        $field = $this->getField('user_id', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);

        // user_id
        $relation = $this->getRelation('user', $relationsArray);

        $this->assertEquals(RelationMetadata::BELONGS_TO, $relation->type);
        $this->assertFalse($relation->many);
        $this->assertEquals(UserSchema::class, $relation->schemaClass);
        $this->assertEquals('user_id', $relation->fromField);
        $this->assertEmpty($relation->select);
        $this->assertEmpty($relation->toField);
    }
}
