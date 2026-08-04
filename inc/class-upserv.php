<?php
/**
 * Main UpdatePulse Server plugin class.
 *
 * @package UPServ
 */

namespace Anyape\UpdatePulse\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Exception;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Manager\Package_Manager;
use Anyape\Utils\Utils;

/**
 * Main server class for UpdatePulse
 *
 * @since 1.0.0
 */
class UPServ {

	/**
	 * Class instance
	 *
	 * @var UPServ|null
	 * @since 1.0.0
	 */
	protected static $instance;
	/**
	 * Default plugin options
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected static $default_options;
	/**
	 * Current plugin options
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected static $options;

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks.
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {
		self::$default_options = array(
			'use_vcs'           => 0,
			'use_licenses'      => 0,
			'use_cloud_storage' => 0,
			'vcs'               => (object) array(),
			'api'               => array(
				'webhooks' => (object) array(),
				'licenses' => array(
					'private_api_keys'         => (object) array(),
					'private_api_ip_whitelist' => array(),
				),
				'packages' => array(
					'private_api_keys'         => (object) array(),
					'private_api_ip_whitelist' => array(),
				),
			),
			'cloud_storage'     => array(
				'access_key' => '',
				'secret_key' => '',
				'endpoint'   => '',
				'unit'       => '',
				'region'     => 'auto',
			),
			'limits'            => array(
				'archive_max_size' => Package_Manager::DEFAULT_ARCHIVE_MAX_SIZE,
				'cache_max_size'   => Package_Manager::DEFAULT_CACHE_MAX_SIZE,
				'logs_max_size'    => Package_Manager::DEFAULT_LOGS_MAX_SIZE,
			),
		);
		self::$options         = $this->get_options();

		if ( $init_hooks ) {

			if ( ! upserv_is_doing_api_request() ) {
				$parts     = explode( DIRECTORY_SEPARATOR, untrailingslashit( UPSERV_PLUGIN_PATH ) );
				$plugin_id = end( $parts ) . '/updatepulse-server.php';

				add_action( 'init', array( $this, 'init' ), 99, 0 );
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
				add_action( 'admin_menu', array( $this, 'admin_menu' ), 5, 0 );
				add_action( 'admin_menu', array( $this, 'admin_menu_help' ), 99, 0 );
				add_action( 'action_scheduler_failed_execution', array( $this, 'action_scheduler_failed_execution' ), 10, 3 );

				add_filter( 'upserv_admin_scripts', array( $this, 'upserv_admin_scripts' ), 10, 1 );
				add_filter( 'upserv_admin_styles', array( $this, 'upserv_admin_styles' ), 10, 1 );
				add_filter( 'plugin_action_links_' . $plugin_id, array( $this, 'add_action_links' ), 10, 1 );
				add_filter( 'upserv_admin_tab_links', array( $this, 'upserv_admin_tab_links' ), 99, 1 );
				add_filter( 'upserv_admin_tab_states', array( $this, 'upserv_admin_tab_states' ), 99, 2 );
				add_filter( 'action_scheduler_retention_period', array( $this, 'action_scheduler_retention_period' ), 10, 0 );
				add_filter( 'upserv_get_admin_template_args', array( $this, 'upserv_get_admin_template_args' ), 10, 2 );
				add_filter( 'upserv_scripts_l10n', array( $this, 'upserv_scripts_l10n' ), 10, 2 );
			}

			add_action( 'init', array( $this, 'load_textdomain' ), 10, 0 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Handle Action Scheduler failed execution
	 *
	 * Logs information about failed scheduled actions when debug mode is enabled.
	 *
	 * @param int       $action_id The ID of the failed action.
	 * @param Exception $exception The exception that was thrown.
	 * @param string    $context Additional context information.
	 * @since 1.0.0
	 */
	public function action_scheduler_failed_execution( $action_id, Exception $exception, $context = '' ) {

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		Utils::php_log(
			array(
				'action_id' => $action_id,
				'exception' => $exception,
				'context'   => $context,
			)
		);
	}

	// WordPress hooks ---------------------------------------------

