<?php
/**
 * Cleaning and workorder helpers.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Cleaning {
    /**
     * Init hooks.
     */
    public static function init() {
        add_filter( 'query_vars', [ __CLASS__, 'register_query_var' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_render_public_workorder' ] );
    }

    /**
     * Register custom query var.
     */
    public static function register_query_var( $vars ) {
        $vars[] = 'ctvr_token';
        return $vars;
    }

    /**
     * Render public workorder if token present.
     */
    public static function maybe_render_public_workorder() {
        $token = get_query_var( 'ctvr_token' );
        if ( empty( $token ) ) {
            return;
        }

        $workorder = self::get_workorder_by_token( sanitize_text_field( $token ) );
        if ( ! $workorder ) {
            status_header( 404 );
            wp_die( esc_html__( 'Reserva no encontrada o enlace caducado.', 'casatrinidad-vacation-rental' ), '', [ 'response' => 404 ] );
        }

        wp_enqueue_style( 'ctvr-front', CTVR_ASSETS_URL . 'css/front.css', [], CTVR_Plugin::VERSION );
        wp_enqueue_script( 'ctvr-cleaning', CTVR_ASSETS_URL . 'js/cleaning.js', [ 'wp-element' ], CTVR_Plugin::VERSION, true );
        wp_localize_script(
            'ctvr-cleaning',
            'ctvrCleaning',
            [
                'restUrl' => esc_url_raw( rest_url( 'ctvr/v1' ) ),
                'token'   => sanitize_text_field( $token ),
            ]
        );

        status_header( 200 );
        nocache_headers();

        echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>';
        echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html__( 'Orden de trabajo Casa Trinidad', 'casatrinidad-vacation-rental' ) . '</title>';
        wp_head();
        echo '</head><body class="ctvr-cleaning-public">';
        if ( function_exists( 'wp_body_open' ) ) {
            wp_body_open();
        }
        echo '<div class="ctvr-calendar-wrapper"><div id="ctvr-cleaning-app" class="ctvr-card"></div></div>';
        wp_footer();
        echo '</body></html>';
        exit;
    }

    /**
     * Ensure workorder entry exists for reservation.
     */
    public static function ensure_workorder( $reservation_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_tasks';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE reservation_id = %d", $reservation_id ) );
        if ( ! $exists ) {
            $wpdb->insert( $table, [ 'reservation_id' => $reservation_id ] );
        }
    }

    /**
     * Fetch reservation by token.
     */
    public static function get_workorder_by_token( $token ) {
        global $wpdb;
        $reservations = $wpdb->prefix . 'ctvr_reservations';
        $tasks        = $wpdb->prefix . 'ctvr_tasks';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, t.entry_hours, t.exit_hours, t.entry_checklist, t.exit_checklist, t.services, t.purchases
                 FROM $reservations r
                 LEFT JOIN $tasks t ON r.id = t.reservation_id
                 WHERE r.public_token = %s",
                $token
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['payload']         = json_decode( $row['payload'], true );
        $row['entry_checklist'] = $row['entry_checklist'] ? json_decode( $row['entry_checklist'], true ) : [];
        $row['exit_checklist']  = $row['exit_checklist'] ? json_decode( $row['exit_checklist'], true ) : [];
        $row['services']        = $row['services'] ? json_decode( $row['services'], true ) : [];
        $row['purchases']       = $row['purchases'] ? json_decode( $row['purchases'], true ) : [];

        $row['checklists'] = self::get_checklists_grouped();
        $row['settings']   = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );

        return $row;
    }

    /**
     * Fetch reservation by ID with workorder data.
     */
    public static function get_workorder_by_reservation( $reservation_id ) {
        global $wpdb;
        $reservations = $wpdb->prefix . 'ctvr_reservations';
        $tasks        = $wpdb->prefix . 'ctvr_tasks';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, t.entry_hours, t.exit_hours, t.entry_checklist, t.exit_checklist, t.services, t.purchases
                 FROM $reservations r
                 LEFT JOIN $tasks t ON r.id = t.reservation_id
                 WHERE r.id = %d",
                $reservation_id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        $row['payload']         = json_decode( $row['payload'], true );
        $row['entry_checklist'] = $row['entry_checklist'] ? json_decode( $row['entry_checklist'], true ) : [];
        $row['exit_checklist']  = $row['exit_checklist'] ? json_decode( $row['exit_checklist'], true ) : [];
        $row['services']        = $row['services'] ? json_decode( $row['services'], true ) : [];
        $row['purchases']       = $row['purchases'] ? json_decode( $row['purchases'], true ) : [];

        $row['checklists'] = self::get_checklists_grouped();
        $row['settings']   = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );

        return $row;
    }

    /**
     * Update workorder data.
     */
    public static function update_workorder( $reservation_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_tasks';
        self::ensure_workorder( $reservation_id );

        $fields = [
            'entry_hours'     => isset( $data['entry_hours'] ) ? floatval( $data['entry_hours'] ) : null,
            'exit_hours'      => isset( $data['exit_hours'] ) ? floatval( $data['exit_hours'] ) : null,
            'entry_checklist' => isset( $data['entry_checklist'] ) ? wp_json_encode( $data['entry_checklist'] ) : null,
            'exit_checklist'  => isset( $data['exit_checklist'] ) ? wp_json_encode( $data['exit_checklist'] ) : null,
            'services'        => isset( $data['services'] ) ? wp_json_encode( $data['services'] ) : null,
            'purchases'       => isset( $data['purchases'] ) ? wp_json_encode( $data['purchases'] ) : null,
        ];

        $wpdb->update( $table, $fields, [ 'reservation_id' => $reservation_id ] );
    }

    /**
     * Get checklists grouped by tab and area.
     */
    public static function get_checklists_grouped() {
        $all = self::get_checklists( false );
        $grouped = [ 'entry' => [], 'exit' => [] ];

        foreach ( $all as $item ) {
            if ( in_array( $item['scope'], [ 'entry', 'both' ], true ) ) {
                if ( ! isset( $grouped['entry'][ $item['location'] ] ) ) {
                    $grouped['entry'][ $item['location'] ] = [];
                }
                $grouped['entry'][ $item['location'] ][] = $item;
            }
            if ( in_array( $item['scope'], [ 'exit', 'both' ], true ) ) {
                if ( ! isset( $grouped['exit'][ $item['location'] ] ) ) {
                    $grouped['exit'][ $item['location'] ] = [];
                }
                $grouped['exit'][ $item['location'] ][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Retrieve raw checklist items.
     */
    public static function get_checklists( $include_inactive = false ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_task_checklists';
        $where = $include_inactive ? '1=1' : 'is_active = 1';
        $rows  = $wpdb->get_results( "SELECT * FROM $table WHERE $where ORDER BY location ASC, sort_order ASC, title ASC", ARRAY_A );

        return array_map(
            static function ( $row ) {
                return [
                    'id'        => intval( $row['id'] ),
                    'title'     => $row['title'],
                    'scope'     => $row['scope'],
                    'location'  => $row['location'],
                    'is_active' => intval( $row['is_active'] ),
                    'sort_order'=> intval( $row['sort_order'] ),
                ];
            },
            $rows
        );
    }

    /**
     * Upsert checklist item.
     */
    public static function save_checklist( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_task_checklists';

        $fields = [
            'title'      => sanitize_text_field( $data['title'] ),
            'scope'      => in_array( $data['scope'], [ 'entry', 'exit', 'both' ], true ) ? $data['scope'] : 'both',
            'location'   => sanitize_text_field( $data['location'] ),
            'is_active'  => isset( $data['is_active'] ) ? intval( $data['is_active'] ) : 1,
            'sort_order' => isset( $data['sort_order'] ) ? intval( $data['sort_order'] ) : 0,
        ];

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $table, $fields, [ 'id' => intval( $data['id'] ) ] );
            return intval( $data['id'] );
        }

        $wpdb->insert( $table, $fields );
        return intval( $wpdb->insert_id );
    }

    /**
     * Delete checklist.
     */
    public static function delete_checklist( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_task_checklists';
        $wpdb->delete( $table, [ 'id' => intval( $id ) ] );
    }
}
