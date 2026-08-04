<?php
/**
 * UpdatePulse Server Core Functions
 *
 * This file contains essential functions for the UpdatePulse Server plugin.
 * It handles updates, licensing, package management and other core functionality.
 *
 * @package UPServ
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Anyape\UpdatePulse\Server\Nonce\Nonce;
use Anyape\UpdatePulse\Server\API\License_API;
use Anyape\UpdatePulse\Server\API\Webhook_API;
use Anyape\UpdatePulse\Server\API\Update_API;
use Anyape\UpdatePulse\Server\API\Package_API;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Manager\Package_Manager;
use Anyape\UpdatePulse\Server\UPServ;

/*******************************************************************
 * Utility functions
 *******************************************************************/

if ( ! function_exists( 'upserv_get_vcs_name' ) ) {
	/**
	 * Get the formatted name of a version control system
	 *
	 * Returns the name of the given VCS type formatted according to the context.
	 * When context is 'view', returns the translatable string meant for display.
	 * When context is something else, returns the plain string name.
	 *
	 * @param string $type    The VCS type ('github', 'gitlab', 'bitbucket', 'gitea')
	 * @param string $context The context for the return value ('view' or other)
	 *
	 * @return string|null The VCS name formatted according to context, or null if invalid type with non-view context
	 */
	function upserv_get_vcs_name( $type, $context = 'view' ) {

		switch ( $type ) {
			case 'github':
				return 'view' === $context ? __( 'GitHub', 'updatepulse-server' ) : 'GitHub';
			case 'gitlab':
				return 'view' === $context ? __( 'GitLab', 'updatepulse-server' ) : 'GitLab';
			case 'bitbucket':
				return 'view' === $context ? __( 'Bitbucket', 'updatepulse-server' ) : 'Bitbucket';
			case 'gitea':
				return 'view' === $context ? __( 'Gitea', 'updatepulse-server' ) : 'Gitea';
			default:
				return 'view' === $context ? __( 'Undefined', 'updatepulse-server' ) : null;
		}
	}
}

/*******************************************************************
 * Options functions
 *******************************************************************/

if ( ! function_exists( 'upserv_get_options' ) ) {
	/**
	 * Retrieves all plugin options
	 *
	 * Gets the complete options array from the main UPServ instance.
	 *
	 * @since 1.0
	 *
	 * @return array All plugin options
	 */
	function upserv_get_options() {
		return UPServ::get_instance()->get_options();
	}
}

if ( ! function_exists( 'upserv_update_options' ) ) {
	/**
	 * Updates all plugin options
	 *
	 * Replaces the entire options array with the provided options.
	 *
	 * @since 1.0
	 *
	 * @param array $options The new options to save
	 * @return bool True on success, false on failure
	 */
	function upserv_update_options( $options ) {
		return UPServ::get_instance()->update_options( $options );
	}
}

if ( ! function_exists( 'upserv_get_option' ) ) {
	/**
	 * Gets a specific option by path
	 *
	 * Retrieves an option value using slash notation path.
	 *
	 * @since 1.0
	 *
	 * @param string $path     The path to the option using slash notation
	 * @param mixed  $_default Default value if option doesn't exist
	 * @return mixed The option value or default if not found
	 */
	function upserv_get_option( $path, $_default = null ) {
		return UPServ::get_instance()->get_option( $path, $_default );
	}
}

if ( ! function_exists( 'upserv_set_option' ) ) {
	/**
	 * Sets a specific option by path
	 *
	 * Set an option value within the current request using slash notation path.
	 * Does NOT commit the changes to persistence.
	 * To persist the data, call @see upserv_update_options()
	 * with the return value of this function.
	 *
	 * @since 1.0
	 *
	 * @param string $path  The path to the option using slash notation
	 * @param mixed  $value The value to set
	 * @return array The updated options array
	 */
	function upserv_set_option( $path, $value ) {
		return UPServ::get_instance()->set_option( $path, $value );
	}
}

if ( ! function_exists( 'upserv_update_option' ) ) {
	/**
	 * Updates a specific option by path
	 *
	 * Updates an existing option value using slash notation path.
	 * Commits the changes to persistence.
	 *
	 * @since 1.0
	 *
	 * @param string $path  The path to the option using slash notation
	 * @param mixed  $value The value to set
	 * @return bool True on success, false on failure
	 */
	function upserv_update_option( $path, $value ) {
		return UPServ::get_instance()->update_option( $path, $value );
	}
}

