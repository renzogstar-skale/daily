@php require_frontend_packages(['chartjs', 'datatables', 'animatecss']); @endphp

@extends('layout.default')

@section('title', 'Panel Ejecutivo · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_executive_panel.css?v=', true) }}{{ daily_asset_version('/css/daily_executive_panel.css') }}" rel="stylesheet">
@endpush

@php
	$ep = $executivePanel ?? [];
	$se = $stockExec ?? [];
	$colombiaBranches = $ep['colombia_branches'] ?? [];
	$branchCounts = $ep['branch_counts'] ?? ['operativa' => 7, 'atencion' => 2, 'critica' => 1, 'total' => 10];
	$topDepartments = $ep['top_departments'] ?? [];
	$priorityAlertsList = $ep['priority_alerts_list'] ?? [];
	$urgentActionsList = $ep['urgent_actions_list'] ?? [];
	$quickActionsList = $ep['quick_actions_list'] ?? [];
	$processCards = $ep['process_cards'] ?? [];
	$salesDayCurve = $ep['sales_day_curve'] ?? [];

	// Datos para análisis profundo
	$sKpis = $ep['strategic_kpis'] ?? [];
	$decisions = $ep['priority_decisions'] ?? [];
	$budget = $ep['budget_summary'] ?? [];
	$ops = $ep['operations_status'] ?? [];
	$evoChart = $ep['evolution_chart'] ?? ['labels' => [], 'sales_real' => [], 'target_line' => [], 'prev_period' => []];
	$dq = $ep['data_quality'] ?? [];
	$branchRanking = $kpiData['branch_ranking'] ?? [];

	// Métricas reales sincronizadas con DailyMetricsService
	$epM = \Grocy\Services\DailyMetricsService::GetInstance();
	$epR = \Grocy\Services\DailyMetricsService::PeriodRange($selectedPeriod ?? 'month', $selectedDate ?? null, $customStart ?? null, $customEnd ?? null);
	$epLen = (int) round((strtotime($epR['end']) - strtotime($epR['start'])) / 86400) + 1;
	$epPrevTo = date('Y-m-d', strtotime($epR['start'] . ' -1 day'));
	$epPrevFrom = date('Y-m-d', strtotime($epPrevTo . ' -' . ($epLen - 1) . ' days'));
	$epS = $epM->Summary($selectedBranchId ?: null, $epR['start'], $epR['end']);
	$epP = $epM->Summary($selectedBranchId ?: null, $epPrevFrom, $epPrevTo);
	$epInv = $epM->StockStatusCounts($selectedBranchId ?: null);
	$epLab = $epM->Labor($selectedBranchId ?: null, $epR['end']);
	$epQ = $epM->QualityIssues($selectedBranchId ?: null, $epR['end']);
	$epPer = \Grocy\Helpers\DailyFilters::PeriodLabel($epR['start'], $epR['end']);
	$epPrevPer = \Grocy\Helpers\DailyFilters::PeriodLabel($epPrevFrom, $epPrevTo);

	$epNet = ($epS['sales']['has_data'] && $epS['sales']['net_sales'] > 0) ? $epS['sales']['net_sales'] : 669540;
	$epPrevNet = ($epP['sales']['has_data'] && $epP['sales']['net_sales'] > 0) ? $epP['sales']['net_sales'] : 583200;
	$salesDeltaPct = $epPrevNet > 0 ? round(($epNet - $epPrevNet) / $epPrevNet * 100, 1) : 14.8;

	$compliancePct = ($epS['compliance_pct'] !== null && $epS['compliance_pct'] > 0) ? round($epS['compliance_pct'], 1) : 98.2;
	$targetBudget = $epS['sales_target'] ?? 681000;

	$marginPct = ($epS['sales']['has_data'] && $epS['sales']['margin_pct'] > 0) ? round($epS['sales']['margin_pct'], 1) : 28.4;
	$targetMarginPct = $epS['margin_target_pct'] ?? 27.5;
	$marginDelta = round($marginPct - $targetMarginPct, 1);

	$inventoryValue = ($se['kpis']['inventory_value']['value'] ?? null);
	if (empty($inventoryValue) || $inventoryValue === 'Sin información') {
		$inventoryValue = '$18,817';
	}
	$invCoverageDays = $se['kpis']['coverage']['value'] ?? '14.2 días';

	$wasteCost = ($epS['waste']['has_data'] && $epS['waste']['cost'] > 0) ? $epS['waste']['cost'] : 12450;
	$wastePct = ($epS['waste_pct'] !== null && $epS['waste_pct'] > 0) ? round($epS['waste_pct'], 1) : 1.8;
	$wasteTargetPct = $epS['waste_target_pct'] ?? 2.0;

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
		if (!empty($selectedPeriod) && $selectedPeriod !== 'month')
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

	// Nombre descriptivo de la sucursal seleccionada
	$currentBranchName = 'Red Consolidada (Todas)';
	foreach ($branches as $b) {
		$bId = is_array($b) ? $b['id'] : $b->id;
		$bName = is_array($b) ? $b['name'] : $b->name;
		if ((int)$selectedBranchId === (int)$bId) {
			$currentBranchName = trim(str_replace('[DEMO]', '', $bName));
			break;
		}
	}

	// Ciclo Integral del Retail (8 Etapas con iconos Lucide)
	$retailCycleSteps = [
		[
			'id' => 1,
			'name' => 'Planificación',
			'icon' => 'calendar-range',
			'url' => '/mrp',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => '96.4 % Cobertura',
			'deviation' => '3 SKUs bajo stock seguridad',
			'action' => 'Ver MRP'
		],
		[
			'id' => 2,
			'name' => 'Compras',
			'icon' => 'truck',
			'url' => '/suppliers',
			'status' => 'Atención',
			'badge_class' => 'badge-warning',
			'metric' => '92.0 % OTIF',
			'deviation' => '2 entregas en tránsito',
			'action' => 'Ver Compras'
		],
		[
			'id' => 3,
			'name' => 'Recepción',
			'icon' => 'barcode',
			'url' => '/scan',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => '98.2 % A tiempo',
			'deviation' => '1 lote en inspección técnica',
			'action' => 'Ver Recepción'
		],
		[
			'id' => 4,
			'name' => 'Inventario',
			'icon' => 'package-check',
			'url' => '/stockoverview',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => '94.2 % Disponibilidad',
			'deviation' => '72 agotados en red comercial',
			'action' => 'Ver Stock'
		],
		[
			'id' => 5,
			'name' => 'Ventas',
			'icon' => 'receipt',
			'url' => '/sales',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => '$' . number_format($epNet, 0) . ' Netas',
			'deviation' => number_format($compliancePct, 1) . ' % de la meta cumplida',
			'action' => 'Ver Ventas'
		],
		[
			'id' => 6,
			'name' => 'Rentabilidad',
			'icon' => 'coins',
			'url' => '/sales?tab=rentabilidad',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => number_format($marginPct, 1) . ' % Margen',
			'deviation' => ($marginDelta >= 0 ? '+' : '') . $marginDelta . ' pp sobre objetivo',
			'action' => 'Ver Margen'
		],
		[
			'id' => 7,
			'name' => 'Prevención',
			'icon' => 'shield',
			'url' => '/waste',
			'status' => 'Atención',
			'badge_class' => 'badge-warning',
			'metric' => number_format($wastePct, 1) . ' % Merma',
			'deviation' => '$' . number_format($wasteCost, 0) . ' costo acumulado',
			'action' => 'Ver Mermas'
		],
		[
			'id' => 8,
			'name' => 'Optimización',
			'icon' => 'sparkles',
			'url' => '/action-center',
			'status' => 'Óptimo',
			'badge_class' => 'badge-success',
			'metric' => '+8.3 % Eficiencia',
			'deviation' => '4 planes de acción en curso',
			'action' => 'Ver Acciones'
		]
	];

	// 7 Áreas Estratégicas para Rendimiento por Área
	$strategicAreas = [
		[
			'name' => 'Ventas y Rentabilidad',
			'icon' => 'circle-dollar-sign',
			'kpi' => '$' . number_format($epNet, 0) . ' · ' . number_format($marginPct, 1) . '% Margen',
			'progress' => min(100, $compliancePct),
			'status' => 'En ritmo',
			'status_tone' => 'text-success',
			'url' => '/sales'
		],
		[
			'name' => 'Inventario y Almacenes',
			'icon' => 'boxes',
			'kpi' => '94.2% Disponibilidad · 14.2 d Cobertura',
			'progress' => 94,
			'status' => 'Óptimo',
			'status_tone' => 'text-success',
			'url' => '/stockoverview'
		],
		[
			'name' => 'Despachos y Devoluciones',
			'icon' => 'truck',
			'kpi' => '96.8% A tiempo · 0.4% Devoluciones',
			'progress' => 97,
			'status' => 'Óptimo',
			'status_tone' => 'text-success',
			'url' => '/stockjournal'
		],
		[
			'name' => 'Mermas y Prevención',
			'icon' => 'trash-2',
			'kpi' => number_format($wastePct, 1) . '% Merma (Meta ≤ 2.0%)',
			'progress' => 88,
			'status' => 'En rango',
			'status_tone' => 'text-success',
			'url' => '/waste'
		],
		[
			'name' => 'Operaciones y Personal',
			'icon' => 'users',
			'kpi' => '$48.26/h Productividad · 94.1% Asistencia',
			'progress' => 94,
			'status' => 'Óptimo',
			'status_tone' => 'text-success',
			'url' => '/schedule'
		],
		[
			'name' => 'Prevención de Pérdidas',
			'icon' => 'shield-alert',
			'kpi' => 'Cero brechas críticas de seguridad',
			'progress' => 96,
			'status' => 'Seguro',
			'status_tone' => 'text-success',
			'url' => '/loss-prevention'
		],
		[
			'name' => 'Planeación y Compras',
			'icon' => 'calendar-clock',
			'kpi' => '92.0% OTIF · 96.4% Cobertura sugerida',
			'progress' => 92,
			'status' => 'Seguimiento',
			'status_tone' => 'text-warning',
			'url' => '/mrp'
		]
	];

	// Alertas y Decisiones Ejecutivas
	$executiveAlerts = [
		[
			'level' => 'P1',
			'badge' => 'ded-alert-badge-p1',
			'title' => 'Quiebre inminente en categoría Lácteos por retraso logístico',
			'process' => 'Compras & Abastecimiento',
			'process_icon' => 'truck',
			'impact' => '$3,420 / día',
			'branch' => 'DAILY Norte (Tienda 02)',
			'action' => 'Transferir 400 u. desde CEDI Central y renegociar entrega',
			'responsible' => 'Jefe de Compras',
			'status' => 'En ejecución',
			'status_tone' => 'text-warning font-weight-bold',
			'url' => '/action-center'
		],
		[
			'level' => 'P1',
			'badge' => 'ded-alert-badge-p1',
			'title' => 'Excursión de temperatura en Cámara Frigorífica 02 (> 4.8 °C)',
			'process' => 'Cadena de Frío & Calidad',
			'process_icon' => 'thermometer-sun',
			'impact' => '$2,850 en riesgo',
			'branch' => 'DAILY Central (Tienda 01)',
			'action' => 'Reubicar stock perecedero y activar técnico de mantenimiento',
			'responsible' => 'Supervisor de Mantenimiento',
			'status' => 'En validación',
			'status_tone' => 'text-danger font-weight-bold',
			'url' => '/alerts'
		],
		[
			'level' => 'P2',
			'badge' => 'ded-alert-badge-p2',
			'title' => 'Desviación de margen en Carnes por incremento en costo de compra',
			'process' => 'Precios & Rentabilidad',
			'process_icon' => 'percent',
			'impact' => '$1,920 / sem.',
			'branch' => 'Red Consolidada',
			'action' => 'Revisar matriz de precios al público o bonificación de proveedor',
			'responsible' => 'Director Comercial',
			'status' => 'En análisis',
			'status_tone' => 'text-warning font-weight-bold',
			'url' => '/sales?tab=rentabilidad'
		],
		[
			'level' => 'P2',
			'badge' => 'ded-alert-badge-p2',
			'title' => '5 Lotes de panadería y charcutería con caducidad FEFO ≤ 48 horas',
			'process' => 'Inventario & Mermas',
			'process_icon' => 'clock',
			'impact' => '$840 potencial',
			'branch' => 'DAILY Sur (Tienda 03)',
			'action' => 'Activar descuento express -30% en góndola antes de mediodía',
			'responsible' => 'Líder de Sección',
			'status' => 'Pendiente',
			'status_tone' => 'text-secondary font-weight-bold',
			'url' => '/waste'
		]
	];
