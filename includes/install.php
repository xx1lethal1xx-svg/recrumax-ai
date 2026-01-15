<?php
/**
 * Install / upgrade routines for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'AI_SUITE_OPTION_INSTALLED_VER' ) ) {
    define( 'AI_SUITE_OPTION_INSTALLED_VER', 'ai_suite_installed_ver' );
}

if ( ! function_exists( 'aisuite_capability' ) ) {
    /**
     * Central capability used by the plugin.
     *
     * @return string
     */
    function aisuite_capability() {
        $admin_cap = apply_filters( 'ai_suite_capability', 'manage_ai_suite' );

        // If the current user can manage the whole suite, keep using the admin capability.
        if ( is_user_logged_in() && current_user_can( $admin_cap ) ) {
            return $admin_cap;
        }

        // Internal team access (Recruiter/Manager).
        return apply_filters( 'ai_suite_recruit_capability', 'aisuite_recruit_access' );
    }
}

if ( ! function_exists( 'aisuite_add_caps' ) ) {
    /**
     * Add plugin capabilities to administrator role.
     */
    function aisuite_add_caps() {
        $role = get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }
        $role->add_cap( 'manage_ai_suite' );
        // Internal team capabilities
        $role->add_cap( 'aisuite_recruit_access' );
        $role->add_cap( 'aisuite_manage_recruitment' );
        $role->add_cap( 'aisuite_manage_team' );
        $role->add_cap( 'aisuite_view_logs' );
    }
}



if ( ! function_exists( 'aisuite_enable_en_pages' ) ) {
    /**
     * Should we create EN convenience pages too?
     * Stored in ai_suite_settings['enable_en_pages'] (preferred) or ai_suite_enable_en_pages option (legacy).
     */
    function aisuite_enable_en_pages() {
        $enable = 0;
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $s = aisuite_get_settings();
            if ( is_array( $s ) && isset( $s['enable_en_pages'] ) ) {
                $enable = (int) $s['enable_en_pages'];
            }
        }
        if ( ! $enable ) {
            $enable = (int) get_option( 'ai_suite_enable_en_pages', 0 );
        }
        if ( ! $enable ) {
            $loc = function_exists( 'get_locale' ) ? (string) get_locale() : '';
            if ( $loc && strpos( $loc, 'en_' ) === 0 ) {
                $enable = 1;
            }
        }
        return (int) $enable;
    }
}

