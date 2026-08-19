<?php

declare(strict_types=1);

namespace Jengo\Schema\Commands\Variants\Schema;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Publisher\Publisher;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Throwable;

class SetupVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'setup';
    }

    public static function description(): string
    {
        return 'Publish Jengo Schema configuration to the project.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [];
    }

    public function run(array $params): void
    {
        CLI::write('Publishing Jengo Schema configurations...', 'cyan');

        $source = __DIR__ . '/../../../Publisher/Stubs';
        $destination = APPPATH . 'Config';

        try {
            $publisher = new Publisher($source, $destination);

            if ($publisher->publish()) {
                CLI::write('Published: app/Config/JengoSchema.php', 'green');
            } else {
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
                    throw new \Exception("Publisher failed to copy configuration file: " . var_export($publisher->getErrors(), true));
                }
                CLI::error('Publisher failed to copy configuration file.');
            }
        } catch (Throwable $e) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') {
                throw $e;
            }
            CLI::error('Setup failed: ' . $e->getMessage());
        }
    }
}
