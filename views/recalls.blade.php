@extends('layout.default')

@section('title', 'Retiros de Producto - DAILY')

@section('content')
@php
	$open = array_values(array_filter($recalls, fn($r) => $r['status'] === 'open'));
	$closed = array_values(array_filter($recalls, fn($r) => $r['status'] === 'closed'));
	$cancelled = array_values(array_filter($recalls, fn($r) => $r['status'] === 'cancelled'));
	$pendingLots = array_sum(array_column($open, 'pending_lots'));
	$retained = array_sum(array_column($open, 'amount'));
	$retainedFormatted = $retained > 0 ? rtrim(rtrim(number_format($retained, 2), '0'), '.') : '0';

	$statusMeta = [
		'open' => ['Abierto', 'warning', '#f59e0b', '#fffbeb'],
		'closed' => ['Cerrado', 'success', '#10b981', '#ecfdf5'],
		'cancelled' => ['Anulado', 'secondary', '#64748b', '#f1f5f9']
	];

	$itemMeta = [
		'quarantined' => ['Por confirmar', 'warning', '#f59e0b', '#fffbeb'],
		'confirmed' => ['Separado', 'info', '#0284c7', '#f0f9ff'],
		'disposed' => ['Dado de baja', 'success', '#10b981', '#ecfdf5']
	];

	$sevMeta = [
		'class_1' => ['Clase I · Riesgo Grave', 'danger', '#dc2626', '#fef2f2'],
		'class_2' => ['Clase II · Riesgo Moderado', 'warning', '#d97706', '#fffbeb'],
		'class_3' => ['Clase III · Riesgo Menor', 'info', '#059669', '#ecfdf5']
	];
@endphp

