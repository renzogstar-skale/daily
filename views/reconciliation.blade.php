@extends('layout.default')

@section('title', 'Trazabilidad, Conciliación e Inventario Sensible | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_reconciliation.css?v=', true) }}{{ time() }}" rel="stylesheet">
@endpush

@section('content')
<link href="{{ $U('/css/daily_reconciliation.css?v=', true) }}{{ time() }}" rel="stylesheet">

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$fmtQty = fn($v) => rtrim(rtrim(number_format(floatval($v), 2, '.', ''), '0'), '.');
	$card = $selectedProductCard ?? null;
	$prod = $card['product'] ?? null;
	$metrics = $card['metrics'] ?? null;
	$activeCase = $card['active_case'] ?? null;

	$selectedBranchName = 'Red Consolidada (Todas las sucursales)';
	if (!empty($selectedBranchId)) {
		foreach ($branches as $b) {
			if (intval($b->id) === intval($selectedBranchId)) {
				$selectedBranchName = $b->name;
				break;
			}
		}
	}
@endphp

<div class="rc-page-container">

	<!-- ====================================================================
	     1. ENCABEZADO HERO BANNER
	     ==================================================================== -->
	<div class="rc-hero-card">
		<div class="rc-hero-flex">
			<div class="rc-hero-left">
				<div class="rc-hero-icon">
					<i class="fa-solid fa-arrows-rotate"></i>
				</div>
				<div>
					<h1 class="rc-hero-title">Trazabilidad, Conciliación e Inventario Sensible</h1>
					<p class="rc-hero-desc">Control integral de 5 etapas: Ingreso → Devoluciones → Despachos → Inventario Sensible → Alertas e Investigaciones.</p>
				</div>
			</div>

			<div class="rc-hero-actions">
				<form method="get" class="d-inline-flex align-items-center gap-2 m-0" id="formBranchFilter">
					<select name="branch_id" class="rc-hero-select" onchange="this.form.submit()" aria-label="Sucursal">
						<option value="">Todas las sucursales</option>
						@foreach($branches as $b)
							<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
						@endforeach
					</select>
					@if(!empty($selectedProductId))
						<input type="hidden" name="product_id" value="{{ $selectedProductId }}">
					@endif
					@if(!empty($selectedDepartment))
						<input type="hidden" name="department" value="{{ $selectedDepartment }}">
					@endif
				</form>

				<button type="button" class="rc-btn-primary" data-toggle="modal" data-target="#modalCount">
					<i class="fa-solid fa-clipboard-check"></i>
					<span>Realizar conteo</span>
				</button>

				<button type="button" class="rc-btn-outline" data-toggle="modal" data-target="#modalGoodsIn">
					<i class="fa-solid fa-truck-ramp-box text-success"></i>
					<span>Ingreso</span>
				</button>

				<button type="button" class="rc-btn-outline" data-toggle="modal" data-target="#modalReturn">
					<i class="fa-solid fa-arrow-rotate-left text-warning"></i>
					<span>Devolución</span>
				</button>

				<button type="button" class="rc-btn-outline" data-toggle="modal" data-target="#modalMovement">
					<i class="fa-solid fa-truck text-info"></i>
					<span>Despacho</span>
				</button>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     2. OPERATIONAL FLOW STEPPER (1 -> 2 -> 3 -> 4 -> 5)
	     ==================================================================== -->
	<div class="rc-flow-bar">
		<div class="rc-flow-step" onclick="$('#modalGoodsIn').modal('show')" title="Registrar recepción de mercancía con OC, proveedor y series">
			<div class="rc-flow-num">1</div>
			<div class="rc-flow-label">1. Ingreso de mercancía</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step" onclick="$('#modalReturn').modal('show')" title="Integrar devoluciones, cambios y RTV con disposición de stock">
			<div class="rc-flow-num">2</div>
			<div class="rc-flow-label">2. Devoluciones & cambios</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step" onclick="$('#modalMovement').modal('show')" title="Despachos, transferencias físicas y reservas de bodega digital">
			<div class="rc-flow-num">3</div>
			<div class="rc-flow-label">3. Despachos & movimientos</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step active" title="Centro de conciliación e inventario sensible actual">
			<div class="rc-flow-num">4</div>
			<div class="rc-flow-label">4. Inventario sensible & selectivo</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step" onclick="@if($activeCase)$('#modalInvestigationDossier').modal('show')@else window.location.href='{{ $U('/alerts') }}'@endif" title="Motor automático de alertas e investigación de diferencias">
			<div class="rc-flow-num">5</div>
			<div class="rc-flow-label">5. Alertas & investigaciones</div>
		</div>
	</div>

	<!-- ====================================================================
	     3. CORPORATE MULTI-BRANCH KPIS
	     ==================================================================== -->
	<div class="rc-kpis-grid">
		<!-- KPI 1: Faltantes Detectados -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-accent is-red"></div>
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Faltantes Detectados</span>
				<span class="rc-kpi-value text-danger">{{ $money($overview['shortage_value']) }}</span>
				<span class="rc-kpi-sub">{{ $fmtQty($overview['shortage_units']) }} u. con descuadre físico</span>
			</div>
			<div class="rc-kpi-icon-box is-red">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 2: Casos Abiertos -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-accent is-amber"></div>
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Casos en Investigación</span>
				<span class="rc-kpi-value">{{ $overview['open_cases_count'] }}</span>
				<span class="rc-kpi-sub">{{ $overview['preliminary_count'] }} preliminares · {{ $overview['confirmed_shortage_count'] }} confirmados</span>
			</div>
			<div class="rc-kpi-icon-box is-amber">
				<i class="fa-solid fa-magnifying-glass-chart"></i>
			</div>
		</div>

		<!-- KPI 3: Pérdidas Reconocidas -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-accent is-purple"></div>
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Pérdidas Reconocidas</span>
				<span class="rc-kpi-value">{{ $money($overview['recognized_loss_value']) }}</span>
				<span class="rc-kpi-sub">Ajustes autorizados en merma</span>
			</div>
			<div class="rc-kpi-icon-box is-purple">
				<i class="fa-solid fa-file-invoice-dollar"></i>
			</div>
		</div>

		<!-- KPI 4: Conteos Pendientes -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-accent is-blue"></div>
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Conteos Pendientes</span>
				<span class="rc-kpi-value">{{ $overview['pending_counts'] }}</span>
				<span class="rc-kpi-sub">Inventarios selectivos en cola</span>
			</div>
			<div class="rc-kpi-icon-box is-blue">
				<i class="fa-solid fa-clipboard-list"></i>
			</div>
		</div>

		<!-- KPI 5: Reincidencias -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-accent is-green"></div>
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Reincidencias 90D</span>
				<span class="rc-kpi-value">{{ $overview['recidivism_count'] }}</span>
				<span class="rc-kpi-sub">Productos con >1 diferencia</span>
			</div>
			<div class="rc-kpi-icon-box is-green">
				<i class="fa-solid fa-chart-line"></i>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     4. TWO-COLUMN INTERACTIVE WORKSPACE
	     ==================================================================== -->
	<div class="rc-workspace-grid">

		<!-- LEFT COLUMN: PRODUCT SELECTOR & RISK MATRIX -->
		<div class="rc-catalog-panel">
			<div class="rc-catalog-header">
				<div class="rc-catalog-title">
					<span><i class="fa-solid fa-boxes-stacked mr-1 text-primary"></i>Catálogo & Sensibilidad</span>
					<span class="badge badge-light border">{{ count($products) }} ítems</span>
				</div>

				<form method="get" class="mb-2">
					@if(!empty($selectedBranchId))<input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">@endif
					<div class="rc-search-input-box">
						<i class="fa-solid fa-magnifying-glass"></i>
						<input type="text" name="q" class="rc-search-input" value="{{ $query }}" placeholder="Buscar SKU, UPC, nombre...">
					</div>
				</form>

				<!-- Department Pills -->
				<div class="rc-filter-pills">
					<a href="{{ $U('/reconciliation' . ($selectedBranchId ? '?branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if(empty($selectedDepartment)) active @endif">Todos</a>
					@foreach(array_slice($departments, 0, 4) as $dept)
						<a href="{{ $U('/reconciliation?department=' . urlencode($dept) . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
						   class="rc-pill-btn @if($selectedDepartment === $dept) active @endif">{{ $dept }}</a>
					@endforeach
				</div>
			</div>

			<div class="rc-product-list">
				@forelse($products as $p)
					@php
						$isActive = ($selectedProductId === intval($p['id']));
						$imgUrl = !empty($p['picture_file_name']) ? $U('/img/control_center/' . $p['picture_file_name']) : $U('/img/control_center/daily_scan_phone.jpg');
					@endphp
					<a href="{{ $U('/reconciliation?product_id=' . $p['id'] . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '') . ($selectedDepartment ? '&department=' . urlencode($selectedDepartment) : '')) }}"
					   class="rc-product-card @if($isActive) active @endif">
						<img src="{{ $imgUrl }}" alt="{{ $p['name'] }}" class="rc-prod-thumb" onerror="this.src='/img/control_center/daily_card_shelf.jpg'">
						<div class="rc-prod-info">
							<div class="rc-prod-name">{{ $p['name'] }}</div>
							<div class="rc-prod-meta">
								<span>{{ $p['sku'] }}</span>
								<span>·</span>
								<span>Stock: <b>{{ $fmtQty($p['current_stock']) }}</b></span>
								@if(!empty($p['open_case_id']))
									<span class="badge badge-danger">Caso abierto</span>
								@elseif($p['is_sensitive'])
									<span class="badge badge-warning">Sensible</span>
								@endif
							</div>
						</div>
						<div>
							<span class="rc-prod-stock-badge is-{{ $p['risk_level'] }}">
								@if($p['risk_level'] === 'danger')
									<i class="fa-solid fa-circle-exclamation"></i>
								@elseif($p['risk_level'] === 'warning')
									<i class="fa-solid fa-triangle-exclamation"></i>
								@else
									<i class="fa-solid fa-circle-check"></i>
								@endif
							</span>
						</div>
					</a>
				@empty
					<div class="p-3 text-center text-muted small">
						No se encontraron productos coincidentes.
					</div>
				@endforelse
			</div>
		</div>

		<!-- RIGHT COLUMN: 360° PRODUCT DOSSIER -->
		<div class="rc-dossier-panel">
			@if($prod)
				<!-- Product Header Card -->
				<div class="rc-dossier-header-card">
					<div class="rc-dossier-left">
						@php
							$headerImg = !empty($prod['picture_file_name']) ? $U('/img/control_center/' . $prod['picture_file_name']) : $U('/img/control_center/daily_scan_phone.jpg');
						@endphp
						<img src="{{ $headerImg }}" alt="{{ $prod['name'] }}" class="rc-dossier-img" onerror="this.src='/img/control_center/daily_card_shelf.jpg'">
						<div>
							<h2 class="rc-dossier-title">{{ $prod['name'] }}</h2>
							<div class="rc-dossier-badges">
								<span class="rc-tag is-dept"><i class="fa-solid fa-tag"></i>{{ $prod['department'] }}</span>
								<span class="rc-tag"><i class="fa-solid fa-barcode"></i>SKU: <b>{{ $prod['sku'] }}</b></span>
								<span class="rc-tag"><i class="fa-solid fa-fingerprint"></i>UPC: <b>{{ $prod['upc'] }}</b></span>
								@if($prod['is_sensitive'])
									<span class="rc-tag is-sensitive"><i class="fa-solid fa-shield-halved"></i>Inventario Sensible</span>
								@endif
								@if($prod['is_serialized'])
									<span class="rc-tag is-serial"><i class="fa-solid fa-microchip"></i>Control Serie / IMEI</span>
								@endif
								<span class="rc-tag">Precio: <b>{{ $money($prod['sale_price']) }}</b> · Costo: <b>{{ $money($prod['unit_cost']) }}</b></span>
							</div>
						</div>
					</div>

					<div class="rc-dossier-actions">
						<a href="{{ $U('/kardex?product_id=' . $prod['id']) }}" class="rc-btn-outline" title="Ver Kardex Valorizado">
							<i class="fa-solid fa-book-open text-primary"></i>
							<span>Kardex</span>
						</a>
						<button type="button" class="rc-btn-primary" data-toggle="modal" data-target="#modalCount">
							<i class="fa-solid fa-clipboard-check"></i>
							<span>Contar producto</span>
						</button>
					</div>
				</div>

				<!-- Active Investigation Alert Banner (if discrepancy or case exists) -->
				@if($activeCase)
					<div class="rc-alert-banner">
						<div class="rc-alert-banner-left">
							<div class="rc-alert-banner-icon">
								<i class="fa-solid fa-triangle-exclamation"></i>
							</div>
							<div>
								<div class="rc-alert-banner-title">
									{{ $activeCase['case_code'] }} · {{ $activeCase['stage'] === 'preliminar' ? 'Alerta Preliminar de Diferencia' : 'Faltante Confirmado en Investigación' }}
									<span class="badge badge-danger ml-2">{{ $fmtQty($activeCase['difference_qty']) }} u. ({{ $money($activeCase['financial_impact']) }})</span>
								</div>
								<div class="rc-alert-banner-desc">
									Conteo físico registró <b>{{ $fmtQty($activeCase['physical_count']) }} u.</b> vs <b>{{ $fmtQty($activeCase['expected_stock']) }} u.</b> esperadas en sistema.
									<b>Intervalo de auditoría:</b> {{ substr($activeCase['audit_interval_start'], 0, 10) }} al {{ substr($activeCase['audit_interval_end'], 0, 10) }} ({{ $activeCase['audit_interval_tx_count'] }} movimientos en ventana).
									No se presume sustracción en momento exacto; requiere reconteo y validación de reservas.
								</div>
							</div>
						</div>
						<div>
							<button type="button" class="rc-btn-investigate" data-toggle="modal" data-target="#modalInvestigationDossier">
								<i class="fa-solid fa-folder-open"></i>
								<span>Abrir Ficha de Investigación</span>
							</button>
						</div>
					</div>
				@endif

				<!-- ====================================================================
				     THE 14 MANDATORY OPERATIONAL METRICS GRID
				     ==================================================================== -->
				<div>
					<div class="rc-metrics-section-title">
						<i class="fa-solid fa-list-check text-primary"></i>
						<span>Ficha 360° de Conciliación Operativa (14 Puntos Clave)</span>
					</div>

					<div class="rc-metrics-grid-14">
						<!-- 1. Fecha de ingreso inicial y último ingreso -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">1. Ingreso Inicial & Último</span>
								<span class="rc-metric-idx">01</span>
							</div>
							<div class="rc-metric-val">
								{{ !empty($metrics['last_reception_date']) ? substr($metrics['last_reception_date'], 0, 10) : '—' }}
							</div>
							<div class="rc-metric-detail">
								Inicial: {{ !empty($metrics['first_reception_date']) ? substr($metrics['first_reception_date'], 0, 10) : 'Sin registro' }}
							</div>
						</div>

						<!-- 2. Cantidad recibida y stock esperado -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">2. Recibido & Esperado</span>
								<span class="rc-metric-idx">02</span>
							</div>
							<div class="rc-metric-val text-primary">
								{{ $fmtQty($metrics['current_expected_stock']) }} <span class="small font-weight-normal text-muted">esperado</span>
							</div>
							<div class="rc-metric-detail">
								Histórico recibido: {{ $fmtQty($metrics['total_received_amount']) }} u.
							</div>
						</div>

						<!-- 3. Última venta registrada -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">3. Última Venta Registrada</span>
								<span class="rc-metric-idx">03</span>
							</div>
							<div class="rc-metric-val">
								@if(!empty($metrics['last_sale']))
									{{ $fmtQty($metrics['last_sale']['actual_amount']) }} u. · {{ substr($metrics['last_sale']['created_at'], 0, 10) }}
								@else
									<span class="text-muted">Sin ventas</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								Doc: {{ $metrics['last_sale']['document_code'] ?? '—' }} @if(!empty($metrics['last_sale']['imei']))· IMEI: {{ substr($metrics['last_sale']['imei'], -6) }}@endif
							</div>
						</div>

						<!-- 4. Última devolución o cambio -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">4. Última Devolución</span>
								<span class="rc-metric-idx">04</span>
							</div>
							<div class="rc-metric-val">
								@if(!empty($metrics['last_return']))
									{{ $fmtQty($metrics['last_return']['actual_amount']) }} u. · {{ substr($metrics['last_return']['created_at'], 0, 10) }}
								@else
									<span class="text-muted">Sin devoluciones</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								Destino: <b>{{ $metrics['last_return']['disposition'] ?? 'N/A' }}</b> ({{ $metrics['last_return']['document_code'] ?? '—' }})
							</div>
						</div>

						<!-- 5. Último despacho -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">5. Último Despacho</span>
								<span class="rc-metric-idx">05</span>
							</div>
							<div class="rc-metric-val">
								@if(!empty($metrics['last_dispatch']))
									{{ $fmtQty($metrics['last_dispatch']['actual_amount']) }} u. · {{ substr($metrics['last_dispatch']['created_at'], 0, 10) }}
								@else
									<span class="text-muted">Sin despachos</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								Estado: <span class="badge badge-light border">{{ $metrics['last_dispatch']['status'] ?? '—' }}</span> · {{ $metrics['last_dispatch']['dest_location'] ?? '—' }}
							</div>
						</div>

						<!-- 6. Último movimiento bodega digital -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">6. Mov. Bodega Digital</span>
								<span class="rc-metric-idx">06</span>
							</div>
							<div class="rc-metric-val text-info">
								@if(!empty($metrics['last_digital_movement']))
									{{ $metrics['last_digital_movement']['document_code'] ?? 'Reserva' }}
								@else
									<span class="text-muted">Sin reservas</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								@if(!empty($metrics['last_digital_movement']))
									{{ $metrics['last_digital_movement']['dest_location'] }} ({{ substr($metrics['last_digital_movement']['created_at'], 0, 10) }})
								@else
									Bodega virtual sincronizada
								@endif
							</div>
						</div>

						<!-- 7. Último movimiento bodega física -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">7. Mov. Bodega Física</span>
								<span class="rc-metric-idx">07</span>
							</div>
							<div class="rc-metric-val">
								@if(!empty($metrics['last_physical_movement']))
									{{ substr($metrics['last_physical_movement']['created_at'], 0, 10) }}
								@else
									<span class="text-muted">Sin registro</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								Ubicación: {{ $metrics['last_physical_movement']['dest_location'] ?? 'Sala Principal' }}
							</div>
						</div>

						<!-- 8. Fecha último inventario selectivo -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">8. Último Inv. Selectivo</span>
								<span class="rc-metric-idx">08</span>
							</div>
							<div class="rc-metric-val">
								{{ !empty($metrics['last_selective_count_date']) ? substr($metrics['last_selective_count_date'], 0, 10) : 'Pendiente' }}
							</div>
							<div class="rc-metric-detail">
								Conteo ciego por muestreo de rotación
							</div>
						</div>

						<!-- 9. Fecha último inventario sensible -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">9. Último Inv. Sensible</span>
								<span class="rc-metric-idx">09</span>
							</div>
							<div class="rc-metric-val text-danger font-weight-800">
								{{ !empty($metrics['last_sensitive_count_date']) ? substr($metrics['last_sensitive_count_date'], 0, 10) : 'Pendiente' }}
							</div>
							<div class="rc-metric-detail">
								Auditoría estricta de equipos y alto valor
							</div>
						</div>

						<!-- 10. Resultado del último conteo -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">10. Resultado Último Conteo</span>
								<span class="rc-metric-idx">10</span>
							</div>
							<div class="rc-metric-val @if(!empty($metrics['latest_count']['difference_amount']) && floatval($metrics['latest_count']['difference_amount']) < 0) text-danger @endif">
								@if(!empty($metrics['latest_count']))
									{{ $fmtQty($metrics['latest_count']['actual_amount']) }} u.
									<span class="small font-weight-bold">({{ $fmtQty($metrics['latest_count']['difference_amount']) > 0 ? '+' : '' }}{{ $fmtQty($metrics['latest_count']['difference_amount']) }} u.)</span>
								@else
									<span class="text-muted">Sin conteos</span>
								@endif
							</div>
							<div class="rc-metric-detail">
								Esperado: {{ $fmtQty($metrics['latest_count']['expected_amount'] ?? 0) }} u. · {{ substr($metrics['latest_count']['created_at'] ?? '', 0, 10) }}
							</div>
						</div>

						<!-- 11. Diferencias históricas -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">11. Diferencias Históricas</span>
								<span class="rc-metric-idx">11</span>
							</div>
							<div class="rc-metric-val">
								{{ $fmtQty($metrics['historical_diff_stats']['net_difference'] ?? 0) }} u.
							</div>
							<div class="rc-metric-detail">
								{{ $metrics['historical_diff_stats']['count_with_diff'] ?? 0 }} conteos con descuadre de {{ $metrics['historical_diff_stats']['total_counts'] ?? 0 }} totales
							</div>
						</div>

						<!-- 12. Ajustes autorizados -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">12. Ajustes Autorizados</span>
								<span class="rc-metric-idx">12</span>
							</div>
							<div class="rc-metric-val">
								{{ count($metrics['authorized_adjustments'] ?? []) }}
							</div>
							<div class="rc-metric-detail">
								@if(!empty($metrics['authorized_adjustments']))
									Último por: {{ $metrics['authorized_adjustments'][0]['authorizer_name'] ?? 'Supervisor' }}
								@else
									Sin ajustes pendientes
								@endif
							</div>
						</div>

						<!-- 13. Número de serie o IMEI -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">13. Números de Serie / IMEI</span>
								<span class="rc-metric-idx">13</span>
							</div>
							<div class="rc-metric-val">
								{{ count($metrics['serials'] ?? []) }} <span class="small font-weight-normal text-muted">series</span>
							</div>
							<div class="rc-metric-detail">
								@if(!empty($metrics['serials']))
									Activos: {{ count(array_filter($metrics['serials'], fn($s) => $s['serial_status'] === 'active')) }} · Custodia trazable
								@else
									Control por lote / código general
								@endif
							</div>
						</div>

						<!-- 14. Responsable de la última operación -->
						<div class="rc-metric-tile">
							<div class="rc-metric-num-header">
								<span class="rc-metric-label">14. Último Operador</span>
								<span class="rc-metric-idx">14</span>
							</div>
							<div class="rc-metric-val">
								{{ $metrics['last_operation']['user'] ?? 'Sistema' }}
							</div>
							<div class="rc-metric-detail">
								{{ $metrics['last_operation']['type'] ?? 'Operación' }} · {{ substr($metrics['last_operation']['date'] ?? '', 0, 16) }}
							</div>
						</div>
					</div>
				</div>

				<!-- Serial/IMEI Badge Strip (if serialized) -->
				@if(!empty($metrics['serials']))
					<div class="bg-white border rounded p-3 shadow-sm">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<span class="font-weight-700 text-dark small"><i class="fa-solid fa-microchip mr-2 text-primary"></i>Números de Serie e IMEI en Custodia ({{ count($metrics['serials']) }} registrados)</span>
							<span class="small text-muted">Verificación directa por escáner</span>
						</div>
						<div class="rc-serial-pill-list">
							@foreach($metrics['serials'] as $sn)
								<span class="rc-serial-pill is-{{ $sn['serial_status'] }}" title="{{ $sn['serial_status'] === 'active' ? 'En stock activo físico' : 'Estado: ' . $sn['serial_status'] }}">
									@if($sn['serial_status'] === 'active')
										<i class="fa-solid fa-check text-success"></i>
									@elseif($sn['serial_status'] === 'missing')
										<i class="fa-solid fa-xmark text-danger"></i>
									@else
										<i class="fa-solid fa-circle-dot text-muted"></i>
									@endif
									IMEI: {{ $sn['imei'] ?? $sn['serial_number'] }}
								</span>
							@endforeach
						</div>
					</div>
				@endif

				<!-- ====================================================================
				     UNIFIED CHRONOLOGICAL TIMELINE (LÍNEA DE TIEMPO ÚNICA)
				     ==================================================================== -->
				<div class="rc-timeline-panel">
					<div class="rc-timeline-header">
						<div class="rc-timeline-title">
							<i class="fa-solid fa-timeline text-primary"></i>
							<span>Línea de Tiempo Única de Movimientos</span>
							<span class="badge badge-light border ml-1">{{ count($timeline) }} eventos</span>
						</div>

						<div class="rc-timeline-filters" id="timelineFilterContainer">
							<button type="button" class="rc-timeline-filter-btn active" onclick="filterTimeline('all', this)">Todos</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('reception', this)">Ingresos</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('sale', this)">Ventas</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('return_exchange', this)">Devoluciones</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('dispatch', this)">Despachos</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('transfer', this)">Traslados</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('cycle_count', this)">Conteos</button>
							<button type="button" class="rc-timeline-filter-btn" onclick="filterTimeline('adjustment', this)">Ajustes</button>
						</div>
					</div>

					<div class="rc-timeline-flow">
						@forelse($timeline as $t)
							@php
								$diffVal = floatval($t['difference_amount'] ?? 0);
								$hasDiff = abs($diffVal) > 0.001;
								$isAlert = ($t['event_type'] === 'cycle_count' && $hasDiff);
							@endphp
							<div class="rc-timeline-entry is-{{ $t['event_type'] }} @if($isAlert) is-alert @endif" data-event-type="{{ $t['event_type'] }}">
								<div class="rc-timeline-marker">
									<i class="fa-solid {{ $t['icon_class'] }}"></i>
								</div>

								<div class="rc-timeline-entry-header">
									<div class="rc-timeline-entry-type" style="color: {{ $t['color'] }};">
										<span>{{ $t['type_label'] }}</span>
										<span class="badge {{ $t['badge_class'] }} ml-1">{{ $t['movement_code'] }}</span>
										@if($hasDiff)
											<span class="badge badge-danger">Diferencia: {{ $fmtQty($diffVal) }} u.</span>
										@endif
									</div>
									<div class="rc-timeline-entry-time">
										<i class="fa-regular fa-clock mr-1"></i>{{ $t['created_at'] }}
									</div>
								</div>

								<div class="rc-timeline-entry-body">
									{{ $t['notes'] }}
								</div>

								<div class="rc-timeline-entry-chips">
									<span class="rc-chip is-doc"><i class="fa-regular fa-file-lines mr-1"></i>{{ $t['document_type'] }}: {{ $t['document_code'] }}</span>
									<span class="rc-chip"><i class="fa-solid fa-user mr-1"></i>{{ $t['operator_name'] }}</span>
									@if(!empty($t['dest_location']))
										<span class="rc-chip"><i class="fa-solid fa-location-dot mr-1"></i>{{ $t['dest_location'] }}</span>
									@endif
									@if(!empty($t['imei']))
										<span class="rc-chip is-serial"><i class="fa-solid fa-microchip mr-1"></i>{{ $t['imei'] }}</span>
									@endif
									@if(!empty($t['supplier_name']))
										<span class="rc-chip"><i class="fa-solid fa-truck-field mr-1"></i>{{ $t['supplier_name'] }}</span>
									@endif
									@if(!empty($t['status']))
										<span class="rc-chip font-weight-bold">{{ strtoupper($t['status']) }}</span>
									@endif
								</div>
							</div>
						@empty
							<div class="p-3 text-center text-muted small">
								No hay movimientos registrados para este producto en los filtros seleccionados.
							</div>
						@endforelse
					</div>
				</div>

			@else
				<div class="bg-white border rounded p-5 text-center text-muted">
					<i class="fa-solid fa-box-open fa-3x mb-3 text-muted"></i>
					<h5>Selecciona un producto del catálogo para ver su conciliación 360°</h5>
					<p class="small">El centro reúne el historial registrado completo de ingresos, ventas, devoluciones, despachos y conteos.</p>
				</div>
			@endif
		</div>

	</div>

