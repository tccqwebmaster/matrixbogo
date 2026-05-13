/**
 * Matrix BOGO – Gift Popup JS
 * Manages the gift-selector modal on cart/checkout pages.
 */
/* global matrixBogoFrontend */
(function ($) {
	'use strict';

	const $popup = $('#matrix-bogo-gift-popup');
	const $overlay = $popup.find('.matrix-bogo-popup-overlay');
	const $products = $popup.find('#matrix-bogo-popup-products');
	const $addBtn = $popup.find('#matrix-bogo-popup-add-btn');

	let currentRuleId = null;
	let currentNonce = null;

	// ── Open popup if choice gifts exist ────────────────────────────

	function maybeOpenPopup() {
		if (!$popup.length) return;
		if (!matrixBogoFrontend.hasChoiceGifts) return;

		// Avoid re-showing if already dismissed this session.
		if (sessionStorage.getItem('matrix_bogo_popup_dismissed')) return;

		openPopup(matrixBogoFrontend.choiceRuleId, matrixBogoFrontend.nonce);
	}

	function openPopup(ruleId, nonce) {
		currentRuleId = ruleId;
		currentNonce = nonce;
		loadGiftProducts(ruleId, nonce);
		$popup.fadeIn(200).attr('aria-hidden', 'false');
		$('body').addClass('matrix-bogo-popup-open');
	}

	function closePopup() {
		$popup.fadeOut(200).attr('aria-hidden', 'true');
		$('body').removeClass('matrix-bogo-popup-open');
		sessionStorage.setItem('matrix_bogo_popup_dismissed', '1');
	}

	// ── Load gift product options ────────────────────────────────────

	function loadGiftProducts(ruleId, nonce) {
		$products.html('<div class="matrix-bogo-popup-loading"><span class="spinner is-active"></span></div>');

		$.post(matrixBogoFrontend.ajaxUrl, {
			action: 'matrix_bogo_search_gifts',
			nonce,
			rule_id: ruleId,
		}).done(function (res) {
			if (!res.success || !res.data.products.length) {
				$products.html('<p>' + matrixBogoFrontend.i18n.noGifts + '</p>');
				return;
			}
			renderProducts(res.data.products);
		}).fail(function () {
			$products.html('<p>' + matrixBogoFrontend.i18n.error + '</p>');
		});
	}

	function renderProducts(products) {
		let html = '<div class="matrix-bogo-gift-products">';
		products.forEach(function (p) {
			html += `
				<div class="matrix-bogo-gift-item">
					<label>
						<input type="radio" name="matrix_bogo_popup_choice"
						       value="${p.id}" data-quantity="1">
						<img src="${p.thumbnail}" alt="${p.name}" width="80">
						<span class="matrix-bogo-gift-name">${p.name}</span>
						<span class="matrix-bogo-gift-free-label">${matrixBogoFrontend.i18n.free}</span>
					</label>
				</div>`;
		});
		html += '</div>';
		$products.html(html);
	}

	// ── Add selected gift ────────────────────────────────────────────

	$addBtn.on('click', function () {
		const $checked = $products.find('input[type="radio"]:checked');
		if (!$checked.length) {
			alert(matrixBogoFrontend.i18n.selectGift);
			return;
		}

		const productId = $checked.val();
		const quantity = $checked.data('quantity') || 1;

		$addBtn.prop('disabled', true);

		$.post(matrixBogoFrontend.ajaxUrl, {
			action: 'matrix_bogo_apply_gift',
			nonce: currentNonce,
			rule_id: currentRuleId,
			product_id: productId,
			quantity,
		}).done(function (res) {
			if (res.success) {
				$(document.body).trigger('wc_fragment_refresh');
				closePopup();
			} else {
				alert(res.data.message || matrixBogoFrontend.i18n.error);
				$addBtn.prop('disabled', false);
			}
		});
	});

	// ── Enable Add button when a product is selected ─────────────────

	$(document).on('change', '#matrix-bogo-popup-products input[type="radio"]', function () {
		$addBtn.prop('disabled', false);
	});

	// ── Close handlers ────────────────────────────────────────────── */

	$popup.find('.matrix-bogo-popup-close, .matrix-bogo-popup-skip').on('click', closePopup);
	$overlay.on('click', closePopup);

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') closePopup();
	});

	// ── Init ─────────────────────────────────────────────────────────

	$(function () {
		// Small delay so cart fragments have loaded.
		setTimeout(maybeOpenPopup, 800);
	});

})(jQuery);
