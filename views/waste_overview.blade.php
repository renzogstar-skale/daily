@php require_frontend_packages(['datatables', 'chartjs']); @endphp

@extends('layout.default')

@section('title', 'Control y Prevención de Mermas')

@push('pageStyles')
<link href="{{ $U('/css/daily_waste_control.css?v=', true) }}{{ daily_asset_version('/css/daily_waste_control.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_waste_control_center.css?v=', true) }}{{ daily_asset_version('/css/daily_waste_control_center.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_waste_normalize.css?v=', true) }}{{ daily_asset_version('/css/daily_waste_normalize.css') }}" rel="stylesheet">
<style>
	#page-content > .dairy-related { display: none !important; }
	#dailyTopKpisBar { display: none !important; }
</style>
@endpush

@section('content')
@include('components.loss_strip')
@include('components.alert_strip', ['alertCategories' => ['waste', 'security']])
<!-- ==========================================================================
     CABECERA DEL MÓDULO — CONTROL Y PREVENCIÓN DE MERMAS
     ========================================================================== -->
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-xl-8 col-lg-7 col-md-12 d-flex align-items-center">
		<!-- Icono distintivo rojo de mermas -->
		<div class="dairy-page-icon is-danger"><i class="fa-solid fa-trash-can"></i></div>
		<div>
			<h4 class="font-weight-bold text-dark mb-0 dairy-fs-xl" style="letter-spacing: -0.02em;">
				Cuadro de Control de Mermas
			</h4>
			<p class="text-muted mb-0 font-weight-400 dairy-fs-base">
				Cuadro de Control Ejecutivo: pérdidas, causales, investigaciones, recuperación financiera y planes de acción.
			</p>
		</div>
	</div>
	<div class="col-xl-4 col-lg-5 col-md-12 text-lg-right mt-2 mt-lg-0 dairy-page-actions">
		<button class="btn btn-primary font-weight-600 shadow-xs mr-1" 
				style="font-size: var(--dairy-fs-base); padding: 6.5px 14px; border-radius: var(--dairy-radius-sm); background-color: var(--dairy-primary); border-color: var(--dairy-primary);"
				onclick="$('#tab-registro-link').click();">
			<i class="fa-solid fa-plus-circle mr-1.5"></i>Registrar Merma
		</button>
		
		<div class="btn-group mr-1">
			<button class="btn btn-light bg-white border font-weight-500 text-dark dropdown-toggle shadow-xs" 
					data-toggle="dropdown" 
					style="font-size: var(--dairy-fs-base); padding: 6.5px 13px; border-radius: var(--dairy-radius-sm); border-color: var(--dairy-border);">
				<i class="fa-solid fa-bolt text-primary mr-1"></i>Acciones Rápidas
			</button>
			<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 dairy-radius-md dairy-fs-base">
				<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-conteos-link').click();">
					<i class="fa-solid fa-barcode text-info mr-2"></i>Iniciar Conteo Cíclico
				</a>
				<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-fefo-link').click();">
					<i class="fa-solid fa-snowflake text-primary mr-2"></i>Revisión de Cadena de Frío
				</a>
				<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-fefo-link').click();">
					<i class="fa-solid fa-lock text-danger mr-2"></i>Bloquear Lote en Riesgo
				</a>
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-recuperacion-link').click();">
					<i class="fa-solid fa-hand-holding-dollar text-success mr-2"></i>Gestionar Reclamo a Proveedor
				</a>
			</div>
		</div>

		<div class="btn-group">
			<button class="btn btn-light bg-white border font-weight-500 text-dark dropdown-toggle shadow-xs" 
					data-toggle="dropdown" 
					style="font-size: var(--dairy-fs-base); padding: 6.5px 13px; border-radius: var(--dairy-radius-sm); border-color: var(--dairy-border);">
				<i class="fa-solid fa-download text-muted mr-1"></i>Exportar
			</button>
			<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 dairy-radius-md dairy-fs-base">
				<a class="dropdown-item" href="javascript:void(0)" onclick="dairyWasteExport('xls');">
					<i class="fa-solid fa-file-excel text-success mr-2"></i>Exportar Casos (Excel)
				</a>
				<a class="dropdown-item" href="javascript:void(0)" onclick="dairyWasteExport('csv');">
					<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar Casos (CSV)
				</a>
				<a class="dropdown-item" href="javascript:void(0)" onclick="window.print();">
					<i class="fa-solid fa-file-pdf text-danger mr-2"></i>Informe Ejecutivo (PDF)
				</a>
				<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-auditoria-link').click();">
					<i class="fa-solid fa-clock-rotate-left text-muted mr-2"></i>Trazabilidad Completa
				</a>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     BARRA DE FILTROS GLOBALES — CONTROL Y PREVENCIÓN DE MERMAS
     ========================================================================== -->
