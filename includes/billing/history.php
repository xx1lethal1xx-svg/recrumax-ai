<?php
/**
 * Billing History + Invoices (HTML) for AI Suite.
 *
 * - Stores immutable billing events (checkout, paid, failed, confirm).
 * - Stores invoices (Stripe invoice.id, NETOPIA order_id, etc.).
 * - Provides Portal + Admin listing and HTML invoice rendering.
 *
 * NOTE: Invoices are HTML print-friendly (browser: Print → Save as PDF).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_billing_history_tables' ) ) {
    function ai_suite_billing_history_tables() {
        global $wpdb;
        return array(
            'events'   => $wpdb->prefix . 'ai_suite_billing_events',
            'invoices' => $wpdb->prefix . 'ai_suite_billing_invoices',
        );
    }
}

// -------------------------
// Invoice settings helpers (issuer + numbering)
// -------------------------
if ( ! function_exists( 'ai_suite_billing_invoice_get_settings' ) ) {
    function ai_suite_billing_invoice_get_settings() {
        // Prefer billing settings if available.
        if ( function_exists( 'ai_suite_billing_get_settings' ) ) {
            $s = ai_suite_billing_get_settings();
            if ( is_array( $s ) ) return $s;
        }
        // Fallback (safe defaults).
        return array(
            'invoice_series_template' => 'RMX-{Y}-',
            'invoice_number_padding'  => 4,
            'invoice_issuer_name'     => 'RecruMax',
            'invoice_issuer_cui'      => '',
            'invoice_issuer_reg'      => '',
            'invoice_issuer_address'  => '',
            'invoice_issuer_city'     => '',
            'invoice_issuer_country'  => 'RO',
            'invoice_issuer_iban'     => '',
            'invoice_issuer_bank'     => '',
            'invoice_issuer_vat'      => 0,
            'invoice_issuer_email'    => '',
            'invoice_issuer_phone'    => '',
            'invoice_issuer_website'  => home_url('/'),
            'invoice_issuer_logo_url' => '',
            'invoice_footer_note'     => 'Document generat automat de AI Suite. Print → Save as PDF.',
        );
    }
}

if ( ! function_exists( 'ai_suite_billing_invoice_build_prefix' ) ) {
    function ai_suite_billing_invoice_build_prefix( $template ) {
        $template = (string) $template;
        if ( $template === '' ) { $template = 'RMX-{Y}-'; }

        $Y = (string) gmdate('Y');
        $y = (string) gmdate('y');
        $m = (string) gmdate('m');
        $d = (string) gmdate('d');

        $out = str_replace(
            array('{Y}','{y}','{m}','{d}'),
            array($Y, $y, $m, $d),
            $template
        );
        $out = trim( $out );
        return $out;
    }
}

if ( ! function_exists( 'ai_suite_billing_history_install' ) ) {
    function ai_suite_billing_history_install() {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $t = ai_suite_billing_history_tables();

        $sql_events = "CREATE TABLE {$t['events']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            company_id BIGINT(20) UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT '',
            event_type VARCHAR(64) NOT NULL DEFAULT '',
            status VARCHAR(24) NOT NULL DEFAULT '',
            amount_cents INT(11) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            provider_ref VARCHAR(128) NOT NULL DEFAULT '',
            invoice_id BIGINT(20) UNSIGNED NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY company_id (company_id),
            KEY provider (provider),
            KEY event_type (event_type),
            KEY status (status),
            KEY created_at (created_at),
            KEY provider_ref (provider_ref)
        ) $charset;";

        $sql_invoices = "CREATE TABLE {$t['invoices']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            company_id BIGINT(20) UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT '',
            invoice_number VARCHAR(64) NOT NULL DEFAULT '',
            provider_invoice_id VARCHAR(128) NOT NULL DEFAULT '',
            status VARCHAR(24) NOT NULL DEFAULT '',
            amount_cents INT(11) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            period_start INT(11) NOT NULL DEFAULT 0,
            period_end INT(11) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY provider_invoice (provider, provider_invoice_id),
            KEY company_id (company_id),
            KEY provider (provider),
            KEY status (status),
            KEY created_at (created_at),
            KEY invoice_number (invoice_number)
        ) $charset;";

        dbDelta( $sql_events );
        dbDelta( $sql_invoices );
    }
}

if ( ! function_exists( 'ai_suite_billing_invoice_next_number' ) ) {
    function ai_suite_billing_invoice_next_number() {
        global $wpdb;
        $t = ai_suite_billing_history_tables();

        $settings = ai_suite_billing_invoice_get_settings();
        $prefix = ai_suite_billing_invoice_build_prefix( (string) ( $settings['invoice_series_template'] ?? 'RMX-{Y}-' ) );
        $pad = max( 2, min( 8, absint( $settings['invoice_number_padding'] ?? 4 ) ) );

        // Find max for current prefix.
        $like = $wpdb->esc_like( $prefix ) . '%';
        $max = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(invoice_number) FROM {$t['invoices']} WHERE invoice_number LIKE %s",
            $like
        ) );

        $n = 0;
        if ( $max && strpos( $max, $prefix ) === 0 ) {
            if ( preg_match( '/(\d+)\s*$/', (string) $max, $m ) ) {
                $n = absint( $m[1] );
            }
        }
        $n++;
        return $prefix . str_pad( (string) $n, $pad, '0', STR_PAD_LEFT );
    }
}

if ( ! function_exists( 'ai_suite_billing_invoice_upsert' ) ) {
    /**
     * Upsert invoice by (provider, provider_invoice_id).
     *
     * @param array $a
     *  - company_id (int)
     *  - provider (string) stripe|netopia|manual
     *  - provider_invoice_id (string) invoice.id / order_id
     *  - status (string) paid|failed|open|pending
     *  - amount_cents (int)
     *  - currency (string)
     *  - period_start (int unix) optional
     *  - period_end (int unix) optional
     *  - meta (array) optional
     * @return int invoice_id
     */
    function ai_suite_billing_invoice_upsert( $a ) {
        global $wpdb;
        $t = ai_suite_billing_history_tables();

        $company_id = absint( $a['company_id'] ?? 0 );
        $provider = sanitize_key( (string) ( $a['provider'] ?? '' ) );
        $provider_invoice_id = sanitize_text_field( (string) ( $a['provider_invoice_id'] ?? '' ) );
        if ( ! $company_id || ! $provider || ! $provider_invoice_id ) {
            return 0;
        }

        $status = sanitize_key( (string) ( $a['status'] ?? '' ) );
        $amount_cents = (int) ( $a['amount_cents'] ?? 0 );
        $currency = strtoupper( preg_replace('/[^A-Z]/', '', (string) ( $a['currency'] ?? 'EUR' ) ) );
        if ( strlen( $currency ) !== 3 ) $currency = 'EUR';

        $period_start = absint( $a['period_start'] ?? 0 );
        $period_end = absint( $a['period_end'] ?? 0 );
        $meta = $a['meta'] ?? array();
        if ( ! is_array( $meta ) ) $meta = array();

        $now = current_time( 'mysql' );

        $existing_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$t['invoices']} WHERE provider=%s AND provider_invoice_id=%s LIMIT 1",
            $provider, $provider_invoice_id
        ) );

        $data = array(
            'updated_at' => $now,
            'company_id' => $company_id,
            'provider' => $provider,
            'provider_invoice_id' => $provider_invoice_id,
            'status' => $status,
            'amount_cents' => $amount_cents,
            'currency' => $currency,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'meta' => wp_json_encode( $meta ),
        );

        if ( $existing_id ) {
            $wpdb->update( $t['invoices'], $data, array( 'id' => $existing_id ) );
            return $existing_id;
        }

        $data['created_at'] = $now;
        $data['invoice_number'] = ai_suite_billing_invoice_next_number();

        $wpdb->insert( $t['invoices'], $data );
        return (int) $wpdb->insert_id;
    }
}

