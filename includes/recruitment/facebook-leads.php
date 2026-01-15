<?php
/**
 * AI Suite – Facebook Leads (Lead Ads) integration
 *
 * Features:
 * - Public Webhook endpoint (REST) for Meta "leadgen" notifications (GET verify + POST events)
 * - Optional payload signature verification (X-Hub-Signature-256) using App Secret
 * - Fetch lead details from Graph API using Page Access Token
 * - Store leads in a dedicated DB table (inbox)
 * - Admin UI: settings + leads list + convert-to-candidate
 *
 * Notes:
 * - You must configure the Webhook in Meta Developers (leadgen) and set:
 *   Callback URL: {site}/wp-json/ai-suite/v1/facebook/webhook
 *   Verify Token: the value you set in plugin settings
 * - You must provide a Page Access Token with lead retrieval permissions.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_fb_leads_table' ) ) {
    function ai_suite_fb_leads_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_suite_fb_leads';
    }
}

if ( ! function_exists( 'ai_suite_fb_settings_key' ) ) {
    function ai_suite_fb_settings_key() {
        return 'ai_suite_fb_settings';
    }
}

if ( ! function_exists( 'ai_suite_fb_get_settings' ) ) {
    function ai_suite_fb_get_settings() {
        $defaults = array(
            'enabled'             => 1,
            'verify_token'        => '',
            'app_secret'          => '',
            'graph_version'       => 'v19.0',
            'default_page_token'  => '',
            'page_tokens'         => array(), // page_id => token
            'form_company_map'    => array(), // form_id => company_id (post ID)
            'auto_convert'        => 0,
        );
        $opt = get_option( ai_suite_fb_settings_key(), array() );
        if ( ! is_array( $opt ) ) { $opt = array(); }
        $s = array_merge( $defaults, $opt );

        if ( empty( $s['verify_token'] ) ) {
            // Generate a stable token once.
            $s['verify_token'] = wp_generate_password( 24, false, false );
            update_option( ai_suite_fb_settings_key(), $s, false );
        }
        if ( empty( $s['page_tokens'] ) || ! is_array( $s['page_tokens'] ) ) {
            $s['page_tokens'] = array();
        }
        if ( empty( $s['form_company_map'] ) || ! is_array( $s['form_company_map'] ) ) {
            $s['form_company_map'] = array();
        }
        return $s;
    }
}

if ( ! function_exists( 'ai_suite_fb_save_settings' ) ) {
    function ai_suite_fb_save_settings( $settings ) {
        if ( ! is_array( $settings ) ) { return false; }
        return update_option( ai_suite_fb_settings_key(), $settings, false );
    }
}

if ( ! function_exists( 'ai_suite_fb_parse_kv_lines' ) ) {
    /**
     * Parse textarea lines in format key=value.
     * Returns associative array.
     */
    function ai_suite_fb_parse_kv_lines( $text ) {
        $out = array();
        $lines = preg_split( '/\r\n|\r|\n/', (string) $text );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            if ( strpos( $line, '#' ) === 0 ) continue;
            if ( strpos( $line, '=' ) === false ) continue;
            list( $k, $v ) = array_map( 'trim', explode( '=', $line, 2 ) );
            if ( $k === '' || $v === '' ) continue;
            $out[ $k ] = $v;
        }
        return $out;
    }
}

if ( ! function_exists( 'ai_suite_fb_ensure_table' ) ) {
    function ai_suite_fb_ensure_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = ai_suite_fb_leads_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            leadgen_id VARCHAR(64) NOT NULL,
            page_id VARCHAR(64) NULL,
            form_id VARCHAR(64) NULL,
            ad_id VARCHAR(64) NULL,
            adgroup_id VARCHAR(64) NULL,
            campaign_id VARCHAR(64) NULL,
            created_time DATETIME NULL,
            company_id BIGINT(20) UNSIGNED NULL,
            candidate_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'new',
            field_data LONGTEXT NULL,
            payload_json LONGTEXT NULL,
            fetched_json LONGTEXT NULL,
            error_text TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY leadgen_id (leadgen_id),
            KEY form_id (form_id),
            KEY company_id (company_id),
            KEY status (status),
            KEY created_time (created_time)
        ) {$charset};";

        dbDelta( $sql );
        return true;
    }
}

if ( ! function_exists( 'ai_suite_fb_get_page_token' ) ) {
    function ai_suite_fb_get_page_token( $page_id ) {
        $s = ai_suite_fb_get_settings();
        $page_id = (string) $page_id;
        if ( $page_id !== '' && ! empty( $s['page_tokens'][ $page_id ] ) ) {
            return (string) $s['page_tokens'][ $page_id ];
        }
        return (string) $s['default_page_token'];
    }
}

