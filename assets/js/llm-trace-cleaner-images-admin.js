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

  function downloadText(filename, text) {
    var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  $(document).on('click', '#llmtc-report-export-json', function () {
    post('llmtc_image_export_report', 'audit', {
      attachment_id: $('#llmtc-report-id').val(),
      format: 'json'
    }).done(function (resp) {
      if (resp && resp.success) {
        downloadText('llmtc-report-' + $('#llmtc-report-id').val() + '.json', resp.data.content);
      }
    });
  });

  $(document).on('click', '#llmtc-report-export-csv', function () {
    post('llmtc_image_export_report', 'audit', {
      attachment_id: $('#llmtc-report-id').val(),
      format: 'csv'
    }).done(function (resp) {
      if (resp && resp.success) {
        downloadText('llmtc-report-' + $('#llmtc-report-id').val() + '.csv', resp.data.content);
      }
    });
  });

  function setNested(obj, path, value) {
    var parts = path.split('.');
    var cur = obj;
    for (var i = 0; i < parts.length - 1; i++) {
      if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object') cur[parts[i]] = {};
      cur = cur[parts[i]];
    }
    var last = parts[parts.length - 1];
    if (last === 'keywords') {
      cur[last] = String(value).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    } else {
      cur[last] = value;
    }
  }

  $('#llmtc-profile-save').on('click', function () {
    var meta = {};
    $('.llmtc-meta').each(function () {
      var path = $(this).data('path');
      if (!path) return;
      setNested(meta, path, $(this).val());
    });
    var profile = {
      id: $('#llmtc-profile-id').val(),
      description: $('#llmtc-profile-desc').val(),
      metadata: meta
    };
    if ($('#llmtc-profile-name').length) {
      profile.name = $('#llmtc-profile-name').val();
      profile.force = 1;
    }
    post('llmtc_image_save_profile', 'profile', { profile: JSON.stringify(profile) })
      .done(function (resp) {
        $('#llmtc-profile-save-msg').text(resp && resp.success ? 'Guardado.' : ((resp && resp.data && resp.data.message) || 'Error'));
      });
  });

  $('#llmtc-profile-duplicate').on('click', function () {
    var source = $(this).data('source');
    var newId = window.prompt('ID del nuevo perfil (a-z0-9_-):', source + '_copy');
    if (!newId) return;
    post('llmtc_image_duplicate_profile', 'profile', { source: source, new_id: newId })
      .done(function (resp) {
        if (resp && resp.success) {
          window.location.search = '?page=llm-trace-cleaner-images&tab=profiles&edit=' + encodeURIComponent(resp.data.id);
        } else {
          alert((resp && resp.data && resp.data.message) || 'Error');
        }
      });
  });

  $('#llmtc-profile-export').on('click', function () {
    post('llmtc_image_export_profiles', 'profile', {})
      .done(function (resp) {
        if (resp && resp.success) {
          downloadText('llmtc-profiles.json', JSON.stringify(resp.data.profiles, null, 2));
        }
      });
  });

  $('#llmtc-profile-import').on('click', function () {
    var json = $('#llmtc-profile-import-json').val();
    post('llmtc_image_import_profiles', 'profile', { json: json })
      .done(function (resp) {
        alert(resp && resp.success ? 'Importado. Recarga la página.' : ((resp && resp.data && resp.data.message) || 'Error'));
        if (resp && resp.success) window.location.reload();
      });
  });

  $('#llmtc-test-exiftool').on('click', function () {
    post('llmtc_image_test_exiftool', 'settings', { path: $('#llmtc-exiftool-path').val() })
      .done(function (resp) {
        if (resp && resp.success) {
          if (resp.data && resp.data.path) {
            $('#llmtc-exiftool-path').val(resp.data.path);
          }
          $('#llmtc-exiftool-msg').text('OK v' + resp.data.version + ' @ ' + resp.data.path);
        } else {
          $('#llmtc-exiftool-msg').text((resp && resp.data && resp.data.message) || 'Error');
        }
      });
  });
})(jQuery);
