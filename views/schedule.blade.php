@php require_frontend_packages(['chartjs', 'daterangepicker']); @endphp
@extends('layout.default')

@section('title', 'Horarios y Cobertura — Motor Inteligente de Planificación')

@push('pageStyles')
<link href="{{ $U('/css/dairy_schedule.css?v=', true) }}{{ time() }}" rel="stylesheet">
<style>
/* Eliminar espacio en blanco superior innecesario para que Horarios y Cobertura rellene todo el espacio superior */
.content-wrapper {
	padding-top: 12px !important;
}
.sch-container {
	padding: 0 !important;
	padding-top: 0 !important;
	margin-top: 0 !important;
}
.sch-header-top {
	margin-top: 0 !important;
	padding-top: 0 !important;
	margin-bottom: 0.65rem !important;
}
.sch-title-row {
	margin-bottom: 0.85rem !important;
}
</style>
@endpush

@section('content')
@php
	$w = $week;
	$dyn = $dynamicPlan ?? [];
	$sidebarData = $sidebar ?? ($dyn['sidebar'] ?? []);
	$kpiData = $kpis ?? ($dyn['kpis'] ?? []);
	$analyticsData = $analytics ?? ($dyn['analytics'] ?? []);
	$deptList = $departments ?? ($dyn['departments'] ?? []);
	$budgetData = $budget ?? ($dyn['budget'] ?? []);

	$dayLabels = [];
	$dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
	$curDate = new \DateTime($monday);
	for ($i = 0; $i < 7; $i++) {
		$dStr = $curDate->format('Y-m-d');
		$dayLabels[$dStr] = [
			'name' => $dayNames[$i],
			'date' => $curDate->format('d/m'),
			'is_today' => ($dStr === date('Y-m-d'))
		];
		$curDate->modify('+1 day');
	}

	$colorPalette = ['#2563eb', '#16a34a', '#0d9488', '#4f46e5', '#e11d48', '#0284c7', '#10b981', '#f59e0b', '#7c3aed', '#ec4899', '#059669', '#d97706', '#dc2626'];
	$getAvatarColor = function($id) use ($colorPalette) {
		return $colorPalette[abs(intval($id)) % count($colorPalette)];
	};

	$initials = function($name) {
		$parts = preg_split('/\s+/', trim($name));
		return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
	};
@endphp

