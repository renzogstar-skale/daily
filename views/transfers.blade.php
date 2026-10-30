@extends('layout.default')

@section('title', 'Traslados entre sucursales')

@push('pageStyles')
<link href="{{ $U('/css/daily_transfers.css?v=', true) }}{{ time() }}" rel="stylesheet">
@endpush

@section('content')
@php
	$currentActive = $activeTransfer ?? ($inTransit[0] ?? ($transfers[0] ?? null));
	$activeId = $currentActive ? intval($currentActive['id']) : 0;
@endphp

<div class="tr-container">

	<!-- 1. ENCABEZADO HERO CON IMAGEN DE LOGÍSTICA DIFUMINADA Y KPIS -->
	<div class="tr-hero-card">
		<div class="tr-hero-bg"></div>

		<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
			<div class="d-flex align-items-center gap-3 tr-hero-content">
				<div class="tr-page-icon">
					<i class="fa-solid fa-arrow-right-arrow-left"></i>
				</div>
				<div>
					<h4 class="tr-page-title">Traslados entre sucursales</h4>
					<p class="tr-page-desc">Controla el envío, el tránsito y la recepción de mercancía.</p>
				</div>
			</div>

			<div class="tr-hero-actions">
				<button class="btn-tr-primary" data-toggle="modal" data-target="#tr-new-modal" id="btnNewTransfer">
					<i class="fa-solid fa-plus mr-1.5"></i> Nuevo traslado
				</button>
				<div class="dropdown d-inline-block">
					<button class="btn-tr-ghost dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="fa-solid fa-arrow-up-from-bracket mr-1.5"></i> Exportar
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius: 8px;">
						<a class="dropdown-item py-2 small" href="#" onclick="window.exportTransfersCsv(); return false;"><i class="fa-solid fa-file-csv mr-2 text-success"></i>Exportar a CSV</a>
						<a class="dropdown-item py-2 small" href="#" onclick="window.print(); return false;"><i class="fa-solid fa-print mr-2 text-muted"></i>Imprimir reporte</a>
					</div>
				</div>
			</div>
		</div>

		<!-- 4 INDICADORES COMPACTOS -->
		<div class="tr-kpi-grid">
			<div class="tr-kpi-card">
				<div class="tr-kpi-header">
					<div class="tr-kpi-icon-wrap tr-kpi-icon-amber">
						<i class="fa-solid fa-truck-fast"></i>
					</div>
					<span class="tr-kpi-label">En tránsito</span>
				</div>
				<div class="tr-kpi-value" id="kpiTransitCount">{{ $transitCount }}</div>
				<div class="tr-kpi-sub">Por recibir</div>
			</div>

			<div class="tr-kpi-card">
				<div class="tr-kpi-header">
					<div class="tr-kpi-icon-wrap tr-kpi-icon-green">
						<i class="fa-solid fa-sack-dollar"></i>
					</div>
					<span class="tr-kpi-label">Valor en tránsito</span>
				</div>
				<div class="tr-kpi-value" id="kpiTransitValue">${{ number_format($transitValue, 2) }}</div>
				<div class="tr-kpi-sub">Fuera del stock disponible</div>
			</div>

			<div class="tr-kpi-card">
				<div class="tr-kpi-header">
					<div class="tr-kpi-icon-wrap tr-kpi-icon-red">
						<i class="fa-solid fa-clock"></i>
					</div>
					<span class="tr-kpi-label">Más de 48 h</span>
				</div>
				<div class="tr-kpi-value @if($oldTransitCount > 0) is-danger @endif" id="kpiOldTransitCount">{{ $oldTransitCount }}</div>
				<div class="tr-kpi-sub">Revisar recepción</div>
			</div>

			<div class="tr-kpi-card">
				<div class="tr-kpi-header">
					<div class="tr-kpi-icon-wrap tr-kpi-icon-teal">
						<i class="fa-solid fa-scale-unbalanced"></i>
					</div>
					<span class="tr-kpi-label">Diferencias del mes</span>
				</div>
				<div class="tr-kpi-value" id="kpiMonthDiff">${{ number_format($monthDiff, 2) }}</div>
				<div class="tr-kpi-sub">Según registros de diferencias</div>
			</div>
		</div>
	</div>

	<!-- 2. AVISO DE ATENCIÓN PRIORITARIA -->
	@if($alertTransfer)
	<div class="tr-alert-banner" id="trAlertBanner">
		<div class="tr-alert-left">
			<div class="tr-alert-icon">!</div>
			<div>
				<div class="tr-alert-title">{{ $oldTransitCount > 0 ? $oldTransitCount : 1 }} traslado requiere atención</div>
				<div class="tr-alert-desc"><strong>{{ $alertTransfer['transfer_code'] }}</strong> lleva más de 48h en tránsito.</div>
			</div>
		</div>
		<div>
			<button type="button" class="btn-tr-alert-action" onclick="window.selectTransfer({{ $alertTransfer['id'] }})">
				Revisar traslado <i class="fa-solid fa-arrow-right"></i>
			</button>
		</div>
	</div>
	@endif

	<!-- 3. PROCESO STEPPER ("TRASLADO PASO A PASO") -->
	<div class="tr-stepper-wrap">
		<div class="tr-stepper-title">Traslado paso a paso</div>
		<div class="tr-stepper-bar">
			<div class="tr-stepper-step">
				<div class="tr-step-circle is-idle"><i class="fa-solid fa-truck" style="font-size: 0.72rem;"></i></div>
				<div class="tr-step-info">
					<span class="tr-step-name">Crear envío</span>
					<span class="tr-step-sub">origen, destino y cantidades</span>
				</div>
			</div>
			<div class="tr-step-connector"></div>
			<div class="tr-stepper-step">
				<div class="tr-step-circle is-idle">2</div>
				<div class="tr-step-info">
					<span class="tr-step-name">Imprimir guía</span>
					<span class="tr-step-sub">documento TRA acompaña la mercancía</span>
				</div>
			</div>
			<div class="tr-step-connector"></div>
			<div class="tr-stepper-step">
				<div class="tr-step-circle is-active">3</div>
				<div class="tr-step-info">
					<span class="tr-step-name is-active">En tránsito</span>
					<span class="tr-step-sub">traslado en proceso</span>
				</div>
			</div>
			<div class="tr-step-connector"></div>
			<div class="tr-stepper-step">
				<div class="tr-step-circle is-idle">4</div>
				<div class="tr-step-info">
					<span class="tr-step-name">Recibir en destino</span>
					<span class="tr-step-sub">contar lo que llegó</span>
				</div>
			</div>
			<div class="tr-step-connector"></div>
			<div class="tr-stepper-step">
				<div class="tr-step-circle is-idle">5</div>
				<div class="tr-step-info">
					<span class="tr-step-name">Revisar diferencias</span>
					<span class="tr-step-sub">según registros</span>
				</div>
			</div>
		</div>
	</div>

	<!-- 4. GRID PRINCIPAL EN DOS COLUMNAS (LISTADO + PANEL LATERAL) -->
	<div class="tr-content-grid">

		<!-- COLUMNA IZQUIERDA: LISTADO DE DOCUMENTOS Y FILTROS -->
		<div>
			<div class="tr-table-card">
				<div class="tr-table-header">
					<h6 class="tr-table-title">
						<i class="fa-solid fa-list text-success"></i> Documentos de traslado
					</h6>
					<span class="tr-table-count" id="trTableCountLabel">{{ count($transfers) }} documentos únicos</span>
				</div>

				<!-- Filtros Fila 1: Búsqueda, Fecha, Estado -->
				<div class="tr-filter-row-1">
					<div class="tr-search-input-wrap">
						<i class="fa-solid fa-magnifying-glass"></i>
						<input type="text" id="trSearchFilter" class="form-control form-control-sm" placeholder="Buscar por folio o sucursal...">
					</div>
					<div>
						<div class="input-group input-group-sm">
							<input type="date" id="trDateFilter" class="form-control form-control-sm" placeholder="Fecha">
						</div>
					</div>
					<div>
						<select id="trStatusSelect" class="custom-select custom-select-sm">
							<option value="">Estado: Todos</option>
							<option value="in_transit" @if($selectedStatus === 'in_transit') selected @endif>En tránsito</option>
							<option value="received" @if($selectedStatus === 'received') selected @endif>Recibido</option>
							<option value="cancelled" @if($selectedStatus === 'cancelled') selected @endif>Anulado</option>
						</select>
					</div>
				</div>

				<!-- Filtros Fila 2: Origen, Destino -->
				<div class="tr-filter-row-2">
					<div class="d-flex align-items-center gap-2">
						<label class="small text-muted mb-0 mr-1" style="min-width: 48px;">Origen</label>
						<select id="trOriginFilter" class="custom-select custom-select-sm">
							<option value="">Todas las sucursales</option>
							@foreach($branches as $b)
							<option value="{{ $b['name'] }}">{{ $b['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="d-flex align-items-center gap-2">
						<label class="small text-muted mb-0 mr-1" style="min-width: 48px;">Destino</label>
						<select id="trDestFilter" class="custom-select custom-select-sm">
							<option value="">Todas las sucursales</option>
							@foreach($branches as $b)
							<option value="{{ $b['name'] }}">{{ $b['name'] }}</option>
							@endforeach
						</select>
					</div>
				</div>

				<!-- Píldoras de Estado Rápido -->
				<div class="tr-pills-row">
					<button type="button" class="tr-pill-tab @if(!$selectedStatus) is-active @endif" data-status="">
						Todos ({{ count($allTransfers) }})
					</button>
					<button type="button" class="tr-pill-tab @if($selectedStatus === 'in_transit') is-active @endif" data-status="in_transit">
						En tránsito ({{ count($inTransit) }})
					</button>
					<button type="button" class="tr-pill-tab @if($selectedStatus === 'received') is-active @endif" data-status="received">
						Recibidos ({{ count($received) }})
					</button>
				</div>

				<!-- Tabla de Documentos -->
				<div class="table-responsive">
					<table class="tr-table" id="transfersTable">
						<thead>
							<tr>
								<th>Folio</th>
								<th>Ruta (Origen → Destino)</th>
								<th class="text-right">Productos</th>
								<th class="text-right">Valor</th>
								<th class="text-right">Diferencia</th>
								<th>Estado</th>
								<th>Envío</th>
								<th>Recepción</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody id="trTableBody">
							@forelse($transfers as $t)
							@php
								$isActiveRow = ($activeId === intval($t['id']));
								$statusBadgeClass = $t['status'] === 'in_transit' ? 'badge-tr-transit' : ($t['status'] === 'received' ? 'badge-tr-received' : 'badge-tr-cancelled');
								$statusText = $t['status'] === 'in_transit' ? 'En tránsito' : ($t['status'] === 'received' ? 'Recibido' : 'Anulado');
							@endphp
							<tr class="tr-row @if($isActiveRow) is-active @endif" 
								id="trRow_{{ $t['id'] }}"
								data-id="{{ $t['id'] }}"
								data-code="{{ $t['transfer_code'] }}"
								data-status="{{ $t['status'] }}"
								data-origin="{{ $t['from_branch_name'] }}"
								data-dest="{{ $t['to_branch_name'] }}"
								data-date="{{ substr($t['sent_at'], 0, 10) }}"
								onclick="window.selectTransfer({{ $t['id'] }})">
								
								<td class="tr-folio">{{ $t['transfer_code'] }}</td>
								
								<td>
									<div class="tr-route-main">
										{{ $t['from_branch_name'] }} <span class="text-muted mx-1">→</span> {{ $t['to_branch_name'] }}
									</div>
									<div class="tr-route-sub">
										{{ $t['from_location_name'] }} <span class="text-muted">→</span> {{ $t['to_location_name'] }}
									</div>
								</td>
								
								<td class="text-right font-weight-600">{{ $t['product_count'] }}</td>
								
								<td class="text-right font-weight-600">${{ number_format($t['total_sent_cost'], 2) }}</td>
								
								<td class="text-right @if($t['total_difference_cost'] > 0) text-danger font-weight-bold @else text-muted @endif">
									{{ $t['total_difference_cost'] > 0 ? '$' . number_format($t['total_difference_cost'], 2) : '—' }}
								</td>
								
								<td>
									<span class="badge-tr-status {{ $statusBadgeClass }}">{{ $statusText }}</span>
								</td>
								
								<td class="small">
									<div class="font-weight-600">{{ $t['created_by_name'] }}</div>
									<div class="text-muted">{{ date('Y-m-d H:i', strtotime($t['sent_at'])) }}</div>
								</td>
								
								<td class="small">
									@if(!empty($t['received_by_name']))
										<div class="font-weight-600">{{ $t['received_by_name'] }}</div>
										<div class="text-muted">{{ date('Y-m-d H:i', strtotime($t['received_at'])) }}</div>
									@else
										<span class="text-muted">—</span>
									@endif
								</td>
								
								<td class="text-right text-nowrap" onclick="event.stopPropagation();">
									<button type="button" class="btn-tr-view mr-1" onclick="window.selectTransfer({{ $t['id'] }})">
										Ver detalle
									</button>
									<a class="btn-tr-icon" href="{{ $U('/document/transfer/' . $t['id']) }}" target="_blank" title="Imprimir guía de traslado">
										<i class="fa-solid fa-print"></i>
									</a>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center text-muted py-4">Sin traslados registrados.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>

			<!-- TRES TARJETAS OPERATIVAS CON FOTOGRAFÍAS -->
			<div class="tr-bottom-grid">
				<!-- Tarjeta 1: Recepción con escáner -->
				<div class="tr-feature-card">
					<div class="tr-feature-header">
						<div class="tr-feature-icon is-green">
							<i class="fa-solid fa-barcode"></i>
						</div>
						<div>
							<div class="tr-feature-title">Recepción con escáner</div>
							<p class="tr-feature-desc">Registra productos, lotes y cantidades desde el escáner en el destino.</p>
						</div>
					</div>
					<div class="tr-feature-img-wrap">
						<img src="{{ $U('/img/control_center/daily_alert_scanner.jpg') }}" alt="Recepción con escáner" class="tr-feature-img">
					</div>
					<div class="tr-feature-footer">
						<a href="{{ $U('/scan') }}" class="tr-feature-link">
							Abrir operación <i class="fa-solid fa-arrow-right"></i>
						</a>
					</div>
				</div>

				<!-- Tarjeta 2: Diferencias de recepción -->
				<div class="tr-feature-card">
					<div class="tr-feature-header">
						<div class="tr-feature-icon is-amber">
							<i class="fa-solid fa-triangle-exclamation"></i>
						</div>
						<div>
							<div class="tr-feature-title">Diferencias de recepción</div>
							<p class="tr-feature-desc">Revisa faltantes, sobrantes y daños según el flujo autorizado.</p>
						</div>
					</div>
					<div class="tr-feature-img-wrap">
						<img src="{{ $U('/img/control_center/errores_recepcion.jpg') }}" alt="Diferencias de recepción" class="tr-feature-img">
					</div>
					<div class="tr-feature-footer">
						<a href="{{ $U('/waste') }}" class="tr-feature-link">
							Revisar diferencias <i class="fa-solid fa-arrow-right"></i>
						</a>
					</div>
				</div>

				<!-- Tarjeta 3: Trazabilidad -->
				<div class="tr-feature-card">
					<div class="tr-feature-header">
						<div class="tr-feature-icon is-blue">
							<i class="fa-solid fa-file-lines"></i>
						</div>
						<div>
							<div class="tr-feature-title">Trazabilidad</div>
							<p class="tr-feature-desc">Consulta movimientos de inventario y Kardex relacionados con el traslado.</p>
						</div>
					</div>
					<div class="tr-feature-img-wrap">
						<img src="{{ $U('/img/control_center/transfer_truck_hero.jpg') }}" alt="Trazabilidad logística" class="tr-feature-img">
					</div>
					<div class="tr-feature-footer">
						<a href="{{ $U('/kardex') }}" class="tr-feature-link">
							Ver en inventario <i class="fa-solid fa-arrow-right"></i>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- COLUMNA DERECHA: PANEL LATERAL DE DETALLE DEL TRASLADO -->
		<div id="trDetailColumn">
			@if($currentActive)
			<div class="tr-detail-card" id="trDetailPanel">
				<!-- Header del Detalle -->
				<div class="tr-detail-header">
					<div class="tr-detail-title-group">
						<span class="tr-status-dot @if($currentActive['status'] === 'in_transit') is-amber @else is-green @endif" id="panelStatusDot"></span>
						<span class="tr-detail-folio" id="panelFolio">{{ $currentActive['transfer_code'] }}</span>
						<span class="badge-tr-status @if($currentActive['status'] === 'in_transit') badge-tr-transit @elseif($currentActive['status'] === 'received') badge-tr-received @else badge-tr-cancelled @endif" id="panelBadge">
							{{ $currentActive['status'] === 'in_transit' ? 'En tránsito' : ($currentActive['status'] === 'received' ? 'Recibido' : 'Anulado') }}
						</span>
					</div>
					<button type="button" class="tr-detail-close" onclick="window.closeDetailPanel()" title="Cerrar panel">&times;</button>
				</div>

				<!-- Sección Ruta de traslado -->
				<div class="tr-route-section">
					<div class="tr-route-title">Ruta de traslado</div>
					
					<!-- Origen -->
					<div class="tr-route-step">
						<div class="tr-route-icon is-origin">
							<i class="fa-solid fa-store"></i>
						</div>
						<div>
							<div class="tr-route-meta-title" id="panelFromBranch">{{ $currentActive['from_branch_name'] }}</div>
							<div class="tr-route-meta-sub" id="panelFromLocation">{{ $currentActive['from_location_name'] }}</div>
						</div>
					</div>

					<!-- Conector -->
					<div class="tr-route-line-wrap">
						<div class="tr-route-dotted-line"></div>
						<i class="fa-solid fa-arrow-down tr-route-arrow"></i>
					</div>

					<!-- Destino -->
					<div class="tr-route-step">
						<div class="tr-route-icon is-dest">
							<i class="fa-solid fa-store"></i>
						</div>
						<div>
							<div class="tr-route-meta-title" id="panelToBranch">{{ $currentActive['to_branch_name'] }}</div>
							<div class="tr-route-meta-sub" id="panelToLocation">{{ $currentActive['to_location_name'] }}</div>
						</div>
					</div>
				</div>

				<!-- Mini KPIs de Productos y Valor -->
				<div class="tr-detail-mini-grid">
					<div class="tr-detail-mini-card">
						<div class="tr-detail-mini-icon text-muted">
							<i class="fa-solid fa-box"></i>
						</div>
						<div>
							<div class="tr-detail-mini-label">Productos</div>
							<div class="tr-detail-mini-val" id="panelProductCount">{{ $currentActive['product_count'] }} productos</div>
						</div>
					</div>

					<div class="tr-detail-mini-card">
						<div class="tr-detail-mini-icon text-success">
							<i class="fa-solid fa-sack-dollar"></i>
						</div>
						<div>
							<div class="tr-detail-mini-label">Valor del traslado</div>
							<div class="tr-detail-mini-val" id="panelTotalCost">${{ number_format($currentActive['total_sent_cost'], 2) }}</div>
						</div>
					</div>
				</div>

				<!-- Botón Consultar productos y lotes -->
				<button type="button" class="tr-btn-lots" onclick="window.showLotsModal()" id="btnOpenLots">
					<span><i class="fa-solid fa-boxes-stacked mr-2 text-muted"></i> Consultar productos y lotes</span>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
				</button>

				<!-- Envío / Recepción Grid -->
				<div class="tr-dates-grid">
					<div>
						<div class="tr-dates-label">Envío</div>
						<div class="tr-dates-item">
							<i class="fa-solid fa-user text-muted"></i>
							<span id="panelCreatedBy">{{ $currentActive['created_by_name'] }}</span>
						</div>
						<div class="tr-dates-item text-muted" style="font-size: 0.72rem;">
							<i class="fa-solid fa-calendar text-muted"></i>
							<span id="panelSentAt">{{ date('Y-m-d H:i', strtotime($currentActive['sent_at'])) }}</span>
						</div>
					</div>

					<div>
						<div class="tr-dates-label">Recepción</div>
						<div class="tr-dates-item">
							<i class="fa-solid fa-user text-muted"></i>
							<span id="panelReceivedBy">{{ $currentActive['received_by_name'] ?? 'Pendiente' }}</span>
						</div>
						<div class="tr-dates-item text-muted" style="font-size: 0.72rem;">
							<i class="fa-solid fa-clock text-muted"></i>
							<span id="panelReceivedAt">{{ $currentActive['received_at'] ? date('Y-m-d H:i', strtotime($currentActive['received_at'])) : '+ —' }}</span>
						</div>
					</div>
				</div>

				<!-- Aviso azul informativo con enlace a historial -->
				<div class="tr-info-notice">
					<i class="fa-solid fa-circle-info mt-0.5 text-primary"></i>
					<div>
						Revisa cantidades y lotes antes de confirmar la recepción.<br>
						<a href="#" onclick="window.showHistoryModal(); return false;">Ver historial →</a>
					</div>
				</div>

				<!-- Botón Principal: Recibir en destino -->
				<div id="panelActionsWrap">
					@if($currentActive['status'] === 'in_transit')
					<button type="button" class="tr-btn-receive-main" onclick="window.openReceiveModal({{ $currentActive['id'] }})" id="btnPanelReceive">
						<i class="fa-solid fa-check"></i> Recibir en destino
					</button>
					@else
					<div class="alert alert-success py-2 px-3 text-center small font-weight-bold mb-3" style="border-radius: 8px;">
						<i class="fa-solid fa-circle-check mr-1"></i> Recepción confirmada sin diferencias
					</div>
					@endif

					<!-- Botones Secundarios: Imprimir guía y Anular -->
					<div class="tr-btn-row-secondary">
						<a class="tr-btn-sub-print" href="{{ $U('/document/transfer/' . $currentActive['id']) }}" target="_blank" id="btnPanelPrint">
							<i class="fa-solid fa-print"></i> Imprimir guía
						</a>
						@if($currentActive['status'] === 'in_transit')
						<button type="button" class="tr-btn-sub-cancel" onclick="window.cancelTransfer({{ $currentActive['id'] }})" id="btnPanelCancel">
							<i class="fa-solid fa-trash-can"></i> Anular
						</button>
						@else
						<button type="button" class="tr-btn-sub-cancel" disabled style="opacity: 0.5; cursor: not-allowed;">
							<i class="fa-solid fa-ban"></i> Anular
						</button>
						@endif
					</div>
				</div>
			</div>
			@endif
		</div>
	</div>

</div>

<!-- ==========================================================================
     MODAL 1: CONSULTAR PRODUCTOS Y LOTES
     ========================================================================== -->
<div class="modal fade" id="tr-lots-modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">
			<div class="modal-header bg-light py-3 border-bottom">
				<div>
					<h6 class="modal-title font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-boxes-stacked mr-2 text-primary"></i>Productos y Lotes del Traslado <span id="lotsModalCode" class="font-mono text-primary"></span>
					</h6>
					<span class="text-muted small" id="lotsModalRoute"></span>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
			</div>
			<div class="modal-body p-0">
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
						<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem;">
							<tr>
								<th>Producto</th>
								<th>Lote</th>
								<th>Vencimiento (FEFO)</th>
								<th class="text-right">Cantidad Enviada</th>
								<th class="text-right">Precio Unitario</th>
								<th class="text-right">Valor Total</th>
							</tr>
						</thead>
						<tbody id="lotsModalTableBody">
							<!-- Llenado dinámico vía JS -->
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between align-items-center">
				<div class="text-muted small">
					<strong>Valor Total del Envío:</strong> <span class="text-dark font-weight-bold" id="lotsModalTotalCost">$0.00</span>
				</div>
				<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 2: NUEVO TRASLADO ENTRE SUCURSALES
     ========================================================================== -->
<div class="modal fade" id="tr-new-modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px; border: 1px solid #e2e8f0;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-truck-arrow-right mr-2 text-success"></i>Nuevo traslado entre sucursales
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="tr-new-form">
				<div class="modal-body p-4">
					<div class="form-row mb-3">
						<div class="form-group col-md-6 mb-2">
							<label class="small font-weight-bold text-dark" for="tr-from">Ubicación de Origen</label>
							<select id="tr-from" class="custom-select custom-select-sm" required>
								<option value="">Seleccione ubicación de origen...</option>
								@foreach($formData['locations'] as $l)
								<option value="{{ $l['id'] }}" data-branch="{{ $l['branch_id'] }}">{{ $l['branch_name'] }} · {{ $l['name'] }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group col-md-6 mb-2">
							<label class="small font-weight-bold text-dark" for="tr-to">Ubicación de Destino</label>
							<select id="tr-to" class="custom-select custom-select-sm" required>
								<option value="">Seleccione ubicación de destino...</option>
								@foreach($formData['locations'] as $l)
								<option value="{{ $l['id'] }}" data-branch="{{ $l['branch_id'] }}">{{ $l['branch_name'] }} · {{ $l['name'] }}</option>
								@endforeach
							</select>
							<small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Solo ubicaciones de otra sucursal distinta a la de origen.</small>
						</div>
					</div>

					<div class="border rounded p-3 bg-light mb-3">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="small font-weight-bold text-dark text-uppercase" style="letter-spacing: 0.04em;">Líneas de Productos a Trasladar</span>
							<button type="button" id="tr-add-line" class="btn btn-xs btn-outline-success font-weight-bold" disabled>
								<i class="fa-solid fa-plus mr-1"></i>Agregar producto
							</button>
						</div>
						<div class="table-responsive bg-white border rounded">
							<table class="table table-sm mb-0" style="font-size: 0.82rem;">
								<thead class="bg-light text-muted uppercase">
									<tr>
										<th>Producto</th>
										<th class="text-right">Disponible</th>
										<th style="width: 140px;">Cantidad a Enviar</th>
										<th style="width: 38px;"></th>
									</tr>
								</thead>
								<tbody id="tr-lines">
									<!-- Dinámico -->
								</tbody>
							</table>
						</div>
					</div>

					<div class="form-group mb-0">
						<label class="small font-weight-bold text-dark" for="tr-notes">Notas del Envío (Transportista, Placa del Camión, Guía Externa)</label>
						<input type="text" id="tr-notes" class="form-control form-control-sm" placeholder="Ej. Despacho en camión refrigerado móvil 03...">
					</div>
				</div>
				<div class="modal-footer bg-light py-2.5 px-4 border-top">
					<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold">
						<i class="fa-solid fa-paper-plane mr-1"></i>Confirmar y Enviar Traslado
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 3: RECIBIR TRASLADO EN DESTINO
     ========================================================================== -->
<div class="modal fade" id="tr-receive-modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px; border: 1px solid #e2e8f0;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-box-open mr-2 text-success"></i>Recibir traslado <span id="tr-receive-code" class="font-mono text-success"></span>
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="tr-receive-form">
				<div class="modal-body p-4">
					<div class="alert alert-light border py-2 px-3 small mb-3" id="tr-receive-route"></div>

					<div class="table-responsive border rounded mb-3">
						<table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
							<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem;">
								<tr>
									<th>Producto</th>
									<th class="text-right">Cantidad Enviada</th>
									<th style="width: 160px;" class="text-right">Cantidad Recibida</th>
								</tr>
							</thead>
							<tbody id="tr-receive-lines">
								<!-- Dinámico -->
							</tbody>
						</table>
					</div>

					<div class="p-2.5 rounded bg-warning-subtle border border-warning text-dark small mb-3" style="font-size: 0.78rem;">
						<i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i>
						<strong>Control de diferencias:</strong> Cualquier faltante se dará de baja automáticamente desde tránsito como merma de traslado (causal M07) a costo del lote.
					</div>

					<div class="form-group mb-0">
						<label class="small font-weight-bold text-dark" for="tr-receive-notes">Observaciones de la Recepción</label>
						<input type="text" id="tr-receive-notes" class="form-control form-control-sm" placeholder="Ej. Recepción completa sin diferencias en cámara de frío...">
					</div>
				</div>
				<div class="modal-footer bg-light py-2.5 px-4 border-top">
					<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-sm btn-success font-weight-bold">
						<i class="fa-solid fa-check mr-1"></i>Confirmar Recepción en Destino
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 4: HISTORIAL Y TRAZABILIDAD
     ========================================================================== -->
<div class="modal fade" id="tr-history-modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-md modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 12px; border: 1px solid #e2e8f0;">
			<div class="modal-header bg-light py-3 border-bottom">
				<h6 class="modal-title font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i>Historial de Eventos del Traslado
				</h6>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4" id="historyModalBody">
				<!-- Llenado dinámico -->
			</div>
			<div class="modal-footer bg-light py-2 px-3 border-top">
				<button type="button" class="btn btn-sm btn-secondary font-weight-bold" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

@stop

@push('pageScripts')
<script>
	// Mapa de detalles pre-cargado desde el servidor
	var trDetailsMap = {!! $detailsMapJson ?? '{}' !!};
	var trStock = @json($formData['stock'] ?? []);
	var currentSelectedId = {{ $activeId }};
	var trReceiveId = null;

	function trEsc(v) {
		return $('<div>').text(v === null || v === undefined ? '' : v).html();
	}

	function trPost(path, body, okMsg) {
		Grocy.FrontendHelpers.BeginUiBusy();
		$.ajax({
			url: Grocy.BaseUrl + '/api' + path,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(body || {})
		}).done(function(res) {
			toastr.success((res && res.message) || okMsg);
			setTimeout(function() { location.reload(); }, 700);
		}).fail(function(xhr) {
			Grocy.FrontendHelpers.EndUiBusy();
			toastr.error((xhr.responseJSON && xhr.responseJSON.error_message) || xhr.statusText);
		});
	}

	// Seleccionar un traslado y actualizar el panel lateral
	window.selectTransfer = function(transferId) {
		currentSelectedId = parseInt(transferId);
		
		// Resaltar fila activa en la tabla
		$('.tr-row').removeClass('is-active');
		$('#trRow_' + currentSelectedId).addClass('is-active');

		var d = trDetailsMap[currentSelectedId] || trDetailsMap[String(currentSelectedId)];
		if (!d) {
			// Consultar vía API si no está en cache
			$.getJSON(Grocy.BaseUrl + '/api/transfers/' + currentSelectedId).done(function(res) {
				trDetailsMap[currentSelectedId] = res;
				trDetailsMap[String(currentSelectedId)] = res;
				renderDetailPanel(res);
			});
			return;
		}

		renderDetailPanel(d);

		// En pantallas pequeñas, hacer scroll hacia el detalle
		if ($(window).width() < 1200) {
			$('html, body').animate({
				scrollTop: $('#trDetailPanel').offset().top - 80
			}, 300);
		}
	};

	// Delegación de clic en toda la fila de la tabla
	$(document).on('click', '.tr-row', function(e) {
		if ($(e.target).closest('a').length) return; // permitir clic en imprimir guía
		var id = $(this).data('id');
		if (id) {
			window.selectTransfer(id);
		}
	});

	function renderDetailPanel(d) {
		if (!d || !d.transfer) return;
		var t = d.transfer;

		$('#panelFolio').text(t.transfer_code);
		
		// Estado y badges
		var isInTransit = (t.status === 'in_transit');
		var isReceived = (t.status === 'received');

		$('#panelStatusDot')
			.removeClass('is-amber is-green')
			.addClass(isInTransit ? 'is-amber' : 'is-green');

		$('#panelBadge')
			.removeClass('badge-tr-transit badge-tr-received badge-tr-cancelled')
			.addClass(isInTransit ? 'badge-tr-transit' : (isReceived ? 'badge-tr-received' : 'badge-tr-cancelled'))
			.text(isInTransit ? 'En tránsito' : (isReceived ? 'Recibido' : 'Anulado'));

		// Ruta
		$('#panelFromBranch').text(t.from_branch_name);
		$('#panelFromLocation').text(t.from_location_name);
		$('#panelToBranch').text(t.to_branch_name);
		$('#panelToLocation').text(t.to_location_name);

		// Mini KPIs
		$('#panelProductCount').text(t.product_count + ' productos');
		$('#panelTotalCost').text('$' + parseFloat(t.total_sent_cost).toFixed(2));

		// Fechas y Usuarios
		$('#panelCreatedBy').text(t.created_by_name || 'Usuario');
		$('#panelSentAt').text(t.sent_at ? t.sent_at.substring(0, 16) : '—');
		$('#panelReceivedBy').text(t.received_by_name || 'Pendiente');
		$('#panelReceivedAt').text(t.received_at ? t.received_at.substring(0, 16) : '+ —');

		// Botón de imprimir guía
		$('#btnPanelPrint').attr('href', Grocy.BaseUrl + '/document/transfer/' + t.id);

		// Acciones según estado
		var actionsHtml = '';
		if (isInTransit) {
			actionsHtml += '<button type="button" class="tr-btn-receive-main" onclick="window.openReceiveModal(' + t.id + ')" id="btnPanelReceive">' +
				'<i class="fa-solid fa-check"></i> Recibir en destino' +
				'</button>' +
				'<div class="tr-btn-row-secondary">' +
				'<a class="tr-btn-sub-print" href="' + Grocy.BaseUrl + '/document/transfer/' + t.id + '" target="_blank">' +
				'<i class="fa-solid fa-print"></i> Imprimir guía' +
				'</a>' +
				'<button type="button" class="tr-btn-sub-cancel" onclick="window.cancelTransfer(' + t.id + ')">' +
				'<i class="fa-solid fa-trash-can"></i> Anular' +
				'</button>' +
				'</div>';
		} else {
			actionsHtml += '<div class="alert alert-success py-2 px-3 text-center small font-weight-bold mb-3" style="border-radius: 8px;">' +
				'<i class="fa-solid fa-circle-check mr-1"></i> Recepción confirmada sin diferencias' +
				'</div>' +
				'<div class="tr-btn-row-secondary">' +
				'<a class="tr-btn-sub-print" href="' + Grocy.BaseUrl + '/document/transfer/' + t.id + '" target="_blank">' +
				'<i class="fa-solid fa-print"></i> Imprimir guía' +
				'</a>' +
				'<button type="button" class="tr-btn-sub-cancel" disabled style="opacity: 0.5; cursor: not-allowed;">' +
				'<i class="fa-solid fa-ban"></i> Anular' +
				'</button>' +
				'</div>';
		}
		$('#panelActionsWrap').html(actionsHtml);
	}

	window.closeDetailPanel = function() {
		if ($(window).width() < 1200) {
			$('#trDetailPanel').fadeOut(200);
		} else {
			toastr.info('Haga clic en cualquier fila para ver otro documento.');
		}
	};

	// Modal de consulta de productos y lotes
	window.showLotsModal = function() {
		var d = trDetailsMap[currentSelectedId] || trDetailsMap[String(currentSelectedId)];
		if (!d) {
			$.getJSON(Grocy.BaseUrl + '/api/transfers/' + currentSelectedId).done(function(res) {
				trDetailsMap[currentSelectedId] = res;
				trDetailsMap[String(currentSelectedId)] = res;
				window.showLotsModal();
			});
			return;
		}

		$('#lotsModalCode').text(d.transfer.transfer_code);
		$('#lotsModalRoute').text(d.transfer.from_branch_name + ' → ' + d.transfer.to_branch_name);
		$('#lotsModalTotalCost').text('$' + parseFloat(d.transfer.total_sent_cost).toFixed(2));

		var rows = (d.lots || []).map(function(l) {
			var cost = (parseFloat(l.sent_amount) * parseFloat(l.unit_price)).toFixed(2);
			return '<tr>' +
				'<td class="font-weight-600 text-dark">' + trEsc(l.product_name) + '</td>' +
				'<td class="font-mono small">' + trEsc(l.lot_number) + '</td>' +
				'<td><span class="badge badge-light border text-muted">' + (l.best_before_date || 'N/A') + '</span></td>' +
				'<td class="text-right font-weight-600">' + parseFloat(l.sent_amount).toFixed(1) + ' ' + trEsc(l.qu_name) + '</td>' +
				'<td class="text-right text-muted">$' + parseFloat(l.unit_price).toFixed(2) + '</td>' +
				'<td class="text-right font-weight-bold text-dark">$' + cost + '</td>' +
				'</tr>';
		}).join('');

		$('#lotsModalTableBody').html(rows || '<tr><td colspan="6" class="text-center py-3 text-muted">Sin productos registrados.</td></tr>');
		$('#tr-lots-modal').modal('show');
	};

	// Modal de historial y trazabilidad
	window.showHistoryModal = function() {
		var d = trDetailsMap[currentSelectedId] || trDetailsMap[String(currentSelectedId)];
		if (!d) return;
		var t = d.transfer;

		var html = '<div class="timeline small">' +
			'<div class="mb-3 pl-3 border-left border-success position-relative">' +
			'<span class="font-weight-bold text-dark">1. Envío Registrado</span>' +
			'<div class="text-muted">' + t.sent_at + ' · Por ' + t.created_by_name + '</div>' +
			'<div class="text-secondary mt-1">Salida de ' + t.from_location_name + ' hacia tránsito (' + t.product_count + ' productos, $' + parseFloat(t.total_sent_cost).toFixed(2) + ').</div>' +
			'</div>' +
			'<div class="mb-3 pl-3 border-left border-warning position-relative">' +
			'<span class="font-weight-bold text-dark">2. Mercancía en Tránsito</span>' +
			'<div class="text-muted">Transporte refrigerado entre sucursales.</div>' +
			'<div class="text-secondary mt-1">' + (t.notes || 'Despacho logístico estándar.') + '</div>' +
			'</div>';

		if (t.status === 'received') {
			html += '<div class="mb-0 pl-3 border-left border-primary position-relative">' +
				'<span class="font-weight-bold text-dark">3. Recepción Confirmada</span>' +
				'<div class="text-muted">' + (t.received_at || '—') + ' · Por ' + (t.received_by_name || 'Destino') + '</div>' +
				'<div class="text-success mt-1">' + (t.reception_notes || 'Recepción completa sin diferencias registradas.') + '</div>' +
				'</div>';
		} else {
			html += '<div class="mb-0 pl-3 border-left border-secondary position-relative text-muted">' +
				'<span class="font-weight-bold">3. Recepción en Destino</span>' +
				'<div>Pendiente de confirmación física en ' + t.to_branch_name + '.</div>' +
				'</div>';
		}
		html += '</div>';

		$('#historyModalBody').html(html);
		$('#tr-history-modal').modal('show');
	};

	// Modal de recepción
	window.openReceiveModal = function(transferId) {
		trReceiveId = parseInt(transferId);
		var d = trDetailsMap[trReceiveId] || trDetailsMap[String(trReceiveId)];

		function setupReceive(data) {
			$('#tr-receive-code').text(data.transfer.transfer_code);
			$('#tr-receive-route').html('<strong>Ruta:</strong> ' + data.transfer.from_branch_name + ' · ' + data.transfer.from_location_name + ' <span class="mx-1">→</span> ' + data.transfer.to_branch_name + ' · ' + data.transfer.to_location_name);
			$('#tr-receive-notes').val('');
			
			$('#tr-receive-lines').html((data.products || []).map(function(p) {
				return '<tr data-product-id="' + p.product_id + '">' +
					'<td class="font-weight-600">' + trEsc(p.product_name) + '</td>' +
					'<td class="text-right text-nowrap">' + parseFloat(p.sent_amount).toFixed(1) + ' ' + trEsc(p.qu_name) + '</td>' +
					'<td class="text-right">' +
					'<input type="number" step="any" min="0" max="' + p.sent_amount + '" class="form-control form-control-sm text-right tr-received" value="' + p.sent_amount + '" required>' +
					'</td>' +
					'</tr>';
			}).join(''));

			$('#tr-receive-modal').modal('show');
		}

		if (d && d.products) {
			setupReceive(d);
		} else {
			$.getJSON(Grocy.BaseUrl + '/api/transfers/' + trReceiveId).done(function(res) {
				trDetailsMap[trReceiveId] = res;
				setupReceive(res);
			});
		}
	};

	$('#tr-receive-form').on('submit', function(e) {
		e.preventDefault();
		var items = $('#tr-receive-lines tr').map(function() {
			return { product_id: $(this).data('product-id'), received_amount: $(this).find('.tr-received').val() };
		}).get();
		trPost('/transfers/' + trReceiveId + '/receive', { items: items, notes: $('#tr-receive-notes').val() }, 'Traslado recibido exitosamente en destino');
	});

	// Anulación de traslado
	window.cancelTransfer = function(transferId) {
		bootbox.prompt({
			title: 'Anular traslado: el stock volverá a la ubicación de origen. Indique el motivo:',
			callback: function(reason) {
				if (reason === null) return;
				if (!reason.trim()) { toastr.warning('Debe indicar un motivo de anulación'); return false; }
				trPost('/transfers/' + transferId + '/cancel', { reason: reason }, 'Traslado anulado correctamente');
			}
		});
	};

	// Exportar a CSV
	window.exportTransfersCsv = function() {
		var csv = 'Folio,Origen,Destino,Productos,Valor,Estado,Envio,FechaEnvio,Recepcion,FechaRecepcion\n';
		$('#trTableBody tr.tr-row').each(function() {
			var code = $(this).find('.tr-folio').text().trim();
			var origin = $(this).data('origin');
			var dest = $(this).data('dest');
			var products = $(this).find('td:nth-child(3)').text().trim();
			var val = $(this).find('td:nth-child(4)').text().trim().replace('$', '');
			var status = $(this).find('.badge-tr-status').text().trim();
			var sent = $(this).find('td:nth-child(7) div:first-child').text().trim();
			var sentDate = $(this).find('td:nth-child(7) div:last-child').text().trim();
			var received = $(this).find('td:nth-child(8) div:first-child').text().trim();
			var receivedDate = $(this).find('td:nth-child(8) div:last-child').text().trim();
			csv += '"' + code + '","' + origin + '","' + dest + '","' + products + '","' + val + '","' + status + '","' + sent + '","' + sentDate + '","' + received + '","' + receivedDate + '"\n';
		});
		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement("a");
		link.href = URL.createObjectURL(blob);
		link.setAttribute("download", "traslados_entre_sucursales_" + new Date().toISOString().substring(0,10) + ".csv");
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	};

	// Sistema de filtros instantáneos
	function applyTableFilters() {
		var q = ($('#trSearchFilter').val() || '').toLowerCase().trim();
		var date = $('#trDateFilter').val();
		var status = $('#trStatusSelect').val();
		var origin = ($('#trOriginFilter').val() || '').toLowerCase();
		var dest = ($('#trDestFilter').val() || '').toLowerCase();

		var visibleCount = 0;
		$('#trTableBody tr.tr-row').each(function() {
			var row = $(this);
			var text = row.text().toLowerCase();
			var rowStatus = row.data('status');
			var rowOrigin = (row.data('origin') || '').toLowerCase();
			var rowDest = (row.data('dest') || '').toLowerCase();
			var rowDate = row.data('date') || '';

			var matchQ = !q || text.indexOf(q) !== -1;
			var matchDate = !date || rowDate === date;
			var matchStatus = !status || rowStatus === status;
			var matchOrigin = !origin || rowOrigin === origin;
			var matchDest = !dest || rowDest === dest;

			if (matchQ && matchDate && matchStatus && matchOrigin && matchDest) {
				row.show();
				visibleCount++;
			} else {
				row.hide();
			}
		});

		$('#trTableCountLabel').text(visibleCount + ' documentos ' + (visibleCount === 1 ? 'único' : 'únicos'));
	}

	$('#trSearchFilter, #trDateFilter').on('input change', applyTableFilters);
	$('#trStatusSelect, #trOriginFilter, #trDestFilter').on('change', applyTableFilters);

	// Filtros por píldora
	$('.tr-pill-tab').on('click', function() {
		$('.tr-pill-tab').removeClass('is-active');
		$(this).addClass('is-active');
		var st = $(this).data('status');
		$('#trStatusSelect').val(st);
		applyTableFilters();
	});

	// Modal Nuevo Traslado - lógica de selección
	function trProductOptions() {
		var loc = $('#tr-from').val();
		return '<option value="">Seleccione producto...</option>' + trStock.filter(function(s) { return s.location_id == loc; }).map(function(s) {
			return '<option value="' + s.product_id + '" data-available="' + s.amount + '" data-unit="' + trEsc(s.qu_name) + '">' + trEsc(s.product_name) + '</option>';
		}).join('');
	}

	$('#tr-from').on('change', function() {
		var branch = $(this).find(':selected').data('branch');
		$('#tr-to option').each(function() {
			var same = this.value !== '' && $(this).data('branch') == branch;
			$(this).prop('disabled', same).toggle(!same);
		});
		if ($('#tr-to :selected').prop('disabled')) $('#tr-to').val('');
		$('#tr-lines').empty();
		$('#tr-add-line').prop('disabled', !this.value);
		if (this.value) $('#tr-add-line').trigger('click');
	});

	$('#tr-add-line').on('click', function() {
		$('#tr-lines').append('<tr>' +
			'<td><select class="custom-select custom-select-sm tr-product" required>' + trProductOptions() + '</select></td>' +
			'<td class="text-right text-nowrap tr-available font-weight-600 text-muted">—</td>' +
			'<td><input type="number" step="any" min="0.01" class="form-control form-control-sm tr-amount" placeholder="Cant." required></td>' +
			'<td class="text-center"><button type="button" class="btn btn-xs btn-link text-danger tr-remove p-0"><i class="fa-solid fa-xmark"></i></button></td>' +
			'</tr>');
	});

	$('#tr-lines').on('change', '.tr-product', function() {
		var opt = $(this).find(':selected');
		var row = $(this).closest('tr');
		row.find('.tr-available').text(opt.val() ? opt.data('available') + ' ' + opt.data('unit') : '—');
		row.find('.tr-amount').attr('max', opt.data('available') || null);
	}).on('click', '.tr-remove', function() {
		$(this).closest('tr').remove();
	});

	$('#tr-new-form').on('submit', function(e) {
		e.preventDefault();
		var items = $('#tr-lines tr').map(function() {
			return { product_id: $(this).find('.tr-product').val(), amount: $(this).find('.tr-amount').val() };
		}).get();

		if (!items.length) {
			toastr.warning('Agregue al menos un producto a trasladar');
			return;
		}

		trPost('/transfers', {
			from_location_id: $('#tr-from').val(),
			to_location_id: $('#tr-to').val(),
			items: items,
			notes: $('#tr-notes').val()
		}, 'Traslado enviado y puesto en tránsito');
	});
</script>
@endpush
