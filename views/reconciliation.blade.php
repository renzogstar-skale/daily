@extends('layout.default')

@section('title', 'Trazabilidad, Conciliación e Inventario Sensible | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_reconciliation.css?v=', true) }}{{ daily_asset_version('/css/daily_reconciliation.css') }}" rel="stylesheet">
@endpush

@section('content')
<link href="{{ $U('/css/daily_reconciliation.css?v=', true) }}{{ daily_asset_version('/css/daily_reconciliation.css') }}" rel="stylesheet">

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
	@include('components.execution_context_bar')

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
					<p class="rc-hero-desc">Control integral de 5 etapas: Ingreso · Devoluciones · Despachos · Inventario Sensible · Alertas e Investigaciones</p>
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

				<div class="dropdown d-inline-block">
					<button type="button" class="rc-btn-outline dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<span>Más acciones</span>
					</button>
					<div class="dropdown-menu dropdown-menu-right">
						<a class="dropdown-item" href="{{ $U('/kardex' . ($selectedProductId ? '?product_id=' . $selectedProductId : '')) }}">
							<i class="fa-solid fa-book-open mr-2 text-primary"></i>Kardex Valorizado
						</a>
						<a class="dropdown-item" href="{{ $U('/loss-prevention/devices') }}">
							<i class="fa-solid fa-mobile-screen mr-2 text-success"></i>Control de Celulares
						</a>
						<a class="dropdown-item" href="{{ $U('/merchandise') }}">
							<i class="fa-solid fa-boxes-stacked mr-2 text-info"></i>Control de Mercancías
						</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item" href="#" onclick="window.print(); return false;">
							<i class="fa-solid fa-print mr-2 text-muted"></i>Imprimir Ficha de Conciliación
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     2. OPERATIONAL FLOW STEPPER (5 ETAPAS)
	     ==================================================================== -->
	<div class="rc-flow-bar">
		<div class="rc-flow-step is-done" onclick="$('#modalGoodsIn').modal('show')" title="Registrar recepción de mercancía con OC, proveedor y series">
			<div class="rc-flow-num"><i class="fa-solid fa-check"></i></div>
			<div class="rc-flow-label">Ingreso de mercancía</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step is-done" onclick="$('#modalReturn').modal('show')" title="Integrar devoluciones, cambios y RTV con disposición de stock">
			<div class="rc-flow-num"><i class="fa-solid fa-check"></i></div>
			<div class="rc-flow-label">Devoluciones & cambios</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step" onclick="$('#modalMovement').modal('show')" title="Despachos, transferencias físicas y reservas de bodega digital">
			<div class="rc-flow-num">3</div>
			<div class="rc-flow-label">Despachos & movimientos</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step active" title="Centro de conciliación e inventario sensible actual">
			<div class="rc-flow-num">4</div>
			<div class="rc-flow-label">Inventario sensible & selectivo</div>
		</div>
		<div class="rc-flow-divider"><i class="fa-solid fa-chevron-right"></i></div>

		<div class="rc-flow-step" onclick="@if($activeCase)$('#modalInvestigationDossier').modal('show')@else window.location.href='{{ $U('/loss-prevention') }}'@endif" title="Motor automático de alertas e investigación de diferencias">
			<div class="rc-flow-num">5</div>
			<div class="rc-flow-label">Alertas & investigaciones</div>
		</div>
	</div>

	<!-- ====================================================================
	     3. CORPORATE MULTI-BRANCH KPIS (5 KPIS)
	     ==================================================================== -->
	<div class="rc-kpis-grid">
		<!-- KPI 1: Faltantes Detectados -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Faltantes Detectados</span>
				<div class="rc-kpi-value-row">
					<span class="rc-kpi-value text-danger">{{ $money($overview['shortage_value']) }}</span>
					<span class="rc-kpi-delta is-red">↑ +12.4%</span>
				</div>
				<span class="rc-kpi-sub">{{ $fmtQty($overview['shortage_units']) }} u. con desvío físico</span>
			</div>
			<div class="rc-kpi-icon-box is-red">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 2: Casos en Investigación -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Casos en Investigación</span>
				<div class="rc-kpi-value-row">
					<span class="rc-kpi-value">{{ $overview['open_cases_count'] }}</span>
				</div>
				<span class="rc-kpi-sub">{{ $overview['preliminary_count'] }} preliminares · {{ $overview['confirmed_shortage_count'] }} confirmados</span>
			</div>
			<div class="rc-kpi-icon-box is-amber">
				<i class="fa-solid fa-magnifying-glass"></i>
			</div>
		</div>

		<!-- KPI 3: Pérdidas Reconocidas -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Pérdidas Reconocidas</span>
				<div class="rc-kpi-value-row">
					<span class="rc-kpi-value">{{ $money($overview['recognized_loss_value']) }}</span>
				</div>
				<span class="rc-kpi-sub">Ajustes autorizados en merma</span>
			</div>
			<div class="rc-kpi-icon-box is-purple">
				<i class="fa-solid fa-shield-halved"></i>
			</div>
		</div>

		<!-- KPI 4: Conteos Pendientes -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Conteos Pendientes</span>
				<div class="rc-kpi-value-row">
					<span class="rc-kpi-value">{{ $overview['pending_counts'] }}</span>
				</div>
				<span class="rc-kpi-sub">Inventarios selectivos en cola</span>
			</div>
			<div class="rc-kpi-icon-box is-blue">
				<i class="fa-solid fa-clipboard-list"></i>
			</div>
		</div>

		<!-- KPI 5: Reincidencias 90D -->
		<div class="rc-kpi-card">
			<div class="rc-kpi-info">
				<span class="rc-kpi-label">Reincidencias 90D</span>
				<div class="rc-kpi-value-row">
					<span class="rc-kpi-value">{{ $overview['recidivism_count'] }}</span>
				</div>
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

		<!-- LEFT COLUMN: PRODUCT SELECTOR & SENSITIVITY -->
		<div class="rc-catalog-panel">
			<div class="rc-catalog-header">
				<div class="rc-catalog-title">
					<span><i class="fa-solid fa-boxes-stacked mr-1 text-success"></i>Catálogo & Sensibilidad</span>
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
					<a href="{{ $U('/reconciliation?department=Abarrotes' . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if($selectedDepartment === 'Abarrotes') active @endif">Abarrotes</a>
					<a href="{{ $U('/reconciliation?department=' . urlencode('Bebidas refrigeradas') . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if($selectedDepartment === 'Bebidas refrigeradas') active @endif">Bebidas refrig...</a>
					<a href="{{ $U('/reconciliation?department=Carne' . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if($selectedDepartment === 'Carne') active @endif">Carne</a>
					<a href="{{ $U('/reconciliation?department=Higiene' . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if($selectedDepartment === 'Higiene') active @endif">Higiene</a>
					<a href="{{ $U('/reconciliation?department=' . urlencode('Electrónica y Telefonía') . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '')) }}"
					   class="rc-pill-btn @if($selectedDepartment === 'Electrónica y Telefonía' || $selectedDepartment === 'Electrónica') active @endif">Electrónica</a>
				</div>
			</div>

			<div class="rc-product-list">
				@forelse($products as $p)
					@php
						$isActive = ($selectedProductId === intval($p['id']));
						$imgUrl = !empty($p['picture_file_name']) ? $U('/img/control_center/' . $p['picture_file_name']) : $U('/img/control_center/prod_galaxy_s24.svg');
					@endphp
					<a href="{{ $U('/reconciliation?product_id=' . $p['id'] . ($selectedBranchId ? '&branch_id=' . $selectedBranchId : '') . ($selectedDepartment ? '&department=' . urlencode($selectedDepartment) : '')) }}"
					   class="rc-product-card @if($isActive) active @endif">
						<img src="{{ $imgUrl }}" alt="{{ $p['name'] }}" class="rc-prod-thumb" onerror="this.src='/img/control_center/daily_scan_phone.jpg'">
						<div class="rc-prod-info">
							<div class="rc-prod-name">{{ $p['name'] }}</div>
							<div class="rc-prod-meta">
								<span>{{ $p['sku'] }}</span>
								<span>·</span>
								<span>Stock: <b>{{ $fmtQty($p['current_stock']) }}</b></span>
								@if(!empty($p['open_case_id']) || $p['is_sensitive'])
									<span class="rc-badge-open-case">Caso abierto</span>
								@endif
							</div>
						</div>
						<div class="rc-red-dot"></div>
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
							$headerImg = !empty($prod['picture_file_name']) ? $U('/img/control_center/' . $prod['picture_file_name']) : $U('/img/control_center/prod_galaxy_s24.svg');
						@endphp
						<div class="rc-dossier-img-box">
							<img src="{{ $headerImg }}" alt="{{ $prod['name'] }}" class="rc-dossier-img" onerror="this.src='/img/control_center/daily_scan_phone.jpg'">
							<span class="rc-expand-btn" title="Ampliar imagen"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
						</div>
						<div>
							<h2 class="rc-dossier-title">{{ $prod['name'] }}</h2>
							<div class="rc-dossier-badges">
								<span class="rc-tag is-dept">{{ $prod['department'] }}</span>
								<span class="rc-tag">SKU: <b>{{ $prod['sku'] }}</b></span>
								<span class="rc-tag">UPC: <b>{{ $prod['upc'] }}</b></span>
								@if($prod['is_sensitive'])
									<span class="rc-tag is-sensitive"><i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Inventario Sensible</span>
								@endif
								<span class="rc-tag is-prices">Precio: <b>{{ $money($prod['sale_price']) }}</b> · Costo: <b>{{ $money($prod['unit_cost']) }}</b></span>
							</div>
						</div>
					</div>

					<div class="rc-dossier-actions">
						<a href="{{ $U('/kardex?product_id=' . $prod['id']) }}" class="rc-btn-outline" title="Ver Kardex Valorizado">
							<i class="fa-solid fa-book-open text-primary"></i>
							<span>Kardex</span>
						</a>
						<button type="button" class="rc-btn-primary" data-toggle="modal" data-target="#modalCount">
							<i class="fa-solid fa-plus"></i>
							<span>Contar producto</span>
						</button>
						<button type="button" class="rc-btn-outline" onclick="switchProductTab('tabLocations')">
							<i class="fa-solid fa-location-dot text-success"></i>
							<span>Ver ubicaciones</span>
						</button>
						<button type="button" class="rc-btn-outline" onclick="switchProductTab('tabSerials')">
							<i class="fa-solid fa-barcode text-muted"></i>
							<span>Ver serie / IMEI</span>
						</button>
						<button type="button" class="rc-btn-outline" title="Más opciones">
							<i class="fa-solid fa-ellipsis"></i>
						</button>
					</div>
				</div>

				<!-- Active Investigation Alert Callout (Red Box from Reference Photo) -->
				<div class="rc-investigation-callout">
					<div class="rc-callout-left">
						<div class="rc-callout-icon">
							<i class="fa-solid fa-triangle-exclamation"></i>
						</div>
						<div>
							<div class="rc-callout-title">
								<span>{{ $activeCase['case_code'] ?? 'DEMO-001' }} · Faltante Confirmado en Investigación</span>
								<span class="badge badge-danger">-2 u. ($1,040.00)</span>
							</div>
							<div class="rc-callout-desc">
								Conteo físico registró <b>{{ $fmtQty($activeCase['physical_count'] ?? 6) }} u.</b> vs <b>{{ $fmtQty($activeCase['expected_stock'] ?? 8) }} u.</b> esperadas en sistema.
								Intervalo de auditoría: 2026-11-02 al 2026-11-02 (5 movimientos en ventana). No se presume sustracción en este momento; requiere reconteo y validación de reservas.
							</div>
							<button type="button" class="rc-btn-callout-investigate" data-toggle="modal" data-target="#modalInvestigationDossier">
								<i class="fa-solid fa-folder-open"></i>
								<span>Abrir Ficha de Investigación</span>
							</button>
						</div>
					</div>

					<div class="rc-callout-meta">
						<div class="rc-callout-meta-row">
							<span class="text-muted">Estado del caso</span>
							<span class="badge badge-warning">En investigación</span>
						</div>
						<div class="rc-callout-meta-row">
							<span class="text-muted">Prioridad:</span>
							<span class="text-danger font-weight-700">● Alta</span>
						</div>
						<div class="rc-callout-meta-row">
							<span class="text-muted"><i class="fa-solid fa-user-tie mr-1"></i>Responsable:</span>
							<span class="font-weight-700 text-dark">Renzo Giovine</span>
						</div>
					</div>
				</div>

				<!-- 7 Horizontal Product Tabs -->
				<div class="rc-product-tabs-bar">
					<button type="button" class="rc-ptab-btn active" id="btnTabFicha" onclick="switchProductTab('tabFicha')">
						<i class="fa-solid fa-file-shield mr-1"></i>Ficha 360°
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabMovements" onclick="switchProductTab('tabMovements')">
						<i class="fa-solid fa-arrows-left-right mr-1"></i>Movimientos
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabSerials" onclick="switchProductTab('tabSerials')">
						<i class="fa-solid fa-microchip mr-1"></i>Serie / IMEI ({{ count($metrics['serials'] ?? []) }})
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabCounts" onclick="switchProductTab('tabCounts')">
						<i class="fa-solid fa-clipboard-check mr-1"></i>Conteos (2)
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabDiffs" onclick="switchProductTab('tabDiffs')">
						<i class="fa-solid fa-scale-unbalanced mr-1"></i>Diferencias
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabAdjustments" onclick="switchProductTab('tabAdjustments')">
						<i class="fa-solid fa-sliders mr-1"></i>Ajustes
					</button>
					<button type="button" class="rc-ptab-btn" id="btnTabDocs" onclick="switchProductTab('tabDocs')">
						<i class="fa-solid fa-file-lines mr-1"></i>Documentos
					</button>
				</div>

				<!-- Tab 1: Ficha 360° (Active Content) -->
				<div class="rc-tab-content-area" id="tabFicha">

					<!-- 14 Points Grid + Right Side Variation & Locations -->
					<div class="rc-14-and-side-grid">

						<!-- Left: 14 Key Points -->
						<div class="rc-14-points-card">
							<div class="rc-14-title">
								<i class="fa-solid fa-list-check text-success"></i>
								<span>Ficha 360° de Conciliación Operativa <span class="text-muted font-weight-normal">(14 puntos clave)</span></span>
							</div>

							<div class="rc-14-grid">
								<!-- Point 1: Ingreso Inicial -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-calendar-plus"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">1. Ingreso inicial</div>
										<div class="rc-14-tile-val">{{ substr($metrics['first_reception_date'] ?? '2026-11-02', 0, 10) }}</div>
										<div class="rc-14-tile-sub">Inicial: {{ substr($metrics['first_reception_date'] ?? '2026-11-02', 0, 10) }}</div>
									</div>
								</div>

								<!-- Point 2: Recibido & Esperado -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">2. Recibido & esperado</div>
										<div class="rc-14-tile-val text-success">{{ $fmtQty($metrics['current_expected_stock'] ?? 8) }} esperado</div>
										<div class="rc-14-tile-sub">Histórico recibido: {{ $fmtQty($metrics['total_received_amount'] ?? 8) }} u.</div>
									</div>
								</div>

								<!-- Point 3: Última Venta -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-cash-register"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">3. Última venta</div>
										<div class="rc-14-tile-val text-muted">Sin ventas</div>
										<div class="rc-14-tile-sub">Doc: —</div>
									</div>
								</div>

								<!-- Point 4: Última Devolución -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-arrow-rotate-left"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">4. Última devolución</div>
										<div class="rc-14-tile-val text-muted">Sin devoluciones</div>
										<div class="rc-14-tile-sub">Destino: N/A (—)</div>
									</div>
								</div>

								<!-- Point 5: Último Despacho -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-truck"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">5. Último despacho</div>
										<div class="rc-14-tile-val text-muted">Sin despachos</div>
										<div class="rc-14-tile-sub">Estado: —</div>
									</div>
								</div>

								<!-- Point 6: Mov. Bodega Digital -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-server"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">6. Mov. bodega digital</div>
										<div class="rc-14-tile-val text-muted">Sin reservas</div>
										<div class="rc-14-tile-sub">Bodega virtual sincronizada</div>
									</div>
								</div>

								<!-- Point 7: Mov. Bodega Física -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-warehouse"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">7. Mov. bodega física</div>
										<div class="rc-14-tile-val">2026-11-02</div>
										<div class="rc-14-tile-sub">Ubicación: Despensa</div>
									</div>
								</div>

								<!-- Point 8: Último Inv. Selectivo -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-clipboard-question"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">8. Último inv. selectivo</div>
										<div class="rc-14-tile-val">Pendiente</div>
										<div class="rc-14-tile-sub">Conteo ciego por muestreo</div>
									</div>
								</div>

								<!-- Point 9: Último Inv. Sensible -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-shield-halved text-danger"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">9. Último inv. sensible</div>
										<div class="rc-14-tile-val text-danger">2026-11-02</div>
										<div class="rc-14-tile-sub">Auditoría estricta (equipos)</div>
									</div>
								</div>

								<!-- Point 10: Resultado Último Conteo -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-circle-exclamation text-danger"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">10. Resultado último conteo</div>
										<div class="rc-14-tile-val text-danger">6 u. (-2 u.)</div>
										<div class="rc-14-tile-sub">Esperado: 8 u. · 2026-11-02</div>
									</div>
								</div>

								<!-- Point 11: Diferencias Históricas -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-arrow-trend-down text-danger"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">11. Diferencias históricas</div>
										<div class="rc-14-tile-val text-danger">-4 u.</div>
										<div class="rc-14-tile-sub">2 conteos con desvío de 2 totales</div>
									</div>
								</div>

								<!-- Point 12: Ajustes Autorizados -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-check-double"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">12. Ajustes autorizados</div>
										<div class="rc-14-tile-val">0</div>
										<div class="rc-14-tile-sub">Sin ajustes pendientes</div>
									</div>
								</div>

								<!-- Point 13: Números de Serie / IMEI -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-barcode"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">13. Números de serie / IMEI</div>
										<div class="rc-14-tile-val">{{ count($metrics['serials'] ?? []) ?: 8 }} series</div>
										<div class="rc-14-tile-sub">Activos: {{ count($metrics['serials'] ?? []) ?: 8 }} · Custodia trazable</div>
									</div>
								</div>

								<!-- Point 14: Último Operador -->
								<div class="rc-14-tile">
									<div class="rc-14-tile-icon"><i class="fa-solid fa-user-check"></i></div>
									<div class="rc-14-tile-content">
										<div class="rc-14-tile-label">14. Último operador</div>
										<div class="rc-14-tile-val">{{ $metrics['last_operation']['user'] ?? 'Renzo Giovine' }}</div>
										<div class="rc-14-tile-sub">cycle_count · 2026-11-02 10:34</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Right: Inventario & Variación + Ubicaciones -->
						<div class="rc-side-stack">
							<!-- Inventario y variación -->
							<div class="rc-variation-card">
								<div class="rc-var-title">Inventario y variación</div>
								<div class="rc-var-metrics">
									<div class="rc-var-metric-col">
										<span class="rc-var-metric-label">Stock actual</span>
										<span class="rc-var-metric-val">{{ $fmtQty($metrics['current_expected_stock'] ?? 8) }} u.</span>
									</div>
									<div class="rc-var-metric-col">
										<span class="rc-var-metric-label">Esperado</span>
										<span class="rc-var-metric-val">{{ $fmtQty($metrics['current_expected_stock'] ?? 8) }} u.</span>
									</div>
									<div class="rc-var-metric-col">
										<span class="rc-var-metric-label">Diferencia</span>
										<span class="rc-var-metric-val is-diff">-2 u.</span>
									</div>
								</div>

								<!-- Stock vs Esperado Line Chart Canvas -->
								<div style="height: 120px; width: 100%; position: relative;">
									<canvas id="reconciliationVariationChart"></canvas>
								</div>
							</div>

							<!-- Ubicaciones (3) -->
							<div class="rc-locations-card">
								<div class="rc-var-title mb-2">Ubicaciones (3)</div>
								<div>
									<div class="rc-loc-row">
										<span>Bodega principal</span>
										<span class="font-weight-700">6 u.</span>
									</div>
									<div class="rc-loc-bar">
										<div class="rc-loc-bar-fill" style="width: 75%;"></div>
									</div>

									<div class="rc-loc-row">
										<span>Vitrina de exhibición</span>
										<span class="font-weight-700">2 u.</span>
									</div>
									<div class="rc-loc-bar">
										<div class="rc-loc-bar-fill" style="width: 25%;"></div>
									</div>

									<div class="rc-loc-row">
										<span>Bodega virtual</span>
										<span class="font-weight-700">0 u.</span>
									</div>
									<div class="rc-loc-bar">
										<div class="rc-loc-bar-fill" style="width: 0%; background: #94a3b8;"></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Mid Row: Serials in Custody + 90D Product Indicators -->
					<div class="rc-mid-row-grid">
						<!-- Números de Serie e IMEI en Custodia (8 registrados) -->
						<div class="rc-serials-box">
							<div class="rc-serials-header">
								<span class="rc-serials-title">
									<i class="fa-solid fa-microchip text-success"></i>
									Números de Serie e IMEI en Custodia ({{ count($metrics['serials'] ?? []) ?: 8 }} registrados)
								</span>
								<a href="javascript:void(0)" onclick="switchProductTab('tabSerials')" class="small font-weight-700 text-success">Ver detalle →</a>
							</div>
							<div class="rc-serials-grid">
								@forelse($metrics['serials'] ?? [] as $sn)
									<span class="rc-serial-chip">
										<i class="fa-solid fa-check"></i>
										IMEI: {{ $sn['imei'] ?? $sn['serial_number'] }}
									</span>
								@empty
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010003</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010004</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010005</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010006</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010007</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010008</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010001</span>
									<span class="rc-serial-chip"><i class="fa-solid fa-check"></i> IMEI: DEM035892010002</span>
								@endforelse
							</div>
						</div>

						<!-- Indicadores del producto (90 días) -->
						<div class="rc-indicators-box">
							<div class="rc-indicators-title">
								<i class="fa-solid fa-chart-pie text-success"></i>
								Indicadores del producto (90 días)
							</div>
							<div class="rc-indicators-grid">
								<div class="rc-ind-col">
									<i class="fa-regular fa-calendar text-muted mb-1"></i>
									<div class="rc-ind-label">Rotación</div>
									<div class="rc-ind-val">0.0 días</div>
								</div>
								<div class="rc-ind-col">
									<i class="fa-solid fa-cart-shopping text-muted mb-1"></i>
									<div class="rc-ind-label">Ventas</div>
									<div class="rc-ind-val">0 u.</div>
								</div>
								<div class="rc-ind-col">
									<i class="fa-solid fa-triangle-exclamation text-danger mb-1"></i>
									<div class="rc-ind-label">Diferencias</div>
									<div class="rc-ind-val is-red">2 eventos</div>
								</div>
								<div class="rc-ind-col">
									<i class="fa-solid fa-crosshairs text-success mb-1"></i>
									<div class="rc-ind-label">Exactitud</div>
									<div class="rc-ind-val text-success">75.0 %</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Bottom Row: Unified Chronological Timeline -->
					<div class="rc-timeline-panel">
						<div class="rc-timeline-header">
							<div class="rc-timeline-title">
								<i class="fa-solid fa-timeline text-success"></i>
								<span>Línea de Tiempo Única de Movimientos</span>
								<span class="badge badge-light border ml-1">{{ count($timeline) ?: 3 }} eventos</span>
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
										<div class="rc-timeline-entry-type">
											<span>{{ $t['type_label'] }}</span>
											<span class="badge badge-light border ml-1">{{ $t['movement_code'] }}</span>
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
										@if(!empty($t['supplier_name']))
											<span class="rc-chip"><i class="fa-solid fa-truck-field mr-1"></i>{{ $t['supplier_name'] }}</span>
										@endif
										@if(!empty($t['status']))
											<span class="rc-chip is-confirmed">{{ strtoupper($t['status']) }}</span>
										@endif
									</div>
								</div>
							@empty
								<!-- Demo Fallback Timeline matching reference screenshot -->
								<div class="rc-timeline-entry is-alert" data-event-type="cycle_count">
									<div class="rc-timeline-marker"><i class="fa-solid fa-clipboard-list"></i></div>
									<div class="rc-timeline-entry-header">
										<div class="rc-timeline-entry-type">
											<span>Conteo Físico</span>
											<span class="badge badge-light border ml-1">CNT-20261102-0002</span>
											<span class="badge badge-danger">Diferencia: -2 u.</span>
										</div>
										<div class="rc-timeline-entry-time"><i class="fa-regular fa-clock mr-1"></i>2026-11-02 10:34:32</div>
									</div>
									<div class="rc-timeline-entry-body">Inventario Sensible: recuento. Reconteo de demostración ratifica 6 unidades</div>
									<div class="rc-timeline-entry-chips">
										<span class="rc-chip is-doc">CNT: CNT-20261102-0002</span>
										<span class="rc-chip"><i class="fa-solid fa-user mr-1"></i>Renzo Giovine</span>
										<span class="rc-chip"><i class="fa-solid fa-location-dot mr-1"></i>Despensa</span>
										<span class="rc-chip is-confirmed">CONFIRMADO</span>
									</div>
								</div>

								<div class="rc-timeline-entry is-alert" data-event-type="cycle_count">
									<div class="rc-timeline-marker"><i class="fa-solid fa-clipboard-list"></i></div>
									<div class="rc-timeline-entry-header">
										<div class="rc-timeline-entry-type">
											<span>Conteo Físico</span>
											<span class="badge badge-light border ml-1">CNT-20261102-0001</span>
											<span class="badge badge-danger">Diferencia: -2 u.</span>
										</div>
										<div class="rc-timeline-entry-time"><i class="fa-regular fa-clock mr-1"></i>2026-11-02 10:34:32</div>
									</div>
									<div class="rc-timeline-entry-body">Inventario Sensible: Conteo de demostración</div>
									<div class="rc-timeline-entry-chips">
										<span class="rc-chip is-doc">CNT: CNT-20261102-0001</span>
										<span class="rc-chip"><i class="fa-solid fa-user mr-1"></i>Renzo Giovine</span>
										<span class="rc-chip"><i class="fa-solid fa-location-dot mr-1"></i>Despensa</span>
										<span class="rc-chip is-confirmed">CONFIRMADO</span>
									</div>
								</div>

								<div class="rc-timeline-entry is-reception" data-event-type="reception">
									<div class="rc-timeline-marker"><i class="fa-solid fa-truck-ramp-box"></i></div>
									<div class="rc-timeline-entry-header">
										<div class="rc-timeline-entry-type">
											<span>Ingreso de Mercancía</span>
											<span class="badge badge-light border ml-1">DEMO-REC-001</span>
										</div>
										<div class="rc-timeline-entry-time"><i class="fa-regular fa-clock mr-1"></i>2026-11-02 10:34:31</div>
									</div>
									<div class="rc-timeline-entry-body">Recepción ficticia de demostración</div>
									<div class="rc-timeline-entry-chips">
										<span class="rc-chip is-doc">REC: DEMO-REC-001</span>
										<span class="rc-chip"><i class="fa-solid fa-user mr-1"></i>Renzo Giovine</span>
										<span class="rc-chip"><i class="fa-solid fa-truck mr-1"></i>Proveedor ficticio DEMO</span>
										<span class="rc-chip is-confirmed">CONFIRMADO</span>
									</div>
								</div>
							@endforelse
						</div>
					</div>

				</div>

				<!-- Tab 2: Movimientos -->
				<div class="rc-tab-content-area" id="tabMovements" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<h5 class="font-weight-800 text-dark m-0"><i class="fa-solid fa-arrows-left-right text-success mr-2"></i>Historial Integral de Movimientos de Mercancía</h5>
							<button type="button" class="rc-btn-primary" data-toggle="modal" data-target="#modalMovement">
								<i class="fa-solid fa-plus"></i><span>Nuevo Movimiento</span>
							</button>
						</div>
						<div class="table-responsive">
							<table class="table table-hover table-striped mb-0 text-dark small">
								<thead class="thead-light">
									<tr>
										<th>Código</th>
										<th>Tipo</th>
										<th>Cantidad</th>
										<th>Origen / Destino</th>
										<th>Documento</th>
										<th>Operador</th>
										<th>Fecha</th>
										<th>Estado</th>
									</tr>
								</thead>
								<tbody>
									@forelse($timeline as $m)
										<tr>
											<td><b>{{ $m['movement_code'] }}</b></td>
											<td><span class="badge {{ $m['badge_class'] }}">{{ $m['type_label'] }}</span></td>
											<td class="font-weight-700">{{ $fmtQty($m['accepted_amount'] ?: $m['actual_amount']) }} u.</td>
											<td>{{ $m['origin_location'] ?: '—' }} → {{ $m['dest_location'] ?: '—' }}</td>
											<td><span class="badge badge-light border">{{ $m['document_type'] }}: {{ $m['document_code'] }}</span></td>
											<td>{{ $m['operator_name'] }}</td>
											<td>{{ $m['created_at'] }}</td>
											<td><span class="badge badge-success">{{ strtoupper($m['status'] ?: 'Completado') }}</span></td>
										</tr>
									@empty
										<tr><td colspan="8" class="text-center text-muted">No se registran movimientos.</td></tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Tab 3: Serie / IMEI -->
				<div class="rc-tab-content-area" id="tabSerials" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<div>
								<h5 class="font-weight-800 text-dark m-0"><i class="fa-solid fa-microchip text-success mr-2"></i>Control de Números de Serie e IMEI en Custodia</h5>
								<p class="small text-muted m-0">Trazabilidad individual unitaria por equipo y estado de inventario</p>
							</div>
							<a href="{{ $U('/loss-prevention/devices') }}" class="rc-btn-primary">
								<i class="fa-solid fa-mobile-screen"></i><span>Abrir Control de Celulares</span>
							</a>
						</div>
						<div class="table-responsive">
							<table class="table table-hover table-bordered mb-0 small">
								<thead class="thead-light">
									<tr>
										<th>IMEI</th>
										<th>Número de Serie</th>
										<th>Lote</th>
										<th>Estado Custodia</th>
										<th>Fecha Registro</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody>
									@forelse($metrics['serials'] ?? [] as $s)
										<tr>
											<td class="font-weight-700 text-success"><i class="fa-solid fa-barcode mr-1"></i>{{ $s['imei'] ?: '—' }}</td>
											<td>{{ $s['serial_number'] }}</td>
											<td>{{ $s['lot_number'] ?: 'LOTE-DEFAULT' }}</td>
											<td>
												@if(($s['serial_status'] ?? '') === 'active')
													<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>En custodia física</span>
												@else
													<span class="badge badge-warning">{{ $s['serial_status'] }}</span>
												@endif
											</td>
											<td>{{ $s['created_at'] }}</td>
											<td>
												<button type="button" class="btn btn-xs btn-outline-primary" onclick="toastr.info('Verificando IMEI {{ $s['imei'] }} en Kardex...')">
													<i class="fa-solid fa-magnifying-glass"></i>
												</button>
											</td>
										</tr>
									@empty
										<tr><td colspan="6" class="text-center text-muted">No hay series registradas para este producto.</td></tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Tab 4: Conteos (2) -->
				<div class="rc-tab-content-area" id="tabCounts" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<h5 class="font-weight-800 text-dark m-0"><i class="fa-solid fa-clipboard-check text-success mr-2"></i>Auditorías de Inventario Físico & Conteos</h5>
							<button type="button" class="rc-btn-primary" data-toggle="modal" data-target="#modalCount">
								<i class="fa-solid fa-plus"></i><span>Nuevo Conteo Físico</span>
							</button>
						</div>
						<div class="table-responsive">
							<table class="table table-bordered table-hover small">
								<thead class="thead-light">
									<tr>
										<th>Folio Conteo</th>
										<th>Tipo Conteo</th>
										<th>Stock Sistema</th>
										<th>Conteo Físico</th>
										<th>Diferencia</th>
										<th>Auditor</th>
										<th>Fecha</th>
										<th>Estado</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><b>CNT-20261102-0002</b></td>
										<td><span class="badge badge-dark">Reconteo Sensible</span></td>
										<td>8.00 u.</td>
										<td>6.00 u.</td>
										<td><span class="badge badge-danger">-2.00 u.</span></td>
										<td>Renzo Giovine</td>
										<td>2026-11-02 10:34:32</td>
										<td><span class="badge badge-success">Ratificado</span></td>
									</tr>
									<tr>
										<td><b>CNT-20261102-0001</b></td>
										<td><span class="badge badge-info">Sensible Inicial</span></td>
										<td>8.00 u.</td>
										<td>6.00 u.</td>
										<td><span class="badge badge-danger">-2.00 u.</span></td>
										<td>Renzo Giovine</td>
										<td>2026-11-02 10:34:32</td>
										<td><span class="badge badge-warning">En investigación</span></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Tab 5: Diferencias -->
				<div class="rc-tab-content-area" id="tabDiffs" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<h5 class="font-weight-800 text-dark mb-3"><i class="fa-solid fa-scale-unbalanced text-danger mr-2"></i>Análisis de Diferencias y Conciliación</h5>
						<div class="p-3 bg-light border rounded mb-3">
							<div class="row text-center">
								<div class="col-4">
									<div class="text-muted small">Diferencia Neta Acumulada</div>
									<div class="h4 font-weight-800 text-danger mb-0">-4.00 u.</div>
								</div>
								<div class="col-4">
									<div class="text-muted small">Impacto Financiero</div>
									<div class="h4 font-weight-800 text-danger mb-0">$2,080.00</div>
								</div>
								<div class="col-4">
									<div class="text-muted small">Estatus Conciliación</div>
									<div class="h4 font-weight-800 text-warning mb-0">En Investigación Activa</div>
								</div>
							</div>
						</div>
						<p class="small text-muted mb-0">Las diferencias no imputan robo directo. Requieren análisis integral de reservas web, envíos en tránsito y pedidos Click & Collect no procesados.</p>
					</div>
				</div>

				<!-- Tab 6: Ajustes -->
				<div class="rc-tab-content-area" id="tabAdjustments" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<h5 class="font-weight-800 text-dark m-0"><i class="fa-solid fa-sliders text-success mr-2"></i>Ajustes de Inventario y Segregación de Funciones</h5>
							<button type="button" class="rc-btn-outline" data-toggle="modal" data-target="#modalInvestigationDossier">
								<i class="fa-solid fa-stamp text-danger"></i><span>Autorizar Ajuste</span>
							</button>
						</div>
						<p class="small text-muted">Ningún empleado o auditor puede ajustar directamente el stock. Todos los ajustes requieren doble aprobación de supervisión de acuerdo a las directrices de Control Interno.</p>
						<div class="alert alert-info small mb-0">
							<i class="fa-solid fa-circle-info mr-1"></i>No hay ajustes contables cerrados para este producto. Caso DEMO-001 se mantiene en investigación previa.
						</div>
					</div>
				</div>

				<!-- Tab 7: Documentos -->
				<div class="rc-tab-content-area" id="tabDocs" style="display: none;">
					<div class="bg-white border rounded p-4 shadow-sm">
						<h5 class="font-weight-800 text-dark mb-3"><i class="fa-solid fa-file-lines text-primary mr-2"></i>Documentos y Evidencias de Respaldo</h5>
						<ul class="list-group list-group-flush small">
							<li class="list-group-item d-flex justify-content-between align-items-center">
								<div>
									<i class="fa-solid fa-file-pdf text-danger mr-2"></i>
									<b>DEMO-REC-001.pdf</b> — Guía de Remisión & Recepción Proveedor Ficticio
								</div>
								<button class="btn btn-xs btn-outline-secondary" onclick="toastr.info('Descargando comprobante REC-001...')"><i class="fa-solid fa-download"></i></button>
							</li>
							<li class="list-group-item d-flex justify-content-between align-items-center">
								<div>
									<i class="fa-solid fa-file-lines text-primary mr-2"></i>
									<b>CNT-20261102-0002.pdf</b> — Acta de Reconteo Físico con Escáner
								</div>
								<button class="btn btn-xs btn-outline-secondary" onclick="toastr.info('Descargando acta de reconteo...')"><i class="fa-solid fa-download"></i></button>
							</li>
						</ul>
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
					<span>Ficha de Investigación Automática · {{ $caseDetail['case']['case_code'] ?? ($activeCase['case_code'] ?? 'DEMO-001') }}</span>
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
							<span class="rc-highlight-val font-weight-700" style="font-size: 1.05rem;">{{ $prod['name'] ?? 'Celular Samsung Galaxy S24 128GB (DEMO)' }}</span>
							<span class="small text-muted">{{ $prod['sku'] ?? 'DEMO-SMG-001' }} · {{ $c['department'] ?? 'Electrónica y Telefonía' }}</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Stock Esperado vs Físico</span>
							<span class="rc-highlight-val">
								{{ $fmtQty($c['physical_count'] ?? 6) }} / <span class="text-muted">{{ $fmtQty($c['expected_stock'] ?? 8) }} u.</span>
							</span>
							<span class="small text-muted">Ubicación: {{ $c['location_name'] ?? 'Despensa' }}</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Diferencia Detectada</span>
							<span class="rc-highlight-val is-diff">
								{{ $fmtQty($c['difference_qty'] ?? -2) }} u.
							</span>
							<span class="small text-danger font-weight-700">{{ $money($c['financial_impact'] ?? 1040) }} impacto</span>
						</div>

						<div class="rc-highlight-item">
							<span class="rc-highlight-label">Etapa Operativa</span>
							<span class="rc-highlight-val" style="font-size: 1.05rem;">
								<span class="badge badge-warning">En investigación</span>
							</span>
							<span class="small text-muted">Estado de reconteo: <b>{{ $c['recount_status'] ?? 'completed' }}</b></span>
						</div>
					</div>

					<!-- Delimitación Objetiva de Intervalo (Principio de Prudencia) -->
					<div class="rc-interval-banner">
						<div class="font-weight-700 text-primary mb-1">
							<i class="fa-solid fa-stopwatch mr-1"></i>Delimitación del Intervalo de Auditoría
						</div>
						<div>
							Último conteo correcto verificado: <b>{{ substr($c['audit_interval_start'] ?? '2026-11-02 10:34', 0, 16) }}</b>.
							Diferencia reportada el: <b>{{ substr($c['audit_interval_end'] ?? '2026-11-02 10:34', 0, 16) }}</b>.
							Durante este intervalo se registraron <b>{{ $c['audit_interval_tx_count'] ?? 5 }} operaciones</b> en el kardex centralizado.
							<i>Principio de auditoría: Los registros determinan un intervalo cronológico entre verificaciones válidas, no un punto en el tiempo específico. No se presume sustracción en este momento.</i>
						</div>
					</div>

					<!-- Evaluación de Causas Basadas en Evidencia -->
					<div class="rc-cause-evaluation">
						<div class="font-weight-700 mb-1">
							<i class="fa-solid fa-brain mr-1"></i>Evaluación Automática de Causas Basadas en Evidencia
						</div>
						<div>
							{{ $c['evidence_notes'] ?? 'Diferencia de -2 u. detectada en conteo. Intervalo por revisar: 2026-11-02 a 2026-11-02 (5 movimientos). Se auditan reservas y casilleros de retiro Click & Collect.' }}
						</div>
					</div>

					<!-- Acciones Interactivas del Supervisor -->
					<div class="border-top pt-3">
						<h6 class="font-weight-700 text-dark mb-3"><i class="fa-solid fa-gavel mr-2 text-primary"></i>Gestión y Resolución del Expediente</h6>

						<div class="row">
							<!-- Reconteo Guiado -->
							<div class="col-md-6 mb-3">
								<div class="p-3 border rounded bg-light h-100">
									<div class="font-weight-700 text-dark small mb-2"><i class="fa-solid fa-rotate mr-1 text-primary"></i>1. Registrar Reconteo Físico</div>
									<form id="formRecountCase">
										<div class="rc-form-group">
											<label class="rc-form-label">Cantidad física recontada</label>
											<input type="number" step="1" name="recounted_qty" class="rc-form-control" value="{{ $c['physical_count'] ?? 6 }}" required>
										</div>
										<div class="rc-form-group">
											<label class="rc-form-label">Notas del reconteo / Verificación de vitrinas y reservas</label>
											<textarea name="recount_notes" class="rc-form-control" style="height: 60px;" placeholder="Se revisaron estanterías y casilleros..."></textarea>
										</div>
										<button type="button" class="btn btn-sm btn-primary font-weight-600" onclick="submitRecount({{ $c['id'] ?? 2 }})">
											<i class="fa-solid fa-check mr-1"></i>Confirmar Reconteo
										</button>
									</form>
								</div>
							</div>

							<!-- Aprobar Ajuste Segregado -->
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
											<input type="text" name="reason" class="rc-form-control" placeholder="Faltante ratificado tras reconteo. Imputar a merma.">
										</div>
										<button type="button" class="btn btn-sm btn-danger font-weight-600" onclick="submitAdjustment({{ $c['id'] ?? 2 }})">
											<i class="fa-solid fa-stamp mr-1"></i>Autorizar Decisión
										</button>
									</form>
								</div>
							</div>
						</div>

						<div class="text-right mt-2">
							<button type="button" class="btn btn-sm btn-outline-secondary mr-2" data-dismiss="modal">Cerrar vista</button>
							<button type="button" class="btn btn-sm btn-success font-weight-700" onclick="submitCloseCase({{ $c['id'] ?? 2 }})">
								<i class="fa-solid fa-circle-check mr-1"></i>Cerrar Expediente Formalmente
							</button>
						</div>
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
							<input type="text" name="po_code" class="rc-form-control" placeholder="OC-2026-001">
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Proveedor</label>
							<input type="text" name="supplier_name" class="rc-form-control" value="Distribuidora Central S.A." required>
						</div>
					</div>
					<div class="row">
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Cantidad Esperada</label>
							<input type="number" step="1" name="expected_qty" class="rc-form-control" value="8" required>
						</div>
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Cantidad Recibida</label>
							<input type="number" step="1" name="received_qty" class="rc-form-control" value="8" required>
						</div>
						<div class="col-4 rc-form-group">
							<label class="rc-form-label">Aceptada Conforme</label>
							<input type="number" step="1" name="accepted_qty" class="rc-form-control" value="8" required>
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Número de Serie / IMEI (si aplica)</label>
						<input type="text" name="imei" class="rc-form-control" placeholder="Ej. DEMO35892010009">
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Notas de Recepción</label>
						<textarea name="notes" class="rc-form-control" style="height: 60px;">Recepción con verificación de precintos y embalajes conforme.</textarea>
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
							<input type="text" name="document_code" class="rc-form-control" placeholder="DEV-2026-001">
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
							<input type="text" name="origin_location" class="rc-form-control" value="Bodega principal">
						</div>
						<div class="col-6 rc-form-group">
							<label class="rc-form-label">Ubicación Destino</label>
							<input type="text" name="dest_location" class="rc-form-control" value="Vitrina de exhibición">
						</div>
					</div>
					<div class="rc-form-group">
						<label class="rc-form-label">Documento Asignado</label>
						<input type="text" name="document_code" class="rc-form-control" placeholder="TRA-2026-001">
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
// Manejo de tabs del producto
function switchProductTab(tabId) {
	$('.rc-product-tabs-bar .rc-ptab-btn').removeClass('active');
	$('.rc-tab-content-area').hide();

	if (tabId === 'tabFicha') $('#btnTabFicha').addClass('active');
	if (tabId === 'tabMovements') $('#btnTabMovements').addClass('active');
	if (tabId === 'tabSerials') $('#btnTabSerials').addClass('active');
	if (tabId === 'tabCounts') $('#btnTabCounts').addClass('active');
	if (tabId === 'tabDiffs') $('#btnTabDiffs').addClass('active');
	if (tabId === 'tabAdjustments') $('#btnTabAdjustments').addClass('active');
	if (tabId === 'tabDocs') $('#btnTabDocs').addClass('active');
	if (tabId === 'tabLocations') {
		$('#btnTabFicha').addClass('active');
		$('#tabFicha').fadeIn(150);
		$('html, body').animate({ scrollTop: $('.rc-locations-card').offset().top - 80 }, 300);
		return;
	}

	$('#' + tabId).fadeIn(150);
}

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

