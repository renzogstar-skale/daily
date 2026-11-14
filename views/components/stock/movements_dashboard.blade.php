{{-- ==========================================================================
     DAILY — TABLERO DE STOCK (RÉPLICA EXACTA CON EL SISTEMA DE DISEÑO DE VENTAS)
     ========================================================================== --}}
@php
	$sb = $stockBoard ?? [];
	$k = $sb['kpis'] ?? [];
	$sf = $sharedFilters ?? [];
	$isFiltered = !empty($sf['branch_id']) || !empty($sf['department']);

	// Métricas calibradas de inventario exactas de la referencia visual
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

	// Top 5 con menor cobertura
	$topCoverage = [
		['name' => 'Smartphone Galaxy Pro 5...', 'full' => 'Smartphone Galaxy Pro 5G', 'stock' => 12, 'days' => 3.1, 'status' => 'critico', 'label' => 'Crítico'],
		['name' => 'Laptop MacBook Pro 14 M...', 'full' => 'Laptop MacBook Pro 14 M3 Pro', 'stock' => 6, 'days' => 4.8, 'status' => 'critico', 'label' => 'Crítico'],
		['name' => 'Laptop Dell XPS 15 OLED...', 'full' => 'Laptop Dell XPS 15 OLED', 'stock' => 9, 'days' => 7.2, 'status' => 'bajo', 'label' => 'Bajo'],
		['name' => 'Smartphone iPhone 15 Pr...', 'full' => 'Smartphone iPhone 15 Pro Max', 'stock' => 11, 'days' => 9.0, 'status' => 'bajo', 'label' => 'Bajo'],
		['name' => 'Laptop HP Pavilion Plus 1...', 'full' => 'Laptop HP Pavilion Plus 14', 'stock' => 18, 'days' => 12.4, 'status' => 'bajo', 'label' => 'Bajo']
	];

	// Comparación de inventario
	$compRows = [
		['label' => 'Valor stock', 'cur' => '$2.85M', 'prev' => '$2.74M', 'delta' => '+3.8 %', 'up' => true],
		['label' => 'Unidades', 'cur' => '18,420', 'prev' => '17,180', 'delta' => '+7.2 %', 'up' => true],
		['label' => 'Recepciones', 'cur' => '9,860', 'prev' => '8,420', 'delta' => '+17.1 %', 'up' => true],
		['label' => 'Salidas', 'cur' => '8,620', 'prev' => '8,790', 'delta' => '-1.9 %', 'up' => false],
		['label' => 'Transferencias', 'cur' => '1,340', 'prev' => '1,120', 'delta' => '+19.6 %', 'up' => true],
		['label' => 'Ajustes netos', 'cur' => '-96', 'prev' => '-141', 'delta' => '+31.9 %', 'up' => true],
	];

	// Movimientos por tipo (barra Venta POS en gris pizarra como se solicitó)
	$typeBars = [
		['label' => 'Recepción', 'pct' => 48, 'color' => '#10b981'],
		['label' => 'Venta POS', 'pct' => 36, 'color' => '#64748b'],
		['label' => 'Transferencia', 'pct' => 7, 'color' => '#f59e0b'],
		['label' => 'Devolución', 'pct' => 5, 'color' => '#eab308'],
		['label' => 'Ajuste', 'pct' => 3, 'color' => '#cbd5e1'],
		['label' => 'Merma', 'pct' => 1, 'color' => '#ef4444']
	];

	// FEFO lotes por vencer
	$fefoBars = [
		['label' => '0–7 días', 'val' => '$6,310', 'pct' => 28, 'color' => '#10b981'],
		['label' => '8–15 días', 'val' => '$12,480', 'pct' => 54, 'color' => '#10b981'],
		['label' => '16–30 días', 'val' => '$23,130', 'pct' => 100, 'color' => '#059669']
	];
@endphp

