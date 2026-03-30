<?php
/**
 * Plugin Name:       The Simplest Importer
 * Plugin URI:        https://github.com/ahmed-essawy/the-simplest-importer
 * Description:       Import, export, and manage posts and custom post types via CSV with visual column mapping and batch processing.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ahmed Essawy
 * Author URI:        https://profiles.wordpress.org/ahm.elessawy/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       the-simplest-importer
 * Domain Path:       /languages
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TSI_VERSION', '1.2.0' );
define( 'TSI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSI_HISTORY_OPTION', 'tsi_import_history' );
define( 'TSI_PROFILES_OPTION', 'tsi_mapping_profiles' );
define( 'TSI_SCHEDULES_OPTION', 'tsi_scheduled_imports' );
define( 'TSI_EXPORT_SCHEDULES_OPTION', 'tsi_scheduled_exports' );

/* ------------------------------------------------------------------
 * Admin Menu — Tools submenu
 * ------------------------------------------------------------------ */

add_action( 'admin_menu', 'tsi_register_admin_page' );

/**
 * Register the plugin page under Tools.
 *
 * @return void
 */
function tsi_register_admin_page() {
	add_management_page(
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Simplest Importer', 'the-simplest-importer' ),
		'manage_options',
		'the-simplest-importer',
		'tsi_render_admin_page'
	);
}

/* ------------------------------------------------------------------
 * WordPress Importer Registration — Tools → Import screen
 * ------------------------------------------------------------------ */

add_action( 'admin_init', 'tsi_register_wp_importer' );

/**
 * Register on the Tools → Import screen so the plugin appears in the
 * built-in importer list alongside other importers.
 *
 * @return void
 */
function tsi_register_wp_importer() {
	if ( ! function_exists( 'register_importer' ) ) {
		return;
	}
	register_importer(
		'the-simplest-importer',
		__( 'The Simplest Importer', 'the-simplest-importer' ),
		__( 'Import posts and custom post types from CSV files with visual column mapping.', 'the-simplest-importer' ),
		'tsi_wp_importer_dispatch'
	);
}

/**
 * Redirect from the Import screen to the dedicated plugin page.
 *
 * @return void
 */
function tsi_wp_importer_dispatch() {
	wp_safe_redirect( admin_url( 'tools.php?page=the-simplest-importer' ) );
	exit;
}

/* ------------------------------------------------------------------
 * Enqueue Admin Assets
 * ------------------------------------------------------------------ */

add_action( 'admin_enqueue_scripts', 'tsi_enqueue_admin_assets' );

/**
 * Enqueue CSS and JS only on the plugin admin page.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function tsi_enqueue_admin_assets( $hook ) {
	if ( 'tools_page_the-simplest-importer' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'tsi-admin',
		TSI_PLUGIN_URL . 'assets/style.css',
		array(),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/style.css' )
	);

	wp_enqueue_script(
		'tsi-admin',
		TSI_PLUGIN_URL . 'assets/app.js',
		array( 'jquery' ),
		(string) filemtime( TSI_PLUGIN_DIR . 'assets/app.js' ),
		true
	);

	wp_localize_script( 'tsi-admin', 'tsiImporter', array(
		'ajax_url'         => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'tsi_nonce' ),
		'batch_size'       => absint( apply_filters( 'tsi_import_batch_size', 50 ) ),
		'profiles'         => tsi_get_mapping_profiles(),
		'history'          => tsi_get_import_history(),
		'schedules'        => tsi_get_scheduled_imports(),
		'export_schedules' => tsi_get_export_schedules(),
	) );
}

/* ------------------------------------------------------------------
 * Admin Page Markup
 * ------------------------------------------------------------------ */

/**
 * Render the plugin admin page.
 *
 * @return void
 */