<div class="daily-recalls-container" id="dailyRecallsApp">
	<style>
		/* Estilos específicos Retiros de Producto DAILY */
		.daily-recalls-container {
			font-family: inherit;
			color: #0f172a;
		}

		/* 1. Header Card */
		.daily-rc-header-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 16px;
			margin-bottom: 20px;
			padding: 22px 28px;
			position: relative;
			overflow: hidden;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			display: flex;
			align-items: center;
			justify-content: space-between;
			min-height: 94px;
		}
		.daily-rc-header-bg {
			position: absolute;
			top: 0;
			right: 0;
			bottom: 0;
			width: 48%;
			background: url('{{ $U('/img/control_center/retiros_header.jpg') }}') no-repeat right center;
			background-size: cover;
			-webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.5) 40%, rgba(0,0,0,0.95) 85%, black 100%);
			mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.5) 40%, rgba(0,0,0,0.95) 85%, black 100%);
			pointer-events: none;
			z-index: 1;
		}
		.daily-rc-header-left {
			display: flex;
			align-items: center;
			gap: 16px;
			position: relative;
			z-index: 2;
		}
		.daily-rc-header-icon {
			width: 48px;
			height: 48px;
			border-radius: 14px;
			background: #ecfdf5;
			color: #00C83B;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 20px;
			flex-shrink: 0;
			box-shadow: 0 1px 2px rgba(0, 200, 59, 0.1);
		}
		.daily-rc-header-title {
			font-size: 22px;
			font-weight: 700;
			color: #0f172a;
			margin: 0 0 4px 0;
			line-height: 1.2;
			letter-spacing: -0.3px;
		}
		.daily-rc-header-desc {
			font-size: 13.5px;
			color: #64748b;
			margin: 0;
			line-height: 1.4;
		}
		.daily-rc-header-actions {
			display: flex;
			align-items: center;
			gap: 10px;
			position: relative;
			z-index: 2;
		}
		.btn-daily-action-primary {
			background-color: #00C83B;
			border: 1px solid #00C83B;
			color: #ffffff;
			font-weight: 600;
			font-size: 13.5px;
			border-radius: 9px;
			padding: 9px 18px;
			display: inline-flex;
			align-items: center;
			gap: 7px;
			box-shadow: 0 2px 5px rgba(0, 200, 59, 0.25);
			transition: all 0.15s ease;
		}
		.btn-daily-action-primary:hover,
		.btn-daily-action-primary:focus {
			background-color: #00b034;
			border-color: #00b034;
			color: #ffffff;
			transform: translateY(-1px);
			box-shadow: 0 4px 8px rgba(0, 200, 59, 0.3);
		}

		/* 2. KPI Cards */
		.daily-rc-kpi-grid {
			margin-bottom: 20px;
		}
		.daily-rc-kpi-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 16px 20px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			transition: all 0.15s ease;
			height: 100%;
			display: flex;
			flex-direction: column;
			justify-content: space-between;
		}
		.daily-rc-kpi-card:hover {
			border-color: #cbd5e1;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
		}
		.daily-rc-kpi-top {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 10px;
		}
		.daily-rc-kpi-icon-box {
			width: 36px;
			height: 36px;
			border-radius: 10px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 15px;
			flex-shrink: 0;
		}
		.daily-rc-kpi-icon-box.is-green {
			background: #ecfdf5;
			color: #00C83B;
		}
		.daily-rc-kpi-icon-box.is-amber {
			background: #fffbeb;
			color: #f59e0b;
		}
		.daily-rc-kpi-icon-box.is-red {
			background: #fef2f2;
			color: #ef4444;
		}
		.daily-rc-kpi-label {
			font-size: 13px;
			font-weight: 600;
			color: #64748b;
			margin: 0;
			line-height: 1.2;
		}
		.daily-rc-kpi-val {
			font-size: 28px;
			font-weight: 700;
			color: #0f172a;
			line-height: 1.1;
			margin-bottom: 4px;
			letter-spacing: -0.5px;
		}
		.daily-rc-kpi-sub {
			font-size: 12px;
			color: #94a3b8;
			margin: 0;
			font-weight: 500;
		}

		/* 3. Main Panel & Guides */
		.daily-rc-main-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			margin-bottom: 20px;
			overflow: hidden;
		}
		.daily-rc-card-header {
			padding: 16px 20px;
			border-bottom: 1px solid #f1f5f9;
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 12px;
		}
		.daily-rc-card-title {
			font-size: 16px;
			font-weight: 700;
			color: #0f172a;
			margin: 0;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.daily-rc-tabs-group {
			display: inline-flex;
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			padding: 3px;
			border-radius: 10px;
			gap: 4px;
		}
		.daily-rc-tab-btn {
			border: none;
			background: transparent;
			padding: 5px 14px;
			font-size: 13px;
			font-weight: 600;
			color: #64748b;
			border-radius: 7px;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.daily-rc-tab-btn:hover {
			color: #0f172a;
		}
		.daily-rc-tab-btn.is-active {
			background: #00C83B;
			color: #ffffff;
			box-shadow: 0 1px 3px rgba(0, 200, 59, 0.25);
		}
		.daily-rc-search-box {
			position: relative;
			min-width: 240px;
		}
		.daily-rc-search-box i {
			position: absolute;
			left: 12px;
			top: 50%;
			transform: translateY(-50%);
			color: #94a3b8;
			font-size: 13px;
			pointer-events: none;
		}
		.daily-rc-search-input {
			padding-left: 34px !important;
			border-radius: 9px !important;
			border: 1px solid #cbd5e1 !important;
			font-size: 13px !important;
			height: 36px !important;
		}
		.daily-rc-search-input:focus {
			border-color: #00C83B !important;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12) !important;
		}

		/* Table styles */
		.daily-rc-table {
			width: 100%;
			margin-bottom: 0;
			font-size: 13px;
		}
		.daily-rc-table thead th {
			background: #f8fafc;
			color: #64748b;
			font-weight: 700;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			border-top: none;
			border-bottom: 1px solid #e2e8f0;
			padding: 12px 16px;
			vertical-align: middle;
		}
		.daily-rc-table tbody td {
			padding: 14px 16px;
			vertical-align: middle;
			border-bottom: 1px solid #f1f5f9;
			color: #1e293b;
		}
		.daily-rc-table tbody tr:hover td {
			background-color: #f8fafc;
		}
		.daily-rc-folio-link {
			font-family: monospace;
			font-weight: 700;
			color: #00C83B;
			text-decoration: none;
			font-size: 13px;
		}
		.daily-rc-folio-link:hover {
			text-decoration: underline;
			color: #00992c;
		}
		.badge-daily-status {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 4px 10px;
			border-radius: 6px;
			font-size: 12px;
			font-weight: 600;
		}

		/* Empty State Component */
		.daily-rc-empty-state {
			padding: 50px 24px;
			text-align: center;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
		}
		.daily-rc-empty-img {
			width: 230px;
			max-width: 85%;
			height: auto;
			margin-bottom: 18px;
			border-radius: 12px;
			filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.05));
		}
		.daily-rc-empty-title {
			font-size: 19px;
			font-weight: 700;
			color: #0f172a;
			margin-bottom: 6px;
			letter-spacing: -0.2px;
		}
		.daily-rc-empty-desc {
			font-size: 13.5px;
			color: #64748b;
			max-width: 480px;
			margin: 0 auto 20px auto;
			line-height: 1.45;
		}

		/* 4. Guide Card "Cómo gestionar un retiro" */
		.daily-rc-guide-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			margin-bottom: 20px;
			overflow: hidden;
		}
		.daily-rc-guide-header {
			padding: 16px 20px;
			border-bottom: 1px solid #f1f5f9;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.daily-rc-guide-title {
			font-size: 15px;
			font-weight: 700;
			color: #0f172a;
			margin: 0;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-rc-guide-body {
			padding: 20px;
		}
		.daily-rc-step-item {
			display: flex;
			gap: 14px;
			position: relative;
		}
		.daily-rc-step-left {
			display: flex;
			flex-direction: column;
			align-items: center;
		}
		.daily-rc-step-circle {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			background: #00C83B;
			color: #ffffff;
			font-size: 12.5px;
			font-weight: 700;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			box-shadow: 0 1px 3px rgba(0, 200, 59, 0.3);
			z-index: 2;
		}
		.daily-rc-step-circle.is-completed {
			background: #10b981;
		}
		.daily-rc-step-line {
			width: 2px;
			flex-grow: 1;
			min-height: 28px;
			border-left: 2px dashed #86efac;
			margin: 4px 0;
		}
		.daily-rc-step-content {
			padding-bottom: 18px;
		}
		.daily-rc-step-title {
			font-size: 14px;
			font-weight: 700;
			color: #0f172a;
			margin: 0 0 2px 0;
			display: flex;
			align-items: center;
			gap: 7px;
		}
		.daily-rc-step-desc {
			font-size: 12.5px;
			color: #64748b;
			margin: 0;
			line-height: 1.35;
		}
		.daily-rc-guide-alert {
			background: #fffbeb;
			border: 1px solid #fde68a;
			border-radius: 10px;
			padding: 12px 14px;
			margin-top: 10px;
			display: flex;
			align-items: flex-start;
			gap: 10px;
		}
		.daily-rc-guide-alert i {
			color: #d97706;
			font-size: 14px;
			margin-top: 2px;
		}
		.daily-rc-guide-alert-title {
			font-size: 12.5px;
			font-weight: 700;
			color: #92400e;
			margin-bottom: 2px;
		}
		.daily-rc-guide-alert-text {
			font-size: 11.5px;
			color: #b45309;
			margin: 0;
			line-height: 1.3;
		}

		/* 5. Detail Panel */
		.daily-rc-detail-card {
			background: #ffffff;
			border: 1px solid #cbd5e1;
			border-left: 4px solid #00C83B;
			border-radius: 14px;
			margin-bottom: 20px;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
			overflow: hidden;
		}
		.daily-rc-detail-header {
			padding: 18px 22px;
			background: #f8fafc;
			border-bottom: 1px solid #e2e8f0;
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 14px;
		}

		/* 6. Connected Module Card */
		.daily-rc-connected-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 16px;
			padding: 20px 24px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			position: relative;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: space-between;
			min-height: 84px;
			margin-top: 10px;
			margin-bottom: 24px;
		}
		.daily-rc-connected-bg {
			position: absolute;
			top: 0;
			right: 0;
			bottom: 0;
			width: 60%;
			background: url('{{ $U('/img/control_center/calidad_banner.jpg') }}') no-repeat right center;
			background-size: cover;
			-webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.55) 30%, rgba(0,0,0,0.9) 75%, black 100%);
			mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.55) 30%, rgba(0,0,0,0.9) 75%, black 100%);
			pointer-events: none;
			z-index: 1;
		}
		.daily-rc-connected-left {
			display: flex;
			align-items: center;
			gap: 16px;
			position: relative;
			z-index: 2;
		}
		.daily-rc-connected-icon {
			width: 44px;
			height: 44px;
			border-radius: 12px;
			background: #ecfdf5;
			color: #00C83B;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 20px;
			flex-shrink: 0;
		}
		.daily-rc-connected-tag {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			color: #64748b;
			letter-spacing: 0.5px;
			margin-bottom: 2px;
		}
		.daily-rc-connected-title {
			font-size: 17px;
			font-weight: 700;
			color: #0f172a;
			margin: 0 0 2px 0;
		}
		.daily-rc-connected-desc {
			font-size: 13px;
			color: #64748b;
			margin: 0;
		}
		.daily-rc-connected-right {
			display: flex;
			flex-direction: column;
			align-items: flex-end;
			gap: 5px;
			position: relative;
			z-index: 2;
		}
		.btn-daily-open-module {
			background: #ffffff;
			border: 1px solid #cbd5e1;
			color: #0f172a;
			font-weight: 600;
			font-size: 13px;
			border-radius: 9px;
			padding: 8px 18px;
			display: inline-flex;
			align-items: center;
			gap: 7px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
			transition: all 0.15s ease;
			text-decoration: none;
		}
		.btn-daily-open-module:hover {
			border-color: #94a3b8;
			background: #f8fafc;
			color: #0f172a;
			text-decoration: none;
			transform: translateY(-1px);
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
		}
		.daily-rc-connected-footnote {
			font-size: 11px;
			color: #64748b;
			margin: 0;
		}

		/* Responsive adjustments */
		@media (max-width: 991px) {
			.daily-rc-header-card {
				flex-direction: column;
				align-items: flex-start;
				gap: 16px;
				padding: 18px 20px;
			}
			.daily-rc-header-bg {
				display: none;
			}
			.daily-rc-header-actions {
				width: 100%;
				justify-content: flex-start;
			}
			.daily-rc-connected-card {
				flex-direction: column;
				align-items: flex-start;
				gap: 16px;
			}
			.daily-rc-connected-bg {
				display: none;
			}
			.daily-rc-connected-right {
				align-items: flex-start;
				width: 100%;
			}
		}
	</style>

	<!-- 1. ENCABEZADO -->
	<div class="daily-rc-header-card">
		<div class="daily-rc-header-bg"></div>
		<div class="daily-rc-header-left">
			<div class="daily-rc-header-icon">
				<i class="fa-solid fa-rotate-left"></i>
			</div>
			<div>
				<h1 class="daily-rc-header-title">Retiros de Producto</h1>
				<p class="daily-rc-header-desc">Coordina la retención, separación y cierre de lotes afectados en toda la red.</p>
			</div>
		</div>
		<div class="daily-rc-header-actions">
			@if($canManage)
			<button type="button" class="btn btn-daily-action-primary" id="rc-new">
				<i class="fa-solid fa-plus"></i>
				<span>Nuevo retiro</span>
			</button>
			@endif
			@include('components.dairy_export', ['exportName' => 'retiros_de_producto'])
		</div>
	</div>

	<!-- 2. INDICADORES (4 KPI Cards) -->
	<div class="row daily-rc-kpi-grid">
		<div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
			<div class="daily-rc-kpi-card">
				<div class="daily-rc-kpi-top">
					<div class="daily-rc-kpi-icon-box is-green">
						<i class="fa-solid fa-rotate-left"></i>
					</div>
					<h3 class="daily-rc-kpi-label">Retiros abiertos</h3>
				</div>
				<div class="daily-rc-kpi-val">{{ count($open) }}</div>
				<p class="daily-rc-kpi-sub">En ejecución</p>
			</div>
		</div>

		<div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
			<div class="daily-rc-kpi-card">
				<div class="daily-rc-kpi-top">
					<div class="daily-rc-kpi-icon-box is-amber">
						<i class="fa-solid fa-hand"></i>
					</div>
					<h3 class="daily-rc-kpi-label">Por confirmar</h3>
				</div>
				<div class="daily-rc-kpi-val" @if($pendingLots > 0) style="color: #d97706;" @endif>{{ $pendingLots }}</div>
				<p class="daily-rc-kpi-sub">Lotes pendientes de separar</p>
			</div>
		</div>

		<div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
			<div class="daily-rc-kpi-card">
				<div class="daily-rc-kpi-top">
					<div class="daily-rc-kpi-icon-box is-red">
						<i class="fa-solid fa-lock"></i>
					</div>
					<h3 class="daily-rc-kpi-label">Unidades retenidas</h3>
				</div>
				<div class="daily-rc-kpi-val" @if($retained > 0) style="color: #dc2626;" @endif>{{ $retainedFormatted }}</div>
				<p class="daily-rc-kpi-sub">Bloqueadas para venta</p>
			</div>
		</div>

		<div class="col-xl-3 col-md-6 col-12 mb-3 mb-xl-0">
			<div class="daily-rc-kpi-card">
				<div class="daily-rc-kpi-top">
					<div class="daily-rc-kpi-icon-box is-green">
						<i class="fa-solid fa-folder-closed"></i>
					</div>
					<h3 class="daily-rc-kpi-label">Total de retiros</h3>
				</div>
				<div class="daily-rc-kpi-val">{{ count($recalls) }}</div>
				<p class="daily-rc-kpi-sub">Histórico</p>
			</div>
		</div>
	</div>

	<!-- 3. DETALLE DE RETIRO SELECCIONADO (Si aplica) -->
	@if($selected)
	@php
		$sm = $statusMeta[$selected['status']] ?? [$selected['status'], 'secondary', '#64748b', '#f1f5f9'];
		$svm = $sevMeta[$selected['severity']] ?? [$selected['severity'], 'secondary', '#64748b', '#f1f5f9'];
	@endphp
	<div class="daily-rc-detail-card" id="rc-detail">
		<div class="daily-rc-detail-header">
			<div>
				<div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
					<span class="font-mono font-weight-700 font-size-16 text-dark mr-2">{{ $selected['recall_code'] }}</span>
					<span class="font-weight-600 font-size-15 text-dark mr-2">{{ $selected['product_name'] }}</span>
					<span class="badge badge-daily-status mr-1" style="background: {{ $sm[3] }}; color: {{ $sm[2] }}; border: 1px solid {{ $sm[2] }}33;">
						<i class="fa-solid fa-circle font-size-8 mr-1"></i>{{ $sm[0] }}
					</span>
					<span class="badge badge-daily-status" style="background: {{ $svm[3] }}; color: {{ $svm[2] }}; border: 1px solid {{ $svm[2] }}33;">
						{{ $svm[0] }}
					</span>
				</div>
				<div class="small text-muted">
					<strong>Motivo:</strong> {{ $selected['reason'] }}
					@if($selected['disposition']) · <strong>Disposición:</strong> {{ $dispositions[$selected['disposition']] ?? $selected['disposition'] }}@endif
					@if($selected['supplier_name']) · <strong>Proveedor:</strong> {{ $selected['supplier_name'] }}@endif
					@if($selected['best_before_from'] || $selected['best_before_to']) · <strong>Vence:</strong> {{ $selected['best_before_from'] ?? 'Inicio' }} a {{ $selected['best_before_to'] ?? 'Fin' }}@endif
				</div>
			</div>
			<div class="d-flex align-items-center gap-2 flex-wrap">
				@if($canManage && $selected['status'] === 'open')
				<button type="button" class="btn btn-sm btn-success font-weight-600" id="rc-close" data-id="{{ $selected['id'] }}">
					<i class="fa-solid fa-flag-checkered mr-1"></i>Cerrar retiro
				</button>
				<button type="button" class="btn btn-sm btn-outline-danger font-weight-600" id="rc-cancel" data-id="{{ $selected['id'] }}">
					<i class="fa-solid fa-ban mr-1"></i>Anular
				</button>
				@endif
				<a class="btn btn-sm btn-light border" href="{{ $U('/recalls') }}" title="Cerrar detalle">
					<i class="fa-solid fa-xmark mr-1"></i>Volver al listado
				</a>
			</div>
		</div>

		@if(count($selected['in_transit'] ?? []))
		<div class="alert alert-warning m-3 small d-flex align-items-center">
			<i class="fa-solid fa-truck-fast mr-2 font-size-16"></i>
			<div><strong>Lotes en tránsito:</strong> Se detectaron {{ count($selected['in_transit']) }} lote(s) en tránsito entre sucursales. Al recibirse en destino, se aplicará la retención en Calidad & Vencimientos.</div>
		</div>
		@endif

		<div class="table-responsive">
			<table class="table table-sm table-hover daily-rc-table mb-0">
				<thead>
					<tr>
						<th>Sucursal</th>
						<th>Ubicación</th>
						<th>Lote / Stock ID</th>
						<th>Vencimiento</th>
						<th class="text-right">Sistema</th>
						<th class="text-right">Separado</th>
						<th>Estado</th>
						<th>Confirmación</th>
						<th class="text-right">Acción</th>
					</tr>
				</thead>
				<tbody>
					@foreach($selected['items'] as $i)
					@php
						$im = $itemMeta[$i['status']] ?? [$i['status'], 'secondary', '#64748b', '#f1f5f9'];
						$diff = $i['found_amount'] !== null ? round($i['found_amount'] - $i['amount'], 2) : null;
					@endphp
					<tr>
						<td class="font-weight-600">{{ $i['branch_name'] }}</td>
						<td>{{ $i['location_name'] }}</td>
						<td class="font-mono small">{{ $i['stock_id'] }}</td>
						<td>{{ $i['best_before_date'] }}</td>
						<td class="text-right font-weight-600">{{ $i['amount'] }} u.</td>
						<td class="text-right @if($diff !== null && abs($diff) > 0) text-danger font-weight-700 @endif">
							{{ $i['found_amount'] !== null ? $i['found_amount'] . ' u.' : '—' }}
							@if($diff !== null && abs($diff) > 0)
							<span class="small">({{ $diff > 0 ? '+' : '' }}{{ $diff }})</span>
							@endif
						</td>
						<td>
							<span class="badge badge-daily-status" style="background: {{ $im[3] }}; color: {{ $im[2] }}; border: 1px solid {{ $im[2] }}33;">
								{{ $im[0] }}
							</span>
							@if(!empty($i['waste_case']))
							<a class="small ml-1" href="{{ $U('/document/waste/' . $i['waste_record_id']) }}">({{ $i['waste_case'] }})</a>
							@endif
						</td>
						<td class="small">
							@if(!empty($i['confirmed_by']))
							<div><i class="fa-solid fa-user-check text-success mr-1"></i>{{ $i['confirmed_by'] }}</div>
							<div class="text-muted">{{ substr($i['confirmed_at'] ?? '', 0, 16) }}</div>
							@if(!empty($i['notes']))<div class="text-muted font-italic">{{ $i['notes'] }}</div>@endif
							@else
							<span class="text-muted font-italic">Pendiente de confirmación</span>
							@endif
						</td>
						<td class="text-right">
							@if($i['status'] === 'quarantined' && $selected['status'] === 'open' && \Grocy\Helpers\DairyAccess::CanSeeBranch($i['branch_id'] !== null ? intval($i['branch_id']) : null))
							<button type="button" class="btn btn-sm btn-primary rc-confirm" data-id="{{ $i['id'] }}" data-amount="{{ $i['amount'] }}" data-label="{{ $i['branch_name'] }} · {{ $i['location_name'] }}">
								<i class="fa-solid fa-check mr-1"></i>Confirmar separado
							</button>
							@endif
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		@if(!empty($selected['close_notes']))
		<div class="small text-muted p-3 bg-light border-top">
			<i class="fa-solid fa-circle-info text-primary mr-1"></i><strong>Notas de cierre:</strong> {{ $selected['close_notes'] }}
		</div>
		@endif
	</div>
	@endif

	<!-- 4. CONTENIDO PRINCIPAL: LISTADO & GUÍA (2 Columnas) -->
	<div class="row">
		<!-- Columna Izquierda: Listado de Retiros -->
		<div class="col-lg-8 col-xl-8 mb-4">
			<div class="daily-rc-main-card">
				<div class="daily-rc-card-header">
					<div class="d-flex align-items-center gap-3 flex-wrap">
						<h2 class="daily-rc-card-title">
							<i class="fa-solid fa-list-check text-dark"></i>Retiros
						</h2>
						<!-- Tabs Pill: Todos, Abiertos, Cerrados -->
						<div class="daily-rc-tabs-group" role="tablist">
							<button type="button" class="daily-rc-tab-btn is-active" id="rc-tab-all" data-filter="all">Todos ({{ count($recalls) }})</button>
							<button type="button" class="daily-rc-tab-btn" id="rc-tab-open" data-filter="open">Abiertos ({{ count($open) }})</button>
							<button type="button" class="daily-rc-tab-btn" id="rc-tab-closed" data-filter="closed">Cerrados ({{ count($closed) }})</button>
						</div>
					</div>

					<div class="d-flex align-items-center gap-2 flex-wrap">
						<!-- Búsqueda en vivo -->
						<div class="daily-rc-search-box">
							<i class="fa-solid fa-magnifying-glass"></i>
							<input type="text" id="rc-search" class="form-control form-control-sm daily-rc-search-input" placeholder="Buscar folio, producto o lote..." aria-label="Buscar retiro">
						</div>

						<!-- Filtro dropdown de Estado -->
						<div class="dropdown">
							<button class="btn btn-sm btn-light border dropdown-toggle font-weight-600" type="button" id="rc-filter-status-btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<i class="fa-solid fa-filter text-muted mr-1"></i><span id="rc-filter-status-label">Estado</span>
							</button>
							<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" aria-labelledby="rc-filter-status-btn">
								<a class="dropdown-item rc-status-opt font-weight-600 active" href="#" data-status="all">Todos los estados</a>
								<a class="dropdown-item rc-status-opt" href="#" data-status="open">Abiertos</a>
								<a class="dropdown-item rc-status-opt" href="#" data-status="closed">Cerrados</a>
								<a class="dropdown-item rc-status-opt" href="#" data-status="cancelled">Anulados</a>
							</div>
						</div>
					</div>
				</div>

				<div class="daily-rc-card-body p-0">
					@if(count($recalls) === 0)
					<!-- Estado Vacío (Exacto a la foto de referencia) -->
					<div class="daily-rc-empty-state">
						<img src="{{ $U('/img/control_center/retiros_empty.jpg') }}" alt="Crate con productos lácteos y verduras con escudo verde de seguridad" class="daily-rc-empty-img">
						<h3 class="daily-rc-empty-title">Aún no hay retiros registrados</h3>
						<p class="daily-rc-empty-desc">Registra un retiro cuando recibas una notificación de un proveedor o de la autoridad sanitaria.</p>
						@if($canManage)
						<button type="button" class="btn btn-daily-action-primary" id="rc-new-empty">
							<i class="fa-solid fa-plus"></i>
							<span>Crear primer retiro</span>
						</button>
						@else
						<p class="small text-muted font-italic mb-0"><i class="fa-solid fa-lock mr-1"></i>No tiene permisos para registrar retiros de producto.</p>
						@endif
					</div>
					@else
					<!-- Tabla de Retiros con datos existentes -->
					<div class="table-responsive">
						<table class="table table-hover daily-rc-table mb-0" id="rc-table">
							<thead>
								<tr>
									<th>Folio</th>
									<th>Producto</th>
									<th>Clase</th>
									<th>Motivo</th>
									<th class="text-right">Sucursales</th>
									<th class="text-right">Lotes</th>
									<th class="text-right">Por confirmar</th>
									<th>Estado</th>
									<th>Creado</th>
									<th class="text-right">Acción</th>
								</tr>
							</thead>
							<tbody id="rc-table-body">
								@foreach($recalls as $r)
								@php
									$sm = $statusMeta[$r['status']] ?? [$r['status'], 'secondary', '#64748b', '#f1f5f9'];
									$svm = $sevMeta[$r['severity']] ?? [$r['severity'], 'secondary', '#64748b', '#f1f5f9'];
									$hasPending = $r['status'] === 'open' && intval($r['pending_lots']) > 0;
								@endphp
								<tr class="rc-row" data-status="{{ $r['status'] }}" data-search="{{ mb_strtolower($r['recall_code'] . ' ' . $r['product_name'] . ' ' . $r['reason']) }}">
									<td>
										<a href="{{ $U('/recalls?id=' . $r['id']) }}" class="daily-rc-folio-link">{{ $r['recall_code'] }}</a>
									</td>
									<td class="font-weight-600">{{ $r['product_name'] }}</td>
									<td>
										<span class="badge badge-daily-status" style="background: {{ $svm[3] }}; color: {{ $svm[2] }}; border: 1px solid {{ $svm[2] }}33;">
											{{ strtoupper(str_replace('class_', 'Clase ', $r['severity'])) }}
										</span>
									</td>
									<td class="small" title="{{ $r['reason'] }}">{{ \Illuminate\Support\Str::limit($r['reason'], 48) }}</td>
									<td class="text-right">{{ $r['branches'] }}</td>
									<td class="text-right">{{ $r['lots'] }}</td>
									<td class="text-right @if($hasPending) text-danger font-weight-700 @endif">
										{{ $r['status'] === 'open' ? $r['pending_lots'] : '—' }}
									</td>
									<td>
										<span class="badge badge-daily-status" style="background: {{ $sm[3] }}; color: {{ $sm[2] }}; border: 1px solid {{ $sm[2] }}33;">
											<i class="fa-solid fa-circle font-size-8 mr-1"></i>{{ $sm[0] }}
										</span>
									</td>
									<td class="small text-muted">{{ substr($r['created_at'], 0, 10) }}</td>
									<td class="text-right">
										<a class="btn btn-sm btn-light border font-weight-600" href="{{ $U('/recalls?id=' . $r['id']) }}">Ver</a>
									</td>
								</tr>
								@endforeach
								<tr id="rc-no-results-row" style="display: none;">
									<td colspan="10" class="text-center text-muted py-4">
										<i class="fa-solid fa-circle-question mr-1"></i>No se encontraron retiros que coincidan con la búsqueda.
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					@endif
				</div>
			</div>
		</div>

		<!-- Columna Derecha: Guía "Cómo gestionar un retiro" -->
		<div class="col-lg-4 col-xl-4 mb-4">
			<div class="daily-rc-guide-card">
				<div class="daily-rc-guide-header">
					<h3 class="daily-rc-guide-title">
						<i class="fa-solid fa-book-open text-success"></i>Cómo gestionar un retiro
					</h3>
				</div>
				<div class="daily-rc-guide-body">
					<!-- Paso 1 -->
					<div class="daily-rc-step-item">
						<div class="daily-rc-step-left">
							<div class="daily-rc-step-circle @if($selected) is-completed @endif">1</div>
							<div class="daily-rc-step-line"></div>
						</div>
						<div class="daily-rc-step-content">
							<h4 class="daily-rc-step-title">
								<i class="fa-regular fa-file-lines text-muted"></i>Crear retiro
							</h4>
							<p class="daily-rc-step-desc">Define producto, lotes y motivo.</p>
						</div>
					</div>

					<!-- Paso 2 -->
					<div class="daily-rc-step-item">
						<div class="daily-rc-step-left">
							<div class="daily-rc-step-circle @if($selected) is-completed @endif">2</div>
							<div class="daily-rc-step-line"></div>
						</div>
						<div class="daily-rc-step-content">
							<h4 class="daily-rc-step-title">
								<i class="fa-solid fa-box text-muted"></i>Retener lotes
							</h4>
							<p class="daily-rc-step-desc">Cuarentena de existencias afectadas.</p>
						</div>
					</div>

					<!-- Paso 3 -->
					<div class="daily-rc-step-item">
						<div class="daily-rc-step-left">
							<div class="daily-rc-step-circle @if($selected && intval($selected['pending_lots'] ?? 0) === 0) is-completed @endif">3</div>
							<div class="daily-rc-step-line"></div>
						</div>
						<div class="daily-rc-step-content">
							<h4 class="daily-rc-step-title">
								<i class="fa-solid fa-store text-muted"></i>Confirmar separación
							</h4>
							<p class="daily-rc-step-desc">Cada sucursal confirma la separación física.</p>
						</div>
					</div>

					<!-- Paso 4 -->
					<div class="daily-rc-step-item">
						<div class="daily-rc-step-left">
							<div class="daily-rc-step-circle @if($selected && $selected['status'] === 'closed') is-completed @endif">4</div>
						</div>
						<div class="daily-rc-step-content" style="padding-bottom: 6px;">
							<h4 class="daily-rc-step-title">
								<i class="fa-regular fa-circle-check text-muted"></i>Cerrar retiro
							</h4>
							<p class="daily-rc-step-desc">Registra merma o devolución según corresponda.</p>
						</div>
					</div>

					<!-- Alerta de advertencia al pie de la guía -->
					<div class="daily-rc-guide-alert">
						<i class="fa-solid fa-triangle-exclamation"></i>
						<div>
							<div class="daily-rc-guide-alert-title">Retención y separación son pasos distintos</div>
							<div class="daily-rc-guide-alert-text">El bloqueo de venta no confirma la separación física.</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 5. MÓDULO CONECTADO (Calidad & Vencimientos) -->
	<div class="daily-rc-connected-card">
		<div class="daily-rc-connected-bg"></div>
		<div class="daily-rc-connected-left">
			<div class="daily-rc-connected-icon">
				<i class="fa-solid fa-snowflake"></i>
			</div>
			<div>
				<div class="daily-rc-connected-tag">Módulo conectado</div>
				<h3 class="daily-rc-connected-title">Calidad & Vencimientos</h3>
				<p class="daily-rc-connected-desc">Consulta la cuarentena de los lotes afectados.</p>
			</div>
		</div>
		<div class="daily-rc-connected-right">
			<a href="{{ $U('/quality') }}" class="btn-daily-open-module">
				<span>Abrir módulo</span>
				<i class="fa-solid fa-arrow-right"></i>
			</a>
			<p class="daily-rc-connected-footnote">El cierre registra el destino y el costo real según el flujo autorizado.</p>
		</div>
	</div>
</div>

<script>
	window.DairyRecalls = {
		products: {!! json_encode($products) !!},
		suppliers: {!! json_encode($suppliers) !!},
		severities: {!! json_encode($severities) !!},
		dispositions: {!! json_encode($dispositions) !!}
	};
</script>
@stop
