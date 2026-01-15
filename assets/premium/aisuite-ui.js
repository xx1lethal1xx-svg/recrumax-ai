
(function(){
  'use strict';
  window.AISuiteUI = window.AISuiteUI || {};
  function qs(s, r){return (r||document).querySelector(s);}
  function qsa(s, r){return Array.prototype.slice.call((r||document).querySelectorAll(s));}

  AISuiteUI.toast = function(msg, type){
    type = type || 'info';
    var wrap = qs('#ais-toast-wrap');
    if(!wrap){ wrap=document.createElement('div'); wrap.id='ais-toast-wrap';
      wrap.style.position='fixed'; wrap.style.right='16px'; wrap.style.bottom='16px'; wrap.style.zIndex='99999';
      wrap.style.display='flex'; wrap.style.flexDirection='column'; wrap.style.gap='10px';
      document.body.appendChild(wrap);
    }
    var t=document.createElement('div');
    t.className='ais-card';
    t.style.minWidth='260px';
    t.style.background = (type==='ok') ? 'rgba(16,185,129,.18)' : (type==='err' ? 'rgba(239,68,68,.18)' : 'rgba(99,102,241,.18)');
    t.textContent=msg;
    wrap.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transform='translateY(6px)'; t.style.transition='all .25s ease'; }, 2500);
    setTimeout(function(){ if(t && t.parentNode) t.parentNode.removeChild(t); }, 2900);
  };

  AISuiteUI.tabs = function(root){
    root = root || document;
    qsa('[data-ais-tab]', root).forEach(function(btn){
      btn.addEventListener('click', function(e){
        e.preventDefault();
        var key = btn.getAttribute('data-ais-tab');
        var scope = btn.closest('[data-ais-tabs]') || root;
        qsa('[data-ais-tab]', scope).forEach(function(b){ b.classList.remove('active');});
        qsa('[data-ais-pane]', scope).forEach(function(p){ p.style.display='none';});
        btn.classList.add('active');
        var pane = qs('[data-ais-pane="'+key+'"]', scope);
        if(pane){ pane.style.display='block';}
      });
    });
  };

  document.addEventListener('DOMContentLoaded', function(){
    AISuiteUI.tabs(document);
  });
})();
