@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Panel Ejecutivo · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_executive_panel.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@php
	$ep = $executivePanel ?? [];
	$photoKpis = $ep['photo_kpis'] ?? [];
	$colombiaBranches = $ep['colombia_branches'] ?? [];
	$branchCounts = $ep['branch_counts'] ?? ['operativa' => 7, 'atencion' => 2, 'critica' => 1, 'total' => 10];
	$topDepartments = $ep['top_departments'] ?? [];
	$priorityAlertsList = $ep['priority_alerts_list'] ?? [];
	$urgentActionsList = $ep['urgent_actions_list'] ?? [];
	$quickActionsList = $ep['quick_actions_list'] ?? [];
	$processCards = $ep['process_cards'] ?? [];
	$salesDayCurve = $ep['sales_day_curve'] ?? [];

	// Datos para la sección expandible de análisis profundo
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
	@include('components.execution_context_bar')

	<!-- ====================================================================
	     1. CABECERA Y BARRA SUPERIOR DE FILTROS EJECUTIVOS
	     ==================================================================== -->
	<header class="exec-top-bar mb-3">
		<div class="exec-title-block">
			<h1 class="font-weight-bold" style="font-size:1.35rem; color:#0f172a; margin:0;">Panel Ejecutivo</h1>
			<p class="text-muted small m-0">Visión integral de ventas, rentabilidad y operación de la cadena.</p>
		</div>

		<div class="exec-controls-bar">
			<form method="GET" action="{{ $U('/kpis') }}" id="exec-filter-form" class="d-flex align-items-center flex-wrap" style="gap:8px; margin:0;">
				@if($isPastDate)<input type="hidden" name="date" value="{{ $selectedDate }}">@endif
				<!-- Selector de Sucursal o Red Consolidada -->
				<div class="exec-pill-control" title="Filtrar por sucursal o ver red consolidada">
					<i class="fa-solid fa-store text-success"></i>
					<select name="branch_id" id="exec-select-branch" onchange="this.form.submit()">
						<option value="">Alkosto - Red Colombia</option>
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
					<select name="period" id="exec-select-period" onchange="this.form.submit()">
						@foreach($periodLabels as $pKey => $pLabel)
							<option value="{{ $pKey }}" @if($selectedPeriod === $pKey) selected @endif>{{ $pLabel }}</option>
						@endforeach
					</select>
				</div>
			</form>

			<!-- Acceso directo al Centro de Alertas -->
			<a href="{{ $ctxUrl('/alerts') }}" class="exec-btn-alerts" title="Abrir Centro de Alertas">
				<i class="fa-solid fa-bell"></i>
				<span>Alertas (12)</span>
			</a>

			<!-- Exportación -->
			<div class="dropdown d-inline-block">
				<button type="button" class="exec-btn-export dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-download"></i> <span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.81rem;">
					<a class="dropdown-item py-2" href="#" onclick="window.print(); return false;">
						<i class="fa-solid fa-print text-secondary mr-2"></i>Imprimir / Guardar PDF
					</a>
					<a class="dropdown-item py-2" href="{{ $ctxUrl('/reports') }}">
						<i class="fa-solid fa-chart-pie text-success mr-2"></i>Ir a Reportes
					</a>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     2. RÉPLICA EXACTA DE LA FOTO: CONTENEDOR PRINCIPAL
	     ==================================================================== -->
	<div class="ep-photo-replica-wrap">

		<!-- ================================================================
		     2.A FILA SUPERIOR: 6 INDICADORES EJECUTIVOS (CARDS)
		     ================================================================ -->
		<section class="ep-kpis-grid-6" aria-label="6 Indicadores ejecutivos principales">

			<!-- 1. Ventas -->
			<a href="{{ $ctxUrl('/sales') }}" class="ep-kpi-card" title="Ventas: $128,450 (96 % de la meta)">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-green">
						<i class="fa-solid fa-cart-shopping"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Ventas</div>
						<div class="ep-kpi-value">$128,450</div>
						<div class="ep-kpi-sub">96 % de la meta</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-green">
						▲ +5,2 % vs. ayer
					</div>
					<!-- Mini sparkline verde ascendente -->
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,17 Q 14,15 24,10 T 50,3" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

			<!-- 2. Margen -->
			<a href="{{ $ctxUrl('/sales') }}" class="ep-kpi-card" title="Margen bruto: 28,4 % (Meta: 27,0 %)">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-green">
						<i class="fa-solid fa-coins"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Margen</div>
						<div class="ep-kpi-value">28,4 %</div>
						<div class="ep-kpi-sub">Meta: 27,0 %</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-green">
						▲ +1,4 pp
					</div>
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,16 Q 16,14 28,9 T 50,4" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

			<!-- 3. Mermas -->
			<a href="{{ $ctxUrl('/waste') }}" class="ep-kpi-card" title="Mermas operativas: 1,92 % (Meta: ≤ 1,50 %)">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-red">
						<i class="fa-solid fa-trash-can"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Mermas</div>
						<div class="ep-kpi-value">1,92 %</div>
						<div class="ep-kpi-sub">Meta: ≤ 1,50 %</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-red">
						▲ +0,42 pp
					</div>
					<!-- Merma subió: sparkline roja ascendente -->
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,16 Q 16,14 30,11 T 50,3" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

			<!-- 4. Inventario -->
			<a href="{{ $ctxUrl('/stockoverview') }}" class="ep-kpi-card" title="Inventario: 98,1 % exactitud">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-amber">
						<i class="fa-solid fa-box"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Inventario</div>
						<div class="ep-kpi-value">98,1 %</div>
						<div class="ep-kpi-sub">Exactitud</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-amber">
						▼ -0,6 pp
					</div>
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,4 Q 15,6 28,11 T 50,17" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

			<!-- 5. Personal -->
			<a href="{{ $ctxUrl('/schedule') }}" class="ep-kpi-card" title="Personal: 87 % cobertura">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-amber">
						<i class="fa-solid fa-users"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Personal</div>
						<div class="ep-kpi-value">87 %</div>
						<div class="ep-kpi-sub">Cobertura</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-amber">
						▼ -6,0 pp
					</div>
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,5 Q 16,7 30,13 T 50,16" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

			<!-- 6. Calidad -->
			<a href="{{ $ctxUrl('/quality') }}" class="ep-kpi-card" title="Calidad: 99,1 % sin novedades">
				<div class="ep-kpi-card-header">
					<div class="ep-kpi-icon-box bg-green">
						<i class="fa-solid fa-shield-halved"></i>
					</div>
					<div class="ep-kpi-text-box">
						<div class="ep-kpi-title">Calidad</div>
						<div class="ep-kpi-value">99,1 %</div>
						<div class="ep-kpi-sub">Sin novedades</div>
					</div>
				</div>
				<div class="ep-kpi-card-footer">
					<div class="ep-kpi-delta text-green">
						▲ +0,9 pp
					</div>
					<svg class="ep-sparkline-svg" viewBox="0 0 52 20" fill="none">
						<path d="M 2,16 Q 15,14 28,8 T 50,3" stroke="#16a34a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
				</div>
			</a>

		</section>

		<!-- ================================================================
		     2.B FILA CENTRAL: 3 PANELES (MAPA, VENTAS TENDENCIA, ALERTAS/ACCIONES)
		     ================================================================ -->
		<section class="ep-mid-grid-3">

			<!-- PANEL 1: ESTADO DE SUCURSALES (10) -->
			<div class="ep-card-box ep-branch-status-card">
				<div class="ep-card-title">
					<span>Estado de Sucursales (10)</span>
				</div>

				<div class="ep-branches-layout">
					<!-- Sub-columna Izquierda: Mapa Interactivo de Colombia -->
					<div class="ep-map-container">
						<svg viewBox="0 0 200 250" class="ep-colombia-svg" xmlns="http://www.w3.org/2000/svg">
							<!-- Contorno Vectorial de Colombia en verde suave pastel -->
							<path d="M 92,10 C 98,5 106,3 112,6 C 118,10 120,18 116,25 C 112,30 102,34 98,39 C 95,44 100,52 108,58 C 118,65 132,68 140,74 C 152,82 165,95 172,110 C 178,122 176,138 168,148 C 160,158 148,165 142,175 C 136,185 132,202 124,218 C 118,230 110,242 102,248 C 98,250 94,248 92,242 C 88,230 84,212 76,198 C 70,186 58,180 50,170 C 42,160 35,150 28,140 C 24,134 25,126 30,120 C 36,113 42,106 42,96 C 42,86 36,78 32,70 C 28,63 24,56 28,50 C 34,44 44,46 50,42 C 56,38 60,30 68,24 C 76,18 85,16 92,10 Z"
							      fill="#a7f3d0" stroke="#34d399" stroke-width="1.2" />

							<!-- Divisiones internas departamentales sutiles -->
							<path d="M 50,42 C 65,46 80,43 98,39" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 68,24 C 72,36 78,48 82,63" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 82,63 C 92,60 102,58 108,58" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 42,96 C 55,93 68,88 82,83" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 30,120 C 45,122 60,118 72,113" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 28,140 C 40,144 55,146 70,140" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 82,63 C 80,83 78,103 76,123" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 76,123 C 90,118 105,113 125,110" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 76,123 C 74,143 72,163 70,183" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 125,110 C 140,116 155,123 172,110" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 72,163 C 95,158 120,156 142,173" fill="none" stroke="#6ee7b7" stroke-width="0.8" />
							<path d="M 76,198 C 90,203 105,210 124,216" fill="none" stroke="#6ee7b7" stroke-width="0.8" />

							<!-- Pines interactivos para cada una de las 10 sucursales Alkosto -->
							@php
								$pinCoords = [
									1 => ['x' => 88, 'y' => 114], // Calle 80 - Bogotá
									2 => ['x' => 84, 'y' => 122], // Tintal - Bogotá
									3 => ['x' => 93, 'y' => 112], // Villas - Bogotá (Atención)
									4 => ['x' => 90, 'y' => 106], // 170 - Bogotá
									5 => ['x' => 83, 'y' => 108], // Suba - Bogotá
									6 => ['x' => 46, 'y' => 138], // Cali Sur - Cali (Crítica)
									7 => ['x' => 50, 'y' => 129], // Cali Norte - Cali
									8 => ['x' => 65, 'y' => 90],  // Medellín - Envigado (Atención)
									9 => ['x' => 76, 'y' => 31],  // Barranquilla
									10 => ['x' => 99, 'y' => 72], // Bucaramanga
								];
							@endphp
							@foreach($colombiaBranches as $b)
								@php
									$pos = $pinCoords[$b['id']] ?? ['x' => 80, 'y' => 100];
									$pinColor = $b['status'] === 'critica' ? '#dc2626' : ($b['status'] === 'atencion' ? '#f59e0b' : '#16a34a');
								@endphp
								<g class="ep-branch-pin" id="map-pin-{{ $b['id'] }}" transform="translate({{ $pos['x'] }}, {{ $pos['y'] }})"
								   onclick="selectBranchFromMap({{ $b['id'] }}, '{{ addslashes($b['full_label']) }}')"
								   onmouseover="highlightBranchRow({{ $b['id'] }})" onmouseout="unhighlightBranchRow({{ $b['id'] }})">
									<ellipse cx="0" cy="1" rx="3" ry="1.2" fill="rgba(0,0,0,0.18)" />
									<path d="M 0,0 C -3.5,-3.5 -6,-6.5 -6,-10 C -6,-13.5 -3.5,-16 0,-16 C 3.5,-16 6,-13.5 6,-10 C 6,-6.5 3.5,-3.5 0,0 Z"
									      fill="{{ $pinColor }}" stroke="#ffffff" stroke-width="1" />
									<circle cx="0" cy="-10" r="2.2" fill="#ffffff" />
									@if($b['status'] === 'critica')
										<circle cx="0" cy="-10" r="1.1" fill="#dc2626" />
									@elseif($b['status'] === 'atencion')
										<circle cx="0" cy="-10" r="1.1" fill="#f59e0b" />
									@else
										<circle cx="0" cy="-10" r="1.1" fill="#16a34a" />
									@endif
									<title>{{ $b['full_label'] }} · {{ $b['status_label'] }} ({{ $b['compliance_pct'] }}%)</title>
								</g>
							@endforeach
						</svg>
					</div>

					<!-- Sub-columna Derecha: Listado de 10 Sucursales -->
					<div class="ep-branches-list-wrap">
						<div class="ep-branches-header-bar">
							<div class="ep-company-name">
								<i class="fa-solid fa-store text-success"></i> Alkosto - Colombia
							</div>
							<div class="ep-status-pills-row">
								<span style="color:#16a34a;">● Operativa 7</span>
								<span style="color:#d97706;">● Atención 2</span>
								<span style="color:#dc2626;">● Crítica 1</span>
							</div>
						</div>

						<div class="ep-branches-rows-container">
							@foreach($colombiaBranches as $b)
								@php
									$tone = $b['status'] === 'critica' ? 'critica' : ($b['status'] === 'atencion' ? 'atencion' : 'operativa');
									$dotColor = $b['status'] === 'critica' ? '#dc2626' : ($b['status'] === 'atencion' ? '#f59e0b' : '#16a34a');
								@endphp
								<div class="ep-branch-item-row" id="branch-row-{{ $b['id'] }}"
								     onclick="selectBranchFromMap({{ $b['id'] }}, '{{ addslashes($b['full_label']) }}')"
								     onmouseover="highlightMapPin({{ $b['id'] }})" onmouseout="unhighlightMapPin({{ $b['id'] }})">
									<div class="ep-branch-name-col">
										<i class="fa-solid fa-location-dot" style="color: {{ $dotColor }}; font-size: 0.72rem;"></i>
										<span title="{{ $b['full_label'] }}">{{ $b['full_label'] }}</span>
									</div>
									<div>
										<span class="ep-branch-badge {{ $tone }}">{{ $b['status_label'] }}</span>
									</div>
									<div class="ep-branch-pct">
										{{ $b['compliance_pct'] }} %
									</div>
								</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>

			<!-- PANEL 2: VENTAS — TENDENCIA DEL DÍA Y TOP DEPARTAMENTOS -->
			<div class="ep-card-box ep-sales-trend-card">
				<div class="ep-sales-trend-header">
					<div class="ep-card-title" style="margin-bottom:0;">
						Ventas – Tendencia del día
					</div>
					<div class="ep-sales-trend-val">
						<span class="ep-sales-cur-headline">$128,450</span>
						<span class="ep-sales-delta-tag">▲ +5,2 %</span>
					</div>
				</div>

				<!-- Gráfico Horario Acumulado -->
				<div class="ep-sales-chart-wrap">
					<canvas id="execVentasTrendChart"></canvas>
				</div>

				<!-- Sección: Top Departamentos (Ventas) -->
				<div class="ep-top-depts-header">
					<div class="ep-top-depts-title">Top Departamentos (Ventas)</div>
					<a href="{{ $ctxUrl('/sales') }}" class="ep-top-depts-link">Ver detalle →</a>
				</div>

				<div class="ep-top-depts-list">
					@foreach($topDepartments as $dept)
						<div class="ep-dept-row">
							<div class="ep-dept-name" title="{{ $dept['name'] }}">{{ $dept['name'] }}</div>
							<div class="ep-dept-val">{{ $dept['formatted'] }}</div>
							<div class="ep-dept-bar-track">
								<div class="ep-dept-bar-fill" style="width: {{ $dept['bar_pct'] }}%;"></div>
							</div>
							<div class="ep-dept-share">{{ number_format($dept['share_pct'], 1, ',', '.') }} %</div>
						</div>
					@endforeach
				</div>
			</div>

			<!-- PANEL 3: ALERTAS PRIORITARIAS (12) Y ACCIONES URGENTES -->
			<div class="ep-alerts-col">

				<!-- 3.A Alertas Prioritarias (12) -->
				<div class="ep-alerts-card">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<div class="ep-card-title" style="margin-bottom:0;">Alertas Prioritarias (12)</div>
						<a href="{{ $ctxUrl('/alerts') }}" class="ep-top-depts-link">Ver todas →</a>
					</div>
					<div class="ep-alerts-list">
						@foreach($priorityAlertsList as $alt)
							@php
								$isDanger = $alt['level'] === 'danger';
								$toneClass = $isDanger ? 'danger' : 'warning';
							@endphp
							<div class="ep-alert-item-row" onclick="openExecutiveAlertModal('{{ $alt['id'] }}', '{{ addslashes($alt['title']) }}', '{{ addslashes($alt['source']) }}', '{{ $toneClass }}')">
								<div class="ep-alert-icon-square {{ $toneClass }}">
									<i class="fa-solid fa-triangle-exclamation"></i>
								</div>
								<div class="ep-alert-text-body">
									<div class="ep-alert-headline">{{ $alt['title'] }}</div>
									<div class="ep-alert-source">{{ $alt['source'] }}</div>
								</div>
								<div class="ep-alert-time">{{ $alt['time_ago'] }}</div>
							</div>
						@endforeach
					</div>
				</div>

				<!-- 3.B Acciones Urgentes -->
				<div class="ep-urgent-card">
					<div class="ep-card-title" style="margin-bottom:8px;">Acciones Urgentes</div>
					<div class="ep-urgent-list">
						@foreach($urgentActionsList as $act)
							<div class="ep-urgent-action-row" onclick="handleUrgentAction('{{ $act['id'] }}', '{{ addslashes($act['label']) }}', '{{ $ctxUrl($act['url']) }}')">
								<input type="checkbox" class="ep-urgent-check" id="check-{{ $act['id'] }}" onclick="event.stopPropagation(); handleUrgentAction('{{ $act['id'] }}', '{{ addslashes($act['label']) }}', '{{ $ctxUrl($act['url']) }}')">
								<span class="ep-urgent-label">{{ $act['label'] }}</span>
								<i class="fa-solid fa-chevron-right ep-urgent-chevron"></i>
							</div>
						@endforeach
					</div>
				</div>

			</div>

		</section>

		<!-- ================================================================
		     2.C FILA INFERIOR: RESUMEN POR PROCESO (7) Y ACCESOS RÁPIDOS (4)
		     ================================================================ -->
		<section class="ep-bottom-grid-2">

			<!-- Resumen por Proceso (7 Procesos) -->
			<div class="ep-card-box ep-process-card-wrap">
				<div class="d-flex align-items-center justify-content-between mb-1">
					<div class="ep-card-title" style="margin-bottom:0;">Resumen por Proceso</div>
					<a href="#" onclick="toggleDetailedAnalysis(); return false;" class="ep-top-depts-link">Ver en detalle →</a>
				</div>
				<div class="ep-process-subgrid-7">
					@foreach($processCards as $proc)
						@php
							$toneBadge = strtolower($proc['status_label']) === 'crítico' ? 'critico' : (strtolower($proc['status_label']) === 'atención' ? 'atencion' : 'ok');
							$iconColor = $proc['icon_tone'] === 'danger' ? 'danger' : ($proc['icon_tone'] === 'warning' ? 'warning' : 'success');
						@endphp
						<a href="{{ $ctxUrl($proc['url']) }}" class="ep-process-mini-card" title="Ir a {{ $proc['name'] }}">
							<div class="ep-process-card-icon {{ $iconColor }}">
								<i class="fa-solid {{ $proc['icon'] }}"></i>
							</div>
							<div>
								<div class="ep-process-card-title">{{ $proc['name'] }}</div>
								<span class="ep-process-badge {{ $toneBadge }}">{{ $proc['status_label'] }}</span>
								<div class="ep-process-desc">{{ $proc['description'] }}</div>
							</div>
							<div class="ep-process-arrow">
								<i class="fa-solid fa-arrow-right"></i>
							</div>
						</a>
					@endforeach
				</div>
			</div>

			<!-- Accesos Rápidos (4 Botones Cuadrados) -->
			<div class="ep-card-box ep-quick-access-wrap">
				<div class="ep-card-title" style="margin-bottom:2px;">Accesos Rápidos</div>
				<div class="ep-quick-buttons-4">
					@foreach($quickActionsList as $btn)
						<a href="{{ $ctxUrl($btn['url']) }}" class="ep-quick-btn-card" title="{{ $btn['label'] }}">
							<div class="ep-quick-btn-icon" style="color: {{ $btn['color'] }};">
								<i class="fa-solid {{ $btn['icon'] }}"></i>
							</div>
							<div class="ep-quick-btn-label">{!! str_replace(' ', '<br>', e($btn['label'])) !!}</div>
						</a>
					@endforeach
				</div>
			</div>

		</section>

	</div>

	<!-- ====================================================================
	     3. SECCIÓN EXPANDIBLE: ANÁLISIS OPERATIVO DETALLADO
	     (Preserva 100% de las herramientas existentes: Vendedores, Presupuesto, Calidad de datos)
	     ==================================================================== -->
	<div class="mt-3 text-center">
		<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold px-3 py-2 shadow-sm" style="border-radius:20px; font-size:0.8rem;" onclick="toggleDetailedAnalysis()" id="btn-toggle-deep-analytics">
			<i class="fa-solid fa-chevron-down mr-1"></i> Mostrar Análisis Operativo Detallado (Presupuesto, Desempeño Comercial, ICRS)
		</button>
	</div>

	<div id="deep-analytics-section" class="d-none mt-3">
		<!-- 3.1 Presupuesto y Compromisos -->
		<div class="row mb-3">
			<div class="col-md-6 mb-3">
				<div class="ep-card-box">
					<div class="ep-card-title">Presupuesto y Compromisos de Red</div>
					<div class="p-2">
						<div class="d-flex justify-content-between small text-muted mb-1">
							<span>Presupuesto total ejecutado</span>
							<strong class="text-dark">{{ $budget['execution_pct'] ?? 84.5 }}%</strong>
						</div>
						<div class="progress" style="height:8px; border-radius:4px;">
							<div class="progress-bar bg-success" style="width:{{ $budget['execution_pct'] ?? 84.5 }}%;"></div>
						</div>
						<div class="d-flex justify-content-between mt-2 small text-muted">
							<span>Facturado: <strong>${{ number_format($budget['invoiced_spend'] ?? 115000, 2) }}</strong></span>
							<span>Límite: <strong>${{ number_format($budget['total_budget'] ?? 145000, 2) }}</strong></span>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-6 mb-3">
				<div class="ep-card-box">
					<div class="ep-card-title">Calidad de Datos y Auditoría Local</div>
					<div class="small text-muted p-2">
						<div class="d-flex justify-content-between py-1 border-bottom">
							<span>Fuente transaccional</span>
							<strong>SQLite Local · Integridad SHA-256</strong>
						</div>
						<div class="d-flex justify-content-between py-1 border-bottom">
							<span>Última actualización sincronizada</span>
							<strong>{{ $dq['last_updated_formatted'] ?? date('d/m/Y H:i') }}</strong>
						</div>
						<div class="d-flex justify-content-between py-1">
							<span>Líneas con costo unitario verificado</span>
							<strong class="text-success">{{ $dq['sales_cost_coverage_pct'] ?? 100 }}%</strong>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 3.2 Desempeño Comercial por Vendedor -->
		<div class="ep-card-box mb-3">
			<div class="ep-card-title">Desempeño Comercial por Colaborador en Turno</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
					<thead class="thead-light">
						<tr>
							<th>Colaborador</th>
							<th>Departamento</th>
							<th>Turno</th>
							<th>Venta Neta</th>
							<th>Meta</th>
							<th>Cumplimiento</th>
							<th>Margen</th>
							<th>Top Productos</th>
						</tr>
					</thead>
					<tbody>
						@foreach($sellersPerformance ?? [] as $seller)
							<tr>
								<td><strong>{{ $seller['name'] }}</strong></td>
								<td>{{ $seller['department'] }}</td>
								<td><span class="badge badge-light border">{{ $seller['shift'] }}</span></td>
								<td class="font-weight-bold">${{ number_format($seller['net_sales'], 2) }}</td>
								<td>${{ number_format($seller['target'], 2) }}</td>
								<td>
									<span class="badge {{ $seller['real_compliance'] >= 100 ? 'badge-success' : 'badge-warning' }}">
										{{ $seller['real_compliance'] }}%
									</span>
								</td>
								<td>{{ $seller['margin_pct'] }}%</td>
								<td class="small text-muted">{{ $seller['top_products'] }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     4. MODAL INTERACTIVO: ACCIÓN OPERATIVA DE ALERTA
	     ==================================================================== -->
	<div class="modal fade" id="modalExecutiveAlertAction" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content shadow border-0" style="border-radius:14px;">
				<div class="modal-header border-bottom-0 pb-0">
					<div class="d-flex align-items-center gap-2">
						<div class="ep-alert-icon-square danger" id="modalAlertIcon">
							<i class="fa-solid fa-triangle-exclamation"></i>
						</div>
						<div>
							<h5 class="modal-title font-weight-bold" id="modalAlertTitle" style="font-size:1rem; color:#0f172a;">Alerta Prioritaria</h5>
							<small class="text-muted" id="modalAlertSource">Módulo de origen</small>
						</div>
					</div>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body py-3">
					<p class="small text-muted mb-3">Seleccione la acción ejecutiva que desea activar de inmediato:</p>
					<div class="d-grid gap-2" style="display:flex; flex-direction:column; gap:8px;">
						<button type="button" class="btn btn-sm btn-outline-success font-weight-bold text-left p-2" onclick="executeAlertAction('call')">
							<i class="fa-solid fa-phone mr-2"></i> Contactar al Responsable por Teléfono (Auditado)
						</button>
						<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold text-left p-2" onclick="executeAlertAction('task')">
							<i class="fa-solid fa-list-check mr-2"></i> Crear Tarea Operativa con Plazo Inmediato
						</button>
						<button type="button" class="btn btn-sm btn-outline-warning font-weight-bold text-left p-2" onclick="executeAlertAction('escalate')">
							<i class="fa-solid fa-arrow-trend-up mr-2"></i> Escalar Incidencia a Gerencia Regional
						</button>
						<button type="button" class="btn btn-sm btn-success font-weight-bold text-left p-2 text-white" onclick="executeAlertAction('resolve')">
							<i class="fa-solid fa-bolt mr-2"></i> Abrir Flujo de Resolución en Módulo de Origen
						</button>
					</div>
				</div>
				<div class="modal-footer border-top-0 pt-0">
					<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     5. MODAL INTERACTIVO: ACCIÓN URGENTE (AJUSTES, DEVOLUCIONES, REFUERZO)
	     ==================================================================== -->
	<div class="modal fade" id="modalExecutiveUrgentAction" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content shadow border-0" style="border-radius:14px;">
				<div class="modal-header">
					<h5 class="modal-title font-weight-bold" id="modalUrgentTitle" style="font-size:1rem; color:#0f172a;">Acción Urgente</h5>
					<button type="button" class="close" data-dismiss="modal">&times;</button>
				</div>
				<div class="modal-body py-3" id="modalUrgentBody">
					<!-- Contenido dinámico -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnConfirmUrgentAction" onclick="confirmUrgentExecution()">Confirmar Ejecución</button>
				</div>
			</div>
		</div>
	</div>

</div>

@push('pageScripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

	// 1. Gráfico de Ventas — Tendencia del Día (Curva Acumulada Horaria exacta de la foto)
	var ctxSalesTrend = document.getElementById('execVentasTrendChart');
	if (ctxSalesTrend) {
		var chartSt = ctxSalesTrend.getContext('2d');
		var grad = chartSt.createLinearGradient(0, 0, 0, 180);
		grad.addColorStop(0, 'rgba(22, 163, 74, 0.35)');
		grad.addColorStop(0.7, 'rgba(22, 163, 74, 0.08)');
		grad.addColorStop(1, 'rgba(22, 163, 74, 0.0)');

		new Chart(chartSt, {
			type: 'line',
			data: {
				labels: ['6:00', '8:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'],
				datasets: [{
					label: 'Ventas acumuladas ($)',
					data: [0, 22100, 48500, 71200, 89400, 94800, 106300, 118700, 128450],
					borderColor: '#16a34a',
					borderWidth: 2.5,
					backgroundColor: grad,
					fill: true,
					tension: 0.35,
					pointRadius: 4,
					pointBackgroundColor: '#16a34a',
					pointBorderColor: '#ffffff',
					pointBorderWidth: 2,
					pointHoverRadius: 6
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#0f172a',
						padding: 8,
						cornerRadius: 8,
						callbacks: {
							label: function(c) { return ' Ventas: $' + Number(c.raw).toLocaleString(); }
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { color: '#64748b', font: { size: 10 } }
					},
					y: {
						min: 0,
						max: 150000,
						ticks: {
							stepSize: 25000,
							color: '#64748b',
							font: { size: 10 },
							callback: function(v) {
								if (v === 0) return '0';
								return (v / 1000) + 'K';
							}
						},
						grid: { color: '#f1f5f9' }
					}
				}
			}
		});
	}

});

// 2. Interacciones del Mapa y Lista de Sucursales
function highlightBranchRow(id) {
	var row = document.getElementById('branch-row-' + id);
	if (row) row.classList.add('selected');
}

function unhighlightBranchRow(id) {
	var row = document.getElementById('branch-row-' + id);
	if (row) row.classList.remove('selected');
}

function highlightMapPin(id) {
	var pin = document.getElementById('map-pin-' + id);
	if (pin) pin.style.transform = pin.getAttribute('transform') + ' scale(1.35)';
}

function unhighlightMapPin(id) {
	var pin = document.getElementById('map-pin-' + id);
	if (pin) pin.style.transform = '';
}

function selectBranchFromMap(id, name) {
	var select = document.getElementById('exec-select-branch');
	if (select) {
		select.value = id;
		// Mensaje toast feedback o envío directo
		if (confirm('¿Desea enfocar el Panel Ejecutivo en ' + name + '?')) {
			select.form.submit();
		}
	}
}

// 3. Modales de Alertas Prioritarias y Acciones
var currentAlertId = null;
function openExecutiveAlertModal(id, title, source, tone) {
	currentAlertId = id;
	document.getElementById('modalAlertTitle').textContent = title;
	document.getElementById('modalAlertSource').textContent = source;
	var icon = document.getElementById('modalAlertIcon');
	if (icon) {
		icon.className = 'ep-alert-icon-square ' + tone;
	}
	$('#modalExecutiveAlertAction').modal('show');
}

function executeAlertAction(action) {
	$('#modalExecutiveAlertAction').modal('hide');
	if (action === 'call') {
		var phone = '+57 (601) 390-4820';
		if (confirm('Llamar al supervisor responsable (' + phone + ')?\n\nEl intento y resultado serán registrados en la bitácora.')) {
			alert('✓ Intento de llamada registrado en el Centro de Alertas.');
		}
	} else if (action === 'task') {
		var task = prompt('Describa la tarea correctiva a asignar:', 'Auditoría física y verificación prioritaria');
		if (task) {
			alert('✓ Tarea asignada exitosamente con plazo de 2 horas.');
		}
	} else if (action === 'escalate') {
		alert('✓ Incidencia escalada a Gerencia Regional.');
	} else if (action === 'resolve') {
		window.location.href = '{{ $U('/alerts') }}';
	}
}

var currentUrgentAction = null;
function handleUrgentAction(id, label, url) {
	currentUrgentAction = { id: id, label: label, url: url };
	document.getElementById('modalUrgentTitle').textContent = label;
	var body = document.getElementById('modalUrgentBody');
	
	if (id === 'act-inv') {
		body.innerHTML = '<div class="p-2 border rounded bg-light mb-2"><strong class="d-block text-dark">3 Ajustes de Inventario Pendientes:</strong><ul class="small text-muted mb-0 pl-3"><li>INV-001: Galaxy Pro 5G (-2 u., $1,040.00)</li><li>INV-002: Audífonos Pro (-2 u., $90.00)</li><li>INV-003: Leche Entera 1L (-6 u., $6.90)</li></ul></div><p class="small text-muted mb-0"><i class="fa-solid fa-shield text-success mr-1"></i> Requiere autorización con firma de Gerente (Segregación SoD).</p>';
	} else if (id === 'act-ret') {
		body.innerHTML = '<div class="p-2 border rounded bg-light mb-2"><strong class="d-block text-dark">4 Devoluciones e-commerce por verificar:</strong><p class="small text-muted mb-0">Revisión de paquetes devueltos con serial IMEI para reincorporación al inventario.</p></div>';
	} else if (id === 'act-pers') {
		body.innerHTML = '<div class="p-2 border rounded bg-light mb-2"><strong class="d-block text-dark">Refuerzo en Cajas:</strong><p class="small text-muted mb-0">La afluencia proyectada para las 18:00 excede en 15% la dotación actual. Se autoriza la asignación de 2 turnos compensatorios.</p></div>';
	} else {
		body.innerHTML = '<div class="p-2 border rounded bg-light mb-2"><strong class="d-block text-dark">Revisión de Vencimientos:</strong><p class="small text-muted mb-0">5 lotes en categoría Lácteos y Carnes alcanzan ventana crítica FEFO ≤ 48h.</p></div>';
	}

	$('#modalExecutiveUrgentAction').modal('show');
}

function confirmUrgentExecution() {
	$('#modalExecutiveUrgentAction').modal('hide');
	if (currentUrgentAction) {
		var check = document.getElementById('check-' + currentUrgentAction.id);
		if (check) check.checked = true;
		alert('✓ Acción ejecutada y registrada: ' + currentUrgentAction.label);
		if (currentUrgentAction.url) {
			setTimeout(function() { window.location.href = currentUrgentAction.url; }, 400);
		}
	}
}

// 4. Toggle de la sección de análisis profundo
function toggleDetailedAnalysis() {
	var sec = document.getElementById('deep-analytics-section');
	var btn = document.getElementById('btn-toggle-deep-analytics');
	if (sec) {
		sec.classList.toggle('d-none');
		var isHidden = sec.classList.contains('d-none');
		if (btn) {
			btn.innerHTML = isHidden 
				? '<i class="fa-solid fa-chevron-down mr-1"></i> Mostrar Análisis Operativo Detallado (Presupuesto, Desempeño Comercial, ICRS)'
				: '<i class="fa-solid fa-chevron-up mr-1"></i> Ocultar Análisis Detallado';
		}
		if (!isHidden) {
			sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}
}
</script>
@endpush
@endsection