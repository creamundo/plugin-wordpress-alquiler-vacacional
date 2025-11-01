<?php
/**
 * Plugin Name: Casa Trinidad Vacation Rental Manager
 * Description: Gestiona la disponibilidad y limpieza del alquiler vacacional Casa Trinidad en Caños de Meca.
 * Version: 0.1.0
 * Author: Casa Trinidad
 * Text Domain: casatrinidad-vacation-rental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-ctvr-plugin.php';

\CTVR_Plugin::instance();