<div class="sch-container">

	<!-- 1. TOP HEADER & STATUS BADGES -->
	<div class="sch-header-top">
		<div class="sch-badge-group">
			<span class="sch-badge sch-badge-purple">
				<span class="sch-badge-dot"></span> Motor Inteligente Activo
			</span>
			<span class="sch-badge sch-badge-gray">
				{{ date('d/m/Y', strtotime($monday)) }} - {{ date('d/m/Y', strtotime($monday . ' +6 day')) }}
			</span>
			<span class="sch-badge sch-badge-green">
				<span class="sch-badge-dot"></span> Presupuesto: {{ $kpiData['labor_budget']['value'] ?? '$19,083' }} (8.5%)
			</span>
			<span class="sch-badge sch-badge-amber">
				<span class="sch-badge-dot"></span> Cobertura: {{ $kpiData['demand_coverage']['value'] ?? '96%' }}
			</span>
			@if(!empty($sidebarData['potential_savings']) && $sidebarData['potential_savings'] > 0)
				<span class="sch-badge sch-badge-purple">
					<i class="fa-solid fa-piggy-bank mr-1"></i> Ahorro potencial: ${{ number_format($sidebarData['potential_savings'], 2) }}
				</span>
			@endif
		</div>
		<div class="sch-header-clock-box d-none d-md-block">
			<div class="text-muted small font-weight-600">Planificación Dinámica por Ventas</div>
			<div class="sch-clock-time">Ventas: {{ isset($dyn['sales']['total_projected']) ? '$' . number_format($dyn['sales']['total_projected']) : 'Sin información' }}</div>
			<div class="sch-clock-meta">SPLH Tienda: {{ $kpiData['splh']['value'] ?? '$138.2 /h' }}</div>
		</div>
	</div>

	<!-- 2. DEMO SCENARIO BANNER & CONTROLS -->
	@if(!empty($isDemoScenario))
		<div class="sch-demo-banner d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 px-3 rounded shadow-sm" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); color: #fff; border: 1px solid #10b981;">
			<div class="d-flex align-items-center flex-wrap gap-2">
				<span class="badge badge-warning text-dark font-weight-bold px-2 py-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
					<i class="fa-solid fa-store mr-1"></i> ESCENARIO DE EJEMPLO
				</span>
				<strong style="font-size: 1rem; letter-spacing: 0.2px;">{{ $demoScenarioName ?? 'Doral' }}</strong>
				<span class="text-white-50 small d-none d-md-inline">|</span>
				<span class="small text-white-50">25 Personas ficticias · 7 Departamentos · Noviembre 2026 (15 a 40h · 6 a 30h · 4 a 20h)</span>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<button type="button" class="btn btn-xs btn-outline-light font-weight-bold" data-toggle="modal" data-target="#modalValidationScenarios">
					<i class="fa-solid fa-vial-circle-check mr-1 text-warning"></i> 6 Casos de Prueba
				</button>
				<button type="button" class="btn btn-xs btn-warning text-dark font-weight-bold" onclick="window.switchScheduleView('matrix')">
					<i class="fa-solid fa-table-cells mr-1"></i> Matriz 30 min
				</button>
				<button type="button" class="btn btn-xs btn-danger font-weight-bold" onclick="window.triggerRestoreDemo()">
					<i class="fa-solid fa-rotate-left mr-1"></i> Restaurar escenario de ejemplo
				</button>
			</div>
		</div>
	@endif

	<!-- 2. MAIN TITLE & CONTROLS ROW -->
	<div class="sch-title-row">
		<div>
			<h1 class="sch-main-title">Horarios y Cobertura</h1>
			<p class="sch-subtitle">Planificación laboral dinámica calculada según proyección de ventas, productividad por departamento (SPLH) y distribución equitativa de turnos.</p>
		</div>
		<div class="sch-header-controls">
			<select class="sch-select" id="branchSelector">
				<option value="0">Red Consolidada</option>
				@foreach($branches as $b)
					<option value="{{ $b->id }}" @if($b->id == $branchId) selected @endif>{{ $b->name }}</option>
				@endforeach
			</select>

			<div class="btn-group">
				<button type="button" class="sch-btn sch-btn-icon" id="prevWeekBtn" title="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>
				<button type="button" class="sch-btn" id="currentWeekLabel">Semana actual</button>
				<button type="button" class="sch-btn sch-btn-icon" id="nextWeekBtn" title="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button>
			</div>

			<button type="button" class="sch-btn sch-btn-icon" id="calendarPickerBtn" title="Seleccionar fecha"><i class="fa-solid fa-calendar-days"></i></button>

			<button type="button" class="sch-btn sch-btn-emerald" id="btnGenerarProgramacionHeader" data-toggle="modal" data-target="#modalGenerarProgramacion">
				<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generar programación
			</button>

			<button type="button" class="sch-btn sch-btn-purple" id="btnAIOptimizeHeader" data-toggle="modal" data-target="#modalAIOptimize">
				<i class="fa-solid fa-brain mr-1"></i> Optimizar con IA
			</button>

			<button type="button" class="sch-btn" id="btnSimulateScenarioHeader" data-toggle="modal" data-target="#modalSimulateScenario">
				<i class="fa-solid fa-chart-line mr-1 text-info"></i> Simular What-If
			</button>

			<button type="button" class="sch-btn sch-btn-primary" id="btnCreateShift">
				<i class="fa-solid fa-plus mr-1"></i> Crear turno
			</button>

			<button type="button" class="sch-btn" id="btnPublishSchedule">
				<i class="fa-solid fa-paper-plane mr-1 text-primary"></i> Publicar
			</button>

			<div class="dropdown d-inline-block">
				<button type="button" class="sch-btn dropdown-toggle" data-toggle="dropdown">
					<i class="fa-solid fa-gear mr-1 text-muted"></i> Acciones
				</button>
				<div class="dropdown-menu dropdown-menu-right">
					<a class="dropdown-item font-weight-bold" href="#" id="btnCompanyPolicies" data-toggle="modal" data-target="#modalCompanyLaborPolicies"><i class="fa-solid fa-building-shield mr-2 text-success"></i>Políticas Laborales de Empresa</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" id="btnTriggerDailyAutomation" data-toggle="modal" data-target="#modalDailyAutomation"><i class="fa-solid fa-robot mr-2 text-primary"></i>Automatización diaria (10 pasos)</a>
					<a class="dropdown-item" href="#" id="btnDeptStandards" data-toggle="modal" data-target="#modalDeptStandards"><i class="fa-solid fa-sliders mr-2 text-teal"></i>Estándares de productividad y SPLH</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" id="btnExportPdf"><i class="fa-solid fa-file-pdf mr-2 text-danger"></i>Exportar PDF</a>
					<a class="dropdown-item" href="#" id="btnExportExcel"><i class="fa-solid fa-file-excel mr-2 text-success"></i>Exportar Excel</a>
					<a class="dropdown-item" href="#" id="btnExportCsv"><i class="fa-solid fa-file-csv mr-2 text-info"></i>Exportar CSV</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item text-danger font-weight-bold" href="#" id="btnDropdownRestoreDemo" onclick="window.triggerRestoreDemo()"><i class="fa-solid fa-rotate-left mr-2"></i>Restaurar únicamente el escenario de ejemplo</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ALERTA CRÍTICA DE PRESUPUESTO (Si presupuesto < dotación mínima de seguridad) -->
	@if(!empty($sidebarData['budget_deficit_alert']))
		<div class="sch-budget-alert-banner">
			<div>
				<strong class="text-danger"><i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ $sidebarData['budget_deficit_alert']['title'] }}</strong>
				<div class="small text-dark mt-1">{{ $sidebarData['budget_deficit_alert']['message'] }}</div>
				<div class="small text-danger font-weight-bold mt-1">{{ $sidebarData['budget_deficit_alert']['action_required'] }}</div>
			</div>
			<div>
				<button type="button" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="toastr.info('Solicitud de autorización presupuestaria enviada a Gerencia General');">
					<i class="fa-solid fa-check mr-1"></i> Autorizar sobregiro presupuestario
				</button>
			</div>
		</div>
	@endif

	<!-- 3. EIGHT KPI CARDS (Conforme a la especificación oficial) -->
	<div class="sch-kpis-grid">
		<!-- KPI 1: Empleados programados -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Empleados programados</span>
				<span class="sch-kpi-icon tone-primary"><i class="fa-solid fa-users"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['scheduled_employees']['value'] ?? count($w['employees']) }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,24 C14,22 24,18 36,14 C48,10 58,6 70,3" fill="none" stroke="var(--daily-primary, #00C83B)" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">de {{ $kpiData['scheduled_employees']['active_total'] ?? count($w['employees']) }} activos · {{ $kpiData['scheduled_employees']['coverage_pct'] ?? '—' }}% dotación</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta good"><i class="fa-solid fa-arrow-up"></i> {{ $kpiData['scheduled_employees']['delta'] ?? '+6%' }} {{ $kpiData['scheduled_employees']['delta_label'] ?? 'vs. semana anterior' }}</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 2: Horas asignadas -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Horas asignadas</span>
				<span class="sch-kpi-icon tone-warning"><i class="fa-solid fa-clock"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['scheduled_hours']['value'] ?? '1,024 h' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,8 C14,14 26,22 38,20 C50,18 60,16 70,10" fill="none" stroke="#f87171" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">Costo programado · {{ $kpiData['scheduled_hours']['labor_cost'] ?? 'Sin información' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta warning"><i class="fa-solid fa-arrow-up"></i> {{ $kpiData['scheduled_hours']['delta'] ?? '+3%' }} {{ $kpiData['scheduled_hours']['delta_label'] ?? 'vs. semana anterior' }}</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 3: Cobertura proyectada -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Cobertura proyectada</span>
				<span class="sch-kpi-icon tone-success"><i class="fa-solid fa-shield-halved"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['demand_coverage']['value'] ?? '96%' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,18 C14,12 26,4 38,6 C50,8 60,16 70,14" fill="none" stroke="#10b981" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">{{ $kpiData['demand_coverage']['sub'] ?? 'Horas óptimas por ventas' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-badge sch-badge-green" style="padding: 0.15rem 0.5rem; font-size: 0.68rem;">
					<span class="sch-badge-dot"></span> {{ $kpiData['demand_coverage']['status'] ?? 'En rango objetivo' }}
				</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 4: Presupuesto laboral -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Presupuesto laboral</span>
				<span class="sch-kpi-icon tone-primary" style="background-color: #ede9fe; color: #7c3aed;"><i class="fa-solid fa-wallet"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['labor_budget']['value'] ?? '$19,083' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,20 C14,18 26,14 38,12 C50,10 60,6 70,4" fill="none" stroke="#7c3aed" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">{{ $kpiData['labor_budget']['target_pct'] ?? '8.5% de ventas' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-badge sch-badge-green" style="padding: 0.15rem 0.5rem; font-size: 0.68rem;">
					<span class="sch-badge-dot"></span> {{ $kpiData['labor_budget']['status'] ?? 'Bajo presupuesto' }}
				</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 5: Costo programado (con cargas y beneficios) -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Costo programado</span>
				<span class="sch-kpi-icon tone-primary"><i class="fa-solid fa-receipt"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['scheduled_cost']['value'] ?? 'Sin información' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,16 C14,14 26,18 38,12 C50,14 60,8 70,10" fill="none" stroke="#0284c7" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">Base: {{ $kpiData['scheduled_cost']['base_cost'] ?? '$15,420' }} + Cargas: {{ $kpiData['scheduled_cost']['burden_cost'] ?? '$2,900' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta good"><i class="fa-solid fa-check"></i> {{ $kpiData['scheduled_cost']['pct_sales'] ?? '8.1% de ventas' }}</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 6: Ventas por hora laboral (SPLH) -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Ventas por hora (SPLH)</span>
				<span class="sch-kpi-icon tone-info" style="background-color: #ecfdf5; color: #059669;"><i class="fa-solid fa-chart-pie"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['splh']['value'] ?? '$138.2 /h' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,22 C14,18 26,16 38,12 C50,8 60,6 70,2" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">Meta objetivo: {{ $kpiData['splh']['target'] ?? '$140.0 /h' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta good"><i class="fa-solid fa-arrow-up"></i> {{ $kpiData['splh']['status'] ?? 'Eficiencia óptima' }}</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 7: Horas extras -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Horas extras</span>
				<span class="sch-kpi-icon tone-info"><i class="fa-solid fa-user-clock"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['overtime']['value'] ?? '8.5 h' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,10 C14,14 26,10 38,12 C50,14 60,8 70,12" fill="none" stroke="#3b82f6" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">{{ $kpiData['overtime']['sub'] ?? '0.8% del total' }}</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta good"><i class="fa-solid fa-arrow-down"></i> {{ $kpiData['overtime']['delta'] ?? '-32%' }} vs. semana anterior</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>

		<!-- KPI 8: Brecha de cobertura -->
		<div class="sch-kpi-card">
			<div class="sch-kpi-head">
				<span class="sch-kpi-title">Brecha de cobertura</span>
				<span class="sch-kpi-icon tone-danger"><i class="fa-solid fa-scale-balanced"></i></span>
			</div>
			<div class="sch-kpi-body">
				<div class="sch-kpi-value">{{ $kpiData['coverage_gap']['value'] ?? '+4 h' }}</div>
				<svg class="sch-kpi-sparkline" viewBox="0 0 72 28">
					<path d="M2,14 C12,8 24,18 36,12 C48,8 60,16 70,10" fill="none" stroke="#10b981" stroke-width="2.2" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="sch-kpi-sub">Diferencia horas prog. vs demanda</div>
			<div class="sch-kpi-footer">
				<span class="sch-kpi-delta good"><i class="fa-solid fa-check"></i> {{ $kpiData['coverage_gap']['status'] ?? 'Balance equilibrado' }}</span>
				<span class="sch-kpi-arrow"><i class="fa-solid fa-arrow-right"></i></span>
			</div>
		</div>
	</div>

	<!-- 4. CENTRAL ANALYTICS ROW -->
	<div class="sch-analytics-row">
		<!-- Card A: Ventas Proyectadas vs. Horas Programadas -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Ventas Proyectadas vs. Horas Laborales</h3>
				<span class="badge badge-light border text-muted">Lunes a Domingo</span>
			</div>
			<div class="sch-chart-legend">
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #38bdf8;"></span> Ventas ($ mil)</span>
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #93c5fd;"></span> Horas necesarias</span>
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: var(--daily-primary, #00C83B);"></span> Horas programadas</span>
			</div>
			<div style="height: 195px; position: relative;">
				<canvas id="chartDemandCoverage"></canvas>
			</div>
		</div>

		<!-- Card B: Productividad por Departamento (SPLH) -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Productividad por Departamento (SPLH)</h3>
				<span class="badge badge-light border text-muted">Ventas / Hora Laboral</span>
			</div>
			<div class="sch-dist-list">
				@foreach($deptList as $d)
					<div class="sch-dist-row">
						<span class="sch-dist-name" title="{{ $d['department'] }}">{{ $d['department'] }}</span>
						<div class="sch-dist-bar-bg">
							<div class="sch-dist-bar-fill" style="width: {{ min(100, $d['sales_share_pct'] * 3.2) }}%; background-color: {{ $d['color'] }};"></div>
						</div>
						<span class="sch-dist-count">${{ number_format($d['current_splh'] ?? $d['target_splh'], 0) }}/h</span>
						<span class="sch-dist-pct" style="font-size: 0.65rem; color: {{ $d['risk_level'] === 'Alto' ? '#ef4444' : '#10b981' }};">
							{{ $d['coverage_pct'] }}% cob.
						</span>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Card C: Costo Laboral vs. Presupuesto -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Costo Laboral vs. Presupuesto</h3>
				<span class="badge badge-light border text-muted">Semanal</span>
			</div>
			<div class="sch-donut-container">
				<div class="sch-donut-chart-box">
					<canvas id="chartLaborCost"></canvas>
					<div class="sch-donut-center-label">
						<div class="sch-donut-amount">{{ $analyticsData['labor_cost']['total'] ?? 'Sin información' }}</div>
						<div class="sch-donut-sub">Costo programado</div>
					</div>
				</div>
				<div class="sch-cost-list">
					@foreach($analyticsData['labor_cost']['breakdown'] as $c)
						<div class="sch-cost-item">
							<span class="sch-cost-label">
								<span class="sch-legend-dot" style="background-color: {{ $c['color'] }};"></span>
								{{ $c['name'] }}
							</span>
							<div class="sch-cost-vals">
								<span class="sch-cost-amt">{{ $c['amount'] }}</span>
								<span class="sch-cost-pct">{{ $c['pct'] }}%</span>
							</div>
						</div>
					@endforeach
				</div>
			</div>
			<div class="sch-cost-footer">
				<span class="sch-badge sch-badge-green" style="padding: 0.15rem 0.5rem;">
					Presupuesto: {{ $kpiData['labor_budget']['value'] ?? '$19,083' }}
				</span>
				<span class="text-muted" style="font-size: 0.7rem;">
					Costo vs. Ventas: <strong>{{ $analyticsData['labor_cost']['pct_sales'] ?? '8.1%' }}</strong>
				</span>
			</div>
		</div>
	</div>

	<!-- 5. MAIN SCHEDULING CALENDAR -->
	<div class="sch-main-section">
		<!-- View switch pills bar (7 Core Views + Specialized Tools) -->
		<div class="sch-view-bar">
			<div class="sch-pills-nav">
				<button type="button" class="sch-view-pill active" data-view="weekly" onclick="window.switchScheduleView('weekly', this)"><i class="fa-solid fa-calendar-week mr-1"></i> 1. Vista semanal</button>
				<button type="button" class="sch-view-pill" data-view="equity" id="viewPillEquity" onclick="window.switchScheduleView('equity', this)"><i class="fa-solid fa-scale-balanced mr-1 text-success"></i> 2. Equidad y Rotaciones</button>
				<button type="button" class="sch-view-pill" data-view="monthly" id="viewPillMonthly" onclick="window.switchScheduleView('monthly', this)"><i class="fa-solid fa-calendar-days mr-1 text-primary"></i> 3. Vista mensual</button>
				<button type="button" class="sch-view-pill" data-view="branch" id="viewPillBranch" onclick="window.switchScheduleView('branch', this)"><i class="fa-solid fa-building mr-1 text-info"></i> 4. Por sucursal</button>
				<button type="button" class="sch-view-pill" data-view="area" id="viewPillArea" onclick="window.switchScheduleView('area', this)"><i class="fa-solid fa-layer-group mr-1 text-warning"></i> 5. Por área</button>
				<button type="button" class="sch-view-pill" data-view="employee" id="viewPillEmployee" onclick="window.switchScheduleView('employee', this)"><i class="fa-solid fa-user-tie mr-1 text-purple"></i> 6. Por empleado</button>
				<button type="button" class="sch-view-pill" data-view="list" id="viewPillList" onclick="window.switchScheduleView('list', this)"><i class="fa-solid fa-list-check mr-1 text-teal"></i> 7. Lista de turnos</button>
				<button type="button" class="sch-view-pill" data-view="matrix" id="viewPillMatrix" onclick="window.switchScheduleView('matrix', this)"><i class="fa-solid fa-table-cells mr-1 text-success"></i> 8. Matriz 30 min</button>
				<button type="button" class="sch-view-pill" data-view="skills" id="viewPillSkills" onclick="window.switchScheduleView('skills', this)"><i class="fa-solid fa-user-gear mr-1 text-purple"></i> Habilidades & Apple</button>
				<button type="button" class="sch-view-pill" data-view="holidays" id="viewPillHolidays" onclick="window.switchScheduleView('holidays', this)"><i class="fa-solid fa-flag mr-1 text-danger"></i> Festivos & Vacaciones</button>
				<button type="button" class="sch-view-pill" data-view="peaks" id="viewPillPeaks" onclick="window.switchScheduleView('peaks', this)"><i class="fa-solid fa-fire mr-1 text-danger"></i> Horas Peak & Dotación</button>
			</div>
			<div class="sch-coverage-status-tag">
				<span class="sch-badge sch-badge-green">
					<span class="sch-badge-dot"></span> {{ $kpiData['demand_coverage']['status'] ?? 'Cobertura óptima' }}
				</span>
				<span class="text-muted small">Demanda cubierta: <strong>{{ $kpiData['demand_coverage']['value'] ?? '96%' }}</strong></span>
				<button type="button" class="btn btn-sm btn-link text-primary font-weight-600 p-0 ml-2" data-toggle="modal" data-target="#modalAIOptimize">Ver optimización</button>
			</div>
		</div>

		@include('components.schedule_monthly_tab')
		@include('components.schedule_equity_tab')
		@include('components.schedule_branch_tab')
		@include('components.schedule_area_tab')
		@include('components.schedule_employee_tab')
		@include('components.schedule_list_tab')
		@include('components.schedule_matrix_tab')
		@include('components.schedule_skills_tab')
		@include('components.schedule_holidays_tab')
		@include('components.schedule_peaks_tab')

		<div id="schStandardViewsContainer">
			<!-- Filters and actions toolbar -->
			<div class="sch-filter-bar">
				<div class="sch-filters-left">
					<div class="sch-filter-group">
						<span class="sch-filter-label">Sucursal</span>
						<select class="sch-filter-select" id="filterBranch">
							@foreach($branches as $b)
								<option @if(intval($b->id) === intval($branchId ?? 1)) selected @endif>{{ $b->name }}</option>
							@endforeach
						</select>
				</div>
				<div class="sch-filter-group">
					<span class="sch-filter-label">Área</span>
					<select class="sch-filter-select" id="filterArea">
						<option selected value="">Todas las áreas</option>
						@php
							$uniqueDepts = array_values(array_unique(array_filter(array_map(fn($e) => $e->department, $w['employees']))));
							sort($uniqueDepts);
						@endphp
						@foreach($uniqueDepts as $deptName)
							<option value="{{ strtolower($deptName) }}">{{ $deptName }}</option>
						@endforeach
					</select>
				</div>
				<div class="sch-filter-group">
					<span class="sch-filter-label">Cargo</span>
					<select class="sch-filter-select" id="filterRole">
						<option selected value="">Todos los roles</option>
						@php
							$uniqueRoles = array_values(array_unique(array_filter(array_map(fn($e) => $e->position, $w['employees']))));
							sort($uniqueRoles);
						@endphp
						@foreach($uniqueRoles as $roleName)
							<option value="{{ strtolower($roleName) }}">{{ $roleName }}</option>
						@endforeach
					</select>
				</div>
				<div class="sch-filter-group">
					<span class="sch-filter-label">Turno</span>
					<select class="sch-filter-select" id="filterShiftType">
						<option selected>Todos los turnos</option>
						<option>Apertura (06:00 - 15:30)</option>
						<option>Intermedio (09:00 - 18:30)</option>
						<option>Intermedio Tardío (10:00 - 20:00)</option>
						<option>Cierre (12:30 - 21:30)</option>
						<option>Cierre Total (13:00 - 22:30)</option>
					</select>
				</div>
				<div class="sch-filter-group">
					<span class="sch-filter-label">&nbsp;</span>
					<button type="button" class="sch-btn" style="padding: 0.35rem 0.65rem;" id="dateRangePicker">
						{{ date('d/m/Y', strtotime($monday)) }} - {{ date('d/m/Y', strtotime($sunday)) }} <i class="fa-solid fa-calendar-days ml-1 text-muted"></i>
					</button>
				</div>
				<div class="sch-filter-group">
					<span class="sch-filter-label">&nbsp;</span>
					<button type="button" class="sch-btn" id="btnTodayFilter">Hoy</button>
				</div>
			</div>
			<div class="sch-filters-right">
				<button type="button" class="sch-btn sch-btn-purple" data-toggle="modal" data-target="#modalAIOptimize">
					<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Optimizar con IA
				</button>
				<button type="button" class="sch-btn" id="btnCopyWeek">
					<i class="fa-solid fa-copy mr-1 text-muted"></i> Copiar semana
				</button>
			</div>
		</div>

		<!-- Grid & Sidebar Layout -->
		<div class="sch-grid-layout">
			<!-- Calendar Grid Table -->
			<div class="sch-table-wrapper">
				<table class="sch-table" id="schWeeklyTable">
					<thead>
						<tr>
							<th class="sch-th-employee">
								<div class="d-flex align-items-center justify-content-between">
									<span>Empleados (<span id="empCountDisplay">{{ count($w['employees']) }}</span>)</span>
									<span class="small text-muted">Orig. → Rec.</span>
								</div>
								<div class="sch-emp-search-box">
									<i class="fa-solid fa-magnifying-glass sch-emp-search-icon"></i>
									<input type="text" class="sch-emp-search-input" id="employeeSearchInput" placeholder="Buscar empleado...">
								</div>
							</th>
							@foreach($dayLabels as $dateKey => $d)
								<th class="sch-th-day @if(!empty($d['is_today'])) is-today @endif">
									<div class="sch-th-day-name">{{ $d['name'] }}</div>
									<div class="sch-th-day-date">{{ $d['date'] }}</div>
								</th>
							@endforeach
						</tr>
					</thead>
					<tbody id="schTableBody">
						@foreach($w['employees'] as $empIndex => $emp)
							@php
								$tot = $w['totals'][$emp->id]['scheduled'] ?? 0;
								$empAvatar = $getAvatarColor($emp->id);
								$initial = $initials($emp->name);
								$origH = $emp->original_hours ?? ($tot . 'h');
								$recH = $emp->recommended_hours ?? $origH;
								$deltaH = $emp->hours_delta ?? '0h';
								$hasRec = ($origH !== $recH);
							@endphp
							<tr class="sch-employee-row" data-emp-id="{{ $emp->id }}" data-emp-name="{{ strtolower($emp->name) }}" data-emp-dept="{{ strtolower($emp->department) }}" data-emp-role="{{ strtolower($emp->position) }}">
								<td class="sch-emp-cell">
									<div class="sch-emp-card">
										<div class="sch-emp-avatar" style="background-color: {{ $empAvatar }};">
											{{ $initial }}
										</div>
										<div class="sch-emp-details">
											<div class="sch-emp-name">{{ $emp->name }}</div>
											<div class="sch-emp-meta">{{ $emp->department }} · {{ $emp->position }}</div>
										</div>
										<div class="text-right" style="margin-left: auto; margin-right: 0.5rem;">
											@if($hasRec)
												<div class="small text-muted" style="text-decoration: line-through; font-size: 0.65rem;">{{ $origH }}</div>
												<div class="sch-emp-hours-rec" title="{{ $emp->rec_reason ?? 'Recomendación de optimización por ventas' }}">
													{{ $recH }}
												</div>
											@else
												<span class="sch-emp-hours">{{ $origH }}</span>
											@endif
										</div>
										<button type="button" class="sch-emp-menu-btn" title="Opciones" onclick="event.stopPropagation(); window.openEmpMenu({{ $emp->id }});">
											<i class="fa-solid fa-ellipsis-vertical"></i>
										</button>
									</div>
								</td>
								@foreach($dayLabels as $dateKey => $d)
									@php
										$shifts = $w['grid'][$emp->id][$dateKey] ?? [];
									@endphp
									<td class="sch-day-cell" data-employee="{{ $emp->id }}" data-date="{{ $dateKey }}">
										<div class="sch-cell-content">
											@if(!empty($shifts))
												@foreach($shifts as $s)
													@php
														$deptLower = strtolower($s->department ?: $emp->department);
														if (str_contains($deptLower, 'accesorio')) $colorClass = 'sch-shift-blue';
														elseif (str_contains($deptLower, 'bodega')) $colorClass = 'sch-shift-purple';
														elseif (str_contains($deptLower, 'despacho')) $colorClass = 'sch-shift-green';
														elseif (str_contains($deptLower, 'venta') || str_contains($deptLower, 'operaci')) $colorClass = 'sch-shift-orange';
														elseif (str_contains($deptLower, 'virtual')) $colorClass = 'sch-shift-coral';
														else $colorClass = 'sch-shift-blue';
													@endphp
													<div class="sch-shift-card {{ $colorClass }}" data-shift-id="{{ $s->id }}" draggable="true" onclick="window.showShiftDetail({
														id: {{ $s->id }},
														start_time: '{{ $s->start_time }}',
														end_time: '{{ $s->end_time }}',
														duration: '{{ $s->hours }}h',
														shift_date: '{{ $s->shift_date }}',
														date_formatted: '{{ $d['name'] }} {{ $d['date'] }}',
														employee_name: '{{ $emp->name }}',
														employee_id: {{ $emp->id }},
														employee_avatar: '{{ $initial }}',
														department: '{{ $s->department ?: $emp->department }}',
														role: '{{ $s->role ?: $emp->position }}',
														status: '{{ $s->status === 'published' ? 'Confirmado' : 'Borrador' }}',
														branch: '{{ \Grocy\Services\BranchService::NameById(intval($s->branch_id ?? $branchId ?? 1)) }}',
														cost: '${{ number_format($s->hours * floatval($emp->hourly_cost ?: 16), 2) }}'
													})">
														<div class="sch-shift-header">
															<span class="sch-shift-time">{{ $s->start_time }} - {{ $s->end_time }}</span>
															<span class="sch-shift-dots"><i class="fa-solid fa-ellipsis-vertical"></i></span>
														</div>
														<div class="sch-shift-dept">{{ $s->department ?: $emp->department }}</div>
													</div>
												@endforeach
											@else
												<div class="sch-shift-empty" onclick="window.createShiftForCell({{ $emp->id }}, '{{ $dateKey }}')">
													Libre
												</div>
											@endif
										</div>
									</td>
								@endforeach
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>

			<!-- Right Sidebar: Intelligent Panel & Shift Detail -->
			<div class="sch-sidebar-panel">

				<!-- Panel Inteligente: Recomendaciones de Ajuste según Ventas -->
				<div class="sch-side-card">
					<div class="sch-side-card-title d-flex align-items-center justify-content-between">
						<span><i class="fa-solid fa-wand-magic-sparkles text-purple mr-1"></i> Recomendaciones IA</span>
						<span class="badge badge-purple" style="font-size: 0.65rem;">{{ count($sidebarData['recommendations'] ?? []) }} activas</span>
					</div>

					@if(!empty($sidebarData['potential_savings']) && $sidebarData['potential_savings'] > 0)
						<div class="p-2 mb-2 rounded bg-purple-light border text-purple small d-flex align-items-center justify-content-between">
							<span><strong>Ahorro semanal estimado:</strong></span>
							<strong class="h6 mb-0">${{ number_format($sidebarData['potential_savings'], 2) }}</strong>
						</div>
					@endif

					<div class="sch-recs-scrollable" style="max-height: 280px; overflow-y: auto;">
						@forelse($sidebarData['recommendations'] ?? [] as $rec)
							<div class="sch-rec-card-item">
								<div class="sch-rec-card-head">
									<span class="sch-rec-card-title">{{ $rec['employee_name'] }}</span>
									<span class="sch-rec-card-hours">
										{{ $rec['original_hours'] }}h → <strong>{{ $rec['recommended_hours'] }}h</strong>
									</span>
								</div>
								<div class="sch-rec-card-reason">
									{{ $rec['reason'] }}
								</div>
								<div class="sch-rec-card-footer">
									<span class="text-success font-weight-bold">Ahorro: ${{ number_format(abs($rec['savings_amount']), 2) }}</span>
									<div class="btn-group btn-group-sm">
										<button type="button" class="btn btn-xs btn-outline-success py-0 px-1" title="Aceptar recomendación" onclick="window.decideRec({{ $rec['id'] }}, 'approved')">
											<i class="fa-solid fa-check"></i>
										</button>
										<button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1" title="Modificar" onclick="window.editRec({{ $rec['id'] }}, {{ $rec['recommended_hours'] }})">
											<i class="fa-solid fa-pen"></i>
										</button>
										<button type="button" class="btn btn-xs btn-outline-danger py-0 px-1" title="Rechazar" onclick="window.decideRec({{ $rec['id'] }}, 'rejected')">
											<i class="fa-solid fa-xmark"></i>
										</button>
									</div>
								</div>
							</div>
						@empty
							<div class="text-center py-3 text-muted small">
								<i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
								Horarios equilibrados según demanda. No se requieren ajustes adicionales.
							</div>
						@endforelse
					</div>
				</div>

				<!-- Shift Detail Card (Interactive Live Preview) -->
				<div class="sch-side-card" id="schDetailCard">
					<div class="sch-detail-head">
						<span class="sch-side-card-title" style="margin: 0;">Detalle del turno</span>
						<button type="button" class="sch-detail-edit-btn" id="btnEditSelectedShift">
							<i class="fa-solid fa-pen-to-square"></i> Editar
						</button>
					</div>
					<div class="sch-detail-date" id="detailShiftDate">{{ $selectedShift['date_formatted'] }}</div>
					<div class="sch-detail-time" id="detailShiftTime">{{ $selectedShift['start_time'] }} - {{ $selectedShift['end_time'] }} ({{ $selectedShift['duration'] }})</div>
					<div class="sch-detail-dept" id="detailShiftDeptRole">{{ $selectedShift['department'] }} · {{ $selectedShift['role'] }}</div>

					<div class="sch-detail-emp-box">
						<div class="sch-detail-avatar" id="detailShiftAvatar">{{ $selectedShift['employee_avatar'] }}</div>
						<div>
							<div class="font-weight-bold" style="font-size: 0.8rem; color: #0f172a;" id="detailShiftEmpName">{{ $selectedShift['employee_name'] }}</div>
							<div class="text-muted" style="font-size: 0.68rem;" id="detailShiftEmpId">ID: {{ $selectedShift['employee_id'] }}</div>
						</div>
					</div>

					<div class="sch-detail-rows">
						<div class="sch-detail-row">
							<span>Estado</span>
							<strong class="text-success" id="detailShiftStatus"><i class="fa-solid fa-circle font-size-xs mr-1" style="font-size: 6px;"></i>{{ $selectedShift['status'] }}</strong>
						</div>
						<div class="sch-detail-row">
							<span>Sucursal</span>
							<strong id="detailShiftBranch">{{ $selectedShift['branch'] }}</strong>
						</div>
						<div class="sch-detail-row">
							<span>Área</span>
							<strong id="detailShiftArea">{{ $selectedShift['department'] }}</strong>
						</div>
						<div class="sch-detail-row">
							<span>Rol</span>
							<strong id="detailShiftRole">{{ $selectedShift['role'] }}</strong>
						</div>
						<div class="sch-detail-row">
							<span>Costo estimado</span>
							<strong id="detailShiftCost">{{ $selectedShift['cost'] }}</strong>
						</div>
					</div>

					<div class="sch-detail-actions">
						<button type="button" class="sch-btn" id="btnMoveShift">Mover</button>
						<button type="button" class="sch-btn" id="btnDuplicateShift">Duplicar</button>
						<button type="button" class="sch-btn text-danger" id="btnDeleteShift">Eliminar</button>
					</div>
				</div>

				<!-- Quick Actions Card -->
				<div class="sch-side-card">
					<div class="sch-side-card-title">
						<span>Herramientas rápidas</span>
					</div>
					<div class="sch-quick-list">
						<button type="button" class="sch-quick-btn text-purple" data-toggle="modal" data-target="#modalAIOptimize">
							<i class="fa-solid fa-wand-magic-sparkles"></i> Optimización con IA
						</button>
						<button type="button" class="sch-quick-btn text-teal" data-toggle="modal" data-target="#modalSimulateScenario">
							<i class="fa-solid fa-chart-line"></i> Simular What-If
						</button>
						<button type="button" class="sch-quick-btn text-blue" id="quickAssignShift">
							<i class="fa-solid fa-user-plus"></i> Asignar turno
						</button>
						<button type="button" class="sch-quick-btn text-green" id="quickApproveHours">
							<i class="fa-solid fa-circle-check"></i> Aprobar horas
						</button>
						<button type="button" class="sch-quick-btn text-indigo" id="quickGenerateReport">
							<i class="fa-solid fa-file-lines"></i> Generar reporte
						</button>
					</div>
			</div>
		</div>
		</div><!-- #schStandardViewsContainer -->
	</div>

	<!-- 6. BOTTOM ROW: 4 CARDS -->
	<div class="sch-bottom-grid">
		<!-- Card 1: Ausencias y solicitudes -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Ausencias y solicitudes (7)</h3>
				<a href="#tab-requests" class="text-primary font-weight-600 small">Ver todas</a>
			</div>
			<div class="sch-abs-list">
				@foreach($bottomCards['requests'] as $req)
					<div class="sch-abs-item">
						<div class="sch-abs-avatar" style="background-color: {{ $req['avatar_color'] }};">
							{{ $req['avatar'] }}
						</div>
						<div class="sch-abs-info">
							<div class="sch-abs-name">{{ $req['name'] }}</div>
							<div class="sch-abs-type">{{ $req['type'] }}</div>
						</div>
						<div class="sch-abs-meta">
							<div class="sch-abs-dates">{{ $req['dates'] }}</div>
							@if($req['status'] === 'Pendiente')
								<span class="sch-badge sch-badge-amber" style="padding: 0.15rem 0.4rem; font-size: 0.65rem;">Pendiente</span>
							@elseif($req['status'] === 'Requiere aprobación')
								<span class="sch-badge sch-badge-danger" style="padding: 0.15rem 0.4rem; font-size: 0.65rem;">Requiere aprobación</span>
							@else
								<span class="sch-badge sch-badge-info" style="padding: 0.15rem 0.4rem; font-size: 0.65rem;">Por revisar</span>
							@endif
						</div>
						<button type="button" class="sch-emp-menu-btn" title="Opciones" onclick="window.decideRequest('{{ $req['name'] }}')">
							<i class="fa-solid fa-ellipsis-vertical"></i>
						</button>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Card 2: Cobertura por franja horaria -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Cobertura por franja horaria</h3>
				<span class="badge badge-light border text-muted">8 Franjas</span>
			</div>
			<div class="sch-chart-legend" style="margin-bottom: 0.4rem;">
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #06b6d4;"></span> Programados</span>
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: var(--daily-primary, #00C83B);"></span> Necesarios</span>
				<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #a855f7;"></span> Diferencia</span>
			</div>
			<div style="height: 155px; position: relative;">
				<canvas id="chartHourlyCoverage"></canvas>
			</div>
		</div>

		<!-- Card 3: Comparativa Horario Original vs. Optimizado -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Impacto de la Optimización por Ventas</h3>
				<span class="badge badge-purple" style="font-size: 0.65rem;">IA Motor 35</span>
			</div>
			<div class="p-2">
				<div class="d-flex align-items-center justify-content-between mb-2 p-2 rounded bg-light border">
					<div>
						<div class="small text-muted">Horas Planificadas</div>
						<div class="font-weight-bold text-dark">{{ $analyticsData['schedule_comparison']['original_hours'] ?? '1,024' }} h → <span class="text-purple">{{ $analyticsData['schedule_comparison']['optimized_hours'] ?? '976' }} h</span></div>
					</div>
					<div class="text-right">
						<span class="badge badge-success">-{{ $analyticsData['schedule_comparison']['hours_saved'] ?? '48' }} h</span>
					</div>
				</div>

				<div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border mb-2">
					<div>
						<div class="small text-muted">Costo Laboral Proyectado</div>
						<div class="font-weight-bold text-dark">{{ $kpiData['scheduled_cost']['value'] ?? 'Sin información' }} → <span class="text-success">{{ isset($analyticsData['schedule_comparison']['optimized_cost']) ? '$' . number_format($analyticsData['schedule_comparison']['optimized_cost']) : 'Sin información' }}</span></div>
					</div>
					<div class="text-right">
						<span class="badge badge-success">Ahorro {{ isset($analyticsData['schedule_comparison']['potential_savings']) ? '$' . number_format($analyticsData['schedule_comparison']['potential_savings']) : 'Sin información' }}</span>
					</div>
				</div>

				<div class="small text-muted">
					<i class="fa-solid fa-shield-halved text-success mr-1"></i> Garantiza cobertura mínima de apertura y respeto a contratos garantizados.
				</div>
			</div>
		</div>

		<!-- Card 4: Productividad y cumplimiento -->
		<div class="sch-card">
			<div class="sch-card-header">
				<h3 class="sch-card-title">Productividad y cumplimiento</h3>
				<span class="badge badge-light border text-muted">Semanal</span>
			</div>
			<div class="sch-prod-body">
				<div class="sch-gauge-box">
					<canvas id="chartProductivityGauge"></canvas>
					<div class="sch-gauge-label">
						<div class="sch-gauge-val">{{ $bottomCards['productivity']['rate'] }}%</div>
						<div class="sch-gauge-sub">Asistencia</div>
					</div>
				</div>
				<div class="sch-prod-list">
					@foreach($bottomCards['productivity']['metrics'] as $m)
						<div class="sch-prod-item">
							<span class="sch-prod-name">
								<span class="sch-legend-dot" style="background-color: {{ $m['color'] }};"></span>
								{{ $m['name'] }}
							</span>
							<div>
								<strong class="mr-1" style="font-size: 0.75rem;">{{ $m['value'] }}</strong>
								<span class="text-success font-weight-600" style="font-size: 0.68rem;">{{ $m['delta'] }}</span>
							</div>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

</div>
</div>

<!-- ================= MODALS & DIALOGS ================= -->

<!-- Modal 1: Crear / Editar Turno -->
<div class="modal fade" id="modalShiftEdit" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px; border: 1px solid var(--sch-border);">
			<div class="modal-header">
				<h5 class="modal-title font-weight-bold" id="shiftModalTitle">Crear nuevo turno</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<form id="formShiftEdit" autocomplete="off">
				<div class="modal-body">
					<input type="hidden" name="id" id="inputShiftId">
					<input type="hidden" name="branch_id" value="{{ $branchId }}">

					<div class="form-group">
						<label class="font-weight-600 small">Empleado</label>
						<select name="employee_id" id="inputShiftEmployee" class="custom-select custom-select-sm" required>
							@foreach($w['employees'] as $e)
								<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->department }} · {{ $e->position }})</option>
							@endforeach
						</select>
					</div>

					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Fecha</label>
							<input type="date" name="shift_date" id="inputShiftDate" class="form-control form-control-sm" value="2026-10-23" required>
						</div>
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Área / Departamento</label>
							<select name="department" id="inputShiftDept" class="custom-select custom-select-sm">
								<option value="Dairy">Dairy</option>
								<option value="Fruver">Fruver</option>
								<option value="Carnes">Carnes</option>
								<option value="Panadería">Panadería</option>
								<option value="Caja">Caja</option>
								<option value="Reposición">Reposición</option>
								<option value="Mantenimiento">Mantenimiento</option>
								<option value="Administración">Administración</option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-4">
							<label class="font-weight-600 small">Hora inicio</label>
							<input type="time" name="start_time" id="inputShiftStart" class="form-control form-control-sm" value="07:00" required>
						</div>
						<div class="form-group col-md-4">
							<label class="font-weight-600 small">Hora fin</label>
							<input type="time" name="end_time" id="inputShiftEnd" class="form-control form-control-sm" value="15:00" required>
						</div>
						<div class="form-group col-md-4">
							<label class="font-weight-600 small">Descanso (min)</label>
							<input type="number" name="break_minutes" id="inputShiftBreak" class="form-control form-control-sm" value="30" min="0" max="180">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Rol</label>
							<input type="text" name="role" id="inputShiftRole" class="form-control form-control-sm" value="Asociado">
						</div>
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Estado</label>
							<select name="status" id="inputShiftStatus" class="custom-select custom-select-sm">
								<option value="published">Confirmado / Publicado</option>
								<option value="planned">Borrador / Planificado</option>
							</select>
						</div>
					</div>

					<div class="form-group mb-2">
						<label class="font-weight-600 small">Notas operativas</label>
						<input type="text" name="notes" id="inputShiftNotes" class="form-control form-control-sm" placeholder="Ej. Apoyo en conteo FEFO...">
					</div>

					<div class="p-2 border rounded bg-light" id="shiftConflictPreview">
						<div class="small text-success"><i class="fa-solid fa-check mr-1"></i>Sin conflictos detectados en esta franja.</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-light border mr-auto" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-sm btn-outline-danger d-none" id="btnModalDeleteShift"><i class="fa-solid fa-trash mr-1"></i>Eliminar</button>
					<button type="submit" class="btn btn-sm btn-primary" id="btnModalSaveShift"><i class="fa-solid fa-save mr-1"></i>Guardar turno</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal 2: Optimización Inteligente de Horarios según Ventas -->
