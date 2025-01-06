<?php
require UPSERV_PLUGIN_PATH . '/lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p3\Vcs\GitHubApi;
use YahnisElsts\PluginUpdateChecker\v5p3\Vcs\GitLabApi;
use YahnisElsts\PluginUpdateChecker\v5p3\Vcs\BitBucketApi;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

class UPServ_Update_Server extends Wpup_UpdateServer {

	const LOCK_REMOTE_UPDATE_SEC = 10;

	protected $server_directory;
	protected $use_remote_repository;
	protected $repository_service_url;
	protected $repository_branch;
	protected $repository_credentials;
	protected $repository_service_self_hosted;
	protected $update_checker;
	protected $type;

	public function __construct(
		$use_remote_repository,
		$server_url,
		$server_directory = null,
		$repository_service_url = null,
		$repository_branch = 'master',
		$repository_credentials = null,
		$repository_service_self_hosted = false
	) {
		parent::__construct( $server_url, untrailingslashit( $server_directory ) );

		$this->use_remote_repository          = $use_remote_repository;
		$this->server_directory               = $server_directory;
		$this->repository_service_self_hosted = $repository_service_self_hosted;
		$this->repository_service_url         = $repository_service_url;
		$this->repository_branch              = $repository_branch;
		$this->repository_credentials         = $repository_credentials;
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// Misc. -------------------------------------------------------

	public static function unlock_update_from_remote( $slug ) {
		$locks = get_option( 'upserv_update_from_remote_locks' );

		if ( ! is_array( $locks ) ) {
			update_option( 'upserv_update_from_remote_locks', array() );
		} elseif ( array_key_exists( $slug, $locks ) ) {
			unset( $locks[ $slug ] );

			update_option( 'upserv_update_from_remote_locks', $locks );
		}
	}

	public static function lock_update_from_remote( $slug ) {
		$locks = get_option( 'upserv_update_from_remote_locks' );

		if ( ! is_array( $locks ) ) {
			update_option( 'upserv_update_from_remote_locks', array( $slug => time() + self::LOCK_REMOTE_UPDATE_SEC ) );
		} elseif ( ! array_key_exists( $slug, $locks ) ) {
			$locks[ $slug ] = time() + self::LOCK_REMOTE_UPDATE_SEC;

			update_option( 'upserv_update_from_remote_locks', $locks );
		}
	}

	public static function is_update_from_remote_locked( $slug ) {
		$locks     = get_option( 'upserv_update_from_remote_locks' );
		$is_locked = is_array( $locks ) && array_key_exists( $slug, $locks ) && $locks[ $slug ] >= time();

		return $is_locked;
	}

	public function save_remote_package_to_local( $safe_slug ) {
		$local_ready = false;

		if ( ! self::is_update_from_remote_locked( $safe_slug ) ) {
			self::lock_update_from_remote( $safe_slug );
			$this->init_update_checker( $safe_slug );

			if ( $this->update_checker ) {

				try {
					$info = $this->update_checker->requestInfo();

					if ( $info && ! is_wp_error( $info ) ) {
						require_once UPSERV_PLUGIN_PATH . 'inc/class-upserv-zip-package-manager.php';

						$this->remove_package( $safe_slug );

						$package = $this->download_remote_package( $info['download_url'] );

						do_action( 'upserv_downloaded_remote_package', $package, $info['type'], $safe_slug );

						$package_manager = new UPServ_Zip_Package_Manager(
							$safe_slug,
							$package,
							UPServ_Data_Manager::get_data_dir( 'tmp' ),
							UPServ_Data_Manager::get_data_dir( 'packages' )
						);
						$local_ready     = $package_manager->clean_package();

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

	public function set_type( $type ) {
		$type = $type ? ucfirst( $type ) : false;

		if ( 'Plugin' === $type || 'Theme' === $type || 'Generic' === $type ) {
			$this->type = $type;
		}
	}

	public function check_remote_package_update( $slug ) {
		do_action( 'upserv_check_remote_update', $slug );

		$needs_update  = true;
		$local_package = $this->findPackage( $slug );

		if ( $local_package instanceof Wpup_Package ) {
			$package_path = $local_package->getFileName();
			$local_meta   = WshWordPressPackageParser_Extended::parsePackage( $package_path, true );
			$local_meta   = apply_filters(
				'upserv_check_remote_package_update_local_meta',
				$local_meta,
				$local_package,
				$slug
			);

			if ( ! $local_meta ) {
				$needs_update = apply_filters(
					'upserv_check_remote_package_update_no_local_meta_needs_update',
					$needs_update,
					$local_package,
					$slug
				);

				return $needs_update;
			}

			$local_info = array(
				'type'         => $local_meta['type'],
				'version'      => $local_meta['header']['Version'],
				'main_file'    => $local_meta['pluginFile'],
				'download_url' => '',
			);

			$this->type = ucfirst( $local_info['type'] );

			if ( 'Plugin' === $this->type || 'Theme' === $this->type || 'Generic' === $this->type ) {
				$this->init_update_checker( $slug );

				$remote_info = $this->update_checker->requestInfo();

				if ( $remote_info && ! is_wp_error( $remote_info ) ) {
					$needs_update = version_compare( $remote_info['version'], $local_info['version'], '>' );
				} else {
					php_log(
						$remote_info,
						'Invalid value $remote_info for package of type '
						. $this->type . ' and slug ' . $slug
					);
				}
			}
		} else {
			$needs_update = null;
		}

		do_action( 'upserv_checked_remote_package_update', $needs_update, $this->type, $slug );

		return $needs_update;
	}

	public function remove_package( $slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		$package_path = trailingslashit( $this->packageDirectory ) . $slug . '.zip'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$result       = false;
		$type         = false;
		$cache_key    = false;

		if ( $wp_filesystem->is_file( $package_path ) ) {
			$cache_key = 'metadata-b64-' . $slug . '-'
				. md5(
					$package_path . '|'
					. filesize( $package_path ) . '|'
					. filemtime( $package_path )
				);

			$parsed_info = WshWordPressPackageParser_Extended::parsePackage( $package_path, true );
			$type        = ucfirst( $parsed_info['type'] );
			$result      = $wp_filesystem->delete( $package_path );
		}

		$result = apply_filters( 'upserv_remove_package_result', $result, $type, $slug );

		if ( $result && $cache_key ) {

			if ( ! $this->cache ) {
				$this->cache = new Wpup_FileCache( UPServ_Data_Manager::get_data_dir( 'cache' ) );
			}

			$this->cache->clear( $cache_key );
		}

		do_action( 'upserv_removed_package', $result, $type, $slug );

		return $result;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	// Overrides ---------------------------------------------------

	protected function initRequest( $query = null, $headers = null ) {
		$request = parent::initRequest( $query, $headers );

		if ( $request->param( 'type' ) ) {
			$request->type = $request->param( 'type' );
			$this->type    = ucfirst( $request->type );
		}

		$request->token = $request->param( 'token' );

		return $request;
	}

	protected function checkAuthorization( $request ) {
		parent::checkAuthorization( $request );

		if (
			'download' === $request->action &&
			! upserv_validate_nonce( $request->token )
		) {
			$message = __( 'The download URL token has expired.', 'updatepulse-server' );

			$this->exitWithError( $message, 403 );
		}
	}

	protected function generateDownloadUrl( Wpup_Package $package ) {
		$query = array(
			'action'     => 'download',
			'token'      => upserv_create_nonce(),
			'package_id' => $package->slug,
		);

		return self::addQueryArg( $query, $this->serverUrl ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	protected function actionDownload( Wpup_Request $request ) {
		do_action( 'upserv_update_server_action_download', $request );

		$handled = apply_filters( 'upserv_update_server_action_download_handled', false, $request );

		if ( ! $handled ) {
			parent::actionDownload( $request );
		}
	}

	protected function findPackage( $slug, $check_remote = true ) {
		WP_Filesystem();

		global $wp_filesystem;

		$safe_slug            = preg_replace( '@[^a-z0-9\-_\.,+!]@i', '', $slug );
		$filename             = trailingslashit( $this->packageDirectory ) . $safe_slug . '.zip'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$save_remote_to_local = apply_filters(
			'upserv_save_remote_to_local',
			! $wp_filesystem->is_file( $filename ) || ! $wp_filesystem->is_readable( $filename ),
			$safe_slug,
			$filename,
			$check_remote
		);

		if ( $save_remote_to_local ) {
			$re_check_local = false;

			if ( $this->use_remote_repository && $this->repository_service_url ) {

				if ( $check_remote ) {
					$re_check_local = $this->save_remote_package_to_local( $safe_slug );
				}
			}

			if ( $re_check_local ) {
				return $this->findPackage( $slug, false );
			}
		}

		$package = false;

		try {
			$cached_value = null;

			if ( $this->cache ) {

				if ( $wp_filesystem->is_file( $filename ) && $wp_filesystem->is_readable( $filename ) ) {
					$cache_key    = 'metadata-b64-' . $safe_slug . '-'
						. md5( $filename . '|' . filesize( $filename ) . '|' . filemtime( $filename ) );
					$cached_value = $this->cache->get( $cache_key );
				}
			} else {
				$this->cache = new Wpup_FileCache( UPServ_Data_Manager::get_data_dir( 'cache' ) );
			}

			if ( ! $cached_value ) {
				do_action( 'upserv_find_package_no_cache', $safe_slug, $filename, $this->cache );
			}

			$package = Wpup_Package_Extended::fromArchive( $filename, $safe_slug, $this->cache );
		} catch ( Exception $e ) {
			php_log( 'Corrupt archive ' . $filename . ' ; package will not be displayed or delivered' );

			$log  = 'Exception caught: ' . $e->getMessage() . "\n";
			$log .= 'File: ' . $e->getFile() . "\n";
			$log .= 'Line: ' . $e->getLine() . "\n";

			php_log( $log );
		}

		return $package;
	}

	protected function actionGetMetadata( Wpup_Request $request ) {
		$meta                         = $request->package->getMetadata();
		$meta['download_url']         = $this->generateDownloadUrl( $request->package );
		$meta                         = $this->filterMetadata( $meta, $request );
		$meta['request_time_elapsed'] = sprintf( '%.3f', microtime( true ) - $this->startTime ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$this->outputAsJson( $meta );

		exit;
	}

	// Misc. -------------------------------------------------------

	protected static function build_update_checker(
		$metadata_url,
		$slug,
		$file_name,
		$type,
		$package_container,
		$self_hosted = false,
		$option_name = ''
	) {

		if ( 'Plugin' !== $type && 'Theme' !== $type && 'Generic' !== $type ) {
			trigger_error( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				sprintf(
					'Proxuc does not support packages of type %s',
					esc_html( $type )
				),
				E_USER_ERROR
			);
		}

		$service       = null;
		$api_class     = null;
		$checker_class = null;

		if ( $self_hosted ) {
			$service = 'GitLab';
		} else {
			$host                = wp_parse_url( $metadata_url, PHP_URL_HOST );
			$path                = wp_parse_url( $metadata_url, PHP_URL_PATH );
			$username_repo_regex = '@^/?([^/]+?)/([^/#?&]+?)/?$@';

			if ( preg_match( $username_repo_regex, $path ) ) {
				$known_services = array(
					'github.com'    => 'GitHub',
					'bitbucket.org' => 'BitBucket',
					'gitlab.com'    => 'GitLab',
				);

				if ( isset( $known_services[ $host ] ) ) {
					$service = $known_services[ $host ];
				}
			}
		}

		if ( $service ) {
			$checker_class = 'Proxuc_Vcs_' . $type . 'UpdateChecker';
			$api_class     = $service . 'Api';
		} else {
			trigger_error( //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				sprintf(
					'Proxuc could not find a supported service for %s',
					esc_html( $metadata_url )
				),
				E_USER_ERROR
			);
		}

		require UPSERV_PLUGIN_PATH . '/lib/plugin-update-checker/Puc/v5p3/Vcs/' . $api_class . '.php';

		$api_class = 'YahnisElsts\PluginUpdateChecker\v5p3\Vcs\\' . $api_class;
		$params    = array();

		if ( $file_name ) {
			$params = array(
				new $api_class( $metadata_url ),
				$slug,
				$file_name,
				$package_container,
				$option_name,
			);
		} else {
			$params = array(
				new $api_class( $metadata_url ),
				$slug,
				$package_container,
				$option_name,
			);
		}

		$update_checker = new $checker_class( ...$params );

		return $update_checker;
	}

	protected function init_update_checker( $slug ) {

		if ( $this->update_checker ) {
			return;
		}

		require_once UPSERV_PLUGIN_PATH . 'lib/proxy-update-checker/proxy-update-checker.php';

		$package_file_name = null;

		if ( 'Plugin' === $this->type ) {
			$package_file_name = $slug;
		} elseif ( 'Generic' === $this->type ) {
			$package_file_name = 'updatepulse-server';
		}

		$this->update_checker = self::build_update_checker(
			trailingslashit( $this->repository_service_url ) . $slug,
			$slug,
			$package_file_name,
			$this->type,
			$this->packageDirectory, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->repository_service_self_hosted
		);

		if ( $this->update_checker ) {

			if ( $this->repository_credentials ) {
				$this->update_checker->setAuthentication( $this->repository_credentials );
			}

			if ( $this->repository_branch ) {
				$this->update_checker->setBranch( $this->repository_branch );
			}
		}

		$this->update_checker = apply_filters(
			'upserv_update_checker',
			$this->update_checker,
			$slug,
			$this->type,
			$this->repository_service_url,
			$this->repository_branch,
			$this->repository_credentials,
			$this->repository_service_self_hosted
		);
	}

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

		if ( is_string( $this->repository_credentials ) ) {
			$params['headers'] = array(
				'Authorization' => 'token ' . $this->repository_credentials,
			);
		}

		$response = wp_safe_remote_get( $url, $params );

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $local_filename );
			php_log( $response, 'Invalid value for $response' );

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
				php_log( $md5_check, 'Invalid value for $md5_check' );

				return $md5_check;
			}
		}

		return $local_filename;
	}
}
