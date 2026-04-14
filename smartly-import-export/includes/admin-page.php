<?php
/**
 * Admin page rendering and asset enqueueing.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/* ------------------------------------------------------------------
 * Enqueue Admin Assets
 * ------------------------------------------------------------------ */

add_action( 'admin_enqueue_scripts', 'smie_enqueue_admin_assets' );

/**
 * Enqueue CSS and JS only on the plugin admin page.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function smie_enqueue_admin_assets( $hook ) {
	if ( 'tools_page_smartly-import-export' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'smie-admin',
		SMIE_PLUGIN_URL . 'assets/style.css',
		array(),
		(string) filemtime( SMIE_PLUGIN_DIR . 'assets/style.css' )
	);

	wp_enqueue_style(
		'smie-responsive',
		SMIE_PLUGIN_URL . 'assets/css/responsive.css',
		array( 'smie-admin' ),
		(string) filemtime( SMIE_PLUGIN_DIR . 'assets/css/responsive.css' )
	);

	wp_enqueue_style(
		'smie-features',
		SMIE_PLUGIN_URL . 'assets/css/features.css',
		array( 'smie-admin' ),
		(string) filemtime( SMIE_PLUGIN_DIR . 'assets/css/features.css' )
	);

	wp_enqueue_script(
		'smie-admin',
		SMIE_PLUGIN_URL . 'assets/app.js',
		array( 'jquery' ),
		(string) filemtime( SMIE_PLUGIN_DIR . 'assets/app.js' ),
		true
	);

	wp_localize_script( 'smie-admin', 'smieImporter', array(
		'ajax_url'         => admin_url( 'admin-ajax.php' ),
		'nonce'            => wp_create_nonce( 'smie_nonce' ),
		'batch_size'       => absint( smie_apply_filters( 'smie_import_batch_size', 50 ) ),
		'profiles'         => smie_get_mapping_profiles(),
		'history'          => smie_get_import_history(),
		'schedules'        => smie_get_scheduled_imports(),
		'export_schedules' => smie_get_export_schedules(),
	) );

	wp_add_inline_script( 'smie-admin', 'window.tsiImporter = window.smieImporter;', 'after' );
}

/* ------------------------------------------------------------------
 * Admin Page Markup
 * ------------------------------------------------------------------ */

/**
 * Render the plugin admin page.
 *
 * @return void
 */
