@extends('layout.default')

@section('title', 'Devoluciones del POS | DAILY')

@push('pageStyles')
<style>
	:root {
		--pos-ret-primary: #059669;
		--pos-ret-primary-light: #ecfdf5;
		--pos-ret-border: #e2e8f0;
		--pos-ret-text-main: #0f172a;
		--pos-ret-text-sub: #64748b;
	}

	.pos-ret-container {
		padding: 1.25rem;
		max-width: 1600px;
		margin: 0 auto;
	}

	/* Hero Card */
	.pos-ret-hero {
		background: #ffffff;
		border: 1px solid var(--pos-ret-border);
		border-radius: 12px;
		padding: 18px 24px;
		margin-bottom: 1.25rem;
		display: flex;
		justify-content: space-between;
		align-items: center;
		flex-wrap: wrap;
		gap: 16px;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
	}
	.pos-ret-hero-left {
		display: flex;
		align-items: center;
		gap: 16px;
	}
	.pos-ret-hero-icon {
		width: 48px;
		height: 48px;
		border-radius: 10px;
		background: #059669;
		color: #ffffff;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 1.4rem;
		flex-shrink: 0;
	}
	.pos-ret-title {
		font-size: 1.35rem;
		font-weight: 800;
		color: var(--pos-ret-text-main);
		margin: 0 0 2px 0;
	}
	.pos-ret-desc {
		font-size: 0.85rem;
		color: var(--pos-ret-text-sub);
		margin: 0;
	}
	.pos-ret-hero-actions {
		display: flex;
		align-items: center;
		gap: 10px;
		flex-wrap: wrap;
	}
	.pos-ret-select {
		height: 38px;
		border-radius: 8px;
		border: 1px solid #cbd5e1;
		padding: 0 12px;
		font-size: 0.85rem;
		color: #334155;
		background-color: #f8fafc;
		font-weight: 600;
	}
	.pos-ret-btn-green {
		height: 38px;
		background: #059669;
		color: #ffffff !important;
		border: none;
		border-radius: 8px;
		padding: 0 16px;
		font-size: 0.85rem;
		font-weight: 700;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		text-decoration: none !important;
		transition: background 0.15s ease;
	}
	.pos-ret-btn-green:hover {
		background: #047857;
	}
	.pos-ret-btn-outline {
		height: 38px;
		background: #ffffff;
		color: #334155 !important;
		border: 1px solid #cbd5e1;
		border-radius: 8px;
		padding: 0 14px;
		font-size: 0.85rem;
		font-weight: 600;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		text-decoration: none !important;
		transition: all 0.15s ease;
	}
	.pos-ret-btn-outline:hover {
		background: #f8fafc;
		border-color: #94a3b8;
	}

	/* 9-Fases Lifecycle Stepper */
	.pos-ret-lifecycle-card {
		background: #ffffff;
		border: 1px solid var(--pos-ret-border);
		border-radius: 12px;
		padding: 14px 18px;
		margin-bottom: 1.25rem;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
	}
	.pos-ret-stepper-wrap {
		overflow-x: auto;
		padding: 8px 4px 4px 4px;
	}
	.pos-ret-stepper {
		display: flex;
		align-items: center;
		min-width: 950px;
		justify-content: space-between;
	}
	.pos-ret-step {
		display: flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		padding: 6px 10px;
		border-radius: 8px;
		transition: background 0.15s ease;
	}
	.pos-ret-step:hover {
		background: #f8fafc;
	}
	.pos-ret-step-num {
		width: 28px;
		height: 28px;
		border-radius: 50%;
		background: #f1f5f9;
		color: #475569;
		border: 1.5px solid #cbd5e1;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.78rem;
		font-weight: 700;
		flex-shrink: 0;
	}
	.pos-ret-step.is-active .pos-ret-step-num,
	.pos-ret-step:hover .pos-ret-step-num {
		background: #059669;
		color: #ffffff;
		border-color: #059669;
	}
	.pos-ret-step-divider {
		flex: 1;
		height: 2px;
		background: #e2e8f0;
		min-width: 12px;
		margin: 0 4px;
	}

	/* 7 KPIs Grid */
	.pos-ret-kpis-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 12px;
		margin-bottom: 1.25rem;
	}
	@media (max-width: 1200px) {
		.pos-ret-kpis-grid {
			grid-template-columns: repeat(2, 1fr);
		}
	}
	@media (max-width: 576px) {
		.pos-ret-kpis-grid {
			grid-template-columns: 1fr;
		}
	}
	.pos-ret-kpi-card {
		background: #ffffff;
		border: 1px solid var(--pos-ret-border);
		border-radius: 10px;
		padding: 14px 16px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
		transition: transform 0.15s ease, border-color 0.15s ease;
	}
	.pos-ret-kpi-card:hover {
		transform: translateY(-2px);
		border-color: #a7f3d0;
	}
	.pos-ret-kpi-label {
		font-size: 0.78rem;
		font-weight: 600;
		color: var(--pos-ret-text-sub);
		margin-bottom: 2px;
	}
	.pos-ret-kpi-val {
		font-size: 1.35rem;
		font-weight: 800;
		color: var(--pos-ret-text-main);
		line-height: 1.2;
	}
	.pos-ret-kpi-sub {
		font-size: 0.72rem;
		color: #64748b;
		margin-top: 2px;
	}
	.pos-ret-kpi-icon-box {
		width: 42px;
		height: 42px;
		border-radius: 8px;
		background: #f8fafc;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 1.2rem;
		color: #64748b;
	}

	/* Navigation Tabs */
	.pos-ret-nav {
		display: flex;
		gap: 6px;
		border-bottom: 2px solid #e2e8f0;
		margin-bottom: 1.25rem;
		overflow-x: auto;
		padding-bottom: 2px;
	}
	.pos-ret-nav-link {
		padding: 9px 16px;
		font-size: 0.85rem;
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
		background-color: transparent;
		transition: all 0.15s ease;
	}
	.pos-ret-nav-link:hover {
		color: #059669;
		background-color: #f8fafc;
	}
	.pos-ret-nav-link.active {
		color: #059669;
		border-bottom-color: #059669;
		background-color: #ffffff;
		box-shadow: 0 -2px 4px rgba(0,0,0,0.02);
	}

	.pos-ret-card {
		background: #ffffff;
		border: 1px solid var(--pos-ret-border);
		border-radius: 12px;
		padding: 20px;
		margin-bottom: 1.25rem;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
	}
	.pos-ret-section-title {
		font-size: 1.05rem;
		font-weight: 700;
		color: #0f172a;
		margin-bottom: 4px;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.pos-ret-section-sub {
		font-size: 0.82rem;
		color: #64748b;
		margin-bottom: 14px;
	}

	/* Search bar */
	.pos-search-box {
		display: flex;
		gap: 8px;
		margin-bottom: 14px;
	}
	.pos-search-input {
		flex: 1;
		height: 42px;
		border: 1.5px solid #cbd5e1;
		border-radius: 8px;
		padding: 0 14px;
		font-size: 0.92rem;
		transition: border-color 0.15s ease;
	}
	.pos-search-input:focus {
		border-color: #059669;
		outline: none;
		box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
	}
	.pos-ticket-badge {
		cursor: pointer;
		padding: 5px 10px;
		border-radius: 6px;
		border: 1px solid #cbd5e1;
		background: #f8fafc;
		font-size: 0.78rem;
		font-weight: 600;
		color: #334155;
		transition: all 0.15s ease;
	}
	.pos-ticket-badge:hover {
		background: #ecfdf5;
		border-color: #059669;
		color: #059669;
	}

	/* Sale detail header */
	.pos-sale-banner {
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 14px 18px;
		margin-bottom: 14px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		flex-wrap: wrap;
		gap: 12px;
	}
</style>
@endpush

@section('content')
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
@endphp

<div class="pos-ret-container">

	<!-- ====================================================================
	     1. ENCABEZADO HERO
	     ==================================================================== -->
	<div class="pos-ret-hero">
		<div class="pos-ret-hero-left">
			<div class="pos-ret-hero-icon">
				<i class="fa-solid fa-cash-register"></i>
			</div>
			<div>
				<h1 class="pos-ret-title">Devoluciones del POS</h1>
				<p class="pos-ret-desc">Búsqueda de ticket, validación comercial, inspección de producto, reembolsos autorizados y actualización de kardex.</p>
			</div>
		</div>

		<div class="pos-ret-hero-actions">
			<form method="get" class="d-inline-flex gap-2 align-items-center mb-0" id="formBranchFilter">
				<select name="branch_id" class="pos-ret-select" onchange="this.form.submit()">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>
			</form>

			<button type="button" class="pos-ret-btn-green" onclick="$('#tab-nueva-devolucion-tab').click(); $('#posSearchQuery').focus();">
				<i class="fa-solid fa-barcode"></i>
				<span>Buscar Venta / Ticket</span>
			</button>

			<a href="{{ $U('/pos') }}" class="pos-ret-btn-outline" title="Ir a Terminal POS de Venta Rápida">
				<i class="fa-solid fa-cash-register text-success"></i>
				<span>Punto de Venta (POS)</span>
			</a>

			<a href="{{ $U('/returns') }}" class="pos-ret-btn-outline" title="Ir al Centro General de Devoluciones">
				<i class="fa-solid fa-arrow-rotate-left text-primary"></i>
				<span>Devoluciones Generales</span>
			</a>

			<a href="{{ $U('/sales') }}" class="pos-ret-btn-outline" title="Historial General de Ventas">
				<i class="fa-solid fa-chart-line text-info"></i>
				<span>Ventas</span>
			</a>
		</div>
	</div>

	<!-- ====================================================================
	     2. CICLO OPERATIVO VISUAL (9 FASES OBLIGATORIAS)
	     ==================================================================== -->
	<div class="pos-ret-lifecycle-card">
		<div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
			<div class="d-flex align-items-center">
				<span class="badge badge-success px-2.5 py-1 text-uppercase font-weight-700 mr-2" style="font-size: 0.72rem; letter-spacing: 0.05em; background-color: #059669;">
					<i class="fa-solid fa-arrows-spin mr-1"></i> Ciclo de Devolución POS
				</span>
				<h6 class="mb-0 font-weight-bold text-dark" style="font-size: 0.95rem;">Flujo Seguro de Devoluciones y Reembolsos en Mostrador</h6>
			</div>
			<div class="d-flex align-items-center mt-1 mt-md-0">
				<span class="text-muted small"><i class="fa-solid fa-shield-halved text-success mr-1"></i> Control Dual · Cero reembolsos duplicados · Bloqueo de excedentes</span>
			</div>
		</div>

		<div class="pos-ret-stepper-wrap">
			<div class="pos-ret-stepper" data-testid="pos-returns-stepper">
				<!-- Fase 1: Venta POS -->
				<div class="pos-ret-step is-active" onclick="$('#tab-nueva-devolucion-tab').click();">
					<div class="pos-ret-step-num">1</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Venta POS</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Ticket Original</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 2: Solicitud -->
				<div class="pos-ret-step" onclick="$('#tab-nueva-devolucion-tab').click();">
					<div class="pos-ret-step-num">2</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Solicitud</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Motivo & SKU</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 3: Validación -->
				<div class="pos-ret-step" onclick="$('#tab-nueva-devolucion-tab').click();">
					<div class="pos-ret-step-num">3</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Validación</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Plazo & Unidades</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 4: Recepción -->
				<div class="pos-ret-step" onclick="$('#tab-inspeccion-inventario-tab').click();">
					<div class="pos-ret-step-num">4</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Recepción</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Física en Caja</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 5: Inspección -->
				<div class="pos-ret-step" onclick="$('#tab-inspeccion-inventario-tab').click();">
					<div class="pos-ret-step-num">5</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Inspección</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Estado Físico</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 6: Autorización -->
				<div class="pos-ret-step" onclick="$('#tab-control-antifraude-tab').click();">
					<div class="pos-ret-step-num">6</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Autorización</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Supervisor / PIN</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 7: Reembolso o cambio -->
				<div class="pos-ret-step" onclick="$('#tab-reembolsos-pasarela-tab').click();">
					<div class="pos-ret-step-num">7</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Reembolso</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Dinero / Vale</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 8: Actualización de inventario -->
				<div class="pos-ret-step" onclick="$('#tab-inspeccion-inventario-tab').click();">
					<div class="pos-ret-step-num">8</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Inventario</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Stock / Merma</span>
					</div>
				</div>
				<div class="pos-ret-step-divider"></div>

				<!-- Fase 9: Cierre -->
				<div class="pos-ret-step" onclick="$('#tab-historial-devoluciones-tab').click();">
					<div class="pos-ret-step-num">9</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong style="font-size: 0.8rem; color: #0f172a;">Cierre</strong>
						<span style="font-size: 0.68rem; color: #64748b;">Comprobante</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     3. LOS 7 INDICADORES CLAVE CONSOLIDADOS (KPIS)
	     ==================================================================== -->
	<div class="pos-ret-kpis-grid" data-testid="pos-returns-seven-kpis">
		<!-- KPI 1: Total devuelto -->
		<div class="pos-ret-kpi-card" data-testid="kpi-total-devuelto">
			<div>
				<div class="pos-ret-kpi-label">Total devuelto</div>
				<div class="pos-ret-kpi-val text-danger">{{ $money($kpis['total_returned_amount'] ?? 0) }}</div>
				<div class="pos-ret-kpi-sub"><i class="fa-solid fa-arrow-down mr-1"></i>Reembolsos y vales emitidos</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-danger" style="background: #fef2f2;">
				<i class="fa-solid fa-hand-holding-dollar"></i>
			</div>
		</div>

		<!-- KPI 2: Porcentaje de devoluciones -->
		<div class="pos-ret-kpi-card" data-testid="kpi-porcentaje-devoluciones">
			<div>
				<div class="pos-ret-kpi-label">Tasa de devoluciones</div>
				<div class="pos-ret-kpi-val text-primary" style="color: #0284c7;">{{ number_format($kpis['return_rate_pct'] ?? 0.8, 2) }}%</div>
				<div class="pos-ret-kpi-sub">Sobre ventas POS totales (Meta ≤ 2.0%)</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-primary" style="background: #f0f9ff;">
				<i class="fa-solid fa-percent"></i>
			</div>
		</div>

		<!-- KPI 3: Motivos principales -->
		<div class="pos-ret-kpi-card" data-testid="kpi-motivos-principales">
			<div>
				<div class="pos-ret-kpi-label">Motivo principal</div>
				<div class="pos-ret-kpi-val" style="font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
					{{ !empty($topReasons) ? array_key_first($topReasons) : 'Defecto fábrica' }}
				</div>
				<div class="pos-ret-kpi-sub">{{ !empty($topReasons) ? reset($topReasons) : 0 }} casos registrados</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-warning" style="background: #fffbeb;">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 4: Productos más devueltos -->
		<div class="pos-ret-kpi-card" data-testid="kpi-productos-mas-devueltos">
			<div>
				<div class="pos-ret-kpi-label">Top producto devuelto</div>
				<div class="pos-ret-kpi-val" style="font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
					{{ !empty($topProducts) ? $topProducts[0]['name'] : 'Sin casos' }}
				</div>
				<div class="pos-ret-kpi-sub">{{ !empty($topProducts) ? $topProducts[0]['units'] : 0 }} unidades devueltas</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-info" style="background: #f0fdf4;">
				<i class="fa-solid fa-box-open"></i>
			</div>
		</div>

		<!-- KPI 5: Reembolsos pendientes -->
		<div class="pos-ret-kpi-card" data-testid="kpi-reembolsos-pendientes">
			<div>
				<div class="pos-ret-kpi-label">Reembolsos pendientes</div>
				<div class="pos-ret-kpi-val text-warning">{{ $kpis['pending_refunds_count'] ?? 0 }}</div>
				<div class="pos-ret-kpi-sub">En revisión técnica o cuarentena</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-warning" style="background: #fffbeb;">
				<i class="fa-solid fa-clock-rotate-left"></i>
			</div>
		</div>

		<!-- KPI 6: Tiempo de resolución -->
		<div class="pos-ret-kpi-card" data-testid="kpi-tiempo-resolucion">
			<div>
				<div class="pos-ret-kpi-label">Tiempo de resolución</div>
				<div class="pos-ret-kpi-val text-success">{{ number_format($kpis['avg_resolution_time_minutes'] ?? 5.2, 1) }} min</div>
				<div class="pos-ret-kpi-sub">Atención en mostrador de caja</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-success" style="background: #f0fdf4;">
				<i class="fa-solid fa-stopwatch"></i>
			</div>
		</div>

		<!-- KPI 7: Impacto financiero -->
		<div class="pos-ret-kpi-card" data-testid="kpi-impacto-financiero">
			<div>
				<div class="pos-ret-kpi-label">Impacto financiero neto</div>
				<div class="pos-ret-kpi-val text-danger">{{ $money($kpis['total_loss'] ?? 0) }}</div>
				<div class="pos-ret-kpi-sub">Pérdida en mermas definitivas</div>
			</div>
			<div class="pos-ret-kpi-icon-box text-danger" style="background: #fef2f2;">
				<i class="fa-solid fa-scale-unbalanced-flip"></i>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     4. BANNER DE CONTROL Y POLÍTICA RETAIL
	     ==================================================================== -->
	<div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-4 rounded" style="border-left: 4px solid #059669 !important; background: #ffffff;">
		<div class="d-flex align-items-center">
			<i class="fa-solid fa-shield-halved text-success fa-2x mr-3"></i>
			<div>
				<strong class="text-dark">Políticas Comerciales & Salvaguardas Sanitarias de Caja:</strong>
				<div class="small text-muted">
					1. Límite de devolución: 30 días con ticket original. &bull;
					2. Prohibido devolver más unidades que las vendidas. &bull;
					3. Alimentos perecederos o abiertos van a merma o cuarentena técnica, jamás a reventa directa. &bull;
					4. Tarjetas reembolsadas vía Sandbox Gateway PCI-DSS (solo últimos 4 dígitos visibles).
				</div>
			</div>
		</div>
		<span class="badge badge-success px-2.5 py-1">Auditoría Dual Activa</span>
	</div>

	<!-- ====================================================================
	     5. PESTAÑAS OPERATIVAS (8 COMPONENTES)
	     ==================================================================== -->
	<ul class="nav pos-ret-nav" id="posRetNav" role="tablist" data-testid="pos-returns-main-tabs">
		<li class="nav-item">
			<a class="pos-ret-nav-link active" id="tab-nueva-devolucion-tab" data-toggle="pill" href="#tab-nueva-devolucion" role="tab">
				<i class="fa-solid fa-cash-register"></i> 1. Nueva Devolución & Búsqueda
			</a>
		</li>
		<li class="nav-item">
			<a class="pos-ret-nav-link" id="tab-historial-devoluciones-tab" data-toggle="pill" href="#tab-historial-devoluciones" role="tab">
				<i class="fa-solid fa-clock-rotate-left"></i> 2. Historial & Auditoría
			</a>
		</li>
		<li class="nav-item">
			<a class="pos-ret-nav-link" id="tab-control-antifraude-tab" data-toggle="pill" href="#tab-control-antifraude" role="tab">
				<i class="fa-solid fa-user-shield"></i> 3. Control Anti-Fraude & Alertas
			</a>
		</li>
		<li class="nav-item">
			<a class="pos-ret-nav-link" id="tab-inspeccion-inventario-tab" data-toggle="pill" href="#tab-inspeccion-inventario" role="tab">
				<i class="fa-solid fa-boxes-stacked"></i> 4. Inspección & Destinos Kardex
			</a>
		</li>
		<li class="nav-item">
			<a class="pos-ret-nav-link" id="tab-reembolsos-pasarela-tab" data-toggle="pill" href="#tab-reembolsos-pasarela" role="tab">
				<i class="fa-solid fa-credit-card"></i> 5. Reembolsos & Pasarelas Demo
			</a>
		</li>
	</ul>

	<!-- CONTENIDO DE PESTAÑAS -->
	<div class="tab-content" id="posRetTabContent">

		<!-- ================= TAB 1: NUEVA DEVOLUCIÓN & BÚSQUEDA ================= -->
		<div class="tab-pane fade show active" id="tab-nueva-devolucion" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title">
					<i class="fa-solid fa-magnifying-glass text-success"></i> 1. Búsqueda y Validación de Venta Original
				</h3>
				<p class="pos-ret-section-sub">Ingrese el número de ticket, factura, cliente o escanee el código de barras del producto vendido.</p>

				<!-- Buscador reactivo -->
				<div class="pos-search-box">
					<input type="text" class="pos-search-input" id="posSearchQuery" placeholder="Buscar por Nº de Ticket (ej: VTA-20261105-08820), cliente, SKU o ID de transacción..." autocomplete="off">
					<button type="button" class="btn btn-success font-weight-bold px-4" id="btnSearchSale">
						<i class="fa-solid fa-search mr-1"></i> Buscar Venta
					</button>
				</div>

				<!-- Tickets Rápidos Recientes -->
				<div class="d-flex align-items-center flex-wrap gap-2 mb-4" id="recentTicketsWrap">
					<span class="small font-weight-bold text-muted mr-1"><i class="fa-solid fa-bolt text-warning mr-1"></i>Tickets recientes:</span>
					@foreach(array_slice($recentSales, 0, 6) as $rs)
						<button type="button" class="pos-ticket-badge" onclick="loadSaleDetail('{{ $rs['sale_number'] }}')">
							{{ $rs['sale_number'] }} · {{ $money($rs['total_amount']) }}
						</button>
					@endforeach
				</div>

				<hr class="my-3">

				<!-- Área del Ticket Seleccionado -->
				<div id="saleDetailContainer" style="display: none;">
					<div class="pos-sale-banner" id="saleBannerHeader">
						<div>
							<span class="badge badge-success px-2 py-1 font-weight-bold mr-2" id="valSaleNumber">VTA-XXXX</span>
							<strong class="text-dark" id="valCustomerName">Cliente</strong> · 
							<span class="text-muted small" id="valBranchName">Sucursal</span>
						</div>
						<div class="small">
							<span class="mr-3"><i class="fa-solid fa-calendar mr-1 text-muted"></i>Fecha: <strong id="valSaleDate">--</strong></span>
							<span class="mr-3"><i class="fa-solid fa-credit-card mr-1 text-muted"></i>Pago: <strong id="valPaymentMethod">--</strong></span>
							<span><i class="fa-solid fa-receipt mr-1 text-muted"></i>Total Venta: <strong class="text-success" id="valTotalAmount">$0.00</strong></span>
						</div>
					</div>

					<div class="table-responsive bg-white rounded border mb-4">
						<table class="table table-sm table-hover mb-0" id="tableSaleItems" style="font-size: 0.85rem;">
							<thead class="thead-light">
								<tr>
									<th>Producto & SKU</th>
									<th>Vendidas</th>
									<th>Ya devueltas</th>
									<th><span class="text-success font-weight-bold">Disponibles</span></th>
									<th style="width: 110px;">Cant. a Devolver</th>
									<th style="width: 170px;">Estado Inspección</th>
									<th style="width: 160px;">Resolución</th>
									<th style="width: 150px;">Destino Inventario</th>
									<th style="width: 140px;">Supervisor</th>
									<th class="text-right" style="width: 180px;">Acciones</th>
								</tr>
							</thead>
							<tbody id="saleItemsTbody">
								<!-- Dinámico vía JS -->
							</tbody>
						</table>
					</div>
				</div>

				<div id="saleEmptyPrompt" class="text-center py-5 text-muted">
					<i class="fa-solid fa-receipt fa-3x mb-3 text-muted" style="opacity: 0.35;"></i>
					<h5>Seleccione o busque un ticket de venta</h5>
					<p class="small mb-0">Podrá inspeccionar los productos, verificar la cantidad elegible y emitir el reembolso o cambio.</p>
				</div>
			</div>
		</div>

		<!-- ================= TAB 2: HISTORIAL & AUDITORÍA DE DEVOLUCIONES ================= -->
		<div class="tab-pane fade" id="tab-historial-devoluciones" role="tabpanel">
			<div class="pos-ret-card">
				<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
					<div>
						<h3 class="pos-ret-section-title"><i class="fa-solid fa-list-check text-success"></i> Bitácora de Devoluciones y Auditoría POS</h3>
						<p class="pos-ret-section-sub">Registro cronológico de todas las operaciones de devolución, cambio, reembolso y rechazo en caja.</p>
					</div>
					<div>
						<button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print();">
							<i class="fa-solid fa-print mr-1"></i> Imprimir Reporte
						</button>
					</div>
				</div>

				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" id="tablePosReturnsHistory" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Código Devolución</th>
								<th>Ticket Venta</th>
								<th>Cliente</th>
								<th>Producto</th>
								<th>Cant.</th>
								<th>Reembolso ($)</th>
								<th>Resolución</th>
								<th>Inspección</th>
								<th>Destino Kardex</th>
								<th>Método / Pasarela</th>
								<th>Cajero / Supervisor</th>
								<th>Fecha & Hora</th>
								<th class="text-right">Comprobante</th>
							</tr>
						</thead>
						<tbody>
							@forelse($returns as $r)
								<tr>
									<td><strong class="text-success">{{ $r['return_code'] }}</strong></td>
									<td>
										<a href="javascript:void(0)" onclick="loadSaleDetail('{{ $r['ticket_number'] }}')" class="font-weight-bold text-dark">
											{{ $r['ticket_number'] ?: 'Sin ticket' }}
										</a>
									</td>
									<td>{{ $r['customer_name'] }}</td>
									<td><strong>{{ $r['product_name'] }}</strong> <span class="text-muted small">({{ $r['sku'] }})</span></td>
									<td>{{ $r['quantity'] }}</td>
									<td class="font-weight-bold @if($r['status'] === 'rechazada') text-muted @else text-danger @endif">
										{{ $money($r['customer_refund_amount']) }}
									</td>
									<td>
										@if($r['resolution'] === 'reembolso')
											<span class="badge badge-success px-2 py-0.5"><i class="fa-solid fa-money-bill-wave mr-1"></i>Reembolso</span>
										@elseif($r['resolution'] === 'cambio')
											<span class="badge badge-primary px-2 py-0.5"><i class="fa-solid fa-rotate mr-1"></i>Cambio</span>
										@elseif($r['resolution'] === 'credito_tienda')
											<span class="badge badge-info px-2 py-0.5"><i class="fa-solid fa-ticket mr-1"></i>Vale Tienda</span>
										@elseif($r['resolution'] === 'rechazo_justificado')
											<span class="badge badge-danger px-2 py-0.5"><i class="fa-solid fa-ban mr-1"></i>Rechazada</span>
										@else
											<span class="badge badge-secondary px-2 py-0.5">{{ $r['resolution'] }}</span>
										@endif
									</td>
									<td>
										<span class="small text-muted">{{ $r['condition_state'] ?: 'sin_uso' }}</span>
									</td>
									<td>
										<span class="badge badge-light border text-dark">{{ $r['classification'] ?: 'reincorporacion' }}</span>
									</td>
									<td>
										@if($r['refund_method'] === 'card')
											<span class="badge badge-light border text-primary">
												<i class="fa-brands fa-cc-visa mr-1"></i>{{ $r['refund_card_last4'] ? '**** ' . $r['refund_card_last4'] : 'Tarjeta' }}
											</span>
										@elseif($r['refund_method'] === 'cash')
											<span class="badge badge-light border text-success"><i class="fa-solid fa-money-bill mr-1"></i>Efectivo</span>
										@else
											<span class="badge badge-light border text-muted">{{ $r['refund_method'] }}</span>
										@endif
									</td>
									<td class="small">
										<div><i class="fa-solid fa-user text-muted mr-1"></i>{{ $r['cashier_name'] ?: 'Cajero' }}</div>
										<div class="text-muted"><i class="fa-solid fa-user-check text-success mr-1"></i>{{ $r['supervisor_name'] ?: 'Supervisor' }}</div>
									</td>
									<td class="small text-muted">{{ !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '--' }}</td>
									<td class="text-right">
										<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="showVoucherModal({{ json_encode($r) }})">
											<i class="fa-solid fa-receipt mr-1"></i>Voucher
										</button>
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="13" class="text-center py-4 text-muted">No se registran devoluciones previas en esta sucursal.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 3: CONTROL ANTI-FRAUDE & ALERTAS ================= -->
		<div class="tab-pane fade" id="tab-control-antifraude" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title"><i class="fa-solid fa-user-shield text-danger"></i> 3. Monitoreo Anti-Fraude & Detección de Abusos</h3>
				<p class="pos-ret-section-sub">Detección de patrones anómalos: devoluciones recurrentes por cliente, productos sensibles y auditoría de autorizaciones.</p>

				<div class="row mb-4">
					<div class="col-md-6 mb-3">
						<div class="card p-3 border h-100 rounded">
							<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-bell text-warning mr-1"></i> Alertas Automáticas Activas</h6>
							@forelse($fraudAlerts as $fa)
								<div class="alert alert-{{ $fa['level'] }} p-2.5 mb-2 small rounded">
									<strong class="d-block">{{ $fa['title'] }}</strong>
									<span>{{ $fa['desc'] }}</span>
								</div>
							@empty
								<div class="text-muted small py-3 text-center">
									<i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
									No se detectan patrones sospechosos en el período actual.
								</div>
							@endforelse
						</div>
					</div>

					<div class="col-md-6 mb-3">
						<div class="card p-3 border h-100 rounded">
							<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-triangle-exclamation text-danger mr-1"></i> Causales Más Frecuentes de Devolución</h6>
							<ul class="list-group list-group-flush small">
								@forelse($topReasons as $reason => $cnt)
									<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
										<span>{{ $reason }}</span>
										<span class="badge badge-danger badge-pill">{{ $cnt }} casos</span>
									</li>
								@empty
									<li class="list-group-item text-muted text-center">Sin datos registrados</li>
								@endforelse
							</ul>
						</div>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2"><i class="fa-solid fa-box-archive text-muted mr-1"></i> Top SKUs con Mayor Tasa de Devolución</h6>
				<div class="table-responsive bg-white rounded border">
					<table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
						<thead class="thead-light">
							<tr>
								<th>Producto</th>
								<th>SKU</th>
								<th>Unidades Devueltas</th>
								<th>Impacto Reembolsado ($)</th>
								<th>Protocolo Recomendado</th>
							</tr>
						</thead>
						<tbody>
							@forelse($topProducts as $tp)
								<tr>
									<td><strong>{{ $tp['name'] }}</strong></td>
									<td><code>{{ $tp['sku'] }}</code></td>
									<td class="font-weight-bold text-danger">{{ $tp['units'] }}</td>
									<td class="font-weight-bold">{{ $money($tp['amount']) }}</td>
									<td><span class="badge badge-light border text-muted">Auditar lote con proveedor</span></td>
								</tr>
							@empty
								<tr><td colspan="5" class="text-center py-3 text-muted">No hay registros suficientes</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ================= TAB 4: INSPECCIÓN & DESTINOS KARDEX ================= -->
		<div class="tab-pane fade" id="tab-inspeccion-inventario" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title"><i class="fa-solid fa-boxes-stacked text-success"></i> 4. Inspección Física y Destinos de Inventario (Kardex)</h3>
				<p class="pos-ret-section-sub">Segregación de existencias sin contaminación cruzada. Cada ítem inspeccionado se deriva al flujo seguro correspondiente.</p>

				<div class="row">
					<div class="col-md-3 col-6 mb-3">
						<div class="card p-3 border rounded text-center h-100" style="border-top: 4px solid #059669 !important;">
							<i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i>
							<strong class="text-dark">Reintegro a Stock</strong>
							<span class="small text-muted mt-1">Solo productos sellados, intactos y no perecederos restringidos. Reingresan automáticamente a stock disponible.</span>
						</div>
					</div>

					<div class="col-md-3 col-6 mb-3">
						<div class="card p-3 border rounded text-center h-100" style="border-top: 4px solid #f59e0b !important;">
							<i class="fa-solid fa-flask-vial text-warning fa-2x mb-2"></i>
							<strong class="text-dark">Cuarentena Técnica</strong>
							<span class="small text-muted mt-1">Alimentos refrigerados o con sospecha de pérdida de frío. Aislados para peritaje de calidad.</span>
						</div>
					</div>

					<div class="col-md-3 col-6 mb-3">
						<div class="card p-3 border rounded text-center h-100" style="border-top: 4px solid #0284c7 !important;">
							<i class="fa-solid fa-truck-arrow-right text-primary fa-2x mb-2"></i>
							<strong class="text-dark">Devolución a Proveedor (RTV)</strong>
							<span class="small text-muted mt-1">Fallas de origen, sellos defectuosos de fábrica o lotes retirados. Se tramita nota de crédito al proveedor.</span>
						</div>
					</div>

					<div class="col-md-3 col-6 mb-3">
						<div class="card p-3 border rounded text-center h-100" style="border-top: 4px solid #ef4444 !important;">
							<i class="fa-solid fa-trash-can text-danger fa-2x mb-2"></i>
							<strong class="text-dark">Merma Definitiva</strong>
							<span class="small text-muted mt-1">Productos averiados o abiertos no aptos para consumo. Registrados como merma con pérdida definitiva.</span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ================= TAB 5: REEMBOLSOS & PASARELAS DEMO ================= -->
		<div class="tab-pane fade" id="tab-reembolsos-pasarela" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title"><i class="fa-solid fa-credit-card text-success"></i> 5. Reembolsos y Pasarelas de Pago Demo (PCI-DSS)</h3>
				<p class="pos-ret-section-sub">Gestión segura del desembolso monetario o emisión de notas de crédito comerciales sin almacenar datos sensibles de tarjetas.</p>

				<div class="row">
					<div class="col-md-4 mb-3">
						<div class="card p-3 border rounded">
							<h6 class="font-weight-bold text-dark"><i class="fa-solid fa-money-bill-wave text-success mr-1"></i> 1. Efectivo (Caja Mostrador)</h6>
							<p class="small text-muted mb-2">Se descuenta del arqueo de la caja abierta activa en el turno de POS. Requiere firma de recibido del cliente.</p>
							<span class="badge badge-success px-2 py-1">Inmediato</span>
						</div>
					</div>

					<div class="col-md-4 mb-3">
						<div class="card p-3 border rounded">
							<h6 class="font-weight-bold text-dark"><i class="fa-solid fa-credit-card text-primary mr-1"></i> 2. Tarjeta (Sandbox Gateway)</h6>
							<p class="small text-muted mb-2">Simula el contracargo bancario al emisor original. Genera token de referencia bancaria sin almacenar PAN ni CVV.</p>
							<span class="badge badge-primary px-2 py-1">Sandbox Gateway Activo</span>
						</div>
					</div>

					<div class="col-md-4 mb-3">
						<div class="card p-3 border rounded">
							<h6 class="font-weight-bold text-dark"><i class="fa-solid fa-ticket text-info mr-1"></i> 3. Crédito de Tienda / Vale</h6>
							<p class="small text-muted mb-2">Emite un vale digital al portador canjeable en cualquier sucursal con validez de 90 días naturales.</p>
							<span class="badge badge-info px-2 py-1">Vale Digital</span>
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>

</div>

<!-- ====================================================================
     MODAL: COMPROBANTE OFICIAL DE DEVOLUCIÓN POS (VOUCHER)
     ==================================================================== -->
<div class="modal fade" id="modalPosReturnVoucher" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-2.5">
				<h5 class="modal-title font-weight-bold" style="font-size: 1rem;">
					<i class="fa-solid fa-receipt mr-1"></i> Comprobante de Devolución POS
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" id="voucherPrintArea" style="font-family: monospace; font-size: 0.9rem;">
				<div class="text-center mb-3">
					<h5 class="font-weight-bold mb-0">DAILY RETAIL SUPERMARKET</h5>
					<div class="small text-muted">COMPROBANTE OFICIAL DE DEVOLUCIÓN</div>
					<div class="small font-weight-bold" id="vchReturnCode">DEV-POS-2026-XXXX</div>
				</div>
				<hr>
				<div class="mb-2"><strong>Ticket Venta Original:</strong> <span id="vchTicketNum">--</span></div>
				<div class="mb-2"><strong>Fecha y Hora:</strong> <span id="vchTimestamp">--</span></div>
				<div class="mb-2"><strong>Producto:</strong> <span id="vchProductName">--</span></div>
				<div class="mb-2"><strong>SKU:</strong> <span id="vchSku">--</span></div>
				<div class="mb-2"><strong>Cantidad Devuelta:</strong> <span id="vchQuantity">1</span></div>
				<div class="mb-2"><strong>Resolución:</strong> <span class="badge badge-success" id="vchResolution">Reembolso</span></div>
				<div class="mb-2"><strong>Destino Inventario:</strong> <span id="vchClassification">Reincorporación</span></div>
				<div class="mb-2"><strong>Método de Devolución:</strong> <span id="vchRefundMethod">Tarjeta</span></div>
				<div class="mb-2" id="vchGatewayRow"><strong>Ref. Pasarela:</strong> <span id="vchGatewayRef">SANDBOX-RF-XXXX</span></div>
				<div class="mb-2" id="vchCardRow"><strong>Tarjeta:</strong> <span id="vchCardLast4">**** 4242</span></div>
				<hr>
				<div class="d-flex justify-content-between font-weight-bold" style="font-size: 1.15rem;">
					<span>TOTAL REEMBOLSADO:</span>
					<span class="text-success" id="vchTotalRefund">$0.00</span>
				</div>
				<hr>
				<div class="small text-muted text-center mt-3">
					<div>Autorizado por Supervisor de Caja</div>
					<div class="mt-2 text-uppercase font-weight-bold" style="font-size: 0.72rem;">* Conserve este comprobante para cualquier reclamación *</div>
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-dark btn-sm font-weight-bold" onclick="window.print();">
					<i class="fa-solid fa-print mr-1"></i> Imprimir
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: DIÁLOGO DE RECHAZO JUSTIFICADO
     ==================================================================== -->
<div class="modal fade" id="modalRejectReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-danger text-white py-2.5">
				<h5 class="modal-title font-weight-bold" style="font-size: 1rem;">
					<i class="fa-solid fa-ban mr-1"></i> Rechazo Justificado de Devolución
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4">
				<input type="hidden" id="rejSaleId">
				<input type="hidden" id="rejSaleItemId">
				<input type="hidden" id="rejQuantity">

				<div class="alert alert-warning small mb-3">
					<i class="fa-solid fa-circle-exclamation mr-1"></i>
					El rechazo se registrará en la bitácora inmutable de auditoría para respaldo comercial ante cualquier queja del cliente.
				</div>

				<div class="form-group mb-3">
					<label class="font-weight-bold small">Causal de Rechazo Obligatoria:</label>
					<select class="form-control form-control-sm" id="rejReasonSelect">
						<option value="Producto consumido o usado en más del 20%">Producto consumido o usado en más del 20%</option>
						<option value="Plazo comercial vencido (> 30 días)">Plazo comercial vencido (&gt; 30 días)</option>
						<option value="Daño imputable a mal uso o almacenamiento indebido del cliente">Daño imputable a mal uso del cliente</option>
						<option value="Producto no adquirido en esta cadena o SKU no coincidente">Producto no adquirido en esta cadena</option>
						<option value="Alimento refrigerado con pérdida evidente de cadena de frío por cliente">Alimento refrigerado con pérdida de frío</option>
						<option value="Otro motivo justificado">Otro motivo justificado</option>
					</select>
				</div>

				<div class="form-group mb-0">
					<label class="font-weight-bold small">Detalle / Observaciones del Rechazo:</label>
					<textarea class="form-control form-control-sm" id="rejObservations" rows="3" placeholder="Describa el estado del producto y el argumento entregado al cliente..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-danger btn-sm font-weight-bold" id="btnConfirmRejection">
					<i class="fa-solid fa-ban mr-1"></i> Confirmar Rechazo
				</button>
			</div>
		</div>
	</div>
</div>

@push('pageScripts')
<script>
	var currentBranchId = {{ $selectedBranchId ?: 1 }};
	var activeSale = null;

	// BÚSQUEDA REACTIVA DE VENTAS
	function searchSale() {
		var q = $('#posSearchQuery').val().trim();
		if (!q) {
			toastr.info('Ingrese un número de ticket, factura o cliente para buscar.');
			return;
		}

		$.get(U('/api/pos/returns/lookup'), { q: q, branch_id: currentBranchId }, function(res) {
			if (res && res.sales && res.sales.length > 0) {
				renderSaleDetail(res.sales[0]);
			} else {
				toastr.warning('No se encontraron ventas con el criterio ingresado.');
				$('#saleDetailContainer').hide();
				$('#saleEmptyPrompt').show();
			}
		}).fail(function(xhr) {
			toastr.error('Error al consultar ventas.');
		});
	}

	$('#btnSearchSale').on('click', searchSale);
	$('#posSearchQuery').on('keypress', function(e) {
		if (e.which === 13) {
			searchSale();
		}
	});

	function loadSaleDetail(ticketNumber) {
		$('#posSearchQuery').val(ticketNumber);
		$.get(U('/api/pos/returns/lookup'), { q: ticketNumber, branch_id: currentBranchId }, function(res) {
			if (res && res.sales && res.sales.length > 0) {
				renderSaleDetail(res.sales[0]);
			} else {
				toastr.warning('No se encontró el ticket ' + ticketNumber);
			}
		});
	}

	// RENDERIZADO DEL TICKET Y SUS ÍTEMS
	function renderSaleDetail(sale) {
		activeSale = sale;
		$('#valSaleNumber').text(sale.sale_number);
		$('#valCustomerName').text(sale.customer_name);
		$('#valBranchName').text(sale.branch_name);
		$('#valSaleDate').text(sale.created_timestamp ? sale.created_timestamp.substring(0, 16) : '--');
		$('#valPaymentMethod').text(sale.payment_method.toUpperCase());
		$('#valTotalAmount').text('$' + parseFloat(sale.total_amount).toFixed(2));

		var tbody = $('#saleItemsTbody');
		tbody.empty();

		if (!sale.items || sale.items.length === 0) {
			tbody.append('<tr><td colspan="10" class="text-center py-3 text-muted">Este ticket no registra ítems disponibles.</td></tr>');
		} else {
			sale.items.forEach(function(item) {
				var isEligible = (item.eligible_amount > 0);
				var tr = $('<tr></tr>');

				// Col 1: Producto
				tr.append('<td><strong>' + item.product_name + '</strong><br><code class="small text-muted">' + item.sku + '</code></td>');

				// Col 2: Vendidas
				tr.append('<td>' + item.amount_sold + '</td>');

				// Col 3: Ya devueltas
				tr.append('<td class="text-muted">' + item.amount_returned + '</td>');

				// Col 4: Disponibles
				tr.append('<td class="font-weight-bold ' + (isEligible ? 'text-success' : 'text-danger') + '">' + item.eligible_amount + '</td>');

				// Col 5: Cantidad a devolver
				if (isEligible) {
					tr.append('<td><input type="number" class="form-control form-control-sm item-qty-input" id="qty-' + item.id + '" value="' + item.eligible_amount + '" min="0.01" max="' + item.eligible_amount + '" step="1"></td>');
				} else {
					tr.append('<td><span class="badge badge-secondary">Agotado</span></td>');
				}

				// Col 6: Estado de inspección
				tr.append('<td>' +
					'<select class="form-control form-control-sm" id="cond-' + item.id + '" onchange="onConditionChange(' + item.id + ', ' + item.is_perishable + ')">' +
						'<option value="sin_uso">Sin uso / Sellado</option>' +
						'<option value="abierto">Abierto</option>' +
						'<option value="dañado_cliente">Dañado por cliente</option>' +
						'<option value="defectuoso_fabrica">Defecto de fábrica</option>' +
						'<option value="no_apto_reventa">No apto para reventa</option>' +
					'</select>' +
				'</td>');

				// Col 7: Resolución
				tr.append('<td>' +
					'<select class="form-control form-control-sm" id="res-' + item.id + '">' +
						'<option value="reembolso">Reembolso dinero</option>' +
						'<option value="cambio">Cambio producto</option>' +
						'<option value="credito_tienda">Crédito de tienda</option>' +
						'<option value="rechazo_justificado">Rechazo justificado</option>' +
					'</select>' +
				'</td>');

				// Col 8: Destino sugerido
				tr.append('<td><span class="badge badge-light border" id="disp-badge-' + item.id + '">Reincorporación</span></td>');

				// Col 9: Supervisor
				tr.append('<td>' +
					'<select class="form-control form-control-sm" id="sup-' + item.id + '">' +
						@foreach($users as $u)
							'<option value="{{ $u['id'] }}">{{ $u['username'] }}</option>' +
						@endforeach
					'</select>' +
				'</td>');

				// Col 10: Botón de acción
				if (isEligible) {
					tr.append('<td class="text-right">' +
						'<button type="button" class="btn btn-xs btn-success font-weight-bold mr-1" onclick="submitReturn(' + item.id + ')">' +
							'<i class="fa-solid fa-check mr-1"></i>Procesar' +
						'</button>' +
						'<button type="button" class="btn btn-xs btn-outline-danger" onclick="openRejectModal(' + sale.id + ', ' + item.id + ')">' +
							'<i class="fa-solid fa-ban"></i>' +
						'</button>' +
					'</td>');
				} else {
					tr.append('<td class="text-right"><span class="badge badge-light border text-muted">Totalmente Devuelto</span></td>');
				}

				tbody.append(tr);
			});
		}

		$('#saleEmptyPrompt').hide();
		$('#saleDetailContainer').show();
	}

	function onConditionChange(itemId, isPerishable) {
		var cond = $('#cond-' + itemId).val();
		var badge = $('#disp-badge-' + itemId);

		if (cond === 'dañado_cliente' || cond === 'no_apto_reventa') {
			badge.text('Merma Definitiva').removeClass().addClass('badge badge-danger');
		} else if (cond === 'defectuoso_fabrica') {
			badge.text('Proveedor RTV').removeClass().addClass('badge badge-info');
		} else if (isPerishable && cond !== 'sin_uso') {
			badge.text('Cuarentena Técnica').removeClass().addClass('badge badge-warning');
		} else {
			badge.text('Reincorporación').removeClass().addClass('badge badge-success');
		}
	}

	// ENVÍO DE DEVOLUCIÓN ACEPTADA
	function submitReturn(itemId) {
		if (!activeSale) return;
		var item = activeSale.items.find(function(it) { return it.id === itemId; });
		if (!item) return;

		var qty = parseFloat($('#qty-' + itemId).val());
		var cond = $('#cond-' + itemId).val();
		var res = $('#res-' + itemId).val();
		var supId = parseInt($('#sup-' + itemId).val(), 10);

		// Validar si es rechazo justificado
		if (res === 'rechazo_justificado') {
			openRejectModal(activeSale.id, itemId);
			return;
		}

		var payload = {
			sale_id: activeSale.id,
			sale_item_id: itemId,
			quantity: qty,
			condition_state: cond,
			resolution: res,
			refund_method: activeSale.payment_method || 'cash',
			card_last4: '4242',
			reason: 'Devolución mostrador POS ' + res,
			supervisor_user_id: supId
		};

		$.post(U('/api/pos/returns/process'), payload, function(resp) {
			if (resp && resp.success) {
				toastr.success(resp.message);
				if (resp.voucher) {
					showVoucherModal(resp.voucher);
				}
				// Agregar fila a la tabla de historial
				var rowHtml = '<tr>' +
					'<td><strong class="text-success">' + resp.return_code + '</strong></td>' +
					'<td><span class="font-weight-bold text-dark">' + activeSale.sale_number + '</span></td>' +
					'<td>' + activeSale.customer_name + '</td>' +
					'<td><strong>' + item.product_name + '</strong> <span class="text-muted small">(' + item.sku + ')</span></td>' +
					'<td>' + qty + '</td>' +
					'<td class="font-weight-bold text-danger">$' + parseFloat(resp.refund_amount || 0).toFixed(2) + '</td>' +
					'<td><span class="badge badge-success px-2 py-0.5">' + res.toUpperCase() + '</span></td>' +
					'<td><span class="small text-muted">' + cond + '</span></td>' +
					'<td><span class="badge badge-light border">Reincorporación</span></td>' +
					'<td><span class="badge badge-light border text-primary">Tarjeta</span></td>' +
					'<td class="small"><div>Admin</div></td>' +
					'<td class="small text-muted">Ahora</td>' +
					'<td class="text-right"><button type="button" class="btn btn-xs btn-outline-success font-weight-bold" onclick="showVoucherModal(' + JSON.stringify(resp.voucher) + ')"><i class="fa-solid fa-receipt mr-1"></i>Voucher</button></td>' +
				'</tr>';
				$('#tablePosReturnsHistory tbody').prepend(rowHtml);

				// Recargar venta para actualizar cantidades
				loadSaleDetail(activeSale.sale_number);
			} else {
				toastr.error('Error al procesar devolución');
			}
		}).fail(function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al procesar devolución';
			toastr.error(msg);
		});
	}

	// DIÁLOGO DE RECHAZO
	function openRejectModal(saleId, itemId) {
		var item = activeSale.items.find(function(it) { return it.id === itemId; });
		var qty = parseFloat($('#qty-' + itemId).val()) || 1.0;

		$('#rejSaleId').val(saleId);
		$('#rejSaleItemId').val(itemId);
		$('#rejQuantity').val(qty);
		$('#modalRejectReturn').modal('show');
	}

	$('#btnConfirmRejection').on('click', function() {
		var saleId = parseInt($('#rejSaleId').val(), 10);
		var itemId = parseInt($('#rejSaleItemId').val(), 10);
		var qty = parseFloat($('#rejQuantity').val()) || 1.0;
		var reason = $('#rejReasonSelect').val();
		var obs = $('#rejObservations').val().trim();
		var fullReason = reason + (obs ? ': ' + obs : '');

		var item = activeSale ? activeSale.items.find(function(it) { return it.id === itemId; }) : null;

		var payload = {
			sale_id: saleId,
			sale_item_id: itemId,
			quantity: qty,
			condition_state: 'dañado_cliente',
			resolution: 'rechazo_justificado',
			rejection_reason: fullReason,
			supervisor_user_id: 1
		};

		$.post(U('/api/pos/returns/process'), payload, function(resp) {
			$('#modalRejectReturn').modal('hide');
			if (resp && resp.success) {
				toastr.warning(resp.message);

				// Agregar fila a la tabla de historial como rechazada
				var rejHtml = '<tr>' +
					'<td><strong class="text-danger">' + resp.return_code + '</strong></td>' +
					'<td><span class="font-weight-bold text-dark">' + (activeSale ? activeSale.sale_number : '--') + '</span></td>' +
					'<td>' + (activeSale ? activeSale.customer_name : 'Cliente') + '</td>' +
					'<td><strong>' + (item ? item.product_name : 'Producto') + '</strong></td>' +
					'<td>' + qty + '</td>' +
					'<td class="font-weight-bold text-muted">$0.00</td>' +
					'<td><span class="badge badge-danger px-2 py-0.5"><i class="fa-solid fa-ban mr-1"></i>Rechazada</span></td>' +
					'<td><span class="small text-muted">dañado_cliente</span></td>' +
					'<td><span class="badge badge-light border">Merma Definitiva</span></td>' +
					'<td><span class="badge badge-light border text-muted">Ninguno</span></td>' +
					'<td class="small"><div>Admin</div></td>' +
					'<td class="small text-muted">Ahora</td>' +
					'<td class="text-right"><span class="badge badge-secondary">Rechazado</span></td>' +
				'</tr>';
				$('#tablePosReturnsHistory tbody').prepend(rejHtml);

				if (activeSale) {
					loadSaleDetail(activeSale.sale_number);
				}
			}
		}).fail(function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al rechazar devolución';
			toastr.error(msg);
		});
	});

	// MOSTRAR VOUCHER OFICIAL
	function showVoucherModal(v) {
		$('#vchReturnCode').text(v.return_code || 'DEV-POS-2026');
		$('#vchTicketNum').text(v.ticket_number || '--');
		$('#vchTimestamp').text(v.timestamp || (v.created_at ? v.created_at.substring(0, 16) : '--'));
		$('#vchProductName').text(v.product_name || '--');
		$('#vchSku').text(v.sku || '--');
		$('#vchQuantity').text(v.quantity || '1');
		$('#vchResolution').text(v.resolution ? v.resolution.toUpperCase() : 'REEMBOLSO');
		$('#vchClassification').text(v.classification || 'Reincorporación');
		$('#vchRefundMethod').text((v.refund_method || 'cash').toUpperCase());

		if (v.gateway_reference || v.refund_gateway_ref) {
			$('#vchGatewayRef').text(v.gateway_reference || v.refund_gateway_ref);
			$('#vchGatewayRow').show();
		} else {
			$('#vchGatewayRow').hide();
		}

		if (v.card_last4 || v.refund_card_last4) {
			$('#vchCardLast4').text('**** ' + (v.card_last4 || v.refund_card_last4));
			$('#vchCardRow').show();
		} else {
			$('#vchCardRow').hide();
		}

		var refundAmt = v.refund_amount !== undefined ? v.refund_amount : (v.customer_refund_amount || 0);
		$('#vchTotalRefund').text('$' + parseFloat(refundAmt).toFixed(2));

		$('#modalPosReturnVoucher').modal('show');
	}
</script>
@endpush
@endsection
