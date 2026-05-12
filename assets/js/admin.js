/**
 * Matrix BOGO – Admin JS
 * Handles the promotion builder, list table actions, analytics chart.
 */
/* global matrixBogoAdmin, Chart */
(function ($) {
	'use strict';

	// ── Promotions List – row actions ────────────────────────────────

	$(document).on('click', '.matrix-bogo-delete-rule', function () {
		if (!window.confirm(matrixBogoAdmin.i18n.confirmDelete)) return;
		const $btn = $(this);
		const id = $btn.data('id');
		const nonce = $btn.data('nonce');

		$.post(matrixBogoAdmin.ajaxUrl, {
			action: 'matrix_bogo_delete_rule',
			nonce,
			id,
		}).done(function (res) {
			if (res.success) {
				$btn.closest('tr').fadeOut(300, function () { $(this).remove(); });
			} else {
				alert(res.data.message);
			}
		});
	});

	$(document).on('click', '.matrix-bogo-toggle-rule', function () {
		const $btn = $(this);
		const id = $btn.data('id');
		const nonce = $btn.data('nonce');

		$.post(matrixBogoAdmin.ajaxUrl, {
			action: 'matrix_bogo_toggle_rule',
			nonce,
			id,
		}).done(function (res) {
			if (res.success) {
				location.reload();
			} else {
				alert(res.data.message);
			}
		});
	});

	$(document).on('click', '.matrix-bogo-clone-rule', function () {
		const $btn = $(this);
		const id = $btn.data('id');
		const nonce = $btn.data('nonce');

		$.post(matrixBogoAdmin.ajaxUrl, {
			action: 'matrix_bogo_clone_rule',
			nonce,
			id,
		}).done(function (res) {
			if (res.success) {
				location.reload();
			} else {
				alert(res.data.message);
			}
		});
	});

	// ── Export ───────────────────────────────────────────────────────

	$(document).on('click', '#matrix-bogo-export-btn', function () {
		const nonce = $(this).data('nonce');

		$.post(matrixBogoAdmin.ajaxUrl, {
			action: 'matrix_bogo_export_rules',
			nonce,
		}).done(function (res) {
			if (!res.success) { alert(res.data.message); return; }
			const blob = new Blob([JSON.stringify(res.data.data, null, 2)], { type: 'application/json' });
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = res.data.filename;
			a.click();
			URL.revokeObjectURL(url);
		});
	});

	// ── Import ───────────────────────────────────────────────────────

	$(document).on('click', '#matrix-bogo-import-btn', function () {
		const nonce = $(this).data('nonce');
		const input = document.createElement('input');
		input.type = 'file';
		input.accept = '.json';

		input.addEventListener('change', function () {
			const file = this.files[0];
			if (!file) return;
			const reader = new FileReader();
			reader.onload = function (e) {
				$.post(matrixBogoAdmin.ajaxUrl, {
					action: 'matrix_bogo_import_rules',
					nonce,
					data: e.target.result,
				}).done(function (res) {
					if (res.success) {
						alert(res.data.message);
						location.reload();
					} else {
						alert(res.data.message);
					}
				});
			};
			reader.readAsText(file);
		});

		input.click();
	});

	// ── Promotion Builder – save via AJAX ────────────────────────────

	$(document).on('submit', '#matrix-bogo-rule-form', function (e) {
		e.preventDefault();
		const $form = $(this);
		const $btn = $form.find('#matrix-bogo-save-btn');
		const $status = $form.find('#matrix-bogo-save-status');

		$btn.prop('disabled', true).text(matrixBogoAdmin.i18n.saving);

		const formData = $form.serializeArray();
		const payload = {};
		formData.forEach(function (item) {
			payload[item.name] = item.value;
		});

		// Gather JSON data from the condition/reward builders.
		payload.conditions = JSON.stringify(window.matrixBogoConditions || []);
		payload.rewards = JSON.stringify(window.matrixBogoRewards || []);
		payload.rule_data = JSON.stringify(window.matrixBogoRuleData || {});
		payload.action = 'matrix_bogo_save_rule';
		payload.nonce = matrixBogoAdmin.nonce;

		$.post(matrixBogoAdmin.ajaxUrl, payload).done(function (res) {
			if (res.success) {
				$status.text(matrixBogoAdmin.i18n.saved).removeClass('error').addClass('saved');
				if (!payload.id) {
					window.location.href = matrixBogoAdmin.listUrl + '&action=edit&id=' + res.data.id;
				}
			} else {
				$status.text(res.data.message).removeClass('saved').addClass('error');
			}
		}).always(function () {
			$btn.prop('disabled', false).text(matrixBogoAdmin.i18n.save);
		});
	});

	// ── License activation ───────────────────────────────────────────

	$(document).on('click', '#matrix-bogo-activate-license', function () {
		const nonce = $(this).data('nonce');
		const key = $('input[name="license_key"]').val();

		$.post(matrixBogoAdmin.ajaxUrl, {
			action: 'matrix_bogo_activate_license',
			nonce,
			license_key: key,
		}).done(function (res) {
			const $status = $('#matrix-bogo-license-status');
			if (res.success) {
				$status.text(matrixBogoAdmin.i18n.licenseActive).removeClass('invalid').addClass('valid');
			} else {
				$status.text(res.data.message).removeClass('valid').addClass('invalid');
			}
		});
	});

	// ── Promotion Builder – Condition & Reward UI ────────────────────

	const conditionTypes = {
		user_role:        { label: 'User Role',                   operators: ['in','not_in'],          valueType: 'text',      placeholder: 'e.g. customer,subscriber' },
		logged_in:        { label: 'Customer Logged In',          operators: ['is_true','is_false'],   valueType: 'none' },
		specific_user:    { label: 'Specific User ID',            operators: ['in','not_in'],          valueType: 'text',      placeholder: 'User IDs, comma-separated' },
		email:            { label: 'Customer Email',              operators: ['is','contains'],        valueType: 'text',      placeholder: 'e.g. @example.com' },
		first_order:      { label: 'First Order',                 operators: ['is_true','is_false'],   valueType: 'none' },
		repeat_customer:  { label: 'Repeat Customer',             operators: ['is_true','is_false'],   valueType: 'none' },
		cart_subtotal:    { label: 'Cart Subtotal',               operators: ['>=','<=','>','<','='],  valueType: 'number',    placeholder: '0.00' },
		cart_quantity:    { label: 'Cart Quantity',               operators: ['>=','<=','>','<','='],  valueType: 'number',    placeholder: '1' },
		cart_categories:  { label: 'Cart Contains Categories',    operators: ['in','not_in'],          valueType: 'category_select', placeholder: 'Search by category name or ID' },
		cart_products:    { label: 'Cart Contains Products',      operators: ['in','not_in'],          valueType: 'product_select',  placeholder: 'Search by name, SKU, or ID' },
		coupon_applied:   { label: 'Coupon Applied',              operators: ['is','is_not'],          valueType: 'text',      placeholder: 'Coupon code' },
		purchase_history: { label: 'Purchased Product',           operators: ['in','not_in'],          valueType: 'product_select',  placeholder: 'Search by name, SKU, or ID' },
		total_spent:      { label: 'Total Spent',                 operators: ['>=','<=','>','<'],      valueType: 'number',    placeholder: '0.00' },
		completed_orders: { label: 'Completed Orders',            operators: ['>=','<=','>','<'],      valueType: 'number',    placeholder: '1' },
		date_range:       { label: 'Date Range',                  operators: ['between'],              valueType: 'daterange' },
		day_of_week:      { label: 'Day of Week',                 operators: ['in'],                  valueType: 'days' },
		time_range:       { label: 'Time Range',                  operators: ['between'],              valueType: 'timerange' },
	};

	const rewardTypes = {
		free_product:     'Free Product',
		fixed_discount:   'Fixed Discount ($)',
		percent_discount: 'Percentage Discount (%)',
		cheapest_free:    'Cheapest Item Free',
	};

	const ruleDataFields = {
		buy_x_get_y:            [{ key: 'trigger_product_ids',  label: 'Trigger Products',   type: 'product_multi',  placeholder: 'Search by name, SKU, or ID' },
		                         { key: 'trigger_quantity',     label: 'Trigger Quantity',    type: 'number',         placeholder: '1' }],
		buy_x_get_x:            [{ key: 'trigger_product_ids',  label: 'Trigger Products',   type: 'product_multi',  placeholder: 'Search by name, SKU, or ID' },
		                         { key: 'trigger_quantity',     label: 'Trigger Quantity',    type: 'number',         placeholder: '1' }],
		spend_amount_get_gift:  [{ key: 'min_amount',           label: 'Minimum Spend',       type: 'number',         placeholder: '0.00' }],
		cart_quantity_get_gift: [{ key: 'min_quantity',         label: 'Minimum Cart Qty',    type: 'number',         placeholder: '1' }],
		category_get_gift:      [{ key: 'trigger_category_ids', label: 'Trigger Categories', type: 'category_multi', placeholder: 'Search by category name or ID' },
		                         { key: 'trigger_quantity',     label: 'Trigger Quantity',    type: 'number',         placeholder: '1' }],
	};

	function esc(str) {
		return $('<div>').text(str == null ? '' : String(str)).html();
	}

	// ── Select2 AJAX helpers ──────────────────────────────────────────

	function s2AjaxCfg(action) {
		return {
			url: matrixBogoAdmin.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			delay: 250,
			cache: true,
			data: function (params) {
				return { action: action, nonce: matrixBogoAdmin.nonce, q: params.term };
			},
			processResults: function (res) {
				return { results: res.success ? res.data.results : [] };
			},
		};
	}

	function prePopulateProducts($el, ids) {
		if (!ids) { return; }
		$.post(matrixBogoAdmin.ajaxUrl, { action: 'matrix_bogo_get_product_details', nonce: matrixBogoAdmin.nonce, ids: ids })
			.done(function (res) {
				if (!res.success) { return; }
				res.data.results.forEach(function (item) {
					if (!$el.find('option[value="' + item.id + '"]').length) {
						$el.append(new Option(item.text, item.id, true, true));
					}
				});
				$el.trigger('change.select2');
			});
	}

	function prePopulateCategories($el, ids) {
		if (!ids) { return; }
		$.post(matrixBogoAdmin.ajaxUrl, { action: 'matrix_bogo_get_category_details', nonce: matrixBogoAdmin.nonce, ids: ids })
			.done(function (res) {
				if (!res.success) { return; }
				res.data.results.forEach(function (item) {
					if (!$el.find('option[value="' + item.id + '"]').length) {
						$el.append(new Option(item.text, item.id, true, true));
					}
				});
				$el.trigger('change.select2');
			});
	}

	/**
	 * Initialise (or re-initialise) Select2 on all product/category selects
	 * within $ctx (a jQuery object). Already-initialised elements are skipped.
	 */
	function initSelect2($ctx) {
		if (typeof $.fn.select2 === 'undefined') { return; }
		$ctx = $ctx || $(document);

		// Multi-product search.
		$ctx.find('.matrix-bogo-product-select2').each(function () {
			const $el = $(this);
			if ($el.hasClass('select2-hidden-accessible')) { return; }
			$el.select2({ width: '100%', placeholder: 'Search by name, SKU, or ID…', allowClear: true, minimumInputLength: 3, ajax: s2AjaxCfg('matrix_bogo_search_products') });
			prePopulateProducts($el, String($el.data('saved-ids') || '').trim());
		});

		// Single-product search (reward product_id).
		$ctx.find('.matrix-bogo-product-select2-single').each(function () {
			const $el = $(this);
			if ($el.hasClass('select2-hidden-accessible')) { return; }
			$el.select2({ width: '100%', placeholder: 'Search by name, SKU, or ID…', allowClear: true, minimumInputLength: 3, ajax: s2AjaxCfg('matrix_bogo_search_products') });
			const savedId = String($el.data('saved-id') || '').trim();
			if (savedId) { prePopulateProducts($el, savedId); }
		});

		// Multi-category search.
		$ctx.find('.matrix-bogo-category-select2').each(function () {
			const $el = $(this);
			if ($el.hasClass('select2-hidden-accessible')) { return; }
			$el.select2({ width: '100%', placeholder: 'Search by category name or ID…', allowClear: true, minimumInputLength: 3, ajax: s2AjaxCfg('matrix_bogo_search_categories') });
			prePopulateCategories($el, String($el.data('saved-ids') || '').trim());
		});
	}

	// ── Rule Data Section ─────────────────────────────────────────────

	function renderRuleData(type) {
		const $container = $('#matrix-bogo-rule-data-container');
		if (!$container.length) { return; }
		const fields = ruleDataFields[type] || [];
		if (!fields.length) {
			$container.html('<p><em>No additional settings for this promotion type.</em></p>');
			return;
		}
		let html = '<table class="form-table">';
		fields.forEach(function (field) {
			const val = window.matrixBogoRuleData && window.matrixBogoRuleData[field.key] !== undefined
				? window.matrixBogoRuleData[field.key] : '';
			html += '<tr><th>' + esc(field.label) + '</th><td>';
			if (field.type === 'product_multi') {
				html += '<select multiple class="matrix-bogo-product-select2 matrix-bogo-rule-data-field"'
					+ ' data-key="' + esc(field.key) + '" data-saved-ids="' + esc(String(val)) + '" style="min-width:380px"></select>'
					+ '<p class="description">' + esc(field.placeholder) + '</p>';
			} else if (field.type === 'category_multi') {
				html += '<select multiple class="matrix-bogo-category-select2 matrix-bogo-rule-data-field"'
					+ ' data-key="' + esc(field.key) + '" data-saved-ids="' + esc(String(val)) + '" style="min-width:380px"></select>'
					+ '<p class="description">' + esc(field.placeholder) + '</p>';
			} else {
				html += '<input type="' + field.type + '" step="any" class="regular-text matrix-bogo-rule-data-field"'
					+ ' data-key="' + esc(field.key) + '" value="' + esc(val) + '"'
					+ (field.placeholder ? ' placeholder="' + esc(field.placeholder) + '"' : '')
					+ '>';
			}
			html += '</td></tr>';
		});
		html += '</table>';
		$container.html(html);
		initSelect2($container);
	}

	$(document).on('change', '#matrix-bogo-type', function () {
		window.matrixBogoRuleData = {};
		renderRuleData($(this).val());
	});

	$(document).on('input change', '.matrix-bogo-rule-data-field', function () {
		const v = $(this).val();
		window.matrixBogoRuleData[$(this).data('key')] = Array.isArray(v) ? v.join(',') : (v || '');
	});

	// ── Conditions Builder ─────────────────────────────────────────────

	function buildConditionValueHtml(meta, val, g, c) {
		const a = 'data-group="' + g + '" data-cond="' + c + '"';
		switch (meta.valueType) {
			case 'none':
				return '';
			case 'number':
				return '<input type="number" step="0.01" class="small-text mbcg-value" ' + a + ' value="' + esc(val) + '" placeholder="' + esc(meta.placeholder || '') + '">';
			case 'days': {
				const days = [['mon','Mon'],['tue','Tue'],['wed','Wed'],['thu','Thu'],['fri','Fri'],['sat','Sat'],['sun','Sun']];
				const sel  = String(val || '').split(',');
				let h = '<span class="mbcg-days">';
				days.forEach(function (d) {
					h += '<label style="margin-right:8px"><input type="checkbox" class="mbcg-day-check" data-group="' + g + '" data-cond="' + c + '" value="' + d[0] + '"'
					   + (sel.indexOf(d[0]) > -1 ? ' checked' : '') + '> ' + d[1] + '</label>';
				});
				return h + '</span>';
			}
			case 'daterange':
			case 'timerange': {
				const parts = String(val || '|').split('|');
				const itype = meta.valueType === 'timerange' ? 'time' : 'date';
				return '<input type="' + itype + '" class="mbcg-value" ' + a + ' data-part="from" value="' + esc(parts[0] || '') + '"> '
					+ '<span style="line-height:28px"> to </span>'
					+ '<input type="' + itype + '" class="mbcg-value-to" data-group="' + g + '" data-cond="' + c + '" data-part="to" value="' + esc(parts[1] || '') + '">';
			}
			case 'product_select': {
				const savedPIds = String(val || '').trim();
				return '<select multiple class="matrix-bogo-product-select2 mbcg-value-select" ' + a
					+ (savedPIds ? ' data-saved-ids="' + esc(savedPIds) + '"' : '')
					+ ' style="min-width:320px"></select>'
					+ '<p class="description" style="margin:2px 0 0">Search by name, SKU, or ID (type 3+ chars)</p>';
			}
			case 'category_select': {
				const savedCIds = String(val || '').trim();
				return '<select multiple class="matrix-bogo-category-select2 mbcg-value-select" ' + a
					+ (savedCIds ? ' data-saved-ids="' + esc(savedCIds) + '"' : '')
					+ ' style="min-width:320px"></select>'
					+ '<p class="description" style="margin:2px 0 0">Search by category name or ID (type 3+ chars)</p>';
			}
			default:
				return '<input type="text" class="regular-text mbcg-value" ' + a + ' value="' + esc(val) + '" placeholder="' + esc(meta.placeholder || '') + '">';
		}
	}

	function buildConditionRowHtml(g, c, cond) {
		const type    = cond.type     || 'cart_subtotal';
		const meta    = conditionTypes[type] || conditionTypes.cart_subtotal;
		const operator= cond.operator || meta.operators[0];
		const val     = cond.value    || '';

		let typeOpts = '';
		Object.keys(conditionTypes).forEach(function (k) {
			typeOpts += '<option value="' + k + '"' + (k === type ? ' selected' : '') + '>' + conditionTypes[k].label + '</option>';
		});
		let opOpts = '';
		meta.operators.forEach(function (op) {
			opOpts += '<option value="' + op + '"' + (op === operator ? ' selected' : '') + '>' + op + '</option>';
		});

		return '<div class="mbcg-condition-row" data-group="' + g + '" data-cond="' + c + '">'
			+ '<select class="mbcg-type" data-group="' + g + '" data-cond="' + c + '">' + typeOpts + '</select>'
			+ '<select class="mbcg-operator" data-group="' + g + '" data-cond="' + c + '">' + opOpts + '</select>'
			+ '<span class="mbcg-value-wrap">' + buildConditionValueHtml(meta, val, g, c) + '</span>'
			+ '<button type="button" class="button-link mbcg-remove-cond" style="color:#b32d2e;margin-left:8px" data-group="' + g + '" data-cond="' + c + '">&#10005;</button>'
			+ '</div>';
	}

	function buildConditionGroupHtml(g, group) {
		let h = '<div class="mbcg-group" data-group="' + g + '" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px">'
			+ '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">'
			+ '<strong>Group ' + (g + 1) + '</strong>'
			+ '<button type="button" class="button-link mbcg-remove-group" style="color:#b32d2e" data-group="' + g + '">&#10005; Remove Group</button>'
			+ '</div>';
		group.forEach(function (cond, c) { h += buildConditionRowHtml(g, c, cond); });
		h += '<button type="button" class="button button-small mbcg-add-cond" style="margin-top:8px" data-group="' + g + '">+ Add Condition</button>'
			+ '</div>';
		if (g < window.matrixBogoConditions.length - 1) {
			h += '<div style="text-align:center;margin:8px 0;color:#888;font-weight:600">— OR —</div>';
		}
		return h;
	}

	function reRenderConditions() {
		let h = '';
		window.matrixBogoConditions.forEach(function (group, g) { h += buildConditionGroupHtml(g, group); });
		const $cont = $('#matrix-bogo-conditions-container');
		$cont.html(h);
		initSelect2($cont);
	}

	$(document).on('click', '#matrix-bogo-add-condition-group', function () {
		window.matrixBogoConditions.push([{ type: 'cart_subtotal', operator: '>=', value: '' }]);
		reRenderConditions();
	});
	$(document).on('click', '.mbcg-remove-group', function () {
		window.matrixBogoConditions.splice(parseInt($(this).data('group'), 10), 1);
		reRenderConditions();
	});
	$(document).on('click', '.mbcg-add-cond', function () {
		const g = parseInt($(this).data('group'), 10);
		window.matrixBogoConditions[g].push({ type: 'cart_subtotal', operator: '>=', value: '' });
		reRenderConditions();
	});
	$(document).on('click', '.mbcg-remove-cond', function () {
		const g = parseInt($(this).data('group'), 10);
		const c = parseInt($(this).data('cond'), 10);
		window.matrixBogoConditions[g].splice(c, 1);
		if (!window.matrixBogoConditions[g].length) { window.matrixBogoConditions.splice(g, 1); }
		reRenderConditions();
	});
	$(document).on('change', '.mbcg-type', function () {
		const g = parseInt($(this).data('group'), 10);
		const c = parseInt($(this).data('cond'), 10);
		const newType = $(this).val();
		window.matrixBogoConditions[g][c] = { type: newType, operator: conditionTypes[newType].operators[0], value: '' };
		reRenderConditions();
	});
	$(document).on('change', '.mbcg-operator', function () {
		const g = parseInt($(this).data('group'), 10);
		const c = parseInt($(this).data('cond'), 10);
		window.matrixBogoConditions[g][c].operator = $(this).val();
	});
	$(document).on('input change', '.mbcg-value', function () {
		const g    = parseInt($(this).data('group'), 10);
		const c    = parseInt($(this).data('cond'), 10);
		const part = $(this).data('part');
		if (part === 'from') {
			const parts = String(window.matrixBogoConditions[g][c].value || '|').split('|');
			window.matrixBogoConditions[g][c].value = $(this).val() + '|' + (parts[1] || '');
		} else {
			window.matrixBogoConditions[g][c].value = $(this).val();
		}
	});
	$(document).on('change', '.mbcg-value-to', function () {
		const g     = parseInt($(this).data('group'), 10);
		const c     = parseInt($(this).data('cond'), 10);
		const parts = String(window.matrixBogoConditions[g][c].value || '|').split('|');
		window.matrixBogoConditions[g][c].value = (parts[0] || '') + '|' + $(this).val();
	});
	$(document).on('change', '.mbcg-day-check', function () {
		const g = parseInt($(this).data('group'), 10);
		const c = parseInt($(this).data('cond'), 10);
		const checked = [];
		$('.mbcg-condition-row[data-group="' + g + '"][data-cond="' + c + '"] .mbcg-day-check:checked').each(function () {
			checked.push($(this).val());
		});
		window.matrixBogoConditions[g][c].value = checked.join(',');
	});

	// Select2 product/category value change for conditions.
	$(document).on('change', '.mbcg-value-select', function () {
		const g = parseInt($(this).data('group'), 10);
		const c = parseInt($(this).data('cond'), 10);
		const v = $(this).val();
		window.matrixBogoConditions[g][c].value = Array.isArray(v) ? v.join(',') : (v || '');
	});

	// ── Rewards Builder ───────────────────────────────────────────────

	function buildRewardFieldsHtml(i, type, reward) {
		const a = 'data-reward="' + i + '"';
		let h = '<table class="form-table" style="margin:0"><tbody>';
		if (type === 'free_product') {
			h += '<tr><th style="width:180px">Product</th><td>'
				+ '<select class="matrix-bogo-product-select2-single mbr-field" ' + a + ' data-field="product_id"'
				+ ' data-saved-id="' + esc(reward.product_id || '') + '" style="min-width:320px"></select>'
				+ '</td></tr>';
			h += '<tr><th>Variation ID</th><td><input type="number" min="0" class="small-text mbr-field" ' + a + ' data-field="variation_id" value="' + esc(reward.variation_id || '') + '" placeholder="0 = none"></td></tr>';
			h += '<tr><th>Quantity</th><td><input type="number" min="1" class="small-text mbr-field" ' + a + ' data-field="quantity" value="' + esc(reward.quantity || 1) + '"></td></tr>';
			h += '<tr><th>Max per Order</th><td><input type="number" min="0" class="small-text mbr-field" ' + a + ' data-field="max_per_order" value="' + esc(reward.max_per_order || 0) + '"><p class="description">0 = unlimited</p></td></tr>';
			h += '<tr><th>Customer Choice</th><td><label><input type="checkbox" class="mbr-field mbr-checkbox" ' + a + ' data-field="customer_choice" value="1"' + (reward.customer_choice ? ' checked' : '') + '> Let customer pick from a pool</label></td></tr>';
			h += '<tr class="mbr-choice-pool-row"' + (reward.customer_choice ? '' : ' style="display:none"') + '>'
				+ '<th>Choice Pool</th><td>'
				+ '<select multiple class="matrix-bogo-product-select2 mbr-field" ' + a + ' data-field="choice_pool"'
				+ ' data-saved-ids="' + esc(reward.choice_pool || '') + '" style="min-width:320px"></select>'
				+ '<p class="description">Products customer can choose from</p>'
				+ '</td></tr>';
		} else if (type === 'fixed_discount') {
			h += '<tr><th>Discount Amount ($)</th><td><input type="number" step="0.01" min="0" class="small-text mbr-field" ' + a + ' data-field="discount_value" value="' + esc(reward.discount_value || '') + '" placeholder="0.00"></td></tr>';
			h += '<tr><th>Max per Order</th><td><input type="number" min="0" class="small-text mbr-field" ' + a + ' data-field="max_per_order" value="' + esc(reward.max_per_order || 0) + '"><p class="description">0 = unlimited</p></td></tr>';
		} else if (type === 'percent_discount') {
			h += '<tr><th>Discount (%)</th><td><input type="number" step="0.01" min="0" max="100" class="small-text mbr-field" ' + a + ' data-field="discount_value" value="' + esc(reward.discount_value || '') + '" placeholder="0.00"></td></tr>';
			h += '<tr><th>Max per Order</th><td><input type="number" min="0" class="small-text mbr-field" ' + a + ' data-field="max_per_order" value="' + esc(reward.max_per_order || 0) + '"><p class="description">0 = unlimited</p></td></tr>';
		} else if (type === 'cheapest_free') {
			h += '<tr><th>Items Free</th><td><input type="number" min="1" class="small-text mbr-field" ' + a + ' data-field="quantity" value="' + esc(reward.quantity || 1) + '"></td></tr>';
		}
		return h + '</tbody></table>';
	}

	function buildRewardRowHtml(i, reward) {
		const type = reward.reward_type || 'free_product';
		let typeOpts = '';
		Object.keys(rewardTypes).forEach(function (k) {
			typeOpts += '<option value="' + k + '"' + (k === type ? ' selected' : '') + '>' + rewardTypes[k] + '</option>';
		});
		return '<div class="mbr-reward-row" data-reward="' + i + '" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px">'
			+ '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">'
			+ '<strong>Reward ' + (i + 1) + '</strong>'
			+ ' <select class="mbr-type" data-reward="' + i + '" style="margin:0 12px">' + typeOpts + '</select>'
			+ '<button type="button" class="button-link mbr-remove" style="color:#b32d2e" data-reward="' + i + '">&#10005; Remove</button>'
			+ '</div>'
			+ '<div class="mbr-fields">' + buildRewardFieldsHtml(i, type, reward) + '</div>'
			+ '</div>';
	}

	function reRenderRewards() {
		let h = '';
		window.matrixBogoRewards.forEach(function (reward, i) { h += buildRewardRowHtml(i, reward); });
		const $cont = $('#matrix-bogo-rewards-container');
		$cont.html(h);
		initSelect2($cont);
	}

	$(document).on('click', '#matrix-bogo-add-reward', function () {
		window.matrixBogoRewards.push({ reward_type: 'free_product', product_id: '', variation_id: '', quantity: 1, discount_value: 0, max_per_order: 0, customer_choice: 0, choice_pool: '' });
		reRenderRewards();
	});
	$(document).on('click', '.mbr-remove', function () {
		window.matrixBogoRewards.splice(parseInt($(this).data('reward'), 10), 1);
		reRenderRewards();
	});
	$(document).on('change', '.mbr-type', function () {
		const i = parseInt($(this).data('reward'), 10);
		window.matrixBogoRewards[i].reward_type = $(this).val();
		reRenderRewards();
	});
	$(document).on('input change', '.mbr-field:not(.mbr-checkbox)', function () {
		const i = parseInt($(this).data('reward'), 10);
		const v = $(this).val();
		window.matrixBogoRewards[i][$(this).data('field')] = Array.isArray(v) ? v.join(',') : (v || '');
	});
	$(document).on('change', '.mbr-checkbox', function () {
		const i = parseInt($(this).data('reward'), 10);
		window.matrixBogoRewards[i][$(this).data('field')] = $(this).is(':checked') ? 1 : 0;
		reRenderRewards();
	});

	// ── Builder initialisation ────────────────────────────────────────

	function initBuilder() {
		const $cond = $('#matrix-bogo-conditions-container');
		if (!$cond.length) { return; } // Not on the builder page.

		// matrixBogoBuilderData is injected as an inline <script> by PromotionBuilder.php
		const data = window.matrixBogoBuilderData || {};

		window.matrixBogoConditions = Array.isArray(data.conditions) ? data.conditions : [];
		window.matrixBogoRewards    = Array.isArray(data.rewards)    ? data.rewards    : [];
		window.matrixBogoRuleData   = (data.ruleData && typeof data.ruleData === 'object' && !Array.isArray(data.ruleData))
			? data.ruleData : {};

		// PHP already rendered #matrix-bogo-rule-data-container — do NOT overwrite it here.
		// Only render conditions and rewards (these start empty in HTML).
		reRenderConditions();
		reRenderRewards();
		// Initialise any Select2 widgets that PHP pre-rendered in the rule data section.
		initSelect2($('#matrix-bogo-rule-data-container'));
	}

	$(function () { initBuilder(); });

	// ── Analytics Chart ──────────────────────────────────────────────

	$(function () {
		const $canvas = $('#matrix-bogo-daily-chart');
		if (!$canvas.length || typeof Chart === 'undefined') { return; }
		const daily = JSON.parse($canvas.attr('data-daily') || '[]');
		const labels = daily.map(function (row) { return row.date; });
		const values = daily.map(function (row) { return row.redemptions; });

		new Chart($canvas[0], {
			type: 'line',
			data: {
				labels,
				datasets: [{
					label: matrixBogoAdmin.i18n.redemptions,
					data: values,
					borderColor: '#5b47e0',
					backgroundColor: 'rgba(91,71,224,.08)',
					fill: true,
					tension: 0.4,
					pointBackgroundColor: '#5b47e0',
					pointRadius: 4,
				}],
			},
			options: {
				responsive: true,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});
	});

})(jQuery);
