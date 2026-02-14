<?php
/**
 * Plugin Name: Machh 
 * Plugin URI: https://machh.io
 * Description: Server-side tracking for Machh ingestion API
 * Version: 1.2.0
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
define( 'MACHH_PLUGIN_VERSION', '1.2.0' );
define( 'MACHH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MACHH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MACHH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Ingestion API base URL (hardcoded - change this value for production)
define( 'MACHH_INGEST_BASE_URL', 'https://ingest.machh.io' );

// ============================================================================
// AUTO-UPDATES FROM GITHUB
// ============================================================================
require_once MACHH_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$machh_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/yohanbrg/wp-machh/',
    __FILE__,
    'wp-machh'
);

// Utiliser les GitHub Releases pour les mises à jour
$machh_update_checker->getVcsApi()->enableReleaseAssets();

// Appliquer le token GitHub depuis les options (si configuré)
add_action( 'init', function() use ( $machh_update_checker ) {
    $github_token = get_option( 'machh_github_token', '' );
    if ( ! empty( $github_token ) ) {
        $machh_update_checker->setAuthentication( $github_token );
    }
}, 1 );

// Détecter les erreurs d'API GitHub (rate limiting 403)
add_action( 'puc_api_error', function( $error, $response = null, $url = null, $slug = null ) {
    // Vérifier que c'est notre plugin
    if ( $slug !== 'wp-machh' ) {
        return;
    }
    
    // Récupérer le code HTTP
    $http_code = wp_remote_retrieve_response_code( $response );
    
    // Si c'est une erreur 403 (rate limiting)
    if ( $http_code === 403 ) {
        // Stocker l'erreur en transient pour afficher un admin notice
        set_transient( 'machh_github_api_error', true, HOUR_IN_SECONDS );
    }
}, 10, 4 );

// Afficher un avertissement si erreur GitHub 403
add_action( 'admin_notices', function() {
    // Ne pas afficher si un token est déjà configuré
    $github_token = get_option( 'machh_github_token', '' );
    if ( ! empty( $github_token ) ) {
        delete_transient( 'machh_github_api_error' );
        return;
    }
    
    if ( get_transient( 'machh_github_api_error' ) ) {
        $settings_url = admin_url( 'options-general.php?page=machh-settings' );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Machh :</strong> <?php esc_html_e( 'Impossible de vérifier les mises à jour (GitHub API rate limit).', 'machh-wp-plugin' ); ?>
            <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Ajouter un token GitHub dans les réglages', 'machh-wp-plugin' ); ?> →</a></p>
        </div>
        <?php
    }
});

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

