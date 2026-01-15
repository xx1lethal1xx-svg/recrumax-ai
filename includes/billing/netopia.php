<?php
/**
 * AI Suite – NETOPIA (mobilPay) payments integration (Hosted Page)
 *
 * Provides:
 * - Create encrypted payment request (env_key + data + optional cipher/iv)
 * - Redirect user via auto-submitting form to NETOPIA hosted payment page
 * - Confirm (IPN) endpoint that decrypts and activates subscription
 *
 * Notes:
 * - Implements a hybrid RSA + symmetric encryption approach compatible with mobilPay v1.x payload shape.
 * - Defensive parsing: multiple payload variants (mobilpay root vs order root).
 *
 * ADD-ONLY module (introduced in v4.4.0). This file is safe to replace in newer patches.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_netopia_settings' ) ) {
    function ai_suite_netopia_settings() {
        $s = function_exists( 'ai_suite_billing_get_settings' ) ? ai_suite_billing_get_settings() : array();
        return array(
            'mode' => sanitize_key( (string) ( $s['mode'] ?? 'stripe' ) ),
            'sandbox' => ! empty( $s['netopia_sandbox'] ),
            'signature' => (string) ( $s['netopia_signature'] ?? '' ),
            'public_cert_pem' => (string) ( $s['netopia_public_cert_pem'] ?? '' ),
            'private_key_pem' => (string) ( $s['netopia_private_key_pem'] ?? '' ),
            // Optional overrides (some accounts use different endpoints)
            'live_url' => (string) ( $s['netopia_live_url'] ?? '' ),
            'sandbox_url' => (string) ( $s['netopia_sandbox_url'] ?? '' ),
        );
    }
}

if ( ! function_exists( 'ai_suite_netopia_payment_url' ) ) {
    function ai_suite_netopia_payment_url() {
        $cfg = ai_suite_netopia_settings();

        // Defaults from mobilPay docs (v1.x):
        // Live:    https://secure.mobilpay.ro
        // Sandbox: https://sandboxsecure.mobilpay.ro
        // Some merchants may use different endpoints – allow override in settings.
        $default_live = 'https://secure.mobilpay.ro';
        $default_sandbox = 'https://sandboxsecure.mobilpay.ro';

        if ( ! empty( $cfg['sandbox'] ) ) {
            $u = trim( (string) ( $cfg['sandbox_url'] ?? '' ) );
            return $u ? $u : $default_sandbox;
        }

        $u = trim( (string) ( $cfg['live_url'] ?? '' ) );
        return $u ? $u : $default_live;
    }
}

if ( ! function_exists( 'ai_suite_netopia_is_configured' ) ) {
    function ai_suite_netopia_is_configured() {
        $cfg = ai_suite_netopia_settings();
        return ( ! empty( $cfg['signature'] ) && ! empty( $cfg['public_cert_pem'] ) && ! empty( $cfg['private_key_pem'] ) );
    }
}

if ( ! function_exists( 'ai_suite_netopia_order_id' ) ) {
    function ai_suite_netopia_order_id( $company_id ) {
        $company_id = absint( $company_id );
        $salt = wp_generate_password( 6, false, false );
        // AIS-<company>-<UTC timestamp YYYYMMDDHHMMSS>-<salt>
        return 'AIS-' . $company_id . '-' . gmdate('YmdHis') . '-' . $salt;
    }
}

if ( ! function_exists( 'ai_suite_netopia_build_xml' ) ) {
    function ai_suite_netopia_build_xml( $company_id, $plan, $order_id ) {
        $company_id = absint( $company_id );
        $plan_id = sanitize_key( (string) ( $plan['id'] ?? '' ) );
        $plan_name = (string) ( $plan['name'] ?? $plan_id );
        $amount = (float) ( $plan['price_monthly'] ?? 0 );
        $currency = strtoupper( (string) ( $plan['currency'] ?? 'EUR' ) );

        $cfg = ai_suite_netopia_settings();
        $signature = (string) $cfg['signature'];

        $portal_url = function_exists( 'aisuite_get_portal_url' ) ? aisuite_get_portal_url() : home_url( '/portal/' );
        $return_url = add_query_arg( array( 'tab' => 'billing', 'billing' => 'success', 'provider' => 'netopia' ), $portal_url );
        $confirm_url = rest_url( 'ai-suite/v1/netopia/confirm' );

        $company_title = get_the_title( $company_id );
        $user = wp_get_current_user();
        $email = ( $user && ! empty( $user->user_email ) ) ? (string) $user->user_email : '';

        // XML aligned to common mobilPay hosted page schema.
        // We keep it minimal but with correct params structure (<param><name>..</name><value>..</value></param>).
        $timestamp = gmdate('YmdHis');

        $xml  = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<order id="' . esc_attr( $order_id ) . '" type="card" timestamp="' . esc_attr( $timestamp ) . '">';
        $xml .=   '<signature>' . esc_html( $signature ) . '</signature>';
        $xml .=   '<url>';
        $xml .=     '<return>' . esc_html( $return_url ) . '</return>';
        $xml .=     '<confirm>' . esc_html( $confirm_url ) . '</confirm>';
        $xml .=   '</url>';
        $xml .=   '<invoice currency="' . esc_attr( $currency ) . '" amount="' . esc_attr( number_format( $amount, 2, '.', '' ) ) . '">';
        $xml .=     '<details>' . esc_html( 'Abonament ' . $plan_name . ' (' . $plan_id . ')' ) . '</details>';
        $xml .=     '<contact_info>';
        $xml .=       '<billing type="person">';
        $xml .=         '<email>' . esc_html( $email ) . '</email>';
        $xml .=         '<first_name>' . esc_html( $company_title ) . '</first_name>';
        $xml .=         '<last_name>' . esc_html( (string) $company_id ) . '</last_name>';
        $xml .=       '</billing>';
        $xml .=     '</contact_info>';
        $xml .=   '</invoice>';
        $xml .=   '<params>';
        $xml .=     '<param><name>company_id</name><value>' . esc_html( (string) $company_id ) . '</value></param>';
        $xml .=     '<param><name>plan_id</name><value>' . esc_html( $plan_id ) . '</value></param>';
        $xml .=   '</params>';
        $xml .= '</order>';

        return $xml;
    }
}

if ( ! function_exists( 'ai_suite_netopia_encrypt' ) ) {
    function ai_suite_netopia_encrypt( $xml, $public_cert_pem ) {
        $public_cert_pem = (string) $public_cert_pem;
        if ( ! $public_cert_pem ) {
            return new WP_Error( 'ai_suite_netopia_no_pub', __( 'NETOPIA public certificate lipsește.', 'ai-suite' ) );
        }

        if ( ! function_exists( 'openssl_public_encrypt' ) || ! function_exists( 'openssl_encrypt' ) ) {
            return new WP_Error( 'ai_suite_netopia_no_openssl', __( 'OpenSSL nu este disponibil pe server.', 'ai-suite' ) );
        }

        // We explicitly use AES-256-CBC and transmit cipher + iv for maximum compatibility.
        $cipher = 'AES-256-CBC';
        $key = random_bytes( 32 );
        $iv  = random_bytes( 16 );

        $enc = openssl_encrypt( (string) $xml, $cipher, $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $enc ) {
            return new WP_Error( 'ai_suite_netopia_enc_fail', __( 'Nu am putut cripta cererea NETOPIA (data).', 'ai-suite' ) );
        }

        $env = '';
        $ok = openssl_public_encrypt( $key, $env, $public_cert_pem, OPENSSL_PKCS1_PADDING );
        if ( ! $ok ) {
            return new WP_Error( 'ai_suite_netopia_env_fail', __( 'Nu am putut cripta cererea NETOPIA (env_key).', 'ai-suite' ) );
        }

        return array(
            'env_key' => base64_encode( $env ),
            'data'    => base64_encode( $enc ),
            'cipher'  => $cipher,
            'iv'      => base64_encode( $iv ),
        );
    }
}

if ( ! function_exists( 'ai_suite_netopia_decrypt' ) ) {
    function ai_suite_netopia_decrypt( $env_key_b64, $data_b64, $private_key_pem, $cipher = '', $iv_b64 = '' ) {
        $private_key_pem = (string) $private_key_pem;
        if ( ! $private_key_pem ) {
            return new WP_Error( 'ai_suite_netopia_no_priv', __( 'NETOPIA private key lipsește.', 'ai-suite' ) );
        }
        if ( ! function_exists( 'openssl_private_decrypt' ) || ! function_exists( 'openssl_decrypt' ) || ! function_exists( 'openssl_open' ) ) {
            return new WP_Error( 'ai_suite_netopia_no_openssl', __( 'OpenSSL nu este disponibil pe server.', 'ai-suite' ) );
        }

        // Accept both base64 and raw values.
        $env_raw  = base64_decode( (string) $env_key_b64, true );
        $data_raw = base64_decode( (string) $data_b64, true );
        if ( false === $env_raw ) $env_raw = (string) $env_key_b64;
        if ( false === $data_raw ) $data_raw = (string) $data_b64;

        if ( ! is_string( $env_raw ) || $env_raw === '' || ! is_string( $data_raw ) || $data_raw === '' ) {
            return new WP_Error( 'ai_suite_netopia_bad_payload', __( 'Payload NETOPIA invalid.', 'ai-suite' ) );
        }

        $key = '';
        $ok = openssl_private_decrypt( $env_raw, $key, $private_key_pem, OPENSSL_PKCS1_PADDING );
        if ( ! $ok || ! $key ) {
            return new WP_Error( 'ai_suite_netopia_dec_env_fail', __( 'Nu am putut decripta env_key NETOPIA.', 'ai-suite' ) );
        }

        $cipher = $cipher ? (string) $cipher : '';
        $cipher_norm = strtoupper( trim( $cipher ) );

        // Prefer AES (our request uses AES, and many IPNs send AES fields).
        $iv = '';
        if ( $iv_b64 ) {
            $iv_try = base64_decode( (string) $iv_b64, true );
            if ( is_string( $iv_try ) && strlen( $iv_try ) === 16 ) $iv = $iv_try;
        }

        $tries = array();

        // AES-256-CBC with provided IV.
        if ( $iv ) {
            $tries[] = array( 'AES-256-CBC', $key, $iv, $data_raw );
        }

        // AES-256-CBC with IV embedded at start.
        if ( strlen( $data_raw ) > 16 ) {
            $tries[] = array( 'AES-256-CBC', $key, substr( $data_raw, 0, 16 ), substr( $data_raw, 16 ) );
        }

        // Some implementations use AES-128 with first 16 bytes of key.
        if ( strlen( $key ) >= 16 ) {
            $k16 = substr( $key, 0, 16 );
            if ( $iv ) {
                $tries[] = array( 'AES-128-CBC', $k16, $iv, $data_raw );
            }
            if ( strlen( $data_raw ) > 16 ) {
                $tries[] = array( 'AES-128-CBC', $k16, substr( $data_raw, 0, 16 ), substr( $data_raw, 16 ) );
            }
        }

        // RC4 fallback via openssl_open (used by some older libs).
        if ( $cipher_norm === 'RC4' || $cipher_norm === '' ) {
            $opened = '';
            if ( @openssl_open( $data_raw, $opened, $env_raw, $private_key_pem, 'RC4' ) ) {
                if ( is_string( $opened ) && $opened !== '' ) {
                    return $opened;
                }
            }
        }

        foreach ( $tries as $t ) {
            $c = $t[0];
            $k = $t[1];
            $iv_try = $t[2];
            $payload = $t[3];
            $plain = openssl_decrypt( $payload, $c, $k, OPENSSL_RAW_DATA, $iv_try );
            if ( is_string( $plain ) && $plain !== '' && strpos( $plain, '<' ) !== false ) {
                return $plain;
            }
        }

        return new WP_Error( 'ai_suite_netopia_dec_fail', __( 'Nu am putut decripta payload-ul NETOPIA.', 'ai-suite' ) );
    }
}

// --------------------
// Parsing helpers
// --------------------

if ( ! function_exists( 'ai_suite_netopia_xml_load' ) ) {
    function ai_suite_netopia_xml_load( $xml ) {
        try {
            libxml_use_internal_errors( true );
            $xo = simplexml_load_string( (string) $xml );
            if ( $xo ) return $xo;
        } catch ( Exception $e ) {}
        return null;
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_order_node' ) ) {
    function ai_suite_netopia_find_order_node( $xml_obj ) {
        if ( ! $xml_obj ) return null;
        // Root might be <order> or <mobilpay>
        if ( strtolower( $xml_obj->getName() ) === 'order' ) return $xml_obj;
        if ( isset( $xml_obj->order ) ) return $xml_obj->order;
        if ( isset( $xml_obj->mobilpay ) && isset( $xml_obj->mobilpay->order ) ) return $xml_obj->mobilpay->order;
        return null;
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_order_id' ) ) {
    function ai_suite_netopia_find_order_id( $xml_obj ) {
        $order = ai_suite_netopia_find_order_node( $xml_obj );
        if ( $order && isset( $order['id'] ) ) return (string) $order['id'];
        if ( isset( $xml_obj['id'] ) ) return (string) $xml_obj['id'];
        if ( isset( $xml_obj->order_id ) ) return (string) $xml_obj->order_id;
        return '';
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_action' ) ) {
    function ai_suite_netopia_find_action( $xml_obj ) {
        if ( ! $xml_obj ) return '';
        if ( isset( $xml_obj->action ) ) return (string) $xml_obj->action;
        $order = ai_suite_netopia_find_order_node( $xml_obj );
        if ( $order && isset( $order->action ) ) return (string) $order->action;
        return '';
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_crc' ) ) {
    function ai_suite_netopia_find_crc( $xml_obj ) {
        if ( ! $xml_obj ) return '';
        // Common: <mobilpay crc="...">...
        if ( strtolower( $xml_obj->getName() ) === 'mobilpay' && isset( $xml_obj['crc'] ) ) {
            return (string) $xml_obj['crc'];
        }
        if ( isset( $xml_obj->mobilpay ) && isset( $xml_obj->mobilpay['crc'] ) ) {
            return (string) $xml_obj->mobilpay['crc'];
        }
        // Some payloads: <order crc="...">
        $order = ai_suite_netopia_find_order_node( $xml_obj );
        if ( $order && isset( $order['crc'] ) ) {
            return (string) $order['crc'];
        }
        return '';
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_error_code' ) ) {
    function ai_suite_netopia_find_error_code( $xml_obj ) {
        if ( ! $xml_obj ) return null;
        // Common: <mobilpay><error code="0">...</error></mobilpay>
        if ( strtolower( $xml_obj->getName() ) === 'mobilpay' && isset( $xml_obj->error ) && isset( $xml_obj->error['code'] ) ) {
            return (int) $xml_obj->error['code'];
        }
        if ( isset( $xml_obj->mobilpay ) && isset( $xml_obj->mobilpay->error ) && isset( $xml_obj->mobilpay->error['code'] ) ) {
            return (int) $xml_obj->mobilpay->error['code'];
        }
        if ( isset( $xml_obj->error ) && isset( $xml_obj->error['code'] ) ) {
            return (int) $xml_obj->error['code'];
        }
        return null;
    }
}

if ( ! function_exists( 'ai_suite_netopia_find_param' ) ) {
    function ai_suite_netopia_find_param( $xml_obj, $name ) {
        $name = (string) $name;
        if ( ! $xml_obj || ! $name ) return '';

        $order = ai_suite_netopia_find_order_node( $xml_obj );
        if ( ! $order ) $order = $xml_obj;

        if ( ! isset( $order->params ) ) return '';

        foreach ( $order->params->param as $param ) {
            // Variant A: <param name="company_id">123</param>
            if ( isset( $param['name'] ) && (string) $param['name'] === $name ) {
                return trim( (string) $param );
            }
            // Variant B: <param><name>company_id</name><value>123</value></param>
            if ( isset( $param->name ) && isset( $param->value ) && trim( (string) $param->name ) === $name ) {
                return trim( (string) $param->value );
            }
        }

        return '';
    }
}

// --------------------
// CRC response helper
// --------------------

if ( ! function_exists( 'ai_suite_netopia_crc_xml' ) ) {
    /**
     * Merchant response to NETOPIA IPN.
     *
     * On success: <crc>OK</crc> or <crc>CRC_VALUE</crc>
     * On error:   <crc error_type="1|2" error_code="N">Message</crc>
     */
    function ai_suite_netopia_crc_xml( $ok, $message = 'OK', $code = 0, $error_type = 1 ) {
        $ok = (bool) $ok;
        $code = (int) $code;
        $msg = trim( (string) $message );
        $msg = $msg ? $msg : ( $ok ? 'OK' : 'ERROR' );

        if ( $ok ) {
            return '<?xml version="1.0" encoding="utf-8"?>' . '<crc>' . esc_html( $msg ) . '</crc>';
        }

        $error_type = (int) $error_type;
        if ( ! in_array( $error_type, array( 1, 2 ), true ) ) $error_type = 1;

        return '<?xml version="1.0" encoding="utf-8"?>' .
            '<crc error_type="' . esc_attr( (string) $error_type ) . '" error_code="' . esc_attr( (string) $code ) . '">' .
            esc_html( $msg ) .
            '</crc>';
    }
}