</div>

<!-- ====================================================================
     MODAL: FICHA DE INVESTIGACIÓN AUTOMÁTICA 360°
     ==================================================================== -->
<div class="modal fade" id="modalInvestigationDossier" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg rc-modal-dialog-wide" role="document">
		<div class="modal-content">
			<div class="rc-modal-header">
				<h5 class="rc-modal-title">
					<i class="fa-solid fa-shield-halved text-danger"></i>
					<span>Ficha de Investigación Automática · {{ $caseDetail['case']['case_code'] ?? ($activeCase['case_code'] ?? 'INV-2026') }}</span>
				</h5>
				<button type="button" class="rc-modal-close" data-dismiss="modal" aria-label="Close">
					<i class="fa-solid fa-xmark"></i>
				</button>
			</div>

			<div class="rc-dossier-body">
				@php
					$c = $caseDetail['case'] ?? $activeCase;
				@endphp

				@if($c)
					<!-- Highlight Box -->
					<div class="rc-dossier-highlight-box">
						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Producto</span>
							<span class="rc-highlight-val font-weight-700" style="font-size: 1.05rem;">{{ $prod['name'] ?? 'Producto' }}</span>
							<span class="small text-muted">{{ $prod['sku'] ?? 'SKU' }} · {{ $c['department'] ?? 'Dept' }}</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Stock Esperado vs Físico</span>
							<span class="rc-highlight-val">
								{{ $fmtQty($c['physical_count']) }} / <span class="text-muted">{{ $fmtQty($c['expected_stock']) }} u.</span>
							</span>
							<span class="small text-muted">Ubicación: {{ $c['location_name'] ?? 'Jaula Segura' }}</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Diferencia Detectada</span>
							<span class="rc-highlight-val is-diff">
								{{ $fmtQty($c['difference_qty']) }} u.
							</span>
							<span class="small text-danger font-weight-700">{{ $money($c['financial_impact']) }} impacto</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Etapa Operativa</span>
							<span class="rc-highlight-val" style="font-size: 1.05rem;">
								@if($c['stage'] === 'preliminar')
									<span class="badge badge-warning">Alerta Preliminar</span>
								@elseif($c['stage'] === 'faltante_confirmado')
									<span class="badge badge-danger">Faltante Confirmado</span>
								@elseif($c['stage'] === 'perdida_reconocida')
									<span class="badge badge-purple">Pérdida Reconocida</span>
								@else
									<span class="badge badge-success">Cerrado</span>
								@endif
							</span>
							<span class="small text-muted">Estado de reconteo: <b>{{ $c['recount_status'] ?? 'pending' }}</b></span>
						</div>
					</div>

					<!-- Delimitación Objetiva de Intervalo (Principio de Prudencia: no afirmar robo exacto) -->
					<div class="rc-interval-banner">
						<div class="font-weight-700 text-primary mb-1">
							<i class="fa-solid fa-stopwatch mr-1"></i>Delimitación del Intervalo de Auditoría
						</div>
						<div>
							Último conteo correcto verificado: <b>{{ substr($c['audit_interval_start'] ?? 'Sin información', 0, 16) }}</b>.
							Diferencia reportada el: <b>{{ substr($c['audit_interval_end'] ?? 'Sin información', 0, 16) }}</b>.
							Durante este intervalo se registraron <b>{{ $c['audit_interval_tx_count'] ?? 3 }} operaciones</b> en el kardex centralizado.
							<i>Principio de auditoría: Los registros determinan un intervalo cronológico entre verificaciones válidas, no un punto en el tiempo específico.</i>
						</div>
					</div>

					<!-- Evaluación de Causas Basadas en Evidencia -->
					<div class="rc-cause-evaluation">
						<div class="font-weight-700 mb-1">
							<i class="fa-solid fa-brain mr-1"></i>Evaluación Automática de Causas Basadas en Evidencia
						</div>
						<div>
							{{ $c['evidence_notes'] ?? 'Análisis en proceso. Se auditan pedidos web reservados sin entrega y casilleros de retiro Click & Collect.' }}
						</div>
					</div>

					<!-- Acciones Recomendadas -->
					<div class="bg-white border rounded p-3">
						<div class="font-weight-700 text-dark small mb-2">
							<i class="fa-solid fa-list-ol text-success mr-2"></i>Acciones Recomendadas por el Sistema
						</div>
						<div class="small text-muted" style="white-space: pre-line;">
							{{ $c['recommended_action'] ?? '1. Ejecutar reconteo guiado con operador independiente.' }}
						</div>
					</div>

					<!-- Acciones Interactivas del Supervisor -->
					<div class="border-top pt-3">
						<h6 class="font-weight-700 text-dark mb-3"><i class="fa-solid fa-gavel mr-2 text-primary"></i>Gestión y Resolución del Expediente</h6>

						<div class="row">
							<!-- Formulario 1: Reconteo Guiado -->
							<div class="col-md-6 mb-3">
								<div class="p-3 border rounded bg-light h-100">
									<div class="font-weight-700 text-dark small mb-2"><i class="fa-solid fa-rotate mr-1 text-primary"></i>1. Registrar Reconteo Físico</div>
									<form id="formRecountCase">
										<div class="rc-form-group">
											<label class="rc-form-label">Cantidad física recontada</label>
											<input type="number" step="1" name="recounted_qty" class="rc-form-control" value="{{ $c['physical_count'] }}" required>
										</div>
										<div class="rc-form-group">
											<label class="rc-form-label">Notas del reconteo / Verificación de casilleros</label>
											<textarea name="recount_notes" class="rc-form-control" style="height: 60px;" placeholder="Ej. Se verificó casillero #04 y se halló 1 unidad..."></textarea>
										</div>
										<button type="button" class="btn btn-sm btn-primary font-weight-600" onclick="submitRecount({{ $c['id'] }})">
											<i class="fa-solid fa-check mr-1"></i>Confirmar Reconteo
										</button>
									</form>
								</div>
							</div>

							<!-- Formulario 2: Aprobar Ajuste Segregado -->
							<div class="col-md-6 mb-3">
								<div class="p-3 border rounded bg-light h-100">
									<div class="font-weight-700 text-dark small mb-2"><i class="fa-solid fa-scale-unbalanced-flip mr-1 text-danger"></i>2. Autorizar Ajuste (Segregación de Funciones)</div>
									<form id="formAdjustCase">
										<div class="rc-form-group">
											<label class="rc-form-label">Decisión del supervisor</label>
											<select name="decision" class="rc-form-control">
												<option value="approved">Aprobar ajuste contable (Pérdida Reconocida)</option>
												<option value="rejected">Rechazar ajuste (Mantener en investigación)</option>
											</select>
										</div>
										<div class="rc-form-group">
											<label class="rc-form-label">Dictamen de aprobación / Caso de Merma</label>
											<input type="text" name="reason" class="rc-form-control" placeholder="Ej. Faltante ratificado tras reconteo. Imputar a merma.">
										</div>
										<button type="button" class="btn btn-sm btn-danger font-weight-600" onclick="submitAdjustment({{ $c['id'] }})">
											<i class="fa-solid fa-stamp mr-1"></i>Autorizar Decisión
										</button>
									</form>
								</div>
							</div>
						</div>

						<div class="text-right mt-2">
							<button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-dismiss="modal">Cerrar vista</button>
							<button type="button" class="btn btn-sm btn-success font-weight-700" onclick="submitCloseCase({{ $c['id'] }})">
								<i class="fa-solid fa-circle-check mr-1"></i>Cerrar Expediente Formalmente
							</button>
						</div>
					</div>
				@else
					<div class="text-center p-4 text-muted">
						No hay expediente activo seleccionado.
					</div>
				@endif
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REGISTRAR INGRESO DE MERCANCÍA (1. Ingreso)
     ==================================================================== -->