<div class="modal fade" id="modalAIOptimize" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content" style="border-radius: 12px;">
			<div class="modal-header">
				<h5 class="modal-title font-weight-bold text-primary">
					<i class="fa-solid fa-wand-magic-sparkles mr-2 text-purple"></i> Motor de Planificación Dinámica según Ventas
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<div class="alert alert-info py-2" style="font-size: 0.8125rem;">
					<strong>Planificación fundamentada en demanda real:</strong> Distribuye el presupuesto laboral objetivo ({{ $budgetData['target_pct'] ?? '8.5' }}%) entre Dairy, Grocery, Produce, Carnes, Panadería y Caja según SPLH, unidades por hora y dotación mínima de seguridad.
				</div>

				<div class="row mb-3">
					<div class="col-md-3">
						<div class="p-2 border rounded bg-light text-center">
							<div class="small text-muted">Ventas Proyectadas</div>
							<div class="h6 font-weight-bold text-primary mb-0">{{ isset($dyn['sales']['total_projected']) ? '$' . number_format($dyn['sales']['total_projected']) : 'Sin información' }}</div>
							<div class="small text-muted">+14% vs. promedio</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="p-2 border rounded bg-light text-center">
							<div class="small text-muted">Presupuesto (8.5%)</div>
							<div class="h6 font-weight-bold text-purple mb-0">{{ isset($budgetData['weekly_amount']) ? '$' . number_format($budgetData['weekly_amount']) : 'Sin información' }}</div>
							<div class="small text-muted">Financiable: {{ $budgetData['fundable_hours'] ?? '—' }}h</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="p-2 border rounded bg-light text-center">
							<div class="small text-muted">Costo Programado</div>
							<div class="h6 font-weight-bold text-dark mb-0">{{ isset($budgetData['scheduled_amount']) ? '$' . number_format($budgetData['scheduled_amount']) : 'Sin información' }}</div>
							<div class="small text-success font-weight-bold">✓ Bajo presupuesto</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="p-2 border rounded bg-light text-center">
							<div class="small text-muted">Ahorro Potencial</div>
							<div class="h6 font-weight-bold text-success mb-0">{{ isset($sidebarData['potential_savings']) ? '$' . number_format($sidebarData['potential_savings']) : 'Sin información' }}</div>
							<div class="small text-muted">{{ count($sidebarData['recommendations'] ?? []) }} propuestas</div>
						</div>
					</div>
				</div>

				<h6 class="font-weight-bold mb-2">Propuestas de ajuste equitativo verificables:</h6>
				<div class="list-group list-group-flush border rounded mb-3" style="max-height: 250px; overflow-y: auto; font-size: 0.8125rem;">
					@forelse($sidebarData['recommendations'] ?? [] as $rec)
						<div class="list-group-item d-flex align-items-center justify-content-between p-2">
							<div>
								<div class="font-weight-bold text-dark">
									{{ $rec['employee_name'] }} ({{ $rec['department'] }}): {{ $rec['original_hours'] }}h → <span class="text-purple">{{ $rec['recommended_hours'] }}h</span>
									<span class="badge badge-light border ml-1">Mínimo garantizado: {{ $rec['guaranteed_min_hours'] }}h</span>
								</div>
								<div class="text-muted small mt-1">{{ $rec['reason'] }}</div>
								<div class="text-success small font-weight-600 mt-1">Ahorro estimado: ${{ number_format(abs($rec['savings_amount']), 2) }}</div>
							</div>
							<div class="btn-group btn-group-sm ml-2">
								<button type="button" class="btn btn-outline-success" onclick="window.decideRec({{ $rec['id'] }}, 'approved')">Aceptar</button>
								<button type="button" class="btn btn-outline-danger" onclick="window.decideRec({{ $rec['id'] }}, 'rejected')">Rechazar</button>
							</div>
						</div>
					@empty
						<div class="p-3 text-center text-muted">No hay ajustes pendientes. La programación actual respeta el presupuesto y la demanda óptima.</div>
					@endforelse
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-sm btn-primary" onclick="toastr.success('Plan de horarios optimizado aplicado y auditado'); $('#modalAIOptimize').modal('hide');">
					<i class="fa-solid fa-check mr-1"></i> Aplicar recomendaciones seleccionadas
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal 3: Simulador de Escenarios What-If -->
<div class="modal fade" id="modalSimulateScenario" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px;">
			<div class="modal-header">
				<h5 class="modal-title font-weight-bold text-info">
					<i class="fa-solid fa-chart-line mr-2"></i> Simulador de Escenarios Laborales
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<form id="formWhatIf" autocomplete="off">
				<div class="modal-body">
					<p class="text-muted small mb-3">Simula el impacto de variaciones de ventas, ausencias imprevistas o carga extraordinaria en el presupuesto y la cobertura necesaria.</p>

					<div class="form-group">
						<label class="font-weight-600 small d-flex justify-content-between">
							<span>Variación en Ventas (%)</span>
							<span id="labelSalesVar" class="font-weight-bold text-primary">+0%</span>
						</label>
						<input type="range" class="custom-range" id="inputSalesVar" min="-50" max="100" step="5" value="0">
					</div>

					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Pallets extra recepción</label>
							<input type="number" id="inputExtraPallets" class="form-control form-control-sm" value="0" min="0" max="200">
						</div>
						<div class="form-group col-md-6">
							<label class="font-weight-600 small">Ausencias imprevistas</label>
							<input type="number" id="inputAbsentStaff" class="form-control form-control-sm" value="0" min="0" max="20">
						</div>
					</div>

					<div class="form-group">
						<label class="font-weight-600 small">% Presupuesto Laboral Objetivo</label>
						<input type="number" id="inputTargetBudgetPct" class="form-control form-control-sm" value="8.5" step="0.1" min="5" max="15">
					</div>

					<div class="p-3 border rounded bg-light" id="whatIfResultBox">
						<div class="small text-muted mb-1 font-weight-600">Impacto Proyectado:</div>
						<div class="d-flex justify-content-between small py-1">
							<span>Ventas simuladas:</span>
							<strong id="simSalesVal">{{ isset($dyn['sales']['total_projected']) ? '$' . number_format($dyn['sales']['total_projected']) : 'Sin información' }}</strong>
						</div>
						<div class="d-flex justify-content-between small py-1">
							<span>Presupuesto disponible:</span>
							<strong id="simBudgetVal" class="text-purple">{{ isset($budgetData['weekly_amount']) ? '$' . number_format($budgetData['weekly_amount']) : 'Sin información' }}</strong>
						</div>
						<div class="d-flex justify-content-between small py-1">
							<span>Horas necesarias:</span>
							<strong id="simHoursVal">{{ $budgetData['min_safety_hours'] ?? '—' }} h</strong>
						</div>
						<div class="d-flex justify-content-between small py-1">
							<span>Riesgo operativo:</span>
							<strong id="simRiskVal" class="text-success">Bajo</strong>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-sm btn-info" id="btnExecuteWhatIf">
						<i class="fa-solid fa-calculator mr-1"></i> Recalcular Escenario
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal 4: Estándares de Productividad y SPLH por Departamento -->
<div class="modal fade" id="modalDeptStandards" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content" style="border-radius: 12px;">
			<div class="modal-header">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-sliders mr-2 text-primary"></i> Estándares Departamentales de Productividad (SPLH)
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small">Configura la meta de ventas por hora laboral (SPLH), unidades procesadas por hora y dotación mínima indispensable de seguridad para cada área.</p>
				<div class="table-responsive">
					<table class="table table-sm table-bordered">
						<thead class="bg-light">
							<tr>
								<th>Departamento</th>
								<th>SPLH Meta ($/h)</th>
								<th>Unidades / h</th>
								<th>Mín. Apertura</th>
								<th>Mín. Pico</th>
								<th>% Ventas</th>
							</tr>
						</thead>
						<tbody>
							@foreach($deptList as $d)
								<tr>
									<td class="font-weight-600">{{ $d['department'] }}</td>
									<td><input type="number" class="form-control form-control-sm py-0" value="{{ $d['target_splh'] }}" style="width: 90px;"></td>
									<td><input type="number" class="form-control form-control-sm py-0" value="{{ $d['units_per_hour'] }}" style="width: 80px;"></td>
									<td><input type="number" class="form-control form-control-sm py-0" value="1" style="width: 60px;"></td>
									<td><input type="number" class="form-control form-control-sm py-0" value="2" style="width: 60px;"></td>
									<td><input type="number" class="form-control form-control-sm py-0" value="{{ $d['sales_share_pct'] }}" style="width: 70px;">%</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary" onclick="toastr.success('Estándares departamentales actualizados'); $('#modalDeptStandards').modal('hide');">
					<i class="fa-solid fa-save mr-1"></i> Guardar Estándares
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal 5: Automatización Diaria (10 Pasos) -->
<div class="modal fade" id="modalDailyAutomation" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px;">
			<div class="modal-header">
				<h5 class="modal-title font-weight-bold text-primary">
					<i class="fa-solid fa-robot mr-2"></i> Automatización Diaria de Planificación
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small">Ejecuta el ciclo de optimización diaria de DAILY en 10 pasos estructurados:</p>
				<ol class="small text-muted pl-3 mb-3">
					<li>Importar ventas históricas y transacciones recientes.</li>
					<li>Pronosticar ventas por día y franja horaria.</li>
					<li>Estimar carga operativa por departamento.</li>
					<li>Calcular horas necesarias y presupuesto disponible (8.5%).</li>
					<li>Comparar con el horario existente publicado.</li>
					<li>Generar recomendaciones individualizadas respetando contratos.</li>
					<li>Mostrar impacto financiero y operativo cuantificado.</li>
					<li>Solicitar aprobación supervisada del manager.</li>
					<li>Publicar horario aprobado con trazabilidad inalterable.</li>
					<li>Notificar automáticamente a los colaboradores afectados.</li>
				</ol>
				<div class="p-2 border rounded bg-light small" id="dailyAutoStatus">
					<div class="text-success"><i class="fa-solid fa-circle-check mr-1"></i>Listo para ejecutar el ciclo diario de la sucursal.</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-sm btn-primary" id="btnRunDailyAutoExec">
					<i class="fa-solid fa-play mr-1"></i> Ejecutar ciclo diario
				</button>
			</div>
		</div>
	</div>
