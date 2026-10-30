/* Integración POS (Etapa 32): estado honesto del conector, importación CSV (validar / importar), errores y reintentos */
(function ($) {
	'use strict';
	var loaded = false;
	var stateText = { not_configured: 'Sin conector de extracción', pending_credentials: 'Pendiente de credenciales', pending_validation: 'Pendiente de validación' };
	var statusText = { imported: 'Importada', duplicate: 'Duplicada', failed: 'Error', valid: 'Válida' };
	var statusTone = { imported: 'success', duplicate: 'secondary', failed: 'danger', valid: 'info' };
	var kindText = { sale: 'Venta', refund: 'Devolución', void: 'Anulación' };
	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html().replace(/"/g, '&quot;'); }
	function errMsg(xhr) { try { return JSON.parse(xhr.responseText).error_message || 'Error'; } catch (e) { return 'No se pudo completar la operación'; } }

	function load() {
		loaded = true;
		Grocy.Api.Get('pos/status', render, function (xhr) { $('#pos-status').html('<div class="alert alert-danger">' + esc(errMsg(xhr)) + '</div>'); });
	}
	function render(s) {
		var live = s.sources.length > 0;
		$('#pos-status').html(
			'<div class="alert ' + (live ? 'alert-success' : 'alert-warning') + ' d-flex flex-wrap align-items-center dairy-gap-2 mb-3" role="status"><i class="fa-solid ' + (live ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i><div>' +
			(live ? '<strong>Recibiendo ventas externas</strong> de ' + s.sources.length + ' origen(es).' : '<strong>Ningún POS externo ha enviado ventas.</strong> Las cifras de ventas provienen solo del POS de DAILY.') +
			' Extracción programada: <strong>' + esc(stateText[s.pull_connector.state] || s.pull_connector.state) + '</strong> — ' + esc(s.pull_connector.message) + '</div></div>' +
			'<div class="card mb-0"><div class="card-body small"><div class="row">' +
			'<div class="col-md-6 mb-2"><div class="text-muted">Webhook / API</div><code>POST ' + esc(s.webhook.endpoint) + '</code><div>' + esc(s.webhook.auth) + (s.webhook.signature_required ? ' · firma <code>X-Dairy-Signature: sha256=…</code> exigida' : ' · sin firma HMAC (configure POS_WEBHOOK_SECRET)') + '</div></div>' +
			'<div class="col-md-3 mb-2"><div class="text-muted">Zona horaria sin offset</div><strong>' + esc(s.timezone) + '</strong></div>' +
			'<div class="col-md-3 mb-2"><div class="text-muted">Moneda aceptada</div><strong>' + esc(s.currency) + '</strong></div></div>' +
			(live ? '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Origen</th><th class="text-right">Importadas</th><th class="text-right">Con error</th><th>Última venta</th><th>Última actividad</th></tr></thead><tbody>' +
				s.sources.map(function (r) { return '<tr><td>' + esc(r.source) + '</td><td class="text-right">' + esc(r.imported) + '</td><td class="text-right' + (Number(r.failed) ? ' text-danger font-weight-700' : '') + '">' + esc(r.failed) + '</td><td>' + esc(r.last_sale_at || '—') + '</td><td>' + esc(r.last_activity) + '</td></tr>'; }).join('') +
				'</tbody></table></div>' : '') + '</div></div>');
		$('#pos-csv-help').html('Una fila por línea de producto; las filas con el mismo ticket forman una venta. Obligatorias: <code>ticket</code>, <code>sucursal</code> (código o id), <code>fecha</code> (AAAA-MM-DD HH:MM o ISO 8601) y producto por <code>codigo_barras</code>, <code>sku</code> o <code>producto_id</code> con <code>cantidad</code> y <code>precio_unitario</code>. Opcionales: <code>tipo</code> (sale/refund/void), <code>ticket_original</code>, <code>metodo_pago</code>, <code>moneda</code>, <code>impuesto</code>, <code>descuento</code>. Reenviar un archivo no duplica ventas.');
		if (!s.can_import) { $('#pos-import-col').addClass('d-none'); }
		$('#pos-failed').html(s.failed.length ? '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Ticket</th><th>Motivo</th><th></th></tr></thead><tbody>' +
			s.failed.map(function (f) {
				return '<tr><td class="text-nowrap"><div>' + esc(f.source) + ' · ' + esc(f.external_id) + '</div><div class="small text-muted">' + esc(kindText[f.kind] || f.kind) + (f.occurred_at ? ' · ' + esc(f.occurred_at) : '') + ' · ' + esc(f.attempts) + ' intento(s)</div></td>' +
					'<td class="small">' + esc(f.error) + '</td><td class="text-right">' + (s.can_import ? '<button type="button" class="btn btn-sm btn-outline-primary pos-retry" data-id="' + f.id + '">Reintentar</button>' : '') + '</td></tr>';
			}).join('') + '</tbody></table></div>' : '<div class="small text-muted p-3">Sin ventas externas pendientes de corregir.</div>');
	}
	function send(dry) {
		var file = $('#pos-file')[0].files[0];
		var source = $('#pos-source').val().trim();
		if (!source || !file) { toastr.warning('Indique el origen y el archivo CSV'); return; }
		var $btns = $('#pos-csv-form button').prop('disabled', true);
		var reader = new FileReader();
		reader.onload = function () {
			Grocy.Api.Post('pos/import-csv', { source: source, csv: reader.result, file_name: file.name, dry_run: dry ? 1 : 0 }, function (r) {
				$btns.prop('disabled', false);
				showResult(r);
				if (!dry) { toastr.success(r.imported + ' venta(s) importadas'); load(); }
			}, function (xhr) { $btns.prop('disabled', false); toastr.error(errMsg(xhr)); });
		};
		reader.onerror = function () { $btns.prop('disabled', false); toastr.error('No se pudo leer el archivo'); };
		reader.readAsText(file);
	}
	function showResult(r) {
		var parts = r.dry_run ? [r.valid + ' válidas', r.duplicates + ' ya importadas', r.failed + ' con error'] : [r.imported + ' importadas', r.duplicates + ' duplicadas', r.failed + ' con error'];
		$('#pos-result').html('<div class="font-weight-700 mb-1">' + (r.dry_run ? 'Validación (no se importó nada): ' : 'Resultado: ') + parts.join(' · ') + '</div>' +
			(r.dry_run ? '<div class="small text-muted mb-2">La validación no comprueba stock: eso ocurre al importar.</div>' : '') +
			'<div class="table-responsive" style="max-height: 320px"><table class="table table-sm mb-0"><thead><tr><th>Ticket</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>' +
			r.results.map(function (x) {
				return '<tr><td>' + esc(x.external_id || '—') + '</td><td><span class="badge badge-' + (statusTone[x.status] || 'secondary') + '">' + esc(statusText[x.status] || x.status) + '</span></td><td class="small">' +
					(x.error ? esc(x.error) : x.sale_id ? '<a href="#" class="view-sale-detail-btn" data-id="' + x.sale_id + '">Venta #' + x.sale_id + '</a>' : '') + '</td></tr>';
			}).join('') + '</tbody></table></div>');
	}

	// En /sales la pestaña es #subtab-pos
	$(document).on('shown.bs.tab', 'a[href="#tab-pos"], a[href="#subtab-pos"]', function () { if (!loaded) { load(); } });
	$(function () { if (location.hash === '#tab-pos' || location.hash === '#subtab-pos') { $('a[href="' + location.hash + '"]').tab('show'); load(); } });
	$('#pos-csv-form').on('submit', function (e) { e.preventDefault(); send(false); });
	$('#pos-validate').on('click', function () { send(true); });
	$(document).on('click', '.pos-retry', function () {
		var $b = $(this).prop('disabled', true);
		Grocy.Api.Post('pos/external/' + $b.data('id') + '/retry', {}, function (r) {
			if (r.status === 'imported') { toastr.success('Venta importada'); } else { toastr.error(r.error || 'Sigue con error'); }
			load();
		}, function (xhr) { $b.prop('disabled', false); toastr.error(errMsg(xhr)); });
	});
})(jQuery);
