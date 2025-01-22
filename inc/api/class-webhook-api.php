<?php

namespace Anyape\UpdatePulse\Server\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use DateTimeZone;
use DateTime;
use WP_Error;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;

class Webhook_API {

	protected static $doing_update_api_request = null;
	protected static $instance;

	protected $webhooks;
	protected $http_response_code = 200;

	public function __construct( $init_hooks = false ) {
		$this->webhooks = upserv_get_option( 'api/webhooks', array() );
		$repo_configs   = upserv_get_option( 'remote_repositories', array() );
		$use_webhooks   = false;

		if ( ! empty( $repo_configs ) ) {

			foreach ( $repo_configs as $r_c ) {

				if ( isset( $r_c['use_webhooks'] ) && $r_c['use_webhooks'] ) {
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

	public function add_endpoints() {
		add_rewrite_rule( '^updatepulse-server-webhook$', 'index.php?__upserv_webhook=1&', 'top' );
		add_rewrite_rule(
			'^updatepulse-server-webhook/(plugin|theme|generic)/(.+)?$',
			'index.php?type=$matches[1]&slug=$matches[2]&__upserv_webhook=1&',
			'top'
		);
	}

	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__upserv_webhook'] ) ) {
			$this->handle_api_request();

			exit;
		}
	}

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

	public function upserv_webhook_invalid_request() {

		if ( ! isset( $_SERVER['SERVER_PROTOCOL'] ) || '' === $_SERVER['SERVER_PROTOCOL'] ) {
			$protocol = 'HTTP/1.1';
		} else {
			$protocol = $_SERVER['SERVER_PROTOCOL'];
		}

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

	public function upserv_webhook_process_request( $process, $payload, $slug, $type, $package_exists, $vcs_config ) {
		$payload = json_decode( $payload, true );

		if ( ! $payload ) {
			return false;
		}

		$branch = false;

		if (
			( isset( $payload['object_kind'] ) && 'push' === $payload['object_kind'] ) ||
			( isset( $_SERVER['HTTP_X_GITHUB_EVENT'] ) && 'push' === $_SERVER['HTTP_X_GITHUB_EVENT'] )
		) {
			$branch = str_replace( 'refs/heads/', '', $payload['ref'] );
		} elseif ( isset( $payload['push'], $payload['push']['changes'] ) ) {
			$branch = str_replace(
				'refs/heads/',
				'',
				$payload['push']['changes'][0]['new']['name']
			);
		}

		$process = $branch === $vcs_config['branch'];

		return $process;
	}

	// Misc. -------------------------------------------------------

	public static function is_doing_api_request() {

		if ( null === self::$doing_update_api_request ) {
			self::$doing_update_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], 'updatepulse-server-webhook' ) );
		}

		return self::$doing_update_api_request;
	}

	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

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

