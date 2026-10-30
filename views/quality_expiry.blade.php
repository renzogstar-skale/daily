@extends('layout.default')

@section('title', 'Calidad & Vencimientos | DAILY')

@section('content')

@php
	$criticalCount = count(array_filter($batches, function($b) { return $b['risk_level'] === 'critical' || $b['risk_level'] === 'expired'; }));
	$warningCount = count(array_filter($batches, function($b) { return $b['risk_level'] === 'warning'; }));
	$totalRiskValue = 0.0;
	foreach ($batches as $b) {
		if ($b['risk_level'] === 'critical' || $b['risk_level'] === 'expired') {
			$totalRiskValue += floatval($b['total_value']);
		}
	}
	$coldTotal = count($coldChain);
	$coldAlerts = count(array_filter($coldChain, function($c) { return in_array($c['eval_class'], ['danger', 'warning']); }));
	$inspApproved = count(array_filter($inspections, function($i) { return ($i['result'] ?? '') === 'approved'; }));
	$inspRate = count($inspections) > 0 ? round($inspApproved / count($inspections) * 100, 1) : 0;
	$canInspect = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_QUALITY_INSPECT]);
	$canQuarantine = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_QUALITY_QUARANTINE]);
@endphp

<div class="quality-page-wrapper">

	<!-- 1. ENCABEZADO CON BANNER FOTOGRÁFICO -->
	<div class="card border-0 shadow-sm quality-header-card mb-3">
		<div class="quality-header-bg"></div>
		<div class="quality-header-content d-flex flex-column flex-md-row justify-content-between align-items-md-center position-relative">
			<div class="d-flex align-items-center mb-3 mb-md-0">
				<div class="quality-header-icon mr-3">
					<i class="fa-solid fa-cube"></i>
				</div>
				<div>
					<h3 class="font-weight-bold mb-1 text-slate-900 tracking-tight">Calidad & Vencimientos</h3>
					<p class="text-muted mb-0 small">Control de lotes, cadena de frío y calidad.</p>
				</div>
			</div>
			<div class="quality-header-actions d-flex flex-wrap gap-2 align-items-center">
				<button class="btn btn-daily-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modal-temp-form" @if(!$canInspect) disabled title="Sin permiso" @endif>
					<i class="fa-solid fa-temperature-half mr-1"></i>Registrar temperatura
				</button>
				<button class="btn btn-daily-outline-green font-weight-bold shadow-sm ml-2" data-toggle="modal" data-target="#modal-inspection-form" @if(!$canInspect) disabled title="Sin permiso" @endif>
					<i class="fa-solid fa-clipboard-check mr-1"></i>Inspección de calidad
				</button>
				<button class="btn btn-daily-outline-amber font-weight-bold shadow-sm ml-2" data-toggle="modal" data-target="#modal-quarantine-form" @if(!$canQuarantine) disabled title="Sin permiso" @endif>
					<i class="fa-solid fa-triangle-exclamation mr-1"></i>Aislar lote
				</button>
				<div class="dropdown d-inline-block ml-2">
					<button class="btn btn-white-border font-weight-bold shadow-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="fa-solid fa-download mr-1 text-muted"></i>Exportar
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow border-0">
						<a class="dropdown-item py-2" href="#" onclick="DairyQuality.ExportCSV(event)">
							<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar Lotes (CSV)
						</a>
						<a class="dropdown-item py-2" href="#" onclick="DairyQuality.ExportColdChain(event)">
							<i class="fa-solid fa-file-excel text-primary mr-2"></i>Exportar Cadena de Frío
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 2. FRANJA COMPACTA DE ALERTAS Y FILTRO DE SUCURSAL -->
	<div class="card border-0 shadow-sm quality-alert-bar mb-3">
		<div class="card-body py-2 px-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
			<div class="d-flex align-items-center text-dark small mb-2 mb-md-0 font-weight-medium">
				<i class="fa-solid fa-triangle-exclamation text-danger mr-2 fa-lg"></i>
				<span>
					Alertas activas del módulo:
					<strong class="text-danger ml-1">{{ $alertCounts['all'] ?: $criticalCount }} críticas</strong> ·
					<strong class="text-dark">{{ $alertCounts['overdue'] ?: 1 }} vencida</strong> ·
					<strong class="text-dark">{{ $alertCounts['unassigned'] ?: $criticalCount }} sin responsable</strong>
				</span>
			</div>
			<div class="d-flex align-items-center">
				<form method="GET" action="{{ $U('/quality') }}" class="form-inline mr-3">
					<span class="small text-muted font-weight-bold mr-2">Sucursal</span>
					<select name="branch_id" class="custom-select custom-select-sm branch-filter-select" onchange="this.form.submit()">
						<option value="">Todas las sucursales</option>
						@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
						@endforeach
					</select>
				</form>
				<a href="{{ $U('/alerts') }}?category=quality" class="small font-weight-bold text-danger text-nowrap hover-underline">
					Ver alertas <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>
		</div>
	</div>

	<!-- 3. RESUMEN SUPERIOR: 6 TARJETAS COMPACTAS -->
	<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4 kpi-top-row">
		<!-- KPI 1: Riesgo Crítico -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-red-soft text-danger mr-2">
							<i class="fa-solid fa-triangle-exclamation"></i>
						</div>
						<span class="kpi-label">Riesgo crítico</span>
					</div>
					<div class="kpi-value text-danger font-weight-bold">
						${{ number_format($totalRiskValue, 2) }}
					</div>
					<div class="kpi-sub text-muted small mt-1">
						{{ $criticalCount }} lotes · ≤ 5 días o vencidos
					</div>
				</div>
			</div>
		</div>

		<!-- KPI 2: Lotes en Vigilancia -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-amber-soft text-amber mr-2">
							<i class="fa-solid fa-hourglass-half"></i>
						</div>
						<span class="kpi-label">Lotes en vigilancia</span>
					</div>
					<div class="kpi-value text-dark font-weight-bold">
						{{ $warningCount }}
					</div>
					<div class="kpi-sub text-muted small mt-1">
						6 a 15 días
					</div>
				</div>
			</div>
		</div>

		<!-- KPI 3: Cámaras de Frío -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-blue-soft text-primary mr-2">
							<i class="fa-solid fa-snowflake"></i>
						</div>
						<span class="kpi-label">Cámaras de frío</span>
					</div>
					<div class="kpi-value text-dark font-weight-bold">
						{{ $coldTotal - $coldAlerts }} / {{ $coldTotal }}
					</div>
					<div class="kpi-sub text-muted small mt-1">
						en rango
					</div>
				</div>
			</div>
		</div>

		<!-- KPI 4: Alertas Térmicas -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-red-soft text-danger mr-2">
							<i class="fa-solid fa-temperature-high"></i>
						</div>
						<span class="kpi-label">Alertas térmicas</span>
					</div>
					<div class="kpi-value font-weight-bold @if($coldAlerts > 0) text-danger @else text-dark @endif">
						{{ $coldAlerts }}
					</div>
					<div class="kpi-sub text-muted small mt-1">
						{{ $coldAlerts > 0 ? 'cámaras fuera de rango' : 'sin desviaciones' }}
					</div>
				</div>
			</div>
		</div>

		<!-- KPI 5: En Cuarentena -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-amber-soft text-amber mr-2">
							<i class="fa-solid fa-biohazard"></i>
						</div>
						<span class="kpi-label">En cuarentena</span>
					</div>
					<div class="kpi-value text-dark font-weight-bold">
						{{ count($quarantineItems) }}
					</div>
					<div class="kpi-sub text-muted small mt-1">
						lotes fuera de venta
					</div>
				</div>
			</div>
		</div>

		<!-- KPI 6: Inspecciones OK -->
		<div class="col mb-3">
			<div class="card h-100 border-0 shadow-sm kpi-card">
				<div class="card-body p-3">
					<div class="d-flex align-items-center mb-2">
						<div class="kpi-icon-pill bg-green-soft text-success mr-2">
							<i class="fa-solid fa-clipboard-check"></i>
						</div>
						<span class="kpi-label">Inspecciones OK</span>
					</div>
					<div class="kpi-value text-success font-weight-bold">
						@if(count($inspections) > 0) {{ $inspRate }}% @else Sin datos @endif
					</div>
					<div class="kpi-sub text-muted small mt-1">
						@if(count($inspections) > 0) {{ $inspApproved }} de {{ count($inspections) }} auditorías @else Sin inspecciones registradas @endif
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 4. PESTAÑAS DE NAVEGACIÓN INTERNA -->
	<div class="quality-tabs-container mb-3">
		<ul class="nav nav-pills quality-nav-pills bg-white shadow-sm rounded-lg p-1 border" id="qualityTabs" role="tablist">
			<li class="nav-item">
				<a class="nav-link active font-weight-bold" id="tab-expiry-link" data-toggle="pill" href="#tab-expiry" role="tab" aria-selected="true">
					<i class="fa-solid fa-table-list mr-2"></i>Lotes y caducidades
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link font-weight-bold" id="tab-cold-link" data-toggle="pill" href="#tab-cold" role="tab" aria-selected="false">
					<i class="fa-solid fa-temperature-low mr-2"></i>Cadena de frío
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link font-weight-bold" id="tab-quarantine-link" data-toggle="pill" href="#tab-quarantine" role="tab" aria-selected="false">
					<i class="fa-solid fa-biohazard mr-2"></i>Cuarentena
					@if(count($quarantineItems) > 0)
					<span class="badge badge-amber ml-1">{{ count($quarantineItems) }}</span>
					@endif
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link font-weight-bold" id="tab-inspections-link" data-toggle="pill" href="#tab-inspections" role="tab" aria-selected="false">
					<i class="fa-solid fa-microscope mr-2"></i>Inspecciones
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link font-weight-bold" id="tab-traceability-link" data-toggle="pill" href="#tab-traceability" role="tab" aria-selected="false">
					<i class="fa-solid fa-chart-line mr-2"></i>Trazabilidad 360°
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link font-weight-bold text-danger" id="tab-predictive-link" data-toggle="pill" href="#tab-predictive" role="tab" aria-selected="false">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i>Prevención Predictiva & FEFO
					<span class="badge badge-danger ml-1" id="predictiveAlertsCountBadge">{{ count($predictiveAlerts ?? []) }}</span>
				</a>
			</li>
		</ul>
	</div>

	<!-- 5. CONTENIDO DE PESTAÑAS -->
	<div class="tab-content" id="qualityTabContent">

		<!-- TAB 1: LOTES Y CADUCIDADES (2 COLUMNAS) -->
		<div class="tab-pane fade show active" id="tab-expiry" role="tabpanel">
			<div class="row">
				<!-- COLUMNA PRINCIPAL (IZQUIERDA ~70%) -->
				<div class="col-lg-8 mb-4">

					<!-- SECCIÓN: LOTES QUE REQUIEREN ATENCIÓN -->
					<div class="d-flex align-items-center mb-3">
						<div class="icon-square-sm bg-green-soft text-success mr-2">
							<i class="fa-solid fa-arrow-trend-up"></i>
						</div>
						<h5 class="font-weight-bold text-dark mb-0">Lotes que requieren atención</h5>
					</div>

					<div class="row row-cols-1 row-cols-sm-3 g-3 mb-4 attention-cards-row">
						@forelse($attentionBatches as $ab)
						<div class="col mb-3">
							<div class="card h-100 border-0 shadow-sm attention-card cursor-pointer" onclick="DairyQuality.OpenBatchDetailModal({{ json_encode($ab) }})">
								<div class="attention-card-img-wrapper position-relative">
									<img src="{{ $U($ab['card_image'] ?: '/img/control_center/prod_leche_galon.jpg') }}" alt="{{ $ab['product_name'] }}" class="attention-card-img">
									<div class="attention-card-badge-overlay">
										@if($ab['risk_level'] === 'expired')
										<span class="badge badge-pill badge-danger-soft">
											<i class="fa-solid fa-triangle-exclamation mr-1"></i>Vencido
										</span>
										@else
										<span class="badge badge-pill badge-amber-soft">
											<i class="fa-solid fa-triangle-exclamation mr-1"></i>Crítico
										</span>
										@endif
									</div>
								</div>
								<div class="card-body p-3">
									<h6 class="font-weight-bold text-dark text-truncate mb-1" title="{{ $ab['product_name'] }}">
										{{ $ab['product_name'] }}
									</h6>
									<div class="small text-muted mb-2">
										{{ $ab['amount'] }} {{ strtolower($ab['qu_name']) }} · {{ $ab['sku'] ?: 'ID #' . $ab['product_id'] }}
									</div>
									<div class="d-flex justify-content-between align-items-center mt-2">
										<div class="font-weight-bold text-danger attention-price">
											${{ number_format($ab['total_value'], 2) }}
										</div>
										<button class="btn btn-sm btn-light py-0 px-2 text-muted" title="Ver detalle del lote">
											<i class="fa-solid fa-eye"></i>
										</button>
									</div>
								</div>
							</div>
						</div>
						@empty
						<div class="col-12">
							<div class="alert alert-light border text-center text-muted py-3">
								<i class="fa-solid fa-circle-check text-success mr-2"></i>No hay lotes con alerta crítica en este momento.
							</div>
						</div>
						@endforelse
					</div>

					<!-- SECCIÓN: MATRIZ DE LOTES Y CADUCIDADES -->
					<div class="card border-0 shadow-sm matrix-card">
						<div class="card-header bg-white py-3 border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
							<div class="d-flex align-items-center mb-2 mb-md-0">
								<div class="icon-square-sm bg-green-soft text-success mr-2">
									<i class="fa-solid fa-table-cells-large"></i>
								</div>
								<h5 class="font-weight-bold text-dark mb-0">Matriz de lotes y caducidades</h5>
							</div>
							<div class="d-flex flex-wrap align-items-center gap-2">
								<div class="search-box-wrap mr-2">
									<i class="fa-solid fa-search search-icon text-muted"></i>
									<input type="text" id="batch-search-input" class="form-control form-control-sm search-input" placeholder="Buscar lote o producto..." onkeyup="DairyQuality.FilterBatches()">
								</div>
								<select id="batch-risk-filter" class="custom-select custom-select-sm risk-filter-select" onchange="DairyQuality.FilterBatches()">
									<option value="all">Todos los riesgos</option>
									<option value="critical">Crítico / Vencido (≤ 5 d)</option>
									<option value="warning">Próximo (6 a 15 d)</option>
									<option value="optimal">Óptimo (> 15 d)</option>
								</select>
							</div>
						</div>

						<div class="card-body p-0">
							<div class="table-responsive">
								<table class="table table-hover align-middle mb-0 matrix-table" id="batches-table">
									<thead>
										<tr>
											<th>PRODUCTO / LOTE</th>
											<th>UBICACIÓN</th>
											<th>EXISTENCIAS</th>
											<th>INGRESO</th>
											<th>VENCIMIENTO</th>
											<th>ESTADO</th>
											<th class="text-right">VALOR EN RIESGO</th>
											<th class="text-right">ACCIONES</th>
										</tr>
									</thead>
									<tbody id="batches-table-body">
										@forelse($batches as $index => $b)
										<tr class="batch-row @if($index >= 12) batch-row-overflow d-none @endif status-border-{{ $b['risk_level'] }}" 
											data-name="{{ strtolower($b['product_name'] . ' ' . $b['sku'] . ' ' . $b['stock_id']) }}" 
											data-risk="{{ $b['risk_level'] }}">
											<td>
												<div class="font-weight-bold text-dark product-row-name">{{ $b['product_name'] }}</div>
												<div class="small text-muted font-monospace">{{ $b['sku'] ?: 'ID #' . $b['product_id'] }} · {{ $b['stock_id'] }}</div>
											</td>
											<td>
												<div class="text-dark small d-flex align-items-center">
													<i class="fa-solid @if($b['is_freezer']) fa-snowflake text-info @else fa-temperature-low text-primary @endif mr-1"></i>
													<span>{{ $b['location_name'] }}</span>
												</div>
												<small class="text-muted">{{ $b['branch_name'] }}</small>
											</td>
											<td class="font-weight-bold text-dark text-nowrap">
												{{ $b['amount'] }} {{ $b['qu_name'] }}
											</td>
											<td class="small text-muted text-nowrap">
												{{ $b['purchased_date'] }}
											</td>
											<td class="font-weight-bold text-dark text-nowrap">
												{{ $b['best_before_date'] }}
											</td>
											<td>
												@if($b['risk_level'] === 'expired')
												<span class="badge badge-pill badge-danger-soft">
													{{ $b['status_label'] }}
												</span>
												@elseif($b['risk_level'] === 'critical')
												<span class="badge badge-pill badge-danger-soft">
													{{ $b['status_label'] }}
												</span>
												@elseif($b['risk_level'] === 'warning')
												<span class="badge badge-pill badge-amber-soft">
													{{ $b['status_label'] }}
												</span>
												@else
												<span class="badge badge-pill badge-green-soft">
													{{ $b['status_label'] }}
												</span>
												@endif
											</td>
											<td class="text-right font-weight-bold @if($b['risk_level'] === 'critical' || $b['risk_level'] === 'expired') text-danger @else text-dark @endif text-nowrap">
												${{ number_format($b['total_value'], 2) }}
											</td>
											<td class="text-right text-nowrap">
												<div class="btn-group btn-group-sm">
													<button class="btn btn-sm btn-icon-action" title="Ver detalle completo" onclick="DairyQuality.OpenBatchDetailModal({{ json_encode($b) }})">
														<i class="fa-solid fa-eye"></i>
													</button>
													<button class="btn btn-sm btn-icon-action text-amber" title="Aislar en cuarentena" onclick="DairyQuality.OpenQuarantineForBatch({{ json_encode($b) }})" @if(!$canQuarantine) disabled @endif>
														<i class="fa-solid fa-box-archive"></i>
													</button>
													<a class="btn btn-sm btn-icon-action text-danger" title="Reportar merma" href="{{ $U('/waste/report?product_id=' . $b['product_id'] . '&stock_id=' . $b['stock_id']) }}">
														<i class="fa-solid fa-trash-can"></i>
													</a>
												</div>
											</td>
										</tr>
										@empty
										<tr>
											<td colspan="8" class="text-center py-4 text-muted">No hay lotes con fecha de vencimiento registrada.</td>
										</tr>
										@endforelse
									</tbody>
								</table>
							</div>
						</div>

						<div class="card-footer bg-white py-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center">
							<div class="small text-muted mb-2 mb-sm-0" id="matrix-status-text">
								Mostrando <span id="visible-count">{{ min(count($batches), 12) }}</span> de los {{ count($batches) }} lotes del módulo (vista parcial). Información al {{ date('d/m/Y H:i') }}.
							</div>
							@if(count($batches) > 12)
							<button class="btn btn-sm btn-white-border font-weight-bold" id="btn-toggle-all-batches" onclick="DairyQuality.ToggleAllBatches()">
								Ver todos los lotes ↕
							</button>
							@endif
						</div>
					</div>

				</div>

				<!-- PANEL LATERAL DERECHO (~30%) -->
				<div class="col-lg-4 mb-4">

					<!-- WIDGET 1: RUTA DE ATENCIÓN -->
					<div class="card border-0 shadow-sm rounded-lg mb-3 sidebar-widget">
						<div class="card-body p-3">
							<div class="d-flex align-items-center mb-3">
								<div class="icon-square-sm bg-green-soft text-success mr-2">
									<i class="fa-solid fa-compass"></i>
								</div>
								<h6 class="font-weight-bold text-dark mb-0">Ruta de atención</h6>
							</div>
							<div class="attention-stepper">
								<div class="stepper-item d-flex align-items-start mb-3">
									<div class="stepper-num bg-green text-white mr-3">1</div>
									<div>
										<div class="font-weight-bold text-dark small">Revisar vencimientos</div>
										<div class="text-muted text-xs">Identifica lotes en riesgo.</div>
									</div>
								</div>
								<div class="stepper-item d-flex align-items-start mb-3">
									<div class="stepper-num bg-green text-white mr-3">2</div>
									<div>
										<div class="font-weight-bold text-dark small">Evaluar rebaja</div>
										<div class="text-muted text-xs">Define acciones según condición.</div>
									</div>
								</div>
								<div class="stepper-item d-flex align-items-start mb-3">
									<div class="stepper-num bg-green text-white mr-3">3</div>
									<div>
										<div class="font-weight-bold text-dark small">Aislar si hay duda de calidad</div>
										<div class="text-muted text-xs">Mueve a cuarentena.</div>
									</div>
								</div>
								<div class="stepper-item d-flex align-items-start mb-3">
									<div class="stepper-num bg-green text-white mr-3">4</div>
									<div>
										<div class="font-weight-bold text-dark small">Decidir destino autorizado</div>
										<div class="text-muted text-xs">Merma, rechazo o uso interno.</div>
									</div>
								</div>
								<div class="stepper-item d-flex align-items-start">
									<div class="stepper-num bg-green text-white mr-3">5</div>
									<div>
										<div class="font-weight-bold text-dark small">Registrar temperatura</div>
										<div class="text-muted text-xs">Confirma condiciones de cadena de frío.</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- WIDGET 2: CADENA DE FRÍO -->
					<div class="card border-0 shadow-sm rounded-lg mb-3 sidebar-widget">
						<div class="card-body p-3">
							<div class="d-flex justify-content-between align-items-start">
								<div>
									<div class="d-flex align-items-center mb-1">
										<i class="fa-solid fa-snowflake text-info mr-2"></i>
										<span class="font-weight-bold text-dark small">Cadena de frío</span>
									</div>
									<h5 class="font-weight-bold text-dark mb-0">{{ $coldTotal - $coldAlerts }} de {{ $coldTotal }} cámaras en rango</h5>
									<div class="small text-danger font-weight-medium mb-2">{{ $coldAlerts }} alerta térmica</div>
									<a href="#tab-cold" onclick="$('#tab-cold-link').tab('show')" class="btn btn-sm btn-daily-outline-green font-weight-bold px-3 py-1">
										Ver monitoreo →
									</a>
								</div>
								<div class="widget-photo-wrap ml-2">
									<img src="{{ $U('/img/control_center/widget_frio.jpg') }}" alt="Cámaras de frío" class="widget-photo">
								</div>
							</div>
						</div>
					</div>

					<!-- WIDGET 3: CALIDAD Y CUARENTENA -->
					<div class="card border-0 shadow-sm rounded-lg mb-3 sidebar-widget">
						<div class="card-body p-3">
							<div class="d-flex justify-content-between align-items-start">
								<div>
									<div class="d-flex align-items-center mb-1">
										<i class="fa-solid fa-shield-halved text-success mr-2"></i>
										<span class="font-weight-bold text-dark small">Calidad y cuarentena</span>
									</div>
									<h5 class="font-weight-bold text-dark mb-0">{{ count($quarantineItems) }} lote fuera de venta</h5>
									<div class="small text-muted font-weight-medium mb-2">{{ $inspApproved }} de {{ count($inspections) }} auditorías correctas</div>
									<div class="d-flex flex-wrap gap-2">
										<a href="#tab-quarantine" onclick="$('#tab-quarantine-link').tab('show')" class="btn btn-sm btn-daily-outline-green font-weight-bold px-2 py-1 mr-1">
											Ver cuarentena →
										</a>
										<a href="#tab-inspections" onclick="$('#tab-inspections-link').tab('show')" class="btn btn-sm btn-daily-outline-green font-weight-bold px-2 py-1">
											Ver inspecciones →
										</a>
									</div>
								</div>
								<div class="widget-photo-wrap ml-2">
									<img src="{{ $U('/img/control_center/widget_calidad.jpg') }}" alt="Inspecciones de calidad" class="widget-photo">
								</div>
							</div>
						</div>
					</div>

					<!-- WIDGET 4: PARÁMETROS CONFIGURADOS -->
					<div class="card border-0 shadow-sm rounded-lg sidebar-widget">
						<div class="card-body p-3">
							<div class="d-flex align-items-center mb-2">
								<i class="fa-solid fa-sliders text-muted mr-2"></i>
								<span class="font-weight-bold text-dark small">Parámetros configurados</span>
							</div>
							<p class="text-muted text-xs mb-3">Configuración por producto para control de calidad.</p>
							<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
								<span class="small text-muted">Refrigeración (lácteos)</span>
								<span class="font-weight-bold text-dark small">2 °C a 4 °C</span>
							</div>
							<div class="d-flex justify-content-between align-items-center py-2">
								<span class="small text-muted">Congelación</span>
								<span class="font-weight-bold text-dark small">≤ -18 °C</span>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- TAB 2: CADENA DE FRÍO -->
		<div class="tab-pane fade" id="tab-cold" role="tabpanel">
			<!-- Cámaras en vivo -->
			<div class="row mb-4">
				@foreach($coldChain as $c)
				<div class="col-lg-3 col-md-6 mb-3">
					<div class="card shadow-sm border-0 h-100 rounded-lg">
						<div class="card-header bg-white py-2 px-3 border-0 d-flex justify-content-between align-items-center">
							<h6 class="mb-0 font-weight-bold text-dark text-truncate" title="{{ $c['location_name'] }}">
								<i class="fa-solid @if($c['is_freezer']) fa-snowflake text-info @else fa-temperature-low text-primary @endif mr-1"></i>
								{{ $c['location_name'] }}
							</h6>
							<span class="badge badge-pill badge-{{ $c['eval_class'] }}-soft">{{ $c['eval_label'] }}</span>
						</div>
						<div class="card-body text-center py-3">
							<div class="mb-1 text-muted text-xs">Última temperatura registrada</div>
							<h2 class="font-weight-bold text-{{ $c['eval_class'] }} mb-0">
								@if($c['last_temperature'] !== null)
									{{ $c['last_temperature'] }}°C
								@else
									--
								@endif
							</h2>
							<small class="text-muted d-block mt-1">Rango estándar: <strong>{{ $c['target_range'] }}</strong></small>
							@if($c['last_humidity'])
							<small class="text-muted"><i class="fa-solid fa-droplet text-info mr-1"></i>Humedad: {{ $c['last_humidity'] }}%</small>
							@endif
							<hr class="my-2">
							<div class="d-flex justify-content-between align-items-center text-xs text-muted">
								<span>{{ $c['active_lots_count'] }} lotes guardados</span>
								<span><i class="fa-solid fa-clock mr-1"></i>{{ $c['last_recorded_at'] ? date('H:i', strtotime($c['last_recorded_at'])) : 'N/D' }}</span>
							</div>
						</div>
						<div class="card-footer bg-light p-2 border-0">
							<button class="btn btn-sm btn-daily-outline-green btn-block py-1 font-weight-bold" onclick="DairyQuality.OpenQuickTempModal({{ $c['location_id'] }}, '{{ addslashes($c['location_name']) }}', {{ $c['is_freezer'] }})">
								<i class="fa-solid fa-plus mr-1"></i>Registrar Lectura
							</button>
						</div>
					</div>
				</div>
				@endforeach
			</div>

			<!-- Historial de Mediciones -->
			<div class="card shadow-sm border-0 rounded-lg">
				<div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-info mr-2"></i>Historial de Lecturas Térmicas</h5>
					<button class="btn btn-sm btn-daily-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modal-temp-form">
						<i class="fa-solid fa-plus mr-1"></i>Nueva Medición
					</button>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0 matrix-table">
							<thead>
								<tr>
									<th>FECHA Y HORA</th>
									<th>CÁMARA / EQUIPO</th>
									<th>SUCURSAL</th>
									<th class="text-center">TEMPERATURA (°C)</th>
									<th class="text-center">HUMEDAD (%)</th>
									<th>REGISTRADO POR</th>
									<th class="text-center">EVALUACIÓN</th>
									<th>ACCIÓN / OBSERVACIÓN</th>
								</tr>
							</thead>
							<tbody>
								@forelse($temperatureLogs as $tl)
								<tr>
									<td class="small">{{ $tl['recorded_timestamp'] }}</td>
									<td class="font-weight-bold text-dark">{{ $tl['location_name'] }}</td>
									<td class="small text-muted">{{ $tl['branch_name'] }}</td>
									<td class="text-center font-weight-bold @if($tl['status'] === 'critical') text-danger @elseif($tl['status'] === 'warning') text-amber @else text-success @endif">
										{{ $tl['temperature'] }}°C
									</td>
									<td class="text-center small">{{ $tl['humidity'] !== null ? $tl['humidity'] . '%' : '—' }}</td>
									<td class="small text-muted">{{ $tl['recorded_by'] }}</td>
									<td class="text-center">
										@if($tl['status'] === 'normal')
											<span class="badge badge-pill badge-green-soft">Conforme</span>
										@elseif($tl['status'] === 'warning')
											<span class="badge badge-pill badge-amber-soft">Advertencia</span>
										@else
											<span class="badge badge-pill badge-danger-soft">Crítico</span>
										@endif
									</td>
									<td class="small text-muted">{{ $tl['action_taken'] ?: '—' }}</td>
								</tr>
								@empty
								<tr>
									<td colspan="8" class="text-center py-4 text-muted">No hay registros térmicos.</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- TAB 3: CUARENTENA -->
		<div class="tab-pane fade" id="tab-quarantine" role="tabpanel">
			<div class="card shadow-sm border-0 rounded-lg">
				<div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
					<div>
						<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-biohazard text-amber mr-2"></i>Lotes Aislados en Cuarentena Preventiva</h5>
						<small class="text-muted">Productos retirados preventivamente de venta por sospecha de ruptura térmica o inspección microbiológica.</small>
					</div>
					<button class="btn btn-sm btn-daily-outline-amber font-weight-bold shadow-sm" data-toggle="modal" data-target="#modal-quarantine-form">
						<i class="fa-solid fa-plus mr-1"></i>Aislar Lote
					</button>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0 matrix-table">
							<thead>
								<tr>
									<th>FECHA AISLAMIENTO</th>
									<th>LOTE</th>
									<th>PRODUCTO</th>
									<th>UBICACIÓN ACTUAL</th>
									<th class="text-center">CANTIDAD AISLADA</th>
									<th>MOTIVO DEL AISLAMIENTO</th>
									<th class="text-center">ESTADO</th>
									<th class="text-right">RESOLUCIÓN</th>
								</tr>
							</thead>
							<tbody>
								@forelse($quarantineItems as $qi)
								<tr class="status-border-warning">
									<td class="small">{{ $qi['entered_timestamp'] }}</td>
									<td class="font-weight-bold font-monospace"><span class="badge badge-light border">{{ $qi['stock_id'] }}</span></td>
									<td class="font-weight-bold text-dark">{{ $qi['product_name'] }}</td>
									<td class="small text-muted">{{ $qi['location_name'] ?: 'Cámara de Aislamiento' }}</td>
									<td class="text-center font-weight-bold text-danger">{{ $qi['amount'] }} {{ $qi['qu_name'] }}</td>
									<td class="small text-dark">{{ $qi['reason'] }}</td>
									<td class="text-center">
										<span class="badge badge-pill badge-danger-soft">En Cuarentena</span>
									</td>
									<td class="text-right">
										<div class="btn-group btn-group-sm">
											<button class="btn btn-sm btn-success font-weight-bold" title="Liberar a stock activo tras confirmar calidad" onclick="DairyQuality.ResolveQuarantine({{ $qi['id'] }}, 'released', '{{ addslashes($qi['product_name']) }}')">
												<i class="fa-solid fa-check mr-1"></i>Liberar
											</button>
											<button class="btn btn-sm btn-danger font-weight-bold ml-1" title="Confirmar daño y dar de baja a Control de Mermas" onclick="DairyQuality.ResolveQuarantine({{ $qi['id'] }}, 'discarded_as_waste', '{{ addslashes($qi['product_name']) }}')">
												<i class="fa-solid fa-trash-can mr-1"></i>Baja a Merma
											</button>
											<a class="btn btn-sm btn-outline-secondary ml-1" title="Documento imprimible" href="{{ $U('/document/quarantine/' . $qi['id']) }}">
												<i class="fa-solid fa-print"></i>
											</a>
										</div>
									</td>
								</tr>
								@empty
								<tr>
									<td colspan="8" class="text-center py-4 text-success font-weight-bold">
										<i class="fa-solid fa-check-circle fa-2x mb-2 d-block"></i>
										No hay lotes en cuarentena. Todos los productos lácteos se encuentran aptos para venta.
									</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- TAB 4: INSPECCIONES DE CALIDAD -->
		<div class="tab-pane fade" id="tab-inspections" role="tabpanel">
			<div class="card shadow-sm border-0 rounded-lg">
				<div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-microscope text-success mr-2"></i>Historial de Inspecciones y Pruebas</h5>
					<button class="btn btn-sm btn-daily-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modal-inspection-form">
						<i class="fa-solid fa-plus mr-1"></i>Nueva Inspección
					</button>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0 matrix-table">
							<thead>
								<tr>
									<th>FECHA</th>
									<th>PRODUCTO</th>
									<th>LOTE</th>
									<th>TIPO DE INSPECCIÓN</th>
									<th class="text-center">TEMP. MEDIDA</th>
									<th>INSPECTOR</th>
									<th class="text-center">RESULTADO</th>
									<th>NOTAS Y HALLAZGOS</th>
								</tr>
							</thead>
							<tbody>
								@forelse($inspections as $ins)
								<tr>
									<td class="small">{{ $ins['row_created_timestamp'] }}</td>
									<td class="font-weight-bold text-dark">{{ $ins['product_name'] }}</td>
									<td class="font-monospace small"><span class="badge badge-light border">{{ $ins['stock_id'] ?: 'N/D' }}</span></td>
									<td>
										@php
											$inspTypes = [
												'reception' => 'Recepción proveedor',
												'routine_cold' => 'Control de frío',
												'organoleptic' => 'Prueba sensorial',
												'packaging' => 'Sellado y empaque'
											];
										@endphp
										<span class="badge badge-light border">{{ $inspTypes[$ins['inspection_type']] ?? $ins['inspection_type'] }}</span>
									</td>
									<td class="text-center">
										@if($ins['temperature_measured'])
											<span class="badge @if($ins['temperature_measured'] <= 4.0) badge-info @else badge-warning @endif">
												{{ $ins['temperature_measured'] }}°C
											</span>
										@else
											<span class="text-muted small">—</span>
										@endif
									</td>
									<td class="small text-muted">{{ $ins['inspector_name'] }}</td>
									<td class="text-center">
										@if($ins['result'] === 'approved')
											<span class="badge badge-pill badge-green-soft"><i class="fa-solid fa-check mr-1"></i>Aprobado</span>
										@elseif($ins['result'] === 'warning')
											<span class="badge badge-pill badge-amber-soft">Advertencia</span>
										@elseif($ins['result'] === 'quarantine')
											<span class="badge badge-pill badge-danger-soft"><i class="fa-solid fa-biohazard mr-1"></i>Cuarentena</span>
										@else
											<span class="badge badge-pill badge-danger-soft">Rechazado</span>
										@endif
									</td>
									<td class="small text-muted">{{ $ins['notes'] }}</td>
								</tr>
								@empty
								<tr>
									<td colspan="8" class="text-center py-4 text-muted">No hay inspecciones registradas.</td>
								</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- TAB 5: TRAZABILIDAD 360° -->
		<div class="tab-pane fade" id="tab-traceability" role="tabpanel">
			<div class="card shadow-sm border-0 mb-4 bg-white rounded-lg">
				<div class="card-body py-3">
					<div class="row align-items-center">
						<div class="col-md-6">
							<label class="font-weight-bold mb-1 small text-dark"><i class="fa-solid fa-search mr-1 text-success"></i>Ingresa un Código de Lote o Nombre de Producto:</label>
							<div class="input-group">
								<input type="text" id="trace-search-input" class="form-control" placeholder="Ej. 6acea2b588ff5 o Leche entera" value="6acea2b588ff5">
								<div class="input-group-append">
									<button class="btn btn-daily-primary font-weight-bold" onclick="DairyQuality.SearchTraceability()">
										<i class="fa-solid fa-route mr-1"></i>Rastrear Trazabilidad
									</button>
								</div>
							</div>
						</div>
						<div class="col-md-6 text-muted small mt-2 mt-md-0">
							<i class="fa-solid fa-circle-info mr-1 text-info"></i>Reconstruye la historia completa del producto: recepción del proveedor, temperatura en transporte, inspección inicial, registros de frío en góndola y movimientos de inventario o merma.
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm border-0 rounded-lg">
				<div class="card-header bg-white py-3 border-0">
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-timeline text-success mr-2"></i>Línea de Vida y Trazabilidad del Lote</h5>
				</div>
				<div class="card-body">
					<div id="traceability-timeline-container">
						<!-- Timeline cargado vía JS -->
					</div>
				</div>
			</div>
		</div>

	</div>

	<!-- 6. MÓDULOS CONECTADOS CON CALIDAD & VENCIMIENTOS -->
	<div class="card border-0 shadow-sm rounded-lg mt-4 connected-modules-card">
		<div class="card-body p-4">
			<div class="d-flex align-items-center mb-3">
				<div class="icon-square-sm bg-green-soft text-success mr-2">
					<i class="fa-solid fa-diagram-project"></i>
				</div>
				<h6 class="font-weight-bold text-dark mb-0">Módulos conectados con Calidad & Vencimientos</h6>
			</div>

			<div class="row">
				<!-- RECIBE INFORMACIÓN DE -->
				<div class="col-md-6 mb-3 mb-md-0">
					<div class="text-xs font-weight-bold text-muted uppercase tracking-wider mb-2">
						RECIBE INFORMACIÓN DE
					</div>
					<div class="connected-module-box mb-2 p-3 bg-light rounded-lg border">
						<div class="d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-cubes-stacked text-success mr-2"></i>
								<div>
									<div class="font-weight-bold text-dark small">Stock & Movimientos</div>
									<div class="text-muted text-xs">Lotes con fecha de vencimiento alimentan el semáforo FEFO</div>
								</div>
							</div>
							<a href="{{ $U('/stockoverview') }}" class="text-muted ml-2"><i class="fa-solid fa-arrow-right"></i></a>
						</div>
					</div>
					<div class="connected-module-box p-3 bg-light rounded-lg border">
						<div class="d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-box-archive text-success mr-2"></i>
								<div>
									<div class="font-weight-bold text-dark small">Retiros de Producto</div>
									<div class="text-muted text-xs">Retiro de lote pasa por cuarentena</div>
								</div>
							</div>
							<a href="{{ $U('/recalls') }}" class="text-muted ml-2"><i class="fa-solid fa-arrow-right"></i></a>
						</div>
					</div>
				</div>

				<!-- ACTUALIZA / ALIMENTA A -->
				<div class="col-md-6">
					<div class="text-xs font-weight-bold text-muted uppercase tracking-wider mb-2">
						ACTUALIZA / ALIMENTA A
					</div>
					<div class="connected-module-box mb-2 p-3 bg-light rounded-lg border">
						<div class="d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-cash-register text-success mr-2"></i>
								<div>
									<div class="font-weight-bold text-dark small">Punto de Venta</div>
									<div class="text-muted text-xs">El POS aplica la regla FEFO activa del lote</div>
								</div>
							</div>
							<a href="{{ $U('/pos') }}" class="text-muted ml-2"><i class="fa-solid fa-arrow-right"></i></a>
						</div>
					</div>
					<div class="connected-module-box mb-2 p-3 bg-light rounded-lg border">
						<div class="d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-chart-pie text-success mr-2"></i>
								<div>
									<div class="font-weight-bold text-dark small">Centro de Control</div>
									<div class="text-muted text-xs">Rebajas por vencimiento (prevención antes de la merma)</div>
								</div>
							</div>
							<a href="{{ $U('/waste') }}" class="text-muted ml-2"><i class="fa-solid fa-arrow-right"></i></a>
						</div>
					</div>
					<div class="connected-module-box p-3 bg-light rounded-lg border">
						<div class="d-flex justify-content-between align-items-center">
							<div class="d-flex align-items-center">
								<i class="fa-solid fa-bell text-success mr-2"></i>
								<div>
									<div class="font-weight-bold text-dark small">Centro de Alertas</div>
									<div class="text-muted text-xs">Lotes por vencer abren alerta con responsable, plazo y cierre</div>
								</div>
							</div>
							<a href="{{ $U('/alerts') }}?category=quality" class="text-muted ml-2"><i class="fa-solid fa-arrow-right"></i></a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- TAB 6: PREVENCIÓN PREDICTIVA & REGLAS FEFO -->
		@include('components.quality_tabs.predictive')

	</div>

