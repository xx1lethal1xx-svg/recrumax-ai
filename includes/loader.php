<?php
/**
 * Loader class for AI Suite.
 *
 * Bootstraps all plugin modules in a safe, deterministic order.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'AI_Suite_Loader' ) ) {
final class AI_Suite_Loader {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot() {
        $this->include_files();

        // Boot core modules
        if ( class_exists( 'AI_Suite_Portal_Frontend' ) ) {
            AI_Suite_Portal_Frontend::boot();
        }
        if ( class_exists( 'AI_Suite_Frontend' ) ) {
            AI_Suite_Frontend::boot();
        }

        // Admin boots via hooks in includes/admin/*

    }

    private function include_files() {
        // Map files to modules so Safe Mode can disable risky parts and keep wp-admin usable.
        $files = array(
            array( 'module' => 'core',        'file' => 'includes/constants.php' ),
            array( 'module' => 'core',        'file' => 'includes/helpers.php' ),
            array( 'module' => 'core',        'file' => 'includes/ui.php' ),
            array( 'module' => 'core',        'file' => 'includes/logger.php' ),
            array( 'module' => 'core',        'file' => 'includes/install.php' ),
            array( 'module' => 'core',        'file' => 'includes/ai-queue.php' ),
            array( 'module' => 'core',        'file' => 'includes/cron.php' ),
            array( 'module' => 'core',        'file' => 'includes/autorepair.php' ),
            array( 'module' => 'core',        'file' => 'includes/autopatch.php' ),
            array( 'module' => 'core',        'file' => 'includes/ajax.php' ),
            array( 'module' => 'core',        'file' => 'includes/copilot.php' ),

            // Recruitment core
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/roles.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/cpt.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/featured-jobs.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/companies.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/candidates.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/candidate-index.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/company-team.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/pipeline-settings.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/applications-pro.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/seed.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/ats-pro.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/job-posting-pro.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/portal-frontend.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/communications-pro.php' ),
            array( 'module' => 'facebook',    'file' => 'includes/recruitment/facebook-leads.php' ),
            array( 'module' => 'recruitment', 'file' => 'includes/recruitment/i18n-rewrites.php' ),

            // Billing / Subscriptions
            array( 'module' => 'billing',     'file' => 'includes/billing/history.php' ),
            array( 'module' => 'billing',     'file' => 'includes/billing/netopia.php' ),
            array( 'module' => 'billing',     'file' => 'includes/billing/subscriptions.php' ),

            // Frontend (jobs/apply templates)
            array( 'module' => 'recruitment', 'file' => 'includes/frontend.php' ),

            // Admin
            array( 'module' => 'admin',       'file' => 'includes/admin/tabs.php' ),
            array( 'module' => 'admin',       'file' => 'includes/admin/actions.php' ),
            array( 'module' => 'admin',       'file' => 'includes/admin/page.php' ),
            array( 'module' => 'admin',       'file' => 'includes/admin/menu.php' ),

            // Bots
            array( 'module' => 'core',        'file' => 'includes/registry.php' ),
            array( 'module' => 'core',        'file' => 'includes/bots/bot-interface.php' ),
            array( 'module' => 'core',        'file' => 'includes/bots/bot-healthcheck.php' ),
            array( 'module' => 'bots',        'file' => 'includes/bots/bot-manager.php' ),
            array( 'module' => 'bots',        'file' => 'includes/bots/bot-content.php' ),
            array( 'module' => 'bots',        'file' => 'includes/bots/bot-social.php' ),
        );

        foreach ( $files as $row ) {
            $file   = isset( $row['file'] ) ? (string) $row['file'] : '';
            $module = isset( $row['module'] ) ? (string) $row['module'] : 'core';
            if ( ! $file ) {
                continue;
            }
            $path = trailingslashit( AI_SUITE_DIR ) . ltrim( $file, '/' );
            if ( function_exists( 'aisuite_safe_require_once' ) ) {
                aisuite_safe_require_once( $path, $module );
            } elseif ( file_exists( $path ) ) {
                require_once $path;
            }
        }


        // Ensure default bots exist in registry (needed for Healthcheck/OpenAI buttons).
        // IMPORTANT: delay to init so translations are loaded and we avoid WP 6.7+ JIT notices.
        add_action( 'init', function() {
            if ( class_exists( 'AI_Suite_Registry' ) && method_exists( 'AI_Suite_Registry', 'register_defaults' ) ) {
                AI_Suite_Registry::register_defaults();
            }
        }, 10 );

        // Register CPTs early
        add_action( 'init', function() {
            if ( function_exists( 'aisuite_register_recruitment_cpts' ) ) {
                aisuite_register_recruitment_cpts();
            }
        }, 0 );

        // Ensure portal pages exist (fallback for updates on live sites)
// IMPORTANT: keep this lightweight to avoid slowing down wp-admin.
        add_action( 'init', function() {
            // Skip during plugin activation request to avoid double-running installers.
            if ( defined( 'AI_SUITE_IS_ACTIVATION' ) && AI_SUITE_IS_ACTIVATION ) {
                return;
            }

            $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
            if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! current_user_can( $cap ) ) {
                return;
            }

            // Only run install/upgrade when version changed OR required pages are missing.
            $installed = get_option( AI_SUITE_OPTION_INSTALLED_VER, '' );
            $current   = defined( 'AI_SUITE_VER' ) ? AI_SUITE_VER : '0.0.0';

            $needs = ( empty( $installed ) || version_compare( (string) $installed, (string) $current, '<' ) );

            if ( ! $needs && function_exists( 'get_page_by_path' ) ) {
                $required = array( 'portal-login', 'inregistrare-candidat', 'inregistrare-companie', 'portal-candidat', 'portal-companie' );
                foreach ( $required as $slug ) {
                    if ( ! get_page_by_path( $slug ) ) {
                        $needs = true;
                        break;
                    }
                }
            }

            if ( $needs && function_exists( 'aisuite_install_or_upgrade' ) ) {
                aisuite_install_or_upgrade( false );
            }
        }, 2 );
    }
}
}