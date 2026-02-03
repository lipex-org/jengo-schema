<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Graph;

use Jengo\Schema\Graph\Node;
use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use PHPUnit\Framework\TestCase;
use Tests\Support\Schemas\ProfileSchema;
use Tests\Support\Schemas\UserSchema;

/**
 * Validates the RelationshipGraph when starting from a ProfileSchema root.
 */
final class ProfileGraphTest extends TestCase
{
    private RelationshipGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();

         $schema = SchemaReflector::reflect(ProfileSchema::class);
        
        $this->graph = RelationshipGraph::build(
            rootSchema: $schema,
            derivePaths: ['user.profile.user'],
        );
    }

    /**
     * Level 0: Root Node (Profile)
     */
    public function testProfileRootIntegrity(): void
    {
        $root = $this->graph->root;

        $this->assertInstanceOf(Node::class, $root);
        $this->assertSame(ProfileSchema::class, $root->schema->schemaClass);
        $this->assertTrue($root->isRoot());
        
        // Profile should have 8 fields as per the dump
        $this->assertCount(8, $root->schema->fields);
        $this->assertSame('user_id', $root->schema->fields[0]->name);
        $this->assertSame('github_handle', $root->schema->fields[5]->name);
    }

    /**
     * Level 1: Child Node (User)
     */
    public function testChildNodeIsUser(): void
    {
        $root = $this->graph->root;
        $this->assertCount(1, $root->children);

        $userNode = $root->children[0];
        $this->assertSame(UserSchema::class, $userNode->schema->schemaClass);
        $this->assertSame($root, $userNode->parent);
        
        // Check User fields and computed properties
        $this->assertCount(5, $userNode->schema->fields);
        $this->assertCount(1, $userNode->schema->computed);
        $this->assertSame('full_name', $userNode->schema->computed[0]->name);
    }

    /**
     * Level 2: Recursive Node (Back to Profile)
     */
    public function testGrandchildNodeRecursionBackToProfile(): void
    {
        $userNode = $this->graph->root->children[0];
        $this->assertCount(1, $userNode->children);

        $recursiveProfileNode = $userNode->children[0];
        $this->assertSame(ProfileSchema::class, $recursiveProfileNode->schema->schemaClass);
        $this->assertSame($userNode, $recursiveProfileNode->parent);

        // Verify the edge connecting User back to Profile
        $edge = $recursiveProfileNode->edge;
        $this->assertSame('profile', $edge->relation->name);
        $this->assertSame(RelationMetadata::BELONGS_TO, $edge->relation->type);
    }

    /**
     * Level 3: Final Recursion (Back to User)
     */
    public function testGreatGrandchildNodeRecursionBackToUser(): void
    {
        $recursiveProfileNode = $this->graph->root->children[0]->children[0];
        $this->assertCount(1, $recursiveProfileNode->children);

        $finalUserNode = $recursiveProfileNode->children[0];
        $this->assertSame(UserSchema::class, $finalUserNode->schema->schemaClass);
        
        // Verify this is the terminal node (leaf)
        $this->assertEmpty($finalUserNode->children, "The graph should terminate at the third level (User).");
    }

    /**
     * Tests that the "many" flag is correctly false for these relationships.
     */
    public function testRelationshipsAreNotMany(): void
    {
        $node = $this->graph->root;
        
        // Traverse through and check isMany()
        while (!empty($node->children)) {
            $node = $node->children[0];
            $this->assertFalse($node->isMany(), "Relationship to {$node->schema->schemaClass} should not be 'many'.");
        }
    }
}