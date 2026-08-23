<?php

declare(strict_types=1);

namespace App\Api;

use Contempt\Attribute\Controller;
use Contempt\Attribute\FromBody;
use Contempt\Attribute\Length;
use Contempt\Attribute\NotBlank;
use Contempt\Attribute\Post;
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

#[Controller]
final readonly class ProfileController
{
    #[Post('/profiles', name: 'profiles.create')]
    public function create(#[FromBody] Profile $profile): Profile
    {
        return $profile;
    }
}
