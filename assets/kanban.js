/* global jQuery, AI_SUITE */
(function ($) {
  'use strict';

  function toast(msg, type) {
    var $t = $('<div class="ai-toast" />').text(msg || '');
    $t.addClass(type === 'ok' ? 'ok' : (type === 'bad' ? 'bad' : ''));
    $('body').append($t);
    setTimeout(function () { $t.addClass('show'); }, 20);
    setTimeout(function () { $t.removeClass('show'); $t.remove(); }, 2200);
  }

  function updateCounts() {
    $('.ai-kanban-col').each(function () {
      var $col = $(this);
      var count = $col.find('.ai-kanban-card').length;
      $col.find('.ai-kanban-count').text(String(count));
      if (count === 0) {
        if ($col.find('.ai-kanban-empty').length === 0) {
          $col.find('.ai-kanban-list').append('<div class="ai-kanban-empty">—</div>');
        }
      } else {
        $col.find('.ai-kanban-empty').remove();
      }
    });
  }

  function initKanban() {
    var $board = $('.ai-kanban');
    if ($board.length === 0) return;

    $('.ai-kanban-list').sortable({
      connectWith: '.ai-kanban-list',
      placeholder: 'ai-kanban-placeholder',
      forcePlaceholderSize: true,
      tolerance: 'pointer',
      items: '.ai-kanban-card',
      start: function () {
        toast((AI_SUITE && AI_SUITE.i18n && AI_SUITE.i18n.se_salveaza) ? AI_SUITE.i18n.se_salveaza : 'Se salvează...', '');
      },
      receive: function (event, ui) {
        var $item = $(ui.item);
        var appId = $item.data('app-id');
        var newStatus = $(this).data('status');

        if (!appId || !newStatus) {
          toast((AI_SUITE && AI_SUITE.i18n && AI_SUITE.i18n.eroare) ? AI_SUITE.i18n.eroare : 'Eroare', 'bad');
          return;
        }

        $.post(AI_SUITE.ajax_url, {
          action: 'ai_suite_kanban_update_status',
          nonce: AI_SUITE.nonce,
          app_id: appId,
          status: newStatus
        }).done(function (resp) {
          if (resp && resp.success) {
            toast((AI_SUITE && AI_SUITE.i18n && AI_SUITE.i18n.ok) ? AI_SUITE.i18n.ok : 'Actualizat', 'ok');
            updateCounts();
          } else {
            toast((resp && resp.data && resp.data.message) ? resp.data.message : 'Eroare', 'bad');
          }
        }).fail(function () {
          toast('Eroare la salvare', 'bad');
        });
      },
      stop: function () {
        updateCounts();
      }
    }).disableSelection();

    updateCounts();
  }

  $(document).ready(function () {
    initKanban();
  });
})(jQuery);
