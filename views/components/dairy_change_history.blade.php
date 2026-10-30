{{-- DAIRY: historial de cambios del registro (dairy_change_log). Parámetros: $table, $rowId --}}
@if(!empty($rowId))
<details class="card mt-3 dairy-change-history" data-table="{{ $table }}" data-id="{{ $rowId }}">
	<summary class="card-header font-weight-700"><i class="fa-solid fa-clock-rotate-left mr-2 text-secondary"></i>Historial de cambios</summary>
	<div class="card-body p-0"><p class="small text-muted p-3 mb-0">Cargando…</p></div>
</details>
<script>
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.dairy-change-history').forEach(function (box) {
		box.addEventListener('toggle', function () {
			if (!box.open || box.dataset.loaded) { return; }
			box.dataset.loaded = '1';
			var body = box.querySelector('.card-body'), esc = function (v) { return $('<div>').text(v == null || v === '' ? '—' : String(v)).html(); };
			Grocy.Api.Get('change-history/' + box.dataset.table + '/' + box.dataset.id, function (rows) {
				if (!rows.length) { body.innerHTML = '<p class="small text-muted p-3 mb-0">Sin cambios registrados desde que se activó el historial.</p>'; return; }
				var label = { insert: 'Alta', update: 'Cambio', delete: 'Baja' };
				body.innerHTML = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody>' +
					rows.map(function (r) {
						var ch = r.action === 'insert' ? [{ field: '', from: '', to: 'registro creado' }] : r.changes;
						return ch.map(function (c, i) {
							return '<tr>' + (i === 0 ? '<td rowspan="' + ch.length + '" class="text-nowrap">' + esc(r.changed_at) + '</td><td rowspan="' + ch.length + '">' + esc(r.user || 'sistema') + '</td><td rowspan="' + ch.length + '">' + label[r.action] + '</td>' : '') +
								'<td>' + esc(c.field) + '</td><td class="text-muted">' + esc(c.from) + '</td><td>' + esc(c.to) + '</td></tr>';
						}).join('');
					}).join('') + '</tbody></table></div>';
			}, function () { body.innerHTML = '<p class="small text-danger p-3 mb-0">No se pudo cargar el historial.</p>'; });
		});
	});
});
</script>
@endif
