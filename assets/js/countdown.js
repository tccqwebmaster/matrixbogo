/**
 * Matrix BOGO – Countdown Timer & Progress Bar JS
 * Drives all live countdown clocks and AJAX progress bar refreshes.
 */
/* global matrixBogoFrontend */
(function ($) {
	'use strict';

	// ── Utility ────────────────────────────────────────────────────

	function pad(n) {
		return String(n).padStart(2, '0');
	}

	function getRemaining(endIso) {
		const diff = Math.max(0, new Date(endIso).getTime() - Date.now());
		return {
			total:   diff,
			hours:   Math.floor(diff / 3600000),
			minutes: Math.floor((diff % 3600000) / 60000),
			seconds: Math.floor((diff % 60000) / 1000),
		};
	}

	// ── Full countdown banner (cart / checkout) ──────────────────

	function initBanners() {
		$('.mb-countdown-banner').each(function () {
			const $banner = $(this);
			const endIso  = $banner.data('end');
			if (!endIso) return;

			let prevVals = {};

			function tick() {
				const r = getRemaining(endIso);

				// Expired — remove the banner gracefully.
				if (r.total <= 0) {
					$banner.fadeOut(400, function () { $(this).remove(); });
					return;
				}

				// Urgency class escalation.
				$banner.removeClass('mb-countdown--warn mb-countdown--urgent');
				if (r.total < 3600000)  $banner.addClass('mb-countdown--warn');
				if (r.total < 600000)   $banner.addClass('mb-countdown--urgent');

				const vals = { hours: pad(r.hours), minutes: pad(r.minutes), seconds: pad(r.seconds) };

				$banner.find('[data-unit]').each(function () {
					const unit = $(this).data('unit');
					const $el  = $(this);
					if (vals[unit] !== prevVals[unit]) {
						$el.addClass('mb-clock-flip');
						setTimeout(() => $el.removeClass('mb-clock-flip'), 300);
						$el.text(vals[unit]);
					}
				});

				prevVals = vals;
				setTimeout(tick, 1000);
			}

			tick();
		});
	}

	// ── Compact inline countdown (progress bar / product page) ───

	function initInline() {
		$('.mb-inline-countdown, .mb-product-timer, .mb-shop-ticker').each(function () {
			const $wrap  = $(this);
			const endIso = $wrap.data('end');
			if (!endIso) return;

			const $val = $wrap.find('.mb-product-timer-value, .mb-shop-ticker-time, .mb-inline-countdown');

			function tick() {
				const r = getRemaining(endIso);
				if (r.total <= 0) { $wrap.fadeOut(400); return; }

				let str;
				if (r.hours > 0) {
					str = pad(r.hours) + 'h ' + pad(r.minutes) + 'm ' + pad(r.seconds) + 's';
				} else {
					str = pad(r.minutes) + 'm ' + pad(r.seconds) + 's';
				}

				// Urgency color
				$wrap.removeClass('mb-timer--warn mb-timer--urgent');
				if (r.total < 3600000) $wrap.addClass('mb-timer--warn');
				if (r.total < 300000)  $wrap.addClass('mb-timer--urgent');

				if ($wrap.hasClass('mb-inline-countdown')) {
					$wrap.text(str);
				} else {
					$val.text(str);
				}

				setTimeout(tick, 1000);
			}
			tick();
		});
	}

	// ── Progress bar AJAX refresh ────────────────────────────────

	let refreshPending = false;

	function refreshProgress() {
		if (refreshPending || !$('#mb-progress-region').length) return;
		refreshPending = true;

		$.post(matrixBogoFrontend.ajaxUrl, {
			action: 'matrix_bogo_get_progress',
			nonce:  matrixBogoFrontend.nonce,
		}).done(function (res) {
			if (res.success && res.data.html) {
				const $new = $(res.data.html);
				$('#mb-progress-region').replaceWith($new);
				// Re-init inline timers inside the refreshed HTML.
				initInline();
				// Animate bars in.
				$new.find('.mb-progress-fill').each(function () {
					const target = $(this).css('width');
					$(this).css('width', 0).animate({ width: target }, 700);
				});
			}
		}).always(function () {
			refreshPending = false;
		});
	}

	// Re-run after WooCommerce cart fragment updates.
	$(document.body).on('wc_fragments_refreshed wc_fragments_loaded added_to_cart removed_from_cart', function () {
		setTimeout(refreshProgress, 300);
	});

	// ── Init ─────────────────────────────────────────────────────

	$(function () {
		initBanners();
		initInline();

		// Animate progress bars on load.
		$('.mb-progress-fill').each(function () {
			const target = $(this).css('width');
			$(this).css('width', 0).animate({ width: target }, { duration: 900, easing: 'swing' });
		});
	});

})(jQuery);
