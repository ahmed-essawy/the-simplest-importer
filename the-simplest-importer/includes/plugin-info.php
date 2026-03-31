<?php
/**
 * Plugin info for the Plugins page thickbox.
 *
 * @package TheSimplestImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Plugin Row Meta — "View details" link on Plugins page
 * ------------------------------------------------------------------ */

add_filter( 'plugin_row_meta', 'tsi_plugin_row_meta', 10, 2 );

/**
 * Add a "View details" thickbox link to the plugin row on the Plugins page.
 *
 * @param array  $links Existing meta links.
 * @param string $file  Plugin file path.
 * @return array Modified links.
 */
function tsi_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( TSI_PLUGIN_DIR . 'the-simplest-importer.php' ) !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
		esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=the-simplest-importer&TB_iframe=true&width=600&height=550' ) ),
		esc_attr__( 'More information about The Simplest Importer', 'the-simplest-importer' ),
		esc_html__( 'View details', 'the-simplest-importer' )
	);

	return $links;
}

add_filter( 'plugins_api', 'tsi_plugins_api_info', 10, 3 );

/**
 * Provide plugin details for the "View details" thickbox popup.
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string             $action The API action being performed.
 * @param object             $args   Plugin API arguments.
 * @return false|object
 */
function tsi_plugins_api_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}
	if ( ! isset( $args->slug ) || 'the-simplest-importer' !== $args->slug ) {
		return $result;
	}

	$info                 = new stdClass();
	$info->name           = 'The Simplest Importer';
	$info->slug           = 'the-simplest-importer';
	$info->version        = TSI_VERSION;
	$info->author         = '<a href="https://minicad.io/">Ahmed Essawy</a>';
	$info->author_profile = 'https://minicad.io/';
	$info->requires       = '5.8';
	$info->tested         = '6.9.4';
	$info->requires_php   = '7.4';
	$info->homepage       = 'https://github.com/ahmed-essawy/the-simplest-importer';

	$info->sections = array(
		'description'  => '<p>' . esc_html__( 'Import, export, and manage posts and custom post types via CSV, JSON, and XML with visual column mapping and batch processing.', 'the-simplest-importer' ) . '</p>'
			. '<h4>' . esc_html__( 'Key Features', 'the-simplest-importer' ) . '</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'CSV, JSON, and XML import with auto-format detection', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Visual drag-and-drop column mapping with auto-match', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Export to CSV or Excel XLSX', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Batch processing with progress bar and ETA', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Scheduled imports and exports via WP-Cron', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Duplicate detection, dry run mode, and import rollback', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'ACF, Yoast SEO, and Rank Math meta support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Google Sheets import, field transforms, and conditional row filtering', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge, post parent-child, and mapping profiles', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode toggle and field search in mapping table', 'the-simplest-importer' ) . '</li>'
			. '</ul>',
		'installation' => '<ol>'
			. '<li>' . esc_html__( 'Upload the plugin folder to /wp-content/plugins/', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Activate from the Plugins page', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Navigate to Tools → Simplest Importer', 'the-simplest-importer' ) . '</li>'
			. '</ol>',
		'changelog'    => '<h4>v1.4.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'Full-width dashboard layout', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dark mode with manual toggle', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Field search in mapping table', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Post parent-child import support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Extra transforms: find/replace, prepend, append, math, dates, URL encode', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Column merge mapping', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Dashboard statistics widget', 'the-simplest-importer' ) . '</li>'
			. '</ul>'
			. '<h4>v1.3.0</h4>'
			. '<ul>'
			. '<li>' . esc_html__( 'XML and JSON import support', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Excel XLSX export', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'WooCommerce product gallery image import', 'the-simplest-importer' ) . '</li>'
			. '<li>' . esc_html__( 'Error row retry', 'the-simplest-importer' ) . '</li>'
			. '</ul>',
	);

	$info->banners = array(
		'low'  => '',
		'high' => '',
	);

	return $info;
}
