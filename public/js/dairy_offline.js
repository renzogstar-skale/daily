/*
 * DAIRY — Operaciones de tienda sin conexión.
 * DairyOffline.send(path, body, {label, photo}) envía a /api/<path>. Si no hay red, la operación (y su foto) se guarda
 * en IndexedDB y se reenvía al volver la conexión. Cada operación lleva una clave X-Idempotency-Key fija: un reenvío
 * nunca se ejecuta dos veces en el servidor (DairyIdempotencyMiddleware). Un rechazo del servidor (validación, permiso,
 * estado ya cambiado por otro usuario) queda como conflicto visible para descartarlo; nunca se reintenta a ciegas.
 * La cola es por usuario: en un equipo compartido no se envían operaciones de otra sesión.
 */
(function(window, $) {
	'use strict';
	var DB_NAME = 'dairy-offline', STORE = 'queue', db = null, syncing = false, listeners = [];
	var userId = (window.Grocy && Grocy.UserId) || -1;

	function openDb() {
		if (db) { return Promise.resolve(db); }
		return new Promise(function(resolve, reject) {
			if (!window.indexedDB) { reject(new Error('IndexedDB no disponible')); return; }
			var req = indexedDB.open(DB_NAME, 1);
			req.onupgradeneeded = function() { req.result.createObjectStore(STORE, { keyPath: 'key' }); };
			req.onsuccess = function() {
				var conn = db = req.result;
				// Otra pestaña actualiza o borra la base: cerrar para no bloquearla
				conn.onversionchange = function() { conn.close(); if (db === conn) { db = null; } };
				resolve(db);
			};
			req.onerror = function() { reject(req.error); };
			req.onblocked = function() { reject(new Error('Almacenamiento local en uso por otra pestaña de DAIRY')); };
		});
	}

	function tx(mode, fn) {
		return openDb().then(function(d) {
			return new Promise(function(resolve, reject) {
				var t = d.transaction(STORE, mode), store = t.objectStore(STORE), out = fn(store);
				t.oncomplete = function() { resolve(out && out.result !== undefined ? out.result : out); };
				t.onerror = function() { reject(t.error); };
			});
		});
	}

	function all() {
		return tx('readonly', function(s) { return s.getAll(); }).then(function(items) {
			return (items || []).filter(function(i) { return i.user_id === userId; }).sort(function(a, b) { return a.created - b.created; });
		}).catch(function() { return []; });
	}
	function put(item) { return tx('readwrite', function(s) { s.put(item); }); }
	function remove(key) { return tx('readwrite', function(s) { s.delete(key); }); }

	function newKey() {
		if (window.crypto && crypto.randomUUID) { return 'off-' + crypto.randomUUID(); }
		return 'off-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
	}

	// Resultado: {ok:true,data} | {ok:false,network:true} | {ok:false,status,message}
	function request(path, body, key) {
		return fetch(Grocy.BaseUrl + '/api' + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Idempotency-Key': key },
			body: JSON.stringify(body || {})
		}).then(function(res) {
			return res.text().then(function(text) {
				var data = {};
				try { data = text ? JSON.parse(text) : {}; } catch (e) { data = {}; }
				if (res.ok) { return { ok: true, data: data, replay: res.headers.get('X-Idempotent-Replay') === '1' }; }
				return { ok: false, status: res.status, message: data.error_message || ('Error ' + res.status) };
			});
		}, function() { return { ok: false, network: true }; });
	}

	function uploadPhoto(photo) {
		var fd = new FormData();
		fd.append('photo', photo, photo.name || 'evidencia.jpg');
		return fetch(Grocy.BaseUrl + '/api/waste/upload-photo', { method: 'POST', credentials: 'same-origin', body: fd }).then(function(res) {
			return res.json().catch(function() { return {}; }).then(function(data) {
				if (res.ok && data.relative_url) { return { ok: true, url: data.relative_url }; }
				return { ok: false, status: res.status, message: data.error_message || 'No se pudo subir la foto' };
			});
		}, function() { return { ok: false, network: true }; });
	}

	// Envía un elemento (con foto primero, si tiene). La ruta de la foto se guarda para no volver a subirla.
	function deliver(item) {
		var step = Promise.resolve({ ok: true });
		if (item.photo && !item.body.evidence_photo_path) {
			step = uploadPhoto(item.photo).then(function(r) {
				if (r.ok) { item.body.evidence_photo_path = r.url; item.photo = null; }
				return r;
			});
		}
		return step.then(function(r) { return r.ok ? request(item.path, item.body, item.key) : r; });
	}

	function isRetryable(r) { return r.network || r.status === 401 || r.status === 409 || r.status >= 500; }

	function send(path, body, opts) {
		opts = opts || {};
		var item = { key: newKey(), user_id: userId, path: path, body: $.extend({}, body), photo: opts.photo || null, label: opts.label || path, created: Date.now(), state: 'pending', message: null };
		if (navigator.onLine === false) { return enqueue(item); }
		return deliver(item).then(function(r) {
			if (r.ok) { return { queued: false, data: r.data }; }
			if (r.network) { return enqueue(item); }
			var err = new Error(r.message); err.status = r.status; throw err;
		});
	}

	function enqueue(item) {
		return put(item).then(function() {
			notify();
			if (window.toastr) { toastr.info('Sin conexión: «' + item.label + '» se enviará al recuperar la red', '', { timeOut: 6000 }); }
			return { queued: true, key: item.key };
		}, function() {
			var err = new Error('Sin conexión y sin almacenamiento local disponible: la operación no se guardó'); err.status = 0; throw err;
		});
	}

	function sync() {
		if (syncing || navigator.onLine === false) { return Promise.resolve(); }
		syncing = true; notify();
		return all().then(function(items) {
			var pending = items.filter(function(i) { return i.state === 'pending'; }), sent = 0, conflicts = 0;
			return pending.reduce(function(p, item) {
				return p.then(function(stop) {
					if (stop) { return true; }
					return deliver(item).then(function(r) {
						if (r.ok) { sent++; return remove(item.key).then(function() { return false; }); }
						if (isRetryable(r)) { item.message = r.network ? null : r.message; return put(item).then(function() { return true; }); }
						conflicts++; item.state = 'conflict'; item.message = r.message; item.photo = null;
						return put(item).then(function() { return false; });
					});
				});
			}, Promise.resolve(false)).then(function() {
				if (window.toastr) {
					if (sent) { toastr.success(sent + (sent === 1 ? ' operación sincronizada' : ' operaciones sincronizadas')); }
					if (conflicts) { toastr.warning(conflicts + ' rechazada(s) por el servidor: revise la cola sin conexión'); }
				}
			});
		}).then(function() { syncing = false; notify(); }, function() { syncing = false; notify(); });
	}

	function notify() {
		all().then(function(items) {
			var state = { pending: items.filter(function(i) { return i.state === 'pending'; }).length, conflicts: items.filter(function(i) { return i.state === 'conflict'; }).length, syncing: syncing, online: navigator.onLine !== false, items: items };
			renderBadge(state);
			listeners.forEach(function(fn) { try { fn(state); } catch (e) {} });
		});
	}

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

	function renderBadge(st) {
		var $b = $('#dairy-offline-badge');
		if (!$b.length) {
			$b = $('<button type="button" id="dairy-offline-badge" class="dairy-offline-badge" aria-live="polite"></button>').appendTo('body').on('click', openPanel);
		}
		var text = '', tone = '';
		if (st.conflicts) { text = st.conflicts + ' por revisar'; tone = 'danger'; }
		else if (st.syncing) { text = 'Sincronizando…'; tone = 'info'; }
		else if (st.pending) { text = st.pending + ' sin enviar'; tone = 'warning'; }
		else if (!st.online) { text = 'Sin conexión'; tone = 'warning'; }
		$b.toggle(!!text).attr('data-tone', tone).html('<i class="fa-solid ' + (st.online ? 'fa-cloud-arrow-up' : 'fa-wifi') + ' mr-1" aria-hidden="true"></i>' + esc(text) + (!st.online && (st.pending || st.conflicts) ? ' · sin conexión' : ''));
	}

	function openPanel() {
		all().then(function(items) {
			var rows = items.map(function(i) {
				return '<li class="list-group-item d-flex justify-content-between align-items-start" data-key="' + esc(i.key) + '"><div class="mr-2" style="min-width:0">'
					+ '<div class="font-weight-bold">' + esc(i.label) + '</div><div class="small text-muted">' + new Date(i.created).toLocaleString() + (i.photo ? ' · con foto' : '') + '</div>'
					+ (i.state === 'conflict' ? '<div class="small text-danger">Rechazada: ' + esc(i.message) + '</div>' : (i.message ? '<div class="small text-muted">' + esc(i.message) + '</div>' : ''))
					+ '</div><button type="button" class="btn btn-sm btn-outline-danger dairy-offline-discard" title="Descartar">' + (i.state === 'conflict' ? 'Descartar' : 'Cancelar') + '</button></li>';
			}).join('');
			var html = items.length ? '<p class="small text-muted">Se envían en orden al recuperar la conexión, una sola vez cada una. Las rechazadas no se reintentan: corrija y registre de nuevo si corresponde.</p><ul class="list-group">' + rows + '</ul>'
				: '<p class="mb-0 text-muted">No hay operaciones pendientes.</p>';
			var dlg = bootbox.dialog({ title: 'Operaciones sin conexión', message: html, onEscape: true, buttons: {
				sync: { label: 'Sincronizar ahora', className: 'btn-primary', callback: function() { sync(); } },
				close: { label: 'Cerrar', className: 'btn-secondary' }
			} });
			dlg.on('click', '.dairy-offline-discard', function() {
				var $li = $(this).closest('li');
				remove($li.data('key')).then(function() { $li.remove(); notify(); });
			});
		});
	}

	window.DairyOffline = { send: send, sync: sync, items: all, onChange: function(fn) { listeners.push(fn); notify(); } };

	window.addEventListener('online', function() { sync(); });
	window.addEventListener('offline', notify);
	document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'visible') { sync(); } });
	$(function() { notify(); sync(); });
})(window, jQuery);
