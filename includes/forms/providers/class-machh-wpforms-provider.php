<?php
/**
 * WPForms Provider
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_WPForms_Provider
 *
 * Handles WPForms form submission tracking.
 */
class Machh_WPForms_Provider implements Machh_Form_Provider {

    /**
     * HTTP handler
     *
     * @var Machh_Http
     */
    private $http;

    /**
     * Field types to exclude from raw_fields
     *
     * @var array
     */
    private $excluded_field_types = array(
        'captcha',
        'hcaptcha',
        'turnstile',
        'divider',
        'html',
        'pagebreak',
        'hidden',
    );

    /**
     * Field type mapping for common fields
     * Maps WPForms field types to our standard field names
     *
     * @var array
     */
    private $field_type_mappings = array(
        'email'   => array( 'email' ),
        'name'    => array( 'name' ),
        'phone'   => array( 'phone' ),
        'message' => array( 'textarea' ),
    );

    /**
     * Field name mapping for common fields (fallback for text fields)
     *
     * @var array
     */
    private $field_name_mappings = array(
        'email'   => array( 'email', 'e-mail', 'mail', 'courriel' ),
        'name'    => array( 'name', 'nom', 'full-name', 'fullname', 'your-name' ),
        'phone'   => array( 'phone', 'tel', 'telephone', 'mobile', 'cell' ),
        'message' => array( 'message', 'msg', 'comment', 'comments', 'your-message' ),
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
     * Check if WPForms is available
     *
     * @return bool
     */
    public function is_available() {
        return defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' );
    }

    /**
     * Register WPForms hooks
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'wpforms_process_complete', array( $this, 'on_form_submitted' ), 10, 4 );
    }

    /**
     * Handle WPForms form submission
     *
     * @param array $fields    Sanitized entry field values/properties.
     * @param array $entry     Original $_POST global.
     * @param array $form_data Form data and settings.
     * @param int   $entry_id  Entry ID.
     * @return void
     */
    public function on_form_submitted( $fields, $entry, $form_data, $entry_id ) {
        if ( empty( $fields ) || ! is_array( $fields ) ) {
            Machh_Plugin::log( 'WPForms: No fields data available', 'warning' );
            return;
        }

        // Build payload
        $payload = $this->build_payload( $fields, $entry, $form_data, $entry_id );

        // Send to ingestion API
        $result = $this->http->send_form_submitted( $payload );

        if ( is_wp_error( $result ) ) {
            Machh_Plugin::log(
                sprintf( 'WPForms form submission forwarding failed: %s', $result->get_error_message() ),
                'error'
            );
        } else {
            Machh_Plugin::log(
                sprintf( 'WPForms form submission forwarded: form_id=%d, status=%d', $payload['form_id'], $result['status_code'] ),
                'info'
            );
        }
    }

    /**
     * Build form submission payload
     *
     * @param array $fields    Sanitized entry field values/properties.
     * @param array $entry     Original $_POST global.
     * @param array $form_data Form data and settings.
     * @param int   $entry_id  Entry ID.
     * @return array
     */
    private function build_payload( array $fields, array $entry, array $form_data, $entry_id ) {
        // Extract form info
        $form_id   = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
        $form_name = isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '';

        // Build raw_fields (filtered)
        $raw_fields = $this->build_raw_fields( $fields );

        // Map common fields
        $mapped_fields = $this->map_common_fields( $fields );

        // Build payload
        $payload = array(
            'device_id'   => Machh_Context::get_device_id(),
            'url'         => Machh_Context::get_current_url_best_effort(),
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
     * Build raw_fields excluding internal WPForms fields
     *
     * @param array $fields WPForms fields data.
     * @return array
     */
    private function build_raw_fields( array $fields ) {
        $raw_fields = array();

        foreach ( $fields as $field ) {
            // Skip if field structure is invalid
            if ( ! isset( $field['type'] ) || ! isset( $field['name'] ) ) {
                continue;
            }

            // Skip excluded field types
            if ( $this->is_excluded_field_type( $field['type'] ) ) {
                continue;
            }

            // Get field value
            $value = $this->get_field_value( $field );

            // Skip empty values
            if ( empty( $value ) && $value !== '0' ) {
                continue;
            }

            // Use field name as key (sanitized)
            $key = sanitize_title( $field['name'] );
            if ( empty( $key ) ) {
                $key = 'field_' . ( isset( $field['id'] ) ? $field['id'] : uniqid() );
            }

            $raw_fields[ $key ] = sanitize_text_field( $value );
        }

        return $raw_fields;
    }

    /**
     * Get field value from WPForms field structure
     *
     * @param array $field Field data.
     * @return string
     */
    private function get_field_value( array $field ) {
        // Check for value_raw first (preserves original formatting)
        if ( isset( $field['value_raw'] ) && ! empty( $field['value_raw'] ) ) {
            $value = $field['value_raw'];
        } elseif ( isset( $field['value'] ) ) {
            $value = $field['value'];
        } else {
            return '';
        }

        // Convert array to string if needed
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_filter( $value ) );
        }

        return (string) $value;
    }

    /**
     * Check if field type should be excluded
     *
     * @param string $type Field type.
     * @return bool
     */
    private function is_excluded_field_type( $type ) {
        return in_array( strtolower( $type ), $this->excluded_field_types, true );
    }

    /**
     * Map common fields from WPForms field structure
     *
     * @param array $fields WPForms fields data.
     * @return array
     */
    private function map_common_fields( array $fields ) {
        $mapped = array(
            'email'   => '',
            'name'    => '',
            'phone'   => '',
            'message' => '',
        );

        foreach ( $mapped as $field_type => $default ) {
            $mapped[ $field_type ] = $this->find_field_value_by_type( $fields, $field_type );
        }

        return $mapped;
    }

    /**
     * Find field value by standard field type
     *
     * @param array  $fields     WPForms fields data.
     * @param string $field_type Standard field type (email, name, phone, message).
     * @return string
     */
    private function find_field_value_by_type( array $fields, $field_type ) {
        // First try to match by WPForms field type
        if ( isset( $this->field_type_mappings[ $field_type ] ) ) {
            foreach ( $fields as $field ) {
                if ( isset( $field['type'] ) && in_array( strtolower( $field['type'] ), $this->field_type_mappings[ $field_type ], true ) ) {
                    $value = $this->get_field_value( $field );
                    if ( ! empty( $value ) ) {
                        return sanitize_text_field( $value );
                    }
                }
            }
        }

        // Fallback: try to match by field name
        if ( isset( $this->field_name_mappings[ $field_type ] ) ) {
            foreach ( $fields as $field ) {
                if ( isset( $field['name'] ) ) {
                    $field_name_lower = strtolower( $field['name'] );
                    foreach ( $this->field_name_mappings[ $field_type ] as $candidate ) {
                        if ( strpos( $field_name_lower, $candidate ) !== false ) {
                            $value = $this->get_field_value( $field );
                            if ( ! empty( $value ) ) {
                                return sanitize_text_field( $value );
                            }
                        }
                    }
                }
            }
        }

        return '';
    }
}