</div>

@include('components.schedule_policies_modal')

<script>
window.DairyScheduleData = {
	branchId: {{ $branchId }},
	monday: '{{ $monday }}',
	sunday: '{{ $sunday }}',
	analytics: {!! json_encode($analyticsData) !!},
	bottomCards: {!! json_encode($bottomCards) !!},
	selectedShift: {!! json_encode($selectedShift) !!},
	dynamicPlan: {!! json_encode($dynamicPlan ?? []) !!},
	sidebar: {!! json_encode($sidebarData) !!},
	equityData: {!! json_encode($equityData ?? []) !!}
};

window.switchScheduleView = function(view, btn) {
	$('.sch-view-pill').removeClass('active');
	if (btn) {
		$(btn).addClass('active');
	} else {
		$('.sch-view-pill[data-view="' + view + '"]').addClass('active');
	}

	if (history.replaceState) {
		var u = new URL(window.location);
		u.searchParams.set('view', view);
		window.history.replaceState({}, '', u);
	}

	$('#schStandardViewsContainer, #schMonthlySection, #schEquitySection, #schViewBranch, #schViewArea, #schViewEmployee, #schViewList, #schSkillsSection, #schHolidaysSection, #schPeaksSection, #schViewMatrix').hide();

	if (view === 'matrix') {
		$('#schViewMatrix').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.success('Cargando Matriz de Cobertura en Intervalos de 30 Minutos');
	} else if (view === 'monthly') {
		$('#schMonthlySection').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.success('Cargando Calendario Mensual 2026–2030');
	} else if (view === 'equity') {
		$('#schEquitySection').fadeIn(150);
		if (typeof window.initEquityChart === 'function') window.initEquityChart();
		if (typeof toastr !== 'undefined') toastr.success('Cargando Equidad y Rotaciones');
	} else if (view === 'branch') {
		$('#schViewBranch').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Gestión Multisucursal');
	} else if (view === 'area') {
		$('#schViewArea').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Cobertura Operativa por Área');
	} else if (view === 'employee') {
		$('#schViewEmployee').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Ficha por Empleado');
	} else if (view === 'list') {
		$('#schViewList').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Lista Maestra de Turnos');
	} else if (view === 'skills') {
		$('#schSkillsSection').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Matriz de Habilidades y Cobertura Apple');
	} else if (view === 'holidays') {
		$('#schHolidaysSection').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.info('Cargando Calendario de Festivos y Vacaciones');
	} else if (view === 'peaks') {
		$('#schPeaksSection').fadeIn(150);
		if (typeof toastr !== 'undefined') toastr.success('Cargando Horas Peak y Dotación Inteligente');
	} else {
		$('#schStandardViewsContainer').fadeIn(150);
	}
	if (typeof window.renderViewInsights === 'function') window.renderViewInsights(view);
};

