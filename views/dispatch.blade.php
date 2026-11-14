@extends('layout.default')

@section('title', 'Despachos y Devoluciones | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_dispatches_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_dispatches_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_dispatch.css?v=', true) }}{{ daily_asset_version('/css/daily_dispatch.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_sales_unslop.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_unslop.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_a11y.css?v=', true) }}{{ daily_asset_version('/css/daily_a11y.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_dashboard_kit.css?v=', true) }}{{ daily_asset_version('/css/daily_dashboard_kit.css') }}" rel="stylesheet">
<style>
	/* Ciclo Operativo Stepper */
	.dp-lifecycle-card {
		background: #ffffff;
		border: 1px solid var(--dp-border, #e2e8f0);
		border-radius: 12px;
		padding: 14px 18px;
		margin-bottom: 1.25rem;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
	}
	.dp-lifecycle-header {
		padding-bottom: 10px;
		margin-bottom: 12px;
		border-bottom: 1px solid #f1f5f9;
	}
	.dp-lifecycle-stepper-wrap {
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
		padding-bottom: 4px;
	}
	.dp-lifecycle-stepper {
		display: flex;
		align-items: center;
		min-width: 880px;
		justify-content: space-between;
	}
	.dp-lifecycle-step {
		display: flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		padding: 6px 10px;
		border-radius: 8px;
		text-decoration: none !important;
		color: inherit !important;
		transition: background-color 0.15s ease, transform 0.15s ease;
	}
	.dp-lifecycle-step:hover {
		background-color: #f1f5f9;
		transform: translateY(-1px);
	}
	.dp-step-circle-num {
		width: 28px;
		height: 28px;
		border-radius: 50%;
		background: #f8fafc;
		color: #475569;
		display: flex;
		align-items: center;
		justify-content: center;
		font-weight: 700;
		font-size: 0.8rem;
		border: 1.5px solid #cbd5e1;
		flex-shrink: 0;
		transition: all 0.15s ease;
	}
	.dp-lifecycle-step:hover .dp-step-circle-num {
		background: #059669;
		color: #ffffff;
		border-color: #059669;
	}
	.dp-step-circle-num.is-active {
		background: #059669;
		color: #ffffff;
		border-color: #059669;
	}
	.dp-lifecycle-divider {
		flex: 1;
		height: 2px;
		background: #e2e8f0;
		min-width: 12px;
		margin: 0 4px;
	}

	/* 8 KPIs Grid */
	.dp-kpis-grid-8 {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 12px;
		margin-bottom: 1.25rem;
	}
	@media (max-width: 1200px) {
		.dp-kpis-grid-8 {
			grid-template-columns: repeat(2, 1fr);
		}
	}
	@media (max-width: 576px) {
		.dp-kpis-grid-8 {
			grid-template-columns: 1fr;
		}
	}

	/* Main Tabs Navigation */
	.dp-main-nav {
		display: flex;
		gap: 6px;
		border-bottom: 2px solid #e2e8f0;
		margin-bottom: 1.25rem;
		overflow-x: auto;
		padding-bottom: 2px;
	}
	.dp-main-nav-link {
		padding: 9px 16px;
		font-size: 0.84rem;
		font-weight: 600;
		color: #64748b;
		border-radius: 8px 8px 0 0;
		text-decoration: none !important;
		border-bottom: 2px solid transparent;
		margin-bottom: -2px;
		white-space: nowrap;
		display: flex;
		align-items: center;
		gap: 8px;
		transition: all 0.15s ease;
		background-color: transparent;
	}
	.dp-main-nav-link:hover {
		color: #059669;
		background-color: #f8fafc;
	}
	.dp-main-nav-link.active {
		color: #059669;
		border-bottom-color: #059669;
		background-color: #ffffff;
		box-shadow: 0 -2px 4px rgba(0,0,0,0.02);
	}

	/* Table and panels styling */
	.dp-section-card {
		background: #ffffff;
		border: 1px solid var(--dp-border, #e2e8f0);
		border-radius: 12px;
		padding: 18px 20px;
		margin-bottom: 1.25rem;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
	}
	.dp-section-title {
		font-size: 1.05rem;
		font-weight: 700;
		color: #0f172a;
		margin-bottom: 4px;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.dp-section-sub {
		font-size: 0.8rem;
		color: #64748b;
		margin-bottom: 14px;
	}
	.dp-fleet-card {
		background: #ffffff;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 14px;
		transition: all 0.15s ease;
	}
	.dp-fleet-card:hover {
		border-color: #a7f3d0;
		box-shadow: 0 4px 12px rgba(5, 150, 105, 0.08);
		transform: translateY(-2px);
	}
</style>
@endpush

@section('content')
@php
	$money = fn($v) => $v === null ? 'Sin información' : '$' . number_format(floatval($v), 2);
	$prepAcc = $kpis['picking_accuracy_pct'] ?? null;
	$otifPct = $kpis['otif_pct'] ?? null;
@endphp

<div class="ded-wrapper dp-main-wrapper" id="dispatchApp">
	@include('components.dispatch.dispatches_header')

	<!-- PESTAÑA PRINCIPAL: TABLERO DE DESPACHOS (RÉPLICA EXACTA) -->
	<div id="dp-pane-tablero" class="dp-pane" style="@if(($dpTab ?? 'tablero') === 'tablero') display: block; @else display: none; @endif">
		@include('components.dispatch.dispatches_dashboard')
	</div>

	<!-- PESTAÑAS OPERATIVAS (PEDIDOS, PICKING, RUTAS) -->
	<div id="dp-pane-operational" class="dp-pane" style="@if(($dpTab ?? 'tablero') !== 'tablero') display: block; @else display: none; @endif">
		<div class="dp-page-container">

	<!-- ================= 1. ENCABEZADO HERO ================= -->
	<div class="dp-hero-card">
		<div class="dp-hero-bg"></div>
		
		<div class="dp-hero-left">
			<div class="dp-hero-icon" style="background: #059669; color: #ffffff;">
				<i class="fa-solid fa-truck-ramp-box"></i>
			</div>
			<div>
				<h1 class="dp-hero-title">Gestión de Despachos</h1>
				<p class="dp-hero-desc">Ciclo OMS/WMS exclusivo de pedidos retail: Pedido → Reserva → Picking → Packing → Despacho → Entrega → Conciliación.</p>
			</div>
		</div>

		<div class="dp-hero-actions">
			<form method="get" class="d-inline-flex gap-2 align-items-center mb-0" id="dpFilterForm">
				<select name="branch_id" class="dp-hero-select" onchange="this.form.submit()">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>
				<select name="type" class="dp-hero-select" onchange="this.form.submit()">
					<option value="">Todas las modalidades</option>
					<option value="pickup" @if($deliveryTypeFilter === 'pickup') selected @endif>Retiro en tienda (Pick-up)</option>
					<option value="delivery" @if($deliveryTypeFilter === 'delivery') selected @endif>Despacho a Domicilio</option>
				</select>
			</form>

			<button type="button" class="dp-btn-green" id="btnOpenNewOrderModal" data-toggle="modal" data-target="#modalNewOrder">
				<i class="fa-solid fa-plus"></i>
				<span>Nuevo Pedido</span>
			</button>

			<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalFastScanVerify">
				<i class="fa-solid fa-barcode"></i>
				<span>Escáner PDA / UPC</span>
			</button>

			<button type="button" class="dp-btn-cyan" data-toggle="modal" data-target="#modalScheduleRoute">
				<i class="fa-solid fa-route"></i>
				<span>Transporte</span>
			</button>

			<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalComparativeAnalytics">
				<i class="fa-solid fa-chart-column text-info"></i>
				<span>Analítica OTIF</span>
			</button>
		</div>
	</div>

	<!-- ================= 2. CICLO OPERATIVO VISUAL (7 ETAPAS CANÓNICAS) ================= -->
	<div class="dp-lifecycle-card">
		<div class="dp-lifecycle-header d-flex align-items-center justify-content-between flex-wrap">
			<div class="d-flex align-items-center">
				<span class="badge badge-success px-2.5 py-1 text-uppercase font-weight-700 mr-2" style="font-size: 0.72rem; letter-spacing: 0.05em; background-color: #059669;">
					<i class="fa-solid fa-arrows-spin mr-1"></i> Ciclo OMS / WMS
				</span>
				<h6 class="mb-0 font-weight-bold text-dark" style="font-size: 0.95rem;">Ciclo de Preparación y Entrega (7 Etapas)</h6>
			</div>
			<div class="d-flex align-items-center mt-1 mt-md-0">
				<span class="text-muted small"><i class="fa-solid fa-shield-halved text-success mr-1"></i> Reserva estricta · Cero sobre-despacho · POD obligatorio</span>
			</div>
		</div>
		<div class="dp-lifecycle-stepper-wrap">
			<div class="dp-lifecycle-stepper" data-testid="dispatch-lifecycle-stepper">
				<!-- Etapa 1: Pedido -->
				<div class="dp-lifecycle-step" onclick="$('#tab-pedidos-link').click();" title="1. Pedido: Registro sin duplicación">
					<div class="dp-step-circle-num is-active">1</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Pedido</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Alta y prioridad</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 2: Reserva -->
				<div class="dp-lifecycle-step" onclick="$('#tab-pedidos-link').click();" title="2. Reserva: Bloqueo estricto de mercancía disponible">
					<div class="dp-step-circle-num">2</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Reserva</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Stock bloqueado</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 3: Picking -->
				<div class="dp-lifecycle-step" onclick="$('#tab-picking-link').click();" title="3. Picking: Recolección y validación UPC/FEFO">
					<div class="dp-step-circle-num">3</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Picking</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Escáner y FEFO</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 4: Packing -->
				<div class="dp-lifecycle-step" onclick="$('#tab-picking-link').click();" title="4. Packing: Bultos y etiqueta de despacho">
					<div class="dp-step-circle-num">4</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Packing</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Bultos y Etiqueta</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 5: Despacho -->
				<div class="dp-lifecycle-step" onclick="$('#tab-salidas-link').click();" title="5. Despacho: Transporte y salida física de muelle">
					<div class="dp-step-circle-num">5</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Despacho</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Salida & Ruta</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 6: Entrega -->
				<div class="dp-lifecycle-step" onclick="$('#tab-entregas-link').click();" title="6. Entrega: POD obligatoria (Firma/Foto o PIN)">
					<div class="dp-step-circle-num">6</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Entrega</strong>
						<span style="font-size: 0.68rem; color: #64748b;">POD / PIN / Parcial</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Etapa 7: Conciliación -->
				<div class="dp-lifecycle-step" onclick="$('#tab-conciliacion-link').click();" title="7. Conciliación: Resolución de diferencias e incidencias">
					<div class="dp-step-circle-num">7</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Conciliación</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Cierre Final</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ================= 3. LOS 8 INDICADORES CLAVE CONSOLIDADOS (DATOS REALES) ================= -->
	<div class="dp-kpis-grid-8" data-testid="dispatch-eight-kpis">
		<!-- KPI 1: Pedidos pendientes -->
		<div class="dp-kpi-card kpi-amber" data-testid="kpi-pedidos-pendientes" onclick="$('#tab-pedidos-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Pendientes / En cola</div>
				<div class="dp-kpi-val">{{ intval($kpis['pending_orders'] ?? 0) }}</div>
				<div class="dp-kpi-sub text-warning font-weight-bold">
					<i class="fa-solid fa-clock"></i>
					<span>{{ intval($kpis['preparing_orders'] ?? 0) }} en picking · {{ intval($kpis['ready_orders'] ?? 0) }} listos</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-clock-rotate-left"></i>
			</div>
		</div>

		<!-- KPI 2: Pedidos despachados -->
		<div class="dp-kpi-card kpi-green" data-testid="kpi-pedidos-despachados" onclick="$('#tab-seguimiento-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Pedidos despachados</div>
				<div class="dp-kpi-val text-success">{{ intval($kpis['dispatched_orders'] ?? 0) }}</div>
				<div class="dp-kpi-sub text-success">
					<i class="fa-solid fa-circle-check"></i>
					<span>Salida confirmada</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-truck-ramp-box"></i>
			</div>
		</div>

		<!-- KPI 3: Pedidos atrasados -->
		<div class="dp-kpi-card kpi-red" data-testid="kpi-entregas-atrasadas" onclick="$('#tab-pedidos-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Pedidos atrasados</div>
				<div class="dp-kpi-val text-red">{{ intval($kpis['overdue_orders'] ?? 0) }}</div>
				<div class="dp-kpi-sub text-danger font-weight-bold">
					<i class="fa-solid fa-triangle-exclamation"></i>
					<span>Ventana o fecha vencida</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-hourglass-end"></i>
			</div>
		</div>

		<!-- KPI 4: OTIF (On-Time In-Full) -->
		<div class="dp-kpi-card kpi-blue" data-testid="kpi-otif" data-toggle="modal" data-target="#modalComparativeAnalytics" role="button">
			<div>
				<div class="dp-kpi-label">OTIF (A tiempo y completo)</div>
				<div class="dp-kpi-val text-primary" style="color: #0284c7;">
					{{ $otifPct !== null ? number_format($otifPct, 1) . '%' : 'Sin info' }}
				</div>
				<div class="dp-kpi-sub text-primary">
					<i class="fa-solid fa-award"></i>
					<span>{{ intval($kpis['otif_evaluated'] ?? 0) }} entregas evaluadas</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-bullseye"></i>
			</div>
		</div>

		<!-- KPI 5: Exactitud de picking -->
		<div class="dp-kpi-card kpi-green" data-testid="kpi-exactitud-picking" onclick="$('#tab-picking-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Exactitud de picking</div>
				<div class="dp-kpi-val">{{ $prepAcc !== null ? number_format($prepAcc, 1) . '%' : 'Sin info' }}</div>
				<div class="dp-kpi-sub text-success">
					<i class="fa-solid fa-barcode"></i>
					<span>Validado vs reservado</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-clipboard-check"></i>
			</div>
		</div>

		<!-- KPI 6: Tiempo promedio de preparación -->
		<div class="dp-kpi-card kpi-blue" data-testid="kpi-tiempo-preparacion" onclick="$('#tab-picking-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Tiempo promedio prep.</div>
				<div class="dp-kpi-val" style="font-size: 1.3rem;">{{ $kpis['prep_time_minutes'] ?? 'Sin información' }}</div>
				<div class="dp-kpi-sub">
					<span>Reserva → Packing</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-stopwatch"></i>
			</div>
		</div>

		<!-- KPI 7: Incidencias logísticas -->
		<div class="dp-kpi-card kpi-amber" data-testid="kpi-incidencias" onclick="$('#tab-conciliacion-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Incidencias logísticas</div>
				<div class="dp-kpi-val text-warning">{{ intval($kpis['incidents_count'] ?? 0) }}</div>
				<div class="dp-kpi-sub text-warning font-weight-bold">
					<span>Faltantes / Parciales / Ruta</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-circle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 8: Productividad -->
		<div class="dp-kpi-card kpi-green" data-testid="kpi-productividad" onclick="$('#tab-picking-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Productividad despacho</div>
				<div class="dp-kpi-val text-success" style="font-size: 1.3rem;">{{ $kpis['productivity'] ?? 'Sin información' }}</div>
				<div class="dp-kpi-sub text-success">
					<span>Órdenes reales / hora</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-chart-line"></i>
			</div>
		</div>
	</div>

	<!-- ================= 4. PESTAÑAS OPERATIVAS (7 COMPONENTES) ================= -->
	<ul class="nav dp-main-nav" id="dpMainNav" role="tablist" data-testid="dispatch-main-tabs">
		<li class="nav-item">
			<a class="dp-main-nav-link active" id="tab-seguimiento-link" data-toggle="pill" href="#tab-despachos-seguimiento" role="tab">
				<i class="fa-solid fa-truck-ramp-box"></i> 1. Despachos y Seguimiento
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-pedidos-link" data-toggle="pill" href="#tab-pedidos-prioridades" role="tab">
				<i class="fa-solid fa-list-check"></i> 2. Pedidos y Prioridades
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-picking-link" data-toggle="pill" href="#tab-preparacion-picking" role="tab">
				<i class="fa-solid fa-boxes-packing"></i> 3. Preparación y Picking
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-transporte-link" data-toggle="pill" href="#tab-programacion-rutas" role="tab">
				<i class="fa-solid fa-route"></i> 4. Programación y Transporte
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-salidas-link" data-toggle="pill" href="#tab-salidas-documentacion" role="tab">
				<i class="fa-solid fa-clipboard-check"></i> 5. Salidas y Carga
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-entregas-link" data-toggle="pill" href="#tab-entregas-evidencias" role="tab">
				<i class="fa-solid fa-signature"></i> 6. Entregas y Evidencias
			</a>
		</li>
		<li class="nav-item">
			<a class="dp-main-nav-link" id="tab-conciliacion-link" data-toggle="pill" href="#tab-conciliacion-variaciones" role="tab">
				<i class="fa-solid fa-scale-balanced"></i> 7. Conciliación y Variaciones
			</a>
		</li>
	</ul>

	<!-- CONTENIDO DE LAS PESTAÑAS -->
	<div class="tab-content" id="dpMainTabContent">

		<!-- ================= TAB 1: DESPACHOS Y SEGUIMIENTO (2 COLUMNAS INTERACTIVAS) ================= -->
		<div class="tab-pane fade show active" id="tab-despachos-seguimiento" role="tabpanel">
			<div class="dp-content-grid">

				<!-- Panel Izquierdo: Lista de Pedidos -->
				<div class="dp-orders-panel">
					<div class="dp-orders-header">
						<div class="dp-orders-header-title">
							<i class="fa-solid fa-list-check text-success"></i>
							<span>Pedidos por atender</span>
						</div>
						<span class="dp-badge-count">{{ count($orders) }} pedidos</span>
					</div>

					<div class="dp-orders-filter-box">
						<div class="dp-search-wrap">
							<i class="fa-solid fa-magnifying-glass"></i>
							<input type="text" id="dpOrderSearch" class="dp-search-input" placeholder="Buscar pedido, cliente o producto...">
						</div>

						<div class="dp-pills-bar">
							<button type="button" class="dp-pill-btn is-active" data-filter="all">
								<i class="fa-solid fa-circle-dot"></i> Todos
							</button>
							<button type="button" class="dp-pill-btn" data-filter="pickup">
								<i class="fa-solid fa-store"></i> Retiro en tienda
							</button>
							<button type="button" class="dp-pill-btn" data-filter="delivery">
								<i class="fa-solid fa-truck"></i> Domicilio
							</button>
							<button type="button" class="dp-pill-btn" data-filter="variances">
								<i class="fa-solid fa-triangle-exclamation"></i> Con diferencias
							</button>
						</div>
					</div>

					<div class="dp-orders-list" id="dpOrdersList">
						@foreach($orders as $ord)
							@php
								$isActive = ($ord['id'] == $selectedOrderId);
								$isPickup = (($ord['delivery_type'] ?? 'pickup') === 'pickup');
								$stClass = 'st-packed';
								$stLabel = 'Empacado';
								if ($ord['status'] === 'dispatched') {
									$stClass = 'st-dispatched';
									$stLabel = 'Despachado';
								} elseif ($ord['status'] === 'picked') {
									$stClass = 'st-prepared';
									$stLabel = 'Preparado';
								} elseif ($ord['status'] === 'picking') {
									$stClass = 'st-prepared';
									$stLabel = 'En picking';
								}

								$noticeClass = 'notice-green';
								$noticeIcon = 'fa-circle-check';
								$noticeText = 'Sin diferencias';

								if ($ord['notice_type'] === 'review_reconciliation') {
									$noticeClass = 'notice-amber';
									$noticeIcon = 'fa-triangle-exclamation';
									$noticeText = 'Revisar conciliación';
								} elseif ($ord['notice_type'] === 'pending_variance') {
									$noticeClass = 'notice-red';
									$noticeIcon = 'fa-triangle-exclamation';
									$noticeText = $ord['notice_text'];
								} elseif ($ord['notice_type'] === 'resolved_variances') {
									$noticeClass = 'notice-green';
									$noticeIcon = 'fa-circle-check';
									$noticeText = 'Variaciones resueltas';
								}
							@endphp

							<div class="dp-order-card @if($isActive) is-active @endif" 
								 data-id="{{ $ord['id'] }}" 
								 data-code="{{ $ord['order_code'] }}"
								 data-type="{{ $ord['delivery_type'] ?? 'pickup' }}"
								 data-hasdiff="{{ ($ord['notice_type'] !== 'no_diff') ? '1' : '0' }}">
								
								<img src="{{ $ord['thumbnail'] }}" alt="{{ $ord['order_code'] }}" class="dp-order-thumb" width="58" height="58" style="width: 58px; height: 58px; object-fit: cover; flex-shrink: 0; border-radius: 8px;">

								<div class="dp-order-info">
									<div class="dp-order-top">
										<div class="dp-order-code">
											<span>{{ $ord['order_code'] }}</span>
											@if($isPickup)
												<span class="dp-tag-pickup">Retiro en tienda</span>
											@else
												<span class="dp-tag-delivery">Domicilio</span>
											@endif
										</div>
										<div class="dp-order-price">{{ $money($ord['total_amount']) }}</div>
									</div>

									<div class="dp-order-customer">
										<span>{{ $ord['customer_name'] }}</span>
										<span><i class="fa-solid fa-box mr-1"></i>{{ $ord['packages'] ?: 1 }} bulto(s)</span>
									</div>

									<div class="dp-order-branch d-flex justify-content-between align-items-center">
										<span>{{ $ord['branch_name'] ?: 'Sin sucursal' }} · <small class="text-muted">{{ $ord['channel'] ?? 'POS' }}</small></span>
										<span class="badge {{ $ord['priority_class'] ?? 'badge-secondary' }} px-1.5 py-0.5" style="font-size: 0.68rem;">{{ $ord['priority'] ?? 'Normal' }}</span>
									</div>

									<div class="dp-order-bottom">
										<span class="dp-badge-status {{ $stClass }}">{{ $stLabel }}</span>
										<span class="dp-badge-notice {{ $noticeClass }}">
											<i class="fa-solid {{ $noticeIcon }}"></i>
											<span>{{ $noticeText }}</span>
										</span>
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>

				<!-- Panel Derecho: Detalle y Avance Operativo -->
				<div class="dp-detail-panel" id="dpDetailPanel">
					@if($selectedDetail)
						@php
							$so = $selectedDetail['order'];
							$isPickup = (($so['delivery_type'] ?? 'pickup') === 'pickup');
							$summary = $selectedDetail['summary'];
							$lines = $selectedDetail['lines'];
							$variances = $selectedDetail['variances'];
							$incidents = $selectedDetail['incidents'] ?? [];
							$stages = $so['stages'] ?? \Grocy\Services\RetailPreventionService::BuildOrderStages($so);
							$statusLabels = [
								'draft' => 'Pedido Nuevo',
								'confirmed' => 'Reservado',
								'picking' => 'En Picking',
								'picked' => 'Picking Validado',
								'packed' => 'Empacado / Etiquetado',
								'dispatched' => !empty($so['conciliated_at']) ? 'Conciliado' : (($so['delivery_status'] ?? '') === 'delivered' ? 'Entregado (POD)' : 'Despachado'),
								'cancelled' => 'Cancelado'
							];
						@endphp

						<!-- Encabezado del Detalle -->
						<div class="dp-detail-header">
							<div class="dp-detail-title-wrap">
								<div class="dp-detail-title-row">
									<span class="dp-detail-code" id="dpDetailCode">{{ $so['order_code'] }}</span>
									@if($isPickup)
										<span class="dp-tag-pickup" id="dpDetailTypeTag">Retiro en tienda</span>
									@else
										<span class="dp-tag-delivery" id="dpDetailTypeTag">Domicilio</span>
									@endif
									<span class="dp-badge-status st-packed" id="dpDetailStatusBadge">
										{{ $statusLabels[$so['status'] ?? 'draft'] ?? strtoupper($so['status'] ?? 'draft') }}
									</span>
								</div>
								<div class="dp-detail-meta" id="dpDetailMeta">
									Cliente: <strong class="text-dark">{{ $so['customer_name'] }}</strong> · Sucursal: <strong class="text-dark">{{ $so['branch_name'] ?: 'Matriz' }}</strong>
									@if(!empty($so['label_code']))
										· Etiqueta: <strong class="text-success">{{ $so['label_code'] }}</strong>
									@endif
								</div>
							</div>

							<div class="dp-detail-actions">
								@if(($so['status'] ?? '') === 'draft')
									<button type="button" class="dp-btn-green" id="btnStageReserve" onclick="reserveOrder({{ $so['id'] }})">
										<i class="fa-solid fa-lock"></i>
										<span>Reservar stock</span>
									</button>
								@endif

								@if(in_array($so['status'] ?? '', ['confirmed', 'picking', 'picked'], true))
									<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalFastScanVerify">
										<i class="fa-solid fa-barcode"></i>
										<span>Escanear UPC</span>
									</button>
									<button type="button" class="dp-btn-green" id="btnOpenPackModal" onclick="openPackOrderModal({{ $so['id'] }}, '{{ $so['order_code'] }}')">
										<i class="fa-solid fa-box-open"></i>
										<span>Empacar y Etiquetar</span>
									</button>
								@endif

								@if(($so['status'] ?? '') === 'packed')
									<button type="button" class="dp-btn-green" id="btnDetailConfirmExit" onclick="openConfirmExitModal({{ $so['id'] }}, '{{ $so['order_code'] }}')">
										<i class="fa-solid fa-truck-fast"></i>
										<span>Confirmar Salida</span>
									</button>
								@endif

								@if(($so['status'] ?? '') === 'dispatched')
									@if($isPickup)
										<button type="button" class="dp-btn-green" id="btnOpenVerifyPickupModal" data-toggle="modal" data-target="#modalVerifyPickup">
											<i class="fa-solid fa-key"></i>
											<span>Validar retiro PIN</span>
										</button>
									@else
										<button type="button" class="dp-btn-green" id="btnOpenDeliveryModal" data-toggle="modal" data-target="#modalRecordDelivery">
											<i class="fa-solid fa-truck-arrow-right"></i>
											<span>Registrar Entrega / POD</span>
										</button>
									@endif
								@endif

								<button type="button" class="dp-btn-outline" id="btnOpenLabelModal" onclick="openDispatchLabelModal({{ $so['id'] }})">
									<i class="fa-solid fa-tag"></i>
									<span>Etiqueta</span>
								</button>

								<button type="button" class="dp-btn-cyan" data-toggle="modal" data-target="#modalScheduleRoute" onclick="$('#schedOrderId').val({{ $so['id'] }});">
									<i class="fa-solid fa-truck"></i>
									<span>Transporte</span>
								</button>

								<button type="button" class="dp-btn-outline text-warning border-warning" id="btnOpenReportIncident" onclick="openReportIncidentModal({{ $so['id'] }}, '{{ $so['order_code'] }}')">
									<i class="fa-solid fa-triangle-exclamation"></i>
									<span>Incidencia</span>
								</button>

								@if(($so['status'] ?? '') === 'dispatched' && empty($so['conciliated_at']))
									<button type="button" class="dp-btn-outline font-weight-bold" id="btnConciliateOrder" onclick="conciliateOrder({{ $so['id'] }})">
										<i class="fa-solid fa-scale-balanced"></i>
										<span>Conciliar Cierre</span>
									</button>
								@endif
							</div>
						</div>

						<!-- Stepper: 7 Etapas Canónicas del Pedido -->
						<div class="dp-stepper-box">
							<div class="dp-stepper-title">
								<i class="fa-solid fa-route text-success"></i>
								<span>Avance del ciclo OMS/WMS (7 Etapas)</span>
							</div>

							<div class="dp-stepper-track" id="dpStepperTrack">
								@foreach($stages as $idx => $stgItem)
									<div class="dp-stepper-step {{ $stgItem['state'] === 'done' ? 'is-done' : ($stgItem['state'] === 'current' ? 'is-current' : '') }}">
										<div class="dp-step-circle">
											@if($stgItem['state'] === 'done')
												<i class="fa-solid fa-check"></i>
											@else
												{{ $idx + 1 }}
											@endif
										</div>
										<div class="dp-step-label">{{ $idx + 1 }}. {{ $stgItem['label'] }}<br><small>{{ $stgItem['sub'] }}</small></div>
									</div>
								@endforeach
							</div>
						</div>

						<!-- Sección: Conciliación por Producto -->
						<div class="dp-reconcile-wrap">
							<div class="dp-reconcile-header">
								<div class="dp-reconcile-title">
									<i class="fa-solid fa-scale-balanced text-success"></i>
									<span>Conciliación por producto (Matriz 7 Etapas)</span>
								</div>

								<div class="dp-toggle-wrap">
									<div class="custom-control custom-switch">
										<input type="checkbox" class="custom-control-input" id="toggle7Stages">
										<label class="custom-control-label" for="toggle7Stages">Ver 7 etapas</label>
									</div>
								</div>
							</div>

							<div class="dp-table-card">
								<table class="dp-reconcile-table" id="dpReconcileTable">
									<thead>
										<tr>
											<th style="min-width: 200px;">Producto</th>
											<th>Solicitada</th>
											<th>Preparada</th>
											<th>Escaneada</th>
											<th>Despachada</th>
											<th>Recibida</th>
											<th class="dp-col-7stage d-none">Devuelta</th>
											<th class="dp-col-7stage d-none">Dif. Final</th>
										</tr>
									</thead>
									<tbody>
										@foreach($lines as $l)
											@php
												$stg = $l['stages'];
												$isPrepDiff = ($stg['2_prepared'] < $stg['1_requested']);
												$isRecvZero = ($stg['5_received'] == 0 && $stg['1_requested'] > 0);
											@endphp
											<tr>
												<td>
													<div class="dp-product-cell">
														<img src="{{ $l['product_image'] }}" alt="{{ $l['product_name'] }}" class="dp-prod-img">
														<div>
															<div class="dp-prod-name">{{ $l['product_name'] }}</div>
															<div class="dp-prod-sku">SKU: {{ $l['sku'] }} · UPC: {{ $l['upc'] }}</div>
															@if(!empty($l['sku_warning']))
																<div class="dp-badge-sku-warn">
																	<i class="fa-solid fa-triangle-exclamation"></i>
																	<span>Revisar identificación</span>
																</div>
															@endif
														</div>
													</div>
												</td>
												<td class="dp-val-req">{{ $stg['1_requested'] }}</td>
												<td class="dp-val-prep @if($isPrepDiff) is-diff @endif">{{ $stg['2_prepared'] }}</td>
												<td class="dp-val-scan">{{ $stg['3_scanned'] }}</td>
												<td class="dp-val-disp">{{ $stg['4_dispatched'] }}</td>
												<td class="dp-val-recv @if($isRecvZero) is-zero @endif">{{ $stg['5_received'] }}</td>
												<td class="dp-col-7stage d-none">{{ $stg['6_returned'] }}</td>
												<td class="dp-col-7stage d-none @if($stg['7_final_difference'] != 0) text-danger font-weight-bold @endif">{{ $stg['7_final_difference'] }}</td>
											</tr>
										@endforeach

										<!-- Fila de Totales -->
										<tr class="dp-totals-row">
											<td>
												<div class="dp-totals-cell-left">TOTALES (Unidades)</div>
												<div class="dp-totals-cell-sub">Cantidades reportadas. Validar unidades.</div>
											</td>
											<td class="dp-val-req">{{ $summary['total_requested'] }}</td>
											<td class="dp-val-prep">{{ $summary['total_prepared'] }}</td>
											<td class="dp-val-scan">{{ $summary['total_scanned'] }}</td>
											<td class="dp-val-disp">{{ $summary['total_dispatched'] }}</td>
											<td class="dp-val-recv">{{ $summary['total_received'] }}</td>
											<td class="dp-col-7stage d-none">{{ $summary['total_returned'] }}</td>
											<td class="dp-col-7stage d-none">{{ $summary['total_final_diff'] }}</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<!-- Caja Ámbar: Cantidades por conciliar (si hay diferencias pendientes) -->
						@if(!empty($summary['has_unreconciled_shortage']))
							<div class="dp-alert-unreconciled" id="dpAlertUnreconciled">
								<div class="dp-alert-unreconciled-left">
									<i class="fa-solid fa-triangle-exclamation dp-alert-unreconciled-icon"></i>
									<div>
										<div class="dp-alert-unreconciled-title">Cantidades por conciliar</div>
										<div class="dp-alert-unreconciled-text">
											@foreach($summary['unreconciled_items'] as $ui)
												<strong>{{ $ui['product_name'] }}:</strong> {{ $ui['requested'] }} solicitadas / {{ $ui['prepared'] }} preparadas.<br>
											@endforeach
											<span class="text-muted">Verifica sustituciones, faltantes o cambios autorizados antes de cerrar.</span>
										</div>
									</div>
								</div>

								<button type="button" class="dp-btn-amber" data-toggle="modal" data-target="#modalResolveVariance">
									<i class="fa-solid fa-gavel"></i>
									<span>Revisar diferencias</span>
								</button>
							</div>
						@endif

						<!-- Pestañas de detalle inferior -->
						<div class="dp-tabs-bar">
							<a href="javascript:void(0)" class="dp-tab-item is-active" data-tab="detalle">Detalle</a>
							<a href="javascript:void(0)" class="dp-tab-item" data-tab="variaciones">Variaciones</a>
							<a href="javascript:void(0)" class="dp-tab-item" data-tab="evidencia">Evidencia</a>
							<a href="javascript:void(0)" class="dp-tab-item" data-tab="historial">Historial</a>
						</div>

						<!-- Panes de Pestañas -->
						<div class="dp-tab-content">
							<!-- Pestaña Detalle -->
							<div class="dp-tab-pane" id="pane-detalle">
								<div class="dp-info-notice">
									<i class="fa-solid fa-circle-info"></i>
									<div>
										<strong>Trazabilidad Logística OMS/WMS:</strong>
										<div class="text-muted small">
											Pedido <strong class="text-dark">{{ $so['order_code'] }}</strong> · Canal: {{ $so['channel'] ?? 'POS' }} · Etiqueta: <strong>{{ $so['label_code'] ?: 'Pendiente Packing' }}</strong> · Bultos: <strong>{{ max(1, intval($so['packages_count'] ?? ($so['packages'] ?? 1))) }}</strong>.
										</div>
									</div>
								</div>
							</div>

							<!-- Pestaña Variaciones -->
							<div class="dp-tab-pane d-none" id="pane-variaciones">
								@if(empty($variances) && empty($incidents))
									<div class="p-3 bg-light rounded text-center text-muted small">
										Sin variaciones ni incidencias logísticas registradas en este pedido.
									</div>
								@else
									@foreach($variances as $v)
										<div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center">
											<div>
												<strong class="text-dark">{{ $v['product_name'] }}</strong>
												<span class="badge @if($v['stage'] === 'preparation') badge-warning @else badge-danger @endif ml-1">{{ $v['variance_type'] }}</span>
												<span class="text-muted ml-2">Diferencia: <strong>{{ $v['difference_qty'] }}</strong></span>
												<div class="small text-muted">{{ $v['resolution_notes'] ?: 'Pendiente autorización' }}</div>
											</div>
											<div>
												@if($v['resolution_status'] === 'pending')
													<button class="btn btn-xs btn-danger font-weight-bold" onclick="openResolveVarianceModal({{ $v['id'] }}, '{{ addslashes($v['product_name']) }}', '{{ $v['difference_qty'] }}')">
														Resolver
													</button>
												@else
													<span class="badge badge-success">{{ $v['resolution_status'] }}</span>
												@endif
											</div>
										</div>
									@endforeach
									@foreach($incidents as $inc)
										<div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center">
											<div>
												<span class="badge badge-warning mr-1">{{ $inc['incident_type'] }}</span>
												<strong class="text-dark">{{ $inc['description'] }}</strong>
												<div class="small text-muted">{{ $inc['created_at'] }} @if(!empty($inc['resolution_notes'])) · Resolución: {{ $inc['resolution_notes'] }} @endif</div>
											</div>
											<div>
												@if(($inc['resolution_status'] ?? 'open') === 'open')
													<button class="btn btn-xs btn-warning font-weight-bold" onclick="openResolveIncidentModal({{ $inc['id'] }}, '{{ addslashes($inc['description']) }}')">
														Cerrar Incidencia
													</button>
												@else
													<span class="badge badge-success">Resuelta</span>
												@endif
											</div>
										</div>
									@endforeach
								@endif
							</div>

							<!-- Pestaña Evidencia -->
							<div class="dp-tab-pane d-none" id="pane-evidencia">
								<div class="card p-3 border-0 bg-light" id="dpDetailEvidenceCard">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<strong class="text-dark"><i class="fa-solid fa-file-signature text-success mr-1"></i>Prueba de Entrega (POD) y Cadena de Frío</strong>
										@if(!empty($so['cold_chain_temp_ok']))
											<span class="badge badge-success"><i class="fa-solid fa-temperature-arrow-down mr-1"></i>Frío Verificado (≤ 4°C)</span>
										@else
											<span class="badge badge-secondary"><i class="fa-solid fa-snowflake mr-1"></i>Por verificar en entrega</span>
										@endif
									</div>
									<p class="small text-muted mb-1">Transporte / Guía: <strong>{{ $so['carrier_name'] ?: 'Sin asignar' }}</strong> · <span>{{ $so['carrier_tracking_number'] ?: 'Sin guía' }}</span></p>
									<p class="small text-muted mb-1">Firma / Receptor: <strong>{{ $so['delivery_signature_name'] ?: 'Sin captura POD aún' }}</strong></p>
									<p class="small text-muted mb-0">Folio / Evidencia POD: <strong>{{ $so['delivery_evidence_url'] ?: 'Pendiente de evidencia' }}</strong></p>
								</div>
							</div>

							<!-- Pestaña Historial -->
							<div class="dp-tab-pane d-none" id="pane-historial">
								<ul class="list-unstyled small text-muted mb-0">
									<li class="mb-1"><i class="fa-solid fa-file-circle-plus mr-1 text-primary"></i>1. Pedido registrado: <strong>{{ $so['created_at'] }}</strong></li>
									<li class="mb-1"><i class="fa-solid fa-lock mr-1 text-info"></i>2. Reserva confirmada: <strong>{{ $so['confirmed_at'] ?: 'Pendiente' }}</strong></li>
									<li class="mb-1"><i class="fa-solid fa-barcode mr-1 text-success"></i>3. Picking / Escaneo: <strong>{{ $so['picked_at'] ?: 'Pendiente' }}</strong></li>
									<li class="mb-1"><i class="fa-solid fa-box-open mr-1 text-success"></i>4. Packing & Etiqueta: <strong>{{ $so['packed_at'] ?: 'Pendiente' }}</strong> @if(!empty($so['label_code'])) ({{ $so['label_code'] }}) @endif</li>
									<li class="mb-1"><i class="fa-solid fa-truck mr-1 text-primary"></i>5. Salida muelle: <strong>{{ $so['dispatched_at'] ?: 'Pendiente' }}</strong></li>
									<li class="mb-1"><i class="fa-solid fa-signature mr-1 text-success"></i>6. Entrega POD: <strong>{{ $so['delivery_time'] ?: ($so['pickup_verified_at'] ?: 'Pendiente') }}</strong></li>
									<li><i class="fa-solid fa-scale-balanced mr-1 text-dark"></i>7. Conciliación final: <strong>{{ $so['conciliated_at'] ?: 'Pendiente de cierre' }}</strong></li>
								</ul>
							</div>
						</div>

					@else
						<div class="p-5 text-center text-muted">
							<i class="fa-solid fa-truck-ramp-box fa-3x mb-3 text-muted" style="opacity: 0.4;"></i>
							<h5>Seleccione un pedido del panel izquierdo</h5>
							<p class="small mb-0">Podrá conciliar las cantidades línea por línea, validar retiros de tienda o registrar entregas de ruta.</p>
						</div>
					@endif
				</div>

			</div>
		</div>

		<!-- ================= TAB 2: PEDIDOS Y PRIORIDADES ================= -->
		<div class="tab-pane fade" id="tab-pedidos-prioridades" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-list-check text-success"></i> Pedidos, Prioridades y Fechas Comprometidas</h3>
						<p class="dp-section-sub">Consolidación de órdenes generadas desde POS, Tiendas, E-Commerce y Almacén B2B.</p>
					</div>
					<div class="d-flex gap-2">
						<button class="btn btn-sm btn-outline-success font-weight-bold" onclick="$('#tab-picking-link').click();">
							<i class="fa-solid fa-boxes-packing mr-1"></i> Ir a Picking
						</button>
						<button class="btn btn-sm btn-outline-primary font-weight-bold" data-toggle="modal" data-target="#modalScheduleRoute">
							<i class="fa-solid fa-route mr-1"></i> Programar Transporte
						</button>
					</div>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" id="tableOrdersAll" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Código Pedido</th>
								<th>Canal Origen</th>
								<th>Cliente</th>
								<th>Sucursal</th>
								<th>Fecha Comprometida</th>
								<th>Prioridad</th>
								<th>Modalidad</th>
								<th>Total ($)</th>
								<th>Bultos</th>
								<th>Estado</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach($orders as $ord)
								<tr>
									<td>
										<a href="javascript:void(0)" onclick="selectOrderAndSwitch({{ $ord['id'] }})" class="font-weight-bold text-dark">
											{{ $ord['order_code'] }}
										</a>
									</td>
									<td>
										<span class="badge badge-light border text-muted">
											<i class="fa-solid fa-store mr-1"></i>{{ $ord['channel'] ?? 'POS' }}
										</span>
									</td>
									<td><strong class="text-dark">{{ $ord['customer_name'] }}</strong></td>
									<td>{{ $ord['branch_name'] ?: 'Matriz' }}</td>
									<td>
										@php
											$isOverdue = (!empty($ord['requested_date']) && $ord['requested_date'] < date('Y-m-d') && !in_array($ord['status'], ['dispatched', 'delivered']));
										@endphp
										<span class="@if($isOverdue) text-danger font-weight-bold @endif">
											{{ !empty($ord['requested_date']) ? date('d/m/Y', strtotime($ord['requested_date'])) : 'Inmediata' }}
											@if($isOverdue) <i class="fa-solid fa-triangle-exclamation ml-1" title="Vencido"></i> @endif
										</span>
									</td>
									<td>
										<span class="badge {{ $ord['priority_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['priority'] ?? 'Normal' }}
										</span>
									</td>
									<td>
										@if(($ord['delivery_type'] ?? 'pickup') === 'pickup')
											<span class="badge badge-light border text-primary"><i class="fa-solid fa-store mr-1"></i>Retiro</span>
										@else
											<span class="badge badge-light border text-info"><i class="fa-solid fa-truck mr-1"></i>Domicilio</span>
										@endif
									</td>
									<td class="font-weight-bold">{{ $money($ord['total_amount']) }}</td>
									<td>{{ max(1, intval($ord['packages_count'] ?? ($ord['packages'] ?? 1))) }}</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['tracking_status'] ?? 'Pendiente' }}
										</span>
									</td>
									<td class="text-right text-nowrap">
										@if(($ord['status'] ?? '') === 'draft')
											<button class="btn btn-xs btn-outline-success mr-1" title="Reservar Stock" onclick="reserveOrder({{ $ord['id'] }})">
												<i class="fa-solid fa-lock"></i>
											</button>
										@endif
										@if(in_array($ord['status'] ?? '', ['confirmed', 'picking'], true))
											<button class="btn btn-xs btn-outline-success mr-1" title="Asignar Picking" onclick="openAssignPickingModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
												<i class="fa-solid fa-boxes-packing"></i>
											</button>
										@endif
										<button class="btn btn-xs btn-outline-primary mr-1" title="Programar Transporte" onclick="openScheduleTransportModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
											<i class="fa-solid fa-truck"></i>
										</button>
										<button class="btn btn-xs btn-light border" title="Ver Detalle" onclick="selectOrderAndSwitch({{ $ord['id'] }})">
											<i class="fa-solid fa-eye"></i>
										</button>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 3: PREPARACIÓN, PICKING Y PACKING ================= -->
		<div class="tab-pane fade" id="tab-preparacion-picking" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-boxes-packing text-success"></i> Estación de Preparación, Picking, Packing y Etiquetas</h3>
						<p class="dp-section-sub">Reserva de mercancía, picking con escáner UPC, validación de cantidades, empaque de bultos e impresión de etiquetas.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-success font-weight-bold" data-toggle="modal" data-target="#modalFastScanVerify">
							<i class="fa-solid fa-barcode mr-1"></i> Escanear Código UPC
						</button>
					</div>
				</div>

				<div class="row mb-3">
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">En cola Reserva/Picking</span>
							<h4 class="font-weight-bold text-warning mb-0">{{ intval($kpis['pending_orders'] ?? 0) + intval($kpis['preparing_orders'] ?? 0) }}</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Listos en Packing</span>
							<h4 class="font-weight-bold text-success mb-0">{{ intval($kpis['ready_orders'] ?? 0) }}</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Exactitud Picking</span>
							<h4 class="font-weight-bold text-primary mb-0">{{ $prepAcc !== null ? number_format($prepAcc, 1) . '%' : 'Sin info' }}</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Tiempo Promedio</span>
							<h4 class="font-weight-bold text-dark mb-0">{{ $kpis['prep_time_minutes'] ?? 'Sin información' }}</h4>
						</div>
					</div>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Pedido</th>
								<th>Cliente</th>
								<th>Progreso Picking</th>
								<th>Etiqueta Despacho</th>
								<th>Bultos</th>
								<th>Estado</th>
								<th class="text-right">Acciones Etapa</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 12) as $ord)
								@php
									$totU = max(1, floatval($ord['total_units'] ?? 1));
									$picU = floatval($ord['total_picked_units'] ?? 0);
									$pctPick = min(100, round(($picU / $totU) * 100));
								@endphp
								<tr>
									<td><a href="javascript:void(0)" onclick="selectOrderAndSwitch({{ $ord['id'] }})" class="font-weight-bold text-dark">{{ $ord['order_code'] }}</a></td>
									<td>{{ $ord['customer_name'] }}</td>
									<td>
										<div class="d-flex align-items-center gap-2">
											<div class="progress flex-grow-1" style="height: 10px; border-radius: 5px; min-width: 80px;">
												<div class="progress-bar bg-success" role="progressbar" style="width: {{ $pctPick }}%;"></div>
											</div>
											<span class="small font-weight-bold">{{ $pctPick }}%</span>
										</div>
									</td>
									<td>
										@if(!empty($ord['label_code']))
											<span class="badge badge-light border text-success font-weight-bold">{{ $ord['label_code'] }}</span>
										@else
											<span class="text-muted small">Sin etiqueta</span>
										@endif
									</td>
									<td><strong>{{ max(1, intval($ord['packages_count'] ?? ($ord['packages'] ?? 1))) }}</strong> bulto(s)</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['tracking_status'] }}
										</span>
									</td>
									<td class="text-right text-nowrap">
										@if(($ord['status'] ?? '') === 'draft')
											<button class="btn btn-xs btn-outline-success mr-1" onclick="reserveOrder({{ $ord['id'] }})">Reservar</button>
										@endif
										@if(in_array($ord['status'] ?? '', ['confirmed', 'picking'], true))
											<button class="btn btn-xs btn-outline-success mr-1" onclick="openAssignPickingModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">Asignar</button>
											<button class="btn btn-xs btn-outline-primary mr-1" onclick="window.SelectOrder && window.SelectOrder({{ $ord['id'] }}); $('#modalFastScanVerify').modal('show');">UPC</button>
										@endif
										@if(in_array($ord['status'] ?? '', ['picking', 'picked'], true))
											<button class="btn btn-xs btn-success font-weight-bold mr-1" onclick="openPackOrderModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">Empacar</button>
										@endif
										<button class="btn btn-xs btn-light border" onclick="openDispatchLabelModal({{ $ord['id'] }})" title="Ver Etiqueta">
											<i class="fa-solid fa-tag"></i>
										</button>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 4: PROGRAMACIÓN Y TRANSPORTE ================= -->
		<div class="tab-pane fade" id="tab-programacion-rutas" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-route text-success"></i> Programación de Transporte, Flota y Rutas</h3>
						<p class="dp-section-sub">Asignación de vehículos, conductores, capacidad en volumen/peso y ventanas horarias de entrega.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-primary font-weight-bold" data-toggle="modal" data-target="#modalScheduleRoute">
							<i class="fa-solid fa-calendar-plus mr-1"></i> Nueva Asignación de Ruta
						</button>
					</div>
				</div>

				<!-- Tarjetas de Unidades de Transporte -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-truck text-muted mr-1"></i> Unidades de Transporte Activas</h6>
				<div class="row mb-4">
					@foreach($vehicles as $vh)
						<div class="col-xl-3 col-md-6 col-12 mb-3">
							<div class="dp-fleet-card">
								<div class="d-flex justify-content-between align-items-start mb-2">
									<div>
										<strong class="text-dark d-block" style="font-size: 0.9rem;">{{ $vh['name'] }}</strong>
										<span class="badge badge-light border text-muted">Placa: {{ $vh['plate'] }}</span>
									</div>
									<span class="badge badge-success px-2 py-0.5">En servicio</span>
								</div>
								<div class="small text-muted mb-2">
									<div><i class="fa-solid fa-weight-hanging mr-1"></i>Capacidad: <strong>{{ number_format($vh['capacity_kg']) }} kg</strong> / {{ $vh['volume_m3'] }} m³</div>
									<div><i class="fa-solid fa-snowflake mr-1 text-info"></i>{{ $vh['temp'] }}</div>
								</div>
								<div class="d-flex justify-content-between align-items-center pt-2 border-top">
									<span class="small font-weight-bold text-success"><i class="fa-solid fa-circle-check mr-1"></i>Checklist salida OK</span>
									<button class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#modalScheduleRoute">Programar</button>
								</div>
							</div>
						</div>
					@endforeach
				</div>

				<!-- Programación de Rutas Reales del Día -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-clock mr-1 text-muted"></i> Rutas Activas y Pedidos Asignados</h6>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Ruta & Zona</th>
								<th>Vehículo Asignado</th>
								<th>Conductor Responsable</th>
								<th>Bultos Totales</th>
								<th>Pedidos en Ruta</th>
								<th>Entregados</th>
								<th>Estado Ruta</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@forelse($routesSummary as $rt)
								<tr>
									<td><strong>{{ $rt['route_name'] }}</strong></td>
									<td>{{ $rt['carrier_vehicle'] }}</td>
									<td>{{ $rt['carrier_name'] }}</td>
									<td>{{ $rt['packages'] }} bultos</td>
									<td><strong>{{ $rt['orders_count'] }} pedidos</strong></td>
									<td><strong>{{ $rt['delivered_count'] }}</strong> / {{ $rt['orders_count'] }}</td>
									<td>
										@if($rt['delivered_count'] === $rt['orders_count'] && $rt['orders_count'] > 0)
											<span class="badge badge-success px-2 py-0.5">Completada</span>
										@elseif($rt['in_transit_count'] > 0)
											<span class="badge badge-info px-2 py-0.5">En tránsito ({{ $rt['in_transit_count'] }})</span>
										@else
											<span class="badge badge-secondary px-2 py-0.5">Programada</span>
										@endif
									</td>
									<td class="text-right"><button class="btn btn-xs btn-light border" onclick="$('#tab-salidas-link').click();">Ver Salidas</button></td>
								</tr>
							@empty
								<tr>
									<td colspan="8" class="text-center text-muted py-3">No hay despachos a domicilio registrados para rutas activas.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 5: SALIDAS Y CARGA ================= -->
		<div class="tab-pane fade" id="tab-salidas-documentacion" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-clipboard-check text-success"></i> Salidas de Bodega, Confirmación de Carga y Etiquetas</h3>
						<p class="dp-section-sub">Control físico de bultos en muelle, validación de cantidad ≤ reservada, etiqueta de despacho y guía de salida.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-success font-weight-bold" data-toggle="modal" data-target="#modalConfirmExit">
							<i class="fa-solid fa-truck-fast mr-1"></i> Confirmar Salida de Carga
						</button>
					</div>
				</div>

				<div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-3 rounded" style="border-left: 4px solid #059669 !important;">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-circle-check text-success fa-2x mr-3"></i>
						<div>
							<strong class="text-dark">Control de Salida en Muelle:</strong>
							<div class="small text-muted">Solo se autorizan pedidos en Packing (bultos sellados y cantidad preparada ≤ reservada).</div>
						</div>
					</div>
					<span class="badge badge-success px-2.5 py-1">Protocolo Activo</span>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Nº Despacho / Guía</th>
								<th>Pedido</th>
								<th>Etiqueta</th>
								<th>Destino & Cliente</th>
								<th>Transporte / Placa</th>
								<th>Bultos</th>
								<th>Salida Real</th>
								<th>Estado Salida</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 12) as $ord)
								<tr>
									<td><strong class="text-primary">{{ $ord['carrier_tracking_number'] ?: 'Pendiente salida' }}</strong></td>
									<td><a href="javascript:void(0)" onclick="selectOrderAndSwitch({{ $ord['id'] }})" class="badge badge-light border font-weight-bold text-dark">{{ $ord['order_code'] }}</a></td>
									<td><span class="small font-weight-bold text-success">{{ $ord['label_code'] ?: 'Sin etiqueta' }}</span></td>
									<td><strong>{{ $ord['customer_name'] }}</strong></td>
									<td>{{ $ord['carrier_vehicle'] ?: (($ord['delivery_type'] ?? 'pickup') === 'pickup' ? 'Mostrador Retiro' : 'Sin vehículo asignado') }}</td>
									<td>{{ max(1, intval($ord['packages_count'] ?? ($ord['packages'] ?? 1))) }}</td>
									<td>{{ !empty($ord['departure_time']) ? date('d/m H:i', strtotime($ord['departure_time'])) : 'Pendiente' }}</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['tracking_status'] }}
										</span>
									</td>
									<td class="text-right text-nowrap">
										<button class="btn btn-xs btn-outline-secondary mr-1" onclick="openDispatchLabelModal({{ $ord['id'] }})" title="Etiqueta">
											<i class="fa-solid fa-tag"></i>
										</button>
										@if(($ord['status'] ?? '') === 'packed')
											<button class="btn btn-xs btn-success font-weight-bold" onclick="openConfirmExitModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
												<i class="fa-solid fa-check mr-1"></i>Autorizar Salida
											</button>
										@elseif(($ord['status'] ?? '') === 'dispatched')
											<button class="btn btn-xs btn-outline-info" onclick="openEvidenceModal({{ $ord['id'] }})">
												<i class="fa-solid fa-file-invoice mr-1"></i>POD
											</button>
										@else
											<span class="small text-muted">Completar Packing</span>
										@endif
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 6: ENTREGAS Y EVIDENCIAS (POD OBLIGATORIA) ================= -->
		<div class="tab-pane fade" id="tab-entregas-evidencias" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-signature text-success"></i> Confirmación de Entrega, POD Obligatoria, Parciales y Rechazos</h3>
						<p class="dp-section-sub">Auditoría de recepción real: firmas digitales, folio/evidencia POD, verificación PIN/QR en tienda y entregas parciales.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-outline-success font-weight-bold" onclick="openEvidenceModal(window.CurrentOrderId)">
							<i class="fa-solid fa-file-invoice mr-1"></i> Consultar POD Pedido Activo
						</button>
					</div>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Pedido</th>
								<th>Modalidad</th>
								<th>Fecha & Hora Entrega</th>
								<th>Receptor POD</th>
								<th>Validación / PIN</th>
								<th>Evidencia / Folio POD</th>
								<th>Cadena de Frío</th>
								<th>Resultado</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 12) as $ord)
								@php
									$isPickup = (($ord['delivery_type'] ?? 'pickup') === 'pickup');
									$delSt = $ord['delivery_status'] ?? 'pending';
									$hasPod = !empty($ord['delivery_signature_name']) || !empty($ord['pickup_verified_at']);
								@endphp
								<tr>
									<td><a href="javascript:void(0)" onclick="selectOrderAndSwitch({{ $ord['id'] }})" class="font-weight-bold text-dark">{{ $ord['order_code'] }}</a></td>
									<td>
										@if($isPickup)
											<span class="badge badge-light border text-primary">Retiro en tienda</span>
										@else
											<span class="badge badge-light border text-info">Domicilio</span>
										@endif
									</td>
									<td>{{ !empty($ord['delivery_time']) ? date('d/m/Y H:i', strtotime($ord['delivery_time'])) : (!empty($ord['pickup_verified_at']) ? date('d/m/Y H:i', strtotime($ord['pickup_verified_at'])) : 'Pendiente') }}</td>
									<td><strong>{{ $ord['delivery_signature_name'] ?: 'Sin firma POD' }}</strong></td>
									<td>
										@if($isPickup)
											@if(!empty($ord['pickup_pin']))
												<span class="badge {{ !empty($ord['pickup_verified_at']) ? 'badge-success' : 'badge-light border' }}"><i class="fa-solid fa-key mr-1"></i>PIN: {{ $ord['pickup_pin'] }}</span>
											@else
												<span class="text-muted small">Sin PIN</span>
											@endif
										@else
											<span class="badge {{ !empty($ord['delivery_signature_name']) ? 'badge-success' : 'badge-light border' }}"><i class="fa-solid fa-signature mr-1"></i>{{ !empty($ord['delivery_signature_name']) ? 'Firmado' : 'Pendiente' }}</span>
										@endif
									</td>
									<td>
										@if(!empty($ord['delivery_evidence_url']))
											<a href="javascript:void(0)" onclick="openEvidenceModal({{ $ord['id'] }})" class="badge badge-info">
												<i class="fa-solid fa-file-shield mr-1"></i>{{ $ord['delivery_evidence_url'] }}
											</a>
										@else
											<span class="badge badge-light border text-muted">Sin evidencia POD</span>
										@endif
									</td>
									<td>
										@if(!empty($ord['cold_chain_temp_ok']))
											<span class="text-success small font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i>Frío OK</span>
										@else
											<span class="text-muted small">Sin verificar</span>
										@endif
									</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">{{ $ord['tracking_status'] }}</span>
									</td>
									<td class="text-right text-nowrap">
										@if(($ord['status'] ?? '') === 'dispatched' && !in_array($delSt, ['delivered', 'partial', 'rejected'], true))
											@if($isPickup)
												<button class="btn btn-xs btn-success font-weight-bold mr-1" onclick="window.SelectOrder && window.SelectOrder({{ $ord['id'] }}); $('#modalVerifyPickup').modal('show');">PIN</button>
											@else
												<button class="btn btn-xs btn-success font-weight-bold mr-1" onclick="window.SelectOrder && window.SelectOrder({{ $ord['id'] }}); $('#modalRecordDelivery').modal('show');">Registrar POD</button>
											@endif
										@endif
										<button class="btn btn-xs btn-light border font-weight-bold" onclick="openEvidenceModal({{ $ord['id'] }})">
											<i class="fa-solid fa-file-invoice mr-1"></i>POD
										</button>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 7: CONCILIACIÓN, VARIACIONES E INCIDENCIAS LOGÍSTICAS ================= -->
		<div class="tab-pane fade" id="tab-conciliacion-variaciones" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-scale-balanced text-success"></i> Conciliación Final, Variaciones e Incidencias Logísticas</h3>
						<p class="dp-section-sub">Cierre controlado tras validar cantidades solicitadas, reservadas, preparadas, despachadas, entregadas y POD obligatoria.</p>
					</div>
					<div class="d-flex gap-2">
						<button class="btn btn-sm btn-warning font-weight-bold" onclick="openReportIncidentModal(window.CurrentOrderId, $('#dpDetailCode').text())">
							<i class="fa-solid fa-triangle-exclamation mr-1"></i> Reportar Incidencia
						</button>
						<button class="btn btn-sm btn-danger font-weight-bold" data-toggle="modal" data-target="#modalResolveVariance">
							<i class="fa-solid fa-gavel mr-1"></i> Resolver Variación
						</button>
					</div>
				</div>

				<div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-3 rounded" style="border-left: 4px solid #2563eb !important;">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-shield-halved text-primary fa-2x mr-3"></i>
						<div>
							<strong class="text-dark">Regla de Cierre Logístico Obligatorio:</strong>
							<div class="small text-muted">Ningún despacho se concilia sin Prueba de Entrega (POD / PIN) ni con diferencias o incidencias abiertas pendientes de dictamen.</div>
						</div>
					</div>
					<span class="badge badge-primary px-2.5 py-1">Auditoría 7 Etapas</span>
				</div>

				<!-- Matriz de las 7 Cantidades -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-table-list text-muted mr-1"></i> Matriz de Conciliación por Pedido</h6>
				<div class="table-responsive bg-white rounded border mb-4">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Pedido</th>
								<th>Cliente</th>
								<th>1. Solicitada</th>
								<th>2. Preparada</th>
								<th>3. Escaneada</th>
								<th>4. Despachada</th>
								<th>5. Recibida</th>
								<th>6. Devuelta</th>
								<th>7. Dif. Final</th>
								<th>POD</th>
								<th class="text-right">Dictamen / Cierre</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 12) as $ord)
								@php
									$d = $ordersDetailMap[$ord['id']]['summary'] ?? null;
									$hasPod = !empty($ord['delivery_signature_name']) || !empty($ord['pickup_verified_at']);
								@endphp
								@if($d)
									<tr>
										<td><a href="javascript:void(0)" onclick="selectOrderAndSwitch({{ $ord['id'] }})" class="font-weight-bold text-dark">{{ $ord['order_code'] }}</a></td>
										<td>{{ $ord['customer_name'] }}</td>
										<td class="font-weight-bold">{{ $d['total_requested'] }}</td>
										<td>{{ $d['total_prepared'] }}</td>
										<td>{{ $d['total_scanned'] }}</td>
										<td>{{ $d['total_dispatched'] }}</td>
										<td class="font-weight-bold">{{ $d['total_received'] }}</td>
										<td>{{ $d['total_returned'] }}</td>
										<td class="@if($d['total_final_diff'] != 0) text-danger font-weight-bold @else text-success @endif">
											{{ $d['total_final_diff'] }}
										</td>
										<td>
											@if($hasPod)
												<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>POD</span>
											@else
												<span class="badge badge-light border text-muted">Sin POD</span>
											@endif
										</td>
										<td class="text-right text-nowrap">
											@if(intval($ord['pending_variances_count']) > 0)
												<button class="btn btn-xs btn-danger font-weight-bold" data-toggle="modal" data-target="#modalResolveVariance">
													Variación ({{ $ord['pending_variances_count'] }})
												</button>
											@elseif(!empty($ord['conciliated_at']))
												<span class="badge badge-success"><i class="fa-solid fa-check-double mr-1"></i>Conciliado</span>
											@elseif(($ord['status'] ?? '') === 'dispatched')
												<button class="btn btn-xs btn-outline-success font-weight-bold" onclick="conciliateOrder({{ $ord['id'] }})">
													Conciliar Cierre
												</button>
											@else
												<span class="badge badge-light border text-muted">{{ $ord['tracking_status'] }}</span>
											@endif
										</td>
									</tr>
								@endif
							@endforeach
						</tbody>
					</table>
				</div>

				<!-- Incidencias Logísticas -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-triangle-exclamation text-warning mr-1"></i> Registro de Incidencias Logísticas</h6>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>#</th>
								<th>Pedido</th>
								<th>Cliente</th>
								<th>Tipo</th>
								<th>Severidad</th>
								<th>Detalle</th>
								<th>Estado</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@forelse($openIncidents as $inc)
								<tr>
									<td>#{{ $inc['id'] }}</td>
									<td><strong>{{ $inc['order_code'] }}</strong></td>
									<td>{{ $inc['customer_name'] ?: '-' }}</td>
									<td><span class="badge badge-light border">{{ $inc['incident_type'] }}</span></td>
									<td>
										<span class="badge @if(in_array($inc['severity'], ['high','critical'])) badge-danger @elseif($inc['severity']==='medium') badge-warning @else badge-secondary @endif">{{ $inc['severity'] }}</span>
									</td>
									<td>
										<div>{{ $inc['description'] }}</div>
										@if(!empty($inc['resolution_notes']))
											<small class="text-success">Resolución: {{ $inc['resolution_notes'] }}</small>
										@endif
									</td>
									<td>
										@if(($inc['resolution_status'] ?? 'open') === 'open')
											<span class="badge badge-warning">Abierta</span>
										@else
											<span class="badge badge-success">Resuelta</span>
										@endif
									</td>
									<td class="text-right">
										@if(($inc['resolution_status'] ?? 'open') === 'open')
											<button class="btn btn-xs btn-outline-success font-weight-bold" onclick="openResolveIncidentModal({{ $inc['id'] }}, '{{ addslashes($inc['description']) }}')">
												Resolver
											</button>
										@else
											<span class="small text-muted">{{ $inc['resolved_at'] }}</span>
										@endif
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="8" class="text-center text-muted py-3">Sin incidencias logísticas registradas.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

	</div>

	<!-- ================= 5. TARJETAS OPERATIVAS INFERIORES ================= -->
	<div class="dp-bottom-grid">
		<!-- Tarjeta 1: Etiquetas de despacho -->
		<a href="javascript:void(0)" class="dp-bottom-card" onclick="openDispatchLabelModal(window.CurrentOrderId)">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-tag"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Etiquetas de despacho</h6>
						<p class="dp-bottom-card-desc">Genera e imprime rótulos de envío con bultos, ruta y código de barras.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/dispatch_card_boxes.jpg" alt="Etiquetas de despacho" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Imprimir etiqueta</span>
				<i class="fa-solid fa-arrow-right"></i>
			</div>
		</a>

		<!-- Tarjeta 2: Comprobante de entrega -->
		<a href="javascript:void(0)" class="dp-bottom-card" onclick="openEvidenceModal(window.CurrentOrderId)">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-file-invoice"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Comprobante de entrega (POD)</h6>
						<p class="dp-bottom-card-desc">Verifica receptor, folio de evidencia y validación PIN/QR del pedido.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/dispatch_card_doc.jpg" alt="Comprobante de entrega" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Consultar POD</span>
				<i class="fa-solid fa-arrow-right"></i>
			</div>
		</a>

		<!-- Tarjeta 3: Conciliación e Incidencias -->
		<a href="javascript:void(0)" class="dp-bottom-card" onclick="$('#tab-conciliacion-link').click();">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-scale-balanced"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Conciliación logística</h6>
						<p class="dp-bottom-card-desc">Audita las 7 etapas, incidencias en ruta y cierre final de pedidos.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/transfer_truck_hero.jpg" alt="Conciliación" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Ir a Conciliación</span>
				<i class="fa-solid fa-arrow-right"></i>
			</div>
		</a>
	</div>

