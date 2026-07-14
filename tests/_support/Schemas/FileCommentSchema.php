<?php

declare(strict_types=1);

namespace Tests\Support\Schemas;

use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Tests\Support\Entity\FileComment;
use Tests\Support\Models\FileCommentModel;

#[Model(FileCommentModel::class, FileComment::class)]
class FileCommentSchema
{
    #[PrimaryKey()]
    public int $id;

    #[Field()]
    public string $comment;

    #[Field()]
    public int $user_file_id;
}
