<?php
/**
 * Admin post actions for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Save plugin settings.
add_action( 'admin_post_ai_suite_save_settings', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_save_settings' );
    $settings = aisuite_get_settings();
    $openai   = isset( $_POST['openai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_api_key'] ) ) : '';
    $settings['openai_api_key'] = $openai;


    // v1.9.2: model selection
    $settings['openai_model'] = isset( $_POST['openai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_model'] ) ) : ( $settings['openai_model'] ?? 'gpt-4.1-mini' );
    $settings['notificari_admin_email'] = isset( $_POST['notificari_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['notificari_admin_email'] ) ) : '';
    $settings['limita_upload_mb'] = isset( $_POST['limita_upload_mb'] ) ? absint( wp_unslash( $_POST['limita_upload_mb'] ) ) : 8;

    // v1.7.2: demo data toggle.
    $settings['demo_enabled'] = isset( $_POST['demo_enabled'] ) ? 1 : 0;


    // v1.6: email templates + toggle.
    $settings['trimite_email_candidat'] = isset( $_POST['trimite_email_candidat'] ) ? 1 : 0;
    $settings['email_tpl_admin_new_application'] = isset( $_POST['email_tpl_admin_new_application'] ) ? wp_kses_post( wp_unslash( $_POST['email_tpl_admin_new_application'] ) ) : '';
    $settings['email_tpl_candidate_confirmation'] = isset( $_POST['email_tpl_candidate_confirmation'] ) ? wp_kses_post( wp_unslash( $_POST['email_tpl_candidate_confirmation'] ) ) : '';
    $settings['email_tpl_candidate_feedback'] = isset( $_POST['email_tpl_candidate_feedback'] ) ? wp_kses_post( wp_unslash( $_POST['email_tpl_candidate_feedback'] ) ) : '';

    // v1.9.2: AI queue + automation toggles
    $settings['ai_queue_enabled'] = isset( $_POST['ai_queue_enabled'] ) ? 1 : 0;
    $settings['ai_auto_score_on_apply'] = isset( $_POST['ai_auto_score_on_apply'] ) ? 1 : 0;
    $settings['ai_auto_summary_on_apply'] = isset( $_POST['ai_auto_summary_on_apply'] ) ? 1 : 0;
    $settings['ai_email_status_enabled'] = isset( $_POST['ai_email_status_enabled'] ) ? 1 : 0;
    $settings['ai_email_use_ai'] = isset( $_POST['ai_email_use_ai'] ) ? 1 : 0;

    aisuite_update_settings( $settings );
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=settings&notice=saved' ) );
    exit;
} );

// Clear logs.
add_action( 'admin_post_ai_suite_clear_logs', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_clear_logs' );
    update_option( AI_SUITE_OPTION_LOGS, array(), false );
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=logs&notice=cleared' ) );
    exit;
} );

// Clear runs history.
add_action( 'admin_post_ai_suite_clear_runs', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_clear_runs' );
    update_option( AI_SUITE_OPTION_RUNS, array(), false );
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=runs&notice=cleared' ) );
    exit;
} );

// Repair / (re)create frontend pages + flush permalinks.
add_action( 'admin_post_ai_suite_repair_frontend_pages', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_repair_frontend_pages' );

    // Create pages if missing.
    if ( function_exists( 'aisuite_create_default_pages' ) ) {
        aisuite_create_default_pages();
    }
    if ( function_exists( 'aisuite_create_portal_pages' ) ) {
        aisuite_create_portal_pages();
    }
    if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
        aisuite_create_portal_hub_page();
    }

    // Register CPTs and flush.
    if ( function_exists( 'aisuite_register_cpts_for_activation' ) ) {
        aisuite_register_cpts_for_activation();
    }
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
    }

    // Rebuild curated menu (best-effort assign to theme location).
    if ( function_exists( 'aisuite_create_nav_menu' ) ) {
        aisuite_create_nav_menu( true );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&notice=repaired' ) );
    exit;
} );

// Rebuild frontend navigation menu only.
add_action( 'admin_post_ai_suite_rebuild_frontend_menu', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_rebuild_frontend_menu' );

    if ( function_exists( 'aisuite_create_nav_menu' ) ) {
        aisuite_create_nav_menu( true );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&notice=menu_rebuilt' ) );
    exit;
} );

// Enterprise: Reindex Candidates (SQL Index)
add_action( 'admin_post_ai_suite_reindex_candidates', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_reindex_candidates', 'ai_suite_nonce' );

    if ( function_exists( 'ai_suite_candidate_index_install' ) ) {
        ai_suite_candidate_index_install();
    }

    $count = 0;
    if ( function_exists( 'ai_suite_candidate_index_reindex_all' ) ) {
        $count = (int) ai_suite_candidate_index_reindex_all( 15000 );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&notice=reindexed&count=' . absint( $count ) ) );
    exit;
} );

// Safe Mode: clear Safe Mode + enable all modules.
add_action( 'admin_post_ai_suite_clear_safe_mode', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_clear_safe_mode' );

    // Clear Safe Mode + last fatal + disabled modules.
    if ( defined( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE' ) ) {
        update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE, 0, false );
    }
    if ( defined( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL' ) ) {
        update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0, false );
    }
    if ( defined( 'AI_SUITE_SAFEBOOT_OPT_FATAL' ) ) {
        update_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array(), false );
    }
    if ( defined( 'AI_SUITE_SAFEBOOT_OPT_DISABLED' ) ) {
        update_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array(), false );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&notice=safe_mode_cleared' ) );
    exit;
} );

// Safe Mode: enable/disable a module (bots/facebook/billing...).
add_action( 'admin_post_ai_suite_toggle_module', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_toggle_module' );

    $module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
    $enabled = isset( $_POST['enabled'] ) ? (int) wp_unslash( $_POST['enabled'] ) : 0;
    if ( ! $module ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools' ) );
        exit;
    }

    if ( function_exists( 'aisuite_safe_boot_get_disabled_modules' ) && defined( 'AI_SUITE_SAFEBOOT_OPT_DISABLED' ) ) {
        $disabled = aisuite_safe_boot_get_disabled_modules();
        if ( $enabled ) {
            $disabled = array_values( array_diff( $disabled, array( $module ) ) );
        } else {
            if ( ! in_array( $module, $disabled, true ) ) {
                $disabled[] = $module;
            }
        }
        update_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array_values( array_unique( $disabled ) ), false );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&notice=module_saved' ) );
    exit;
} );

// Toggle bot status.
add_action( 'admin_post_ai_suite_toggle_bot', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_toggle_bot' );
    $bot_key = isset( $_POST['bot_key'] ) ? sanitize_key( wp_unslash( $_POST['bot_key'] ) ) : '';
    $enabled = isset( $_POST['enabled'] ) ? (bool) wp_unslash( $_POST['enabled'] ) : false;
    if ( $bot_key && class_exists( 'AI_Suite_Registry' ) ) {
        AI_Suite_Registry::toggle( $bot_key, $enabled );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=bots' ) );
    exit;
} );

// Export aplicații CSV (cu filtre).
add_action( 'admin_post_ai_suite_export_applications_csv', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_export_csv' );

    $job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
    $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $tag    = isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '';

    $meta_query = array();
    if ( $job_id ) {
        $meta_query[] = array( 'key' => '_application_job_id', 'value' => (string) $job_id );
    }
    if ( $status ) {
        $meta_query[] = array( 'key' => '_application_status', 'value' => $status );
    }
    if ( $tag ) {
        $meta_query[] = array(
            'key'     => '_application_tags',
            'value'   => $tag,
            'compare' => 'LIKE',
        );
    }

    $apps = get_posts( array(
        'post_type'      => 'rmax_application',
        'posts_per_page' => 5000,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => $meta_query,
        's'              => $search,
    ) );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite_aplicatii.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'ID', 'Data', 'Job', 'Candidat', 'Email', 'Telefon', 'Status', 'CV' ) );

    foreach ( $apps as $app ) {
        $application_id = (int) $app->ID;
        $candidate_id   = (int) get_post_meta( $application_id, '_application_candidate_id', true );
        $jid            = (int) get_post_meta( $application_id, '_application_job_id', true );
        $st             = (string) get_post_meta( $application_id, '_application_status', true );
        $email          = (string) get_post_meta( $candidate_id, '_candidate_email', true );
        $tel            = (string) get_post_meta( $candidate_id, '_candidate_phone', true );
        $cv_id          = (int) get_post_meta( $application_id, '_application_cv', true );
        if ( ! $cv_id ) {
            $cv_id = (int) get_post_meta( $candidate_id, '_candidate_cv', true );
        }
        $cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

        fputcsv( $out, array(
            $application_id,
            get_the_date( 'Y-m-d H:i', $application_id ),
            $jid ? get_the_title( $jid ) : '',
            $candidate_id ? get_the_title( $candidate_id ) : '',
            $email,
            $tel,
            $st,
            $cv_url,
        ) );
    }

    fclose( $out );
    exit;
} );

// Export joburi CSV (cu filtre).
add_action( 'admin_post_ai_suite_export_jobs_csv', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_export_jobs_csv' );

    $status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
    $search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $department = isset( $_GET['department'] ) ? absint( wp_unslash( $_GET['department'] ) ) : 0;
    $location   = isset( $_GET['location'] ) ? absint( wp_unslash( $_GET['location'] ) ) : 0;

    $args = array(
        'post_type'      => 'rmax_job',
        'posts_per_page' => 5000,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_key'       => '_job_status',
    );

    if ( 'all' !== $status ) {
        $args['meta_query'] = array(
            array(
                'key'   => '_job_status',
                'value' => $status,
            ),
        );
    }

    if ( $search ) {
        $args['s'] = $search;
    }

    $tax_query = array();
    if ( $department ) {
        $tax_query[] = array(
            'taxonomy' => 'job_department',
            'field'    => 'term_id',
            'terms'    => array( $department ),
        );
    }
    if ( $location ) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field'    => 'term_id',
            'terms'    => array( $location ),
        );
    }
    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = $tax_query;
    }

    $jobs = get_posts( $args );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite_joburi.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'ID', 'Data', 'Titlu', 'Status', 'Departamente', 'Locații', 'Link' ) );

    foreach ( $jobs as $job ) {
        $job_id   = (int) $job->ID;
        $st       = (string) get_post_meta( $job_id, '_job_status', true );
        $deps     = wp_get_post_terms( $job_id, 'job_department', array( 'fields' => 'names' ) );
        $locs     = wp_get_post_terms( $job_id, 'job_location', array( 'fields' => 'names' ) );
        $permalink = get_permalink( $job_id );
        fputcsv( $out, array(
            $job_id,
            get_the_date( 'Y-m-d H:i', $job_id ),
            $job->post_title,
            $st,
            is_array( $deps ) ? implode( ', ', $deps ) : '',
            is_array( $locs ) ? implode( ', ', $locs ) : '',
            $permalink,
        ) );
    }

    fclose( $out );
    exit;
} );

// Export candidați CSV (cu căutare).
add_action( 'admin_post_ai_suite_export_candidates_csv', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_export_candidates_csv' );

    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

    // Search across title + meta (email/phone) by merging IDs.
    $post__in = array();
    if ( $search !== '' ) {
        $ids_title = get_posts( array(
            'post_type'      => 'rmax_candidate',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 5000,
            's'              => $search,
        ) );

        $ids_meta = get_posts( array(
            'post_type'      => 'rmax_candidate',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 5000,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_candidate_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_candidate_phone',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ),
            ),
        ) );

        $post__in = array_values( array_unique( array_merge( array_map( 'absint', (array) $ids_title ), array_map( 'absint', (array) $ids_meta ) ) ) );
        if ( empty( $post__in ) ) {
            $post__in = array( 0 );
        }
    }

    $args = array(
        'post_type'      => 'rmax_candidate',
        'posts_per_page' => 5000,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if ( $search !== '' ) {
        $args['post__in'] = $post__in;
        $args['orderby']  = 'post__in';
    }

    $cands = get_posts( $args );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite_candidati.csv' );

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'ID', 'Data', 'Nume', 'Email', 'Telefon', 'CV' ) );

    foreach ( $cands as $cand ) {
        $cid   = (int) $cand->ID;
        $email = (string) get_post_meta( $cid, '_candidate_email', true );
        $tel   = (string) get_post_meta( $cid, '_candidate_phone', true );
        $cv_id = (int) get_post_meta( $cid, '_candidate_cv', true );
        $cv    = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

        fputcsv( $out, array(
            $cid,
            get_the_date( 'Y-m-d H:i', $cid ),
            $cand->post_title,
            $email,
            $tel,
            $cv,
        ) );
    }

    fclose( $out );
    exit;
} );

// Bulk update status aplicații.
add_action( 'admin_post_ai_suite_bulk_update_applications', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_bulk_apps' );

    $new_status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
    $ids        = isset( $_POST['application_ids'] ) ? (array) $_POST['application_ids'] : array();

    if ( ! $new_status || empty( $ids ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=bulk_missing' ) );
        exit;
    }

    $updated = 0;
    $skipped = 0;
    foreach ( $ids as $id ) {
        $app_id = absint( $id );
        if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
            $skipped++;
            continue;
        }
        $old_status = (string) get_post_meta( $app_id, '_application_status', true );
        if ( $old_status === $new_status ) {
            continue;
        }
        // v1.7.6: validate flow (skip invalid).
        if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $old_status, $new_status ) ) {
            $skipped++;
            continue;
        }
        update_post_meta( $app_id, '_application_status', $new_status );
        if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
            ai_suite_application_record_status_change( $app_id, $old_status, $new_status, 'bulk' );
        } elseif ( function_exists( 'ai_suite_application_add_timeline' ) ) {
            ai_suite_application_add_timeline( $app_id, 'status_changed', array( 'from' => $old_status, 'to' => $new_status, 'context' => 'bulk' ) );
        }
        $updated++;
    }

    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', __( 'Status aplicații actualizat (bulk)', 'ai-suite' ), array( 'count' => count( $ids ), 'updated' => $updated, 'skipped' => $skipped, 'status' => $new_status ) );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=bulk_ok&updated=' . (int) $updated . '&skipped=' . (int) $skipped ) );
    exit;
} );

// v1.6 – Update aplicație (status + tags) din ecranul detaliu.
add_action( 'admin_post_ai_suite_update_application', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }

    $app_id = isset( $_POST['app_id'] ) ? absint( wp_unslash( $_POST['app_id'] ) ) : 0;
    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=error' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_update_application_' . $app_id );

    $new_status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
    $tags_raw   = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

    if ( $new_status ) {
        $old_status = (string) get_post_meta( $app_id, '_application_status', true );
        if ( $old_status !== $new_status ) {
            // v1.7.6: validate status flow.
            if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $old_status, $new_status ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=invalid_transition' ) );
                exit;
            }

            update_post_meta( $app_id, '_application_status', $new_status );

            // v1.7.6: status history + timeline.
            if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
                ai_suite_application_record_status_change( $app_id, $old_status, $new_status, 'manual' );
            } elseif ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $app_id, 'status_changed', array( 'from' => $old_status, 'to' => $new_status ) );
            }
        }
    }

    // Tags
    $tags = array();
    if ( $tags_raw ) {
        foreach ( explode( ',', $tags_raw ) as $t ) {
            $t = trim( $t );
            if ( $t !== '' ) {
                $tags[] = sanitize_text_field( $t );
            }
        }
        $tags = array_values( array_unique( $tags ) );
    }
    update_post_meta( $app_id, '_application_tags', $tags );
    if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
        ai_suite_application_add_timeline( $app_id, 'tags_updated', array( 'tags' => $tags ) );
    }

    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', __( 'Aplicație actualizată', 'ai-suite' ), array( 'application_id' => $app_id, 'status' => $new_status, 'tags' => $tags ) );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=saved' ) );
    exit;
} );

// v1.7.6 – Tranziție rapidă status (nu atinge tags).
add_action( 'admin_post_ai_suite_transition_application_status', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }

    $app_id = isset( $_POST['app_id'] ) ? absint( wp_unslash( $_POST['app_id'] ) ) : 0;
    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=error' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_transition_status_' . $app_id );

    $new_status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
    $old_status = (string) get_post_meta( $app_id, '_application_status', true );

    if ( ! $new_status || $new_status === $old_status ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=saved' ) );
        exit;
    }

    if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $old_status, $new_status ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=invalid_transition' ) );
        exit;
    }

    update_post_meta( $app_id, '_application_status', $new_status );

    if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
        ai_suite_application_record_status_change( $app_id, $old_status, $new_status, 'manual_quick' );
    } elseif ( function_exists( 'ai_suite_application_add_timeline' ) ) {
        ai_suite_application_add_timeline( $app_id, 'status_changed', array( 'from' => $old_status, 'to' => $new_status, 'context' => 'manual_quick' ) );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=saved' ) );
    exit;
} );

// v1.6 – Adaugă notiță internă.
add_action( 'admin_post_ai_suite_add_application_note', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }

    $app_id = isset( $_POST['app_id'] ) ? absint( wp_unslash( $_POST['app_id'] ) ) : 0;
    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=error' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_add_note_' . $app_id );
    $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
    $note = trim( $note );

    if ( $note !== '' ) {
        // v1.7.6: folosește helper PRO (id/user/meta) dacă este disponibil.
        if ( function_exists( 'ai_suite_application_add_note' ) ) {
            ai_suite_application_add_note( $app_id, $note, array( 'context' => 'manual' ) );
        } else {
            $notes = get_post_meta( $app_id, '_application_notes', true );
            if ( ! is_array( $notes ) ) {
                $notes = array();
            }
            $notes[] = array(
                'time' => time(),
                'text' => $note,
                'user' => get_current_user_id(),
            );
            update_post_meta( $app_id, '_application_notes', $notes );

            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $app_id, 'note_added' );
            }
        }

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', __( 'Notiță aplicație adăugată', 'ai-suite' ), array( 'application_id' => $app_id ) );
        }
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=note_added' ) );
    exit;
} );

// v1.7.6 – Șterge notiță internă (din ecranul detaliu).
add_action( 'admin_post_ai_suite_delete_application_note', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }

    $app_id  = isset( $_GET['app_id'] ) ? absint( wp_unslash( $_GET['app_id'] ) ) : 0;
    $note_id = isset( $_GET['note_id'] ) ? sanitize_text_field( wp_unslash( $_GET['note_id'] ) ) : '';
    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=error' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_delete_note_' . $app_id );

    $ok = false;
    if ( function_exists( 'ai_suite_application_delete_note' ) ) {
        $ok = ai_suite_application_delete_note( $app_id, $note_id );
    } elseif ( function_exists( 'ai_suite_application_delete_note_pro' ) ) {
        $ok = ai_suite_application_delete_note_pro( $app_id, $note_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=' . ( $ok ? 'note_deleted' : 'error' ) ) );
    exit;
} );

// v1.6 – Trimite feedback candidat din aplicație.
add_action( 'admin_post_ai_suite_send_application_feedback', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }

    $app_id = isset( $_POST['app_id'] ) ? absint( wp_unslash( $_POST['app_id'] ) ) : 0;
    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&notice=error' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_send_feedback_' . $app_id );

    $candidate_id = (int) get_post_meta( $app_id, '_application_candidate_id', true );
    $job_id       = (int) get_post_meta( $app_id, '_application_job_id', true );
    $status       = (string) get_post_meta( $app_id, '_application_status', true );
    $email        = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_email', true ) : '';

    if ( ! is_email( $email ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=error' ) );
        exit;
    }

    // Asigură funcțiile template.
    if ( ! function_exists( 'ai_suite_render_template' ) || ! function_exists( 'ai_suite_app_statuses' ) ) {
        $maybe = AI_SUITE_DIR . 'includes/recruitment/applications-pro.php';
        if ( file_exists( $maybe ) ) {
            require_once $maybe;
        }
    }

    $statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
    $status_label = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

    $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : __( 'Actualizare aplicație', 'ai-suite' );
    $tpl     = isset( $_POST['feedback'] ) ? wp_kses_post( wp_unslash( $_POST['feedback'] ) ) : '';
    if ( ! $tpl ) {
        $tpl = "Salut, {CANDIDATE_NAME}!\n\nStatus aplicație: {STATUS_LABEL}\n\nFeedback: {FEEDBACK}\n\nMulțumim!\n";
    }

    $candidate_name = $candidate_id ? get_the_title( $candidate_id ) : '';
    $job_title      = $job_id ? get_the_title( $job_id ) : '';

    // {FEEDBACK} – dacă nu există câmp separat, îl setăm gol (mesajul poate fi editat direct în textarea).
    $body = function_exists( 'ai_suite_render_template' ) ? ai_suite_render_template( $tpl, array(
        'candidate_name' => $candidate_name,
        'job_title'      => $job_title,
        'status_label'   => $status_label,
        'feedback'       => '',
        'email'          => $email,
        'status'         => $status,
    ) ) : $tpl;

    wp_mail( $email, $subject, $body );

    if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
        ai_suite_application_add_timeline( $app_id, 'feedback_sent', array( 'to' => $email ) );
    }
    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', __( 'Feedback trimis către candidat', 'ai-suite' ), array( 'application_id' => $app_id, 'to' => $email ) );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id . '&notice=feedback_sent' ) );
    exit;
} );

// Demo: Seed data (manual).
add_action( 'admin_post_ai_suite_seed_demo', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_demo_actions' );

    $force = isset( $_POST['force'] ) ? 1 : 0;

    if ( function_exists( 'aisuite_seed_demo_data' ) ) {
        $summary = aisuite_seed_demo_data( (bool) $force );
        $msg = sprintf( 'seeded:%d,%d,%d', (int) $summary['jobs_created'], (int) $summary['candidates_created'], (int) $summary['applications_created'] );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( 'seed_error' ) ), admin_url( 'admin.php' ) ) );
    exit;
} );

// Demo: Clear data (manual).
add_action( 'admin_post_ai_suite_clear_demo', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_demo_actions' );

    if ( function_exists( 'aisuite_clear_demo_data' ) ) {
        $summary = aisuite_clear_demo_data();
        $msg = sprintf( 'cleared:%d,%d,%d', (int) $summary['jobs_deleted'], (int) $summary['candidates_deleted'], (int) $summary['applications_deleted'] );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( 'clear_error' ) ), admin_url( 'admin.php' ) ) );
    exit;
} );

// Demo: Create demo portal users (candidate + company) for quick login preview.
add_action( 'admin_post_ai_suite_demo_users_create', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_demo_actions' );

    $force = isset( $_POST['force'] ) ? 1 : 0;

    if ( function_exists( 'aisuite_seed_demo_portal_users' ) ) {
        $summary = aisuite_seed_demo_portal_users( (bool) $force );
        $msg = sprintf( 'users:%d,%d', (int) $summary['candidate_user_id'], (int) $summary['company_user_id'] );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( 'users_error' ) ), admin_url( 'admin.php' ) ) );
    exit;
} );

// Demo: Clear demo portal users.
add_action( 'admin_post_ai_suite_demo_users_clear', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_demo_actions' );

    if ( function_exists( 'aisuite_clear_demo_portal_users' ) ) {
        $summary = aisuite_clear_demo_portal_users();
        $msg = sprintf( 'users_cleared:%d', (int) $summary['users_deleted'] );
        wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'settings', 'demo_notice' => rawurlencode( 'users_clear_error' ) ), admin_url( 'admin.php' ) ) );
    exit;
} );

// Wizard: Mark setup as completed.
add_action( 'admin_post_ai_suite_mark_setup_done', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_mark_setup_done' );

    update_option( 'ai_suite_setup_done', 1, false );

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=wizard&notice=setup_done' ) );
    exit;
} );


// -------------------------
// Internal Team actions (Admin/Manager)
// -------------------------

add_action( 'admin_post_ai_suite_team_invite', function() {
    if ( ! function_exists( 'aisuite_current_user_can_manage_team' ) || ! aisuite_current_user_can_manage_team() ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_team_invite' );

    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'aisuite_recruiter';

    if ( ! $email || ! is_email( $email ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( __( 'Email invalid.', 'ai-suite' ) ) ) );
        exit;
    }

    if ( ! in_array( $role, array( 'aisuite_recruiter', 'aisuite_manager' ), true ) ) {
        $role = 'aisuite_recruiter';
    }

    $user_id = email_exists( $email );
    if ( $user_id ) {
        // Existing user: set role.
        $u = get_user_by( 'id', $user_id );
        if ( $u ) {
            $u->set_role( $role );
            if ( $name ) {
                wp_update_user( array( 'ID' => (int) $user_id, 'display_name' => $name ) );
            }
        }
        $msg = __( 'Utilizator existent actualizat cu rolul selectat.', 'ai-suite' );
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( $msg ) ) );
        exit;
    }

    // Create user with random password.
    $login = sanitize_user( current( explode( '@', $email ) ), true );
    if ( ! $login ) {
        $login = 'user' . wp_rand( 1000, 9999 );
    }
    // Ensure unique login.
    $base_login = $login;
    $i = 0;
    while ( username_exists( $login ) ) {
        $i++;
        $login = $base_login . $i;
        if ( $i > 20 ) {
            $login = $base_login . wp_rand( 1000, 9999 );
            break;
        }
    }

    $pass = wp_generate_password( 20, true, true );
    $user_id = wp_create_user( $login, $pass, $email );
    if ( is_wp_error( $user_id ) ) {
        $msg = __( 'Nu am putut crea userul.', 'ai-suite' );
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( $msg ) ) );
        exit;
    }

    wp_update_user( array( 'ID' => (int) $user_id, 'display_name' => ( $name ? $name : $login ), 'role' => $role ) );

    // Send WP standard notification (password set link).
    if ( function_exists( 'wp_new_user_notification' ) ) {
        // Since WP 5.3, signature: ( $user_id, $deprecated = null, $notify = 'both' ).
        @wp_new_user_notification( (int) $user_id, null, 'both' );
    }

    $msg = __( 'User creat. A fost trimis un email pentru setarea parolei.', 'ai-suite' );
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( $msg ) ) );
    exit;
} );

add_action( 'admin_post_ai_suite_team_save_assignments', function() {
    if ( ! function_exists( 'aisuite_current_user_can_manage_team' ) || ! aisuite_current_user_can_manage_team() ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_team_save_assignments' );

    $user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
    $company_ids = isset( $_POST['company_ids'] ) ? (array) wp_unslash( $_POST['company_ids'] ) : array();

    if ( ! $user_id ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( __( 'User invalid.', 'ai-suite' ) ) ) );
        exit;
    }

    if ( function_exists( 'aisuite_set_assigned_company_ids' ) ) {
        aisuite_set_assigned_company_ids( $user_id, $company_ids );
    }

    $msg = __( 'Alocările au fost salvate.', 'ai-suite' );
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=team&msg=' . rawurlencode( $msg ) ) );
    exit;
} );


// === v3.6.0 – Extra exports (companies/leads/queue/logs/runs) + bulk bot toggles ===
if ( ! function_exists( 'ai_suite_actions_user_can_recruitment' ) ) {
    function ai_suite_actions_user_can_recruitment() {
        if ( current_user_can( 'manage_ai_suite' ) ) return true;
        if ( function_exists( 'aisuite_current_user_is_manager' ) && aisuite_current_user_is_manager() ) return true;
        if ( function_exists( 'aisuite_current_user_is_recruiter' ) && aisuite_current_user_is_recruiter() ) return true;
        return false;
    }
}

// Export Companii CSV
add_action( 'admin_post_ai_suite_export_companies_csv', function() {
    if ( ! ai_suite_actions_user_can_recruitment() ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_export_companies_csv' );

    $ids = get_posts( array(
        'post_type'      => 'rmax_company',
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'private', 'draft' ),
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite-companii-' . gmdate( 'Ymd-His' ) . '.csv' );
    echo "ï»¿";

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'id', 'nume', 'email_contact', 'plan', 'team_emails', 'created', 'updated' ) );

    foreach ( $ids as $id ) {
        $email = (string) get_post_meta( $id, '_company_contact_email', true );
        $plan  = (string) get_post_meta( $id, '_company_plan', true );
        $team  = (string) get_post_meta( $id, '_company_team_emails', true );
        $post  = get_post( $id );

        fputcsv( $out, array(
            (int) $id,
            get_the_title( $id ),
            $email,
            $plan,
            $team,
            $post ? $post->post_date : '',
            $post ? $post->post_modified : '',
        ) );
    }
    fclose( $out );
    exit;
} );

// Export Facebook Leads CSV (din tabela ai_suite_fb_leads)
add_action( 'admin_post_ai_suite_export_fb_leads_csv', function() {
    if ( ! ai_suite_actions_user_can_recruitment() ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_export_fb_leads_csv' );

    global $wpdb;
    $table = $wpdb->prefix . 'ai_suite_fb_leads';
    // Optional filter
    $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

    $where = '';
    $params = array();
    if ( $status ) {
        $where = 'WHERE status = %s';
        $params[] = $status;
    }

    // Table may not exist on older installs; fail gracefully.
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( $exists !== $table ) {
        wp_die( __( 'Tabela Facebook Leads nu există încă. Deschide tabul Facebook Leads pentru instalare.', 'ai-suite' ) );
    }

    $sql = "SELECT * FROM {$table} " . ( $where ? $wpdb->prepare( $where, $params ) : '' ) . " ORDER BY created_time DESC LIMIT 5000";
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite-fb-leads-' . gmdate( 'Ymd-His' ) . '.csv' );
    echo "ï»¿";

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'id', 'leadgen_id', 'status', 'company_id', 'candidate_id', 'created_time', 'updated_at', 'email', 'telefon', 'nume', 'raw' ) );

    foreach ( $rows as $r ) {
        $raw = isset( $r['field_data'] ) ? (string) $r['field_data'] : '';
        $fd  = $raw ? json_decode( $raw, true ) : array();
        $email = '';
        $phone = '';
        $name  = '';
        if ( is_array( $fd ) ) {
            foreach ( $fd as $pair ) {
                $k = isset( $pair['name'] ) ? strtolower( (string) $pair['name'] ) : '';
                $v = isset( $pair['values'] ) && is_array( $pair['values'] ) ? implode( ' | ', $pair['values'] ) : '';
                if ( $k && $v ) {
                    if ( strpos( $k, 'mail' ) !== false ) $email = $v;
                    if ( strpos( $k, 'phone' ) !== false || strpos( $k, 'telefon' ) !== false ) $phone = $v;
                    if ( strpos( $k, 'name' ) !== false || strpos( $k, 'nume' ) !== false ) $name = $v;
                }
            }
        }

        fputcsv( $out, array(
            (int) $r['id'],
            (string) $r['leadgen_id'],
            (string) $r['status'],
            (string) $r['company_id'],
            (string) $r['candidate_id'],
            (string) $r['created_time'],
            (string) $r['updated_at'],
            $email,
            $phone,
            $name,
            $raw,
        ) );
    }
    fclose( $out );
    exit;
} );

// Export AI Queue CSV
add_action( 'admin_post_ai_suite_export_ai_queue_csv', function() {
    if ( ! ai_suite_actions_user_can_recruitment() ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_export_ai_queue_csv' );

    if ( ! function_exists( 'ai_suite_queue_table_name' ) ) {
        $maybe = AI_SUITE_DIR . 'includes/ai-queue.php';
        if ( file_exists( $maybe ) ) require_once $maybe;
    }

    global $wpdb;
    $table = function_exists( 'ai_suite_queue_table_name' ) ? ai_suite_queue_table_name() : '';
    if ( ! $table ) {
        wp_die( __( 'AI Queue nu este disponibil.', 'ai-suite' ) );
    }

    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( $exists !== $table ) {
        wp_die( __( 'Tabela AI Queue nu există încă. Activează Coada AI din Setări sau rulează instalarea.', 'ai-suite' ) );
    }

    $rows = $wpdb->get_results( "SELECT id,type,status,priority,attempts,max_attempts,run_at,locked_at,locked_by,created_at,updated_at,last_error FROM {$table} ORDER BY id DESC LIMIT 5000", ARRAY_A );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite-ai-queue-' . gmdate( 'Ymd-His' ) . '.csv' );
    echo "ï»¿";

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array_keys( $rows ? $rows[0] : array(
        'id'=>1,'type'=>'','status'=>'','priority'=>10,'attempts'=>0,'max_attempts'=>3,'run_at'=>'','locked_at'=>'','locked_by'=>'','created_at'=>'','updated_at'=>'','last_error'=>''
    ) ) );

    foreach ( $rows as $r ) {
        fputcsv( $out, $r );
    }
    fclose( $out );
    exit;
} );

// Export Loguri JSON
add_action( 'admin_post_ai_suite_export_logs_json', function() {
    if ( ! ai_suite_actions_user_can_recruitment() ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_export_logs_json' );

    $logs = get_option( AI_SUITE_OPTION_LOGS, array() );
    if ( ! is_array( $logs ) ) $logs = array();

    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite-logs-' . gmdate( 'Ymd-His' ) . '.json' );
    echo wp_json_encode( array_values( $logs ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    exit;
} );

// Export Rulări CSV (AI_SUITE_OPTION_RUNS)
add_action( 'admin_post_ai_suite_export_runs_csv', function() {
    if ( ! ai_suite_actions_user_can_recruitment() ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_export_runs_csv' );

    $runs = get_option( AI_SUITE_OPTION_RUNS, array() );
    if ( ! is_array( $runs ) ) $runs = array();

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=ai-suite-rulari-' . gmdate( 'Ymd-His' ) . '.csv' );
    echo "ï»¿";

    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'time', 'type', 'status', 'message', 'context' ) );

    foreach ( array_reverse( $runs ) as $r ) {
        $time = isset( $r['time'] ) ? (string) $r['time'] : '';
        $type = isset( $r['type'] ) ? (string) $r['type'] : '';
        $st   = isset( $r['status'] ) ? (string) $r['status'] : '';
        $msg  = isset( $r['message'] ) ? (string) $r['message'] : '';
        $ctx  = isset( $r['context'] ) ? wp_json_encode( $r['context'], JSON_UNESCAPED_UNICODE ) : '';
        fputcsv( $out, array( $time, $type, $st, $msg, $ctx ) );
    }
    fclose( $out );
    exit;
} );

// Bulk enable/disable bots (registry)
add_action( 'admin_post_ai_suite_toggle_all_bots', function() {
    if ( ! current_user_can( 'manage_ai_suite' ) ) { wp_die( __( 'Neautorizat', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_toggle_all_bots' );

    $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
    $enable = ( $mode === 'enable' );

    if ( class_exists( 'AI_Suite_Registry' ) ) {
        $all = AI_Suite_Registry::get_all();
        foreach ( $all as $k => $row ) {
            $all[ $k ]['enabled'] = $enable;
        }
        AI_Suite_Registry::set_all( $all );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=bots&notice=bulk_updated' ) );
    exit;
} );


// Manual promo top-up (admin)
add_action( 'admin_post_ai_suite_company_promo_topup', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( esc_html__( 'Nu ai permisiuni.', 'ai-suite' ), 403 );
    }

    $company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;
    if ( ! $company_id ) {
        wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies' ) );
        exit;
    }

    check_admin_referer( 'ai_suite_company_promo_topup_' . (int) $company_id );

    if ( function_exists( 'aisuite_company_promo_topup_maybe' ) ) {
        aisuite_company_promo_topup_maybe( $company_id, true );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=companies&company_id=' . (int) $company_id . '#promo' ) );
    exit;
} );

