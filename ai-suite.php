<?php
/**
 * Plugin Name: AI Suite
 * Description: Modular AI bots suite with recruitment module (jobs, candidates, applications) for WordPress.
 * Version: 5.5.0
 * Author: RecruMax
 * Text Domain: ai-suite
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// i18n
// WP 6.7+ notice fix: avoid "just-in-time" early translation load notices.
// Load the textdomain early enough so any labels used during admin bootstrap won't trigger JIT loading.
add_action( 'init', function() {
    load_plugin_textdomain( 'ai-suite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Polylang support: register key public strings if available.
    if ( function_exists( 'pll_register_string' ) ) {
        pll_register_string( 'ai_suite_jobs', 'Joburi', 'AI Suite' );
        pll_register_string( 'ai_suite_apply', 'Aplică', 'AI Suite' );
    }
}, 5 );

// Constants
if ( ! defined( 'AI_SUITE_FILE' ) ) { define( 'AI_SUITE_FILE', __FILE__ ); }
if ( ! defined( 'AI_SUITE_DIR' ) )  { define( 'AI_SUITE_DIR', plugin_dir_path( __FILE__ ) ); }
if ( ! defined( 'AI_SUITE_URL' ) )  { define( 'AI_SUITE_URL', plugin_dir_url( __FILE__ ) ); }
if ( ! defined( 'AI_SUITE_VER' ) )  { define('AI_SUITE_VER','5.5.0'); }

// Safe Boot (Hardening): detect fatal errors, enable Safe Mode and disable the faulty module automatically.
// This prevents wp-admin lockouts after a bad update/module.
if ( file_exists( AI_SUITE_DIR . 'includes/safe-boot.php' ) ) {
    require_once AI_SUITE_DIR . 'includes/safe-boot.php';
    if ( function_exists( 'aisuite_safe_boot_init' ) ) {
        aisuite_safe_boot_init();
    }
}

// Activation / Deactivation

// Detect plugin activation request to avoid running heavy init tasks twice.
if ( ! defined( 'AI_SUITE_IS_ACTIVATION' ) ) {
    $ai_suite_is_activation = false;
    if ( function_exists('is_admin') && is_admin() ) {
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        $plugin = isset($_GET['plugin']) ? (string) $_GET['plugin'] : '';
        if ( $action === 'activate' && $plugin === plugin_basename( __FILE__ ) ) {
            $ai_suite_is_activation = true;
        }
    }
    define( 'AI_SUITE_IS_ACTIVATION', $ai_suite_is_activation );
}

register_activation_hook( __FILE__, function() {
    if ( file_exists( AI_SUITE_DIR . 'includes/install.php' ) ) {
        require_once AI_SUITE_DIR . 'includes/install.php';
    }
    if ( file_exists( AI_SUITE_DIR . 'includes/recruitment/cpt.php' ) ) {
        require_once AI_SUITE_DIR . 'includes/recruitment/cpt.php';
    }
    if ( function_exists( 'aisuite_install_or_upgrade' ) ) {
        aisuite_install_or_upgrade( true );
    }
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
    }
} );

register_deactivation_hook( __FILE__, function() {
    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
    }
} );

// Loader
require_once AI_SUITE_DIR . 'includes/loader.php';
// Runtime safety: ensure portal pages exist even after file-replace updates (no deactivate/activate).
add_action( 'init', function() {
    if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
        return;
    }

    // Only admins (or users with plugin capability).
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $cap ) ) {
        return;
    }

    // Throttle checks (once per day).
    $last = (int) get_option( 'ai_suite_pages_last_check', 0 );
    if ( $last && ( time() - $last ) < DAY_IN_SECONDS ) {
        return;
    }

    // Load install helpers if needed.
    if ( file_exists( AI_SUITE_DIR . 'includes/install.php' ) ) {
        require_once AI_SUITE_DIR . 'includes/install.php';
    }

    $slugs = array(
        'portal',
        'portal-login',
        'inregistrare-candidat',
        'inregistrare-companie',
        'portal-candidat',
        'portal-companie',
        'joburi',
    );

    $missing = false;
    foreach ( $slugs as $slug ) {
        if ( ! get_page_by_path( $slug ) ) {
            $missing = true;
            break;
        }
    }

    if ( $missing ) {
        if ( function_exists( 'aisuite_create_default_pages' ) ) {
            aisuite_create_default_pages();
        }
        if ( function_exists( 'aisuite_create_portal_pages' ) ) {
            aisuite_create_portal_pages();
        }
        if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
            aisuite_create_portal_hub_page();
        }
        if ( function_exists( 'ai_suite_queue_install' ) ) {
            ai_suite_queue_install();
        }
        // Hardening: don't flush rewrites on every init (can freeze/slow wp-admin on large sites).
        // Instead, mark that a flush is needed and let the admin Tools action do it safely.
        update_option( 'ai_suite_needs_flush', 1, false );
    }

    update_option( 'ai_suite_pages_last_check', time(), false );
}, 50 );

// One-time safe rewrite flush if a previous auto-repair created missing pages.
add_action( 'admin_init', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $cap ) ) {
        return;
    }
    $needs = (int) get_option( 'ai_suite_needs_flush', 0 );
    if ( ! $needs ) {
        return;
    }

    // Avoid doing heavy work during AJAX.
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    if ( function_exists( 'flush_rewrite_rules' ) ) {
        flush_rewrite_rules();
    }
    update_option( 'ai_suite_needs_flush', 0, false );
}, 30 );

// Admin notice if permalinks are Plain (causes /portal-companie 404).
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'current_user_can' ) ) {
        return;
    }
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        return;
    }
    $structure = (string) get_option( 'permalink_structure', '' );
    if ( $structure !== '' ) {
        return;
    }

    // Show only on Plugins and Permalinks pages to avoid noise.
    $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    if ( $pagenow && ! in_array( $pagenow, array( 'plugins.php', 'options-permalink.php' ), true ) ) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>AI Suite:</strong> Permalinks sunt pe <em>Plain</em>. Linkurile de tip <code>/portal-companie</code> pot afișa 404. Setează <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">Permalinks</a> pe <em>Post name</em>, apoi mergi la <strong>AI Suite → Unelte</strong> și apasă „Recreează paginile + repară permalinks”.</p></div>';
}, 20 );

// Setup Wizard reminder (runs only for admins until marked done).
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'current_user_can' ) ) {
        return;
    }
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        return;
    }
    if ( (bool) get_option( 'ai_suite_setup_done', 0 ) ) {
        return;
    }

    $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    $is_ai_suite = ( isset( $_GET['page'] ) && 'ai-suite' === (string) $_GET['page'] );
    if ( ! $is_ai_suite && 'plugins.php' !== $pagenow ) {
        return;
    }

    $url = admin_url( 'admin.php?page=ai-suite&tab=wizard' );
    echo '<div class="notice notice-info"><p><strong>AI Suite:</strong> Recomandat: rulează <a href="' . esc_url( $url ) . '"><strong>Asistentul de configurare</strong></a> (Wizard) ca să verifici paginile, meniurile și OpenAI.</p></div>';
}, 21 );


add_action( 'init', function() {
    if ( class_exists( 'AI_Suite_Loader' ) ) {
        AI_Suite_Loader::instance()->boot();
    }
}, 5 );
