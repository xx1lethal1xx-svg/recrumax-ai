<?php
/**
 * AI Suite – Billing & Subscriptions (Stripe Checkout)
 *
 * Provides:
 * - Plan catalog (options)
 * - Company subscription state (DB + meta)
 * - Stripe Checkout session creation (AJAX)
 * - Stripe webhook handler (REST)
 *
 * ADD-ONLY.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'AI_SUITE_OPTION_BILLING' ) ) {
    define( 'AI_SUITE_OPTION_BILLING', 'ai_suite_billing' );
}
if ( ! defined( 'AI_SUITE_OPTION_BILLING_PLANS' ) ) {
    define( 'AI_SUITE_OPTION_BILLING_PLANS', 'ai_suite_billing_plans' );
}
if ( ! defined( 'AI_SUITE_OPTION_BILLING_DEFAULT_PLAN' ) ) {
    define( 'AI_SUITE_OPTION_BILLING_DEFAULT_PLAN', 'ai_suite_billing_default_plan' );
}

if ( ! function_exists( 'ai_suite_subscriptions_table' ) ) {
    function ai_suite_subscriptions_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_suite_subscriptions';
    }
}

if ( ! function_exists( 'ai_suite_billing_defaults' ) ) {
    function ai_suite_billing_defaults() {
        return array(
            'mode' => 'stripe',
            'currency' => 'eur',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',

            // NETOPIA (mobilPay) – optional
            'netopia_sandbox' => 1,
            'netopia_signature' => '',
            'netopia_public_cert_pem' => '',
            'netopia_private_key_pem' => '',
            // Optional overrides for hosted payment URL (defaults to mobilPay docs)
            'netopia_live_url' => 'https://secure.mobilpay.ro',
            'netopia_sandbox_url' => 'https://sandboxsecure.mobilpay.ro',
            'stripe_app_info' => array(
                'name' => 'AI Suite',
                'url'  => home_url('/'),
                'version' => defined('AI_SUITE_VER') ? AI_SUITE_VER : 'dev',
            ),
            'success_path' => 'portal',

            // Trial / gating
            'trial_enabled' => 1,
            'trial_days' => 14,
            'trial_plan_id' => 'pro',
            'trial_once_per_company' => 1,
            'trial_grace_days' => 0,

            // Expiry / renewal automation
            'expiry_grace_days' => 3,
            'expiry_notify_days' => 3,
            'expiry_sender_email' => '',
            'expiry_sender_name' => 'RecruMax',

            // Invoice / HTML billing (issuer details + numbering)
            'invoice_series_template' => 'RMX-{Y}-',
            'invoice_number_padding' => 4,
            'invoice_issuer_name' => 'RecruMax',
            'invoice_issuer_cui' => '',
            'invoice_issuer_reg' => '',
            'invoice_issuer_address' => '',
            'invoice_issuer_city' => '',
            'invoice_issuer_country' => 'RO',
            'invoice_issuer_iban' => '',
            'invoice_issuer_bank' => '',
            'invoice_issuer_vat' => 0,
            'invoice_issuer_email' => '',
            'invoice_issuer_phone' => '',
            'invoice_issuer_website' => home_url('/'),
            'invoice_issuer_logo_url' => '',
            'invoice_footer_note' => __( 'Document generat automat de AI Suite. Print → Save as PDF.', 'ai-suite' ),

        );
    }
}

if ( ! function_exists( 'ai_suite_billing_get_settings' ) ) {
    function ai_suite_billing_get_settings() {
        $s = get_option( AI_SUITE_OPTION_BILLING, array() );
        if ( ! is_array( $s ) ) $s = array();
        return array_merge( ai_suite_billing_defaults(), $s );
    }
}

if ( ! function_exists( 'ai_suite_billing_get_plans' ) ) {
    function ai_suite_billing_get_plans() {
        $plans = get_option( AI_SUITE_OPTION_BILLING_PLANS, array() );
        if ( ! is_array( $plans ) || empty( $plans ) ) {
            $plans = array(
                array(
                    'id' => 'free',
                    'name' => __( 'Free', 'ai-suite' ),
                    'price_monthly' => 0,
                    'currency' => 'EUR',
                    'stripe_price_id' => '',
                    'features' => array(
                        'active_jobs' => 1,
                        'team_members' => 1,
                        'ats' => 0,
                        'ai_matching' => 0,
                        'facebook_leads' => 0,
                        'exports' => 0,
                        'promo_credits_monthly' => 0,
                        'copilot' => 0,
                    ),
                ),
                array(
                    'id' => 'pro',
                    'name' => __( 'Pro', 'ai-suite' ),
                    'price_monthly' => 99,
                    'currency' => 'EUR',
                    'stripe_price_id' => '',
                    'features' => array(
                        'active_jobs' => 20,
                        'team_members' => 5,
                        'ats' => 1,
                        'ai_matching' => 1,
                        'facebook_leads' => 1,
                        'exports' => 1,
                    ),
                ),
                array(
                    'id' => 'enterprise',
                    'name' => __( 'Enterprise', 'ai-suite' ),
                    'price_monthly' => 299,
                    'currency' => 'EUR',
                    'stripe_price_id' => '',
                    'features' => array(
                        'active_jobs' => 9999,
                        'team_members' => 50,
                        'ats' => 1,
                        'ai_matching' => 1,
                        'facebook_leads' => 1,
                        'exports' => 1,
                        'promo_credits_monthly' => 10,
                        'copilot' => 1,
                    ),
                ),
            );
            update_option( AI_SUITE_OPTION_BILLING_PLANS, $plans, false );
        }
        return $plans;
    }
}

if ( ! function_exists( 'ai_suite_billing_get_plan' ) ) {
    function ai_suite_billing_get_plan( $plan_id ) {
        $plan_id = (string) $plan_id;
        foreach ( ai_suite_billing_get_plans() as $p ) {
            if ( isset( $p['id'] ) && $p['id'] === $plan_id ) return $p;
        }
        return null;
    }
}

if ( ! function_exists( 'ai_suite_billing_get_default_plan_id' ) ) {
    function ai_suite_billing_get_default_plan_id() {
        $def = (string) get_option( AI_SUITE_OPTION_BILLING_DEFAULT_PLAN, 'free' );
        if ( ! $def ) $def = 'free';
        return $def;
    }
}



// --------------------
// Trial helpers (SaaS gating)
// --------------------
if ( ! function_exists( 'ai_suite_trial_settings' ) ) {
    function ai_suite_trial_settings() {
        $s = ai_suite_billing_get_settings();
        return array(
            'enabled' => ! empty( $s['trial_enabled'] ),
            'days' => max( 0, (int) ( $s['trial_days'] ?? 0 ) ),
            'plan_id' => sanitize_key( (string) ( $s['trial_plan_id'] ?? 'pro' ) ),
            'once' => ! empty( $s['trial_once_per_company'] ),
            'grace_days' => max( 0, (int) ( $s['trial_grace_days'] ?? 0 ) ),
        );
    }
}

if ( ! function_exists( 'ai_suite_trial_meta_keys' ) ) {
    function ai_suite_trial_meta_keys() {
        return array(
            'started' => '_ai_suite_trial_started_at',
            'end' => '_ai_suite_trial_end_at',
            'used' => '_ai_suite_trial_used',
        );
    }
}

if ( ! function_exists( 'ai_suite_trial_maybe_start' ) ) {
    function ai_suite_trial_maybe_start( $company_id, $force = false ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return false;

        $cfg = ai_suite_trial_settings();
        if ( ! $cfg['enabled'] || $cfg['days'] <= 0 || ! $cfg['plan_id'] ) return false;

        $keys = ai_suite_trial_meta_keys();

        $used = (int) get_post_meta( $company_id, $keys['used'], true );
        if ( $cfg['once'] && $used && ! $force ) {
            return false;
        }

        $end = (int) get_post_meta( $company_id, $keys['end'], true );
        if ( $end && ! $force ) {
            return false; // already started
        }

        $now = time();
        $end_ts = $now + ( DAY_IN_SECONDS * (int) $cfg['days'] );

        update_post_meta( $company_id, $keys['started'], (string) $now );
        update_post_meta( $company_id, $keys['end'], (string) $end_ts );
        update_post_meta( $company_id, $keys['used'], '1' );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_trial_is_active' ) ) {
    function ai_suite_trial_is_active( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return false;

        $cfg = ai_suite_trial_settings();
        if ( ! $cfg['enabled'] ) return false;

        $keys = ai_suite_trial_meta_keys();
        $end = (int) get_post_meta( $company_id, $keys['end'], true );
        if ( ! $end ) return false;

        $grace = (int) $cfg['grace_days'];
        $end_with_grace = $end + ( DAY_IN_SECONDS * $grace );

        // If a paid subscription is active, trial is irrelevant.
        if ( function_exists( 'ai_suite_subscription_is_active' ) && ai_suite_subscription_is_active( $company_id ) ) {
            return false;
        }

        return time() <= $end_with_grace;
    }
}

if ( ! function_exists( 'ai_suite_trial_remaining_days' ) ) {
    function ai_suite_trial_remaining_days( $company_id ) {
        $company_id = absint( $company_id );
        $keys = ai_suite_trial_meta_keys();
        $end = (int) get_post_meta( $company_id, $keys['end'], true );
        if ( ! $end ) return 0;
        $secs = max( 0, $end - time() );
        return (int) ceil( $secs / DAY_IN_SECONDS );
    }
}

if ( ! function_exists( 'ai_suite_subscription_install' ) ) {
    function ai_suite_subscription_install() {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        global $wpdb;
        $table = ai_suite_subscriptions_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            plan_id VARCHAR(50) NOT NULL DEFAULT 'free',
            provider VARCHAR(20) NOT NULL DEFAULT 'stripe',
            provider_customer_id VARCHAR(100) NULL,
            provider_subscription_id VARCHAR(100) NULL,
            provider_session_id VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'inactive',
            current_period_end BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY company_id (company_id),
            KEY provider_subscription_id (provider_subscription_id),
            KEY status (status)
        ) {$charset};";

        dbDelta( $sql );
    }
}

if ( ! function_exists( 'ai_suite_subscription_table_exists' ) ) {
    function ai_suite_subscription_table_exists() {
        global $wpdb;
        $table = ai_suite_subscriptions_table();
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        return ( $found === $table );
    }
}

if ( ! function_exists( 'ai_suite_subscription_ensure_table' ) ) {
    function ai_suite_subscription_ensure_table() {
        if ( ! ai_suite_subscription_table_exists() ) {
            ai_suite_subscription_install();
        }
    }
}

if ( ! function_exists( 'ai_suite_subscription_get_company' ) ) {
    function ai_suite_subscription_get_company( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return null;
        ai_suite_subscription_ensure_table();
        global $wpdb;
        $table = ai_suite_subscriptions_table();
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id=%d ORDER BY id DESC LIMIT 1",
            $company_id
        ), ARRAY_A );
        return $row ?: null;
    }
}

if ( ! function_exists( 'ai_suite_subscription_is_active' ) ) {
    function ai_suite_subscription_is_active( $company_id ) {
        $row = ai_suite_subscription_get_company( $company_id );
        if ( empty( $row ) ) return false;

        $st = (string) ( $row['status'] ?? '' );
        if ( ! in_array( $st, array( 'active', 'grace' ), true ) ) return false;

        $end = isset( $row['current_period_end'] ) ? absint( $row['current_period_end'] ) : 0;
        if ( ! $end ) {
            return ( $st === 'active' );
        }

        $now = time();
        if ( $end >= $now ) return true;

        // Allow grace window after expiry (configurable).
        $settings = function_exists( 'ai_suite_billing_get_settings' ) ? ai_suite_billing_get_settings() : array();
        $grace_days = isset( $settings['expiry_grace_days'] ) ? max( 0, absint( $settings['expiry_grace_days'] ) ) : 0;
        if ( $grace_days <= 0 ) return false;

        $grace_end = $end + ( DAY_IN_SECONDS * (int) $grace_days );
        return ( $now <= $grace_end );
    }
}
if ( ! function_exists( 'ai_suite_company_plan_id' ) ) {
    /**
     * Returns company plan:
     * - active subscription plan_id if present (includes grace window)
     * - else meta _ai_suite_plan
     * - else default plan
     */
    function ai_suite_company_plan_id( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return ai_suite_billing_get_default_plan_id();

        // Trial beats free/inactive subscriptions when enabled.
        if ( function_exists( 'ai_suite_trial_is_active' ) && ai_suite_trial_is_active( $company_id ) ) {
            $cfg = function_exists( 'ai_suite_trial_settings' ) ? ai_suite_trial_settings() : array();
            $trial_plan = isset( $cfg['plan_id'] ) ? sanitize_key( (string) $cfg['plan_id'] ) : '';
            if ( $trial_plan ) return $trial_plan;
        }

        $row = ai_suite_subscription_get_company( $company_id );
        if ( $row && ! empty( $row['plan_id'] ) ) {
            $pid = (string) $row['plan_id'];
            if ( $pid === 'free' ) return 'free';

            $st  = (string) ( $row['status'] ?? '' );
            $end = isset( $row['current_period_end'] ) ? absint( $row['current_period_end'] ) : 0;

            if ( in_array( $st, array( 'active', 'grace' ), true ) ) {
                if ( ! $end ) {
                    // If we don't have period_end, consider only strict active.
                    if ( $st === 'active' ) return $pid;
                } else {
                    $now = time();
                    if ( $end >= $now ) return $pid;

                    // Grace window after expiry.
                    $settings = function_exists( 'ai_suite_billing_get_settings' ) ? ai_suite_billing_get_settings() : array();
                    $grace_days = isset( $settings['expiry_grace_days'] ) ? max( 0, absint( $settings['expiry_grace_days'] ) ) : 0;
                    if ( $grace_days > 0 ) {
                        $grace_end = $end + ( DAY_IN_SECONDS * (int) $grace_days );
                        if ( $now <= $grace_end ) return $pid;
                    }
                }
            }
        }

        $m = (string) get_post_meta( $company_id, '_ai_suite_plan', true );
        if ( $m ) return $m;

        return ai_suite_billing_get_default_plan_id();
    }
}
if ( ! function_exists( 'ai_suite_company_has_feature' ) ) {
    function ai_suite_company_has_feature( $company_id, $feature_key ) {
        $plan_id = ai_suite_company_plan_id( $company_id );

        $plan = function_exists( 'ai_suite_billing_get_plan' ) ? ai_suite_billing_get_plan( $plan_id ) : null;
        if ( ! is_array( $plan ) ) {
            return false;
        }
        $features = ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) ? $plan['features'] : array();
        $val = $features[ $feature_key ] ?? 0;
        return (int) $val > 0;
    }
}

