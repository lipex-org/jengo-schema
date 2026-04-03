<?php

declare(strict_types=1);

namespace Tests\Unit\Reflector;

use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use Jengo\Schema\Support\ArrayUtils;
use Tests\Support\Entity\User;
use Tests\Support\Models\UserModel;
use Tests\Support\Schemas\UserFileSchema;
use Tests\Support\Schemas\UserSchema;

final class UserSchemaTest extends ReflectorTestCase
{
    public function testUserSchema(): void
    {
        $schema = SchemaReflector::reflect(UserSchema::class);
        $fieldsArray = ArrayUtils::toArray($schema->fields);
        $relationsArray = ArrayUtils::toArray($schema->relations);
        $fieldNames = array_column($fieldsArray, 'name');

        // classes
        $this->assertSame(UserSchema::class, $schema->schemaClass);
        $this->assertSame(UserModel::class, $schema->modelClass);
        $this->assertSame(User::class, $schema->entityClass);

        // primary key
        $this->assertSame('id', $schema->primaryKey->name);
        $this->assertFalse($schema->primaryKey->searchable);
        $this->assertFalse($schema->primaryKey->derived);

        // fields
        $this->assertEquals(5, count($schema->fields));
        $this->assertContains('first_name', $fieldNames);
        $this->assertContains('last_name', $fieldNames);
        $this->assertContains('email', $fieldNames);
        $this->assertContains('files', $fieldNames);

        // individual fields

        // first_name
        $firstNameField = $this->getField('first_name', $fieldsArray);

        $this->assertTrue($firstNameField->searchable);
        $this->assertFalse($firstNameField->derived);

        // last_name
        $lastNameField = $this->getField('last_name', $fieldsArray);

        $this->assertTrue($lastNameField->searchable);
        $this->assertFalse($lastNameField->derived);

        // email
        $emailField = $this->getField('email', $fieldsArray);

        $this->assertTrue($emailField->searchable);
        $this->assertFalse($emailField->derived);

        // files
        $relation = $this->getRelation('files', $relationsArray);

        $this->assertEquals(RelationMetadata::HAS_MANY, $relation->type);
        $this->assertTrue($relation->many);
        $this->assertEquals(UserFileSchema::class, $relation->schemaClass);
        $this->assertEquals('id', $relation->fromField);
        $this->assertEquals('user_id', $relation->toField);
        $this->assertEmpty($relation->select);

        // computed fields
        $this->assertEquals(3, count($schema->computed));

        $fullNameComputedField = $schema->computed[0];

        $this->assertEquals('getFullName', $fullNameComputedField->method);
        $this->assertEquals('full_name', $fullNameComputedField->name);
    }
}
