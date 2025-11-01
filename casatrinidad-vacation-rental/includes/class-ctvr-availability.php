<?php
/**
 * Availability and pricing helpers.
 *
 * @package CasaTrinidadVacationRental
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CTVR_Availability {
    const STATUS_AVAILABLE = 'available';
    const STATUS_BLOCKED   = 'blocked';
    const STATUS_UNPRICED  = 'unpriced';

    /**
     * Init hooks.
     */
    public static function init() {
        add_action( 'ctvr_update_availability', [ __CLASS__, 'update_range' ], 10, 4 );
    }

    /**
     * Fetch availability for given month.
     *
     * @param int $year  Year.
     * @param int $month Month.
     * @return array
     */
    public static function get_month( $year, $month ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_availability';

        $start = sprintf( '%04d-%02d-01', $year, $month );
        $end   = date( 'Y-m-t', strtotime( $start ) );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE day BETWEEN %s AND %s", $start, $end ), ARRAY_A );
        $map  = [];
        foreach ( $rows as $row ) {
            $map[ $row['day'] ] = $row;
        }

        $days = [];
        $date = new DateTime( $start );
        while ( $date->format( 'Y-m-d' ) <= $end ) {
            $day_key = $date->format( 'Y-m-d' );
            $days[]  = array_merge(
                [
                    'day'    => $day_key,
                    'status' => self::STATUS_UNPRICED,
                    'price'  => null,
                ],
                isset( $map[ $day_key ] ) ? $map[ $day_key ] : []
            );
            $date->modify( '+1 day' );
        }

        return $days;
    }

    /**
     * Update range with price or status.
     *
     * @param string $start  Start date.
     * @param string $end    End date.
     * @param string $status Status to assign.
     * @param float  $price  Price to set.
     */
    public static function update_range( $start, $end, $status = self::STATUS_AVAILABLE, $price = null ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ctvr_availability';
        $start = date( 'Y-m-d', strtotime( $start ) );
        $end   = date( 'Y-m-d', strtotime( $end ) );

        $date = new DateTime( $start );
        while ( $date->format( 'Y-m-d' ) <= $end ) {
            $current = $date->format( 'Y-m-d' );

            $data = [
                'day'    => $current,
                'status' => $status,
                'price'  => null !== $price ? floatval( $price ) : null,
            ];

            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE day = %s", $current ) );

            if ( $existing ) {
                $wpdb->update( $table, $data, [ 'id' => $existing ] );
            } else {
                $wpdb->insert( $table, $data );
            }

            $date->modify( '+1 day' );
        }
    }

    /**
     * Calculate total price for range.
     *
     * @param string $start Start date.
     * @param string $end   End date.
     * @return array
     */
    public static function calculate_range_price( $start, $end ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ctvr_availability';

        $start = date( 'Y-m-d', strtotime( $start ) );
        $end   = date( 'Y-m-d', strtotime( $end ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT day, status, price FROM $table WHERE day BETWEEN %s AND %s",
                $start,
                $end
            ),
            ARRAY_A
        );

        $map = [];
        foreach ( $rows as $row ) {
            $map[ $row['day'] ] = $row;
        }

        $total = 0;
        $valid = true;

        $date = new DateTime( $start );
        while ( $date->format( 'Y-m-d' ) <= $end ) {
            $day_key = $date->format( 'Y-m-d' );
            if ( ! isset( $map[ $day_key ] ) ) {
                $valid = false;
                break;
            }
            if ( self::STATUS_BLOCKED === $map[ $day_key ]['status'] || null === $map[ $day_key ]['price'] ) {
                $valid = false;
                break;
            }
            $total += floatval( $map[ $day_key ]['price'] );
            $date->modify( '+1 day' );
        }

        return [
            'valid' => $valid,
            'total' => $valid ? $total : 0,
        ];
    }
}
