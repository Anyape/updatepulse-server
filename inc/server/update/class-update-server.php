<?php

namespace Anyape\UpdatePulse\Server\Server\Update;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use DateTime;
use DateTimeZone;
use WP_Error;
use Exception;
use Anyape\UpdatePulse\Package_Parser\Parser as Package_Parser;
use Anyape\UpdatePulse\Server\Server\Update\Cache;
use Anyape\UpdatePulse\Server\Server\Update\Package;
use Anyape\UpdatePulse\Server\Server\Update\Request;
use Anyape\UpdatePulse\Server\Server\Update\Headers;
use Anyape\UpdatePulse\Server\Server\Update\Invalid_Package_Exception;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Manager\Zip_Package_Manager;
use Anyape\UpdatePulse\Server\Server\License\License_Server;
use Anyape\Utils\Utils;

/**
 * Update Server class
 *
 * @since 1.0.0
 */
class Update_Server {

	/**
	 * Lock duration for remote updates in seconds
	 *
	 * @var int
	 * @since 1.0.0
	 */
	const LOCK_REMOTE_UPDATE_SEC = 10;

	/**
	 * Directory for package storage
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $package_dir;
	/**
	 * Directory for log storage
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $log_dir;
	/**
	 * Cache instance
	 *
	 * @var Cache
	 * @since 1.0.0
	 */
	protected $cache;
	/**
	 * Server URL
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $server_url;
	/**
	 * Server timezone
	 *
	 * @var DateTimeZone
	 * @since 1.0.0
	 */
	protected $timezone;
	/**
	 * Server directory
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $server_dir;
	/**
	 * Version control system URL
	 *
	 * @var string|false
	 * @since 1.0.0
	 */
	protected $vcs_url;
	/**
	 * Branch name
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $branch;
	/**
	 * VCS credentials
	 *
	 * @var array|string|null
	 * @since 1.0.0
	 */
	protected $credentials;
	/**
	 * Version control system type
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $vcs_type;
	/**
	 * Whether VCS is self-hosted
	 *
	 * @var bool|null
	 * @since 1.0.0
	 */
	protected $self_hosted;
	/**
	 * Update checker instance
	 *
	 * @var object|null
	 * @since 1.0.0
	 */
	protected $update_checker;
	/**
	 * Package type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $type;
	/**
	 * Content of the packages file for filtering
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $filter_packages_file_content;
	/**
	 * License key
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $license_key;
	/**
	 * License signature
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected $license_signature;

	/**
	 * Constructor
	 *
	 * @param string            $server_url The server URL
	 * @param string            $server_dir The server directory
	 * @param string|false      $vcs_url The VCS URL
	 * @param string|null       $branch The branch name.
	 * @param array|string|null $credentials The VCS credentials.
	 * @param string|null       $vcs_type The VCS type.
	 * @param bool|null         $self_hosted Whether VCS is self-hosted.
	 * @since 1.0.0
	 */
	public function __construct( $server_url, $server_dir, $vcs_url, $branch, $credentials, $vcs_type, $self_hosted ) {
		$this->server_dir  = $this->normalize_file_path( untrailingslashit( $server_dir ) );
		$this->server_url  = $server_url;
		$this->package_dir = $server_dir . 'packages';
		$this->log_dir     = $server_dir . 'logs';
		$this->cache       = new Cache( $server_dir . 'cache' );
		$this->timezone    = new DateTimeZone( wp_timezone_string() );
		$this->server_dir  = $server_dir;
		$this->vcs_type    = $vcs_type;
		$this->self_hosted = $self_hosted;
		$this->vcs_url     = $vcs_url ? trailingslashit( $vcs_url ) : false;
		$this->branch      = $branch;
		$this->credentials = $credentials;
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Process an update API request
	 *
	 * Handles incoming update requests by initializing, validating, and dispatching them.
	 *
	 * @param array $query Query parameters
	 * @since 1.0.0
	 */
	public function handle_request( $query ) {
		$request = $this->init_request( $query );

		$this->log_request( $request );
		$this->load_package_for( $request );
		$this->validate_request( $request );
		$this->check_authorization( $request );
		$this->dispatch( $request );

		exit;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Get VCS URL
	 *
	 * @return string|false The VCS URL
	 * @since 1.0.0
	 */
	public function get_vcs_url() {
		return $this->vcs_url;
	}

	/**
	 * Get branch name
	 *
	 * @return string The branch name
	 * @since 1.0.0
	 */
	public function get_branch() {
		return $this->branch;
	}

	/**
	 * Pre-filter package information
	 *
	 * Filter package information before it's processed by the update system.
	 *
	 * @param array  $info Package information
	 * @param object $api API instance
	 * @param mixed  $ref Reference
	 * @return array Filtered package information
	 * @since 1.0.0
	 */
	public function pre_filter_package_info( $info, $api, $ref ) {
		$abort = true;
		/**
		 * Filters the name of the file used to filter the packages retrieved from the VCS
		 *
		 * @param string $file_name The name of the file used to filter the packages retrieved from the VCS
		 * @return string Modified file name
		 */
		$_file        = apply_filters( 'upserv_filter_packages_filename', 'updatepulse.json' );
		$file_content = $api->get_remote_file( $_file, $ref );

		$this->filter_packages_file_content = $file_content;

		if ( ! empty( $file_content ) ) {
			/**
			 * Filters package information before processing
			 *
			 * Allows modification of package data before it's processed by the update system.
			 *
			 * @param array $info The package information array
			 * @param string $file_content The content of the updatepulse.json file
			 * @return array Modified package information
			 */
			$info          = apply_filters( 'upserv_pre_filter_package_info', $info, $file_content );
			$file_contents = json_decode( $file_content, true );

			if (
				is_array( $info ) &&
				! isset( $info['abort_request'] ) &&
				$file_contents &&
				isset( $file_contents['server'] )
			) {
				$url              = filter_var( $file_contents['server'], FILTER_VALIDATE_URL );
				$server_url_parts = explode( '/', untrailingslashit( $this->server_url ) );

				array_pop( $server_url_parts );

				if ( $url ) {
					$info['server'] = trailingslashit( $url );
				}

				$server_url = implode( '/', $server_url_parts );
				$abort      = ! ( $url && trailingslashit( $server_url ) === trailingslashit( $url ) );
			}
		}

		if ( $abort ) {

			if ( is_array( $info ) ) {
				$info['abort_request'] = true;
			} else {
				$info = array( 'abort_request' => true );
			}
		}

		/**
		 * Fires after pre-filtering package information
		 *
		 * Allows developers to perform actions after the package information has been initially filtered.
		 *
		 * @param array $info The package information array after pre-filtering
		 */
		do_action( 'upserv_pre_filter_package_info', $info );

		return $info;
	}

	/**
	 * Filter package information
	 *
	 * Apply filters to package information after processing.
	 *
	 * @param array $info Package information
	 * @return array Filtered package information
	 * @since 1.0.0
	 */
	public function filter_package_info( $info ) {
		/**
		 * Filters package information after processing
		 *
		 * Allows modification of package data after initial processing.
		 *
		 * @param array $info The package information array
		 * @param string $filter_packages_file_content The content of the packages file
		 * @return array Modified package information
		 */
		$info = apply_filters( 'upserv_filter_package_info', $info, $this->filter_packages_file_content );

		/**
		 * Fires after filtering package information
		 *
		 * Allows developers to perform actions after the package information has been filtered.
		 *
		 * @param array $info The package information array after filtering
		 */
		do_action( 'upserv_filter_package_info', $info );

		return $info;
	}

	/**
	 * Save remote package to local storage
	 *
	 * Download and save a package from remote repository to local storage.
	 *
	 * @param string $safe_slug Sanitized package slug
	 * @param bool   $force Whether to force update even if locked
	 * @return bool|array|WP_Error Whether the package was saved, or update information when the download is aborted.
	 * @since 1.0.0
	 */
	public function save_remote_package_to_local( $safe_slug, $force = false ) {
		$local_ready = false;

		if ( $force ) {
			self::unlock_update_from_remote( $safe_slug );
		}

		if ( ! self::is_update_from_remote_locked( $safe_slug ) ) {
			self::lock_update_from_remote( $safe_slug );
			$this->init_update_checker( $safe_slug );

			if ( $this->update_checker ) {

				try {
					$info = $this->update_checker->request_info();

					/**
					 * Filters whether to download a remote package
					 *
					 * Allows plugins to control whether a package should be downloaded from remote repository.
					 *
					 * @param bool $download Whether to download the package
					 * @param string $safe_slug The sanitized package slug
					 * @param string $type The package type
					 * @param array $info The package information
					 * @return bool Whether to proceed with download
					 */
					if ( ! apply_filters(
						'upserv_download_remote_package',
						! ( is_array( $info ) && isset( $info['abort_request'] ) && $info['abort_request'] ),
						$safe_slug,
						$this->type,
						$info
					) ) {
						$this->remove_package( $safe_slug, true );

						/**
						 * Fires when a remote package download is aborted
						 *
						 * @param string $safe_slug The sanitized package slug
						 * @param string $type The package type
						 * @param array|WP_Error $info The package information or update-check error.
						 */
						do_action( 'upserv_download_remote_package_aborted', $safe_slug, $this->type, $info );

						return $info;
					}

					if ( $info && ! is_wp_error( $info ) ) {
						$this->remove_package( $safe_slug, true );

						$package = $this->download_remote_package( $info['download_url'] );

						/**
						 * Fires after a remote package has been downloaded
						 *
						 * @param string|WP_Error $package Path to the downloaded package file, or a download error.
						 * @param string $type The package type
						 * @param string $safe_slug The sanitized package slug
						 */
						do_action( 'upserv_downloaded_remote_package', $package, $info['type'], $safe_slug );

						$package_manager = new Zip_Package_Manager(
							$safe_slug,
							$package,
							Data_Manager::get_data_dir( 'tmp' ),
							Data_Manager::get_data_dir( 'packages' )
						);
						$local_ready     = $package_manager->clean_package();

						/**
						 * Fires after a remote package has been saved to local storage
						 *
						 * @param bool $local_ready Whether the package was successfully saved locally
						 * @param string $type The package type
						 * @param string $safe_slug The sanitized package slug
						 */
						do_action(
							'upserv_saved_remote_package_to_local',
							$local_ready,
							$info['type'],
							$safe_slug
						);
					}
				} catch ( Exception $e ) {
					self::unlock_update_from_remote( $safe_slug );

					throw $e;
				}
			}

			self::unlock_update_from_remote( $safe_slug );
		}

		return $local_ready;
	}

	/**
	 * Set package type
	 *
	 * @param string $type Package type
	 * @since 1.0.0
	 */
	public function set_type( $type ) {
		$type = is_string( $type ) ? ucfirst( strtolower( $type ) ) : false;

		if ( 'Plugin' === $type || 'Theme' === $type || 'Generic' === $type ) {
			$this->type = $type;
		}
	}

	/**
	 * Check if remote package needs update
	 *
	 * Compare local and remote package versions to determine if update is needed.
	 *
	 * @param string $slug Package slug
	 * @return bool|null Whether the package needs update
	 * @since 1.0.0
	 */
	public function check_remote_package_update( $slug ) {
		/**
		 * Fires before checking if a remote package needs to be updated
		 *
		 * @param string $slug The package slug
		 */
		do_action( 'upserv_check_remote_update', $slug );

		$needs_update  = true;
		$local_package = $this->find_package( $slug );

		if ( $local_package instanceof Package ) {
			$package_path = $local_package->get_filename();
			/**
			 * Filters the package information gathered from the file system before checking for updates in the VCS
			 *
			 * @param array $package_info The package information
			 * @param Package $package The package object retrieved from the file system
			 * @param string $package_slug The slug of the package
			 * @return array Modified package information
			 */
			$meta = apply_filters(
				'upserv_check_remote_package_update_local_meta',
				Package_Parser::parse_package( $package_path, true ),
				$local_package,
				$slug
			);

			if ( ! $meta ) {
				/**
				 * Filters whether the package needs to be updated when no metadata is found
				 *
				 * @param bool $needs_update Whether the package needs to be updated
				 * @param Package $package The package object retrieved from the file system
				 * @param string $package_slug The slug of the package
				 * @return bool Whether the package needs to be updated
				 */
				$needs_update = apply_filters(
					'upserv_check_remote_package_update_no_local_meta_needs_update',
					$needs_update,
					$local_package,
					$slug
				);

				return $needs_update;
			}

			$this->set_type( $meta['type'] );

			if ( 'Plugin' === $this->type || 'Theme' === $this->type || 'Generic' === $this->type ) {
				$this->init_update_checker( $slug );

				$remote_info = $this->update_checker->request_info();

				if ( ! is_wp_error( $remote_info ) && isset( $remote_info['version'] ) ) {
					$needs_update = version_compare( $remote_info['version'], $meta['header']['Version'], '>' );
				} else {
					Utils::php_log(
						$remote_info,
						'Invalid value $remote_info for package of type '
						. $this->type . ' and slug ' . $slug
					);
				}
			}
		} else {
			$needs_update = null;
		}

		/**
		 * Fires after checking if a remote package needs to be updated
		 *
		 * @param bool|null $needs_update Whether the package needs to be updated
		 * @param string $type The package type
		 * @param string $slug The package slug
		 */
		do_action( 'upserv_checked_remote_package_update', $needs_update, $this->type, $slug );

		return $needs_update;
	}

	/**
	 * Remove a package
	 *
	 * Delete a package from the filesystem and clear cache.
	 *
	 * @param string $slug Package slug
	 * @param bool   $force Whether to force removal even if locked
	 * @return bool Whether the package was removed successfully
	 * @since 1.0.0
	 */
	public function remove_package( $slug, $force = false ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( $force ) {
			self::unlock_update_from_remote( $slug );
		}

		if ( self::is_update_from_remote_locked( $slug ) ) {
			return false;
		}

		self::lock_update_from_remote( $slug );

		$package_path = trailingslashit( $this->package_dir ) . $slug . '.zip';
		$result       = false;
		$type         = false;
		$cache_key    = false;

		if ( is_file( $package_path ) ) {
			$cache_key   = Zip_Metadata_Parser::build_cache_key( $slug, $package_path );
			$parsed_info = Package_Parser::parse_package( $package_path, true );
			$type        = ucfirst( $parsed_info['type'] );
			$result      = $wp_filesystem->delete( $package_path );
		}

		/**
		 * Filters whether the package was removed from the file system
		 *
		 * @param bool $removed Whether the package was removed from the file system
		 * @param string $type The type of the package
		 * @param string $package_slug The slug of the package
		 * @return bool Whether the package was removed from the file system
		 */
		$result = apply_filters( 'upserv_remove_package_result', $result, $type, $slug );

		if ( $result && $cache_key ) {

			if ( ! $this->cache ) {
				$this->cache = new Cache( Data_Manager::get_data_dir( 'cache' ) );
			}

			$this->cache->clear( $cache_key );
		}

		/**
		 * Fires after a package has been removed
		 *
		 * @param bool $result Whether the package was successfully removed
		 * @param string $type The package type
		 * @param string $slug The package slug
		 */
		do_action( 'upserv_removed_package', $result, $type, $slug );
		self::unlock_update_from_remote( $slug );

		return $result;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Add query arguments to a URL
	 *
	 * Adds or removes query parameters from a URL.
	 *
	 * @param array  $args An associative array of query arguments
	 * @param string $url The old URL
	 * @return string New URL
	 * @since 1.0.0
	 */
	protected static function add_query_arg( $args, $url ) {

		if ( strpos( $url, '?' ) !== false ) {
			$parts = explode( '?', $url, 2 );
			$base  = $parts[0] . '?';

			parse_str( $parts[1], $query );
		} else {
			$base  = $url . '?';
			$query = array();
		}

		$query = array_merge( $query, $args );

		// Remove null/false arguments.
		$query = array_filter(
			$query,
			function ( $value ) {
				return ( null !== $value ) && ( false !== $value );
			}
		);

		return $base . http_build_query( $query, '', '&' );
	}

	/**
	 * Dispatch request to appropriate handler
	 *
	 * Routes the request to the proper action handler.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function dispatch( $request ) {

		if ( 'get_metadata' === $request->action ) {
			$this->action_get_metadata( $request );
		} elseif ( 'download' === $request->action ) {
			$this->action_download( $request );
		} else {
			$this->exit_with_error( sprintf( 'Invalid action "%s".', htmlentities( $request->action ) ), 400 );
		}
	}

	/**
	 * Initialize request
	 *
	 * Parse and prepare the request parameters.
	 *
	 * @param array $query Query parameters
	 * @return Request Initialized request object
	 * @since 1.0.0
	 */
	protected function init_request( $query ) {
		$headers     = Headers::parse_current();
		$client_ip   = Utils::get_remote_ip();
		$http_method = ! empty( $_SERVER['REQUEST_METHOD'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) :
			'GET';

		if ( ! in_array( $http_method, array( 'GET', 'POST' ), true ) ) {
			$this->exit_with_error( 'Invalid request method.', 405 );
		}

		$request = new Request( $query, $headers, $client_ip, $http_method );

		if ( ! upserv_is_package_whitelisted( $request->slug ) && upserv_get_option( 'use_vcs' ) ) {
			$this->exit_with_error( 'Invalid package.', 404 );
		}

		if ( $request->param( 'type' ) ) {
			$request->type = $request->param( 'type' );
			$this->type    = ucfirst( $request->type );
		}

		$request->token = $request->param( 'token' );

		return $this->init_license_request( $request );
	}

	/**
	 * Check authorization
	 *
	 * Verify if the request has proper authorization.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function check_authorization( $request ) {

		if (
			'download' === $request->action &&
			! upserv_validate_nonce( $request->token )
		) {
			$message = __( 'The download URL token has expired.', 'updatepulse-server' );

			$this->exit_with_error( $message, 403 );
		}

		$this->check_license_authorization( $request );
	}

	/**
	 * Generate download URL
	 *
	 * Create a URL for package download with appropriate parameters.
	 *
	 * @param Package $package Package instance
	 * @return string Download URL
	 * @since 1.0.0
	 */
	protected function generate_download_url( Package $package ) {
		$metadata = $package->get_metadata();

		$this->set_type( $metadata['type'] );

		$query          = $this->filter_license_download_query(
			array(
				'action'      => 'download',
				'package_id'  => $package->slug,
				'update_type' => $this->type,
			)
		);
		$query['token'] = isset( $query['token'] ) ? $query['token'] : upserv_create_nonce();

		return self::add_query_arg( $query, $this->server_url );
	}

	/**
	 * Handle download action
	 *
	 * Process a download request for a package.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function action_download( Request $request ) {
		/**
		 * Fires when processing a download action
		 *
		 * @param Request $request The current request object
		 */
		do_action( 'upserv_update_server_action_download', $request );

		/**
		 * Filters whether the download action has been handled
		 *
		 * Allows plugins to take over the download action processing.
		 *
		 * @param bool $handled Whether the download has been handled
		 * @param Request $request The current request object
		 * @return bool Whether the download has been handled
		 */
		if ( apply_filters( 'upserv_update_server_action_download_handled', false, $request ) ) {
			return;
		}

		// Required for IE, otherwise Content-Disposition may be ignored.
		if ( ini_get( 'zlib.output_compression' ) ) {
			@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.IniSet.Risky, WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$package = $request->package;

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $package->slug . '.zip' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . $package->get_file_size() );

		readfile( $package->get_filename() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}

	/**
	 * Validate request parameters
	 *
	 * Check if the request contains required parameters.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function validate_request( $request ) {

		if ( ! $request->action ) {
			$this->exit_with_error( 'You must specify an action.', 400 );
		}

		if ( ! $request->slug ) {
			$this->exit_with_error( 'You must specify a package slug.', 400 );
		}

		if ( ! $request->package ) {
			$this->exit_with_error( 'Package not found', 404 );
		}
	}

	/**
	 * Load package for request
	 *
	 * Find and load the requested package.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function load_package_for( $request ) {

		if ( empty( $request->slug ) ) {
			return;
		}

		try {
			$request->package = $this->find_package( $request->slug );
		} catch ( Invalid_Package_Exception $e ) {
			$this->exit_with_error(
				sprintf(
					'Package "%s" exists, but it is not a valid plugin or theme. ' .
					'Make sure it has the right format ( Zip ) and directory structure.',
					htmlentities( $request->slug )
				)
			);

			exit;
		}
	}

	/**
	 * Find package by slug
	 *
	 * Locate a package in local storage or download from remote if needed.
	 *
	 * @param string $slug Package slug
	 * @param bool   $check_remote Whether to check remote repositories
	 * @return Package|false Package instance or false if not found
	 * @since 1.0.0
	 */
	protected function find_package( $slug, $check_remote = true ) {

		if ( ! $this->cache ) {
			$this->cache = new Cache( Data_Manager::get_data_dir( 'cache' ) );
		}

		$safe_slug = preg_replace( '@[^a-z0-9\-_\.,+!]@i', '', $slug );
		$package   = false;
		$filename  = trailingslashit( $this->package_dir ) . $safe_slug . '.zip';
		/**
		 * Filters whether to save remote package to local storage
		 *
		 * Determines if the package should be fetched from remote repository and saved locally.
		 *
		 * @param bool $save_to_local Whether to save the package locally
		 * @param string $safe_slug The sanitized package slug
		 * @param string $filename The local filename path
		 * @param bool $check_remote Whether to check remote repositories
		 * @return bool Whether to save the package locally
		 */
		$save_to_local = apply_filters(
			'upserv_save_remote_to_local',
			! is_file( $filename ) || ! is_readable( $filename ),
			$safe_slug,
			$filename,
			$check_remote
		);

		if ( upserv_get_option( 'use_vcs' ) && $save_to_local && $check_remote ) {
			$is_package_ready = $this->save_remote_package_to_local( $safe_slug );

			if ( true === $is_package_ready ) {
				return $this->find_package( $slug, false );
			}
		}

		try {
			$cached_value = null;

			if ( is_file( $filename ) && is_readable( $filename ) ) {
				$cache_key    = Zip_Metadata_Parser::build_cache_key( $safe_slug, $filename );
				$cached_value = $this->cache->get( $cache_key );
			}

			if ( null === $cached_value ) {
				/**
				 * Fires when no cached package metadata is available
				 *
				 * @param string $safe_slug The sanitized package slug
				 * @param string $filename The local filename path
				 * @param Cache $cache The cache instance
				 */
				do_action( 'upserv_find_package_no_cache', $safe_slug, $filename, $this->cache );
			}

			$package = Package::from_archive( $filename, $safe_slug, $this->cache );
		} catch ( Exception $e ) {
			Utils::php_log( 'Corrupt archive ' . $filename . '; package will not be displayed or delivered' );

			$log  = 'Exception caught: ' . $e->getMessage() . "\n";
			$log .= 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";

			Utils::php_log( $log );
		}

		return $package;
	}

	/**
	 * Handle metadata action
	 *
	 * Process a request for package metadata.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function action_get_metadata( Request $request ) {
		$meta = array();

		if ( $request->package ) {
			$meta                 = $request->package->get_metadata();
			$meta['download_url'] = $this->generate_download_url( $request->package );
		} else {
			$meta['error']   = 'invalid_package';
			$meta['message'] = __( 'Invalid package.', 'updatepulse-server' );
		}

		$meta                 = $this->filter_metadata( $meta, $request );
		$meta['time_elapsed'] = Utils::get_time_elapsed();

		$this->output_as_json( $meta );

		exit;
	}

	/**
	 * Filter metadata
	 *
	 * Apply filters to package metadata.
	 *
	 * @param array   $meta Package metadata
	 * @param Request $request Request instance
	 * @return array Filtered metadata
	 * @since 1.0.0
	 */
	protected function filter_metadata( $meta, $request ) {
		$meta = array_filter(
			$meta,
			function ( $value ) {
				return null !== $value;
			}
		);

		if ( ! isset( $meta['slug'] ) ) {
			return $meta;
		}

		return $this->filter_license_metadata( $meta, $request );
	}

	/**
	 * Normalize file path
	 *
	 * Convert all directory separators to forward slashes.
	 *
	 * @param string $path File path
	 * @return string Normalized path
	 * @since 1.0.0
	 */
	protected function normalize_file_path( $path ) {

		if ( ! is_string( $path ) ) {
			return $path;
		}

		return str_replace( array( DIRECTORY_SEPARATOR, '\\' ), '/', $path );
	}

	/**
	 * Log a request
	 *
	 * Record details of an API request to log file.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function log_request( $request ) {
		$log_file = $this->get_log_file_name();
		$handle   = fopen( $log_file, 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( $handle && flock( $handle, LOCK_EX ) ) {
			$logged_ip = $request->client_ip;
			$columns   = array(
				'ip'                => $logged_ip,
				'http_method'       => $request->http_method,
				'action'            => $request->param( 'action', '-' ),
				'slug'              => $request->param( 'slug', '-' ),
				'installed_version' => $request->param( 'installed_version', '-' ),
				'wp_version'        => isset( $request->wp_version ) ? $request->wp_version : '-',
				'site_url'          => isset( $request->wp_site_url ) ? $request->wp_site_url : '-',
				'query'             => http_build_query( $request->query, '', '&' ),
			);
			$columns   = $this->escape_log_info( $columns );

			if ( isset( $columns['ip'] ) ) {
				$columns['ip'] = str_pad( $columns['ip'], 15, ' ' );
			}

			if ( isset( $columns['http_method'] ) ) {
				$columns['http_method'] = str_pad( $columns['http_method'], 4, ' ' );
			}

			$date = new DateTime( 'now', $this->timezone );
			$line = $date->format( '[Y-m-d H:i:s O]' ) . ' ' . implode( "\t", $columns ) . "\n";

			fwrite( $handle, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			flock( $handle, LOCK_UN );
		}

		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/**
	 * Get log file name
	 *
	 * Generate the name of the log file for the current date.
	 *
	 * @return string Log file path
	 * @since 1.0.0
	 */
	protected function get_log_file_name() {
		$path  = $this->log_dir . '/request';
		$date  = new DateTime( 'now', $this->timezone );
		$path .= '-' . $date->format( 'Y-m-d' );

		return $path . '.log';
	}

	/**
	 * Escape log information
	 *
	 * Sanitize data for safe storage in log files.
	 *
	 * @param string[] $columns List of columns in the log entry
	 * @return string[] Escaped columns
	 * @since 1.0.0
	 */
	protected function escape_log_info( $columns ) {
		return array_map( array( $this, 'escape_log_value' ), $columns );
	}

	/**
	 * Escape log value
	 *
	 * Escape a single value for safe storage in log files.
	 *
	 * @param string|null $value Value to escape
	 * @return string|null Escaped value
	 * @since 1.0.0
	 */
	protected function escape_log_value( $value ) {

		if ( ! isset( $value ) ) {
			return null;
		}

		$value = (string) $value;
		$regex = '/[[:^graph:]]/';

		// preg_replace_callback will return NULL if the input contains invalid Unicode sequences, so only enable the Unicode flag if the input encoding looks valid.
		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $value, 'UTF-8' ) ) {
			$regex = $regex . 'u';
		}

		$value = str_replace( '\\', '\\\\', $value );
		$value = preg_replace_callback(
			$regex,
			function ( array $matches ) {
				$length  = strlen( $matches[0] );
				$escaped = '';

				for ( $i = 0; $i < $length; $i++ ) {
					// Convert the character to a hexadecimal escape sequence.
					$hex_code = dechex( ord( $matches[0][ $i ] ) );
					$escaped .= '\x' . strtoupper( str_pad( $hex_code, 2, '0', STR_PAD_LEFT ) );
				}

				return $escaped;
			},
			$value
		);

		return $value;
	}

	/**
	 * Exit with error
	 *
	 * Terminate execution and display error message.
	 *
	 * @param string $message Error message
	 * @param int    $http_status HTTP status code
	 * @since 1.0.0
	 */
	protected function exit_with_error( $message = '', $http_status = 500 ) {
		$status_messages = array(
			// This is not a full list of HTTP status messages. We only need the errors.
			// [Client Error 4xx]
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			402 => '402 Payment Required',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',
			407 => '407 Proxy Authentication Required',
			408 => '408 Request Timeout',
			409 => '409 Conflict',
			410 => '410 Gone',
			411 => '411 Length Required',
			412 => '412 Precondition Failed',
			413 => '413 Request Entity Too Large',
			414 => '414 Request-URI Too Long',
			415 => '415 Unsupported Media Type',
			416 => '416 Requested Range Not Satisfiable',
			417 => '417 Expectation Failed',
			// [Server Error 5xx]
			500 => '500 Internal Server Error',
			501 => '501 Not Implemented',
			502 => '502 Bad Gateway',
			503 => '503 Service Unavailable',
			504 => '504 Gateway Timeout',
			505 => '505 HTTP Version Not Supported',
		);

		$protocol = empty( $_SERVER['SERVER_PROTOCOL'] ) ? 'HTTP/1.1' : sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) );

		// Output a HTTP status header.
		if ( isset( $status_messages[ $http_status ] ) ) {
			header( $protocol . ' ' . $status_messages[ $http_status ] );
			$title = $status_messages[ $http_status ];
		} else {
			header( 'X-Ws-Update-Server-Error: ' . $http_status, true, $http_status );
			$title = 'HTTP ' . $http_status;
		}

		if ( '' === $message ) {
			$message = $title;
		}

		// And a basic HTML error message.
		printf(
			'<html>
				<head> <title>%1$s</title> </head>
				<body> <h1>%1$s</h1> <p>%2$s</p> </body>
			 </html>',
			esc_html( $title ),
			esc_html( $message )
		);
		exit;
	}

