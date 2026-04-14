# Smartly Import Export

**Import, export, and manage WordPress posts and custom post types via CSV — with visual column mapping and batch processing.**

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/smartly-import-export)](https://wordpress.org/plugins/smartly-import-export/)
[![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/smartly-import-export)](https://wordpress.org/plugins/smartly-import-export/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP Compatibility](https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-8892BF.svg)](https://www.php.net/)

---

## Features

- **Visual Column Mapping** — Map CSV columns to WordPress fields with smart auto-matching.
- **Batch Import** — Large files processed in configurable batches with a real-time progress bar.
- **Export** — Download all posts of any type as a CSV with core fields, taxonomies, and custom meta.
- **Export Template** — Generate a blank CSV template with correct headers for any post type.
- **Drag & Drop Upload** — Drop your CSV file or browse to select it.
- **Fetch from URL** — Import a CSV from any publicly accessible URL.
- **Data Preview** — See the first rows before importing.
- **Custom Static Values** — Assign fixed values to any field during import.
- **Extra Custom Fields** — Add new meta fields on-the-fly.
- **Insert or Update** — Existing posts matched by ID are updated; new rows are inserted.
- **Detailed Log** — Color-coded per-row results: inserted, updated, skipped, or errored.
- **Taxonomy Support** — Import comma-separated terms for categories, tags, and custom taxonomies.
- **Featured Image** — Set featured images from URLs during import.
- **Developer Hooks** — Filters and actions to extend import behavior.
- **Admin Only** — Requires `manage_options`. No front-end output or tracking. Remote requests only occur for URLs or notifications you explicitly configure, such as importing a file from a URL, downloading external media during import, or sending scheduled import/export emails.

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |

## Installation

### From WordPress Admin

1. Go to **Plugins → Add New** and search for **Smartly Import Export**.
2. Click **Install Now**, then **Activate**.
3. Go to **Tools → Smartly Import Export**.

### Manual

1. Download the [latest release](https://github.com/ahmed-essawy/smartly-import-export/releases).
2. Upload the `smartly-import-export` folder to `/wp-content/plugins/`.
3. Activate through **Plugins** in WordPress admin.

## Usage

1. **Choose Content Type** — Select a post type (Posts, Pages, WooCommerce Products, etc.).
2. **Choose Action** — Import, Export, or Export Template.
3. **Provide CSV** — Upload a file or fetch from a URL. Preview the data.
4. **Map Columns** — Match CSV columns to post fields. Auto-matching handles common names.
5. **Run Import** — Watch the real-time progress bar and live log.
6. **Review Results** — Color-coded summary with detailed per-row log.

## Developer Hooks

### Filters

| Filter | Description |
|---|---|
| `smie_post_types` | Modify the list of post types in the dropdown. |
| `smie_post_type_fields` | Modify importable/exportable fields for a post type. |
| `smie_import_batch_size` | Change the batch size (default: 50). |

### Actions

| Action | Description |
|---|---|
| `smie_after_import_row` | Fires after each row is imported. Receives `$post_id`, `$row`, `$row_num`, `$is_update`. |

## Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a pull request.

## License

This project is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) license.
