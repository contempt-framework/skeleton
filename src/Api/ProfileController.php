<?php

declare(strict_types=1);

namespace App\Api;

use Contempt\Attribute\Controller;
use Contempt\Attribute\FromBody;
use Contempt\Attribute\Post;

#[Controller]
final readonly class ProfileController
{
    #[Post('/profiles', name: 'profiles.create')]
    public function create(#[FromBody] Profile $profile): Profile
    {
        return $profile;
    }
}
