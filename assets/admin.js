(function ($) {
    'use strict';

  // Config (compatibil cu versiunile mai vechi ale pluginului)
  var CFG = window.AI_Suite_Admin || {
    ajax: (window.AI_SUITE && AI_SUITE.ajax_url) ? AI_SUITE.ajax_url : (window.ajaxurl || ''),
    nonce: (window.AI_SUITE && AI_SUITE.nonce) ? AI_SUITE.nonce : ''
  };

    function renderResult($el, payload) {
        if (!$el) {
            return;
        }
        if (payload && payload.success) {
            var data = payload.data || {};
            var html = '<div class="ai-ok"><strong>' + (data.message || 'OK') + '</strong></div>';
            if (data.data && data.data.checks) {
                html += '<ul class="ai-checks">';
                data.data.checks.forEach(function (c) {
                    var cls = c.ok ? 'ok' : 'bad';
                    html += '<li class="' + cls + '"><strong>' + c.title + '</strong>: ' + c.details + '</li>';
                });
                html += '</ul>';
            }
            // Additional data display for manager summary or bots can be added here.
            $el.html(html);
        } else {
            var msg = (payload && payload.data && payload.data.message) ? payload.data.message : 'Eroare necunoscută';
            $el.html('<div class="ai-bad"><strong>Eroare:</strong> ' + msg + '</div>');
        }
    }

    function runBot(botKey, args, $output) {
        $output.html('<em>Se rulează...</em>');
        $.post(AI_SUITE.ajax_url, {
            action: 'ai_suite_run_bot',
            nonce: AI_SUITE.nonce,
            bot: botKey,
            args: args || {}
        }).done(function (res) {
            renderResult($output, res);
        }).fail(function (xhr) {
            renderResult($output, { success: false, data: { message: xhr.responseText || 'Request failed' } });
        });
    }

    $(document).on('click', '#ai-run-healthcheck', function () {
        runBot('healthcheck', {}, $('#ai-healthcheck-result'));
    });

    $(document).on('click', '#ai-test-openai', function () {
        runBot('healthcheck', { mode: 'openai_test' }, $('#ai-healthcheck-result'));
    });

    $(document).on('click', '.ai-run-bot', function (e) {
        e.preventDefault();
        var botKey = $(this).data('bot');
        runBot(botKey, {}, $('#ai-bots-result'));
    });

    // Select all checkboxes in jobs table.
    $(document).on('change', '.job-select-all', function () {
        var checked = $(this).prop('checked');
        $('.job-select').prop('checked', checked);
    });
    // v1.8.1: AI Queue (Coada AI)
    function aiQueuePost(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = AI_SUITE.nonce;
        return $.post(AI_SUITE.ajax_url, data);
    }

    function aiQueueMsg(html) {
        var $out = $('#ai-queue-result');
        if ($out.length) {
            $out.html(html);
        }
    }

    $(document).on('click', '#ai-queue-run', function (e) {
        e.preventDefault();
        aiQueueMsg('<em>Se rulează worker...</em>');
        aiQueuePost('ai_suite_ai_queue_run', { limit: 10 })
            .done(function (res) {
                if (res && res.success) {
                    aiQueueMsg('<div class="ai-ok"><strong>OK</strong> – procesate: ' + (res.data && res.data.processed ? res.data.processed : 0) + '</div>');
                } else {
                    aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + ((res && res.data && res.data.message) ? res.data.message : 'Unknown') + '</div>');
                }
            })
            .fail(function (xhr) {
                aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + (xhr.responseText || 'Request failed') + '</div>');
            });
    });

    $(document).on('click', '#ai-queue-refresh', function (e) {
        e.preventDefault();
        window.location.reload();
    });

    $(document).on('click', '#ai-queue-purge', function (e) {
        e.preventDefault();
        if (!confirm('Ștergi task-urile DONE/FAILED mai vechi de 14 zile?')) {
            return;
        }
        aiQueueMsg('<em>Se curăță...</em>');
        aiQueuePost('ai_suite_ai_queue_purge', { days: 14 })
            .done(function (res) {
                if (res && res.success) {
                    var d = res.data || {};
                    aiQueueMsg('<div class="ai-ok"><strong>Curățat</strong> – șterse: ' + (d.deleted || 0) + '</div>');
                    setTimeout(function(){ window.location.reload(); }, 350);
                } else {
                    aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + ((res && res.data && res.data.message) ? res.data.message : 'Unknown') + '</div>');
                }
            })
            .fail(function (xhr) {
                aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + (xhr.responseText || 'Request failed') + '</div>');
            });
    });

    $(document).on('click', '#ai-queue-install', function (e) {
        e.preventDefault();
        aiQueueMsg('<em>Se instalează...</em>');
        aiQueuePost('ai_suite_ai_queue_install', {})
            .done(function (res) {
                if (res && res.success) {
                    aiQueueMsg('<div class="ai-ok"><strong>OK</strong> – tabela este pregătită.</div>');
                    setTimeout(function(){ window.location.reload(); }, 450);
                } else {
                    aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + ((res && res.data && res.data.message) ? res.data.message : 'Unknown') + '</div>');
                }
            })
            .fail(function (xhr) {
                aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + (xhr.responseText || 'Request failed') + '</div>');
            });
    });

    $(document).on('click', '.ai-queue-retry', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        if (!id) return;
        aiQueueMsg('<em>Retry...</em>');
        aiQueuePost('ai_suite_ai_queue_retry', { id: id })
            .done(function (res) {
                if (res && res.success) {
                    aiQueueMsg('<div class="ai-ok"><strong>OK</strong> – retry setat.</div>');
                    setTimeout(function(){ window.location.reload(); }, 300);
                } else {
                    aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + ((res && res.data && res.data.message) ? res.data.message : 'Unknown') + '</div>');
                }
            })
            .fail(function (xhr) {
                aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + (xhr.responseText || 'Request failed') + '</div>');
            });
    });

    $(document).on('click', '.ai-queue-delete', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        if (!id) return;
        if (!confirm('Ștergi acest item din coadă?')) return;
        aiQueueMsg('<em>Se șterge...</em>');
        aiQueuePost('ai_suite_ai_queue_delete', { id: id })
            .done(function (res) {
                if (res && res.success) {
                    aiQueueMsg('<div class="ai-ok"><strong>Șters</strong></div>');
                    setTimeout(function(){ window.location.reload(); }, 250);
                } else {
                    aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + ((res && res.data && res.data.message) ? res.data.message : 'Unknown') + '</div>');
                }
            })
            .fail(function (xhr) {
                aiQueueMsg('<div class="ai-bad"><strong>Eroare:</strong> ' + (xhr.responseText || 'Request failed') + '</div>');
            });
    });

  // === Global search (v3.6.0) ===
  (function(){
    var $input = $('#ais-global-search');
    var $pop = $('#ais-global-search-pop');
    if (!$input.length || !$pop.length) return;

    var t = null;
    function closePop(){
      $pop.removeClass('is-open').attr('aria-hidden','true').empty();
    }
    function openPop(html){
      $pop.html(html).addClass('is-open').attr('aria-hidden','false');
    }
    function doSearch(q){
      $.post(AI_Suite_Admin.ajax, {
        action: 'ai_suite_global_search',
        nonce: AI_Suite_Admin.nonce,
        q: q
      }).done(function(resp){
        if (resp && resp.success && resp.data && resp.data.html){
          openPop(resp.data.html);
        } else {
          closePop();
        }
      }).fail(closePop);
    }

    $input.on('input', function(){
      var q = ($input.val() || '').trim();
      if (q.length < 2){ closePop(); return; }
      if (t) clearTimeout(t);
      t = setTimeout(function(){ doSearch(q); }, 220);
    });

    $(document).on('click', function(e){
      if ($(e.target).closest('.ais-search').length) return;
      closePop();
    });
    $input.on('keydown', function(e){
      if (e.key === 'Escape'){ closePop(); }
    });
  })();

  // === Run all bots (v3.6.0) ===
  (function(){
    $(document).on('click', '.ai-run-bots-all', function(e){
      e.preventDefault();
      var $btn = $(this);
      var $rows = $('button.ai-run-bot[data-bot]');
      if (!$rows.length) return;

      $btn.prop('disabled', true).text($btn.data('running') || 'Rulează...');

      var i = 0;
      function next(){
        if (i >= $rows.length){
          $btn.prop('disabled', false).text($btn.data('label') || 'Rulează toți boții activi');
          return;
        }
        var $b = $($rows.get(i));
        i++;
        // skip disabled bots (checkbox unchecked)
        var $chk = $b.closest('tr').find('input[type=checkbox][name=enabled]');
        if ($chk.length && !$chk.is(':checked')){ next(); return; }

        $b.trigger('click');
        setTimeout(next, 650);
      }
      next();
    });
  })();

})(jQuery);

