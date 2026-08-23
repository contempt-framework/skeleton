<?php

declare(strict_types=1);

namespace App\Api;

use Contempt\Attribute\Length;
use Contempt\Attribute\NotBlank;
use Contempt\Attribute\Range;
use Contempt\Attribute\SerializedName;

final readonly class Profile
{
    public function __construct(
        #[SerializedName('identifier')]
        #[Range(min: 1)]
        public int $id,
        #[NotBlank]
        #[Length(min: 2, max: 100)]
        public string $name,
    ) {}
}
