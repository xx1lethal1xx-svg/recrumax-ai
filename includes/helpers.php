<?php
/**
 * Helper functions for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieve plugin settings.
 *
 * @return array
 */
if ( ! function_exists( 'aisuite_get_settings' ) ) {
function aisuite_get_settings() {
    $settings = get_option( AI_SUITE_OPTION_SETTINGS, array() );
    $defaults = array(
        'openai_api_key' => '',
        'openai_model' => 'gpt-4.1-mini',
        'ai_queue_enabled' => 0,
        'ui_language' => 'ro',
        'enable_en_pages' => 0,
    );
    $settings = wp_parse_args( is_array($settings) ? $settings : array(), $defaults );
    return is_array( $settings ) ? $settings : array();
}
}

/**
 * Update plugin settings.
 *
 * @param array $data Settings data.
 */
if ( ! function_exists( 'aisuite_update_settings' ) ) {
function aisuite_update_settings( array $data ) {
    update_option( AI_SUITE_OPTION_SETTINGS, $data, false );
}
}

/**
 * Log a message.
 *
 * @param string $level Log level (info, warning, error).
 * @param string $message Message to log.
 * @param array  $context Additional context.
 */
if ( ! function_exists( 'aisuite_log' ) ) {
function aisuite_log( $level, $message, $context = array() ) {
    $logs = get_option( AI_SUITE_OPTION_LOGS, array() );
    if ( ! is_array( $logs ) ) {
        $logs = array();
    }
    // Limit logs to 500 entries.
    if ( count( $logs ) > 500 ) {
        $logs = array_slice( $logs, -500, 500, true );
    }
    $logs[] = array(
        'time'    => current_time( 'mysql' ),
        'level'   => $level,
        'message' => $message,
        'context' => $context,
    );
    update_option( AI_SUITE_OPTION_LOGS, $logs, false );
}
}

/**
 * Portal: unified nonce verification for AJAX requests.
 *
 * @param string $action
 */
if ( ! function_exists( 'ai_suite_portal_require_nonce' ) ) {
function ai_suite_portal_require_nonce( $action = 'ai_suite_portal_nonce' ) {
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'warning', 'portal_ajax_403', array(
                'action'  => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
                'user_id' => get_current_user_id(),
                'reason'  => 'nonce_invalid',
            ) );
        }
        wp_send_json_error( array( 'message' => __( 'Sesiune expirată (nonce invalid).', 'ai-suite' ) ), 403 );
    }
}
}

/**
 * Portal: role-based access helper with admin override.
 *
 * @param string $role company|candidate|recruiter|portal
 * @param int    $user_id
 * @return bool
 */
if ( ! function_exists( 'ai_suite_portal_user_can' ) ) {
function ai_suite_portal_user_can( $role = 'portal', $user_id = 0 ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id || ! is_user_logged_in() ) {
        return false;
    }
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    $is_company   = function_exists( 'aisuite_current_user_is_company' ) ? aisuite_current_user_is_company() : false;
    $is_candidate = function_exists( 'aisuite_current_user_is_candidate' ) ? aisuite_current_user_is_candidate() : false;
    $is_recruiter = function_exists( 'aisuite_current_user_is_recruiter' ) ? aisuite_current_user_is_recruiter() : false;

    if ( $role === 'company' ) {
        return $is_company;
    }
    if ( $role === 'candidate' ) {
        return $is_candidate;
    }
    if ( $role === 'recruiter' ) {
        return $is_recruiter;
    }
    // portal = any portal role
    return $is_company || $is_candidate || $is_recruiter;
}
}

/**
 * Portal: log authorization failures (best-effort).
 *
 * @param string $reason
 * @param array  $context
 */
if ( ! function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
function ai_suite_portal_log_auth_failure( $reason, $context = array() ) {
    if ( function_exists( 'aisuite_log' ) ) {
        $ctx = is_array( $context ) ? $context : array();
        $ctx['reason'] = (string) $reason;
        $ctx['user_id'] = get_current_user_id();
        aisuite_log( 'warning', 'portal_ajax_403', $ctx );
    }
}
}

/**
 * Record a bot run.
 *
 * @param string $bot_key Bot key.
 * @param array  $result Result data.
 */
