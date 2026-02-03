<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Fabricator;
use Config\Database;
use Tests\Support\Models\ProfileModel;
use Tests\Support\Models\UserFileModel;
use Tests\Support\Models\UserModel;

class TestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected bool $fill = true;

    public function setUp(): void
    {
        $this->loadDependencies();
        $this->migrateDatabase();
        
        if ($this->fill) {
            $this->generateData();
        }
    }

    public function tearDown(): void
    {
        $this->regressDatabase();
        $this->loadDependencies();
        $this->migrateDatabase();
    }

    private function generateData(): void
    {
        $conn = Database::connect('tests');
        $userModel = new UserModel($conn);
        $userFileModel = new UserFileModel($conn);
        $userProfileModel = new ProfileModel($conn);

        $users = new Fabricator($userModel)->make(10);
        $userFiles = new Fabricator($userFileModel)->make(10);
        $userProfiles = new Fabricator($userProfileModel)->make(10);

        $conn->table('users')->insertBatch($users);
        $conn->table('user_files')->insertBatch($userFiles);
        $conn->table('profiles')->insertBatch($userProfiles);
    }
}