	/**
	 * Output data as JSON
	 *
	 * Send data as JSON response with appropriate headers.
	 *
	 * @param mixed $response Response data
	 * @since 1.0.0
	 */
	protected function output_as_json( $response ) {
		header( 'Content-Type: application/json; charset=utf-8' );

		echo wp_json_encode( $response, Utils::JSON_OPTIONS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Misc. -------------------------------------------------------

	/**
	 * Unlock update from remote
	 *
	 * Remove lock for remote update process.
	 *
	 * @param string $slug Package slug
	 * @since 1.0.0
	 */
	protected static function unlock_update_from_remote( $slug ) {
		$locks = get_option( 'upserv_update_from_remote_locks' );
		$locks = is_array( $locks ) ? $locks : array();

		if ( array_key_exists( $slug, $locks ) ) {
			unset( $locks[ $slug ] );
		}

		update_option( 'upserv_update_from_remote_locks', $locks );
	}

	/**
	 * Lock update from remote
	 *
	 * Create lock for remote update process.
	 *
	 * @param string $slug Package slug
	 * @since 1.0.0
	 */
	protected static function lock_update_from_remote( $slug ) {
		$locks = get_option( 'upserv_update_from_remote_locks', array() );
		$locks = is_array( $locks ) ? $locks : array();

		if ( ! array_key_exists( $slug, $locks ) ) {
			$locks[ $slug ] = time() + self::LOCK_REMOTE_UPDATE_SEC;

			update_option( 'upserv_update_from_remote_locks', $locks );
		}
	}

	/**
	 * Check if update from remote is locked
	 *
	 * Determine if there's an active lock for remote update.
	 *
	 * @param string $slug Package slug
	 * @return bool Whether the update is locked
	 * @since 1.0.0
	 */
	protected static function is_update_from_remote_locked( $slug ) {
		$locks     = get_option( 'upserv_update_from_remote_locks' );
		$is_locked = is_array( $locks ) && array_key_exists( $slug, $locks ) && $locks[ $slug ] >= time();

		return $is_locked;
	}

	/**
	 * Build update checker
	 *
	 * Create instance of appropriate update checker for package type.
	 *
	 * @param string $slug Package slug
	 * @param string $package_filename Package filename
	 * @return object|false Update checker instance or false if not supported
	 * @since 1.0.0
	 */
	protected function build_update_checker( $slug, $package_filename ) {
		$repo_url  = trailingslashit( $this->vcs_url ) . $slug;
		$service   = upserv_get_vcs_name( $this->vcs_type, 'edit' );
		$api_class = $service ? 'Anyape\PackageUpdateChecker\Vcs\\' . $service . 'Api' : false;

		if ( ! $api_class ) {
			return false;
		}

		$checker_class = 'Anyape\PackageUpdateChecker\\' . $this->type . 'UpdateChecker';
		$params        = array( new $api_class( $repo_url ), $slug, $this->package_dir );

		if ( $package_filename ) {
			$params[] = $package_filename;
		}

		return new $checker_class( ...$params );
	}

	/**
	 * Initialize update checker
	 *
	 * Set up the update checker with appropriate credentials and settings.
	 *
	 * @param string $slug Package slug
	 * @since 1.0.0
	 */
	protected function init_update_checker( $slug ) {
		/**
		 * Filters the checker object used to perform remote checks and downloads
		 *
		 * @param mixed $update_checker The checker object
		 * @param string $package_slug The slug of the package using the checker object
		 * @param string $type The type of the package using the checker object
		 * @param string $vcs_url URL of the VCS where the remote packages are located
		 * @param string $branch The branch of the VCS repository where the packages are located
		 * @param mixed $credentials The credentials to access the VCS where the packages are located
		 * @param bool $self_hosted Whether the VCS is self-hosted
		 * @return mixed Modified update checker object
		 */
		$this->update_checker = apply_filters(
			'upserv_update_checker',
			$this->update_checker,
			$slug,
			$this->type,
			$this->vcs_url,
			$this->branch,
			$this->credentials,
			$this->self_hosted
		);

		if ( $this->update_checker && $this->update_checker->slug === $slug ) {
			return;
		}

		require_once UPSERV_PLUGIN_PATH . 'lib/package-update-checker/package-update-checker.php';

		$package_filename = null;

		if ( 'Plugin' === $this->type ) {
			$package_filename = $slug;
		} elseif ( 'Generic' === $this->type ) {
			$package_filename = 'updatepulse';
		}

		$this->update_checker = $this->build_update_checker( $slug, $package_filename );

		if ( $this->update_checker ) {

			if ( $this->credentials ) {
				$this->update_checker->set_authentication( $this->credentials );
			}

			$this->update_checker->set_branch( $this->branch );
		}
	}

	/**
	 * Download remote package
	 *
	 * Fetch a package file from a remote URL.
	 *
	 * @param string $url Remote file URL
	 * @param int    $timeout Request timeout in seconds
	 * @return string|WP_Error Local filename or error
	 * @since 1.0.0
	 */
	protected function download_remote_package( $url, $timeout = 300 ) {

		if ( ! $url ) {
			return new WP_Error( 'http_no_url', __( 'Invalid URL provided.', 'updatepulse-server' ) );
		}

		$local_filename = wp_tempnam( $url );

		if ( ! $local_filename ) {
			return new WP_Error( 'http_no_file', __( 'Could not create temporary file.', 'updatepulse-server' ) );
		}

		$params = array(
			'timeout'  => $timeout,
			'stream'   => true,
			'filename' => $local_filename,
		);

		if ( $this->credentials ) {
			$auth_headers = $this->update_checker->get_vcs_api()->get_authorization_headers();

			if ( $auth_headers ) {
				$params['headers'] = $auth_headers;
			}
		}

		$response = wp_safe_remote_get( $url, $params );

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $local_filename );
			Utils::php_log( $response, 'Invalid value for $response' );

			return $response;
		}

		if ( 200 !== abs( intval( wp_remote_retrieve_response_code( $response ) ) ) ) {
			wp_delete_file( $local_filename );

			return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
		}

		$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );

