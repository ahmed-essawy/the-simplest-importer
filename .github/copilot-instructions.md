# The Simplest Importer — Project Instructions

## Overview

WordPress plugin (slug: `the-simplest-importer`) for importing, exporting, and managing posts and custom post types via CSV, JSON, and XML. Single-file PHP architecture with a jQuery admin UI. Hosted on GitHub at `ahmed-essawy/the-simplest-importer`, targeting WordPress.org distribution.

- **Version**: 1.3.0
- **License**: GPL-2.0-or-later
- **Requires**: WordPress 5.8+, PHP 7.4+
- **Tested up to**: WordPress 6.9
- **Author**: Ahmed Essawy (`ahm.elessawy` on WP.org)

## Repository Structure

```
the-simplest-importer/              ← Git root (NOT the plugin folder)
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
└── the-simplest-importer/          ← PLUGIN FOLDER (what ships to WP.org)
    ├── the-simplest-importer.php   ← Main plugin file (~1,250 lines, all logic)
    ├── readme.txt                  ← WordPress.org readme (Stable tag must match Version)
    ├── uninstall.php               ← Cleans up transients on uninstall
    ├── index.php                   ← "Silence is golden"
    ├── assets/
    │   ├── app.js                  ← jQuery admin UI (~900 lines, IIFE)
    │   ├── style.css               ← Admin styles (~800 lines, responsive)
    │   └── index.php               ← "Silence is golden"
    └── languages/
        └── index.php               ← "Silence is golden"
```

**Key distinction**: The Git root is NOT the plugin directory. The deployable plugin lives inside `the-simplest-importer/` subfolder. Workflows reference this as `PLUGIN_SLUG` or `PLUGIN_DIR`.

## Architecture

### Single-File PHP (`the-simplest-importer.php`)

All server logic is in one file — no classes, no autoloader, no separate includes. Functions are prefixed `tsi_` and organized by section comments.

**Sections in order:**
1. Plugin header + constants (`TSI_VERSION`, `TSI_PLUGIN_DIR`, `TSI_PLUGIN_URL`)
2. Admin menu registration (`tsi_register_admin_page`) — Tools submenu
3. WordPress Importer registration (`tsi_register_wp_importer`) — appears on Tools → Import
4. Asset enqueueing (`tsi_enqueue_admin_assets`) — only loads on plugin page
5. Admin page markup (`tsi_render_admin_page`) — 6-step wizard HTML
6. AJAX handlers:
   - `tsi_get_post_types` — returns post types with counts + max ID
   - `tsi_get_fields` — returns importable fields for a post type
   - `tsi_parse_csv` — parses uploaded CSV file
   - `tsi_parse_csv_url` — fetches and parses CSV from URL
   - `tsi_export` — exports posts as base64 CSV (modes: all, rows, range, dates)
   - `tsi_template` — generates blank CSV template
   - `tsi_import_batch` — processes N rows per call, client loops until done
7. Helper functions:
   - `tsi_get_post_type_fields()` — builds field list (core + taxonomies + meta)
   - `tsi_read_csv_file()` — reads CSV, stores in transient, returns preview
   - `tsi_sanitize_mapping()` — sanitizes mapping payload from client
   - `tsi_import_single_row()` — inserts or updates one post
   - `tsi_set_featured_image()` — downloads image URL and sets as thumbnail
   - `tsi_filter_export_id_range()` — `posts_where` filter for ID range export

### JavaScript (`assets/app.js`)

jQuery IIFE, no build step, no transpilation. Uses `var` throughout for max compat.

**State variables**: `csvHeaders`, `csvToken`, `csvRowCount`, `extraFieldCount`, `postTypeData`

**Flow**: Load post types → select type → choose action (import/export/template) → upload CSV → auto-match columns → run batch import → show results

**Key patterns:**
- All AJAX via `$.post()` to `tsiImporter.ajax_url` with `tsiImporter.nonce`
- `buildMappingRow()` creates mapping table rows with auto-match logic
- `processBatch()` recurses until server returns `done: true`
- `esc()` function creates text node for XSS-safe HTML insertion
- `downloadBase64()` converts base64 CSV to Blob for download

### CSS (`assets/style.css`)

No preprocessor, no build. BEM-ish naming with `tsi-` prefix. Responsive breakpoints at 782px and 480px. Uses WordPress admin palette and Dashicons.

## Security Model

