<?php

declare(strict_types=1);

namespace Jengo\Base\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for managing schemas.
 */
class SchemaCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:schema';
    protected $description = 'Consolidated database schema management tools.';
    protected $usage = 'jengo:schema <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Schema';
}
