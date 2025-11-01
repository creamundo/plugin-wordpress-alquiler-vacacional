<?php
/**
 * Settings handling.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Settings {
    /**
     * Register settings section and fields.
     */
    public static function register() {
        register_setting( 'ctvr_settings', CTVR_Plugin::SETTINGS_OPTION, [ __CLASS__, 'sanitize' ] );

        add_settings_section(
            'ctvr_general_section',
            __( 'Preferencias generales', 'casatrinidad-vacation-rental' ),
            '__return_false',
            'ctvr_settings'
        );

        add_settings_field(
            'min_nights',
            __( 'Número mínimo de noches', 'casatrinidad-vacation-rental' ),
            [ __CLASS__, 'render_number_field' ],
            'ctvr_settings',
            'ctvr_general_section',
            [
                'label_for'   => 'ctvr_min_nights',
                'option_key'  => 'min_nights',
                'description' => __( 'Número mínimo de noches que debe tener una solicitud de reserva.', 'casatrinidad-vacation-rental' ),
                'min'         => 1,
            ]
        );

        add_settings_field(
            'notify_emails',
            __( 'Correos de notificación', 'casatrinidad-vacation-rental' ),
            [ __CLASS__, 'render_text_field' ],
            'ctvr_settings',
            'ctvr_general_section',
            [
                'label_for'   => 'ctvr_notify_emails',
                'option_key'  => 'notify_emails',
                'description' => __( 'Correos separados por comas que recibirán notificaciones de nuevas solicitudes.', 'casatrinidad-vacation-rental' ),
            ]
        );

        add_settings_section(
            'ctvr_operations_section',
            __( 'Ajustes operativos', 'casatrinidad-vacation-rental' ),
            '__return_false',
            'ctvr_settings'
        );

        $operation_fields = [
            'cleaning_hour_price'   => [ __( 'Precio por hora de limpieza', 'casatrinidad-vacation-rental' ), 'number', [ 'min' => 0, 'step' => '0.01' ] ],
            'tax_percentage'        => [ __( '% de impuestos (Hacienda)', 'casatrinidad-vacation-rental' ), 'number', [ 'min' => 0, 'step' => '0.01' ] ],
            'platforms'             => [ __( 'Plataformas externas (JSON)', 'casatrinidad-vacation-rental' ), 'textarea' ],
            'key_delivery_price'    => [ __( 'Precio entrega de llaves', 'casatrinidad-vacation-rental' ), 'number', [ 'min' => 0, 'step' => '0.01' ] ],
            'linen_cleaning_price'  => [ __( 'Precio limpieza ropa de cama', 'casatrinidad-vacation-rental' ), 'number', [ 'min' => 0, 'step' => '0.01' ] ],
            'management_percentage' => [ __( '% gestión', 'casatrinidad-vacation-rental' ), 'number', [ 'min' => 0, 'step' => '0.01' ] ],
            'assistant_email'       => [ __( 'Correo ayudante limpieza', 'casatrinidad-vacation-rental' ), 'text' ],
        ];

        foreach ( $operation_fields as $key => $field ) {
            $callback = 'render_text_field';
            $args     = [ 'label_for' => 'ctvr_' . $key, 'option_key' => $key ];

            if ( 'number' === $field[1] ) {
                $callback             = 'render_number_field';
                $args['min']          = isset( $field[2]['min'] ) ? $field[2]['min'] : null;
                $args['step']         = isset( $field[2]['step'] ) ? $field[2]['step'] : null;
            } elseif ( 'textarea' === $field[1] ) {
                $callback = 'render_textarea_field';
            }

            $args['description'] = isset( $field[2]['description'] ) ? $field[2]['description'] : '';

            add_settings_field(
                $key,
                $field[0],
                [ __CLASS__, $callback ],
                'ctvr_settings',
                'ctvr_operations_section',
                $args
            );
        }
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Raw data.
     * @return array
     */
    public static function sanitize( $input ) {
        $defaults = [
            'min_nights'            => 2,
            'notify_emails'         => '',
            'cleaning_hour_price'   => '',
            'tax_percentage'        => '',
            'platforms'             => '',
            'key_delivery_price'    => '',
            'linen_cleaning_price'  => '',
            'management_percentage' => '',
            'assistant_email'       => '',
        ];

        $input = wp_parse_args( (array) $input, $defaults );

        $output = [];
        $output['min_nights'] = max( 1, absint( $input['min_nights'] ) );
        $output['notify_emails'] = sanitize_text_field( $input['notify_emails'] );

        $numeric_keys = [ 'cleaning_hour_price', 'tax_percentage', 'key_delivery_price', 'linen_cleaning_price', 'management_percentage' ];

        foreach ( $numeric_keys as $numeric_key ) {
            $value = isset( $input[ $numeric_key ] ) ? floatval( $input[ $numeric_key ] ) : 0;
            $output[ $numeric_key ] = $value >= 0 ? $value : 0;
        }

        $output['platforms'] = wp_kses_post( $input['platforms'] );
        $output['assistant_email'] = sanitize_email( $input['assistant_email'] );

        return $output;
    }

    /**
     * Render a text field.
     */
    public static function render_text_field( $args ) {
        $options = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        $value   = isset( $options[ $args['option_key'] ] ) ? $options[ $args['option_key'] ] : '';
        printf(
            '<input type="text" id="%1$s" name="%2$s[%3$s]" class="regular-text" value="%4$s" />',
            esc_attr( $args['label_for'] ),
            esc_attr( CTVR_Plugin::SETTINGS_OPTION ),
            esc_attr( $args['option_key'] ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render a textarea field.
     */
    public static function render_textarea_field( $args ) {
        $options = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        $value   = isset( $options[ $args['option_key'] ] ) ? $options[ $args['option_key'] ] : '';
        printf(
            '<textarea id="%1$s" name="%2$s[%3$s]" class="large-text" rows="5">%4$s</textarea>',
            esc_attr( $args['label_for'] ),
            esc_attr( CTVR_Plugin::SETTINGS_OPTION ),
            esc_attr( $args['option_key'] ),
            esc_textarea( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render number field.
     */
    public static function render_number_field( $args ) {
        $options = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        $value   = isset( $options[ $args['option_key'] ] ) ? $options[ $args['option_key'] ] : '';
        $attrs   = '';
        if ( isset( $args['min'] ) ) {
            $attrs .= ' min="' . esc_attr( $args['min'] ) . '"';
        }
        if ( isset( $args['step'] ) ) {
            $attrs .= ' step="' . esc_attr( $args['step'] ) . '"';
        }

        printf(
            '<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$s" %5$s />',
            esc_attr( $args['label_for'] ),
            esc_attr( CTVR_Plugin::SETTINGS_OPTION ),
            esc_attr( $args['option_key'] ),
            esc_attr( $value ),
            $attrs
        );
        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }
}
