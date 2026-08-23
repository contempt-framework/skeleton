<?php

declare(strict_types=1);

namespace App\Tests;

use Contempt\Config\ConfigurationProvider;
use Contempt\Config\RuntimeConfiguration;
use Contempt\Console\ConsoleRuntime;
use Contempt\Core\Environment;
use Contempt\Core\Time\SystemClock;
use Contempt\DevTools\Build\BuildPlan;
use Contempt\Http\Fpm\EmissionTarget;
use Contempt\Http\Fpm\FpmRuntime;
use Contempt\Http\Fpm\RequestSource;
use Contempt\Http\Fpm\ResponseEmitter;
use Contempt\Http\Fpm\ServerRequestFactory;
use Contempt\Kernel\Artifact\CompiledRuntimeLoader;
use Contempt\Kernel\Error\EmergencyOutput;
use Contempt\Kernel\Error\ErrorHandler;
use Contempt\Kernel\Error\ErrorHandlerConfiguration;
use Contempt\Kernel\Error\ErrorReporter;
use Contempt\Kernel\Error\ErrorSink;
use Contempt\Kernel\Error\UnhandledError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require_once \dirname(__DIR__) . '/src/Api/HealthController.php';
require_once \dirname(__DIR__) . '/src/Api/Profile.php';
require_once \dirname(__DIR__) . '/src/Api/ProfileController.php';
require_once \dirname(__DIR__) . '/src/Console/StatusCommand.php';
require_once \dirname(__DIR__) . '/src/Configuration/ApplicationConfiguration.php';
require_once \dirname(__DIR__) . '/src/Service/BuildIdentity.php';

final class SkeletonE2ETest extends TestCase
{
    public function testSerializedDtoLivesInItsOwnPsr4File(): void
    {
        $path = \dirname(__DIR__) . '/src/Api/Profile.php';

        self::assertFileExists($path);
        self::assertTrue(class_exists(\App\Api\Profile::class, false));
        self::assertSame(\App\Api\Profile::class, new \ReflectionClass(\App\Api\Profile::class)->getName());
        self::assertSame($path, new \ReflectionClass(\App\Api\Profile::class)->getFileName());
    }

    public function testCompiledSkeletonServesHealthThroughTheRealFpmStack(): void
    {
        $sink = new SkeletonSink();
        $target = new SkeletonTarget();
        $errors = $this->errors($sink);
        $runtime = $this->runtime($errors);
        $driver = new FpmRuntime(
            $errors,
            new ServerRequestFactory(),
            new ResponseEmitter($target),
        );

        $exit = $driver->run($runtime, new SkeletonRequestSource([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/health',
            'HTTP_HOST' => 'api.example.test',
            'HTTPS' => 'on',
        ]));

        self::assertSame(0, $exit);
        self::assertSame([], $sink->errors);
        self::assertContains('status:200', $target->events);
        self::assertContains('header:content-type:application/json; charset=utf-8:replace', $target->events);
        self::assertContains('write:{"status":"up","application":"contempt-skeleton","framework":"1.0.0"}', $target->events);
    }

    public function testCompiledSkeletonRunsItsRealConsoleCommand(): void
    {
        $sink = new SkeletonSink();
        $output = new BufferedOutput();
        $errors = $this->errors($sink);

        $exit = new ConsoleRuntime($errors)->run(
            $this->runtime($errors),
            new ArrayInput(['command' => 'app:status']),
            $output,
        );

        self::assertSame(0, $exit);
        self::assertSame("Contempt skeleton is ready.\n", $output->fetch());
        self::assertSame([], $sink->errors);
    }

    public function testSkeletonOperatorCanExplainAndExportTheVerifiedCompiledGraph(): void
    {
        [$explainExit, $explainOutput, $explainError] = $this->command(['explain', 'service', 'App\\Api\\HealthController']);
        [$graphExit, $graphOutput, $graphError] = $this->command(['graph', '--format=mermaid']);

        self::assertSame(0, $explainExit, $explainError);
        self::assertStringContainsString('App\\Configuration\\ApplicationConfiguration', $explainOutput);
        self::assertSame(0, $graphExit, $graphError);
        self::assertStringStartsWith("flowchart LR\n", $graphOutput);
        self::assertStringNotContainsString('configuration values', strtolower($graphOutput));
    }

    public function testCompiledSkeletonStrictlyDeserializesAndSerializesABodyDto(): void
    {
        $sink = new SkeletonSink();
        $target = new SkeletonTarget();
        $errors = $this->errors($sink);
        $driver = new FpmRuntime($errors, new ServerRequestFactory(), new ResponseEmitter($target));

        $exit = $driver->run(
            $this->runtime($errors),
            new SkeletonRequestSource([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/profiles',
                'CONTENT_TYPE' => 'application/json',
            ], '{"identifier":7,"name":"Ada"}'),
        );

        self::assertSame(0, $exit);
        self::assertSame([], $sink->errors);
        self::assertContains('status:200', $target->events);
        self::assertContains('write:{"identifier":7,"name":"Ada"}', $target->events);

        $invalidTarget = new SkeletonTarget();
        $invalidExit = new FpmRuntime($errors, new ServerRequestFactory(), new ResponseEmitter($invalidTarget))->run(
            $this->runtime($errors),
            new SkeletonRequestSource([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/profiles',
                'CONTENT_TYPE' => 'application/json',
            ], '{"identifier":"7","name":"Ada","admin":true}'),
        );

        self::assertSame(0, $invalidExit);
        self::assertContains('status:400', $invalidTarget->events);
        self::assertContains('write:{"type":"about:blank","title":"Bad Request","status":400}', $invalidTarget->events);
        self::assertSame([], $sink->errors);

        $constraintTarget = new SkeletonTarget();
        $constraintExit = new FpmRuntime($errors, new ServerRequestFactory(), new ResponseEmitter($constraintTarget))->run(
            $this->runtime($errors),
            new SkeletonRequestSource([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/profiles',
                'CONTENT_TYPE' => 'application/json',
            ], '{"identifier":0,"name":" "}'),
        );

        self::assertSame(0, $constraintExit);
        self::assertContains('status:400', $constraintTarget->events);
        self::assertSame([], $sink->errors);
    }

