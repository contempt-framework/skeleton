<?php

declare(strict_types=1);

use Contempt\Config\RuntimeConfiguration;
use Contempt\Console\ConsoleExtension;
use Contempt\Container\Definition\Services;
use Contempt\DevTools\Build\ApplicationBuildConfiguration;
use Contempt\DevTools\Build\ApplicationCompiler;
use Contempt\DevTools\Build\SourceFingerprint;
use Contempt\Http\HttpExtension;

$applicationRoot = dirname(__DIR__);
$runtimeConfiguration = require $applicationRoot . '/config/runtime.php';

if (!$runtimeConfiguration instanceof RuntimeConfiguration) {
    throw new \LogicException('config/runtime.php must return RuntimeConfiguration.');
}

$projectRoot = is_file($applicationRoot . '/composer.lock') ? $applicationRoot : dirname($applicationRoot, 2);
$buildDirectory = $projectRoot === $applicationRoot
    ? 'var/contempt/build'
    : 'packages/skeleton/var/contempt/build';
$configHash = SourceFingerprint::hash($applicationRoot, [
    'config/build.php',
    'config/runtime.php',
    'config/services.php',
]);
$services = new Services();
$configureServices = require $applicationRoot . '/config/services.php';

if (!$configureServices instanceof \Closure) {
    throw new \LogicException('config/services.php must return a service configuration closure.');
}

$configureServices($services);

return new ApplicationCompiler()->plan(new ApplicationBuildConfiguration(
    projectRoot: $projectRoot,
    applicationRoot: $applicationRoot,
    sourceRoots: ['src'],
    buildDirectory: $buildDirectory,
    containerClass: 'ContemptGenerated\\ApplicationContainer',
    frameworkVersion: '1.0.0',
    configurationSchemaHash: $configHash,
    environment: $runtimeConfiguration->environment,
    debug: $runtimeConfiguration->environment->allowsDebug(),
    extensions: [
        'contempt/console' => '1.0.0',
        'contempt/http' => '1.0.0',
    ],
    services: $services->toDefinitions(),
    compilerExtensions: [new ConsoleExtension(), new HttpExtension()],
));
