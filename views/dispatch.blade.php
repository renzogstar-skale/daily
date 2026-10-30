@extends('layout.default')

@section('title', 'Despachos y Validaciones | DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_dispatch.css?v=', true) }}{{ time() }}" rel="stylesheet">
@endpush

@section('content')
<link href="{{ $U('/css/daily_dispatch.css?v=', true) }}{{ time() }}" rel="stylesheet">
@php
	$money = fn($v) => $v === null ? 'Sin información' : '$' . number_format(floatval($v), 2);
	$prepAcc = $analytics['pickup']['prep_accuracy_pct'] ?? null;
	$delivAcc = $analytics['delivery']['delivery_accuracy_pct'] ?? null;
	$missing1k = $analytics['pickup']['missing_per_thousand'] ?? null;
	$finVar = $analytics['delivery']['financial_variance'] ?? null;
@endphp

<div class="dp-page-container">

	<!-- ================= 1. ENCABEZADO HERO ================= -->
	<div class="dp-hero-card">
		<div class="dp-hero-bg"></div>
		
		<div class="dp-hero-left">
			<div class="dp-hero-icon">
				<i class="fa-solid fa-truck-ramp-box"></i>
			</div>
			<div>
				<h1 class="dp-hero-title">Despachos y Validaciones</h1>
				<p class="dp-hero-desc">Controla preparación, entrega y diferencias de cada pedido.</p>
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

			<button type="button" class="dp-btn-cyan" data-toggle="modal" data-target="#modalComparativeAnalytics">
				<i class="fa-solid fa-chart-column"></i>
				<span>Analítica Retiro vs Domicilio</span>
			</button>

			<button type="button" class="dp-btn-green" data-toggle="modal" data-target="#modalFastScanVerify">
				<i class="fa-solid fa-barcode"></i>
				<span>Escaneo Rápido UPC</span>
			</button>

			<a href="{{ $U('/returns') }}" class="dp-btn-outline">
				<i class="fa-solid fa-rotate-left"></i>
				<span>Cambios y Devoluciones</span>
			</a>
		</div>
	</div>

	<!-- ================= 2. 4 KPIS COMPACTOS ================= -->
	<div class="dp-kpi-grid">
		<!-- KPI 1: Exactitud Preparación -->
		<div class="dp-kpi-card kpi-green">
			<div>
				<div class="dp-kpi-label">Exactitud Preparación</div>
				<div class="dp-kpi-val">{{ $prepAcc === null ? 'Sin información' : number_format($prepAcc, 1) . '%' }}</div>
				<div class="dp-kpi-sub text-success">
					<i class="fa-solid fa-circle-check"></i>
					<span>Meta operativa: ≥ 99.0%</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-clipboard-check"></i>
			</div>
		</div>

		<!-- KPI 2: Exactitud Entrega -->
		<div class="dp-kpi-card kpi-blue">
			<div>
				<div class="dp-kpi-label">Exactitud Entrega</div>
				<div class="dp-kpi-val">{{ $delivAcc === null ? 'Sin información' : number_format($delivAcc, 1) . '%' }}</div>
				<div class="dp-kpi-sub">
					<span>Auditoría en ruta y cliente</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-truck-fast"></i>
			</div>
		</div>

		<!-- KPI 3: Faltantes por 1.000 un. -->
		<div class="dp-kpi-card kpi-amber">
			<div>
				<div class="dp-kpi-label">Faltantes por 1.000 un.</div>
				<div class="dp-kpi-val">{{ $missing1k === null ? 'Sin información' : number_format($missing1k, 1) . ' un.' }}</div>
				<div class="dp-kpi-sub text-warning font-weight-bold">
					<span>Control de picking y ruta</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
		</div>

		<!-- KPI 4: Variación Monetaria -->
		<div class="dp-kpi-card kpi-red">
			<div>
				<div class="dp-kpi-label">Variación Monetaria</div>
				<div class="dp-kpi-val text-red">{{ $money($finVar) }}</div>
				<div class="dp-kpi-sub text-danger font-weight-bold">
					<span>Sin doble contabilización</span>
				</div>
			</div>
			<div class="dp-kpi-icon-box">
				<i class="fa-solid fa-circle-exclamation"></i>
			</div>
		</div>
	</div>

	<!-- ================= 3. CONTENIDO PRINCIPAL EN 2 COLUMNAS ================= -->
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

							<div class="dp-order-branch">{{ $ord['branch_name'] ?: 'Sin sucursal' }}</div>

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
							<span>Conciliación por producto</span>
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
								<strong>Sin incidencias registradas no equivale a conciliación validada.</strong>
								<div class="text-muted small">Revisa las diferencias aunque no haya una incidencia registrada.</div>
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

	<!-- ================= 4. 3 TARJETAS OPERATIVAS INFERIORES ================= -->
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
								<th>Indicador Operativo</th>
								<th class="text-center font-weight-bold text-success"><i class="fa-solid fa-store mr-1"></i>Retiro en Tienda</th>
								<th class="text-center font-weight-bold text-primary"><i class="fa-solid fa-truck mr-1"></i>Despacho a Domicilio</th>
								<th class="text-center">Brecha</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-600">Pedidos Procesados</td>
								<td class="text-center">{{ $analytics['pickup']['orders_count'] }} pedidos</td>
								<td class="text-center">{{ $analytics['delivery']['orders_count'] }} pedidos</td>
								<td class="text-center text-muted">—</td>
							</tr>
							<tr>
								<td class="font-weight-600">Exactitud de Preparación (Picking)</td>
								<td class="text-center font-weight-bold text-success">{{ $analytics['pickup']['prep_accuracy_pct'] }}%</td>
								<td class="text-center font-weight-bold text-primary">{{ $analytics['delivery']['prep_accuracy_pct'] }}%</td>
								<td class="text-center text-success">+{{ round($analytics['pickup']['prep_accuracy_pct'] - $analytics['delivery']['prep_accuracy_pct'], 1) }}%</td>
							</tr>
							<tr>
								<td class="font-weight-600">Exactitud de Entrega al Cliente</td>
								<td class="text-center font-weight-bold text-success">{{ $analytics['pickup']['delivery_accuracy_pct'] }}%</td>
								<td class="text-center font-weight-bold text-warning">{{ $analytics['delivery']['delivery_accuracy_pct'] }}%</td>
								<td class="text-center text-success">+{{ round($analytics['pickup']['delivery_accuracy_pct'] - $analytics['delivery']['delivery_accuracy_pct'], 1) }}%</td>
							</tr>
							<tr>
								<td class="font-weight-600">Faltantes por 1.000 unidades</td>
								<td class="text-center font-weight-bold">{{ $analytics['pickup']['missing_per_thousand'] }} un.</td>
								<td class="text-center font-weight-bold text-danger">{{ $analytics['delivery']['missing_per_thousand'] }} un.</td>
								<td class="text-center text-danger">+{{ round($analytics['delivery']['missing_per_thousand'] - $analytics['pickup']['missing_per_thousand'], 1) }} en ruta</td>
							</tr>
							<tr>
								<td class="font-weight-600">Variación Monetaria Acumulada</td>
								<td class="text-center font-weight-bold text-success">{{ $money($analytics['pickup']['financial_variance']) }}</td>
								<td class="text-center font-weight-bold text-danger">{{ $money($analytics['delivery']['financial_variance']) }}</td>
								<td class="text-center text-danger">{{ $money($analytics['delivery']['financial_variance'] - $analytics['pickup']['financial_variance']) }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Tiempo Promedio de Validación</td>
								<td class="text-center">{{ $analytics['pickup']['avg_validation_minutes'] }} min</td>
								<td class="text-center">{{ $analytics['delivery']['avg_validation_minutes'] }} min</td>
								<td class="text-center text-muted">+5.3 min</td>
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

<!-- ================= MODAL: VALIDAR RETIRO EN TIENDA CON PIN SEGURO (SIN EXPONER PIN EN BOTÓN) ================= -->
<div class="modal fade" id="modalVerifyPickup" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header text-white" style="background: #059669;">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-key mr-2"></i>Validar Retiro en Tienda</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="card p-3 border mb-3 bg-white text-center">
					<label class="font-weight-bold text-dark mb-1">Ingrese el código PIN de 6 dígitos provisto por el cliente:</label>
					<span class="text-muted small d-block mb-2">Por seguridad, el PIN debe ser digitado directamente o escaneado desde el comprobante móvil del cliente.</span>
					<input type="password" id="inputPickupPinCode" class="form-control text-center font-weight-bold text-dark mt-2" 
						   placeholder="••••••" style="font-size: 2rem; letter-spacing: 0.35em; height: 58px;" maxlength="6" autofocus>
				</div>
				<div class="form-group mb-2">
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="chkPickupTempOk" checked>
						<label class="custom-control-label small font-weight-bold" for="chkPickupTempOk">Temperatura de cadena de frío conforme (≤ 4°C)</label>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSubmitVerifyPickup">
					<i class="fa-solid fa-circle-check mr-1"></i>Confirmar Entrega Segura
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ================= MODAL: REGISTRAR ENTREGA DE RUTA A DOMICILIO ================= -->
<div class="modal fade" id="modalRecordDelivery" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-truck mr-2"></i>Registrar Entrega de Ruta a Domicilio</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<form id="formRecordDelivery">
					<div class="form-group mb-2">
						<label class="font-weight-bold small text-dark">Transportista / Conductor:</label>
						<input type="text" class="form-control form-control-sm" id="inputCarrierName" value="Transportes Andinos">
					</div>
					<div class="form-group mb-2">
						<label class="font-weight-bold small text-dark">Persona que recibe en destino:</label>
						<input type="text" class="form-control form-control-sm" id="inputDeliverySignature" placeholder="Nombre completo de quien firma">
					</div>
					<div class="form-group mb-2">
						<label class="font-weight-bold small text-dark">Incidencias en ruta (si hubo):</label>
						<textarea class="form-control form-control-sm" id="inputRouteIncidents" rows="2" placeholder="Demoras, daño en bultos, etc."></textarea>
					</div>
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="chkDeliveryTempOk" checked>
						<label class="custom-control-label small font-weight-bold" for="chkDeliveryTempOk">Cadena de frío en ruta preservada conforme</label>
					</div>
				</form>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnSubmitRecordDelivery">
					<i class="fa-solid fa-floppy-disk mr-1"></i>Guardar Conciliación
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
						<span class="font-weight-bold small" id="lblEvidenceReceiver">Representante autorizado de Cafetería Central Gourmet</span>
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

<script>
window.CurrentOrderId = {{ $selectedOrderId ?: 0 }};
window.DispatchOrdersMap = {!! $ordersDetailMapJson !!};
</script>
@endsection

@push('pageScripts')
<script src="{{ $U('/viewjs/dispatch.js?v=', true) }}{{ time() }}"></script>
@endpush
