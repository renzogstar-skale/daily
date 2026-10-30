/* DAIRY: utilidades comunes de páginas (POST con errores reales, diálogos de formulario, escape) */
(function ($) {
	'use strict';
	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
	function errMsg(xhr) { try { return JSON.parse(xhr.responseText).error_message || 'Error'; } catch (e) { return 'No se pudo completar la operación'; } }
	function reload(hash) { setTimeout(function () { if (hash) { location.hash = hash; } location.reload(); }, 400); }

	// POST a la API; deshabilita el botón mientras corre y muestra el error real del servidor
	function post(url, body, ok, btn) {
		var $b = btn ? $(btn).prop('disabled', true) : null;
		Grocy.Api.Post(url, body, function (res) {
			if ($b) { $b.prop('disabled', false); }
			(res && res.warnings || []).forEach(function (w) { toastr.warning(w.message || w); });
			ok && ok(res);
		}, function (xhr) {
			if ($b) { $b.prop('disabled', false); }
			toastr.error(errMsg(xhr));
		});
	}

	function get(url, ok, fail) {
		Grocy.Api.Get(url, ok, function (xhr) { toastr.error(errMsg(xhr)); fail && fail(xhr); });
	}

	// Diálogo con formulario; onSave(data, dlg) recibe los campos name=; return false mantiene abierto
	function formDialog(title, html, onSave, okLabel) {
		var dlg = bootbox.dialog({
			title: title,
			message: '<form class="dairy-form-dialog" autocomplete="off">' + html + '</form>',
			buttons: {
				cancel: { label: 'Cancelar', className: 'btn-light border' },
				ok: {
					label: okLabel || 'Guardar', className: 'btn-primary', callback: function () {
						var data = {};
						dlg.find('form').serializeArray().forEach(function (f) { data[f.name] = f.value; });
						onSave(data, dlg);
						return false;
					}
				}
			}
		});
		dlg.on('submit', 'form', function (e) { e.preventDefault(); dlg.find('.btn-primary').trigger('click'); });
		return dlg;
	}

	function options(list, selected, valueKey, labelKey) {
		return list.map(function (i) {
			var v = i[valueKey || 'id'];
			return '<option value="' + esc(v) + '"' + (String(v) === String(selected) ? ' selected' : '') + '>' + esc(i[labelKey || 'name']) + '</option>';
		}).join('');
	}

	function money(v) { return '$' + Number(v || 0).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

	// Exporta las tablas visibles (pestaña activa) de un contenedor: CSV (Excel, UTF-8 con BOM) o impresión.
	// Omite columnas «Acciones» y filas de estado vacío; respeta data-export="false" en tablas o celdas.
	function cellText(c) { return (c.getAttribute('data-export-value') || c.innerText || '').replace(/\s+/g, ' ').trim(); }
	function exportTables(fmt, scope, name) {
		var root = scope ? document.querySelector(scope) : document.getElementById('page-content');
		var tables = Array.prototype.filter.call((root || document).querySelectorAll('table'), function (t) {
			return t.offsetParent !== null && t.getAttribute('data-export') !== 'false' && !t.closest('.modal');
		});
		if (!tables.length) { toastr.info('No hay tablas visibles para exportar en esta vista.'); return; }
		if (fmt === 'print') { window.print(); return; }
		var lines = [];
		tables.forEach(function (t, ti) {
			var heads = Array.prototype.map.call(t.querySelectorAll("thead th"), function (c) { return (c.getAttribute("data-export-value") || c.textContent || "").replace(/\s+/g, " ").trim(); });
			var skip = heads.map(function (h) { return /^(acciones?|acción)$/i.test(h) || h === ''; });
			var title = t.getAttribute('data-export-title') || (t.closest('.card') && t.closest('.card').querySelector('.card-header, h5, h6') || {}).innerText || '';
			if (tables.length > 1) { if (ti) { lines.push(''); } lines.push(csv([title.trim() || ('Tabla ' + (ti + 1))])); }
			if (heads.length) { lines.push(csv(heads.filter(function (h, i) { return !skip[i]; }))); }
			t.querySelectorAll('tbody tr').forEach(function (tr) {
				if (tr.offsetParent === null && !tr.classList.contains('dairy-more-hidden')) { return; }
				var cells = Array.prototype.filter.call(tr.children, function (c, i) { return !skip[i] && c.getAttribute('data-export') !== 'false'; });
				if (cells.length === 1 && tr.children.length === 1 && (tr.children[0].colSpan || 1) > 1) { return; }
				lines.push(csv(cells.map(cellText)));
			});
		});
		var file = (name || document.title.split('|')[0].trim() || 'dairy').replace(/[^\wáéíóúñÁÉÍÓÚÑ\- ]+/g, '').replace(/\s+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
		a.download = file;
		document.body.appendChild(a); a.click(); a.remove();
		toastr.success('Exportado: ' + file);
	}
	function csv(row) { return row.map(function (v) { v = String(v == null ? '' : v); return /[";,\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(';'); }

	// Pestañas ↔ URL: #tab-x abre la pestaña al cargar o al cambiar el hash, y la pestaña activa queda en la URL
	function openTabFromHash() {
		var h = window.location.hash;
		if (!h || h.length < 2 || !/^#[\w-]+$/.test(h)) { return; }
		var $l = $('a[data-toggle="pill"][href="' + h + '"], a[data-toggle="tab"][href="' + h + '"]').first();
		if ($l.length && !$l.hasClass('active')) { $l.tab('show'); }
	}
	// Móvil: tablas largas (table[data-chunk="N"]) muestran N filas y un botón «Ver más» (las filas ocultas siguen exportándose al expandir)
	function chunkTables() {
		if (window.innerWidth >= 768) { return; }
		$('table[data-chunk]').each(function () {
			var $t = $(this), n = parseInt($t.attr('data-chunk'), 10) || 20;
			var $rows = $t.find('> tbody > tr').filter(function () { return this.children.length > 1 || (this.children[0] && this.children[0].colSpan < 2); });
			if ($rows.length <= n + 3 || $t.data('chunked')) { return; }
			$t.data('chunked', true);
			$rows.slice(n).addClass('dairy-more-hidden');
			var $b = $('<button type="button" class="btn btn-light border btn-block my-2 dairy-more-btn"></button>');
			var label = function () { var left = $t.find('tr.dairy-more-hidden').length; return 'Ver ' + Math.min(left, n) + ' más (' + left + ' restantes)'; };
			$b.text(label()).on('click', function () {
				$t.find('tr.dairy-more-hidden').slice(0, n).removeClass('dairy-more-hidden');
				if ($t.find('tr.dairy-more-hidden').length) { $b.text(label()); } else { $b.remove(); }
			});
			($t.closest('.table-responsive').length ? $t.closest('.table-responsive') : $t).after($b);
		});
	}

	/*
	 * Accesibilidad común (Etapa 34, medida con axe-core): nombres accesibles para controles solo-icono y campos sin
	 * <label>, estructura ARIA de pestañas Bootstrap, regiones con desplazamiento alcanzables por teclado y barras de
	 * progreso con nombre. Solo completa lo que falta: nunca reemplaza un nombre o etiqueta existente.
	 */
	var ICON_NAMES = { 'fa-xmark': 'Limpiar', 'fa-times': 'Limpiar', 'fa-magnifying-glass': 'Buscar', 'fa-search': 'Buscar', 'fa-ellipsis': 'Más acciones', 'fa-ellipsis-vertical': 'Más acciones', 'fa-ellipsis-v': 'Más acciones',
		'fa-pen': 'Editar', 'fa-pen-to-square': 'Editar', 'fa-edit': 'Editar', 'fa-trash': 'Eliminar', 'fa-trash-can': 'Eliminar', 'fa-eye': 'Ver', 'fa-filter': 'Filtrar', 'fa-eye-slash': 'Columnas visibles', 'fa-plus': 'Agregar',
		'fa-download': 'Descargar', 'fa-print': 'Imprimir', 'fa-arrows-rotate': 'Actualizar', 'fa-rotate': 'Actualizar', 'fa-chevron-left': 'Anterior', 'fa-chevron-right': 'Siguiente', 'fa-check': 'Confirmar', 'fa-barcode': 'Escanear', 'fa-camera': 'Cámara', 'fa-clock-rotate-left': 'Historial', 'fa-play': 'Ejecutar', 'fa-ban': 'Descartar', 'fa-gear': 'Opciones', 'fa-table-columns': 'Columnas' };
	function hasName(el) {
		if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')) { return true; }
		if ((el.textContent || '').replace(/\s+/g, '').length) { return true; }
		var img = el.querySelector('img[alt]');
		return !!(img && img.alt);
	}
	function iconName(el) {
		var i = el.querySelector('i[class*="fa-"], span[class*="fa-"]');
		if (!i) { return null; }
		var c = Array.prototype.slice.call(i.classList).filter(function (k) { return ICON_NAMES[k]; })[0];
		return c ? ICON_NAMES[c] : null;
	}
	function fieldName(el) {
		// Sube hasta 4 niveles buscando una etiqueta visible con texto (la de un .input-group suele ser solo un icono)
		var t = '';
		for (var g = el.parentElement, k = 0; g && k < 4 && !t; g = g.parentElement, k++) {
			var lbl = g.querySelector('label, .small, .input-group-text');
			t = lbl && !lbl.contains(el) ? lbl.textContent.replace(/\s+/g, ' ').trim() : '';
		}
		return el.getAttribute('title') || el.getAttribute('data-original-title') || el.getAttribute('placeholder') || t || (el.tagName === 'SELECT' && el.options[0] ? el.options[0].textContent.trim() : '') || el.name || null;
	}
	function a11y(root) {
		root = root || document;
		root.querySelectorAll('.navbar-toggler:not([aria-label])').forEach(function (el) { el.setAttribute('aria-label', 'Abrir o cerrar el menú'); });
		root.querySelectorAll('button, a[href], [role="button"]').forEach(function (el) {
			if (hasName(el)) { return; }
			var n = el.getAttribute('title') || el.getAttribute('data-original-title') || iconName(el) || (el.type === 'submit' ? 'Enviar' : null);
			if (n) { el.setAttribute('aria-label', n.trim()); }
		});
		root.querySelectorAll('select, input:not([type="hidden"]):not([type="submit"]):not([type="button"]), textarea').forEach(function (el) {
			if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) || el.closest('label')) { return; }
			var n = fieldName(el);
			if (n) { el.setAttribute('aria-label', n.replace(/\s+/g, ' ').slice(0, 80)); }
		});
		// Pestañas Bootstrap: <ul role="tablist"><li> → los <li> son presentación (el rol tab lo tiene el enlace)
		root.querySelectorAll('[role="tablist"] > li').forEach(function (li) { li.setAttribute('role', 'presentation'); li.removeAttribute('tabindex'); });
		root.querySelectorAll('[role="tablist"] > a:not([role]), [role="tablist"] > li > a:not([role])').forEach(function (a) { a.setAttribute('role', 'tab'); });
		root.querySelectorAll('.progress-bar:not([aria-label]):not([aria-labelledby])').forEach(function (b) {
			var t = b.getAttribute('title') || b.getAttribute('data-original-title') || (b.textContent || '').trim() || ((b.getAttribute('aria-valuenow') || parseFloat(b.style.width) || 0) + ' %');
			b.setAttribute('aria-label', t);
			if (!b.getAttribute('role')) { b.setAttribute('role', 'progressbar'); }
		});
		// Zonas con desplazamiento propio (tablas anchas, listas con altura fija) alcanzables con teclado
		root.querySelectorAll('.table-responsive, [class*="dairy-scroll"], [data-dairy-scroll-x], .card-body, .dataTables_scrollBody, .list-group').forEach(function (el) {
			if (el.hasAttribute('tabindex') || el.querySelector('a[href], button, input, select, textarea, [tabindex]')) { return; }
			if (el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2) {
				el.setAttribute('tabindex', '0');
				if (!el.getAttribute('aria-label')) { var h = el.closest('.card'); h = h && h.querySelector('.card-header'); el.setAttribute('aria-label', (h ? h.textContent.trim().slice(0, 60) : 'Contenido') + ' (desplazable)'); }
				if (!el.getAttribute('role') && !/^(UL|OL|TABLE|TBODY)$/.test(el.tagName)) { el.setAttribute('role', 'region'); }
			}
		});
	}
	var a11yTimer = null;
	function a11ySoon() { clearTimeout(a11yTimer); a11yTimer = setTimeout(function () { a11y(document); }, 250); }

	$(function () {
		a11y(document);
		$(window).on('load', a11ySoon);
		if (window.MutationObserver) { new MutationObserver(a11ySoon).observe(document.body, { childList: true, subtree: true }); }
		chunkTables();
		openTabFromHash();
		$(window).on('hashchange', openTabFromHash);
		$(document).on('shown.bs.tab', '.dairy-tabs-url a[data-toggle]', function (e) {
			var h = $(e.target).attr('href');
			if (h && h.charAt(0) === '#' && history.replaceState) { history.replaceState(null, '', h); }
		});
	});

	window.DairyUI = { esc: esc, errMsg: errMsg, reload: reload, post: post, get: get, formDialog: formDialog, options: options, money: money, exportTables: exportTables, a11y: a11y };
})(jQuery);
