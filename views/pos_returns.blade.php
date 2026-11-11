@extends('layout.default')

@section('title', 'Devoluciones del POS | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_pos.css?v=', true) }}{{ daily_asset_version('/css/daily_pos.css') }}" rel="stylesheet">
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

<div class="pos-ret-container" data-testid="pos-returns-page">
{{--
	DAILY — Devoluciones comerciales del POS: ticket → elegibilidad → productos, cantidades y motivo → autorización →
	reembolso al medio original, nota de crédito o cambio → comprobante → inspección física en /returns.
	Lógica en public/js/daily_pos_returns.js; reglas en services/PosReturnService.php. Proveedores, despachos, almacenes,
	conteos, investigaciones, mermas y reposición viven en sus propios tableros.
--}}
	@php
		$pct = fn($v) => $v === null ? 'Sin información' : number_format($v, 2) . ' %';
		$resNames = ['refund' => 'Reembolso', 'credit_note' => 'Nota de crédito', 'exchange' => 'Cambio'];
		$methodNames = \Grocy\Services\PosService::METHODS;
		$boot = ['branchId' => intval($selectedBranchId), 'settings' => $posSettings, 'reasons' => $reasons, 'conditions' => $conditions, 'methods' => $methodNames, 'registers' => $registers];
	@endphp
	<div class="pos-ret-hero">
		<div class="pos-ret-hero-left">
			<div class="pos-ret-hero-icon"><i class="fa-solid fa-cash-register"></i></div>
			<div>
				<h1 class="pos-ret-title">Devoluciones del POS</h1>
				<p class="pos-ret-desc">Devoluciones comerciales, cambios y reembolsos a clientes con ticket.</p>
			</div>
		</div>
		<div class="pos-ret-hero-actions">
			<form method="get" class="d-inline-flex gap-2 align-items-center mb-0">
				<select name="branch_id" class="pos-ret-select" onchange="this.form.submit()" aria-label="Sucursal">
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>
			</form>
			<a href="{{ $U('/pos') }}?branch_id={{ $selectedBranchId }}" class="pos-ret-btn-outline"><i class="fa-solid fa-cash-register text-success"></i><span>Punto de venta</span></a>
			<a href="{{ $U('/returns') }}" class="pos-ret-btn-outline" title="La inspección física de lo devuelto se gestiona en Devoluciones e inspección"><i class="fa-solid fa-magnifying-glass-arrow-right text-primary"></i><span>Inspección de devoluciones</span></a>
		</div>
	</div>

	<div class="pos-ret-lifecycle-card">
		<div class="pos-ret-stepper-wrap">
			<div class="pos-ret-stepper" data-testid="pos-returns-stepper">
				@foreach([['Ticket', 'Buscar la venta'], ['Elegibilidad', 'Plazo y saldo'], ['Productos', 'Cantidad y motivo'], ['Autorización', 'Por importe y rol'], ['Reembolso', 'Medio original o nota'], ['Comprobante', 'Para el cliente'], ['Inspección', 'Módulo de devoluciones']] as $i => $st)
					<div class="pos-ret-step" data-step="{{ $i + 1 }}">
						<div class="pos-ret-step-num">{{ $i + 1 }}</div>
						<div class="d-flex flex-column" style="line-height: 1.15;"><strong style="font-size: 0.8rem;">{{ $st[0] }}</strong><span style="font-size: 0.68rem; color: #64748b;">{{ $st[1] }}</span></div>
					</div>
					@if(!$loop->last)<div class="pos-ret-step-divider"></div>@endif
				@endforeach
			</div>
		</div>
	</div>

	<div class="pos-ret-kpis-grid" data-testid="pos-returns-kpis">
		@foreach([
			['Devuelto (30 días)', $money($kpis['amount']), $kpis['returns'] . ' devoluciones', 'fa-hand-holding-dollar', '#fef2f2', 'text-danger', 'kpi-total-devuelto'],
			['Tasa de devolución', $pct($kpis['rate_amount_pct']), 'devuelto ÷ vendido (' . $money($kpis['sales_amount']) . ')', 'fa-percent', '#eff6ff', 'text-primary', 'kpi-tasa-importe'],
			['Tasa por unidades', $pct($kpis['rate_units_pct']), number_format($kpis['units'], 0) . ' de ' . number_format($kpis['sold_units'], 0) . ' u.', 'fa-boxes-stacked', '#f0fdf4', 'text-success', 'kpi-tasa-unidades'],
			['Motivo principal', $kpis['by_reason'][0]['reason'] ?? 'Sin información', isset($kpis['by_reason'][0]) ? $kpis['by_reason'][0]['n'] . ' líneas' : '—', 'fa-comment-dots', '#fffbeb', 'text-warning', 'kpi-motivo'],
			['Con autorización', number_format($kpis['authorized']), 'devoluciones sobre ' . $money($posSettings['return_auth_threshold']), 'fa-user-shield', '#f5f3ff', 'text-primary', 'kpi-autorizadas'],
			['Pendientes de inspección', number_format($kpis['pending_inspection']), 'líneas en el módulo de devoluciones', 'fa-magnifying-glass', '#ecfeff', 'text-info', 'kpi-inspeccion'],
			['Notas de crédito vigentes', $money(array_sum(array_map(fn($n) => $n['status'] === 'active' ? floatval($n['balance']) : 0, $creditNotes))), count(array_filter($creditNotes, fn($n) => $n['status'] === 'active')) . ' con saldo', 'fa-ticket', '#f0fdf4', 'text-success', 'kpi-notas']
		] as $k)
		<div class="pos-ret-kpi-card" data-testid="{{ $k[6] }}">
			<div><div class="pos-ret-kpi-label">{{ $k[0] }}</div><div class="pos-ret-kpi-val {{ $k[5] }}">{{ $k[1] }}</div><div class="pos-ret-kpi-sub">{{ $k[2] }}</div></div>
			<div class="pos-ret-kpi-icon-box {{ $k[5] }}" style="background: {{ $k[4] }};"><i class="fa-solid {{ $k[3] }}"></i></div>
		</div>
		@endforeach
	</div>

	<div class="alert alert-light border d-flex align-items-center p-3 mb-4 rounded" style="border-left: 4px solid #059669 !important; background: #ffffff;">
		<i class="fa-solid fa-shield-halved text-success fa-2x mr-3"></i>
		<div class="small text-muted">
			<strong class="text-dark">Política de devoluciones:</strong>
			hasta {{ $posSettings['return_days'] }} días con ticket ·
			nunca más unidades ni más dinero que lo pagado ·
			reembolso al medio con que se pagó o en nota de crédito (vigencia {{ $posSettings['credit_note_days'] }} días) ·
			desde {{ $money($posSettings['return_auth_threshold']) }} un cajero necesita autorización de supervisor ·
			lo devuelto no vuelve al stock vendible: queda en inspección.
		</div>
	</div>

	<ul class="nav pos-ret-nav" role="tablist" data-testid="pos-returns-main-tabs">
		<li class="nav-item"><a class="pos-ret-nav-link active" id="tab-nueva-devolucion-tab" data-toggle="pill" href="#tab-nueva-devolucion" role="tab"><i class="fa-solid fa-cash-register"></i> Nueva devolución</a></li>
		<li class="nav-item"><a class="pos-ret-nav-link" id="tab-historial-tab" data-toggle="pill" href="#tab-historial" role="tab"><i class="fa-solid fa-clock-rotate-left"></i> Historial</a></li>
		<li class="nav-item"><a class="pos-ret-nav-link" id="tab-indicadores-tab" data-toggle="pill" href="#tab-indicadores" role="tab"><i class="fa-solid fa-chart-pie"></i> Indicadores</a></li>
		<li class="nav-item"><a class="pos-ret-nav-link" id="tab-notas-tab" data-toggle="pill" href="#tab-notas" role="tab"><i class="fa-solid fa-ticket"></i> Notas de crédito</a></li>
	</ul>

	<div class="tab-content">
		<div class="tab-pane fade show active" id="tab-nueva-devolucion" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title"><i class="fa-solid fa-magnifying-glass text-success"></i> Ticket de la venta</h3>
				<p class="pos-ret-section-sub">Número de ticket, referencia de la transacción o código de autorización del pago.</p>
				<form class="pos-search-box" id="ret-search-form">
					<input type="text" class="pos-search-input" id="posSearchQuery" placeholder="Ej. VTA-20261110-1A2B3C o AUT-1234" autocomplete="off" aria-label="Ticket, transacción o referencia">
					<button type="submit" class="btn btn-success font-weight-bold px-4" id="btnSearchSale"><i class="fa-solid fa-search mr-1"></i> Buscar venta</button>
				</form>
				@if($recentSales)
				<div class="d-flex align-items-center flex-wrap gap-2 mb-3">
					<span class="small font-weight-bold text-muted mr-1">Últimos tickets:</span>
					@foreach($recentSales as $rs)
						<button type="button" class="pos-ticket-badge" data-ticket="{{ $rs['sale_number'] }}">{{ $rs['sale_number'] }} · {{ $money($rs['total_amount']) }}</button>
					@endforeach
				</div>
				@endif
				<div id="ret-sale" data-testid="ret-sale"></div>
				<div id="saleEmptyPrompt" class="text-center py-5 text-muted">
					<i class="fa-solid fa-receipt fa-3x mb-3 text-muted" style="opacity: 0.35;"></i>
					<h5>Busque el ticket de la venta</h5>
					<p class="small mb-0">Verá qué se puede devolver, cuánto y a qué medio de pago.</p>
				</div>
			</div>
		</div>

		<div class="tab-pane fade" id="tab-historial" role="tabpanel">
			<div class="pos-ret-card">
				<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:8px;">
					<h3 class="pos-ret-section-title mb-0"><i class="fa-solid fa-list-check text-success"></i> Historial de devoluciones comerciales</h3>
					<div class="d-flex flex-wrap" style="gap:6px;">
						<input type="date" class="pos-ret-select" id="ret-h-from" value="{{ $kpiFrom }}" aria-label="Desde">
						<input type="date" class="pos-ret-select" id="ret-h-to" value="{{ date('Y-m-d') }}" aria-label="Hasta">
						<input type="search" class="pos-ret-select" id="ret-h-q" placeholder="Devolución, ticket o cliente" aria-label="Buscar">
					</div>
				</div>
				<div class="table-responsive"><table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;" data-testid="ret-history">
					<thead class="thead-light"><tr><th>Fecha</th><th>Devolución</th><th>Ticket</th><th>Cliente</th><th>Resolución</th><th>Medio</th><th class="text-right">Unid.</th><th class="text-right">Importe</th><th>Autorizó</th><th>Inspección</th><th></th></tr></thead>
					<tbody id="ret-h-body"></tbody>
				</table></div>
			</div>
		</div>

		<div class="tab-pane fade" id="tab-indicadores" role="tabpanel">
			<div class="row">
				<div class="col-lg-6 mb-3"><div class="pos-ret-card h-100">
					<h3 class="pos-ret-section-title"><i class="fa-solid fa-comment-dots text-warning"></i> Motivos (30 días)</h3>
					@if(!$kpis['by_reason'])<p class="text-muted small mb-0">Sin devoluciones en el período.</p>@else
					<table class="table table-sm mb-0"><thead class="thead-light"><tr><th>Motivo</th><th class="text-right">Líneas</th><th class="text-right">Importe</th></tr></thead><tbody>
					@foreach($kpis['by_reason'] as $r)<tr><td>{{ $r['reason'] }}</td><td class="text-right">{{ $r['n'] }}</td><td class="text-right">{{ $money($r['amount']) }}</td></tr>@endforeach
					</tbody></table>@endif
				</div></div>
				<div class="col-lg-6 mb-3"><div class="pos-ret-card h-100">
					<h3 class="pos-ret-section-title"><i class="fa-solid fa-money-bill-transfer text-success"></i> Resolución y medio de reembolso</h3>
					@if(!$kpis['by_resolution'])<p class="text-muted small mb-0">Sin devoluciones en el período.</p>@else
					<table class="table table-sm mb-2"><thead class="thead-light"><tr><th>Resolución</th><th class="text-right">Devoluciones</th><th class="text-right">Importe</th></tr></thead><tbody>
					@foreach($kpis['by_resolution'] as $r)<tr><td>{{ $resNames[$r['resolution']] ?? $r['resolution'] }}</td><td class="text-right">{{ $r['n'] }}</td><td class="text-right">{{ $money($r['amount']) }}</td></tr>@endforeach
					</tbody></table>
					<table class="table table-sm mb-0"><thead class="thead-light"><tr><th>Medio</th><th class="text-right">Importe</th></tr></thead><tbody>
					@foreach($kpis['by_method'] as $r)<tr><td>{{ $methodNames[$r['method']] ?? $r['method'] }}</td><td class="text-right">{{ $money($r['amount']) }}</td></tr>@endforeach
					</tbody></table>@endif
				</div></div>
				<div class="col-12 mb-3"><div class="pos-ret-card">
					<h3 class="pos-ret-section-title"><i class="fa-solid fa-box-open text-danger"></i> Productos más devueltos (30 días)</h3>
					@if(!$kpis['top_products'])<p class="text-muted small mb-0">Sin devoluciones en el período.</p>@else
					<table class="table table-sm mb-0"><thead class="thead-light"><tr><th>Producto</th><th>SKU</th><th class="text-right">Unidades</th><th class="text-right">Importe</th></tr></thead><tbody>
					@foreach($kpis['top_products'] as $r)<tr><td>{{ $r['name'] }}</td><td>{{ $r['sku'] }}</td><td class="text-right">{{ number_format($r['units'], 0) }}</td><td class="text-right">{{ $money($r['amount']) }}</td></tr>@endforeach
					</tbody></table>@endif
				</div></div>
			</div>
		</div>

		<div class="tab-pane fade" id="tab-notas" role="tabpanel">
			<div class="pos-ret-card">
				<h3 class="pos-ret-section-title"><i class="fa-solid fa-ticket text-success"></i> Notas de crédito</h3>
				<p class="pos-ret-section-sub">Se canjean en el punto de venta como medio de pago «Nota de crédito» con su código.</p>
				@if(!$creditNotes)<p class="text-muted small mb-0">Aún no se han emitido notas de crédito en esta sucursal.</p>@else
				<div class="table-responsive"><table class="table table-sm mb-0" style="font-size: 0.85rem;">
					<thead class="thead-light"><tr><th>Código</th><th>Emitida</th><th>Cliente</th><th>Devolución</th><th class="text-right">Importe</th><th class="text-right">Saldo</th><th>Vence</th><th>Estado</th></tr></thead>
					<tbody>
					@foreach($creditNotes as $n)
						<tr><td><strong>{{ $n['code'] }}</strong></td><td>{{ date('d/m/Y', strtotime($n['created_at'])) }}</td><td>{{ $n['customer'] ?: 'Público general' }}</td><td>{{ $n['return_code'] }}</td>
							<td class="text-right">{{ $money($n['amount']) }}</td><td class="text-right">{{ $money($n['balance']) }}</td><td>{{ $n['expires_at'] ? date('d/m/Y', strtotime($n['expires_at'])) : '—' }}</td>
							<td>{{ $n['status'] === 'active' ? ($n['expires_at'] && $n['expires_at'] < date('Y-m-d') ? 'Vencida' : 'Vigente') : ($n['status'] === 'used' ? 'Usada' : 'Anulada') }}</td></tr>
					@endforeach
					</tbody>
				</table></div>@endif
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-pos-auth" tabindex="-1" role="dialog" aria-labelledby="ret-auth-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<form class="modal-content" id="form-pos-auth" autocomplete="off">
			<div class="modal-header"><h5 class="modal-title" id="ret-auth-title">Autorización de supervisor</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
			<div class="modal-body">
				<p class="small text-muted" id="pos-auth-desc"></p>
				<div class="form-group"><label for="auth-reason">Motivo</label><input type="text" class="form-control" id="auth-reason" maxlength="200" required></div>
				<div class="form-group"><label for="auth-user">Usuario supervisor</label><input type="text" class="form-control" id="auth-user" autocomplete="off" required></div>
				<div class="form-group mb-0"><label for="auth-pass">Clave</label><input type="password" class="form-control" id="auth-pass" autocomplete="new-password" required></div>
			</div>
			<div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Autorizar</button></div>
		</form>
	</div>
</div>

<div class="modal fade" id="modalPosReturnVoucher" tabindex="-1" role="dialog" aria-labelledby="ret-voucher-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header"><h5 class="modal-title" id="ret-voucher-title">Comprobante de devolución</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
			<div class="modal-body"><div class="pos-receipt" id="ret-voucher" data-testid="ret-voucher"></div></div>
			<div class="modal-footer"><button type="button" class="btn btn-success" id="btn-voucher-print"><i class="fa-solid fa-print mr-1"></i> Imprimir</button></div>
		</div>
	</div>
</div>

<script type="application/json" id="ret-boot">{!! json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@stop

@push('pageScripts')
<script src="{{ $U('/js/daily_pos_returns.js?v=', true) }}{{ daily_asset_version('/js/daily_pos_returns.js') }}"></script>
@endpush
