<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Faker\Generator;

final class UserModel extends Model
{
    protected $table = 'users';

    public function fake(Generator $generator): array
    {
        return [
            'first_name' => $generator->firstName(),
            'last_name' => $generator->lastName(),
            'email' => $generator->email(),
            'created_at' => Time::now()->toDateTimeString(),
            'updated_at' => Time::now()->toDateTimeString(),
        ];
    }
}