<div class="modal fade" id="modalGoodsIn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-truck-ramp-box mr-2 text-success"></i>1. Ingreso de Mercancía</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formGoodsIn">
				<div class="modal-body">
					<div class="rc-form-group">
						<label class="rc-form-label">Producto</label>
						<select name="product_id" class="rc-form-control" required>
							@foreach($products as $p)
								<option value="{{ $p['id'] }}" @if($selectedProductId == $p['id']) selected @endif>{{ $p['name'] }} ({{ $p['sku'] }})</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Orden de Compra</label>
							<input type="text" name="po_code" class="rc-form-control" placeholder="N.º de OC del proveedor (vacío: folio secuencial al guardar)">
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Proveedor</label>
							<input type="text" name="supplier_name" class="rc-form-control" value="Distribuidora Central S.A." required>
						</div>
					</div>
					<div class="row">
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Cantidad Esperada</label>
							<input type="number" step="1" name="expected_qty" class="rc-form-control" value="10" required>
						</div>
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Cantidad Recibida</label>
							<input type="number" step="1" name="received_qty" class="rc-form-control" value="10" required>
						</div>
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Aceptada Conforme</label>
							<input type="number" step="1" name="accepted_qty" class="rc-form-control" value="10" required>
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Número de Serie / IMEI (si aplica)</label>
						<input type="text" name="imei" class="rc-form-control" placeholder="Ej. 358920110482911 (o rangos de serie)">
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Notas de Recepción y Precinto</label>
						<textarea name="notes" class="rc-form-control" style="height: 60px;">Recepción en muelle con verificación física de precintos y embalaje.</textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-success font-weight-700" onclick="submitGoodsIn()"><i class="fa-solid fa-check mr-1"></i>Registrar Ingreso</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REGISTRAR DEVOLUCIÓN O CAMBIO (2. Devoluciones)
     ==================================================================== -->
