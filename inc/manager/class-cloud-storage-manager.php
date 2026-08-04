<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Exception;
use WP_Error;
use PhpS3\PhpS3;
use PhpS3\PhpS3Exception;
use Anyape\UpdatePulse\Server\API\Package_API;
use Anyape\UpdatePulse\Server\Server\Update\Zip_Metadata_Parser;
use Anyape\UpdatePulse\Server\Server\Update\Invalid_Package_Exception;
use Anyape\UpdatePulse\Server\Server\Update\Cache;
use Anyape\UpdatePulse\Server\Server\Update\Package;
use Anyape\UpdatePulse\Package_Parser\Parser;
use Anyape\Utils\Utils;

/**
 * Cloud Storage Manager class
 *
 * Handles integration with S3-compatible cloud storage for package management.
 *
 * @since 1.0.0
 */
class Cloud_Storage_Manager {

	/**
	 * Instance of the Cloud Storage Manager
	 *
	 * @var Cloud_Storage_Manager|null
	 * @since 1.0.0
	 */
	protected static $instance;
	/**
	 * Cloud storage configuration
	 *
	 * @var array|null
	 * @since 1.0.0
	 */
	protected static $config;
	/**
	 * Cloud storage client instance
	 *
	 * @var PhpS3|null
	 * @since 1.0.0
	 */
	protected static $cloud_storage;
	/**
	 * Virtual directory path in cloud storage
	 *
	 * @var string|null
	 * @since 1.0.0
	 */
	protected static $virtual_dir;
	/**
	 * Hooks registered by the manager
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected static $hooks = array();

	/**
	 * Whether we're currently performing a redirect
	 *
	 * @var bool
	 * @since 1.0.0
	 */
	protected $doing_redirect = false;

	/**
	 * Download URL lifetime in seconds
	 */
	public const DOWNLOAD_URL_LIFETIME = MINUTE_IN_SECONDS;

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {
		$config = self::get_config();

		if ( $config['use_cloud_storage'] ) {
			$this->init_manager( $config );
		}

		if ( $init_hooks ) {

			if ( ! upserv_is_doing_api_request() ) {
				add_action( 'wp_ajax_upserv_cloud_storage_test', array( $this, 'cloud_storage_test' ), 10, 0 );
				add_action( 'upserv_package_options_updated', array( $this, 'upserv_package_options_updated' ), 10, 0 );
				add_action( 'upserv_template_package_manager_option_before_miscellaneous', array( $this, 'upserv_template_package_manager_option_before_miscellaneous' ), 10, 0 );

				add_filter( 'upserv_admin_scripts', array( $this, 'upserv_admin_scripts' ), 10, 1 );
				add_filter( 'upserv_submitted_package_config', array( $this, 'upserv_submitted_package_config' ), 10, 1 );
				add_filter( 'upserv_package_option_update', array( $this, 'upserv_package_option_update' ), 10, 4 );
			}

			if ( $config['use_cloud_storage'] ) {
				$this->add_hooks();
			} else {
				$this->remove_hooks();
			}
		}
	}

	/**
	 * Initialize cloud storage manager
	 *
	 * @param array $config Cloud storage configuration
	 * @since 1.0.0
	 */
	protected function init_manager( $config ) {

		if ( ! self::$cloud_storage instanceof PhpS3 ) {
			self::$cloud_storage = new PhpS3(
				$config['access_key'],
				$config['secret_key'],
				true,
				$config['endpoint'],
				$config['region'],
			);

			self::$cloud_storage->setExceptions();

			/**
			 * Filter the virtual directory path used in cloud storage.
			 *
			 * @param string $virtual_dir The default virtual directory name
			 * @return string The filtered virtual directory name
			 * @since 1.0.0
			 */
			self::$virtual_dir = apply_filters( 'upserv_cloud_storage_virtual_dir', 'updatepulse-packages' );
		}
	}

