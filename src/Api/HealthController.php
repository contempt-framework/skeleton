<?php

declare(strict_types=1);

namespace App\Api;

use App\Configuration\ApplicationConfiguration;
use App\Service\BuildIdentity;
use Contempt\Attribute\Controller;
use Contempt\Attribute\Get;
use Contempt\Http\Request;

#[Controller]
final readonly class HealthController
{
    public function __construct(
        private ApplicationConfiguration $configuration,
        private BuildIdentity $build,
    ) {}

    /** @return array{status: string, application: string, framework: string} */
    #[Get('/health', name: 'health')]
    public function __invoke(Request $request): array
    {
        return [
            'status' => 'up',
            'application' => $this->configuration->name,
            'framework' => $this->build->frameworkVersion,
        ];
    }
}
