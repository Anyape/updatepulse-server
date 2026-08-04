<?php
/**
 * Nonce generation, persistence, and validation.
 *
 * @package UPServ
 */

namespace Anyape\UpdatePulse\Server\Nonce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use DateTime;
use DateTimeZone;
use PasswordHash;
use Anyape\Utils\Utils;
use Anyape\UpdatePulse\Server\Scheduler\Scheduler;

/**
 * Nonce class
 *
 * @since 1.0.0
 */
class Nonce {

	/**
	 * Default expiry length
	 *
	 * Default time in seconds before a nonce expires.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const DEFAULT_EXPIRY_LENGTH = MINUTE_IN_SECONDS / 2;
	/**
	 * Nonce only return type
	 *
	 * Constant indicating to return just the nonce string.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const NONCE_ONLY = 1;
	/**
	 * Nonce info array return type
	 *
	 * Constant indicating to return the nonce with additional information.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const NONCE_INFO_ARRAY = 2;

	/**
	 * True nonce flag
	 *
	 * Indicates if a nonce is a true nonce.
	 *
	 * @var bool|null
	 * @since 1.0.0
	 */
	protected static $true_nonce;
	/**
	 * Expiry length
	 *
	 * Time in seconds before a nonce expires.
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	protected static $expiry_length;
	/**
	 * API request flag
	 *
	 * Indicates if the current request targets the nonce or token API endpoint.
	 *
	 * @var int|null
	 * @since 1.0.0
	 */
	protected static $doing_api_request = null;
	/**
	 * Private keys
	 *
	 * Array of private keys used for authentication.
	 *
	 * @var array|null
	 * @since 1.0.0
	 */
	protected static $private_keys;

	/**
	 * Key proven by the current Nonce API request signature.
	 *
	 * @var array|false
	 * @since 1.1.0
	 */
	protected static $authenticated_key = false;

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Activate
	 *
	 * Setup necessary database tables on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		$result = self::maybe_create_or_upgrade_db();

		if ( ! $result ) {
			$error_message = __( 'Failed to create the necessary database table(s).', 'updatepulse-server' );

			die( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Deactivate
	 *
	 * Clean up scheduled actions on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		Scheduler::get_instance()->unschedule_all_actions( 'upserv_nonce_cleanup' );
	}

	/**
	 * Initialize scheduler
	 *
	 * Schedule recurring actions for nonce cleanup.
	 *
	 * @since 1.0.0
	 */
	public static function upserv_scheduler_init() {

		if ( Scheduler::get_instance()->has_scheduled_action( 'upserv_nonce_cleanup' ) ) {
			return;
		}

		$d = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );

		$d->setTime( 0, 0, 0 );
		Scheduler::get_instance()->schedule_recurring_action(
			$d->getTimestamp() + DAY_IN_SECONDS,
			DAY_IN_SECONDS,
			'upserv_nonce_cleanup'
		);
	}

	/**
	 * Add endpoints
	 *
	 * Add rewrite rules for nonce and token endpoints.
	 *
	 * @since 1.0.0
	 */
	public static function add_endpoints() {
		add_rewrite_rule(
			'^updatepulse-server-token/*?$',
			'index.php?$matches[1]&action=token&__upserv_nonce_api=1&',
			'top'
		);
		add_rewrite_rule(
			'^updatepulse-server-nonce/*?$',
			'index.php?$matches[1]&action=nonce&__upserv_nonce_api=1&',
			'top'
		);
	}

