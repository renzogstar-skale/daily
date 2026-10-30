@extends('layout.default')

@section('title', 'Pedidos de clientes')

@push('pageStyles')
<link href="{{ $U('/css/daily_orders.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@section('content')
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$monthsEs = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
	$fmtDateShort = function (?string $d) use ($monthsEs): string {
		if (!$d || strlen($d) < 10) return 'Sin fecha';
		$ts = strtotime(substr($d, 0, 10));
		if (!$ts) return substr($d, 0, 10);
		return intval(date('d', $ts)) . ' ' . ($monthsEs[intval(date('n', $ts))] ?? date('M', $ts)) . ' ' . date('Y', $ts);
	};
	$fmtDateDayMonth = function (?string $d) use ($monthsEs): string {
		if (!$d || strlen($d) < 10) return 'Sin fecha';
		$ts = strtotime(substr($d, 0, 10));
		if (!$ts) return substr($d, 0, 10);
		return intval(date('d', $ts)) . ' ' . ($monthsEs[intval(date('n', $ts))] ?? date('M', $ts));
	};
	$fmtStepTime = function (?string $dt) use ($monthsEs): string {
		if (!$dt || strlen($dt) < 10) return 'Pendiente';
		$ts = strtotime($dt);
		if (!$ts) return substr($dt, 0, 16);
		return intval(date('d', $ts)) . ' ' . ($monthsEs[intval(date('n', $ts))] ?? date('M', $ts)) . ', ' . date('H:i', $ts);
	};

	// Conjunto inicial visible (por defecto todos los pedidos de la sucursal o el filtro activo)
	$visibleOrders = $orders;
	$openOrders = array_values(array_filter($allOrders, fn($o) => !in_array($o['status'], ['dispatched', 'cancelled'], true)));
	$toPrepareOrders = array_values(array_filter($allOrders, fn($o) => in_array($o['status'], ['confirmed', 'picking'], true)));
	$toDispatchOrders = array_values(array_filter($allOrders, fn($o) => in_array($o['status'], ['picked', 'packed'], true)));
	$openValueSum = array_sum(array_map(fn($o) => floatval($o['total']), $openOrders));

	// Prioridades operativas calculadas sobre pedidos abiertos reales
	$refToday = date('Y-m-d');
	// Si las fechas demo son de octubre 2026 (ej. 2026-10-24 / 2026-10-25), identificar entregas del día compromiso más próximo y atrasados
	$minOpenReqDate = null;
	foreach ($openOrders as $oo) {
		if (!empty($oo['requested_date'])) {
			if ($minOpenReqDate === null || $oo['requested_date'] < $minOpenReqDate) {
				$minOpenReqDate = $oo['requested_date'];
			}
		}
	}
	$dispatchTodayCount = count($toDispatchOrders);
	$overdueOrders = array_values(array_filter($openOrders, fn($o) => !empty($o['requested_date']) && $o['requested_date'] < ($minOpenReqDate ?? $refToday)));
	$overdueCount = count($overdueOrders);
	$readyForDispatchCount = count($toDispatchOrders);
	$shortageOrders = array_values(array_filter($openOrders, fn($o) => !empty($o['has_shortage'])));
	$shortageCount = count($shortageOrders);
@endphp