if ( ! function_exists( 'ai_suite_fb_verify_signature' ) ) {
    function ai_suite_fb_verify_signature( $raw_body ) {
        $s = ai_suite_fb_get_settings();
        $secret = isset( $s['app_secret'] ) ? (string) $s['app_secret'] : '';
        if ( $secret === '' ) {
            return true; // not configured => skip
        }
        $header = isset( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ? (string) $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';
        if ( $header === '' ) {
            return false;
        }
        if ( stripos( $header, 'sha256=' ) === 0 ) {
            $header = substr( $header, 7 );
        }
        $calc = hash_hmac( 'sha256', (string) $raw_body, $secret );
        return hash_equals( strtolower( $calc ), strtolower( $header ) );
    }
}

if ( ! function_exists( 'ai_suite_fb_fetch_lead' ) ) {
    function ai_suite_fb_fetch_lead( $leadgen_id, $page_id = '' ) {
        $leadgen_id = trim( (string) $leadgen_id );
        if ( $leadgen_id === '' ) {
            return new WP_Error( 'empty_lead', __( 'leadgen_id lipsă', 'ai-suite' ) );
        }
        $s = ai_suite_fb_get_settings();
        $token = ai_suite_fb_get_page_token( $page_id );
        if ( $token === '' ) {
            return new WP_Error( 'no_token', __( 'Lipsește Page Access Token', 'ai-suite' ) );
        }
        $ver = ! empty( $s['graph_version'] ) ? preg_replace( '/[^a-zA-Z0-9\.]/', '', (string) $s['graph_version'] ) : 'v19.0';
        $fields = 'created_time,field_data';
        $url = 'https://graph.facebook.com/' . $ver . '/' . rawurlencode( $leadgen_id ) . '?fields=' . rawurlencode( $fields ) . '&access_token=' . rawurlencode( $token );

        $resp = wp_remote_get( $url, array( 'timeout' => 20 ) );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $json = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $msg = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : __( 'Eroare Graph API', 'ai-suite' );
            return new WP_Error( 'graph_error', $msg, array( 'status' => $code, 'body' => $body ) );
        }
        if ( ! is_array( $json ) ) {
            return new WP_Error( 'bad_json', __( 'Răspuns invalid de la Graph API', 'ai-suite' ) );
        }
        return $json;
    }
}

if ( ! function_exists( 'ai_suite_fb_field_map' ) ) {
    function ai_suite_fb_field_map( $field_data ) {
        // Normalize field_data[] => key => value
        $out = array();
        if ( ! is_array( $field_data ) ) return $out;
        foreach ( $field_data as $row ) {
            if ( ! is_array( $row ) ) continue;
            $name = isset( $row['name'] ) ? (string) $row['name'] : '';
            if ( $name === '' ) continue;
            $vals = isset( $row['values'] ) && is_array( $row['values'] ) ? $row['values'] : array();
            $val = isset( $vals[0] ) ? (string) $vals[0] : '';
            $out[ $name ] = $val;
        }
        return $out;
    }
}

if ( ! function_exists( 'ai_suite_fb_guess_name_email_phone_location' ) ) {
    function ai_suite_fb_guess_name_email_phone_location( $fields ) {
        $fields = is_array( $fields ) ? $fields : array();
        $email = '';
        foreach ( array( 'email', 'email_address' ) as $k ) {
            if ( ! empty( $fields[ $k ] ) ) { $email = (string) $fields[ $k ]; break; }
        }
        $phone = '';
        foreach ( array( 'phone_number', 'phone', 'telefon' ) as $k ) {
            if ( ! empty( $fields[ $k ] ) ) { $phone = (string) $fields[ $k ]; break; }
        }
        $name = '';
        foreach ( array( 'full_name', 'nume', 'name' ) as $k ) {
            if ( ! empty( $fields[ $k ] ) ) { $name = (string) $fields[ $k ]; break; }
        }
        if ( $name === '' ) {
            $fn = ! empty( $fields['first_name'] ) ? (string) $fields['first_name'] : '';
            $ln = ! empty( $fields['last_name'] ) ? (string) $fields['last_name'] : '';
            $name = trim( $fn . ' ' . $ln );
        }
        if ( $name === '' && $email !== '' ) {
            $name = $email;
        }

        $loc = '';
        foreach ( array( 'city', 'location', 'oras', 'localitate' ) as $k ) {
            if ( ! empty( $fields[ $k ] ) ) { $loc = (string) $fields[ $k ]; break; }
        }

        return array( 'name' => $name, 'email' => $email, 'phone' => $phone, 'location' => $loc );
    }
}

