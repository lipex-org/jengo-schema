<?php

declare(strict_types=1);

namespace Tests\Unit\Graph;

use Jengo\Schema\Graph\Node;
use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use Tests\Support\Schemas\ProfileSchema;
use Tests\Support\Schemas\UserFileSchema;
use Tests\Support\Schemas\UserSchema;

final class UserFileGraphTest extends GraphTestCase
{
    private RelationshipGraph $graph;

    public function setUp(): void
    {
        parent::setUp();

        $schema = SchemaReflector::reflect(UserFileSchema::class);

        $this->graph = RelationshipGraph::build(
            rootSchema: $schema,
            derivePaths: ['user.profile.user'],
        );
    }

    /**
     * Level 0: Root Node (UserFile)
     */
    public function testRootLevelIntegrity(): void
    {
        $root = $this->graph->root;

        $this->assertInstanceOf(Node::class, $root);
        $this->assertSame(UserFileSchema::class, $root->schema->schemaClass);
        $this->assertTrue($root->isRoot());
        $this->assertCount(1, $root->children, "UserFile should have one child: User.");
    }

    /**
     * Level 1: Child Node (User)
     */
    public function testFirstLevelChildUser(): void
    {
        $userNode = $this->graph->root->children[0];

        $this->assertSame(UserSchema::class, $userNode->schema->schemaClass);
        $this->assertSame($this->graph->root, $userNode->parent, "User node must point back to UserFile.");
        $this->assertFalse($userNode->isMany());

        // Check relation metadata on the edge
        $this->assertSame(RelationMetadata::BELONGS_TO, $userNode->edge->relation->type);
        $this->assertSame('user', $userNode->edge->relation->name);
    }

    /**
     * Level 2: Grandchild Node (Profile)
     */
    public function testSecondLevelGrandchildProfile(): void
    {
        $userNode = $this->graph->root->children[0];
        $profileNode = $userNode->children[0];

        $this->assertSame(ProfileSchema::class, $profileNode->schema->schemaClass);
        $this->assertSame($userNode, $profileNode->parent, "Profile node must point back to User.");

        // Validate Profile fields count (matches the 8 fields in your dump)
        $this->assertCount(8, $profileNode->schema->fields);

        // Validate specific field in Profile
        $githubField = array_filter($profileNode->schema->fields, fn($f) => $f->name === 'github_handle');
        $this->assertNotEmpty($githubField);
    }

    /**
     * Level 3: Great-Grandchild Node (User recursion)
     */
    public function testThirdLevelRecursionBackToUser(): void
    {
        $profileNode = $this->graph->root->children[0]->children[0];

        $this->assertCount(1, $profileNode->children, "Profile should have recursed back to User.");

        $recursiveUserNode = $profileNode->children[0];
        $this->assertSame(UserSchema::class, $recursiveUserNode->schema->schemaClass);
        $this->assertSame($profileNode, $recursiveUserNode->parent);

        // This node is a leaf in your current dump (children count is 0)
        $this->assertCount(0, $recursiveUserNode->children, "Graph should terminate at the third level.");
    }

    /**
     * Tests Computed Properties at multiple levels.
     */
    public function testComputedMetadataIntegrity(): void
    {
        // User node at Level 1
        $userNode = $this->graph->root->children[0];
        $this->assertCount(3, $userNode->schema->computed);
        $this->assertSame('full_name', $userNode->schema->computed[0]->name);

        // Recursive User node at Level 3
        $recursiveUserNode = $userNode->children[0]->children[0];
        $this->assertCount(3, $recursiveUserNode->schema->computed);
        $this->assertSame('getFullName', $recursiveUserNode->schema->computed[0]->method);
    }
}
