<?php

namespace Anyape\UpdatePulse\Server\Server\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Exception;
use DateTime;
use DateTimeZone;
use WP_Error;
use Anyape\Crypto\Crypto;

class License_Server {

	public static $license_definition = array(
		'id'                  => 0,
		'license_key'         => '',
		'max_allowed_domains' => 1,
		'allowed_domains'     => array(),
		'status'              => '',
		'owner_name'          => '',
		'email'               => '',
		'company_name'        => '',
		'txn_id'              => '',
		'date_created'        => '',
		'date_renewed'        => '',
		'date_expiry'         => '',
		'package_slug'        => '',
		'package_type'        => '',
		'data'                => array(),
	);
	public static $browsing_query     = array(
		'relationship' => 'AND',
		'limit'        => 999,
		'offset'       => 0,
		'order_by'     => 'date_created',
		'criteria'     => array(),
	);
	public static $browsing_operators = array(
		'=',
		'!=',
		'>',
		'<',
		'>=',
		'<=',
		'BETWEEN',
		'NOT BETWEEN',
		'IN',
		'NOT IN',
		'LIKE',
		'NOT LIKE',
	);
	public static $license_statuses   = array(
		'pending',
		'activated',
		'deactivated',
		'on-hold',
		'blocked',
		'expired',
	);

	public function __construct() {}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function build_license_payload( $payload ) {
		$payload = $this->extend_license_payload( $this->filter_license_payload( $payload ) );

		unset( $payload['id'] );

		return $this->cleanup_license_payload( $payload );
	}

	public function browse_licenses( $payload ) {
		global $wpdb;

		$prepare_args = array();
		$payload      = apply_filters( 'upserv_browse_licenses_payload', $payload );

		try {
			$browsing_query = $this->build_browsing_query( $payload );
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_license_query', 'Invalid license query - ' . $e->getMessage() );
		}

		$sql = "SELECT * FROM {$wpdb->prefix}upserv_licenses WHERE 1 = 1 ";

		foreach ( $browsing_query['criteria'] as $crit ) {
			$sql .= $browsing_query['relationship'] . ' ' . $crit['field'] . ' ';

			if ( 'id' === $crit['field'] || 'max_allowed_domains' === $crit['field'] ) {
				$placeholder = '%d';
			} else {
				$placeholder = '%s';
			}

			if ( 'IN' === $crit['operator'] || 'NOT IN' === $crit['operator'] ) {
				$sql .= $crit['operator'] . ' (' . implode( ', ', array_fill( 0, count( $crit['value'] ), $placeholder ) ) . ')';
			} elseif ( 'BETWEEN' === $crit['operator'] || 'NOT BETWEEN' === $crit['operator'] ) {
				$sql .= $crit['operator'] . ' ' . $placeholder . ' AND ' . $placeholder;
			} else {
				$sql .= $crit['operator'] . ' ' . $placeholder;
			}

			if ( ! is_array( $crit['value'] ) ) {
				$prepare_args[] = $crit['value'];
			} else {
				$prepare_args = array_merge( $prepare_args, $crit['value'] );
			}
		}

		$sql .= ' ORDER BY ' . $browsing_query['order_by'];

		if ( 0 < $browsing_query['limit'] ) {
			$sql           .= ' LIMIT %d OFFSET %d';
			$prepare_args[] = $browsing_query['limit'];
			$prepare_args[] = $browsing_query['offset'];
		}

		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$licenses = array();

		if ( ! empty( $rows ) ) {

			foreach ( $rows as $row ) {
				$row->max_allowed_domains      = intval( $row->max_allowed_domains );
				$row->allowed_domains          = maybe_unserialize( $row->allowed_domains );
				$row->data                     = json_decode( $row->data, true );
				$row->data                     = ( null === $row->data ) ?
					array() :
					$row->data;
				$licenses[ $row->license_key ] = $row;
			}
		}

		do_action( 'upserv_did_browse_licenses', $licenses, $payload );

		return $licenses;
	}