if ( ! function_exists( 'aisuite_record_run' ) ) {
function aisuite_record_run( $bot_key, $result ) {
    $runs = get_option( AI_SUITE_OPTION_RUNS, array() );
    if ( ! is_array( $runs ) ) {
        $runs = array();
    }
    // Limit runs to 200 entries.
    if ( count( $runs ) > 200 ) {
        $runs = array_slice( $runs, -200, 200, true );
    }
    $runs[] = array(
        'time'     => current_time( 'mysql' ),
        'bot_key'  => $bot_key,
        'result'   => $result,
    );
    update_option( AI_SUITE_OPTION_RUNS, $runs, false );
}
}
/**
 * Get admin notification email (fallback to WP admin_email).
 *
 * @return string
 */
if ( ! function_exists( 'aisuite_get_notification_email' ) ) {
function aisuite_get_notification_email() {
    $email = '';
    if ( function_exists( 'aisuite_get_settings' ) ) {
        $settings = (array) aisuite_get_settings();
        if ( ! empty( $settings['notificari_admin_email'] ) ) {
            $email = sanitize_email( (string) $settings['notificari_admin_email'] );
        }
    }
    if ( ! $email ) {
        $email = (string) get_option( 'admin_email', '' );
    }
    return sanitize_email( $email );
}
}

/**
 * Send an admin notification email (best-effort).
 *
 * @param string $subject
 * @param string $message
 * @return bool
 */
if ( ! function_exists( 'aisuite_notify_admin' ) ) {
function aisuite_notify_admin( $subject, $message ) {
    if ( ! function_exists( 'wp_mail' ) ) {
        return false;
    }
    $to = aisuite_get_notification_email();
    if ( ! $to ) {
        return false;
    }
    $subject = wp_strip_all_tags( (string) $subject );
    $message = (string) $message;

    $headers = array();
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    return (bool) wp_mail( $to, $subject, $message, $headers );
}
}

/**
 * Make a raw OpenAI API request using WordPress HTTP API.
 *
 * @param array $payload
 * @param int   $timeout
 * @return array{ok:bool,status:int,body:array|string,error:string}
 */
if ( ! function_exists( 'ai_suite_openai_request' ) ) {
function ai_suite_openai_request( array $payload, $timeout = 25 ) {
    $settings = function_exists( 'aisuite_get_settings' ) ? aisuite_get_settings() : array();
    $key = isset( $settings['openai_api_key'] ) ? trim( (string) $settings['openai_api_key'] ) : '';
    if ( ! $key ) {
        return array( 'ok' => false, 'status' => 0, 'body' => array(), 'error' => 'Missing OpenAI API key.' );
    }

    if ( ! function_exists( 'wp_remote_post' ) ) {
        return array( 'ok' => false, 'status' => 0, 'body' => array(), 'error' => 'HTTP API unavailable.' );
    }

    $endpoint = apply_filters( 'ai_suite_openai_endpoint', 'https://api.openai.com/v1/chat/completions', $payload );

    $args = array(
        'timeout' => (int) $timeout,
        'headers' => array(
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ),
        'body' => wp_json_encode( $payload ),
    );

    $res = wp_remote_post( $endpoint, $args );
    if ( is_wp_error( $res ) ) {
        return array( 'ok' => false, 'status' => 0, 'body' => array(), 'error' => $res->get_error_message() );
    }

    $status = (int) wp_remote_retrieve_response_code( $res );
    $raw    = (string) wp_remote_retrieve_body( $res );
    $json   = json_decode( $raw, true );
    $body   = is_array( $json ) ? $json : $raw;

    if ( $status >= 200 && $status < 300 && is_array( $json ) ) {
        return array( 'ok' => true, 'status' => $status, 'body' => $json, 'error' => '' );
    }

    $err = '';
    if ( is_array( $json ) && ! empty( $json['error']['message'] ) ) {
        $err = (string) $json['error']['message'];
    }
    if ( ! $err ) {
        $err = 'OpenAI request failed (HTTP ' . $status . ').';
    }

    return array( 'ok' => false, 'status' => $status, 'body' => $body, 'error' => $err );
}
}

/**
 * Simple AI call helper (chat completions). Used by AI Queue + bots.
 *
 * @param string $prompt
 * @param int    $max_tokens
 * @param array  $opts {model?:string, temperature?:float, system?:string}
 * @return array{ok:bool,text:string,error:string,raw:array,status:int}
 */
