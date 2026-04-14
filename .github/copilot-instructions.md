# Smartly Import Export — Project Instructions

## Overview

WordPress plugin (slug: `smartly-import-export`) for importing, exporting, and managing posts and custom post types via CSV, JSON, and XML. Multi-file PHP architecture with a jQuery admin UI. Hosted on GitHub at `ahmed-essawy/smartly-import-export`, targeting WordPress.org distribution.

- **Version**: 1.4.0
- **License**: GPL-2.0-or-later
- **Requires**: WordPress 5.8+, PHP 7.4+
- **Tested up to**: WordPress 6.9
- **Author**: Ahmed Essawy (`ahm.elessawy` on WP.org)

## Repository Structure

```
smartly-import-export/              ← Git root (NOT the plugin folder)
├── .editorconfig                   ← Tabs for PHP/JS/CSS, spaces for YAML/MD
├── .gitignore                      ← Ignores vendor/, node_modules/, *.zip, IDE files
├── .github/
│   ├── copilot-instructions.md     ← THIS FILE — project-wide AI instructions
│   ├── FUNDING.yml                 ← GitHub Sponsors
│   ├── PULL_REQUEST_TEMPLATE.md    ← PR checklist
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.yml          ← Structured bug report form
│   │   ├── feature_request.yml     ← Feature request form
│   │   └── config.yml              ← Disables blank issues
│   └── workflows/
│       ├── php-lint.yml            ← PHP syntax check across 8.0–8.3
│       ├── wpcs.yml                ← PHPCS with WordPress Coding Standards
│       ├── plugin-check.yml        ← Combined lint + WPCS (overlaps above two)
│       ├── wp-plugin-check.yml     ← Official WordPress Plugin Check action
│       ├── wp-deploy.yml           ← Deploy to WordPress.org SVN on release
│       └── release.yml             ← Build & attach ZIP to GitHub release
├── composer.json                   ← WPCS + PHPCompatibility dev dependencies
├── phpcs.xml.dist                  ← PHPCS config (text domain, prefixes, compat)
├── CHANGELOG.md                    ← Keep a Changelog format
├── CONTRIBUTING.md                 ← Dev setup and contribution guide
├── CODE_OF_CONDUCT.md              ← Contributor Covenant v2.1
├── README.md                       ← GitHub-facing readme
├── SECURITY.md                     ← Responsible disclosure policy
└── smartly-import-export/          ← PLUGIN FOLDER (what ships to WP.org)
    ├── smartly-import-export.php   ← Main plugin file (thin loader ~97 lines)
    ├── readme.txt                  ← WordPress.org readme (Stable tag must match Version)
    ├── uninstall.php               ← Cleans up transients on uninstall
    ├── index.php                   ← "Silence is golden"
    ├── includes/
    │   ├── index.php               ← "Silence is golden"
    │   ├── plugin-info.php         ← Plugin row meta + thickbox info (2 functions)
    │   ├── admin-page.php          ← Asset enqueueing + wizard HTML (2 functions)
    │   ├── ajax-core.php           ← Post types, fields, file parsing (12 functions)
    │   ├── ajax-export.php         ← Export + XLSX generation (4 functions)
    │   ├── ajax-import.php         ← Import batch + helpers (9 functions)
    │   ├── history.php             ← History, profiles, validation, rollback (9 functions)
    │   ├── scheduled.php           ← Scheduled imports/exports via WP-Cron (10 functions)
    │   └── meta-box.php            ← Single post export + dashboard widget (5 functions)
    ├── assets/
    │   ├── app.js                  ← jQuery admin UI (~1,825 lines, IIFE)
    │   ├── style.css               ← Core admin styles (~991 lines)
    │   ├── index.php               ← "Silence is golden"
    │   └── css/
    │       ├── index.php           ← "Silence is golden"
    │       ├── responsive.css      ← Tablet + mobile breakpoints (~516 lines)
    │       └── features.css        ← v1.1.0+ feature styles (~545 lines)
    └── languages/
        └── index.php               ← "Silence is golden"
```

**Key distinction**: The Git root is NOT the plugin directory. The deployable plugin lives inside `smartly-import-export/` subfolder. Workflows reference this as `PLUGIN_SLUG` or `PLUGIN_DIR`.

