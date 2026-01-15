<?php
/**
 * AI Suite – Communications PRO (Messages + Interviews + Activity Log)
 * v2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aisuite_register_comm_cpts' ) ) {
    function aisuite_register_comm_cpts() {
        // Messages (non-public)
        register_post_type( 'rmax_message', array(
            'labels' => array(
                'name'          => __( 'Mesaje', 'ai-suite' ),
                'singular_name' => __( 'Mesaj', 'ai-suite' ),
            ),
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'supports'            => array( 'editor' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ) );

        // Interviews (non-public)
        register_post_type( 'rmax_interview', array(
            'labels' => array(
                'name'          => __( 'Interviuri', 'ai-suite' ),
                'singular_name' => __( 'Interviu', 'ai-suite' ),
            ),
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ) );
    }
    add_action( 'init', 'aisuite_register_comm_cpts', 8 );
}

if ( ! function_exists( 'ai_suite_activity_table' ) ) {
    function ai_suite_activity_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_suite_activity';
    }
}

if ( ! function_exists( 'ai_suite_activity_install' ) ) {
    function ai_suite_activity_install() {
        global $wpdb;
        $table = ai_suite_activity_table();
        // fast check
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists === $table ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ts BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            role VARCHAR(32) NOT NULL DEFAULT '',
            company_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            candidate_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            application_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(64) NOT NULL DEFAULT '',
            details LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY ts (ts),
            KEY company_id (company_id),
            KEY candidate_id (candidate_id),
            KEY application_id (application_id),
            KEY action (action)
        ) {$charset_collate};";
        dbDelta( $sql );
    }
    add_action( 'init', 'ai_suite_activity_install', 20 );
}

if ( ! function_exists( 'ai_suite_log_activity' ) ) {
    /**
     * @param string $action
     * @param array  $ctx {company_id,candidate_id,application_id,details,role,user_id}
     */
    function ai_suite_log_activity( $action, $ctx = array() ) {
        global $wpdb;
        $table = ai_suite_activity_table();
        if ( empty( $action ) ) { return; }

        $user_id = isset( $ctx['user_id'] ) ? absint( $ctx['user_id'] ) : ( is_user_logged_in() ? get_current_user_id() : 0 );
        $role    = isset( $ctx['role'] ) ? sanitize_text_field( $ctx['role'] ) : '';
        $company_id = isset( $ctx['company_id'] ) ? absint( $ctx['company_id'] ) : 0;
        $candidate_id = isset( $ctx['candidate_id'] ) ? absint( $ctx['candidate_id'] ) : 0;
        $application_id = isset( $ctx['application_id'] ) ? absint( $ctx['application_id'] ) : 0;
        $details = isset( $ctx['details'] ) ? wp_json_encode( $ctx['details'] ) : null;

        $wpdb->insert( $table, array(
            'ts' => time(),
            'user_id' => $user_id,
            'role' => $role,
            'company_id' => $company_id,
            'candidate_id' => $candidate_id,
            'application_id' => $application_id,
            'action' => sanitize_text_field( $action ),
            'details' => $details,
        ), array( '%d','%d','%s','%d','%d','%d','%s','%s' ) );
    }
}

if ( ! function_exists( 'ai_suite_comm_access_application' ) ) {
    /**
     * Returns array(company_id,candidate_id) if user is allowed for application.
     */
    function ai_suite_comm_access_application( $application_id ) {
        $application_id = absint( $application_id );
        if ( ! $application_id || get_post_type( $application_id ) !== 'rmax_application' ) {
            return false;
        }

        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();

        $company_id   = absint( get_post_meta( $application_id, '_application_company_id', true ) );
        $candidate_id = absint( get_post_meta( $application_id, '_application_candidate_id', true ) );

        // Company access
        $is_company = current_user_can( 'rmax_company_access' );
        if ( $is_company ) {
            $my_company = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_company_id_for_user( $uid ) : 0;
            if ( $my_company && $company_id && $my_company === $company_id ) {
                return array( 'company_id' => $company_id, 'candidate_id' => $candidate_id, 'role' => 'company' );
            }
        }

        // Candidate access
        $is_candidate = current_user_can( 'rmax_candidate_access' );
        if ( $is_candidate ) {
            $my_candidate = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_candidate_id_for_user( $uid ) : 0;
            if ( $my_candidate && $candidate_id && $my_candidate === $candidate_id ) {
                return array( 'company_id' => $company_id, 'candidate_id' => $candidate_id, 'role' => 'candidate' );
            }
        }

        return false;
    }
}

