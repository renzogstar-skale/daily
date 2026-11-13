@php require_frontend_packages(['chartjs']); @endphp

@extends('layout.default')

@section('title', 'Panel Ejecutivo · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_executive_panel.css?v=', true) }}{{ daily_asset_version('/css/daily_executive_panel.css') }}" rel="stylesheet">
<style>
.ded-kpis-grid-6 {
	display: grid;
	grid-template-columns: repeat(6, minmax(0, 1fr));
	gap: 10px;
	margin-bottom: 14px;
	align-items: stretch;
}
@media (max-width: 1360px) {
	.ded-kpis-grid-6 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
	.ded-kpis-grid-6 { grid-template-columns: 1fr; }
}
.ded-kpis-grid-6 .ded-kpi-card { min-width: 0; }
.ded-select-native {
	border: none;
	background: transparent;
	color: inherit;
	font-weight: 700;
	font-size: 12.5px;
	outline: none;
	cursor: pointer;
	max-width: 195px;
}
html.daily-dark #dailyExecutiveDashboardApp .text-dark { color: #e2e8f0 !important; }
html.daily-dark #dailyExecutiveDashboardApp .text-secondary { color: #94a3b8 !important; }
html.daily-dark #dailyExecutiveDashboardApp .bg-light,
html.daily-dark #dailyExecutiveDashboardApp .badge-light { background-color: rgba(148,163,184,0.14) !important; color: #e2e8f0 !important; border-color: rgba(148,163,184,0.22) !important; }
html.daily-dark #dailyExecutiveDashboardApp .ded-dropdown-menu { background: #111827 !important; border-color: rgba(148,163,184,0.22) !important; }
html.daily-dark #dailyExecutiveDashboardApp .ded-dropdown-item { color: #e2e8f0 !important; }
html.daily-dark #dailyExecutiveDashboardApp .ded-dropdown-item:hover { background: rgba(16,185,129,0.14) !important; }
.ded-state-pill {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	white-space: nowrap;
}
.ded-state-pill.is-ok { background: rgba(16, 185, 129, 0.14); color: #047857; }
.ded-state-pill.is-warn { background: rgba(245, 158, 11, 0.16); color: #b45309; }
.ded-state-pill.is-crit { background: rgba(239, 68, 68, 0.14); color: #b91c1c; }
.ded-state-pill.is-no_data { background: rgba(100, 116, 139, 0.14); color: #475569; }
html.daily-dark .ded-state-pill.is-ok { color: #34d399; }
html.daily-dark .ded-state-pill.is-warn { color: #fbbf24; }
html.daily-dark .ded-state-pill.is-crit { color: #f87171; }
html.daily-dark .ded-state-pill.is-no_data { color: #94a3b8; }
.ded-def-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 10px;
}
.ded-def-item {
	border: 1px solid rgba(148, 163, 184, 0.22);
	border-radius: 10px;
	padding: 10px 12px;
	font-size: 11.5px;
}
</style>
@endpush

@php
	$ep = $executivePanel ?? [];
	$se = $stockExec ?? [];
	$fs = $ep['financial_summary'] ?? [];
	$sKpis = $ep['strategic_kpis'] ?? [];
	$decisions = $ep['priority_decisions'] ?? [];
	$budget = $ep['budget_summary'] ?? [];
	$ops = $ep['operations_status'] ?? [];
	$ca = $ep['corrective_actions'] ?? [];
	$kpiDefs = $ep['kpi_definitions'] ?? [];
	$deptRows = $ep['department_breakdown'] ?? [];
	$evoChart = $ep['evolution_chart'] ?? ['labels' => [], 'sales_real' => [], 'target_line' => [], 'prev_period' => []];
	$dq = $ep['data_quality'] ?? [];
	$branchRanking = $kpiData['branch_ranking'] ?? [];
	$deptCatalog = $departments ?? \Grocy\Services\DailyMetricsService::Departments();

	$epM = \Grocy\Services\DailyMetricsService::GetInstance();
	$epR = \Grocy\Services\DailyMetricsService::PeriodRange($selectedPeriod ?? 'month', $selectedDate ?? null, $customStart ?? null, $customEnd ?? null);
	$epLen = max(1, (int) round((strtotime($epR['end']) - strtotime($epR['start'])) / 86400) + 1);
	$epPrevTo = date('Y-m-d', strtotime($epR['start'] . ' -1 day'));
	$epPrevFrom = date('Y-m-d', strtotime($epPrevTo . ' -' . ($epLen - 1) . ' days'));
	$epP = $epM->Summary($selectedBranchId ?: null, $epPrevFrom, $epPrevTo);
	$epInv = $epM->StockStatusCounts($selectedBranchId ?: null);
	$epQ = $epM->QualityIssues($selectedBranchId ?: null, $epR['end']);
	$epPer = \Grocy\Helpers\DailyFilters::PeriodLabel($epR['start'], $epR['end']);
	$epPrevPer = \Grocy\Helpers\DailyFilters::PeriodLabel($epPrevFrom, $epPrevTo);

	$hasSalesData = !empty($fs['has_data']) || ($sKpis['sales']['has_data'] ?? false);
	// Mismo estado de datos que HOY: $0 solo con corte confirmado; sin corte, «Sin información» y sin variación
	$epSalesState = $epM->SalesDataState($selectedBranchId ?: null, $epR['start'], $epR['end'], (bool) ($sKpis['sales']['has_data'] ?? false));
	$epNet = $epSalesState === 'data' ? floatval($fs['net_sales'] ?? 0) : ($epSalesState === 'confirmed_zero' ? 0.0 : null);
	$epPrevNet = ($epP['sales']['has_data'] ?? false) ? floatval($epP['sales']['net_sales']) : null;
	$salesDeltaPct = ($epNet !== null && $epPrevNet !== null && $epPrevNet > 0)
		? round(($epNet - $epPrevNet) / $epPrevNet * 100, 1)
		: null;

	$compliancePct = $fs['compliance_pct'] ?? null;
	$targetBudget = $fs['sales_target'] ?? null;
	$complianceDeltaPp = $compliancePct !== null ? round($compliancePct - 100, 1) : null;

	$marginHasData = ($sKpis['margin']['has_data'] ?? false);
	$marginPct = $marginHasData ? ($fs['margin_pct'] ?? null) : null;
	$targetMarginPct = $fs['margin_target_pct'] ?? null;
	$marginDelta = ($marginPct !== null && $targetMarginPct !== null) ? round($marginPct - $targetMarginPct, 1) : null;

	$invValNum = floatval($fs['inventory_valuation'] ?? ($kpiData['total_inventory_valuation'] ?? 0));
	$inventoryValue = $invValNum > 0 ? '$' . number_format($invValNum, 0) : ($se['kpis']['inventory_value']['value'] ?? 'Sin información');
	$invCoverageDays = ($fs['dsi_days'] ?? null) !== null
		? number_format(floatval($fs['dsi_days']), 1) . ' días'
		: ($se['kpis']['coverage']['value'] ?? 'Sin información');
	$turnoverVal = floatval($fs['turnover'] ?? ($kpiData['inventory_turnover'] ?? 0));

	$wasteHasData = ($sKpis['waste']['has_data'] ?? false) || floatval($fs['waste_cost'] ?? 0) > 0;
	$wasteCost = floatval($fs['waste_cost'] ?? 0);
	$wasteRecovered = floatval($fs['waste_recovered'] ?? 0);
	$netWasteCost = floatval($fs['net_waste_cost'] ?? 0);
	$wastePct = $fs['waste_pct'] ?? null;
	$wasteTargetPct = floatval($fs['waste_target_pct'] ?? 2.0);

	$netOpContribution = ($sKpis['sales']['has_data'] ?? false) ? floatval($fs['net_operating_contribution'] ?? 0) : null;
	$netOpMarginPct = $fs['net_operating_margin_pct'] ?? null;

	$selectedDeptData = (!empty($selectedDepartment) && $selectedDepartment !== 'todos' && isset($deptRows[$selectedDepartment]))
		? $deptRows[$selectedDepartment]
		: null;

	$ctxUrl = function(string $path, array $overrides = []) use ($U, $selectedBranchId, $selectedPeriod, $selectedDepartment, $customStart, $customEnd, $selectedDate) {
		$hash = '';
		if (strpos($path, '#') !== false) {
			[$path, $hash] = explode('#', $path, 2);
			$hash = '#' . $hash;
		}
		$query = [
			'branch_id' => $selectedBranchId ?: '',
			'period' => $selectedPeriod ?? 'month',
			'department' => $selectedDepartment ?? 'todos',
			'date' => $selectedDate ?? date('Y-m-d'),
			'start_date' => $customStart ?? '',
			'end_date' => $customEnd ?? '',
		];
		foreach ($overrides as $k => $v) {
			$query[$k] = $v;
		}
		if (($query['period'] ?? 'month') !== 'custom') {
			unset($query['start_date'], $query['end_date']);
		}
		if (($query['date'] ?? '') === date('Y-m-d')) {
			unset($query['date']);
		}
		if (($query['department'] ?? 'todos') === 'todos' || ($query['department'] ?? '') === '') {
			unset($query['department']);
		}
		if (($query['branch_id'] ?? '') === '') {
			unset($query['branch_id']);
		}
		$query = array_filter($query, fn($v) => $v !== null && $v !== '');
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
		'custom' => ($customStart && $customEnd) ? ($customStart . ' al ' . $customEnd) : 'Personalizado'
	];

	$currentBranchName = 'Red Consolidada (Todas)';
	foreach ($branches as $b) {
		$bId = is_array($b) ? $b['id'] : $b->id;
		$bName = is_array($b) ? $b['name'] : $b->name;
		if ((int)$selectedBranchId === (int)$bId) {
			$currentBranchName = trim(str_replace('[DEMO]', '', $bName));
			break;
		}
	}

	// Riesgos y oportunidades calculadas desde datos reales
	$opportunities = [];
	$aboveGoalBranches = array_values(array_filter($branchRanking, fn($r) => ($r['compliance_pct'] ?? 0) >= 100 && !empty($r['has_activity'])));
	$belowGoalBranches = array_values(array_filter($branchRanking, fn($r) => ($r['compliance_pct'] ?? null) !== null && $r['compliance_pct'] < 90 && !empty($r['has_activity'])));
	if (count($aboveGoalBranches) > 0) {
		usort($aboveGoalBranches, fn($a, $b) => ($b['compliance_pct'] ?? 0) <=> ($a['compliance_pct'] ?? 0));
		$topB = $aboveGoalBranches[0];
		$opportunities[] = [
			'type' => 'Oportunidad',
			'tone' => 'success',
			'title' => count($aboveGoalBranches) . ' sucursal(es) superando meta (Líder: ' . $topB['clean_name'] . ' ' . number_format($topB['compliance_pct'], 1) . '%)',
			'detail' => 'Replicar mix de categoría y cobertura en tiendas bajo presupuesto.',
			'url' => '/kpis?branch_id=' . $topB['branch_id']
		];
	}
	if (count($belowGoalBranches) > 0) {
		usort($belowGoalBranches, fn($a, $b) => ($a['compliance_pct'] ?? 999) <=> ($b['compliance_pct'] ?? 999));
		$lowB = $belowGoalBranches[0];
		$gapAmt = ($lowB['sales_target'] && $lowB['sales'] !== null) ? max(0, $lowB['sales_target'] - $lowB['sales']) : 0;
		$opportunities[] = [
			'type' => 'Riesgo Comercial',
			'tone' => 'danger',
			'title' => count($belowGoalBranches) . ' sucursal(es) bajo 90% de meta (Mayor brecha: ' . $lowB['clean_name'] . ')',
			'detail' => ($gapAmt > 0 ? 'Brecha de venta: $' . number_format($gapAmt, 0) . ' · ' : '') . 'Revisar inventario y tracción comercial.',
			'url' => '/sales?branch_id=' . $lowB['branch_id']
		];
	}
	if ($epInv['expiring_products'] > 0) {
		$opportunities[] = [
			'type' => 'Riesgo FEFO',
			'tone' => 'warning',
			'title' => $epInv['expiring_products'] . ' SKU(s) con lotes próximos a vencer (≤ 7 días)',
			'detail' => 'Costo expuesto: $' . number_format($epInv['expiring_value'], 0) . '. Activar liquidación o transferencia.',
			'url' => '/traceability'
		];
	}
	if (($epInv['below_min'] + $epInv['out_of_stock']) > 0) {
		$opportunities[] = [
			'type' => 'Riesgo de Quiebre',
			'tone' => 'warning',
			'title' => $epInv['out_of_stock'] . ' agotados y ' . $epInv['below_min'] . ' SKUs bajo mínimo',
			'detail' => 'Afecta disponibilidad en góndola y captura de ventas.',
			'url' => '/mrp'
		];
	}

	$uName = $currentUser['name'] ?? (defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'Usuario');
	$uInitials = $currentUser['initials'] ?? mb_strtoupper(mb_substr($uName, 0, 2));
	$uRole = $currentUser['role'] ?? 'Dirección Ejecutiva';

	$hasChartData = !empty(array_filter($evoChart['sales_real'] ?? [], fn($v) => floatval($v) > 0))
		|| !empty(array_filter($evoChart['target_line'] ?? [], fn($v) => floatval($v) > 0));
@endphp

@section('content')
<div class="ded-dashboard-wrap" id="dailyExecutiveDashboardApp">

	<!-- ENCABEZADO Y FILTROS GLOBALES -->
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Panel Ejecutivo</h1>
			<p class="ded-subtitle">
				Cómo está operando el negocio · {{ $epPer }} · Comparado vs {{ $epPrevPer }} ·
				<span class="font-weight-bold text-success" title="Timestamp máximo real de ventas, mermas, stock y pedidos">
					Actualizado: {{ $dq['last_updated_formatted'] ?? 'Sin registros' }}
				</span>
			</p>
		</div>

		<div class="ded-header-right flex-wrap">
			<!-- Período / Fechas -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnExecDates" title="Seleccionar período">
					<i data-lucide="calendar"></i>
					<span>{{ $periodLabels[$selectedPeriod ?? 'month'] ?? 'Este mes' }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecDates" style="min-width: 260px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Período Estratégico</div>
					@foreach(['day' => 'Hoy / Día de corte', 'week' => 'Esta semana', 'month' => 'Este mes', 'quarter' => 'Este trimestre', 'year' => 'Este año'] as $pKey => $pLabel)
					<a href="{{ $ctxUrl('/kpis', ['period' => $pKey]) }}" class="ded-dropdown-item @if(($selectedPeriod ?? 'month') === $pKey) is-active @endif">
						<span>{{ $pLabel }}</span>
						@if(($selectedPeriod ?? 'month') === $pKey)<i data-lucide="check" style="width:13px;height:13px;"></i>@endif
					</a>
					@endforeach
					<div class="dropdown-divider my-1"></div>
					<form method="GET" action="{{ $U('/kpis') }}" class="px-3 py-2">
						@if($selectedBranchId)<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">@endif
						@if(!empty($selectedDepartment) && $selectedDepartment !== 'todos')<input type="hidden" name="department" value="{{ $selectedDepartment }}">@endif
						<input type="hidden" name="period" value="custom">
						<div class="small font-weight-bold text-muted mb-1" style="font-size:10px;">Rango personalizado</div>
						<div class="d-flex gap-1 mb-1">
							<input type="date" name="start_date" class="form-control form-control-sm" value="{{ $customStart ?? $epR['start'] }}" required>
							<input type="date" name="end_date" class="form-control form-control-sm" value="{{ $customEnd ?? $epR['end'] }}" required>
						</div>
						<button type="submit" class="btn btn-xs btn-success btn-block font-weight-bold">Aplicar rango</button>
					</form>
				</div>
			</div>

			<!-- Sucursal (mantiene #exec-select-branch para compatibilidad) -->
			<div class="ded-select-btn" title="Filtrar por sucursal">
				<i data-lucide="store"></i>
				<select id="exec-select-branch" class="ded-select-native" aria-label="Sucursal">
					@if(\Grocy\Helpers\DairyAccess::AllowedBranches() === null)
						<option value="" @if(!$selectedBranchId) selected @endif>Red Consolidada (Todas)</option>
					@endif
					@foreach($branches as $b)
						@php
							$bId = is_array($b) ? $b['id'] : $b->id;
							$bName = trim(str_replace('[DEMO]', '', is_array($b) ? $b['name'] : $b->name));
							$bCode = is_array($b) ? ($b['code'] ?? '') : ($b->code ?? '');
							if ($bCode !== '' && $bCode !== null && !str_contains($bName, $bCode)) $bName .= ' · ' . $bCode;
						@endphp
						<option value="{{ $bId }}" @if((int)$selectedBranchId === (int)$bId) selected @endif>{{ $bName }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-select-btn ded-dropdown-toggle" id="btnExecDepartments" title="Filtrar por departamento">
					<i data-lucide="layers"></i>
					<span>{{ !empty($selectedDepartment) && $selectedDepartment !== 'todos' ? (isset($deptCatalog[$selectedDepartment]) ? \Grocy\Services\DailyMetricsService::DepartmentLabel($selectedDepartment) : 'Departamento') : 'Todos los departamentos' }}</span>
					<i data-lucide="chevron-down" class="ded-chevron"></i>
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecDepartments" style="min-width: 220px;">
					<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: 10px; color: #64748b; padding: 6px 14px;">Departamento</div>
					<a href="{{ $ctxUrl('/kpis', ['department' => 'todos']) }}" class="ded-dropdown-item @if(empty($selectedDepartment) || $selectedDepartment === 'todos') is-active @endif">
						<span>Todos los departamentos</span>
					</a>
					<div class="dropdown-divider my-1"></div>
					@foreach($deptCatalog as $dKey => $dDef)
					<a href="{{ $ctxUrl('/kpis', ['department' => $dKey]) }}" class="ded-dropdown-item @if(($selectedDepartment ?? '') === $dKey) is-active @endif">
						<span>{{ \Grocy\Services\DailyMetricsService::DepartmentLabel($dKey) }}</span>
					</a>
					@endforeach
				</div>
			</div>

			<!-- Enlace rápido a HOY (operación en curso) -->
			<a href="{{ $ctxUrl('/today') }}" class="ded-select-btn text-decoration-none" title="Ir a HOY (qué necesita atención ahora)">
				<i data-lucide="activity" style="color:#16a34a;"></i>
				<span>Ver HOY</span>
			</a>

			<!-- Alertas reales -->
			<a href="{{ $ctxUrl('/alerts') }}" class="ded-icon-btn" title="Alertas abiertas ({{ $alertCount }})" aria-label="Notificaciones">
				<i data-lucide="bell"></i>
				@if($alertCount > 0)<span class="ded-notif-dot"></span>@endif
			</a>

			<!-- Perfil real de usuario -->
			<div class="ded-dropdown-container position-relative">
				<button type="button" class="ded-user-avatar ded-dropdown-toggle" id="btnExecUserAvatar" title="Perfil ({{ $uName }})" aria-label="Perfil de usuario">
					{{ $uInitials }}
				</button>
				<div class="ded-dropdown-menu dropdown-menu-right" aria-labelledby="btnExecUserAvatar" style="min-width: 205px;">
					<div class="px-3 py-2 border-bottom">
						<div class="font-weight-bold text-dark" style="font-size: 13px;">{{ $uName }}</div>
						<small class="text-muted" style="font-size: 11px;">{{ $uRole }}</small>
					</div>
					@if(\Grocy\Helpers\DairyAccess::CanOpen('/admin/economics'))
					<a href="{{ $U('/admin/economics') }}" class="ded-dropdown-item"><i data-lucide="sliders-horizontal" style="width:14px;height:14px;margin-right:6px;"></i>Economía y Metas</a>
					@endif
					<a href="{{ $U('/usersettings') }}" class="ded-dropdown-item"><i data-lucide="user-cog" style="width:14px;height:14px;margin-right:6px;"></i>Configuración</a>
					<a href="{{ $ctxUrl('/alerts') }}" class="ded-dropdown-item"><i data-lucide="bell" style="width:14px;height:14px;margin-right:6px;"></i>Centro de Alertas</a>
					<div class="dropdown-divider my-1"></div>
					<a href="{{ $U('/logout') }}" class="ded-dropdown-item text-danger"><i data-lucide="log-out" style="width:14px;height:14px;margin-right:6px;"></i>Cerrar sesión</a>
				</div>
			</div>
		</div>
	</header>

	@if($selectedDeptData)
	<div class="alert alert-light border d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3 mb-3" style="border-radius:10px; font-size:12px;">
		<div>
			<strong class="text-success"><i data-lucide="filter" style="width:14px;height:14px;vertical-align:-2px;"></i> Enfoque por departamento: {{ $selectedDeptData['name'] }}</strong>
			<span class="text-muted ml-2">
				Ventas netas: <strong>${{ number_format($selectedDeptData['sales'], 2) }}</strong> ·
				Utilidad bruta: <strong>${{ number_format($selectedDeptData['gross_profit'], 2) }}</strong> ({{ number_format($selectedDeptData['margin_pct'], 1) }}%) ·
				{{ number_format($selectedDeptData['units'], 0) }} unidades en {{ number_format($selectedDeptData['tickets'], 0) }} tickets.
				<em>Las tarjetas globales abajo reflejan la {{ $currentBranchName }}.</em>
			</span>
		</div>
		<a href="{{ $ctxUrl('/kpis', ['department' => 'todos']) }}" class="btn btn-xs btn-outline-secondary">Ver todos</a>
	</div>
	@endif

	<!-- BARRA DE NAVEGACIÓN INTERNA -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Vistas del Panel Ejecutivo">
		<button type="button" class="ded-nav-tab is-active" onclick="document.getElementById('sec-exec-kpis').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-chart-line mr-1"></i><span>KPIs Estratégicos</span>
		</button>
		<button type="button" class="ded-nav-tab" onclick="document.getElementById('sec-evo-finance').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-coins mr-1"></i><span>Tendencia y Finanzas</span>
		</button>
		<button type="button" class="ded-nav-tab" onclick="document.getElementById('sec-branch-dept').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-store mr-1"></i><span>Sucursales y Áreas</span>
		</button>
		<button type="button" class="ded-nav-tab" onclick="document.getElementById('sec-process-gov').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-diagram-project mr-1"></i><span>7 Procesos</span>
		</button>
		<button type="button" class="ded-nav-tab" onclick="document.getElementById('sec-risks-actions').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-list-check mr-1"></i><span>Riesgos y Acciones</span>
		</button>
		<button type="button" class="ded-nav-tab" onclick="document.getElementById('sec-kpi-definitions').scrollIntoView({behavior:'smooth'});">
			<i class="fa-solid fa-book-open mr-1"></i><span>Definiciones KPI</span>
		</button>
	</nav>

	<!-- ====================================================================
	     SECCIÓN A. 6 KPI ESTRATÉGICOS CONSOLIDADOS (SIN DATOS DE RELLENO)
	     ==================================================================== -->
	<section class="ded-kpis-grid-6" id="sec-exec-kpis" data-testid="exec-real-kpis" aria-label="Indicadores Estratégicos del Negocio">
		<!-- 1. Ventas netas -->
		@php $defSales = $kpiDefs['sales_net'] ?? []; @endphp
		<article class="dkpi ded-kpi-card is-highlight" title="{{ ($defSales['formula'] ?? 'Ventas brutas − IVA − descuentos.') . ' Responsable: ' . ($defSales['owner_name'] ?? 'Dirección Comercial') }}" onclick="window.location.href='{{ $ctxUrl('/sales') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="circle-dollar-sign"></i></span>
				<span class="badge badge-light" style="font-size:10px;">{{ $sKpis['sales']['state'] ?? 'Sin datos' }}</span>
			</div>
			<div class="ded-kpi-label">Ventas netas</div>
			<div class="ded-kpi-val">{{ $epNet !== null ? ('$' . number_format($epNet, 2)) : 'Sin información' }}</div>
			<div class="ded-kpi-sub">
				@if($salesDeltaPct !== null)
					<span class="ded-kpi-badge-green">{{ $salesDeltaPct >= 0 ? '▲ +' : '▼ ' }}{{ $salesDeltaPct }} %</span> vs ${{ number_format($epPrevNet, 0) }}
				@else
					<span>Sin comparativo previo</span>
				@endif
			</div>
		</article>

		<!-- 2. Cumplimiento de meta (real sin tope 100%) -->
		<article class="dkpi ded-kpi-card" title="Venta neta del período ÷ meta comercial acumulada al corte (sin tope en 100%). Responsable: Gerencia Comercial" onclick="window.location.href='{{ $ctxUrl('/cost-centers') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="target"></i></span>
				<span class="badge {{ $compliancePct === null ? 'badge-secondary' : ($compliancePct >= 100 ? 'badge-success' : ($compliancePct >= 90 ? 'badge-warning' : 'badge-danger')) }}" style="font-size:10px;">
					{{ $compliancePct === null ? 'Sin meta' : ($complianceDeltaPp >= 0 ? ('+' . $complianceDeltaPp . ' pp') : ($complianceDeltaPp . ' pp')) }}
				</span>
			</div>
			<div class="ded-kpi-label">Cumplimiento de meta</div>
			<div class="ded-kpi-val">{{ $compliancePct !== null ? (number_format($compliancePct, 1) . ' %') : 'Sin meta' }}</div>
			<div class="ded-kpi-sub">
				@if($targetBudget !== null && $targetBudget > 0)
					<span class="ded-kpi-badge-green">Meta: ${{ number_format($targetBudget, 0) }}</span>
				@else
					<span>Sin meta configurada en el período</span>
				@endif
			</div>
		</article>

		<!-- 3. Margen bruto -->
		@php $defMargin = $kpiDefs['gross_margin_pct'] ?? []; @endphp
		<article class="dkpi ded-kpi-card" title="{{ ($defMargin['formula'] ?? '(Venta neta − CMV) ÷ Venta neta × 100.') . ' Responsable: ' . ($defMargin['owner_name'] ?? 'Dirección Comercial') }}" onclick="window.location.href='{{ $ctxUrl('/sales?tab=rentabilidad') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="percent"></i></span>
				<span class="badge badge-light" style="font-size:10px;">{{ $sKpis['margin']['state'] ?? 'Sin datos' }}</span>
			</div>
			<div class="ded-kpi-label">Margen bruto</div>
			<div class="ded-kpi-val">{{ $marginPct !== null ? (number_format($marginPct, 1) . ' %') : 'Sin información' }}</div>
			<div class="ded-kpi-sub">
				@if($marginPct !== null)
					<span class="{{ ($marginDelta ?? 0) >= 0 ? 'ded-kpi-badge-green' : 'ded-pill-red' }}">
						{{ $marginDelta !== null ? (($marginDelta >= 0 ? '+' : '') . $marginDelta . ' pp vs ' . number_format($targetMarginPct, 1) . '%') : ('Utilidad $' . number_format($fs['gross_margin'] ?? 0, 0)) }}
					</span>
				@else
					<span>Sin ventas con costo unitario</span>
				@endif
			</div>
		</article>

		<!-- 4. Capital en inventario y cobertura DSI -->
		@php $defInv = $kpiDefs['inventory_value'] ?? []; @endphp
		<article class="dkpi ded-kpi-card" title="{{ ($defInv['formula'] ?? 'Σ existencias disponibles × costo unitario (excluye tránsito).') . ' Responsable: ' . ($defInv['owner_name'] ?? 'Jefe de Inventario') }}" onclick="window.location.href='{{ $ctxUrl('/stockoverview') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="package"></i></span>
				<span class="badge badge-light" style="font-size:10px;">{{ $sKpis['turnover']['state'] ?? 'Sin datos' }}</span>
			</div>
			<div class="ded-kpi-label">Valor del inventario</div>
			<div class="ded-kpi-val">{{ $inventoryValue }}</div>
			<div class="ded-kpi-sub">
				<span class="ded-kpi-badge-green">{{ $invCoverageDays }}</span>
				@if($turnoverVal > 0)· {{ number_format($turnoverVal, 2) }}x rot.@endif
			</div>
		</article>

		<!-- 5. Merma neta sobre ventas -->
		@php $defWaste = $kpiDefs['waste_pct'] ?? []; @endphp
		<article class="dkpi ded-kpi-card" title="{{ ($defWaste['formula'] ?? 'Merma aprobada a costo ÷ Base de merma × 100.') . ' Responsable: ' . ($defWaste['owner_name'] ?? 'Jefe de Mermas') }}" onclick="window.location.href='{{ $ctxUrl('/waste') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon" style="color: #ef4444;"><i data-lucide="shield-alert"></i></span>
				<span class="badge badge-light" style="font-size:10px;">{{ $sKpis['waste']['state'] ?? 'Sin datos' }}</span>
			</div>
			<div class="ded-kpi-label">Impacto de mermas</div>
			<div class="ded-kpi-val">{{ $wasteHasData ? ('$' . number_format($wasteCost, 0)) : 'Sin información' }}</div>
			<div class="ded-kpi-sub">
				@if($wastePct !== null)
					<span class="{{ $wastePct <= $wasteTargetPct ? 'ded-kpi-badge-green' : 'ded-pill-red' }}">{{ number_format($wastePct, 1) }} %</span> (obj. ≤ {{ number_format($wasteTargetPct, 1) }}%)
				@else
					<span>Sin base de ventas</span>
				@endif
			</div>
		</article>

		<!-- 6. Contribución operativa neta -->
		<article class="dkpi ded-kpi-card" title="Utilidad bruta − Merma neta aprobada (después de recuperaciones). Responsable: Finanzas / Dirección" onclick="window.location.href='{{ $ctxUrl('/cost-centers') }}'">
			<div class="ded-kpi-top">
				<span class="ded-kpi-icon"><i data-lucide="wallet"></i></span>
				<span class="badge badge-light" style="font-size:10px;">Neto post-merma</span>
			</div>
			<div class="ded-kpi-label">Contribución operativa</div>
			<div class="ded-kpi-val">{{ $netOpContribution !== null ? ('$' . number_format($netOpContribution, 0)) : 'Sin información' }}</div>
			<div class="ded-kpi-sub">
				@if($netOpMarginPct !== null)
					<span class="ded-kpi-badge-green">{{ number_format($netOpMarginPct, 1) }} %</span> margen neto · Recup. ${{ number_format($wasteRecovered, 0) }}
				@else
					<span>Sin ventas registradas</span>
				@endif
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN B. EVOLUCIÓN REAL + RESUMEN FINANCIERO Y PRESUPUESTO
	     ==================================================================== -->
	<section class="ded-row-2 mb-3" id="sec-evo-finance">
		<!-- Evolución y Comparativo -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="trending-up" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Evolución de Ventas vs Meta y Período Anterior</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Lectura exacta de registros reales ({{ $epPer }} vs {{ $epPrevPer }})</p>
				</div>
				<div class="ded-legend-inline">
					<span class="ded-legend-item"><span class="ded-legend-rect" style="background:#10b981;"></span><span>Venta real</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot" style="background:#f59e0b;"></span><span>Meta</span></span>
					<span class="ded-legend-item"><span class="ded-legend-line-dot" style="background:#64748b;"></span><span>Período anterior</span></span>
				</div>
			</header>
			<div class="ded-chart-canvas-box" style="position: relative; height: 255px; width: 100%;">
				@if($hasChartData)
					<canvas id="dedChartExecEvo" aria-label="Evolución real de ventas netas"></canvas>
				@else
					<div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted small">
						<i data-lucide="bar-chart-2" style="width:28px;height:28px;opacity:0.45;" class="mb-2"></i>
						<span>Sin ventas ni metas registradas en este período para graficar.</span>
					</div>
				@endif
			</div>
			<div class="ded-comp-grid pt-2 mt-2 border-top">
				<div class="ded-comp-col">
					<div class="ded-comp-row">
						<span class="ded-comp-label">Ventas netas ({{ $epPer }})</span>
						<span class="ded-comp-val font-weight-bold text-dark">{{ $epNet !== null ? ('$' . number_format($epNet, 2)) : 'Sin información' }}</span>
					</div>
					<div class="ded-comp-row">
						<span class="ded-comp-label">Período anterior ({{ $epPrevPer }})</span>
						<span class="ded-comp-val">{{ $epPrevNet !== null ? ('$' . number_format($epPrevNet, 2)) : 'Sin información' }}</span>
					</div>
				</div>
				<div class="ded-comp-col">
					<div class="ded-comp-row">
						<span class="ded-comp-label">Meta acumulada al corte</span>
						<span class="ded-comp-val">{{ $targetBudget !== null ? ('$' . number_format($targetBudget, 2)) : 'Sin meta' }}</span>
					</div>
					<div class="ded-comp-row">
						<span class="ded-comp-label">Cumplimiento real</span>
						<span class="ded-comp-val font-weight-bold {{ ($compliancePct ?? 0) >= 100 ? 'text-success' : 'text-warning' }}">
							{{ $compliancePct !== null ? (number_format($compliancePct, 1) . ' %') : 'Sin meta' }}
						</span>
					</div>
				</div>
			</div>
		</article>

		<!-- Resumen Financiero Consolidado & Presupuesto de Abastecimiento -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="landmark" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Resumen Financiero Consolidado</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Cascada de rentabilidad y ejecución presupuestaria del período</p>
				</div>
				@if(\Grocy\Helpers\DairyAccess::CanOpen('/cost-centers'))
				<a href="{{ $ctxUrl('/cost-centers') }}" class="small font-weight-bold text-success">Centros de costo →</a>
				@endif
			</header>

			<table class="table table-sm mb-3" style="font-size:12px;">
				<tbody>
					<tr>
						<td class="text-muted border-top-0">Ventas brutas (con IVA)</td>
						<td class="text-right font-weight-bold border-top-0">{{ $hasSalesData ? ('$' . number_format($fs['gross_sales'] ?? 0, 2)) : 'Sin información' }}</td>
					</tr>
					<tr>
						<td class="text-muted">Impuestos descontados / Ventas netas</td>
						<td class="text-right font-weight-bold text-dark">{{ $epNet !== null ? ('$' . number_format($epNet, 2)) : 'Sin información' }}</td>
					</tr>
					<tr>
						<td class="text-muted">Costo de mercancía vendida (CMV)</td>
						<td class="text-right text-secondary">{{ $hasSalesData ? ('-$' . number_format($fs['cogs'] ?? 0, 2)) : 'Sin información' }}</td>
					</tr>
					<tr>
						<td class="text-muted">Utilidad bruta</td>
						<td class="text-right font-weight-bold text-success">{{ $marginHasData ? ('$' . number_format($fs['gross_margin'] ?? 0, 2) . ' (' . number_format($marginPct ?? 0, 1) . '%)') : 'Sin información' }}</td>
					</tr>
					<tr>
						<td class="text-muted">Merma aprobada − Recuperación (${{ number_format($wasteRecovered, 0) }})</td>
						<td class="text-right text-danger">{{ $wasteHasData ? ('-$' . number_format($netWasteCost, 2)) : '$0.00' }}</td>
					</tr>
					<tr class="table-active">
						<td class="font-weight-bold text-dark">Contribución operativa neta</td>
						<td class="text-right font-weight-bold text-dark">{{ $netOpContribution !== null ? ('$' . number_format($netOpContribution, 2)) : 'Sin información' }}</td>
					</tr>
				</tbody>
			</table>

			<div class="pt-2 border-top">
				<div class="d-flex justify-content-between align-items-center mb-1">
					<strong class="small text-dark">Ejecución Presupuesto de Compras</strong>
					@if(!empty($budget['has_data']))
						<strong class="small text-dark">{{ number_format(floatval($budget['executed_pct'] ?? 0), 0) }}%</strong>
					@else
						<span class="small text-muted">Sin presupuesto</span>
					@endif
				</div>
				@if(!empty($budget['has_data']))
					@php $execPctBar = min(100, max(0, floatval($budget['executed_pct'] ?? 0))); @endphp
					<div class="progress mb-2" style="height:7px; border-radius:4px;">
						<div class="progress-bar {{ floatval($budget['executed_pct'] ?? 0) > 100 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $execPctBar }}%;"></div>
					</div>
					<div class="d-flex justify-content-between small text-muted" style="font-size:11px;">
						<span>Recibido: <strong>${{ number_format($budget['invoiced_total'] ?? 0, 0) }}</strong></span>
						<span>OC comprometidas: <strong>${{ number_format($budget['committed_oc_total'] ?? 0, 0) }}</strong></span>
						<span>Presupuesto: <strong>${{ number_format($budget['total_budget'] ?? 0, 0) }}</strong></span>
					</div>
					@if(!empty($budget['reconciliation_note']))
						<div class="text-muted mt-1" style="font-size:10.5px;">{{ $budget['reconciliation_note'] }}</div>
					@endif
				@else
					<p class="small text-muted mb-0">{{ $budget['reconciliation_note'] ?? 'Sin presupuesto asignado ni compras en este período.' }}</p>
				@endif
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN C. COMPARACIÓN ENTRE SUCURSALES & DEPARTAMENTOS
	     ==================================================================== -->
	<section class="ded-row-2 mb-3" id="sec-branch-dept">
		<!-- Ranking real de sucursales -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="store" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Desempeño Comparativo por Sucursal</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Ventas netas, cumplimiento real (sin tope), margen, merma e inventario por tienda</p>
				</div>
				<a href="{{ $ctxUrl('/sales?tab=sucursales') }}" class="small font-weight-bold text-success">Detalle comercial →</a>
			</header>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0" style="font-size:11.5px;">
					<thead>
						<tr class="text-muted">
							<th>Sucursal</th>
							<th class="text-right">Ventas</th>
							<th class="text-center">Meta</th>
							<th class="text-center">Margen</th>
							<th class="text-center">Merma</th>
							<th class="text-center">Cobertura</th>
							<th class="text-center">Estado</th>
							<th class="text-right">Enfocar</th>
						</tr>
					</thead>
					<tbody>
						@forelse($branchRanking as $br)
							@php
								$stTone = $br['exec_state'] ?? 'no_data';
								$stLbl = $br['exec_state_label'] ?? 'Sin información';
							@endphp
							<tr class="@if((int)$selectedBranchId === (int)$br['branch_id']) table-success @endif">
								<td>
									<strong class="text-dark d-block">{{ $br['clean_name'] ?? $br['name'] }}</strong>
									@if(!empty($br['code']))<small class="text-muted">{{ $br['code'] }}</small>@endif
								</td>
								<td class="text-right font-weight-bold">
									{{ !empty($br['has_activity']) ? ('$' . number_format($br['sales'], 0)) : 'Sin ventas' }}
								</td>
								<td class="text-center">
									@if($br['compliance_pct'] !== null)
										<span class="badge {{ $br['compliance_pct'] >= 100 ? 'badge-success' : ($br['compliance_pct'] >= 90 ? 'badge-warning' : 'badge-danger') }}">
											{{ number_format($br['compliance_pct'], 1) }}%
										</span>
									@else
										<span class="text-muted">Sin meta</span>
									@endif
								</td>
								<td class="text-center">{{ !empty($br['has_activity']) ? (number_format($br['margin_pct'], 1) . '%') : '—' }}</td>
								<td class="text-center">{{ $br['waste_ratio_pct'] !== null ? (number_format($br['waste_ratio_pct'], 1) . '%') : '—' }}</td>
								<td class="text-center text-muted">{{ $br['coverage_days'] !== null ? ($br['coverage_days'] . ' d') : 'Sin datos' }}</td>
								<td class="text-center">
									<span class="ded-state-pill is-{{ $stTone }}">{{ $stLbl }}</span>
								</td>
								<td class="text-right">
									<a href="{{ $ctxUrl('/kpis', ['branch_id' => $br['branch_id']]) }}" class="btn btn-xs btn-outline-success py-0 px-2 font-weight-bold" style="font-size:10.5px;">Ver</a>
								</td>
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-3">Sin sucursales activas autorizadas.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</article>

		<!-- Resumen estratégico por departamento comercial -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="layers" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Mix Comercial por Departamento</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Contribución a ventas y margen del período (enlace al detalle comercial)</p>
				</div>
				<a href="{{ $ctxUrl('/sales?tab=departamentos') }}" class="small font-weight-bold text-success">Departamentos →</a>
			</header>
			@php
				$deptSalesTotal = array_sum(array_map(fn($d) => floatval($d['sales'] ?? 0), $deptRows));
				$sortedDepts = array_values(array_filter($deptRows, fn($d) => floatval($d['sales'] ?? 0) > 0 || floatval($d['units'] ?? 0) > 0));
				usort($sortedDepts, fn($a, $b) => floatval($b['sales'] ?? 0) <=> floatval($a['sales'] ?? 0));
			@endphp
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0" style="font-size:11.5px;">
					<thead>
						<tr class="text-muted">
							<th>Departamento</th>
							<th class="text-right">Ventas</th>
							<th class="text-center">Part.</th>
							<th class="text-center">Margen</th>
							<th class="text-right">Utilidad</th>
						</tr>
					</thead>
					<tbody>
						@forelse(array_slice($sortedDepts, 0, 10) as $dr)
							@php $share = $deptSalesTotal > 0 ? round(floatval($dr['sales']) / $deptSalesTotal * 100, 1) : 0; @endphp
							<tr>
								<td>
									<a href="{{ $ctxUrl('/sales?tab=departamentos', ['department' => $dr['id']]) }}" class="font-weight-bold text-dark d-flex align-items-center gap-1">
										<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $dr['color'] ?? '#10b981' }};"></span>
										<span>{{ $dr['name'] }}</span>
									</a>
								</td>
								<td class="text-right font-weight-bold">${{ number_format($dr['sales'], 0) }}</td>
								<td class="text-center text-muted">{{ $share }}%</td>
								<td class="text-center">{{ number_format($dr['margin_pct'], 1) }}%</td>
								<td class="text-right text-success font-weight-bold">${{ number_format($dr['gross_profit'], 0) }}</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-center text-muted py-3">Sin ventas por departamento en el período seleccionado.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN D. GOBIERNO DE LOS 7 PROCESOS (MISMO ESTADO QUE HOY)
	     ==================================================================== -->
	<section class="ded-card mb-3" id="sec-process-gov">
		<header class="ded-card-header">
			<div>
				<h2 class="ded-card-title d-flex align-items-center gap-2">
					<i data-lucide="diagram-project" style="width:16px;height:16px;color:#10b981;"></i>
					<span>Estado Consolidado de los 7 Procesos del Negocio</span>
				</h2>
				<p class="text-muted small mb-0" style="font-size:11.5px;">Sincronizado con HOY al {{ date('d/m/Y', strtotime($epR['end'])) }} · Incluye desviación, impacto, responsable y acceso directo</p>
			</div>
			<a href="{{ $ctxUrl('/today?tab=processes') }}" class="small font-weight-bold text-success">Supervisar en HOY →</a>
		</header>
		<div class="table-responsive">
			<table class="table table-hover table-sm mb-0" style="font-size:12px;">
				<thead>
					<tr class="text-muted">
						<th>Proceso</th>
						<th class="text-center">Estado</th>
						<th>Desviación vs objetivo</th>
						<th>Impacto económico</th>
						<th>Situación / Alcance</th>
						<th>Responsable</th>
						<th>Actualización</th>
						<th class="text-right">Módulo propietario</th>
					</tr>
				</thead>
				<tbody>
					@foreach($ops as $op)
					<tr>
						<td>
							<strong class="text-dark">{{ $op['full_name'] ?? $op['name'] }}</strong>
							<small class="d-block text-muted">{{ $op['metric'] }}</small>
						</td>
						<td class="text-center">
							<span class="ded-state-pill is-{{ $op['state'] ?? 'no_data' }}">{{ $op['state_label'] ?? 'Sin información' }}</span>
						</td>
						<td>{{ $op['deviation'] ?? '—' }}</td>
						<td class="font-weight-bold {{ ($op['economic_impact'] ?? 0) < 0 ? 'text-danger' : 'text-dark' }}">
							{{ $op['economic_impact_label'] ?? 'No calculable' }}
						</td>
						<td class="text-muted" style="max-width:260px;">{{ $op['subtitle'] ?? '' }}</td>
						<td>{{ $op['owner'] ?? 'Operaciones' }}</td>
						<td class="text-muted">{{ $op['last_updated'] ?? ($dq['last_updated_formatted'] ?? 'Sin registros') }}</td>
						<td class="text-right">
							<a href="{{ $ctxUrl($op['url'] ?? '/today') }}" class="btn btn-xs btn-outline-success font-weight-bold">Abrir →</a>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</section>

	<!-- ====================================================================
	     SECCIÓN E. RIESGOS, OPORTUNIDADES Y ACCIONES CORRECTIVAS
	     ==================================================================== -->
	<section class="ded-row-2 mb-3" id="sec-risks-actions">
		<!-- Decisiones Prioritarias + Riesgos/Oportunidades -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="compass" style="width:16px;height:16px;color:#f59e0b;"></i>
						<span>Desviaciones, Riesgos y Oportunidades</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Hallazgos reales que explican brechas de meta, margen o riesgo operativo</p>
				</div>
				<a href="{{ $ctxUrl('/alerts') }}" class="small font-weight-bold text-success">Alertas ({{ $alertCount }}) →</a>
			</header>
			<div class="ded-alerts-list">
				@foreach($decisions as $dec)
				<div class="ded-alert-row">
					<div>
						<div class="d-flex align-items-center gap-2 mb-1">
							<span class="badge badge-{{ $dec['level'] === 'danger' ? 'danger' : ($dec['level'] === 'warning' ? 'warning' : ($dec['level'] === 'success' ? 'success' : 'secondary')) }}">
								{{ $dec['priority_badge'] }}
							</span>
							<strong class="text-dark" style="font-size:12px;">{{ $dec['title'] }}</strong>
						</div>
						<div class="small text-secondary mb-1">{{ $dec['description'] }}</div>
						<div class="small text-muted" style="font-size:11px;">
							<span>{{ $dec['branch_or_process'] }}</span> ·
							<span>Responsable: <strong>{{ $dec['responsible'] }}</strong></span>
							@if(!empty($dec['due_date']))· <span>Plazo: <strong>{{ $dec['due_date'] }}</strong></span>@endif
						</div>
					</div>
					<div class="text-right flex-shrink-0">
						@if($dec['economic_impact'] !== null)
							<div class="ded-alert-impact mb-1 {{ $dec['economic_impact'] < 0 ? 'text-danger' : 'text-success' }}">
								{{ $dec['economic_impact'] < 0 ? ('-$' . number_format(abs($dec['economic_impact']), 0)) : ('+$' . number_format($dec['economic_impact'], 0)) }}
							</div>
						@endif
						<a href="{{ $ctxUrl($dec['url']) }}" class="btn btn-xs btn-outline-success font-weight-bold">{{ $dec['action_label'] }}</a>
					</div>
				</div>
				@endforeach

				@foreach($opportunities as $opp)
				<div class="ded-alert-row">
					<div>
						<div class="d-flex align-items-center gap-2 mb-1">
							<span class="badge badge-{{ $opp['tone'] }}">{{ $opp['type'] }}</span>
							<strong class="text-dark" style="font-size:12px;">{{ $opp['title'] }}</strong>
						</div>
						<div class="small text-muted">{{ $opp['detail'] }}</div>
					</div>
					<div class="text-right flex-shrink-0">
						<a href="{{ $ctxUrl($opp['url']) }}" class="btn btn-xs btn-outline-secondary font-weight-bold">Revisar →</a>
					</div>
				</div>
				@endforeach
			</div>
		</article>

		<!-- Seguimiento de Acciones Correctivas -->
		<article class="ded-card">
			<header class="ded-card-header">
				<div>
					<h2 class="ded-card-title d-flex align-items-center gap-2">
						<i data-lucide="list-checks" style="width:16px;height:16px;color:#10b981;"></i>
						<span>Seguimiento de Acciones Correctivas</span>
					</h2>
					<p class="text-muted small mb-0" style="font-size:11.5px;">Planes correctivos de merma y alertas prioritarias activas</p>
				</div>
				<a href="{{ $ctxUrl('/waste') }}" class="small font-weight-bold text-success">Planes →</a>
			</header>

			<div class="d-flex justify-content-between gap-2 mb-3 text-center">
				<div class="flex-fill border rounded p-2">
					<div class="font-weight-bold text-dark" style="font-size:16px;">{{ $ca['open'] ?? 0 }}</div>
					<small class="text-muted">Abiertas</small>
				</div>
				<div class="flex-fill border rounded p-2">
					<div class="font-weight-bold text-primary" style="font-size:16px;">{{ $ca['in_progress'] ?? 0 }}</div>
					<small class="text-muted">En curso</small>
				</div>
				<div class="flex-fill border rounded p-2">
					<div class="font-weight-bold text-danger" style="font-size:16px;">{{ $ca['overdue'] ?? 0 }}</div>
					<small class="text-muted">Vencidas</small>
				</div>
				<div class="flex-fill border rounded p-2">
					<div class="font-weight-bold text-success" style="font-size:16px;">
						{{ ($ca['compliance_rate'] ?? null) !== null ? (number_format($ca['compliance_rate'], 0) . '%') : 'Sin planes' }}
					</div>
					<small class="text-muted">Cumplimiento</small>
				</div>
			</div>

			@if(!empty($ca['items']))
				<div class="ded-alerts-list">
					@foreach($ca['items'] as $ci)
					<div class="ded-alert-row py-2">
						<div>
							<div class="d-flex align-items-center gap-2">
								<span class="badge {{ !empty($ci['is_overdue']) ? 'badge-danger' : 'badge-light border' }}">{{ $ci['source'] }}</span>
								<strong class="text-dark" style="font-size:11.5px;">{{ $ci['title'] }}</strong>
							</div>
							<small class="text-muted">
								{{ $ci['branch'] }} · Responsable: <strong>{{ $ci['owner'] }}</strong>
								@if(!empty($ci['due_date']))· Límite: <span class="{{ !empty($ci['is_overdue']) ? 'text-danger font-weight-bold' : '' }}">{{ $ci['due_date'] }}</span>@endif
							</small>
						</div>
						<a href="{{ $ctxUrl($ci['url']) }}" class="btn btn-xs btn-outline-success">Atender</a>
					</div>
					@endforeach
				</div>
			@else
				<div class="text-center text-muted small py-3">Sin acciones correctivas ni alertas críticas abiertas en este alcance.</div>
			@endif
		</article>
	</section>

	<!-- ====================================================================
	     SECCIÓN F. DEFINICIONES CLARAS DE KPI & CALIDAD DE DATOS
	     ==================================================================== -->
	<section class="ded-card" id="sec-kpi-definitions">
		<header class="ded-card-header">
			<div>
				<h2 class="ded-card-title d-flex align-items-center gap-2">
					<i data-lucide="book-open" style="width:16px;height:16px;color:#10b981;"></i>
					<span>Gobierno de Indicadores, Definiciones y Calidad de Datos</span>
				</h2>
				<p class="text-muted small mb-0" style="font-size:11.5px;">
					Fuente: {{ $dq['source_label'] ?? 'Base local SQLite' }} · Último movimiento: <strong>{{ $dq['last_updated_formatted'] ?? 'Sin registros' }}</strong> ·
					Cobertura CMV ventas: <strong>{{ ($dq['sales_cost_coverage_pct'] ?? null) !== null ? ($dq['sales_cost_coverage_pct'] . '%') : 'Sin datos' }}</strong> ·
					Cobertura costos stock: <strong>{{ ($dq['stock_cost_coverage_pct'] ?? null) !== null ? ($dq['stock_cost_coverage_pct'] . '%') : 'Sin datos' }}</strong>
				</p>
			</div>
			@if(\Grocy\Helpers\DairyAccess::CanOpen('/admin/economics'))
			<a href="{{ $U('/admin/economics') }}" class="small font-weight-bold text-success">Configurar economía y metas →</a>
			@elseif(\Grocy\Helpers\DairyAccess::CanOpen('/cost-centers'))
			<a href="{{ $ctxUrl('/cost-centers') }}" class="small font-weight-bold text-success">Ver centros de costo →</a>
			@endif
		</header>
		<div class="ded-def-grid">
			@foreach($kpiDefs as $kd)
			<div class="ded-def-item">
				<div class="d-flex justify-content-between align-items-center mb-1">
					<strong class="text-dark">{{ $kd['name'] }}</strong>
					<span class="badge badge-light border">{{ $kd['unit'] }}</span>
				</div>
				<div class="text-muted mb-1">{{ $kd['formula'] }}</div>
				<div class="d-flex justify-content-between text-secondary" style="font-size:10.5px;">
					<span>Fuente: {{ $kd['source'] }}</span>
					<span>Resp.: <strong>{{ $kd['owner_name'] }}</strong></span>
				</div>
			</div>
			@endforeach
		</div>
	</section>

</div>

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	if (window.lucide) {
		lucide.createIcons();
	}

	// 1. Dropdown toggles (.ded-dropdown-toggle)
	document.querySelectorAll('.ded-dropdown-toggle').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.stopPropagation();
			var container = btn.closest('.ded-dropdown-container');
			var menu = container ? container.querySelector('.ded-dropdown-menu') : null;
			var wasOpen = menu && menu.classList.contains('show');
			document.querySelectorAll('.ded-dropdown-menu.show').forEach(function(m) {
				m.classList.remove('show');
			});
			if (menu && !wasOpen) {
				menu.classList.add('show');
			}
		});
	});
	document.addEventListener('click', function(e) {
		if (!e.target.closest('.ded-dropdown-container')) {
			document.querySelectorAll('.ded-dropdown-menu.show').forEach(function(m) {
				m.classList.remove('show');
			});
		}
	});

	// 2. Branch native selector preserving period/department/date
	var branchSelect = document.getElementById('exec-select-branch');
	if (branchSelect) {
		branchSelect.addEventListener('change', function() {
			var url = new URL(window.location.href);
			if (this.value) {
				url.searchParams.set('branch_id', this.value);
			} else {
				url.searchParams.delete('branch_id');
			}
			window.location.href = url.toString();
		});
	}

	// 3. Chart.js 2.9.4 evolution chart (xAxes / yAxes / tooltips)
	initExecEvoChart();
});

var execEvoChartInstance = null;
function initExecEvoChart() {
	var ctx = document.getElementById('dedChartExecEvo');
	if (!ctx || typeof Chart === 'undefined') return;

	var chartLabels = @json($evoChart['labels'] ?? []);
	var salesRealData = @json($evoChart['sales_real'] ?? []);
	var targetLineData = @json($evoChart['target_line'] ?? []);
	var prevPeriodData = @json($evoChart['prev_period'] ?? []);
	if (!chartLabels.length) return;

	execEvoChartInstance = new Chart(ctx.getContext('2d'), {
		type: 'bar',
		data: {
			labels: chartLabels,
			datasets: [
				{
					type: 'line',
					label: 'Meta presupuestada',
					data: targetLineData,
					borderColor: '#f59e0b',
					backgroundColor: 'transparent',
					borderWidth: 2,
					borderDash: [5, 5],
					pointRadius: 2.5,
					pointBackgroundColor: '#f59e0b',
					fill: false,
					lineTension: 0.25
				},
				{
					type: 'line',
					label: 'Período anterior',
					data: prevPeriodData,
					borderColor: '#64748b',
					backgroundColor: 'transparent',
					borderWidth: 1.8,
					borderDash: [2, 3],
					pointRadius: 2,
					pointBackgroundColor: '#64748b',
					fill: false,
					lineTension: 0.25
				},
				{
					type: 'bar',
					label: 'Ventas reales',
					data: salesRealData,
					backgroundColor: 'rgba(16, 185, 129, 0.72)',
					borderColor: '#10b981',
					borderWidth: 1.5
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
				backgroundColor: 'rgba(15, 23, 42, 0.94)',
				callbacks: {
					label: function(tooltipItem, data) {
						var dsLabel = data.datasets[tooltipItem.datasetIndex].label || '';
						var v = Number(tooltipItem.yLabel || 0);
						return dsLabel + ': $' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					}
				}
			},
			scales: {
				xAxes: [{
					gridLines: { display: false },
					ticks: { fontColor: '#64748b', fontSize: 11 }
				}],
				yAxes: [{
					gridLines: { color: 'rgba(148, 163, 184, 0.22)' },
					ticks: {
						beginAtZero: true,
						fontColor: '#64748b',
						fontSize: 11,
						callback: function(v) {
							return Math.abs(v) >= 1000 ? '$' + Math.round(v / 1000) + 'K' : '$' + v;
						}
					}
				}]
			}
		}
	});
}
</script>
@endpush
@endsection
