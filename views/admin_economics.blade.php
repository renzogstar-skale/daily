@extends('layout.default')

@section('title', 'Economía de DAILY · Centro de Costos y Rentabilidad SaaS')

@push('pageStyles')
<link href="{{ $U('/css/daily_design_system.css?v=', true) }}{{ daily_asset_version('/css/daily_design_system.css') }}" rel="stylesheet">
<style>
.econ-metric-box {
	background: var(--daily-surface);
	border: 1px solid var(--daily-border);
	border-radius: var(--daily-radius-md);
	padding: 1.25rem;
	box-shadow: var(--daily-shadow-card);
}
.econ-slider {
	width: 100%;
	height: 6px;
	border-radius: 3px;
	background: var(--daily-surface-subtle);
	outline: none;
	accent-color: var(--daily-green-700);
}
.econ-roi-pill {
	background: var(--daily-green-50);
	border: 1px solid var(--daily-green-200);
	border-radius: var(--daily-radius-md);
	padding: 1rem;
	color: var(--daily-green-700);
}
</style>
@endpush

@section('content')
<div class="daily-container">

	<!-- CABECERA PRINCIPAL -->
	<header class="daily-header">
		<div class="daily-header-title-group daily-head">
			<div class="dairy-page-icon is-primary"><i class="fa-solid fa-coins"></i></div>
			<h1>Economía de DAILY</h1>
			<p class="daily-header-subtitle">
				Gestión financiera interna SaaS · Cómputo cloud, rentabilidad unitaria, márgenes y simulador de precios
			</p>
		</div>

		<div class="daily-toolbar">
			<form method="GET" action="{{ $U('/admin/economics') }}" class="d-flex align-items-center gap-2 m-0">
				<div class="d-flex align-items-center">
					<span class="text-muted mr-2 small font-weight-bold">Período:</span>
					<input type="month" name="period" value="{{ $period }}" class="daily-input" onchange="this.form.submit()">
				</div>
			</form>

			<button type="button" class="daily-btn daily-btn-secondary" onclick="$('#modalNewExpense').fadeIn(200)">
				<i class="fa-solid fa-plus"></i> Registrar Gasto Cloud
			</button>
		</div>
	</header>

	<!-- ==================== NIVEL 1: PULSO FINANCIERO SAAS (BANNER MACRO) ==================== -->
	<section class="daily-pulse-banner" aria-label="Pulso Financiero SaaS">
		<div class="daily-pulse-status">
			<div class="daily-pulse-indicator is-{{ $summary['margin_status'] === 'optimal' ? 'good' : ($summary['margin_status'] === 'warning' ? 'warning' : 'danger') }}">
				<i class="fa-solid {{ $summary['margin_status'] === 'optimal' ? 'fa-chart-pie' : 'fa-triangle-exclamation' }}"></i>
			</div>
			<div class="daily-pulse-info">
				<div class="d-flex align-items-center gap-2 mb-1">
					<span class="daily-badge daily-badge-{{ $summary['margin_status'] === 'optimal' ? 'success' : ($summary['margin_status'] === 'warning' ? 'warning' : 'danger') }}">
						Margen Bruto: {{ $summary['gross_margin_pct'] }}%
					</span>
					<span class="text-muted" style="font-size: 11px;">Objetivo ≥ 85%</span>
				</div>
				<h3 style="font-size: 1.05rem; font-weight: 700; margin: 0; color: var(--daily-text-main);">
					{{ $summary['gross_margin_pct'] >= 85.0 ? 'Salud Financiera Óptima' : 'Atención a Costos de Cómputo' }}
				</h3>
				<p style="margin: 3px 0 0; font-size: 0.8rem; color: var(--daily-text-muted);">
					{{ $summary['active_clients'] }} clientes activos · {{ $summary['active_stores'] }} sucursales conectadas
				</p>
			</div>
		</div>

		<!-- Métrica 1: MRR & ARR -->
		<div class="daily-pulse-metric">
			<span class="daily-pulse-metric-label"><i class="fa-solid fa-repeat text-success mr-1"></i> MRR (Mensual)</span>
			<span class="daily-pulse-metric-value daily-tabular">${{ number_format($summary['mrr'], 2) }}</span>
			<span class="daily-pulse-metric-sub">
				<span>ARR: <strong class="text-dark">${{ number_format($summary['arr'], 2) }}</strong></span>
			</span>
		</div>

		<!-- Métrica 2: COGS de Infraestructura -->
		<div class="daily-pulse-metric">
			<span class="daily-pulse-metric-label"><i class="fa-solid fa-server text-secondary mr-1"></i> Costo Infra Cloud</span>
			<span class="daily-pulse-metric-value daily-tabular text-danger">${{ number_format($summary['cogs_monthly'], 2) }}</span>
			<span class="daily-pulse-metric-sub">
				<span>${{ number_format($summary['cogs_per_store'], 2) }}/sucursal · @if($summary['is_cogs_estimated']) <span class="badge badge-warning text-dark">Estimado</span> @else <span class="badge badge-success">Facturado</span> @endif</span>
			</span>
		</div>

		<!-- Métrica 3: ARPU & Ganancia Operativa -->
		<div class="daily-pulse-metric">
			<span class="daily-pulse-metric-label"><i class="fa-solid fa-hand-holding-dollar text-primary mr-1"></i> Margen / Tienda</span>
			<span class="daily-pulse-metric-value daily-tabular">${{ number_format($summary['arpu_store'], 2) }}</span>
			<span class="daily-pulse-metric-sub">
				<span>Payback: <strong class="text-dark">{{ $summary['payback_months'] }} meses</strong></span>
			</span>
		</div>
	</section>

	<!-- ==================== CONTROL DE NIVELES (SEGMENTED TABS) ==================== -->
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
		<div class="daily-segmented-tabs" role="tablist">
			<button type="button" class="daily-tab-btn active" data-tab-target="#econ-tab-overview">
				<i class="fa-solid fa-chart-pie mr-1 text-success"></i> 1. Infraestructura & COGS
			</button>
			<button type="button" class="daily-tab-btn" data-tab-target="#econ-tab-clients">
				<i class="fa-solid fa-store mr-1 text-primary"></i> 2. Rentabilidad por Cliente ({{ count($clients) }})
			</button>
			<button type="button" class="daily-tab-btn" data-tab-target="#econ-tab-simulator">
				<i class="fa-solid fa-calculator mr-1 text-warning"></i> 3. Simulador de Precios & Cotizador
			</button>
			<button type="button" class="daily-tab-btn" data-tab-target="#econ-tab-alerts">
				<i class="fa-solid fa-bell mr-1 text-danger"></i> 4. Alertas FinOps
				@if(count($alerts) > 0)
					<span class="daily-badge daily-badge-danger ml-1" style="font-size: 11px; padding: 1px 6px;">{{ count($alerts) }}</span>
				@endif
			</button>
		</div>
		<div class="text-muted small">
			<i class="fa-solid fa-lock mr-1 text-muted"></i> Acceso restringido a Administradores DAILY
		</div>
	</div>

	<!-- ==================== PESTAÑA 1: INFRAESTRUCTURA & COGS ==================== -->
	<div class="daily-tab-pane active" id="econ-tab-overview">
		<div class="row">
			<div class="col-lg-4 mb-4">
				<div class="daily-card h-100">
					<div class="daily-card-header">
						<h3 class="daily-card-title"><i class="fa-solid fa-layer-group text-primary mr-1"></i> Desglose por Servicio Cloud</h3>
						<span class="daily-badge daily-badge-neutral">${{ number_format($expenses['total'], 2) }}</span>
					</div>
					<div class="d-flex flex-column gap-3">
						@foreach($expenses['categories'] as $catKey => $cat)
						<div class="p-2 border rounded" style="background: var(--daily-surface-subtle);">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span class="font-weight-bold small"><i class="fa-solid {{ $cat['icon'] }} mr-1" style="color: {{ $cat['color'] }};"></i> {{ $cat['name'] }}</span>
								<strong class="daily-tabular">${{ number_format($cat['total'], 2) }}</strong>
							</div>
							<div class="progress" style="height: 6px;">
								<div class="progress-bar" style="width: {{ $expenses['total'] > 0 ? ($cat['total'] / $expenses['total'] * 100) : 0 }}%; background-color: {{ $cat['color'] }};"></div>
							</div>
						</div>
						@endforeach
					</div>
				</div>
			</div>

			<div class="col-lg-8 mb-4">
				<div class="daily-card h-100">
					<div class="daily-card-header">
						<h3 class="daily-card-title"><i class="fa-solid fa-file-invoice-dollar text-success mr-1"></i> Libro de Facturación y Gastos Cloud ({{ $period }})</h3>
						<button type="button" class="daily-btn daily-btn-sm daily-btn-secondary" onclick="$('#modalNewExpense').fadeIn(200)">
							<i class="fa-solid fa-plus"></i> Añadir Gasto
						</button>
					</div>
					<div class="daily-table-wrap">
						<table class="daily-table">
							<thead>
								<tr>
									<th>Proveedor</th>
									<th>Categoría</th>
									<th>Concepto / Detalle</th>
									<th>Factura / Ref</th>
									<th class="is-number">Monto USD</th>
									<th class="is-center">Tipo</th>
								</tr>
							</thead>
							<tbody>
								@forelse($expenses['items'] as $it)
								<tr>
									<td><strong>{{ $it['provider'] }}</strong></td>
									<td><span class="badge badge-light border">{{ strtoupper($it['category']) }}</span></td>
									<td>{{ $it['description'] }}</td>
									<td class="text-muted small">{{ $it['invoice_ref'] ?: '—' }}</td>
									<td class="is-number font-weight-bold">${{ number_format($it['amount_usd'], 2) }}</td>
									<td class="is-center">
										@if($it['is_estimate'])
											<span class="daily-badge daily-badge-warning">Estimación</span>
										@else
											<span class="daily-badge daily-badge-success">Factura Real</span>
										@endif
									</td>
								</tr>
								@empty
								<tr>
									<td colspan="6" class="text-center py-4 text-muted">
										Sin gastos registrados en el periodo {{ $period }}.
									</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ==================== PESTAÑA 2: RENTABILIDAD POR CLIENTE ==================== -->
	<div class="daily-tab-pane" id="econ-tab-clients">
		<div class="daily-card">
			<div class="daily-card-header">
				<h3 class="daily-card-title"><i class="fa-solid fa-building text-primary mr-1"></i> Cartera de Clientes y Margen por Empresa</h3>
				<span class="text-muted small">Costo asignado calculado con base en sucursales y almacenamiento R2/S3</span>
			</div>
			<div class="daily-table-wrap">
				<table class="daily-table">
					<thead>
						<tr>
							<th>Empresa / Cliente</th>
							<th class="is-center">Sucursales</th>
							<th class="is-center">Usuarios</th>
							<th>Plan Contratado</th>
							<th>Soporte</th>
							<th class="is-number">Tarifa/Sucursal</th>
							<th class="is-number">MRR Total</th>
							<th class="is-number">Costo Cloud</th>
							<th class="is-number">Margen Bruto</th>
							<th class="is-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($clients as $cl)
						<tr>
							<td>
								<strong>{{ $cl['company_name'] }}</strong>
								<div class="text-muted small">Renovación: {{ $cl['renewal_date'] }} · {{ $cl['storage_gb_used'] }} GB fotos</div>
							</td>
							<td class="is-center"><strong>{{ $cl['stores_count'] }}</strong></td>
							<td class="is-center">{{ $cl['users_count'] }}</td>
							<td><span class="badge badge-light border">{{ $cl['plan_name'] }}</span></td>
							<td><span class="text-muted small">{{ $cl['support_tier'] === 'premium_24x7' ? '24/7 Premium' : '8x5 Standard' }}</span></td>
							<td class="is-number font-weight-bold">${{ number_format($cl['price_per_store'], 2) }}</td>
							<td class="is-number font-weight-bold text-success">${{ number_format($cl['mrr_usd'], 2) }}</td>
							<td class="is-number text-danger">${{ number_format($cl['allocated_cost_usd'], 2) }}</td>
							<td class="is-number">
								<strong class="{{ $cl['gross_margin_pct'] >= 85.0 ? 'text-success' : ($cl['gross_margin_pct'] >= 75.0 ? 'text-warning' : 'text-danger') }}">
									{{ $cl['gross_margin_pct'] }}%
								</strong>
								<div class="text-muted" style="font-size: 11px;">+${{ number_format($cl['gross_margin_usd'], 0) }}</div>
							</td>
							<td class="is-center">
								<span class="daily-badge daily-badge-success">Activo</span>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ==================== PESTAÑA 3: SIMULADOR DE PRECIOS & COTIZADOR ==================== -->
	<div class="daily-tab-pane" id="econ-tab-simulator">
		<div class="row">
			<!-- Columna Izquierda: Parámetros del Prospecto -->
			<div class="col-lg-5 mb-4">
				<div class="daily-card">
					<div class="daily-card-header">
						<h3 class="daily-card-title"><i class="fa-solid fa-sliders text-success mr-1"></i> Parámetros del Cliente Retail</h3>
					</div>

					<form id="formSimulator" onsubmit="runSimulation(event)">
						<div class="form-group mb-3">
							<label class="font-weight-bold small d-flex justify-content-between">
								<span>Número de Sucursales / Tiendas:</span>
								<strong id="dispStores" class="text-success font-weight-bold">5</strong>
							</label>
							<input type="range" class="econ-slider" id="simStores" min="1" max="100" value="5" oninput="$('#dispStores').text(this.value)">
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-bold small d-flex justify-content-between">
								<span>Usuarios por Tienda (Cajeros, Supervisores, Seguridad):</span>
								<strong id="dispUsers" class="text-dark font-weight-bold">5</strong>
							</label>
							<input type="range" class="econ-slider" id="simUsers" min="2" max="20" value="5" oninput="$('#dispUsers').text(this.value)">
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-bold small">Plan Contratado:</label>
							<select class="daily-select w-100" id="simPlan">
								<option value="enterprise_360" selected>DAILY Enterprise 360° (Full Prevención + Auditoría + Celulares)</option>
								<option value="full_retail">DAILY Full Retail (Ventas + Stock + Mermas)</option>
								<option value="basic">DAILY Básico (Inventario + Conteo)</option>
							</select>
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-bold small">Volumen de Dispositivos / Celulares Serializados:</label>
							<select class="daily-select w-100" id="simDevices">
								<option value="0">Sin módulo de serializados / celulares</option>
								<option value="50">Hasta 50 equipos por tienda</option>
								<option value="150" selected>150 a 300 equipos de alto valor (Telefonía)</option>
								<option value="500">Más de 500 equipos (Centro de Distribución)</option>
							</select>
						</div>

						<div class="form-group mb-3">
							<label class="font-weight-bold small">Nivel de Soporte y SLA:</label>
							<select class="daily-select w-100" id="simSupport">
								<option value="standard_8x5" selected>Estándar 8x5 (Email + Tickets + SLA 4h)</option>
								<option value="premium_24x7">Misión Crítica 24x7 (Llamadas + Canal Dedicado + SLA 30m)</option>
							</select>
						</div>

						<div class="form-group mb-4">
							<label class="font-weight-bold small d-flex justify-content-between">
								<span>Margen Bruto Objetivo:</span>
								<strong id="dispMargin" class="text-success font-weight-bold">85%</strong>
							</label>
							<input type="range" class="econ-slider" id="simMargin" min="60" max="95" value="85" oninput="$('#dispMargin').text(this.value + '%')">
						</div>

						<button type="submit" class="daily-btn daily-btn-primary w-100">
							<i class="fa-solid fa-calculator"></i> Calcular Cotización y Margen
						</button>
					</form>
				</div>
			</div>

			<!-- Columna Derecha: Resultado Comercial y Cotización -->
			<div class="col-lg-7 mb-4">
				<div class="daily-card">
					<div class="daily-card-header">
						<h3 class="daily-card-title"><i class="fa-solid fa-file-invoice text-primary mr-1"></i> Propuesta Comercial Recomendada</h3>
						<span class="daily-badge daily-badge-success" id="resMarginBadge">Margen: 85.2%</span>
					</div>

					<div class="row mb-3">
						<div class="col-sm-6 mb-2">
							<div class="p-3 border rounded" style="background: var(--daily-surface-subtle);">
								<span class="text-muted small text-uppercase font-weight-bold">Precio Sugerido / Tienda</span>
								<h2 class="m-0 font-weight-bold text-success" id="resPriceStore">$99.00 <small class="text-muted" style="font-size: 14px;">USD/mes</small></h2>
								<small class="text-muted">Costo infraestructura: <strong id="resCostStore">$6.30</strong>/mes</small>
							</div>
						</div>
						<div class="col-sm-6 mb-2">
							<div class="p-3 border rounded" style="background: var(--daily-surface-subtle);">
								<span class="text-muted small text-uppercase font-weight-bold">Facturación Recurrente (MRR)</span>
								<h2 class="m-0 font-weight-bold text-dark" id="resMrrTotal">$495.00 <small class="text-muted" style="font-size: 14px;">USD/mes</small></h2>
								<small class="text-muted">ARR (Valor anual): <strong id="resArrTotal">$5,940.00</strong> USD</small>
							</div>
						</div>
					</div>

					<!-- Argumento de Retorno de Inversión (ROI) para la Venta -->
					<div class="econ-roi-pill mb-3">
						<h4 class="font-weight-bold mb-1" style="font-size: 1rem;"><i class="fa-solid fa-shield-halved mr-1"></i> Argumentario de Retorno para el Cliente Retail</h4>
						<p class="m-0 small" id="resRoiText">
							Para una red de 5 tiendas, una reducción del 18% en faltantes de inventario y mermas operativas representa un ahorro estimado de <strong>$1,377.00 USD/mes</strong>. Frente a una inversión en DAILY de <strong>$495.00 USD/mes</strong>, el cliente obtiene un <strong>beneficio neto de $882.00 USD/mes</strong> (retorno de inversión de <strong>2.8x</strong>).
						</p>
					</div>

					<!-- Resumen Desglosado para Propuesta Comercial -->
					<div class="border rounded p-3" style="background: #ffffff;">
						<h5 class="font-weight-bold small text-uppercase text-muted mb-2">Estructura de la Propuesta</h5>
						<ul class="list-unstyled m-0 small">
							<li class="d-flex justify-content-between py-1 border-bottom">
								<span>Licenciamiento SaaS Multi-sucursal:</span>
								<strong id="resStoresText">5 tiendas × $99.00</strong>
							</li>
							<li class="d-flex justify-content-between py-1 border-bottom">
								<span>Almacenamiento de Evidencias y Fotos R2:</span>
								<strong>Incluido (Hasta 20 GB)</strong>
							</li>
							<li class="d-flex justify-content-between py-1 border-bottom">
								<span>Control de Dispositivos e IMEI Serializado:</span>
								<strong>Habilitado</strong>
							</li>
							<li class="d-flex justify-content-between py-1">
								<span>SLA de Soporte y Actualizaciones:</span>
								<strong id="resSupportText">Estándar 8x5</strong>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ==================== PESTAÑA 4: ALERTAS FINOPS ==================== -->
	<div class="daily-tab-pane" id="econ-tab-alerts">
		<div class="daily-card">
			<div class="daily-card-header">
				<h3 class="daily-card-title"><i class="fa-solid fa-triangle-exclamation text-danger mr-1"></i> Alertas y Políticas de Gobierno FinOps</h3>
				<span class="text-muted small">Detección automática de desviaciones de margen y sobreconsumo</span>
			</div>

			<div class="d-flex flex-column gap-3">
				@forelse($alerts as $al)
				<div class="p-3 border rounded d-flex align-items-center justify-content-between" style="background: var(--daily-{{ $al['level'] === 'danger' ? 'red' : ($al['level'] === 'warning' ? 'amber' : 'green') }}-50);">
					<div>
						<div class="d-flex align-items-center gap-2 mb-1">
							<span class="daily-badge daily-badge-{{ $al['level'] === 'danger' ? 'danger' : ($al['level'] === 'warning' ? 'warning' : 'success') }}">
								{{ strtoupper($al['level']) }}
							</span>
							<strong class="text-dark">{{ $al['title'] }}</strong>
						</div>
						<p class="m-0 text-muted small">{{ $al['message'] }}</p>
					</div>
					<a href="{{ $al['href'] }}" class="daily-btn daily-btn-sm daily-btn-secondary" onclick="$('.daily-tab-btn[data-tab-target=\'#econ-tab-simulator\']').trigger('click');">
						{{ $al['action_label'] }}
					</a>
				</div>
				@empty
				<div class="text-center py-4 text-muted">
					<i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i>
					<p class="m-0">No hay alertas financieras activas. Todos los márgenes y gastos están dentro de los parámetros esperados.</p>
				</div>
				@endforelse
			</div>
		</div>
	</div>