if ( ! function_exists( 'upserv_assets_suffix' ) ) {
	/**
	 * Gets the appropriate asset file suffix based on debug mode
	 *
	 * Returns an empty string in debug mode, or '.min' in production,
	 * to be used for loading appropriate CSS/JS file versions.
	 *
	 * @since 1.0
	 *
	 * @return string '.min' if WP_DEBUG is false, empty string otherwise
	 */
	function upserv_assets_suffix() {
		return (bool) ( constant( 'WP_DEBUG' ) ) ? '' : '.min';
	}
}

/*******************************************************************
 * Doing API functions
 *******************************************************************/

if ( ! function_exists( 'upserv_is_doing_license_api_request' ) ) {
	/**
	 * Determines if the current request is a License API request
	 *
	 * Checks whether the current request is made by a client plugin or theme
	 * interacting with the plugin's license API.
	 *
	 * @since 1.0
	 *
	 * @return bool True if the current request is a License API request, false otherwise
	 */
	function upserv_is_doing_license_api_request() {
		return License_API::is_doing_api_request();
	}
}

if ( ! function_exists( 'upserv_is_doing_update_api_request' ) ) {
	/**
	 * Determine whether the current request is made by a client plugin, theme, or generic package interacting with the plugin's API.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` if the current request is a client plugin, theme, or generic package interacting with the plugin's API, `false` otherwise
	 */
	function upserv_is_doing_update_api_request() {
		return Update_API::is_doing_api_request();
	}
}

if ( ! function_exists( 'upserv_is_doing_webhook_api_request' ) ) {
	/**
	 * Determine whether the current request is made by a Webhook.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` if the current request is made by a Webhook, `false` otherwise
	 */
	function upserv_is_doing_webhook_api_request() {
		return Webhook_API::is_doing_api_request();
	}
}

if ( ! function_exists( 'upserv_is_doing_package_api_request' ) ) {
	/**
	 * Determine whether the current request is made by a remote client interacting with the plugin's package API.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` if the current request is made by a remote client interacting with the plugin's package API, `false` otherwise
	 */
	function upserv_is_doing_package_api_request() {
		return Package_API::is_doing_api_request();
	}
}

if ( ! function_exists( 'upserv_is_doing_api_request' ) ) {
	/**
	 * Determine whether the current request is made by a remote client interacting with any of the APIs.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` if the current request is made by a remote client interacting with any of the APIs, `false` otherwise
	 */
	function upserv_is_doing_api_request() {
		$mu_doing_api   = wp_cache_get( 'upserv_mu_doing_api', 'updatepulse-server' );
		$is_api_request = $mu_doing_api ?
			$mu_doing_api :
			(
				upserv_is_doing_license_api_request() ||
				upserv_is_doing_update_api_request() ||
				upserv_is_doing_webhook_api_request() ||
				upserv_is_doing_package_api_request()
			);

		return apply_filters( 'upserv_is_api_request', $is_api_request );
	}
}

/*******************************************************************
 * Data directories functions
 *******************************************************************/

if ( ! function_exists( 'upserv_get_data_dir' ) ) {
	/**
	 * Get the path to a specific directory within the plugin's content directory.
	 *
	 * @since 1.0
	 *
	 * @param string $dir The directory to get the path for
	 * @return string The path to the specified directory within the plugin's content directory
	 */
	function upserv_get_data_dir( $dir ) {
		return Data_Manager::get_data_dir( $dir );
	}
}

if ( ! function_exists( 'upserv_get_root_data_dir' ) ) {
	function upserv_get_root_data_dir() {
		return Data_Manager::get_data_dir();
	}
}

if ( ! function_exists( 'upserv_get_packages_data_dir' ) ) {
	/**
	 * Get the path to the packages directory on the file system.
	 *
	 * @since 1.0
	 *
	 * @return string The path to the packages directory on the file system
	 */
	function upserv_get_packages_data_dir() {
		return Data_Manager::get_data_dir( 'packages' );
	}
}

if ( ! function_exists( 'upserv_get_logs_data_dir' ) ) {
	/**
	 * Get the path to the plugin's log directory.
	 *
	 * @since 1.0
	 *
	 * @return string The path to the plugin's log directory
	 */
	function upserv_get_logs_data_dir() {
		return Data_Manager::get_data_dir( 'logs' );
	}
}

if ( ! function_exists( 'upserv_get_cache_data_dir' ) ) {
	/**
	 * Get the path to the plugin's package cache directory.
	 *
	 * @since 1.0
	 *
	 * @return string The path to the plugin's package cache directory
	 */
	function upserv_get_cache_data_dir() {
		return Data_Manager::get_data_dir( 'cache' );
	}
}

