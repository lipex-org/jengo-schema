<?php 

declare(strict_types=1);

namespace Tests\Unit\Reflector;

use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use Jengo\Schema\Support\ArrayUtils;
use Tests\Support\Entity\Profile;
use Tests\Support\Models\ProfileModel;
use Tests\Support\Schemas\ProfileSchema;
use Tests\Support\Schemas\UserSchema;

final class ProfileSchemaTest extends ReflectorTestCase
{

    public function testProfileSchema(): void
    {
        $schema = SchemaReflector::reflect(ProfileSchema::class);
        $fieldsArray = ArrayUtils::toArray($schema->fields);
        $relationsArray = ArrayUtils::toArray($schema->relations);
        $fieldNames = array_column($fieldsArray, 'name');

        // classes
        $this->assertSame(ProfileSchema::class, $schema->schemaClass);
        $this->assertSame(ProfileModel::class, $schema->modelClass);
        $this->assertSame(Profile::class, $schema->entityClass);

        // primary key
        $this->assertSame('id', $schema->primaryKey->name);
        $this->assertFalse($schema->primaryKey->searchable);
        $this->assertFalse($schema->primaryKey->derived);

        // fields
        $this->assertEquals(8, count($schema->fields));
        $this->assertContains('updated_at', $fieldNames);
        $this->assertContains('github_handle', $fieldNames);
        $this->assertContains('address', $fieldNames);
        $this->assertContains('phone', $fieldNames);
        $this->assertContains('avatar', $fieldNames);
        $this->assertContains('bio', $fieldNames);
        $this->assertContains('user_id', $fieldNames);
        $this->assertContains('user', $fieldNames);

        // individual fields

        // name
        $nameField = $this->getField('bio', $fieldsArray);

        $this->assertFalse($nameField->searchable);
        $this->assertFalse($nameField->derived);

        // size
        $field = $this->getField('avatar', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);

        // path
        $field = $this->getField('phone', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);

        // user_id
        $field = $this->getField('user_id', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);
        
        // user_id
        $field = $this->getField('address', $fieldsArray);

        $this->assertFalse($field->searchable);
        $this->assertFalse($field->derived);
        
        // user_id
        $field = $this->getField('github_handle', $fieldsArray);

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
