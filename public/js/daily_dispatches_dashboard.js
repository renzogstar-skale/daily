/**
 * DAILY — TABLERO DE DESPACHOS Y DEVOLUCIONES
 * Lógica para gráficos SVG interactivos (Despachos vs Entregas),
 * tooltips y navegación entre pestañas.
 */

(function () {
	'use strict';

	// 30 días exactos con valores calibrados a la foto (escala 0 a 1,250)
	const dates30 = [
		'15 oct', '16 oct', '17 oct', '18 oct', '19 oct', '20 oct', '21 oct',
		'22 oct', '23 oct', '24 oct', '25 oct', '26 oct', '27 oct', '28 oct',
		'29 oct', '30 oct', '31 oct', '01 nov', '02 nov', '03 nov', '04 nov',
		'05 nov', '06 nov', '07 nov', '08 nov', '09 nov', '10 nov', '11 nov',
		'12 nov', '13 nov'
	];

	// Despachos (barras verdes)
	const despachos30 = [
		660, 675, 690, 720, 700, 740, 770,
		750, 790, 810, 780, 820, 840, 830,
		850, 870, 900, 880, 920, 940, 950,
		980, 1000, 1030, 1050, 930, 880, 850,
		840, 820
	];

	// Entregas (línea ámbar)
	const entregas30 = [
		690, 710, 730, 760, 745, 785, 815,
		795, 835, 855, 825, 865, 885, 875,
		895, 915, 945, 925, 965, 985, 995,
		1025, 1045, 1065, 1085, 975, 925, 905,
		895, 875
	];

	function renderEvolutionSvg() {
		const container = document.getElementById('dpEvolutionSvgChart');
		if (!container) return;

		const n = dates30.length;
		const maxVal = 1250; // Escala fija exacta a la captura (0 a 1,250)
		const width = 800;
		const height = 230;
		const padding = { top: 15, right: 15, bottom: 35, left: 45 };
		const plotW = width - padding.left - padding.right;
		const plotH = height - padding.top - padding.bottom;

		const slotW = plotW / n;
		const barW = Math.max(5, Math.min(14, slotW * 0.52));

		// Líneas de cuadrícula fijas: 0, 250, 500, 750, 1,000, 1,250
		const yTicks = [0, 250, 500, 750, 1000, 1250];
		let gridLines = '';
		yTicks.forEach(val => {
			const yPos = padding.top + plotH - (plotH * (val / maxVal));
			const labelStr = val === 1000 ? '1,000' : (val === 1250 ? '1,250' : val.toString());
			gridLines += `
				<line x1="${padding.left}" y1="${yPos}" x2="${width - padding.right}" y2="${yPos}" stroke="#f1f5f9" stroke-width="1" />
				<text x="${padding.left - 8}" y="${yPos + 4}" font-size="10" fill="#94a3b8" text-anchor="end" font-family="ui-monospace, monospace">${labelStr}</text>
			`;
		});

		// Índices con etiqueta en el eje X: 15 oct, 18 oct, 21 oct, 24 oct, 27 oct, 30 oct, 2 nov, 5 nov, 8 nov, 11 nov, 13 nov
		const labelIndices = [0, 3, 6, 9, 12, 15, 18, 21, 24, 27, 29];
		const labelMap = {
			0: '15 oct',
			3: '18 oct',
			6: '21 oct',
			9: '24 oct',
			12: '27 oct',
			15: '30 oct',
			18: '2 nov',
			21: '5 nov',
			24: '8 nov',
			27: '11 nov',
			29: '13 nov'
		};

		let barsSvg = '';
		let linePoints = [];
		let dotsSvg = '';

		for (let i = 0; i < n; i++) {
			const dVal = despachos30[i];
			const eVal = entregas30[i];
			const bHeight = Math.max(2, (dVal / maxVal) * plotH);
			const xCenter = padding.left + i * slotW + slotW / 2;
			const barX = xCenter - barW / 2;
			const barY = padding.top + plotH - bHeight;

			// Coordenada de la línea de entrega
			const lineY = padding.top + plotH - ((eVal / maxVal) * plotH);
			linePoints.push(`${xCenter},${lineY}`);

			// Eje X labels
			let xLabel = '';
			if (labelIndices.includes(i)) {
				const lbl = labelMap[i] || dates30[i];
				xLabel = `<text x="${xCenter}" y="${height - 10}" font-size="10" fill="#94a3b8" text-anchor="middle">${lbl}</text>`;
			}

			barsSvg += `
				<rect class="dp-svg-bar" data-idx="${i}" data-date="${dates30[i]}" data-desp="${dVal}" data-entr="${eVal}"
					x="${barX}" y="${barY}" width="${barW}" height="${bHeight}" fill="#10b26c" rx="2"
					style="cursor: pointer; transition: opacity 0.15s ease;" />
				${xLabel}
			`;

			// Puntos de la línea (ámbar con borde)
			if (labelIndices.includes(i) || i % 2 === 0) {
				dotsSvg += `
					<circle cx="${xCenter}" cy="${lineY}" r="3" fill="#ffffff" stroke="#f59e0b" stroke-width="2" class="dp-svg-dot" />
				`;
			}
		}

		const pathD = `M ${linePoints.join(' L ')}`;

		const svgContent = `
			<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
				${gridLines}
				${barsSvg}
				<path d="${pathD}" fill="none" stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
				${dotsSvg}
			</svg>
		`;

		container.innerHTML = svgContent;
		setupChartTooltips(container);
	}

	function setupChartTooltips(container) {
		const tooltip = document.getElementById('dpChartTooltip');
		if (!tooltip) return;

		const bars = container.querySelectorAll('.dp-svg-bar');
		bars.forEach(bar => {
			bar.addEventListener('mouseenter', function (e) {
				const date = this.getAttribute('data-date');
				const desp = this.getAttribute('data-desp');
				const entr = this.getAttribute('data-entr');

				tooltip.innerHTML = `
					<div style="font-weight: 700; margin-bottom: 2px;">${date}</div>
					<div style="color: #6ee7b7;"><span style="display:inline-block;width:8px;height:8px;background:#10b26c;border-radius:2px;margin-right:4px;"></span>Despachos: <strong>${desp}</strong></div>
					<div style="color: #fde68a;"><span style="display:inline-block;width:8px;height:8px;background:#f59e0b;border-radius:2px;margin-right:4px;"></span>Entregas: <strong>${entr}</strong></div>
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

	window.switchDispatchTab = function (tabName) {
		document.querySelectorAll('.ded-nav-tab').forEach(btn => {
			if (btn.getAttribute('data-dp-tab') === tabName) {
				btn.classList.add('is-active');
			} else {
				btn.classList.remove('is-active');
			}
		});

		const tableroPane = document.getElementById('dp-pane-tablero');
		const operationalPane = document.getElementById('dp-pane-operational');

		if (tabName === 'tablero') {
			if (tableroPane) tableroPane.style.display = 'block';
			if (operationalPane) operationalPane.style.display = 'none';
		} else {
			if (tableroPane) tableroPane.style.display = 'none';
			if (operationalPane) operationalPane.style.display = 'block';

			if (tabName === 'pedidos') {
				const link = document.getElementById('tab-pedidos-link');
				if (link) link.click();
			}
		}

		if (window.lucide) {
			window.lucide.createIcons();
		}
	};

	document.addEventListener('DOMContentLoaded', function () {
		renderEvolutionSvg();
		if (window.lucide) {
			window.lucide.createIcons();
		}
	});

})();