if ( ! function_exists( 'ai_suite_company_limit' ) ) {
    function ai_suite_company_limit( $company_id, $limit_key, $fallback = 0 ) {
        $plan_id = ai_suite_company_plan_id( $company_id );
        $plan = ai_suite_billing_get_plan( $plan_id );
        if ( ! $plan ) return (int)$fallback;
        $features = isset($plan['features']) && is_array($plan['features']) ? $plan['features'] : array();
        $val = $features[ $limit_key ] ?? $fallback;
        return (int)$val;
    }
}

/**
 * Stripe helpers (wp_remote_* based, no SDK).
 */
if ( ! function_exists( 'ai_suite_stripe_request' ) ) {
    function ai_suite_stripe_request( $method, $path, $params = array() ) {
        $settings = ai_suite_billing_get_settings();
        $sk = (string) ($settings['stripe_secret_key'] ?? '');
        if ( ! $sk ) {
            return new WP_Error( 'ai_suite_stripe_no_key', __( 'Stripe Secret Key lipsește în setări.', 'ai-suite' ) );
        }

        $url = 'https://api.stripe.com' . $path;
        $args = array(
            'method'  => strtoupper( $method ),
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $sk,
            ),
        );

        if ( strtoupper($method) === 'GET' ) {
            if ( ! empty( $params ) ) {
                $url .= ( strpos($url, '?') === false ? '?' : '&' ) . http_build_query( $params );
            }
        } else {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query( $params );
        }

        $resp = wp_remote_request( $url, $args );
        if ( is_wp_error( $resp ) ) return $resp;

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $body = (string) wp_remote_retrieve_body( $resp );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $msg = __( 'Eroare Stripe.', 'ai-suite' );
            if ( is_array($data) && isset($data['error']['message']) ) {
                $msg .= ' ' . (string) $data['error']['message'];
            }
            return new WP_Error( 'ai_suite_stripe_http_' . $code, $msg, array( 'status' => $code, 'body' => $data ) );
        }
        return is_array($data) ? $data : array();
    }
}

