# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.3.0] - Unreleased

### Added
- XML and JSON import — import data from XML (including WordPress WXR format) and JSON files alongside CSV. Auto-detects format from file extension and MIME type.
- Excel XLSX export — export data as .xlsx spreadsheets using PHP ZipArchive. Falls back to CSV if ZipArchive is unavailable.
- Product image gallery — import WooCommerce product gallery images from comma-separated URLs via `_product_gallery_urls` field.
- Hierarchical taxonomy import — use `>` separator to create nested term hierarchies (e.g., `Parent > Child > Grandchild`). Parent terms are created automatically if they don't exist.
- Error row retry — after import, retry only the rows that failed without re-importing the entire file. Transient data is preserved for retry.
- Real-time mapping preview — live preview panel in the mapping step showing how the first data row maps to WordPress fields.
- Import progress ETA — estimated time remaining displayed during batch import processing.
- Scheduled import error logging — last error details stored per schedule and shown as tooltip in the schedule list.
- Accessibility improvements — `role="progressbar"` with ARIA attributes on progress bar, `aria-live` on live log and overlay, `role="alert"` on validation results, `role="dialog"` on overlay, ESC key closes overlay, `:focus-visible` styles for keyboard navigation.

## [1.2.0] - Unreleased

### Added
- ACF support — auto-detects Advanced Custom Fields and uses `update_field()` for proper data handling during import.
- SEO meta support — auto-detects Yoast SEO and Rank Math, adds SEO Title, Description, and Focus Keyword fields.
- Google Sheets import — automatically converts Google Sheets URLs to CSV download links (both edit and published formats).
- Conditional row filtering — define rules to skip rows during import based on column values (equals, not equals, contains, not contains, greater than, less than, empty, not empty).
- Email notifications — receive email reports when scheduled imports complete or fail.
- Scheduled exports — recurring WP-Cron exports saved to `wp-content/uploads/tsi-exports/` with optional email attachment. Auto-cleanup after 7 days.
- Single post export — meta box on post edit screens for exporting individual posts as CSV.
- New developer hooks: `tsi_csv_parsed`, `tsi_export_columns`, `tsi_export_row`, `tsi_export_completed`, `tsi_before_import_row`, `tsi_import_row_data`, `tsi_import_completed`, `tsi_import_row_filter`.

### Fixed
- "Proceed with Import" button after CSV validation was non-functional (missing JS handler).
- Scheduled imports failed on semicolon, tab, and pipe-delimited CSV files (now uses delimiter auto-detection).
- Misleading "plus WordPress Users" text in admin UI replaced with accurate "all registered post types with a UI".

## [1.1.0] - 2026-03-30

### Added
- Scheduled imports — WP-Cron based recurring imports from URL (hourly, twice daily, daily, weekly).
- Import history — records last 50 imports with post IDs, supports rollback to trash.
- Duplicate detection — skip imports when matching post title, slug, or custom meta key.
- Field transforms — per-field transforms during import (uppercase, lowercase, title case, trim, strip tags, slug, date formats).
- Status filter export — filter exported posts by status (publish, draft, pending, private, future).
- Mapping profiles — save, load, and delete column mapping configurations per post type.
- CSV validation — pre-import validation checking dates, statuses, author IDs, and thumbnail URLs.
- Multi-file upload — drag-and-drop multiple CSV files, queued for sequential processing.
- Selective column export — choose which fields to include in export.
- Delimiter auto-detection — automatically detects comma, semicolon, tab, or pipe delimiters.
- Dry run mode — preview import results without making any database changes.
- Download import log — export per-row import results as CSV.
- Import rollback — move all posts from a specific import to trash.
- Sticky post type — remembers last selected post type via localStorage.
- Export row count — shows post count in the export button.
- New developer hooks: `tsi_import_batch_size` filter, `tsi_after_import_row` action.

## [1.0.0] - 2026-03-29

### Added
- CSV import with column mapping to any post type and its fields.
- Support for post meta, taxonomies, and featured images.
- Batch processing with configurable batch size.
- CSV export with multiple modes: All Posts, Row Range, Post ID Range, and Date Range.
- Insert New and Update Existing import modes.
- Drag-and-drop CSV file upload.
- Admin UI under Tools menu.
