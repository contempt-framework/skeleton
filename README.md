# Contempt application skeleton

Minimal, bare-bones Contempt project in the same role as `symfony/skeleton`.
It contains the application boundary, environment/configuration bootstrap,
deterministic compiler entry point and front controllers, but no example
business domain. Database, ORM, messaging and security remain explicit choices.

> This repository is a **read-only split** of the Contempt monorepo. Issues and
> pull requests belong in
> [contempt-framework/contempt](https://github.com/contempt-framework/contempt).

## Requirements

- PHP 8.5 with the extensions required by Composer
- Composer 2.9 or newer
- Docker with Compose v2 only when using the container workflow

## Create an application

```bash
composer create-project contempt/skeleton my-application
cd my-application
cp .env.dist .env
composer qa
```

`composer qa` validates the Composer manifest, compiles the production runtime,
checks formatting, runs PHPStan at level 10, executes the test suite and audits
dependencies. A failed build never publishes a partial runtime generation.

Start the local PHP development server:

```bash
export APP_ENV=dev
composer serve
```

`composer serve` first compiles a development generation, then starts the
server. Selecting the environment through the native process environment is
intentional: an untrusted `.env` file cannot silently enable debug behavior.

Open the application:

```bash
open http://127.0.0.1:8080/
```

In `dev` and `test`, Contempt compiles a framework-owned welcome page at `/`
when the application has no `GET /` route. It is not a catch-all and it is not
published by production builds. Add a normal application controller:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Contempt\Attribute\Controller;
use Contempt\Attribute\Get;
use Contempt\Http\Body;
use Contempt\Http\Headers;
use Contempt\Http\Response;

#[Controller]
final readonly class HomeController
{
    #[Get('/', name: 'home')]
    public function __invoke(): Response
    {
        return new Response(
            headers: new Headers(['content-type' => 'text/html; charset=utf-8']),
            body: Body::fromString('<h1>Hello</h1>'),
        );
    }
}
```

Run `composer build` again. The compiler then omits the welcome service and
route completely, so the application route owns `/` without a priority trick
or runtime branch.

The built-in server is a development convenience. Production runs through
PHP-FPM behind a web server.

## Project layout

```text
bin/contempt                application CLI and build entry point
config/bootstrap.php        shared HTTP/CLI bootstrap
config/build.php            compile-time application composition
config/runtime.php          environment and provider boundary
config/services.php         explicit service bindings and arguments
docker/                     hardened PHP and nginx configuration
public/index.php            only web-accessible PHP entry point
src/Api/                    operational HTTP probes
src/Configuration/          minimal typed application identity
src/Kernel/                 application bootstrap boundary
src/Service/                operational build identity
tests/                      integration and application tests
var/                        generated runtime and local tool state
```

Contempt does not require this internal `src/` layout. A larger application may
move to bounded contexts such as
`src/Orders/{Domain,Application,Infrastructure,Interface}` without changing the
framework.

## Configuration and environments

Application code receives typed configuration objects and must not read
`$_ENV`, `$_SERVER` or `getenv()` directly. The runtime boundary uses
`symfony/dotenv` without `putenv()`.

Precedence is deterministic:

```text
defaults < dotenv files < native process variables
```

Supported environments are `dev`, `test` and `prod`:

- development and test may load `.env` automatically;
- production never loads dotenv implicitly;
- `CONTEMPT_LOAD_DOTENV=1` is an explicit operator opt-in;
- `CONTEMPT_LOAD_DOTENV=0` disables dotenv inspection completely;
- native process variables are never overwritten by dotenv files.

`.env` files are local convenience files. Do not store secrets in Git or bake
them into container images. Supply production secrets through the process
environment or an installed secret-provider integration.

## Development commands

```bash
composer build          # compile and atomically activate a runtime generation
APP_ENV=dev composer serve # compile and start the local development server
composer test           # PHPUnit
composer test:coverage  # text and Clover coverage report
composer analyse        # PHPStan level 10 plus Contempt rules
composer cs:check       # verify formatting
composer cs             # apply formatting
composer audit          # security and abandoned-package audit
composer qa             # complete local/CI quality gate

bin/contempt explain service App\\Api\\HealthController
bin/contempt graph --format=mermaid
```

Generated artifacts live under immutable generations in
`var/contempt/build/generations/`. The small `current` pointer is replaced
atomically. Do not edit generated PHP.

## Tests

Tests boot the real compiled runtime. Test configuration disables dotenv file
loading so a developer's local `.env` cannot make CI nondeterministic.

```bash
composer test
```

Add fast unit tests next to application-level integration tests. Prefer the
fakes from `contempt/testing` for clocks, buses, transports and stores. Test
rejection paths, malformed input, boundaries and lifecycle cleanup before
duplicating happy-path cases.

## Containers

Build and run the production-equivalent PHP-FPM and nginx images:

```bash
docker compose up --build
curl --fail http://127.0.0.1:8080/health/ready
```

Operational probes are separate and remain opaque: `/health/startup` confirms
that the compiled runtime reached `Ready`, `/health/live` confirms that the
process can boot and serve work, and `/health/ready` is the endpoint used by
the container health check. A bootstrap, manifest, configuration or lifecycle
failure prevents all three from returning `up`.

The image:

- installs dependencies with `--no-dev --classmap-authoritative`;
- compiles the runtime during the image build with `APP_ENV=prod`;
- runs PHP-FPM as `www-data`;
- makes the application filesystem read-only;
- disables displayed PHP errors and timestamp-based OPcache validation;
- exposes only `public/index.php` through nginx.

The supplied Compose file is an operational baseline, not a requirement.
Contempt does not require Docker, Kubernetes or a specific deployment platform.

## Production deployment without Docker

Build artifacts in trusted CI, then deploy source, `vendor/`, `composer.lock`
and the generated `var/contempt/build` directory together:

```bash
composer install \
  --no-dev \
  --classmap-authoritative \
  --no-interaction \
  --no-progress

APP_ENV=prod CONTEMPT_LOAD_DOTENV=0 php bin/contempt build
composer check-platform-reqs --no-dev
```

For an immutable deployment, CI can also publish the fingerprint printed by
`bin/contempt build` and the runtime operator can set:

```bash
CONTEMPT_BUILD_FINGERPRINT=sha256:<64-lowercase-hex-digits>
```

When present, bootstrap rejects any other compiled generation before executing
its PHP runtime artifact. This catches a stale mount, partial rollout or
artifact substitution independently of the manifest's internal checks.

Configure the web-server document root as `public/`, keep the remaining project
tree outside web access, make deployed source and build artifacts read-only and
route PHP only to `public/index.php`. Forward application logs from stderr and
retain the `error_id` correlation value returned by the framework error
boundary.

## Adding infrastructure

The skeleton intentionally starts without persistence or distributed
infrastructure. Install only what the application needs:

```bash
composer require contempt/doctrine-orm
composer require contempt/rabbitmq
composer require contempt/redis
composer require contempt/security
```

Contempt recipes are declarative, checksum-owned and may not silently overwrite
modified application files.

## Troubleshooting

- **`Composer autoload is unavailable`** — run `composer install`.
- **missing or stale runtime generation** — run `composer build` after source,
  configuration or dependency changes.
- **composer-lock mismatch** — deploy `composer.lock` and the build generated
  from exactly that lock file as one release.
- **dotenv is ignored in production** — provide native environment variables,
  or explicitly opt in with `CONTEMPT_LOAD_DOTENV=1` when an operator has
  intentionally selected that model.
- **generic HTML/JSON 500 response** — inspect structured logs using the
  response correlation identifier; exception messages and traces are never
  exposed to clients in production.

## License

MIT. See [LICENSE](LICENSE).
