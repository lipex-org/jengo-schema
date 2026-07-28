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

/**
 * @internal
 */
final class UserSchemaTest extends ReflectorTestCase
{
    public function testUserSchema(): void
    {
        $schema         = SchemaReflector::reflect(UserSchema::class);
        $fieldsArray    = ArrayUtils::toArray($schema->fields);
        $relationsArray = ArrayUtils::toArray($schema->relations);
        $fieldNames     = array_column($fieldsArray, 'name');

        // classes
        $this->assertSame(UserSchema::class, $schema->schemaClass);
        $this->assertSame(UserModel::class, $schema->modelClass);
        $this->assertSame(User::class, $schema->entityClass);

        // primary key
        $this->assertSame('id', $schema->primaryKey->name);
        $this->assertFalse($schema->primaryKey->searchable);
        $this->assertFalse($schema->primaryKey->derived);

        // fields
        $this->assertCount(5, $schema->fields);
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

        $this->assertSame(RelationMetadata::HAS_MANY, $relation->type);
        $this->assertTrue($relation->many);
        $this->assertSame(UserFileSchema::class, $relation->schemaClass);
        $this->assertSame('id', $relation->fromField);
        $this->assertSame('user_id', $relation->toField);
        $this->assertEmpty($relation->select);

        // computed fields
        $this->assertCount(3, $schema->computed);

        $fullNameComputedField = $schema->computed[0];

        $this->assertSame('getFullName', $fullNameComputedField->method);
        $this->assertSame('full_name', $fullNameComputedField->name);
    }
}
