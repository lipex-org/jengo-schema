<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Faker\Generator;

final class ProfileModel extends Model
{
    protected $table = 'profiles';

    public function fake(Generator $generator): array
    {
        return [
            'user_id' => $generator->unique()->numberBetween(1, 10),
            'phone' => $generator->phoneNumber(),
            'address' => $generator->address(),
            'avatar' => $generator->url(),
            'bio' => $generator->text(16),
            'github_handle' => $generator->text(16),
            'updated_at' => Time::now()->toDateTimeString(),
        ];
    }
}