	/**
	 * Activate plugin
	 *
	 * Runs on plugin activation to verify requirements and initialize settings.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {

		if ( ! version_compare( phpversion(), '8.0', '>=' ) ) {
			$error_message  = __( 'PHP version 8.0 or higher is required. Current version: ', 'updatepulse-server' );
			$error_message .= phpversion();

			die( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$error_message = __( 'The zip PHP extension is required by UpdatePulse Server. Please check your server configuration.', 'updatepulse-server' );

			die( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		if ( ! get_option( 'upserv_plugin_version' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$plugin_data = get_plugin_data( UPSERV_PLUGIN_FILE );
			$version     = $plugin_data['Version'];

			update_option( 'upserv_plugin_version', $version );
		}

		if ( ! get_option( 'upserv_options' ) ) {
			$instance = self::get_instance();

			$instance->update_options( self::$default_options );
		}
	}

	/**
	 * Deactivate plugin
	 *
	 * Runs on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Get all plugin options
	 *
	 * Retrieves the stored options and merges them with the current defaults.
	 *
	 * @return array Plugin options
	 * @since 1.0.0
	 */
	public function get_options() {
		$options = get_option( 'upserv_options' );
		$options = json_decode( $options, true );
		$options = $options ? $options : array();
		$options = array_merge( self::$default_options, $options );

		/**
		 * Filter the plugin options.
		 *
		 * @param array $options The plugin options
		 * @return array The filtered options
		 * @since 1.0.0
		 */
		return apply_filters( 'upserv_get_options', $options );
	}

	/**
	 * Update plugin options
	 *
	 * Updates the plugin's options in the database.
	 *
	 * @param array $options New options to update.
	 * @return bool Whether the stored option value changed.
	 * @since 1.0.0
	 */
	public function update_options( $options ) {
		$options = array_merge( self::$options, $options );
		/**
		 * Filter the options before updating.
		 *
		 * @param array $options The options to update
		 * @return array The filtered options
		 * @since 1.0.0
		 */
		$options = apply_filters( 'upserv_update_options', $options );
		$options = wp_json_encode(
			$options,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		);
		$result  = update_option( 'upserv_options', $options, true );

		if ( $result ) {
			self::$options = $this->get_options();
		}

		return $result;
	}

	/**
	 * Get single option value
	 *
	 * Retrieves a specific option by its path.
	 *
	 * @param string $path Option path using slash notation.
	 * @param mixed  $_default Default value if option not found.
	 * @return mixed Option value
	 * @since 1.0.0
	 */
	public function get_option( $path, $_default ) {
		$options = $this->get_options();
		$option  = Utils::access_nested_array( $options, $path );

		if ( is_null( $option ) ) {
			$option = $_default;
		}

		/**
		 * Filter a specific option value.
		 *
		 * @param mixed $option The option value
		 * @param string|array $path The option path
		 * @return mixed The filtered option value
		 * @since 1.0.0
		 */
		return apply_filters( 'upserv_get_option', $option, $path );
	}

	/**
	 * Set option in memory
	 *
	 * Sets an option value in memory without saving to database.
	 *
	 * @param string $path Option path using slash notation.
	 * @param mixed  $value Option value.
	 * @return array Updated options
	 * @since 1.0.0
	 */
	public function set_option( $path, $value ) {
		$options = self::$options;

		Utils::access_nested_array( $options, $path, $value, true );

		self::$options = $options;

		return self::$options;
	}

	/**
	 * Update single option
	 *
	 * Updates a specific option by its path and saves to database.
	 *
	 * @param string $path Option path using slash notation.
	 * @param mixed  $value Option value.
	 * @return bool Whether the stored option value changed.
	 * @since 1.0.0
	 */
	public function update_option( $path, $value ) {
		$options = $this->get_options();

		Utils::access_nested_array( $options, $path, $value, true );

		return $this->update_options( $options );
	}

