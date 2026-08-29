<?php

namespace Anyape\UpdatePulse\Server\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;
use Anyape\UpdatePulse\Server\Nonce\Nonce;
use Anyape\UpdatePulse\Server\Manager\Zip_Package_Manager;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Server\Update\Cache;
use Anyape\UpdatePulse\Server\Server\Update\Package;
use Anyape\UpdatePulse\Server\Server\Update\Invalid_Package_Exception;
use Anyape\Utils\Utils;

/**
 * Package API class
 *
 * @since 1.0.0
 */
class Package_API {

	/**
	 * Is doing API request
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	protected static $doing_api_request = null;
	/**
	 * Instance
	 *
	 * @var Package_API|null
	 * @since 1.0.0
	 */
	protected static $instance;
	/**
	 * Config
	 *
	 * @var array|null
	 * @since 1.0.0
	 */
	protected static $config;

	/**
	 * HTTP response code
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	protected $http_response_code = 200;
	/**
	 * API key ID
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $api_key_id;
	/**
	 * API access
	 *
	 * @var array|null
	 * @since 1.0.0
	 */
	protected $api_access;
	/**
	 * Server-validated claims to add to the current Nonce API payload.
	 *
	 * @var array
	 * @since 1.1.0
	 */
	protected $validated_nonce_api_claims = array();

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'add_endpoints' ), 10, 0 );
			add_action( 'parse_request', array( $this, 'parse_request' ), -99, 0 );
			add_action( 'upserv_saved_remote_package_to_local', array( $this, 'upserv_saved_remote_package_to_local' ), 10, 3 );
			add_action( 'upserv_pre_delete_package', array( $this, 'upserv_pre_delete_package' ), 0, 2 );
			add_action( 'upserv_did_delete_package', array( $this, 'upserv_did_delete_package' ), 20, 3 );
			add_action( 'upserv_did_download_package', array( $this, 'upserv_did_download_package' ), 20, 1 );

			add_filter( 'query_vars', array( $this, 'query_vars' ), -99, 1 );
			add_filter( 'upserv_api_package_actions', array( $this, 'upserv_api_package_actions' ), 0, 1 );
			add_filter( 'upserv_api_webhook_events', array( $this, 'upserv_api_webhook_events' ), 10, 1 );
			add_filter( 'upserv_nonce_api_payload_validation', array( $this, 'upserv_nonce_api_payload_validation' ), 10, 3 );
			add_filter( 'upserv_nonce_api_payload', array( $this, 'upserv_nonce_api_payload' ), 0, 1 );
			add_filter( 'upserv_package_info_include', array( $this, 'upserv_package_info_include' ), 10, 2 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// API action --------------------------------------------------

	/**
	 * Browse packages
	 *
	 * Get information about multiple packages.
	 *
	 * @param string|array $query The search query or parameters.
	 * @return object Response with package information.
	 * @since 1.0.0
	 */
	public function browse( $query ) {
		$result          = false;
		$query           = empty( $query ) || ! is_string( $query ) ? array() : json_decode( wp_unslash( $query ), true );
		$query['search'] = isset( $query['search'] ) ? trim( esc_html( $query['search'] ) ) : false;
		$result          = upserv_get_batch_package_info( $query['search'], false );
		$result['count'] = is_array( $result ) ? count( $result ) : 0;
		/**
		 * Filter the result of the `browse` operation of the Package API.
		 *
		 * @param array $result The result of the `browse` operation
		 * @param array $query The query - see browse()
		 * @return array The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_package_browse', $result, $query );

		/**
		 * Fired after the `browse` Package API action.
		 *
		 * @param array $result the result of the action
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_browse_package', $result );

		if ( empty( $result ) ) {
			$result = array( 'count' => 0 );
		}

		if ( isset( $result['count'] ) && 0 === $result['count'] ) {
			$this->http_response_code = 404;
			$result                   = (object) array(
				'code'    => 'no_packages_found',
				'message' => __( 'No packages found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Read package information
	 *
	 * Get information about a single package.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return object Response with package information.
	 * @since 1.0.0
	 */
	public function read( $package_id, $type ) {
		$result = upserv_get_package_info( $package_id, false );

		if (
			! is_array( $result ) ||
			! isset( $result['type'] ) ||
			$type !== $result['type']
		) {
			$result = false;
		} else {
			unset( $result['file_path'] );
		}

		/**
		 * Filter the result of the `read` operation of the Package API.
		 *
		 * @param array $result The result of the `read` operation
		 * @param string $package_id The slug of the read package
		 * @param string $type The type of the read package
		 * @return array The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_package_read', $result, $package_id, $type );

		/**
		 * Fired after the `read` Package API action.
		 *
		 * @param array $result the result of the action
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_read_package', $result );

		if ( ! $result ) {
			$this->http_response_code = 404;
			$result                   = (object) array(
				'code'    => 'package_not_found',
				'message' => __( 'Package not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Edit a package
	 *
	 * If a package exists, update it by uploading a valid package file, or by downloading it if using a VCS.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return object Response with package information or error.
	 * @since 1.0.0
	 */
	public function edit( $package_id, $type ) {
		$result = false;
		$config = self::get_config();
		$exists = upserv_get_package_info( $package_id, false );

		if ( ! empty( $exists ) ) {
			$file = $this->get_file();

			if ( $file ) {
				$result = $this->process_file( $file, $package_id, $type );
			} elseif ( $config['use_vcs'] ) {
				$result = $this->download_file( $package_id, $type );
			}

			$result = $result && ! is_wp_error( $result ) ? upserv_get_package_info( $package_id, false ) : $result;
		}

		/**
		 * Filter the result of the `edit` operation of the Package API.
		 *
		 * @param array $result The result of the `edit` operation
		 * @param string $package_id The slug of the edited package
		 * @param string $type The type of the edited package
		 * @return array The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_package_edit', $result, $package_id, $type );

		if ( empty( $exists ) ) {
			$this->http_response_code = 404;
			$result                   = (object) array(
				'code'    => 'package_not_found',
				'message' => __( 'Package not found.', 'updatepulse-server' ),
			);
		} elseif ( is_wp_error( $result ) ) {
			$this->http_response_code = 400;
			$result                   = (object) array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
		} elseif ( ! $result ) {
			$this->http_response_code = 400;
			$result                   = (object) array(
				'code'    => 'invalid_parameters',
				'message' => __( 'Package could not be edited - invalid parameters.', 'updatepulse-server' ),
			);
		} else {
			/**
			 * Fired after the `edit` Package API action.
			 *
			 * @param array $result the result of the action
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_edit_package', $result );
		}

		return (object) $result;
	}

	/**
	 * Add a package
	 *
	 * If a package does not exist, upload it by providing a valid package file, or download it if using a VCS.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return object Response with package information or error.
	 * @since 1.0.0
	 */
	public function add( $package_id, $type ) {
		$result = false;
		$config = self::get_config();
		$exists = upserv_get_package_info( $package_id, false );

		if ( empty( $exists ) ) {
			$file = $this->get_file();

			if ( $file ) {
				$result = $this->process_file( $file, $package_id, $type );
			} elseif ( $config['use_vcs'] ) {
				$result = $this->download_file( $package_id, $type );
			}

			$result = $result && ! is_wp_error( $result ) ? upserv_get_package_info( $package_id, false ) : $result;
		}

		/**
		 * Filter the result of the `add` operation of the Package API.
		 *
		 * @param array $result The result of the `add` operation
		 * @param string $package_id The slug of the added package
		 * @param string $type The type of the added package
		 * @return array The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_package_add', $result, $package_id, $type );

		if ( ! empty( $exists ) ) {
			$this->http_response_code = 409;
			$result                   = (object) array(
				'code'    => 'package_exists',
				'message' => __( 'Package already exists.', 'updatepulse-server' ),
			);
		} elseif ( is_wp_error( $result ) ) {
			$this->http_response_code = 400;
			$result                   = (object) array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
		} elseif ( ! $result ) {
			$this->http_response_code = 400;
			$result                   = (object) array(
				'code'    => 'invalid_parameters',
				'message' => __( 'Package could not be added - invalid parameters.', 'updatepulse-server' ),
			);
		} else {
			/**
			 * Fired after the `add` Package API action.
			 *
			 * @param array $result the result of the action
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_add_package', $result );
		}

		return (object) $result;
	}

	/**
	 * Delete a package
	 *
	 * Remove a package from the system.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return object Response with deletion status or error.
	 * @since 1.0.0
	 */
	public function delete( $package_id, $type ) {
		/**
		 * Fired before the `delete` Package API action.
		 *
		 * @param string $package_slug the slug of the package to be deleted
		 * @param string $type the type of the package to be deleted
		 * @since 1.0.0
		 */
		do_action( 'upserv_pre_delete_package', $package_id, $type );

		$result = upserv_delete_package( $package_id );
		/**
		 * Filter the result of the `delete` operation of the Package API.
		 *
		 * @param bool $result The result of the `delete` operation
		 * @param string $package_id The slug of the deleted package
		 * @param string $type The type of the deleted package
		 * @return bool The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_package_delete', $result, $package_id, $type );

		if ( $result ) {
			/**
			 * Fired after the `delete` Package API action.
			 *
			 * @param bool $result the result of the `delete` operation
			 * @param string $package_slug the slug of the deleted package
			 * @param string $type the type of the deleted package
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_delete_package', $result, $package_id, $type );
		} else {
			$this->http_response_code = 404;
			$result                   = (object) array(
				'code'    => 'package_not_found',
				'message' => __( 'Package not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Download a package
	 *
	 * Initiate download of a package file.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return array Error information if package not found.
	 * @since 1.0.0
	 */
	public function download( $package_id, $type ) {
		$path = upserv_get_local_package_path( $package_id );

		if ( ! $path ) {

			if ( ! $this->add( $package_id, $type ) ) {
				return array(
					'code'    => 'package_not_found',
					'message' => __( 'Package not found.', 'updatepulse-server' ),
				);
			}
		}

		upserv_download_local_package( $package_id, $path, false );
		/**
		 * Fired after the `download` Package API action.
		 *
		 * @param string $package_slug the slug of the downloaded package
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_download_package', $package_id );

		exit;
	}

	/**
	 * Generate signed URL for package download
	 *
	 * Create a secure URL for downloading packages.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return object Response with signed URL information.
	 * @since 1.0.0
	 */
	public function signed_url( $package_id, $type ) {
		$package_info = upserv_get_package_info( $package_id, false );

		if (
			! $this->is_valid_package_target( $type, $package_id ) ||
			! is_array( $package_info ) ||
			! isset( $package_info['type'] ) ||
			$type !== $package_info['type']
		) {
			$this->http_response_code = 404;

			return (object) array(
				'code'    => 'package_not_found',
				'message' => __( 'Package not found.', 'updatepulse-server' ),
			);
		}

		/**
		 * Filter the token used to sign the URL.
		 *
		 * @param mixed $token The token used to sign the URL
		 * @param string $package_id The slug of the package for which the URL needs to be signed
		 * @param string $type The type of the package for which the URL needs to be signed
		 * @return mixed The filtered token
		 * @since 1.0.0
		*/
		$token = apply_filters( 'upserv_package_signed_url_token', false, $package_id, $type );

		if ( ! $token ) {
			$token = upserv_create_nonce(
				false,
				HOUR_IN_SECONDS,
				array(
					'package_download' => array(
						'action'     => 'download',
						'type'       => $type,
						'package_id' => $package_id,
						'issued_by'  => 'package_api',
					),
				),
			);
		}

		/**
		 * Filter the result of the `signed_url` operation of the Package API.
		 *
		 * @param array $result The result of the `signed_url` operation
		 * @param string $package_id The slug of the package for which the URL was signed
		 * @param string $type The type of the package for which the URL was signed
		 * @return array The filtered result
		 * @since 1.0.0
		 */
		$result = apply_filters(
			'upserv_package_signed_url',
			array(
				'url'    => add_query_arg(
					array(
						'token'  => $token,
						'action' => 'download',
					),
					home_url( 'updatepulse-server-package-api/' . $type . '/' . $package_id . '/' )
				),
				'token'  => $token,
				'expiry' => upserv_get_nonce_expiry( $token ),
			),
			$package_id,
			$type
		);

		if ( $result ) {
			/**
			 * Fired after the `signed_url` Package API action.
			 *
			 * @param array $result the result of the action
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_signed_url_package', $result );
		} else {
			$this->http_response_code = 404;
			$result                   = (object) array(
				'code'    => 'package_not_found',
				'message' => __( 'Package not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	// WordPress hooks ---------------------------------------------

	/**
	 * Add API endpoints
	 *
	 * Register the rewrite rules for the Package API endpoints.
	 *
	 * @since 1.0.0
	 */
	public function add_endpoints() {
		add_rewrite_rule(
			'^updatepulse-server-package-api/(plugin|theme|generic)/(.+)/*?$',
			'index.php?type=$matches[1]&package_id=$matches[2]&$matches[3]&__upserv_package_api=1&',
			'top'
		);
		add_rewrite_rule(
			'^updatepulse-server-package-api/*?$',
			'index.php?$matches[1]&__upserv_package_api=1&',
			'top'
		);
	}

	/**
	 * Parse API requests
	 *
	 * Handle incoming API requests to the Package API endpoints.
	 *
	 * @since 1.0.0
	 */
	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__upserv_package_api'] ) ) {
			$this->handle_api_request();

			exit;
		}
	}

	/**
	 * Register query variables
	 *
	 * Add custom query variables used by the Package API.
	 *
	 * @param array $query_vars Existing query variables.
	 * @return array Modified query variables.
	 * @since 1.0.0
	 */
	public function query_vars( $query_vars ) {
		$query_vars = array_merge(
			$query_vars,
			array(
				'__upserv_package_api',
				'action',
				'api',
				'api_token',
				'api_credentials',
				'package_id',
				'type',
				'browse_query',
			)
		);

		return $query_vars;
	}

	/**
	 * Handle package saved to local event
	 *
	 * Actions to perform when a remote package has been saved locally.
	 *
	 * @param bool   $local_ready Whether the local package is ready.
	 * @param string $package_type The type of the package.
	 * @param string $package_slug The slug of the package.
	 * @since 1.0.0
	 */
	public function upserv_saved_remote_package_to_local( $local_ready, $package_type, $package_slug ) {

		if ( ! $local_ready ) {
			return;
		}

		upserv_whitelist_package( $package_slug );

		$payload = array(
			'event'       => 'package_updated',
			// translators: %1$s is the package type, %2$s is the pakage slug
			'description' => sprintf( esc_html__( 'The package of type `%1$s` and slug `%2$s` has been updated on UpdatePulse Server', 'updatepulse-server' ), $package_type, $package_slug ),
			'content'     => upserv_get_package_info( $package_slug, false ),
		);

		upserv_schedule_webhook( $payload, 'package' );
	}

	/**
	 * Handle pre-delete package event
	 *
	 * Actions to perform before a package is deleted.
	 *
	 * @param string $package_slug The slug of the package.
	 * @param string $package_type The type of the package.
	 * @since 1.0.0
	 */
	public function upserv_pre_delete_package( $package_slug, $package_type ) {
		wp_cache_set(
			'upserv_package_deleted_info' . $package_slug . '_' . $package_type,
			upserv_get_package_info( $package_slug, false ),
			'updatepulse-server'
		);
	}

	/**
	 * Handle post-delete package event
	 *
	 * Actions to perform after a package is deleted.
	 *
	 * @param bool   $result The result of the deletion.
	 * @param string $package_slug The slug of the package.
	 * @param string $package_type The type of the package.
	 * @since 1.0.0
	 */
	public function upserv_did_delete_package( $result, $package_slug, $package_type ) {
		$package_info = wp_cache_get(
			'upserv_package_deleted_info' . $package_slug . '_' . $package_type,
			'updatepulse-server'
		);

		if ( $package_info ) {
			$payload = array(
				'event'       => 'package_deleted',
				// translators: %1$s is the package type, %2$s is the package slug
				'description' => sprintf( esc_html__( 'The package of type `%1$s` and slug `%2$s` has been deleted on UpdatePulse Server', 'updatepulse-server' ), $package_type, $package_slug ),
				'content'     => $package_info,
			);

			upserv_schedule_webhook( $payload, 'package' );
		}
	}

	/**
	 * Handle package downloaded event
	 *
	 * Actions to perform after a package is downloaded.
	 *
	 * @param string $package_slug The slug of the downloaded package.
	 * @since 1.0.0
	 */
	public function upserv_did_download_package( $package_slug ) {
		$payload = array(
			'event'       => 'package_downloaded',
			// translators: %s is the package slug
			'description' => sprintf( esc_html__( 'The package of `%s` has been securely downloaded from UpdatePulse Server', 'updatepulse-server' ), $package_slug ),
			'content'     => upserv_get_package_info( $package_slug, false ),
		);

		upserv_schedule_webhook( $payload, 'package' );
	}

	/**
	 * Register package API actions
	 *
	 * Add descriptions for all available Package API actions.
	 *
	 * @param array $actions Existing API actions.
	 * @return array Modified API actions with descriptions.
	 * @since 1.0.0
	 */
	public function upserv_api_package_actions( $actions ) {
		$actions['browse']     = __( 'Get information about multiple packages', 'updatepulse-server' );
		$actions['read']       = __( 'Get information about a single package', 'updatepulse-server' );
		$actions['edit']       = __( 'If a package does exist, update it by uploading a valid package file, or by downloading it if using a VCS', 'updatepulse-server' );
		$actions['add']        = __( 'If a package does not exist, upload it by providing a valid package file, or download it if using a VCS', 'updatepulse-server' );
		$actions['delete']     = __( 'Delete a package', 'updatepulse-server' );
		$actions['signed_url'] = __( 'Retrieve secure URLs for downloading packages', 'updatepulse-server' );

		return $actions;
	}

	/**
	 * Register webhook events
	 *
	 * Add supported webhook events for the Package API.
	 *
	 * @param array $webhook_events Existing webhook events.
	 * @return array Modified webhook events.
	 * @since 1.0.0
	 */
	public function upserv_api_webhook_events( $webhook_events ) {

		if ( isset( $webhook_events['package'], $webhook_events['package']['events'] ) ) {
			$webhook_events['package']['events']['package_updated']    = __( 'Package added or updated', 'updatepulse-server' );
			$webhook_events['package']['events']['package_deleted']    = __( 'Package deleted', 'updatepulse-server' );
			$webhook_events['package']['events']['package_downloaded'] = __( 'Package downloaded via a signed URL', 'updatepulse-server' );
		}

		return $webhook_events;
	}

	/**
	 * Fetch nonce for public API
	 *
	 * Validate nonce for public API requests.
	 *
	 * @param mixed $nonce The nonce to validate.
	 * @param mixed $true_nonce The true nonce value.
	 * @param int   $expiry The nonce expiry time.
	 * @param array $data Additional data associated with the nonce.
	 * @return mixed Validated nonce or null if invalid.
	 * @since 1.0.0
	 */
	public function upserv_fetch_nonce_public( $nonce, $true_nonce, $expiry, $data ) {
		global $wp;

		$claim      = isset( $data['package_download'] ) && is_array( $data['package_download'] ) ? $data['package_download'] : array();
		$action     = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : null;
		$type       = isset( $wp->query_vars['type'] ) ? $wp->query_vars['type'] : null;
		$package_id = isset( $wp->query_vars['package_id'] ) ? $wp->query_vars['package_id'] : null;

		if (
			! isset( $claim['action'], $claim['type'], $claim['package_id'], $claim['issued_by'] ) ||
			'download' !== $claim['action'] ||
			'package_api' !== $claim['issued_by'] ||
			$action !== $claim['action'] ||
			$type !== $claim['type'] ||
			$package_id !== $claim['package_id']
		) {
			$nonce = null;
		}

		return $nonce;
	}

	/**
	 * Validate Package API authorization intent in a Nonce API payload.
	 *
	 * @since 1.1.0
	 *
	 * @param true|WP_Error $validation Previous validation result.
	 * @param array         $payload    The unmodified Nonce API payload.
	 * @param string        $method     The Nonce API action.
	 * @return true|WP_Error Validation result.
	 */
	public function upserv_nonce_api_payload_validation( $validation, $payload, $method ) {
		unset( $method );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wp;

		$data                     = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
		$targets_package_api      = isset( $wp->query_vars['api'] ) && 'package' === $wp->query_vars['api'];
		$package_api_token_intent = $targets_package_api || isset( $data['package_api'] );
		$package_download_intent  = isset( $data['actions'], $data['type'], $data['package_id'] )
			&& is_array( $data['actions'] )
			&& in_array( 'download', $data['actions'], true );
		$submitted_download_claim = isset( $data['package_download'] )
			&& is_array( $data['package_download'] )
			&& isset( $data['package_download']['action'] )
			&& 'download' === $data['package_download']['action'];

		if ( ! $package_api_token_intent && ! $package_download_intent && ! $submitted_download_claim ) {
			return true;
		}

		$config        = self::get_config();
		$authenticated = Nonce::authenticate_api_request( $config['private_api_auth_keys'] );

		if ( ! $authenticated || ! $this->authorize_ip() ) {
			return new WP_Error( 'invalid_parameters', __( 'Malformed request.', 'updatepulse-server' ) );
		}

		$key_id            = $authenticated['id'];
		$configured_access = $authenticated['access'];

		if ( isset( $data['package_api'] ) ) {
			$claim = $data['package_api'];

			if (
				! is_array( $claim ) ||
				! isset( $claim['id'], $claim['access'] ) ||
				$key_id !== $claim['id'] ||
				! is_array( $claim['access'] ) ||
				! Nonce::is_access_subset( $claim['access'], $configured_access )
			) {
				return new WP_Error( 'invalid_parameters', __( 'Malformed request.', 'updatepulse-server' ) );
			}
		}

		if ( $package_download_intent || $submitted_download_claim ) {
			$download_claim = $package_download_intent ? $data : $data['package_download'];
			$type           = isset( $download_claim['type'] ) ? $download_claim['type'] : null;
			$package_id     = isset( $download_claim['package_id'] ) ? $download_claim['package_id'] : null;
			$package_info   = $this->is_valid_package_target( $type, $package_id ) ? upserv_get_package_info( $package_id, false ) : false;

			if (
				! $this->has_api_access( $configured_access, 'signed_url' ) ||
				! is_array( $package_info ) ||
				! isset( $package_info['type'] ) ||
				$type !== $package_info['type']
			) {
				return new WP_Error( 'invalid_parameters', __( 'Malformed request.', 'updatepulse-server' ) );
			}
		}

		if ( $package_api_token_intent ) {
			$this->validated_nonce_api_claims['package_api'] = array(
				'id'     => $key_id,
				'access' => $configured_access,
			);
		}

		if ( $package_download_intent || $submitted_download_claim ) {
			$this->validated_nonce_api_claims['package_download'] = array(
				'action'     => 'download',
				'type'       => $download_claim['type'],
				'package_id' => $download_claim['package_id'],
				'issued_by'  => 'package_api',
			);
		}

		return true;
	}

	/**
	 * Fetch nonce for private API
	 *
	 * Validate nonce for private API requests.
	 *
	 * @param mixed $nonce The nonce to validate.
	 * @param mixed $true_nonce The true nonce value.
	 * @param int   $expiry The nonce expiry time.
	 * @param array $data Additional data associated with the nonce.
	 * @return mixed Validated nonce or null if invalid.
	 * @since 1.0.0
	 */
	public function upserv_fetch_nonce_private( $nonce, $true_nonce, $expiry, $data ) {
		$config = self::get_config();
		$valid  = false;

		if (
			! empty( $config['private_api_auth_keys'] ) &&
			isset( $data['package_api'], $data['package_api']['id'], $data['package_api']['access'] )
		) {
			global $wp;

			$action = $wp->query_vars['action'];

			foreach ( $config['private_api_auth_keys'] as $id => $values ) {

				if (
					$id === $data['package_api']['id'] &&
					isset( $values['access'] ) &&
					is_array( $values['access'] ) &&
					(
						in_array( 'all', $values['access'], true ) ||
						in_array( $action, $values['access'], true )
					)
				) {
					$this->api_key_id = $id;
					$this->api_access = $values['access'];
					$valid            = true;
				}
			}
		}

		if ( ! $valid ) {
			$nonce = null;
		}

		return $nonce;
	}

	/**
	 * Modify nonce API payload
	 *
	 * Adjust the payload for API nonce creation.
	 *
	 * @param array $payload The original payload.
	 * @return array Modified payload.
	 * @since 1.0.0
	 */
	public function upserv_nonce_api_payload( $payload ) {
		if ( ! $this->validated_nonce_api_claims ) {
			return $payload;
		}

		foreach ( $this->validated_nonce_api_claims as $claim_name => $claim ) {
			$payload['data'][ $claim_name ] = $claim;
		}

		$payload['expiry_length'] = HOUR_IN_SECONDS / 2;

		return $payload;
	}

	/**
	 * Filter package information inclusion
	 *
	 * Determine whether to include package information in responses.
	 *
	 * @param bool  $_include Current inclusion status.
	 * @param array $info Package information.
	 * @return bool Whether to include the package information.
	 * @since 1.0.0
	 */
	public function upserv_package_info_include( $_include, $info ) {
		return ! upserv_get_option( 'use_vcs' ) || upserv_is_package_whitelisted( $info['slug'] );
	}

	// Misc. -------------------------------------------------------

	/**
	 * Check if currently processing an API request
	 *
	 * Determine whether the current request is a Package API request.
	 *
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
	 * @since 1.0.0
	 */
	public static function is_doing_api_request() {

		if ( null === self::$doing_api_request ) {
			self::$doing_api_request = Utils::is_url_subpath_match( '/^updatepulse-server-package-api$/' );
		}

		return self::$doing_api_request;
	}

	/**
	 * Get Package API configuration
	 *
	 * Retrieve and filter the Package API configuration settings.
	 *
	 * @return array Package API configuration.
	 * @since 1.0.0
	 */
	public static function get_config() {

		if ( ! self::$config ) {
			$config = array(
				'use_vcs'               => upserv_get_option( 'use_vcs' ),
				'private_api_auth_keys' => upserv_get_option( 'api/packages/private_api_keys' ),
				'ip_whitelist'          => upserv_get_option( 'api/packages/private_api_ip_whitelist' ),
			);

			self::$config = $config;
		}

		/**
		 * Filter the configuration of the Package API.
		 *
		 * @param array $config The configuration of the Package API
		 * @return array The filtered configuration
		 * @since 1.0.0
		 */
		return apply_filters( 'upserv_package_api_config', self::$config );
	}

	/**
	 * Get Package API instance
	 *
	 * Retrieve or create the Package API singleton instance.
	 *
	 * @return Package_API The Package API instance.
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Get uploaded file
	 *
	 * Retrieve the uploaded file from a request.
	 *
	 * @return array|false File information array or false if no valid file.
	 * @since 1.0.0
	 */
	protected function get_file() {
		$files  = $_FILES; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$return = false;

		if (
			is_array( $files ) &&
			isset( $files['file'], $files['file']['tmp_name'], $files['file']['name'] ) &&
			is_uploaded_file( $files['file']['tmp_name'] ) &&
			sanitize_file_name( $files['file']['name'] ) === $files['file']['name']
		) {
			$return = array( $files['file']['tmp_name'], $files['file']['name'] );
		}

		return $return;
	}

	/**
	 * Process uploaded package file
	 *
	 * Handle validation and processing of an uploaded package file.
	 *
	 * @param array  $file The file information array.
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	protected function process_file( $file, $package_id, $type ) {
		list(
			$local_filename,
			$filename
		)          = $file;
		$file_hash = ! empty( $_SERVER['HTTP_FILE_HASH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_FILE_HASH'] ) ) : false;

		if ( hash_file( 'sha256', $local_filename ) !== $file_hash ) {
			wp_delete_file( $local_filename );

			return new WP_Error(
				'invalid_hash',
				__( 'The provided file does not match the provided hash.', 'updatepulse-server' )
			);
		}

		$zip_check   = wp_check_filetype( $filename, array( 'zip' => 'application/zip' ) );
		$bytes       = filesize( $local_filename ) > 4 ?
			file_get_contents( $local_filename, false, null, 0, 4 ) : // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			false;
		$bytes_check = $bytes ? '504b0304' === bin2hex( $bytes ) : false;

		if (
			! $bytes_check ||
			'zip' !== $zip_check['ext'] ||
			'application/zip' !== mime_content_type( $local_filename )
		) {
			wp_delete_file( $local_filename );

			return new WP_Error(
				'invalid_file_type',
				__( 'The provided file is not a valid ZIP file.', 'updatepulse-server' )
			);
		}

		$package_manager = new Zip_Package_Manager(
			$package_id,
			$local_filename,
			Data_Manager::get_data_dir( 'tmp' ),
			Data_Manager::get_data_dir( 'packages' )
		);

		$package = null;
		$result  = false;

		try {
			$result    = $package_manager->clean_package();
			$cache     = new Cache( Data_Manager::get_data_dir( 'cache' ) );
			$file_path = upserv_get_validated_package_path( $package_id, true );

			if ( ! $result || ! $file_path ) {
				throw new Invalid_Package_Exception( 'Invalid package path.' );
			}

			$package = Package::from_archive( $file_path, $package_id, $cache );
		} catch ( Invalid_Package_Exception ) {
			wp_delete_file( $local_filename );

			if ( upserv_is_valid_package_slug( $package_id ) ) {
				$tmp_root = realpath( Data_Manager::get_data_dir( 'tmp' ) );

				if ( false !== $tmp_root ) {
					wp_delete_file( trailingslashit( wp_normalize_path( $tmp_root ) ) . $package_id . '.zip' );
				}

				$package_path = upserv_get_validated_package_path( $package_id, true );

				if ( $package_path ) {
					wp_delete_file( $package_path );
				}
			}

			$result = false;
		}

		if ( ! $result ) {
			return new WP_Error(
				'invalid_package',
				__( 'The provided file is not a valid package.', 'updatepulse-server' )
			);
		}

		$vcs_url      = filter_input( INPUT_POST, 'vcs_url', FILTER_SANITIZE_URL );
		$branch       = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'branch' ) ) );
		$meta         = upserv_get_package_metadata( $package_id );
		$meta['type'] = $type;

		if ( $vcs_url ) {
			$branch          = $branch ? $branch : 'main';
			$vcs_configs     = upserv_get_option( 'vcs', array() );
			$meta['vcs_key'] = hash( 'sha256', trailingslashit( $vcs_url ) . '|' . $branch );
			$meta['origin']  = 'vcs';
			$meta['branch']  = $branch;
			$meta['vcs']     = trailingslashit( $vcs_url );

			if ( isset( $vcs_configs[ $meta['vcs_key'] ] ) ) {
				upserv_set_package_metadata( $package_id, $meta );
			} else {
				wp_delete_file( $package->get_filename() );

				return new WP_Error(
					'invalid_vcs',
					__( 'The provided VCS information is not valid', 'updatepulse-server' )
				);
			}
		} else {
			$meta['origin'] = 'manual';

			upserv_set_package_metadata( $package_id, $meta );
		}

		/**
		 * Fired after an attempt to save a downloaded package on the file system has been performed.
		 * Fired during client update API request.
		 *
		 * @param bool $result `true` in case of success, `false` otherwise
		 * @param string $type type of the saved package - `"Plugin"`, `"Theme"`, or `"Generic"`
		 * @param string $package_slug slug of the saved package
		 * @since 1.0.0
		 */
		do_action( 'upserv_saved_remote_package_to_local', true, $type, $package_id );

		return $result;
	}

	/**
	 * Download a package file from VCS
	 *
	 * Fetch a package from its version control system source.
	 *
	 * @param string $package_id The package ID/slug.
	 * @param string $type The package type.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	protected function download_file( $package_id, $type ) {
		$vcs_url = filter_input( INPUT_POST, 'vcs_url', FILTER_SANITIZE_URL );
		$branch  = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'branch' ) ) );
		$result  = false;

		if ( $vcs_url ) {
			$branch = $branch ? $branch : 'main';
			$result = upserv_download_remote_package( $package_id, $type, $vcs_url, $branch );
		} else {
			$result = upserv_download_remote_package( $package_id, $type );
		}

		return $result;
	}

	/**
	 * Authorize public API request
	 *
	 * Validate authorization for public API endpoints.
	 *
	 * @return bool Whether the request is authorized.
	 * @since 1.0.0
	 */
	protected function authorize_public() {
		$nonce = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'token' ) ) );

		if ( ! $nonce ) {
			$nonce = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'nonce' ) ) );
		}

		add_filter( 'upserv_fetch_nonce', array( $this, 'upserv_fetch_nonce_public' ), 10, 4 );

		$result = upserv_validate_nonce( $nonce );

		remove_filter( 'upserv_fetch_nonce', array( $this, 'upserv_fetch_nonce_public' ), 10 );

		return $result;
	}

	/**
	 * Authorize private API request
	 *
	 * Validate authorization for private API endpoints.
	 *
	 * @param string $action The requested API action.
	 * @return bool Whether the request is authorized.
	 * @since 1.0.0
	 */
	protected function authorize_private( $action ) {
		$token   = false;
		$is_auth = false;

		if ( ! empty( $_SERVER['HTTP_X_UPDATEPULSE_TOKEN'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_UPDATEPULSE_TOKEN'] ) );
		} else {
			global $wp;

			if (
				isset( $wp->query_vars['api_token'] ) &&
				is_string( $wp->query_vars['api_token'] ) &&
				! empty( $wp->query_vars['api_token'] )
			) {
				$token = $wp->query_vars['api_token'];
			}
		}

		add_filter( 'upserv_fetch_nonce', array( $this, 'upserv_fetch_nonce_private' ), 10, 4 );

		$is_auth = upserv_validate_nonce( $token );

		remove_filter( 'upserv_fetch_nonce', array( $this, 'upserv_fetch_nonce_private' ), 10 );

		if ( $this->api_key_id && $this->api_access ) {
			$is_auth = $is_auth && (
				in_array( 'all', $this->api_access, true ) ||
				in_array( $action, $this->api_access, true )
			);
		}

		return $is_auth;
	}

	/**
	 * Check if API action is public
	 *
	 * Determine if a specific API action is available publicly.
	 *
	 * @param string $method The API method to check.
	 * @return bool Whether the API action is public.
	 * @since 1.0.0
	 */
	protected function is_api_public( $method ) {
		/**
		 * Filter the public API actions; public actions can be accessed via the `GET` method and a token,
		 * all other actions are considered private and can only be accessed via the `POST` method.
		 *
		 * @param array $public_api_actions The public API actions
		 * @return array The filtered public API actions
		 * @since 1.0.0
		 */
		$public_api    = apply_filters(
			'upserv_package_public_api_actions',
			array( 'download' )
		);
		$is_api_public = in_array( $method, $public_api, true );

		return $is_api_public;
	}

	/**
	 * Handle incoming API requests
	 *
	 * Process and respond to Package API requests.
	 *
	 * @since 1.0.0
	 */
	protected function handle_api_request() {
		global $wp;

		$method             = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : false;
		$registered_actions = array( 'browse', 'read', 'edit', 'add', 'delete', 'download', 'signed_url' );
		$target_actions     = array( 'read', 'edit', 'add', 'delete', 'download', 'signed_url' );
		$type               = isset( $wp->query_vars['type'] ) ? $wp->query_vars['type'] : null;
		$package_id         = isset( $wp->query_vars['package_id'] ) ? $wp->query_vars['package_id'] : null;

		if ( in_array( $method, $target_actions, true ) && ! $this->is_valid_package_target( $type, $package_id ) ) {
			$this->http_response_code = 400;
			$response                 = array(
				'code'    => 'invalid_parameters',
				'message' => __( 'Malformed request.', 'updatepulse-server' ),
			);
		} elseif (
			sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'action' ) ) ) &&
			! $this->is_api_public( $method )
		) {
			$this->http_response_code = 405;
			$response                 = array(
				'code'    => 'method_not_allowed',
				'message' => __( 'Unauthorized GET method.', 'updatepulse-server' ),
			);
		} else {
			$malformed_request = false;

			if ( ! isset( $wp->query_vars['action'] ) ) {
				$malformed_request = true;
			} elseif (
				'browse' === $wp->query_vars['action'] &&
				isset( $wp->query_vars['browse_query'] )
			) {
				$payload = $wp->query_vars['browse_query'];
			} else {
				$payload = $wp->query_vars;
			}

			if ( ! $malformed_request ) {
				/**
				 * Filter whether the Package API request is authorized
				 *
				 * @param bool $authorized Whether the Package API request is authorized
				 * @param string $method The method of the request - `GET` or `POST`
				 * @param array $payload The payload of the request
				 * @return bool The filtered authorization status
				 * @since 1.0.0
				 */
				$authorized = apply_filters(
					'upserv_package_api_request_authorized',
					(
						(
							$this->is_api_public( $method ) &&
							$this->authorize_public()
						) ||
						(
							$this->authorize_private( $method ) &&
							$this->authorize_ip()
						)
					),
					$method,
					$payload
				);

				if ( $authorized ) {
					/**
					 * Fired before the Package API request is processed; useful to bypass the execution of currently implemented actions, or implement new actions.
					 *
					 * @param string $action the Package API action
					 * @param array $payload the payload of the request
					 * @since 1.0.0
					 */
					do_action( 'upserv_package_api_request', $method, $payload );

					if ( in_array( $method, $registered_actions, true ) && method_exists( $this, $method ) ) {
						$type       = isset( $payload['type'] ) ? $payload['type'] : null;
						$package_id = isset( $payload['package_id'] ) ? $payload['package_id'] : null;

						if ( $type && $package_id ) {
							$response = $this->$method( $package_id, $type );
						} else {
							$response = $this->$method( $payload );
						}

						if ( is_object( $response ) && ! empty( get_object_vars( $response ) ) ) {
							$response->time_elapsed = Utils::get_time_elapsed();
						}
					} else {
						$this->http_response_code = 400;
						$response                 = array(
							'code'    => 'action_not_found',
							'message' => __( 'Package API action not found.', 'updatepulse-server' ),
						);
					}
				} else {
					$this->http_response_code = 403;
					$response                 = array(
						'code'    => 'unauthorized',
						'message' => __( 'Unauthorized access.', 'updatepulse-server' ),
					);
				}
			} else {
				$this->http_response_code = 400;
				$response                 = array(
					'code'    => 'malformed_request',
					'message' => __( 'Malformed request.', 'updatepulse-server' ),
				);
			}
		}

		wp_send_json( $response, $this->http_response_code, Utils::JSON_OPTIONS );
	}

	/**
	 * Authorize request by IP address
	 *
	 * Validate if the request IP is allowed.
	 *
	 * @return bool Whether the request IP is authorized.
	 * @since 1.0.0
	 */
	protected function authorize_ip() {
		$result = false;
		$config = self::get_config();

		if ( is_array( $config['ip_whitelist'] ) && ! empty( $config['ip_whitelist'] ) ) {

			foreach ( $config['ip_whitelist'] as $range ) {

				if ( Utils::cidr_match( Utils::get_remote_ip(), $range ) ) {
					$result = true;

					break;
				}
			}
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Determine whether configured access grants an action.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $configured_access Configured access entries.
	 * @param string $action            Requested action.
	 * @return bool Whether access is granted.
	 */
	protected function has_api_access( $configured_access, $action ) {
		return in_array( 'all', $configured_access, true ) || in_array( $action, $configured_access, true );
	}

	/**
	 * Validate a package API target before authorization or dispatch.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $type       Package type.
	 * @param mixed $package_id Package slug.
	 * @return bool Whether the target is valid.
	 */
	protected function is_valid_package_target( $type, $package_id ) {
		return is_string( $type )
			&& in_array( $type, array( 'plugin', 'theme', 'generic' ), true )
			&& upserv_is_valid_package_slug( $package_id );
	}
}
