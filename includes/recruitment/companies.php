<?php
/**
 * AI Suite – Companii + Portal Client (v1.7.7)
 *
 * Obiectiv: fundație enterprise pentru managementul clienților/companiilor,
 * asocierea joburilor și un portal intern (admin) pentru a vedea aplicațiile pe companie.
 *
 * ADD-ONLY: nu modificăm structuri existente; adăugăm capabilități noi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// -------------------------
// CPT: rmax_company
// -------------------------

if ( ! function_exists( 'aisuite_register_company_cpt' ) ) {
    function aisuite_register_company_cpt() {
        $labels = array(
            'name'               => __( 'Companii', 'ai-suite' ),
            'singular_name'      => __( 'Companie', 'ai-suite' ),
            'add_new'            => __( 'Adaugă companie', 'ai-suite' ),
            'add_new_item'       => __( 'Adaugă companie', 'ai-suite' ),
            'edit_item'          => __( 'Editează companie', 'ai-suite' ),
            'new_item'           => __( 'Companie nouă', 'ai-suite' ),
            'view_item'          => __( 'Vezi companie', 'ai-suite' ),
            'search_items'       => __( 'Caută companii', 'ai-suite' ),
            'not_found'          => __( 'Nu s-au găsit companii.', 'ai-suite' ),
            'not_found_in_trash' => __( 'Nu s-au găsit companii în coș.', 'ai-suite' ),
        );

        register_post_type( 'rmax_company', array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // folosim tabul AI Suite
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'show_in_rest'        => false,
        ) );
    }
    add_action( 'init', 'aisuite_register_company_cpt' );
}

// -------------------------
// Tabs: Companii + Portal Client
// -------------------------

if ( ! function_exists( 'aisuite_register_company_tabs' ) ) {
    function aisuite_register_company_tabs( $tabs ) {
        if ( ! is_array( $tabs ) ) {
            $tabs = array();
        }

        // Inserăm după "applications" dacă există.
        $out = array();
        foreach ( $tabs as $k => $v ) {
            $out[ $k ] = $v;
            if ( 'applications' === $k ) {
                $out['companies'] = array(
                    'label' => __( 'Companii', 'ai-suite' ),
                    'view'  => 'tab-companies.php',
                );
                $out['portal'] = array(
                    'label' => __( 'Portal client', 'ai-suite' ),
                    'view'  => 'tab-portal.php',
                );
            }
        }

        // Dacă "applications" lipsește, le adăugăm la final.
        if ( ! isset( $out['companies'] ) ) {
            $out['companies'] = array( 'label' => __( 'Companii', 'ai-suite' ), 'view' => 'tab-companies.php' );
        }
        if ( ! isset( $out['portal'] ) ) {
            $out['portal'] = array( 'label' => __( 'Portal client', 'ai-suite' ), 'view' => 'tab-portal.php' );
        }

        return $out;
    }
    add_filter( 'ai_suite_tabs', 'aisuite_register_company_tabs', 30 );
}

// -------------------------
// Helpers (meta canonice)
// -------------------------

if ( ! function_exists( 'aisuite_company_get_meta' ) ) {
    function aisuite_company_get_meta( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) {
            return array();
        }

        $email = (string) get_post_meta( $company_id, '_company_contact_email', true );
        $team  = get_post_meta( $company_id, '_company_team_emails', true );
        if ( ! is_array( $team ) ) {
            $team = array();
        }
        $max_team = (int) get_post_meta( $company_id, '_company_max_team', true );
        if ( $max_team < 1 ) {
            $max_team = 3;
        }
        if ( $max_team > 3 ) {
            $max_team = 3;
        }

        $promo_credits = (int) get_post_meta( $company_id, '_company_promo_credits', true );
        if ( $promo_credits < 0 ) { $promo_credits = 0; }


        return array(
            'email'    => sanitize_email( $email ),
            'team'     => array_values( array_filter( array_map( 'sanitize_email', $team ) ) ),
            'max_team' => $max_team,
            'promo_credits' => $promo_credits,
        );
    }
}

if ( ! function_exists( 'aisuite_company_set_meta' ) ) {
    function aisuite_company_set_meta( $company_id, array $data ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) {
            return;
        }
        $email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
        $team  = isset( $data['team'] ) ? (array) $data['team'] : array();
        $team  = array_values( array_unique( array_filter( array_map( 'sanitize_email', $team ) ) ) );
        $max_team = isset( $data['max_team'] ) ? absint( $data['max_team'] ) : 3;
        $promo_credits = isset( $data['promo_credits'] ) ? intval( $data['promo_credits'] ) : (int) get_post_meta( $company_id, '_company_promo_credits', true );
        if ( $promo_credits < 0 ) { $promo_credits = 0; }
        if ( $max_team < 1 ) {
            $max_team = 1;
        }
        if ( $max_team > 3 ) {
            $max_team = 3;
        }
        update_post_meta( $company_id, '_company_contact_email', $email );
        update_post_meta( $company_id, '_company_team_emails', $team );
        update_post_meta( $company_id, '_company_max_team', $max_team );
        update_post_meta( $company_id, '_company_promo_credits', $promo_credits );
    }
}

if ( ! function_exists( 'aisuite_company_get_job_ids' ) ) {
    function aisuite_company_get_job_ids( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) {
            return array();
        }
        $jobs = get_posts( array(
            'post_type'      => 'rmax_job',
            'posts_per_page' => 200,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_job_company_id',
                    'value' => (string) $company_id,
                ),
            ),
        ) );
        return array_map( 'absint', (array) $jobs );
    }
}

if ( ! function_exists( 'aisuite_company_attach_jobs' ) ) {
    function aisuite_company_attach_jobs( $company_id, array $job_ids ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) {
            return;
        }
        $job_ids = array_values( array_filter( array_map( 'absint', $job_ids ) ) );

        // Atașăm joburile selectate.
        foreach ( $job_ids as $jid ) {
            if ( 'rmax_job' !== get_post_type( $jid ) ) {
                continue;
            }
            update_post_meta( $jid, '_job_company_id', (string) $company_id );
        }
    }
}

// -------------------------
// Admin-post handlers
// -------------------------

// Salvează meta companie (email + team + max team).
add_action( 'admin_post_ai_suite_company_save', function() {
    if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    if ( ! $company_id || 'rmax_company' !== get_post_type( $company_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies&notice=error' ) );
        exit;
    }
    check_admin_referer( 'ai_suite_company_save_' . $company_id );

    $email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
    $max_team = isset( $_POST['max_team'] ) ? absint( wp_unslash( $_POST['max_team'] ) ) : 3;
    $promo_credits = isset( $_POST['promo_credits'] ) ? intval( wp_unslash( $_POST['promo_credits'] ) ) : 0;
    if ( $promo_credits < 0 ) { $promo_credits = 0; }
    $team_raw = isset( $_POST['team_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['team_emails'] ) ) : '';
    $team = array();
    if ( $team_raw ) {
        foreach ( explode( ',', $team_raw ) as $t ) {
            $t = trim( $t );
            if ( $t !== '' ) {
                $team[] = sanitize_email( $t );
            }
        }
    }
    aisuite_company_set_meta( $company_id, array(
        'email'    => $email,
        'team'     => $team,
        'max_team' => $max_team,
        'promo_credits' => $promo_credits,
    ) );

    // Billing / Buyer details (Patch48)
    $billing_name    = isset( $_POST['billing_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_name'] ) ) : '';
    $billing_cui     = isset( $_POST['billing_cui'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cui'] ) ) : '';
    $billing_reg     = isset( $_POST['billing_reg'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_reg'] ) ) : '';
    $billing_address = isset( $_POST['billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address'] ) ) : '';
    $billing_city    = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';
    $billing_country = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';
    $billing_email   = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
    $billing_phone   = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
    $billing_contact = isset( $_POST['billing_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_contact'] ) ) : '';
    $billing_vat     = ! empty( $_POST['billing_vat'] ) ? 1 : 0;

    // Save only if any field provided (avoid overwriting old data with empties)
    if ( $billing_name !== '' || $billing_cui !== '' || $billing_reg !== '' || $billing_address !== '' || $billing_city !== '' || $billing_country !== '' || $billing_email !== '' || $billing_phone !== '' || $billing_contact !== '' ) {
        update_post_meta( $company_id, '_company_billing_name', $billing_name );
        update_post_meta( $company_id, '_company_billing_cui', $billing_cui );
        update_post_meta( $company_id, '_company_billing_reg', $billing_reg );
        update_post_meta( $company_id, '_company_billing_address', $billing_address );
        update_post_meta( $company_id, '_company_billing_city', $billing_city );
        update_post_meta( $company_id, '_company_billing_country', $billing_country );
        update_post_meta( $company_id, '_company_billing_email', $billing_email );
        update_post_meta( $company_id, '_company_billing_phone', $billing_phone );
        update_post_meta( $company_id, '_company_billing_contact', $billing_contact );
        update_post_meta( $company_id, '_company_billing_vat', $billing_vat );
    }


    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', __( 'Companie actualizată', 'ai-suite' ), array( 'company_id' => $company_id ) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies&notice=saved#company-' . $company_id ) );
    exit;
} );

// Atașează joburi la companie.
add_action( 'admin_post_ai_suite_company_attach_jobs', function() {
    if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    if ( ! $company_id || 'rmax_company' !== get_post_type( $company_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies&notice=error' ) );
        exit;
    }
    check_admin_referer( 'ai_suite_attach_jobs_' . $company_id );
    $job_ids = isset( $_POST['job_ids'] ) ? (array) wp_unslash( $_POST['job_ids'] ) : array();
    aisuite_company_attach_jobs( $company_id, $job_ids );

    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', __( 'Joburi atașate companiei', 'ai-suite' ), array( 'company_id' => $company_id, 'jobs' => array_map( 'absint', $job_ids ) ) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies&notice=jobs_attached#company-' . $company_id ) );
    exit;
} );


// -------------------------
// Promo Credits (auto top-up lunar)
// -------------------------

if ( ! function_exists( 'aisuite_company_promo_monthly_allowance' ) ) {
    /**
     * Câte credite de promovare primește compania lunar (în funcție de plan).
     * IMPORTANT: Nu scade creditele existente; doar definește minimul lunar.
     */
    function aisuite_company_promo_monthly_allowance( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) { return 0; }

        // Preferred: din plan (features).
        if ( function_exists( 'ai_suite_company_limit' ) ) {
            $v = (int) ai_suite_company_limit( $company_id, 'promo_credits_monthly', 0 );
            if ( $v < 0 ) { $v = 0; }
            return $v;
        }

        // Fallback: pe baza meta plan.
        $plan_id = (string) get_post_meta( $company_id, '_ai_suite_plan', true );
        $plan_id = $plan_id ? sanitize_key( $plan_id ) : 'free';

        switch ( $plan_id ) {
            case 'enterprise': return 10;
            case 'pro':        return 2;
            default:           return 0;
        }
    }
}

