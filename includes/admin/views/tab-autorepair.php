<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$status = function_exists( 'aisuite_autorepair_get_status' ) ? aisuite_autorepair_get_status() : array();
$enabled = ! empty( $status['enabled'] );
$last_run = ! empty( $status['last_run'] ) ? (int) $status['last_run'] : 0;
$last_ai  = ! empty( $status['last_ai_at'] ) ? (int) $status['last_ai_at'] : 0;
$last_hash = ! empty( $status['last_issue_hash'] ) ? (string) $status['last_issue_hash'] : '';
$last_ai_text = ! empty( $status['last_ai_text'] ) ? (string) $status['last_ai_text'] : '';

$fmt_time = function( $ts ) {
    if ( ! $ts ) return '—';
    return date_i18n( 'Y-m-d H:i', $ts );
};
?>

<div class="ais-card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">Auto-Repair AI</h2>
      <p class="description" style="margin:6px 0 0;">
        Diagnostic + self-heal. Rulează automat periodic și trimite email când apare ceva critic.
      </p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <span class="ais-pill" id="ais-ar-status-pill" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
        <?php echo $enabled ? 'Activ' : 'Oprit'; ?>
      </span>
      <button type="button" class="button" id="ais-ar-toggle" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
        <?php echo $enabled ? 'Dezactivează' : 'Activează'; ?>
      </button>
      <button type="button" class="button button-primary" id="ais-ar-run">Rulează diagnostic</button>
      <button type="button" class="button" id="ais-ar-apply-all" disabled>Aplică remedieri sigure</button>
    </div>
  </div>

  <div style="margin-top:12px; display:flex; gap:16px; flex-wrap:wrap;">
    <div class="ais-kpi" style="min-width:220px;">
      <div class="ais-kpi__label">Ultima rulare</div>
      <div class="ais-kpi__value"><?php echo esc_html( $fmt_time( $last_run ) ); ?></div>
      <div class="ais-kpi__sub">Hash: <?php echo esc_html( $last_hash ? substr( $last_hash, 0, 12 ) . '…' : '—' ); ?></div>
    </div>
    <div class="ais-kpi" style="min-width:220px;">
      <div class="ais-kpi__label">Ultimul AI advisor</div>
      <div class="ais-kpi__value"><?php echo esc_html( $fmt_time( $last_ai ) ); ?></div>
      <div class="ais-kpi__sub">Se rulează doar când există probleme + throttling.</div>
    </div>
    <div class="ais-kpi" style="min-width:280px;">
      <div class="ais-kpi__label">Notificări email</div>
      <div class="ais-kpi__value"><?php echo esc_html( function_exists('aisuite_get_notification_email') ? aisuite_get_notification_email() : (string) get_option('admin_email','') ); ?></div>
      <div class="ais-kpi__sub">Se folosește câmpul "Email notificări" din Setări (dacă există).</div>
    </div>
  </div>
</div>

<div class="ais-grid" style="margin-top:16px;">
  <div class="ais-card">
    <h3 style="margin-top:0;">Rezultat diagnostic</h3>
    <div id="ais-ar-result" class="ais-muted">Apasă „Rulează diagnostic”.</div>
  </div>

  <div class="ais-card">
    <h3 style="margin-top:0;">AI Advisor (opțional)</h3>
    <p class="description" style="margin-top:0;">AI nu rescrie cod automat. Doar sumarizează problemele și propune pași siguri.</p>
    <div id="ais-ar-ai" class="ais-pre" style="white-space:pre-wrap; min-height:140px;">
      <?php echo $last_ai_text ? esc_html( $last_ai_text ) : '—'; ?>
    </div>
    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
      <button type="button" class="button" id="ais-ar-ai-run">Generează recomandări AI acum</button>
      <button type="button" class="button" id="ais-ar-history">Istoric</button>
    </div>
  </div>
</div>



<div class="ais-card" style="margin-top:16px;">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
      <h2 style="margin:0 0 6px 0;">Auto-Patch AI (cod) 🔧🤖</h2>
      <div class="ais-muted">Generează un patch mic cu AI pentru probleme recurente. Safe-by-default: nu aplică automat fără click + backup + rollback.</div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="button button-secondary" id="ais-ap-refresh">Refresh</button>
      <button type="button" class="button button-primary" id="ais-ap-generate">Generează fix AI</button>
      <button type="button" class="button" id="ais-ap-apply" disabled>Aplică fix</button>
      <button type="button" class="button" id="ais-ap-rollback" disabled>Rollback</button>
    </div>
  </div>

  <div style="margin-top:12px;">
    <div class="ais-muted" id="ais-ap-note">—</div>
    <div class="ais-pre" id="ais-ap-preview" style="margin-top:10px; white-space:pre-wrap; max-height:320px; overflow:auto;">Nicio propunere încă.</div>
  </div>
</div>

<div class="ais-modal" id="ais-ar-modal" style="display:none;">
  <div class="ais-modal__backdrop"></div>
  <div class="ais-modal__panel">
    <div class="ais-modal__head">
      <strong>Istoric Auto-Repair</strong>
      <button type="button" class="button" id="ais-ar-modal-close">Închide</button>
    </div>
    <div class="ais-modal__body">
      <div id="ais-ar-history-body" class="ais-pre" style="white-space:pre-wrap; max-height:60vh; overflow:auto;">Se încarcă…</div>
    </div>
  </div>
</div>
