<?php
/**
 * Elementor Pro Forms Provider
 *
 * @package Machh_WP_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Machh_Elementor_Provider
 *
 * Handles Elementor Pro Form widget submission tracking.
 */
class Machh_Elementor_Provider implements Machh_Form_Provider {

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
        'recaptcha',
        'recaptcha_v3',
        'honeypot',
        'hcaptcha',
        'hidden',
        'step',
    );

    /**
     * Field type mapping for common fields
     * Maps Elementor field types to standard field names
     *
     * @var array
     */
    private $field_type_mappings = array(
        'email'   => array( 'email' ),
        'phone'   => array( 'tel' ),
        'message' => array( 'textarea' ),
    );

    /**
     * Field ID/label mapping for common fields (fallback)
     *
     * @var array
     */
    private $field_name_mappings = array(
        'email'   => array( 'email', 'e-mail', 'mail', 'courriel' ),
        'name'    => array( 'name', 'nom', 'full-name', 'fullname', 'your-name', 'first-name', 'last-name', 'prenom', 'prénom' ),
        'phone'   => array( 'phone', 'tel', 'telephone', 'téléphone', 'mobile', 'cell' ),
        'message' => array( 'message', 'msg', 'comment', 'comments', 'your-message', 'textarea' ),
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
     * Check if Elementor Pro Forms is available
     *
     * @return bool
     */
    public function is_available() {
        return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( 'ElementorPro\Plugin' );
    }

    /**
     * Register Elementor Pro Forms hooks
     *
     * @return void
     */
    public function register_hooks() {
        add_action( 'elementor_pro/forms/new_record', array( $this, 'on_new_record' ), 10, 2 );
    }

    /**
     * Handle Elementor form new record
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       The form record.
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler The Ajax handler.
     * @return void
     */
    public function on_new_record( $record, $ajax_handler ) {
        $raw_fields_data = $record->get( 'fields' );

        if ( empty( $raw_fields_data ) || ! is_array( $raw_fields_data ) ) {
            Machh_Plugin::log( 'Elementor Forms: No fields data available', 'warning' );
            return;
        }

        $payload = $this->build_payload( $record, $raw_fields_data );

        $result = $this->http->send_form_submitted( $payload );

        if ( is_wp_error( $result ) ) {
            Machh_Plugin::log(
                sprintf( 'Elementor Forms submission forwarding failed: %s', $result->get_error_message() ),
                'error'
            );
        } else {
            Machh_Plugin::log(
                sprintf( 'Elementor Forms submission forwarded: form_name=%s, status=%d', $payload['form_name'], $result['status_code'] ),
                'info'
            );
        }
    }

    /**
     * Build form submission payload
     *
     * @param object $record          The form record.
     * @param array  $raw_fields_data Raw fields from record->get('fields').
     * @return array
     */
    private function build_payload( $record, array $raw_fields_data ) {
        $form_settings = $record->get( 'form_settings' );
        $form_name     = isset( $form_settings['form_name'] ) ? (string) $form_settings['form_name'] : '';
        $form_id       = isset( $form_settings['id'] ) ? (string) $form_settings['id'] : '';

        $raw_fields    = $this->build_raw_fields( $raw_fields_data );
        $mapped_fields = $this->map_common_fields( $raw_fields_data );

        return array(
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
    }

    /**
     * Build raw_fields excluding internal Elementor fields
     *
     * @param array $fields_data Elementor fields data.
     * @return array
     */
    private function build_raw_fields( array $fields_data ) {
        $raw_fields = array();

        foreach ( $fields_data as $id => $field ) {
            if ( ! isset( $field['type'] ) ) {
                continue;
            }

            if ( $this->is_excluded_field_type( $field['type'] ) ) {
                continue;
            }

            $value = isset( $field['value'] ) ? $field['value'] : '';

            if ( is_array( $value ) ) {
                $value = implode( ', ', array_filter( $value ) );
            }

            if ( empty( $value ) && $value !== '0' ) {
                continue;
            }

            $key = ! empty( $field['title'] ) ? sanitize_title( $field['title'] ) : $id;
            $raw_fields[ $key ] = sanitize_text_field( $value );
        }

        return $raw_fields;
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
     * Map common fields from Elementor field structure
     *
     * @param array $fields_data Elementor fields data.
     * @return array
     */
    private function map_common_fields( array $fields_data ) {
        $mapped = array(
            'email'   => '',
            'name'    => '',
            'phone'   => '',
            'message' => '',
        );

        foreach ( $mapped as $field_type => $default ) {
            $mapped[ $field_type ] = $this->find_field_value( $fields_data, $field_type );
        }

        return $mapped;
    }

    /**
     * Find field value by standard field type
     *
     * First tries to match by Elementor field type, then falls back to ID/label matching.
     *
     * @param array  $fields_data Elementor fields data.
     * @param string $field_type  Standard field type (email, name, phone, message).
     * @return string
     */
    private function find_field_value( array $fields_data, $field_type ) {
        // 1. Match by Elementor field type
        if ( isset( $this->field_type_mappings[ $field_type ] ) ) {
            foreach ( $fields_data as $field ) {
                if ( isset( $field['type'] ) && in_array( strtolower( $field['type'] ), $this->field_type_mappings[ $field_type ], true ) ) {
                    $value = isset( $field['value'] ) ? $field['value'] : '';
                    if ( ! empty( $value ) ) {
                        return sanitize_text_field( $value );
                    }
                }
            }
        }

        // 2. Fallback: match by field ID or title
        if ( isset( $this->field_name_mappings[ $field_type ] ) ) {
            foreach ( $fields_data as $id => $field ) {
                $id_lower    = strtolower( $id );
                $title_lower = isset( $field['title'] ) ? strtolower( $field['title'] ) : '';

                foreach ( $this->field_name_mappings[ $field_type ] as $candidate ) {
                    if ( strpos( $id_lower, $candidate ) !== false || strpos( $title_lower, $candidate ) !== false ) {
                        $value = isset( $field['value'] ) ? $field['value'] : '';
                        if ( ! empty( $value ) ) {
                            return sanitize_text_field( $value );
                        }
                    }
                }
            }
        }

        return '';
    }
}