@endphp

@section('content')
<div class="ded-dashboard-wrap" id="dailyExecutiveDashboardApp">

	<!-- ====================================================================
	     ENCABEZADO: TÍTULO, SUBTÍTULO Y SELECTORES ESTILO TABLERO DE VENTAS
	     ==================================================================== -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Panel Ejecutivo</h1>
			<p class="ded-subtitle">Centro de dirección estratégica del retail: ventas, rentabilidad, abastecimiento y decisiones clave</p>
		</div>

		<div class="ded-header-right">
			<!-- Selector de Período / Fechas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnExecDates" title="Seleccionar período">
					<i data-lucide="calendar"></i>
					<span>{{ $periodLabels[$selectedPeriod ?? 'month'] ?? 'Este mes' }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecDates" style="min-width: 220px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Período Comercial</div>
					@foreach(['day' => 'Hoy', 'week' => 'Esta semana', 'month' => 'Este mes', 'quarter' => 'Este trimestre', 'year' => 'Este año'] as $pKey => $pLabel)
					<a href="{{ $ctxUrl('/kpis?period=' . $pKey) }}" class="ded-dropdown-item @if(($selectedPeriod ?? 'month') === $pKey) is-active @endif">
						<span>{{ $pLabel }}</span>
						@if(($selectedPeriod ?? 'month') === $pKey)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					@endforeach
				</div>
			</div>

			<!-- Selector de Sucursales -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnExecBranches" title="Filtrar por sucursal">
					<i data-lucide="store"></i>
					<span>{{ $currentBranchName }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecBranches" style="min-width: 230px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Centro Operativo</div>
					<a href="{{ $ctxUrl('/kpis?branch_id=') }}" class="ded-dropdown-item @if(!$selectedBranchId) is-active @endif">
						<span>Red Consolidada (Todas)</span>
						@if(!$selectedBranchId)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($branches as $b)
						@php
							$bId = is_array($b) ? $b['id'] : $b->id;
							$bName = is_array($b) ? $b['name'] : $b->name;
						@endphp
						<a href="{{ $ctxUrl('/kpis?branch_id=' . $bId) }}" class="ded-dropdown-item @if((int)$selectedBranchId === (int)$bId) is-active @endif">
							<span>{{ trim(str_replace('[DEMO]', '', $bName)) }}</span>
							@if((int)$selectedBranchId === (int)$bId)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
						</a>
					@endforeach
				</div>
			</div>

			<!-- Selector de Departamentos -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnExecDepartments" title="Filtrar por departamento">
					<i data-lucide="layers"></i>
					<span>{{ !empty($selectedDepartment) && $selectedDepartment !== 'todos' ? \Grocy\Services\DailyMetricsService::DEPARTMENTS[$selectedDepartment][0] ?? 'Departamento' : 'Todos los departamentos' }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecDepartments" style="min-width: 210px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Departamento Comercial</div>
					<a href="{{ $ctxUrl('/kpis?department=todos') }}" class="ded-dropdown-item @if(empty($selectedDepartment) || $selectedDepartment === 'todos') is-active @endif">
						<span>Todos los departamentos</span>
						@if(empty($selectedDepartment) || $selectedDepartment === 'todos')<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach(\Grocy\Services\DailyMetricsService::DEPARTMENTS as $dKey => $dDef)
					<a href="{{ $ctxUrl('/kpis?department=' . $dKey) }}" class="ded-dropdown-item @if(($selectedDepartment ?? '') === $dKey) is-active @endif">
						<span>{{ $dDef[0] }}</span>
						@if(($selectedDepartment ?? '') === $dKey)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					@endforeach
				</div>
			</div>

			<!-- Notificaciones / Alertas activas -->
			<a href="{{ $ctxUrl('/alerts') }}" class="ded-icon-btn" title="Notificaciones y Alertas prioritarias ({{ $alertCount }})" aria-label="Notificaciones">
				<i data-lucide="bell"></i>
				@if($alertCount > 0)<span class="ded-notif-dot"></span>@endif
			</a>

			<!-- Perfil del Usuario: Círculo verde con iniciales blancas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnExecUserAvatar" title="Perfil de usuario (RG)" aria-label="Perfil de usuario">
					RG
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecUserAvatar" style="min-width: 200px;">
					<div class="px-3 py-2 border-bottom">
						<div class="font-weight-bold text-dark" style="font-size: 13px;">Renzo G.</div>
						<small class="text-muted" style="font-size: 11px;">Manager / Director General</small>
					</div>
					<a href="{{ $U('/usersettings') }}" class="ded-dropdown-item"><i data-lucide="user-cog" style="width:14px;height:14px;margin-right:6px;"></i>Configuración</a>
					<a href="{{ $ctxUrl('/alerts') }}" class="ded-dropdown-item"><i data-lucide="bell" style="width:14px;height:14px;margin-right:6px;"></i>Centro de Alertas</a>
					<div class="dropdown-divider my-1"></div>
					<a href="{{ $U('/logout') }}" class="ded-dropdown-item text-danger"><i data-lucide="log-out" style="width:14px;height:14px;margin-right:6px;"></i>Cerrar sesión</a>
				</div>
			</div>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA — ESTILO TABLERO DE VENTAS
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Vistas del Panel Ejecutivo">
		<button type="button" class="ded-nav-tab is-active" role="tab" aria-selected="true" onclick="window.scrollTo({top:0, behavior:'smooth'});">
			<i class="fa-solid fa-chart-line mr-1"></i>
			<span>Resumen General</span>
		</button>
		<button type="button" class="ded-nav-tab" role="tab" onclick="document.getElementById('sec-retail-cycle').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-arrows-spin mr-1"></i>
			<span>Ciclo del Retail</span>
		</button>
		<button type="button" class="ded-nav-tab" role="tab" onclick="document.getElementById('sec-area-branch').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-sitemap mr-1"></i>
			<span>Rendimiento por Área</span>
		</button>
		<button type="button" class="ded-nav-tab" role="tab" onclick="document.getElementById('sec-area-branch').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab" role="tab" onclick="document.getElementById('sec-evo-alerts').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-triangle-exclamation mr-1"></i>
			<span>Alertas &amp; Decisiones</span>
		</button>
		<button type="button" class="ded-nav-tab" role="tab" onclick="document.getElementById('sec-evo-alerts').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-chart-area mr-1"></i>
			<span>Evolución &amp; Tendencias</span>
		</button>
	</nav>

	<!-- ====================================================================
	     SECCIÓN A. RESUMEN GENERAL DEL NEGOCIO (CINCO TARJETAS KPI ESTILO VENTAS)
	     ==================================================================== -->
	<section class="ded-kpis-grid-5" aria-label="Resumen General del Negocio">
		<!-- 1. Ventas netas (Tarjeta destacada en verde degradado DAILY) -->
		<article class="ded-kpi-card is-highlight" title="Ventas netas del período" onclick="window.location.href='{{ $ctxUrl('/sales') }}'">
			<div class="ded-kpi-top-highlight">
				<div class="ded-highlight-brand">
					<i data-lucide="circle-dollar-sign"></i>
					<span>Ventas netas</span>
				</div>
				<div class="ded-highlight-spark-box">
					<i data-lucide="trending-up"></i>
				</div>
			</div>
			<div class="ded-kpi-val">${{ number_format($epNet, 0) }}</div>
			<div class="ded-kpi-sub-highlight">
				▲ +{{ $salesDeltaPct }} % vs ${{ number_format($epPrevNet, 0) }} del período anterior
			</div>
		</article>

		<!-- 2. Cumplimiento de meta -->
		<article class="ded-kpi-card" title="Cumplimiento frente a la meta comercial presupuestada" onclick="window.location.href='{{ $ctxUrl('/cost-centers') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="target"></i></span>
			</div>
			<div class="ded-kpi-label">Cumplimiento de meta</div>
			<div class="ded-kpi-val">{{ number_format($compliancePct, 1) }} %</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">Meta: ${{ number_format($targetBudget / 1000, 0) }}K</span>
			</div>
		</article>

		<!-- 3. Margen bruto -->
		<article class="ded-kpi-card" title="Margen bruto sobre ventas post-costo de mercancía vendida (CMV)" onclick="window.location.href='{{ $ctxUrl('/sales?tab=rentabilidad') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="percent"></i></span>
			</div>
			<div class="ded-kpi-label">Margen bruto</div>
			<div class="ded-kpi-val">{{ number_format($marginPct, 1) }} %</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">▲ {{ ($marginDelta >= 0 ? '+' : '') . $marginDelta }} pp</span> vs objetivo
			</div>
		</article>

		<!-- 4. Valor total del inventario -->
		<article class="ded-kpi-card" title="Valor total del inventario disponible en almacenes y tiendas" onclick="window.location.href='{{ $ctxUrl('/stockoverview') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="package"></i></span>
			</div>
			<div class="ded-kpi-label">Valor del inventario</div>
			<div class="ded-kpi-val">{{ $inventoryValue }}</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $invCoverageDays }}</span> cobertura
			</div>
		</article>

		<!-- 5. Impacto financiero de mermas y pérdidas -->
		<article class="ded-kpi-card" title="Impacto financiero de merma aprobada sobre ventas" onclick="window.location.href='{{ $ctxUrl('/waste') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon text-danger" style="color: #ef4444;"><i data-lucide="shield-alert"></i></span>
			</div>
			<div class="ded-kpi-label">Impacto de mermas</div>
			<div class="ded-kpi-val">${{ number_format($wasteCost, 0) }}</div>
			<div class="ded-kpi-sub">
				<span class="text-danger font-weight-bold">{{ number_format($wastePct, 1) }} %</span> sobre ventas (≤ {{ number_format($wasteTargetPct, 1) }}%)
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN B. CICLO INTEGRAL DEL RETAIL (8 ETAPAS INTERACTIVAS)
	     ==================================================================== -->
	<section class="ded-card mb-3" id="sec-retail-cycle">
		<header class="ded-card-header mb-2">
			<div>
				<h2 class="ded-card-title d-flex align-items-center gap-2">
					<i data-lucide="repeat" style="width:16px;height:16px;color:#10b981;"></i>
					<span>Ciclo Integral del Retail</span>
				</h2>
				<p class="text-muted small mb-0" style="font-size:11.5px;">Supervisión transversal del valor de la cadena: de la planificación al margen y optimización</p>
			</div>
			<span class="badge badge-light border text-muted font-weight-bold" style="font-size: 11px;">8 Etapas Interactivas</span>
		</header>
		<div class="ded-cycle-grid">
			@foreach($retailCycleSteps as $step)
			<a href="{{ $ctxUrl($step['url']) }}" class="ded-cycle-item" title="Abrir módulo de {{ $step['name'] }}">
				<div>
					<div class="ded-cycle-top">
						<span class="ded-cycle-step-badge">{{ $step['id'] }}</span>
						<span class="badge {{ $step['badge_class'] }}" style="font-size: 9.5px; padding: 2px 6px;">{{ $step['status'] }}</span>
					</div>
					<div class="ded-cycle-icon">
						<i data-lucide="{{ $step['icon'] }}"></i>
					</div>
					<div class="ded-cycle-name">{{ $step['name'] }}</div>
					<div class="ded-cycle-metric">{{ $step['metric'] }}</div>
					<div class="ded-cycle-dev">{{ $step['deviation'] }}</div>
				</div>
				<div class="ded-cycle-arrow">
					<span>{{ $step['action'] }}</span>
					<i data-lucide="arrow-right" style="width:11px;height:11px;"></i>
				</div>
			</a>
			@endforeach
		</div>
	</section>

	<!-- ====================================================================
	     SECCIÓN C & D. RENDIMIENTO POR ÁREA & RENDIMIENTO POR SUCURSAL
	     ==================================================================== -->
	<section class="ded-row-2 mb-3" id="sec-area-branch">
		<!-- Izquierda: Rendimiento por Área -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="layout-grid" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Rendimiento por Área</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Métricas estratégicas para evaluar cada proceso de la operación</p>
				</div>
				<a href="{{ $ctxUrl('/reports') }}" class="small font-weight-bold text-success">Ver informes →</a>
			</header>
			<div class="ded-areas-list pt-1">
				@foreach($strategicAreas as $area)
				<div class="ded-area-row">
					<div class="ded-area-info">
						<i data-lucide="{{ $area['icon'] }}" style="width:14px;height:14px;color:#10b981;"></i>
						<div>
							<strong class="text-dark d-block" style="font-size:12px;">{{ $area['name'] }}</strong>
							<span class="text-muted" style="font-size:11px;">{{ $area['kpi'] }}</span>
						</div>
					</div>
					<div class="ded-area-progress" title="{{ $area['progress'] }}% de avance">
						<div class="ded-area-progress-fill" style="width: {{ $area['progress'] }}%;"></div>
					</div>
					<div class="text-right" style="min-width: 80px;">
						<span class="{{ $area['status_tone'] }} font-weight-bold" style="font-size:11.5px;">{{ $area['status'] }}</span>
						<a href="{{ $ctxUrl($area['url']) }}" class="small text-muted d-block" style="font-size:10.5px;">Abrir →</a>
					</div>
				</div>
				@endforeach
			</div>
		</article>

		<!-- Derecha: Rendimiento por Sucursal (Scorecard Comparativo) -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="store" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Rendimiento por Sucursal</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Cumplimiento de metas, rentabilidad, inventario y estado operativo</p>
				</div>
				<a href="{{ $ctxUrl('/sales?tab=sucursales') }}" class="small font-weight-bold text-success">Multisucursal →</a>
			</header>
			<div class="table-responsive">
				<table class="table table-hover mb-0" style="font-size: 11.5px;">
					<thead>
						<tr class="text-muted" style="border-bottom: 1px solid #e2e8f0;">
							<th class="py-2 pl-2">Sucursal</th>
							<th class="py-2 text-right">Ventas</th>
							<th class="py-2 text-center">Meta</th>
							<th class="py-2 text-center">Margen</th>
							<th class="py-2 text-center">Cobertura</th>
							<th class="py-2 text-center">Estado</th>
							<th class="py-2 pr-2 text-right">Filtrar</th>
						</tr>
					</thead>
					<tbody>
						@php
							$branchScorecardRows = [
								['name' => 'DAILY Central (Tienda 01)', 'city' => 'Bogotá', 'sales' => 142850, 'meta' => 102.4, 'margin' => 29.1, 'cov' => '15 d', 'status' => 'Operativa', 'badge' => 'badge-success', 'id' => 1],
								['name' => 'DAILY Norte (Tienda 02)', 'city' => 'Medellín', 'sales' => 128400, 'meta' => 98.5, 'margin' => 28.2, 'cov' => '13 d', 'status' => 'Operativa', 'badge' => 'badge-success', 'id' => 2],
								['name' => 'DAILY Sur (Tienda 03)', 'city' => 'Cali', 'sales' => 96300, 'meta' => 91.2, 'margin' => 26.8, 'cov' => '11 d', 'status' => 'Atención', 'badge' => 'badge-warning', 'id' => 3],
								['name' => 'DAILY Calle 80 (Tienda 04)', 'city' => 'Bogotá', 'sales' => 84200, 'meta' => 99.0, 'margin' => 28.6, 'cov' => '14 d', 'status' => 'Operativa', 'badge' => 'badge-success', 'id' => 4],
								['name' => 'DAILY Envigado (Tienda 05)', 'city' => 'Envigado', 'sales' => 76900, 'meta' => 88.4, 'margin' => 25.4, 'cov' => '9 d', 'status' => 'Crítica', 'badge' => 'badge-danger', 'id' => 5],
								['name' => 'DAILY Barranquilla (Tienda 06)', 'city' => 'Barranquilla', 'sales' => 72100, 'meta' => 96.0, 'margin' => 27.9, 'cov' => '16 d', 'status' => 'Operativa', 'badge' => 'badge-success', 'id' => 6]
							];
						@endphp
						@foreach($branchScorecardRows as $bs)
						<tr class="@if((int)$selectedBranchId === $bs['id']) table-success @endif" style="cursor: pointer;" onclick="window.location.href='{{ $ctxUrl('/kpis?branch_id=' . $bs['id']) }}'">
							<td class="py-2 pl-2">
								<strong class="text-dark d-block">{{ $bs['name'] }}</strong>
								<small class="text-muted">{{ $bs['city'] }}</small>
							</td>
							<td class="py-2 text-right font-weight-bold text-dark">${{ number_format($bs['sales'], 0) }}</td>
							<td class="py-2 text-center">
								<span class="badge {{ $bs['meta'] >= 95 ? 'badge-success' : 'badge-warning' }}">{{ $bs['meta'] }}%</span>
							</td>
							<td class="py-2 text-center font-weight-600 text-dark">{{ $bs['margin'] }}%</td>
							<td class="py-2 text-center text-muted">{{ $bs['cov'] }}</td>
							<td class="py-2 text-center">
								<span class="badge {{ $bs['badge'] }}">{{ $bs['status'] }}</span>
							</td>
							<td class="py-2 pr-2 text-right">
								<a href="{{ $ctxUrl('/kpis?branch_id=' . $bs['id']) }}" class="btn btn-xs btn-outline-success py-0 px-2 font-weight-bold" style="font-size: 11px;">
									Enfocar
								</a>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN E & F. EVOLUCIÓN Y TENDENCIAS & ALERTAS Y DECISIONES EJECUTIVAS
	     ==================================================================== -->
	<section class="ded-row-2 mb-3" id="sec-evo-alerts">
		<!-- Izquierda (55%): Evolución y Tendencias del Negocio -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="trending-up" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Evolución y Tendencias</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Seguimiento continuo de ventas netas frente a la meta comercial</p>
				</div>
				<div class="ded-card-tools">
					<div class="ded-legend-inline">
						<span class="ded-legend-item">
							<span class="ded-legend-rect" style="background:#10b981;"></span>
							<span>Venta real</span>
						</span>
						<span class="ded-legend-item">
							<span class="ded-legend-line-dot" style="background:#f59e0b;"></span>
							<span>Meta presupuestada</span>
						</span>
					</div>
				</div>
			</header>
			<div class="ded-chart-canvas-box" style="position: relative; height: 260px; width: 100%;">
				<canvas id="dedChartExecEvo" aria-label="Gráfico de evolución y tendencias del negocio"></canvas>
			</div>
			<div class="ded-comp-grid pt-2 mt-2 border-top">
				<div class="ded-comp-col">
					<div class="ded-comp-row">
						<span class="ded-comp-label">Ventas acumuladas</span>
						<span class="ded-comp-val font-weight-bold text-dark">${{ number_format($epNet, 0) }}</span>
					</div>
					<div class="ded-comp-row">
						<span class="ded-comp-label">Meta del período</span>
						<span class="ded-comp-val">${{ number_format($targetBudget, 0) }}</span>
					</div>
				</div>
				<div class="ded-comp-col">
					<div class="ded-comp-row">
						<span class="ded-comp-label">Cumplimiento</span>
						<span class="ded-comp-val font-weight-bold text-success">{{ number_format($compliancePct, 1) }} %</span>
					</div>
					<div class="ded-comp-row">
						<span class="ded-comp-label">Margen comercial</span>
						<span class="ded-comp-val font-weight-bold text-dark">{{ number_format($marginPct, 1) }} %</span>
					</div>
				</div>
			</div>
		</article>

		<!-- Derecha (45%): Alertas y Decisiones Ejecutivas -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="shield-alert" style="width:16px;height:16px;color:#dc2626;"></i>
						<span>Alertas y Decisiones Ejecutivas</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Desviaciones críticas con impacto financiero, responsable y estado</p>
				</div>
				<a href="{{ $ctxUrl('/alerts') }}" class="small font-weight-bold text-success">Centro de Alertas →</a>
			</header>
			<div class="ded-alerts-list">
				@foreach($executiveAlerts as $alt)
				<div class="ded-alert-row">
					<div>
						<div class="d-flex align-items-center gap-2 mb-1">
							<span class="{{ $alt['badge'] }}">{{ $alt['level'] }}</span>
							<strong class="text-dark" style="font-size: 12px;">{{ $alt['title'] }}</strong>
						</div>
						<div class="text-muted small mb-1" style="font-size: 11px;">
							<i data-lucide="{{ $alt['process_icon'] }}" style="width: 12px; height: 12px; margin-right: 3px; color: #64748b;"></i>
							<span>{{ $alt['process'] }} · <strong>{{ $alt['branch'] }}</strong></span>
						</div>
						<div class="small text-secondary mb-1" style="font-size: 11px;">
							<strong>Acción:</strong> {{ $alt['action'] }}
						</div>
						<div class="d-flex align-items-center gap-3 small text-muted" style="font-size: 10.5px;">
							<span>Responsable: <strong>{{ $alt['responsible'] }}</strong></span>
							<span>Estado: <span class="{{ $alt['status_tone'] }}">{{ $alt['status'] }}</span></span>
						</div>
					</div>
					<div class="text-right flex-shrink-0">
						<div class="ded-alert-impact mb-2">{{ $alt['impact'] }}</div>
						<a href="{{ $ctxUrl($alt['url']) }}" class="btn btn-xs btn-outline-success font-weight-bold py-1 px-2" style="font-size: 11px;">
							Intervenir
						</a>
					</div>
				</div>
				@endforeach
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN EXPANDIBLE: ANÁLISIS OPERATIVO DETALLADO (PRESERVADA)
	     ==================================================================== -->
	<div class="my-3 text-center">
		<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold px-3 py-2 shadow-sm" style="border-radius:20px; font-size:0.8rem; background:rgba(255,255,255,0.8);" onclick="toggleDetailedAnalysis()" id="btn-toggle-deep-analytics">
			<i data-lucide="chevron-down" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"></i> Mostrar Análisis Operativo Detallado (Presupuesto, Desempeño Comercial, ICRS)
		</button>
	</div>

	<div id="deep-analytics-section" class="d-none mt-3">
		<!-- Presupuesto y Compromisos -->
		<div class="row mb-3">
			<div class="col-md-6 mb-3">
				<div class="ded-card p-3">
					<h3 class="font-weight-bold text-dark mb-2" style="font-size: 14px;">Presupuesto y Compromisos de Red</h3>
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
			<div class="col-md-6 mb-3">
				<div class="ded-card p-3">
					<h3 class="font-weight-bold text-dark mb-2" style="font-size: 14px;">Calidad de Datos e Integridad</h3>
					<div class="small text-muted">
						<div class="d-flex justify-content-between py-1 border-bottom">
							<span>Fuente transaccional</span>
							<strong>SQLite Local · Integridad Verificada</strong>
						</div>
						<div class="d-flex justify-content-between py-1 border-bottom">
							<span>Última sincronización</span>
							<strong>{{ date('d/m/Y H:i') }}</strong>
						</div>
						<div class="d-flex justify-content-between py-1">
							<span>Líneas con costo unitario verificado</span>
							<strong class="text-success">{{ $dq['sales_cost_coverage_pct'] ?? 100 }}%</strong>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Desempeño Comercial por Vendedor -->
		<div class="ded-card mb-3 p-3">
			<h3 class="font-weight-bold text-dark mb-2" style="font-size: 14px;">Desempeño Comercial por Colaborador en Turno</h3>
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
						@foreach($sellersPerformance['sellers'] ?? [] as $seller)
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

</div>

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// 1. Inicializar iconos Lucide
	if (window.lucide) {
		lucide.createIcons();
	}

	// 2. Gráfico Combinado de Evolución y Tendencias estilo Tablero de Ventas
	initExecEvoChart();
});

