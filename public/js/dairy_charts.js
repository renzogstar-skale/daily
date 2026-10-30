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
	if (Chart.defaults.scale)
	{
		Chart.defaults.scale.gridLines.color = DairyPalette.grid;
		Chart.defaults.scale.gridLines.zeroLineColor = token('border', '#e2e8f0');
	}
})();