	/**
	 * Parse request
	 *
	 * Authorizes nonce and token endpoint requests, dispatches the selected action,
	 * and sends the resulting JSON response.
	 *
	 * @since 1.0.0
	 */
	public static function parse_request() {
		global $wp;

		if ( ! isset( $wp->query_vars['__upserv_nonce_api'] ) ) {
			return;
		}

		$code     = 400;
		$response = array(
			'code'    => 'action_not_found',
			'message' => __( 'Malformed request', 'updatepulse-server' ),
		);

		if ( ! self::authorize() ) {
			$code     = 403;
			$response = array(
				'code'    => 'unauthorized',
				'message' => __( 'Unauthorized access.', 'updatepulse-server' ),
			);
		} elseif ( isset( $wp->query_vars['action'] ) ) {
			$method  = $wp->query_vars['action'];
			$payload = $wp->query_vars;

			unset( $payload['action'] );

			/**
			 * Validate security-sensitive authorization intent before nonce creation.
			 *
			 * Ordinary custom nonce data remains opaque. API owners use this filter
			 * to prove and normalize claims that would grant access to their API.
			 *
			 * @since 1.1.0
			 *
			 * @param true|\WP_Error $validation True or an authorization error.
			 * @param array          $payload    The unmodified Nonce API payload.
			 * @param string         $method     The API action, `token` or `nonce`.
			 */
			$validation = apply_filters(
				'upserv_nonce_api_payload_validation',
				true,
				$payload,
				$method
			);

			if ( is_wp_error( $validation ) ) {
				$response = array(
					'code'    => 'invalid_parameters',
					'message' => __( 'Malformed request.', 'updatepulse-server' ),
				);
			} else {

				/**
				 * Filter the payload sent to the Nonce API.
				 *
				 * @param array $payload The payload sent to the Nonce API
				 * @param string $method The api action - `token` or `nonce`
				 */
				$payload = apply_filters( 'upserv_nonce_api_payload', $payload, $method );

				if (
					is_string( $wp->query_vars['action'] ) &&
					method_exists(
						__CLASS__,
						'generate_' . $wp->query_vars['action'] . '_api_response'
					)
				) {
					$method   = 'generate_' . $wp->query_vars['action'] . '_api_response';
					$response = self::$method( $payload );

					if ( $response ) {
						$code                     = 200;
						$response['time_elapsed'] = Utils::get_time_elapsed();
					} else {
						$code     = 500;
						$response = array(
							'code'    => 'internal_error',
							'message' => __( 'Internal Error - nonce insert error', 'updatepulse-server' ),
						);

						Utils::php_log( __METHOD__ . ' wpdb::insert error' );
					}
				}
			}
		}

		/**
		 * Filter the HTTP response code to be sent by the Nonce API.
		 *
		 * @param int $code The HTTP response code to be sent by the Nonce API.
		 * @param array $request_params The request's parameters
		 */
		$code = apply_filters( 'upserv_nonce_api_code', $code, $wp->query_vars );

		/**
		 * Filter the response to be sent by the Nonce API.
		 *
		 * @param array $response The response to be sent by the Nonce API
		 * @param int $code The HTTP response code sent by the Nonce API.
		 * @param array $request_params The request's parameters
		 */
		$response = apply_filters( 'upserv_nonce_api_response', $response, $code, $wp->query_vars );

		wp_send_json( $response, $code );
	}

	/**
	 * Add query vars
	 *
	 * Add custom query variables for nonce and token endpoints.
	 *
	 * @param array $query_vars Existing query variables.
	 * @return array Modified query variables.
	 * @since 1.0.0
	 */
	public static function query_vars( $query_vars ) {
		$query_vars = array_merge(
			$query_vars,
			array(
				'__upserv_nonce_api',
				'api_signature',
				'api_credentials',
				'action',
				'expiry_length',
				'data',
			)
		);

		return $query_vars;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Create or upgrade database
	 *
	 * Create or upgrade the custom nonce table and verify that it exists.
	 *
	 * @return bool True on success, false on failure.
	 * @since 1.0.0
	 */
	public static function maybe_create_or_upgrade_db() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql =
			"CREATE TABLE {$wpdb->prefix}upserv_nonce (
				id int(12) NOT NULL auto_increment,
				nonce varchar(255) NOT NULL,
				true_nonce tinyint(2) NOT NULL DEFAULT '1',
				expiry int(12) NOT NULL,
				data longtext NOT NULL,
				PRIMARY KEY (id),
				KEY nonce (nonce)
			) {$charset_collate};";

