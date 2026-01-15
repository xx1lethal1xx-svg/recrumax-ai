
/*
 * AI Suite – Portal Premium JS (Company + Candidate)
 *
 * FIX: aliniază JS-ul cu markup-ul actual din portal-frontend.php.
 * Include: tabs, candidate search, shortlist, pipeline + Job Posting PRO.
 */
(function($){
  function esc(s){
    s = String(s === undefined ? '' : s);
    return s.replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c] || c);
    });
  }

  // expose helpers for other modules (Copilot etc.)
  window.AISuitePortalEsc = esc;

  // Toast + Diagnostics
  function ensureToastHost(){
    var $host = $('.ais-toast-host');
    if(!$host.length){
      $host = $('<div class="ais-toast-host" aria-live="polite"></div>');
      $('body').append($host);
    }
    return $host;
  }
  function toast(type, title, msg, extraHtml){
    var $host = ensureToastHost();
    var cls = (type === 'err' ? 'ais-toast-err' : (type === 'ok' ? 'ais-toast-ok' : 'ais-toast-info'));
    var $t = $('<div class="ais-toast '+cls+'"></div>');
    var h = '<div class="ais-toast-h"><strong>'+esc(title||'')+'</strong><button type="button" class="ais-toast-x" aria-label="Close">×</button></div>';
    h += '<div class="ais-toast-b">'+esc(msg||'')+'</div>';
    if(extraHtml){ h += '<div class="ais-toast-a">'+extraHtml+'</div>'; }
    $t.html(h);
    $host.append($t);
    $t.find('.ais-toast-x').on('click', function(){ $t.remove(); });
    setTimeout(function(){ $t.fadeOut(200,function(){ $(this).remove(); }); }, 9000);
    return $t;
  }