if ( ! function_exists( 'ai_suite_stripe_verify_signature' ) ) {
    /**
     * Manual Stripe webhook signature verification.
     * Uses Stripe-Signature: t=timestamp,v1=signature...
     *
     * Docs: https://docs.stripe.com/webhooks/signature
     */
    function ai_suite_stripe_verify_signature( $payload, $sig_header, $secret, $tolerance = 300 ) {
        $payload = (string) $payload;
        $sig_header = (string) $sig_header;
        $secret = (string) $secret;

        if ( ! $secret || ! $sig_header ) {
            return new WP_Error( 'ai_suite_stripe_sig_missing', __( 'Lipsește secretul webhook sau headerul Stripe-Signature.', 'ai-suite' ) );
        }

        $parts = array();
        foreach ( explode( ',', $sig_header ) as $kv ) {
            $kv = trim( $kv );
            if ( strpos( $kv, '=' ) === false ) continue;
            list($k,$v) = explode('=', $kv, 2);
            $parts[ trim($k) ][] = trim($v);
        }

        if ( empty($parts['t'][0]) || empty($parts['v1'][0]) ) {
            return new WP_Error( 'ai_suite_stripe_sig_bad', __( 'Stripe-Signature invalid.', 'ai-suite' ) );
        }

        $timestamp = (int) $parts['t'][0];
        if ( $tolerance > 0 && abs( time() - $timestamp ) > $tolerance ) {
            return new WP_Error( 'ai_suite_stripe_sig_old', __( 'Webhook prea vechi (timestamp în afara toleranței).', 'ai-suite' ) );
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac( 'sha256', $signed_payload, $secret );

        $valid = false;
        foreach ( $parts['v1'] as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                $valid = true;
                break;
            }
        }

        if ( ! $valid ) {
            return new WP_Error( 'ai_suite_stripe_sig_invalid', __( 'Semnătură webhook Stripe invalidă.', 'ai-suite' ) );
        }

        return true;
    }
}

/**
 * REST webhook: /wp-json/ai-suite/v1/stripe/webhook
 */
if ( ! function_exists( 'ai_suite_billing_register_routes' ) ) {
    function ai_suite_billing_register_routes() {
        register_rest_route( 'ai-suite/v1', '/stripe/webhook', array(
            'methods'  => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function( WP_REST_Request $req ) {
                $payload = $req->get_body();
                $settings = ai_suite_billing_get_settings();
                $secret = (string) ($settings['stripe_webhook_secret'] ?? '');
                $sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string) $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

                if ( $secret ) {
                    $ok = ai_suite_stripe_verify_signature( $payload, $sig, $secret, 300 );
                    if ( is_wp_error( $ok ) ) {
                        return new WP_REST_Response( array( 'ok' => false, 'error' => $ok->get_error_message() ), 400 );
                    }
                }

                $event = json_decode( $payload, true );
                if ( ! is_array( $event ) || empty( $event['type'] ) ) {
                    return new WP_REST_Response( array( 'ok' => false, 'error' => 'Bad payload' ), 400 );
                }

                $type = (string) $event['type'];

                // Process subscription-related events.
                try {
                    ai_suite_subscription_ensure_table();
                    ai_suite_billing_handle_stripe_event( $event );
                } catch ( Exception $e ) {
                    return new WP_REST_Response( array( 'ok' => false, 'error' => $e->getMessage() ), 500 );
                }

                return new WP_REST_Response( array( 'ok' => true, 'type' => $type ), 200 );
            },
        ) );
    }
    add_action( 'rest_api_init', 'ai_suite_billing_register_routes' );
}

