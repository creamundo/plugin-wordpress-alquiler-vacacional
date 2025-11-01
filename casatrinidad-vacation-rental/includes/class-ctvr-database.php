<?php
/**
 * Database schema management.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Database {
    /**
     * Database version option name.
     */
    const DB_VERSION_OPTION = 'ctvr_db_version';

    /**
     * Current database version.
     */
    const DB_VERSION = '2024.04.01';

    /**
     * Plugin activation handler.
     */
    public static function activate() {
        self::create_tables();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Plugin deactivation handler.
     */
    public static function deactivate() {
        // Nothing to clean up for now.
    }

    /**
     * Create or upgrade database tables.
     */
    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $availability = $wpdb->prefix . 'ctvr_availability';
        $requests     = $wpdb->prefix . 'ctvr_requests';
        $reservations = $wpdb->prefix . 'ctvr_reservations';
        $tasks        = $wpdb->prefix . 'ctvr_tasks';
        $checklists   = $wpdb->prefix . 'ctvr_task_checklists';
        $stats        = $wpdb->prefix . 'ctvr_stats';

        $sql_availability = "CREATE TABLE $availability (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            day date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            price decimal(10,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY day (day)
        ) $charset_collate;";

        $sql_requests = "CREATE TABLE $requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            start_date date NOT NULL,
            end_date date NOT NULL,
            nights smallint(5) unsigned NOT NULL DEFAULT 1,
            price_total decimal(10,2) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payload longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_reservations = "CREATE TABLE $reservations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_id bigint(20) unsigned DEFAULT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            price_total decimal(10,2) DEFAULT NULL,
            payload longtext NOT NULL,
            checklist longtext DEFAULT NULL,
            services longtext DEFAULT NULL,
            public_token varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY request_id (request_id),
            UNIQUE KEY public_token (public_token)
        ) $charset_collate;";

        $sql_tasks = "CREATE TABLE $tasks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reservation_id bigint(20) unsigned NOT NULL,
            entry_hours decimal(5,2) DEFAULT NULL,
            exit_hours decimal(5,2) DEFAULT NULL,
            entry_checklist longtext DEFAULT NULL,
            exit_checklist longtext DEFAULT NULL,
            services longtext DEFAULT NULL,
            purchases longtext DEFAULT NULL,
            notes longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY reservation_id (reservation_id)
        ) $charset_collate;";

        $sql_checklists = "CREATE TABLE $checklists (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            scope varchar(20) NOT NULL DEFAULT 'both',
            location varchar(50) NOT NULL DEFAULT 'general',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_stats = "CREATE TABLE $stats (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event varchar(50) NOT NULL,
            event_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            payload longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY event (event),
            KEY event_date (event_date)
        ) $charset_collate;";

        dbDelta( $sql_availability );
        dbDelta( $sql_requests );
        dbDelta( $sql_reservations );
        dbDelta( $sql_tasks );
        dbDelta( $sql_checklists );
        dbDelta( $sql_stats );
    }
}