if ( ! function_exists( 'ai_suite_fb_store_lead' ) ) {
    function ai_suite_fb_store_lead( $leadgen_id, $value, $payload_raw, $fetched = null ) {
        global $wpdb;

        ai_suite_fb_ensure_table();
        $table = ai_suite_fb_leads_table();

        $page_id = isset( $value['page_id'] ) ? (string) $value['page_id'] : '';
        $form_id = isset( $value['form_id'] ) ? (string) $value['form_id'] : '';
        $ad_id   = isset( $value['ad_id'] ) ? (string) $value['ad_id'] : '';
        $adg_id  = isset( $value['adgroup_id'] ) ? (string) $value['adgroup_id'] : '';
        $camp_id = isset( $value['campaign_id'] ) ? (string) $value['campaign_id'] : '';

        $created_time = null;
        $field_data = null;
        $fetched_json = null;
        $error_text = null;

        if ( is_array( $fetched ) ) {
            $created_time = ! empty( $fetched['created_time'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $fetched['created_time'] ) ) : null;
            $field_data = isset( $fetched['field_data'] ) ? $fetched['field_data'] : null;
            $fetched_json = wp_json_encode( $fetched );
        } elseif ( is_wp_error( $fetched ) ) {
            $error_text = $fetched->get_error_message();
        }

        // Map company by form_id (optional)
        $s = ai_suite_fb_get_settings();
        $company_id = null;
        if ( $form_id !== '' && ! empty( $s['form_company_map'][ $form_id ] ) ) {
            $company_id = absint( $s['form_company_map'][ $form_id ] );
        }

        // Premium gate: facebook leads are available only on plans with feature "facebook_leads".
        $blocked = false;
        $block_reason = '';
        if ( $company_id && function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'facebook_leads' ) ) {
            $blocked = true;
            $block_reason = 'Upgrade required';
        }

        $wpdb->replace(
            $table,
            array(
                'leadgen_id'   => $leadgen_id,
                'page_id'      => $page_id,
                'form_id'      => $form_id,
                'ad_id'        => $ad_id,
                'adgroup_id'   => $adg_id,
                'campaign_id'  => $camp_id,
                'created_time' => $created_time,
                'company_id'   => $company_id ? $company_id : null,
                'status'       => ( $blocked ? 'blocked' : 'new' ),
                'field_data'   => $field_data ? wp_json_encode( $field_data ) : null,
                'payload_json' => $payload_raw ? (string) $payload_raw : null,
                'fetched_json' => $fetched_json,
                'error_text'   => ( $blocked ? ( $block_reason ?: $error_text ) : $error_text ),
                'updated_at'   => current_time( 'mysql' ),
            ),
            array(
                '%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s'
            )
        );

        $lead_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE leadgen_id=%s", $leadgen_id ) );

        // Optional auto-convert
        if ( ! empty( $s['auto_convert'] ) && $lead_id && ! $blocked ) {
            ai_suite_fb_convert_lead_to_candidate( $lead_id );
        }

        return $lead_id;
    }
}

if ( ! function_exists( 'ai_suite_fb_convert_lead_to_candidate' ) ) {
    function ai_suite_fb_convert_lead_to_candidate( $lead_id ) {
        global $wpdb;

        $lead_id = absint( $lead_id );
        if ( ! $lead_id ) return new WP_Error( 'bad_id', __( 'ID lead invalid', 'ai-suite' ) );

        ai_suite_fb_ensure_table();
        $table = ai_suite_fb_leads_table();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $lead_id ), ARRAY_A );
        if ( ! $row ) return new WP_Error( 'not_found', __( 'Lead inexistent', 'ai-suite' ) );