/**
 * Create a checkout redirect URL (internal) that will POST to NETOPIA.
 * Returns array(url, order_id).
 */
if ( ! function_exists( 'ai_suite_netopia_prepare_checkout' ) ) {
    function ai_suite_netopia_prepare_checkout( $company_id, $plan ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return new WP_Error( 'ai_suite_np_company', __( 'Company invalid.', 'ai-suite' ) );
        if ( ! is_array( $plan ) || empty( $plan['id'] ) ) return new WP_Error( 'ai_suite_np_plan', __( 'Plan invalid.', 'ai-suite' ) );
        if ( ! ai_suite_netopia_is_configured() ) return new WP_Error( 'ai_suite_np_cfg', __( 'NETOPIA nu este configurat în AI Suite → Billing.', 'ai-suite' ) );

        $cfg = ai_suite_netopia_settings();
        $order_id = ai_suite_netopia_order_id( $company_id );

        $xml = ai_suite_netopia_build_xml( $company_id, $plan, $order_id );
        $enc = ai_suite_netopia_encrypt( $xml, $cfg['public_cert_pem'] );
        if ( is_wp_error( $enc ) ) return $enc;

        // Upsert subscription row as pending (we'll activate on confirm/IPN).
        if ( function_exists( 'ai_suite_subscription_ensure_table' ) ) {
            ai_suite_subscription_ensure_table();
        }

        global $wpdb;
        $table = function_exists( 'ai_suite_subscriptions_table' ) ? ai_suite_subscriptions_table() : $wpdb->prefix . 'ai_suite_subscriptions';

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, current_period_end FROM {$table} WHERE provider_session_id=%s ORDER BY id DESC LIMIT 1", $order_id ), ARRAY_A );

        $data = array(
            'company_id' => $company_id,
            'plan_id' => sanitize_key( (string) $plan['id'] ),
            'provider' => 'netopia',
            'provider_session_id' => $order_id,
            'status' => 'pending',
            'current_period_end' => null,
            'meta' => wp_json_encode( array(
                'order_id' => $order_id,
                'created_at' => time(),
                'amount' => (float) ( $plan['price_monthly'] ?? 0 ),
                'currency' => (string) ( $plan['currency'] ?? 'EUR' ),
            ) ),
            'updated_at' => current_time( 'mysql' ),
        );

        if ( $existing && ! empty( $existing['id'] ) ) {
            $wpdb->update( $table, $data, array( 'id' => absint( $existing['id'] ) ), array('%d','%s','%s','%s','%s','%d','%s','%s'), array('%d') );
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert( $table, $data, array('%d','%s','%s','%s','%s','%d','%s','%s','%s') );
        }

        // Store encrypted fields in a short-lived transient, then redirect to internal auto-post endpoint.
        $token = wp_generate_password( 20, false, false );

        // Billing history (checkout created) + pending invoice
        if ( function_exists( 'ai_suite_billing_event_add' ) ) {
            $amount = (int) round( ((float)($plan['price_monthly'] ?? 0)) * 100 );
            $cur = (string) ( $plan['currency'] ?? 'EUR' );
            ai_suite_billing_event_add( array(
                'company_id' => $company_id,
                'provider' => 'netopia',
                'event_type' => 'checkout_created',
                'status' => 'pending',
                'amount_cents' => $amount,
                'currency' => $cur,
                'provider_ref' => $order_id,
                'meta' => array(
                    'plan_id' => (string) ($plan['id'] ?? ''),
                    'order_id' => $order_id,
                ),
            ) );
        }
        if ( function_exists( 'ai_suite_billing_invoice_upsert' ) ) {
            $amount = (int) round( ((float)($plan['price_monthly'] ?? 0)) * 100 );
            $cur = (string) ( $plan['currency'] ?? 'EUR' );
            ai_suite_billing_invoice_upsert( array(
                'company_id' => $company_id,
                'provider' => 'netopia',
                'provider_invoice_id' => $order_id,
                'status' => 'pending',
                'amount_cents' => $amount,
                'currency' => $cur,
                'period_start' => 0,
                'period_end' => 0,
                'meta' => array(
                    'plan_id' => (string) ($plan['id'] ?? ''),
                    'order_id' => $order_id,
                    'note' => 'pending invoice created at checkout',
                ),
            ) );
        }

        $payload = array(
            'uid' => get_current_user_id(),
            'company_id' => $company_id,
            'order_id' => $order_id,
            'payment_url' => ai_suite_netopia_payment_url(),
            'env_key' => (string) $enc['env_key'],
            'data'    => (string) $enc['data'],
            'cipher'  => (string) $enc['cipher'],
            'iv'      => (string) $enc['iv'],
        );

        set_transient( 'ai_suite_np_checkout_' . $token, $payload, 10 * MINUTE_IN_SECONDS );

        $redir = add_query_arg( array(
            'ai_suite_netopia_checkout' => '1',
            'token' => $token,
        ), home_url( '/' ) );

        return array( 'url' => $redir, 'order_id' => $order_id );
    }
}

