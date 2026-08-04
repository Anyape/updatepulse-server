<?php

namespace Anyape\UpdatePulse\Server\Server\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * This class represents the collection of files and metadata that make up
 * a WordPress plugin or theme, or a generic software package.
 *
 * @since 1.0.0
 */
class Package {

	/**
	 * Path to the Zip archive that contains the package.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $filename;

	/**
	 * Package metadata in a format suitable for the update checker.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $metadata = array();

	/**
	 * Package slug.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $slug;

	/**
	 * Create a new package.
	 *
	 * In most cases you will probably want to use self::fromArchive($package) instead
	 * of instantiating this class directly. Still, you can do it if you want to, for example,
	 * load package metadata from the database instead of extracting it from a Zip file.
	 *
	 * @param string      $slug The package slug.
	 * @param string|null $filename The path to the package file.
	 * @param array       $metadata The package metadata.
	 * @since 1.0.0
	 */
	public function __construct( $slug, $filename = null, $metadata = array() ) {
		$this->slug     = $slug;
		$this->filename = $filename;
		$this->metadata = $metadata;
	}

	/**
	 * Get the full file path of this package.
	 *
	 * @return string|null The full file path of the package.
	 * @since 1.0.0
	 */
	public function get_filename() {
		return $this->filename;
	}

	/**
	 * Get package metadata.
	 *
	 * @return array The package metadata merged with the slug.
	 * @since 1.0.0
	 */
	public function get_metadata() {
		return array_merge( $this->metadata, array( 'slug' => $this->slug ) );
	}

	/**
	 * Load package information.
	 *
	 * @param string      $filename Path to a Zip archive that contains a package.
	 * @param string|null $slug Optional package slug. Will be detected automatically.
	 * @param Cache|null  $cache Optional cache object for metadata.
	 * @return Package A new Package instance with the extracted metadata.
	 * @since 1.0.0
	 */
	public static function from_archive( $filename, $slug = null, $cache = null ) {
		$meta_obj = new Zip_Metadata_Parser( $slug, $filename, $cache );
		$metadata = $meta_obj->get();

		if ( null === $slug && isset( $metadata['slug'] ) ) {
			$slug = $metadata['slug'];
		}

		return new self( $slug, $filename, $metadata );
	}

	/**
	 * Get the size of the package (in bytes).
	 *
	 * @return int|false The size of the package file in bytes, or false on failure.
	 * @since 1.0.0
	 */
	public function get_file_size() {
		return filesize( $this->filename );
	}

	/**
	 * Get the Unix timestamp of the last time this package was modified.
	 *
	 * @return int|false The Unix timestamp when the package was last modified, or false on failure.
	 * @since 1.0.0
	 */
	public function get_last_modified() {
		return filemtime( $this->filename );
	}
}
