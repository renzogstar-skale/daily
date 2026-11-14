/**
 * DAILY — Filtros del encabezado de tableros (DashboardFilters), mismo comportamiento que /stockoverview.
 * Lo cargan /dispatches y /waste: sus botones .ded-dropdown-toggle no tenían manejador y no abrían.
 * Período (data-select-range), sucursal (data-select-branch) y departamento (data-select-dept) recargan con ?query.
 */
(function () {
	'use strict';

	function go(set) {
		var url = new URL(window.location.href);
		Object.keys(set).forEach(function (k) { if (set[k]) url.searchParams.set(k, set[k]); else url.searchParams.delete(k); });
		window.location.href = url.toString();
	}

	function rangeDates(r) {
		var t = new Date(), from, to;
		var fmt = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
		to = fmt(t);
		if (r === '30d') from = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - 29));
		else if (r === 'today') from = to;
		else if (r === 'yesterday') from = to = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - 1));
		else if (r === 'week') from = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() - ((t.getDay() + 6) % 7)));
		else if (r === 'month') from = fmt(new Date(t.getFullYear(), t.getMonth(), 1));
		else if (r === 'prev_month') { from = fmt(new Date(t.getFullYear(), t.getMonth() - 1, 1)); to = fmt(new Date(t.getFullYear(), t.getMonth(), 0)); }
		return from && to ? { start_date: from, end_date: to } : null;
	}

	function init() {
		var headers = [].filter.call(document.querySelectorAll('.ded-header'), function (h) { return !h.closest('#stockMovementsBoard'); });
		if (!headers.length) return;
		var closeAll = function () { document.querySelectorAll('.ded-header .ded-dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); }); };
		document.addEventListener('click', function (e) { if (!e.target.closest('.ded-dropdown-container')) closeAll(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
		headers.forEach(function (root) {
			root.querySelectorAll('.ded-dropdown-toggle').forEach(function (btn) {
				btn.setAttribute('aria-haspopup', 'true');
				btn.setAttribute('aria-expanded', 'false');
				btn.addEventListener('click', function (e) {
					e.preventDefault();
					e.stopPropagation();
					var menu = this.closest('.ded-dropdown-container').querySelector('.ded-dropdown-menu');
					var open = menu.classList.contains('show');
					closeAll();
					root.querySelectorAll('.ded-dropdown-toggle').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
					if (!open) { menu.classList.add('show'); this.setAttribute('aria-expanded', 'true'); }
				});
			});
			root.querySelectorAll('[data-select-branch]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); go({ branch_id: this.getAttribute('data-select-branch') }); }); });
			root.querySelectorAll('[data-select-dept]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); go({ department: this.getAttribute('data-select-dept') }); }); });
			root.querySelectorAll('[data-select-range]').forEach(function (a) {
				a.addEventListener('click', function (e) {
					e.preventDefault();
					var d = rangeDates(this.getAttribute('data-select-range'));
					if (d) go(d);
				});
			});
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
