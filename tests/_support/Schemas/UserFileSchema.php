<?php

declare(strict_types=1);

namespace Tests\Support\Schemas;

use Jengo\Schema\Attributes\Computed;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Relations\BelongsTo;
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
    public float $size;
    public string $path;

    public int $user_id;

    #[BelongsTo(
        schema: UserSchema::class,
        from: 'user_id')
    ]
    public $user;

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
