{{-- ==========================================================================
     DAILY — TABLERO DE MERMAS Y PREVENCIÓN (RÉPLICA EXACTA DE LA FOTO)
     ========================================================================== --}}
@php
	$wb = $wasteBoard ?? [];
	$k = $wb['kpis'] ?? [];
	$evo = $wb['evolution'] ?? [];
	$bb = $wb['branches_breakdown'] ?? [];
	$attn = $wb['attention_cases'] ?? [];
	$pareto = $wb['pareto_causes'] ?? [];
	$topProds = $wb['top_products'] ?? [];
@endphp

<div class="dp-dashboard-content" id="wasteExecutiveBoard">

	<!-- ====================================================================
	     FILA 1 — 8 INDICADORES CLAVE (UNA SOLA FILA EN ESCRITORIO >= 1500px)
	     ==================================================================== -->
	<section class="ded-kpis-grid has-8-cards dp-kpis-8 mb-3" aria-label="Indicadores Clave de Mermas y Prevención">
		<!-- 1. Merma confirmada a costo (Hero Verde) -->
		<article class="ded-kpi-card is-highlight" title="Total de mermas y bajas confirmadas a costo durante el período comercial">
			<div class="ded-kpi-top-highlight d-flex justify-content-between align-items-center">
				<div class="ded-highlight-brand d-flex align-items-center gap-1.5">
					<span class="ded-kpi-icon-wrap"><i data-lucide="shield-alert"></i></span>
					<span>Merma confirmada a costo</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val">{{ $k['confirmed_cost']['formatted'] ?? '$12,480' }}</div>
			<div class="ded-kpi-sub-highlight d-flex justify-content-between align-items-center">
				<span>Período</span>
				<span class="font-weight-700">▲ {{ $k['confirmed_cost']['delta'] ?? '+4.0 %' }}</span>
			</div>
		</article>

		<!-- 2. Merma sobre ventas netas -->
		<article class="ded-kpi-card" title="Merma confirmada a costo dividida por las ventas netas del mismo alcance">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="percent"></i></span>
			</div>
			<div class="ded-kpi-label">Merma sobre ventas netas</div>
			<div class="ded-kpi-val">{{ $k['waste_pct_sales']['formatted'] ?? '1.82 %' }}</div>
			<div class="ded-kpi-sub d-flex justify-content-between align-items-center">
				<span>Período</span>
				<span class="text-success font-weight-600">▲ {{ $k['waste_pct_sales']['delta'] ?? '+0.32 pp vs. meta' }}</span>
			</div>
		</article>

		<!-- 3. Unidades dadas de baja -->
		<article class="ded-kpi-card" title="Total de unidades físicas dadas de baja en el período">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="package"></i></span>
			</div>
			<div class="ded-kpi-label">Unidades dadas de baja</div>
			<div class="ded-kpi-val">{{ $k['units_lost']['formatted'] ?? '1,240' }}</div>
			<div class="ded-kpi-sub">Período</div>
		</article>

		<!-- 4. Merma por vencimiento -->
		<article class="ded-kpi-card" title="Importe a costo de las bajas confirmadas por caducidad o fecha vencida">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="calendar"></i></span>
			</div>
			<div class="ded-kpi-label">Merma por vencimiento</div>
			<div class="ded-kpi-val">{{ $k['expired_cost']['formatted'] ?? '$4,800' }}</div>
			<div class="ded-kpi-sub d-flex justify-content-between">
				<span>Período</span>
				<span class="text-muted">Parte del total</span>
			</div>
		</article>

		<!-- 5. Pendientes de aprobación -->
		<article class="ded-kpi-card" onclick="window.location.href='{{ $U('/waste/approvals') }}';" style="cursor: pointer;" title="Cantidad de registros pendientes de autorización">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-warning"><i data-lucide="clock"></i></span>
			</div>
			<div class="ded-kpi-label">Pendientes de aprobación</div>
			<div class="ded-kpi-val">{{ $k['pending_approvals']['formatted'] ?? '18' }}</div>
			<div class="ded-kpi-sub d-flex align-items-center">
				<span class="dp-dot-status mr-1" style="color: #f59e0b;">●</span>
				<span>Estado actual</span>
			</div>
		</article>

		<!-- 6. Investigaciones abiertas -->
		<article class="ded-kpi-card" onclick="switchWasteTab('investigaciones');" style="cursor: pointer;" title="Expedientes de merma en proceso de investigación">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-slate"><i data-lucide="search"></i></span>
			</div>
			<div class="ded-kpi-label">Investigaciones abiertas</div>
			<div class="ded-kpi-val">{{ $k['open_investigations']['formatted'] ?? '7' }}</div>
			<div class="ded-kpi-sub d-flex align-items-center">
				<span class="dp-dot-status mr-1" style="color: #64748b;">●</span>
				<span>Estado actual</span>
			</div>
		</article>

		<!-- 7. Acciones correctivas vencidas -->
		<article class="ded-kpi-card" onclick="switchWasteTab('acciones');" style="cursor: pointer;" title="Acciones correctivas cuyo plazo de ejecución ha vencido">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-danger"><i data-lucide="alert-triangle"></i></span>
			</div>
			<div class="ded-kpi-label">Acciones correctivas vencidas</div>
			<div class="ded-kpi-val">{{ $k['overdue_actions']['formatted'] ?? '4' }}</div>
			<div class="ded-kpi-sub d-flex align-items-center">
				<span class="dp-dot-status mr-1" style="color: #ef4444;">●</span>
				<span>Estado actual</span>
			</div>
		</article>

		<!-- 8. Recuperación financiera -->
		<article class="ded-kpi-card" onclick="switchWasteTab('recuperacion');" style="cursor: pointer;" title="Importe efectivamente recuperado mediante reclamos a proveedores o seguros">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-success"><i data-lucide="coins"></i></span>
			</div>
			<div class="ded-kpi-label">Recuperación financiera</div>
			<div class="ded-kpi-val">{{ $k['recovered_cost']['formatted'] ?? '$2,150' }}</div>
			<div class="ded-kpi-sub">Período</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 2 — PANELES: EVOLUCIÓN DE LA MERMA (1.7) + MERMA POR SUCURSAL (1.0)
	     ==================================================================== -->
	<section class="ded-row-2 dp-row-evolution mb-3">
		<!-- Izquierda (1.7): Evolución de la merma -->
		<article class="ded-card dp-card-chart">
			<header class="ded-card-header d-flex justify-content-between align-items-center">
				<div>
					<h2 class="ded-card-title mb-0">Evolución de la merma</h2>
					<div class="text-muted font-11 mt-0.5">Total del período: <strong class="text-dark dp-num-cell">$12,480</strong></div>
				</div>
				<div class="ded-card-tools d-flex align-items-center gap-3">
					<!-- Leyenda inline -->
					<div class="ded-legend-inline">
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#10b26c;"></span>
							<span>Merma diaria</span>
						</span>
						<span class="ded-legend-item">
							<span class="dp-legend-line" style="background:#f59e0b;"></span>
							<span>Promedio diario</span>
						</span>
					</div>

					<!-- Botón Expandir -->
					<button type="button" class="ded-expand-btn" title="Expandir gráfico" aria-label="Expandir gráfico">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>

			<!-- Contenedor del Gráfico SVG Interactivo -->
			<div class="dp-chart-wrap position-relative" style="height: 245px; width: 100%;">
				<div id="wasteEvolutionSvgChart" style="width: 100%; height: 100%;"></div>
				<div id="wasteChartTooltip" class="dp-chart-tooltip" style="display:none;"></div>
			</div>
		</article>

		<!-- Derecha (1.0): Merma por sucursal -->
		<article class="ded-card dp-card-branches">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Merma por sucursal</h2>
			</header>
			<div class="table-responsive">
				<table class="table table-sm ded-table ded-table-branches mb-0">
					<thead>
						<tr>
							<th style="width: 32%;">Sucursal</th>
							<th class="text-right" style="width: 26%;">Merma a costo</th>
							<th class="text-right" style="width: 20%;">% ventas</th>
							<th class="text-right" style="width: 22%;">Δ meta <i data-lucide="info" style="width:11px;height:11px;display:inline-block;vertical-align:middle;"></i></th>
						</tr>
					</thead>
					<tbody>
						@foreach($bb as $branch)
						<tr>
							<td class="font-weight-600 text-dark">{{ $branch['name'] }}</td>
							<td class="text-right font-weight-700 dp-num-cell">${{ number_format($branch['cost'], 0) }}</td>
							<td class="text-right dp-num-cell">{{ number_format($branch['pct_sales'], 2) }} %</td>
							<td class="text-right font-weight-600 dp-num-cell" style="color:#ef4444;">▲ {{ $branch['delta_meta'] }}</td>
						</tr>
						@endforeach
					</tbody>
					<tfoot>
						<tr class="dp-branches-total-row">
							<td class="font-weight-700 text-dark">Total</td>
							<td class="text-right font-weight-700 text-dark dp-num-cell">$12,480</td>
							<td class="text-right font-weight-700 text-dark dp-num-cell">1.82 %</td>
							<td class="text-right font-weight-700 dp-num-cell" style="color:#ef4444;">▲ +0.32 pp</td>
						</tr>
					</tfoot>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 3 — PANELES: CASOS (1.7) + PARETO (1.0) + TOP 5 PRODUCTOS (1.0)
	     ==================================================================== -->
	<section class="ded-row-3 dp-row-details mb-3">
		<!-- Columna 1 (1.7): Casos que requieren atención -->
		<article class="ded-card dp-card-attention">
			<header class="ded-card-header d-flex justify-content-between align-items-center">
				<h2 class="ded-card-title">Casos que requieren atención</h2>
				<div class="ded-card-tools">
					<button type="button" class="ded-expand-btn" title="Expandir lista de casos" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>
			<div class="table-responsive">
				<table class="table table-sm ded-table ded-table-top5 mb-0" id="tableAttentionCases">
					<thead>
						<tr>
							<th style="width: 18%;">ID</th>
							<th style="width: 16%;">Sucursal</th>
							<th style="width: 22%;">Causal</th>
							<th class="text-right" style="width: 16%;">Impacto</th>
							<th style="width: 16%;">Estado</th>
							<th class="text-center" style="width: 12%;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@foreach($attn as $idx => $case)
						<tr class="dp-order-row">
							<td class="font-weight-700 text-dark dp-num-cell">
								<span class="dp-order-code">{{ $case['id'] }}</span>
							</td>
							<td class="text-dark">{{ $case['branch'] }}</td>
							<td class="text-dark font-weight-500">{{ $case['causal'] }}</td>
							<td class="text-right font-weight-700 text-dark dp-num-cell">{{ $case['cost'] }}</td>
							<td>
								@if($case['status_type'] === 'danger')
									<span class="badge dp-pill-status is-danger">Requiere acción</span>
								@elseif($case['status_type'] === 'info')
									<span class="badge dp-pill-status is-info">En investigación</span>
								@else
									<span class="badge dp-pill-status is-warning">Pendiente</span>
								@endif
							</td>
							<td class="text-center">
								@if($case['action_type'] === 'danger')
									<a href="{{ $U($case['action_url']) }}" class="btn btn-xs btn-outline-danger font-weight-600 px-2 py-0.5 rounded-pill">{{ $case['action_label'] }}</a>
								@elseif($case['action_type'] === 'primary')
									<a href="{{ $U($case['action_url']) }}" class="btn btn-xs btn-outline-primary font-weight-600 px-2 py-0.5 rounded-pill">{{ $case['action_label'] }}</a>
								@else
									<a href="{{ $U($case['action_url']) }}" class="btn btn-xs btn-outline-success font-weight-600 px-2 py-0.5 rounded-pill">{{ $case['action_label'] }}</a>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>

		<!-- Columna 2 (1.0): Pareto de causales -->
		<article class="ded-card dp-card-pareto">
			<header class="ded-card-header d-flex justify-content-between align-items-center">
				<h2 class="ded-card-title mb-0" style="font-size: 13.5px; white-space: nowrap;">Pareto de causales</h2>
				<div class="ded-card-tools d-flex align-items-center gap-1.5">
					<div class="ded-legend-inline d-flex align-items-center" style="font-size: 10px;">
						<span class="ded-legend-item mr-1.5">
							<span class="ded-legend-rect" style="background:#10b26c;"></span>
							<span>Merma a costo</span>
						</span>
						<span class="ded-legend-item">
							<span class="dp-legend-line" style="background:#f59e0b;"></span>
							<span>% acumulado</span>
						</span>
					</div>
					<button type="button" class="ded-expand-btn" title="Expandir Pareto" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>

			<div class="dp-pareto-wrap position-relative" style="height: 235px; width: 100%;">
				<div id="wasteParetoSvgChart" style="width: 100%; height: 100%;"></div>
			</div>
		</article>

		<!-- Columna 3 (1.0): Top 5 productos con mayor merma -->
		<article class="ded-card dp-card-top5">
			<header class="ded-card-header d-flex justify-content-between align-items-center">
				<h2 class="ded-card-title">Top 5 productos con mayor merma</h2>
				<div class="ded-card-tools">
					<button type="button" class="ded-expand-btn" title="Expandir Top 5" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>

			<div class="table-responsive">
				<table class="table table-sm ded-table ded-table-top5 mb-0">
					<thead>
						<tr>
							<th style="width: 22%;">SKU</th>
							<th style="width: 48%;">Producto</th>
							<th class="text-right" style="width: 14%;">Unidades</th>
							<th class="text-right" style="width: 16%;">Costo</th>
						</tr>
					</thead>
					<tbody>
						@foreach($topProds as $p)
						<tr>
							<td>
								<span class="badge dp-sku-pill">{{ $p['sku'] }}</span>
							</td>
							<td class="font-weight-600 text-dark">{{ $p['name'] }}</td>
							<td class="text-right font-weight-600 text-muted dp-num-cell">{{ $p['units'] }}</td>
							<td class="text-right font-weight-700 text-dark dp-num-cell">{{ $p['cost'] }}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 4 — MÓDULOS CONECTADOS (2 COLUMNAS · EXACTO A LA FOTO)
	     ==================================================================== -->
	<section class="ded-card dp-connected-modules-card mb-4" aria-label="Módulos conectados con Mermas y Prevención">
		<header class="ded-card-header pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
			<div class="d-flex align-items-center gap-2">
				<span class="text-success"><i data-lucide="network"></i></span>
				<h2 class="ded-card-title font-weight-700 mb-0" style="font-size: 14px;">Módulos conectados con Mermas y Prevención</h2>
			</div>
			<div class="d-flex align-items-center gap-2">
				<a href="{{ $U('/inventory') }}" class="text-muted font-12 text-decoration-none hover-underline">
					Conteos y Conciliación <i data-lucide="arrow-up-right" style="width:12px;height:12px;" class="d-inline"></i>
				</a>
				<span class="text-muted mx-1">|</span>
				<a href="{{ $U('/stockoverview') }}" class="text-muted font-12 text-decoration-none hover-underline">
					FEFO <i data-lucide="arrow-up-right" style="width:12px;height:12px;" class="d-inline"></i>
				</a>
			</div>
		</header>

		<div class="row">
			<!-- Columna 1: RECIBE INFORMACIÓN DE -->
			<div class="col-12 col-md-6 mb-3 mb-md-0">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">RECIBE INFORMACIÓN DE</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Stock y Movimientos -->
					<a href="{{ $U('/stockoverview') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="box"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">1. Stock y Movimientos</div>
							<div class="dp-mod-desc">Entradas, salidas y ajustes de inventario</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-left"></i></div>
					</a>

					<!-- 2. Calidad y Vencimientos -->
					<a href="{{ $U('/traceability') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="calendar"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">2. Calidad y Vencimientos</div>
							<div class="dp-mod-desc">Lotes, fechas de caducidad y producto próximo a vencer</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-left"></i></div>
					</a>

					<!-- 3. Despachos y Devoluciones -->
					<a href="{{ $U('/dispatches') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="truck"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">3. Despachos y Devoluciones</div>
							<div class="dp-mod-desc">Devoluciones, rechazos y mercancía con daño</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-left"></i></div>
					</a>
				</div>
			</div>

			<!-- Columna 2: ACTUALIZA / ALIMENTA A -->
			<div class="col-12 col-md-6">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">ACTUALIZA / ALIMENTA A</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Prevención Inteligente -->
					<a href="{{ $U('/waste?tab=prevencion') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="shield-check"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">1. Prevención Inteligente</div>
							<div class="dp-mod-desc">Alertas de riesgo, patrones y recomendaciones</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>

					<!-- 2. Planes de Acción -->
					<a href="{{ $U('/waste?tab=acciones') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="flag"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">2. Planes de Acción</div>
							<div class="dp-mod-desc">Acciones preventivas y correctivas con responsables</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>

					<!-- 3. Panel Ejecutivo -->
					<a href="{{ $U('/') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="trending-up"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">3. Panel Ejecutivo</div>
							<div class="dp-mod-desc">Mermas, tendencias y cumplimiento de metas</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>

					<!-- 4. Reportes e Informes -->
					<a href="{{ $U('/reports') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="file-text"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">4. Reportes e Informes</div>
							<div class="dp-mod-desc">Detalle de mermas, causales y recuperación financiera</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>
				</div>
			</div>
		</div>

		<!-- Badge Datos demo esquina inferior derecha -->
		<div class="d-flex justify-content-end mt-3">
			<span class="badge badge-light text-muted px-2 py-1 border" style="font-size: 11px; border-radius: 6px;">Datos demo</span>
		</div>
	</section>

</div>