// Internal redirect endpoint (template_redirect) – prints HTML form and auto-submits to NETOPIA.
if ( ! function_exists( 'ai_suite_netopia_template_redirect' ) ) {
    function ai_suite_netopia_template_redirect() {
        if ( empty( $_GET['ai_suite_netopia_checkout'] ) || (string) $_GET['ai_suite_netopia_checkout'] !== '1' ) {
            return;
        }

        $token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_GET['token']) : '';
        if ( ! $token ) {
            status_header( 400 );
            echo 'Bad request';
            exit;
        }

        $payload = get_transient( 'ai_suite_np_checkout_' . $token );
        if ( ! is_array( $payload ) ) {
            status_header( 410 );
            echo 'Expired';
            exit;
        }

        // Basic safety: require the same logged-in user that initiated checkout.
        $uid = (int) ( $payload['uid'] ?? 0 );
        if ( ! is_user_logged_in() || ! $uid || get_current_user_id() !== $uid ) {
            status_header( 403 );
            echo 'Forbidden';
            exit;
        }

        delete_transient( 'ai_suite_np_checkout_' . $token );

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );

        $action = esc_url( (string) ( $payload['payment_url'] ?? ai_suite_netopia_payment_url() ) );
        $env_key = esc_attr( (string) ( $payload['env_key'] ?? '' ) );
        $data = esc_attr( (string) ( $payload['data'] ?? '' ) );
        $cipher = esc_attr( (string) ( $payload['cipher'] ?? '' ) );
        $iv = esc_attr( (string) ( $payload['iv'] ?? '' ) );

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html__( 'Redirect către NETOPIA…', 'ai-suite' ) . '</title>';
        echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0;padding:24px;background:#0b1220;color:#fff} .card{max-width:520px;margin:40px auto;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px} .muted{opacity:.85;margin-top:8px}</style>';
        echo '</head><body>';
        echo '<div class="card">';
        echo '<div style="font-size:18px;font-weight:700">' . esc_html__( 'Se deschide plata în NETOPIA', 'ai-suite' ) . '</div>';
        echo '<div class="muted">' . esc_html__( 'Dacă nu se redirecționează automat în 2 secunde, apasă butonul de mai jos.', 'ai-suite' ) . '</div>';
        echo '<form id="np" method="post" action="' . $action . '">';
        echo '<input type="hidden" name="env_key" value="' . $env_key . '">';
        echo '<input type="hidden" name="data" value="' . $data . '">';
        // Optional fields.
        if ( $cipher ) echo '<input type="hidden" name="cipher" value="' . $cipher . '">';
        if ( $iv ) echo '<input type="hidden" name="iv" value="' . $iv . '">';
        echo '<button type="submit" style="margin-top:14px;padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.2);background:#2563eb;color:#fff;font-weight:700;cursor:pointer">' . esc_html__( 'Continuă către NETOPIA', 'ai-suite' ) . '</button>';
        echo '</form>';
        echo '</div>';
        echo '<script>setTimeout(function(){try{document.getElementById("np").submit();}catch(e){}}, 600);</script>';
        echo '</body></html>';
        exit;
    }
    add_action( 'template_redirect', 'ai_suite_netopia_template_redirect', 0 );
}