## Architecture

### PHP — Multi-File with Thin Loader

The main plugin file (`smartly-import-export.php`, ~97 lines) defines constants, registers the admin menu, and loads 8 include files from `includes/`. All functions are prefixed `smie_`.

**Main file** (`smartly-import-export.php`):
- Plugin header + 7 constants (`SMIE_VERSION`, `SMIE_PLUGIN_DIR`, `SMIE_PLUGIN_URL`, `SMIE_HISTORY_OPTION`, `SMIE_PROFILES_OPTION`, `SMIE_SCHEDULES_OPTION`, `SMIE_EXPORT_SCHEDULES_OPTION`)
- Admin menu registration (`smie_register_admin_page`) — Tools submenu
- WordPress Importer registration (`smie_register_wp_importer`) — appears on Tools → Import
- 8 `require_once` calls to includes

**Include files** (all start with `ABSPATH` check):

| File | Purpose | Key Functions |
|------|---------|---------------|
| `plugin-info.php` | Plugin row meta + thickbox details | `smie_plugin_row_meta()`, `smie_plugins_api_info()` |
| `admin-page.php` | Asset enqueueing + 6-step wizard HTML | `smie_enqueue_admin_assets()`, `smie_render_admin_page()` |
| `ajax-core.php` | Post types, fields, CSV/JSON/XML parsing | `smie_get_post_type_fields()`, `smie_read_csv_file()`, `smie_read_json_file()`, `smie_read_xml_file()`, `smie_convert_google_sheets_url()` |
| `ajax-export.php` | Export handler + XLSX generation | `smie_ajax_export()`, `smie_generate_xlsx()`, `smie_filter_export_id_range()` |
| `ajax-import.php` | Import batch + row processing helpers | `smie_ajax_import_batch()`, `smie_sanitize_mapping()`, `smie_import_single_row()`, `smie_apply_transform()`, `smie_check_duplicate()` |
| `history.php` | Import history, profiles, validation, rollback | `smie_record_import_history()`, `smie_get_mapping_profiles()`, `smie_ajax_validate_csv()`, `smie_ajax_rollback()` |
| `scheduled.php` | WP-Cron scheduled imports/exports | `smie_run_scheduled_import()`, `smie_run_scheduled_export()`, `smie_send_schedule_email()`, `smie_cleanup_old_exports()` |
| `meta-box.php` | Single post export meta box + dashboard widget | `smie_register_meta_box()`, `smie_ajax_export_single_post()`, `smie_render_dashboard_widget()` |

### JavaScript (`assets/app.js`)

jQuery IIFE, no build step, no transpilation. Uses `var` throughout for max compat.

**State variables**: `csvHeaders`, `csvToken`, `csvRowCount`, `extraFieldCount`, `postTypeData`

**Flow**: Load post types → select type → choose action (import/export/template) → upload CSV → auto-match columns → run batch import → show results

**Key patterns:**
- All AJAX via `$.post()` to `smieImporter.ajax_url` with `smieImporter.nonce`
- `buildMappingRow()` creates mapping table rows with auto-match logic
- `processBatch()` recurses until server returns `done: true`
- `esc()` function creates text node for XSS-safe HTML insertion
- `downloadBase64()` converts base64 CSV to Blob for download

### CSS (`assets/style.css` + `assets/css/`)

No preprocessor, no build. BEM-ish naming with `smie-` prefix. Split into 3 files:

- **`style.css`** (~991 lines) — CSS custom properties (light/dark themes), all base component styles, keyboard focus styles
- **`css/responsive.css`** (~516 lines) — Tablet (`@media 782px`) and mobile (`@media 480px`) breakpoints
- **`css/features.css`** (~545 lines) — v1.1.0+ feature styles (column mapping, export options, delimiter badges, transforms, profiles, validation, dry run, history, schedules, mapping preview, conditional filters)

All three are enqueued with `smie-admin` dependency chain. Uses WordPress admin palette and Dashicons.

## Security Model

Every AJAX handler follows this pattern — never skip any step:
1. `check_ajax_referer( 'smie_nonce', 'nonce' )`
2. `current_user_can( 'manage_options' )` check
3. Sanitize all input (`sanitize_key`, `sanitize_text_field`, `absint`, `esc_url_raw`, `wp_unslash`)
4. Escape all output (`esc_html`, `esc_attr`, `esc_html__`)

