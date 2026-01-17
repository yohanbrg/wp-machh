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

        // Settings section
        add_settings_section(
            'machh_main_section',
            __( 'Machh Configuration', 'machh-wp-plugin' ),
            array( $this, 'render_section_description' ),
            'machh-settings'
        );

        // Fields
        add_settings_field(
            'machh_enabled',
            __( 'Enable Tracking', 'machh-wp-plugin' ),
            array( $this, 'render_enabled_field' ),
            'machh-settings',
            'machh_main_section'
        );

        add_settings_field(
            'machh_client_key',
            __( 'Client API Key', 'machh-wp-plugin' ),
            array( $this, 'render_client_key_field' ),
            'machh-settings',
            'machh_main_section'
        );
    }

    /**
     * Render settings section description
     */
    public function render_section_description() {
        echo '<p>' . esc_html__( 'Enter your Client API Key to enable Machh tracking.', 'machh-wp-plugin' ) . '</p>';
    }

    /**
     * Render enabled checkbox field
     */
    public function render_enabled_field() {
        $enabled = get_option( 'machh_enabled', false );
        ?>
        <label>
            <input type="checkbox" name="machh_enabled" value="1" <?php checked( $enabled, true ); ?> />
            <?php esc_html_e( 'Enable Machh pageview tracking', 'machh-wp-plugin' ); ?>
        </label>
        <?php
    }

    /**
     * Render client key field
     */
    public function render_client_key_field() {
        $key = get_option( 'machh_client_key', '' );
        ?>
        <input type="text" name="machh_client_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" placeholder="your-api-key-here" />
        <p class="description"><?php esc_html_e( 'Your unique Client API Key provided by Machh.', 'machh-wp-plugin' ); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'machh_settings_group' );
                do_settings_sections( 'machh-settings' );
                submit_button();
                ?>
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