if ( ! function_exists( 'ai_suite_comm_json' ) ) {
    function ai_suite_comm_json( $arr ) {
        wp_send_json( $arr );
    }
}

if ( ! function_exists( 'ai_suite_comm_verify' ) ) {
    function ai_suite_comm_verify() {
        if ( ! is_user_logged_in() ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Autentificare necesară.', 'ai-suite' ) ) );
        }
        if ( function_exists( 'ai_suite_portal_require_nonce' ) ) {
            ai_suite_portal_require_nonce( 'ai_suite_portal_nonce' );
        } else {
            check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );
        }
        if ( function_exists( 'ai_suite_portal_user_can' ) && ! ai_suite_portal_user_can( 'portal' ) ) {
            if ( function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
                ai_suite_portal_log_auth_failure( 'capability', array(
                    'action' => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
                ) );
            }
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Neautorizat.', 'ai-suite' ) ) );
        }
    }
}

/**
 * Threads list (applications as threads).
 */
if ( ! function_exists( 'ai_suite_ajax_threads_list' ) ) {
    function ai_suite_ajax_threads_list() {
        ai_suite_comm_verify();

        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
        $role = '';
        $company_id = 0;
        $candidate_id = 0;

        $is_company = current_user_can( 'rmax_company_access' );
        if ( $is_company ) {
            $role = 'company';
            $company_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_company_id_for_user( $uid ) : 0;
        } else {
            $is_candidate = current_user_can( 'rmax_candidate_access' );
            if ( $is_candidate ) {
            $role = 'candidate';
            $candidate_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_candidate_id_for_user( $uid ) : 0;
            } else {
                ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Rol invalid.', 'ai-suite' ) ) );
            }
        }

        $meta_query = array();
        if ( $role === 'company' && $company_id ) {
            $meta_query[] = array( 'key' => '_application_company_id', 'value' => $company_id, 'compare' => '=' );
        }
        if ( $role === 'candidate' && $candidate_id ) {
            $meta_query[] = array( 'key' => '_application_candidate_id', 'value' => $candidate_id, 'compare' => '=' );
        }

        $apps = get_posts( array(
            'post_type'      => 'rmax_application',
            'post_status'    => 'publish',
            'numberposts'    => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
            'suppress_filters' => false,
        ) );

        $out = array();
        foreach ( $apps as $a ) {
            $app_id = (int) $a->ID;
            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            $status = (string) get_post_meta( $app_id, '_application_status', true );

            $cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
            $comp_id = absint( get_post_meta( $app_id, '_application_company_id', true ) );

            $job_title = $job_id ? get_the_title( $job_id ) : __( 'Job', 'ai-suite' );
            $other = '';
            if ( $role === 'company' ) {
                $other = $cand_id ? get_the_title( $cand_id ) : __( 'Candidat', 'ai-suite' );
            } else {
                $other = $comp_id ? get_the_title( $comp_id ) : __( 'Companie', 'ai-suite' );
            }

            // last message
            $last = get_posts( array(
                'post_type' => 'rmax_message',
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_parent' => $app_id,
                'suppress_filters' => false,
            ) );
            $last_txt = '';
            $last_ts = 0;
            if ( $last ) {
                $last_txt = wp_strip_all_tags( $last[0]->post_content );
                $last_txt = mb_substr( $last_txt, 0, 140 );
                $last_ts  = strtotime( $last[0]->post_date_gmt ? $last[0]->post_date_gmt : $last[0]->post_date );
            }

            $out[] = array(
                'application_id' => $app_id,
                'job_id' => $job_id,
                'job_title' => $job_title,
                'status' => $status,
                'other' => $other,
                'last_message' => $last_txt,
                'last_ts' => $last_ts,
            );
        }

        ai_suite_comm_json( array( 'ok' => true, 'threads' => $out ) );
    }
    add_action( 'wp_ajax_ai_suite_threads_list', 'ai_suite_ajax_threads_list' );
}

/**
 * Thread get (messages for an application).
 */
