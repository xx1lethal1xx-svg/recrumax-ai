<?php
/**
 * Featured / Sponsored Jobs (MVP).
 *
 * Adds admin controls (meta box) + automatic expiry + helpers for frontend display.
 *
 * Meta keys:
 *  - _rmax_featured (1/0)
 *  - _rmax_featured_until (unix timestamp, optional; if missing and featured=1 => featured indefinitely)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aisuite_is_job_featured' ) ) {
    function aisuite_is_job_featured( $job_id ) {
        $job_id = intval( $job_id );
        if ( $job_id <= 0 ) { return false; }
        $flag = get_post_meta( $job_id, '_rmax_featured', true );
        if ( '1' !== (string) $flag ) { return false; }

        $until = get_post_meta( $job_id, '_rmax_featured_until', true );
        if ( '' === $until || null === $until ) {
            return true; // indefinite
        }
        $until = intval( $until );
        if ( $until <= 0 ) { return true; }
        return $until >= time();
    }
}

if ( ! function_exists( 'aisuite_get_job_featured_until' ) ) {
    function aisuite_get_job_featured_until( $job_id ) {
        $until = get_post_meta( intval( $job_id ), '_rmax_featured_until', true );
        return intval( $until );
    }
}

if ( ! function_exists( 'aisuite_set_job_featured' ) ) {
    /**
     * @param int $job_id
     * @param bool $is_featured
     * @param int|null $until_ts Unix timestamp or null to keep/clear.
     */
    function aisuite_set_job_featured( $job_id, $is_featured, $until_ts = null ) {
        $job_id = intval( $job_id );
        if ( $job_id <= 0 ) { return; }

        update_post_meta( $job_id, '_rmax_featured', $is_featured ? '1' : '0' );

        if ( null !== $until_ts ) {
            $until_ts = intval( $until_ts );
            if ( $until_ts > 0 ) {
                update_post_meta( $job_id, '_rmax_featured_until', $until_ts );
            } else {
                delete_post_meta( $job_id, '_rmax_featured_until' );
            }
        }
    }
}

/**
 * Admin meta box: mark job as featured and set expiry date.
 */
add_action( 'add_meta_boxes', function() {
    // Only for our job CPT.
    add_meta_box(
        'aisuite_featured_job',
        __( 'Promovare job (Sponsored)', 'ai-suite' ),
        function( $post ) {
            if ( ! $post || 'rmax_job' !== $post->post_type ) { return; }

            $flag  = get_post_meta( $post->ID, '_rmax_featured', true );
            $until = get_post_meta( $post->ID, '_rmax_featured_until', true );

            $checked = ( '1' === (string) $flag ) ? 'checked' : '';
            $date_val = '';
            if ( $until ) {
                $ts = intval( $until );
                if ( $ts > 0 ) {
                    $date_val = gmdate( 'Y-m-d', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
                }
            }

            wp_nonce_field( 'aisuite_featured_job_save', 'aisuite_featured_job_nonce' );

            echo '<p style="margin:0 0 10px 0;">';
            echo '<label style="display:flex;gap:10px;align-items:center;">';
            echo '<input type="checkbox" name="aisuite_featured_job" value="1" ' . esc_attr( $checked ) . ' />';
            echo '<strong>' . esc_html__( 'Marchează jobul ca Promovat (Featured)', 'ai-suite' ) . '</strong>';
            echo '</label>';
            echo '</p>';

            echo '<p style="margin:0 0 10px 0;">';
            echo '<label style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Expiră la (opțional)', 'ai-suite' ) . '</label>';
            echo '<input type="date" name="aisuite_featured_until" value="' . esc_attr( $date_val ) . '" />';
            echo '<span style="margin-left:8px;color:#666;">' . esc_html__( 'Dacă e gol, rămâne promovat nelimitat.', 'ai-suite' ) . '</span>';
            echo '</p>';

            echo '<p style="margin:0;color:#555;">' . esc_html__( 'Notă: joburile promovate apar într-o secțiune separată sus și nu se dublează în listă.', 'ai-suite' ) . '</p>';
        },
        'rmax_job',
        'side',
        'high'
    );
} );

add_action( 'save_post_rmax_job', function( $post_id ) {
    // Basic guards.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( wp_is_post_revision( $post_id ) ) { return; }
    if ( ! isset( $_POST['aisuite_featured_job_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aisuite_featured_job_nonce'] ) ), 'aisuite_featured_job_save' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    $is_featured = isset( $_POST['aisuite_featured_job'] ) && '1' === (string) wp_unslash( $_POST['aisuite_featured_job'] );

    $date = isset( $_POST['aisuite_featured_until'] ) ? sanitize_text_field( wp_unslash( $_POST['aisuite_featured_until'] ) ) : '';
    $until_ts = 0;
    if ( $date ) {
        // Convert local date to timestamp at end of day (23:59:59) in site timezone.
        $tz_string = get_option( 'timezone_string' );
        try {
            $tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
        } catch ( Exception $e ) {
            $tz = new DateTimeZone( 'UTC' );
        }
        try {
            $dt = new DateTime( $date . ' 23:59:59', $tz );
            $until_ts = $dt->getTimestamp();
        } catch ( Exception $e ) {
            $until_ts = 0;
        }
    }

    update_post_meta( $post_id, '_rmax_featured', $is_featured ? '1' : '0' );

    if ( $date ) {
        if ( $until_ts > 0 ) {
            update_post_meta( $post_id, '_rmax_featured_until', intval( $until_ts ) );
        } else {
            delete_post_meta( $post_id, '_rmax_featured_until' );
        }
    } else {
        // Empty = indefinite.
        delete_post_meta( $post_id, '_rmax_featured_until' );
    }
}, 10, 1 );

/**
 * Auto-expire featured jobs (runs daily).
 */
if ( ! function_exists( 'aisuite_featured_jobs_schedule' ) ) {
    function aisuite_featured_jobs_schedule() {
        if ( ! wp_next_scheduled( 'aisuite_featured_jobs_cleanup' ) ) {
            wp_schedule_event( time() + 300, 'daily', 'aisuite_featured_jobs_cleanup' );
        }
    }
}
add_action( 'init', 'aisuite_featured_jobs_schedule' );

add_action( 'aisuite_featured_jobs_cleanup', function() {
    $now = time();

    $q = new WP_Query( array(
        'post_type'      => 'rmax_job',
        'posts_per_page' => 200,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_rmax_featured',
                'value'   => '1',
                'compare' => '=',
            ),
            array(
                'key'     => '_rmax_featured_until',
                'value'   => $now,
                'type'    => 'NUMERIC',
                'compare' => '<',
            ),
        ),
    ) );

    if ( $q->have_posts() ) {
        foreach ( $q->posts as $job_id ) {
            // Expire it.
            update_post_meta( $job_id, '_rmax_featured', '0' );
        }
    }
} );
