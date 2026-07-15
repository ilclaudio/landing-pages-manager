# Change Log

Any notable changes to this project will be documented in this file.

This file is based on [Keep a Changelog](http://keepachangelog.com/).
This project uses [Semantic Versioning](http://semver.org/).


TAGS: Added, Changed, Deprecated, Removed, Fixed, Security.

## DESIDERATA 2.0.0
- Newsletter subscription.
- Prestashop integration.
- Chatbot integration.

## DESIDERATA 1.0.0
- Manage multilanguage pages.
- Create and modify landing page layout using AI.
- Post section with infinite scrolling.
- Contact form.


## [Unreleased]

### Added
- Two custom capabilities, `kklpm_manage_landing_pages` (Administrator, Editor) and `kklpm_manage_domain_router` (Administrator only), replacing the previous `edit_post`/`manage_options` gating. Granted on activation and defensively on every `admin_init`; removed on uninstall (`uninstall.php`, new file).
- "Manage routes" links to the Domain Router settings page, on the Plugins list row and inside the Landing Page meta box (shown only to users with `kklpm_manage_domain_router`).
- `DOC/Permissions.md`, documenting the capability model.
- Canonical URL support for Domain Router mappings via a per-mapping `is_canonical` flag, with single-canonical enforcement per page and fallback to the native page permalink when no canonical mapping is selected.
- Language Adapter layer (Step 3): `KKLPM_Language_Adapter_Interface`, a safe-fallback Null adapter, and one concrete adapter each for WPML, Polylang, TranslatePress, Weglot, and MultilingualPress, all guard-based (`defined()`/`function_exists()`) with no hard dependency on any of them. `KKLPM_Language_Adapter_Resolver` selects the single active adapter per request (filterable priority via `kklpm_language_adapter_priority`), with the Null adapter as guaranteed fallback.
- The Domain Router now resolves the translated page for the active language automatically (`KKLPM_Domain_Router_Module::resolve_translated_page_id()`), falling back to the source page when no multilingual plugin is active or no translation exists — no behavior change for single-language sites.
- `?kklpm_lang=xx` query parameter: lets a visitor request a specific language explicitly for a mapped request (direct link, or a manually authored language switcher in the landing page's HTML), overriding the active adapter's own language detection. No cookie/persistence.
- `DOC/GestioneMultilingua.md`, documenting the whole multilingual mechanism, the adapter table, and the `kklpm_lang` override.

### Changed
- The Domain Router admin UI now lets administrators mark a mapping as canonical and shows that state in the mappings table.
- The landing page frontend now emits `<link rel="canonical">` for routed pages both in the isolated template and when `wp_head()` is enabled.

### Fixed
- Polylang's own canonical redirect (`pll_check_canonical_url`) no longer hijacks requests already routed by the Domain Router. Previously, a `subpath` mapping on the site's main domain could 301-redirect to a language-prefixed path (e.g. `/it/...`) that matched no mapping, resulting in a 404 instead of the translated page. `KKLPM_Language_Adapter_Polylang` now suppresses that redirect specifically for Domain-Router-matched requests, leaving it untouched everywhere else.

## [DEV-0.0.1] - 2026-07-05
First internal development version.