function downloadCsv(filename, csvText){
  try{
    var blob = new Blob([csvText], {type:'text/csv;charset=utf-8;'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    document.body.appendChild(a);
    a.click();
    setTimeout(function(){
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }, 0);
  }catch(e){
    if(window.console && console.error){ console.error(e); }
    toast('err','Export','Nu am putut descărca fișierul.');
  }
}

  function hasBillingTab(){
    try{ return $('.ais-portal .ais-tab[data-ais-tab="billing"]').length > 0; }catch(e){ return false; }
  }
  function gotoBillingTab(){
    try{
      var $b = $('.ais-portal .ais-tab[data-ais-tab="billing"]').first();
      if($b.length){ $b.trigger('click'); return true; }
    }catch(e){}
    return false;
  }
  function shouldDebug(){
    return !!(window.AISuitePortal && (AISuitePortal.debug || AISuitePortal.isAdmin));
  }
  function logPortalIssue(payload){
    // Fire-and-forget log to server (avoid loops)
    try{
      var url = (window.AISuitePortal && AISuitePortal.ajaxUrl) ? AISuitePortal.ajaxUrl : '/wp-admin/admin-ajax.php';
      var data = {
        action: 'ai_suite_portal_js_log',
        nonce: (window.AISuitePortal && AISuitePortal.nonce) ? AISuitePortal.nonce : '',
        payload: payload || {}
      };
      $.ajax({url:url, method:'POST', dataType:'json', data:data, timeout:8000});
    }catch(e){}
  }

  function ajax(action, data, opts){
    data = data || {};
    opts = opts || {};
    var attempt = opts.attempt || 0;
    var maxRetry = (opts.maxRetry === undefined) ? 1 : opts.maxRetry;
    data.action = action;
    data.nonce  = (window.AISuitePortal && AISuitePortal.nonce) ? AISuitePortal.nonce : '';
    // Admin Preview impersonation (server validates)
    if(window.AISuitePortal && AISuitePortal.asUser && AISuitePortal.asNonce){
      data.as_user = AISuitePortal.asUser;
      data.as_nonce = AISuitePortal.asNonce;
    }
    var url = (window.AISuitePortal && AISuitePortal.ajaxUrl) ? AISuitePortal.ajaxUrl : '/wp-admin/admin-ajax.php';
    var req = $.ajax({
      url: url,
      method: 'POST',
      dataType: 'json',
      data: data,
      timeout: (opts.timeout || 25000)
    });

    // Retry once on network/timeout/server errors (not on explicit JSON error responses)
    req.fail(function(xhr, textStatus, errorThrown){
      var status = xhr && xhr.status ? xhr.status : 0;
      var isRetryable = (attempt < maxRetry) && (textStatus === 'timeout' || status === 0 || status >= 500);
      if(isRetryable){
        var wait = 600 + (attempt * 800);
        if(shouldDebug()){
          console.warn('[AI Suite Portal] AJAX retry', {action:action, attempt:attempt+1, status:status, textStatus:textStatus});
        }
        setTimeout(function(){ ajax(action, data, {attempt:attempt+1, maxRetry:maxRetry, timeout:(opts.timeout||25000)}); }, wait);
      }else{
        // Surface a friendly toast + log
        var bodyMsg = '';
        try{
          if(xhr && xhr.responseJSON && xhr.responseJSON.message){ bodyMsg = String(xhr.responseJSON.message||''); }
        }catch(e){}
        // Special case: Upgrade required (402)
        if(status === 402){
          var upMsg = bodyMsg || 'Funcționalitate disponibilă doar pe planurile plătite.';
          var extraUp = '';
          if(hasBillingTab()){
            extraUp = '<button type="button" class="ais-btn ais-btn-sm ais-btn-primary ais-upgrade">Vezi abonamente</button> ' +
                      '<button type="button" class="ais-btn ais-btn-sm ais-btn-ghost ais-retry">Reîncearcă</button>';
          }else{
            extraUp = '<button type="button" class="ais-btn ais-btn-sm ais-btn-ghost ais-retry">Reîncearcă</button>';
          }
          var $tu = toast('info', 'Upgrade necesar', upMsg, extraUp);
          $tu.find('.ais-retry').on('click', function(){
            $(this).prop('disabled', true);
            ajax(action, data, {attempt:0, maxRetry:maxRetry, timeout:(opts.timeout||25000)});
          });
          $tu.find('.ais-upgrade').on('click', function(){
            gotoBillingTab();
          });
          logPortalIssue({
            companyId: (window.AISuitePortal && AISuitePortal.companyId) ? AISuitePortal.companyId : 0,
            candidateId: (window.AISuitePortal && AISuitePortal.candidateId) ? AISuitePortal.candidateId : 0,
            type: 'upgrade_required',
            action: action,
            status: status,
            url: url
          });
          return;
        }
var msg = 'Eroare AJAX ('+action+'): ' + (status ? 'HTTP '+status : (textStatus||'fail'));
        if(bodyMsg){ msg += ' — ' + bodyMsg; }
        var extra = '<button type="button" class="ais-btn ais-btn-sm ais-btn-ghost ais-retry">Reîncearcă</button>';
        var $t = toast('err', 'Portal', msg, extra);
        $t.find('.ais-retry').on('click', function(){
          $(this).prop('disabled', true);
          ajax(action, data, {attempt:0, maxRetry:maxRetry, timeout:(opts.timeout||25000)});
        });
        logPortalIssue({
          companyId: (window.AISuitePortal && AISuitePortal.companyId) ? AISuitePortal.companyId : 0,
          candidateId: (window.AISuitePortal && AISuitePortal.candidateId) ? AISuitePortal.candidateId : 0,
          type: 'ajax_fail',
          action: action,
          status: status,
          textStatus: textStatus,
          error: String(errorThrown||''),
          url: url
        });
      }
    });

    return req;
  }

  function setActiveTab($root, key){
    $root.find('.ais-tab').removeClass('is-active');
    $root.find('.ais-tab[data-ais-tab="'+key+'"]').addClass('is-active');
    $root.find('.ais-pane').removeClass('is-active');
    $root.find('.ais-pane[data-ais-pane="'+key+'"]').addClass('is-active');

    // Company portal tabs
    if(key === 'candidates') loadCandidates($root);
    if(key === 'shortlist') loadShortlist($root);
    if(key === 'pipeline') loadPipeline($root);
    if(key === 'ats_board') loadATSBoard($root);
    if(key === 'jobs') loadJobs($root);
    if(key === 'team') loadTeam($root);
    if(key === 'ats_settings') loadATSSettings($root);
    if(key === 'billing') loadBilling($root);

    // Shared comms tabs (company + candidate)
    if(key === 'messages') loadThreads($root);
    if(key === 'interviews') loadInterviews($root);
    if(key === 'activity') loadActivity($root);

    // Candidate portal tabs
    if(key === 'applications') loadCandidateApplications($root);
    if(key === 'overview') loadCandidateOverview($root);
  }

  function skeleton(msg){
    return '<div class="ais-skeleton">'+esc(msg || 'Se încarcă…')+'</div>';
  }

  // ---------------------------
  // Candidates
  // ---------------------------
  function renderCandidateCard(c){
    var btn = c.isShortlisted
      ? '<button type="button" class="ais-btn ais-btn-ghost" data-act="unshortlist" data-id="'+esc(c.id)+'">Scoate</button>'
      : '<button type="button" class="ais-btn ais-btn-primary" data-act="shortlist" data-id="'+esc(c.id)+'">Shortlist</button>';

    var cv = c.cvUrl
      ? '<a class="ais-link" href="'+esc(c.cvUrl)+'" target="_blank" rel="noopener">CV</a>'
      : '<span class="ais-muted">CV lipsă</span>';

    return (
      '<div class="ais-card" data-candidate="'+esc(c.id)+'">'
      + '<div class="ais-card-head ais-row ais-row-between">'
      + '  <div><div class="ais-card-title">'+esc(c.name || ('Candidat #'+c.id))+'</div>'
      + '       <div class="ais-muted">'+esc(c.location||'')+'</div></div>'
      + '  <div class="ais-actions">'+btn+'</div>'
      + '</div>'
      + '<div class="ais-card-meta">'
      + '  <div><span class="ais-muted">Email:</span> '+esc(c.email||'')+'</div>'
      + '  <div><span class="ais-muted">Telefon:</span> '+esc(c.phone||'')+'</div>'
      + '  <div><span class="ais-muted">Skills:</span> '+esc(c.skills||'')+'</div>'
      + '  <div>'+cv+'</div>'
      + '</div>'
      + '</div>'
    );
  }

  function loadCandidates($root){
    var $results = $root.find('#ais-cand-results');
    if(!$results.length) return;
    if($results.data('loaded')) return;
    $results.data('loaded', true);

    var $q    = $root.find('#ais-cand-q');
    var $loc  = $root.find('#ais-cand-loc');
    var $has  = $root.find('#ais-cand-has-cv');
    var $btn  = $root.find('#ais-cand-search');

    function run(){
      $results.html(skeleton('Căutare…'));
      ajax('ai_suite_candidate_search', {
        q: ($q.val()||'').trim(),
        loc: ($loc.val()||'').trim(),
        hasCv: $has.is(':checked') ? 1 : 0
      }).done(function(res){
        if(!res || !res.ok){
          $results.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
          return;
        }
        var list = res.candidates || [];
        if(!list.length){
          $results.html('<div class="ais-muted">Nu am găsit candidați.</div>');
          return;
        }
        var html = '<div class="ais-grid ais-grid-2">';
        list.forEach(function(c){ html += renderCandidateCard(c); });
        html += '</div>';
        $results.html(html);
      }).fail(function(){
        $results.html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
      });
    }

    $btn.on('click', function(){ run(); });
    $q.add($loc).on('keydown', function(e){
      if(e.key === 'Enter'){ e.preventDefault(); run(); }
    });

    $results.on('click', 'button[data-act]', function(){
      var act = $(this).data('act');
      var id  = $(this).data('id');
      if(!id) return;
      var action = (act === 'shortlist') ? 'ai_suite_shortlist_add' : 'ai_suite_shortlist_remove';
      ajax(action, { candidateId: id }).always(function(){ run(); });
    });

    run();
  }

  // ---------------------------
  // Shortlist
  // ---------------------------
  function loadShortlist($root){
    var $wrap = $root.find('#ais-shortlist');
    if(!$wrap.length) return;
    $wrap.html(skeleton('Se încarcă shortlist…'));

    ajax('ai_suite_shortlist_get', {}).done(function(res){
      if(!res || !res.ok){
        $wrap.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
        return;
      }
      var items = res.items || [];
      if(!items.length){
        $wrap.html('<div class="ais-muted">Shortlist gol.</div>');
        return;
      }
      var html = '<div class="ais-grid ais-grid-2">';
      items.forEach(function(c){
        html += '<div class="ais-card" data-candidate="'+esc(c.id)+'">';
        html += '  <div class="ais-card-head ais-row ais-row-between">';
        html += '    <div><div class="ais-card-title">'+esc(c.name||('Candidat #'+c.id))+'</div><div class="ais-muted">'+esc(c.location||'')+'</div></div>';
        html += '    <div class="ais-actions"><button type="button" class="ais-btn ais-btn-ghost" data-ais="remove" data-id="'+esc(c.id)+'">Scoate</button></div>';
        html += '  </div>';
        html += '  <div class="ais-card-meta">';
        html += '    <div><span class="ais-muted">Email:</span> '+esc(c.email||'')+'</div>';
        html += '    <div><span class="ais-muted">Telefon:</span> '+esc(c.phone||'')+'</div>';
        html += '    <div><span class="ais-muted">Skills:</span> '+esc(c.skills||'')+'</div>';
        html += '    <div class="ais-field"><label>Tags</label><input type="text" class="ais-input" data-ais="tags" value="'+esc(c.tags||'')+'" placeholder="ex: sudor, CNC, germană"/></div>';
        html += '    <div class="ais-field"><label>Notiță</label><textarea class="ais-textarea" data-ais="note" rows="3" placeholder="Observații interne...">'+esc(c.note||'')+'</textarea></div>';
        html += '    <div class="ais-row ais-row-between">';
        html += '      <button type="button" class="ais-btn ais-btn-primary" data-ais="save" data-id="'+esc(c.id)+'">Salvează</button>';
        html += '      <span class="ais-muted" data-ais="status"></span>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';
      });
      html += '</div>';
      $wrap.html(html);
    }).fail(function(){
      $wrap.html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
    });

    $wrap.off('click.aisShort').on('click.aisShort', 'button[data-ais]', function(){
      var $btn = $(this);
      var act = $btn.data('ais');
      var id  = $btn.data('id');
      if(act === 'remove'){
        ajax('ai_suite_shortlist_remove', { candidateId: id }).always(function(){ loadShortlist($root); });
        return;
      }
      if(act === 'save'){
        var $card = $btn.closest('.ais-card');
        var tags  = $card.find('[data-ais="tags"]').val();
        var note  = $card.find('[data-ais="note"]').val();
        var $st   = $card.find('[data-ais="status"]');
        $st.text('Se salvează…');
        ajax('ai_suite_shortlist_update', { candidateId: id, tags: tags, note: note }).done(function(res){
          $st.text(res && res.ok ? 'Salvat ✓' : 'Eroare');
          setTimeout(function(){ $st.text(''); }, 1500);
        }).fail(function(){ $st.text('Eroare'); });
      }
    });
  }

  // ---------------------------
  // Pipeline
  // ---------------------------
  function loadPipeline($root){
    var $wrap = $root.find('#ais-pipeline');
    var lastRes = null;
    var teamMap = {}; // userId => name
    var teamList = [];

    if(!$wrap.length) return;

    // Ensure job filter exists (optional)
    var $filterRow = $root.find('#ais-pipe-filter');
    if(!$filterRow.length){
      $filterRow = $('<div id="ais-pipe-filter" class="ais-filters" style="margin-bottom:10px;"></div>');
      $filterRow.append('<select id="ais-pipe-job" class="ais-select"><option value="">Toate joburile</option></select>');
$filterRow.append('<select id="ais-ats-filter-recruiter" class="ais-select" style="min-width:180px"><option value="">Toți recruiterii</option></select>');
$filterRow.append('<input id="ais-ats-filter-tag" class="ais-input" placeholder="Filtru tag (ex: #senior)" style="min-width:180px" />');
$filterRow.append('<label class="ais-row" style="gap:6px"><input type="checkbox" id="ais-ats-filter-mine" /> <span>Doar ale mele</span></label>');
$filterRow.append('<label class="ais-row" style="gap:6px"><input type="checkbox" id="ais-ats-group-recruiter" /> <span>Grupează pe recruiter</span></label>');
      $wrap.before($filterRow);
    }

    

// Team dropdown for recruiter filter + ATS filters
var _atsTeamMap = {};
function refreshTeamFilter(selected){
  ajax('ai_suite_pipeline_team_list', {}).done(function(res){
    if(!res || !res.ok) return;
    var members = res.members || res.team || [];
    _atsTeamMap = {};
    members.forEach(function(m){
      var id = String(m.userId || m.id || '');
      if(!id) return;
      _atsTeamMap[id] = m.name || m.email || ('User #'+id);
    });
    var $sel = $root.find('#ais-atsf-recruiter');
    if(!$sel.length) return;
    var cur = (selected !== undefined) ? String(selected||'') : String($sel.val()||'');
    var html = '<option value="">Toți recruiterii</option>';
    members.forEach(function(m){
      var id = String(m.userId || m.id || '');
      if(!id) return;
      var nm = m.name || m.email || ('User #'+id);
      html += '<option value="'+esc(id)+'"'+(id===cur?' selected':'')+'>'+esc(nm)+'</option>';
    });
    $sel.html(html);
  });
}

function getAtsFilterState(){
  return {
    recruiterId: String($root.find('#ais-atsf-recruiter').val()||''),
    tag: String(($root.find('#ais-atsf-tag').val()||'')).trim().toLowerCase(),
    mine: $root.find('#ais-atsf-mine').is(':checked'),
    group: $root.find('#ais-atsf-group').is(':checked'),
    swim: $root.find('#ais-atsf-swim').is(':checked')
  };
}

function normalizeTags(tags){
  if(!tags) return [];
  if(Array.isArray(tags)) return tags.map(function(t){ return String(t||'').trim(); }).filter(Boolean);
  if(typeof tags === 'string') return tags.split(',').map(function(t){ return String(t||'').trim(); }).filter(Boolean);
  return [];
}

function itemMatches(it, fs){
  if(!it) return false;
  var assigned = (it.assignedUserId!==undefined && it.assignedUserId!==null) ? String(it.assignedUserId) : '';
  if(fs.recruiterId && assigned !== fs.recruiterId) return false;
  if(fs.mine){
    var myId = (window.AISuitePortal && AISuitePortal.effectiveUserId) ? String(AISuitePortal.effectiveUserId) : '';
    if(!myId || assigned !== myId) return false;
  }
  if(fs.tag){
    var tags = normalizeTags(it.tags || it.tag || it.labels);
    var hay = tags.join(' ').toLowerCase();
    if(hay.indexOf(fs.tag) === -1) return false;
  }
  return true;
}

// Persist ATS filters per user (server-side)
var _atsPrefKey = 'ats_filters_v1';
function loadAtsPrefs(){
  ajax('ai_suite_portal_pref_get', { key: _atsPrefKey }).done(function(res){
    if(!res || !res.ok) return;
    var v = res.value || {};
    if(v.jobId !== undefined) $root.find('#ais-ats-job').val(String(v.jobId||''));
    if(v.recruiterId !== undefined) $root.find('#ais-atsf-recruiter').val(String(v.recruiterId||''));
    if(v.tag !== undefined) $root.find('#ais-atsf-tag').val(String(v.tag||''));
    if(v.mine !== undefined) $root.find('#ais-atsf-mine').prop('checked', !!v.mine);
    if(v.group !== undefined) $root.find('#ais-atsf-group').prop('checked', !!v.group);
    if(v.swim !== undefined) $root.find('#ais-atsf-swim').prop('checked', !!v.swim);
    refreshJobsDropdown(v.jobId);
    refreshTeamFilter(v.recruiterId);
  });
}

function saveAtsPrefs(){
  var fs = getAtsFilterState();
  ajax('ai_suite_portal_pref_set', {
    key: _atsPrefKey,
    value: {
      jobId: String($root.find('#ais-ats-job').val()||''),
      recruiterId: fs.recruiterId,
      tag: fs.tag,
      mine: !!fs.mine,
      group: !!fs.group,
      swim: !!fs.swim
    }
  });
}

function bindAtsFilterEvents(){
  $root.off('change.aisAtsF').on('change.aisAtsF', '#ais-ats-job, #ais-atsf-recruiter, #ais-atsf-mine, #ais-atsf-group, #ais-atsf-swim', function(){
    saveAtsPrefs();
    run();
  });
  $root.off('input.aisAtsF').on('input.aisAtsF', '#ais-atsf-tag', function(){
    saveAtsPrefs();
    run();
  });
  // clickable tags -> fill filter
  $root.off('click.aisAtsTag').on('click.aisAtsTag', '.ais-tag[data-ais-tag]', function(){
    var t = String($(this).data('ais-tag')||'');
    if(!t) return;
    $root.find('#ais-atsf-tag').val(t);
    saveAtsPrefs();
    run();
  });
}

function refreshJobsDropdown(selected){
      ajax('ai_suite_company_jobs_list', {}).done(function(res){
        if(!res || !res.ok) return;
        var jobs = res.jobs || [];
        var $sel = $root.find('#ais-pipe-job');
        if(!$sel.length) return;
        var cur = (selected !== undefined) ? String(selected||'') : String($sel.val()||'');
        var html = '<option value="">Toate joburile</option>';
        jobs.forEach(function(j){
          html += '<option value="'+esc(j.id)+'"'+(String(j.id)===cur?' selected':'')+'>'+esc(j.title || ('Job #'+j.id))+'</option>';
        });
        $sel.html(html);
      });
    }

    
    function refreshTeamDropdown(){
      // populate recruiter dropdown + reuse for bulk assign dropdown if present
      ajax('ai_suite_pipeline_team_list', {}).done(function(res){
        if(!res || !res.ok) return;
        teamList = res.team || [];
        teamMap = {};
        teamList.forEach(function(u){
          teamMap[String(u.id)] = u.name || ('User #'+u.id);
        });
        var $sel = $root.find('#ais-ats-filter-recruiter');
        if($sel.length){
          var cur = String($sel.val()||'');
          var html = '<option value="">Toți recruiterii</option>';
          teamList.forEach(function(u){
            html += '<option value="'+esc(u.id)+'"'+(String(u.id)===cur?' selected':'')+'>'+esc(u.name||('User #'+u.id))+'</option>';
          });
          $sel.html(html);
        }
      });
    }

    function getFilterState(){
      return {
        recruiterId: String($root.find('#ais-ats-filter-recruiter').val()||''),
        tag: String(($root.find('#ais-ats-filter-tag').val()||'')).trim().toLowerCase(),
        mine: $root.find('#ais-ats-filter-mine').is(':checked'),
        group: $root.find('#ais-ats-group-recruiter').is(':checked')
      };
    }

    function normalizeTags(tags){
      if(!tags) return [];
      if(Array.isArray(tags)) return tags.map(function(t){ return String(t||'').trim(); }).filter(Boolean);
      if(typeof tags === 'string') return tags.split(',').map(function(t){ return String(t||'').trim(); }).filter(Boolean);
      return [];
    }

    function itemMatches(it, fs){
      if(!it) return false;
      var assigned = (it.assignedUserId!==undefined && it.assignedUserId!==null) ? String(it.assignedUserId) : '';
      if(fs.recruiterId && assigned !== fs.recruiterId) return false;
      if(fs.mine){
        var myId = (window.AISuitePortal && AISuitePortal.effectiveUserId) ? String(AISuitePortal.effectiveUserId) : '';
        if(!myId || assigned !== myId) return false;
      }
      if(fs.tag){
        var tags = normalizeTags(it.tags || it.tag || it.labels);
        var hay = tags.join(' ').toLowerCase();
        if(hay.indexOf(fs.tag) === -1) return false;
      }
      return true;
    }

    function renderPipeline(res){
      if(!res || !res.ok){
        $wrap.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
        return;
      }
      var cols = res.columns || [];
      if(!cols.length){
        $wrap.html('<div class="ais-muted">Nu există aplicații încă.</div>');
        return;
      }
      var statuses = (window.AISuitePortal && AISuitePortal.statuses) ? AISuitePortal.statuses : {};
      var fs = getFilterState();

      function renderItems(items){
        var out = '';
        (items||[]).forEach(function(it){
          var cand = it.candidate || {};
          var tags = normalizeTags(it.tags);
          var assigned = (it.assignedUserId!==undefined && it.assignedUserId!==null) ? String(it.assignedUserId) : '';
          var assignedName = assigned ? (teamMap[assigned] || ('User #'+assigned)) : 'Neasignat';
          out += '<div class="ais-card ais-card-small" data-ais-app-card="1" data-app="'+esc(it.id)+'">';
          out += '  <div class="ais-row ais-row-between" style="gap:10px;align-items:flex-start;">';
          out += '    <label class="ais-row" style="gap:8px;align-items:center;">';
          out += '      <input type="checkbox" class="ais-ats-check" data-ais-ats-check="'+esc(it.id)+'" />';
          out += '      <span class="ais-muted">Select</span>';
          out += '    </label>';
          out += '    <div class="ais-row" style="gap:6px;flex-wrap:wrap;">';
          out += '      <button type="button" class="ais-btn ais-btn-soft" data-ais-ats-details="'+esc(it.id)+'">Detalii</button>';
          out += '      <button type="button" class="ais-btn ais-btn-soft" data-ais-ats-note="'+esc(it.id)+'">Notă</button>';
          out += '      <button type="button" class="ais-btn ais-btn-soft" data-ais-ats-history="'+esc(it.id)+'">Istoric</button>';
          out += '      <button type="button" class="ais-btn ais-btn-danger" data-ais-ats-reject="'+esc(it.id)+'">Respins</button>';
          out += '    </div>';
          out += '  </div>';

          out += '  <div class="ais-card-title">'+esc(cand.name || it.title || ('Aplicație #'+it.id))+'</div>';
          out += '  <div class="ais-card-meta">';
          out += '    <div class="ais-muted">Job: '+esc(it.jobTitle||'')+'</div>';
          out += '    <div class="ais-row ais-row-between" style="gap:8px;flex-wrap:wrap;align-items:center;">';
          out += '      <span class="ais-pill">'+esc(assignedName)+'</span>';
          if(tags.length){
            out += '      <span class="ais-tags">'+tags.slice(0,3).map(function(t){ return '<span class="ais-tag">'+esc(t)+'</span>'; }).join(' ')+'</span>';
          }
          out += '    </div>';
          out += '    <div class="ais-row ais-row-between" style="gap:8px;">';
          out += '      <select class="ais-select" data-ais="move" data-id="'+esc(it.id)+'">';
          Object.keys(statuses).forEach(function(k){
            var sel = (String(k) === String(it.status)) ? ' selected' : '';
            out += '<option value="'+esc(k)+'"'+sel+'>'+esc(statuses[k])+'</option>';
          });
          out += '      </select>';
          out += (cand.cvUrl ? ('<a class="ais-link" href="'+esc(cand.cvUrl)+'" target="_blank" rel="noopener">CV</a>') : '');
          out += '    </div>';
          out += '  </div>';
          out += '</div>';
        });
        return out;
      }

      var html = '<div class="ais-kanban ais-kanban-ats">';
      cols.forEach(function(col){
        var allItems = (col.items||[]).filter(function(it){ return itemMatches(it, fs); });
        html += '<div class="ais-col" data-col="'+esc(col.key)+'">';
        html += '  <div class="ais-col-head">'+esc(col.label||col.key)+' <span class="ais-badge">'+(allItems?allItems.length:0)+'</span></div>';
        html += '  <div class="ais-col-body">';
        if(fs.group){
          // group by assigned user
          var groups = {};
          allItems.forEach(function(it){
            var aid = (it.assignedUserId!==undefined && it.assignedUserId!==null) ? String(it.assignedUserId) : '0';
            if(!groups[aid]) groups[aid] = [];
            groups[aid].push(it);
          });
          var keys = Object.keys(groups);
          keys.sort(function(a,b){
            if(a==='0') return 1;
            if(b==='0') return -1;
            var an = (teamMap[a]||a).toLowerCase();
            var bn = (teamMap[b]||b).toLowerCase();
            return an.localeCompare(bn);
          });
          keys.forEach(function(aid){
            var name = (aid==='0') ? 'Neasignat' : (teamMap[aid] || ('User #'+aid));
            html += '<div class="ais-group">';
            html += '  <div class="ais-group-head">'+esc(name)+' <span class="ais-badge">'+groups[aid].length+'</span></div>';
            html += '  <div class="ais-group-body">'+renderItems(groups[aid])+'</div>';
            html += '</div>';
          });
        } else {
          html += renderItems(allItems);
        }
        html += '  </div>';
        html += '</div>';
      });
      html += '</div>';
      $wrap.html(html);
    }

function run(){
      $wrap.html(skeleton('Se încarcă pipeline…'));
      var jobId = ($root.find('#ais-pipe-job').val()||'').trim();
      ajax('ai_suite_pipeline_list', { jobId: jobId }).done(function(res){
        if(!res || !res.ok){
          $wrap.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
          return;
        }
        lastRes = res;
        renderPipeline(res);
        return;


        // snapshot in overview if present
        var $snap = $root.find('#ais-status-snapshot');
        if($snap.length){
          var counts = res.counts || {};
          var parts = [];
          Object.keys(statuses).forEach(function(k){
            var n = counts[k] ? counts[k] : 0;
            parts.push('<span class="ais-pill">'+esc(statuses[k])+': <strong>'+esc(n)+'</strong></span>');
          });
          $snap.html(parts.length ? parts.join(' ') : '<span class="ais-muted">—</span>');
        }
      }).fail(function(){
        $wrap.html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
      });
    }

    refreshJobsDropdown();
    refreshTeamDropdown();

    function rerenderOrRun(){
      if(lastRes){
        renderPipeline(lastRes);
      } else {
        run();
      }
    }

    $root.off('change.aisAtsFilter').on('change.aisAtsFilter', '#ais-ats-filter-recruiter, #ais-ats-filter-mine, #ais-ats-group-recruiter', function(){ rerenderOrRun(); });
    $root.off('input.aisAtsFilter').on('input.aisAtsFilter', '#ais-ats-filter-tag', function(){ rerenderOrRun(); });
    run();

    $root.off('change.aisPipeJob').on('change.aisPipeJob', '#ais-pipe-job', function(){ run(); });

    $wrap.off('change.aisPipeMove').on('change.aisPipeMove', 'select[data-ais="move"]', function(){
      var $sel = $(this);
      var id = $sel.data('id');
      var to = $sel.val();
      $sel.prop('disabled', true);
      ajax('ai_suite_pipeline_move', { applicationId: id, toStatus: to }).always(function(){
        $sel.prop('disabled', false);
      }).done(function(res){
        if(res && res.ok){ run(); }
      });
    });
  }



  // ---------------------------
  // ATS Board (Kanban drag & drop)
  // ---------------------------
  function loadATSBoard($root){
    var $wrap = $root.find('#ais-ats-board');
    if(!$wrap.length) return;

    // Job filter (reuse pipeline filter pattern)
    var $filterRow = $root.find('#ais-ats-filter');
    if(!$filterRow.length){
      $filterRow = $('<div id="ais-ats-filter" class="ais-filters" style="margin-bottom:10px;"></div>');
      $filterRow.append('<select id="ais-ats-job" class="ais-select"><option value="">Toate joburile</option></select>');
      $filterRow.append('<select id="ais-atsf-recruiter" class="ais-select" style="min-width:180px"><option value="">Toți recruiterii</option></select>');
      $filterRow.append('<input id="ais-atsf-tag" class="ais-input" placeholder="Filtru tag (ex: #senior)" style="min-width:180px" />');
      $filterRow.append('<label class="ais-row" style="gap:6px"><input type="checkbox" id="ais-atsf-mine" /> <span>Doar ale mele</span></label>');
      $filterRow.append('<label class="ais-row" style="gap:6px"><input type="checkbox" id="ais-atsf-group" /> <span>Grupează</span></label>');
      $filterRow.append('<label class="ais-row" style="gap:6px"><input type="checkbox" id="ais-atsf-swim" /> <span>Swimlanes</span></label>');
      $wrap.before($filterRow);
    }

    function refreshJobsDropdown(selected){
      ajax('ai_suite_company_jobs_list', {}).done(function(res){
        if(!res || !res.ok) return;
        var jobs = res.jobs || [];
        var $sel = $root.find('#ais-ats-job');
        if(!$sel.length) return;
        var cur = (selected !== undefined) ? String(selected||'') : String($sel.val()||'');
        var html = '<option value="">Toate joburile</option>';
        jobs.forEach(function(j){
          html += '<option value="'+esc(j.id)+'"'+(String(j.id)===cur?' selected':'')+'>'+esc(j.title || ('Job #'+j.id))+'</option>';
        });
        $sel.html(html);
      });
    }

    function bindDnD(){
      // Drag start
      $wrap.find('[data-ais-app-card]').attr('draggable','true')
        .off('dragstart.aisAts').on('dragstart.aisAts', function(ev){
          var id = $(this).data('app');
          try{ ev.originalEvent.dataTransfer.setData('text/plain', String(id)); }catch(e){}
          $(this).addClass('is-dragging');
        })
        .off('dragend.aisAts').on('dragend.aisAts', function(){
          $(this).removeClass('is-dragging');
          $wrap.find('.ais-col-body').removeClass('is-drop');
        });

      // Drop zones
      $wrap.find('.ais-col-body')
        .off('dragover.aisAts').on('dragover.aisAts', function(ev){
          ev.preventDefault();
          $(this).addClass('is-drop');
        })
        .off('dragleave.aisAts').on('dragleave.aisAts', function(){
          $(this).removeClass('is-drop');
        })
        .off('drop.aisAts').on('drop.aisAts', function(ev){
          ev.preventDefault();
          var to = $(this).closest('.ais-col').data('col');
          var id = '';
          try{ id = ev.originalEvent.dataTransfer.getData('text/plain'); }catch(e){}
          id = String(id||'').trim();
          $(this).removeClass('is-drop');
          if(!id || !to) return;

          toast('info', 'ATS', 'Se salvează mutarea…');
          ajax('ai_suite_pipeline_move', { applicationId: id, toStatus: to }).done(function(res){
            if(res && res.ok){
              toast('ok','ATS','Mutat ✓');
              run();
            } else {
              toast('err','ATS', (res && res.message) ? res.message : 'Eroare la salvare');
              run();
            }
          }).fail(function(){
            toast('err','ATS','Eroare la server (AJAX).');
            run();
          });
        });
    }

    function run(){
      $wrap.html(skeleton('Se încarcă ATS Board…'));
      var jobId = ($root.find('#ais-ats-job').val()||'').trim();
      ajax('ai_suite_pipeline_list', { jobId: jobId }).done(function(res){
        if(!res || !res.ok){
          $wrap.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
          return;
        }
        var cols = res.columns || [];
        if(!cols.length){
          $wrap.html('<div class="ais-muted">Nu există aplicații încă.</div>');
          return;
        }

        
var fs = getAtsFilterState();
// Apply item-level filters (recruiter/tag/mine)
cols.forEach(function(col){
  col.items = (col.items||[]).filter(function(it){ return itemMatches(it, fs); });
});

function renderCard(it){
  var cand = it.candidate || {};
  var tagsArr = normalizeTags(it.tags);
  var tags = tagsArr.length ? tagsArr.slice(0,6).map(function(t){
    return '<span class="ais-tag" data-ais-tag="'+esc(t)+'">'+esc(t)+'</span>';
  }).join('') : '';
  var assignedLabel = '';
  if(it.assignedUserId){
    var uid = String(it.assignedUserId);
    var nm = _atsTeamMap[uid] || ('User #'+uid);
    assignedLabel = '<span class="ais-pill">👤 '+esc(nm)+'</span>';
  } else {
    assignedLabel = '<span class="ais-pill">👤 Neasignat</span>';
  }
  var interview = it.interviewAt ? ('<span class="ais-pill">📅 '+esc(it.interviewAt)+'</span>') : '';

  var html = '';
  html += '<div class="ais-card ais-card-small ais-ats-card" data-ais-app-card="1" data-app="'+esc(it.id)+'">';
  html += '  <div class="ais-row ais-row-between" style="gap:8px; align-items:flex-start">';
  html += '    <label class="ais-row" style="gap:8px; align-items:center">';
  html += '      <input type="checkbox" class="ais-ats-sel" data-app="'+esc(it.id)+'" />';
  html += '      <div>'; 
  html += '        <div class="ais-card-title">'+esc(cand.name || it.title || ('Aplicație #'+it.id))+'</div>';
  html += '        <div class="ais-muted" style="font-size:12px">'+esc(it.jobTitle||'')+'</div>';
  html += '      </div>';
  html += '    </label>';
  html += '    <div class="ais-row" style="gap:6px; flex-wrap:wrap; justify-content:flex-end">';
  html += '      <button type="button" class="ais-btn ais-btn-sm ais-btn-ghost" data-ais-ats-detail="'+esc(it.id)+'">Detalii</button>';
  html += '      <button type="button" class="ais-btn ais-btn-sm ais-btn-ghost" data-ais-ats-note="'+esc(it.id)+'">Notă</button>';
  html += '      <button type="button" class="ais-btn ais-btn-sm ais-btn-ghost" data-ais-ats-history="'+esc(it.id)+'">Istoric</button>';
  html += '      <button type="button" class="ais-btn ais-btn-sm ais-btn-ghost" data-ais-ats-reject="'+esc(it.id)+'">Respinge</button>';
  html += '    </div>';
  html += '  </div>';
  html += '  <div class="ais-card-meta" style="margin-top:10px">';
  html += '    <div class="ais-row" style="gap:8px; flex-wrap:wrap">';
  html +=      (cand.cvUrl ? ('<a class="ais-link" href="'+esc(cand.cvUrl)+'" target="_blank" rel="noopener">CV</a>') : '<span class="ais-muted">Fără CV</span>');
  html +=      assignedLabel + interview;
  html += '    </div>';
  if(tags){ html += '    <div class="ais-row" style="gap:6px; flex-wrap:wrap; margin-top:8px">'+tags+'</div>'; }
  html += '  </div>';
  html += '</div>';
  return html;
}

function renderKanbanSimple(){
  var html = '<div class="ais-kanban">';
  cols.forEach(function(col){
    var items = (col.items||[]);
    html += '<div class="ais-col" data-col="'+esc(col.key)+'">';
    html += '  <div class="ais-col-head">'+esc(col.label||col.key)+' <span class="ais-badge">'+items.length+'</span></div>';
    html += '  <div class="ais-col-body" data-ais-drop="1">';
    items.forEach(function(it){ html += renderCard(it); });
    html += '  </div></div>';
  });
  html += '</div>';
  return html;
}

function renderKanbanGrouped(){
  var html = '<div class="ais-kanban ais-kanban-grouped">';
  cols.forEach(function(col){
    var items = (col.items||[]);
    var g = {};
    items.forEach(function(it){
      var uid = it.assignedUserId ? String(it.assignedUserId) : '0';
      if(!g[uid]) g[uid] = [];
      g[uid].push(it);
    });
    html += '<div class="ais-col" data-col="'+esc(col.key)+'">';
    html += '  <div class="ais-col-head">'+esc(col.label||col.key)+' <span class="ais-badge">'+items.length+'</span></div>';
    html += '  <div class="ais-col-body" data-ais-drop="1">';
    Object.keys(g).sort().forEach(function(uid){
      var nm = uid==='0' ? 'Neasignat' : (_atsTeamMap[uid] || ('User #'+uid));
      html += '<div class="ais-group-head">'+esc(nm)+' <span class="ais-badge">'+g[uid].length+'</span></div>';
      g[uid].forEach(function(it){ html += renderCard(it); });
    });
    html += '  </div></div>';
  });
  html += '</div>';
  return html;
}

function renderKanbanSwimlanes(){
  var laneMap = {};
  cols.forEach(function(col){
    (col.items||[]).forEach(function(it){
      var uid = it.assignedUserId ? String(it.assignedUserId) : '0';
      if(!laneMap[uid]) laneMap[uid] = { uid: uid, itemsByCol: {} };
      if(!laneMap[uid].itemsByCol[col.key]) laneMap[uid].itemsByCol[col.key] = [];
      laneMap[uid].itemsByCol[col.key].push(it);
    });
  });
  var uids = Object.keys(laneMap);
  if(!uids.length){
    return '<div class="ais-muted">Nu există aplicații pe filtrele selectate.</div>';
  }
  uids.sort(function(a,b){
    if(a==='0') return 1;
    if(b==='0') return -1;
    return a.localeCompare(b);
  });
  var html = '<div class="ais-kanban ais-kanban-swim">';
  uids.forEach(function(uid){
    var nm = uid==='0' ? 'Neasignat' : (_atsTeamMap[uid] || ('User #'+uid));
    var total = 0;
    cols.forEach(function(col){ total += (laneMap[uid].itemsByCol[col.key] ? laneMap[uid].itemsByCol[col.key].length : 0); });
    html += '<div class="ais-swimlane" data-uid="'+esc(uid)+'">';
    html += ' <div class="ais-swim-head">👤 '+esc(nm)+' <span class="ais-badge">'+total+'</span></div>';
    html += ' <div class="ais-swim-cols">';
    cols.forEach(function(col){
      var items = laneMap[uid].itemsByCol[col.key] || [];
      html += '<div class="ais-col" data-col="'+esc(col.key)+'">';
      html += '  <div class="ais-col-head">'+esc(col.label||col.key)+' <span class="ais-badge">'+items.length+'</span></div>';
      html += '  <div class="ais-col-body" data-ais-drop="1">';
      items.forEach(function(it){ html += renderCard(it); });
      html += '  </div></div>';
    });
    html += ' </div></div>';
  });
  html += '</div>';
  return html;
}

var html = '';
if(fs.swim){
  html = renderKanbanSwimlanes();
} else if(fs.group){
  html = renderKanbanGrouped();
} else {
  html = renderKanbanSimple();
}
$wrap.html(html);
        // Bulk statuses dropdown
        try{
          var $dd = $root.find('#ais-ats-bulk-status');
          if($dd.length){
            var s = (window.AISuitePortal && AISuitePortal.statuses) ? AISuitePortal.statuses : {};
            var opts = '';
            Object.keys(s).forEach(function(k){ opts += '<option value="'+esc(k)+'">'+esc(s[k])+'</option>'; });
            $dd.html(opts);
          }
        }catch(e){}
        bindATSBulkActions($root);
        bindDnD();
      }).fail(function(){
        $wrap.html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
      });
    }

    refreshJobsDropdown();
    refreshTeamFilter();
    bindAtsFilterEvents();
    loadAtsPrefs();
    run();
  }

  // ---------------------------
  // Jobs (Company Portal) – PRO
  // ---------------------------
  function jobModalHtml(job){
    job = job || {};
    var title = job.title || '';
    var content = job.content || '';
    var dept = job.department || '';
    var loc = job.location || '';
    var salaryMin = job.salaryMin || '';
    var salaryMax = job.salaryMax || '';
    var type = job.employmentType || '';
    var status = job.status || 'draft';

    return ''+
      '<div class="ais-modal-overlay" data-ais-modal="1">' +
      ' <div class="ais-modal">' +
      '  <div class="ais-modal-head">' +
      '    <div class="ais-modal-title">'+esc(job.id ? 'Editează job' : 'Job nou')+'</div>' +
      '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-close=\"1\">✕</button>' +
      '  </div>' +
      '  <div class="ais-modal-body">' +
      '   <div class="ais-field"><label>Titlu</label><input class="ais-input" name="title" value="'+esc(title)+'" placeholder="ex: Vopsitor auto, Sudor TIG..." /></div>' +
      '   <div class="ais-grid ais-grid-2">' +
      '     <div class="ais-field"><label>Departament</label><input class="ais-input" name="department" value="'+esc(dept)+'" placeholder="ex: Auto / Construcții" /></div>' +
      '     <div class="ais-field"><label>Locație</label><input class="ais-input" name="location" value="'+esc(loc)+'" placeholder="ex: Olanda / Amsterdam" /></div>' +
      '   </div>' +
      '   <div class="ais-grid ais-grid-2">' +
      '     <div class="ais-field"><label>Salariu minim</label><input class="ais-input" name="salaryMin" value="'+esc(salaryMin)+'" placeholder="ex: 2500" /></div>' +
      '     <div class="ais-field"><label>Salariu maxim</label><input class="ais-input" name="salaryMax" value="'+esc(salaryMax)+'" placeholder="ex: 3500" /></div>' +
      '   </div>' +
      '   <div class="ais-grid ais-grid-2">' +
      '     <div class="ais-field"><label>Tip</label><input class="ais-input" name="employmentType" value="'+esc(type)+'" placeholder="ex: Full-time / Contract" /></div>' +
      '     <div class="ais-field"><label>Status</label>' +
      '       <select class="ais-select" name="status">' +
      '         <option value="draft"'+(status==='draft'?' selected':'')+'>Draft</option>' +
      '         <option value="publish"'+(status==='publish'?' selected':'')+'>Publicat</option>' +
      '       </select>' +
      '     </div>' +
      '   </div>' +
      '   <div class="ais-field"><label>Descriere</label><textarea class="ais-textarea" name="content" rows="8" placeholder="Descriere job, cerințe, beneficii...">'+esc(content)+'</textarea></div>' +
      '   <div class="ais-row ais-row-between" style="margin-top:12px;">' +
      '     <div class="ais-muted" data-ais-msg></div>' +
      '     <div class="ais-row" style="gap:8px;">' +
      '       <button type="button" class="ais-btn ais-btn-ghost" data-ais-close="1">Anulează</button>' +
      '       <button type="button" class="ais-btn ais-btn-primary" data-ais-save="1">'+esc(job.id ? 'Salvează' : 'Creează')+'</button>' +
      '     </div>' +
      '   </div>' +
      '  </div>' +
      ' </div>' +
      '</div>';
  }

  function openJobModal($root, job){
    var $m = $(jobModalHtml(job));
    $('body').append($m);

    function close(){ $m.remove(); }

    $m.on('click', function(e){
      if($(e.target).is('.ais-modal-overlay')) close();
    });
    $m.on('click', '[data-ais-close]', function(){ close(); });

    $m.on('click', '[data-ais-save]', function(){
      var payload = {
        jobId: job && job.id ? job.id : 0,
        title: $m.find('[name="title"]').val(),
        content: $m.find('[name="content"]').val(),
        department: $m.find('[name="department"]').val(),
        location: $m.find('[name="location"]').val(),
        salaryMin: $m.find('[name="salaryMin"]').val(),
        salaryMax: $m.find('[name="salaryMax"]').val(),
        employmentType: $m.find('[name="employmentType"]').val(),
        status: $m.find('[name="status"]').val()
      };
      $m.find('[data-ais-msg]').text('Se salvează…');
      ajax('ai_suite_company_job_save', payload).done(function(res){
        if(res && res.ok){
          $m.find('[data-ais-msg]').text('Salvat ✓');
          setTimeout(function(){ close(); loadJobs($root, true); }, 350);
        } else {
          $m.find('[data-ais-msg]').text((res && res.message) ? res.message : 'Eroare');
        }
      }).fail(function(){
        $m.find('[data-ais-msg]').text('Eroare la server (AJAX).');
      });
    });
  }

  function renderJobsList(jobs, promoCredits, promoDays){
    promoCredits = parseInt(promoCredits,10); if(isNaN(promoCredits)) promoCredits = 0;
    promoDays = parseInt(promoDays,10); if(isNaN(promoDays) || promoDays<1) promoDays = 7;
      if(isNaN(promoAllowance) || promoAllowance<0) promoAllowance = 0;
    if(!jobs || !jobs.length){
      return '<div class="ais-muted">Nu ai joburi încă. Apasă „Job nou”.</div>';
    }
    var html = '<div class="ais-table">';
    html += '<div class="ais-table-head"><div>Titlu</div><div>Status</div><div>Aplicații</div><div>Acțiuni</div></div>';
    jobs.forEach(function(j){
      var now = Math.floor(Date.now()/1000);
      var featuredUntil = j && j.featuredUntil ? parseInt(j.featuredUntil,10) : 0;
      var isFeatured = featuredUntil && featuredUntil > now;
      if(isNaN(featuredUntil)) featuredUntil = 0;
      var featuredLabel = '';
      if(isFeatured){
        var d = new Date(featuredUntil*1000);
        featuredLabel = ' <span class="ais-pill" title="Promovat">⭐ Featured până la '+d.toLocaleDateString()+'</span>';
      }
      var st = '<span class="ais-pill">'+esc(j.status||'')+'</span>';
      var apps = '<span class="ais-badge">'+esc(j.applications||0)+'</span>';
      html += '<div class="ais-table-row" data-job="'+esc(j.id)+'">';
      html += '  <div><strong>'+esc(j.title||('Job #'+j.id))+'</strong>'+featuredLabel+'<div class="ais-muted">'+esc((j.department||'') + (j.location?(' · '+j.location):''))+'</div></div>';
      html += '  <div>'+st+'</div>';
      html += '  <div>'+apps+'</div>';
      html += '  <div class="ais-row" style="gap:8px; flex-wrap:wrap;">';
      html += '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-job="edit" data-id="'+esc(j.id)+'">Editează</button>';
      html += '    <button type="button" class="ais-btn" data-ais-job="toggle" data-to="'+esc(j.status==='publish'?'draft':'publish')+'" data-id="'+esc(j.id)+'">'+(j.status==='publish'?'Pune Draft':'Publică')+'</button>';
      html += '    <a class="ais-btn ais-btn-ghost" href="'+esc(j.permalink||'#')+'" target="_blank" rel="noopener">Vezi</a>';
      var canPromote = (promoCredits && promoCredits>0) || isFeatured; // allow extend if already featured
      var promoText = isFeatured ? ('Extinde '+(promoDays||7)+' zile') : ('Promovează '+(promoDays||7)+' zile');
      html += '    <button type="button" class="ais-btn" data-ais-job="promote" data-id="'+esc(j.id)+'" '+(canPromote?'':'disabled title="Nu ai credite"')+'>'+esc(promoText)+'</button>';
      html += '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-job="delete" data-id="'+esc(j.id)+'">Șterge</button>';
      html += '  </div>';
      html += '</div>';
    });
    html += '</div>';
    return html;
  }

  function loadJobs($root, force){
    var $pane = $root.find('.ais-pane[data-ais-pane="jobs"]');
    if(!$pane.length) return;
    var $list = $pane.find('#ais-jobs-list');
    if(!$list.length){
      // Inject minimal UI if missing (compat for older markup)
      $pane.append('<div class="ais-card"><div class="ais-row ais-row-between"><h3 class="ais-card-title">Joburile companiei</h3><button type="button" class="ais-btn ais-btn-primary" id="ais-job-new">Job nou</button></div><div id="ais-jobs-list"></div></div>');
      $list = $pane.find('#ais-jobs-list');
    }
    if($list.data('loaded') && !force) return;
    $list.data('loaded', true);
    $list.html(skeleton('Se încarcă joburile…'));

    ajax('ai_suite_company_jobs_list', {}).done(function(res){
      if(!res || !res.ok){
        $list.html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>');
        return;
      }
      var promoCredits = (res && typeof res.promo_credits !== 'undefined') ? parseInt(res.promo_credits,10) : 0;
      var promoAllowance = (res && typeof res.promo_monthly_allowance !== 'undefined') ? parseInt(res.promo_monthly_allowance,10) : 0;
      var promoDays = (res && typeof res.promo_days !== 'undefined') ? parseInt(res.promo_days,10) : 7;
      if(isNaN(promoCredits)) promoCredits = 0;
      if(isNaN(promoDays) || promoDays<1) promoDays = 7;
      $root.data('aisPromoCredits', promoCredits);
      $root.data('aisPromoDays', promoDays);
      $root.data('aisPromoAllowance', promoAllowance);
      var $a = $root.find('#ais-promo-allowance');
      if($a.length){ $a.text(String(isNaN(promoAllowance)?0:promoAllowance)); }
      var $c = $root.find('#ais-promo-credits');
      if($c.length){ $c.text(String(promoCredits)); }
      $list.html(renderJobsList(res.jobs || [], promoCredits, promoDays));
    }).fail(function(){
      $list.html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
    });
  }

  // ---------- Communications: Threads / Messages ----------
  function renderThreads($root, threads){
    var $box = $root.find('[data-ais-threads]');
    if(!$box.length) return;

    if(!threads || !threads.length){
      $box.html('<div class="ais-muted">—</div>');
      return;
    }

    var html = '';
    threads.forEach(function(t){
      var active = ($root.data('aisApp') && parseInt($root.data('aisApp'),10) === parseInt(t.application_id,10)) ? ' is-active' : '';
      html += '<button type="button" class="ais-thread'+active+'" data-ais-thread="'+esc(t.application_id)+'">' +
                '<div class="t1"><strong>'+esc(t.job_title)+'</strong></div>' +
                '<div class="t2">'+esc(t.other)+' • <span class="ais-pill">'+esc(t.status || '—')+'</span></div>' +
                (t.last_message ? '<div class="t3">'+esc(t.last_message)+'</div>' : '<div class="t3 ais-muted">Fără mesaje încă</div>') +
              '</button>';
    });
    $box.html(html);
  }

  function renderMessages($root, messages){
    var $chat = $root.find('[data-ais-chat]');
    if(!$chat.length) return;

    if(!messages || !messages.length){
      $chat.html('<div class="ais-muted">Niciun mesaj încă.</div>');
      return;
    }

    var isCompany = !!(window.AISuitePortal && AISuitePortal.isCompany);
    var meRole = isCompany ? 'company' : 'candidate';

    var html = '';
    messages.forEach(function(m){
      var cls = (m.from === meRole) ? ' me' : ' them';
      html += '<div class="ais-chat-msg'+cls+'"><div class="b">'+(m.text || '')+'</div></div>';
    });
    $chat.html(html);
    $chat.scrollTop($chat.prop('scrollHeight'));
  }

  function loadThreads($root){
    ajax('ai_suite_threads_list', {}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      renderThreads($root, res.threads || []);
      // auto-select first thread if none selected
      if(!$root.data('aisApp') && res.threads && res.threads.length){
        $root.data('aisApp', parseInt(res.threads[0].application_id,10));
        loadThread($root, parseInt(res.threads[0].application_id,10));
        renderThreads($root, res.threads || []);
      }
      // candidate KPIs
      if($root.data('portal') === 'candidate'){
        updateCandidateKpis($root, res.threads || []);
      }
    });
  }

  function loadThread($root, appId){
    if(!appId) return;
    ajax('ai_suite_thread_get', {application_id: appId}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      renderMessages($root, res.messages || []);
    });
  }

  function sendMessage($root){
    var appId = parseInt($root.data('aisApp') || 0, 10);
    var $input = $root.find('[data-ais-chat-input]');
    var text = $input.val();
    if(!appId){ toast('Selectează o conversație.'); return; }
    if(!text || !text.trim()){ toast('Scrie un mesaj.'); return; }

    ajax('ai_suite_message_send', {application_id: appId, text: text}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      $input.val('');
      // refresh
      loadThread($root, appId);
      loadThreads($root);
    });
  }

  // ---------- Interviews ----------
  function fmtTs(ts){
    if(!ts) return '—';
    try{
      var d = new Date(ts * 1000);
      return d.toLocaleString();
    }catch(e){ return '—'; }
  }

  function renderInterviews($root, items){
    var $box = $root.find('[data-ais-interviews]');
    if(!$box.length) return;

    if(!items || !items.length){
      $box.html('<div class="ais-muted">—</div>');
      return;
    }

    var isCompany = !!(window.AISuitePortal && AISuitePortal.isCompany);

    var html = '<div class="ais-table-wrap"><table class="ais-table"><thead><tr>' +
      '<th>Job</th><th>Data</th><th>Durată</th><th>Status</th><th>Locație</th><th></th></tr></thead><tbody>';
    items.forEach(function(it){
      html += '<tr>' +
        '<td>'+esc(it.job_title || '—')+'</td>' +
        '<td>'+esc(fmtTs(it.scheduled_at))+'</td>' +
        '<td>'+esc((it.duration || 0) + ' min')+'</td>' +
        '<td><span class="ais-pill">'+esc(it.status || '—')+'</span></td>' +
        '<td>'+ (it.location ? '<a target="_blank" rel="noopener" href="'+esc(it.location)+'">'+esc(it.location)+'</a>' : '<span class="ais-muted">—</span>') + '</td>' +
        '<td>';
      if(!isCompany){
        html += '<button type="button" class="ais-btn ais-btn-ghost" data-ais-interview-status="'+esc(it.id)+'" data-status="confirmed">Confirm</button> ' +
                '<button type="button" class="ais-btn ais-btn-ghost" data-ais-interview-status="'+esc(it.id)+'" data-status="declined">Refuz</button>';
      } else {
        html += '<button type="button" class="ais-btn ais-btn-ghost" data-ais-interview-status="'+esc(it.id)+'" data-status="cancelled">Anulează</button>';
      }
      html += '</td></tr>';
    });
    html += '</tbody></table></div>';
    $box.html(html);
  }

  function loadInterviews($root){
    ajax('ai_suite_interviews_list', {}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      renderInterviews($root, res.items || []);
      if($root.data('portal') === 'candidate'){
        updateCandidateKpis($root, null, res.items || []);
      }
    });
  }

  function createInterview($root){
    var isCompany = !!(window.AISuitePortal && AISuitePortal.isCompany);
    if(!isCompany){ return; }

    var appId = parseInt($root.data('aisApp') || 0, 10);
    if(!appId){ toast('Selectează o aplicație din Mesaje.'); return; }

    var $dt = $root.find('[data-ais-interview-dt]');
    var $dur = $root.find('[data-ais-interview-duration]');
    var $loc = $root.find('[data-ais-interview-location]');

    var dtVal = $dt.val();
    if(!dtVal){ toast('Alege data/ora.'); return; }

    var ts = Math.floor(new Date(dtVal).getTime()/1000);
    var duration = parseInt($dur.val() || 30, 10);
    var location = $loc.val() || '';

    ajax('ai_suite_interview_create', {application_id: appId, scheduled_at: ts, duration: duration, location: location}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      toast('Interviu programat.');
      loadInterviews($root);
      loadThreads($root);
    });
  }

  function updateInterviewStatus($root, id, status){
    ajax('ai_suite_interview_update_status', {id: id, status: status}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      toast('Actualizat.');
      loadInterviews($root);
    });
  }

  // ---------- Activity ----------
  function renderActivity($root, items){
    var $box = $root.find('[data-ais-activity]');
    if(!$box.length) return;

    if(!items || !items.length){
      $box.html('<div class="ais-muted">—</div>');
      return;
    }

    var html = '<div class="ais-activity">';
    items.forEach(function(it){
      html += '<div class="ais-activity-row">' +
        '<div class="t">'+esc(fmtTs(it.ts))+'</div>' +
        '<div class="a"><span class="ais-pill">'+esc(it.action)+'</span></div>' +
        '<div class="d">'+ (it.details ? '<code>'+esc(JSON.stringify(it.details))+'</code>' : '<span class="ais-muted">—</span>') + '</div>' +
      '</div>';
    });
    html += '</div>';
    $box.html(html);
  }

  function loadActivity($root){
    ajax('ai_suite_activity_list', {}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      renderActivity($root, res.items || []);
    });
  }

  // ---------- Candidate: Applications ----------
  function renderCandidateApps($root, items){
    var $box = $root.find('[data-ais-cand-apps]');
    if(!$box.length) return;

    if(!items || !items.length){
      $box.html('<div class="ais-muted">Nu ai aplicații încă.</div>');
      return;
    }

    var html = '<div class="ais-table-wrap"><table class="ais-table"><thead><tr>' +
      '<th>Job</th><th>Companie</th><th>Status</th><th>Scor AI</th><th>Data</th><th></th></tr></thead><tbody>';
    items.forEach(function(it){
      html += '<tr>' +
        '<td><strong>'+esc(it.job_title || '—')+'</strong></td>' +
        '<td>'+esc(it.company || '—')+'</td>' +
        '<td><span class="ais-pill">'+esc(it.status || '—')+'</span></td>' +
        '<td>'+ (it.score === null || typeof it.score === 'undefined' ? '<span class="ais-muted">—</span>' : '<strong>'+esc(it.score)+'</strong>') + '</td>' +
        '<td>'+esc(fmtTs(it.created))+'</td>' +
        '<td><button type="button" class="ais-btn ais-btn-ghost" data-ais-open-thread="'+esc(it.application_id)+'">Mesaje</button></td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
    $box.html(html);
  }

  function loadCandidateApplications($root){
    ajax('ai_suite_candidate_applications_list', {}, function(res){
      if(!res || !res.ok){ toast((res && res.message) ? res.message : 'Eroare'); return; }
      renderCandidateApps($root, res.items || []);
    });
  }

  function updateCandidateKpis($root, threads, interviews){
    // threads optional
    var $kApps = $root.find('[data-ais-kpi="apps"]');
    var $kMsg = $root.find('[data-ais-kpi="messages"]');
    var $kInt = $root.find('[data-ais-kpi="interviews"]');
    var $kStatus = $root.find('[data-ais-kpi="status"]');
    if(!$kApps.length) return;

    if(threads){
      $kApps.text(threads.length);
      // approximate messages as number of threads with last_message
      var m = 0;
      threads.forEach(function(t){ if(t.last_message) m++; });
      $kMsg.text(m);
      var st = threads[0] ? (threads[0].status || '—') : '—';
      $kStatus.text(st);
    }
    if(interviews){
      $kInt.text(interviews.length);
    }
  }

  function loadCandidateOverview($root){
    // lightweight: load threads + interviews and update KPIs
    if($root.data('portal') !== 'candidate') return;
    loadThreads($root);
    loadInterviews($root);
  }



  // ---------------------------
  // Init
  // ---------------------------
  function initCompanyPortal($root){
    // tabs click
    $root.on('click', '.ais-tab', function(){
      var key = $(this).data('ais-tab');
      if(!key) return;
      setActiveTab($root, key);
    });

    // quick open tab buttons
    $root.on('click', '[data-ais-open-tab]', function(){
      var key = $(this).data('ais-open-tab');
      if(!key) return;
      setActiveTab($root, key);
    });

    // default tab
    var $active = $root.find('.ais-tab.is-active').first();
    var firstKey = $active.length ? $active.data('ais-tab') : 'overview';
    setActiveTab($root, firstKey);

// exports (CSV) – server-side gated via 402
$root.on('click', '#ais-export-shortlist', function(){
  ajax('ai_suite_export_shortlist_csv', {}).done(function(res){
    if(!res || !res.ok){ return; }
    downloadCsv(res.filename || 'shortlist.csv', res.csv || '');
  });
});
$root.on('click', '#ais-export-pipeline', function(){
  ajax('ai_suite_export_pipeline_csv', {}).done(function(res){
    if(!res || !res.ok){ return; }
    downloadCsv(res.filename || 'pipeline.csv', res.csv || '');
  });
});


    // jobs: new job
    $root.on('click', '#ais-job-new', function(){
      openJobModal($root, null);
    });

    // jobs actions
    $root.on('click', '[data-ais-job]', function(){
      var act = $(this).data('ais-job');
      var id  = $(this).data('id');
      if(!id) return;
      if(act === 'edit'){
        ajax('ai_suite_company_job_get', { jobId: id }).done(function(res){
          if(res && res.ok){ openJobModal($root, res.job || {id:id}); }
        });
        return;
      }
      if(act === 'toggle'){
        var to = $(this).data('to');
        ajax('ai_suite_company_job_toggle_status', { jobId: id, status: to }).always(function(){
          loadJobs($root, true);
        });
        return;
      }
      if(act === 'promote'){
        ajax('ai_suite_company_job_promote', { jobId: id }).done(function(res){
          if(res && res.ok){
            if(typeof res.promo_credits !== 'undefined'){
              var pc = parseInt(res.promo_credits,10); if(isNaN(pc)) pc = 0;
              $root.data('aisPromoCredits', pc);
              var $c = $root.find('#ais-promo-credits'); if($c.length){ $c.text(String(pc)); }
              if(typeof res.promo_monthly_allowance !== 'undefined'){
                var pa = parseInt(res.promo_monthly_allowance,10); if(isNaN(pa)||pa<0) pa=0;
                var $a = $root.find('#ais-promo-allowance'); if($a.length){ $a.text(String(pa)); }
                $root.data('aisPromoAllowance', pa);
              }
            }
          }
        }).always(function(){
          loadJobs($root, true);
        });
        return;
      }
      if(act === 'delete'){
        if(!window.confirm('Sigur ștergi jobul?')) return;
        ajax('ai_suite_company_job_delete', { jobId: id }).always(function(){
          loadJobs($root, true);
        });
        return;
      }
    });

    bindCommHandlers($root);

    // overview snapshot (lazy)
    if($root.find('#ais-status-snapshot').length){
      // trigger pipeline load counts without rendering pipeline UI
      ajax('ai_suite_pipeline_list', { jobId: '' }).done(function(res){
        if(res && res.ok){
          var statuses = (window.AISuitePortal && AISuitePortal.statuses) ? AISuitePortal.statuses : {};
          var counts = res.counts || {};
          var parts = [];
          Object.keys(statuses).forEach(function(k){
            var n = counts[k] ? counts[k] : 0;
            parts.push('<span class="ais-pill">'+esc(statuses[k])+': <strong>'+esc(n)+'</strong></span>');
          });
          $root.find('#ais-status-snapshot').html(parts.length ? parts.join(' ') : '<span class="ais-muted">—</span>');
        }
      });
    }
  }

  function bindCommHandlers($root){
    // thread selection
    $root.off('click.ais_thread').on('click.ais_thread', '[data-ais-thread]', function(e){
      e.preventDefault();
      var appId = parseInt($(this).attr('data-ais-thread') || 0, 10);
      if(!appId) return;
      $root.data('aisApp', appId);
      $root.find('.ais-thread').removeClass('is-active');
      $(this).addClass('is-active');
      loadThread($root, appId);
    });

    // send message
    $root.off('click.ais_send').on('click.ais_send', '[data-ais-chat-send]', function(e){
      e.preventDefault();
      sendMessage($root);
    });

    // quick open tab buttons
    $root.off('click.ais_open_tab').on('click.ais_open_tab', '[data-ais-open-tab]', function(e){
      e.preventDefault();
      var k = $(this).attr('data-ais-open-tab');
      if(k) setActiveTab($root, k);
    });

    // open thread from candidate apps
    $root.off('click.ais_open_thread').on('click.ais_open_thread', '[data-ais-open-thread]', function(e){
      e.preventDefault();
      var appId = parseInt($(this).attr('data-ais-open-thread') || 0, 10);
      if(!appId) return;
      $root.data('aisApp', appId);
      setActiveTab($root, 'messages');
      // after threads load, highlight selection
      setTimeout(function(){
        var $btn = $root.find('[data-ais-thread="'+appId+'"]');
        if($btn.length){ $btn.trigger('click'); }
        else { loadThread($root, appId); }
      }, 250);
    });

    // create interview (company)
    $root.off('click.ais_int_create').on('click.ais_int_create', '[data-ais-interview-create]', function(e){
      e.preventDefault();
      createInterview($root);
    });

    // interview status
    $root.off('click.ais_int_status').on('click.ais_int_status', '[data-ais-interview-status]', function(e){
      e.preventDefault();
      var id = parseInt($(this).attr('data-ais-interview-status') || 0, 10);
      var st = $(this).attr('data-status') || '';
      if(!id || !st) return;
      updateInterviewStatus($root, id, st);
    });
  }

  function initCandidatePortal($root){
    if(!window.AISuitePortal || !AISuitePortal.isCandidate) return;
    $root.data('portal','candidate');

    // tabs
    $root.on('click', '[data-ais-tab]', function(e){
      e.preventDefault();
      setActiveTab($root, $(this).attr('data-ais-tab'));
    });

    bindCommHandlers($root);
    setActiveTab($root, 'overview');
  }


  // --------------------------
  // Company Team (Enterprise)
  // --------------------------
  function loadTeam($root){
    var $list = $root.find('#ais-team-list');
    var $msg = $root.find('#ais-team-invite-msg');
    if(!$list.length) return;

    $list.html('<div class="ais-muted">Se încarcă…</div>');

    ajax('ai_suite_team_list', {}).done(function(res){
      if(!res || !res.ok){
        $list.html('<div class="ais-muted">'+esc(res && res.message ? res.message : 'Eroare la încărcare.')+'</div>');
        return;
      }
      var roles = res.roles || {};
      var rows = res.members || [];
      if(!rows.length){
        $list.html('<div class="ais-muted">Nu există membri. Invită primul coleg.</div>');
        return;
      }

      var html = '';
      html += '<div class="ais-table-head"><div>Email</div><div>Rol</div><div>Status</div><div>Acțiuni</div></div>';
      rows.forEach(function(m){
        var roleLabel = roles[m.role] || m.role;
        html += '<div class="ais-table-row" data-member-id="'+(m.id||0)+'">';
        html += '<div><strong>'+esc(m.email||'')+'</strong>'+(m.name?'<div class="ais-muted">'+esc(m.name)+'</div>':'')+'</div>';
        html += '<div><select class="ais-team-role" '+(m.status==='invited'?'disabled':'')+'>';
        Object.keys(roles).forEach(function(k){
          var sel = (k===m.role)?' selected':'';
          html += '<option value="'+esc(k)+'"'+sel+'>'+esc(roles[k])+'</option>';
        });
        html += '</select></div>';
        html += '<div><span class="ais-pill">'+esc(m.status||'')+'</span></div>';
        html += '<div><button type="button" class="ais-btn ais-btn-ghost ais-team-remove">Șterge</button></div>';
        html += '</div>';
      });
      $list.html(html);
    });

    // Invite handler
    $root.off('click.aisTeamInvite').on('click.aisTeamInvite', '#ais-team-invite-btn', function(){
      var email = ($root.find('#ais-team-invite-email').val()||'').trim();
      var role  = ($root.find('#ais-team-invite-role').val()||'recruiter');
      $msg.text('');
      if(!email){ $msg.text('Introdu un email.'); return; }
      ajax('ai_suite_team_invite', { email: email, role: role }).done(function(res){
        if(res && res.ok){
          $msg.text('Invitație trimisă.');
          $root.find('#ais-team-invite-email').val('');
          loadTeam($root);
        } else {
          $msg.text(res && res.message ? res.message : 'Eroare la invitație.');
        }
      });
    });

    // Remove member
    $root.off('click.aisTeamRemove').on('click.aisTeamRemove', '.ais-team-remove', function(){
      var $row = $(this).closest('[data-member-id]');
      var id = parseInt($row.attr('data-member-id')||'0',10);
      if(!id) return;
      ajax('ai_suite_team_remove', { memberId: id }).done(function(res){
        if(res && res.ok){
          loadTeam($root);
        } else {
          alert(res && res.message ? res.message : 'Nu pot șterge.');
        }
      });
    });

    // Update role
    $root.off('change.aisTeamRole').on('change.aisTeamRole', '.ais-team-role', function(){
      var $row = $(this).closest('[data-member-id]');
      var id = parseInt($row.attr('data-member-id')||'0',10);
      var role = $(this).val();
      if(!id) return;
      ajax('ai_suite_team_update_role', { memberId: id, role: role }).done(function(res){
        if(!(res && res.ok)){
          alert(res && res.message ? res.message : 'Nu pot salva rolul.');
        }
      });
    });
  }

  // --------------------------
  // ATS Settings (Enterprise)
  // --------------------------
  function loadATSSettings($root){
    var $wrap = $root.find('#ais-ats-settings');
    var $msg  = $root.find('#ais-ats-save-msg');
    if(!$wrap.length) return;

    $wrap.html('<div class="ais-muted">Se încarcă…</div>');
    $msg.text('');

    ajax('ai_suite_pipeline_settings_get', {}).done(function(res){
      if(!res || !res.ok){
        $wrap.html('<div class="ais-muted">'+esc(res && res.message ? res.message : 'Eroare la încărcare.')+'</div>');
        return;
      }

      var defaults = res.defaults || {};
      var labels   = res.labels || {};
      var hidden   = res.hidden || [];
      var hiddenMap = {};
      hidden.forEach(function(k){ hiddenMap[k]=true; });

      var html = '<div class="ais-grid ais-grid-2">';
      Object.keys(defaults).forEach(function(k){
        var label = labels[k] || defaults[k] || k;
        var isHidden = !!hiddenMap[k];
        html += '<div class="ais-card" style="padding:12px">';
        html += '<div class="ais-field"><label>'+esc(k)+'</label>';
        html += '<input type="text" class="ais-ats-label" data-key="'+esc(k)+'" value="'+esc(label)+'" /></div>';
        html += '<label class="ais-checkbox"><input type="checkbox" class="ais-ats-hidden" data-key="'+esc(k)+'" '+(isHidden?'checked':'')+' /> Ascunde coloană</label>';
        html += '</div>';
      });
      html += '</div>';
      $wrap.html(html);
    });

    $root.off('click.aisATSSave').on('click.aisATSSave', '#ais-ats-save-btn', function(){
      var labels = {};
      var hidden = [];
      $root.find('.ais-ats-label').each(function(){
        labels[$(this).attr('data-key')] = $(this).val();
      });
      $root.find('.ais-ats-hidden:checked').each(function(){
        hidden.push($(this).attr('data-key'));
      });
      $msg.text('Se salvează…');
      ajax('ai_suite_pipeline_settings_save', { labels: labels, hidden: hidden }).done(function(res){
        if(res && res.ok){
          $msg.text('Setări salvate.');
          // Refresh pipeline tab columns without reload.
          loadATSSettings($root);
          // Optional: when user goes to pipeline, it will reflect labels via API.
        } else {
          $msg.text(res && res.message ? res.message : 'Eroare la salvare.');
        }
      });
    });
  }


  function loadBilling($root){
    const $box = $root.find('#ais-billing-box');
    if(!$box.length) return;

    const companyId = parseInt($box.data('companyId') || $root.data('companyId') || 0, 10);
    const ajaxUrl = (window.AISuitePortal && AISuitePortal.ajaxUrl) ? AISuitePortal.ajaxUrl : (window.ajaxurl || '');
    const nonce = (window.AISuitePortal && AISuitePortal.nonce) ? AISuitePortal.nonce : '';

    const post = (action, data) => {
      data = data || {};
      data.action = action;
      data.nonce = nonce;
      data.company_id = companyId;
      return $.post(ajaxUrl, data);
    };

    let providerMode = 'stripe';

    const storageKey = () => 'aisuite_billing_provider_' + String(companyId || 0);

    const getSavedProvider = () => {
      try{ return localStorage.getItem(storageKey()) || ''; }catch(e){ return ''; }
    };

    const saveProvider = (p) => {
      try{ localStorage.setItem(storageKey(), p); }catch(e){}
    };

    const getProvider = () => {
      if(providerMode === 'both'){
        const $sel = $box.find('[data-ais-billing-provider]');
        let v = ($sel.length ? ($sel.val() || '') : '') || getSavedProvider();
        v = (v === 'netopia' || v === 'stripe') ? v : 'stripe';
        if($sel.length) $sel.val(v);
        return v;
      }
      return providerMode;
    };

    const updateManageButton = () => {
      const provider = getProvider();
      const $m = $box.find('[data-ais-billing-manage]');
      if(!$m.length) return;
      if(provider === 'stripe'){
        $m.show();
        $m.text($m.data('labelStripe') || 'Gestionează în Stripe');
      } else {
        $m.hide();
      }
    };

    const configureProviderUi = () => {
      const $row = $box.find('[data-ais-billing-provider-row]');
      const $sel = $box.find('[data-ais-billing-provider]');
      if($row.length){
        if(providerMode === 'both'){
          $row.show();
          const v = getProvider();
          if($sel.length) $sel.val(v);
          $sel.off('change.aisuiteBilling').on('change.aisuiteBilling', function(){
            const v2 = ($(this).val() || '').toString();
            const vFinal = (v2 === 'netopia' || v2 === 'stripe') ? v2 : 'stripe';
            saveProvider(vFinal);
            updateManageButton();
          });
        } else {
          $row.hide();
        }
      }
      updateManageButton();
    };

    const refresh = () => {
      post('ai_suite_billing_get', {}).done(function(res){
        if(!res || !res.success) return;
        const d = res.data || {};
        const planId = (d.plan_id || 'free').toString();
        const isActive = !!d.is_active;
        providerMode = (d.provider_mode || 'stripe').toString();

        const $plan = $box.find('#ais-billing-current-plan');
        const $status = $box.find('#ais-billing-current-status');
        if($plan.length) $plan.text(planId);
        if($status.length) $status.text(isActive ? 'Activ' : 'Inactiv / Free');

        // Expiry / grace info (Patch45)
        try{
          const sub = d.subscription || {};
          const end = parseInt(sub.current_period_end || 0, 10) || 0;
          const subStatus = (sub.status || '').toString();
          const graceDays = parseInt(d.expiry_grace_days || 0, 10) || 0;
          const notifyDays = parseInt(d.expiry_notify_days || 0, 10) || 0;
          const now = Math.floor(Date.now()/1000);
          const graceEnd = (end && graceDays>0) ? (end + (graceDays*86400)) : 0;

          const $exp = $box.find('#ais-billing-expiry');
          const $notice = $box.find('#ais-billing-notice');

          const fmt = (ts) => {
            const dt = new Date(ts*1000);
            try{ return dt.toLocaleDateString('ro-RO'); }catch(e){ return dt.toISOString().slice(0,10); }
          };

          if($notice.length){
            $notice.hide().removeClass('ais-notice-ok ais-notice-warn ais-notice-err ais-notice-info');
          }

          if(end && $exp.length){
            if(end > now){
              $exp.text('Valabil până la: ' + fmt(end));
              if(notifyDays>0 && (end-now) <= (notifyDays*86400) && $notice.length){
                $notice.addClass('ais-notice-warn').html('⚠️ Abonamentul expiră în curând. Recomandat: reînnoiește din această pagină.').show();
              }
            }else if(graceEnd && now <= graceEnd){
              $exp.text('Expirat la: ' + fmt(end) + ' • Grație până la: ' + fmt(graceEnd));
              if($notice.length){
                $notice.addClass('ais-notice-warn').html('⚠️ Abonamentul a expirat. Ești în <strong>perioada de grație</strong> — reînnoiește până la ' + fmt(graceEnd) + ' ca să eviți downgrade pe Free.').show();
              }
            }else{
              $exp.text('Expirat la: ' + fmt(end));
              if($notice.length){
                $notice.addClass('ais-notice-err').html('⛔ Abonamentul este expirat. Upgrade din această pagină pentru a reactiva funcțiile premium.').show();
              }
            }
          }else if($exp.length){
            $exp.text('');
          }

          // Keep status label more accurate when in grace
          if(subStatus === 'grace' && $status.length){
            $status.text('Grație');
          }
        }catch(e){}

        // Provider UI / manage button
        configureProviderUi();

        // Toggle plan buttons
        $box.find('[data-ais-upgrade-plan]').each(function(){
          const pid = ($(this).data('aisUpgradePlan') || '').toString();
          const isCurrent = (pid === planId);
          $(this).prop('disabled', isCurrent);
          $(this).text(isCurrent ? ($(this).data('labelSelected') || 'Selectat') : ($(this).data('labelChoose') || 'Alege'));
        });
      });
    };

    // Bind only once
    if($box.data('billingBound')){ refresh(); return; }
    $box.data('billingBound', 1);


    // Buyer / Billing details save
    const $buyerCard = $root.find('#ais-buyer-details-card');
    const $buyerSave = $root.find('#ais-company-billing-save');
    const $buyerMsg  = $root.find('#ais-company-billing-save-msg');

    const bindBuyerSave = () => {
      if(!$buyerSave.length || $buyerSave.data('bound')) return;
      $buyerSave.data('bound', 1);
      $buyerSave.on('click', function(e){
        e.preventDefault();
        const payload = {
          billing_name: ($root.find('#ais-buyer-name').val() || '').toString().trim(),
          billing_cui: ($root.find('#ais-buyer-cui').val() || '').toString().trim(),
          billing_reg: ($root.find('#ais-buyer-reg').val() || '').toString().trim(),
          billing_address: ($root.find('#ais-buyer-address').val() || '').toString().trim(),
          billing_city: ($root.find('#ais-buyer-city').val() || '').toString().trim(),
          billing_country: ($root.find('#ais-buyer-country').val() || '').toString().trim(),
          billing_email: ($root.find('#ais-buyer-email').val() || '').toString().trim(),
          billing_phone: ($root.find('#ais-buyer-phone').val() || '').toString().trim(),
          billing_contact: ($root.find('#ais-buyer-contact').val() || '').toString().trim(),
          billing_vat: $root.find('#ais-buyer-vat').is(':checked') ? 1 : 0,
        };

        if(!payload.billing_name){
          toast('err','Date facturare','Completează „Denumire firmă”.');
          return;
        }

        $buyerSave.prop('disabled', true).addClass('is-busy');
        if($buyerMsg.length) $buyerMsg.text('Se salvează…');

        post('ai_suite_company_billing_save', payload).done(function(res){
          $buyerSave.prop('disabled', false).removeClass('is-busy');
          if(res && res.success){
            if($buyerMsg.length) $buyerMsg.text((res.data && res.data.message) ? res.data.message : 'Salvat.');
            toast('ok','Date facturare','Salvate cu succes.');
            return;
          }
          const msg = (res && res.data && res.data.message) ? res.data.message : 'Eroare la salvare.';
          if($buyerMsg.length) $buyerMsg.text(msg);
          toast('err','Date facturare', msg);
        }).fail(function(xhr){
          $buyerSave.prop('disabled', false).removeClass('is-busy');
          let msg = 'Eroare la salvare.';
          try{ if(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message){ msg = xhr.responseJSON.data.message; } }catch(e){}
          if($buyerMsg.length) $buyerMsg.text(msg);
          toast('err','Date facturare', msg);
        });
      });
    };

    if($buyerCard.length){ bindBuyerSave(); }


    $box.on('click', '[data-ais-billing-refresh]', function(e){
      e.preventDefault();
      refresh();
    refreshHistory();
    });

    $box.on('click', '[data-ais-upgrade-plan]', function(e){
      e.preventDefault();
      const $btn = $(this);
      const pid = ($btn.data('aisUpgradePlan') || '').toString();
      if(!pid) return;

      $btn.prop('disabled', true).addClass('is-busy');

      const provider = getProvider();
      post('ai_suite_billing_checkout', { plan_id: pid, provider: provider }).done(function(res){
        $btn.removeClass('is-busy');
        if(res && res.success && res.data){
          if(res.data.checkout_url){
            window.location.href = res.data.checkout_url;
            return;
          }
          refresh();
          return;
        }
        alert((res && res.data && res.data.message) ? res.data.message : 'Eroare la checkout.');
        refresh();
      }).fail(function(){
        $btn.removeClass('is-busy');
        alert('Eroare la checkout.');
        refresh();
      });
    });

    $box.on('click', '[data-ais-billing-manage]', function(e){
      e.preventDefault();
      const $btn = $(this);
      $btn.prop('disabled', true).addClass('is-busy');
      post('ai_suite_billing_portal', {}).done(function(res){
        if(res && res.success && res.data && res.data.url){
          window.location.href = res.data.url;
          return;
        }
        alert((res && res.data && res.data.message) ? res.data.message : 'Eroare portal billing.');
        $btn.prop('disabled', false).removeClass('is-busy');
      }).fail(function(){
        alert('Eroare portal billing.');
        $btn.prop('disabled', false).removeClass('is-busy');
      });
    });


    // Billing History (invoices + events)
    const $hist = $root.find('#ais-billing-history');
    const $histTable = $root.find('#ais-billing-history-table');
    const $histRefresh = $root.find('#ais-billing-history-refresh');

    const fmtMoney = (cents, cur) => {
      const v = (parseInt(cents||0,10) / 100);
      try{ return v.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + (cur||'EUR'); }catch(e){ return String(v.toFixed(2)) + ' ' + (cur||'EUR'); }
    };

    const invoiceHtmlUrl = (invoiceId) => {
      const q = new URLSearchParams();
      q.set('action','ai_suite_billing_invoice_html_portal');
      q.set('invoice_id', String(invoiceId||0));
      q.set('company_id', String(companyId||0));
      q.set('nonce', nonce || '');
      return ajaxUrl + '?' + q.toString();
    };

    const renderHistory = (payload) => {
      if(!$histTable.length) return;
      const inv = (payload && payload.invoices) ? payload.invoices : [];
      const $tbody = $histTable.find('tbody');
      if(!$tbody.length) return;

      if(!inv.length){
        $tbody.html('<tr><td colspan="5" class="ais-muted">Încă nu există facturi.</td></tr>');
        $hist.find('.ais-muted').first().text('Nu există facturi încă.');
        return;
      }

      const rows = inv.map(function(x){
        const id = parseInt(x.id||0,10);
        const dt = (x.created_at||'').toString();
        const st = (x.status||'').toString();
        const prov = (x.provider||'').toString().toUpperCase();
        const total = fmtMoney(x.amount_cents||0, x.currency||'EUR');
        const invNo = (x.invoice_number||'—').toString();
        const a = '<a class="ais-btn ais-btn--tiny" target="_blank" rel="noopener" href="'+ invoiceHtmlUrl(id) +'">HTML</a>';
        return '<tr>' +
          '<td>'+ (dt?dt:'—') +'</td>' +
          '<td><span class="ais-pill ais-pill--' + (st==='paid'?'ok':(st==='failed'?'bad':'warn')) + '">'+ st +'</span></td>' +
          '<td><strong>'+ total +'</strong></td>' +
          '<td>'+ prov +'</td>' +
          '<td><div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span><strong>'+ invNo +'</strong></span>'+ a +'</div></td>' +
        '</tr>';
      }).join('');
      $tbody.html(rows);
      $hist.find('.ais-muted').first().text('Istoric încărcat.');
    };

    const refreshHistory = () => {
      if(!$hist.length) return;
      $hist.find('.ais-muted').first().text('Se încarcă istoricul…');
      post('ai_suite_billing_history_list', {limit: 30}).done(function(res){
        if(!res || !res.success){ $hist.find('.ais-muted').first().text('Eroare la încărcare.'); return; }
        renderHistory(res.data || {});
      }).fail(function(){
        $hist.find('.ais-muted').first().text('Eroare la încărcare.');
      });
    };

    if($histRefresh.length){
      $histRefresh.on('click', function(){ refreshHistory(); });
    }

    // initial
    refresh();
  }




  $(function(){
    // company portal(s)
    $('.ais-portal[data-portal="company"]').each(function(){
      initCompanyPortal($(this));
    });

    // candidate portal(s)
    $('.ais-portal[data-portal="candidate"]').each(function(){
      initCandidatePortal($(this));
    });
  });
})(jQuery);



