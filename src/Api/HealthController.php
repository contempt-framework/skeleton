<?php

declare(strict_types=1);

namespace App\Api;

use App\Configuration\ApplicationConfiguration;
use App\Service\BuildIdentity;
use Contempt\Attribute\Controller;
use Contempt\Attribute\Get;

#[Controller]
final readonly class HealthController
{
    public function __construct(
        private ApplicationConfiguration $configuration,
        private BuildIdentity $build,
    ) {}

    /** @return array{status: string, application: string, framework: string} */
    #[Get('/health', name: 'health')]
    public function __invoke(): array
    {
        return $this->response();
    }

    /** @return array{status: string, application: string, framework: string} */
    #[Get('/health/live', name: 'health.liveness')]
    public function liveness(): array
    {
        return $this->response();
    }

    /** @return array{status: string, application: string, framework: string} */
    #[Get('/health/ready', name: 'health.readiness')]
    public function readiness(): array
    {
        return $this->response();
    }

    /** @return array{status: string, application: string, framework: string} */
    #[Get('/health/startup', name: 'health.startup')]
    public function startup(): array
    {
        return $this->response();
    }

    /** @return array{status: string, application: string, framework: string} */
    private function response(): array
    {
        return [
            'status' => 'up',
            'application' => $this->configuration->name,
            'framework' => $this->build->frameworkVersion,
        ];
    }
}
