<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Anyape\UpdatePulse\Server\Scheduler\Scheduler;

class Data_Manager {

	/**
	 * Transient data directories
	 *
	 * List of directories that store temporary data.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static $transient_data_dirs = array(
		'cache',
		'logs',
		'tmp',
	);
	/**
	 * Persistent data directories
	 *
	 * List of directories that store permanent data.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static $persistent_data_dirs = array(
		'packages',
		'metadata',
	);
	/**
	 * Transient data in database
	 *
	 * List of temporary data stored in the database.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static $transient_data_db = array(
		'update_from_remote_locks',
	);
	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks.
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'upserv_scheduler_init', array( $this, 'upserv_scheduler_init' ), 10, 0 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Activate
	 *
	 * Actions to perform when the plugin is activated.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		set_transient( 'upserv_flush', 1, 60 );

		$result = self::maybe_setup_directories();

		if ( ! $result ) {
			$error_message = sprintf(
				// translators: %1$s is the path to the plugin's data directory
				__( 'Permission errors creating %1$s - could not setup the data directory. Please check the parent directory is writable.', 'updatepulse-server' ),
				'<code>' . self::get_data_dir() . '</code>'
			);

			die( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$result = self::maybe_setup_mu_plugin();

		if ( $result ) {
			setcookie( 'upserv_activated_mu_success', '1', 60, '/', COOKIE_DOMAIN );
		} else {
			setcookie( 'upserv_activated_mu_failure', '1', 60, '/', COOKIE_DOMAIN );
		}
	}

	/**
	 * Deactivate
	 *
	 * Actions to perform when the plugin is deactivated.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		self::clear_schedules();
	}

	/**
	 * Initialize scheduler
	 *
	 * Register cleanup events and schedules.
	 *
	 * @since 1.0.0
	 */
	public function upserv_scheduler_init() {
		self::register_cleanup_events();
		self::register_cleanup_schedules();
	}

	// Misc. -------------------------------------------------------

	/**
	 * Clear schedules
	 *
	 * Remove all scheduled cleanup events.
	 *
	 * @since 1.0.0
	 */
	public static function clear_schedules() {
		self::clear_cleanup_schedules();
	}

	/**
	 * Setup directories
	 *
	 * Create data directories if they don't exist.
	 *
	 * @return bool True if directories were created successfully, false otherwise.
	 * @since 1.0.0
	 */
	public static function maybe_setup_directories() {
		$root_dir = self::get_data_dir();
		$result   = true;

		if ( ! is_dir( $root_dir ) ) {
			$result = self::create_data_dir( 'updatepulse-server', false, true );
		}

		if ( $result ) {

			foreach ( array_merge( self::$transient_data_dirs, self::$persistent_data_dirs ) as $directory ) {

				if ( ! is_dir( $root_dir . $directory ) ) {
					$result = $result && self::create_data_dir( $directory );
				}
			}
		}

		return $result;
	}

