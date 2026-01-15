<?php
/**
 * Admin tab: Billing History (events + invoices).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! current_user_can( 'manage_options' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Acces interzis.', 'ai-suite' ) . '</p></div>';
    return;
}

$company_id = isset($_GET['company_id']) ? absint($_GET['company_id']) : 0;
$provider   = isset($_GET['provider']) ? sanitize_key( wp_unslash($_GET['provider']) ) : '';
$status     = isset($_GET['status']) ? sanitize_key( wp_unslash($_GET['status']) ) : '';
$limit      = isset($_GET['limit']) ? absint($_GET['limit']) : 100;
if ( ! $limit ) $limit = 100;
$limit = max( 10, min( 500, $limit ) );

$data = array( 'events' => array(), 'invoices' => array() );
if ( function_exists( 'ai_suite_billing_history_admin_query' ) ) {
    $data = ai_suite_billing_history_admin_query( array(
        'company_id' => $company_id,
        'provider'   => $provider,
        'status'     => $status,
        'limit'      => $limit,
    ) );
}

$admin_nonce = function_exists('wp_create_nonce') ? wp_create_nonce('ai_suite_admin_nonce') : '';
?>
<div class="ais-card">
  <div class="ais-card-head">
    <h3 class="ais-card-title"><?php echo esc_html__( 'Istoric plăți & facturi', 'ai-suite' ); ?></h3>
    <div class="ais-muted"><?php echo esc_html__( 'Audit complet: evenimente (checkout/paid/failed/confirm) + facturi HTML.', 'ai-suite' ); ?></div>
  </div>

  <form method="get" style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
    <input type="hidden" name="page" value="ai-suite"/>
    <input type="hidden" name="tab" value="billing_history"/>

    <label style="display:flex;flex-direction:column;gap:6px">
      <span class="ais-muted"><?php echo esc_html__( 'Company ID', 'ai-suite' ); ?></span>
      <input type="number" name="company_id" value="<?php echo esc_attr($company_id); ?>" min="0" style="width:160px"/>
    </label>

    <label style="display:flex;flex-direction:column;gap:6px">
      <span class="ais-muted"><?php echo esc_html__( 'Provider', 'ai-suite' ); ?></span>
      <select name="provider" style="width:180px">
        <option value=""><?php echo esc_html__( 'Toți', 'ai-suite' ); ?></option>
        <option value="stripe" <?php selected($provider,'stripe'); ?>>STRIPE</option>
        <option value="netopia" <?php selected($provider,'netopia'); ?>>NETOPIA</option>
      </select>
    </label>

    <label style="display:flex;flex-direction:column;gap:6px">
      <span class="ais-muted"><?php echo esc_html__( 'Status', 'ai-suite' ); ?></span>
      <select name="status" style="width:180px">
        <option value=""><?php echo esc_html__( 'Toate', 'ai-suite' ); ?></option>
        <option value="paid" <?php selected($status,'paid'); ?>>paid</option>
        <option value="failed" <?php selected($status,'failed'); ?>>failed</option>
        <option value="pending" <?php selected($status,'pending'); ?>>pending</option>
        <option value="open" <?php selected($status,'open'); ?>>open</option>
      </select>
    </label>

    <label style="display:flex;flex-direction:column;gap:6px">
      <span class="ais-muted"><?php echo esc_html__( 'Limit', 'ai-suite' ); ?></span>
      <input type="number" name="limit" value="<?php echo esc_attr($limit); ?>" min="10" max="500" style="width:140px"/>
    </label>

    <button class="button button-primary"><?php echo esc_html__( 'Filtrează', 'ai-suite' ); ?></button>
  </form>
</div>

<div class="ais-grid" style="grid-template-columns:1fr; gap:14px; margin-top:14px">

  <div class="ais-card">
    <div class="ais-card-head">
      <h3 class="ais-card-title"><?php echo esc_html__( 'Facturi', 'ai-suite' ); ?></h3>
      <div class="ais-muted"><?php echo esc_html__( 'Click „HTML” pentru factura print-friendly.', 'ai-suite' ); ?></div>
    </div>

    <div class="ais-tablewrap" style="margin-top:10px">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th><?php echo esc_html__( 'Factura', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Companie', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Provider', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Status', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Total', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Perioadă', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Creat', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Acțiuni', 'ai-suite' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $invoices = is_array($data['invoices']) ? $data['invoices'] : array();
          if ( ! $invoices ) :
          ?>
            <tr><td colspan="9"><?php echo esc_html__( 'Nu există facturi încă.', 'ai-suite' ); ?></td></tr>
          <?php else:
            foreach ( $invoices as $inv ) :
              $cid = absint($inv['company_id'] ?? 0);
              $amt = ((int)($inv['amount_cents'] ?? 0))/100;
              $cur = esc_html( (string)($inv['currency'] ?? 'EUR') );
              $p1 = absint($inv['period_start'] ?? 0);
              $p2 = absint($inv['period_end'] ?? 0);
              $per = ($p1||$p2) ? esc_html( ($p1?gmdate('Y-m-d',$p1):'—') . ' → ' . ($p2?gmdate('Y-m-d',$p2):'—') ) : '—';
              $html_url = admin_url( 'admin-ajax.php?action=ai_suite_billing_invoice_html_admin&invoice_id=' . absint($inv['id']) . '&nonce=' . urlencode($admin_nonce) );
          ?>
            <tr>
              <td><?php echo absint($inv['id']); ?></td>
              <td><strong><?php echo esc_html( (string)($inv['invoice_number'] ?? '—') ); ?></strong></td>
              <td><?php echo $cid ? esc_html( get_the_title($cid) ) : '—'; ?></td>
              <td><?php echo esc_html( strtoupper((string)($inv['provider'] ?? '')) ); ?></td>
              <td><?php echo esc_html( (string)($inv['status'] ?? '') ); ?></td>
              <td><strong><?php echo esc_html( number_format_i18n($amt,2) . ' ' . $cur ); ?></strong></td>
              <td><?php echo $per; ?></td>
              <td><?php echo esc_html( (string)($inv['created_at'] ?? '') ); ?></td>
              <td><a class="button button-small" href="<?php echo esc_url($html_url); ?>" target="_blank" rel="noopener">HTML</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="ais-card">
    <div class="ais-card-head">
      <h3 class="ais-card-title"><?php echo esc_html__( 'Evenimente (audit)', 'ai-suite' ); ?></h3>
      <div class="ais-muted"><?php echo esc_html__( 'Ultimele evenimente de facturare (immutable).', 'ai-suite' ); ?></div>
    </div>

    <div class="ais-tablewrap" style="margin-top:10px">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th><?php echo esc_html__( 'Companie', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Provider', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Tip', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Status', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Suma', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Ref', 'ai-suite' ); ?></th>
            <th><?php echo esc_html__( 'Creat', 'ai-suite' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $events = is_array($data['events']) ? $data['events'] : array();
          if ( ! $events ) :
          ?>
            <tr><td colspan="8"><?php echo esc_html__( 'Nu există evenimente încă.', 'ai-suite' ); ?></td></tr>
          <?php else:
            foreach ( $events as $ev ) :
              $cid = absint($ev['company_id'] ?? 0);
              $amt = ((int)($ev['amount_cents'] ?? 0))/100;
              $cur = esc_html( (string)($ev['currency'] ?? 'EUR') );
          ?>
            <tr>
              <td><?php echo absint($ev['id']); ?></td>
              <td><?php echo $cid ? esc_html(get_the_title($cid)) : '—'; ?></td>
              <td><?php echo esc_html( strtoupper((string)($ev['provider'] ?? '')) ); ?></td>
              <td><?php echo esc_html( (string)($ev['event_type'] ?? '') ); ?></td>
              <td><?php echo esc_html( (string)($ev['status'] ?? '') ); ?></td>
              <td><?php echo esc_html( number_format_i18n($amt,2) . ' ' . $cur ); ?></td>
              <td><?php echo esc_html( (string)($ev['provider_ref'] ?? '') ); ?></td>
              <td><?php echo esc_html( (string)($ev['created_at'] ?? '') ); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
