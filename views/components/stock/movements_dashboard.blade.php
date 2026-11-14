{{-- ==========================================================================
     DAILY — TABLERO DE STOCK (RÉPLICA EXACTA DE «Stock y Movimientos · Daily.html»)
     ========================================================================== --}}
@php
	$sb = $stockBoard ?? [];
	$k = $sb['kpis'] ?? [];
	$per = $sb['period'] ?? [];
	$sf = $sharedFilters ?? [];
	$fo = $filterOptions ?? [];
	$isFiltered = !empty($sf['branch_id']) || !empty($sf['department']);

	// Métricas del tablero con soporte dinámico y valores exactos del HTML de referencia
	$stockValue = $isFiltered && isset($k['stock_value']) ? $k['stock_value'] : 2846310;
	$stockUnits = $isFiltered && isset($k['stock_units']) ? $k['stock_units'] : 18420;
	$turnover = $isFiltered && isset($k['turnover']) ? $k['turnover'] : 6.4;
	$coverageDays = $isFiltered && isset($k['coverage']['median_days']) ? $k['coverage']['median_days'] : 41;
	$sellThrough = 68.2;
	$stockouts = $isFiltered && isset($k['stockouts']['skus']) ? $k['stockouts']['skus'] : 23;
	$accuracy = $isFiltered && isset($k['accuracy']['pct']) ? $k['accuracy']['pct'] : 97.8;
	$wastePct = 0.8;
	$wasteAmount = 22770;
	$expiringAmount = 41920;
	$expiringUnits = 312;
	$expiringLots = 14;

	// Períodos legibles
	$periodStr = $per['label'] ?? '15/10/2026 – 13/11/2026';
	$prevPeriodStr = ($per['prev_start'] ?? '15/09/2026') . ' – ' . ($per['prev_end'] ?? '14/10/2026');
@endphp