if ( ! function_exists( 'ai_suite_billing_event_add' ) ) {
    /**
     * Add immutable billing event.
     *
     * @param array $a
     * @return int event_id
     */
    function ai_suite_billing_event_add( $a ) {
        global $wpdb;
        $t = ai_suite_billing_history_tables();

        $company_id = absint( $a['company_id'] ?? 0 );
        if ( ! $company_id ) return 0;

        $provider = sanitize_key( (string) ( $a['provider'] ?? '' ) );
        $event_type = sanitize_key( (string) ( $a['event_type'] ?? '' ) );
        $status = sanitize_key( (string) ( $a['status'] ?? '' ) );

        $amount_cents = (int) ( $a['amount_cents'] ?? 0 );
        $currency = strtoupper( preg_replace('/[^A-Z]/', '', (string) ( $a['currency'] ?? 'EUR' ) ) );
        if ( strlen( $currency ) !== 3 ) $currency = 'EUR';

        $provider_ref = sanitize_text_field( (string) ( $a['provider_ref'] ?? '' ) );
        $invoice_id = absint( $a['invoice_id'] ?? 0 );
        $meta = $a['meta'] ?? array();
        if ( ! is_array( $meta ) ) $meta = array();

        $wpdb->insert( $t['events'], array(
            'created_at' => current_time('mysql'),
            'company_id' => $company_id,
            'provider' => $provider,
            'event_type' => $event_type,
            'status' => $status,
            'amount_cents' => $amount_cents,
            'currency' => $currency,
            'provider_ref' => $provider_ref,
            'invoice_id' => $invoice_id ? $invoice_id : null,
            'meta' => wp_json_encode( $meta ),
        ) );

        return (int) $wpdb->insert_id;
    }
}