if ( ! function_exists( 'ai_suite_ajax_thread_get' ) ) {
    function ai_suite_ajax_thread_get() {
        ai_suite_comm_verify();
        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;

        $access = ai_suite_comm_access_application( $application_id );
        if ( ! $access ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Acces interzis.', 'ai-suite' ) ) );
        }

        $msgs = get_posts( array(
            'post_type'   => 'rmax_message',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby'     => 'date',
            'order'       => 'ASC',
            'post_parent' => $application_id,
            'suppress_filters' => false,
        ) );

        $out = array();
        foreach ( $msgs as $m ) {
            $out[] = array(
                'id' => (int) $m->ID,
                'ts' => strtotime( $m->post_date_gmt ? $m->post_date_gmt : $m->post_date ),
                'from' => (string) get_post_meta( $m->ID, '_msg_from', true ),
                'text' => wp_kses_post( $m->post_content ),
            );
        }

        ai_suite_comm_json( array( 'ok' => true, 'messages' => $out ) );
    }
    add_action( 'wp_ajax_ai_suite_thread_get', 'ai_suite_ajax_thread_get' );
}

/**
 * Send message.
 */
if ( ! function_exists( 'ai_suite_ajax_message_send' ) ) {
    function ai_suite_ajax_message_send() {
        ai_suite_comm_verify();
        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
        $text = isset( $_POST['text'] ) ? wp_kses_post( wp_unslash( $_POST['text'] ) ) : '';

        if ( ! $application_id || ! $text ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Mesaj invalid.', 'ai-suite' ) ) );
        }

        $access = ai_suite_comm_access_application( $application_id );
        if ( ! $access ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Acces interzis.', 'ai-suite' ) ) );
        }

        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
        $role = $access['role'];

        $msg_id = wp_insert_post( array(
            'post_type'   => 'rmax_message',
            'post_status' => 'publish',
            'post_parent' => $application_id,
            'post_content'=> $text,
            'post_author' => $uid,
        ), true );

        if ( is_wp_error( $msg_id ) || ! $msg_id ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Eroare la trimitere.', 'ai-suite' ) ) );
        }

        update_post_meta( $msg_id, '_msg_from', $role );
        update_post_meta( $msg_id, '_msg_company_id', $access['company_id'] );
        update_post_meta( $msg_id, '_msg_candidate_id', $access['candidate_id'] );
        update_post_meta( $msg_id, '_msg_application_id', $application_id );

        ai_suite_log_activity( 'message_sent', array(
            'role' => $role,
            'company_id' => $access['company_id'],
            'candidate_id' => $access['candidate_id'],
            'application_id' => $application_id,
            'details' => array( 'len' => strlen( wp_strip_all_tags( $text ) ) ),
        ) );

        ai_suite_comm_json( array(
            'ok' => true,
            'message' => array(
                'id' => (int) $msg_id,
                'ts' => time(),
                'from' => $role,
                'text' => wp_kses_post( $text ),
            )
        ) );
    }
    add_action( 'wp_ajax_ai_suite_message_send', 'ai_suite_ajax_message_send' );
}

/**
 * Interviews list (role-based).
 */
if ( ! function_exists( 'ai_suite_ajax_interviews_list' ) ) {
    function ai_suite_ajax_interviews_list() {
        ai_suite_comm_verify();
        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
        $role = '';
        $company_id = 0;
        $candidate_id = 0;

        $is_company = current_user_can( 'rmax_company_access' );
        if ( $is_company ) {
            $role = 'company';
            $company_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_company_id_for_user( $uid ) : 0;
        } else {
            $is_candidate = current_user_can( 'rmax_candidate_access' );
            if ( $is_candidate ) {
                $role = 'candidate';
                $candidate_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_candidate_id_for_user( $uid ) : 0;
            } else {
                ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Rol invalid.', 'ai-suite' ) ) );
            }
        }

        $meta_query = array();
        if ( $role === 'company' && $company_id ) {
            $meta_query[] = array( 'key' => '_interview_company_id', 'value' => $company_id, 'compare' => '=' );
        }
        if ( $role === 'candidate' && $candidate_id ) {
            $meta_query[] = array( 'key' => '_interview_candidate_id', 'value' => $candidate_id, 'compare' => '=' );
        }

        $items = get_posts( array(
            'post_type'   => 'rmax_interview',
            'post_status' => 'publish',
            'numberposts' => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_query'  => $meta_query,
            'suppress_filters' => false,
        ) );

        $out = array();
        foreach ( $items as $it ) {
            $iid = (int) $it->ID;
            $app_id = absint( get_post_meta( $iid, '_interview_application_id', true ) );
            $job_id = $app_id ? absint( get_post_meta( $app_id, '_application_job_id', true ) ) : 0;
            $job_title = $job_id ? get_the_title( $job_id ) : __( 'Job', 'ai-suite' );

            $out[] = array(
                'id' => $iid,
                'application_id' => $app_id,
                'job_title' => $job_title,
                'scheduled_at' => absint( get_post_meta( $iid, '_interview_scheduled_at', true ) ),
                'duration' => absint( get_post_meta( $iid, '_interview_duration', true ) ),
                'status' => (string) get_post_meta( $iid, '_interview_status', true ),
                'location' => (string) get_post_meta( $iid, '_interview_location', true ),
            );
        }

        ai_suite_comm_json( array( 'ok' => true, 'items' => $out ) );
    }
    add_action( 'wp_ajax_ai_suite_interviews_list', 'ai_suite_ajax_interviews_list' );
}

