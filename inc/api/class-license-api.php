<?php

namespace Anyape\UpdatePulse\Server\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;
use Exception;
use Anyape\UpdatePulse\Server\Server\License\License_Server;
use Anyape\Utils\Utils;

/**
 * License API
 *
 * @since 1.0.0
 */
class License_API {

	/**
	 * Is doing API request
	 *
	 * @var boolean|null
	 * @since 1.0.0
	 */
	protected static $doing_api_request = null;
	/**
	 * Instance
	 *
	 * @var License_API|null
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
	 * License server
	 *
	 * @var License_Server
	 * @since 1.0.0
	 */
	protected $license_server;
	/**
	 * HTTP response code
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	protected $http_response_code = null;
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
	 * Constructor
	 *
	 * @param boolean $init_hooks
	 * @param boolean $local_request
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false, $local_request = true ) {

		if ( upserv_get_option( 'use_licenses' ) ) {

			if ( $local_request ) {
				$this->init_server();
			}

			if ( $init_hooks ) {
				add_action( 'init', array( $this, 'add_endpoints' ), -99, 0 );
				add_action( 'parse_request', array( $this, 'parse_request' ), -99, 0 );
				add_action( 'upserv_pre_activate_license', array( $this, 'upserv_bypass_did_edit_license_action' ), 10, 0 );
				add_action( 'upserv_did_activate_license', array( $this, 'upserv_did_license_action' ), 20, 2 );
				add_action( 'upserv_pre_deactivate_license', array( $this, 'upserv_bypass_did_edit_license_action' ), 10, 0 );
				add_action( 'upserv_did_deactivate_license', array( $this, 'upserv_did_license_action' ), 20, 2 );
				add_action( 'upserv_did_add_license', array( $this, 'upserv_did_license_action' ), 20, 2 );
				add_action( 'upserv_did_edit_license', array( $this, 'upserv_did_license_action' ), 20, 3 );
				add_action( 'upserv_did_delete_license', array( $this, 'upserv_did_license_action' ), 20, 2 );

				add_filter( 'query_vars', array( $this, 'query_vars' ), -99, 1 );
				add_filter( 'upserv_handle_update_request_params', array( $this, 'upserv_handle_update_request_params' ), 0, 1 );
				add_filter( 'upserv_api_license_actions', array( $this, 'upserv_api_license_actions' ), 0, 1 );
				add_filter( 'upserv_api_webhook_events', array( $this, 'upserv_api_webhook_events' ), 0, 1 );
				add_filter( 'upserv_nonce_api_payload', array( $this, 'upserv_nonce_api_payload' ), 0, 1 );
			}
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// API action --------------------------------------------------

	/**
	 * Browse licenses
	 *
	 * @param string $query
	 * @return object Result of the browse operation
	 * @since 1.0.0
	 */
	public function browse( $query ) {
		$payload = json_decode( wp_unslash( $query ), true );

		switch ( json_last_error() ) {
			case JSON_ERROR_NONE:
				if ( ! empty( $payload['criteria'] ) ) {

					foreach ( $payload['criteria'] as $index => $criteria ) {

						if ( 'id' === $criteria['field'] ) {
							unset( $payload['criteria'][ $index ] );
						}
					}
				}

				$result = $this->license_server->browse_licenses( $payload );

				if (
					is_array( $result ) &&
					! empty( $result ) &&
					$this->api_access &&
					$this->api_key_id &&
					! in_array( 'other', $this->api_access, true )
				) {

					foreach ( $result as $index => $license ) {

						if (
							! isset( $license->data, $license->data['api_owner'] ) ||
							$license->data['api_owner'] !== $this->api_key_id
						) {
							unset( $result[ $index ] );
						}
					}
				}

				break;
			case JSON_ERROR_DEPTH:
				$result = 'JSON parse error - Maximum stack depth exceeded.';

				break;
			case JSON_ERROR_STATE_MISMATCH:
				$result = 'JSON parse error - Underflow or the modes mismatch.';

				break;
			case JSON_ERROR_CTRL_CHAR:
				$result = 'JSON parse error - Unexpected control character found.';

				break;
			case JSON_ERROR_SYNTAX:
				$result = 'JSON parse error - Syntax error, malformed JSON.';

				break;
			case JSON_ERROR_UTF8:
				$result = 'JSON parse error - Malformed UTF-8 characters, possibly incorrectly encoded.';

				break;
			default:
				$result = 'JSON parse error - Unknown error.';

				break;
		}

		if ( $result instanceof WP_Error ) {
			$this->http_response_code = 400;
			$result                   = array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
		} elseif ( ! is_array( $result ) ) {
			$this->http_response_code = 400;
			$result                   = array(
				'code'    => 'invalid_json',
				'message' => $result,
			);
		} elseif ( empty( $result ) ) {
			$this->http_response_code = 404;
			$result                   = array(
				'code'    => 'licenses_not_found',
				'message' => __( 'Licenses not found.', 'updatepulse-server' ),
			);
		} else {
			$result['count'] = count( $result );
		}

		return (object) $result;
	}

