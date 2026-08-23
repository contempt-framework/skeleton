<?php

declare(strict_types=1);

use App\Kernel\ApplicationBootstrap;
use Contempt\Contracts\Runtime\Runtime;
use Contempt\Http\Fpm\FpmRuntime;
use Contempt\Http\Fpm\NativeEmissionTarget;
use Contempt\Http\Fpm\NativeRequestSource;
use Contempt\Http\Fpm\ResponseEmitter;
use Contempt\Http\Fpm\ServerRequestFactory;

$applicationRoot = dirname(__DIR__);
$autoload = $applicationRoot . '/vendor/autoload.php';

if (is_file($autoload) && !is_link($autoload) && is_readable($autoload)) {
    require $autoload;
} elseif (!class_exists(ApplicationBootstrap::class)) {
    error_log('Contempt bootstrap failed: Composer autoload is unavailable.');
    http_response_code(500);
    exit(1);
}

$errors = ApplicationBootstrap::productionErrors();
$bootstrapErrors = $errors->install();

try {
    $exitCode = new FpmRuntime(
        $errors,
        new ServerRequestFactory(),
        new ResponseEmitter(new NativeEmissionTarget()),
    )->run(
        static function () use ($applicationRoot, $errors): Runtime {
            $bootstrap = require $applicationRoot . '/config/bootstrap.php';

            if (!$bootstrap instanceof ApplicationBootstrap) {
                throw new \LogicException('config/bootstrap.php must return ApplicationBootstrap.');
            }

            return $bootstrap->runtime($errors);
        },
        new NativeRequestSource(),
    );
} finally {
    $bootstrapErrors->close();
}

exit($exitCode);
