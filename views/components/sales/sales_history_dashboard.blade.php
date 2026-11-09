@php
	$dash = $salesAuditDashboard ?? [];
	$k = $dash['kpis'] ?? [];
	$txList = $dash['transactions_master'] ?? [];
	$ficha = $dash['ficha_360'] ?? [];
	$timeline = $dash['timeline'] ?? [];
	$recSources = $dash['reconciliation_sources'] ?? [];
	$exceptions = $dash['exceptions'] ?? [];
	$voidsDiscounts = $dash['voids_discounts'] ?? [];
	$diffs = $dash['differences_detected'] ?? [];
	$returns = $dash['returns_tracking'] ?? [];
	$auditLog = $dash['change_audit_log'] ?? [];
	$syncSources = $dash['sync_control'] ?? [];
	$caseCounters = $dash['case_counters'] ?? [];
	$cases = $dash['cases'] ?? [];
	$comp = $dash['comparator'] ?? [];
@endphp

<div class="sales-hist-wrapper">
	<!-- ====================================================================
	     ENCABEZADO COMERCIAL — EXACTAMENTE EQUILIBRADO CON LOS DEMÁS MÓDULOS
	     ==================================================================== -->
	<header class="sales-hist-header">
		<div class="sales-hist-header-left">
			<h1 class="sales-hist-title">Historial de Ventas</h1>
			<p class="sales-hist-subtitle">Trazabilidad, auditoría y conciliación comercial: localizar, reconstruir, investigar y resolver cualquier operación.</p>
		</div>
		<div class="sales-hist-header-right">
			<span class="sales-hist-mockup-badge">Mockup con datos demo · referencias de pago enmascaradas</span>
		</div>
	</header>

	<!-- ====================================================================
	     BARRA DE PESTAÑAS INTEGRADA DE DAILY (NAVEGACIÓN DIRECTA ENTRE MÓDULOS)
	     ==================================================================== -->
	<nav class="ded-nav-tabs-bar mb-3" role="tablist" aria-label="Módulos de Ventas y Rentabilidad">
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-resumen" role="tab" id="pill-resumen-hist">
			<i class="fa-solid fa-chart-column mr-1"></i>
			<span>Tablero de Ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-departamentos" role="tab" id="pill-departamentos-hist">
			<i class="fa-solid fa-boxes-stacked mr-1"></i>
			<span>Rendimiento por departamento</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-productos" role="tab" id="pill-productos-hist">
			<i class="fa-solid fa-barcode mr-1"></i>
			<span>Productos</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-sucursales" role="tab" id="pill-sucursales-hist">
			<i class="fa-solid fa-store mr-1"></i>
			<span>Sucursales</span>
		</button>
		<button type="button" class="ded-nav-tab is-active" data-toggle="pill" data-target="#tab-detalle" role="tab" id="pill-detalle-hist" aria-selected="true">
			<i class="fa-solid fa-list-ul mr-1"></i>
			<span>Historial de ventas</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-rentabilidad" role="tab" id="pill-rentabilidad-hist">
			<i class="fa-solid fa-coins mr-1"></i>
			<span>Rentabilidad</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-precios-promociones" role="tab" id="pill-precios-hist">
			<i class="fa-solid fa-tags mr-1"></i>
			<span>Precios y promociones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#tab-campanas" role="tab" id="pill-campanas-hist">
			<i class="fa-solid fa-bullhorn mr-1"></i>
			<span>Campañas</span>
		</button>
	</nav>

	<!-- ====================================================================
	     FILA 1 — BUSCADOR UNIVERSAL Y FILTROS AVANZADOS
	     ==================================================================== -->
	<div class="sales-hist-search-card mb-3">
		<!-- Buscador Universal -->
		<div class="hist-search-box-row mb-2">
			<div class="hist-search-input-wrap">
				<i class="fa-solid fa-magnifying-glass hist-search-icon"></i>
				<input type="text" class="hist-search-input" id="histUniversalSearch"
					   placeholder="Buscar por ticket, ID de transacción, SKU o referencia de pago autorizada..."
					   value="">
			</div>
			<div class="hist-search-actions">
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnHistSearch">
					Buscar
				</button>
				<button type="button" class="btn btn-sm btn-light border" id="btnHistSaveSearch">
					Guardar búsqueda
				</button>
			</div>
		</div>

		<!-- Tira de 12 Filtros Avanzados -->
		<div class="hist-filters-grid mb-2">
			<div class="hist-filter-item">
				<label class="hist-filter-label">Fecha y rango horario</label>
				<input type="text" class="hist-filter-input" value="08/10 – 06/11 · 00:00–23:59" readonly>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Sucursal</label>
				<select class="hist-filter-select" id="filterHistBranch">
					<option value="all" selected>Todas</option>
					<option value="Hallandale">Hallandale Beach</option>
					<option value="Miami Beach">Miami Beach</option>
					<option value="Sunny Isles">Sunny Isles</option>
					<option value="Sucursal 07">Sucursal 07 (Hollywood)</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Departamento</label>
				<select class="hist-filter-select" id="filterHistDept">
					<option value="all" selected>Todos</option>
					<option value="frozen">90 Frozen</option>
					<option value="dairy">91 Dairy</option>
					<option value="pescaderia">92 Pescadería</option>
					<option value="carnes">93 Carnes</option>
					<option value="bakery">94 Bakery</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Caja / terminal POS</label>
				<select class="hist-filter-select" id="filterHistRegister">
					<option value="all" selected>Todas</option>
					<option value="Caja 1">Caja 1</option>
					<option value="Caja 2">Caja 2</option>
					<option value="Caja 3">Caja 3</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Cajero o colaborador</label>
				<select class="hist-filter-select" id="filterHistCashier">
					<option value="all" selected>Todos</option>
					<option value="Cajero 02">Cajero 02</option>
					<option value="Cajero 03">Cajero 03</option>
					<option value="Cajero 05">Cajero 05</option>
					<option value="Cajero 07">Cajero 07</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Método de pago</label>
				<select class="hist-filter-select" id="filterHistPayment">
					<option value="all" selected>Todos</option>
					<option value="Tarjeta">Tarjeta</option>
					<option value="Efectivo">Efectivo</option>
					<option value="Transferencia">Transferencia</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Estado de transacción</label>
				<select class="hist-filter-select" id="filterHistStatus">
					<option value="all" selected>Todos</option>
					<option value="Completada">Completada</option>
					<option value="Devuelto parcial">Devuelto parcial</option>
					<option value="Devuelto total">Devuelto total</option>
					<option value="Anulado">Anulado</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Tipo de operación</label>
				<select class="hist-filter-select" id="filterHistOpType">
					<option value="all" selected>Todas</option>
					<option value="Venta">Venta</option>
					<option value="Devolución">Devolución</option>
					<option value="Anulación">Anulación</option>
				</select>
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">N.° de ticket</label>
				<input type="text" class="hist-filter-input" placeholder="—" id="filterHistTicket">
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">ID transacción</label>
				<input type="text" class="hist-filter-input" placeholder="—" id="filterHistTxId">
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">SKU / UPC</label>
				<input type="text" class="hist-filter-input" placeholder="—" id="filterHistSku">
			</div>
			<div class="hist-filter-item">
				<label class="hist-filter-label">Importe mín. y máx.</label>
				<input type="text" class="hist-filter-input" value="$0 – sin límite" id="filterHistAmount">
			</div>
		</div>

		<!-- Búsquedas Guardadas -->
		<div class="hist-saved-searches-row">
			<span class="hist-saved-label">Búsquedas guardadas:</span>
			<button type="button" class="hist-saved-pill" data-preset="diff">Diferencias de conciliación</button>
			<button type="button" class="hist-saved-pill" data-preset="voids">Anulaciones post-pago</button>
			<button type="button" class="hist-saved-pill" data-preset="noref">Devoluciones sin referencia</button>
			<button type="button" class="hist-saved-pill" data-preset="discounts">Descuentos > límite</button>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 2 — SIETE INDICADORES KPI
	     ==================================================================== -->
	<div class="sales-hist-kpis-grid mb-3">
		<div class="hist-kpi-card" data-kpi-filter="all">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Transacciones registradas</span>
				<div class="hist-kpi-icon icon-green"><i class="fa-solid fa-file-lines"></i></div>
			</div>
			<div class="hist-kpi-value" id="kpiHistRegistered">{{ number_format($k['registered_count'] ?? 5611) }}</div>
			<div class="hist-kpi-sub">{{ $k['registered_sub'] ?? 'en el período' }}</div>
		</div>

		<div class="hist-kpi-card" data-kpi-filter="reconciled">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Transacciones conciliadas</span>
				<div class="hist-kpi-icon icon-green"><i class="fa-regular fa-circle-check"></i></div>
			</div>
			<div class="hist-kpi-value" id="kpiHistReconciled">{{ number_format($k['reconciled_count'] ?? 5437) }}</div>
			<div class="hist-kpi-sub">{{ $k['reconciled_sub'] ?? '96.9 % verificadas' }}</div>
		</div>

		<div class="hist-kpi-card" data-kpi-filter="pending">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Transacciones pendientes</span>
				<div class="hist-kpi-icon icon-amber"><i class="fa-regular fa-clock"></i></div>
			</div>
			<div class="hist-kpi-value" id="kpiHistPending">{{ $k['pending_count'] ?? 118 }}</div>
			<div class="hist-kpi-sub">{{ $k['pending_sub'] ?? '140 esperan conciliar' }}</div>
		</div>

		<div class="hist-kpi-card" data-kpi-filter="voided">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Transacciones anuladas</span>
				<div class="hist-kpi-icon icon-gray"><i class="fa-solid fa-ban"></i></div>
			</div>
			<div class="hist-kpi-value" id="kpiHistVoided">{{ $k['voided_count'] ?? 41 }}</div>
			<div class="hist-kpi-sub">{{ $k['voided_sub'] ?? '7.3 por 1,000 operaciones' }}</div>
		</div>

		<div class="hist-kpi-card" data-kpi-filter="differences">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Transacciones con diferencias</span>
				<div class="hist-kpi-icon icon-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
			</div>
			<div class="hist-kpi-value text-danger" id="kpiHistDifferences">{{ $k['differences_count'] ?? 15 }}</div>
			<div class="hist-kpi-sub text-danger font-weight-600">{{ $k['differences_sub'] ?? '$469.04 afectadas' }}</div>
		</div>

		<div class="hist-kpi-card" data-kpi-filter="time">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title">Tiempo medio de conciliación</span>
				<div class="hist-kpi-icon icon-teal"><i class="fa-solid fa-stopwatch"></i></div>
			</div>
			<div class="hist-kpi-value" id="kpiHistAvgTime">{{ $k['avg_time'] ?? '42 min' }}</div>
			<div class="hist-kpi-sub">{{ $k['avg_time_sub'] ?? 'registro → acreditación' }}</div>
		</div>

		<!-- KPI 7: Degradado / Fondo verde sólido con texto blanco -->
		<div class="hist-kpi-card hist-kpi-card-green" data-kpi-filter="review">
			<div class="hist-kpi-header">
				<span class="hist-kpi-title text-white">Operaciones que requieren revisión</span>
				<div class="hist-kpi-icon icon-white"><i class="fa-solid fa-flag"></i></div>
			</div>
			<div class="hist-kpi-value text-white" id="kpiHistReview">{{ $k['require_review_count'] ?? 23 }}</div>
			<div class="hist-kpi-sub text-white-50">{{ $k['require_review_sub'] ?? 'con excepciones abiertas' }}</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 3 — EXPLORADOR MAESTRO (65%) Y FICHA 360° (35%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- EXPLORADOR MAESTRO DE TRANSACCIONES (65%) -->
		<div class="col-lg-8 col-xl-8 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header">
					<div>
						<h2 class="hist-panel-title">Explorador maestro de transacciones</h2>
						<p class="hist-panel-subtitle">Paginación desde servidor, orden por columna, selección múltiple y exportación. Clic en una fila muestra la ficha sin salir de la página.</p>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableTransactionsMaster">
						<thead>
							<tr>
								<th style="width: 25px;"><input type="checkbox" id="cbSelectAllTx"></th>
								<th>Fecha y hora</th>
								<th>ID</th>
								<th>Ticket</th>
								<th>Sucursal</th>
								<th>Caja</th>
								<th>Cajero</th>
								<th>Tipo</th>
								<th class="text-right">Importe</th>
								<th>Pago</th>
								<th>Estado comercial</th>
								<th>Conciliación</th>
								<th class="text-right">Acción</th>
							</tr>
						</thead>
						<tbody id="txMasterTbody">
							@foreach($txList as $tx)
							<tr class="tx-row @if($tx['id'] === 'TX-4712' || $loop->first) is-selected @endif" data-tx-id="{{ $tx['id'] }}">
								<td><input type="checkbox" class="cb-tx-item" @if($loop->index < 2) checked @endif></td>
								<td class="font-weight-600 text-dark text-nowrap">{{ $tx['datetime'] }}</td>
								<td class="font-weight-bold text-dark text-nowrap">{{ $tx['id'] }}</td>
								<td class="font-weight-600 text-muted text-nowrap">{{ $tx['ticket'] }}</td>
								<td class="text-nowrap">{{ $tx['branch'] }}</td>
								<td class="text-nowrap">{{ $tx['register'] }}</td>
								<td class="text-nowrap text-muted">{{ $tx['cashier'] }}</td>
								<td><span class="hist-status-pill {{ $tx['type_badge'] }}">{{ $tx['type'] }}</span></td>
								<td class="text-right font-weight-700 text-dark text-nowrap">{{ $tx['amount'] }}</td>
								<td class="text-nowrap text-muted">{{ $tx['payment_method'] }}</td>
								<td><span class="hist-status-pill {{ $tx['commercial_status_badge'] }}">{{ $tx['commercial_status'] }}</span></td>
								<td><span class="hist-status-pill {{ $tx['reconciliation_status_badge'] }}">{{ $tx['reconciliation_status'] }}</span></td>
								<td class="text-right">
									<button type="button" class="btn btn-xs btn-light border btn-open-tx-detail" data-tx-id="{{ $tx['id'] }}">Abrir</button>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<!-- Barra Inferior del Explorador -->
				<div class="hist-explorer-footer mt-2">
					<div class="small text-muted" id="txPaginationInfo">
						<strong>2 seleccionados</strong> · 1-8 de 5,611 · Página 1 de 702
					</div>
					<div class="hist-explorer-actions">
						<button type="button" class="btn btn-xs btn-light border" id="btnSaveView">Guardar vista</button>
						<button type="button" class="btn btn-xs btn-light border" id="btnCompareTx">Comparar</button>
						<button type="button" class="btn btn-xs btn-light border" id="btnExportTx">Exportar CSV / Excel</button>
						<button type="button" class="btn btn-xs btn-success font-weight-bold" id="btnOpenCase">Abrir caso</button>
					</div>
				</div>
			</div>
		</div>

		<!-- FICHA 360° DE TRANSACCIÓN (35%) -->
		<div class="col-lg-4 col-xl-4 pl-lg-2">
			<div class="hist-card-panel h-100" id="panelFicha360Tx">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title" id="fichaTxTitle">Ficha 360° · {{ $ficha['tx_id'] ?? 'TX-4712' }}</h2>
						<p class="hist-panel-subtitle">Panel lateral: estado comercial separado del estado de conciliación.</p>
					</div>
				</div>

				<!-- Badges de estado comercial y de conciliación separados -->
				<div class="d-flex align-items-center mb-2" style="gap: 6px;">
					<span class="hist-status-pill {{ $ficha['commercial_status_badge'] ?? 'badge-atencion' }}" id="fichaCommStatus">
						{{ $ficha['commercial_status'] ?? 'Devuelto parcialmente' }}
					</span>
					<span class="hist-status-pill {{ $ficha['reconciliation_status_badge'] ?? 'badge-critico' }}" id="fichaRecStatus">
						{{ $ficha['reconciliation_status'] ?? 'Diferencia de conciliación' }}
					</span>
				</div>

				<!-- 1. IDENTIFICACIÓN -->
				<div class="ficha-section-title">IDENTIFICACIÓN</div>
				<div class="ficha-keyval-grid mb-2">
					<div class="ficha-kv-row">
						<span class="text-muted">Ticket / ID:</span>
						<strong id="fichaTicketId">{{ $ficha['ticket'] ?? 'T-48520' }} · {{ $ficha['tx_id'] ?? 'TX-4712' }}</strong>
					</div>
					<div class="ficha-kv-row">
						<span class="text-muted">Fecha y hora / Zona:</span>
						<span class="text-dark font-weight-600" id="fichaDatetime">{{ $ficha['datetime'] ?? '05/11/2026 09:41:12 · ET' }}</span>
					</div>
					<div class="ficha-kv-row">
						<span class="text-muted">Sucursal / Caja:</span>
						<span id="fichaBranchRegister">{{ $ficha['branch'] ?? 'Hallandale Beach' }} · {{ $ficha['register'] ?? 'Caja 2' }}</span>
					</div>
					<div class="ficha-kv-row">
						<span class="text-muted">Cajero / Canal:</span>
						<span id="fichaCashierChannel">{{ $ficha['cashier'] ?? 'Cajero 02' }} · {{ $ficha['channel'] ?? 'POS en tienda' }}</span>
					</div>
				</div>

				<!-- 2. DETALLE COMERCIAL -->
				<div class="ficha-section-title">DETALLE COMERCIAL · 4 LÍNEAS</div>
				<div class="table-responsive mb-2">
					<table class="table hist-items-table mb-0" id="tableFichaItems">
						<thead>
							<tr>
								<th>Producto</th>
								<th class="text-center">Cant.</th>
								<th class="text-right">Precio</th>
								<th class="text-right">Total</th>
							</tr>
						</thead>
						<tbody>
							@foreach($ficha['items'] ?? [] as $item)
							<tr>
								<td class="font-weight-600 text-dark">{{ $item['name'] }}</td>
								<td class="text-center">{{ $item['qty'] }}</td>
								<td class="text-right text-muted">${{ number_format($item['price'], 2) }}</td>
								<td class="text-right font-weight-700">${{ number_format($item['total'], 2) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<!-- Totales y Descuento -->
				<div class="ficha-totals-box mb-2">
					<div class="d-flex justify-content-between small">
						<span class="text-muted">Subtotal · Descuento 5 %:</span>
						<span>${{ number_format($ficha['subtotal'] ?? 61.36, 2) }} · <span class="text-danger">-${{ number_format(abs($ficha['discount'] ?? 3.07), 2) }}</span></span>
					</div>
					<div class="d-flex justify-content-between small font-weight-bold">
						<span>Impuestos · Total final:</span>
						<span class="text-success">${{ number_format($ficha['tax'] ?? 0, 2) }} · ${{ number_format($ficha['total'] ?? 57.06, 2) }}</span>
					</div>
				</div>

				<!-- 3. PAGO -->
				<div class="ficha-section-title">PAGO</div>
				<div class="ficha-keyval-grid mb-2">
					<div class="ficha-kv-row">
						<span class="text-muted">Método / Autorización:</span>
						<strong id="fichaPaymentAuth">{{ $ficha['payment_method'] ?? 'Tarjeta' }} · {{ $ficha['auth_status'] ?? 'Autorizado' }}</strong>
					</div>
					<div class="ficha-kv-row">
						<span class="text-muted">Referencia:</span>
						<span class="font-weight-600" id="fichaMaskedRef">{{ $ficha['masked_ref'] ?? 'AUTH-****-8761' }}</span>
					</div>
					<div class="ficha-kv-row">
						<span class="text-muted">Reembolso:</span>
						<span class="text-danger font-weight-600" id="fichaRefund">{{ $ficha['refund_amount'] ?? '$14.50' }} · {{ $ficha['refund_status'] ?? 'sin confirmar' }}</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 4 — LÍNEA DE TIEMPO (60%) Y ESTADO DE CONCILIACIÓN (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- LÍNEA DE TIEMPO DE TRANSACCIÓN (60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Línea de tiempo · TX-4712</h2>
						<p class="hist-panel-subtitle">Solo eventos realmente disponibles en las fuentes. Cada evento muestra: responsable, estado anterior → posterior, referencia y evidencia.</p>
					</div>
				</div>

				<div class="hist-timeline-wrapper">
					@foreach($timeline as $ev)
					<div class="hist-timeline-item">
						<div class="hist-timeline-time">{{ $ev['time'] }}</div>
						<div class="hist-timeline-marker">
							<span class="hist-timeline-dot {{ $ev['dot_class'] }}"></span>
							<div class="hist-timeline-line"></div>
						</div>
						<div class="hist-timeline-content">
							<div class="font-weight-bold text-dark font-12">{{ $ev['title'] }}</div>
							<div class="small text-muted">{{ $ev['desc'] }}</div>
						</div>
					</div>
					@endforeach

					<!-- Evento no disponible -->
					<div class="hist-timeline-item text-muted font-italic small pt-1">
						<div class="hist-timeline-time">—</div>
						<div class="hist-timeline-marker">
							<span class="hist-timeline-dot dot-gray"></span>
						</div>
						<div class="hist-timeline-content">
							<span class="badge badge-light border text-muted mr-1">Evento no disponible</span>
							La fuente de pagos solo informa el estado final con retraso.
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ESTADO DE CONCILIACIÓN (40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Estado de conciliación</h2>
						<p class="hist-panel-subtitle">Operaciones del período por estado y fuentes comparadas.</p>
					</div>
				</div>

				<!-- Barra Visual Proporcional de Conciliación -->
				<div class="progress hist-rec-progress mb-2">
					<div class="progress-bar bg-success" style="width: 96.9%;" title="5,437 conciliadas"></div>
					<div class="progress-bar bg-warning" style="width: 2.1%;" title="118 pendientes"></div>
					<div class="progress-bar bg-danger" style="width: 0.3%;" title="15 en diferencias"></div>
					<div class="progress-bar bg-secondary" style="width: 0.7%;" title="41 anuladas"></div>
				</div>

				<div class="small text-muted mb-3 d-flex justify-content-between flex-wrap">
					<span><strong>5,437</strong> conciliadas</span>
					<span><strong>118</strong> pendientes</span>
					<span class="text-danger"><strong>15</strong> en diferencias</span>
					<span><strong>41</strong> anuladas</span>
				</div>

				<!-- Fuentes Comparadas -->
				<div class="hist-rec-sources-list">
					@foreach($recSources['sources'] ?? [] as $src)
					<div class="hist-source-row">
						<div>
							<div class="font-weight-600 text-dark font-12">{{ $src['name'] }}</div>
							<div class="small text-muted">{{ $src['detail'] }}</div>
						</div>
						<span class="hist-status-pill {{ $src['badge_class'] }}">{{ $src['status'] }}</span>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 5 — TRANSACCIONES INVESTIGACIÓN (60%) Y ANULACIONES/DESCUENTOS (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- TRANSACCIONES QUE REQUIEREN INVESTIGACIÓN (60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Transacciones que requieren investigación</h2>
						<p class="hist-panel-subtitle">Una excepción no es una acusación: cada una conserva evidencia y trazabilidad.</p>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableExceptions">
						<thead>
							<tr>
								<th>ID excepción</th>
								<th>Transacción</th>
								<th>Sucursal</th>
								<th>Tipo de excepción</th>
								<th class="text-right">Importe</th>
								<th>Fecha</th>
								<th>Prior.</th>
								<th>Responsable</th>
								<th class="text-right">Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach($exceptions as $ex)
							<tr data-exception-id="{{ $ex['id'] }}">
								<td class="font-weight-bold text-dark text-nowrap">{{ $ex['id'] }}</td>
								<td class="text-nowrap">{{ $ex['tx_id'] }}</td>
								<td class="text-nowrap">{{ $ex['branch'] }}</td>
								<td class="text-truncate" style="max-width: 140px;">{{ $ex['exception_type'] }}</td>
								<td class="text-right font-weight-700 text-dark">{{ $ex['amount'] }}</td>
								<td class="text-muted text-nowrap">{{ $ex['date'] }}</td>
								<td><span class="hist-status-pill {{ $ex['priority_badge'] }}">{{ $ex['priority'] }}</span></td>
								<td class="text-nowrap">{{ $ex['responsible'] }}</td>
								<td class="text-right"><span class="hist-status-pill {{ $ex['status_badge'] }}">{{ $ex['status'] }}</span></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- ANULACIONES Y DESCUENTOS POR CAJA (40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Anulaciones y descuentos</h2>
						<p class="hist-panel-subtitle">Tasa por 1,000 operaciones de cada caja. Una tasa alta no implica irregularidad por sí sola.</p>
					</div>
				</div>

				<!-- Barras horizontales por caja -->
				<div class="hist-voids-chart mb-2">
					@foreach($voidsDiscounts['registers'] ?? [] as $reg)
					<div class="hist-void-row mb-2">
						<div class="d-flex justify-content-between small mb-1">
							<span class="font-weight-600 text-dark">{{ $reg['name'] }}</span>
							<strong class="text-dark">{{ $reg['rate_formatted'] }}</strong>
						</div>
						<div class="progress hist-void-progress">
							<div class="progress-bar {{ $reg['bar_color_class'] }}" style="width: {{ min(100, $reg['rate'] * 6) }}%;"></div>
						</div>
					</div>
					@endforeach
				</div>

				<!-- Indicadores Clave Inferiores -->
				<div class="d-flex justify-content-between align-items-center small text-muted pt-2 border-top">
					<div>Tasa media red: <strong>{{ $voidsDiscounts['network_rate'] ?? '2.9 %' }}</strong></div>
					<div>Total anuladas: <strong>{{ $voidsDiscounts['total_voids'] ?? 14 }}</strong></div>
					<div>Anulaciones post-pago: <strong class="text-danger">{{ $voidsDiscounts['post_pay_voids'] ?? 6 }}</strong></div>
				</div>
				<div class="small text-muted font-italic mt-1">Línea de referencia: umbral técnico de la red (7.5)</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 6 — CONCILIACIÓN POS (60%) Y SEGUIMIENTO DEVOLUCIONES (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- CONCILIACIÓN POS · DIFERENCIAS DETECTADAS (60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Conciliación POS · diferencias detectadas</h2>
						<p class="hist-panel-subtitle">Cada diferencia genera un caso investigable. Ninguna se marca como fraude automáticamente.</p>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableDifferences">
						<thead>
							<tr>
								<th>Tipo de diferencia</th>
								<th class="text-center">Casos</th>
								<th class="text-right">Importe</th>
							</tr>
						</thead>
						<tbody>
							@foreach($diffs['items'] ?? [] as $df)
							<tr>
								<td class="font-weight-600 text-dark">{{ $df['type'] }}</td>
								<td class="text-center font-weight-700">{{ $df['cases'] }}</td>
								<td class="text-right font-weight-700 text-danger">{{ $df['amount'] }}</td>
							</tr>
							@endforeach
						</tbody>
						<tfoot>
							<tr class="font-weight-bold bg-light">
								<td>Total</td>
								<td class="text-center">{{ $diffs['total_cases'] ?? 15 }}</td>
								<td class="text-right text-danger">{{ $diffs['total_amount'] ?? '$469.04' }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>

		<!-- SEGUIMIENTO DE DEVOLUCIONES (40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Seguimiento de devoluciones</h2>
						<p class="hist-panel-subtitle">Relación venta → devolución. La gestión se hace en Devoluciones del POS; aquí no se reejecuta.</p>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap mb-2">
					<table class="table hist-table mb-0" id="tableReturnsTracking">
						<thead>
							<tr>
								<th>Ticket original</th>
								<th>Compra</th>
								<th>Producto</th>
								<th class="text-center">Cant.</th>
								<th>Motivo</th>
								<th class="text-right">Importe</th>
								<th>Reembolso</th>
								<th>Devolución</th>
								<th>Ref.</th>
							</tr>
						</thead>
						<tbody>
							@foreach($returns as $ret)
							<tr>
								<td class="font-weight-600 text-dark">{{ $ret['original_ticket'] }}</td>
								<td class="text-muted">{{ $ret['purchase_date'] }}</td>
								<td class="text-truncate" style="max-width: 100px;">{{ $ret['product'] }}</td>
								<td class="text-center">{{ $ret['qty'] }}</td>
								<td class="text-muted">{{ $ret['reason'] }}</td>
								<td class="text-right font-weight-700">{{ $ret['amount'] }}</td>
								<td><span class="hist-status-pill {{ $ret['refund_status_badge'] }}">{{ $ret['refund_status'] }}</span></td>
								<td class="text-muted">{{ $ret['return_date'] }}</td>
								<td class="text-info font-weight-600">{{ $ret['return_ref'] }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="text-right">
					<a href="{{ $U('/sales?tab=devoluciones') }}" class="small font-weight-bold text-success">
						Gestionar en Devoluciones del POS →
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 7 — AUDITORÍA DE CAMBIOS (60%) Y CONTROL DE SINCRONIZACIÓN (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- AUDITORÍA DE CAMBIOS (60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Auditoría de cambios</h2>
						<p class="hist-panel-subtitle">Registro append-only: no se edita ni se elimina desde la interfaz.</p>
					</div>
					<span class="hist-status-pill badge-normal"><i class="fa-solid fa-lock mr-1"></i>Solo lectura</span>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableAuditChanges">
						<thead>
							<tr>
								<th>Fecha y hora</th>
								<th>Evento</th>
								<th>Transacción</th>
								<th>Usuario</th>
								<th>Campo</th>
								<th>Anterior → Nuevo</th>
								<th>Motivo</th>
								<th class="text-right">Autoriz.</th>
							</tr>
						</thead>
						<tbody>
							@foreach($auditLog as $aud)
							<tr>
								<td class="font-weight-600 text-dark text-nowrap">{{ $aud['datetime'] }}</td>
								<td class="text-nowrap">{{ $aud['event'] }}</td>
								<td class="font-weight-bold text-nowrap">{{ $aud['tx_id'] }}</td>
								<td class="text-muted text-nowrap">{{ $aud['user'] }}</td>
								<td class="text-muted">{{ $aud['field'] }}</td>
								<td class="text-nowrap">{{ $aud['diff'] }}</td>
								<td class="text-muted text-truncate" style="max-width: 120px;">{{ $aud['reason'] }}</td>
								<td class="text-right"><span class="hist-status-pill {{ $aud['authorizer_badge'] }}">{{ $aud['authorizer'] }}</span></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- CONTROL DE SINCRONIZACIÓN (40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Control de sincronización</h2>
						<p class="hist-panel-subtitle">Integridad de datos por fuente. Reintentos idempotentes.</p>
					</div>
					<button type="button" class="btn btn-xs btn-outline-success font-weight-bold" id="btnRetrySync">
						<i class="fa-solid fa-rotate mr-1"></i>Reintentar (seguro)
					</button>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableSyncControl">
						<thead>
							<tr>
								<th>Fuente</th>
								<th>Última sincr.</th>
								<th class="text-center">Recib.</th>
								<th class="text-center">Proc.</th>
								<th class="text-center">Pend.</th>
								<th class="text-center">Rech.</th>
								<th class="text-right">Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach($syncSources as $syn)
							<tr>
								<td class="font-weight-bold text-dark">{{ $syn['source'] }}</td>
								<td class="text-muted text-nowrap">{{ $syn['last_sync'] }}</td>
								<td class="text-center">{{ number_format($syn['received']) }}</td>
								<td class="text-center font-weight-600">{{ number_format($syn['processed']) }}</td>
								<td class="text-center {{ $syn['pending'] > 0 ? 'text-warning font-weight-bold' : '' }}">{{ $syn['pending'] }}</td>
								<td class="text-center {{ $syn['rejected'] > 0 ? 'text-danger font-weight-bold' : '' }}">{{ $syn['rejected'] }}</td>
								<td class="text-right"><span class="hist-status-pill {{ $syn['status_badge'] }}">{{ $syn['status'] }}</span></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     FILA 8 — GESTIÓN DE CASOS (60%) Y COMPARADOR (40%)
	     ==================================================================== -->
	<div class="row mb-3">
		<!-- GESTIÓN DE CASOS (60%) -->
		<div class="col-lg-7 col-xl-7 pr-lg-2 mb-3 mb-lg-0">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Gestión de casos</h2>
						<p class="hist-panel-subtitle">Reutiliza incidencias, tareas y auditoría existentes. Cada caso mantiene su histórico.</p>
					</div>
				</div>

				<!-- Contadores de estado -->
				<div class="hist-case-counters-bar mb-2">
					<div class="case-counter-pill">
						<span class="case-counter-label">Pendiente</span>
						<strong class="case-counter-num">{{ $caseCounters['pendiente'] ?? 5 }}</strong>
					</div>
					<div class="case-counter-pill">
						<span class="case-counter-label">En investigación</span>
						<strong class="case-counter-num">{{ $caseCounters['investigacion'] ?? 8 }}</strong>
					</div>
					<div class="case-counter-pill">
						<span class="case-counter-label">Esperando info</span>
						<strong class="case-counter-num">{{ $caseCounters['esperando_info'] ?? 3 }}</strong>
					</div>
					<div class="case-counter-pill">
						<span class="case-counter-label">En corrección</span>
						<strong class="case-counter-num">{{ $caseCounters['correccion'] ?? 4 }}</strong>
					</div>
					<div class="case-counter-pill">
						<span class="case-counter-label">En validación</span>
						<strong class="case-counter-num">{{ $caseCounters['validacion'] ?? 3 }}</strong>
					</div>
					<div class="case-counter-pill is-resolved">
						<span class="case-counter-label">Resuelta</span>
						<strong class="case-counter-num">{{ $caseCounters['resuelta'] ?? 31 }}</strong>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap">
					<table class="table hist-table mb-0" id="tableCases">
						<thead>
							<tr>
								<th>Caso</th>
								<th>Transacción</th>
								<th>Responsable</th>
								<th>Última observación</th>
								<th>Estado</th>
								<th class="text-right">Validación</th>
							</tr>
						</thead>
						<tbody>
							@foreach($cases as $cs)
							<tr>
								<td class="font-weight-bold text-dark">{{ $cs['case_id'] }}</td>
								<td>{{ $cs['tx_id'] }}</td>
								<td>{{ $cs['responsible'] }}</td>
								<td class="text-truncate" style="max-width: 170px;">{{ $cs['last_obs'] }}</td>
								<td><span class="hist-status-pill {{ $cs['status_badge'] }}">{{ $cs['status'] }}</span></td>
								<td class="text-right small text-muted">{{ $cs['validation'] }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- COMPARADOR — POSIBLE DUPLICIDAD (40%) -->
		<div class="col-lg-5 col-xl-5 pl-lg-2">
			<div class="hist-card-panel h-100">
				<div class="hist-card-header pb-1">
					<div>
						<h2 class="hist-panel-title">Comparador · posible duplicidad</h2>
						<p class="hist-panel-subtitle">Criterios de coincidencia transaccionales. La similitud no implica irregularidad.</p>
					</div>
				</div>

				<div class="table-responsive hist-table-wrap mb-2">
					<table class="table hist-comp-table mb-0" id="tableTxComparator">
						<thead>
							<tr>
								<th>Campo</th>
								<th class="text-center font-weight-bold text-dark">{{ $comp['tx_a']['id'] ?? 'TX-4803' }}</th>
								<th class="text-center font-weight-bold text-dark">{{ $comp['tx_b']['id'] ?? 'TX-4802' }}</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="font-weight-600">Hora</td>
								<td class="text-center">{{ $comp['tx_a']['time'] ?? '10:12:15' }}</td>
								<td class="text-center">{{ $comp['tx_b']['time'] ?? '10:12:45' }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Sucursal · caja</td>
								<td class="text-center">{{ $comp['tx_a']['branch_register'] ?? 'Sunny Isles · Caja 1' }}</td>
								<td class="text-center">{{ $comp['tx_b']['branch_register'] ?? 'Sunny Isles · Caja 1' }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Cajero</td>
								<td class="text-center">{{ $comp['tx_a']['cashier'] ?? 'Cajero 05' }}</td>
								<td class="text-center">{{ $comp['tx_b']['cashier'] ?? 'Cajero 05' }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Productos</td>
								<td class="text-center">{{ $comp['tx_a']['products'] ?? '1 línea' }}</td>
								<td class="text-center">{{ $comp['tx_b']['products'] ?? '1 línea idéntica' }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Total</td>
								<td class="text-center font-weight-bold text-success">{{ $comp['tx_a']['amount'] ?? '$18.50' }}</td>
								<td class="text-center font-weight-bold text-success">{{ $comp['tx_b']['amount'] ?? '$18.50' }}</td>
							</tr>
							<tr>
								<td class="font-weight-600">Referencia de pago</td>
								<td class="text-center"><span class="badge badge-warning-subtle text-amber font-weight-600 px-2 py-1 rounded">{{ $comp['tx_a']['payment_ref'] ?? 'AUTH-****-6112' }}</span></td>
								<td class="text-center"><span class="badge badge-warning-subtle text-amber font-weight-600 px-2 py-1 rounded">{{ $comp['tx_b']['payment_ref'] ?? 'AUTH-****-6114' }}</span></td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="small text-muted font-italic">
					{{ $comp['note'] ?? 'Coinciden productos e importes; pagos con distintos vouchers: requiere revisión de contexto antes de concluir.' }}
				</div>
			</div>
		</div>
	</div>

	<!-- PIE DE PÁGINA DEL DASHBOARD -->
	<footer class="sales-hist-footer text-center py-2 text-muted small">
		DAILY — Historial de Ventas · Mockup con datos demo · sin transacciones reales
	</footer>
</div>

<!-- ====================================================================
     MODALES INTERACTIVOS
     ==================================================================== -->

<!-- MODAL: ABRIR CASO DE AUDITORÍA -->
<div class="modal fade" id="modalOpenCase" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-success text-white py-2">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-folder-plus mr-2"></i>Abrir Caso de Conciliación</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<form id="formOpenCase">
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Transacciones vinculadas</label>
						<input type="text" class="form-control form-control-sm" id="caseTxInput" value="TX-4872, TX-4871" readonly>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Tipo de caso</label>
						<select class="form-control form-control-sm" id="caseTypeSelect">
							<option value="Diferencia de conciliación">Diferencia de conciliación</option>
							<option value="Reembolso no acreditado">Reembolso no acreditado</option>
							<option value="Descuento fuera de política">Descuento fuera de política</option>
							<option value="Posible duplicidad">Posible duplicidad de cobro</option>
						</select>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Responsable asignado</label>
						<select class="form-control form-control-sm" id="caseResponsibleSelect">
							<option value="Supervisor 02">Supervisor 02 (Hallandale)</option>
							<option value="Gerente 03">Gerente 03 (Miami Beach)</option>
							<option value="Supervisor 05">Supervisor 05 (Sunny Isles)</option>
							<option value="Supervisor 07">Supervisor 07 (Hollywood)</option>
						</select>
					</div>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Observación inicial</label>
						<textarea class="form-control form-control-sm" id="caseObsInput" rows="2" placeholder="Describa el motivo de la apertura del caso..."></textarea>
					</div>
				</form>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnConfirmOpenCase"><i class="fa-solid fa-check mr-1"></i>Crear caso</button>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: GUARDAR BÚSQUEDA -->
<div class="modal fade" id="modalSaveSearch" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white py-2">
				<h5 class="modal-title font-weight-bold"><i class="fa-solid fa-bookmark mr-2"></i>Guardar Consulta de Búsqueda</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Nombre de la búsqueda</label>
					<input type="text" class="form-control form-control-sm" id="saveSearchName" placeholder="Ej. Auditoría semanal de Caja 2">
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="cbSaveSearchNotify" checked>
					<label class="form-check-label small" for="cbSaveSearchNotify">
						Crear alerta automática cuando aparezcan transacciones con estos criterios
					</label>
				</div>
			</div>
			<div class="modal-footer py-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnConfirmSaveSearch">Guardar</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     LÓGICA JAVASCRIPT REACTIVA DEL HISTORIAL DE VENTAS
     ==================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
	const txCatalog = @json($txList);

	// 1. Clic en fila del explorador maestro para actualizar la Ficha 360°
	document.querySelectorAll('.tx-row').forEach(row => {
		row.addEventListener('click', function(e) {
			if (e.target.closest('input[type="checkbox"]')) return;
			document.querySelectorAll('.tx-row').forEach(r => r.classList.remove('is-selected'));
			this.classList.add('is-selected');

			const txId = this.getAttribute('data-tx-id');
			const txData = txCatalog.find(t => t.id === txId);
			if (!txData) return;

			// Actualizar encabezado de Ficha 360°
			const fTitle = document.getElementById('fichaTxTitle');
			if (fTitle) fTitle.innerText = 'Ficha 360° · ' + txData.id;

			const fTicket = document.getElementById('fichaTicketId');
			if (fTicket) fTicket.innerText = txData.ticket + ' · ' + txData.id;

			const fBranch = document.getElementById('fichaBranchRegister');
			if (fBranch) fBranch.innerText = txData.branch_full + ' · ' + txData.register;

			const fCashier = document.getElementById('fichaCashierChannel');
			if (fCashier) fCashier.innerText = txData.cashier + ' · POS en tienda';

			const fComm = document.getElementById('fichaCommStatus');
			if (fComm) {
				fComm.className = 'hist-status-pill ' + txData.commercial_status_badge;
				fComm.innerText = txData.commercial_status;
			}

			const fRec = document.getElementById('fichaRecStatus');
			if (fRec) {
				fRec.className = 'hist-status-pill ' + txData.reconciliation_status_badge;
				fRec.innerText = txData.reconciliation_status;
			}
		});
	});

	// 2. Buscador Universal instantáneo
	const searchInput = document.getElementById('histUniversalSearch');
	const btnSearch = document.getElementById('btnHistSearch');

	function executeSearch() {
		const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
		document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(row => {
			const text = row.innerText.toLowerCase();
			row.style.display = (q === '' || text.indexOf(q) >= 0) ? '' : 'none';
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', executeSearch);
		searchInput.addEventListener('keypress', function(e) {
			if (e.key === 'Enter') executeSearch();
		});
	}
	if (btnSearch) {
		btnSearch.addEventListener('click', executeSearch);
	}

	// 3. Píldoras de Búsquedas Guardadas
	document.querySelectorAll('.hist-saved-pill').forEach(pill => {
		pill.addEventListener('click', function() {
			const wasActive = this.classList.contains('active');
			document.querySelectorAll('.hist-saved-pill').forEach(p => p.classList.remove('active'));
			if (wasActive) {
				document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(row => row.style.display = '');
				return;
			}
			this.classList.add('active');
			const preset = this.getAttribute('data-preset');

			document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(row => {
				const text = row.innerText.toLowerCase();
				if (preset === 'diff') {
					row.style.display = (text.includes('descuadre') || text.includes('diferencia')) ? '' : 'none';
				} else if (preset === 'voids') {
					row.style.display = text.includes('anulado') ? '' : 'none';
				} else if (preset === 'noref') {
					row.style.display = text.includes('devolución') ? '' : 'none';
				} else if (preset === 'discounts') {
					row.style.display = (text.includes('212.15') || text.includes('57.06')) ? '' : 'none';
				} else {
					row.style.display = '';
				}
			});
		});
	});

	// 4. Filtros interactivos por selectores
	['filterHistBranch', 'filterHistDept', 'filterHistRegister', 'filterHistCashier', 'filterHistPayment', 'filterHistStatus', 'filterHistOpType'].forEach(id => {
		const el = document.getElementById(id);
		if (el) {
			el.addEventListener('change', function() {
				const val = this.value.toLowerCase();
				if (val === 'all') {
					document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(r => r.style.display = '');
				} else {
					document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(r => {
						r.style.display = r.innerText.toLowerCase().includes(val) ? '' : 'none';
					});
				}
			});
		}
	});

	// 5. Clic en KPIs para filtrar
	document.querySelectorAll('.hist-kpi-card').forEach(card => {
		card.addEventListener('click', function() {
			const filter = this.getAttribute('data-kpi-filter');
			document.querySelectorAll('#tableTransactionsMaster tbody tr').forEach(row => {
				const text = row.innerText.toLowerCase();
				if (filter === 'reconciled') {
					row.style.display = text.includes('conciliado') ? '' : 'none';
				} else if (filter === 'pending') {
					row.style.display = text.includes('pendiente') ? '' : 'none';
				} else if (filter === 'voided') {
					row.style.display = text.includes('anulado') ? '' : 'none';
				} else if (filter === 'differences' || filter === 'review') {
					row.style.display = (text.includes('descuadre') || text.includes('pendiente')) ? '' : 'none';
				} else {
					row.style.display = '';
				}
			});
		});
	});

	// 6. Selección múltiple de transacciones
	const cbAll = document.getElementById('cbSelectAllTx');
	if (cbAll) {
		cbAll.addEventListener('change', function() {
			document.querySelectorAll('.cb-tx-item').forEach(cb => cb.checked = cbAll.checked);
			updateSelectionCount();
		});
	}

	document.querySelectorAll('.cb-tx-item').forEach(cb => {
		cb.addEventListener('change', updateSelectionCount);
	});

	function updateSelectionCount() {
		const count = document.querySelectorAll('.cb-tx-item:checked').length;
		const el = document.getElementById('txPaginationInfo');
		if (el) {
			el.innerHTML = '<strong>' + count + ' seleccionados</strong> · 1-8 de 5,611 · Página 1 de 702';
		}
	}

	// 7. Modales: Abrir caso, Guardar búsqueda, Reintentar sync
	const btnOpenCase = document.getElementById('btnOpenCase');
	if (btnOpenCase) {
		btnOpenCase.addEventListener('click', function() {
			$('#modalOpenCase').modal('show');
		});
	}

	const btnConfirmOpenCase = document.getElementById('btnConfirmOpenCase');
	if (btnConfirmOpenCase) {
		btnConfirmOpenCase.addEventListener('click', function() {
			toastr.success('Caso de auditoría y conciliación abierto con éxito.');
			$('#modalOpenCase').modal('hide');
		});
	}

	const btnSaveSearch = document.getElementById('btnHistSaveSearch');
	if (btnSaveSearch) {
		btnSaveSearch.addEventListener('click', function() {
			$('#modalSaveSearch').modal('show');
		});
	}

	const btnConfirmSaveSearch = document.getElementById('btnConfirmSaveSearch');
	if (btnConfirmSaveSearch) {
		btnConfirmSaveSearch.addEventListener('click', function() {
			toastr.success('Búsqueda guardada y agregada a accesos rápidos.');
			$('#modalSaveSearch').modal('hide');
		});
	}

	const btnRetrySync = document.getElementById('btnRetrySync');
	if (btnRetrySync) {
		btnRetrySync.addEventListener('click', function() {
			toastr.info('Iniciando sincronización segura e idempotente...');
			setTimeout(function() {
				toastr.success('Sincronización de transacciones POS completada sin rechazos.');
			}, 900);
		});
	}

	// Exportar CSV
	const btnExport = document.getElementById('btnExportTx');
	if (btnExport) {
		btnExport.addEventListener('click', function() {
			toastr.success('Exportando registros conciliados a archivo Excel/CSV...');
		});
	}
});
</script>