if ( ! function_exists( 'ai_suite_billing_handle_stripe_event' ) ) {
    function ai_suite_billing_handle_stripe_event( $event ) {
        $type = (string) ($event['type'] ?? '');
        $obj  = $event['data']['object'] ?? array();

        // Helper for upsert.
        $upsert = function( $company_id, $fields ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) return;

            global $wpdb;
            $table = ai_suite_subscriptions_table();

            // Find existing by company.
            $existing = $wpdb->get_row( $wpdb->prepare("SELECT id FROM {$table} WHERE company_id=%d ORDER BY id DESC LIMIT 1", $company_id), ARRAY_A );
            $data = array_merge( array(
                'company_id' => $company_id,
                'updated_at' => current_time( 'mysql' ),
            ), $fields );

            $formats = array();
            foreach ( $data as $k => $v ) {
                if ( in_array( $k, array('company_id','user_id','current_period_end'), true ) ) $formats[] = '%d';
                else $formats[] = '%s';
            }

            if ( $existing && ! empty($existing['id']) ) {
                $wpdb->update( $table, $data, array( 'id' => absint($existing['id']) ), $formats, array('%d') );
            } else {
                $data['created_at'] = current_time( 'mysql' );
                $wpdb->insert( $table, $data, $formats );
            }
        };

        // We prefer subscription.* events for authoritative status.
        if ( $type === 'customer.subscription.created' || $type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted' ) {
            $sub_id = (string) ($obj['id'] ?? '');
            $status = (string) ($obj['status'] ?? '');
            $customer = (string) ($obj['customer'] ?? '');
            $period_end = isset($obj['current_period_end']) ? absint($obj['current_period_end']) : 0;
            $meta = isset($obj['metadata']) && is_array($obj['metadata']) ? $obj['metadata'] : array();
            $company_id = isset($meta['company_id']) ? absint($meta['company_id']) : 0;
            $plan_id = isset($meta['plan_id']) ? (string) $meta['plan_id'] : '';

            if ( ! $company_id ) {
                // Try to locate by subscription id.
                global $wpdb;
                $table = ai_suite_subscriptions_table();
                $row = $wpdb->get_row( $wpdb->prepare("SELECT company_id FROM {$table} WHERE provider_subscription_id=%s ORDER BY id DESC LIMIT 1", $sub_id), ARRAY_A );
                if ( $row && ! empty($row['company_id']) ) $company_id = absint($row['company_id']);
            }

            if ( ! $plan_id ) {
                // Map from Stripe items->price if we can (best-effort). Keep existing if unknown.
                $plan_id = ai_suite_company_plan_id( $company_id );
            }

            if ( $type === 'customer.subscription.deleted' ) {
                $status = 'canceled';
            } elseif ( $status === 'trialing' || $status === 'active' ) {
                $status = 'active';
            } elseif ( $status === 'past_due' ) {
                $status = 'past_due';
            } elseif ( $status === 'incomplete' || $status === 'incomplete_expired' ) {
                $status = 'incomplete';
            }

            $upsert( $company_id, array(
                'plan_id' => $plan_id ?: 'free',
                'provider' => 'stripe',
                'provider_customer_id' => $customer,
                'provider_subscription_id' => $sub_id,
                'status' => $status ?: 'inactive',
                'current_period_end' => $period_end ?: null,
                'meta' => wp_json_encode( $obj ),
            ) );

            // Mirror plan meta for quick gating.
            if ( $company_id && $plan_id ) {
                update_post_meta( $company_id, '_ai_suite_plan', $plan_id );
                update_post_meta( $company_id, '_ai_suite_stripe_customer_id', $customer );
                update_post_meta( $company_id, '_ai_suite_stripe_subscription_id', $sub_id );
            }

            return;
        }

        if ( $type === 'checkout.session.completed' ) {
            // Session completed successfully. Link to company via metadata.
            $meta = isset($obj['metadata']) && is_array($obj['metadata']) ? $obj['metadata'] : array();
            $company_id = isset($meta['company_id']) ? absint($meta['company_id']) : 0;
            $plan_id = isset($meta['plan_id']) ? (string)$meta['plan_id'] : '';
            $customer = (string) ($obj['customer'] ?? '');
            $session_id = (string) ($obj['id'] ?? '');
            $sub_id = (string) ($obj['subscription'] ?? '');

            // Best-effort: fetch subscription for status/period_end.
            $status = 'active';
            $period_end = 0;
            if ( $sub_id ) {
                $sub = ai_suite_stripe_request( 'GET', '/v1/subscriptions/' . rawurlencode($sub_id), array() );
                if ( is_array($sub) && isset($sub['status']) ) {
                    $st = (string) $sub['status'];
                    if ( $st === 'trialing' || $st === 'active' ) $status = 'active';
                    else $status = $st;
                    $period_end = isset($sub['current_period_end']) ? absint($sub['current_period_end']) : 0;
                }
            }

            $upsert = function( $company_id, $fields ) {
                $company_id = absint($company_id);
                if ( ! $company_id ) return;
                global $wpdb;
                $table = ai_suite_subscriptions_table();
                $existing = $wpdb->get_row( $wpdb->prepare("SELECT id FROM {$table} WHERE company_id=%d ORDER BY id DESC LIMIT 1", $company_id), ARRAY_A );
                $data = array_merge( array(
                    'company_id' => $company_id,
                    'updated_at' => current_time('mysql'),
                ), $fields );
                $formats = array();
                foreach ( $data as $k => $v ) {
                    if ( in_array($k, array('company_id','user_id','current_period_end'), true ) ) $formats[] = '%d';
                    else $formats[] = '%s';
                }
                if ( $existing && ! empty($existing['id']) ) {
                    $wpdb->update( $table, $data, array('id'=>absint($existing['id'])), $formats, array('%d') );
                } else {
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert( $table, $data, $formats );
                }
            };

            $upsert( $company_id, array(
                'plan_id' => $plan_id ?: ai_suite_company_plan_id($company_id),
                'provider' => 'stripe',
                'provider_customer_id' => $customer,
                'provider_subscription_id' => $sub_id,
                'provider_session_id' => $session_id,
                'status' => $status ?: 'active',
                'current_period_end' => $period_end ?: null,
                'meta' => wp_json_encode( $obj ),
            ) );

            if ( $company_id && $plan_id ) {
                update_post_meta( $company_id, '_ai_suite_plan', $plan_id );
            }
            if ( $company_id && $customer ) {
                update_post_meta( $company_id, '_ai_suite_stripe_customer_id', $customer );
            }
            if ( $company_id && $sub_id ) {
                update_post_meta( $company_id, '_ai_suite_stripe_subscription_id', $sub_id );
            }
            return;
        }

        if ( $type === 'invoice.payment_failed' ) {
            $sub_id = (string) ($obj['subscription'] ?? '');
            if ( ! $sub_id ) return;
            global $wpdb;
            $table = ai_suite_subscriptions_table();
            $row = $wpdb->get_row( $wpdb->prepare("SELECT company_id FROM {$table} WHERE provider_subscription_id=%s ORDER BY id DESC LIMIT 1", $sub_id), ARRAY_A );
            if ( ! $row || empty($row['company_id']) ) return;
            $company_id = absint($row['company_id']);
            $wpdb->update( $table, array(
                'status' => 'past_due',
                'updated_at' => current_time('mysql'),
            ), array( 'company_id' => $company_id ), array('%s','%s'), array('%d') );
            return;
        }

        if ( $type === 'invoice.paid' ) {
            // Keep active.
            $sub_id = (string) ($obj['subscription'] ?? '');
            if ( ! $sub_id ) return;
            global $wpdb;
            $table = ai_suite_subscriptions_table();
            $row = $wpdb->get_row( $wpdb->prepare("SELECT company_id, plan_id FROM {$table} WHERE provider_subscription_id=%s ORDER BY id DESC LIMIT 1", $sub_id), ARRAY_A );
            if ( ! $row || empty($row['company_id']) ) return;
            $company_id = absint($row['company_id']);
            $wpdb->update( $table, array(
                'status' => 'active',
                'updated_at' => current_time('mysql'),
            ), array( 'company_id' => $company_id ), array('%s','%s'), array('%d') );
            update_post_meta( $company_id, '_ai_suite_plan', (string)($row['plan_id'] ?? 'pro') );
            return;
        }
    }
}

/**
 * AJAX – Billing data for portal + checkout session.
 */
