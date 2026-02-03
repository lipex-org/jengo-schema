<?php

declare(strict_types=1);

namespace Tests\Support\Schemas;

use Jengo\Schema\Attributes\Computed;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Relations\BelongsTo;
use Jengo\Schema\Attributes\Relations\HasMany;
use Tests\Support\Entity\User;
use Tests\Support\Models\UserModel;

#[Model(UserModel::class, User::class)]
final class UserSchema
{
    #[PrimaryKey()]
    public string $id;

    #[Field(searchable: true)]
    public string $first_name;

    #[Field(searchable: true)]
    public string $last_name;

    #[Field(searchable: true)]
    public string $email;

    #[BelongsTo(
        ProfileSchema::class,
        'id',
        'user_id',
    )]
    public $profile;

    #[HasMany(
        UserFileSchema::class,
        'id',
        'user_id'
    )]
    public array $files = [];

    #[Computed('full_name')]
    public function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
