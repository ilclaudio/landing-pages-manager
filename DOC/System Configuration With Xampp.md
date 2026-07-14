# System Configuration With Xampp

This document explains how to use XAMPP on Windows to test all three Domain Router mapping types implemented by KK Landing Pages Manager:

- `subpath`
- `subdomain`
- `external`

It is written for the current local setup discussed during manual testing:

- XAMPP document root: `C:\xampp\htdocs`
- WordPress site folder: `C:\xampp\htdocs\lpmanager`
- Plugin folder can be the real folder or a junction/symlink pointing to the development copy

## Goal

Make Apache answer multiple local hostnames that all point to the same WordPress install, so the plugin can test host-based routing in realistic conditions.

## Recommended setup

Use a single, consistent base URL for every hostname involved in testing:

- primary local site URL: `http://lpmanager.test/`
- additional local hostname for `subdomain` test: `http://promo.lpmanager.test/`
- additional local hostname for `external` test: `http://www.lpmanager.net/`

All three point to the same document root, `C:\xampp\htdocs\lpmanager`, and WordPress `home`/`siteurl` must be `http://lpmanager.test` (Step 4).

Do **not** mix this with `http://localhost/lpmanager/`. WordPress writes a `RewriteBase` into `.htaccess` derived from `home`/`siteurl`, and that base path only resolves correctly for the one Apache `DocumentRoot` it was generated for:

- `home`/`siteurl` = `http://lpmanager.test` → `.htaccess` gets `RewriteBase /`, which only works when the vhost's `DocumentRoot` **is** `C:/xampp/htdocs/lpmanager` (i.e. `lpmanager.test`, `promo.lpmanager.test`, `www.lpmanager.net`).
- `home`/`siteurl` = `http://localhost/lpmanager` → `.htaccess` gets `RewriteBase /lpmanager/`, which only works when the vhost's `DocumentRoot` is the *parent* folder `C:/xampp/htdocs` (i.e. `localhost`).

Only one of the two can work at a time. This guide standardizes on `lpmanager.test`, so `http://localhost/lpmanager/...` is expected to stop working once Step 4 is applied — that's correct, not a bug.

## Step 1: Update the Windows hosts file

Edit `C:\Windows\System32\drivers\etc\hosts` and add:

```txt
127.0.0.1 lpmanager.test
127.0.0.1 promo.lpmanager.test
127.0.0.1 www.lpmanager.net
```

For IPv6 parity, also add:

```txt
::1 lpmanager.test
::1 promo.lpmanager.test
::1 www.lpmanager.net
```

## Step 2: Configure Apache VirtualHosts in XAMPP

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.

As soon as any `<VirtualHost *:80>` block exists in this file, Apache switches to name-based virtual hosting for port 80 and **stops using the default `DocumentRoot` from `httpd.conf`**. Any request whose `Host` header doesn't match a defined `ServerName`/`ServerAlias` (for example `localhost`, or any other project under `htdocs`) falls back to the *first* VirtualHost block in the file. Without an explicit default block, that first block silently becomes the catch-all for `localhost` too — breaking every other project under `htdocs`, not just this one.

Add a default block first, then the three testing hostnames:

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"

    <Directory "C:/xampp/htdocs">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName lpmanager.test
    DocumentRoot "C:/xampp/htdocs/lpmanager"

    <Directory "C:/xampp/htdocs/lpmanager">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName promo.lpmanager.test
    DocumentRoot "C:/xampp/htdocs/lpmanager"

    <Directory "C:/xampp/htdocs/lpmanager">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName www.lpmanager.net
    DocumentRoot "C:/xampp/htdocs/lpmanager"

    <Directory "C:/xampp/htdocs/lpmanager">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Verify `C:\xampp\apache\conf\httpd.conf` includes this file:

```apache
Include conf/extra/httpd-vhosts.conf
```

## Step 3: Restart Apache

Restart Apache from the XAMPP Control Panel after saving the config files.

## Step 4: Point WordPress to the host-based local URL

Set, via `wp-admin` or directly in the database:

- `home = http://lpmanager.test`
- `siteurl = http://lpmanager.test`

Then **resave permalinks**: go to Settings → Permalinks and click "Save Changes" (no need to change the structure). This step is mandatory — updating `home`/`siteurl` alone (including via a direct DB query) does not touch `.htaccess`. WordPress only rewrites `.htaccess` when its rewrite rules are flushed, and until that happens it keeps using the old `RewriteBase`, silently breaking any path-based request (see "Recommended setup" above for why).

