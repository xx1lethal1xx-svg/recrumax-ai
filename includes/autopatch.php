<?php
/**
 * AI Auto-Patch Engine (v3.8.0)
 *
 * Scope:
 *  - Generează patch-uri mici cu AI pentru probleme recurente (JS/JIT i18n/AJAX guard).
 *  - Nu aplică automat cod prin default (safe-by-default). Necesită acțiune explicită în Admin.
 *  - Include backup + rollback pentru fiecare fișier modificat.
 *
 * Security:
 *  - Allowlist pe directoarele din plugin
 *  - Blochează funcții periculoase în patch (eval/exec/shell_exec/system/proc_open/popen/passthru/assert/create_function)
 *  - Limitează numărul de operații și dimensiunea patch-ului
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'AI_SUITE_AUTOPATCH_OPT_LAST' ) ) {
    define( 'AI_SUITE_AUTOPATCH_OPT_LAST', 'ai_suite_autopatch_last' );
}
if ( ! defined( 'AI_SUITE_AUTOPATCH_OPT_SETTINGS' ) ) {
    define( 'AI_SUITE_AUTOPATCH_OPT_SETTINGS', 'ai_suite_autopatch_settings' );
}

if ( ! function_exists( 'aisuite_autopatch_get_settings' ) ) {
    function aisuite_autopatch_get_settings() {
        $s = get_option( AI_SUITE_AUTOPATCH_OPT_SETTINGS, array() );
        if ( ! is_array( $s ) ) { $s = array(); }
        $s = wp_parse_args( $s, array(
            'enabled'        => 1,     // engine disponibil
            'auto_apply'     => 0,     // SAFE default: OFF
            'max_ops'        => 2,
            'max_chars'      => 8000,
            'allow_modules'  => array( 'core', 'recruitment', 'portal', 'admin', 'assets' ),
        ) );
        return $s;
    }
}

if ( ! function_exists( 'aisuite_autopatch_ajax_guard' ) ) {
    function aisuite_autopatch_ajax_guard() {
        if ( ! function_exists( 'aisuite_current_user_can_manage_team' ) || ! aisuite_current_user_can_manage_team() ) {
            wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
        }
        check_ajax_referer( 'ai_suite_nonce', 'nonce' );
    }
}

if ( ! function_exists( 'aisuite_autopatch_backups_dir' ) ) {
    function aisuite_autopatch_backups_dir() {
        $u = wp_upload_dir();
        $base = trailingslashit( $u['basedir'] ) . 'ai-suite-backups';
        if ( ! file_exists( $base ) ) {
            wp_mkdir_p( $base );
        }
        return $base;
    }
}

if ( ! function_exists( 'aisuite_autopatch_is_path_allowed' ) ) {
    function aisuite_autopatch_is_path_allowed( $rel_path ) {
        $rel_path = ltrim( str_replace( array('..\\','../','\\'), array('','','/'), (string) $rel_path ), '/' );
        if ( ! $rel_path ) return false;

        // allow only inside plugin
        $plugin_root = realpath( dirname( __DIR__ ) ); // ai-suite/
        $target = realpath( $plugin_root . '/' . $rel_path );
        if ( ! $target ) return false;
        if ( strpos( $target, $plugin_root ) !== 0 ) return false;

        // allowlist folders
        $allow_prefixes = array(
            'includes/',
            'assets/',
            'templates/',
        );
        foreach ( $allow_prefixes as $p ) {
            if ( strpos( $rel_path, $p ) === 0 ) return true;
        }
        return false;
    }
}

if ( ! function_exists( 'aisuite_autopatch_contains_dangerous_code' ) ) {
    function aisuite_autopatch_contains_dangerous_code( $code ) {
        $code = (string) $code;
        $bad = array(
            'eval\s*\(',
            'assert\s*\(',
            'shell_exec\s*\(',
            'exec\s*\(',
            'system\s*\(',
            'passthru\s*\(',
            'popen\s*\(',
            'proc_open\s*\(',
            'create_function\s*\(',
            'base64_decode\s*\(',
            'gzinflate\s*\(',
        );
        foreach ( $bad as $rx ) {
            if ( preg_match( '/'.$rx.'/i', $code ) ) {
                return true;
            }
        }
        return false;
    }
}

if ( ! function_exists( 'aisuite_autopatch_collect_context' ) ) {
    function aisuite_autopatch_collect_context() {
        $ctx = array();

        // SafeBoot fatal
        $fatal = get_option( defined('AI_SUITE_SAFEBOOT_OPT_FATAL') ? AI_SUITE_SAFEBOOT_OPT_FATAL : 'ai_suite_last_fatal', array() );
        if ( is_array( $fatal ) && ! empty( $fatal ) ) {
            $ctx['last_fatal'] = $fatal;
        }

        // Recent portal JS issues (logged by portal diagnostics)
        $logs = get_option( defined('AI_SUITE_OPTION_LOGS') ? AI_SUITE_OPTION_LOGS : 'ai_suite_logs', array() );
        $js = array();
        if ( is_array( $logs ) ) {
            $slice = array_slice( $logs, -80 );
            foreach ( $slice as $it ) {
                if ( ! is_array( $it ) ) continue;
                $msg = isset( $it['message'] ) ? (string) $it['message'] : '';
                if ( stripos( $msg, 'Portal JS issue' ) !== false ) {
                    $js[] = $it;
                }
            }
        }
        if ( $js ) {
            $ctx['portal_js'] = array_slice( $js, -10 );
        }

        // Last crash file from SafeBoot
        $last_crash = get_option( defined('AI_SUITE_SAFEBOOT_OPT_LAST_CRASH') ? AI_SUITE_SAFEBOOT_OPT_LAST_CRASH : 'ai_suite_last_crash', '' );
        if ( $last_crash ) $ctx['last_crash'] = (string) $last_crash;

        // Versions
        $ctx['wp'] = get_bloginfo( 'version' );
        $ctx['php'] = PHP_VERSION;
        $ctx['plugin_version'] = defined('AI_SUITE_VER') ? AI_SUITE_VER : '';
        return $ctx;
    }
}

if ( ! function_exists( 'aisuite_autopatch_build_prompt' ) ) {
    function aisuite_autopatch_build_prompt( array $ctx ) {
        $rules = array(
            "Return STRICT JSON only. No markdown.",
            "Patch ops limit: 1-2 ops.",
            "Allowed ops: replace_once, insert_after, insert_before.",
            "Each op must include: file, op, find, code, note.",
            "file must be relative to plugin root (e.g., includes/ajax.php).",
            "find must be an exact substring present in file.",
            "code must be the full replacement or inserted code snippet.",
            "Do NOT propose changes that add dangerous functions (eval/exec/shell_exec/system/proc_open/popen).",
            "Keep patches minimal (<= 120 lines per op).",
        );

        $payload = array(
            'rules' => $rules,
            'context' => $ctx,
        );

        return "You are a senior WordPress plugin maintainer.\n"
            . "Goal: generate a minimal safe patch for AI Suite plugin to fix the observed issues.\n"
            . "Follow the rules strictly.\n"
            . "Input JSON:\n" . wp_json_encode( $payload );
    }
}

if ( ! function_exists( 'aisuite_autopatch_generate' ) ) {
    function aisuite_autopatch_generate() {
        $settings = aisuite_autopatch_get_settings();
        if ( empty( $settings['enabled'] ) ) {
            return array( 'ok' => false, 'error' => 'AutoPatch disabled.' );
        }

        $ctx = aisuite_autopatch_collect_context();
        $prompt = aisuite_autopatch_build_prompt( $ctx );

        $payload = array(
            'model' => apply_filters( 'ai_suite_openai_model', 'gpt-4o-mini' ),
            'messages' => array(
                array( 'role' => 'system', 'content' => 'You output strict JSON only.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'temperature' => 0.2,
        );

        $resp = function_exists( 'ai_suite_openai_request' ) ? ai_suite_openai_request( $payload, 35 ) : array( 'ok' => false, 'error' => 'OpenAI helper missing.' );
        if ( empty( $resp['ok'] ) ) {
            $err = isset( $resp['error'] ) ? (string) $resp['error'] : 'OpenAI error';
            return array( 'ok' => false, 'error' => $err );
        }

        $body = isset( $resp['body'] ) ? $resp['body'] : array();
        $content = '';
        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            $content = (string) $body['choices'][0]['message']['content'];
        }

        $json = json_decode( $content, true );
        if ( ! is_array( $json ) ) {
            return array( 'ok' => false, 'error' => 'Invalid JSON from AI.' );
        }

        $record = array(
            'created_at' => time(),
            'ctx' => $ctx,
            'patch' => $json,
            'status' => 'proposed',
        );
        update_option( AI_SUITE_AUTOPATCH_OPT_LAST, $record, false );
        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', 'AutoPatch generated proposal', array( 'ops' => isset($json['ops']) ? count((array)$json['ops']) : null ) );
        }
        return array( 'ok' => true, 'data' => $record );
    }
}

if ( ! function_exists( 'aisuite_autopatch_apply' ) ) {
    function aisuite_autopatch_apply() {
        $record = get_option( AI_SUITE_AUTOPATCH_OPT_LAST, array() );
        if ( ! is_array( $record ) || empty( $record['patch'] ) ) {
            return array( 'ok' => false, 'error' => 'No patch proposal found.' );
        }
        if ( isset( $record['status'] ) && $record['status'] === 'applied' ) {
            return array( 'ok' => false, 'error' => 'Patch already applied.' );
        }

        $patch = $record['patch'];
        $ops = array();

        // Accept either {ops:[...]} or a single op object
        if ( isset( $patch['ops'] ) && is_array( $patch['ops'] ) ) {
            $ops = $patch['ops'];
        } elseif ( isset( $patch['file'] ) ) {
            $ops = array( $patch );
        }

        $settings = aisuite_autopatch_get_settings();
        $max_ops = max( 1, (int) $settings['max_ops'] );

        if ( ! $ops ) {
            return array( 'ok' => false, 'error' => 'Patch ops missing.' );
        }
        if ( count( $ops ) > $max_ops ) {
            return array( 'ok' => false, 'error' => 'Too many ops.' );
        }

        $results = array();
        $plugin_root = realpath( dirname( __DIR__ ) );

        foreach ( $ops as $op ) {
            if ( ! is_array( $op ) ) continue;
            $file = isset( $op['file'] ) ? (string) $op['file'] : '';
            $mode = isset( $op['op'] ) ? (string) $op['op'] : '';
            $find = isset( $op['find'] ) ? (string) $op['find'] : '';
            $code = isset( $op['code'] ) ? (string) $op['code'] : '';

            if ( ! $file || ! $mode || ! $find ) {
                return array( 'ok' => false, 'error' => 'Op missing required fields.' );
            }
            if ( strlen( $code ) > (int) $settings['max_chars'] ) {
                return array( 'ok' => false, 'error' => 'Patch too large.' );
            }
            if ( ! aisuite_autopatch_is_path_allowed( $file ) ) {
                return array( 'ok' => false, 'error' => 'File path not allowed: '.$file );
            }
            if ( aisuite_autopatch_contains_dangerous_code( $code ) ) {
                return array( 'ok' => false, 'error' => 'Patch contains dangerous code.' );
            }

            $abs = realpath( $plugin_root . '/' . ltrim( $file, '/' ) );
            if ( ! $abs || ! file_exists( $abs ) ) {
                return array( 'ok' => false, 'error' => 'File not found: '.$file );
            }

            $orig = file_get_contents( $abs );
            if ( $orig === false ) {
                return array( 'ok' => false, 'error' => 'Cannot read: '.$file );
            }
            if ( strpos( $orig, $find ) === false ) {
                return array( 'ok' => false, 'error' => 'Anchor not found in '.$file );
            }

            $new = $orig;
            if ( $mode === 'replace_once' ) {
                $pos = strpos( $orig, $find );
                $new = substr( $orig, 0, $pos ) . $code . substr( $orig, $pos + strlen( $find ) );
            } elseif ( $mode === 'insert_after' ) {
                $pos = strpos( $orig, $find );
                $new = substr( $orig, 0, $pos + strlen( $find ) ) . $code . substr( $orig, $pos + strlen( $find ) );
            } elseif ( $mode === 'insert_before' ) {
                $pos = strpos( $orig, $find );
                $new = substr( $orig, 0, $pos ) . $code . substr( $orig, $pos );
            } else {
                return array( 'ok' => false, 'error' => 'Unsupported op: '.$mode );
            }

            // Backup
            $backup_dir = aisuite_autopatch_backups_dir();
            $stamp = gmdate( 'Ymd_His' );
            $hash  = substr( sha1( $orig ), 0, 10 );
            $backup_file = $backup_dir . '/' . sanitize_file_name( str_replace( '/', '_', $file ) ) . '__' . $stamp . '__' . $hash . '.bak';
            file_put_contents( $backup_file, $orig );

            // Write
            $ok = file_put_contents( $abs, $new );
            if ( $ok === false ) {
                return array( 'ok' => false, 'error' => 'Cannot write: '.$file );
            }

            // Lightweight syntax sanity for PHP files
            if ( preg_match( '/\.php$/i', $abs ) ) {
                // Basic guard: file must start with <?php
                $trim = ltrim( $new );
                if ( strpos( $trim, '<?php' ) !== 0 ) {
                    // rollback
                    file_put_contents( $abs, $orig );
                    return array( 'ok' => false, 'error' => 'PHP file corrupted (missing opening tag). Rolled back.' );
                }
            }

            $results[] = array(
                'file' => $file,
                'backup' => $backup_file,
                'bytes' => strlen( $new ),
            );
        }

        $record['status'] = 'applied';
        $record['applied_at'] = time();
        $record['applied_results'] = $results;
        update_option( AI_SUITE_AUTOPATCH_OPT_LAST, $record, false );

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'warning', 'AutoPatch applied', array( 'files' => wp_list_pluck( $results, 'file' ) ) );
        }

        return array( 'ok' => true, 'data' => $record );
    }
}

if ( ! function_exists( 'aisuite_autopatch_rollback' ) ) {
    function aisuite_autopatch_rollback() {
        $record = get_option( AI_SUITE_AUTOPATCH_OPT_LAST, array() );
        if ( ! is_array( $record ) || empty( $record['applied_results'] ) ) {
            return array( 'ok' => false, 'error' => 'No applied patch to rollback.' );
        }
        $plugin_root = realpath( dirname( __DIR__ ) );
        $rolled = array();

        foreach ( (array) $record['applied_results'] as $r ) {
            $file = isset( $r['file'] ) ? (string) $r['file'] : '';
            $bak  = isset( $r['backup'] ) ? (string) $r['backup'] : '';
            if ( ! $file || ! $bak || ! file_exists( $bak ) ) continue;
            if ( ! aisuite_autopatch_is_path_allowed( $file ) ) continue;

            $abs = realpath( $plugin_root . '/' . ltrim( $file, '/' ) );
            if ( ! $abs ) continue;

            $orig = file_get_contents( $bak );
            if ( $orig === false ) continue;

            file_put_contents( $abs, $orig );
            $rolled[] = $file;
        }

        $record['status'] = 'rolled_back';
        $record['rolled_back_at'] = time();
        $record['rolled_back_files'] = $rolled;
        update_option( AI_SUITE_AUTOPATCH_OPT_LAST, $record, false );

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'warning', 'AutoPatch rollback executed', array( 'files' => $rolled ) );
        }
        return array( 'ok' => true, 'data' => $record );
    }
}

// AJAX endpoints
add_action( 'wp_ajax_ai_suite_autopatch_generate', function() {
    aisuite_autopatch_ajax_guard();
    $out = aisuite_autopatch_generate();
    if ( ! empty( $out['ok'] ) ) {
        wp_send_json_success( $out['data'] );
    }
    wp_send_json_error( array( 'message' => isset($out['error']) ? $out['error'] : 'Eroare' ) );
} );

add_action( 'wp_ajax_ai_suite_autopatch_apply', function() {
    aisuite_autopatch_ajax_guard();
    $out = aisuite_autopatch_apply();
    if ( ! empty( $out['ok'] ) ) {
        wp_send_json_success( $out['data'] );
    }
    wp_send_json_error( array( 'message' => isset($out['error']) ? $out['error'] : 'Eroare' ) );
} );

add_action( 'wp_ajax_ai_suite_autopatch_rollback', function() {
    aisuite_autopatch_ajax_guard();
    $out = aisuite_autopatch_rollback();
    if ( ! empty( $out['ok'] ) ) {
        wp_send_json_success( $out['data'] );
    }
    wp_send_json_error( array( 'message' => isset($out['error']) ? $out['error'] : 'Eroare' ) );
} );

add_action( 'wp_ajax_ai_suite_autopatch_status', function() {
    aisuite_autopatch_ajax_guard();
    $record = get_option( AI_SUITE_AUTOPATCH_OPT_LAST, array() );
    if ( ! is_array( $record ) ) $record = array();
    wp_send_json_success( $record );
} );