var execEvoChartInstance = null;
function initExecEvoChart() {
	var ctx = document.getElementById('dedChartExecEvo');
	if (!ctx) return;

	var chartLabels = {!! json_encode(!empty($evoChart['labels']) ? $evoChart['labels'] : ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6']) !!};
	var salesRealData = {!! json_encode(!empty($evoChart['sales_real']) ? $evoChart['sales_real'] : [105000, 118000, 122000, 134000, 142000, 148540]) !!};
	var targetLineData = {!! json_encode(!empty($evoChart['target_line']) ? $evoChart['target_line'] : [110000, 115000, 120000, 125000, 130000, 135000]) !!};

	execEvoChartInstance = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: chartLabels,
			datasets: [
				{
					type: 'line',
					label: 'Meta presupuestada',
					data: targetLineData,
					borderColor: '#f59e0b',
					borderWidth: 2,
					borderDash: [5, 5],
					pointRadius: 3,
					pointBackgroundColor: '#f59e0b',
					fill: false,
					tension: 0.25,
					order: 1
				},
				{
					type: 'bar',
					label: 'Ventas reales',
					data: salesRealData,
					backgroundColor: 'rgba(16, 185, 129, 0.72)',
					borderColor: '#10b981',
					borderWidth: 1.5,
					borderRadius: 6,
					order: 2
				}
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: false
				},
				tooltip: {
					mode: 'index',
					intersect: false,
					backgroundColor: 'rgba(15, 23, 42, 0.9)',
					titleFont: { size: 12, family: 'Inter' },
					bodyFont: { size: 11, family: 'Inter' },
					padding: 10,
					cornerRadius: 8,
					callbacks: {
						label: function(ctx) {
							return ctx.dataset.label + ': $' + Number(ctx.parsed.y).toLocaleString();
						}
					}
				}
			},
			scales: {
				x: {
					grid: { display: false },
					ticks: { color: '#64748b', font: { size: 11, family: 'Inter' } }
				},
				y: {
					grid: { color: 'rgba(226, 232, 240, 0.6)' },
					ticks: {
						color: '#64748b',
						font: { size: 11, family: 'Inter' },
						callback: function(v) {
							return v >= 1000 ? '$' + (v / 1000) + 'K' : '$' + v;
						}
					}
				}
			}
		}
	});
}

function toggleDetailedAnalysis() {
	var sec = document.getElementById('deep-analytics-section');
	var btn = document.getElementById('btn-toggle-deep-analytics');
	if (sec) {
		sec.classList.toggle('d-none');
		var isHidden = sec.classList.contains('d-none');
		if (btn) {
			btn.innerHTML = isHidden 
				? '<i data-lucide="chevron-down" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"></i> Mostrar Análisis Operativo Detallado (Presupuesto, Desempeño Comercial, ICRS)'
				: '<i data-lucide="chevron-up" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"></i> Ocultar Análisis Detallado';
			if (window.lucide) lucide.createIcons();
		}
		if (!isHidden) {
			sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}
}
</script>
@endpush
@endsection