if ( ! function_exists( 'upserv_get_package_metadata_data_dir' ) ) {
	/**
	 * Get the path to the plugin's package metadata directory.
	 *
	 * @since 1.0
	 *
	 * @return string The path to the plugin's package metadata directory
	 */
	function upserv_get_package_metadata_data_dir() {
		return Data_Manager::get_data_dir( 'metadata' );
	}
}

/*******************************************************************
 * Whitelisting functions
 *******************************************************************/

if ( ! function_exists( 'upserv_is_package_whitelisted' ) ) {
	/**
	 * Determine whether a package is whitelisted.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @return bool `true` if the package is whitelisted, `false` otherwise
	 */
	function upserv_is_package_whitelisted( $package_slug ) {
		return Package_Manager::get_instance()->is_package_whitelisted( $package_slug );
	}
}

if ( ! function_exists( 'upserv_whitelist_package' ) ) {
	/**
	 * Whitelist a package.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @return bool `true` if the package was successfully whitelisted, `false` otherwise
	 */
	function upserv_whitelist_package( $package_slug ) {
		return Package_Manager::get_instance()->whitelist_package( $package_slug );
	}
}

if ( ! function_exists( 'upserv_unwhitelist_package' ) ) {
	/**
	 * Unwhitelist a package.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @return bool `true` if the package was successfully unwhitelisted, `false` otherwise
	 */
	function upserv_unwhitelist_package( $package_slug ) {
		return Package_Manager::get_instance()->unwhitelist_package( $package_slug );
	}
}

/*******************************************************************
 * Package Metadata functions
 *******************************************************************/

if ( ! function_exists( 'upserv_get_package_metadata' ) ) {
	/**
	 * Get metadata of a package.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @param bool   $json_encode  Whether to return a JSON object (default) or a PHP associative array
	 * @return mixed The package metadata
	 */
	function upserv_get_package_metadata( $package_slug, $json_encode = false ) {
		return Package_Manager::get_instance()->get_package_metadata(
			$package_slug,
			$json_encode
		);
	}
}

if ( ! function_exists( 'upserv_set_package_metadata' ) ) {
	/**
	 * Set metadata of a package.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @param array  $metadata     The metadata to set
	 * @return bool `true` if the metadata was successfully set, `false` otherwise
	 */
	function upserv_set_package_metadata( $package_slug, $metadata ) {
		return Package_Manager::get_instance()->set_package_metadata(
			$package_slug,
			$metadata
		);
	}
}

/*******************************************************************
 * Cleanup functions
 *******************************************************************/

if ( ! function_exists( 'upserv_force_cleanup_cache' ) ) {
	/**
	 * Force clean up the `cache` plugin data.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` in case of success, `false` otherwise
	 */
	function upserv_force_cleanup_cache() {
		return Data_Manager::maybe_cleanup( 'cache', true );
	}
}

if ( ! function_exists( 'upserv_force_cleanup_logs' ) ) {
	/**
	 * Force clean up the `logs` plugin data.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` in case of success, `false` otherwise
	 */
	function upserv_force_cleanup_logs() {
		return Data_Manager::maybe_cleanup( 'logs', true );
	}
}

if ( ! function_exists( 'upserv_force_cleanup_tmp' ) ) {
	/**
	 * Force clean up the `tmp` plugin data.
	 *
	 * @since 1.0
	 *
	 * @return bool `true` in case of success, `false` otherwise
	 */
	function upserv_force_cleanup_tmp() {
		return Data_Manager::maybe_cleanup( 'tmp', true );
	}
}

/*******************************************************************
 * VCS Package functions
 *******************************************************************/

if ( ! function_exists( 'upserv_check_remote_plugin_update' ) ) {
	/**
	 * Determine whether the remote plugin package is an updated version compared to one on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $slug The slug of the plugin package to check
	 * @return bool `true` if the remote plugin package is an updated version, `false` otherwise. If the local package does not exist, returns `true`
	 */
	function upserv_check_remote_plugin_update( $slug ) {
		return upserv_check_remote_package_update( $slug, 'plugin' );
	}
}

if ( ! function_exists( 'upserv_check_remote_theme_update' ) ) {
	/**
	 * Determine whether the remote theme package is an updated version compared to the one on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $slug The slug of the theme package to check
	 * @return bool `true` if the remote theme package is an updated version, `false` otherwise. If the package does not exist on the file system, returns `true`
	 */
	function upserv_check_remote_theme_update( $slug ) {
		return upserv_check_remote_package_update( $slug, 'theme' );
	}
}