			if ( apply_filters( 'upserv_webhook_fire', $fire, $payload, $info['url'], $info ) ) {
				$body   = wp_json_encode(
					$payload,
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK
				);
				$hook   = 'upserv_webhook';
				$params = array( $info['url'], $info['secret'], $body, current_action() );

				if ( ! as_has_scheduled_action( 'upserv_webhook', $params ) ) {
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

					$timestamp = time();

					as_schedule_single_action( $timestamp, $hook, $params );
				}
			}
		}
	}

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

	protected function handle_remote_test() {
		$sign       = $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'];
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

		wp_send_json( $valid, $valid ? 200 : 403 );
	}

	protected function handle_api_request() {
		global $wp;

		if ( isset( $_SERVER['HTTP_X_UPDATEPULSE_SIGNATURE_256'] ) ) {
			$this->handle_remote_test();
		}

		$response = array();
		$payload  = $this->get_payload();
		$slug     = isset( $payload['repository'], $payload['repository']['name'] ) ? $payload['repository']['name'] : false;

		if ( ! $slug ) {
			$full_name = isset( $payload['repository'], $payload['repository']['full_name'] ) ?
				$payload['repository']['full_name'] :
				false;
			$parts     = explode( '/', $full_name );
			$slug      = isset( $parts[1] ) ? $parts[1] : false;
		}

		$url     = $this->get_payload_vcs_url( $payload );
		$meta    = $slug ? upserv_get_package_metadata( $slug ) : false;
		$vcs_url = $meta ? trailingslashit( $meta['vcs_url'] ) : false;

		if ( ! $vcs_url || ! $url || $vcs_url !== $url ) {
			return;
		}

		$key        = $meta ? $meta['vcs_key'] : false;
		$vcs_config = upserv_get_option( 'remote_repositories/' . $key, array() );

		do_action( 'upserv_webhook_before_handling_request', $vcs_config );

		if ( $this->validate_request( $vcs_config ) ) {
			$slug           = isset( $wp->query_vars['slug'] ) ?
				trim( rawurldecode( $wp->query_vars['slug'] ) ) :
				null;
			$type           = isset( $wp->query_vars['type'] ) ?
				trim( rawurldecode( $wp->query_vars['type'] ) ) :
				null;
			$delay          = $vcs_config['check_delay'];
			$dir            = Data_Manager::get_data_dir( 'packages' );
			$package_exists = null;
			$payload        = $payload ? wp_json_encode( $payload ) : false;
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
					$params           = array( $slug, $type, true );
					$result           = true;
					$scheduled_action = as_next_scheduled_action( $hook, $params );
					$timestamp        = is_int( $scheduled_action ) ? $scheduled_action : false;

					if ( ! is_int( $scheduled_action ) ) {

						if ( ! $scheduled_action ) {
							as_unschedule_all_actions( $hook );
							do_action( 'upserv_cleared_check_remote_schedule', $slug, $hook );
						}

						$delay     = apply_filters( 'upserv_check_remote_delay', $delay, $slug );
						$timestamp = ( $delay ) ?
							time() + ( abs( intval( $delay ) ) * MINUTE_IN_SECONDS ) :
							time();
						$result    = as_schedule_single_action( $timestamp, $hook, $params );

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
						$response['message']      = sprintf(
						/* translators: %s: package ID */
							__( 'Failed to sechedule download for package %s.', 'updatepulse-server' ),
							sanitize_title( $slug )
						);
					}
				} else {
					as_unschedule_all_actions( $hook );
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
						$response['message']      = sprintf(
						/* translators: %s: package ID */
							__( 'Failed to download package %s.', 'updatepulse-server' ),
							sanitize_title( $slug )
						);
					}
				}

				do_action(
					'upserv_webhook_after_processing_request',
					$payload,
					$slug,
					$type,
					$package_exists,
					$vcs_config
				);
			}

			$response['time_elapsed'] = sprintf( '%.3f', microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] );
		} else {
			$this->http_response_code = 403;
			$response                 = array(
				'message' => __( 'Invalid request signature', 'updatepulse-server' ),
			);

			do_action( 'upserv_webhook_invalid_request', $vcs_config );
		}

		$response = apply_filters( 'upserv_webhook_response', $response, $this->http_response_code, $vcs_config );

		do_action( 'upserv_webhook_after_handling_request', $vcs_config, $response );
		wp_send_json( $response, $this->http_response_code );
	}

	protected function validate_request( $vcs_config ) {
		$valid  = false;
		$sign   = false;
		$secret = $vcs_config && isset( $vcs_config['secret'] ) ? $vcs_config['secret'] : false;
		$secret = apply_filters( 'upserv_webhook_secret', $secret, $vcs_config );

		if ( ! $vcs_config || ! $secret ) {
			return apply_filters( 'upserv_webhook_validate_request', $valid, $sign, '', $vcs_config );
		}

		if ( isset( $_SERVER['HTTP_X_GITLAB_TOKEN'] ) ) {
			$valid = $_SERVER['HTTP_X_GITLAB_TOKEN'] === $secret;
		} else {

			if ( isset( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ) {
				$sign = $_SERVER['HTTP_X_HUB_SIGNATURE_256'];
			} elseif ( isset( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ) {
				$sign = $_SERVER['HTTP_X_HUB_SIGNATURE'];
			}

			$sign = apply_filters( 'upserv_webhook_signature', $sign, $secret, $vcs_config );

			if ( $sign ) {
				$sign_parts = explode( '=', $sign );
				$sign       = 2 === count( $sign_parts ) ? end( $sign_parts ) : false;
				$algo       = ( $sign ) ? reset( $sign_parts ) : false;
				$payload    = ( $sign ) ? @file_get_contents( 'php://input' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
				$valid      = $sign && hash_equals( hash_hmac( $algo, $payload, $secret ), $sign );
			}
		}

		return apply_filters( 'upserv_webhook_validate_request', $valid, $sign, $secret, $vcs_config );
	}

	protected function get_payload() {
		$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		$decoded = json_decode( $payload, true );

		if ( ! $decoded ) {
			parse_str( $payload, $payload );

			if ( is_array( $payload ) && isset( $payload['payload'] ) ) {
				$decoded = json_decode( $payload['payload'] );
			} elseif ( is_string( $payload ) ) {
				$decoded = json_decode( $payload );
			}
		}

		return $decoded;
	}

	protected function get_payload_vcs_url( $payload ) {
		$url = false;

		if ( isset( $payload['repository'], $payload['repository']['html_url'] ) ) {
			$url = $payload['repository']['html_url'];
		} elseif ( isset( $payload['repository'], $payload['repository']['git_http_url'] ) ) {
			$url = $payload['repository']['git_http_url'];
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
}
