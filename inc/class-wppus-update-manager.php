<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WPPUS_Update_Manager {

	const WPPUS_DEFAULT_LOGS_MAX_SIZE    = 10;
	const WPPUS_DEFAULT_CACHE_MAX_SIZE   = 100;
	const WPPUS_DEFAULT_ARCHIVE_MAX_SIZE = 20;

	protected static $instance;
	public static $filesystem_clean_types = array(
		'cache',
		'logs',
	);

	protected $packages_table;
	protected $rows = array();

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			$parts     = explode( DIRECTORY_SEPARATOR, untrailingslashit( WPPUS_PLUGIN_PATH ) );
			$plugin_id = end( $parts ) . '/wp-plugin-update-server.php';

			add_action( 'admin_init', array( $this, 'init_request' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'plugin_options_menu_main' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'plugin_options_menu_help' ), 99, 0 );
			add_action( 'wp_ajax_wppus_force_clean', array( $this, 'force_clean' ), 10, 0 );
			add_action( 'wp_ajax_wppus_prime_package_from_remote', array( $this, 'prime_package_from_remote' ), 10, 0 );
			add_action( 'wp_ajax_wppus_manual_package_upload', array( $this, 'manual_package_upload' ), 10, 0 );
			add_action( 'load-toplevel_page_wppus-page', array( $this, 'add_page_options' ), 10, 0 );

			add_filter( 'set-screen-option', array( $this, 'set_page_options' ), 10, 3 );
			add_filter( 'plugin_action_links_' . $plugin_id, array( $this, 'add_action_links' ), 10, 1 );
		}
	}

	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init_request() {

		if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			$this->packages_table = new WPPUS_Packages_Table( $this );

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
				$delete_all_packages = isset( $_REQUEST['wppus_delete_all_packages'] ) ? true : false;
				$action              = false;

				if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					$action = $_REQUEST['action'];
				} elseif ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					$action = $_REQUEST['action2'];
				}

				if ( 'wppus-page' === $page ) {

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
						// @todo doc
						do_action( 'wppus_udpdate_manager_request_action', $action, $packages );
					}
				}
			}
		}
	}

	public function add_action_links( $links ) {
		$link = array(
			'<a href="' . admin_url( 'admin.php?page=wppus-page' ) . '">' . __( 'Packages overview', 'wppus' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=wppus-page-help' ) . '">' . __( 'Help', 'wppus' ) . '</a>',
		);

		return array_merge( $links, $link );
	}

	public function plugin_options_menu_main() {
		$page_title  = __( 'WP Plugin Update Server', 'wppus' );
		$menu_title  = $page_title;
		$capability  = 'manage_options';
		$menu_slug   = 'wppus-page';
		$parent_slug = $menu_slug;
		$function    = array( $this, 'plugin_main_page' );
		$icon        = 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNy44NSAxNS4zMSI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNhNGE0YTQ7fS5jbHMtMntmaWxsOiNhMGE1YWE7fTwvc3R5bGU+PC9kZWZzPjx0aXRsZT5VbnRpdGxlZC0xPC90aXRsZT48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0xMCwxMy41NGMyLjIzLDAsNC40NiwwLDYuNjksMCwuNjksMCwxLS4xNSwxLS45MSwwLTIuMzUsMC00LjcxLDAtNy4wNiwwLS42NC0uMi0uODctLjg0LS44NS0xLjEzLDAtMi4yNiwwLTMuMzksMC0uNDQsMC0uNjgtLjExLS42OC0uNjJzLjIzLS42My42OC0uNjJjMS40MSwwLDIuODEsMCw0LjIyLDAsLjgyLDAsMS4yMS40MywxLjIsMS4yNywwLDIuOTMsMCw1Ljg3LDAsOC44LDAsMS0uMjksMS4yNC0xLjI4LDEuMjVxLTIuNywwLTUuNDEsMGMtLjU0LDAtLjg1LjA5LS44NS43NXMuMzUuNzMuODcuNzFjLjgyLDAsMS42NSwwLDIuNDgsMCwuNDgsMCwuNzQuMTguNzUuNjlzLS40LjUxLS43NS41MUg1LjJjLS4zNSwwLS43OC4xMS0uNzUtLjVzLjI4LS43MS43Ni0uN2MuODMsMCwxLjY1LDAsMi40OCwwLC41NCwwLC45NSwwLC45NC0uNzRzLS40OC0uNzEtMS0uNzFIMi41MWMtMS4yMiwwLTEuNS0uMjgtMS41LTEuNTFRMSw5LjE1LDEsNWMwLTEuMTQuMzQtMS40NiwxLjQ5LTEuNDdINi40NGMuNCwwLC43LDAsLjcxLjU3cy0uMjEuNjgtLjcuNjdjLTEuMTMsMC0yLjI2LDAtMy4zOSwwLS41NywwLS44My4xNy0uODIuNzhxMCwzLjYyLDAsNy4yNGMwLC42LjIxLjguOC43OUM1LjM2LDEzLjUyLDcuNjgsMTMuNTQsMTAsMTMuNTRaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMSAtMi4xOSkiLz48cGF0aCBjbGFzcz0iY2xzLTIiIGQ9Ik0xMy4xLDkuMzhsLTIuNjIsMi41YS44MS44MSwwLDAsMS0xLjEyLDBMNi43NCw5LjM4YS43NC43NCwwLDAsMSwwLTEuMDguODIuODIsMCwwLDEsMS4xMywwTDkuMTMsOS41VjNhLjguOCwwLDAsMSwxLjU5LDBWOS41TDEyLDguM2EuODIuODIsMCwwLDEsMS4xMywwQS43NC43NCwwLDAsMSwxMy4xLDkuMzhaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMSAtMi4xOSkiLz48L3N2Zz4=';

		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon );

		$menu_title = __( 'Overview', 'wppus' );

		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	}

	public function plugin_options_menu_help() {
		$function    = array( $this, 'plugin_help_page' );
		$page_title  = __( 'WP Plugin Update Server - Help', 'wppus' );
		$menu_title  = __( 'Help', 'wppus' );
		$menu_slug   = 'wppus-page-help';
		$capability  = 'manage_options';
		$parent_slug = 'wppus-page';

		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	}

	public function add_page_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Packages per page', 'wppus' ),
			'default' => 10,
			'option'  => 'packages_per_page',
		);

		add_screen_option( $option, $args );
	}

	public function set_page_options( $status, $option, $value ) {

		return $value;
	}

	public function plugin_main_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$package_rows = $this->get_batch_package_info();

		$this->packages_table->set_rows( $package_rows );
		$this->packages_table->prepare_items();

		wppus_get_admin_template(
			'plugin-main-page.php',
			array(
				'updated'              => $this->plugin_options_handler(),
				'action_error'         => '',
				'default_cache_size'   => self::WPPUS_DEFAULT_LOGS_MAX_SIZE,
				'default_logs_size'    => self::WPPUS_DEFAULT_CACHE_MAX_SIZE,
				'default_archive_size' => self::WPPUS_DEFAULT_ARCHIVE_MAX_SIZE,
				'packages_table'       => $this->packages_table,
				'cache_size'           => self::get_dir_size_mb( 'cache' ),
				'logs_size'            => self::get_dir_size_mb( 'logs' ),
				'package_rows'         => $package_rows,
				'packages_dir'         => WPPUS_Data_Manager::get_data_dir( 'packages' ),
			)
		);
	}

	public function plugin_help_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wppus_get_admin_template(
			'plugin-help-page.php',
			array(
				'packages_dir' => WPPUS_Data_Manager::get_data_dir( 'packages' ),
			)
		);
	}

	public function force_clean() {
		$result = false;
		$type   = false;

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {
			$type = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( in_array( $type, self::$filesystem_clean_types, true ) ) {
				$result = WPPUS_Data_Manager::maybe_cleanup( $type, true );
			}
		}

		if ( $result && $type ) {
			wp_send_json_success( array( 'btnVal' => __( 'Force Clean', 'wppus' ) . ' (' . self::get_dir_size_mb( $type ) . ')' ) );
		} elseif ( in_array( $type, self::$filesystem_clean_types, true ) ) {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - check the directory is writable', 'wppus' )
			);

			wp_send_json_error( $error );
		}
	}

	public function prime_package_from_remote() {
		$result = false;
		$error  = false;
		$slug   = 'N/A';

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {
			$slug = filter_input( INPUT_POST, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( $slug ) {
				Wppus_Update_Server::unlock_update_from_remote( $slug );

				$api    = WPPUS_Update_API::get_instance();
				$result = $api->download_remote_package( $slug, 'Theme', true );

				if ( ! $result ) {
					Wppus_Update_Server::unlock_update_from_remote( $slug );

					$result = $api->download_remote_package( $slug, 'Plugin', true );
				}
			} else {
				$error = new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. Missing package slug - please reload the page and try again.', 'wppus' )
				);
			}
		} else {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - could not get remote package. The page has expired - please reload the page and try again.', 'wppus' )
			);
		}

		do_action( 'wppus_primed_package_from_remote', $result, $slug );

		if ( ! $error && $result ) {
			wp_send_json_success();
		} else {

			if ( ! $error ) {
				$error = new WP_Error(
					__METHOD__,
					__( 'Error - could not get remote package. Check if a repository with this slug exists and has a valid file structure.', 'wppus' )
				);
			}

			wp_send_json_error( $error );
		}
	}

	public function manual_package_upload() {
		$result      = false;
		$slug        = 'N/A';
		$parsed_info = false;
		$error_text  = __( 'Reload the page and try again.', 'wppus' );

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {

				return;
			}

			$package_info = isset( $_FILES['package'] ) ? $_FILES['package'] : false;
			$valid        = (bool) ( $package_info );

			if ( ! $valid ) {
				$error_text = __( 'Something very wrong happened.', 'wppus' );
			}

			$valid_archive_formats = array(
				'multipart/x-zip',
				'application/zip',
				'application/zip-compressed',
				'application/x-zip-compressed',
			);

			if ( $valid && ! in_array( $package_info['type'], $valid_archive_formats, true ) ) {
				$valid      = false;
				$error_text = __( 'Make sure the uploaded file is a zip archive.', 'wppus' );
			}

			if ( $valid && 0 !== absint( $package_info['error'] ) ) {
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
				$error_text = __( 'Make sure the uploaded file is not empty.', 'wppus' );
			}

			if ( $valid ) {
				$parsed_info = WshWordPressPackageParser_Extended::parsePackage( $package_info['tmp_name'], true );
			}

			if ( $valid && ! $parsed_info ) {
				$valid      = false;
				$error_text = __( 'The uploaded package is not a valid WordPress package, or if it is a plugin, the main plugin file could not be found.', 'wppus' );
			}

			if ( $valid ) {
				$source      = $package_info['tmp_name'];
				$filename    = $package_info['name'];
				$slug        = str_replace( '.zip', '', $filename );
				$type        = ucfirst( $parsed_info['type'] );
				$destination = WPPUS_Data_Manager::get_data_dir( 'packages' ) . $filename;

				Wppus_Update_Server::unlock_update_from_remote( $filename );

				$result = $wp_filesystem->move( $source, $destination, true );
			} else {
				$result = false;

				$wp_filesystem->delete( $package_info['tmp_name'] );
			}
		}

		do_action( 'wppus_did_manual_upload_package', $result, $type, $slug );

		if ( $result ) {
			wp_send_json_success();
		} else {
			$error = new WP_Error(
				__METHOD__,
				__( 'Error - could not upload the package. ', 'wppus' ) . "\n\n" . $error_text
			);

			wp_send_json_error( $error );
		}
	}

	public function delete_packages_bulk( $package_slugs = array() ) {
		$package_slugs         = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );
		$package_directory     = WPPUS_Data_Manager::get_data_dir( 'packages' );
		$package_paths         = glob( trailingslashit( $package_directory ) . '*.zip' );
		$package_names         = array();
		$deleted_package_slugs = array();
		$delete_all            = false;
		// @todo doc
		$package_paths = apply_filters( 'wppus_delete_packages_bulk_paths', $package_paths, $package_slugs );

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
			'server_directory'      => WPPUS_Data_Manager::get_data_dir(),
		);

		$update_server = new WPPUS_Update_Server(
			$config['use_remote_repository'],
			home_url( '/wppus-update-api/' ),
			$config['server_directory']
		);

		$update_server = apply_filters( 'wppus_update_server', $update_server, $config, '', '' );

		foreach ( $package_slugs as $slug ) {
			$package_name = $slug . '.zip';

			if ( in_array( $package_name, $package_names, true ) ) {
				$update_server_class = get_class( $update_server );
				$result              = false;

				if ( ! $update_server_class::is_update_from_remote_locked( $slug ) ) {
					$update_server_class::lock_update_from_remote( $slug );

					$result = $update_server->remove_package( $slug );

					$update_server_class::unlock_update_from_remote( $slug );
				}

				if ( $result ) {
					$deleted_package_slugs[] = $slug;

					unset( $this->rows[ $slug ] );
				}
			}
		}

		if ( ! empty( $deleted_package_slugs ) ) {
			// @todo doc
			do_action( 'wppus_update_manager_deleted_packages_bulk', $deleted_package_slugs );
		}
	}

	public function download_packages_bulk( $package_slugs ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return null;
		}

		$package_directory = WPPUS_Data_Manager::get_data_dir( 'packages' );
		$total_size        = 0;
		$max_archive_size  = get_option( 'wppus_archive_max_size', self::WPPUS_DEFAULT_ARCHIVE_MAX_SIZE );
		$package_slugs     = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );

		if ( 1 === count( $package_slugs ) ) {
			$archive_name = reset( $package_slugs );
			$archive_path = trailingslashit( $package_directory ) . $archive_name . '.zip';

			do_action( 'wppus_before_packages_download', $archive_name, $archive_path, $package_slugs );

			foreach ( $package_slugs as $package_slug ) {
				$total_size += filesize( trailingslashit( $package_directory ) . $package_slug . '.zip' );
			}

			if ( $max_archive_size < ( (float) ( $total_size / WPPUS_MB_TO_B ) ) ) {
				$this->packages_table->bulk_action_error = 'max_file_size_exceeded';

				return;
			}

			$this->trigger_packages_download( $archive_name, $archive_path );

			return;
		}

		$temp_directory = WPPUS_Data_Manager::get_data_dir( 'tmp' );
		$archive_name   = 'archive-' . time();
		$archive_path   = trailingslashit( $temp_directory ) . $archive_name . '.zip';

		// @todo doc
		do_action( 'wppus_before_packages_download_repack', $archive_name, $archive_path, $package_slugs );

		foreach ( $package_slugs as $package_slug ) {
			$total_size += filesize( trailingslashit( $package_directory ) . $package_slug . '.zip' );
		}

		if ( $max_archive_size < ( (float) ( $total_size / WPPUS_MB_TO_B ) ) ) {
			$this->packages_table->bulk_action_error = 'max_file_size_exceeded';

			return;
		}

		$zip = new ZipArchive();

		if ( ! $zip->open( $archive_path, ZIPARCHIVE::CREATE ) ) {

			return false;
		}

		foreach ( $package_slugs as $package_slug ) {
			$file = trailingslashit( $package_directory ) . $package_slug . '.zip';

			if ( $wp_filesystem->is_file( $file ) ) {
				$zip->addFromString( $package_slug . '.zip', $wp_filesystem->get_contents( $file ) );
			}
		}

		$zip->close();

		do_action( 'wppus_before_packages_download', $archive_name, $archive_path, $package_slugs );
		$this->trigger_packages_download( $archive_name, $archive_path );
	}

	protected static function get_dir_size_mb( $type ) {
		$result = 'N/A';

		if ( ! WPPUS_Data_Manager::is_valid_data_dir( $type ) ) {

			return $result;
		}

		$directory  = WPPUS_Data_Manager::get_data_dir( $type );
		$total_size = 0;

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			$total_size += $file->getSize();
		}

		$size = (float) ( $total_size / WPPUS_MB_TO_B );

		if ( $size < 0.01 ) {
			$result = '< 0.01 MB';
		} else {
			$result = number_format( $size, 2, '.', '' ) . 'MB';
		}

		return $result;
	}

	public function trigger_packages_download( $archive_name, $archive_path, $exit_or_die = true ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! empty( $archive_path ) && ! empty( $archive_name ) ) {

			if ( ini_get( 'zlib.output_compression' ) ) {
				@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
			}

			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $archive_name . '.zip"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length: ' . filesize( $archive_path ) );

			// @todo doc
			do_action( 'wppus_triggered_packages_download', $archive_name, $archive_path );

			echo $wp_filesystem->get_contents( $archive_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// @todo doc
		do_action( 'wppus_after_packages_download', $archive_name, $archive_path );

		if ( $exit_or_die ) {
			exit;
		}
	}

	protected function plugin_options_handler() {
		$errors = array();
		$result = '';

		if ( isset( $_REQUEST['wppus_plugin_options_handler_nonce'] ) && wp_verify_nonce( $_REQUEST['wppus_plugin_options_handler_nonce'], 'wppus_plugin_options' ) ) {
			$result  = __( 'WP Plugin Update Server options successfully updated', 'wppus' );
			$options = $this->get_submitted_options();

			foreach ( $options as $option_name => $option_info ) {
				$condition = $option_info['value'];

				if ( isset( $option_info['condition'] ) && 'number' === $option_info['condition'] ) {
					$condition = is_numeric( $option_info['value'] );
				}

				if ( $condition ) {
					update_option( $option_name, $option_info['value'] );
				} else {
					$errors[ $option_name ] = sprintf(
						// translators: %1$s is the option display name, %2$s is the condition for update
						__( 'Option %1$s was not updated. Reason: %2$s', 'wppus' ),
						$option_info['display_name'],
						$option_info['failure_display_message']
					);
				}
			}
		} elseif (
			isset( $_REQUEST['wppus_plugin_options_handler_nonce'] ) &&
			! wp_verify_nonce( $_REQUEST['wppus_plugin_options_handler_nonce'], 'wppus_plugin_options' )
		) {
			$errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'wppus' );
		}

		if ( ! empty( $errors ) ) {
			$result = $errors;
		}

		return $result;
	}

	protected function get_submitted_options() {

		return apply_filters(
			'wppus_submitted_data_config',
			array(
				'wppus_cache_max_size'   => array(
					'value'                   => filter_input( INPUT_POST, 'wppus_cache_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Cache max size (in MB)', 'wppus' ),
					'failure_display_message' => __( 'Not a valid number', 'wppus' ),
					'condition'               => 'number',
				),
				'wppus_logs_max_size'    => array(
					'value'                   => filter_input( INPUT_POST, 'wppus_logs_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Logs max size (in MB)', 'wppus' ),
					'failure_display_message' => __( 'Not a valid number', 'wppus' ),
					'condition'               => 'number',
				),
				'wppus_archive_max_size' => array(
					'value'                   => filter_input( INPUT_POST, 'wppus_archive_max_size', FILTER_VALIDATE_INT ),
					'display_name'            => __( 'Archive max size (in MB)', 'wppus' ),
					'failure_display_message' => __( 'Not a valid number', 'wppus' ),
					'condition'               => 'number',
				),
			)
		);
	}

	public function get_package_info( $slug ) {
		$package_info = wp_cache_get( 'package_info_' . $slug, 'wppus' );

		if ( false === $package_info ) {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {

				return;
			}

			$package_directory = WPPUS_Data_Manager::get_data_dir( 'packages' );

			// @todo doc
			do_action( 'wppus_get_package_info', $package_info, $slug, $package_directory . $slug . '.zip' );

			if ( $wp_filesystem->exists( $package_directory . $slug . '.zip' ) ) {
				$package = $this->get_package(
					$package_directory . $slug . '.zip',
					$slug
				);

				if ( $package ) {
					$package_info                       = $package->getMetadata();
					$package_info['type']               = isset( $package_info['details_url'] ) ? 'theme' : 'plugin';
					$package_info['file_name']          = $package_info['slug'] . '.zip';
					$package_info['file_path']          = $package_directory . $slug . '.zip';
					$package_info['file_size']          = $package->getFileSize();
					$package_info['file_last_modified'] = $package->getLastModified();
				}
			}

			wp_cache_set( 'package_info_' . $slug, $package_info, 'wppus' );
		}

		// @todo doc
		$package_info = apply_filters( 'wppus_package_info', $package_info, $slug );

		return $package_info;
	}

	public function get_batch_package_info( $search = false ) {
		$packages = wp_cache_get( 'packages', 'wppus' );

		if ( false === $packages ) {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {

				return;
			}

			$package_directory = WPPUS_Data_Manager::get_data_dir( 'packages' );
			$packages          = array();

			if ( $wp_filesystem->is_dir( $package_directory ) ) {

				if ( ! WPPUS_Package_API::is_doing_api_request() ) {
					$search = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : $search; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
							$meta    = $package->getMetadata();
							$include = true;

							if ( $search ) {

								if (
									false === strpos( strtolower( $meta['name'] ), strtolower( $search ) ) ||
									false === strpos( strtolower( $meta['slug'] ) . '.zip', strtolower( $search ) )
								) {
									$include = false;
								}
							}

							// @todo doc
							$include = apply_filters( 'wppus_batch_package_info_include', $include, $meta, $search );

							if ( $include ) {
								$packages[ $meta['slug'] ]                       = $meta;
								$packages[ $meta['slug'] ]['type']               = isset( $meta['details_url'] ) ? 'theme' : 'plugin';
								$packages[ $meta['slug'] ]['file_name']          = $meta['slug'] . '.zip';
								$packages[ $meta['slug'] ]['file_size']          = $package->getFileSize();
								$packages[ $meta['slug'] ]['file_last_modified'] = $package->getLastModified();
							}
						}
					}
				}
			}

			// @todo doc
			$packages = apply_filters( 'wppus_update_manager_batch_package_info', $packages, $search );

			wp_cache_set( 'packages', $packages, 'wppus' );
		}

		return $packages;
	}

	protected function get_package( $filename, $slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		$package = false;
		$cache   = new Wpup_FileCache( WPPUS_Data_Manager::get_data_dir( 'cache' ) );

		try {

			if ( $wp_filesystem->is_file( $filename ) && $wp_filesystem->is_readable( $filename ) ) {
				$cache_key    = 'metadata-b64-' . $slug . '-'
					. md5( $filename . '|' . filesize( $filename ) . '|' . filemtime( $filename ) );
				$cached_value = $cache->get( $cache_key );
			}

			if ( ! $cached_value ) {
				// @todo doc
				do_action( 'wppus_find_package_no_cache', $slug, $filename, $cache );
			}

			$package = Wpup_Package_Extended::fromArchive( $filename, $slug, $cache );
		} catch ( Exception $e ) {
			php_log( 'Corrupt archive ' . $filename . ' ; will not be displayed or delivered' );

			$log  = 'Exception caught: ' . $e->getMessage() . "\n";
			$log .= 'File: ' . $e->getFile() . "\n";
			$log .= 'Line: ' . $e->getLine() . "\n";

			php_log( $log );
		}

		return $package;
	}
}
