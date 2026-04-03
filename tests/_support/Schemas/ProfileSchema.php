<?php

declare(strict_types=1);

namespace Tests\Support\Schemas;

use CodeIgniter\I18n\Time;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Relations\BelongsTo;
use Jengo\Schema\Hydration\Enums\Cast;
use Tests\Support\Entity\Profile;
use Tests\Support\Models\ProfileModel;

#[Model(ProfileModel::class, Profile::class)]
final class ProfileSchema
{
    #[PrimaryKey()]
    public string $id;

    #[Field(cast: Cast::STRING)]
    public string $user_id;

    #[Field(cast: Cast::STRING)]
    public string $bio;

    #[Field(cast: Cast::STRING)]
    public string $avatar;

    public ?string $phone = null;

    public ?string $address = null;

    public ?string $github_handle = null;

    #[Field(cast: Cast::DATETIME)]
    public $updated_at;

    #[BelongsTo(
        UserSchema::class,
        'user_id'
    )]
    public $user;
}
