<?php
/**
 * WP Update Migrate
 * WordPress plugins and themes update path library.
 *
 * @package UpdatePulseServer
 * @subpackage WP_Update_Migrate
 * @version 1.5.0
 */

/*================================================================================================ */
/*                                        WP Update Migrate                                        */
/*================================================================================================ */

/**
 * Copy/paste this section to your main plugin file or theme functions.php and selectively uncomment the
 * code below to enable migrations
 *
 * WARNING - READ FIRST:
 * Before enabling migrations, setup your plugin or theme properly by making sure:
 * - the wp-update-migrate folder library is present in a lib directory at the root of the plugin or theme
 * - to change 'example_prefix' with a unique prefix identifier for your plugin or theme, snake_case format
 * - to change 'example_function' with the name of the function to remove from or add to the action queue
 * - to have an 'updates' directory at the root of the plugin or theme
 * - to name each file in the 'updates' folder with a version number as file name (example: 1.5.3.php)
 * - each update file in the 'updates' directory have a single update function, and do not include any logic outside of that function
 * - the update function name in each update file follows the pattern: [example_prefix]_update_to_[version]
 *     - example: in 1.5.3.php, the function is my_plugin_update_to_1_5_3 with [example_prefix] = my_plugin
 *     - example: in 2.4.9.php, the function is my_theme_update_to_2_4_9 with [example_prefix] = my_theme
 * - each update function returns (bool) true in case of success, a WP_Error object otherwise
 **/

// phpcs:disable
// require_once __DIR__ . '/lib/wp-update-migrate/class-wp-update-migrate.php';

// $hook = 'after_setup_theme';

// add_action(
// 	0 === strpos( __DIR__, WP_PLUGIN_DIR ) ? 'plugins_loaded' : 'after_setup_theme',
// 	function () {
// 		$update_migrate = WP_Update_Migrate::get_instance( __FILE__, 'example_prefix' );

// 		if ( false === $update_migrate->get_result() ) {
// 			/**
// 			* @todo
// 			* Execute your own logic here in case the update failed.
// 			*
// 			* if ( false !== has_action( 'example_action', 'example_function' ) ) {
// 			*     remove_action( 'example_action', 'example_function', 10 );
// 			* }
// 			**/
// 		}

// 		if ( true === $update_migrate->get_result() ) {
// 			/**
// 			* @todo
// 			* Execute your own logic here in case an update was applied successfully.
// 			*
// 			* if ( false === has_action( 'example_action', 'example_function' ) ) {
// 			*     add_action( 'example_action', 'example_function', 10 );
// 			* }
// 			**/
// 		}
// 	},
// 	PHP_INT_MIN + 100
// );
// phpcs:enable

