<?php
/**
 * Plugin Name: Machh 
 * Plugin URI: https://machh.io
 * Description: Server-side tracking for Machh ingestion API
 * Version: 1.0.0
 * Author: Machh
 * Author URI: https://machh.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: machh-wp-plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'MACHH_PLUGIN_VERSION', '1.0.0' );
define( 'MACHH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MACHH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MACHH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Ingestion API base URL (hardcoded - change this value for production)
define( 'MACHH_INGEST_BASE_URL', 'https://ingest-machh.test' );

// Load dependencies
require_once MACHH_PLUGIN_DIR . 'includes/class-machh-plugin.php';

/**
 * Initialize the plugin
 */
function machh_init() {
    return Machh_Plugin::get_instance();
}

// Start the plugin
machh_init();

