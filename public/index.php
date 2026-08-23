<?php

declare(strict_types=1);

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\RuntimeConfiguration;
use Contempt\Contracts\Runtime\Runtime;
use Contempt\Core\Time\SystemClock;
use Contempt\Http\Fpm\FpmRuntime;
use Contempt\Http\Fpm\NativeEmissionTarget;
use Contempt\Http\Fpm\NativeRequestSource;
use Contempt\Http\Fpm\ResponseEmitter;
use Contempt\Http\Fpm\ServerRequestFactory;
use Contempt\Kernel\Artifact\CompiledRuntimeLoader;
use Contempt\Kernel\Error\ErrorHandler;
use Contempt\Kernel\Error\ErrorHandlerConfiguration;
use Contempt\Kernel\Error\ErrorReporter;
use Contempt\Kernel\Error\NativeEmergencyOutput;
use Contempt\Kernel\Error\NativeErrorLogSink;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
}

require $autoload;

$composerLock = dirname(__DIR__) . '/composer.lock';

if (!is_file($composerLock)) {
    $composerLock = dirname(__DIR__, 3) . '/composer.lock';
}

$errors = new ErrorHandler(
    ErrorHandlerConfiguration::production(),
    new NativeErrorLogSink(),
    new NativeEmergencyOutput(),
    new SystemClock(),
);
exit(new FpmRuntime(
    $errors,
    new ServerRequestFactory(),
    new ResponseEmitter(new NativeEmissionTarget()),
)->run(
    static function () use ($composerLock, $errors): Runtime {
        $configuration = require dirname(__DIR__) . '/config/runtime.php';

        if (!$configuration instanceof RuntimeConfiguration) {
            throw new LogicException('config/runtime.php must return RuntimeConfiguration.');
        }

        return new CompiledRuntimeLoader(
            buildDirectory: dirname(__DIR__) . '/var/contempt/build',
            environment: $configuration->environment,
            composerLockPath: $composerLock,
        )->load(runtimeServices: [
            ConfigurationProvider::class => $configuration->provider,
            ErrorReporter::class => $errors,
        ]);
    },
    new NativeRequestSource(),
));
