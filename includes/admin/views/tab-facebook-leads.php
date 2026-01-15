<?php
/**
 * Facebook Leads tab – settings + inbox
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
if ( ! current_user_can( $cap ) ) {
    echo '<p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p>';
    return;
}

if ( function_exists( 'ai_suite_fb_get_settings' ) ) {
    $s = ai_suite_fb_get_settings();
} else {
    $s = array();
}

$webhook_url = function_exists( 'rest_url' ) ? rest_url( 'ai-suite/v1/facebook/webhook' ) : site_url( '/wp-json/ai-suite/v1/facebook/webhook' );

$notice = '';
if ( isset( $_GET['saved'] ) ) {
    $notice = '<div class="notice notice-success"><p>' . esc_html__( 'Setările au fost salvate.', 'ai-suite' ) . '</p></div>';
} elseif ( isset( $_GET['convert'] ) ) {
    $notice = '<div class="notice ' . ( $_GET['convert'] === 'ok' ? 'notice-success' : 'notice-error' ) . '"><p>' . ( $_GET['convert'] === 'ok' ? esc_html__( 'Lead convertit în candidat.', 'ai-suite' ) : esc_html__( 'Conversia a eșuat. Verifică logurile.', 'ai-suite' ) ) . '</p></div>';
}

echo $notice;

?>
<div class="ais-card">
  <h2><?php echo esc_html__( 'Facebook Leads (Lead Ads)', 'ai-suite' ); ?></h2>
  <p class="description"><?php echo esc_html__( 'Primește lead-uri în timp real din formularele Facebook/Instagram Lead Ads, le salvează în Inbox și le poți converti rapid în candidați.', 'ai-suite' ); ?></p>

  <div class="ais-grid" style="grid-template-columns:1fr 1fr; gap:16px;">
    <div class="ais-card" style="margin:0;">
      <h3><?php echo esc_html__( 'Webhook URL', 'ai-suite' ); ?></h3>
      <code style="display:block; padding:10px; border-radius:10px; background:#0b1020; color:#cbd5e1; overflow:auto;"><?php echo esc_html( $webhook_url ); ?></code>
      <p class="description"><?php echo esc_html__( 'În Meta Developers → Webhooks → leadgen: folosește exact URL-ul de mai sus.', 'ai-suite' ); ?></p>
      <p><strong><?php echo esc_html__( 'Verify Token:', 'ai-suite' ); ?></strong> <code><?php echo esc_html( isset($s['verify_token']) ? $s['verify_token'] : '' ); ?></code></p>
      <p class="description"><?php echo esc_html__( 'În Meta trebuie să pui același Verify Token ca să treacă validarea (hub.challenge).', 'ai-suite' ); ?></p>
    </div>

    <div class="ais-card" style="margin:0;">
      <h3><?php echo esc_html__( 'Test Token Rapid', 'ai-suite' ); ?></h3>
      <p class="description"><?php echo esc_html__( 'Testează Page Access Token (apelează Graph /me).', 'ai-suite' ); ?></p>
      <input type="text" id="aisFbTokenTest" class="regular-text" placeholder="<?php echo esc_attr__( 'Page Access Token…', 'ai-suite' ); ?>" />
      <button class="button button-primary" id="aisFbTestBtn"><?php echo esc_html__( 'Testează', 'ai-suite' ); ?></button>
      <pre id="aisFbTestOut" style="margin-top:10px; max-height:220px; overflow:auto; background:#0b1020; color:#cbd5e1; padding:10px; border-radius:10px;"></pre>
    </div>
  </div>
</div>

<div class="ais-card">
  <h2><?php echo esc_html__( 'Setări', 'ai-suite' ); ?></h2>
  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'ai_suite_fb_settings' ); ?>
    <input type="hidden" name="action" value="ai_suite_save_fb_settings" />

    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><?php echo esc_html__( 'Activ', 'ai-suite' ); ?></th>
        <td>
          <label><input type="checkbox" name="enabled" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <?php echo esc_html__( 'Activează webhook + inbox', 'ai-suite' ); ?></label>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Auto-convert', 'ai-suite' ); ?></th>
        <td>
          <label><input type="checkbox" name="auto_convert" <?php checked( ! empty( $s['auto_convert'] ) ); ?> /> <?php echo esc_html__( 'Convertește automat lead-urile în candidați', 'ai-suite' ); ?></label>
          <p class="description"><?php echo esc_html__( 'Recomandat doar după ce verifici că field mapping-ul din formulare este corect.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Verify Token', 'ai-suite' ); ?></th>
        <td>
          <input type="text" name="verify_token" class="regular-text" value="<?php echo esc_attr( isset($s['verify_token']) ? $s['verify_token'] : '' ); ?>" />
          <p class="description"><?php echo esc_html__( 'Valoarea din Meta Webhooks → Verification Token.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'App Secret (opțional)', 'ai-suite' ); ?></th>
        <td>
          <input type="password" name="app_secret" class="regular-text" value="<?php echo esc_attr( isset($s['app_secret']) ? $s['app_secret'] : '' ); ?>" />
          <p class="description"><?php echo esc_html__( 'Dacă setezi App Secret, payload-ul va fi verificat prin X-Hub-Signature-256.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Graph API Version', 'ai-suite' ); ?></th>
        <td>
          <input type="text" name="graph_version" class="regular-text" value="<?php echo esc_attr( isset($s['graph_version']) ? $s['graph_version'] : 'v19.0' ); ?>" />
          <p class="description"><?php echo esc_html__( 'Ex: v19.0. Poți lăsa default.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Default Page Access Token', 'ai-suite' ); ?></th>
        <td>
          <textarea name="default_page_token" class="large-text code" rows="2"><?php echo esc_textarea( isset($s['default_page_token']) ? $s['default_page_token'] : '' ); ?></textarea>
          <p class="description"><?php echo esc_html__( 'Token folosit dacă nu setezi token specific pe page_id.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Page Tokens (page_id=token)', 'ai-suite' ); ?></th>
        <td>
          <textarea name="page_tokens" class="large-text code" rows="5" placeholder="1234567890=EAAB...&#10;9876543210=EAAB..."><?php
            if ( ! empty( $s['page_tokens'] ) && is_array( $s['page_tokens'] ) ) {
                foreach ( $s['page_tokens'] as $pid => $tok ) {
                    echo esc_textarea( $pid . '=' . $tok ) . "\n";
                }
            }
          ?></textarea>
          <p class="description"><?php echo esc_html__( 'Opțional. Dacă ai mai multe pagini, poți seta token separat pentru fiecare page_id.', 'ai-suite' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><?php echo esc_html__( 'Mapare Form → Companie (form_id=company_post_id)', 'ai-suite' ); ?></th>
        <td>
          <textarea name="form_company_map" class="large-text code" rows="4" placeholder="1122334455=123&#10;2233445566=456"><?php
            if ( ! empty( $s['form_company_map'] ) && is_array( $s['form_company_map'] ) ) {
                foreach ( $s['form_company_map'] as $fid => $cid ) {
                    echo esc_textarea( $fid . '=' . (int) $cid ) . "\n";
                }
            }
          ?></textarea>
          <p class="description"><?php echo esc_html__( 'Dacă vrei ca lead-urile dintr-un form să fie atașate automat unei companii (CPT company).', 'ai-suite' ); ?></p>
        </td>
      </tr>
    </table>

    <p>
      <button type="submit" class="button button-primary"><?php echo esc_html__( 'Salvează setările', 'ai-suite' ); ?></button>
    </p>
  </form>
</div>

<?php
// Leads Inbox
global $wpdb;
if ( function_exists( 'ai_suite_fb_ensure_table' ) ) {
    ai_suite_fb_ensure_table();
}

$table = function_exists('ai_suite_fb_leads_table') ? ai_suite_fb_leads_table() : '';
if ( $table ) :
  $page = isset($_GET['p']) ? max(1, absint($_GET['p'])) : 1;
  $per_page = 20;
  $offset = ($page-1)*$per_page;

  $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
  $where = '1=1';
  $args = array();

  if ( $status !== '' && in_array( $status, array('new','processed','error'), true ) ) {
      $where .= ' AND status=%s';
      $args[] = $status;
  }

  $total = (int) $wpdb->get_var( $args ? $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $args) : "SELECT COUNT(*) FROM {$table}" );
  $rows = $wpdb->get_results(
      $args
        ? $wpdb->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge($args, array($per_page, $offset)) )
        : $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset),
      ARRAY_A
  );

  $pages = max(1, (int) ceil($total / $per_page));
?>
<div class="ais-card">
  <h2><?php echo esc_html__( 'Inbox Leads', 'ai-suite' ); ?></h2>
  <p class="description"><?php echo esc_html__( 'Lead-urile recepționate prin webhook apar aici. Poți converti manual în candidat.', 'ai-suite' ); ?></p>

  <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
    <a class="button <?php echo $status===''?'button-primary':''; ?>" href="<?php echo esc_url( admin_url('admin.php?page=ai-suite&tab=facebook_leads') ); ?>"><?php echo esc_html__('Toate', 'ai-suite'); ?></a>
    <a class="button <?php echo $status==='new'?'button-primary':''; ?>" href="<?php echo esc_url( admin_url('admin.php?page=ai-suite&tab=facebook_leads&status=new') ); ?>"><?php echo esc_html__('Noi', 'ai-suite'); ?></a>
    <a class="button <?php echo $status==='processed'?'button-primary':''; ?>" href="<?php echo esc_url( admin_url('admin.php?page=ai-suite&tab=facebook_leads&status=processed') ); ?>"><?php echo esc_html__('Procesate', 'ai-suite'); ?></a>
    <a class="button <?php echo $status==='error'?'button-primary':''; ?>" href="<?php echo esc_url( admin_url('admin.php?page=ai-suite&tab=facebook_leads&status=error') ); ?>"><?php echo esc_html__('Erori', 'ai-suite'); ?></a>
    <span style="margin-left:auto; color:#64748b;"><?php echo esc_html( sprintf( __( 'Total: %d', 'ai-suite' ), $total ) ); ?></span>
  </div>

  <table class="widefat striped">
    <thead>
      <tr>
        <th><?php echo esc_html__('ID', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Leadgen', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Form', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Page', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Status', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Candidat', 'ai-suite'); ?></th>
        <th><?php echo esc_html__('Acțiuni', 'ai-suite'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ( empty( $rows ) ) : ?>
        <tr><td colspan="7"><?php echo esc_html__( 'Niciun lead încă.', 'ai-suite' ); ?></td></tr>
      <?php else : foreach ( $rows as $r ) :
        $lead_id = (int) $r['id'];
        $convert_url = wp_nonce_url(
          admin_url( 'admin-post.php?action=ai_suite_fb_convert_lead&lead_id=' . $lead_id ),
          'ai_suite_fb_convert_' . $lead_id
        );

        $details = '';
        if ( ! empty( $r['field_data'] ) ) {
            $f = json_decode( (string) $r['field_data'], true );
            if ( is_array($f) ) {
                $m = function_exists('ai_suite_fb_field_map') ? ai_suite_fb_field_map($f) : array();
                $g = function_exists('ai_suite_fb_guess_name_email_phone_location') ? ai_suite_fb_guess_name_email_phone_location($m) : array();
                $details = trim( (isset($g['name'])?$g['name']:'') . ' • ' . (isset($g['email'])?$g['email']:'') . ' • ' . (isset($g['phone'])?$g['phone']:'') );
            }
        }
      ?>
        <tr>
          <td><?php echo (int) $r['id']; ?></td>
          <td>
            <code><?php echo esc_html( $r['leadgen_id'] ); ?></code>
            <?php if ( $details ) : ?><div style="color:#64748b; font-size:12px; margin-top:4px;"><?php echo esc_html($details); ?></div><?php endif; ?>
          </td>
          <td><code><?php echo esc_html( $r['form_id'] ); ?></code></td>
          <td><code><?php echo esc_html( $r['page_id'] ); ?></code></td>
          <td>
            <span class="ais-pill" style="padding:4px 10px; border-radius:999px; background:#0b1020; color:#cbd5e1;"><?php echo esc_html( $r['status'] ); ?></span>
            <?php if ( ! empty($r['error_text']) ) : ?><div style="color:#ef4444; font-size:12px; margin-top:4px;"><?php echo esc_html($r['error_text']); ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ( ! empty( $r['candidate_id'] ) ) : ?>
              <a href="<?php echo esc_url( get_edit_post_link( (int) $r['candidate_id'] ) ); ?>"><?php echo esc_html__( 'Vezi', 'ai-suite' ); ?> #<?php echo (int) $r['candidate_id']; ?></a>
            <?php else : ?>
              —
            <?php endif; ?>
          </td>
          <td>
            <?php if ( empty( $r['candidate_id'] ) ) : ?>
              <a class="button button-small" href="<?php echo esc_url( $convert_url ); ?>"><?php echo esc_html__( 'Convertește în candidat', 'ai-suite' ); ?></a>
            <?php else : ?>
              <span style="color:#64748b;"><?php echo esc_html__( 'Procesat', 'ai-suite' ); ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if ( $pages > 1 ) : ?>
    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      <?php for ($i=1;$i<=$pages;$i++): 
        $url = admin_url('admin.php?page=ai-suite&tab=facebook_leads' . ($status?('&status='.rawurlencode($status)):'') . '&p=' . $i);
      ?>
        <a class="button <?php echo $i===$page?'button-primary':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo (int)$i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</div>
<?php endif; ?>

<script>
(function(){
  const btn = document.getElementById('aisFbTestBtn');
  const inp = document.getElementById('aisFbTokenTest');
  const out = document.getElementById('aisFbTestOut');
  if(!btn || !inp || !out) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    out.textContent = '...';
    const fd = new FormData();
    fd.append('action', 'ai_suite_fb_test_token');
    fd.append('token', inp.value || '');
    fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' })
      .then(r=>r.json())
      .then(j=>{
        out.textContent = JSON.stringify(j, null, 2);
      })
      .catch(err=>{
        out.textContent = String(err);
      });
  });
})();
</script>