<div class="modal fade" id="modalReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-arrow-rotate-left mr-2 text-warning"></i>2. Devoluciones y Cambios</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formReturn">
				<div class="modal-body">
					<div class="rc-form-group">
						<label class="rc-form-label">Producto</label>
						<select name="product_id" class="rc-form-control" required>
							@foreach($products as $p)
								<option value="{{ $p['id'] }}" @if($selectedProductId == $p['id']) selected @endif>{{ $p['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Tipo de Operación</label>
							<select name="return_type" class="rc-form-control">
								<option value="devolucion_cliente">Devolución de Cliente</option>
								<option value="cambio_comercial">Cambio Comercial</option>
								<option value="devolucion_proveedor">Devolución a Proveedor (RTV)</option>
							</select>
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Disposición del Producto</label>
							<select name="disposition" class="rc-form-control" required>
								<option value="disponible">Reincorporar a inventario disponible</option>
								<option value="cuarentena">Enviado a cuarentena técnica</option>
								<option value="otra_bodega">Enviado a otra bodega / almacén</option>
								<option value="merma_definitiva">Salida definitiva / Merma</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Cantidad</label>
							<input type="number" step="1" name="quantity" class="rc-form-control" value="1" required>
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Documento de Respaldo</label>
							<input type="text" name="document_code" class="rc-form-control" placeholder="Documento de respaldo (vacío: folio secuencial al guardar)">
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Motivo y Diagnóstico</label>
						<textarea name="notes" class="rc-form-control" style="height: 60px;">Inspección técnica de estado y verificación de sellos de seguridad.</textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-warning font-weight-700" onclick="submitReturn()"><i class="fa-solid fa-check mr-1"></i>Registrar Operación</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REGISTRAR DESPACHO / MOVIMIENTO (3. Despachos)
     ==================================================================== -->
<div class="modal fade" id="modalMovement" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-truck mr-2 text-info"></i>3. Despachos y Movimientos</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formMovement">
				<div class="modal-body">
					<div class="rc-form-group">
						<label class="rc-form-label">Producto</label>
						<select name="product_id" class="rc-form-control" required>
							@foreach($products as $p)
								<option value="{{ $p['id'] }}" @if($selectedProductId == $p['id']) selected @endif>{{ $p['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Tipo de Movimiento</label>
							<select name="event_type" class="rc-form-control">
								<option value="dispatch">Despacho a Cliente</option>
								<option value="transfer">Transferencia entre Bodegas</option>
							</select>
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Tipo de Bodega</label>
							<select name="warehouse_type" class="rc-form-control">
								<option value="physical">Bodega Física (Pasillo / Jaula)</option>
								<option value="digital_reserve">Bodega Digital (Reserva E-commerce)</option>
								<option value="digital_transit">Bodega Digital (Casillero Pick-up)</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Estado de la Operación</label>
							<select name="status" class="rc-form-control">
								<option value="solicitado">Solicitado (Reserva, no deducir aún)</option>
								<option value="en_transito">En tránsito (Descontado de origen)</option>
								<option value="confirmado">Confirmado / Entregado en destino</option>
								<option value="cancelado">Cancelado (Liberar existencias)</option>
							</select>
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Cantidad</label>
							<input type="number" step="1" name="quantity" class="rc-form-control" value="1" required>
						</div>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Ubicación Origen</label>
							<input type="text" name="origin_location" class="rc-form-control" value="Bodega Matriz - Jaula Segura">
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Ubicación Destino</label>
							<input type="text" name="dest_location" class="rc-form-control" value="Casillero Retiro Tienda #04">
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Documento Asignado</label>
						<input type="text" name="document_code" class="rc-form-control" placeholder="Vacío: folio secuencial al guardar">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-info font-weight-700 text-white" onclick="submitMovement()"><i class="fa-solid fa-check mr-1"></i>Registrar Movimiento</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REALIZAR CONTEO FÍSICO (4. Inventario Sensible)
     ==================================================================== -->
<div class="modal fade" id="modalCount" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-clipboard-check mr-2 text-primary"></i>4. Realizar Conteo Físico</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="formCount">
				<div class="modal-body">
					<div class="rc-form-group">
						<label class="rc-form-label">Producto a contar</label>
						<select name="product_id" class="rc-form-control" required id="countProductId">
							@foreach($products as $p)
								<option value="{{ $p['id'] }}" @if($selectedProductId == $p['id']) selected @endif>
									{{ $p['name'] }} (Esperado: {{ $fmtQty($p['current_stock']) }} u.)
								</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Tipo de Conteo</label>
							<select name="count_type" class="rc-form-control">
								<option value="sensible">Inventario Sensible (Alto Valor / IMEI)</option>
								<option value="selectivo">Inventario Selectivo (Cíclico ABC)</option>
							</select>
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Cantidad Física Contada</label>
							<input type="number" step="1" name="counted_qty" class="rc-form-control" value="6" required id="countQuantityInput">
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Notas del Conteo</label>
						<textarea name="notes" class="rc-form-control" style="height: 60px;" placeholder="Detalles de la verificación física..."></textarea>
					</div>
					<div class="small text-muted p-2 bg-light border rounded">
						<i class="fa-solid fa-info-circle text-primary mr-1"></i>
						<b>Evaluación automática:</b> Si la cantidad física difiere del stock esperado, el motor generará una alerta preliminar, delimitará el intervalo de auditoría y abrirá la ficha de investigación correspondiente sin presumir sustracción.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-primary font-weight-700" onclick="submitCount()"><i class="fa-solid fa-check mr-1"></i>Procesar Conteo</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('pageScripts')
<script>
// Filtrar línea de tiempo única
function filterTimeline(eventType, btn) {
	$('#timelineFilterContainer button').removeClass('active');
	$(btn).addClass('active');

	if (eventType === 'all') {
		$('.rc-timeline-entry').fadeIn(150);
	} else {
		$('.rc-timeline-entry').hide();
		$('.rc-timeline-entry[data-event-type="' + eventType + '"]').fadeIn(150);
	}
}

// Ejecutar conteo físico (Punto 5 del requerimiento)
function submitCount() {
	var form = $('#formCount');
	var data = {
		product_id: form.find('[name="product_id"]').val(),
		branch_id: {{ $selectedBranchId ?: 1 }},
		count_type: form.find('[name="count_type"]').val(),
		counted_qty: form.find('[name="counted_qty"]').val(),
		notes: form.find('[name="notes"]').val()
	};

	toastr.info('Procesando conteo y evaluando motor de alertas...');
	$.ajax({
		url: U('/api/reconciliation/count'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalCount').modal('hide');
			toastr.success(resp.message || 'Conteo registrado correctamente.');
			setTimeout(function() {
				window.location.href = U('/reconciliation?product_id=' + data.product_id + '&branch_id=' + data.branch_id);
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar conteo';
			toastr.error(msg);
		}
	});
}

// 1. Ingreso de mercancía
function submitGoodsIn() {
	var form = $('#formGoodsIn');
	var data = {
		product_id: form.find('[name="product_id"]').val(),
		branch_id: {{ $selectedBranchId ?: 1 }},
		po_code: form.find('[name="po_code"]').val(),
		supplier_name: form.find('[name="supplier_name"]').val(),
		expected_qty: form.find('[name="expected_qty"]').val(),
		received_qty: form.find('[name="received_qty"]').val(),
		accepted_qty: form.find('[name="accepted_qty"]').val(),
		imei: form.find('[name="imei"]').val(),
		notes: form.find('[name="notes"]').val()
	};

	$.ajax({
		url: U('/api/reconciliation/goods-in'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalGoodsIn').modal('hide');
			toastr.success(resp.message || 'Ingreso registrado correctamente.');
			setTimeout(function() { window.location.reload(); }, 1000);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar ingreso');
		}
	});
}

// 2. Devoluciones y cambios
function submitReturn() {
	var form = $('#formReturn');
	var data = {
		product_id: form.find('[name="product_id"]').val(),
		branch_id: {{ $selectedBranchId ?: 1 }},
		return_type: form.find('[name="return_type"]').val(),
		disposition: form.find('[name="disposition"]').val(),
		quantity: form.find('[name="quantity"]').val(),
		document_code: form.find('[name="document_code"]').val(),
		notes: form.find('[name="notes"]').val()
	};

	$.ajax({
		url: U('/api/reconciliation/return'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalReturn').modal('hide');
			toastr.success(resp.message || 'Devolución registrada correctamente.');
			setTimeout(function() { window.location.reload(); }, 1000);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar devolución');
		}
	});
}

// 3. Despachos y movimientos
function submitMovement() {
	var form = $('#formMovement');
	var data = {
		product_id: form.find('[name="product_id"]').val(),
		branch_id: {{ $selectedBranchId ?: 1 }},
		event_type: form.find('[name="event_type"]').val(),
		warehouse_type: form.find('[name="warehouse_type"]').val(),
		status: form.find('[name="status"]').val(),
		quantity: form.find('[name="quantity"]').val(),
		origin_location: form.find('[name="origin_location"]').val(),
		dest_location: form.find('[name="dest_location"]').val(),
		document_code: form.find('[name="document_code"]').val()
	};

	$.ajax({
		url: U('/api/reconciliation/dispatch'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalMovement').modal('hide');
			toastr.success(resp.message || 'Movimiento registrado.');
			setTimeout(function() { window.location.reload(); }, 1000);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar movimiento');
		}
	});
}

// 6. Acciones de investigación
function submitRecount(caseId) {
	var form = $('#formRecountCase');
	var data = {
		recounted_qty: form.find('[name="recounted_qty"]').val(),
		notes: form.find('[name="recount_notes"]').val()
	};

	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/recount'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			toastr.success(resp.message || 'Reconteo registrado.');
			setTimeout(function() { window.location.reload(); }, 1200);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error en reconteo');
		}
	});
}

function submitAdjustment(caseId) {
	var form = $('#formAdjustCase');
	var data = {
		decision: form.find('[name="decision"]').val(),
		reason: form.find('[name="reason"]').val()
	};

	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/adjust'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			toastr.success(resp.message || 'Ajuste procesado.');
			setTimeout(function() { window.location.reload(); }, 1200);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error en ajuste');
		}
	});
}

function submitCloseCase(caseId) {
	var res = prompt('Indica las notas finales para cerrar formalmente el expediente:', 'Auditoría completada conforme a protocolo DAILY.');
	if (!res) return;

	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/close'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ resolution: res }),
		success: function(resp) {
			toastr.success(resp.message || 'Expediente cerrado formalmente.');
			setTimeout(function() { window.location.reload(); }, 1200);
		},
		error: function(err) {
			toastr.error('Error al cerrar expediente');
		}
	});
}
</script>
@endpush
