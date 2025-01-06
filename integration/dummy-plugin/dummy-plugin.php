<?php
/*
Plugin Name: Dummy Plugin
Plugin URI: https://anyape.com/
Description: Empty plugin to demonstrate the UpdatePulse Updater.
Version: 1.5.0
Author: Alexandre Froger
Author URI: https://froger.me/
Icon1x: https://raw.githubusercontent.com/anyape/updatepulse-server/main/integration/assets/icon-128x128.png
Icon2x: https://raw.githubusercontent.com/anyape/updatepulse-server/main/integration/assets/icon-256x256.png
BannerLow: https://raw.githubusercontent.com/anyape/updatepulse-server/main/integration/assets/banner-772x250.png
BannerHigh: https://raw.githubusercontent.com/anyape/updatepulse-server/main/integration/assets/banner-1544x500.png
Require License: yes
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/* ================================================================================================ */
/*                                  UpdatePulse Server                                         */
/* ================================================================================================ */

/**
* WARNING - READ FIRST:
*
* Before deploying the plugin or theme, make sure to change the value of `server` in updatepulse.json
* with the URL of the server where UpdatePulse Server is installed.
*
* Also change $prefix_updater below - replace "prefix" in this variable's name with a unique prefix
*
* If the plugin or theme requires a license, change the header `Require License` to either `yes`, `true`, or `1`
* in the main plugin file or the `style.css` file.
*
* If the plugin or theme uses the license of another plugin or theme, add the header `Licensed With`
* with the slug of the plugin or theme that provides the license in the main plugin file or the `style.css` file.
*
* @see https://github.com/anyape/updatepulse-server/tree/main/integration/dummy-plugin/lib/updatepulse-updater
**/

require_once plugin_dir_path( __FILE__ ) . 'lib/updatepulse-updater/class-updatepulse-updater.php';

/** Enable plugin updates**/
$dummy_plugin_updater = new UpdatePulse_Updater(
	wp_normalize_path( __FILE__ ),
	wp_normalize_path( plugin_dir_path( __FILE__ ) )
);

/* ================================================================================================ */

function dummy_plugin_run() {}
add_action( 'plugins_loaded', 'dummy_plugin_run', 10, 0 );