window.triggerRestoreDemo = function() {
	if (!confirm('¿Confirma que desea restaurar el escenario "Doral"?\n\nEsta acción reconstruirá de forma idempotente las 25 personas, 7 áreas y horarios de Noviembre 2026 sin tocar ninguna otra sucursal o dato real.')) {
		return;
	}
	if (typeof toastr !== 'undefined') toastr.info('Restaurando escenario de ejemplo...');
	fetch(U('/api/schedule/demo/restore'), {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' }
	})
	.then(function(r) { return r.json(); })
	.then(function(data) {
		if (data.success) {
			if (typeof toastr !== 'undefined') toastr.success(data.message || 'Escenario de ejemplo restaurado');
			setTimeout(function() { window.location.reload(); }, 900);
		} else {
			alert('Error: ' + (data.error_message || 'No se pudo restaurar el escenario'));
		}
	})
	.catch(function(err) {
		alert('Error al conectar con el servidor: ' + err.message);
	});
};

window.onMatrixParamsChanged = function() {
	var date = $('#matrixDatePicker').val() || '2026-11-04';
	var dept = $('#matrixDeptSelector').val() || 'all';
	var scenario = $('#matrixScenarioSelector').val() || 'normal';
	var branchId = $('#branchSelector').val() || '{{ $branchId }}';

	$('#matrixDisplayDate').text(date);

	fetch(U('/api/schedule/demo/coverage-matrix?branch_id=' + branchId + '&date=' + date + '&department=' + encodeURIComponent(dept) + '&scenario=' + scenario))
	.then(function(r) { return r.json(); })
	.then(function(res) {
		if (!res || !res.intervals) return;
		window.renderMatrixRows(res);
	})
	.catch(function(e) {
		console.error('Error fetching coverage matrix:', e);
	});
};

