<?php
/**
 * Form Provider Interface
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface Machh_Form_Provider
 *
 * All form provider implementations must implement this interface.
 */
interface Machh_Form_Provider {

    /**
     * Check if the form plugin is available and active
     *
     * @return bool True if the form plugin is installed and active.
     */
    public function is_available();

    /**
     * Register WordPress hooks for form submission tracking
     *
     * This method should add actions/filters to capture form submissions.
     *
     * @return void
     */
    public function register_hooks();
}


