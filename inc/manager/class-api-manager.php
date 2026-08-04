<?php

namespace Anyape\UpdatePulse\Server\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use stdClass;

/**
 * API Manager class
 *
 * @since 1.0.0
 */
class API_Manager {

	/**
	 * Constructor
	 *
	 * @param boolean $init_hooks Whether to initialize hooks
	 * @since 1.0.0
	 */
	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 20, 0 );

			add_filter( 'upserv_admin_scripts', array( $this, 'upserv_admin_scripts' ), 10, 1 );
			add_filter( 'upserv_admin_styles', array( $this, 'upserv_admin_styles' ), 10, 1 );
			add_filter( 'upserv_admin_tab_links', array( $this, 'upserv_admin_tab_links' ), 20, 1 );
			add_filter( 'upserv_admin_tab_states', array( $this, 'upserv_admin_tab_states' ), 20, 2 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	// WordPress hooks ---------------------------------------------

	/**
	 * Register admin styles
	 *
	 * Add custom styles used by the API admin interface.
	 *
	 * @param array $styles Existing admin styles.
	 * @return array Modified admin styles.
	 * @since 1.0.0
	 */
	public function upserv_admin_styles( $styles ) {
		$styles['api'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'css/admin/api' . upserv_assets_suffix() . '.css',
			'uri'  => UPSERV_PLUGIN_URL . 'css/admin/api' . upserv_assets_suffix() . '.css',
		);

		return $styles;
	}

	/**
	 * Register admin scripts
	 *
	 * Add custom scripts used by the API admin interface.
	 *
	 * @param array $scripts Existing admin scripts.
	 * @return array Modified admin scripts.
	 * @since 1.0.0
	 */
	public function upserv_admin_scripts( $scripts ) {
		$page = ! empty( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'upserv-page-api' !== $page ) {
			return $scripts;
		}

		$l10n = array(
			'deleteApiKeyConfirm'           => array(
				__( 'You are about to delete an API key.', 'updatepulse-server' ),
				__( 'If you proceed, the remote systems using it will not be able to access the API anymore.', 'updatepulse-server' ),
				"\n",
				__( 'Are you sure you want to do this?', 'updatepulse-server' ),
			),
			'deleteApiWebhookConfirm'       => array(
				__( 'You are about to delete a Webhook.', 'updatepulse-server' ),
				__( 'If you proceed, the Payload URL will not receive the configured events anymore.', 'updatepulse-server' ),
				"\n",
				__( 'Are you sure you want to do this?', 'updatepulse-server' ),
			),
			'addWebhookNoLicenseApiConfirm' => array(
				__( 'You are about to add a Webhook without License API Key ID.', 'updatepulse-server' ),
				__( 'If you proceed, the Payload URL will receive events for ALL licenses.', 'updatepulse-server' ),
				"\n",
				__( 'Are you sure you want to do this?', 'updatepulse-server' ),
			),
			'actionApiCountSingular'        => __( '1 action', 'updatepulse-server' ),
			'actionApiCountSingularOther'   => __( '1 action (all records)', 'updatepulse-server' ),
			// translators: %d is the number of actions
			'actionApiCountPlural'          => __( '%d actions', 'updatepulse-server' ),
			// translators: %d is the number of actions
			'actionApiCountPluralOther'     => __( '%d actions (all records)', 'updatepulse-server' ),
			'actionApiCountAll'             => __( 'All actions', 'updatepulse-server' ),
			'actionApiCountAllOther'        => __( 'All actions (all records)', 'updatepulse-server' ),
			'eventApiCountAll'              => __( 'All events', 'updatepulse-server' ),
			// translators: %s is the type of events
			'eventApiCountAllType'          => __( 'All %s events', 'updatepulse-server' ),
			// translators: %s is the type of event
			'eventApiCountTypeSingular'     => __( '1 %s event', 'updatepulse-server' ),
			// translators: %1$d is the number of events, %s is the type of events
			'eventApiCountTypePlural'       => __( '%1$d %2$s events', 'updatepulse-server' ),
			'eventApiTypePackage'           => _x( 'package', 'UpdatePulse Server webhook event type', 'updatepulse-server' ),
			'eventApiTypeLicense'           => _x( 'license', 'UpdatePulse Server webhook event type', 'updatepulse-server' ),
			// translators: the separator between summaries; example: All package events, 3 license events
			'apiSumSep'                     => _x( ', ', 'UpdatePulse Server separator between API summaries', 'updatepulse-server' ),
		);
		$scripts['api'] = array(
			'path' => UPSERV_PLUGIN_PATH . 'js/admin/api' . upserv_assets_suffix() . '.js',
			'uri'  => UPSERV_PLUGIN_URL . 'js/admin/api' . upserv_assets_suffix() . '.js',
			'deps' => array( 'jquery' ),
			'l10n' => apply_filters( 'upserv_scripts_l10n', $l10n, 'api' ),
		);

		return $scripts;
	}

	/**
	 * Register admin menu
	 *
	 * Add the API settings page to the admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		$function   = array( $this, 'plugin_page' );
		$page_title = __( 'UpdatePulse Server - API & Webhooks', 'updatepulse-server' );
		$menu_title = __( 'API & Webhooks', 'updatepulse-server' );
		$menu_slug  = 'upserv-page-api';

		add_submenu_page( 'upserv-page', $page_title, $menu_title, 'manage_options', $menu_slug, $function );
	}

	/**
	 * Register admin tab links
	 *
	 * Add API tab to the admin navigation.
	 *
	 * @param array $links Existing tab links.
	 * @return array Modified tab links.
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_links( $links ) {
		$links['api'] = array(
			admin_url( 'admin.php?page=upserv-page-api' ),
			'<i class="fa-solid fa-share-nodes"></i>' . __( 'API & Webhooks', 'updatepulse-server' ),
		);

		return $links;
	}

	/**
	 * Register admin tab states
	 *
	 * Set active state for API tab in admin navigation.
	 *
	 * @param array  $states Existing tab states.
	 * @param string $page Current admin page.
	 * @return array Modified tab states.
	 * @since 1.0.0
	 */
	public function upserv_admin_tab_states( $states, $page ) {
		$states['api'] = 'upserv-page-api' === $page;

		return $states;
	}

	// Misc. -------------------------------------------------------

	/**
	 * Render plugin page
	 *
	 * Output the API settings admin interface.
	 *
	 * @since 1.0.0
	 */
	public function plugin_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_cache_set( 'settings_notice', $this->plugin_options_handler(), 'updatepulse-server' );

		$options = array(
			'package_private_api_keys'         => wp_json_encode(
				upserv_get_option(
					'api/packages/private_api_keys',
					(object) array()
				)
			),
			'package_private_api_ip_whitelist' => upserv_get_option(
				'api/packages/private_api_ip_whitelist',
				array()
			),
			'license_private_api_keys'         => wp_json_encode(
				upserv_get_option(
					'api/licenses/private_api_keys',
					(object) array()
				)
			),
			'license_private_api_ip_whitelist' => upserv_get_option(
				'api/licenses/private_api_ip_whitelist',
				array()
			),
			'webhooks'                         => wp_json_encode(
				upserv_get_option(
					'api/webhooks',
					(object) array(),
				)
			),
		);

		upserv_get_admin_template(
			'plugin-api-page.php',
			array(
				'options'             => $options,
				/**
				 * Filter the list of available License API actions.
				 *
				 * @param array $actions The list of available License API actions
				 * @return array The filtered list of actions
				 * @since 1.0.0
				 */
				'license_api_actions' => apply_filters(
					'upserv_api_license_actions',
					array()
				),
				/**
				 * Filter the list of available Package API actions.
				 *
				 * @param array $actions The list of available Package API actions
				 * @return array The filtered list of actions
				 * @since 1.0.0
				 */
				'package_api_actions' => apply_filters(
					'upserv_api_package_actions',
					array()
				),
				/**
				 * Filter the list of available webhook events.
				 *
				 * @param array $webhook_events The list of available webhook events
				 * @return array The filtered list of webhook events
				 * @since 1.0.0
				 */
				'webhook_events'      => apply_filters(
					'upserv_api_webhook_events',
					array(
						'package' => array(
							'label'  => __( 'Package events', 'updatepulse-server' ),
							'events' => array(),
						),
						'license' => array(
							'label'  => __( 'License events', 'updatepulse-server' ),
							'events' => array(),
						),
					)
				),
			)
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Handle plugin options
	 *
	 * Process and save API settings form submissions.
	 *
	 * @return string|array Success message or array of errors.
	 * @since 1.0.0
	 */
	protected function plugin_options_handler() {
		$errors  = array();
		$result  = '';
		$to_save = array();
		$nonce   = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'upserv_plugin_options_handler_nonce' ) ) );

		if ( $nonce && ! wp_verify_nonce( $nonce, 'upserv_plugin_options' ) ) {
			$errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'updatepulse-server' );

			return $errors;
		} elseif ( ! $nonce ) {
			return $result;
		}

		$result  = __( 'UpdatePulse Server options successfully updated', 'updatepulse-server' );
		$options = $this->get_submitted_options();

		foreach ( $options as $option_name => $option_info ) {
			$condition = $option_info['value'];

			if ( isset( $option_info['condition'] ) && 'ip-list' === $option_info['condition'] ) {
				$condition = true;

				if ( empty( $option_info['value'] ) ) {
					$option_info['value'] = array();
				} else {
					$option_info['value'] = array_filter(
						array_map(
							'trim',
							explode( "\n", $option_info['value'] )
						)
					);
					$option_info['value'] = array_unique(
						array_filter(
							array_map(
								function ( $value ) {
									$parts = explode( '/', $value );

									if ( count( $parts ) > 2 || count( $parts ) < 1 ) {
										return null;
									}

									$ip = filter_var( $parts[0], FILTER_VALIDATE_IP );

									if ( ! $ip ) {
										return null;
									}

									if ( isset( $parts[1] ) ) {
										$args = array(
											'options' => array(
												'min_range' => 0,
												'max_range' => 32,
											),
										);
										$cdir = filter_var( $parts[1], FILTER_VALIDATE_INT, $args );

										if ( false === $cdir ) {
											return null;
										}
									} else {
										$cdir = 32;
									}

									return $ip . '/' . $cdir;
								},
								$option_info['value']
							)
						)
					);
				}
			} elseif ( isset( $option_info['condition'] ) && 'api-keys' === $option_info['condition'] ) {
				$inputs = json_decode( $option_info['value'], true );
				$prefix = '';

				if ( 'upserv_package_private_api_keys' === $option_name ) {
					$prefix = 'UPDATEPULSE_P_';
				} elseif ( 'upserv_license_private_api_keys' === $option_name ) {
					$prefix = 'UPDATEPULSE_L_';
				}

				if ( empty( $option_info['value'] ) || json_last_error() ) {
					$option_info['value'] = (object) array();
				} else {
					$filtered = array();

					foreach ( $inputs as $id => $values ) {
						$id = filter_var( $id, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
						$id = preg_replace( '/^' . preg_quote( $prefix, '/' ) . '/', '', $id );

						if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $id ) ) {
							$id = false;
						} else {
							$id = $prefix . $id;
						}

						$access = filter_var(
							isset( $values['access'] ) ? $values['access'] : array(),
							FILTER_SANITIZE_FULL_SPECIAL_CHARS,
							FILTER_REQUIRE_ARRAY
						);
						$key    = filter_var(
							isset( $values['key'] ) ? $values['key'] : false,
							FILTER_SANITIZE_FULL_SPECIAL_CHARS
						);

						if ( ! $id || empty( $access ) || ! $key ) {
							$filtered = new stdClass();

							break;
						}

						$filtered[ $id ] = array(
							'key'    => $key,
							'access' => $access,
						);
					}

					$option_info['value'] = $filtered;
				}
			} elseif ( 'webhooks' === $option_info['condition'] ) {
				$inputs = json_decode( $option_info['value'], true );

				if ( empty( $option_info['value'] ) || json_last_error() ) {
					$option_info['value'] = (object) array();
				} else {
					$filtered = array();

					foreach ( $inputs as $index => $values ) {
						$check_url       = base64_decode( str_replace( '|', '/', $index ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
						$url             = filter_var(
							isset( $values['url'] ) ? $values['url'] : false,
							FILTER_SANITIZE_URL
						);
						$events          = filter_var(
							isset( $values['events'] ) ? $values['events'] : array(),
							FILTER_SANITIZE_FULL_SPECIAL_CHARS,
							FILTER_REQUIRE_ARRAY
						);
						$secret          = filter_var(
							isset( $values['secret'] ) ? $values['secret'] : false,
							FILTER_SANITIZE_FULL_SPECIAL_CHARS
						);
						$license_api_key = filter_var(
							isset( $values['licenseAPIKey'] ) ? $values['licenseAPIKey'] : false,
							FILTER_SANITIZE_FULL_SPECIAL_CHARS
						);

						if ( ! $url || $check_url !== $url || empty( $events ) || ! $secret ) {
							$filtered = (object) array();

							break;
						}

						$filtered[ $index ] = array(
							'url'           => $url,
							'secret'        => $secret,
							'events'        => $events,
							'licenseAPIKey' => 'UPDATEPULSE_L_' === $license_api_key ? '' : $license_api_key,
						);
					}

					$option_info['value'] = $filtered;
				}
			}

			/**
			 * Filter whether an API option should be updated.
			 *
			 * @param bool $condition Whether the condition for updating the option is met
			 * @param string $option_name The name of the option
			 * @param array $option_info Information about the option
			 * @param array $options All submitted options
			 * @return bool Whether the option should be updated
			 * @since 1.0.0
			 */
			$condition = apply_filters(
				'upserv_api_option_update',
				$condition,
				$option_name,
				$option_info,
				$options
			);

			if ( $condition ) {
				/**
				 * Filter the value of an API option before it is saved.
				 *
				 * @param mixed $value The value to save
				 * @param string $option_name The name of the option
				 * @param array $option_info Information about the option
				 * @param array $options All submitted options
				 * @return mixed The filtered value to save
				 * @since 1.0.0
				 */
				$to_save[ $option_info['path'] ] = apply_filters(
					'upserv_api_option_save_value',
					$option_info['value'],
					$option_name,
					$option_info,
					$options
				);
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
			$result = $errors;
		}

		/**
		 * Fired after the API options form has been processed.
		 *
		 * @param array $errors Array of errors that occurred during the update process
		 * @since 1.0.0
		 */
		do_action( 'upserv_api_options_updated', $errors );

		return $result;
	}

	/**
	 * Get submitted options
	 *
	 * Retrieve and sanitize form data from API settings form.
	 *
	 * @return array Sanitized form data.
	 * @since 1.0.0
	 */
	protected function get_submitted_options() {
		/**
		 * Filter the submitted API configuration options.
		 *
		 * @param array $config The submitted API configuration options
		 * @return array The filtered configuration options
		 * @since 1.0.0
		 */
		return apply_filters(
			'upserv_submitted_api_config',
			array(
				'upserv_package_private_api_keys'         => array(
					'value'                   => wp_kses_post( filter_input( INPUT_POST, 'upserv_package_private_api_keys' ) ),
					'display_name'            => __( 'Package API Authentication Keys', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid payload', 'updatepulse-server' ),
					'condition'               => 'api-keys',
					'path'                    => 'api/packages/private_api_keys',
				),
				'upserv_package_private_api_ip_whitelist' => array(
					'value'     => wp_kses_post( filter_input( INPUT_POST, 'upserv_package_private_api_ip_whitelist' ) ),
					'condition' => 'ip-list',
					'path'      => 'api/packages/private_api_ip_whitelist',
				),
				'upserv_license_private_api_keys'         => array(
					'value'                   => wp_kses_post( filter_input( INPUT_POST, 'upserv_license_private_api_keys' ) ),
					'display_name'            => __( 'Private API Authentication Key', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid payload', 'updatepulse-server' ),
					'condition'               => 'api-keys',
					'path'                    => 'api/licenses/private_api_keys',
				),
				'upserv_license_private_api_ip_whitelist' => array(
					'value'     => wp_kses_post( filter_input( INPUT_POST, 'upserv_license_private_api_ip_whitelist' ) ),
					'condition' => 'ip-list',
					'path'      => 'api/licenses/private_api_ip_whitelist',
				),
				'upserv_webhooks'                         => array(
					'value'                   => wp_kses_post( filter_input( INPUT_POST, 'upserv_webhooks' ) ),
					'display_name'            => __( 'Webhooks', 'updatepulse-server' ),
					'failure_display_message' => __( 'Not a valid payload', 'updatepulse-server' ),
					'condition'               => 'webhooks',
					'path'                    => 'api/webhooks',
				),
			)
		);
	}
}