function tsi_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap tsi-wrap">

		<div class="tsi-header">
			<h1>
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'The Simplest Importer', 'the-simplest-importer' ); ?>
			</h1>
			<p class="tsi-subtitle"><?php esc_html_e( 'Import, export, and manage your WordPress content using CSV files.', 'the-simplest-importer' ); ?></p>
		</div>

		<!-- Step 1 — Choose content type -->
		<div class="tsi-card" id="tsi-step-entity">
			<div class="tsi-card-header">
				<span class="tsi-step-num">1</span>
				<div>
					<h2><?php esc_html_e( 'Choose Content Type', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Select the post type you want to work with.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<select id="tsi-post-type" class="tsi-select" aria-label="<?php esc_attr_e( 'Select content type', 'the-simplest-importer' ); ?>">
					<option value=""><?php esc_html_e( '— Select content type —', 'the-simplest-importer' ); ?></option>
				</select>
				<p class="description tsi-entity-hint"><?php esc_html_e( 'Includes all registered post types with a UI.', 'the-simplest-importer' ); ?></p>
			</div>
		</div>

		<!-- Step 2 — Choose action -->
		<div class="tsi-card" id="tsi-step-actions" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">2</span>
				<div>
					<h2><?php esc_html_e( 'Choose Action', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'What would you like to do with this content type?', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-action-grid">
					<button type="button" id="tsi-btn-import" class="tsi-action-card">
						<span class="dashicons dashicons-upload"></span>
						<strong><?php esc_html_e( 'Import', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Upload a CSV to create or update posts', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-export" class="tsi-action-card">
						<span class="dashicons dashicons-download"></span>
						<strong><?php esc_html_e( 'Export', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Download posts as a CSV file', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-template" class="tsi-action-card">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<strong><?php esc_html_e( 'Template', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Get a blank CSV with correct headers', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-history" class="tsi-action-card">
						<span class="dashicons dashicons-backup"></span>
						<strong><?php esc_html_e( 'History', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'View past imports and rollback', 'the-simplest-importer' ); ?></span>
					</button>
					<button type="button" id="tsi-btn-schedule" class="tsi-action-card">
						<span class="dashicons dashicons-clock"></span>
						<strong><?php esc_html_e( 'Schedule', 'the-simplest-importer' ); ?></strong>
						<span class="tsi-action-desc"><?php esc_html_e( 'Set up recurring CSV imports', 'the-simplest-importer' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 3 — Provide CSV -->
		<div class="tsi-card" id="tsi-step-export" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Export Options', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Choose which posts to include in the export.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<fieldset class="tsi-export-options">
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="all" checked>
							<strong><?php esc_html_e( 'Export All', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export every post of this content type.', 'the-simplest-importer' ); ?></span>
						</label>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="rows">
							<strong><?php esc_html_e( 'Export by Row Number', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export a specific range of rows (ordered by ID, starting from row 1).', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-range-fields" id="tsi-export-row-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From row:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-row-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To row:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-row-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="range">
							<strong><?php esc_html_e( 'Export by Post ID Range', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts whose ID property falls within a specific range.', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-range-fields" id="tsi-export-range-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From ID:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-id-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To ID:', 'the-simplest-importer' ); ?>
								<input type="number" id="tsi-export-id-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="tsi-export-option-wrap">
						<label class="tsi-export-option">
							<input type="radio" name="tsi-export-mode" value="dates">
							<strong><?php esc_html_e( 'Export by Date Range', 'the-simplest-importer' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts published between two dates.', 'the-simplest-importer' ); ?></span>
						</label>
						<div class="tsi-export-date-fields" id="tsi-export-date-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From:', 'the-simplest-importer' ); ?>
								<input type="date" id="tsi-export-date-from">
							</label>
							<label>
								<?php esc_html_e( 'To:', 'the-simplest-importer' ); ?>
								<input type="date" id="tsi-export-date-to">
							</label>
						</div>
					</div>
				</fieldset>
				<div class="tsi-export-actions">
					<button type="button" id="tsi-btn-run-export" class="button button-primary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export', 'the-simplest-importer' ); ?>
					</button>
				</div>

				<details class="tsi-export-advanced">
					<summary><?php esc_html_e( 'Advanced Options', 'the-simplest-importer' ); ?></summary>
					<div class="tsi-export-advanced-body">
						<div class="tsi-export-section">
							<h4><?php esc_html_e( 'Filter by Status', 'the-simplest-importer' ); ?></h4>
							<div class="tsi-status-checks">
								<label><input type="checkbox" name="tsi-export-status" value="publish" checked> <?php esc_html_e( 'Published', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="draft" checked> <?php esc_html_e( 'Draft', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="pending" checked> <?php esc_html_e( 'Pending', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="private" checked> <?php esc_html_e( 'Private', 'the-simplest-importer' ); ?></label>
								<label><input type="checkbox" name="tsi-export-status" value="future"> <?php esc_html_e( 'Scheduled', 'the-simplest-importer' ); ?></label>
							</div>
						</div>
						<div class="tsi-export-section">
							<h4><?php esc_html_e( 'Select Columns', 'the-simplest-importer' ); ?></h4>
							<p class="description"><?php esc_html_e( 'Leave all checked to export every field.', 'the-simplest-importer' ); ?></p>
							<div id="tsi-export-fields" class="tsi-export-fields-list"></div>
						</div>
					</div>
				</details>
			</div>
		</div>

		<!-- Step 3 — Provide CSV (Import) -->
		<div class="tsi-card" id="tsi-step-source" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Provide CSV File', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Upload a file from your computer or fetch one from a URL.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-source-tabs">
					<button type="button" class="tsi-source-tab tsi-source-tab--active" data-tab="upload">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Upload File', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" class="tsi-source-tab" data-tab="url">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'From URL', 'the-simplest-importer' ); ?>
					</button>
				</div>

				<div id="tsi-source-upload" class="tsi-source-panel">
					<div class="tsi-dropzone" id="tsi-dropzone">
						<div class="tsi-dropzone-inner">
							<span class="dashicons dashicons-cloud-upload"></span>
							<p class="tsi-dropzone-title"><?php esc_html_e( 'Drag & drop your CSV file here', 'the-simplest-importer' ); ?></p>
							<p class="tsi-dropzone-or"><?php esc_html_e( 'or', 'the-simplest-importer' ); ?></p>
							<button type="button" class="button" id="tsi-browse-btn"><?php esc_html_e( 'Browse Files', 'the-simplest-importer' ); ?></button>
							<input type="file" id="tsi-csv-file" accept=".csv">
							<p class="description"><?php esc_html_e( 'Accepts .csv files · UTF-8 recommended · first row must be column headers', 'the-simplest-importer' ); ?></p>
						</div>
					</div>
				</div>

				<div id="tsi-source-url" class="tsi-source-panel" style="display:none">
					<div class="tsi-url-row">
						<input type="url" id="tsi-csv-url" class="regular-text" placeholder="https://example.com/data.csv">
						<button type="button" id="tsi-btn-fetch-url" class="button button-primary"><?php esc_html_e( 'Fetch', 'the-simplest-importer' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Enter a direct link to a publicly accessible CSV file. Google Sheets URLs are automatically converted.', 'the-simplest-importer' ); ?></p>
				</div>

				<div id="tsi-file-info" class="tsi-file-info" style="display:none"></div>
				<div id="tsi-file-queue" style="display:none"></div>
				<div id="tsi-preview" style="display:none"></div>
			</div>
		</div>

		<!-- Step 4 — Map columns -->
		<div class="tsi-card" id="tsi-step-mapping" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num">4</span>
				<div>
					<h2><?php esc_html_e( 'Map Columns', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Match CSV columns to post fields. Uncheck fields you do not need.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-mapping-toolbar">
					<button type="button" id="tsi-select-all" class="button button-small"><?php esc_html_e( 'Select All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-deselect-all" class="button button-small"><?php esc_html_e( 'Deselect All', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-reset-all" class="button button-small"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset Mappings', 'the-simplest-importer' ); ?></button>
					<select id="tsi-profile-select" class="tsi-profile-select">
						<option value=""><?php esc_html_e( '— Load Profile —', 'the-simplest-importer' ); ?></option>
					</select>
					<button type="button" id="tsi-save-profile" class="button button-small"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Save Profile', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-delete-profile" class="button button-small" style="display:none"><span class="dashicons dashicons-trash"></span></button>
					<span class="tsi-mapping-count" id="tsi-mapping-count"></span>
				</div>
				<div class="tsi-table-wrap">
					<table class="widefat tsi-mapping-table" id="tsi-mapping-table">
						<thead>
							<tr>
								<th class="tsi-col-check"><?php esc_html_e( 'Use', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-field"><?php esc_html_e( 'Post Field', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-map"><?php esc_html_e( 'CSV Column / Value', 'the-simplest-importer' ); ?></th>
								<th class="tsi-col-transform"><?php esc_html_e( 'Transform', 'the-simplest-importer' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="tsi-mapping-actions">
					<button type="button" id="tsi-add-extra" class="button">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom Field', 'the-simplest-importer' ); ?>
					</button>
					<div class="tsi-import-options">
						<label class="tsi-option-label" title="<?php esc_attr_e( 'Process all rows without inserting or updating any posts', 'the-simplest-importer' ); ?>">
							<input type="checkbox" id="tsi-dry-run"> <?php esc_html_e( 'Dry Run', 'the-simplest-importer' ); ?>
						</label>
						<label class="tsi-option-label" title="<?php esc_attr_e( 'Check for existing posts before inserting', 'the-simplest-importer' ); ?>">
							<input type="checkbox" id="tsi-dup-check"> <?php esc_html_e( 'Duplicate Check', 'the-simplest-importer' ); ?>
						</label>
						<div id="tsi-dup-options">
							<select id="tsi-dup-field" class="tsi-dup-field-select">
								<option value="post_title"><?php esc_html_e( 'by Title', 'the-simplest-importer' ); ?></option>
								<option value="post_name"><?php esc_html_e( 'by Slug', 'the-simplest-importer' ); ?></option>
								<option value="meta_key"><?php esc_html_e( 'by Meta Key', 'the-simplest-importer' ); ?></option>
							</select>
							<span id="tsi-dup-meta-wrap">
								<input type="text" id="tsi-dup-meta-key" class="small-text" placeholder="<?php esc_attr_e( 'meta key', 'the-simplest-importer' ); ?>">
							</span>
						</div>
					</div>
					<div class="tsi-filter-section" id="tsi-filter-section" style="display:none">
						<h4><?php esc_html_e( 'Row Filters', 'the-simplest-importer' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Only import rows matching all rules (AND logic).', 'the-simplest-importer' ); ?></p>
						<div id="tsi-filter-rules"></div>
						<button type="button" id="tsi-add-filter" class="button button-small">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Filter Rule', 'the-simplest-importer' ); ?>
						</button>
					</div>
					<div class="tsi-import-buttons">
						<button type="button" id="tsi-btn-validate" class="button tsi-btn-run">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Validate', 'the-simplest-importer' ); ?>
						</button>
						<button type="button" id="tsi-btn-insert" class="button button-primary tsi-btn-run">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Insert New', 'the-simplest-importer' ); ?>
						</button>
						<button type="button" id="tsi-btn-update" class="button button-primary tsi-btn-run" style="display:none">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Update Existing', 'the-simplest-importer' ); ?>
						</button>
						<button type="button" id="tsi-btn-insert-update" class="button button-primary tsi-btn-run" style="display:none">
							<span class="dashicons dashicons-controls-repeat"></span>
							<?php esc_html_e( 'Insert / Update', 'the-simplest-importer' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Step 5 — Progress -->
		<div class="tsi-card" id="tsi-step-progress" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num tsi-step-num--active">
					<span class="spinner is-active" style="margin:0;float:none"></span>
				</span>
				<div>
					<h2 id="tsi-progress-title"><?php esc_html_e( 'Importing…', 'the-simplest-importer' ); ?></h2>
					<p id="tsi-progress-detail"><?php esc_html_e( 'Preparing your import…', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div class="tsi-progress">
					<div class="tsi-progress-bar">
						<div class="tsi-progress-fill" id="tsi-progress-fill"></div>
					</div>
					<span class="tsi-progress-pct" id="tsi-progress-pct">0%</span>
				</div>
				<div class="tsi-live-log" id="tsi-live-log"></div>
			</div>
		</div>

		<!-- Step 6 — Results -->
		<div class="tsi-card" id="tsi-step-results" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num tsi-step-num--done">
					<span class="dashicons dashicons-yes-alt"></span>
				</span>
				<div>
					<h2><?php esc_html_e( 'Import Complete', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Review the results below.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div id="tsi-results-summary" class="tsi-summary"></div>
				<div id="tsi-results-log"></div>
				<div class="tsi-results-actions">
					<button type="button" id="tsi-btn-new" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Start New Import', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" id="tsi-btn-download-log" class="button">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Log', 'the-simplest-importer' ); ?>
					</button>
					<button type="button" id="tsi-btn-rollback" class="button" style="display:none">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Rollback Import', 'the-simplest-importer' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Validation Results -->
		<div class="tsi-card" id="tsi-step-validation" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-yes-alt"></span></span>
				<div>
					<h2><?php esc_html_e( 'Validation Results', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Review issues found in your CSV data before importing.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<div id="tsi-validation-results"></div>
				<div class="tsi-results-actions">
					<button type="button" id="tsi-btn-proceed-import" class="button button-primary"><?php esc_html_e( 'Proceed with Import', 'the-simplest-importer' ); ?></button>
					<button type="button" id="tsi-btn-cancel-import" class="button"><?php esc_html_e( 'Cancel', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Import History -->
		<div class="tsi-card" id="tsi-step-history" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-backup"></span></span>
				<div>
					<h2><?php esc_html_e( 'Import History', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'View past imports and undo them if needed.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'I / U / S / E', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Posts', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-history-body">
						<tr><td colspan="6"><?php esc_html_e( 'Loading…', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Scheduled Imports -->
		<div class="tsi-card" id="tsi-step-schedule" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-clock"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Imports', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Automatically import from a URL on a recurring schedule.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled imports.', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
				<div class="tsi-schedule-form">
					<h4><?php esc_html_e( 'Add New Schedule', 'the-simplest-importer' ); ?></h4>
					<div class="tsi-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'the-simplest-importer' ); ?>
							<input type="text" id="tsi-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My daily import', 'the-simplest-importer' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'CSV URL:', 'the-simplest-importer' ); ?>
							<input type="url" id="tsi-schedule-url" class="regular-text" placeholder="https://example.com/data.csv">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'the-simplest-importer' ); ?>
							<select id="tsi-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'the-simplest-importer' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'the-simplest-importer' ); ?></option>
								<option value="daily" selected><?php esc_html_e( 'Daily', 'the-simplest-importer' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Mapping Profile:', 'the-simplest-importer' ); ?>
							<select id="tsi-schedule-profile">
								<option value=""><?php esc_html_e( '— auto-match —', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'the-simplest-importer' ); ?>
							<input type="email" id="tsi-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'the-simplest-importer' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will use the currently selected post type.', 'the-simplest-importer' ); ?></p>
					<button type="button" id="tsi-btn-add-schedule" class="button button-primary"><?php esc_html_e( 'Add Schedule', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Scheduled Exports -->
		<div class="tsi-card" id="tsi-step-export-schedule" style="display:none">
			<div class="tsi-card-header">
				<span class="tsi-step-num"><span class="dashicons dashicons-download"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Exports', 'the-simplest-importer' ); ?></h2>
					<p><?php esc_html_e( 'Automatically export posts on a recurring schedule and optionally receive the file by email.', 'the-simplest-importer' ); ?></p>
				</div>
			</div>
			<div class="tsi-card-body">
				<table class="widefat tsi-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'the-simplest-importer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'the-simplest-importer' ); ?></th>
						</tr>
					</thead>
					<tbody id="tsi-export-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled exports.', 'the-simplest-importer' ); ?></td></tr>
					</tbody>
				</table>
				<div class="tsi-schedule-form">
					<h4><?php esc_html_e( 'Add New Export Schedule', 'the-simplest-importer' ); ?></h4>
					<div class="tsi-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'the-simplest-importer' ); ?>
							<input type="text" id="tsi-export-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My weekly export', 'the-simplest-importer' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'the-simplest-importer' ); ?>
							<select id="tsi-export-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'the-simplest-importer' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'the-simplest-importer' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'the-simplest-importer' ); ?></option>
								<option value="weekly" selected><?php esc_html_e( 'Weekly', 'the-simplest-importer' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'the-simplest-importer' ); ?>
							<input type="email" id="tsi-export-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'the-simplest-importer' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will export all posts of the currently selected post type. Files are stored in wp-content/uploads/tsi-exports/ and auto-deleted after 7 days.', 'the-simplest-importer' ); ?></p>
					<button type="button" id="tsi-btn-add-export-schedule" class="button button-primary"><?php esc_html_e( 'Add Export Schedule', 'the-simplest-importer' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Overlay spinner (export / template) -->
		<div id="tsi-overlay" class="tsi-overlay" style="display:none">
			<div class="tsi-overlay-inner">
				<span class="spinner is-active"></span>
				<span id="tsi-overlay-text"><?php esc_html_e( 'Processing…', 'the-simplest-importer' ); ?></span>
			</div>
		</div>

	</div>
	<?php
}

/* ------------------------------------------------------------------
 * AJAX — Get post types
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_get_post_types', 'tsi_ajax_get_post_types' );

/**
 * Return available post types with post counts.
 *
 * @return void
 */
function tsi_ajax_get_post_types() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$skip  = array(
		'attachment',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'wp_global_styles',
	);

	$result = array();
	foreach ( $types as $slug => $obj ) {
		if ( in_array( $slug, $skip, true ) ) {
			continue;
		}

		$counts = wp_count_posts( $slug );
		$total  = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $s ) {
			$total += isset( $counts->$s ) ? (int) $counts->$s : 0;
		}

		$max_id = 0;
		if ( $total > 0 ) {
			$latest = get_posts( array(
				'post_type'      => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			) );
			$max_id = ! empty( $latest ) ? (int) $latest[0] : 0;
		}

		$result[] = array(
			'slug'   => $slug,
			'label'  => $obj->labels->singular_name,
			'count'  => $total,
			'max_id' => $max_id,
		);
	}

	/**
	 * Filter the list of post types shown in the importer dropdown.
	 *
	 * @param array $result Array of post type data.
	 */
	$result = apply_filters( 'tsi_post_types', $result );

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Get fields for a post type
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_get_fields', 'tsi_ajax_get_fields' );

/**
 * Return importable fields for a given post type.
 *
 * @return void
 */
function tsi_ajax_get_fields() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	wp_send_json_success( tsi_get_post_type_fields( $post_type ) );
}

/**
 * Build the list of importable / exportable fields for a post type.
 *
 * @param string $post_type The post type slug.
 * @return array Associative array of field_key => label.
 */
function tsi_get_post_type_fields( $post_type ) {
	$fields = array(
		'ID'           => __( 'ID (update existing)', 'the-simplest-importer' ),
		'post_title'   => __( 'Title', 'the-simplest-importer' ),
		'post_content' => __( 'Content', 'the-simplest-importer' ),
		'post_excerpt' => __( 'Excerpt', 'the-simplest-importer' ),
		'post_status'  => __( 'Status', 'the-simplest-importer' ),
		'post_date'    => __( 'Date', 'the-simplest-importer' ),
		'post_name'    => __( 'Slug', 'the-simplest-importer' ),
		'post_author'  => __( 'Author ID', 'the-simplest-importer' ),
	);

	/* Taxonomies */
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	foreach ( $taxonomies as $tax_slug => $tax_obj ) {
		/* translators: %s: taxonomy singular name */
		$fields[ 'tax__' . $tax_slug ] = sprintf( __( 'Tax: %s', 'the-simplest-importer' ), $tax_obj->labels->singular_name );
	}

	/* Meta keys already in use */
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta key discovery; not cacheable.
	$meta_keys = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT pm.meta_key
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = %s
		   AND pm.meta_key NOT LIKE %s
		 ORDER BY pm.meta_key",
		$post_type,
		$wpdb->esc_like( '_edit_' ) . '%'
	) );

	$skip_meta = array( '_wp_old_slug', '_wp_attached_file', '_wp_attachment_metadata', '_pingme', '_encloseme' );
	foreach ( $meta_keys as $key ) {
		if ( in_array( $key, $skip_meta, true ) ) {
			continue;
		}
		/* translators: %s: meta key name */
		$fields[ 'meta__' . $key ] = sprintf( __( 'Meta: %s', 'the-simplest-importer' ), $key );
	}

	$fields['_thumbnail_url'] = __( 'Featured Image URL', 'the-simplest-importer' );

	/* SEO plugin fields — auto-detect Yoast SEO or Rank Math */
	if ( defined( 'WPSEO_VERSION' ) ) {
		$fields['meta___yoast_wpseo_title']    = __( 'SEO: Title (Yoast)', 'the-simplest-importer' );
		$fields['meta___yoast_wpseo_metadesc'] = __( 'SEO: Description (Yoast)', 'the-simplest-importer' );
		$fields['meta___yoast_wpseo_focuskw']  = __( 'SEO: Focus Keyword (Yoast)', 'the-simplest-importer' );
	}

	if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
		$fields['meta__rank_math_title']       = __( 'SEO: Title (Rank Math)', 'the-simplest-importer' );
		$fields['meta__rank_math_description'] = __( 'SEO: Description (Rank Math)', 'the-simplest-importer' );
		$fields['meta__rank_math_focus_keyword'] = __( 'SEO: Focus Keyword (Rank Math)', 'the-simplest-importer' );
	}

	/* ACF fields — auto-detect Advanced Custom Fields */
	if ( function_exists( 'acf_get_field_groups' ) ) {
		$acf_groups = acf_get_field_groups( array(
			'post_type' => $post_type,
		) );

		$acf_simple_types = array(
			'text', 'textarea', 'number', 'range', 'email', 'url', 'password',
			'wysiwyg', 'select', 'radio', 'true_false', 'date_picker',
			'date_time_picker', 'time_picker', 'color_picker',
		);

		foreach ( $acf_groups as $group ) {
			$acf_fields = acf_get_fields( $group );
			if ( ! is_array( $acf_fields ) ) {
				continue;
			}
			foreach ( $acf_fields as $acf_field ) {
				$key = 'meta__' . $acf_field['name'];
				/* Only add if not already discovered via meta query above */
				if ( isset( $fields[ $key ] ) ) {
					/* Upgrade label from raw meta to friendly ACF label */
					/* translators: %s: ACF field label */
					$fields[ $key ] = sprintf( __( 'ACF: %s', 'the-simplest-importer' ), $acf_field['label'] );
				} elseif ( in_array( $acf_field['type'], $acf_simple_types, true ) ) {
					/* translators: %s: ACF field label */
					$fields[ $key ] = sprintf( __( 'ACF: %s', 'the-simplest-importer' ), $acf_field['label'] );
				} elseif ( 'image' === $acf_field['type'] || 'file' === $acf_field['type'] ) {
					/* translators: 1: ACF field label, 2: field type */
					$fields[ $key ] = sprintf( __( 'ACF: %1$s (%2$s URL)', 'the-simplest-importer' ), $acf_field['label'], $acf_field['type'] );
				}
			}
		}
	}

	/**
	 * Filter the importable fields for a post type.
	 *
	 * @param array  $fields    Associative array of field_key => label.
	 * @param string $post_type The post type slug.
	 */
	return apply_filters( 'tsi_post_type_fields', $fields, $post_type );
}

/* ------------------------------------------------------------------
 * AJAX — Parse uploaded CSV (file)
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_parse_csv', 'tsi_ajax_parse_csv' );

/**
 * Parse an uploaded CSV file and store rows in a transient.
 *
 * @return void
 */
function tsi_ajax_parse_csv() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	if ( empty( $_FILES['csv_file'] ) ) {
		wp_send_json_error( esc_html__( 'No file uploaded.', 'the-simplest-importer' ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array handled by PHP/WP upload processing.
	$file = $_FILES['csv_file'];

	$ext = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
	if ( 'csv' !== $ext ) {
		wp_send_json_error( esc_html__( 'Only .csv files are allowed.', 'the-simplest-importer' ) );
	}

	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$mime  = finfo_file( $finfo, $file['tmp_name'] );
	finfo_close( $finfo );

	$allowed = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		wp_send_json_error( esc_html__( 'Invalid file type.', 'the-simplest-importer' ) );
	}

	$result = tsi_read_csv_file( $file['tmp_name'] );
	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/* ------------------------------------------------------------------
 * AJAX — Parse CSV from a URL
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_parse_csv_url', 'tsi_ajax_parse_csv_url' );

/**
 * Fetch a remote CSV by URL and parse it.
 *
 * @return void
 */
function tsi_ajax_parse_csv_url() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$url = isset( $_POST['csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['csv_url'] ) ) : '';
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		wp_send_json_error( esc_html__( 'Invalid URL.', 'the-simplest-importer' ) );
	}

	/* Auto-convert Google Sheets URL to CSV export URL */
	$url = tsi_convert_google_sheets_url( $url );

	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			/* translators: %s: HTTP error message */
			sprintf( esc_html__( 'Failed to fetch: %s', 'the-simplest-importer' ), $response->get_error_message() )
		);
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		wp_send_json_error( esc_html__( 'Empty response from URL.', 'the-simplest-importer' ) );
	}

	$tmp = wp_tempnam( 'tsi_csv_' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for CSV parsing.
	file_put_contents( $tmp, $body );

	$result = tsi_read_csv_file( $tmp );
	wp_delete_file( $tmp );

	if ( is_string( $result ) ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

/**
 * Read a CSV file, store data in a transient, and return parsed info.
 *
 * Automatically detects the delimiter (comma, semicolon, tab, pipe).
 *
 * @param string $filepath Absolute path to the CSV file.
 * @return array|string Parsed data array on success, error message on failure.
 */
function tsi_read_csv_file( $filepath ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fopen needed for fgetcsv streaming.
	$handle = fopen( $filepath, 'r' );
	if ( ! $handle ) {
		return esc_html__( 'Cannot read file.', 'the-simplest-importer' );
	}

	/* Skip BOM */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- fread needed for BOM detection on fgetcsv stream.
	$bom = fread( $handle, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $handle );
	}

	/* Auto-detect delimiter by reading the first line */
	$first_line_pos = ftell( $handle );
	$first_line     = fgets( $handle );
	fseek( $handle, $first_line_pos );

	$delimiter  = ',';
	$delimiters = array( ',' => 0, ';' => 0, "\t" => 0, '|' => 0 );
	if ( $first_line ) {
		foreach ( $delimiters as $d => &$count ) {
			$count = substr_count( $first_line, $d );
		}
		unset( $count );
		arsort( $delimiters );
		$best = key( $delimiters );
		if ( $delimiters[ $best ] > 0 ) {
			$delimiter = $best;
		}
	}

	$headers = fgetcsv( $handle, 0, $delimiter );
	if ( ! $headers ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
		fclose( $handle );
		return esc_html__( 'Empty CSV or invalid format.', 'the-simplest-importer' );
	}

	$headers = array_map( 'trim', $headers );

	$rows = array();
	while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
		if ( count( $row ) === 1 && '' === trim( (string) $row[0] ) ) {
			continue; // Skip blank rows.
		}
		$rows[] = $row;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing fgetcsv stream handle.
	fclose( $handle );

	if ( empty( $rows ) ) {
		return esc_html__( 'CSV file contains headers but no data rows.', 'the-simplest-importer' );
	}

	/**
	 * Filter parsed CSV data before storing in transient.
	 *
	 * @param array $csv_data {
	 *     @type array  $headers   Column headers.
	 *     @type array  $rows      All data rows.
	 *     @type string $delimiter Detected delimiter character.
	 * }
	 */
	$csv_data = apply_filters( 'tsi_csv_parsed', array(
		'headers'   => $headers,
		'rows'      => $rows,
		'delimiter' => $delimiter,
	) );

	$headers   = $csv_data['headers'];
	$rows      = $csv_data['rows'];
	$delimiter = $csv_data['delimiter'];

	$token = wp_generate_password( 20, false );
	set_transient( 'tsi_csv_data_' . $token, $csv_data, HOUR_IN_SECONDS );

	$delimiter_labels = array(
		','  => __( 'comma', 'the-simplest-importer' ),
		';'  => __( 'semicolon', 'the-simplest-importer' ),
		"\t" => __( 'tab', 'the-simplest-importer' ),
		'|'  => __( 'pipe', 'the-simplest-importer' ),
	);

	return array(
		'headers'   => $headers,
		'row_count' => count( $rows ),
		'preview'   => array_slice( $rows, 0, 5 ),
		'token'     => $token,
		'delimiter' => isset( $delimiter_labels[ $delimiter ] ) ? $delimiter_labels[ $delimiter ] : $delimiter,
	);
}

/**
 * Convert a Google Sheets URL to its CSV export equivalent.
 *
 * Accepts formats:
 *   https://docs.google.com/spreadsheets/d/{ID}/edit#gid=0
 *   https://docs.google.com/spreadsheets/d/{ID}/edit?...
 *   https://docs.google.com/spreadsheets/d/{ID}/pub?...
 *   https://docs.google.com/spreadsheets/d/e/{PUBID}/pub?output=csv
 *
 * If the URL is not a Google Sheets URL, returns it unchanged.
 *
 * @param string $url The original URL.
 * @return string The CSV export URL, or original if not a Google Sheets URL.
 */
function tsi_convert_google_sheets_url( $url ) {
	/* Already a CSV export URL */
	if ( preg_match( '/output=csv/', $url ) ) {
		return $url;
	}

	/* Standard Google Sheets URL: /spreadsheets/d/{ID}/... */
	if ( preg_match( '#docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $matches ) ) {
		$sheet_id = $matches[1];

		/* Extract gid if present */
		$gid = '0';
		if ( preg_match( '/gid=(\d+)/', $url, $gid_matches ) ) {
			$gid = $gid_matches[1];
		}

		return 'https://docs.google.com/spreadsheets/d/' . $sheet_id . '/export?format=csv&gid=' . $gid;
	}

	/* Published Google Sheets URL: /spreadsheets/d/e/{PUBID}/pub */
	if ( preg_match( '#docs\.google\.com/spreadsheets/d/e/([a-zA-Z0-9_-]+)/pub#', $url, $matches ) ) {
		$pub_id = $matches[1];

		$gid = '0';
		if ( preg_match( '/gid=(\d+)/', $url, $gid_matches ) ) {
			$gid = $gid_matches[1];
		}

		return 'https://docs.google.com/spreadsheets/d/e/' . $pub_id . '/pub?output=csv&gid=' . $gid;
	}

	return $url;
}

/* ------------------------------------------------------------------
 * AJAX — Export posts as CSV
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_export', 'tsi_ajax_export' );

/**
 * Export all posts of a given type as a base64-encoded CSV.
 *
 * @return void
 */
function tsi_ajax_export() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$export_mode = isset( $_POST['export_mode'] ) ? sanitize_key( wp_unslash( $_POST['export_mode'] ) ) : 'all';

	/* Status filter */
	$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
	$export_statuses  = array();
	if ( ! empty( $_POST['export_statuses'] ) && is_array( $_POST['export_statuses'] ) ) {
		foreach ( $_POST['export_statuses'] as $s ) {
			$s = sanitize_key( $s );
			if ( in_array( $s, $allowed_statuses, true ) ) {
				$export_statuses[] = $s;
			}
		}
	}

	/* Selective columns */
	$selected_fields = array();
	if ( ! empty( $_POST['export_fields'] ) && is_array( $_POST['export_fields'] ) ) {
		foreach ( $_POST['export_fields'] as $f ) {
			$selected_fields[] = sanitize_text_field( wp_unslash( $f ) );
		}
	}

	$query_args = array(
		'post_type'        => $post_type,
		'post_status'      => ! empty( $export_statuses ) ? $export_statuses : 'any',
		'posts_per_page'   => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => false,
	);

	if ( 'rows' === $export_mode ) {
		$row_from = isset( $_POST['row_from'] ) ? absint( $_POST['row_from'] ) : 1;
		$row_to   = isset( $_POST['row_to'] ) ? absint( $_POST['row_to'] ) : 0;

		if ( $row_from < 1 ) {
			$row_from = 1;
		}

		$query_args['offset'] = $row_from - 1;
		if ( $row_to >= $row_from ) {
			$query_args['posts_per_page'] = $row_to - $row_from + 1;
		}
	} elseif ( 'range' === $export_mode ) {
		$id_from = isset( $_POST['id_from'] ) ? absint( $_POST['id_from'] ) : 0;
		$id_to   = isset( $_POST['id_to'] ) ? absint( $_POST['id_to'] ) : 0;

		if ( $id_from || $id_to ) {
			$query_args['tsi_id_from'] = $id_from;
			$query_args['tsi_id_to']   = $id_to;

			add_filter( 'posts_where', 'tsi_filter_export_id_range', 10, 2 );
		}
	} elseif ( 'dates' === $export_mode ) {
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$date_query = array();
		if ( $date_from ) {
			$date_query['after'] = $date_from;
		}
		if ( $date_to ) {
			$date_query['before']    = $date_to;
			$date_query['inclusive'] = true;
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive']  = true;
			$query_args['date_query'] = array( $date_query );
		}
	}

	$posts = get_posts( $query_args );

	remove_filter( 'posts_where', 'tsi_filter_export_id_range', 10 );

	if ( empty( $posts ) ) {
		wp_send_json_error( esc_html__( 'No posts found for this post type.', 'the-simplest-importer' ) );
	}

	$fields    = tsi_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author' );

	/**
	 * Filter the list of columns included in an export.
	 *
	 * @param array  $fields    Associative array of field_key => label.
	 * @param string $post_type The post type being exported.
	 */
	$fields = apply_filters( 'tsi_export_columns', $fields, $post_type );

	/* Apply selective field filter if specified */
	if ( ! empty( $selected_fields ) ) {
		$fields    = array_intersect_key( $fields, array_flip( $selected_fields ) );
		$core_keys = array_intersect( $core_keys, $selected_fields );
	}

	$meta_fields = array();
	$tax_fields  = array();
	foreach ( $fields as $key => $label ) {
		if ( 0 === strpos( $key, 'meta__' ) ) {
			$meta_fields[] = substr( $key, 6 );
		} elseif ( 0 === strpos( $key, 'tax__' ) ) {
			$tax_fields[] = substr( $key, 5 );
		}
	}

	$header = $core_keys;
	foreach ( $tax_fields as $t ) {
		$header[] = 'tax__' . $t;
	}
	foreach ( $meta_fields as $m ) {
		$header[] = 'meta__' . $m;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $header );

	foreach ( $posts as $p ) {
		$row = array();
		foreach ( $core_keys as $ck ) {
			$row[] = isset( $p->$ck ) ? $p->$ck : '';
		}
		foreach ( $tax_fields as $t ) {
			$terms = wp_get_object_terms( $p->ID, $t, array( 'fields' => 'names' ) );
			$row[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
		}
		foreach ( $meta_fields as $m ) {
			$val   = get_post_meta( $p->ID, $m, true );
			$row[] = is_array( $val ) ? wp_json_encode( $val ) : ( isset( $val ) ? (string) $val : '' );
		}
		/**
		 * Filter a single export row before writing to CSV.
		 *
		 * @param array   $row    The row values.
		 * @param WP_Post $p      The post object.
		 * @param array   $header The CSV header row.
		 */
		$row = apply_filters( 'tsi_export_row', $row, $p, $header );

		fputcsv( $output, $row );
	}

	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	/**
	 * Fires after an export completes.
	 *
	 * @param string $post_type  The exported post type.
	 * @param int    $post_count Number of posts exported.
	 */
	do_action( 'tsi_export_completed', $post_type, count( $posts ) );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d' ) . '.csv' ),
	) );
}

/**
 * Filter posts_where to constrain by ID range during export.
 *
 * @param string   $where    The WHERE clause.
 * @param WP_Query $wp_query The query object.
 * @return string
 */
function tsi_filter_export_id_range( $where, $wp_query ) {
	global $wpdb;

	$id_from = $wp_query->get( 'tsi_id_from' );
	$id_to   = $wp_query->get( 'tsi_id_to' );

	if ( $id_from ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", $id_from );
	}
	if ( $id_to ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", $id_to );
	}

	return $where;
}

/* ------------------------------------------------------------------
 * AJAX — Export blank template CSV
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_template', 'tsi_ajax_template' );

/**
 * Generate a blank CSV template for a given post type.
 *
 * @return void
 */
function tsi_ajax_template() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$fields = tsi_get_post_type_fields( $post_type );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array_keys( $fields ) );
	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-template.csv' ),
	) );
}

/* ------------------------------------------------------------------
 * AJAX — Batch import
 * ------------------------------------------------------------------ */

add_action( 'wp_ajax_tsi_import_batch', 'tsi_ajax_import_batch' );

/**
 * Process a batch of CSV rows for import.
 *
 * Expects: token, post_type, mapping (JSON), offset, batch_size.
 * Returns partial results so the client can call repeatedly until done.
 *
 * @return void
 */
function tsi_ajax_import_batch() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$post_type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 50;
	$mapping    = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and keys sanitized below.
	$dry_run    = ! empty( $_POST['dry_run'] );
	$dup_field  = isset( $_POST['dup_field'] ) ? sanitize_key( wp_unslash( $_POST['dup_field'] ) ) : '';
	$dup_meta   = isset( $_POST['dup_meta_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dup_meta_key'] ) ) : '';
	$transforms = isset( $_POST['transforms'] ) ? wp_unslash( $_POST['transforms'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.
	$import_mode = isset( $_POST['import_mode'] ) ? sanitize_key( wp_unslash( $_POST['import_mode'] ) ) : 'insert';
	$history_id  = isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( $_POST['history_id'] ) ) : '';
	$filters_raw = isset( $_POST['filters'] ) ? wp_unslash( $_POST['filters'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $token || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Missing parameters.', 'the-simplest-importer' ) );
	}

	if ( ! post_type_exists( $post_type ) ) {
		wp_send_json_error( esc_html__( 'Invalid post type.', 'the-simplest-importer' ) );
	}

	$batch_size = min( max( $batch_size, 1 ), 200 );

	$data = get_transient( 'tsi_csv_data_' . $token );
	if ( ! $data ) {
		wp_send_json_error( esc_html__( 'CSV data expired. Please re-upload the file.', 'the-simplest-importer' ) );
	}

	$headers = $data['headers'];
	$total   = count( $data['rows'] );
	$rows    = array_slice( $data['rows'], $offset, $batch_size );

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	$clean_map = tsi_sanitize_mapping( $mapping, count( $headers ) );

	$transforms = json_decode( $transforms, true );
	if ( ! is_array( $transforms ) ) {
		$transforms = array();
	}

	$filters = json_decode( $filters_raw, true );
	if ( ! is_array( $filters ) ) {
		$filters = array();
	}
	/* Sanitize filter rules */
	$clean_filters = array();
	$allowed_ops   = array( 'equals', 'not_equals', 'contains', 'not_contains', 'gt', 'lt', 'empty', 'not_empty' );
	foreach ( $filters as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		$col = isset( $rule['col'] ) ? absint( $rule['col'] ) : 0;
		$op  = isset( $rule['op'] ) ? sanitize_key( $rule['op'] ) : '';
		$val = isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '';
		if ( in_array( $op, $allowed_ops, true ) ) {
			$clean_filters[] = array(
				'col'   => $col,
				'op'    => $op,
				'value' => $val,
			);
		}
	}

	$log      = array();
	$inserted = 0;
	$updated  = 0;
	$skipped  = 0;
	$errors   = 0;
	$post_ids = array();

	foreach ( $rows as $i => $row ) {
		$row_num = $offset + $i + 2; // +2: header is row 1, data starts at row 2.

		/* Conditional row filtering — skip rows that don't match all rules */
		if ( ! empty( $clean_filters ) ) {
			$pass = tsi_row_matches_filters( $row, $clean_filters );

			/**
			 * Filter whether a row passes the import filter rules.
			 *
			 * @param bool  $pass    Whether the row passed.
			 * @param array $row     CSV row values.
			 * @param array $filters The filter rules.
			 * @param int   $row_num Row number.
			 */
			$pass = apply_filters( 'tsi_import_row_filter', $pass, $row, $clean_filters, $row_num );

			if ( ! $pass ) {
				$skipped++;
				/* translators: %d: row number */
				$log[] = sprintf( __( 'Row %d: Filtered — did not match filter rules.', 'the-simplest-importer' ), $row_num );
				continue;
			}
		}

		$result  = tsi_import_single_row( $row, $row_num, $post_type, $clean_map, $dry_run, $dup_field, $dup_meta, $transforms );

		$log[] = $result['message'];
		if ( ! empty( $result['post_id'] ) ) {
			$post_ids[] = $result['post_id'];
		}
		switch ( $result['status'] ) {
			case 'inserted':
				$inserted++;
				break;
			case 'updated':
				$updated++;
				break;
			case 'skipped':
				$skipped++;
				break;
			default:
				$errors++;
				break;
		}
	}

	$next_offset = $offset + count( $rows );
	$done        = $next_offset >= $total;

	if ( $done ) {
		delete_transient( 'tsi_csv_data_' . $token );

		/* Record import in history (skip for dry runs) */
		if ( ! $dry_run && ( $inserted > 0 || $updated > 0 ) ) {
			if ( ! $history_id ) {
				$history_id = wp_generate_password( 12, false );
			}
			tsi_record_import_history( $history_id, $post_type, $import_mode, $inserted, $updated, $skipped, $errors, $post_ids );
		}

		/**
		 * Fires when a full import is complete (last batch).
		 *
		 * @param string $post_type The imported post type.
		 * @param array  $stats     { inserted, updated, skipped, errors, post_ids, dry_run, history_id }
		 */
		do_action( 'tsi_import_completed', $post_type, array(
			'inserted'   => $inserted,
			'updated'    => $updated,
			'skipped'    => $skipped,
			'errors'     => $errors,
			'post_ids'   => $post_ids,
			'dry_run'    => $dry_run,
			'history_id' => $history_id,
		) );
	}

	wp_send_json_success( array(
		'offset'     => $next_offset,
		'total'      => $total,
		'done'       => $done,
		'log'        => $log,
		'inserted'   => $inserted,
		'updated'    => $updated,
		'skipped'    => $skipped,
		'errors'     => $errors,
		'post_ids'   => $post_ids,
		'history_id' => $history_id ? $history_id : '',
		'dry_run'    => $dry_run,
	) );
}

/* ------------------------------------------------------------------
 * Helpers — Mapping & Row Import
 * ------------------------------------------------------------------ */

/**
 * Sanitize the mapping array received from the client.
 *
 * @param array $mapping  Raw mapping from JSON decode.
 * @param int   $col_max  Number of CSV columns (for bounds checking).
 * @return array Sanitized mapping.
 */
function tsi_sanitize_mapping( $mapping, $col_max ) {
	$clean = array();

	foreach ( $mapping as $field => $info ) {
		$field = sanitize_text_field( $field );
		if ( ! is_array( $info ) ) {
			continue;
		}

		$source = isset( $info['source'] ) ? sanitize_text_field( $info['source'] ) : 'csv';

		if ( 'custom' === $source ) {
			$clean[ $field ] = array(
				'source' => 'custom',
				'value'  => sanitize_text_field( isset( $info['value'] ) ? $info['value'] : '' ),
			);
		} else {
			$col = isset( $info['col'] ) ? absint( $info['col'] ) : 0;
			if ( $col >= $col_max ) {
				continue;
			}
			$clean[ $field ] = array(
				'source' => 'csv',
				'col'    => $col,
			);
		}
	}

	return $clean;
}

/**
 * Import a single CSV row: insert or update one post.
 *
 * @param array  $row        The CSV row values.
 * @param int    $row_num    Human-readable row number (for logs).
 * @param string $post_type  Target post type.
 * @param array  $mapping    Sanitized mapping array.
 * @param bool   $dry_run    If true, skip actual insert/update.
 * @param string $dup_field  Field to check for duplicates (post_title, post_name, meta_key).
 * @param string $dup_meta   Meta key name when dup_field is 'meta_key'.
 * @param array  $transforms Transforms to apply per field.
 * @return array { status: string, message: string, post_id: int }
 */
function tsi_import_single_row( $row, $row_num, $post_type, $mapping, $dry_run = false, $dup_field = '', $dup_meta = '', $transforms = array() ) {
	$post_data = array( 'post_type' => $post_type );
	$meta_data = array();
	$tax_data  = array();
	$thumb_url = '';

	/**
	 * Fires before a single CSV row is imported.
	 *
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 * @param string $post_type Target post type.
	 * @param array  $mapping   Sanitized mapping array.
	 */
	do_action( 'tsi_before_import_row', $row, $row_num, $post_type, $mapping );

	foreach ( $mapping as $field => $info ) {
		$value = ( 'custom' === $info['source'] )
			? $info['value']
			: ( isset( $row[ $info['col'] ] ) ? $row[ $info['col'] ] : '' );

		/* Apply transform if set */
		if ( ! empty( $transforms[ $field ] ) ) {
			$value = tsi_apply_transform( $value, $transforms[ $field ] );
		}

		if ( 'ID' === $field ) {
			$post_data['ID'] = absint( $value );
		} elseif ( 'post_title' === $field ) {
			$post_data['post_title'] = sanitize_text_field( $value );
		} elseif ( 'post_content' === $field ) {
			$post_data['post_content'] = wp_kses_post( $value );
		} elseif ( 'post_excerpt' === $field ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $value );
		} elseif ( 'post_status' === $field ) {
			$allowed = array( 'publish', 'draft', 'pending', 'private', 'future' );
			$post_data['post_status'] = in_array( $value, $allowed, true ) ? $value : 'draft';
		} elseif ( 'post_date' === $field ) {
			$post_data['post_date'] = sanitize_text_field( $value );
		} elseif ( 'post_name' === $field ) {
			$post_data['post_name'] = sanitize_title( $value );
		} elseif ( 'post_author' === $field ) {
			$author_id = absint( $value );
			if ( $author_id && get_userdata( $author_id ) ) {
				$post_data['post_author'] = $author_id;
			}
		} elseif ( '_thumbnail_url' === $field ) {
			$thumb_url = esc_url_raw( $value );
		} elseif ( 0 === strpos( $field, 'tax__' ) ) {
			$tax_slug = sanitize_key( substr( $field, 5 ) );
			$terms    = array_map( 'trim', explode( ',', $value ) );
			$terms    = array_filter( $terms );
			if ( ! empty( $terms ) ) {
				$tax_data[ $tax_slug ] = $terms;
			}
		} elseif ( 0 === strpos( $field, 'meta__' ) ) {
			$meta_key              = sanitize_text_field( substr( $field, 6 ) );
			$meta_data[ $meta_key ] = sanitize_text_field( $value );
		}
	}

	/**
	 * Filter the row data array before inserting or updating a post.
	 *
	 * @param array  $post_data Post data for wp_insert_post / wp_update_post.
	 * @param array  $meta_data Meta key => value pairs.
	 * @param array  $tax_data  Taxonomy slug => terms pairs.
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 */
	$import_row_data = apply_filters( 'tsi_import_row_data', array(
		'post_data' => $post_data,
		'meta_data' => $meta_data,
		'tax_data'  => $tax_data,
		'thumb_url' => $thumb_url,
	), $row, $row_num, $post_type );

	$post_data = $import_row_data['post_data'];
	$meta_data = $import_row_data['meta_data'];
	$tax_data  = $import_row_data['tax_data'];
	$thumb_url = $import_row_data['thumb_url'];

	/* Determine insert vs. update */
	$is_update = false;
	if ( ! empty( $post_data['ID'] ) ) {
		$existing = get_post( $post_data['ID'] );
		if ( $existing && $existing->post_type === $post_type ) {
			$is_update = true;
		} elseif ( $existing ) {
			return array(
				'status'  => 'skipped',
				'post_id' => 0,
				/* translators: 1: row number, 2: post ID */
				'message' => sprintf( __( 'Row %1$d: Skipped — ID %2$d belongs to a different post type.', 'the-simplest-importer' ), $row_num, $post_data['ID'] ),
			);
		} else {
			unset( $post_data['ID'] ); // ID not found; insert as new.
		}
	}

	/* Duplicate detection (only for inserts) */
	if ( ! $is_update && $dup_field ) {
		$dup_found = tsi_check_duplicate( $post_type, $dup_field, $dup_meta, $post_data, $meta_data );
		if ( $dup_found ) {
			return array(
				'status'  => 'skipped',
				'post_id' => 0,
				/* translators: 1: row number, 2: post ID */
				'message' => sprintf( __( 'Row %1$d: Skipped — duplicate found (post #%2$d).', 'the-simplest-importer' ), $row_num, $dup_found ),
			);
		}
	}

	if ( ! $is_update && empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = 'draft';
	}

	/* Dry run — report what would happen without writing */
	if ( $dry_run ) {
		$action_label = $is_update
			/* translators: 1: row number, 2: post ID */
			? sprintf( __( 'Row %1$d: Would update post #%2$d', 'the-simplest-importer' ), $row_num, $post_data['ID'] )
			/* translators: %d: row number */
			: sprintf( __( 'Row %d: Would insert new post', 'the-simplest-importer' ), $row_num );
		return array(
			'status'  => $is_update ? 'updated' : 'inserted',
			'post_id' => 0,
			'message' => $action_label . ' [' . __( 'DRY RUN', 'the-simplest-importer' ) . ']',
		);
	}

	$result = $is_update
		? wp_update_post( $post_data, true )
		: wp_insert_post( $post_data, true );

	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			'post_id' => 0,
			/* translators: 1: row number, 2: error message */
			'message' => sprintf( __( 'Row %1$d: Error — %2$s', 'the-simplest-importer' ), $row_num, $result->get_error_message() ),
		);
	}

	$post_id = $result;

	foreach ( $meta_data as $key => $val ) {
		/*
		 * Use ACF's update_field() when available for ACF-registered fields.
		 * This ensures proper storage for ACF field types (select, true_false, etc.).
		 */
		if ( function_exists( 'acf_get_field' ) && function_exists( 'update_field' ) ) {
			$acf_field = acf_get_field( $key );
			if ( $acf_field ) {
				update_field( $key, $val, $post_id );
				continue;
			}
		}
		update_post_meta( $post_id, $key, $val );
	}

	foreach ( $tax_data as $tax => $terms ) {
		if ( taxonomy_exists( $tax ) ) {
			wp_set_object_terms( $post_id, $terms, $tax );
		}
	}

	if ( $thumb_url ) {
		tsi_set_featured_image( $post_id, $thumb_url );
	}

	/**
	 * Fires after a single CSV row has been imported.
	 *
	 * @param int    $post_id   The imported post ID.
	 * @param array  $row       Original CSV row values.
	 * @param int    $row_num   Row number in the CSV.
	 * @param bool   $is_update Whether this was an update.
	 */
	do_action( 'tsi_after_import_row', $post_id, $row, $row_num, $is_update );

	if ( $is_update ) {
		return array(
			'status'  => 'updated',
			'post_id' => $post_id,
			/* translators: 1: row number, 2: post ID */
			'message' => sprintf( __( 'Row %1$d: Updated post #%2$d', 'the-simplest-importer' ), $row_num, $post_id ),
		);
	}

	return array(
		'status'  => 'inserted',
		'post_id' => $post_id,
		/* translators: 1: row number, 2: post ID */
		'message' => sprintf( __( 'Row %1$d: Inserted new post #%2$d', 'the-simplest-importer' ), $row_num, $post_id ),
	);
}

/* ------------------------------------------------------------------
 * Helper — Set featured image from URL
 * ------------------------------------------------------------------ */

/**
 * Download an image from a URL and set it as a post's featured image.
 *
 * @param int    $post_id The post to attach the image to.
 * @param string $url     The image URL.
 * @return void
 */
function tsi_set_featured_image( $post_id, $url ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return;
	}

	$file_array = array(
		'name'     => sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
		'tmp_name' => $tmp,
	);

	$attach_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $attach_id ) ) {
		wp_delete_file( $tmp );
		return;
	}

	set_post_thumbnail( $post_id, $attach_id );
}

/* ------------------------------------------------------------------
 * Helper — Apply field transform
 * ------------------------------------------------------------------ */

/**
 * Apply a named transform to a value during import.
 *
 * @param string $value     The raw value.
 * @param string $transform The transform key.
 * @return string Transformed value.
 */
function tsi_apply_transform( $value, $transform ) {
	switch ( $transform ) {
		case 'uppercase':
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value ) : strtoupper( $value );
		case 'lowercase':
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		case 'titlecase':
			return function_exists( 'mb_convert_case' ) ? mb_convert_case( $value, MB_CASE_TITLE ) : ucwords( strtolower( $value ) );
		case 'trim':
			return trim( $value );
		case 'strip_tags':
			return wp_strip_all_tags( $value );
		case 'slug':
			return sanitize_title( $value );
		case 'date_ymd':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'Y-m-d', $ts ) : $value;
		case 'date_dmy':
			$ts = strtotime( $value );
			return $ts ? gmdate( 'd/m/Y', $ts ) : $value;
		default:
			return $value;
	}
}

/* ------------------------------------------------------------------
 * Helper — Duplicate detection
 * ------------------------------------------------------------------ */

/**
 * Check if a post already exists based on a given field.
 *
 * @param string $post_type Target post type.
 * @param string $field     The field to check (post_title, post_name, meta_key).
 * @param string $meta_key  Meta key name when field is 'meta_key'.
 * @param array  $post_data Post data array being imported.
 * @param array  $meta_data Meta data array being imported.
 * @return int Found post ID, or 0.
 */
function tsi_check_duplicate( $post_type, $field, $meta_key, $post_data, $meta_data ) {
	global $wpdb;

	if ( 'post_title' === $field && ! empty( $post_data['post_title'] ) ) {
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status != 'trash' LIMIT 1",
				$post_type,
				$post_data['post_title']
			)
		);
		return $found ? (int) $found : 0;
	}

	if ( 'post_name' === $field && ! empty( $post_data['post_name'] ) ) {
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status != 'trash' LIMIT 1",
				$post_type,
				$post_data['post_name']
			)
		);
		return $found ? (int) $found : 0;
	}

	if ( 'meta_key' === $field && $meta_key && ! empty( $meta_data[ $meta_key ] ) ) {
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value = %s AND p.post_status != 'trash'
				LIMIT 1",
				$post_type,
				$meta_key,
				$meta_data[ $meta_key ]
			)
		);
		return $found ? (int) $found : 0;
	}

	return 0;
}

/* ------------------------------------------------------------------
 * Helper — Row filter evaluation
 * ------------------------------------------------------------------ */

/**
 * Check if a CSV row matches all filter rules (AND logic).
 *
 * @param array $row     The CSV row values.
 * @param array $filters Array of filter rules { col, op, value }.
 * @return bool True if the row matches all rules.
 */
function tsi_row_matches_filters( $row, $filters ) {
	foreach ( $filters as $rule ) {
		$cell = isset( $row[ $rule['col'] ] ) ? trim( (string) $row[ $rule['col'] ] ) : '';
		$val  = $rule['value'];

		switch ( $rule['op'] ) {
			case 'equals':
				if ( $cell !== $val ) {
					return false;
				}
				break;
			case 'not_equals':
				if ( $cell === $val ) {
					return false;
				}
				break;
			case 'contains':
				if ( false === stripos( $cell, $val ) ) {
					return false;
				}
				break;
			case 'not_contains':
				if ( false !== stripos( $cell, $val ) ) {
					return false;
				}
				break;
			case 'gt':
				if ( (float) $cell <= (float) $val ) {
					return false;
				}
				break;
			case 'lt':
				if ( (float) $cell >= (float) $val ) {
					return false;
				}
				break;
			case 'empty':
				if ( '' !== $cell ) {
					return false;
				}
				break;
			case 'not_empty':
				if ( '' === $cell ) {
					return false;
				}
				break;
		}
	}

	return true;
}

/* ------------------------------------------------------------------
 * Helper — Import history CRUD
 * ------------------------------------------------------------------ */

/**
 * Record an import in history.
 *
 * @param string $history_id Unique import ID.
 * @param string $post_type  Post type imported.
 * @param string $mode       Import mode (insert, update, insert_update).
 * @param int    $inserted   Inserted count.
 * @param int    $updated    Updated count.
 * @param int    $skipped    Skipped count.
 * @param int    $errors     Error count.
 * @param array  $post_ids   Array of affected post IDs.
 * @return void
 */
function tsi_record_import_history( $history_id, $post_type, $mode, $inserted, $updated, $skipped, $errors, $post_ids ) {
	$history = get_option( TSI_HISTORY_OPTION, array() );

	$history[ $history_id ] = array(
		'id'        => $history_id,
		'date'      => current_time( 'mysql' ),
		'post_type' => $post_type,
		'mode'      => $mode,
		'inserted'  => $inserted,
		'updated'   => $updated,
		'skipped'   => $skipped,
		'errors'    => $errors,
		'post_ids'  => $post_ids,
	);

	/* Keep only last 50 entries */
	if ( count( $history ) > 50 ) {
		$history = array_slice( $history, -50, 50, true );
	}

	update_option( TSI_HISTORY_OPTION, $history, false );
}

/**
 * Get the import history array.
 *
 * @return array
 */
function tsi_get_import_history() {
	return get_option( TSI_HISTORY_OPTION, array() );
}

/* ------------------------------------------------------------------
 * Helper — Mapping profiles CRUD
 * ------------------------------------------------------------------ */

/**
 * Get saved mapping profiles.
 *
 * @return array
 */
function tsi_get_mapping_profiles() {
	return get_option( TSI_PROFILES_OPTION, array() );
}

/* ------------------------------------------------------------------
 * Helper — Scheduled imports CRUD
 * ------------------------------------------------------------------ */

/**
 * Get scheduled imports list.
 *
 * @return array
 */
function tsi_get_scheduled_imports() {
	return get_option( TSI_SCHEDULES_OPTION, array() );
}

/* ------------------------------------------------------------------
 * AJAX — Save mapping profile
 * ------------------------------------------------------------------ */

/**
 * Save a mapping profile.
 */
function tsi_ajax_save_profile() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$name      = isset( $_POST['profile_name'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_name'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$mapping   = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $name ) {
		wp_send_json_error( esc_html__( 'Profile name is required.', 'the-simplest-importer' ) );
	}

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	/* Sanitize mapping keys and values */
	$clean = array();
	foreach ( $mapping as $field => $info ) {
		$field = sanitize_text_field( $field );
		if ( is_array( $info ) ) {
			$clean[ $field ] = array_map( 'sanitize_text_field', $info );
		}
	}

	$profiles = tsi_get_mapping_profiles();
	$id       = sanitize_title( $name . '-' . $post_type );

	$profiles[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'post_type' => $post_type,
		'mapping'   => $clean,
	);

	update_option( TSI_PROFILES_OPTION, $profiles, false );

	wp_send_json_success( array(
		'profiles' => $profiles,
		/* translators: %s: profile name */
		'message'  => sprintf( esc_html__( 'Profile "%s" saved.', 'the-simplest-importer' ), $name ),
	) );
}
add_action( 'wp_ajax_tsi_save_profile', 'tsi_ajax_save_profile' );

/* ------------------------------------------------------------------
 * AJAX — Delete mapping profile
 * ------------------------------------------------------------------ */

/**
 * Delete a mapping profile.
 */
function tsi_ajax_delete_profile() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$id = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing profile ID.', 'the-simplest-importer' ) );
	}

	$profiles = tsi_get_mapping_profiles();
	if ( isset( $profiles[ $id ] ) ) {
		unset( $profiles[ $id ] );
		update_option( TSI_PROFILES_OPTION, $profiles, false );
	}

	wp_send_json_success( array(
		'profiles' => $profiles,
		'message'  => esc_html__( 'Profile deleted.', 'the-simplest-importer' ),
	) );
}
add_action( 'wp_ajax_tsi_delete_profile', 'tsi_ajax_delete_profile' );

