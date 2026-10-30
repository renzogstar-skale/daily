@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Panel Ejecutivo · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_executive_panel.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@php
	$ep = $executivePanel ?? [];
	$sKpis = $ep['strategic_kpis'] ?? [];
	$decisions = $ep['priority_decisions'] ?? [];
	$budget = $ep['budget_summary'] ?? [];
	$ops = $ep['operations_status'] ?? [];
	$evoChart = $ep['evolution_chart'] ?? ['labels' => [], 'sales_real' => [], 'target_line' => [], 'prev_period' => []];
	$dq = $ep['data_quality'] ?? [];
	$branchRanking = $kpiData['branch_ranking'] ?? [];

	// Helper para preservar el contexto de sucursal y período al navegar a módulos de detalle
	$ctxUrl = function(string $path) use ($U, $selectedBranchId, $selectedPeriod, $customStart, $customEnd, $selectedDate) {
		$hash = '';
		if (strpos($path, '#') !== false)
		{
			[$path, $hash] = explode('#', $path, 2);
			$hash = '#' . $hash;
		}
		$query = [];
		if (!empty($selectedBranchId))
		{
			$query['branch_id'] = $selectedBranchId;
		}
		if (!empty($selectedPeriod))
		{
			$query['period'] = $selectedPeriod;
		}
		// Fecha de referencia compartida con HOY (solo si no es hoy)
		if (!empty($selectedDate) && $selectedDate !== date('Y-m-d'))
		{
			$query['date'] = $selectedDate;
		}
		if ($selectedPeriod === 'custom' && !empty($customStart) && !empty($customEnd))
		{
			$query['start_date'] = $customStart;
			$query['end_date'] = $customEnd;
		}
		$sep = (strpos($path, '?') !== false) ? '&' : '?';
		$qs = !empty($query) ? ($sep . http_build_query($query)) : '';
		return $U($path . $qs . $hash);
	};

	$isPastDate = !empty($selectedDate) && $selectedDate !== date('Y-m-d');
	$periodLabels = [
		'day' => $isPastDate ? 'Día ' . date('d/m/Y', strtotime($selectedDate)) : 'Hoy',
		'week' => 'Esta semana',
		'month' => 'Este mes',
		'quarter' => 'Este trimestre',
		'year' => 'Este año',
		'custom' => 'Rango personalizado'
	];
@endphp

