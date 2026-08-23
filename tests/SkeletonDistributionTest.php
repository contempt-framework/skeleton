<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SkeletonDistributionTest extends TestCase
{
    private const array REQUIRED_PROJECT_FILES = [
        '.dockerignore',
        '.editorconfig',
        '.env.dist',
        '.gitattributes',
        '.gitignore',
        '.php-cs-fixer.dist.php',
        'Dockerfile',
        'README.md',
        'bin/contempt',
        'compose.yaml',
        'config/bootstrap.php',
        'config/build.php',
        'config/runtime.php',
        'config/services.php',
        'docker/nginx/default.conf',
        'docker/php/php.ini',
        'docker/php/opcache.ini',
        'phpstan.neon.dist',
        'phpunit.xml.dist',
        'public/index.php',
        'tests/bootstrap.php',
        'var/.gitignore',
    ];

    public function testCreateProjectDistributionContainsACompleteOperationalBaseline(): void
    {
        foreach (self::REQUIRED_PROJECT_FILES as $relativePath) {
            self::assertFileExists(self::root() . '/' . $relativePath, $relativePath);
        }
    }

    public function testComposerManifestProvidesTheFullLocalQualityWorkflow(): void
    {
        $manifest = json_decode(self::contents('composer.json'), true, 32, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        self::assertSame('project', $manifest['type'] ?? null);
        $requirements = $manifest['require'] ?? null;

        if (!\is_array($requirements)) {
            self::fail('Skeleton Composer requirements are missing or malformed.');
        }

        self::assertSame(
            ['contempt/composer-plugin', 'contempt/config', 'contempt/devtools', 'php'],
            array_keys($requirements),
        );
        self::assertArrayNotHasKey('contempt/starter-api', $requirements);
        $autoload = $manifest['autoload'] ?? null;
        $developmentAutoload = $manifest['autoload-dev'] ?? null;
        self::assertIsArray($autoload);
        self::assertIsArray($developmentAutoload);
        $psr4 = $autoload['psr-4'] ?? null;
        $developmentPsr4 = $developmentAutoload['psr-4'] ?? null;
        self::assertIsArray($psr4);
        self::assertIsArray($developmentPsr4);
        self::assertSame('src/', $psr4['App\\'] ?? null);
        self::assertSame('tests/', $developmentPsr4['App\\Tests\\'] ?? null);

        $development = $manifest['require-dev'] ?? null;
        self::assertIsArray($development);
        self::assertArrayHasKey('friendsofphp/php-cs-fixer', $development);

        $scripts = $manifest['scripts'] ?? null;
        self::assertIsArray($scripts);

        foreach (['build', 'analyse', 'cs', 'cs:check', 'test', 'test:coverage', 'audit', 'qa'] as $script) {
            self::assertArrayHasKey($script, $scripts);
        }

        self::assertIsArray($scripts['qa']);
        self::assertContains('@build', $scripts['qa']);
        self::assertContains('@cs:check', $scripts['qa']);
        self::assertContains('@analyse', $scripts['qa']);
        self::assertContains('@test', $scripts['qa']);
        self::assertContains('@audit', $scripts['qa']);
    }

    public function testSkeletonContainsNoExampleBusinessDomain(): void
    {
        foreach ([
            'src/Api/Profile.php',
            'src/Api/ProfileController.php',
            'src/Console/StatusCommand.php',
        ] as $relativePath) {
            self::assertFileDoesNotExist(self::root() . '/' . $relativePath, $relativePath);
        }

        self::assertStringContainsString('framework-owned welcome page', self::contents('README.md'));
        self::assertStringContainsString("#[Get('/', name: 'home')]", self::contents('README.md'));
    }

    public function testHttpAndConsoleFrontControllersShareOneFailClosedBootstrap(): void
    {
        $http = self::contents('public/index.php');
        $console = self::contents('bin/contempt');
        $bootstrap = self::contents('config/bootstrap.php');

        self::assertStringContainsString("'/config/bootstrap.php'", $http);
        self::assertStringContainsString("'/config/bootstrap.php'", $console);
        self::assertStringContainsString("'/vendor/autoload.php'", $http);
        self::assertStringContainsString("'/vendor/autoload.php'", $console);
        self::assertStringNotContainsString("'/vendor/autoload.php'", $bootstrap);
        self::assertStringContainsString('ApplicationBootstrap::productionErrors()', $http);
        self::assertStringContainsString('ApplicationBootstrap::productionErrors()', $console);
        self::assertStringContainsString('CONTEMPT_BUILD_FINGERPRINT', self::contents('src/Kernel/ApplicationBootstrap.php'));
        self::assertLessThan(strpos($http, "'/config/bootstrap.php'"), strpos($http, '$errors->install()'));
        self::assertLessThan(strpos($console, "'/config/bootstrap.php'"), strpos($console, '$errors->install()'));
        self::assertStringContainsString('ApplicationBootstrap', $bootstrap);
    }

    public function testDistributedTestSuiteUsesTheApplicationsOwnComposerAutoloader(): void
    {
        $endToEndTest = self::contents('tests/SkeletonE2ETest.php');

        self::assertStringContainsString('$applicationRoot . \'/vendor/autoload.php\'', $endToEndTest);
        self::assertStringContainsString('is_file($autoload)', $endToEndTest);
    }

    public function testContainerDeploymentIsImmutableAndUsesThePublicFrontController(): void
    {
        $dockerfile = self::contents('Dockerfile');
        $compose = self::contents('compose.yaml');
        $nginx = self::contents('docker/nginx/default.conf');

        self::assertStringContainsString('--no-dev', $dockerfile);
        self::assertStringContainsString('--classmap-authoritative', $dockerfile);
        self::assertStringContainsString('bin/contempt build', $dockerfile);
        self::assertStringContainsString('USER www-data', $dockerfile);
        self::assertStringContainsString('HEALTHCHECK', $dockerfile);
        self::assertStringContainsString('/health/ready', $dockerfile);
        self::assertStringContainsString('read_only: true', $compose);
        self::assertStringContainsString('try_files $uri /index.php$is_args$args;', $nginx);
        self::assertStringContainsString('fastcgi_pass php:9000;', $nginx);
    }

    public function testCopiedSkeletonBuildsAndRunsWithoutMonorepoPathFallbacks(): void
    {
        $project = sys_get_temp_dir() . '/contempt-create-project-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($project, 0o700, true));

        try {
            foreach (['bin', 'config', 'public', 'src'] as $directory) {
                self::copyDirectory(self::root() . '/' . $directory, $project . '/' . $directory);
            }

            foreach (['composer.json', '.env.dist'] as $file) {
                self::assertTrue(copy(self::root() . '/' . $file, $project . '/' . $file));
            }

            self::assertTrue(copy(\dirname(self::root(), 2) . '/composer.lock', $project . '/composer.lock'));
            self::assertTrue(mkdir($project . '/vendor', 0o700));
            $workspaceAutoload = \dirname(self::root(), 2) . '/vendor/autoload.php';
            $autoload = \sprintf(
                "<?php\n\ndeclare(strict_types=1);\n\n\$loader = require %s;\n\$loader->setPsr4('App\\\\', dirname(__DIR__) . '/src');\n\nreturn \$loader;\n",
                var_export($workspaceAutoload, true),
            );
            self::assertSame(\strlen($autoload), file_put_contents($project . '/vendor/autoload.php', $autoload, LOCK_EX));

            [$buildExit, $buildOutput, $buildError] = self::process($project, ['build']);
            self::assertSame(0, $buildExit, $buildError);
            self::assertStringContainsString('Built production runtime sha256:', $buildOutput);
            self::assertFileExists($project . '/var/contempt/build/current');

            [$listExit, $listOutput, $listError] = self::process($project, ['list', '--raw']);
            self::assertSame(0, $listExit, $listError);
            self::assertStringContainsString('list', $listOutput);
            self::assertStringNotContainsString('app:status', $listOutput);
        } finally {
            self::removeDirectory($project);
        }
    }

    private static function root(): string
    {
        return \dirname(__DIR__);
    }

    private static function contents(string $relativePath): string
    {
        $contents = file_get_contents(self::root() . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }

    private static function copyDirectory(string $source, string $target): void
    {
        self::assertTrue(mkdir($target, 0o700, true));

        foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $entry) {
            self::assertInstanceOf(\SplFileInfo::class, $entry);
            $destination = $target . '/' . $entry->getFilename();

            if ($entry->isDir() && !$entry->isLink()) {
                self::copyDirectory($entry->getPathname(), $destination);
            } else {
                self::assertTrue($entry->isFile() && !$entry->isLink());
                self::assertTrue(copy($entry->getPathname(), $destination));
            }
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string, string}
     */
    private static function process(string $project, array $arguments): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $project . '/bin/contempt', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $project,
            ['APP_ENV' => 'prod', 'CONTEMPT_LOAD_DOTENV' => '0'],
        );

        self::assertIsResource($process);
        self::assertArrayHasKey(1, $pipes);
        self::assertArrayHasKey(2, $pipes);
        self::assertIsResource($pipes[1]);
        self::assertIsResource($pipes[2]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertIsString($output);
        self::assertIsString($error);

        return [$exit, $output, $error];
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            self::assertInstanceOf(\SplFileInfo::class, $entry);

            if ($entry->isDir() && !$entry->isLink()) {
                self::removeDirectory($entry->getPathname());
            } else {
                self::assertTrue(unlink($entry->getPathname()));
            }
        }

        self::assertTrue(rmdir($directory));
    }
}
