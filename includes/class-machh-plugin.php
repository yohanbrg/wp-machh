<?php
/**
 * Main plugin bootstrap class
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load dependencies
require_once MACHH_PLUGIN_DIR . 'includes/class-machh-cookies.php';
require_once MACHH_PLUGIN_DIR . 'includes/class-machh-ajax.php';
require_once MACHH_PLUGIN_DIR . 'includes/class-machh-http.php';
require_once MACHH_PLUGIN_DIR . 'includes/class-machh-context.php';

// Form tracking
require_once MACHH_PLUGIN_DIR . 'includes/forms/interface-machh-form-provider.php';
require_once MACHH_PLUGIN_DIR . 'includes/forms/class-machh-form-provider-manager.php';
require_once MACHH_PLUGIN_DIR . 'includes/forms/providers/class-machh-cf7-provider.php';

/**
 * Class Machh_Plugin
 */
class Machh_Plugin {

    /**
     * Singleton instance
     *
     * @var Machh_Plugin
     */
    private static $instance = null;

    /**
     * Cookies handler
     *
     * @var Machh_Cookies
     */
    public $cookies;

    /**
     * Ajax handler
     *
     * @var Machh_Ajax
     */
    public $ajax;

    /**
     * HTTP handler
     *
     * @var Machh_Http
     */
    public $http;

    /**
     * Form provider manager
     *
     * @var Machh_Form_Provider_Manager
     */
    public $form_manager;

    /**
     * Get singleton instance
     *
     * @return Machh_Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_classes();
        $this->init_hooks();
    }

    /**
     * Initialize plugin classes
     */
    private function init_classes() {
        $this->cookies = new Machh_Cookies();
        $this->http    = new Machh_Http();
        $this->ajax    = new Machh_Ajax( $this->cookies, $this->http );

        // Initialize form tracking (only when enabled and configured)
        $this->init_form_tracking();
    }

    /**
     * Initialize form tracking providers
     */
    private function init_form_tracking() {
        // Only initialize if tracking is enabled and client key is set
        $enabled    = get_option( 'machh_enabled', false );
        $client_key = get_option( 'machh_client_key', '' );

        if ( ! $enabled || empty( $client_key ) ) {
            return;
        }

        // Create form provider manager
        $this->form_manager = new Machh_Form_Provider_Manager( $this->http );

        // Register providers
        $this->form_manager->add_provider( new Machh_CF7_Provider( $this->http ) );

        // Hook registration needs to happen after plugins_loaded to ensure CF7 is loaded
        add_action( 'plugins_loaded', array( $this, 'register_form_providers' ), 20 );
    }

