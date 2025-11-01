<?php
/**
 * Front-end rendering helpers.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Frontend {
    /**
     * Enqueue assets.
     */
    public static function enqueue_assets() {
        wp_enqueue_style( 'ctvr-front', CTVR_ASSETS_URL . 'css/front.css', [], CTVR_Plugin::VERSION );
        wp_enqueue_script( 'ctvr-front', CTVR_ASSETS_URL . 'js/front.js', [ 'wp-element' ], CTVR_Plugin::VERSION, true );
        wp_localize_script(
            'ctvr-front',
            'ctvrFront',
            [
                'restUrl'   => esc_url_raw( rest_url( 'ctvr/v1' ) ),
                'minNights' => intval( self::get_setting( 'min_nights', 1 ) ),
            ]
        );
    }

    /**
     * Render calendar shortcode.
     */
    public static function render_calendar_shortcode() {
        ob_start();
        ?>
        <div class="ctvr-calendar-wrapper">
            <div id="ctvr-calendar-app" class="ctvr-card" aria-live="polite">
                <noscript><?php esc_html_e( 'Necesitas activar JavaScript para ver el calendario.', 'casatrinidad-vacation-rental' ); ?></noscript>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get setting helper.
     */
    protected static function get_setting( $key, $default = null ) {
        $settings = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}