// Premium gate: conversion requires feature facebook_leads
$company_id = ! empty( $row['company_id'] ) ? absint( $row['company_id'] ) : 0;
if ( $company_id && function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'facebook_leads' ) ) {
    $wpdb->update( $table, array( 'status' => 'blocked', 'error_text' => 'Upgrade required', 'updated_at' => current_time('mysql') ), array( 'id' => $lead_id ), array( '%s','%s','%s' ), array( '%d' ) );
    return new WP_Error( 'upgrade_required', __( 'Facebook Leads este disponibil doar pe planurile Pro/Enterprise. Upgrade required.', 'ai-suite' ), array( 'status' => 402 ) );
}

        if ( ! empty( $row['candidate_id'] ) ) {
            return (int) $row['candidate_id'];
        }

        $field_data = array();
        if ( ! empty( $row['field_data'] ) ) {
            $decoded = json_decode( (string) $row['field_data'], true );
            if ( is_array( $decoded ) ) $field_data = $decoded;
        } else if ( ! empty( $row['fetched_json'] ) ) {
            $decoded = json_decode( (string) $row['fetched_json'], true );
            if ( is_array( $decoded ) && isset( $decoded['field_data'] ) ) $field_data = $decoded['field_data'];
        }

        $fields = ai_suite_fb_field_map( $field_data );
        $g = ai_suite_fb_guess_name_email_phone_location( $fields );

        $name  = sanitize_text_field( $g['name'] );
        $email = sanitize_email( $g['email'] );
        $phone = sanitize_text_field( $g['phone'] );
        $loc   = sanitize_text_field( $g['location'] );

        if ( $name === '' ) $name = __( 'Lead Facebook', 'ai-suite' );

        $candidate_id = wp_insert_post( array(
            'post_type'   => 'rmax_candidate',
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => 0,
        ), true );

        if ( is_wp_error( $candidate_id ) ) {
            $wpdb->update( $table, array( 'status' => 'error', 'error_text' => $candidate_id->get_error_message(), 'updated_at' => current_time('mysql') ), array( 'id' => $lead_id ), array( '%s','%s','%s' ), array( '%d' ) );
            return $candidate_id;
        }

        // Save basic fields + raw map for auditing
        if ( $email !== '' ) update_post_meta( $candidate_id, '_candidate_email', $email );
        if ( $phone !== '' ) update_post_meta( $candidate_id, '_candidate_phone', $phone );
        if ( $loc !== '' ) update_post_meta( $candidate_id, '_candidate_location', $loc );
        update_post_meta( $candidate_id, '_candidate_source', 'facebook_lead' );
        update_post_meta( $candidate_id, '_fb_lead_id', $lead_id );
        update_post_meta( $candidate_id, '_fb_form_id', (string) $row['form_id'] );
        update_post_meta( $candidate_id, '_fb_page_id', (string) $row['page_id'] );
        update_post_meta( $candidate_id, '_fb_fields', $fields );

        // Update candidate index if available
        if ( function_exists( 'ai_suite_candidate_index_upsert' ) ) {
            ai_suite_candidate_index_upsert( (int) $candidate_id );
        }

        $wpdb->update(
            $table,
            array(
                'candidate_id' => (int) $candidate_id,
                'status'       => 'processed',
                'updated_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $lead_id ),
            array( '%d','%s','%s' ),
            array( '%d' )
        );

        return (int) $candidate_id;
    }
}

/**
 * REST Webhook endpoint
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'ai-suite/v1', '/facebook/webhook', array(
        array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => function( WP_REST_Request $request ) {
                $s = ai_suite_fb_get_settings();
                $mode = (string) $request->get_param( 'hub_mode' );
                $token = (string) $request->get_param( 'hub_verify_token' );
                $challenge = (string) $request->get_param( 'hub_challenge' );

                // Meta sends hub.mode / hub.verify_token / hub.challenge (also accessible via hub_mode mapping)
                if ( $mode === '' ) $mode = (string) $request->get_param( 'hub.mode' );
                if ( $token === '' ) $token = (string) $request->get_param( 'hub.verify_token' );
                if ( $challenge === '' ) $challenge = (string) $request->get_param( 'hub.challenge' );

                if ( $mode === 'subscribe' && $token !== '' && hash_equals( (string) $s['verify_token'], $token ) ) {
                    return new WP_REST_Response( $challenge, 200 );
                }
                return new WP_REST_Response( 'Forbidden', 403 );
            },
            'permission_callback' => '__return_true',
        ),
        array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => function( WP_REST_Request $request ) {
                $s = ai_suite_fb_get_settings();
                if ( empty( $s['enabled'] ) ) {
                    return new WP_REST_Response( array( 'ok' => true, 'disabled' => true ), 200 );
                }

                $raw = $request->get_body();
                if ( ! ai_suite_fb_verify_signature( $raw ) ) {
                    return new WP_REST_Response( 'Bad signature', 403 );
                }

                $payload = json_decode( $raw, true );
                if ( ! is_array( $payload ) ) {
                    return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_json' ), 400 );
                }

                $stored = 0;
                $errors = 0;

                $entries = isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array();
                foreach ( $entries as $entry ) {
                    $changes = isset( $entry['changes'] ) && is_array( $entry['changes'] ) ? $entry['changes'] : array();
                    foreach ( $changes as $chg ) {
                        $value = isset( $chg['value'] ) && is_array( $chg['value'] ) ? $chg['value'] : array();
                        $leadgen_id = isset( $value['leadgen_id'] ) ? (string) $value['leadgen_id'] : '';
                        if ( $leadgen_id === '' ) continue;

                        $page_id = isset( $value['page_id'] ) ? (string) $value['page_id'] : '';
                        $fetched = ai_suite_fb_fetch_lead( $leadgen_id, $page_id );
                        $lead_id = ai_suite_fb_store_lead( $leadgen_id, $value, $raw, $fetched );

                        if ( $lead_id ) {
                            $stored++;
                        } else {
                            $errors++;
                        }
                    }
                }

                return new WP_REST_Response( array(
                    'ok'     => true,
                    'stored' => $stored,
                    'errors' => $errors,
                ), 200 );
            },
            'permission_callback' => '__return_true',
        ),
    ) );
}, 20 );

/**
 * Admin: Save settings
 */