if ( ! function_exists( 'ai_suite_billing_history_get_company' ) ) {
    function ai_suite_billing_history_get_company( $company_id, $limit_events = 30, $limit_invoices = 30 ) {
        global $wpdb;
        $t = ai_suite_billing_history_tables();
        $company_id = absint( $company_id );
        $limit_events = max( 1, min( 200, absint( $limit_events ) ) );
        $limit_invoices = max( 1, min( 200, absint( $limit_invoices ) ) );

        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t['events']} WHERE company_id=%d ORDER BY id DESC LIMIT %d",
            $company_id, $limit_events
        ), ARRAY_A );

        $invoices = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t['invoices']} WHERE company_id=%d ORDER BY id DESC LIMIT %d",
            $company_id, $limit_invoices
        ), ARRAY_A );

        return array( 'events' => $events, 'invoices' => $invoices );
    }
}

if ( ! function_exists( 'ai_suite_billing_history_admin_query' ) ) {
    function ai_suite_billing_history_admin_query( $args ) {
        global $wpdb;
        $t = ai_suite_billing_history_tables();

        $company_id = absint( $args['company_id'] ?? 0 );
        $provider = sanitize_key( (string) ( $args['provider'] ?? '' ) );
        $status = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $limit = max( 1, min( 500, absint( $args['limit'] ?? 100 ) ) );

        $where = "1=1";
        $params = array();

        if ( $company_id ) { $where .= " AND company_id=%d"; $params[] = $company_id; }
        if ( $provider ) { $where .= " AND provider=%s"; $params[] = $provider; }
        if ( $status ) { $where .= " AND status=%s"; $params[] = $status; }

        $sql_e = "SELECT * FROM {$t['events']} WHERE $where ORDER BY id DESC LIMIT $limit";
        $sql_i = "SELECT * FROM {$t['invoices']} WHERE $where ORDER BY id DESC LIMIT $limit";

        if ( $params ) {
            $events = $wpdb->get_results( $wpdb->prepare( $sql_e, $params ), ARRAY_A );
            $invoices = $wpdb->get_results( $wpdb->prepare( $sql_i, $params ), ARRAY_A );
        } else {
            $events = $wpdb->get_results( $sql_e, ARRAY_A );
            $invoices = $wpdb->get_results( $sql_i, ARRAY_A );
        }
        return array( 'events' => $events, 'invoices' => $invoices );
    }
}

