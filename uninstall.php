<?php
/**
 * UpdatePulse Server Uninstall Script
 *
 * This file runs when the plugin is uninstalled through the WordPress admin.
 * It removes plugin options, tables, generated files, and plugin-owned scheduled work.
 *
 * @package UPServ
 * @since 1.0.0
 */

// Prevent direct access and ensure this is a proper WordPress uninstall request.
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main uninstallation function for the UpdatePulse Server plugin.
 *
 * Removes the following plugin-owned resources:
 * - Plugin-prefixed WordPress cron events
 * - Must-use plugin optimizer files
 * - Upload directory files and folders
 * - Scheduled cleanup actions
 * - Database options
 * - Custom database tables
 *
 * @since 1.0.0
 * @return void
 */
function upserv_uninstall() {
	global $wpdb;

	// Initialize WordPress filesystem
	WP_Filesystem();

	global $wp_filesystem;

	// Remove all scheduled cron jobs belonging to the plugin
	$cron = get_option( 'cron' );

	foreach ( $cron as $job ) {

		if ( is_array( $job ) ) {
			$keys = array_keys( $job );

			foreach ( $keys as $key ) {

				if ( 0 === strpos( $key, 'upserv_' ) ) {
					wp_unschedule_hook( $key );
				}
			}
		}
	}

	// Delete any must-use plugin optimizer files
	$mu_plugin_path = trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) );
	$mu_plugins     = $wp_filesystem->dirlist( $mu_plugin_path );

	foreach ( $mu_plugins as $_mu_plugin ) {

		if ( preg_match( '/^upserv-.*-optimizer\.php$/', $_mu_plugin['name'] ) ) {
			$wp_filesystem->delete( $mu_plugin_path . $_mu_plugin['name'] );
		}
	}

	// Remove the plugin's uploads directory
	$wp_upload_dir = wp_upload_dir();
	$upserv_dir    = trailingslashit( $wp_upload_dir['basedir'] . '/updatepulse-server' );

	$wp_filesystem->delete( $upserv_dir, true );

	// Clean up Action Scheduler entries if available
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'upserv_cleanup' );
	}

	// Remove default WordPress cron hooks
	wp_unschedule_hook( 'upserv_cleanup' );

	// Delete all plugin options and database tables
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE `option_name` LIKE %s", '%upserv_%' ) );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upserv_licenses;" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}upserv_nonce;" );
}

// Execute the uninstall process
upserv_uninstall();
