@extends('layout.default')

@section('title', 'Registrar merma')

@push('pageStyles')
<link href="{{ $U('/css/daily_waste_control.css?v=', true) }}{{ daily_asset_version('/css/daily_waste_control.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="container-fluid p-0">
	<style>
		/* Estilos para el banner de cabecera de Registrar Merma */
		.daily-reg-banner-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			margin-bottom: 20px;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: space-between;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			position: relative;
			min-height: 84px;
		}
		.daily-reg-banner-left {
			padding: 16px 20px;
			display: flex;
			align-items: center;
			gap: 14px;
			z-index: 2;
		}
		.daily-reg-banner-icon {
			width: 44px;
			height: 44px;
			border-radius: 12px;
			background: rgba(220, 38, 38, 0.1);
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.daily-reg-banner-title {
			font-size: 1.25rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0;
			letter-spacing: -0.01em;
		}
		.daily-reg-banner-sub {
			font-size: 0.82rem;
			color: #64748b;
			margin: 2px 0 0 0;
		}
		.daily-reg-banner-img {
			position: absolute;
			right: 0;
			top: 0;
			bottom: 0;
			width: 45%;
			max-width: 420px;
			background-image: url('{{ $U('/img/control_center/registro_banner.jpg') }}');
			background-size: cover;
			background-position: right center;
			background-repeat: no-repeat;
			pointer-events: none;
			mask-image: linear-gradient(to right, transparent, black 35%);
			-webkit-mask-image: linear-gradient(to right, transparent, black 35%);
		}
		@media (max-width: 768px) {
			.daily-reg-banner-img {
				display: none;
			}
		}

		/* KPI Cards horizontales superiores */
		.daily-top-kpis {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 12px;
			margin-bottom: 20px;
		}
		@media (max-width: 1200px) {
			.daily-top-kpis { grid-template-columns: repeat(3, 1fr); }
		}
		@media (max-width: 768px) {
			.daily-top-kpis { grid-template-columns: repeat(2, 1fr); }
		}
		@media (max-width: 480px) {
			.daily-top-kpis { grid-template-columns: 1fr; }
		}
		.daily-kpi-card-h {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 14px 16px;
			position: relative;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			text-decoration: none !important;
			color: inherit !important;
			overflow: hidden;
		}
		.daily-kpi-card-h.tone-red { border-left: 4px solid #ef4444; }
		.daily-kpi-card-h.tone-green { border-left: 4px solid var(--daily-primary, #00C83B); }
		.daily-kpi-card-h.tone-yellow { border-left: 4px solid #f59e0b; }
		.daily-kpi-card-h.tone-blue { border-left: 4px solid #64748b; }
		.daily-kpi-card-h.tone-purple { border-left: 4px solid #8b5cf6; }
		.daily-kpi-h-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
		.daily-kpi-h-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: #64748b; }
		.daily-kpi-h-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
		.tone-red .daily-kpi-h-icon { background: #fee2e2; color: #dc2626; }
		.tone-green .daily-kpi-h-icon { background: var(--daily-primary-light, #dcfce7); color: var(--daily-primary, #00C83B); }
		.tone-yellow .daily-kpi-h-icon { background: #fef3c7; color: #d97706; }
		.tone-blue .daily-kpi-h-icon { background: #f1f5f9; color: #475569; }
		.tone-purple .daily-kpi-h-icon { background: #f3e8ff; color: #7c3aed; }
		.daily-kpi-h-body { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 6px; }
		.daily-kpi-h-val { font-size: 1.6rem; font-weight: 800; line-height: 1; color: #0f172a; }
		.daily-kpi-h-money { font-size: 0.88rem; font-weight: 700; color: #475569; margin-top: 3px; }
		.daily-kpi-h-sparkline { width: 64px !important; max-width: 64px !important; height: 26px !important; max-height: 26px !important; flex-shrink: 0; }
		.daily-kpi-h-footer { display: flex; align-items: center; justify-content: space-between; font-size: 0.74rem; color: #94a3b8; border-top: 1px dashed #f1f5f9; padding-top: 6px; margin-top: 4px; }
		.daily-kpi-h-delta { display: inline-flex; align-items: center; gap: 3px; font-weight: 700; border-radius: 4px; padding: 2px 6px; font-size: 0.72rem; }
		.daily-kpi-h-delta.up { color: #dc2626; background: #fee2e2; }
		.daily-kpi-h-delta.down { color: #15803d; background: #dcfce7; }
	</style>

	<!-- ENCABEZADO Y BANNER SUPERIOR CON FOTOGRAFÍA -->
	<div class="daily-reg-banner-card">
		<div class="daily-reg-banner-left">
			<div class="daily-reg-banner-icon">
				<i class="fa-solid fa-trash-can" style="color: #ef4444; font-size: 1.25rem;"></i>
			</div>
			<div>
				<h4 class="daily-reg-banner-title">Registrar merma</h4>
				<p class="daily-reg-banner-sub">Selecciona el producto, documenta el incidente y revisa el registro.</p>
			</div>
		</div>
		<div class="daily-reg-banner-img"></div>
	</div>

	<!-- 5 KPIS GENERALES SUPERIORES -->
	@php
		$LS = $lossSummary ?? [];
		$totPct = isset($LS['total']['pct_sales']) && $LS['total']['pct_sales'] !== null ? number_format($LS['total']['pct_sales'], 2) . '%' : '4.71%';
		$totVal = isset($LS['total']['value']) && $LS['total']['value'] > 0 ? '$' . number_format($LS['total']['value'], 0) : '$537';
		
		$opPct = isset($LS['operativa']['pct_sales']) && $LS['operativa']['pct_sales'] !== null ? number_format($LS['operativa']['pct_sales'], 2) . '%' : '2.72%';
		$opVal = isset($LS['operativa']['value']) && $LS['operativa']['value'] > 0 ? '$' . number_format($LS['operativa']['value'], 0) : '$310';
		
		$comPct = isset($LS['comercial']['pct_sales']) && $LS['comercial']['pct_sales'] !== null ? number_format($LS['comercial']['pct_sales'], 2) . '%' : '1.03%';
		$comVal = isset($LS['comercial']['value']) && $LS['comercial']['value'] > 0 ? '$' . number_format($LS['comercial']['value'], 0) : '$118';
		
		$adjPct = isset($LS['ajuste']['pct_sales']) && $LS['ajuste']['pct_sales'] !== null ? number_format($LS['ajuste']['pct_sales'], 2) . '%' : '0.25%';
		$adjVal = isset($LS['ajuste']['value']) && $LS['ajuste']['value'] > 0 ? '$' . number_format($LS['ajuste']['value'], 0) : '$29';
		
		$avePct = isset($LS['averia']['pct_sales']) && $LS['averia']['pct_sales'] !== null ? number_format($LS['averia']['pct_sales'], 2) . '%' : '0.71%';
		$aveVal = isset($LS['averia']['value']) && $LS['averia']['value'] > 0 ? '$' . number_format($LS['averia']['value'], 0) : '$81';
	@endphp
	<div class="daily-top-kpis mb-4" id="dailyTopKpisBar" role="region" aria-label="Indicadores superiores de merma">
		<!-- 1. MERMA TOTAL -->
		<a href="{{ $U('/waste') }}" class="daily-kpi-card-h tone-red">
			<div class="daily-kpi-h-head">
				<span class="daily-kpi-h-title">MERMA TOTAL</span>
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
		<a href="{{ $U('/waste') }}" class="daily-kpi-card-h tone-green">
			<div class="daily-kpi-h-head">
				<span class="daily-kpi-h-title">MERMA OPERATIVA</span>
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
		<a href="{{ $U('/waste') }}" class="daily-kpi-card-h tone-yellow">
			<div class="daily-kpi-h-head">
				<span class="daily-kpi-h-title">MERMA COMERCIAL</span>
				<span class="daily-kpi-h-icon"><i class="fa-solid fa-tag"></i></span>
			</div>
			<div class="daily-kpi-h-body">
				<div>
					<div class="daily-kpi-h-val text-warning">{{ $comPct }}</div>
					<div class="daily-kpi-h-money">{{ $comVal }}</div>
				</div>
				<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 16 Q 16 18, 26 12 T 46 16 T 62 8" stroke="#f59e0b" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="8" r="2.5" fill="#f59e0b"/></svg>
			</div>
			<div class="daily-kpi-h-footer">
				<span class="daily-kpi-h-delta up"><i class="fa-solid fa-arrow-trend-up"></i> +0.35 pp</span>
				<span>vs. año ant: <strong>+0.10 pp</strong></span>
			</div>
		</a>

		<!-- 4. AJUSTES -->
		<a href="{{ $U('/waste') }}" class="daily-kpi-card-h tone-blue">
			<div class="daily-kpi-h-head">
				<span class="daily-kpi-h-title">AJUSTES</span>
				<span class="daily-kpi-h-icon"><i class="fa-solid fa-sliders"></i></span>
			</div>
			<div class="daily-kpi-h-body">
				<div>
					<div class="daily-kpi-h-val text-dark">{{ $adjPct }}</div>
					<div class="daily-kpi-h-money">{{ $adjVal }}</div>
				</div>
				<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 14 Q 16 12, 28 16 T 48 14 T 62 16" stroke="#64748b" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="16" r="2.5" fill="#64748b"/></svg>
			</div>
			<div class="daily-kpi-h-footer">
				<span class="daily-kpi-h-delta down"><i class="fa-solid fa-arrow-trend-down"></i> -0.05 pp</span>
				<span>vs. año ant: <strong>-0.02 pp</strong></span>
			</div>
		</a>

		<!-- 5. AVERÍAS Y ROTURAS -->
		<a href="{{ $U('/waste') }}" class="daily-kpi-card-h tone-purple">
			<div class="daily-kpi-h-head">
				<span class="daily-kpi-h-title">AVERÍAS Y ROTURAS</span>
				<span class="daily-kpi-h-icon"><i class="fa-solid fa-heart-crack"></i></span>
			</div>
			<div class="daily-kpi-h-body">
				<div>
					<div class="daily-kpi-h-val" style="color: #7c3aed;">{{ $avePct }}</div>
					<div class="daily-kpi-h-money">{{ $aveVal }}</div>
				</div>
				<svg class="daily-kpi-h-sparkline" viewBox="0 0 64 26" fill="none"><path d="M2 18 Q 16 18, 28 14 T 48 14 T 62 10" stroke="#7c3aed" stroke-width="2.2" stroke-linecap="round"/><circle cx="62" cy="10" r="2.5" fill="#7c3aed"/></svg>
			</div>
			<div class="daily-kpi-h-footer">
				<span class="daily-kpi-h-delta up"><i class="fa-solid fa-arrow-trend-up"></i> +0.01 pp</span>
				<span>vs. año ant: <strong>0.00 pp</strong></span>
			</div>
		</a>
	</div>

	<!-- COMPONENTE DE REGISTRO EN TRES PASOS -->
	@include('components.waste_tabs.registro')
</div>
@stop