if ( ! function_exists( 'upserv_check_remote_package_update' ) ) {
	/**
	 * Determine whether the remote package is an updated version compared to the one on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $slug The slug of the package to check
	 * @param string $type The type of the package
	 * @return bool `true` if the remote package is an updated version, `false` otherwise. If the local package does not exist, returns `true`
	 */
	function upserv_check_remote_package_update( $slug, $type ) {
		$api = Update_API::get_instance();

		return $api->check_remote_update( $slug, $type );
	}
}

if ( ! function_exists( 'upserv_download_remote_plugin' ) ) {
	/**
	 * Download a plugin package from the Version Control System to the package directory on the file system.
	 * If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.
	 *
	 * @since 1.0
	 *
	 * @param string $slug     The slug of the plugin package to download
	 * @param string $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
	 * @param string $branch  The branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`
	 * @return bool `true` if the plugin package was successfully downloaded, `false` otherwise
	 */
	function upserv_download_remote_plugin( $slug, $vcs_url = false, $branch = 'main' ) {
		return upserv_download_remote_package( $slug, 'plugin', $vcs_url, $branch );
	}
}

if ( ! function_exists( 'upserv_download_remote_theme' ) ) {
	/**
	 * Download a theme package from the Version Control System to the package directory on the file system.
	 * If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.
	 *
	 * @since 1.0
	 *
	 * @param string $slug     The slug of the theme package to download
	 * @param string $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
	 * @param string $branch  The branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`
	 * @return bool `true` if the theme package was successfully downloaded, `false` otherwise
	 */
	function upserv_download_remote_theme( $slug, $vcs_url = false, $branch = 'main' ) {
		return upserv_download_remote_package( $slug, 'theme', $vcs_url, $branch );
	}
}

if ( ! function_exists( 'upserv_download_remote_package' ) ) {
	/**
	 * Download a package from the Version Control System to the package directory on the file system.
	 * If `$vcs_url` and `$branch` are provided, the plugin will attempt to get an existing VCS configuration and register the package with it.
	 *
	 * @since 1.0
	 *
	 * @param string $slug     The slug of the package to download
	 * @param string $type     The type of the package; default to `'generic'`
	 * @param string $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
	 * @param string $branch  The branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`
	 * @return bool|WP_Error `WP_Error` if provided VCS information is invalid, `true` if the package was successfully downloaded, `false` otherwise
	 */
	function upserv_download_remote_package( $slug, $type = 'generic', $vcs_url = false, $branch = 'main' ) {

		if ( $vcs_url ) {
			$vcs_configs     = upserv_get_option( 'vcs', array() );
			$meta            = upserv_get_package_metadata( $slug );
			$meta['type']    = $type;
			$meta['vcs_key'] = hash( 'sha256', trailingslashit( $vcs_url ) . '|' . $branch );
			$meta['origin']  = 'vcs';

			if ( isset( $vcs_configs[ $meta['vcs_key'] ] ) ) {
				upserv_set_package_metadata( $slug, $meta );
			} else {
				return new WP_Error(
					'invalid_vcs',
					__( 'The provided VCS information is not valid', 'updatepulse-server' )
				);
			}
		}

		$api = Update_API::get_instance();

		return $api->download_remote_package( $slug, $type, true );
	}
}

if ( ! function_exists( 'upserv_get_package_vcs_config' ) ) {
	/**
	 * Get the Version Control System (VCS) configuration for a package.
	 *
	 * @since 1.0
	 *
	 * @param string $slug The slug of the package
	 * @return array The VCS configuration for the package
	 */
	function upserv_get_package_vcs_config( $slug ) {
		$meta = upserv_get_package_metadata( $slug );

		return isset( $meta['vcs_key'] ) ? upserv_get_option( 'vcs/' . $meta['vcs_key'], array() ) : array();
	}
}

/*******************************************************************
 * Package functions
 *******************************************************************/

if ( ! function_exists( 'upserv_delete_package' ) ) {
	/**
	 * Delete a package on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $slug The slug of the package to delete
	 * @return bool `true` if the package was successfully deleted, `false` otherwise
	 */
	function upserv_delete_package( $slug ) {
		$package_manager = Package_Manager::get_instance();

		return (bool) $package_manager->delete_packages_bulk( array( $slug ) );
	}
}