; (function($){
  if (typeof $ !== 'function') { return; }
  // -------------------------
  // Copilot AI (Portal Companie)
  // -------------------------
  function copilotRender($box, chat){
    chat = Array.isArray(chat) ? chat : [];
    var html = '';
    chat.forEach(function(m){
      var role = (m && m.role) ? String(m.role) : '';
      var content = (m && m.content) ? String(m.content) : '';
      if(!content) return;
      var cls = (role === 'assistant') ? 'ais-copilot-msg ais-assistant' : 'ais-copilot-msg ais-user';
      var label = (role === 'assistant') ? 'AI' : 'Tu';
      html += '<div class="'+cls+'"><div class="ais-copilot-who">'+label+'</div><div class="ais-copilot-text">'+esc(content).replace(/\n/g,'<br>')+'</div></div>';
    });
    $box.html(html || '<div class="ais-muted">Scrie un mesaj ca să începi.</div>');
    $box.scrollTop($box[0].scrollHeight);
  }

  $(document).on('click', '#ais-portal-copilot-send', function(){
    var msg = ($('#ais-portal-copilot-input').val() || '').trim();
    var includePii = $('#ais-portal-copilot-include-pii').is(':checked') ? 1 : 0;
    if(!msg){ return; }
    $('#ais-portal-copilot-status').text('Se trimite…');
    ajax('ai_suite_portal_copilot_send', { message: msg, include_pii: includePii })
      .done(function(r){
        if(r && r.success){
          $('#ais-portal-copilot-input').val('');
          copilotRender($('#ais-portal-copilot-chat'), r.data && r.data.chat ? r.data.chat : []);
          $('#ais-portal-copilot-status').text('Gata.');
        } else {
          $('#ais-portal-copilot-status').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      })
      .fail(function(){
        $('#ais-portal-copilot-status').text('Eroare la conexiune.');
      });
  });

  $(document).on('click', '#ais-portal-copilot-clear', function(){
    $('#ais-portal-copilot-status').text('Se șterge…');
    ajax('ai_suite_portal_copilot_clear', {})
      .done(function(r){
        if(r && r.success){
          copilotRender($('#ais-portal-copilot-chat'), []);
          $('#ais-portal-copilot-status').text('Conversația a fost ștearsă.');
        } else {
          $('#ais-portal-copilot-status').text((r && r.data && r.data.message) ? r.data.message : 'Eroare.');
        }
      });
  });

  function copilotAutoLoad(){
    if(!$('#ais-portal-copilot-chat').length){ return; }
    ajax('ai_suite_portal_copilot_load', {})
      .done(function(r){
        if(r && r.success){
          copilotRender($('#ais-portal-copilot-chat'), r.data && r.data.chat ? r.data.chat : []);
        }
      });
  }

  $(function(){
    // Load when the pane exists (initial or after tab change).
    copilotAutoLoad();
    $(document).on('click', '.ais-tab', function(){
      var key = $(this).attr('data-ais-tab');
      if(key === 'copilot'){
        setTimeout(copilotAutoLoad, 50);
      }
    });
  });

  function modal(title, bodyHtml){
    var $m = $('#ais-modal');
    if(!$m.length){
      $m = $('<div id="ais-modal" class="ais-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:none;align-items:center;justify-content:center;padding:20px;"></div>');
      $m.append('<div class="ais-modal-card" style="background:#111827;color:#e5e7eb;border-radius:14px;max-width:920px;width:100%;max-height:80vh;overflow:auto;border:1px solid rgba(255,255,255,.08);"><div class="ais-row ais-row-between" style="padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08);"><strong id="ais-modal-title"></strong><button type="button" id="ais-modal-x" class="ais-btn ais-btn-soft">Închide</button></div><div id="ais-modal-body" style="padding:14px 16px;"></div></div>');
      $('body').append($m);
      $m.on('click', function(e){ if(e.target===this){ $m.hide(); } });
      $m.on('click', '#ais-modal-x', function(){ $m.hide(); });
    }
    $('#ais-modal-title').text(title||'');
    $('#ais-modal-body').html(bodyHtml||'');
    $m.show();
  }

  function bindATSBulkActions($root){
    // PATCH51: ensure bulkbar exists + advanced bulk actions (assign/tag/schedule/email)
    if(!$root.find('#ais-ats-bulkbar').length){
      var $pane = $root.find('.ais-pane[data-ais-pane="ats_board"]').first();
      if($pane.length){
        var bar = ''+
          '<div id="ais-ats-bulkbar" class="ais-bulkbar">'+
          ' <label class="ais-row" style="gap:8px"><input type="checkbox" id="ais-ats-select-all" /> <span>Selectează tot</span></label>'+
          ' <span id="ais-ats-bulk-count" class="ais-muted">0 selectate</span>'+
          ' <select id="ais-ats-bulk-action" class="ais-select" style="min-width:180px">'+
          '  <option value="move">Mută status</option>'+
          '  <option value="assign">Atribuie recruiter</option>'+
          '  <option value="tag">Adaugă tag</option>'+
          '  <option value="schedule">Programează interviu</option>'+
          '  <option value="email">Trimite email</option>'+
          ' </select>'+
          ' <span class="ais-bulk-slot" data-slot="move"><select id="ais-ats-bulk-status" class="ais-select"></select></span>'+
          ' <span class="ais-bulk-slot" data-slot="assign" style="display:none"><select id="ais-ats-bulk-user" class="ais-select"><option value="">Alege…</option></select></span>'+
          ' <span class="ais-bulk-slot" data-slot="tag" style="display:none"><input id="ais-ats-bulk-tags" class="ais-input" placeholder="#tag1, #tag2" style="min-width:180px" /></span>'+
          ' <span class="ais-bulk-slot" data-slot="schedule" style="display:none">'+
          '  <input id="ais-ats-bulk-interviewat" type="datetime-local" class="ais-input" />'+
          '  <input id="ais-ats-bulk-interviewnote" class="ais-input" placeholder="Notiță (opțional)" style="min-width:180px" />'+
          ' </span>'+
          ' <span class="ais-bulk-slot" data-slot="email" style="display:none">'+
          '  <select id="ais-ats-bulk-email-template" class="ais-select" style="min-width:180px"><option value="">Șablon email…</option></select>'+
          '  <input id="ais-ats-bulk-email-subject" class="ais-input" placeholder="Subiect" style="min-width:180px" />'+
          '  <input id="ais-ats-bulk-email-body" class="ais-input" placeholder="Mesaj" style="min-width:260px" />'+
          ' </span>'+
          ' <button type="button" id="ais-ats-bulk-apply" class="ais-btn ais-btn-primary">Aplică</button>'+
          '</div>';
        // Insert after the info card in ATS pane
        var $card = $pane.find('.ais-card').first();
        if($card.length){ $card.after(bar); } else { $pane.prepend(bar); }
      }
    }

    // Populate team dropdown (best-effort)
    try{
      var $ud = $root.find('#ais-ats-bulk-user');
      if($ud.length && !$ud.data('loaded')){
        $ud.data('loaded', true);
        ajax('ai_suite_pipeline_team_list', {}).done(function(res){
          if(!res || !res.ok) return;
          var members = res.members || [];
          var html = '<option value="">Alege…</option>';
          members.forEach(function(m){
            html += '<option value="'+esc(m.userId)+'">'+esc(m.name || m.email || ('User #'+m.userId))+'</option>';
          });
          $ud.html(html);
        });
      }
    }catch(e){}

    
// ATS Email templates (Portal)
var _aisEmailTplCache = null;
function ensureAtsEmailTemplates(){
  var d = $.Deferred();
  if(_aisEmailTplCache){ d.resolve(_aisEmailTplCache); return d.promise(); }
  ajax('ai_suite_ats_templates_portal', {}).done(function(res){
    if(res && res.ok && res.templates){
      _aisEmailTplCache = res.templates;
      d.resolve(_aisEmailTplCache);
    } else {
      _aisEmailTplCache = [];
      d.resolve(_aisEmailTplCache);
    }
  }).fail(function(){ _aisEmailTplCache = []; d.resolve(_aisEmailTplCache); });
  return d.promise();
}
function fillEmailTemplateSelect($sel){
  ensureAtsEmailTemplates().done(function(tpls){
    var html = '<option value="">Șablon email…</option>';
    (tpls||[]).forEach(function(t){
      html += '<option value="'+esc(t.key)+'">'+esc(t.label||t.key)+'</option>';
    });
    $sel.html(html);
  });
}
// Bulk action UI switch
    $root.off('change.aisAtsBulkAction').on('change.aisAtsBulkAction', '#ais-ats-bulk-action', function(){
      var act = String($(this).val()||'move');
      $root.find('#ais-ats-bulkbar .ais-bulk-slot').hide();
      $root.find('#ais-ats-bulkbar .ais-bulk-slot[data-slot="'+act+'"]').show();
    });

// When template chosen, prefill subject/body (can still edit)
$root.off('change.aisAtsEmailTpl').on('change.aisAtsEmailTpl', '#ais-ats-bulk-email-template', function(){
  var key = String($(this).val()||'');
  if(!key) return;
  ensureAtsEmailTemplates().done(function(tpls){
    var t = null;
    (tpls||[]).forEach(function(x){ if(x.key === key) t = x; });
    if(t){
      if(t.subject){ $root.find('#ais-ats-bulk-email-subject').val(t.subject); }
      if(t.body){ $root.find('#ais-ats-bulk-email-body').val(t.body); }
    }
  });
});


    // update selected count
    function countSel(){
      var n = $root.find('.ais-ats-sel:checked').length;
      $root.find('#ais-ats-bulk-count').text(String(n)+' selectate');
      return n;
    }

    $root.off('change.aisAtsSel').on('change.aisAtsSel', '.ais-ats-sel', function(){
      countSel();
    });

    $root.off('change.aisAtsSelAll').on('change.aisAtsSelAll', '#ais-ats-select-all', function(){
      var on = $(this).is(':checked');
      $root.find('.ais-ats-sel').prop('checked', on);
      countSel();
    });

    $root.off('click.aisAtsBulkApply').on('click.aisAtsBulkApply', '#ais-ats-bulk-apply', function(){
      var ids = [];
      $root.find('.ais-ats-sel:checked').each(function(){ ids.push($(this).data('app')); });
      ids = ids.filter(Boolean);
      if(!ids.length){ toast('info','ATS','Nu ai selectat nimic.'); return; }
      var act = ($root.find('#ais-ats-bulk-action').val()||'move').trim();
      // MOVE
      if(act === 'move'){
        var to = ($root.find('#ais-ats-bulk-status').val()||'').trim();
        if(!to){ toast('err','ATS','Alege un status.'); return; }
        toast('info','ATS','Se mută '+ids.length+' aplicații…');
        ajax('ai_suite_pipeline_bulk_move', { applicationIds: ids, toStatus: to }).done(function(res){
          if(res && res.ok){
            toast('ok','ATS','Mutate: '+(res.moved||0));
            if(res.errors && res.errors.length){ toast('info','ATS','Unele mutări au eșuat ('+res.errors.length+').'); }
            $root.find('#ais-ats-select-all').prop('checked', false);
            $root.find('#ais-ats-job').trigger('change');
          } else {
            toast('err','ATS', (res && res.message) ? res.message : 'Eroare.');
          }
        }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
        return;
      }

      // ASSIGN
      if(act === 'assign'){
        var uid = ($root.find('#ais-ats-bulk-user').val()||'').trim();
        if(!uid){ toast('err','ATS','Alege un recruiter.'); return; }
        toast('info','ATS','Se atribuie '+ids.length+' aplicații…');
        ajax('ai_suite_pipeline_bulk_assign', { applicationIds: ids, userId: uid }).done(function(res){
          if(res && res.ok){
            toast('ok','ATS','Atribuite: '+(res.assigned||0));
            if(res.errors && res.errors.length){ toast('info','ATS','Unele atribuiri au eșuat ('+res.errors.length+').'); }
            $root.find('#ais-ats-job').trigger('change');
          } else {
            toast('err','ATS', (res && res.message) ? res.message : 'Eroare.');
          }
        }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
        return;
      }

      // TAG
      if(act === 'tag'){
        var tags = ($root.find('#ais-ats-bulk-tags').val()||'').trim();
        if(!tags){ toast('err','ATS','Scrie cel puțin un tag.'); return; }
        toast('info','ATS','Se aplică tag-uri pe '+ids.length+' aplicații…');
        ajax('ai_suite_pipeline_bulk_tag', { applicationIds: ids, tags: tags }).done(function(res){
          if(res && res.ok){
            toast('ok','ATS','Actualizate: '+(res.updated||0));
            $root.find('#ais-ats-job').trigger('change');
          } else { toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); }
        }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
        return;
      }

      // SCHEDULE
      if(act === 'schedule'){
        var dt = ($root.find('#ais-ats-bulk-interviewat').val()||'').trim();
        var note = ($root.find('#ais-ats-bulk-interviewnote').val()||'').trim();
        if(!dt){ toast('err','ATS','Alege data/ora interviului.'); return; }
        toast('info','ATS','Se programează '+ids.length+' interviuri…');
        ajax('ai_suite_pipeline_bulk_schedule', { applicationIds: ids, interviewAt: dt, note: note }).done(function(res){
          if(res && res.ok){
            toast('ok','ATS','Programate: '+(res.scheduled||0));
            $root.find('#ais-ats-job').trigger('change');
          } else { toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); }
        }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
        return;
      }

      // EMAIL
      if(act === 'email'){
        var sub = ($root.find('#ais-ats-bulk-email-subject').val()||'').trim();
        var body = ($root.find('#ais-ats-bulk-email-body').val()||'').trim();
        if(!sub || !body){ toast('err','ATS','Completează subiect + mesaj.'); return; }
        toast('info','ATS','Se trimit '+ids.length+' emailuri…');
        ajax('ai_suite_pipeline_bulk_email', { applicationIds: ids, subject: sub, body: body }).done(function(res){
          if(res && res.ok){
            toast('ok','ATS','Trimise: '+(res.sent||0));
          } else { toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); }
        }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
        return;
      }
    });

    // Drawer – details
    function ensureDrawer(){
      if($('#ais-ats-drawer').length) return;
      var h = ''+
        '<div id="ais-ats-drawer" class="ais-drawer-overlay" style="display:none">'+
        ' <div class="ais-drawer">'+
        '  <div class="ais-drawer-head">'+
        '    <div class="ais-drawer-title">Detalii aplicație</div>'+
        '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-drawer-close="1">✕</button>'+
        '  </div>'+
        '  <div class="ais-drawer-body" id="ais-ats-drawer-content"></div>'+
        ' </div>'+
        '</div>';
      $('body').append(h);
      $('body').on('click', '[data-ais-drawer-close]', function(){ $('#ais-ats-drawer').hide(); });
      $('body').on('click', '#ais-ats-drawer', function(ev){ if(ev.target === this) $('#ais-ats-drawer').hide(); });
    }

    function openDrawer(appId){
      ensureDrawer();
      $('#ais-ats-drawer-content').html(skeleton('Se încarcă…'));
      $('#ais-ats-drawer').show();
      ajax('ai_suite_pipeline_get', { applicationId: appId }).done(function(res){
        if(!res || !res.ok){ $('#ais-ats-drawer-content').html('<div class="ais-notice ais-notice-err">'+esc((res&&res.message)?res.message:'Eroare')+'</div>'); return; }
        var a = res.application || {};
        var c = a.candidate || {};
        var tags = (a.tags && a.tags.length) ? a.tags.map(function(t){ return '<span class="ais-tag">'+esc(t)+'</span>'; }).join('') : '<span class="ais-muted">—</span>';
        var cv = c.cvUrl ? ('<a class="ais-link" href="'+esc(c.cvUrl)+'" target="_blank" rel="noopener">Deschide CV</a>') : '<span class="ais-muted">CV lipsă</span>';
        var tl = a.timeline || [];
        var notes = a.notes || [];
        var tlHtml = '';
        tl.slice(-15).reverse().forEach(function(e){
          var dt = e.time ? new Date(e.time*1000) : null;
          tlHtml += '<div class="ais-card" style="padding:10px 12px">'+
            '<div class="ais-muted" style="font-size:12px">'+esc(dt?dt.toLocaleString():'')+'</div>'+
            '<div>'+esc(e.event||'')+'</div></div>';
        });
        var nHtml = '';
        notes.slice(-10).reverse().forEach(function(n){
          var dt = n.time ? new Date(n.time*1000) : null;
          nHtml += '<div class="ais-card" style="padding:10px 12px">'+
            '<div class="ais-muted" style="font-size:12px">'+esc(dt?dt.toLocaleString():'')+'</div>'+
            '<div>'+esc(n.text||'')+'</div></div>';
        });

        var html = ''+
          '<div class="ais-grid ais-grid-2" style="gap:12px">'+
          ' <div class="ais-card">'+
          '  <div class="ais-card-title">'+esc(c.name || a.title || ('Aplicație #'+a.id))+'</div>'+
          '  <div class="ais-muted" style="margin-top:4px">'+esc(a.jobTitle||'')+'</div>'+
          '  <div style="margin-top:10px">'+cv+'</div>'+
          '  <div class="ais-card-meta" style="margin-top:10px">'+
          '    <div><span class="ais-muted">Email:</span> '+esc(c.email||'')+'</div>'+
          '    <div><span class="ais-muted">Telefon:</span> '+esc(c.phone||'')+'</div>'+
          '    <div><span class="ais-muted">Locație:</span> '+esc(c.location||'')+'</div>'+
          '  </div>'+
          ' </div>'+
          ' <div class="ais-card">'+
          '  <div class="ais-card-title">Acțiuni rapide</div>'+
          '  <div class="ais-row" style="gap:8px; flex-wrap:wrap; margin-top:10px">'+
          '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-ats-note="'+esc(a.id)+'">Adaugă notă</button>'+
          '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-ats-history="'+esc(a.id)+'">Istoric</button>'+
          '    <button type="button" class="ais-btn ais-btn-ghost" data-ais-ats-reject="'+esc(a.id)+'">Respinge</button>'+
          '  </div>'+
          '  <div style="margin-top:12px" class="ais-muted">Tags</div>'+
          '  <div class="ais-tags" style="margin-top:6px">'+tags+'</div>'+
          '  <div style="margin-top:12px" class="ais-muted">Interviu</div>'+
          '  <div style="margin-top:6px">'+esc(a.interviewAt||'—')+'</div>'+
          ' </div>'+
          '</div>'+
          '<div class="ais-grid ais-grid-2" style="gap:12px; margin-top:12px">'+
          ' <div class="ais-card">'+
          '  <div class="ais-card-title">Note</div>'+
          '  <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">'+(nHtml||'<div class="ais-muted">—</div>')+'</div>'+
          ' </div>'+
          ' <div class="ais-card">'+
          '  <div class="ais-card-title">Timeline</div>'+
          '  <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">'+(tlHtml||'<div class="ais-muted">—</div>')+'</div>'+
          ' </div>'+
          '</div>';

        $('#ais-ats-drawer-content').html(html);
      }).fail(function(){
        $('#ais-ats-drawer-content').html('<div class="ais-notice ais-notice-err">Eroare la server (AJAX).</div>');
      });
    }

    $root.off('click.aisAtsDetail').on('click.aisAtsDetail', '[data-ais-ats-detail]', function(){
      var id = $(this).attr('data-ais-ats-detail');
      if(!id) return;
      openDrawer(id);
    });

    $root.off('click.aisAtsReject').on('click.aisAtsReject', '[data-ais-ats-reject]', function(){
      var id = $(this).attr('data-ais-ats-reject');
      if(!id) return;
      ajax('ai_suite_pipeline_move', { applicationId: id, toStatus: 'respins' }).done(function(res){
        if(res && res.ok){ toast('ok','ATS','Marcat Respins'); $root.find('#ais-ats-job').trigger('change'); }
        else { toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); }
      }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
    });

    $root.off('click.aisAtsNote').on('click.aisAtsNote', '[data-ais-ats-note]', function(){
      var id = $(this).attr('data-ais-ats-note');
      if(!id) return;
      var t = prompt('Scrie o notă internă pentru această aplicație:');
      if(t===null) return;
      t = String(t||'').trim();
      if(!t){ toast('info','ATS','Nota este goală.'); return; }
      ajax('ai_suite_pipeline_add_note', { applicationId: id, text: t }).done(function(res){
        if(res && res.ok){ toast('ok','ATS','Notă salvată'); }
        else { toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); }
      }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
    });

    $root.off('click.aisAtsHistory').on('click.aisAtsHistory', '[data-ais-ats-history]', function(){
      var id = $(this).attr('data-ais-ats-history');
      if(!id) return;
      toast('info','ATS','Se încarcă istoricul…');
      ajax('ai_suite_pipeline_activity', { applicationId: id }).done(function(res){
        if(!res || !res.ok){ toast('err','ATS', (res && res.message) ? res.message : 'Eroare.'); return; }
        var tl = res.timeline || [];
        var notes = res.notes || [];
        var h = '';
        if(notes.length){
          h += '<h4 style="margin:0 0 8px;">Note</h4>';
          h += '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">';
          notes.slice(-15).reverse().forEach(function(n){
            var dt = n.time ? new Date(n.time*1000) : null;
            h += '<div class="ais-card" style="padding:10px 12px;">'
              + '<div class="ais-muted" style="font-size:12px;">'+esc(dt?dt.toLocaleString():'')+'</div>'
              + '<div>'+esc(n.text||'')+'</div></div>';
          });
          h += '</div>';
        }
        if(tl.length){
          h += '<h4 style="margin:0 0 8px;">Istoric</h4>';
          h += '<div style="display:flex;flex-direction:column;gap:8px;">';
          tl.slice(-20).reverse().forEach(function(e){
            var dt = e.time ? new Date(e.time*1000) : null;
            var line = (e.event||'') + (e.data && e.data.from && e.data.to ? (' • '+esc(e.data.from)+' → '+esc(e.data.to)) : '');
            h += '<div class="ais-card" style="padding:10px 12px;">'
              + '<div class="ais-muted" style="font-size:12px;">'+esc(dt?dt.toLocaleString():'')+'</div>'
              + '<div>'+esc(line)+'</div></div>';
          });
          h += '</div>';
        }
        if(!h){ h = '<div class="ais-muted">Nu există activitate încă.</div>'; }
        modal('Istoric aplicație #'+esc(id), h);
      }).fail(function(){ toast('err','ATS','Eroare la server (AJAX).'); });
    });

    countSel();
  }

})(window.jQuery);