/* ------------------------------------------------------------------
 * AJAX — Validate CSV data
 * ------------------------------------------------------------------ */

/**
 * Validate CSV data against the selected post type fields.
 */
function tsi_ajax_validate_csv() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$mapping   = isset( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized below.

	if ( ! $token || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Missing parameters.', 'the-simplest-importer' ) );
	}

	$data = get_transient( 'tsi_csv_data_' . $token );
	if ( ! $data ) {
		wp_send_json_error( esc_html__( 'CSV data expired.', 'the-simplest-importer' ) );
	}

	$mapping = json_decode( $mapping, true );
	if ( ! is_array( $mapping ) ) {
		wp_send_json_error( esc_html__( 'Invalid mapping data.', 'the-simplest-importer' ) );
	}

	$mapping = tsi_sanitize_mapping( $mapping, count( $data['headers'] ) );

	$warnings = array();
	$errors   = array();
	$rows     = $data['rows'];

	/* Check a sample of rows (max 100) */
	$sample = array_slice( $rows, 0, min( 100, count( $rows ) ) );

	foreach ( $sample as $i => $row ) {
		$row_num = $i + 2;

		foreach ( $mapping as $field => $info ) {
			$value = ( 'custom' === $info['source'] )
				? $info['value']
				: ( isset( $row[ $info['col'] ] ) ? $row[ $info['col'] ] : '' );

			/* Empty required fields */
			if ( 'post_title' === $field && '' === trim( $value ) ) {
				/* translators: %d: row number */
				$warnings[] = sprintf( esc_html__( 'Row %d: Empty post title.', 'the-simplest-importer' ), $row_num );
			}

			/* Invalid dates */
			if ( 'post_date' === $field && '' !== $value && ! strtotime( $value ) ) {
				/* translators: %d: row number */
				$errors[] = sprintf( esc_html__( 'Row %d: Invalid date format.', 'the-simplest-importer' ), $row_num );
			}

			/* Invalid status */
			if ( 'post_status' === $field && '' !== $value ) {
				$allowed = array( 'publish', 'draft', 'pending', 'private', 'future' );
				if ( ! in_array( $value, $allowed, true ) ) {
					/* translators: 1: row number, 2: status value */
					$warnings[] = sprintf( esc_html__( 'Row %1$d: Unknown status "%2$s" — will default to draft.', 'the-simplest-importer' ), $row_num, esc_html( $value ) );
				}
			}

			/* Invalid author IDs */
			if ( 'post_author' === $field && '' !== $value && ! get_userdata( absint( $value ) ) ) {
				/* translators: 1: row number, 2: author ID */
				$warnings[] = sprintf( esc_html__( 'Row %1$d: Author ID %2$d not found.', 'the-simplest-importer' ), $row_num, absint( $value ) );
			}

			/* Invalid URL for thumbnail */
			if ( '_thumbnail_url' === $field && '' !== $value && ! wp_http_validate_url( $value ) ) {
				/* translators: %d: row number */
				$errors[] = sprintf( esc_html__( 'Row %d: Invalid thumbnail URL.', 'the-simplest-importer' ), $row_num );
			}
		}
	}

	wp_send_json_success( array(
		'warnings' => array_slice( $warnings, 0, 50 ),
		'errors'   => array_slice( $errors, 0, 50 ),
		'rows_checked' => count( $sample ),
		/* translators: %d: number of rows checked */
		'message'  => sprintf( esc_html__( 'Validated %d rows.', 'the-simplest-importer' ), count( $sample ) ),
	) );
}
add_action( 'wp_ajax_tsi_validate_csv', 'tsi_ajax_validate_csv' );

