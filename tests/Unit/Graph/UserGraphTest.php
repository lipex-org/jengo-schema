<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Graph;

use Jengo\Schema\Graph\Node;
use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use PHPUnit\Framework\TestCase;
use Tests\Support\Schemas\UserSchema;
use Tests\Support\Schemas\ProfileSchema;

/**
 * Validates the RelationshipGraph when starting from a UserSchema root.
 */
final class UserGraphTest extends TestCase
{
    private RelationshipGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = SchemaReflector::reflect(UserSchema::class);

        $this->graph = RelationshipGraph::build(
            rootSchema: $schema,
            derivePaths: [
                'profile.user',
                'files'
            ],
        );
    }

    /**
     * Level 0: Root Node (User)
     */
    public function testUserRootIntegrity(): void
    {
        $root = $this->graph->root;

        $this->assertInstanceOf(Node::class, $root);
        $this->assertSame(UserSchema::class, $root->schema->schemaClass);
        $this->assertTrue($root->isRoot());

        // Check fields specific to User
        $this->assertCount(5, $root->schema->fields);
        $this->assertSame('first_name', $root->schema->fields[0]->name);
        $this->assertTrue($root->schema->fields[0]->searchable);
    }

    /**
     * Level 1: Child Node (Profile)
     */
    public function testChildNodeIsProfile(): void
    {
        $root = $this->graph->root;
        $this->assertCount(2, $root->children);

        $profileNode = $root->children[0];
        $this->assertSame(ProfileSchema::class, $profileNode->schema->schemaClass);
        $this->assertSame($root, $profileNode->parent);

        // Validate Edge connecting User to Profile
        $edge = $profileNode->edge;
        $this->assertSame('profile', $edge->relation->name);
        $this->assertSame(RelationMetadata::BELONGS_TO, $edge->relation->type);
        $this->assertSame('user_id', $edge->relation->toField);
    }

    /**
     * Level 2: Recursive Node (Back to User)
     */
    public function testGrandchildNodeRecursionBackToUser(): void
    {
        $profileNode = $this->graph->root->children[0];
        $this->assertCount(1, $profileNode->children);

        $recursiveUserNode = $profileNode->children[0];
        $this->assertSame(UserSchema::class, $recursiveUserNode->schema->schemaClass);
        $this->assertSame($profileNode, $recursiveUserNode->parent);

        // Verify the edge back to user
        $edge = $recursiveUserNode->edge;
        $this->assertSame('user', $edge->relation->name);
        $this->assertNull($edge->relation->toField, "belongs_to 'user' usually has a null toField as it defaults to PK");
    }

    /**
     * Validates that the recursion stops at level 2.
     */
    public function testGraphTerminatesCorrectly(): void
    {
        $recursiveUserNode = $this->graph->root->children[0]->children[0];
        $this->assertEmpty($recursiveUserNode->children, "The graph should not recurse beyond the second User instance.");
    }

    /**
     * Tests that computed methods are preserved through the graph nodes.
     */
    public function testComputedMetadataConsistency(): void
    {
        $root = $this->graph->root;
        $recursiveUser = $root->children[0]->children[0];

        foreach ([$root, $recursiveUser] as $node) {
            $this->assertCount(1, $node->schema->computed);
            $this->assertSame('full_name', $node->schema->computed[0]->name);
            $this->assertSame('getFullName', $node->schema->computed[0]->method);
        }
    }
}