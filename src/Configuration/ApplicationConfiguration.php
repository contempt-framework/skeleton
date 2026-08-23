<?php

declare(strict_types=1);

namespace App\Configuration;

use Contempt\Attribute\ConfigPrefix;
use Contempt\Attribute\Configuration;

#[Configuration]
#[ConfigPrefix('app')]
final readonly class ApplicationConfiguration
{
    public function __construct(public string $name = 'contempt-skeleton') {}
}
