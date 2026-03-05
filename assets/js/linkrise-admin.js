/* global jQuery, LR */
;(function($) {
  'use strict';
  var aj  = LR.ajax;
  var nce = LR.nonce;
  var sit = LR.site;
  var pfx = LR.prefix;
  var C   = LR.confirm;

  /* ── Helpers ─────────────────────────────────────────────────────────── */
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function post(action, data, cb) {
    $.post(aj, $.extend({ action: action, nonce: nce }, data), cb, 'json')
     .fail(function() { toast('Request failed. Please try again.', 'error'); });
  }

  function toast(msg, type) {
    var $t = $('<div class="lr-toast lr-toast-'+(type||'info')+'">'+esc(msg)+'</div>');
    $('body').append($t);
    setTimeout(function(){ $t.addClass('lr-toast-show'); }, 10);
    setTimeout(function(){ $t.removeClass('lr-toast-show'); setTimeout(function(){ $t.remove(); }, 350); }, 3500);
  }

  function openModal(id) { $('#'+id).show(); $('body').addClass('lr-modal-open'); }
  function closeModal(id){ $('#'+id).hide(); $('body').removeClass('lr-modal-open'); }

  /* ── TABS ─────────────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-nav-btn', function() {
    var tab = $(this).data('tab');
    $('.lr-nav-btn').removeClass('lr-nav-active');
    $(this).addClass('lr-nav-active');
    $('.lr-panel').hide();
    $('#lr-panel-'+tab).show();
    if (tab === 'analytics') loadAnalytics();
  });

  /* ── ANALYTICS ───────────────────────────────────────────────────────── */
  function loadAnalytics() {
    post('lr_get_analytics', {}, function(res) {
      if (!res || !res.success) { toast('Could not load analytics.', 'error'); return; }
      var d = res.data;

      $('#st-total').text( numFmt(d.total_clicks) );
      $('#st-today').text( numFmt(d.today) );
      $('#st-week').text(  numFmt(d.week) );
      $('#st-links').text( numFmt(d.total_links) );
      $('#st-active').text(numFmt(d.active_links) );

      // Countries
      renderBars('#lr-countries', d.countries, 'c');
      // Devices
      renderBars('#lr-devices', d.devices, 'c');

      // Top links
      var $tb = $('#lr-top-tbl tbody').empty();
      if (d.top_links && d.top_links.length) {
        d.top_links.forEach(function(l) {
          var short = sit + (l.custom_prefix || pfx) + '/' + l.shortcode;
          $tb.append('<tr><td><a href="'+esc(short)+'" target="_blank">'+esc(short)+'</a></td><td title="'+esc(l.long_url)+'">'+esc(l.long_url.length>55?l.long_url.slice(0,55)+'…':l.long_url)+'</td><td><strong>'+numFmt(+l.click_count)+'</strong></td></tr>');
        });
      } else {
        $tb.html('<tr><td colspan="3" class="lr-cell-empty">No clicks recorded yet.</td></tr>');
      }

      // Line chart
      if (d.daily && d.daily.length) {
        renderChart(d.daily);
      }
    });
  }

  function numFmt(n) { return Number(n||0).toLocaleString(); }

  function renderBars(sel, rows, key) {
    var $c = $(sel).empty();
    if (!rows || !rows.length) { $c.html('<p class="lr-loading">No data yet.</p>'); return; }
    var max = Math.max.apply(null, rows.map(function(r){ return +r[key]; }));
    rows.slice(0,8).forEach(function(r) {
      var pct = Math.round((+r[key]/max)*100);
      var lbl = r.country || r.device || r.browser || '';
      $c.append(
        '<div class="lr-bar-row">'+
        '<span class="lr-bar-lbl">'+esc(lbl)+'</span>'+
        '<div class="lr-bar-track"><div class="lr-bar-fill" style="width:'+pct+'%"></div></div>'+
        '<span class="lr-bar-num">'+numFmt(+r[key])+'</span>'+
        '</div>'
      );
    });
  }

  function renderChart(daily) {
    function draw() {
      var canvas = document.getElementById('lr-daily-chart');
      if (!canvas) return;
      if (canvas._chart) { canvas._chart.destroy(); }
      canvas._chart = new window.Chart(canvas, {
        type: 'line',
        data: {
          labels: daily.map(function(r){ return r.d; }),
          datasets: [{
            label: 'Clicks',
            data: daily.map(function(r){ return +r.c; }),
            borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)',
            borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: '#6366f1'
          }]
        },
        options: { responsive:true, plugins:{ legend:{display:false} }, scales:{ y:{ beginAtZero:true, ticks:{precision:0} } } }
      });
    }
    if (window.Chart) { draw(); }
    else {
      var s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
      s.onload = draw;
      document.head.appendChild(s);
    }
  }

  // Auto-load if analytics tab is active on page open
  $(function() {
    if ($('#lr-panel-analytics').is(':visible')) loadAnalytics();
  });

  $(document).on('click', '#btn-wipe', function() {
    if (!confirm(C.wipe)) return;
    post('lr_wipe_analytics', {}, function(res) {
      if (res && res.success) { loadAnalytics(); toast('Analytics wiped.', 'success'); }
    });
  });

  /* ── FLUSH RULES ──────────────────────────────────────────────────────── */
  $(document).on('click', '#btn-flush', function() {
    var $b = $(this).prop('disabled', true).text('Flushing…');
    post('lr_flush_rules', {}, function(res) {
      $b.prop('disabled', false).text('🔄 Flush Rewrite Rules');
      if (res && res.success) { toast(res.data.msg, 'success'); }
      else { toast('Flush failed. Try deactivating and reactivating the plugin.', 'error'); }
    });
  });

  /* ── ADD / EDIT LINK ──────────────────────────────────────────────────── */
  $(document).on('click', '#btn-add', function() {
    resetModal(); $('#lr-modal-ttl').text('Add New Link'); openModal('lr-modal-link');
  });

  $(document).on('click', '.lr-edit-btn', function() {
    var raw = $(this).attr('data-ldata') || '{}';
    var d;
    try { d = JSON.parse(raw); } catch(e) { toast('Could not parse link data.', 'error'); return; }
    resetModal();
    $('#lr-modal-ttl').text('Edit Link');
    $('#m-id').val(d.id||0);
    $('#m-url').val(d.url||'');
    $('#m-code').val(d.code||'');
    $('#m-expiry').val(d.expiry||'');
    $('#m-cat').val(d.cat||'');
    $('#m-notes').val(d.notes||'');
    $('#m-limit').val(d.limit||0);
    $('#m-fb').val(d.fb||'');
    if (d.hasPw) { $('#m-pw').attr('placeholder', '(blank = keep existing password)'); }
    openModal('lr-modal-link');
  });

  $(document).on('click', '#btn-modal-save', function() {
    var url = $('#m-url').val().trim();
    if (!url || (!url.startsWith('http://') && !url.startsWith('https://'))) {
      toast('Please enter a valid URL (http:// or https://)', 'error'); return;
    }
    var $b = $(this).prop('disabled', true).text('Saving…');
    post('lr_save_link', {
      id:          $('#m-id').val(),
      long_url:    url,
      shortcode:   $('#m-code').val().trim(),
      password:    $('#m-pw').val(),
      expiry:      $('#m-expiry').val(),
      category:    $('#m-cat').val().trim(),
      notes:       $('#m-notes').val().trim(),
      click_limit: $('#m-limit').val(),
      fallback:    $('#m-fb').val().trim(),
    }, function(res) {
      $b.prop('disabled', false).text('Save Link');
      if (res && res.success) {
        closeModal('lr-modal-link');
        toast(res.data.msg, 'success');
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        toast((res && res.data && res.data.msg) ? res.data.msg : 'Save failed.', 'error');
      }
    });
  });

  function resetModal() {
    '#m-id,#m-url,#m-code,#m-pw,#m-cat,#m-notes,#m-expiry,#m-fb'.split(',').forEach(function(id){
      $(id).val('');
    });
    $('#m-limit').val('0');
    $('#m-pw').attr('type','password').attr('placeholder','Leave blank to keep existing password');
    $('.lr-pw-tog').text('Show');
  }

  /* Password show/hide in modal */
  $(document).on('click', '.lr-pw-tog', function() {
    var $inp = $(this).siblings('.lr-pw-inp');
    var isPw = $inp.attr('type') === 'password';
    $inp.attr('type', isPw ? 'text' : 'password');
    $(this).text(isPw ? 'Hide' : 'Show');
  });

  /* ── DELETE LINK ──────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-del-btn', function() {
    if (!confirm(C.del)) return;
    var id = $(this).data('id');
    post('lr_delete_link', { id: id }, function(res) {
      if (res && res.success) {
        $('tr[data-id="'+id+'"]').fadeOut(250, function(){ $(this).remove(); });
        toast('Link deleted.', 'success');
      }
    });
  });

  /* ── TOGGLE STATUS ───────────────────────────────────────────────────── */
  $(document).on('click', '.lr-toggle-btn', function() {
    var $b = $(this), id = $b.data('id');
    post('lr_toggle_status', { id: id }, function(res) {
      if (res && res.success) {
        var st = res.data.status;
        $b.data('status', st).text(st === 'active' ? 'Pause' : 'Resume');
        $b.closest('tr').find('.lr-pill')
          .removeClass('lr-pill-active lr-pill-paused lr-pill-expired')
          .addClass('lr-pill-'+st).text(st === 'active' ? 'Active' : 'Paused');
        toast('Status updated to '+st+'.', 'success');
      }
    });
  });

  /* ── COPY URL ─────────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-copy-btn', function() {
    var url = $(this).data('url'), $b = $(this), orig = $b.text();
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(function(){
        $b.text('✅').addClass('lr-copied');
        setTimeout(function(){ $b.text(orig).removeClass('lr-copied'); }, 2200);
      });
    }
  });

  /* ── QR CODE ──────────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-qr-btn', function() {
    var url = $(this).data('url');
    $('#lr-qr-img').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='+encodeURIComponent(url));
    $('#lr-qr-dl').attr('href', 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data='+encodeURIComponent(url));
    openModal('lr-modal-qr');
  });

  /* ── CLICK HISTORY ───────────────────────────────────────────────────── */
  $(document).on('click', '.lr-hist-btn', function() {
    var id = $(this).data('id');
    $('#lr-clicks-bd').html('<p class="lr-loading">Loading…</p>');
    openModal('lr-modal-clicks');
    post('lr_get_clicks', { link_id: id }, function(res) {
      if (!res || !res.success || !res.data.clicks || !res.data.clicks.length) {
        $('#lr-clicks-bd').html('<p class="lr-loading">No clicks recorded yet.</p>'); return;
      }
      var html = '<div class="lr-tbl-wrap"><table class="lr-tbl"><thead><tr><th>Date</th><th>Country</th><th>Device</th><th>Browser</th><th>OS</th><th>Referrer</th></tr></thead><tbody>';
      res.data.clicks.forEach(function(c) {
        html += '<tr><td>'+esc(c.clicked_at)+'</td><td>'+esc(c.country||'—')+'</td><td>'+esc(c.device||'—')+'</td><td>'+esc(c.browser||'—')+'</td><td>'+esc(c.os||'—')+'</td><td title="'+esc(c.referrer||'')+'">'+esc((c.referrer||'Direct').slice(0,40))+'</td></tr>';
      });
      html += '</tbody></table></div>';
      $('#lr-clicks-bd').html(html);
    });
  });

  /* ── BULK SELECT ─────────────────────────────────────────────────────── */
  $(document).on('change', '#lr-chk-all', function() {
    $('.lr-row-chk').prop('checked', $(this).is(':checked'));
    updateBulkBar();
  });
  $(document).on('change', '.lr-row-chk', updateBulkBar);

  function updateBulkBar() {
    var n = $('.lr-row-chk:checked').length;
    n > 0 ? $('#lr-bulk-bar').show().find('#lr-bulk-cnt').text(n+' selected') : $('#lr-bulk-bar').hide();
  }

  function getSelected() { return $('.lr-row-chk:checked').map(function(){ return $(this).val(); }).get(); }

  $(document).on('click', '#btn-bulk-del', function() {
    var ids = getSelected(); if (!ids.length) return;
    if (!confirm(C.bulkDel)) return;
    post('lr_bulk_delete', { ids: ids }, function(res) {
      if (res && res.success) {
        ids.forEach(function(id){ $('tr[data-id="'+id+'"]').fadeOut(200, function(){ $(this).remove(); }); });
        updateBulkBar(); toast(ids.length+' links deleted.', 'success');
      }
    });
  });

  $(document).on('click', '#btn-bulk-exp', function() {
    var ids = getSelected(); if (!ids.length) return;
    if (!confirm('Expire '+ids.length+' link(s) now?')) return;
    post('lr_bulk_expire', { ids: ids }, function(res) {
      if (res && res.success) { toast(ids.length+' links expired.', 'success'); setTimeout(function(){ location.reload(); }, 700); }
    });
  });

  /* ── EXPORT / BACKUP ──────────────────────────────────────────────────── */
  $(document).on('click', '#btn-csv', function(e) {
    e.preventDefault();
    window.location.href = aj+'?action=lr_export_csv&nonce='+encodeURIComponent(nce);
  });
  $(document).on('click', '#btn-json', function(e) {
    e.preventDefault();
    window.location.href = aj+'?action=lr_export_json&nonce='+encodeURIComponent(nce);
  });
  $(document).on('click', '#btn-backup', function(e) {
    e.preventDefault();
    window.location.href = aj+'?action=lr_full_backup&nonce='+encodeURIComponent(nce);
  });

  /* ── IMPORT ───────────────────────────────────────────────────────────── */
  $(document).on('change', '#import-file', function() {
    var file = this.files[0]; if (!file) return;
    var fd = new FormData(); fd.append('action','lr_import'); fd.append('nonce',nce); fd.append('file',file);
    $.ajax({ url:aj, method:'POST', data:fd, processData:false, contentType:false,
      success: function(res) {
        if (res && res.success) { toast('Imported '+res.data.imported+' links ('+res.data.skipped+' skipped).', 'success'); setTimeout(function(){ location.reload(); }, 900); }
        else { toast('Import failed.', 'error'); }
      }, error: function(){ toast('Upload failed.', 'error'); }
    });
    this.value = '';
  });

  $(document).on('change', '#restore-file', function() {
    if (!confirm(C.restore)) { this.value=''; return; }
    var file = this.files[0]; if (!file) return;
    var fd = new FormData(); fd.append('action','lr_full_restore'); fd.append('nonce',nce); fd.append('file',file);
    $.ajax({ url:aj, method:'POST', data:fd, processData:false, contentType:false,
      success: function(res) {
        if (res && res.success) { toast('Restored '+res.data.imported+' links.', 'success'); setTimeout(function(){ location.reload(); }, 900); }
        else { toast('Restore failed.', 'error'); }
      }, error: function(){ toast('Upload failed.', 'error'); }
    });
    this.value = '';
  });

  /* ── REPORTS ─────────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-dismiss-btn', function() {
    var id = $(this).data('id');
    post('lr_dismiss_report', { id:id }, function(res) {
      if (res && res.success) { $('#rpt-'+id).fadeOut(200, function(){ $(this).remove(); }); toast('Report dismissed.', 'success'); }
    });
  });

  $(document).on('click', '.lr-del-report-btn', function() {
    if (!confirm('Delete this link and dismiss the report?')) return;
    var id = $(this).data('id'), sc = $(this).data('sc');
    post('lr_delete_report_link', { id:id, sc:sc }, function(res) {
      if (res && res.success) { $('#rpt-'+id).fadeOut(200, function(){ $(this).remove(); }); toast('Link and report deleted.', 'success'); }
    });
  });

  /* ── MODAL CLOSE ──────────────────────────────────────────────────────── */
  $(document).on('click', '.lr-modal-cls', function() {
    var target = $(this).data('modal');
    if (target) closeModal(target);
  });
  $(document).on('click', '.lr-modal', function(e) {
    if ($(e.target).hasClass('lr-modal')) closeModal($(e.target).attr('id'));
  });
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape') $('.lr-modal:visible').each(function(){ closeModal($(this).attr('id')); });
  });

})(jQuery);
