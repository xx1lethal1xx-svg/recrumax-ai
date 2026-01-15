
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$templates = function_exists('ai_suite_get_ats_templates') ? ai_suite_get_ats_templates() : array();
if ( empty( $templates ) ) {
    $templates = array();
}
?>
<div class="ais-card">
  <h2 style="margin-top:0;"><?php echo esc_html__( 'Șabloane Email ATS', 'ai-suite' ); ?></h2>
  <p style="margin-top:6px;opacity:.85">
    <?php echo esc_html__( 'Aceste șabloane se folosesc în Portal → ATS Board → Bulk Email. Poți folosi variabile:', 'ai-suite' ); ?>
    <code>{candidate_name}</code> <code>{job_title}</code> <code>{company_name}</code> <code>{interview_date}</code> <code>{portal_link}</code>
  </p>

  <div id="ais-ats-tpl-wrap" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin-top:14px;">
    <?php foreach ( $templates as $t ) : ?>
      <div class="ais-card" style="border:1px solid rgba(0,0,0,.08);">
        <div style="display:flex;gap:10px;align-items:center;">
          <input class="ais-input ais-tpl-key" style="max-width:160px" value="<?php echo esc_attr( $t['key'] ); ?>" placeholder="key" />
          <input class="ais-input ais-tpl-label" value="<?php echo esc_attr( $t['label'] ); ?>" placeholder="Etichetă" />
        </div>
        <div style="margin-top:10px">
          <input class="ais-input ais-tpl-subject" value="<?php echo esc_attr( $t['subject'] ); ?>" placeholder="Subiect" />
        </div>
        <div style="margin-top:10px">
          <textarea class="ais-input ais-tpl-body" rows="6" placeholder="Conținut"><?php echo esc_textarea( $t['body'] ); ?></textarea>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
    <button type="button" class="ais-btn ais-btn-primary" id="ais-ats-tpl-save"><?php echo esc_html__( 'Salvează șabloanele', 'ai-suite' ); ?></button>
    <button type="button" class="ais-btn" id="ais-ats-tpl-add"><?php echo esc_html__( 'Adaugă șablon', 'ai-suite' ); ?></button>
    <span id="ais-ats-tpl-msg" style="opacity:.85"></span>
  </div>
</div>

<script>
(function($){
  function toast(msg, ok){
    $('#ais-ats-tpl-msg').text(msg).css('color', ok ? '#1b7f3a' : '#b00020');
    setTimeout(function(){ $('#ais-ats-tpl-msg').text('').css('color',''); }, 5000);
  }
  function rowTpl(){
    return `
    <div class="ais-card" style="border:1px solid rgba(0,0,0,.08);">
      <div style="display:flex;gap:10px;align-items:center;">
        <input class="ais-input ais-tpl-key" style="max-width:160px" value="" placeholder="key (ex: followup)" />
        <input class="ais-input ais-tpl-label" value="" placeholder="Etichetă" />
      </div>
      <div style="margin-top:10px">
        <input class="ais-input ais-tpl-subject" value="" placeholder="Subiect" />
      </div>
      <div style="margin-top:10px">
        <textarea class="ais-input ais-tpl-body" rows="6" placeholder="Conținut"></textarea>
      </div>
    </div>`;
  }

  $('#ais-ats-tpl-add').on('click', function(){
    $('#ais-ats-tpl-wrap').append(rowTpl());
  });

  $('#ais-ats-tpl-save').on('click', function(){
    var tpls = [];
    $('#ais-ats-tpl-wrap .ais-card').each(function(){
      var $c = $(this);
      var key = ($c.find('.ais-tpl-key').val()||'').trim();
      if(!key) return;
      tpls.push({
        key: key,
        label: ($c.find('.ais-tpl-label').val()||'').trim(),
        subject: ($c.find('.ais-tpl-subject').val()||'').trim(),
        body: ($c.find('.ais-tpl-body').val()||'').trim()
      });
    });
    if(!tpls.length){ toast('Nu ai niciun șablon valid (key lipsă).', false); return; }

    $.post(AI_Suite_Admin.ajax, {
      action: 'ai_suite_ats_templates_save',
      nonce: AI_Suite_Admin.nonce,
      templates: JSON.stringify(tpls)
    }).done(function(res){
      if(res && res.success) toast('Salvat ✅', true);
      else toast((res && res.data && res.data.message) ? res.data.message : 'Eroare la salvare', false);
    }).fail(function(){ toast('Eroare AJAX', false); });
  });
})(jQuery);
</script>
