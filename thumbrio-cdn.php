<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://freshflesh.fr
 * @since             1.0.0
 * @package           Thumbrio_Cdn
 *
 * @wordpress-plugin
 * Plugin Name:       Thumbrio CDN
 * Plugin URI:        http://freshflesh.fr
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Thomas Charbit
 * Author URI:        http://freshflesh.fr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       thumbrio-cdn
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-thumbrio-cdn-activator.php
 */
function activate_thumbrio_cdn() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-thumbrio-cdn-activator.php';
	Thumbrio_Cdn_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-thumbrio-cdn-deactivator.php
 */
function deactivate_thumbrio_cdn() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-thumbrio-cdn-deactivator.php';
	Thumbrio_Cdn_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_thumbrio_cdn' );
register_deactivation_hook( __FILE__, 'deactivate_thumbrio_cdn' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-thumbrio-cdn.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_thumbrio_cdn() {

	$plugin = new Thumbrio_Cdn();
	$plugin->run();

}
run_thumbrio_cdn();
