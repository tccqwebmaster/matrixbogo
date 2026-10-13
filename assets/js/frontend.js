/**
 * Matrix BOGO – Frontend JS
 * Handles gift selection on cart and promotion notices.
 */
/* global matrixBogoFrontend */
(function ($) {
	'use strict';

	// ── Add selected gift to cart ────────────────────────────────────

	$(document).on('click', '.matrix-bogo-add-gift-btn', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const ruleId = $btn.data('rule-id');
		const nonce = $btn.data('nonce');

		const $group = $btn.closest('.matrix-bogo-gift-group');
		const $checked = $group.find('input[type="radio"]:checked');

		if (!$checked.length) {
			alert(matrixBogoFrontend.i18n.selectGift);
			return;
		}

		const productId = $checked.val();
		const quantity = $checked.data('quantity') || 1;

		$btn.prop('disabled', true).text(matrixBogoFrontend.i18n.adding);

		$.post(matrixBogoFrontend.ajaxUrl, {
			action: 'matrix_bogo_apply_gift',
			nonce,
			rule_id: ruleId,
			product_id: productId,
			quantity,
		}).done(function (res) {
			if (res.success) {
				// Refresh the cart fragments.
				$(document.body).trigger('wc_fragment_refresh');
				$btn.text(matrixBogoFrontend.i18n.added).css('color', 'green');
			} else {
				alert(res.data.message || matrixBogoFrontend.i18n.error);
				$btn.prop('disabled', false).text(matrixBogoFrontend.i18n.addGift);
			}
		}).fail(function () {
			alert(matrixBogoFrontend.i18n.error);
			$btn.prop('disabled', false).text(matrixBogoFrontend.i18n.addGift);
		});
	});

	// ── Visual: highlight selected gift card ─────────────────────────

	$(document).on('change', '.matrix-bogo-gift-choice', function () {
		const $group = $(this).closest('.matrix-bogo-gift-products');
		$group.find('label').css({ borderColor: '', background: '' });
		$(this).closest('label').css({ borderColor: '#2271b1', background: '#eef5fb' });
	});

	// ── Option-level promotions (variable products) ──────────────────
	// The variation JSON carries matrix_bogo_promos (eligible promotion
	// labels) only on products with option rules. Show them for an eligible
	// option, hide otherwise. Other code can listen to the
	// 'matrix_bogo_option_promos' event (promos array, empty = not eligible).

	function setOptionPromos($form, promos) {
		const $box = $form.find('.matrix-bogo-variation-promos');
		const list = Array.isArray(promos) ? promos : [];
		$box.empty();
		list.forEach(function (p) {
			$('<div class="matrix-bogo-product-promo-badge"><span class="matrix-bogo-promo-icon">🎁</span><span class="matrix-bogo-promo-name"></span></div>')
				.find('.matrix-bogo-promo-name').text(p.label).end()
				.appendTo($box);
		});
		$box.prop('hidden', !list.length);
		$form.toggleClass('matrix-bogo-option-eligible', list.length > 0);
		$form.trigger('matrix_bogo_option_promos', [list]);
	}

	$(document).on('found_variation', '.variations_form', function (e, variation) {
		if (variation && 'matrix_bogo_promos' in variation) {
			setOptionPromos($(this), variation.matrix_bogo_promos);
		}
	});

	$(document).on('reset_data hide_variation', '.variations_form', function () {
		setOptionPromos($(this), []);
	});

})(jQuery);
