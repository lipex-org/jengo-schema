<?php

declare(strict_types=1);

namespace Tests\Feature;

use Config\Database;
use Tests\TestCase;

/**
 * @internal
 */
final class SchemaCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFileSystem();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanFileSystem();
    }

    public function testSchemaGenerateCommand(): void
    {
        $forge = Database::forge('tests');
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('temp_test_table', true);

        command('jengo:schema generate --table temp_test_table --with-vendor');

        $filePath = APPPATH . 'Schemas/TempTestTableSchema.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('class TempTestTableSchema', $content);
        $this->assertStringContainsString('public int $id;', $content);
        $this->assertStringContainsString('public string $title;', $content);

        $forge = Database::forge('tests');
        $forge->dropTable('temp_test_table', true);
    }

    private function cleanFileSystem(): void
    {
        $schemaFile = APPPATH . 'Schemas/TempTestTableSchema.php';
        if (file_exists($schemaFile)) {
            unlink($schemaFile);
        }
    }
}