Every AJAX handler follows this pattern — never skip any step:
1. `check_ajax_referer( 'tsi_nonce', 'nonce' )`
2. `current_user_can( 'manage_options' )` check
3. Sanitize all input (`sanitize_key`, `sanitize_text_field`, `absint`, `esc_url_raw`, `wp_unslash`)
4. Escape all output (`esc_html`, `esc_attr`, `esc_html__`)

**CSV data** is stored in transients with random tokens (20-char `wp_generate_password`), auto-expiring after 1 hour. Transients are cleaned up after import completes and on plugin uninstall.

**No external calls** except user-initiated: fetching CSV from user-provided URL, downloading featured images from user-provided URLs.

## Developer Hooks

Filters:
- `tsi_post_types` — modify the post type list shown in dropdown
- `tsi_post_type_fields` — modify importable fields for a post type
- `tsi_import_batch_size` — change batch size (default: 50)
- `tsi_csv_parsed` — filter parsed CSV data before transient storage (v1.2.0)
- `tsi_export_columns` — filter export column list (v1.2.0)
- `tsi_export_row` — filter each export row before writing (v1.2.0)
- `tsi_import_row_data` — filter post/meta/tax data before insert/update (v1.2.0)
- `tsi_import_row_filter` — custom row filter logic during import (v1.2.0)

Actions:
- `tsi_after_import_row` — fires after each row import with `$post_id`, `$row`, `$row_num`, `$is_update`
- `tsi_before_import_row` — fires before each row import (v1.2.0)
- `tsi_export_completed` — fires after export finishes (v1.2.0)
- `tsi_import_completed` — fires after batch import finishes all rows (v1.2.0)

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
- `TSI_HISTORY_OPTION` — wp_options key for import history
- `TSI_PROFILES_OPTION` — wp_options key for mapping profiles
- `TSI_SCHEDULES_OPTION` — wp_options key for scheduled imports

### New AJAX Handlers (v1.1.0)
- `tsi_save_profile` — Save a mapping profile
- `tsi_delete_profile` — Delete a mapping profile
- `tsi_validate_csv` — Validate CSV data before import
- `tsi_rollback` — Rollback an import by trashing posts
- `tsi_get_history` — Fetch import history
- `tsi_add_schedule` — Create a scheduled import
- `tsi_delete_schedule` — Remove a scheduled import

### New Helper Functions (v1.1.0)
- `tsi_apply_transform()` — Apply named transform to a field value
- `tsi_check_duplicate()` — Check for duplicate posts by field
- `tsi_record_import_history()` — Save import to history
- `tsi_get_import_history()` — Retrieve import history
- `tsi_get_mapping_profiles()` — Retrieve saved profiles
- `tsi_get_scheduled_imports()` — Retrieve scheduled imports
- `tsi_run_scheduled_import()` — WP-Cron callback for scheduled imports
- `tsi_add_cron_interval()` — Registers weekly cron schedule

## v1.2.0 Features

1. **ACF Support** — Auto-detects Advanced Custom Fields groups per post type, shows friendly field labels, uses `update_field()` during import
2. **SEO Meta Support** — Auto-detects Yoast SEO (`WPSEO_VERSION`) and Rank Math (`RANK_MATH_VERSION`/`RankMath` class), adds SEO Title, Description, and Focus Keyword fields
3. **Google Sheets Import** — `tsi_convert_google_sheets_url()` auto-converts `/d/{ID}/edit` and `/d/e/{PUBID}/pub` URLs to CSV export links, works in both manual URL fetch and scheduled imports
4. **Conditional Row Filtering** — Filter builder UI in mapping step with 8 operators (equals, not_equals, contains, not_contains, gt, lt, empty, not_empty), AND logic, `tsi_import_row_filter` hook
5. **Scheduled Exports** — WP-Cron recurring exports saved to `wp-content/uploads/tsi-exports/` with `.htaccess` protection, auto-cleanup after 7 days, optional email attachment
6. **Email Notifications** — Scheduled imports send email reports on success or failure via `tsi_send_schedule_email()`, exports attach the CSV file
7. **Single Post Export** — Meta box on all post type edit screens with "Download CSV" button, uses `tsi_ajax_export_single_post()` AJAX handler
8. **Enhanced Developer Hooks** — 8 new hooks: `tsi_csv_parsed`, `tsi_export_columns`, `tsi_export_row`, `tsi_export_completed`, `tsi_before_import_row`, `tsi_import_row_data`, `tsi_import_completed`, `tsi_import_row_filter`

### New Constants (v1.2.0)
- `TSI_EXPORT_SCHEDULES_OPTION` — wp_options key for scheduled exports