<div class="orders-page-wrapper">
	<!-- CABECERA PRINCIPAL -->
	<div class="orders-top-header">
		<div class="orders-title-group">
			<div class="orders-title-icon">
				<i class="fa-solid fa-boxes-packing"></i>
			</div>
			<div class="orders-title-text">
				<h1>Pedidos de clientes</h1>
				<p>Confirma, prepara y despacha los pedidos de tus clientes con reserva de inventario y trazabilidad FEFO.</p>
			</div>
		</div>

		<div class="orders-header-actions">
			@if($can['manage'])
				<button type="button" class="orders-btn-new" id="or-new">
					<i class="fa-solid fa-plus"></i>
					<span>Nuevo pedido</span>
				</button>
			@endif

			<div class="dropdown">
				<button type="button" class="orders-btn-export dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
					<i class="fa-solid fa-download text-slate-600"></i>
					<span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius:10px; font-size:0.82rem;">
					<a class="dropdown-item py-2" href="#" id="btn-export-orders-csv">
						<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar vista actual (CSV)
					</a>
					<a class="dropdown-item py-2" href="#" id="btn-export-orders-tsv">
						<i class="fa-solid fa-file-excel text-primary mr-2"></i>Exportar para Excel (TSV)
					</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item py-2" href="#" id="btn-export-orders-print">
						<i class="fa-solid fa-print text-secondary mr-2"></i>Imprimir / Guardar PDF
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- BARRA DE BÚSQUEDA Y FILTROS -->
	<div class="orders-filter-card">
		<div class="orders-search-wrap">
			<i class="fa-solid fa-magnifying-glass"></i>
			<input type="text"
				id="orders-search-input"
				class="orders-search-input"
				placeholder="Buscar por pedido, cliente o producto..."
				autocomplete="off">
		</div>

		<div class="orders-filter-pill">
			<span class="fp-icon"><i class="fa-solid fa-store"></i></span>
			<div class="fp-meta">
				<span class="fp-label">Sucursal</span>
				<select id="filter-branch" aria-label="Filtrar por sucursal">
					<option value="">Todas</option>
					@foreach($branches as $b)
						<option value="{{ $b['id'] }}" {{ intval($b['id']) === intval($branchId) ? 'selected' : '' }}>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="orders-filter-pill">
			<span class="fp-icon"><i class="fa-regular fa-file-lines"></i></span>
			<div class="fp-meta">
				<span class="fp-label">Estado</span>
				<select id="filter-status" aria-label="Filtrar por estado">
					<option value="open" {{ $status === 'open' ? 'selected' : '' }}>Todos (Abiertos)</option>
					<option value="all" {{ $status === 'all' ? 'selected' : '' }}>Todos (Incl. cerrados)</option>
					<option value="to_prepare" {{ $status === 'to_prepare' ? 'selected' : '' }}>Por preparar</option>
					<option value="to_dispatch" {{ $status === 'to_dispatch' ? 'selected' : '' }}>Por despachar</option>
					@foreach(\Grocy\Services\CustomerOrderService::STATUS as $k => $l)
						<option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $l }}</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="orders-filter-pill">
			<span class="fp-icon"><i class="fa-regular fa-calendar"></i></span>
			<div class="fp-meta">
				<span class="fp-label">Fecha</span>
				<select id="filter-date" aria-label="Filtrar por fecha">
					<option value="30d" selected>Últimos 30 días</option>
					<option value="all">Todas las fechas</option>
					<option value="today">Entrega prioritaria / hoy</option>
					<option value="overdue">Con retraso</option>
				</select>
			</div>
		</div>

		<div class="orders-filter-pill">
			<span class="fp-icon"><i class="fa-regular fa-credit-card"></i></span>
			<div class="fp-meta">
				<span class="fp-label">Pago</span>
				<select id="filter-payment" aria-label="Filtrar por estado de pago">
					<option value="all" selected>Todos</option>
					<option value="pending">Por facturar (Reserva)</option>
					<option value="invoiced">Facturado al despacho</option>
					<option value="cancelled">Anulado</option>
				</select>
			</div>
		</div>

		<div class="orders-filter-pill">
			<span class="fp-icon"><i class="fa-solid fa-truck"></i></span>
			<div class="fp-meta">
				<span class="fp-label">Entrega</span>
				<select id="filter-delivery" aria-label="Filtrar por modalidad de entrega">
					<option value="all" selected>Todos</option>
					<option value="pickup">Retiro en tienda</option>
					<option value="delivery">Domicilio / Ruta</option>
				</select>
			</div>
		</div>

		<button type="button" class="orders-btn-clear" id="btn-clear-filters" title="Restablecer todos los filtros">
			<i class="fa-solid fa-rotate-left"></i>
			<span>Limpiar</span>
		</button>
	</div>

	<!-- TARJETAS KPI DE RESUMEN -->
	<div class="orders-kpi-grid">
		<div class="orders-kpi-card" data-kpi-filter="open" title="Clic para filtrar pedidos abiertos sin despachar">
			<div class="orders-kpi-icon tone-green">
				<i class="fa-solid fa-file-invoice"></i>
			</div>
			<div class="orders-kpi-body">
				<div class="orders-kpi-label">Abiertos</div>
				<div class="orders-kpi-value" id="kpi-open-count">{{ count($openOrders) }}</div>
				<div class="orders-kpi-sub">sin despachar</div>
			</div>
		</div>

		<div class="orders-kpi-card" data-kpi-filter="to_prepare" title="Clic para filtrar pedidos confirmados pendientes de picking">
			<div class="orders-kpi-icon tone-amber">
				<i class="fa-solid fa-barcode"></i>
			</div>
			<div class="orders-kpi-body">
				<div class="orders-kpi-label">Por preparar</div>
				<div class="orders-kpi-value" id="kpi-prepare-count">{{ count($toPrepareOrders) }}</div>
				<div class="orders-kpi-sub">confirmados</div>
			</div>
		</div>

		<div class="orders-kpi-card" data-kpi-filter="to_dispatch" title="Clic para filtrar pedidos preparados o empacados listos para despacho">
			<div class="orders-kpi-icon tone-blue">
				<i class="fa-solid fa-truck"></i>
			</div>
			<div class="orders-kpi-body">
				<div class="orders-kpi-label">Por despachar</div>
				<div class="orders-kpi-value" id="kpi-dispatch-count">{{ count($toDispatchOrders) }}</div>
				<div class="orders-kpi-sub">preparados / empacados</div>
			</div>
		</div>

		<div class="orders-kpi-card" data-kpi-filter="open" title="Valor monetario de los pedidos abiertos a precio de lista">
			<div class="orders-kpi-icon tone-green">
				<i class="fa-solid fa-sack-dollar"></i>
			</div>
			<div class="orders-kpi-body">
				<div class="orders-kpi-label">Valor abierto</div>
				<div class="orders-kpi-value" id="kpi-open-value">{{ $money($openValueSum) }}</div>
				<div class="orders-kpi-sub">a precio de pedido</div>
			</div>
		</div>
	</div>

	<!-- SECCIÓN PRINCIPAL: LISTA DE PEDIDOS (IZQUIERDA) + DETALLE DEL PEDIDO (DERECHA) -->
	<div class="orders-main-grid">
		<!-- COLUMNA IZQUIERDA: LISTA DE PEDIDOS -->
		<div class="orders-panel-card">
			<div class="orders-list-header">
				<h2 class="orders-list-title" id="orders-list-heading">Pedidos ({{ count($visibleOrders) }})</h2>
				<div class="d-flex align-items-center" style="gap:6px;">
					<span class="small text-muted" style="font-size:0.72rem;">Ordenar:</span>
					<select id="orders-sort-select" class="orders-sort-select" aria-label="Ordenar pedidos">
						<option value="recent" selected>Más recientes</option>
						<option value="delivery_asc">Fecha de entrega</option>
						<option value="total_desc">Mayor importe</option>
					</select>
				</div>
			</div>

			<div class="orders-table-wrap">
				<table class="orders-table">
					<thead>
						<tr>
							<th>PEDIDO</th>
							<th>CLIENTE</th>
							<th>FECHA</th>
							<th class="text-right">TOTAL</th>
							<th class="text-right">ESTADO</th>
						</tr>
					</thead>
					<tbody id="orders-list-tbody">
						@forelse($visibleOrders as $o)
							@php
								$isSelected = $selected && intval($selected['order']['id']) === intval($o['id']);
							@endphp
							<tr class="order-row {{ $isSelected ? 'is-selected' : '' }}" data-id="{{ $o['id'] }}">
								<td>
									<span class="order-code-link">{{ $o['order_code'] }}</span>
									<div class="order-sub-meta">{{ $o['branch_name'] }} · {{ $o['lines'] }} línea(s)</div>
								</td>
								<td>
									<div class="font-weight-bold text-slate-800">{{ $o['customer_name'] }}</div>
									<div class="order-sub-meta">{{ $o['branch_name'] }} · {{ $o['delivery_type_label'] ?? 'Retiro en tienda' }}</div>
								</td>
								<td>
									<div class="font-weight-bold text-slate-800">{{ $fmtDateShort($o['created_at']) }}</div>
									<div class="order-sub-meta">
										@if(!empty($o['requested_date']))
											Entrega {{ $fmtDateDayMonth($o['requested_date']) }}
										@else
											Sin fecha compromiso
										@endif
									</div>
								</td>
								<td class="text-right">
									<div class="font-weight-bold text-slate-900">{{ $money($o['total']) }}</div>
									<div class="order-sub-meta">{{ $o['payment_label'] ?? 'Por facturar' }}</div>
								</td>
								<td class="text-right">
									<div class="d-inline-flex align-items-center" style="gap:8px;">
										<span class="order-status-pill st-{{ $o['status'] }}">{{ $o['status_label'] }}</span>
										<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="5" class="text-center text-muted py-5">
									<i class="fa-solid fa-inbox fa-2x mb-2 d-block text-slate-300"></i>
									No se encontraron pedidos con los filtros seleccionados.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- COLUMNA DERECHA: DETALLE Y SEGUIMIENTO DEL PEDIDO SELECCIONADO -->
		<div class="orders-panel-card" id="or-detail" data-id="{{ $selected ? $selected['order']['id'] : '' }}">
			<div class="orders-detail-body" id="orders-detail-content">
				@if(!$selected)
					<div class="text-center py-5 my-auto text-muted">
						<i class="fa-solid fa-box-open fa-2x mb-2 d-block text-slate-300"></i>
						<div class="font-weight-bold text-slate-700">Ningún pedido seleccionado</div>
						<div class="small mt-1">Selecciona un pedido de la lista izquierda para consultar su disponibilidad, reserva FEFO y acciones operativas.</div>
					</div>
				@else
					@php
						$o = $selected['order'];
						$items = $selected['items'];
						$picks = $selected['picks'];
						$stepOrder = ['confirmed' => 1, 'picking' => 1, 'picked' => 2, 'packed' => 3, 'dispatching' => 4, 'dispatched' => 4];
						$currRank = $stepOrder[$o['status']] ?? 0;
					@endphp

					<!-- Encabezado del Detalle -->
					<div class="orders-detail-top">
						<div>
							<div class="orders-detail-code-row">
								<h3 class="orders-detail-code">{{ $o['order_code'] }}</h3>
								<span class="order-status-pill st-{{ $o['status'] }}">{{ $o['status_label'] }}</span>
								@if(!empty($o['has_shortage']))
									<span class="badge badge-warning font-weight-bold">Con diferencia / faltante</span>
								@endif
							</div>
							<div class="orders-detail-customer">
								<strong>{{ $o['customer_name'] }}</strong> · {{ $o['branch_name'] }}
							</div>
							<div class="orders-detail-dates">
								Creado el {{ $fmtStepTime($o['created_at']) }} · Entrega estimada: {{ !empty($o['requested_date']) ? $fmtDateShort($o['requested_date']) : 'Sin fecha comprometida' }}
							</div>
						</div>

						<!-- Menú Más Acciones -->
						<div class="dropdown">
							<button type="button" class="orders-btn-more dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
								<i class="fa-solid fa-ellipsis"></i>
								<span>Más acciones</span>
							</button>
							<div class="dropdown-menu dropdown-menu-right shadow border-0" style="border-radius:11px; font-size:0.81rem; min-width:230px;">
								@if($o['status'] === 'draft' && $can['manage'])
									<a class="dropdown-item py-2 or-act" href="#" data-action="confirm" data-id="{{ $o['id'] }}">
										<i class="fa-solid fa-lock text-primary mr-2"></i>Confirmar y reservar stock
									</a>
								@endif
								@if(in_array($o['status'], ['confirmed', 'picking'], true) && $can['pick'])
									<a class="dropdown-item py-2" href="{{ $U('/scan?pick=' . $o['id']) }}">
										<i class="fa-solid fa-barcode text-warning mr-2"></i>Preparar con escáner (Picking)
									</a>
									<a class="dropdown-item py-2 or-act" href="#" data-action="pick-fefo" data-id="{{ $o['id'] }}">
										<i class="fa-solid fa-bolt text-success mr-2"></i>Asignar lotes FEFO sugeridos
									</a>
								@endif
								@if($o['status'] === 'picked' && $can['pick'])
									<a class="dropdown-item py-2" href="#" id="or-pack" data-id="{{ $o['id'] }}">
										<i class="fa-solid fa-box text-primary mr-2"></i>Registrar empaque (Bultos)
									</a>
								@endif
								@if($o['status'] === 'packed' && $can['dispatch'])
									<a class="dropdown-item py-2 or-act" href="#" data-action="dispatch" data-id="{{ $o['id'] }}">
										<i class="fa-solid fa-truck-fast text-success mr-2"></i>Despachar y generar venta
									</a>
								@endif
								<a class="dropdown-item py-2" href="#" id="btn-menu-prep-detail" data-id="{{ $o['id'] }}">
									<i class="fa-solid fa-clipboard-check text-info mr-2"></i>Ver lotes FEFO e historial
								</a>
								<a class="dropdown-item py-2" href="{{ $U('/dispatch?id=' . $o['id']) }}">
									<i class="fa-solid fa-route text-secondary mr-2"></i>Abrir en módulo de Despachos
								</a>
								@if(!empty($o['sale_id']))
									<a class="dropdown-item py-2" href="{{ $U('/sales?id=' . $o['sale_id']) }}">
										<i class="fa-solid fa-receipt text-success mr-2"></i>Ver comprobante ({{ $o['sale_number'] }})
									</a>
								@endif
								@if(!in_array($o['status'], ['dispatched', 'dispatching', 'cancelled'], true) && $can['manage'])
									<div class="dropdown-divider"></div>
									<a class="dropdown-item py-2 text-danger" href="#" id="or-cancel" data-id="{{ $o['id'] }}">
										<i class="fa-solid fa-ban mr-2"></i>Anular pedido y liberar reserva
									</a>
								@endif
							</div>
						</div>
					</div>

					<!-- Línea de Progreso del Pedido -->
					@if($o['status'] === 'cancelled')
						<div class="alert alert-danger mb-0 py-2 px-3 small" style="border-radius:10px;">
							<i class="fa-solid fa-ban mr-1"></i>
							<strong>Pedido anulado:</strong> {{ $o['cancel_reason'] ?: 'Sin motivo especificado' }}
							({{ $fmtStepTime($o['cancelled_at']) }}) · Reserva de inventario liberada.
						</div>
					@else
						<div class="orders-stepper-wrap">
							<div class="orders-stepper">
								@if($o['status'] === 'draft')
									<div class="orders-step-item is-current">
										<div class="orders-step-circle"><i class="fa-solid fa-pen"></i></div>
										<div class="orders-step-title">Borrador</div>
										<div class="orders-step-time">{{ $fmtStepTime($o['created_at']) }}</div>
									</div>
								@endif

								<div class="orders-step-item {{ $currRank >= 1 ? 'is-done' : 'is-pending' }}">
									<div class="orders-step-circle">
										<i class="fa-solid {{ $currRank >= 1 ? 'fa-check' : 'fa-clock' }}"></i>
									</div>
									<div class="orders-step-title">Confirmado</div>
									<div class="orders-step-time">{{ !empty($o['confirmed_at']) ? $fmtStepTime($o['confirmed_at']) : 'Reserva stock' }}</div>
								</div>

								<div class="orders-step-item {{ $currRank >= 2 ? 'is-done' : ($o['status'] === 'picking' ? 'is-current' : 'is-pending') }}">
									<div class="orders-step-circle">
										<i class="fa-solid {{ $currRank >= 2 ? 'fa-check' : 'fa-barcode' }}"></i>
									</div>
									<div class="orders-step-title">{{ $o['status'] === 'picking' ? 'En preparación' : 'Preparado' }}</div>
									<div class="orders-step-time">{{ !empty($o['picked_at']) ? $fmtStepTime($o['picked_at']) : 'Picking FEFO' }}</div>
								</div>

								<div class="orders-step-item {{ $currRank >= 3 ? 'is-done' : 'is-pending' }}">
									<div class="orders-step-circle">
										<i class="fa-solid {{ $currRank >= 3 ? 'fa-check' : 'fa-box' }}"></i>
									</div>
									<div class="orders-step-title">Empacado</div>
									<div class="orders-step-time">
										@if(!empty($o['packed_at']))
											{{ $fmtStepTime($o['packed_at']) }}
										@elseif(!empty($o['packages']))
											{{ $o['packages'] }} bulto(s)
										@else
											Pendiente
										@endif
									</div>
								</div>

								<div class="orders-step-item {{ $currRank >= 4 ? 'is-done' : 'is-pending' }}">
									<div class="orders-step-circle">
										<i class="fa-solid {{ $currRank >= 4 ? 'fa-check' : 'fa-clock' }}"></i>
									</div>
									<div class="orders-step-title">Despachado</div>
									<div class="orders-step-time">{{ !empty($o['dispatched_at']) ? $fmtStepTime($o['dispatched_at']) : 'Venta y Kardex' }}</div>
								</div>
							</div>
						</div>
					@endif

					<!-- Cuerpo Dividido: Tabla de Productos (Izquierda) + Resumen del Pedido (Derecha) -->
					<div class="orders-detail-split">
						<div>
							<div class="d-flex align-items-center justify-content-between mb-2">
								<div class="orders-prod-box-title mb-0">Productos ({{ count($items) }})</div>
								@if(!empty($o['notes']))
									<span class="small text-muted" title="{{ $o['notes'] }}">
										<i class="fa-regular fa-comment-dots mr-1"></i>{{ \Illuminate\Support\Str::limit($o['notes'], 48) }}
									</span>
								@endif
							</div>

							<div class="orders-prod-table-wrap">
								<table class="orders-prod-table">
									<thead>
										<tr>
											<th>PRODUCTO</th>
											<th class="text-center">CANTIDAD</th>
											<th class="text-center">DISPONIBILIDAD</th>
											<th class="text-right">RESERVA</th>
										</tr>
									</thead>
									<tbody>
										@foreach($items as $it)
											@php
												$unitShort = mb_strtolower((string)($it['unit_name'] ?? 'pza'), 'UTF-8');
												$phys = floatval($it['physical_available'] ?? 0);
												$req = floatval($it['amount'] ?? 0);
												$picked = floatval($it['picked_amount'] ?? 0);
												$res = floatval($it['reserved_amount'] ?? 0);
												$dotClass = $phys <= 0 ? 'is-zero' : ($phys < $req ? 'is-low' : '');
												$firstPick = !empty($it['item_picks']) ? $it['item_picks'][0] : null;
												$firstSug = !empty($it['suggested']) ? $it['suggested'][0] : null;
											@endphp
											<tr>
												<td>
													<div class="orders-prod-cell">
														<img src="{{ $U($it['image_url']) }}" alt="{{ $it['product_name'] }}" class="orders-prod-thumb">
														<div>
															<div class="font-weight-bold text-slate-900">{{ $it['product_name'] }}</div>
															<div class="small text-muted" style="font-size:0.68rem;">
																{{ $money($it['unit_price']) }} / {{ $unitShort }}
																@if($firstPick)
																	· <span class="text-success font-weight-bold">Lote {{ $firstPick['lot_number'] ?: 'FEFO' }}</span>
																@elseif($firstSug)
																	· <span class="text-info">Sug. FEFO: {{ $firstSug['lot_number'] ?: 'Lote' }} (vence {{ $firstSug['best_before_date'] === '2999-12-31' ? 'N/A' : $firstSug['best_before_date'] }})</span>
																@endif
															</div>
														</div>
													</div>
												</td>
												<td class="text-center font-weight-bold">
													{{ round($req, 2) }} {{ $unitShort }}
													@if(in_array($o['status'], ['picked', 'packed', 'dispatched'], true) && abs($picked - $req) > 0.001)
														<div class="small text-warning font-weight-bold">Prep: {{ round($picked, 2) }}</div>
													@endif
												</td>
												<td class="text-center">
													<span class="orders-avail-dot {{ $dotClass }}"></span>
													<span class="text-slate-700 font-weight-bold">{{ round($phys, 2) }} {{ $unitShort }}</span>
												</td>
												<td class="text-right font-weight-bold">
													@if($res > 0)
														{{ round($res, 2) }} {{ $unitShort }}
													@elseif($o['status'] === 'dispatched')
														<span class="text-success small font-weight-bold">Despachado</span>
													@else
														<span class="text-muted small">0 {{ $unitShort }}</span>
													@endif
												</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>

						<!-- Tarjeta Lateral de Resumen -->
						<div class="orders-summary-sidebox">
							<div class="orders-sum-block">
								<div class="sum-label">Total del pedido</div>
								<div class="sum-total">{{ $money($selected['total']) }}</div>
								<div class="small text-muted mt-1" style="font-size:0.68rem;">
									Subtotal: {{ $money($selected['total']) }} · Desc: $0.00
								</div>
							</div>

							<div class="orders-sum-meta-item">
								<i class="fa-regular fa-calendar"></i>
								<div>
									<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Fecha de entrega</div>
									<div class="font-weight-bold text-slate-900">
										{{ !empty($o['requested_date']) ? $fmtDateShort($o['requested_date']) : 'Sin fecha' }}
									</div>
								</div>
							</div>

							<div class="orders-sum-meta-item">
								<i class="fa-regular fa-credit-card"></i>
								<div>
									<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Estado de pago</div>
									<span class="order-status-pill {{ $o['payment_status'] === 'invoiced' ? 'st-dispatched' : ($o['payment_status'] === 'cancelled' ? 'st-cancelled' : 'st-confirmed') }}" style="margin-top:3px;">
										{{ $o['payment_label'] }}
									</span>
								</div>
							</div>

							<div class="orders-sum-meta-item">
								<i class="fa-solid fa-box-archive"></i>
								<div>
									<div class="text-muted" style="font-size:0.69rem; font-weight:600;">Entrega y empaque</div>
									<div class="font-weight-bold text-slate-800" style="font-size:0.76rem;">
										{{ $o['delivery_type_label'] }}
										@if(!empty($o['packages']))
											· {{ $o['packages'] }} bulto(s)
										@endif
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Pie de Acciones del Detalle -->
					<div class="orders-detail-footer">
						<button type="button" class="orders-btn-secondary-act" id="btn-open-prep-modal" data-id="{{ $o['id'] }}">
							<i class="fa-solid fa-clipboard-list text-slate-600"></i>
							<span>Ver preparación</span>
						</button>

						@if($o['status'] === 'draft' && $can['manage'])
							<button type="button" class="orders-btn-primary-act or-act" data-action="confirm" data-id="{{ $o['id'] }}">
								<i class="fa-solid fa-lock"></i>
								<span>Confirmar y reservar</span>
							</button>
						@elseif(in_array($o['status'], ['confirmed', 'picking'], true) && $can['pick'])
							<a href="{{ $U('/scan?pick=' . $o['id']) }}" class="orders-btn-secondary-act">
								<i class="fa-solid fa-barcode text-warning"></i>
								<span>Picking con escáner</span>
							</a>
							<button type="button" class="orders-btn-primary-act or-act" data-action="pick-fefo" data-id="{{ $o['id'] }}">
								<i class="fa-solid fa-bolt"></i>
								<span>Completar preparación FEFO</span>
							</button>
						@elseif($o['status'] === 'picked' && $can['pick'])
							<button type="button" class="orders-btn-primary-act" id="btn-footer-pack" data-id="{{ $o['id'] }}">
								<i class="fa-solid fa-box"></i>
								<span>Empacar pedido</span>
							</button>
						@elseif($o['status'] === 'packed' && $can['dispatch'])
							<button type="button" class="orders-btn-primary-act" id="btn-prepare-dispatch" data-id="{{ $o['id'] }}">
								<i class="fa-solid fa-truck"></i>
								<span>Preparar despacho</span>
							</button>
						@elseif($o['status'] === 'dispatched' && !empty($o['sale_id']))
							<a href="{{ $U('/sales?id=' . $o['sale_id']) }}" class="orders-btn-primary-act">
								<i class="fa-solid fa-receipt"></i>
								<span>Ver comprobante {{ $o['sale_number'] }}</span>
							</a>
						@endif
					</div>
				@endif
			</div>
		</div>
	</div>

	<!-- FILA INFERIOR: PRIORIDADES OPERATIVAS (IZQUIERDA) + ACCESOS RÁPIDOS (DERECHA) -->
	<div class="orders-bottom-grid">
		<!-- Prioridades Operativas -->
		<div class="orders-bottom-card">
			<div class="orders-bottom-head">
				<h3 class="orders-bottom-title">
					<i class="fa-solid fa-chart-simple text-success"></i>
					<span>Prioridades operativas</span>
				</h3>
				<a href="#" class="small font-weight-bold text-primary" id="btn-view-all-priorities">Ver todas</a>
			</div>

			<div class="orders-priorities-row">
				<div class="orders-priority-pill" data-priority-filter="to_dispatch" title="Filtrar pedidos listos o comprometidos para despachar hoy">
					<div class="orders-priority-left">
						<div class="orders-priority-icon" style="background:#fef3c7; color:#d97706;">
							<i class="fa-solid fa-truck"></i>
						</div>
						<div>
							<div class="font-weight-bold" style="color:#d97706; font-size:0.84rem;" id="prio-today-count">{{ $dispatchTodayCount }} pedidos</div>
							<div class="text-muted" style="font-size:0.71rem;">por despachar hoy</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
				</div>

				<div class="orders-priority-pill" data-priority-filter="overdue" title="Filtrar pedidos con compromiso vencido o faltantes">
					<div class="orders-priority-left">
						<div class="orders-priority-icon" style="background:#fee2e2; color:#dc2626;">
							<i class="fa-solid fa-clock"></i>
						</div>
						<div>
							<div class="font-weight-bold text-slate-900" style="font-size:0.84rem;" id="prio-overdue-count">{{ $overdueCount }} pedidos</div>
							<div class="text-muted" style="font-size:0.71rem;">en retraso{{ $shortageCount > 0 ? ' · ' . $shortageCount . ' con faltante' : '' }}</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
				</div>

				<div class="orders-priority-pill" data-priority-filter="ready" title="Filtrar pedidos preparados y empacados listos para salir">
					<div class="orders-priority-left">
						<div class="orders-priority-icon" style="background:#dbeafe; color:#2563eb;">
							<i class="fa-solid fa-truck-ramp-box"></i>
						</div>
						<div>
							<div class="font-weight-bold text-slate-900" style="font-size:0.84rem;" id="prio-ready-count">{{ $readyForDispatchCount }} pedidos</div>
							<div class="text-muted" style="font-size:0.71rem;">listos para despacho</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
				</div>
			</div>
		</div>

		<!-- Accesos Rápidos a Módulos Relacionados -->
		<div class="orders-bottom-card">
			<div class="orders-bottom-head">
				<h3 class="orders-bottom-title">
					<i class="fa-solid fa-grip text-success"></i>
					<span>Accesos rápidos</span>
				</h3>
				<a href="{{ $U('/customers') }}" class="small font-weight-bold text-success">
					<i class="fa-solid fa-users mr-1"></i>Clientes y Cuentas
				</a>
			</div>

			<div class="orders-quicklinks-row">
				<a href="{{ $U('/stockoverview') }}" class="orders-quicklink-card">
					<div class="d-flex align-items-center" style="gap:9px; min-width:0;">
						<div class="orders-ql-icon" style="background:#dcfce7; color:#16a34a;">
							<i class="fa-solid fa-boxes-stacked"></i>
						</div>
						<div style="min-width:0;">
							<div class="font-weight-bold text-slate-900" style="font-size:0.78rem;">Inventario</div>
							<div class="text-muted text-truncate" style="font-size:0.68rem;">Ver existencias</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.68rem;"></i>
				</a>

				<a href="{{ $U('/scan') }}" class="orders-quicklink-card">
					<div class="d-flex align-items-center" style="gap:9px; min-width:0;">
						<div class="orders-ql-icon" style="background:#dbeafe; color:#2563eb;">
							<i class="fa-solid fa-barcode"></i>
						</div>
						<div style="min-width:0;">
							<div class="font-weight-bold text-slate-900" style="font-size:0.78rem;">Operaciones con escáner</div>
							<div class="text-muted text-truncate" style="font-size:0.68rem;">Picking y empaque</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.68rem;"></i>
				</a>

				<a href="{{ $U('/dispatch') }}" class="orders-quicklink-card">
					<div class="d-flex align-items-center" style="gap:9px; min-width:0;">
						<div class="orders-ql-icon" style="background:#f3e8ff; color:#7c3aed;">
							<i class="fa-solid fa-truck-fast"></i>
						</div>
						<div style="min-width:0;">
							<div class="font-weight-bold text-slate-900" style="font-size:0.78rem;">Despachos</div>
							<div class="text-muted text-truncate" style="font-size:0.68rem;">Preparar envíos</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.68rem;"></i>
				</a>

				<a href="{{ $U('/sales') }}" class="orders-quicklink-card">
					<div class="d-flex align-items-center" style="gap:9px; min-width:0;">
						<div class="orders-ql-icon" style="background:#dcfce7; color:#16a34a;">
							<i class="fa-solid fa-chart-column"></i>
						</div>
						<div style="min-width:0;">
							<div class="font-weight-bold text-slate-900" style="font-size:0.78rem;">Ventas y Rentabilidad</div>
							<div class="text-muted text-truncate" style="font-size:0.68rem;">Ver reportes</div>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right text-muted" style="font-size:0.68rem;"></i>
				</a>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL: DETALLE DE PREPARACIÓN, LOTES FEFO E HISTORIAL DE CAMBIOS
     ========================================================================= -->
