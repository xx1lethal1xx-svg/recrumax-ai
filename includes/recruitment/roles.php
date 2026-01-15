<?php
/**
 * AI Suite – Roles & capabilities for public portals.
 *
 * ADD-ONLY.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'aisuite_roles_boot' ) ) {
    /**
     * Ensure portal roles exist.
     */
    function aisuite_roles_boot() {
        if ( ! get_role( 'aisuite_candidate' ) ) {
            add_role(
                'aisuite_candidate',
                __( 'AI Suite – Candidat', 'ai-suite' ),
                array( 'read' => true )
            );
        }

        if ( ! get_role( 'aisuite_company' ) ) {
            add_role(
                'aisuite_company',
                __( 'AI Suite – Companie', 'ai-suite' ),
                array( 'read' => true )
            );
        }

        

        // Internal team roles (Admin dashboard access).
        if ( ! get_role( 'aisuite_recruiter' ) ) {
            add_role(
                'aisuite_recruiter',
                __( 'AI Suite – Recruiter', 'ai-suite' ),
                array(
                    'read'                     => true,
                    'aisuite_recruit_access'   => true,
                    'aisuite_manage_recruitment' => true,
                )
            );
        }

        if ( ! get_role( 'aisuite_manager' ) ) {
            add_role(
                'aisuite_manager',
                __( 'AI Suite – Manager', 'ai-suite' ),
                array(
                    'read'                     => true,
                    'aisuite_recruit_access'   => true,
                    'aisuite_manage_recruitment' => true,
                    'aisuite_manage_team'      => true,
                    'aisuite_view_logs'        => true,
                )
            );
        }
// Ensure admin cap exists (already handled by install.php, but keep safe).
        if ( function_exists( 'aisuite_add_caps' ) ) {
            aisuite_add_caps();
        }
    }
    add_action( 'init', 'aisuite_roles_boot', 5 );
}

if ( ! function_exists( 'aisuite_user_has_role' ) ) {
    function aisuite_user_has_role( $user_id, $role ) {
        $user_id = absint( $user_id );
        $role    = (string) $role;
        if ( ! $user_id || ! $role ) {
            return false;
        }
        $u = get_user_by( 'id', $user_id );
        if ( ! $u || empty( $u->roles ) ) {
            return false;
        }
        return in_array( $role, (array) $u->roles, true );
    }
}

if ( ! function_exists( 'aisuite_current_user_is_company' ) ) {
    function aisuite_current_user_is_company() {
        return is_user_logged_in() && aisuite_user_has_role( get_current_user_id(), 'aisuite_company' );
    }
}

if ( ! function_exists( 'aisuite_current_user_is_candidate' ) ) {
    function aisuite_current_user_is_candidate() {
        return is_user_logged_in() && aisuite_user_has_role( get_current_user_id(), 'aisuite_candidate' );
    }
}


if ( ! function_exists( 'aisuite_current_user_is_admin' ) ) {
    function aisuite_current_user_is_admin() {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }
}

if ( ! function_exists( 'aisuite_hide_admin_bar_for_portal_roles' ) ) {
    /**
     * UX: hide WP Admin Bar on frontend for candidate/company roles.
     * Keeps the SaaS feel on mobile.
     */
    function aisuite_hide_admin_bar_for_portal_roles( $show ) {
        if ( ! is_user_logged_in() ) {
            return $show;
        }
        $user = wp_get_current_user();
        if ( ! $user || empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return $show;
        }
        if ( in_array( 'aisuite_candidate', $user->roles, true ) || in_array( 'aisuite_company', $user->roles, true ) ) {
            return false;
        }
        return $show;
    }
    add_filter( 'show_admin_bar', 'aisuite_hide_admin_bar_for_portal_roles', 20 );
}



// -------------------------
// Internal team helpers (Recruiter/Manager)
// -------------------------

if ( ! defined( 'AI_SUITE_USERMETA_ASSIGNED_COMPANIES' ) ) {
    define( 'AI_SUITE_USERMETA_ASSIGNED_COMPANIES', '_aisuite_assigned_company_ids' );
}

if ( ! defined( 'AI_SUITE_COMPANYMETA_ASSIGNED_RECRUITERS' ) ) {
    define( 'AI_SUITE_COMPANYMETA_ASSIGNED_RECRUITERS', '_aisuite_assigned_recruiter_ids' );
}