**CSV data** is stored in transients with random tokens (20-char `wp_generate_password`), auto-expiring after 1 hour. Transients are cleaned up after import completes and on plugin uninstall.

**No external calls** except user-initiated: fetching CSV from user-provided URL, downloading featured images from user-provided URLs.

## Developer Hooks

Filters:
- `smie_post_types` — modify the post type list shown in dropdown
- `smie_post_type_fields` — modify importable fields for a post type
- `smie_import_batch_size` — change batch size (default: 50)
- `smie_csv_parsed` — filter parsed CSV data before transient storage (v1.2.0)
- `smie_export_columns` — filter export column list (v1.2.0)
- `smie_export_row` — filter each export row before writing (v1.2.0)
- `smie_import_row_data` — filter post/meta/tax data before insert/update (v1.2.0)
- `smie_import_row_filter` — custom row filter logic during import (v1.2.0)

Actions:
- `smie_after_import_row` — fires after each row import with `$post_id`, `$row`, `$row_num`, `$is_update`
- `smie_before_import_row` — fires before each row import (v1.2.0)
- `smie_export_completed` — fires after export finishes (v1.2.0)
- `smie_import_completed` — fires after batch import finishes all rows (v1.2.0)

## v1.1.0 Features

1. **Scheduled Imports** — WP-Cron based recurring imports from URL (hourly/twicedaily/daily/weekly)
2. **Import History** — Records last 50 imports with post IDs, supports rollback (trash)
3. **Duplicate Detection** — Skip imports when matching post_title, post_name, or custom meta key
4. **Field Transforms** — Per-field transforms during import (uppercase, lowercase, titlecase, trim, strip_tags, slug, date formats)
5. **Status Filter Export** — Filter exported posts by status (publish, draft, pending, private, future)
6. **Mapping Profiles** — Save/load/delete column mapping configurations per post type
7. **CSV Validation** — Pre-import validation checking dates, statuses, author IDs, thumbnail URLs
8. **Multi-file Upload** — Drag-and-drop multiple CSV files, queued for sequential processing
9. **Selective Column Export** — Choose which fields to include in export
10. **Delimiter Auto-detection** — Automatically detects comma, semicolon, tab, or pipe delimiters
11. **Dry Run Mode** — Preview import results without making any database changes
12. **Download Import Log** — Export per-row import results as CSV
13. **Import Rollback** — Move all posts from a specific import to trash
14. **Sticky Post Type** — Remembers last selected post type via localStorage
15. **Export Row Count** — Shows post count in the export button

### New Constants (v1.1.0)
- `SMIE_HISTORY_OPTION` — wp_options key for import history
- `SMIE_PROFILES_OPTION` — wp_options key for mapping profiles
- `SMIE_SCHEDULES_OPTION` — wp_options key for scheduled imports

### New AJAX Handlers (v1.1.0)
- `smie_save_profile` — Save a mapping profile
- `smie_delete_profile` — Delete a mapping profile
- `smie_validate_csv` — Validate CSV data before import
- `smie_rollback` — Rollback an import by trashing posts
- `smie_get_history` — Fetch import history
- `smie_add_schedule` — Create a scheduled import
- `smie_delete_schedule` — Remove a scheduled import

### New Helper Functions (v1.1.0)
- `smie_apply_transform()` — Apply named transform to a field value
- `smie_check_duplicate()` — Check for duplicate posts by field
- `smie_record_import_history()` — Save import to history
- `smie_get_import_history()` — Retrieve import history
- `smie_get_mapping_profiles()` — Retrieve saved profiles
- `smie_get_scheduled_imports()` — Retrieve scheduled imports
- `smie_run_scheduled_import()` — WP-Cron callback for scheduled imports
- `smie_add_cron_interval()` — Registers weekly cron schedule

## v1.2.0 Features