	/**
	 * Add hooks for cloud storage functionality
	 *
	 * @since 1.0.0
	 */
	protected function add_hooks() {

		if ( ! empty( self::$hooks ) ) {
			return;
		}

		self::$hooks = array(
			'actions' => array(
				array( 'upserv_saved_remote_package_to_local', 'upserv_saved_remote_package_to_local', PHP_INT_MIN + 100, 3 ),
				array( 'upserv_find_package_no_cache', 'upserv_find_package_no_cache', 10, 3 ),
				array( 'upserv_update_server_action_download', 'upserv_update_server_action_download', 10, 1 ),
				array( 'upserv_after_packages_download', 'upserv_after_packages_download', 10, 2 ),
				array( 'upserv_before_packages_download_repack', 'upserv_before_packages_download_repack', 10, 3 ),
				array( 'upserv_before_packages_download', 'upserv_before_packages_download', 10, 3 ),
				array( 'upserv_package_api_request', 'upserv_package_api_request', 10, 2 ),
			),
			'filters' => array(
				array( 'upserv_save_remote_to_local', 'upserv_save_remote_to_local', 10, 4 ),
				array( 'upserv_check_remote_package_update_local_meta', 'upserv_check_remote_package_update_local_meta', 10, 3 ),
				array( 'upserv_zip_metadata_parser_cache_key', 'upserv_zip_metadata_parser_cache_key', 10, 3 ),
				array( 'upserv_package_manager_get_batch_package_info', 'upserv_package_manager_get_batch_package_info', 10, 2 ),
				array( 'upserv_package_manager_get_package_info', 'upserv_package_manager_get_package_info', 10, 2 ),
				array( 'upserv_update_server_action_download_handled', 'upserv_update_server_action_download_handled', 10, 1 ),
				array( 'upserv_remove_package_result', 'upserv_remove_package_result', 10, 3 ),
				array( 'upserv_webhook_package_exists', 'upserv_webhook_package_exists', 10, 3 ),
				array( 'upserv_is_package_whitelisted', 'upserv_is_package_whitelisted', PHP_INT_MIN + 100, 2 ),
				array( 'upserv_whitelist_package_data', 'upserv_whitelist_package_data', 10, 2 ),
				array( 'upserv_unwhitelist_package_data', 'upserv_unwhitelist_package_data', 10, 2 ),
			),
		);

		if ( ! upserv_is_doing_api_request() ) {
			self::$hooks['actions'] = array_merge(
				self::$hooks['actions'],
				array(
					array( 'upserv_did_manual_upload_package', 'upserv_did_manual_upload_package', PHP_INT_MIN + 100, 3 ),
				)
			);
			self::$hooks['filters'] = array_merge(
				self::$hooks['filters'],
				array(
					array( 'upserv_get_admin_template_args', 'upserv_get_admin_template_args', 10, 2 ),
					array( 'upserv_delete_packages_bulk_paths', 'upserv_delete_packages_bulk_paths', 10, 1 ),
				)
			);
		}

		// Register actions.
		foreach ( self::$hooks['actions'] as $hook ) {
			$accepted_args = isset( $hook[3] ) ? $hook[3] : 0;

			add_action( $hook[0], array( $this, $hook[1] ), $hook[2], $accepted_args );
		}

		// Register filters.
		foreach ( self::$hooks['filters'] as $hook ) {
			$accepted_args = isset( $hook[3] ) ? $hook[3] : 1;

			add_filter( $hook[0], array( $this, $hook[1] ), $hook[2], $accepted_args );
		}
	}

	/**
	 * Remove hooks for cloud storage functionality
	 *
	 * @since 1.0.0
	 */
	protected function remove_hooks() {

		if ( empty( self::$hooks ) ) {
			return;
		}

		// Remove actions.
		foreach ( self::$hooks['actions'] as $hook ) {
			remove_action( $hook[0], array( $this, $hook[1] ), $hook[2] );
		}

		// Remove filters.
		foreach ( self::$hooks['filters'] as $hook ) {
			remove_filter( $hook[0], array( $this, $hook[1] ), $hook[2] );
		}

		self::$hooks = array();
	}

	/**
	 * Get cloud storage configuration
	 *
	 * @param boolean $force Whether to force reload the configuration
	 * @return array Cloud storage configuration
	 * @since 1.0.0
	 */
	public static function get_config( $force = false ) {

		if ( $force || ! self::$config ) {
			$config                      = upserv_get_option( 'cloud_storage' );
			$config['use_cloud_storage'] = upserv_get_option( 'use_cloud_storage' );

			self::$config = $config;
		}

		/**
		 * Filter the configuration of the Cloud Storage Manager.
		 *
		 * @param array $config The configuration of the Cloud Storage Manager
		 * @return array The filtered configuration
		 * @since 1.0.0
		 */
		return apply_filters( 'upserv_cloud_storage_config', self::$config );
	}

	/**
	 * Get Cloud Storage Manager instance
	 *
	 * @return Cloud_Storage_Manager The singleton instance
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add cloud storage-specific scripts to admin
	 *
	 * @param array $scripts Existing registered scripts
	 * @return array Modified scripts array
	 * @since 1.0.0
	 */
	public function upserv_admin_scripts( $scripts ) {
		$page = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'upserv-page' !== $page ) {
			return $scripts;
		}

		$scripts['cloud-storage'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'js/admin/cloud-storage' . upserv_assets_suffix() . '.js',
			'uri'  => UPSERV_PLUGIN_URL . 'js/admin/cloud-storage' . upserv_assets_suffix() . '.js',
			'deps' => array( 'jquery' ),
		);