</div>

<!-- MODAL REGISTRO DE GASTO CLOUD -->
<div id="modalNewExpense" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.5); z-index:1060; backdrop-filter:blur(2px);">
	<div class="daily-card" style="max-width:500px; margin: 80px auto; box-shadow: var(--daily-shadow-modal);">
		<div class="daily-card-header">
			<h3 class="daily-card-title"><i class="fa-solid fa-receipt text-success mr-1"></i> Registrar Gasto Cloud</h3>
			<button type="button" class="close" onclick="$('#modalNewExpense').fadeOut(200)">&times;</button>
		</div>

		<form id="formExpense" onsubmit="submitExpense(event)">
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Proveedor:</label>
				<input type="text" class="daily-input w-100" name="provider" placeholder="ej. Hetzner, AWS, Supabase, Cloudflare" required>
			</div>

			<div class="form-group mb-2">
				<label class="small font-weight-bold">Categoría:</label>
				<select class="daily-select w-100" name="category" required>
					<option value="compute">Cómputo Backend (VPS / Contenedores)</option>
					<option value="database">Base de Datos (PostgreSQL / HA)</option>
					<option value="storage">Almacenamiento Evidencias (R2 / S3)</option>
					<option value="network">Red, CDN & Seguridad</option>
					<option value="monitoring">Monitoreo & Logs</option>
					<option value="other">Otros servicios</option>
				</select>
			</div>

			<div class="form-group mb-2">
				<label class="small font-weight-bold">Monto en USD:</label>
				<input type="number" step="0.01" class="daily-input w-100" name="amount_usd" placeholder="0.00" required>
			</div>

			<div class="form-group mb-2">
				<label class="small font-weight-bold">Período de Facturación (YYYY-MM):</label>
				<input type="month" class="daily-input w-100" name="billing_period" value="{{ $period }}" required>
			</div>

			<div class="form-group mb-3">
				<label class="small font-weight-bold">Descripción / N° Factura:</label>
				<input type="text" class="daily-input w-100" name="description" placeholder="Referencia de factura o detalle del servidor">
			</div>

			<div class="d-flex justify-content-end gap-2">
				<button type="button" class="daily-btn daily-btn-secondary" onclick="$('#modalNewExpense').fadeOut(200)">Cancelar</button>
				<button type="submit" class="daily-btn daily-btn-primary">Guardar Gasto</button>
			</div>
		</form>
	</div>
