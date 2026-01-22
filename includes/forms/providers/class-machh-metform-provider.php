<?php
/**
 * MetForm Provider
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_MetForm_Provider
 *
 * Handles MetForm form submission tracking.
 */
class Machh_MetForm_Provider implements Machh_Form_Provider {

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
        'id',
        'form_nonce',
        'action',
        'g-recaptcha-response',
        'g-recaptcha-response-v3',
        'mf-captcha-challenge',
        'hidden-fields',
        'mf-hcaptcha-response',
        'mf-turnstile-response',
    );

    /**
     * Field name mapping for common fields
     *
     * @var array
     */
    private $field_name_mappings = array(
        'email'   => array( 'email', 'e-mail', 'mail', 'courriel', 'mf-email' ),
        'name'    => array( 'name', 'nom', 'full-name', 'fullname', 'your-name', 'mf-name', 'mf-first-name', 'mf-last-name' ),
        'phone'   => array( 'phone', 'tel', 'telephone', 'mobile', 'cell', 'mf-phone', 'mf-mobile' ),
        'message' => array( 'message', 'msg', 'comment', 'comments', 'your-message', 'mf-textarea', 'mf-message' ),
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
     * Check if MetForm is available
     *
     * @return bool
     */
    public function is_available() {
        return class_exists( 'MetForm\Plugin' );
    }

    /**
     * Register MetForm hooks
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'metform_after_store_form_data', array( $this, 'on_form_submitted' ), 10, 4 );
    }

    /**
     * Handle MetForm form submission
     *
     * @param int   $form_id       Form ID.
     * @param array $form_data     Submitted form data.
     * @param array $form_settings Form settings.
     * @param array $attributes    Additional attributes (email_field_name, file_data, file_upload_info).
     * @return void
     */
    public function on_form_submitted( $form_id, $form_data, $form_settings, $attributes ) {
        if ( empty( $form_data ) || ! is_array( $form_data ) ) {
            Machh_Plugin::log( 'MetForm: No form data available', 'warning' );
            return;
        }

        // Build payload
        $payload = $this->build_payload( $form_id, $form_data, $form_settings, $attributes );

        // Send to ingestion API
        $result = $this->http->send_form_submitted( $payload );

        if ( is_wp_error( $result ) ) {
            Machh_Plugin::log(
                sprintf( 'MetForm form submission forwarding failed: %s', $result->get_error_message() ),
                'error'
            );
        } else {
            Machh_Plugin::log(
                sprintf( 'MetForm form submission forwarded: form_id=%d, status=%d', $payload['form_id'], $result['status_code'] ),
                'info'
            );
        }
    }

    /**
     * Build form submission payload
     *
     * @param int   $form_id       Form ID.
     * @param array $form_data     Submitted form data.
     * @param array $form_settings Form settings.
     * @param array $attributes    Additional attributes.
     * @return array
     */
    private function build_payload( $form_id, array $form_data, array $form_settings, array $attributes ) {
        // Get form name from post title
        $form_name = get_the_title( $form_id );

        // Build raw_fields (filtered)
        $raw_fields = $this->build_raw_fields( $form_data );

        // Map common fields
        $mapped_fields = $this->map_common_fields( $form_data, $attributes );

        // Build payload
        $payload = array(
            'device_id'   => Machh_Context::get_device_id(),
            'url'         => Machh_Context::get_current_url_best_effort(),
            'referrer'    => Machh_Context::get_referrer(),
            'site_domain' => Machh_Context::get_site_domain(),
            'form_id'     => (int) $form_id,
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
     * Build raw_fields excluding internal MetForm fields
     *
     * @param array $form_data Submitted form data.
     * @return array
     */
    private function build_raw_fields( array $form_data ) {
        $raw_fields = array();

        foreach ( $form_data as $key => $value ) {
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

            // Sanitize key (remove mf- prefix for cleaner output)
            $clean_key = preg_replace( '/^mf-/', '', $key );

            $raw_fields[ $clean_key ] = sanitize_text_field( $value );
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

        // Check prefix matches for internal MetForm fields
        $excluded_prefixes = array( 'mf-recaptcha', 'mf-hcaptcha', 'mf-turnstile', 'mf-captcha' );
        foreach ( $excluded_prefixes as $prefix ) {
            if ( strpos( $key, $prefix ) === 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map common fields from form data
     *
     * @param array $form_data  Submitted form data.
     * @param array $attributes Additional attributes (contains email_field_name).
     * @return array
     */
    private function map_common_fields( array $form_data, array $attributes ) {
        $mapped = array(
            'email'   => '',
            'name'    => '',
            'phone'   => '',
            'message' => '',
        );

        // Try to get email from the known email field name (from attributes)
        if ( ! empty( $attributes['email_field_name'] ) ) {
            $email_keys = (array) $attributes['email_field_name'];
            foreach ( $email_keys as $email_key ) {
                if ( isset( $form_data[ $email_key ] ) && ! empty( $form_data[ $email_key ] ) ) {
                    $mapped['email'] = sanitize_email( $form_data[ $email_key ] );
                    break;
                }
            }
        }

        // Map remaining fields using name patterns
        foreach ( $mapped as $field_type => $value ) {
            // Skip email if already found via attributes
            if ( 'email' === $field_type && ! empty( $mapped['email'] ) ) {
                continue;
            }

            $mapped[ $field_type ] = $this->find_field_value( $form_data, $this->field_name_mappings[ $field_type ] );
        }

        return $mapped;
    }

    /**
     * Find first non-empty value from candidate keys
     *
     * @param array $form_data  Submitted form data.
     * @param array $candidates Candidate field keys to check.
     * @return string
     */
    private function find_field_value( array $form_data, array $candidates ) {
        // First try exact matches
        foreach ( $candidates as $key ) {
            if ( isset( $form_data[ $key ] ) && ! empty( $form_data[ $key ] ) ) {
                return $this->sanitize_field_value( $form_data[ $key ] );
            }
        }

        // Then try partial matches (field name contains candidate)
        foreach ( $form_data as $field_key => $value ) {
            if ( empty( $value ) ) {
                continue;
            }

            $field_key_lower = strtolower( $field_key );
            foreach ( $candidates as $candidate ) {
                if ( strpos( $field_key_lower, $candidate ) !== false ) {
                    return $this->sanitize_field_value( $value );
                }
            }
        }

        return '';
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Field value.
     * @return string
     */
    private function sanitize_field_value( $value ) {
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_filter( $value ) );
        }

        return sanitize_text_field( $value );
    }
}

