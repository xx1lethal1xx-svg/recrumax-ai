<?php
/**
 * AI Suite – Company Team (Enterprise)
 *
 * Obiectiv:
 * - Permite mai mulți utilizatori pe aceeași companie (echipă)
 * - Invitații pe email + roluri (owner / recruiter / viewer)
 * - Adminul are acces complet (manage_options)
 *
 * Tabel: {prefix}ai_suite_company_members
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_company_members_table' ) ) {
    function ai_suite_company_members_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_suite_company_members';
    }
}

if ( ! function_exists( 'ai_suite_company_members_install' ) ) {
    function ai_suite_company_members_install() {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        global $wpdb;
        $table   = ai_suite_company_members_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            invited_email VARCHAR(190) NULL,
            member_role VARCHAR(32) NOT NULL DEFAULT 'recruiter',
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            invited_by BIGINT(20) UNSIGNED NULL,
            token_hash VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            accepted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY company_id (company_id),
            KEY user_id (user_id),
            KEY invited_email (invited_email),
            KEY status (status)
        ) {$charset};";

        dbDelta( $sql );
        return true;
    }
}

if ( ! function_exists( 'ai_suite_company_member_roles' ) ) {
    function ai_suite_company_member_roles() {
        return array(
            'owner'     => __( 'Owner', 'ai-suite' ),
            'recruiter' => __( 'Recruiter', 'ai-suite' ),
            'viewer'    => __( 'Viewer', 'ai-suite' ),
        );
    }
}


if ( ! function_exists( 'ai_suite_get_page_id_by_shortcode' ) ) {
    function ai_suite_get_page_id_by_shortcode( $shortcode ) {
        $shortcode = (string) $shortcode;
        if ( $shortcode === '' ) return 0;
        static $cache = array();
        if ( isset( $cache[ $shortcode ] ) ) return (int) $cache[ $shortcode ];

        $q = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            's'              => $shortcode,
            'fields'         => 'ids',
        ) );
        $pid = 0;
        if ( $q->have_posts() ) {
            $pid = (int) $q->posts[0];
        }
        wp_reset_postdata();
        $cache[ $shortcode ] = $pid;
        return $pid;
    }
}

if ( ! function_exists( 'ai_suite_portal_login_url_public' ) ) {
    function ai_suite_portal_login_url_public() {
        $pid = ai_suite_get_page_id_by_shortcode( '[ai_suite_portal_login]' );
        if ( $pid ) {
            $url = get_permalink( $pid );
            if ( $url ) return $url;
        }
        return wp_login_url();
    }
}


if ( ! function_exists( 'ai_suite_company_members_can_manage' ) ) {
    function ai_suite_company_members_can_manage( $company_id, $user_id = 0 ) {
        $company_id = absint( $company_id );
        $user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $company_id || ! $user_id ) return false;

        if ( current_user_can( 'manage_options' ) ) return true;

        $role = ai_suite_company_members_get_role( $company_id, $user_id );
        return in_array( $role, array( 'owner', 'recruiter' ), true );
    }
}

if ( ! function_exists( 'ai_suite_company_members_get_role' ) ) {
    function ai_suite_company_members_get_role( $company_id, $user_id ) {
        $company_id = absint( $company_id );
        $user_id    = absint( $user_id );
        if ( ! $company_id || ! $user_id ) return '';

        // Fast path: meta on user.
        $meta_company = (int) get_user_meta( $user_id, '_ai_suite_company_id', true );
        if ( $meta_company === $company_id ) {
            $meta_role = (string) get_user_meta( $user_id, '_ai_suite_company_role', true );
            if ( $meta_role ) return $meta_role;
        }

        global $wpdb;
        $table = ai_suite_company_members_table();
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT member_role FROM {$table} WHERE company_id = %d AND user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $company_id, $user_id
        ), ARRAY_A );

        return $row && ! empty( $row['member_role'] ) ? (string) $row['member_role'] : '';
    }
}

if ( ! function_exists( 'ai_suite_company_members_get' ) ) {
    function ai_suite_company_members_get( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return array();

        global $wpdb;
        $table = ai_suite_company_members_table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id = %d ORDER BY created_at DESC",
            $company_id
        ), ARRAY_A );

        return is_array( $rows ) ? $rows : array();
    }
}

if ( ! function_exists( 'ai_suite_company_members_upsert_owner' ) ) {
    /**
     * Asigură ownerul în tabel (folosit la asociere companie).
     */
    function ai_suite_company_members_upsert_owner( $company_id, $user_id ) {
        $company_id = absint( $company_id );
        $user_id    = absint( $user_id );
        if ( ! $company_id || ! $user_id ) return false;

        if ( ! function_exists( 'ai_suite_company_members_install' ) ) return false;
        ai_suite_company_members_install();

        global $wpdb;
        $table = ai_suite_company_members_table();

        // If already exists active row, keep.
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE company_id = %d AND user_id = %d AND status = 'active' LIMIT 1",
            $company_id, $user_id
        ) );
        if ( $exists ) {
            // Update role to owner if needed.
            $wpdb->update( $table, array( 'member_role' => 'owner' ), array( 'id' => (int) $exists ), array( '%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table, array(
                'company_id' => $company_id,
                'user_id'    => $user_id,
                'member_role'=> 'owner',
                'status'     => 'active',
                'created_at' => current_time( 'mysql' ),
                'accepted_at'=> current_time( 'mysql' ),
            ), array( '%d','%d','%s','%s','%s','%s' ) );
        }

        // Keep user meta in sync (fast checks).
        update_user_meta( $user_id, '_ai_suite_company_id', $company_id );
        update_user_meta( $user_id, '_ai_suite_company_role', 'owner' );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_company_members_invite' ) ) {
    function ai_suite_company_members_invite( $company_id, $email, $role = 'recruiter', $invited_by = 0 ) {
        $company_id = absint( $company_id );
        $email      = sanitize_email( $email );
        $role       = sanitize_key( $role );
        $invited_by = $invited_by ? absint( $invited_by ) : get_current_user_id();

        if ( ! $company_id || ! is_email( $email ) ) return new WP_Error( 'invalid', __( 'Email invalid.', 'ai-suite' ) );

        $roles = ai_suite_company_member_roles();
        if ( ! isset( $roles[ $role ] ) ) $role = 'recruiter';

        // Subscription gating: team members limit
        if ( function_exists( 'ai_suite_company_limit' ) ) {
            $limit = (int) ai_suite_company_limit( $company_id, 'team_members' );
            if ( $limit > 0 ) {
                global $wpdb;
                $table = ai_suite_company_members_table();
                $cnt = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(1) FROM {$table} WHERE company_id=%d AND status IN ('active','invited')",
                    $company_id
                ) );
                if ( $cnt >= $limit ) {
                    return new WP_Error( 'limit', __( 'Ai atins limita de membri ai echipei din planul tău. Fă upgrade pentru mai mulți utilizatori.', 'ai-suite' ) );
                }
            }
        }

        ai_suite_company_members_install();

        $token = wp_generate_password( 32, false, false );
        $hash  = wp_hash_password( $token );

        global $wpdb;
        $table = ai_suite_company_members_table();

        $wpdb->insert( $table, array(
            'company_id'     => $company_id,
            'user_id'        => null,
            'invited_email'  => $email,
            'member_role'    => $role,
            'status'         => 'invited',
            'invited_by'     => $invited_by,
            'token_hash'     => $hash,
            'created_at'     => current_time( 'mysql' ),
        ), array( '%d','%d','%s','%s','%s','%d','%s','%s' ) );

        // Link: portal login + params. Accept handler runs on init.
        $login_page = ai_suite_portal_login_url_public();
        $url = add_query_arg( array(
            'ais_invite' => 1,
            'company'    => $company_id,
            'email'      => rawurlencode( $email ),
            'token'      => rawurlencode( $token ),
        ), $login_page );

        $subject = sprintf( __( '[%s] Invitație în echipă', 'ai-suite' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
        $company_name = get_the_title( $company_id );
        $message  = "Ai fost invitat(ă) în echipa companiei: {$company_name}\n\n";
        $message .= "Rol: " . ( isset( $roles[ $role ] ) ? $roles[ $role ] : $role ) . "\n\n";
        $message .= "Acceptă invitația aici:\n{$url}\n\n";
        $message .= "Dacă nu ai cont, vei putea crea unul cu acest email.\n";

        wp_mail( $email, $subject, $message );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_company_members_accept_invite' ) ) {
    function ai_suite_company_members_accept_invite( $company_id, $email, $token ) {
        $company_id = absint( $company_id );
        $email = sanitize_email( $email );
        $token = (string) $token;

        if ( ! $company_id || ! is_email( $email ) || $token === '' ) {
            return new WP_Error( 'invalid', __( 'Invitație invalidă.', 'ai-suite' ) );
        }

        ai_suite_company_members_install();
        global $wpdb;
        $table = ai_suite_company_members_table();

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id = %d AND invited_email = %s AND status = 'invited' ORDER BY id DESC LIMIT 1",
            $company_id, $email
        ), ARRAY_A );

        if ( ! $row || empty( $row['token_hash'] ) || ! wp_check_password( $token, $row['token_hash'] ) ) {
            return new WP_Error( 'invalid', __( 'Token invalid sau expirat.', 'ai-suite' ) );
        }

        // Ensure WP user exists.
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $pass = wp_generate_password( 14, true );
            $uid = wp_create_user( $email, $pass, $email );
            if ( is_wp_error( $uid ) ) {
                return $uid;
            }
            wp_update_user( array(
                'ID'           => $uid,
                'display_name' => $email,
                'role'         => 'aisuite_company',
            ) );
            // Send credentials.
            wp_new_user_notification( $uid, null, 'user' );
            $user = get_user_by( 'id', $uid );
        } else {
            $uid = (int) $user->ID;
            // Ensure role is company.
            if ( ! in_array( 'aisuite_company', (array) $user->roles, true ) ) {
                $user->add_role( 'aisuite_company' );
            }
        }

        // Activate membership row.
        $wpdb->update( $table, array(
            'user_id'     => $uid,
            'status'      => 'active',
            'accepted_at' => current_time( 'mysql' ),
            'token_hash'  => null,
        ), array( 'id' => (int) $row['id'] ), array( '%d','%s','%s','%s' ), array( '%d' ) );

        // Keep meta in sync (portal context uses this).
        update_user_meta( $uid, '_ai_suite_company_id', $company_id );
        update_user_meta( $uid, '_ai_suite_company_role', sanitize_key( $row['member_role'] ) );

        return $uid;
    }
}

