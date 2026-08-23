<?php

declare(strict_types=1);

namespace App\Service;

final readonly class BuildIdentity
{
    public function __construct(public string $frameworkVersion) {}
}