function smie_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap smie-wrap">

		<div class="smie-header">
			<h1>
				<span class="dashicons dashicons-database-import"></span>
				<?php esc_html_e( 'Smartly Import Export', 'smartly-import-export' ); ?>
				<button type="button" class="smie-dark-toggle" id="smie-dark-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'smartly-import-export' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
				</button>
			</h1>
			<p class="smie-subtitle"><?php esc_html_e( 'Import, export, and manage your WordPress content using CSV, JSON, and XML files.', 'smartly-import-export' ); ?></p>
		</div>

		<!-- Step 1 — Choose content type -->
		<div class="smie-card" id="smie-step-entity">
			<div class="smie-card-header">
				<span class="smie-step-num">1</span>
				<div>
					<h2><?php esc_html_e( 'Choose Content Type', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Select the post type you want to work with.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<select id="smie-post-type" class="smie-select" aria-label="<?php esc_attr_e( 'Select content type', 'smartly-import-export' ); ?>">
					<option value=""><?php esc_html_e( '— Select content type —', 'smartly-import-export' ); ?></option>
				</select>
				<p class="description smie-entity-hint"><?php esc_html_e( 'Includes all registered post types with a UI.', 'smartly-import-export' ); ?></p>
			</div>
		</div>

		<!-- Step 2 — Choose action -->
		<div class="smie-card" id="smie-step-actions" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num">2</span>
				<div>
					<h2><?php esc_html_e( 'Choose Action', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'What would you like to do with this content type?', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div class="smie-action-grid">
					<button type="button" id="smie-btn-import" class="smie-action-card">
						<span class="dashicons dashicons-upload"></span>
						<strong><?php esc_html_e( 'Import', 'smartly-import-export' ); ?></strong>
						<span class="smie-action-desc"><?php esc_html_e( 'Upload a file to create or update posts', 'smartly-import-export' ); ?></span>
					</button>
					<button type="button" id="smie-btn-export" class="smie-action-card">
						<span class="dashicons dashicons-download"></span>
						<strong><?php esc_html_e( 'Export', 'smartly-import-export' ); ?></strong>
						<span class="smie-action-desc"><?php esc_html_e( 'Download posts as CSV or Excel', 'smartly-import-export' ); ?></span>
					</button>
					<button type="button" id="smie-btn-template" class="smie-action-card">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<strong><?php esc_html_e( 'Template', 'smartly-import-export' ); ?></strong>
						<span class="smie-action-desc"><?php esc_html_e( 'Get a blank CSV with correct headers', 'smartly-import-export' ); ?></span>
					</button>
					<button type="button" id="smie-btn-history" class="smie-action-card">
						<span class="dashicons dashicons-backup"></span>
						<strong><?php esc_html_e( 'History', 'smartly-import-export' ); ?></strong>
						<span class="smie-action-desc"><?php esc_html_e( 'View past imports and rollback', 'smartly-import-export' ); ?></span>
					</button>
					<button type="button" id="smie-btn-schedule" class="smie-action-card">
						<span class="dashicons dashicons-clock"></span>
						<strong><?php esc_html_e( 'Schedule', 'smartly-import-export' ); ?></strong>
						<span class="smie-action-desc"><?php esc_html_e( 'Set up recurring imports from a URL', 'smartly-import-export' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 3 — Provide CSV -->
		<div class="smie-card" id="smie-step-export" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Export Options', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Choose which posts to include in the export.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<fieldset class="smie-export-options">
					<div class="smie-export-option-wrap">
						<label class="smie-export-option">
							<input type="radio" name="smie-export-mode" value="all" checked>
							<strong><?php esc_html_e( 'Export All', 'smartly-import-export' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export every post of this content type.', 'smartly-import-export' ); ?></span>
						</label>
					</div>
					<div class="smie-export-option-wrap">
						<label class="smie-export-option">
							<input type="radio" name="smie-export-mode" value="rows">
							<strong><?php esc_html_e( 'Export by Row Number', 'smartly-import-export' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export a specific range of rows (ordered by ID, starting from row 1).', 'smartly-import-export' ); ?></span>
						</label>
						<div class="smie-export-range-fields" id="smie-export-row-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From row:', 'smartly-import-export' ); ?>
								<input type="number" id="smie-export-row-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To row:', 'smartly-import-export' ); ?>
								<input type="number" id="smie-export-row-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="smie-export-option-wrap">
						<label class="smie-export-option">
							<input type="radio" name="smie-export-mode" value="range">
							<strong><?php esc_html_e( 'Export by Post ID Range', 'smartly-import-export' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts whose ID property falls within a specific range.', 'smartly-import-export' ); ?></span>
						</label>
						<div class="smie-export-range-fields" id="smie-export-range-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From ID:', 'smartly-import-export' ); ?>
								<input type="number" id="smie-export-id-from" min="1" step="1" class="small-text">
							</label>
							<label>
								<?php esc_html_e( 'To ID:', 'smartly-import-export' ); ?>
								<input type="number" id="smie-export-id-to" min="1" step="1" class="small-text">
							</label>
						</div>
					</div>
					<div class="smie-export-option-wrap">
						<label class="smie-export-option">
							<input type="radio" name="smie-export-mode" value="dates">
							<strong><?php esc_html_e( 'Export by Date Range', 'smartly-import-export' ); ?></strong>
							<span class="description"><?php esc_html_e( 'Export posts published between two dates.', 'smartly-import-export' ); ?></span>
						</label>
						<div class="smie-export-date-fields" id="smie-export-date-fields" style="display:none">
							<label>
								<?php esc_html_e( 'From:', 'smartly-import-export' ); ?>
								<input type="date" id="smie-export-date-from">
							</label>
							<label>
								<?php esc_html_e( 'To:', 'smartly-import-export' ); ?>
								<input type="date" id="smie-export-date-to">
							</label>
						</div>
					</div>
				</fieldset>
				<div class="smie-export-actions">
					<select id="smie-export-format" class="smie-export-format">
						<option value="csv"><?php esc_html_e( 'CSV', 'smartly-import-export' ); ?></option>
						<option value="xlsx"><?php esc_html_e( 'Excel (XLSX)', 'smartly-import-export' ); ?></option>
					</select>
					<button type="button" id="smie-btn-run-export" class="button button-primary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export', 'smartly-import-export' ); ?>
					</button>
				</div>

				<details class="smie-export-advanced">
					<summary><?php esc_html_e( 'Advanced Options', 'smartly-import-export' ); ?></summary>
					<div class="smie-export-advanced-body">
						<div class="smie-export-section">
							<h4><?php esc_html_e( 'Filter by Status', 'smartly-import-export' ); ?></h4>
							<div class="smie-status-checks">
								<label><input type="checkbox" class="smie-export-status" name="smie-export-status" value="publish" checked> <?php esc_html_e( 'Published', 'smartly-import-export' ); ?></label>
								<label><input type="checkbox" class="smie-export-status" name="smie-export-status" value="draft" checked> <?php esc_html_e( 'Draft', 'smartly-import-export' ); ?></label>
								<label><input type="checkbox" class="smie-export-status" name="smie-export-status" value="pending" checked> <?php esc_html_e( 'Pending', 'smartly-import-export' ); ?></label>
								<label><input type="checkbox" class="smie-export-status" name="smie-export-status" value="private" checked> <?php esc_html_e( 'Private', 'smartly-import-export' ); ?></label>
								<label><input type="checkbox" class="smie-export-status" name="smie-export-status" value="future"> <?php esc_html_e( 'Scheduled', 'smartly-import-export' ); ?></label>
							</div>
						</div>
						<div class="smie-export-section">
							<h4><?php esc_html_e( 'Select Columns', 'smartly-import-export' ); ?></h4>
							<p class="description"><?php esc_html_e( 'Leave all checked to export every field.', 'smartly-import-export' ); ?></p>
							<div id="smie-export-fields" class="smie-export-fields-list"></div>
						</div>
					</div>
				</details>
			</div>
		</div>

		<!-- Step 3 — Provide CSV (Import) -->
		<div class="smie-card" id="smie-step-source" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num">3</span>
				<div>
					<h2><?php esc_html_e( 'Provide Data File', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Upload a file from your computer or fetch one from a URL.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div class="smie-source-tabs">
					<button type="button" class="smie-source-tab smie-source-tab--active" data-tab="upload">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Upload File', 'smartly-import-export' ); ?>
					</button>
					<button type="button" class="smie-source-tab" data-tab="url">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'From URL', 'smartly-import-export' ); ?>
					</button>
				</div>

				<div id="smie-source-upload" class="smie-source-panel">
					<div class="smie-dropzone" id="smie-dropzone">
						<div class="smie-dropzone-inner">
							<span class="dashicons dashicons-cloud-upload"></span>
							<p class="smie-dropzone-title"><?php esc_html_e( 'Drag & drop your file here', 'smartly-import-export' ); ?></p>
							<p class="smie-dropzone-or"><?php esc_html_e( 'or', 'smartly-import-export' ); ?></p>
							<button type="button" class="button" id="smie-browse-btn"><?php esc_html_e( 'Browse Files', 'smartly-import-export' ); ?></button>
							<input type="file" id="smie-csv-file" accept=".csv,.json,.xml">
							<p class="description"><?php esc_html_e( 'Accepts .csv, .json, and .xml files · UTF-8 recommended · first row/object must define columns', 'smartly-import-export' ); ?></p>
						</div>
					</div>
				</div>

				<div id="smie-source-url" class="smie-source-panel" style="display:none">
					<div class="smie-url-row">
						<input type="url" id="smie-csv-url" class="regular-text" placeholder="https://example.com/data.csv">
						<button type="button" id="smie-btn-fetch-url" class="button button-primary"><?php esc_html_e( 'Fetch', 'smartly-import-export' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Enter a direct link to a publicly accessible CSV, JSON, or XML file. Google Sheets URLs are automatically converted.', 'smartly-import-export' ); ?></p>
				</div>

				<div id="smie-file-info" class="smie-file-info" style="display:none"></div>
				<div id="smie-file-queue" style="display:none"></div>
				<div id="smie-preview" style="display:none"></div>
			</div>
		</div>

		<!-- Step 4 — Map columns -->
		<div class="smie-card" id="smie-step-mapping" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num">4</span>
				<div>
					<h2><?php esc_html_e( 'Map Columns', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Match file columns to post fields. Uncheck fields you do not need.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div class="smie-mapping-toolbar">
					<button type="button" id="smie-select-all" class="button button-small"><?php esc_html_e( 'Select All', 'smartly-import-export' ); ?></button>
					<button type="button" id="smie-deselect-all" class="button button-small"><?php esc_html_e( 'Deselect All', 'smartly-import-export' ); ?></button>
					<button type="button" id="smie-reset-all" class="button button-small"><span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e( 'Reset Mappings', 'smartly-import-export' ); ?></button>
					<select id="smie-profile-select" class="smie-profile-select">
						<option value=""><?php esc_html_e( '— Load Profile —', 'smartly-import-export' ); ?></option>
					</select>
					<button type="button" id="smie-save-profile" class="button button-small"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Save Profile', 'smartly-import-export' ); ?></button>
					<button type="button" id="smie-delete-profile" class="button button-small" style="display:none"><span class="dashicons dashicons-trash"></span></button>
					<span class="smie-mapping-count" id="smie-mapping-count"></span>
				</div>
				<div class="smie-field-search">
					<span class="dashicons dashicons-search smie-field-search-icon"></span>
					<input type="text" id="smie-field-search" class="smie-field-search-input" placeholder="<?php esc_attr_e( 'Search fields…', 'smartly-import-export' ); ?>" />
					<span class="smie-field-search-count" id="smie-field-search-count"></span>
				</div>
				<div class="smie-table-wrap">
					<table class="widefat smie-mapping-table" id="smie-mapping-table">
						<thead>
							<tr>
								<th class="smie-col-check"><?php esc_html_e( 'Use', 'smartly-import-export' ); ?></th>
								<th class="smie-col-field"><?php esc_html_e( 'Post Field', 'smartly-import-export' ); ?></th>
								<th class="smie-col-map"><?php esc_html_e( 'Column / Value', 'smartly-import-export' ); ?></th>
								<th class="smie-col-transform"><?php esc_html_e( 'Transform', 'smartly-import-export' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="smie-mapping-preview" id="smie-mapping-preview" style="display:none">
					<h4><?php esc_html_e( 'Sample Row Preview', 'smartly-import-export' ); ?></h4>
					<div id="smie-mapping-preview-content"></div>
				</div>
				<div class="smie-mapping-actions">
					<button type="button" id="smie-add-extra" class="button">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom Field', 'smartly-import-export' ); ?>
					</button>

					<details class="smie-import-settings" open>
						<summary><?php esc_html_e( 'Import Settings', 'smartly-import-export' ); ?></summary>
						<div class="smie-import-settings-body">
							<div class="smie-import-options">
								<label class="smie-option-label" title="<?php esc_attr_e( 'Process all rows without inserting or updating any posts', 'smartly-import-export' ); ?>">
									<input type="checkbox" id="smie-dry-run"> <?php esc_html_e( 'Dry Run', 'smartly-import-export' ); ?>
								</label>
								<label class="smie-option-label" title="<?php esc_attr_e( 'Check for existing posts before inserting', 'smartly-import-export' ); ?>">
									<input type="checkbox" id="smie-dup-check"> <?php esc_html_e( 'Duplicate Check', 'smartly-import-export' ); ?>
								</label>
								<div id="smie-dup-options">
									<select id="smie-dup-field" class="smie-dup-field-select">
										<option value="post_title"><?php esc_html_e( 'by Title', 'smartly-import-export' ); ?></option>
										<option value="post_name"><?php esc_html_e( 'by Slug', 'smartly-import-export' ); ?></option>
										<option value="meta_key"><?php esc_html_e( 'by Meta Key', 'smartly-import-export' ); ?></option>
									</select>
									<span id="smie-dup-meta-wrap">
										<input type="text" id="smie-dup-meta-key" class="small-text" placeholder="<?php esc_attr_e( 'meta key', 'smartly-import-export' ); ?>">
									</span>
								</div>
							</div>
							<div class="smie-filter-section" id="smie-filter-section" style="display:none">
								<h4><?php esc_html_e( 'Row Filters', 'smartly-import-export' ); ?></h4>
								<p class="description"><?php esc_html_e( 'Only import rows matching all rules (AND logic).', 'smartly-import-export' ); ?></p>
								<div id="smie-filter-rules"></div>
								<button type="button" id="smie-add-filter" class="button button-small">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e( 'Add Filter Rule', 'smartly-import-export' ); ?>
								</button>
							</div>
						</div>
					</details>

					<div class="smie-import-buttons">
						<button type="button" id="smie-btn-validate" class="button smie-btn-run">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Validate', 'smartly-import-export' ); ?>
						</button>
						<button type="button" id="smie-btn-insert" class="button button-primary smie-btn-run">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Insert New', 'smartly-import-export' ); ?>
						</button>
						<button type="button" id="smie-btn-update" class="button button-primary smie-btn-run" style="display:none">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Update Existing', 'smartly-import-export' ); ?>
						</button>
						<button type="button" id="smie-btn-insert-update" class="button button-primary smie-btn-run" style="display:none">
							<span class="dashicons dashicons-controls-repeat"></span>
							<?php esc_html_e( 'Insert / Update', 'smartly-import-export' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Step 5 — Progress -->
		<div class="smie-card" id="smie-step-progress" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num smie-step-num--active">
					<span class="spinner is-active" style="margin:0;float:none"></span>
				</span>
				<div>
					<h2 id="smie-progress-title"><?php esc_html_e( 'Importing…', 'smartly-import-export' ); ?></h2>
					<p id="smie-progress-detail"><?php esc_html_e( 'Preparing your import…', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div class="smie-progress">
					<div class="smie-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Import progress', 'smartly-import-export' ); ?>" id="smie-progress-bar">
						<div class="smie-progress-fill" id="smie-progress-fill"></div>
					</div>
					<span class="smie-progress-pct" id="smie-progress-pct">0%</span>
				</div>
				<div class="smie-live-log" id="smie-live-log" aria-live="polite" aria-relevant="additions"></div>
			</div>
		</div>

		<!-- Step 6 — Results -->
		<div class="smie-card" id="smie-step-results" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num smie-step-num--done">
					<span class="dashicons dashicons-yes-alt"></span>
				</span>
				<div>
					<h2><?php esc_html_e( 'Import Complete', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Review the results below.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div id="smie-results-summary" class="smie-summary"></div>
				<div id="smie-results-log"></div>
				<div class="smie-results-actions">
					<button type="button" id="smie-btn-new" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Start New Import', 'smartly-import-export' ); ?>
					</button>
					<button type="button" id="smie-btn-download-log" class="button">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Log', 'smartly-import-export' ); ?>
					</button>
					<button type="button" id="smie-btn-rollback" class="button" style="display:none">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Rollback Import', 'smartly-import-export' ); ?>
					</button>
					<button type="button" id="smie-btn-retry-failed" class="button button-primary" style="display:none">
						<span class="dashicons dashicons-controls-repeat"></span>
						<?php esc_html_e( 'Retry Failed Rows', 'smartly-import-export' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Validation Results -->
		<div class="smie-card" id="smie-step-validation" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num"><span class="dashicons dashicons-yes-alt"></span></span>
				<div>
					<h2><?php esc_html_e( 'Validation Results', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Review issues found in your data before importing.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<div id="smie-validation-results" role="alert"></div>
				<div class="smie-results-actions">
					<button type="button" id="smie-btn-proceed-import" class="button button-primary"><?php esc_html_e( 'Proceed with Import', 'smartly-import-export' ); ?></button>
					<button type="button" id="smie-btn-cancel-import" class="button"><?php esc_html_e( 'Cancel', 'smartly-import-export' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Import History -->
		<div class="smie-card" id="smie-step-history" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num"><span class="dashicons dashicons-backup"></span></span>
				<div>
					<h2><?php esc_html_e( 'Import History', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'View past imports and undo them if needed.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<table class="widefat smie-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'I / U / S / E', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Posts', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'smartly-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="smie-history-body">
						<tr><td colspan="6"><?php esc_html_e( 'Loading…', 'smartly-import-export' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Scheduled Imports -->
		<div class="smie-card" id="smie-step-schedule" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num"><span class="dashicons dashicons-clock"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Imports', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Automatically import from a URL on a recurring schedule.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<table class="widefat smie-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Status', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'smartly-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="smie-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled imports.', 'smartly-import-export' ); ?></td></tr>
					</tbody>
				</table>
				<div class="smie-schedule-form">
					<h4><?php esc_html_e( 'Add New Schedule', 'smartly-import-export' ); ?></h4>
					<div class="smie-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'smartly-import-export' ); ?>
							<input type="text" id="smie-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My daily import', 'smartly-import-export' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'Data URL:', 'smartly-import-export' ); ?>
							<input type="url" id="smie-schedule-url" class="regular-text" placeholder="https://example.com/data.csv">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'smartly-import-export' ); ?>
							<select id="smie-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'smartly-import-export' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'smartly-import-export' ); ?></option>
								<option value="daily" selected><?php esc_html_e( 'Daily', 'smartly-import-export' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'smartly-import-export' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Mapping Profile:', 'smartly-import-export' ); ?>
							<select id="smie-schedule-profile">
								<option value=""><?php esc_html_e( '— auto-match —', 'smartly-import-export' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'smartly-import-export' ); ?>
							<input type="email" id="smie-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'smartly-import-export' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will use the currently selected post type.', 'smartly-import-export' ); ?></p>
					<button type="button" id="smie-btn-add-schedule" class="button button-primary"><?php esc_html_e( 'Add Schedule', 'smartly-import-export' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Scheduled Exports -->
		<div class="smie-card" id="smie-step-export-schedule" style="display:none">
			<div class="smie-card-header">
				<span class="smie-step-num"><span class="dashicons dashicons-download"></span></span>
				<div>
					<h2><?php esc_html_e( 'Scheduled Exports', 'smartly-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Automatically export posts on a recurring schedule and optionally receive the file by email.', 'smartly-import-export' ); ?></p>
				</div>
			</div>
			<div class="smie-card-body">
				<table class="widefat smie-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Frequency', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Notify', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Last Run', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Status', 'smartly-import-export' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'smartly-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="smie-export-schedule-body">
						<tr><td colspan="7"><?php esc_html_e( 'No scheduled exports.', 'smartly-import-export' ); ?></td></tr>
					</tbody>
				</table>
				<div class="smie-schedule-form">
					<h4><?php esc_html_e( 'Add New Export Schedule', 'smartly-import-export' ); ?></h4>
					<div class="smie-schedule-fields">
						<label>
							<?php esc_html_e( 'Name:', 'smartly-import-export' ); ?>
							<input type="text" id="smie-export-schedule-name" class="regular-text" placeholder="<?php esc_attr_e( 'My weekly export', 'smartly-import-export' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'Frequency:', 'smartly-import-export' ); ?>
							<select id="smie-export-schedule-freq">
								<option value="hourly"><?php esc_html_e( 'Hourly', 'smartly-import-export' ); ?></option>
								<option value="twicedaily"><?php esc_html_e( 'Twice Daily', 'smartly-import-export' ); ?></option>
								<option value="daily"><?php esc_html_e( 'Daily', 'smartly-import-export' ); ?></option>
								<option value="weekly" selected><?php esc_html_e( 'Weekly', 'smartly-import-export' ); ?></option>
							</select>
						</label>
						<label>
							<?php esc_html_e( 'Notify Email (optional):', 'smartly-import-export' ); ?>
							<input type="email" id="smie-export-schedule-email" class="regular-text" placeholder="<?php esc_attr_e( 'admin@example.com', 'smartly-import-export' ); ?>">
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'The schedule will export all posts of the currently selected post type. Files are stored in wp-content/uploads/smie-exports/ and auto-deleted after 7 days.', 'smartly-import-export' ); ?></p>
					<button type="button" id="smie-btn-add-export-schedule" class="button button-primary"><?php esc_html_e( 'Add Export Schedule', 'smartly-import-export' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Overlay spinner (export / template) -->
		<div id="smie-overlay" class="smie-overlay" style="display:none" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Processing', 'smartly-import-export' ); ?>">
			<div class="smie-overlay-inner">
				<span class="spinner is-active"></span>
				<span id="smie-overlay-text" aria-live="assertive"><?php esc_html_e( 'Processing…', 'smartly-import-export' ); ?></span>
			</div>
		</div>

	</div>
	<?php
}