</div>

@stop

@push('pageScripts')
<script>
$(document).on('click', '.daily-tab-btn', function() {
	var target = $(this).data('tab-target');
	$('.daily-tab-btn').removeClass('active');
	$(this).addClass('active');
	$('.daily-tab-pane').removeClass('active');
	$(target).addClass('active');
});

function runSimulation(e) {
	if (e) e.preventDefault();

	var payload = {
		stores_count: parseInt($('#simStores').val()),
		users_per_store: parseInt($('#simUsers').val()),
		plan: $('#simPlan').val(),
		serialized_devices_volume: parseInt($('#simDevices').val()),
		support_tier: $('#simSupport').val(),
		target_margin_pct: parseFloat($('#simMargin').val())
	};

	$.ajax({
		url: Grocy.BaseUrl + '/api/admin/economics/simulate',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(payload),
		success: function(res) {
			$('#resPriceStore').html('$' + res.pricing.suggested_price_per_store.toFixed(2) + ' <small class="text-muted" style="font-size: 14px;">USD/mes</small>');
			$('#resCostStore').text('$' + res.costs.infra_per_store_monthly.toFixed(2));
			$('#resMrrTotal').html('$' + res.pricing.suggested_mrr.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' <small class="text-muted" style="font-size: 14px;">USD/mes</small>');
			$('#resArrTotal').text('$' + res.pricing.suggested_arr.toLocaleString('en-US', {minimumFractionDigits: 2}));
			$('#resMarginBadge').text('Margen: ' + res.pricing.projected_margin_pct + '%');
			$('#resStoresText').text(res.inputs.stores_count + ' tiendas × $' + res.pricing.suggested_price_per_store.toFixed(2));
			$('#resSupportText').text(res.inputs.support_tier === 'premium_24x7' ? '24/7 Premium' : 'Estándar 8x5');

			$('#resRoiText').html('Para una red de ' + res.inputs.stores_count + ' tiendas, una reducción del 18% en faltantes de inventario y mermas representa un ahorro estimado de <strong>$' + (res.client_roi.estimated_loss_prevented_store * res.inputs.stores_count).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' USD/mes</strong>. Frente a una inversión en DAILY de <strong>$' + res.pricing.suggested_mrr.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' USD/mes</strong>, el cliente obtiene un <strong>beneficio neto de $' + res.client_roi.client_net_savings_monthly.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' USD/mes</strong> (retorno de inversión de <strong>' + res.client_roi.roi_ratio + '</strong>).');
		}
	});
}

function submitExpense(e) {
	e.preventDefault();
	var form = document.getElementById('formExpense');
	var data = {
		provider: form.provider.value,
		category: form.category.value,
		amount_usd: parseFloat(form.amount_usd.value),
		billing_period: form.billing_period.value,
		description: form.description.value
	};

	$.ajax({
		url: Grocy.BaseUrl + '/api/admin/economics/expense',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function() {
			toastr.success('Gasto de infraestructura guardado');
			$('#modalNewExpense').fadeOut(200);
			location.reload();
		},
		error: function(xhr) {
			toastr.error(xhr.responseJSON ? xhr.responseJSON.error_message : 'Error al guardar el gasto');
		}
	});
}
</script>
@endpush
