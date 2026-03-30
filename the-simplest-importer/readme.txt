=== The Simplest Importer ===
Contributors: ahm.elessawy
Tags: csv, import, export, custom post type, bulk
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import, export, and manage WordPress posts and custom post types via CSV with visual column mapping and batch processing.

== Description ==

**The Simplest Importer** lets you import, export, and manage posts, pages, and any custom post type using CSV files — all with a clean, visual interface that makes the process intuitive and reliable.

**Key Features:**

* **Visual Column Mapping** — Map CSV columns to WordPress fields with an auto-matching interface. The plugin detects matching column names automatically.
* **Batch Import** — Large files are processed in batches with a real-time progress bar, so your server never times out.
* **Export** — Download all posts of any type as a CSV file, including core fields, taxonomies, and custom meta.
* **Export Template** — Generate a blank CSV template with the correct headers for any post type.
* **Drag & Drop Upload** — Drop your CSV file directly onto the upload area, or click to browse.
* **Fetch from URL** — Import a CSV from any publicly accessible URL.
* **Data Preview** — See the first rows of your CSV before importing, so you know the data is correct.
* **Custom Static Values** — Assign a fixed value to any field during import (e.g., set all posts to "publish").
* **Extra Custom Fields** — Add new meta fields on-the-fly, even if they don't exist yet.
* **Insert or Update** — If your CSV includes an ID column matching an existing post, it will be updated.
* **Detailed Log** — See exactly what happened for each row: inserted, updated, skipped, or errored.
* **Taxonomy Support** — Import comma-separated terms for categories, tags, and custom taxonomies.
* **Featured Image** — Set featured images from URLs during import.
* **Select All / Deselect All** — Quickly toggle field mappings.
* **Developer Friendly** — Action hooks and filters to extend import behaviour.
* **Admin Only** — Requires `manage_options` capability. No front-end output, no tracking, no external calls.
* **Scheduled Imports** — Set up recurring WP-Cron imports from a URL (hourly, twice daily, daily, weekly).
* **Import History & Rollback** — View past imports and move imported posts to trash with one click.
* **Duplicate Detection** — Skip rows when a matching post title, slug, or meta key already exists.
* **Field Transforms** — Apply per-field transforms during import: uppercase, lowercase, title case, trim, strip tags, slug, and date formats.
* **Mapping Profiles** — Save and reuse column mapping configurations.
* **CSV Validation** — Check for issues before importing: invalid dates, statuses, author IDs, and thumbnail URLs.
* **Dry Run Mode** — Preview what an import would do without touching the database.
* **Multi-file Upload** — Drag-and-drop multiple CSV files to queue them for sequential import.
* **Delimiter Auto-detection** — Handles comma, semicolon, tab, and pipe delimiters automatically.
* **Selective Column Export** — Choose which fields to include in your export.
* **Status Filter Export** — Filter exported posts by publish status.
* **ACF Support** — Auto-detects Advanced Custom Fields and uses `update_field()` for proper ACF data handling.
* **SEO Meta Support** — Auto-detects Yoast SEO and Rank Math, adding SEO Title, Description, and Focus Keyword fields to import/export.
* **Google Sheets Import** — Paste a Google Sheets URL and it's automatically converted for CSV download.
* **Conditional Row Filtering** — Define rules to skip rows during import based on column values (equals, contains, greater than, etc.).
* **Email Notifications** — Receive email reports when scheduled imports complete (success or failure).
* **Scheduled Exports** — Set up recurring WP-Cron exports with files saved to uploads and optionally emailed as attachments.
* **Single Post Export** — Export any individual post as CSV from its edit screen via a meta box.
* **Enhanced Developer Hooks** — 8 new filters and actions for CSV parsing, export columns, export rows, import rows, and more.

**Supported Post Types:**

Works with all registered post types that have a UI — Posts, Pages, WooCommerce Products, and any custom post type registered by themes or plugins.

== Installation ==