@section('content')
<div class="exec-panel-wrapper" id="daily-executive-panel">

	<!-- ====================================================================
	     1. CABECERA Y BARRA SUPERIOR DE FILTROS
	     ==================================================================== -->
	<header class="exec-top-bar">
		<div class="exec-title-block">
			<h1>Panel Ejecutivo</h1>
			<p>Visión integral de ventas, rentabilidad y operación de la cadena.</p>
		</div>

		<div class="exec-controls-bar">
			<form method="GET" action="{{ $U('/kpis') }}" id="exec-filter-form" class="d-flex align-items-center flex-wrap" style="gap:8px; margin:0;">
				@if($isPastDate)<input type="hidden" name="date" value="{{ $selectedDate }}">@endif
				<!-- Selector de Sucursal o Red Consolidada -->
				<div class="exec-pill-control" title="Filtrar por sucursal o ver red consolidada">
					<i class="fa-solid fa-store text-success"></i>
					<select name="branch_id" id="exec-select-branch" onchange="this.form.submit()">
						<option value="">Red Consolidada</option>
						@foreach($branches as $b)
							@php
								$bId = is_array($b) ? $b['id'] : $b->id;
								$bName = is_array($b) ? $b['name'] : $b->name;
							@endphp
							<option value="{{ $bId }}" @if((int)$selectedBranchId === (int)$bId) selected @endif>
								{{ trim(str_replace('[DEMO]', '', $bName)) }}
							</option>
						@endforeach
					</select>
				</div>

				<!-- Selector de Período -->
				<div class="exec-pill-control" title="Seleccionar período de análisis">
					<i class="fa-regular fa-calendar text-success"></i>
					<select name="period" id="exec-select-period" onchange="onExecPeriodChange(this.value)">
						@foreach($periodLabels as $pKey => $pLabel)
							<option value="{{ $pKey }}" @if($selectedPeriod === $pKey) selected @endif>{{ $pLabel }}</option>
						@endforeach
					</select>
				</div>

				<!-- Rango personalizado (visible cuando period == custom) -->
				<div id="exec-custom-dates" class=" align-items-center @if($selectedPeriod === 'custom') d-inline-flex @else d-none @endif" style="gap:6px;">
					<input type="date" name="start_date" value="{{ $customStart ?? $kpiData['start_date'] }}" class="form-control form-control-sm" style="height:38px; border-radius:9px; font-size:0.78rem;">
					<span class="text-muted small">a</span>
					<input type="date" name="end_date" value="{{ $customEnd ?? $kpiData['end_date'] }}" class="form-control form-control-sm" style="height:38px; border-radius:9px; font-size:0.78rem;">
					<button type="submit" class="btn btn-sm btn-success font-weight-bold" style="height:38px; border-radius:9px;">Aplicar</button>
				</div>
			</form>

			<!-- Fecha y hora de la última actualización disponible -->
			<div class="exec-update-pill" title="Fecha del último registro procesado en la base local ({{ $dq['source_label'] ?? 'SQLite local' }})">
				<span class="exec-update-dot"></span>
				<div>
					<div style="font-size:0.65rem; color:#64748b; line-height:1;">Última actualización</div>
					<div style="font-weight:700; color:#0f172a; line-height:1.15;">{{ $dq['last_updated_formatted'] ?? 'Sin fecha' }}</div>
				</div>
			</div>

			<!-- Acceso directo al Centro de Alertas -->
			<a href="{{ $ctxUrl('/alerts') }}" class="exec-btn-alerts" title="Abrir Centro de Alertas y seguimiento">
				<i class="fa-solid fa-bell"></i>
				<span>Alertas ({{ $alertCount ?? 0 }})</span>
			</a>

			<!-- Exportación local -->
			<div class="dropdown d-inline-block">
				<button type="button" class="exec-btn-export dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-download"></i>
					<span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.81rem;">
					<a class="dropdown-item py-2" href="#" id="btn-exec-export-csv">
						<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar resumen ejecutivo (CSV)
					</a>
					<a class="dropdown-item py-2" href="#" id="btn-exec-export-tsv">
						<i class="fa-solid fa-file-excel text-primary mr-2"></i>Exportar para Excel (TSV)
					</a>
					<a class="dropdown-item py-2" href="#" onclick="window.print(); return false;">
						<i class="fa-solid fa-print text-secondary mr-2"></i>Imprimir / Guardar PDF
					</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item py-2" href="{{ $ctxUrl('/reports') }}">
						<i class="fa-solid fa-chart-pie text-success mr-2"></i>Ir al Centro de Reportes
					</a>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     2. INDICADORES ESTRATÉGICOS (4 TARJETAS PRINCIPALES)
	     ==================================================================== -->
	@php
		$kSales = $sKpis['sales'] ?? [];
		$kMargin = $sKpis['margin'] ?? [];
		$kWaste = $sKpis['waste'] ?? [];
		$kTurn = $sKpis['turnover'] ?? [];
	@endphp
	<section class="exec-strategic-grid" aria-label="Indicadores estratégicos">
		<!-- KPI 1: Ventas totales vs. meta -->
		<a href="{{ $ctxUrl($kSales['url'] ?? '/sales') }}" class="exec-kpi-card" title="Abrir análisis detallado en Ventas y Rentabilidad">
			<div>
				<div class="exec-kpi-top">
					<div class="exec-kpi-icon-title">
						<div class="exec-kpi-icon tone-green">
							<i class="fa-solid fa-chart-simple"></i>
						</div>
						<div>
							<div class="exec-kpi-name">Ventas netas</div>
							<div class="exec-kpi-sublabel">vs. meta</div>
						</div>
					</div>
					@if(!empty($kSales['has_data']) && $kSales['delta_pct'] !== null)
						<span class="exec-kpi-delta {{ $kSales['is_favorable'] ? 'is-good' : 'is-bad' }}">
							{{ ($kSales['delta_pct'] >= 0 ? '+' : '') . number_format($kSales['delta_pct'], 1) }}%
						</span>
					@else
						<span class="exec-kpi-delta is-none">Sin información</span>
					@endif
				</div>

				<div class="exec-kpi-value">
					@if(!empty($kSales['has_data']))
						${{ number_format($kSales['value'], 2) }}
					@else
						Sin información
					@endif
				</div>

				<div class="exec-kpi-target-row">
					<span>
						Meta: {{ !empty($kSales['target']) ? '$' . number_format($kSales['target'], 2) : 'Sin meta' }}
					</span>
					<span class="text-success font-weight-bold" style="font-size:0.71rem;">
						Ver ventas <i class="fa-solid fa-angle-right"></i>
					</span>
				</div>
			</div>

			<div>
				<div class="exec-kpi-spark-wrap">
					<canvas id="execSparkSales"></canvas>
				</div>
				<div class="exec-kpi-days-tag">{{ $kSales['days_label'] ?? '28 días' }}</div>
			</div>
		</a>

		<!-- KPI 2: Margen bruto vs. meta -->
		<a href="{{ $ctxUrl($kMargin['url'] ?? '/sales') }}" class="exec-kpi-card" title="Abrir análisis de margen y costos en Ventas y Rentabilidad">
			<div>
				<div class="exec-kpi-top">
					<div class="exec-kpi-icon-title">
						<div class="exec-kpi-icon tone-amber">
							<i class="fa-solid fa-coins"></i>
						</div>
						<div>
							<div class="exec-kpi-name">Margen bruto</div>
							<div class="exec-kpi-sublabel">vs. meta ({{ number_format($kMargin['margin_pct'] ?? 0, 1) }}%)</div>
						</div>
					</div>
					@if(!empty($kMargin['has_data']) && $kMargin['delta_pct'] !== null)
						<span class="exec-kpi-delta {{ $kMargin['is_favorable'] ? 'is-good' : 'is-bad' }}">
							{{ ($kMargin['delta_pct'] >= 0 ? '+' : '') . number_format($kMargin['delta_pct'], 1) }}%
						</span>
					@else
						<span class="exec-kpi-delta is-none">Sin información</span>
					@endif
				</div>

				<div class="exec-kpi-value">
					@if(!empty($kMargin['has_data']))
						${{ number_format($kMargin['value'], 2) }}
					@else
						Sin información
					@endif
				</div>

				<div class="exec-kpi-target-row">
					<span>
						Meta: {{ !empty($kMargin['target']) ? '$' . number_format($kMargin['target'], 2) : 'Sin meta' }}
					</span>
					@if(!empty($kMargin['is_partial']))
						<span class="text-warning font-weight-bold" title="Faltan costos unitarios en {{ $kMargin['lines_without_cost'] }} línea(s) de venta; el margen mostrado es parcial ({{ $kMargin['coverage_pct'] }}% con CMV).">
							<i class="fa-solid fa-triangle-exclamation"></i> CMV {{ $kMargin['coverage_pct'] }}%
						</span>
					@else
						<span class="text-success font-weight-bold" style="font-size:0.71rem;">
							Ver margen <i class="fa-solid fa-angle-right"></i>
						</span>
					@endif
				</div>
			</div>

			<div>
				<div class="exec-kpi-spark-wrap">
					<canvas id="execSparkMargin"></canvas>
				</div>
				<div class="exec-kpi-days-tag">{{ $kMargin['days_label'] ?? '28 días' }}</div>
			</div>
		</a>

		<!-- KPI 3: Merma total vs. meta -->
		<a href="{{ $ctxUrl($kWaste['url'] ?? '/waste') }}" class="exec-kpi-card" title="Abrir control y causales en Mermas y Prevención">
			<div>
				<div class="exec-kpi-top">
					<div class="exec-kpi-icon-title">
						<div class="exec-kpi-icon tone-red">
							<i class="fa-solid fa-basket-shopping"></i>
						</div>
						<div>
							<div class="exec-kpi-name">Merma total</div>
							<div class="exec-kpi-sublabel">vs. meta ({{ number_format($kWaste['ratio_pct'] ?? 0, 2) }}% s/venta)</div>
						</div>
					</div>
					@if(!empty($kWaste['has_data']) && $kWaste['delta_pct'] !== null)
						<span class="exec-kpi-delta {{ $kWaste['is_favorable'] ? 'is-good' : 'is-bad' }}">
							{{ ($kWaste['delta_pct'] >= 0 ? '+' : '') . number_format($kWaste['delta_pct'], 1) }}%
						</span>
					@else
						<span class="exec-kpi-delta is-none">Sin información</span>
					@endif
				</div>

				<div class="exec-kpi-value">
					@if(!empty($kWaste['has_data']))
						${{ number_format($kWaste['value'], 2) }}
					@else
						Sin información
					@endif
				</div>

				<div class="exec-kpi-target-row">
					<span>
						Meta: {{ !empty($kWaste['target']) ? '$' . number_format($kWaste['target'], 2) : '≤ ' . number_format($kWaste['target_ratio_pct'] ?? 0, 1) . '%' }}
					</span>
					<span class="text-danger font-weight-bold" style="font-size:0.71rem;">
						Ver mermas <i class="fa-solid fa-angle-right"></i>
					</span>
				</div>
			</div>

			<div>
				<div class="exec-kpi-spark-wrap">
					<canvas id="execSparkWaste"></canvas>
				</div>
				<div class="exec-kpi-days-tag">{{ $kWaste['days_label'] ?? '28 días' }}</div>
			</div>
		</a>

		<!-- KPI 4: Rotación de inventario vs. meta -->
		<a href="{{ $ctxUrl($kTurn['url'] ?? '/stockoverview') }}" class="exec-kpi-card" title="Abrir Stock & Movimientos">
			<div>
				<div class="exec-kpi-top">
					<div class="exec-kpi-icon-title">
						<div class="exec-kpi-icon tone-blue">
							<i class="fa-solid fa-cube"></i>
						</div>
						<div>
							<div class="exec-kpi-name">Rotación de inventario</div>
							<div class="exec-kpi-sublabel">vs. meta {{ !empty($kTurn['dsi']) ? '· DSI ' . $kTurn['dsi'] . 'd' : '' }}</div>
						</div>
					</div>
					@if(!empty($kTurn['has_data']) && $kTurn['delta_pct'] !== null)
						<span class="exec-kpi-delta {{ $kTurn['is_favorable'] ? 'is-good' : 'is-bad' }}">
							{{ ($kTurn['delta_pct'] >= 0 ? '+' : '') . number_format($kTurn['delta_pct'], 1) }}%
						</span>
					@else
						<span class="exec-kpi-delta is-none">Sin información</span>
					@endif
				</div>

				<div class="exec-kpi-value">
					@if(!empty($kTurn['has_data']))
						{{ number_format($kTurn['value'], 2) }}x
					@else
						Sin información
					@endif
				</div>

				<div class="exec-kpi-target-row">
					<span>
						Meta: {{ !empty($kTurn['target']) ? number_format($kTurn['target'], 2) . 'x' : 'Sin meta' }}
					</span>
					<span class="text-primary font-weight-bold" style="font-size:0.71rem;">
						Ver stock <i class="fa-solid fa-angle-right"></i>
					</span>
				</div>
			</div>

			<div>
				<div class="exec-kpi-spark-wrap">
					<canvas id="execSparkTurn"></canvas>
				</div>
				<div class="exec-kpi-days-tag">{{ $kTurn['days_label'] ?? '28 días' }}</div>
			</div>
		</a>
	</section>

	<!-- ====================================================================
	     3. DECISIONES PRIORITARIAS (IZQUIERDA) + PRESUPUESTO Y COMPROMISOS (DERECHA)
	     ==================================================================== -->
	<div class="exec-mid-grid">
		<!-- Columna Izquierda: Decisiones Prioritarias -->
		<section class="exec-section-card" aria-label="Decisiones prioritarias">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-shield-check fa-check"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Decisiones prioritarias</h2>
						<p class="exec-section-subtitle">Acciones que requieren atención para asegurar el cumplimiento de objetivos.</p>
					</div>
				</div>
				<a href="{{ $ctxUrl('/alerts') }}" class="exec-link-green">
					Ver todas las alertas <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>

			<div class="exec-decisions-grid">
				@foreach($decisions as $dec)
					<div class="exec-decision-card tone-{{ $dec['tone'] ?? 'amber' }}">
						<div>
							<div class="d-flex justify-content-between align-items-center mb-2">
								<span class="badge badge-light border font-weight-bold" style="font-size:0.66rem;">
									{{ $dec['priority_badge'] ?? 'P2 · Alta' }} · {{ $dec['status_badge'] ?? 'Abierta' }}
								</span>
								<span class="text-muted" style="font-size:0.65rem; font-weight:600;">
									{{ $dec['branch_or_process'] ?? 'General' }}
								</span>
							</div>

							<div class="exec-dec-head">
								<div class="exec-dec-icon">
									<i class="fa-solid {{ $dec['icon'] ?? 'fa-triangle-exclamation' }}"></i>
								</div>
								<h3 class="exec-dec-title">{{ $dec['title'] }}</h3>
							</div>

							<p class="exec-dec-desc">{{ $dec['description'] }}</p>
						</div>

						<div>
							<div class="exec-dec-meta">
								<div class="exec-dec-meta-row">
									<i class="fa-solid fa-user-tie text-secondary" style="width:14px;"></i>
									<span>Responsable: <strong>{{ !empty($dec['responsible']) ? $dec['responsible'] : 'Sin asignar' }}</strong></span>
								</div>
								<div class="exec-dec-meta-row">
									<i class="fa-regular fa-calendar-check text-secondary" style="width:14px;"></i>
									<span>Fecha límite: <strong>{{ !empty($dec['due_date']) ? $dec['due_date'] : 'Sin fecha' }}</strong></span>
								</div>
							</div>

							<a href="{{ $ctxUrl($dec['url'] ?? '/alerts') }}" class="exec-dec-btn">
								<span>{{ $dec['action_label'] ?? 'Ver detalle' }}</span>
								<i class="fa-solid fa-arrow-right"></i>
							</a>
						</div>
					</div>
				@endforeach
			</div>
		</section>

		<!-- Columna Derecha: Presupuesto y Compromisos -->
		<section class="exec-section-card" aria-label="Presupuesto y compromisos">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-coins"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Presupuesto y compromisos</h2>
						<p class="exec-section-subtitle">Ejecución del presupuesto, gasto y compromisos (USD).</p>
					</div>
				</div>
				<span class="badge badge-light border px-2 py-1 font-weight-bold" style="font-size:0.72rem; border-radius:7px;">
					{{ $budget['period_label'] ?? 'Este mes' }}
				</span>
			</div>

			@if(!empty($budget['has_data']))
				<div class="exec-budget-rows">
					<!-- Fila 1: Presupuesto total -->
					<div class="exec-budget-row">
						<div class="exec-budget-label">Presupuesto total</div>
						<div class="exec-budget-bar-wrap">
							<div class="exec-budget-top-val">
								<span>${{ number_format($budget['total_budget'], 2) }}</span>
								<span style="font-size:0.76rem;">{{ $budget['executed_pct'] }}%</span>
							</div>
							<div class="exec-budget-track">
								<div class="exec-budget-fill is-green" style="width: {{ min(100, $budget['executed_pct']) }}%;"></div>
							</div>
							<div class="exec-budget-sub">
								<span>Ejecutado: ${{ number_format($budget['executed_total'], 2) }}</span>
								<span>Restante: ${{ number_format($budget['remaining_budget'], 2) }}</span>
							</div>
						</div>
					</div>

					<!-- Fila 2: Gasto comprometido (OC vigentes no facturadas) -->
					<div class="exec-budget-row">
						<div class="exec-budget-label">Gasto comprometido<br><small class="text-muted">(OC sin facturar)</small></div>
						<div class="exec-budget-bar-wrap">
							<div class="exec-budget-top-val">
								<span>${{ number_format($budget['committed_oc_total'], 2) }}</span>
								<span style="font-size:0.76rem;">{{ $budget['committed_bar_pct'] }}%</span>
							</div>
							<div class="exec-budget-track">
								<div class="exec-budget-fill is-amber" style="width: {{ min(100, $budget['committed_bar_pct']) }}%;"></div>
							</div>
							<div class="exec-budget-sub">
								<span>En tránsito: ${{ number_format($budget['committed_in_transit'], 2) }}</span>
								<span>Por entregar: ${{ number_format($budget['committed_pending_delivery'], 2) }}</span>
							</div>
						</div>
					</div>

					<!-- Fila 3: Gasto facturado -->
					<div class="exec-budget-row">
						<div class="exec-budget-label">{{ !empty($budget['is_derived']) ? 'Recibido' : 'Gasto facturado' }}</div>
						<div class="exec-budget-bar-wrap">
							<div class="exec-budget-top-val">
								<span>${{ number_format($budget['invoiced_total'], 2) }}</span>
								<span style="font-size:0.76rem;">{{ $budget['invoiced_bar_pct'] }}%</span>
							</div>
							<div class="exec-budget-track">
								<div class="exec-budget-fill is-blue" style="width: {{ min(100, $budget['invoiced_bar_pct']) }}%;"></div>
							</div>
							<div class="exec-budget-sub">
								<span>{{ !empty($budget['is_derived']) ? 'Facturado' : 'Pagado' }}: ${{ number_format($budget['invoiced_paid'], 2) }}</span>
								<span>{{ !empty($budget['is_derived']) ? 'Sin factura' : 'Por pagar' }}: ${{ number_format($budget['invoiced_pending'], 2) }}</span>
							</div>
						</div>
					</div>
				</div>

				<div class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between flex-wrap" style="gap:6px;">
					<span class="text-muted" style="font-size:0.68rem;">
						<i class="fa-solid fa-circle-check text-success mr-1"></i>{{ $budget['reconciliation_note'] ?? 'Conciliado: no duplica OC con factura.' }}
					</span>
					<a href="{{ $ctxUrl($budget['url'] ?? '/suppliers#tab-budget') }}" class="exec-link-green" style="font-size:0.74rem;">
						Ver presupuesto y compras <i class="fa-solid fa-arrow-right ml-1"></i>
					</a>
				</div>
			@else
				<div class="my-auto text-center py-4 text-muted">
					<i class="fa-regular fa-folder-open fa-2x mb-2"></i>
					<div class="font-weight-bold text-dark small">Sin información presupuestaria para esta sucursal</div>
					<div class="small mt-1 mb-3">{{ $budget['reconciliation_note'] ?? 'Esta sucursal no tiene presupuesto de compras registrado en el período.' }}</div>
					<a href="{{ $ctxUrl('/suppliers#tab-budget') }}" class="btn btn-sm btn-outline-success font-weight-bold" style="border-radius:8px;">
						Ir a Planeación y Compras
					</a>
				</div>
			@endif
		</section>
	</div>

	<!-- ====================================================================
	     4. ESTADO DE LA OPERACIÓN (7 PROCESOS CLAVE)
	     ==================================================================== -->
	<section class="exec-section-card mb-3" aria-label="Estado de la operación">
		<div class="exec-section-head">
			<div class="exec-section-title-wrap">
				<div class="exec-section-icon">
					<i class="fa-solid fa-gear"></i>
				</div>
				<div>
					<h2 class="exec-section-title">Estado de la operación</h2>
					<p class="exec-section-subtitle">Procesos clave del supermercado. Desviación versus meta del período.</p>
				</div>
			</div>

			<div class="exec-ops-legend">
				<span><span class="exec-legend-dot" style="background:#16a34a;"></span>En objetivo</span>
				<span><span class="exec-legend-dot" style="background:#f59e0b;"></span>Atención</span>
				<span><span class="exec-legend-dot" style="background:#dc2626;"></span>Crítico</span>
				<span><span class="exec-legend-dot" style="background:#94a3b8;"></span>Sin información</span>
			</div>
		</div>

		<div class="exec-ops-grid">
			@foreach($ops as $idx => $op)
				<a href="{{ $ctxUrl($op['url']) }}" class="exec-op-card" title="Abrir {{ $op['full_name'] }}">
					<div>
						<div class="exec-op-top">
							<span class="exec-op-name">
								<i class="fa-solid {{ $op['icon'] }} text-secondary"></i>
								<span>{{ $op['name'] }}</span>
							</span>
							<span class="exec-op-badge state-{{ $op['state'] }}">
								{{ $op['state_label'] }}
							</span>
						</div>

						<div class="exec-op-metric-row">
							<span class="exec-op-metric state-{{ $op['state'] }}">{{ $op['metric'] }}</span>
							@if($op['state'] !== 'no_data')
								<i class="fa-solid {{ $op['trend'] === 'up' ? 'fa-arrow-up-right-dots text-success' : 'fa-arrow-trend-down text-danger' }}" style="font-size:0.75rem;"></i>
							@else
								<i class="fa-solid fa-minus text-muted" style="font-size:0.72rem;"></i>
							@endif
						</div>

						<div class="exec-op-sub" title="{{ $op['subtitle'] }}">{{ $op['subtitle'] }}</div>
					</div>

					<div class="exec-op-spark">
						<canvas id="execOpSpark{{ $idx }}"></canvas>
					</div>
				</a>
			@endforeach
		</div>
	</section>

	<!-- ====================================================================
	     5. EVOLUCIÓN CONSOLIDADA (IZQUIERDA) + DESEMPEÑO POR SUCURSAL (DERECHA)
	     ==================================================================== -->
	<div class="exec-analytics-grid">
		<!-- Izquierda: Evolución de ventas vs. meta -->
		<section class="exec-section-card" aria-label="Evolución de ventas frente a meta">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-chart-column"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Evolución de ventas vs. meta</h2>
						<p class="exec-section-subtitle">Ventas diarias consolidadas frente a la meta y período comparable.</p>
					</div>
				</div>

				<div class="d-flex align-items-center flex-wrap" style="gap:10px;">
					<div class="d-flex align-items-center small text-muted" style="gap:10px; font-size:0.72rem;">
						<span><i class="fa-solid fa-square text-success mr-1"></i>Ventas reales</span>
						<span><strong style="color:#475569;">--</strong> Meta</span>
						<span><i class="fa-solid fa-square mr-1" style="color:#cbd5e1;"></i>Período anterior</span>
					</div>

					<select id="exec-chart-range-select" class="form-control form-control-sm" style="width:auto; height:30px; font-size:0.74rem; font-weight:700; border-radius:8px;">
						<option value="all">Período actual ({{ count($evoChart['labels'] ?? []) }} pts)</option>
						<option value="last14">Últimos 14 puntos</option>
					</select>
				</div>
			</div>

			@if(($sKpis['sales']['has_data'] ?? false) && count($evoChart['labels'] ?? []) > 0)
				@php
					$evoSales = array_map('floatval', array_values($evoChart['sales_real'] ?? []));
					$evoTarget = array_map('floatval', array_values($evoChart['target_line'] ?? []));
					$evoPrev = array_map('floatval', array_values($evoChart['prev_period'] ?? []));
					$evoLabels = array_values($evoChart['labels'] ?? []);
					$evoTotal = array_sum($evoSales);
					$evoTargetTotal = array_sum($evoTarget);
					$evoPrevTotal = array_sum($evoPrev);
					$evoDays = max(1, count(array_filter($evoSales, fn($v) => $v > 0)));
					$evoAvg = $evoTotal / $evoDays;
					$evoCompliance = $evoTargetTotal > 0 ? $evoTotal / $evoTargetTotal * 100 : null;
					$evoVsPrev = $evoPrevTotal > 0 ? ($evoTotal - $evoPrevTotal) / $evoPrevTotal * 100 : null;
					$evoBestIdx = $evoSales ? array_search(max($evoSales), $evoSales) : null;
					$evoWorstPos = array_filter($evoSales, fn($v) => $v > 0);
					$evoWorstIdx = $evoWorstPos ? array_search(min($evoWorstPos), $evoSales) : null;
					$evoAbove = 0;
					foreach ($evoSales as $i => $v) { if (isset($evoTarget[$i]) && $evoTarget[$i] > 0 && $v >= $evoTarget[$i]) $evoAbove++; }
					$evoWithTarget = count(array_filter($evoTarget, fn($v) => $v > 0));
				@endphp
				<div style="flex:1 1 auto; min-height:255px; position:relative;">
					<canvas id="execEvolutionChart"></canvas>
				</div>

				<div class="exec-evo-summary">
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Ventas del período</span>
						<span class="exec-evo-value">${{ number_format($evoTotal, 0) }}</span>
						<span class="exec-evo-sub">
							@if($evoVsPrev !== null)
								<span class="{{ $evoVsPrev >= 0 ? 'text-success' : 'text-danger' }}">{{ $evoVsPrev >= 0 ? '↑ +' : '↓ ' }}{{ number_format($evoVsPrev, 1) }}%</span> vs. período anterior
							@else
								Sin período comparable
							@endif
						</span>
					</div>
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Cumplimiento de meta</span>
						<span class="exec-evo-value {{ $evoCompliance === null ? '' : ($evoCompliance >= 100 ? 'text-success' : 'text-danger') }}">{{ $evoCompliance === null ? 'Sin meta' : number_format($evoCompliance, 1) . '%' }}</span>
						<span class="exec-evo-sub">Meta: ${{ number_format($evoTargetTotal, 0) }}</span>
					</div>
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Promedio diario</span>
						<span class="exec-evo-value">${{ number_format($evoAvg, 0) }}</span>
						<span class="exec-evo-sub">{{ $evoDays }} día(s) con ventas</span>
					</div>
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Días sobre meta</span>
						<span class="exec-evo-value">{{ $evoWithTarget ? $evoAbove . ' / ' . $evoWithTarget : '—' }}</span>
						<span class="exec-evo-sub">{{ $evoWithTarget ? round($evoAbove / $evoWithTarget * 100) . '% de los días' : 'Sin meta registrada' }}</span>
					</div>
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Mejor día</span>
						<span class="exec-evo-value">{{ $evoBestIdx !== null ? ($evoLabels[$evoBestIdx] ?? '—') : '—' }}</span>
						<span class="exec-evo-sub">{{ $evoBestIdx !== null ? '$' . number_format($evoSales[$evoBestIdx], 0) : '' }}</span>
					</div>
					<div class="exec-evo-stat">
						<span class="exec-evo-label">Día más bajo</span>
						<span class="exec-evo-value">{{ $evoWorstIdx !== null ? ($evoLabels[$evoWorstIdx] ?? '—') : '—' }}</span>
						<span class="exec-evo-sub">{{ $evoWorstIdx !== null ? '$' . number_format($evoSales[$evoWorstIdx], 0) : '' }}</span>
					</div>
				</div>
			@else
				<div class="my-auto text-center py-5 text-muted">
					<i class="fa-solid fa-chart-column fa-2x mb-2"></i>
					<div class="font-weight-bold text-dark small">Sin registros de ventas en el período o sucursal seleccionada</div>
					<div class="small mt-1">Selecciona «Red Consolidada» o cambia el período para visualizar la curva comparativa.</div>
				</div>
			@endif
		</section>

		<!-- Derecha: Desempeño por sucursal -->
		<section class="exec-section-card" aria-label="Desempeño por sucursal">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-location-dot"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Desempeño por sucursal</h2>
						<p class="exec-section-subtitle">Resumen de las principales sucursales.</p>
					</div>
				</div>

				<div class="d-flex align-items-center" style="gap:6px;">
					<select id="exec-branch-metric-select" class="form-control form-control-sm" style="width:auto; height:30px; font-size:0.74rem; font-weight:700; border-radius:8px;">
						<option value="sales">Ventas del período</option>
						<option value="margin">Margen bruto</option>
						<option value="waste">Merma (%)</option>
					</select>
				</div>
			</div>

			<div class="table-responsive">
				<table class="exec-branch-table" id="exec-branch-summary-table">
					<thead>
						<tr>
							<th>SUCURSAL</th>
							<th class="text-right" id="th-branch-metric">VENTAS</th>
							<th class="text-right">VS. META</th>
							<th class="text-center">ESTADO</th>
							<th class="text-right">TENDENCIA</th>
						</tr>
					</thead>
					<tbody>
						@foreach($branchRanking as $br)
							@php
								$isHiddenInactive = empty($br['has_activity']) && (int)$selectedBranchId !== (int)$br['branch_id'];
								$stateClass = $br['exec_state'] ?? 'no_data';
								$strokeColor = $stateClass === 'ok' ? '#16a34a' : ($stateClass === 'warn' ? '#d97706' : ($stateClass === 'crit' ? '#dc2626' : '#94a3b8'));
								$pts = $br['trend_sales'] ?? [];
								$maxPt = !empty($pts) ? max(max($pts), 1) : 1;
								$svgPoints = [];
								if (!empty($pts) && !empty($br['has_activity']))
								{
									foreach (array_values($pts) as $i => $v)
									{
										$x = round(($i / max(1, count($pts) - 1)) * 68, 1) + 2;
										$y = round(22 - (($v / $maxPt) * 18), 1);
										$svgPoints[] = "{$x},{$y}";
									}
								}
								else
								{
									$svgPoints = ['2,14', '70,14'];
								}
							@endphp
							<tr class="@if((int)$selectedBranchId === (int)$br['branch_id']) is-selected-branch @endif @if($isHiddenInactive) branch-inactive-row d-none @endif"
							    data-branch-id="{{ $br['branch_id'] }}"
							    data-sales="${{ number_format($br['sales'], 2) }}"
							    data-margin="${{ number_format($br['gross_margin'], 2) }}"
							    data-waste="${{ number_format($br['waste_cost'], 2) }} ({{ number_format($br['waste_ratio_pct'], 1) }}%)"
							    onclick="window.location.href='{{ $U('/kpis?period=' . $selectedPeriod . '&branch_id=' . $br['branch_id'] . ($isPastDate ? '&date=' . $selectedDate : '')) }}'"
							    title="Clic para filtrar el Panel Ejecutivo por {{ $br['clean_name'] }}">
								<td>
									<div class="d-flex align-items-center" style="gap:8px;">
										<span class="d-inline-flex align-items-center justify-content-center" style="width:24px; height:24px; border-radius:6px; background:{{ $stateClass === 'ok' ? '#dcfce7' : ($stateClass === 'warn' ? '#fef3c7' : ($stateClass === 'crit' ? '#fee2e2' : '#f1f5f9')) }}; color:{{ $strokeColor }}; font-size:0.72rem;">
											<i class="fa-solid fa-store"></i>
										</span>
										<span class="font-weight-bold text-dark">{{ $br['clean_name'] }}</span>
									</div>
								</td>
								<td class="text-right font-weight-bold text-dark branch-metric-cell">
									@if(!empty($br['has_activity']))
										${{ number_format($br['sales'], 2) }}
									@else
										<span class="text-muted font-weight-normal">$0.00</span>
									@endif
								</td>
								<td class="text-right font-weight-bold">
									@if($br['vs_meta_pct'] !== null)
										<span class="{{ $br['vs_meta_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
											{{ ($br['vs_meta_pct'] >= 0 ? '+' : '') . number_format($br['vs_meta_pct'], 1) }}%
										</span>
									@else
										<span class="text-muted small">Sin meta / actividad</span>
									@endif
								</td>
								<td class="text-center">
									<span class="exec-op-badge state-{{ $stateClass }}">
										● {{ $br['exec_state_label'] }}
									</span>
								</td>
								<td class="text-right">
									<svg width="72" height="24" viewBox="0 0 72 24" aria-hidden="true">
										<polyline fill="none" stroke="{{ $strokeColor }}" stroke-width="2" points="{{ implode(' ', $svgPoints) }}" />
									</svg>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<div class="pt-2 mt-auto border-top d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
				<button type="button" class="btn btn-link btn-sm p-0 text-secondary font-weight-bold" id="btn-toggle-inactive-branches" style="font-size:0.73rem; text-decoration:none;">
					<i class="fa-regular fa-eye mr-1"></i><span>Mostrar sucursales sin actividad en el período</span>
				</button>
				@if(!empty($selectedBranchId))
					<a href="{{ $U('/kpis?period=' . $selectedPeriod . ($isPastDate ? '&date=' . $selectedDate : '')) }}" class="exec-link-green" style="font-size:0.74rem;">
						Volver a Red Consolidada
					</a>
				@else
					<a href="{{ $ctxUrl('/branches') }}" class="exec-link-green" style="font-size:0.74rem;">
						Ver todas las sucursales <i class="fa-solid fa-arrow-right ml-1"></i>
					</a>
				@endif
			</div>
		</section>
	</div>

	<!-- ====================================================================
	     6. CALIDAD Y COBERTURA DE LOS DATOS (IZQUIERDA) + ACCESOS RÁPIDOS (DERECHA)
	     ==================================================================== -->
	<div class="exec-bottom-grid">
		<!-- Izquierda: Calidad y cobertura de los datos -->
		<section class="exec-section-card" aria-label="Calidad y cobertura de los datos">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-database"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Calidad y cobertura de los datos</h2>
						<p class="exec-section-subtitle">Estado de la información utilizada en este panel ({{ $dq['source_label'] ?? 'Base local SQLite' }}).</p>
					</div>
				</div>
				<span class="badge badge-light border px-2 py-1 text-secondary" style="font-size:0.71rem; border-radius:7px;">
					<i class="fa-regular fa-clock mr-1"></i>{{ $dq['last_updated_formatted'] ?? 'Sin fecha' }}
				</span>
			</div>

			<div class="exec-dq-grid">
				<div class="exec-dq-item">
					<div class="exec-dq-label">
						<i class="fa-regular fa-clock text-success"></i>
						<span>Última lectura</span>
					</div>
					<div class="exec-dq-val">{{ $dq['last_updated_formatted'] ?? 'Sin registros' }}</div>
					<div class="exec-dq-sub">Consulta local bajo demanda (no tiempo real)</div>
				</div>

				<div class="exec-dq-item">
					<div class="exec-dq-label">
						<i class="fa-solid fa-boxes-stacked text-primary"></i>
						<span>Inventario</span>
					</div>
					<div class="exec-dq-val">
						{{ $dq['stock_cost_coverage_pct'] !== null ? $dq['stock_cost_coverage_pct'] . '% con costo' : 'Sin lotes' }}
					</div>
					<div class="exec-dq-sub">
						{{ $dq['stock_products_with_stock'] ?? 0 }}/{{ $dq['stock_active_products'] ?? 0 }} SKUs con stock ({{ $dq['stock_products_out'] ?? 0 }} agotados)
					</div>
				</div>

				<div class="exec-dq-item">
					<div class="exec-dq-label">
						<i class="fa-solid fa-chart-simple {{ ($dq['sales_lines_without_cost'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}"></i>
						<span>Ventas (CMV)</span>
					</div>
					<div class="exec-dq-val">
						{{ $dq['sales_cost_coverage_pct'] !== null ? $dq['sales_cost_coverage_pct'] . '% con CMV' : 'Sin ventas' }}
					</div>
					<div class="exec-dq-sub">
						@if(($dq['sales_lines_without_cost'] ?? 0) > 0)
							<span class="text-danger font-weight-bold">{{ $dq['sales_lines_without_cost'] }} línea(s) sin costo</span> (afecta margen)
						@elseif(($dq['sales_total_lines'] ?? 0) > 0)
							100% de líneas con costo
						@else
							0 líneas en el período
						@endif
					</div>
				</div>

				<div class="exec-dq-item">
					<div class="exec-dq-label">
						<i class="fa-solid fa-cart-shopping text-success"></i>
						<span>Compras</span>
					</div>
					<div class="exec-dq-val">{{ $dq['deliveries_total'] ?? 0 }} entregas</div>
					<div class="exec-dq-sub">OC y facturas conciliadas sin duplicidad</div>
				</div>

				<div class="exec-dq-item">
					<div class="exec-dq-label">
						<i class="fa-solid fa-users text-warning"></i>
						<span>Personal</span>
					</div>
					<div class="exec-dq-val">{{ count($laborOutlook) }} sucursales</div>
					<div class="exec-dq-sub">Turnos y pronóstico laboral cargados</div>
				</div>
			</div>
		</section>

		<!-- Derecha: Accesos rápidos a módulos -->
		<section class="exec-section-card" aria-label="Accesos rápidos a módulos">
			<div class="exec-section-head">
				<div class="exec-section-title-wrap">
					<div class="exec-section-icon">
						<i class="fa-solid fa-border-all"></i>
					</div>
					<div>
						<h2 class="exec-section-title">Accesos rápidos a módulos</h2>
						<p class="exec-section-subtitle">Continúa con el análisis en los módulos de DAILY.</p>
					</div>
				</div>
				<a href="{{ $U('/system-map') }}" class="btn btn-sm btn-light border font-weight-bold" style="font-size:0.73rem; border-radius:8px;">
					Ir al mapa del sistema <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
				</a>
			</div>

			<div class="exec-shortcuts-grid">
				<a href="{{ $ctxUrl('/today') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-bolt text-success"></i>Operación HOY</div>
					<div class="sc-sub">Seguimiento inmediato</div>
				</a>

				<a href="{{ $ctxUrl('/alerts') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-bell text-warning"></i>Centro de Alertas</div>
					<div class="sc-sub">{{ $alertCount ?? 0 }} alertas activas</div>
				</a>

				<a href="{{ $ctxUrl('/sales') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-chart-line text-success"></i>Ventas y Margen</div>
					<div class="sc-sub">Detalle y transacciones</div>
				</a>

				<a href="{{ $ctxUrl('/suppliers') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-truck-field text-primary"></i>Compras y OC</div>
					<div class="sc-sub">Presupuesto y entregas</div>
				</a>

				<a href="{{ $ctxUrl('/stockoverview') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-boxes-stacked text-success"></i>Inventario</div>
					<div class="sc-sub">Stock y movimientos</div>
				</a>

				<a href="{{ $ctxUrl('/orders') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-truck-fast text-info"></i>Pedidos y Despacho</div>
					<div class="sc-sub">{{ $dq['orders_open'] ?? 0 }} pedidos abiertos</div>
				</a>

				<a href="{{ $ctxUrl('/schedule') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-user-clock text-warning"></i>Personal y Turnos</div>
					<div class="sc-sub">Cobertura horaria</div>
				</a>

				<a href="{{ $ctxUrl('/waste') }}" class="exec-shortcut-btn">
					<div class="sc-title"><i class="fa-solid fa-trash-can text-danger"></i>Calidad y Mermas</div>
					<div class="sc-sub">Causales y lotes FEFO</div>
				</a>
			</div>
		</section>
	</div>

