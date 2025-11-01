<?php
/**
 * Reservation helpers.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Reservations {
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Init hooks.
     */
    public static function init() {
        // Placeholder for custom post types if needed.
    }

    /**
     * Create reservation request entry.
     *
     * @param array $data Payload.
     * @return int|WP_Error
     */
    public static function create_request( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_requests';

        $start = sanitize_text_field( $data['start_date'] );
        $end   = sanitize_text_field( $data['end_date'] );

        $sanitized_payload = [
            'start_date'     => $start,
            'end_date'       => $end,
            'nights'         => absint( $data['nights'] ),
            'price_total'    => isset( $data['price_total'] ) ? floatval( $data['price_total'] ) : null,
            'name'           => sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ),
            'surname'        => sanitize_text_field( isset( $data['surname'] ) ? $data['surname'] : '' ),
            'phone'          => sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' ),
            'email'          => sanitize_email( isset( $data['email'] ) ? $data['email'] : '' ),
            'people'         => absint( isset( $data['people'] ) ? $data['people'] : 0 ),
            'ages'           => array_map( 'absint', isset( $data['ages'] ) ? (array) $data['ages'] : [] ),
            'nationality'    => sanitize_text_field( isset( $data['nationality'] ) ? $data['nationality'] : '' ),
            'province'       => sanitize_text_field( isset( $data['province'] ) ? $data['province'] : '' ),
            'legal_text'     => wp_kses_post( isset( $data['legal_text'] ) ? $data['legal_text'] : '' ),
            'accept_privacy' => ! empty( $data['accept_privacy'] ),
            'accept_news'    => ! empty( $data['accept_news'] ),
        ];

        $payload = wp_json_encode( $sanitized_payload );

        $inserted = $wpdb->insert(
            $table,
            [
                'start_date'  => $start,
                'end_date'    => $end,
                'nights'      => $sanitized_payload['nights'],
                'price_total' => $sanitized_payload['price_total'],
                'status'      => self::STATUS_PENDING,
                'payload'     => $payload,
            ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_error', __( 'No se pudo crear la solicitud.', 'casatrinidad-vacation-rental' ) );
        }

        $request_id = $wpdb->insert_id;

        do_action( 'ctvr_request_created', $request_id, $data );

        return $request_id;
    }

    /**
     * Approve request.
     *
     * @param int $request_id Request ID.
     * @return int|WP_Error Reservation ID.
     */
    public static function approve_request( $request_id ) {
        global $wpdb;

        $requests_table     = $wpdb->prefix . 'ctvr_requests';
        $reservations_table = $wpdb->prefix . 'ctvr_reservations';

        $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $requests_table WHERE id = %d", $request_id ) );
        if ( ! $request ) {
            return new WP_Error( 'not_found', __( 'Solicitud no encontrada.', 'casatrinidad-vacation-rental' ) );
        }

        $wpdb->update( $requests_table, [ 'status' => self::STATUS_APPROVED ], [ 'id' => $request_id ] );

        $payload = json_decode( $request->payload, true );
        $token   = wp_generate_password( 32, false, false );

        $wpdb->insert(
            $reservations_table,
            [
                'request_id'  => $request_id,
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'price_total' => $request->price_total,
                'payload'     => $request->payload,
                'public_token'=> $token,
            ]
        );

        $reservation_id = $wpdb->insert_id;

        if ( isset( $payload['start_date'], $payload['end_date'] ) ) {
            CTVR_Availability::update_range( $payload['start_date'], $payload['end_date'], CTVR_Availability::STATUS_BLOCKED, null );
        }

        CTVR_Cleaning::ensure_workorder( $reservation_id );

        do_action( 'ctvr_request_approved', $reservation_id, $payload );

        return $reservation_id;
    }

    /**
     * Reject request.
     *
     * @param int $request_id Request ID.
     */
    public static function reject_request( $request_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_requests';
        $wpdb->update( $table, [ 'status' => self::STATUS_REJECTED ], [ 'id' => $request_id ] );
    }

    /**
     * Delete request.
     *
     * @param int $request_id Request ID.
     */
    public static function delete_request( $request_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_requests';
        $wpdb->delete( $table, [ 'id' => $request_id ] );
    }
}