1. Upload the `the-simplest-importer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Tools → Simplest Importer** to start importing or exporting.
4. The plugin also appears in **Tools → Import** alongside other WordPress importers.

== Frequently Asked Questions ==

= What CSV format does the plugin expect? =

Standard CSV with the first row as column headers. UTF-8 encoding is recommended. The plugin handles BOM (byte order mark) automatically.

= Can I update existing posts? =

Yes. Include an `ID` column in your CSV with the WordPress post ID. Matching posts of the selected type will be updated.

= What happens if the ID doesn't exist? =

A new post is created with the next available ID. The non-existent ID is simply ignored.

= Can I import custom fields (post meta)? =

Yes. The plugin auto-discovers all meta keys for the selected post type. You can also add new fields on-the-fly using the "Add Custom Field" button.

= Does this plugin work with WooCommerce? =

Yes, it works with any custom post type that has `show_ui` enabled, including WooCommerce Products.

= Is there a limit on CSV file size? =

The limit depends on your server settings (`upload_max_filesize`, `post_max_size`, `memory_limit`). The batch import system prevents timeouts even for large files.

= Does this plugin send data to external servers? =

No. All processing happens on your server. External requests only occur if you fetch a CSV from a URL you provide, or import a featured image URL.

= How does batch import work? =

The plugin processes 50 rows at a time (filterable via `tsi_import_batch_size`). A progress bar shows real-time status, and your browser stays responsive throughout.

== Screenshots ==

1. Step 1 — Choose a content type from the dropdown.
2. Step 2 — Pick an action: Import, Export, or Export Template.
3. Step 3 — Drag & drop a CSV file or fetch from a URL, with data preview.
4. Step 4 — Map CSV columns to post fields with auto-matching.
5. Step 5 — Real-time batch import with progress bar.
6. Step 6 — Color-coded result summary with detailed log.

== Changelog ==

= 1.2.0 =
* Added: ACF support — auto-detects Advanced Custom Fields, uses `update_field()` for proper data handling.
* Added: SEO meta support — auto-detects Yoast SEO and Rank Math, adds SEO title/description/focus keyword fields.
* Added: Google Sheets import — automatically converts Google Sheets URLs to CSV download links.
* Added: Conditional row filtering — skip rows during import based on column value rules (8 operators).
* Added: Email notifications — receive reports when scheduled imports complete or fail.
* Added: Scheduled exports — recurring WP-Cron exports saved to uploads with optional email attachment.
* Added: Single post export — meta box on post edit screens for exporting individual posts as CSV.
* Added: 8 new developer hooks: `tsi_csv_parsed`, `tsi_export_columns`, `tsi_export_row`, `tsi_export_completed`, `tsi_before_import_row`, `tsi_import_row_data`, `tsi_import_completed`, `tsi_import_row_filter`.
* Fixed: "Proceed with Import" button after CSV validation was not functional.
* Fixed: Scheduled imports failed on semicolon/tab/pipe-delimited CSV files.
* Fixed: Misleading "WordPress Users" reference in admin UI replaced with accurate description.

= 1.1.0 =
* Added: Scheduled imports — WP-Cron based recurring imports from URL (hourly, twice daily, daily, weekly).
* Added: Import history — records last 50 imports with post IDs, supports rollback to trash.
* Added: Duplicate detection — skip imports when matching post title, slug, or custom meta key.
* Added: Field transforms — per-field transforms during import (uppercase, lowercase, title case, trim, strip tags, slug, date formats).
* Added: Status filter export — filter exported posts by status.
* Added: Mapping profiles — save, load, and delete column mapping configurations per post type.
* Added: CSV validation — pre-import validation checking dates, statuses, author IDs, and thumbnail URLs.
* Added: Multi-file upload — drag-and-drop multiple CSV files, queued for sequential processing.
* Added: Selective column export — choose which fields to include in export.
* Added: Delimiter auto-detection — automatically detects comma, semicolon, tab, or pipe delimiters.
* Added: Dry run mode — preview import results without making any database changes.
* Added: Download import log — export per-row import results as CSV.
* Added: Import rollback — move all posts from a specific import to trash.
* Added: Sticky post type — remembers last selected post type via localStorage.
* Added: Export row count — shows post count in the export button.

= 1.0.0 =
* Initial release.
* Import, export, and template generation for all public post types.
* Visual column mapping with smart auto-matching.
* Batch import with real-time progress bar.
* Drag & drop CSV upload and remote URL fetch.
* Data preview before import.
* Custom static values and on-the-fly custom fields.
* Insert or update posts by ID.
* Detailed per-row import log.
* Featured image import from URL.
* Taxonomy term support.
* Responsive admin interface.
* Developer hooks: `tsi_post_types`, `tsi_post_type_fields`, `tsi_import_batch_size`, `tsi_after_import_row`.

== Upgrade Notice ==

= 1.2.0 =
New: ACF & SEO meta support, Google Sheets import, conditional row filtering, scheduled exports, email notifications, and single post export meta box.

= 1.0.0 =
Initial release.