/* ------------------------------------------------------------------
 * AJAX — Rollback import
 * ------------------------------------------------------------------ */

/**
 * Rollback an import by deleting or trashing the imported posts.
 */
function tsi_ajax_rollback() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$history_id = isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( $_POST['history_id'] ) ) : '';
	if ( ! $history_id ) {
		wp_send_json_error( esc_html__( 'Missing history ID.', 'the-simplest-importer' ) );
	}

	$history = tsi_get_import_history();
	if ( ! isset( $history[ $history_id ] ) ) {
		wp_send_json_error( esc_html__( 'Import not found in history.', 'the-simplest-importer' ) );
	}

	$record   = $history[ $history_id ];
	$post_ids = isset( $record['post_ids'] ) ? $record['post_ids'] : array();
	$trashed  = 0;

	foreach ( $post_ids as $pid ) {
		$pid = absint( $pid );
		if ( $pid && get_post( $pid ) ) {
			wp_trash_post( $pid );
			$trashed++;
		}
	}

	/* Mark as rolled back in history */
	$history[ $history_id ]['rolled_back'] = true;
	$history[ $history_id ]['rolled_back_date'] = current_time( 'mysql' );
	update_option( TSI_HISTORY_OPTION, $history, false );

	wp_send_json_success( array(
		/* translators: %d: number of posts trashed */
		'message' => sprintf( esc_html__( '%d posts moved to trash.', 'the-simplest-importer' ), $trashed ),
		'trashed' => $trashed,
	) );
}
add_action( 'wp_ajax_tsi_rollback', 'tsi_ajax_rollback' );