/**
 * Create interview (company only).
 */
if ( ! function_exists( 'ai_suite_ajax_interview_create' ) ) {
    function ai_suite_ajax_interview_create() {
        ai_suite_comm_verify();

        if ( ! current_user_can( 'rmax_company_access' ) ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Doar compania poate programa interviuri.', 'ai-suite' ) ) );
        }

        $application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
        $ts = isset( $_POST['scheduled_at'] ) ? absint( $_POST['scheduled_at'] ) : 0;
        $duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 30;
        $location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

        if ( ! $application_id || $ts < time() - 3600 ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Date interviu invalide.', 'ai-suite' ) ) );
        }

        $access = ai_suite_comm_access_application( $application_id );
        if ( ! $access || $access['role'] !== 'company' ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Acces interzis.', 'ai-suite' ) ) );
        }

        $iid = wp_insert_post( array(
            'post_type'   => 'rmax_interview',
            'post_status' => 'publish',
            'post_title'  => 'Interview #' . $application_id,
            'post_author' => get_current_user_id(),
        ), true );

        if ( is_wp_error( $iid ) || ! $iid ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Eroare creare interviu.', 'ai-suite' ) ) );
        }

        update_post_meta( $iid, '_interview_application_id', $application_id );
        update_post_meta( $iid, '_interview_company_id', $access['company_id'] );
        update_post_meta( $iid, '_interview_candidate_id', $access['candidate_id'] );
        update_post_meta( $iid, '_interview_scheduled_at', $ts );
        update_post_meta( $iid, '_interview_duration', $duration );
        update_post_meta( $iid, '_interview_location', $location );
        update_post_meta( $iid, '_interview_status', 'scheduled' );

        // Move application status to interviu
        update_post_meta( $application_id, '_application_status', 'interviu' );

        ai_suite_log_activity( 'interview_scheduled', array(
            'role' => 'company',
            'company_id' => $access['company_id'],
            'candidate_id' => $access['candidate_id'],
            'application_id' => $application_id,
            'details' => array( 'scheduled_at' => $ts, 'duration' => $duration ),
        ) );

        ai_suite_comm_json( array( 'ok' => true, 'id' => (int) $iid ) );
    }
    add_action( 'wp_ajax_ai_suite_interview_create', 'ai_suite_ajax_interview_create' );
}

/**
 * Update interview status (company or candidate).
 */
if ( ! function_exists( 'ai_suite_ajax_interview_update_status' ) ) {
    function ai_suite_ajax_interview_update_status() {
        ai_suite_comm_verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $allowed = array( 'scheduled','confirmed','declined','completed','cancelled' );
        if ( ! $id || get_post_type( $id ) !== 'rmax_interview' || ! in_array( $status, $allowed, true ) ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Date invalide.', 'ai-suite' ) ) );
        }

        $app_id = absint( get_post_meta( $id, '_interview_application_id', true ) );
        $access = ai_suite_comm_access_application( $app_id );
        if ( ! $access ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Acces interzis.', 'ai-suite' ) ) );
        }

        update_post_meta( $id, '_interview_status', $status );

        ai_suite_log_activity( 'interview_status', array(
            'role' => $access['role'],
            'company_id' => $access['company_id'],
            'candidate_id' => $access['candidate_id'],
            'application_id' => $app_id,
            'details' => array( 'status' => $status ),
        ) );

        ai_suite_comm_json( array( 'ok' => true ) );
    }
    add_action( 'wp_ajax_ai_suite_interview_update_status', 'ai_suite_ajax_interview_update_status' );
}