Confirm `.htaccess` now starts with `RewriteBase /` (not `/lpmanager/`), then verify these all reach the same WordPress install:

- `http://lpmanager.test/`
- `http://promo.lpmanager.test/`
- `http://www.lpmanager.net/`

At this stage the plugin may still show the same homepage on each host — that's fine, the goal here is only to confirm Apache/WordPress routing before testing the plugin itself.

## Step 5: Create the landing page test content

Create or reuse a published WordPress page, for example with slug `landing-page`. Optionally enable landing page mode for that page if the test case requires the isolated landing template.

## Step 6: Test the three mapping types

Mapping `Value` fields never include a scheme — only the bare host or the bare path. The form rejects a scheme-prefixed value with an explicit error. The scheme belongs only in the browser address bar.

URLs to visit for this test:

1. Subpath → `http://lpmanager.test/promo-lpmanager`
2. Subdomain → `http://promo.lpmanager.test/`
3. External → `http://www.lpmanager.net/`

### 1. Subpath

Mapping: `Type` = `subpath`, `Value` = `/promo-lpmanager`, `Page` = `landing-page`, `Active` = enabled.

Visit `http://lpmanager.test/promo-lpmanager`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect.

### 2. Subdomain

Mapping: `Type` = `subdomain`, `Value` = `promo.lpmanager.test`, `Page` = `landing-page`, `Active` = enabled.

Visit `http://promo.lpmanager.test/`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect to `lpmanager.test`.

### 3. External

Mapping: `Type` = `external`, `Value` = `www.lpmanager.net`, `Page` = `landing-page`, `Active` = enabled.

Visit `http://www.lpmanager.net/`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect to the main local hostname.

## Additional checks

These complement the core three mapping tests and mirror the manual checklist in `DEV/TODO/09-TODO_TestManuali.md`.

### CRUD

In the Domain Router admin page, verify you can create, edit, enable/disable, delete, and mark a mapping as canonical.

### Canonical URL

If the same page is reachable through multiple mappings, mark one of them as canonical and visit one of the alternative URLs.

Expected:

- the page is still served on the requested host/path, with no redirect;
- the HTML contains a single `<link rel="canonical">`;
- that tag points to the mapping marked as canonical.

### Inactive mapping

Disable a working mapping and reload its URL. Expected: the mapping no longer hijacks the request; if no native content exists there, the result is a normal 404.

### Invalid target fallback

Point a mapping to a page, then trash that page. Expected: visiting the mapped URL returns a 404; the router must not serve unrelated content.

### Subpath collision

Create a `subpath` mapping matching an existing native WordPress path (e.g. value `/landing-page`). Expected: the real native WordPress content keeps winning; the router does not hijack the existing route.

## Troubleshooting

### `Site Not Found`

The hostname is reaching your machine but isn't mapped to a VirtualHost. Check: the `hosts` entry exists, the `ServerName` exists in `httpd-vhosts.conf`, and Apache was restarted after the change.

### `Internal Server Error` right after adding VirtualHost blocks

Symptom: any URL under `http://localhost/`, not just `/lpmanager/`, breaks as soon as VirtualHost blocks are added. Cause: the default `localhost` block from Step 2 is missing, so Apache falls back to the first VirtualHost in the file, whose `DocumentRoot` doesn't match. Fix: add the default `ServerName localhost` block as the *first* block, then restart Apache.

### A path-based URL 404s on one host but works on another

Symptom: `http://lpmanager.test/promo-lpmanager` (or any non-root URL) doesn't load, while `http://localhost/lpmanager/promo-lpmanager` does (or vice versa). Cause: `home`/`siteurl` and the resulting `.htaccess` `RewriteBase` only match one `DocumentRoot` depth at a time — see "Recommended setup" and Step 4. Fix: confirm `home`/`siteurl` are `http://lpmanager.test`, then resave Permalinks to regenerate `.htaccess`.

### Mapping saved but never matches

The `Value` was typed with a scheme prefix (`http://...`) — the form now blocks this at save time (Step 6). If an old mapping was created before this validation existed, edit it and remove the prefix.

### Host works but plugin mapping does not

The Apache/WordPress layer is fine; the issue is in the plugin configuration. Check the mapping's type, value, target page, and active flag.

## Suggested local verification order

1. Verify `lpmanager.test` works as the primary site URL (Step 4, including the Permalinks resave).
2. Verify `promo.lpmanager.test` and `www.lpmanager.net` both reach the same WordPress install.
3. Test `subpath`, then `subdomain`, then `external`.
4. Test inactive mapping, invalid target, and subpath collision.