// REST confirm endpoint (IPN)
if ( ! function_exists( 'ai_suite_netopia_register_routes' ) ) {
    function ai_suite_netopia_register_routes() {
        register_rest_route( 'ai-suite/v1', '/netopia/confirm', array(
            'methods'  => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function( WP_REST_Request $req ) {
                $p = $req->get_params();
                $env_key = isset($p['env_key']) ? (string) $p['env_key'] : '';
                $data    = isset($p['data']) ? (string) $p['data'] : '';
                $cipher  = isset($p['cipher']) ? (string) $p['cipher'] : '';
                $iv      = isset($p['iv']) ? (string) $p['iv'] : '';

                if ( ! $env_key || ! $data ) {
                    $xml = ai_suite_netopia_crc_xml( false, 'Missing env_key/data', 10, 2 );
                    $resp = new WP_REST_Response( $xml, 400 );
                    $resp->header( 'Content-Type', 'text/xml; charset=utf-8' );
                    return $resp;
                }

                $cfg = ai_suite_netopia_settings();
                $plain = ai_suite_netopia_decrypt( $env_key, $data, $cfg['private_key_pem'], $cipher, $iv );
                if ( is_wp_error( $plain ) ) {
                    if ( function_exists('aisuite_log') ) {
                        aisuite_log( 'error', 'NETOPIA confirm decrypt failed', array( 'err' => $plain->get_error_message() ) );
                    }
                    $xml = ai_suite_netopia_crc_xml( false, 'Decrypt failed', 20, 1 );
                    $resp = new WP_REST_Response( $xml, 200 );
                    $resp->header( 'Content-Type', 'text/xml; charset=utf-8' );
                    return $resp;
                }

                $xo = ai_suite_netopia_xml_load( $plain );
                if ( ! $xo ) {
                    $xml = ai_suite_netopia_crc_xml( false, 'Invalid XML', 21, 2 );
                    $resp = new WP_REST_Response( $xml, 200 );
                    $resp->header( 'Content-Type', 'text/xml; charset=utf-8' );
                    return $resp;
                }

                $order_id = (string) ai_suite_netopia_find_order_id( $xo );
                $action = strtolower( trim( (string) ai_suite_netopia_find_action( $xo ) ) );
                $crc = (string) ai_suite_netopia_find_crc( $xo );
                $err_code = ai_suite_netopia_find_error_code( $xo );

                if ( ! $order_id ) {
                    $xml = ai_suite_netopia_crc_xml( false, 'Order id missing', 30, 2 );
                    $resp = new WP_REST_Response( $xml, 200 );
                    $resp->header( 'Content-Type', 'text/xml; charset=utf-8' );
                    return $resp;
                }

                // Payment success decision.
                $ok = false;
                if ( $err_code === null || (int) $err_code === 0 ) {
                    if ( in_array( $action, array( 'confirmed', 'paid', 'credit', 'ok', 'success' ), true ) ) {
                        $ok = true;
                    }
                }

                // Determine company/plan.
                $company_id = 0;
                $plan_id = '';

                // First: from params.
                $company_param = ai_suite_netopia_find_param( $xo, 'company_id' );
                if ( $company_param !== '' ) $company_id = absint( $company_param );
                $plan_param = ai_suite_netopia_find_param( $xo, 'plan_id' );
                if ( $plan_param !== '' ) $plan_id = sanitize_key( $plan_param );

                // Fallback from order id prefix.
                if ( ! $company_id && strpos( $order_id, 'AIS-' ) === 0 ) {
                    $parts = explode( '-', $order_id );
                    if ( isset( $parts[1] ) ) $company_id = absint( $parts[1] );
                }

                // Update subscription state (idempotent, updates by provider_session_id).
                global $wpdb;
                $table = function_exists( 'ai_suite_subscriptions_table' ) ? ai_suite_subscriptions_table() : $wpdb->prefix . 'ai_suite_subscriptions';

                if ( function_exists( 'ai_suite_subscription_ensure_table' ) ) {
                    ai_suite_subscription_ensure_table();
                }

                $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, company_id, plan_id, status, current_period_end, meta FROM {$table} WHERE provider_session_id=%s ORDER BY id DESC LIMIT 1", $order_id ), ARRAY_A );

                if ( $row ) {
                    $company_id = $company_id ? $company_id : absint( $row['company_id'] );
                    $plan_id = $plan_id ? $plan_id : sanitize_key( (string) $row['plan_id'] );
                }

                if ( $company_id ) {
                    $now = time();
                    $prev_end = $row && ! empty( $row['current_period_end'] ) ? absint( $row['current_period_end'] ) : 0;
                    $base = max( $now, $prev_end );
                    $new_end = $ok ? ( $base + ( 30 * DAY_IN_SECONDS ) ) : $prev_end;

                    // Merge meta.
                    $meta = array();
                    if ( $row && ! empty( $row['meta'] ) ) {
                        $m = json_decode( (string) $row['meta'], true );
                        if ( is_array( $m ) ) $meta = $m;
                    }

                    $meta['ipn'] = array(
                        'received_at' => time(),
                        'order_id' => $order_id,
                        'action' => $action,
                        'crc' => $crc,
                        'error_code' => $err_code,
                    );

                    $meta['raw_xml'] = (string) $plain;

                    $update_data = array(
                        'provider' => 'netopia',
                        'status' => $ok ? 'active' : 'failed',
                        'current_period_end' => $ok ? $new_end : $prev_end,
                        'meta' => wp_json_encode( $meta ),
                        'updated_at' => current_time('mysql'),
                    );

                    if ( $row && ! empty( $row['id'] ) ) {
                        $wpdb->update( $table, $update_data, array( 'id' => absint( $row['id'] ) ), array('%s','%s','%d','%s','%s'), array('%d') );
                    } else {
                        $wpdb->insert( $table, array_merge( array(
                            'company_id' => $company_id,
                            'plan_id' => $plan_id,
                            'provider' => 'netopia',
                            'provider_session_id' => $order_id,
                            'status' => $ok ? 'active' : 'failed',
                            'current_period_end' => $ok ? $new_end : null,
                            'meta' => wp_json_encode( $meta ),
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ), array() ) );
                    }

                    if ( $ok && $plan_id ) {
                        update_post_meta( $company_id, '_ai_suite_plan', $plan_id );
                    }

                    if ( function_exists('aisuite_log') ) {
                        aisuite_log( 'info', 'NETOPIA confirm processed', array(
                            'ok' => $ok,
                            'company_id' => $company_id,
                            'plan_id' => $plan_id,
                            'order_id' => $order_id,
                            'action' => $action,
                            'error_code' => $err_code,
                        ) );
                    }
                } else {
                    if ( function_exists('aisuite_log') ) {
                        aisuite_log( 'warning', 'NETOPIA confirm: company not resolved', array(
                            'order_id' => $order_id,
                            'action' => $action,
                            'error_code' => $err_code,
                        ) );
                    }
                }


                // Billing history (confirm)
                if ( function_exists( 'ai_suite_billing_invoice_upsert' ) ) {
                    $amount = 0; $currency = 'EUR';
                    if ( ! empty( $meta['amount'] ) ) { $amount = (int) round( ((float)$meta['amount']) * 100 ); }
                    if ( ! empty( $meta['currency'] ) ) { $currency = (string) $meta['currency']; }

                    $inv_id = ai_suite_billing_invoice_upsert( array(
                        'company_id' => $company_id,
                        'provider' => 'netopia',
                        'provider_invoice_id' => $order_id,
                        'status' => $ok ? 'paid' : 'failed',
                        'amount_cents' => $amount,
                        'currency' => $currency,
                        'period_start' => absint( $period_start ?? 0 ),
                        'period_end' => absint( $period_end ?? 0 ),
                        'meta' => array(
                            'plan_id' => (string) $plan_id,
                            'order_id' => $order_id,
                            'action' => $action,
                            'gateway' => 'NETOPIA',
                        ),
                    ) );

                    if ( function_exists( 'ai_suite_billing_event_add' ) ) {
                        ai_suite_billing_event_add( array(
                            'company_id' => $company_id,
                            'provider' => 'netopia',
                            'event_type' => 'netopia_confirm',
                            'status' => $ok ? 'paid' : 'failed',
                            'amount_cents' => $amount,
                            'currency' => $currency,
                            'provider_ref' => $order_id,
                            'invoice_id' => $inv_id,
                            'meta' => array(
                                'plan_id' => (string) $plan_id,
                                'action' => $action,
                            ),
                        ) );
                    }
                }

                // Success response should echo CRC value when available.
                $reply_msg = $crc ? $crc : 'OK';
                $xml = ai_suite_netopia_crc_xml( true, $reply_msg, 0 );
                $resp = new WP_REST_Response( $xml, 200 );
                $resp->header( 'Content-Type', 'text/xml; charset=utf-8' );
                return $resp;
            },
        ) );
    }
    add_action( 'rest_api_init', 'ai_suite_netopia_register_routes' );
}