1. **ACF Support** — Auto-detects Advanced Custom Fields groups per post type, shows friendly field labels, uses `update_field()` during import
2. **SEO Meta Support** — Auto-detects Yoast SEO (`WPSEO_VERSION`) and Rank Math (`RANK_MATH_VERSION`/`RankMath` class), adds SEO Title, Description, and Focus Keyword fields
3. **Google Sheets Import** — `smie_convert_google_sheets_url()` auto-converts `/d/{ID}/edit` and `/d/e/{PUBID}/pub` URLs to CSV export links, works in both manual URL fetch and scheduled imports
4. **Conditional Row Filtering** — Filter builder UI in mapping step with 8 operators (equals, not_equals, contains, not_contains, gt, lt, empty, not_empty), AND logic, `smie_import_row_filter` hook
5. **Scheduled Exports** — WP-Cron recurring exports saved to `wp-content/uploads/smie-exports/` with `.htaccess` protection, auto-cleanup after 7 days, optional email attachment
6. **Email Notifications** — Scheduled imports send email reports on success or failure via `smie_send_schedule_email()`, exports attach the CSV file
7. **Single Post Export** — Meta box on all post type edit screens with "Download CSV" button, uses `smie_ajax_export_single_post()` AJAX handler
8. **Enhanced Developer Hooks** — 8 new hooks: `smie_csv_parsed`, `smie_export_columns`, `smie_export_row`, `smie_export_completed`, `smie_before_import_row`, `smie_import_row_data`, `smie_import_completed`, `smie_import_row_filter`

### New Constants (v1.2.0)
- `SMIE_EXPORT_SCHEDULES_OPTION` — wp_options key for scheduled exports

### New AJAX Handlers (v1.2.0)
- `smie_add_export_schedule` — Create a scheduled export
- `smie_delete_export_schedule` — Delete a scheduled export
- `smie_export_single_post` — Export a single post as CSV from edit screen

### New Helper Functions (v1.2.0)
- `smie_convert_google_sheets_url()` — Convert Google Sheets URLs to CSV export URLs
- `smie_row_matches_filters()` — Evaluate conditional filter rules against a CSV row
- `smie_send_schedule_email()` — Send email notification for a scheduled import
- `smie_get_export_schedules()` — Retrieve scheduled exports list
- `smie_run_scheduled_export()` — WP-Cron callback for scheduled exports
- `smie_cleanup_old_exports()` — Remove export CSV files older than 7 days
- `smie_register_meta_box()` — Register single post export meta box
- `smie_render_meta_box()` — Render meta box with download button
- `smie_ajax_export_single_post()` — AJAX handler for single post CSV export

## v1.3.0 Features

1. **XML & JSON Import** — Import from XML (including WXR format) and JSON files alongside CSV. Auto-detects format from file extension and MIME type.
2. **Excel XLSX Export** — Export as .xlsx spreadsheets via PHP ZipArchive. Falls back to CSV if ZipArchive unavailable.
3. **Product Image Gallery** — Import WooCommerce product gallery images from comma-separated URLs via `_product_gallery_urls` field.
4. **Hierarchical Taxonomy Import** — Use `>` separator to create nested term hierarchies (e.g., `Parent > Child > Grandchild`). Parents auto-created.
5. **Error Row Retry** — After import, retry only failed rows without re-importing the entire file. Transient preserved for retry.
6. **Real-time Mapping Preview** — Live preview panel in mapping step showing first row mapped to WordPress fields.
7. **Import Progress ETA** — Estimated time remaining during batch import.
8. **Scheduled Import Error Logging** — Last error details stored per schedule, shown as tooltip.
9. **Accessibility** — ARIA attributes on progress bar/live log/overlay/validation, ESC key overlay close, `:focus-visible` keyboard styles.

### New Helper Functions (v1.3.0)
- `smie_read_json_file()` — Read and parse JSON file (array of objects), flatten nested keys with dot notation
- `smie_read_xml_file()` — Parse XML via SimpleXML, supports WXR format and auto-detecting repeating elements
- `smie_read_import_file()` — Dispatcher routing to CSV/JSON/XML reader by file extension
- `smie_flatten_array()` — Recursive helper for JSON nested key flattening
- `smie_xml_element_to_flat()` — Recursive helper for XML element flattening
- `smie_set_product_gallery()` — Download comma-separated image URLs and store as WooCommerce product gallery
- `smie_generate_xlsx()` — Build minimal valid XLSX using ZipArchive
- `smie_xlsx_col_letter()` — Convert 0-based column index to Excel column letter (A, B, ..., AA, ...)

