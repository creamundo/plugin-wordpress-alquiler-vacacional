<?php
/**
 * Admin pages.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Admin {
    /**
     * Register admin menus.
     */
    public static function register_menus() {
        add_menu_page(
            __( 'Casa Trinidad', 'casatrinidad-vacation-rental' ),
            __( 'Casa Trinidad', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-dashboard',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-calendar-alt'
        );

        add_submenu_page(
            'ctvr-dashboard',
            __( 'Disponibilidad', 'casatrinidad-vacation-rental' ),
            __( 'Disponibilidad', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-availability',
            [ __CLASS__, 'render_availability' ]
        );

        add_submenu_page(
            'ctvr-dashboard',
            __( 'Solicitudes', 'casatrinidad-vacation-rental' ),
            __( 'Solicitudes', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-requests',
            [ __CLASS__, 'render_requests' ]
        );

        add_submenu_page(
            'ctvr-dashboard',
            __( 'Reservas aprobadas', 'casatrinidad-vacation-rental' ),
            __( 'Reservas aprobadas', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-reservations',
            [ __CLASS__, 'render_reservations' ]
        );

        add_submenu_page(
            'ctvr-dashboard',
            __( 'Checklist', 'casatrinidad-vacation-rental' ),
            __( 'Checklist', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-checklist',
            [ __CLASS__, 'render_checklist' ]
        );

        add_submenu_page(
            'ctvr-dashboard',
            __( 'Ajustes', 'casatrinidad-vacation-rental' ),
            __( 'Ajustes', 'casatrinidad-vacation-rental' ),
            'manage_options',
            'ctvr-settings',
            [ __CLASS__, 'render_settings' ]
        );

        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    /**
     * Enqueue admin assets.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'ctvr' ) ) {
            return;
        }

        wp_enqueue_style( 'ctvr-admin', CTVR_ASSETS_URL . 'css/admin.css', [], CTVR_Plugin::VERSION );
        wp_enqueue_script( 'ctvr-admin', CTVR_ASSETS_URL . 'js/admin.js', [ 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch' ], CTVR_Plugin::VERSION, true );
        wp_localize_script(
            'ctvr-admin',
            'ctvrAdmin',
            [
                'restUrl' => esc_url_raw( rest_url( 'ctvr/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }

    /**
     * Dashboard page.
     */
    public static function render_dashboard() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Estadísticas Casa Trinidad', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<div id="ctvr-dashboard-app" class="ctvr-card">' . esc_html__( 'Cargando estadísticas…', 'casatrinidad-vacation-rental' ) . '</div>';
        echo '</div>';
    }

    /**
     * Availability page.
     */
    public static function render_availability() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Calendario de disponibilidad', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<div id="ctvr-availability-app" class="ctvr-card">' . esc_html__( 'Cargando calendario…', 'casatrinidad-vacation-rental' ) . '</div>';
        echo '</div>';
    }

    /**
     * Requests page.
     */
    public static function render_requests() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Solicitudes de reserva', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<div id="ctvr-requests-app" class="ctvr-card">' . esc_html__( 'Cargando solicitudes…', 'casatrinidad-vacation-rental' ) . '</div>';
        echo '</div>';
    }

    /**
     * Reservations page.
     */
    public static function render_reservations() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Reservas aprobadas', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<div id="ctvr-reservations-app" class="ctvr-card">' . esc_html__( 'Cargando reservas…', 'casatrinidad-vacation-rental' ) . '</div>';
        echo '</div>';
    }

    /**
     * Checklist page.
     */
    public static function render_checklist() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Checklist de tareas', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<div id="ctvr-checklist-app" class="ctvr-card">' . esc_html__( 'Cargando checklist…', 'casatrinidad-vacation-rental' ) . '</div>';
        echo '</div>';
    }

    /**
     * Settings page.
     */
    public static function render_settings() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ajustes Casa Trinidad', 'casatrinidad-vacation-rental' ) . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields( 'ctvr_settings' );
        do_settings_sections( 'ctvr_settings' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
