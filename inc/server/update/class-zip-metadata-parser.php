<?php

namespace Anyape\UpdatePulse\Server\Server\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Anyape\UpdatePulse\Package_Parser\Parser;

/**
 * This class represents the metadata from one specific package.
 */
class Zip_Metadata_Parser {

	/**
	 * Cache time
	 *
	 * How long the package metadata should be cached in seconds.
	 * Defaults to 1 week ( 7 * 24 * 60 * 60 ).
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public static $cache_time = 604800;

	/**
	 * Header map
	 *
	 * Package PHP header mapping, i.e. which tags to add to the metadata under which array key.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $header_map = array(
		'Name'        => 'name',
		'Version'     => 'version',
		'PluginURI'   => 'homepage',
		'ThemeURI'    => 'homepage',
		'Homepage'    => 'homepage',
		'Author'      => 'author',
		'AuthorURI'   => 'author_homepage',
		'RequiresPHP' => 'requires_php',
		'Description' => 'description',
		'DetailsURI'  => 'details_url',
		'Depends'     => 'depends',
		'Provides'    => 'provides',
	);
	/**
	 * Readme map
	 *
	 * Plugin readme file mapping, i.e. which tags to add to the metadata.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $readme_map = array(
		'requires',
		'tested',
		'requires_php',
	);
	/**
	 * Package info
	 *
	 * Package info as retrieved by the parser.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $package_info;
	/**
	 * Filename
	 *
	 * Path to the Zip archive that contains the package.
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $filename;
	/**
	 * Slug
	 *
	 * Package slug.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $slug;
	/**
	 * Cache
	 *
	 * Cache object.
	 *
	 * @var Cache|null
	 * @since 1.0.0
	 */
	protected $cache;
	/**
	 * Metadata
	 *
	 * Package metadata in a format suitable for the update checker.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $metadata;


	/**
	 * Constructor
	 *
	 * Get the metadata from a zip file.
	 *
	 * @param string|null $slug Package slug.
	 * @param string      $filename Path to the Zip archive.
	 * @param Cache|null  $cache Cache object.
	 * @since 1.0.0
	 */
	public function __construct( $slug, $filename, $cache = null ) {
		$this->slug     = $slug;
		$this->filename = $filename;
		$this->cache    = $cache;

		$this->set_metadata();
	}

	/**
	 * Build cache key
	 *
	 * Build the cache key (cache filename) for a file.
	 *
	 * @param string $slug Package slug.
	 * @param string $filename Path to the Zip archive.
	 * @return string The cache key.
	 * @since 1.0.0
	 */
	public static function build_cache_key( $slug, $filename ) {
		$cache_key = $slug . '-b64-';

		if ( file_exists( $filename ) ) {
			$cache_key .= md5( $filename . '|' . filesize( $filename ) . '|' . filemtime( $filename ) );
		}

		/**
		 * Filter the cache key used for storing package metadata.
		 *
		 * @param string $cache_key The generated cache key for the package.
		 * @param string $slug      The package slug.
		 * @param string $filename  The path to the Zip archive.
		 * @return string The filtered cache key.
		 * @since 1.0.0
		 */
		return apply_filters(
			'upserv_zip_metadata_parser_cache_key',
			$cache_key,
			$slug,
			$filename
		);
	}

	/**
	 * Get metadata
	 *
	 * Get the package metadata.
	 *
	 * @return array Package metadata.
	 * @since 1.0.0
	 */
	public function get() {
		return $this->metadata;
	}