window.renderMatrixRows = function(data) {
	var intervals = data.intervals;
	var summary = data.summary;

	$('#matrixKpiCompliance').text(summary.coverage_compliance_pct + '%').css('color', summary.coverage_compliance_pct >= 95 ? '#059669' : '#ef4444');
	$('#matrixKpiUncovered').text(summary.uncovered_slots + ' franjas').toggleClass('text-danger', summary.uncovered_slots > 0).toggleClass('text-success', summary.uncovered_slots === 0);
	$('#matrixKpiDeficitHours').text(summary.total_deficit_hours + ' h');
	$('#matrixKpiSurplusHours').text('+' + summary.total_surplus_hours + ' h');

	var html = '';
	intervals.forEach(function(slot, idx) {
		var isDeficit = (slot.balance < 0);
		var hasBreak = (slot.on_break > 0);
		var rowClass = isDeficit ? 'table-danger' : (hasBreak ? 'bg-amber-light' : '');

		html += '<tr class="matrix-row ' + rowClass + '" data-status="' + slot.status + '" data-has-break="' + (hasBreak ? '1' : '0') + '">';
		html += '<td class="text-left pl-3 font-weight-bold"><span class="badge badge-light border text-dark">' + slot.slot_label + '</span></td>';
		html += '<td><span class="font-weight-600 text-muted">' + slot.available + '</span></td>';
		html += '<td><span class="font-weight-600 text-primary">' + slot.scheduled + '</span></td>';
		html += '<td>' + (slot.on_break > 0 ? '<span class="badge badge-warning px-2 font-weight-bold"><i class="fa-solid fa-mug-saucer mr-1"></i>' + slot.on_break + '</span>' : '<span class="text-muted">0</span>') + '</td>';
		html += '<td><span class="badge ' + (isDeficit ? 'badge-danger' : 'badge-success') + ' px-2 font-weight-bold" style="font-size: 0.82rem;">' + slot.working + ' personas</span></td>';
		html += '<td><div class="input-group input-group-sm d-inline-flex justify-content-center" style="width: 80px;">' +
		        '<input type="number" class="form-control form-control-sm text-center font-weight-bold matrix-req-input" value="' + slot.required + '" min="0" max="25" data-slot-idx="' + idx + '" data-working="' + slot.working + '" onchange="window.updateMatrixSlotRequirement(this, ' + idx + ')">' +
		        '</div></td>';
		html += '<td><strong class="' + (slot.balance < 0 ? 'text-danger' : (slot.balance === 0 ? 'text-success' : 'text-primary')) + '">' + (slot.balance > 0 ? '+' : '') + slot.balance + '</strong></td>';
		html += '<td>' + (slot.status === 'deficit' ? '<span class="badge badge-danger px-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>DÉFICIT</span>' : (slot.status === 'optimal' ? '<span class="badge badge-success px-2"><i class="fa-solid fa-check mr-1"></i>ÓPTIMO</span>' : '<span class="badge badge-info px-2">+' + slot.balance + ' HOLGURA</span>')) + '</td>';
		html += '<td class="text-left pl-3"><div class="d-flex flex-wrap align-items-center gap-1">';
		(slot.workers || []).forEach(function(w) {
			html += '<span class="badge badge-light border text-dark" style="font-size: 0.72rem;"><i class="fa-solid fa-user text-success mr-1"></i>' + w + '</span> ';
		});
		(slot.break_workers || []).forEach(function(b) {
			html += '<span class="badge badge-warning text-dark border-warning" style="font-size: 0.72rem;" title="En colación"><i class="fa-solid fa-mug-saucer mr-1"></i>' + b + ' (pausa)</span> ';
		});
		if ((!slot.workers || slot.workers.length === 0) && (!slot.break_workers || slot.break_workers.length === 0)) {
			html += '<span class="text-muted font-italic" style="font-size: 0.72rem;">Sin personal programado</span>';
		}
		html += '</div></td></tr>';
	});

	$('#matrixTableBody').html(html);
};

