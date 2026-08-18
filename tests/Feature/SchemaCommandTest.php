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

        $tsDir = SUPPORTPATH . 'ts_schemas';
        command("jengo:schema generate --table temp_test_table --with-vendor --ts --ts-dir={$tsDir}");

        $filePath = APPPATH . 'Schemas/TempTestTableSchema.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('class TempTestTableSchema', $content);
        $this->assertStringContainsString('public int $id;', $content);
        $this->assertStringContainsString('public string $title;', $content);

        // Verify TS Interface
        $tsFilePath = $tsDir . '/TempTestTableSchema.ts';
        $this->assertFileExists($tsFilePath);
        $tsContent = file_get_contents($tsFilePath);
        $this->assertStringContainsString('export interface TempTestTableSchema', $tsContent);
        $this->assertStringContainsString('id: number;', $tsContent);
        $this->assertStringContainsString('title: string;', $tsContent);
        $this->assertStringContainsString('user_id: number | null;', $tsContent);

        // Verify TS Index
        $indexFilePath = $tsDir . '/index.ts';
        $this->assertFileExists($indexFilePath);
        $indexContent = file_get_contents($indexFilePath);
        $this->assertStringContainsString("export * from './TempTestTableSchema';", $indexContent);

        $forge = Database::forge('tests');
        $forge->dropTable('temp_test_table', true);
    }

    private function cleanFileSystem(): void
    {
        $schemaFile = APPPATH . 'Schemas/TempTestTableSchema.php';
        if (file_exists($schemaFile)) {
            unlink($schemaFile);
        }

        $tsDir = SUPPORTPATH . 'ts_schemas';
        if (is_dir($tsDir)) {
            $files = glob($tsDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tsDir);
        }
    }
}