if ( ! function_exists( 'ai_suite_ai_call' ) ) {
function ai_suite_ai_call( $prompt, $max_tokens = 500, $opts = array() ) {
    $settings = function_exists( 'aisuite_get_settings' ) ? aisuite_get_settings() : array();
    $model = ! empty( $opts['model'] ) ? (string) $opts['model'] : (string) ( $settings['openai_model'] ?? 'gpt-4.1-mini' );
    $temp  = isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.2;
    $sys   = ! empty( $opts['system'] ) ? (string) $opts['system'] : 'You are a helpful assistant for a recruitment platform. Respond in Romanian, concise.';

    $payload = array(
        'model' => $model,
        'temperature' => $temp,
        'max_tokens' => max( 1, (int) $max_tokens ),
        'messages' => array(
            array( 'role' => 'system', 'content' => $sys ),
            array( 'role' => 'user', 'content' => (string) $prompt ),
        ),
    );

    $res = ai_suite_openai_request( $payload, 30 );
    if ( empty( $res['ok'] ) ) {
        return array(
            'ok' => false,
            'text' => '',
            'error' => (string) ( $res['error'] ?? 'Unknown error' ),
            'raw' => is_array( $res['body'] ) ? $res['body'] : array(),
            'status' => (int) ( $res['status'] ?? 0 ),
        );
    }

    $data = (array) $res['body'];
    $text = '';
    if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
        $text = (string) $data['choices'][0]['message']['content'];
    } elseif ( ! empty( $data['choices'][0]['text'] ) ) {
        $text = (string) $data['choices'][0]['text'];
    }

    return array(
        'ok' => (bool) $text,
        'text' => trim( $text ),
        'error' => $text ? '' : 'Empty response from OpenAI.',
        'raw' => $data,
        'status' => (int) ( $res['status'] ?? 200 ),
    );
}
}

/**
 * Quick OpenAI connection test.
 *
 * @return array{ok:bool,message:string,model:string,status:int,error:string}
 */
if ( ! function_exists( 'ai_suite_openai_test_connection' ) ) {
function ai_suite_openai_test_connection() {
    $settings = function_exists( 'aisuite_get_settings' ) ? aisuite_get_settings() : array();
    $model = (string) ( $settings['openai_model'] ?? 'gpt-4.1-mini' );

    $call = ai_suite_ai_call( 'Răspunde doar cu: OK', 15, array(
        'model' => $model,
        'temperature' => 0.0,
        'system' => 'You are a connectivity test. Reply only with OK.',
    ) );

    if ( empty( $call['ok'] ) ) {
        return array(
            'ok' => false,
            'message' => 'Eroare la conectarea OpenAI.',
            'model' => $model,
            'status' => (int) ( $call['status'] ?? 0 ),
            'error' => (string) ( $call['error'] ?? 'Unknown error' ),
        );
    }

    $txt = trim( (string) $call['text'] );
    $ok  = ( stripos( $txt, 'OK' ) !== false );

    return array(
        'ok' => $ok,
        'message' => $ok ? 'Conexiune OK.' : ( 'Răspuns neașteptat: ' . $txt ),
        'model' => $model,
        'status' => (int) ( $call['status'] ?? 200 ),
        'error' => $ok ? '' : 'Unexpected reply.',
    );
}
}

/**
 * Portal effective user id for AJAX requests.
 *
 * Supports Admin Preview (impersonation) in portals by sending:
 *  - as_user (int)
 *  - as_nonce (string) nonce created with action "ais_as_user_{UID}"
 *
 * Security:
 *  - only users with manage_options can impersonate
 *  - nonce must match the target user id
 */
if ( ! function_exists( 'ai_suite_portal_effective_user_id' ) ) {
    function ai_suite_portal_effective_user_id() {
        $uid = get_current_user_id();

        // Allow Admin Preview to impersonate user for portal AJAX.
        if ( ! current_user_can( 'manage_options' ) ) {
            return $uid;
        }

        $as_user  = isset( $_POST['as_user'] ) ? absint( wp_unslash( $_POST['as_user'] ) ) : 0;
        $as_nonce = isset( $_POST['as_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['as_nonce'] ) ) : '';
        if ( ! $as_user || ! $as_nonce ) {
            return $uid;
        }

        if ( ! wp_verify_nonce( $as_nonce, 'ais_as_user_' . $as_user ) ) {
            return $uid;
        }

        return $as_user;
    }
}
