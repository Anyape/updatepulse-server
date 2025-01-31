<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use WP_Error;
use Anyape\UpdatePulse\Server\Manager\Data_Manager;
use Anyape\UpdatePulse\Server\API\Update_API;

class Remote_Sources_Manager {

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {

			if ( upserv_get_option( 'use_vcs' ) ) {
				add_action( 'action_scheduler_init', array( $this, 'register_remote_check_scheduled_hooks' ), 10, 0 );
			} else {
				add_action( 'init', array( $this, 'clear_remote_check_scheduled_hooks' ), 10, 0 );
			}

			add_action( 'wp_ajax_upserv_force_clean', array( $this, 'force_clean' ), 10, 0 );
			add_action( 'wp_ajax_upserv_vcs_test', array( $this, 'vcs_test' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 15, 0 );

			add_filter( 'upserv_admin_scripts', array( $this, 'upserv_admin_scripts' ), 10, 1 );
			add_filter( 'upserv_admin_styles', array( $this, 'upserv_admin_styles' ), 10, 1 );
			add_filter( 'upserv_admin_tab_links', array( $this, 'upserv_admin_tab_links' ), 15, 1 );
			add_filter( 'upserv_admin_tab_states', array( $this, 'upserv_admin_tab_states' ), 15, 2 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	public static function activate() {
		self::register_schedules();
	}

	public static function deactivate() {
		self::clear_schedules();
	}

	public function upserv_admin_scripts( $scripts ) {
		$scripts['remote_sources'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'js/admin/remote-sources' . upserv_assets_suffix() . '.js',
			'uri'  => UPSERV_PLUGIN_URL . 'js/admin/remote-sources' . upserv_assets_suffix() . '.js',
			'deps' => array( 'jquery' ),
		);

		return $scripts;
	}

	public function upserv_admin_styles( $styles ) {
		$styles['remote_sources'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/admin/remote-sources' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/admin/remote-sources' . upserv_assets_suffix() . '.css',
		);

		return $styles;
	}

	public function register_remote_check_scheduled_hooks() {

		if ( upserv_is_doing_update_api_request() ) {
			return;
		}

		$vcs_configs = upserv_get_option( 'vcs', array() );

		if ( empty( $vcs_configs ) ) {
			return;
		}

		$slugs = array();

		foreach ( $vcs_configs as $vcs_c ) {

			if ( $vcs_c['use_webhooks'] || ! isset( $vcs_c['url'] ) ) {
				continue;
			}

			$slugs = $this->get_package_slugs( $vcs_c['url'] );

			if ( empty( $slugs ) ) {
				continue;
			}

			$api         = Update_API::get_instance();
			$action_hook = array( $api, 'download_remote_package' );

			foreach ( $slugs as $slug ) {
				add_action( 'upserv_check_remote_' . $slug, $action_hook, 10, 3 );
				do_action(
					'upserv_registered_check_remote_schedule',
					$slug,
					'upserv_check_remote_' . $slug,
					$action_hook
				);
			}
		}
	}

	public function clear_remote_check_scheduled_hooks( $vcs_configs = null ) {

		if ( upserv_is_doing_update_api_request() ) {
			return false;
		}

		if ( null === $vcs_configs ) {
			$vcs_configs = upserv_get_option( 'vcs', array() );
		}

		if ( empty( $vcs_configs ) ) {
			return true;
		}

		foreach ( $vcs_configs as $vcs_c ) {

			if ( ! isset( $vcs_c['url'] ) ) {
				continue;
			}

			$slugs = $this->get_package_slugs( $vcs_c['url'] );

			if ( empty( $slugs ) ) {
				continue;
			}

			foreach ( $slugs as $slug ) {
				$scheduled_hook = 'upserv_check_remote_' . $slug;

				as_unschedule_all_actions( $scheduled_hook );
				do_action( 'upserv_cleared_check_remote_schedule', $slug, $scheduled_hook );
			}
		}

		return true;
	}

	public function admin_menu() {
		$function   = array( $this, 'plugin_page' );
		$page_title = __( 'UpdatePulse Server - Version Control Systems ', 'updatepulse-server' );
		$menu_title = __( 'Version Control Systems ', 'updatepulse-server' );
		$menu_slug  = 'upserv-page-remote-sources';

		add_submenu_page( 'upserv-page', $page_title, $menu_title, 'manage_options', $menu_slug, $function );
	}

	public function upserv_admin_tab_links( $links ) {
		$links['remote-sources'] = array(
			admin_url( 'admin.php?page=upserv-page-remote-sources' ),
			'<i class="fa-solid fa-code-commit"></i>' . __( 'Version Control Systems ', 'updatepulse-server' ),
		);

		return $links;
	}

	public function upserv_admin_tab_states( $states, $page ) {
		$states['remote-sources'] = 'upserv-page-remote-sources' === $page;

		return $states;
	}

	public function force_clean() {
		$result = false;
		$type   = false;

		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'upserv_plugin_options' ) ) {
			return;
		}

		$type = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'schedules' !== $type ) {
			return;
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY );

		if ( ! $data || ! isset( $data['upserv_vcs_list'] ) ) {
			return;
		}

		$vcs_configs = upserv_get_option( 'vcs', array() );
		$key         = $data['upserv_vcs_list'];

		if ( isset( $vcs_configs[ $key ] ) ) {
			$result = $this->clear_remote_check_scheduled_hooks( array( $vcs_configs[ $key ] ) );
		}

		if ( $result && ! $vcs_configs[ $key ]['use_webhooks'] ) {
			$this->reschedule_remote_check_recurring_events( $vcs_configs[ $key ] );
		}

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error(
				new WP_Error(
					__METHOD__,
					__( 'Error - check the packages directory is readable and not empty', 'updatepulse-server' )
				)
			);
		}
	}

	public function vcs_test() {
		$result = false;

		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'upserv_plugin_options' ) ) {
			wp_send_json_error(
				new WP_Error(
					__METHOD__,
					__( 'Error - Received invalid data; please reload the page and try again.', 'updatepulse-server' )
				)
			);
		}

		$data = filter_input( INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY );

		if ( ! $data ) {
			wp_send_json_error(
				new WP_Error(
					__METHOD__,
					__( 'Error - Received invalid data; please reload the page and try again.', 'updatepulse-server' )
				)
			);
		}

		require_once UPSERV_PLUGIN_PATH . 'lib/package-update-checker/package-update-checker.php';

		$url         = $data['upserv_vcs_url'];
		$credentials = $data['upserv_vcs_credentials'];
		$vcs_type    = $data['upserv_vcs_type'];
		$service     = upserv_get_vcs_name( $vcs_type, 'edit' );
		$api_class   = $service ? 'Anyape\PackageUpdateChecker\Vcs\\' . $service . 'Api' : false;
		$test_result = $api_class::test( $url, $credentials );

		if ( true === $test_result ) {
			$result = array( __( 'Version Control System was reached sucessfully.', 'updatepulse-server' ) );
		} elseif ( false === $test_result ) {
			$result = new WP_Error(
				__METHOD__,
				__( 'Error - Please check the provided Version Control System Credentials.', 'updatepulse-server' )
			);
		} elseif ( 'failed_org_check' === $test_result ) {
			$result = new WP_Error(
				__METHOD__,
				__( 'Error - Please check the provided Version Control System URL.', 'updatepulse-server' )
					. "\n"
					. __( 'If you are using a fine-grained access token for an organisation, please check the provided token has the permissions to access members information.', 'updatepulse-server' )
			);
		} elseif ( 'missing_privileges' === $test_result ) {
			$result = new WP_Error(
				__METHOD__,
				__( 'Error - Please check the provided Version Control System URL.', 'updatepulse-server' )
					. "\n"
					. __( 'Please also check the provided credentials have access to account information and repositories.', 'updatepulse-server' )
			);
		} else {
			$result = new WP_Error(
				__METHOD__,
				__( 'Error - Please check the provided Version Control System URL.', 'updatepulse-server' )
			);
		}

		if ( ! is_wp_error( $result ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	// Misc. -------------------------------------------------------

	public static function clear_schedules() {
		$manager = new self();

		return $manager->clear_remote_check_scheduled_hooks();
	}

	public static function register_schedules() {
		$options     = get_option( 'upserv_options' );
		$options     = json_decode( $options, true );
		$options     = $options ? $options : array();
		$vcs_configs = isset( $options['vcs'] ) && ! empty( $options['vcs'] ) ? $options['vcs'] : array();

		if ( empty( $vcs_configs ) ) {
			return;
		}

		$manager = new self();

		foreach ( $vcs_configs as $vcs_c ) {

			if ( ! isset( $vcs_c['url'], $vcs_c['use_webhooks'] ) || $vcs_c['use_webhooks'] ) {
				continue;
			}

			$manager->reschedule_remote_check_recurring_events( $vcs_c );
		}
	}

	public function reschedule_remote_check_recurring_events( $vcs_c ) {

		if (
			upserv_is_doing_update_api_request() ||
			! isset( $vcs_c['url'], $vcs_c['use_webhooks'] ) ||
			$vcs_c['use_webhooks']
		) {
			return false;
		}

		$slugs = $this->get_package_slugs( $vcs_c['url'] );

		if ( empty( $slugs ) ) {
			return false;
		}

		foreach ( $slugs as $slug ) {
			$meta      = upserv_get_package_metadata( $slug );
			$type      = isset( $meta['type'] ) ? $meta['type'] : null;
			$hook      = 'upserv_check_remote_' . $slug;
			$params    = array( $slug, $type, false );
			$frequency = apply_filters(
				'upserv_check_remote_frequency',
				isset( $vcs_c['check_frequency'] ) ? $vcs_c['check_frequency'] : 'daily',
				$slug
			);
			$timestamp = time();
			$schedules = wp_get_schedules();

			as_unschedule_all_actions( $hook );
			do_action( 'upserv_cleared_check_remote_schedule', $slug, $hook );

			$result = as_schedule_recurring_action(
				$timestamp,
				$schedules[ $frequency ]['interval'],
				$hook,
				$params
			);

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

		return true;
	}

	public function plugin_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_cache_set( 'settings_notice', $this->plugin_options_handler(), 'updatepulse-server' );

		$registered_schedules = wp_get_schedules();
		$schedules            = array();
		$vcs_configs          = upserv_get_option( 'vcs', array() );
		$options              = array(
			'use_vcs' => upserv_get_option( 'use_vcs', 0 ),
			'vcs'     => empty( $vcs_configs ) ? '{}' : wp_json_encode( $vcs_configs ),
		);

		foreach ( $registered_schedules as $key => $schedule ) {
			$schedules[ $schedule['display'] ] = array(
				'slug' => $key,
			);
		}

		upserv_get_admin_template(
			'plugin-remote-sources-page.php',
			array(
				'options'              => $options,
				'packages_dir'         => Data_Manager::get_data_dir( 'packages' ),
				'registered_schedules' => $registered_schedules,
				'schedules'            => $schedules,
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function plugin_options_handler() {
		$errors          = array();
		$result          = '';
		$to_save         = array();
		$old_vcs_configs = upserv_get_option( 'vcs', array() );
		$old_use_vcs     = upserv_get_option( 'use_vcs' );

		if (
			isset( $_REQUEST['upserv_plugin_options_handler_nonce'] ) &&
			! wp_verify_nonce( $_REQUEST['upserv_plugin_options_handler_nonce'], 'upserv_plugin_options' )
		) {
			$errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'updatepulse-server' );

			return $errors;
		} elseif ( ! isset( $_REQUEST['upserv_plugin_options_handler_nonce'] ) ) {
			return $result;
		}

		$result  = __( 'UpdatePulse Server options successfully updated', 'updatepulse-server' );
		$options = $this->get_submitted_options();

		foreach ( $options as $option_name => $option_info ) {
			$condition = $option_info['value'];

			if ( isset( $option_info['condition'] ) && 'vcs' === $option_info['condition'] ) {
				$inputs = json_decode( $option_info['value'], true );

				if ( ! is_array( $inputs ) ) {
					$inputs = upserv_get_option( 'vcs', array() );
				} else {
					$option_info['value'] = $this->filter_json_input( $inputs, $option_name, $errors );
				}
			} elseif ( isset( $option_info['condition'] ) && 'boolean' === $option_info['condition'] ) {
				$condition            = true;
				$option_info['value'] = (bool) $option_info['value'];
			}

			$condition = apply_filters(
				'upserv_remote_source_option_update',
				$condition,
				$option_name,
				$option_info,
				$options
			);

			if ( $condition ) {
				$to_save[ $option_info['path'] ] = apply_filters(
					'upserv_remote_sources_option_save_value',
					$option_info['value'],
					$option_name,
					$option_info,
					$options
				);
				$to_save[ $option_info['path'] ] = $option_info['value'];
			} else {
				$errors[ $option_name ] = sprintf(
					// translators: %1$s is the option display name, %2$s is the condition for update
					__( 'Option %1$s was not updated. Reason: %2$s', 'updatepulse-server' ),
					$option_info['display_name'],
					$option_info['failure_display_message']
				);
			}
		}

		if ( ! empty( $to_save ) ) {

			foreach ( $to_save as $key => $value ) {
				$to_update = upserv_set_option( $key, $value );
			}

			upserv_update_options( $to_update );
		}

		if ( ! empty( $errors ) ) {
			$result = $errors;
		}

		$new_use_vcs     = upserv_get_option( 'use_vcs' );
		$new_vcs_configs = upserv_get_option( 'vcs', array() );
		$keys            = array_merge( array_keys( $old_vcs_configs ), array_keys( $new_vcs_configs ) );

		foreach ( $keys as $key ) {
			$old_use_webhooks    = false;
			$new_use_webhooks    = false;
			$clear               = false;
			$reschedule          = false;
			$old_check_frequency = 'daliy';
			$new_check_frequency = 'daily';

			if ( isset( $old_vcs_configs[ $key ], $old_vcs_configs[ $key ]['use_webhooks'] ) ) {
				$old_use_webhooks = (bool) $old_vcs_configs[ $key ]['use_webhooks'];
			}

			if ( isset( $new_vcs_configs[ $key ], $new_vcs_configs[ $key ]['use_webhooks'] ) ) {
				$new_use_webhooks = (bool) $new_vcs_configs[ $key ]['use_webhooks'];
			}

			if ( isset( $old_vcs_configs[ $key ], $old_vcs_configs[ $key ]['check_frequency'] ) ) {
				$old_check_frequency = $old_vcs_configs[ $key ]['check_frequency'];
			}

			if ( isset( $new_vcs_configs[ $key ], $new_vcs_configs[ $key ]['check_frequency'] ) ) {
				$new_check_frequency = $new_vcs_configs[ $key ]['check_frequency'];
			}

			if ( $old_check_frequency !== $new_check_frequency ) {
				$reschedule = true;
			}

			if ( ! $old_use_vcs && $new_use_vcs ) {
				$reschedule = true;
			}

			if ( $old_use_webhooks && ! $new_use_webhooks ) {
				$reschedule = true;
			}

			if ( ! $old_use_webhooks && $new_use_webhooks ) {
				$clear = true;
			}

			if ( $old_use_vcs && ! $new_use_vcs ) {
				$clear      = true;
				$reschedule = false;
			}

			if ( $reschedule && ! isset( $new_vcs_configs[ $key ] ) ) {
				$clear = true;
			} elseif ( ! $clear && $reschedule ) {
				$this->reschedule_remote_check_recurring_events(
					$new_vcs_configs[ $key ]
				);
			}

			if ( $clear ) {
				$vcs_configs = false;

				if ( isset( $new_vcs_configs[ $key ] ) ) {
					$vcs_configs = array( $new_vcs_configs[ $key ] );
				} elseif ( isset( $old_vcs_configs[ $key ] ) ) {
					$vcs_configs = array( $old_vcs_configs[ $key ] );
				}

				if ( $vcs_configs ) {
					$this->clear_remote_check_scheduled_hooks( $vcs_configs );
				}
			}
		}

		set_transient( 'upserv_flush', 1, 60 );
		do_action( 'upserv_remote_sources_options_updated', $result );

		return $result;
	}

	protected function filter_json_input( $inputs, $option_name, &$errors ) {
		$filtered    = array();
		$index       = 0;
		$error_array = array();

		foreach ( $inputs as $id => $values ) {
			$url    = filter_var( $values['url'], FILTER_VALIDATE_URL );
			$url    = trailingslashit( $url );
			$branch = filter_var( $values['branch'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$id     = hash( 'sha256', $url . '|' . $branch );

			if ( ! preg_match( '@^https?://[^/]+/[^/]+/$@', $url ) || ! $branch ) {
				$error_array[] = sprintf(
					// translators: %d is the index of the item in the list
					__( 'Invalid URL or Branch for item at index %d', 'updatepulse-server' ),
					$index
				);

				continue;
			}

			$type = filter_var( $values['type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! $type || 'undefined' === $type ) {
				$error_array[] = sprintf(
					// translators: %d is the index of the item in the list
					__( 'Undefined VCS Type for item at index %d', 'updatepulse-server' ),
					$index
				);

				continue;
			}

			$self_hosted       = intval( filter_var( $values['self_hosted'], FILTER_VALIDATE_BOOLEAN ) );
			$credentials       = filter_var( $values['credentials'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$filter_packages   = intval( filter_var( $values['filter_packages'], FILTER_VALIDATE_BOOLEAN ) );
			$check_frequency   = filter_var( $values['check_frequency'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$use_webhooks      = intval( filter_var( $values['use_webhooks'], FILTER_VALIDATE_BOOLEAN ) );
			$check_delay       = intval( filter_var( $values['check_delay'], FILTER_VALIDATE_INT ) );
			$webhook_secret    = filter_var( $values['webhook_secret'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$known_frequencies = wp_get_schedules();
			$known_frequencies = array_keys( $known_frequencies );

			if ( ! in_array( $check_frequency, $known_frequencies, true ) ) {
				$check_frequency = 'daily';
			}

			$filtered[ $id ] = array(
				'url'             => $url,
				'branch'          => $branch,
				'type'            => $type,
				'self_hosted'     => $self_hosted,
				'credentials'     => $credentials,
				'filter_packages' => $filter_packages,
				'check_frequency' => $check_frequency,
				'use_webhooks'    => $use_webhooks,
				'check_delay'     => $check_delay,
				'webhook_secret'  => $webhook_secret,
			);

			++$index;
		}

		if ( ! empty( $error_array ) ) {
			$errors[ $option_name ] = implode( '<br>', $error_array );
		}

		return $filtered;
	}

	protected function get_submitted_options() {
		return apply_filters(
			'upserv_submitted_remote_sources_config',
			array(
				'upserv_use_vcs' => array(
					'value'        => filter_input( INPUT_POST, 'upserv_use_vcs', FILTER_VALIDATE_BOOLEAN ),
					'display_name' => __( 'Enable VCS', 'updatepulse-server' ),
					'condition'    => 'boolean',
					'path'         => 'use_vcs',
				),
				'upserv_vcs'     => array(
					'value'                   => filter_input( INPUT_POST, 'upserv_vcs', FILTER_UNSAFE_RAW ),
					'display_name'            => __( 'Version Control Systems', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid payload', 'updatepulse-server' ),
					'condition'               => 'vcs',
					'path'                    => 'vcs',
				),
			)
		);
	}

	protected function get_package_slugs( $vcs_url ) {
		$slugs = wp_cache_get( 'package_slugs', 'updatepulse-server' );

		if ( false === $slugs ) {
			$slugs    = array();
			$meta_dir = Data_Manager::get_data_dir( 'metadata' );

			if ( is_dir( $meta_dir ) ) {
				$meta_paths = glob( trailingslashit( $meta_dir ) . '*.json' );

				if ( ! empty( $meta_paths ) ) {

					foreach ( $meta_paths as $meta_path ) {
						$meta_path_parts = explode( '/', $meta_path );
						$slugs[]         = str_replace( '.json', '', end( $meta_path_parts ) );
					}
				}
			}

			if ( empty( $slugs ) ) {

				foreach ( $slugs as $idx => $slug ) {
					$meta = upserv_get_package_metadata( $slug );
					$mode = upserv_get_option( 'use_cloud_storage' ) ? 'cloud' : 'local';

					if (
						! isset( $meta['vcs'] ) ||
						trailingslashit( $meta['vcs'] ) !== trailingslashit( $vcs_url ) ||
						! isset( $meta['whitelisted'] ) ||
						! isset( $meta['whitelisted'][ $mode ] ) ||
						! $meta['whitelisted'][ $mode ]
					) {
						unset( $slugs[ $idx ] );
					}
				}
			}

			wp_cache_set( 'package_slugs', $slugs, 'updatepulse-server' );
		}

		return $slugs;
	}
}