    /**
     * Register available form providers (called on plugins_loaded)
     */
    public function register_form_providers() {
        if ( $this->form_manager ) {
            $this->form_manager->register_available_providers();
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Frontend scripts (only on public pages)
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Set cookies early (before headers sent)
        add_action( 'init', array( $this->cookies, 'set_cookies' ), 1 );
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Machh Settings', 'machh-wp-plugin' ),
            __( 'Machh', 'machh-wp-plugin' ),
            'manage_options',
            'machh-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings
        register_setting( 'machh_settings_group', 'machh_enabled', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ) );

        register_setting( 'machh_settings_group', 'machh_client_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'machh_settings_group', 'machh_github_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
    }

    /**
     * Get tracking status info
     */
    private function get_status_info() {
        $enabled    = get_option( 'machh_enabled', false );
        $client_key = get_option( 'machh_client_key', '' );
        
        if ( ! $enabled ) {
            return array(
                'status'  => 'inactive',
                'color'   => '#d63638',
                'bg'      => '#fcf0f1',
                'icon'    => '○',
                'label'   => __( 'Tracking disabled', 'machh-wp-plugin' ),
            );
        }
        
        if ( empty( $client_key ) ) {
            return array(
                'status'  => 'incomplete',
                'color'   => '#dba617',
                'bg'      => '#fcf9e8',
                'icon'    => '◐',
                'label'   => __( 'API Key required', 'machh-wp-plugin' ),
            );
        }
        
        return array(
            'status'  => 'active',
            'color'   => '#00a32a',
            'bg'      => '#edfaef',
            'icon'    => '●',
            'label'   => __( 'Tracking active', 'machh-wp-plugin' ),
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $enabled      = get_option( 'machh_enabled', false );
        $client_key   = get_option( 'machh_client_key', '' );
        $github_token = get_option( 'machh_github_token', '' );
        $status       = $this->get_status_info();
        ?>
        <style>
            .machh-settings-wrap {
                max-width: 800px;
                margin: 20px 0;
            }
            .machh-header {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 24px;
            }
            .machh-logo {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 20px;
            }
            .machh-header-text h1 {
                margin: 0 0 4px 0;
                font-size: 24px;
                font-weight: 600;
            }
            .machh-header-text .version {
                color: #757575;
                font-size: 13px;
            }
            .machh-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
            }
            .machh-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 24px;
                margin-bottom: 20px;
            }
            .machh-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f0f0f0;
            }
            .machh-card-title {
                font-size: 15px;
                font-weight: 600;
                margin: 0;
                color: #1d2327;
            }
            .machh-card-description {
                color: #757575;
                font-size: 13px;
                margin: 4px 0 0 0;
            }
            .machh-field {
                margin-bottom: 20px;
            }
            .machh-field:last-child {
                margin-bottom: 0;
            }
            .machh-field-label {
                display: block;
                font-weight: 500;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .machh-field-description {
                color: #757575;
                font-size: 13px;
                margin-top: 6px;
            }
            .machh-toggle-wrapper {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .machh-toggle {
                position: relative;
                width: 44px;
                height: 24px;
            }
            .machh-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .machh-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .2s;
                border-radius: 24px;
            }
            .machh-toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .2s;
                border-radius: 50%;
            }
            .machh-toggle input:checked + .machh-toggle-slider {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .machh-toggle input:checked + .machh-toggle-slider:before {
                transform: translateX(20px);
            }
            .machh-toggle-label {
                font-size: 14px;
                color: #1d2327;
            }
            .machh-input {
                width: 100%;
                max-width: 400px;
                padding: 10px 12px;
                border: 1px solid #d0d0d0;
                border-radius: 6px;
                font-size: 14px;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .machh-input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.15);
            }
            .machh-input::placeholder {
                color: #a0a0a0;
            }
            .machh-advanced-toggle {
                display: flex;
                align-items: center;
                gap: 8px;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 14px;
                color: #667eea;
                padding: 0;
            }
            .machh-advanced-toggle:hover {
                text-decoration: underline;
            }
            .machh-advanced-content {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #f0f0f0;
            }
            .machh-token-status {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 13px;
                margin-top: 8px;
            }
            .machh-token-status.configured {
                color: #00a32a;
            }
            .machh-link {
                color: #667eea;
                text-decoration: none;
            }
            .machh-link:hover {
                text-decoration: underline;
            }
            .machh-submit-wrapper {
                margin-top: 24px;
            }
            .machh-submit-wrapper .button-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                padding: 8px 24px;
                height: auto;
                font-size: 14px;
                border-radius: 6px;
            }
            .machh-submit-wrapper .button-primary:hover {
                opacity: 0.9;
            }
        </style>
        
        <div class="wrap machh-settings-wrap">
            <div class="machh-header">
                <div class="machh-logo">M</div>
                <div class="machh-header-text">
                    <h1><?php esc_html_e( 'Machh', 'machh-wp-plugin' ); ?></h1>
                    <span class="version">v<?php echo esc_html( MACHH_PLUGIN_VERSION ); ?></span>
                </div>
                <div style="margin-left: auto;">
                    <span class="machh-status-badge" style="background: <?php echo esc_attr( $status['bg'] ); ?>; color: <?php echo esc_attr( $status['color'] ); ?>;">
                        <?php echo esc_html( $status['icon'] ); ?>
                        <?php echo esc_html( $status['label'] ); ?>
                    </span>
                </div>
            </div>
            
            <form action="options.php" method="post">
                <?php settings_fields( 'machh_settings_group' ); ?>
                
                <!-- Main Settings Card -->
                <div class="machh-card">
                    <div class="machh-card-header">
                        <div>
                            <h2 class="machh-card-title"><?php esc_html_e( 'Configuration', 'machh-wp-plugin' ); ?></h2>
                            <p class="machh-card-description"><?php esc_html_e( 'Connect your website to the Machh platform.', 'machh-wp-plugin' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="machh-field">
                        <div class="machh-toggle-wrapper">
                            <label class="machh-toggle">
                                <input type="checkbox" name="machh_enabled" value="1" <?php checked( $enabled, true ); ?> />
                                <span class="machh-toggle-slider"></span>
                            </label>
                            <span class="machh-toggle-label"><?php esc_html_e( 'Enable Machh tracking', 'machh-wp-plugin' ); ?></span>
                        </div>
                        <p class="machh-field-description"><?php esc_html_e( 'Activate tracking for page views, form submissions, and other events.', 'machh-wp-plugin' ); ?></p>
                    </div>
                    
                    <div class="machh-field">
                        <label class="machh-field-label" for="machh_client_key"><?php esc_html_e( 'API Key', 'machh-wp-plugin' ); ?></label>
                        <input type="text" id="machh_client_key" name="machh_client_key" value="<?php echo esc_attr( $client_key ); ?>" class="machh-input" placeholder="machh_xxxxxxxxxxxxxxxx" autocomplete="off" />
                        <p class="machh-field-description"><?php esc_html_e( 'Your unique API key provided by Machh.', 'machh-wp-plugin' ); ?></p>
                    </div>
                </div>
                
                <!-- Advanced Settings Card -->
                <div class="machh-card">
                    <details>
                        <summary class="machh-advanced-toggle">
                            <span>⚙️</span>
                            <?php esc_html_e( 'Advanced settings', 'machh-wp-plugin' ); ?>
                        </summary>
                        <div class="machh-advanced-content">
                            <div class="machh-field">
                                <label class="machh-field-label" for="machh_github_token"><?php esc_html_e( 'GitHub Token', 'machh-wp-plugin' ); ?></label>
                                <input type="password" id="machh_github_token" name="machh_github_token" value="<?php echo esc_attr( $github_token ); ?>" class="machh-input" placeholder="ghp_xxxxxxxxxxxx" autocomplete="off" />
                                <?php if ( ! empty( $github_token ) ) : ?>
                                    <div class="machh-token-status configured">
                                        <span>✓</span>
                                        <?php esc_html_e( 'Token configured', 'machh-wp-plugin' ); ?>
                                    </div>
                                <?php endif; ?>
                                <p class="machh-field-description">
                                    <?php esc_html_e( 'Optional: Resolves update check errors on shared hosting (GitHub API rate limit).', 'machh-wp-plugin' ); ?>
                                    <br>
                                    <a href="https://github.com/settings/tokens" target="_blank" class="machh-link"><?php esc_html_e( 'Create a token on GitHub', 'machh-wp-plugin' ); ?> →</a>
                                </p>
                            </div>
                        </div>
                    </details>
                </div>
                
                <div class="machh-submit-wrapper">
                    <?php submit_button( __( 'Save changes', 'machh-wp-plugin' ), 'primary', 'submit', false ); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Only on public pages, not admin
        if ( is_admin() ) {
            return;
        }

        // Check if tracking is enabled
        $enabled = get_option( 'machh_enabled', false );
        if ( ! $enabled ) {
            return;
        }

        // Enqueue collector script
        wp_enqueue_script(
            'machh-collector',
            MACHH_PLUGIN_URL . 'assets/machh-collector.js',
            array(), // No dependencies (no jQuery)
            MACHH_PLUGIN_VERSION,
            true // Load in footer
        );

        // Localize script data
        wp_localize_script( 'machh-collector', 'machhData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'machh_nonce' ),
            'enabled'  => (bool) $enabled,
            'isAdmin'  => current_user_can( 'manage_options' ),
        ) );
    }

    /**
     * Check if tracking is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option( 'machh_enabled', false );
    }

    /**
     * Get ingestion API base URL (hardcoded constant)
     *
     * @return string
     */
    public static function get_ingest_base_url() {
        return rtrim( MACHH_INGEST_BASE_URL, '/' );
    }

    /**
     * Get client API key
     *
     * @return string
     */
    public static function get_client_key() {
        return get_option( 'machh_client_key', '' );
    }

    /**
     * Log message if WP_DEBUG is enabled
     *
     * @param string $message Message to log.
     * @param string $level   Log level (info, warning, error).
     */
    public static function log( $message, $level = 'info' ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        $prefix = '[Machh][' . strtoupper( $level ) . '] ';
        error_log( $prefix . $message );
    }
}


