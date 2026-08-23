<?php

declare(strict_types=1);

namespace App\Kernel;

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\RuntimeConfiguration;
use Contempt\Contracts\Runtime\Runtime;
use Contempt\Core\Time\SystemClock;
use Contempt\DevTools\Build\BuildPlan;
use Contempt\Kernel\Artifact\CompiledRuntimeLoader;
use Contempt\Kernel\Error\ErrorHandler;
use Contempt\Kernel\Error\ErrorHandlerConfiguration;
use Contempt\Kernel\Error\ErrorReporter;
use Contempt\Kernel\Error\NativeEmergencyOutput;
use Contempt\Kernel\Error\NativeErrorLogSink;

/** Single, fail-closed application boundary shared by HTTP and console. */
final readonly class ApplicationBootstrap
{
    private function __construct(public string $projectRoot) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        $root = realpath($projectRoot);

        if ($root === false || !is_dir($root) || is_link($projectRoot)) {
            throw new \InvalidArgumentException('The application project root must be a real non-symlink directory.');
        }

        return new self(rtrim(str_replace('\\', '/', $root), '/'));
    }

    public function errors(): ErrorHandler
    {
        return self::productionErrors();
    }

    public static function productionErrors(): ErrorHandler
    {
        return new ErrorHandler(
            ErrorHandlerConfiguration::production(),
            new NativeErrorLogSink(),
            new NativeEmergencyOutput(),
            new SystemClock(),
        );
    }

    public function runtime(ErrorReporter $errors): Runtime
    {
        $configuration = $this->runtimeConfiguration();

        return new CompiledRuntimeLoader(
            buildDirectory: $this->buildDirectory(),
            environment: $configuration->environment,
            composerLockPath: $this->composerLock(),
        )->load($this->trustedBuildFingerprint(), runtimeServices: [
            ConfigurationProvider::class => $configuration->provider,
            ErrorReporter::class => $errors,
        ]);
    }

    public function buildPlan(): BuildPlan
    {
        $plan = $this->loadConfiguration('config/build.php');

        if (!$plan instanceof BuildPlan) {
            throw new \LogicException('config/build.php must return a BuildPlan.');
        }

        return $plan;
    }

    public function buildDirectory(): string
    {
        return $this->projectRoot . '/var/contempt/build';
    }

    private function runtimeConfiguration(): RuntimeConfiguration
    {
        $configuration = $this->loadConfiguration('config/runtime.php');

        if (!$configuration instanceof RuntimeConfiguration) {
            throw new \LogicException('config/runtime.php must return RuntimeConfiguration.');
        }

        return $configuration;
    }

    private function composerLock(): string
    {
        $path = $this->projectRoot . '/composer.lock';
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false || is_link($path) || !is_readable($path) || $size > 134_217_728) {
            throw new \RuntimeException('Run composer install: a regular readable composer.lock no larger than 128 MiB is required.');
        }

        return $path;
    }

    private function trustedBuildFingerprint(): ?string
    {
        $fingerprint = $_ENV['CONTEMPT_BUILD_FINGERPRINT']
            ?? $_SERVER['CONTEMPT_BUILD_FINGERPRINT']
            ?? null;

        if ($fingerprint === null) {
            return null;
        }

        if (!\is_string($fingerprint)) {
            throw new \InvalidArgumentException('CONTEMPT_BUILD_FINGERPRINT must be a canonical SHA-256 string.');
        }

        try {
            \Contempt\Kernel\Artifact\BuildManifest::assertSha256($fingerprint);
        } catch (\InvalidArgumentException $failure) {
            throw new \InvalidArgumentException(
                'CONTEMPT_BUILD_FINGERPRINT must be a canonical SHA-256 string.',
                previous: $failure,
            );
        }

        return $fingerprint;
    }

    private function loadConfiguration(string $relativePath): mixed
    {
        $path = $this->projectRoot . '/' . $relativePath;

        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new \RuntimeException(\sprintf('Required application configuration %s is missing or unsafe.', $relativePath));
        }

        return require $path;
    }
}
