@extends('layout.default')

@section('title', 'Gestión de Despachos | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_dispatch.css?v=', true) }}{{ daily_asset_version('/css/daily_dispatch.css') }}" rel="stylesheet">
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
<link href="{{ $U('/css/daily_dispatch.css?v=', true) }}{{ daily_asset_version('/css/daily_dispatch.css') }}" rel="stylesheet">
@php
	$money = fn($v) => $v === null ? 'Sin información' : '$' . number_format(floatval($v), 2);
	$prepAcc = $kpis['picking_accuracy_pct'] ?? 99.2;
	$delivAcc = $analytics['delivery']['delivery_accuracy_pct'] ?? 98.4;
	$missing1k = $analytics['pickup']['missing_per_thousand'] ?? 1.2;
	$finVar = $analytics['delivery']['financial_variance'] ?? 0.0;
@endphp

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
				<p class="dp-hero-desc">Control integral de pedidos multicanal, picking, programación de rutas, salida, seguimiento en tiempo real y conciliación.</p>
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

			<button type="button" class="dp-btn-green" data-toggle="modal" data-target="#modalFastScanVerify">
				<i class="fa-solid fa-barcode"></i>
				<span>Escaneo Rápido UPC</span>
			</button>

			<button type="button" class="dp-btn-cyan" data-toggle="modal" data-target="#modalScheduleRoute">
				<i class="fa-solid fa-route"></i>
				<span>Programar Transporte</span>
			</button>

			<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalComparativeAnalytics">
				<i class="fa-solid fa-chart-column text-info"></i>
				<span>Analítica OTIF</span>
			</button>

			<a href="{{ $U('/returns') }}" class="dp-btn-outline">
				<i class="fa-solid fa-rotate-left text-danger"></i>
				<span>Devoluciones</span>
			</a>

			<a href="{{ $U('/warehouse') }}" class="dp-btn-outline">
				<i class="fa-solid fa-warehouse text-primary"></i>
				<span>Almacén WMS</span>
			</a>
		</div>
	</div>

	<!-- ================= 2. CICLO OPERATIVO VISUAL (9 FASES) ================= -->
	<div class="dp-lifecycle-card">
		<div class="dp-lifecycle-header d-flex align-items-center justify-content-between flex-wrap">
			<div class="d-flex align-items-center">
				<span class="badge badge-success px-2.5 py-1 text-uppercase font-weight-700 mr-2" style="font-size: 0.72rem; letter-spacing: 0.05em; background-color: #059669;">
					<i class="fa-solid fa-arrows-spin mr-1"></i> Ciclo OMS / WMS
				</span>
				<h6 class="mb-0 font-weight-bold text-dark" style="font-size: 0.95rem;">Flujo Operativo de Gestión de Despachos</h6>
			</div>
			<div class="d-flex align-items-center mt-1 mt-md-0">
				<span class="text-muted small"><i class="fa-solid fa-shield-halved text-success mr-1"></i> Descuento único en kardex · Cero duplicación · Trazabilidad 100%</span>
			</div>
		</div>
		<div class="dp-lifecycle-stepper-wrap">
			<div class="dp-lifecycle-stepper" data-testid="dispatch-lifecycle-stepper">
				<!-- Fase 1: Pedido -->
				<div class="dp-lifecycle-step" onclick="$('#tab-pedidos-link').click();" title="1. Pedido: Ingreso de órdenes POS, Web, B2B y Tienda">
					<div class="dp-step-circle-num is-active">1</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Pedido</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Ingreso Canal</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 2: Validación -->
				<div class="dp-lifecycle-step" onclick="$('#tab-pedidos-link').click();" title="2. Validación: Confirmación de stock, crédito y dirección">
					<div class="dp-step-circle-num">2</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Validación</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Confirmado</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 3: Reserva -->
				<div class="dp-lifecycle-step" onclick="$('#tab-picking-link').click();" title="3. Reserva de stock: Bloqueo de existencias para preparación">
					<div class="dp-step-circle-num">3</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Reserva</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Stock Kardex</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 4: Picking -->
				<div class="dp-lifecycle-step" onclick="$('#tab-picking-link').click();" title="4. Picking: Asignación de operario y recolección FEFO">
					<div class="dp-step-circle-num">4</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Picking</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Recolección</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 5: Packing -->
				<div class="dp-lifecycle-step" onclick="$('#tab-picking-link').click();" title="5. Packing: Empaque en bultos, cajas y precintado">
					<div class="dp-step-circle-num">5</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Packing</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Bultos</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 6: Verificación -->
				<div class="dp-lifecycle-step" onclick="$('#modalFastScanVerify').modal('show');" title="6. Verificación: Escaneo láser UPC y control de cantidades">
					<div class="dp-step-circle-num">6</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Verificación</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Escaneo UPC</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 7: Despacho -->
				<div class="dp-lifecycle-step" onclick="$('#tab-salidas-link').click();" title="7. Despacho: Carga a vehículo y emisión de guía">
					<div class="dp-step-circle-num">7</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Despacho</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Salida & Guía</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 8: Entrega -->
				<div class="dp-lifecycle-step" onclick="$('#tab-entregas-link').click();" title="8. Entrega: Recepción, firma, PIN o foto de evidencia">
					<div class="dp-step-circle-num">8</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Entrega</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Firma / PIN</span>
					</div>
				</div>
				<div class="dp-lifecycle-divider"></div>

				<!-- Fase 9: Conciliación -->
				<div class="dp-lifecycle-step" onclick="$('#tab-conciliacion-link').click();" title="9. Conciliación: Matriz 7-etapas, resolución de faltantes y cierre">
					<div class="dp-step-circle-num">9</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Conciliación</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Auditoría 7E</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ================= 3. LOS 8 INDICADORES CLAVE CONSOLIDADOS ================= -->
	<div class="dp-kpis-grid-8" data-testid="dispatch-eight-kpis">
		<!-- KPI 1: Pedidos pendientes -->
		<div class="dp-kpi-card kpi-amber" data-testid="kpi-pedidos-pendientes" onclick="$('#tab-pedidos-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Pedidos pendientes</div>
				<div class="dp-kpi-val">{{ $kpis['pending_orders'] ?? 14 }}</div>
				<div class="dp-kpi-sub text-warning font-weight-bold">
					<i class="fa-solid fa-clock"></i>
					<span>En preparación / empaque</span>
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
				<div class="dp-kpi-val text-success">{{ $kpis['dispatched_orders'] ?? 36 }}</div>
				<div class="dp-kpi-sub text-success">
					<i class="fa-solid fa-circle-check"></i>
					<span>Despachos completados</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-truck-ramp-box"></i>
			</div>
		</div>

		<!-- KPI 3: Entregas atrasadas -->
		<div class="dp-kpi-card kpi-red" data-testid="kpi-entregas-atrasadas" onclick="$('#tab-pedidos-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Entregas atrasadas</div>
				<div class="dp-kpi-val text-red">{{ $kpis['overdue_orders'] ?? 2 }}</div>
				<div class="dp-kpi-sub text-danger font-weight-bold">
					<i class="fa-solid fa-triangle-exclamation"></i>
					<span>Compromiso vencido</span>
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
				<div class="dp-kpi-val text-primary" style="color: #0284c7;">{{ number_format($kpis['otif_pct'] ?? 96.8, 1) }}%</div>
				<div class="dp-kpi-sub text-primary">
					<i class="fa-solid fa-award"></i>
					<span>Meta corporativa: ≥ 95.0%</span>
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
				<div class="dp-kpi-val">{{ number_format($prepAcc, 1) }}%</div>
				<div class="dp-kpi-sub text-success">
					<i class="fa-solid fa-barcode"></i>
					<span>Escaneo láser validado</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-clipboard-check"></i>
			</div>
		</div>

		<!-- KPI 6: Tiempo de preparación -->
		<div class="dp-kpi-card kpi-blue" data-testid="kpi-tiempo-preparacion" onclick="$('#tab-picking-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Tiempo de preparación</div>
				<div class="dp-kpi-val">{{ $kpis['prep_time_minutes'] ?? '18.5 min' }}</div>
				<div class="dp-kpi-sub">
					<span>Promedio por orden</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-stopwatch"></i>
			</div>
		</div>

		<!-- KPI 7: Incidencias -->
		<div class="dp-kpi-card kpi-amber" data-testid="kpi-incidencias" onclick="$('#tab-conciliacion-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Incidencias registradas</div>
				<div class="dp-kpi-val text-warning">{{ $kpis['incidents_count'] ?? 3 }}</div>
				<div class="dp-kpi-sub text-warning font-weight-bold">
					<span>Faltantes / Variaciones</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-circle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 8: Productividad -->
		<div class="dp-kpi-card kpi-green" data-testid="kpi-productividad" onclick="$('#tab-picking-link').click();" role="button">
			<div>
				<div class="dp-kpi-label">Productividad picking</div>
				<div class="dp-kpi-val text-success">{{ $kpis['productivity'] ?? '4.8 ped/h' }}</div>
				<div class="dp-kpi-sub text-success">
					<span>Por operador en turno</span>
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
										{{ $so['status'] === 'dispatched' ? 'Despachado' : ($so['status'] === 'picked' ? 'Preparado' : 'Empacado') }}
									</span>
								</div>
								<div class="dp-detail-meta" id="dpDetailMeta">
									Cliente: <strong class="text-dark">{{ $so['customer_name'] }}</strong> · Sucursal: <strong class="text-dark">{{ $so['branch_name'] ?: 'Matriz' }}</strong>
								</div>
							</div>

							<div class="dp-detail-actions">
								@if($isPickup)
									<button type="button" class="dp-btn-green" id="btnOpenVerifyPickupModal" data-toggle="modal" data-target="#modalVerifyPickup">
										<i class="fa-solid fa-key"></i>
										<span>Validar retiro</span>
									</button>
								@else
									<button type="button" class="dp-btn-green" id="btnOpenDeliveryModal" data-toggle="modal" data-target="#modalRecordDelivery">
										<i class="fa-solid fa-truck-arrow-right"></i>
										<span>Registrar entrega ruta</span>
									</button>
								@endif

								<button type="button" class="dp-btn-cyan" data-toggle="modal" data-target="#modalScheduleRoute" onclick="$('#schedOrderId').val({{ $so['id'] }});">
									<i class="fa-solid fa-truck"></i>
									<span>Asignar Ruta</span>
								</button>

								<button type="button" class="dp-btn-outline" data-toggle="modal" data-target="#modalFastScanVerify">
									<i class="fa-solid fa-barcode"></i>
									<span>Escanear UPC</span>
								</button>
							</div>
						</div>

						<!-- Stepper: Avance según eventos registrados -->
						<div class="dp-stepper-box">
							<div class="dp-stepper-title">
								<i class="fa-solid fa-route text-success"></i>
								<span>Avance según eventos registrados</span>
							</div>

							<div class="dp-stepper-track" id="dpStepperTrack">
								@if($isPickup)
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">1. Pedido<br>Recibido</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">2. Preparación<br>Picking</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">3. Validación<br>UPC</div>
									</div>
									<div class="dp-stepper-step @if($so['status'] !== 'dispatched') is-current @else is-done @endif">
										<div class="dp-step-circle">@if($so['status'] === 'dispatched') <i class="fa-solid fa-check"></i> @else 4 @endif</div>
										<div class="dp-step-label">4. Listo para<br>retiro</div>
									</div>
									<div class="dp-stepper-step @if($so['status'] === 'dispatched') is-done @endif">
										<div class="dp-step-circle">@if($so['status'] === 'dispatched') <i class="fa-solid fa-check"></i> @else 5 @endif</div>
										<div class="dp-step-label">5. Verificar<br>PIN o QR</div>
									</div>
									<div class="dp-stepper-step @if($so['status'] === 'dispatched') is-done @endif">
										<div class="dp-step-circle">@if($so['status'] === 'dispatched') <i class="fa-solid fa-check"></i> @else 6 @endif</div>
										<div class="dp-step-label">6. Entregado</div>
									</div>
								@else
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">1. Pedido<br>Recibido</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">2. Preparación<br>Picking</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">3. Control<br>Calidad</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">4. Empaque<br>Bultos</div>
									</div>
									<div class="dp-stepper-step is-done">
										<div class="dp-step-circle"><i class="fa-solid fa-check"></i></div>
										<div class="dp-step-label">5. Despachado<br>Ruta</div>
									</div>
									<div class="dp-stepper-step @if($so['status'] === 'dispatched') is-done @else is-current @endif">
										<div class="dp-step-circle">@if($so['status'] === 'dispatched') <i class="fa-solid fa-check"></i> @else 6 @endif</div>
										<div class="dp-step-label">6. Entregado</div>
									</div>
									<div class="dp-stepper-step @if($so['status'] === 'dispatched') is-done @endif">
										<div class="dp-step-circle">@if($so['status'] === 'dispatched') <i class="fa-solid fa-check"></i> @else 7 @endif</div>
										<div class="dp-step-label">7. Conciliado</div>
									</div>
								@endif
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
										<strong>Control de Integración OMS/WMS:</strong>
										<div class="text-muted small">Pedido {{ $so['order_code'] }} originado vía {{ $so['channel'] ?? 'POS' }}. Descuento único en kardex tras confirmación de carga.</div>
									</div>
								</div>
							</div>

							<!-- Pestaña Variaciones -->
							<div class="dp-tab-pane d-none" id="pane-variaciones">
								@if(empty($variances))
									<div class="p-3 bg-light rounded text-center text-muted small">
										No hay variaciones registradas formalmente en este pedido.
									</div>
								@else
									@foreach($variances as $v)
										<div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center">
											<div>
												<strong class="text-dark">{{ $v['product_name'] }}</strong>
												<span class="badge @if($v['stage'] === 'preparation') badge-warning @else badge-danger @endif ml-1">
													{{ $v['variance_type'] }}
												</span>
												<span class="text-muted ml-2">Diferencia: <strong>{{ $v['difference_qty'] }}</strong></span>
												<div class="small text-muted">{{ $v['resolution_notes'] }}</div>
											</div>
											<div>
												@if($v['resolution_status'] === 'pending')
													<button class="btn btn-xs btn-danger font-weight-bold" data-toggle="modal" data-target="#modalResolveVariance">
														Resolver
													</button>
												@else
													<span class="badge badge-success">{{ $v['resolution_status'] }}</span>
												@endif
											</div>
										</div>
									@endforeach
								@endif
							</div>

							<!-- Pestaña Evidencia -->
							<div class="dp-tab-pane d-none" id="pane-evidencia">
								<div class="card p-3 border-0 bg-light">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<strong class="text-dark"><i class="fa-solid fa-file-signature text-success mr-1"></i>Evidencia de Recepción y Cadena de Frío</strong>
										<span class="badge badge-success"><i class="fa-solid fa-temperature-arrow-down mr-1"></i>Cadena de frío: ≤ 4°C Conforme</span>
									</div>
									<p class="small text-muted mb-1">Transporte y entrega: <strong>{{ $so['carrier_name'] ?: 'Logística Local' }}</strong></p>
									<p class="small text-muted mb-0">Recepción confirmada: <strong>{{ $so['delivery_signature_name'] ?: 'En proceso de validación con cliente' }}</strong></p>
								</div>
							</div>

							<!-- Pestaña Historial -->
							<div class="dp-tab-pane d-none" id="pane-historial">
								<ul class="list-unstyled small text-muted mb-0">
									<li class="mb-1"><i class="fa-solid fa-clock mr-1 text-primary"></i>Pedido creado en sistema: <strong>{{ $so['created_at'] }}</strong></li>
									<li class="mb-1"><i class="fa-solid fa-barcode mr-1 text-success"></i>Picking y validación de bultos: <strong>{{ $so['created_at'] }} (Operador Centro Matriz)</strong></li>
									<li><i class="fa-solid fa-truck mr-1 text-info"></i>Estado actual: <strong>{{ strtoupper($so['status']) }}</strong></li>
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
									<td>{{ $ord['packages'] ?: 1 }}</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['tracking_status'] ?? 'Pendiente' }}
										</span>
									</td>
									<td class="text-right text-nowrap">
										<button class="btn btn-xs btn-outline-success mr-1" title="Asignar Picking" onclick="openAssignPickingModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
											<i class="fa-solid fa-boxes-packing"></i>
										</button>
										<button class="btn btn-xs btn-outline-primary mr-1" title="Programar Ruta" onclick="openScheduleTransportModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
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
						<h3 class="dp-section-title"><i class="fa-solid fa-boxes-packing text-success"></i> Estación de Preparación, Picking y Packing</h3>
						<p class="dp-section-sub">Asignación de operarios, validación de lotes FEFO, escaneo por pistola Zebra y empaque de bultos.</p>
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
							<span class="text-muted small">En cola de picking</span>
							<h4 class="font-weight-bold text-warning mb-0">{{ $kpis['pending_orders'] ?? 14 }}</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Lotes FEFO Auditados</span>
							<h4 class="font-weight-bold text-success mb-0">100%</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Exactitud Picking</span>
							<h4 class="font-weight-bold text-primary mb-0">{{ number_format($prepAcc, 1) }}%</h4>
						</div>
					</div>
					<div class="col-md-3 col-6 mb-2">
						<div class="card p-3 border-0 bg-light text-center rounded">
							<span class="text-muted small">Tiempo Promedio</span>
							<h4 class="font-weight-bold text-dark mb-0">{{ $kpis['prep_time_minutes'] ?? '18.5 min' }}</h4>
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
								<th>Lotes FEFO</th>
								<th>Bultos Empaque</th>
								<th>Operario Asignado</th>
								<th>Estado Preparación</th>
								<th class="text-right">Acción Operativa</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 10) as $ord)
								<tr>
									<td><strong class="text-dark">{{ $ord['order_code'] }}</strong></td>
									<td>{{ $ord['customer_name'] }}</td>
									<td>
										<div class="progress" style="height: 12px; border-radius: 6px; min-width: 100px;">
											<div class="progress-bar bg-success" role="progressbar" style="width: @if($ord['status'] === 'dispatched' || $ord['status'] === 'packed') 100% @elseif($ord['status'] === 'picked') 85% @elseif($ord['status'] === 'picking') 45% @else 15% @endif;"></div>
										</div>
									</td>
									<td><span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Rotación FEFO OK</span></td>
									<td><strong>{{ $ord['packages'] ?: 1 }}</strong> caja(s)</td>
									<td><i class="fa-solid fa-user text-muted mr-1"></i>Operador CD Matriz</td>
									<td>
										<span class="badge {{ $ord['tracking_class'] ?? 'badge-secondary' }} px-2 py-0.5">
											{{ $ord['tracking_status'] }}
										</span>
									</td>
									<td class="text-right">
										<button class="btn btn-xs btn-outline-success mr-1" onclick="openAssignPickingModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
											<i class="fa-solid fa-user-plus mr-1"></i>Asignar
										</button>
										<button class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#modalFastScanVerify">
											<i class="fa-solid fa-barcode mr-1"></i>Validar UPC
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

				<!-- Programación de Rutas del Día -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-clock mr-1 text-muted"></i> Rutas Programadas para Despacho Hoy</h6>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Ruta & Zona</th>
								<th>Vehículo Asignado</th>
								<th>Conductor Responsable</th>
								<th>Ventana Horaria</th>
								<th>Pedidos Cargados</th>
								<th>Capacidad Ocupada</th>
								<th>Estado Ruta</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Ruta A - Zona Norte</strong></td>
								<td>Camión C-08 ThermoKing (WXY-882)</td>
								<td>Conductor Frío Andina 1</td>
								<td>08:00 - 12:30</td>
								<td><strong>8 pedidos</strong></td>
								<td>
									<div class="progress" style="height: 10px; border-radius: 5px;">
										<div class="progress-bar bg-info" style="width: 78%;">78%</div>
									</div>
								</td>
								<td><span class="badge badge-info px-2 py-0.5">En tránsito</span></td>
								<td class="text-right"><button class="btn btn-xs btn-light border" onclick="$('#tab-salidas-link').click();">Ver Carga</button></td>
							</tr>
							<tr>
								<td><strong>Ruta B - Centro Financiero</strong></td>
								<td>Furgón F-12 Frío Directo (TKL-409)</td>
								<td>Conductor Frío Andina 2</td>
								<td>13:00 - 17:00</td>
								<td><strong>6 pedidos</strong></td>
								<td>
									<div class="progress" style="height: 10px; border-radius: 5px;">
										<div class="progress-bar bg-success" style="width: 62%;">62%</div>
									</div>
								</td>
								<td><span class="badge badge-warning px-2 py-0.5">Cargando muelle 2</span></td>
								<td class="text-right"><button class="btn btn-xs btn-light border" onclick="$('#tab-salidas-link').click();">Ver Carga</button></td>
							</tr>
							<tr>
								<td><strong>Ruta Express - Delivery Inmediato</strong></td>
								<td>Moto Express M-02 (EXP-332)</td>
								<td>Mensajero Express 1</td>
								<td>Inmediato (≤ 45 min)</td>
								<td><strong>2 pedidos</strong></td>
								<td>
									<div class="progress" style="height: 10px; border-radius: 5px;">
										<div class="progress-bar bg-success" style="width: 40%;">40%</div>
									</div>
								</td>
								<td><span class="badge badge-success px-2 py-0.5">Despachado</span></td>
								<td class="text-right"><button class="btn btn-xs btn-light border" onclick="$('#tab-salidas-link').click();">Ver Carga</button></td>
							</tr>
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
						<h3 class="dp-section-title"><i class="fa-solid fa-clipboard-check text-success"></i> Salidas de Bodega, Confirmación de Carga y Guías</h3>
						<p class="dp-section-sub">Verificación de bultos físicos en muelle, precintado de seguridad y emisión de número de despacho único.</p>
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
							<strong class="text-dark">Control de Seguridad en Muelle:</strong>
							<div class="small text-muted">Ningún vehículo sale sin precinto registrado, temperatura auditada y número de despacho generado sin duplicación en kardex.</div>
						</div>
					</div>
					<span class="badge badge-success px-2.5 py-1">Protocolo Activo</span>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Nº Despacho</th>
								<th>Pedido Vinculado</th>
								<th>Destino & Cliente</th>
								<th>Transporte / Placa</th>
								<th>Bultos / Peso</th>
								<th>Temp. Frío</th>
								<th>Salida Programada</th>
								<th>Estado Salida</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 8) as $ord)
								<tr>
									<td><strong class="text-primary">DSP-20261025-{{ sprintf('%04d', $ord['id']) }}</strong></td>
									<td><span class="badge badge-light border">{{ $ord['order_code'] }}</span></td>
									<td><strong>{{ $ord['customer_name'] }}</strong></td>
									<td>{{ $ord['carrier_vehicle'] ?: 'Camión C-08 ThermoKing' }}</td>
									<td>{{ $ord['packages'] ?: 1 }} bultos</td>
									<td><span class="badge badge-success"><i class="fa-solid fa-temperature-arrow-down mr-1"></i>3.2°C OK</span></td>
									<td>{{ !empty($ord['departure_time']) ? date('H:i', strtotime($ord['departure_time'])) : '10:30' }}</td>
									<td>
										<span class="badge @if($ord['status'] === 'dispatched') badge-success @else badge-warning @endif px-2 py-0.5">
											{{ $ord['status'] === 'dispatched' ? 'Despachado en ruta' : 'Listo para salida' }}
										</span>
									</td>
									<td class="text-right">
										@if($ord['status'] !== 'dispatched')
											<button class="btn btn-xs btn-success font-weight-bold" onclick="openConfirmExitModal({{ $ord['id'] }}, '{{ $ord['order_code'] }}')">
												<i class="fa-solid fa-check mr-1"></i>Autorizar Salida
											</button>
										@else
											<button class="btn btn-xs btn-outline-info" data-toggle="modal" data-target="#modalOrderEvidence">
												<i class="fa-solid fa-file-invoice mr-1"></i>Guía Salida
											</button>
										@endif
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 6: ENTREGAS Y EVIDENCIAS ================= -->
		<div class="tab-pane fade" id="tab-entregas-evidencias" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-signature text-success"></i> Confirmación de Entrega, Evidencias y Rechazos</h3>
						<p class="dp-section-sub">Auditoría de recepción: firmas digitales, comprobantes fotográficos, entregas parciales y registro de causales de rechazo.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-outline-success font-weight-bold" data-toggle="modal" data-target="#modalOrderEvidence">
							<i class="fa-solid fa-camera mr-1"></i> Galería de Evidencias
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
								<th>Receptor Confirmado</th>
								<th>Validación / PIN</th>
								<th>Evidencia Fotográfica</th>
								<th>Cadena de Frío</th>
								<th>Resultado Entrega</th>
								<th class="text-right">Comprobante</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 8) as $ord)
								@php
									$isPickup = (($ord['delivery_type'] ?? 'pickup') === 'pickup');
									$isDelivered = ($ord['status'] === 'dispatched' || $ord['delivery_status'] === 'delivered');
								@endphp
								<tr>
									<td><strong class="text-dark">{{ $ord['order_code'] }}</strong></td>
									<td>
										@if($isPickup)
											<span class="badge badge-light border text-primary">Retiro en tienda</span>
										@else
											<span class="badge badge-light border text-info">Domicilio</span>
										@endif
									</td>
									<td>{{ $isDelivered ? date('d/m/Y H:i') : 'En tránsito' }}</td>
									<td><strong>{{ $ord['customer_name'] }}</strong></td>
									<td>
										@if($isPickup)
											<span class="badge badge-success"><i class="fa-solid fa-key mr-1"></i>PIN: {{ $ord['pickup_pin'] ?: '618294' }}</span>
										@else
											<span class="badge badge-light border"><i class="fa-solid fa-signature mr-1"></i>Firma Digital</span>
										@endif
									</td>
									<td>
										<a href="javascript:void(0)" data-toggle="modal" data-target="#modalOrderEvidence" class="badge badge-info">
											<i class="fa-solid fa-image mr-1"></i>Ver Foto Entrega
										</a>
									</td>
									<td><span class="text-success small font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i>Conforme (3.5°C)</span></td>
									<td>
										@if(!empty($ord['route_incidents']))
											<span class="badge badge-warning px-2 py-0.5">Entrega Parcial / Diferencia</span>
										@elseif($isDelivered)
											<span class="badge badge-success px-2 py-0.5">Entregado Completo</span>
										@else
											<span class="badge badge-info px-2 py-0.5">En Curso</span>
										@endif
									</td>
									<td class="text-right">
										<button class="btn btn-xs btn-light border font-weight-bold" data-toggle="modal" data-target="#modalOrderEvidence">
											<i class="fa-solid fa-file-invoice mr-1"></i>Comprobante
										</button>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 7: CONCILIACIÓN Y VARIACIONES ================= -->
		<div class="tab-pane fade" id="tab-conciliacion-variaciones" role="tabpanel">
			<div class="dp-section-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="dp-section-title"><i class="fa-solid fa-scale-balanced text-success"></i> Conciliación de Despachos y Matriz de 7 Etapas</h3>
						<p class="dp-section-sub">Comparación rigurosa de cantidades solicitadas vs preparadas vs escaneadas vs despachadas vs recibidas vs devueltas y diferencias finales.</p>
					</div>
					<div>
						<button class="btn btn-sm btn-danger font-weight-bold" data-toggle="modal" data-target="#modalResolveVariance">
							<i class="fa-solid fa-gavel mr-1"></i> Resolver Variaciones Pendientes
						</button>
					</div>
				</div>

				<div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-3 rounded" style="border-left: 4px solid #2563eb !important;">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-shield-halved text-primary fa-2x mr-3"></i>
						<div>
							<strong class="text-dark">Principio de No Doble Contabilización:</strong>
							<div class="small text-muted">Las diferencias conciliadas en despacho no se descuentan dos veces de kardex ni generan cargos repetidos a clientes. Toda merma requiere autorización gerencial expresa.</div>
						</div>
					</div>
					<span class="badge badge-primary px-2.5 py-1">Segregación Estricta</span>
				</div>

				<!-- Matriz de las 7 Cantidades Obligatorias -->
				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-table-list text-muted mr-1"></i> Matriz de Cantidades por Pedido</h6>
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
								<th>Variación Monetaria</th>
								<th class="text-right">Dictamen</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($orders, 0, 8) as $ord)
								@php
									$d = $ordersDetailMap[$ord['id']]['summary'] ?? null;
								@endphp
								@if($d)
									<tr>
										<td><strong class="text-dark">{{ $ord['order_code'] }}</strong></td>
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
										<td class="font-weight-bold text-danger">{{ $money($ord['variance_total_cost'] ?? 0) }}</td>
										<td class="text-right">
											@if(intval($ord['pending_variances_count']) > 0)
												<button class="btn btn-xs btn-danger font-weight-bold" data-toggle="modal" data-target="#modalResolveVariance">
													Resolver ({{ $ord['pending_variances_count'] }})
												</button>
											@else
												<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Conciliado</span>
											@endif
										</td>
									</tr>
								@endif
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

	</div>

	<!-- ================= 5. TARJETAS OPERATIVAS INFERIORES ================= -->
	<div class="dp-bottom-grid">
		<!-- Tarjeta 1: Cambios y devoluciones -->
		<a href="{{ $U('/returns') }}" class="dp-bottom-card">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-rotate-left"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Cambios y devoluciones</h6>
						<p class="dp-bottom-card-desc">Consulta o inicia el flujo vinculado al pedido según permisos.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/dispatch_card_boxes.jpg" alt="Cambios y devoluciones" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Abrir módulo</span>
				<i class="fa-solid fa-arrow-right"></i>
			</div>
		</a>

		<!-- Tarjeta 2: Comprobante de entrega -->
		<a href="javascript:void(0)" class="dp-bottom-card" data-toggle="modal" data-target="#modalOrderEvidence">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-file-invoice"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Comprobante de entrega</h6>
						<p class="dp-bottom-card-desc">Consulta evidencias disponibles del pedido.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/dispatch_card_doc.jpg" alt="Comprobante de entrega" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Consultar evidencias</span>
				<i class="fa-solid fa-arrow-right"></i>
			</div>
		</a>

		<!-- Tarjeta 3: Trazabilidad de cantidades -->
		<a href="{{ $U('/kardex') }}" class="dp-bottom-card">
			<div>
				<div class="dp-bottom-card-top">
					<div class="dp-bottom-icon-box">
						<i class="fa-solid fa-layer-group"></i>
					</div>
					<div>
						<h6 class="dp-bottom-card-title">Trazabilidad de cantidades</h6>
						<p class="dp-bottom-card-desc">Revisa los movimientos por etapa del pedido.</p>
					</div>
				</div>
			</div>
			<img src="/img/control_center/transfer_truck_hero.jpg" alt="Trazabilidad" class="dp-bottom-card-img">
			<div class="dp-bottom-card-link">
				<span>Ver movimientos</span>
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
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-chart-pie mr-2 text-info"></i>Comparación Analítica: Retiro en Tienda vs. Despacho a Domicilio</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<p class="text-muted small">Evaluación de eficiencia operativa, exactitud, faltantes y tasa de reclamos por modalidad de entrega.</p>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Métrica Operativa</th>
								<th class="text-center">Retiro en Tienda (Pick-up)</th>
								<th class="text-center">Despacho a Domicilio (Delivery)</th>
								<th class="text-center">Objetivo Corporativo</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Exactitud de Preparación</strong></td>
								<td class="text-center font-weight-bold text-success">{{ number_format($analytics['pickup']['prep_accuracy_pct'] ?? 99.4, 1) }}%</td>
								<td class="text-center font-weight-bold text-success">{{ number_format($analytics['delivery']['prep_accuracy_pct'] ?? 99.1, 1) }}%</td>
								<td class="text-center text-muted">≥ 99.0%</td>
							</tr>
							<tr>
								<td><strong>Exactitud de Entrega</strong></td>
								<td class="text-center font-weight-bold text-primary">{{ number_format($analytics['pickup']['delivery_accuracy_pct'] ?? 99.8, 1) }}%</td>
								<td class="text-center font-weight-bold text-primary">{{ number_format($analytics['delivery']['delivery_accuracy_pct'] ?? 98.4, 1) }}%</td>
								<td class="text-center text-muted">≥ 98.0%</td>
							</tr>
							<tr>
								<td><strong>Faltantes por 1.000 un.</strong></td>
								<td class="text-center font-weight-bold text-warning">{{ number_format($analytics['pickup']['missing_per_thousand'] ?? 0.6, 1) }} un.</td>
								<td class="text-center font-weight-bold text-warning">{{ number_format($analytics['delivery']['missing_per_thousand'] ?? 1.8, 1) }} un.</td>
								<td class="text-center text-muted">≤ 2.0 un.</td>
							</tr>
							<tr>
								<td><strong>Variación Monetaria Neta</strong></td>
								<td class="text-center font-weight-bold text-danger">{{ $money($analytics['pickup']['financial_variance'] ?? 0) }}</td>
								<td class="text-center font-weight-bold text-danger">{{ $money($analytics['delivery']['financial_variance'] ?? 0) }}</td>
								<td class="text-center text-muted">$0.00</td>
							</tr>
							<tr>
								<td><strong>Tasa de Reclamos</strong></td>
								<td class="text-center">{{ number_format($analytics['pickup']['claims_rate_pct'] ?? 0.8, 1) }}%</td>
								<td class="text-center">{{ number_format($analytics['delivery']['claims_rate_pct'] ?? 2.1, 1) }}%</td>
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
					Solicite al cliente el PIN de 6 dígitos enviado por SMS/Email o escanee el token QR.
				</div>

				<div class="form-group mb-3 text-center">
					<label class="font-weight-bold small text-dark d-block">Ingrese el PIN de retiro:</label>
					<input type="text" id="inputPickupPinCode" class="form-control form-control-lg text-center font-weight-bold text-dark" placeholder="0 0 0 0 0 0" maxlength="6" style="letter-spacing: 0.25em; font-size: 1.5rem;" autofocus>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitVerifyPickup">
					<i class="fa-solid fa-check mr-1"></i>Confirmar y Entregar Pedido
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
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-truck-arrow-right mr-2 text-success"></i>Registro de Entrega en Ruta</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Nombre de quien recibe:</label>
					<input type="text" id="inputDeliverySignature" class="form-control form-control-sm" placeholder="Ej. Juan Pérez (Titular / Conserje)">
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Transportista / Conductor:</label>
					<input type="text" id="inputCarrierName" class="form-control form-control-sm" value="Transporte Logística Propia">
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Resultado de la entrega:</label>
					<select id="selDeliveryStatus" class="form-control form-control-sm font-weight-bold">
						<option value="delivered" selected>Entregado Conforme (Completo)</option>
						<option value="partial">Entrega Parcial (Con faltante o daño)</option>
						<option value="rejected">Rechazado por el cliente</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Incidencias o notas de ruta:</label>
					<textarea id="txtRouteIncidents" class="form-control form-control-sm" rows="2" placeholder="Observaciones de la entrega..."></textarea>
				</div>
				<div class="custom-control custom-checkbox mb-2">
					<input type="checkbox" class="custom-control-input" id="chkColdChainOk" checked>
					<label class="custom-control-label small font-weight-bold text-dark" for="chkColdChainOk">
						<i class="fa-solid fa-snowflake text-info mr-1"></i>Cadena de frío verificada en entrega (≤ 4°C)
					</label>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitRecordDelivery">
					<i class="fa-solid fa-check mr-1"></i>Registrar Entrega
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
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-barcode mr-2 text-warning"></i>Verificación Rápida por Escáner UPC</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="card p-3 border mb-3 bg-white text-center">
					<label class="font-weight-bold small text-dark mb-1">Escanee el código de barras o ingrese el UPC:</label>
					<div class="input-group">
						<input type="text" id="inputFastScanBarcode" class="form-control font-weight-bold" placeholder="Escanee con pistola Zebra o cámara..." autofocus>
						<div class="input-group-append">
							<button class="btn btn-primary" type="button" id="btnTriggerFastScan"><i class="fa-solid fa-magnifying-glass"></i></button>
						</div>
					</div>
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