    public function testPhpFailureDuringArtifactLoadingIsCaughtReportedAndSanitizedByFpmBoundary(): void
    {
        $sink = new SkeletonSink();
        $target = new SkeletonTarget();
        $errors = $this->errors($sink);
        $driver = new FpmRuntime(
            $errors,
            new ServerRequestFactory(),
            new ResponseEmitter($target),
        );

        $previousReporting = error_reporting(E_ALL);

        try {
            $exit = $driver->run(function () use ($errors): \Contempt\Contracts\Runtime\Runtime {
                trigger_error('artifact bootstrap secret=must-not-leak', E_USER_WARNING);

                return $this->runtime($errors);
            }, new SkeletonRequestSource([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/health',
            ]));
        } finally {
            error_reporting($previousReporting);
        }

        self::assertSame(1, $exit);
        self::assertCount(1, $sink->errors);
        self::assertInstanceOf(\ErrorException::class, $sink->errors[0]->throwable);
        self::assertContains('status:500', $target->events);
        self::assertNotContains('write:artifact bootstrap secret=must-not-leak', $target->events);
    }

    public function testCommittedBuildWasGeneratedFromCurrentSourcesAndDependencyLock(): void
    {
        $applicationRoot = \dirname(__DIR__);
        $workspaceRoot = \dirname($applicationRoot, 2);
        $buildRoot = $applicationRoot . '/var/contempt/build';
        $generation = trim((string) file_get_contents($buildRoot . '/current'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $generation);
        $contents = file_get_contents($buildRoot . '/generations/' . $generation . '/manifest.json');
        self::assertIsString($contents);
        $manifest = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        $plan = require $applicationRoot . '/config/build.php';
        self::assertInstanceOf(BuildPlan::class, $plan);

        $composerLockHash = $manifest['composerLockHash'] ?? null;
        $graphHash = $manifest['graphHash'] ?? null;
        $configSchemaHash = $manifest['configSchemaHash'] ?? null;

        if (!\is_string($composerLockHash) || !\is_string($graphHash) || !\is_string($configSchemaHash)) {
            self::fail('Generated build manifest hash field is missing.');
        }

        self::assertSame('sha256:' . hash_file('sha256', $workspaceRoot . '/composer.lock'), $composerLockHash);
        self::assertSame($plan->specification->graphHash, $graphHash);
        self::assertSame($plan->specification->configSchemaHash, $configSchemaHash);
        self::assertNotSame('sha256:' . str_repeat('0', 64), $composerLockHash);
    }

    private function loader(): CompiledRuntimeLoader
    {
        return new CompiledRuntimeLoader(
            \dirname(__DIR__) . '/var/contempt/build',
            Environment::Production,
            composerLockPath: \dirname(__DIR__, 3) . '/composer.lock',
        );
    }

    private function runtime(ErrorHandler $errors): \Contempt\Contracts\Runtime\Runtime
    {
        $configuration = require \dirname(__DIR__) . '/config/runtime.php';

        if (!$configuration instanceof RuntimeConfiguration) {
            self::fail('Skeleton runtime configuration is invalid.');
        }

        return $this->loader()->load(runtimeServices: [
            ConfigurationProvider::class => $configuration->provider,
            ErrorReporter::class => $errors,
        ]);
    }

    private function errors(SkeletonSink $sink): ErrorHandler
    {
        return new ErrorHandler(
            ErrorHandlerConfiguration::production(),
            $sink,
            new SkeletonEmergency(),
            new SystemClock(),
        );
    }

    /**
     * @param list<string> $arguments
     * @return array{int, string, string}
     */
    private function command(array $arguments): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, \dirname(__DIR__) . '/bin/contempt', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            \dirname(__DIR__),
        );

        if (!\is_resource($process) || !isset($pipes[1], $pipes[2]) || !\is_resource($pipes[1]) || !\is_resource($pipes[2])) {
            self::fail('Could not start the skeleton CLI subprocess.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if (!\is_string($output) || !\is_string($error)) {
            self::fail('Could not read skeleton CLI subprocess output.');
        }

        return [$exit, $output, $error];
    }
}

final readonly class SkeletonRequestSource implements RequestSource
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values, private string $requestBody = '') {}

    public function server(): array
    {
        return $this->values;
    }

    public function body(int $maximumBytes): string
    {
        return $this->requestBody;
    }
}

final class SkeletonTarget implements EmissionTarget
{
    /** @var list<string> */
    public array $events = [];

    public function headersSent(): bool
    {
        return false;
    }

    public function status(int $status): void
    {
        $this->events[] = 'status:' . $status;
    }

    public function header(string $name, string $value, bool $replace): void
    {
        $this->events[] = \sprintf('header:%s:%s:%s', $name, $value, $replace ? 'replace' : 'append');
    }

    public function write(string $bytes): void
    {
        $this->events[] = 'write:' . $bytes;
    }
}

final class SkeletonSink implements ErrorSink
{
    /** @var list<UnhandledError> */
    public array $errors = [];

    public function handle(UnhandledError $error): void
    {
        $this->errors[] = $error;
    }
}

final readonly class SkeletonEmergency implements EmergencyOutput
{
    public function write(string $message): void {}
}