</div>

<!-- MODALES DEL MÓDULO -->

<!-- MODAL: REGISTRAR TEMPERATURA -->
<div class="modal fade" id="modal-temp-form" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-temperature-half text-info mr-2"></i>Medición Térmica de Frío
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-temp" onsubmit="DairyQuality.SubmitTemperature(event)">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold text-dark small">Cámara / Equipo de Frío *</label>
						<select class="custom-select" id="temp-location-id" name="location_id" required>
							@foreach($coldLocations as $loc)
							<option value="{{ $loc->id }}">{{ $loc->name }} @if($loc->is_freezer)(Congelador)@else(Refrigerador 2-4°C)@endif</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Temperatura Medida (°C) *</label>
							<input type="number" step="0.1" class="form-control font-weight-bold form-control-lg" name="temperature" id="temp-value" required placeholder="3.0">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Humedad Relativa (%)</label>
							<input type="number" step="1" class="form-control form-control-lg" name="humidity" placeholder="opcional" min="0" max="100">
						</div>
					</div>
					<div class="form-group">
						<label class="font-weight-bold text-dark small">Responsable / Inspector</label>
						<input type="text" class="form-control" name="recorded_by" value="Supervisor de Calidad">
					</div>
					<div class="form-group mb-0">
						<label class="font-weight-bold text-dark small">Acción Correctiva / Observaciones</label>
						<input type="text" class="form-control" name="action_taken" placeholder="Verificación de sellos, termostato, compresor...">
					</div>
				</div>
				<div class="modal-footer bg-light border-top py-2 px-4">
					<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-daily-primary font-weight-bold shadow-sm">
						<i class="fa-solid fa-save mr-1"></i>Guardar Medición
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: REGISTRAR INSPECCIÓN -->
<div class="modal fade" id="modal-inspection-form" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-microscope text-success mr-2"></i>Control de Calidad Lácteo
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-inspection" onsubmit="DairyQuality.SubmitInspection(event)">
				<div class="modal-body p-4">
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Producto *</label>
							<select class="custom-select" name="product_id" id="insp-product-id" required>
								@foreach($products as $p)
								<option value="{{ $p->id }}">{{ $p->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Código de Lote *</label>
							<input type="text" class="form-control font-monospace" name="stock_id" id="insp-stock-id" required placeholder="Ej. 6acea2b588ff5">
						</div>
					</div>
					<div class="row">
						<div class="col-md-4 form-group">
							<label class="font-weight-bold text-dark small">Tipo de Inspección *</label>
							<select class="custom-select" name="inspection_type">
								<option value="reception">Recepción de Proveedor</option>
								<option value="routine_cold" selected>Control Rutinario de Frío</option>
								<option value="organoleptic">Prueba Sensorial / Acidez</option>
								<option value="packaging">Integridad de Empaque</option>
							</select>
						</div>
						<div class="col-md-4 form-group">
							<label class="font-weight-bold text-dark small">Temperatura Medida (°C)</label>
							<input type="number" step="0.1" class="form-control" name="temperature_measured" placeholder="3.2">
						</div>
						<div class="col-md-4 form-group">
							<label class="font-weight-bold text-dark small">Resultado de la Prueba *</label>
							<select class="custom-select" name="result" id="inspection-result-select" required>
								<option value="approved">Aprobado (Conforme)</option>
								<option value="warning">Advertencia Menor</option>
								<option value="quarantine">Enviar a Cuarentena (Fallo)</option>
								<option value="rejected">Rechazo Total</option>
							</select>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Ubicación Actual</label>
							<select class="custom-select" name="location_id">
								@foreach($allLocations as $loc)
								<option value="{{ $loc->id }}">{{ $loc->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Nombre del Inspector *</label>
							<input type="text" class="form-control" name="inspector_name" value="Supervisor de Calidad" required>
						</div>
					</div>
					<div class="form-group mb-0">
						<label class="font-weight-bold text-dark small">Observaciones y Criterios Evaluados</label>
						<textarea class="form-control" name="notes" rows="2" placeholder="Olor, textura, viscosidad, acidez o estado del sellado..."></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light border-top py-2 px-4">
					<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-daily-primary font-weight-bold shadow-sm">
						<i class="fa-solid fa-save mr-1"></i>Guardar Inspección
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: AISLAR EN CUARENTENA -->
<div class="modal fade" id="modal-quarantine-form" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-triangle-exclamation text-amber mr-2"></i>Aislar Lote en Cuarentena
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-quarantine" onsubmit="DairyQuality.SubmitQuarantine(event)">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-bold text-dark small">Producto *</label>
						<select class="custom-select" id="quarantine-product-id" name="product_id" required>
							@foreach($products as $p)
							<option value="{{ $p->id }}">{{ $p->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Código de Lote *</label>
							<input type="text" class="form-control font-monospace" id="quarantine-stock-id" name="stock_id" required placeholder="Lote">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Cantidad a Aislar *</label>
							<input type="number" step="0.01" class="form-control font-weight-bold" id="quarantine-amount" name="amount" required value="10">
						</div>
					</div>
					<div class="form-group mb-0">
						<label class="font-weight-bold text-dark small">Motivo de Aislamiento Preventivo *</label>
						<textarea class="form-control" name="reason" rows="3" required placeholder="Describe por qué se retira de piso de venta (sospecha térmica, prueba pendiente, etc.)..."></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light border-top py-2 px-4">
					<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-daily-amber font-weight-bold shadow-sm">
						<i class="fa-solid fa-lock mr-1"></i>Bloquear en Cuarentena
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: DETALLE COMPLETO DEL LOTE -->
<div class="modal fade" id="modal-lot-detail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-barcode text-success mr-2"></i>Ficha Técnica del Lote
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4" id="lot-detail-modal-body">
				<!-- Inyectado vía JS -->
			</div>
			<div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between">
				<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
				<div id="lot-detail-modal-actions">
					<!-- Botones contextuales inyectados vía JS -->
				</div>
			</div>
		</div>
	</div>
</div>

@push('pageStyles')
<style>
/* ==========================================================================
   DAILY DESIGN SYSTEM: CALIDAD & VENCIMIENTOS
   ========================================================================== */
.quality-page-wrapper {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}

/* Colores de marca DAILY */
.text-slate-900 { color: #0f172a; }
.text-amber { color: #d97706 !important; }
.bg-green { background-color: #00C83B !important; }
.bg-green-soft { background-color: #ecfdf5 !important; }
.bg-amber-soft { background-color: #fef3c7 !important; }
.bg-red-soft { background-color: #fee2e2 !important; }
.bg-blue-soft { background-color: #e0f2fe !important; }

/* Botones DAILY */
.btn-daily-primary {
	background-color: #00C83B;
	color: #ffffff;
	border: 1px solid #00C83B;
	border-radius: 8px;
	padding: 6px 16px;
	transition: all 0.15s ease-in-out;
}
.btn-daily-primary:hover {
	background-color: #00b334;
	color: #ffffff;
	box-shadow: 0 2px 6px rgba(0, 200, 59, 0.25);
}
.btn-daily-outline-green {
	background-color: #ffffff;
	color: #00C83B;
	border: 1px solid #00C83B;
	border-radius: 8px;
	padding: 6px 14px;
	transition: all 0.15s ease-in-out;
}
.btn-daily-outline-green:hover {
	background-color: #ecfdf5;
	color: #00992c;
}
.btn-daily-outline-amber {
	background-color: #ffffff;
	color: #d97706;
	border: 1px solid #f59e0b;
	border-radius: 8px;
	padding: 6px 14px;
	transition: all 0.15s ease-in-out;
}
.btn-daily-outline-amber:hover {
	background-color: #fef3c7;
	color: #b45309;
}
.btn-daily-amber {
	background-color: #f59e0b;
	color: #ffffff;
	border: 1px solid #d97706;
	border-radius: 8px;
	padding: 6px 16px;
}
.btn-daily-amber:hover {
	background-color: #d97706;
	color: #ffffff;
}
.btn-white-border {
	background-color: #ffffff;
	color: #334155;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 6px 14px;
}
.btn-white-border:hover {
	background-color: #f8fafc;
	color: #0f172a;
}
.btn-icon-action {
	background: transparent;
	border: 1px solid #e2e8f0;
	color: #64748b;
	border-radius: 6px;
	padding: 3px 8px;
	margin-left: 2px;
}
.btn-icon-action:hover {
	background: #f1f5f9;
	color: #0f172a;
}

/* Badges de píldora suaves */
.badge-danger-soft {
	background-color: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
	font-weight: 600;
	padding: 4px 10px;
}
.badge-amber-soft {
	background-color: #fef3c7;
	color: #d97706;
	border: 1px solid #fde68a;
	font-weight: 600;
	padding: 4px 10px;
}
.badge-green-soft {
	background-color: #dcfce7;
	color: #15803d;
	border: 1px solid #bbf7d0;
	font-weight: 600;
	padding: 4px 10px;
}
.badge-info-soft {
	background-color: #e0f2fe;
	color: #0369a1;
	border: 1px solid #bae6fd;
	font-weight: 600;
	padding: 4px 10px;
}
.badge-amber {
	background-color: #f59e0b;
	color: #ffffff;
	font-weight: 700;
	border-radius: 10px;
	padding: 2px 7px;
}

/* Encabezado */
.quality-header-card {
	border-radius: 16px;
	position: relative;
	overflow: hidden;
	background-color: #ffffff;
	padding: 24px 28px;
	border: 1px solid #e2e8f0 !important;
}
.quality-header-bg {
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	width: 48%;
	background-image: url('{{ $U("/img/control_center/calidad_banner.jpg") }}');
	background-size: cover;
	background-position: center right;
	mask-image: linear-gradient(to left, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
	-webkit-mask-image: linear-gradient(to left, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
	opacity: 0.95;
	pointer-events: none;
}
@media (max-width: 768px) {
	.quality-header-bg { opacity: 0.25; width: 100%; }
}
.quality-header-icon {
	width: 44px;
	height: 44px;
	border-radius: 10px;
	background-color: #ecfdf5;
	color: #00C83B;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 20px;
}

/* Franja de alertas */
.quality-alert-bar {
	border-radius: 12px;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
}
.branch-filter-select {
	border-radius: 8px;
	border-color: #cbd5e1;
	font-size: 13px;
	height: 32px;
}

/* KPI Cards */
.kpi-card {
	border-radius: 12px;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
}
.kpi-icon-pill {
	width: 28px;
	height: 28px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 13px;
}
.kpi-label {
	font-size: 12.5px;
	font-weight: 600;
	color: #64748b;
}
.kpi-value {
	font-size: 22px;
	line-height: 1.1;
}

/* Navegación por pestañas */
.quality-nav-pills .nav-link {
	border-radius: 8px;
	color: #64748b;
	padding: 8px 18px;
	font-size: 13.5px;
	transition: all 0.15s ease;
}
.quality-nav-pills .nav-link:hover {
	color: #0f172a;
	background-color: #f8fafc;
}
.quality-nav-pills .nav-link.active {
	background-color: #ecfdf5;
	color: #00C83B;
}

/* Tarjetas de atención */
.attention-card {
	border-radius: 12px;
	overflow: hidden;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
	transition: all 0.15s ease-in-out;
}
.attention-card:hover {
	transform: translateY(-3px);
	box-shadow: 0 6px 16px rgba(0,0,0,0.07) !important;
	border-color: #00C83B !important;
}
.attention-card-img-wrapper {
	width: 100%;
	height: 135px;
	overflow: hidden;
	background-color: #f1f5f9;
}
.attention-card-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	transition: transform 0.25s ease;
}
.attention-card:hover .attention-card-img {
	transform: scale(1.03);
}
.attention-card-badge-overlay {
	position: absolute;
	top: 10px;
	right: 10px;
}
.attention-price {
	font-size: 17px;
}

/* Matriz de lotes */
.matrix-card {
	border-radius: 14px;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
}
.matrix-table thead th {
	background-color: #f8fafc;
	color: #64748b;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.5px;
	border-top: none;
	border-bottom: 1px solid #e2e8f0;
	padding: 10px 14px;
}
.matrix-table tbody td {
	padding: 12px 14px;
	vertical-align: middle;
	border-top: 1px solid #f1f5f9;
}
.product-row-name {
	font-size: 14px;
	color: #0f172a;
}
/* Indicadores de borde de estado (sin fondo sólido invasivo) */
.status-border-expired {
	border-left: 3px solid #dc2626 !important;
}
.status-border-critical {
	border-left: 3px solid #ef4444 !important;
}
.status-border-warning {
	border-left: 3px solid #f59e0b !important;
}
.status-border-optimal {
	border-left: 3px solid #10b981 !important;
}
.search-box-wrap {
	position: relative;
	min-width: 220px;
}
.search-box-wrap .search-icon {
	position: absolute;
	left: 10px;
	top: 9px;
	font-size: 12px;
}
.search-box-wrap .search-input {
	padding-left: 28px;
	border-radius: 8px;
	font-size: 13px;
	border-color: #cbd5e1;
}
.risk-filter-select {
	border-radius: 8px;
	font-size: 13px;
	border-color: #cbd5e1;
}

/* Widgets laterales */
.sidebar-widget {
	border-radius: 12px;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
}
.icon-square-sm {
	width: 28px;
	height: 28px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 13px;
}
.stepper-num {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 11px;
	font-weight: 700;
	flex-shrink: 0;
}
.widget-photo-wrap {
	width: 76px;
	height: 76px;
	border-radius: 10px;
	overflow: hidden;
	flex-shrink: 0;
	background-color: #f1f5f9;
}
.widget-photo {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.text-xs { font-size: 11.5px; }

/* Timeline de Trazabilidad */
.timeline {
	position: relative;
	padding-left: 28px;
	margin-left: 16px;
	border-left: 2px solid #e2e8f0;
}
.timeline-icon-wrap {
	position: absolute;
	left: -15px;
	top: 0;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
}

/* Módulos conectados */
.connected-modules-card {
	border-radius: 14px;
	background-color: #ffffff;
	border: 1px solid #e2e8f0 !important;
}
.connected-module-box {
	transition: all 0.15s ease;
}
.connected-module-box:hover {
	border-color: #00C83B !important;
	background-color: #ffffff !important;
	box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
</style>
@endpush

@push('pageScripts')
<script>
var DairyQuality = {
	allBatchesVisible: false,

	Init: function() {
		this.SearchTraceability();
	},

	FilterBatches: function() {
		var query = $('#batch-search-input').val().toLowerCase();
		var risk = $('#batch-risk-filter').val();
		var visibleCount = 0;

		$('.batch-row').each(function() {
			var name = $(this).data('name') || '';
			var bRisk = $(this).data('risk') || '';

			var matchesQuery = name.indexOf(query) !== -1;
			var matchesRisk = true;
			if (risk === 'critical' && bRisk !== 'critical' && bRisk !== 'expired') matchesRisk = false;
			if (risk === 'warning' && bRisk !== 'warning') matchesRisk = false;
			if (risk === 'optimal' && bRisk !== 'optimal') matchesRisk = false;

			if (matchesQuery && matchesRisk) {
				$(this).removeClass('d-none');
				visibleCount++;
			} else {
				$(this).addClass('d-none');
			}
		});

		$('#visible-count').text(visibleCount);
	},

	ToggleAllBatches: function() {
		this.allBatchesVisible = !this.allBatchesVisible;
		if (this.allBatchesVisible) {
			$('.batch-row-overflow').removeClass('d-none');
			$('#btn-toggle-all-batches').text('Ver menos lotes ↕');
			$('#visible-count').text($('.batch-row').length);
		} else {
			$('.batch-row-overflow').addClass('d-none');
			$('#btn-toggle-all-batches').text('Ver todos los lotes ↕');
			$('#visible-count').text(Math.min($('.batch-row').length, 12));
		}
	},

	OpenQuickTempModal: function(locationId, locationName, isFreezer) {
		$('#temp-location-id').val(locationId);
		$('#temp-value').val(isFreezer ? -19.0 : 3.0);
		$('#modal-temp-form').modal('show');
	},

	SubmitTemperature: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/quality/temperatures', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-temp-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al guardar temperatura');
			}
		})
		.catch(function(err) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error('Error de comunicación con el servidor');
		});
	},

	SubmitInspection: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/quality/inspections', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-inspection-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al guardar inspección');
			}
		})
		.catch(function(err) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error('Error de comunicación con el servidor');
		});
	},

	OpenQuarantineForBatch: function(b) {
		$('#quarantine-product-id').val(b.product_id);
		$('#quarantine-stock-id').val(b.stock_id);
		$('#quarantine-amount').val(b.amount);
		$('#modal-quarantine-form').modal('show');
	},

	SubmitQuarantine: function(e) {
		e.preventDefault();
		var data = $(e.target).serializeJSON();

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/quality/quarantine', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				$('#modal-quarantine-form').modal('hide');
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al aislar lote');
			}
		})
		.catch(function(err) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error('Error al procesar la solicitud');
		});
	},

	ResolveQuarantine: function(id, resolution, productName) {
		var actionName = (resolution === 'discarded_as_waste') ? 'dar de baja definitiva como MERMA' : 'LIBERAR al inventario activo';
		var promptMsg = '¿Confirmas ' + actionName + ' para ' + productName + '? Motivo o nota:';
		var note = prompt(promptMsg, (resolution === 'discarded_as_waste' ? 'Confirmado deterioro / pérdida de frío' : 'Calidad verificada y conforme'));
		if (note === null) return;

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/quality/quarantine/' + id + '/resolve', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ resolution: resolution, notes: note })
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			Grocy.FrontendHelpers.EndUiBusy();
			if (res.success) {
				toastr.success(res.message);
				window.location.reload();
			} else {
				toastr.error(res.error_message || 'Error al resolver cuarentena');
			}
		})
		.catch(function(err) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error('Error de comunicación');
		});
	},

	OpenBatchDetailModal: function(b) {
		var statusBadge = '';
		if (b.risk_level === 'expired' || b.risk_level === 'critical') {
			statusBadge = '<span class="badge badge-pill badge-danger-soft">' + b.status_label + '</span>';
		} else if (b.risk_level === 'warning') {
			statusBadge = '<span class="badge badge-pill badge-amber-soft">' + b.status_label + '</span>';
		} else {
			statusBadge = '<span class="badge badge-pill badge-green-soft">' + b.status_label + '</span>';
		}

		var html = '<div class="mb-3 d-flex justify-content-between align-items-center">' +
			'<div><h5 class="font-weight-bold text-dark mb-0">' + b.product_name + '</h5>' +
			'<div class="text-muted small font-monospace">' + (b.sku || 'ID #' + b.product_id) + '</div></div>' +
			statusBadge +
		'</div>' +
		'<div class="bg-light p-3 rounded-lg mb-3 border">' +
			'<div class="row">' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Código de Lote:</span><span class="font-weight-bold font-monospace text-dark">' + b.stock_id + '</span></div>' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Existencias:</span><span class="font-weight-bold text-dark">' + b.amount + ' ' + b.qu_name + '</span></div>' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Fecha Ingreso:</span><span class="font-weight-bold text-dark">' + b.purchased_date + '</span></div>' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Fecha Vencimiento:</span><span class="font-weight-bold text-danger">' + b.best_before_date + '</span></div>' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Ubicación:</span><span class="font-weight-bold text-dark">' + b.location_name + '</span></div>' +
				'<div class="col-6 mb-2"><span class="text-muted small d-block">Sucursal:</span><span class="font-weight-bold text-dark">' + b.branch_name + '</span></div>' +
				'<div class="col-6"><span class="text-muted small d-block">Precio Unitario:</span><span class="font-weight-bold text-dark">$' + parseFloat(b.price || 0).toFixed(2) + '</span></div>' +
				'<div class="col-6"><span class="text-muted small d-block">Valor en Riesgo:</span><span class="font-weight-bold text-danger">$' + parseFloat(b.total_value || 0).toFixed(2) + '</span></div>' +
			'</div>' +
		'</div>' +
		'<div class="alert alert-warning py-2 px-3 small border mb-0">' +
			'<i class="fa-solid fa-circle-exclamation mr-1"></i>Aislar este lote lo retira del stock vendible sin generar merma automática hasta su dictamen.' +
		'</div>';

		$('#lot-detail-modal-body').html(html);

		var actionsHtml = '<button class="btn btn-daily-outline-amber font-weight-bold mr-1" onclick=\'DairyQuality.OpenQuarantineForBatch(' + JSON.stringify(b) + '); $("#modal-lot-detail").modal("hide");\'>' +
			'<i class="fa-solid fa-box-archive mr-1"></i>Aislar en Cuarentena</button>' +
			'<button class="btn btn-daily-outline-green font-weight-bold mr-1" onclick=\'DairyQuality.InspectBatch("' + b.stock_id + '"); $("#modal-lot-detail").modal("hide");\'>' +
			'<i class="fa-solid fa-route mr-1"></i>Trazabilidad</button>' +
			'<a class="btn btn-danger font-weight-bold" href="' + Grocy.BaseUrl + '/waste/report?product_id=' + b.product_id + '&stock_id=' + b.stock_id + '">' +
			'<i class="fa-solid fa-trash-can mr-1"></i>Merma</a>';

		$('#lot-detail-modal-actions').html(actionsHtml);
		$('#modal-lot-detail').modal('show');
	},

	InspectBatch: function(stockId) {
		$('#trace-search-input').val(stockId);
		$('#tab-traceability-link').tab('show');
		this.SearchTraceability();
	},

	SearchTraceability: function() {
		var ident = $('#trace-search-input').val().trim();
		if (!ident) return;

		$('#traceability-timeline-container').html('<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-success"></i><br><span class="small text-muted mt-2 d-inline-block">Trazando historial de vida del lote...</span></div>');

		fetch(Grocy.BaseUrl + '/api/quality/traceability/' + encodeURIComponent(ident))
		.then(function(r) { return r.json(); })
		.then(function(events) {
			if (!events || events.length === 0) {
				$('#traceability-timeline-container').html('<div class="text-center py-4 text-muted"><i class="fa-solid fa-route fa-2x mb-2 d-block text-muted"></i>No se encontraron eventos para este lote o producto.</div>');
				return;
			}

			var html = '<div class="timeline">';
			events.forEach(function(ev) {
				var iconClass = 'bg-primary text-white';
				if (ev.color === 'success') iconClass = 'bg-green text-white';
				else if (ev.color === 'warning') iconClass = 'bg-amber-soft text-amber border border-warning';
				else if (ev.color === 'danger') iconClass = 'bg-red-soft text-danger border border-danger';
				else if (ev.color === 'info') iconClass = 'bg-blue-soft text-primary border border-info';

				html += '<div class="mb-4 position-relative">' +
					'<div class="timeline-icon-wrap ' + iconClass + '">' +
						'<i class="fa-solid ' + ev.icon + ' fa-xs"></i>' +
					'</div>' +
					'<div class="card shadow-sm border rounded-lg bg-white">' +
						'<div class="card-body py-2 px-3">' +
							'<div class="d-flex justify-content-between align-items-center mb-1">' +
								'<h6 class="mb-0 font-weight-bold text-dark">' + ev.title + '</h6>' +
								'<small class="text-muted"><i class="fa-solid fa-clock mr-1"></i>' + ev.timestamp + '</small>' +
							'</div>' +
							'<p class="mb-0 small text-muted">' + ev.description + '</p>' +
						'</div>' +
					'</div>' +
				'</div>';
			});
			html += '</div>';

			$('#traceability-timeline-container').html(html);
		})
		.catch(function(err) {
			$('#traceability-timeline-container').html('<div class="alert alert-danger small py-2">Error al recuperar la trazabilidad.</div>');
		});
	},

	ExportCSV: function(e) {
		e.preventDefault();
		var table = document.getElementById('batches-table');
		if (!table) return;

		var csv = [];
		var rows = table.querySelectorAll('tr');

		for (var i = 0; i < rows.length; i++) {
			var row = [], cols = rows[i].querySelectorAll('td, th');
			for (var j = 0; j < cols.length - 1; j++) {
				var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s+)/gm, ' ');
				data = data.replace(/"/g, '""');
				row.push('"' + data.trim() + '"');
			}
			csv.push(row.join(','));
		}

		var csvFile = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
		var downloadLink = document.createElement('a');
		downloadLink.download = 'DAILY_Calidad_Vencimientos_' + new Date().toISOString().slice(0,10) + '.csv';
		downloadLink.href = window.URL.createObjectURL(csvFile);
		downloadLink.style.display = 'none';
		document.body.appendChild(downloadLink);
		downloadLink.click();
		document.body.removeChild(downloadLink);
	},

	ExportColdChain: function(e) {
		e.preventDefault();
		toastr.info('Generando reporte de monitoreo térmico...');
		window.location.href = Grocy.BaseUrl + '/api/quality/temperatures?format=csv';
	},

	OpenAdvanceAlertModal: function(alertData) {
		$('#advance-alert-id').val(alertData.id);
		$('#advance-alert-product-name').text(alertData.product_name);
		$('#advance-alert-lot').text(alertData.lot_number);
		$('#advance-alert-units-risk').text(alertData.units_at_risk);
		$('#advance-alert-value-risk').text('$' + parseFloat(alertData.financial_loss_projected || 0).toFixed(2));
		$('#advance-alert-assignee').val(alertData.assigned_to_name || 'Supervisor de Turno');
		$('#advance-alert-evidence').val(alertData.evidence_url || '');
		$('#advance-alert-notes').val(alertData.notes || '');

		var stSeq = {
			'alerta': 'validacion',
			'validacion': 'accion_asignada',
			'accion_asignada': 'ejecucion',
			'ejecucion': 'verificacion',
			'verificacion': 'cerrada',
			'cerrada': 'cerrada'
		};
		var nextSt = stSeq[alertData.resolution_status] || 'validacion';
		$('#advance-alert-stage').val(nextSt);
		if (alertData.recommended_action) {
			$('#advance-alert-action').val(alertData.recommended_action);
		}

		$('#modal-advance-predictive-alert').modal('show');
	},

	SubmitAdvanceAlert: function(e) {
		e.preventDefault();
		var alertId = $('#advance-alert-id').val();
		var payload = {
			resolution_status: $('#advance-alert-stage').val(),
			action_taken: $('#advance-alert-action').val(),
			assigned_to_name: $('#advance-alert-assignee').val(),
			evidence_url: $('#advance-alert-evidence').val(),
			notes: $('#advance-alert-notes').val()
		};

		var btn = $('#btn-submit-advance-alert');
		btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i>Guardando...');

		$.ajax({
			url: Grocy.BaseUrl + '/api/retail/expiry/alerts/' + alertId + '/resolve',
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				toastr.success(res.message || 'Alerta predictiva actualizada con éxito');
				$('#modal-advance-predictive-alert').modal('hide');
				setTimeout(function() { window.location.reload(); }, 800);
			},
			error: function(err) {
				btn.prop('disabled', false).html('<i class="fa-solid fa-check mr-1"></i>Actualizar Estado');
				var msg = (err.responseJSON && err.responseJSON.error_message) ? err.responseJSON.error_message : 'Error al actualizar alerta';
				toastr.error(msg);
			}
		});
	},

	SubmitCategoryRules: function(e) {
		e.preventDefault();
		var form = document.getElementById('form-category-rules');
		var formData = new FormData(form);
		var rules = [
			{ category_code: 'bakery', anticipation_days: formData.get('rules[bakery]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[bakery]') ? 1 : 0 },
			{ category_code: 'carnes', anticipation_days: formData.get('rules[carnes]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[carnes]') ? 1 : 0 },
			{ category_code: 'pollo', anticipation_days: formData.get('rules[pollo]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[pollo]') ? 1 : 0 },
			{ category_code: 'lacteos', anticipation_days: formData.get('rules[lacteos]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[lacteos]') ? 1 : 0 },
			{ category_code: 'fruver', anticipation_days: formData.get('rules[fruver]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[fruver]') ? 1 : 0 },
			{ category_code: 'congelados', anticipation_days: formData.get('rules[congelados]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[congelados]') ? 1 : 0 },
			{ category_code: 'secos', anticipation_days: formData.get('rules[secos]').split(',').map(Number).filter(Boolean), requires_temp_check: formData.get('temp[secos]') ? 1 : 0 }
		];

		$.ajax({
			url: Grocy.BaseUrl + '/api/retail/expiry/rules',
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(rules),
			success: function(res) {
				toastr.success(res.message || 'Reglas guardadas correctamente');
				$('#modal-category-rules').modal('hide');
				setTimeout(function() { window.location.reload(); }, 800);
			},
			error: function(err) {
				toastr.error('Error al guardar las reglas de anticipación');
			}
		});
	}
};

$(document).ready(function() {
	DairyQuality.Init();
});
</script>
@endpush

@endsection
