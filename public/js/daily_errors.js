/*
 * Recuperación de errores DAILY (equivalente a un Error Boundary en una app multipágina):
 * - Registra en el servidor los errores de JavaScript no controlados y las solicitudes API que fallan con 5xx o sin red
 *   (POST /api/system/client-error → data/logs/client_errors.jsonl). No los oculta: también siguen en la consola.
 * - Muestra un aviso pequeño y recuperable («Reintentar») en lugar de dejar una sección vacía o la página bloqueada.
 * - window.DailyNotice(texto, reintentar) lo usa daily_nav.js cuando una navegación tarda demasiado.
 * Se carga en <head>, antes que el resto de scripts, para capturar también los errores de arranque.
 */
(function (w, d) {
	'use strict';
	var src = (d.currentScript && d.currentScript.src) || '';
	var base = src ? src.replace(/\/js\/daily_errors\.js.*$/, '') : '';
	var sent = {}, count = 0, shown = false;
	var en = function () { return d.documentElement.lang === 'en'; };
	var txt = function (es, eng) { return en() ? eng : es; };

	function report(kind, msg, extra) {
		var key = kind + '|' + msg;
		if (sent[key] || count >= 10 || !base) return;
		sent[key] = 1; count++;
		var body = JSON.stringify({ kind: kind, page: location.pathname + location.search, msg: msg, src: extra && extra.src || '', stack: extra && extra.stack || '' });
		try {
			if (w.navigator.sendBeacon) { w.navigator.sendBeacon(base + '/api/system/client-error', new Blob([body], { type: 'application/json' })); return; }
			var x = new XMLHttpRequest(); x.open('POST', base + '/api/system/client-error'); x.setRequestHeader('Content-Type', 'application/json'); x.send(body);
		} catch (e) { /* registrar el error nunca debe provocar otro */ }
	}

	function notice(text, retry) {
		var show = function () {
			var box = d.getElementById('daily-notice');
			if (!box) {
				box = d.createElement('div');
				box.id = 'daily-notice'; box.className = 'daily-notice'; box.setAttribute('role', 'alert');
				box.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span></span>'
					+ '<button type="button" class="daily-notice__retry"></button><button type="button" class="daily-notice__close" aria-label="Cerrar">&times;</button>';
				d.body.appendChild(box);
				box.querySelector('.daily-notice__close').addEventListener('click', function () { box.hidden = true; });
			}
			box.querySelector('span').textContent = text;
			var r = box.querySelector('.daily-notice__retry');
			r.textContent = txt('Reintentar', 'Retry');
			r.onclick = retry || function () { location.reload(); };
			box.querySelector('.daily-notice__close').setAttribute('aria-label', txt('Cerrar', 'Close'));
			box.hidden = false;
		};
		if (d.body) show(); else d.addEventListener('DOMContentLoaded', show);
	}
	w.DailyNotice = notice;

	// Errores ajenos a DAILY o sin información útil (extensiones, avisos benignos del navegador)
	var ignore = function (msg, file) {
		return !msg || /^Script error\.?$/.test(msg) || /ResizeObserver loop/.test(msg) || /^(chrome|moz|safari)-extension:/.test(file || '');
	};
	var failed = function () {
		if (shown) return;
		shown = true;
		notice(txt('Una parte de esta página no se cargó correctamente.', 'Part of this page did not load correctly.'));
	};

	w.addEventListener('error', function (e) {
		if (!(e instanceof ErrorEvent)) return; // recursos (img, css): los registra el servidor
		if (ignore(e.message, e.filename)) return;
		report('error', e.message, { src: (e.filename || '').replace(location.origin, '') + ':' + e.lineno + ':' + e.colno, stack: e.error && e.error.stack });
		failed();
	});
	w.addEventListener('unhandledrejection', function (e) {
		var r = e.reason || {};
		var msg = r.message || String(r);
		if (ignore(msg) || r.name === 'AbortError') return;
		report('promise', msg, { stack: r.stack });
		failed();
	});
	// Solicitudes de datos (jQuery) que fallan por el servidor o la red; los 4xx son validaciones que cada pantalla ya muestra
	d.addEventListener('DOMContentLoaded', function () {
		if (!w.jQuery) return;
		w.jQuery(d).ajaxError(function (ev, xhr, settings, thrown) {
			if (!xhr || thrown === 'abort' || xhr.statusText === 'abort' || (xhr.status > 0 && xhr.status < 500)) return;
			if (/\/api\/system\/client-error/.test(settings.url || '')) return;
			report('ajax', (settings.type || 'GET') + ' ' + (settings.url || '').replace(location.origin, '') + ' → ' + (xhr.status || txt('sin conexión', 'no connection')));
			failed();
		});
	});
})(window, document);
