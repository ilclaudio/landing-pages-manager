# KK Landing Pages Manager

A standalone WordPress plugin that turns any page into a landing page with an isolated template and flexible domain routing — no dependency on third-party plugins.

## Description

When a page is enabled as a landing page, the plugin serves an isolated HTML document instead of the active theme's template. By default it does not call `wp_head()` / `wp_footer()`, so no theme or third-party CSS/JS loads unless you explicitly opt in. The page's HTML, CSS, and JavaScript are managed as free-form content stored on the page itself.

The **Domain Router** lets a landing page be reached through a subdomain, a subpath, or an entirely external domain, while keeping the original URL in the browser — no redirect is involved. The plugin only handles the WordPress-side routing; DNS and web server configuration remain the site administrator's responsibility.

When the same landing page is reachable through multiple hosts, one mapping can be marked as **canonical**. The plugin then emits a `<link rel="canonical">` tag for that page, while still serving the requested host without redirecting the visitor.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- No dependency on other plugins

## Installation

1. Upload the plugin folder to `wp-content/plugins/`, or install the packaged ZIP through the WordPress admin (**Plugins → Add New → Upload Plugin**).
2. Activate the plugin. Its database table for domain mappings and its default capabilities are set up automatically on activation.

## Usage

**Enable a landing page**
1. Edit a Page and open the **Landing Page** meta box.
2. Check **Enable Landing Page**.
3. Fill in the HTML, CSS, and JavaScript fields (raw, unescaped content; requires the `unfiltered_html` capability, available to Administrators by default).
4. Optionally enable **Show theme header/footer** and/or **Load WordPress CSS/JS** if the page needs the active theme's chrome or WordPress's own enqueued assets.

**Route a custom domain to the page**
1. Go to **Settings → Landing Domain Router**, or use the **Manage routes** link on the Plugins list or inside the Landing Page meta box.
2. Add a mapping: choose a type (subdomain, subpath, or external domain), a value, and the target page.
3. Optionally mark one mapping for that page as **canonical** if search engines should treat that host/path as the official URL for the content.
4. Configure DNS and your web server so requests for that host reach this WordPress installation — the plugin does not automate DNS or virtual host setup.

## Permissions

Access is controlled by two capabilities: `kklpm_manage_landing_pages` (Administrator, Editor) and `kklpm_manage_domain_router` (Administrator only). See [DOC/Permissions.md](DOC/Permissions.md) for the full model, including how raw HTML/CSS/JS access is separately gated by WordPress's `unfiltered_html` capability.

## Development

```bash
composer install

composer lint       # PHP_CodeSniffer (WordPress Coding Standards)
composer lint:fix    # Auto-fix what PHPCBF can safely fix

composer test:unit         # Plain PHPUnit, no WordPress bootstrap
composer test:integration  # WP_UnitTestCase against a real WordPress test install
composer test:all          # Both suites
```

Integration tests require a WordPress test library and a disposable test database; see `tests/integration/README.md` for setup instructions.

## License

GPL-2.0-or-later — the same license used by WordPress core. See [LICENSE](LICENSE) for the full text and [CHANGELOG.md](CHANGELOG.md) for release history.
