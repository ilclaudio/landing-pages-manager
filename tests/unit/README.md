# Unit Tests — Setup Guide

Unit tests in this plugin are designed to be the fastest feedback loop.
They cover pure PHP logic with no real WordPress bootstrap and no database.

If a programmer clones this repository and installs Composer dependencies,
the unit test suite should be runnable immediately.

---

## Goal

Use unit tests for:

- pure utility methods
- string and path normalization
- validation helpers
- lightweight business rules that do not require WordPress runtime state

Do not use this suite for hooks, database behavior, REST routes, or template
loading inside real WordPress. Those belong to integration tests.

---

## First-time setup

From the plugin root:

```bash
composer install
```

This installs PHPUnit and the other PHP development tools into `vendor/`.

On Windows PowerShell:

```powershell
composer install
```

---

## Run the suite

From the plugin root:

```bash
composer test:unit
```

On Windows PowerShell:

```powershell
composer test:unit
```

Current expected baseline:

```text
OK (3 tests, 3 assertions)
```

The exact number will grow as the plugin grows.

---

## Current bootstrap

Unit tests use:

- `phpunit.xml`
- `tests/bootstrap-unit.php`

That bootstrap intentionally stays minimal.
It currently loads only the utility classes required by the unit suite, without
booting WordPress.

---

## Current coverage

The first unit suite currently covers:

- `KKLPM_Path_Utils::normalize_route_path()`

Current test file:

- `tests/unit/KKLPMPathUtilsTest.php`

Current source under test:

- `includes/utils/class-kklpm-path-utils.php`

---

## How to add a new unit test

1. Put pure PHP logic in a small class or helper.
2. Require that class from `tests/bootstrap-unit.php` if needed.
3. Add a new PHPUnit file under `tests/unit/`.
4. Name the file with the `Test.php` suffix so PHPUnit discovers it.
5. Run `composer test:unit`.

Example naming:

```text
tests/unit/KKLPMSomethingTest.php
```

---

## Troubleshooting

**`phpunit` not found**

Run:

```bash
composer install
```

The Composer script already uses `vendor/bin/phpunit`, so no global PHPUnit
installation is required.

**No tests executed**

Check that the file name ends with `Test.php` and that the class extends
`PHPUnit\Framework\TestCase`.

**Fatal error in bootstrap**

Check `tests/bootstrap-unit.php` and verify that every required file actually
exists in the repository.

---

## Relationship with pre-commit

The repository githooks are intended to run unit tests before commit.
So keeping this suite fast and deterministic is important: it should stay small,
reliable, and runnable on every machine after a fresh clone plus `composer install`.
