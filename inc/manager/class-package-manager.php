<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Exception;
use Anyape\UpdatePulse\Package_Parser\Parser;
use Anyape\UpdatePulse\Server\Server\Update\Cache;
use Anyape\UpdatePulse\Server\Server\Update\Package;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Server\Update\Update_Server;
use Anyape\UpdatePulse\Server\API\Package_API;
use Anyape\UpdatePulse\Server\API\Update_API;
use Anyape\UpdatePulse\Server\Table\Packages_Table;

class Package_Manager {

	const DEFAULT_LOGS_MAX_SIZE    = 10;
	const DEFAULT_CACHE_MAX_SIZE   = 100;
	const DEFAULT_ARCHIVE_MAX_SIZE = 20;

	public static $filesystem_clean_types = array(
		'cache',
		'logs',
	);

	protected static $instance;

	protected $packages_table;
	protected $rows = array();

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'admin_init', array( $this, 'admin_init' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 10, 0 );
			add_action( 'wp_ajax_upserv_force_clean', array( $this, 'force_clean' ), 10, 0 );
			add_action( 'wp_ajax_upserv_prime_package_from_remote', array( $this, 'prime_package_from_remote' ), 10, 0 );
			add_action( 'wp_ajax_upserv_manual_package_upload', array( $this, 'manual_package_upload' ), 10, 0 );
			add_action( 'load-toplevel_page_upserv-page', array( $this, 'add_page_options' ), 10, 0 );
			add_action( 'upserv_package_manager_pre_delete_package', array( $this, 'upserv_package_manager_pre_delete_package' ), 10, 1 );
			add_action( 'upserv_package_manager_deleted_package', array( $this, 'upserv_package_manager_deleted_package' ), 20, 1 );
			add_action( 'upserv_download_remote_package_aborted', array( $this, 'upserv_download_remote_package_aborted' ), 10, 3 );

