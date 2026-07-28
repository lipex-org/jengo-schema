<?php

declare(strict_types=1);

namespace Jengo\Schema;

use Jengo\Schema\Query\FluentQueryAPI;

/**
 * Helper function to initialze the FluentQueryAPI
 */
function query(string $schema): FluentQueryAPI
{
    return new FluentQueryAPI($schema);
}

/**
 * Dumps output of the query logger.
 * NOTE: This is a helper function for debugging purposes. It will dump all the logged queries and their details,
 * and then exit the script. Use this function after running your queries to see the logged information.
 * This function avoids execution in production
 */
function dump_query(): void
{
    FluentQueryAPI::dd();
}
