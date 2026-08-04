<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use DateTime;
use DateTimeZone;
use Anyape\Utils\Utils;
use Anyape\UpdatePulse\Server\Table\Licenses_Table;
use Anyape\UpdatePulse\Server\Server\License\License_Server;
use Anyape\UpdatePulse\Server\Scheduler\Scheduler;

/**
 * License Manager class
 *
 * @since 1.0.0
 */
class License_Manager {

	/**
	 * Licenses table
	 *
	 * @var Licenses_Table|null
	 * @since 1.0.0
	 */
	protected $licenses_table;
	/**
	 * Message to display
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $message = '';
	/**
	 * Error messages
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $errors = array();
	/**
	 * License server instance
	 *
	 * @var License_Server|null
	 * @since 1.0.0
	 */
	protected $license_server;

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			$use_licenses = upserv_get_option( 'use_licenses' );

			if ( $use_licenses ) {
				$this->license_server = new License_Server();

				add_action( 'upserv_scheduler_init', array( $this, 'upserv_scheduler_init' ), 10, 0 );
				add_action( 'upserv_packages_table_cell', array( $this, 'upserv_packages_table_cell' ), 10, 4 );

				add_filter( 'upserv_packages_table_columns', array( $this, 'upserv_packages_table_columns' ), 10, 1 );
				add_filter( 'upserv_packages_table_sortable_columns', array( $this, 'upserv_packages_table_sortable_columns' ), 10, 1 );
			}

