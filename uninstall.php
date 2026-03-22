<?php
/**
 * Uninstall TranslateAI for TranslatePress
 *
 * Removes all plugin options from the database when the plugin is deleted.
 *
 * @package TranslateAI_For_TranslatePress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$taitp_options = [
	'taitp_main_model',
	'taitp_judge_model',
	'taitp_api_url',
	'taitp_api_key',
	'taitp_source_lang',
	'taitp_target_lang',
	'taitp_selected_table',
	'taitp_batch_enabled',
	'taitp_batch_size',
	'taitp_batch_delay_ms',
	'taitp_site_context',
	'taitp_judge_endpoint_enabled',
	'taitp_judge_api_url',
	'taitp_judge_api_key',
	'taitp_mode',
	'taitp_api_service',
	'taitp_judge_api_service',
];

foreach ( $taitp_options as $taitp_option ) {
	delete_option( $taitp_option );
}

// Clean up any leftover retry transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_taitp_retry_%'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_taitp_retry_%'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_taitp_err_%'" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_taitp_err_%'" );