<?php
/**
 * Safe Boot / Hardening layer for AI Suite.
 *
 * Goals:
 * - Detect fatal errors and record crash details.
 * - Auto-enable Safe Mode after a crash to avoid wp-admin lockouts.
 * - Auto-disable the module that caused the crash (best-effort mapping).
 *
 * NOTE: We cannot prevent the *first* fatal from happening (PHP parse/error),
 * but we can prevent repeated lockouts on subsequent requests.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_FATAL' ) ) {
    define( 'AI_SUITE_SAFEBOOT_OPT_FATAL', 'ai_suite_last_fatal' );
}
if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_DISABLED' ) ) {
    define( 'AI_SUITE_SAFEBOOT_OPT_DISABLED', 'ai_suite_disabled_modules' );
}
if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE' ) ) {
    define( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE', 'ai_suite_safe_mode' );
}
if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL' ) ) {
    define( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL', 'ai_suite_safe_mode_until' );
}

if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING' ) ) {
    define( 'AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING', 'ai_suite_safeboot_email_pending' );
}

if ( ! function_exists( 'aisuite_safe_boot_init' ) ) {
    function aisuite_safe_boot_init() {
        // If plugin was updated, clear stale crash notices and remove essential modules from disabled list.
        $seen_ver = (string) get_option( 'ai_suite_version_seen', '' );
        $cur_ver  = defined( 'AI_SUITE_VER' ) ? (string) AI_SUITE_VER : '';
        if ( $cur_ver && $seen_ver !== $cur_ver ) {
            // Clear stale crash data on version change (most crashes are fixed by updates).
            update_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array(), false );
            update_option( AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING, array( 'sent' => 1, 'time' => time() ), false );
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE, 0, false );
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0, false );

            // Ensure essential modules cannot remain disabled after an update.
            $disabled = get_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array() );
            if ( is_array( $disabled ) ) {
                $essential = function_exists( 'aisuite_safe_boot_essential_modules' ) ? aisuite_safe_boot_essential_modules() : array();
                $disabled  = array_values( array_diff( array_map( 'sanitize_key', $disabled ), array_map( 'sanitize_key', (array) $essential ) ) );
                update_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, $disabled, false );
            }

            update_option( 'ai_suite_version_seen', $cur_ver, false );
        }

        // Normalize Safe Mode TTL.
        $until = (int) get_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0 );
        if ( $until && time() > $until ) {
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE, 0, false );
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0, false );
        }

        // Register shutdown handler for fatal error detection.
        aisuite_safe_boot_register_shutdown();

        // Email notification (best-effort): send last fatal details once to admin.
        add_action( 'admin_init', function() {
            $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
            if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! current_user_can( $cap ) ) {
                return;
            }
            if ( ! defined( 'AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING' ) ) {
                return;
            }
            $pending = get_option( AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING, array() );
            if ( empty( $pending ) || ! is_array( $pending ) || ! empty( $pending['sent'] ) ) {
                return;
            }

            $fatal = get_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array() );
            $disabled = get_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array() );

            $site = function_exists( 'home_url' ) ? home_url() : '';
            $subject = 'AI Suite: Safe Mode activat (fatal error)';
            $msg  = "S-a detectat o eroare fatală și AI Suite a intrat în Safe Mode.\n\n";
            $msg .= "Site: " . $site . "\n";
            $msg .= "Timp: " . (string) current_time( 'mysql' ) . "\n";
            if ( is_array( $disabled ) && ! empty( $disabled ) ) {
                $msg .= "Module dezactivate: " . implode( ', ', array_map( 'sanitize_key', $disabled ) ) . "\n";
            }
            if ( is_array( $fatal ) && ! empty( $fatal['message'] ) ) {
                $msg .= "\nEroare: " . (string) $fatal['message'] . "\n";
                $msg .= "Fișier: " . (string) ( $fatal['file'] ?? '' ) . ":" . (string) ( $fatal['line'] ?? '' ) . "\n";
            }

            if ( function_exists( 'aisuite_notify_admin' ) ) {
                aisuite_notify_admin( $subject, $msg );
            }

            // Mark as sent.
            $pending['sent'] = 1;
            update_option( AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING, $pending, false );
        }, 9 );


        // Show admin notice if Safe Mode is active.
        add_action( 'admin_notices', 'aisuite_safe_boot_admin_notice', 5 );

        // Hide tabs for disabled modules.
        add_filter( 'ai_suite_tabs', 'aisuite_safe_boot_filter_tabs', 50 );
    }
}

if ( ! function_exists( 'aisuite_safe_boot_register_shutdown' ) ) {
    function aisuite_safe_boot_register_shutdown() {
        static $registered = false;
        if ( $registered ) { return; }
        $registered = true;

        register_shutdown_function( function() {
            $err = error_get_last();
            if ( ! is_array( $err ) ) {
                return;
            }

            $type = isset( $err['type'] ) ? (int) $err['type'] : 0;
            $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
            if ( ! in_array( $type, $fatal_types, true ) ) {
                return;
            }

            $file = isset( $err['file'] ) ? (string) $err['file'] : '';
            if ( ! $file ) {
                return;
            }

            // Only record if it looks like our plugin.
            $plugin_dir = defined( 'AI_SUITE_DIR' ) ? (string) AI_SUITE_DIR : '';
            if ( $plugin_dir && strpos( $file, $plugin_dir ) !== 0 ) {
                return;
            }

            $payload = array(
                'time'    => time(),
                'type'    => $type,
                'message' => isset( $err['message'] ) ? (string) $err['message'] : '',
                'file'    => $file,
                'line'    => isset( $err['line'] ) ? (int) $err['line'] : 0,
            );
            update_option( AI_SUITE_SAFEBOOT_OPT_FATAL, $payload, false );

            // Mark that we should email the admin about this crash on the next admin page load.
            if ( defined( 'AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING' ) ) {
                update_option( AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING, array( 'sent' => 0, 'time' => time() ), false );
            }

            // Best-effort: map file to module and disable it.
            $module = aisuite_safe_boot_file_to_module( $file );
            // Never disable essential modules (core/admin/recruitment) - we must keep recovery access.
            if ( $module && ! in_array( $module, aisuite_safe_boot_essential_modules(), true ) ) {
                $disabled = aisuite_safe_boot_get_disabled_modules();
                if ( ! in_array( $module, $disabled, true ) ) {
                    $disabled[] = $module;
                    update_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array_values( array_unique( $disabled ) ), false );
                }
            }

            // Turn on Safe Mode for 15 minutes.
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE, 1, false );
            update_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, time() + ( 15 * MINUTE_IN_SECONDS ), false );
        } );
    }
}

if ( ! function_exists( 'aisuite_is_safe_mode' ) ) {
    function aisuite_is_safe_mode() {
        return (bool) get_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE, 0 );
    }
}

if ( ! function_exists( 'aisuite_safe_boot_get_disabled_modules' ) ) {
    function aisuite_safe_boot_get_disabled_modules() {
        $disabled = get_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array() );
        if ( ! is_array( $disabled ) ) {
            $disabled = array();
        }
        $disabled = array_values( array_filter( array_map( 'sanitize_key', $disabled ) ) );
        return array_values( array_unique( $disabled ) );
    }
}

if ( ! function_exists( 'aisuite_safe_boot_essential_modules' ) ) {
    function aisuite_safe_boot_essential_modules() {
        // Modules that must stay on even in Safe Mode (to allow admin recovery).
        return array( 'core', 'admin', 'recruitment' );
    }
}

if ( ! function_exists( 'aisuite_is_module_enabled' ) ) {
    function aisuite_is_module_enabled( $module ) {
        $module = sanitize_key( (string) $module );
        if ( ! $module ) {
            return true;
        }

        $disabled = aisuite_safe_boot_get_disabled_modules();
        if ( in_array( $module, $disabled, true ) ) {
            return false;
        }

        // In Safe Mode, only keep essential modules.
        if ( aisuite_is_safe_mode() ) {
            return in_array( $module, aisuite_safe_boot_essential_modules(), true );
        }

        return true;
    }
}

if ( ! function_exists( 'aisuite_safe_require_once' ) ) {
    function aisuite_safe_require_once( $path, $module = 'core' ) {
        if ( ! aisuite_is_module_enabled( $module ) ) {
            return false;
        }
        if ( file_exists( $path ) ) {
            require_once $path;
            return true;
        }
        return false;
    }
}

if ( ! function_exists( 'aisuite_safe_boot_file_to_module' ) ) {
    function aisuite_safe_boot_file_to_module( $file ) {
        $file = (string) $file;
        if ( ! $file ) {
            return '';
        }
        $p = str_replace( '\\', '/', $file );

        if ( strpos( $p, '/includes/billing/' ) !== false ) {
            return 'billing';
        }
        if ( strpos( $p, '/includes/recruitment/facebook-leads.php' ) !== false ) {
            return 'facebook';
        }
        if ( strpos( $p, '/includes/bots/' ) !== false ) {
            return 'bots';
        }
        if ( strpos( $p, '/includes/admin/' ) !== false ) {
            return 'admin';
        }
        if ( strpos( $p, '/includes/recruitment/' ) !== false ) {
            return 'recruitment';
        }
        if ( strpos( $p, '/includes/' ) !== false ) {
            return 'core';
        }
        return 'core';
    }
}

if ( ! function_exists( 'aisuite_safe_boot_admin_notice' ) ) {
    function aisuite_safe_boot_admin_notice() {
        if ( ! function_exists( 'current_user_can' ) ) {
            return;
        }
        $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            return;
        }

        $fatal = get_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array() );
        $has_fatal = is_array( $fatal ) && ! empty( $fatal['file'] );

        if ( ! aisuite_is_safe_mode() && ! $has_fatal ) {
            return;
        }

        $url = admin_url( 'admin.php?page=ai-suite&tab=tools' );
        echo '<div class="notice notice-warning" style="border-left-color:#d63638;">';
        echo '<p><strong>AI Suite:</strong> ';
        if ( aisuite_is_safe_mode() ) {
            $until = (int) get_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0 );
            $mins  = $until ? max( 1, (int) ceil( ( $until - time() ) / 60 ) ) : 0;
            echo 'Safe Mode este activ' . ( $mins ? ' (aprox. ' . esc_html( $mins ) . ' min)' : '' ) . '. ';
        }
        if ( $has_fatal ) {
            $file = basename( (string) $fatal['file'] );
            echo 'Ultimul crash: <code>' . esc_html( $file ) . '</code>. ';
        }
        echo '<a href="' . esc_url( $url ) . '">Deschide Unelte</a> pentru reset / module.</p>';
        echo '</div>';
    }
}

if ( ! function_exists( 'aisuite_safe_boot_filter_tabs' ) ) {
    function aisuite_safe_boot_filter_tabs( $tabs ) {
        if ( ! is_array( $tabs ) ) {
            return $tabs;
        }

        // Hide optional tabs when the module is disabled or in Safe Mode.
        if ( ! aisuite_is_module_enabled( 'facebook' ) ) {
            unset( $tabs['facebook_leads'] );
        }
        if ( ! aisuite_is_module_enabled( 'billing' ) ) {
            unset( $tabs['billing'] );
        }
        if ( ! aisuite_is_module_enabled( 'bots' ) ) {
            unset( $tabs['bots'] );
        }

        return $tabs;
    }
}
