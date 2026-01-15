<?php
/**
 * Cron tasks for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register custom cron schedules.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['ai_suite_48h'] ) ) {
        $schedules['ai_suite_48h'] = array(
            'interval' => 48 * 3600,
            // Use Romanian text for schedule display.
            'display'  => __( 'La fiecare 48 de ore', 'ai-suite' ),
        );
    }

    // v1.8.1: worker pentru coada AI (aprox. la 2 minute)
    if ( ! isset( $schedules['ai_suite_2min'] ) ) {
        $schedules['ai_suite_2min'] = array(
            'interval' => 2 * 60,
            'display'  => __( 'La fiecare 2 minute', 'ai-suite' ),
        );
    }
    return $schedules;
} );

/**
 * Schedule the cron event on activation.
 */
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'ai_suite_cron_48h' ) ) {
        // Default to 48h schedule.
        wp_schedule_event( time() + 300, 'ai_suite_48h', 'ai_suite_cron_48h' );
    }

    // v1.8.1: schedule AI queue worker
    if ( ! wp_next_scheduled( 'ai_suite_ai_queue_tick' ) ) {
        wp_schedule_event( time() + 180, 'ai_suite_2min', 'ai_suite_ai_queue_tick' );
    }
} );

/**
 * Cron callback to run healthcheck automatically.
 */
add_action( 'ai_suite_cron_48h', function() {
    if ( ! class_exists( 'AI_Suite_Bot_Healthcheck' ) ) {
        return;
    }
    if ( class_exists( 'AI_Suite_Registry' ) && ! AI_Suite_Registry::is_enabled( 'healthcheck' ) ) {
        return;
    }
    $bot = new AI_Suite_Bot_Healthcheck();
    $bot->run( array( 'source' => 'cron' ) );
} );

/**
 * v1.8.1: Cron callback – procesează coada AI.
 */
add_action( 'ai_suite_ai_queue_tick', function() {
    if ( ! function_exists( 'ai_suite_queue_worker' ) ) {
        return;
    }
    // Run a small batch to keep requests light.
    ai_suite_queue_worker( 3 );
} );


// -------------------------
// Promo credits top-up (daily cron, runs monthly logic)
// -------------------------
if ( ! function_exists( 'ai_suite_schedule_promo_topup' ) ) {
    function ai_suite_schedule_promo_topup() {
        if ( ! wp_next_scheduled( 'ai_suite_promo_topup_daily' ) ) {
            // start in ~10 min
            wp_schedule_event( time() + 600, 'daily', 'ai_suite_promo_topup_daily' );
        }
    }
}
add_action( 'init', 'ai_suite_schedule_promo_topup', 12 );

add_action( 'ai_suite_promo_topup_daily', function() {
    // Process in small batches to avoid timeouts on large installs.
    $per    = 50;
    $cursor = absint( get_option( 'ai_suite_promo_topup_cursor', 0 ) );

    $ids = get_posts( array(
        'post_type'      => 'rmax_company',
        'posts_per_page' => $per,
        'offset'         => $cursor,
        'fields'         => 'ids',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) );

    if ( empty( $ids ) ) {
        update_option( 'ai_suite_promo_topup_cursor', 0, false );
        return;
    }

    foreach ( $ids as $company_id ) {
        if ( function_exists( 'aisuite_company_promo_topup_maybe' ) ) {
            aisuite_company_promo_topup_maybe( (int) $company_id, false );
        }
    }

    update_option( 'ai_suite_promo_topup_cursor', (int) ( $cursor + $per ), false );
} );



// -------------------------
// Billing renewal/expiry automation (daily)
// -------------------------
if ( ! function_exists( 'ai_suite_schedule_billing_daily' ) ) {
    function ai_suite_schedule_billing_daily() {
        if ( ! wp_next_scheduled( 'ai_suite_billing_daily' ) ) {
            // start in ~15 min
            wp_schedule_event( time() + 900, 'daily', 'ai_suite_billing_daily' );
        }
    }
}
add_action( 'init', 'ai_suite_schedule_billing_daily', 15 );

