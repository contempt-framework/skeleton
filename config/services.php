<?php

declare(strict_types=1);

use App\Service\BuildIdentity;
use Contempt\Container\Definition\Services;

return static function (Services $services): void {
    $services->singleton(BuildIdentity::class)->argument('frameworkVersion', '1.0.0');
};