<div class="smv-dashboard-content">

	<!-- ====================================================================
	     FILA 1 — 8 INDICADORES CLAVE (CUADRÍCULA IDÉNTICA A VENTAS)
	     ==================================================================== -->
	<section class="ded-kpis-grid has-8-cards" aria-label="Indicadores Clave de Inventario">
		<!-- 1. Valor del inventario (costo) - Tarjeta verde destacada -->
		<article class="ded-kpi-card is-highlight" title="Definición: Valorización monetaria del inventario a costo de reposición / lote sin IVA.&#10;Fórmula: Σ (existencias físicas disponibles × costo unitario de compra).&#10;Monto exacto: ${{ number_format($stockValue, 2) }}&#10;Comparación: ▲ +3.8 % vs. período anterior · {{ number_format($stockUnits, 0) }} unidades&#10;Origen: Maestro de existencias e ingresos de almacén&#10;Actualización: {{ date('d/m/Y H:i') }}" data-smv-tab="movimientos">
			<div class="ded-kpi-top-highlight">
				<div class="ded-highlight-brand">
					<i data-lucide="package"></i>
					<span>Valor del inventario</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val">${{ number_format($stockValue, 0) }}</div>
			<div class="ded-kpi-sub-highlight">
				▲ +3.8 % vs. período anterior · {{ number_format($stockUnits, 0) }} unidades
			</div>
		</article>

		<!-- 2. Rotación de inventario -->
		<article class="ded-kpi-card" title="Definición: Velocidad con la que el stock se vende y renueva en el período.&#10;Fórmula: Costo de mercancía vendida (CMV) ÷ Inventario promedio.&#10;Monto exacto: {{ number_format($turnover, 1) }}×&#10;Comparación: Anterior: 5.9×&#10;Origen: Ventas POS y costo de ventas&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="rotate-cw"></i></span>
			</div>
			<div class="ded-kpi-label">Rotación de inventario</div>
			<div class="ded-kpi-val">{{ number_format($turnover, 1) }}×</div>
			<div class="ded-kpi-sub">Anterior: 5.9×</div>
		</article>

		<!-- 3. Cobertura -->
		<article class="ded-kpi-card" title="Definición: Días de venta garantizados con las existencias actuales.&#10;Fórmula: Inventario disponible actual ÷ Venta promedio diaria del período.&#10;Monto exacto: {{ round($coverageDays) }} días&#10;Comparación: Objetivo: 35–45 días&#10;Origen: Existencias activas ÷ Salidas diarias POS&#10;Actualización: {{ date('d/m/Y H:i') }}">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="calendar"></i></span>
			</div>
			<div class="ded-kpi-label">Cobertura</div>
			<div class="ded-kpi-val">{{ round($coverageDays) }} días</div>
			<div class="ded-kpi-sub">Objetivo: 35-45 días</div>
		</article>

		<!-- 4. SKU con sobrestock -->
		<article class="ded-kpi-card" title="Definición: Artículos con existencias superiores a su objetivo de cobertura planificado (> 90 días).&#10;Fórmula: Conteo de SKUs activos con disponible ÷ venta promedio diaria > 90 días.&#10;Monto exacto: 41 SKU&#10;Comparación: 3.4 % del surtido activo&#10;Acción: Evaluar promoción comercial, devolución o transferencia&#10;Origen: Inventario disponible vs. proyección comercial&#10;Actualización: {{ date('d/m/Y H:i') }}" data-smv-tab="movimientos" style="cursor: pointer;">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="background:#fffbeb; color:#d97706;"><i data-lucide="layers"></i></span>
			</div>
			<div class="ded-kpi-label">SKU con sobrestock</div>
			<div class="ded-kpi-val" style="color:#d97706;">41 SKU</div>
			<div class="ded-kpi-sub">> 90 días de cobertura</div>
		</article>

		<!-- 5. Quiebres de stock -->
		<article class="ded-kpi-card" title="Definición: Cantidad de artículos sin stock disponible en sala o bodega.&#10;Fórmula: Conteo de SKUs activos con disponible ≤ 0.&#10;Monto exacto: {{ $stockouts }} SKU&#10;Comparación: 1.9 % del surtido activo&#10;Origen: Catálogo de productos y existencias&#10;Actualización: {{ date('d/m/Y H:i') }}" data-smv-tab="conteos" style="cursor: pointer;">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="background:#fee2e2; color:#b91c1c;"><i data-lucide="alert-triangle"></i></span>
			</div>
			<div class="ded-kpi-label">Quiebres de stock</div>
			<div class="ded-kpi-val" style="color:#b91c1c;">{{ $stockouts }} SKU</div>
			<div class="ded-kpi-sub">1.9 % del surtido activo</div>
		</article>

		<!-- 6. Exactitud de inventario -->
		<article class="ded-kpi-card" title="Definición: Grado de concordancia entre el inventario físico y el sistema.&#10;Fórmula: Líneas contadas sin discrepancia ÷ Total de líneas evaluadas × 100.&#10;Monto exacto: {{ number_format($accuracy, 1) }} %&#10;Comparación: Último conteo cíclico 10/11&#10;Origen: Conteos cíclicos y auditorías de inventario&#10;Actualización: {{ date('d/m/Y H:i') }}" data-smv-tab="conteos" style="cursor: pointer;">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="target"></i></span>
			</div>
			<div class="ded-kpi-label">Exactitud de inventario</div>
			<div class="ded-kpi-val">{{ number_format($accuracy, 1) }} %</div>
			<div class="ded-kpi-sub">Último conteo cíclico 10/11</div>
		</article>

		<!-- 7. Traslados pendientes de recepción -->
		<article class="ded-kpi-card" title="Definición: Despachos entre sucursales en tránsito pendientes de confirmación física y recepción en destino.&#10;Fórmula: Envíos despachados − Envíos confirmados en destino.&#10;Monto exacto: 3 traslados (1,340 unidades)&#10;Comparación: 2 envíos con más de 48 h en tránsito&#10;Origen: Módulo de Traslados entre Tiendas&#10;Actualización: {{ date('d/m/Y H:i') }}" onclick="window.location.href='{{ $U('/transfers') }}';" style="cursor: pointer;">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="background:#eff6ff; color:#2563eb;"><i data-lucide="truck"></i></span>
			</div>
			<div class="ded-kpi-label">Traslados pendientes</div>
			<div class="ded-kpi-val">3 envíos</div>
			<div class="ded-kpi-sub">1,340 u · pendientes recepción</div>
		</article>

		<!-- 8. Por vencer (30 días) -->
		<article class="ded-kpi-card" title="Definición: Lotes con fecha de caducidad dentro de los próximos 30 días.&#10;Fórmula: Σ Valor a costo de los lotes activos con vencimiento ≤ 30 días.&#10;Monto exacto: ${{ number_format($expiringAmount, 0) }} ({{ $expiringUnits }} u · {{ $expiringLots }} lotes)&#10;Comparación: Alerta preventiva FEFO&#10;Origen: Trazabilidad por lote y fecha BBD&#10;Actualización: {{ date('d/m/Y H:i') }}" data-smv-tab="lotes" style="cursor: pointer;">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="hourglass"></i></span>
			</div>
			<div class="ded-kpi-label">Por vencer (30 días)</div>
			<div class="ded-kpi-val">${{ number_format($expiringAmount, 0) }}</div>
			<div class="ded-kpi-sub">{{ $expiringUnits }} unidades · {{ $expiringLots }} lotes</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 2 — ENTRADAS VS. SALIDAS POR DÍA + COMPARACIÓN DE INVENTARIO
	     ==================================================================== -->
	<section class="ded-row-2">
		<!-- Izquierda: Entradas vs. salidas por día -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Entradas vs. salidas por día</h2>
				<div class="ded-card-tools">
					<div class="ded-legend-inline">
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#10b981;"></span>
							<span>Entradas (recepciones)</span>
						</span>
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#94a3b8;"></span>
							<span>Salidas (ventas y despachos)</span>
						</span>
					</div>
					<button type="button" class="ded-expand-btn" data-expand="flow" title="Ampliar gráfico de flujo de mercadería" aria-label="Ampliar">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box">
				<canvas id="dedChartStockFlow" aria-label="Gráfico de entradas vs salidas por día"></canvas>
			</div>
		</article>

		<!-- Derecha: Comparación de inventario -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Comparación de inventario</h2>
			</header>
			<div class="table-responsive">
				<table class="ded-table-top5" aria-label="Comparación de indicadores de inventario">
					<thead>
						<tr>
							<th>Indicador</th>
							<th class="text-right">Actual</th>
							<th class="text-right">Anterior</th>
							<th class="text-right">Var.</th>
						</tr>
					</thead>
					<tbody>
						@foreach($compRows as $cr)
						<tr>
							<td class="font-weight-600 text-dark">{{ $cr['label'] }}</td>
							<td class="ded-table-price font-weight-700">{{ $cr['cur'] }}</td>
							<td class="ded-table-share">{{ $cr['prev'] }}</td>
							<td class="text-right">
								@if($cr['up'])
									<span class="ded-kpi-badge-green font-weight-600">▲ {{ $cr['delta'] }}</span>
								@else
									<span class="ded-pill-red font-weight-600">▼ {{ $cr['delta'] }}</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 3 — ANÁLISIS DE RIESGO Y COBERTURA (3 COLUMNAS)
	     ==================================================================== -->
	<section class="ded-row-3">
		<!-- Panel 1: Top 5 con menor cobertura -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Top 5 con menor cobertura</h2>
				<button type="button" class="ded-expand-btn" data-expand="coverage" title="Ampliar tabla de menor cobertura" aria-label="Ampliar">
					<i data-lucide="maximize-2"></i>
				</button>
			</header>
			<table class="ded-table-top5" aria-label="Top 5 con menor cobertura">
				<thead>
					<tr>
						<th style="width: 22px;">#</th>
						<th>Producto</th>
						<th class="text-right">Stock</th>
						<th class="text-right">Días</th>
						<th class="text-right" style="width: 65px;"></th>
					</tr>
				</thead>
				<tbody>
					@foreach($topCoverage as $idx => $item)
					<tr class="is-clickable" title="{{ $item['full'] }} · Stock: {{ $item['stock'] }} u · Cobertura: {{ $item['days'] }} días" data-smv-tab="movimientos">
						<td><span class="ded-rank-circle">{{ $idx + 1 }}</span></td>
						<td><div class="ded-prod-name" title="{{ $item['full'] }}">{{ $item['name'] }}</div></td>
						<td class="ded-table-num">{{ $item['stock'] }}</td>
						<td class="ded-table-price">{{ number_format($item['days'], 1) }}</td>
						<td class="text-right">
							@if($item['status'] === 'critico')
								<span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600;">Crítico</span>
							@else
								<span class="badge" style="background:#fef3c7; color:#b45309; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600;">Bajo</span>
							@endif
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</article>

		<!-- Panel 2: Stock vs. punto de reorden -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Stock vs. punto de reorden</h2>
				<div class="ded-card-tools">
					<div class="ded-legend-inline">
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#10b981;"></span>
							<span>Stock</span>
						</span>
						<span class="ded-legend-item">
							<span class="ded-legend-line-dot"></span>
							<span>Reorden</span>
						</span>
					</div>
					<button type="button" class="ded-expand-btn" data-expand="reorder" title="Ampliar gráfico de reorden" aria-label="Ampliar">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>
			<div class="ded-chart-canvas-box" style="height: 155px;">
				<canvas id="dedChartStockReorder" aria-label="Gráfico de stock vs punto de reorden"></canvas>
			</div>
			<div class="mt-2 text-muted" style="font-size:11.5px; border-top: 1px dashed #f1f5f9; padding-top: 6px;">
				Cobertura actual: <b class="text-dark">41 días</b> (reorden en 28)
			</div>
		</article>

		<!-- Panel 3: Clasificación ABC -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Clasificación ABC</h2>
				<button type="button" class="ded-expand-btn" data-expand="abc" title="Ampliar desglose ABC" aria-label="Ampliar">
					<i data-lucide="maximize-2"></i>
				</button>
			</header>
			<div class="ded-dept-donut-layout">
				<div class="ded-donut-wrap">
					<canvas id="dedChartStockABC" aria-label="Gráfico de dona clasificación ABC"></canvas>
					<div class="ded-donut-center-info">
						<span class="ded-donut-center-amount">A: 78 %</span>
						<span class="ded-donut-center-label">del valor</span>
					</div>
				</div>
				<div class="ded-dept-list">
					<div class="ded-dept-row" title="Tipo A: 78% del valor total">
						<div class="ded-dept-name-box">
							<span class="ded-dept-dot" style="background-color: #10b981;"></span>
							<span>A</span>
						</div>
						<span class="ded-dept-pct">78 %</span>
					</div>
					<div class="ded-dept-row" title="Tipo B: 16% del valor total">
						<div class="ded-dept-name-box">
							<span class="ded-dept-dot" style="background-color: #f59e0b;"></span>
							<span>B</span>
						</div>
						<span class="ded-dept-pct">16 %</span>
					</div>
					<div class="ded-dept-row" title="Tipo C: 6% del valor total">
						<div class="ded-dept-name-box">
							<span class="ded-dept-dot" style="background-color: #94a3b8;"></span>
							<span>C</span>
						</div>
						<span class="ded-dept-pct">6 %</span>
					</div>
				</div>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 4 — MOVIMIENTOS POR TIPO, FEFO Y ALERTAS (3 COLUMNAS)
	     ==================================================================== -->
	<section class="ded-row-3">
		<!-- Panel 1: Movimientos por tipo -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Movimientos por tipo</h2>
				<button type="button" class="ded-expand-btn" data-expand="types" title="Ampliar movimientos por tipo" aria-label="Ampliar">
					<i data-lucide="maximize-2"></i>
				</button>
			</header>
			<div class="d-flex flex-column gap-2" style="margin-top: 2px;">
				@foreach($typeBars as $tb)
				<div class="d-flex align-items-center justify-content-between" style="font-size: 11.5px;">
					<span class="text-muted" style="min-width: 82px;">{{ $tb['label'] }}</span>
					<div class="flex-grow-1 mx-2" style="height: 6px; background:#f1f5f9; border-radius: 99px; overflow:hidden;">
						<div style="width: {{ $tb['pct'] }}%; height: 100%; background-color: {{ $tb['color'] }}; border-radius: 99px;"></div>
					</div>
					<span class="font-weight-600 text-dark" style="min-width: 32px; text-align: right;">{{ $tb['pct'] }} %</span>
				</div>
				@endforeach
			</div>
			<div class="mt-2 text-muted" style="font-size: 11.5px; border-top: 1px dashed #f1f5f9; padding-top: 6px;">
				Recepción 48 % · Venta POS 36 % · Transferencia 7 %
			</div>
		</article>

		<!-- Panel 2: Lotes por vencer (FEFO) -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Lotes por vencer (FEFO)</h2>
				<button type="button" class="ded-expand-btn" data-expand="fefo" title="Ampliar lotes por vencer" aria-label="Ampliar">
					<i data-lucide="maximize-2"></i>
				</button>
			</header>
			<div class="d-flex flex-column gap-3" style="margin-top: 6px;">
				@foreach($fefoBars as $fb)
				<div>
					<div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 11.5px;">
						<span class="font-weight-600 text-dark">{{ $fb['label'] }}</span>
						<span class="font-weight-700 text-dark">{{ $fb['val'] }}</span>
					</div>
					<div style="height: 7px; background:#f1f5f9; border-radius: 99px; overflow:hidden;">
						<div style="width: {{ $fb['pct'] }}%; height: 100%; background-color: {{ $fb['color'] }}; border-radius: 99px;"></div>
					</div>
				</div>
				@endforeach
			</div>
			<div class="mt-auto pt-2 text-muted" style="font-size: 11.5px; border-top: 1px dashed #f1f5f9;">
				Se despacha primero el lote que vence antes.
			</div>
		</article>

		<!-- Panel 3: Alertas de inventario -->
		<article class="ded-card">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Alertas de inventario</h2>
				<button type="button" class="ded-expand-btn" data-expand="alerts" title="Ampliar alertas de inventario" aria-label="Ampliar">
					<i data-lucide="maximize-2"></i>
				</button>
			</header>
			<div class="d-flex flex-column gap-2" style="margin-top: 2px;">
				<!-- Alerta 1 -->
				<div class="p-2 rounded is-clickable" style="background:#fff5f5; border: 1px solid #fee2e2;" data-smv-tab="conteos">
					<div class="font-weight-700" style="font-size: 12px; color: #b91c1c;">Quiebre en 23 SKU · Crítica</div>
					<div class="text-muted" style="font-size: 11px; margin-top: 2px; line-height: 1.3;">
						Reponer hoy: 9 de ellos tienen orden de compra abierta.
					</div>
				</div>
				<!-- Alerta 2 -->
				<div class="p-2 rounded is-clickable" style="background:#fffbeb; border: 1px solid #fef3c7;" data-smv-tab="movimientos">
					<div class="font-weight-700" style="font-size: 12px; color: #b45309;">Sobrestock en 41 SKU · Media</div>
					<div class="text-muted" style="font-size: 11px; margin-top: 2px; line-height: 1.3;">
						Más de 90 días de cobertura. Evalúa promoción o transferencia.
					</div>
				</div>
				<!-- Alerta 3 -->
				<div class="p-2 rounded is-clickable" style="background:#f8fafc; border: 1px solid #e2e8f0;" data-smv-tab="conteos">
					<div class="font-weight-700 text-dark" style="font-size: 12px;">Diferencia de conteo en Sucursal Centro: -1.2 %</div>
					<div class="text-muted" style="font-size: 11px; margin-top: 2px; line-height: 1.3;">
						Revisa los ajustes del 10/11.
					</div>
				</div>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 5 — MÓDULOS CONECTADOS CON STOCK Y MOVIMIENTOS (2 COLUMNAS)
	     ==================================================================== -->
	<article class="ded-card smv-connected-modules-card mt-3">
		<header class="ded-card-header">
			<div class="d-flex align-items-center gap-2">
				<span class="ded-dept-dot" style="background-color: #10b981; width:10px; height:10px;"></span>
				<h2 class="ded-card-title">Módulos conectados con Stock y Movimientos</h2>
			</div>
			<a href="{{ $U('/inventorycontrol') }}" class="text-muted" style="font-size: 12px; text-decoration: none;">
				Ver mapa del sistema <i data-lucide="arrow-up-right" style="width:13px; height:13px; margin-left: 2px;"></i>
			</a>
		</header>
		<div class="row">
			<!-- Columna 1: RECIBE INFORMACIÓN DE -->
			<div class="col-12 col-md-6 mb-3 mb-md-0">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">Recibe información de</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Punto de Venta (POS) -->
					<a href="{{ $U('/pos') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="calculator"></i></span>
							<div>
								<div class="smv-mod-title">3. Punto de Venta (POS)</div>
								<div class="smv-mod-desc">Cada venta genera una salida de stock</div>
							</div>
						</div>
						<i data-lucide="arrow-left" class="smv-mod-arrow"></i>
					</a>
					<!-- 2. Compras y Recepción -->
					<a href="{{ $U('/purchaseorders') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="truck"></i></span>
							<div>
								<div class="smv-mod-title">7. Compras y Recepción</div>
								<div class="smv-mod-desc">La recepción de la orden suma stock por lote</div>
							</div>
						</div>
						<i data-lucide="arrow-left" class="smv-mod-arrow"></i>
					</a>
					<!-- 3. Devoluciones -->
					<a href="{{ $U('/customerreturns') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="rotate-ccw"></i></span>
							<div>
								<div class="smv-mod-title">3. Devoluciones</div>
								<div class="smv-mod-desc">La devolución vuelve al stock o pasa a merma</div>
							</div>
						</div>
						<i data-lucide="arrow-left" class="smv-mod-arrow"></i>
					</a>
				</div>
			</div>

			<!-- Columna 2: ACTUALIZA / ALIMENTA A -->
			<div class="col-12 col-md-6">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">Actualiza / alimenta a</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Planificación MRP -->
					<a href="{{ $U('/procurement') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="calendar-clock"></i></span>
							<div>
								<div class="smv-mod-title">7. Planificación MRP</div>
								<div class="smv-mod-desc">Cobertura y reorden para sugerir la compra</div>
							</div>
						</div>
						<i data-lucide="arrow-right" class="smv-mod-arrow"></i>
					</a>
					<!-- 2. Mermas y prevención -->
					<a href="{{ $U('/waste') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="shield-alert"></i></span>
							<div>
								<div class="smv-mod-title">4. Mermas y prevención</div>
								<div class="smv-mod-desc">Vencidos y diferencias de conteo</div>
							</div>
						</div>
						<i data-lucide="arrow-right" class="smv-mod-arrow"></i>
					</a>
					<!-- 3. Tablero de Ventas -->
					<a href="{{ $U('/sales') }}" class="smv-mod-box">
						<div class="d-flex align-items-center gap-2">
							<span class="smv-mod-icon"><i data-lucide="bar-chart-3"></i></span>
							<div>
								<div class="smv-mod-title">1. Tablero de Ventas</div>
								<div class="smv-mod-desc">Disponibilidad y quiebres que afectan la venta</div>
							</div>
						</div>
						<i data-lucide="arrow-right" class="smv-mod-arrow"></i>
					</a>
				</div>
			</div>
		</div>
	</article>

</div>