		return $scripts;
	}

	/**
	 * Process submitted package configurations
	 *
	 * @param array $config Existing configuration
	 * @return array Modified configuration
	 * @since 1.0.0
	 */
	public function upserv_submitted_package_config( $config ) {
		$config = array_merge(
			$config,
			array(
				'upserv_use_cloud_storage'        => array(
					'value'                   => filter_input( INPUT_POST, 'upserv_use_cloud_storage', FILTER_VALIDATE_BOOLEAN ),
					'display_name'            => __( 'Use Cloud Storage', 'updatepulse-server' ),
					'failure_display_message' => __( 'Something went wrong', 'updatepulse-server' ),
					'condition'               => 'boolean',
					'path'                    => 'use_cloud_storage',
				),
				'upserv_cloud_storage_access_key' => array(
					'value'                   => sanitize_text_field(
						wp_unslash(
							filter_input( INPUT_POST, 'upserv_cloud_storage_access_key' )
						)
					),
					'display_name'            => __( 'Cloud Storage Access Key', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid string', 'updatepulse-server' ),
					'condition'               => 'use-cloud-storage',
					'path'                    => 'cloud_storage/access_key',
				),
				'upserv_cloud_storage_secret_key' => array(
					'value'                   => sanitize_text_field(
						wp_unslash(
							filter_input( INPUT_POST, 'upserv_cloud_storage_secret_key' )
						)
					),
					'display_name'            => __( 'Cloud Storage Secret Key', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid string', 'updatepulse-server' ),
					'condition'               => 'use-cloud-storage',
					'path'                    => 'cloud_storage/secret_key',
				),
				'upserv_cloud_storage_endpoint'   => array(
					'value'                   => sanitize_text_field(
						wp_unslash(
							filter_input( INPUT_POST, 'upserv_cloud_storage_endpoint' )
						)
					),
					'display_name'            => __( 'Cloud Storage Endpoint', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid string', 'updatepulse-server' ),
					'condition'               => 'use-cloud-storage',
					'path'                    => 'cloud_storage/endpoint',
				),
				'upserv_cloud_storage_unit'       => array(
					'value'                   => sanitize_text_field(
						wp_unslash(
							filter_input( INPUT_POST, 'upserv_cloud_storage_unit' )
						)
					),
					'display_name'            => __( 'Cloud Storage Unit', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid string', 'updatepulse-server' ),
					'condition'               => 'use-cloud-storage',
					'path'                    => 'cloud_storage/storage_unit',
				),
				'upserv_cloud_storage_region'     => array(
					'value'                   => sanitize_text_field(
						wp_unslash(
							filter_input( INPUT_POST, 'upserv_cloud_storage_region' )
						)
					),
					'display_name'            => __( 'Cloud Storage Region', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid string', 'updatepulse-server' ),
					'condition'               => 'use-cloud-storage',
					'path'                    => 'cloud_storage/region',
				),
			)
		);

		return $config;
	}

	/**
	 * Validate package option updates
	 *
	 * @param boolean $condition Current validation condition
	 * @param string  $option_name Option being updated
	 * @param array   $option_info Option information
	 * @param array   $options All options being processed
	 * @return boolean Whether option is valid
	 * @since 1.0.0
	 */
	public function upserv_package_option_update( $condition, $option_name, $option_info, $options ) {

		if ( 'use-cloud-storage' === $option_info['condition'] ) {

			if (
				'upserv_cloud_storage_region' === $option_name &&
				empty( $option_info['value'] )
			) {
				$condition = true;
			} elseif ( $options['upserv_use_cloud_storage']['value'] ) {
				$condition = ! empty( $option_info['value'] );
			} else {

				if ( 'upserv_cloud_storage_endpoint' === $option_name ) {
					$condition = filter_var( 'http://' . $option_info['value'], FILTER_SANITIZE_URL );
				} else {
					$condition = true;
				}

				$option_info['value'] = '';
			}

			if ( ! $condition ) {
				upserv_update_option( 'use_cloud_storage', false );
			}
		} else {
			$condition = true;
		}

		return $condition;
	}

	/**
	 * Render cloud storage options in template
	 *
	 * @since 1.0.0
	 */
	public function upserv_template_package_manager_option_before_miscellaneous() {
		$options = array(
			'access_key'   => upserv_get_option( 'cloud_storage/access_key' ),
			'secret_key'   => upserv_get_option( 'cloud_storage/secret_key' ),
			'endpoint'     => upserv_get_option( 'cloud_storage/endpoint' ),
			'storage_unit' => upserv_get_option( 'cloud_storage/storage_unit' ),
			'region'       => upserv_get_option( 'cloud_storage/region' ),
		);

		upserv_get_admin_template(
			'cloud-storage-options.php',
			array(
				'use_cloud_storage' => upserv_get_option( 'use_cloud_storage' ),
				'virtual_dir'       => self::$virtual_dir,
				'options'           => $options,
			)
		);
	}

	/**
	 * Add cloud storage paths to bulk delete
	 *
	 * @param array $package_paths Current package paths
	 * @return array Modified package paths
	 * @since 1.0.0
	 */
	public function upserv_delete_packages_bulk_paths( $package_paths ) {
		$config = self::get_config();

		try {
			$contents = self::$cloud_storage->getBucket( $config['storage_unit'], self::$virtual_dir . '/' );

			unset( $contents[ self::$virtual_dir . '/' ] );

			if ( ! empty( $contents ) ) {

				foreach ( $contents as $item ) {
					$package_paths[] = $item['name'];
				}
			}
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'  => $e->getMessage(),
					'file'   => $e->getFile(),
					'line'   => $e->getLine(),
					'caller' => $e->getTrace()[1],
				)
			);
		}

		return $package_paths;
	}

	/**
	 * Check if package exists in cloud storage
	 *
	 * @param boolean $package_exists Current existence state
	 * @param array   $payload Request payload
	 * @param string  $slug Package slug
	 * @return boolean|null Whether package exists in cloud storage
	 * @since 1.0.0
	 */
	public function upserv_webhook_package_exists( $package_exists, $payload, $slug ) {

		if ( null !== $package_exists ) {
			return $package_exists;
		}

		$config = self::get_config();

		try {
			$info = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );

			if ( false === $info ) {
				$info = self::$cloud_storage->getObjectInfo(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
				);

				wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
			}

			return null === $info ? $info : (bool) $info;
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'  => $e->getMessage(),
					'file'   => $e->getFile(),
					'line'   => $e->getLine(),
					'caller' => $e->getTrace()[1],
					'slug'   => $slug,
				)
			);

			return $package_exists;
		}
	}

	/**
	 * Process package removal from cloud storage
	 *
	 * @param boolean $result Current removal result
	 * @param string  $type Package type
	 * @param string  $slug Package slug
	 * @return boolean Whether removal was successful
	 * @since 1.0.0
	 */
	public function upserv_remove_package_result( $result, $type, $slug ) {
		$config = self::get_config();

		try {
			$info = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );

			if ( false === $info ) {
				$info = self::$cloud_storage->getObjectInfo(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
				);

				wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
			}

			$package_directory = Data_Manager::get_data_dir( 'packages' );
			$filename          = $package_directory . $slug . '.zip';
			$cache_key         = self::build_cache_key( $slug, $filename );

			if ( $cache_key ) {
				$cache = new Cache( Data_Manager::get_data_dir( 'cache' ) );

				$cache->clear( $cache_key );
			}

			$result = self::$cloud_storage->deleteObject(
				$config['storage_unit'],
				self::$virtual_dir . '/' . $slug . '.zip'
			);
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'  => $e->getMessage(),
					'file'   => $e->getFile(),
					'line'   => $e->getLine(),
					'caller' => $e->getTrace()[1],
					'slug'   => $slug,
				)
			);
		}

		return $result;
	}

	/**
	 * Modify admin template arguments
	 *
	 * @param array  $args Current template arguments
	 * @param string $template_name Template being rendered
	 * @return array Modified template arguments
	 * @since 1.0.0
	 */
	public function upserv_get_admin_template_args( $args, $template_name ) {
		$template_names = array( 'plugin-packages-page.php', 'plugin-help-page.php', 'plugin-remote-sources-page.php' );

		if ( in_array( $template_name, $template_names, true ) ) {
			$args['packages_dir'] = 'CloudStorageUnit://' . self::$virtual_dir . '/';
		}

		return $args;
	}

	/**
	 * Test cloud storage connectivity
	 *
	 * AJAX handler for testing cloud storage configuration
	 *
	 * @since 1.0.0
	 */
	public function cloud_storage_test() {
		$result = array();
		$nonce  = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'nonce' ) ) );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'upserv_plugin_options' ) ) {
			wp_send_json_error(
				new WP_Error(
					__METHOD__,
					__( 'Error - Received invalid data; please reload the page and try again.', 'updatepulse-server' )
				)
			);
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$data = $data ? array_map( 'sanitize_text_field', wp_unslash( $data ) ) : false;

		if ( ! $data ) {
			wp_send_json_error(
				new WP_Error(
					__METHOD__,
					__( 'Error - Received invalid data; please reload the page and try again.', 'updatepulse-server' )
				)
			);
		}

		$config = self::get_config( true );

		$this->init_manager( $config );

		$access_key   = $data['upserv_cloud_storage_access_key'];
		$secret_key   = $data['upserv_cloud_storage_secret_key'];
		$endpoint     = $data['upserv_cloud_storage_endpoint'];
		$storage_unit = $data['upserv_cloud_storage_unit'];
		$region       = $data['upserv_cloud_storage_region'];

		self::$cloud_storage->setAuth( $access_key, $secret_key );
		self::$cloud_storage->setEndpoint( $endpoint );
		self::$cloud_storage->setRegion( $region );

		try {
			$storage_units = self::$cloud_storage->listBuckets();

			if ( ! in_array( $storage_unit, $storage_units, true ) ) {
				wp_send_json_error(
					new WP_Error(
						__METHOD__,
						__( 'Error - Storage Unit not found', 'updatepulse-server' )
					)
				);
			}

			$result[] = __( 'Cloud Storage Service was reached sucessfully.', 'updatepulse-server' );

			if ( ! $this->virtual_folder_exists( self::$virtual_dir ) ) {
				$created  = $this->create_virtual_folder( self::$virtual_dir, $storage_unit );
				$result[] = $created ?
					sprintf(
						// translators: %s is the virtual folder
						esc_html__( 'Virtual folder "%s" was created successfully.', 'updatepulse-server' ),
						self::$virtual_dir,
					) :
					sprintf(
						// translators: %s is the virtual folder
						esc_html__( 'WARNING: Unable to create Virtual folder "%s". The Cloud Storage feature may not work as expected. Try to create it manually and test again.', 'updatepulse-server' ),
						self::$virtual_dir,
					);
			} else {
				$result[] = sprintf(
					// translators: %s is the virtual folder
					esc_html__( 'Virtual folder "%s" found.', 'updatepulse-server' ),
					self::$virtual_dir,
				);
			}
		} catch ( PhpS3Exception $e ) {
			$result = new WP_Error(
				__METHOD__ . ' => PhpS3Exception',
				$e->getMessage()
			);

			$result->add( __METHOD__ . ' => LF', '' );
			$result->add( __METHOD__, __( 'An error occured when attempting to communicate with the Cloud Storage Service. Please check all the settings and try again.', 'updatepulse-server' ) );
		}

		if ( ! is_wp_error( $result ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Handle package options update
	 *
	 * Set up cloud storage after options are updated
	 *
	 * @since 1.0.0
	 */
	public function upserv_package_options_updated() {
		$config = self::get_config( true );

		if ( ! $config['use_cloud_storage'] ) {
			$this->remove_hooks();

			return;
		} else {
			$this->init_manager( $config );
		}

		$this->add_hooks();

		try {

			if ( ! $this->virtual_folder_exists( self::$virtual_dir ) ) {

				if ( ! $this->create_virtual_folder( self::$virtual_dir ) ) {
					Utils::php_log(
						sprintf(
							// translators: %s is the virtual folder
							esc_html__( 'WARNING: Unable to create Virtual folder "%s". The Cloud Storage feature may not work as expected. Try to create it manually and test again.', 'updatepulse-server' ),
							self::$virtual_dir,
						)
					);
				}
			}
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'  => $e->getMessage(),
					'file'   => $e->getFile(),
					'line'   => $e->getLine(),
					'caller' => $e->getTrace()[1],
				)
			);
		}
	}

	/**
	 * Update local package metadata from cloud storage
	 *
	 * @param array|false $local_meta Current local metadata
	 * @param Package     $local_package Local package instance
	 * @param string      $slug Package slug
	 * @return array|false Updated metadata or false
	 * @since 1.0.0
	 */
	public function upserv_check_remote_package_update_local_meta( $local_meta, $local_package, $slug ) {

		if ( ! $local_meta ) {
			$config = self::get_config();

			try {
				$filename = $local_package->get_filename();
				$result   = self::$cloud_storage->getObject(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
					$local_package->get_filename()
				);

				if (
					$result &&
					is_file( $filename ) &&
					is_readable( $filename )
				) {
					$local_meta = Parser::parse_package( $filename, true );
				}
			} catch ( PhpS3Exception $e ) {
				Utils::php_log(
					array(
						'error'    => $e->getMessage(),
						'file'     => $e->getFile(),
						'line'     => $e->getLine(),
						'caller'   => $e->getTrace()[1],
						'filename' => $local_package->get_filename(),
					)
				);
			}
		}

		if ( is_file( $local_package->get_filename() ) ) {
			wp_delete_file( $local_package->get_filename() );
		}

		return $local_meta;
	}

	/**
	 * Handle saving remote package to cloud storage
	 *
	 * @param boolean $local_ready Whether local package is ready
	 * @param string  $type Package type
	 * @param string  $slug Package slug
	 * @since 1.0.0
	 */
	public function upserv_saved_remote_package_to_local( $local_ready, $type, $slug ) {
		$config            = self::get_config();
		$package_directory = Data_Manager::get_data_dir( 'packages' );
		$filename          = trailingslashit( $package_directory ) . $slug . '.zip';

		if ( ! $local_ready ) {

			if ( is_file( $filename ) ) {
				wp_delete_file( $filename );
			}

			return;
		}

		try {
			self::$cloud_storage->putObject(
				self::$cloud_storage::inputFile( $filename ),
				$config['storage_unit'],
				self::$virtual_dir . '/' . $slug . '.zip',
				PhpS3::ACL_PRIVATE,
				array(
					'updatepulse-digests-sha1'   => hash_file( 'sha1', $filename ),
					'updatepulse-digests-sha256' => hash_file( 'sha256', $filename ),
					'updatepulse-digests-sha512' => hash_file( 'sha512', $filename ),
					'updatepulse-digests-crc32'  => hash_file( 'crc32', $filename ),
					'updatepulse-digests-crc32c' => hash_file( 'crc32c', $filename ),
				)
			);
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
					'caller'   => $e->getTrace()[1],
					'filename' => $filename,
				)
			);
		}

		if ( is_file( $filename ) ) {
			wp_delete_file( $filename );
		}
	}

	/**
	 * Handle manual package upload to cloud storage
	 *
	 * @param boolean $result Upload result
	 * @param string  $type Package type
	 * @param string  $slug Package slug
	 * @since 1.0.0
	 */
	public function upserv_did_manual_upload_package( $result, $type, $slug ) {

		if ( ! $result ) {
			return;
		}

		$config            = self::get_config();
		$package_directory = Data_Manager::get_data_dir( 'packages' );
		$filename          = trailingslashit( $package_directory ) . $slug . '.zip';

		try {
			self::$cloud_storage->putObjectFile(
				$filename,
				$config['storage_unit'],
				self::$virtual_dir . '/' . $slug . '.zip'
			);
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
					'caller'   => $e->getTrace()[1],
					'filename' => $filename,
				)
			);
		}

		if ( is_file( $filename ) ) {
			wp_delete_file( $filename );
		}
	}

	/**
	 * Determine whether to save remote package locally
	 *
	 * @param boolean $save Current save decision
	 * @param string  $slug Package slug
	 * @param string  $filename Target filename
	 * @param boolean $check_remote Whether to check remote storage
	 * @return boolean Whether to save package locally
	 * @since 1.0.0
	 */
	public function upserv_save_remote_to_local( $save, $slug, $filename, $check_remote ) {
		$config = self::get_config();

		try {

			if ( $check_remote ) {
				$info = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );

				if ( false === $info ) {
					$info = self::$cloud_storage->getObjectInfo(
						$config['storage_unit'],
						self::$virtual_dir . '/' . $slug . '.zip',
					);

					wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
				}

				$save = false === $info;
			}
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
					'caller'   => $e->getTrace()[1],
					'filename' => $filename,
				)
			);
		}

		return $save;
	}

	/**
	 * Handle pre-download actions for packages
	 *
	 * @param string $archive_name Archive name
	 * @param string $archive_path Archive path
	 * @param array  $package_slugs Package slugs to download
	 * @since 1.0.0
	 */
	public function upserv_before_packages_download( $archive_name, $archive_path, $package_slugs ) {

		if ( 1 === count( $package_slugs ) ) {
			$config = self::get_config();

			try {
				self::$cloud_storage->getObject(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $package_slugs[0] . '.zip',
					$archive_path
				);
			} catch ( PhpS3Exception $e ) {
				Utils::php_log(
					array(
						'error'    => $e->getMessage(),
						'file'     => $e->getFile(),
						'line'     => $e->getLine(),
						'caller'   => $e->getTrace()[1],
						'filename' => $archive_path,
					)
				);
			}
		} elseif ( ! empty( $package_slugs ) ) {

			foreach ( $package_slugs as $slug ) {
				$package_directory = Data_Manager::get_data_dir( 'packages' );
				$filename          = trailingslashit( $package_directory ) . $slug . '.zip';

				if ( is_file( $filename ) ) {
					wp_delete_file( $filename );
				}
			}
		}
	}

	/**
	 * Handle pre-download repack actions for packages
	 *
	 * @param string $archive_name Archive name
	 * @param string $archive_path Archive path
	 * @param array  $package_slugs Package slugs to download
	 * @since 1.0.0
	 */
	public function upserv_before_packages_download_repack( $archive_name, $archive_path, $package_slugs ) {

		if ( ! empty( $package_slugs ) ) {
			$config = self::get_config();

			foreach ( $package_slugs as $slug ) {
				$package_directory = Data_Manager::get_data_dir( 'packages' );
				$filename          = trailingslashit( $package_directory ) . $slug . '.zip';

				try {
					self::$cloud_storage->getObject(
						$config['storage_unit'],
						self::$virtual_dir . '/' . $slug . '.zip',
						$filename
					);
				} catch ( PhpS3Exception $e ) {
					Utils::php_log(
						array(
							'error'    => $e->getMessage(),
							'file'     => $e->getFile(),
							'line'     => $e->getLine(),
							'caller'   => $e->getTrace()[1],
							'filename' => $filename,
						)
					);
				}
			}
		}
	}

	/**
	 * Handle post-download actions for packages
	 *
	 * @param string $archive_name Archive name
	 * @param string $archive_path Archive path
	 * @since 1.0.0
	 */
	public function upserv_after_packages_download( $archive_name, $archive_path ) {

		if ( is_file( $archive_path ) ) {
			wp_delete_file( $archive_path );
		}
	}

	/**
	 * Redirect package download API requests to cloud storage.
	 *
	 * @param string $method API method being called
	 * @param array  $payload Request payload
	 * @since 1.0.0
	 */
	public function upserv_package_api_request( $method, $payload ) {
		$config = self::get_config();

		if ( 'download' === $method ) {
			$package_id = isset( $payload['package_id'] ) ? $payload['package_id'] : null;
			$type       = isset( $payload['type'] ) ? $payload['type'] : null;
			$info       = wp_cache_get( $package_id . '-getObjectInfo', 'updatepulse-server' );

			if ( false === $info ) {
				$info = self::$cloud_storage->getObjectInfo(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $package_id . '.zip',
				);

				wp_cache_set( $package_id . '-getObjectInfo', $info, 'updatepulse-server' );
			}

			if ( ! $info ) {
				$api = Package_API::get_instance();

				if ( ! $api->add( $package_id, $type ) ) {
					wp_send_json( array( 'message' => __( 'Package not found', 'updatepulse-server' ) ), 404 );
				}
			}

			$nonce = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'token' ) ) );

			if ( ! $nonce ) {
				$nonce = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'nonce' ) ) );
			}

			$url                  = self::$cloud_storage->getAuthenticatedUrlV4(
				$config['storage_unit'],
				self::$virtual_dir . '/' . $package_id . '.zip',
				abs( intval( upserv_get_nonce_expiry( $nonce ) ) ) - time(),
			);
			$this->doing_redirect = wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

			/**
			 * Fired after the package download redirect is issued.
			 *
			 * @param string $package_slug the slug of the downloaded package
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_download_package', $package_id );

			exit;
		}
	}

	/**
	 * Fetch package from cloud storage when not in cache
	 *
	 * @param string $slug Package slug
	 * @param string $filename Target filename
	 * @param Cache  $cache Cache instance
	 * @since 1.0.0
	 */
	public function upserv_find_package_no_cache( $slug, $filename, $cache ) {

		if ( is_file( $filename ) ) {
			return;
		}

		$config = self::get_config();

		try {
			$info = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );

			if ( false === $info ) {
				$info = self::$cloud_storage->getObjectInfo(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
				);

				wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
			}

			$cache_key = self::build_cache_key( $slug, $filename );

			if ( $cache_key && ! $cache->get( $cache_key ) ) {
				$result = self::$cloud_storage->getObject(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
					$filename
				);

				if ( $result ) {
					$package     = Package::from_archive( $filename, $slug, $cache );
					$cache_value = $package->get_metadata();

					$cache->set( $cache_key, $cache_value, Zip_Metadata_Parser::$cache_time );
				}
			}
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'    => $e->getMessage(),
					'file'     => $e->getFile(),
					'line'     => $e->getLine(),
					'caller'   => $e->getTrace()[1],
					'filename' => $filename,
				)
			);
		}

		if ( is_file( $filename ) ) {
			wp_delete_file( $filename );
		}
	}

	/**
	 * Generate cache key for cloud storage metadata
	 *
	 * @param string $cache_key Current cache key
	 * @param string $slug Package slug
	 * @param string $filename Package filename
	 * @return string Modified cache key
	 * @since 1.0.0
	 */
	public function upserv_zip_metadata_parser_cache_key( $cache_key, $slug, $filename ) {
		$cloud_cache_key = self::build_cache_key( $slug, $filename );

		return $cloud_cache_key ? $cloud_cache_key : $cache_key;
	}

	/**
	 * Get package information from cloud storage
	 *
	 * @param array|false $package_info Current package information
	 * @param string      $slug Package slug
	 * @return array|false Updated package information
	 * @since 1.0.0
	 */
	public function upserv_package_manager_get_package_info( $package_info, $slug ) {
		$cache             = new Cache( Data_Manager::get_data_dir( 'cache' ) );
		$config            = self::get_config();
		$package_directory = Data_Manager::get_data_dir( 'packages' );
		$filename          = $package_directory . $slug . '.zip';
		$cleanup           = ! is_file( $filename );

		try {
			$info = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );

			if ( false === $info ) {
				$info = self::$cloud_storage->getObjectInfo(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
				);

				wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
			}

			$cache_key = self::build_cache_key( $slug, $filename );

			if ( ! $cache_key ) {
				return $package_info;
			}

			$package_info = $cache->get( $cache_key );
			$result       = true;

			if ( ! $package_info ) {
				$result = self::$cloud_storage->getObject(
					$config['storage_unit'],
					self::$virtual_dir . '/' . $slug . '.zip',
					$filename
				);
			}

			if ( ! $result ) {

				if ( $cleanup && is_file( $filename ) ) {
					wp_delete_file( $filename );
				}

				return $package_info;
			}

			try {
				$package      = Package::from_archive( $filename, $slug, $cache );
				$package_info = $package->get_metadata();

				$cache->set(
					$cache_key,
					$package_info,
					Zip_Metadata_Parser::$cache_time // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				);
			} catch ( Invalid_Package_Exception $e ) {
				Utils::php_log(
					array(
						'error'    => $e->getMessage(),
						'file'     => $e->getFile(),
						'line'     => $e->getLine(),
						'caller'   => $e->getTrace()[1],
						'filename' => $filename,
					)
				);

				$cleanup = true;
			}

			if ( ! $package_info ) {

				if ( $cleanup && is_file( $filename ) ) {
					wp_delete_file( $filename );
				}

				return $package_info;
			}

			if ( ! isset( $package_info['type'] ) ) {
				$package_info['type'] = 'unknown';
			}

			$packages[ $slug ]['metadata']      = upserv_get_package_metadata( $slug );
			$package_info['file_name']          = $package_info['slug'] . '.zip';
			$package_info['file_path']          = 'cloudStorage://' . self::$virtual_dir . '/' . $slug . '.zip';
			$package_info['file_size']          = $info['size'];
			$package_info['file_last_modified'] = $info['time'];
			$package_info['etag']               = $info['hash'];
			$package_info['digests']            = array();
			$digest_keys                        = array( 'crc32', 'crc32c', 'sha1', 'sha256', 'sha512' );

			foreach ( $digest_keys as $key ) {

				if ( isset( $info[ 'x-amz-meta-updatepulse-digests-' . $key ] ) ) {
					$package_info['digests'][ $key ] = $info[ 'x-amz-meta-updatepulse-digests-' . $key ];
				}
			}
		} catch ( Exception $e ) {

			if ( $e instanceof PhpS3Exception ) {
				Utils::php_log(
					array(
						'error'    => $e->getMessage(),
						'file'     => $e->getFile(),
						'line'     => $e->getLine(),
						'caller'   => $e->getTrace()[1],
						'filename' => $filename,
					)
				);
			} else {
				Utils::php_log( 'Corrupt archive ' . $filename . '; package will not be displayed or delivered' );

				$log  = 'Exception caught: ' . $e->getMessage() . "\n";
				$log .= 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";

				Utils::php_log( $log );
			}
		}

		if ( $cleanup && is_file( $filename ) ) {
			wp_delete_file( $filename );
		}

		return $package_info;
	}

	/**
	 * Get batch package information from cloud storage
	 *
	 * @param array  $packages Current packages information
	 * @param string $search Search term
	 * @return array Updated packages information
	 * @since 1.0.0
	 */
	public function upserv_package_manager_get_batch_package_info( $packages, $search ) {
		$config   = self::get_config();
		$contents = wp_cache_get( 'upserv-getBucket', 'updatepulse-server' );
		$packages = is_array( $packages ) ? $packages : array();

		if ( false !== $contents ) {
			return $packages;
		}

		try {
			$contents = self::$cloud_storage->getBucket( $config['storage_unit'], self::$virtual_dir . '/' );
			unset( $contents[ self::$virtual_dir . '/' ] );

			if ( empty( $contents ) ) {
				wp_cache_set( 'upserv-getBucket', $contents, 'updatepulse-server' );

				return $packages;
			}

			$package_manager = Package_Manager::get_instance();

			foreach ( $contents as $item ) {
				$slug = str_replace( array( self::$virtual_dir . '/', '.zip' ), array( '', '' ), $item['name'] );
				$info = $package_manager->get_package_info( $slug );

				if ( ! $info ) {
					continue;
				}

				$include = ! $search ? true : (
					$search &&
					(
						false === strpos( strtolower( $info['name'] ), strtolower( $search ) ) ||
						false === strpos( strtolower( $info['slug'] ) . '.zip', strtolower( $search ) )
					)
				);
				/**
				 * Filter whether to include package information in responses.
				 *
				 * @param bool $_include Current inclusion status
				 * @param array $info Package information
				 * @return bool Whether to include the package information
				 * @since 1.0.0
				 */
				$include = apply_filters( 'upserv_package_info_include', $include, $info );

				if ( $include ) {
					$packages[ $info['slug'] ] = $info;
				}
			}
		} catch ( PhpS3Exception $e ) {
			Utils::php_log(
				array(
					'error'  => $e->getMessage(),
					'file'   => $e->getFile(),
					'line'   => $e->getLine(),
					'caller' => $e->getTrace()[1],
				)
			);
		}

		wp_cache_set( 'upserv-getBucket', $contents, 'updatepulse-server' );

		return $packages;
	}

	/**
	 * Handle package download action
	 *
	 * @param object $request Download request
	 * @since 1.0.0
	 */
	public function upserv_update_server_action_download( $request ) {
		$config = self::get_config();
		$url    = self::$cloud_storage->getAuthenticatedUrlV4(
			$config['storage_unit'],
			self::$virtual_dir . '/' . $request->slug . '.zip',
			self::DOWNLOAD_URL_LIFETIME,
		);

		$this->doing_redirect = wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
	}

	/**
	 * Check if download request is already handled
	 *
	 * @return boolean Whether download is handled
	 * @since 1.0.0
	 */
	public function upserv_update_server_action_download_handled() {
		return $this->doing_redirect;
	}

	/**
	 * Check if package is whitelisted in cloud storage
	 *
	 * @param boolean $whitelisted Current whitelist status
	 * @param string  $package_slug Package slug
	 * @return boolean Updated whitelist status
	 * @since 1.0.0
	 */
	public function upserv_is_package_whitelisted( $whitelisted, $package_slug ) {
		$data = upserv_get_package_metadata( $package_slug, false );

		if (
			isset(
				$data['whitelisted'],
				$data['whitelisted']['cloud'],
				$data['whitelisted']['cloud'][0]
			)
		) {
			return (bool) $data['whitelisted']['cloud'][0];
		}

		return $whitelisted;
	}

	/**
	 * Update package data when whitelisted
	 *
	 * @param array  $data Package data
	 * @param string $slug Package slug
	 * @return array Updated package data
	 * @since 1.0.0
	 */
	public function upserv_whitelist_package_data( $data, $slug ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$data['whitelisted']['cloud'] = array(
			true,
			time(),
		);

		return $data;
	}

	/**
	 * Update package data when unwhitelisted
	 *
	 * @param array  $data Package data
	 * @param string $slug Package slug
	 * @return array Updated package data
	 * @since 1.0.0
	 */
	public function upserv_unwhitelist_package_data( $data, $slug ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$data['whitelisted']['cloud'] = array(
			false,
			time(),
		);

		return $data;
	}

	/**
	 * Build cache key for cloud storage items
	 *
	 * @param string $slug Package slug
	 * @param string $filename Package filename
	 * @return string|false Cache key or false
	 * @since 1.0.0
	 */
	protected static function build_cache_key( $slug, $filename ) {
		$config    = self::get_config();
		$info      = wp_cache_get( $slug . '-getObjectInfo', 'updatepulse-server' );
		$cache_key = false;

		if ( false === $info ) {
			$info = self::$cloud_storage->getObjectInfo(
				$config['storage_unit'],
				self::$virtual_dir . '/' . $slug . '.zip',
			);

			wp_cache_set( $slug . '-getObjectInfo', $info, 'updatepulse-server' );
		}

		if ( $info ) {
			$cache_key = $slug . '-b64-'
						. md5( $filename . '|' . $info['size'] . '|' . $info['time'] );
		}

		return $cache_key;
	}

	/**
	 * Check if virtual folder exists in cloud storage
	 *
	 * @param string $name Folder name
	 * @return array|false Object metadata when the folder marker exists, or false.
	 * @since 1.0.0
	 */
	protected function virtual_folder_exists( $name ) {
		$config = self::get_config();

		return self::$cloud_storage->getObjectInfo(
			$config['storage_unit'],
			trailingslashit( $name )
		);
	}

	/**
	 * Create a virtual folder in cloud storage
	 *
	 * @param string      $name Folder name
	 * @param string|null $storage_unit Storage unit name
	 * @return boolean Whether folder was created
	 * @since 1.0.0
	 */
	protected function create_virtual_folder( $name, $storage_unit = null ) {

		if ( ! $storage_unit ) {
			$config       = self::get_config();
			$storage_unit = $config['storage_unit'];
		}

		return self::$cloud_storage->putObject(
			trailingslashit( $name ),
			$storage_unit,
			trailingslashit( $name )
		);
	}
}