window.updateMatrixSlotRequirement = function(input, idx) {
	var req = parseInt($(input).val(), 10) || 0;
	var working = parseInt($(input).data('working'), 10) || 0;
	var balance = working - req;
	var tr = $(input).closest('tr');

	tr.find('td:eq(6) strong').text((balance > 0 ? '+' : '') + balance)
	  .attr('class', balance < 0 ? 'text-danger' : (balance === 0 ? 'text-success' : 'text-primary'));

	if (balance < 0) {
		tr.attr('class', 'matrix-row table-danger').attr('data-status', 'deficit');
		tr.find('td:eq(7)').html('<span class="badge badge-danger px-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>DÉFICIT</span>');
	} else if (balance === 0) {
		tr.attr('class', 'matrix-row').attr('data-status', 'optimal');
		tr.find('td:eq(7)').html('<span class="badge badge-success px-2"><i class="fa-solid fa-check mr-1"></i>ÓPTIMO</span>');
	} else {
		tr.attr('class', 'matrix-row').attr('data-status', 'surplus');
		tr.find('td:eq(7)').html('<span class="badge badge-info px-2">+' + balance + ' HOLGURA</span>');
	}

	if (typeof toastr !== 'undefined') toastr.info('Requerimiento actualizado para franja. Recalculando balance.');
};

