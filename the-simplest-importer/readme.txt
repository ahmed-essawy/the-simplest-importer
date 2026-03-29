=== The Simplest Importer ===
Contributors: ahm.elessawy
Tags: csv, import, export, custom post type, bulk
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
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

= 1.0.0 =
Initial release.