## v1.4.0 Features

1. **Full-width Dashboard** — Plugin page uses `max-width: 100%` instead of 860px, with right padding for comfortable reading.
2. **Dark Mode** — Manual toggle button (dashicons-visibility/hidden) with `localStorage` persistence. All ~30 hardcoded colors refactored to CSS custom properties on `.smie-wrap` (light) and `.smie-wrap--dark` (dark). Includes WP admin element overrides.
3. **Field Search in Mapping** — Search input above mapping table filters rows by field label text, shows "N / M" match counter.
4. **Post Parent-Child Import** — `post_parent` field in core fields. Accepts numeric post ID or title string (title lookup queries same post type via `$wpdb`).
5. **Extra Transforms with Parameters** — 9 new transforms: `find_replace`, `prepend`, `append`, `math_multiply`, `math_add`, `number_format`, `date_mdy`, `date_iso`, `url_encode`. Transform select grouped into optgroups. Parameterized transforms use `{ transform, param }` object format alongside simple strings.
6. **Column Merge Mapping** — `__merge__` source type in column select. Template input (e.g., `{first_name} {last_name}`) replaces `{col_name}` placeholders with CSV row values. Mapping payload: `{ source: 'merge', template: '...' }`.
7. **Dashboard Statistics Widget** — `wp_dashboard_setup` widget showing last 5 imports, next scheduled import/export, and quick link to importer. Visible only to `manage_options` users.
8. **View Details Popup** — `plugins_api` filter (`smie_plugins_api_info`) provides plugin description, installation, and changelog for thickbox modal. `plugin_row_meta` filter (`smie_plugin_row_meta`) adds "View details" link.
9. **Author URI** — Changed to `https://minicad.io/`.

### New Helper Functions (v1.4.0)
- `smie_plugin_row_meta()` — Add "View details" thickbox link on Plugins page
- `smie_plugins_api_info()` — Provide plugin details for the thickbox popup (description, installation, changelog)
- `smie_register_dashboard_widget()` — Register the dashboard statistics widget
- `smie_render_dashboard_widget()` — Render dashboard widget content (last 5 imports, next scheduled events)

### Updated Functions (v1.4.0)
- `smie_apply_transform()` — Now accepts `string|array` (simple name or `{ transform, param }` object). Added 9 new transforms.
- `smie_sanitize_mapping()` — Now handles `source: 'merge'` with `template` field sanitization.
- `smie_import_single_row()` — Added `$headers` parameter (9th). Handles `post_parent` (ID or title lookup) and `merge` source type (template replacement).
- `smie_get_post_type_fields()` — Added `post_parent` to core fields.

### CSS Architecture (v1.4.0)
- ~30 CSS custom properties defined on `.smie-wrap` (light theme defaults)
- `.smie-wrap--dark` overrides all variables for dark theme
- Zero hardcoded colors remain in rule declarations — all use `var(--smie-*)`
- New classes: `.smie-dark-toggle`, `.smie-field-search`, `.smie-field-search-input`, `.smie-field-search-count`, `.smie-transform-param`, `.smie-merge-template`

### JS Architecture (v1.4.0)
- Dark mode IIFE: reads `localStorage('smie_dark_mode')`, toggles `.smie-wrap--dark`, swaps icon
- `buildMappingRow()`: optgroup-based transform select, param input, merge option + template input
- `bindMappingEvents()`: `paramTransforms` map for placeholder text, show/hide param and merge inputs
- `buildMappingPayload()`: returns `{ source: 'merge', template }` for merge selections
- `buildTransformPayload()`: returns `{ transform, param }` for parameterized transforms

## Coding Standards

### PHP
- **WordPress Coding Standards** enforced via `phpcs.xml.dist`
- Tabs for indentation, Yoda conditions, spaces inside parentheses
- Prefix all globals with `smie_` or `smartly_import_export_`
- Text domain: `smartly-import-export` (must match slug exactly)
- Every translatable string uses `__()`, `_e()`, `esc_html__()`, `esc_attr__()`
- Translator comments (`/* translators: */`) on every `sprintf` pattern
- `$wpdb->prepare()` on all raw SQL — add `phpcs:ignore` with explanation when needed
- `ABSPATH` check at top of every PHP file
- `WP_UNINSTALL_PLUGIN` check at top of `uninstall.php`

