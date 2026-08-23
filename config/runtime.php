<?php

declare(strict_types=1);

use Contempt\Config\Provider\ArrayConfigurationProvider;
use Contempt\Config\Provider\DotEnvBootstrap;
use Contempt\Config\Provider\DotEnvLoadingPolicy;
use Contempt\Config\Provider\EnvironmentConfigurationProvider;
use Contempt\Config\Provider\ProviderChain;
use Contempt\Config\RuntimeConfiguration;

$applicationRoot = dirname(__DIR__);
$environment = DotEnvBootstrap::selectedEnvironment();
$loadingFlag = $_ENV['CONTEMPT_LOAD_DOTENV'] ?? $_SERVER['CONTEMPT_LOAD_DOTENV'] ?? null;

if ($loadingFlag !== null && !is_string($loadingFlag)) {
    throw new InvalidArgumentException('CONTEMPT_LOAD_DOTENV must be a string when defined.');
}

$bootstrapped = new DotEnvBootstrap()->boot(
    $applicationRoot . '/.env',
    $environment,
    DotEnvLoadingPolicy::fromFlag($loadingFlag),
);

return new RuntimeConfiguration(
    $bootstrapped->environment,
    new ProviderChain([
        new ArrayConfigurationProvider([
            'app' => ['name' => 'contempt-skeleton'],
        ]),
        new EnvironmentConfigurationProvider($bootstrapped->values, [
            'APP_NAME' => 'app.name',
        ]),
    ]),
);
