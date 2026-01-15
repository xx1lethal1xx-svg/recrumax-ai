<?php
/**
 * AI Suite – Portal Frontend (Candidați / Companii) – PREMIUM
 *
 * ADD-ONLY: adaugă portal public cu autentificare + înregistrare.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AI_Suite_Portal_Frontend' ) ) {
    final class AI_Suite_Portal_Frontend {

        public static function boot() {
            // Shortcodes.
            add_shortcode( 'ai_suite_portal_hub', array( __CLASS__, 'sc_portal_hub' ) );
            add_shortcode( 'ai_suite_portal_login', array( __CLASS__, 'sc_login' ) );
            add_shortcode( 'ai_suite_candidate_register', array( __CLASS__, 'sc_register_candidate' ) );
            add_shortcode( 'ai_suite_company_register', array( __CLASS__, 'sc_register_company' ) );
            add_shortcode( 'ai_suite_candidate_portal', array( __CLASS__, 'sc_candidate_portal' ) );
            add_shortcode( 'ai_suite_company_portal', array( __CLASS__, 'sc_company_portal' ) );

            // Form handlers.
            add_action( 'admin_post_nopriv_ai_suite_register_candidate', array( __CLASS__, 'handle_register_candidate' ) );
            add_action( 'admin_post_nopriv_ai_suite_register_company', array( __CLASS__, 'handle_register_company' ) );

            // Assets.
            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

            // Login redirect: send users to the correct portal based on their role.
            // This also fixes the situation where users land on wp-login.php directly.
            add_filter( 'login_redirect', array( __CLASS__, 'filter_login_redirect' ), 20, 3 );
        }

        /**
         * Redirect users after login to the right portal (company/candidate).
         *
         * @param string          $redirect_to           The redirect destination URL.
         * @param string          $requested_redirect_to The requested redirect destination URL.
         * @param WP_User|WP_Error $user                 WP_User object if login was successful, WP_Error otherwise.
         * @return string
         */
        public static function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
            if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
                return $redirect_to;
            }

            $roles = (array) $user->roles;

            // Company portal.
            if ( in_array( 'aisuite_company', $roles, true ) ) {
                $url = self::get_portal_url_by_slug_or_shortcode( 'portal-companie', '[ai_suite_company_portal]' );
                return $url ? $url : $redirect_to;
            }

            // Candidate portal.
            if ( in_array( 'aisuite_candidate', $roles, true ) ) {
                $url = self::get_portal_url_by_slug_or_shortcode( 'portal-candidat', '[ai_suite_candidate_portal]' );
                return $url ? $url : $redirect_to;
            }

            // Admin/editor etc: keep their intended redirect (or wp-admin if none).
            if ( user_can( $user, 'manage_options' ) ) {
                return $redirect_to ? $redirect_to : admin_url();
            }

            return $redirect_to;
        }

        // --------------------
        // Helpers
        // --------------------

        private static function get_page_id_by_shortcode( $shortcode ) {
            $shortcode = (string) $shortcode;
            if ( $shortcode === '' ) {
                return 0;
            }
            $q = new WP_Query( array(
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                's'              => $shortcode,
                'fields'         => 'ids',
            ) );
            if ( ! empty( $q->posts[0] ) ) {
                return absint( $q->posts[0] );
            }
            return 0;
        }

        /**
         * Get a portal URL by known slug first (stable), then by shortcode search (fallback).
         */
        private static function get_portal_url_by_slug_or_shortcode( $slug, $shortcode ) {
            $slug = sanitize_title( (string) $slug );
            $shortcode = (string) $shortcode;

            if ( $slug ) {
                $p = get_page_by_path( $slug );
                if ( $p && isset( $p->ID ) ) {
                    $u = get_permalink( $p->ID );
                    return $u ? $u : '';
                }
            }

            // Fallback to shortcode search.
            $pid = self::get_page_id_by_shortcode( $shortcode );
            if ( $pid > 0 ) {
                $u = get_permalink( $pid );
                return $u ? $u : '';
            }

            return '';
        }

        public static function get_company_id_for_user( $user_id ) {
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return 0;
            }
            $cid = absint( get_user_meta( $user_id, '_ai_suite_company_id', true ) );
            if ( $cid && get_post_type( $cid ) === 'rmax_company' ) {
                return $cid;
            }

            // Fallback: search by meta on company.
            $found = get_posts( array(
                'post_type'      => 'rmax_company',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_company_user_id',
                        'value' => (string) $user_id,
                    ),
                ),
            ) );
            if ( ! empty( $found[0] ) ) {
                $cid = absint( $found[0] );
                update_user_meta( $user_id, '_ai_suite_company_id', $cid );
                return $cid;
            }
            return 0;
        }

        public static function get_candidate_id_for_user( $user_id ) {
            $user_id = absint( $user_id );
            if ( ! $user_id ) {
                return 0;
            }
            $pid = absint( get_user_meta( $user_id, '_ai_suite_candidate_id', true ) );
            if ( $pid && get_post_type( $pid ) === 'rmax_candidate' ) {
                return $pid;
            }

            $found = get_posts( array(
                'post_type'      => 'rmax_candidate',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_candidate_user_id',
                        'value' => (string) $user_id,
                    ),
                ),
            ) );
            if ( ! empty( $found[0] ) ) {
                $pid = absint( $found[0] );
                update_user_meta( $user_id, '_ai_suite_candidate_id', $pid );
                return $pid;
            }
            return 0;
        }

        private static function portal_login_url() {
            $pid = self::get_page_id_by_shortcode( '[ai_suite_portal_login]' );
            return $pid ? get_permalink( $pid ) : wp_login_url();
        }

        private static function company_portal_url() {
            $pid = self::get_page_id_by_shortcode( '[ai_suite_company_portal]' );
            return $pid ? get_permalink( $pid ) : home_url( '/' );
        }

        private static function candidate_portal_url() {
            $pid = self::get_page_id_by_shortcode( '[ai_suite_candidate_portal]' );
            return $pid ? get_permalink( $pid ) : home_url( '/' );
        }

        private static function nice_notice( $type, $msg ) {
            $type = ( $type === 'ok' ) ? 'ok' : 'err';
            return '<div class="ais-notice ais-notice-' . esc_attr( $type ) . '">' . esc_html( (string) $msg ) . '</div>';
        }

        private static function should_enqueue_portal_assets() {
            $post_id = get_queried_object_id();
            if ( ! $post_id ) {
                return false;
            }
            $content = get_post_field( 'post_content', $post_id );
            if ( ! $content || ! is_string( $content ) ) {
                return false;
            }
            $shortcodes = array(
                'ai_suite_portal_hub',
                'ai_suite_portal_login',
                'ai_suite_candidate_register',
                'ai_suite_company_register',
                'ai_suite_candidate_portal',
                'ai_suite_company_portal',
            );
            foreach ( $shortcodes as $sc ) {
                if ( has_shortcode( $content, $sc ) ) {
                    return true;
                }
            }
            return false;
        }


/**
 * Admin preview / impersonation for portals.
 * Allows admins to open Candidate/Company portals as any portal user.
 * Query args: ?ais_as_user=<ID>&ais_as_nonce=<nonce>
 */
private static function is_admin() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

private static function get_impersonated_user_id() {
    if ( ! self::is_admin() ) {
        return 0;
    }
    $uid = isset( $_GET['ais_as_user'] ) ? absint( $_GET['ais_as_user'] ) : 0;
    if ( ! $uid ) {
        return 0;
    }
    $nonce = isset( $_GET['ais_as_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ais_as_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ais_as_user_' . $uid ) ) {
        return 0;
    }
    return $uid;
}

public static function effective_user_id() {
    // For portal AJAX we also support impersonation via POST (see helpers.php).
    if ( function_exists( 'ai_suite_portal_effective_user_id' ) ) {
        $uid = ai_suite_portal_effective_user_id();
        if ( $uid ) {
            return absint( $uid );
        }
    }

    $uid = get_current_user_id();
    $as  = self::get_impersonated_user_id();
    return $as ? $as : $uid;
}

private static function admin_as_url( $url, $user_id ) {
    $user_id = absint( $user_id );
    if ( ! self::is_admin() || ! $user_id ) {
        return $url;
    }
    $nonce = wp_create_nonce( 'ais_as_user_' . $user_id );
    return add_query_arg(
        array(
            'ais_as_user'  => $user_id,
            'ais_as_nonce' => $nonce,
        ),
        $url
    );
}

private static function admin_exit_preview_url( $url ) {
    if ( ! self::is_admin() ) {
        return $url;
    }
    return remove_query_arg( array( 'ais_as_user', 'ais_as_nonce' ), $url );
}

