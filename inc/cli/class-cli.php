<?php

namespace Anyape\UpdatePulse\Server\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_CLI_Command;
use WP_CLI;
use WP_Error;
use Anyape\UpdatePulse\Server\Nonce\Nonce;

/**
 * CLI commands for UpdatePulse Server.
 *
 * @since 1.0.0
 */
class CLI extends WP_CLI_Command {

	/**
	 * Error code for when a resource is not found.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	protected const RESOURCE_NOT_FOUND = 3;
	/**
	 * Default error code for general errors.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	protected const DEFAULT_ERROR = 1;
	/**
	 * Available log methods for WP_CLI.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected const LOG_METHODS = array(
		'line',
		'log',
		'success',
		'debug',
		'warning',
		'error',
		'halt',
		'error_multi_line',
	);
	/**
	 * Available package types supported by the plugin.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected const PACKAGE_TYPES = array(
		'plugin',
		'theme',
		'generic',
	);

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Cleans up the cache folder in wp-content/uploads/updatepulse-server.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server cleanup_cache
	 *
	 * @since 1.0.0
	 */
	public function cleanup_cache() {
		$this->cleanup( 'cache' );
	}

	/**
	 * Cleans up the logs folder in wp-content/uploads/updatepulse-server.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server cleanup_logs
	 *
	 * @since 1.0.0
	 */
	public function cleanup_logs() {
		$this->cleanup( 'logs' );
	}

	/**
	 * Cleans up the tmp folder in wp-content/uploads/updatepulse-server.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server cleanup_tmp
	 *
	 * @since 1.0.0
	 */
	public function cleanup_tmp() {
		$this->cleanup( 'tmp' );
	}

	/**
	 * Cleans up the cache, logs, and tmp folders in wp-content/uploads/updatepulse-server.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server cleanup_all
	 *
	 * @since 1.0.0
	 */
	public function cleanup_all() {
		$this->cleanup( 'cache' );
		$this->cleanup( 'logs' );
		$this->cleanup( 'tmp' );
	}

	/**
	 * Checks for updates for a package.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The package slug.
	 *
	 * <type>
	 * : The package type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server check_remote_package_update my-package plugin
	 *
	 * @since 1.0.0
	 */
	public function check_remote_package_update( $args, $assoc_args ) {
		$slug = $args[0];
		$type = $args[1];

		if ( ! in_array( $type, self::PACKAGE_TYPES, true ) ) {
			$this->process_result( false, '', 'Invalid package type', self::DEFAULT_ERROR, 'error' );
		}

		$result          = upserv_check_remote_package_update( $slug, $type );
		$success_message = $result ? 'Update available' : 'No update needed';
		$error_message   = 'Unknown package slug';
		$result          = null !== $result;

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Downloads a package.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The package slug.
	 *
	 * <type>
	 * : The package type.
	 *
	 * [--vcs_url=<vcs_url>]
	 * : The URL of a VCS configured in UpdatePulse Server.
	 * If provided along with a valid branch, the package is downloaded from the VCS, and associated with that VCS.
	 *
	 * [--branch=<branch>]
	 * : The branch as provided in a VCS configured in UpdatePulse Server.
	 * If provided along with a valid VCS URL, the package is downloaded from the VCS, and associated with that VCS.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server download_remote_package my-package plugin --vcs_url='https://vcs-url.tld/identifier/' --branch='main'
	 *
	 * @since 1.0.0
	 */
	public function download_remote_package( $args, $assoc_args ) {
		$slug       = $args[0];
		$type       = $args[1];
		$result     = false;
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'vcs_url' => false,
				'branch'  => 'main',
			)
		);

		if ( ! in_array( $type, self::PACKAGE_TYPES, true ) ) {
			$this->process_result( false, '', 'Invalid package type', self::DEFAULT_ERROR, 'error' );
		}

		if ( $assoc_args['vcs_url'] ) {
			$result = upserv_download_remote_package( $slug, $type, $assoc_args['vcs_url'], $assoc_args['branch'] );
		} else {
			$result = upserv_download_remote_package( $slug, $type );
		}

