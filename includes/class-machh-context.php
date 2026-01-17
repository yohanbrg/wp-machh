<?php
/**
 * Shared context helper for Machh tracking
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Context
 *
 * Provides shared context data for all tracking events (pageview, form submission, etc.)
 */
class Machh_Context {

    /**
     * Device ID cookie name
     */
    const DEVICE_ID_COOKIE = 'machh_did';

    /**
     * UTM cookie name
     */
    const UTM_COOKIE = 'machh_utm';

    /**
     * Get device ID from cookie
     *
     * @return string
     */
    public static function get_device_id() {
        if ( isset( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) && ! empty( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE[ self::DEVICE_ID_COOKIE ] ) );
        }

        // Generate fallback device ID if cookie not available
        Machh_Plugin::log( 'Device ID cookie not found, generating fallback', 'warning' );
        return bin2hex( random_bytes( 16 ) );
    }

    /**
     * Get UTM data from cookie
     *
     * @return array|null
     */
    public static function get_utm() {
        if ( isset( $_COOKIE[ self::UTM_COOKIE ] ) && ! empty( $_COOKIE[ self::UTM_COOKIE ] ) ) {
            $json = sanitize_text_field( wp_unslash( $_COOKIE[ self::UTM_COOKIE ] ) );
            $data = json_decode( $json, true );

            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Get site domain without www prefix
     *
     * @return string
     */
    public static function get_site_domain() {
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
     * Get user agent string
     *
     * @return string
     */
    public static function get_user_agent() {
        return isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';
    }

    /**
     * Get client IP address (best effort)
     *
     * @return string
     */
    public static function get_ip() {
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

    /**
     * Get current timestamp
     *
     * @return int
     */
    public static function now_ts() {
        return time();
    }

    /**
     * Get referrer URL
     *
     * @return string
     */
    public static function get_referrer() {
        // Try WordPress function first
        $referrer = wp_get_referer();
        if ( ! empty( $referrer ) ) {
            return esc_url_raw( $referrer );
        }

        // Fallback to HTTP_REFERER
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        }

        return '';
    }

    /**
     * Get current URL (best effort, useful for form submissions)
     *
     * @param object|null $submission Optional CF7 submission object for meta extraction.
     * @return string
     */
    public static function get_current_url_best_effort( $submission = null ) {
        // Try to get URL from CF7 submission meta
        if ( $submission !== null && method_exists( $submission, 'get_meta' ) ) {
            $url = $submission->get_meta( 'url' );
            if ( ! empty( $url ) ) {
                return esc_url_raw( $url );
            }
        }

        // Try referrer
        $referrer = self::get_referrer();
        if ( ! empty( $referrer ) ) {
            return $referrer;
        }

        // Fallback to home URL
        return home_url( '/' );
    }
}


