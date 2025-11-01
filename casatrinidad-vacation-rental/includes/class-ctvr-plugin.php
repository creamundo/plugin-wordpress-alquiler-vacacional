<?php
/**
 * Main plugin bootstrap.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Plugin {
    /**
     * Singleton instance.
     *
     * @var CTVR_Plugin
     */
    protected static $instance;

    /**
     * Plugin version.
     */
    const VERSION = '0.1.0';

    /**
     * Option key used for settings.
     */
    const SETTINGS_OPTION = 'ctvr_settings';

    /**
     * Get singleton instance.
     *
     * @return CTVR_Plugin
     */
    public static function instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->define_constants();
        $this->includes();
        $this->hooks();
    }

    /**
     * Define plugin constants.
     */
    protected function define_constants() {
        define( 'CTVR_PLUGIN_FILE', dirname( __DIR__ ) . '/casatrinidad-vacation-rental.php' );
        define( 'CTVR_PLUGIN_DIR', dirname( __FILE__, 2 ) . '/' );
        define( 'CTVR_PLUGIN_URL', plugin_dir_url( CTVR_PLUGIN_FILE ) );
        define( 'CTVR_ASSETS_URL', trailingslashit( CTVR_PLUGIN_URL . 'includes/assets' ) );
    }

    /**
     * Include plugin files.
     */
    protected function includes() {
        require_once __DIR__ . '/class-ctvr-database.php';
        require_once __DIR__ . '/class-ctvr-settings.php';
        require_once __DIR__ . '/class-ctvr-rest.php';
        require_once __DIR__ . '/class-ctvr-availability.php';
        require_once __DIR__ . '/class-ctvr-reservations.php';
        require_once __DIR__ . '/class-ctvr-cleaning.php';
        require_once __DIR__ . '/class-ctvr-admin.php';
        require_once __DIR__ . '/class-ctvr-frontend.php';
    }

    /**
     * Hook into WordPress.
     */
    protected function hooks() {
        register_activation_hook( CTVR_PLUGIN_FILE, [ 'CTVR_Database', 'activate' ] );
        register_deactivation_hook( CTVR_PLUGIN_FILE, [ 'CTVR_Database', 'deactivate' ] );

        add_action( 'init', [ 'CTVR_Availability', 'init' ] );
        add_action( 'init', [ 'CTVR_Reservations', 'init' ] );
        add_action( 'init', [ 'CTVR_Cleaning', 'init' ] );

        add_action( 'rest_api_init', [ 'CTVR_REST', 'register_routes' ] );

        add_action( 'admin_menu', [ 'CTVR_Admin', 'register_menus' ] );
        add_action( 'admin_init', [ 'CTVR_Settings', 'register' ] );

        add_action( 'wp_enqueue_scripts', [ 'CTVR_Frontend', 'enqueue_assets' ] );
        add_shortcode( 'casatrinidad_calendar', [ 'CTVR_Frontend', 'render_calendar_shortcode' ] );
    }
}
