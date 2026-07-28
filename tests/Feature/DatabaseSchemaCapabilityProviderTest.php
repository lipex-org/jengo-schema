<?php

declare(strict_types=1);

namespace Tests\Feature;

use Config\Database;
use Jengo\Schema\AI\DatabaseSchemaCapabilityProvider;
use Tests\TestCase;

final class DatabaseSchemaCapabilityProviderTest extends TestCase
{
    public function testGetCapabilities(): void
    {
        $forge = Database::forge('tests');
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('temp_cap_table', true);

        $provider = new DatabaseSchemaCapabilityProvider();
        $capabilities = $provider->getCapabilities();

        $this->assertNotEmpty($capabilities);
        $this->assertSame('Database Schema Mapping', $capabilities['name']);

        $tempTableFound = false;
        foreach ($capabilities['tables'] as $tableInfo) {
            if ($tableInfo['table'] === 'temp_cap_table') {
                $tempTableFound = true;
                $this->assertCount(2, $tableInfo['fields']);
                $this->assertSame('id', $tableInfo['fields'][0]['name']);
                $this->assertSame('title', $tableInfo['fields'][1]['name']);
            }
        }

        $this->assertTrue($tempTableFound);

        $forge = Database::forge('tests');
        $forge->dropTable('temp_cap_table', true);
    }
}