	public function read_license( $payload, $force = false ) {
		$where_field = isset( $payload['license_key'] ) ? 'license_key' : 'id';
		$where_value = $payload[ $where_field ];
		$md5         = md5( wp_json_encode( array( $where_field => $where_value ) ) );
		$return      = wp_cache_get( $md5, 'updatepulse-server', false, $found );

		if ( $force || ! $found ) {
			$payload    = $this->filter_license_payload( $payload );
			$payload    = apply_filters( 'upserv_read_license_payload', $payload );
			$validation = $this->validate_license_payload( $payload, true );
			$return     = $validation;

			if ( true === $validation ) {
				global $wpdb;

				$payload = $this->sanitize_payload( $payload );
				$sql     = "SELECT * FROM {$wpdb->prefix}upserv_licenses WHERE {$where_field} = %s;";
				$license = $wpdb->get_row( $wpdb->prepare( $sql, $where_value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( is_object( $license ) ) {
					$license->max_allowed_domains = intval( $license->max_allowed_domains );
					$license->allowed_domains     = maybe_unserialize( $license->allowed_domains );
					$license->data                = json_decode( $license->data, true );
					$license->data                = ( null === $license->data ) ? array() : $license->data;
					$return                       = $license;
				}
			}

			wp_cache_set( $md5, $return, 'updatepulse-server' );
		}

		do_action( 'upserv_did_read_license', $return, $payload );

		return $return;
	}

	public function edit_license( $payload ) {
		$payload    = $this->cleanup_license_payload( $this->filter_license_payload( $payload ) );
		$payload    = apply_filters( 'upserv_edit_license_payload', $payload );
		$validation = $this->validate_license_payload( $payload, true );
		$return     = $validation;
		$original   = null;

		if ( true === $validation ) {
			global $wpdb;

			$field    = isset( $payload['license_key'] ) ? 'license_key' : 'id';
			$where    = array( $field => $payload[ $field ] );
			$payload  = $this->sanitize_payload( $payload );
			$original = $this->read_license( $where );

			if ( isset( $payload['allowed_domains'] ) ) {
				$payload['allowed_domains'] = maybe_serialize( $payload['allowed_domains'] );
			}

			if ( isset( $payload['data'] ) ) {
				$payload['data'] = wp_json_encode( $payload['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			}

			$result = $wpdb->update(
				$wpdb->prefix . 'upserv_licenses',
				$payload,
				$where
			);

			if ( false !== $result ) {
				$md5_id = md5( wp_json_encode( array( 'id' => $original->id ) ) );
				$m5_key = md5( wp_json_encode( array( 'license_key' => $original->license_key ) ) );

				wp_cache_delete( $md5_id, 'updatepulse-server' );
				wp_cache_delete( $m5_key, 'updatepulse-server' );

				$return = $this->read_license( $where, true );
			} else {
				php_log( 'License update failed - database update error.' );
				throw new Exception( esc_html__( 'License update failed - database update error.', 'updatepulse-server' ) );
			}
		}

		do_action( 'upserv_did_edit_license', $return, $payload, $original );

		return $return;
	}

	public function add_license( $payload ) {
		$payload    = $this->build_license_payload( $payload );
		$payload    = apply_filters( 'upserv_add_license_payload', $payload );
		$validation = $this->validate_license_payload( $payload );
		$return     = $validation;

		if ( true === $validation ) {
			global $wpdb;

			$payload['id']              = null;
			$payload                    = $this->sanitize_payload( $payload );
			$payload['allowed_domains'] = maybe_serialize( $payload['allowed_domains'] );
			$payload['data']            = wp_json_encode(
				$payload['data'],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			);
			$payload['hmac_key']        = bin2hex( openssl_random_pseudo_bytes( 16 ) );
			$payload['crypto_key']      = bin2hex( openssl_random_pseudo_bytes( 16 ) );
			$result                     = $wpdb->insert(
				$wpdb->prefix . 'upserv_licenses',
				$payload
			);

			if ( false !== $result ) {
				$m5_key = md5( wp_json_encode( array( 'license_key' => $payload['license_key'] ) ) );

				wp_cache_delete( $m5_key, 'updatepulse-server' );
				wp_cache_delete( 'upserv_license_exists_' . $payload['license_key'], 'updatepulse-server' );

				$return = $this->read_license( array( 'license_key' => $payload['license_key'] ), true );
				$md5_id = md5( wp_json_encode( array( 'id' => $return->id ) ) );

				wp_cache_delete( $md5_id, 'updatepulse-server' );
				wp_cache_delete( 'upserv_license_exists_' . $return->id, 'updatepulse-server' );
			} else {
				php_log( 'License creation failed - database insertion error.' );
				throw new Exception( esc_html__( 'License creation failed - database insertion error.', 'updatepulse-server' ) );
			}
		}

		do_action( 'upserv_did_add_license', $return, $payload );

		return $return;
	}

	public function delete_license( $payload ) {
		$payload    = $this->filter_license_payload( $payload );
		$payload    = apply_filters( 'upserv_delete_license_payload', $payload );
		$validation = $this->validate_license_payload( $payload, true );
		$return     = $validation;

		if ( true === $validation ) {
			global $wpdb;

			$field   = isset( $payload['license_key'] ) ? 'license_key' : 'id';
			$where   = array( $field => $payload[ $field ] );
			$payload = $this->sanitize_payload( $payload );
			$license = $this->read_license( $payload );
			$result  = $wpdb->delete(
				$wpdb->prefix . 'upserv_licenses',
				$where
			);

			if ( false !== $result ) {
				$md5_id = md5( wp_json_encode( array( 'id' => $license->id ) ) );
				$m5_key = md5( wp_json_encode( array( 'license_key' => $license->license_key ) ) );

				wp_cache_delete( $md5_id, 'updatepulse-server' );
				wp_cache_delete( $m5_key, 'updatepulse-server' );
				wp_cache_delete( 'upserv_license_exists_' . $license->id, 'updatepulse-server' );
				wp_cache_delete( 'upserv_license_exists_' . $license->license_key, 'updatepulse-server' );

				$return = $license;
			} else {
				php_log( 'License removal failed - database deletion error.' );
				throw new Exception( esc_html__( 'License removal failed - database deletion error.', 'updatepulse-server' ) );
			}
		}

		do_action( 'upserv_did_delete_license', $return, $payload );

		return $return;
	}

	public function generate_license_signature( $license, $domain ) {
		$hmac_key      = $license->hmac_key;
		$crypto_key    = $license->crypto_key;
		$crypt_payload = array( $domain, $license->package_slug, $license->license_key, $license->id );
		$signature     = Crypto::encrypt( implode( '|', $crypt_payload ), $crypto_key, $hmac_key );

		return $signature;
	}

	public function is_signature_valid( $license_key, $license_signature ) {
		$valid      = false;
		$crypt      = $license_signature;
		$license    = $this->read_license( array( 'license_key' => $license_key ) );
		$hmac_key   = $license->hmac_key;
		$crypto_key = $license->crypto_key;

		if ( ! ( empty( $crypt ) ) ) {
			$payload = null;

			try {
				$payload = Crypto::decrypt( $crypt, $crypto_key, $hmac_key );
			} catch ( Exception $e ) {
				$payload = false;
			}

			if ( $payload ) {
				$data         = explode( '|', $payload );
				$domain       = isset( $data[0] ) ? $data[0] : null;
				$package_slug = isset( $data[1] ) ? $data[1] : null;

				if (
					in_array( $domain, $license->allowed_domains, true ) &&
					$license->package_slug === $package_slug
				) {
					$valid = true;
				}
			}
		}

		return $valid;
	}

	public function switch_expired_licenses_status() {
		global $wpdb;

		$timezone      = new DateTimeZone( wp_timezone_string() );
		$date          = new DateTime( 'now', $timezone );
		$license_query = array(
			'limit'    => '-1',
			'criteria' => array(
				array(
					'field'    => 'date_expiry',
					'value'    => '0000-00-00',
					'operator' => '!=',
				),
				array(
					'field'    => 'status',
					'value'    => 'blocked',
					'operator' => '!=',
				),
				array(
					'field'    => 'date_expiry',
					'value'    => $date->format( 'Y-m-d' ),
					'operator' => '<=',
				),
			),
		);
		$time          = time();
		$items         = $this->browse_licenses( $license_query );
		$sql           = "UPDATE {$wpdb->prefix}upserv_licenses
			SET status = 'expired'
			WHERE date_expiry <= %s
			AND status != 'blocked'
			AND date_expiry != '0000-00-00'";

		$wpdb->query( $wpdb->prepare( $sql, $date->format( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {
				$original     = $item;
				$item->status = 'expired';

				if ( ! is_array( $item->data ) ) {
					$item->data = array();
				}

				$item->data['operation_timestamp'] = $time;
				$item->data['operation']           = 'edit';
				$item->data['operation_id']        = bin2hex( random_bytes( 16 ) );

				do_action(
					'upserv_did_edit_license',
					$item,
					array(
						'license_key' => $item->license_key,
						'status'      => 'expired',
					),
					$original
				);
			}
		}
	}

	public function update_licenses_status( $status, $license_ids = array() ) {
		$license_query = array( 'limit' => '-1' );

		global $wpdb;

		$where = '';

		if ( ! empty( $license_ids ) ) {
			$where                     = " AND id IN ('" . implode( "','", $license_ids ) . "')";
			$license_query['criteria'] = array(
				array(
					'field'    => 'id',
					'value'    => $license_ids,
					'operator' => 'IN',
				),
			);
		}

		$time  = time();
		$items = $this->browse_licenses( $license_query );
		$sql   = "UPDATE {$wpdb->prefix}upserv_licenses SET status = %s WHERE 1=1" . $where;

		$wpdb->query( $wpdb->prepare( $sql, $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {
				$original     = $item;
				$item->status = $status;

				if ( ! is_array( $item->data ) ) {
					$item->data = array();
				}

				$item->data['operation_timestamp'] = $time;
				$item->data['operation']           = 'edit';
				$item->data['operation_id']        = bin2hex( random_bytes( 16 ) );

				do_action(
					'upserv_did_edit_license',
					$item,
					array(
						'license_key' => $item->license_key,
						'status'      => $status,
					),
					$original
				);
			}
		}
	}

	public function purge_licenses( $license_ids = array() ) {
		$license_query = array( 'limit' => '-1' );

		global $wpdb;

		$where = '';

		if ( ! empty( $license_ids ) ) {
			$where                     = " AND id IN ('" . implode( "','", $license_ids ) . "')";
			$license_query['criteria'] = array(
				array(
					'field'    => 'id',
					'value'    => $license_ids,
					'operator' => 'IN',
				),
			);
		}

		$time  = time();
		$items = $this->browse_licenses( $license_query );
		$sql   = "DELETE FROM {$wpdb->prefix}upserv_licenses WHERE 1=1" . $where;

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {

				if ( ! is_array( $item->data ) ) {
					$item->data = array();
				}

				$item->data['operation_timestamp'] = $time;
				$item->data['operation']           = 'delete';
				$item->data['operation_id']        = bin2hex( random_bytes( 16 ) );

				do_action( 'upserv_did_delete_license', $item, array( $item->license_key ) );
			}
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function build_browsing_query( $payload ) {
		$original = $payload;
		$payload  = array_intersect_key( $payload, self::$browsing_query );
		$invalid  = array_diff_key( $original, $payload );

		if ( ! empty( $invalid ) ) {
			$keys    = implode( ', ', array_keys( $invalid ) );
			$message = sprintf(
				// translators: %s is a comma-separated list of valid keys.
				__( 'Invalid keys. The following values are valid: %s', 'updatepulse-server' ),
				$keys
			);

			throw new Exception( esc_html( $message ) );
		}

		$payload         = array_merge( self::$browsing_query, $payload );
		$faulty_criteria = array(
			'operator' => '=',
			'field'    => 0,
			'value'    => 1,
		);

		if ( empty( $payload['relationship'] ) ) {
			$payload['relationship'] = self::$browsing_query['relationship'];
		} elseif ( 'AND' !== $payload['relationship'] && 'OR' !== $payload['relationship'] ) {
			throw new Exception( esc_html__( 'Invalid relationship operator. Only "AND" and "OR" are allowed.', 'updatepulse-server' ) );
		}

		if ( ! is_numeric( $payload['limit'] ) && ! empty( $payload['limit'] ) && 0 !== $payload['limit'] ) {
			throw new Exception( esc_html__( 'The limit must be an integer.', 'updatepulse-server' ) );
		}

		$payload['limit'] = intval( $payload['limit'] );

		if ( ! is_numeric( $payload['offset'] ) && ! empty( $payload['offset'] ) || 0 > $payload['offset'] ) {
			throw new Exception( esc_html__( 'The offset must be a positive integer.', 'updatepulse-server' ) );
		}

		$payload['offset'] = intval( $payload['offset'] );

		if (
			isset( $payload['order_by'] ) &&
			! in_array( $payload['order_by'], array_keys( self::$license_definition ), true )
		) {
			$keys    = implode( ', ', array_keys( self::$license_definition ) );
			$message = sprintf(
				// translators: %s is a comma-separated list of valid fields.
				__( 'Invalid order_by field. The following values are valid: %s', 'updatepulse-server' ),
				$keys
			);

			throw new Exception( esc_html( $message ) );
		}

		if ( ! isset( $payload['order_by'] ) ) {
			$payload['order_by'] = 'date_created';
		}

		if ( ! isset( $payload['criteria'][0] ) ) {
			$payload['criteria'] = self::$browsing_query['criteria'];
		}

		if ( ! isset( $payload['criteria'] ) || empty( $payload['criteria'] ) ) {
			return $payload;
		}

		foreach ( $payload['criteria'] as $index => $crit ) {
			$crit = array_intersect_key( $crit, $faulty_criteria );

			if (
				! isset( $crit['operator'], $crit['value'], $crit['field'] ) ||
				empty( $crit['operator'] ) || empty( $crit['value'] ) || empty( $crit['field'] )
			) {
				$allowed_operators = implode( ', ', self::$browsing_operators );
				$message           = sprintf(
					// translators: %s is a comma-separated list of valid operators.
					__( 'Invalid criteria. The following keys are required: operator, value, field. The following values are valid for the operator: %s', 'updatepulse-server' ),
					$allowed_operators
				);

				throw new Exception( esc_html( $message ) );
			}

			if ( ! in_array( $crit['operator'], self::$browsing_operators, true ) ) {
				$allowed_operators = implode( ', ', self::$browsing_operators );
				$message           = sprintf(
					// translators: %s is a comma-separated list of valid operators.
					__( 'Invalid operator. The following values are valid: %s', 'updatepulse-server' ),
					$allowed_operators
				);

				throw new Exception( esc_html( $message ) );
			}

			if ( ! in_array( $crit['field'], array_keys( self::$license_definition ), true ) ) {
				$keys    = implode( ', ', array_keys( self::$license_definition ) );
				$message = sprintf(
					// translators: %s is a comma-separated list of valid fields.
					__( 'Invalid field. The following values are valid: %s', 'updatepulse-server' ),
					$keys
				);

				throw new Exception( esc_html( $message ) );
			}

			if (
				( 'BETWEEN' === $crit['operator'] || 'NOT BETWEEN' === $crit['operator'] ) &&
				( ! is_array( $crit['value'] ) || 2 !== count( $crit['value'] ) )
			) {
				$message = __( 'The value for the BETWEEN operator must be an array with two elements.', 'updatepulse-server' );

				throw new Exception( esc_html( $message ) );
			} elseif (
				( 'IN' === $crit['operator'] || 'NOT IN' === $crit['operator'] ) &&
				! is_array( $crit['value'] )
			) {
				$message = __( 'The value for the IN and NOT IN operators must be an array.', 'updatepulse-server' );

				throw new Exception( esc_html( $message ) );
			} elseif (
				( 'IN' === $crit['operator'] || 'NOT IN' === $crit['operator'] ) &&
				empty( $crit['value'] )
			) {
				$message = __( 'The value for the IN and NOT IN operators must not be empty.', 'updatepulse-server' );

				throw new Exception( esc_html( $message ) );
			} elseif (
				! (
					( 'BETWEEN' === $crit['operator'] || 'NOT BETWEEN' === $crit['operator'] ) ||
					( 'IN' === $crit['operator'] || 'NOT IN' === $crit['operator'] )
				) &&
				! is_scalar( $crit['value'] )
			) {
				$message = __( 'The value must be a scalar for all operators except BETWEEN, NOT BETWEEN, IN, and NOT IN operators.', 'updatepulse-server' );

				throw new Exception( esc_html( $message ) );
			}

			$payload['criteria'][ $index ] = $crit;
		}

		return $payload;
	}

	protected function cleanup_license_payload( $payload ) {

		if ( isset( $payload['license_key'] ) && empty( $payload['license_key'] ) ) {
			$payload['license_key'] = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		}

		if ( isset( $payload['date_created'] ) && empty( $payload['date_created'] ) ) {
			$timezone                = new DateTimeZone( wp_timezone_string() );
			$date                    = new DateTime( time(), $timezone );
			$payload['date_created'] = $date->format( 'Y-m-d' );
		}

		if ( isset( $payload['status'] ) && empty( $payload['status'] ) ) {
			$payload['status'] = 'pending';
		}

		return $payload;
	}

	protected function filter_license_payload( $payload ) {
		return array_intersect_key( $payload, self::$license_definition );
	}

	protected function extend_license_payload( $payload ) {
		return array_merge( self::$license_definition, $payload );
	}

	protected function sanitize_payload( $license ) {

		foreach ( $license as $key => $value ) {

			if ( 'allowed_domains' === $key ) {

				if ( is_array( $value ) && ! empty( $value ) ) {

					foreach ( $value as $index => $domain ) {

						if ( ! is_scalar( $domain ) || 5 > strlen( strval( $domain ) ) ) {
							unset( $license['allowed_domains'][ $index ] );
						}
					}
				} else {
					$license['allowed_domains'] = array();
				}
			} elseif ( 'data' === $key ) {

				if ( empty( $license[ $key ] ) ) {
					$license[ $key ] = '{}';
				}

				if ( is_scalar( $license[ $key ] ) ) {
					$license[ $key ] = wp_json_encode( json_decode( $license[ $key ], true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
				} else {
					$license[ $key ] = wp_json_encode( $license[ $key ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
				}

				$license[ $key ] = json_decode( $license[ $key ], true );
			} else {
				$license[ $key ] = wp_strip_all_tags( $value );
			}
		}

		if (
			isset( $license['date_expiry'], $license['status'] ) &&
			! empty( $license['date_expiry'] ) &&
			'blocked' !== $license['status']
		) {
			$timezone    = new DateTimeZone( wp_timezone_string() );
			$date_expiry = new DateTime( $license['date_expiry'], $timezone );

			if ( $date_expiry->getTimestamp() <= time() ) {
				$license['status'] = 'expired';
			}
		} elseif ( ! isset( $license['status'] ) ) {
			$license['status'] = 'blocked';
		}

		return $license;
	}

	protected function validate_license_payload( $license, $partial = false ) {
		global $wpdb;

		$errors = array();
		$return = true;

		if ( ! is_array( $license ) ) {
			$errors['unexpected'] = __( 'An unexpected error has occured. Please try again. If the problem persists, please contact the author of the plugin.', 'updatepulse-server' );
		} else {
			$date_regex = '/[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])/';

			if ( $partial ) {

				if ( ! isset( $license['id'] ) && ! isset( $license['license_key'] ) ) {
					$errors['missing_key'] = __( 'A license key is required to identify the license.', 'updatepulse-server' );
				} elseif ( isset( $license['license_key'] ) ) {
					$cache_key = 'upserv_license_exists_' . $license['license_key'];
					$exists    = wp_cache_get( $cache_key, 'updatepulse-server', false, $found );

					if ( ! $found ) {
						$sql    = "SELECT COUNT(*) FROM {$wpdb->prefix}upserv_licenses WHERE license_key = %s;";
						$exists = ( '1' === $wpdb->get_var( $wpdb->prepare( $sql, $license['license_key'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

						wp_cache_set( $cache_key, $exists, 'updatepulse-server' );
					}

					if ( ! $exists ) {
						$errors['license_not_found'] = __( 'The license cannot be found.', 'updatepulse-server' );
					}
				} elseif ( ! is_numeric( $license['id'] ) ) {
						$errors['invalid_id'] = __( 'The ID must be an integer.', 'updatepulse-server' );
				} else {
					$cache_key = 'upserv_license_exists_' . $license['id'];
					$exists    = wp_cache_get( $cache_key, 'updatepulse-server', false, $found );

					if ( ! $found ) {
						$sql    = "SELECT COUNT(*) FROM {$wpdb->prefix}upserv_licenses WHERE id = %s;";
						$exists = ( '1' === $wpdb->get_var( $wpdb->prepare( $sql, $license['id'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

						wp_cache_set( $cache_key, $exists, 'updatepulse-server' );
					}

					if ( ! $exists ) {
						$errors['license_not_found'] = __( 'The license cannot be found.', 'updatepulse-server' );
					}
				}
			}

			if (
				! ( $partial && ! isset( $license['license_key'] ) ) &&
				(
					! is_string( $license['license_key'] ) ||
					empty( $license['license_key'] )
				)
			) {
				$errors['invalid_key'] = __( 'The license key is required and must be a string.', 'updatepulse-server' );
			} elseif ( ! $partial && isset( $license['license_key'] ) ) {
				$cache_key = 'upserv_license_exists_' . $license['license_key'];
				$exists    = wp_cache_get( $cache_key, 'updatepulse-server', false, $found );

				if ( ! $found ) {
					$sql    = "SELECT COUNT(*) FROM {$wpdb->prefix}upserv_licenses WHERE license_key = %s;";
					$exists = ( '1' === $wpdb->get_var( $wpdb->prepare( $sql, $license['license_key'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					wp_cache_set( $cache_key, $exists, 'updatepulse-server' );
				}

				if ( $exists ) {
					$errors['key_exists'] = __( 'A value already exists for the given license key. Each key must be unique.', 'updatepulse-server' );
				}
			}

			if (
				! ( $partial && ! isset( $license['max_allowed_domains'] ) ) &&
				(
					! is_numeric( $license['max_allowed_domains'] ) ||
					$license['max_allowed_domains'] < 1
				)
			) {
				$errors['max_allowed_domains_missing'] = __( 'The number of allowed domains is required and must be greater than 1.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['status'] ) ) &&
				! in_array( $license['status'], self::$license_statuses, true )
			) {
				$errors['invalid_status'] = __( 'The license status is invalid.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['email'] ) ) &&
				! filter_var( $license['email'] )
			) {
				$errors['invalid_email'] = __( 'The registered email is required and must be a valid email address.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['date_created'] ) ) &&
				(
					empty( $license['date_created'] ) ||
					! preg_match( $date_regex, $license['date_created'] )
				)
			) {
				$errors['invalid_date_created'] = __( 'The creation date is required and must follow the following format: YYYY-MM-DD', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['date_renewed'] ) ) &&
				(
					! empty( $license['date_renewed'] ) &&
					! preg_match( $date_regex, $license['date_renewed'] )
				)
			) {
				$errors['invalid_date_renewal'] = __( 'The renewal date must follow the following format: YYYY-MM-DD', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['date_expiry'] ) ) &&
				(
					! empty( $license['date_expiry'] ) &&
					! preg_match( $date_regex, $license['date_expiry'] )
				)
			) {
				$errors['invalid_date_expiry'] = __( 'The expiry date must follow the following format: YYYY-MM-DD', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['package_slug'] ) ) &&
				(
					empty( $license['package_slug'] ) ||
					! preg_match( '/[a-z0-9-]*/', $license['package_slug'] )
				)
			) {
				$errors['invalid_package_slug'] = __( 'The package slug is required and must contain only alphanumeric characters or dashes.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['package_type'] ) ) &&
				'plugin' !== $license['package_type'] &&
				'theme' !== $license['package_type'] &&
				'generic' !== $license['package_type']
			) {
				$errors['invalid_package_type'] = __( 'The package type is required and must be "generic", "plugin" or "theme".', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['owner_name'] ) ) &&
				! empty( $license['owner_name'] ) &&
				! is_string( $license['owner_name'] )
			) {
				$errors['invalid_license_owner'] = __( 'The license owner name must be a string.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['company_name'] ) ) &&
				! empty( $license['company_name'] ) &&
				! is_string( $license['company_name'] )
			) {
				$errors['invalid_company_name'] = __( 'The company name must be a string.', 'updatepulse-server' );
			}

			if (
				! ( $partial && ! isset( $license['txn_id'] ) ) &&
				! empty( $license['txn_id'] ) &&
				! is_string( $license['txn_id'] )
			) {
				$errors['invalid_txn_id'] = __( 'The transaction ID must be a string.', 'updatepulse-server' );
			}

			if ( ! ( $partial && ! isset( $license['allowed_domains'] ) ) ) {

				if ( ! is_array( $license['allowed_domains'] ) ) {
					$errors[] = __( 'The allowed domains must be an array.', 'updatepulse-server' );
				} elseif ( ! empty( $license['allowed_domains'] ) ) {

					foreach ( $license['allowed_domains'] as $value ) {

						if ( ! is_scalar( $value ) || 5 > strlen( strval( $value ) ) ) {
							$errors['invalid_domain'] = __( 'All allowed domains values must be scalar with a string-equivalent length superior to 5 characters.', 'updatepulse-server' );

							break;
						}
					}
				}
			}
		}

		if ( ! empty( $errors ) ) {
			$return = $errors;
		}

		return $return;
	}
}
