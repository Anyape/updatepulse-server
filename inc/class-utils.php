<?php

namespace Anyape\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Utils
 *
 * @package Anyape\Utils
 * @since 1.0.0
 */
class Utils {

	/**
	 * JSON encoding options
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

	/**
	 * Log a message to PHP error log
	 *
	 * Adds class/method context information to the log message.
	 *
	 * @param mixed  $message Value to log.
	 * @param string $prefix Optional prefix for the log message
	 * @since 1.0.0
	 */
	public static function php_log( $message = '', $prefix = '' ) {
		$prefix   = $prefix ? ' ' . $prefix . ' => ' : ' => ';
		$trace    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$caller   = end( $trace );
		$class    = isset( $caller['class'] ) ? $caller['class'] : '';
		$type     = isset( $caller['type'] ) ? $caller['type'] : '';
		$function = isset( $caller['function'] ) ? $caller['function'] : '';
		$context  = $class . $type . $function . $prefix;

		error_log( $context . print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Check if IP address is within CIDR range
	 *
	 * Validates whether a given IP address falls within the specified CIDR range.
	 *
	 * @param string $ip IP address to check
	 * @param string $range CIDR range notation (e.g., 192.168.1.0/24)
	 * @return bool True if IP is in range, false otherwise
	 * @since 1.0.0
	 */
	public static function cidr_match( $ip, $range ) {
		list ( $subnet, $bits ) = explode( '/', $range );
		$ip                     = ip2long( $ip );
		$subnet                 = ip2long( $subnet );

		if ( ! $ip || ! $subnet || ! $bits ) {
			return false;
		}

		$mask    = -1 << ( 32 - $bits );
		$subnet &= $mask; // in case the supplied subnet was not correctly aligned

		return ( $ip & $mask ) === $subnet;
	}

	/**
	 * Access or update nested array using path notation
	 *
	 * Gets or sets a value in a nested array using a path string with / as separator.
	 *
	 * @param array  $_array Reference to the array to access
	 * @param string $path Path notation to the nested element (e.g., 'parent/child/item')
	 * @param mixed  $value Optional value to set if updating
	 * @param bool   $update Whether to update the array (true) or just read (false)
	 * @return mixed|null Retrieved value or null if path doesn't exist
	 * @since 1.0.0
	 */
	public static function access_nested_array( &$_array, $path, $value = null, $update = false ) {
		$keys    = explode( '/', $path );
		$current = &$_array;

		foreach ( $keys as $key ) {

			if ( ! isset( $current[ $key ] ) ) {

				if ( $update ) {
					$current[ $key ] = array();
				} else {
					return null;
				}
			}

			$current = &$current[ $key ];
		}

		if ( $update ) {
			$current = $value;
		}

		return $current;
	}

	/**
	 * Check if URL subpath matches a regex pattern
	 *
	 * Tests if the first segment of the current request URI matches the provided regex.
	 *
	 * @param string $regex Regular expression to match against the first path segment
	 * @return int|null 1 if match found, 0 if no match, null if host couldn't be determined
	 * @since 1.0.0
	 */
	public static function is_url_subpath_match( $regex ) {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : false;

		if ( ! $host && isset( $_SERVER['SERVER_NAME'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
		}

		if ( ! $host || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		$url   = sanitize_url( 'https://' . $host . wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path  = str_replace( trailingslashit( home_url() ), '', $url );
		$frags = explode( '/', $path );

		return preg_match( $regex, $frags[0] );
	}

	/**
	 * Get time elapsed since request start
	 *
	 * Calculates the time elapsed since the request started in seconds.
	 *
	 * @return string|null Time elapsed with an `s` suffix and three decimal places, or null when unavailable.
	 * @since 1.0.0
	 */
	public static function get_time_elapsed() {

		if ( empty( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
			return null;
		}

		$req_time_float = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME_FLOAT'] ) );

		if ( ! is_numeric( $req_time_float ) ) {
			return null;
		}

		return (string) sprintf( '%.3fs', microtime( true ) - $req_time_float );
	}

	/**
	 * Get remote IP address
	 *
	 * Safely retrieves the remote IP address of the client.
	 *
	 * @return string IP address of the client or '0.0.0.0' if not available or invalid
	 * @since 1.0.0
	 */
	public static function get_remote_ip() {

		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '0.0.0.0';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Get human-readable status string
	 *
	 * Converts a status code to a localized human-readable string.
	 *
	 * @param string $status Status code to convert
	 * @return string Localized human-readable status string
	 * @since 1.0.0
	 */
	public static function get_status_string( $status ) {
		switch ( $status ) {
			case 'pending':
				return __( 'Pending', 'updatepulse-server' );
			case 'activated':
				return __( 'Activated', 'updatepulse-server' );
			case 'deactivated':
				return __( 'Deactivated', 'updatepulse-server' );
			case 'on-hold':
				return __( 'On Hold', 'updatepulse-server' );
			case 'blocked':
				return __( 'Blocked', 'updatepulse-server' );
			case 'expired':
				return __( 'Expired', 'updatepulse-server' );
			default:
				return __( 'N/A', 'updatepulse-server' );
		}
	}
}
