<?php

declare(strict_types=1);

namespace Tests\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFileComments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_file_id' => [
                'type' => 'INT',
                'unsigned' => true
            ],
            'comment' => [
                'type' => 'TEXT',
                'null' => false
            ],
        ]);

        $this->forge->addForeignKey('user_file_id', 'user_files', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('file_comments');
    }

    public function down(): void
    {
        $this->forge->dropTable('file_comments');
    }
}