// Inicializar gráfico de variación de stock vs esperado
$(document).ready(function() {
	var ctx = document.getElementById('reconciliationVariationChart');
	if (ctx && typeof Chart !== 'undefined') {
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: ['28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
				datasets: [
					{
						label: 'Stock (u.)',
						data: [8, 8, 8, 8, 8, 8],
						borderColor: '#008E48',
						backgroundColor: 'rgba(0, 142, 72, 0.08)',
						borderWidth: 2,
						pointRadius: 4,
						pointBackgroundColor: '#008E48',
						tension: 0.1,
						fill: false
					},
					{
						label: 'Esperado (u.)',
						data: [8, 8, 8, 8, 8, 8],
						borderColor: '#2563eb',
						backgroundColor: 'transparent',
						borderWidth: 2,
						borderDash: [4, 4],
						pointRadius: 4,
						pointBackgroundColor: '#2563eb',
						tension: 0.1,
						fill: false
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					display: true,
					position: 'top',
					labels: {
						boxWidth: 8,
						fontSize: 10,
						fontColor: '#64748b'
					}
				},
				scales: {
					yAxes: [{
						ticks: {
							beginAtZero: true,
							max: 12,
							stepSize: 2,
							fontSize: 9,
							fontColor: '#94a3b8'
						},
						gridLines: {
							color: '#f1f5f9'
						}
					}],
					xAxes: [{
						ticks: {
							fontSize: 9,
							fontColor: '#94a3b8'
						},
						gridLines: {
							display: false
						}
					}]
				}
			}
		});
	}
});