if ( ! function_exists( 'ai_suite_billing_invoice_html' ) ) {
    function ai_suite_billing_invoice_html( $invoice ) {
        $settings = ai_suite_billing_invoice_get_settings();

        $company_id = absint( $invoice['company_id'] ?? 0 );
        $company_title = $company_id ? get_the_title( $company_id ) : '';
        $buyer_email = $company_id ? (string) get_post_meta( $company_id, '_company_billing_email', true ) : '';
        if ( $buyer_email === '' && $company_id ) { $buyer_email = (string) get_post_meta( $company_id, '_company_contact_email', true ); }
        $buyer_email = sanitize_email( $buyer_email );
        $buyer_cui = $company_id ? (string) get_post_meta( $company_id, '_company_billing_cui', true ) : '';
        if ( $buyer_cui === '' && $company_id ) {
            $buyer_cui = (string) get_post_meta( $company_id, '_company_cui', true );
            if ( $buyer_cui === '' ) { $buyer_cui = (string) get_post_meta( $company_id, '_company_tax_id', true ); }
        }
        $buyer_addr = $company_id ? (string) get_post_meta( $company_id, '_company_billing_address', true ) : '';
        $buyer_name = $company_id ? (string) get_post_meta( $company_id, '_company_billing_name', true ) : '';
        if ( $buyer_name === '' ) { $buyer_name = $company_title; }
        $buyer_reg = $company_id ? (string) get_post_meta( $company_id, '_company_billing_reg', true ) : '';
        $buyer_city = $company_id ? (string) get_post_meta( $company_id, '_company_billing_city', true ) : '';
        $buyer_country = $company_id ? (string) get_post_meta( $company_id, '_company_billing_country', true ) : '';
        $buyer_phone = $company_id ? (string) get_post_meta( $company_id, '_company_billing_phone', true ) : '';
        $buyer_contact = $company_id ? (string) get_post_meta( $company_id, '_company_billing_contact', true ) : '';
        $buyer_vat = $company_id ? (int) get_post_meta( $company_id, '_company_billing_vat', true ) : 0;

        $buyer_address_line = trim( $buyer_addr );
        if ( $buyer_city ) { $buyer_address_line = trim( $buyer_address_line . ', ' . $buyer_city ); }
        if ( $buyer_country ) { $buyer_address_line = trim( $buyer_address_line . ', ' . $buyer_country ); }
        $buyer_address_line = trim( $buyer_address_line, ' ,' );

        if ( $buyer_address_line === '' && $company_id ) { $buyer_address_line = (string) get_post_meta( $company_id, '_company_address', true ); }
        $inv_no = (string) ( $invoice['invoice_number'] ?? '' );
        $provider = (string) ( $invoice['provider'] ?? '' );
        $prov_id = (string) ( $invoice['provider_invoice_id'] ?? '' );
        $status = (string) ( $invoice['status'] ?? '' );
        $amount = ((int)($invoice['amount_cents'] ?? 0))/100.0;
        $currency = (string) ( $invoice['currency'] ?? 'EUR' );
        $created = (string) ( $invoice['created_at'] ?? '' );
        $pstart = absint( $invoice['period_start'] ?? 0 );
        $pend = absint( $invoice['period_end'] ?? 0 );

        $meta = array();
        if ( ! empty( $invoice['meta'] ) ) {
            $m = json_decode( (string) $invoice['meta'], true );
            if ( is_array( $m ) ) $meta = $m;
        }
        $plan_id = (string) ( $meta['plan_id'] ?? '' );

        // Issuer details (configured in Billing tab)
        $issuer_name = (string) ( $settings['invoice_issuer_name'] ?? 'RecruMax' );
        $issuer_cui = (string) ( $settings['invoice_issuer_cui'] ?? '' );
        $issuer_reg = (string) ( $settings['invoice_issuer_reg'] ?? '' );
        $issuer_address = (string) ( $settings['invoice_issuer_address'] ?? '' );
        $issuer_city = (string) ( $settings['invoice_issuer_city'] ?? '' );
        $issuer_country = (string) ( $settings['invoice_issuer_country'] ?? 'RO' );
        $issuer_iban = (string) ( $settings['invoice_issuer_iban'] ?? '' );
        $issuer_bank = (string) ( $settings['invoice_issuer_bank'] ?? '' );
        $issuer_vat = ! empty( $settings['invoice_issuer_vat'] ) ? 1 : 0;
        $issuer_email = (string) ( $settings['invoice_issuer_email'] ?? '' );
        $issuer_phone = (string) ( $settings['invoice_issuer_phone'] ?? '' );
        $issuer_website = (string) ( $settings['invoice_issuer_website'] ?? home_url('/') );
        $issuer_logo = (string) ( $settings['invoice_issuer_logo_url'] ?? '' );
        $footer_note = (string) ( $settings['invoice_footer_note'] ?? '' );
        $period_txt = '';
        if ( $pstart && $pend ) {
            $period_txt = gmdate('Y-m-d', $pstart) . ' → ' . gmdate('Y-m-d', $pend);
        } elseif ( $pstart || $pend ) {
            $period_txt = ($pstart ? gmdate('Y-m-d', $pstart) : '—') . ' → ' . ($pend ? gmdate('Y-m-d', $pend) : '—');
        }

        ob_start();
        ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $inv_no ? ('Factura ' . $inv_no) : 'Factura' ); ?></title>
<style>
    :root{--bg:#0b0f19;--card:#0f172a;--muted:#93a4c7;--txt:#e8eeff;--line:rgba(255,255,255,.09);--acc:#3b82f6;}
    body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; padding:24px}
    .wrap{max-width:900px;margin:0 auto}
    .card{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02)); border:1px solid var(--line); border-radius:16px; padding:18px}
    .top{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .top-left{display:flex;gap:12px;align-items:center}
    .logo{width:44px;height:44px;object-fit:contain;border-radius:10px;background:rgba(255,255,255,.06);border:1px solid var(--line)}
    h1{margin:0;font-size:20px}
    .muted{color:var(--muted)}
    .btn{display:inline-flex;align-items:center;gap:8px;background:var(--acc);color:white;padding:10px 14px;border-radius:12px;text-decoration:none;font-weight:700}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
    .cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
    .box{border:1px solid var(--line);border-radius:12px;padding:12px}
    .row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed var(--line)}
    .row:last-child{border-bottom:none}
    @media print{ body{background:white;color:black} .card{background:white;border:1px solid #ddd} .btn{display:none} .muted{color:#444} .logo{background:white;border:1px solid #ddd} }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top">
      <div class="top-left">
        <?php if ( ! empty( $issuer_logo ) ) : ?>
          <img class="logo" src="<?php echo esc_url( $issuer_logo ); ?>" alt="Logo" />
        <?php endif; ?>
        <div>
          <h1>Factura <?php echo esc_html( $inv_no ?: '—' ); ?></h1>
          <div class="muted"><?php echo esc_html( $issuer_name ?: 'Emitent' ); ?></div>
        </div>
      </div>
      <div>
        <a class="btn" href="#" onclick="window.print();return false;">Print / Save as PDF</a>
      </div>
    </div>

    <div class="cols">
      <div class="box">
        <div class="muted" style="font-weight:700;margin-bottom:6px">Emitent</div>
        <div style="line-height:1.55">
          <div><strong><?php echo esc_html( $issuer_name ?: '—' ); ?></strong></div>
          <div class="muted"><?php echo esc_html( $issuer_address ); ?><?php echo $issuer_city ? esc_html( ', ' . $issuer_city ) : ''; ?><?php echo $issuer_country ? esc_html( ', ' . $issuer_country ) : ''; ?></div>
          <div class="muted"><?php echo esc_html( $issuer_cui ? ('CUI: ' . $issuer_cui) : '' ); ?><?php echo esc_html( $issuer_reg ? ('  •  RC: ' . $issuer_reg) : '' ); ?></div>
          <div class="muted"><?php echo esc_html( $issuer_iban ? ('IBAN: ' . $issuer_iban) : '' ); ?><?php echo esc_html( $issuer_bank ? ('  •  ' . $issuer_bank) : '' ); ?></div>
          <div class="muted"><?php echo esc_html( $issuer_email ? ('Email: ' . $issuer_email) : '' ); ?><?php echo esc_html( $issuer_phone ? ('  •  Tel: ' . $issuer_phone) : '' ); ?></div>
          <div class="muted"><?php echo esc_html( $issuer_website ? $issuer_website : '' ); ?></div>
          <div class="muted"><?php echo esc_html( $issuer_vat ? 'Plătitor TVA: Da' : 'Plătitor TVA: Nu' ); ?></div>
        </div>
      </div>
      <div class="box">
        <div class="muted" style="font-weight:700;margin-bottom:6px">Cumpărător</div>
        <div style="line-height:1.55">
          <div><strong><?php echo esc_html( $buyer_name ?: '—' ); ?></strong></div>
          <?php if ( $buyer_address_line ) : ?><div class="muted"><?php echo esc_html( $buyer_address_line ); ?></div><?php endif; ?>
          <?php if ( $buyer_cui ) : ?><div class="muted"><?php echo esc_html( 'CUI: ' . $buyer_cui ); ?></div><?php endif; ?>
          <?php if ( $buyer_reg ) : ?><div class="muted"><?php echo esc_html( 'RC: ' . $buyer_reg ); ?></div><?php endif; ?>
          <?php if ( $buyer_phone ) : ?><div class="muted"><?php echo esc_html( 'Tel: ' . $buyer_phone ); ?></div><?php endif; ?>
          <?php if ( $buyer_contact ) : ?><div class="muted"><?php echo esc_html( 'Contact: ' . $buyer_contact ); ?></div><?php endif; ?>
          <div class="muted"><?php echo esc_html( $buyer_vat ? 'Plătitor TVA: Da' : 'Plătitor TVA: Nu' ); ?></div>
          <?php if ( $buyer_email ) : ?><div class="muted"><?php echo esc_html( 'Email: ' . $buyer_email ); ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="box">
        <div class="row"><span class="muted">Data</span><span><?php echo esc_html( $created ? $created : current_time('mysql') ); ?></span></div>
        <div class="row"><span class="muted">Status</span><span><?php echo esc_html( $status ?: '—' ); ?></span></div>
        <div class="row"><span class="muted">Provider</span><span><?php echo esc_html( strtoupper($provider) ); ?></span></div>
        <div class="row"><span class="muted">ID Provider</span><span><?php echo esc_html( $prov_id ?: '—' ); ?></span></div>
      </div>
      <div class="box">
        <div class="row"><span class="muted">Plan</span><span><?php echo esc_html( $plan_id ?: '—' ); ?></span></div>
        <div class="row"><span class="muted">Perioadă</span><span><?php echo esc_html( $period_txt ?: '—' ); ?></span></div>
        <div class="row"><span class="muted">Total</span><span><strong><?php echo esc_html( number_format_i18n( $amount, 2 ) . ' ' . $currency ); ?></strong></span></div>
      </div>
    </div>

    <div class="box" style="margin-top:14px">
      <div class="muted" style="font-weight:700;margin-bottom:6px">Detalii</div>
      <div class="muted" style="line-height:1.5">
        <?php echo esc_html( $footer_note ? $footer_note : 'Document generat automat de AI Suite. Pentru contabilitate, folosește Print → Save as PDF.' ); ?>
      </div>
    </div>

  </div>
</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

# AJAX: Portal list
if ( ! function_exists( 'ai_suite_billing_ajax_history_list' ) ) {
    function ai_suite_billing_ajax_history_list() {
        $company_id = function_exists('ai_suite_billing_ajax_require_company') ? ai_suite_billing_ajax_require_company() : 0;
        if ( is_wp_error( $company_id ) ) wp_send_json_error( array('message'=>$company_id->get_error_message()), 403 );

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 30;
        $data = ai_suite_billing_history_get_company( $company_id, $limit, $limit );
        wp_send_json_success( $data );
    }
    add_action( 'wp_ajax_ai_suite_billing_history_list', 'ai_suite_billing_ajax_history_list' );
}

# AJAX: Portal invoice HTML
if ( ! function_exists( 'ai_suite_billing_ajax_invoice_html_portal' ) ) {
    function ai_suite_billing_ajax_invoice_html_portal() {
        // Allow GET links (HTML invoice in new tab)
        if ( empty($_POST['company_id']) && isset($_GET['company_id']) ) { $_POST['company_id'] = absint($_GET['company_id']); }
        if ( empty($_POST['nonce']) && isset($_GET['nonce']) ) { $_POST['nonce'] = sanitize_text_field( wp_unslash( $_GET['nonce'] ) ); }

        $company_id = function_exists('ai_suite_billing_ajax_require_company') ? ai_suite_billing_ajax_require_company() : 0;
        if ( is_wp_error( $company_id ) ) {
            status_header( 403 ); echo 'Forbidden'; exit;
        }
        check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );

        $invoice_id = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : (isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0);
        if ( ! $invoice_id ) { status_header( 400 ); echo 'Missing invoice_id'; exit; }

        global $wpdb;
        $t = ai_suite_billing_history_tables();
        $inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['invoices']} WHERE id=%d AND company_id=%d LIMIT 1", $invoice_id, $company_id ), ARRAY_A );
        if ( ! $inv ) { status_header( 404 ); echo 'Not found'; exit; }

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        echo ai_suite_billing_invoice_html( $inv );
        exit;
    }
    add_action( 'wp_ajax_ai_suite_billing_invoice_html_portal', 'ai_suite_billing_ajax_invoice_html_portal' );
}

