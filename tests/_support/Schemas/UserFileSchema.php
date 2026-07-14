<?php

declare(strict_types=1);

namespace Tests\Support\Schemas;

use Jengo\Schema\Attributes\Computed;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Relations\BelongsTo;
use Jengo\Schema\Hydration\Enums\Cast;
use Jengo\Schema\Attributes\Relations\HasMany;
use Tests\Support\Entity\UserFile;
use Tests\Support\Models\UserFileModel;

#[Model(UserFileModel::class, UserFile::class)]

/**
 * @property string $message
 * @property string $manipulatedMessage
 */
class UserFileSchema
{
    #[PrimaryKey()]
    public int $id;

    #[Field(searchable: true)]
    public string $name;

    #[Field(cast: Cast::FLOAT)]
    public float $size;

    #[Field()]
    public string $path;

    public int $user_id;

    #[BelongsTo(
        schema: UserSchema::class,
        from: 'user_id')
    ]
    public $user;

    #[HasMany(
        FileCommentSchema::class,
        'id',
        'user_file_id'
    )]
    public array $comments = [];

    #[Computed('message', ['name', 'size'])]
    public function getMessage(): string
    {
        return "File: {$this->name} ({$this->size} bytes)";
    }

    // test for using computed values as dependecies for other computed values
    #[Computed('manipulatedMessage', ['message'])]
    public function getManipulatedMessage(): string
    {
        return strtoupper($this->message);
    }
}
