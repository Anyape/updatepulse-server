<?php
/**
* Run as little as possible of the WordPress core with UpdatePulse Server actions and filters.
* Effect:
* - keep only a selection of plugins (@see upserv_mu_always_active_plugins filter below)
* - prevent inclusion of themes functions.php (parent and child)
* - remove all core actions and filters that haven't been fired yet
*
* Place this file in a wp-content/mu-plugin folder and it will be loaded automatically.
*
* Use the following filters in your own MU plugin for customization purposes:
* - @see upserv_mu_always_active_plugins - filter the plugins to be kept active during UpdatePulse Server API calls
* - @see upserv_mu_doing_api_request - determine if the current request is an UpdatePulse Server API call
* - @see upserv_mu_require - filter the files to be required before UpdatePulse Server API calls are handled
*
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function upserv_muplugins_loaded() {
	$upserv_always_active_plugins = apply_filters(
		'upserv_mu_always_active_plugins',
		array(
			// Add your own MU plugin and subscribe to this filter to add your plugin IDs here
			// to keep them active during update checks.
			// 'my-plugin-slug/my-plugin-file.php',
			// 'my-other-plugin-slug/my-other-plugin-file.php',
			'updatepulse-server/updatepulse-server.php',
		)
	);

	$host      = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
	$url       = 'https://' . $host . $_SERVER['REQUEST_URI'];
	$path      = str_replace( trailingslashit( home_url() ), '', $url );
	$frags     = explode( '/', $path );
	$doing_api = preg_match( '/^updatepulse-server-(.*?)-(api|nonce|token)$/', $frags[0] );

	if ( apply_filters( 'upserv_mu_doing_api_request', $doing_api ) ) {
		$hooks = array(
			'registered_taxonomy',
			'wp_register_sidebar_widget',
			'registered_post_type',
			'auth_cookie_malformed',
			'auth_cookie_valid',
			'widgets_init',
			'wp_default_scripts',
			'option_siteurl',
			'option_home',
			'option_active_plugins',
			'query',
			'option_blog_charset',
			'plugins_loaded',
			'sanitize_comment_cookies',
			'template_directory',
			'stylesheet_directory',
			'determine_current_user',
			'set_current_user',
			'user_has_cap',
			'init',
			'option_category_base',
			'option_tag_base',
			'heartbeat_settings',
			'locale',
			'wp_loaded',
			'query_vars',
			'request',
			'parse_request',
			'shutdown',
		);

		foreach ( $hooks as $hook ) {
			remove_all_filters( $hook );
		}

		add_filter(
			'option_active_plugins',
			function ( $plugins ) use ( $upserv_always_active_plugins ) {

				foreach ( $plugins as $key => $plugin ) {

					if ( ! in_array( $plugin, $upserv_always_active_plugins, true ) ) {
						unset( $plugins[ $key ] );
					}
				}

				return $plugins;
			},
			PHP_INT_MAX - 100,
			1
		);

		add_filter( 'template_directory', fn() => __DIR__, PHP_INT_MAX - 100, 0 );
		add_filter( 'stylesheet_directory', fn() => __DIR__, PHP_INT_MAX - 100, 0 );
		add_filter( 'enable_loading_advanced_cache_dropin', fn() => false, PHP_INT_MAX - 100, 0 );
	}

	do_action( 'upserv_mu_endpoint_optimizer_ready' );
}
add_action( 'muplugins_loaded', 'upserv_muplugins_loaded', 0 );