if ( ! function_exists( 'ai_suite_billing_process_company' ) ) {
    function ai_suite_billing_process_company( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return;

        if ( ! function_exists( 'ai_suite_subscription_get_company' ) || ! function_exists( 'ai_suite_subscription_update_latest' ) ) {
            return;
        }

        $row = ai_suite_subscription_get_company( $company_id );
        if ( empty( $row ) ) return;

        $plan_id = (string) ( $row['plan_id'] ?? 'free' );
        if ( $plan_id === 'free' ) return;

        $end = isset( $row['current_period_end'] ) ? absint( $row['current_period_end'] ) : 0;
        if ( ! $end ) return;

        $st = (string) ( $row['status'] ?? '' );
        $now = time();

        $settings = function_exists( 'ai_suite_billing_get_settings' ) ? ai_suite_billing_get_settings() : array();
        $grace_days  = isset( $settings['expiry_grace_days'] ) ? max( 0, absint( $settings['expiry_grace_days'] ) ) : 0;
        $notify_days = isset( $settings['expiry_notify_days'] ) ? max( 0, absint( $settings['expiry_notify_days'] ) ) : 0;

        // Reminder before expiry
        if ( $notify_days > 0 && $end > $now ) {
            $delta = $end - $now;
            if ( $delta <= ( DAY_IN_SECONDS * (int) $notify_days ) ) {
                $key = 'reminder_' . (string) $end;
                if ( function_exists( 'ai_suite_billing_notice_marked' ) && function_exists( 'ai_suite_billing_notice_mark' ) && ! ai_suite_billing_notice_marked( $company_id, $key ) ) {
                    $d = wp_date( 'd.m.Y', $end );
                    $subj = 'Abonamentul tău expiră în curând';
                    $msg  = '<p>Bună,</p><p>Abonamentul companiei tale expiră la <strong>' . esc_html( $d ) . '</strong>.</p>'
                          . '<p>Intră în portal → <strong>Abonament</strong> și fă upgrade/reînnoire pentru a evita întreruperi.</p>'
                          . '<p>Mulțumim,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
                    if ( function_exists( 'ai_suite_billing_send_email' ) && ai_suite_billing_send_email( $company_id, $subj, $msg ) ) {
                        ai_suite_billing_notice_mark( $company_id, $key );
                    }
                }
            }
        }

        // Expired -> grace / downgrade
        if ( $end <= $now ) {
            $grace_end = $grace_days > 0 ? ( $end + ( DAY_IN_SECONDS * (int) $grace_days ) ) : 0;

            if ( $grace_end && $now <= $grace_end ) {
                // Ensure grace status (keeps features via company_plan_id grace logic, but shows warnings).
                if ( $st !== 'grace' ) {
                    ai_suite_subscription_update_latest( $company_id, array(
                        'status' => 'grace',
                        'meta'   => array(
                            'expired_at' => $end,
                            'grace_end'  => $grace_end,
                        ),
                    ), true );
                }

                // Send expired notice once.
                $key = 'expired_' . (string) $end;
                if ( function_exists( 'ai_suite_billing_notice_marked' ) && function_exists( 'ai_suite_billing_notice_mark' ) && ! ai_suite_billing_notice_marked( $company_id, $key ) ) {
                    $d1 = wp_date( 'd.m.Y', $end );
                    $d2 = wp_date( 'd.m.Y', $grace_end );
                    $subj = 'Abonamentul a expirat – perioadă de grație activă';
                    $msg  = '<p>Bună,</p><p>Abonamentul companiei tale a expirat la <strong>' . esc_html( $d1 ) . '</strong>.</p>'
                          . '<p>Ai o perioadă de grație până la <strong>' . esc_html( $d2 ) . '</strong> pentru a reînnoi fără downgrade.</p>'
                          . '<p>Intră în portal → <strong>Abonament</strong> și finalizează plata.</p>'
                          . '<p>Mulțumim,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
                    if ( function_exists( 'ai_suite_billing_send_email' ) && ai_suite_billing_send_email( $company_id, $subj, $msg ) ) {
                        ai_suite_billing_notice_mark( $company_id, $key );
                    }
                }

                // Grace ending soon (<= 24h) – optional
                if ( ( $grace_end - $now ) <= DAY_IN_SECONDS ) {
                    $key2 = 'grace_ending_' . (string) $end;
                    if ( function_exists( 'ai_suite_billing_notice_marked' ) && function_exists( 'ai_suite_billing_notice_mark' ) && ! ai_suite_billing_notice_marked( $company_id, $key2 ) ) {
                        $d2 = wp_date( 'd.m.Y', $grace_end );
                        $subj = 'Atenție: grația expiră în curând';
                        $msg  = '<p>Bună,</p><p>Perioada de grație se încheie la <strong>' . esc_html( $d2 ) . '</strong>.</p>'
                              . '<p>Dacă nu reînnoiești până atunci, contul va fi trecut automat pe <strong>Free</strong>.</p>'
                              . '<p>Portal → <strong>Abonament</strong>.</p>'
                              . '<p>Mulțumim,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
                        if ( function_exists( 'ai_suite_billing_send_email' ) && ai_suite_billing_send_email( $company_id, $subj, $msg ) ) {
                            ai_suite_billing_notice_mark( $company_id, $key2 );
                        }
                    }
                }

                return;
            }

            // Downgrade to free after grace
            ai_suite_subscription_update_latest( $company_id, array(
                'status' => 'inactive',
                'plan_id' => 'free',
                'meta'   => array(
                    'expired_at'    => $end,
                    'downgraded_at' => $now,
                ),
            ), true );
            update_post_meta( $company_id, '_ai_suite_plan', 'free' );

            $key = 'downgraded_' . (string) $end;
            if ( function_exists( 'ai_suite_billing_notice_marked' ) && function_exists( 'ai_suite_billing_notice_mark' ) && ! ai_suite_billing_notice_marked( $company_id, $key ) ) {
                $subj = 'Abonamentul a fost trecut pe Free';
                $msg  = '<p>Bună,</p><p>Perioada de grație a expirat, iar contul a fost trecut automat pe planul <strong>Free</strong>.</p>'
                      . '<p>Poți face oricând upgrade din portal → <strong>Abonament</strong>.</p>'
                      . '<p>Mulțumim,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
                if ( function_exists( 'ai_suite_billing_send_email' ) && ai_suite_billing_send_email( $company_id, $subj, $msg ) ) {
                    ai_suite_billing_notice_mark( $company_id, $key );
                }
            }
        }
    }
}

add_action( 'ai_suite_billing_daily', function() {
    $per    = 50;
    $cursor = absint( get_option( 'ai_suite_billing_cursor', 0 ) );

    $ids = get_posts( array(
        'post_type'      => 'rmax_company',
        'posts_per_page' => $per,
        'offset'         => $cursor,
        'fields'         => 'ids',
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) );

    if ( empty( $ids ) ) {
        update_option( 'ai_suite_billing_cursor', 0, false );
        return;
    }

    foreach ( $ids as $company_id ) {
        ai_suite_billing_process_company( (int) $company_id );
    }

    update_option( 'ai_suite_billing_cursor', (int) ( $cursor + $per ), false );
} );