<div class="modal fade" id="modal-order-prep" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-clipboard-check text-success mr-2"></i>
					<span id="prep-modal-title">Preparación, Lotes FEFO e Historial</span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" id="prep-modal-body"></div>
			<div class="modal-footer bg-light d-flex justify-content-between" id="prep-modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL: PREPARAR DESPACHO (EJECUTAR DESPACHO O ABRIR MÓDULO DE DESPACHOS)
     ========================================================================= -->
<div class="modal fade" id="modal-order-dispatch" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
			<div class="modal-header bg-success text-white border-0">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-truck-fast mr-2"></i>Preparar y Confirmar Despacho
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" id="dispatch-modal-body"></div>
			<div class="modal-footer bg-light d-flex justify-content-between">
				<a href="{{ $U('/dispatch') }}" class="btn btn-outline-primary font-weight-bold btn-sm" id="btn-go-dispatch-module">
					<i class="fa-solid fa-route mr-1"></i> Ir a Despachos y Validaciones
				</a>
				<div>
					<button type="button" class="btn btn-outline-secondary btn-sm mr-1" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-success font-weight-bold btn-sm" id="btn-confirm-dispatch-now">
						<i class="fa-solid fa-check mr-1"></i> Despachar y Generar Venta Ahora
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.DairyOrders = {
		branches: {!! json_encode(array_values($branches), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		customers: {!! json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		products: {!! json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		allOrders: {!! json_encode($allOrders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		detailsMap: {!! json_encode($orderDetailsMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		selectedId: {{ $selected ? intval($selected['order']['id']) : 'null' }},
		branchId: {{ $branchId ?: 'null' }},
		initialStatus: {!! json_encode($status) !!},
		can: {!! json_encode($can) !!}
	};
</script>
@stop