<div class="card border-0 shadow-xs mb-3 bg-white dairy-bordered" style="border-radius: 10px;">
	<div class="card-body py-2 px-3">
		<form method="GET" action="{{ $U('/waste') }}" id="dailyWasteFilterForm" class="form-row align-items-center">
			<!-- Sucursal -->
			<div class="col-xl-2 col-lg-3 col-md-6 col-12 mb-1 mb-lg-0">
				<label class="small text-muted font-weight-700 text-uppercase mb-0 d-block" style="font-size: 0.68rem; letter-spacing: 0.04em;">Sucursal</label>
				<select name="branch_id" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px; font-size: 0.82rem;" onchange="this.form.submit()">
					<option value="" {{ empty($selectedBranchId) ? 'selected' : '' }}>Todas las sucursales</option>
					@if(isset($branches))
						@foreach($branches as $b)
							<option value="{{ $b->id }}" {{ ($selectedBranchId == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
						@endforeach
					@endif
				</select>
			</div>

			<!-- Departamento -->
			<div class="col-xl-2 col-lg-3 col-md-6 col-12 mb-1 mb-lg-0">
				<label class="small text-muted font-weight-700 text-uppercase mb-0 d-block" style="font-size: 0.68rem; letter-spacing: 0.04em;">Departamento</label>
				<select name="department_id" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px; font-size: 0.82rem;" onchange="this.form.submit()">
					<option value="">Todos los departamentos</option>
					<option value="perecederos" selected>Fruver y Carnes (Perecederos)</option>
					<option value="abarrotes">Abarrotes y Secos</option>
					<option value="lacteos">Lácteos y Fiambrería</option>
					<option value="panaderia">Panadería y Masas</option>
					<option value="bebidas">Bebidas y Licores</option>
				</select>
			</div>

			<!-- Selector de fechas -->
			<div class="col-xl-3 col-lg-3 col-md-6 col-12 mb-1 mb-lg-0">
				<label class="small text-muted font-weight-700 text-uppercase mb-0 d-block" style="font-size: 0.68rem; letter-spacing: 0.04em;">Rango de Fechas</label>
				<div class="input-group input-group-sm">
					<input type="date" name="start_date" class="form-control form-control-sm border font-weight-500" value="{{ $startDate ?? date('Y-m-01') }}" style="border-radius: 6px 0 0 6px; font-size: 0.8rem;">
					<div class="input-group-prepend input-group-append"><span class="input-group-text px-1 bg-light border-left-0 border-right-0" style="font-size: 0.72rem;">a</span></div>
					<input type="date" name="end_date" class="form-control form-control-sm border font-weight-500" value="{{ $endDate ?? date('Y-m-d') }}" style="border-radius: 0 6px 6px 0; font-size: 0.8rem;" onchange="this.form.submit()">
				</div>
			</div>

			<!-- Comparación de períodos -->
			<div class="col-xl-3 col-lg-3 col-md-6 col-12 mb-1 mb-lg-0">
				<label class="small text-muted font-weight-700 text-uppercase mb-0 d-block" style="font-size: 0.68rem; letter-spacing: 0.04em;">Comparación de Períodos</label>
				<select name="period_compare" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px; font-size: 0.82rem;" onchange="this.form.submit()">
					<option value="prev_year" selected>vs. Mismo período año anterior</option>
					<option value="prev_month">vs. Mes anterior</option>
					<option value="budget">vs. Presupuesto / Tolerancia</option>
					<option value="none">Sin comparación</option>
				</select>
			</div>

			<!-- Botón Aplicar / Reset -->
			<div class="col-xl-2 col-lg-12 col-md-12 col-12 d-flex align-items-end mt-2 mt-xl-0">
				<button type="submit" class="btn btn-sm btn-outline-secondary font-weight-600 mr-1 w-100" style="border-radius: 6px; height: 31px;">
					<i class="fa-solid fa-filter mr-1"></i>Filtrar
				</button>
				<a href="{{ $U('/waste') }}" class="btn btn-sm btn-light border text-muted px-2" style="border-radius: 6px; height: 31px;" title="Restablecer filtros">
					<i class="fa-solid fa-rotate-left"></i>
				</a>
			</div>
		</form>
	</div>
</div>

<!-- ==========================================================================
     SUB-NAVEGACIÓN EN 10 PESTAÑAS (PILLS STYLE)
     ========================================================================== -->
<!-- Centro de Control: 13 secciones (1–13) + utilidades. Desplazable en pantallas angostas. -->
<div class="dairy-subnav-container mb-3 dlx-subnav">
	<ul class="nav dairy-subnav-pills flex-nowrap w-100 mb-0" id="wasteModuleNav" role="tablist" data-dairy-scroll-x>
		<li class="nav-item">
			<a class="nav-link active dairy-fs-sm dairy-tab-link text-nowrap" id="tab-centro-control-link" data-toggle="pill" href="#tab-centro-control" role="tab"><span class="dlx-num">1</span>Cuadro de Control de Mermas</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-clasificacion-link" data-toggle="pill" href="#tab-clasificacion" role="tab"><span class="dlx-num">2</span>Operativa y comercial</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-ajustes-link" data-toggle="pill" href="#tab-ajustes" role="tab"><span class="dlx-num">3</span>Ajustes</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-averias-link" data-toggle="pill" href="#tab-averias" role="tab"><span class="dlx-num">4</span>Averías y roturas</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-anual-link" data-toggle="pill" href="#tab-anual" role="tab"><span class="dlx-num">5</span>Comparativo anual</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-causales-link" data-toggle="pill" href="#tab-causales" role="tab"><span class="dlx-num">6</span>Causales</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-conteos-link" data-toggle="pill" href="#tab-conteos" role="tab"><span class="dlx-num">7</span>Selectivos y sensibles</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-precios-link" data-toggle="pill" href="#tab-precios" role="tab"><span class="dlx-num">8</span>Precios y marcación</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-perecederos-link" data-toggle="pill" href="#tab-perecederos" role="tab"><span class="dlx-num">9</span>Fruver y carnes</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-fefo-link" data-toggle="pill" href="#tab-fefo" role="tab"><span class="dlx-num">10</span>Frío y vencimientos</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-robo-link" data-toggle="pill" href="#tab-robo" role="tab"><span class="dlx-num">11</span>Robo interno/externo</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-recuperacion-link" data-toggle="pill" href="#tab-recuperacion" role="tab"><span class="dlx-num">12</span>Impacto y recuperación</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-investigaciones-link" data-toggle="pill" href="#tab-investigaciones" role="tab"><span class="dlx-num">13</span>Investigación y planes</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-censo-link" data-toggle="pill" href="#tab-censo" role="tab"><span class="dlx-num">14</span>Estructura y tolerancia</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-errores-link" data-toggle="pill" href="#tab-errores" role="tab"><span class="dlx-num">15</span>Errores administrativos</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-controles-link" data-toggle="pill" href="#tab-controles" role="tab"><span class="dlx-num">16</span>Controles e impacto</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap font-weight-bold" id="tab-bakery-link" data-toggle="pill" href="#tab-bakery" role="tab" style="color: #b45309;"><span class="dlx-num" style="background:#f59e0b; color:#fff;"><i class="fa-solid fa-bread-slice"></i></span>Bakery & Panadería</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-prevencion-link" data-toggle="pill" href="#tab-prevencion" role="tab">Rebajas <i class="fa-solid fa-caret-down ml-1 dairy-fs-xs"></i></a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-registro-link" data-toggle="pill" href="#tab-registro" role="tab">Registrar merma</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap" id="tab-auditoria-link" data-toggle="pill" href="#tab-auditoria" role="tab">Auditoría</a>
		</li>
			<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap font-weight-bold" id="tab-damaged-goods-link" data-toggle="pill" href="#tab-damaged-goods" role="tab" style="color: #dc2626;">
				<span class="dlx-num" style="background:#dc2626; color:#fff;"><i class="fa-solid fa-camera"></i></span>Productos Dañados
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link dairy-fs-sm dairy-tab-link text-nowrap font-weight-bold" id="tab-smart-audits-link" data-toggle="pill" href="#tab-smart-audits" role="tab" style="color: #2563eb;">
				<span class="dlx-num" style="background:#2563eb; color:#fff;"><i class="fa-solid fa-magnifying-glass-chart"></i></span>Auditorías Inteligentes
			</a>
		</li>
</ul>
</div>

<!-- ==========================================================================
     INDICADORES SUPERIORES (5 TARJETAS HORIZONTALES DE ALTA FIDELIDAD)
     ========================================================================== -->
@php
	// Cifras de waste_records; sin registro = «Sin información» (no un valor de referencia), cero registrado = $0
	$LS = $lossSummary ?? [];
	$lsPct = fn($k) => isset($LS[$k]['pct_sales']) && $LS[$k]['pct_sales'] !== null ? number_format($LS[$k]['pct_sales'], 2) . '%' : 'Sin información';
	$lsVal = fn($k) => isset($LS[$k]['value']) && $LS[$k]['value'] !== null ? '$' . number_format($LS[$k]['value'], 0) : 'Sin información';
	$totPct = $lsPct('total');
	$totVal = $lsVal('total');

	$opPct = $lsPct('operativa');
	$opVal = $lsVal('operativa');

	$comPct = $lsPct('comercial');
	$comVal = $lsVal('comercial');

	$adjPct = $lsPct('ajuste');
	$adjVal = $lsVal('ajuste');

	$avePct = $lsPct('averia');
	$aveVal = $lsVal('averia');
@endphp
<div class="daily-top-kpis" id="dailyTopKpisBar" role="region" aria-label="Indicadores superiores de merma">
	<!-- 1. MERMA TOTAL -->
	<a href="#tab-centro-control" class="daily-kpi-card-h tone-red" onclick="$('#tab-centro-control-link').click();">
		<div class="daily-kpi-h-head">
			<span class="daily-kpi-h-title">Merma Total</span>
			<span class="daily-kpi-h-icon"><i class="fa-solid fa-chart-pie"></i></span>
		</div>
		<div class="daily-kpi-h-body">
			<div>
				<div class="daily-kpi-h-val text-danger">{{ $totPct }}</div>
				<div class="daily-kpi-h-money">{{ $totVal }}</div>
			</div>
			<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 18 Q 14 20, 22 13 T 42 16 T 62 6" stroke="#ef4444" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="6" r="2.5" fill="#ef4444"/></svg>
		</div>
		<div class="daily-kpi-h-footer">
			<span class="daily-kpi-h-delta up"><i class="fa-solid fa-arrow-trend-up"></i> +0.28 pp</span>
			<span>vs. año ant: <strong>-0.15 pp</strong></span>
		</div>
	</a>

	<!-- 2. MERMA OPERATIVA -->
	<a href="#tab-clasificacion" class="daily-kpi-card-h tone-green" onclick="$('#tab-clasificacion-link').click();">
		<div class="daily-kpi-h-head">
			<span class="daily-kpi-h-title">Merma Operativa</span>
			<span class="daily-kpi-h-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
		</div>
		<div class="daily-kpi-h-body">
			<div>
				<div class="daily-kpi-h-val" style="color: var(--daily-primary, #00C83B);">{{ $opPct }}</div>
				<div class="daily-kpi-h-money">{{ $opVal }}</div>
			</div>
			<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 8 Q 16 6, 24 14 T 44 12 T 62 20" stroke="#00C83B" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="20" r="2.5" fill="#00C83B"/></svg>
		</div>
		<div class="daily-kpi-h-footer">
			<span class="daily-kpi-h-delta down"><i class="fa-solid fa-arrow-trend-down"></i> -0.12 pp</span>
			<span>vs. año ant: <strong>-0.30 pp</strong></span>
		</div>
	</a>

	<!-- 3. MERMA COMERCIAL -->
	<a href="#tab-clasificacion" class="daily-kpi-card-h tone-yellow" onclick="$('#tab-clasificacion-link').click();">
		<div class="daily-kpi-h-head">
			<span class="daily-kpi-h-title">Merma Comercial</span>
			<span class="daily-kpi-h-icon"><i class="fa-solid fa-tag"></i></span>
		</div>
		<div class="daily-kpi-h-body">
			<div>
				<div class="daily-kpi-h-val text-warning">{{ $comPct }}</div>
				<div class="daily-kpi-h-money">{{ $comVal }}</div>
			</div>
			<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 16 Q 16 18, 26 11 T 46 14 T 62 7" stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="7" r="2.5" fill="#f59e0b"/></svg>
		</div>
		<div class="daily-kpi-h-footer">
			<span class="daily-kpi-h-delta up"><i class="fa-solid fa-arrow-trend-up"></i> +0.35 pp</span>
			<span>vs. año ant: <strong>+0.10 pp</strong></span>
		</div>
	</a>

	<!-- 4. AJUSTES -->
	<a href="#tab-ajustes" class="daily-kpi-card-h tone-neutral" onclick="$('#tab-ajustes-link').click();">
		<div class="daily-kpi-h-head">
			<span class="daily-kpi-h-title">Ajustes</span>
			<span class="daily-kpi-h-icon"><i class="fa-solid fa-sliders"></i></span>
		</div>
		<div class="daily-kpi-h-body">
			<div>
				<div class="daily-kpi-h-val text-secondary">{{ $adjPct }}</div>
				<div class="daily-kpi-h-money">{{ $adjVal }}</div>
			</div>
			<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 11 Q 16 9, 28 15 T 48 13 T 62 17" stroke="#64748b" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="17" r="2.5" fill="#64748b"/></svg>
		</div>
		<div class="daily-kpi-h-footer">
			<span class="daily-kpi-h-delta down"><i class="fa-solid fa-arrow-trend-down"></i> -0.05 pp</span>
			<span>vs. año ant: <strong>-0.02 pp</strong></span>
		</div>
	</a>

	<!-- 5. AVERÍAS Y ROTURAS -->
	<a href="#tab-averias" class="daily-kpi-card-h tone-purple" onclick="$('#tab-averias-link').click();">
		<div class="daily-kpi-h-head">
			<span class="daily-kpi-h-title">Averías y Roturas</span>
			<span class="daily-kpi-h-icon"><i class="fa-solid fa-heart-crack"></i></span>
		</div>
		<div class="daily-kpi-h-body">
			<div>
				<div class="daily-kpi-h-val text-purple" style="color: #8b5cf6;">{{ $avePct }}</div>
				<div class="daily-kpi-h-money">{{ $aveVal }}</div>
			</div>
			<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 14 Q 18 16, 30 12 T 48 10 T 62 8" stroke="#8b5cf6" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="8" r="2.5" fill="#8b5cf6"/></svg>
		</div>
		<div class="daily-kpi-h-footer">
			<span class="daily-kpi-h-delta up"><i class="fa-solid fa-arrow-trend-up"></i> +0.01 pp</span>
			<span>vs. año ant: <strong>0.00 pp</strong></span>
		</div>
	</a>
</div>

<!-- ==========================================================================
     CONTENEDOR DE PESTAÑAS (TAB CONTENT)
     ========================================================================== -->
<div class="tab-content" id="wasteModuleTabContent">

	<!-- ======================================================================
	     PESTAÑA 1: CENTRO DE CONTROL (RÉPLICA EXACTA DE LA FOTO)
	     ====================================================================== -->
	<div class="tab-pane fade show active" id="tab-centro-control" role="tabpanel">

		<!-- ======================================================================
		     CENTRO EJECUTIVO DE CONTROL DE MERMAS — RÉPLICA EXACTA DE LA FOTO
		     ====================================================================== -->
		<div class="dw-executive-wrapper">
			<!-- HEADER EJECUTIVO DEL CENTRO DE CONTROL -->
			<div class="dw-exec-header">
				<div class="dw-exec-title-box">
					<h2>Control de Mermas</h2>
					<p>Resumen ejecutivo · Pérdidas, causas y oportunidades</p>
				</div>
				<div class="dw-exec-controls">
					<!-- Selector Fecha -->
					<div class="dw-filter-select-group">
						<label><i class="fa-regular fa-calendar mr-1"></i></label>
						<select id="dw-filter-date" onchange="dwApplyFilters()">
							@php
	$dd = fn($t) => ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][intval(date('N', $t))] . ', ' . date('j', $t) . ' ' . ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][intval(date('n', $t))] . ' ' . date('Y', $t);
@endphp
							<option value="{{ date('Y-m-d') }}" selected>Mes en curso hasta hoy ({{ $dd(time()) }})</option>
							<option value="{{ date('Y-m-d', strtotime('-1 day')) }}">Mes en curso hasta ayer</option>
							<option value="{{ date('Y-m-t', strtotime('first day of last month')) }}">Mes anterior completo</option>
						</select>
					</div>

					<!-- Selector Sucursal -->
					<div class="dw-filter-select-group">
						<label>Sucursal</label>
						<select id="dw-filter-branch" onchange="dwApplyFilters()">
							<option value="" selected>Todas</option>
							@foreach(($branches ?? []) as $b)
							<option value="{{ $b->id }}">{{ $b->name }}</option>
							@endforeach
						</select>
					</div>

					<!-- Selector Departamento -->
					<div class="dw-filter-select-group">
						<label>Departamento</label>
						<select id="dw-filter-dept" onchange="dwApplyFilters()">
							<option value="" selected>Todos</option>
							<option value="fruver">Frutas y verduras</option>
							<option value="bakery">Bakery</option>
							<option value="dairy">Dairy</option>
							<option value="carnes">Carnes</option>
							<option value="preparadas">Comidas preparadas</option>
							<option value="electronica">Electrónica</option>
							<option value="otros">Otros</option>
						</select>
					</div>

					<!-- Selector Causal -->
					<div class="dw-filter-select-group">
						<label>Causal</label>
						<select id="dw-filter-causal" onchange="dwApplyFilters()">
							<option value="" selected>Todas</option>
							<option value="vencimiento">Vencimiento</option>
							<option value="dano_externo">Daño externo</option>
							<option value="rotura">Rotura</option>
							<option value="cadena_frio">Cadena de frío</option>
							<option value="sobreproduccion">Sobreproducción</option>
							<option value="robo_interno">Robo interno</option>
							<option value="otros">Otros</option>
						</select>
					</div>

					<!-- Estado Live -->
					<div class="dw-live-badge">
						<span class="dw-live-dot"></span>
						<span id="dw-last-updated-text">Última actualización {{ $wasteExec['last_updated'] ?? '' }}</span>
					</div>

					<!-- Botón Recargar -->
					<button class="dw-refresh-btn" title="Actualizar datos" onclick="dwApplyFilters()">
						<i class="fa-solid fa-rotate"></i>
					</button>
				</div>
			</div>

			<!-- ==================================================================
			     ZONA 1: OCHO TARJETAS KPI PRINCIPALES
			     ================================================================== -->
			<div class="dw-kpis-grid">
				<!-- KPI 1: Merma total -->
				<div class="dw-kpi-card" id="kpi-total-waste" title="Valor monetario total de pérdidas registradas en el período">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Merma total</span>
						<div class="dw-kpi-icon is-red"><i class="fa-solid fa-trash-can"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-total-waste">{{ $wasteExec['kpis']['total_waste']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad" id="dw-delta-total-waste">{{ $wasteExec['kpis']['total_waste']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['total_waste']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-total-waste" d="{{ $wasteExec['kpis']['total_waste']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['total_waste']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 2: Merma / ventas -->
				<div class="dw-kpi-card" id="kpi-waste-sales" title="Porcentaje de merma respecto a ventas netas (Meta: ≤ {{ number_format($wasteExec['target_pct'] ?? 0, 2) }}%)">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Merma / ventas</span>
						<div class="dw-kpi-icon is-red"><i class="fa-solid fa-percent"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-waste-sales">{{ $wasteExec['kpis']['waste_sales']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad" id="dw-delta-waste-sales">{{ $wasteExec['kpis']['waste_sales']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['waste_sales']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-waste-sales" d="{{ $wasteExec['kpis']['waste_sales']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['waste_sales']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 3: Merma operativa -->
				<div class="dw-kpi-card" id="kpi-operative" title="Pérdidas operativas en tienda y trastienda">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Merma operativa</span>
						<div class="dw-kpi-icon is-red"><i class="fa-solid fa-box"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-operative">{{ $wasteExec['kpis']['operative']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad" id="dw-delta-operative">{{ $wasteExec['kpis']['operative']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['operative']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-operative" d="{{ $wasteExec['kpis']['operative']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['operative']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 4: Merma comercial -->
				<div class="dw-kpi-card" id="kpi-commercial" title="Rebajas por vencimiento, liquidaciones comerciales y mermas de precio">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Merma comercial</span>
						<div class="dw-kpi-icon is-amber"><i class="fa-solid fa-cart-shopping"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-commercial">{{ $wasteExec['kpis']['commercial']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-good" id="dw-delta-commercial">{{ $wasteExec['kpis']['commercial']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['commercial']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-commercial" d="{{ $wasteExec['kpis']['commercial']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['commercial']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 5: Averías -->
				<div class="dw-kpi-card" id="kpi-breakage" title="Roturas por manipulación, caída de carga y daños físicos">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Averías</span>
						<div class="dw-kpi-icon is-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-breakage">{{ $wasteExec['kpis']['breakage']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad" id="dw-delta-breakage">{{ $wasteExec['kpis']['breakage']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['breakage']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-breakage" d="{{ $wasteExec['kpis']['breakage']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['breakage']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 6: Ajustes -->
				<div class="dw-kpi-card" id="kpi-adjustments" title="Ajustes de inventario netos resultantes de conteos cíclicos">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Ajustes</span>
						<div class="dw-kpi-icon is-green"><i class="fa-solid fa-sliders"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-adjustments">{{ $wasteExec['kpis']['adjustments']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-good" id="dw-delta-adjustments">{{ $wasteExec['kpis']['adjustments']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['adjustments']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-adjustments" d="{{ $wasteExec['kpis']['adjustments']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['adjustments']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 7: Recuperación financiera -->
				<div class="dw-kpi-card" id="kpi-recovery" title="Importe efectivamente recuperado a través de reclamos y notas de crédito">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Recuperación financiera</span>
						<div class="dw-kpi-icon is-green"><i class="fa-solid fa-rotate-left"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-recovery">{{ $wasteExec['kpis']['recovery']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-good" id="dw-delta-recovery">{{ $wasteExec['kpis']['recovery']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['recovery']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-recovery" d="{{ $wasteExec['kpis']['recovery']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['recovery']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 8: Pérdida evitable -->
				<div class="dw-kpi-card" id="kpi-avoidable" title="Pérdidas clasificadas como 100% prevenibles mediante protocolos estándar">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Pérdida evitable</span>
						<div class="dw-kpi-icon is-red"><i class="fa-solid fa-circle-xmark"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-avoidable">{{ $wasteExec['kpis']['avoidable']['value'] }}</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad" id="dw-delta-avoidable">{{ $wasteExec['kpis']['avoidable']['delta'] }}</span>
						<span class="dw-kpi-subtext">{{ $wasteExec['kpis']['avoidable']['subtext'] }}</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path id="dw-spark-avoidable" d="{{ $wasteExec['kpis']['avoidable']['sparkline'] }}" fill="none" stroke="{{ $wasteExec['kpis']['avoidable']['spark_color'] }}" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 9: Casos e Investigaciones -->
				<div class="dw-kpi-card" id="kpi-pending-cases" title="Expedientes de merma en proceso de investigación y peritaje">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Casos pendientes</span>
						<div class="dw-kpi-icon is-amber"><i class="fa-solid fa-magnifying-glass"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-cases">{{ $openIncidentsCount ?? 8 }} casos</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-bad"><i class="fa-solid fa-clock"></i> 3 críticos</span>
						<span class="dw-kpi-subtext">Investigación activa</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path d="M 0 16 Q 30 18 60 12 T 120 8" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>

				<!-- KPI 10: Cumplimiento de Planes -->
				<div class="dw-kpi-card" id="kpi-action-plans" title="Porcentaje de planes de acción preventiva y correctiva completados">
					<div class="dw-kpi-top">
						<span class="dw-kpi-label">Planes de acción</span>
						<div class="dw-kpi-icon is-green"><i class="fa-solid fa-clipboard-check"></i></div>
					</div>
					<div class="dw-kpi-val" id="dw-val-plans">94.2%</div>
					<div class="dw-kpi-delta-row">
						<span class="dw-delta-tag is-good"><i class="fa-solid fa-check"></i> 16/17</span>
						<span class="dw-kpi-subtext">Cerrados a tiempo</span>
					</div>
					<div class="dw-sparkline-box">
						<svg class="dw-sparkline-svg" viewBox="0 0 120 24" preserveAspectRatio="none">
							<path d="M 0 20 Q 30 14 60 8 T 120 4" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</div>
				</div>
			</div>

			<!-- ==================================================================
			     ZONA 2: 3 GRÁFICOS PRINCIPALES DE ANÁLISIS
			     ================================================================== -->
			<div class="dw-section-grid dw-grid-3-col-a">
				<!-- Gráfico 1: Evolución de mermas vs. ventas -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Evolución de mermas vs. ventas</h3>
						<div class="dw-card-tools">
							<select class="dw-select-mini" id="dw-evolution-mode" onchange="dwToggleEvolutionUnit(this.value)">
								<option value="pct" selected>Merma / ventas</option>
								<option value="money">Valor monetario ($)</option>
								<option value="per1k">Por cada $1,000</option>
							</select>
						</div>
					</div>
					<div class="dw-card-body">
						<!-- Subcontroles y leyenda inline -->
						<div class="dw-evolution-subcontrols">
							<div class="dw-pill-tabs" id="dw-unit-pills">
								<button class="dw-pill-btn" onclick="dwSetEvolutionUnit('money')">$</button>
								<button class="dw-pill-btn active" onclick="dwSetEvolutionUnit('pct')">%</button>
								<button class="dw-pill-btn" onclick="dwSetEvolutionUnit('per1k')">Por cada $1,000</button>
							</div>
							<div class="dw-chart-legend-inline">
								<span class="dw-leg-item"><span class="dw-leg-dot" style="background: #EF4444;"></span>Merma {{ date('Y') }}</span>
								<span class="dw-leg-item"><span class="dw-leg-dot" style="background: #94A3B8;"></span>Merma {{ date('Y') - 1 }}</span>
								<span class="dw-leg-item"><span class="dw-leg-dash"></span>Objetivo</span>
							</div>
						</div>

						<div class="dw-evolution-content">
							<div class="dw-evolution-chart-box">
								<canvas id="dwEvolutionChart"></canvas>
							</div>
							<div class="dw-evolution-side-panel">
								<div class="dw-side-kpi-block">
									<div class="dw-side-kpi-title">{{ $wasteExec['evolution']['current_month']['label'] }}</div>
									<div class="dw-side-kpi-val" id="dw-side-curr-val">{{ $wasteExec['evolution']['current_month']['value'] }}</div>
									<div class="dw-side-kpi-delta text-danger" id="dw-side-curr-delta">{{ $wasteExec['evolution']['current_month']['delta'] }}</div>
									<div class="dw-side-kpi-sub">{{ $wasteExec['evolution']['current_month']['subtext'] }}</div>
								</div>
								<div class="dw-side-kpi-block">
									<div class="dw-side-kpi-title">{{ $wasteExec['evolution']['closing_projection']['label'] }}</div>
									<div class="dw-side-kpi-val" id="dw-side-proj-val">{{ $wasteExec['evolution']['closing_projection']['value'] }}</div>
									<div class="dw-side-kpi-delta text-success" id="dw-side-proj-delta">{{ $wasteExec['evolution']['closing_projection']['delta'] }}</div>
									<div class="dw-side-kpi-sub">{{ $wasteExec['evolution']['closing_projection']['subtext'] }}</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Gráfico 2: Distribución de mermas por tipo -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Distribución de mermas por tipo</h3>
						<div class="dw-card-tools">
							<select class="dw-select-mini" id="dw-donut-mode" onchange="dwToggleDonutUnit(this.value)">
								<option value="money" selected>Valor ($)</option>
								<option value="pct">Porcentaje (%)</option>
							</select>
						</div>
					</div>
					<div class="dw-card-body">
						<div class="dw-donut-container">
							<div class="dw-donut-chart-box">
								<canvas id="dwTypeDonutChart"></canvas>
								<div class="dw-donut-center-text">
									<div class="dw-donut-center-val" id="dw-donut-center-total">{{ $wasteExec['donut_types']['total'] }}</div>
									<div class="dw-donut-center-sub">100%</div>
								</div>
							</div>
							<div class="dw-donut-legend">
								@foreach($wasteExec['donut_types']['items'] as $item)
								<div class="dw-donut-legend-row">
									<div class="dw-donut-legend-name">
										<span class="dw-leg-dot" style="background: {{ $item['color'] }};"></span>
										<span>{{ $item['label'] }}</span>
									</div>
									<div class="dw-donut-legend-vals">
										<span>${{ number_format($item['amount'], 0) }}</span>
										<span class="text-muted">{{ number_format($item['share'], 1) }}%</span>
									</div>
								</div>
								@endforeach
							</div>
						</div>
					</div>
				</div>

				<!-- Gráfico 3: Merma por departamento -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Merma por departamento</h3>
						<div class="dw-card-tools">
							<select class="dw-select-mini" id="dw-dept-sort" onchange="dwSortDepartmentBars(this.value)">
								<option value="pct" selected>Merma / ventas</option>
								<option value="val">Mayor pérdida ($)</option>
								<option value="dev">Mayor desviación</option>
							</select>
						</div>
					</div>
					<div class="dw-card-body">
						<div class="dw-dept-bars-box" id="dw-dept-bars-container">
							<div class="dw-dept-target-line" title="Meta: {{ number_format($wasteExec['target_pct'] ?? 0, 2) }}%"></div>
							@foreach($wasteExec['departments_bar'] as $dept)
							@php $barWidth = min(100, round(($dept['pct'] / 3.5) * 100)); @endphp
							<div class="dw-dept-bar-row" data-pct="{{ $dept['pct'] }}" data-amount="{{ $dept['amount'] }}" title="{{ $dept['name'] }}: {{ $dept['pct'] }}% (${{ number_format($dept['amount'], 0) }})">
								<span class="dw-dept-bar-label">{{ $dept['name'] }}</span>
								<div class="dw-dept-bar-track">
									<div class="dw-dept-bar-fill" style="width: {{ $barWidth }}%; background-color: {{ $dept['color'] }};"></div>
								</div>
								<span class="dw-dept-bar-val">{{ number_format($dept['pct'], 1) }}%</span>
							</div>
							@endforeach
						</div>
						<div class="d-flex justify-content-end mt-2">
							<span class="badge badge-light border text-muted" style="font-size: 0.65rem;">Meta: {{ number_format($wasteExec['target_pct'] ?? 0, 2) }}%</span>
						</div>
					</div>
				</div>
			</div>

			<!-- ==================================================================
			     ZONA 3: PRODUCTOS, CAUSAS Y HORARIOS
			     ================================================================== -->
			<div class="dw-section-grid dw-grid-3-col-b">
				<!-- Gráfico 5: Top 10 productos con mayor merma -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Top 10 productos con mayor merma</h3>
						<div class="dw-card-tools">
							<select class="dw-select-mini" id="dw-top-prod-sort" onchange="dwSortTopProducts(this.value)">
								<option value="val" selected>Por valor ($)</option>
								<option value="units">Por unidades</option>
								<option value="pct">Por % Ventas</option>
							</select>
						</div>
					</div>
					<div class="dw-card-body p-0" style="overflow-x: auto;">
						<table class="dw-products-table" id="dwTopProductsTable">
							<thead>
								<tr>
									<th style="width: 20px;">#</th>
									<th>Producto</th>
									<th>Departamento</th>
									<th class="text-right">Valor</th>
									<th class="text-right">Unidades</th>
									<th class="text-right">% Ventas</th>
								</tr>
							</thead>
							<tbody>
								@foreach($wasteExec['top_products'] as $prod)
								<tr data-val="{{ intval(str_replace(['$', ','], '', $prod['amount'])) }}" data-units="{{ $prod['units'] }}" data-pct="{{ floatval($prod['pct_sales']) }}">
									<td class="font-weight-700 text-muted">{{ $prod['rank'] }}</td>
									<td>
										<div class="dw-prod-name-box">
											<span class="dw-prod-icon">{{ $prod['icon'] }}</span>
											<span>{{ $prod['name'] }}</span>
										</div>
									</td>
									<td class="text-muted">{{ $prod['dept'] }}</td>
									<td class="text-right font-weight-700 text-dark">{{ $prod['amount'] }}</td>
									<td class="text-right text-secondary">{{ $prod['units'] }}</td>
									<td class="text-right dw-prod-pct-bad">{{ $prod['pct_sales'] }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>

				<!-- Gráfico 6: Causas principales de merma -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Causas principales de merma</h3>
						<div class="dw-card-tools">
							<select class="dw-select-mini">
								<option value="val" selected>Valor ($)</option>
								<option value="pct">Porcentaje (%)</option>
								<option value="pareto">Análisis Pareto 80/20</option>
							</select>
						</div>
					</div>
					<div class="dw-card-body">
						<div class="dw-causes-bars-box">
							@foreach($wasteExec['causes'] as $cause)
							@php $cWidth = min(100, round(($cause['amount'] / 6500) * 100)); @endphp
							<div class="dw-cause-row" title="{{ $cause['name'] }}: ${{ number_format($cause['amount'], 0) }} ({{ $cause['pct'] }}%)">
								<span class="dw-cause-name">{{ $cause['name'] }}</span>
								<div class="dw-cause-track">
									<div class="dw-cause-fill" style="width: {{ $cWidth }}%; background-color: {{ $cause['color'] }};"></div>
								</div>
								<div class="dw-cause-val-box">
									<span>${{ number_format($cause['amount'], 0) }}</span>
									<span class="text-muted">{{ number_format($cause['pct'], 1) }}%</span>
								</div>
							</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>

			<!-- ==================================================================
			     ZONA 4 & 5: SUCURSALES, RECUPERACIÓN Y DECISIONES PRIORITARIAS
			     ================================================================== -->
			<div class="dw-section-grid dw-grid-3-col-c">
				<!-- Gráfico 7: Tendencia por sucursal -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Tendencia por sucursal</h3>
						<div class="dw-card-tools">
							<div class="dw-pill-tabs">
								<button class="dw-pill-btn active" onclick="dwFilterBranchTab('pct', this)">Merma / ventas</button>
								<button class="dw-pill-btn" onclick="dwFilterBranchTab('val', this)">Valor ($)</button>
								<button class="dw-pill-btn" onclick="dwFilterBranchTab('var', this)">Variación</button>
							</div>
						</div>
					</div>
					<div class="dw-card-body p-0" style="overflow-x: auto;">
						<table class="dw-branch-table" id="dwBranchTable">
							<thead>
								<tr>
									<th>Sucursal</th>
									<th class="text-right">Ventas</th>
									<th class="text-right">Merma</th>
									<th class="text-right">Merma %</th>
									<th class="text-right">Δ vs. anterior</th>
									<th class="text-center" style="width: 40px;">Estado</th>
								</tr>
							</thead>
							<tbody>
								@foreach($wasteExec['branches_table'] as $br)
								@php 
									$isDanger = $br['status'] === 'danger';
									$isWarning = $br['status'] === 'warning';
									$deltaColor = strpos($br['delta'], '↑') !== false ? 'text-danger' : 'text-success';
									$pctColor = $isDanger ? 'text-danger font-weight-700' : ($isWarning ? 'text-warning font-weight-700' : 'text-success font-weight-700');
								@endphp
								<tr>
									<td class="dw-branch-name">{{ $br['name'] }}</td>
									<td class="text-right text-muted">{{ $br['sales'] }}</td>
									<td class="text-right font-weight-600 text-dark">{{ $br['waste'] }}</td>
									<td class="text-right {{ $pctColor }}">{{ $br['pct'] }}</td>
									<td class="text-right font-weight-600 {{ $deltaColor }}">{{ $br['delta'] }}</td>
									<td class="text-center">{{ $br['dot'] }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>

				<!-- Gráfico 8: Recuperación financiera -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Recuperación financiera</h3>
						<div class="dw-card-tools">
							<div class="dw-pill-tabs">
								<button class="dw-pill-btn active">Recuperado</button>
								<button class="dw-pill-btn">En proceso</button>
							</div>
						</div>
					</div>
					<div class="dw-card-body">
						<div class="dw-recovery-layout">
							<!-- Dinero Recuperado -->
							<div class="dw-rec-stat-box">
								<div class="dw-rec-big-val" id="dw-rec-val">{{ $wasteExec['recovery']['recovered_amount'] }}</div>
								<div class="dw-rec-label">Recuperado</div>
								<div class="dw-rec-sub text-success font-weight-700">{{ $wasteExec['recovery']['delta_pct'] }}</div>
								<div class="dw-rec-sub">vs. período anterior</div>
							</div>

							<!-- Reclamos -->
							<div class="dw-rec-stat-box">
								<div class="dw-rec-big-val">{{ $wasteExec['recovery']['claims_count'] }}</div>
								<div class="dw-rec-label">Reclamos</div>
								<div class="dw-rec-sub font-weight-600 text-secondary">{{ $wasteExec['recovery']['claims_closed'] }} cerrados · {{ $wasteExec['recovery']['claims_in_process'] }} en proceso</div>
							</div>

							<!-- Semicircular Gauge -->
							<div class="dw-rec-gauge-container">
								<div class="dw-gauge-canvas-box">
									<canvas id="dwRecoveryGaugeChart"></canvas>
									<div class="dw-gauge-center-text">
										<div class="dw-gauge-val" id="dw-gauge-center-pct">{{ $wasteExec['recovery']['recovery_rate'] }}%</div>
									</div>
								</div>
								<div class="dw-gauge-label">
									<div>Recuperación sobre potencial</div>
									<div class="text-muted" style="font-size: 0.6rem;">Meta: {{ $wasteExec['recovery']['target_rate'] }}%</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Gráfico 9: Decisiones prioritarias -->
				<div class="dw-card">
					<div class="dw-card-header">
						<h3 class="dw-card-title">Decisiones prioritarias</h3>
						<div class="dw-card-tools">
							<a href="{{ $U('/alerts') }}" class="small font-weight-700 text-primary">Ver todas</a>
						</div>
					</div>
					<div class="dw-card-body">
						<div class="dw-priority-alerts">
							@foreach($wasteExec['priority_decisions'] as $dec)
							<a href="{{ $U($dec['href']) }}" class="dw-priority-card is-{{ $dec['severity'] }}">
								<div class="dw-priority-left">
									<div class="dw-priority-icon">
										<i class="fa-solid {{ $dec['icon'] }}"></i>
									</div>
									<div class="dw-priority-texts">
										<span class="dw-priority-title">{{ $dec['title'] }}</span>
										<span class="dw-priority-desc">{{ $dec['description'] }}</span>
									</div>
								</div>
								<span class="dw-priority-badge">{{ $dec['badge_text'] }} <i class="fa-solid fa-chevron-right ml-1" style="font-size: 0.55rem;"></i></span>
							</a>
							@endforeach
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Acceso al Detalle y Auditoría Completa de Casos -->
		<div class="dw-audit-drawer-toggle mb-3" onclick="$('#waste-legacy-audit-drawer').slideToggle(200); $(this).find('i.toggle-arrow').toggleClass('fa-chevron-down fa-chevron-up');">
			<span><i class="fa-solid fa-table-list mr-2 text-primary"></i><strong>Detalle Completo de Registros y Casos de Auditoría</strong> ({{ count($wasteRecords) }} casos registrados en sistema)</span>
			<span><span class="badge badge-light border mr-2 font-weight-500">Auditoría & Trazabilidad</span><i class="fa-solid fa-chevron-down toggle-arrow"></i></span>
		</div>
		<div id="waste-legacy-audit-drawer" style="display: none;">
		<!-- FILA 5: TABLA DE REGISTRO Y SEGUIMIENTO DE CASOS (17 COLUMNAS RÉPLICA EXACTA) -->
		<div class="card border-0 shadow-xs bg-white mb-4 dairy-bordered">
			<!-- Barra de Cabecera y Filtros en Línea -->
			<div class="card-header bg-white py-2.5 px-3 border-0">
				<div class="d-flex flex-wrap align-items-center justify-content-between">
					<div class="d-flex align-items-center mr-3 mb-2 mb-xl-0">
						<i class="fa-solid fa-file-lines text-danger mr-2 dairy-fs-lg"></i>
						<span class="font-weight-700 text-dark dairy-fs-md">Registro y Seguimiento de Casos de Merma</span>
					</div>

					<!-- Filtros alineados a la derecha -->
					<div class="d-flex flex-wrap align-items-center">
						<select id="filterBranchSelect" class="form-control form-control-sm border bg-white mr-1.5 mb-1 mb-md-0 w-auto" style="height: 32px;">
							<option value="">Todas las sucursales</option>
							@foreach(($branches ?? []) as $b)
							<option value="{{ $b->name }}">{{ $b->name }}</option>
							@endforeach
						</select>

						<select id="filterDeptSelect" class="form-control form-control-sm border bg-white mr-1.5 mb-1 mb-md-0 w-auto" style="height: 32px;">
							<option value="">Todos los departamentos</option>
							<option value="Lácteos">Lácteos</option>
							<option value="Quesos y Embutidos">Quesos y Embutidos</option>
							<option value="Huevos">Huevos</option>
						</select>

						<select id="filterCausalSelect" class="form-control form-control-sm border bg-white mr-1.5 mb-1 mb-md-0 w-auto" style="height: 32px;">
							<option value="">Todas las causales</option>
							<option value="Ruptura de cadena de frío">Ruptura de cadena de frío</option>
							<option value="Vencimiento">Vencimiento</option>
							<option value="Producto dañado">Producto dañado</option>
							<option value="Rotura">Rotura</option>
						</select>

						<select id="filterStatusSelect" class="form-control form-control-sm border bg-white mr-1.5 mb-1 mb-md-0 w-auto" style="height: 32px;">
							<option value="">Todos los estados</option>
							<option value="En investigación">En investigación</option>
							<option value="Aprobado">Aprobado</option>
							<option value="Ejecutado">Ejecutado</option>
							<option value="Cerrado">Cerrado</option>
							<option value="En revisión">En revisión</option>
						</select>

						<div class="input-group input-group-sm mr-1.5 mb-1 mb-md-0" style="width: 220px;">
							<div class="input-group-prepend">
								<span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-magnifying-glass text-muted dairy-fs-xs"></i></span>
							</div>
							<input type="text" id="wasteCaseSearchInput" class="form-control border-left-0" placeholder="Buscar casos, productos, lotes..." style="height: 32px;">
						</div>

						<button class="btn btn-sm btn-primary font-weight-600 shadow-xs">
							<i class="fa-solid fa-filter mr-1"></i>Filtros
						</button>
					</div>
				</div>
			</div>

			<!-- Tabla de 17 Columnas con Estilo Exacto -->
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0 dairy-fs-xs text-nowrap" id="exactPhotoCasesTable" style="width: 100%;">
						<thead class="dairy-bg-base" style="border-top: 1px solid var(--dairy-border-subtle); border-bottom: 1.5px solid var(--dairy-border);">
							<tr>
								<th class="py-2 px-3 font-weight-700 text-muted">ID CASO</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">FECHA</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">SUCURSAL</th>
								<th class="py-2 px-3 font-weight-700 text-muted">PRODUCTO</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">SKU</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">LOTE</th>
								<th class="py-2 px-2 text-right font-weight-700 text-muted">CANTIDAD</th>
								<th class="py-2 px-2.5 text-right font-weight-700 text-muted">COSTO UNIT.</th>
								<th class="py-2 px-2.5 text-right font-weight-700 text-danger">PÉRDIDA ($)</th>
								<th class="py-2 px-3 font-weight-700 text-muted">CAUSAL</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">CATEGORÍA</th>
								<th class="py-2 px-2.5 text-center font-weight-700 text-muted">ESTADO</th>
								<th class="py-2 px-2 text-center font-weight-700 text-muted">PRIORIDAD</th>
								<th class="py-2 px-2.5 font-weight-700 text-muted">RESPONSABLE</th>
								<th class="py-2 px-2.5 text-center font-weight-700 text-muted">APROBACIÓN</th>
								<th class="py-2 px-2.5 text-center font-weight-700 text-muted">EVIDENCIA</th>
								<th class="py-2 px-3 text-center font-weight-700 text-muted">ACCIONES</th>
							</tr>
						</thead>
						<tbody>
							@foreach($wasteRecords as $idx => $case)
							@php
								$dtFormatted = date('d/m/Y H:i', strtotime($case->created_timestamp));
								$statusBadgeClass = 'dairy-badge-closed';
								$statusLabel = 'Cerrado';
								if ($case->workflow_status === 'investigating') {
									$statusBadgeClass = 'dairy-badge-investigating';
									$statusLabel = 'En investigación';
								} elseif ($case->workflow_status === 'approved') {
									$statusBadgeClass = 'dairy-badge-approved';
									$statusLabel = 'Aprobado';
								} elseif ($case->workflow_status === 'executed') {
									$statusBadgeClass = 'dairy-badge-executed';
									$statusLabel = 'Ejecutado';
								} elseif ($case->workflow_status === 'reviewing' || $case->status === 'pending_approval') {
									$statusBadgeClass = 'dairy-badge-reviewing';
									$statusLabel = 'En revisión';
								}

								$priorityBadgeClass = 'dairy-badge-priority-media';
								$priorityLabel = 'Media';
								if ($case->total_cost >= 15.0 || strpos($case->reason_name, 'cadena de frío') !== false) {
									$priorityBadgeClass = 'dairy-badge-priority-alta';
									$priorityLabel = 'Alta';
								} elseif ($case->total_cost <= 10.0) {
									$priorityBadgeClass = 'dairy-badge-priority-baja';
									$priorityLabel = 'Baja';
								}

								$reporterName = 'Carlos M.';
								if (!empty($case->reported_by_username)) {
									if ($case->reported_by_username === 'cmartinez') $reporterName = 'Carlos M.';
									elseif ($case->reported_by_username === 'aperez') $reporterName = 'Ana P.';
									elseif ($case->reported_by_username === 'lprado') $reporterName = 'Luis P.';
									elseif ($case->reported_by_username === 'msilva') $reporterName = 'María S.';
									elseif ($case->reported_by_username === 'jlopez') $reporterName = 'Jorge L.';
									elseif ($case->reported_by_username === 'rgiovine') $reporterName = 'Renzo G.';
								}

								$isApproved = ($case->status === 'approved');
							@endphp
							<tr class="case-data-row" 
								data-idx="{{ $idx }}" 
								data-branch="{{ $case->branch_name }}" 
								data-causal="{{ $case->reason_name }}" 
								data-status="{{ $statusLabel }}"
								style="{{ $idx >= 5 ? 'display: none;' : '' }}">
								<!-- 1. ID CASO -->
								<td class="py-2.5 px-3 font-weight-600 text-dark font-mono">
									<a href="javascript:void(0)" onclick="openCaseModal({{ $case->id }});" class="text-dark discrete-link">
										{{ $case->case_number }}
									</a>
								</td>

								<!-- 2. FECHA -->
								<td class="py-2.5 px-2.5 text-muted">{{ $dtFormatted }}</td>

								<!-- 3. SUCURSAL -->
								<td class="py-2.5 px-2.5 text-dark">{{ $case->branch_name }}</td>

								<!-- 4. PRODUCTO -->
								<td class="py-2.5 px-3 font-weight-600 text-dark">{{ $case->product_name }}</td>

								<!-- 5. SKU -->
								<td class="py-2.5 px-2.5 text-muted font-mono dairy-fs-xs">{{ $case->product_sku }}</td>

								<!-- 6. LOTE -->
								<td class="py-2.5 px-2.5 text-muted font-mono dairy-fs-xs">{{ $case->batch_number }}</td>

								<!-- 7. CANTIDAD -->
								<td class="py-2.5 px-2 text-right font-weight-500">{{ round($case->amount, 1) }}</td>

								<!-- 8. COSTO UNIT. -->
								<td class="py-2.5 px-2.5 text-right text-muted">${{ number_format($case->unit_cost, 2) }}</td>

								<!-- 9. PÉRDIDA ($) -->
								<td class="py-2.5 px-2.5 text-right font-weight-700 text-danger">${{ number_format($case->total_cost, 2) }}</td>

								<!-- 10. CAUSAL -->
								<td class="py-2.5 px-3 text-dark">{{ $case->reason_name }}</td>

								<!-- 11. CATEGORÍA -->
								<td class="py-2.5 px-2.5 text-muted">{{ $case->reason_category }}</td>

								<!-- 12. ESTADO -->
								<td class="py-2.5 px-2.5 text-center">
									<span class="dairy-pill-badge {{ $statusBadgeClass }}">
										{{ $statusLabel }}
									</span>
								</td>

								<!-- 13. PRIORIDAD -->
								<td class="py-2.5 px-2 text-center">
									<span class="dairy-pill-badge {{ $priorityBadgeClass }}">
										● {{ $priorityLabel }}
									</span>
								</td>

								<!-- 14. RESPONSABLE -->
								<td class="py-2.5 px-2.5 text-dark">{{ $reporterName }}</td>

								<!-- 15. APROBACIÓN -->
								<td class="py-2.5 px-2.5 text-center">
									@if($isApproved)
									<span class="dairy-badge-appr-ok">
										<i class="fa-solid fa-check mr-1 text-success"></i>Aprobado
									</span>
									@else
									<span class="dairy-badge-appr-pending">
										<i class="fa-regular fa-clock mr-1 text-muted"></i>Pendiente
									</span>
									@endif
								</td>

								<!-- 16. EVIDENCIA -->
								<td class="py-2.5 px-2.5 text-center">
									@if($idx % 2 == 0)
									<span class="dairy-evidence-thumb" style="background-image: url('{{ $U('/img/icon-32.png') }}'); background-size: cover;" title="Foto de envase dañado"></span>
									<span class="dairy-evidence-thumb" style="background-image: url('{{ $U('/img/icon-32.png') }}'); background-size: cover;" title="Foto de lectura de sensor"></span>
									@else
									<span class="dairy-evidence-thumb" style="background-image: url('{{ $U('/img/icon-32.png') }}'); background-size: cover;" title="Foto de lote"></span>
									@endif
								</td>

								<!-- 17. ACCIONES -->
								<td class="py-2.5 px-3 text-center">
									<div class="d-inline-flex align-items-center">
										<button class="btn btn-sm btn-link text-muted p-1 mr-1" title="Ver detalle de auditoría" onclick="openCaseModal({{ $case->id }});">
											<i class="fa-regular fa-eye"></i>
										</button>
										<button class="btn btn-sm btn-link text-primary p-1 mr-1" title="Investigar Causa Raíz (5 Porqués)" onclick='openInvestigateModal({{ $case->id }}, @json($case->case_number), @json($case->product_name), {{ floatval($case->total_cost) }});'>
											<i class="fa-solid fa-clipboard-question"></i>
										</button>
										<a class="btn btn-sm btn-link text-muted p-1 mr-1" title="Documento imprimible" href="{{ $U('/document/waste/' . $case->id) }}">
											<i class="fa-solid fa-print"></i>
										</a>
										<div class="dropdown d-inline-block">
											<button class="btn btn-sm btn-link text-muted p-1" data-toggle="dropdown">
												<i class="fa-solid fa-ellipsis"></i>
											</button>
											<div class="dropdown-menu dropdown-menu-right shadow-sm dairy-fs-sm">
												<a class="dropdown-item" href="javascript:void(0)" onclick="quickApproveCase({{ $case->id }});">
													<i class="fa-solid fa-check text-success mr-2"></i>Aprobar Merma
												</a>
												<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-recuperacion-link').click();">
													<i class="fa-solid fa-hand-holding-dollar text-primary mr-2"></i>Reclamar a Proveedor
												</a>
												<a class="dropdown-item" href="javascript:void(0)" onclick="$('#tab-investigaciones-link').click();">
													<i class="fa-solid fa-list-check text-warning mr-2"></i>Asignar Plan de Acción
												</a>
												<div class="dropdown-divider"></div>
												<a class="dropdown-item text-danger" href="javascript:void(0)" onclick="dairyCloseCase({{ $case->id }});">
													<i class="fa-solid fa-lock mr-2"></i>Cerrar Caso
												</a>
											</div>
										</div>
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			<!-- Paginación Inferior idéntica a la Foto -->
			<div class="card-footer bg-white border-top py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between">
				<div class="text-muted font-weight-500 mb-2 mb-md-0 dairy-fs-sm" id="casesCountInfo">
					Mostrando 1 a 5 de {{ count($wasteRecords) }} casos
				</div>
				<div class="d-flex align-items-center">
					<ul class="pagination pagination-sm mb-0 mr-3" id="casesPaginationUl"></ul>
					<select id="casesPageSizeSelect" class="form-control form-control-sm border bg-white w-auto" style="height: 30px;">
						<option value="5" selected>5 por página</option>
						<option value="10">10 por página</option>
						<option value="25">25 por página</option>
						<option value="Todos">Todos</option>
					</select>
				</div>
			</div>
		</div>
		</div> <!-- /waste-legacy-audit-drawer -->
	</div> <!-- /tab-centro-control -->

	<!-- ======================================================================
	     PESTAÑA 2: PREVENCIÓN INTELIGENTE (MOTOR DE RIESGO PREDICTIVO & FEFO)
	     ====================================================================== -->
	<!-- Secciones 2–5, 8, 9 y 11 (LossIntelligenceService), diferidas -->
	<div class="tab-pane fade" id="tab-clasificacion" role="tabpanel" data-lazy-tab="clasificacion">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-ajustes" role="tabpanel" data-lazy-tab="ajustes">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-averias" role="tabpanel" data-lazy-tab="averias">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-anual" role="tabpanel" data-lazy-tab="anual">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-precios" role="tabpanel" data-lazy-tab="precios">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-perecederos" role="tabpanel" data-lazy-tab="perecederos">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-robo" role="tabpanel" data-lazy-tab="robo">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<div class="tab-pane fade" id="tab-prevencion" role="tabpanel">
		@include('components.waste_tabs.prevencion')
	</div>

	<!-- ======================================================================
	     PESTAÑA 3: REGISTRO DE MERMAS (RÁPIDO, CON LECTOR & ATÓMICO)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-registro" role="tabpanel">
		@include('components.waste_tabs.registro')
	</div>

	<!-- ======================================================================
	     PESTAÑA 4: MATRIZ DE CAUSALES (24+ CAUSALES ESTANDARIZADAS)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-causales" role="tabpanel">
		<div data-lazy-block="pareto" id="dailyCausalesContainer"></div>
	</div>

	<!-- ======================================================================
	     PESTAÑA 5: INVESTIGACIONES (5 PORQUÉS Y CAUSA RAÍZ)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-investigaciones" role="tabpanel" data-lazy-tab="investigaciones">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<!-- ======================================================================
	     PESTAÑA 6: PLANES DE ACCIÓN
	     ====================================================================== -->


	<!-- ======================================================================
	     PESTAÑA 7: RECUPERACIÓN FINANCIERA (NOTAS DE CRÉDITO Y RECLAMOS)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-recuperacion" role="tabpanel" data-lazy-tab="impacto">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<!-- ======================================================================
	     PESTAÑA 8: CONTROL FEFO (FIRST EXPIRED, FIRST OUT)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-fefo" role="tabpanel" data-lazy-tab="fefo">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<!-- ======================================================================
	     PESTAÑA 9: CONTEOS Y CONCILIACIÓN (IRA % Y DESCUADRES)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-conteos" role="tabpanel" data-lazy-tab="conteos">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<!-- PÉRDIDA TOTAL: conocida + desconocida + salidas sin documento + conciliación POS (Etapa 19) -->


	<!-- ======================================================================
	     PESTAÑA 10: AUDITORÍA Y TRAZABILIDAD (LOG INMUTABLE)
	     ====================================================================== -->
	<div class="tab-pane fade" id="tab-auditoria" role="tabpanel" data-lazy-tab="auditoria">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>

	<!-- Etapa 36: metodología del Censo Nacional de Mermas -->
	<div class="tab-pane fade" id="tab-censo" role="tabpanel" data-lazy-tab="censo">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-errores" role="tabpanel" data-lazy-tab="errores">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>
	</div>
	<div class="tab-pane fade" id="tab-controles" role="tabpanel" data-lazy-tab="controles">
		<div class="text-center text-muted py-5 dairy-lazy-placeholder"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>

	<!-- PESTAÑA: GESTIÓN DE PRODUCTOS DAÑADOS (FOTOS, CAUSALES, COSTOS, CHARGEBACK) -->
	<div class="tab-pane fade" id="tab-damaged-goods" role="tabpanel">
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">PRODUCTOS DAÑADOS REGISTRADOS</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ count($damagedProducts ?? []) }} <span class="small font-weight-normal text-muted">casos</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-camera text-danger mr-1"></i> Evidencia fotográfica adjunta</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">COSTO TOTAL DEL DAÑO</div>
					<div class="h4 font-weight-bold text-danger mb-0">${{ number_format(array_sum(array_column($damagedProducts ?? [], 'total_cost')), 2) }}</div>
					<div class="small text-muted mt-1">Impacto directo a valoración</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">RECUPERACIÓN VÍA PROVEEDOR</div>
					<div class="h4 font-weight-bold text-success mb-0">${{ number_format(array_sum(array_column($damagedProducts ?? [], 'recovery_potential')), 2) }}</div>
					<div class="small text-muted mt-1">Reclamaciones aceptadas (Chargeback)</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">DISPOSICIÓN FINAL</div>
					<div class="h4 font-weight-bold text-info mb-0">3 destinos</div>
					<div class="small text-muted mt-1">Merma, Devolución o Compostaje</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-camera text-danger mr-2"></i>Gestión Integral de Productos Dañados y Averías</h5>
					<div class="small text-muted">Registro de roturas, causas operativas/comerciales, evidencia gráfica y gestión de recuperaciones.</div>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-danger font-weight-bold" onclick="alert('Abriendo cámara del dispositivo para capturar evidencia fotográfica de producto dañado.');">
						<i class="fa-solid fa-camera mr-1"></i> Capturar Producto Dañado
					</button>
					<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
						<i class="fa-solid fa-shield-halved mr-1"></i> Action Center
					</a>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Evidencia</th>
							<th>Código</th>
							<th>UPC / Barras</th>
							<th>Producto</th>
							<th>Causal de Daño</th>
							<th>Clasificación AKCAL</th>
							<th class="text-right">Cant.</th>
							<th class="text-right">Costo Unit.</th>
							<th class="text-right">Total</th>
							<th>Cobro Proveedor</th>
							<th>Disposición</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach(($damagedProducts ?? []) as $dmg)
						<tr>
							<td>
								@if(!empty($dmg['photo_evidence']))
								<img src="{{ $U($dmg['photo_evidence']) }}" alt="Foto" class="rounded border" style="width: 36px; height: 36px; object-fit: cover;">
								@else
								<span class="badge badge-light border text-muted"><i class="fa-solid fa-image"></i></span>
								@endif
							</td>
							<td><code>{{ $dmg['incident_code'] }}</code></td>
							<td><code>{{ $dmg['barcode'] }}</code></td>
							<td class="font-weight-bold text-dark">{{ $dmg['product_name'] }}</td>
							<td class="small text-muted">{{ $dmg['reason'] }}</td>
							<td><span class="badge badge-light border font-weight-bold">{{ $dmg['classification'] }}</span></td>
							<td class="text-right font-weight-bold">{{ $dmg['quantity'] }}</td>
							<td class="text-right">${{ number_format($dmg['unit_cost'], 2) }}</td>
							<td class="text-right font-weight-bold text-danger">${{ number_format($dmg['total_cost'], 2) }}</td>
							<td>
								@if($dmg['recovery_potential'] > 0)
									<span class="badge badge-success"><i class="fa-solid fa-hand-holding-dollar mr-1"></i> ${{ number_format($dmg['recovery_potential'], 2) }}</span>
								@else
									<span class="badge badge-light border text-muted">No aplica</span>
								@endif
							</td>
							<td><span class="badge badge-info">{{ $dmg['disposition_action'] }}</span></td>
							<td class="text-center">
								@if($dmg['status'] === 'approved')
									<span class="badge badge-success"><i class="fa-solid fa-check"></i> Aprobado</span>
								@elseif($dmg['status'] === 'claimed')
									<span class="badge badge-warning text-dark"><i class="fa-solid fa-paper-plane"></i> Reclamado</span>
								@else
									<span class="badge badge-secondary">Pendiente</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- PESTAÑA: AUDITORÍAS INTELIGENTES (DISCREPANCIAS RECURRENTES, RISK SCORE, CHECKLISTS) -->
	<div class="tab-pane fade" id="tab-smart-audits" role="tabpanel">
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">AUDITORÍAS ACTIVAS</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ count($smartAudits ?? []) }} <span class="small font-weight-normal text-muted">programadas</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-brain text-primary mr-1"></i> Discrepancias recurrentes detectadas</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">STORE RISK SCORE (MEDIO)</div>
					<div class="h4 font-weight-bold text-warning mb-0">68.2 <span class="small font-weight-normal text-muted">/ 100</span></div>
					<div class="small text-muted mt-1">Puntuación ponderada de merma</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">SKUS DE ALTO RIESGO</div>
					<div class="h4 font-weight-bold text-danger mb-0">33 SKUs</div>
					<div class="small text-muted mt-1">Generadores del 80% de pérdida</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">EFECTIVIDAD DE CHECKLISTS</div>
					<div class="h4 font-weight-bold text-success mb-0">94.5%</div>
					<div class="small text-muted mt-1">Reducción de desviaciones post-auditoría</div>
				</div>
			</div>
		</div>

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-magnifying-glass-chart text-primary mr-2"></i>Programa de Auditorías Inteligentes Dirigidas</h5>
					<div class="small text-muted">Auditorías generadas automáticamente por detección algorítmica de variaciones atípicas y patrones de pérdida.</div>
				</div>
				<div>
					<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Generando nueva checklist inteligente basada en los SKUs con mayores discrepancias del último mes.');">
						<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generar Checklist Automática
					</button>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Código</th>
							<th>Título de Auditoría</th>
							<th>Tipo</th>
							<th>Nivel de Riesgo</th>
							<th>Categoría / SKUs</th>
							<th>Motivo Detonador</th>
							<th>Auditor Asignado</th>
							<th>Fecha</th>
							<th>Checklist / Hallazgos</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach(($smartAudits ?? []) as $aud)
						<tr class="@if($aud['risk_level'] === 'critical') table-danger @elseif($aud['risk_level'] === 'high') table-warning @endif">
							<td><code>{{ $aud['audit_code'] }}</code></td>
							<td class="font-weight-bold text-dark">{{ $aud['title'] }}</td>
							<td><span class="badge badge-light border">{{ $aud['audit_type'] }}</span></td>
							<td>
								@if($aud['risk_level'] === 'critical')
									<span class="badge badge-danger">Crítico</span>
								@elseif($aud['risk_level'] === 'high')
									<span class="badge badge-warning text-dark">Alto</span>
								@else
									<span class="badge badge-info">Medio</span>
								@endif
							</td>
							<td><strong>{{ $aud['target_category_or_sku'] }}</strong></td>
							<td class="small text-muted">{{ $aud['trigger_reason'] }}</td>
							<td class="small">{{ $aud['assigned_auditor_name'] }}</td>
							<td class="small text-muted">{{ date('d/m/Y', strtotime($aud['scheduled_date'])) }}</td>
							<td class="small">{{ $aud['findings_summary'] }}</td>
							<td class="text-center">
								@if($aud['status'] === 'completed')
									<span class="badge badge-success"><i class="fa-solid fa-check"></i> Completada</span>
								@elseif($aud['status'] === 'in_progress')
									<span class="badge badge-warning text-dark"><i class="fa-solid fa-spinner fa-spin"></i> En Curso</span>
								@else
									<span class="badge badge-secondary"><i class="fa-solid fa-clock"></i> Programada</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	</div>

	<!-- ======================================================================
	     PESTAÑA 17: BAKERY & PANADERÍA — ¿QUÉ PRODUCTOS SE BOTAN MÁS?
	     ====================================================================== -->
	@include('components.waste_tabs.bakery')

</div> <!-- /wasteModuleTabContent -->

<!-- ==========================================================================
     MODAL DE INVESTIGACIÓN DE CAUSA RAÍZ (5 PORQUÉS)
     ========================================================================== -->
<div class="modal fade" id="investigateModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg dairy-radius-md">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-magnifying-glass-chart text-primary mr-2"></i>Investigación Causa Raíz: <span id="modalCaseNumTitle"></span>
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-light border mb-3">
					<div class="row">
						<div class="col-6"><strong>Producto:</strong> <span id="modalProductTitle"></span></div>
						<div class="col-6 text-right"><strong>Pérdida en Disputa:</strong> <span id="modalCostTitle" class="text-danger font-weight-bold"></span></div>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2">Desarrollo de los 5 Porqués (Ishikawa / Root Cause)</h6>
				<div class="form-group mb-2">
					<label class="small font-weight-600">1. ¿Por qué ocurrió la pérdida?</label>
					<input type="text" class="form-control form-control-sm" id="modalWhy1" placeholder="Respuesta...">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-600">2. ¿Por qué ocurrió lo anterior?</label>
					<input type="text" class="form-control form-control-sm" id="modalWhy2" placeholder="Respuesta...">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-600">3. ¿Por qué ocurrió lo anterior?</label>
					<input type="text" class="form-control form-control-sm" id="modalWhy3" placeholder="Respuesta...">
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-600">4. ¿Por qué ocurrió lo anterior?</label>
					<input type="text" class="form-control form-control-sm" id="modalWhy4" placeholder="Respuesta...">
				</div>
				<div class="form-group mb-3">
					<label class="small font-weight-600">5. ¿Por qué ocurrió lo anterior?</label>
					<input type="text" class="form-control form-control-sm" id="modalWhy5" placeholder="Respuesta...">
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold text-dark">Plan Correctivo Inmediato</label>
					<textarea class="form-control form-control-sm" id="modalCorrectivePlan" rows="2"></textarea>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-3 border-top">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary btn-sm font-weight-600" onclick="saveInvestigationModal();">
					<i class="fa-solid fa-save mr-1"></i>Guardar Dictamen de Causa Raíz
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     SCRIPTS & CHART.JS 2.9.4 ENGINE
     ========================================================================== -->
@push('pageScripts')
<script src="{{ $U('/js/dairy_loss.js?v=', true) }}{{ $version }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	var mdForm = document.getElementById('markdown-rules-form');
	if (!mdForm) return;
	mdForm.addEventListener('submit', function(e) {
		e.preventDefault();
		var btn = document.getElementById('btn-save-markdown-rules');
		var rules = Array.prototype.map.call(mdForm.querySelectorAll('.markdown-rule-row'), function(row) {
			return {
				id: row.getAttribute('data-id'),
				name: row.querySelector('.md-name').value,
				min_days: row.querySelector('.md-min').value,
				max_days: row.querySelector('.md-max').value,
				discount_pct: row.querySelector('.md-pct').value,
				active: row.querySelector('.md-active').checked ? 1 : 0
			};
		});
		btn.disabled = true;
		fetch(Grocy.BaseUrl + '/api/markdown/rules', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ rules: rules })
		})
		.then(function(res) { return res.json(); })
		.then(function(resp) {
			btn.disabled = false;
			if (resp.success) {
				toastr.success('Reglas de rebaja guardadas');
				setTimeout(function() { window.location.hash = 'prevencion'; window.location.reload(); }, 600);
			} else {
				toastr.error(resp.error_message || 'No se pudieron guardar las reglas');
			}
		})
		.catch(function() { btn.disabled = false; toastr.error('Error de conexión'); });
	});
	// Pestañas pesadas: se cargan por AJAX la primera vez que se activan
	$('a[data-toggle="pill"]').on('show.bs.tab', function() {
		var pane = $($(this).attr('href'));
		if (pane.data('lazy-tab') && !pane.data('loaded')) dairyLoadTab(pane);
		pane.find('[data-lazy-block]').each(function() {
			var el = $(this);
			if (el.data('loaded')) return;
			el.data('loaded', true).html('<div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando…</div>');
			$.get(Grocy.BaseUrl + '/waste/tab/' + el.data('lazy-block') + window.location.search)
				.done(function(html) { el.html(html); })
				.fail(function(xhr) { el.data('loaded', false).html('<div class="alert alert-danger">No se pudo cargar (' + xhr.status + ').</div>'); });
		});
	});
	// La pestaña activa se recuerda en el hash (filtros por GET la conservan)
	$('#wasteModuleNav a[data-toggle="pill"]').on('shown.bs.tab', function() {
		history.replaceState(null, '', window.location.pathname + window.location.search + $(this).attr('href'));
	});

	// Abrir la pestaña indicada en el hash (#prevencion, #tab-conteos, ...)
	function dairyOpenHash() {
		if (!window.location.hash) return;
		var h = window.location.hash.replace(/^#(tab-)?/, '');
		// Secciones fusionadas: los enlaces antiguos siguen funcionando
		var alias = { planes: 'investigaciones', perdida: 'recuperacion', 'centro': 'centro-control', frio: 'fefo', selectivos: 'conteos', impacto: 'recuperacion' };
		var target = alias[h] || h;
		$('a[data-toggle="pill"][href="#tab-' + target + '"]').tab('show');
		if (alias[h] && document.getElementById('tab-' + h)) setTimeout(function() { document.getElementById('tab-' + h).scrollIntoView({ behavior: 'smooth' }); }, 400);
	}
	dairyOpenHash();
	window.addEventListener('hashchange', dairyOpenHash);
});

// Carga (o recarga) el contenido de una pestaña diferida
function dairyLoadTab(pane) {
	pane.data('loaded', true);
	return $.get(Grocy.BaseUrl + '/waste/tab/' + pane.data('lazy-tab') + window.location.search)
		.done(function(html) { pane.html(html); })
		.fail(function(xhr) {
			pane.data('loaded', false);
			pane.html('<div class="alert alert-danger m-3">No se pudo cargar la pestaña (' + xhr.status + ').</div>');
		});
}

// Tras una acción: recarga solo la pestaña activa si es diferida; si no, recarga la página volviendo a la misma pestaña
function dairyRefreshActiveTab() {
	var pane = $('#wasteModuleTabContent > .tab-pane.active');
	if (pane.data('lazy-tab')) {
		dairyLoadTab(pane).always(function() { Grocy.FrontendHelpers.EndUiBusy(); });
		return;
	}
	if (pane.length) history.replaceState(null, '', '#' + pane.attr('id'));
	location.reload();
}
document.addEventListener("DOMContentLoaded", function() {

	// 4. PAGINACIÓN Y FILTRADO AVANZADO DE CASOS (RÉPLICA EXACTA)
	var currentCasePage = 1;
	var casePageSize = 5;

	function renderCaseTable() {
		var branch = ($('#filterBranchSelect').val() || '').toLowerCase();
		var causal = ($('#filterCausalSelect').val() || '').toLowerCase();
		var status = ($('#filterStatusSelect').val() || '').toLowerCase();
		var search = ($('#wasteCaseSearchInput').val() || '').toLowerCase();

		var matchedRows = [];
		$('#exactPhotoCasesTable tbody tr.case-data-row').each(function() {
			var $tr = $(this);
			var text = $tr.text().toLowerCase();
			var match = true;
			if (branch && text.indexOf(branch) === -1) match = false;
			if (causal && text.indexOf(causal) === -1) match = false;
			if (status && text.indexOf(status) === -1) match = false;
			if (search && text.indexOf(search) === -1) match = false;

			if (match) {
				matchedRows.push($tr);
			} else {
				$tr.hide();
			}
		});

		var total = matchedRows.length;
		var totalPages = Math.ceil(total / casePageSize) || 1;
		if (currentCasePage > totalPages) currentCasePage = totalPages;
		if (currentCasePage < 1) currentCasePage = 1;

		var startIdx = (currentCasePage - 1) * casePageSize;
		var endIdx = startIdx + casePageSize;

		$.each(matchedRows, function(i, $tr) {
			if (i >= startIdx && i < endIdx) {
				$tr.show();
			} else {
				$tr.hide();
			}
		});

		var startLabel = total > 0 ? (startIdx + 1) : 0;
		var endLabel = Math.min(endIdx, total);
		$('#casesCountInfo').text('Mostrando ' + startLabel + ' a ' + endLabel + ' de ' + total + ' casos');

		// Renderizar links de paginación
		renderPaginationLinks(currentCasePage, totalPages);
	}

	function renderPaginationLinks(curr, totalPages) {
		var $ul = $('#casesPaginationUl');
		$ul.empty();

		// Botón Prev
		var prevDisabled = curr <= 1 ? ' disabled' : '';
		$ul.append('<li class="page-item' + prevDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="setCasePage(' + (curr - 1) + ')"><i class="fa-solid fa-angle-left"></i></a></li>');

		// Mostrar exactamente páginas como la foto: 1, 2, 3, 4, 5, ..., totalPages
		if (totalPages <= 7) {
			for (var p = 1; p <= totalPages; p++) {
				var active = p === curr ? ' active' : '';
				var activeStyle = p === curr ? ' style="background-color: var(--daily-primary, #00C83B); border-color: var(--daily-primary, #00C83B); color: var(--daily-primary-text, #ffffff);"' : '';
				$ul.append('<li class="page-item' + active + '"><a class="page-link" href="javascript:void(0)" onclick="setCasePage(' + p + ')"' + activeStyle + '>' + p + '</a></li>');
			}
		} else {
			var maxLeading = Math.min(5, totalPages - 1);
			for (var p = 1; p <= maxLeading; p++) {
				var active = p === curr ? ' active' : '';
				var activeStyle = p === curr ? ' style="background-color: var(--daily-primary, #00C83B); border-color: var(--daily-primary, #00C83B); color: var(--daily-primary-text, #ffffff);"' : '';
				$ul.append('<li class="page-item' + active + '"><a class="page-link" href="javascript:void(0)" onclick="setCasePage(' + p + ')"' + activeStyle + '>' + p + '</a></li>');
			}
			if (totalPages > 6) {
				$ul.append('<li class="page-item disabled"><a class="page-link" href="#">...</a></li>');
			}
			var lastActive = totalPages === curr ? ' active' : '';
			var lastActiveStyle = totalPages === curr ? ' style="background-color: var(--daily-primary, #00C83B); border-color: var(--daily-primary, #00C83B); color: var(--daily-primary-text, #ffffff);"' : '';
			$ul.append('<li class="page-item' + lastActive + '"><a class="page-link" href="javascript:void(0)" onclick="setCasePage(' + totalPages + ')"' + lastActiveStyle + '>' + totalPages + '</a></li>');
		}

		// Botón Next
		var nextDisabled = curr >= totalPages ? ' disabled' : '';
		$ul.append('<li class="page-item' + nextDisabled + '"><a class="page-link" href="javascript:void(0)" onclick="setCasePage(' + (curr + 1) + ')"><i class="fa-solid fa-angle-right"></i></a></li>');
	}

	window.setCasePage = function(p) {
		currentCasePage = p;
		renderCaseTable();
	};

	$('#wasteCaseSearchInput').on('keyup', function() {
		currentCasePage = 1;
		renderCaseTable();
	});

	$('#filterBranchSelect, #filterDeptSelect, #filterCausalSelect, #filterStatusSelect').on('change', function() {
		currentCasePage = 1;
		renderCaseTable();
	});

	$('#casesPageSizeSelect').on('change', function() {
		var val = $(this).val();
		if (val === 'Todos') {
			casePageSize = 999999;
		} else {
			casePageSize = parseInt(val) || 5;
		}
		currentCasePage = 1;
		renderCaseTable();
	});
});

// CALCULAR COSTO EN FORMULARIO RÁPIDO
function calculateFormCost() {
	var sel = document.getElementById('formProductId');
	var opt = sel.options[sel.selectedIndex];
	var price = parseFloat(opt.getAttribute('data-price') || 0);
	var amount = parseFloat(document.getElementById('formAmount').value || 0);

	document.getElementById('formUnitCost').value = price.toFixed(2);
	document.getElementById('formTotalCost').value = '$' + (price * amount).toFixed(2);
}

// ACCIONES DEL CENTRO DE CONTROL (conectadas a la API)
var DairyMaster = {
	branchId: @json($selectedBranchId ?: 1),
	products: @json(collect($products)->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()),
	reasons: @json(collect($reasons)->map(fn($r) => ['id' => $r->id, 'name' => $r->code . ' - ' . $r->name])->values()),
	users: @json(collect($users)->map(fn($u) => ['id' => $u->id, 'name' => $u->username])->values()),
	suppliers: @json(collect($suppliers)->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values()),
	locations: @json(collect($locations)->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values())
};

function dairyApiError(xhr, prefix) {
	var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error_message) || (xhr && xhr.statusText) || 'Error';
	toastr.error(prefix + ': ' + msg);
}

function dairyPost(path, data, okMsg, errPrefix) {
	Grocy.FrontendHelpers.BeginUiBusy();
	return $.ajax({
		url: Grocy.BaseUrl + '/api' + path,
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data || {})
	}).done(function(res) {
		toastr.success((res && res.message) || okMsg);
		dairyRefreshActiveTab();
	}).fail(function(xhr) {
		Grocy.FrontendHelpers.EndUiBusy();
		var m = '';
		try { m = JSON.parse(xhr.responseText).error_message || ''; } catch (e) { }
		if (m.indexOf('Posible duplicado') === 0 && data && !data.confirm_duplicate) {
			bootbox.confirm({ title: '¿Registrar otra vez?', message: $('<div>').text(m).html(), buttons: { confirm: { label: 'Registrar de todos modos', className: 'btn-danger' }, cancel: { label: 'Cancelar' } }, callback: function(ok) {
				if (ok) { data.confirm_duplicate = true; dairyPost(path, data, okMsg, errPrefix); }
			} });
			return;
		}
		dairyApiError(xhr, errPrefix);
	});
}

// Exportación del servidor con los filtros del período/sucursal actuales (todas las filas, no solo la página visible)
function dairyWasteExport(format) {
	var q = new URLSearchParams(window.location.search), out = new URLSearchParams({ format: format });
	[['branch_id', 'branch_id'], ['start_date', 'from'], ['end_date', 'to'], ['from', 'from'], ['to', 'to'], ['category', 'category'], ['reason_id', 'reason_id']].forEach(function(p) { if (q.get(p[0])) { out.set(p[1], q.get(p[0])); } });
	window.location.href = Grocy.BaseUrl + '/api/waste/export?' + out.toString();
}

function dairyOptions(list, emptyLabel) {
	var html = emptyLabel ? '<option value="">' + emptyLabel + '</option>' : '';
	list.forEach(function(o) { html += '<option value="' + o.id + '">' + $('<div>').text(o.name).html() + '</option>'; });
	return html;
}

// Diálogo de formulario reutilizable (bootbox); fields: [{id, label, type, options, required, value}]
function dairyFormDialog(title, fields, onSave) {
	var html = '<form id="dairyDialogForm">';
	fields.forEach(function(f) {
		html += '<div class="form-group mb-2"><label class="small font-weight-600" for="dlg_' + f.id + '">' + f.label + (f.required ? ' *' : '') + '</label>';
		if (f.type === 'select') {
			html += '<select class="form-control form-control-sm" id="dlg_' + f.id + '">' + f.options + '</select>';
		} else if (f.type === 'textarea') {
			html += '<textarea class="form-control form-control-sm" rows="2" id="dlg_' + f.id + '"></textarea>';
		} else {
			html += '<input class="form-control form-control-sm" type="' + (f.type || 'text') + '" id="dlg_' + f.id + '" value="' + (f.value || '') + '"' + (f.type === 'number' ? ' step="any" min="0"' : '') + '>';
		}
		html += '</div>';
	});
	html += '</form>';
	bootbox.dialog({
		title: title,
		message: html,
		buttons: {
			cancel: { label: 'Cancelar', className: 'btn-secondary btn-sm' },
			ok: {
				label: 'Guardar', className: 'btn-primary btn-sm',
				callback: function() {
					var data = {}, missing = false;
					fields.forEach(function(f) {
						data[f.id] = $('#dlg_' + f.id).val();
						if (f.required && !data[f.id]) { missing = true; $('#dlg_' + f.id).addClass('is-invalid'); }
					});
					if (missing) { toastr.warning('Complete los campos obligatorios.'); return false; }
					onSave(data);
				}
			}
		}
	});
}

function dairyToday(offsetDays) {
	var d = new Date(); d.setDate(d.getDate() + (offsetDays || 0));
	return d.toISOString().slice(0, 10);
}

function dairyNewPlanDialog() {
	dairyFormDialog('Nuevo plan de acción', [
		{ id: 'activity_title', label: 'Acción preventiva / correctiva', required: true },
		{ id: 'problem_description', label: 'Problema detectado', type: 'textarea', required: true },
		{ id: 'follow_up_actions', label: 'Seguimiento', type: 'textarea' },
		{ id: 'waste_reason_id', label: 'Causal asociada', type: 'select', options: dairyOptions(DairyMaster.reasons, '—') },
		{ id: 'assigned_user_id', label: 'Responsable', type: 'select', options: dairyOptions(DairyMaster.users), required: true },
		{ id: 'priority', label: 'Prioridad', type: 'select', options: '<option value="critica">Crítica</option><option value="alta" selected>Alta</option><option value="media">Media</option><option value="baja">Baja</option>' },
		{ id: 'due_date', label: 'Fecha límite', type: 'date', value: dairyToday(7), required: true },
		{ id: 'cost_avoided_estimate', label: 'Costo evitado estimado ($)', type: 'number' }
	], function(d) {
		d.branch_id = DairyMaster.branchId;
		dairyPost('/waste/action-plans', d, 'Plan de acción creado', 'No se pudo crear el plan');
	});
}

function dairyClosePlan(planId) {
	bootbox.prompt({
		title: 'Cerrar plan de acción: evidencia de cumplimiento',
		inputType: 'textarea',
		callback: function(notes) {
			if (notes === null) return;
			dairyPost('/waste/action-plans/' + planId + '/update', { status: 'completed', evidence_notes: notes }, 'Plan cerrado', 'No se pudo cerrar el plan');
		}
	});
}

function dairyNewClaimDialog() {
	dairyFormDialog('Reclamo a proveedor (nota de crédito)', [
		{ id: 'supplier_id', label: 'Proveedor', type: 'select', options: dairyOptions(DairyMaster.suppliers, 'Seleccione...'), required: true },
		{ id: 'product_id', label: 'Producto', type: 'select', options: dairyOptions(DairyMaster.products, 'Seleccione...'), required: true },
		{ id: 'amount', label: 'Cantidad afectada', type: 'number', required: true },
		{ id: 'batch_number', label: 'Lote' },
		{ id: 'incident_type', label: 'Tipo', type: 'select', options: '<option value="quality">Calidad</option><option value="temperature">Cadena de frío</option><option value="short_delivery">Faltante en entrega</option><option value="damaged">Producto averiado</option><option value="expiry">Vida útil corta</option><option value="other">Otro</option>' },
		{ id: 'credit_requested_amount', label: 'Monto a reclamar ($) — vacío = costo de la pérdida', type: 'number' },
		{ id: 'return_reason', label: 'Motivo', type: 'textarea', required: true }
	], function(d) {
		d.branch_id = DairyMaster.branchId;
		dairyPost('/waste/supplier-returns', d, 'Reclamo registrado', 'No se pudo registrar el reclamo');
	});
}

function dairyRecordCreditDialog(returnId, caseNumber, requested) {
	dairyFormDialog('Nota de crédito: ' + caseNumber, [
		{ id: 'credit_recovered_amount', label: 'Monto recuperado ($)', type: 'number', value: requested, required: true },
		{ id: 'notes', label: 'Nº de nota de crédito / observaciones', type: 'textarea' }
	], function(d) {
		dairyPost('/waste/supplier-returns/' + returnId + '/credit', d, 'Nota de crédito registrada', 'No se pudo registrar la nota de crédito');
	});
}

function dairyQuarantineLot(productId, stockId, amount, locationId) {
	bootbox.prompt({
		title: 'Bloquear lote en cuarentena: motivo',
		value: 'Lote crítico por vencimiento (FEFO)',
		callback: function(reason) {
			if (reason === null) return;
			dairyPost('/quality/quarantine', { product_id: productId, stock_id: stockId, amount: amount, location_id: locationId, branch_id: DairyMaster.branchId, reason: reason }, 'Lote enviado a cuarentena', 'No se pudo bloquear el lote');
		}
	});
}

function dairyAutoAbcCounts() {
	if (!DairyMaster.branchId) { toastr.warning('Seleccione una sucursal en el encabezado para programar por ABC'); return; }
	$.ajax({ url: Grocy.BaseUrl + '/api/stock/cycle-counts/auto-abc', method: 'POST', contentType: 'application/json', data: JSON.stringify({ branch_id: DairyMaster.branchId, dry_run: true }) })
	.done(function(r) {
		if (!r.due.length) { toastr.info('Ningún producto tiene conteo vencido según su clase ABC'); return; }
		var byClass = { A: 0, B: 0, C: 0 }; r.due.forEach(function(d) { byClass[d.abc_class]++; });
		var rows = r.due.slice(0, 12).map(function(d) { return '<tr><td>' + $('<span>').text(d.product_name).html() + '</td><td class="text-center"><span class="badge badge-' + (d.abc_class === 'A' ? 'danger' : d.abc_class === 'B' ? 'warning' : 'secondary') + '">' + d.abc_class + '</span></td><td class="text-right">' + d.stock + '</td><td>' + (d.last_count || 'nunca') + '</td></tr>'; }).join('');
		bootbox.confirm({
			title: 'Programar ' + r.due.length + ' conteos por ABC',
			message: '<p class="mb-2 dairy-fs-base">Clase A: ' + byClass.A + ' · B: ' + byClass.B + ' · C: ' + byClass.C + ' (frecuencia A ' + r.interval_days.A + ' d, B ' + r.interval_days.B + ' d, C ' + r.interval_days.C + ' d; ventas de 90 días).</p><table class="table table-sm mb-0"><thead><tr><th>Producto</th><th class="text-center">Clase</th><th class="text-right">Stock</th><th>Último conteo</th></tr></thead><tbody>' + rows + '</tbody></table>' + (r.due.length > 12 ? '<p class="text-muted small mt-1 mb-0">… y ' + (r.due.length - 12) + ' más</p>' : ''),
			buttons: { confirm: { label: 'Programar', className: 'btn-primary' }, cancel: { label: 'Cancelar', className: 'btn-secondary' } },
			callback: function(ok) { if (ok) dairyPost('/stock/cycle-counts/auto-abc', { branch_id: DairyMaster.branchId }, 'Conteos programados', 'No se pudo programar'); }
		});
	}).fail(function(xhr) { dairyApiError(xhr, 'No se pudo calcular la clasificación ABC'); });
}

function dairyScheduleCountDialog() {
	dairyFormDialog('Programar conteo cíclico', [
		{ id: 'product_id', label: 'Producto', type: 'select', options: dairyOptions(DairyMaster.products, 'Seleccione...'), required: true },
		{ id: 'location_id', label: 'Ubicación', type: 'select', options: dairyOptions(DairyMaster.locations, 'Todas') },
		{ id: 'assigned_user_id', label: 'Responsable', type: 'select', options: dairyOptions(DairyMaster.users), required: true },
		{ id: 'scheduled_date', label: 'Fecha', type: 'date', value: dairyToday(0), required: true }
	], function(d) {
		d.branch_id = DairyMaster.branchId;
		dairyPost('/stock/cycle-counts', d, 'Conteo programado', 'No se pudo programar el conteo');
	});
}

function dairyExecuteCountDialog(countId, productName, theoretical) {
	dairyFormDialog('Registrar conteo: ' + productName + (theoretical === null ? ' (conteo ciego)' : ' (sistema: ' + theoretical + ')'), [
		{ id: 'counted_stock', label: 'Stock físico contado', type: 'number', required: true },
		{ id: 'notes', label: 'Observaciones', type: 'textarea' }
	], function(d) {
		dairyPost('/stock/cycle-counts/' + countId + '/execute', d, 'Conteo registrado', 'No se pudo registrar el conteo');
	});
}

function dairyApproveAdjustment(countId, diff) {
	bootbox.confirm('¿Aprobar el ajuste de inventario de ' + (diff > 0 ? '+' : '') + diff + ' unidades? Se contabiliza como corrección de inventario, no como merma.', function(ok) {
		if (!ok) return;
		dairyPost('/stock/cycle-counts/' + countId + '/approve-adjustment', {}, 'Ajuste contabilizado', 'No se pudo aprobar el ajuste');
	});
}

function dairyCloseCase(caseId) {
	bootbox.confirm('¿Cerrar este caso? Quedará registrado en la bitácora de auditoría.', function(ok) {
		if (!ok) return;
		dairyPost('/waste/cases/' + caseId + '/investigation', { workflow_status: 'closed' }, 'Caso cerrado', 'No se pudo cerrar el caso');
	});
}

// SUBMIT REGISTRO RÁPIDO DE MERMA (sube la foto de evidencia si se adjuntó)
function submitFastWaste(e) {
	e.preventDefault();
	var data = {
		product_id: $('#formProductId').val(),
		amount: $('#formAmount').val(),
		waste_reason_id: $('#formReasonId').val(),
		branch_id: $('#formBranchId').val(),
		batch_number: $('#formBatchNumber').val(),
		notes: $('#formNotes').val(),
		final_destination: $('#formDestination').val(),
		weight_kg: $('#formWeightKg').val()
	};

	if (!data.product_id || !data.amount || !data.waste_reason_id) {
		toastr.warning('Por favor complete los campos obligatorios.');
		return;
	}

	var file = $('#formEvidencePhoto')[0] && $('#formEvidencePhoto')[0].files[0];
	if (!file) {
		dairyPost('/waste/report', data, 'Caso de merma registrado', 'No se pudo registrar la merma');
		return;
	}

	var formData = new FormData();
	formData.append('photo', file);
	Grocy.FrontendHelpers.BeginUiBusy();
	$.ajax({
		url: Grocy.BaseUrl + '/api/waste/upload-photo',
		method: 'POST',
		data: formData,
		processData: false,
		contentType: false
	}).done(function(res) {
		Grocy.FrontendHelpers.EndUiBusy();
		data.evidence_photo_path = res.relative_url;
		dairyPost('/waste/report', data, 'Caso de merma registrado', 'No se pudo registrar la merma');
	}).fail(function(xhr) {
		Grocy.FrontendHelpers.EndUiBusy();
		dairyApiError(xhr, 'No se pudo subir la foto');
	});
}

// APROBACIÓN RÁPIDA
function quickApproveCase(caseId) {
	bootbox.confirm('¿Autorizar este registro de merma conforme a política corporativa?', function(ok) {
		if (!ok) return;
		dairyPost('/waste/' + caseId + '/approve', {}, 'Caso aprobado', 'No se pudo aprobar el caso');
	});
}

// MODAL DE INVESTIGACIÓN
var investigateCaseId = null;
function openInvestigateModal(id, caseNum, product, cost) {
	investigateCaseId = id;
	$('#modalCaseNumTitle').text(caseNum);
	$('#modalProductTitle').text(product);
	$('#modalCostTitle').text('$' + Number(cost).toFixed(2));
	$('#investigateModal').modal('show');
}

function saveInvestigationModal() {
	var whys = [];
	for (var i = 1; i <= 5; i++) {
		var v = $.trim($('#modalWhy' + i).val());
		if (v) whys.push(i + '. ' + v);
	}
	if (!whys.length) {
		toastr.warning('Registre al menos el primer porqué.');
		return;
	}
	dairyPost('/waste/cases/' + investigateCaseId + '/investigation', {
		root_cause_analysis: whys.join('\n'),
		corrective_action_plan: $('#modalCorrectivePlan').val(),
		workflow_status: 'reviewing'
	}, 'Investigación guardada', 'No se pudo guardar la investigación');
}

function openCaseModal(id) {
	$('#tab-investigaciones-link').click();
}

function exportToCsv() {
	var csv = "ID CASO,FECHA,SUCURSAL,PRODUCTO,SKU,LOTE,CANTIDAD,COSTO UNITARIO,PERDIDA TOTAL,CAUSAL,ESTADO,RESPONSABLE\n";
	$('#exactPhotoCasesTable tbody tr').each(function() {
		var row = [];
		$(this).find('td').each(function(idx) {
			if (idx < 14) {
				row.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
			}
		});
		csv += row.join(",") + "\n";
	});
	var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	var link = document.createElement("a");
	link.href = URL.createObjectURL(blob);
	link.setAttribute("download", 'DAILY_Control_Mermas_' + new Date().toISOString().slice(0, 10) + '.csv');
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
}
</script>

<script>
// ============================================================================
// DAILY CONTROL DE MERMAS — EXECUTIVE CHARTS & INTERACTIVE FILTERS
// ============================================================================
var dwEvolutionChartInstance = null;
var dwTypeDonutChartInstance = null;
var dwRecoveryGaugeChartInstance = null;

var dwEvolutionData = @json($wasteExec['evolution'] ?? []);
var dwDonutData = @json($wasteExec['donut_types'] ?? []);
var dwRecoveryData = @json($wasteExec['recovery'] ?? []);
var dwCurrentMetric = 'pct';

function initExecutiveCharts() {
	if (typeof Chart === 'undefined') {
		setTimeout(initExecutiveCharts, 150);
		return;
	}

	// 1. Gráfico de Evolución de Mermas vs. Ventas
	var ctxEvol = document.getElementById('dwEvolutionChart');
	if (ctxEvol && dwEvolutionData && dwEvolutionData.labels) {
		if (dwEvolutionChartInstance) dwEvolutionChartInstance.destroy();
		
		var currSeries = dwCurrentMetric === 'money' ? dwEvolutionData.money.current : dwEvolutionData.pct.current;
		var prevSeries = dwCurrentMetric === 'money' ? dwEvolutionData.money.previous : dwEvolutionData.pct.previous;
		var targetSeries = dwCurrentMetric === 'money' ? dwEvolutionData.money.target : dwEvolutionData.pct.target;

		dwEvolutionChartInstance = new Chart(ctxEvol.getContext('2d'), {
			type: 'line',
			data: {
				labels: dwEvolutionData.labels,
				datasets: [
					{
						label: 'Merma {{ date('Y') }}',
						data: currSeries,
						borderColor: '#EF4444',
						backgroundColor: 'rgba(239, 68, 68, 0.05)',
						borderWidth: 2.5,
						pointBackgroundColor: '#EF4444',
						pointBorderColor: '#FFFFFF',
						pointBorderWidth: 1.5,
						pointRadius: 3.5,
						pointHoverRadius: 5.5,
						fill: false,
						lineTension: 0.3
					},
					{
						label: 'Merma {{ date('Y') - 1 }}',
						data: prevSeries,
						borderColor: '#94A3B8',
						backgroundColor: 'transparent',
						borderWidth: 2,
						pointBackgroundColor: '#94A3B8',
						pointBorderColor: '#FFFFFF',
						pointBorderWidth: 1.5,
						pointRadius: 3,
						pointHoverRadius: 5,
						fill: false,
						lineTension: 0.3
					},
					{
						label: 'Objetivo',
						data: targetSeries,
						borderColor: '#10B981',
						borderDash: [5, 4],
						borderWidth: 1.8,
						pointRadius: 0,
						fill: false
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
							var ds = data.datasets[item.datasetIndex];
							var val = dwCurrentMetric === 'money' ? '$' + Number(item.yLabel).toLocaleString() : Number(item.yLabel).toFixed(2) + '%';
							return ds.label + ': ' + val;
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontSize: 10, fontColor: '#94A3B8' }
					}],
					yAxes: [{
						gridLines: { color: '#F1F5F9' },
						ticks: {
							beginAtZero: true,
							fontSize: 10,
							fontColor: '#94A3B8',
							callback: function(v) {
								return dwCurrentMetric === 'money' ? '$' + v : v + '%';
							}
						}
					}]
				}
			}
		});
	}

	// 2. Gráfico de Dona: Distribución por Tipo
	var ctxDonut = document.getElementById('dwTypeDonutChart');
	if (ctxDonut && dwDonutData && dwDonutData.items) {
		if (dwTypeDonutChartInstance) dwTypeDonutChartInstance.destroy();

		var labels = dwDonutData.items.map(function(i) { return i.label; });
		var vals = dwDonutData.items.map(function(i) { return i.amount; });
		var colors = dwDonutData.items.map(function(i) { return i.color; });

		dwTypeDonutChartInstance = new Chart(ctxDonut.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: vals,
					backgroundColor: colors,
					borderWidth: 2,
					borderColor: '#FFFFFF'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutoutPercentage: 70,
				legend: { display: false },
				tooltips: {
					callbacks: {
						label: function(item, data) {
							var label = data.labels[item.index] || '';
							var val = data.datasets[0].data[item.index];
							return label + ': $' + Number(val).toLocaleString();
						}
					}
				}
			}
		});
	}

	// 3. Semicircular Gauge: Recuperación Financiera
	var ctxGauge = document.getElementById('dwRecoveryGaugeChart');
	if (ctxGauge && dwRecoveryData) {
		if (dwRecoveryGaugeChartInstance) dwRecoveryGaugeChartInstance.destroy();

		var rate = dwRecoveryData.recovery_rate || 72;
		var remain = Math.max(0, 100 - rate);

		dwRecoveryGaugeChartInstance = new Chart(ctxGauge.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: ['Recuperado', 'Pendiente'],
				datasets: [{
					data: [rate, remain],
					backgroundColor: ['#10B981', '#E2E8F0'],
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				circumference: Math.PI,
				rotation: Math.PI,
				cutoutPercentage: 75,
				legend: { display: false },
				tooltips: {
					callbacks: {
						label: function(item, data) {
							return data.labels[item.index] + ': ' + data.datasets[0].data[item.index] + '%';
						}
					}
				}
			}
		});
	}
}

// Cambiar métrica de evolución ($, %, por cada $1,000)
function dwSetEvolutionUnit(unit) {
	dwCurrentMetric = unit;
	$('#dw-unit-pills .dw-pill-btn').removeClass('active');
	if (unit === 'money') $('#dw-unit-pills .dw-pill-btn:eq(0)').addClass('active');
	else if (unit === 'pct') $('#dw-unit-pills .dw-pill-btn:eq(1)').addClass('active');
	else if (unit === 'per1k') $('#dw-unit-pills .dw-pill-btn:eq(2)').addClass('active');
	
	initExecutiveCharts();
}

function dwToggleEvolutionUnit(val) {
	dwSetEvolutionUnit(val);
}

function dwToggleDonutUnit(val) {
	if (!dwTypeDonutChartInstance || !dwDonutData.items) return;
	if (val === 'pct') {
		dwTypeDonutChartInstance.data.datasets[0].data = dwDonutData.items.map(function(i) { return i.share; });
	} else {
		dwTypeDonutChartInstance.data.datasets[0].data = dwDonutData.items.map(function(i) { return i.amount; });
	}
	dwTypeDonutChartInstance.update();
}

// Filtro en vivo AJAX del Centro de Control Ejecutivo
function dwApplyFilters() {
	var date = $('#dw-filter-date').val();
	var branch = $('#dw-filter-branch').val();
	var dept = $('#dw-filter-dept').val();
	var causal = $('#dw-filter-causal').val();

	Grocy.FrontendHelpers.BeginUiBusy();

	$.get(Grocy.BaseUrl + '/api/loss/control-center-executive', {
		date: date,
		branch_id: branch,
		department: dept,
		causal: causal,
		metric: dwCurrentMetric
	})
	.done(function(data) {
		Grocy.FrontendHelpers.EndUiBusy();
		if (!data || !data.kpis) return;

		// Actualizar KPIs
		$('#dw-val-total-waste').text(data.kpis.total_waste.value);
		$('#dw-delta-total-waste').text(data.kpis.total_waste.delta);
		$('#dw-spark-total-waste').attr('d', data.kpis.total_waste.sparkline);

		$('#dw-val-waste-sales').text(data.kpis.waste_sales.value);
		$('#dw-delta-waste-sales').text(data.kpis.waste_sales.delta);
		$('#dw-spark-waste-sales').attr('d', data.kpis.waste_sales.sparkline);

		$('#dw-val-operative').text(data.kpis.operative.value);
		$('#dw-delta-operative').text(data.kpis.operative.delta);
		$('#dw-spark-operative').attr('d', data.kpis.operative.sparkline);

		$('#dw-val-commercial').text(data.kpis.commercial.value);
		$('#dw-delta-commercial').text(data.kpis.commercial.delta);
		$('#dw-spark-commercial').attr('d', data.kpis.commercial.sparkline);

		$('#dw-val-breakage').text(data.kpis.breakage.value);
		$('#dw-delta-breakage').text(data.kpis.breakage.delta);
		$('#dw-spark-breakage').attr('d', data.kpis.breakage.sparkline);

		$('#dw-val-adjustments').text(data.kpis.adjustments.value);
		$('#dw-delta-adjustments').text(data.kpis.adjustments.delta);
		$('#dw-spark-adjustments').attr('d', data.kpis.adjustments.sparkline);

		$('#dw-val-recovery').text(data.kpis.recovery.value);
		$('#dw-delta-recovery').text(data.kpis.recovery.delta);
		$('#dw-spark-recovery').attr('d', data.kpis.recovery.sparkline);

		$('#dw-val-avoidable').text(data.kpis.avoidable.value);
		$('#dw-delta-avoidable').text(data.kpis.avoidable.delta);
		$('#dw-spark-avoidable').attr('d', data.kpis.avoidable.sparkline);

		// Actualizar Gráficos
		dwEvolutionData = data.evolution;
		dwDonutData = data.donut_types;
		dwRecoveryData = data.recovery;

		$('#dw-side-curr-val').text(data.evolution.current_month.value);
		$('#dw-side-curr-delta').text(data.evolution.current_month.delta);
		$('#dw-side-proj-val').text(data.evolution.closing_projection.value);
		$('#dw-side-proj-delta').text(data.evolution.closing_projection.delta);
		$('#dw-donut-center-total').text(data.donut_types.total);
		$('#dw-gauge-center-pct').text(data.recovery.recovery_rate + '%');
		$('#dw-rec-val').text(data.recovery.recovered_amount);

		initExecutiveCharts();
		toastr.info('Centro de control actualizado');
	})
	.fail(function() {
		Grocy.FrontendHelpers.EndUiBusy();
		toastr.error('Error al actualizar datos');
	});
}

function dwSortTopProducts(mode) {
	var $tbody = $('#dwTopProductsTable tbody');
	var rows = $tbody.find('tr').get();
	rows.sort(function(a, b) {
		var va = parseFloat($(a).data(mode)) || 0;
		var vb = parseFloat($(b).data(mode)) || 0;
		return vb - va;
	});
	$.each(rows, function(idx, row) {
		$(row).find('td:first').text(idx + 1);
		$tbody.append(row);
	});
}

function dwSortDepartmentBars(mode) {
	var $container = $('#dw-dept-bars-container');
	var rows = $container.find('.dw-dept-bar-row').get();
	rows.sort(function(a, b) {
		var va = parseFloat($(a).data(mode === 'val' ? 'amount' : 'pct')) || 0;
		var vb = parseFloat($(b).data(mode === 'val' ? 'amount' : 'pct')) || 0;
		return vb - va;
	});
	$.each(rows, function(idx, row) {
		$container.append(row);
	});
}

function dwFilterBranchTab(tab, el) {
	$(el).parent().find('button').removeClass('active');
	$(el).addClass('active');
}

// Ocultar dailyTopKpisBar en el Centro de Control para evitar duplicidad y mantener el diseño exacto de la foto
function dwSyncTopKpisBar() {
	if ($('#tab-centro-control').hasClass('active') || window.location.hash === '' || window.location.hash === '#tab-centro-control') {
		$('#dailyTopKpisBar').hide();
	} else {
		$('#dailyTopKpisBar').show();
	}
}

// Exponer funciones en window
window.dwSetEvolutionUnit = dwSetEvolutionUnit;
window.dwToggleEvolutionUnit = dwToggleEvolutionUnit;
window.dwToggleDonutUnit = dwToggleDonutUnit;
window.dwApplyFilters = dwApplyFilters;
window.dwSortTopProducts = dwSortTopProducts;
window.dwSortDepartmentBars = dwSortDepartmentBars;
window.dwFilterBranchTab = dwFilterBranchTab;
window.initExecutiveCharts = initExecutiveCharts;

// Inicializar al cargar el DOM
$(document).ready(function() {
	$('[data-toggle="tooltip"]').tooltip();
	dwSyncTopKpisBar();
	$('#wasteModuleNav a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
		dwSyncTopKpisBar();
		if ($(this).attr('href') === '#tab-centro-control') {
			setTimeout(initExecutiveCharts, 150);
		}
	});
	setTimeout(initExecutiveCharts, 120);
});
</script>
@endpush
@endsection
