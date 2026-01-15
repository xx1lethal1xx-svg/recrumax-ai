<?php
/**
 * Copilot AI tab view (Admin / Recruiter / Manager)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$cap = function_exists('aisuite_capability') ? aisuite_capability() : 'manage_options';
if ( ! current_user_can( $cap ) ) {
  echo '<div class="notice notice-error"><p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p></div>';
  return;
}

$user_id = get_current_user_id();
$is_admin = current_user_can( 'manage_ai_suite' );

$company_ids = array();
if ( $is_admin ) {
  $company_ids = get_posts( array(
    'post_type'      => 'ai_suite_company',
    'post_status'    => 'publish',
    'numberposts'    => 200,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
  ) );
} elseif ( function_exists( 'aisuite_get_assigned_company_ids' ) ) {
  $company_ids = aisuite_get_assigned_company_ids( $user_id );
}

$company_ids = is_array( $company_ids ) ? array_values( array_unique( array_map( 'intval', $company_ids ) ) ) : array();
?>
<div class="ais-premium">
  <div class="ais-container" style="padding-left:0;padding-right:0;">
    <div class="ais-card">
      <div class="ais-flex" style="gap:12px;align-items:center;justify-content:space-between;">
        <div>
          <div class="ais-pill"><?php echo esc_html__( 'Copilot AI', 'ai-suite' ); ?></div>
          <h2 class="ais-text-20" style="margin:10px 0 0;"><?php echo esc_html__( 'ChatGPT în dashboard (per companie)', 'ai-suite' ); ?></h2>
          <div class="ais-muted" style="margin-top:6px;"><?php echo esc_html__( 'Sugestii: anunț job, shortlist, mesaje către candidați, plan interviu, follow-up.', 'ai-suite' ); ?></div>
        </div>
        <div style="min-width:320px;">
          <label class="ais-muted" style="display:block;margin-bottom:6px;"><?php echo esc_html__( 'Companie', 'ai-suite' ); ?></label>
          <select id="ais-copilot-company" class="regular-text" style="width:100%;">
            <?php if ( empty( $company_ids ) ) : ?>
              <option value=""><?php echo esc_html__( 'Nu există companii alocate.', 'ai-suite' ); ?></option>
            <?php else : ?>
              <?php foreach ( $company_ids as $cid ) :
                $title = get_the_title( $cid );
                if ( ! $title ) { $title = '#' . $cid; }
              ?>
                <option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $title ); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <label style="display:flex;gap:8px;align-items:center;margin-top:10px;">
            <input type="checkbox" id="ais-copilot-include-pii" value="1" />
            <span class="ais-muted"><?php echo esc_html__( 'Include date personale în context (NU recomandat)', 'ai-suite' ); ?></span>
          </label>
        </div>
      </div>

      <hr style="margin:18px 0;border:0;border-top:1px solid rgba(255,255,255,0.08);" />

      <div id="ais-copilot-chat" class="ais-copilot-chat" aria-live="polite"></div>

      <div class="ais-flex" style="gap:10px;margin-top:12px;align-items:flex-start;">
        <textarea id="ais-copilot-input" class="large-text" rows="3" placeholder="<?php echo esc_attr__( 'Scrie o cerere… (ex: „Fă un anunț pentru vopsitor auto, Olanda, salariu 700€/săpt.”)', 'ai-suite' ); ?>"></textarea>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <button type="button" class="button button-primary" id="ais-copilot-send"><?php echo esc_html__( 'Trimite', 'ai-suite' ); ?></button>
          <button type="button" class="button" id="ais-copilot-clear"><?php echo esc_html__( 'Șterge conversația', 'ai-suite' ); ?></button>
        </div>
      </div>

      <div id="ais-copilot-status" class="ais-muted" style="margin-top:10px;"></div>
    </div>
  </div>
</div>
