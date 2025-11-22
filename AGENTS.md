# Repository Guidelines

## Project Structure & Module Organization
- PHP hooks and extension bootstrap live in `itemmanager.php` and `itemmanager.civix.php`; extension metadata is in `info.xml`.
- Business logic, forms, and pages follow the CiviCRM namespace under `CRM/Itemmanager` (e.g., `Page`, `Form`), with Smarty templates in `templates/CRM/Itemmanager`.
- Client assets are grouped by type: `js/` (UI helpers like `handlePayment.js`), `css/`, and `images/`.
- Configuration scaffolding and mixins reside in `settings/`, `mixin/`, `xml/` (schema/menu), and `sql/` (install/upgrade scripts). Localized strings live in `l10n/`.
- Tests belong in `tests/phpunit`, bootstrapped via `tests/phpunit/bootstrap.php`.

## Build, Test, and Development Commands
- Enable/install via `cv en itemmanager` after placing the extension in your CiviCRM `ext/` directory. Use `cv dis itemmanager` before destructive schema work.
- Run the suite with `phpunit -c phpunit.xml.dist`; requires `cv` on PATH and a bootstrappable CiviCRM site (bootstrap handles autoload and database connection).
- Package for distribution with `cv dl org.stadtlandbeides.itemmanager@<git-url>` to verify installability from a clean site.

## Coding Style & Naming Conventions
- PHP: 4-space indentation, PSR-12 style braces, and `CRM_Itemmanager_*` class prefixes; prefer dependency injection via CiviCRM services where available.
- Templates: keep logic minimal in `.tpl` files and move processing into `CRM/Itemmanager` PHP classes. Use `ts()` or `E::ts()` for strings.
- JavaScript: plain ES5/ES6; keep file-level scope isolated. Name files by feature (`renewPeriods.js`, etc.).
- Follow existing naming for menu/actions (`renew_item_periods`) and settings keys defined in `settings/`.

## Testing Guidelines
- Add PHPUnit cases under `tests/phpunit` mirroring source namespaces (e.g., `CRM_Itemmanager_Page_DashboardTest`).
- Prefer integration-style tests using CiviCRM bootstrapping; mock only when database state is not required.
- Keep tests idempotent: create and clean up entities within each test or use transactional helpers.

## Commit & Pull Request Guidelines
- Commit messages are short, imperative, and scoped (`Update API usage for CiviCRM 6.8`, `Declare properties to avoid PHP 8 dynamic deprecations`).
- Each PR should state purpose, key changes, and deployment notes; link CiviCRM issue IDs or internal tickets when applicable.
- Include reproducible steps for any UI change (screenshots optional but helpful for template/JS updates).
- Re-run `phpunit -c phpunit.xml.dist` before requesting review; note if tests depend on specific CiviCRM versions or Membership Extras.

## Security & Configuration Tips
- Keep credentials and site-specific config out of the repo; rely on CiviCRM settings and extension settings forms in `Administer » CiviMember`.
- When altering SQL or schema files, provide upgrade steps and test both install and upgrade flows via `cv en/dis`.