if ( ! function_exists( 'ai_suite_billing_ajax_boot' ) ) {
    function ai_suite_billing_ajax_boot() {
        add_action( 'wp_ajax_ai_suite_billing_get', 'ai_suite_billing_ajax_get' );
        add_action( 'wp_ajax_ai_suite_billing_checkout', 'ai_suite_billing_ajax_checkout' );
        add_action( 'wp_ajax_ai_suite_billing_portal', 'ai_suite_billing_ajax_portal' );

        // Admin save actions (via AJAX for fast UX in tab).
        add_action( 'wp_ajax_ai_suite_billing_admin_save', 'ai_suite_billing_admin_save' );
        add_action( 'wp_ajax_ai_suite_billing_admin_save_plans', 'ai_suite_billing_admin_save_plans' );
    }
    add_action( 'init', 'ai_suite_billing_ajax_boot', 20 );
}

if ( ! function_exists( 'ai_suite_billing_ajax_require_company' ) ) {
    function ai_suite_billing_ajax_require_company() {
        if ( ! function_exists( 'check_ajax_referer' ) ) {
            return new WP_Error('ai_suite_ajax_no_nonce', 'no nonce');
        }
        check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

        $company_id = isset($_POST['company_id']) ? absint($_POST['company_id']) : 0;
        if ( ! $company_id ) return new WP_Error( 'ai_suite_no_company', __( 'Company invalid.', 'ai-suite' ) );

        // Admin preview or company user.
        $is_admin = function_exists('aisuite_current_user_is_admin') ? aisuite_current_user_is_admin() : current_user_can('manage_options');
        $is_company = function_exists('aisuite_current_user_is_company') ? aisuite_current_user_is_company() : false;
        if ( ! $is_admin && ! $is_company ) return new WP_Error( 'ai_suite_forbidden', __( 'Access denied.', 'ai-suite' ) );

        return $company_id;
    }
}

if ( ! function_exists( 'ai_suite_billing_ajax_get' ) ) {
    function ai_suite_billing_ajax_get() {
        $company_id = ai_suite_billing_ajax_require_company();
        if ( is_wp_error( $company_id ) ) wp_send_json_error( array('message'=>$company_id->get_error_message() ), 403 );

        $plans = ai_suite_billing_get_plans();
        $row = ai_suite_subscription_get_company( $company_id );
        $plan_id = ai_suite_company_plan_id( $company_id );

        $plan = function_exists( 'ai_suite_billing_get_plan' ) ? ai_suite_billing_get_plan( $plan_id ) : null;
        $features = ( is_array( $plan ) && isset( $plan['features'] ) && is_array( $plan['features'] ) ) ? $plan['features'] : array();
        $settings = ai_suite_billing_get_settings();

        wp_send_json_success( array(
            'company_id' => $company_id,
            'plan_id' => $plan_id,
            'is_active' => ai_suite_subscription_is_active($company_id),
            'subscription' => $row,
            'plans' => $plans,
            'provider_mode' => sanitize_key( (string) ($settings['mode'] ?? 'stripe') ),
            'expiry_grace_days' => (int) ( $settings['expiry_grace_days'] ?? 3 ),
            'expiry_notify_days' => (int) ( $settings['expiry_notify_days'] ?? 3 ),
            'features' => $features,
            'limits' => array(
                'active_jobs' => (int)($features['active_jobs'] ?? 0),
                'team_members' => (int)($features['team_members'] ?? 0),
                'exports' => (int)($features['exports'] ?? 0),
                'facebook_leads' => (int)($features['facebook_leads'] ?? 0),
                'ai_matching' => (int)($features['ai_matching'] ?? 0),
                'promo_credits_monthly' => (int)($features['promo_credits_monthly'] ?? 0),
                'copilot' => (int)($features['copilot'] ?? 0),
            ),
        ) );
    }
}

if ( ! function_exists( 'ai_suite_billing_ajax_checkout' ) ) {
    function ai_suite_billing_ajax_checkout() {
        $company_id = ai_suite_billing_ajax_require_company();
        if ( is_wp_error( $company_id ) ) wp_send_json_error( array('message'=>$company_id->get_error_message() ), 403 );

        $plan_id = isset($_POST['plan_id']) ? sanitize_key($_POST['plan_id']) : '';
        $requested_provider = isset($_POST['provider']) ? sanitize_key( (string) $_POST['provider'] ) : '';
        $plan = ai_suite_billing_get_plan( $plan_id );
        if ( ! $plan ) wp_send_json_error( array('message'=>__('Plan invalid.', 'ai-suite') ), 400 );

        $settings = ai_suite_billing_get_settings();
        $mode = sanitize_key( (string) ( $settings['mode'] ?? 'stripe' ) );

        // Decide provider for this checkout.
        $provider = 'stripe';
        if ( $mode === 'netopia' ) {
            $provider = 'netopia';
        } elseif ( $mode === 'both' ) {
            $provider = in_array( $requested_provider, array('stripe','netopia'), true ) ? $requested_provider : 'stripe';
        }

        // Free plan: activate locally without Stripe.
        if ( (int)($plan['price_monthly'] ?? 0) === 0 ) {
            update_post_meta( $company_id, '_ai_suite_plan', 'free' );
            wp_send_json_success( array( 'mode' => 'free', 'redirect' => '' ) );
        }

        // NETOPIA: create internal redirect URL to hosted payment page.
        if ( $provider === 'netopia' ) {
            if ( ! function_exists( 'ai_suite_netopia_prepare_checkout' ) ) {
                // Best-effort include (in case loader didn't).
                $p = trailingslashit( AI_SUITE_DIR ) . 'includes/billing/netopia.php';
                if ( file_exists( $p ) ) require_once $p;
            }
            if ( ! function_exists( 'ai_suite_netopia_prepare_checkout' ) ) {
                wp_send_json_error( array('message'=>__('NETOPIA module missing.', 'ai-suite') ), 500 );
            }

            $res = ai_suite_netopia_prepare_checkout( $company_id, $plan );
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array('message'=>$res->get_error_message() ), 400 );
            }
            wp_send_json_success( array(
                'mode' => 'netopia',
                'checkout_url' => (string) ($res['url'] ?? ''),
                'order_id' => (string) ($res['order_id'] ?? ''),
            ) );
        }

        // Stripe checkout (subscription mode).
        $price_id = (string) ($plan['stripe_price_id'] ?? '');
        if ( ! $price_id ) {
            wp_send_json_error( array('message'=>__('Stripe Price ID lipsește pentru acest plan. Setează-l în AI Suite → Billing.', 'ai-suite') ), 400 );
        }

        $customer_id = (string) get_post_meta( $company_id, '_ai_suite_stripe_customer_id', true );
        if ( ! $customer_id ) {
            $owner_email = '';
            $owner = get_userdata( get_current_user_id() );
            if ( $owner && ! empty($owner->user_email) ) $owner_email = (string) $owner->user_email;

            $company_title = get_the_title( $company_id );

            $cust = ai_suite_stripe_request( 'POST', '/v1/customers', array(
                'email' => $owner_email,
                'name'  => $company_title,
                'metadata[company_id]' => (string) $company_id,
            ) );
            if ( is_wp_error( $cust ) ) {
                wp_send_json_error( array('message'=>$cust->get_error_message() ), 400 );
            }
            $customer_id = (string) ($cust['id'] ?? '');
            if ( $customer_id ) update_post_meta( $company_id, '_ai_suite_stripe_customer_id', $customer_id );
        }

        // Create Checkout Session (subscription mode).
        $portal_url = (function_exists('aisuite_get_portal_url') ? aisuite_get_portal_url() : home_url('/portal/'));
        $success_url = add_query_arg( array('tab'=>'billing','billing'=>'success','session_id'=>'{CHECKOUT_SESSION_ID}'), $portal_url );
        $cancel_url  = add_query_arg( array('tab'=>'billing','billing'=>'cancel'), $portal_url );

        $params = array(
            'mode' => 'subscription',
            'customer' => $customer_id,
            'success_url' => $success_url,
            'cancel_url'  => $cancel_url,
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => 1,
            'metadata[company_id]' => (string) $company_id,
            'metadata[plan_id]'    => (string) $plan_id,
            'subscription_data[metadata][company_id]' => (string) $company_id,
            'subscription_data[metadata][plan_id]'    => (string) $plan_id,
        );

        $session = ai_suite_stripe_request( 'POST', '/v1/checkout/sessions', $params );
        if ( is_wp_error( $session ) ) {
            wp_send_json_error( array('message'=>$session->get_error_message() ), 400 );
        }

        $url = (string) ($session['url'] ?? '');
        if ( ! $url ) {
            wp_send_json_error( array('message'=>__('Stripe nu a returnat URL-ul sesiunii.', 'ai-suite') ), 400 );
        }

        wp_send_json_success( array(
            'mode' => 'stripe',
            'checkout_url' => $url,
            'session_id' => (string) ($session['id'] ?? ''),
        ) );
    }
}

