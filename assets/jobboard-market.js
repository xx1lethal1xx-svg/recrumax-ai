/* AI Suite Job Board – Market enhancements (Saved jobs) */
(function($){
  function setBtnState($btn, saved){
    if(saved){
      $btn.addClass('ai-job-save-saved');
      $btn.text(AISuiteJobboard && AISuiteJobboard.isRo ? 'Salvat' : 'Saved');
    } else {
      $btn.removeClass('ai-job-save-saved');
      $btn.text(AISuiteJobboard && AISuiteJobboard.isRo ? 'Salvează' : 'Save');
    }
  }

  $(document).on('click', '.ai-job-save[data-job-id]', function(){
    var $btn = $(this);
    var jobId = parseInt($btn.attr('data-job-id'), 10) || 0;
    if(!jobId) return;

    $btn.prop('disabled', true).addClass('ai-busy');

    $.post(AISuiteJobboard.ajaxUrl, {
      action: 'ai_suite_toggle_save_job',
      nonce: AISuiteJobboard.nonce,
      job_id: jobId
    }).done(function(resp){
      if(resp && resp.success){
        setBtnState($btn, !!resp.data.saved);
      } else {
        var msg = (resp && resp.data && resp.data.message) ? resp.data.message : (AISuiteJobboard.isRo ? 'Eroare.' : 'Error.');
        alert(msg);
      }
    }).fail(function(xhr){
      var msg = (AISuiteJobboard.isRo ? 'Eroare. Reîncearcă.' : 'Error. Please retry.');
      try{
        if(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) msg = xhr.responseJSON.data.message;
      }catch(e){}
      alert(msg);
    }).always(function(){
      $btn.prop('disabled', false).removeClass('ai-busy');
    });
  });
})(jQuery);
