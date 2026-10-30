/* DAIRY: importación de maestros (validar → confirmar) */
(function ($) {
	'use strict';
	var U = window.DairyUI, esc = U.esc, E = window.DairyImportEntities || {}, content = null, fileName = null;

	function entity() { return $('#di-entity').val(); }
	function refreshLinks() {
		var e = entity(), base = Grocy.BaseUrl + '/api/data-import/' + e + '/template';
		$('#di-columns').text(Object.values(E[e].columns).join(', '));
		$('#di-template-csv').attr('href', base + '?format=csv');
		$('#di-template-xls').attr('href', base + '?format=xls');
		$('#di-export-xls').attr('href', base + '?format=xls&with_data=1');
		$('#di-commit').addClass('d-none');
		$('#di-result').empty();
	}
	$('#di-entity, #di-mode').on('change', refreshLinks);
	$('#di-file').on('change', function () { content = null; $('#di-commit').addClass('d-none'); });
	refreshLinks();

	function read() {
		var f = $('#di-file')[0].files[0];
		if (!f) { return Promise.reject(new Error('Seleccione un archivo')); }
		if (f.size > 5 * 1024 * 1024) { return Promise.reject(new Error('Archivo mayor a 5 MB')); }
		if (/\.xlsx?$/i.test(f.name)) { return Promise.reject(new Error('Guarde la hoja como CSV (Archivo → Guardar como → CSV UTF-8) e impórtela de nuevo')); }
		return new Promise(function (ok, ko) {
			var r = new FileReader();
			r.onload = function () { fileName = f.name; ok(r.result); };
			r.onerror = function () { ko(new Error('No se pudo leer el archivo')); };
			r.readAsText(f, 'UTF-8');
		});
	}

	function run(dryRun, btn) {
		var send = function (text) {
			content = text;
			U.post('data-import/' + entity(), { content: text, dry_run: dryRun ? 1 : 0, mode: $('#di-mode').val(), file_name: fileName }, function (r) { show(r); if (!dryRun) { toastr.success(r.message); $('#di-commit').addClass('d-none'); } }, btn);
		};
		(content && !dryRun ? Promise.resolve(content) : read()).then(send, function (e) { toastr.error(e.message); });
	}

	$('#di-form').on('submit', function (e) { e.preventDefault(); run(true, $('#di-validate')); });
	$('#di-commit').on('click', function () {
		var b = this;
		bootbox.confirm('¿Guardar ' + $(b).data('n') + ' registro(s)? Se aplicará todo o nada.', function (ok) { if (ok) { run(false, b); } });
	});

	function show(r) {
		var bad = r.results.filter(function (x) { return x.errors.length; }), warn = r.results.filter(function (x) { return !x.errors.length && x.warnings.length; });
		var head = '<div class="alert alert-' + (r.dry_run ? (r.rejected ? 'warning' : 'info') : 'success') + '">' +
			(r.dry_run ? 'Validación: ' : 'Importado: ') + r.rows + ' fila(s) · <b>' + r.to_create + '</b> alta(s) · <b>' + r.to_update + '</b> actualización(es) · <b class="' + (r.rejected ? 'text-danger' : '') + '">' + r.rejected + '</b> con error' +
			(r.ignored_columns.length ? '<div class="small mt-1">Columnas ignoradas: ' + esc(r.ignored_columns.join(', ')) + '</div>' : '') + '</div>';
		var rows = bad.concat(warn).slice(0, 300).map(function (x) {
			return '<tr><td>' + x.line + '</td><td>' + esc(x.data && x.data.name || '') + '</td><td>' + x.errors.map(function (m) { return '<div class="text-danger">' + esc(m) + '</div>'; }).join('') + x.warnings.map(function (m) { return '<div class="text-warning">' + esc(m) + '</div>'; }).join('') + '</td></tr>';
		}).join('');
		$('#di-result').html(head + (rows ? '<div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fila</th><th>Nombre</th><th>Observación</th></tr></thead><tbody>' + rows + '</tbody></table></div></div>' : ''));
		var n = r.to_create + r.to_update;
		$('#di-commit').toggleClass('d-none', !(r.dry_run && r.rejected === 0 && n > 0)).data('n', n);
	}
})(jQuery);