</div>

<!-- ================= MODAL: ANALÍTICA COMPARATIVA PICKUP VS DOMICILIO ================= -->
<div class="modal fade" id="modalComparativeAnalytics" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-chart-pie mr-2 text-info"></i>Comparación Analítica Real: Retiro en Tienda vs. Domicilio</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<p class="text-muted small">Métricas calculadas en tiempo real sobre pedidos efectivos, preparación, POD y tiempo entre Reserva y Packing.</p>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Métrica Operativa</th>
								<th class="text-center">Retiro en Tienda ({{ intval($analytics['pickup']['orders_count'] ?? 0) }})</th>
								<th class="text-center">Domicilio ({{ intval($analytics['delivery']['orders_count'] ?? 0) }})</th>
								<th class="text-center">Meta OMS</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>OTIF Real (A tiempo y completo)</strong></td>
								<td class="text-center font-weight-bold text-primary">{{ $analytics['pickup']['otif_pct'] !== null ? number_format($analytics['pickup']['otif_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center font-weight-bold text-primary">{{ $analytics['delivery']['otif_pct'] !== null ? number_format($analytics['delivery']['otif_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center text-muted">≥ 95.0%</td>
							</tr>
							<tr>
								<td><strong>Exactitud de Preparación</strong></td>
								<td class="text-center font-weight-bold text-success">{{ $analytics['pickup']['prep_accuracy_pct'] !== null ? number_format($analytics['pickup']['prep_accuracy_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center font-weight-bold text-success">{{ $analytics['delivery']['prep_accuracy_pct'] !== null ? number_format($analytics['delivery']['prep_accuracy_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center text-muted">≥ 99.0%</td>
							</tr>
							<tr>
								<td><strong>Exactitud de Entrega</strong></td>
								<td class="text-center font-weight-bold text-primary">{{ $analytics['pickup']['delivery_accuracy_pct'] !== null ? number_format($analytics['pickup']['delivery_accuracy_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center font-weight-bold text-primary">{{ $analytics['delivery']['delivery_accuracy_pct'] !== null ? number_format($analytics['delivery']['delivery_accuracy_pct'], 1).'%' : 'Sin info' }}</td>
								<td class="text-center text-muted">≥ 98.0%</td>
							</tr>
							<tr>
								<td><strong>Tiempo Promedio de Preparación</strong></td>
								<td class="text-center font-weight-bold">{{ $analytics['pickup']['avg_validation_minutes'] !== null ? $analytics['pickup']['avg_validation_minutes'].' min' : 'Sin info' }}</td>
								<td class="text-center font-weight-bold">{{ $analytics['delivery']['avg_validation_minutes'] !== null ? $analytics['delivery']['avg_validation_minutes'].' min' : 'Sin info' }}</td>
								<td class="text-center text-muted">≤ 30 min</td>
							</tr>
							<tr>
								<td><strong>Faltantes por 1.000 un.</strong></td>
								<td class="text-center font-weight-bold text-warning">{{ number_format($analytics['pickup']['missing_per_thousand'] ?? 0, 1) }} un.</td>
								<td class="text-center font-weight-bold text-warning">{{ number_format($analytics['delivery']['missing_per_thousand'] ?? 0, 1) }} un.</td>
								<td class="text-center text-muted">≤ 2.0 un.</td>
							</tr>
							<tr>
								<td><strong>Variación Monetaria Neta</strong></td>
								<td class="text-center font-weight-bold text-danger">{{ $money($analytics['pickup']['financial_variance'] ?? 0) }}</td>
								<td class="text-center font-weight-bold text-danger">{{ $money($analytics['delivery']['financial_variance'] ?? 0) }}</td>
								<td class="text-center text-muted">$0.00</td>
							</tr>
							<tr>
								<td><strong>Tasa de Incidencias</strong></td>
								<td class="text-center">{{ number_format($analytics['pickup']['claims_rate_pct'] ?? 0, 1) }}%</td>
								<td class="text-center">{{ number_format($analytics['delivery']['claims_rate_pct'] ?? 0, 1) }}%</td>
								<td class="text-center text-muted">≤ 2.5%</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: VALIDAR ENTREGA CLIENTE RECOGE (PICK-UP) ================= -->
<div class="modal fade" id="modalVerifyPickup" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-key mr-2 text-warning"></i>Validación de Retiro en Tienda</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="alert alert-info py-2 small mb-3">
					<i class="fa-solid fa-circle-info mr-1"></i>
					Solicite al cliente el PIN de 6 dígitos o token QR y registre quién retira el pedido como POD.
				</div>

				<div class="form-group mb-3 text-center">
					<label class="font-weight-bold small text-dark d-block">PIN de retiro (6 dígitos):</label>
					<input type="text" id="inputPickupPinCode" class="form-control form-control-lg text-center font-weight-bold text-dark" placeholder="000000" maxlength="16" style="letter-spacing: 0.2em; font-size: 1.4rem;" autofocus>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Nombre / Documento de quien retira (POD):</label>
					<input type="text" id="inputPickupReceiverName" class="form-control form-control-sm" placeholder="Ej. María Gómez · CI 8812...">
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitVerifyPickup">
					<i class="fa-solid fa-check mr-1"></i>Confirmar Retiro y POD
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: REGISTRAR ENTREGA RUTA A DOMICILIO ================= -->
<div class="modal fade" id="modalRecordDelivery" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-truck-arrow-right mr-2 text-success"></i>Registro de Entrega y POD Obligatoria</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Resultado de la entrega:</label>
					<select id="selDeliveryStatus" class="form-control form-control-sm font-weight-bold">
						<option value="delivered" selected>Entregado Conforme (Completo)</option>
						<option value="partial">Entrega Parcial (Genera incidencia logística)</option>
						<option value="rejected">Rechazado por el cliente</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Nombre y firma de quien recibe (POD obligatorio):</label>
					<input type="text" id="inputDeliverySignature" class="form-control form-control-sm" placeholder="Ej. Juan Pérez (Titular / Conserjería)">
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Folio o referencia de evidencia fotográfica POD (obligatorio):</label>
					<input type="text" id="inputDeliveryEvidenceUrl" class="form-control form-control-sm" placeholder="Ej. POD-GUIA-9941 o URL foto recepción">
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Transportista / Conductor:</label>
					<input type="text" id="inputCarrierName" class="form-control form-control-sm" placeholder="Conductor asignado en ruta">
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Incidencias o detalle de entrega parcial / rechazo:</label>
					<textarea id="txtRouteIncidents" class="form-control form-control-sm" rows="2" placeholder="Obligatorio si la entrega es parcial o rechazada..."></textarea>
				</div>
				<div class="custom-control custom-checkbox mb-0">
					<input type="checkbox" class="custom-control-input" id="chkColdChainOk" checked>
					<label class="custom-control-label small font-weight-bold text-dark" for="chkColdChainOk">
						<i class="fa-solid fa-snowflake text-info mr-1"></i>Cadena de frío verificada en entrega (≤ 4°C)
					</label>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitRecordDelivery">
					<i class="fa-solid fa-check mr-1"></i>Registrar Entrega POD
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: ESCANEO RÁPIDO UPC ================= -->
<div class="modal fade" id="modalFastScanVerify" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-barcode mr-2 text-warning"></i>Picking y Validación con Escáner PDA / UPC</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="card p-3 border mb-3 bg-white text-center">
					<label class="font-weight-bold small text-dark mb-1">Escanee código de barras UPC o SKU (cantidad por escaneo):</label>
					<div class="input-group mb-2">
						<input type="text" id="inputFastScanBarcode" class="form-control form-control-lg font-weight-bold" placeholder="Escanee código o presione Enter..." autofocus>
						<input type="number" id="inputFastScanQty" class="form-control form-control-lg font-weight-bold" value="1" min="0.1" step="1" style="max-width: 85px;" title="Cantidad">
						<div class="input-group-append">
							<button class="btn btn-success font-weight-bold" type="button" id="btnTriggerFastScan"><i class="fa-solid fa-check"></i></button>
						</div>
					</div>
					<small class="text-muted">Bloquea automáticamente sobrantes mayores a la cantidad reservada.</small>
				</div>

				<!-- Live Result Alert Banner -->
				<div id="fastScanResultBanner" class="d-none p-3 rounded border mb-2 text-center font-weight-bold">
					<div id="fastScanResultIcon" class="mb-1" style="font-size: 2rem;"></div>
					<div id="fastScanResultMessage" style="font-size: 0.95rem;"></div>
					<div id="fastScanResultDetails" class="small mt-1 text-muted"></div>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: RESOLVER VARIACIÓN LOGÍSTICA ================= -->
<div class="modal fade" id="modalResolveVariance" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header text-white" style="background: #dc2626;">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-gavel mr-2"></i>Resolución Logística de Variación</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="resolveVarianceId" value="0">
				<div class="card p-3 border mb-3 bg-white">
					<strong class="text-dark" id="resolveVarianceProductName">Seleccione una variación pendiente</strong>
					<span class="text-danger font-weight-bold" id="resolveVarianceDiffQty">-</span>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Dictamen logístico autorizado:</label>
					<select class="form-control form-control-sm font-weight-bold" id="selResolutionStatus">
						<option value="authorized_exception">Excepción autorizada en despacho</option>
						<option value="shortage_confirmed">Faltante logístico confirmado en salida/ruta</option>
						<option value="partial_credit">Ajuste por entrega parcial al cliente</option>
						<option value="credited">Nota de crédito logística aprobada</option>
						<option value="resolved">Diferencia aclarada en verificación física</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Justificación logística obligatoria:</label>
					<textarea class="form-control form-control-sm" id="txtResolutionNotes" rows="2" placeholder="Motivo de la resolución antes de conciliación..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-danger font-weight-bold" id="btnSubmitResolveVariance">
					<i class="fa-solid fa-check mr-1"></i>Autorizar y Resolver
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: COMPROBANTE DE ENTREGA POD ================= -->
<div class="modal fade" id="modalOrderEvidence" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-file-invoice mr-2 text-success"></i>Prueba de Entrega (POD) · <span id="eviOrderCode">-</span></h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="bg-white p-3 rounded border">
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted small">Estado POD:</span>
						<span class="badge badge-secondary" id="eviStatus">Pendiente</span>
					</div>
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted small">Receptor / Firma:</span>
						<span class="font-weight-bold small" id="eviReceiver">Sin registro</span>
					</div>
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted small">Referencia / Folio Evidencia:</span>
						<span class="font-weight-bold small text-primary" id="eviPodRef">-</span>
					</div>
					<div class="d-flex justify-content-between mb-2">
						<span class="text-muted small">Fecha & Hora Entrega:</span>
						<span class="small font-weight-bold" id="eviDeliveryTime">-</span>
					</div>
					<div class="d-flex justify-content-between">
						<span class="text-muted small">Cadena de Frío:</span>
						<span class="small font-weight-bold" id="eviColdChain">Sin verificar</span>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: PROGRAMAR TRANSPORTE Y RUTA ================= -->
<div class="modal fade" id="modalScheduleRoute" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-route mr-2 text-primary"></i>Programación de Transporte y Ruta</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="schedOrderId" value="{{ $selectedOrderId ?: 1 }}">
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Vehículo de la Flota:</label>
					<select id="selSchedVehicle" class="form-control form-control-sm font-weight-bold">
						@foreach($vehicles as $vh)
							<option value="{{ $vh['name'] }} ({{ $vh['plate'] }})">{{ $vh['name'] }} · {{ $vh['plate'] }} ({{ $vh['capacity_kg'] }} kg)</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Conductor / Transportista:</label>
					<select id="selSchedDriver" class="form-control form-control-sm">
						@foreach($users as $u)
							<option value="{{ $u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : $u['username'] }}">{{ $u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : $u['username'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Ruta de Reparto:</label>
					<select id="selSchedRoute" class="form-control form-control-sm font-weight-bold">
						<option value="Ruta A - Zona Norte">Ruta A - Zona Norte (Matriz, Norte y Periferia)</option>
						<option value="Ruta B - Centro Financiero">Ruta B - Centro Financiero (Comercial y Empresas)</option>
						<option value="Ruta C - Zona Sur">Ruta C - Zona Sur (Residencial y Almacenes)</option>
						<option value="Ruta Express">Ruta Express (Entrega inmediata ≤ 45 min)</option>
					</select>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Teléfono de Contacto en Ruta:</label>
					<input type="text" id="inputSchedPhone" class="form-control form-control-sm" value="+57 310 555-8291">
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnSubmitScheduleTransport">
					<i class="fa-solid fa-check mr-1"></i>Confirmar Programación
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: ASIGNAR OPERARIO DE PICKING ================= -->
<div class="modal fade" id="modalAssignPicking" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-boxes-packing mr-2 text-success"></i>Asignación de Picking y Operador</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="pickingOrderId" value="{{ $selectedOrderId ?: 1 }}">
				<div class="card p-2 bg-white border mb-3">
					<span class="small text-muted">Pedido Seleccionado:</span>
					<strong class="text-dark" id="lblPickingOrderCode">PED-2026-00002</strong>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Operador / Picker asignado:</label>
					<select id="selPickerUser" class="form-control form-control-sm font-weight-bold">
						@foreach($users as $u)
							<option value="{{ $u['id'] }}">{{ $u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : $u['username'] }}</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitAssignPicking">
					<i class="fa-solid fa-check mr-1"></i>Asignar a Picking
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: CONFIRMAR SALIDA Y GUÍA DE DESPACHO ================= -->
<div class="modal fade" id="modalConfirmExit" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-truck-fast mr-2 text-success"></i>Confirmación de Carga y Salida</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="exitOrderId" value="{{ $selectedOrderId ?: 1 }}">
				<div class="card p-2 bg-white border mb-3">
					<span class="small text-muted">Pedido para Despacho:</span>
					<strong class="text-dark" id="lblExitOrderCode">PED-2026-00002</strong>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Número de Guía / Despacho Oficial:</label>
					<input type="text" id="inputDispatchDocNum" class="form-control form-control-sm font-weight-bold" value="DSP-{{ date('Ymd') }}-{{ sprintf('%04d', $selectedOrderId ?: 1) }}">
				</div>
				<div class="custom-control custom-checkbox mb-2">
					<input type="checkbox" class="custom-control-input" id="chkExitLoadingOk" checked>
					<label class="custom-control-label small font-weight-bold text-dark" for="chkExitLoadingOk">
						Carga física completa y verificada en muelle
					</label>
				</div>
				<div class="custom-control custom-checkbox mb-0">
					<input type="checkbox" class="custom-control-input" id="chkExitSealOk" checked>
					<label class="custom-control-label small font-weight-bold text-dark" for="chkExitSealOk">
						Precinto de seguridad instalado en compuerta
					</label>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitConfirmExit">
					<i class="fa-solid fa-truck-arrow-right mr-1"></i>Confirmar Salida a Ruta
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: NUEVO PEDIDO DE DESPACHO ================= -->
<div class="modal fade" id="modalNewOrder" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-plus mr-2 text-success"></i>Nuevo Pedido de Despacho</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="newOrderIdempotencyKey" value="">
				<div class="row">
					<div class="col-md-6 form-group mb-2">
						<label class="font-weight-bold small text-dark">Cliente:</label>
						<select id="selNewOrderCustomer" class="form-control form-control-sm font-weight-bold">
							@foreach($customers as $c)
								<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-6 form-group mb-2">
						<label class="font-weight-bold small text-dark">Sucursal de Origen:</label>
						<select id="selNewOrderBranch" class="form-control form-control-sm">
							@foreach($branches as $b)
								<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 form-group mb-2">
						<label class="font-weight-bold small text-dark">Modalidad:</label>
						<select id="selNewOrderType" class="form-control form-control-sm font-weight-bold">
							<option value="delivery">Despacho a Domicilio</option>
							<option value="pickup">Retiro en Tienda (Click & Collect)</option>
						</select>
					</div>
					<div class="col-md-4 form-group mb-2">
						<label class="font-weight-bold small text-dark">Fecha Comprometida:</label>
						<input type="date" id="inputNewOrderDate" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
					</div>
					<div class="col-md-4 form-group mb-2">
						<label class="font-weight-bold small text-dark">Ruta (opcional):</label>
						<input type="text" id="inputNewOrderRoute" class="form-control form-control-sm" placeholder="Ej. Ruta Norte">
					</div>
				</div>

				<div class="card p-3 border bg-white mt-2">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<strong class="small text-dark">Productos solicitados (Sin líneas duplicadas):</strong>
						<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" id="btnAddNewOrderLine">
							<i class="fa-solid fa-plus mr-1"></i>Agregar Línea
						</button>
					</div>
					<div id="newOrderLinesWrap">
						<div class="form-row align-items-center mb-2 dp-new-order-line">
							<div class="col-7">
								<select class="form-control form-control-sm sel-new-prod">
									@foreach($sellableProducts as $sp)
										<option value="{{ $sp['id'] }}" data-price="{{ number_format(floatval($sp['ref_price'] > 0 ? $sp['ref_price'] : 1), 2, '.', '') }}">
											{{ $sp['name'] }} (SKU: {{ $sp['sku'] }} · Disp: {{ number_format(floatval($sp['stock_avail']), 0) }})
										</option>
									@endforeach
								</select>
							</div>
							<div class="col-3">
								<input type="number" class="form-control form-control-sm inp-new-qty" value="1" min="0.1" step="1" placeholder="Cant.">
							</div>
							<div class="col-2 text-right">
								<button type="button" class="btn btn-xs btn-light border btn-remove-line"><i class="fa-solid fa-trash text-danger"></i></button>
							</div>
						</div>
					</div>
				</div>
				<div class="custom-control custom-checkbox mt-2">
					<input type="checkbox" class="custom-control-input" id="chkNewOrderReserveNow" checked>
					<label class="custom-control-label small font-weight-bold text-dark" for="chkNewOrderReserveNow">
						Reservar mercancía automáticamente tras crear el pedido
					</label>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitNewOrder">
					<i class="fa-solid fa-check mr-1"></i>Crear Pedido
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: PACKING Y ETIQUETA ================= -->
<div class="modal fade" id="modalPackOrder" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-box-open mr-2 text-success"></i>Empaque (Packing) y Etiqueta</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="packOrderId" value="{{ $selectedOrderId ?: 0 }}">
				<div class="card p-2 bg-white border mb-3">
					<span class="small text-muted">Pedido en empaque:</span>
					<strong class="text-dark" id="lblPackOrderCode">-</strong>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Número de bultos / cajas selladas:</label>
					<input type="number" id="inputPackBoxes" class="form-control form-control-sm font-weight-bold" value="1" min="1" step="1">
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Notas de empaque / precinto:</label>
					<input type="text" id="inputPackNotes" class="form-control form-control-sm" placeholder="Ej. Precinto #441 · Fragilidad controlada">
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitPackOrder">
					<i class="fa-solid fa-tag mr-1"></i>Cerrar Packing y Generar Etiqueta
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: ETIQUETA DE DESPACHO IMPRIMIBLE ================= -->
<div class="modal fade" id="modalDispatchLabel" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-tag mr-2 text-success"></i>Etiqueta de Despacho</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div id="dpPrintableLabel" class="bg-white p-4 rounded border" style="border: 2px dashed #0f172a !important;">
					<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
						<div>
							<strong class="text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.08em;">DAILY LOGISTICS OMS/WMS</strong>
							<h5 class="mb-0 font-weight-bold text-dark" id="lblTagCode">ETQ-PED</h5>
						</div>
						<span class="badge badge-dark px-2 py-1" id="lblTagPackages">1 BULTO</span>
					</div>
					<div class="small mb-2">
						<div><span class="text-muted">Pedido:</span> <strong id="lblTagOrderCode">-</strong> · <span id="lblTagModality">-</span></div>
						<div><span class="text-muted">Origen:</span> <strong id="lblTagBranch">-</strong></div>
						<div><span class="text-muted">Destino / Cliente:</span> <strong id="lblTagCustomer">-</strong></div>
						<div><span class="text-muted">Dirección / Ruta:</span> <span id="lblTagRoute">-</span></div>
						<div><span class="text-muted">Unidades / Ítems:</span> <strong id="lblTagUnits">-</strong></div>
						<div id="lblTagPinWrap" class="d-none text-success font-weight-bold">PIN Retiro: <span id="lblTagPin">-</span></div>
					</div>
					<div class="text-center border-top pt-2 mt-2">
						<div style="font-family: monospace; font-size: 1.15rem; letter-spacing: 0.25em; font-weight: 800;" id="lblTagBarcode">*ETQ*</div>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="window.print()">
					<i class="fa-solid fa-print mr-1"></i>Imprimir Etiqueta
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: REPORTAR INCIDENCIA LOGÍSTICA ================= -->
<div class="modal fade" id="modalReportIncident" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-warning text-dark">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Registrar Incidencia Logística</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="incidentOrderId" value="{{ $selectedOrderId ?: 0 }}">
				<div class="card p-2 bg-white border mb-2">
					<span class="small text-muted">Pedido afectado:</span>
					<strong class="text-dark" id="lblIncidentOrderCode">-</strong>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Tipo de incidencia:</label>
					<select id="selIncidentType" class="form-control form-control-sm font-weight-bold">
						<option value="atraso_ruta">Atraso en tránsito / tráfico</option>
						<option value="entrega_parcial">Entrega parcial de bultos</option>
						<option value="rechazo_cliente">Rechazo parcial o total del cliente</option>
						<option value="averia_transito">Embalaje averiado en transporte</option>
						<option value="direccion_erronea">Dirección incompleta / acceso cerrado</option>
						<option value="faltante_empaque">Faltante detectado en carga</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Severidad:</label>
					<select id="selIncidentSeverity" class="form-control form-control-sm">
						<option value="low">Baja</option>
						<option value="medium" selected>Media</option>
						<option value="high">Alta</option>
						<option value="critical">Crítica</option>
					</select>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Descripción:</label>
					<textarea id="txtIncidentDesc" class="form-control form-control-sm" rows="2" placeholder="Detalle logístico ocurrido..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-warning font-weight-bold" id="btnSubmitReportIncident">
					<i class="fa-solid fa-check mr-1"></i>Guardar Incidencia
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: RESOLVER INCIDENCIA LOGÍSTICA ================= -->
<div class="modal fade" id="modalResolveIncident" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-check-circle mr-2 text-success"></i>Resolver Incidencia Logística</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="resolveIncidentId" value="0">
				<div class="card p-2 bg-white border mb-2">
					<strong class="small text-dark" id="lblResolveIncidentDesc">-</strong>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Acción resolutiva / cierre:</label>
					<textarea id="txtResolveIncidentNotes" class="form-control form-control-sm" rows="2" placeholder="Ej. Re-programado en segundo viaje / autorizado por cliente"></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitResolveIncident">
					<i class="fa-solid fa-check mr-1"></i>Resolver Incidencia
				</button>
			</div>
		</div>
	</div>
</div>

<script>
window.CurrentOrderId = {{ $selectedOrderId ?: 0 }};
window.DispatchOrdersMap = {!! $ordersDetailMapJson !!};

function extractErr(xhr, fallback) {
	if (xhr && xhr.responseJSON) {
		return xhr.responseJSON.error_message || xhr.responseJSON.message || fallback;
	}
	return fallback;
}

function selectOrderAndSwitch(orderId) {
	$('#tab-seguimiento-link').click();
	if (typeof window.SelectOrder === 'function') {
		window.SelectOrder(orderId);
	} else {
		$('.dp-order-card[data-id="' + orderId + '"]').click();
	}
}

function openAssignPickingModal(orderId, orderCode) {
	$('#pickingOrderId').val(orderId);
	$('#lblPickingOrderCode').text(orderCode);
	$('#modalAssignPicking').modal('show');
}

function openScheduleTransportModal(orderId, orderCode) {
	$('#schedOrderId').val(orderId);
	$('#modalScheduleRoute').modal('show');
}

function openPackOrderModal(orderId, orderCode) {
	$('#packOrderId').val(orderId);
	$('#lblPackOrderCode').text(orderCode || '');
	$('#modalPackOrder').modal('show');
}

function openConfirmExitModal(orderId, orderCode) {
	$('#exitOrderId').val(orderId);
	$('#lblExitOrderCode').text(orderCode);
	$('#inputDispatchDocNum').val('DSP-{{ date("Ymd") }}-' + ('0000' + orderId).slice(-4));
	$('#modalConfirmExit').modal('show');
}

function openReportIncidentModal(orderId, orderCode) {
	$('#incidentOrderId').val(orderId || window.CurrentOrderId);
	$('#lblIncidentOrderCode').text(orderCode || ('#' + (orderId || window.CurrentOrderId)));
	$('#txtIncidentDesc').val('');
	$('#modalReportIncident').modal('show');
}

function openResolveIncidentModal(incidentId, desc) {
	$('#resolveIncidentId').val(incidentId);
	$('#lblResolveIncidentDesc').text(desc || ('Incidencia #' + incidentId));
	$('#txtResolveIncidentNotes').val('');
	$('#modalResolveIncident').modal('show');
}

function openResolveVarianceModal(varianceId, prodName, diffQty) {
	$('#resolveVarianceId').val(varianceId);
	$('#resolveVarianceProductName').text(prodName || 'Variación #' + varianceId);
	$('#resolveVarianceDiffQty').text('Diferencia: ' + diffQty);
	$('#txtResolutionNotes').val('');
	$('#modalResolveVariance').modal('show');
}

function openEvidenceModal(orderId) {
	var oid = orderId || window.CurrentOrderId;
	var d = window.DispatchOrdersMap ? window.DispatchOrdersMap[oid] : null;
	if (d && d.order) {
		var o = d.order;
		$('#eviOrderCode').text(o.order_code || '-');
		var isDelivered = (o.delivery_status === 'delivered' || o.delivery_status === 'partial');
		$('#eviStatus')
			.removeClass('badge-secondary badge-success badge-warning badge-danger')
			.addClass(o.delivery_status === 'delivered' ? 'badge-success' : (o.delivery_status === 'partial' ? 'badge-warning' : (o.delivery_status === 'rejected' ? 'badge-danger' : 'badge-secondary')))
			.text(o.delivery_status === 'delivered' ? 'Entregado POD OK' : (o.delivery_status === 'partial' ? 'Entrega Parcial' : (o.delivery_status === 'rejected' ? 'Rechazado' : 'Pendiente POD')));
		$('#eviReceiver').text(o.delivery_signature_name || 'Sin receptor registrado');
		$('#eviPodRef').text(o.delivery_evidence_url || (o.pickup_verified_at ? ('PIN Verificado: ' + (o.pickup_pin || 'OK')) : 'Sin folio POD'));
		$('#eviDeliveryTime').text(o.delivery_time || o.pickup_verified_at || 'Pendiente');
		$('#eviColdChain').text(parseInt(o.cold_chain_temp_ok || 0, 10) === 1 ? 'Verificada ≤ 4°C' : 'No verificada');
	}
	$('#modalOrderEvidence').modal('show');
}

function openDispatchLabelModal(orderId) {
	var oid = orderId || window.CurrentOrderId;
	if (!oid) { toastr.warning('Seleccione un pedido primero.'); return; }
	$.get(U('/api/retail/dispatch/orders/' + oid + '/label'), function (res) {
		$('#lblTagCode').text(res.label_code || ('ETQ-' + res.order_code));
		$('#lblTagOrderCode').text(res.order_code || '-');
		$('#lblTagModality').text(res.delivery_type === 'pickup' ? 'RETIRO EN TIENDA' : 'DOMICILIO');
		$('#lblTagBranch').text(res.branch_name || '-');
		$('#lblTagCustomer').text(res.customer_name || '-');
		$('#lblTagRoute').text((res.delivery_address || '-') + ' · ' + (res.route_name || '-'));
		$('#lblTagPackages').text((res.packages_count || 1) + ' BULTO(S)');
		$('#lblTagUnits').text((res.items_count || 0) + ' SKU(s) · ' + (res.total_units || 0) + ' u.');
		$('#lblTagBarcode').text('*' + (res.barcode_value || res.order_code) + '*');
		if (res.pickup_pin) {
			$('#lblTagPinWrap').removeClass('d-none');
			$('#lblTagPin').text(res.pickup_pin);
		} else {
			$('#lblTagPinWrap').addClass('d-none');
		}
		$('#modalDispatchLabel').modal('show');
	}).fail(function (xhr) {
		toastr.error(extractErr(xhr, 'No se pudo cargar la etiqueta de despacho.'));
	});
}

function reserveOrder(orderId) {
	$.ajax({
		url: U('/api/retail/dispatch/reserve-order'),
		type: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ order_id: orderId }),
		success: function (res) {
			toastr.success(res.message || 'Stock reservado correctamente.');
			setTimeout(function () { location.reload(); }, 500);
		},
		error: function (xhr) {
			toastr.error(extractErr(xhr, 'No se pudo reservar el pedido.'));
		}
	});
}

function conciliateOrder(orderId) {
	$.ajax({
		url: U('/api/retail/dispatch/conciliate'),
		type: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ order_id: orderId }),
		success: function (res) {
			toastr.success(res.message || 'Pedido conciliado y cerrado.');
			setTimeout(function () { location.reload(); }, 600);
		},
		error: function (xhr) {
			toastr.error(extractErr(xhr, 'No se pudo conciliar el pedido.'));
		}
	});
}

document.addEventListener('DOMContentLoaded', function () {
	$('#modalNewOrder').on('show.bs.modal', function () {
		$('#newOrderIdempotencyKey').val('ord-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8));
	});

	$('#btnAddNewOrderLine').on('click', function () {
		var firstLine = $('#newOrderLinesWrap .dp-new-order-line').first().clone();
		firstLine.find('.inp-new-qty').val('1');
		$('#newOrderLinesWrap').append(firstLine);
	});

	$(document).on('click', '.btn-remove-line', function () {
		if ($('#newOrderLinesWrap .dp-new-order-line').length > 1) {
			$(this).closest('.dp-new-order-line').remove();
		}
	});

	$('#btnSubmitNewOrder').on('click', function () {
		var btn = $(this).prop('disabled', true);
		var items = [];
		$('#newOrderLinesWrap .dp-new-order-line').each(function () {
			var sel = $(this).find('.sel-new-prod option:selected');
			var pid = parseInt(sel.val(), 10);
			var qty = parseFloat($(this).find('.inp-new-qty').val());
			if (pid && qty > 0) {
				items.push({ product_id: pid, amount: qty });
			}
		});
		$.ajax({
			url: U('/api/retail/dispatch/create-order'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				idempotency_key: $('#newOrderIdempotencyKey').val(),
				customer_id: parseInt($('#selNewOrderCustomer').val(), 10),
				branch_id: parseInt($('#selNewOrderBranch').val(), 10),
				delivery_type: $('#selNewOrderType').val(),
				requested_date: $('#inputNewOrderDate').val(),
				route_name: $('#inputNewOrderRoute').val(),
				confirm: $('#chkNewOrderReserveNow').is(':checked') ? 1 : 0,
				items: items
			}),
			success: function (res) {
				toastr.success(res.message || 'Pedido creado.');
				$('#modalNewOrder').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				btn.prop('disabled', false);
				toastr.error(extractErr(xhr, 'Error al registrar pedido.'));
			}
		});
	});

	$('#btnSubmitPackOrder').on('click', function () {
		$.ajax({
			url: U('/api/retail/dispatch/pack-order'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: parseInt($('#packOrderId').val(), 10),
				packages_count: parseInt($('#inputPackBoxes').val(), 10) || 1,
				notes: $('#inputPackNotes').val()
			}),
			success: function (res) {
				toastr.success(res.message || 'Packing cerrado.');
				$('#modalPackOrder').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error en Packing.'));
			}
		});
	});

	// Submit Assign Picking
	$('#btnSubmitAssignPicking').on('click', function () {
		var oid = $('#pickingOrderId').val();
		var uid = $('#selPickerUser').val();
		$.ajax({
			url: U('/api/retail/dispatch/assign-picking'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ order_id: oid, picker_user_id: uid }),
			success: function (res) {
				toastr.success(res.message || 'Operario asignado a picking.');
				$('#modalAssignPicking').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al asignar picking.'));
			}
		});
	});

	// Submit Schedule Transport
	$('#btnSubmitScheduleTransport').on('click', function () {
		var oid = $('#schedOrderId').val();
		var vh = $('#selSchedVehicle').val();
		var driver = $('#selSchedDriver').val();
		var route = $('#selSchedRoute').val();
		var phone = $('#inputSchedPhone').val();

		$.ajax({
			url: U('/api/retail/dispatch/schedule-transport'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: oid,
				carrier_vehicle: vh,
				carrier_name: driver,
				route: route,
				carrier_phone: phone
			}),
			success: function (res) {
				toastr.success(res.message || 'Transporte y ruta programados.');
				$('#modalScheduleRoute').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al programar transporte.'));
			}
		});
	});

	// Submit Confirm Exit
	$('#btnSubmitConfirmExit').on('click', function () {
		var oid = $('#exitOrderId').val();
		var doc = $('#inputDispatchDocNum').val();

		$.ajax({
			url: U('/api/retail/dispatch/confirm-exit'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({ order_id: oid, dispatch_number: doc }),
			success: function (res) {
				toastr.success(res.message || 'Salida confirmada.');
				$('#modalConfirmExit').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al confirmar salida.'));
			}
		});
	});

	// Submit Logistics Incident
	$('#btnSubmitReportIncident').on('click', function () {
		$.ajax({
			url: U('/api/retail/dispatch/report-incident'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				order_id: parseInt($('#incidentOrderId').val(), 10),
				incident_type: $('#selIncidentType').val(),
				severity: $('#selIncidentSeverity').val(),
				description: $('#txtIncidentDesc').val()
			}),
			success: function (res) {
				toastr.success(res.message || 'Incidencia registrada.');
				$('#modalReportIncident').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al registrar incidencia.'));
			}
		});
	});

	// Submit Resolve Incident
	$('#btnSubmitResolveIncident').on('click', function () {
		$.ajax({
			url: U('/api/retail/dispatch/resolve-incident'),
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				incident_id: parseInt($('#resolveIncidentId').val(), 10),
				resolution_notes: $('#txtResolveIncidentNotes').val()
			}),
			success: function (res) {
				toastr.success(res.message || 'Incidencia resuelta.');
				$('#modalResolveIncident').modal('hide');
				setTimeout(function () { location.reload(); }, 600);
			},
			error: function (xhr) {
				toastr.error(extractErr(xhr, 'Error al resolver incidencia.'));
			}
		});
	});
});
</script>
		</div>{{-- /.dp-page-container --}}
	</div>{{-- /#dp-pane-operational --}}
</div>{{-- /#dispatchApp --}}
@endsection

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script src="{{ $U('/js/daily_dispatches_dashboard.js?v=', true) }}{{ daily_asset_version('/js/daily_dispatches_dashboard.js') }}" defer></script>
<script src="{{ $U('/js/daily_dashboard_kit.js?v=', true) }}{{ daily_asset_version('/js/daily_dashboard_kit.js') }}"></script>
<script src="{{ $U('/viewjs/dispatch.js?v=', true) }}{{ daily_asset_version('/viewjs/dispatch.js') }}"></script>
@endpush
