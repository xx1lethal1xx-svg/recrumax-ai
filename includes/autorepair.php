<?php
/**
 * Auto-Repair AI (Self-Heal) module.
 *
 * - Rulează diagnostice pentru zonele critice (OpenAI, cron, Safe Mode, portal pages, registry).
 * - Poate aplica "safe fixes" (fără rescriere cod) pentru probleme comune.
 * - Trimite notificări pe email când apar probleme și când se aplică remedieri.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'AI_SUITE_AUTOREPAIR_OPT_STATUS' ) ) {
    define( 'AI_SUITE_AUTOREPAIR_OPT_STATUS', 'ai_suite_autorepair_status' );
}
if ( ! defined( 'AI_SUITE_AUTOREPAIR_OPT_HISTORY' ) ) {
    define( 'AI_SUITE_AUTOREPAIR_OPT_HISTORY', 'ai_suite_autorepair_history' );
}
if ( ! defined( 'AI_SUITE_AUTOREPAIR_OPT_LAST_AI' ) ) {
    define( 'AI_SUITE_AUTOREPAIR_OPT_LAST_AI', 'ai_suite_autorepair_last_ai' );
}

if ( ! function_exists( 'aisuite_autorepair_default_status' ) ) {
    function aisuite_autorepair_default_status() {
        return array(
            'enabled'         => 1,
            'last_run'        => 0,
            'last_email'      => 0,
            'last_issue_hash' => '',
            'last_ai_summary' => '',
            'last_ai_time'    => 0,
        );
    }
}

if ( ! function_exists( 'aisuite_autorepair_get_status' ) ) {
    function aisuite_autorepair_get_status() {
        $st = get_option( AI_SUITE_AUTOREPAIR_OPT_STATUS, array() );
        if ( ! is_array( $st ) ) {
            $st = array();
        }
        return array_merge( aisuite_autorepair_default_status(), $st );
    }
}

if ( ! function_exists( 'aisuite_autorepair_set_status' ) ) {
    function aisuite_autorepair_set_status( $status ) {
        $status = is_array( $status ) ? $status : array();
        $merged = array_merge( aisuite_autorepair_default_status(), $status );
        update_option( AI_SUITE_AUTOREPAIR_OPT_STATUS, $merged, false );
        return $merged;
    }
}

if ( ! function_exists( 'aisuite_autorepair_add_history' ) ) {
    function aisuite_autorepair_add_history( $entry ) {
        $hist = get_option( AI_SUITE_AUTOREPAIR_OPT_HISTORY, array() );
        if ( ! is_array( $hist ) ) {
            $hist = array();
        }
        // Keep last 80.
        if ( count( $hist ) > 80 ) {
            $hist = array_slice( $hist, -80, 80, true );
        }
        $hist[] = $entry;
        update_option( AI_SUITE_AUTOREPAIR_OPT_HISTORY, $hist, false );
    }
}

if ( ! function_exists( 'aisuite_autorepair_collect_recent_logs' ) ) {
    function aisuite_autorepair_collect_recent_logs( $limit = 80 ) {
        $logs = get_option( defined('AI_SUITE_OPTION_LOGS') ? AI_SUITE_OPTION_LOGS : 'ai_suite_logs', array() );
        if ( ! is_array( $logs ) ) {
            return array();
        }
        $logs = array_slice( $logs, -absint( $limit ) );
        // Reduce payload size.
        $out = array();
        foreach ( $logs as $row ) {
            $out[] = array(
                'time'    => $row['time'] ?? '',
                'level'   => $row['level'] ?? '',
                'message' => $row['message'] ?? '',
                'context' => is_array( $row['context'] ?? null ) ? $row['context'] : array(),
            );
        }
        return $out;
    }
}

if ( ! function_exists( 'aisuite_autorepair_detect_issues' ) ) {
    function aisuite_autorepair_detect_issues() {
        $issues = array();

        // 1) OpenAI key.
        $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
        $key = trim( (string) ( $settings['openai_api_key'] ?? '' ) );
        if ( $key === '' ) {
            $issues[] = array(
                'id'       => 'openai_missing_key',
                'severity' => 'critical',
                'title'    => __( 'Cheia OpenAI lipsește', 'ai-suite' ),
                'details'  => __( 'Setează cheia în tabul Setări / Asistent configurare. Fără cheie, boții AI și Copilot nu pot funcționa.', 'ai-suite' ),
                'fixable'  => false,
            );
        }

        // 2) Safe Mode.
        $safe_mode = function_exists( 'aisuite_is_safe_mode' ) ? (bool) aisuite_is_safe_mode() : ( (int) get_option( defined('AI_SUITE_SAFEBOOT_OPT_SAFEMODE') ? AI_SUITE_SAFEBOOT_OPT_SAFEMODE : 'ai_suite_safe_mode', 0 ) === 1 );
        if ( $safe_mode ) {
            $fatal = get_option( defined('AI_SUITE_SAFEBOOT_OPT_FATAL') ? AI_SUITE_SAFEBOOT_OPT_FATAL : 'ai_suite_last_fatal', array() );
            $msg   = '';
            if ( is_array( $fatal ) ) {
                $msg = (string) ( $fatal['message'] ?? '' );
                if ( $msg && strlen( $msg ) > 160 ) {
                    $msg = substr( $msg, 0, 160 ) . '...';
                }
            }
            $issues[] = array(
                'id'       => 'safe_mode_active',
                'severity' => 'critical',
                'title'    => __( 'Safe Mode este activ', 'ai-suite' ),
                'details'  => $msg ? ( __( 'Ultima eroare: ', 'ai-suite' ) . $msg ) : __( 'Pluginul a detectat un crash anterior și a activat Safe Mode pentru a preveni blocarea wp-admin.', 'ai-suite' ),
                'fixable'  => true,
                'fix_id'   => 'clear_safe_mode',
                'fix_label'=> __( 'Dezactivează Safe Mode (cu grijă)', 'ai-suite' ),
            );
        }

        // 3) Disabled modules.
        $disabled = get_option( defined('AI_SUITE_SAFEBOOT_OPT_DISABLED') ? AI_SUITE_SAFEBOOT_OPT_DISABLED : 'ai_suite_disabled_modules', array() );
        if ( is_array( $disabled ) && ! empty( $disabled ) ) {
            $issues[] = array(
                'id'       => 'disabled_modules',
                'severity' => 'warning',
                'title'    => __( 'Unele module sunt dezactivate (Safe Boot)', 'ai-suite' ),
                'details'  => sprintf( __( 'Module dezactivate: %s', 'ai-suite' ), implode( ', ', array_map( 'sanitize_key', $disabled ) ) ),
                'fixable'  => true,
                'fix_id'   => 'reenable_modules',
                'fix_label'=> __( 'Re-activează modulele', 'ai-suite' ),
            );
        }

        // 4) Cron.
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            $issues[] = array(
                'id'       => 'wp_cron_disabled',
                'severity' => 'warning',
                'title'    => __( 'WP-Cron este dezactivat', 'ai-suite' ),
                'details'  => __( 'DISABLE_WP_CRON este true. Automatizările pot să nu ruleze. Recomand: cron real pe server.', 'ai-suite' ),
                'fixable'  => false,
            );
        }

        $missing = array();
        if ( ! wp_next_scheduled( 'ai_suite_cron_48h' ) ) {
            $missing[] = 'ai_suite_cron_48h';
        }
        if ( ! wp_next_scheduled( 'ai_suite_ai_queue_tick' ) ) {
            $missing[] = 'ai_suite_ai_queue_tick';
        }
        if ( ! empty( $missing ) ) {
            $issues[] = array(
                'id'       => 'cron_missing',
                'severity' => 'warning',
                'title'    => __( 'Unele task-uri CRON nu sunt programate', 'ai-suite' ),
                'details'  => sprintf( __( 'Lipsesc: %s', 'ai-suite' ), implode( ', ', $missing ) ),
                'fixable'  => true,
                'fix_id'   => 'schedule_cron',
                'fix_label'=> __( 'Reprogramează CRON', 'ai-suite' ),
            );
        }

        // 5) Portal pages.
        $slugs = array( 'portal', 'portal-login', 'portal-candidat', 'portal-companie', 'inregistrare-candidat', 'inregistrare-companie' );
        $missing_pages = array();
        foreach ( $slugs as $slug ) {
            $p = get_page_by_path( $slug );
            if ( ! $p ) {
                $missing_pages[] = $slug;
            }
        }
        if ( ! empty( $missing_pages ) ) {
            $issues[] = array(
                'id'       => 'portal_pages_missing',
                'severity' => 'warning',
                'title'    => __( 'Lipsesc pagini de portal', 'ai-suite' ),
                'details'  => sprintf( __( 'Slug-uri lipsă: %s', 'ai-suite' ), implode( ', ', $missing_pages ) ),
                'fixable'  => true,
                'fix_id'   => 'repair_portal_pages',
                'fix_label'=> __( 'Creează/Repară pagini portal', 'ai-suite' ),
            );
        }

        // 6) Registry.
        $reg = get_option( 'ai_suite_registry', array() );
        if ( ! is_array( $reg ) || empty( $reg ) ) {
            $issues[] = array(
                'id'       => 'registry_empty',
                'severity' => 'warning',
                'title'    => __( 'Registry gol / neinițializat', 'ai-suite' ),
                'details'  => __( 'Registry-ul boților (ai_suite_registry) este gol. Unele funcții pot să raporteze "Clasa bot lipsește".', 'ai-suite' ),
                'fixable'  => true,
                'fix_id'   => 'rebuild_registry',
                'fix_label'=> __( 'Regenerează registry', 'ai-suite' ),
            );
        }

        // 7) Portal JS issues from logs.
        $logs = aisuite_autorepair_collect_recent_logs( 120 );
        $js_count = 0;
        foreach ( $logs as $row ) {
            if ( ( $row['message'] ?? '' ) === 'Portal JS issue' ) {
                $js_count++;
            }
        }
        if ( $js_count > 0 ) {
            $issues[] = array(
                'id'       => 'portal_js_issues',
                'severity' => 'info',
                'title'    => __( 'S-au înregistrat erori JS în portal', 'ai-suite' ),
                'details'  => sprintf( __( 'Au fost logate %d evenimente recente de tip "Portal JS issue". Vezi tabul Jurnal activitate pentru detalii.', 'ai-suite' ), $js_count ),
                'fixable'  => false,
            );
        }

        return $issues;
    }
}

if ( ! function_exists( 'aisuite_autorepair_apply_fix' ) ) {
    function aisuite_autorepair_apply_fix( $fix_id ) {
        $fix_id = sanitize_key( (string) $fix_id );
        $done   = false;
        $note   = '';

        switch ( $fix_id ) {
            case 'clear_safe_mode':
                update_option( defined('AI_SUITE_SAFEBOOT_OPT_FATAL') ? AI_SUITE_SAFEBOOT_OPT_FATAL : 'ai_suite_last_fatal', array(), false );
                update_option( defined('AI_SUITE_SAFEBOOT_OPT_SAFEMODE') ? AI_SUITE_SAFEBOOT_OPT_SAFEMODE : 'ai_suite_safe_mode', 0, false );
                update_option( defined('AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL') ? AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL : 'ai_suite_safe_mode_until', 0, false );
                update_option( defined('AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING') ? AI_SUITE_SAFEBOOT_OPT_EMAIL_PENDING : 'ai_suite_safeboot_email_pending', array( 'sent' => 1, 'time' => time() ), false );
                $done = true;
                $note = 'Safe Mode cleared.';
                break;

            case 'reenable_modules':
                update_option( defined('AI_SUITE_SAFEBOOT_OPT_DISABLED') ? AI_SUITE_SAFEBOOT_OPT_DISABLED : 'ai_suite_disabled_modules', array(), false );
                $done = true;
                $note = 'Disabled modules cleared.';
                break;

            case 'schedule_cron':
                if ( function_exists( 'aisuite_cron_schedule' ) ) {
                    aisuite_cron_schedule();
                } else {
                    // best-effort.
                    if ( ! wp_next_scheduled( 'ai_suite_cron_48h' ) ) {
                        wp_schedule_event( time() + 300, 'ai_suite_48h', 'ai_suite_cron_48h' );
                    }
                    if ( ! wp_next_scheduled( 'ai_suite_ai_queue_tick' ) ) {
                        wp_schedule_event( time() + 180, 'ai_suite_2min', 'ai_suite_ai_queue_tick' );
                    }
                }
                $done = true;
                $note = 'Cron scheduled.';
                break;

            case 'repair_portal_pages':
                if ( function_exists( 'aisuite_create_portal_pages' ) ) {
                    aisuite_create_portal_pages();
                }
                if ( function_exists( 'aisuite_create_portal_hub_page' ) ) {
                    aisuite_create_portal_hub_page();
                }
                $done = true;
                $note = 'Portal pages ensured.';
                break;

            case 'rebuild_registry':
                if ( class_exists( 'AI_Suite_Registry' ) ) {
                    AI_Suite_Registry::register_defaults();
                    $done = true;
                    $note = 'Registry defaults registered.';
                }
                break;
        }

        if ( $done && function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', 'Auto-Repair fix applied', array( 'fix' => $fix_id ) );
        }

        return array( 'ok' => $done, 'note' => $note );
    }
}

if ( ! function_exists( 'aisuite_autorepair_ai_summary' ) ) {
    function aisuite_autorepair_ai_summary( $issues, $recent_logs = array() ) {
        $st = aisuite_autorepair_get_status();
        $last_ai = (int) ( $st['last_ai_time'] ?? 0 );
        if ( time() - $last_ai < 6 * HOUR_IN_SECONDS ) {
            return array( 'ok' => true, 'text' => (string) ( $st['last_ai_summary'] ?? '' ), 'skipped' => true );
        }

        $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
        if ( empty( $settings['openai_api_key'] ) || ! function_exists( 'ai_suite_ai_call' ) ) {
            return array( 'ok' => false, 'text' => '', 'error' => 'OpenAI not configured.' );
        }

        $prompt = "Ai rol de DevOps/WordPress engineer pentru pluginul AI Suite.\n";
        $prompt .= "Generează un plan scurt de remediere (max 10 bullet points), prioritizat, pe baza problemelor de mai jos.\n";
        $prompt .= "NU propune schimbări de cod; doar pași operaționali și setări.\n\n";
        $prompt .= "PROBLEME DETECTATE:\n" . wp_json_encode( $issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n\n";
        $prompt .= "LOGURI RECENTE (rezumat):\n" . wp_json_encode( array_slice( $recent_logs, -40 ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";

        $call = ai_suite_ai_call( $prompt, 450, array(
            'temperature' => 0.2,
            'system'      => 'You are a senior WordPress engineer. Reply in Romanian. Be concise and actionable.',
        ) );

        if ( empty( $call['ok'] ) ) {
            return array( 'ok' => false, 'text' => '', 'error' => (string) ( $call['error'] ?? 'AI error' ) );
        }

        $st['last_ai_summary'] = (string) $call['text'];
        $st['last_ai_time']    = time();
        aisuite_autorepair_set_status( $st );

        return array( 'ok' => true, 'text' => (string) $call['text'] );
    }
}

if ( ! function_exists( 'aisuite_autorepair_maybe_email' ) ) {
    function aisuite_autorepair_maybe_email( $issues, $applied_fixes = array(), $ai_text = '' ) {
        $issues = is_array( $issues ) ? $issues : array();
        if ( empty( $issues ) ) {
            return false;
        }

        $st = aisuite_autorepair_get_status();
        $hash = md5( wp_json_encode( $issues ) );

        // throttle email: 6h or same issues hash.
        $last_email = (int) ( $st['last_email'] ?? 0 );
        $last_hash  = (string) ( $st['last_issue_hash'] ?? '' );

        if ( $hash === $last_hash && time() - $last_email < 12 * HOUR_IN_SECONDS ) {
            return false;
        }
        if ( time() - $last_email < 6 * HOUR_IN_SECONDS ) {
            return false;
        }

        $to = function_exists( 'aisuite_get_notification_email' ) ? aisuite_get_notification_email() : (string) get_option( 'admin_email' );
        $subject = '[AI Suite] Auto-Repair: Probleme detectate';

        $lines = array();
        $lines[] = 'S-au detectat probleme în AI Suite.';
        $lines[] = '';
        foreach ( $issues as $it ) {
            $lines[] = strtoupper( (string) ( $it['severity'] ?? 'info' ) ) . ' - ' . (string) ( $it['title'] ?? '' );
            $lines[] = '  - ' . (string) ( $it['details'] ?? '' );
        }

        if ( ! empty( $applied_fixes ) ) {
            $lines[] = '';
            $lines[] = 'Remedieri aplicate automat:';
            foreach ( $applied_fixes as $fx ) {
                $lines[] = '  - ' . (string) $fx;
            }
        }

        if ( $ai_text ) {
            $lines[] = '';
            $lines[] = 'Recomandări AI (rezumat):';
            $lines[] = $ai_text;
        }

        $sent = wp_mail( $to, $subject, implode( "\n", $lines ) );

        $st['last_email']      = time();
        $st['last_issue_hash'] = $hash;
        aisuite_autorepair_set_status( $st );

        if ( $sent && function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', 'Auto-Repair email sent', array( 'to' => $to, 'issue_count' => count( $issues ) ) );
        }

        return (bool) $sent;
    }
}

if ( ! function_exists( 'aisuite_autorepair_run' ) ) {
    function aisuite_autorepair_run( $apply_safe_fixes = false, $with_ai = true ) {
        $issues = aisuite_autorepair_detect_issues();
        $recent = aisuite_autorepair_collect_recent_logs( 120 );

        $applied = array();
        if ( $apply_safe_fixes ) {
            foreach ( $issues as $it ) {
                if ( ! empty( $it['fixable'] ) && ! empty( $it['fix_id'] ) ) {
                    $r = aisuite_autorepair_apply_fix( $it['fix_id'] );
                    if ( ! empty( $r['ok'] ) ) {
                        $applied[] = $it['fix_id'];
                    }
                }
            }
            // Re-check after fixes.
            $issues = aisuite_autorepair_detect_issues();
        }

        $ai_text = '';
        $ai = array();
        if ( $with_ai ) {
            $ai = aisuite_autorepair_ai_summary( $issues, $recent );
            if ( ! empty( $ai['ok'] ) && ! empty( $ai['text'] ) ) {
                $ai_text = (string) $ai['text'];
            }
        }

        $st = aisuite_autorepair_get_status();
        $st['last_run'] = time();
        aisuite_autorepair_set_status( $st );

        aisuite_autorepair_add_history( array(
            'time'   => current_time( 'mysql' ),
            'issues' => $issues,
            'fixes'  => $applied,
            'ai'     => $ai_text,
        ) );

        // Email only when issues exist.
        aisuite_autorepair_maybe_email( $issues, $applied, $ai_text );

        return array(
            'issues' => $issues,
            'fixes'  => $applied,
            'ai'     => $ai_text,
            'status' => aisuite_autorepair_get_status(),
        );
    }
}

if ( ! function_exists( 'aisuite_autorepair_cron_tick' ) ) {
    function aisuite_autorepair_cron_tick() {
        $st = aisuite_autorepair_get_status();
        if ( empty( $st['enabled'] ) ) {
            return;
        }

        // throttle: max once / 3 hours.
        $last = (int) ( $st['last_run'] ?? 0 );
        if ( $last && ( time() - $last < 3 * HOUR_IN_SECONDS ) ) {
            return;
        }

        // Apply only safe fixes automatically.
        aisuite_autorepair_run( true, true );
    }
}

if ( ! function_exists( 'aisuite_autorepair_ensure_schedule' ) ) {
    function aisuite_autorepair_ensure_schedule() {
        if ( wp_next_scheduled( 'ai_suite_autorepair_cron' ) ) {
            return;
        }
        wp_schedule_event( time() + 600, 'hourly', 'ai_suite_autorepair_cron' );
    }
}

if ( ! function_exists( 'aisuite_autorepair_ajax_guard' ) ) {
    function aisuite_autorepair_ajax_guard() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai_suite_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
        }
        if ( ! current_user_can( 'manage_ai_suite' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
        }
    }
}

if ( ! function_exists( 'aisuite_autorepair_ajax_run' ) ) {
    function aisuite_autorepair_ajax_run() {
        aisuite_autorepair_ajax_guard();

        $apply = ! empty( $_POST['apply'] ) ? (bool) absint( wp_unslash( $_POST['apply'] ) ) : false;
        $with_ai = ! isset( $_POST['with_ai'] ) ? true : (bool) absint( wp_unslash( $_POST['with_ai'] ) );

        $res = aisuite_autorepair_run( $apply, $with_ai );
        wp_send_json_success( $res );
    }
}

if ( ! function_exists( 'aisuite_autorepair_ajax_apply' ) ) {
    function aisuite_autorepair_ajax_apply() {
        aisuite_autorepair_ajax_guard();

        $fix = isset( $_POST['fix_id'] ) ? sanitize_key( wp_unslash( $_POST['fix_id'] ) ) : '';
        if ( ! $fix ) {
            wp_send_json_error( array( 'message' => 'missing_fix' ), 400 );
        }

        $r = aisuite_autorepair_apply_fix( $fix );
        if ( empty( $r['ok'] ) ) {
            wp_send_json_error( array( 'message' => 'apply_failed', 'note' => $r['note'] ?? '' ) );
        }

        // Return fresh diagnostics after apply.
        $res = aisuite_autorepair_run( false, false );
        $res['applied'] = $fix;

        wp_send_json_success( $res );
    }
}

if ( ! function_exists( 'aisuite_autorepair_ajax_toggle' ) ) {
    function aisuite_autorepair_ajax_toggle() {
        aisuite_autorepair_ajax_guard();

        $enabled = isset( $_POST['enabled'] ) ? (int) absint( wp_unslash( $_POST['enabled'] ) ) : 0;
        $st = aisuite_autorepair_get_status();
        $st['enabled'] = $enabled ? 1 : 0;
        aisuite_autorepair_set_status( $st );

        if ( $enabled ) {
            aisuite_autorepair_ensure_schedule();
        }

        wp_send_json_success( array( 'status' => $st ) );
    }
}

// Hooks.
add_action( 'init', 'aisuite_autorepair_ensure_schedule' );
add_action( 'ai_suite_autorepair_cron', 'aisuite_autorepair_cron_tick' );

add_action( 'wp_ajax_ai_suite_autorepair_run', 'aisuite_autorepair_ajax_run' );
add_action( 'wp_ajax_ai_suite_autorepair_apply', 'aisuite_autorepair_ajax_apply' );
add_action( 'wp_ajax_ai_suite_autorepair_toggle', 'aisuite_autorepair_ajax_toggle' );