### JavaScript
- `var` not `let`/`const` — broadest browser compatibility
- `window.alert()` / `window.confirm()` not bare `alert()` / `confirm()`
- jQuery dependency declared in `wp_enqueue_script`
- Data passed via `wp_localize_script` (`smieImporter` object)
- IIFE wrapper: `(function($) { 'use strict'; ... })(jQuery)`

### CSS
- All classes prefixed `smie-`
- Use WordPress admin classes where it makes sense (`widefat`, `button`, `button-primary`, `notice`, `description`)
- Test at 782px (WP admin breakpoint) and 480px

## Version Management

Three places must stay in sync on every release:
1. `smartly-import-export/smartly-import-export.php` → `Version:` header + `SMIE_VERSION` constant
2. `smartly-import-export/readme.txt` → `Stable tag:`
3. Git tag (checked by `wp-deploy.yml`)

## CI/CD Workflows

| Workflow | Trigger | Purpose |
|---|---|---|
| `php-lint.yml` | push/PR to main | Syntax check PHP 8.0–8.3 |
| `wpcs.yml` | push/PR to main | PHPCS via `composer install` + `phpcs.xml.dist` |
| `plugin-check.yml` | push/PR to main | Combined lint + WPCS (can be removed if redundant) |
| `wp-plugin-check.yml` | push/PR on plugin file changes | Official WP Plugin Check action |
| `wp-deploy.yml` | GitHub release published | Version check → generate POT → deploy to WP.org SVN |
| `release.yml` | GitHub release published | Build clean ZIP → attach to release |

**Secrets required**: `SVN_USERNAME`, `SVN_PASSWORD` (WordPress.org credentials for `wp-deploy.yml`)

## Database Usage

**No custom tables.** Uses only:
- WordPress transients for temporary CSV data storage (`smie_csv_data_{token}`)
- `wp_options` for import history (`smie_import_history`), mapping profiles (`smie_mapping_profiles`), scheduled imports (`smie_scheduled_imports`), and scheduled exports (`smie_scheduled_exports`)
- Standard `wp_posts`, `wp_postmeta`, `wp_terms` via WordPress APIs

`uninstall.php` cleans up all `smie_csv_data_*` transients via direct `$wpdb` query, plus `smie_import_history`, `smie_mapping_profiles`, `smie_scheduled_imports`, `smie_scheduled_exports` options and their associated cron events.

## Asset Loading

Assets load **only** on the plugin's admin page (`tools_page_smartly-import-export` hook check). Version parameter uses `filemtime()` for cache busting during development. JS loaded in footer.

## Import Flow (6 Steps)

1. **Choose Content Type** — dropdown of registered post types with `show_ui => true`
2. **Choose Action** — Import, Export, or Template buttons
3. **Provide CSV** — drag-and-drop upload OR fetch from URL, with data preview
4. **Map Columns** — auto-matches CSV headers to WP fields, supports custom static values and extra meta fields
5. **Batch Import** — processes 50 rows/request with progress bar and live log
6. **Results** — color-coded summary badges + full per-row log

## Export Modes

- **All** — every post of the selected type
- **Row Range** — offset/limit by row number
- **ID Range** — filter by post ID min/max
- **Date Range** — filter by publish date

Exports include BOM for Excel compatibility. CSV transported as base64 through JSON response, converted to Blob download on client.

## Testing Checklist

Before any PR:
- [ ] PHPCS passes (`composer phpcs` or CI)
- [ ] PHP syntax valid on 7.4, 8.0, 8.1, 8.2, 8.3
- [ ] Every AJAX handler has nonce + capability check
- [ ] All input sanitized, all output escaped
- [ ] Text domain is `smartly-import-export` on all strings
- [ ] `Stable tag` in readme.txt matches `Version` in PHP header
- [ ] Responsive at 782px and 480px
- [ ] Import works: insert, update, insert-update modes
- [ ] Export works: all 4 modes
- [ ] No JS console errors
- [ ] Plugin Check action passes