	/**
	 * Handle deferred initialization tasks
	 *
	 * Flushes rewrite rules when requested and registers activation-result notices.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( get_transient( 'upserv_flush' ) ) {
			delete_transient( 'upserv_flush' );
			flush_rewrite_rules();
		}

		if ( filter_input( INPUT_COOKIE, 'upserv_activated_mu_failure', FILTER_VALIDATE_INT ) ) {
			setcookie( 'upserv_activated_mu_failure', '', time() - 3600, '/', COOKIE_DOMAIN );
			add_action( 'admin_notices', array( $this, 'setup_mu_plugin_failure_notice' ), 10, 0 );
		}

		if ( filter_input( INPUT_COOKIE, 'upserv_activated_mu_success', FILTER_VALIDATE_INT ) ) {
			setcookie( 'upserv_activated_mu_success', '', time() - 3600, '/', COOKIE_DOMAIN );
			add_action( 'admin_notices', array( $this, 'setup_mu_plugin_success_notice' ), 10, 0 );
		}
	}

	/**
	 * Load text domain
	 *
	 * Loads the plugin's translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'updatepulse-server', false, '/languages' );
	}

	/**
	 * Register admin styles
	 *
	 * Adds stylesheets for the admin interface.
	 *
	 * @param array $styles Existing styles.
	 * @return array Modified styles
	 * @since 1.0.0
	 */
	public function upserv_admin_styles( $styles ) {
		$styles['main']        = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/admin/main' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/admin/main' . upserv_assets_suffix() . '.css',
		);
		$styles['fa_solid']    = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/fontawesome/css/solid' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/fontawesome/css/solid' . upserv_assets_suffix() . '.css',
		);
		$styles['fontawesome'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/fontawesome/css/fontawesome' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/fontawesome/css/fontawesome' . upserv_assets_suffix() . '.css',
		);

		return $styles;
	}

	/**
	 * Register admin scripts
	 *
	 * Adds JavaScript files for the admin interface.
	 *
	 * @param array $scripts Existing scripts.
	 * @return array Modified scripts
	 * @since 1.0.0
	 */
	public function upserv_admin_scripts( $scripts ) {
		$scripts['main'] = array(
			'path'   => UPSERV_PLUGIN_PATH . 'js/admin/main' . upserv_assets_suffix() . '.js',
			'uri'    => UPSERV_PLUGIN_URL . 'js/admin/main' . upserv_assets_suffix() . '.js',
			'deps'   => array( 'jquery' ),
			'params' => array(
				'debug'    => (bool) ( constant( 'WP_DEBUG' ) ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			),
		);

		return $scripts;
	}

	/**
	 * Normalize script localization values
	 *
	 * Converts localization values supplied as arrays into newline-delimited strings.
	 *
	 * @param array  $l10n Localization data.
	 * @param string $script Script name.
	 * @return array Modified localization data
	 * @since 1.0.0
	 */
	public function upserv_scripts_l10n( $l10n, $script ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		foreach ( $l10n as $key => $values ) {

			if ( ! is_array( $values ) ) {
				continue;
			}

			$l10n[ $key ] = implode( "\n", $values );
		}

		return $l10n;
	}

	/**
	 * Enqueue assets on UpdatePulse admin pages
	 *
	 * Loads the registered admin assets only for UpdatePulse page hooks.
	 *
	 * @param string $hook Current admin page hook.
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( false !== strpos( $hook, 'page_upserv' ) ) {
			$this->enqueue_styles( array() );
			$this->enqueue_scripts( array() );
		}
	}

	/**
	 * Register main admin menu
	 *
	 * Adds the main UpdatePulse menu item to the admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		$page_title = __( 'UpdatePulse', 'updatepulse-server' );
		$menu_title = $page_title;
		$icon       = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMiIgdmlld0JveD0iMCAwIDQwIDQwIj48cGF0aCBmaWxsPSIjOWNhMmE3IiBkPSJNMjAuMDI2IDEuNzEyYy0yLjMxNCAwLTQuNDM0LjE2OC03LjUzMyAxLjMyOC0xLjU1LjU4LTMuNDI0IDEuMzM0LTUuOSAzLjUyNUM0LjExNiA4Ljc1Ni41MTQgMTMuNTYyLjUxNCAyMC4wMDVjMCA2LjQ0MiAzLjYwMiAxMS4yNDYgNi4wNzkgMTMuNDM3IDIuNDc2IDIuMTkxIDQuMzUgMi45NDggNS45IDMuNTI4IDMuMDk5IDEuMTYgNS4yMiAxLjMyNiA3LjUzMyAxLjMyNiAyLjMxNCAwIDQuNDM0LS4xNjcgNy41MzMtMS4zMjYgMS41NS0uNTggMy40MjQtMS4zMzcgNS45LTMuNTI4IDIuNDc4LTIuMTkxIDYuMDc5LTYuOTk1IDYuMDc5LTEzLjQzNyAwLTYuNDQzLTMuNjAxLTExLjI0OS02LjA3OC0xMy40NC0yLjQ3Ny0yLjE5MS00LjM1MS0yLjk0NS01LjktMy41MjUtMy4xLTEuMTYtNS4yMi0xLjMyOC03LjUzNC0xLjMyOFptMCAxLjQ1OWMxLjIzOCAwIDIuMzg0LjAwNCA0Ljc3OC45IDEuMTk2LjQ0OCAyLjc4NCAxLjA0NiA1LjA2NCAzLjA2MyAxLjk4NCAxLjc1NSA0Ljg4NiA1LjQ3OSA1LjYwNSAxMC41MjktMTAuNTcyLS4wNDYgNC4zMS0uMDUtNi44Ni0uMDI2LTIuNDU1LjAyLTMuNTE0IDIuMzk2LTQuMzIzIDMuNjA0bC0yLjU0OSAzLjc5NS00LjAzMy0xMS45OTRjLS4zNDctMS4wNy0xLjAxLTEuNzIzLTIuMTYtMS43MjMtMS4wOTEgMC0xLjc1Ljk1Ni0yLjI0MyAxLjY4OEw5LjY2MSAxNy42M2wtLjAxNi4wMTVjLTQuODA0LjAxNi00LjQ1Ni4wMDYtNS4wNjQuMDEuNzIyLTUuMDQ2IDMuNjItOC43NjcgNS42MDMtMTAuNTIxIDIuMjgtMi4wMTcgMy44NjgtMi42MTUgNS4wNjUtMy4wNjMgMi4zOTMtLjg5NiAzLjU0LS45IDQuNzc3LS45em0tNS4xMjkgMTMuMTgxYzEuNDA5IDQuMTE1IDIuOTk2IDguNzk5IDQuNjk4IDEzLjMyNy42NCAxLjI3NyAxLjkxIDEuMzMyIDIuNzQyLjUzOSAyLjI5OC0zLjQ1NiA0LjIyLTYuMTQ3IDYuMTkzLTkuMTk0IDIuMzM0LS4wMSA0Ljc4Ny0uMDAyIDcuMDc4LS4wMTctLjM0NiA1LjczLTMuNTg1IDkuOTYtNS43NCAxMS44NjctMi4yOCAyLjAxNy0zLjg2OCAyLjYxNi01LjA2NCAzLjA2NC0yLjM5NC44OTYtMy41NC45LTQuNzc4LjktMS4yMzggMC0yLjM4NC0uMDA0LTQuNzc3LS45LTEuMTk3LS40NDgtMi43ODUtMS4wNDctNS4wNjUtMy4wNjQtMi4xNTgtMS45MS01LjQtNi4xNDgtNS43NC0xMS44OS4xMTItLjAxNy0uMjU3LS4wMzUgMi43MjMuMDA3IDQuMzUuMDMxIDMuNTY1LjMyNiA2LjA3NC0yLjI2LjU5NS0uODM0IDEuMTIzLTEuNTM4IDEuNjU2LTIuMzc5eiIvPjwvc3ZnPg==';

		add_menu_page( $page_title, $menu_title, 'manage_options', 'upserv-page', '', $icon );
	}

	/**
	 * Register help page in admin menu
	 *
	 * Adds the help submenu to the UpdatePulse menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu_help() {
		$function   = array( $this, 'help_page' );
		$page_title = __( 'UpdatePulse Server - Help', 'updatepulse-server' );
		$menu_title = __( 'Help', 'updatepulse-server' );
		$menu_slug  = 'upserv-page-help';

		add_submenu_page( 'upserv-page', $page_title, $menu_title, 'manage_options', $menu_slug, $function );
	}

	/**
	 * Add tab links for admin interface
	 *
	 * Registers navigation tabs for the admin interface.
	 *
	 * @param array $links Existing tab links.
	 * @return array Modified tab links
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_links( $links ) {
		$links['help'] = array(
			admin_url( 'admin.php?page=upserv-page-help' ),
			'<i class="fa-solid fa-circle-question"></i>' . __( 'Help', 'updatepulse-server' ),
		);

		return $links;
	}

	/**
	 * Add tab states for admin interface
	 *
	 * Sets active states for navigation tabs.
	 *
	 * @param array  $states Existing tab states.
	 * @param string $page Current page.
	 * @return array Modified tab states
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_states( $states, $page ) {
		$states['help'] = 'upserv-page-help' === $page;

		return $states;
	}

	/**
	 * Add plugin action links
	 *
	 * Adds custom links to the plugin's entry in the plugins list.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links
	 * @since 1.0.0
	 */
	public function add_action_links( $links ) {
		$link = array(
			'<a href="' . admin_url( 'admin.php?page=upserv-page-help' ) . '">' . __( 'Help', 'updatepulse-server' ) . '</a>',
		);

		return array_merge( $links, $link );
	}

	/**
	 * Set action scheduler retention period
	 *
	 * Limits Action Scheduler retention to one day.
	 *
	 * @return int Retention period in seconds
	 * @since 1.0.0
	 */
	public function action_scheduler_retention_period() {
		return DAY_IN_SECONDS;
	}

	/**
	 * Modify admin template arguments
	 *
	 * Adds the rendered settings header to plugin page templates.
	 *
	 * @param array  $args Existing template arguments.
	 * @param string $template_name Name of the template.
	 * @return array Modified template arguments
	 * @since 1.0.0
	 */
	public function upserv_get_admin_template_args( $args, $template_name ) {

		if ( preg_match( '/^plugin-.*-page\.php$/', $template_name ) ) {
			ob_start();
			$this->display_settings_header( wp_cache_get( 'settings_notice', 'updatepulse-server' ) );
			$args['header'] = (string) ob_get_clean();
		}

		return $args;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Get class instance
	 *
	 * Retrieves or creates the singleton instance of this class.
	 *
	 * @return UPServ The class instance
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Locate template file
	 *
	 * Resolves a theme override when present, otherwise uses the plugin template path.
	 *
	 * @param string $template_name Template name.
	 * @param bool   $load Whether to load the template.
	 * @param bool   $required_once Whether to use require_once or require.
	 * @return string Template path
	 * @since 1.0.0
	 */
	public static function locate_template( $template_name, $load = false, $required_once = true ) {
		$name  = str_replace( 'templates/', '', $template_name );
		$paths = array(
			'plugins/updatepulse-server/templates/' . $name,
			'plugins/updatepulse-server/' . $name,
			'updatepulse-server/templates/' . $name,
			'updatepulse-server/' . $name,
		);
		/**
		 * Filter the paths where templates can be located.
		 *
		 * @param array $paths Array of template paths
		 * @return array The filtered paths
		 * @since 1.0.0
		 */
		$template = locate_template( apply_filters( 'upserv_locate_template_paths', $paths ) );

		if ( empty( $template ) ) {
			$template = UPSERV_PLUGIN_PATH . 'inc/templates/' . $template_name;
		}

		/**
		 * Filter the located template.
		 *
		 * @param string $template The path to the template
		 * @param string $template_name The template name
		 * @param string $template_path The template path
		 * @return string The filtered template path
		 * @since 1.0.0
		 */
		$template = apply_filters(
			'upserv_locate_template',
			$template,
			$template_name,
			str_replace( $template_name, '', $template )
		);

		if ( $load && '' !== $template ) {
			load_template( $template, $required_once );
		}

		return $template;
	}

	/**
	 * Locate admin template file
	 *
	 * Resolves the filterable plugin admin-template path and optionally loads it.
	 *
	 * @param string $template_name Template name.
	 * @param bool   $load Whether to load the template.
	 * @param bool   $required_once Whether to use require_once or require.
	 * @return string Template path
	 * @since 1.0.0
	 */
	public static function locate_admin_template( $template_name, $load = false, $required_once = true ) {
		/**
		 * Filter the admin template location.
		 *
		 * @param string $template The path to the template
		 * @param string $template_name The template name
		 * @param string $template_path The template path
		 * @return string The filtered template path
		 * @since 1.0.0
		 */
		$template = apply_filters(
			'upserv_locate_admin_template',
			UPSERV_PLUGIN_PATH . 'inc/templates/admin/' . $template_name,
			$template_name,
			str_replace( $template_name, '', UPSERV_PLUGIN_PATH . 'inc/templates/admin/' )
		);

		if ( $load && '' !== $template ) {
			load_template( $template, $required_once );
		}

		return $template;
	}

	/**
	 * Display MU plugin setup failure notice
	 *
	 * Shows admin notice when MU plugin couldn't be installed.
	 *
	 * @since 1.0.0
	 */
	public function setup_mu_plugin_failure_notice() {
		$class   = 'notice notice-error';
		$message = sprintf(
			// translators: %1$s is the <code>path</code> to the mu-plugins directory, %2$s is the <code>path</code> of the source MU Plugin.
			esc_html__( 'Permission errors for %1$s - could not setup the default optimizer MU Plugin. You may create the directory if necessary and manually copy %2$s in it (recommended).', 'updatepulse-server' ),
			'<code>' . trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) . '</code>',
			'<code>' . wp_normalize_path( UPSERV_PLUGIN_PATH . 'optimisation/upserv-default-optimizer.php</code>' ),
		);

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Display MU plugin setup success notice
	 *
	 * Shows admin notice when MU plugin was successfully installed.
	 *
	 * @since 1.0.0
	 */
	public function setup_mu_plugin_success_notice() {
		$class   = 'notice notice-info is-dismissible';
		$message = sprintf(
			// translators: %1$s is the <code>path</code> to the mu-plugin.
			esc_html__( 'An optimizer MU Plugin has been confirmed to be installed in %1$s.', 'updatepulse-server' ),
			'<code>' . trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) . 'upserv-default-optimizer.php</code>'
		);

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Display settings header
	 *
	 * Renders the settings title, an optional notice, and the navigation tabs.
	 *
	 * @param string|array $notice Optional notice to display.
	 * @since 1.0.0
	 */
	public function display_settings_header( $notice ) {
		echo '<h1>' . esc_html__( 'UpdatePulse Server', 'updatepulse-server' ) . '</h1>';

		if ( is_string( $notice ) && ! empty( $notice ) ) {
			echo '
				<div class="updated notice notice-success is-dismissible">
					<p>'
						. esc_html( $notice ) . '
					</p>
				</div>
			';
		} elseif ( is_array( $notice ) && ! empty( $notice ) ) {
			echo '
				<div class="error notice notice-error is-dismissible">
					<ul>';

			foreach ( $notice as $key => $message ) {
				echo '
				<li id="upserv_option_error_item_' . esc_attr( $key ) . '">'
					. wp_kses_post( $message ) .
				'</li>';
			}

			echo '
					</ul>
				</div>';
		}

		$this->display_tabs();
	}

	/**
	 * Render help page
	 *
	 * Displays the plugin's help documentation.
	 *
	 * @since 1.0.0
	 */
	public function help_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$options = array(
			'use_vcs' => upserv_get_option( 'use_vcs' ),
		);

		upserv_get_admin_template(
			'plugin-help-page.php',
			array(
				'packages_dir' => Data_Manager::get_data_dir( 'packages' ),
				'options'      => $options,
				'mu_path'      => trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ),
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Display navigation tabs
	 *
	 * Renders the tab navigation for admin pages.
	 *
	 * @since 1.0.0
	 */
	protected function display_tabs() {
		$states = $this->get_tab_states();
		$state  = array_filter( $states );

		if ( ! $state ) {
			return;
		}

		$state = array_keys( $state );
		$state = reset( $state );
		/**
		 * Filter the admin tab links.
		 *
		 * @param array $links The existing tab links
		 * @return array The modified tab links
		 * @since 1.0.0
		 */
		$links = apply_filters( 'upserv_admin_tab_links', array() );

		upserv_get_admin_template(
			'tabs.php',
			array(
				'states' => $states,
				'state'  => $state,
				'links'  => $links,
			)
		);
	}

	/**
	 * Get tab states
	 *
	 * Builds tab states for the current UpdatePulse admin page.
	 *
	 * @return array Tab states
	 * @since 1.0.0
	 */
	protected function get_tab_states() {
		$page   = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'page' ) ) );
		$states = array();

		if ( 0 === strpos( $page, 'upserv-page' ) ) {
			/**
			 * Filter the admin tab states.
			 *
			 * @param array $states The existing tab states
			 * @param string $page The current page
			 * @return array The modified tab states
			 * @since 1.0.0
			 */
			$states = apply_filters( 'upserv_admin_tab_states', $states, $page );
		}

		return $states;
	}

	/**
	 * Enqueue styles
	 *
	 * Loads stylesheets for the admin interface.
	 *
	 * @param array $styles Styles to enqueue.
	 * @return array Filtered style definitions.
	 * @since 1.0.0
	 */
	protected function enqueue_styles( $styles ) {
		/**
		 * Filter the admin styles to be enqueued.
		 *
		 * @param array $styles Array of styles to be enqueued
		 * @return array Modified array of styles
		 * @since 1.0.0
		 */
		$styles = apply_filters( 'upserv_admin_styles', $styles );

		if ( ! empty( $styles ) ) {

			foreach ( $styles as $key => $values ) {

				if ( isset( $values['path'] ) && file_exists( $values['path'] ) ) {
					$version        = filemtime( $values['path'] );
					$values['deps'] = isset( $values['deps'] ) ? $values['deps'] : array();
					$suffix         = '-admin-style';

					wp_enqueue_style(
						'upserv-' . $key . $suffix,
						$values['uri'],
						$values['deps'],
						$version
					);

					if ( isset( $values['inline'] ) ) {
						wp_add_inline_style( 'upserv-' . $key . $suffix, $values['inline'] );
					}
				}
			}
		}

		return $styles;
	}

	/**
	 * Enqueue scripts
	 *
	 * Loads JavaScript files for the admin interface.
	 *
	 * @param array $scripts Scripts to enqueue.
	 * @return array Filtered script definitions.
	 * @since 1.0.0
	 */
	protected function enqueue_scripts( $scripts ) {
		/**
		 * Filter the admin scripts to be enqueued.
		 *
		 * @param array $scripts Array of scripts to be enqueued
		 * @return array Modified array of scripts
		 * @since 1.0.0
		 */
		$scripts = apply_filters( 'upserv_admin_scripts', $scripts );

		if ( ! empty( $scripts ) ) {

			foreach ( $scripts as $key => $values ) {

				if ( isset( $values['path'] ) && file_exists( $values['path'] ) ) {
					$version             = filemtime( $values['path'] );
					$values['deps']      = isset( $values['deps'] ) ? $values['deps'] : array();
					$values['in_footer'] = isset( $values['in_footer'] ) ? $values['in_footer'] : true;
					$suffix              = '-admin-script';

					wp_enqueue_script(
						'upserv-' . $key . $suffix,
						$values['uri'],
						$values['deps'],
						$version,
						$values['in_footer']
					);

					if ( isset( $values['params'] ) ) {
						$values['params_before'] = isset( $values['params_before'] ) ?
							$values['params_before'] :
							'before';

						wp_add_inline_script(
							'upserv-' . $key . $suffix,
							'var UPServAdmin'
								. ucfirst( str_replace( '-', '', ucwords( $key, '-' ) ) )
								. ' = '
								. wp_json_encode( $values['params'] ),
							$values['params_before']
						);
					}

					if ( isset( $values['l10n'] ) ) {
						$vals = $values['l10n'];
						$var  = 'UPServAdmin'
							. ucfirst( str_replace( '-', '', ucwords( $key, '-' ) ) )
							. '_l10n';

						if ( isset( $values['l10n']['_var'] ) ) {
							$var = $values['l10n']['_var'];

							unset( $vals['_var'] );
						}

						wp_localize_script( 'upserv-' . $key . $suffix, $var, $vals );
					}
				}
			}
		}

		return $scripts;
	}
}
