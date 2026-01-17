<?php
/**
 * Form Provider Manager
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Form_Provider_Manager
 *
 * Manages form provider registration and initialization.
 */
class Machh_Form_Provider_Manager {

    /**
     * Registered providers
     *
     * @var Machh_Form_Provider[]
     */
    private $providers = array();

    /**
     * HTTP handler
     *
     * @var Machh_Http
     */
    private $http;

    /**
     * Constructor
     *
     * @param Machh_Http $http HTTP handler for API requests.
     */
    public function __construct( Machh_Http $http ) {
        $this->http = $http;
    }

    /**
     * Add a provider to the registry
     *
     * @param Machh_Form_Provider $provider Form provider instance.
     * @return void
     */
    public function add_provider( Machh_Form_Provider $provider ) {
        $this->providers[] = $provider;
    }

    /**
     * Register hooks for all available providers
     *
     * @return void
     */
    public function register_available_providers() {
        foreach ( $this->providers as $provider ) {
            if ( $provider->is_available() ) {
                $provider->register_hooks();
                Machh_Plugin::log(
                    sprintf( 'Form provider registered: %s', get_class( $provider ) ),
                    'info'
                );
            }
        }
    }

    /**
     * Get HTTP handler
     *
     * @return Machh_Http
     */
    public function get_http() {
        return $this->http;
    }

    /**
     * Get count of registered providers
     *
     * @return int
     */
    public function get_provider_count() {
        return count( $this->providers );
    }

    /**
     * Get count of available (active) providers
     *
     * @return int
     */
    public function get_available_provider_count() {
        $count = 0;
        foreach ( $this->providers as $provider ) {
            if ( $provider->is_available() ) {
                $count++;
            }
        }
        return $count;
    }
}


