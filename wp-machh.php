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
 * Text Domain: wp-machh
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
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

// ============================================================================
// AUTO-UPDATES FROM GITHUB
// ============================================================================
require_once MACHH_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$machh_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/yohanbrg/wp-machh/', // ⚠️ REMPLACEZ PAR VOTRE URL GITHUB
    __FILE__,
    'wp-machh'
);

// Utiliser les GitHub Releases pour les mises à jour
$machh_update_checker->getVcsApi()->enableReleaseAssets();

// Si votre repo est privé, décommentez et ajoutez votre token GitHub :
// $machh_update_checker->setAuthentication('ghp_votre_token_github');

// ============================================================================

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

