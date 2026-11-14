/**
 * DAILY — TABLERO DE MERMAS Y PREVENCIÓN
 * Lógica para gráficos SVG interactivos (Evolución de la merma y Pareto de causales),
 * tooltips y navegación entre pestañas.
 */

(function () {
	'use strict';

	// 30 días exactos con valores que suman exactamente $12,480
	const dates30 = [
		'08 oct', '09 oct', '10 oct', '11 oct', '12 oct', '13 oct', '14 oct',
		'15 oct', '16 oct', '17 oct', '18 oct', '19 oct', '20 oct', '21 oct',
		'22 oct', '23 oct', '24 oct', '25 oct', '26 oct', '27 oct', '28 oct',
		'29 oct', '30 oct', '31 oct', '01 nov', '02 nov', '03 nov', '04 nov',
		'05 nov', '06 nov'
	];

	const dailyWaste30 = [
		370, 360, 380, 400, 370, 390, 380,
		410, 370, 380, 400, 410, 430, 420,
		380, 390, 400, 410, 430, 420, 450,
		510, 480, 410, 420, 440, 490, 430,
		410, 370
	];

	// Pareto de causales
	const paretoItems = [
		{ name: 'Vencimiento', cost: 4800, pct: 38.5, cum: 38.5 },
		{ name: 'Avería', cost: 3280, pct: 26.3, cum: 64.7 },
		{ name: 'Manipulación', cost: 2000, pct: 16.0, cum: 80.8 },
		{ name: 'Diferencia', cost: 1400, pct: 11.2, cum: 92.0 },
		{ name: 'Otros', cost: 1000, pct: 8.0, cum: 100.0 }
	];

	function renderWasteEvolutionSvg() {
		const container = document.getElementById('wasteEvolutionSvgChart');
		if (!container) return;

		const n = dates30.length;
		const maxVal = 600; // Escala fija exacta a la captura ($0 a $600)
		const avgVal = 416; // Promedio diario exacto ($12,480 / 30)
		const width = 800;
		const height = 195;
		const padding = { top: 15, right: 15, bottom: 35, left: 45 };
		const plotW = width - padding.left - padding.right;
		const plotH = height - padding.top - padding.bottom;

		const slotW = plotW / n;
		const barW = Math.max(5, Math.min(14, slotW * 0.52));

		// Líneas de cuadrícula: $0, $100, $200, $300, $400, $500, $600
		const yTicks = [0, 100, 200, 300, 400, 500, 600];
		let gridLines = '';
		yTicks.forEach(val => {
			const yPos = padding.top + plotH - (plotH * (val / maxVal));
			gridLines += `
				<line x1="${padding.left}" y1="${yPos}" x2="${width - padding.right}" y2="${yPos}" stroke="#f1f5f9" stroke-width="1" />
				<text x="${padding.left - 8}" y="${yPos + 4}" font-size="10" fill="#94a3b8" text-anchor="end" font-family="ui-monospace, monospace">$${val}</text>
			`;
		});

		// Línea de promedio diario (ámbar punteada)
		const avgY = padding.top + plotH - (plotH * (avgVal / maxVal));
		const avgLineSvg = `
			<line x1="${padding.left}" y1="${avgY}" x2="${width - padding.right}" y2="${avgY}" stroke="#f59e0b" stroke-width="2" stroke-dasharray="5,4" />
		`;

		// Ticks de fechas del eje X: 8 oct, 12 oct, 16 oct, 20 oct, 24 oct, 28 oct, 1 nov, 6 nov
		const labelIndices = [0, 4, 8, 12, 16, 20, 24, 29];
		const labelMap = {
			0: '8 oct',
			4: '12 oct',
			8: '16 oct',
			12: '20 oct',
			16: '24 oct',
			20: '28 oct',
			24: '1 nov',
			29: '6 nov'
		};

		let barsSvg = '';
		for (let i = 0; i < n; i++) {
			const val = dailyWaste30[i];
			const bHeight = Math.max(2, (val / maxVal) * plotH);
			const xCenter = padding.left + i * slotW + slotW / 2;
			const barX = xCenter - barW / 2;
			const barY = padding.top + plotH - bHeight;

			let xLabel = '';
			if (labelIndices.includes(i)) {
				const lbl = labelMap[i] || dates30[i];
				xLabel = `<text x="${xCenter}" y="${height - 10}" font-size="10" fill="#94a3b8" text-anchor="middle">${lbl}</text>`;
			}

			barsSvg += `
				<rect class="dp-svg-bar" data-idx="${i}" data-date="${dates30[i]}" data-val="${val}"
					x="${barX}" y="${barY}" width="${barW}" height="${bHeight}" fill="#10b26c" rx="2"
					style="cursor: pointer; transition: opacity 0.15s ease;" />
				${xLabel}
			`;
		}

		const svgContent = `
			<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
				${gridLines}
				${avgLineSvg}
				${barsSvg}
			</svg>
		`;

		container.innerHTML = svgContent;
		setupEvolutionTooltips(container);
	}

	function setupEvolutionTooltips(container) {
		const tooltip = document.getElementById('wasteChartTooltip');
		if (!tooltip) return;

		const bars = container.querySelectorAll('.dp-svg-bar');
		bars.forEach(bar => {
			bar.addEventListener('mouseenter', function (e) {
				const date = this.getAttribute('data-date');
				const val = this.getAttribute('data-val');

				tooltip.innerHTML = `
					<div style="font-weight: 700; margin-bottom: 2px;">${date}</div>
					<div style="color: #6ee7b7;"><span style="display:inline-block;width:8px;height:8px;background:#10b26c;border-radius:2px;margin-right:4px;"></span>Merma: <strong>$${val}</strong></div>
					<div style="color: #fde68a;"><span style="display:inline-block;width:8px;height:8px;background:#f59e0b;border-radius:2px;margin-right:4px;"></span>Promedio: <strong>$416</strong></div>
				`;
				tooltip.style.display = 'block';
				this.style.opacity = '0.8';
			});

			bar.addEventListener('mousemove', function (e) {
				const rect = container.getBoundingClientRect();
				const x = e.clientX - rect.left + 12;
				const y = e.clientY - rect.top - 38;
				tooltip.style.left = `${Math.min(x, rect.width - 130)}px`;
				tooltip.style.top = `${Math.max(10, y)}px`;
			});

			bar.addEventListener('mouseleave', function () {
				tooltip.style.display = 'none';
				this.style.opacity = '1';
			});
		});
	}

	function renderWasteParetoSvg() {
		const container = document.getElementById('wasteParetoSvgChart');
		if (!container) return;

		const width = 420;
		const height = 195;
		const padding = { top: 20, right: 35, bottom: 35, left: 45 };
		const plotW = width - padding.left - padding.right;
		const plotH = height - padding.top - padding.bottom;
		const n = paretoItems.length;

		const maxCost = 5000;
		const yTicksLeft = [0, 1000, 2000, 3000, 4000, 5000];
		const yTicksRight = [0, 20, 40, 60, 80, 100];

		let gridSvg = '';
		for (let i = 0; i < yTicksLeft.length; i++) {
			const costVal = yTicksLeft[i];
			const pctVal = yTicksRight[i];
			const yPos = padding.top + plotH - (plotH * (costVal / maxCost));

			gridSvg += `
				<line x1="${padding.left}" y1="${yPos}" x2="${width - padding.right}" y2="${yPos}" stroke="#f1f5f9" stroke-width="1" />
				<text x="${padding.left - 6}" y="${yPos + 3.5}" font-size="9" fill="#94a3b8" text-anchor="end" font-family="ui-monospace, monospace">$${costVal === 0 ? '0' : (costVal / 1000) + ',000'}</text>
				<text x="${width - padding.right + 6}" y="${yPos + 3.5}" font-size="9" fill="#94a3b8" text-anchor="start" font-family="ui-monospace, monospace">${pctVal} %</text>
			`;
		}

		const slotW = plotW / n;
		const barW = slotW * 0.65;

		let barsSvg = '';
		let linePoints = [];
		let dotsSvg = '';

		for (let i = 0; i < n; i++) {
			const item = paretoItems[i];
			const bHeight = (item.cost / maxCost) * plotH;
			const xCenter = padding.left + i * slotW + slotW / 2;
			const barX = xCenter - barW / 2;
			const barY = padding.top + plotH - bHeight;

			// Coordenada línea porcentaje acumulado
			const lineY = padding.top + plotH - (plotH * (item.cum / 100));
			linePoints.push(`${xCenter},${lineY}`);

			// Barra
			barsSvg += `
				<rect x="${barX}" y="${barY}" width="${barW}" height="${bHeight}" fill="#10b26c" rx="2" />
				<text x="${xCenter}" y="${barY + bHeight - 5}" font-size="8.5" fill="#ffffff" text-anchor="middle" font-weight="700" font-family="ui-monospace, monospace">$${item.cost.toLocaleString()}</text>
				<text x="${xCenter}" y="${height - 10}" font-size="9.5" fill="#64748b" text-anchor="middle" font-weight="500">${item.name}</text>
			`;

			dotsSvg += `
				<circle cx="${xCenter}" cy="${lineY}" r="3" fill="#ffffff" stroke="#f59e0b" stroke-width="2" />
			`;
		}

		const pathD = `M ${linePoints.join(' L ')}`;

		const svgContent = `
			<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
				${gridSvg}
				${barsSvg}
				<path d="${pathD}" fill="none" stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
				${dotsSvg}
			</svg>
		`;

		container.innerHTML = svgContent;
	}

	window.switchWasteTab = function (tabName) {
		document.querySelectorAll('.ded-nav-tab').forEach(btn => {
			if (btn.getAttribute('data-waste-tab') === tabName) {
				btn.classList.add('is-active');
			} else {
				btn.classList.remove('is-active');
			}
		});

		const boardPane = document.getElementById('waste-pane-control');
		const legacyPane = document.getElementById('waste-pane-legacy');

		if (tabName === 'centro-control') {
			if (boardPane) boardPane.style.display = 'block';
			if (legacyPane) legacyPane.style.display = 'none';
		} else {
			if (boardPane) boardPane.style.display = 'none';
			if (legacyPane) legacyPane.style.display = 'block';

			// Mapear con tabs legacy existentes en waste_overview
			const map = {
				'registro': 'tab-registro-link',
				'prevencion': 'tab-prevencion-link',
				'investigaciones': 'tab-acciones-link',
				'acciones': 'tab-acciones-link',
				'recuperacion': 'tab-recuperacion-link'
			};
			const linkId = map[tabName];
			if (linkId) {
				const link = document.getElementById(linkId);
				if (link) link.click();
			}
		}

		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
	};

	function initAll() {
		renderWasteEvolutionSvg();
		renderWasteParetoSvg();
		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

})();
