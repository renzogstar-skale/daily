// /reports — «Mis reportes»: constructor de reportes personalizables y programados (ReportBuilderService)
(function () {
	if (!document.getElementById('rb-card')) { return; }
	var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
	var err = function (xhr, d) { try { return JSON.parse(xhr.responseText).error_message || d; } catch (e) { return d; } };
	var cat = { datasets: {}, periods: {}, schedules: {} };
	var defs = [];

	function fillSelect(sel, obj, val) {
		$(sel).html(Object.keys(obj).map(function (k) { return '<option value="' + esc(k) + '">' + esc(obj[k].label || obj[k]) + '</option>'; }).join(''));
		if (val) { $(sel).val(val); }
	}
	function renderColumns(ds, chosen, sort) {
		var cols = (cat.datasets[ds] || {}).columns || {};
		chosen = chosen || Object.keys(cols).slice(0, 6);
		$('#rb-columns').html(Object.keys(cols).map(function (k) {
			return '<div class="custom-control custom-checkbox mr-2"><input type="checkbox" class="custom-control-input rb-col" id="rb-c-' + esc(k) + '" value="' + esc(k) + '"' + (chosen.indexOf(k) >= 0 ? ' checked' : '') + '>'
				+ '<label class="custom-control-label small" for="rb-c-' + esc(k) + '">' + esc(cols[k]) + '</label></div>';
		}).join(''));
		fillSelect('#rb-sort', cols, sort);
		$('#rb-period').prop('disabled', !(cat.datasets[ds] || {}).has_date);
	}
	function config() {
		return { columns: $('.rb-col:checked').map(function () { return this.value; }).get(), period: $('#rb-period').val(), branch_id: $('#rb-branch').val() || null,
			sort: $('#rb-sort').val(), dir: $('#rb-dir').val(), limit: parseInt($('#rb-limit').val(), 10) || 1000 };
	}
	function openForm(d) {
		d = d || { config: {} };
		$('#rb-id').val(d.id || '');
		$('#rb-name').val(d.name || '');
		$('#rb-dataset').val(d.dataset || Object.keys(cat.datasets)[0]);
		renderColumns($('#rb-dataset').val(), d.config.columns, d.config.sort);
		$('#rb-period').val(d.config.period || '30d');
		$('#rb-branch').val(d.config.branch_id || '');
		$('#rb-dir').val((d.config.dir || 'DESC').toLowerCase());
		$('#rb-limit').val(d.config.limit || 1000);
		$('#rb-schedule').val(d.schedule || 'none');
		$('#rb-hour').val(d.schedule_hour != null ? d.schedule_hour : 7);
		$('#rb-preview-out').empty();
		$('#rb-form').removeClass('d-none');
		$('#rb-name').trigger('focus');
	}
	function load() {
		Grocy.Api.Get('reports/definitions', function (r) {
			defs = r.definitions;
			var body = defs.length ? defs.map(function (d) {
				return '<tr><td>' + esc(d.name) + '</td><td>' + esc(d.dataset_label) + '</td><td>' + esc(cat.periods[d.config.period] || '—') + '</td>'
					+ '<td>' + esc(cat.schedules[d.schedule] || d.schedule) + (d.schedule !== 'none' ? ' · ' + esc(d.schedule_hour) + ':00<br><span class="small text-muted">próxima ' + esc(d.next_run_at || '—') + '</span>' : '') + '</td>'
					+ '<td>' + esc(d.last_run_at || 'nunca') + (d.last_status === 'failed' ? ' <span class="badge badge-danger">falló</span>' : '') + '</td>'
					+ '<td class="text-right text-nowrap"><button type="button" class="btn btn-sm btn-primary rb-run" data-id="' + d.id + '" title="Ejecutar ahora"><i class="fa-solid fa-play"></i><span class="sr-only">Ejecutar ' + esc(d.name) + '</span></button> '
					+ (d.last_run_id ? '<a class="btn btn-sm btn-light border" href="' + U('/api/reports/runs/' + d.last_run_id + '/csv') + '" title="Descargar último CSV"><i class="fa-solid fa-download"></i><span class="sr-only">Descargar ' + esc(d.name) + '</span></a> ' : '')
					+ '<button type="button" class="btn btn-sm btn-light border rb-edit" data-id="' + d.id + '" title="Editar"><i class="fa-solid fa-pen"></i><span class="sr-only">Editar ' + esc(d.name) + '</span></button> '
					+ '<button type="button" class="btn btn-sm btn-light border rb-del" data-id="' + d.id + '" title="Eliminar"><i class="fa-solid fa-trash"></i><span class="sr-only">Eliminar ' + esc(d.name) + '</span></button></td></tr>';
			}).join('') : '<tr><td colspan="6" class="text-muted text-center">Aún no tiene reportes guardados. Cree uno con «Nuevo reporte»: elija datos, columnas, período y, si quiere, una programación.</td></tr>';
			$('#rb-list tbody').html(body);
			$('#rb-runs tbody').html(r.runs.length ? r.runs.map(function (x) {
				return '<tr><td>' + esc(x.row_created_timestamp) + '</td><td>' + esc(x.name) + '</td><td>' + (x.trigger_type === 'schedule' ? 'Programado' : 'Manual') + '</td><td>' + esc(x.period_from ? x.period_from + ' → ' + x.period_to : '—') + '</td><td class="text-right">' + esc(x.row_count) + '</td>'
					+ '<td>' + (x.status === 'ok' ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger" title="' + esc(x.error_message) + '">Falló</span> <span class="small">' + esc(x.error_message) + '</span>') + '</td>'
					+ '<td>' + (x.status === 'ok' ? '<a href="' + U('/api/reports/runs/' + x.id + '/csv') + '">CSV</a>' : '') + '</td></tr>';
			}).join('') : '<tr><td colspan="7" class="text-muted text-center">Sin ejecuciones todavía.</td></tr>');
		}, function (xhr) { $('#rb-list tbody').html('<tr><td colspan="6" class="text-danger">' + esc(err(xhr, 'No se pudieron cargar los reportes')) + '</td></tr>'); });
	}

	Grocy.Api.Get('reports/catalog', function (c) {
		cat = c;
		if (!Object.keys(cat.datasets).length) {
			$('#rb-new').prop('disabled', true);
			$('#rb-list tbody').html('<tr><td colspan="6" class="text-muted text-center">Su usuario no tiene permiso sobre ningún conjunto de datos.</td></tr>');
			return;
		}
		fillSelect('#rb-dataset', cat.datasets);
		fillSelect('#rb-period', cat.periods);
		fillSelect('#rb-schedule', cat.schedules);
		load();
	}, function (xhr) { toastr.error(esc(err(xhr, 'No se pudo cargar el catálogo de reportes'))); });

	$('#rb-new').on('click', function () { openForm(); });
	$('#rb-cancel').on('click', function () { $('#rb-form').addClass('d-none'); });
	$('#rb-dataset').on('change', function () { renderColumns(this.value); });
	$('#rb-preview').on('click', function () {
		$('#rb-preview-out').html('<span class="text-muted"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Consultando…</span>');
		Grocy.Api.Post('reports/preview', { dataset: $('#rb-dataset').val(), config: config() }, function (r) {
			$('#rb-preview-out').html('<div class="small text-muted mb-1">' + esc(r.label) + (r.period ? ' · ' + esc(r.period[0]) + ' → ' + esc(r.period[1]) : '') + ' · ' + r.rows.length + ' fila(s)' + (r.truncated ? ' (vista previa limitada a 200)' : '') + '</div>'
				+ (r.rows.length ? '<div class="table-responsive" style="max-height:320px"><table class="table table-sm"><thead><tr>' + r.columns.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>'
					+ r.rows.map(function (row) { return '<tr>' + row.map(function (v) { return '<td>' + esc(v) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>'
					: '<p class="text-muted small mb-0">Sin datos para este período y filtros.</p>'));
		}, function (xhr) { $('#rb-preview-out').html('<span class="text-danger">' + esc(err(xhr, 'No se pudo generar la vista previa')) + '</span>'); });
	});
	$('#rb-form').on('submit', function (e) {
		e.preventDefault();
		var id = $('#rb-id').val();
		Grocy.Api.Post('reports/definitions' + (id ? '/' + id : ''), { name: $('#rb-name').val(), dataset: $('#rb-dataset').val(), config: config(), schedule: $('#rb-schedule').val(), schedule_hour: parseInt($('#rb-hour').val(), 10) }, function () {
			toastr.success('Reporte guardado');
			$('#rb-form').addClass('d-none');
			load();
		}, function (xhr) { toastr.error(esc(err(xhr, 'No se pudo guardar'))); });
	});
	$(document).on('click', '.rb-edit', function () { var id = $(this).data('id'); openForm(defs.filter(function (d) { return d.id == id; })[0]); });
	$(document).on('click', '.rb-run', function () {
		var b = $(this).prop('disabled', true);
		Grocy.Api.Post('reports/definitions/' + b.data('id') + '/run', {}, function (r) {
			r.status === 'ok' ? toastr.success('Ejecutado: ' + r.rows + ' fila(s). Descargue el CSV en la lista.') : toastr.error(esc(r.error));
			load();
		}, function (xhr) { b.prop('disabled', false); toastr.error(esc(err(xhr, 'No se pudo ejecutar'))); });
	});
	$(document).on('click', '.rb-del', function () {
		var id = $(this).data('id');
		bootbox.confirm({ message: '¿Eliminar este reporte y su programación? Las ejecuciones guardadas dejan de estar disponibles.', buttons: { confirm: { label: 'Eliminar', className: 'btn-danger' }, cancel: { label: 'Cancelar', className: 'btn-light border' } }, callback: function (ok) {
			if (ok) { Grocy.Api.Post('reports/definitions/' + id + '/delete', {}, function () { toastr.success('Reporte eliminado'); load(); }, function (xhr) { toastr.error(esc(err(xhr, 'No se pudo eliminar'))); }); }
		} });
	});
})();