if ( ! function_exists( 'aisuite_company_promo_topup_maybe' ) ) {
    /**
     * Reîncarcă automat creditele de promovare (o dată pe lună) până la allowance.
     * - Dacă firma are deja mai multe credite, NU le modifică.
     * - Dacă allowance = 0, marchează luna ca procesată (ca să nu scriem inutil în loop).
     *
     * @return bool True dacă a rulat pentru luna curentă (sau force), false dacă nu era nevoie.
     */
    function aisuite_company_promo_topup_maybe( $company_id, $force = false ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) { return false; }

        $now = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
        $ym  = function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m', $now ) : (string) date( 'Y-m', $now );

        $last = (string) get_post_meta( $company_id, '_company_promo_last_topup_ym', true );
        $last = sanitize_text_field( $last );

        if ( ! $force && $last === $ym ) {
            return false;
        }

        $allow = (int) ( function_exists( 'aisuite_company_promo_monthly_allowance' ) ? aisuite_company_promo_monthly_allowance( $company_id ) : 0 );
        if ( $allow < 0 ) { $allow = 0; }

        $credits = (int) get_post_meta( $company_id, '_company_promo_credits', true );
        if ( $credits < 0 ) { $credits = 0; }

        // Top-up only if below allowance.
        if ( $allow > 0 && $credits < $allow ) {
            update_post_meta( $company_id, '_company_promo_credits', (int) $allow );
            $credits = $allow;
        }

        update_post_meta( $company_id, '_company_promo_last_topup_ym', $ym );

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', __( 'Promo credits top-up lunar', 'ai-suite' ), array(
                'company_id' => $company_id,
                'month'      => $ym,
                'allowance'  => $allow,
                'credits'    => (int) $credits,
                'force'      => $force ? 1 : 0,
            ) );
        }

        return true;
    }
}