// Accept invite handler (runs on frontend on /portal-login).
add_action( 'init', function() {
    if ( ! isset( $_GET['ais_invite'] ) ) return;

    $company = isset( $_GET['company'] ) ? absint( $_GET['company'] ) : 0;
    $email   = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
    $token   = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';

    $res = ai_suite_company_members_accept_invite( $company, $email, $token );

    // Redirect to login with notice.
    $login_url = ai_suite_portal_login_url_public();
    if ( is_wp_error( $res ) ) {
        $login_url = add_query_arg( array( 'ais_invite_err' => rawurlencode( $res->get_error_message() ) ), $login_url );
        wp_safe_redirect( $login_url );
        exit;
    }

    $login_url = add_query_arg( array( 'ais_invite_ok' => 1 ), $login_url );
    wp_safe_redirect( $login_url );
    exit;
}, 9 );

// AJAX endpoints for portal UI.
add_action( 'wp_ajax_ai_suite_team_list', function() {
    check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) && method_exists( 'AI_Suite_Portal_Frontend', 'get_company_id_for_user' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    } else {
        $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );
    }

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi pentru echipă.', 'ai-suite' ) ), 403 );
    }

    $rows = ai_suite_company_members_get( $company_id );
    $out = array();
    foreach ( $rows as $r ) {
        $uid = ! empty( $r['user_id'] ) ? absint( $r['user_id'] ) : 0;
        $u = $uid ? get_user_by( 'id', $uid ) : null;
        $out[] = array(
            'id' => (int) $r['id'],
            'userId' => $uid,
            'email' => $u ? $u->user_email : (string) $r['invited_email'],
            'name'  => $u ? $u->display_name : '',
            'role'  => (string) $r['member_role'],
            'status'=> (string) $r['status'],
            'created' => (string) $r['created_at'],
        );
    }

    wp_send_json( array( 'ok' => true, 'members' => $out, 'roles' => ai_suite_company_member_roles() ) );
} );

