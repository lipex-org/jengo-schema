<?php

declare(strict_types=1);

namespace Tests\Support\Entity;

use CodeIgniter\Entity\Entity;

final class User extends Entity
{
    protected $datamap    = [];
    protected $attributes = [];
    protected $casts      = [];
}