			add_filter( 'upserv_admin_tab_links', array( $this, 'upserv_admin_tab_links' ), 10, 1 );
			add_filter( 'upserv_admin_tab_states', array( $this, 'upserv_admin_tab_states' ), 10, 2 );
			add_filter( 'set-screen-option', array( $this, 'set_page_options' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	public function admin_init() {

		if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			$this->packages_table = new Packages_Table( $this );

			if (
				(
					isset( $_REQUEST['_wpnonce'] ) &&
					wp_verify_nonce( $_REQUEST['_wpnonce'], $this->packages_table->nonce_action )
				) ||
				(
					isset( $_REQUEST['linknonce'] ) &&
					wp_verify_nonce( $_REQUEST['linknonce'], 'linknonce' )
				)
			) {
				$page                = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : false;
				$packages            = isset( $_REQUEST['packages'] ) ? $_REQUEST['packages'] : false;
				$delete_all_packages = isset( $_REQUEST['upserv_delete_all_packages'] ) ? true : false;
				$action              = false;

				if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					$action = $_REQUEST['action'];
				} elseif ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					$action = $_REQUEST['action2'];
				}

				if ( 'upserv-page' === $page ) {

					if ( $packages && 'download' === $action ) {
						$error = $this->download_packages_bulk( $packages );

						if ( $error ) {
							$this->packages_table->bulk_action_error = $error;
						}
					} elseif ( $packages && 'delete' === $action ) {
						$this->delete_packages_bulk( $packages );
					} elseif ( $delete_all_packages ) {
						$this->delete_packages_bulk();
					} else {
						do_action( 'upserv_udpdate_manager_request_action', $action, $packages );
					}
				}
			}
		}
	}

	public function admin_menu() {
		$page_title = __( 'UpdatePulse Server', 'updatepulse-server' );
		$capability = 'manage_options';
		$function   = array( $this, 'plugin_page' );
		$menu_title = __( 'Packages Overview', 'updatepulse-server' );

		add_submenu_page( 'upserv-page', $page_title, $menu_title, $capability, 'upserv-page', $function );
	}

	public function add_page_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Packages per page', 'updatepulse-server' ),
			'default' => 10,
			'option'  => 'packages_per_page',
		);

		add_screen_option( $option, $args );
	}

	public function set_page_options( $status, $option, $value ) {
		return $value;
	}

	public function upserv_admin_tab_links( $links ) {
		$links['main'] = array(
			admin_url( 'admin.php?page=upserv-page' ),
			"<span class='dashicons dashicons-media-archive'></span> " . __( 'Packages Overview', 'updatepulse-server' ),
		);

		return $links;
	}

	public function upserv_admin_tab_states( $states, $page ) {
		$states['main'] = 'upserv-page' === $page;

		return $states;
	}

	public function force_clean() {
		$result = false;
		$type   = false;

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'upserv_plugin_options' ) ) {
			$type = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( in_array( $type, self::$filesystem_clean_types, true ) ) {
				$result = Data_Manager::maybe_cleanup( $type, true );
			}
		}

		if ( $result && $type ) {
			wp_send_json_success( array( 'btnVal' => __( 'Force Clean', 'updatepulse-server' ) . ' (' . self::get_dir_size_mb( $type ) . ')' ) );
		} elseif ( in_array( $type, self::$filesystem_clean_types, true ) ) {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - check the directory is writable', 'updatepulse-server' )
			);

			wp_send_json_error( $error );
		}
	}

	public function upserv_download_remote_package_aborted( $safe_slug, $type, $info ) {
		wp_cache_set( 'upserv_download_remote_package_aborted', $info, 'updatepulse-server' );
	}

	public function prime_package_from_remote() {
		$result = false;
		$error  = false;
		$slug   = 'N/A';

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'upserv_plugin_options' ) ) {
			$slug = filter_input( INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( $slug ) {
				$api    = Update_API::get_instance();
				$result = $api->download_remote_package( $slug, null, true );
			} else {
				$error = new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. Missing package slug - please reload the page and try again.', 'updatepulse-server' )
				);
			}
		} else {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - could not get remote package. The page has expired - please reload the page and try again.', 'updatepulse-server' )
			);
		}

		if ( wp_cache_get( 'upserv_download_remote_package_aborted', 'updatepulse-server' ) ) {
			$api_config = Update_API::get_instance()->get_config();
			$error      = $api_config['repository_filter_packages'] ?
				new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. The package was filtered out because it is not linked to this server.', 'updatepulse-server' )
				) :
				new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. The package was found and is valid, but the download was aborted. Please check the package is satisfying custom the requirements for this server.', 'updatepulse-server' )
				);

			wp_cache_delete( 'upserv_download_remote_package_aborted', 'updatepulse-server' );
		}

		do_action( 'upserv_primed_package_from_remote', $result, $slug );

		if ( ! $error && $result ) {
			wp_send_json_success();
		} else {

			if ( ! $error ) {
				$error = new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. Check if a repository with this slug exists and has a valid file structure.', 'updatepulse-server' )
				);
			}

			wp_send_json_error( $error );
		}
	}

	public function manual_package_upload() {
		$result      = false;
		$slug        = 'N/A';
		$parsed_info = false;
		$error_text  = __( 'Reload the page and try again.', 'updatepulse-server' );

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'upserv_plugin_options' ) ) {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				return;
			}

			$package_info = isset( $_FILES['package'] ) ? $_FILES['package'] : false;
			$valid        = (bool) ( $package_info );

			if ( ! $valid ) {
				$error_text = __( 'Something very wrong happened.', 'updatepulse-server' );
			}

			$valid_archive_formats = array(
				'multipart/x-zip',
				'application/zip',
				'application/zip-compressed',
				'application/x-zip-compressed',
			);

			if ( $valid && ! in_array( $package_info['type'], $valid_archive_formats, true ) ) {
				$valid      = false;
				$error_text = __( 'Make sure the uploaded file is a zip archive.', 'updatepulse-server' );
			}

			if ( $valid && 0 !== abs( intval( $package_info['error'] ) ) ) {
				$valid = false;

				switch ( $package_info['error'] ) {
					case UPLOAD_ERR_INI_SIZE:
						$error_text = ( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.' );
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$error_text = ( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.' );
						break;

					case UPLOAD_ERR_PARTIAL:
						$error_text = ( 'The uploaded file was only partially uploaded.' );
						break;

					case UPLOAD_ERR_NO_FILE:
						$error_text = ( 'No file was uploaded.' );
						break;

					case UPLOAD_ERR_NO_TMP_DIR:
						$error_text = ( 'Missing a temporary folder.' );
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$error_text = ( 'Failed to write file to disk.' );
						break;

					case UPLOAD_ERR_EXTENSION:
						$error_text = ( 'A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.' );
						break;
				}
			}

			if ( $valid && 0 >= $package_info['size'] ) {
				$valid      = false;
				$error_text = __( 'Make sure the uploaded file is not empty.', 'updatepulse-server' );
			}

			if ( $valid ) {
				$parsed_info = Parser::parse_package( $package_info['tmp_name'], true );
			}

			if ( $valid && ! $parsed_info ) {
				$valid      = false;
				$error_text = __( 'The uploaded package is not a valid Generic, Theme or Plugin package.', 'updatepulse-server' );
			}

			if ( $valid ) {
				$source      = $package_info['tmp_name'];
				$filename    = $package_info['name'];
				$slug        = str_replace( '.zip', '', $filename );
				$type        = ucfirst( $parsed_info['type'] );
				$destination = Data_Manager::get_data_dir( 'packages' ) . $filename;
				$result      = $wp_filesystem->move( $source, $destination, true );
			} else {
				$result = false;

				$wp_filesystem->delete( $package_info['tmp_name'] );
			}
		}

		do_action( 'upserv_did_manual_upload_package', $result, $type, $slug );

		if ( $result ) {
			wp_send_json_success();
		} else {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - could not upload the package. ', 'updatepulse-server' ) . "\n\n" . $error_text
			);

			wp_send_json_error( $error );
		}
	}

	public function upserv_package_manager_pre_delete_package( $package_slug ) {
		$info = upserv_get_package_info( $package_slug, false );

		wp_cache_set( 'upserv_package_manager_pre_delete_package_info', $info, 'updatepulse-server' );
	}

	public function upserv_package_manager_deleted_package( $package_slug ) {
		$package_info = wp_cache_get( 'upserv_package_manager_pre_delete_package_info', 'updatepulse-server' );

		if ( $package_info ) {
			$payload = array(
				'event'       => 'package_deleted',
				// translators: %1$s is the package type, %2$s is the package slug
				'description' => sprintf( esc_html__( 'The package of type `%1$s` and slug `%2$s` has been deleted on UpdatePulse Server' ), $package_info['type'], $package_slug ),
				'content'     => $package_info,
			);

			upserv_schedule_webhook( $payload, 'package' );
		}
	}

	// Misc. -------------------------------------------------------

	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function plugin_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$package_rows = $this->get_batch_package_info();

		$this->packages_table->set_rows( $package_rows );
		$this->packages_table->prepare_items();

		wp_cache_set( 'settings_notice', $this->plugin_options_handler(), 'upserv' );
		upserv_get_admin_template(
			'plugin-packages-page.php',
			array(
				'action_error'         => '',
				'default_cache_size'   => self::DEFAULT_LOGS_MAX_SIZE,
				'default_logs_size'    => self::DEFAULT_CACHE_MAX_SIZE,
				'default_archive_size' => self::DEFAULT_ARCHIVE_MAX_SIZE,
				'packages_table'       => $this->packages_table,
				'cache_size'           => self::get_dir_size_mb( 'cache' ),
				'logs_size'            => self::get_dir_size_mb( 'logs' ),
				'package_rows'         => $package_rows,
				'packages_dir'         => Data_Manager::get_data_dir( 'packages' ),
			)
		);
	}

	public function delete_packages_bulk( $package_slugs = array() ) {
		$package_slugs         = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );
		$package_directory     = Data_Manager::get_data_dir( 'packages' );
		$package_paths         = glob( trailingslashit( $package_directory ) . '*.zip' );
		$package_names         = array();
		$deleted_package_slugs = array();
		$delete_all            = false;
		$package_paths         = apply_filters(
			'upserv_delete_packages_bulk_paths',
			$package_paths,
			$package_slugs
		);

		if ( ! empty( $package_paths ) ) {

			if ( empty( $package_slugs ) ) {
				$delete_all = true;
			}

			foreach ( $package_paths as $package_path ) {
				$package_name    = basename( $package_path );
				$package_names[] = $package_name;

				if ( $delete_all ) {
					$package_slugs[] = str_replace( '.zip', '', $package_name );
				}
			}
		} else {
			return;
		}

		$config = array(
			'use_remote_repository' => false,
			'server_directory'      => Data_Manager::get_data_dir(),
		);

		$update_server = new Update_Server(
			$config['use_remote_repository'],
			home_url( '/updatepulse-server-update-api/' ),
			$config['server_directory']
		);

		$update_server = apply_filters( 'upserv_update_server', $update_server, $config, '', '' );

		do_action( 'upserv_package_manager_pre_delete_packages_bulk', $package_slugs );

		foreach ( $package_slugs as $slug ) {
			$package_name = $slug . '.zip';

			if ( in_array( $package_name, $package_names, true ) ) {
				do_action( 'upserv_package_manager_pre_delete_package', $slug );

				$result = $update_server->remove_package( $slug );

				do_action( 'upserv_package_manager_deleted_package', $slug, $result );

				if ( $result ) {
					upserv_unwhitelist_package( $slug );

					$deleted_package_slugs[] = $slug;

					unset( $this->rows[ $slug ] );
				}
			}
		}

		if ( ! empty( $deleted_package_slugs ) ) {
			do_action( 'upserv_package_manager_deleted_packages_bulk', $deleted_package_slugs );
		}

		return empty( $deleted_package_slugs ) ? false : $deleted_package_slugs;
	}

	public function download_packages_bulk( $package_slugs ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return null;
		}

		$package_directory = Data_Manager::get_data_dir( 'packages' );
		$total_size        = 0;
		$max_archive_size  = get_option( 'upserv_archive_max_size', self::DEFAULT_ARCHIVE_MAX_SIZE );
		$package_slugs     = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );

		if ( 1 === count( $package_slugs ) ) {
			$archive_name = reset( $package_slugs );
			$archive_path = trailingslashit( $package_directory ) . $archive_name . '.zip';

			do_action( 'upserv_before_packages_download', $archive_name, $archive_path, $package_slugs );

			foreach ( $package_slugs as $package_slug ) {
				$total_size += filesize( trailingslashit( $package_directory ) . $package_slug . '.zip' );
			}

			if ( $max_archive_size < ( (float) ( $total_size / UPSERV_MB_TO_B ) ) ) {
				$this->packages_table->bulk_action_error = 'max_file_size_exceeded';

				return;
			}

			$this->trigger_packages_download( $archive_name, $archive_path );

			return;
		}

		$temp_directory = Data_Manager::get_data_dir( 'tmp' );
		$archive_name   = 'archive-' . time();
		$archive_path   = trailingslashit( $temp_directory ) . $archive_name . '.zip';

		do_action( 'upserv_before_packages_download_repack', $archive_name, $archive_path, $package_slugs );

		foreach ( $package_slugs as $package_slug ) {
			$total_size += filesize( trailingslashit( $package_directory ) . $package_slug . '.zip' );
		}

		if ( $max_archive_size < ( (float) ( $total_size / UPSERV_MB_TO_B ) ) ) {
			$this->packages_table->bulk_action_error = 'max_file_size_exceeded';

			return;
		}

		$zip = new ZipArchive();

		if ( ! $zip->open( $archive_path, ZIPARCHIVE::CREATE ) ) {
			return false;
		}

		foreach ( $package_slugs as $package_slug ) {
			$file = trailingslashit( $package_directory ) . $package_slug . '.zip';

			if ( is_file( $file ) ) {
				$zip->addFromString( $package_slug . '.zip', @file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		$zip->close();

		do_action( 'upserv_before_packages_download', $archive_name, $archive_path, $package_slugs );
		$this->trigger_packages_download( $archive_name, $archive_path );
	}

	public function trigger_packages_download( $archive_name, $archive_path, $exit_or_die = true ) {

		if ( ! empty( $archive_path ) && is_file( $archive_path ) && ! empty( $archive_name ) ) {

			if ( ini_get( 'zlib.output_compression' ) ) {
				@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
			}

			$md5 = md5_file( $archive_path );

			if ( $md5 ) {
				header( 'Content-MD5: ' . $md5 );
			}

			// Add Content-Digest or Repr-Digest header based on requested priority
			$digest_requested = isset( $_SERVER['HTTP_WANT_DIGEST'] ) && is_string( $_SERVER['HTTP_WANT_DIGEST'] ) ?
				$_SERVER['HTTP_WANT_DIGEST'] :
				( isset( $_SERVER['HTTP_WANT_REPR_DIGEST'] ) && is_string( $_SERVER['HTTP_WANT_REPR_DIGEST'] ) ?
					$_SERVER['HTTP_WANT_REPR_DIGEST'] :
					'sha-256=1'
				);
			$digest_field     = isset( $_SERVER['HTTP_WANT_DIGEST'] ) ?
				'Content-Digest' :
				( isset( $_SERVER['HTTP_WANT_REPR_DIGEST'] ) ? 'Repr-Digest' : 'Content-Digest' );

			if ( $digest_requested && $digest_field ) {
				$digests = array_map(
					function ( $digest ) {

						if ( strpos( $digest, '=' ) === false ) {
							return array( '', 0 ); // Return default value if '=' delimiter is missing
						}

						$parts = explode( '=', strtolower( trim( $digest ) ) );

						return array(
							$parts[0], // Algorithm
							isset( $parts[1] ) ? (int) $parts[1] : 0, // Priority
						);
					},
					explode( ',', $digest_requested )
				);

				$sha_digests = array_filter(
					$digests,
					function ( $digest ) {
						return ! empty( $digest[0] ) && str_starts_with( $digest[0], 'sha-' );
					}
				);

				// Find the digest with the highest priority
				$selected_digest = array_reduce(
					$sha_digests,
					function ( $carry, $item ) {
						return $carry[1] > $item[1] ? $carry : $item;
					},
					array( '', 0 )
				);

				if ( ! empty( $selected_digest[0] ) ) {
					$digest = str_replace( '-', '', $selected_digest[0] );

					if ( ! in_array( $digest, hash_algos(), true ) ) {
						$digest = '';
					}

					$hash = hash_file( $digest, $archive_path );

					if ( $hash ) {
						$safe_digest = htmlspecialchars( $digest, ENT_QUOTES, 'UTF-8' );
						$safe_hash   = htmlspecialchars( $hash, ENT_QUOTES, 'UTF-8' );

						header( "$digest_field: $safe_digest=$safe_hash" );
					}
				}
			}

			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $archive_name . '.zip"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length: ' . filesize( $archive_path ) );

			do_action( 'upserv_triggered_packages_download', $archive_name, $archive_path );

			echo @file_get_contents( $archive_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		do_action( 'upserv_after_packages_download', $archive_name, $archive_path );

		if ( $exit_or_die ) {
			exit;
		}
	}

	public function get_package_info( $slug ) {
		$package_info = wp_cache_get( 'package_info_' . $slug, 'updatepulse-server' );

		if ( false === $package_info ) {
			do_action( 'upserv_get_package_info', $package_info, $slug );

			if ( has_filter( 'upserv_package_manager_get_package_info' ) ) {
				$package_info = apply_filters( 'upserv_package_manager_get_package_info', $package_info, $slug );
			} else {
				$package_directory = Data_Manager::get_data_dir( 'packages' );

				if ( file_exists( $package_directory . $slug . '.zip' ) ) {
					$package = $this->get_package(
						$package_directory . $slug . '.zip',
						$slug
					);

					if ( $package ) {
						$package_info = $package->get_metadata();

						if ( ! isset( $package_info['type'] ) ) {
							$package_info['type'] = 'unknown';
						}

						$package_info['file_name']          = $package_info['slug'] . '.zip';
						$package_info['file_path']          = $package_directory . $slug . '.zip';
						$package_info['file_size']          = $package->get_file_size();
						$package_info['file_last_modified'] = $package->get_last_modified();
						$package_info['etag']               = hash_file( 'md5', $package_info['file_path'] );
						$package_info['digests']            = array(
							'sha1'   => hash_file( 'sha1', $package_info['file_path'] ),
							'sha256' => hash_file( 'sha256', $package_info['file_path'] ),
							'sha512' => hash_file( 'sha512', $package_info['file_path'] ),
							'crc32'  => hash_file( 'crc32b', $package_info['file_path'] ),
							'crc32c' => hash_file( 'crc32c', $package_info['file_path'] ),
						);
					}
				}
			}

			wp_cache_set( 'package_info_' . $slug, $package_info, 'updatepulse-server' );
		}

		$package_info = apply_filters( 'upserv_package_manager_package_info', $package_info, $slug );

		return $package_info;
	}

	public function get_batch_package_info( $search = false ) {
		$packages = wp_cache_get( 'packages', 'updatepulse-server' );

		if ( false === $packages ) {

			if ( has_filter( 'upserv_package_manager_get_batch_package_info' ) ) {
				$packages = apply_filters( 'upserv_package_manager_get_batch_package_info', $packages, $search );
			} else {
				$package_directory = Data_Manager::get_data_dir( 'packages' );
				$packages          = array();

				if ( is_dir( $package_directory ) ) {

					if ( ! Package_API::is_doing_api_request() ) {
						$search = isset( $_REQUEST['s'] ) ? // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							wp_unslash( trim( $_REQUEST['s'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							$search;
					}

					$package_paths = glob( trailingslashit( $package_directory ) . '*.zip' );

					if ( ! empty( $package_paths ) ) {

						foreach ( $package_paths as $package_path ) {
							$package = $this->get_package(
								$package_path,
								str_replace(
									array( trailingslashit( $package_directory ), '.zip' ),
									array( '', '' ),
									$package_path
								)
							);

							if ( $package ) {
								$meta    = $package->get_metadata();
								$include = true;

								if ( $search ) {

									if (
										false === strpos(
											strtolower( $meta['name'] ),
											strtolower( $search )
										) ||
										false === strpos(
											strtolower( $meta['slug'] ) . '.zip',
											strtolower( $search )
										)
									) {
										$include = false;
									}
								}

								$include = apply_filters(
									'upserv_batch_package_info_include',
									$include,
									$meta,
									$search
								);

								if ( $include ) {
									$idx                                    = $meta['slug'];
									$packages[ $idx ]                       = $meta;
									$packages[ $idx ]['file_name']          = $meta['slug'] . '.zip';
									$packages[ $idx ]['file_size']          = $package->get_file_size();
									$packages[ $idx ]['file_last_modified'] = $package->get_last_modified();
								}
							}
						}
					}
				}

				$packages = apply_filters( 'upserv_package_manager_batch_package_info', $packages, $search );
			}

			wp_cache_set( 'packages', $packages, 'updatepulse-server' );
		}

		if ( empty( $packages ) ) {
			$packages = array();
		}

		return $packages;
	}

	public function is_package_whitelisted( $package_slug ) {
		return apply_filters(
			'upserv_is_package_whitelisted',
			is_file(
				trailingslashit( upserv_get_whitelist_data_dir() )
				. sanitize_file_name( $package_slug . '.json' )
			),
			$package_slug
		);
	}

	public function whitelist_package( $package_slug, $repository_service_url ) {
		$whitelist = upserv_get_whitelist_data_dir();
		$filename  = sanitize_file_name( $package_slug . '.json' );
		$data      = apply_filters(
			'upserv_whitelist_package_data',
			array(
				'repository_service_url' => $repository_service_url,
				'timestamp'              => time(),
			),
			$package_slug
		);

		$result = (bool) file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			trailingslashit( $whitelist ) . $filename,
			wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			FS_CHMOD_FILE
		);

		do_action(
			'upserv_whitelist_package',
			$package_slug,
			$repository_service_url,
			$result
		);

		return $result;
	}

	public function unwhitelist_package( $package_slug ) {
		$whitelist = upserv_get_whitelist_data_dir();
		$filename  = sanitize_file_name( $package_slug . '.json' );

		WP_Filesystem();

		global $wp_filesystem;

		$result = (bool) $wp_filesystem->delete( trailingslashit( $whitelist ) . $filename );

		do_action(
			'upserv_unwhitelist_package',
			$package_slug,
			$result
		);

		return $result;
	}

	public function get_package_whitelist_info( $package_slug, $json_encode = true ) {
		$whitelist = upserv_get_whitelist_data_dir();
		$filename  = sanitize_file_name( $package_slug . '.json' );
		$data      = $json_encode ? '{}' : array();

		if ( is_file( trailingslashit( $whitelist ) . $filename ) ) {
			$data = @file_get_contents( trailingslashit( $whitelist ) . $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged

			if ( ! $json_encode ) {
				$data = json_decode( $data, true );
			}
		}

		return apply_filters(
			'upserv_get_package_whitelist_info',
			$data,
			$package_slug
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function get_dir_size_mb( $type ) {
		$result = 'N/A';

		if ( ! Data_Manager::is_valid_data_dir( $type ) ) {
			return $result;
		}

		$directory  = Data_Manager::get_data_dir( $type );
		$total_size = 0;

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			$total_size += $file->getSize();
		}

		$size = (float) ( $total_size / UPSERV_MB_TO_B );

		if ( $size < 0.01 ) {
			$result = '< 0.01 MB';
		} else {
			$result = number_format( $size, 2, '.', '' ) . 'MB';
		}

		return $result;
	}

	protected function plugin_options_handler() {
		$errors = array();
		$result = '';

		if ( isset( $_REQUEST['upserv_plugin_options_handler_nonce'] ) && wp_verify_nonce( $_REQUEST['upserv_plugin_options_handler_nonce'], 'upserv_plugin_options' ) ) {
			$result  = __( 'UpdatePulse Server options successfully updated', 'updatepulse-server' );
			$options = $this->get_submitted_options();

			foreach ( $options as $option_name => $option_info ) {
				$condition = $option_info['value'];

				if ( isset( $option_info['condition'] ) && 'number' === $option_info['condition'] ) {
					$condition = is_numeric( $option_info['value'] );
				}

				$condition = apply_filters(
					'upserv_package_option_update',
					$condition,
					$option_name,
					$option_info,
					$options
				);

				if ( $condition ) {
					update_option( $option_name, $option_info['value'] );
				} else {
					$errors[ $option_name ] = sprintf(
						// translators: %1$s is the option display name, %2$s is the condition for update
						__( 'Option %1$s was not updated. Reason: %2$s', 'updatepulse-server' ),
						$option_info['display_name'],
						$option_info['failure_display_message']
					);
				}
			}
		} elseif (
			isset( $_REQUEST['upserv_plugin_options_handler_nonce'] ) &&
			! wp_verify_nonce( $_REQUEST['upserv_plugin_options_handler_nonce'], 'upserv_plugin_options' )
		) {
			$errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'updatepulse-server' );
		}

		if ( ! empty( $errors ) ) {
			$result = $errors;
		}

		do_action( 'upserv_package_options_updated', $errors );

		return $result;
	}

	protected function get_submitted_options() {

		return apply_filters(
			'upserv_submitted_package_config',
			array(
				'upserv_cache_max_size'   => array(
					'value'                   => filter_input( INPUT_POST, 'upserv_cache_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Cache max size (in MB)', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid number', 'updatepulse-server' ),
					'condition'               => 'number',
				),
				'upserv_logs_max_size'    => array(
					'value'                   => filter_input( INPUT_POST, 'upserv_logs_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Logs max size (in MB)', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid number', 'updatepulse-server' ),
					'condition'               => 'number',
				),
				'upserv_archive_max_size' => array(
					'value'                   => filter_input( INPUT_POST, 'upserv_archive_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Archive max size (in MB)', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid number', 'updatepulse-server' ),
					'condition'               => 'number',
				),
			)
		);
	}

	protected function get_package( $filename, $slug ) {
		$package      = false;
		$cache        = new Cache( Data_Manager::get_data_dir( 'cache' ) );
		$cached_value = null;

		try {

			if ( is_file( $filename ) && is_readable( $filename ) ) {
				$cache_key    = 'metadata-b64-' . $slug . '-'
					. md5( $filename . '|' . filesize( $filename ) . '|' . filemtime( $filename ) );
				$cached_value = $cache->get( $cache_key );
			}

			if ( null === $cached_value ) {
				do_action( 'upserv_find_package_no_cache', $slug, $filename, $cache );
			}

			$package = Package::from_archive( $filename, $slug, $cache );
		} catch ( Exception $e ) {
			php_log( 'Corrupt archive ' . $filename . ' ; package will not be displayed or delivered' );

			$log  = 'Exception caught: ' . $e->getMessage() . "\n";
			$log .= 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";

			php_log( $log );
		}

		return $package;
	}
}
