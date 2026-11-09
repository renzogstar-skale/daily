// DAILY · filtros compartidos: conserva sucursal, departamento, categoría, producto y fechas al navegar entre tableros.
// La URL manda: en un tablero que aplica un filtro, lo que trae la URL reemplaza lo guardado para ese filtro.
// Los enlaces a otros tableros (menú lateral incluido) reciben solo los filtros que ese tablero aplica (helpers/DailyFilters.php).
(function () {
	var KEY = 'daily.filters.v1';
	var cfgEl = document.getElementById('dailyFilterRoutes');
	if (!cfgEl) return;
	var ROUTES = JSON.parse(cfgEl.textContent);
	var base = new URL(cfgEl.getAttribute('data-base') || '/', location.href).pathname.replace(/\/$/, '');
	var read = function () { try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; } };
	var write = function (o) { try { Object.keys(o).length ? localStorage.setItem(KEY, JSON.stringify(o)) : localStorage.removeItem(KEY); } catch (e) { } };
	var routeOf = function (path) {
		var p = path.replace(base, '').replace(/\/$/, '') || '/';
		return ROUTES[p] ? p : null;
	};

	// 1. Guardar lo que trae la URL del tablero actual
	var here = routeOf(location.pathname), store = read();
	if (here) {
		var q = new URLSearchParams(location.search), keys = ROUTES[here];
		if (here === '/today' && q.get('date')) q.set('end_date', q.get('date'));
		keys.forEach(function (k) {
			// Las fechas ausentes son el valor por defecto del tablero, no un borrado
			if (q.has(k) && q.get(k) !== '') store[k] = q.get(k);
			else if (k !== 'start_date' && k !== 'end_date') delete store[k];
		});
		write(store);
	}

	// 2. Llevar los filtros aplicables a los enlaces de otros tableros
	var decorate = function (a) {
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) === '#' || a.hasAttribute('data-daily-filters-reset')) return;
		var u; try { u = new URL(href, location.href); } catch (e) { return; }
		if (u.origin !== location.origin || u.search) return;
		var r = routeOf(u.pathname);
		if (!r) return;
		var s = read(), add = false;
		ROUTES[r].forEach(function (k) {
			if (!s[k]) return;
			var name = (r === '/today' && k === 'end_date') ? 'date' : k;
			u.searchParams.set(name, s[k]); add = true;
		});
		if (add && (r === '/kpis' || r === '/waste') && (s.start_date || s.end_date)) u.searchParams.set('period', 'custom');
		if (add) a.setAttribute('href', u.pathname + u.search + u.hash);
	};
	document.querySelectorAll('a[href]').forEach(decorate);

	// 3. Restablecer: borra lo guardado y vuelve al tablero sin filtros
	document.addEventListener('click', function (e) {
		var t = e.target.closest('[data-daily-filters-reset]');
		if (t) write({});
	});

	// 4. Aviso compacto en el encabezado cuando hay filtros guardados
	var n = Object.keys(read()).length, zone = document.querySelector('.header-brand-zone');
	if (n && zone) {
		var chip = document.createElement('span');
		chip.className = 'daily-filters-chip d-none d-md-inline-flex';
		chip.setAttribute('data-testid', 'filters-chip');
		chip.innerHTML = '<i class="fa-solid fa-filter"></i>' + n + (n === 1 ? ' filtro activo' : ' filtros activos') + ' <button type="button">Restablecer</button>';
		chip.querySelector('button').addEventListener('click', function () { write({}); location.href = location.pathname; });
		zone.appendChild(chip);
	}
})();