add_action( 'wp_ajax_ai_suite_team_invite', function() {
    check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $role  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'recruiter';

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) && method_exists( 'AI_Suite_Portal_Frontend', 'get_company_id_for_user' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    } else {
        $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );
    }

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi pentru invitații.', 'ai-suite' ) ), 403 );
    }

    $res = ai_suite_company_members_invite( $company_id, $email, $role, get_current_user_id() );
    if ( is_wp_error( $res ) ) {
        $code = $res->get_error_code();
        $payload = array( 'ok' => false, 'message' => $res->get_error_message() );
        if ( 'limit' === $code ) {
            $payload['upgrade_required'] = true;
            wp_send_json( $payload, 402 );
        }
        wp_send_json( $payload, 400 );
    }wp_send_json( array( 'ok' => true ) );
} );

add_action( 'wp_ajax_ai_suite_team_remove', function() {
    check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

    $member_id = isset( $_POST['memberId'] ) ? absint( wp_unslash( $_POST['memberId'] ) ) : 0;

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) && method_exists( 'AI_Suite_Portal_Frontend', 'get_company_id_for_user' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    } else {
        $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );
    }

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
    }

    ai_suite_company_members_install();
    global $wpdb;
    $table = ai_suite_company_members_table();

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND company_id = %d",
        $member_id, $company_id
    ), ARRAY_A );

    if ( ! $row ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Membru inexistent.', 'ai-suite' ) ) );
    }

    // Safety: cannot remove last owner.
    if ( (string) $row['member_role'] === 'owner' ) {
        $owners = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE company_id = %d AND status = 'active' AND member_role = 'owner'",
            $company_id
        ) );
        if ( (int) $owners <= 1 ) {
            wp_send_json( array( 'ok' => false, 'message' => __( 'Nu poți elimina ultimul owner.', 'ai-suite' ) ), 409 );
        }
    }

    $wpdb->delete( $table, array( 'id' => $member_id ), array( '%d' ) );
    wp_send_json( array( 'ok' => true ) );
} );

add_action( 'wp_ajax_ai_suite_team_update_role', function() {
    check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

    $member_id = isset( $_POST['memberId'] ) ? absint( wp_unslash( $_POST['memberId'] ) ) : 0;
    $role      = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'recruiter';

    $roles = ai_suite_company_member_roles();
    if ( ! isset( $roles[ $role ] ) ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Rol invalid.', 'ai-suite' ) ) );
    }

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) && method_exists( 'AI_Suite_Portal_Frontend', 'get_company_id_for_user' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    } else {
        $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );
    }

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
    }

    ai_suite_company_members_install();
    global $wpdb;
    $table = ai_suite_company_members_table();

    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND company_id = %d",
        $member_id, $company_id
    ), ARRAY_A );

    if ( ! $row ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Membru inexistent.', 'ai-suite' ) ) );
    }

    $wpdb->update( $table, array( 'member_role' => $role ), array( 'id' => $member_id ), array( '%s' ), array( '%d' ) );

    // Sync user meta if member is active.
    if ( ! empty( $row['user_id'] ) && (string) $row['status'] === 'active' ) {
        update_user_meta( (int) $row['user_id'], '_ai_suite_company_role', $role );
    }

    wp_send_json( array( 'ok' => true ) );
} );