	/**
	 * Read license
	 *
	 * @param array $license_data
	 * @return object Result of the read operation
	 * @since 1.0.0
	 */
	public function read( $license_data ) {
		$result = wp_cache_get(
			'upserv_license_' . md5( wp_json_encode( $license_data ) ),
			'updatepulse-server',
			false,
			$found
		);

		if ( ! $found ) {
			$result = $this->license_server->read_license( $license_data );

			wp_cache_set(
				'upserv_license_' . md5( wp_json_encode( $license_data ) ),
				$result,
				'updatepulse-server'
			);
		}

		if ( ! is_object( $result ) ) {

			if ( isset( $result['license_not_found'] ) ) {
				$this->http_response_code = 404;
				$result                   = array(
					'code'    => 'license_not_found',
					'message' => __( 'License not found.', 'updatepulse-server' ),
				);
			} else {
				$this->http_response_code = 400;
				$result                   = array(
					'code'    => 'invalid_license_data',
					'message' => __( 'Invalid license data.', 'updatepulse-server' ),
				);
			}
		} elseif ( ! isset( $result->license_key ) ) {
			$this->http_response_code = 404;
			$result                   = array(
				'code'    => 'license_not_found',
				'message' => __( 'License not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Edit license
	 *
	 * @param array $license_data
	 * @return object Result of the edit operation
	 * @since 1.0.0
	 */
	public function edit( $license_data ) {

		if ( upserv_is_doing_api_request() ) {

			if ( ! $this->api_access || ! $this->api_key_id || ! in_array( 'other', $this->api_access, true ) ) {
				$original = $this->read( $license_data );

				if ( isset( $original->data, $original->data['api_owner'] ) ) {
					$license_data['data']['api_owner'] = $original->data['api_owner'];
				} else {
					unset( $license_data['data']['api_owner'] );
				}
			}
		}

		$result = $this->license_server->edit_license( $license_data );

		if ( ! is_object( $result ) ) {

			if ( ! is_array( $result ) ) {
				$this->http_response_code = 400;
				$result                   = array(
					'code'    => 'invalid_license_data',
					'errors'  => array( __( 'Unknown error.', 'updatepulse-server' ) ),
					'message' => __( 'Invalid license data.', 'updatepulse-server' ),
				);
			} elseif ( isset( $result['license_not_found'] ) ) {
				$this->http_response_code = 404;
				$result                   = array(
					'code'    => 'license_not_found',
					'message' => __( 'License not found.', 'updatepulse-server' ),
				);
			} elseif ( ! empty( $result ) ) {
				$this->http_response_code = 400;
				$result                   = array(
					'code'    => 'invalid_license_data',
					'errors'  => array_values( $result ),
					'message' => __( 'Invalid license data.', 'updatepulse-server' ),
				);
			} else {
				$this->http_response_code = 400;
				$result                   = array(
					'code'    => 'invalid_license_data',
					'errors'  => array( __( 'Unknown error.', 'updatepulse-server' ) ),
					'message' => __( 'Invalid license data.', 'updatepulse-server' ),
				);
			}
		} elseif ( ! isset( $result->license_key ) ) {
			$this->http_response_code = 404;
			$result                   = array(
				'code'    => 'license_not_found',
				'message' => __( 'License not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Add license
	 *
	 * @param array $license_data
	 * @return object Result of the add operation
	 * @since 1.0.0
	 */
	public function add( $license_data ) {

		if ( $this->api_key_id ) {
			$license_data['data']['api_owner'] = $this->api_key_id;
		}

		$result = $this->license_server->add_license( $license_data );

		if ( is_object( $result ) ) {
			$result->result  = 'success';
			$result->message = 'License successfully created';
			$result->key     = $result->license_key;
		} elseif ( ! is_array( $result ) ) {
			$this->http_response_code = 400;
			$result                   = array(
				'code'    => 'invalid_license_data',
				'errors'  => array( __( 'Unknown error.', 'updatepulse-server' ) ),
				'message' => __( 'Invalid license data.', 'updatepulse-server' ),
			);
		} elseif ( ! empty( $result ) ) {
			$this->http_response_code = 400;
			$result                   = array(
				'code'    => 'invalid_license_data',
				'errors'  => array_values( $result ),
				'message' => __( 'Invalid license data.', 'updatepulse-server' ),
			);
		} else {
			$this->http_response_code = 400;
			$result                   = array(
				'code'    => 'invalid_license_data',
				'errors'  => array( __( 'Unknown error.', 'updatepulse-server' ) ),
				'message' => __( 'Invalid license data.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Delete license
	 *
	 * @param array $license_data
	 * @return object Result of the delete operation
	 * @since 1.0.0
	 */
	public function delete( $license_data ) {
		$result = $this->license_server->delete_license( $license_data );

		if ( ! is_object( $result ) ) {

			if ( isset( $license['license_not_found'] ) ) {
				$this->http_response_code = 404;
				$result                   = array(
					'code'    => 'license_not_found',
					'message' => __( 'License not found.', 'updatepulse-server' ),
				);
			} else {
				$this->http_response_code = 400;
				$result                   = array(
					'code'    => 'invalid_license_data',
					'message' => __( 'Invalid license data.', 'updatepulse-server' ),
				);
			}
		} elseif ( ! isset( $result->license_key ) ) {
			$this->http_response_code = 404;
			$result                   = array(
				'code'    => 'license_not_found',
				'message' => __( 'License not found.', 'updatepulse-server' ),
			);
		}

		return (object) $result;
	}

	/**
	 * Check license
	 *
	 * @param array $license_data
	 * @return object Result of the check operation
	 * @since 1.0.0
	 */
	public function check( $license_data ) {
		/**
		 * Filter the license data payload before checking a license.
		 *
		 * @param array $license_data The license data payload.
		 * @since 1.0.0
		 */
		$license_data = apply_filters( 'upserv_check_license_dirty_payload', $license_data );
		$license      = $this->license_server->read_license( $license_data );
		$raw_result   = array();

		if ( is_object( $license ) ) {
			$raw_result = clone $license;

			$this->sanitize_license_result( $license );
		} else {
			$raw_result = $license;
			$result     = null;
		}

		/**
		 * Filter the result of the license check operation.
		 *
		 * @param object|null $license      The license object or null if not found.
		 * @param array       $license_data The license data payload.
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_check_license_result', $license, $license_data );

		/**
		 * Fired after checking a license.
		 *
		 * @param mixed $raw_result The raw result of the license check.
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_check_license', $raw_result );

		if ( ! is_object( $result ) ) {
			$this->handle_invalid_license( $result, $license_data );
		}

		return (object) $result;
	}

	/**
	 * Activate license
	 *
	 * @param array $license_data
	 * @return object Result of the activate operation
	 * @since 1.0.0
	 */
	public function activate( $license_data ) {
		/**
		 * Filter the license data payload before activating a license.
		 *
		 * @param array $license_data The license data payload.
		 * @since 1.0.0
		 */
		$license_data = apply_filters( 'upserv_activate_license_dirty_payload', $license_data );

		$this->normalize_allowed_domains( $license_data );

		$request_slug = isset( $license_data['package_slug'] ) ? $license_data['package_slug'] : false;
		$license      = $this->license_server->read_license( $license_data );
		$domain       = $this->extract_domain_from_license_data( $license_data );

		/**
		 * Fired before activating a license.
		 *
		 * @param object $license The license object.
		 * @since 1.0.0
		 */
		do_action( 'upserv_pre_activate_license', $license );

		if ( $this->is_valid_license_for_state_transition( $license, $request_slug, $domain ) ) {
			$result = $this->handle_license_activation( $license, $domain );
		} else {
			$result = $this->handle_invalid_license( $license, $license_data );
		}

		$raw_result = isset( $result['raw_result'] ) ? $result['raw_result'] : $result;
		$result     = isset( $result['result'] ) ? $result['result'] : $result;

		/**
		 * Filter the result of the license activation operation.
		 *
		 * @param object     $result       The result of the license activation.
		 * @param array      $license_data The license data payload.
		 * @param object     $license      The license object.
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_activate_license_result', $result, $license_data, $license );

		/**
		 * Fired after activating a license.
		 *
		 * @param mixed $raw_result   The raw result of the license activation.
		 * @param array $license_data The license data payload.
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_activate_license', $raw_result, $license_data );

		return (object) $result;
	}

	/**
	 * Deactivate license
	 *
	 * @param array $license_data
	 * @return object Result of the deactivate operation
	 * @since 1.0.0
	 */
	public function deactivate( $license_data ) {
		/**
		 * Filter the license data payload before deactivating a license.
		 *
		 * @param array $license_data The license data payload.
		 * @since 1.0.0
		 */
		$license_data = apply_filters( 'upserv_deactivate_license_dirty_payload', $license_data );

		$this->normalize_allowed_domains( $license_data );

		$request_slug = isset( $license_data['package_slug'] ) ? $license_data['package_slug'] : false;
		$license      = $this->license_server->read_license( $license_data );
		$domain       = $this->extract_domain_from_license_data( $license_data );

		/**
		 * Fired before deactivating a license.
		 *
		 * @param object $license The license object.
		 * @since 1.0.0
		 */
		do_action( 'upserv_pre_deactivate_license', $license );

		if ( $this->is_valid_license_for_state_transition( $license, $request_slug, $domain ) ) {
			$result = $this->handle_license_deactivation( $license, $domain );
		} else {
			$result = $this->handle_invalid_license( $license, $license_data );
		}

		$raw_result = isset( $result['raw_result'] ) ? $result['raw_result'] : $result;
		$result     = isset( $result['result'] ) ? $result['result'] : $result;

		/**
		 * Filter the result of the license deactivation operation.
		 *
		 * @param object     $result       The result of the license deactivation.
		 * @param array      $license_data The license data payload.
		 * @param object     $license      The license object.
		 * @since 1.0.0
		 */
		$result = apply_filters( 'upserv_deactivate_license_result', $result, $license_data, $license );

		/**
		 * Fired after deactivating a license.
		 *
		 * @param mixed $raw_result   The raw result of the license deactivation.
		 * @param array $license_data The license data payload.
		 * @since 1.0.0
		 */
		do_action( 'upserv_did_deactivate_license', $raw_result, $license_data );

		return (object) $result;
	}

	// WordPress hooks ---------------------------------------------

	/**
	 * Add endpoints
	 *
	 * @since 1.0.0
	 */
	public function add_endpoints() {
		add_rewrite_rule(
			'^updatepulse-server-license-api/*$',
			'index.php?$matches[1]&__upserv_license_api=1&',
			'top'
		);
		add_rewrite_rule(
			'^updatepulse-server-license-api$',
			'index.php?&__upserv_license_api=1&',
			'top'
		);
	}

	/**
	 * Parse request
	 *
	 * @since 1.0.0
	 */
	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__upserv_license_api'] ) ) {
			$this->handle_api_request();

			exit;
		}
	}

	/**
	 * Query vars filter
	 *
	 * @param array $query_vars
	 * @return array The filtered query vars
	 * @since 1.0.0
	 */
	public function query_vars( $query_vars ) {
		$query_vars = array_merge(
			$query_vars,
			array(
				'__upserv_license_api',
				'action',
				'api',
				'api_token',
				'api_credentials',
				'browse_query',
				'license_key',
				'license_signature',
			),
			array_keys( License_Server::$license_definition )
		);

		return $query_vars;
	}

	/**
	 * Filter update request params
	 *
	 * @param array $params
	 * @return array The filtered params
	 * @since 1.0.0
	 */
	public function upserv_handle_update_request_params( $params ) {
		global $wp;

		$vars                        = $wp->query_vars;
		$params['license_key']       = isset( $vars['license_key'] ) ?
			trim( $vars['license_key'] ) :
			null;
		$params['license_signature'] = isset( $vars['license_signature'] ) ?
			trim( $vars['license_signature'] ) :
			null;

		return $params;
	}

	/**
	 * Filter license actions
	 *
	 * @param array $actions
	 * @return array The filtered actions
	 * @since 1.0.0
	 */
	public function upserv_api_license_actions( $actions ) {
		$actions['browse'] = __( 'Browse multiple license records', 'updatepulse-server' );
		$actions['read']   = __( 'Get single license records', 'updatepulse-server' );
		$actions['edit']   = __( 'Update license records', 'updatepulse-server' );
		$actions['add']    = __( 'Create license records', 'updatepulse-server' );
		$actions['delete'] = __( 'Delete license records', 'updatepulse-server' );

		return $actions;
	}

	/**
	 * Filter webhook events
	 *
	 * @param array $webhook_events
	 * @return array The filtered webhook events
	 * @since 1.0.0
	 */
	public function upserv_api_webhook_events( $webhook_events ) {

		if ( isset( $webhook_events['license'], $webhook_events['license']['events'] ) ) {
			$webhook_events['license']['events']['license_activate']   = __( 'License activated', 'updatepulse-server' );
			$webhook_events['license']['events']['license_deactivate'] = __( 'License deactivated', 'updatepulse-server' );
			$webhook_events['license']['events']['license_add']        = __( 'License added', 'updatepulse-server' );
			$webhook_events['license']['events']['license_edit']       = __( 'License edited', 'updatepulse-server' );
			$webhook_events['license']['events']['license_delete']     = __( 'License deleted', 'updatepulse-server' );
		}

		return $webhook_events;
	}

	/**
	 * Bypass license action
	 *
	 * @since 1.0.0
	 */
	public function upserv_bypass_did_edit_license_action() {
		remove_action( 'upserv_did_edit_license', array( $this, 'upserv_did_license_action' ), 20 );
	}

	/**
	 * License action
	 *
	 * @param object $result
	 * @param array  $payload
	 * @param object $original
	 * @since 1.0.0
	 */
	public function upserv_did_license_action( $result, $payload, $original = null ) {
		$format = '';
		$event  = 'license_' . str_replace(
			array( 'upserv_did_', '_license' ),
			array( '', '' ),
			current_action()
		);

		if ( ! is_object( $result ) ) {
			$description = sprintf(
				// translators: %s is operation slug
				esc_html__( 'An error occured for License operation `%s` on UpdatePulse Server.', 'updatepulse-server' ),
				$event
			);
			$content = array(
				'error'   => true,
				'result'  => $result,
				'payload' => $payload,
			);
		} else {
			$content = null !== $original ?
				array(
					'new'      => $result,
					'original' => $original,
				) :
				$result;

			switch ( $event ) {
				case 'license_edit':
					// translators: %s is the license key
					$format = esc_html__( 'The license `%s` has been edited on UpdatePulse Server', 'updatepulse-server' );

					break;
				case 'license_add':
					// translators: %s is the license key
					$format = esc_html__( 'The license `%s` has been added on UpdatePulse Server', 'updatepulse-server' );

					break;
				case 'license_delete':
					// translators: %s is the license key
					$format = esc_html__( 'The license `%s` has been deleted on UpdatePulse Server', 'updatepulse-server' );

					break;
				case 'license_activate':
					// translators: %s is the license key
					$format = esc_html__( 'The license `%s` has been activated on UpdatePulse Server', 'updatepulse-server' );

					break;
				case 'license_deactivate':
					// translators: %s is the license key
					$format = esc_html__( 'The license `%s` has been deactivated on UpdatePulse Server', 'updatepulse-server' );

					break;
				default:
					return;
			}

			$description = sprintf( $format, $result->license_key );
		}

		$payload = array(
			'event'       => $event,
			'description' => $description,
			'content'     => $content,
		);

		add_filter( 'upserv_webhook_fire', array( $this, 'upserv_webhook_fire' ), 10, 4 );
		upserv_schedule_webhook( $payload, 'license' );
		remove_filter( 'upserv_webhook_fire', array( $this, 'upserv_webhook_fire' ), 10 );
	}

	/**
	 * Webhook fire filter
	 *
	 * @param boolean $fire
	 * @param array   $payload
	 * @param string  $url
	 * @param array   $info
	 * @return boolean The filtered fire value
	 * @since 1.0.0
	 */
	public function upserv_webhook_fire( $fire, $payload, $url, $info ) {

		if ( ! isset( $info['licenseAPIKey'] ) || empty( $info['licenseAPIKey'] ) ) {
			return $fire;
		}

		$owner = false;

		if (
			is_array( $payload['content'] ) &&
			isset( $payload['content']['new'] ) &&
			isset( $payload['content']['new']->data['api_owner'] )
		) {
			$owner = $payload['content']['new']->data['api_owner'];
		} elseif (
			is_object( $payload['content'] ) &&
			isset( $payload['content']->data['api_owner'] )
		) {
			$owner = $payload['content']->data['api_owner'];
		}

		$config     = self::get_config();
		$api_access = false;

		foreach ( $config['private_api_auth_keys'] as $id => $values ) {

			if (
				$id === $info['licenseAPIKey'] &&
				isset( $values['access'] ) &&
				is_array( $values['access'] )
			) {
				$api_access = $values['access'];

				break;
			}
		}

		if ( $api_access && in_array( 'other', $api_access, true ) ) {
			$fire = true;
		} elseif ( $api_access ) {
			$action = str_replace( 'license_', '', $payload['event'] );

			if (
				in_array( 'all', $api_access, true ) ||
				in_array( 'read', $api_access, true ) ||
				in_array( 'browse', $api_access, true ) ||
				(
					in_array( $action, array( 'edit', 'add', 'delete' ), true ) &&
					in_array( $action, $api_access, true )
				)
			) {
				$fire = $owner === $info['licenseAPIKey'];
			} else {
				$fire = false;
			}
		} else {
			$fire = $owner === $info['licenseAPIKey'];
		}

		return $fire;
	}

	/**
	 * Fetch nonce filter
	 *
	 * @param string $nonce
	 * @param string $true_nonce
	 * @param int    $expiry
	 * @param array  $data
	 * @return string|null The filterd nonce or null if invalid
	 * @since 1.0.0
	 */
	public function upserv_fetch_nonce_private( $nonce, $true_nonce, $expiry, $data ) {
		$config = self::get_config();
		$valid  = false;

		if (
			! empty( $config['private_api_auth_keys'] ) &&
			isset( $data['license_api'], $data['license_api']['id'], $data['license_api']['access'] )
		) {
			global $wp;

			$action = $wp->query_vars['action'];

			foreach ( $config['private_api_auth_keys'] as $id => $values ) {

				if (
					$id === $data['license_api']['id'] &&
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

					break;
				}
			}
		}

		if ( ! $valid ) {
			$nonce = null;
		}

		return $nonce;
	}

	/**
	 * Nonce API payload filter
	 *
	 * @param array $payload
	 * @return array The filtered payload
	 * @since 1.0.0
	 */
	public function upserv_nonce_api_payload( $payload ) {
		global $wp;

		if ( ! isset( $wp->query_vars['api'] ) || 'license' !== $wp->query_vars['api'] ) {
			return $payload;
		}

		$key_id      = false;
		$credentials = array();
		$config      = self::get_config();

		if ( ! empty( $_SERVER['HTTP_X_UPDATEPULSE_API_CREDENTIALS'] ) ) {
			$credentials = explode(
				'|',
				sanitize_text_field(
					wp_unslash( $_SERVER['HTTP_X_UPDATEPULSE_API_CREDENTIALS'] )
				)
			);
		} elseif (
			isset( $wp->query_vars['api_credentials'], $wp->query_vars['api'] ) &&
			is_string( $wp->query_vars['api_credentials'] ) &&
			! empty( $wp->query_vars['api_credentials'] )
		) {
			$credentials = explode( '|', $wp->query_vars['api_credentials'] );
		}

		if ( 2 === count( $credentials ) ) {
			$key_id = end( $credentials );
		}

		if ( $key_id && isset( $config['private_api_auth_keys'][ $key_id ] ) ) {
			$values                         = $config['private_api_auth_keys'][ $key_id ];
			$payload['data']['license_api'] = array(
				'id'     => $key_id,
				'access' => isset( $values['access'] ) ? $values['access'] : array(),
			);
		}

		$payload['expiry_length'] = HOUR_IN_SECONDS / 2;

		return $payload;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Is doing API request
	 *
	 * @return boolean True if doing API request, false otherwise
	 * @since 1.0.0
	 */
	public static function is_doing_api_request() {

		if ( null === self::$doing_api_request ) {
			self::$doing_api_request = Utils::is_url_subpath_match( '/^updatepulse-server-license-api$/' );
		}

		return self::$doing_api_request;
	}

	/**
	 * Get config
	 *
	 * @return array The config
	 * @since 1.0.0
	 */
	public static function get_config() {

		if ( ! self::$config ) {
			$config = array(
				'private_api_auth_keys' => upserv_get_option( 'api/licenses/private_api_keys' ),
				'ip_whitelist'          => upserv_get_option( 'api/licenses/private_api_ip_whitelist' ),
			);

			self::$config = $config;
		}

		/**
		 * Filter the License API configuration.
		 *
		 * @param array $config The License API configuration.
		 * @since 1.0.0
		 */
		return apply_filters( 'upserv_license_api_config', self::$config );
	}

	/**
	 * Get instance
	 *
	 * @return License_API The instance
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Is package require license
	 *
	 * @param int $package_id
	 * @return boolean True if package requires license, false otherwise
	 * @since 1.0.0
	 */
	public static function is_package_require_license( $package_id ) {
		$require_license = wp_cache_get( 'upserv_package_require_license_' . $package_id, 'updatepulse-server', false, $found );

		if ( ! $found ) {
			$package_info    = upserv_get_package_info( $package_id, false );
			$require_license = (
				is_array( $package_info ) &&
				isset( $package_info['require_license'] ) &&
				$package_info['require_license']
			);

			wp_cache_set( 'upserv_package_require_license_' . $package_id, $require_license, 'updatepulse-server' );
		}

		return $require_license;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Sanitize license result
	 *
	 * @param object $result - by reference
	 * @return void
	 * @since 1.0.0
	 */
	protected function sanitize_license_result( &$result ) {
		$num_allowed_domains         = (
				isset( $result->allowed_domains ) &&
				is_array( $result->allowed_domains )
			) ? count( $result->allowed_domains ) : 0;
		$result->num_allowed_domains = $num_allowed_domains;
		$properties_to_unset         = array(
			'allowed_domains',
			'hmac_key',
			'crypto_key',
			'data',
			'owner_name',
			'email',
			'company_name',
			'txn_id',
		);

		foreach ( $properties_to_unset as $property ) {
			unset( $result->$property );
		}
	}

	/**
	 * Prepare error response
	 *
	 * @param string $code
	 * @param string $message
	 * @param array $data
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function prepare_error_response( $code, $message, $data = array() ) {
		return array(
			'code'    => $code,
			'message' => $message,
			'data'    => $data,
		);
	}

	/**
	 * Normalize allowed domains
	 *
	 * @param array $license_data - by reference
	 * @return void
	 * @since 1.0.0
	 */
	protected function normalize_allowed_domains( &$license_data ) {

		if ( isset( $license_data['allowed_domains'] ) && ! is_array( $license_data['allowed_domains'] ) ) {
			$license_data['allowed_domains'] = array( $license_data['allowed_domains'] );
		}
	}

	/**
	 * Extract domain from license data
	 *
	 * @param array $license_data
	 * @return string|false The first domain found or false if not found
	 * @since 1.0.0
	 */
	protected function extract_domain_from_license_data( $license_data ) {

		if (
			isset( $license_data['allowed_domains'] ) &&
			is_array( $license_data['allowed_domains'] ) &&
			1 === count( $license_data['allowed_domains'] )
		) {
			return $license_data['allowed_domains'][0];
		}

		return false;
	}

	/**
	 * Is valid license for state transition
	 *
	 * @param object $license
	 * @param string $request_slug
	 * @param string $domain
	 * @return boolean True if valid, false otherwise
	 * @since 1.0.0
	 */
	protected function is_valid_license_for_state_transition( $license, $request_slug, $domain ) {
		return (
			is_object( $license ) &&
			$domain &&
			isset( $license->package_slug ) &&
			$request_slug === $license->package_slug
		);
	}

	/**
	 * Handle license activation
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array|null The result or null if not found
	 * @since 1.0.0
	 */
	protected function handle_license_activation( $license, $domain ) {

		if ( in_array( $license->status, array( 'expired', 'blocked', 'on-hold' ), true ) ) {
			$this->http_response_code = 403;

			return $this->prepare_illegal_status_response( $license );
		} elseif ( 'activated' === $license->status && in_array( $domain, $license->allowed_domains, true ) ) {
			$this->http_response_code = 409;

			return $this->process_already_activated( $license, $domain );
		} elseif (
			( count( $license->allowed_domains ) + 1 ) > abs( intval( $license->max_allowed_domains ) )
		) {
			$this->http_response_code = 422;

			return $this->prepare_max_domains_response( $license );
		}

		return $this->process_license_activation( $license, $domain );
	}

	/**
	 * Prepare illegal status response
	 *
	 * @param object $license
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function prepare_illegal_status_response( $license ) {
		$response = array(
			'code'    => 'illegal_license_status',
			'message' => __( 'The license cannot be activated due to its current status.', 'updatepulse-server' ),
			'data'    => array(
				'status' => $license->status,
			),
		);

		if ( 'expired' === $license->status ) {
			$response['data']['date_expiry'] = $license->date_expiry;
		}

		return $response;
	}

	/**
	 * Prepare max domains response
	 *
	 * @param object $license
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function prepare_max_domains_response( $license ) {
		return array(
			'code'    => 'max_domains_reached',
			'message' => __( 'The license has reached the maximum allowed activations for domains.', 'updatepulse-server' ),
			'data'    => array(
				'max_allowed_domains' => $license->max_allowed_domains,
			),
		);
	}

	/**
	 * Process already activated license
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function process_already_activated( $license, $domain ) {
		$data = isset( $license->data ) ? $license->data : array();
		$op   = 'read';

		if ( ! isset( $data['next_deactivate'] ) || time() > $data['next_deactivate'] ) {
			/**
			 * Filter the timestamp for the next allowed deactivation after activation.
			 *
			 * @param int    $timestamp The timestamp for the next allowed deactivation.
			 * @param object $license   The license object.
			 * @since 1.0.0
			 */
			$data['next_deactivate'] = apply_filters( 'upserv_activate_license_next_deactivate', time(), $license );
			$op                      = 'edit';
		}

		$payload = array(
			'license_key'     => $license->license_key,
			'status'          => 'activated',
			'allowed_domains' => array_unique( array_merge( array( $domain ), $license->allowed_domains ) ),
			'data'            => $data,
		);

		try {
			if ( 'edit' === $op ) {
				$result = $this->license_server->edit_license(
					/**
					 * Filter the payload for license activation.
					 *
					 * @param array $payload The license activation payload.
					 * @since 1.0.0
					 */
					apply_filters( 'upserv_activate_license_payload', $payload )
				);
			} else {
				$result = $this->license_server->read_license( $payload );
			}
		} catch ( Exception $e ) {
			return array( $e->getMessage() );
		}

		if ( is_object( $result ) ) {
			$result->license_signature = $this->license_server->generate_license_signature( $license, $domain );
			$result->next_deactivate   = $data['next_deactivate'];

			$this->sanitize_license_result( $result );
		} else {
			$result = null;
		}

		return array(
			'code'    => 'license_already_activated',
			'message' => __( 'The license is already activated for the specified domain.', 'updatepulse-server' ),
			'data'    => array(
				'domain'  => $domain,
				'license' => $result,
			),
		);
	}

	/**
	 * Process license activation
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array|null The result or null if not found
	 * @since 1.0.0
	 */
	protected function process_license_activation( $license, $domain ) {
		$data = isset( $license->data ) ? $license->data : array();

		if ( ! isset( $data['next_deactivate'] ) || time() > $data['next_deactivate'] ) {
			/**
			 * Filter the timestamp for the next allowed deactivation after activation.
			 *
			 * @param int    $timestamp The timestamp for the next allowed deactivation.
			 * @param object $license   The license object.
			 * @since 1.0.0
			 */
			$data['next_deactivate'] = apply_filters( 'upserv_activate_license_next_deactivate', time(), $license );
		}

		$payload = array(
			'license_key'     => $license->license_key,
			'status'          => 'activated',
			'allowed_domains' => array_unique( array_merge( array( $domain ), $license->allowed_domains ) ),
			'data'            => $data,
		);

		try {
			$result = $this->license_server->edit_license(
				/**
				 * Filter the payload for license activation.
				 *
				 * @param array $payload The license activation payload.
				 * @since 1.0.0
				 */
				apply_filters( 'upserv_activate_license_payload', $payload )
			);
		} catch ( Exception $e ) {
			return array( $e->getMessage() );
		}

		if ( is_object( $result ) ) {
			$result->license_signature = $this->license_server->generate_license_signature( $license, $domain );
			$raw_result                = clone $result;
			$result->next_deactivate   = $data['next_deactivate'];

			$this->sanitize_license_result( $result );

			return array(
				'result'     => $result,
				'raw_result' => $raw_result,
			);
		}

		return null;
	}

	/**
	 * Handle license deactivation
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array|null The result or null if not found
	 * @since 1.0.0
	 */
	protected function handle_license_deactivation( $license, $domain ) {

		if ( in_array( $license->status, array( 'expired', 'blocked', 'on-hold' ), true ) ) {
			$this->http_response_code = 403;

			return $this->prepare_illegal_status_response( $license );
		} elseif ( 'deactivated' === $license->status || ! in_array( $domain, $license->allowed_domains, true ) ) {
			$this->http_response_code = 409;

			return $this->process_already_deactivated( $license, $domain );
		} elseif (
			isset( $license->data, $license->data['next_deactivate'] ) &&
			$license->data['next_deactivate'] > time()
		) {
			$this->http_response_code = 403;

			return $this->prepare_too_early_deactivation_response( $license );
		}

		return $this->process_license_deactivation( $license, $domain );
	}

	/**
	 * Process already deactivated
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array The response
	 * @since 1.0.11
	 */
	protected function process_already_deactivated( $license, $domain ) {
		$payload = array( 'license_key' => $license->license_key );

		try {
			$result = $this->license_server->read_license( $payload );
		} catch ( Exception $e ) {
			return array( $e->getMessage() );
		}

		if ( is_object( $result ) ) {
			$this->sanitize_license_result( $result );
		} else {
			$result = null;
		}

		return array(
			'code'    => 'license_already_deactivated',
			'message' => __( 'The license is already deactivated for the specified domain.', 'updatepulse-server' ),
			'data'    => array(
				'domain'  => $domain,
				'license' => $result,
			),
		);
	}

	/**
	 * Prepare too early deactivation response
	 *
	 * @param object $license
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function prepare_too_early_deactivation_response( $license ) {
		return array(
			'code'    => 'too_early_deactivation',
			'message' => __( 'The license cannot be deactivated before the specified date.', 'updatepulse-server' ),
			'data'    => array(
				'next_deactivate' => $license->data['next_deactivate'],
			),
		);
	}

	/**
	 * Process license deactivation
	 *
	 * @param object $license
	 * @param string $domain
	 * @return array|null The result or null if not found
	 * @since 1.0.0
	 */
	protected function process_license_deactivation( $license, $domain ) {
		$data = isset( $license->data ) ? $license->data : array();
		/**
		 * Filter the timestamp for the next allowed deactivation.
		 *
		 * @param int    $timestamp The timestamp for the next allowed deactivation.
		 * @param object $license   The license object.
		 * @since 1.0.0
		 */
		$data['next_deactivate'] = apply_filters(
			'upserv_deactivate_license_next_deactivate',
			(bool) ( constant( 'WP_DEBUG' ) ) ? time() + ( MINUTE_IN_SECONDS / 4 ) : time() + MONTH_IN_SECONDS,
			$license
		);

		$allowed_domains = array_diff( $license->allowed_domains, array( $domain ) );
		$payload         = array(
			'license_key'     => $license->license_key,
			'status'          => empty( $allowed_domains ) ? 'deactivated' : $license->status,
			'allowed_domains' => $allowed_domains,
			'data'            => $data,
		);

		try {
			$result = $this->license_server->edit_license(
				/**
				 * Filter the payload for license deactivation.
				 *
				 * @param array $payload The license deactivation payload.
				 * @since 1.0.0
				 */
				apply_filters( 'upserv_deactivate_license_payload', $payload )
			);
		} catch ( Exception $e ) {
			return array( $e->getMessage() );
		}

		if ( is_object( $result ) ) {
			$result->license_signature = $this->license_server->generate_license_signature( $license, $domain );
			$raw_result                = clone $result;
			$result->next_deactivate   = $data['next_deactivate'];

			$this->sanitize_license_result( $result );

			return array(
				'result'     => $result,
				'raw_result' => $raw_result,
			);
		}

		return null;
	}

	/**
	 * Handle invalid license
	 *
	 * @param array $license
	 * @param array $license_data
	 * @return array The response
	 * @since 1.0.0
	 */
	protected function handle_invalid_license( $license, $license_data ) {

		if ( is_array( $license ) && isset( $license['license_not_found'] ) ) {

			$this->http_response_code = 400;

			return $this->prepare_error_response(
				'invalid_license_key',
				__( 'The provided license key is invalid.', 'updatepulse-server' ),
				array( 'license_key' => $license_data['license_key'] ? $license_data['license_key'] : false )
			);

		}

		if ( ! is_array( $license ) || empty( $license ) || ! isset( $license[0] ) ) {
			$license = array( __( 'Unknown error.', 'updatepulse-server' ) );
		}

		$this->http_response_code = 500;

		return $this->prepare_error_response(
			'unexpected_error',
			__( 'This is an unexpected error. Please contact support.', 'updatepulse-server' ),
			array( 'error' => $license )
		);
	}

	/**
	 * Authorize private API access
	 *
	 * @param string $action
	 * @param array  $payload
	 * @return boolean True if authorized, false otherwise
	 * @since 1.0.0
	 */
	protected function authorize_private( $action, $payload ) {
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

			if ( 'browse' === $action || 'add' === $action ) {
				$is_auth = $is_auth && (
					in_array( 'all', $this->api_access, true ) ||
					in_array( $action, $this->api_access, true )
				);
			} elseif ( isset( $payload['license_key'] ) ) {
				$license = $this->license_server->read_license( $payload );
				$is_auth = $is_auth && (
					! is_object( $license ) ||
					(
						is_object( $license ) &&
						empty( get_object_vars( $license ) )
					) ||
					(
						is_object( $license ) &&
						(
							in_array( 'all', $this->api_access, true ) ||
							in_array( $action, $this->api_access, true )
						) &&
						(
							(
								isset( $license->data['api_owner'] ) &&
								$this->api_key_id === $license->data['api_owner']
							) ||
							in_array( 'other', $this->api_access, true )
						)
					)
				);
			}
		}

		return $is_auth;
	}

	/**
	 * Is API public
	 *
	 * @param string $method
	 * @return boolean True if public, false otherwise
	 * @since 1.0.0
	 */
	protected function is_api_public( $method ) {
		/**
		 * Filter the list of public License API actions.
		 *
		 * @param array $public_api_actions List of public License API actions.
		 * @since 1.0.0
		 */
		$public_api    = apply_filters(
			'upserv_license_public_api_actions',
			array(
				'check',
				'activate',
				'deactivate',
			)
		);
		$is_api_public = in_array( $method, $public_api, true );

		return $is_api_public;
	}

	/**
	 * Handle API request
	 *
	 * @since 1.0.0
	 */
	protected function handle_api_request() {
		global $wp;

		$method = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : false;

		if (
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
			} elseif ( 'browse' === $wp->query_vars['action'] ) {

				if ( isset( $wp->query_vars['browse_query'] ) ) {
					$payload = $wp->query_vars['browse_query'];
				} else {
					$malformed_request = true;
				}
			} else {
				$payload = $wp->query_vars;

				unset( $payload['id'] );
			}

			if ( ! $malformed_request ) {
				$this->init_server();

				/**
				 * Filter whether the License API request is authorized.
				 *
				 * @param bool   $authorized Whether the License API request is authorized.
				 * @param string $method     The method of the request.
				 * @param array  $payload    The payload of the request.
				 * @since 1.0.0
				 */
				$authorized = apply_filters(
					'upserv_license_api_request_authorized',
					(
						$this->is_api_public( $method ) ||
						(
							$this->authorize_ip() &&
							$this->authorize_private( $method, $payload )
						)
					),
					$method,
					$payload
				);

				if ( $authorized ) {
					/**
					 * Fired before the License API request is processed.
					 *
					 * @param string $method  The License API action.
					 * @param array  $payload The payload of the request.
					 * @since 1.0.0
					 */
					do_action( 'upserv_license_api_request', $method, $payload );

					if ( method_exists( $this, $method ) ) {
						$response = $this->$method( $payload );

						if ( is_object( $response ) && ! empty( get_object_vars( $response ) ) ) {
							$response->time_elapsed = Utils::get_time_elapsed();
						}
					} else {
						$this->http_response_code = 400;
						$response                 = array(
							'code'    => 'action_not_found',
							'message' => __( 'License API action not found.', 'updatepulse-server' ),
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
	 * Authorize IP
	 *
	 * @return boolean True if authorized, false otherwise
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
	 * Init server
	 *
	 * @since 1.0.0
	 */
	protected function init_server() {
		/**
		 * Filter the License Server instance.
		 *
		 * @param License_Server $license_server The license server instance.
		 * @since 1.0.0
		 */
		$this->license_server = apply_filters( 'upserv_license_server', new License_Server() );
	}
}