private static function admin_preview_banner( $context ) {
    if ( ! self::is_admin() ) {
        return '';
    }
    $as_uid = self::get_impersonated_user_id();
    if ( ! $as_uid ) {
        return '';
    }
    $u = get_user_by( 'id', $as_uid );
    $label = $u ? ( $u->display_name . ' (' . $u->user_email . ')' ) : ( '#' . $as_uid );
    $exit  = self::admin_exit_preview_url( ( $context === 'company' ) ? self::company_portal_url() : self::candidate_portal_url() );
    return '<div class="ais-notice ais-notice-ok" style="margin-bottom:12px;">'
        . '<strong>' . esc_html__( 'Admin Preview:', 'ai-suite' ) . '</strong> '
        . esc_html( $label )
        . ' <a style="margin-left:10px;" href="' . esc_url( $exit ) . '">' . esc_html__( 'Ieși din preview', 'ai-suite' ) . '</a>'
        . '</div>';
}

private static function admin_user_picker( $context ) {
    if ( ! self::is_admin() ) {
        return '';
    }

    $portal_url = ( $context === 'company' ) ? self::company_portal_url() : self::candidate_portal_url();
    $role       = ( $context === 'company' ) ? 'aisuite_company' : 'aisuite_candidate';

    $users = get_users( array(
        'role'   => $role,
        'number' => 50,
        'orderby'=> 'ID',
        'order'  => 'DESC',
        'fields' => array( 'ID', 'display_name', 'user_email' ),
    ) );

    ob_start();
    echo '<div class="ais-card" style="margin-bottom:14px;">';
    echo '<div class="ais-card-title">' . esc_html__( 'Admin: alege cont pentru preview', 'ai-suite' ) . '</div>';
    echo '<div class="ais-muted" style="margin-top:6px;">' . esc_html__( 'Ești logat ca admin. Selectează un utilizator candidat/companie ca să vezi portalul exact ca el (fără să-i știi parola).', 'ai-suite' ) . '</div>';
    echo '<div class="ais-grid" style="margin-top:12px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">';
    if ( empty( $users ) ) {
        echo '<div class="ais-muted">' . esc_html__( 'Nu există utilizatori pentru acest portal încă.', 'ai-suite' ) . '</div>';
    } else {
        foreach ( $users as $u ) {
            $url = self::admin_as_url( $portal_url, $u->ID );
            echo '<div class="ais-card-tile">';
            echo '<div class="ais-card-title">' . esc_html( $u->display_name ) . '</div>';
            echo '<div class="ais-card-desc">' . esc_html( $u->user_email ) . '</div>';
            echo '<a class="ais-btn ais-btn-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Deschide în preview', 'ai-suite' ) . '</a>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>';
    return ob_get_clean();
}


        // --------------------
        // Assets
        // --------------------

        public static function enqueue_assets() {
            if ( ! self::should_enqueue_portal_assets() ) {
                return;
            }

            wp_enqueue_style( 'ai-suite-portal', AI_SUITE_URL . 'assets/portal.css', array(), AI_SUITE_VER );
            wp_enqueue_style( 'ai-suite-premium', AI_SUITE_URL . 'assets/premium/aisuite-premium.css', array('ai-suite-portal'), AI_SUITE_VER );
            wp_enqueue_style( 'ai-suite-portal-premium', AI_SUITE_URL . 'assets/premium/portal-premium.css', array('ai-suite-premium'), AI_SUITE_VER );
            wp_enqueue_script( 'ai-suite-portal', AI_SUITE_URL . 'assets/portal.js', array( 'jquery' ), AI_SUITE_VER, true );
            wp_enqueue_script( 'ai-suite-premium-ui', AI_SUITE_URL . 'assets/premium/aisuite-ui.js', array(), AI_SUITE_VER, true );

            $effective_uid = ( is_user_logged_in() ) ? self::effective_user_id() : 0;
            $as_uid        = self::get_impersonated_user_id();
            $company_id   = $effective_uid ? self::get_company_id_for_user( $effective_uid ) : 0;
            $candidate_id = $effective_uid ? self::get_candidate_id_for_user( $effective_uid ) : 0;
            $statuses     = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();

            wp_localize_script( 'ai-suite-portal', 'AISuitePortal', array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'ai_suite_portal_nonce' ),
                // Admin Preview: allow AJAX to act as the impersonated user (server validates with nonce).
                'asUser'       => $as_uid ? (int) $as_uid : 0,
                'asNonce'      => $as_uid ? wp_create_nonce( 'ais_as_user_' . (int) $as_uid ) : '',
                'isLogged'        => is_user_logged_in(),
                'isAdmin'         => self::is_admin(),
                'effectiveUserId' => $effective_uid,
                'isCompany'       => ( $effective_uid && function_exists( 'aisuite_user_has_role' ) ) ? aisuite_user_has_role( $effective_uid, 'aisuite_company' ) : ( function_exists( 'aisuite_current_user_is_company' ) ? aisuite_current_user_is_company() : false ),
                'isCandidate'     => ( $effective_uid && function_exists( 'aisuite_user_has_role' ) ) ? aisuite_user_has_role( $effective_uid, 'aisuite_candidate' ) : ( function_exists( 'aisuite_current_user_is_candidate' ) ? aisuite_current_user_is_candidate() : false ),
                'companyId'    => $company_id,
                'candidateId'  => $candidate_id,
                'debug'        => ( self::is_admin() || ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) || isset( $_GET['ais_debug'] ) ),
                'statuses'     => $statuses,
            ) );
        }

        // --------------------
        // Shortcodes
        // --------------------

        public static function sc_portal_hub() {
            $login  = self::get_page_id_by_shortcode( '[ai_suite_portal_login]' );
            $reg_c  = self::get_page_id_by_shortcode( '[ai_suite_candidate_register]' );
            $reg_co = self::get_page_id_by_shortcode( '[ai_suite_company_register]' );
            $cand   = self::get_page_id_by_shortcode( '[ai_suite_candidate_portal]' );
            $comp   = self::get_page_id_by_shortcode( '[ai_suite_company_portal]' );
            $jobs   = get_page_by_path( 'joburi' );

            $items = array(
                array( 'title' => __( 'Autentificare', 'ai-suite' ), 'desc' => __( 'Intră în cont.', 'ai-suite' ), 'url' => $login ? get_permalink( $login ) : wp_login_url() ),
                array( 'title' => __( 'Înregistrare candidat', 'ai-suite' ), 'desc' => __( 'Creează cont candidat.', 'ai-suite' ), 'url' => $reg_c ? get_permalink( $reg_c ) : home_url( '/' ) ),
                array( 'title' => __( 'Înregistrare companie', 'ai-suite' ), 'desc' => __( 'Creează cont companie.', 'ai-suite' ), 'url' => $reg_co ? get_permalink( $reg_co ) : home_url( '/' ) ),
                array( 'title' => __( 'Portal candidat', 'ai-suite' ), 'desc' => __( 'Aplicațiile mele, profil, CV.', 'ai-suite' ), 'url' => $cand ? get_permalink( $cand ) : home_url( '/' ) ),
                array( 'title' => __( 'Portal companie', 'ai-suite' ), 'desc' => __( 'Joburi, candidați, pipeline.', 'ai-suite' ), 'url' => $comp ? get_permalink( $comp ) : home_url( '/' ) ),
            );
            if ( $jobs && isset( $jobs->ID ) ) {
                $items[] = array( 'title' => __( 'Lista joburi', 'ai-suite' ), 'desc' => __( 'Vezi joburile publice.', 'ai-suite' ), 'url' => get_permalink( $jobs->ID ) );
            }

            // Invite notices
            if ( isset( $_GET['ais_invite_ok'] ) ) {
                $notice = self::nice_notice( 'ok', __( 'Invitația a fost acceptată. Te poți autentifica.', 'ai-suite' ) );
            }
            if ( isset( $_GET['ais_invite_err'] ) ) {
                $msg = rawurldecode( (string) wp_unslash( $_GET['ais_invite_err'] ) );
                $notice = self::nice_notice( 'err', $msg ? $msg : __( 'Invitația nu a putut fi procesată.', 'ai-suite' ) );
            }

            ob_start();
            echo '<div class="ais-portal ais-hub ais-premium">';
            echo '<div class="ais-portal-hero">';
            echo '<div class="ais-hero-overlay"></div>';
            echo '<div class="ais-hero-content">';
            echo '<div class="ais-pill">AI Suite • Portal</div>';
            echo '<h2 class="ais-title" style="margin:0;">' . esc_html__( 'Portal', 'ai-suite' ) . '</h2>';
            echo '<div style="color:var(--ais-muted);max-width:740px;">' . esc_html__( 'Intră rapid în cont, creează joburi, gestionează aplicații și folosește automatizări AI.', 'ai-suite' ) . '</div>';
            echo '</div>'; // hero content
            echo '</div>'; // hero
            echo '<div class="ais-portal-card ais-mt-14">';
            echo '<h2 class="ais-title">' . esc_html__( 'Portal', 'ai-suite' ) . '</h2>';
            echo '<p style="margin:0 0 14px; color:#6b7280;">' . esc_html__( 'Aici ai linkurile principale. Dacă o pagină dă 404, intră în Admin → AI Suite → Unelte și apasă „Recreează paginile”.', 'ai-suite' ) . '</p>';
            echo '<div class="ais-grid">';
            foreach ( $items as $it ) {
                echo '<div class="ais-card-tile">';
                echo '<div class="ais-card-title">' . esc_html( $it['title'] ) . '</div>';
                echo '<div class="ais-card-desc">' . esc_html( $it['desc'] ) . '</div>';
                echo '<a class="ais-btn ais-btn-primary" href="' . esc_url( $it['url'] ) . '">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            return ob_get_clean();
        }

        public static function sc_login() {
            // If logged, redirect to portal.
            if ( is_user_logged_in() ) {
                if ( function_exists( 'aisuite_current_user_is_company' ) && aisuite_current_user_is_company() ) {
                    wp_safe_redirect( self::company_portal_url() );
                    exit;
                }
                if ( function_exists( 'aisuite_current_user_is_candidate' ) && aisuite_current_user_is_candidate() ) {
                    wp_safe_redirect( self::candidate_portal_url() );
                    exit;
                }
                // Default: homepage.
                wp_safe_redirect( home_url( '/' ) );
                exit;
            }

            $notice = '';
            if ( isset( $_GET['ai_suite_notice'] ) ) {
                $flag = sanitize_key( wp_unslash( $_GET['ai_suite_notice'] ) );
                $msg  = isset( $_GET['ai_suite_msg'] ) ? rawurldecode( (string) wp_unslash( $_GET['ai_suite_msg'] ) ) : '';
                if ( $msg ) {
                    $notice = self::nice_notice( ( $flag === 'ok' ) ? 'ok' : 'err', $msg );
                }
            }

            ob_start();
            echo '<div class="ais-portal ais-auth">';
            echo '<div class="ais-portal-card">';
            echo '<h2 class="ais-title">' . esc_html__( 'Autentificare', 'ai-suite' ) . '</h2>';
            if ( $notice ) { echo $notice; }

            $redirect = self::company_portal_url();
            echo '<div class="ais-auth-grid">';
            echo '<div class="ais-auth-col">';
            wp_login_form( array(
                'echo'           => true,
                'redirect'       => esc_url( $redirect ),
                'form_id'        => 'aisuite-loginform',
                'label_username' => __( 'Email / Username', 'ai-suite' ),
                'label_password' => __( 'Parolă', 'ai-suite' ),
                'label_remember' => __( 'Ține-mă minte', 'ai-suite' ),
                'label_log_in'   => __( 'Intră în cont', 'ai-suite' ),
                'remember'       => true,
            ) );
            echo '</div>';

            echo '<div class="ais-auth-col ais-auth-side">';
            echo '<div class="ais-auth-side-box">';
            echo '<h3>' . esc_html__( 'Nu ai cont?', 'ai-suite' ) . '</h3>';
            echo '<p class="ais-muted">' . esc_html__( 'Alege tipul de cont și înregistrează-te în 1 minut.', 'ai-suite' ) . '</p>';
            $pid_c = self::get_page_id_by_shortcode( '[ai_suite_company_register]' );
            $pid_p = self::get_page_id_by_shortcode( '[ai_suite_candidate_register]' );
            if ( $pid_c ) {
                echo '<a class="ais-btn ais-btn-primary" href="' . esc_url( get_permalink( $pid_c ) ) . '">' . esc_html__( 'Sunt companie', 'ai-suite' ) . '</a>';
            }
            if ( $pid_p ) {
                echo '<a class="ais-btn ais-btn-ghost" href="' . esc_url( get_permalink( $pid_p ) ) . '">' . esc_html__( 'Sunt candidat', 'ai-suite' ) . '</a>';
            }
            echo '</div>';
            echo '</div>';

            echo '</div>'; // grid
            echo '</div></div>';
            return ob_get_clean();
        }

        public static function sc_register_candidate() {
            if ( is_user_logged_in() ) {
                wp_safe_redirect( self::candidate_portal_url() );
                exit;
            }

            $notice = '';
            if ( isset( $_GET['ai_suite_notice'], $_GET['ai_suite_msg'] ) ) {
                $flag = sanitize_key( wp_unslash( $_GET['ai_suite_notice'] ) );
                $msg  = rawurldecode( (string) wp_unslash( $_GET['ai_suite_msg'] ) );
                $notice = self::nice_notice( ( $flag === 'ok' ) ? 'ok' : 'err', $msg );
            }

            $action = esc_url( admin_url( 'admin-post.php' ) );

            ob_start();
            echo '<div class="ais-portal ais-auth">';
            echo '<div class="ais-portal-card">';
            echo '<h2 class="ais-title">' . esc_html__( 'Înregistrare candidat', 'ai-suite' ) . '</h2>';
            if ( $notice ) { echo $notice; }

            echo '<form class="ais-form" method="post" action="' . $action . '">';
            echo '<input type="hidden" name="action" value="ai_suite_register_candidate" />';
            wp_nonce_field( 'ai_suite_register_candidate', 'ai_suite_register_nonce' );

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Nume complet', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="name" required />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Email', 'ai-suite' ) . '</label>';
            echo '<input type="email" name="email" required />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Telefon', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="phone" required />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Locație (oraș / țară)', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="location" />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Skill-uri (ex: sudor, vopsitor, CNC)', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="skills" />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Parolă', 'ai-suite' ) . '</label>';
            echo '<input type="password" name="pass" required minlength="6" />';
            echo '</div>';

            echo '<button class="ais-btn ais-btn-primary" type="submit">' . esc_html__( 'Creează cont', 'ai-suite' ) . '</button>';
            echo '<p class="ais-muted ais-mt">' . esc_html__( 'Ai deja cont?', 'ai-suite' ) . ' <a href="' . esc_url( self::portal_login_url() ) . '">' . esc_html__( 'Autentifică-te', 'ai-suite' ) . '</a></p>';
            echo '</form>';

            echo '</div></div>';
            return ob_get_clean();
        }

        public static function sc_register_company() {
            if ( is_user_logged_in() ) {
                wp_safe_redirect( self::company_portal_url() );
                exit;
            }

            $notice = '';
            if ( isset( $_GET['ai_suite_notice'], $_GET['ai_suite_msg'] ) ) {
                $flag = sanitize_key( wp_unslash( $_GET['ai_suite_notice'] ) );
                $msg  = rawurldecode( (string) wp_unslash( $_GET['ai_suite_msg'] ) );
                $notice = self::nice_notice( ( $flag === 'ok' ) ? 'ok' : 'err', $msg );
            }

            $action = esc_url( admin_url( 'admin-post.php' ) );

            ob_start();
            echo '<div class="ais-portal ais-auth">';
            echo '<div class="ais-portal-card">';
            echo '<h2 class="ais-title">' . esc_html__( 'Înregistrare companie', 'ai-suite' ) . '</h2>';
            if ( $notice ) { echo $notice; }

            echo '<form class="ais-form" method="post" action="' . $action . '">';
            echo '<input type="hidden" name="action" value="ai_suite_register_company" />';
            wp_nonce_field( 'ai_suite_register_company', 'ai_suite_register_nonce' );

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Nume companie', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="company" required />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Email (login)', 'ai-suite' ) . '</label>';
            echo '<input type="email" name="email" required />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Telefon (opțional)', 'ai-suite' ) . '</label>';
            echo '<input type="text" name="phone" />';
            echo '</div>';

            echo '<div class="ais-form-row">';
            echo '<label>' . esc_html__( 'Parolă', 'ai-suite' ) . '</label>';
            echo '<input type="password" name="pass" required minlength="6" />';
            echo '</div>';

            echo '<button class="ais-btn ais-btn-primary" type="submit">' . esc_html__( 'Creează cont companie', 'ai-suite' ) . '</button>';
            echo '<p class="ais-muted ais-mt">' . esc_html__( 'Ai deja cont?', 'ai-suite' ) . ' <a href="' . esc_url( self::portal_login_url() ) . '">' . esc_html__( 'Autentifică-te', 'ai-suite' ) . '</a></p>';
            echo '</form>';

            echo '</div></div>';
            return ob_get_clean();
        }

        public static function sc_candidate_portal() {
            if ( ! is_user_logged_in() ) {
                return self::nice_notice( 'err', __( 'Te rugăm să te autentifici pentru a continua.', 'ai-suite' ) ) . ' <a class="ais-btn ais-btn-primary" href="' . esc_url( self::portal_login_url() ) . '">' . esc_html__( 'Autentificare', 'ai-suite' ) . '</a>';
            }
            if ( function_exists( 'aisuite_current_user_is_candidate' ) && ! aisuite_current_user_is_candidate() && ! current_user_can( 'manage_options' ) ) {
                return self::nice_notice( 'err', __( 'Contul tău nu este de tip candidat.', 'ai-suite' ) );
            }

            $uid = self::effective_user_id();

            // Admin: dacă nu ești candidat și nu ai selectat un candidat, arată picker.
            if ( current_user_can( 'manage_options' ) ) {
                if ( ! function_exists( 'aisuite_user_has_role' ) || ! aisuite_user_has_role( $uid, 'aisuite_candidate' ) ) {
                    return '<div class="ais-portal" data-portal="candidate">' . self::admin_user_picker( 'candidate' ) . '</div>';
                }
            }

            $pid    = self::get_candidate_id_for_user( $uid );
            $uview  = get_user_by( 'id', $uid );
            $uemail = $uview ? $uview->user_email : '';
            $logout = wp_logout_url( self::portal_login_url() );

            ob_start();
            echo '<div class="ais-portal" data-portal="candidate">';
            echo '<div class="ais-portal-header">';
            echo '<div><h2 class="ais-title">' . esc_html__( 'Portal candidat', 'ai-suite' ) . '</h2>';
            echo '<div class="ais-muted">' . esc_html( $uemail ) . '</div></div>';
            echo '<div class="ais-actions"><a class="ais-btn ais-btn-ghost" href="' . esc_url( $logout ) . '">' . esc_html__( 'Logout', 'ai-suite' ) . '</a></div>';
            echo '</div>';
            echo self::admin_preview_banner( 'candidate' );

            // Tabs (simple, premium)
            $tabs = array(
                'overview'     => __( 'Profil', 'ai-suite' ),
                'applications' => __( 'Aplicațiile mele', 'ai-suite' ),
                'messages'     => __( 'Mesaje', 'ai-suite' ),
                'interviews'   => __( 'Interviuri', 'ai-suite' ),
                'activity'     => __( 'Activitate', 'ai-suite' ),
            );

            echo '<div class="ais-tabs" role="tablist">';
            $first = true;
            foreach ( $tabs as $key => $label ) {
                echo '<button type="button" class="ais-tab' . ( $first ? ' is-active' : '' ) . '" data-ais-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
                $first = false;
            }
            echo '</div>';

            echo '<div class="ais-panes">';

            // Overview
            echo '<section class="ais-pane is-active" data-ais-pane="overview">';
            echo '<div class="ais-grid ais-grid-2">';

            echo '<div class="ais-card">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Profil', 'ai-suite' ) . '</h3>';
            if ( $pid ) {
                $email  = (string) get_post_meta( $pid, '_candidate_email', true );
                $phone  = (string) get_post_meta( $pid, '_candidate_phone', true );
                $loc    = (string) get_post_meta( $pid, '_candidate_location', true );
                $skills = (string) get_post_meta( $pid, '_candidate_skills', true );
                $cv_id  = absint( get_post_meta( $pid, '_candidate_cv', true ) );
                $cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

                echo '<div class="ais-kv"><span>' . esc_html__( 'Email', 'ai-suite' ) . '</span><strong>' . esc_html( $email ) . '</strong></div>';
                echo '<div class="ais-kv"><span>' . esc_html__( 'Telefon', 'ai-suite' ) . '</span><strong>' . esc_html( $phone ) . '</strong></div>';
                if ( $loc ) {
                    echo '<div class="ais-kv"><span>' . esc_html__( 'Locație', 'ai-suite' ) . '</span><strong>' . esc_html( $loc ) . '</strong></div>';
                }
                if ( $skills ) {
                    echo '<div class="ais-kv"><span>' . esc_html__( 'Skill-uri', 'ai-suite' ) . '</span><strong>' . esc_html( $skills ) . '</strong></div>';
                }

                if ( $cv_url ) {
                    echo '<div style="margin-top:10px"><a class="ais-btn ais-btn-primary" target="_blank" rel="noopener" href="' . esc_url( $cv_url ) . '">' . esc_html__( 'Descarcă CV', 'ai-suite' ) . '</a></div>';
                } else {
                    echo '<div class="ais-muted" style="margin-top:10px">' . esc_html__( 'Nu ai încă un CV încărcat.', 'ai-suite' ) . '</div>';
                }
            } else {
                echo self::nice_notice( 'warn', __( 'Profil candidat lipsă. Te rugăm completează înregistrarea.', 'ai-suite' ) );
            }
            echo '</div>';

            echo '<div class="ais-card">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Ghid rapid', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted" style="margin-bottom:10px">' . esc_html__( 'Totul e simplu: aplici la joburi, apoi primești update-uri în Mesaje / Interviuri.', 'ai-suite' ) . '</div>';
            echo '<div class="ais-kpi-grid">';
            echo '<div class="ais-kpi"><div class="k">' . esc_html__( 'Aplicații', 'ai-suite' ) . '</div><div class="v" data-ais-kpi="apps">—</div></div>';
            echo '<div class="ais-kpi"><div class="k">' . esc_html__( 'Interviuri', 'ai-suite' ) . '</div><div class="v" data-ais-kpi="interviews">—</div></div>';
            echo '<div class="ais-kpi"><div class="k">' . esc_html__( 'Mesaje', 'ai-suite' ) . '</div><div class="v" data-ais-kpi="messages">—</div></div>';
            echo '<div class="ais-kpi"><div class="k">' . esc_html__( 'Status', 'ai-suite' ) . '</div><div class="v" data-ais-kpi="status">—</div></div>';
            echo '</div>';
            echo '<div class="ais-actions" style="margin-top:12px">';
            echo '<button type="button" class="ais-btn ais-btn-ghost" data-ais-open-tab="applications">' . esc_html__( 'Vezi aplicațiile', 'ai-suite' ) . '</button>';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-open-tab="messages">' . esc_html__( 'Deschide mesaje', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</section>';

            // Applications
            echo '<section class="ais-pane" data-ais-pane="applications">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-title">' . esc_html__( 'Aplicațiile mele', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin-top:6px">' . esc_html__( 'Aici vezi statusul, compania și scorul AI (dacă este activ).', 'ai-suite' ) . '</div>';
            echo '<div style="margin-top:12px" data-ais-cand-apps></div>';
            echo '</div>';
            echo '</section>';

            // Messages
            echo '<section class="ais-pane" data-ais-pane="messages">';
            echo '<div class="ais-card">';
            echo '<div class="ais-grid ais-grid-2">';
            echo '<div>';
            echo '<div class="ais-card-title">' . esc_html__( 'Conversații', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin:6px 0 10px">' . esc_html__( 'Fiecare aplicație are propriul thread.', 'ai-suite' ) . '</div>';
            echo '<div data-ais-threads></div>';
            echo '</div>';
            echo '<div>';
            echo '<div class="ais-card-title">' . esc_html__( 'Mesaje', 'ai-suite' ) . '</div>';
            echo '<div class="ais-chatbox" data-ais-chat style="margin-top:10px"></div>';
            echo '<div class="ais-row" style="margin-top:10px">';
            echo '<textarea class="ais-textarea" rows="2" placeholder="' . esc_attr__( 'Scrie un mesaj…', 'ai-suite' ) . '" data-ais-chat-input></textarea>';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-chat-send>' . esc_html__( 'Trimite', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-muted" style="margin-top:8px">' . esc_html__( 'Tip: selectează o conversație din stânga.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</section>';

            // Interviews
            echo '<section class="ais-pane" data-ais-pane="interviews">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-title">' . esc_html__( 'Interviuri', 'ai-suite' ) . '</div>';
            echo '<div style="margin-top:12px" data-ais-interviews></div>';
            echo '</div>';
            echo '</section>';

            // Activity
            echo '<section class="ais-pane" data-ais-pane="activity">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-title">' . esc_html__( 'Activitate', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin-top:6px">' . esc_html__( 'Timeline simplu cu acțiuni importante.', 'ai-suite' ) . '</div>';
            echo '<div style="margin-top:12px" data-ais-activity></div>';
            echo '</div>';
            echo '</section>';

            // Team pane.
            echo '<section class="ais-pane" data-ais-pane="team">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Echipă companie', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Invită colegi și gestionează rolurile (owner/recruiter/viewer).', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div class="ais-grid ais-grid-2" style="margin-top:12px">';
            echo '<div>';
            echo '<div class="ais-card-subtitle">' . esc_html__( 'Membri', 'ai-suite' ) . '</div>';
            echo '<div id="ais-team-list" class="ais-table" style="margin-top:10px"></div>';
            echo '</div>';
            echo '<div>';
            echo '<div class="ais-card-subtitle">' . esc_html__( 'Invită membru nou', 'ai-suite' ) . '</div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Email', 'ai-suite' ) . '</label><input type="email" id="ais-team-invite-email" placeholder="email@company.com" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Rol', 'ai-suite' ) . '</label><select id="ais-team-invite-role"><option value="recruiter">Recruiter</option><option value="viewer">Viewer</option><option value="owner">Owner</option></select></div>';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-team-invite-btn">' . esc_html__( 'Trimite invitație', 'ai-suite' ) . '</button>';
            echo '<div class="ais-muted" id="ais-team-invite-msg" style="margin-top:8px"></div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</section>';

            // ATS Settings pane.
            echo '<section class="ais-pane" data-ais-pane="ats_settings">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Setări ATS', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Personalizează coloanele din Pipeline (denumiri + ascundere).', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div id="ais-ats-settings" style="margin-top:12px"></div>';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-ats-save-btn" style="margin-top:12px">' . esc_html__( 'Salvează setări', 'ai-suite' ) . '</button>';
            echo '<div class="ais-muted" id="ais-ats-save-msg" style="margin-top:8px"></div>';
            echo '</div>';
            echo '</section>';

            // Billing pane.
            echo '<section class="ais-pane" data-ais-pane="billing">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Abonament', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Upgrade/Downgrade, status și planuri. Integrare Stripe / NETOPIA.', 'ai-suite' ) . '</div>';
            echo '</div>';

            $current_plan_id = function_exists('ai_suite_company_plan_id') ? ai_suite_company_plan_id( $company_id ) : 'free';
            $is_active = function_exists('ai_suite_subscription_is_active') ? ai_suite_subscription_is_active( $company_id ) : false;
            $plans = function_exists('ai_suite_billing_get_plans') ? ai_suite_billing_get_plans() : array();

            echo '<div id="ais-billing-box" data-company-id="' . esc_attr( (string)$company_id ) . '">';

            echo '<div class="ais-row" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start">';

            echo '<div class="ais-card" style="flex:1; min-width:260px">';
            echo '<div class="ais-muted">' . esc_html__( 'Plan curent', 'ai-suite' ) . '</div>';
            echo '<div id="ais-billing-current-plan" style="font-size:20px; font-weight:700; margin-top:4px">' . esc_html( $current_plan_id ) . '</div>';
            echo '<div id="ais-billing-current-status" class="ais-muted" style="margin-top:6px">' . ( $is_active ? esc_html__( 'Activ', 'ai-suite' ) : esc_html__( 'Inactiv / Free', 'ai-suite' ) ) . '</div>';
            echo '<div id="ais-billing-expiry" class="ais-muted" style="margin-top:8px"></div>';
            echo '<div id="ais-billing-notice" class="ais-notice ais-notice-warn" style="display:none; margin-top:10px"></div>';
            echo '<div class="ais-field" data-ais-billing-provider-row style="margin-top:10px; display:none">';
            echo '<div class="ais-muted" style="margin-bottom:6px">' . esc_html__( 'Plătește cu', 'ai-suite' ) . '</div>';
            echo '<select data-ais-billing-provider style="min-width:180px"><option value="stripe">Stripe</option><option value="netopia">NETOPIA</option></select>';
            echo '<div class="ais-muted" style="margin-top:6px; font-size:12px">' . esc_html__( 'Mod „Ambele”: alegi provider-ul pentru upgrade.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap">';
            echo '<button type="button" class="ais-btn ais-btn-secondary" data-ais-billing-refresh>' . esc_html__( 'Refresh', 'ai-suite' ) . '</button>';
            echo '<button type="button" class="ais-btn" data-ais-billing-manage data-label-stripe="' . esc_attr__( 'Gestionează în Stripe', 'ai-suite' ) . '" data-label-unavailable="' . esc_attr__( 'Indisponibil pentru NETOPIA', 'ai-suite' ) . '">' . esc_html__( 'Gestionează în Stripe', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-muted" style="margin-top:10px">' . esc_html__( 'Dacă ai făcut deja plata, apasă Refresh. Activarea se face prin webhook.', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '<div class="ais-card" style="flex:2; min-width:320px">';
            echo '<div class="ais-muted">' . esc_html__( 'Upgrade/Downgrade', 'ai-suite' ) . '</div>';
            echo '<div class="ais-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px; margin-top:10px">';

            foreach ( $plans as $p ) {
                if ( ! is_array($p) || empty($p['id']) ) continue;
                $pid = sanitize_key( (string)$p['id'] );
                $name = isset($p['name']) ? (string)$p['name'] : $pid;
                $price = isset($p['price_monthly']) ? floatval($p['price_monthly']) : 0;
                $cur = isset($p['currency']) ? strtoupper((string)$p['currency']) : 'EUR';
                $is_current = ( $pid === $current_plan_id );

                echo '<div class="ais-plan-card" style="border:1px solid rgba(0,0,0,.08); border-radius:12px; padding:12px">';
                echo '<div style="display:flex; align-items:center; justify-content:space-between; gap:8px">';
                echo '<div style="font-weight:700">' . esc_html( $name ) . '</div>';
                if ( $is_current ) {
                    echo '<span class="ais-badge">' . esc_html__( 'Curent', 'ai-suite' ) . '</span>';
                }
                echo '</div>';

                if ( $price <= 0 ) {
                    echo '<div class="ais-muted" style="margin-top:6px">' . esc_html__( 'Gratuit', 'ai-suite' ) . '</div>';
                } else {
                    echo '<div class="ais-muted" style="margin-top:6px">' . esc_html( number_format_i18n($price, 0) . ' ' . $cur . '/' . __( 'lună', 'ai-suite' ) ) . '</div>';
                }

                $btn_label = $is_current ? esc_html__( 'Selectat', 'ai-suite' ) : esc_html__( 'Alege', 'ai-suite' );
                $disabled = $is_current ? 'disabled' : '';
                echo '<div style="margin-top:10px"><button type="button" class="ais-btn" data-ais-upgrade-plan="' . esc_attr( $pid ) . '" data-label-selected="' . esc_attr__( 'Selectat', 'ai-suite' ) . '" data-label-choose="' . esc_attr__( 'Alege', 'ai-suite' ) . '" ' . $disabled . '>' . esc_html( $btn_label ) . '</button></div>';
                echo '</div>';
            }

            echo '</div>';
            echo '<div class="ais-muted" style="margin-top:10px">' . esc_html__( 'Notă: dacă folosești Stripe, planurile plătite necesită stripe_price_id setat în AI Suite → Billing. Pentru NETOPIA, e suficient price_monthly + configurarea NETOPIA.', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

            echo '</div>';
            
            // Buyer / Billing details (saved on company meta)
            $b_name    = (string) get_post_meta( $company_id, '_company_billing_name', true );
            $b_cui     = (string) get_post_meta( $company_id, '_company_billing_cui', true );
            $b_reg     = (string) get_post_meta( $company_id, '_company_billing_reg', true );
            $b_addr    = (string) get_post_meta( $company_id, '_company_billing_address', true );
            $b_city    = (string) get_post_meta( $company_id, '_company_billing_city', true );
            $b_country = (string) get_post_meta( $company_id, '_company_billing_country', true );
            $b_email   = (string) get_post_meta( $company_id, '_company_billing_email', true );
            $b_phone   = (string) get_post_meta( $company_id, '_company_billing_phone', true );
            $b_contact = (string) get_post_meta( $company_id, '_company_billing_contact', true );
            $b_vat     = (int) get_post_meta( $company_id, '_company_billing_vat', true );

            echo '<div class="ais-card" style="margin-top:14px" id="ais-buyer-details-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Date facturare (cumpărător)', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Completează datele firmei pentru facturile generate automat. Se salvează în profilul companiei.', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '<div class="ais-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:12px; margin-top:10px">';
            echo '<div class="ais-field"><label>' . esc_html__( 'Denumire firmă', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-name" value="' . esc_attr( $b_name ) . '" placeholder="SC Exemplu SRL" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'CUI', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-cui" value="' . esc_attr( $b_cui ) . '" placeholder="RO123456" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Nr. Reg. Com.', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-reg" value="' . esc_attr( $b_reg ) . '" placeholder="J00/000/2026" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Adresă', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-address" value="' . esc_attr( $b_addr ) . '" placeholder="Str. Exemplu nr. 1" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Oraș', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-city" value="' . esc_attr( $b_city ) . '" placeholder="Baia Mare" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Țara', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-country" value="' . esc_attr( $b_country ) . '" placeholder="RO" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Email facturare', 'ai-suite' ) . '</label><input type="email" id="ais-buyer-email" value="' . esc_attr( $b_email ) . '" placeholder="billing@firma.ro" /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Telefon', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-phone" value="' . esc_attr( $b_phone ) . '" placeholder="+40..." /></div>';
            echo '<div class="ais-field"><label>' . esc_html__( 'Persoană contact', 'ai-suite' ) . '</label><input type="text" id="ais-buyer-contact" value="' . esc_attr( $b_contact ) . '" placeholder="Nume Prenume" /></div>';
            echo '<div class="ais-field" style="display:flex; gap:10px; align-items:center; padding-top:26px">';
            echo '<label style="display:flex; gap:10px; align-items:center"><input type="checkbox" id="ais-buyer-vat" ' . checked( 1, $b_vat ? 1 : 0, false ) . ' /> ' . esc_html__( 'Plătitor TVA', 'ai-suite' ) . '</label>';
            echo '</div>';
            echo '</div>';

            echo '<div style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap">';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-company-billing-save">' . esc_html__( 'Salvează date facturare', 'ai-suite' ) . '</button>';
            echo '<div class="ais-muted" id="ais-company-billing-save-msg"></div>';
            echo '</div>';
            echo '</div>';

            // Billing history (HTML invoices)
            echo '<div class="ais-card" style="margin-top:14px" id="ais-billing-history-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Istoric plăți & facturi', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Audit complet (paid/failed/confirm) + facturi HTML (Print → Save as PDF).', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div id="ais-billing-history" data-company-id="' . esc_attr( $company_id ) . '">';
            echo '<div class="ais-row" style="justify-content:space-between;gap:10px;flex-wrap:wrap">';
            echo '<div class="ais-muted">' . esc_html__( 'Se încarcă istoricul…', 'ai-suite' ) . '</div>';
            echo '<button type="button" class="ais-btn ais-btn--soft" id="ais-billing-history-refresh">' . esc_html__( 'Reîncarcă', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-tablewrap" style="margin-top:10px">';
            echo '<table class="ais-table" id="ais-billing-history-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Data', 'ai-suite' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
            echo '<th>' . esc_html__( 'Total', 'ai-suite' ) . '</th>';
            echo '<th>' . esc_html__( 'Provider', 'ai-suite' ) . '</th>';
            echo '<th>' . esc_html__( 'Factura', 'ai-suite' ) . '</th>';
            echo '</tr></thead>';
            echo '<tbody><tr><td colspan="5" class="ais-muted">' . esc_html__( 'Încă nu există facturi.', 'ai-suite' ) . '</td></tr></tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

echo '</section>';


            echo '</div>'; // panes
            echo '</div>'; // portal

            return ob_get_clean();
        }

        public static function sc_company_portal() {
            if ( ! is_user_logged_in() ) {
                return self::nice_notice( 'err', __( 'Te rugăm să te autentifici.', 'ai-suite' ) ) . '<a class="ais-btn ais-btn-primary" href="' . esc_url( self::portal_login_url() ) . '">' . esc_html__( 'Autentificare', 'ai-suite' ) . '</a>';
            }
            if ( function_exists( 'aisuite_current_user_is_company' ) && ! aisuite_current_user_is_company() && ! current_user_can( 'manage_options' ) ) {
                return self::nice_notice( 'err', __( 'Contul tău nu este de tip companie.', 'ai-suite' ) );
            }

            $uid = self::effective_user_id();

            // Admin: dacă nu ești companie și nu ai selectat o companie, arată picker.
            if ( current_user_can( 'manage_options' ) ) {
                if ( ! function_exists( 'aisuite_user_has_role' ) || ! aisuite_user_has_role( $uid, 'aisuite_company' ) ) {
                    return '<div class="ais-portal" data-portal="company">' . self::admin_user_picker( 'company' ) . '</div>';
                }
            }

            $company_id = self::get_company_id_for_user( $uid );
            if ( $company_id && function_exists( 'ai_suite_company_members_upsert_owner' ) ) {
                // Ensure baseline membership exists.
                ai_suite_company_members_upsert_owner( $company_id, $uid );
            }
            if ( ! $company_id ) {
                if ( current_user_can( 'manage_options' ) ) {
                    return '<div class="ais-portal" data-portal="company">' . self::nice_notice( 'err', __( 'Compania nu este încă asociată cu acest cont.', 'ai-suite' ) ) . self::admin_user_picker( 'company' ) . '</div>';
                }
                return self::nice_notice( 'err', __( 'Compania nu este încă asociată cu acest cont.', 'ai-suite' ) );
            }

            $logout = wp_logout_url( self::portal_login_url() );
            $company_name = get_the_title( $company_id );

            // Metrics.
            $job_ids = function_exists( 'aisuite_company_get_job_ids' ) ? (array) aisuite_company_get_job_ids( $company_id ) : array();
            $jobs_count = count( $job_ids );

            $apps_count = 0;
            if ( ! empty( $job_ids ) ) {
                $apps_count = (int) count( get_posts( array(
                    'post_type'      => 'rmax_application',
                    'posts_per_page' => 500,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_application_job_id',
                            'value'   => array_map( 'strval', array_map( 'absint', $job_ids ) ),
                            'compare' => 'IN',
                        ),
                    ),
                ) ) );
            }

            $shortlist = (array) get_post_meta( $company_id, '_company_shortlist', true );
            $shortlist_count = is_array( $shortlist ) ? count( $shortlist ) : 0;

            ob_start();

            echo '<div class="ais-portal" data-portal="company" data-company-id="' . esc_attr( $company_id ) . '">';
            echo '<div class="ais-portal-header">';
            echo '<div><h2 class="ais-title">' . esc_html( $company_name ) . '</h2>';
            echo '<div class="ais-muted">' . esc_html__( 'Portal companie (ATS)', 'ai-suite' ) . '</div></div>';
            echo '<div class="ais-actions">';
            echo '<a class="ais-btn ais-btn-ghost" href="' . esc_url( $logout ) . '">' . esc_html__( 'Logout', 'ai-suite' ) . '</a>';
            echo '</div>';
            echo '</div>';
            echo self::admin_preview_banner( 'company' );

            // KPI cards.
            echo '<div class="ais-kpis">';
            echo '<div class="ais-kpi"><div class="ais-kpi-label">' . esc_html__( 'Joburi', 'ai-suite' ) . '</div><div class="ais-kpi-val">' . esc_html( (string) $jobs_count ) . '</div></div>';
            echo '<div class="ais-kpi"><div class="ais-kpi-label">' . esc_html__( 'Aplicații', 'ai-suite' ) . '</div><div class="ais-kpi-val">' . esc_html( (string) $apps_count ) . '</div></div>';
            echo '<div class="ais-kpi"><div class="ais-kpi-label">' . esc_html__( 'Shortlist', 'ai-suite' ) . '</div><div class="ais-kpi-val">' . esc_html( (string) $shortlist_count ) . '</div></div>';
            echo '</div>';

            // Billing badge (plan/trial/limits)
            $plan_id = function_exists( 'ai_suite_company_plan_id' ) ? ai_suite_company_plan_id( $company_id ) : 'free';
            $plan_nm = function_exists( 'ai_suite_company_plan_name' ) ? ai_suite_company_plan_name( $company_id ) : $plan_id;
            $trial_active = function_exists( 'ai_suite_trial_is_active' ) ? ai_suite_trial_is_active( $company_id ) : false;
            $trial_days = $trial_active && function_exists( 'ai_suite_trial_remaining_days' ) ? ai_suite_trial_remaining_days( $company_id ) : 0;

            $jobs_limit = function_exists( 'ai_suite_company_limit' ) ? (int) ai_suite_company_limit( $company_id, 'active_jobs' ) : 0;
            $jobs_active = 0;
            if ( $jobs_limit > 0 ) {
                global $wpdb;
                $jobs_active = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(1) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_job_company_id' AND m.meta_value=%s WHERE p.post_type='rmax_job' AND p.post_status='publish'",
                    (string) $company_id
                ) );
            }

            echo '<div class="ais-card ais-billing-badge" style="margin-top:12px">';
            echo '<div class="ais-row ais-row-between" style="align-items:center">';
            echo '<div><strong>' . esc_html__( 'Plan:', 'ai-suite' ) . ' ' . esc_html( $plan_nm ) . '</strong>';
            if ( $trial_active ) {
                echo ' <span class="ais-pill" style="margin-left:8px">' . esc_html__( 'TRIAL', 'ai-suite' ) . ' · ' . esc_html( (string) $trial_days ) . ' ' . esc_html__( 'zile rămase', 'ai-suite' ) . '</span>';
            }
            echo '</div>';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-open-tab="billing">' . esc_html__( 'Gestionează abonament', 'ai-suite' ) . '</button>';
            echo '</div>';

            if ( $jobs_limit > 0 ) {
                echo '<div class="ais-muted" style="margin-top:8px">' . sprintf(
                    esc_html__( 'Limita joburi active: %1$s / %2$s', 'ai-suite' ),
                    '<strong>' . esc_html( (string) $jobs_active ) . '</strong>',
                    '<strong>' . esc_html( (string) $jobs_limit ) . '</strong>'
                ) . '</div>';
            } else {
                echo '<div class="ais-muted" style="margin-top:8px">' . esc_html__( 'Limita joburi active: Nelimitat (sau neconfigurat).', 'ai-suite' ) . '</div>';
            }
            echo '</div>';

            // Tabs.
            $tabs = array(
                'overview'  => __( 'Overview', 'ai-suite' ),
                'jobs'      => __( 'Joburi', 'ai-suite' ),
                'candidates'=> __( 'Candidați', 'ai-suite' ),
                'shortlist' => __( 'Shortlist', 'ai-suite' ),
                'pipeline'  => __( 'Pipeline', 'ai-suite' ),
                'ats_board' => __( 'ATS Board', 'ai-suite' ),
                            'messages' => __( 'Mesaje', 'ai-suite' ),
                'interviews' => __( 'Interviuri', 'ai-suite' ),
                'activity' => __( 'Activitate', 'ai-suite' ),
                'team'     => __( 'Echipă', 'ai-suite' ),
                'ats_settings' => __( 'Setări ATS', 'ai-suite' ),
            );

            // Allow modules to add portal tabs (billing, etc.).
            $tabs = apply_filters( 'ai_suite_company_portal_tabs', $tabs, $company_id );

            echo '<div class="ais-tabs" role="tablist">';
            $first = true;
            foreach ( $tabs as $key => $label ) {
                echo '<button type="button" class="ais-tab' . ( $first ? ' is-active' : '' ) . '" data-ais-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
                $first = false;
            }
            echo '</div>';

            // Panes.
            echo '<div class="ais-panes">';

            // Overview pane.
            echo '<section class="ais-pane is-active" data-ais-pane="overview">';
            echo '<div class="ais-grid ais-grid-2">';
            echo '<div class="ais-card"><h3 class="ais-card-title">' . esc_html__( 'Acțiuni rapide', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-actions-grid">';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-open-tab="candidates">' . esc_html__( 'Caută candidați', 'ai-suite' ) . '</button>';
            echo '<button type="button" class="ais-btn ais-btn-ghost" data-ais-open-tab="pipeline">' . esc_html__( 'Vezi pipeline', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-muted ais-mt">' . esc_html__( 'Notă: Postarea de joburi din portal va fi activată în patch-ul următor (Job Posting PRO).', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '<div class="ais-card"><h3 class="ais-card-title">' . esc_html__( 'Status aplicații', 'ai-suite' ) . '</h3>';
            echo '<div id="ais-status-snapshot" class="ais-muted">' . esc_html__( 'Se încarcă…', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '<div class="ais-card" id="ais-kpi-recruiters-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'KPI per recruiter', 'ai-suite' ) . '</h3>';
            echo '<button type="button" class="ais-btn ais-btn-ghost" id="ais-kpi-recruiters-refresh">' . esc_html__( 'Reîncarcă', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div id="ais-kpi-recruiters" class="ais-muted">' . esc_html__( 'Se încarcă KPI-urile…', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '</div>';
            echo '</section>';

            // Jobs pane.
            echo '<section class="ais-pane" data-ais-pane="jobs">';
            echo '<div class="ais-card">';
            echo '<div class="ais-row ais-row-between">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Joburile companiei', 'ai-suite' ) . '</h3>';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-job-new">' . esc_html__( 'Job nou', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-muted" style="margin-top:6px;">' . esc_html__( 'Creează/editează joburi direct din portal. Publică sau salvează ca draft.', 'ai-suite' ) . '</div>';
            echo '<div class="ais-row" style="margin-top:10px;gap:10px;align-items:center;flex-wrap:wrap;">';
            echo '<span class="ais-pill">' . esc_html__( 'Credite promovare:', 'ai-suite' ) . ' <strong id="ais-promo-credits">—</strong></span>';
            echo '<span class="ais-pill">' . esc_html__( 'Reîncărcare lunară:', 'ai-suite' ) . ' <strong id="ais-promo-allowance">—</strong></span>';
            echo '<span class="ais-muted">' . esc_html__( '1 credit = 7 zile. Creditele se reîncarcă automat lunar (în funcție de plan).', 'ai-suite' ) . '</span>';
            echo '</div>';

            // Fallback server-side list (JS o va înlocui cu versiunea PRO).
            echo '<div id="ais-jobs-list" style="margin-top:12px;">';
            if ( empty( $job_ids ) ) {
                echo '<div class="ais-muted">' . esc_html__( 'Nu ai joburi încă. Apasă „Job nou”.', 'ai-suite' ) . '</div>';
            } else {
                echo '<div class="ais-table">';
                echo '<div class="ais-table-head"><div>' . esc_html__( 'Titlu', 'ai-suite' ) . '</div><div>' . esc_html__( 'Status', 'ai-suite' ) . '</div><div>' . esc_html__( 'Link', 'ai-suite' ) . '</div><div>' . esc_html__( 'Acțiuni', 'ai-suite' ) . '</div></div>';
                foreach ( $job_ids as $jid ) {
                    $st = get_post_status( $jid );
                    echo '<div class="ais-table-row">';
                    echo '<div><strong>' . esc_html( get_the_title( $jid ) ) . '</strong></div>';
                    echo '<div><span class="ais-pill">' . esc_html( $st ) . '</span></div>';
                    echo '<div><a class="ais-link" target="_blank" rel="noopener" href="' . esc_url( get_permalink( $jid ) ) . '">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a></div>';
                    echo '<div><span class="ais-muted">' . esc_html__( 'Activează JS pentru editare.', 'ai-suite' ) . '</span></div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>'; // #ais-jobs-list
            echo '</div></section>';

            // Candidates pane (AJAX search).
            echo '<section class="ais-pane" data-ais-pane="candidates">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Căutare candidați', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Caută după nume/skill-uri/locație. Rezultatele apar mai jos.', 'ai-suite' ) . '</div>';
            echo '</div>';

            echo '<div class="ais-filters">';
            echo '<input type="text" class="ais-input" id="ais-cand-q" placeholder="' . esc_attr__( 'Caută… (ex: sudor, Amsterdam, CNC)', 'ai-suite' ) . '">' ;
            echo '<input type="text" class="ais-input" id="ais-cand-loc" placeholder="' . esc_attr__( 'Locație (opțional)', 'ai-suite' ) . '">' ;
            echo '<label class="ais-check"><input type="checkbox" id="ais-cand-has-cv" checked> ' . esc_html__( 'Doar cu CV', 'ai-suite' ) . '</label>';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-cand-search">' . esc_html__( 'Caută', 'ai-suite' ) . '</button>';
            echo '</div>';

            echo '<div id="ais-cand-results" class="ais-results"></div>';
            echo '</div></section>';

            // Shortlist pane.
            echo '<section class="ais-pane" data-ais-pane="shortlist">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Shortlist', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Candidați salvați de echipa ta. Tags + notițe.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div class="ais-row" style="margin-top:10px; gap:10px; flex-wrap:wrap; align-items:center">';
            echo '<button type="button" class="ais-btn ais-btn-ghost" id="ais-export-shortlist">' . esc_html__( 'Export Shortlist CSV', 'ai-suite' ) . '</button>';
            echo '<div class="ais-muted">' . esc_html__( 'Disponibil pe Pro/Enterprise (server-side 402).', 'ai-suite' ) . '</div>';
            echo '</div>';
echo '</div>';
            echo '<div id="ais-shortlist" class="ais-results"></div>';
            echo '</div></section>';

            // Pipeline pane.
            echo '<section class="ais-pane" data-ais-pane="pipeline">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Pipeline aplicații', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Mută aplicațiile pe statusuri (salvare sigură).', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div class="ais-row" style="margin-top:10px; gap:10px; flex-wrap:wrap; align-items:center">';
            echo '<button type="button" class="ais-btn ais-btn-ghost" id="ais-export-pipeline">' . esc_html__( 'Export Pipeline CSV', 'ai-suite' ) . '</button>';
            echo '<div class="ais-muted">' . esc_html__( 'Disponibil pe Pro/Enterprise (server-side 402).', 'ai-suite' ) . '</div>';
            echo '</div>';
echo '</div>';
            echo '<div id="ais-pipeline" class="ais-kanban"></div>';
            echo '</div></section>';
            // ATS Board (Kanban drag&drop)
            echo '<section class="ais-pane" data-ais-pane="ats_board">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'ATS Board (Kanban)', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Trage și mută aplicațiile între coloane (Drag & Drop). Schimbările se salvează automat.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div class="ais-row" style="margin-top:10px; gap:10px; flex-wrap:wrap; align-items:center">';
            echo '<button type="button" class="ais-btn ais-btn-ghost" id="ais-ats-refresh">' . esc_html__( 'Reîncarcă', 'ai-suite' ) . '</button>';
            echo '<span class="ais-muted">' . esc_html__( 'Disponibil pe Pro/Enterprise (server-side 402).', 'ai-suite' ) . '</span>';
            echo '</div>';
            echo '<div class="ais-ats-tools" style="margin-top:12px">';
            echo '<div class="ais-row" style="gap:8px; flex-wrap:wrap; align-items:center">';
            echo '<select id="ais-ats-saved-views" class="ais-select"><option value="">' . esc_html__( 'Saved views', 'ai-suite' ) . '</option></select>';
            echo '<input type="text" id="ais-ats-view-name" class="ais-input" placeholder="' . esc_attr__( 'Nume view...', 'ai-suite' ) . '" style="min-width:180px" />';
            echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-ats-view-save">' . esc_html__( 'Salvează view', 'ai-suite' ) . '</button>';
            echo '<button type="button" class="ais-btn ais-btn-ghost" id="ais-ats-view-delete">' . esc_html__( 'Șterge view', 'ai-suite' ) . '</button>';
            echo '<div class="ais-smart-search">';
            echo '<input type="text" id="ais-ats-smart-q" class="ais-input" placeholder="' . esc_attr__( 'Smart search: candidați, joburi, aplicații…', 'ai-suite' ) . '" />';
            echo '<div id="ais-ats-smart-results" class="ais-smart-results" style="display:none"></div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="ais-muted" id="ais-ats-view-msg" style="margin-top:6px"></div>';
            echo '</div>';
            echo '</div>';
            echo '<div id="ais-ats-board" class="ais-kanban"></div>';
            echo '</section>';


            // Messages (thread-based)
            echo '<section class="ais-pane" data-ais-pane="messages">';
            echo '<div class="ais-card">';
            echo '<div class="ais-grid ais-grid-2">';
            echo '<div>';
            echo '<div class="ais-card-title">' . esc_html__( 'Conversații', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin:6px 0 10px">' . esc_html__( 'Selectează o aplicație pentru a discuta cu candidatul.', 'ai-suite' ) . '</div>';
            echo '<div data-ais-threads></div>';
            echo '</div>';
            echo '<div>';
            echo '<div class="ais-card-title">' . esc_html__( 'Mesaje', 'ai-suite' ) . '</div>';
            echo '<div class="ais-chatbox" data-ais-chat style="margin-top:10px"></div>';
            echo '<div class="ais-row" style="margin-top:10px">';
            echo '<textarea class="ais-textarea" rows="2" placeholder="' . esc_attr__( 'Scrie un mesaj…', 'ai-suite' ) . '" data-ais-chat-input></textarea>';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-chat-send>' . esc_html__( 'Trimite', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</section>';

            // Interviews
            echo '<section class="ais-pane" data-ais-pane="interviews">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-title">' . esc_html__( 'Interviuri', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin-top:6px">' . esc_html__( 'Programează interviuri rapid (selectează întâi un thread din Mesaje sau o aplicație).', 'ai-suite' ) . '</div>';

            echo '<div class="ais-grid ais-grid-2" style="margin-top:12px">';
            echo '<div>';
            echo '<div class="ais-card-title" style="font-size:14px">' . esc_html__( 'Programare rapidă', 'ai-suite' ) . '</div>';
            echo '<div class="ais-row" style="margin-top:8px">';
            echo '<input type="datetime-local" class="ais-input" data-ais-interview-dt />';
            echo '<input type="number" class="ais-input" min="10" step="5" value="30" data-ais-interview-duration />';
            echo '</div>';
            echo '<div class="ais-row" style="margin-top:8px">';
            echo '<input type="text" class="ais-input" placeholder="' . esc_attr__( 'Locație / link (Zoom/Meet)', 'ai-suite' ) . '" data-ais-interview-location />';
            echo '<button type="button" class="ais-btn ais-btn-primary" data-ais-interview-create>' . esc_html__( 'Programează', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '<div class="ais-muted" style="margin-top:8px">' . esc_html__( 'Tip: deschide un thread (Mesaje) ca să selectezi aplicația.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div>';
            echo '<div class="ais-card-title" style="font-size:14px">' . esc_html__( 'Lista interviurilor', 'ai-suite' ) . '</div>';
            echo '<div style="margin-top:10px" data-ais-interviews></div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
            echo '</section>';

            // Activity
            echo '<section class="ais-pane" data-ais-pane="activity">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-title">' . esc_html__( 'Activitate', 'ai-suite' ) . '</div>';
            echo '<div class="ais-muted" style="margin-top:6px">' . esc_html__( 'Log intern: statusuri, mesaje, interviuri, shortlist.', 'ai-suite' ) . '</div>';
            echo '<div style="margin-top:12px" data-ais-activity></div>';
            echo '</div>';
            echo '</section>';

            
            // Allow modules to render extra panes (ADD-ONLY hook).
            do_action( 'ai_suite_company_portal_render_panes', $company_id );

echo '</div>'; // panes
            echo '</div>'; // portal

            return ob_get_clean();
        }

        // --------------------
        // Handlers
        // --------------------

        public static function handle_register_candidate() {
            if ( empty( $_POST['ai_suite_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_suite_register_nonce'] ) ), 'ai_suite_register_candidate' ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Sesiune invalidă.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
            $loc   = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
            $skills= isset( $_POST['skills'] ) ? sanitize_text_field( wp_unslash( $_POST['skills'] ) ) : '';
            $pass  = isset( $_POST['pass'] ) ? (string) wp_unslash( $_POST['pass'] ) : '';

            if ( mb_strlen( $name ) < 3 || ! is_email( $email ) || mb_strlen( $pass ) < 6 ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Date invalide. Verifică nume/email/parolă.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            if ( email_exists( $email ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Există deja un cont cu acest email.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            $user_id = wp_create_user( $email, $pass, $email );
            if ( is_wp_error( $user_id ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( $user_id->get_error_message() ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
            $u = new WP_User( $user_id );
            $u->set_role( 'aisuite_candidate' );

            // Create candidate CPT and link.
            $candidate_id = wp_insert_post( array(
                'post_type'   => 'rmax_candidate',
                'post_title'  => $name,
                'post_status' => 'publish',
                'post_author' => 0,
            ), true );

            if ( ! is_wp_error( $candidate_id ) && $candidate_id ) {
                update_post_meta( $candidate_id, '_candidate_email', $email );
                update_post_meta( $candidate_id, '_candidate_phone', $phone );
                update_post_meta( $candidate_id, '_candidate_location', $loc );
                update_post_meta( $candidate_id, '_candidate_skills', $skills );
                update_post_meta( $candidate_id, '_candidate_user_id', (string) $user_id );
                update_user_meta( $user_id, '_ai_suite_candidate_id', absint( $candidate_id ) );
            }

            // Auto login.
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, true );

            wp_safe_redirect( self::candidate_portal_url() );
            exit;
        }

        public static function handle_register_company() {
            if ( empty( $_POST['ai_suite_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_suite_register_nonce'] ) ), 'ai_suite_register_company' ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Sesiune invalidă.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            $company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
            $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
            $pass    = isset( $_POST['pass'] ) ? (string) wp_unslash( $_POST['pass'] ) : '';

            if ( mb_strlen( $company ) < 2 || ! is_email( $email ) || mb_strlen( $pass ) < 6 ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Date invalide. Verifică nume companie/email/parolă.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            if ( email_exists( $email ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( __( 'Există deja un cont cu acest email.', 'ai-suite' ) ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            $user_id = wp_create_user( $email, $pass, $email );
            if ( is_wp_error( $user_id ) ) {
                wp_safe_redirect( add_query_arg( array( 'ai_suite_notice' => 'err', 'ai_suite_msg' => rawurlencode( $user_id->get_error_message() ) ), wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
                exit;
            }

            wp_update_user( array( 'ID' => $user_id, 'display_name' => $company ) );
            $u = new WP_User( $user_id );
            $u->set_role( 'aisuite_company' );

            // Create company CPT and link.
            $company_id = wp_insert_post( array(
                'post_type'   => 'rmax_company',
                'post_title'  => $company,
                'post_status' => 'publish',
                'post_author' => 0,
            ), true );

            if ( ! is_wp_error( $company_id ) && $company_id ) {
                // Reuse existing meta helper.
                update_post_meta( $company_id, '_company_contact_email', $email );
                if ( $phone ) {
                    update_post_meta( $company_id, '_company_phone', $phone );
                }
                update_post_meta( $company_id, '_company_user_id', (string) $user_id );
                update_user_meta( $user_id, '_ai_suite_company_id', absint( $company_id ) );
                if ( function_exists( 'ai_suite_company_members_upsert_owner' ) ) {
                    ai_suite_company_members_upsert_owner( $company_id, $user_id );
                }
                if ( function_exists( 'ai_suite_trial_maybe_start' ) ) {
                    ai_suite_trial_maybe_start( $company_id );
                }
            }

            // Auto login.
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, true );

            wp_safe_redirect( self::company_portal_url() );
            exit;
        }
    }
}

add_action( 'init', function() {
    if ( class_exists( 'AI_Suite_Portal_Frontend' ) ) {
        AI_Suite_Portal_Frontend::boot();
    }
}, 12 );
