<?php
/**
 * REST API endpoints.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_REST {
    /**
     * Register routes.
     */
    public static function register_routes() {
        register_rest_route(
            'ctvr/v1',
            '/calendar',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_calendar' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'year'  => [ 'required' => true, 'validate_callback' => 'is_numeric' ],
                    'month' => [ 'required' => true, 'validate_callback' => 'is_numeric' ],
                ],
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/range-price',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_range_price' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'start' => [ 'required' => true ],
                    'end'   => [ 'required' => true ],
                ],
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/request',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_request' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/availability',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'update_availability' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/event',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'log_event_endpoint' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/requests',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_requests' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/requests/(?P<id>\\d+)/approve',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'approve_request' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/requests/(?P<id>\\d+)/reject',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'reject_request' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/requests/(?P<id>\\d+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ __CLASS__, 'delete_request' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/reservations',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_reservations' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/reservations/(?P<id>\\d+)/workorder',
            [
                'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ],
                'callback'            => [ __CLASS__, 'handle_workorder_admin' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/checklists',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_checklists' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/checklists',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'save_checklist' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/checklists/(?P<id>\\d+)',
            [
                'methods'             => [ WP_REST_Server::CREATABLE, WP_REST_Server::DELETABLE ],
                'callback'            => [ __CLASS__, 'handle_checklist_item' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/stats',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_stats' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ]
        );

        register_rest_route(
            'ctvr/v1',
            '/workorder/(?P<token>[A-Za-z0-9]+)',
            [
                'methods'             => [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ],
                'callback'            => [ __CLASS__, 'handle_public_workorder' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Return calendar data.
     */
    public static function get_calendar( WP_REST_Request $request ) {
        $year  = intval( $request->get_param( 'year' ) );
        $month = intval( $request->get_param( 'month' ) );

        return rest_ensure_response(
            [
                'days' => CTVR_Availability::get_month( $year, $month ),
            ]
        );
    }

    /**
     * Get price for range.
     */
    public static function get_range_price( WP_REST_Request $request ) {
        $start = sanitize_text_field( $request->get_param( 'start' ) );
        $end   = sanitize_text_field( $request->get_param( 'end' ) );

        return rest_ensure_response( CTVR_Availability::calculate_range_price( $start, $end ) );
    }

    /**
     * Handle reservation request creation.
     */
    public static function create_request( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $required = [ 'start_date', 'end_date', 'nights', 'name', 'surname', 'email', 'phone', 'people' ];

        foreach ( $required as $field ) {
            if ( empty( $params[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf( __( 'Campo obligatorio: %s', 'casatrinidad-vacation-rental' ), $field ), [ 'status' => 400 ] );
            }
        }

        $email = sanitize_email( $params['email'] );
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'El correo electrónico no es válido.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }
        $params['email'] = $email;

        $people = absint( $params['people'] );
        if ( $people < 1 ) {
            return new WP_Error( 'invalid_people', __( 'El número de personas debe ser al menos 1.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }
        $params['people'] = $people;
        $params['ages']   = array_map( 'absint', isset( $params['ages'] ) ? (array) $params['ages'] : [] );

        $params['name']    = sanitize_text_field( $params['name'] );
        $params['surname'] = sanitize_text_field( $params['surname'] );
        $params['phone']   = sanitize_text_field( $params['phone'] );
        if ( isset( $params['nationality'] ) ) {
            $params['nationality'] = sanitize_text_field( $params['nationality'] );
        }
        if ( isset( $params['province'] ) ) {
            $params['province'] = sanitize_text_field( $params['province'] );
        }
        if ( isset( $params['legal_text'] ) ) {
            $params['legal_text'] = wp_kses_post( $params['legal_text'] );
        }
        $params['accept_privacy'] = ! empty( $params['accept_privacy'] );
        $params['accept_news']    = ! empty( $params['accept_news'] );

        $start = strtotime( $params['start_date'] );
        $end   = strtotime( $params['end_date'] );
        if ( ! $start || ! $end || $end < $start ) {
            return new WP_Error( 'invalid_dates', __( 'Fechas inválidas.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }

        $nights = max( 1, (int) round( ( $end - $start ) / DAY_IN_SECONDS ) );
        if ( $nights < 1 ) {
            $nights = 1;
        }
        $params['nights'] = $nights;

        $settings  = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        $min_nights = isset( $settings['min_nights'] ) ? absint( $settings['min_nights'] ) : 1;

        if ( intval( $params['nights'] ) < $min_nights ) {
            return new WP_Error( 'min_nights', __( 'La estancia seleccionada no alcanza el mínimo configurado.', 'casatrinidad-vacation-rental' ), [ 'status' => 400, 'data' => [ 'min_nights' => $min_nights ] ] );
        }

        $calculated = CTVR_Availability::calculate_range_price( $params['start_date'], $params['end_date'] );
        if ( ! $calculated['valid'] ) {
            return new WP_Error( 'invalid_range', __( 'El rango seleccionado no está disponible o falta precio.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }

        $params['price_total'] = $calculated['total'];

        $request_id = CTVR_Reservations::create_request( $params );
        if ( is_wp_error( $request_id ) ) {
            return $request_id;
        }

        self::notify_new_request( $request_id, $params );
        self::log_event( 'form_submission', [ 'request_id' => $request_id, 'start_date' => $params['start_date'], 'end_date' => $params['end_date'] ] );

        return rest_ensure_response( [ 'success' => true, 'request_id' => $request_id ] );
    }

    /**
     * Update availability from admin.
     */
    public static function update_availability( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $start  = sanitize_text_field( $params['start'] );
        $end    = sanitize_text_field( $params['end'] );
        $status = sanitize_text_field( $params['status'] );
        $price  = isset( $params['price'] ) ? floatval( $params['price'] ) : null;

        if ( strtotime( $start ) === false || strtotime( $end ) === false ) {
            return new WP_Error( 'invalid_dates', __( 'Fechas inválidas.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }

        if ( strtotime( $end ) < strtotime( $start ) ) {
            return new WP_Error( 'invalid_range', __( 'La fecha de fin debe ser posterior al inicio.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }

        CTVR_Availability::update_range( $start, $end, $status, $price );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Log event endpoint.
     */
    public static function log_event_endpoint( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        if ( empty( $params['event'] ) ) {
            return new WP_Error( 'missing_event', __( 'Evento no especificado.', 'casatrinidad-vacation-rental' ), [ 'status' => 400 ] );
        }

        self::log_event( sanitize_text_field( $params['event'] ), isset( $params['payload'] ) ? (array) $params['payload'] : [] );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Return requests list.
     */
    public static function get_requests() {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_requests';
        $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

        $requests = array_map( [ __CLASS__, 'format_request_row' ], $rows );

        return rest_ensure_response( [ 'requests' => $requests ] );
    }

    /**
     * Approve request endpoint.
     */
    public static function approve_request( WP_REST_Request $request ) {
        $request_id = intval( $request['id'] );
        $result     = CTVR_Reservations::approve_request( $request_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $reservation = self::format_reservation_row_by_id( $result );

        return rest_ensure_response( [ 'reservation' => $reservation ] );
    }

    /**
     * Reject request endpoint.
     */
    public static function reject_request( WP_REST_Request $request ) {
        CTVR_Reservations::reject_request( intval( $request['id'] ) );
        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Delete request endpoint.
     */
    public static function delete_request( WP_REST_Request $request ) {
        CTVR_Reservations::delete_request( intval( $request['id'] ) );
        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Return reservations list.
     */
    public static function get_reservations() {
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'ctvr_reservations';
        $rows               = $wpdb->get_results( "SELECT * FROM $reservations_table ORDER BY start_date DESC", ARRAY_A );

        $reservations = array_map( [ __CLASS__, 'format_reservation_row' ], $rows );

        return rest_ensure_response( [ 'reservations' => $reservations ] );
    }

    /**
     * Handle admin workorder (GET/POST).
     */
    public static function handle_workorder_admin( WP_REST_Request $request ) {
        $reservation_id = intval( $request['id'] );

        if ( 'GET' === $request->get_method() ) {
            $workorder = CTVR_Cleaning::get_workorder_by_reservation( $reservation_id );
            if ( ! $workorder ) {
                return new WP_Error( 'not_found', __( 'Reserva no encontrada.', 'casatrinidad-vacation-rental' ), [ 'status' => 404 ] );
            }

            return rest_ensure_response( [ 'workorder' => $workorder ] );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        CTVR_Cleaning::update_workorder( $reservation_id, $params );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Get checklist items.
     */
    public static function get_checklists() {
        return rest_ensure_response( [ 'checklists' => CTVR_Cleaning::get_checklists( true ) ] );
    }

    /**
     * Save checklist item (create).
     */
    public static function save_checklist( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $id = CTVR_Cleaning::save_checklist( $params );

        return rest_ensure_response( [ 'id' => $id ] );
    }

    /**
     * Update or delete checklist item.
     */
    public static function handle_checklist_item( WP_REST_Request $request ) {
        $id = intval( $request['id'] );

        if ( $request->get_method() === WP_REST_Server::DELETABLE ) {
            CTVR_Cleaning::delete_checklist( $id );
            return rest_ensure_response( [ 'success' => true ] );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        $params['id'] = $id;
        CTVR_Cleaning::save_checklist( $params );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Gather statistics.
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_stats';

        $totals = $wpdb->get_results( "SELECT event, COUNT(*) as total FROM $table GROUP BY event", ARRAY_A );
        $by_event = [];
        foreach ( $totals as $row ) {
            $by_event[ $row['event'] ] = intval( $row['total'] );
        }

        $top_ranges = $wpdb->get_results( $wpdb->prepare( "SELECT payload FROM $table WHERE event = %s", 'date_range_selected' ), ARRAY_A );
        $range_count = [];
        foreach ( $top_ranges as $row ) {
            $payload = json_decode( $row['payload'], true );
            if ( empty( $payload['start'] ) || empty( $payload['end'] ) ) {
                continue;
            }
            $key = $payload['start'] . ' → ' . $payload['end'];
            if ( ! isset( $range_count[ $key ] ) ) {
                $range_count[ $key ] = 0;
            }
            $range_count[ $key ]++;
        }
        arsort( $range_count );
        $range_count = array_slice( $range_count, 0, 5, true );

        $popular_days = $wpdb->get_results( $wpdb->prepare( "SELECT payload FROM $table WHERE event = %s", 'form_submission' ), ARRAY_A );
        $day_count    = [];
        foreach ( $popular_days as $row ) {
            $payload = json_decode( $row['payload'], true );
            if ( empty( $payload['start_date'] ) ) {
                continue;
            }
            if ( ! isset( $day_count[ $payload['start_date'] ] ) ) {
                $day_count[ $payload['start_date'] ] = 0;
            }
            $day_count[ $payload['start_date'] ]++;
        }
        arsort( $day_count );
        $day_count = array_slice( $day_count, 0, 5, true );

        return rest_ensure_response(
            [
                'events'      => $by_event,
                'top_ranges'  => $range_count,
                'top_entries' => $day_count,
            ]
        );
    }

    /**
     * Handle public workorder endpoints.
     */
    public static function handle_public_workorder( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request['token'] );

        if ( 'GET' === $request->get_method() ) {
            $workorder = CTVR_Cleaning::get_workorder_by_token( $token );
            if ( ! $workorder ) {
                return new WP_Error( 'not_found', __( 'Reserva no encontrada.', 'casatrinidad-vacation-rental' ), [ 'status' => 404 ] );
            }

            return rest_ensure_response( [ 'workorder' => $workorder ] );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        global $wpdb;
        $reservations_table = $wpdb->prefix . 'ctvr_reservations';
        $reservation_id     = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $reservations_table WHERE public_token = %s", $token ) );

        if ( ! $reservation_id ) {
            return new WP_Error( 'not_found', __( 'Reserva no encontrada.', 'casatrinidad-vacation-rental' ), [ 'status' => 404 ] );
        }

        CTVR_Cleaning::update_workorder( intval( $reservation_id ), $params );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Format request row.
     */
    protected static function format_request_row( $row ) {
        $payload = json_decode( $row['payload'], true );

        return [
            'id'          => intval( $row['id'] ),
            'start_date'  => $row['start_date'],
            'end_date'    => $row['end_date'],
            'nights'      => intval( $row['nights'] ),
            'price_total' => $row['price_total'],
            'status'      => $row['status'],
            'created_at'  => $row['created_at'],
            'payload'     => $payload,
        ];
    }

    /**
     * Format reservation row.
     */
    protected static function format_reservation_row( $row ) {
        $payload = json_decode( $row['payload'], true );

        $workorder = CTVR_Cleaning::get_workorder_by_reservation( intval( $row['id'] ) );

        return [
            'id'           => intval( $row['id'] ),
            'request_id'   => intval( $row['request_id'] ),
            'start_date'   => $row['start_date'],
            'end_date'     => $row['end_date'],
            'price_total'  => $row['price_total'],
            'payload'      => $payload,
            'public_token' => $row['public_token'],
            'workorder'    => $workorder,
        ];
    }

    /**
     * Helper to format reservation by ID.
     */
    protected static function format_reservation_row_by_id( $reservation_id ) {
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'ctvr_reservations';
        $row                = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $reservations_table WHERE id = %d", $reservation_id ), ARRAY_A );
        if ( ! $row ) {
            return null;
        }

        return self::format_reservation_row( $row );
    }

    /**
     * Send notification to managers.
     */
    protected static function notify_new_request( $request_id, $data ) {
        $settings = get_option( CTVR_Plugin::SETTINGS_OPTION, [] );
        if ( empty( $settings['notify_emails'] ) ) {
            return;
        }

        $emails = array_map( 'trim', explode( ',', $settings['notify_emails'] ) );
        $emails = array_filter( $emails, 'is_email' );

        if ( empty( $emails ) ) {
            return;
        }

        $subject = sprintf( __( 'Nueva solicitud de reserva #%d', 'casatrinidad-vacation-rental' ), $request_id );

        $lines = [
            __( 'Se ha recibido una nueva solicitud de reserva:', 'casatrinidad-vacation-rental' ),
            sprintf( __( 'Entrada: %s', 'casatrinidad-vacation-rental' ), $data['start_date'] ),
            sprintf( __( 'Salida: %s', 'casatrinidad-vacation-rental' ), $data['end_date'] ),
            sprintf( __( 'Noches: %d', 'casatrinidad-vacation-rental' ), $data['nights'] ),
            sprintf( __( 'Importe estimado: %s €', 'casatrinidad-vacation-rental' ), number_format_i18n( $data['price_total'], 2 ) ),
            sprintf( __( 'Nombre: %s %s', 'casatrinidad-vacation-rental' ), $data['name'], $data['surname'] ),
            sprintf( __( 'Email: %s', 'casatrinidad-vacation-rental' ), $data['email'] ),
            sprintf( __( 'Teléfono: %s', 'casatrinidad-vacation-rental' ), $data['phone'] ),
        ];

        $body = implode( "\n", $lines );

        wp_mail( $emails, $subject, $body );
    }

    /**
     * Store stat event.
     */
    public static function log_event( $event, $payload = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_stats';
        $wpdb->insert(
            $table,
            [
                'event'   => $event,
                'payload' => wp_json_encode( $payload ),
            ]
        );
    }
}