if ( ! function_exists( 'ai_suite_billing_ajax_portal' ) ) {
    function ai_suite_billing_ajax_portal() {
        $company_id = ai_suite_billing_ajax_require_company();
        if ( is_wp_error( $company_id ) ) wp_send_json_error( array('message'=>$company_id->get_error_message() ), 403 );

        $settings = ai_suite_billing_get_settings();
        $mode = sanitize_key( (string) ( $settings['mode'] ?? 'stripe' ) );
        if ( $mode === 'netopia' ) {
            wp_send_json_error( array('message'=>__('Portalul de billing este disponibil doar pentru Stripe.', 'ai-suite') ), 400 );
        }

        $customer_id = (string) get_post_meta( $company_id, '_ai_suite_stripe_customer_id', true );
        if ( ! $customer_id ) {
            wp_send_json_error( array('message'=>__('Nu există customer Stripe pentru această companie.', 'ai-suite') ), 400 );
        }

        // Stripe Billing Portal session.
        $portal_url = (function_exists('aisuite_get_portal_url') ? aisuite_get_portal_url() : home_url('/portal/'));
        $return_url = add_query_arg( array('tab'=>'billing'), $portal_url );

        $sess = ai_suite_stripe_request( 'POST', '/v1/billing_portal/sessions', array(
            'customer' => $customer_id,
            'return_url' => $return_url,
        ) );
        if ( is_wp_error( $sess ) ) {
            wp_send_json_error( array('message'=>$sess->get_error_message() ), 400 );
        }

        $url = (string) ($sess['url'] ?? '');
        if ( ! $url ) wp_send_json_error( array('message'=>__('Stripe nu a returnat URL portal.', 'ai-suite') ), 400 );

        wp_send_json_success( array( 'url' => $url ) );
    }
}

/**
 * Admin – Save settings/plans (AJAX)
 */