	/**
	 * Setup MU plugin
	 *
	 * Create or update the must-use plugin file.
	 *
	 * @return bool True if the MU plugin was setup successfully, false otherwise.
	 * @since 1.0.0
	 */
	public static function maybe_setup_mu_plugin() {
		WP_Filesystem();

		global $wp_filesystem;

		$result        = true;
		$mu_plugin_dir = trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) );
		$mu_plugin     = $mu_plugin_dir . 'upserv-default-optimizer.php';

		if ( ! $wp_filesystem->is_dir( $mu_plugin_dir ) ) {
			$result = wp_mkdir_p( $mu_plugin_dir );
		}

		if ( $wp_filesystem->is_file( $mu_plugin ) ) {
			$result = $wp_filesystem->delete( $mu_plugin );
		}

		if ( $result && ! $wp_filesystem->is_file( $mu_plugin ) ) {
			$source_mu_plugin = wp_normalize_path(
				UPSERV_PLUGIN_PATH . 'optimisation/upserv-default-optimizer.php'
			);
			$result           = $wp_filesystem->copy( $source_mu_plugin, $mu_plugin );
		}

		return $result;
	}

	/**
	 * Get data directory path
	 *
	 * Retrieve the path to a specific data directory.
	 *
	 * @param string $dir Directory name or 'root' for the base directory.
	 * @return string Path to the requested directory.
	 * @since 1.0.0
	 */
	public static function get_data_dir( $dir = 'root' ) {
		$data_dir = wp_cache_get( 'data_dir_' . $dir, 'updatepulse-server' );

		if ( false === $data_dir ) {
			$wp_upload_dir = wp_upload_dir();
			$data_dir      = trailingslashit( $wp_upload_dir['basedir'] . '/updatepulse-server' );

			if ( 'root' !== $dir ) {

				if ( ! self::is_valid_data_dir( $dir ) ) {
					// translators: %1$s is the path to the plugin's data directory
					$error_message = sprintf( __( 'Directory <code>%1$s</code> is not a valid UpdatePulse Server data directory.', 'updatepulse-server' ), $dir );

					wp_die( $error_message, __METHOD__ ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}

				$data_dir .= $dir;
			}

			$data_dir = trailingslashit( $data_dir );

			if ( ! is_dir( $data_dir ) ) {
				self::create_data_dir( $dir );
			}

			wp_cache_set( 'data_dir_' . $dir, $data_dir, 'updatepulse-server' );
		}

		return $data_dir;
	}

	/**
	 * Check if directory is valid
	 *
	 * Determine whether a directory name is a valid data directory.
	 *
	 * @param string $dir The directory name to check.
	 * @param bool   $require_persistent Whether the directory must be persistent.
	 * @return bool Whether the directory is valid.
	 * @since 1.0.0
	 */
	public static function is_valid_data_dir( $dir, $require_persistent = false ) {
		$is_valid = false;

		if ( ! $require_persistent ) {
			$is_valid = in_array( $dir, array_merge( self::$transient_data_dirs, self::$persistent_data_dirs ), true );
		} else {
			$is_valid = in_array( $dir, self::$persistent_data_dirs, true );
		}

		return $is_valid;
	}

	/**
	 * Maybe cleanup data
	 *
	 * Clean up transient data if needed.
	 *
	 * @param string $type The type of data to clean up.
	 * @param bool   $force Whether to force cleanup regardless of conditions.
	 * @return bool Whether cleanup was performed.
	 * @since 1.0.0
	 */
	public static function maybe_cleanup( $type, $force = false ) {

		if ( in_array( $type, self::$transient_data_db, true ) ) {
			$method_name = 'maybe_cleanup_' . $type;

			if ( method_exists( get_called_class(), $method_name ) && ! $force ) {
				return static::$method_name();
			} else {
				return delete_option( 'upserv_' . $type );
			}
		}

		if ( self::is_valid_data_dir( $type ) ) {
			return self::maybe_cleanup_data_dir( $type, $force );
		}

		return false;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Maybe cleanup data directory
	 *
	 * Clean up a data directory if it exceeds its size limit or if forced.
	 *
	 * @param string $type The directory to clean up.
	 * @param bool   $force Whether to force cleanup regardless of conditions.
	 * @return bool Whether cleanup was performed.
	 * @since 1.0.0
	 */
	protected static function maybe_cleanup_data_dir( $type, $force ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		$directory              = self::get_data_dir( $type );
		$max_size_constant_name = __NAMESPACE__ . '\\Package_Manager::DEFAULT_'
			. strtoupper( $type )
			. '_MAX_SIZE';
		$default_max_size       = defined( $max_size_constant_name ) ? constant( $max_size_constant_name ) : 0;
		$cleanup                = false;
		$is_dir                 = is_dir( $directory );
		$total_size             = 0;

		if ( $default_max_size && $is_dir && false === $force ) {
			$max_size = upserv_get_option( 'limits/' . $type . '_max_size', $default_max_size );

			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
				$size = $file->getSize();

				if ( false !== $size ) {
					$total_size += $size;
				}
			}

			if ( $total_size >= ( $max_size * UPSERV_MB_TO_B ) ) {
				$cleanup = true;
			}
		}

		if ( $is_dir && ( $cleanup || $force ) ) {
			$result = true;
			$result = $result && $wp_filesystem->delete( $directory, true );
			$result = $result && wp_mkdir_p( $directory );

			if ( self::is_valid_data_dir( $type ) ) {
				$result = $result && self::generate_restricted_htaccess( $directory );
			}

			/**
			 * Fired after a data directory cleanup operation.
			 *
			 * @param bool $result Whether the cleanup was successful
			 * @param string $type The type of data that was cleaned up
			 * @param int $total_size The total size of the data before cleanup
			 * @param bool $force Whether the cleanup was forced
			 * @since 1.0.0
			 */
			do_action( 'upserv_did_cleanup', $result, $type, $total_size, $force );

			return $result;
		}

		return false;
	}

	/**
	 * Maybe cleanup update from remote locks
	 *
	 * Clean up expired remote update locks from the database.
	 *
	 * @return bool Whether cleanup was performed.
	 * @since 1.0.0
	 */
	protected static function maybe_cleanup_update_from_remote_locks() {
		$locks = get_option( 'upserv_update_from_remote_locks' );

		if ( is_array( $locks ) && ! empty( $locks ) ) {

			foreach ( $locks as $slug => $timestamp ) {

				if ( $timestamp <= time() ) {
					unset( $locks[ $slug ] );
				}
			}

			update_option( 'upserv_update_from_remote_locks', $locks );
		}
	}

	/**
	 * Create data directory
	 *
	 * Create a directory for storing plugin data.
	 *
	 * @param string $name The name of the directory to create.
	 * @param bool   $include_htaccess Whether to create an .htaccess file.
	 * @param bool   $is_root_dir Whether this is the root data directory.
	 * @return bool Whether the directory was created successfully.
	 * @since 1.0.0
	 */
	protected static function create_data_dir( $name, $include_htaccess = true, $is_root_dir = false ) {
		$wp_upload_dir = wp_upload_dir();
		$root_dir      = trailingslashit( $wp_upload_dir['basedir'] . '/updatepulse-server' );
		$path          = ( $is_root_dir ) ? $root_dir : $root_dir . $name;
		$result        = wp_mkdir_p( $path );

		if ( $result && $include_htaccess ) {
			self::generate_restricted_htaccess( $path );
		}

		return $result;
	}

	/**
	 * Generate restricted htaccess
	 *
	 * Create an .htaccess file that prevents direct access to files.
	 *
	 * @param string $directory The directory path where to create the .htaccess file.
	 * @return bool Whether the .htaccess file was created successfully.
	 * @since 1.0.0
	 */
	protected static function generate_restricted_htaccess( $directory ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		$contents = "Order deny,allow\nDeny from all";
		$htaccess = trailingslashit( $directory ) . '.htaccess';

		$wp_filesystem->touch( $htaccess );

		return $wp_filesystem->put_contents( $htaccess, $contents, 0644 );
	}

	/**
	 * Clear cleanup schedules
	 *
	 * Unschedule all cleanup events.
	 *
	 * @since 1.0.0
	 */
	protected static function clear_cleanup_schedules() {

		if ( upserv_is_doing_update_api_request() ) {
			return;
		}

		$cleanable_datatypes = array_merge( self::$transient_data_dirs, self::$transient_data_db );

		foreach ( $cleanable_datatypes as $type ) {
			$params = array( $type );

			if ( 'tmp' === $type ) {
				$params[] = true;
			}

			Scheduler::get_instance()->unschedule_all_actions( 'upserv_cleanup', $params );

			/**
			 * Fired after a cleanup schedule has been cleared.
			 *
			 * @param string $type The type of data for which the schedule was cleared
			 * @param array $params The parameters that were used for the schedule
			 * @since 1.0.0
			 */
			do_action( 'upserv_cleared_cleanup_schedule', $type, $params );
		}
	}

	/**
	 * Register cleanup callbacks.
	 *
	 * Attach cleanup callbacks to the shared cleanup action.
	 *
	 * @return bool|null False when skipped during an update API request; otherwise null.
	 * @since 1.0.0
	 */
	protected static function register_cleanup_schedules() {

		if ( upserv_is_doing_update_api_request() ) {
			return false;
		}

		$cleanable_datatypes = array_merge( self::$transient_data_dirs, self::$transient_data_db );

		foreach ( $cleanable_datatypes as $type ) {
			$params = array( $type );

			if ( 'tmp' === $type ) {
				$params[] = true;
			}

			$hook = array( __NAMESPACE__ . '\\Data_Manager', 'maybe_cleanup' );

			add_action( 'upserv_cleanup', $hook, 10, 2 );

			/**
			 * Fired during scheduler initialization after a cleanup callback has been registered.
			 *
			 * @param string $type The type of data for which the schedule was registered
			 * @param array $params The parameters that are used for the schedule
			 * @since 1.0.0
			 */
			do_action( 'upserv_registered_cleanup_schedule', $type, $params );
		}
	}

	/**
	 * Register cleanup events
	 *
	 * Schedule recurring cleanup events.
	 *
	 * @since 1.0.0
	 */
	protected static function register_cleanup_events() {
		$cleanable_datatypes = array_merge( self::$transient_data_dirs, self::$transient_data_db );

		foreach ( $cleanable_datatypes as $type ) {
			$params = array( $type );
			$hook   = 'upserv_cleanup';

			if ( 'tmp' === $type ) {
				$params[] = true;
			}

			if ( ! Scheduler::get_instance()->has_scheduled_action( $hook, $params ) ) {
				/**
				 * Filter the cleanup schedule frequency.
				 *
				 * @param string $frequency The frequency of the cleanup schedule (default 'hourly')
				 * @param string $type The type of data to clean up
				 * @return string The filtered frequency
				 * @since 1.0.0
				 */
				$frequency = apply_filters( 'upserv_schedule_cleanup_frequency', 'hourly', $type );
				$schedules = wp_get_schedules();
				$timestamp = time();
				$result    = Scheduler::get_instance()->schedule_recurring_action(
					$timestamp,
					$schedules[ $frequency ]['interval'],
					$hook,
					$params
				);

				/**
				 * Fired after a cleanup event has been scheduled.
				 *
				 * @param bool|int $result The Action Scheduler ID or WordPress cron result.
				 * @param string $type The type of data for which the event was scheduled
				 * @param int $timestamp The timestamp at which the event will first run
				 * @param string $frequency The frequency of the scheduled event
				 * @param string $hook The hook that will be triggered
				 * @param array $params The parameters that will be passed to the hook
				 * @since 1.0.0
				 */
				do_action(
					'upserv_scheduled_cleanup_event',
					$result,
					$type,
					$timestamp,
					$frequency,
					$hook,
					$params
				);
			}
		}
	}
}
