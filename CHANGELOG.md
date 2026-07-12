# Change Log

Any notable changes to this project will be documented in this file.

This file is based on [Keep a Changelog](http://keepachangelog.com/).
This project uses [Semantic Versioning](http://semver.org/).


TAGS: Added, Changed, Deprecated, Removed, Fixed, Security.


## DESIDERATA 1.0.0



## [Unreleased]

### Added
- Two custom capabilities, `kklpm_manage_landing_pages` (Administrator, Editor) and `kklpm_manage_domain_router` (Administrator only), replacing the previous `edit_post`/`manage_options` gating. Granted on activation and defensively on every `admin_init`; removed on uninstall (`uninstall.php`, new file).
- "Manage routes" links to the Domain Router settings page, on the Plugins list row and inside the Landing Page meta box (shown only to users with `kklpm_manage_domain_router`).
- `DOC/Permissions.md`, documenting the capability model.

## [DEV-0.0.1] - 2026-07-05
First internal development version.
