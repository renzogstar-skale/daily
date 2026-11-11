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

	// Margen de seguridad para evitar que los elementos o tooltips queden pegados a los bordes
	g.layout = g.layout || {};
	g.layout.padding = g.layout.padding || { top: 8, right: 12, bottom: 6, left: 8 };

	// Tooltips globales DAILY: fondo oscuro sólido y textos/valores blancos de alto contraste
	g.tooltips.backgroundColor = 'rgba(15, 23, 42, 0.94)';
	g.tooltips.titleFontColor = '#FFFFFF';
	g.tooltips.titleFontStyle = 'bold';
	g.tooltips.titleFontSize = 12;
	g.tooltips.titleMarginBottom = 6;
	g.tooltips.bodyFontColor = '#FFFFFF';
	g.tooltips.bodyFontSize = 11;
	g.tooltips.bodySpacing = 4;
	g.tooltips.footerFontColor = '#E2E8F0';
	g.tooltips.footerFontStyle = 'normal';
	g.tooltips.footerMarginTop = 6;
	g.tooltips.borderColor = 'rgba(255, 255, 255, 0.15)';
	g.tooltips.borderWidth = 1;
	g.tooltips.cornerRadius = 8;
	g.tooltips.xPadding = 12;
	g.tooltips.yPadding = 10;
	g.tooltips.caretPadding = 6;
	g.tooltips.caretSize = 5;
	g.tooltips.displayColors = true;
	g.tooltips.multiKeyBackground = '#FFFFFF';

	// Prevención global contra corte de tooltips en los bordes del canvas (Chart.js 2)
	if (Chart.Tooltip && Chart.Tooltip.prototype)
	{
		var origTooltipUpdate = Chart.Tooltip.prototype.update;
		Chart.Tooltip.prototype.update = function(changed)
		{
			var res = origTooltipUpdate.apply(this, arguments);
			var m = this._model;
			if (m && this._chart && m.opacity !== 0 && m.width > 0 && m.height > 0)
			{
				var cw = this._chart.width;
				var ch = this._chart.height;
				var pad = 6;
				if (cw > 0)
				{
					if (m.x < pad)
					{
						m.x = pad;
					}
					else if (m.x + m.width > cw - pad)
					{
						m.x = Math.max(pad, cw - pad - m.width);
					}
				}
				if (ch > 0)
				{
					if (m.y < pad)
					{
						m.y = pad;
					}
					else if (m.y + m.height > ch - pad)
					{
						m.y = Math.max(pad, ch - pad - m.height);
					}
				}
				if (this._view)
				{
					this._view.x = m.x;
					this._view.y = m.y;
				}
			}
			return res;
		};
	}

	// Tooltip DAILY en HTML (mismo aspecto): por encima de todo, nunca recortado por el lienzo ni por overflow,
	// dentro de la pantalla y con valores exactos ($ con decimales solo en métricas monetarias)
	var tipEl = null, tipChart = null;
	var fmtMoney = function(v)
	{
		return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	};
	var fmtNum = function(v) { return v.toLocaleString('en-US', { maximumFractionDigits: 2 }); };
	function rawValue(chart, dp)
	{
		var ds = chart.data.datasets[dp.datasetIndex];
		var v = ds && ds.data ? ds.data[dp.index] : null;
		if (v && typeof v === 'object') v = v.y !== undefined ? v.y : v.x;
		v = typeof v === 'string' ? parseFloat(v) : v;
		return typeof v === 'number' && isFinite(v) ? v : null;
	}
	function axisIsMoney(chart, ds)
	{
		var horiz = /horizontal/i.test(chart.config.type || '');
		var id = horiz ? ds.xAxisID : ds.yAxisID;
		var sc = id && chart.scales[id];
		if (!sc) Object.keys(chart.scales || {}).some(function(k) { var s = chart.scales[k]; if (s.isHorizontal() === horiz) { sc = s; return true; } return false; });
		var cb = sc && sc.options && sc.options.ticks && sc.options.ticks.callback;
		try { if (cb && /\$/.test(String(cb.call(sc, 1234, 0, [1234])))) return true; } catch (e) { }
		// Serie financiera por nombre (ventas, valor, costo…), salvo cantidades o cifras en miles
		var axisName = sc && sc.options && sc.options.scaleLabel && sc.options.scaleLabel.labelString || '';
		return /venta|valor|costo|monto|p[ée]rdida|utilidad|ingreso|importe|usd|\$/i.test(ds.label || '') &&
			!/mil\b|miles|\bk\b|%|unid|ticket|transac|client|cantidad|hora|colab/i.test((ds.label || '') + ' ' + axisName);
	}
	// Mismo número con precisión completa: "$33K" o "$33,416" → "$33,416.00"; "Tickets: 1234.5" → "Tickets: 1,234.5"
	function refine(line, chart, dp)
	{
		var raw = rawValue(chart, dp);
		if (raw === null || typeof line !== 'string') return line;
		var done = false;
		line = line.replace(/(-?)\$\s?(-?)([\d,]+(?:\.\d+)?)\s?([KkMm]?)(?![\d.,])/g, function(tok, s1, s2, n, suf)
		{
			if (done) return tok;
			var v = parseFloat(n.replace(/,/g, '')) * (/k/i.test(suf) ? 1e3 : /m/i.test(suf) ? 1e6 : 1) * (s1 || s2 ? -1 : 1);
			var tol = suf ? Math.abs(raw) * 0.06 + 1 : 0.51;
			if (Math.abs(v - raw) > tol) return tok;
			done = true;
			return fmtMoney(raw);
		});
		if (done) return line;
		var m = /^(.*:\s*)(-?\d+(?:\.\d+)?)\s*$/.exec(line);
		if (m && Math.abs(parseFloat(m[2]) - raw) < 0.01)
		{
			return m[1] + (axisIsMoney(chart, chart.data.datasets[dp.datasetIndex]) ? fmtMoney(raw) : fmtNum(raw));
		}
		return line;
	}
	function esc(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function hideTip() { if (tipEl) tipEl.style.opacity = '0'; tipChart = null; }
	function externalTooltip(model)
	{
		var chart = this._chart;
		if (!chart || !chart.canvas) return;
		if (!tipEl)
		{
			tipEl = document.createElement('div');
			tipEl.className = 'daily-chart-tip';
			tipEl.setAttribute('role', 'tooltip');
			tipEl.style.cssText = 'position:fixed;left:0;top:0;z-index:2147483000;pointer-events:none;opacity:0;transition:opacity .12s ease;' +
				'background:rgba(15,23,42,.96);color:#fff;border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:10px 12px;' +
				'box-shadow:0 8px 24px rgba(15,23,42,.28);font:400 11.5px/1.45 ' + g.defaultFontFamily + ';max-width:min(320px,calc(100vw - 16px));' +
				'white-space:normal;overflow-wrap:anywhere;text-align:left;font-variant-numeric:tabular-nums;';
			document.body.appendChild(tipEl);
		}
		if (!model || model.opacity === 0 || !model.body || !model.body.length)
		{
			if (tipChart === chart) hideTip();
			return;
		}
		tipChart = chart;
		var dps = model.dataPoints || [];
		var title = (model.title || []).filter(function(t) { return t !== '' && t !== undefined && t !== null; });
		if (!title.length && dps[0] && chart.config.type !== 'doughnut' && chart.config.type !== 'pie')
		{
			var lb = chart.data.labels && chart.data.labels[dps[0].index];
			if (lb !== undefined && lb !== null && lb !== '') title = [Array.isArray(lb) ? lb.join(' ') : lb];
		}
		var html = title.length ? '<div style="font-weight:700;font-size:12px;margin-bottom:6px;color:#fff">' + title.map(esc).join('<br>') + '</div>' : '';
		model.body.forEach(function(b, i)
		{
			var lines = [].concat(b.before || [], b.lines || [], b.after || []);
			var c = (model.labelColors || [])[i] || {};
			var sw = c.backgroundColor || c.borderColor || '#10b981';
			if (typeof sw !== 'string') sw = c.borderColor && typeof c.borderColor === 'string' ? c.borderColor : '#10b981';
			lines.forEach(function(l, j)
			{
				var isMain = j === (b.before || []).length;
				var text = isMain && dps[i] ? refine(String(l), chart, dps[i]) : String(l);
				if (!text.trim()) return;
				html += '<div style="display:flex;align-items:center;gap:6px;margin-top:' + (html ? 3 : 0) + 'px;color:#fff;font-weight:' + (isMain ? 600 : 400) + '">' +
					(isMain && model.displayColors !== false ? '<span style="flex:0 0 10px;width:10px;height:10px;border-radius:2px;border:1px solid rgba(255,255,255,.85);background:' + esc(sw) + '"></span>' : '<span style="flex:0 0 10px"></span>') +
					'<span>' + esc(text) + '</span></div>';
			});
		});
		var foot = (model.footer || []).filter(Boolean);
		if (foot.length) html += '<div style="margin-top:6px;color:#e2e8f0">' + foot.map(esc).join('<br>') + '</div>';
		tipEl.innerHTML = html;
		var r = chart.canvas.getBoundingClientRect();
		var sx = r.width / (chart.width || r.width || 1), sy = r.height / (chart.height || r.height || 1);
		var px = r.left + model.caretX * sx, py = r.top + model.caretY * sy;
		var vw = document.documentElement.clientWidth || window.innerWidth, vh = document.documentElement.clientHeight || window.innerHeight;
		tipEl.style.opacity = '1';
		var w = tipEl.offsetWidth, h = tipEl.offsetHeight, gap = 12, pad = 8;
		var left = px + gap, top = py - h / 2;
		if (left + w > vw - pad) left = px - gap - w;
		if (left < pad) { left = Math.min(Math.max(pad, px - w / 2), vw - pad - w); top = py - h - gap; if (top < pad) top = py + gap; }
		top = Math.min(Math.max(pad, top), vh - pad - h);
		tipEl.style.left = Math.round(left) + 'px';
		tipEl.style.top = Math.round(top) + 'px';
	}
	Chart.plugins.register({
		id: 'dailyHtmlTooltip',
		beforeInit: function(chart)
		{
			var t = chart.options && chart.options.tooltips;
			if (!t || t.enabled === false || t.custom) return;
			t.enabled = false;
			t.custom = externalTooltip;
			// Líneas: el valor aparece al acercarse a la hora, sin tener que acertar al punto exacto
			if (chart.config.type === 'line' && t.mode === 'nearest' && t.intersect !== false) t.intersect = false;
		},
		destroy: function(chart) { if (tipChart === chart) hideTip(); }
	});
	window.addEventListener('scroll', function() { if (tipChart) hideTip(); }, true);
	document.addEventListener('touchstart', function(e) { if (tipChart && e.target !== tipChart.canvas) hideTip(); }, { passive: true });

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
	// Crystal: áreas de línea con degradado vertical (mismos datos y colores base)
	if (document.body && document.body.classList.contains('crystal-ui'))
	{
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

	// Modo noche: textos y rejilla legibles en cada dibujo; tooltips siempre en alto contraste
	var DARK = { text: '#cbd5e1', grid: 'rgba(148, 163, 184, 0.16)', tipBg: 'rgba(15, 23, 42, 0.96)', tipText: '#ffffff', tipBorder: 'rgba(255, 255, 255, 0.18)', sep: '#162030' };
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
	var gDay = { font: g.defaultFontColor, tipBg: 'rgba(15, 23, 42, 0.94)', tipTitle: '#FFFFFF', tipBody: '#FFFFFF', tipFooter: '#E2E8F0', tipBorder: 'rgba(255, 255, 255, 0.15)' };
	function applyGlobals(dark)
	{
		g.defaultFontColor = dark ? DARK.text : gDay.font;
		g.tooltips.backgroundColor = dark ? DARK.tipBg : gDay.tipBg;
		g.tooltips.titleFontColor = '#FFFFFF';
		g.tooltips.titleFontStyle = 'bold';
		g.tooltips.bodyFontColor = '#FFFFFF';
		g.tooltips.footerFontColor = '#E2E8F0';
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
			t.backgroundColor = dark ? DARK.tipBg : 'rgba(15, 23, 42, 0.94)';
			t.titleFontColor = '#FFFFFF';
			t.titleFontStyle = 'bold';
			t.bodyFontColor = '#FFFFFF';
			t.footerFontColor = '#E2E8F0';
			t.borderColor = dark ? DARK.tipBorder : 'rgba(255, 255, 255, 0.15)';
			t.borderWidth = 1;
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
