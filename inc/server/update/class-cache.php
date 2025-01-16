<?php

namespace Anyape\UpdatePulse\Server\Server\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * A simple file-based cache.
 *
 */
class Cache {

	protected $cache_directory;

	public function __construct( $cache_directory ) {
		$this->cache_directory = $cache_directory;
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function get( $key ) {
		$filename = $this->get_cache_filename( $key );

		if ( is_file( $filename ) && is_readable( $filename ) ) {
			$cache = unserialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				base64_decode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					file_get_contents( $filename ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				)
			);

			if ( $cache['expiration_time'] < time() ) {
				/* Could cause potential non-critical race condition */
				$this->clear( $key );

				return null; //Cache expired.
			} else {
				return $cache['value'];
			}
		}

		return null;
	}

	/**
	 * Update the cache.
	 *
	 * @param string $key Cache key.
	 * @param mixed $value The value to store in the cache.
	 * @param int $expiration Time until expiration, in seconds. Optional.
	 * @return void
	 */
	public function set( $key, $value, $expiration = 0 ) {
		$cache = array(
			'expiration_time' => time() + $expiration,
			'value'           => $value,
		);

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->get_cache_filename( $key ),
			base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				serialize( $cache ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			)
		);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function get_cache_filename( $key ) {
		return $this->cache_directory . '/' . $key . '.txt';
	}

	/**
	 * Clear a cache.
	 *
	 * @param string $key Cache key.
	 * @param bool $already_locked Whether the file is already locked. Optional. Default `false`.
	 * @return void
	 */
	public function clear( $key ) {
		$file = $this->get_cache_filename( $key );

		if ( is_file( $file ) ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
}