### New AJAX Handlers (v1.2.0)
- `tsi_add_export_schedule` — Create a scheduled export
- `tsi_delete_export_schedule` — Delete a scheduled export
- `tsi_export_single_post` — Export a single post as CSV from edit screen

### New Helper Functions (v1.2.0)
- `tsi_convert_google_sheets_url()` — Convert Google Sheets URLs to CSV export URLs
- `tsi_row_matches_filters()` — Evaluate conditional filter rules against a CSV row
- `tsi_send_schedule_email()` — Send email notification for a scheduled import
- `tsi_get_export_schedules()` — Retrieve scheduled exports list
- `tsi_run_scheduled_export()` — WP-Cron callback for scheduled exports
- `tsi_cleanup_old_exports()` — Remove export CSV files older than 7 days
- `tsi_register_meta_box()` — Register single post export meta box
- `tsi_render_meta_box()` — Render meta box with download button
- `tsi_ajax_export_single_post()` — AJAX handler for single post CSV export

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
- `tsi_read_json_file()` — Read and parse JSON file (array of objects), flatten nested keys with dot notation
- `tsi_read_xml_file()` — Parse XML via SimpleXML, supports WXR format and auto-detecting repeating elements
- `tsi_read_import_file()` — Dispatcher routing to CSV/JSON/XML reader by file extension
- `tsi_flatten_array()` — Recursive helper for JSON nested key flattening
- `tsi_xml_element_to_flat()` — Recursive helper for XML element flattening
- `tsi_set_product_gallery()` — Download comma-separated image URLs and store as WooCommerce product gallery
- `tsi_generate_xlsx()` — Build minimal valid XLSX using ZipArchive
- `tsi_xlsx_col_letter()` — Convert 0-based column index to Excel column letter (A, B, ..., AA, ...)

## Coding Standards

### PHP
- **WordPress Coding Standards** enforced via `phpcs.xml.dist`
- Tabs for indentation, Yoda conditions, spaces inside parentheses
- Prefix all globals with `tsi_` or `the_simplest_importer_`
- Text domain: `the-simplest-importer` (must match slug exactly)
- Every translatable string uses `__()`, `_e()`, `esc_html__()`, `esc_attr__()`
- Translator comments (`/* translators: */`) on every `sprintf` pattern
- `$wpdb->prepare()` on all raw SQL — add `phpcs:ignore` with explanation when needed
- `ABSPATH` check at top of every PHP file
- `WP_UNINSTALL_PLUGIN` check at top of `uninstall.php`

### JavaScript
- `var` not `let`/`const` — broadest browser compatibility
- `window.alert()` / `window.confirm()` not bare `alert()` / `confirm()`
- jQuery dependency declared in `wp_enqueue_script`
- Data passed via `wp_localize_script` (`tsiImporter` object)
- IIFE wrapper: `(function($) { 'use strict'; ... })(jQuery)`

### CSS
- All classes prefixed `tsi-`
- Use WordPress admin classes where it makes sense (`widefat`, `button`, `button-primary`, `notice`, `description`)
- Test at 782px (WP admin breakpoint) and 480px

## Version Management

Three places must stay in sync on every release:
1. `the-simplest-importer/the-simplest-importer.php` → `Version:` header + `TSI_VERSION` constant
2. `the-simplest-importer/readme.txt` → `Stable tag:`
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
- WordPress transients for temporary CSV data storage (`tsi_csv_data_{token}`)
- `wp_options` for import history (`tsi_import_history`), mapping profiles (`tsi_mapping_profiles`), scheduled imports (`tsi_scheduled_imports`), and scheduled exports (`tsi_scheduled_exports`)
- Standard `wp_posts`, `wp_postmeta`, `wp_terms` via WordPress APIs

`uninstall.php` cleans up all `tsi_csv_data_*` transients via direct `$wpdb` query, plus `tsi_import_history`, `tsi_mapping_profiles`, `tsi_scheduled_imports`, `tsi_scheduled_exports` options and their associated cron events.

## Asset Loading

Assets load **only** on the plugin's admin page (`tools_page_the-simplest-importer` hook check). Version parameter uses `filemtime()` for cache busting during development. JS loaded in footer.

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
- [ ] Text domain is `the-simplest-importer` on all strings
- [ ] `Stable tag` in readme.txt matches `Version` in PHP header
- [ ] Responsive at 782px and 480px
- [ ] Import works: insert, update, insert-update modes
- [ ] Export works: all 4 modes
- [ ] No JS console errors
- [ ] Plugin Check action passes
