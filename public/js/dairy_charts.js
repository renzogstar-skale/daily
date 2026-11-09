// DAIRY: paleta y estilo únicos para todos los gráficos (Chart.js 2.x)
// Los colores salen de los tokens --dairy-* del tema, así gráficos y UI comparten la misma paleta.
(function()
{
	if (typeof Chart === 'undefined')
	{
		return;
	}

	var css = getComputedStyle(document.documentElement);
	function token(name, fallback)
	{
		var v = css.getPropertyValue('--daily-' + name) || css.getPropertyValue('--dairy-' + name);
		return v ? v.trim() : fallback;
	}

	window.DailyUpdatePalette = function() {
		var css2 = getComputedStyle(document.documentElement);
		var p = (css2.getPropertyValue('--daily-primary') || css2.getPropertyValue('--dairy-primary') || '#00C83B').trim();
		if (window.DairyPalette) {
			window.DairyPalette.primary = p;
			window.DairyPalette.series[0] = p;
		}
	};

	window.DairyPalette = {
		primary: token('primary', '#00C83B'),
		success: token('success', '#059669'),
		warning: token('warning', '#d97706'),
		danger: token('danger', '#dc2626'),
		info: token('info', '#0891b2'),
		purple: token('purple', '#7c3aed'),
		muted: token('text-muted', '#64748b'),
		grid: token('border-subtle', '#f1f5f9'),
		text: token('text-body', '#334155')
	};
	// Orden para series sin color explícito
	DairyPalette.series = [DairyPalette.primary, DairyPalette.success, DairyPalette.warning, DairyPalette.danger, DairyPalette.purple, DairyPalette.info, DairyPalette.muted];

	var g = Chart.defaults.global;
	g.defaultFontFamily = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
	g.defaultFontColor = DairyPalette.text;
	g.defaultFontSize = 12;
	g.animation.duration = 0; // sin animaciones ni parpadeo al cargar
	g.hover.animationDuration = 0;
	g.responsiveAnimationDuration = 0;
	g.legend.labels.boxWidth = 12;
	g.tooltips.backgroundColor = token('text-main', '#0f172a');
	g.tooltips.cornerRadius = 6;
	g.tooltips.xPadding = 10;
	g.tooltips.yPadding = 8;
	if (g.plugins && g.plugins.colorschemes)
	{
		g.plugins.colorschemes.scheme = DairyPalette.series;
	}
	// El plugin de etiquetas externas se registra global y dibuja líneas sueltas en donas normales;
	// solo se activa en gráficos que lo configuran explícitamente (p. ej. outlabeledDoughnut de Grocy)
	Chart.plugins.getAll().forEach(function(plugin)
	{
		if (plugin.id !== 'outlabels')
		{
			return;
		}
		Object.keys(plugin).forEach(function(hook)
		{
			if (typeof plugin[hook] !== 'function')
			{
				return;
			}
			var original = plugin[hook];
			plugin[hook] = function(chart)
			{
				if (!chart || !chart.config || String(chart.config.type).indexOf('outlabeled') !== 0)
				{
					return;
				}
				return original.apply(this, arguments);
			};
		});
	});
	// Crystal: tooltips de cristal claro y áreas de línea con degradado vertical (mismos datos y colores base)
	if (document.body && document.body.classList.contains('crystal-ui'))
	{
		g.tooltips.backgroundColor = 'rgba(255, 255, 255, 0.94)';
		g.tooltips.titleFontColor = token('text-main', '#0f172a');
		g.tooltips.bodyFontColor = token('text-body', '#334155');
		g.tooltips.footerFontColor = token('text-muted', '#64748b');
		g.tooltips.borderColor = 'rgba(0, 142, 72, 0.22)';
		g.tooltips.borderWidth = 1;
		g.tooltips.cornerRadius = 10;
		var toRgba = function(c, a)
		{
			var m = /^#([0-9a-f]{6})$/i.exec(c);
			if (m) { var n = parseInt(m[1], 16); return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')'; }
			m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/.exec(c);
			return m ? 'rgba(' + m[1] + ',' + m[2] + ',' + m[3] + ',' + (m[4] === undefined ? 1 : m[4]) * a + ')' : null;
		};
		// Gráfico sin datos (sin series o solo valores vacíos): mensaje en lugar de un lienzo en blanco
		Chart.plugins.register({
			id: 'dailyNoData',
			afterDraw: function(chart)
			{
				var sets = chart.data.datasets || [];
				var has = sets.some(function(ds)
				{
					return (ds.data || []).some(function(v) { return v !== null && v !== undefined && v !== '' && !(typeof v === 'number' && isNaN(v)); });
				});
				if (has || !chart.chartArea) return;
				var ctx = chart.ctx, a = chart.chartArea;
				ctx.save();
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.font = '500 13px Inter, "Segoe UI", sans-serif';
				ctx.fillStyle = token('text-muted', '#64748b');
				ctx.fillText(document.documentElement.lang === 'en' ? 'No information available' : 'Sin información disponible', (a.left + a.right) / 2 || chart.width / 2, (a.top + a.bottom) / 2 || chart.height / 2);
				ctx.restore();
			}
		});
		Chart.plugins.register({
			id: 'crystalAreaGradient',
			afterLayout: function(chart)
			{
				var area = chart.chartArea, ctx = chart.ctx;
				if (!area || !ctx || area.bottom <= area.top) return;
				(chart.data.datasets || []).forEach(function(ds, i)
				{
					var type = ds.type || chart.config.type;
					if (type !== 'line' || ds.fill === false) return;
					if (ds._crystalBase === undefined) ds._crystalBase = typeof ds.backgroundColor === 'string' ? ds.backgroundColor : null;
					if (!ds._crystalBase) return;
					var top = toRgba(ds._crystalBase, 1.6), bottom = toRgba(ds._crystalBase, 0);
					// Gráfico dentro de una pestaña o sección oculta: sin área medible todavía
					if (!top || !isFinite(area.top) || !isFinite(area.bottom)) return;
					var grad = ctx.createLinearGradient(0, area.top, 0, area.bottom);
					grad.addColorStop(0, top);
					grad.addColorStop(1, bottom);
					ds.backgroundColor = grad;
				});
			}
		});
	}
	if (Chart.defaults.scale)
	{
		Chart.defaults.scale.gridLines.color = DairyPalette.grid;
		Chart.defaults.scale.gridLines.zeroLineColor = token('border', '#e2e8f0');
	}

	// Modo noche: textos, rejilla y tooltips legibles en cada dibujo; los colores de las
	// series (verde, ámbar, rojo) no se tocan. Al volver a Día se restauran los originales.
	var DARK = { text: '#cbd5e1', grid: 'rgba(148, 163, 184, 0.16)', tipBg: 'rgba(15, 23, 42, 0.96)', tipText: '#e2e8f0', tipBorder: 'rgba(148, 163, 184, 0.28)', sep: '#162030' };
	function rgb(c)
	{
		if (typeof c !== 'string') return null;
		var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(c.trim());
		if (m)
		{
			var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1], n = parseInt(h, 16);
			return [n >> 16, (n >> 8) & 255, n & 255];
		}
		m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(c);
		return m ? [+m[1], +m[2], +m[3]] : null;
	}
	function bright(c) { var x = rgb(c); return x ? (0.299 * x[0] + 0.587 * x[1] + 0.114 * x[2]) / 255 : -1; }
	function isDarkInk(c) { var b = bright(c); return c === undefined || c === null || (b >= 0 && b < 0.55); }
	function isLightLine(c) { var b = bright(c); return c === undefined || c === null || b > 0.78; }
	function swap(o, k, val, test, dark)
	{
		if (!o) return;
		var key = '_dk_' + k;
		if (dark)
		{
			if (!(key in o)) { if (!test(o[k])) return; o[key] = o[k]; }
			o[k] = val;
		}
		else if (key in o) { o[k] = o[key]; delete o[key]; }
	}
	var gDay = { font: g.defaultFontColor, tipBg: g.tooltips.backgroundColor, tipTitle: g.tooltips.titleFontColor, tipBody: g.tooltips.bodyFontColor, tipFooter: g.tooltips.footerFontColor, tipBorder: g.tooltips.borderColor };
	function applyGlobals(dark)
	{
		g.defaultFontColor = dark ? DARK.text : gDay.font;
		g.tooltips.backgroundColor = dark ? DARK.tipBg : gDay.tipBg;
		g.tooltips.titleFontColor = dark ? DARK.tipText : gDay.tipTitle;
		g.tooltips.bodyFontColor = dark ? DARK.tipText : gDay.tipBody;
		g.tooltips.footerFontColor = dark ? DARK.text : gDay.tipFooter;
		g.tooltips.borderColor = dark ? DARK.tipBorder : gDay.tipBorder;
	}
	function themeChart(chart, dark)
	{
		var o = chart.options || {};
		Object.keys(chart.scales || {}).forEach(function(id)
		{
			var so = chart.scales[id].options || {};
			if (so.ticks) swap(so.ticks, 'fontColor', DARK.text, isDarkInk, dark);
			if (so.pointLabels) swap(so.pointLabels, 'fontColor', DARK.text, isDarkInk, dark);
			if (so.scaleLabel) swap(so.scaleLabel, 'fontColor', DARK.text, isDarkInk, dark);
			if (so.gridLines)
			{
				swap(so.gridLines, 'color', DARK.grid, isLightLine, dark);
				swap(so.gridLines, 'zeroLineColor', DARK.grid, isLightLine, dark);
			}
			if (so.angleLines) swap(so.angleLines, 'color', DARK.grid, isLightLine, dark);
		});
		if (chart.legend && chart.legend.options && chart.legend.options.labels) swap(chart.legend.options.labels, 'fontColor', DARK.text, isDarkInk, dark);
		if (chart.titleBlock && chart.titleBlock.options) swap(chart.titleBlock.options, 'fontColor', DARK.text, isDarkInk, dark);
		[o.tooltips, chart.tooltip && chart.tooltip._options].forEach(function(t)
		{
			if (!t) return;
			swap(t, 'backgroundColor', DARK.tipBg, function(c) { return bright(c) > 0.7 || c === undefined; }, dark);
			swap(t, 'titleFontColor', DARK.tipText, isDarkInk, dark);
			swap(t, 'bodyFontColor', DARK.tipText, isDarkInk, dark);
			swap(t, 'footerFontColor', DARK.text, isDarkInk, dark);
		});
		// Separadores blancos de donas y barras apiladas
		(chart.data && chart.data.datasets || []).forEach(function(ds)
		{
			var type = ds.type || (chart.config && chart.config.type) || '';
			if (!/doughnut|pie|polar|outlabeled/i.test(type)) return;
			swap(ds, 'borderColor', DARK.sep, function(c) { return typeof c === 'string' ? bright(c) > 0.95 : c === undefined; }, dark);
		});
	}
	var isDark = function() { return document.documentElement.classList.contains('daily-dark'); };
	applyGlobals(isDark());
	Chart.plugins.register({
		id: 'dailyDarkMode',
		beforeUpdate: function(chart) { themeChart(chart, isDark()); },
		// Cada dibujo: otros complementos (leyenda) pueden haber rehecho sus opciones en la actualización
		beforeDraw: function(chart) { themeChart(chart, isDark()); }
	});
	document.addEventListener('daily:modechange', function()
	{
		applyGlobals(isDark());
		(Chart.instances ? Object.keys(Chart.instances).map(function(k) { return Chart.instances[k]; }) : []).forEach(function(chart)
		{
			try { themeChart(chart, isDark()); chart.update(0); } catch (e) { }
		});
	});
})();
