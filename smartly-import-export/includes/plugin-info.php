<?php
/**
 * Plugin info for the Plugins page thickbox.
 *
 * @package SmartlyImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Plugin Row Meta — "View details" link on Plugins page
 * ------------------------------------------------------------------ */

add_filter( 'plugin_action_links_' . SMIE_PLUGIN_BASENAME, 'smie_plugin_action_links' );

/**
 * Add a "Use Me" link to the plugin action links on the Plugins page.
 *
 * @param array $links Existing action links.
 * @return array Modified links.
 */
function smie_plugin_action_links( $links ) {
	$use_me_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'tools.php?page=smartly-import-export' ) ),
		esc_html__( 'Use Me', 'smartly-import-export' )
	);

	array_unshift( $links, $use_me_link );

	return $links;
}

add_filter( 'plugin_row_meta', 'smie_plugin_row_meta', 10, 2 );

/**
 * Add a "View details" thickbox link to the plugin row on the Plugins page.
 *
 * @param array  $links Existing meta links.
 * @param string $file  Plugin file path.
 * @return array Modified links.
 */
function smie_plugin_row_meta( $links, $file ) {
	if ( SMIE_PLUGIN_BASENAME !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
		esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=smartly-import-export&TB_iframe=true&width=600&height=550' ) ),
		esc_attr__( 'More information about Smartly Import Export', 'smartly-import-export' ),
		esc_html__( 'View details', 'smartly-import-export' )
	);

	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://www.paypal.com/paypalme/ahmessawy/10USD' ),
		esc_html__( 'Donate', 'smartly-import-export' )
	);

	return $links;
}

add_filter( 'plugins_api', 'smie_plugins_api_info', 10, 3 );

/**
 * Provide plugin details for the "View details" thickbox popup.
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string             $action The API action being performed.
 * @param object             $args   Plugin API arguments.
 * @return false|object
 */
function smie_plugins_api_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}
	if ( ! isset( $args->slug ) || 'smartly-import-export' !== $args->slug ) {
		return $result;
	}

	$info                 = new stdClass();
	$info->name           = 'Smartly Import Export';
	$info->slug           = 'smartly-import-export';
	$info->version        = SMIE_VERSION;
	$info->author         = '<a href="https://minicad.io/">Ahmed Essawy</a>';
	$info->author_profile = 'https://minicad.io/';
	$info->banners        = array(
		'low'  => esc_url_raw( SMIE_PLUGIN_URL . 'assets/images/banner-772x250.jpg' ),
		'high' => esc_url_raw( SMIE_PLUGIN_URL . 'assets/images/banner-1544x500.jpg' ),
	);
	$info->requires       = '6.3';
	$info->tested         = '7.0';
	$info->requires_php   = '7.4';
	$info->donate_link    = 'https://www.paypal.com/paypalme/ahmessawy/10USD';
	$info->homepage       = 'https://github.com/ahmed-essawy/smartly-import-export';

	$info->sections = array(
		'description'  => '<p>' . esc_html__( 'Import, export, and manage posts and custom post types via CSV, JSON, and XML with visual column mapping and batch processing.', 'smartly-import-export' ) . '</p>'
			. '<h4>' . esc_html__( 'Key Features', 'smartly-import-export' ) . '</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'CSV, JSON, and XML import with auto-format detection', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Visual drag-and-drop column mapping with auto-match', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Export to CSV or Excel XLSX', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Batch processing with progress bar and ETA', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Scheduled imports and exports via WP-Cron', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Duplicate detection, dry run mode, and import rollback', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'ACF, Yoast SEO, and Rank Math meta support', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Google Sheets import, field transforms, and conditional row filtering', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge, post parent-child, and mapping profiles', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode toggle and field search in mapping table', 'smartly-import-export' ) . '</li>'
			. '</ul>',
		'installation' => '<h4>' . esc_html__( 'Installation from within WordPress', 'smartly-import-export' ) . '</h4>'
			. '<ol>'
			. '<li>' . esc_html__( 'Visit Plugins > Add New.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Search for Smartly Import Export.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Install and activate the Smartly Import Export plugin.', 'smartly-import-export' ) . '</li>'
			. '</ol>'
			. '<h4>' . esc_html__( 'Manual installation', 'smartly-import-export' ) . '</h4>'
			. '<ol>'
			. '<li>' . esc_html__( 'Upload the entire smartly-import-export folder to the /wp-content/plugins/ directory.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Visit Plugins.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Activate the Smartly Import Export plugin.', 'smartly-import-export' ) . '</li>'
			. '</ol>',
		'changelog'    => '<h4>v1.4.2</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Renamed the plugin to Smartly Import Export with the new smartly-import-export slug and smie_ prefix.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Moved the single-post export JavaScript into the proper enqueue flow.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Removed deprecated XML entity-loader handling while keeping secure XML parsing.', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Added upgrade-safe migration for legacy options, cron events, and extension hooks.', 'smartly-import-export' ) . '</li>'
			. '</ul>'
			. '<h4>v1.4.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Full-width dashboard layout', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode with manual toggle', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Field search in mapping table', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Post parent-child import support', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Extra transforms: find/replace, prepend, append, math, dates, URL encode', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge mapping', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Dashboard statistics widget', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'View Details popup on Plugins page', 'smartly-import-export' ) . '</li>'
			. '</ul>'
			. '<h4>v1.3.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'XML and JSON import support', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Excel XLSX export', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'WooCommerce product gallery image import', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Hierarchical taxonomy import', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Error row retry', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Real-time mapping preview', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Import progress ETA', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Accessibility improvements', 'smartly-import-export' ) . '</li>'
			. '</ul>'
			. '<h4>v1.2.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'ACF support with update_field()', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'SEO meta support for Yoast SEO and Rank Math', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Google Sheets import', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Conditional row filtering', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Scheduled exports with email attachment', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Single post export meta box', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Email notifications for scheduled imports', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( '8 new developer hooks', 'smartly-import-export' ) . '</li>'
			. '</ul>'
			. '<h4>v1.1.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Scheduled imports via WP-Cron', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Import history and rollback', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Duplicate detection', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Field transforms', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Mapping profiles', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'CSV validation and dry run mode', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Multi-file upload', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Delimiter auto-detection', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Selective column export and status filter', 'smartly-import-export' ) . '</li>'
			. '</ul>'
			. '<h4>v1.0.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Initial release', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'CSV import with visual column mapping', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Batch import with progress bar', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'CSV export with multiple modes', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Drag-and-drop upload and URL fetch', 'smartly-import-export' ) . '</li>'
			. '<li>' . esc_html__( 'Featured image and taxonomy support', 'smartly-import-export' ) . '</li>'
			. '</ul>',
	);

	$info->last_updated = gmdate( 'Y-m-d' );

	return $info;
}
