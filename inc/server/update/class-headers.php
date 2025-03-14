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

class Headers implements ArrayAccess, IteratorAggregate, Countable {

	/**
	 * HTTP headers stored in the $_SERVER array are usually prefixed with "HTTP_" or "X_".
	 * These special headers don't have that prefix, so we need an explicit list to identify them.
	 *
	 * @var array
	 */
	protected static $unprefixed_names = array(
		'CONTENT_TYPE',
		'CONTENT_LENGTH',
		'PHP_AUTH_USER',
		'PHP_AUTH_PW',
		'PHP_AUTH_DIGEST',
		'AUTH_TYPE',
	);

	protected $headers = array();

	public function __construct( $headers = array() ) {

		foreach ( $headers as $name => $value ) {
			$this->set( $name, $value );
		}
	}

	/**
	 * Extract HTTP headers from an array of data ( usually $_SERVER ).
	 *
	 * @param array $environment
	 * @return array
	 */
	protected static function parse_server() {
		$results     = array();
		$environment = $_SERVER;

		foreach ( $environment as $key => $value ) {
			$key = strtoupper( $key );

			if ( self::is_header_name( $key ) ) {
				//Remove the "HTTP_" prefix that PHP adds to headers stored in $_SERVER.
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
	 * @param string $key
	 * @return bool
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
	 * @return array
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
	 * @param string $name
	 * @return string
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
	 * @param string $string
	 * @param string $prefix
	 * @return bool
	 */
	protected static function starts_with( $_string, $prefix ) {
		return ( substr( $_string, 0, strlen( $prefix ) ) === $prefix );
	}

	/**
	 * Get the value of a HTTP header.
	 *
	 * @param string $name Header name.
	 * @param mixed $_default The default value to return if the header doesn't exist.
	 * @return string|null
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
	 * @param string $name
	 * @param string $value
	 */
	public function set( $name, $value ) {
		$name                   = $this->normalize_name( $name );
		$this->headers[ $name ] = $value;
	}

	/* ArrayAccess interface */

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->headers );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ): mixed {
		return $this->get( $offset );
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ): void {
		$this->set( $offset, $value );
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ): void {
		$name = $this->normalize_name( $offset );
		unset( $this->headers[ $name ] );
	}

	/* Countable interface */
	#[\ReturnTypeWillChange]
	public function count(): int {
		return count( $this->headers );
	}

	/* IteratorAggregate interface  */
	#[\ReturnTypeWillChange]
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->headers );
	}
}
