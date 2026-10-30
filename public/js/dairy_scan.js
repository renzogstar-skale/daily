/*
 * DAIRY: capa de lectores de código. Todas las fuentes entregan el mismo texto a DairyScan.onCode(); el servidor
 * (/api/scan/*) interpreta GS1, EAN/UPC y etiquetas LOC-.
 *  - wedge:  escáner tipo teclado (HID). Honeywell/Zebra/Datalogic en modo teclado o con el perfil «keystroke».
 *            Se reconoce por ráfaga (< 35 ms entre teclas, ≥ 4 caracteres, Enter/Tab final). GS (FNC1) llega como
 *            Ctrl+] o como el carácter 0x1D según la configuración del lector.
 *  - camera: cámara del teléfono con ZXing (EAN-8/13, UPC-A/E, Code 128/GS1-128, QR, Data Matrix).
 *  - bridge: SDK industrial nativo (Honeywell DataCollection / Zebra EMDK-DataWedge intent). Una PWA no puede usarlos
 *            directamente: un contenedor nativo debe inyectar window.DairyScanBridge = { start(cb), stop() }.
 *            PENDIENTE: requiere app contenedora y dispositivo para probar; sin él no se declara compatibilidad.
 */
(function () {
	'use strict';
	var listeners = [], adapters = {}, active = {}, last = { code: null, at: 0 };
	var BOUNCE_MS = 300; // rebote del gatillo; escanear unidad por unidad (aprox. 1/s) debe sumar

	function deviceId() {
		try {
			var id = localStorage.getItem('dairy_device_id');
			if (!id) { id = 'WEB-' + Math.random().toString(36).slice(2, 10).toUpperCase(); localStorage.setItem('dairy_device_id', id); }
			return id;
		} catch (e) { return 'WEB-SIN-ALMACEN'; }
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		return 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
	}

	var audio = null;
	function feedback(ok) {
		try { if (navigator.vibrate) { navigator.vibrate(ok ? 40 : [80, 60, 80]); } } catch (e) { /* sin vibración */ }
		try {
			audio = audio || new (window.AudioContext || window.webkitAudioContext)();
			var o = audio.createOscillator(), g = audio.createGain();
			o.frequency.value = ok ? 1800 : 300; g.gain.value = 0.05;
			o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? 0.08 : 0.25));
		} catch (e) { /* sin audio */ }
	}

	function emit(code, source) {
		code = String(code || '').replace(/[\r\n]+$/g, '');
		if (!code) { return; }
		var now = Date.now();
		if (code === last.code && now - last.at < BOUNCE_MS) { return; } // rebote local; el servidor también filtra
		last = { code: code, at: now };
		var evt = { code: code, source: source, device_id: deviceId(), scanned_at: new Date().toISOString(), client_uuid: uuid() };
		listeners.forEach(function (fn) { try { fn(evt); } catch (e) { console.error(e); } });
	}

	/* ---- Escáner tipo teclado ---- */
	adapters.wedge = (function () {
		var buf = '', lastKey = 0, handler = null;
		function onKey(e) {
			var now = Date.now(), gap = now - lastKey;
			lastKey = now;
			if (gap > 60) { buf = ''; }
			// Con foco en un campo (incluido el de escaneo, que envía con su propio Enter) el texto va al campo
			var t = e.target;
			if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) { buf = ''; return; }
			if (e.key === 'Enter' || e.key === 'Tab') {
				if (buf.length >= 4 && gap < 60) {
					e.preventDefault();
					var code = buf; buf = '';
					emit(code, 'wedge');
					return;
				}
				buf = '';
				return;
			}
			if (e.ctrlKey && (e.key === ']' || e.code === 'BracketRight')) { buf += '\x1D'; return; }
			if (e.key && e.key.length === 1) { buf += e.key; }
		}
		return {
			label: 'Escáner (teclado)',
			available: function () { return true; },
			start: function () { if (!handler) { handler = onKey; document.addEventListener('keydown', handler, true); } },
			stop: function () { if (handler) { document.removeEventListener('keydown', handler, true); handler = null; } }
		};
	})();

	/* ---- Cámara (ZXing) ---- */
	adapters.camera = (function () {
		var reader = null, zxingUrl = null;
		function load() {
			if (window.ZXing) { return Promise.resolve(); }
			return new Promise(function (res, rej) {
				var s = document.createElement('script');
				s.src = zxingUrl || (Grocy.BaseUrl + '/packages/@zxing/library/umd/index.min.js');
				s.onload = res; s.onerror = function () { rej(new Error('No se pudo cargar el lector de cámara')); };
				document.head.appendChild(s);
			});
		}
		return {
			label: 'Cámara',
			available: function () { return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia); },
			setUrl: function (u) { zxingUrl = u; },
			start: function (video) {
				return load().then(function () {
					var hints = new Map(), F = ZXing.BarcodeFormat;
					hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.QR_CODE, F.DATA_MATRIX]);
					hints.set(ZXing.DecodeHintType.ASSUME_GS1, true);
					reader = new ZXing.BrowserMultiFormatReader(hints);
					return reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, video, function (result) {
						if (result) { emit(result.getText(), 'camera'); }
					});
				});
			},
			stop: function () { if (reader) { reader.reset(); reader = null; } }
		};
	})();

	/* ---- Puente para SDK industrial (pendiente de contenedor nativo) ---- */
	adapters.bridge = {
		label: 'Lector industrial (SDK)',
		available: function () { return !!(window.DairyScanBridge && typeof window.DairyScanBridge.start === 'function'); },
		start: function () { if (this.available()) { window.DairyScanBridge.start(function (code) { emit(code, 'bridge'); }); } },
		stop: function () { if (this.available() && window.DairyScanBridge.stop) { window.DairyScanBridge.stop(); } }
	};

	window.DairyScan = {
		deviceId: deviceId,
		uuid: uuid,
		feedback: feedback,
		emit: emit,
		adapters: adapters,
		registerAdapter: function (name, impl) { adapters[name] = impl; },
		onCode: function (fn) { listeners.push(fn); return function () { listeners = listeners.filter(function (f) { return f !== fn; }); }; },
		start: function (name, arg) { var a = adapters[name]; if (!a || !a.available()) { return Promise.reject(new Error('Lector no disponible: ' + name)); } active[name] = true; return Promise.resolve(a.start(arg)); },
		stop: function (name) { if (adapters[name]) { adapters[name].stop(); } delete active[name]; },
		isActive: function (name) { return !!active[name]; }
	};
})();