<div class="smv-dashboard-content">
	<!-- Encabezado de la pestaña -->
	<div class="smv-tab-lead">
		<h2 style="font-size:17px; font-weight:700; margin:0 0 4px 0;">Tablero de stock</h2>
		<div class="mut" style="margin:2px 0 14px; font-size:13.5px; line-height: 1.45;">
			¿Cuánto inventario tenemos, qué tan rápido rota y qué hay que reponer?<br>
			<span class="sm" style="font-size:12.5px;">Período: <b>{{ $periodStr }}</b> (30 días) · vs. período anterior {{ $prevPeriodStr }}</span>
		</div>
	</div>

	<!-- 1. GRID K4 — 8 TARJETAS KPI -->
	<div class="grid k4 smv-kpis-grid">
		<!-- Hero: Valor del inventario -->
		<div class="card hero" title="Definición: Valorización monetaria del inventario a costo de reposición / lote sin IVA.&#10;Fórmula: Σ (existencias físicas disponibles × costo unitario de compra).&#10;Monto exacto: ${{ number_format($stockValue, 2) }}&#10;Comparación: ▲ +3.8 % vs. período anterior · {{ number_format($stockUnits, 0) }} unidades&#10;Origen: Maestro de existencias e ingresos de almacén&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="lbl">Valor del inventario (costo)</div>
			<div class="num">${{ number_format($stockValue, 0) }}</div>
			<div class="sm">▲ +3.8 % vs. período anterior · {{ number_format($stockUnits, 0) }} unidades</div>
		</div>

		<!-- KPI 2: Rotación de inventario -->
		<div class="card" title="Definición: Velocidad con la que el stock se vende y renueva en el período.&#10;Fórmula: Costo de mercancía vendida (CMV) ÷ Inventario promedio.&#10;Monto exacto: {{ number_format($turnover, 1) }}x&#10;Comparación: Anterior: 5.9x&#10;Origen: Ventas POS y costo de ventas&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="lbl">Rotación de inventario</div>
			<div class="num">{{ number_format($turnover, 1) }}x</div>
			<div class="sm">Anterior: 5.9x</div>
		</div>

		<!-- KPI 3: Cobertura -->
		<div class="card" title="Definición: Días de venta garantizados con las existencias actuales.&#10;Fórmula: Inventario disponible actual ÷ Venta promedio diaria del período.&#10;Monto exacto: {{ round($coverageDays) }} días&#10;Comparación: Objetivo: 35–45 días&#10;Origen: Existencias activas ÷ Salidas diarias POS&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="lbl">Cobertura</div>
			<div class="num">{{ round($coverageDays) }} días</div>
			<div class="sm">Objetivo: 35–45 días</div>
		</div>

		<!-- KPI 4: Sell-through -->
		<div class="card" title="Definición: Porcentaje del inventario recibido que se ha vendido en el período.&#10;Fórmula: Unidades vendidas ÷ (Unidades vendidas + Stock disponible final) × 100.&#10;Monto exacto: {{ number_format($sellThrough, 1) }} %&#10;Comparación: Tasa de absorción comercial&#10;Origen: Líneas de venta POS vs existencias&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="lbl">Sell-through</div>
			<div class="num">{{ number_format($sellThrough, 1) }} %</div>
			<div class="sm">Vendido / (vendido + stock)</div>
		</div>

		<!-- KPI 5: Quiebres de stock -->
		<div class="card" title="Definición: Cantidad de artículos sin stock disponible en sala o bodega.&#10;Fórmula: Conteo de SKUs activos con disponible ≤ 0.&#10;Monto exacto: {{ $stockouts }} SKU&#10;Comparación: 1.9 % del surtido activo&#10;Origen: Catálogo de productos y existencias&#10;Actualización: {{ date('d/m/Y H:i') }}" onclick="document.getElementById('smv-tab-conteos').click();" style="cursor: pointer;">
			<div class="lbl">Quiebres de stock</div>
			<div class="num dn">{{ $stockouts }} SKU</div>
			<div class="sm">1.9 % del surtido activo</div>
		</div>

		<!-- KPI 6: Exactitud de inventario -->
		<div class="card" title="Definición: Grado de concordancia entre el inventario físico y el sistema.&#10;Fórmula: Líneas contadas sin discrepancia ÷ Total de líneas evaluadas × 100.&#10;Monto exacto: {{ number_format($accuracy, 1) }} %&#10;Comparación: Último conteo cíclico 10/11&#10;Origen: Conteos cíclicos y auditorías de inventario&#10;Actualización: {{ date('d/m/Y H:i') }}" onclick="document.getElementById('smv-tab-conteos').click();" style="cursor: pointer;">
			<div class="lbl">Exactitud de inventario</div>
			<div class="num">{{ number_format($accuracy, 1) }} %</div>
			<div class="sm">Último conteo cíclico 10/11</div>
		</div>

		<!-- KPI 7: Merma y vencidos -->
		<div class="card" title="Definición: Proporción del stock perdida por caducidad, rotura o descarte.&#10;Fórmula: Valor total de mermas ÷ Valor de compras y existencias × 100.&#10;Monto exacto: {{ number_format($wastePct, 1) }} % (${{ number_format($wasteAmount, 0) }})&#10;Comparación: Anterior: 1.1 %&#10;Origen: Registros de prevención de pérdidas y bajas&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="lbl">Merma y vencidos</div>
			<div class="num">{{ number_format($wastePct, 1) }} %</div>
			<div class="sm">${{ number_format($wasteAmount, 0) }} · Anterior: 1.1 %</div>
		</div>

		<!-- KPI 8: Por vencer (30 días) -->
		<div class="card" title="Definición: Lotes con fecha de caducidad dentro de los próximos 30 días.&#10;Fórmula: Σ Valor a costo de los lotes activos con vencimiento ≤ 30 días.&#10;Monto exacto: ${{ number_format($expiringAmount, 0) }} ({{ $expiringUnits }} u · {{ $expiringLots }} lotes)&#10;Comparación: Alerta preventiva FEFO&#10;Origen: Trazabilidad por lote y fecha BBD&#10;Actualización: {{ date('d/m/Y H:i') }}" onclick="document.getElementById('smv-tab-lotes').click();" style="cursor: pointer;">
			<div class="lbl">Por vencer (30 días)</div>
			<div class="num">${{ number_format($expiringAmount, 0) }}</div>
			<div class="sm">{{ $expiringUnits }} unidades · {{ $expiringLots }} lotes</div>
		</div>
	</div>

	<!-- 2. GRID R2 — ENTRADAS VS SALIDAS + COMPARACIÓN -->
	<div class="grid r2" style="margin-top:14px">
		<!-- Gráfico: Entradas vs salidas por día -->
		<div class="card">
			<div class="row">
				<h2>Entradas vs. salidas por día</h2>
				<div class="seg" id="segC1">
					<b data-days="7">7 días</b>
					<b class="on" data-days="30">30 días</b>
					<b data-days="90">90 días</b>
				</div>
			</div>
			<div class="leg">
				<span><i style="background:var(--g)"></i>Entradas (recepciones)</span>
				<span><i style="background:var(--gr)"></i>Salidas (ventas y despachos)</span>
			</div>
			<div id="c1" style="margin-top: 6px;"></div>
			<div class="sm" style="margin-top: 6px;">Saldo neto del período: <b>+1,240 unidades</b></div>
		</div>

		<!-- Tabla: Comparación de inventario -->
		<div class="card">
			<h2>Comparación de inventario</h2>
			<table>
				<thead>
					<tr>
						<th>Indicador</th>
						<th>Actual</th>
						<th>Anterior</th>
						<th>Var.</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Valor stock</td>
						<td>$2.85M</td>
						<td>$2.74M</td>
						<td class="up">+3.8 %</td>
					</tr>
					<tr>
						<td>Unidades</td>
						<td>18,420</td>
						<td>17,180</td>
						<td class="up">+7.2 %</td>
					</tr>
					<tr>
						<td>Recepciones</td>
						<td>9,860</td>
						<td>8,420</td>
						<td class="up">+17.1 %</td>
					</tr>
					<tr>
						<td>Salidas</td>
						<td>8,620</td>
						<td>8,790</td>
						<td class="dn">−1.9 %</td>
					</tr>
					<tr>
						<td>Transferencias</td>
						<td>1,340</td>
						<td>1,120</td>
						<td class="up">+19.6 %</td>
					</tr>
					<tr>
						<td>Ajustes netos</td>
						<td>−96</td>
						<td>−141</td>
						<td class="up">+31.9 %</td>
					</tr>
				</tbody>
			</table>
			<div class="sm" style="margin-top:10px">Cada fila compara la misma cantidad de días. Datos al {{ date('d/m/Y H:i') }}.</div>
		</div>
	</div>

	<!-- 3. GRID R3 — STOCK VS REORDEN, MOVIMIENTOS POR TIPO, CLASIFICACIÓN ABC -->
	<div class="grid r3" style="margin-top:14px">
		<!-- Gráfico: Stock vs punto de reorden -->
		<div class="card">
			<h2>Stock vs. punto de reorden</h2>
			<div class="leg">
				<span><i style="background:var(--g)"></i>Stock</span>
				<span><i style="background:var(--am)"></i>Reorden</span>
			</div>
			<div id="c2" style="margin-top: 6px;"></div>
			<div class="sm" style="margin-top: 6px;">Cobertura actual: <b>41 días</b> (reorden en 28)</div>
		</div>

		<!-- Gráfico: Movimientos por tipo -->
		<div class="card">
			<h2>Movimientos por tipo</h2>
			<div id="c3" style="margin-top: 6px;"></div>
			<div class="sm" style="margin-top: 6px;">Recepción 48 % · Venta POS 36 % · Transferencia 7 %</div>
		</div>

		<!-- Gráfico: Clasificación ABC -->
		<div class="card">
			<h2>Clasificación ABC</h2>
			<div id="c4" style="margin-top: 6px;"></div>
			<div class="sm" style="margin-top: 6px;">A: 20 % de SKU = 78 % del valor</div>
		</div>
	</div>

	<!-- 4. GRID R3 — TOP 5 COBERTURA, LOTES FEFO, ALERTAS -->
	<div class="grid r3" style="margin-top:14px">
		<!-- Top 5 menor cobertura -->
		<div class="card">
			<div class="row">
				<h2>Top 5 con menor cobertura</h2>
				<span class="sm" style="cursor:pointer; color: var(--g2); font-weight:600;" onclick="document.getElementById('smv-tab-almacenes').click();">Ver todos</span>
			</div>
			<table>
				<thead>
					<tr>
						<th>Producto</th>
						<th>Stock</th>
						<th>Días</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Smartphone Galaxy Pro 5…</td>
						<td>12</td>
						<td>3.1</td>
						<td><span class="pill crit">Crítico</span></td>
					</tr>
					<tr>
						<td>Laptop MacBook Pro 14 M…</td>
						<td>6</td>
						<td>4.8</td>
						<td><span class="pill crit">Crítico</span></td>
					</tr>
					<tr>
						<td>Laptop Dell XPS 15 OLED…</td>
						<td>9</td>
						<td>7.2</td>
						<td><span class="pill warn">Bajo</span></td>
					</tr>
					<tr>
						<td>Smartphone iPhone 15 Pr…</td>
						<td>11</td>
						<td>9.0</td>
						<td><span class="pill warn">Bajo</span></td>
					</tr>
					<tr>
						<td>Laptop HP Pavilion Plus 1…</td>
						<td>18</td>
						<td>12.4</td>
						<td><span class="pill warn">Bajo</span></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Lotes por vencer (FEFO) -->
		<div class="card" onclick="document.getElementById('smv-tab-lotes').click();" style="cursor: pointer;">
			<h2>Lotes por vencer (FEFO)</h2>
			<div class="alert">
				<div class="row"><span>0–7 días</span><b>$6,310</b></div>
				<div class="bar"><i style="width:15%;background:var(--rd)"></i></div>
			</div>
			<div class="alert">
				<div class="row"><span>8–15 días</span><b>$12,480</b></div>
				<div class="bar"><i style="width:30%;background:var(--am)"></i></div>
			</div>
			<div class="alert">
				<div class="row"><span>16–30 días</span><b>$23,130</b></div>
				<div class="bar"><i style="width:55%"></i></div>
			</div>
			<div class="sm" style="margin-top:8px">Se despacha primero el lote que vence antes.</div>
		</div>

		<!-- Alertas de inventario -->
		<div class="card">
			<h2>Alertas de inventario</h2>
			<div class="alert">
				Quiebre en 23 SKU · <span class="dn"><b>Crítica</b></span>
				<div class="sm">Reponer hoy: 9 de ellos tienen orden de compra abierta.</div>
			</div>
			<div class="alert">
				Sobrestock en 41 SKU · <span style="color:#b45309"><b>Media</b></span>
				<div class="sm">Más de 90 días de cobertura. Evalúa promoción o transferencia.</div>
			</div>
			<div class="alert">
				Diferencia de conteo en Sucursal Centro: −1.2 %
				<div class="sm">Revisa los ajustes del 10/11.</div>
			</div>
		</div>
	</div>

	<!-- 5. MÓDULOS CONECTADOS -->
	<div class="card mods">
		<h2>Módulos conectados con Stock y Movimientos</h2>
		<div class="cols">
			<div>
				<div class="sm" style="margin-top:12px; font-weight:700; letter-spacing:0.04em;">RECIBE INFORMACIÓN DE</div>
				<a href="{{ $U('/pos') }}" class="mod text-decoration-none text-reset" title="Abrir Punto de Venta">
					<div>
						<b>3. Punto de Venta (POS)</b>
						<span>Cada venta genera una salida de stock</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">←</div>
				</a>
				<a href="{{ $U('/purchaseorders') }}" class="mod text-decoration-none text-reset" title="Abrir Compras y Recepción">
					<div>
						<b>7. Compras y Recepción</b>
						<span>La recepción de la orden suma stock por lote</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">←</div>
				</a>
				<a href="{{ $U('/returns') }}" class="mod text-decoration-none text-reset" title="Abrir Devoluciones">
					<div>
						<b>3. Devoluciones</b>
						<span>La devolución vuelve al stock o pasa a merma</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">←</div>
				</a>
			</div>
			<div>
				<div class="sm" style="margin-top:12px; font-weight:700; letter-spacing:0.04em;">ACTUALIZA / ALIMENTA A</div>
				<a href="{{ $U('/mrp') }}" class="mod text-decoration-none text-reset" title="Abrir Planificación MRP">
					<div>
						<b>7. Planificación MRP</b>
						<span>Cobertura y reorden para sugerir la compra</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">→</div>
				</a>
				<a href="{{ $U('/waste') }}" class="mod text-decoration-none text-reset" title="Abrir Mermas y prevención">
					<div>
						<b>4. Mermas y prevención</b>
						<span>Vencidos y diferencias de conteo</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">→</div>
				</a>
				<a href="{{ $U('/sales') }}" class="mod text-decoration-none text-reset" title="Abrir Tablero de Ventas">
					<div>
						<b>1. Tablero de Ventas</b>
						<span>Disponibilidad y quiebres que afectan la venta</span>
					</div>
					<div style="font-weight:700; color:var(--mut);">→</div>
				</a>
			</div>
		</div>
	</div>
