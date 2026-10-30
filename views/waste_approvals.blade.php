@extends('layout.default')

@section('title', 'Bandeja de Autorizaciones de Merma')

@section('content')
@php
	$records = $pendingRecords ?? [];
	$totalPending = count($records);
	$totalCost = 0;
	$withEvidence = 0;
	$withoutEvidence = 0;

	foreach ($records as $r) {
		$totalCost += floatval($r->total_cost ?? 0);
		if (!empty($r->evidence_photo_path)) {
			$withEvidence++;
		} else {
			$withoutEvidence++;
		}
	}

	$branchList = $branches ?? [];
	$catList = $categories ?? [];
	$reasonList = $reasons ?? [];
@endphp

<div class="daily-approvals-view" id="dailyApprovalsView">
	<style>
		/* Estilos generales para Aprobaciones de Merma DAILY */
		.daily-approvals-view {
			font-family: inherit;
			color: #1e293b;
		}

		/* 1. Header Card con banner fotográfico */
		.daily-appr-header-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			margin-bottom: 20px;
			padding: 20px 24px;
			position: relative;
			overflow: hidden;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			display: flex;
			align-items: center;
			justify-content: space-between;
			min-height: 86px;
		}
		.daily-appr-header-left {
			display: flex;
			align-items: center;
			gap: 16px;
			position: relative;
			z-index: 2;
		}
		.daily-appr-header-icon {
			width: 44px;
			height: 44px;
			border-radius: 12px;
			background: #fef3c7;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.daily-appr-header-icon i {
			color: #d97706;
			font-size: 1.25rem;
		}
		.daily-appr-header-title {
			font-size: 1.25rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0;
			letter-spacing: -0.01em;
		}
		.daily-appr-header-sub {
			font-size: 0.84rem;
			color: #64748b;
			margin: 2px 0 0 0;
		}
		.daily-appr-header-actions {
			display: flex;
			align-items: center;
			gap: 10px;
			position: relative;
			z-index: 2;
		}
		.daily-appr-header-bg {
			position: absolute;
			right: 0;
			top: 0;
			bottom: 0;
			width: 45%;
			max-width: 440px;
			background-image: url('{{ $U('/img/control_center/aprobaciones_banner.jpg') }}');
			background-size: cover;
			background-position: right center;
			background-repeat: no-repeat;
			pointer-events: none;
			mask-image: linear-gradient(to right, transparent, black 40%);
			-webkit-mask-image: linear-gradient(to right, transparent, black 40%);
			z-index: 1;
		}

		/* 2. Cuadrícula de KPIs superiores (4 Tarjetas) */
		.daily-appr-kpi-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 14px;
			margin-bottom: 20px;
		}
		.daily-appr-kpi-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 16px 18px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
		}
		.daily-appr-kpi-left {
			display: flex;
			align-items: center;
			gap: 14px;
		}
		.daily-appr-kpi-icon {
			width: 44px;
			height: 44px;
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.15rem;
			flex-shrink: 0;
		}
		.kpi-amber { background: #fef3c7; color: #d97706; }
		.kpi-rose { background: #fee2e2; color: #dc2626; }
		.kpi-green { background: #dcfce7; color: #15803d; }
		.kpi-slate { background: #f1f5f9; color: #475569; }

		.daily-appr-kpi-meta {
			display: flex;
			flex-direction: column;
		}
		.daily-appr-kpi-label {
			font-size: 0.76rem;
			font-weight: 700;
			color: #64748b;
			margin-bottom: 2px;
		}
		.daily-appr-kpi-val {
			font-size: 1.55rem;
			font-weight: 800;
			color: #0f172a;
			line-height: 1.1;
		}
		.daily-appr-kpi-sub {
			font-size: 0.72rem;
			color: #94a3b8;
			margin-top: 2px;
		}

		/* 3. Barra de Búsqueda y Filtros */
		.daily-appr-filters-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 14px 18px;
			margin-bottom: 20px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
		}
		.daily-appr-filters-row {
			display: grid;
			grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
			gap: 12px;
			align-items: end;
		}
		.daily-appr-filter-group label {
			font-size: 0.74rem;
			font-weight: 700;
			color: #64748b;
			margin-bottom: 4px;
			display: block;
			text-transform: uppercase;
			letter-spacing: 0.02em;
		}
		.daily-appr-input {
			height: 38px;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 6px 12px;
			font-size: 0.84rem;
			color: #1e293b;
			background: #ffffff;
			width: 100%;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}
		.daily-appr-input:focus {
			border-color: #00C83B;
			outline: none;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12);
		}
		.daily-appr-search-wrap {
			position: relative;
			display: flex;
			align-items: center;
		}
		.daily-appr-search-wrap i {
			position: absolute;
			left: 12px;
			color: #94a3b8;
			font-size: 0.85rem;
		}
		.daily-appr-search-wrap input {
			padding-left: 34px;
		}
		.daily-appr-btn-clean {
			height: 38px;
			border: 1px solid #e2e8f0;
			background: #f8fafc;
			color: #475569;
			border-radius: 8px;
			padding: 0 16px;
			font-size: 0.82rem;
			font-weight: 600;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			white-space: nowrap;
			transition: all 0.15s ease;
		}
		.daily-appr-btn-clean:hover {
			background: #f1f5f9;
			color: #0f172a;
			border-color: #cbd5e1;
		}

		/* 4. Distribución en dos columnas */
		.daily-appr-main-grid {
			display: grid;
			grid-template-columns: 1.05fr 1.35fr;
			gap: 20px;
			margin-bottom: 24px;
			align-items: start;
		}

		/* Columna Izquierda: Listado */
		.daily-appr-list-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 12px;
		}
		.daily-appr-list-title {
			font-size: 0.95rem;
			font-weight: 800;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-appr-list-title i {
			color: #00C83B;
		}
		.daily-appr-sort-select {
			border: 1px solid #cbd5e1;
			border-radius: 6px;
			padding: 3px 8px;
			font-size: 0.78rem;
			color: #475569;
			background: #ffffff;
			outline: none;
		}

		/* Tarjetas individuales de solicitud */
		.daily-request-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 16px;
			margin-bottom: 12px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			cursor: pointer;
			transition: transform 0.12s ease, border-color 0.15s ease, box-shadow 0.15s ease;
			position: relative;
		}
		.daily-request-card:hover {
			border-color: #cbd5e1;
			box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
		}
		.daily-request-card.active {
			border-color: #00C83B;
			border-left: 4px solid #00C83B;
			background: #ffffff;
			box-shadow: 0 4px 14px rgba(0, 200, 59, 0.08);
		}
		.daily-request-card-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 12px;
		}
		.daily-status-pill {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 3px 10px;
			border-radius: 20px;
			font-size: 0.74rem;
			font-weight: 700;
		}
		.pill-pending { background: #fef3c7; color: #b45309; }
		.pill-approved { background: #dcfce7; color: #15803d; }
		.pill-rejected { background: #fee2e2; color: #dc2626; }

		.daily-request-folio {
			font-size: 0.76rem;
			font-weight: 700;
			color: #64748b;
			font-family: monospace;
		}

		.daily-request-body {
			display: flex;
			gap: 14px;
			align-items: flex-start;
			margin-bottom: 12px;
		}
		.daily-request-thumb-wrap {
			width: 68px;
			height: 68px;
			border-radius: 10px;
			background: #f8fafc;
			border: 1px solid #edf2f7;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.daily-request-thumb-wrap img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		.daily-request-meta-block {
			flex: 1;
			min-width: 0;
		}
		.daily-request-prod-title {
			font-size: 0.95rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0 0 2px 0;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.daily-request-sku {
			font-size: 0.75rem;
			color: #64748b;
			margin-bottom: 6px;
		}
		.daily-request-meta-row {
			display: flex;
			align-items: center;
			gap: 12px;
			font-size: 0.75rem;
			color: #64748b;
			margin-bottom: 4px;
			flex-wrap: wrap;
		}
		.daily-request-meta-row i {
			font-size: 0.75rem;
			margin-right: 3px;
		}
		.daily-request-tags-row {
			display: flex;
			align-items: center;
			gap: 6px;
			margin-top: 6px;
			flex-wrap: wrap;
		}
		.daily-tag-causal {
			font-size: 0.72rem;
			font-weight: 700;
			background: #f1f5f9;
			color: #334155;
			padding: 2px 8px;
			border-radius: 6px;
		}
		.daily-tag-cat {
			font-size: 0.72rem;
			font-weight: 700;
			background: #e0f2fe;
			color: #0369a1;
			padding: 2px 8px;
			border-radius: 6px;
		}
		.daily-request-price-col {
			text-align: right;
			flex-shrink: 0;
		}
		.daily-request-price-val {
			font-size: 1.15rem;
			font-weight: 800;
			color: #ef4444;
			line-height: 1;
		}
		.daily-request-price-unit {
			font-size: 0.74rem;
			color: #64748b;
			margin-top: 3px;
		}

		.daily-request-card-foot {
			border-top: 1px dashed #f1f5f9;
			padding-top: 10px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			font-size: 0.76rem;
		}
		.daily-evidence-status {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			color: #64748b;
		}
		.daily-evidence-status.has-file {
			color: #0284c7;
			font-weight: 600;
		}
		.daily-btn-review-card {
			color: #00C83B;
			font-weight: 700;
			font-size: 0.78rem;
			background: none;
			border: none;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 0;
		}

		/* Columna Derecha: Detalle Completo */
		.daily-detail-panel {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 22px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
		}
		.daily-detail-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding-bottom: 14px;
			border-bottom: 1px solid #f1f5f9;
			margin-bottom: 16px;
		}
		.daily-detail-head-left {
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.daily-detail-head-title {
			font-size: 1rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-detail-head-title i {
			color: #00C83B;
		}
		.daily-detail-case-tag {
			font-size: 0.8rem;
			font-weight: 700;
			color: #64748b;
			font-family: monospace;
		}

		/* Hero Producto en Detalle */
		.daily-detail-hero {
			display: flex;
			align-items: center;
			gap: 18px;
			background: #f8fafc;
			border: 1px solid #edf2f7;
			border-radius: 12px;
			padding: 16px;
			margin-bottom: 18px;
		}
		.daily-detail-hero-img-wrap {
			width: 100px;
			height: 100px;
			border-radius: 10px;
			background: #ffffff;
			border: 1px solid #e2e8f0;
			position: relative;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.daily-detail-hero-img-wrap img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		.daily-hero-illustrative-tag {
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			background: rgba(15, 23, 42, 0.75);
			color: #ffffff;
			font-size: 0.6rem;
			text-align: center;
			padding: 2px 0;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.03em;
		}
		.daily-detail-hero-info {
			flex: 1;
		}
		.daily-detail-hero-title {
			font-size: 1.15rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0 0 2px 0;
		}
		.daily-detail-hero-sku {
			font-size: 0.78rem;
			color: #64748b;
			margin-bottom: 8px;
		}
		.daily-detail-hero-price {
			text-align: right;
			flex-shrink: 0;
		}
		.daily-detail-hero-price-val {
			font-size: 1.45rem;
			font-weight: 800;
			color: #ef4444;
			line-height: 1;
		}
		.daily-detail-hero-price-sub {
			font-size: 0.76rem;
			color: #64748b;
			margin-top: 3px;
		}

		/* Grid de Información General */
		.daily-detail-section-title {
			font-size: 0.85rem;
			font-weight: 800;
			color: #334155;
			margin-bottom: 12px;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.daily-info-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px 18px;
			background: #ffffff;
			border: 1px solid #f1f5f9;
			border-radius: 10px;
			padding: 14px 16px;
			margin-bottom: 16px;
		}
		.daily-info-row {
			display: flex;
			justify-content: space-between;
			font-size: 0.82rem;
			padding: 3px 0;
			border-bottom: 1px dashed #f8fafc;
		}
		.daily-info-label {
			color: #64748b;
			font-weight: 500;
		}
		.daily-info-val {
			color: #0f172a;
			font-weight: 700;
			text-align: right;
		}

		/* Tarjetas lado a lado: Evidencia y Observaciones */
		.daily-dual-cards {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 14px;
			margin-bottom: 16px;
		}
		.daily-sub-card {
			background: #f8fafc;
			border: 1px solid #edf2f7;
			border-radius: 10px;
			padding: 14px;
		}
		.daily-sub-card-title {
			font-size: 0.78rem;
			font-weight: 800;
			color: #475569;
			margin-bottom: 8px;
			display: flex;
			align-items: center;
			gap: 6px;
			text-transform: uppercase;
			letter-spacing: 0.02em;
		}
		.daily-sub-card-body {
			font-size: 0.82rem;
			color: #64748b;
			line-height: 1.4;
		}

		/* Alerta preventiva ámbar */
		.daily-alert-warning-box {
			background: #fffbeb;
			border: 1px solid #fde68a;
			border-radius: 10px;
			padding: 12px 16px;
			display: flex;
			align-items: flex-start;
			gap: 12px;
			margin-bottom: 18px;
		}
		.daily-alert-warning-box i {
			color: #f59e0b;
			font-size: 1.15rem;
			margin-top: 2px;
		}
		.daily-alert-warning-text-title {
			font-size: 0.82rem;
			font-weight: 800;
			color: #92400e;
			margin-bottom: 2px;
		}
		.daily-alert-warning-text-sub {
			font-size: 0.74rem;
			color: #b45309;
			margin: 0;
		}

		/* Bloque de Decisión */
		.daily-decision-box {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 16px;
			margin-bottom: 18px;
		}
		.daily-decision-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 12px;
		}
		.daily-decision-title {
			font-size: 0.88rem;
			font-weight: 800;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.daily-decision-title i {
			color: #00C83B;
		}
		.daily-decision-ref-date {
			font-size: 0.74rem;
			color: #64748b;
		}
		.daily-decision-textarea {
			width: 100%;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 10px 12px;
			font-size: 0.82rem;
			color: #1e293b;
			resize: vertical;
			min-height: 56px;
			outline: none;
			margin-bottom: 4px;
		}
		.daily-decision-textarea:focus {
			border-color: #00C83B;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12);
		}
		.daily-decision-counter {
			font-size: 0.7rem;
			color: #94a3b8;
			text-align: right;
			margin-bottom: 12px;
		}
		.daily-decision-buttons-row {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 12px;
		}
		.daily-btn-approve {
			flex: 1;
			height: 42px;
			border: 1px solid #00C83B;
			background: #00C83B;
			color: #ffffff;
			border-radius: 8px;
			font-size: 0.88rem;
			font-weight: 700;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.daily-btn-approve:hover:not(:disabled) {
			background: #00b334;
			box-shadow: 0 3px 8px rgba(0, 200, 59, 0.25);
		}
		.daily-btn-reject {
			flex: 1;
			height: 42px;
			border: 1px solid #ef4444;
			background: #ffffff;
			color: #ef4444;
			border-radius: 8px;
			font-size: 0.88rem;
			font-weight: 700;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.daily-btn-reject:hover:not(:disabled) {
			background: #fee2e2;
		}
		.daily-btn-approve:disabled, .daily-btn-reject:disabled {
			opacity: 0.55;
			cursor: not-allowed;
		}
		.daily-decision-info-note {
			font-size: 0.75rem;
			color: #64748b;
			line-height: 1.4;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.daily-decision-info-note i {
			color: #00C83B;
			font-size: 0.85rem;
		}

		/* Stepper de ciclo de vida al pie del detalle */
		.daily-stepper {
			display: flex;
			align-items: center;
			justify-content: space-between;
			position: relative;
			padding: 10px 8px 0 8px;
		}
		.daily-stepper::before {
			content: '';
			position: absolute;
			top: 20px;
			left: 20px;
			right: 20px;
			height: 2px;
			background: #e2e8f0;
			z-index: 1;
		}
		.daily-step-item {
			position: relative;
			z-index: 2;
			display: flex;
			flex-direction: column;
			align-items: center;
			gap: 6px;
		}
		.daily-step-circle {
			width: 20px;
			height: 20px;
			border-radius: 50%;
			background: #ffffff;
			border: 2px solid #cbd5e1;
		}
		.daily-step-item.completed .daily-step-circle {
			border-color: #00C83B;
			background: #dcfce7;
		}
		.daily-step-item.active .daily-step-circle {
			border-color: #00C83B;
			background: #ffffff;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.2);
		}
		.daily-step-label {
			font-size: 0.7rem;
			color: #64748b;
			font-weight: 600;
		}
		.daily-step-item.active .daily-step-label {
			color: #0f172a;
			font-weight: 700;
		}

		/* 5. Módulos Conectados */
		.daily-connected-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 20px 24px;
			margin-top: 10px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
		}
		.daily-connected-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 16px;
		}
		.daily-connected-title {
			font-size: 0.95rem;
			font-weight: 800;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-connected-title i {
			color: #00C83B;
		}
		.daily-connected-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 20px;
		}
		.daily-connected-col-title {
			font-size: 0.72rem;
			font-weight: 800;
			color: #64748b;
			text-transform: uppercase;
			letter-spacing: 0.03em;
			margin-bottom: 10px;
		}
		.daily-connected-link-box {
			background: #f8fafc;
			border: 1px solid #edf2f7;
			border-radius: 10px;
			padding: 12px 14px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			text-decoration: none !important;
			color: inherit !important;
			margin-bottom: 10px;
			transition: all 0.15s ease;
		}
		.daily-connected-link-box:hover {
			background: #ffffff;
			border-color: #cbd5e1;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
		}
		.daily-connected-link-left {
			display: flex;
			align-items: center;
			gap: 12px;
		}
		.daily-connected-link-icon {
			width: 32px;
			height: 32px;
			border-radius: 8px;
			background: #dcfce7;
			color: #15803d;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 0.9rem;
			flex-shrink: 0;
		}
		.daily-connected-link-name {
			font-size: 0.85rem;
			font-weight: 700;
			color: #0f172a;
			margin-bottom: 2px;
		}
		.daily-connected-link-desc {
			font-size: 0.74rem;
			color: #64748b;
		}

		/* Adaptación Móvil */
		@media (max-width: 992px) {
			.daily-appr-kpi-grid {
				grid-template-columns: repeat(2, 1fr);
			}
			.daily-appr-filters-row {
				grid-template-columns: 1fr;
			}
			.daily-appr-main-grid {
				grid-template-columns: 1fr;
			}
			.daily-connected-grid {
				grid-template-columns: 1fr;
			}
			.daily-appr-header-bg {
				display: none;
			}
		}
		@media (max-width: 576px) {
			.daily-appr-kpi-grid {
				grid-template-columns: 1fr;
			}
			.daily-info-grid {
				grid-template-columns: 1fr;
			}
			.daily-dual-cards {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<!-- 1. ENCABEZADO SUPERIOR -->
	<div class="daily-appr-header-card">
		<div class="daily-appr-header-left">
			<div class="daily-appr-header-icon">
				<i class="fa-solid fa-user-check"></i>
			</div>
			<div>
				<h4 class="daily-appr-header-title">Bandeja de Autorizaciones de Merma</h4>
				<p class="daily-appr-header-sub">Revisa solicitudes, consulta evidencia y registra tu decisión.</p>
			</div>
		</div>
		<div class="daily-appr-header-actions">
			<a href="{{ $U('/waste') }}" class="btn btn-sm btn-white bg-white border font-weight-600 px-3 py-2 text-dark" style="border-radius: 8px;">
				<i class="fa-solid fa-chart-pie mr-1 text-muted"></i> Tablero
			</a>
			<a href="{{ $U('/waste/report') }}" class="btn btn-sm text-white font-weight-700 px-3 py-2" style="background: #00C83B; border-radius: 8px;">
				<i class="fa-solid fa-plus mr-1"></i> Registrar Caso
			</a>
			<div class="dropdown d-inline">
				<button class="btn btn-sm btn-white bg-white border font-weight-600 px-3 py-2 text-dark dropdown-toggle" data-toggle="dropdown" style="border-radius: 8px;">
					<i class="fa-solid fa-download mr-1 text-muted"></i> Exportar
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
					<a class="dropdown-item small" href="javascript:void(0)" onclick="exportApprovalsCsv();">
						<i class="fa-solid fa-file-csv mr-2 text-success"></i>Exportar lista en CSV
					</a>
				</div>
			</div>
		</div>
		<div class="daily-appr-header-bg"></div>
	</div>

	<!-- 2. CUADRÍCULA DE KPIS SUPERIORES (4 Métricas) -->
	<div class="daily-appr-kpi-grid">
		<!-- 1. Solicitudes pendientes -->
		<div class="daily-appr-kpi-card">
			<div class="daily-appr-kpi-left">
				<div class="daily-appr-kpi-icon kpi-amber">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="daily-appr-kpi-meta">
					<span class="daily-appr-kpi-label">Solicitudes pendientes</span>
					<span class="daily-appr-kpi-val" id="kpiPendingCount">{{ $totalPending }}</span>
					<span class="daily-appr-kpi-sub">Por revisar y autorizar</span>
				</div>
			</div>
			<div class="text-muted"><i class="fa-regular fa-file-lines fa-lg" style="opacity: 0.35;"></i></div>
		</div>

		<!-- 2. Importe solicitado -->
		<div class="daily-appr-kpi-card">
			<div class="daily-appr-kpi-left">
				<div class="daily-appr-kpi-icon kpi-rose">
					<i class="fa-solid fa-dollar-sign"></i>
				</div>
				<div class="daily-appr-kpi-meta">
					<span class="daily-appr-kpi-label">Importe solicitado</span>
					<span class="daily-appr-kpi-val" id="kpiTotalCost">${{ number_format($totalCost, 2) }}</span>
					<span class="daily-appr-kpi-sub">No aprobado</span>
				</div>
			</div>
			<div class="text-danger"><i class="fa-solid fa-chart-simple fa-lg" style="opacity: 0.35;"></i></div>
		</div>

		<!-- 3. Con archivo adjunto -->
		<div class="daily-appr-kpi-card">
			<div class="daily-appr-kpi-left">
				<div class="daily-appr-kpi-icon kpi-green">
					<i class="fa-solid fa-paperclip"></i>
				</div>
				<div class="daily-appr-kpi-meta">
					<span class="daily-appr-kpi-label">Con archivo adjunto</span>
					<span class="daily-appr-kpi-val" id="kpiWithEvidence">{{ $withEvidence }}</span>
					<span class="daily-appr-kpi-sub">No implica validación</span>
				</div>
			</div>
			<div class="text-success"><i class="fa-regular fa-image fa-lg" style="opacity: 0.35;"></i></div>
		</div>

		<!-- 4. Sin archivo -->
		<div class="daily-appr-kpi-card">
			<div class="daily-appr-kpi-left">
				<div class="daily-appr-kpi-icon kpi-slate">
					<i class="fa-regular fa-file"></i>
				</div>
				<div class="daily-appr-kpi-meta">
					<span class="daily-appr-kpi-label">Sin archivo</span>
					<span class="daily-appr-kpi-val" id="kpiWithoutEvidence">{{ $withoutEvidence }}</span>
					<span class="daily-appr-kpi-sub">Solicitudes pendientes</span>
				</div>
			</div>
			<div class="text-muted"><i class="fa-regular fa-file-lines fa-lg" style="opacity: 0.35;"></i></div>
		</div>
	</div>

	<!-- 3. BARRA DE BÚSQUEDA Y FILTROS -->
	<div class="daily-appr-filters-card">
		<form id="approvalsFilterForm" onsubmit="return false;">
			<div class="daily-appr-filters-row">
				<!-- Búsqueda -->
				<div class="daily-appr-filter-group">
					<label>Buscar</label>
					<div class="daily-appr-search-wrap">
						<i class="fa-solid fa-magnifying-glass"></i>
						<input type="text" class="daily-appr-input" id="searchApprovalInput" placeholder="Buscar por caso, producto o lote...">
					</div>
				</div>

				<!-- Estado -->
				<div class="daily-appr-filter-group">
					<label>Estado</label>
					<select class="daily-appr-input" id="filterStatus">
						<option value="pending" selected>Pendientes</option>
						<option value="all">Todas</option>
					</select>
				</div>

				<!-- Sucursal -->
				<div class="daily-appr-filter-group">
					<label>Sucursal</label>
					<select class="daily-appr-input" id="filterBranch">
						<option value="">Todas</option>
						@foreach($branchList as $b)
						<option value="{{ $b->name }}">{{ $b->name }}</option>
						@endforeach
					</select>
				</div>

				<!-- Causal -->
				<div class="daily-appr-filter-group">
					<label>Causal</label>
					<select class="daily-appr-input" id="filterReason">
						<option value="">Todas</option>
						@foreach($reasonList as $r)
						<option value="{{ $r->name }}">{{ $r->code }} {{ $r->name }}</option>
						@endforeach
					</select>
				</div>

				<!-- Producto / Categoría -->
				<div class="daily-appr-filter-group">
					<label>Producto / Categoría</label>
					<select class="daily-appr-input" id="filterCategory">
						<option value="">Todas</option>
						@foreach($catList as $cat)
						<option value="{{ $cat }}">{{ $cat }}</option>
						@endforeach
					</select>
				</div>

				<!-- Botón Limpiar -->
				<div>
					<button type="button" class="daily-appr-btn-clean" id="btnResetFilters">
						<i class="fa-solid fa-rotate-left"></i> Limpiar
					</button>
				</div>
			</div>
		</form>
	</div>

	<!-- 4. DISTRIBUCIÓN PRINCIPAL EN DOS COLUMNAS -->
	<div class="daily-appr-main-grid">
		<!-- COLUMNA IZQUIERDA: LISTADO DE SOLICITUDES -->
		<div>
			<div class="daily-appr-list-head">
				<div class="daily-appr-list-title">
					<i class="fa-solid fa-clipboard-list"></i>
					<span>Solicitudes pendientes (<span id="visibleCardsCount">{{ $totalPending }}</span>)</span>
				</div>
				<div>
					<select class="daily-appr-sort-select" id="sortSelect">
						<option value="date_desc">Fecha (más reciente)</option>
						<option value="date_asc">Fecha (más antigua)</option>
						<option value="cost_desc">Importe (mayor a menor)</option>
						<option value="cost_asc">Importe (menor a mayor)</option>
					</select>
				</div>
			</div>

			<div id="requestsListContainer">
				@forelse($records as $idx => $p)
				@php
					// Identificación fotográfica ilustrativa de apoyo
					$sku = strtoupper(trim($p->product_sku ?? ''));
					$thumbImg = '/img/control_center/prod_bebida_proteica.jpg';
					if (strpos($sku, 'BEV') !== false || strpos(strtolower($p->product_name), 'bebida') !== false) {
						$thumbImg = '/img/control_center/prod_bebida_proteica.jpg';
					} elseif (strpos($sku, 'MLK') !== false || strpos(strtolower($p->product_name), 'leche') !== false) {
						$thumbImg = '/img/control_center/prod_leche_galon.jpg';
					} elseif (strpos($sku, 'CRM') !== false || strpos(strtolower($p->product_name), 'crema') !== false) {
						$thumbImg = '/img/control_center/prod_crema_leche.jpg';
					}
					$hasEvidence = !empty($p->evidence_photo_path);
				@endphp
				<div class="daily-request-card {{ $idx === 0 ? 'active' : '' }}"
				     id="approval-card-{{ $p->id }}"
				     data-id="{{ $p->id }}"
				     data-case="{{ $p->case_number ?? ('MRM-#' . $p->id) }}"
				     data-product="{{ $p->product_name }}"
				     data-sku="{{ $p->product_sku ?? '—' }}"
				     data-branch="{{ $p->branch_name }}"
				     data-date="{{ date('d/m/Y H:i', strtotime($p->created_timestamp)) }}"
				     data-raw-date="{{ $p->created_timestamp }}"
				     data-reporter="{{ $p->reported_by_username }}"
				     data-reporter-id="{{ $p->reported_by_user_id }}"
				     data-reason-code="{{ $p->reason_code }}"
				     data-reason-name="{{ $p->reason_name }}"
				     data-category="{{ $p->reason_category }}"
				     data-amount="{{ $p->amount }}"
				     data-qu="{{ $p->qu_name }}"
				     data-unit-cost="{{ number_format($p->unit_cost, 2) }}"
				     data-total-cost="{{ number_format($p->total_cost, 2) }}"
				     data-batch="{{ $p->batch_number ?? '' }}"
				     data-best-before="{{ !empty($p->best_before_date) ? date('d/m/Y', strtotime($p->best_before_date)) : '' }}"
				     data-notes="{{ $p->notes ?? '' }}"
				     data-has-evidence="{{ $hasEvidence ? '1' : '0' }}"
				     data-evidence-url="{{ $hasEvidence ? $U($p->evidence_photo_path) : '' }}"
				     data-thumb="{{ $U($thumbImg) }}">
					
					<div class="daily-request-card-head">
						<span class="daily-status-pill pill-pending">
							<i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i> Pendiente
						</span>
						<span class="daily-request-folio">{{ $p->case_number ?? ('MRM-#' . $p->id) }}</span>
					</div>

					<div class="daily-request-body">
						<div class="daily-request-thumb-wrap">
							<img src="{{ $U($thumbImg) }}" alt="{{ $p->product_name }}" onerror="this.src='{{ $U('/img/control_center/prod_bebida_proteica.jpg') }}'">
						</div>
						<div class="daily-request-meta-block">
							<h6 class="daily-request-prod-title">{{ $p->product_name }}</h6>
							<div class="daily-request-sku">{{ $p->product_sku ?? 'SKU pendiente' }}</div>
							<div class="daily-request-meta-row">
								<span><i class="fa-regular fa-calendar"></i> {{ date('d/m/Y H:i', strtotime($p->created_timestamp)) }}</span>
								<span><i class="fa-solid fa-store"></i> {{ $p->branch_name }}</span>
							</div>
							<div class="daily-request-meta-row">
								<span><i class="fa-regular fa-user"></i> {{ $p->reported_by_username }}</span>
							</div>
							<div class="daily-request-tags-row">
								<span class="daily-tag-causal">{{ $p->reason_code }} {{ $p->reason_name }}</span>
								<span class="daily-tag-cat">{{ $p->reason_category }}</span>
							</div>
						</div>
						<div class="daily-request-price-col">
							<div class="daily-request-price-val">${{ number_format($p->total_cost, 2) }}</div>
							<div class="daily-request-price-unit">${{ number_format($p->unit_cost, 2) }}/u &middot; {{ $p->amount }} {{ $p->qu_name }}</div>
						</div>
					</div>

					<div class="daily-request-card-foot">
						<div class="daily-evidence-status {{ $hasEvidence ? 'has-file' : '' }}">
							@if($hasEvidence)
							<i class="fa-solid fa-paperclip"></i> Con archivo adjunto
							@else
							<i class="fa-regular fa-file"></i> Sin archivo
							@endif
						</div>
						<button type="button" class="daily-btn-review-card">
							Revisar solicitud <i class="fa-solid fa-arrow-right"></i>
						</button>
					</div>
				</div>
				@empty
				<div class="p-5 text-center bg-white border rounded" style="border-radius: 12px;">
					<i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>
					<h6 class="font-weight-bold text-dark">¡Bandeja al día!</h6>
					<p class="text-muted small mb-0">No hay solicitudes de merma pendientes de autorización en este momento.</p>
				</div>
				@endforelse
			</div>
		</div>

		<!-- COLUMNA DERECHA: DETALLE COMPLETO DE LA SOLICITUD SELECCIONADA -->
		<div>
			<div class="daily-detail-panel" id="detailPanel">
				<!-- Cabecera del Detalle -->
				<div class="daily-detail-head">
					<div class="daily-detail-head-left">
						<h5 class="daily-detail-head-title">
							<i class="fa-solid fa-file-signature"></i> Detalle de la solicitud
						</h5>
						<span class="daily-status-pill pill-pending" id="detailStatusPill">
							<i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i> Pendiente
						</span>
					</div>
					<div class="daily-detail-case-tag" id="detailCaseFolio">
						Caso: MRM-202610-0089
					</div>
				</div>

				<!-- Hero del Producto -->
				<div class="daily-detail-hero">
					<div class="daily-detail-hero-img-wrap">
						<img src="{{ $U('/img/control_center/prod_bebida_proteica.jpg') }}" id="detailHeroImg" alt="Producto">
						<div class="daily-hero-illustrative-tag">Imagen ilustrativa</div>
					</div>
					<div class="daily-detail-hero-info">
						<h4 class="daily-detail-hero-title" id="detailProdTitle">Bebida proteica refrigerada</h4>
						<div class="daily-detail-hero-sku" id="detailProdSku">DAIRY-BEV-002</div>
						<div class="d-flex align-items-center gap-2 flex-wrap">
							<span class="daily-tag-causal" id="detailCausalBadge">A01 Producto dañado</span>
							<span class="daily-tag-cat" id="detailCatBadge">Averías</span>
						</div>
					</div>
					<div class="daily-detail-hero-price">
						<div class="daily-detail-hero-price-val" id="detailTotalPrice">$16.80</div>
						<div class="daily-detail-hero-price-sub" id="detailUnitPriceBreakdown">$2.40/u &middot; 7 Pote</div>
					</div>
				</div>

				<!-- Sección: Información General -->
				<div class="daily-detail-section-title">
					<i class="fa-solid fa-circle-nodes"></i> Información general
				</div>
				<div class="daily-info-grid">
					<div class="daily-info-row">
						<span class="daily-info-label">Fecha y hora</span>
						<span class="daily-info-val" id="infoDateTime">22/10/2026 18:44</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Cantidad</span>
						<span class="daily-info-val" id="infoAmount">7 Pote</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Sucursal</span>
						<span class="daily-info-val" id="infoBranch">—</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Costo unitario</span>
						<span class="daily-info-val" id="infoUnitCost">$2.40</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Reportado por</span>
						<span class="daily-info-val" id="infoReporter">rgiovine</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Pérdida total</span>
						<span class="daily-info-val text-danger" id="infoTotalCost">$16.80</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Lote</span>
						<span class="daily-info-val" id="infoBatch">No registrado</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Producto</span>
						<span class="daily-info-val" id="infoProduct">Bebida proteica refrigerada</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Vence el</span>
						<span class="daily-info-val" id="infoBestBefore">—</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">SKU / Clave</span>
						<span class="daily-info-val" id="infoSku">DAIRY-BEV-002</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Categoría</span>
						<span class="daily-info-val" id="infoCategory">Averías</span>
					</div>
					<div class="daily-info-row">
						<span class="daily-info-label">Causal</span>
						<span class="daily-info-val" id="infoCausal">A01 - Producto dañado</span>
					</div>
				</div>

				<!-- Tarjetas duales: Evidencia y Observaciones -->
				<div class="daily-dual-cards">
					<!-- Evidencia -->
					<div class="daily-sub-card">
						<div class="daily-sub-card-title">
							<i class="fa-solid fa-paperclip text-muted"></i> Evidencia
						</div>
						<div class="daily-sub-card-body" id="detailEvidenceBody">
							<div class="d-flex align-items-center gap-2">
								<i class="fa-regular fa-file text-muted fa-lg"></i>
								<div>
									<div class="font-weight-bold text-dark">Sin archivo adjunto</div>
									<div class="small text-muted">No se proporcionó evidencia en la solicitud.</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Observaciones -->
					<div class="daily-sub-card">
						<div class="daily-sub-card-title">
							<i class="fa-regular fa-comment-dots text-muted"></i> Observaciones
						</div>
						<div class="daily-sub-card-body" id="detailNotesBody">
							Sin observaciones registradas.
						</div>
					</div>
				</div>

				<!-- Alerta preventiva de política de evidencia y autoaprobación -->
				<div class="daily-alert-warning-box">
					<i class="fa-solid fa-triangle-exclamation"></i>
					<div>
						<div class="daily-alert-warning-text-title">Comprueba requisitos de evidencia y autorización según la política vigente.</div>
						<div class="daily-alert-warning-text-sub">Autorización sujeta a permisos y reglas de autoaprobación.</div>
					</div>
				</div>

				<!-- Bloque de Decisión de Autorización -->
				<div class="daily-decision-box">
					<div class="daily-decision-head">
						<div class="daily-decision-title">
							<i class="fa-solid fa-file-circle-check"></i> Decisión de autorización
						</div>
						<div class="daily-decision-ref-date">
							<i class="fa-regular fa-calendar mr-1"></i> Fecha de referencia: <strong>{{ date('d/m/Y') }}</strong>
						</div>
					</div>

					<!-- Advertencia de Autoaprobación (Segregación de Funciones) -->
					<div class="alert alert-warning py-2 px-3 small mb-3" id="selfApprovalWarning" style="display: none;">
						<i class="fa-solid fa-user-shield mr-1"></i>
						<strong>Segregación de funciones:</strong> Has reportado esta merma. La política del sistema exige dictamen por parte de otro supervisor.
					</div>

					<div>
						<label class="small font-weight-700 text-muted mb-1">Motivo de decisión (opcional en aprobación, obligatorio en rechazo)</label>
						<textarea class="daily-decision-textarea" id="decisionReasonInput" placeholder="Agrega un motivo en caso de rechazo..."></textarea>
						<div class="daily-decision-counter"><span id="decisionCharCount">0</span>/500</div>
					</div>

					<div class="daily-decision-buttons-row">
						<button type="button" class="daily-btn-approve" id="btnApproveAction">
							<i class="fa-solid fa-check"></i> Aprobar
						</button>
						<button type="button" class="daily-btn-reject" id="btnRejectAction">
							<i class="fa-solid fa-times"></i> Rechazar
						</button>
					</div>

					<div class="daily-decision-info-note">
						<i class="fa-solid fa-circle-info"></i>
						<span>Aprobar puede aplicar la baja según configuración; valida existencias y evita doble descuento.</span>
					</div>
				</div>

				<!-- Stepper de Progreso y Trazabilidad -->
				<div class="daily-stepper">
					<div class="daily-step-item completed">
						<div class="daily-step-circle"></div>
						<span class="daily-step-label">Solicitud</span>
					</div>
					<div class="daily-step-item active">
						<div class="daily-step-circle"></div>
						<span class="daily-step-label">Revisión</span>
					</div>
					<div class="daily-step-item">
						<div class="daily-step-circle"></div>
						<span class="daily-step-label">Decisión</span>
					</div>
					<div class="daily-step-item">
						<div class="daily-step-circle"></div>
						<span class="daily-step-label">Movimiento</span>
					</div>
					<div class="daily-step-item">
						<div class="daily-step-circle"></div>
						<span class="daily-step-label">Auditoría</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 5. MÓDULOS CONECTADOS CON APROBACIONES -->
	<div class="daily-connected-card">
		<div class="daily-connected-head">
			<div class="daily-connected-title">
				<i class="fa-solid fa-diagram-project"></i> Módulos conectados con Aprobaciones
			</div>
			<div>
				<a href="{{ $U('/system-map') }}" class="small text-muted font-weight-600">Ver mapa del sistema</a>
			</div>
		</div>

		<div class="daily-connected-grid">
			<!-- Recibe información de -->
			<div>
				<div class="daily-connected-col-title">RECIBE INFORMACIÓN DE</div>
				<a href="{{ $U('/waste/report') }}" class="daily-connected-link-box">
					<div class="daily-connected-link-left">
						<div class="daily-connected-link-icon">
							<i class="fa-solid fa-file-invoice"></i>
						</div>
						<div>
							<div class="daily-connected-link-name">Registrar Merma</div>
							<div class="daily-connected-link-desc">La merma queda pendiente de aprobación según política</div>
						</div>
					</div>
					<div><i class="fa-solid fa-arrow-right text-muted"></i></div>
				</a>
			</div>

			<!-- Actualiza / Alimenta a -->
			<div>
				<div class="daily-connected-col-title">ACTUALIZA / ALIMENTA A</div>
				<a href="{{ $U('/stockoverview') }}" class="daily-connected-link-box">
					<div class="daily-connected-link-left">
						<div class="daily-connected-link-icon">
							<i class="fa-solid fa-boxes-stacked"></i>
						</div>
						<div>
							<div class="daily-connected-link-name">Stock y Movimientos</div>
							<div class="daily-connected-link-desc">Aprobar descuenta stock (transacción de consumo)</div>
						</div>
					</div>
					<div><i class="fa-solid fa-arrow-right text-muted"></i></div>
				</a>
				<a href="{{ $U('/workforce') }}" class="daily-connected-link-box">
					<div class="daily-connected-link-left">
						<div class="daily-connected-link-icon">
							<i class="fa-solid fa-list-check"></i>
						</div>
						<div>
							<div class="daily-connected-link-name">Tareas y Roles</div>
							<div class="daily-connected-link-desc">Notificaciones y tareas según reglas de aprobación</div>
						</div>
					</div>
					<div><i class="fa-solid fa-arrow-right text-muted"></i></div>
				</a>
			</div>
		</div>
	</div>
</div>

<!-- Modal para ver evidencia fotográfica -->
<div class="modal fade" id="approvalPhotoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark" id="apprModalTitle">
					<i class="fa-solid fa-camera mr-2 text-primary"></i>Evidencia Adjunta
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body text-center p-3 bg-light">
				<img id="apprEvidenceImg" src="" class="img-fluid rounded shadow-xs" style="max-height: 480px; object-fit: contain;">
			</div>
			<div class="modal-footer bg-white py-2 border-top">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     LÓGICA JAVASCRIPT DE APROBACIONES (SELECCIÓN, FILTROS, DECISIONES, ALERTAS)
     ========================================================================== -->
<script>
document.addEventListener("DOMContentLoaded", function() {
	var currentUserId = {{ intval($currentUserId ?? 1) }};
	var canSelfApprove = {{ $canSelfApprove ? 'true' : 'false' }};
	var activeCard = null;

	var cards = document.querySelectorAll('.daily-request-card');
	if (cards.length > 0) {
		selectRequest(cards[0]);
	}

	// 1. Manejo de clic en tarjetas
	cards.forEach(function(card) {
		card.addEventListener('click', function() {
			selectRequest(this);
		});
	});

	function selectRequest(card) {
		cards.forEach(function(c) { c.classList.remove('active'); });
		card.classList.add('active');
		activeCard = card;

		var id = card.getAttribute('data-id');
		var caseNum = card.getAttribute('data-case');
		var prod = card.getAttribute('data-product');
		var sku = card.getAttribute('data-sku');
		var branch = card.getAttribute('data-branch');
		var date = card.getAttribute('data-date');
		var reporter = card.getAttribute('data-reporter');
		var reporterId = parseInt(card.getAttribute('data-reporter-id') || '0', 10);
		var reasonCode = card.getAttribute('data-reason-code');
		var reasonName = card.getAttribute('data-reason-name');
		var cat = card.getAttribute('data-category');
		var amount = card.getAttribute('data-amount');
		var qu = card.getAttribute('data-qu');
		var unitCost = card.getAttribute('data-unit-cost');
		var totalCost = card.getAttribute('data-total-cost');
		var batch = card.getAttribute('data-batch') || 'No registrado';
		var bestBefore = card.getAttribute('data-best-before') || '—';
		var notes = card.getAttribute('data-notes') || 'Sin observaciones registradas.';
		var hasEvidence = card.getAttribute('data-has-evidence') === '1';
		var evidenceUrl = card.getAttribute('data-evidence-url') || '';
		var thumb = card.getAttribute('data-thumb');

		// Actualizar panel de detalle
		document.getElementById('detailCaseFolio').textContent = 'Caso: ' + caseNum;
		document.getElementById('detailHeroImg').src = thumb;
		document.getElementById('detailProdTitle').textContent = prod;
		document.getElementById('detailProdSku').textContent = sku;
		document.getElementById('detailCausalBadge').textContent = reasonCode + ' ' + reasonName;
		document.getElementById('detailCatBadge').textContent = cat;
		document.getElementById('detailTotalPrice').textContent = '$' + totalCost;
		document.getElementById('detailUnitPriceBreakdown').textContent = '$' + unitCost + '/u · ' + amount + ' ' + qu;

		// Información General
		document.getElementById('infoDateTime').textContent = date;
		document.getElementById('infoAmount').textContent = amount + ' ' + qu;
		document.getElementById('infoBranch').textContent = branch;
		document.getElementById('infoUnitCost').textContent = '$' + unitCost;
		document.getElementById('infoReporter').textContent = reporter;
		document.getElementById('infoTotalCost').textContent = '$' + totalCost;
		document.getElementById('infoBatch').textContent = batch;
		document.getElementById('infoProduct').textContent = prod;
		document.getElementById('infoBestBefore').textContent = bestBefore;
		document.getElementById('infoSku').textContent = sku;
		document.getElementById('infoCategory').textContent = cat;
		document.getElementById('infoCausal').textContent = reasonCode + ' - ' + reasonName;

		// Evidencia
		var evBody = document.getElementById('detailEvidenceBody');
		if (hasEvidence) {
			evBody.innerHTML = '<div class="d-flex align-items-center justify-content-between">' +
				'<div class="d-flex align-items-center gap-2">' +
					'<i class="fa-solid fa-paperclip text-success fa-lg mr-2"></i>' +
					'<div>' +
						'<div class="font-weight-bold text-dark">Evidencia disponible</div>' +
						'<div class="small text-muted">Archivo adjunto al reporte</div>' +
					'</div>' +
				'</div>' +
				'<button type="button" class="btn btn-sm btn-outline-info font-weight-600" id="btnOpenEvidence" style="border-radius: 6px;">' +
					'<i class="fa-solid fa-camera mr-1"></i>Ver foto' +
				'</button>' +
			'</div>';

			var btnEv = document.getElementById('btnOpenEvidence');
			if (btnEv) {
				btnEv.addEventListener('click', function() {
					document.getElementById('apprModalTitle').textContent = 'Evidencia: ' + caseNum + ' (' + prod + ')';
					document.getElementById('apprEvidenceImg').src = evidenceUrl;
					$('#approvalPhotoModal').modal('show');
				});
			}
		} else {
			evBody.innerHTML = '<div class="d-flex align-items-center gap-2">' +
				'<i class="fa-regular fa-file text-muted fa-lg mr-2"></i>' +
				'<div>' +
					'<div class="font-weight-bold text-dark">Sin archivo adjunto</div>' +
					'<div class="small text-muted">No se proporcionó evidencia en la solicitud.</div>' +
				'</div>' +
			'</div>';
		}

		// Observaciones
		document.getElementById('detailNotesBody').textContent = notes;

		// Segregación de funciones (Autoaprobación)
		var isSelfReported = (reporterId === currentUserId);
		var warningEl = document.getElementById('selfApprovalWarning');
		var btnApprove = document.getElementById('btnApproveAction');
		var btnReject = document.getElementById('btnRejectAction');

		if (isSelfReported && !canSelfApprove) {
			warningEl.style.display = 'block';
			btnApprove.disabled = true;
			btnApprove.title = 'Segregación de funciones: no puedes autoaprobar tus propias solicitudes';
			btnReject.disabled = true;
			btnReject.title = 'Segregación de funciones: no puedes dictaminar tus propias solicitudes';
		} else {
			warningEl.style.display = 'none';
			btnApprove.disabled = false;
			btnApprove.title = '';
			btnReject.disabled = false;
			btnReject.title = '';
		}

		// Scroll suave al detalle en móviles
		if (window.innerWidth <= 992) {
			document.getElementById('detailPanel').scrollIntoView({ behavior: 'smooth' });
		}
	}

	// 2. Contador de caracteres del motivo de decisión
	var reasonInput = document.getElementById('decisionReasonInput');
	var charCount = document.getElementById('decisionCharCount');
	if (reasonInput && charCount) {
		reasonInput.addEventListener('input', function() {
			charCount.textContent = this.value.length;
		});
	}

	// 3. Acción de Aprobar
	var btnApprove = document.getElementById('btnApproveAction');
	if (btnApprove) {
		btnApprove.addEventListener('click', function() {
			if (!activeCard) return;
			var id = activeCard.getAttribute('data-id');
			var caseNum = activeCard.getAttribute('data-case');
			var prod = activeCard.getAttribute('data-product');
			var amount = activeCard.getAttribute('data-amount') + ' ' + activeCard.getAttribute('data-qu');
			var total = '$' + activeCard.getAttribute('data-total-cost');

			bootbox.confirm({
				title: "<i class='fa-solid fa-circle-check text-success mr-2'></i>Confirmar Autorización de Merma",
				message: "<p>¿Autorizar formalmente el caso <strong>" + caseNum + "</strong>?</p>" +
				         "<div class='p-3 bg-light rounded border mb-3 small'>" +
				         "<div><strong>Producto:</strong> " + prod + "</div>" +
				         "<div><strong>Cantidad:</strong> " + amount + "</div>" +
				         "<div><strong>Importe a asentar:</strong> <span class='text-danger font-weight-bold'>" + total + "</span></div>" +
				         "</div>" +
				         "<div class='small text-muted'>" +
				         "• Se descargará el inventario contable en Stock.<br>" +
				         "• Se asentará la pérdida económica en los KPIs operacionales.<br>" +
				         "• El caso avanzará en trazabilidad para auditoría." +
				         "</div>",
				buttons: {
					confirm: { label: '<i class="fa-solid fa-check mr-1"></i>Sí, Autorizar', className: 'btn-success' },
					cancel: { label: 'Cancelar', className: 'btn-secondary' }
				},
				callback: function(result) {
					if (result) {
						btnApprove.disabled = true;
						Grocy.FrontendHelpers.BeginUiBusy();

						fetch(Grocy.BaseUrl + '/api/waste/' + id + '/approve', {
							method: 'POST',
							headers: { 'Accept': 'application/json' }
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							Grocy.FrontendHelpers.EndUiBusy();
							btnApprove.disabled = false;
							if (data.success) {
								toastr.success(data.message || 'Caso autorizado con éxito');
								// Remover tarjeta resuelta
								activeCard.remove();
								cards = document.querySelectorAll('.daily-request-card');
								recalculateKpis();
								if (cards.length > 0) {
									selectRequest(cards[0]);
								} else {
									document.getElementById('requestsListContainer').innerHTML = 
										'<div class="p-5 text-center bg-white border rounded" style="border-radius: 12px;">' +
										'<i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>' +
										'<h6 class="font-weight-bold text-dark">¡Bandeja al día!</h6>' +
										'<p class="text-muted small mb-0">No hay solicitudes de merma pendientes de autorización en este momento.</p>' +
										'</div>';
								}
							} else {
								toastr.error(data.error_message || 'Error al aprobar la merma');
							}
						})
						.catch(function(err) {
							Grocy.FrontendHelpers.EndUiBusy();
							btnApprove.disabled = false;
							toastr.error('Error de conexión: ' + err);
						});
					}
				}
			});
		});
	}

	// 4. Acción de Rechazar
	var btnReject = document.getElementById('btnRejectAction');
	if (btnReject) {
		btnReject.addEventListener('click', function() {
			if (!activeCard) return;
			var id = activeCard.getAttribute('data-id');
			var caseNum = activeCard.getAttribute('data-case');
			var reason = (reasonInput ? reasonInput.value : '').trim();

			if (reason.length < 5) {
				toastr.warning('Debes escribir un motivo de rechazo en el campo de texto (mínimo 5 caracteres)');
				if (reasonInput) reasonInput.focus();
				return;
			}

			bootbox.confirm({
				title: "<i class='fa-solid fa-triangle-exclamation text-danger mr-2'></i>Confirmar Rechazo de Solicitud",
				message: "¿Rechazar formalmente el caso <strong>" + caseNum + "</strong>?<br><br><strong>Motivo indicado:</strong> " + reason + "<br><br><span class='small text-muted'>El caso quedará rechazado sin afectar existencias de inventario.</span>",
				buttons: {
					confirm: { label: '<i class="fa-solid fa-times mr-1"></i>Rechazar Solicitud', className: 'btn-danger' },
					cancel: { label: 'Cancelar', className: 'btn-secondary' }
				},
				callback: function(result) {
					if (result) {
						btnReject.disabled = true;
						Grocy.FrontendHelpers.BeginUiBusy();

						fetch(Grocy.BaseUrl + '/api/waste/' + id + '/reject', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'Accept': 'application/json'
							},
							body: JSON.stringify({ rejection_reason: reason })
						})
						.then(function(res) { return res.json(); })
						.then(function(data) {
							Grocy.FrontendHelpers.EndUiBusy();
							btnReject.disabled = false;
							if (data.success) {
								toastr.info(data.message || 'Caso rechazado correctamente');
								if (reasonInput) reasonInput.value = '';
								activeCard.remove();
								cards = document.querySelectorAll('.daily-request-card');
								recalculateKpis();
								if (cards.length > 0) {
									selectRequest(cards[0]);
								} else {
									document.getElementById('requestsListContainer').innerHTML = 
										'<div class="p-5 text-center bg-white border rounded" style="border-radius: 12px;">' +
										'<i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>' +
										'<h6 class="font-weight-bold text-dark">¡Bandeja al día!</h6>' +
										'<p class="text-muted small mb-0">No hay solicitudes de merma pendientes de autorización en este momento.</p>' +
										'</div>';
								}
							} else {
								toastr.error(data.error_message || 'Error al rechazar la merma');
							}
						})
						.catch(function(err) {
							Grocy.FrontendHelpers.EndUiBusy();
							btnReject.disabled = false;
							toastr.error('Error de conexión: ' + err);
						});
					}
				}
			});
		});
	}

	// 5. Filtros dinámicos
	var searchInput = document.getElementById('searchApprovalInput');
	var filterBranch = document.getElementById('filterBranch');
	var filterReason = document.getElementById('filterReason');
	var filterCat = document.getElementById('filterCategory');
	var btnReset = document.getElementById('btnResetFilters');

	function applyFilters() {
		var searchVal = (searchInput ? searchInput.value : '').toLowerCase().trim();
		var branchVal = (filterBranch ? filterBranch.value : '').toLowerCase().trim();
		var reasonVal = (filterReason ? filterReason.value : '').toLowerCase().trim();
		var catVal = (filterCat ? filterCat.value : '').toLowerCase().trim();

		var visibleCount = 0;
		var firstVisible = null;

		document.querySelectorAll('.daily-request-card').forEach(function(card) {
			var caseNum = (card.getAttribute('data-case') || '').toLowerCase();
			var prod = (card.getAttribute('data-product') || '').toLowerCase();
			var sku = (card.getAttribute('data-sku') || '').toLowerCase();
			var batch = (card.getAttribute('data-batch') || '').toLowerCase();
			var branch = (card.getAttribute('data-branch') || '').toLowerCase();
			var reason = (card.getAttribute('data-reason-name') || '').toLowerCase();
			var reasonCode = (card.getAttribute('data-reason-code') || '').toLowerCase();
			var cat = (card.getAttribute('data-category') || '').toLowerCase();

			var matchSearch = !searchVal || caseNum.indexOf(searchVal) !== -1 || prod.indexOf(searchVal) !== -1 || sku.indexOf(searchVal) !== -1 || batch.indexOf(searchVal) !== -1;
			var matchBranch = !branchVal || branch.indexOf(branchVal) !== -1;
			var matchReason = !reasonVal || reason.indexOf(reasonVal) !== -1 || reasonCode.indexOf(reasonVal) !== -1;
			var matchCat = !catVal || cat.indexOf(catVal) !== -1;

			if (matchSearch && matchBranch && matchReason && matchCat) {
				card.style.display = '';
				visibleCount++;
				if (!firstVisible) firstVisible = card;
			} else {
				card.style.display = 'none';
			}
		});

		var cntEl = document.getElementById('visibleCardsCount');
		if (cntEl) cntEl.textContent = visibleCount;

		if (firstVisible) {
			selectRequest(firstVisible);
		}
	}

	if (searchInput) searchInput.addEventListener('input', applyFilters);
	if (filterBranch) filterBranch.addEventListener('change', applyFilters);
	if (filterReason) filterReason.addEventListener('change', applyFilters);
	if (filterCat) filterCat.addEventListener('change', applyFilters);

	if (btnReset) {
		btnReset.addEventListener('click', function() {
			if (searchInput) searchInput.value = '';
			if (filterBranch) filterBranch.value = '';
			if (filterReason) filterReason.value = '';
			if (filterCat) filterCat.value = '';
			applyFilters();
		});
	}

	// 6. Recalcular KPIs superiores tras cambios
	function recalculateKpis() {
		var allCards = document.querySelectorAll('.daily-request-card');
		var count = allCards.length;
		var sum = 0;
		var withEv = 0;
		var withoutEv = 0;

		allCards.forEach(function(c) {
			var cost = parseFloat((c.getAttribute('data-total-cost') || '0').replace(/,/g, ''));
			sum += isNaN(cost) ? 0 : cost;
			if (c.getAttribute('data-has-evidence') === '1') {
				withEv++;
			} else {
				withoutEv++;
			}
		});

		var elCount = document.getElementById('kpiPendingCount');
		var elCost = document.getElementById('kpiTotalCost');
		var elWith = document.getElementById('kpiWithEvidence');
		var elWithout = document.getElementById('kpiWithoutEvidence');

		if (elCount) elCount.textContent = count;
		if (elCost) elCost.textContent = '$' + sum.toFixed(2);
		if (elWith) elWith.textContent = withEv;
		if (elWithout) elWithout.textContent = withoutEv;
		var elVis = document.getElementById('visibleCardsCount');
		if (elVis) elVis.textContent = count;
	}
});

// Función de exportación de lista de aprobaciones a CSV
function exportApprovalsCsv() {
	var cards = document.querySelectorAll('.daily-request-card');
	var csv = "FOLIO,PRODUCTO,SKU,SUCURSAL,FECHA_REGISTRO,REPORTADO_POR,CAUSAL,CATEGORIA,CANTIDAD,UNIDAD,COSTO_UNITARIO,IMPORTE_TOTAL,LOTE,VENCIMIENTO,TIENE_EVIDENCIA,NOTAS\n";

	cards.forEach(function(card) {
		if (card.style.display === 'none') return;
		var folio = card.getAttribute('data-case');
		var prod = card.getAttribute('data-product');
		var sku = card.getAttribute('data-sku');
		var branch = card.getAttribute('data-branch');
		var date = card.getAttribute('data-date');
		var rep = card.getAttribute('data-reporter');
		var causal = card.getAttribute('data-reason-code') + ' ' + card.getAttribute('data-reason-name');
		var cat = card.getAttribute('data-category');
		var amount = card.getAttribute('data-amount');
		var qu = card.getAttribute('data-qu');
		var unitCost = card.getAttribute('data-unit-cost');
		var totalCost = card.getAttribute('data-total-cost');
		var batch = card.getAttribute('data-batch') || '';
		var bestBefore = card.getAttribute('data-best-before') || '';
		var hasEv = card.getAttribute('data-has-evidence') === '1' ? 'SI' : 'NO';
		var notes = (card.getAttribute('data-notes') || '').replace(/"/g, '""');

		csv += '"' + folio + '","' + prod + '","' + sku + '","' + branch + '","' + date + '","' + rep + '","' + causal + '","' + cat + '","' + amount + '","' + qu + '","' + unitCost + '","' + totalCost + '","' + batch + '","' + bestBefore + '","' + hasEv + '","' + notes + '"\n';
	});

	var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	var link = document.createElement("a");
	var url = URL.createObjectURL(blob);
	link.setAttribute("href", url);
	link.setAttribute("download", "aprobaciones_merma_" + new Date().toISOString().slice(0,10) + ".csv");
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	if (typeof toastr !== 'undefined') {
		toastr.success('Exportación de aprobaciones generada');
	}
}
</script>
@stop
