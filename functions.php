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
	 * @param string $type    The VCS type ('github', 'gitlab', 'bitbucket', 'gitea', 'forgejo', 'gitee')
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
			case 'forgejo':
				return 'view' === $context ? __( 'Forgejo', 'updatepulse-server' ) : 'Forgejo';
			case 'gitee':
				return 'view' === $context ? __( 'Gitee', 'updatepulse-server' ) : 'Gitee';
			default:
				return 'view' === $context ? __( 'Undefined', 'updatepulse-server' ) : null;
		}
	}
}

if ( ! function_exists( 'upserv_get_brand_icon' ) ) {
	/**
	 * Get an SVG icon for a supported brand.
	 *
	 * The icon paths are stored in one allowlisted registry and use the current
	 * text color so that the same markup works in every interface state.
	 *
	 * @since 1.0.13
	 *
	 * @param string $brand Brand identifier.
	 * @return string SVG markup, or an empty string for an unsupported brand.
	 */
	function upserv_get_brand_icon( $brand ) {
		static $icons = array(
			'bitbucket'          => array(
				'path' => 'M22.2 32c-2.1 0-4.2.4-6.1 1.1s-3.7 1.9-5.2 3.4-2.7 3.2-3.5 5.1a16 16 0 0 0-1.1 8.9l67.8 412.2c.8 5.1 3.4 9.7 7.3 13s8.8 5.2 14 5.2h325.7c3.8.1 7.5-1.3 10.5-3.7s4.9-5.9 5.5-9.7L505 50.7c.7-4.2-.3-8.4-2.8-11.9a15.8 15.8 0 0 0-13.2-6.6zm285.9 297.8h-104l-28.1-147h157.3z',
			),
			'gitea'              => array(
				'path'      => 'M4.2 4.6q-.36-.01-.83.1A4 4 0 0 0 1.3 5.71C-.4 7.25.03 9.69.1 10.05c.06.45.26 1.69 1.2 2.77 1.76 2.14 5.52 2.1 5.52 2.1s.46 1.1 1.17 2.11c.95 1.26 1.94 2.25 2.89 2.37h7.21s.46 0 1.08-.4c.54-.32 1.01-.9 1.01-.9s.5-.52 1.18-1.72q.31-.56.54-1.07S24 10.84 24 6.49c-.04-1.32-.37-1.55-.44-1.63-.16-.16-.37-.15-.37-.15s-4.47.25-6.8.3l-1.5.03V9.5l-.64-.3V5.04c-1.1.02-3.4-.08-3.4-.08s-5.4-.27-6-.33q-.28-.01-.64-.03m.36 1.84h.11s.27 2.26.6 3.6c.28 1.1.95 2.96.95 2.96s-1-.12-1.64-.35c-1-.32-1.41-.71-1.41-.71s-.73-.51-1.1-1.52c-.63-1.69-.05-2.72-.05-2.72s.32-.86 1.47-1.14c.4-.11.86-.12 1.07-.12m8.33 2.55c.26 0 .51.13.51.13l.87.42-.53 1.07a.7.7 0 0 0-.61.36.7.7 0 0 0 .07.76l-.94 1.92a.7.7 0 0 0-.66.53.7.7 0 0 0 .35.76.7.7 0 0 0 .86-.2.7.7 0 0 0-.07-.89l.92-1.87a1 1 0 0 0 .24-.02 1 1 0 0 0 .27-.14 9 9 0 0 1 1.02.51 1 1 0 0 1 .28.29c.07.2-.07.57-.07.57-.09.29-.7 1.55-.7 1.55a.7.7 0 0 0-.68.47.68.68 0 1 0 1.16-.25l.21-.43c.2-.4.52-1.16.52-1.16.03-.07.21-.4.1-.81-.1-.44-.48-.64-.48-.64-.47-.3-1.12-.58-1.12-.58s0-.16-.04-.27a1 1 0 0 0-.15-.24l.52-1.06 2.89 1.4s.48.21.58.62c.07.28-.02.53-.07.65-.24.59-2.1 4.32-2.1 4.32s-.23.55-.74.59a1 1 0 0 1-.4-.05l-.2-.08-4.31-2.1s-.42-.22-.49-.6c-.08-.3.1-.68.1-.68l2.08-4.28s.18-.37.46-.5A1 1 0 0 1 12.9 9',
				'transform' => 'scale(21.3333333333)',
			),
			'forgejo'            => array(
				'path'      => 'M16.78 0a2.9 2.9 0 1 1-2.53 4.32H12.9a4.27 4.27 0 0 0-4.26 4.2v2.11a7 7 0 0 1 4.14-1.42h1.46a2.9 2.9 0 1 1 0 2.85H12.9a4.27 4.27 0 0 0-4.26 4.2v2.31a2.9 2.9 0 1 1-2.85 0V8.6a7.1 7.1 0 0 1 7-7.11h1.45A2.9 2.9 0 0 1 16.78 0M7.22 19.9a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4m9.56-10.46a1.2 1.2 0 1 0 0 2.39 1.2 1.2 0 0 0 0-2.39m0-7.73a1.2 1.2 0 1 0 0 2.38 1.2 1.2 0 0 0 0-2.38',
				'transform' => 'scale(21.3333333333)',
			),
			'gitee'              => array(
				'path'      => 'M12 24a12 12 0 1 1 0-24 12 12 0 1 1 0 24m6.08-18.67h-8.3a4.44 4.44 0 0 0-4.45 4.45v8.3c0 .32.27.59.6.59h8.74a4 4 0 0 0 4-4v-3.41a.6.6 0 0 0-.6-.6h-6.81a.6.6 0 0 0-.6.6v1.48c0 .31.25.57.55.6h4.2c.3 0 .57.23.59.54v.34c0 .98-.8 1.77-1.78 1.77H8.6A.6.6 0 0 1 8 15.4V9.77c0-.95.76-1.74 1.7-1.77h8.38a.6.6 0 0 0 .59-.6V5.93c0-.32-.26-.59-.59-.59z',
				'transform' => 'scale(21.3333333333)',
			),
			'github'             => array(
				'path' => 'M216.5 362.5c-66-8-112.5-55.5-112.5-117 0-25 9-52 24-70-6.5-16.5-5.5-51.5 2-66 20-2.5 47 8 63 22.5 19-6 39-9 63.5-9s44.5 3 62.5 8.5c15.5-14 43-24.5 63-22 7 13.5 8 48.5 1.5 65.5 16 19 24.5 44.5 24.5 70.5 0 61.5-46.5 108-113.5 116.5 17 11 28.5 35 28.5 62.5v52c0 15 12.5 23.5 27.5 17.5C441 459.5 512 369 512 257 512 115.5 397 0 255.5 0S0 115.5 0 257c0 111 70.5 203 165.5 237.5 13.5 5 26.5-4 26.5-17.5v-40c-7 3-16 5-24 5-33 0-52.5-18-66.5-51.5-5.5-13.5-11.5-21.5-23-23-6-.5-8-3-8-6 0-6 10-10.5 20-10.5 14.5 0 27 9 40 27.5 10 14.5 20.5 21 33 21s20.5-4.5 32-16c8.5-8.5 15-16 21-21',
			),
			'gitlab'             => array(
				'path' => 'm504 204.6-.7-1.8L433.6 21c-1.4-3.6-3.9-6.6-7.2-8.6-2.4-1.6-5.1-2.5-8-2.8a18.44 18.44 0 0 0-19.6 13.3l-47 144H161.3l-47.1-144c-.8-2.8-2.2-5.3-4.1-7.4-2-2.1-4.4-3.7-7.1-4.8-2.6-1-5.5-1.4-8.4-1.1s-5.6 1.2-8 2.8c-3.2 2-5.8 5.1-7.2 8.6L9.8 202.8l-.8 1.8c-10 26.2-11.3 55-3.5 82 7.7 26.9 24 50.7 46.4 67.6l.3.2.6.4 106 79.5c38.5 29.1 66.7 50.3 84.6 63.9 3.7 1.9 8.3 4.3 13 4.3s9.3-2.4 13-4.3c17.9-13.5 46.1-34.9 84.6-63.9l106.7-79.9.3-.3c22.4-16.9 38.7-40.6 45.6-67.5 8.6-27 7.4-55.8-2.6-82',
			),
			'gitlab-self-hosted' => array(
				'path'      => 'M0 96v320c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64C28.7 32 0 60.7 0 96m337.5 12.5 44.6 116.4.4 1.2c5.6 16.8 7.2 35.2 2.3 52.5-5 17.2-15.4 32.4-29.8 43.3l-.2.1-68.4 51.2-54.1 40.9c-.5.2-1.1.5-1.7.8-2 1-4.4 2-6.7 2-3 0-6.8-1.8-8.3-2.8l-54.2-40.9-67.9-50.9-.4-.3-.2-.1c-14.3-10.8-24.8-26-29.7-43.3s-4.2-35.7 2.2-52.5l.5-1.2 44.7-116.4c.9-2.3 2.5-4.3 4.5-5.6 1.6-1 3.4-1.6 5.2-1.8 1.3-.7 2.1-.4 3.4.1.6.2 1.2.5 2 .7 1 .4 1.6.9 2.4 1.5.6.4 1.2 1 2.1 1.5 1.2 1.4 2.2 3 2.7 4.8l29.2 92.2H285l30.2-92.2a11.73 11.73 0 0 1 7.1-7.9c1.7-.6 3.6-.9 5.4-.7s3.6.8 5.2 1.8c2 1.3 3.7 3.3 4.6 5.6',
				'transform' => 'translate(32 0)',
			),
		);

		if ( ! isset( $icons[ $brand ] ) ) {
			return '';
		}

		$icon = $icons[ $brand ];
		$path = '<path d="' . esc_attr( $icon['path'] ) . '"';

		if ( isset( $icon['transform'] ) ) {
			$path .= ' transform="' . esc_attr( $icon['transform'] ) . '"';
		}

		$path .= '></path>';

		return sprintf(
			'<svg class="upserv-brand-icon upserv-brand-icon-%1$s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true" focusable="false">%2$s</svg>',
			esc_attr( $brand ),
			$path
		);
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
	 * Merges the provided values into the stored options array.
	 *
	 * @since 1.0
	 *
	 * @param array $options The new options to save
	 * @return bool Whether the stored option value changed.
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
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
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
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
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
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
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
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
	 */
	function upserv_is_doing_package_api_request() {
		return Package_API::is_doing_api_request();
	}
}

if ( ! function_exists( 'upserv_is_doing_api_request' ) ) {
	/**
	 * Determine whether the current request matches any recognized plugin API endpoint.
	 *
	 * @since 1.0
	 *
	 * @return bool Whether the current request matches a recognized plugin API endpoint.
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
	/**
	 * Get the root directory used for plugin data.
	 *
	 * @since 1.0
	 *
	 * @return string The plugin data directory path.
	 */
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
	 * @param bool   $json_encode  Whether to return a JSON string instead of a PHP associative array.
	 * @return array|string The package metadata.
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
	 * @param array|null $metadata The metadata to set, or an empty value to delete it.
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
	 * @param string|false $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
	 * @param string $branch  The branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`
	 * @return bool|WP_Error Whether the package was downloaded, or an error for invalid VCS information.
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
	 * @param string|false $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
	 * @param string $branch  The branch as provided in a VCS configured in UpdatePulse Server; default to `'main'`
	 * @return bool|WP_Error Whether the package was downloaded, or an error for invalid VCS information.
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
	 * @param string|false $vcs_url The URL of a VCS configured in UpdatePulse Server; default to `false`
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
	 * @param bool   $json_encode  Whether to return a JSON string (default) instead of a PHP associative array.
	 * @return array|string The package information.
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
	 * @param bool   $json_encode Whether to return a JSON string (default) instead of a PHP associative array.
	 * @return array|string The batch information; each entry is formatted like in `upserv_get_package_info()`.
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
	 * Stream a package from the file system and optionally terminate the request.
	 *
	 * @since 1.0
	 *
	 * @param string $package_slug  The slug of the package
	 * @param string|null $package_path The package path, or null to locate it from the slug.
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
	 * @param array $private_auth_key The private authentication key data.
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
	 * @param int    $expiry_length The number of seconds after which the nonce expires; default `Nonce::DEFAULT_EXPIRY_LENGTH` - 30 seconds
	 * @param array  $data          Custom data to save along with the nonce; set an element with key `permanent` to a truthy value to create a nonce that never expires; default `array()`
	 * @param int    $return_type   Whether to return the nonce, or an array of information; default `Nonce::NONCE_ONLY`; other accepted value is `Nonce::NONCE_INFO_ARRAY`
	 * @param bool   $store         Whether to store the nonce, or let a third party mechanism take care of it; default `true`
	 * @return bool|string|array `false` on failure, the token for `Nonce::NONCE_ONLY`, or nonce information for `Nonce::NONCE_INFO_ARRAY`.
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
	 * @return int The expiry timestamp, or zero when the nonce is missing or permanent.
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
	 * @return mixed The data stored with the nonce, or an empty array when unavailable.
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
	 * Build credentials and a signature for the UpdatePulse Server Nonce API.
	 *
	 * @since 1.0
	 *
	 * @param string $api_key_id The ID of the private API key.
	 * @param string $api_key    The private API key, which is not included in the result.
	 * @param int    $timestamp  The timestamp included in the credentials and signature.
	 * @param array  $payload    The payload used to request a reusable token or true nonce.
	 * @return array An array with `credentials` and `signature` keys.
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