# AJAX: Admin list
if ( ! function_exists( 'ai_suite_billing_ajax_admin_history_list' ) ) {
    function ai_suite_billing_ajax_admin_history_list() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array('message'=>'Forbidden'), 403 );
        }
        check_ajax_referer( 'ai_suite_admin_nonce', 'nonce' );

        $args = array(
            'company_id' => isset($_POST['company_id']) ? absint($_POST['company_id']) : 0,
            'provider' => isset($_POST['provider']) ? sanitize_key($_POST['provider']) : '',
            'status' => isset($_POST['status']) ? sanitize_key($_POST['status']) : '',
            'limit' => isset($_POST['limit']) ? absint($_POST['limit']) : 100,
        );
        $data = ai_suite_billing_history_admin_query( $args );
        wp_send_json_success( $data );
    }
    add_action( 'wp_ajax_ai_suite_billing_admin_history_list', 'ai_suite_billing_ajax_admin_history_list' );
}

# AJAX: Admin invoice HTML
if ( ! function_exists( 'ai_suite_billing_ajax_invoice_html_admin' ) ) {
    function ai_suite_billing_ajax_invoice_html_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            status_header(403); echo 'Forbidden'; exit;
        }
        check_ajax_referer( 'ai_suite_admin_nonce', 'nonce' );

        $invoice_id = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : (isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0);
        if ( ! $invoice_id ) { status_header(400); echo 'Missing invoice_id'; exit; }

        global $wpdb;
        $t = ai_suite_billing_history_tables();
        $inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['invoices']} WHERE id=%d LIMIT 1", $invoice_id ), ARRAY_A );
        if ( ! $inv ) { status_header(404); echo 'Not found'; exit; }

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        echo ai_suite_billing_invoice_html( $inv );
        exit;
    }
    add_action( 'wp_ajax_ai_suite_billing_invoice_html_admin', 'ai_suite_billing_ajax_invoice_html_admin' );
}
