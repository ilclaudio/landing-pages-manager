# System Configuration With Apache On Linux

This document explains how to configure a production Linux server (Apache + MySQL, Debian/Ubuntu) to serve all three Domain Router mapping types implemented by KK Landing Pages Manager:

- `subpath`
- `subdomain`
- `external`

It assumes:

- Apache, MySQL/MariaDB, PHP, and WordPress are already installed and the primary site is already reachable over its main domain.
- You have root/sudo access to the server and control over DNS for every domain involved.
- Debian/Ubuntu paths and tooling (`apache2`, `a2ensite`, `/etc/apache2/sites-available`, `ufw`). On RHEL/CentOS/AlmaLinux, adapt to `httpd`, `/etc/httpd/conf.d/*.conf` (no `a2ensite` equivalent — just drop a `.conf` file in place), and `firewalld`.

Examples in this document reuse the scenario already defined in `DEV/AGENTS/PROJECT.md` ("Esempio d'uso"): primary site `www.mysite.org`, subdomain mapping `lpmanager.mysite.org`, subpath mapping `www.mysite.org/promo-lpmanager`, external domain mapping `www.lpmanager.net`. Replace with your real domains.

## Goal

Make Apache answer multiple public hostnames that all point to the same WordPress install, so the plugin can route each one to the correct landing page — without any visible redirect.

## Recommended setup

All three mapping types must resolve to the **same Apache `DocumentRoot`** — the WordPress install directory (e.g. `/var/www/mysite`). This isn't just tidiness: WordPress writes a `RewriteBase` into `.htaccess` derived from its `home`/`siteurl` option, and that base path only resolves correctly when every vhost serving the install has a `DocumentRoot` at the same depth. If a mapped hostname's vhost points at a different directory (or a parent/child of the real install path), path-based requests (`subpath` mappings, and any WordPress permalink in general) will 404 or misroute even though the root URL of that host appears to work. This is the same mechanism documented in `DOC/System Configuration With Xampp.md` for local testing — it applies identically here, just with real DNS instead of `/etc/hosts`.

Practical consequence:

- `subpath` (`/promo-lpmanager` on `www.mysite.org`) needs **no new DNS record and no new vhost** — it's served by the primary site's existing vhost.
- `subdomain` (`lpmanager.mysite.org`) and `external` (`www.lpmanager.net`) each need a DNS record pointing at this server, and an Apache vhost whose `DocumentRoot` is identical to the primary site's.

## Step 1: DNS records

- **Subdomain** (`lpmanager.mysite.org`): add an `A` record (or `CNAME` to the primary domain) for `lpmanager` pointing to this server's public IP.
- **External domain** (`www.lpmanager.net`): add an `A` record at that domain's own DNS provider, pointing to this server's public IP.
- **Subpath**: no DNS change — it's already served by `www.mysite.org`.

DNS propagation can take from minutes to hours depending on TTL. Verify with `dig +short lpmanager.mysite.org` / `dig +short www.lpmanager.net` before moving on.

## Step 2: Configure Apache VirtualHosts

Verify the primary site already has a working vhost (it should, since WordPress is already installed and reachable) — note its `DocumentRoot`, you'll reuse the exact same path.

Create `/etc/apache2/sites-available/lpmanager.mysite.org.conf`:

```apache
<VirtualHost *:80>
    ServerName lpmanager.mysite.org
    DocumentRoot /var/www/mysite

    <Directory /var/www/mysite>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Create `/etc/apache2/sites-available/www.lpmanager.net.conf` the same way, with `ServerName www.lpmanager.net` and the same `DocumentRoot`.

Enable both and reload Apache:

```bash
sudo a2ensite lpmanager.mysite.org.conf
sudo a2ensite www.lpmanager.net.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

If Apache serves the wrong site (or its default page) for one of the new hostnames, the vhost order/`ServerName` matching is off — see Troubleshooting.

## Step 3: Firewall

Confirm ports 80 and 443 are open — Certbot's HTTP-01 validation in the next step needs both reachable from the internet:

```bash
sudo ufw allow "Apache Full"
sudo ufw status
```

## Step 4: SSL certificates

