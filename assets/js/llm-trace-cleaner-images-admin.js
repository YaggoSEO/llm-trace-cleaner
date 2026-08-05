(function ($) {
  'use strict';

  function post(action, nonceKey, data) {
    data = data || {};
    data.action = action;
    data.nonce = llmtcImages.nonces[nonceKey];
    return $.post(llmtcImages.ajaxUrl, data);
  }

  var batchId = null;
  var ticking = false;

  function updateProgress(state) {
    var pct = state.total ? Math.round((state.processed / state.total) * 100) : 0;
    $('#llmtc-batch-progress').show();
    $('#llmtc-batch-bar').css('width', pct + '%');
    $('#llmtc-batch-status').text(
      state.status + ' — ' + state.processed + '/' + state.total +
      ' (cambiados: ' + state.changed + ', omitidos: ' + state.skipped + ', fallos: ' + state.failed + ')'
    );
  }

  function tick() {
    if (!batchId || ticking) return;
    ticking = true;
    post('llmtc_image_batch_tick', 'batch', { batch_id: batchId })
      .done(function (resp) {
        ticking = false;
        if (!resp || !resp.success) {
          $('#llmtc-batch-status').text((resp && resp.data && resp.data.message) || 'Error en lote');
          $('#llmtc-batch-cancel').prop('disabled', true);
          return;
        }
        updateProgress(resp.data);
        if (resp.data.status === 'running') {
          setTimeout(tick, 200);
        } else {
          $('#llmtc-batch-cancel').prop('disabled', true);
          batchId = null;
        }
      })
      .fail(function () {
        ticking = false;
        $('#llmtc-batch-status').text('Error de red');
      });
  }

  $('#llmtc-batch-start').on('click', function () {
    post('llmtc_image_batch_start', 'batch', {
      profile: $('#llmtc-batch-profile').val(),
      limit: $('#llmtc-batch-limit').val(),
      mime: $('#llmtc-batch-mime').val(),
      dry_run: $('#llmtc-batch-dry').is(':checked') ? 1 : 0
    }).done(function (resp) {
      if (!resp || !resp.success) {
        alert((resp && resp.data && resp.data.message) || 'No se pudo iniciar');
        return;
      }
      batchId = resp.data.id;
      $('#llmtc-batch-cancel').prop('disabled', false);
      updateProgress(resp.data);
      tick();
    });
  });

  $('#llmtc-batch-cancel').on('click', function () {
    if (!batchId) return;
    post('llmtc_image_batch_cancel', 'batch', { batch_id: batchId }).done(function (resp) {
      if (resp && resp.success) {
        updateProgress(resp.data);
        batchId = null;
        $('#llmtc-batch-cancel').prop('disabled', true);
      }
    });
  });

  function showReport(data) {
    $('#llmtc-report-out').text(JSON.stringify(data, null, 2));
  }

  $('#llmtc-report-audit').on('click', function () {
    var id = $('#llmtc-report-id').val();
    post('llmtc_image_audit', 'audit', { attachment_id: id }).done(function (resp) {
      showReport(resp && resp.data ? resp.data : resp);
    });
  });

  $('#llmtc-report-dry').on('click', function () {
    post('llmtc_image_process', 'process', {
      attachment_id: $('#llmtc-report-id').val(),
      profile: $('#llmtc-report-profile').val(),
      dry_run: 1,
      force: 1
    }).done(function (resp) {
      showReport(resp && resp.data ? resp.data : resp);
    });
  });

  $('#llmtc-report-apply').on('click', function () {
    if (!confirm('¿Aplicar perfil? Revisa dry run y backups antes.')) return;
    post('llmtc_image_process', 'process', {
      attachment_id: $('#llmtc-report-id').val(),
      profile: $('#llmtc-report-profile').val(),
      dry_run: 0,
      force: 1
    }).done(function (resp) {
      showReport(resp && resp.data ? resp.data : resp);
    });
  });

  $('#llmtc-report-restore').on('click', function () {
    post('llmtc_image_restore', 'restore', {
      attachment_id: $('#llmtc-report-id').val(),
      regen_thumbs: 1
    }).done(function (resp) {
      showReport(resp && resp.data ? resp.data : resp);
    });
  });
})(jQuery);