			add_filter( 'upserv_admin_scripts', array( $this, 'upserv_admin_scripts' ), 10, 1 );
			add_filter( 'upserv_admin_styles', array( $this, 'upserv_admin_styles' ), 10, 1 );
			add_action( 'admin_init', array( $this, 'admin_init' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 20, 0 );
			add_filter( 'upserv_admin_tab_links', array( $this, 'upserv_admin_tab_links' ), 20, 1 );
			add_filter( 'upserv_admin_tab_states', array( $this, 'upserv_admin_tab_states' ), 20, 2 );
			add_action( 'load-updatepulse_page_upserv-page-licenses', array( $this, 'add_page_options' ), 10, 0 );

			add_filter( 'set-screen-option', array( $this, 'set_page_options' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Activate license system
	 *
	 * Creates necessary database tables and sets up license expiration schedule.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		$result = self::maybe_create_or_upgrade_db();

		if ( ! $result ) {
			$error_message = __( 'Failed to create the necessary database table(s).', 'updatepulse-server' );

			die( $error_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$manager = new self();
		$manager->register_license_schedules();
	}

	/**
	 * Deactivate license system
	 *
	 * Removes scheduled license expiration tasks.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		Scheduler::get_instance()->unschedule_all_actions( 'upserv_expire_licenses' );

		/**
		 * Fired after the license maintenance event has been unscheduled.
		 */
		do_action( 'upserv_cleared_license_schedule' );
	}

	/**
	 * Initialize scheduler
	 *
	 * Sets up recurring schedule for license expiration checks.
	 *
	 * @since 1.0.0
	 */
	public function upserv_scheduler_init() {
		$hook = 'upserv_expire_licenses';

		if ( ! Scheduler::get_instance()->has_scheduled_action( $hook ) ) {
			/**
			 * Filter the frequency at which the license maintenance task runs.
			 *
			 * @param string $frequency The WordPress schedule frequency (daily, etc.)
			 */
			$frequency = apply_filters( 'upserv_schedule_license_frequency', 'hourly' );
			$schedules = wp_get_schedules();
			$d         = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );

			$d->setTime( 12, 0, 0 );

			$timestamp = $d->getTimestamp() + DAY_IN_SECONDS;
			$result    = Scheduler::get_instance()->schedule_recurring_action(
				$timestamp,
				$schedules[ $frequency ]['interval'],
				$hook
			);

			/**
			 * Fired after the license maintenance event has been scheduled.
			 *
			 * @param bool|int $result The Action Scheduler ID or WordPress cron result.
			 * @param int $timestamp Timestamp for when to run the event the first time
			 * @param string $frequency Frequency at which the event would be ran
			 * @param string $hook Event hook to fire when the event is ran
			 */
			do_action( 'upserv_scheduled_license_event', $result, $timestamp, $frequency, $hook );
		}

		$this->register_license_schedules();
	}

	/**
	 * Initialize admin area
	 *
	 * Sets up license table and processes form submissions.
	 *
	 * @since 1.0.0
	 */
	public function admin_init() {

		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$this->licenses_table = new Licenses_Table();

		if (
			! (
				isset( $_REQUEST['_wpnonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ),
					$this->licenses_table->nonce_action
				)
			) &&
			! (
				isset( $_REQUEST['linknonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_REQUEST['linknonce'] ) ),
					'linknonce'
				)
			) &&
			! (
				isset( $_REQUEST['upserv_license_form_nonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_REQUEST['upserv_license_form_nonce'] ) ),
					'upserv_license_form_nonce'
				)
			)
		) {
			return;
		}

		$page = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false;

		if ( 'upserv-page-licenses' !== $page ) {
			return;
		}

		$license_data        = false;
		$delete_all_licenses = ! empty( $_REQUEST['upserv_delete_all_licenses'] ) ? true : false;
		$action              = ! empty( $_REQUEST['upserv_license_action'] ) ?
			sanitize_text_field( wp_unslash( $_REQUEST['upserv_license_action'] ) ) :
			false;

		if ( ! empty( $_REQUEST['license_data'] ) ) {

			if ( is_array( $_REQUEST['license_data'] ) ) {
				$license_data = array_map( 'wp_kses_post', wp_unslash( $_REQUEST['license_data'] ) );
			} else {
				$license_data = wp_kses_post( wp_unslash( $_REQUEST['license_data'] ) );
			}
		}

		if ( ! empty( $_REQUEST['upserv_license_values'] ) ) {

			if ( is_array( $_REQUEST['upserv_license_values'] ) ) {
				$license_data = array_map( 'wp_kses_post', wp_unslash( $_REQUEST['upserv_license_values'] ) );
			} else {
				$license_data = wp_kses_post( wp_unslash( $_REQUEST['upserv_license_values'] ) );
			}
		}

		if ( ! empty( $_REQUEST['action'] ) && -1 !== intval( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( ! empty( $_REQUEST['action2'] ) && -1 !== intval( $_REQUEST['action2'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( $license_data && in_array( $action, License_Server::$license_statuses, true ) ) {
			$this->change_license_statuses_bulk(
				$action,
				is_array( $license_data ) ? $license_data : array( $license_data )
			);
		} elseif ( false !== $license_data && 'delete' === $action ) {
			$this->delete_license_bulk(
				is_array( $license_data ) ? $license_data : array( $license_data )
			);
		} elseif ( $license_data && 'update' === $action ) {
			$this->update_license(
				is_array( $license_data ) ? reset( $license_data ) : $license_data
			);
		} elseif ( $license_data && 'create' === $action ) {
			$this->create_license(
				is_array( $license_data ) ? reset( $license_data ) : $license_data
			);
		} elseif ( $delete_all_licenses ) {
			$this->delete_all_licenses();
		}
	}

	/**
	 * Register admin styles
	 *
	 * Adds license-specific styles to the admin area.
	 *
	 * @param array $styles Existing admin styles
	 * @return array Modified admin styles
	 * @since 1.0.0
	 */
	public function upserv_admin_styles( $styles ) {
		$styles['license'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/admin/license' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/admin/license' . upserv_assets_suffix() . '.css',
		);

		wp_enqueue_style( 'wp-codemirror' );

		return $styles;
	}

	/**
	 * Register admin scripts
	 *
	 * Adds license-specific scripts to the admin area.
	 *
	 * @param array $scripts Existing admin scripts
	 * @return array Modified admin scripts
	 * @since 1.0.0
	 */
	public function upserv_admin_scripts( $scripts ) {
		$page = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'upserv-page-licenses' !== $page ) {
			return $scripts;
		}

		$l10n['deleteLicensesConfirm'] = array(
			__( 'You are about to delete all the licenses from this server.', 'updatepulse-server' ),
			__( 'All the records will be permanently deleted.', 'updatepulse-server' ),
			__( 'Packages requiring these licenses will not be able to get a successful response from this server.', 'updatepulse-server' ),
			"\n",
			__( 'Are you sure you want to do this?', 'updatepulse-server' ),
		);
		$l10n['deleteLicenseConfirm']  = __( 'Are you sure you want to delete this license?', 'updatepulse-server' );
		$scripts['license']            = array(
			'path'   => UPSERV_PLUGIN_PATH . 'js/admin/license' . upserv_assets_suffix() . '.js',
			'uri'    => UPSERV_PLUGIN_URL . 'js/admin/license' . upserv_assets_suffix() . '.js',
			'deps'   => array( 'jquery' ),
			'params' => array(
				'cm_settings' => wp_enqueue_code_editor( array( 'type' => 'text/json' ) ),
			),
			/**
			 * Filter the internationalization strings passed to the frontend scripts.
			 *
			 * @param array $l10n The internationalization strings passed to the frontend scripts
			 * @param string $handle The handle of the script
			 */
			'l10n'   => apply_filters( 'upserv_scripts_l10n', $l10n, 'license' ),
		);

		wp_enqueue_script( 'wp-theme-plugin-editor' );

		return $scripts;
	}

	/**
	 * Add page options
	 *
	 * Adds screen options for the licenses page.
	 *
	 * @since 1.0.0
	 */
	public function add_page_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Licenses per page', 'updatepulse-server' ),
			'default' => 10,
			'option'  => 'licenses_per_page',
		);

		add_screen_option( $option, $args );
	}

	/**
	 * Pass through a submitted page option value.
	 *
	 * WordPress performs the actual option persistence after this filter returns.
	 *
	 * @param mixed  $status Status of the option
	 * @param string $option Option name
	 * @param mixed  $value Option value
	 * @return mixed Filtered option value
	 * @since 1.0.0
	 */
	public function set_page_options( $status, $option, $value ) {
		return $value;
	}

	/**
	 * Add package table columns
	 *
	 * Adds license columns to the packages table.
	 *
	 * @param array $columns Existing table columns
	 * @return array Modified table columns
	 * @since 1.0.0
	 */
	public function upserv_packages_table_columns( $columns ) {
		$columns['col_use_license'] = __( 'License status', 'updatepulse-server' );

		return $columns;
	}

	/**
	 * Add sortable columns
	 *
	 * Adds sortable license columns to the packages table.
	 *
	 * @param array $columns Existing sortable columns
	 * @return array Modified sortable columns
	 * @since 1.0.0
	 */
	public function upserv_packages_table_sortable_columns( $columns ) {
		$columns['col_use_license'] = __( 'License status', 'updatepulse-server' );

		return $columns;
	}

	/**
	 * Display package table cell content
	 *
	 * Populates license status cells in the packages table.
	 *
	 * @param string $column_name Name of the column
	 * @param array  $record Record data
	 * @param string $record_key Record identifier
	 * @since 1.0.0
	 */
	public function upserv_packages_table_cell( $column_name, $record, $record_key ) {
		$use_license = upserv_is_package_require_license( $record_key );

		if ( 'col_use_license' === $column_name ) {
			echo esc_html( ( $use_license ) ? __( 'Required', 'updatepulse-server' ) : __( 'Not Required', 'updatepulse-server' ) );
		}
	}

	/**
	 * Add admin menu
	 *
	 * Registers the licenses submenu page.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		$function    = array( $this, 'plugin_page' );
		$page_title  = __( 'UpdatePulse Server - Licenses', 'updatepulse-server' );
		$menu_title  = __( 'Licenses', 'updatepulse-server' );
		$menu_slug   = 'upserv-page-licenses';
		$parent_slug = 'upserv-page';
		$capability  = 'manage_options';

		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	}

	/**
	 * Add admin tab links
	 *
	 * Adds licenses tab to the admin navigation.
	 *
	 * @param array $links Existing tab links
	 * @return array Modified tab links
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_links( $links ) {
		$links['licenses'] = array(
			admin_url( 'admin.php?page=upserv-page-licenses' ),
			'<i class="fa-solid fa-key"></i>' . __( 'Licenses', 'updatepulse-server' ),
		);

		return $links;
	}

	/**
	 * Manage admin tab states
	 *
	 * Updates active state for the licenses tab.
	 *
	 * @param array  $states Current tab states
	 * @param string $page Current page
	 * @return array Modified tab states
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_states( $states, $page ) {
		$states['licenses'] = 'upserv-page-licenses' === $page;

		return $states;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Expire licenses
	 *
	 * Changes status of expired licenses.
	 *
	 * @since 1.0.0
	 */
	public function expire_licenses() {
		$this->license_server->switch_expired_licenses_status();
	}

	/**
	 * Register license schedules
	 *
	 * Sets up hooks for scheduled license tasks.
	 *
	 * @since 1.0.0
	 */
	public function register_license_schedules() {
		$scheduled_hook = array( $this, 'expire_licenses' );

		add_action( 'upserv_expire_licenses', $scheduled_hook, 10, 2 );

		/**
		 * Fired after the license maintenance action has been registered.
		 *
		 * @param callable $scheduled_hook The license maintenance callback that has been registered.
		 */
		do_action( 'upserv_registered_license_schedule', $scheduled_hook );
	}

	/**
	 * Display plugin page
	 *
	 * Renders the licenses management page.
	 *
	 * @since 1.0.0
	 */
	public function plugin_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$licenses_table = $this->licenses_table;
		$notices        = $this->plugin_options_handler();
		$options        = array(
			'use_licenses' => upserv_get_option( 'use_licenses', 0 ),
		);
		$status_options = array(
			'pending'     => Utils::get_status_string( 'pending' ),
			'activated'   => Utils::get_status_string( 'activated' ),
			'deactivated' => Utils::get_status_string( 'deactivated' ),
			'on-hold'     => Utils::get_status_string( 'on-hold' ),
			'blocked'     => Utils::get_status_string( 'blocked' ),
			'expired'     => Utils::get_status_string( 'expired' ),
		);

		$licenses_table->prepare_items();

		if ( ! $notices ) {

			if ( ! empty( $this->errors ) ) {
				$notices = $this->errors;
			} else {
				$notices = $this->message;
			}
		}

		wp_cache_set( 'settings_notice', $notices, 'updatepulse-server' );
		upserv_get_admin_template(
			'plugin-licenses-page.php',
			array(
				'licenses_table' => $licenses_table,
				'options'        => $options,
				'status_options' => $status_options,
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Create or upgrade database
	 *
	 * Sets up and updates the licenses database table.
	 *
	 * @return bool Whether database setup was successful
	 * @since 1.0.0
	 */
	protected static function maybe_create_or_upgrade_db() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql =
			"CREATE TABLE {$wpdb->prefix}upserv_licenses (
				id int(12) NOT NULL auto_increment,
				license_key varchar(255) NOT NULL,
				max_allowed_domains int(12) NOT NULL,
				allowed_domains longtext NOT NULL,
				status ENUM('pending', 'activated', 'deactivated', 'on-hold', 'blocked', 'expired') NOT NULL DEFAULT 'pending',
				owner_name varchar(255) NOT NULL default '',
				email varchar(64) NOT NULL,
				company_name varchar(100) NOT NULL default '',
				txn_id varchar(64) NOT NULL default '',
				date_created date NOT NULL DEFAULT '0000-00-00',
				date_renewed date NOT NULL DEFAULT '0000-00-00',
				date_expiry date NOT NULL DEFAULT '0000-00-00',
				package_slug varchar(255) NOT NULL default '',
				package_type varchar(8) NOT NULL default '',
				hmac_key varchar(64) NOT NULL,
				crypto_key varchar(64) NOT NULL,
				data longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY license_key (license_key)
			) {$charset_collate};";

		dbDelta( $sql );

		$table_name = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}upserv_licenses'" );

		if ( "{$wpdb->prefix}upserv_licenses" !== $table_name ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle plugin options
	 *
	 * Processes and saves plugin settings.
	 *
	 * @return string|bool Success message or false on failure
	 * @since 1.0.0
	 */
	protected function plugin_options_handler() {
		$errors  = array();
		$result  = '';
		$to_save = array();
		$nonce   = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'upserv_plugin_options_handler_nonce' ) ) );

		if ( $nonce && ! wp_verify_nonce( $nonce, 'upserv_plugin_options' ) ) {
			$this->errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'updatepulse-server' );

			return false;
		} elseif ( ! $nonce ) {
			return $result;
		}

		$result  = __( 'UpdatePulse Server license options successfully updated.', 'updatepulse-server' );
		$options = $this->get_submitted_options();

		foreach ( $options as $option_name => $option_info ) {
			$condition = $option_info['value'];

			if ( isset( $option_info['condition'] ) ) {

				if ( 'boolean' === $option_info['condition'] ) {
					$condition            = true;
					$option_info['value'] = ( $option_info['value'] );
				}
			}

			if ( $condition ) {
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
			$to_update = array();

			foreach ( $to_save as $path => $value ) {
				$to_update = upserv_set_option( $path, $value );
			}

			upserv_update_options( $to_update );
		}

		if ( ! empty( $errors ) ) {
			$result       = false;
			$this->errors = $errors;
		}

		return $result;
	}

	/**
	 * Get submitted options
	 *
	 * Retrieves and validates options from form submission.
	 *
	 * @return array Options array with validation parameters
	 * @since 1.0.0
	 */
	protected function get_submitted_options() {
		/**
		 * Filter the submitted license configuration values before saving them.
		 *
		 * @param array $config The submitted license configuration values
		 */
		return apply_filters(
			'upserv_submitted_licenses_config',
			array(
				'upserv_use_licenses' => array(
					'value'        => filter_input( INPUT_POST, 'upserv_use_licenses', FILTER_VALIDATE_BOOLEAN ),
					'display_name' => __( 'Enable Package Licenses', 'updatepulse-server' ),
					'condition'    => 'boolean',
					'path'         => 'use_licenses',
				),
			)
		);
	}

	/**
	 * Change license statuses in bulk
	 *
	 * Updates status for multiple licenses at once.
	 *
	 * @param string $status New status to apply
	 * @param array  $license_data Licenses to update
	 * @since 1.0.0
	 */
	protected function change_license_statuses_bulk( $status, $license_data ) {

		if ( ! is_array( $license_data ) ) {
			$this->errors[] = __( 'Operation failed: an unexpected error occured (invalid license data).', 'updatepulse-server' );

			return;
		}

		$applicable_license_ids = array();
		$license_ids            = array();

		foreach ( $license_data as $data ) {
			$license_info = json_decode( $data );
			$include      = false;

			if ( in_array( $status, License_Server::$license_statuses, true ) ) {

				if ( 'blocked' === $status || 'expired' === $status ) {
					$include = true;
				} elseif ( '0000-00-00' !== $license_info->date_expiry ) {
					$timezone    = new DateTimeZone( wp_timezone_string() );
					$date_expiry = new DateTime( $license_info->date_expiry, $timezone );
					$include     = time() < $date_expiry->getTimestamp();
				} else {
					$include = true;
				}

				if ( ! is_numeric( $license_info->id ) ) {
					$include = false;
				}
			}

			if ( $status !== $license_info->status && $include ) {
				$applicable_license_ids[] = $license_info->id;
			}

			$license_ids[] = $license_info->id;
		}

		if ( ! in_array( $status, License_Server::$license_statuses, true ) ) {
			$this->errors[] = __( 'Operation failed: an unexpected error occured (invalid license status).', 'updatepulse-server' );

			return;
		}

		if ( ! empty( $applicable_license_ids ) ) {
			$this->license_server->update_licenses_status( $status, $applicable_license_ids );

			$this->message = __( 'Status of the selected licenses updated successfully where applicable - IDs of updated licenses: ', 'updatepulse-server' ) . implode( ', ', $applicable_license_ids );
		} else {
			$this->errors[] = __( 'Operation failed: all the selected licenses have passed their expiry date, or already have the selected status - IDs: ', 'updatepulse-server' ) . implode( ', ', $license_ids );
		}
	}

	/**
	 * Delete licenses in bulk
	 *
	 * Removes multiple licenses from the system.
	 *
	 * @param array $license_ids IDs of licenses to delete
	 * @return array License IDs submitted for deletion.
	 * @since 1.0.0
	 */
	protected function delete_license_bulk( $license_ids ) {

		if ( ! is_array( $license_ids ) ) {
			return array();
		}

		foreach ( $license_ids as $key => $data ) {

			if ( ! is_numeric( $data ) ) {
				$license = json_decode( $data );

				if ( isset( $license->id ) ) {
					$license_ids[ $key ] = $license->id;
				} else {
					unset( $license_ids[ $key ] );
				}
			}
		}

		$this->license_server->purge_licenses( $license_ids );

		$this->message = __( 'Selected licenses deleted - IDs: ', 'updatepulse-server' ) . implode( ', ', $license_ids );

		return $license_ids;
	}

	/**
	 * Update a license
	 *
	 * Updates an existing license with new data.
	 *
	 * @param string $license_data JSON encoded license data
	 * @since 1.0.0
	 */
	protected function update_license( $license_data ) {
		$payload         = json_decode( $license_data, true );
		$payload['data'] = json_decode( $payload['data'], true );
		$license         = upserv_edit_license( $payload );

		if ( is_object( $license ) ) {
			$this->message = __( 'License edited successfully.', 'updatepulse-server' );
		} else {
			$this->errors   = array_merge( $this->errors, $license );
			$this->errors[] = __( 'Failed to update the license record in the database.', 'updatepulse-server' );
			$this->errors[] = __( 'License update failed.', 'updatepulse-server' );
		}
	}

	/**
	 * Create a new license
	 *
	 * Adds a new license to the system.
	 *
	 * @param string $license_data JSON encoded license data
	 * @since 1.0.0
	 */
	protected function create_license( $license_data ) {
		$payload         = json_decode( $license_data, true );
		$payload['data'] = json_decode( $payload['data'], true );
		$license         = upserv_add_license( $payload );

		if ( is_object( $license ) ) {
			$this->message = __( 'License added successfully.', 'updatepulse-server' );
		} else {
			$this->errors   = array_merge( $this->errors, $license );
			$this->errors[] = __( 'Failed to insert the license record in the database.', 'updatepulse-server' );
			$this->errors[] = __( 'License creation failed.', 'updatepulse-server' );
		}
	}

	/**
	 * Delete all licenses
	 *
	 * Removes all licenses from the system.
	 *
	 * @since 1.0.0
	 */
	protected function delete_all_licenses() {
		$this->license_server->purge_licenses();

		$this->message = __( 'All the licenses have been deleted.', 'updatepulse-server' );
	}
}