Each hostname needs its own valid certificate — `lpmanager.mysite.org` and `www.lpmanager.net` are distinct names from the primary domain's certificate and won't be covered by it. Using [Certbot](https://certbot.eff.org/):

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d lpmanager.mysite.org
sudo certbot --apache -d www.lpmanager.net
```

Certbot edits the vhost files to add the `:443` block and redirect setup, and registers itself for automatic renewal (`systemctl status certbot.timer`). If HTTP-01 validation fails, double-check the firewall rules from Step 3.

The `subpath` mapping needs no new certificate; it's covered by the primary domain's existing one.

## Step 5: Confirm WordPress `home`/`siteurl` and permalinks

`home`/`siteurl` should already be set to the primary domain (`https://www.mysite.org`) — do **not** change them to one of the mapped hostnames. Domain Router mappings are host-based routing on top of a single WordPress identity, not multiple independent WordPress "sites".

If permalinks were ever changed after the vhosts above were added, resave them (Settings → Permalinks → "Save Changes") so `.htaccess` reflects the current setup — this regenerates `RewriteBase` from the current `home`/`siteurl` (see "Recommended setup").

Before testing the plugin, verify all three hostnames reach the same WordPress install:

- `https://www.mysite.org/`
- `https://lpmanager.mysite.org/`
- `https://www.lpmanager.net/`

At this stage the plugin may still show the same homepage on each host — that's expected, the goal here is only to confirm Apache/DNS/WordPress routing before testing the plugin itself.

## Step 6: Create the landing page test content

Create or reuse a published WordPress page, for example with slug `landing-page`. Optionally enable landing page mode for that page if the test case requires the isolated landing template.

## Step 7: Test the three mapping types

Mapping `Value` fields never include a scheme — only the bare host or the bare path. The form rejects a scheme-prefixed value with an explicit error. The scheme belongs only in the browser address bar.

URLs to visit for this test:

1. Subpath → `https://www.mysite.org/promo-lpmanager`
2. Subdomain → `https://lpmanager.mysite.org/`
3. External → `https://www.lpmanager.net/`

### 1. Subpath

Mapping: `Type` = `subpath`, `Value` = `/promo-lpmanager`, `Page` = `landing-page`, `Active` = enabled.

Visit `https://www.mysite.org/promo-lpmanager`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect.

### 2. Subdomain

Mapping: `Type` = `subdomain`, `Value` = `lpmanager.mysite.org`, `Page` = `landing-page`, `Active` = enabled.

Visit `https://lpmanager.mysite.org/`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect to `www.mysite.org`.

### 3. External

Mapping: `Type` = `external`, `Value` = `www.lpmanager.net`, `Page` = `landing-page`, `Active` = enabled.

Visit `https://www.lpmanager.net/`.

Expected: the `landing-page` page is served, the URL stays as-is, no redirect to the main domain.

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

Create a `subpath` mapping matching an existing native WordPress path. Expected: the real native WordPress content keeps winning; the router does not hijack the existing route.

## Production-specific notes

- **Point DNS only when ready.** Once a DNS record is live, real visitors and crawlers can reach the hostname immediately, before its vhost/SSL/mapping are fully configured. Complete Steps 1–5 for a hostname before publishing its DNS record where third parties will find it (or accept that it may briefly serve the wrong content / a certificate warning).
- **File ownership.** New vhosts don't create new files, but if you later add any plugin-managed assets under the install directory, keep ownership consistent with the rest of the WordPress install (typically `www-data:www-data` on Debian/Ubuntu).
- **Certificate renewal.** Confirm `sudo certbot renew --dry-run` succeeds after setup; Certbot's systemd timer handles renewal automatically from then on.

## Troubleshooting

### Hostname unreachable / connection times out

DNS hasn't propagated yet, or the record is missing/wrong. Check with `dig +short <hostname>` and compare against the server's actual public IP.

### Apache serves the wrong site (or its default page) for a new hostname

The vhost's `ServerName` doesn't match, or another vhost with a matching/broader `ServerName`/`ServerAlias` is evaluated first. Check `apache2ctl -S` to see the vhost matching order, and confirm the new `.conf` file was enabled (`a2ensite`) and Apache was reloaded.

### A path-based URL 404s on one host but works on another

Symptom: `https://www.mysite.org/promo-lpmanager` doesn't load, while the site root does. Cause: `home`/`siteurl` and the resulting `.htaccess` `RewriteBase` don't match this vhost's `DocumentRoot` depth — see "Recommended setup". Fix: confirm every mapped hostname's vhost uses the exact same `DocumentRoot` as the primary site, then resave Permalinks to regenerate `.htaccess`.

### Mapping saved but never matches

The `Value` was typed with a scheme prefix (`https://...`) — the form blocks this at save time (Step 7). If an old mapping was created before this validation existed, edit it and remove the prefix.

### Certificate warning in the browser

The certificate doesn't cover the hostname being visited (wrong `-d` in the `certbot` command), or it hasn't been issued yet. Run `sudo certbot certificates` to list what's actually issued and for which names.

### Host works but plugin mapping does not

The Apache/DNS/WordPress layer is fine; the issue is in the plugin configuration. Check the mapping's type, value, target page, and active flag.

## Suggested verification order

1. Verify DNS resolves for every new hostname.
2. Verify each hostname reaches the same WordPress install over HTTPS with a valid certificate (Steps 2–5).
3. Test `subpath`, then `subdomain`, then `external` (Step 7).
4. Test inactive mapping, invalid target, and subpath collision.
