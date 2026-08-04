<?php

namespace Anyape\UpdatePulse\Server\Server\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use ArrayAccess;
use IteratorAggregate;
use Countable;
use ArrayIterator;
use Traversable;

/**
 * Headers class
 *
 * @since 1.0.0
 */
class Headers implements ArrayAccess, IteratorAggregate, Countable {

	/**
	 * HTTP headers stored in the $_SERVER array are usually prefixed with "HTTP_" or "X_".
	 * These special headers don't have that prefix, so we need an explicit list to identify them.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected static $unprefixed_names = array(
		'CONTENT_TYPE',
		'CONTENT_LENGTH',
		'PHP_AUTH_USER',
		'PHP_AUTH_PW',
		'PHP_AUTH_DIGEST',
		'AUTH_TYPE',
	);

	/**
	 * Headers collection
	 *
	 * Stores all HTTP headers.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $headers = array();

	/**
	 * Constructor
	 *
	 * Initialize headers from provided array.
	 *
	 * @param array $headers Initial headers to set.
	 * @since 1.0.0
	 */
	public function __construct( $headers = array() ) {

		foreach ( $headers as $name => $value ) {
			$this->set( $name, $value );
		}
	}

	/**
	 * Extract HTTP headers from an array of data ( usually $_SERVER ).
	 *
	 * @return array Extracted HTTP headers.
	 * @since 1.0.0
	 */
	protected static function parse_server() {
		$results     = array();
		$environment = $_SERVER;

		foreach ( $environment as $key => $value ) {
			$key = strtoupper( $key );

			if ( self::is_header_name( $key ) ) {
				// Remove the "HTTP_" prefix that PHP adds to headers stored in $_SERVER.
				$key = preg_replace( '/^HTTP[_-]/', '', $key );
				// Assign a sanitized value to the parsed results.
				$results[ $key ] = null !== $value ? wp_kses_post( $value ) : $value;
			}
		}

		return $results;
	}

	/**
	 * Check if a $_SERVER key looks like a HTTP header name.
	 *
	 * @param string $key The key to check.
	 * @return bool Whether the key is a HTTP header name.
	 * @since 1.0.0
	 */
	protected static function is_header_name( $key ) {
		return (
			self::starts_with( $key, 'X_' ) ||
			self::starts_with( $key, 'HTTP_' ) ||
			in_array( $key, static::$unprefixed_names, true )
		);
	}

	/**
	 * Parse headers for the current HTTP request.
	 * Will automatically choose the best way to get the headers from PHP.
	 *
	 * @return array HTTP headers from the current request.
	 * @since 1.0.0
	 */
	public static function parse_current() {

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();

			if ( false !== $headers ) {
				return $headers;
			}
		}

		return self::parse_server();
	}

	/**
	 * Convert a header name to "Title-Case-With-Dashes".
	 *
	 * @param string $name Header name to normalize.
	 * @return string Normalized header name.
	 * @since 1.0.0
	 */
	protected function normalize_name( $name ) {
		$name = strtolower( $name );
		$name = str_replace( array( '_', '-' ), ' ', $name );
		$name = ucwords( $name );
		$name = str_replace( ' ', '-', $name );

		return $name;
	}

	/**
	 * Check if a string starts with the given prefix.
	 *
	 * @param string $_string The string to check.
	 * @param string $prefix The prefix to look for.
	 * @return bool Whether the string starts with the prefix.
	 * @since 1.0.0
	 */
	protected static function starts_with( $_string, $prefix ) {
		return ( substr( $_string, 0, strlen( $prefix ) ) === $prefix );
	}

	/**
	 * Get the value of a HTTP header.
	 *
	 * @param string $name Header name.
	 * @param mixed  $_default The default value to return if the header doesn't exist.
	 * @return mixed Header value or the supplied default.
	 * @since 1.0.0
	 */
	public function get( $name, $_default = null ) {
		$name = $this->normalize_name( $name );

		if ( isset( $this->headers[ $name ] ) ) {
			return $this->headers[ $name ];
		}

		return $_default;
	}

	/**
	 * Set a header to value.
	 *
	 * @param string $name Header name.
	 * @param string $value Header value.
	 * @since 1.0.0
	 */
	public function set( $name, $value ) {
		$name                   = $this->normalize_name( $name );
		$this->headers[ $name ] = $value;
	}

	/* ArrayAccess interface */

	/**
	 * Check if header exists
	 *
	 * Implementation for ArrayAccess interface.
	 *
	 * @param mixed $offset The header name.
	 * @return bool Whether the header exists.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->headers );
	}

	/**
	 * Get header value
	 *
	 * Implementation for ArrayAccess interface.
	 *
	 * @param mixed $offset The header name.
	 * @return mixed The header value.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ): mixed {
		return $this->get( $offset );
	}

	/**
	 * Set header value
	 *
	 * Implementation for ArrayAccess interface.
	 *
	 * @param mixed $offset The header name.
	 * @param mixed $value The header value.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ): void {
		$this->set( $offset, $value );
	}

	/**
	 * Unset header
	 *
	 * Implementation for ArrayAccess interface.
	 *
	 * @param mixed $offset The header name.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ): void {
		$name = $this->normalize_name( $offset );
		unset( $this->headers[ $name ] );
	}

	/**
	 * Count headers
	 *
	 * Implementation for Countable interface.
	 *
	 * @return int Number of headers.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function count(): int {
		return count( $this->headers );
	}

	/**
	 * Get iterator for headers
	 *
	 * Implementation for IteratorAggregate interface.
	 *
	 * @return Traversable Iterator for headers.
	 * @since 1.0.0
	 */
	#[\ReturnTypeWillChange]
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->headers );
	}
}
