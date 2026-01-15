<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$cap = function_exists('aisuite_capability') ? aisuite_capability() : 'manage_options';
if ( ! current_user_can( $cap ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Nu ai permisiuni pentru această pagină.', 'ai-suite' ) . '</p></div>';
    return;
}

$settings = function_exists('ai_suite_billing_get_settings') ? ai_suite_billing_get_settings() : array();
$plans    = function_exists('ai_suite_billing_get_plans') ? ai_suite_billing_get_plans() : array();
$default  = function_exists('ai_suite_billing_get_default_plan_id') ? ai_suite_billing_get_default_plan_id() : 'free';

?>
<div class="ais-admin-wrap">
  <div class="ais-admin-head">
    <h2><?php echo esc_html__( 'Billing & Subscriptions', 'ai-suite' ); ?></h2>
    <p class="description"><?php echo esc_html__( 'Configurează plățile (Stripe / NETOPIA) pentru abonamente + planurile tale (RO/EN via i18n).', 'ai-suite' ); ?></p>
  </div>

  <div class="ais-grid ais-grid-2" style="gap:16px; margin-top:12px;">
    <div class="ais-card">
      <h3 class="ais-card-title"><?php echo esc_html__( 'Plăți (Stripe / NETOPIA)', 'ai-suite' ); ?></h3>
      <p class="ais-muted"><?php echo esc_html__( 'Alege provider-ul de plăți. Cheile se salvează local în WP options. Recomandat: folosește chei Test în staging și Live în producție.', 'ai-suite' ); ?></p>

      <div style="margin-top:12px">
        <label class="ais-label"><?php echo esc_html__( 'Provider plăți', 'ai-suite' ); ?></label>
        <select id="ais-billing-mode">
          <?php $mode = sanitize_key( (string) ( $settings['mode'] ?? 'stripe' ) ); ?>
          <option value="stripe" <?php selected( $mode, 'stripe' ); ?>><?php echo esc_html__( 'Stripe', 'ai-suite' ); ?></option>
          <option value="netopia" <?php selected( $mode, 'netopia' ); ?>><?php echo esc_html__( 'NETOPIA (mobilPay)', 'ai-suite' ); ?></option>
          <option value="both" <?php selected( $mode, 'both' ); ?>><?php echo esc_html__( 'Ambele (alegi în portal)', 'ai-suite' ); ?></option>
        </select>
        <p class="ais-muted" style="margin-top:6px"><?php echo esc_html__( 'În modul „Ambele”, portalul poate alege Stripe sau NETOPIA la Upgrade.', 'ai-suite' ); ?></p>
      </div>

      <div id="ais-stripe-section" style="margin-top:12px">
        <h4 style="margin:10px 0 6px; font-size:13px; opacity:.9"><?php echo esc_html__( 'Stripe (abonamente recurente)', 'ai-suite' ); ?></h4>

      <div style="margin-top:12px">
        <label class="ais-label"><?php echo esc_html__( 'Publishable key', 'ai-suite' ); ?></label>
        <input type="text" class="regular-text" id="ais-stripe-pk" value="<?php echo esc_attr( (string)($settings['stripe_publishable_key'] ?? '') ); ?>" placeholder="pk_live_... / pk_test_..." />
      </div>

      <div style="margin-top:12px">
        <label class="ais-label"><?php echo esc_html__( 'Secret key', 'ai-suite' ); ?></label>
        <input type="password" class="regular-text" id="ais-stripe-sk" value="<?php echo esc_attr( (string)($settings['stripe_secret_key'] ?? '') ); ?>" placeholder="sk_live_... / sk_test_..." />
      </div>

      <div style="margin-top:12px">
        <label class="ais-label"><?php echo esc_html__( 'Webhook secret', 'ai-suite' ); ?></label>
        <input type="password" class="regular-text" id="ais-stripe-whsec" value="<?php echo esc_attr( (string)($settings['stripe_webhook_secret'] ?? '') ); ?>" placeholder="whsec_..." />
        <p class="ais-muted" style="margin-top:6px">
          <?php echo esc_html__( 'Setează endpoint-ul în Stripe către:', 'ai-suite' ); ?>
          <code><?php echo esc_html( rest_url( 'ai-suite/v1/stripe/webhook' ) ); ?></code>
        </p>
      </div>

      </div><!-- /#ais-stripe-section -->

      <div id="ais-netopia-section" style="margin-top:12px">
        <h4 style="margin:10px 0 6px; font-size:13px; opacity:.9"><?php echo esc_html__( 'NETOPIA (plată online hosted)', 'ai-suite' ); ?></h4>
        <p class="ais-muted" style="margin-top:0"><?php echo esc_html__( 'Se folosește pagina găzduită NETOPIA. Confirmarea vine pe un endpoint REST securizat prin cheile tale (decriptare).', 'ai-suite' ); ?></p>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px">
          <div>
            <label class="ais-label"><?php echo esc_html__( 'Sandbox', 'ai-suite' ); ?></label>
            <select id="ais-netopia-sandbox">
              <option value="1" <?php selected( (int)($settings['netopia_sandbox'] ?? 1), 1 ); ?>><?php echo esc_html__( 'DA (Test)', 'ai-suite' ); ?></option>
              <option value="0" <?php selected( (int)($settings['netopia_sandbox'] ?? 1), 0 ); ?>><?php echo esc_html__( 'NU (Live)', 'ai-suite' ); ?></option>
            </select>
          </div>
          <div>
            <label class="ais-label"><?php echo esc_html__( 'Signature', 'ai-suite' ); ?></label>
            <input type="text" class="regular-text" id="ais-netopia-signature" value="<?php echo esc_attr( (string)($settings['netopia_signature'] ?? '') ); ?>" placeholder="XXXX-XXXX-..." />
          </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
          <div style="flex:1; min-width:260px">
            <label class="ais-label"><?php echo esc_html__( 'Payment URL Live (advanced)', 'ai-suite' ); ?></label>
            <input type="text" class="regular-text" id="ais-netopia-live-url" value="<?php echo esc_attr( (string)($settings['netopia_live_url'] ?? 'https://secure.mobilpay.ro') ); ?>" placeholder="https://secure.mobilpay.ro" />
          </div>
          <div style="flex:1; min-width:260px">
            <label class="ais-label"><?php echo esc_html__( 'Payment URL Sandbox (advanced)', 'ai-suite' ); ?></label>
            <input type="text" class="regular-text" id="ais-netopia-sandbox-url" value="<?php echo esc_attr( (string)($settings['netopia_sandbox_url'] ?? 'https://sandboxsecure.mobilpay.ro') ); ?>" placeholder="https://sandboxsecure.mobilpay.ro" />
          </div>
        </div>
        <p class="ais-muted" style="margin-top:6px"><?php echo esc_html__( 'Lasă valorile default dacă folosești mobilPay standard. Schimbă doar dacă NETOPIA îți dă alte endpoint-uri.', 'ai-suite' ); ?></p>


        </div>

        <div style="margin-top:12px">
          <label class="ais-label"><?php echo esc_html__( 'Public certificate (PEM)', 'ai-suite' ); ?></label>
          <textarea id="ais-netopia-public" style="width:100%; min-height:110px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?php echo esc_textarea( (string)($settings['netopia_public_cert_pem'] ?? '') ); ?></textarea>
        </div>

        <div style="margin-top:12px">
          <label class="ais-label"><?php echo esc_html__( 'Private key (PEM)', 'ai-suite' ); ?></label>
          <textarea id="ais-netopia-private" style="width:100%; min-height:110px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?php echo esc_textarea( (string)($settings['netopia_private_key_pem'] ?? '') ); ?></textarea>
        </div>

        <div style="margin-top:12px; padding-top:12px; border-top:1px dashed rgba(0,0,0,.10)">
          <div class="ais-muted" style="font-weight:600; margin-bottom:8px"><?php echo esc_html__( 'Automatizare expirare abonament', 'ai-suite' ); ?></div>
          <div style="display:flex; gap:12px; flex-wrap:wrap">
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Grace days după expirare', 'ai-suite' ); ?></label>
              <input type="number" min="0" class="small-text" id="ais-expiry-grace" value="<?php echo esc_attr( (string) (int) ( $settings['expiry_grace_days'] ?? 3 ) ); ?>" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Notifică cu X zile înainte', 'ai-suite' ); ?></label>
              <input type="number" min="0" class="small-text" id="ais-expiry-notify" value="<?php echo esc_attr( (string) (int) ( $settings['expiry_notify_days'] ?? 3 ) ); ?>" />
            </div>
            <div style="min-width:240px">
              <label class="ais-label"><?php echo esc_html__( 'Sender email (opțional)', 'ai-suite' ); ?></label>
              <input type="email" class="regular-text" id="ais-expiry-sender-email" value="<?php echo esc_attr( (string) ( $settings['expiry_sender_email'] ?? '' ) ); ?>" placeholder="no-reply@domain.ro" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Sender name (opțional)', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-expiry-sender-name" value="<?php echo esc_attr( (string) ( $settings['expiry_sender_name'] ?? 'RecruMax' ) ); ?>" />
            </div>
          </div>
          <p class="ais-muted" style="margin-top:8px"><?php echo esc_html__( 'Rulează zilnic prin WP-Cron: trimite reminder înainte de expirare, activează grația după expirare și face downgrade automat la Free după grație.', 'ai-suite' ); ?></p>
        </div>

        <p class="ais-muted" style="margin-top:10px">
          <?php echo esc_html__( 'Confirm URL (setează în NETOPIA):', 'ai-suite' ); ?>
          <code><?php echo esc_html( rest_url( 'ai-suite/v1/netopia/confirm' ) ); ?></code>
        </p>
      </div>

      <div style="margin-top:12px">
        <label class="ais-label"><?php echo esc_html__( 'Default plan (companii noi)', 'ai-suite' ); ?></label>
        <select id="ais-default-plan">
          <?php foreach ( $plans as $p ): ?>
            <option value="<?php echo esc_attr($p['id']); ?>" <?php selected( $default, $p['id'] ); ?>>
              <?php echo esc_html( $p['name'] . ' (' . $p['id'] . ')' ); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.06)">
        <label class="ais-label" style="display:flex; gap:8px; align-items:center">
          <input type="checkbox" id="ais-trial-enabled" <?php checked( ! empty( $settings['trial_enabled'] ?? 1 ) ); ?> />
          <?php echo esc_html__( 'Activează trial pentru companii noi', 'ai-suite' ); ?>
        </label>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px">
          <div>
            <label class="ais-label"><?php echo esc_html__( 'Trial days', 'ai-suite' ); ?></label>
            <input type="number" min="0" class="small-text" id="ais-trial-days" value="<?php echo esc_attr( (string) (int) ( $settings['trial_days'] ?? 14 ) ); ?>" />
          </div>
          <div>
            <label class="ais-label"><?php echo esc_html__( 'Trial plan', 'ai-suite' ); ?></label>
            <select id="ais-trial-plan">
              <?php foreach ( $plans as $p ): ?>
                <option value="<?php echo esc_attr($p['id']); ?>" <?php selected( (string)($settings['trial_plan_id'] ?? 'pro'), $p['id'] ); ?>>
                  <?php echo esc_html( $p['name'] . ' (' . $p['id'] . ')' ); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="ais-label"><?php echo esc_html__( 'One-time trial per company', 'ai-suite' ); ?></label>
            <select id="ais-trial-once">
              <option value="1" <?php selected( (int)($settings['trial_once_per_company'] ?? 1), 1 ); ?>><?php echo esc_html__( 'Da', 'ai-suite' ); ?></option>
              <option value="0" <?php selected( (int)($settings['trial_once_per_company'] ?? 1), 0 ); ?>><?php echo esc_html__( 'Nu', 'ai-suite' ); ?></option>
            </select>
          </div>
          <div>
            <label class="ais-label"><?php echo esc_html__( 'Grace days after trial', 'ai-suite' ); ?></label>
            <input type="number" min="0" class="small-text" id="ais-trial-grace" value="<?php echo esc_attr( (string) (int) ( $settings['trial_grace_days'] ?? 0 ) ); ?>" />
          </div>
        </div>

        <div style="margin-top:12px; padding-top:12px; border-top:1px dashed rgba(0,0,0,.10)">
          <div class="ais-muted" style="font-weight:600; margin-bottom:8px"><?php echo esc_html__( 'Automatizare expirare abonament', 'ai-suite' ); ?></div>
          <div style="display:flex; gap:12px; flex-wrap:wrap">
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Grace days după expirare', 'ai-suite' ); ?></label>
              <input type="number" min="0" class="small-text" id="ais-expiry-grace" value="<?php echo esc_attr( (string) (int) ( $settings['expiry_grace_days'] ?? 3 ) ); ?>" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Notifică cu X zile înainte', 'ai-suite' ); ?></label>
              <input type="number" min="0" class="small-text" id="ais-expiry-notify" value="<?php echo esc_attr( (string) (int) ( $settings['expiry_notify_days'] ?? 3 ) ); ?>" />
            </div>
            <div style="min-width:240px">
              <label class="ais-label"><?php echo esc_html__( 'Sender email (opțional)', 'ai-suite' ); ?></label>
              <input type="email" class="regular-text" id="ais-expiry-sender-email" value="<?php echo esc_attr( (string) ( $settings['expiry_sender_email'] ?? '' ) ); ?>" placeholder="no-reply@domain.ro" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Sender name (opțional)', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-expiry-sender-name" value="<?php echo esc_attr( (string) ( $settings['expiry_sender_name'] ?? 'RecruMax' ) ); ?>" />
            </div>
          </div>
          <p class="ais-muted" style="margin-top:8px"><?php echo esc_html__( 'Rulează zilnic prin WP-Cron: trimite reminder înainte de expirare, activează grația după expirare și face downgrade automat la Free după grație.', 'ai-suite' ); ?></p>
        </div>

        <p class="ais-muted" style="margin-top:10px"><?php echo esc_html__( 'Trial-ul pornește automat la înregistrarea unei companii noi și oferă acces la planul selectat pentru X zile.', 'ai-suite' ); ?></p>
      </div>

      <div style="margin-top:14px">
        <div style="margin-top:14px; padding-top:14px; border-top:1px solid rgba(0,0,0,.06)">
          <h4 style="margin:0 0 8px; font-size:13px; opacity:.9"><?php echo esc_html__( 'Facturare (HTML) – Serii & emitent', 'ai-suite' ); ?></h4>
          <p class="ais-muted" style="margin-top:0"><?php echo esc_html__( 'Facturile sunt HTML (Print → Save as PDF). Completează datele emitentului pentru un document contabil complet. Câmpurile lipsă rămân necompletate pe factură.', 'ai-suite' ); ?></p>

          <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end">
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Serie / Prefix (template)', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-series" value="<?php echo esc_attr( (string)($settings['invoice_series_template'] ?? 'RMX-{Y}-') ); ?>" placeholder="RMX-{Y}-" />
              <div class="ais-muted" style="margin-top:6px"><?php echo esc_html__( 'Folosește {Y} (an), {m} (lună). Exemplu: RMX-{Y}-', 'ai-suite' ); ?></div>
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Padding număr', 'ai-suite' ); ?></label>
              <input type="number" min="2" max="8" class="small-text" id="ais-inv-padding" value="<?php echo esc_attr( (string)(int)($settings['invoice_number_padding'] ?? 4) ); ?>" />
            </div>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
            <div style="min-width:260px">
              <label class="ais-label"><?php echo esc_html__( 'Emitent – Nume firmă', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-name" value="<?php echo esc_attr( (string)($settings['invoice_issuer_name'] ?? 'RecruMax') ); ?>" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'CUI', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-cui" value="<?php echo esc_attr( (string)($settings['invoice_issuer_cui'] ?? '') ); ?>" placeholder="RO12345678" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Reg. Comerțului', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-reg" value="<?php echo esc_attr( (string)($settings['invoice_issuer_reg'] ?? '') ); ?>" placeholder="J00/000/2026" />
            </div>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
            <div style="flex:1; min-width:280px">
              <label class="ais-label"><?php echo esc_html__( 'Adresă', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-address" value="<?php echo esc_attr( (string)($settings['invoice_issuer_address'] ?? '') ); ?>" placeholder="Str., nr., bloc, sc., ap." />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Oraș', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-city" value="<?php echo esc_attr( (string)($settings['invoice_issuer_city'] ?? '') ); ?>" placeholder="Baia Mare" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Țară', 'ai-suite' ); ?></label>
              <input type="text" class="small-text" id="ais-inv-issuer-country" value="<?php echo esc_attr( (string)($settings['invoice_issuer_country'] ?? 'RO') ); ?>" placeholder="RO" />
            </div>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
            <div style="min-width:280px">
              <label class="ais-label"><?php echo esc_html__( 'IBAN', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-iban" value="<?php echo esc_attr( (string)($settings['invoice_issuer_iban'] ?? '') ); ?>" />
            </div>
            <div style="min-width:260px">
              <label class="ais-label"><?php echo esc_html__( 'Banca', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-bank" value="<?php echo esc_attr( (string)($settings['invoice_issuer_bank'] ?? '') ); ?>" placeholder="Banca Transilvania" />
            </div>
            <div>
              <label class="ais-label"><?php echo esc_html__( 'Plătitor TVA', 'ai-suite' ); ?></label>
              <select id="ais-inv-issuer-vat">
                <option value="0" <?php selected( (int)($settings['invoice_issuer_vat'] ?? 0), 0 ); ?>><?php echo esc_html__( 'Nu', 'ai-suite' ); ?></option>
                <option value="1" <?php selected( (int)($settings['invoice_issuer_vat'] ?? 0), 1 ); ?>><?php echo esc_html__( 'Da', 'ai-suite' ); ?></option>
              </select>
            </div>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
            <div style="min-width:260px">
              <label class="ais-label"><?php echo esc_html__( 'Email', 'ai-suite' ); ?></label>
              <input type="email" class="regular-text" id="ais-inv-issuer-email" value="<?php echo esc_attr( (string)($settings['invoice_issuer_email'] ?? '') ); ?>" placeholder="billing@domain.ro" />
            </div>
            <div style="min-width:220px">
              <label class="ais-label"><?php echo esc_html__( 'Telefon', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-phone" value="<?php echo esc_attr( (string)($settings['invoice_issuer_phone'] ?? '') ); ?>" placeholder="+40..." />
            </div>
            <div style="flex:1; min-width:260px">
              <label class="ais-label"><?php echo esc_html__( 'Website', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-website" value="<?php echo esc_attr( (string)($settings['invoice_issuer_website'] ?? home_url('/') ) ); ?>" />
            </div>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px">
            <div style="flex:1; min-width:320px">
              <label class="ais-label"><?php echo esc_html__( 'Logo URL (opțional)', 'ai-suite' ); ?></label>
              <input type="text" class="regular-text" id="ais-inv-issuer-logo" value="<?php echo esc_attr( (string)($settings['invoice_issuer_logo_url'] ?? '') ); ?>" placeholder="https://.../logo.png" />
            </div>
          </div>

          <div style="margin-top:12px">
            <label class="ais-label"><?php echo esc_html__( 'Notă footer', 'ai-suite' ); ?></label>
            <textarea id="ais-inv-footer" style="width:100%; min-height:80px"><?php echo esc_textarea( (string)($settings['invoice_footer_note'] ?? '') ); ?></textarea>
          </div>
        </div>

        <button type="button" class="button button-primary" id="ais-billing-save"><?php echo esc_html__( 'Salvează', 'ai-suite' ); ?></button>
        <span class="ais-muted" id="ais-billing-save-msg" style="margin-left:10px"></span>
      </div>
    </div>

    <div class="ais-card">
      <h3 class="ais-card-title"><?php echo esc_html__( 'Planuri', 'ai-suite' ); ?></h3>
      <p class="ais-muted">
        <?php echo esc_html__( 'Editează planurile ca JSON. Dacă folosești Stripe: setează stripe_price_id (Price ID recurent lunar) din Stripe Dashboard. Dacă folosești NETOPIA: este suficient price_monthly + configurarea NETOPIA.', 'ai-suite' ); ?>
      </p>

      <textarea id="ais-plans-json" style="width:100%; min-height:340px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;"><?php
        echo esc_textarea( wp_json_encode( $plans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
      ?></textarea>

      <div style="display:flex; gap:10px; align-items:center; margin-top:12px">
        <button type="button" class="button" id="ais-plans-validate"><?php echo esc_html__( 'Validează JSON', 'ai-suite' ); ?></button>
        <button type="button" class="button button-primary" id="ais-plans-save"><?php echo esc_html__( 'Salvează planuri', 'ai-suite' ); ?></button>
        <span class="ais-muted" id="ais-plans-msg"></span>
      </div>

      <div class="ais-muted" style="margin-top:12px">
        <strong><?php echo esc_html__( 'Tip:', 'ai-suite' ); ?></strong>
        <?php echo esc_html__( 'Stripe: creezi Products + Prices (monthly recurring). Copiezi Price ID în fiecare plan. NETOPIA: folosește price_monthly și cheile NETOPIA + Confirm URL.', 'ai-suite' ); ?>
      </div>
    </div>
  </div>

  <div class="ais-card" style="margin-top:16px">
    <h3 class="ais-card-title"><?php echo esc_html__( 'Gating (Feature access)', 'ai-suite' ); ?></h3>
    <p class="ais-muted"><?php echo esc_html__( 'Pluginul expune helpers: ai_suite_company_has_feature(), ai_suite_company_limit(), ai_suite_company_plan_id(). Le folosim în patch-urile următoare pentru a bloca module premium (AI Matching, Export, etc.) pe plan.', 'ai-suite' ); ?></p>
  </div>
</div>

<script>
(function(){
  function $id(id){ return document.getElementById(id); }
  function msg(el, t, ok){ if(!el) return; el.textContent = t || ''; el.style.color = ok ? '#0a7' : '#c33'; }
  function ajax(action, data){
    data = data || {};
    data.action = action;
    data.nonce = (window.AI_Suite_Admin && AI_Suite_Admin.nonce) ? AI_Suite_Admin.nonce : '';
    return fetch(ajaxurl, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(data).toString()
    }).then(r=>r.json());
  }

  function toggleProvider(){
    const mode = ($id('ais-billing-mode') ? $id('ais-billing-mode').value : 'stripe') || 'stripe';
    const stripe = $id('ais-stripe-section');
    const netopia = $id('ais-netopia-section');
    if (stripe) stripe.style.display = (mode === 'stripe' || mode === 'both') ? '' : 'none';
    if (netopia) netopia.style.display = (mode === 'netopia' || mode === 'both') ? '' : 'none';
  }
  $id('ais-billing-mode')?.addEventListener('change', toggleProvider);
  toggleProvider();

  $id('ais-billing-save')?.addEventListener('click', async function(){
    msg($id('ais-billing-save-msg'), '<?php echo esc_js( __( 'Se salvează…', 'ai-suite' ) ); ?>', true);
    const res = await ajax('ai_suite_billing_admin_save', {
      mode: $id('ais-billing-mode') ? $id('ais-billing-mode').value : 'stripe',
      netopia_sandbox: $id('ais-netopia-sandbox') ? $id('ais-netopia-sandbox').value : 1,
      netopia_signature: $id('ais-netopia-signature') ? $id('ais-netopia-signature').value : '',
      netopia_public_cert_pem: $id('ais-netopia-public') ? $id('ais-netopia-public').value : '',
      netopia_private_key_pem: $id('ais-netopia-private') ? $id('ais-netopia-private').value : '',
            netopia_live_url: $id('ais-netopia-live-url') ? $id('ais-netopia-live-url').value : '',
      netopia_sandbox_url: $id('ais-netopia-sandbox-url') ? $id('ais-netopia-sandbox-url').value : '',
stripe_publishable_key: $id('ais-stripe-pk').value,
      stripe_secret_key: $id('ais-stripe-sk').value,
      stripe_webhook_secret: $id('ais-stripe-whsec').value,
      default_plan: $id('ais-default-plan').value,
      trial_enabled: $id('ais-trial-enabled') && $id('ais-trial-enabled').checked ? 1 : 0,
      trial_days: $id('ais-trial-days') ? $id('ais-trial-days').value : 0,
      trial_plan_id: $id('ais-trial-plan') ? $id('ais-trial-plan').value : '',
      trial_once_per_company: $id('ais-trial-once') ? $id('ais-trial-once').value : 1,
      trial_grace_days: $id('ais-trial-grace') ? $id('ais-trial-grace').value : 0,
      expiry_grace_days: $id('ais-expiry-grace') ? $id('ais-expiry-grace').value : 3,
      expiry_notify_days: $id('ais-expiry-notify') ? $id('ais-expiry-notify').value : 3,
      expiry_sender_email: $id('ais-expiry-sender-email') ? $id('ais-expiry-sender-email').value : '',
      expiry_sender_name: $id('ais-expiry-sender-name') ? $id('ais-expiry-sender-name').value : '',

      invoice_series_template: $id('ais-inv-series') ? $id('ais-inv-series').value : 'RMX-{Y}-',
      invoice_number_padding: $id('ais-inv-padding') ? $id('ais-inv-padding').value : 4,
      invoice_issuer_name: $id('ais-inv-issuer-name') ? $id('ais-inv-issuer-name').value : '',
      invoice_issuer_cui: $id('ais-inv-issuer-cui') ? $id('ais-inv-issuer-cui').value : '',
      invoice_issuer_reg: $id('ais-inv-issuer-reg') ? $id('ais-inv-issuer-reg').value : '',
      invoice_issuer_address: $id('ais-inv-issuer-address') ? $id('ais-inv-issuer-address').value : '',
      invoice_issuer_city: $id('ais-inv-issuer-city') ? $id('ais-inv-issuer-city').value : '',
      invoice_issuer_country: $id('ais-inv-issuer-country') ? $id('ais-inv-issuer-country').value : 'RO',
      invoice_issuer_iban: $id('ais-inv-issuer-iban') ? $id('ais-inv-issuer-iban').value : '',
      invoice_issuer_bank: $id('ais-inv-issuer-bank') ? $id('ais-inv-issuer-bank').value : '',
      invoice_issuer_vat: $id('ais-inv-issuer-vat') ? $id('ais-inv-issuer-vat').value : 0,
      invoice_issuer_email: $id('ais-inv-issuer-email') ? $id('ais-inv-issuer-email').value : '',
      invoice_issuer_phone: $id('ais-inv-issuer-phone') ? $id('ais-inv-issuer-phone').value : '',
      invoice_issuer_website: $id('ais-inv-issuer-website') ? $id('ais-inv-issuer-website').value : '',
      invoice_issuer_logo_url: $id('ais-inv-issuer-logo') ? $id('ais-inv-issuer-logo').value : '',
      invoice_footer_note: $id('ais-inv-footer') ? $id('ais-inv-footer').value : ''
    });
    if(res && res.success) msg($id('ais-billing-save-msg'), '<?php echo esc_js( __( 'Salvat.', 'ai-suite' ) ); ?>', true);
    else msg($id('ais-billing-save-msg'), (res?.data?.message)||'Error', false);
  });

  $id('ais-plans-validate')?.addEventListener('click', function(){
    try{
      JSON.parse($id('ais-plans-json').value);
      msg($id('ais-plans-msg'), '<?php echo esc_js( __( 'JSON OK.', 'ai-suite' ) ); ?>', true);
    }catch(e){
      msg($id('ais-plans-msg'), '<?php echo esc_js( __( 'JSON invalid: ', 'ai-suite' ) ); ?>'+e.message, false);
    }
  });

  $id('ais-plans-save')?.addEventListener('click', async function(){
    let parsed;
    try{ parsed = JSON.parse($id('ais-plans-json').value); }
    catch(e){ msg($id('ais-plans-msg'), '<?php echo esc_js( __( 'JSON invalid: ', 'ai-suite' ) ); ?>'+e.message, false); return; }
    msg($id('ais-plans-msg'), '<?php echo esc_js( __( 'Se salvează…', 'ai-suite' ) ); ?>', true);
    const res = await ajax('ai_suite_billing_admin_save_plans', { plans_json: JSON.stringify(parsed) });
    if(res && res.success) msg($id('ais-plans-msg'), '<?php echo esc_js( __( 'Planuri salvate.', 'ai-suite' ) ); ?>', true);
    else msg($id('ais-plans-msg'), (res?.data?.message)||'Error', false);
  });

})();
</script>