if ( ! function_exists( 'upserv_get_package_info' ) ) {
	/**
	 * Get information about a package on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @param bool   $json_encode  Whether to return a JSON object (default) or a PHP associative array
	 * @return mixed The package information as a PHP associative array or a JSON object
	 */
	function upserv_get_package_info( $package_slug, $json_encode = true ) {
		$result          = $json_encode ? '{}' : array();
		$package_manager = Package_Manager::get_instance();
		$package_info    = $package_manager->get_package_info( $package_slug );

		if ( $package_info ) {
			$result = $json_encode ? wp_json_encode( $package_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : $package_info;
		}

		return $result;
	}
}

if ( ! function_exists( 'upserv_is_package_require_license' ) ) {
	/**
	 * Determine whether a package requires a license key.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @return bool `true` if the package requires a license key, `false` otherwise
	 */
	function upserv_is_package_require_license( $package_slug ) {
		$api = License_API::get_instance();

		return $api->is_package_require_license( $package_slug );
	}
}

if ( ! function_exists( 'upserv_get_batch_package_info' ) ) {
	/**
	 * Get batch information of packages on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $search      Search string to be used in package's slug and package's name (case insensitive)
	 * @param bool   $json_encode Whether to return a JSON object (default) or a PHP associative array
	 * @return mixed The batch information as a PHP associative array or a JSON object; each entry is formatted like in `upserv_get_package_info`
	 */
	function upserv_get_batch_package_info( $search, $json_encode = true ) {
		$result          = $json_encode ? '{}' : array();
		$package_manager = Package_Manager::get_instance();
		$package_info    = $package_manager->get_batch_package_info( $search );

		if ( $package_info ) {
			$result = $json_encode ? wp_json_encode( $package_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : $package_info;
		}

		return $result;
	}
}

if ( ! function_exists( 'upserv_download_local_package' ) ) {
	/**
	 * Start a download of a package from the file system and exits.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug  The slug of the package
	 * @param string $package_path  The path of the package on the local file system - if `null`, will attempt to find it using `upserv_get_local_package_path( $package_slug )`; default `null`
	 * @param bool   $exit_or_die Whether to exit or die after the download; default `true`
	 * @return void
	 */
	function upserv_download_local_package( $package_slug, $package_path = null, $exit_or_die = true ) {
		$package_manager = Package_Manager::get_instance();

		if ( null === $package_path ) {
			$package_path = upserv_get_local_package_path( $package_slug );
		}

		$package_manager->trigger_packages_download( $package_slug, $package_path, $exit_or_die );
	}
}

if ( ! function_exists( 'upserv_get_local_package_path' ) ) {
	/**
	 * Get the path of a plugin, theme, or generic package on the file system.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug The slug of the package
	 * @return string|false The path of the package on the local file system or `false` if it does not exist
	 */
	function upserv_get_local_package_path( $package_slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			wp_die( __FUNCTION__ . ' - WP_Filesystem not available.' );
		}

		$package_path = trailingslashit( Data_Manager::get_data_dir( 'packages' ) ) . $package_slug . '.zip';

		if ( $wp_filesystem->is_file( $package_path ) ) {
			return $package_path;
		}

		return false;
	}
}

/*******************************************************************
 * Licenses functions
 *******************************************************************/
if ( ! function_exists( 'upserv_browse_licenses' ) ) {
	/**
	 * Browse the license records filtered using various criteria.
	 *
	 * @since 1.0
	 *
	 * @param array $license_query The License Query
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#the-license-query
	 * @return array An array of license objects matching the License Query.
	 */
	function upserv_browse_licenses( $license_query ) {
		$api = License_API::get_instance();

		return $api->browse( $license_query );
	}
}

if ( ! function_exists( 'upserv_read_license' ) ) {
	/**
	 * Read a license record.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data The License payload data.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#read
	 * @return mixed An object in case of success or an empty array otherwise.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#read the object is the decoded value of the JSON string
	 */
	function upserv_read_license( $license_data ) {
		$api = License_API::get_instance();

		return $api->read( $license_data );
	}
}

if ( ! function_exists( 'upserv_add_license' ) ) {
	/**
	 * Add a license.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data The License payload data
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#add
	 * @return mixed An object in case of success or an array of errors otherwise.
	 * * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#add the object is the decoded value of the JSON string
	 */
	function upserv_add_license( $license_data ) {

		if ( is_array( $license_data ) && ! isset( $license_data['data'] ) ) {
			$license_data['data'] = array();
		}

		$license_data['data']['operation_timestamp'] = time();
		$license_data['data']['operation']           = 'add';
		$license_data['data']['operation_id']        = bin2hex( random_bytes( 16 ) );
		$api = License_API::get_instance();

		return $api->add( $license_data );
	}
}

if ( ! function_exists( 'upserv_edit_license' ) ) {
	/**
	 * Edit a license record.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data The License payload data.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#edit
	 * @return mixed An object in case of success or an array of errors otherwise.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#edit the object is the decoded value of the JSON string
	 */
	function upserv_edit_license( $license_data ) {

		if ( is_array( $license_data ) && ! isset( $license_data['data'] ) ) {
			$license_data['data'] = array();
		}

		$license_data['data']['operation_timestamp'] = time();
		$license_data['data']['operation']           = 'edit';
		$license_data['data']['operation_id']        = bin2hex( random_bytes( 16 ) );
		$api = License_API::get_instance();

		return $api->edit( $license_data );
	}
}

if ( ! function_exists( 'upserv_delete_license' ) ) {
	/**
	 * Delete a license record.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data The License payload data.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#delete
	 * @return mixed An object in case of success or an empty array otherwise.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#delete the object is the decoded value of the JSON string
	 */
	function upserv_delete_license( $license_data ) {

		if ( is_array( $license_data ) && ! isset( $license_data['data'] ) ) {
			$license_data['data'] = array();
		}

		$license_data['data']['operation_timestamp'] = time();
		$license_data['data']['operation']           = 'delete';
		$license_data['data']['operation_id']        = bin2hex( random_bytes( 16 ) );
		$api = License_API::get_instance();

		return $api->delete( $license_data );
	}
}

if ( ! function_exists( 'upserv_check_license' ) ) {
	/**
	 * Check a License information.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data An associative array with a single value - `array( 'license_key' => 'key_of_the_license_to_check' )`.
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#check
	 * @return mixed An object in case of success, and associative array in case of failure
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#check the object is the decoded value of the JSON string
	 */
	function upserv_check_license( $license_data ) {
		$api = License_API::get_instance();

		return $api->check( $license_data );
	}
}

if ( ! function_exists( 'upserv_activate_license' ) ) {
	/**
	 * Activate a License.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data An associative array with 2 values - `array( 'license_key' => 'key_of_the_license_to_activate', 'allowed_domains' => 'domain_to_activate' )`.
	 * @return mixed An object in case of success, and associative array in case of failure
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#activate the object is the decoded value of the JSON string
	 */
	function upserv_activate_license( $license_data ) {
		$api = License_API::get_instance();

		return $api->activate( $license_data );
	}
}

if ( ! function_exists( 'upserv_deactivate_license' ) ) {
	/**
	 * Deactivate a License.
	 *
	 * @since 1.0
	 *
	 * @param array $license_data An associative array with 2 values - `array( 'license_key' => 'key_of_the_license_to_deactivate', 'allowed_domains' => 'domain_to_deactivate' )`.
	 * @return mixed An object in case of success, and associative array in case of failure
	 * @see https://github.com/Anyape/updatepulse-server/blob/main/docs/licenses.md#deactivate the object is the decoded value of the JSON string
	 */
	function upserv_deactivate_license( $license_data ) {
		$api = License_API::get_instance();

		return $api->deactivate( $license_data );
	}
}

/*******************************************************************
 * Template functions
 *******************************************************************/

if ( ! function_exists( 'upserv_get_template' ) ) {
	/**
	 * Loads a template file from the plugin's template directory
	 *
	 * This function locates and loads template files for the frontend of the plugin.
	 * It applies filters to the template name and arguments, sets up query variables,
	 * and then passes the template to the UPServ template loader.
	 *
	 * @since 1.0
	 *
	 * @param string  $template_name The name of the template to load
	 * @param array   $args          Arguments to pass to the template
	 * @param boolean $load          Whether to load the template file
	 * @param boolean $require_file  Whether to require or require_once the template file
	 * @return string|bool           Path to the template file or false if not found
	 */
	function upserv_get_template( $template_name, $args = array(), $load = true, $require_file = false ) {
		$template_name = apply_filters( 'upserv_get_template_name', $template_name, $args );
		$template_args = apply_filters( 'upserv_get_template_args', $args, $template_name );

		if ( ! empty( $template_args ) ) {

			foreach ( $template_args as $key => $arg ) {
				$key = is_numeric( $key ) ? 'var_' . $key : $key;

				set_query_var( $key, $arg );
			}
		}

		return UPServ::locate_template( $template_name, $load, $require_file );
	}
}

if ( ! function_exists( 'upserv_get_admin_template' ) ) {
	/**
	 * Loads a template file from the plugin's admin template directory
	 *
	 * This function locates and loads template files for the admin area of the plugin.
	 * It applies filters to the template name and arguments, sets up query variables,
	 * and then passes the template to the UPServ admin template loader.
	 *
	 * @since 1.0
	 *
	 * @param string  $template_name The name of the admin template to load
	 * @param array   $args          Arguments to pass to the template
	 * @param boolean $load          Whether to load the template file
	 * @param boolean $require_file  Whether to require or require_once the template file
	 * @return string|bool           Path to the template file or false if not found
	 */
	function upserv_get_admin_template( $template_name, $args = array(), $load = true, $require_file = false ) {
		$template_name = apply_filters( 'upserv_get_admin_template_name', $template_name, $args );
		$template_args = apply_filters( 'upserv_get_admin_template_args', $args, $template_name );

		if ( ! empty( $template_args ) ) {

			foreach ( $template_args as $key => $arg ) {
				$key = is_numeric( $key ) ? 'var_' . $key : $key;

				set_query_var( $key, $arg );
			}
		}

		return UPServ::locate_admin_template( $template_name, $load, $require_file );
	}
}

/*******************************************************************
 * Nonce functions
 *******************************************************************/

if ( ! function_exists( 'upserv_init_nonce_auth' ) ) {
	/**
	 * Initialize the nonce authentication.
	 *
	 * @since 1.0
	 *
	 * @param string $private_auth_key The private authentication key
	 */
	function upserv_init_nonce_auth( $private_auth_key ) {
		Nonce::init_auth( $private_auth_key );
	}
}

if ( ! function_exists( 'upserv_create_nonce' ) ) {
	/**
	 * Create a nonce
	 *
	 * Creates a cryptographic token - allows creation of tokens that are true one-time-use nonces, with custom expiry length and custom associated data.
	 *
	 * @since 1.0
	 *
	 * @param bool   $true_nonce    Whether the nonce is one-time-use; default `true`
	 * @param int    $expiry_length The number of seconds after which the nonce expires; default `UPServ_Nonce::DEFAULT_EXPIRY_LENGTH` - 30 seconds
	 * @param array  $data          Custom data to save along with the nonce; set an element with key `permanent` to a truthy value to create a nonce that never expires; default `array()`
	 * @param int    $return_type   Whether to return the nonce, or an array of information; default `UPServ_Nonce::NONCE_ONLY`; other accepted value is `UPServ_Nonce::NONCE_INFO_ARRAY`
	 * @param bool   $store         Whether to store the nonce, or let a third party mechanism take care of it; default `true`
	 * @return bool|string|array `false` in case of failure; the cryptographic token string if `$return_type` is set to `UPServ_Nonce::NONCE_ONLY`; an array of information if `$return_type` is set to `UPServ_Nonce::NONCE_INFO_ARRAY`
	 */
	function upserv_create_nonce(
		$true_nonce = true,
		$expiry_length = Nonce::DEFAULT_EXPIRY_LENGTH,
		$data = array(),
		$return_type = Nonce::NONCE_ONLY,
		$store = true
	) {
		return Nonce::create_nonce( $true_nonce, $expiry_length, $data, $return_type, $store );
	}
}

if ( ! function_exists( 'upserv_get_nonce_expiry' ) ) {
	/**
	 * Get the expiry timestamp of a nonce.
	 *
	 * @since 1.0
	 *
	 * @param string $nonce The nonce
	 * @return int The expiry timestamp
	 */
	function upserv_get_nonce_expiry( $nonce ) {
		return Nonce::get_nonce_expiry( $nonce );
	}
}

if ( ! function_exists( 'upserv_get_nonce_data' ) ) {
	/**
	 * Get the data stored along a nonce.
	 *
	 * @since 1.0
	 *
	 * @param string $nonce The nonce
	 * @return array The data stored along the nonce
	 */
	function upserv_get_nonce_data( $nonce ) {
		return Nonce::get_nonce_data( $nonce );
	}
}

if ( ! function_exists( 'upserv_validate_nonce' ) ) {
	/**
	 * Check whether the value is a valid nonce.
	 *
	 * @since 1.0
	 *
	 * @param string $value The value to check
	 * @return bool Whether the value is a valid nonce
	 */
	function upserv_validate_nonce( $value ) {
		return Nonce::validate_nonce( $value );
	}
}

if ( ! function_exists( 'upserv_delete_nonce' ) ) {
	/**
	 * Delete a nonce from the system if the corresponding value exists.
	 *
	 * @since 1.0
	 *
	 * @param string $value The value to delete
	 * @return bool Whether the nonce was deleted
	 */
	function upserv_delete_nonce( $value ) {
		return Nonce::delete_nonce( $value );
	}
}

if ( ! function_exists( 'upserv_clear_nonces' ) ) {
	/**
	 * Clear expired nonces from the system.
	 *
	 * @since 1.0
	 *
	 * @return bool Whether some nonces were cleared
	 */
	function upserv_clear_nonces() {
		return Nonce::upserv_nonce_cleanup();
	}
}

if ( ! function_exists( 'upserv_build_nonce_api_signature' ) ) {
	/**
	* Build credentials and signature for UpdatePulse Server Nonce API.
	*
	* @since 1.0
	*
	* @param string $api_key_id The ID of the Private API Key
	* @param string $api_key The Private API Key - will not be sent over the Internet
	* @param int    $timestamp The timestamp used to limit the validity of the signature (validity is MINUTE_IN_SECONDS)
	* @param int    $payload The payload to acquire a reusable token or a true nonce
	* @return array An array with keys `credentials` and `signature`
	*/
	function upserv_build_nonce_api_signature( $api_key_id, $api_key, $timestamp, $payload ) {
		unset( $payload['api_signature'] );
		unset( $payload['api_credentials'] );

		( function ( &$arr ) {
			$recur_ksort = function ( &$arr ) use ( &$recur_ksort ) {

				foreach ( $arr as &$value ) {

					if ( is_array( $value ) ) {
						$recur_ksort( $value );
					}
				}

				ksort( $arr );
			};

			$recur_ksort( $arr );
		} )( $payload );

		$str         = base64_encode( $api_key_id . json_encode( $payload, JSON_NUMERIC_CHECK ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$credentials = $timestamp . '|' . $api_key_id;
		$time_key    = hash_hmac( 'sha256', $timestamp, $api_key, true );
		$signature   = hash_hmac( 'sha256', $str, $time_key );

		return array(
			'credentials' => $credentials,
			'signature'   => $signature,
		);
	}
}

/*******************************************************************
 * Webhook functions
 *******************************************************************/

if ( ! function_exists( 'upserv_schedule_webhook' ) ) {
	/**
	 * Schedule an event notification to be sent to registered Webhook URLs at next cron run.
	 *
	 * @since 1.0
	 *
	 * @param array   $payload    The data used to schedule the notification
	 * @param string  $event_type The type of event; the payload will only be delivered to URLs subscribed to this type
	 * @param boolean $instant    Whether to send the notification immediately; default `false`
	 * @return null|WP_Error      `null` in case of success, a `WP_Error` otherwise
	 */
	function upserv_schedule_webhook( $payload, $event_type, $instant = false ) {

		if ( isset( $payload['event'], $payload['content'] ) ) {
			$api = Webhook_API::get_instance();

			return $api->schedule_webhook( $payload, $event_type, $instant );
		}

		return new WP_Error(
			__FUNCTION__,
			__( 'The webhook payload must contain an event string and a content.', 'updatepulse-server' )
		);
	}
}

if ( ! function_exists( 'upserv_fire_webhook' ) ) {
	/**
	 * Immediately send a event notification to `$url`, signed with `$secret` with resulting hash stored in `X-UpdatePulse-Signature-256`, with `$action` in `X-UpdatePulse-Action`.
	 *
	 * @since 1.0
	 *
	 * @param string $url    The destination of the notification
	 * @param string $secret The secret used to sign the notification
	 * @param string $body   The JSON string sent in the notification
	 * @param string $action The WordPress action responsible for firing the webhook
	 * @return array|WP_Error The response of the request in case of success, a `WP_Error` otherwise
	 */
	function upserv_fire_webhook( $url, $secret, $body, $action ) {

		if (
			filter_var( $url, FILTER_VALIDATE_URL ) &&
			null !== json_decode( $body )
		) {
			$api = Webhook_API::get_instance();

			return $api->fire_webhook( $url, $secret, $body, $action );
		}

		return new WP_Error(
			__FUNCTION__,
			__( '$url must be a valid url and $body must be a JSON string.', 'updatepulse-server' )
		);
	}
}
