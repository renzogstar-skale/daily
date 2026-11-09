/* DAIRY — Service worker: recursos estáticos en caché, páginas siempre desde la red con aviso sin conexión. La API nunca se cachea. */
var CACHE = 'dairy-static-v5'; // v5: limpia las copias por ?v=time() acumuladas en la caché
var SCOPE = self.registration.scope;
var OFFLINE_HTML = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
	+ '<title>Sin conexión | DAIRY</title><style>body{font-family:Roboto,system-ui,sans-serif;background:#f5f6f7;color:#32363a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;text-align:center}'
	+ 'div{max-width:340px}h1{font-size:20px;margin:0 0 8px}p{color:#6a6d70;font-size:14px}button{margin-top:12px;background:#0a6ed1;color:#fff;border:0;border-radius:8px;padding:12px 20px;font-size:15px}</style></head>'
	+ '<body><div><h1>Sin conexión</h1><p>DAIRY necesita conexión para mostrar datos reales. Revisa tu red e inténtalo de nuevo.</p><button onclick="location.reload()">Reintentar</button></div></body></html>';

self.addEventListener('install', function() { self.skipWaiting(); });

self.addEventListener('activate', function(e) {
	e.waitUntil(caches.keys().then(function(keys) {
		return Promise.all(keys.filter(function(k) { return k !== CACHE; }).map(function(k) { return caches.delete(k); }));
	}).then(function() { return self.clients.claim(); }));
});

function isStatic(url) {
	return /\/(packages|css|js|viewjs|img|uisounds)\//.test(url.pathname) || /\.(css|js|woff2?|ttf|svg|png|jpe?g|webp|ico)$/.test(url.pathname);
}

self.addEventListener('fetch', function(e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var url = new URL(req.url);
	if (url.origin !== self.location.origin || url.href.indexOf(SCOPE) !== 0) { return; }
	if (/\/api\//.test(url.pathname) || url.pathname.endsWith('/sw.js')) { return; }

	if (isStatic(url)) {
		e.respondWith(caches.open(CACHE).then(function(cache) {
			return cache.match(req).then(function(hit) {
				if (hit) return hit;
				return fetch(req).then(function(res) {
					if (res.ok) cache.put(req, res.clone());
					return res;
				});
			});
		}));
		return;
	}

	// DAIRY Mobile (/m): la página es solo el armazón (sin datos); se guarda para abrir la app sin conexión.
	// Los datos llegan de la API y la app muestra los últimos leídos en este dispositivo, marcados como sin conexión.
	if (req.mode === 'navigate' && /\/m\/?$/.test(url.pathname)) {
		e.respondWith(fetch(req).then(function(res) {
			if (res.ok && !res.redirected) { var c = res.clone(); caches.open(CACHE).then(function(cache) { cache.put(SCOPE + 'm', c); }); }
			return res;
		}).catch(function() {
			return caches.match(SCOPE + 'm').then(function(hit) { return hit || new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }); });
		}));
		return;
	}

	// Páginas: siempre red (datos reales y sesión); sin conexión se muestra un aviso, nunca datos guardados
	if (req.mode === 'navigate') {
		e.respondWith(fetch(req).catch(function() {
			return new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
		}));
	}
});

self.addEventListener('notificationclick', function(e) {
	e.notification.close();
	var target = (e.notification.data && e.notification.data.url) || SCOPE;
	e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(list) {
		for (var i = 0; i < list.length; i++) { if ('focus' in list[i]) { list[i].navigate(target); return list[i].focus(); } }
		return self.clients.openWindow(target);
	}));
});
