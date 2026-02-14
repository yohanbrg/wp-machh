<?php
/**
 * HTTP client for Machh ingestion API
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Http
 *
 * Handles HTTP requests to the ingestion API
 */
class Machh_Http {

    /**
     * Request timeout in seconds
     */
    const TIMEOUT = 2;

    /**
     * Send pageview to ingestion API
     *
     * @param array $payload Pageview payload.
     * @return array|WP_Error Response array with status_code, or WP_Error on failure.
     */
    public function send_pageview( array $payload ) {
        return $this->send( '/ingest/pageview', $payload );
    }

    /**
     * Send form submission to ingestion API (placeholder for future use)
     *
     * @param array $payload Form submission payload.
     * @return array|WP_Error Response array with status_code, or WP_Error on failure.
     */
    public function send_form_submitted( array $payload ) {
        return $this->send( '/ingest/form-submitted', $payload );
    }

    /**
     * Send click event to ingestion API via unified endpoint
     *
     * @param array $payload Click event payload.
     * @return array|WP_Error Response array with status_code, or WP_Error on failure.
     */
    public function send_click( array $payload ) {
        return $this->send( '/ingest', array(
            'source'     => 'machh-plugin',
            'event_type' => 'button_clicked',
            'payload'    => $payload,
        ) );
    }

    /**
     * Send request to ingestion API
     *
     * @param string $endpoint API endpoint (e.g., '/pageview').
     * @param array  $payload  Request payload.
     * @return array|WP_Error Response array with status_code, or WP_Error on failure.
     */
    private function send( $endpoint, array $payload ) {
        // Get configuration
        $base_url   = Machh_Plugin::get_ingest_base_url();
        $client_key = Machh_Plugin::get_client_key();

        // Validate client key (base URL is now hardcoded)
        if ( empty( $client_key ) ) {
            Machh_Plugin::log( 'Client API key not configured', 'error' );
            return new WP_Error( 'config_missing', 'Client API key not configured' );
        }

        // Build full URL
        $url = $base_url . $endpoint;

        // Prepare request
        $args = array(
            'method'      => 'POST',
            'timeout'     => self::TIMEOUT,
            'blocking'    => true, // We need blocking to get response status
            'headers'     => array(
                'Content-Type' => 'application/json',
                'X-MACHH-KEY'  => $client_key,
            ),
            'body'        => wp_json_encode( $payload ),
            'data_format' => 'body',
        );

        // Disable SSL verification for local .test/.local domains (self-signed certs)
        if ( $this->is_local_domain( $base_url ) ) {
            $args['sslverify'] = false;
        }

        // Log request (in debug mode)
        Machh_Plugin::log( sprintf( 'Sending request to %s: %s', $url, wp_json_encode( $payload ) ), 'info' );

        // Send request
        $response = wp_remote_post( $url, $args );

        // Handle errors
        if ( is_wp_error( $response ) ) {
            Machh_Plugin::log(
                sprintf( 'HTTP request failed: %s', $response->get_error_message() ),
                'error'
            );
            return $response;
        }

        // Get status code
        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        // Log response
        Machh_Plugin::log(
            sprintf( 'Response from %s: status=%d, body=%s', $endpoint, $status_code, $body ),
            'info'
        );

        // Check for non-2xx status codes
        if ( $status_code < 200 || $status_code >= 300 ) {
            Machh_Plugin::log(
                sprintf( 'Ingestion API returned non-success status: %d', $status_code ),
                'warning'
            );
        }

        return array(
            'status_code' => $status_code,
            'body'        => $body,
        );
    }

    /**
     * Check if URL is a local development domain
     *
     * @param string $url URL to check.
     * @return bool
     */
    private function is_local_domain( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            return false;
        }

        // Common local development TLDs
        $local_tlds = array( '.test', '.local', '.localhost', '.dev' );
        foreach ( $local_tlds as $tld ) {
            if ( substr( $host, -strlen( $tld ) ) === $tld ) {
                return true;
            }
        }

        // Also check for localhost and 127.0.0.1
        if ( $host === 'localhost' || strpos( $host, '127.0.0.1' ) === 0 ) {
            return true;
        }

        return false;
    }
}