		$success_message = 'Package downloaded';
		$error_message   = 'Unable to download package';

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Deletes a package.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The package slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server delete_package my-package
	 *
	 * @since 1.0.0
	 */
	public function delete_package( $args, $assoc_args ) {
		$slug            = $args[0];
		$result          = upserv_delete_package( $slug );
		$success_message = 'Package deleted';
		$error_message   = 'Unable to delete package';

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Gets package info.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The package slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server get_package_info my-package
	 *
	 * @since 1.0.0
	 */
	public function get_package_info( $args, $assoc_args ) {
		$slug          = $args[0];
		$result        = upserv_get_package_info( $slug );
		$error_message = 'Unable to get package info';

		$this->process_result( $result, $result, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Creates a nonce.
	 *
	 * ## OPTIONS
	 *
	 * [--true_nonce=<true_nonce>]
	 * : Whether to create a true nonce, or a reusable token.
	 *
	 * [--expiry_length=<expiry_length>]
	 * : The expiry length.
	 *
	 * [--data=<data>]
	 * : The data to store along the nonce, in JSON.
	 *
	 * [--return_type=<return_type>]
	 * : The return type - nonce_only or nonce_info_array.
	 *
	 * [--store=<store>]
	 * : Whether to store the nonce.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server create_nonce --true_nonce=true --expiry_length=30 --data='{}' --return_type=nonce_only --store=true
	 *
	 * @since 1.0.0
	 */
	public function create_nonce( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'true_nonce'    => true,
				'expiry_length' => Nonce::DEFAULT_EXPIRY_LENGTH,
				'data'          => array(),
				'return_type'   => Nonce::NONCE_ONLY,
				'store'         => true,
			)
		);

		if ( 'nonce_info_array' === $assoc_args['return_type'] ) {
			$assoc_args['return_type'] = Nonce::NONCE_INFO_ARRAY;
		} else {
			$assoc_args['return_type'] = Nonce::NONCE_ONLY;
		}

		if ( ! is_numeric( $assoc_args['expiry_length'] ) ) {
			$assoc_args['expiry_length'] = Nonce::DEFAULT_EXPIRY_LENGTH;
		}

		if ( ! is_bool( $assoc_args['true_nonce'] ) ) {
			$assoc_args['true_nonce'] = true;
		}

		if ( ! is_bool( $assoc_args['store'] ) ) {
			$assoc_args['store'] = true;
		}

		$assoc_args['data'] = json_decode( $assoc_args['data'], true );

		if ( ! is_array( $assoc_args['data'] ) ) {
			$assoc_args['data'] = array();
		}

		$result        = upserv_create_nonce(
			$assoc_args['true_nonce'],
			$assoc_args['expiry_length'],
			$assoc_args['data'],
			$assoc_args['return_type'],
			$assoc_args['store']
		);
		$error_message = 'Unable to create nonce';

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Build a Nonce API signature.
	 *
	 * ## OPTIONS
	 *
	 * [--api_key_id=<api_key_id>]
	 * : The ID of the API key.
	 *
	 * [--api_key=<api_key>]
	 * : The API key.
	 *
	 * [--timestamp=<timestamp>]
	 * : The timestamp.
	 *
	 * [--payload=<payload>]
	 * : The payload.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server build_nonce_api_signature --api_key_id='UPDATEPULSE_L_api_key_name' --timestamp=1704067200 --api_key=da9d20647163a1f3c04844387f91e2c3 --payload='{"key": "value"}'
	 *
	 * @since 1.0.0
	 */
	public function build_nonce_api_signature( $args, $assoc_args ) {
		$assoc_args            = wp_parse_args(
			$assoc_args,
			array(
				'timestamp' => time(),
			)
		);
		$assoc_args['payload'] = json_decode( $assoc_args['payload'], true );

		if (
			empty( $assoc_args['api_key_id'] ) ||
			empty( $assoc_args['timestamp'] ) ||
			empty( $assoc_args['api_key'] ) ||
			empty( $assoc_args['payload'] )
		) {
			$this->process_result( false, '', 'Invalid arguments', self::DEFAULT_ERROR, 'error' );
		}

		$result = upserv_build_nonce_api_signature(
			$assoc_args['api_key_id'],
			$assoc_args['api_key'],
			$assoc_args['timestamp'],
			$assoc_args['payload']
		);

		$this->process_result( $result, $result, 'Unable to create signature', self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Clears nonces.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server clear_nonces
	 *
	 * @since 1.0.0
	 */
	public function clear_nonces() {
		$result          = upserv_clear_nonces();
		$success_message = 'Expired nonce cleared';
		$error_message   = 'Unable to create nonce';

		$this->process_result( $result, $success_message, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Gets the expiry time of a nonce.
	 *
	 * ## OPTIONS
	 *
	 * <nonce>
	 * : The nonce.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server get_nonce_expiry <nonce>
	 *
	 * @since 1.0.0
	 */
	public function get_nonce_expiry( $args, $assoc_args ) {
		$nonce           = $args[0];
		$result          = upserv_get_nonce_expiry( $nonce );
		$error_message   = 'Unable to get nonce expiry';
		$success_message = $result;

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Gets data saved along with a nonce.
	 *
	 * ## OPTIONS
	 *
	 * <nonce>
	 * : The nonce.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server get_nonce_data <nonce>
	 *
	 * @since 1.0.0
	 */
	public function get_nonce_data( $args, $assoc_args ) {
		$nonce           = $args[0];
		$result          = upserv_get_nonce_data( $nonce );
		$error_message   = 'Unable to get nonce data';
		$success_message = $result;

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Deletes a nonce.
	 *
	 * ## OPTIONS
	 *
	 * <nonce>
	 * : The nonce.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server delete_nonce <nonce>
	 *
	 * @since 1.0.0
	 */
	public function delete_nonce( $args, $assoc_args ) {
		$nonce           = $args[0];
		$result          = upserv_delete_nonce( $nonce );
		$error_message   = 'Unable to delete nonce';
		$success_message = 'Nonce deleted';

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Browse licenses.
	 *
	 * ## OPTIONS
	 *
	 * <license_query>
	 * : The License Query, as JSON
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server browse_licenses <license_query>
	 *
	 * @since 1.0.0
	 */
	public function browse_licenses( $args, $assoc_args ) {
		$result          = upserv_browse_licenses( $args[0] );
		$error_message   = 'Unable to browse licenses';
		$success_message = $result;

		$this->process_result( $result, $success_message, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Read a license by ID or key.
	 *
	 * ## OPTIONS
	 *
	 * <license_key_or_id>
	 * : The license key or ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server read_license <license_key_or_id>
	 *
	 * @since 1.0.0
	 */
	public function read_license( $args, $assoc_args ) {
		$license_data = array();

		if ( is_numeric( $args[0] ) ) {
			$license_data['id'] = $args[0];
		} else {
			$license_data['license_key'] = $args[0];
		}

		$result          = upserv_read_license( $license_data );
		$error_message   = 'Unable to read license';
		$success_message = $result;

		$this->process_result( $result, $success_message, $error_message, self::RESOURCE_NOT_FOUND );
	}

	/**
	 * Add a license.
	 *
	 * ## OPTIONS
	 *
	 * <license_data>
	 * : The license data, as JSON - see `$params` for the License API action `add` in the License API documentation for details.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server add_license <license_data>
	 *
	 * @since 1.0.0
	 */
	public function add_license( $args, $assoc_args ) {
		$payload       = json_decode( $args[0], true );
		$result        = upserv_add_license( $payload );
		$error_message = 'Unable to add the license';

		if ( ! is_object( $result ) ) {
			$result = new WP_Error( self::DEFAULT_ERROR, print_r( $result, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Edit a license.
	 *
	 * ## OPTIONS
	 *
	 * <license_data>
	 * : The license data, as JSON - see `$params` for the License API action `edit` in the License API documentation for details.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server edit_license <license_data>
	 *
	 * @since 1.0.0
	 */
	public function edit_license( $args, $assoc_args ) {
		$payload       = json_decode( $args[0], true );
		$result        = upserv_edit_license( $payload );
		$error_message = 'Unable to edit the license';

		if ( ! is_object( $result ) ) {
			$result = new WP_Error( self::DEFAULT_ERROR, print_r( $result, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Delete a license.
	 *
	 * ## OPTIONS
	 *
	 * <license_key_or_id>
	 * : The license key or ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server delete_license <license_key_or_id>
	 *
	 * @since 1.0.0
	 */
	public function delete_license( $args, $assoc_args ) {
		$license_data = array();

		if ( is_numeric( $args[0] ) ) {
			$license_data['id'] = $args[0];
		} else {
			$license_data['license_key'] = $args[0];
		}

		$result        = upserv_delete_license( $license_data );
		$error_message = 'Unable to delete the license';

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Check a license.
	 *
	 * ## OPTIONS
	 *
	 * <license_key_or_id>
	 * : The license key or ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server check_license <license_key_or_id>
	 *
	 * @since 1.0.0
	 */
	public function check_license( $args, $assoc_args ) {
		$license_data = array();

		if ( is_numeric( $args[0] ) ) {
			$license_data['id'] = $args[0];
		} else {
			$license_data['license_key'] = $args[0];
		}

		$result        = upserv_check_license( $license_data );
		$error_message = 'Unable to check the license';

		if ( ! is_object( $result ) ) {
			$result = new WP_Error( self::DEFAULT_ERROR, print_r( $result, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Activate a license for a domain.
	 *
	 * ## OPTIONS
	 *
	 * <license_key>
	 * : The license key.
	 *
	 * <package-slug>
	 * : The package slug.
	 *
	 * <domain>
	 * : The domain.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server activate_license <license_key> <package-slug> <domain>
	 *
	 * @since 1.0.0
	 */
	public function activate_license( $args, $assoc_args ) {
		$license_data  = array(
			'license_key'     => $args[0],
			'package_slug'    => $args[1],
			'allowed_domains' => $args[2],
		);
		$result        = upserv_activate_license( $license_data );
		$error_message = 'Unable to activate the license';

		if ( ! is_object( $result ) ) {
			$result = new WP_Error( self::DEFAULT_ERROR, print_r( $result, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/**
	 * Deactivate a license for a domain.
	 *
	 * ## OPTIONS
	 *
	 * <license_key>
	 * : The license key.
	 *
	 * <package-slug>
	 * : The package slug.
	 *
	 * <domain>
	 * : The domain.
	 *
	 * ## EXAMPLES
	 *
	 *     wp updatepulse-server deactivate_license <license_key> <package-slug> <domain>
	 *
	 * @since 1.0.0
	 */
	public function deactivate_license( $args, $assoc_args ) {
		$license_data  = array(
			'license_key'     => $args[0],
			'package_slug'    => $args[1],
			'allowed_domains' => $args[2],
		);
		$result        = upserv_deactivate_license( $license_data );
		$error_message = 'Unable to deactivate the license';

		if ( ! is_object( $result ) ) {
			$result = new WP_Error( self::DEFAULT_ERROR, print_r( $result, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		$this->process_result( $result, $result, $error_message, self::DEFAULT_ERROR, 'error' );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Cleans up a specific folder in the plugin directory.
	 *
	 * @param string $method The folder to clean up ('cache', 'logs', or 'tmp').
	 * @return void
	 * @since 1.0.0
	 */
	protected function cleanup( $method ) {
		$method          = 'upserv_force_cleanup_' . $method;
		$result          = $method();
		$success_message = 'OK';
		$error_message   = 'Cleanup failed';

		$this->process_result( $result, $success_message, $error_message );
	}

	/**
	 * Processes the result of a command and outputs a message based on success or failure.
	 *
	 * @param mixed  $result          The result to evaluate.
	 * @param mixed  $success_message Message to display on success.
	 * @param string $error_message   Message to display on error.
	 * @param int    $error_code      Error code to return on failure. Default: 1.
	 * @param string $error_level     WP_CLI error level to use. Default: 'warning'.
	 * @return void
	 * @since 1.0.0
	 */
	protected function process_result( $result, $success_message, $error_message, $error_code = 1, $error_level = 'warning' ) {

		if ( $result instanceof WP_Error ) {
			$this->output(
				array(
					'level'  => 'error',
					'output' => $result->get_error_message(),
				)
			);
		} else {

			if ( empty( $success_message ) ) {
				$success_message = $result;
			}

			if ( empty( $error_message ) ) {
				$error_message = 'Undefined error';
			}

			$message = $result ? $success_message : $error_message;
			$level   = $result ? 'success' : $error_level;

			$this->output(
				array(
					'level'  => $level,
					'output' => $message,
				)
			);

			if ( 'warning' === $level ) {
				$this->output(
					array(
						'level'  => 'halt',
						'output' => $error_code,
					)
				);
			}
		}
	}

	/**
	 * Outputs a message to the CLI.
	 *
	 * Handles both string and array message formats. If an array is provided,
	 * it should contain 'level' and 'output' keys. The level determines which
	 * WP_CLI output method to use.
	 *
	 * @param string|array $message The message to output.
	 * @return void
	 * @since 1.0.0
	 */
	protected function output( $message ) {

		if ( is_string( $message ) ) {
			WP_CLI::log( $message );
		} elseif ( is_array( $message ) ) {

			if (
				! isset( $message['level'] ) ||
				! in_array( $message['level'], self::LOG_METHODS, true )
			) {
				$message['level'] = 'log';
			}

			if (
				'halt' === $message['level'] &&
				(
					! isset( $message['output'] ) ||
					! is_int( $message['output'] )
				)
			) {
				$message['output'] = 255;
			} elseif ( ! isset( $message['output'] ) ) {
				$message['output'] = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			if (
				'error_multi_line' === $message['level'] &&
				! is_array( $message['output'] )
			) {
				$message['level'] = 'log';
			}

			if (
				'error_multi_line' !== $message['level'] &&
				! is_string( $message['output'] ) &&
				! is_int( $message['output'] )
			) {
				$message['output'] = print_r( $message['output'], true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			WP_CLI::{$message['level']}( $message['output'] );
		} else {
			WP_CLI::log( print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}
}