		if ( $content_md5 ) {
			$md5_check = verify_file_md5( $local_filename, $content_md5 );

			if ( is_wp_error( $md5_check ) ) {
				wp_delete_file( $local_filename );
				Utils::php_log( $md5_check, 'Invalid value for $md5_check' );

				return $md5_check;
			}
		}

		return $local_filename;
	}

	// Licenses -------------------------------------------------------

	/**
	 * Initialize license request
	 *
	 * Prepare a request with license information.
	 *
	 * @param Request $request Request instance
	 * @return Request Modified request with license data
	 * @since 1.0.0
	 */
	protected function init_license_request( $request ) {

		if ( ! $request->param( 'license_key' ) ) {
			return $request;
		}

		$result = false;

		if ( $request->param( 'licensed_with' ) ) {
			$info = upserv_get_package_info( $request->slug, false );

			if (
				$info &&
				isset( $info['licensed_with'] ) &&
				$request->param( 'licensed_with' ) === $info['licensed_with']
			) {
				$main_package_info = upserv_get_package_info( $info['licensed_with'], false );
				$result            = $this->verify_license_exists(
					$info['licensed_with'],
					$main_package_info['type'],
					$request->param( 'license_key' )
				);
			}
		}

		if ( ! $result ) {
			$result = $this->verify_license_exists(
				$request->slug,
				$request->type,
				$request->param( 'license_key' )
			);
		}

		$request->license_key       = $request->param( 'license_key' );
		$request->license_signature = $request->param( 'license_signature' );
		$request->license           = $result;

		$this->license_key       = $request->license_key;
		$this->license_signature = $request->license_signature;

		return $request;
	}

	/**
	 * Filter license metadata
	 *
	 * Modify metadata based on license status.
	 *
	 * @param array   $meta Package metadata
	 * @param Request $request Request instance
	 * @return array Filtered metadata
	 * @since 1.0.0
	 */
	protected function filter_license_metadata( $meta, $request ) {

		if ( ! upserv_is_package_require_license( $meta['slug'] ) ) {
			return $meta;
		}

		$license           = $request->license;
		$license_signature = $request->license_signature;

		if ( is_object( $license ) || is_array( $license ) ) {
			$meta['license'] = $this->prepare_license_for_output( $license );
		}

		if (
			/**
			 * Filters whether a license is valid
			 *
			 * Allows plugins to override license validation logic.
			 *
			 * @param bool $is_valid Whether the license is valid based on internal validation
			 * @param object $license The license object
			 * @param string $license_signature The license signature
			 * @return bool Whether the license is valid
			 */
			apply_filters(
				'upserv_license_valid',
				$this->is_license_valid( $license, $license_signature ),
				$license,
				$license_signature
			)
		) {
			$args                 = array(
				'license_key'       => $request->license_key,
				'license_signature' => $request->license_signature,
			);
			$meta['download_url'] = self::add_query_arg( $args, $meta['download_url'] );
		} else {
			unset( $meta['download_url'] );
			unset( $meta['license'] );

			$meta['license_error'] = $this->get_license_error( $license );
		}

		return $meta;
	}

	/**
	 * Filter license download query
	 *
	 * Add license parameters to download URL.
	 *
	 * @param array $query Query parameters
	 * @return array Modified query parameters
	 * @since 1.0.0
	 */
	protected function filter_license_download_query( $query ) {

		if ( upserv_is_package_require_license( $query['package_id'] ) ) {
			$query['token']             = upserv_create_nonce( true, DAY_IN_SECONDS / 2 );
			$query['license_key']       = $this->license_key;
			$query['license_signature'] = $this->license_signature;
		}

		return $query;
	}

	/**
	 * Check license authorization
	 *
	 * Verify if the license is valid for the requested action.
	 *
	 * @param Request $request Request instance
	 * @since 1.0.0
	 */
	protected function check_license_authorization( $request ) {

		if ( ! upserv_is_package_require_license( $request->slug ) ) {
			return;
		}

		$license           = $request->license;
		$license_signature = $request->license_signature;
		$valid             = $this->is_license_valid( $license, $license_signature );

		if (
			'download' === $request->action &&
			/**
			 * Filters whether a license is valid when requesting for an update
			 *
			 * @param bool $is_valid Whether the license is valid
			 * @param mixed $license The license to validate
			 * @param string $license_signature The signature of the license
			 * @return bool Whether the license is valid
			 */
			! apply_filters( 'upserv_license_valid', $valid, $license, $license_signature )
		) {
			$this->exit_with_error( 'Invalid license key or signature.', 403 );
		}
	}

	/**
	 * Get license error
	 *
	 * Format error information for invalid license.
	 *
	 * @param mixed $license License data or error
	 * @return object Error information
	 * @since 1.0.0
	 */
	protected function get_license_error( $license ) {

		if ( is_wp_error( $license ) ) {
			$error = (object) array(
				'code'    => 'license_error',
				'message' => implode( '<br>', $license->get_error_messages() ),
				'data'    => (object) array(
					'license' => $license,
				),
			);
		} elseif ( is_object( $license ) && 'activated' !== $license->status ) {
			$error = (object) array(
				'code'    => 'illegal_license_status',
				'message' => 'The license cannot be used for the requested action.',
				'data'    => (object) array(
					'license' => $license,
				),
			);
		} else {
			$error = (object) array(
				'code'    => 'invalid_license',
				'message' => 'The license key or signature is invalid.',
				'data'    => (object) array(
					'license' => $license,
				),
			);
		}

		return $error;
	}

	/**
	 * Verify license exists
	 *
	 * Check if a license exists and is valid for the package.
	 *
	 * @param string $slug Package slug
	 * @param string $type Package type
	 * @param string $license_key License key
	 * @return object|false License data or false if invalid
	 * @since 1.0.0
	 */
	protected function verify_license_exists( $slug, $type, $license_key ) {
		$license_server = new License_Server();
		$payload        = array( 'license_key' => $license_key );
		$result         = $license_server->read_license( $payload );

		if (
			is_object( $result ) &&
			$slug === $result->package_slug &&
			$type &&
			$result->package_type &&
			strtolower( $type ) === strtolower( $result->package_type )
		) {
			$result->result  = 'success';
			$result->message = __( 'License key details retrieved.', 'updatepulse-server' );
		} else {
			$result = false;
		}

		return $result;
	}

	/**
	 * Prepare license for output
	 *
	 * Filter sensitive data from license information.
	 *
	 * @param object|array $license License data
	 * @return array License data safe for output
	 * @since 1.0.0
	 */
	protected function prepare_license_for_output( $license ) {
		$output = json_decode( wp_json_encode( $license ), true );

		unset( $output['id'] );
		unset( $output['hmac_key'] );
		unset( $output['crypto_key'] );
		unset( $output['data'] );
		unset( $output['owner_name'] );
		unset( $output['email'] );
		unset( $output['company_name'] );

		/**
		 * Filters license data prepared for output
		 *
		 * Allows modification of license information before sending to client.
		 *
		 * @param array $output The prepared license data with sensitive information removed
		 * @param object|array $license The original license data
		 * @return array Modified license data for output
		 */
		return apply_filters( 'upserv_license_update_server_prepare_license_for_output', $output, $license );
	}

	/**
	 * Check if license is valid
	 *
	 * Verify license key and signature.
	 *
	 * @param object $license License data
	 * @param string $license_signature License signature
	 * @return bool Whether the license is valid
	 * @since 1.0.0
	 */
	protected function is_license_valid( $license, $license_signature ) {
		$valid = false;

		if ( is_object( $license ) && ! is_wp_error( $license ) && 'activated' === $license->status ) {

			/**
			 * Filters whether to bypass signature validation
			 *
			 * Allows plugins to skip signature validation for certain licenses.
			 *
			 * @param bool $bypass Whether to bypass signature validation
			 * @param object $license The license object
			 * @return bool Whether to bypass signature validation
			 */
			if ( apply_filters( 'upserv_license_bypass_signature', false, $license ) ) {
				$valid = $this->license_key === $license->license_key;
			} else {
				$license_server = new License_Server();
				$valid          = $this->license_key === $license->license_key &&
					$license_server->is_signature_valid( $license->license_key, $license_signature );
			}
		}

		return $valid;
	}
}
