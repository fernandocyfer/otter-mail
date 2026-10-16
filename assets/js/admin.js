(function ($) {
	'use strict';

	if (typeof ottermailAdmin === 'undefined') {
		return;
	}

	var $root = $('.ottermail-wrap');
	if (!$root.length) {
		return;
	}

	var ns = '.ottermail-wrap';
	var previewTimer = null;

	$(document).on('click', ns + ' #ottermail-add-condition', function () {
		var $row = $(ns + ' #ottermail-conditions tbody tr').first().clone();
		$row.find('input').val('');
		$row.find('select').each(function () {
			this.selectedIndex = 0;
		});
		$(ns + ' #ottermail-conditions tbody').append($row);
	});

	$(document).on('click', ns + ' .ottermail-remove-condition', function () {
		var $tbody = $(ns + ' #ottermail-conditions tbody');
		if ($tbody.find('tr').length <= 1) {
			return;
		}
		$(this).closest('tr').remove();
	});

	$(document).on('click', ns + ' #ottermail-add-step', function () {
		var $rows = $(ns + ' #ottermail-steps tbody tr');
		if ($rows.length >= 5) {
			return;
		}
		var $row = $rows.first().clone();
		$row.find('input').val('1');
		$row.find('select').each(function () {
			this.selectedIndex = 0;
		});
		$(ns + ' #ottermail-steps tbody').append($row);
	});

	$(document).on('click', ns + ' #ottermail-estimate-btn', function () {
		var $btn = $(this);
		$btn.prop('disabled', true);
		$(ns + ' #ottermail-estimate-value').text(ottermailAdmin.i18n.estimating);
		var payload = $(ns + ' #ottermail-audience-form').serializeArray().filter(function (item) {
			return item.name !== 'action';
		});
		payload.push({ name: 'action', value: 'ottermail_estimate_audience' });
		payload.push({ name: 'nonce', value: ottermailAdmin.nonce });
		$.post(ottermailAdmin.ajaxUrl, payload)
			.done(function (res) {
				if (res && res.success) {
					$(ns + ' #ottermail-estimate-value').text(res.data.count);
				} else {
					$(ns + ' #ottermail-estimate-value').text('—');
				}
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$(document).on('click', ns + ' .ottermail-preview-btn', function () {
		var $btn = $(this);
		var $panel = $btn.closest('.ottermail-preview-panel, td, .ottermail-wrap');
		var $frame = $panel.find('.ottermail-preview-frame').first();
		if (!$frame.length) {
			$frame = $(ns + ' #ottermail-email-preview');
		}
		$frame.removeAttr('hidden');

		var payload = collectPreviewPayload($btn);
		var requestId = ($frame.data('ottermailPreviewRequest') || 0) + 1;
		$frame.data('ottermailPreviewRequest', requestId);
		$.post(ottermailAdmin.ajaxUrl, payload)
			.done(function (res) {
				if ($frame.data('ottermailPreviewRequest') !== requestId) {
					return;
				}
				if (!res || !res.success) {
					window.alert((res && res.data && res.data.message) ? res.data.message : ottermailAdmin.i18n.previewError);
					return;
				}
				writePreview($frame, res.data.html);
				if (res.data.subject) {
					var $subj = $panel.find('.ottermail-preview-subject');
					$subj.removeAttr('hidden').find('span').text(res.data.subject);
				}
			})
			.fail(function () {
				window.alert(ottermailAdmin.i18n.previewError);
			});
	});

	function queueTemplatePreview() {
		window.clearTimeout(previewTimer);
		previewTimer = window.setTimeout(function () {
			$(ns + ' #ottermail-preview-panel .ottermail-preview-btn').first().trigger('click');
		}, 500);
	}

	$(document).on('input change', ns + ' #ottermail-template-form :input', function () {
		if ($(this).is('[name="action"], [name="template_id"], [name="return_campaign"]')) {
			return;
		}
		queueTemplatePreview();
	});

	$(document).on('click', ns + ' .ottermail-preview-mode', function () {
		var mode = $(this).data('mode');
		var $panel = $(this).closest('.ottermail-preview-panel, td, .ottermail-wrap');
		$panel.find('.ottermail-preview-mode').removeClass('is-active');
		$(this).addClass('is-active');
		var $frame = $panel.find('.ottermail-preview-frame');
		$frame.removeClass('is-desktop is-mobile').addClass('is-' + mode);
		var html = $frame.data('ottermailHtml');
		if (html) {
			window.setTimeout(function () {
				writePreview($frame, html);
			}, 40);
		}
	});

	function syncCampaignTemplateActions() {
		var $sel = $(ns + ' select#template_id');
		if (!$sel.length) {
			return;
		}

		var hasTpl = parseInt($sel.val(), 10) > 0;
		$(ns + ' .ottermail-needs-template').prop('disabled', !hasTpl);

		var isSystem = $sel.find('option:selected').data('system') == 1;
		var $hint = $(ns + ' .ottermail-template-system-hint');
		if ($hint.length) {
			if (isSystem) {
				$hint.removeAttr('hidden');
			} else {
				$hint.attr('hidden', 'hidden');
			}
		}
	}

	$(document).on('change', ns + ' select#template_id', function () {
		syncCampaignTemplateActions();
		if (parseInt($(this).val(), 10) > 0) {
			$(ns + ' .ottermail-preview-btn').first().trigger('click');
		}
	});

	$(window).on('load', function () {
		syncCampaignTemplateActions();
		var shouldPreview = $(ns + ' #ottermail-template-form').length || $(ns + ' .ottermail-campaign-preview').length;
		var templateId = parseInt($(ns + ' #template_id').val(), 10) || 0;
		if (shouldPreview && ($(ns + ' #ottermail-template-form').length || templateId > 0)) {
			window.setTimeout(function () {
				$(ns + ' .ottermail-preview-btn').first().trigger('click');
			}, 400);
		}
	});

	function collectPreviewPayload($btn) {
		var payload = {
			action: 'ottermail_preview_template',
			nonce: ottermailAdmin.nonce,
			template_id: $btn.data('template') || $(ns + ' #template_id').val() || 0
		};

		var $form = $(ns + ' #ottermail-template-form');
		if (!$form.length) {
			return payload;
		}

		payload.name = $form.find('[name="name"]').val() || '';
		payload.subject = $form.find('[name="subject"]').val() || '';
		payload.preheader = $form.find('[name="preheader"]').val() || '';
		$form.trigger('ottermail-persist-layout');
		payload.logo_url = $form.find('[name="logo_url"]').val() || '';
		payload.heading = $form.find('[name="heading"]').val() || '';
		payload.body = getTemplateBody();
		payload.cta_label = $form.find('[name="cta_label"]').val() || '';
		payload.cta_url = $form.find('[name="cta_url"]').val() || '';
		payload.show_products = ( $form.find('[name="show_products"]').val() === '1' || $form.find('[name="show_products"]').is(':checked') ) ? 1 : 0;
		payload.product_ids = $form.find('[name="product_ids"]').val() || '';
		payload.coupon_code = $form.find('[name="coupon_code"]').val() || '';
		payload.footer = $form.find('[name="footer"]').val() || '';
		payload.layout_json = $form.find('[name="layout_json"]').val() || '';
		return payload;
	}

	function getTemplateBody() {
		if (typeof window.tinymce !== 'undefined') {
			var editor = window.tinymce.get('ottermail_body');
			if (editor) {
				return editor.getContent();
			}
		}
		var $area = $(ns + ' #ottermail_body');
		return $area.length ? $area.val() : '';
	}

	function writePreview($frame, html) {
		$frame.data('ottermailHtml', html);
		var iframe = $frame.find('iframe').get(0);
		if (!iframe) {
			iframe = document.createElement('iframe');
			iframe.title = 'Preview';
			$frame.empty().append(iframe);
		}
		iframe.style.width = $frame.hasClass('is-mobile') ? '390px' : '100%';
		var doc = iframe.contentWindow.document;
		doc.open();
		doc.write(html);
		doc.close();
	}

	$(document).on('click', ns + ' .ottermail-test-btn', function () {
		var email = window.prompt('Email de teste', '');
		if (!email) {
			return;
		}
		var $btn = $(this);
		$btn.prop('disabled', true).text(ottermailAdmin.i18n.sending);
		$.post(ottermailAdmin.ajaxUrl, {
			action: 'ottermail_send_test',
			nonce: ottermailAdmin.nonce,
			template_id: $btn.data('template') || $(ns + ' #template_id').val(),
			subject: $btn.data('subject') || $(ns + ' #subject').val(),
			email: email
		}).done(function (res) {
			window.alert(res && res.success ? res.data.message : (res.data && res.data.message ? res.data.message : 'Erro'));
		}).fail(function (xhr) {
			var msg = ottermailAdmin.i18n.previewError || 'Erro';
			if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			} else if (xhr.responseText) {
				try {
					var parsed = JSON.parse(xhr.responseText);
					if (parsed.data && parsed.data.message) {
						msg = parsed.data.message;
					}
				} catch (e) {
					msg = xhr.status ? ('HTTP ' + xhr.status) : msg;
				}
			}
			window.alert(msg);
		}).always(function () {
			$btn.prop('disabled', false).text(ottermailAdmin.i18n.testEmail || 'Enviar teste');
		});
	});

	function toggleRecipientSource() {
		var src = $(ns + ' input[name="recipient_source"]:checked').val();
		if (!src) {
			return;
		}
		$(ns + ' .ottermail-source-audience').prop('hidden', src !== 'audience');
		$(ns + ' .ottermail-source-list').prop('hidden', src !== 'list');
	}

	$(document).on('change', ns + ' input[name="recipient_source"]', toggleRecipientSource);
	toggleRecipientSource();

	$(document).on('change', ns + ' #ottermail-list-form input[name="source"]', function () {
		var src = $(ns + ' #ottermail-list-form input[name="source"]:checked').val();
		$(ns + ' .ottermail-list-source-panel').each(function () {
			$(this).prop('hidden', $(this).data('source') !== src);
		});
	});

	$(document).on('click', ns + ' .ottermail-confirm-send', function (e) {
		if (!window.confirm(ottermailAdmin.i18n.confirmSend)) {
			e.preventDefault();
		}
	});

	var mediaFrame;

	function setMediaPreview($field, url) {
		var $preview = $field.find('.ottermail-media-preview');
		var $img = $preview.find('img');
		var $remove = $field.find('.ottermail-media-remove');
		if (url) {
			$img.attr('src', url);
			$preview.removeAttr('hidden');
			$remove.removeAttr('hidden');
		} else {
			$img.attr('src', '');
			$preview.attr('hidden', 'hidden');
			$remove.attr('hidden', 'hidden');
		}
	}

	$(document).on('click', ns + ' .ottermail-media-select', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}

		var $field = $(this).closest('.ottermail-media-field');

		if (mediaFrame) {
			mediaFrame.off('select');
		}

		mediaFrame = wp.media({
			title: ottermailAdmin.i18n.selectLogo,
			button: { text: ottermailAdmin.i18n.useLogo },
			library: { type: 'image' },
			multiple: false
		});

		mediaFrame.on('select', function () {
			var attachment = mediaFrame.state().get('selection').first().toJSON();
			var url = attachment.url || '';
			$field.find('.ottermail-media-url').val(url).trigger('change');
			setMediaPreview($field, url);
		});

		mediaFrame.open();
	});

	$(document).on('click', ns + ' .ottermail-media-remove', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.ottermail-media-field');
		$field.find('.ottermail-media-url').val('').trigger('change');
		setMediaPreview($field, '');
	});

	$(document).on('input change', ns + ' .ottermail-media-url', function () {
		var $field = $(this).closest('.ottermail-media-field');
		setMediaPreview($field, $.trim($(this).val()));
	});
})(jQuery);