</div>

<script>
function onExecPeriodChange(val) {
	const customBox = document.getElementById('exec-custom-dates');
	if (val === 'custom') {
		customBox.classList.remove('d-none');
		customBox.classList.add('d-inline-flex');
	} else {
		customBox.classList.add('d-none');
		customBox.classList.remove('d-inline-flex');
		document.getElementById('exec-filter-form').submit();
	}
}

document.addEventListener('DOMContentLoaded', function() {
	const sparkData = @json($ep['sparklines'] ?? ($kpiData['sparklines'] ?? []));
	const opsData = @json($ops);
	const evoData = @json($evoChart);
	const branchRankingData = @json($branchRanking);

	function drawMiniSparkline(canvasId, values, hexColor, fillAlpha) {
		const el = document.getElementById(canvasId);
		if (!el || typeof Chart === 'undefined') return;
		if (!Array.isArray(values) || values.length < 2) return; // sin serie real no se dibuja nada
		const pts = values;
		new Chart(el.getContext('2d'), {
			type: 'line',
			data: {
				labels: pts.map((_, i) => i + 1),
				datasets: [{
					data: pts,
					borderColor: hexColor,
					backgroundColor: fillAlpha,
					borderWidth: 2,
					pointRadius: 0,
					fill: true,
					lineTension: 0.35
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: { enabled: false },
				scales: {
					xAxes: [{ display: false }],
					yAxes: [{ display: false }]
				}
			}
		});
	}

	// 1. Sparklines de los 4 Indicadores Estratégicos
	drawMiniSparkline('execSparkSales', sparkData.sales, '#16a34a', 'rgba(22, 163, 74, 0.14)');
	drawMiniSparkline('execSparkMargin', sparkData.margin, '#d97706', 'rgba(217, 119, 6, 0.14)');
	drawMiniSparkline('execSparkWaste', sparkData.waste, '#dc2626', 'rgba(220, 38, 38, 0.14)');
	drawMiniSparkline('execSparkTurn', null, '#dc2626', 'rgba(220, 38, 38, 0.10)');

	// 2. Sparklines de las 7 Tarjetas de Estado de la Operación
	if (Array.isArray(opsData)) {
		opsData.forEach((op, idx) => {
			const color = op.state === 'ok' ? '#16a34a' : (op.state === 'warn' ? '#d97706' : (op.state === 'crit' ? '#dc2626' : '#94a3b8'));
			const bg = op.state === 'ok' ? 'rgba(22, 163, 74, 0.12)' : (op.state === 'warn' ? 'rgba(217, 119, 6, 0.12)' : (op.state === 'crit' ? 'rgba(220, 38, 38, 0.12)' : 'rgba(148, 163, 184, 0.10)'));
			drawMiniSparkline('execOpSpark' + idx, op.spark, color, bg);
		});
	}

	// 3. Gráfica Principal: Evolución de Ventas vs. Meta
	const evoCanvas = document.getElementById('execEvolutionChart');
	let evoChartInstance = null;

	function renderEvolutionChart(mode) {
		if (!evoCanvas || typeof Chart === 'undefined') return;
		const fullLabels = evoData.labels || [];
		const fullSales = evoData.sales_real || [];
		const fullTarget = evoData.target_line || [];
		const fullPrev = evoData.prev_period || [];

		const sliceFrom = (mode === 'last14' && fullLabels.length > 14) ? fullLabels.length - 14 : 0;
		const labels = fullLabels.slice(sliceFrom);
		const sales = fullSales.slice(sliceFrom);
		const target = fullTarget.slice(sliceFrom);
		const prev = fullPrev.slice(sliceFrom);

		if (evoChartInstance) {
			evoChartInstance.destroy();
		}

		evoChartInstance = new Chart(evoCanvas.getContext('2d'), {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						type: 'line',
						label: 'Meta diaria',
						data: target,
						borderColor: '#475569',
						borderDash: [5, 5],
						borderWidth: 2,
						pointRadius: 2,
						pointBackgroundColor: '#475569',
						fill: false,
						lineTension: 0.25,
						order: 1
					},
					{
						type: 'line',
						label: 'Período anterior',
						data: prev,
						borderColor: '#94a3b8',
						backgroundColor: 'rgba(203, 213, 225, 0.28)',
						borderWidth: 1.5,
						pointRadius: 0,
						fill: true,
						lineTension: 0.35,
						order: 3
					},
					{
						type: 'bar',
						label: 'Ventas reales',
						data: sales,
						backgroundColor: '#10b981',
						hoverBackgroundColor: '#059669',
						borderRadius: 4,
						barPercentage: 0.58,
						categoryPercentage: 0.72,
						order: 2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					mode: 'index',
					intersect: false,
					callbacks: {
						label: function(item, data) {
							const ds = data.datasets[item.datasetIndex];
							return ' ' + ds.label + ': $' + Number(item.yLabel || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontSize: 10, fontColor: '#64748b', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
					}],
					yAxes: [{
						gridLines: { color: '#f1f5f9', drawBorder: false },
						ticks: {
							beginAtZero: true,
							fontSize: 10,
							fontColor: '#64748b',
							callback: function(v) { return '$' + Number(v).toLocaleString('en-US'); }
						}
					}]
				}
			}
		});
	}

	renderEvolutionChart('all');

	const rangeSelect = document.getElementById('exec-chart-range-select');
	if (rangeSelect) {
		rangeSelect.addEventListener('change', function() {
			renderEvolutionChart(this.value);
		});
	}

	// 4. Selector de métrica en Desempeño por Sucursal
	const branchMetricSelect = document.getElementById('exec-branch-metric-select');
	if (branchMetricSelect) {
		branchMetricSelect.addEventListener('change', function() {
			const metric = this.value;
			const th = document.getElementById('th-branch-metric');
			if (th) {
				th.textContent = metric === 'margin' ? 'MARGEN BRUTO' : (metric === 'waste' ? 'MERMA' : 'VENTAS');
			}
			document.querySelectorAll('#exec-branch-summary-table tbody tr').forEach(tr => {
				const cell = tr.querySelector('.branch-metric-cell');
				if (cell) {
					cell.textContent = tr.getAttribute('data-' + metric) || '$0.00';
				}
			});
		});
	}

	// 5. Mostrar / ocultar sucursales sin actividad
	const btnToggleInactive = document.getElementById('btn-toggle-inactive-branches');
	if (btnToggleInactive) {
		let showingInactive = false;
		btnToggleInactive.addEventListener('click', function() {
			showingInactive = !showingInactive;
			document.querySelectorAll('.branch-inactive-row').forEach(row => {
				row.classList.toggle('d-none', !showingInactive);
			});
			this.querySelector('span').textContent = showingInactive
				? 'Ocultar sucursales sin actividad en el período'
				: 'Mostrar sucursales sin actividad en el período';
		});
	}

	// 6. Exportación CSV / TSV del resumen ejecutivo
	function exportExecutiveSummary(sep, ext) {
		const lines = [
			['Seccion', 'Indicador / Sucursal', 'Valor Actual', 'Meta / Referencia', 'Variacion (%)', 'Estado'].join(sep),
			['KPI Estrategico', 'Ventas netas', '{{ number_format($kSales["value"] ?? 0, 2, ".", "") }}', '{{ number_format($kSales["target"] ?? 0, 2, ".", "") }}', '{{ $kSales["delta_pct"] ?? "N/A" }}', '{{ $kSales["state"] ?? "" }}'].join(sep),
			['KPI Estrategico', 'Margen bruto', '{{ number_format($kMargin["value"] ?? 0, 2, ".", "") }}', '{{ number_format($kMargin["target"] ?? 0, 2, ".", "") }}', '{{ $kMargin["delta_pct"] ?? "N/A" }}', '{{ !empty($kMargin["is_partial"]) ? "Parcial (CMV incompleto)" : ($kMargin["state"] ?? "") }}'].join(sep),
			['KPI Estrategico', 'Merma total', '{{ number_format($kWaste["value"] ?? 0, 2, ".", "") }}', '{{ number_format($kWaste["target"] ?? 0, 2, ".", "") }}', '{{ $kWaste["delta_pct"] ?? "N/A" }}', '{{ $kWaste["state"] ?? "" }}'].join(sep),
			['KPI Estrategico', 'Rotacion de inventario', '{{ number_format($kTurn["value"] ?? 0, 2, ".", "") }}x', '{{ number_format($kTurn["target"] ?? 0, 2, ".", "") }}x', '{{ $kTurn["delta_pct"] ?? "N/A" }}', '{{ $kTurn["state"] ?? "" }}'].join(sep)
		];

		if (Array.isArray(branchRankingData)) {
			branchRankingData.forEach(b => {
				lines.push([
					'Sucursal',
					`"${(b.clean_name || b.name || '').replace(/"/g, '""')}"`,
					Number(b.sales || 0).toFixed(2),
					b.sales_target !== null ? Number(b.sales_target).toFixed(2) : 'Sin meta',
					b.vs_meta_pct !== null ? b.vs_meta_pct + '%' : 'Sin actividad',
					b.exec_state_label || 'Sin informacion'
				].join(sep));
			});
		}

		const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `panel_ejecutivo_daily.${ext}`;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	const btnCsv = document.getElementById('btn-exec-export-csv');
	if (btnCsv) btnCsv.addEventListener('click', function(e) { e.preventDefault(); exportExecutiveSummary(',', 'csv'); });
	const btnTsv = document.getElementById('btn-exec-export-tsv');
	if (btnTsv) btnTsv.addEventListener('click', function(e) { e.preventDefault(); exportExecutiveSummary('\t', 'tsv'); });
});
</script>
@endsection
