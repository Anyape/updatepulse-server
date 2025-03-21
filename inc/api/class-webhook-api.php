<?php

namespace Anyape\UpdatePulse\Server\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use DateTimeZone;
use DateTime;
use WP_Error;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Scheduler\Scheduler;
use Anyape\Utils\Utils;

/**
 * Webhook API class
 *
 * @since 1.0.0
 */
class Webhook_API {

	/**
	 * Is doing API request
	 *
	 * @var bool|null
	 */
	protected static $doing_api_request = null;

	/**
	 * Instance
	 *
	 * @var Webhook_API|null
	 */
	protected static $instance;

	/**
	 * Webhooks configuration
	 *
	 * @var array
	 */
	protected $webhooks;

	/**
	 * HTTP response code
	 *
	 * @var int
	 */
	protected $http_response_code = 200;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param boolean $init_hooks Whether to initialize hooks
	 */
	public function __construct( $init_hooks = false ) {
		$this->webhooks = upserv_get_option( 'api/webhooks', array() );
		$vcs_configs    = upserv_get_option( 'vcs', array() );
		$use_webhooks   = false;

		if ( ! empty( $vcs_configs ) ) {

			foreach ( $vcs_configs as $vcs_c ) {

				if ( isset( $vcs_c['use_webhooks'] ) && $vcs_c['use_webhooks'] ) {
					$use_webhooks = true;

					break;
				}
			}
		}

		if ( $init_hooks && $use_webhooks ) {
			add_action( 'init', array( $this, 'add_endpoints' ), 10, 0 );
			add_action( 'parse_request', array( $this, 'parse_request' ), -99, 0 );
			add_action( 'upserv_webhook_invalid_request', array( $this, 'upserv_webhook_invalid_request' ), 10, 0 );

			add_filter( 'query_vars', array( $this, 'query_vars' ), -99, 1 );
			add_filter( 'upserv_webhook_process_request', array( $this, 'upserv_webhook_process_request' ), 10, 6 );
		}

		add_action( 'upserv_webhook', array( $this, 'fire_webhook' ), 10, 4 );
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Add API endpoints
	 *
	 * Register the rewrite rules for the Webhook API endpoints.
	 */
	public function add_endpoints() {
		add_rewrite_rule( '^updatepulse-server-webhook$', 'index.php?__upserv_webhook=1&', 'top' );
		add_rewrite_rule(
			'^updatepulse-server-webhook/(plugin|theme|generic)/(.+)?$',
			'index.php?type=$matches[1]&slug=$matches[2]&__upserv_webhook=1&',
			'top'
		);
	}

	/**
	 * Parse API requests
	 *
	 * Handle incoming API requests to the Webhook API endpoints.
	 */
	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__upserv_webhook'] ) ) {
			$this->handle_api_request();

			exit;
		}
	}

	/**
	 * Register query variables
	 *
	 * Add custom query variables used by the Webhook API.
	 *
	 * @param array $query_vars Existing query variables.
	 * @return array Modified query variables.
	 */
	public function query_vars( $query_vars ) {
		$query_vars = array_merge(
			$query_vars,
			array(
				'__upserv_webhook',
				'slug',
				'type',
			)
		);

		return $query_vars;
	}

	/**
	 * Handle invalid webhook requests
	 *
	 * Display error page for unauthorized webhook requests.
	 */
	public function upserv_webhook_invalid_request() {
		$protocol = empty( $_SERVER['SERVER_PROTOCOL'] ) ? 'HTTP/1.1' : sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) );

		header( $protocol . ' 401 Unauthorized' );

		upserv_get_template(
			'error-page.php',
			array(
				'title'   => __( '401 Unauthorized', 'updatepulse-server' ),
				'heading' => __( '401 Unauthorized', 'updatepulse-server' ),
				'message' => __( 'Invalid signature', 'updatepulse-server' ),
			)
		);

		exit( -1 );
	}

	/**
	 * Process webhook requests
	 *
	 * Determine whether to process webhook requests based on branch matching.
	 * If no branch is specified, the request will be processed to account for events
	 * registered to the webhook that do not have a branch associated with them.
	 *
	 * @param bool $process Current process status.
	 * @param array $payload Request payload.
	 * @param string $slug Package slug.
	 * @param string $type Package type.
	 * @param bool $package_exists Whether package already exists.
	 * @param array $vcs_config Version control system configuration.
	 * @return bool Whether to process the webhook request.
	 */
	public function upserv_webhook_process_request( $process, $payload, $slug, $type, $package_exists, $vcs_config ) {
		$branch = $this->get_payload_vcs_branch( $payload );

		return $process && ( $branch === $vcs_config['branch'] || ! $branch );
	}

	// Misc. -------------------------------------------------------

	/**
	 * Check if currently processing an API request
	 *
	 * Determine whether the current request is a Webhook API request.
	 *
	 * @return bool Whether the current request is a Webhook API request.
	 */
	public static function is_doing_api_request() {

		if ( null === self::$doing_api_request ) {
			self::$doing_api_request = Utils::is_url_subpath_match( '/^updatepulse-server-webhook$/' );
		}

		return self::$doing_api_request;
	}

	/**
	 * Get Webhook API instance
	 *
	 * Retrieve or create the Webhook API singleton instance.
	 *
	 * @return Webhook_API The Webhook API instance.
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Schedule webhook
	 *
	 * Schedule a webhook to be fired based on an event.
	 *
	 * @param array $payload Webhook payload data.
	 * @param string $event_type Event type identifier.
	 * @param bool $instant Whether to fire webhook immediately.
	 * @return void|WP_Error WP_Error on failure.
	 */
	public function schedule_webhook( $payload, $event_type, $instant = false ) {

		if ( empty( $this->webhooks ) ) {
			return;
		}

		if ( ! isset( $payload['event'], $payload['content'] ) ) {
			return new WP_Error(
				__METHOD__,
				__( 'The webhook payload must contain an event string and a content.', 'updatepulse-server' )
			);
		}

		$payload['origin']    = get_bloginfo( 'url' );
		$payload['timestamp'] = time();

		foreach ( $this->webhooks as $info ) {
			$fire = false;

			if (
				isset( $info['secret'], $info['events'] ) &&
				! empty( $info['events'] ) &&
				is_array( $info['events'] )
			) {

				if ( in_array( $event_type, $info['events'], true ) ) {
					$fire = true;
				} else {

					foreach ( $info['events'] as $event ) {

						if ( $event === $payload['event'] && 0 === strpos( $event, $event_type ) ) {
							$fire = true;

							break;
						}
					}
				}
			}

			/**
			 * Filter whether to fire the webhook event.
			 *
			 * @param bool   $fire           Whether to fire the event.
			 * @param array  $payload        The payload of the event.
			 * @param string $url            The target url of the event.
			 * @param array  $webhook_setting The settings of the webhook.
			 * @return bool
			 */
			if ( apply_filters( 'upserv_webhook_fire', $fire, $payload, $info['url'], $info ) ) {
				$body   = wp_json_encode( $payload, Utils::JSON_OPTIONS );
				$hook   = 'upserv_webhook';
				$params = array( $info['url'], $info['secret'], $body, current_action() );

				if ( ! Scheduler::get_instance()->has_scheduled_action( $hook, $params ) ) {
					/**
					 * Filter whether to send the webhook notification immediately.
					 *
					 * @param bool   $instant    Whether to send the notification immediately.
					 * @param array  $payload    The payload of the event.
					 * @param string $event_type The type of event.
					 * @return bool
					 */
					$instant = apply_filters(
						'upserv_schedule_webhook_is_instant',
						$instant,
						$event_type,
						$params
					);

					if ( $instant ) {
						$this->fire_webhook( ...$params );

						continue;
					}

					Scheduler::get_instance()->schedule_single_action( time(), $hook, $params );
				}
			}
		}
	}

	/**
	 * Fire webhook
	 *
	 * Send an HTTP request to the webhook endpoint.
	 *
	 * @param string $url Webhook endpoint URL.
	 * @param string $secret Secret key for signature.
	 * @param string $body Request body.
	 * @param string $action Current action.
	 * @return array|WP_Error HTTP response or WP_Error on failure.
	 */
	public function fire_webhook( $url, $secret, $body, $action ) {
		return wp_remote_post(
			$url,
			array(
				'method'   => 'POST',
				'blocking' => false,
				'headers'  => array(
					'X-UpdatePulse-Action'        => $action,
					'X-UpdatePulse-Signature-256' => 'sha256=' . hash_hmac( 'sha256', $body, $secret ),
				),
				'body'     => $body,
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Handle remote test
	 *
	 * Process and respond to webhook test requests.
	 */
	protected function handle_remote_test() {

		if ( empty( $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] ) ) {
			wp_send_json( false, 403, Utils::JSON_OPTIONS );
		}

		$sign       = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] ) );
		$sign_parts = explode( '=', $sign );
		$sign       = 2 === count( $sign_parts ) ? end( $sign_parts ) : false;
		$algo       = ( $sign ) ? reset( $sign_parts ) : false;
		$payload    = ( $sign ) ? filter_input_array(
			INPUT_POST,
			array(
				'test'   => FILTER_VALIDATE_INT,
				'source' => FILTER_SANITIZE_URL,
			)
		) : false;
		$valid      = false;

		if (
			$payload &&
			1 === intval( $payload['test'] ) &&
			! empty( $this->webhooks )
		) {
			$source   = $payload['source'];
			$webhooks = array_filter(
				$this->webhooks,
				function ( $key ) use ( $source ) {
					return 0 === strpos(
						str_replace( '|', '/', base64_decode( $key ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
						$source
					);
				},
				ARRAY_FILTER_USE_KEY
			);

			if ( ! empty( $webhooks ) ) {

				foreach ( $webhooks as $webhook ) {
					$secret = $webhook['secret'];
					$body   = wp_json_encode( $payload, JSON_NUMERIC_CHECK );
					$valid  = hash_equals( hash_hmac( $algo, $body, $secret ), $sign );

					if ( $valid ) {
						break;
					}
				}
			}
		}

		wp_send_json( $valid, $valid ? 200 : 403, Utils::JSON_OPTIONS );
	}

	/**
	 * Handle API request
	 *
	 * Process webhook API requests and return appropriate responses.
	 */
	protected function handle_api_request() {
		global $wp;

		if ( isset( $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] ) ) {
			$this->handle_remote_test();
		}

		$response       = array();
		$payload        = $this->get_payload();
		$url            = $this->get_payload_vcs_url( $payload );
		$branch         = $this->get_payload_vcs_branch( $payload );
		$vcs_configs    = upserv_get_option( 'vcs', array() );
		$vcs_key        = hash( 'sha256', trailingslashit( $url ) . '|' . $branch );
		$vcs_config     = isset( $vcs_configs[ $vcs_key ] ) ? $vcs_configs[ $vcs_key ] : false;
		$vcs_candidates = $vcs_config ? array( $vcs_key => $vcs_config ) : array();

		if ( empty( $vcs_candidates ) ) {

			foreach ( $vcs_configs as $config ) {

				if ( 0 === strpos( $config['url'], trailingslashit( $url ) ) ) {
					$vcs_candidates[] = $config;
					$vcs_candidates[] = $config;
				}
			}
		}

		if ( 1 === count( $vcs_candidates ) ) {
			$vcs_config = reset( $vcs_candidates );
		}

		/**
		 * Fired before handling a webhook request; fired whether it will be processed or not.
		 *
		 * @param array $config The configuration used to handle webhook requests.
		 */
		do_action( 'upserv_webhook_before_handling_request', $vcs_config );

		if ( $vcs_config && $this->validate_request( $vcs_config ) ) {
			$slug           = isset( $wp->query_vars['slug'] ) ?
				trim( rawurldecode( $wp->query_vars['slug'] ) ) :
				null;
			$type           = isset( $wp->query_vars['type'] ) ?
				trim( rawurldecode( $wp->query_vars['type'] ) ) :
				null;
			$delay          = $vcs_config ? $vcs_config['check_delay'] : 0;
			$dir            = Data_Manager::get_data_dir( 'packages' );
			$package_exists = null;
			/**
			 * Filter whether the package exists on the file system before processing the Webhook.
			 *
			 * @param bool|null $package_exists Whether the package exists on the file system; return `null` to leave the decision to the default behavior.
			 * @param array     $payload        The payload of the request.
			 * @param string    $slug           The slug of the package.
			 * @param string    $type           The type of the package.
			 * @param array     $vcs_config     The configuration used to handle webhook requests.
			 * @return bool|null
			 */
			$package_exists = apply_filters(
				'upserv_webhook_package_exists',
				$package_exists,
				$payload,
				$slug,
				$type,
				$vcs_config
			);

			if ( null === $package_exists && is_dir( $dir ) ) {
				$package_path   = trailingslashit( $dir ) . $slug . '.zip';
				$package_exists = file_exists( $package_path );
			}

			/**
			 * Filter whether to process the Webhook request.
			 *
			 * @param bool   $process        Whether to process the Webhook request.
			 * @param array  $payload        The payload of the request.
			 * @param string $slug           The slug of the package.
			 * @param string $type           The type of the package.
			 * @param bool   $package_exists Whether the package exists on the file system.
			 * @param array  $vcs_config     The configuration used to handle webhook requests.
			 * @return bool
			 */
			$process = apply_filters(
				'upserv_webhook_process_request',
				true,
				$payload,
				$slug,
				$type,
				$package_exists,
				$vcs_config
			);

			if ( $process ) {
				/**
				 * Fired before processing a webhook request.
				 *
				 * @param array  $payload        The data sent by the Version Control System.
				 * @param string $slug           The slug of the package triggering the webhook.
				 * @param string $type           The type of the package triggering the webhook.
				 * @param bool   $package_exists Whether the package exists on the file system.
				 * @param array  $vcs_config     The configuration used to handle webhook requests.
				 */
				do_action(
					'upserv_webhook_before_processing_request',
					$payload,
					$slug,
					$type,
					$package_exists,
					$vcs_config
				);

				$hook = 'upserv_check_remote_' . $slug;

				if ( $package_exists ) {
					$params           = array( $slug, $type, false );
					$result           = true;
					$scheduled_action = Scheduler::get_instance()->next_scheduled_action( $hook, $params );
					$timestamp        = is_int( $scheduled_action ) ? $scheduled_action : false;

					if ( ! is_int( $scheduled_action ) ) {

						if ( ! $scheduled_action ) {
							Scheduler::get_instance()->unschedule_all_actions( $hook );
							/**
							 * Fired after a remote check schedule event has been unscheduled for a package.
							 *
							 * @param string $package_slug The slug of the package for which a remote check event has been unscheduled.
							 * @param string $scheduled_hook The remote check event hook that has been unscheduled.
							 */
							do_action( 'upserv_cleared_check_remote_schedule', $slug, $hook );
						}

						/**
						 * Filter the delay time for remote package checks.
						 *
						 * @param int    $delay The delay time in minutes.
						 * @param string $slug  The slug of the package.
						 * @return int
						 */
						$delay     = apply_filters( 'upserv_check_remote_delay', $delay, $slug );
						$timestamp = ( $delay ) ?
							time() + ( abs( intval( $delay ) ) * MINUTE_IN_SECONDS ) :
							time();
						$result    = Scheduler::get_instance()->schedule_single_action( $timestamp, $hook, $params );

						/**
						 * Fired after scheduling a remote check event.
						 *
						 * @param bool   $result    Whether the event was successfully scheduled.
						 * @param string $slug      The slug of the package triggering the webhook.
						 * @param int    $timestamp The timestamp when the event is scheduled to run.
						 * @param bool   $is_cron   Whether the event is a cron job.
						 * @param string $hook      The hook name for the scheduled event.
						 * @param array  $params    The parameters passed to the scheduled event.
						 */
						do_action(
							'upserv_scheduled_check_remote_event',
							$result,
							$slug,
							$timestamp,
							false,
							$hook,
							$params
						);
					}

					if ( $result ) {
						$date = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );

						$date->setTimestamp( $timestamp );

						$response['message'] = sprintf(
						/* translators: %1$s: package ID, %2$s: scheduled date and time */
							__( 'Package %1$s has been scheduled for download: %2$s.', 'updatepulse-server' ),
							sanitize_title( $slug ),
							$date->format( 'Y-m-d H:i:s' ) . ' (' . wp_timezone_string() . ')'
						);
					} else {
						$this->http_response_code = 400;
						$response['code']         = 'schedule_failed';
						$response['message']      = sprintf(
						/* translators: %s: package ID */
							__( 'Failed to sechedule download for package %s.', 'updatepulse-server' ),
							sanitize_title( $slug )
						);
					}
				} else {
					Scheduler::get_instance()->unschedule_all_actions( $hook );
					/**
					 * Fired after a remote check schedule event has been unscheduled for a package.
					 *
					 * @param string $package_slug The slug of the package for which a remote check event has been unscheduled.
					 * @param string $scheduled_hook The remote check event hook that has been unscheduled.
					 */
					do_action( 'upserv_cleared_check_remote_schedule', $slug, $hook );

					$result = upserv_download_remote_package( $slug, $type );

					if ( $result ) {
						$response['message'] = sprintf(
						/* translators: %s: package ID */
							__( 'Package %s downloaded.', 'updatepulse-server' ),
							sanitize_title( $slug )
						);
					} else {
						$this->http_response_code = 400;
						$response['code']         = 'download_failed';
						$response['message']      = sprintf(
						/* translators: %s: package ID */
							__( 'Failed to download package %s.', 'updatepulse-server' ),
							sanitize_title( $slug )
						);
					}
				}

				/**
				 * Fired after processing a webhook request.
				 *
				 * @param array  $payload        The data sent by the Version Control System.
				 * @param string $slug           The slug of the package triggering the webhook.
				 * @param string $type           The type of the package triggering the webhook.
				 * @param bool   $package_exists Whether the package exists on the file system.
				 * @param array  $vcs_config     The configuration used to handle webhook requests.
				 */
				do_action(
					'upserv_webhook_after_processing_request',
					$payload,
					$slug,
					$type,
					$package_exists,
					$vcs_config
				);
			}
		} elseif ( empty( $vcs_candidates ) ) {
			$this->http_response_code = 403;
			$response                 = array(
				'code'    => 'invalid_request',
				'message' => __( 'Invalid request', 'updatepulse-server' ),
			);
		} elseif ( 1 < count( $vcs_candidates ) ) {
			$this->http_response_code = 409;
			$response                 = array(
				'code'    => 'conflict',
				'message' => __( 'Multiple candidate VCS configurations found ; the event has not be processed. Please limit the events sent to the webhook to events specifying the branch in their payload (such as push), or update your UpdatePulse Server VCS configuration to avoid branch conflicts.', 'updatepulse-server' ),
				'details' => array(
					'vcs_candidates' => array_map(
						function ( $config ) {
							return array(
								'url'    => $config['url'],
								'branch' => $config['branch'],
							);
						},
						$vcs_candidates
					),
				),
			);
		} else {
			/**
			 * Fired when a webhook request is invalid.
			 *
			 * @param array $config The configuration used to handle webhook requests.
			 */
			do_action( 'upserv_webhook_invalid_request', $vcs_config );
		}

		$response['time_elapsed'] = Utils::get_time_elapsed();

		/**
		 * Filter the response data to send to the Version Control System after handling the webhook request.
		 *
		 * @param array $response           The response data to send to the Version Control System.
		 * @param int   $http_response_code The HTTP response code.
		 * @param array $vcs_config         The configuration used to handle webhook requests.
		 * @return array
		 */
		$response = apply_filters( 'upserv_webhook_response', $response, $this->http_response_code, $vcs_config );

		/**
		 * Fired after handling a webhook request; fired whether it was processed or not.
		 *
		 * @param array $config   The configuration used to handle webhook requests.
		 * @param array $response The response data that will be sent to the Version Control System.
		 */
		do_action( 'upserv_webhook_after_handling_request', $vcs_config, $response );
		wp_send_json( $response, $this->http_response_code, Utils::JSON_OPTIONS );
	}

	/**
	 * Validate webhook request
	 *
	 * Verify webhook request signature against stored secrets.
	 *
	 * @param array $vcs_config Version control system configuration.
	 * @return bool Whether the request signature is valid.
	 */
	protected function validate_request( $vcs_config ) {
		$valid  = false;
		$sign   = false;
		$secret = isset( $vcs_config['webhook_secret'] ) ? $vcs_config['webhook_secret'] : false;

		/**
		 * Filter the webhook secret used for request validation.
		 *
		 * @param string|bool $secret     The secret key for webhook validation.
		 * @param array       $vcs_config The configuration used to handle webhook requests.
		 * @return string|bool
		 */
		$secret = apply_filters( 'upserv_webhook_secret', $secret, $vcs_config );

		if ( ! $secret ) {
			/**
			 * Filter whether the webhook request is valid after validation.
			 *
			 * @param bool        $valid      Whether the request signature is valid.
			 * @param string|bool $sign       The signature from the request.
			 * @param string      $secret     The secret key for webhook validation.
			 * @param array       $vcs_config The configuration used to handle webhook requests.
			 * @return bool
			 */
			return apply_filters( 'upserv_webhook_validate_request', $valid, $sign, '', $vcs_config );
		}

		if ( ! empty( $_SERVER['HTTP_X_GITLAB_TOKEN'] ) ) {
			$valid = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_GITLAB_TOKEN'] ) ) === $secret;
		} else {

			if ( ! empty( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ) {
				$sign = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ) {
				$sign = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) );
			}

			/**
			 * Filter the signature from the webhook request.
			 *
			 * @param string|bool $sign       The signature from the request.
			 * @param string      $secret     The secret key for webhook validation.
			 * @param array       $vcs_config The configuration used to handle webhook requests.
			 * @return string|bool
			 */
			$sign = apply_filters( 'upserv_webhook_signature', $sign, $secret, $vcs_config );

			if ( $sign ) {
				$sign_parts = explode( '=', $sign );
				$sign       = 2 === count( $sign_parts ) ? end( $sign_parts ) : false;
				$algo       = ( $sign ) ? reset( $sign_parts ) : false;
				$payload    = ( $sign ) ? @file_get_contents( 'php://input' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
				$valid      = $sign && hash_equals( hash_hmac( $algo, $payload, $secret ), $sign );
			}
		}

		/**
		 * Filter whether the webhook request is valid after validation.
		 *
		 * @param bool        $valid      Whether the request signature is valid.
		 * @param string|bool $sign       The signature from the request.
		 * @param string      $secret     The secret key for webhook validation.
		 * @param array       $vcs_config The configuration used to handle webhook requests.
		 * @return bool
		 */
		return apply_filters( 'upserv_webhook_validate_request', $valid, $sign, $secret, $vcs_config );
	}

	/**
	 * Get webhook payload
	 *
	 * Extract and decode the payload from the webhook request.
	 *
	 * @return array Decoded webhook payload.
	 */
	protected function get_payload() {
		$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		$decoded = json_decode( $payload, true );

		if ( ! $decoded ) {
			parse_str( $payload, $payload );

			if ( is_array( $payload ) && isset( $payload['payload'] ) ) {
				$decoded = json_decode( $payload['payload'], true );
			} elseif ( is_string( $payload ) ) {
				$decoded = json_decode( $payload, true );
			}
		}

		return ! is_array( $decoded ) ? array( 'decoded' => $decoded ) : $decoded;
	}

	/**
	 * Get VCS URL from payload
	 *
	 * Extract the version control system URL from webhook payload.
	 *
	 * @param array $payload Webhook payload.
	 * @return string|false VCS URL or false if not found.
	 */
	protected function get_payload_vcs_url( $payload ) {
		$url = false;

		if ( isset( $payload['repository'], $payload['repository']['html_url'] ) ) {
			$url = $payload['repository']['html_url'];
		} elseif ( isset( $payload['repository'], $payload['repository']['homepage'] ) ) {
			$url = $payload['repository']['homepage'];
		} elseif (
			isset(
				$payload['repository'],
				$payload['repository']['links'],
				$payload['repository']['links']['html'],
				$payload['repository']['links']['html']['href']
			)
		) {
			$url = $payload['repository']['links']['html']['href'];
		}

		/**
		 * Filter the Version Control System URL extracted from the webhook payload.
		 *
		 * @param string|bool $url     The URL of the Version Control System.
		 * @param array       $payload The webhook payload data.
		 * @return string|bool
		 */
		$url        = apply_filters( 'upserv_webhook_vcs_url', $url, $payload );
		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['path'] ) ) {
			return false;
		}

		$path_segments = explode( '/', trim( $parsed_url['path'], '/' ) );

		array_pop( $path_segments );

		$parsed_url['path'] = '/' . implode( '/', $path_segments );
		$url                = $parsed_url['scheme']
			. '://'
			. $parsed_url['host']
			. $parsed_url['path'];

		return trailingslashit( $url );
	}

	/**
	 * Get VCS branch from payload
	 *
	 * Extract the branch information from webhook payload.
	 *
	 * @param array $payload Webhook payload.
	 * @return string|false Branch name or false if not found.
	 */
	protected function get_payload_vcs_branch( $payload ) {

		// Ensure we have an array to work with
		$payload = is_string($payload) ? json_decode($payload, true) : $payload;
		if (!is_array($payload)) {
			return false;
		}
		
		$branch = false;

		if (
			( isset( $payload['object_kind'] ) && 'push' === $payload['object_kind'] ) ||
			(
				! empty( $_SERVER['HTTP_X_GITHUB_EVENT'] ) &&
				'push' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_GITHUB_EVENT'] ) )
			)
		) {
			$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
		} elseif ( isset( $payload['push'], $payload['push']['changes'] ) ) {
			$branch = str_replace(
				'refs/heads/',
				'',
				$payload['push']['changes'][0]['new']['name']
			);
		} elseif ( isset( $payload['ref'] ) ) {
			$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
		} else {
			$branch = $this->find_branch_recursively( $payload );
		}

		return $branch;
	}

	/**
	 * Recursively search for branch references in payload
	 *
	 * Search through nested arrays to find values starting with 'refs/heads/'.
	 *
	 * @param mixed $data Part of the payload to search through.
	 * @return string|false Branch name or false if not found.
	 */
	protected function find_branch_recursively( $data ) {

		if ( is_string( $data ) && 0 === strpos( $data, 'refs/heads/' ) ) {
			return str_replace( 'refs/heads/', '', $data );
		}

		if ( is_array( $data ) ) {

			foreach ( $data as $value ) {
				$result = $this->find_branch_recursively( $value );

				if ( false === $result ) {
					return $result;
				}
			}
		}

		return false;
	}
}
