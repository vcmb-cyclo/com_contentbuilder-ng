# Testing Guide

## Test Levels

| Level | Current verification |
| --- | --- |
| PHP compatibility | Syntax and PHPUnit matrices on PHP 8.3, 8.4 and 8.5 |
| Unit | PHPUnit services, models, helpers, routing and security regressions |
| Coverage | PCOV report generated on PHP 8.4 and uploaded as Clover XML |
| Package | Required and forbidden ZIP entries validated automatically |
| Joomla integration | Fresh install on Joomla 6.1.2/PHP 8.4 with MySQL 8.4 |
| Update and migration | Package reinstallation plus historical table rename |
| API end to end | Real Joomla HTTP request and JSON response shape validation |

The unit bootstrap simulates Joomla classes for fast isolation. The Docker smoke test
therefore remains mandatory because it exercises the installer, SQL schema, extension
registration and HTTP application on an actual Joomla instance.

PHP 8.3 and PHP 8.4 are supported production runtimes. PHP 8.4 is the main
development and Joomla integration target. PHP 8.5 is exercised by syntax and
unit tests as an experimental compatibility target; it is not promoted to
production support until the pinned Joomla integration image is available and
the complete smoke test passes on it.

## Unit Tests

```bash
cd admin
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```

To measure coverage locally, enable PCOV or Xdebug and run:

```bash
vendor/bin/phpunit -c phpunit.xml.dist \
  --coverage-text \
  --coverage-clover=coverage.xml
```

CI enforces the existing coverage threshold on PHP 8.4. The PHP 8.3 and PHP 8.5
matrix entries run the same unit suite without generating duplicate reports.

## Package Validation

```bash
scripts/build-package.sh
scripts/validate-package.sh build/com_contentbuilderng-<version>.zip
```

The build installs Composer production dependencies in an isolated staging
directory. Unit tests, PHPUnit packages, caches and development binaries must not be
present in the ZIP.

## Joomla Integration

Docker is required:

```bash
scripts/joomla-install-smoke.sh build/com_contentbuilderng-<version>.zip
```

The smoke test:

1. starts clean Joomla 6.1.2/PHP 8.4 and MySQL 8.4 containers;
2. installs the generated package through the Joomla CLI;
3. verifies component tables and plugin registrations;
4. renames one NG table to its historical name;
5. reinstalls the package and verifies automatic table migration;
6. calls the component API through Joomla and validates the JSON envelope.

Containers and their temporary network are removed automatically.

Set `JOOMLA_IMAGE` only to repeat the same smoke test with another official
Joomla image. The default must stay aligned with the supported main runtime.

## Continuous Integration

`.github/workflows/build-package.yml` runs for pull requests, pushes to `main`
and manual dispatches. A release is created only by an authorized manual run on
`main`, and only after the PHP 8.3/8.4/8.5 matrices, coverage, package and Joomla
integration checks pass. PHP 8.3 and PHP 8.4 are the supported production
runtimes and must both be named in the release validation report; PHP 8.5 remains
an experimental compatibility target.
