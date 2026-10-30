/* Auditorías de tienda: iniciar, responder checklist, cerrar y editar plantillas */
(function ($) {
	'use strict';
	var S = window.DairyAudits || {};
	var UI = window.DairyUI;

	$('#aud-start').on('click', function () {
		var html = '<div class="form-group"><label>Sucursal</label><select name="branch_id" class="custom-select">' + UI.options(S.branches) + '</select></div>' +
			'<div class="form-group"><label>Plantilla</label><select name="template_id" class="custom-select">' + UI.options(S.templates) + '</select></div>';
		UI.formDialog('Nueva auditoría', html, function (data, d) {
			UI.post('audits/start', data, function (r) {
				if (r.resumed) { toastr.info('Ya había una auditoría en curso: se continúa ' + r.audit_code); }
				location.href = U('/audits?id=' + r.id);
			}, d.find('.btn-primary'));
		}, 'Iniciar');
	});

	function answers() {
		return $('#aud-detail .aud-item').map(function () {
			var $i = $(this);
			var r = $i.find('input[type=radio]:checked').val();
			return r ? { item_id: $i.data('item'), result: r, comment: $i.find('.aud-comment').val() } : null;
		}).get();
	}
	function send(complete, btn) {
		var id = $('#aud-detail').data('id');
		UI.post('audits/' + id + '/answers', { answers: answers(), complete: complete, notes: $('#aud-notes').val() }, function (r) {
			toastr.success(r.message);
			if (complete) { UI.reload(); }
		}, btn);
	}
	$('#aud-save').on('click', function () { send(false, this); });
	$('#aud-complete').on('click', function () {
		var btn = this;
		bootbox.confirm('¿Cerrar la auditoría? Se calculará el puntaje y cada «No cumple» creará una tarea correctiva.', function (ok) { if (ok) { send(true, btn); } });
	});
	// Al marcar "No cumple" se enfoca el hallazgo
	$(document).on('change', '#aud-detail input[type=radio]', function () {
		if (this.value === 'fail') { $(this).closest('.aud-item').find('.aud-comment').trigger('focus'); }
	});

	// Editor de plantillas: una línea por punto; prefijo "!" = crítico; sufijo "|peso"
	$('#aud-templates').on('click', function () {
		var t = S.templates[0] || { id: '', name: '', area: '', items: [] };
		var render = function (tpl) {
			return tpl.items.map(function (i) { return (Number(i.is_critical) ? '!' : '') + i.question + (Number(i.weight) !== 1 ? ' |' + Number(i.weight) : ''); }).join('\n');
		};
		var html = '<div class="form-group"><label>Plantilla</label><select name="template" class="custom-select">' + UI.options(S.templates) + '<option value="">+ Nueva plantilla</option></select></div>' +
			'<div class="form-group"><label>Nombre</label><input name="name" class="form-control" value="' + UI.esc(t.name) + '"></div>' +
			'<div class="form-group"><label>Área</label><input name="area" class="form-control" value="' + UI.esc(t.area || '') + '"></div>' +
			'<div class="form-group"><label>Puntos a evaluar</label><textarea name="items" class="form-control" rows="10">' + UI.esc(render(t)) + '</textarea>' +
			'<small class="text-muted">Uno por línea. «!» al inicio = crítico; «|3» al final = peso 3.</small></div>';
		var dlg = UI.formDialog('Plantillas de auditoría', html, function (data, d) {
			var tpl = S.templates.filter(function (x) { return String(x.id) === String(data.template); })[0];
			var existing = tpl ? tpl.items.slice() : [];
			var items = data.items.split('\n').map(function (l) { return l.trim(); }).filter(Boolean).map(function (l) {
				var crit = l.charAt(0) === '!';
				if (crit) { l = l.slice(1).trim(); }
				var w = 1, m = l.match(/\|\s*([\d.]+)\s*$/);
				if (m) { w = Number(m[1]); l = l.slice(0, m.index).trim(); }
				var prev = existing.filter(function (x) { return x.question === l; })[0];
				return { id: prev ? prev.id : '', question: l, weight: w, is_critical: crit ? 1 : 0 };
			});
			UI.post('audits/templates', { id: data.template, name: data.name, area: data.area, items: items }, function () { toastr.success('Plantilla guardada'); d.modal('hide'); UI.reload(); }, d.find('.btn-primary'));
		});
		dlg.on('change', '[name=template]', function () {
			var tpl = S.templates.filter(function (x) { return String(x.id) === String(this.value); }, this)[0] || { name: '', area: '', items: [] };
			dlg.find('[name=name]').val(tpl.name);
			dlg.find('[name=area]').val(tpl.area || '');
			dlg.find('[name=items]').val(render(tpl));
		});
	});
})(jQuery);