window.filterMatrixRows = function(filter) {
	$('#btnMatrixFilterAll, #btnMatrixFilterDeficit, #btnMatrixFilterBreak').removeClass('active');
	if (filter === 'deficit') {
		$('#btnMatrixFilterDeficit').addClass('active');
		$('.matrix-row').hide();
		$('.matrix-row[data-status="deficit"]').show();
	} else if (filter === 'break') {
		$('#btnMatrixFilterBreak').addClass('active');
		$('.matrix-row').hide();
		$('.matrix-row[data-has-break="1"]').show();
	} else {
		$('#btnMatrixFilterAll').addClass('active');
		$('.matrix-row').show();
	}
};

window.applyValidationScenario = function(caseKey, date, dept) {
	$('#modalValidationScenarios').modal('hide');
	if (typeof toastr !== 'undefined') toastr.success('Cargando Caso de Prueba: ' + caseKey);

	// Configurar parámetros de matriz
	if (date) $('#matrixDatePicker').val(date);
	if (dept) $('#matrixDeptSelector').val(dept);

	window.switchScheduleView('matrix');
	window.onMatrixParamsChanged();
};

document.addEventListener('DOMContentLoaded', function() {
	var params = new URLSearchParams(window.location.search);
	var initialView = params.get('view') || params.get('tab');
	if (initialView && ['monthly', 'equity', 'branch', 'area', 'employee', 'list', 'skills', 'holidays', 'peaks', 'weekly', 'matrix'].indexOf(initialView) !== -1) {
		window.switchScheduleView(initialView);
	}
});
</script>

@include('components.schedule_demo_validation_modal')
@endsection

@push('pageScripts')
<script src="{{ $U('/viewjs/schedule.js?t=', true) }}{{ time() }}"></script>
@endpush