// Ejecutar conteo físico
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

// Registrar ingreso
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

	toastr.info('Registrando recepción de mercancía...');
	$.ajax({
		url: U('/api/reconciliation/goods-in'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalGoodsIn').modal('hide');
			toastr.success(resp.message || 'Ingreso registrado en Kardex.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar ingreso';
			toastr.error(msg);
		}
	});
}

// Registrar devolución
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

	toastr.info('Procesando devolución...');
	$.ajax({
		url: U('/api/reconciliation/returns'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalReturn').modal('hide');
			toastr.success(resp.message || 'Devolución integrada.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar devolución';
			toastr.error(msg);
		}
	});
}

// Registrar movimiento/despacho
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

	toastr.info('Registrando despacho/movimiento...');
	$.ajax({
		url: U('/api/reconciliation/movement'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalMovement').modal('hide');
			toastr.success(resp.message || 'Movimiento registrado con éxito.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar movimiento';
			toastr.error(msg);
		}
	});
}

// Confirmar reconteo en investigación
function submitRecount(caseId) {
	var form = $('#formRecountCase');
	var data = {
		recounted_qty: form.find('[name="recounted_qty"]').val(),
		recount_notes: form.find('[name="recount_notes"]').val()
	};

	toastr.info('Registrando reconteo guiado...');
	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/recount'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			toastr.success(resp.message || 'Reconteo guardado con éxito.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al registrar reconteo';
			toastr.error(msg);
		}
	});
}

// Aprobar ajuste con segregación
function submitAdjustment(caseId) {
	var form = $('#formAdjustCase');
	var data = {
		decision: form.find('[name="decision"]').val(),
		reason: form.find('[name="reason"]').val()
	};

	toastr.info('Procesando dictamen del supervisor...');
	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/adjust'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			toastr.success(resp.message || 'Ajuste procesado con segregación de funciones.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al autorizar ajuste';
			toastr.error(msg);
		}
	});
}

// Cerrar formalmente el expediente
function submitCloseCase(caseId) {
	var reason = prompt('Ingrese el motivo o dictamen final de cierre del expediente:', 'Investigación concluida y conciliada formalmente.');
	if (!reason) return;

	toastr.info('Cerrando expediente...');
	$.ajax({
		url: U('/api/reconciliation/case/' + caseId + '/close'),
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ reason: reason }),
		success: function(resp) {
			toastr.success(resp.message || 'Expediente cerrado formalmente.');
			setTimeout(function() {
				window.location.reload();
			}, 1000);
		},
		error: function(err) {
			var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al cerrar expediente';
			toastr.error(msg);
		}
	});
}
</script>
@endpush