// ---------------------------------------------------------------------------
// UI extras (Dashboard KPI, Copilot, Sidebar Menu)
// Wrap in its own IIFE so $ and CFG are always available in wp-admin.
// ---------------------------------------------------------------------------
;(function($){
  'use strict';
  var CFG = window.AI_Suite_Admin || {
    ajax: (window.AI_SUITE && AI_SUITE.ajax_url) ? AI_SUITE.ajax_url : (window.ajaxurl || ''),
    nonce: (window.AI_SUITE && AI_SUITE.nonce) ? AI_SUITE.nonce : ''
  };


    // Dashboard KPI (canvas bar chart)
    function drawBars(canvas, series){
        if(!canvas || !canvas.getContext){ return; }
        var ctx = canvas.getContext('2d');
        var w = canvas.width = canvas.clientWidth * (window.devicePixelRatio||1);
        var h = canvas.height = canvas.clientHeight * (window.devicePixelRatio||1);
        ctx.clearRect(0,0,w,h);

        var pad = 20*(window.devicePixelRatio||1);
        var innerW = w - pad*2;
        var innerH = h - pad*2;

        var max = 1;
        series.forEach(function(p){ if(p.c>max) max=p.c; });

        // background grid
        ctx.globalAlpha = 0.25;
        ctx.strokeStyle = '#ffffff';
        for(var i=0;i<=4;i++){
            var y = pad + (innerH*(i/4));
            ctx.beginPath(); ctx.moveTo(pad,y); ctx.lineTo(pad+innerW,y); ctx.stroke();
        }
        ctx.globalAlpha = 1;

        var n = series.length;
        var gap = 2*(window.devicePixelRatio||1);
        var barW = Math.max(2, (innerW - gap*(n-1)) / n);

        for(var j=0;j<n;j++){
            var v = series[j].c;
            var bh = (v/max)*innerH;
            var x = pad + j*(barW+gap);
            var y2 = pad + innerH - bh;
            // gradient
            var g = ctx.createLinearGradient(0,y2,0,y2+bh);
            g.addColorStop(0,'rgba(99,102,241,0.95)');
            g.addColorStop(1,'rgba(99,102,241,0.25)');
            ctx.fillStyle = g;
            ctx.fillRect(x,y2,barW,bh);
        }
    }

    function loadKpi(){
        var $chart = document.getElementById('ais-kpi-chart');
        if(!$chart){ return; }

        var $apps = document.getElementById('ais-kpi-apps');
        var $jobs = document.getElementById('ais-kpi-jobs');
        var $queue = document.getElementById('ais-kpi-queue');

        $.post(CFG.ajax || window.ajaxurl, { action:'ai_suite_kpi_data', nonce: CFG.nonce }, function(resp){
            if(resp && resp.success){
                var d = resp.data || {};
                if($apps) $apps.textContent = d.apps ?? '0';
                if($jobs) $jobs.textContent = d.jobs ?? '0';
                if($queue) $queue.textContent = d.queue ?? '0';
                if(d.trend && Array.isArray(d.trend)){
                    drawBars($chart, d.trend);
                }
                if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Dashboard actualizat', 'ok'); }
            } else {
                if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Nu pot încărca KPI', 'err'); }
            }
        }).fail(function(){
            if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Eroare KPI (AJAX)', 'err'); }
        });
    }

  $(document).on('click', '#ais-kpi-refresh', function(e){ e.preventDefault(); loadKpi(); });
  $(document).ready(function(){ loadKpi(); });


  // -------------------------
  // Copilot AI (Admin)
  // -------------------------
  function aisCopilotRender($box, chat){
    chat = Array.isArray(chat) ? chat : [];
    var html = '';
    chat.forEach(function(m){
      var role = (m && m.role) ? String(m.role) : '';
      var content = (m && m.content) ? String(m.content) : '';
      if(!content) return;
      var cls = (role === 'assistant') ? 'ais-copilot-msg ais-assistant' : 'ais-copilot-msg ais-user';
      var label = (role === 'assistant') ? 'AI' : 'Tu';
      html += '<div class="'+cls+'"><div class="ais-copilot-who">'+label+'</div><div class="ais-copilot-text">'+content.replace(/\n/g,'<br>')+'</div></div>';
    });
    $box.html(html || '<div class="ais-muted">Scrie un mesaj ca să începi.</div>');
    $box.scrollTop($box[0].scrollHeight);
  }

  function aisCopilotAjax(action, data){
    data = data || {};
    data.action = action;
    data.nonce  = CFG.nonce || '';
    return $.ajax({
      url: CFG.ajax || window.ajaxurl || '',
      method: 'POST',
      dataType: 'json',
      data: data
    });
  }

  $(document).on('click', '#ais-copilot-send', function(){
    var companyId = parseInt($('#ais-copilot-company').val() || '0', 10);
    var msg = ($('#ais-copilot-input').val() || '').trim();
    var includePii = $('#ais-copilot-include-pii').is(':checked') ? 1 : 0;
    if(!companyId){ return; }
    if(!msg){ return; }
    $('#ais-copilot-status').text('Se trimite…');
    aisCopilotAjax('ai_suite_copilot_send', { company_id: companyId, message: msg, include_pii: includePii })
      .done(function(r){
        if(r && r.success){
          $('#ais-copilot-input').val('');
          aisCopilotRender($('#ais-copilot-chat'), r.data && r.data.chat ? r.data.chat : []);
          $('#ais-copilot-status').text('Gata.');
        } else {
          $('#ais-copilot-status').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      })
      .fail(function(){
        $('#ais-copilot-status').text('Eroare la conexiune.');
      });
  });

  $(document).on('click', '#ais-copilot-clear', function(){
    var companyId = parseInt($('#ais-copilot-company').val() || '0', 10);
    if(!companyId){ return; }
    $('#ais-copilot-status').text('Se șterge…');
    aisCopilotAjax('ai_suite_copilot_clear', { company_id: companyId })
      .done(function(r){
        if(r && r.success){
          aisCopilotRender($('#ais-copilot-chat'), []);
          $('#ais-copilot-status').text('Conversația a fost ștearsă.');
        } else {
          $('#ais-copilot-status').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      });
  });

  $(document).on('change', '#ais-copilot-company', function(){
    var companyId = parseInt($(this).val() || '0', 10);
    if(!companyId){ return; }
    $('#ais-copilot-status').text('Se încarcă…');
    aisCopilotAjax('ai_suite_copilot_load', { company_id: companyId })
      .done(function(r){
        if(r && r.success){
          aisCopilotRender($('#ais-copilot-chat'), r.data && r.data.chat ? r.data.chat : []);
          $('#ais-copilot-status').text('');
        } else {
          $('#ais-copilot-status').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      });
  });

  // Auto-load on first render
  $(function(){
    if($('#ais-copilot-company').length){
      $('#ais-copilot-company').trigger('change');
    }
  });


  // -------------------------
  // Sidebar menu UX (v3.4+)
  // -------------------------
  $(function(){
    var $side = $('#ais-side');
    if(!$side.length){ return; }

    // Restore collapsed state
    try{
      if(window.localStorage && localStorage.getItem('aisuite_side_collapsed') === '1'){
        $side.addClass('is-collapsed');
      }
    }catch(e){}

    // Toggle sidebar collapse
    $(document).on('click', '#ais-side-toggle', function(e){
      e.preventDefault();
      $side.toggleClass('is-collapsed');
      try{
        if(window.localStorage){
          localStorage.setItem('aisuite_side_collapsed', $side.hasClass('is-collapsed') ? '1' : '0');
        }
      }catch(err){}
    });

    // Group open/close
    $(document).on('click', '.ais-group__head', function(e){
      e.preventDefault();
      var $g = $(this).closest('.ais-group');
      if(!$g.length){ return; }
      $g.toggleClass('is-closed');
      var gid = $g.data('group') || '';
      try{
        if(window.localStorage && gid){
          localStorage.setItem('aisuite_group_closed_'+gid, $g.hasClass('is-closed') ? '1' : '0');
        }
      }catch(err2){}
    });

    // Restore group states
    try{
      if(window.localStorage){
        $('.ais-group').each(function(){
          var $g = $(this);
          var gid = $g.data('group') || '';
          if(gid && localStorage.getItem('aisuite_group_closed_'+gid) === '1'){
            $g.addClass('is-closed');
          }
        });
      }
    }catch(err3){}

    // Mobile dropdown navigation
    $(document).on('change', '#ais-navselect', function(){
      var url = $(this).val();
      if(url){ window.location.href = url; }
    });

    // Search filter
    $(document).on('input', '#ais-menu-search', function(){
      var q = String($(this).val() || '').trim().toLowerCase();
      if(!q){
        $('.ais-navitem').show();
        $('.ais-group').show();
        return;
      }
      $('.ais-group').each(function(){
        var $group = $(this);
        var any = false;
        $group.find('.ais-navitem').each(function(){
          var $it = $(this);
          var label = String($it.data('label') || '').toLowerCase();
          var ok = label.indexOf(q) !== -1;
          $it.toggle(ok);
          if(ok){ any = true; }
        });
        $group.toggle(any);
      });
    });
  });


  // === Global search (v3.6.0) ===
  (function(){
    var $input = $('#ais-global-search');
    var $pop = $('#ais-global-search-pop');
    if (!$input.length || !$pop.length) return;

    var t = null;
    function closePop(){
      $pop.removeClass('is-open').attr('aria-hidden','true').empty();
    }
    function openPop(html){
      $pop.html(html).addClass('is-open').attr('aria-hidden','false');
    }
    function doSearch(q){
      $.post(AI_Suite_Admin.ajax, {
        action: 'ai_suite_global_search',
        nonce: AI_Suite_Admin.nonce,
        q: q
      }).done(function(resp){
        if (resp && resp.success && resp.data && resp.data.html){
          openPop(resp.data.html);
        } else {
          closePop();
        }
      }).fail(closePop);
    }

    $input.on('input', function(){
      var q = ($input.val() || '').trim();
      if (q.length < 2){ closePop(); return; }
      if (t) clearTimeout(t);
      t = setTimeout(function(){ doSearch(q); }, 220);
    });

    $(document).on('click', function(e){
      if ($(e.target).closest('.ais-search').length) return;
      closePop();
    });
    $input.on('keydown', function(e){
      if (e.key === 'Escape'){ closePop(); }
    });
  })();

  // === Run all bots (v3.6.0) ===
  (function(){
    $(document).on('click', '.ai-run-bots-all', function(e){
      e.preventDefault();
      var $btn = $(this);
      var $rows = $('button.ai-run-bot[data-bot]');
      if (!$rows.length) return;

      $btn.prop('disabled', true).text($btn.data('running') || 'Rulează...');

      var i = 0;
      function next(){
        if (i >= $rows.length){
          $btn.prop('disabled', false).text($btn.data('label') || 'Rulează toți boții activi');
          return;
        }
        var $b = $($rows.get(i));
        i++;
        // skip disabled bots (checkbox unchecked)
        var $chk = $b.closest('tr').find('input[type=checkbox][name=enabled]');
        if ($chk.length && !$chk.is(':checked')){ next(); return; }

        $b.trigger('click');
        setTimeout(next, 650);
      }
      next();
    });
  })();


  // === Auto-Repair AI (v3.8.0) ===
  (function(){
    function fmtIssues(issues){
      if(!issues || !issues.length){
        return '<div class="ais-muted">Nu au fost găsite probleme. ✅</div>';
      }
      var html = '<div class="ais-ar-issues">';
      issues.forEach(function(it){
        var sev = (it.severity || 'info').toLowerCase();
        var badge = sev === 'critical' ? 'CRITIC' : (sev === 'warning' ? 'WARN' : 'INFO');
        html += '<div class="ais-ar-issue">'
              + '<div class="ais-ar-issue-head">'
              + '  <span class="ais-ar-badge ais-ar-badge-'+sev+'">'+badge+'</span>'
              + '  <strong>'+ (it.title || it.id) +'</strong>'
              + '</div>'
              + '<div class="ais-ar-issue-body">'+ (it.details || '') +'</div>'
              + '<div class="ais-ar-issue-actions">'
              + (it.fixable ? ('<button type="button" class="button button-secondary ais-ar-fix" data-fix="'+it.fix+'">Aplică fix</button>') : '')
              + '</div>'
              + '</div>';
      });
      html += '</div>';
      return html;
    }

    function render(res){
      if(!res) return;
      if(res.status){
        var pill = $('#ais-ar-status-pill');
        var enabled = res.status.enabled ? 1 : 0;
        pill.attr('data-enabled', enabled ? '1' : '0').text(enabled ? 'Activ' : 'Oprit');
        $('#ais-ar-toggle').attr('data-enabled', enabled ? '1' : '0').text(enabled ? 'Dezactivează' : 'Activează');
        $('#ais-ar-last-run').text(res.status.last_run_human || '—');
        $('#ais-ar-last-ai').text(res.status.last_ai_human || '—');
      }
      if(res.issues){
        $('#ais-ar-issues').html(fmtIssues(res.issues));
      }
      if(typeof res.ai_text === 'string'){
        $('#ais-ar-ai').text(res.ai_text || '—');
      }
      if(typeof res.message === 'string' && res.message){
        $('#ais-ar-note').text(res.message);
      }
    }

    $(document).on('click', '#ais-ar-run', function(e){
      e.preventDefault();
      var $btn = $(this);
      $btn.prop('disabled', true).text('Rulează...');
      $('#ais-ar-note').text('Se rulează diagnosticul...');

      $.post(AISuite.ajaxUrl, {
        action: 'ai_suite_autorepair_run',
        nonce: AISuite.nonce,
        with_ai: $('#ais-ar-with-ai').is(':checked') ? 1 : 0
      }).done(function(r){
        if(r && r.success){
          render(r.data);
        } else {
          $('#ais-ar-note').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      }).fail(function(xhr){
        $('#ais-ar-note').text('Eroare AJAX ('+(xhr && xhr.status ? xhr.status : 'n/a')+').');
      }).always(function(){
        $btn.prop('disabled', false).text('Rulează diagnostic');
      });
    });

    $(document).on('click', '.ais-ar-fix', function(e){
      e.preventDefault();
      var $btn = $(this);
      var fix = $btn.data('fix');
      if(!fix) return;

      $btn.prop('disabled', true).text('Aplic...');
      $('#ais-ar-note').text('Aplic fix: '+fix+' ...');

      $.post(AISuite.ajaxUrl, {
        action: 'ai_suite_autorepair_apply',
        nonce: AISuite.nonce,
        fix: fix
      }).done(function(r){
        if(r && r.success){
          render(r.data);
        } else {
          $('#ais-ar-note').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      }).fail(function(xhr){
        $('#ais-ar-note').text('Eroare AJAX ('+(xhr && xhr.status ? xhr.status : 'n/a')+').');
      }).always(function(){
        $btn.prop('disabled', false).text('Aplică fix');
      });
    });

    $(document).on('click', '#ais-ar-toggle', function(e){
      e.preventDefault();
      var enabled = $(this).attr('data-enabled') === '1' ? 1 : 0;
      var next = enabled ? 0 : 1;
      var $btn = $(this);
      $btn.prop('disabled', true);
      $('#ais-ar-note').text('Se salvează...');

      $.post(AISuite.ajaxUrl, {
        action: 'ai_suite_autorepair_toggle',
        nonce: AISuite.nonce,
        enabled: next
      }).done(function(r){
        if(r && r.success){
          render(r.data);
        } else {
          $('#ais-ar-note').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      }).fail(function(xhr){
        $('#ais-ar-note').text('Eroare AJAX ('+(xhr && xhr.status ? xhr.status : 'n/a')+').');
      }).always(function(){
        $btn.prop('disabled', false);
      });
    });
  })();


  // ===========================
  // Auto-Patch AI (cod) - UI
  // ===========================
  (function(){
    function pretty(obj){
      try { return JSON.stringify(obj, null, 2); } catch(e){ return String(obj||''); }
    }

    function setButtons(st){
      var has = st && st.patch;
      $('#ais-ap-apply').prop('disabled', !(has && st.status !== 'applied'));
      $('#ais-ap-rollback').prop('disabled', !(st && (st.status === 'applied') && st.applied_results && st.applied_results.length));
    }

    function render(st){
      if(!st || !st.patch){
        $('#ais-ap-note').text('Nicio propunere de patch salvată.');
        $('#ais-ap-preview').text('Nicio propunere încă.');
        setButtons(st);
        return;
      }
      var meta = 'Status: ' + (st.status || '—') + ' | creat: ' + (st.created_at ? new Date(st.created_at*1000).toLocaleString() : '—');
      if(st.applied_at){ meta += ' | aplicat: ' + new Date(st.applied_at*1000).toLocaleString(); }
      if(st.rolled_back_at){ meta += ' | rollback: ' + new Date(st.rolled_back_at*1000).toLocaleString(); }
      $('#ais-ap-note').text(meta);
      $('#ais-ap-preview').text(pretty(st.patch));
      setButtons(st);
    }

    function load(){
      $.post(CFG.ajax, { action:'ai_suite_autopatch_status', nonce: CFG.nonce }, function(r){
        if(r && r.success){
          render(r.data || {});
        } else {
          $('#ais-ap-note').text('Eroare status.');
        }
      }).fail(function(xhr){
        $('#ais-ap-note').text('Eroare AJAX status ('+(xhr && xhr.status ? xhr.status : 'n/a')+').');
      });
    }

    $(document).on('click', '#ais-ap-refresh', function(e){
      e.preventDefault();
      load();
    });

    $(document).on('click', '#ais-ap-generate', function(e){
      e.preventDefault();
      var $btn = $(this);
      $btn.prop('disabled', true).text('Generez…');
      $('#ais-ap-note').text('Generez propunerea AI…');
      $.post(CFG.ajax, { action:'ai_suite_autopatch_generate', nonce: CFG.nonce }, function(r){
        if(r && r.success){
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Patch AI generat', 'ok'); }
          render(r.data || {});
        } else {
          var msg = (r && r.data && r.data.message) ? r.data.message : 'Eroare.';
          $('#ais-ap-note').text(msg);
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
        }
      }).fail(function(xhr){
        var msg = 'Eroare AJAX generate ('+(xhr && xhr.status ? xhr.status : 'n/a')+').';
        $('#ais-ap-note').text(msg);
        if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
      }).always(function(){
        $btn.prop('disabled', false).text('Generează fix AI');
      });
    });

    $(document).on('click', '#ais-ap-apply', function(e){
      e.preventDefault();
      var $btn = $(this);
      $btn.prop('disabled', true).text('Aplic…');
      $('#ais-ap-note').text('Aplic patch… (backup + sanity checks)');
      $.post(CFG.ajax, { action:'ai_suite_autopatch_apply', nonce: CFG.nonce }, function(r){
        if(r && r.success){
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Patch aplicat', 'ok'); }
          render(r.data || {});
        } else {
          var msg = (r && r.data && r.data.message) ? r.data.message : 'Eroare.';
          $('#ais-ap-note').text(msg);
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
        }
      }).fail(function(xhr){
        var msg = 'Eroare AJAX apply ('+(xhr && xhr.status ? xhr.status : 'n/a')+').';
        $('#ais-ap-note').text(msg);
        if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
      }).always(function(){
        $btn.prop('disabled', false).text('Aplică fix');
      });
    });

    $(document).on('click', '#ais-ap-rollback', function(e){
      e.preventDefault();
      var $btn = $(this);
      if(!confirm('Rollback la ultima modificare aplicată de Auto-Patch?')) return;
      $btn.prop('disabled', true).text('Rollback…');
      $('#ais-ap-note').text('Execut rollback…');
      $.post(CFG.ajax, { action:'ai_suite_autopatch_rollback', nonce: CFG.nonce }, function(r){
        if(r && r.success){
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast('Rollback OK', 'ok'); }
          render(r.data || {});
        } else {
          var msg = (r && r.data && r.data.message) ? r.data.message : 'Eroare.';
          $('#ais-ap-note').text(msg);
          if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
        }
      }).fail(function(xhr){
        var msg = 'Eroare AJAX rollback ('+(xhr && xhr.status ? xhr.status : 'n/a')+').';
        $('#ais-ap-note').text(msg);
        if(window.AISuiteUI && AISuiteUI.toast){ AISuiteUI.toast(msg, 'err'); }
      }).always(function(){
        $btn.prop('disabled', false).text('Rollback');
      });
    });

    // Auto-load when tab exists
    $(function(){
      if($('#ais-ap-preview').length){
        load();
      }
    });
  })();

})(jQuery);