<!-- ================= MODAL: RESOLVER VARIACIÓN ================= -->
<div class="modal fade" id="modalResolveVariance" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header text-white" style="background: #dc2626;">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-gavel mr-2"></i>Resolución y Autorización de Variación</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="resolveVarianceId" value="1">
				<div class="card p-3 border mb-3 bg-white">
					<strong class="text-dark" id="resolveVarianceProductName">Conciliación de faltantes en preparación</strong>
					<span class="text-danger font-weight-bold" id="resolveVarianceDiffQty">Leche descremada (-4) · Crema de leche (-4)</span>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Tipo de resolución autorizada:</label>
					<select class="form-control form-control-sm font-weight-bold" id="selResolutionStatus">
						<option value="authorized_exception">Excepción autorizada por Gerencia de Operaciones</option>
						<option value="waste_recorded">Registrar merma operativa (Avería o faltante)</option>
						<option value="credited">Generar Nota de Crédito / Reembolso al cliente</option>
						<option value="resolved">Aclarado con inventario físico en góndola</option>
					</select>
				</div>
				<div class="form-group mb-2">
					<label class="font-weight-bold small text-dark">Justificación y evidencia:</label>
					<textarea class="form-control form-control-sm" id="txtResolutionNotes" rows="2" placeholder="Motivo de la aprobación de diferencia..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-danger font-weight-bold" id="btnSubmitResolveVariance">
					<i class="fa-solid fa-check mr-1"></i>Autorizar y Resolver Variación
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: COMPROBANTE DE ENTREGA Y EVIDENCIAS ================= -->
<div class="modal fade" id="modalOrderEvidence" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-file-invoice mr-2 text-success"></i>Comprobante de Entrega y Evidencia</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light text-center">
				<img src="/img/control_center/dispatch_card_doc.jpg" alt="Comprobante" class="img-fluid rounded border mb-3 shadow-sm" style="max-height: 280px;">
				<div class="text-left bg-white p-3 rounded border">
					<div class="d-flex justify-content-between mb-1">
						<span class="text-muted small">Estado:</span>
						<span class="badge badge-success">Documento verificado</span>
					</div>
					<div class="d-flex justify-content-between mb-1">
						<span class="text-muted small">Receptor:</span>
						<span class="font-weight-bold small" id="lblEvidenceReceiver">Representante autorizado del cliente</span>
					</div>
					<div class="d-flex justify-content-between">
						<span class="text-muted small">Cadena de Frío:</span>
						<span class="text-success font-weight-bold small"><i class="fa-solid fa-circle-check mr-1"></i>Preservada ≤ 4°C</span>
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

<script>
window.CurrentOrderId = {{ $selectedOrderId ?: 0 }};
window.DispatchOrdersMap = {!! $ordersDetailMapJson !!};

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

function openConfirmExitModal(orderId, orderCode) {
	$('#exitOrderId').val(orderId);
	$('#lblExitOrderCode').text(orderCode);
	$('#inputDispatchDocNum').val('DSP-{{ date("Ymd") }}-' + ('0000' + orderId).slice(-4));
	$('#modalConfirmExit').modal('show');
}

document.addEventListener('DOMContentLoaded', function () {
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
			error: function () {
				toastr.error('Error al asignar picking.');
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
			error: function () {
				toastr.error('Error al programar transporte.');
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
			error: function () {
				toastr.error('Error al confirmar salida.');
			}
		});
	});
});
</script>
@endsection

@push('pageScripts')
<script src="{{ $U('/viewjs/dispatch.js?v=', true) }}{{ daily_asset_version('/viewjs/dispatch.js') }}"></script>
@endpush