if ( ! function_exists( 'aisuite_create_default_pages' ) ) {
    /**
     * Create default public pages (job listings).
     */
    function aisuite_create_default_pages() {
        // RO page.
        $slug     = 'joburi';
        $page_obj = get_page_by_path( $slug );

        if ( ! $page_obj ) {
            wp_insert_post( array(
                'post_title'   => __( 'Joburi', 'ai-suite' ),
                'post_name'    => $slug,
                'post_content' => '[ai_suite_jobs]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
        }

        // EN convenience page (does NOT override CPT archive /jobs).
        // Controlled by an option to avoid cluttering menus on RO-only sites.
        $enable_en = function_exists('aisuite_enable_en_pages') ? aisuite_enable_en_pages() : (int) get_option( 'ai_suite_enable_en_pages', 0 );

        if ( $enable_en ) {
            $slug_en     = 'jobs-board';
            $page_obj_en = get_page_by_path( $slug_en );
            if ( ! $page_obj_en ) {
                wp_insert_post( array(
                    'post_title'   => __( 'Jobs', 'ai-suite' ),
                    'post_name'    => $slug_en,
                    'post_content' => '[ai_suite_jobs]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );
            }
        }
    }
}


if ( ! function_exists( 'aisuite_create_portal_pages' ) ) {
    /**
     * Create default public portal pages (login/register/dashboards).
     */
    function aisuite_create_portal_pages() {
        $pages = array(
            array( 'slug' => 'portal-login', 'title' => __( 'Portal – Login', 'ai-suite' ), 'content' => '[ai_suite_portal_login]' ),
            array( 'slug' => 'inregistrare-candidat', 'title' => __( 'Înregistrare Candidat', 'ai-suite' ), 'content' => '[ai_suite_candidate_register]' ),
            array( 'slug' => 'inregistrare-companie', 'title' => __( 'Înregistrare Companie', 'ai-suite' ), 'content' => '[ai_suite_company_register]' ),
            array( 'slug' => 'portal-candidat', 'title' => __( 'Portal Candidat', 'ai-suite' ), 'content' => '[ai_suite_candidate_portal]' ),
            array( 'slug' => 'portal-companie', 'title' => __( 'Portal Companie', 'ai-suite' ), 'content' => '[ai_suite_company_portal]' ),
        );

        // EN pages are optional to avoid cluttering menus.
        $enable_en = function_exists('aisuite_enable_en_pages') ? aisuite_enable_en_pages() : (int) get_option( 'ai_suite_enable_en_pages', 0 );

        if ( $enable_en ) {
            $pages = array_merge( $pages, array(
                array( 'slug' => 'portal-login-en', 'title' => __( 'Portal – Login (EN)', 'ai-suite' ), 'content' => '[ai_suite_portal_login]' ),
                array( 'slug' => 'candidate-register-en', 'title' => __( 'Candidate Registration', 'ai-suite' ), 'content' => '[ai_suite_candidate_register]' ),
                array( 'slug' => 'company-register-en', 'title' => __( 'Company Registration', 'ai-suite' ), 'content' => '[ai_suite_company_register]' ),
                array( 'slug' => 'candidate-portal-en', 'title' => __( 'Candidate Portal', 'ai-suite' ), 'content' => '[ai_suite_candidate_portal]' ),
                array( 'slug' => 'company-portal-en', 'title' => __( 'Company Portal', 'ai-suite' ), 'content' => '[ai_suite_company_portal]' ),
                array( 'slug' => 'portal-en', 'title' => __( 'Portal', 'ai-suite' ), 'content' => '[ai_suite_portal_hub]' ),
            ) );
        }

        foreach ( $pages as $p ) {
            $slug = $p['slug'];
            $page_obj = get_page_by_path( $slug );
            if ( ! $page_obj ) {
                wp_insert_post( array(
                    'post_title'   => $p['title'],
                    'post_name'    => $slug,
                    'post_content' => $p['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );
            }
        }
    }
}

if ( ! function_exists( 'aisuite_create_portal_hub_page' ) ) {
    /**
     * Create a simple frontend hub page so you always know where to go.
     */
    function aisuite_create_portal_hub_page() {
        $slug     = 'portal';
        $page_obj = get_page_by_path( $slug );
        if ( ! $page_obj ) {
            wp_insert_post( array(
                'post_title'   => __( 'Portal', 'ai-suite' ),
                'post_name'    => $slug,
                'post_content' => '[ai_suite_portal_hub]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
        }
    }
}

if ( ! function_exists( 'aisuite_create_nav_menu' ) ) {
    /**
     * Create/refresh a clean frontend menu for the recruitment pages.
     *
     * Many themes show an "auto" menu (all pages) when no menu is assigned.
     * This function builds a curated menu (ordered, without duplicates) and
     * tries to assign it to the theme's primary location.
     *
     * @param bool $force Rebuild menu items even if the menu exists.
     * @return array{menu_id:int, assigned:bool, message:string}
     */
    function aisuite_create_nav_menu( $force = false ) {
        if ( ! function_exists( 'wp_create_nav_menu' ) || ! function_exists( 'wp_update_nav_menu_item' ) ) {
            return array( 'menu_id' => 0, 'assigned' => false, 'message' => 'Menus API not available.' );
        }

        $menu_name = 'AI Suite – Recruitment';
        $menu_obj  = wp_get_nav_menu_object( $menu_name );
        $menu_id   = $menu_obj && ! is_wp_error( $menu_obj ) ? (int) $menu_obj->term_id : 0;

        if ( ! $menu_id ) {
            $menu_id = (int) wp_create_nav_menu( $menu_name );
        }

        if ( ! $menu_id ) {
            return array( 'menu_id' => 0, 'assigned' => false, 'message' => 'Could not create menu.' );
        }

        // Ensure core pages exist first (so we can attach menu items by page ID).
        if ( function_exists( 'aisuite_create_default_pages' ) ) {
            aisuite_create_default_pages();
        }
        if ( function_exists( 'aisuite_create_portal_pages' ) ) {
            aisuite_create_portal_pages();
        }
        if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
            aisuite_create_portal_hub_page();
        }
        // Rebuild items if forced OR if menu has no items.
        $items = wp_get_nav_menu_items( $menu_id );
        $has_items = is_array( $items ) && ! empty( $items );
        if ( $force || ! $has_items ) {
            // Remove existing items to avoid duplicates.
            if ( $has_items ) {
                foreach ( $items as $it ) {
                    wp_delete_post( (int) $it->ID, true );
                }
            }

            $is_ro = false;
            $loc = function_exists( 'get_locale' ) ? (string) get_locale() : '';
            if ( $loc && ( strpos( $loc, 'ro_' ) === 0 || $loc === 'ro_RO' ) ) {
                $is_ro = true;
            }

            $pages = array(
                array( 'slug' => $is_ro ? 'joburi' : 'jobs-board', 'label' => $is_ro ? 'Joburi' : 'Jobs' ),
                array( 'slug' => $is_ro ? 'portal-login' : 'portal-login-en', 'label' => $is_ro ? 'Portal – Login' : 'Portal Login' ),
                array( 'slug' => $is_ro ? 'inregistrare-candidat' : 'candidate-register-en', 'label' => $is_ro ? 'Înregistrare Candidat' : 'Candidate Registration' ),
                array( 'slug' => $is_ro ? 'inregistrare-companie' : 'company-register-en', 'label' => $is_ro ? 'Înregistrare Companie' : 'Company Registration' ),
                array( 'slug' => $is_ro ? 'portal-candidat' : 'candidate-portal-en', 'label' => $is_ro ? 'Portal Candidat' : 'Candidate Portal' ),
                array( 'slug' => $is_ro ? 'portal-companie' : 'company-portal-en', 'label' => $is_ro ? 'Portal Companie' : 'Company Portal' ),
            );

            $pages = apply_filters( 'ai_suite_frontend_menu_items', $pages, $is_ro );

            foreach ( $pages as $p ) {
                $slug = isset( $p['slug'] ) ? sanitize_title( (string) $p['slug'] ) : '';
                if ( ! $slug ) { continue; }
                $page = get_page_by_path( $slug );
                if ( ! $page || empty( $page->ID ) ) {
                    continue;
                }
                $label = isset( $p['label'] ) ? (string) $p['label'] : get_the_title( (int) $page->ID );

                wp_update_nav_menu_item( $menu_id, 0, array(
                    'menu-item-object-id' => (int) $page->ID,
                    'menu-item-object'    => 'page',
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                    'menu-item-title'     => $label,
                ) );
            }
        }

        // Try to assign the menu to a theme location (best-effort).
        $assigned = false;
        if ( function_exists( 'get_nav_menu_locations' ) && function_exists( 'set_theme_mod' ) ) {
            $locations = (array) get_nav_menu_locations();
            if ( ! empty( $locations ) ) {
                // Prefer common primary locations.
                $prefer = array( 'primary', 'menu-1', 'main-menu', 'header-menu', 'top', 'top-menu' );
                $chosen = '';
                foreach ( $prefer as $k ) {
                    if ( array_key_exists( $k, $locations ) ) {
                        $chosen = $k;
                        break;
                    }
                }
                if ( ! $chosen ) {
                    // Fallback to the first registered location.
                    $keys = array_keys( $locations );
                    $chosen = isset( $keys[0] ) ? (string) $keys[0] : '';
                }
                if ( $chosen ) {
                    $locations[ $chosen ] = $menu_id;
                    set_theme_mod( 'nav_menu_locations', $locations );
                    $assigned = true;
                }
            }
        }

        $msg = $assigned ? 'Menu created and assigned.' : 'Menu created (not auto-assigned).';
        return array( 'menu_id' => $menu_id, 'assigned' => $assigned, 'message' => $msg );
    }
}

if ( ! function_exists( 'aisuite_register_cpts_for_activation' ) ) {
    /**
     * Ensure CPTs are registered before flush_rewrite_rules() on activation.
     */
    function aisuite_register_cpts_for_activation() {
        $cpt_file = AI_SUITE_DIR . 'includes/recruitment/cpt.php';
        if ( file_exists( $cpt_file ) ) {
            require_once $cpt_file;
        }

        // If the CPT file provides an explicit function, call it.
        if ( function_exists( 'aisuite_register_recruitment_cpts' ) ) {
            aisuite_register_recruitment_cpts();
        }
    }
}

if ( ! function_exists( 'aisuite_install' ) ) {
    /**
     * Activation routine.
     */
    function aisuite_install() {
        aisuite_add_caps();
        aisuite_create_default_pages();
        aisuite_create_portal_pages();
        aisuite_create_portal_hub_page();

        // Create a curated frontend menu to avoid the theme's auto-page menu.
        if ( function_exists( 'aisuite_create_nav_menu' ) ) {
            aisuite_create_nav_menu( true );
        }

        // v1.9.2: ensure AI Queue table exists
        if ( function_exists( 'ai_suite_queue_install' ) ) {
            ai_suite_queue_install();
        }

        if ( function_exists( 'ai_suite_subscription_install' ) ) {
            ai_suite_subscription_install();
        }

        aisuite_register_cpts_for_activation();
        flush_rewrite_rules();

        // Store installed version for future upgrades.
        update_option( AI_SUITE_OPTION_INSTALLED_VER, AI_SUITE_VER, false );
    }
}

if ( ! function_exists( 'aisuite_maybe_upgrade' ) ) {
    /**
     * Run light upgrade steps when plugin version changes.
     */
    function aisuite_maybe_upgrade() {
        $installed = get_option( AI_SUITE_OPTION_INSTALLED_VER, '' );

        if ( empty( $installed ) ) {
            // Fresh installs may bypass activation in some edge cases.
            update_option( AI_SUITE_OPTION_INSTALLED_VER, AI_SUITE_VER, false );
            return;
        }

        if ( version_compare( $installed, AI_SUITE_VER, '>=' ) ) {
            return;
        }

        // Ensure caps exist.
        aisuite_add_caps();

        // Ensure portal pages exist.
        if ( function_exists( 'aisuite_create_portal_pages' ) ) {
            aisuite_create_portal_pages();
        }
        if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
            aisuite_create_portal_hub_page();
        }

        // v1.9.2: ensure AI Queue table exists
        if ( function_exists( 'ai_suite_queue_install' ) ) {
            ai_suite_queue_install();
        }

        if ( function_exists( 'ai_suite_subscription_install' ) ) {
            ai_suite_subscription_install();
        }

        update_option( AI_SUITE_OPTION_INSTALLED_VER, AI_SUITE_VER, false );
    }
}

// Hook activation.
register_activation_hook( AI_SUITE_FILE, 'aisuite_install' );

// Hook upgrades.
add_action( 'admin_init', 'aisuite_maybe_upgrade' );


if ( ! function_exists( 'aisuite_install_or_upgrade' ) ) {
    /**
     * Install / upgrade entrypoint.
     *
     * @param bool $force Force recreate pages and (re)install queue table.
     */
    function aisuite_install_or_upgrade( $force = false ) {
        $installed = get_option( AI_SUITE_OPTION_INSTALLED_VER, '' );
        $current   = defined( 'AI_SUITE_VER' ) ? AI_SUITE_VER : '0.0.0';

        // Always ensure caps/CPTs/pages exist (lightweight).
        if ( function_exists( 'aisuite_add_caps' ) ) {
            aisuite_add_caps();
        }
        if ( function_exists( 'aisuite_register_recruitment_cpts' ) ) {
            aisuite_register_recruitment_cpts();
        }
        if ( function_exists( 'aisuite_create_portal_pages' ) ) {
            aisuite_create_portal_pages();
        }
        if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
            aisuite_create_portal_hub_page();
        }
        if ( function_exists( 'aisuite_create_default_pages' ) ) {
            aisuite_create_default_pages();
        }

        // Determine if we must run heavier install tasks (dbDelta, menu rebuild).
        $needs_upgrade = (bool) $force;
        if ( ! $needs_upgrade ) {
            if ( empty( $installed ) || version_compare( (string) $installed, (string) $current, '<' ) ) {
                $needs_upgrade = true;
            }
        }

        // Curated frontend menu (ordered, no duplicates). Rebuild items only when forced or empty.
        if ( function_exists( 'aisuite_create_nav_menu' ) ) {
            aisuite_create_nav_menu( (bool) $force );
        }

        // Heavy tasks only on install/upgrade (avoid running dbDelta on every request).
        if ( $needs_upgrade ) {
            if ( function_exists( 'ai_suite_queue_install' ) ) {
                ai_suite_queue_install();
            }
            if ( function_exists( 'ai_suite_subscription_install' ) ) {
                ai_suite_subscription_install();
            }
            if ( function_exists( 'ai_suite_billing_history_install' ) ) {
                ai_suite_billing_history_install();
            }
            update_option( AI_SUITE_OPTION_INSTALLED_VER, $current, false );
        }
    }
}