	/**
	 * Set metadata
	 *
	 * Load metadata information from a cache or create it.
	 *
	 * We'll try to load processed metadata from the cache first (if available), and if that
	 * fails we'll extract package details from the specified Zip file.
	 *
	 * @since 1.0.0
	 */
	protected function set_metadata() {
		$cache_key = self::build_cache_key( $this->slug, $this->filename );

		// Try the cache first.
		if ( isset( $this->cache ) ) {
			$this->metadata = $this->cache->get( $cache_key );
		}

		// Otherwise read out the metadata and create a cache
		if ( ! isset( $this->metadata ) || ! is_array( $this->metadata ) ) {
			$this->extract_metadata();

			// Enforce all the values in the metadata array to be strings when scalar
			array_walk_recursive(
				$this->metadata,
				function ( &$value ) {

					if ( is_scalar( $value ) ) {
						$value = (string) $value;
					}
				}
			);

			// Update cache.
			if ( isset( $this->cache ) ) {
				$this->cache->set( $cache_key, $this->metadata, static::$cache_time );
			}
		}
	}

	/**
	 * Extract metadata
	 *
	 * Extract package headers and readme contents from a ZIP file and convert them
	 * into a structure compatible with the custom update checker.
	 *
	 * @throws Invalid_Package_Exception if the input file can't be parsed as a package.
	 * @since 1.0.0
	 */
	protected function extract_metadata() {
		$this->package_info = Parser::parse_package( $this->filename, true );

		if ( is_array( $this->package_info ) && ! empty( $this->package_info ) ) {
			$this->set_info_from_header();
			$this->set_info_from_readme();
			$this->set_info_from_assets();
			$this->set_slug();
			$this->set_type();
			$this->set_last_update_date();
		} else {
			throw new Invalid_Package_Exception(
				sprintf(
					'The specified file %s does not contain a valid package.',
					esc_html( $this->filename )
				)
			);
		}
	}

	/**
	 * Set info from header
	 *
	 * Extract relevant metadata from the package header information.
	 *
	 * @since 1.0.0
	 */
	protected function set_info_from_header() {

		if ( isset( $this->package_info['header'] ) && ! empty( $this->package_info['header'] ) ) {
			$this->set_mapped_fields( $this->package_info['header'], $this->header_map );
			$this->set_theme_details_url();
		}
	}

	/**
	 * Set info from readme
	 *
	 * Extract relevant metadata from the plugin readme.
	 *
	 * @since 1.0.0
	 */
	protected function set_info_from_readme() {

		if ( ! empty( $this->package_info['readme'] ) ) {
			$readme_map = array_combine( array_values( $this->readme_map ), $this->readme_map );

			$this->set_mapped_fields( $this->package_info['readme'], $readme_map );
			$this->set_readme_sections();
			$this->set_readme_upgrade_notice();
		}
	}

	/**
	 * Set mapped fields
	 *
	 * Extract selected metadata from the retrieved package info.
	 *
	 * @see http://codex.wordpress.org/File_Header
	 * @see https://wordpress.org/plugins/about/readme.txt
	 *
	 * @param array $input The package info sub-array to use to retrieve the info from.
	 * @param array $map The key mapping for that sub-array where the key is the key as used in the
	 *                    input array and the value is the key to use for the output array.
	 * @since 1.0.0
	 */
	protected function set_mapped_fields( $input, $map ) {

		foreach ( $map as $field_key => $meta_key ) {

			if ( ! empty( $input[ $field_key ] ) ) {
				$this->metadata[ $meta_key ] = (string) $input[ $field_key ];
			}
		}
	}

	/**
	 * Set the package details URL fallback.
	 *
	 * Use the package homepage when no explicit details URL is available.
	 *
	 * Package metadata may include a "details_url" that specifies the page to display
	 * for additional details. If it is absent, default to the package homepage.
	 *
	 * @since 1.0.0
	 */
	protected function set_theme_details_url() {

		if (
			! isset( $this->metadata['details_url'] ) &&
			isset( $this->metadata['homepage'] )
		) {
			$this->metadata['details_url'] = $this->metadata['homepage'];
		}
	}

