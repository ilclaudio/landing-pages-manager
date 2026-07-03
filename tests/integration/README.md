# Integration Tests — Setup Guide

Integration tests in this plugin use `WP_UnitTestCase` and boot a real
WordPress test environment. They verify that the plugin works inside WordPress,
not just as isolated PHP code.

Unlike unit tests, integration tests need a one-time local environment setup.
After that setup is done, a programmer should be able to run them directly from
the repository with `composer test:integration`.

---

## Goal

Use integration tests for:

- plugin bootstrap inside WordPress
- hooks and filters
- custom tables or stored data
- REST endpoints
- template loading behavior
- WordPress capability, option, and post-meta interactions

---

## What you need once

1. Clone this repository.
2. Run `composer install` in the plugin root.
3. Have a local checkout of `wordpress-develop`.
4. Create a dedicated empty database for tests only.
5. Create `wp-tests-config.php` inside the WordPress test framework directory.

After those five things are in place, the integration suite should be runnable
directly from the plugin repository.

---

## Default WordPress test framework path

`tests/bootstrap-integration.php` reads the `WP_TESTS_DIR` environment variable.

If `WP_TESTS_DIR` is not set, it falls back to:

```text
C:/WordpressDEV/wordpress-develop/tests/phpunit
```

So you have two valid options:

1. Clone `wordpress-develop` to that exact path.
2. Use a different path and set `WP_TESTS_DIR` before running the suite.

---

## Step 1 — Install PHP dependencies

From the plugin root:

```bash
composer install
```

On Windows PowerShell:

```powershell
composer install
```

---

## Step 2 — Get wordpress-develop

Example:

```bash
git clone https://github.com/WordPress/wordpress-develop.git C:/WordpressDEV/wordpress-develop
```

If you use another folder, remember to set `WP_TESTS_DIR` when running tests.

---

## Step 3 — Create the test database

Use a disposable database dedicated to this plugin only, for example:

```text
kklpm_wordpress_test
```

Never point the WordPress test suite at:

- a production database
- your normal LocalWP site database
- any shared development database

Example SQL:

```sql
CREATE DATABASE IF NOT EXISTS kklpm_wordpress_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

---

## Step 4 — Create wp-tests-config.php

Create this file:

```text
C:/WordpressDEV/wordpress-develop/tests/phpunit/wp-tests-config.php
```

Example minimal configuration:

```php
<?php
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/src/' );

define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', 'kklpm_wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', '127.0.0.1:10028' );

define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'kklpm-test-auth-key' );
define( 'SECURE_AUTH_KEY', 'kklpm-test-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'kklpm-test-logged-in-key' );
define( 'NONCE_KEY', 'kklpm-test-nonce-key' );
define( 'AUTH_SALT', 'kklpm-test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'kklpm-test-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'kklpm-test-logged-in-salt' );
define( 'NONCE_SALT', 'kklpm-test-nonce-salt' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'KKLPM Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
```

Adjust DB host, port, username, and password for your machine.

---

## Step 5 — Optional WP_TESTS_DIR override

If your `wordpress-develop` checkout is not in the default folder, set
`WP_TESTS_DIR` before running the suite.

PowerShell:

```powershell
$env:WP_TESTS_DIR = "C:/your/path/wordpress-develop/tests/phpunit"
composer test:integration
```

Bash:

```bash
WP_TESTS_DIR="C:/your/path/wordpress-develop/tests/phpunit" composer test:integration
```

---

## Run the suite

From the plugin root:

```bash
composer test:integration
```

On Windows PowerShell:

```powershell
composer test:integration
```

The initial baseline is intentionally small.
The first smoke test should prove that:

- WordPress boots correctly
- the plugin main file loads
- `KKLPM_VERSION` is defined
- `KKLPM_Path_Utils` is available inside WordPress

Current first integration test:

- `tests/integration/KKLPMPluginBootstrapTest.php`

---

## Troubleshooting

**`WordPress test library not found`**

Check `WP_TESTS_DIR` or move `wordpress-develop` to:

```text
C:/WordpressDEV/wordpress-develop/tests/phpunit
```

**`Plugin bootstrap not found`**

Check that `landing-pages-manager.php` exists in the plugin root.

**Database connection or installation errors**

Verify the credentials in `wp-tests-config.php`.
Make sure the database exists and is disposable.

**No tests executed**

Check that integration test files end with `Test.php`.

---

## Diagnostic helper

There is also a manual DB connectivity helper:

- `tests/check-db-connection.php`

It is not part of PHPUnit. Use it only when you need to confirm that the test
database credentials are valid on a given machine.
