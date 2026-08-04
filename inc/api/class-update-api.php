<?php

namespace Anyape\UpdatePulse\Server\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\Scheduler\Scheduler;
use Anyape\Utils\Utils;

/**
 * Update API class
 *
 * @since 1.0.0
 */
class Update_API {

	/**
	 * Is doing API request
	 *
	 * @var bool|null
	 * @since 1.0.0
	 */
	protected static $doing_api_request = null;
	/**
	 * Instance
	 *
	 * @var Update_API|null
	 * @since 1.0.0
	 */
	protected static $instance;

	/**
	 * Update server object
	 *
	 * @var object|null
	 * @since 1.0.0
	 */
	protected $update_server;

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks.
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'add_endpoints' ), 10, 0 );
			add_action( 'parse_request', array( $this, 'parse_request' ), -99, 0 );
			add_action( 'upserv_checked_remote_package_update', array( $this, 'upserv_checked_remote_package_update' ), 10, 3 );
			add_action( 'upserv_removed_package', array( $this, 'upserv_removed_package' ), 10, 3 );
			add_action( 'upserv_registered_package_from_vcs', array( $this, 'upserv_registered_package_from_vcs' ), 10, 2 );

			add_filter( 'query_vars', array( $this, 'query_vars' ), -99, 1 );
			add_filter( 'puc_request_info_pre_filter', array( $this, 'puc_request_info_pre_filter' ), 10, 4 );
			add_filter( 'puc_request_info_result', array( $this, 'puc_request_info_result' ), 10, 4 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Add API endpoints
	 *
	 * Register the rewrite rules for the Update API endpoints.
	 *
	 * @since 1.0.0
	 */
	public function add_endpoints() {
		add_rewrite_rule(
			'^updatepulse-server-update-api/*$',
			'index.php?$matches[1]&__upserv_update_api=1&',
			'top'
		);
	}

	/**
	 * Parse API requests
	 *
	 * Handle incoming API requests to the Update API endpoints.
	 *
	 * @since 1.0.0
	 */
	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__upserv_update_api'] ) ) {
			$this->handle_api_request();
		}
	}

	/**
	 * Register query variables
	 *
	 * Add custom query variables used by the Update API.
	 *
	 * @param array $query_vars Existing query variables.
	 * @return array Modified query variables.
	 * @since 1.0.0
	 */
	public function query_vars( $query_vars ) {
		$query_vars = array_merge(
			$query_vars,
			array(
				'__upserv_update_api',
				'action',
				'token',
				'package_id',
				'update_type',
			)
		);

		return $query_vars;
	}

	/**
	 * Handle checked remote package update event
	 *
	 * Actions to perform when a remote package update has been checked.
	 *
	 * @param bool $needs_update Whether the package needs an update.
	 * @param string $type The type of the package.
	 * @param string $slug The slug of the package.
	 * @since 1.0.0
	 */
	public function upserv_checked_remote_package_update( $needs_update, $type, $slug ) {
		$this->schedule_check_remote_event( $slug );
	}

	/**
	 * Handle package registered from VCS event
	 *
	 * Actions to perform when a package has been registered from VCS.
	 *
	 * @param bool $result The result of the registration.
	 * @param string $slug The slug of the package.
	 * @since 1.0.0
	 */
	public function upserv_registered_package_from_vcs( $result, $slug ) {

		if ( $result ) {
			$this->schedule_check_remote_event( $slug );
		}
	}

	/**
	 * Handle package removed event
	 *
	 * Actions to perform when a package has been removed.
	 *
	 * @param bool $result The result of the removal.
	 * @param string $type The type of the package.
	 * @param string $slug The slug of the package.
	 * @since 1.0.0
	 */
	public function upserv_removed_package( $result, $type, $slug ) {

		if ( $result ) {
			Scheduler::get_instance()->unschedule_all_actions( 'upserv_check_remote_' . $slug );
		}
	}

	/**
	 * Pre-filter package information
	 *
	 * Filter package information before the update check.
	 *
	 * @param array $info Package information.
	 * @param object $api_obj The API object.
	 * @param mixed $ref Reference value.
	 * @param object $update_checker The update checker object.
	 * @return array Filtered package information.
	 * @since 1.0.0
	 */
	public function puc_request_info_pre_filter( $info, $api_obj, $ref, $update_checker ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$vcs_config = upserv_get_package_vcs_config( $info['slug'] );

		if ( empty( $vcs_config ) ) {
			return $info;
		}

		/**
		 * Filter whether to filter the packages retrieved from the Version Control System.
		 *
		 * @param bool $filter_packages Whether to filter the packages retrieved from the Version Control System.
		 * @param array $info The information of the package from the VCS.
		 * @since 1.0.0
		 */
		$filter_packages = apply_filters(
			'upserv_vcs_filter_packages',
			$vcs_config['filter_packages'],
			$info
		);

		$this->init_server( $info['slug'] );

		if ( $this->update_server && $filter_packages ) {
			$info = $this->update_server->pre_filter_package_info( $info, $api_obj, $ref );
		}

		return $info;
	}

	/**
	 * Filter package information result
	 *
	 * Filter package information after the update check.
	 *
	 * @param array $info Package information.
	 * @param object $api_obj The API object.
	 * @param mixed $ref Reference value.
	 * @param object $checker The update checker object.
	 * @return array Filtered package information.
	 * @since 1.0.0
	 */
	public function puc_request_info_result( $info, $api_obj, $ref, $checker ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$vcs_config = upserv_get_package_vcs_config( $info['slug'] );

		if ( empty( $vcs_config ) ) {
			return $info;
		}

		/**
		 * Filter whether to filter the packages retrieved from the Version Control System.
		 *
		 * @param bool $filter_packages Whether to filter the packages retrieved from the Version Control System.
		 * @param array $info The information of the package from the VCS.
		 * @since 1.0.0
		 */
		$filter_packages = apply_filters(
			'upserv_vcs_filter_packages',
			$vcs_config['filter_packages'],
			$info
		);

		$this->init_server( $info['slug'] );

		if ( $this->update_server && $filter_packages ) {
			$info = $this->update_server->filter_package_info( $info );
		}

		return $info;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Check if currently processing an API request
	 *
	 * Determine whether the current request is an Update API request.
	 *
	 * @return bool Whether the current request is an Update API request.
	 * @since 1.0.0
	 */
	public static function is_doing_api_request() {

		if ( null === self::$doing_api_request ) {
			self::$doing_api_request = Utils::is_url_subpath_match( '/^updatepulse-server-update-api$/' );
		}

		return self::$doing_api_request;
	}

	/**
	 * Get Update API instance
	 *
	 * Retrieve or create the Update API singleton instance.
	 *
	 * @return Update_API The Update API instance.
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check for remote package updates
	 *
	 * Verify if a remote package has updates available.
	 *
	 * @param string $slug The package slug.
	 * @param string $type The package type.
	 * @return bool|mixed Result of the remote update check.
	 * @since 1.0.0
	 */
	public function check_remote_update( $slug, $type ) {
		$this->init_server( $slug );

		if ( ! $this->update_server ) {
			return false;
		}

		$this->update_server->set_type( $type );

		return $this->update_server->check_remote_package_update( $slug );
	}

	/**
	 * Download a remote package
	 *
	 * Download and process a package from a remote source.
	 *
	 * @param string $slug The package slug.
	 * @param string|null $type The package type.
	 * @param bool $force Whether to force the download.
	 * @return bool Whether the download was successful.
	 * @since 1.0.0
	 */
	public function download_remote_package( $slug, $type = null, $force = false ) {
		$result = false;

		if ( ! $type ) {
			$types = array( 'plugin', 'theme', 'generic' );

			foreach ( $types as $_type ) {
				$result = $this->download_remote_package( $slug, $_type, $force );

				if ( $result ) {
					break;
				}
			}

			return $result;
		}

		$this->init_server( $slug );

		if ( ! $this->update_server ) {
			return false;
		}

		$this->update_server->set_type( $type );

		if ( $force || $this->update_server->check_remote_package_update( $slug ) ) {
			$result = $this->update_server->save_remote_package_to_local( $slug, $force );
		}

		if ( $result ) {

			if ( ! upserv_is_package_whitelisted( $slug ) ) {
				upserv_whitelist_package( $slug );
			}

			$meta            = upserv_get_package_metadata( $slug );
			$meta['vcs']     = trailingslashit( $this->update_server->get_vcs_url() );
			$meta['branch']  = $this->update_server->get_branch();
			$meta['type']    = $type;
			$meta['vcs_key'] = hash( 'sha256', trailingslashit( $meta['vcs'] ) . '|' . $meta['branch'] );
			$meta['origin']  = 'vcs';

			upserv_set_package_metadata( $slug, $meta );
		}

		return $result;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Schedule remote check event
	 *
	 * Set up a scheduled event to check for remote package updates.
	 *
	 * @param string $slug The package slug.
	 * @since 1.0.0
	 */
	protected function schedule_check_remote_event( $slug ) {
		$vcs_config = upserv_get_package_vcs_config( $slug );

		if (
			! upserv_get_option( 'use_vcs', 0 ) ||
			empty( $vcs_config ) ||
			(
				isset( $vcs_config['use_webhooks'] ) &&
				$vcs_config['use_webhooks']
			)
		) {
			return;
		}

		$meta   = upserv_get_package_metadata( $slug );
		$type   = isset( $meta['type'] ) ? $meta['type'] : null;
		$hook   = 'upserv_check_remote_' . $slug;
		$params = array( $slug, $type, false );

		if ( Scheduler::get_instance()->has_scheduled_action( $hook, $params ) ) {
			return;
		}

		/**
		 * Filter the package update remote check frequency set in the configuration.
		 * Fired during client update API request.
		 *
		 * @param string $frequency The frequency set in the configuration.
		 * @param string $package_slug The slug of the package to check for updates.
		 * @since 1.0.0
		 */
		$frequency = apply_filters(
			'upserv_check_remote_frequency',
			$vcs_config['check_frequency'],
			$slug
		);
		$timestamp = time();
		$schedules = wp_get_schedules();
		$result    = Scheduler::get_instance()->schedule_recurring_action(
			$timestamp,
			$schedules[ $frequency ]['interval'],
			$hook,
			$params
		);

		/**
		 * Fired after a remote check event has been scheduled for a package.
		 * Fired during client update API request.
		 *
		 * @param bool $result Whether the event was scheduled.
		 * @param string $package_slug Slug of the package for which the event was scheduled.
		 * @param int $timestamp Timestamp for when to run the event the first time after it's been scheduled.
		 * @param string $frequency Frequency at which the event would be ran.
		 * @param string $hook Event hook to fire when the event is ran.
		 * @param array $params Parameters passed to the actions registered to $hook when the event is ran.
		 * @since 1.0.0
		 */
		do_action(
			'upserv_scheduled_check_remote_event',
			$result,
			$slug,
			$timestamp,
			$frequency,
			$hook,
			$params
		);
	}

	/**
	 * Handle API requests
	 *
	 * Process and respond to Update API requests.
	 *
	 * @since 1.0.0
	 */
	protected function handle_api_request() {
		global $wp;

		$vars   = $wp->query_vars;
		$params = array(
			'action' => isset( $vars['action'] ) ? trim( $vars['action'] ) : null,
			'token'  => isset( $vars['token'] ) ? trim( $vars['token'] ) : null,
			'slug'   => isset( $vars['package_id'] ) ? trim( $vars['package_id'] ) : null,
			'type'   => isset( $vars['update_type'] ) ? trim( $vars['update_type'] ) : null,
		);
		$query  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query  = array_map(
			'sanitize_text_field',
			array_filter(
				$query,
				function ( $key ) use ( $query ) {
					return (
						! empty( $query[ $key ] ) &&
						is_scalar( $query[ $key ] ) &&
						preg_match( '@^[a-z0-9\-_]+$@i', $key )
					);
				},
				ARRAY_FILTER_USE_KEY
			)
		);
		/**
		 * Filter the parameters used to handle the request made by a client plugin, theme, or generic package to the plugin's API.
		 * Fired during client update API request.
		 *
		 * @param array $params The parameters of the request to the API.
		 * @since 1.0.0
		 */
		$params = apply_filters( 'upserv_handle_update_request_params', array_merge( $query, $params ) );

		$this->init_server( $params['slug'] );

		if ( ! $this->update_server ) {
			wp_send_json(
				array(
					'error'   => 'no_server',
					'message' => __( 'No server found for this package.', 'updatepulse-server' ),
				),
				500,
				Utils::JSON_OPTIONS
			);
		}

		/**
		 * Fired before handling the request made by a client plugin, theme, or generic package to the plugin's API.
		 * Fired during client update API request.
		 *
		 * @param array $request_params The parameters or the request to the API.
		 * @since 1.0.0
		 */
		do_action( 'upserv_before_handle_update_request', $params );
		$this->update_server->handle_request( $params );
	}

	/**
	 * Initialize update server
	 *
	 * Set up the update server for a specific package.
	 *
	 * @param string $slug The package slug.
	 * @since 1.0.0
	 */
	protected function init_server( $slug ) {
		$check_manual = false;

		if ( upserv_get_option( 'use_vcs' ) ) {
			$vcs_config  = upserv_get_package_vcs_config( $slug );
			$url         = isset( $vcs_config['url'] ) ? $vcs_config['url'] : false;
			$branch      = isset( $vcs_config['branch'] ) ? $vcs_config['branch'] : false;
			$credentials = isset( $vcs_config['credentials'] ) ? $vcs_config['credentials'] : '';
			$vcs_type    = isset( $vcs_config['type'] ) ? $vcs_config['type'] : false;
			$self_hosted = isset( $vcs_config['self_hosted'] ) ? $vcs_config['self_hosted'] : false;

			if ( ! $url || ! $branch || ! $vcs_type ) {
				$check_manual = true;
			}
		} else {
			$check_manual = true;
		}

		if ( $check_manual ) {
			$meta = upserv_get_package_metadata( $slug );

			if ( ! isset( $meta['origin'] ) || 'manual' !== $meta['origin'] ) {
				return;
			}
		}

		$filter_args = array(
			'url'         => isset( $url ) ? $url : null,
			'branch'      => isset( $branch ) ? $branch : null,
			'credentials' => isset( $credentials ) ? $credentials : null,
			'self_hosted' => isset( $self_hosted ) ? $self_hosted : null,
			'directory'   => Data_Manager::get_data_dir(),
			'vcs_config'  => isset( $vcs_config ) ? $vcs_config : null,
		);
		/**
		 * Filter the class name to use to instantiate a `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
		 * Fired during client update API request.
		 *
		 * @param string $class_name The class name to use to instantiate a `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
		 * @param string $package_slug The slug of the package to serve.
		 * @param array $config The configuration to use to serve the package.
		 * @since 1.0.0
		 */
		$_class_name = apply_filters(
			'upserv_server_class_name',
			str_replace( 'API', 'Server\\Update', __NAMESPACE__ ) . '\\Update_Server',
			$slug,
			$filter_args
		);
		/**
		 * Filter the arguments to pass to the constructor of the `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
		 * Fired during client update API request.
		 *
		 * @param array $args The arguments to pass to the constructor of the `Anyape\UpdatePulse\Server\Server\Update\Update_Server` object.
		 * @param string $package_slug The slug of the package to serve.
		 * @param array $config The configuration to use to serve the package.
		 * @since 1.0.0
		 */
		$args = apply_filters(
			'upserv_server_constructor_args',
			array(
				home_url( '/updatepulse-server-update-api/' ),
				Data_Manager::get_data_dir(),
				isset( $url ) ? $url : null,
				isset( $branch ) ? $branch : null,
				isset( $credentials ) ? $credentials : null,
				isset( $vcs_type ) ? $vcs_type : null,
				isset( $self_hosted ) ? $self_hosted : null,
			),
			$slug,
			$filter_args
		);

		if ( ! isset( $this->update_server ) || ! is_a( $this->update_server, $_class_name ) ) {
			$this->update_server = new $_class_name( ...$args );
		}
	}
}