/* ------------------------------------------------------------------
 * AJAX — Get import history
 * ------------------------------------------------------------------ */

/**
 * Return the import history as JSON.
 */
function tsi_ajax_get_history() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	wp_send_json_success( array(
		'history' => array_reverse( tsi_get_import_history() ),
	) );
}
add_action( 'wp_ajax_tsi_get_history', 'tsi_ajax_get_history' );

/* ------------------------------------------------------------------
 * AJAX — Add scheduled import
 * ------------------------------------------------------------------ */

/**
 * Save a new scheduled import.
 */
function tsi_ajax_add_schedule() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$name      = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
	$url       = isset( $_POST['csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['csv_url'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'daily';
	$profile   = isset( $_POST['profile_id'] ) ? sanitize_key( wp_unslash( $_POST['profile_id'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $name || ! $url || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Name, URL, and post type are required.', 'the-simplest-importer' ) );
	}

	$allowed_freq = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
	if ( ! in_array( $frequency, $allowed_freq, true ) ) {
		$frequency = 'daily';
	}

	$schedules = tsi_get_scheduled_imports();
	$id        = wp_generate_password( 8, false );

	$schedules[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'url'       => $url,
		'post_type' => $post_type,
		'frequency' => $frequency,
		'profile'   => $profile,
		'email'     => $email,
		'created'   => current_time( 'mysql' ),
		'last_run'  => '',
		'status'    => 'active',
	);

	update_option( TSI_SCHEDULES_OPTION, $schedules, false );

	/* Schedule the cron event */
	if ( ! wp_next_scheduled( 'tsi_scheduled_import', array( $id ) ) ) {
		wp_schedule_event( time(), $frequency, 'tsi_scheduled_import', array( $id ) );
	}

	wp_send_json_success( array(
		'schedules' => $schedules,
		/* translators: %s: schedule name */
		'message'   => sprintf( esc_html__( 'Schedule "%s" created.', 'the-simplest-importer' ), $name ),
	) );
}
add_action( 'wp_ajax_tsi_add_schedule', 'tsi_ajax_add_schedule' );

/* ------------------------------------------------------------------
 * AJAX — Delete scheduled import
 * ------------------------------------------------------------------ */

/**
 * Delete a scheduled import.
 */
function tsi_ajax_delete_schedule() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$id = isset( $_POST['schedule_id'] ) ? sanitize_key( wp_unslash( $_POST['schedule_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing schedule ID.', 'the-simplest-importer' ) );
	}

	$schedules = tsi_get_scheduled_imports();
	if ( isset( $schedules[ $id ] ) ) {
		unset( $schedules[ $id ] );
		update_option( TSI_SCHEDULES_OPTION, $schedules, false );

		/* Remove the cron event */
		$timestamp = wp_next_scheduled( 'tsi_scheduled_import', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_import', array( $id ) );
		}
	}

	wp_send_json_success( array(
		'schedules' => $schedules,
		'message'   => esc_html__( 'Schedule deleted.', 'the-simplest-importer' ),
	) );
}
add_action( 'wp_ajax_tsi_delete_schedule', 'tsi_ajax_delete_schedule' );

/* ------------------------------------------------------------------
 * WP-Cron — Execute scheduled import
 * ------------------------------------------------------------------ */

/**
 * Send an email notification for a scheduled import if an email is configured.
 *
 * @param array  $schedule The schedule config array.
 * @param string $body     The email body text.
 */
function tsi_send_schedule_email( $schedule, $body ) {
	$email = ! empty( $schedule['email'] ) ? $schedule['email'] : '';
	if ( ! $email || ! is_email( $email ) ) {
		return;
	}

	/* translators: %s: schedule name */
	$subject = sprintf( esc_html__( '[%s] Scheduled Import Report', 'the-simplest-importer' ), $schedule['name'] );
	wp_mail( $email, $subject, $body );
}

/**
 * Execute a scheduled import via WP-Cron.
 *
 * @param string $schedule_id The schedule ID.
 */
function tsi_run_scheduled_import( $schedule_id ) {
	$schedules = tsi_get_scheduled_imports();
	if ( ! isset( $schedules[ $schedule_id ] ) ) {
		return;
	}

	$schedule  = $schedules[ $schedule_id ];
	$post_type = $schedule['post_type'];
	$url       = $schedule['url'];

	/* Auto-convert Google Sheets URL */
	$url = tsi_convert_google_sheets_url( $url );

	/* Fetch CSV from URL */
	$response = wp_remote_get( $url, array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'error';
		update_option( TSI_SCHEDULES_OPTION, $schedules, false );
		tsi_send_schedule_email( $schedule, esc_html__( 'Failed to fetch CSV from URL.', 'the-simplest-importer' ) );
		return;
	}

	$body = wp_remote_retrieve_body( $response );

	/* Write to temp file so we can reuse tsi_read_csv_file() with delimiter auto-detection. */
	$tmp = wp_tempnam( 'tsi_sched_' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to temp file for CSV parsing.
	file_put_contents( $tmp, $body );

	$parsed = tsi_read_csv_file( $tmp );
	wp_delete_file( $tmp );

	if ( is_string( $parsed ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'error';
		update_option( TSI_SCHEDULES_OPTION, $schedules, false );
		tsi_send_schedule_email( $schedule, esc_html__( 'Failed to parse CSV data.', 'the-simplest-importer' ) );
		return;
	}

	$headers = $parsed['headers'];
	$csv_token = $parsed['token'];

	/* Retrieve rows from transient */
	$csv_data = get_transient( 'tsi_csv_data_' . $csv_token );
	if ( ! $csv_data || empty( $csv_data['rows'] ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'empty';
		update_option( TSI_SCHEDULES_OPTION, $schedules, false );
		tsi_send_schedule_email( $schedule, esc_html__( 'CSV file was empty or contained no data rows.', 'the-simplest-importer' ) );
		return;
	}

	$rows = $csv_data['rows'];

	/* Build mapping from profile or auto-match */
	$mapping = array();
	if ( ! empty( $schedule['profile'] ) ) {
		$profiles = tsi_get_mapping_profiles();
		if ( isset( $profiles[ $schedule['profile'] ] ) ) {
			$mapping = $profiles[ $schedule['profile'] ]['mapping'];
		}
	}

	if ( empty( $mapping ) ) {
		/* Auto-match by header name */
		$fields = tsi_get_post_type_fields( $post_type );
		foreach ( $headers as $col_index => $header ) {
			$header_clean = sanitize_title( trim( $header ) );
			foreach ( $fields as $field_key => $field_label ) {
				if ( sanitize_title( $field_key ) === $header_clean || sanitize_title( $field_label ) === $header_clean ) {
					$mapping[ $field_key ] = array(
						'source' => 'csv',
						'col'    => $col_index,
					);
					break;
				}
			}
		}
	}

	if ( empty( $mapping ) ) {
		$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$schedules[ $schedule_id ]['last_status'] = 'no_mapping';
		update_option( TSI_SCHEDULES_OPTION, $schedules, false );
		tsi_send_schedule_email( $schedule, esc_html__( 'No column mapping could be determined.', 'the-simplest-importer' ) );
		return;
	}

	$clean_map = tsi_sanitize_mapping( $mapping, count( $headers ) );

	$inserted = 0;
	$updated  = 0;
	$errors   = 0;
	$post_ids = array();

	foreach ( $rows as $i => $row ) {
		$result = tsi_import_single_row( $row, $i + 2, $post_type, $clean_map );
		if ( ! empty( $result['post_id'] ) ) {
			$post_ids[] = $result['post_id'];
		}
		if ( 'inserted' === $result['status'] ) {
			$inserted++;
		} elseif ( 'updated' === $result['status'] ) {
			$updated++;
		} else {
			$errors++;
		}
	}

	/* Record in history */
	$history_id = 'sched-' . $schedule_id . '-' . time();
	tsi_record_import_history( $history_id, $post_type, 'scheduled', $inserted, $updated, 0, $errors, $post_ids );

	/* Clean up CSV transient */
	delete_transient( 'tsi_csv_data_' . $csv_token );

	/* Update schedule status */
	$schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
	$schedules[ $schedule_id ]['last_status'] = 'success';
	$schedules[ $schedule_id ]['last_count']  = $inserted + $updated;
	update_option( TSI_SCHEDULES_OPTION, $schedules, false );

	/* Send email notification if configured */
	$lines = array();
	/* translators: %s: schedule name */
	$lines[] = sprintf( esc_html__( 'Schedule: %s', 'the-simplest-importer' ), $schedule['name'] );
	/* translators: %s: post type slug */
	$lines[] = sprintf( esc_html__( 'Post type: %s', 'the-simplest-importer' ), $post_type );
	/* translators: %d: number of inserted posts */
	$lines[] = sprintf( esc_html__( 'Inserted: %d', 'the-simplest-importer' ), $inserted );
	/* translators: %d: number of updated posts */
	$lines[] = sprintf( esc_html__( 'Updated: %d', 'the-simplest-importer' ), $updated );
	/* translators: %d: number of errors */
	$lines[] = sprintf( esc_html__( 'Errors: %d', 'the-simplest-importer' ), $errors );
	/* translators: %s: date and time */
	$lines[] = sprintf( esc_html__( 'Completed at: %s', 'the-simplest-importer' ), current_time( 'mysql' ) );
	tsi_send_schedule_email( $schedule, implode( "\n", $lines ) );
}
add_action( 'tsi_scheduled_import', 'tsi_run_scheduled_import' );

/* ------------------------------------------------------------------
 * WP-Cron — Register weekly schedule interval
 * ------------------------------------------------------------------ */

/**
 * Add a weekly interval to WP-Cron schedules.
 *
 * @param array $schedules Existing cron schedules.
 * @return array
 */
function tsi_add_cron_interval( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => esc_html__( 'Once Weekly', 'the-simplest-importer' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'tsi_add_cron_interval' );

/* ------------------------------------------------------------------
 * Helper — Scheduled exports CRUD
 * ------------------------------------------------------------------ */

/**
 * Get export schedules list.
 *
 * @return array
 */
function tsi_get_export_schedules() {
	return get_option( TSI_EXPORT_SCHEDULES_OPTION, array() );
}

/* ------------------------------------------------------------------
 * AJAX — Add scheduled export
 * ------------------------------------------------------------------ */

/**
 * Save a new scheduled export.
 */
function tsi_ajax_add_export_schedule() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$name      = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'weekly';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $name || ! $post_type ) {
		wp_send_json_error( esc_html__( 'Name and post type are required.', 'the-simplest-importer' ) );
	}

	$allowed_freq = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
	if ( ! in_array( $frequency, $allowed_freq, true ) ) {
		$frequency = 'weekly';
	}

	$export_schedules = tsi_get_export_schedules();
	$id               = wp_generate_password( 8, false );

	$export_schedules[ $id ] = array(
		'id'        => $id,
		'name'      => $name,
		'post_type' => $post_type,
		'frequency' => $frequency,
		'email'     => $email,
		'created'   => current_time( 'mysql' ),
		'last_run'  => '',
		'status'    => 'active',
	);

	update_option( TSI_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

	/* Schedule the cron event */
	if ( ! wp_next_scheduled( 'tsi_scheduled_export', array( $id ) ) ) {
		wp_schedule_event( time(), $frequency, 'tsi_scheduled_export', array( $id ) );
	}

	wp_send_json_success( array(
		'export_schedules' => $export_schedules,
		/* translators: %s: schedule name */
		'message'          => sprintf( esc_html__( 'Export schedule "%s" created.', 'the-simplest-importer' ), $name ),
	) );
}
add_action( 'wp_ajax_tsi_add_export_schedule', 'tsi_ajax_add_export_schedule' );

/* ------------------------------------------------------------------
 * AJAX — Delete scheduled export
 * ------------------------------------------------------------------ */

/**
 * Delete a scheduled export.
 */
function tsi_ajax_delete_export_schedule() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$id = isset( $_POST['schedule_id'] ) ? sanitize_key( wp_unslash( $_POST['schedule_id'] ) ) : '';
	if ( ! $id ) {
		wp_send_json_error( esc_html__( 'Missing schedule ID.', 'the-simplest-importer' ) );
	}

	$export_schedules = tsi_get_export_schedules();
	if ( isset( $export_schedules[ $id ] ) ) {
		unset( $export_schedules[ $id ] );
		update_option( TSI_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

		/* Remove the cron event */
		$timestamp = wp_next_scheduled( 'tsi_scheduled_export', array( $id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsi_scheduled_export', array( $id ) );
		}
	}

	wp_send_json_success( array(
		'export_schedules' => $export_schedules,
		'message'          => esc_html__( 'Export schedule deleted.', 'the-simplest-importer' ),
	) );
}
add_action( 'wp_ajax_tsi_delete_export_schedule', 'tsi_ajax_delete_export_schedule' );

/* ------------------------------------------------------------------
 * WP-Cron — Execute scheduled export
 * ------------------------------------------------------------------ */

/**
 * Execute a scheduled export via WP-Cron.
 *
 * @param string $schedule_id The export schedule ID.
 */
function tsi_run_scheduled_export( $schedule_id ) {
	$export_schedules = tsi_get_export_schedules();
	if ( ! isset( $export_schedules[ $schedule_id ] ) ) {
		return;
	}

	$schedule  = $export_schedules[ $schedule_id ];
	$post_type = $schedule['post_type'];

	if ( ! post_type_exists( $post_type ) ) {
		$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$export_schedules[ $schedule_id ]['last_status'] = 'error';
		update_option( TSI_EXPORT_SCHEDULES_OPTION, $export_schedules, false );
		return;
	}

	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	if ( empty( $posts ) ) {
		$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
		$export_schedules[ $schedule_id ]['last_status'] = 'empty';
		update_option( TSI_EXPORT_SCHEDULES_OPTION, $export_schedules, false );
		return;
	}

	$fields    = tsi_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author' );

	$meta_fields = array();
	$tax_fields  = array();
	foreach ( $fields as $key => $label ) {
		if ( 0 === strpos( $key, 'meta__' ) ) {
			$meta_fields[] = substr( $key, 6 );
		} elseif ( 0 === strpos( $key, 'tax__' ) ) {
			$tax_fields[] = substr( $key, 5 );
		}
	}

	$header = $core_keys;
	foreach ( $tax_fields as $t ) {
		$header[] = 'tax__' . $t;
	}
	foreach ( $meta_fields as $m ) {
		$header[] = 'meta__' . $m;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $header );

	foreach ( $posts as $p ) {
		$row = array();
		foreach ( $core_keys as $ck ) {
			$row[] = isset( $p->$ck ) ? $p->$ck : '';
		}
		foreach ( $tax_fields as $t ) {
			$terms = wp_get_object_terms( $p->ID, $t, array( 'fields' => 'names' ) );
			$row[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
		}
		foreach ( $meta_fields as $m ) {
			$val   = get_post_meta( $p->ID, $m, true );
			$row[] = is_array( $val ) ? wp_json_encode( $val ) : ( isset( $val ) ? (string) $val : '' );
		}
		fputcsv( $output, $row );
	}

	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	/* Ensure export directory exists */
	$upload_dir  = wp_upload_dir();
	$export_dir  = $upload_dir['basedir'] . '/tsi-exports';

	if ( ! file_exists( $export_dir ) ) {
		wp_mkdir_p( $export_dir );

		/* Protect directory from direct browsing */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing security files to export directory.
		file_put_contents( $export_dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing .htaccess to protect export directory.
		file_put_contents( $export_dir . '/.htaccess', "Options -Indexes\nDeny from all\n" );
	}

	/* Write the CSV file */
	$filename = sanitize_file_name( $post_type . '-export-' . gmdate( 'Y-m-d-His' ) . '.csv' );
	$filepath = $export_dir . '/' . $filename;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing scheduled export CSV to disk.
	file_put_contents( $filepath, $csv_string );

	/* Auto-clean exports older than 7 days */
	tsi_cleanup_old_exports( $export_dir );

	/* Update schedule status */
	$export_schedules[ $schedule_id ]['last_run']    = current_time( 'mysql' );
	$export_schedules[ $schedule_id ]['last_status'] = 'success';
	/* translators: %d: number of posts exported */
	$export_schedules[ $schedule_id ]['last_count']  = count( $posts );
	update_option( TSI_EXPORT_SCHEDULES_OPTION, $export_schedules, false );

	/* Send email notification if configured */
	$email = ! empty( $schedule['email'] ) ? $schedule['email'] : '';
	if ( $email && is_email( $email ) ) {
		/* translators: %s: schedule name */
		$subject = sprintf( esc_html__( '[%s] Scheduled Export Complete', 'the-simplest-importer' ), $schedule['name'] );

		$lines = array();
		/* translators: %s: schedule name */
		$lines[] = sprintf( esc_html__( 'Schedule: %s', 'the-simplest-importer' ), $schedule['name'] );
		/* translators: %s: post type slug */
		$lines[] = sprintf( esc_html__( 'Post type: %s', 'the-simplest-importer' ), $post_type );
		/* translators: %d: number of posts exported */
		$lines[] = sprintf( esc_html__( 'Posts exported: %d', 'the-simplest-importer' ), count( $posts ) );
		/* translators: %s: date and time */
		$lines[] = sprintf( esc_html__( 'Completed at: %s', 'the-simplest-importer' ), current_time( 'mysql' ) );
		$lines[] = esc_html__( 'The CSV file is attached to this email.', 'the-simplest-importer' );

		wp_mail( $email, $subject, implode( "\n", $lines ), '', array( $filepath ) );
	}
}
add_action( 'tsi_scheduled_export', 'tsi_run_scheduled_export' );

/**
 * Remove exported CSV files older than 7 days.
 *
 * @param string $directory The export directory path.
 */
function tsi_cleanup_old_exports( $directory ) {
	$files = glob( $directory . '/*.csv' );
	if ( ! is_array( $files ) ) {
		return;
	}
	$threshold = time() - ( 7 * DAY_IN_SECONDS );
	foreach ( $files as $file ) {
		if ( filemtime( $file ) < $threshold ) {
			wp_delete_file( $file );
		}
	}
}

/* ------------------------------------------------------------------
 * Meta Box — Export single post from edit screen
 * ------------------------------------------------------------------ */

/**
 * Register the TSI meta box on all post types with show_ui.
 */
function tsi_register_meta_box() {
	$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'tsi-single-export',
			esc_html__( 'The Simplest Importer', 'the-simplest-importer' ),
			'tsi_render_meta_box',
			$pt,
			'side',
			'low'
		);
	}
}
add_action( 'add_meta_boxes', 'tsi_register_meta_box' );

/**
 * Render the meta box content.
 *
 * @param WP_Post $post The current post object.
 */
function tsi_render_meta_box( $post ) {
	wp_nonce_field( 'tsi_nonce', 'tsi_meta_box_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Export this post as a CSV file.', 'the-simplest-importer' ); ?></p>
	<button type="button" class="button button-small" id="tsi-export-single" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Download CSV', 'the-simplest-importer' ); ?></button>
	<script>
	(function () {
		document.getElementById('tsi-export-single').addEventListener('click', function () {
			var postId = this.getAttribute('data-post-id');
			var nonce  = document.getElementById('tsi_meta_box_nonce').value;
			var data   = new FormData();
			data.append('action', 'tsi_export_single_post');
			data.append('nonce', nonce);
			data.append('post_id', postId);
			fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) {
						window.alert(res.data || 'Export failed.');
						return;
					}
					var raw = atob(res.data.csv);
					var bytes = new Uint8Array(raw.length);
					for (var i = 0; i < raw.length; i++) { bytes[i] = raw.charCodeAt(i); }
					var blob = new Blob([bytes], { type: 'text/csv;charset=utf-8' });
					var url  = URL.createObjectURL(blob);
					var a    = document.createElement('a');
					a.href = url;
					a.download = res.data.filename;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
				});
		});
	})();
	</script>
	<?php
}

/**
 * AJAX handler — export a single post as CSV.
 */
function tsi_ajax_export_single_post() {
	check_ajax_referer( 'tsi_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'the-simplest-importer' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( esc_html__( 'Invalid post ID.', 'the-simplest-importer' ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( esc_html__( 'Post not found.', 'the-simplest-importer' ) );
	}

	$post_type = $post->post_type;
	$fields    = tsi_get_post_type_fields( $post_type );
	$core_keys = array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_name', 'post_author' );

	$meta_fields = array();
	$tax_fields  = array();
	foreach ( $fields as $key => $label ) {
		if ( 0 === strpos( $key, 'meta__' ) ) {
			$meta_fields[] = substr( $key, 6 );
		} elseif ( 0 === strpos( $key, 'tax__' ) ) {
			$tax_fields[] = substr( $key, 5 );
		}
	}

	$header = $core_keys;
	foreach ( $tax_fields as $t ) {
		$header[] = 'tax__' . $t;
	}
	foreach ( $meta_fields as $m ) {
		$header[] = 'meta__' . $m;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp for in-memory CSV generation.
	$output = fopen( 'php://temp', 'r+' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing UTF-8 BOM to in-memory stream.
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $header );

	$row = array();
	foreach ( $core_keys as $ck ) {
		$row[] = isset( $post->$ck ) ? $post->$ck : '';
	}
	foreach ( $tax_fields as $t ) {
		$terms = wp_get_object_terms( $post->ID, $t, array( 'fields' => 'names' ) );
		$row[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
	}
	foreach ( $meta_fields as $m ) {
		$val   = get_post_meta( $post->ID, $m, true );
		$row[] = is_array( $val ) ? wp_json_encode( $val ) : ( isset( $val ) ? (string) $val : '' );
	}
	fputcsv( $output, $row );

	rewind( $output );
	$csv_string = stream_get_contents( $output );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp in-memory stream.
	fclose( $output );

	wp_send_json_success( array(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 needed to transport CSV binary through JSON.
		'csv'      => base64_encode( $csv_string ),
		'filename' => sanitize_file_name( $post_type . '-' . $post_id . '-export.csv' ),
	) );
}
add_action( 'wp_ajax_tsi_export_single_post', 'tsi_ajax_export_single_post' );