		dbDelta( $sql );

		$table_name = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}upserv_nonce'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the plugin's custom nonce table immediately after dbDelta().

		if ( "{$wpdb->prefix}upserv_nonce" !== $table_name ) {
			return false;
		}

		return true;
	}

	/**
	 * Register hooks
	 *
	 * Registers endpoint hooks on every request and cleanup hooks outside nonce API requests.
	 *
	 * @since 1.0.0
	 */
	public static function register() {

		if ( ! self::is_doing_api_request() ) {
			add_action( 'upserv_scheduler_init', array( __CLASS__, 'upserv_scheduler_init' ) );
			add_action( 'upserv_nonce_cleanup', array( __CLASS__, 'upserv_nonce_cleanup' ) );
		}

		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_request' ), -99, 0 );

		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ), -99, 1 );
	}

	/**
	 * Initialize authentication
	 *
	 * Initialize the private keys used for authentication.
	 *
	 * @param array $private_keys Array of private keys.
	 * @since 1.0.0
	 */
	public static function init_auth( $private_keys ) {
		self::$private_keys = $private_keys;
	}

	/**
	 * Match the authenticated Nonce API key against a specific key set.
	 *
	 * This intentionally bypasses the merged-key authorization filter so API
	 * owners can prove that a signature belongs to their own configured keys.
	 *
	 * @since 1.1.0
	 *
	 * @param array $private_keys Private API keys indexed by key ID.
	 * @return array|false Authenticated key data, or false.
	 */
	public static function authenticate_api_request( $private_keys ) {
		if ( ! self::$authenticated_key ) {
			return false;
		}

		$key_id = self::$authenticated_key['id'];

		return isset( $private_keys[ $key_id ]['key'] ) && hash_equals( self::$authenticated_key['key'], $private_keys[ $key_id ]['key'] ) ? array(
			'id'     => $key_id,
			'access' => isset( $private_keys[ $key_id ]['access'] ) && is_array( $private_keys[ $key_id ]['access'] ) ? $private_keys[ $key_id ]['access'] : array(),
		) : false;
	}

	/**
	 * Determine whether requested access is contained by configured access.
	 *
	 * @since 1.1.0
	 *
	 * @param array $requested_access  Requested access entries.
	 * @param array $configured_access Configured access entries.
	 * @return bool Whether every requested entry is configured.
	 */
	public static function is_access_subset( $requested_access, $configured_access ) {
		foreach ( $requested_access as $access ) {
			if ( ! is_string( $access ) || ( ! in_array( 'all', $configured_access, true ) && ! in_array( $access, $configured_access, true ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if doing API request
	 *
	 * Check whether the current URL targets the nonce or token API endpoint.
	 *
	 * @return int|null One for a match, zero for no match, or null when the URL is unavailable.
	 * @since 1.0.0
	 */
	public static function is_doing_api_request() {

		if ( null === self::$doing_api_request ) {
			self::$doing_api_request = Utils::is_url_subpath_match( '/^updatepulse-server-(nonce|token)$/' );
		}

		return self::$doing_api_request;
	}

	/**
	 * Create nonce
	 *
	 * Create a new nonce.
	 *
	 * @param bool  $true_nonce Indicates if the nonce is a true nonce.
	 * @param int   $expiry_length Time in seconds before the nonce expires.
	 * @param array $data Additional data to store with the nonce.
	 * @param int   $return_type Return type (nonce only or nonce info array).
	 * @param bool  $store Indicates if the nonce should be stored in the database.
	 * @return string|array|false The nonce, nonce information, or false on storage failure.
	 * @since 1.0.0
	 */
	public static function create_nonce(
		$true_nonce = true,
		$expiry_length = self::DEFAULT_EXPIRY_LENGTH,
		$data = array(),
		$return_type = self::NONCE_ONLY,
		$store = true
	) {
		/**
		 * Filter the nonce value before it is generated. A truthy string bypasses the
		 * default generation algorithm but still follows the normal storage and return flow.
		 *
		 * @param false|string $nonce_value A custom nonce value, or false to generate one.
		 * @param bool $true_nonce Whether the nonce is a true, one-time-use nonce
		 * @param int $expiry_length The expiry length of the nonce in seconds
		 * @param array $data Data to store along the nonce
		 * @param int $return_type Nonce::NONCE_ONLY or Nonce::NONCE_INFO_ARRAY.
		 */
		$nonce = apply_filters(
			'upserv_created_nonce',
			false,
			$true_nonce,
			$expiry_length,
			$data,
			$return_type
		);

		if ( ! $nonce ) {
			$id    = self::generate_id();
			$nonce = md5( wp_salt( 'nonce' ) . $id . microtime( true ) );
		}

		$data = is_array( $data ) ? filter_var_array( $data, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : false;

		if ( $data && isset( $data['test'] ) && 1 === intval( $data['test'] ) ) {
			$store = false;
		}

		$permanent = false;

		if ( isset( $data['permanent'] ) ) {
			$data['permanent'] = filter_var( $data['permanent'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			$permanent         = (bool) $data['permanent'];
		}

		$expiry = $permanent ? 0 : time() + abs( intval( $expiry_length ) );
		$data   = $data ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '{}';

		if ( $store ) {
			$result = self::store_nonce( $nonce, (bool) $true_nonce, $expiry, $data );
		} else {
			$result = array(
				'nonce'      => $nonce,
				'true_nonce' => (bool) $true_nonce,
				'expiry'     => $expiry,
				'data'       => $data,
			);
		}

		if ( self::NONCE_INFO_ARRAY === $return_type ) {

			if ( is_array( $result ) ) {
				$result['data'] = json_decode( $result['data'], true );
			}

			$return = $result;
		} else {
			$return = ( $result ) ? $result['nonce'] : $result;
		}

		return $return;
	}

	/**
	 * Get nonce expiry
	 *
	 * Get the nonce expiry timestamp.
	 *
	 * @param string $nonce The nonce string.
	 * @return int Unix timestamp, or zero when the nonce was not found or is permanent.
	 * @since 1.0.0
	 */
	public static function get_nonce_expiry( $nonce ) {
		global $wpdb;

		$row = wp_cache_get( 'nonce_' . $nonce, 'updatepulse-server', false, $found );

		if ( ! $found ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reading the plugin's custom nonce table after a cache miss.
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}upserv_nonce WHERE nonce = %s;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$nonce
				)
			);

			wp_cache_set( 'nonce_' . $nonce, $row, 'updatepulse-server' );
		}

		if ( ! $row ) {
			$nonce_expiry = 0;
		} else {
			$nonce_expiry = $row->expiry;
		}

		return intval( $nonce_expiry );
	}

	/**
	 * Get nonce data
	 *
	 * Get the data associated with a nonce.
	 *
	 * @param string $nonce The nonce string.
	 * @return mixed The decoded nonce data, or an empty array when no record exists.
	 * @since 1.0.0
	 */
	public static function get_nonce_data( $nonce ) {
		global $wpdb;

		$row = wp_cache_get( 'nonce_' . $nonce, 'updatepulse-server', false, $found );

		if ( ! $found ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reading the plugin's custom nonce table after a cache miss.
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}upserv_nonce WHERE nonce = %s;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$nonce
				)
			);

			wp_cache_set( 'nonce_' . $nonce, $row, 'updatepulse-server' );
		}

		if ( ! $row ) {
			$data = array();
		} else {
			$data = is_string( $row->data ) ? json_decode( $row->data, true ) : array();
		}

		return $data;
	}

	/**
	 * Validate nonce
	 *
	 * Fetch and validate a nonce, consuming it when it is marked for one-time use.
	 *
	 * @param string $value The nonce string.
	 * @return bool True if the nonce is valid, false otherwise.
	 * @since 1.0.0
	 */
	public static function validate_nonce( $value ) {

		if ( empty( $value ) ) {
			return false;
		}

		$nonce = self::fetch_nonce( $value );
		$valid = ( $nonce === $value );

		return $valid;
	}

	/**
	 * Delete nonce
	 *
	 * Delete a nonce from the database.
	 *
	 * @param string $value The nonce string.
	 * @return bool True on success, false on failure.
	 * @since 1.0.0
	 */
	public static function delete_nonce( $value ) {
		global $wpdb;

		$result = $wpdb->delete( "{$wpdb->prefix}upserv_nonce", array( 'nonce' => $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Deleting from the plugin's custom nonce table and invalidating its cache below.

		wp_cache_delete( 'nonce_' . $value, 'updatepulse-server' );

		return (bool) $result;
	}

	/**
	 * Nonce cleanup
	 *
	 * Deletes non-permanent nonces that have remained expired beyond the default grace period.
	 *
	 * @since 1.0.0
	 */
	public static function upserv_nonce_cleanup() {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {
			return;
		}

		global $wpdb;

		$sql      = "DELETE FROM {$wpdb->prefix}upserv_nonce
			WHERE expiry < %d
			AND (
				JSON_VALID(`data`) = 0
				OR (
					JSON_VALID(`data`) = 1
					AND (
						JSON_EXTRACT(`data` , '$.permanent') IS NULL
						OR JSON_EXTRACT(`data` , '$.permanent') = 0
						OR JSON_EXTRACT(`data` , '$.permanent') = '0'
						OR JSON_EXTRACT(`data` , '$.permanent') = false
					)
				)
			);";
		$sql_args = array( time() - self::DEFAULT_EXPIRY_LENGTH );

		/**
		 * Filter the SQL query used to clear expired nonces.
		 *
		 * @param string $sql The SQL query used to clear expired nonces
		 * @param array $sql_args The arguments passed to the SQL query used to clear expired nonces
		 */
		$sql = apply_filters( 'upserv_clear_nonces_query', $sql, $sql_args );

		/**
		 * Filter the arguments passed to the SQL query used to clear expired nonces.
		 *
		 * @param array $sql_args The arguments passed to the SQL query used to clear expired nonces
		 * @param string $sql The SQL query used to clear expired nonces
		 */
		$sql_args = apply_filters( 'upserv_clear_nonces_query_args', $sql_args, $sql );
		$result   = $wpdb->query( $wpdb->prepare( $sql, $sql_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Filterable prepared maintenance query for the plugin's custom nonce table.

		return (bool) $result;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	// API action --------------------------------------------------

	/**
	 * Generate token API response
	 *
	 * Generate a reusable token response for the token API endpoint.
	 *
	 * @param array $payload The request payload.
	 * @return array|false The API response, or false on storage failure.
	 * @since 1.0.0
	 */
	protected static function generate_token_api_response( $payload ) {
		return self::generate_api_response( $payload, false );
	}

	/**
	 * Generate nonce API response
	 *
	 * Generate a one-time nonce response for the nonce API endpoint.
	 *
	 * @param array $payload The request payload.
	 * @return array|false The API response, or false on storage failure.
	 * @since 1.0.0
	 */
	protected static function generate_nonce_api_response( $payload ) {
		return self::generate_api_response( $payload, true );
	}

	/**
	 * Generate API response
	 *
	 * Generate stored nonce information using payload expiry and data values.
	 *
	 * @param array $payload The request payload.
	 * @param bool  $is_nonce Indicates if the response is for a nonce.
	 * @return array|false The API response, or false on storage failure.
	 * @since 1.0.0
	 */
	protected static function generate_api_response( $payload, $is_nonce ) {
		return self::create_nonce(
			$is_nonce,
			isset( $payload['expiry_length'] ) && is_numeric( $payload['expiry_length'] ) ?
				$payload['expiry_length'] :
				self::DEFAULT_EXPIRY_LENGTH,
			isset( $payload['data'] ) ? $payload['data'] : array(),
			self::NONCE_INFO_ARRAY,
		);
	}

	// Misc. -------------------------------------------------------

	/**
	 * Fetch nonce
	 *
	 * Fetches a nonce, applies expiry and deletion policies, and consumes one-time values.
	 *
	 * @param string $value The nonce string.
	 * @return string|null The nonce or null if not found.
	 * @since 1.0.0
	 */
	protected static function fetch_nonce( $value ) {
		global $wpdb;

		$nonce = null;

		$row = wp_cache_get( 'nonce_' . $value, 'updatepulse-server', false, $found );

		if ( ! $found ) {
			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reading the plugin's custom nonce table after a cache miss.
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}upserv_nonce WHERE nonce = %s;", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$value
				)
			);

			wp_cache_set( 'nonce_' . $value, $row, 'updatepulse-server' );
		}

		if ( ! $row ) {
			return $nonce;
		}

		$data      = is_string( $row->data ) ? json_decode( $row->data, true ) : array();
		$permanent = false;

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( isset( $data['permanent'] ) ) {
			$data['permanent'] = filter_var( $data['permanent'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			$permanent         = (bool) $data['permanent'];
		}

		if ( $row->expiry < time() && ! $permanent ) {
			/**
			 * Filter whether to consider the nonce has expired.
			 *
			 * @param string|null $nonce_value Null by default to invalidate the expired nonce.
			 * @param string $stored_nonce The stored nonce value.
			 * @param bool $true_nonce Whether the nonce is a true, one-time-use nonce
			 * @param int $expiry The timestamp at which the nonce expires
			 * @param array $data Data stored along the nonce
			 * @param object $row The database record corresponding to the nonce
			 */
			$row->nonce = apply_filters(
				'upserv_expire_nonce',
				null,
				$row->nonce,
				$row->true_nonce,
				$row->expiry,
				$data,
				$row
			);
		}

		/**
		 * Filter whether to delete the nonce.
		 *
		 * @param bool $delete Whether to delete the nonce.
		 * @param bool $true_nonce Whether the nonce is a true, one-time-use nonce.
		 * @param int $expiry The timestamp at which the nonce expires.
		 * @param array $data Data stored along the nonce.
		 * @param object $row The database record corresponding to the nonce.
		 */
		$delete_nonce = apply_filters(
			'upserv_delete_nonce',
			$row->true_nonce || null === $row->nonce,
			$row->true_nonce,
			$row->expiry,
			$data,
			$row
		);

		if ( $delete_nonce ) {
			self::delete_nonce( $value );
		}

		/**
		 * Filter the value of the nonce after it has been fetched from the database.
		 *
		 * @param string $nonce_value The value of the nonce after it has been fetched from the database
		 * @param bool $true_nonce Whether the nonce is a true, one-time-use nonce
		 * @param int $expiry The timestamp at which the nonce expires
		 * @param array $data Data stored along the nonce
		 * @param object $row The database record corresponding to the nonce
		 */
		$nonce = apply_filters( 'upserv_fetch_nonce', $row->nonce, $row->true_nonce, $row->expiry, $data, $row );

		return $nonce;
	}

	/**
	 * Store nonce
	 *
	 * Store a nonce in the database.
	 *
	 * @param string $nonce The nonce string.
	 * @param bool   $true_nonce Indicates if the nonce is a true nonce.
	 * @param int    $expiry Unix expiry timestamp, or zero for a permanent nonce.
	 * @param string $data The nonce data.
	 * @return array|false The stored nonce data or false on failure.
	 * @since 1.0.0
	 */
	protected static function store_nonce( $nonce, $true_nonce, $expiry, $data ) {
		global $wpdb;

		$data   = array(
			'nonce'      => $nonce,
			'true_nonce' => (bool) $true_nonce,
			'expiry'     => $expiry,
			'data'       => $data,
		);
		$result = $wpdb->insert( "{$wpdb->prefix}upserv_nonce", $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writing to the plugin's custom nonce table.

		if ( (bool) $result ) {
			return $data;
		}

		return false;
	}

	/**
	 * Generate ID
	 *
	 * Generate a unique ID.
	 *
	 * @return string The generated ID.
	 * @since 1.0.0
	 */
	protected static function generate_id() {
		require_once ABSPATH . 'wp-includes/class-phpass.php';

		$hasher = new PasswordHash( 8, false );

		return md5( $hasher->get_random_bytes( 100, false ) );
	}

	/**
	 * Authorize request
	 *
	 * Authorize the incoming request using the provided credentials and signature.
	 *
	 * @return bool True if the request is authorized, false otherwise.
	 * @since 1.0.0
	 */
	protected static function authorize() {
		$sign         = false;
		$key_id       = false;
		$timestamp    = 0;
		$auth         = false;
		$credentials  = array();
		$current_time = time();

		self::$authenticated_key = false;

		if ( ! empty( $_SERVER['HTTP_X_UPDATEPULSE_API_SIGNATURE'] ) ) {
			$sign = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_UPDATEPULSE_API_SIGNATURE'] ) );
		} else {
			global $wp;

			if (
				isset( $wp->query_vars['api_signature'] ) &&
				is_string( $wp->query_vars['api_signature'] ) &&
				! empty( $wp->query_vars['api_signature'] )
			) {
				$sign = $wp->query_vars['api_signature'];
			}
		}

		if ( ! empty( $_SERVER['HTTP_X_UPDATEPULSE_API_CREDENTIALS'] ) ) {
			$credentials = explode(
				'|',
				sanitize_text_field(
					wp_unslash( $_SERVER['HTTP_X_UPDATEPULSE_API_CREDENTIALS'] )
				)
			);
		} else {
			global $wp;

			if (
				isset( $wp->query_vars['api_credentials'] ) &&
				is_string( $wp->query_vars['api_credentials'] ) &&
				! empty( $wp->query_vars['api_credentials'] )
			) {
				$credentials = explode( '|', $wp->query_vars['api_credentials'] );
			}
		}

		if ( 2 === count( $credentials ) ) {
			$timestamp = intval( reset( $credentials ) );
			$key_id    = end( $credentials );
		}

		$validity = (bool) ( constant( 'WP_DEBUG' ) ) ? HOUR_IN_SECONDS : MINUTE_IN_SECONDS;

		if ( $current_time < $timestamp || $timestamp < ( $current_time - $validity ) ) {
			$timestamp = false;
		}

		if ( $sign && $timestamp && $key_id && isset( self::$private_keys[ $key_id ] ) ) {
			$payload = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$values  = upserv_build_nonce_api_signature(
				$key_id,
				self::$private_keys[ $key_id ]['key'],
				$timestamp,
				$payload
			);
			$auth    = hash_equals( $values['signature'], $sign );

			if ( $auth ) {
				self::$authenticated_key = array(
					'id'  => $key_id,
					'key' => self::$private_keys[ $key_id ]['key'],
				);
			}
		}

		/**
		 * Filter whether the request for a nonce is authorized.
		 *
		 * @param bool $authorized Whether the request is authorized
		 * @param array $received_credentials The received credentials and signature.
		 * @param array $private_auth_keys The configured private authorization keys.
		 */
		return apply_filters(
			'upserv_nonce_authorize',
			$auth,
			array(
				'credentials' => $timestamp . '/' . $key_id,
				'signature'   => $sign,
			),
			self::$private_keys
		);
	}
}
