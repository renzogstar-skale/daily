{{-- ==========================================================================
     DAILY — TABLERO DE DESPACHOS Y DEVOLUCIONES (RÉPLICA EXACTA DE LA FOTO)
     ========================================================================== --}}
@php
	$db = $dispatchBoard ?? [];
	$k = $db['kpis'] ?? [];
	$evo = $db['evolution'] ?? [];
	$bc = $db['branches_compliance'] ?? [];
	$attn = $db['attention_orders'] ?? [];
	$donut = $db['delivery_status_donut'] ?? [];
	$ret = $db['returns_by_reason'] ?? [];
@endphp

<div class="dp-dashboard-content" id="dispatchExecutiveBoard">

	<!-- ====================================================================
	     FILA 1 — 8 INDICADORES CLAVE (UNA SOLA FILA EN ESCRITORIO >= 1500px)
	     ==================================================================== -->
	<section class="ded-kpis-grid has-8-cards dp-kpis-8 mb-3" aria-label="Indicadores Clave de Despachos y Devoluciones">
		<!-- 1. Pedidos despachados (Hero Verde) -->
		<article class="ded-kpi-card is-highlight" onclick="switchDispatchTab('pedidos');" style="cursor: pointer;" title="Total de pedidos despachados en el período comercial">
			<div class="ded-highlight-brand">
				<i data-lucide="truck"></i>
				<span>Pedidos despachados</span>
			</div>
			<div class="ded-kpi-val">{{ number_format($k['dispatched']['value'] ?? 1248, 0) }}</div>
		</article>

		<!-- 2. Pendientes de despacho -->
		<article class="ded-kpi-card" onclick="switchDispatchTab('pedidos');" style="cursor: pointer;" title="Pedidos pendientes de salida física">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="clipboard-list"></i></span>
			</div>
			<div class="ded-kpi-label">Pendientes de despacho</div>
			<div class="ded-kpi-val">{{ number_format($k['pending']['value'] ?? 64, 0) }}</div>
		</article>

		<!-- 3. Despachos a tiempo -->
		<article class="ded-kpi-card" title="Porcentaje de despachos completados dentro del tiempo límite">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="check-circle"></i></span>
			</div>
			<div class="ded-kpi-label">Despachos a tiempo</div>
			<div class="ded-kpi-val">{{ number_format($k['ontime_pct']['value'] ?? 96.8, 1) }} %</div>
		</article>

		<!-- 4. Despachos atrasados -->
		<article class="ded-kpi-card" style="cursor: pointer;" title="Pedidos que superaron la hora límite de despacho">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-danger"><i data-lucide="clock"></i></span>
			</div>
			<div class="ded-kpi-label">Despachos atrasados</div>
			<div class="ded-kpi-val" style="color:#ef4444;">{{ number_format($k['delayed']['value'] ?? 12, 0) }}</div>
		</article>

		<!-- 5. Pedidos en tránsito -->
		<article class="ded-kpi-card" title="Pedidos actualmente en ruta hacia el cliente">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="truck"></i></span>
			</div>
			<div class="ded-kpi-label">Pedidos en tránsito</div>
			<div class="ded-kpi-val">{{ number_format($k['in_transit']['value'] ?? 86, 0) }}</div>
		</article>

		<!-- 6. Entregas confirmadas -->
		<article class="ded-kpi-card" title="Pedidos entregados con confirmación">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="check-square"></i></span>
			</div>
			<div class="ded-kpi-label">Entregas confirmadas</div>
			<div class="ded-kpi-val">{{ number_format($k['confirmed']['value'] ?? 1162, 0) }}</div>
		</article>

		<!-- 7. Devoluciones recibidas -->
		<article class="ded-kpi-card" onclick="window.location.href='{{ $U('/returns') }}';" style="cursor: pointer;" title="Devoluciones recibidas en el período">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="rotate-ccw"></i></span>
			</div>
			<div class="ded-kpi-label">Devoluciones recibidas</div>
			<div class="ded-kpi-val">{{ number_format($k['returns_received']['value'] ?? 38, 0) }}</div>
		</article>

		<!-- 8. Devoluciones pendientes -->
		<article class="ded-kpi-card" onclick="window.location.href='{{ $U('/returns') }}';" style="cursor: pointer;" title="Devoluciones pendientes de resolución">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon is-warning"><i data-lucide="alert-triangle"></i></span>
			</div>
			<div class="ded-kpi-label">Devoluciones pendientes</div>
			<div class="ded-kpi-val" style="color:#f59e0b;">{{ number_format($k['returns_pending']['value'] ?? 9, 0) }}</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 2 — PANELES: DESPACHOS Y ENTREGAS POR DÍA (1.7) + CUMPLIMIENTO POR SUCURSAL (1.0)
	     ==================================================================== -->
	<section class="ded-row-2 dp-row-evolution mb-3">
		<!-- Izquierda (1.7): Despachos y entregas por día -->
		<article class="ded-card dp-card-chart">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Despachos y entregas por día</h2>
				<div class="ded-card-tools d-flex align-items-center gap-3">
					<!-- Leyenda inline -->
					<div class="ded-legend-inline">
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#10b26c;"></span>
							<span>Despachos</span>
						</span>
						<span class="ded-legend-item">
							<span class="dp-legend-line" style="background:#f59e0b;"></span>
							<span>Entregas</span>
						</span>
					</div>

					<!-- Botón Expandir -->
					<button type="button" class="ded-expand-btn" title="Expandir gráfico" aria-label="Expandir gráfico">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>

			<!-- Contenedor del Gráfico SVG Interactivo -->
			<div class="dp-chart-wrap position-relative" style="height: 250px; width: 100%;">
				<div id="dpEvolutionSvgChart" style="width: 100%; height: 100%;"></div>
				<div id="dpChartTooltip" class="dp-chart-tooltip" style="display:none;"></div>
			</div>
		</article>

		<!-- Derecha (1.0): Cumplimiento por sucursal -->
		<article class="ded-card dp-card-branches">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Cumplimiento por sucursal</h2>
			</header>
			<div class="table-responsive">
				<table class="table table-sm ded-table ded-table-branches mb-0">
					<thead>
						<tr>
							<th style="width: 40%;">Sucursal</th>
							<th class="text-right" style="width: 22%;">Despachados</th>
							<th class="text-right" style="width: 20%;">A tiempo</th>
							<th class="text-right" style="width: 18%;">Atrasados</th>
						</tr>
					</thead>
					<tbody>
						@foreach($bc as $branch)
						<tr>
							<td class="font-weight-600 text-dark">{{ $branch['name'] }}</td>
							<td class="text-right font-weight-700 dp-num-cell">{{ number_format($branch['dispatched'], 0) }}</td>
							<td class="text-right dp-num-cell">{{ number_format($branch['ontime_pct'], 1) }} %</td>
							<td class="text-right font-weight-600 dp-num-cell">{{ $branch['delayed'] }}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 3 — PANELES: ATENCIÓN (1.7) + ESTADO ENTREGAS (1.0) + DEVOLUCIONES MOTIVO (1.0)
	     ==================================================================== -->
	<section class="ded-row-3 dp-row-details mb-3">
		<!-- Columna 1 (1.7): Pedidos que requieren atención -->
		<article class="ded-card dp-card-attention">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Pedidos que requieren atención</h2>
				<div class="ded-card-tools">
					<button type="button" class="ded-expand-btn" title="Expandir lista de pedidos" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>
			<div class="table-responsive">
				<table class="table table-sm ded-table ded-table-top5 mb-0" id="tableAttentionOrders">
					<thead>
						<tr>
							<th style="width: 6%;">#</th>
							<th style="width: 26%;">Pedido</th>
							<th style="width: 24%;">Sucursal</th>
							<th style="width: 24%;">Estado</th>
							<th class="text-right" style="width: 20%;">Hora límite</th>
						</tr>
					</thead>
					<tbody>
						@foreach($attn as $idx => $order)
						<tr class="dp-order-row">
							<td class="text-muted font-weight-600">{{ $order['id'] }}</td>
							<td class="font-weight-700 text-dark dp-num-cell">
								<span class="dp-order-code">{{ $order['code'] }}</span>
							</td>
							<td class="text-dark">{{ $order['branch'] }}</td>
							<td>
								@if($order['status_type'] === 'danger')
									<span class="badge dp-pill-status is-danger">Atrasado</span>
								@else
									<span class="badge dp-pill-status is-warning">Pendiente</span>
								@endif
							</td>
							<td class="text-right font-weight-600 text-dark dp-num-cell">{{ $order['deadline'] }}</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>

		<!-- Columna 2 (1.0): Estado de las entregas (Gráfico de Dona) -->
		<article class="ded-card dp-card-donut">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Estado de las entregas</h2>
				<div class="ded-card-tools">
					<button type="button" class="ded-expand-btn" title="Expandir gráfico" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>
			
			<div class="dp-donut-body">
				<div class="dp-donut-chart-container position-relative">
					<svg class="dp-donut-svg" viewBox="0 0 160 160" width="135" height="135">
						<!-- Fondo gris claro de la dona -->
						<circle class="dp-donut-circle-bg" cx="80" cy="80" r="60" fill="none" stroke="#f1f5f9" stroke-width="20" />
						<!-- 1. Entregadas: 1162 / 1248 = 93.1% -> 351 de 377 perímetro -->
						<circle class="dp-donut-seg seg-delivered" cx="80" cy="80" r="60" fill="none" stroke="#10b26c" stroke-width="20" stroke-dasharray="351 377" stroke-dashoffset="0" transform="rotate(-90 80 80)" />
						<!-- 2. En tránsito: 86 / 1248 = 6.9% -> 26 de 377 perímetro -->
						<circle class="dp-donut-seg seg-transit" cx="80" cy="80" r="60" fill="none" stroke="#f59e0b" stroke-width="20" stroke-dasharray="26 377" stroke-dashoffset="-351" transform="rotate(-90 80 80)" />
					</svg>
					<div class="dp-donut-center-info">
						<span class="dp-donut-center-val dp-num-cell">1,162</span>
						<span class="dp-donut-center-label">Entregados</span>
					</div>
				</div>

				<!-- Leyenda de Estados al lado derecho (exacto a la foto) -->
				<div class="dp-donut-legend">
					<div class="dp-donut-legend-item">
						<span class="dp-legend-dot" style="background:#10b26c;"></span>
						<span class="font-weight-700 text-dark dp-num-cell mr-1">1,162</span>
						<span class="text-muted">Entregados</span>
					</div>
					<div class="dp-donut-legend-item">
						<span class="dp-legend-dot" style="background:#f59e0b;"></span>
						<span class="font-weight-700 text-dark dp-num-cell mr-1">86</span>
						<span class="text-muted">En tránsito</span>
					</div>
				</div>
			</div>
		</article>

		<!-- Columna 3 (1.0): Devoluciones por motivo -->
		<article class="ded-card dp-card-reasons">
			<header class="ded-card-header">
				<h2 class="ded-card-title">Devoluciones por motivo</h2>
				<div class="ded-card-tools">
					<button type="button" class="ded-expand-btn" title="Expandir" aria-label="Expandir">
						<i data-lucide="maximize-2"></i>
					</button>
				</div>
			</header>

			<div class="dp-reasons-body d-flex flex-column justify-content-center" style="min-height: 200px; padding: 10px 14px;">
				@foreach(($ret['reasons'] ?? []) as $reason)
				<div class="dp-reason-item mb-3">
					<div class="d-flex align-items-center justify-content-between mb-1" style="font-size: 13px;">
						<span class="text-dark font-weight-500" style="min-width: 140px;">{{ $reason['label'] }}</span>
						<div class="dp-progress-track flex-grow-1 mx-3" style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
							<div class="dp-progress-fill" style="width: {{ $reason['pct'] * 1.5 }}%; height: 100%; background: #10b26c; border-radius: 6px;"></div>
						</div>
						<span class="font-weight-700 text-dark dp-num-cell" style="min-width: 20px; text-align: right;">{{ $reason['count'] }}</span>
					</div>
				</div>
				@endforeach
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     FILA 4 — MÓDULOS CONECTADOS (2 COLUMNAS · EXACTO A LA FOTO)
	     ==================================================================== -->
	<section class="ded-card dp-connected-modules-card mb-4" aria-label="Módulos conectados con Despachos y Devoluciones">
		<header class="ded-card-header pb-2 mb-3 border-bottom">
			<div class="d-flex align-items-center gap-2">
				<span class="text-success"><i data-lucide="network"></i></span>
				<h2 class="ded-card-title font-weight-700 mb-0" style="font-size: 14px;">Módulos conectados con Despachos y Devoluciones</h2>
			</div>
			<a href="{{ $U('/reports') }}" class="text-muted font-12 text-decoration-none hover-underline">
				Ver mapa del sistema <i data-lucide="arrow-right" style="width:12px;height:12px;" class="d-inline"></i>
			</a>
		</header>

		<div class="row">
			<!-- Columna 1: RECIBE INFORMACIÓN DE -->
			<div class="col-12 col-md-6 mb-3 mb-md-0">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">RECIBE INFORMACIÓN DE</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Pedidos -->
					<a href="{{ $U('/orders') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="package"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">Pedidos</div>
							<div class="dp-mod-desc">Registro de pedidos y su estado de preparación</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-left"></i></div>
					</a>

					<!-- 2. Almacén -->
					<a href="{{ $U('/warehouse') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="building"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">Almacén</div>
							<div class="dp-mod-desc">Disponibilidad, surtido y preparación de pedidos</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-left"></i></div>
					</a>
				</div>
			</div>

			<!-- Columna 2: ACTUALIZA / ALIMENTA A -->
			<div class="col-12 col-md-6">
				<div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.04em;">ACTUALIZA / ALIMENTA A</div>
				<div class="d-flex flex-column gap-2">
					<!-- 1. Stock y Movimientos -->
					<a href="{{ $U('/stockoverview') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="layout-grid"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">Stock y Movimientos</div>
							<div class="dp-mod-desc">Salida de inventario por despacho y reingreso por devoluciones</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>

					<!-- 2. Panel Ejecutivo -->
					<a href="{{ $U('/') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="trending-up"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">Panel Ejecutivo</div>
							<div class="dp-mod-desc">Cumplimiento de despachos, entregas y devoluciones</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>

					<!-- 3. Reportes e Informes -->
					<a href="{{ $U('/reports') }}" class="dp-mod-box">
						<div class="dp-mod-icon"><i data-lucide="file-text"></i></div>
						<div class="dp-mod-info">
							<div class="dp-mod-name">Reportes e Informes</div>
							<div class="dp-mod-desc">Despachos, entregas y devoluciones por sucursal y periodo</div>
						</div>
						<div class="dp-mod-arrow"><i data-lucide="arrow-right"></i></div>
					</a>
				</div>
			</div>
		</div>
	</section>

</div>
