# Permissions

KK Landing Pages Manager (KKLPM) does not define custom WordPress roles. Instead, it registers two custom capabilities and grants them to existing core roles. This keeps the Users screen unchanged and lets site administrators reassign access later with standard role-management tools.

## Capabilities

| Capability | Purpose | Default roles |
|---|---|---|
| `kklpm_manage_landing_pages` | Enable/disable the landing page mode on a page and configure it: "Show theme header/footer", "Load WordPress CSS/JS". | Administrator, Editor |
| `kklpm_manage_domain_router` | Access the Domain Router settings page and manage mappings (create, edit, enable/disable, delete). | Administrator |

Both capabilities are independent from each other. An Editor can manage landing pages but cannot access the Domain Router, unless an administrator explicitly grants `kklpm_manage_domain_router` to another role or user.

## Raw HTML/CSS/JS fields

The three raw content fields (HTML, CSS, JavaScript) in the Landing Page meta box are **not** gated by `kklpm_manage_landing_pages`. They remain gated by the native WordPress capability `unfiltered_html`, which by default only Administrators hold (on multisite, only Super Admins). This is intentional: these fields render unescaped markup and scripts, a materially higher-risk operation than toggling landing page settings, so it is kept on WordPress's own long-standing safeguard rather than the plugin's capability.

A user with `kklpm_manage_landing_pages` but without `unfiltered_html` can still enable the landing page and use the two boolean toggles; the raw fields are shown disabled and any submitted value for them is ignored on save.

## Where capabilities are enforced

- `KKLPM_Landing_Page_Module::register_meta_box()` — the "Landing Page" meta box is only registered for users with `kklpm_manage_landing_pages`.
- `KKLPM_Landing_Page_Module::can_save_meta_box()` — saving requires both `edit_post` on the specific page and `kklpm_manage_landing_pages`.
- `KKLPM_Domain_Router_Admin_Page::CAPABILITY` (`kklpm_manage_domain_router`) — used by the settings page (`render_page()`) and by all three `admin-post.php` handlers (save, delete, toggle mapping).
- The "Manage routes" links (Plugins list row, and inside the Landing Page meta box) are only rendered for users who hold `kklpm_manage_domain_router`.

## Granting and upgrades

Capabilities are granted through `KKLPM_Plugin::grant_default_capabilities()`, which adds each capability to its role only if not already present (no destructive writes, no removal of capabilities added manually by an administrator).

This method runs in two places:
- On plugin activation (`register_activation_hook`), for fresh installs.
- On every `admin_init`, as a defensive fallback. `register_activation_hook` does not fire when a plugin is already active and its files are simply replaced during an update, so the `admin_init` check ensures existing installs receive the capabilities without requiring a manual deactivate/reactivate cycle.

## Uninstall

`uninstall.php` removes both capabilities from every role that holds them when the plugin is deleted from the Plugins screen. It does not remove the Domain Router mapping table or any post meta; those are out of scope for this cleanup routine.

## Custom roles in tests

The automated test suite defines additional custom roles (for example `kklpm_limited_editor`) purely to isolate specific capability combinations in integration tests. These roles are created and removed within the test suite and are never registered by the plugin itself.
