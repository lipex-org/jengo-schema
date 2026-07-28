<?php

declare(strict_types=1);

namespace Tests\Support\Entity;

use CodeIgniter\Entity\Entity;

final class UserFile extends Entity
{
    protected $datamap    = [];
    protected $attributes = [];
    protected $casts      = [];
}
