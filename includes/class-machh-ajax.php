<?php
/**
 * AJAX handlers for Machh
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Ajax
 *
 * Handles admin-ajax requests for tracking
 */
class Machh_Ajax {

    /**
     * Cookies handler
     *
     * @var Machh_Cookies
     */
    private $cookies;

    /**
     * HTTP handler
     *
     * @var Machh_Http
     */
    private $http;

    /**
     * Paths to ignore
     *
     * @var array
     */
    private $ignored_paths = array(
        '/wp-admin',
        '/wp-json',
        '/sitemap.xml',
        '/robots.txt',
        '/favicon.ico',
        '/wp-login.php',
        '/wp-cron.php',
    );

    /**
     * Constructor
     *
     * @param Machh_Cookies $cookies Cookies handler.
     * @param Machh_Http    $http    HTTP handler.
     */
    public function __construct( Machh_Cookies $cookies, Machh_Http $http ) {
        $this->cookies = $cookies;
        $this->http    = $http;

        // Register AJAX handlers
        add_action( 'wp_ajax_machh_pageview', array( $this, 'handle_pageview' ) );
        add_action( 'wp_ajax_nopriv_machh_pageview', array( $this, 'handle_pageview' ) );
    }

    /**
     * Handle pageview tracking request
     */
    public function handle_pageview() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'machh_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }

        // Check if tracking is enabled
        if ( ! Machh_Plugin::is_enabled() ) {
            wp_send_json_error( array( 'message' => 'Tracking disabled' ), 400 );
        }

        // Ignore admin users
        if ( current_user_can( 'manage_options' ) ) {
            wp_send_json_success( array( 'ok' => true, 'skipped' => 'admin_user' ) );
        }

        // Get and validate URL
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( empty( $url ) ) {
            wp_send_json_error( array( 'message' => 'URL required' ), 400 );
        }

        // Check if URL should be ignored
        if ( $this->should_ignore_url( $url ) ) {
            wp_send_json_success( array( 'ok' => true, 'skipped' => 'ignored_path' ) );
        }

        // Get referrer
        $referrer = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';

        // Build payload
        $payload = $this->build_pageview_payload( $url, $referrer );

        // Forward to ingestion API
        $result = $this->http->send_pageview( $payload );

        if ( is_wp_error( $result ) ) {
            Machh_Plugin::log( 'Pageview forwarding failed: ' . $result->get_error_message(), 'error' );
            wp_send_json_success( array( 'ok' => false, 'error' => 'forwarding_failed' ) );
        }

        wp_send_json_success( array(
            'ok'     => true,
            'status' => $result['status_code'],
        ) );
    }

    /**
     * Check if URL should be ignored
     *
     * @param string $url URL to check.
     * @return bool
     */
    private function should_ignore_url( $url ) {
        $parsed = wp_parse_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';

        foreach ( $this->ignored_paths as $ignored ) {
            if ( strpos( $path, $ignored ) === 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build pageview payload
     *
     * @param string $url      Page URL.
     * @param string $referrer Referrer URL.
     * @return array
     */
    private function build_pageview_payload( $url, $referrer ) {
        // Get device ID
        $device_id = $this->cookies->get_device_id();
        if ( empty( $device_id ) ) {
            // Generate fallback device ID if cookie not available
            $device_id = bin2hex( random_bytes( 16 ) );
            Machh_Plugin::log( 'Device ID cookie not found, generated fallback', 'warning' );
        }

        // Get site domain (without www)
        $site_domain = $this->get_site_domain();

        // Extract UTM/ad params from the CURRENT URL only (not from cookie!)
        // This ensures each pageview has the correct params for that specific page.
        // Cookie-based first-touch attribution is only used for form submissions.
        $utm_data = $this->extract_utm_from_url( $url );

        // Get user agent
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';

        // Get client IP (best effort)
        $ip = $this->get_client_ip();

        // Build payload matching ingestion API expectations
        $payload = array(
            'device_id'   => $device_id,
            'url'         => $url,
            'referrer'    => $referrer,
            'site_domain' => $site_domain,
            'utm'         => $utm_data,
            'user_agent'  => $user_agent,
            'ip'          => $ip,
            'ts'          => time(),
        );

        return $payload;
    }

    /**
     * Extract UTM and ad click parameters from a URL
     *
     * @param string $url URL to extract params from.
     * @return array|null Array of UTM params or null if none found.
     */
    private function extract_utm_from_url( $url ) {
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['query'] ) ) {
            return null;
        }

        parse_str( $parsed['query'], $query_params );

        $utm_params = array(
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
            'ttclid',
            'wbraid',
            'dclid',
            'twclid',
            'li_fat_id',
        );

        $result = array();
        foreach ( $utm_params as $param ) {
            if ( isset( $query_params[ $param ] ) && ! empty( $query_params[ $param ] ) ) {
                $result[ $param ] = sanitize_text_field( $query_params[ $param ] );
            }
        }

        return ! empty( $result ) ? $result : null;
    }

    /**
     * Get site domain without www prefix
     *
     * @return string
     */
    private function get_site_domain() {
        $host = isset( $_SERVER['HTTP_HOST'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            : wp_parse_url( home_url(), PHP_URL_HOST );

        // Remove www. prefix
        if ( strpos( $host, 'www.' ) === 0 ) {
            $host = substr( $host, 4 );
        }

        return $host;
    }

    /**
     * Get client IP address (best effort)
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy/Load balancer
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Shared internet
            'REMOTE_ADDR',               // Direct connection
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

                // Handle comma-separated list (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }

                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }
}