add_action( 'admin_post_ai_suite_save_fb_settings', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( esc_html__( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_fb_settings' );

    $enabled = isset( $_POST['enabled'] ) ? 1 : 0;
    $auto_convert = isset( $_POST['auto_convert'] ) ? 1 : 0;

    $verify_token  = isset( $_POST['verify_token'] ) ? sanitize_text_field( wp_unslash( $_POST['verify_token'] ) ) : '';
    $app_secret    = isset( $_POST['app_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['app_secret'] ) ) : '';
    $graph_version = isset( $_POST['graph_version'] ) ? sanitize_text_field( wp_unslash( $_POST['graph_version'] ) ) : 'v19.0';
    $default_token = isset( $_POST['default_page_token'] ) ? sanitize_text_field( wp_unslash( $_POST['default_page_token'] ) ) : '';

    $page_tokens_txt = isset( $_POST['page_tokens'] ) ? (string) wp_unslash( $_POST['page_tokens'] ) : '';
    $map_txt = isset( $_POST['form_company_map'] ) ? (string) wp_unslash( $_POST['form_company_map'] ) : '';

    $page_tokens = ai_suite_fb_parse_kv_lines( $page_tokens_txt );
    $map_lines = ai_suite_fb_parse_kv_lines( $map_txt );
    $form_company_map = array();
    foreach ( $map_lines as $form_id => $company_id ) {
        $form_company_map[ (string) $form_id ] = absint( $company_id );
    }

    $settings = ai_suite_fb_get_settings();
    $settings['enabled'] = $enabled;
    $settings['auto_convert'] = $auto_convert;
    if ( $verify_token !== '' ) $settings['verify_token'] = $verify_token;
    $settings['app_secret'] = $app_secret;
    $settings['graph_version'] = $graph_version;
    $settings['default_page_token'] = $default_token;
    $settings['page_tokens'] = $page_tokens;
    $settings['form_company_map'] = $form_company_map;

    ai_suite_fb_save_settings( $settings );

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=facebook_leads&saved=1' ) );
    exit;
} );

/**
 * Admin: Convert lead (manual)
 */
add_action( 'admin_post_ai_suite_fb_convert_lead', function() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( esc_html__( 'Neautorizat', 'ai-suite' ) );
    }
    $lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
    check_admin_referer( 'ai_suite_fb_convert_' . $lead_id );

    $res = ai_suite_fb_convert_lead_to_candidate( $lead_id );
    $msg = is_wp_error( $res ) ? 'err' : 'ok';
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=facebook_leads&convert=' . $msg ) );
    exit;
} );

/**
 * Admin AJAX: Test token by calling Graph /me (simple healthcheck).
 */
add_action( 'wp_ajax_ai_suite_fb_test_token', function() {
    if ( function_exists( 'ai_suite_ajax_guard' ) ) {
        ai_suite_ajax_guard();
    } else {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
        }
    }

    $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    if ( $token === '' ) {
        wp_send_json_error( array( 'message' => __( 'Token lipsă', 'ai-suite' ) ), 400 );
    }

    $url = 'https://graph.facebook.com/me?fields=id,name&access_token=' . rawurlencode( $token );
    $resp = wp_remote_get( $url, array( 'timeout' => 20 ) );
    if ( is_wp_error( $resp ) ) {
        wp_send_json_error( array( 'message' => $resp->get_error_message() ), 500 );
    }
    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    $json = json_decode( $body, true );

    if ( $code < 200 || $code >= 300 ) {
        $msg = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : __( 'Eroare Graph API', 'ai-suite' );
        wp_send_json_error( array( 'message' => $msg, 'status' => $code, 'raw' => $body ), $code );
    }

    wp_send_json_success( array( 'me' => $json ) );
} );
