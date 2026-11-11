/*
 * DAILY — CRM de Clientes Retail (views/customers.blade.php, views/customer_profile.blade.php + services/CustomerCrmService.php).
 * Controla navegación de 7 módulos, vista rápida 360°, altas/edición con duplicados, casos y aprobación financiera separada,
 * campañas, automatizaciones, parámetros del programa y gráficos con tooltips de alto contraste.
 */
(function ($) {
	'use strict';
	var bootEl = document.getElementById('crm-boot');
	if (!bootEl) return;
	var B = JSON.parse(bootEl.textContent);
	if (window.lucide) lucide.createIcons();

	var SYM = (B.regional && B.regional.currency_symbol) || '$';
	function api(url, body) { return new Promise(function (ok, ko) { Grocy.Api.Post(url, body || {}, ok, ko); }); }
	function apiGet(url) { return new Promise(function (ok, ko) { Grocy.Api.Get(url, ok, ko); }); }
	function err(x) { return x && x.responseText ? DairyUI.errMsg(x) : (x && x.message) || 'Error'; }
	function esc(s) { return DairyUI.esc(s); }
	function money(v) { return SYM + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function formData($f) {
		var d = {};
		$f.serializeArray().forEach(function (p) { d[p.name] = p.value; });
		$f.find('input[type=checkbox]').each(function () { d[this.name] = this.checked ? 1 : 0; });
		return d;
	}
	function busy($btn, on) { $btn.prop('disabled', on).toggleClass('is-busy', on); }
	function reload(msg) { if (msg) toastr.success(msg); setTimeout(function () { location.reload(); }, 350); }

	/* ------------------------------------------------------------ navegación entre las 7 pestañas */
	var evoChart = null;
	function switchTab(tabKey) {
		if (!tabKey || !$('#panel-' + tabKey).length) return;
		$('.crm-tab-pill').removeClass('active');
		$('.crm-tab-pill[data-crm-tab="' + tabKey + '"]').addClass('active');
		$('.crm-tab-panel').removeClass('active');
		$('#panel-' + tabKey).addClass('active');
		$('#crm-filter-tab').val(tabKey);
		if (window.history && window.history.replaceState) {
			var u = new URL(window.location.href);
			u.searchParams.set('tab', tabKey);
			window.history.replaceState({}, '', u.toString());
		}
		if (tabKey === 'overview' && evoChart) {
			setTimeout(function () { evoChart.resize(); }, 40);
		}
	}
	$(document).on('click', '.crm-tab-pill[data-crm-tab], [data-jump-tab]', function (e) {
		var t = $(this).data('crm-tab') || $(this).data('jump-tab');
		if (!t || !$('#panel-' + t).length) return;
		e.preventDefault();
		switchTab(t);
	});

	/* ------------------------------------------------------------ vista rápida 360° en Base de Clientes */
	var SEG_LABELS = { champions: 'Campeones', loyal: 'Leales', potential: 'Potenciales', attention: 'Necesitan atención', at_risk: 'Valiosos alejándose', hibernating: 'Hibernando', none: 'Sin compras' };
	var SEG_TONES = { champions: 'good', loyal: 'good', potential: 'info', attention: 'warn', at_risk: 'warn', hibernating: 'bad', none: 'muted' };
	var LIFE_LABELS = { new: 'Nuevo', recurrent: 'Recurrente', occasional: 'Ocasional', at_risk: 'En riesgo', inactive: 'Inactivo', prospect: 'Prospecto' };

	$(document).on('click', '[data-select-customer]', function (e) {
		if ($(e.target).closest('a,button,input').length) return;
		var $row = $(this), cid = parseInt($row.data('select-customer'), 10);
		if (!cid) return;
		$('#crm-directory-table tr.crm-row-selectable').removeClass('is-selected');
		$row.addClass('is-selected');
		apiGet('customers/' + cid + (B.branchId ? '?branch_id=' + B.branchId : '')).then(function (p) {
			var c = p.customer || {}, loy = p.loyalty || {};
			$('#crm-360-name').text(c.name || '');
			$('#crm-360-segment')
				.attr('class', 'crm-pill crm-' + (SEG_TONES[c.segment] || 'muted'))
				.text(SEG_LABELS[c.segment] || c.segment || '');
			var pts = loy.enabled ? (Number(loy.balance || 0).toLocaleString() + ' pts') : (Number(c.points_balance || 0).toLocaleString() + ' pts');
			var dlHtml = ''
				+ '<dt>' + esc((B.regional && B.regional.tax_id_label) || 'Documento') + '</dt><dd>' + esc(c.tax_id || '—') + '</dd>'
				+ '<dt>Teléfono</dt><dd>' + esc(c.phone || '—') + '</dd>'
				+ '<dt>Correo</dt><dd>' + esc(c.email || '—') + '</dd>'
				+ '<dt>Ciclo / Nivel</dt><dd>' + esc(LIFE_LABELS[c.lifecycle] || c.lifecycle || '') + ' · ' + esc(c.loyalty_tier || 'Bronze') + ' (' + esc(pts) + ')</dd>'
				+ '<dt>Compras netas</dt><dd>' + intval(c.tx) + ' tickets · ' + money(c.revenue) + '</dd>'
				+ '<dt>Frecuencia</dt><dd>' + (c.frequency_days !== null && c.frequency_days !== undefined ? ('cada ' + c.frequency_days + ' días') : 'Compra única / sin compras') + '</dd>'
				+ '<dt>CLV anual est.</dt><dd><strong>' + money(c.annual_value) + '</strong></dd>';
			$('#crm-360-dl').html(dlHtml);
			$('#crm-360-actions').html(
				'<a href="' + U('/customers/' + cid) + '" class="ded-select-btn crm-btn-primary">Abrir ficha 360°</a>' +
				'<a href="' + U('/pos?customer_id=' + cid) + '" class="ded-select-btn">Venta en POS</a>'
			);
		}, function (x) { toastr.error(err(x)); });
	});
	function intval(v) { return parseInt(v, 10) || 0; }

	/* ------------------------------------------------------------ alta / edición de cliente */
	var $cf = $('#crm-customer-form'), editId = null, confirmDup = false;
	$('#crm-new-customer').on('show.bs.modal', function (e) {
		if (e.relatedTarget) {
			editId = null; $cf[0].reset();
			$('#crm-new-customer-title').text('Nuevo cliente');
			$cf.find('.crm-new-only').show();
			$cf.find('[name=tax_id],[name=phone],[name=email],[name=address],[name=birth_date]').prop('disabled', false);
		}
		confirmDup = false;
		$cf.find('.crm-dup-warning').prop('hidden', true).empty();
	});
	$('#crm-edit').on('click', function () {
		var c = B.customer; editId = c.id;
		$cf[0].reset();
		Object.keys(c).forEach(function (k) { $cf.find('[name=' + k + ']').val(c[k] === null ? '' : c[k]); });
		$cf.find('[name=tax_id],[name=phone],[name=email],[name=address],[name=birth_date]').prop('disabled', !B.canPii);
		$cf.find('.crm-new-only').hide();
		$('#crm-new-customer-title').text('Editar ficha');
		$('#crm-new-customer').modal('show');
	});
	$cf.on('change input', 'input,select', function () { confirmDup = false; });
	$cf.on('submit', function (e) {
		e.preventDefault();
		var d = formData($cf), $btn = $cf.find('[type=submit]');
		if (confirmDup) d.confirm_duplicate = 1;
		busy($btn, true);
		api(editId ? 'customers/' + editId : 'customers', d).then(function (r) {
			busy($btn, false);
			if (r.duplicate_warning) {
				confirmDup = true;
				$cf.find('.crm-dup-warning').prop('hidden', false).html('<strong>' + esc(r.message) + '</strong><ul>' + r.duplicates.map(function (m) {
					return '<li><a href="' + U('/customers/' + m.id) + '" target="_blank" rel="noopener">#' + m.id + ' ' + esc(m.name) + '</a> · coincide ' + esc(m.reasons.join(', ')) + '</li>';
				}).join('') + '</ul>Si es otra persona, pulse «Guardar cliente» de nuevo para confirmarlo.');
				return;
			}
			if (editId) reload('Ficha actualizada');
			else { toastr.success('Cliente registrado'); location.href = U('/customers/' + r.id); }
		}, function (x) { busy($btn, false); toastr.error(err(x)); });
	});

	$('#crm-toggle-active').on('click', function () {
		var active = parseInt($(this).data('active'), 10) === 1;
		api('customers/' + B.customerId + '/active', { active: active ? 0 : 1 }).then(function () { reload(active ? 'Ficha desactivada' : 'Ficha reactivada'); }, function (x) { toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ casos y solicitudes (+ aprobación crédito separada) */
	function toggleReqExtras() {
		var cat = $('#crm-req-category').val();
		$('.crm-req-extra').prop('hidden', true);
		if (cat === 'credit') $('.crm-req-credit').prop('hidden', false);
		if (cat === 'delivery') $('.crm-req-delivery').prop('hidden', false);
	}
	$('#crm-req-category').on('change', toggleReqExtras);
	$('#crm-new-request').on('show.bs.modal', toggleReqExtras);

	$('#crm-request-form').on('submit', function (e) {
		e.preventDefault();
		var $f = $(this), $btn = $f.find('[type=submit]');
		busy($btn, true);
		api('customers/requests', formData($f)).then(function (r) { reload('Caso ' + r.code + ' registrado'); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});
	$(document).on('click', '[data-request]', function () {
		var r = $(this).data('request'), $f = $('#crm-manage-form');
		$f[0].reset();
		$f.find('[name=id]').val(r.id);
		$f.find('[name=status]').val(r.status);
		$f.find('[name=assigned_user_id]').val(r.assigned_user_id || '');
		$f.find('[name=due_date]').val(r.due_date || '');
		$f.find('[name=dispatch_order_id]').val(r.dispatch_order_id || '');
		$f.find('[name=resolution]').val(r.resolution || '');
		$f.find('.crm-manage-subject').html('<strong>' + esc(r.code) + '</strong> · ' + esc(r.subject));
		$('#crm-manage-request').modal('show');
	});
	$('#crm-manage-form').on('submit', function (e) {
		e.preventDefault();
		var d = formData($(this)), $btn = $(this).find('[type=submit]');
		busy($btn, true);
		api('customers/requests/' + d.id, d).then(function () { reload('Caso actualizado'); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});

	$(document).on('click', '[data-credit-decision]', function () {
		var r = $(this).data('credit-decision'), $f = $('#crm-credit-form');
		$f[0].reset();
		$f.find('[name=id]').val(r.id);
		if (r.requested_credit_limit !== null && r.requested_credit_limit !== undefined) {
			$f.find('[name=approved_limit]').val(r.requested_credit_limit);
		}
		$f.find('.crm-credit-subject').html('<strong>' + esc(r.code) + '</strong> · ' + esc(r.customer_name || '') + ' — ' + esc(r.subject));
		$('#crm-credit-decision').modal('show');
	});
	$('#crm-credit-form').on('submit', function (e) {
		e.preventDefault();
		var d = formData($(this)), $btn = $(this).find('[type=submit]');
		busy($btn, true);
		api('customers/requests/' + d.id + '/credit-decision', d).then(function (res) {
			reload('Dictamen financiero guardado: ' + (res.decision === 'approved_finance' ? 'Aprobado' : 'Rechazado'));
		}, function (x) { busy($btn, false); toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ duplicados */
	$('.crm-merge').on('click', function () {
		var g = $(this).data('group'), keep = parseInt($('input[name=keep-' + g + ']:checked').val(), 10);
		var others = String($(this).data('ids')).split(',').map(Number).filter(function (id) { return id !== keep; });
		if (!keep || !others.length) return;
		var $btn = $(this);
		busy($btn, true);
		others.reduce(function (p, id) { return p.then(function () { return api('customers/merge', { source_id: id, target_id: keep }); }); }, Promise.resolve())
			.then(function () { reload('Registros fusionados en #' + keep); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ campañas y automatizaciones (Tabs 6 & 7) */
	$('#crm-campaign-form').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]');
		busy($btn, true);
		api('customers/campaigns', formData($(this))).then(function () { reload('Campaña creada'); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});
	$(document).on('click', '.crm-campaign-toggle', function () {
		var $b = $(this), id = $b.data('campaign-id'), status = $b.data('next-status');
		busy($b, true);
		api('customers/campaigns', { id: id, status: status }).then(function () { reload('Campaña actualizada'); }, function (x) { busy($b, false); toastr.error(err(x)); });
	});
	$('#crm-run-automations').on('click', function () {
		var $b = $(this);
		busy($b, true);
		api('customers/automations/run', { branch_id: B.branchId || '', days: B.days || 30 }).then(function (r) {
			reload('Reglas evaluadas (' + r.total_matches + ' coincidencias activas)');
		}, function (x) { busy($b, false); toastr.error(err(x)); });
	});
	$(document).on('click', '.crm-auto-toggle', function () {
		var $b = $(this), id = $b.data('rule-id'), active = parseInt($b.data('next-active'), 10);
		busy($b, true);
		api('customers/automations/' + id + '/toggle', { active: active }).then(function () { reload('Regla actualizada'); }, function (x) { busy($b, false); toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ parámetros de fidelización */
	$('#crm-settings').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]');
		busy($btn, true);
		api('customers/settings', formData($(this))).then(function () { reload('Parámetros guardados'); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ ficha individual: consentimientos, preferencias, atención, puntos */
	$(document).on('click', '[data-consent]', function () {
		api('customers/' + B.customerId + '/consents', {
			purpose: $(this).data('consent'), granted: parseInt($(this).data('granted'), 10),
			source: $('#crm-consent-source').val(), evidence: $('#crm-consent-evidence').val()
		}).then(function () { reload('Consentimiento registrado'); }, function (x) { toastr.error(err(x)); });
	});
	$('#crm-prefs').on('submit', function (e) {
		e.preventDefault();
		api('customers/' + B.customerId + '/preferences', formData($(this))).then(function () { toastr.success('Preferencias guardadas'); }, function (x) { toastr.error(err(x)); });
	});
	$('#crm-interaction').on('submit', function (e) {
		e.preventDefault();
		var $btn = $(this).find('[type=submit]');
		busy($btn, true);
		api('customers/' + B.customerId + '/interactions', formData($(this))).then(function () { reload('Contacto registrado'); }, function (x) { busy($btn, false); toastr.error(err(x)); });
	});
	$('#crm-loyalty').on('submit', function (e) {
		e.preventDefault();
		api('customers/' + B.customerId + '/loyalty', formData($(this))).then(function () { reload('Ajuste de puntos registrado'); }, function (x) { toastr.error(err(x)); });
	});

	/* ------------------------------------------------------------ gráficos (con tooltips blanco alto contraste) */
	var darkTooltip = {
		enabled: true,
		mode: 'index',
		intersect: false,
		backgroundColor: 'rgba(15, 23, 42, 0.94)',
		titleFontColor: '#ffffff',
		titleFontStyle: 'bold',
		bodyFontColor: '#ffffff',
		borderColor: 'rgba(255, 255, 255, 0.16)',
		borderWidth: 1,
		cornerRadius: 8,
		xPadding: 12,
		yPadding: 10
	};

	var evoCanvas = document.getElementById('crmEvolutionChart');
	if (evoCanvas && window.Chart) {
		var mData = JSON.parse(document.getElementById('crm-monthly-data').textContent || '{}');
		var labels = Object.keys(mData);
		evoChart = new Chart(evoCanvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{ label: 'Recurrentes', data: labels.map(function (k) { return mData[k].recurrent; }), backgroundColor: '#008E48', yAxisID: 'yUsers' },
					{ label: 'Nuevos', data: labels.map(function (k) { return mData[k].new; }), backgroundColor: '#60a5fa', yAxisID: 'yUsers' },
					{ label: 'Ingreso identificado (' + SYM + ')', type: 'line', data: labels.map(function (k) { return mData[k].revenue; }), borderColor: '#f59e0b', backgroundColor: 'transparent', pointRadius: 3, lineTension: 0.28, yAxisID: 'yRev' }
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { position: 'bottom' },
				tooltips: Object.assign({}, darkTooltip, {
					callbacks: {
						label: function (item, data) {
							var lbl = data.datasets[item.datasetIndex].label || '';
							var v = Number(item.yLabel || 0);
							return item.datasetIndex === 2 ? (lbl + ': ' + money(v)) : (lbl + ': ' + v + ' clientes');
						}
					}
				}),
				scales: {
					xAxes: [{ stacked: true, gridLines: { display: false } }],
					yAxes: [
						{ id: 'yUsers', stacked: true, position: 'left', ticks: { beginAtZero: true, precision: 0 } },
						{ id: 'yRev', position: 'right', gridLines: { display: false }, ticks: { beginAtZero: true, callback: function (v) { return SYM + Number(v).toLocaleString(); } } }
					]
				}
			}
		});
	}

	var pm = document.getElementById('crm-profile-monthly');
	if (pm && window.Chart && B.monthly) {
		new Chart(pm, {
			type: 'bar',
			data: {
				labels: B.monthly.map(function (r) { return r.ym; }),
				datasets: [
					{ label: 'Importe', data: B.monthly.map(function (r) { return Math.round(r.revenue * 100) / 100; }), backgroundColor: '#008E48', yAxisID: 'y' },
					{ label: 'Compras', type: 'line', data: B.monthly.map(function (r) { return r.tx; }), borderColor: '#60a5fa', backgroundColor: 'transparent', fill: false, yAxisID: 'y1', lineTension: 0.3 }
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { position: 'bottom' },
				tooltips: darkTooltip,
				scales: {
					xAxes: [{ gridLines: { display: false } }],
					yAxes: [
						{ id: 'y', ticks: { beginAtZero: true } },
						{ id: 'y1', position: 'right', gridLines: { display: false }, ticks: { beginAtZero: true, precision: 0 } }
					]
				}
			}
		});
	}
})(jQuery);