	/**
	 * Set readme sections
	 *
	 * Extract the textual information sections from a readme file.
	 *
	 * @see https://wordpress.org/plugins/about/readme.txt
	 * @since 1.0.0
	 */
	protected function set_readme_sections() {

		if (
			is_array( $this->package_info['readme']['sections'] ) &&
			! empty( $this->package_info['readme']['sections'] )
		) {

			foreach ( $this->package_info['readme']['sections'] as $section_name => $section_content ) {
				$section_content                             = '<div class="readme-section" data-name="'
					. $section_name
					. '">'
					. $section_content
					. '</div>';
				$section_name                                = str_replace( ' ', '_', strtolower( $section_name ) );
				$this->metadata['sections'][ $section_name ] = $section_content;
			}
		}
	}

	/**
	 * Set readme upgrade notice
	 *
	 * Extract the upgrade notice for the current version from a readme file.
	 *
	 * @see https://wordpress.org/plugins/about/readme.txt
	 * @since 1.0.0
	 */
	protected function set_readme_upgrade_notice() {

		// Check if we have an upgrade notice for this version
		if ( isset( $this->metadata['sections']['upgrade_notice'] ) && isset( $this->metadata['version'] ) ) {
			$regex = '@<h4>\s*'
				. preg_quote( $this->metadata['version'], '@' )
				. '\s*</h4>[^<>]*?<p>( .+? )</p>@i';

			if ( preg_match( $regex, $this->metadata['sections']['upgrade_notice'], $matches ) ) {
				$this->metadata['upgrade_notice'] = trim( wp_strip_all_tags( $matches[1] ) );
			}
		}
	}

	/**
	 * Set last update date
	 *
	 * Add last update date to the metadata; this is tied to the version.
	 *
	 * @since 1.0.0
	 */
	protected function set_last_update_date() {

		if ( isset( $this->metadata['last_updated'] ) ) {
			return;
		}

		$meta = upserv_get_package_metadata( $this->slug );

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		if (
			! isset( $meta['version'], $meta['version_time'] ) ||
			$meta['version'] !== $this->metadata['version']
		) {
			$meta['version']      = $this->metadata['version'];
			$meta['version_time'] = gmdate( 'Y-m-d H:i:s', filemtime( $this->filename ) );

			upserv_set_package_metadata( $this->slug, $meta );
		}

		$this->metadata['last_updated'] = $meta['version_time'];
	}

	/**
	 * Set type
	 *
	 * Set the package type in the metadata.
	 *
	 * @since 1.0.0
	 */
	protected function set_type() {
		$this->metadata['type'] = $this->package_info['type'];
	}

	/**
	 * Set slug
	 *
	 * Set the package slug in the metadata.
	 *
	 * @since 1.0.0
	 */
	protected function set_slug() {

		if ( 'plugin' === $this->package_info['type'] ) {
			$main_file = $this->package_info['plugin_file'];
		} elseif ( 'theme' === $this->package_info['type'] ) {
			$main_file = $this->package_info['stylesheet'];
		} elseif ( 'generic' === $this->package_info['type'] ) {
			$main_file = $this->package_info['generic_file'];
		}

		$this->metadata['slug'] = $main_file ? basename( dirname( strtolower( $main_file ) ) ) : '';
	}

	/**
	 * Set info from assets
	 *
	 * Extract icons and banners info for plugins.
	 *
	 * @since 1.0.0
	 */
	protected function set_info_from_assets() {

		if ( ! empty( $this->package_info['extra'] ) ) {
			$extra_meta = $this->package_info['extra'];

			if ( ! empty( $extra_meta['icons'] ) ) {
				$this->metadata['icons'] = $extra_meta['icons'];
			}

			if ( ! empty( $extra_meta['banners'] ) ) {
				$this->metadata['banners'] = $extra_meta['banners'];
			}

			$this->metadata['require_license'] = (
				! empty( $extra_meta['require_license'] ) &&
				(
					'yes' === $extra_meta['require_license'] ||
					'true' === $extra_meta['require_license'] ||
					1 === intval( $extra_meta['require_license'] )
				)
			);

			if ( ! empty( $extra_meta['licensed_with'] ) ) {
				$this->metadata['licensed_with'] = $extra_meta['licensed_with'];
			}
		}
	}
}
