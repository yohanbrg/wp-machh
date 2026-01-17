<?php
/**
 * Cookie management for Machh
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Cookies
 *
 * Handles device_id and UTM cookie management
 */
class Machh_Cookies {

    /**
     * Device ID cookie name
     */
    const DEVICE_ID_COOKIE = 'machh_did';

    /**
     * UTM cookie name
     */
    const UTM_COOKIE = 'machh_utm';

    /**
     * Cookie expiry in seconds (365 days)
     */
    const COOKIE_EXPIRY = 365 * 24 * 60 * 60;

    /**
     * UTM parameters to capture
     *
     * @var array
     */
    private $utm_params = array(
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

    /**
     * Set cookies on init
     */
    public function set_cookies() {
        // Don't set cookies in admin area
        if ( is_admin() ) {
            return;
        }

        // Don't set cookies if headers already sent
        if ( headers_sent() ) {
            Machh_Plugin::log( 'Headers already sent, cannot set cookies', 'warning' );
            return;
        }

        // Don't set cookies for AJAX requests
        if ( wp_doing_ajax() ) {
            return;
        }

        $this->set_device_id_cookie();
        $this->set_utm_cookie();
    }

    /**
     * Set device ID cookie
     */
    private function set_device_id_cookie() {
        // Only set if not already present
        if ( isset( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) && ! empty( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) ) {
            return;
        }

        // Generate new device ID
        $device_id = $this->generate_device_id();

        // Set cookie
        $this->set_cookie( self::DEVICE_ID_COOKIE, $device_id );

        // Also set in $_COOKIE for immediate access in same request
        $_COOKIE[ self::DEVICE_ID_COOKIE ] = $device_id;
    }

    /**
     * Set UTM cookie (first-touch only)
     */
    private function set_utm_cookie() {
        // Only capture first-touch - if cookie exists, don't overwrite
        if ( isset( $_COOKIE[ self::UTM_COOKIE ] ) && ! empty( $_COOKIE[ self::UTM_COOKIE ] ) ) {
            return;
        }

        // Collect UTM params from request
        $utm_data = $this->capture_utm_params();

        // Only set cookie if we have at least one UTM parameter
        if ( empty( $utm_data ) ) {
            return;
        }

        // Store as JSON
        $json_data = wp_json_encode( $utm_data );

        // Set cookie
        $this->set_cookie( self::UTM_COOKIE, $json_data );

        // Also set in $_COOKIE for immediate access
        $_COOKIE[ self::UTM_COOKIE ] = $json_data;
    }

    /**
     * Capture UTM parameters from request
     *
     * @return array
     */
    private function capture_utm_params() {
        $utm_data = array();

        foreach ( $this->utm_params as $param ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $utm_data[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
            }
        }

        return $utm_data;
    }

    /**
     * Generate a unique device ID
     *
     * @return string
     */
    private function generate_device_id() {
        return bin2hex( random_bytes( 16 ) );
    }

    /**
     * Set a cookie with proper parameters
     *
     * @param string $name  Cookie name.
     * @param string $value Cookie value.
     */
    private function set_cookie( $name, $value ) {
        $expiry   = time() + self::COOKIE_EXPIRY;
        $path     = '/';
        $domain   = '';
        $secure   = is_ssl();
        $httponly = false;

        // Use modern setcookie with options array (PHP 7.3+)
        if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
            setcookie( $name, $value, array(
                'expires'  => $expiry,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax',
            ) );
        } else {
            // Fallback for older PHP versions
            setcookie( $name, $value, $expiry, $path . '; SameSite=Lax', $domain, $secure, $httponly );
        }
    }

    /**
     * Get device ID from cookie
     *
     * @return string|null
     */
    public function get_device_id() {
        if ( isset( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) && ! empty( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) );
        }
        return null;
    }

    /**
     * Get UTM data from cookie
     *
     * @return array|null
     */
    public function get_utm_data() {
        if ( isset( $_COOKIE[ self::UTM_COOKIE ] ) && ! empty( $_COOKIE[ self::UTM_COOKIE ] ) ) {
            $json = sanitize_text_field( wp_unslash( $_COOKIE[ self::UTM_COOKIE ] ) );
            $data = json_decode( $json, true );

            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }
        }
        return null;
    }
}