/**
 * Activity list.
 */
if ( ! function_exists( 'ai_suite_ajax_activity_list' ) ) {
    function ai_suite_ajax_activity_list() {
        ai_suite_comm_verify();
        global $wpdb;

        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
        $role = '';
        $company_id = 0;
        $candidate_id = 0;

        $is_company = current_user_can( 'rmax_company_access' );
        if ( $is_company ) {
            $role = 'company';
            $company_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_company_id_for_user( $uid ) : 0;
        } else {
            $is_candidate = current_user_can( 'rmax_candidate_access' );
            if ( $is_candidate ) {
                $role = 'candidate';
                $candidate_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_candidate_id_for_user( $uid ) : 0;
            } else {
                ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Rol invalid.', 'ai-suite' ) ) );
            }
        }

        $table = ai_suite_activity_table();
        $where = "1=1";
        $params = array();

        if ( $role === 'company' && $company_id ) {
            $where .= " AND company_id = %d";
            $params[] = $company_id;
        }
        if ( $role === 'candidate' && $candidate_id ) {
            $where .= " AND candidate_id = %d";
            $params[] = $candidate_id;
        }

        $sql = "SELECT id, ts, action, details, application_id, role FROM {$table} WHERE {$where} ORDER BY ts DESC LIMIT 60";
        $rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $rows as &$r ) {
            $r['details'] = $r['details'] ? json_decode( $r['details'], true ) : null;
        }

        ai_suite_comm_json( array( 'ok' => true, 'items' => $rows ) );
    }
    add_action( 'wp_ajax_ai_suite_activity_list', 'ai_suite_ajax_activity_list' );
}

/**
 * Candidate applications list (for candidate portal).
 */
if ( ! function_exists( 'ai_suite_ajax_candidate_applications_list' ) ) {
    function ai_suite_ajax_candidate_applications_list() {
        ai_suite_comm_verify();
        $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
        $is_candidate = current_user_can( 'rmax_candidate_access' );
        if ( ! $is_candidate ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Doar candidații pot accesa.', 'ai-suite' ) ) );
        }
        $candidate_id = class_exists( 'AI_Suite_Portal_Frontend' ) ? AI_Suite_Portal_Frontend::get_candidate_id_for_user( $uid ) : 0;
        if ( ! $candidate_id ) {
            ai_suite_comm_json( array( 'ok' => false, 'message' => __( 'Profil candidat lipsă.', 'ai-suite' ) ) );
        }

        $apps = get_posts( array(
            'post_type'      => 'rmax_application',
            'post_status'    => 'publish',
            'numberposts'    => 80,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array( 'key' => '_application_candidate_id', 'value' => $candidate_id, 'compare' => '=' ),
            ),
            'suppress_filters' => false,
        ) );

        $out = array();
        foreach ( $apps as $a ) {
            $app_id = (int) $a->ID;
            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            $status = (string) get_post_meta( $app_id, '_application_status', true );
            $score  = get_post_meta( $app_id, '_application_score', true );
            $score  = is_numeric( $score ) ? (int) $score : null;
            $job_title = $job_id ? get_the_title( $job_id ) : __( 'Job', 'ai-suite' );
            $company_id = $job_id ? absint( get_post_meta( $job_id, '_job_company_id', true ) ) : absint( get_post_meta( $app_id, '_application_company_id', true ) );
            $company_name = $company_id ? get_the_title( $company_id ) : __( 'Companie', 'ai-suite' );

            $out[] = array(
                'application_id' => $app_id,
                'job_id' => $job_id,
                'job_title' => $job_title,
                'company' => $company_name,
                'status' => $status,
                'score' => $score,
                'created' => get_post_time( 'U', true, $app_id ),
            );
        }

        ai_suite_comm_json( array( 'ok' => true, 'items' => $out ) );
    }
    add_action( 'wp_ajax_ai_suite_candidate_applications_list', 'ai_suite_ajax_candidate_applications_list' );
}
