<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Fabricator;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Database;
use Tests\Support\Models\ProfileModel;
use Tests\Support\Models\UserFileModel;
use Tests\Support\Models\UserModel;

/**
 * @internal
 */
class TestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $tables = ['file_comments', 'user_files', 'profiles', 'users'];
    protected $namespace = 'Tests\Support';
    protected bool $fill = true;
    protected MockInputOutput $io;
    protected array $cliOutputs;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = Database::connect('tests');
        $conn->table('file_comments')->emptyTable();
        $conn->table('user_files')->emptyTable();
        $conn->table('profiles')->emptyTable();
        $conn->table('users')->emptyTable();

        try {
            $conn->query('DELETE FROM sqlite_sequence WHERE name IN ("users", "user_files", "profiles", "file_comments")');
        } catch (\Throwable $e) {
        }

        if ($this->fill) {
            $this->generateData();
        }

        $this->io = new MockInputOutput();

        CLI::setInputOutput($this->io);
    }

    protected function tearDown(): void
    {
        $this->cliOutputs = $this->io->getOutputs();

        CLI::resetInputOutput();

        parent::tearDown();
    }

    private function generateData(): void
    {
        $conn = Database::connect('tests');
        $userModel = new UserModel($conn);
        $userFileModel = new UserFileModel($conn);
        $userProfileModel = new ProfileModel($conn);

        $users = (new Fabricator($userModel))->make(10);
        foreach ($users as $i => &$user) {
            $user['id'] = $i + 1;
        }
        unset($user);

        $userFiles = (new Fabricator($userFileModel))->make(10);
        $userProfiles = (new Fabricator($userProfileModel))->make(10);

        $conn->table('users')->insertBatch($users);
        $conn->table('user_files')->insertBatch($userFiles);
        $conn->table('profiles')->insertBatch($userProfiles);
    }
}
