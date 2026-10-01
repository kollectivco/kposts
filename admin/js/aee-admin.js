/**
 * AI Editorial Engine — Admin JavaScript (Fixed & Functional)
 */

/* global aeeData, jQuery */

(function ($) {
  'use strict';

  // ============================================================
  // AJAX HELPER
  // ============================================================

  function aeeAjax(action, data, onSuccess, onError) {
    return $.ajax({
      url: aeeData.ajaxUrl,
      method: 'POST',
      data: Object.assign({ action, nonce: aeeData.nonce }, data),
    })
      .done(function (res) {
        if (res.success) {
          onSuccess && onSuccess(res.data);
        } else {
          onError && onError(res.data?.message || 'حدث خطأ');
        }
      })
      .fail(function (xhr) {
        onError && onError('فشل الطلب: ' + xhr.status);
      });
  }

  // ============================================================
  // MODAL HELPERS  (fixed: IDs now match HTML)
  // ============================================================

  function openModal(backdropId) {
    // backdropId is the id of the .aee-modal-backdrop element itself
    $('#' + backdropId).addClass('open');
  }

  function closeModal() {
    $('.aee-modal-backdrop').removeClass('open');
  }

  // Close on backdrop click
  $(document).on('click', '.aee-modal-backdrop', function (e) {
    if ($(e.target).hasClass('aee-modal-backdrop')) closeModal();
  });

  // Close on X / cancel button
  $(document).on('click', '.aee-modal-close, .aee-btn-cancel', closeModal);

  // Close on Escape
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  // ============================================================
  // API KEY — SHOW / HIDE TOGGLE
  // ============================================================

  $(document).on('click', '.aee-toggle-key', function () {
    const $input = $(this).closest('.aee-api-group').find('input');
    const type   = $input.attr('type') === 'password' ? 'text' : 'password';
    $input.attr('type', type);
    $(this).text(type === 'password' ? '👁' : '🙈');
  });

  // ============================================================
  // TEST API CONNECTION
  // ============================================================

  $(document).on('click', '.aee-test-api', function () {
    const $btn   = $(this);
    const api    = $btn.data('api');
    const $input = $btn.closest('.aee-api-group').find('input');
    const key    = $input.val().trim();

    if (!key || key === '••••••••') {
      aeeShowInline($btn, 'أدخل مفتاح API أولاً', 'warning');
      return;
    }

    $btn.prop('disabled', true).text('⏳ جاري الاختبار...');

    aeeAjax(
      'aee_test_api',
      { api, key },
      function (data) {
        $btn.prop('disabled', false).text('اختبر');
        aeeShowInline($btn, '✅ ' + data.message, 'success');
      },
      function (msg) {
        $btn.prop('disabled', false).text('اختبر');
        aeeShowInline($btn, '❌ ' + msg, 'error');
      }
    );
  });

  // ============================================================
  // MANUAL FETCH
  // ============================================================

  $(document).on('click', '.aee-btn-fetch', function () {
    const $btn     = $(this);
    const sourceId = $btn.data('source-id') || 0;

    $btn.prop('disabled', true).text('⏳ جاري الجلب...');

    aeeAjax(
      'aee_manual_fetch',
      { source_id: sourceId },
      function (data) {
        $btn.prop('disabled', false).text('اجلب الآن');
        aeeShowToast(data.message, 'success');
        setTimeout(() => location.reload(), 2000);
      },
      function (msg) {
        $btn.prop('disabled', false).text('اجلب الآن');
        aeeShowToast(msg, 'error');
      }
    );
  });

  // ============================================================
  // PROCESS SINGLE QUEUE ITEM
  // ============================================================

  $(document).on('click', '.aee-btn-process', function () {
    const $btn   = $(this);
    const itemId = $btn.data('item-id');

    $btn.prop('disabled', true).text('⏳ معالجة...');

    aeeAjax(
      'aee_process_item',
      { item_id: itemId },
      function (data) {
        $btn.prop('disabled', false).text('معالجة');
        aeeShowToast(data.message, 'success');
        // Refresh row status after 3 seconds
        setTimeout(() => location.reload(), 3000);
      },
      function (msg) {
        $btn.prop('disabled', false).text('معالجة');
        aeeShowToast(msg, 'error');
      }
    );
  });

  // ============================================================
  // REWRITE EXISTING POST
  // ============================================================

  $(document).on('click', '.aee-btn-rewrite', function () {
    const $btn   = $(this);
    const postId = $btn.data('post-id');

    if (!confirm('هل تريد إعادة كتابة هذا المقال بالذكاء الاصطناعي؟')) return;

    $btn.prop('disabled', true).text('⏳ جاري الإعادة...');

    aeeAjax(
      'aee_rewrite_existing',
      { post_id: postId },
      function (data) {
        $btn.prop('disabled', false).text('أعد كتابته');
        aeeShowToast(data.message, 'success');
        setTimeout(() => location.reload(), 2000);
      },
      function (msg) {
        $btn.prop('disabled', false).text('أعد كتابته');
        aeeShowToast(msg, 'error');
      }
    );
  });

  // ============================================================
  // SOURCE MANAGEMENT
  // ============================================================

  $(document).on('click', '#aee-add-source, #aee-add-source-empty', function () {
    openSourceModal({});
  });

  $(document).on('click', '.aee-edit-source', function () {
    const $row = $(this).closest('tr');
    openSourceModal({
      id:                 $row.data('id'),
      name:               $row.data('name'),
      url:                $row.data('url'),
      type:               $row.data('type'),
      fetch_interval:     $row.data('interval'),
      is_active:          $row.data('active'),
      keywords_whitelist: $row.data('whitelist'),
      keywords_blacklist: $row.data('blacklist'),
    });
  });

  $(document).on('click', '.aee-delete-source', function () {
    if (!confirm(aeeData.strings.confirm_del)) return;
    const $row = $(this).closest('tr');
    const id   = $row.data('id');

    aeeAjax(
      'aee_delete_source',
      { id },
      function () {
        $row.fadeOut(300, () => $row.remove());
        aeeShowToast('تم حذف المصدر', 'success');
      },
      function (msg) { aeeShowToast(msg, 'error'); }
    );
  });

  $(document).on('submit', '#aee-source-form', function (e) {
    e.preventDefault();
    const data = {
      id:                 $(this).find('[name="id"]').val(),
      name:               $(this).find('[name="name"]').val().trim(),
      url:                $(this).find('[name="url"]').val().trim(),
      type:               $(this).find('[name="type"]').val(),
      fetch_interval:     $(this).find('[name="fetch_interval"]').val(),
      keywords_whitelist: $(this).find('[name="keywords_whitelist"]').val(),
      keywords_blacklist: $(this).find('[name="keywords_blacklist"]').val(),
      is_active:          $(this).find('[name="is_active"]').is(':checked') ? 1 : 0,
    };

    if (!data.name || !data.url) {
      aeeShowToast('الاسم والرابط مطلوبان', 'error');
      return;
    }

    aeeAjax(
      'aee_save_source',
      data,
      function (res) {
        closeModal();
        aeeShowToast('✅ تم حفظ المصدر بنجاح', 'success');
        setTimeout(() => location.reload(), 1000);
      },
      function (msg) { aeeShowToast(msg, 'error'); }
    );
  });

  // ============================================================
  // DICTIONARY MANAGEMENT
  // ============================================================

  $(document).on('click', '#aee-add-dict-entry', function () {
    openDictModal({});
  });

  $(document).on('click', '.aee-edit-dict', function () {
    const $row = $(this).closest('tr');
    openDictModal({
      id:         $row.data('id'),
      formal:     $row.data('formal'),
      colloquial: $row.data('colloquial'),
      context:    $row.data('context'),
    });
  });

  $(document).on('click', '.aee-delete-dict', function () {
    if (!confirm(aeeData.strings.confirm_del)) return;
    const $row = $(this).closest('tr');
    const id   = $row.data('id');

    aeeAjax(
      'aee_delete_dict_entry',
      { id },
      function () {
        $row.fadeOut(300, () => $row.remove());
        aeeShowToast('تم الحذف', 'success');
      },
      function (msg) { aeeShowToast(msg, 'error'); }
    );
  });

  $(document).on('submit', '#aee-dict-form', function (e) {
    e.preventDefault();
    const data = {
      id:         $(this).find('[name="id"]').val(),
      formal:     $(this).find('[name="formal"]').val().trim(),
      colloquial: $(this).find('[name="colloquial"]').val().trim(),
      context:    $(this).find('[name="context"]').val(),
    };

    if (!data.formal || !data.colloquial) {
      aeeShowToast('الكلمة الفصحى والعامية مطلوبتان', 'error');
      return;
    }

    aeeAjax(
      'aee_save_dict_entry',
      data,
      function () {
        closeModal();
        aeeShowToast('✅ تم حفظ الكلمة', 'success');
        setTimeout(() => location.reload(), 1000);
      },
      function (msg) { aeeShowToast(msg, 'error'); }
    );
  });

  // ============================================================
  // COLLOQUIAL INTENSITY SLIDER
  // ============================================================

  const $slider  = $('#aee-intensity-slider');
  const $display = $('#aee-intensity-value');

  if ($slider.length) {
    $slider.on('input', function () {
      const pct = Math.round(parseFloat(this.value) * 100);
      $display.text(pct + '%');
    });
    // Init on page load
    $display.text(Math.round(parseFloat($slider.val()) * 100) + '%');
  }

  // ============================================================
  // LIVE QUEUE STATS (Dashboard only)
  // ============================================================

  if ($('[data-stat]').length) {
    refreshQueueStats();
    setInterval(refreshQueueStats, 30000);
  }

  function refreshQueueStats() {
    aeeAjax('aee_get_queue_stats', {}, function (data) {
      const stats = data.stats || [];
      const map   = {};
      stats.forEach(function (s) { map[s.status] = s.count; });
      $('[data-stat]').each(function () {
        const key = $(this).data('stat');
        if (map[key] !== undefined) $(this).text(map[key]);
      });
    });
  }

  // ============================================================
  // LEARNING TRIGGER (Reports page)
  // ============================================================

  $('#aee-trigger-learning').on('click', function () {
    const $btn    = $(this);
    const $result = $('#aee-learning-result');

    $btn.prop('disabled', true).text('⏳ جاري التحديث...');

    aeeAjax(
      'aee_trigger_learning',
      {},
      function (data) {
        $btn.prop('disabled', false).text('🎓 تحديث نماذج التعلم الآن');
        $result.html(
          '<div class="aee-alert aee-alert-success">✅ ' + (data.message || 'تم التحديث') + '</div>'
        );
      },
      function (msg) {
        $btn.prop('disabled', false).text('🎓 تحديث نماذج التعلم الآن');
        $result.html('<div class="aee-alert aee-alert-error">❌ ' + msg + '</div>');
      }
    );
  });

  // ============================================================
  // LIVE DICT SEARCH
  // ============================================================

  $('#aee-dict-search').on('input', function () {
    const q = this.value.toLowerCase().trim();
    $('#aee-dict-table tbody tr').each(function () {
      $(this).toggle(!q || this.textContent.toLowerCase().includes(q));
    });
  });

  // ============================================================
  // SOURCE / DICT MODAL OPENERS
  // ============================================================

  function openSourceModal(data) {
    const $form = $('#aee-source-form');
    $form.find('[name="id"]').val(data.id || '');
    $form.find('[name="name"]').val(data.name || '');
    $form.find('[name="url"]').val(data.url || '');
    $form.find('[name="type"]').val(data.type || 'rss');
    $form.find('[name="fetch_interval"]').val(data.fetch_interval || 3600);
    $form.find('[name="is_active"]').prop('checked', data.is_active == null ? true : data.is_active != 0);
    $form.find('[name="keywords_whitelist"]').val(data.keywords_whitelist || '');
    $form.find('[name="keywords_blacklist"]').val(data.keywords_blacklist || '');
    $('#aee-modal-source-title').text(data.id ? 'تعديل مصدر' : 'إضافة مصدر جديد');
    openModal('aee-modal-source-backdrop');   // ← fixed: ID of the backdrop div
  }

  function openDictModal(data) {
    const $form = $('#aee-dict-form');
    $form.find('[name="id"]').val(data.id || '');
    $form.find('[name="formal"]').val(data.formal || '');
    $form.find('[name="colloquial"]').val(data.colloquial || '');
    $form.find('[name="context"]').val(data.context || 'general');
    $('#aee-modal-dict-title').text(data.id ? 'تعديل كلمة' : 'إضافة كلمة جديدة');
    openModal('aee-modal-dict-backdrop');     // ← fixed: ID of the backdrop div
  }

  // ============================================================
  // MODAL SAVE BUTTONS (alternative to form submit event)
  // ============================================================

  $('#aee-save-source-btn').on('click', function () {
    $('#aee-source-form').trigger('submit');
  });

  $('#aee-save-dict-btn').on('click', function () {
    $('#aee-dict-form').trigger('submit');
  });

  // ============================================================
  // TOAST NOTIFICATION
  // ============================================================

  function aeeShowToast(message, type) {
    const colors = {
      success: '#00a32a',
      error:   '#d63638',
      info:    '#2271b1',
      warning: '#dba617',
    };
    const $toast = $('<div>')
      .text(message)
      .css({
        position:     'fixed',
        bottom:       '24px',
        right:        '24px',
        background:   colors[type] || colors.info,
        color:        '#fff',
        padding:      '12px 20px',
        borderRadius: '6px',
        boxShadow:    '0 4px 16px rgba(0,0,0,.22)',
        zIndex:       99999,
        fontSize:     '14px',
        direction:    'rtl',
        maxWidth:     '360px',
        lineHeight:   '1.5',
      });

    $('body').append($toast);
    setTimeout(function () {
      $toast.fadeOut(400, function () { $(this).remove(); });
    }, 3500);
  }

  // ============================================================
  // INLINE STATUS MESSAGE (next to a button)
  // ============================================================

  function aeeShowInline($nearBtn, message, type) {
    $nearBtn.siblings('.aee-inline-msg').remove();

    const colors = {
      success: { bg: '#edfaef', color: '#007017', border: '#68de7c' },
      error:   { bg: '#fcf0f1', color: '#8a2424', border: '#f1a1a8' },
      warning: { bg: '#fcf9e8', color: '#9a6700', border: '#f0c33c' },
    };
    const c = colors[type] || colors.info;

    const $msg = $('<span>')
      .addClass('aee-inline-msg')
      .text(message)
      .css({
        display:      'inline-block',
        padding:      '3px 10px',
        marginRight:  '8px',
        borderRadius: '4px',
        fontSize:     '12px',
        background:   c.bg,
        color:        c.color,
        border:       '1px solid ' + c.border,
      });

    $nearBtn.after($msg);
    setTimeout(function () {
      $msg.fadeOut(400, function () { $(this).remove(); });
    }, 5000);
  }

})(jQuery);
