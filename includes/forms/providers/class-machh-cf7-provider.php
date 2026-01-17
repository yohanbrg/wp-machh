<?php
/**
 * Contact Form 7 Provider
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_CF7_Provider
 *
 * Handles Contact Form 7 form submission tracking.
 */
class Machh_CF7_Provider implements Machh_Form_Provider {

    /**
     * HTTP handler
     *
     * @var Machh_Http
     */
    private $http;

    /**
     * Fields to exclude from raw_fields
     *
     * @var array
     */
    private $excluded_fields = array(
        '_wpcf7',
        '_wpcf7_version',
        '_wpcf7_locale',
        '_wpcf7_unit_tag',
        '_wpcf7_container_post',
        '_wpcf7_posted_data_hash',
        '_wpcf7_recaptcha_response',
        'g-recaptcha-response',
        'captcha',
        '_wpnonce',
    );

    /**
     * Field mapping for common fields
     *
     * @var array
     */
    private $field_mappings = array(
        'email'   => array( 'email', 'your-email', 'e-mail', 'mail', 'user-email', 'contact-email' ),
        'name'    => array( 'name', 'your-name', 'nom', 'full-name', 'fullname', 'user-name' ),
        'phone'   => array( 'phone', 'tel', 'your-tel', 'telephone', 'your-phone', 'mobile' ),
        'message' => array( 'message', 'your-message', 'msg', 'textarea', 'comment', 'your-comment' ),
    );

    /**
     * Constructor
     *
     * @param Machh_Http $http HTTP handler.
     */
    public function __construct( Machh_Http $http ) {
        $this->http = $http;
    }

    /**
     * Check if Contact Form 7 is available
     *
     * @return bool
     */
    public function is_available() {
        return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
    }

    /**
     * Register CF7 hooks
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'wpcf7_mail_sent', array( $this, 'on_mail_sent' ), 10, 1 );
    }

    /**
     * Handle CF7 mail sent event
     *
     * @param WPCF7_ContactForm $contact_form The contact form instance.
     * @return void
     */
    public function on_mail_sent( $contact_form ) {
        // Get submission instance
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            Machh_Plugin::log( 'CF7: Could not get submission instance', 'warning' );
            return;
        }

        // Get posted data
        $posted = $submission->get_posted_data();
        if ( empty( $posted ) || ! is_array( $posted ) ) {
            Machh_Plugin::log( 'CF7: No posted data available', 'warning' );
            return;
        }

        // Build payload
        $payload = $this->build_payload( $contact_form, $submission, $posted );

        // Send to ingestion API
        $result = $this->http->send_form_submitted( $payload );

        if ( is_wp_error( $result ) ) {
            Machh_Plugin::log(
                sprintf( 'CF7 form submission forwarding failed: %s', $result->get_error_message() ),
                'error'
            );
        } else {
            Machh_Plugin::log(
                sprintf( 'CF7 form submission forwarded: form_id=%d, status=%d', $payload['form_id'], $result['status_code'] ),
                'info'
            );
        }
    }

    /**
     * Build form submission payload
     *
     * @param WPCF7_ContactForm  $contact_form The contact form instance.
     * @param WPCF7_Submission   $submission   The submission instance.
     * @param array              $posted       Posted data.
     * @return array
     */
    private function build_payload( $contact_form, $submission, array $posted ) {
        // Extract form info
        $form_id   = (int) $contact_form->id();
        $form_name = (string) $contact_form->title();

        // Build raw_fields (filtered)
        $raw_fields = $this->build_raw_fields( $posted );

        // Map common fields
        $mapped_fields = $this->map_common_fields( $posted );

        // Build payload
        $payload = array(
            'device_id'   => Machh_Context::get_device_id(),
            'url'         => Machh_Context::get_current_url_best_effort( $submission ),
            'referrer'    => Machh_Context::get_referrer(),
            'site_domain' => Machh_Context::get_site_domain(),
            'form_id'     => $form_id,
            'form_name'   => $form_name,
            'email'       => $mapped_fields['email'],
            'name'        => $mapped_fields['name'],
            'phone'       => $mapped_fields['phone'],
            'message'     => $mapped_fields['message'],
            'utm'         => Machh_Context::get_utm(),
            'user_agent'  => Machh_Context::get_user_agent(),
            'ip'          => Machh_Context::get_ip(),
            'ts'          => Machh_Context::now_ts(),
            'raw_fields'  => $raw_fields,
        );

        return $payload;
    }

    /**
     * Build raw_fields excluding internal CF7 fields
     *
     * @param array $posted Posted data.
     * @return array
     */
    private function build_raw_fields( array $posted ) {
        $raw_fields = array();

        foreach ( $posted as $key => $value ) {
            // Skip excluded fields
            if ( $this->is_excluded_field( $key ) ) {
                continue;
            }

            // Skip empty values
            if ( empty( $value ) && $value !== '0' ) {
                continue;
            }

            // Convert arrays to comma-separated string
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_filter( $value ) );
            }

            // Skip if still empty after conversion
            if ( empty( $value ) && $value !== '0' ) {
                continue;
            }

            $raw_fields[ $key ] = sanitize_text_field( $value );
        }

        return $raw_fields;
    }

    /**
     * Check if field should be excluded
     *
     * @param string $key Field key.
     * @return bool
     */
    private function is_excluded_field( $key ) {
        // Check exact matches
        if ( in_array( $key, $this->excluded_fields, true ) ) {
            return true;
        }

        // Check prefix matches (fields starting with _wpcf7)
        if ( strpos( $key, '_wpcf7' ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Map common fields using field mapping
     *
     * @param array $posted Posted data.
     * @return array
     */
    private function map_common_fields( array $posted ) {
        $mapped = array(
            'email'   => '',
            'name'    => '',
            'phone'   => '',
            'message' => '',
        );

        foreach ( $mapped as $field_type => $default ) {
            $mapped[ $field_type ] = $this->find_field_value( $posted, $this->field_mappings[ $field_type ] );
        }

        return $mapped;
    }

    /**
     * Find first non-empty value from candidate keys
     *
     * @param array $posted     Posted data.
     * @param array $candidates Candidate field keys to check.
     * @return string
     */
    private function find_field_value( array $posted, array $candidates ) {
        foreach ( $candidates as $key ) {
            if ( isset( $posted[ $key ] ) && ! empty( $posted[ $key ] ) ) {
                $value = $posted[ $key ];

                // Convert array to string if needed
                if ( is_array( $value ) ) {
                    $value = implode( ', ', array_filter( $value ) );
                }

                if ( ! empty( $value ) ) {
                    return sanitize_text_field( $value );
                }
            }
        }

        return '';
    }
}


