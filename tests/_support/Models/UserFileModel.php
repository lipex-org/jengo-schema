<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Faker\Generator;

final class UserFileModel extends Model
{
    protected $table = 'user_files';

    public function fake(Generator $generator): array
    {
        return [
            'name' => $generator->text(5),
            'size' => $generator->randomFloat(max: 20),
            'user_id' => $generator->numberBetween(1, 10),
            'path' => $generator->text(16),
            'created_at' => Time::now()->toDateTimeString(),
            'updated_at' => Time::now()->toDateTimeString(),
        ];
    }
}