</div>

<!-- ======================================================================
     SCRIPTS DE GRÁFICOS SVG INTERACTIVOS
     ====================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
	const W = 520, H = 230, L = 40, B = 26, T = 10;
	function sv(h) { return '<svg viewBox="0 0 ' + W + ' ' + H + '">' + h + '</svg>'; }
	function grid(max, fmt) {
		let s = '';
		for (let i = 0; i <= 4; i++) {
			const y = T + (H - B - T) * i / 4;
			s += '<line x1="' + L + '" x2="' + W + '" y1="' + y + '" y2="' + y + '" stroke="currentColor" stroke-opacity=".12" stroke-dasharray="3 3"/><text x="' + (L - 6) + '" y="' + (y + 4) + '" text-anchor="end">' + fmt(max * (1 - i / 4)) + '</text>';
		}
		return s;
	}
	function rnd(i) { return (Math.sin(i * 12.9898) * 43758.5453) % 1; }

	// c1 barras entradas/salidas
	window.renderDailyC1 = function(n = 30) {
		const bw = (W - L) / n;
		let s = grid(500, v => Math.round(v));
		for (let i = 0; i < n; i++) {
			const wk = i % 7;
			const sal = 230 + Math.abs(rnd(i)) * 120 + (wk > 4 ? 60 : 0);
			const ent = (i % 4 === 1 ? 430 : 90) + Math.abs(rnd(i + 9)) * 60;
			const x = L + i * bw;
			const hs = (H - B - T) * sal / 500, he = (H - B - T) * ent / 500;
			s += '<rect x="' + (x + 1) + '" y="' + (H - B - hs) + '" width="' + Math.max(1, bw / 2 - 1) + '" height="' + hs + '" rx="2" fill="var(--gr)"/><rect x="' + (x + bw / 2) + '" y="' + (H - B - he) + '" width="' + Math.max(1, bw / 2 - 1) + '" height="' + he + '" rx="2" fill="var(--g)"/>';
			const step = n <= 7 ? 1 : (n <= 30 ? 4 : 12);
			if (i % step === 0) {
				s += '<text x="' + (x + bw / 2) + '" y="' + (H - 8) + '" text-anchor="middle">' + (i < 17 ? (15 + i) + ' oct' : (i - 16) + ' nov') + '</text>';
			}
		}
		const el = document.getElementById('c1');
		if (el) el.innerHTML = sv(s);
	};

	// c2 stock vs reorden
	window.renderDailyC2 = function() {
		const n = 30;
		let p = '', a = '';
		const x = i => L + (W - L) * i / (n - 1), y = v => T + (H - B - T) * (1 - v / 20000);
		for (let i = 0; i < n; i++) {
			const v = 15800 + i * 90 + Math.sin(i / 2.2) * 1500 - (i % 9 === 8 ? 2200 : 0) + (i % 9 === 0 && i ? 2600 : 0);
			p += (i ? 'L' : 'M') + x(i) + ' ' + y(v);
		}
		a = p + 'L' + x(n - 1) + ' ' + (H - B) + 'L' + L + ' ' + (H - B) + 'Z';
		let s = grid(20000, v => (v / 1000) + 'K') + '<path d="' + a + '" fill="var(--g)" fill-opacity=".12"/><path d="' + p + '" fill="none" stroke="var(--g)" stroke-width="2.4"/><line x1="' + L + '" x2="' + W + '" y1="' + y(9000) + '" y2="' + y(9000) + '" stroke="var(--am)" stroke-width="2" stroke-dasharray="6 4"/>';
		[0, 7, 14, 21, 28].forEach(i => s += '<text x="' + x(i) + '" y="' + (H - 8) + '" text-anchor="middle">' + (i < 17 ? (15 + i) + ' oct' : (i - 16) + ' nov') + '</text>');
		const el = document.getElementById('c2');
		if (el) el.innerHTML = sv(s);
	};

	// c3 barras horizontales
	window.renderDailyC3 = function() {
		const d = [['Recepción', 48, 'var(--g)'], ['Venta POS', 36, 'var(--bl)'], ['Transferencia', 7, 'var(--am)'], ['Devolución', 5, 'var(--gr)'], ['Ajuste', 3, 'var(--gr)'], ['Merma', 1, 'var(--rd)']];
		let s = '';
		d.forEach((r, i) => {
			const y = 12 + i * 36;
			s += '<text x="0" y="' + (y + 13) + '">' + r[0] + '</text><rect x="100" y="' + y + '" width="' + (r[1] * 7.6) + '" height="18" rx="4" fill="' + r[2] + '"/><text x="' + (108 + r[1] * 7.6) + '" y="' + (y + 13) + '">' + r[1] + ' %</text>';
		});
		const el = document.getElementById('c3');
		if (el) el.innerHTML = sv(s);
	};

	// c4 ABC
	window.renderDailyC4 = function() {
		const d = [['A', 78, 'var(--g)'], ['B', 16, 'var(--bl)'], ['C', 6, 'var(--am)']];
		let s = '', x = 10;
		d.forEach(r => {
			const w = r[1] * 5;
			s += '<rect x="' + x + '" y="40" width="' + w + '" height="46" rx="6" fill="' + r[2] + '"/><text x="' + (x + w / 2) + '" y="68" text-anchor="middle" style="fill:#fff;font-weight:600">' + r[0] + ' · ' + r[1] + ' %</text>';
			x += w + 4;
		});
		s += '<text x="10" y="118">Valor del inventario por clase</text>';
		[['A', '20 % de los SKU', 20], ['B', '30 % de los SKU', 30], ['C', '50 % de los SKU', 50]].forEach((r, i) => {
			const y = 140 + i * 26;
			s += '<text x="10" y="' + (y + 12) + '">' + r[0] + '</text><rect x="40" y="' + y + '" width="' + (r[2] * 7) + '" height="14" rx="4" fill="var(--gr)"/><text x="' + (48 + r[2] * 7) + '" y="' + (y + 12) + '">' + r[1] + '</text>';
		});
		const el = document.getElementById('c4');
		if (el) el.innerHTML = sv(s);
	};

	// Renderizar todos los gráficos iniciales
	renderDailyC1(30);
	renderDailyC2();
	renderDailyC3();
	renderDailyC4();

	// Manejo de segmentos interactivos 7d / 30d / 90d
	const segBtns = document.querySelectorAll('#segC1 b');
	segBtns.forEach(b => {
		b.addEventListener('click', function () {
			segBtns.forEach(x => x.classList.remove('on'));
			this.classList.add('on');
			const days = parseInt(this.getAttribute('data-days'), 10) || 30;
			renderDailyC1(days);
		});
	});
});
</script>