if ( ! function_exists( 'ai_suite_billing_admin_save' ) ) {
    function ai_suite_billing_admin_save() {
        check_ajax_referer( 'ai_suite_nonce', 'nonce' );
        if ( ! current_user_can( function_exists('aisuite_capability') ? aisuite_capability() : 'manage_options' ) ) {
            wp_send_json_error( array('message'=>__('Access denied.', 'ai-suite') ), 403 );
        }

        $settings = ai_suite_billing_get_settings();

        // Provider mode: stripe | netopia | both
        if ( isset( $_POST['mode'] ) ) {
            $m = sanitize_key( (string) $_POST['mode'] );
            if ( in_array( $m, array('stripe','netopia','both'), true ) ) {
                $settings['mode'] = $m;
            }
        }

        // NETOPIA (mobilPay) settings
        if ( isset( $_POST['netopia_sandbox'] ) ) {
            $settings['netopia_sandbox'] = absint( $_POST['netopia_sandbox'] ) ? 1 : 0;
        }
        if ( isset( $_POST['netopia_signature'] ) ) {
            $settings['netopia_signature'] = sanitize_text_field( (string) $_POST['netopia_signature'] );
        }
        if ( isset( $_POST['netopia_public_cert_pem'] ) ) {
            $settings['netopia_public_cert_pem'] = trim( sanitize_textarea_field( (string) wp_unslash( $_POST['netopia_public_cert_pem'] ) ) );
        }
        if ( isset( $_POST['netopia_private_key_pem'] ) ) {
            $settings['netopia_private_key_pem'] = trim( sanitize_textarea_field( (string) wp_unslash( $_POST['netopia_private_key_pem'] ) ) );
        }

        // Optional overrides for NETOPIA hosted payment URL (advanced).
        // Defaults are set in ai_suite_billing_defaults().
        if ( isset( $_POST['netopia_live_url'] ) ) {
            $settings['netopia_live_url'] = esc_url_raw( trim( (string) $_POST['netopia_live_url'] ) );
        }
        if ( isset( $_POST['netopia_sandbox_url'] ) ) {
            $settings['netopia_sandbox_url'] = esc_url_raw( trim( (string) $_POST['netopia_sandbox_url'] ) );
        }


        $settings['stripe_publishable_key'] = isset($_POST['stripe_publishable_key']) ? sanitize_text_field($_POST['stripe_publishable_key']) : $settings['stripe_publishable_key'];
        $settings['stripe_secret_key']      = isset($_POST['stripe_secret_key']) ? sanitize_text_field($_POST['stripe_secret_key']) : $settings['stripe_secret_key'];
        $settings['stripe_webhook_secret']  = isset($_POST['stripe_webhook_secret']) ? sanitize_text_field($_POST['stripe_webhook_secret']) : $settings['stripe_webhook_secret'];

        // Trial settings
        if ( isset($_POST['trial_enabled']) ) {
            $settings['trial_enabled'] = absint( $_POST['trial_enabled'] ) ? 1 : 0;
        }
        if ( isset($_POST['trial_days']) ) {
            $settings['trial_days'] = max( 0, absint( $_POST['trial_days'] ) );
        }
        if ( isset($_POST['trial_plan_id']) ) {
            $settings['trial_plan_id'] = sanitize_key( (string) $_POST['trial_plan_id'] );
        }
        if ( isset($_POST['trial_once_per_company']) ) {
            $settings['trial_once_per_company'] = absint( $_POST['trial_once_per_company'] ) ? 1 : 0;
        }
        if ( isset($_POST['trial_grace_days']) ) {
            $settings['trial_grace_days'] = max( 0, absint( $_POST['trial_grace_days'] ) );
        }

        // Expiry automation
        if ( isset($_POST['expiry_grace_days']) ) {
            $settings['expiry_grace_days'] = max( 0, absint( $_POST['expiry_grace_days'] ) );
        }
        if ( isset($_POST['expiry_notify_days']) ) {
            $settings['expiry_notify_days'] = max( 0, absint( $_POST['expiry_notify_days'] ) );
        }
        if ( isset($_POST['expiry_sender_email']) ) {
            $settings['expiry_sender_email'] = sanitize_email( (string) $_POST['expiry_sender_email'] );
        }
        if ( isset($_POST['expiry_sender_name']) ) {
            $settings['expiry_sender_name'] = sanitize_text_field( (string) $_POST['expiry_sender_name'] );
        }

        // Invoice / HTML billing settings
        if ( isset($_POST['invoice_series_template']) ) {
            $tpl = (string) wp_unslash( $_POST['invoice_series_template'] );
            $tpl = trim( preg_replace( '/[^A-Za-z0-9\-\_\{\}\+\s]/', '', $tpl ) );
            if ( $tpl === '' ) { $tpl = 'RMX-{Y}-'; }
            $settings['invoice_series_template'] = $tpl;
        }
        if ( isset($_POST['invoice_number_padding']) ) {
            $settings['invoice_number_padding'] = max( 2, min( 8, absint( $_POST['invoice_number_padding'] ) ) );
        }
        if ( isset($_POST['invoice_issuer_name']) ) {
            $settings['invoice_issuer_name'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_name'] ) );
        }
        if ( isset($_POST['invoice_issuer_cui']) ) {
            $settings['invoice_issuer_cui'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_cui'] ) );
        }
        if ( isset($_POST['invoice_issuer_reg']) ) {
            $settings['invoice_issuer_reg'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_reg'] ) );
        }
        if ( isset($_POST['invoice_issuer_address']) ) {
            $settings['invoice_issuer_address'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_address'] ) );
        }
        if ( isset($_POST['invoice_issuer_city']) ) {
            $settings['invoice_issuer_city'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_city'] ) );
        }
        if ( isset($_POST['invoice_issuer_country']) ) {
            $c = strtoupper( preg_replace('/[^A-Z]/', '', (string) wp_unslash( $_POST['invoice_issuer_country'] ) ) );
            $settings['invoice_issuer_country'] = (strlen($c) >= 2 && strlen($c) <= 3) ? $c : 'RO';
        }
        if ( isset($_POST['invoice_issuer_iban']) ) {
            $settings['invoice_issuer_iban'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_iban'] ) );
        }
        if ( isset($_POST['invoice_issuer_bank']) ) {
            $settings['invoice_issuer_bank'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_bank'] ) );
        }
        if ( isset($_POST['invoice_issuer_vat']) ) {
            $settings['invoice_issuer_vat'] = absint( $_POST['invoice_issuer_vat'] ) ? 1 : 0;
        }
        if ( isset($_POST['invoice_issuer_email']) ) {
            $settings['invoice_issuer_email'] = sanitize_email( (string) wp_unslash( $_POST['invoice_issuer_email'] ) );
        }
        if ( isset($_POST['invoice_issuer_phone']) ) {
            $settings['invoice_issuer_phone'] = sanitize_text_field( (string) wp_unslash( $_POST['invoice_issuer_phone'] ) );
        }
        if ( isset($_POST['invoice_issuer_website']) ) {
            $settings['invoice_issuer_website'] = esc_url_raw( (string) wp_unslash( $_POST['invoice_issuer_website'] ) );
        }
        if ( isset($_POST['invoice_issuer_logo_url']) ) {
            $settings['invoice_issuer_logo_url'] = esc_url_raw( (string) wp_unslash( $_POST['invoice_issuer_logo_url'] ) );
        }
        if ( isset($_POST['invoice_footer_note']) ) {
            $settings['invoice_footer_note'] = sanitize_textarea_field( (string) wp_unslash( $_POST['invoice_footer_note'] ) );
        }

        update_option( AI_SUITE_OPTION_BILLING, $settings, false );

        $def = isset($_POST['default_plan']) ? sanitize_key($_POST['default_plan']) : '';
        if ( $def ) update_option( AI_SUITE_OPTION_BILLING_DEFAULT_PLAN, $def, false );

        wp_send_json_success( array('saved'=>true) );
    }
}

if ( ! function_exists( 'ai_suite_billing_admin_save_plans' ) ) {
    function ai_suite_billing_admin_save_plans() {
        check_ajax_referer( 'ai_suite_nonce', 'nonce' );
        if ( ! current_user_can( function_exists('aisuite_capability') ? aisuite_capability() : 'manage_options' ) ) {
            wp_send_json_error( array('message'=>__('Access denied.', 'ai-suite') ), 403 );
        }

        $raw = isset($_POST['plans_json']) ? wp_unslash($_POST['plans_json']) : '';
        $arr = json_decode( (string)$raw, true );
        if ( ! is_array($arr) ) {
            wp_send_json_error( array('message'=>__('JSON invalid pentru plans.', 'ai-suite') ), 400 );
        }

        // Sanitize minimal.
        $clean = array();
        foreach ( $arr as $p ) {
            if ( ! is_array($p) ) continue;
            $id = isset($p['id']) ? sanitize_key($p['id']) : '';
            if ( ! $id ) continue;
            $clean[] = array(
                'id' => $id,
                'name' => isset($p['name']) ? sanitize_text_field($p['name']) : $id,
                'price_monthly' => isset($p['price_monthly']) ? floatval($p['price_monthly']) : 0,
                'currency' => isset($p['currency']) ? strtoupper(sanitize_text_field($p['currency'])) : 'EUR',
                'stripe_price_id' => isset($p['stripe_price_id']) ? sanitize_text_field($p['stripe_price_id']) : '',
                'features' => isset($p['features']) && is_array($p['features']) ? array_map('intval', $p['features']) : array(),
            );
        }

        update_option( AI_SUITE_OPTION_BILLING_PLANS, $clean, false );
        wp_send_json_success( array('saved'=>true, 'count'=>count($clean)) );
    }
}

/**
 * Admin tab + Portal tab injection.
 */
if ( ! function_exists( 'ai_suite_billing_register_admin_tab' ) ) {
    function ai_suite_billing_register_admin_tab( $tabs ) {
        if ( ! is_array($tabs) ) $tabs = array();
        $tabs['billing'] = array(
            'label' => __( 'Billing', 'ai-suite' ),
            'view'  => 'tab-billing.php',
        );
        return $tabs;
    }
    add_filter( 'ai_suite_tabs', 'ai_suite_billing_register_admin_tab', 50 );
}

if ( ! function_exists( 'ai_suite_billing_register_company_portal_tab' ) ) {
    function ai_suite_billing_register_company_portal_tab( $tabs, $company_id ) {
        if ( ! is_array($tabs) ) $tabs = array();
        $tabs['billing'] = __( 'Abonament', 'ai-suite' );
        return $tabs;
    }
    add_filter( 'ai_suite_company_portal_tabs', 'ai_suite_billing_register_company_portal_tab', 50, 2 );
}

// Ensure table exists early for admin pages.
add_action( 'admin_init', function() {
    if ( current_user_can( function_exists('aisuite_capability') ? aisuite_capability() : 'manage_options' ) ) {
        ai_suite_subscription_ensure_table();
    }
}, 5 );


// -------------------------
// Billing migration helpers (ADD-ONLY)
// -------------------------
if ( ! function_exists( 'ai_suite_billing_migrate_plans_features' ) ) {
    function ai_suite_billing_migrate_plans_features() {
        // Only admins, and only in wp-admin for performance.
        if ( function_exists( 'is_admin' ) && ! is_admin() ) { return; }
        $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
        if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
            return;
        }

        $plans = ai_suite_billing_get_plans();
        $changed = false;

        foreach ( $plans as $k => $plan ) {
            if ( empty( $plan['features'] ) || ! is_array( $plan['features'] ) ) {
                $plan['features'] = array();
            }
            $pid = isset( $plan['id'] ) ? (string) $plan['id'] : (string) $k;
            // ADD-ONLY defaults (do not override existing values).
            if ( ! array_key_exists( 'promo_credits_monthly', $plan['features'] ) ) {
                $plan['features']['promo_credits_monthly'] = ( 'enterprise' === $pid ) ? 10 : ( ('pro' === $pid) ? 3 : 0 );
                $changed = true;
            }
            if ( ! array_key_exists( 'copilot', $plan['features'] ) ) {
                $plan['features']['copilot'] = ( 'free' !== $pid ) ? 1 : 0;
                $changed = true;
            }
            if ( ! array_key_exists( 'ats', $plan['features'] ) ) {
                // ATS board actions (pipeline/shortlist) are premium from Pro.
                $plan['features']['ats'] = ( 'free' !== $pid ) ? 1 : 0;
                $changed = true;
            }
            $plans[ $k ] = $plan;
        }

        if ( $changed ) {
            update_option( 'ai_suite_billing_plans', $plans, false );
        }
    }
}
add_action( 'admin_init', 'ai_suite_billing_migrate_plans_features', 3 );


// --------------------
// Billing automation helpers (expiry/grace/notifications) – Patch45
// --------------------
if ( ! function_exists( 'ai_suite_subscription_update_latest' ) ) {
    function ai_suite_subscription_update_latest( $company_id, $fields, $merge_meta = true ) {
        $company_id = absint( $company_id );
        if ( ! $company_id || ! is_array( $fields ) || empty( $fields ) ) return false;

        ai_suite_subscription_ensure_table();
        global $wpdb;
        $table = ai_suite_subscriptions_table();

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE company_id=%d ORDER BY id DESC LIMIT 1",
            $company_id
        ), ARRAY_A );

        if ( ! $row || empty( $row['id'] ) ) return false;

        $data = array();
        $formats = array();

        foreach ( $fields as $k => $v ) {
            if ( $k === 'meta' && $merge_meta ) continue;
            $data[ $k ] = $v;
            $formats[] = is_int( $v ) ? '%d' : '%s';
        }

        // Merge meta as JSON (best-effort).
        if ( isset( $fields['meta'] ) && $merge_meta ) {
            $old = array();
            if ( ! empty( $row['meta'] ) ) {
                $dec = json_decode( (string) $row['meta'], true );
                if ( is_array( $dec ) ) $old = $dec;
            }
            $newm = is_array( $fields['meta'] ) ? $fields['meta'] : array();
            $merged = array_merge( $old, $newm );
            $data['meta'] = wp_json_encode( $merged );
            $formats[] = '%s';
        } elseif ( array_key_exists( 'meta', $fields ) ) {
            $data['meta'] = is_array( $fields['meta'] ) ? wp_json_encode( $fields['meta'] ) : (string) $fields['meta'];
            $formats[] = '%s';
        }

        $data['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $ok = $wpdb->update( $table, $data, array( 'id' => (int) $row['id'] ), $formats, array( '%d' ) );
        return ( $ok !== false );
    }
}

if ( ! function_exists( 'ai_suite_billing_get_company_owner_emails' ) ) {
    function ai_suite_billing_get_company_owner_emails( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return array();

        $emails = array();

        // Prefer company members table owners.
        if ( function_exists( 'ai_suite_company_members_get' ) ) {
            $members = ai_suite_company_members_get( $company_id );
            if ( is_array( $members ) ) {
                foreach ( $members as $m ) {
                    if ( ! is_array( $m ) ) continue;
                    if ( (string) ( $m['status'] ?? '' ) !== 'active' ) continue;
                    if ( (string) ( $m['member_role'] ?? '' ) !== 'owner' ) continue;
                    $uid = absint( $m['user_id'] ?? 0 );
                    if ( $uid ) {
                        $u = get_userdata( $uid );
                        if ( $u && ! empty( $u->user_email ) && is_email( $u->user_email ) ) {
                            $emails[] = strtolower( (string) $u->user_email );
                        }
                    }
                }
            }
        }

        // Fallback: subscription row user_id.
        if ( empty( $emails ) ) {
            $row = function_exists( 'ai_suite_subscription_get_company' ) ? ai_suite_subscription_get_company( $company_id ) : null;
            $uid = $row ? absint( $row['user_id'] ?? 0 ) : 0;
            if ( $uid ) {
                $u = get_userdata( $uid );
                if ( $u && ! empty( $u->user_email ) && is_email( $u->user_email ) ) {
                    $emails[] = strtolower( (string) $u->user_email );
                }
            }
        }

        // Fallback: post author.
        if ( empty( $emails ) ) {
            $post = get_post( $company_id );
            if ( $post && ! empty( $post->post_author ) ) {
                $u = get_userdata( (int) $post->post_author );
                if ( $u && ! empty( $u->user_email ) && is_email( $u->user_email ) ) {
                    $emails[] = strtolower( (string) $u->user_email );
                }
            }
        }

        $emails = array_values( array_unique( array_filter( $emails ) ) );
        return $emails;
    }
}

if ( ! function_exists( 'ai_suite_billing_send_email' ) ) {
    function ai_suite_billing_send_email( $company_id, $subject, $message ) {
        $company_id = absint( $company_id );
        $emails = ai_suite_billing_get_company_owner_emails( $company_id );
        if ( empty( $emails ) ) return false;

        $settings = function_exists( 'ai_suite_billing_get_settings' ) ? ai_suite_billing_get_settings() : array();
        $from_email = isset( $settings['expiry_sender_email'] ) ? sanitize_email( (string) $settings['expiry_sender_email'] ) : '';
        $from_name  = isset( $settings['expiry_sender_name'] ) ? sanitize_text_field( (string) $settings['expiry_sender_name'] ) : '';

        $headers = array();
        if ( $from_email && is_email( $from_email ) ) {
            $name = $from_name ? $from_name : get_bloginfo( 'name' );
            $headers[] = 'From: ' . $name . ' <' . $from_email . '>';
        }

        // Simple HTML email.
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        $subject = (string) $subject;
        $message = (string) $message;

        $ok = wp_mail( $emails, $subject, $message, $headers );
        return (bool) $ok;
    }
}

if ( ! function_exists( 'ai_suite_billing_notice_marked' ) ) {
    function ai_suite_billing_notice_marked( $company_id, $key ) {
        $company_id = absint( $company_id );
        $key = sanitize_key( (string) $key );
        if ( ! $company_id || ! $key ) return true;
        return (bool) get_post_meta( $company_id, '_ai_suite_billing_notice_' . $key, true );
    }
}

if ( ! function_exists( 'ai_suite_billing_notice_mark' ) ) {
    function ai_suite_billing_notice_mark( $company_id, $key ) {
        $company_id = absint( $company_id );
        $key = sanitize_key( (string) $key );
        if ( ! $company_id || ! $key ) return false;
        update_post_meta( $company_id, '_ai_suite_billing_notice_' . $key, (string) time() );
        return true;
    }
}