/*================================================================================================ */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_Update_Migrate' ) ) {

	/**
	 * WP_Update_Migrate class
	 *
	 * Handles the migration and update process for WordPress plugins and themes.
	 */
	class WP_Update_Migrate {

		/**
		 * The current version of the WP_Update_Migrate class.
		 *
		 * @var string
		 */
		const VERSION = '1.5.0';

		/**
		 * @var string Information about the failed update.
		 */
		protected $failed_update_info;

		/**
		 * @var string Information about the successful update.
		 */
		protected $success_update_info;

		/**
		 * @var string The name of the package.
		 */
		protected $package_name;

		/**
		 * @var string The prefix of the package.
		 */
		protected $package_prefix;

		/**
		 * @var string The directory of the package.
		 */
		protected $package_dir;

		/**
		 * @var string The version to update to.
		 */
		protected $to_version;

		/**
		 * @var string The version to update from.
		 */
		protected $from_version;

		/**
		 * @var bool The result of the update.
		 */
		protected $update_result;

		/**
		 * @var string The type of the package (plugin or theme).
		 */
		protected $package_type;

		/**
		 * @var string The handle of the package.
		 */
		protected $package_handle;

		/**
		 * @var WP_Update_Migrate The instance of the class.
		 */
		protected static $instance;

		/**
		 * Constructor
		 *
		 * @param string $package_handle The handle of the package.
		 * @param string $package_prefix The prefix of the package.
		 */
		private function __construct( $package_handle, $package_prefix ) {

			if ( ! wp_doing_ajax() ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';

				$this->package_handle = $package_handle;
				$this->package_prefix = $package_prefix;

				$this->init_package_type( $package_prefix );
				add_action( 'init', array( $this, 'init' ), 10, PHP_INT_MIN + 100 );
			}

			wp_cache_set( $package_prefix, $this, 'wp-update-migrate' );
		}

		/**
		 * Get instance
		 *
		 * @param string $package_handle The handle of the package.
		 * @param string $package_prefix The prefix of the package.
		 * @return WP_Update_Migrate The instance of the class.
		 */
		public static function get_instance( $package_handle, $package_prefix ) {
			wp_cache_add_non_persistent_groups( 'wp-update-migrate' );

			$cache = wp_cache_get( $package_prefix, 'wp-update-migrate' );

			if ( ! $cache ) {
				self::$instance = new self( $package_handle, $package_prefix );
			}

			return self::$instance;
		}

		/**
		 * Get result
		 *
		 * @return bool The result of the update.
		 */
		public function get_result() {
			return $this->update_result;
		}

		/**
		 * Initialize the package
		 */
		public function init() {

			if ( 'plugin' === $this->package_type ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';

				$plugin_data        = get_plugin_data( $this->package_handle );
				$latest_version     = $plugin_data['Version'];
				$this->package_name = $plugin_data['Name'];
				$this->package_dir  = plugin_dir_path( $this->package_handle );
			} elseif ( 'theme' === $this->package_type ) {
				require_once ABSPATH . 'wp-includes/theme.php';

				$theme_info         = wp_get_theme( $this->package_handle );
				$latest_version     = $theme_info->get( 'Version' );
				$this->package_name = $theme_info->get( 'Name' );
				$this->package_dir  = $theme_info->get_stylesheet_directory();
			}

			if ( $this->package_type ) {
				$current_recorded_version = get_option(
					$this->package_prefix . '_' . $this->package_type . '_version'
				);

				if ( ! version_compare( $current_recorded_version, $latest_version, '>=' ) ) {
					$i10n_path = trailingslashit( basename( $this->package_dir ) ) . 'lib/wp-update-migrate/languages';

					if ( 'plugin' === $this->package_type ) {
						load_plugin_textdomain( 'wp-update-migrate', false, $i10n_path );
					} elseif ( 'theme' === $this->package_type ) {
						load_theme_textdomain( 'wp-update-migrate', $i10n_path );
					}

					$this->from_version = $current_recorded_version;
					$this->to_version   = $latest_version;

					$this->update();
				}
			}
		}

		/**
		 * Display update failed notice
		 */
		public function update_failed_notice() {
			$class   = 'notice notice-error is-dismissible';
			$message = '<p>' . $this->failed_update_info . '</p>';
			// Translators: %1$s is the package type
			$message .= '<p>' . sprintf( esc_html__( 'The %1$s may not have any effect until the issues are resolved.', 'wp-update-migrate' ), $this->package_type ) . '</p>';

			echo wp_kses_post( sprintf( '<div class="%1$s">%2$s</div>', $class, $message ) );
		}

		/**
		 * Display update success notice
		 */
		public function update_success_notice() {
			$class = 'notice notice-success is-dismissible';
			// Translators: %1$s is the package version to update to
			$title   = $this->package_name . ' - ' . sprintf( __( 'Success updating to version %1$s', 'wp-update-migrate' ), $this->to_version );
			$message = '<h3>' . $title . '</h3><p>' . $this->success_update_info . '</p>';

			echo wp_kses_post( sprintf( '<div class="%1$s">%2$s</div>', $class, $message ) );
		}

		/**
		 * Get content directory
		 *
		 * @return string The content directory path.
		 */
		protected static function get_content_dir() {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				wp_die( 'File system not available.', __METHOD__ );
			}

			return $wp_filesystem->wp_content_dir();
		}

		/**
		 * Build update path
		 *
		 * @return array The update path.
		 */
		protected function build_update_path() {
			$file_list   = glob( $this->package_dir . 'updates' . DIRECTORY_SEPARATOR . '*.php' );
			$update_path = array();

			foreach ( $file_list as $file_path ) {
				$file_version = str_replace( '.php', '', basename( $file_path ) );

				if ( version_compare( $file_version, $this->from_version, '>' ) &&
					version_compare( $file_version, $this->to_version, '<=' ) ) {
					$update_path[] = $file_version;
				}
			}

			return $update_path;
		}

		/**
		 * Update the package
		 *
		 * @return mixed The result of the update.
		 */
		protected function update() {
			$update_path = $this->build_update_path();
			$result      = true;

			if ( ! empty( $update_path ) ) {
				usort( $update_path, 'version_compare' );

				foreach ( $update_path as $version ) {
					$update_success = $this->do_update( $version );

					if ( true !== $update_success ) {
						$result = $update_success;

						break;
					}
				}
			}

			if ( $result && ! is_wp_error( $result ) ) {

				if ( ! $this->update_file_exists_for_version( $this->to_version ) ) {
					$result = $this->update_package_version( $this->to_version );
				}
			}

			if ( true !== $result ) {
				$this->update_result = false;
				$result              = $this->handle_error( $result );
				$notice_hooks        = apply_filters(
					'wpum_update_failure_extra_notice_hooks',
					array( array( $this, 'update_failed_notice' ) )
				);
			} else {
				$this->update_result = true;

				if ( ! empty( $update_path ) ) {
					$this->handle_success( sprintf( __( '<br/>All updates have been applied successfully.', 'wp-update-migrate' ), $this->to_version ) );
				}

				$notice_hooks = apply_filters(
					'wpum_update_success_extra_notice_hooks',
					array( array( $this, 'update_success_notice' ) )
				);
			}

			foreach ( $notice_hooks as $hook ) {
				add_action( 'admin_notices', $hook, 10, 0 );
			}

			return $result;
		}

		/**
		 * Perform the update for a specific version
		 *
		 * @param string $version The version to update to.
		 * @return mixed The result of the update.
		 */
		protected function do_update( $version ) {
			$error       = false;
			$result      = false;
			$update_file = $this->get_update_file_path_for_version( $version );

			require_once $update_file;

			$function_name = $this->package_prefix . '_update_to_' . str_replace( '.', '_', $version );

			if ( function_exists( $function_name ) ) {
				$update_executed = call_user_func( $function_name );

				if ( true !== $update_executed ) {
					$error = $update_executed;
				}
			} else {
				$error = new WP_Error(
					__METHOD__,
					sprintf(
						// Translators: %1$s is the missing function name, %2$s is the package type, %3$s is the path to WP_CONTENT_DIR
						__( '<br/>The update failed: function <code>%1$s</code> not found.<br/>Please restore the previously used version of the %2$s, or delete the %2$s and its files in the <code>%2$s</code> directory if any and install the latest version.', 'wp-update-migrate' ),
						$function_name,
						$this->package_type,
						self::get_content_dir()
					)
				);
			}

			if ( $error ) {
				$this->handle_error( $error );

				return $error;
			} else {
				$result = $this->update_package_version( $version );

				if ( true === $result ) {
					// Translators: %1$s is the version we just updated to
					$this->handle_success( sprintf( __( 'Updates for version %1$s applied.', 'wp-update-migrate' ), $version ) );

					return true;
				} else {
					return $this->handle_error( $result );
				}
			}
		}

		/**
		 * Initialize the package type
		 *
		 * @param string $package_prefix The prefix of the package.
		 */
		protected function init_package_type( $package_prefix ) {
			$hook = current_filter();

			if ( 'plugins_loaded' === $hook ) {
				$this->package_type = 'plugin';
			} elseif ( 'after_setup_theme' === $hook ) {
				$this->package_type = 'theme';
			} else {
				$this->package_type = apply_filters( 'wpum_package_type', false, $package_prefix );
			}
		}

		/**
		 * Check if update file exists for a specific version
		 *
		 * @param string $version The version to check.
		 * @return bool True if the update file exists, false otherwise.
		 */
		protected function update_file_exists_for_version( $version ) {
			return file_exists( $this->package_dir . 'updates' . DIRECTORY_SEPARATOR . $version . '.php' );
		}

		/**
		 * Get the update file path for a specific version
		 *
		 * @param string $version The version to get the file path for.
		 * @return string The update file path.
		 */
		protected function get_update_file_path_for_version( $version ) {
			return $this->package_dir . 'updates' . DIRECTORY_SEPARATOR . $version . '.php';
		}

		/**
		 * Update the package version
		 *
		 * @param string $version The version to update to.
		 * @return mixed The result of the update.
		 */
		protected function update_package_version( $version ) {

			if ( ! version_compare( $this->from_version, $version, '=' ) ) {
				$result = update_option( $this->package_prefix . '_' . $this->package_type . '_version', $version );
			} else {
				$result = true;
			}

			if ( ! $result ) {
				$result = new WP_Error(
					__METHOD__,
					// Translators: %1$s is the package prefix, %2$s is the package type, %3$s is the version number we're trying to update to
					sprintf( __( 'Failed to update the <code>%1$s_%2$s_version</code> to %3$s in the options table.', 'wp-update-migrate' ), $this->package_prefix, $this->package_type, $version )
				);
			}

			return $result;
		}

		/**
		 * Handle error
		 *
		 * @param WP_Error|null $error The error object.
		 * @return bool False indicating an error occurred.
		 */
		protected function handle_error( $error = null ) {
			$error_title = $this->package_name
				. ' - '
				. sprintf(
					// Translators: %1$s is the package version to update to
					__( 'Error updating to version %1$s', 'wp-update-migrate' ),
					$this->to_version
				);
			$error_message = sprintf(
				// Translators: %1$s is the path to WP_CONTENT_DIR, %2$s is the package type
				__( 'An unexpected error has occured during the update.<br/>Please restore the previously used version of the %2$s, or delete the %2$s and its files in the <code>%1$s</code> directory if any and install the latest version.', 'wp-update-migrate' ),
				self::get_content_dir(),
				$this->package_type
			);

			if ( $error instanceof WP_Error ) {
				$error_message = implode( '<br/><br/>', $error->get_error_messages() );
			}

			$this->failed_update_info = '<h3>' . $error_title . '</h3><br/>' . $error_message;

			return false;
		}

		/**
		 * Handle success
		 *
		 * @param string|null $message The success message.
		 */
		protected function handle_success( $message = null ) {
			$this->success_update_info .= $message . '<br/>';
		}
	}
}