if ( ! function_exists( 'aisuite_current_user_is_recruiter' ) ) {
    function aisuite_current_user_is_recruiter() {
        return is_user_logged_in() && aisuite_user_has_role( get_current_user_id(), 'aisuite_recruiter' );
    }
}

if ( ! function_exists( 'aisuite_current_user_is_manager' ) ) {
    function aisuite_current_user_is_manager() {
        return is_user_logged_in() && aisuite_user_has_role( get_current_user_id(), 'aisuite_manager' );
    }
}

if ( ! function_exists( 'aisuite_current_user_can_manage_recruitment' ) ) {
    function aisuite_current_user_can_manage_recruitment() {
        return is_user_logged_in() && ( current_user_can( 'manage_ai_suite' ) || current_user_can( 'aisuite_manage_recruitment' ) );
    }
}

if ( ! function_exists( 'aisuite_current_user_can_manage_team' ) ) {
    function aisuite_current_user_can_manage_team() {
        return is_user_logged_in() && ( current_user_can( 'manage_ai_suite' ) || current_user_can( 'aisuite_manage_team' ) );
    }
}

if ( ! function_exists( 'aisuite_get_assigned_company_ids' ) ) {
    function aisuite_get_assigned_company_ids( $user_id = 0 ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            $user_id = get_current_user_id();
        }
        $ids = get_user_meta( $user_id, AI_SUITE_USERMETA_ASSIGNED_COMPANIES, true );
        if ( ! is_array( $ids ) ) {
            $ids = array();
        }
        $out = array();
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id > 0 ) $out[] = $id;
        }
        $out = array_values( array_unique( $out ) );
        return $out;
    }
}

if ( ! function_exists( 'aisuite_set_assigned_company_ids' ) ) {
    function aisuite_set_assigned_company_ids( $user_id, $company_ids ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) return false;

        $company_ids = is_array( $company_ids ) ? $company_ids : array();
        $clean = array();
        foreach ( $company_ids as $cid ) {
            $cid = (int) $cid;
            if ( $cid > 0 ) $clean[] = $cid;
        }
        $clean = array_values( array_unique( $clean ) );

        update_user_meta( $user_id, AI_SUITE_USERMETA_ASSIGNED_COMPANIES, $clean );

        // Best-effort: mirror to company meta so we can also query "who owns".
        foreach ( $clean as $cid ) {
            $recs = get_post_meta( $cid, AI_SUITE_COMPANYMETA_ASSIGNED_RECRUITERS, true );
            if ( ! is_array( $recs ) ) $recs = array();
            if ( ! in_array( $user_id, $recs, true ) ) {
                $recs[] = $user_id;
                update_post_meta( $cid, AI_SUITE_COMPANYMETA_ASSIGNED_RECRUITERS, array_values( array_unique( array_map( 'intval', $recs ) ) ) );
            }
        }

        return true;
    }
}

if ( ! function_exists( 'aisuite_filter_tabs_for_internal_roles' ) ) {
    function aisuite_filter_tabs_for_internal_roles( $tabs ) {
        if ( ! is_array( $tabs ) ) return $tabs;

        // Admin sees everything.
        if ( current_user_can( 'manage_ai_suite' ) ) return $tabs;

        // Managers: recruitment + logs + runs + portal + team.
        if ( aisuite_current_user_is_manager() ) {
            $allow = array( 'dashboard','wizard','jobs','candidates','applications','companies','portal','logs','runs','team' );
            $out = array();
            foreach ( $tabs as $k => $v ) {
                if ( in_array( $k, $allow, true ) ) $out[ $k ] = $v;
            }
            return $out;
        }

        // Recruiters: recruitment + portal.
        if ( aisuite_current_user_is_recruiter() ) {
            $allow = array( 'dashboard','jobs','candidates','applications','companies','portal' );
            $out = array();
            foreach ( $tabs as $k => $v ) {
                if ( in_array( $k, $allow, true ) ) $out[ $k ] = $v;
            }
            return $out;
        }

        return $tabs;
    }
    add_filter( 'ai_suite_tabs', 'aisuite_filter_tabs_for_internal_roles', 30 );
}
