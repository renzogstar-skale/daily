@extends('layout.default')

@section('title', 'Devoluciones Logísticas | DAILY')

@section('content')
<link href="{{ $U('/css/daily_returns.css?v=', true) }}{{ daily_asset_version('/css/daily_returns.css') }}" rel="stylesheet">

@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$qtyFmt = fn($v) => intval($v) == floatval($v) ? number_format(floatval($v), 0) : number_format(floatval($v), 1);
	$defaultRows = array_values(array_filter($returns, fn($x) => intval($x['is_pos_return'] ?? 0) === 0));
	$firstReturn = !empty($defaultRows) ? $defaultRows[0] : (!empty($returns) ? $returns[0] : null);
@endphp

<div class="ret-container" data-testid="returns-logistics-page">

	<!-- ====================================================================
	     1. ENCABEZADO LOGÍSTICO (SIN POS COMERCIAL / SIN CAJA)
	     ==================================================================== -->
	<div class="ret-header-row">
		<div class="ret-header-title-box">
			<div class="ret-header-icon">
				<i class="fa-solid fa-boxes-packing"></i>
			</div>
			<div>
				<h1 class="ret-header-title">Devoluciones Logísticas</h1>
				<p class="ret-header-desc">Retorno físico, recepción UPC, inspección de estado, clasificación y conciliación de unidades.</p>
			</div>
		</div>

		<div class="ret-header-controls">
			<form method="get" class="d-inline-flex align-items-center gap-2 m-0" id="formReturnsFilter">
				<select name="branch_id" class="ret-select" onchange="this.form.submit()" aria-label="Seleccionar sucursal">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>

				<select name="type" class="ret-select" onchange="this.form.submit()" aria-label="Seleccionar tipo de retorno">
					<option value="">Todos los tipos</option>
					<option value="devolucion_cliente" @if($typeFilter === 'devolucion_cliente') selected @endif>Devolución Logística</option>
					<option value="cambio" @if($typeFilter === 'cambio') selected @endif>Cambio de Producto</option>
					<option value="devolucion_proveedor" @if($typeFilter === 'devolucion_proveedor') selected @endif>Devolución a Proveedor (RTV)</option>
					<option value="rechazo_recepcion" @if($typeFilter === 'rechazo_recepcion') selected @endif>Rechazo en Recepción</option>
					<option value="daño_transporte" @if($typeFilter === 'daño_transporte') selected @endif>Daño en Transporte</option>
					<option value="vencido" @if($typeFilter === 'vencido') selected @endif>Producto Vencido</option>
					<option value="calidad" @if($typeFilter === 'calidad') selected @endif>Calidad / Defecto</option>
				</select>
			</form>

			<button type="button" class="ret-btn-primary" data-toggle="modal" data-target="#modalNewReturn" id="btnOpenNewReturn">
				<i class="fa-solid fa-plus"></i>
				<span>Nueva solicitud RMA</span>
			</button>

			<div class="dropdown d-inline-block">
				<button type="button" class="ret-btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnExportMenu">
					<span>Exportar</span>
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border" aria-labelledby="btnExportMenu">
					<a class="dropdown-item small" href="#" id="btnExportCsv"><i class="fa-solid fa-file-csv mr-2 text-success"></i>Exportar a CSV</a>
					<a class="dropdown-item small" href="#" id="btnExportExcel"><i class="fa-solid fa-file-excel mr-2 text-success"></i>Exportar a Excel</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item small" href="#" onclick="window.print(); return false;"><i class="fa-solid fa-print mr-2 text-muted"></i>Imprimir reporte</a>
				</div>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     2. CICLO CANÓNICO DE 6 ETAPAS
	     Solicitud -> Recepción -> Inspección -> Clasificación -> Disposición -> Conciliación
	     ==================================================================== -->
	<div class="ret-lifecycle-banner" data-testid="returns-lifecycle-stepper">
		<div class="ret-lifecycle-steps">
			@foreach([
				['solicitud', 1, 'Solicitud', 'RMA generado'],
				['recepcion', 2, 'Recepción', 'Física y UPC'],
				['inspeccion', 3, 'Inspección', 'Estado y fotos'],
				['clasificacion', 4, 'Clasificación', 'Destino por unidad'],
				['disposicion', 5, 'Disposición', 'Autorización / Stock'],
				['conciliacion', 6, 'Conciliación', 'Cierre de unidades'],
			] as $i => $st)
				<div class="ret-lc-step" data-stage-filter="{{ $st[0] }}" title="Filtrar por etapa {{ $st[2] }}">
					<div class="ret-lc-num">{{ $st[1] }}</div>
					<div class="d-flex flex-column" style="line-height: 1.15;">
						<strong class="small">{{ $st[2] }}</strong>
						<span class="text-muted" style="font-size: 0.68rem;">{{ $st[3] }}</span>
					</div>
				</div>
				@if($i < 5)<div class="ret-lc-div"></div>@endif
			@endforeach
		</div>
	</div>

	<!-- ====================================================================
	     3. INDICADORES DE LOGÍSTICA INVERSA (6 KPIs)
	     ==================================================================== -->
	<div class="ret-kpis-grid" data-testid="returns-kpis-grid">
		<div class="ret-kpi-card" data-testid="kpi-retornos-pendientes">
			<div class="ret-kpi-header">
				<span>Retornos pendientes</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Solicitudes RMA pendientes de recepción, inspección, clasificación o disposición."></i>
			</div>
			<div class="ret-kpi-value text-amber">{{ intval($logisticsSummary['pending_count']) }}</div>
			<div class="ret-kpi-sub">{{ $totalCount }} casos totales</div>
		</div>

		<div class="ret-kpi-card" data-testid="kpi-unidades-recibidas">
			<div class="ret-kpi-header">
				<span>Unidades recibidas</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Cantidad física recibida y verificada con UPC frente a lo solicitado."></i>
			</div>
			<div class="ret-kpi-value text-dark">{{ $qtyFmt($logisticsSummary['received_units']) }} <small class="text-muted font-weight-normal">/ {{ $qtyFmt($logisticsSummary['requested_units']) }}</small></div>
			<div class="ret-kpi-sub">Costo inventario: {{ $money($financialSummary['total_cost']) }}</div>
		</div>

		<div class="ret-kpi-card" data-testid="kpi-aptos-reventa">
			<div class="ret-kpi-header">
				<span>Aptos para reventa</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Unidades íntegras autorizadas por supervisor para reingreso a stock vendible."></i>
			</div>
			<div class="ret-kpi-value text-green">{{ $qtyFmt($logisticsSummary['resalable_units']) }} u.</div>
			<div class="ret-kpi-sub">Recuperable: {{ $money($financialSummary['recoverable_value']) }}</div>
		</div>

		<div class="ret-kpi-card" data-testid="kpi-mercancia-danada">
			<div class="ret-kpi-header">
				<span>Dañados / Baja física</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Unidades dañadas, vencidas o en destrucción controlada (bloqueadas para reventa)."></i>
			</div>
			<div class="ret-kpi-value text-red">{{ $qtyFmt($logisticsSummary['damaged_units']) }} u.</div>
			<div class="ret-kpi-sub">Pérdida evaluada: {{ $money($financialSummary['definitive_loss']) }}</div>
		</div>

		<div class="ret-kpi-card" data-testid="kpi-rtv-reparacion">
			<div class="ret-kpi-header">
				<span>RTV y Reparación</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Unidades destinadas a devolución a proveedor (RTV) o taller de reacondicionamiento."></i>
			</div>
			<div class="ret-kpi-value text-cyan">{{ $qtyFmt($logisticsSummary['rtv_units'] + $logisticsSummary['repairable_units']) }} u.</div>
			<div class="ret-kpi-sub">RTV {{ $qtyFmt($logisticsSummary['rtv_units']) }} · Rep. {{ $qtyFmt($logisticsSummary['repairable_units']) }}</div>
		</div>

		<div class="ret-kpi-card" data-testid="kpi-conciliacion-unidades">
			<div class="ret-kpi-header">
				<span>Conciliación unidades</span>
				<i class="fa-solid fa-circle-info ret-kpi-info-icon" data-toggle="tooltip" title="Porcentaje de unidades recibidas con destino final autorizado y conciliado."></i>
			</div>
			<div class="ret-kpi-value text-purple">{{ number_format(floatval($logisticsSummary['reconcile_pct']), 1) }}%</div>
			<div class="ret-kpi-sub">{{ $qtyFmt($logisticsSummary['conciliated_units']) }} u. conciliadas</div>
		</div>
	</div>

	<!-- ====================================================================
	     4. BANNER DE CONTROL SANITARIO, REINGRESO Y CONCILIACIÓN
	     ==================================================================== -->
	<div class="ret-banner-quality">
		<div class="ret-banner-left">
			<div class="ret-banner-shield">
				<i class="fa-solid fa-shield-halved"></i>
			</div>
			<div>
				<span class="ret-banner-text-main">Control estricto de calidad y reingreso</span>
				<span class="ret-banner-text-sub">Mercancía dañada o perecedera devuelta queda bloqueada para reventa. Ningún reingreso al inventario ocurre sin inspección y autorización de supervisor.</span>
				<a href="#" class="ret-banner-link" data-toggle="modal" data-target="#modalQualityPolicy">Ver política &rarr;</a>
			</div>
		</div>
		<div class="ret-banner-right">
			<i class="fa-solid fa-scale-balanced mr-1 text-muted"></i>
			Separación activa: devoluciones comerciales POS y caja permanecen fuera de logística física.<br>
			<span class="text-dark font-weight-bold" style="cursor: pointer;" data-toggle="modal" data-target="#modalReconciliation">
				Conciliación de unidades y costos ({{ $money($financialSummary['total_cost']) }} evaluados) &rarr;
			</span>
		</div>
	</div>

	<!-- ====================================================================
	     5. BANDEJA PRINCIPAL (TABLA) + PANEL DE DETALLE
	     ==================================================================== -->
	<div class="ret-workspace-split">

		<!-- LISTADO DE CASOS (IZQUIERDA) -->
		<div class="ret-table-card">
			<div class="ret-table-header">
				<h2 class="ret-table-title">
					<i class="fa-solid fa-list-ul"></i>
					Expedientes RMA y Retornos Físicos
				</h2>
				<div class="ret-table-search-box">
					<input type="text" class="ret-search-input" id="searchReturnsTable" placeholder="Buscar RMA, folio, SKU o producto..." aria-label="Buscar en la tabla">
					<div class="dropdown">
						<button class="ret-btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<i class="fa-solid fa-sliders"></i> Etapas
						</button>
						<div class="dropdown-menu dropdown-menu-right p-3 shadow-sm" style="min-width: 240px;">
							<div class="font-weight-bold small text-dark mb-2">Filtro rápido por etapa</div>
							<div class="form-check small mb-1">
								<input class="form-check-input filter-check" type="checkbox" value="all" id="chkFilterAll" checked>
								<label class="form-check-label" for="chkFilterAll">Todas las etapas</label>
							</div>
							@foreach(['solicitud' => '1. Solicitud RMA', 'recepcion' => '2. Recepción física', 'inspeccion' => '3. En inspección', 'clasificacion' => '4. Clasificación', 'disposicion' => '5. Disposición autorizada', 'conciliacion' => '6. Conciliación'] as $stVal => $stLbl)
							<div class="form-check small mb-1">
								<input class="form-check-input filter-check" type="checkbox" value="{{ $stVal }}" id="chkFilter_{{ $stVal }}">
								<label class="form-check-label" for="chkFilter_{{ $stVal }}">{{ $stLbl }}</label>
							</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>

			<!-- ESCANEO RÁPIDO UPC / RMA PARA RECEPCIÓN -->
			<div class="ret-upc-bar">
				<i class="fa-solid fa-barcode text-success"></i>
				<input type="text" class="form-control form-control-sm" id="inputQuickUpcScan" placeholder="Escanear UPC, SKU o RMA (ej. RMA-2026-0019) para recepción rápida..." aria-label="Escanear UPC o RMA">
				<button type="button" class="btn btn-sm btn-success font-weight-bold text-nowrap" id="btnQuickUpcScan">
					<i class="fa-solid fa-qrcode mr-1"></i>Buscar / Recibir
				</button>
			</div>

			<!-- PESTAÑAS DE SEPARACIÓN ESTRICTA DE CANAL Y ESTADO -->
			<div class="ret-tabs-row" data-testid="returns-channel-tabs">
				<button type="button" class="ret-tab-pill active" data-tab-filter="logistics" id="tabFilterLogistics">
					Retornos Físicos RMA ({{ $logisticsCount }})
				</button>
				<button type="button" class="ret-tab-pill" data-tab-filter="pos_handoff" id="tabFilterPosHandoff" title="Solo inspección física de unidades recibidas desde mostrador POS (sin datos comerciales)">
					Inspección Física desde POS ({{ $posHandoffCount }})
				</button>
				<button type="button" class="ret-tab-pill" data-tab-filter="en_proceso" id="tabFilterProcess">
					Pendientes ({{ $enProcesoCount }})
				</button>
				<button type="button" class="ret-tab-pill" data-tab-filter="conciliados" id="tabFilterClosed">
					Conciliados ({{ $cerradosCount }})
				</button>
			</div>

			<!-- TABLA DE CASOS -->
			<div class="ret-table-responsive">
				<table class="ret-table" id="tableReturns">
					<thead>
						<tr>
							<th>RMA / Folio</th>
							<th>Producto / SKU</th>
							<th>Tipo / Canal</th>
							<th class="text-center">Sol. / Rec. / Con.</th>
							<th class="text-right">Costo Inv.</th>
							<th>Condición y Destino</th>
							<th>Etapa</th>
							<th class="text-center">Acciones</th>
						</tr>
					</thead>
					<tbody>
						@forelse($returns as $idx => $r)
							@php
								$isPosHandoff = intval($r['is_pos_return'] ?? 0) === 1;
								$isFirstLogistics = ($firstReturn && intval($firstReturn['id']) === intval($r['id']));
								$rowCost = floatval($r['total_cost']);
								$groupStatus = $r['group_status'] ?? 'en_proceso';
								$stMeta = $r['stage_meta'];
								$dstMeta = $r['dest_meta'];
								$progressPct = round(($stMeta['num'] / 6) * 100);
								$barColor = ($stMeta['num'] >= 5) ? 'bg-green' : 'bg-amber';
								$rmaNum = $r['rma_number'] ?: $r['return_code'];
							@endphp
							<tr class="ret-table-row @if($isFirstLogistics) is-active @endif @if($isPosHandoff) d-none-init @endif"
								style="@if($isPosHandoff) display: none; @endif"
								data-id="{{ $r['id'] }}"
								data-code="{{ $r['return_code'] }}"
								data-rma="{{ $rmaNum }}"
								data-channel="{{ $isPosHandoff ? 'pos_handoff' : 'logistics' }}"
								data-group="{{ $groupStatus }}"
								data-status="{{ $r['status'] }}"
								data-search="{{ strtolower($rmaNum . ' ' . $r['return_code'] . ' ' . $r['product_name'] . ' ' . ($r['order_code'] ?? '') . ' ' . ($r['sku'] ?? '') . ' ' . ($r['primary_barcode'] ?? '')) }}">

								<td>
									<div class="ret-folio-badge">{{ $rmaNum }} <a href="#" class="dpf-link" data-process-flow="customer_return:{{ $r['id'] }}" title="Ver trazabilidad"><i class="fa-solid fa-diagram-next"></i></a></div>
									<div class="ret-date-sub">{{ $r['return_code'] }} &middot; {{ substr($r['created_at'], 0, 10) }}</div>
								</td>

								<td>
									<div class="ret-product-cell">
										<img src="{{ $U($r['thumbnail']) }}" alt="{{ $r['product_name'] }}" class="ret-product-thumb" loading="lazy">
										<div class="ret-product-meta">
											<span class="ret-product-name">{{ $r['product_name'] }}</span>
											<span class="ret-order-sub">SKU: {{ $r['sku'] ?: 'N/A' }} @if(!empty($r['primary_barcode'])) &middot; UPC: {{ $r['primary_barcode'] }} @endif</span>
											<span class="ret-branch-sub">{{ $r['branch_name'] }}</span>
										</div>
									</div>
								</td>

								<td>
									@php
										$typeLabels = [
											'devolucion_cliente' => 'Retorno cliente',
											'cambio' => 'Cambio físico',
											'rechazo_recepcion' => 'Rechazo muelle',
											'daño_transporte' => 'Daño transporte',
											'calidad' => 'Calidad',
											'vencido' => 'Vencido',
											'devolucion_proveedor' => 'RTV proveedor'
										];
										$tLbl = $typeLabels[$r['return_type']] ?? ucwords(str_replace('_', ' ', $r['return_type']));
									@endphp
									<span class="ret-type-pill">{{ $tLbl }}</span>
									@if($isPosHandoff)
										<div class="ret-dest-sub text-info">Derivado POS (solo físico)</div>
									@endif
								</td>

								<td class="ret-qty-cell text-center">
									<span>{{ $qtyFmt($r['quantity']) }}</span> /
									<strong class="text-dark">{{ $qtyFmt($r['received_quantity']) }}</strong> /
									<strong class="text-success">{{ $qtyFmt($r['conciliated_quantity']) }}</strong>
								</td>

								<td class="ret-cost-cell">
									{{ $money($rowCost) }}
								</td>

								<td>
									<div>
										<span class="ret-dest-pill {{ $dstMeta['badge_class'] }}">
											{{ $dstMeta['title'] }}
										</span>
									</div>
									<div class="ret-dest-sub">Condición: {{ str_replace('_', ' ', $r['condition_grade'] ?? 'sin inspeccionar') }}</div>
								</td>

								<td>
									<div>
										<span class="ret-stage-badge {{ $stMeta['badge'] }}">
											{{ $stMeta['num'] }}. {{ $stMeta['name'] }}
										</span>
									</div>
									<div class="ret-stage-bar">
										<div class="ret-stage-bar-fill {{ $barColor }}" style="width: {{ $progressPct }}%;"></div>
									</div>
								</td>

								<td class="text-center text-nowrap">
									<a href="#" class="ret-action-link btn-select-row" data-id="{{ $r['id'] }}">
										<span>Gestionar</span> &rarr;
									</a>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="8" class="p-4 text-center text-muted">
									No hay retornos logísticos registrados para los filtros seleccionados.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- PANEL DE DETALLE (DERECHA, STICKY) -->
		<div class="ret-detail-panel" id="returnsDetailPanel">
			@if($firstReturn)
				@php
					$fStage = $firstReturn['stage_meta'];
					$fDest = $firstReturn['dest_meta'];
					$fRma = $firstReturn['rma_number'] ?: $firstReturn['return_code'];
				@endphp
				<div class="ret-detail-header">
					<div class="ret-detail-title-box">
						<div class="ret-detail-icon"><i class="fa-solid fa-barcode"></i></div>
						<div>
							<span class="ret-detail-folio" id="detailRma">{{ $fRma }}</span>
							<div class="small text-muted" id="detailFolio">{{ $firstReturn['return_code'] }}</div>
						</div>
						<span class="ret-detail-status-pill" id="detailStatusBadge">{{ $fStage['name'] }}</span>
					</div>
					<button type="button" class="ret-detail-close-btn" id="btnDetailClose" title="Cerrar panel">&times;</button>
				</div>

				<div class="ret-detail-product-card">
					<img src="{{ $U($firstReturn['thumbnail']) }}" alt="{{ $firstReturn['product_name'] }}" class="ret-detail-product-img" id="detailProductImg">
					<div class="ret-detail-product-info">
						<span class="ret-detail-product-title" id="detailProductName">{{ $firstReturn['product_name'] }}</span>
						<span class="ret-detail-product-qty" id="detailProductQty">
							Solicitado: {{ $qtyFmt($firstReturn['quantity']) }} u. &middot; Recibido: {{ $qtyFmt($firstReturn['received_quantity']) }} u. &middot; Conciliado: {{ $qtyFmt($firstReturn['conciliated_quantity']) }} u.
						</span>
						<span class="ret-detail-product-cost" id="detailProductCost">Costo inventario: {{ $money($firstReturn['total_cost']) }}</span>
					</div>
				</div>

				<div class="ret-detail-section">
					<div class="ret-detail-meta-row">
						<i class="fa-solid fa-box ret-detail-meta-icon"></i>
						<span class="ret-detail-meta-label">Tipo:</span>
						<span class="ret-detail-meta-value text-capitalize" id="detailOrigin">{{ str_replace('_', ' ', $firstReturn['return_type']) }}</span>
					</div>
					<div class="ret-detail-meta-row">
						<i class="fa-solid fa-barcode ret-detail-meta-icon"></i>
						<span class="ret-detail-meta-label">UPC / SKU:</span>
						<span class="ret-detail-meta-value" id="detailUpcScanned">{{ $firstReturn['upc_scanned'] ?: ($firstReturn['sku'] ?: 'Sin escanear') }}</span>
					</div>
					<div class="ret-detail-meta-row">
						<i class="fa-regular fa-file-lines ret-detail-meta-icon"></i>
						<span class="ret-detail-meta-label">Ref. Logística:</span>
						<span class="ret-detail-meta-value" id="detailOrderCode">{{ $firstReturn['order_code'] ?: 'Directo en muelle' }}</span>
					</div>
					<div class="ret-detail-meta-row">
						<i class="fa-solid fa-store ret-detail-meta-icon"></i>
						<span class="ret-detail-meta-label">Sucursal:</span>
						<span class="ret-detail-meta-value" id="detailBranchName">{{ $firstReturn['branch_name'] }}</span>
					</div>
					<div class="ret-detail-meta-row">
						<i class="fa-regular fa-calendar ret-detail-meta-icon"></i>
						<span class="ret-detail-meta-label">Fecha:</span>
						<span class="ret-detail-meta-value" id="detailCreatedAt">{{ substr($firstReturn['created_at'], 0, 16) }}</span>
					</div>
				</div>

				<div class="ret-detail-dest-box">
					<i class="fa-solid fa-shield-halved ret-detail-dest-shield"></i>
					<div class="ret-detail-dest-text">
						<span class="ret-detail-dest-title">Destino y Condición</span>
						<span class="ret-detail-dest-name" id="detailDestName">{{ $fDest['title'] }}</span>
						<span class="small text-muted" id="detailStockReentryBadge">
							@if(intval($firstReturn['stock_reentered'] ?? 0) === 1)
								<span class="text-success font-weight-bold"><i class="fa-solid fa-check-double mr-1"></i>Reingresado a inventario ({{ $firstReturn['stock_reentry_tx_id'] }})</span>
							@else
								Sin reingreso automático al stock
							@endif
						</span>
					</div>
				</div>

				<div class="ret-detail-note" id="detailNoticeBox">
					<i class="fa-solid fa-circle-info"></i>
					<span id="detailNoticeText">{{ $fDest['detail_notice'] }}</span>
				</div>

				<div class="ret-detail-tabs">
					<button type="button" class="ret-detail-tab-btn active" data-pane="tabPaneDetail">Detalle</button>
					<button type="button" class="ret-detail-tab-btn" data-pane="tabPaneInspection">Inspección</button>
					<button type="button" class="ret-detail-tab-btn" data-pane="tabPaneEvidence">Evidencia</button>
					<button type="button" class="ret-detail-tab-btn" data-pane="tabPaneHistory">Auditoría</button>
				</div>

				<!-- PESTAÑA 1: DETALLE (STEPPER 6 ETAPAS + ACCIÓN CONTEXTUAL) -->
				<div class="ret-detail-pane-content" id="tabPaneDetail">
					<div class="ret-stepper-container">
						<div class="ret-stepper-title">
							<span id="detailStepperLabel">Etapa {{ $fStage['num'] }} de 6 &middot; {{ $fStage['name'] }}</span>
						</div>
						<div class="ret-stepper-dots" id="detailStepperDots">
							@for($s = 1; $s <= 6; $s++)
								@php
									$dotClass = '';
									if ($s < $fStage['num'] || ($fStage['num'] == 6 && $s == 6)) $dotClass = 'done';
									elseif ($s == $fStage['num']) $dotClass = 'active';
								@endphp
								<div class="ret-step-circle {{ $dotClass }}" data-step="{{ $s }}">
									@if($dotClass === 'done')
										<i class="fa-solid fa-check"></i>
									@else
										{{ $s }}
									@endif
								</div>
							@endfor
						</div>
					</div>

					<div class="ret-detail-info-disclaimer">
						<i class="fa-solid fa-lock"></i>
						<span>Todo reingreso al stock requiere inspección física previa y autorización de supervisor.</span>
					</div>

					<div class="ret-detail-actions">
						<button type="button" class="ret-btn-block-primary btn-stage-action" id="btnDetailPrimaryAction"
								data-id="{{ $firstReturn['id'] }}"
								data-code="{{ $firstReturn['return_code'] }}"
								data-status="{{ $firstReturn['status'] }}"
								data-stage="{{ $fStage['num'] }}">
							<i class="fa-solid fa-forward-step"></i>
							<span id="detailPrimaryActionText">{{ $fStage['action'] }}</span>
						</button>

						<div class="ret-detail-sub-actions">
							<button type="button" class="ret-btn-block-secondary" id="btnDetailOpenReceive" data-id="{{ $firstReturn['id'] }}">
								<i class="fa-solid fa-barcode"></i>
								<span>Recepción UPC</span>
							</button>
							<button type="button" class="ret-btn-block-secondary btn-register-evidence-detail" id="btnDetailEvidence" data-id="{{ $firstReturn['id'] }}" data-code="{{ $fRma }}">
								<i class="fa-solid fa-camera"></i>
								<span>Foto evidencia</span>
							</button>
						</div>
					</div>
				</div>

				<!-- PESTAÑA 2: INSPECCIÓN Y DESGLOSE POR UNIDAD -->
				<div class="ret-detail-pane-content d-none" id="tabPaneInspection">
					<div class="p-2 small bg-light rounded border mb-2">
						<strong class="text-dark d-block mb-1">Dictamen e Inspección Física:</strong>
						<p class="text-muted mb-0" id="detailInspectionNotes">{{ $firstReturn['inspection_notes'] ?: 'Sin inspección registrada.' }}</p>
					</div>
					<div class="p-2 small border rounded bg-white mb-2">
						<div class="d-flex justify-content-between mb-1">
							<span class="text-muted">Condición física:</span>
							<strong class="text-dark text-capitalize" id="detailConditionGrade">{{ str_replace('_', ' ', $firstReturn['condition_grade'] ?? 'sin_inspeccionar') }}</strong>
						</div>
						<div class="d-flex justify-content-between mb-1">
							<span class="text-muted">Inspector:</span>
							<strong class="text-dark" id="detailInspectorName">{{ $firstReturn['inspector_name'] ?: 'Pendiente' }}</strong>
						</div>
						<div class="d-flex justify-content-between mb-1">
							<span class="text-muted">Control perecedero:</span>
							<span id="detailPerishableBadge">
								@if($firstReturn['is_perishable'])
									<span class="badge badge-danger">Perecedero (Bloqueo sanitario)</span>
								@else
									<span class="badge badge-light border">No perecedero</span>
								@endif
							</span>
						</div>
						<div class="d-flex justify-content-between">
							<span class="text-muted">Autorizó disposición:</span>
							<strong class="text-success" id="detailAuthorizerName">{{ $firstReturn['authorized_by_name'] ?: 'Pendiente' }}</strong>
						</div>
					</div>
					<div class="p-2 small border rounded bg-white">
						<strong class="d-block text-dark mb-1">Desglose de unidades clasificadas:</strong>
						<div class="d-flex justify-content-between"><span>Apto reventa:</span><strong class="text-success" id="detailQtyResalable">{{ $qtyFmt($firstReturn['resalable_quantity'] ?? 0) }}</strong></div>
						<div class="d-flex justify-content-between"><span>Dañado / Merma:</span><strong class="text-danger" id="detailQtyDamaged">{{ $qtyFmt($firstReturn['damaged_quantity'] ?? 0) }}</strong></div>
						<div class="d-flex justify-content-between"><span>Proveedor (RTV):</span><strong class="text-info" id="detailQtyRtv">{{ $qtyFmt($firstReturn['rtv_quantity'] ?? 0) }}</strong></div>
						<div class="d-flex justify-content-between"><span>Reparación / Taller:</span><strong class="text-primary" id="detailQtyRepairable">{{ $qtyFmt($firstReturn['repairable_quantity'] ?? 0) }}</strong></div>
					</div>
				</div>

				<!-- PESTAÑA 3: EVIDENCIA FOTOGRÁFICA REAL -->
				<div class="ret-detail-pane-content d-none" id="tabPaneEvidence">
					<div id="detailEvidenceGallery" class="ret-evidence-grid mb-2">
						@forelse($firstReturn['evidence_photos'] ?? [] as $ph)
							<div class="ret-evidence-thumb">
								<img src="{{ str_starts_with($ph['photo_data'], 'data:') ? $ph['photo_data'] : $U($ph['photo_data']) }}" alt="Evidencia RMA">
								<div class="ret-evidence-caption">{{ $ph['caption'] ?: 'Evidencia' }} &middot; {{ substr($ph['created_at'], 0, 10) }}</div>
							</div>
						@empty
							<div class="col-span-2 p-3 text-center border rounded bg-light small text-muted" style="grid-column: span 2;">
								Sin fotografías adjuntas todavía.
							</div>
						@endforelse
					</div>
					<button type="button" class="btn btn-sm btn-outline-success btn-block font-weight-bold btn-register-evidence-detail" data-id="{{ $firstReturn['id'] }}" data-code="{{ $fRma }}">
						<i class="fa-solid fa-camera mr-1"></i>Adjuntar fotografía de inspección
					</button>
				</div>

				<!-- PESTAÑA 4: BITÁCORA DE AUDITORÍA -->
				<div class="ret-detail-pane-content d-none" id="tabPaneHistory">
					<ul class="list-unstyled small mb-0 pl-2 border-left" style="margin-left: 0.5rem;" id="detailHistoryList">
						@forelse($firstReturn['audit_log'] ?? [] as $aud)
							<li class="mb-2">
								<div class="font-weight-bold text-dark">{{ str_replace('_', ' ', ucfirst($aud['action'])) }} ({{ $aud['stage'] }})</div>
								<div class="text-muted">{{ $aud['details'] }}</div>
								<div class="text-muted" style="font-size: 0.7rem;">{{ substr($aud['created_at'], 0, 16) }} &middot; {{ $aud['username'] }}</div>
							</li>
						@empty
							<li class="text-muted">Sin eventos registrados.</li>
						@endforelse
					</ul>
				</div>
			@else
				<div class="p-4 text-center text-muted">
					Seleccione un expediente RMA para gestionar su ciclo logístico.
				</div>
			@endif
		</div>
	</div>

	<!-- ====================================================================
	     6. SECCIÓN INFERIOR: 3 TARJETAS (DESTINOS, CONTROL UNIDADES/COSTO, MÓDULOS LOGÍSTICOS)
	     ==================================================================== -->
	<div class="ret-bottom-grid">
		<div class="ret-bottom-card">
			<div>
				<div class="ret-bottom-header">
					<h3 class="ret-bottom-title">
						<i class="fa-solid fa-boxes-packing"></i>
						Clasificación y destinos
					</h3>
					<a href="#" class="ret-bottom-link" data-toggle="modal" data-target="#modalCatalogDestinations">
						Ver catálogo &rarr;
					</a>
				</div>
				<p class="ret-bottom-sub">Cada unidad recibida debe asignarse a un destino final tras inspección:</p>
			</div>

			<div class="ret-destinos-grid">
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_reincorporacion.jpg') }}" alt="Apto para reventa" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-circle-check text-success" style="font-size: 0.65rem;"></i>Apto para reventa</span>
						<span class="ret-destino-sub">Reingreso autorizado</span>
					</div>
				</div>
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_merma.jpg') }}" alt="Producto dañado" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-trash-can text-danger" style="font-size: 0.65rem;"></i>Producto dañado</span>
						<span class="ret-destino-sub">Bloqueo de reventa</span>
					</div>
				</div>
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_rtv.jpg') }}" alt="Devolución a proveedor" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-truck-ramp-box text-info" style="font-size: 0.65rem;"></i>Proveedor (RTV)</span>
						<span class="ret-destino-sub">Nota de crédito RTV</span>
					</div>
				</div>
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_reincorporacion.jpg') }}" alt="Reparación" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-wrench text-primary" style="font-size: 0.65rem;"></i>Reparación</span>
						<span class="ret-destino-sub">Reacondicionamiento</span>
					</div>
				</div>
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_donacion.jpg') }}" alt="Donación" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-hand-holding-heart text-teal" style="font-size: 0.65rem;"></i>Donación</span>
						<span class="ret-destino-sub">Banco de alimentos</span>
					</div>
				</div>
				<div class="ret-destino-item" data-toggle="modal" data-target="#modalCatalogDestinations">
					<img src="{{ $U('/img/control_center/dest_destruccion.jpg') }}" alt="Destrucción" class="ret-destino-img" loading="lazy">
					<div class="ret-destino-info">
						<span class="ret-destino-name"><i class="fa-solid fa-biohazard text-dark" style="font-size: 0.65rem;"></i>Destrucción</span>
						<span class="ret-destino-sub">Acta ambiental</span>
					</div>
				</div>
			</div>
		</div>

		<!-- CONTROL DE UNIDADES Y COSTO LOGÍSTICO DEL CASO -->
		<div class="ret-bottom-card">
			<div>
				<div class="ret-bottom-header">
					<h3 class="ret-bottom-title">
						<i class="fa-solid fa-scale-balanced"></i>
						Conciliación del expediente
					</h3>
					<a href="#" class="ret-bottom-link" data-toggle="modal" data-target="#modalReconciliation">
						Ver conciliación &rarr;
					</a>
				</div>
				<p class="ret-bottom-sub">Balance físico de unidades y costo de inventario evaluado (sin reembolsos de caja POS).</p>
			</div>

			<div class="ret-econ-list">
				<div class="ret-econ-row">
					<span class="ret-econ-label">Costo inventario del RMA</span>
					<span class="ret-econ-value text-dark" id="econCaseCost">{{ $firstReturn ? $money($firstReturn['total_cost']) : '$0.00' }}</span>
				</div>
				<div class="ret-econ-row">
					<span class="ret-econ-label">Unidades solicitadas vs recibidas</span>
					<span class="ret-econ-value text-dark" id="econQtyCompare">
						{{ $firstReturn ? ($qtyFmt($firstReturn['quantity']) . ' / ' . $qtyFmt($firstReturn['received_quantity']) . ' u.') : '0 / 0 u.' }}
					</span>
				</div>
				<div class="ret-econ-row">
					<span class="ret-econ-label">Unidades conciliadas</span>
					<span class="ret-econ-value text-green" id="econConciliatedQty">{{ $firstReturn ? $qtyFmt($firstReturn['conciliated_quantity']) . ' u.' : '0 u.' }}</span>
				</div>
				<div class="ret-econ-row">
					<span class="ret-econ-label">Valor recuperable / reventa</span>
					<span class="ret-econ-value text-muted" id="econRecoverable">{{ ($firstReturn && floatval($firstReturn['recoverable_value']) > 0) ? $money($firstReturn['recoverable_value']) : '—' }}</span>
				</div>
				<div class="ret-econ-row">
					<span class="ret-econ-label">Crédito proveedor (RTV)</span>
					<span class="ret-econ-value text-muted" id="econSupplierCredit">{{ ($firstReturn && floatval($firstReturn['supplier_credit_amount']) > 0) ? $money($firstReturn['supplier_credit_amount']) : '—' }}</span>
				</div>
				<div class="ret-econ-row">
					<span class="ret-econ-label">Pérdida por daño / merma</span>
					<span class="ret-econ-value text-muted" id="econLoss">{{ ($firstReturn && floatval($firstReturn['definitive_loss_value']) > 0) ? $money($firstReturn['definitive_loss_value']) : '—' }}</span>
				</div>
			</div>
		</div>

		<!-- MÓDULOS LOGÍSTICOS RELACIONADOS (SIN POS / SIN CAJA / SIN SEGURIDAD / SIN COMPRAS NUEVAS) -->
		<div class="ret-bottom-card">
			<div>
				<div class="ret-bottom-header">
					<h3 class="ret-bottom-title">
						<i class="fa-solid fa-network-wired"></i>
						Logística e Inventario
					</h3>
				</div>
				<p class="ret-bottom-sub">Trazabilidad operativa de bodega, despachos y movimientos:</p>
			</div>

			<div class="ret-mod-list">
				<a href="{{ $U('/dispatch') }}" class="ret-mod-item">
					<div class="ret-mod-left">
						<i class="fa-solid fa-truck-fast ret-mod-icon"></i>
						<div class="ret-mod-text">
							<span class="ret-mod-name">Despachos OMS/WMS</span>
							<span class="ret-mod-desc">Pedidos asociados y entregas parciales</span>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right ret-mod-arrow"></i>
				</a>

				<a href="{{ $U('/kardex') }}" class="ret-mod-item">
					<div class="ret-mod-left">
						<i class="fa-solid fa-arrow-right-arrow-left ret-mod-icon"></i>
						<div class="ret-mod-text">
							<span class="ret-mod-name">Kardex de Inventario</span>
							<span class="ret-mod-desc">Movimientos auditados de reingreso</span>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right ret-mod-arrow"></i>
				</a>

				<a href="{{ $U('/stockoverview') }}" class="ret-mod-item">
					<div class="ret-mod-left">
						<i class="fa-solid fa-boxes-stacked ret-mod-icon"></i>
						<div class="ret-mod-text">
							<span class="ret-mod-name">Existencias y Ubicaciones</span>
							<span class="ret-mod-desc">Stock disponible por bodega y lote</span>
						</div>
					</div>
					<i class="fa-solid fa-chevron-right ret-mod-arrow"></i>
				</a>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 1: NUEVA SOLICITUD RMA (CON IDEMPOTENCY KEY ANTI-DUPLICADOS)
     ==================================================================== -->
<div class="modal fade" id="modalNewReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-file-circle-plus mr-2 text-success"></i>Nueva Solicitud de Retorno (RMA)
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<form id="formNewReturn">
					<input type="hidden" id="inputReturnIdempotencyKey" value="">
					<div class="row">
						<div class="col-md-7 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Producto:</label>
							<select class="form-control form-control-sm font-weight-bold" id="inputReturnProductId" required>
								<option value="">Seleccione producto...</option>
								@foreach($products as $p)
									<option value="{{ $p['id'] }}" data-cost="{{ $p['last_cost'] ?: 1.0 }}" data-sku="{{ $p['sku'] }}" data-barcode="{{ $p['barcode'] }}">{{ $p['name'] }} (SKU: {{ $p['sku'] }})</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-5 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Tipo de retorno logístico:</label>
							<select class="form-control form-control-sm font-weight-bold" id="inputReturnType">
								<option value="devolucion_cliente">Retorno físico de cliente</option>
								<option value="cambio">Cambio físico de mercancía</option>
								<option value="devolucion_proveedor">Devolución a Proveedor (RTV)</option>
								<option value="rechazo_recepcion">Rechazo en muelle de recepción</option>
								<option value="daño_transporte">Daño en transporte</option>
								<option value="calidad">No conformidad de calidad</option>
								<option value="vencido">Producto vencido</option>
							</select>
						</div>
					</div>

					<div class="row">
						<div class="col-md-3 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Cantidad solicitada:</label>
							<input type="number" step="0.5" class="form-control form-control-sm font-weight-bold" id="inputReturnQuantity" value="1" min="0.1" required>
						</div>
						<div class="col-md-3 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Costo unitario ($):</label>
							<input type="number" step="0.01" class="form-control form-control-sm" id="inputReturnUnitCost" value="1.00">
						</div>
						<div class="col-md-3 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Pedido asociado:</label>
							<select class="form-control form-control-sm" id="inputReturnOrderId">
								<option value="">Sin pedido directo</option>
								@foreach($orders as $ord)
									<option value="{{ $ord['id'] }}">{{ $ord['order_code'] }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Proveedor RTV (opc.):</label>
							<select class="form-control form-control-sm" id="inputReturnSupplierId">
								<option value="">N/A</option>
								@foreach($suppliers as $sup)
									<option value="{{ $sup['id'] }}">{{ $sup['name'] }}</option>
								@endforeach
							</select>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Documento / Guía / Remisión (opc.):</label>
							<input type="text" class="form-control form-control-sm" id="inputReturnRefDoc" placeholder="Ej. GUIA-4490 o REM-2026-01">
						</div>
						<div class="col-md-6 col-12 mb-3">
							<label class="font-weight-bold small text-dark">Sucursal:</label>
							<select class="form-control form-control-sm" id="inputReturnBranchId">
								@foreach($branches as $b)
									<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
								@endforeach
							</select>
						</div>
					</div>

					<div class="form-group mb-2">
						<label class="font-weight-bold small text-dark">Motivo de solicitud RMA:</label>
						<textarea class="form-control form-control-sm" id="inputReturnReason" rows="2" placeholder="Empaque averiado en tránsito, rechazo en entrega parcial, defecto..." required></textarea>
					</div>

					<div class="alert alert-info small mb-0 mt-2">
						<i class="fa-solid fa-route mr-1"></i>La solicitud iniciará en <strong>Etapa 1 (Solicitud RMA)</strong>. Deberá pasar por recepción física UPC, inspección de estado, clasificación y disposición autorizada antes de conciliar.
					</div>
				</form>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitNewReturn">
					<i class="fa-solid fa-floppy-disk mr-1"></i>Generar RMA
				</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 2: RECEPCIÓN FÍSICA Y ESCANEO UPC (ETAPA 2)
     ==================================================================== -->
<div class="modal fade" id="modalReceiveReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-barcode mr-2 text-success"></i>Etapa 2 · Recepción Física UPC</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="receiveReturnId">
				<div class="p-2 bg-white border rounded mb-3 small">
					<strong id="receiveRmaTitle" class="text-dark">RMA</strong> &middot; <span id="receiveProductLabel" class="text-muted">Producto</span><br>
					<span>Cantidad solicitada en RMA: <strong id="receiveExpectedQty">0</strong> u.</span>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Escaneo UPC / Código de barras / SKU:</label>
					<input type="text" class="form-control form-control-sm font-weight-bold" id="inputReceiveUpc" placeholder="Escanee código UPC o SKU del producto">
					<small class="text-muted">Si escanea un código de otro SKU, la recepción se bloqueará automáticamente.</small>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Cantidad física recibida:</label>
					<input type="number" step="0.5" min="0.1" class="form-control form-control-sm font-weight-bold" id="inputReceiveQty">
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Observaciones de muelle (opc.):</label>
					<input type="text" class="form-control form-control-sm" id="inputReceiveNotes" placeholder="Caja sellada, faltante reportado en guía...">
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitReceiveReturn"><i class="fa-solid fa-check mr-1"></i>Confirmar Recepción Física</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 3: INSPECCIÓN FÍSICA Y FOTOGRAFÍA (ETAPA 3)
     ==================================================================== -->
<div class="modal fade" id="modalInspectReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-magnifying-glass mr-2 text-warning"></i>Etapa 3 · Inspección de Estado</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="inspectReturnId">
				<div class="p-2 bg-white border rounded mb-3 small">
					<strong id="inspectRmaTitle" class="text-dark">RMA</strong> &middot; Recibidas: <strong id="inspectReceivedQty">0</strong> u.
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Estado físico dictaminado:</label>
					<select class="form-control form-control-sm font-weight-bold" id="inputInspectGrade">
						<option value="intacto">Intacto (Empaque y sello originales íntegros)</option>
						<option value="empaque_abierto">Empaque abierto / cosmético</option>
						<option value="reparable">Reparable / Reacondicionable</option>
						<option value="danado">Dañado / Averiado (Bloqueado para reventa)</option>
						<option value="vencido">Vencido / Cadena fría rota (Bloqueado)</option>
						<option value="defectuoso_fabrica">Defecto de fábrica (Candidato RTV)</option>
					</select>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Observaciones de inspección:</label>
					<textarea class="form-control form-control-sm" id="inputInspectNotes" rows="2" placeholder="Detalle el estado de sello, caducidad o daño observado..." required></textarea>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Foto de inspección (opcional en este paso):</label>
					<input type="file" class="form-control-file border p-2 bg-white rounded small" id="inputInspectPhoto" accept="image/*" capture="environment">
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitInspectReturn"><i class="fa-solid fa-clipboard-check mr-1"></i>Guardar Inspección</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 4: CLASIFICACIÓN DE UNIDADES Y DESTINO FINAL (ETAPA 4)
     ==================================================================== -->
<div class="modal fade" id="modalClassifyReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-code-branch mr-2 text-info"></i>Etapa 4 · Clasificación de Mercancía</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="classifyReturnId">
				<input type="hidden" id="classifyReceivedTotal" value="0">
				<div class="p-2 bg-white border rounded mb-3 small d-flex justify-content-between">
					<span><strong id="classifyRmaTitle">RMA</strong> &middot; Condición: <strong id="classifyGradeBadge">-</strong></span>
					<span>Total físico recibido: <strong id="classifyTotalLabel">0</strong> u.</span>
				</div>
				<div id="alertClassifyDamageBlock" class="alert alert-danger small font-weight-bold d-none mb-3">
					<i class="fa-solid fa-ban mr-1"></i>Mercancía dañada, vencida o perecedera devuelta: <strong>Aptos para reventa bloqueado en 0</strong>.
				</div>

				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Destino principal del caso:</label>
					<select class="form-control form-control-sm font-weight-bold" id="inputClassifyDest">
						<option value="reincorporacion" id="optClassifyReventa">Apto para reventa (Reingreso autorizado a stock)</option>
						<option value="merma_definitiva">Producto dañado / Merma definitiva</option>
						<option value="devolucion_proveedor">Devolución a proveedor (RTV)</option>
						<option value="reparacion">Reparación o reacondicionamiento</option>
						<option value="recuperacion">Recuperación / Reproceso interno</option>
						<option value="donacion">Donación autorizada</option>
						<option value="destruccion">Destrucción controlada</option>
					</select>
				</div>

				<div class="card p-3 bg-white border mb-3">
					<strong class="small text-dark mb-2">Distribución exacta de unidades recibidas (debe sumar el total recibido):</strong>
					<div class="row">
						<div class="col-md-3 col-6 mb-2">
							<label class="small text-success font-weight-bold">Aptos Reventa</label>
							<input type="number" step="0.5" min="0" class="form-control form-control-sm classify-qty" id="inputQtyResalable" value="0">
						</div>
						<div class="col-md-3 col-6 mb-2">
							<label class="small text-danger font-weight-bold">Dañado / Baja</label>
							<input type="number" step="0.5" min="0" class="form-control form-control-sm classify-qty" id="inputQtyDamaged" value="0">
						</div>
						<div class="col-md-3 col-6 mb-2">
							<label class="small text-info font-weight-bold">Proveedor RTV</label>
							<input type="number" step="0.5" min="0" class="form-control form-control-sm classify-qty" id="inputQtyRtv" value="0">
						</div>
						<div class="col-md-3 col-6 mb-2">
							<label class="small text-primary font-weight-bold">Reparación</label>
							<input type="number" step="0.5" min="0" class="form-control form-control-sm classify-qty" id="inputQtyRepairable" value="0">
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 col-12 mb-2">
						<label class="font-weight-bold small text-dark">Proveedor (si RTV):</label>
						<select class="form-control form-control-sm" id="inputClassifySupplier">
							<option value="">Automático / Ninguno</option>
							@foreach($suppliers as $sup)
								<option value="{{ $sup['id'] }}">{{ $sup['name'] }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-6 col-12 mb-2">
						<label class="font-weight-bold small text-dark">Notas de reparación / taller (si aplica):</label>
						<input type="text" class="form-control form-control-sm" id="inputClassifyRepairNotes" placeholder="Reempaque técnico, cambio de sello, etc.">
					</div>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitClassifyReturn"><i class="fa-solid fa-check mr-1"></i>Guardar Clasificación</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 5: DISPOSICIÓN AUTORIZADA Y REINGRESO AL INVENTARIO (ETAPA 5)
     ==================================================================== -->
<div class="modal fade" id="modalDispositionReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-user-shield mr-2 text-success"></i>Etapa 5 · Disposición Autorizada</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="dispositionReturnId">
				<div class="p-3 bg-white border rounded mb-3 small">
					<strong id="dispositionRmaTitle" class="text-dark">RMA</strong> &middot; Destino: <strong id="dispositionDestLabel">-</strong><br>
					<span>Resumen: <strong id="dispositionBreakdownText">-</strong></span>
				</div>
				<div id="dispositionStockBox" class="p-3 bg-white border border-success rounded mb-3">
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" id="chkAuthorizeStockReentry" checked>
						<label class="form-check-label font-weight-bold small text-success" for="chkAuthorizeStockReentry">
							Autorizo como supervisor el reingreso al inventario vendible
						</label>
					</div>
					<label class="small font-weight-bold text-dark">Ubicación destino de reingreso:</label>
					<select class="form-control form-control-sm" id="inputDispositionLocation">
						@foreach($locations as $loc)
							<option value="{{ $loc['id'] }}">{{ $loc['name'] }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Notas de autorización y salida/reingreso:</label>
					<textarea class="form-control form-control-sm" id="inputDispositionNotes" rows="2" placeholder="Autorizado por supervisión para ejecución física..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitDispositionReturn"><i class="fa-solid fa-shield-check mr-1"></i>Autorizar y Ejecutar Disposición</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL 6: CONCILIACIÓN FINAL DE UNIDADES (ETAPA 6)
     ==================================================================== -->
<div class="modal fade" id="modalConciliateReturn" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-check-double mr-2 text-success"></i>Etapa 6 · Conciliación de Unidades</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="conciliateReturnId">
				<div class="p-3 bg-white border rounded mb-3 small">
					<strong id="conciliateRmaTitle" class="text-dark">RMA</strong>
					<div class="d-flex justify-content-between mt-2"><span>Solicitadas:</span><strong id="concReqQty">0</strong></div>
					<div class="d-flex justify-content-between"><span>Recibidas físicamente:</span><strong id="concRecQty">0</strong></div>
					<div class="d-flex justify-content-between"><span>Dispuestas (suma destinos):</span><strong id="concDispQty">0</strong></div>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Cantidad final conciliada:</label>
					<input type="number" step="0.5" min="0" class="form-control form-control-sm font-weight-bold" id="inputConciliateQty">
					<small class="text-muted">Debe coincidir exactamente con las unidades físicas recibidas.</small>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Notas de conciliación:</label>
					<textarea class="form-control form-control-sm" id="inputConciliateNotes" rows="2" placeholder="Conciliación cerrada sin diferencias pendientes..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSubmitConciliateReturn"><i class="fa-solid fa-lock mr-1"></i>Conciliar y Cerrar RMA</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: POLÍTICA DE CALIDAD Y LOGÍSTICA INVERSA
     ==================================================================== -->
<div class="modal fade" id="modalQualityPolicy" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-shield-halved mr-2 text-warning"></i>Política de Logística Inversa y Control de Reingreso</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-white" style="font-size: 0.86rem; line-height: 1.6;">
				<h6 class="font-weight-bold text-dark mb-1">1. Separación POS Comercial vs Logística Física</h6>
				<p class="text-muted mb-3">Los reembolsos comerciales, notas de crédito de caja y tickets se gestionan exclusivamente en el módulo POS. Este tablero controla únicamente la recepción física, inspección, clasificación por estado, autorización de reingreso y conciliación de unidades.</p>
				<h6 class="font-weight-bold text-dark mb-1">2. Bloqueo de Mercancía Dañada y Perecederos</h6>
				<p class="text-muted mb-3">Ningún producto dictaminado como dañado, vencido, defectuoso de fábrica o perecedero retornado puede clasificarse como apto para reventa ni reingresar al inventario vendible.</p>
				<h6 class="font-weight-bold text-dark mb-1">3. Autorización Obligatoria de Reingreso</h6>
				<p class="text-muted mb-0">Todo reingreso de mercancía íntegra requiere inspección física completada, asignación al 100% de unidades recibidas y aprobación explícita de supervisor con registro de auditoría en Kardex.</p>
			</div>
			<div class="modal-footer bg-light border-top py-2">
				<button type="button" class="ret-btn-primary" data-dismiss="modal">Entendido</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: CATÁLOGO DE DESTINOS DE CLASIFICACIÓN
     ==================================================================== -->
<div class="modal fade" id="modalCatalogDestinations" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-boxes-packing mr-2 text-success"></i>Catálogo de Destinos de Clasificación</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<div class="row">
					@foreach([
						['Apto para reventa (Reingreso)', 'text-success', 'Reingreso autorizado a stock vendible tras inspección íntegra y aprobación de supervisor.'],
						['Producto dañado / Merma', 'text-danger', 'Mercancía averiada o vencida con bloqueo estricto de reventa y baja controlada.'],
						['Devolución a proveedor (RTV)', 'text-info', 'Retorno al proveedor con seguimiento de crédito o reposición física.'],
						['Reparación / Reacondicionamiento', 'text-primary', 'Envío a taller o reempaque técnico antes de nueva evaluación.'],
						['Donación autorizada', 'text-dark', 'Entrega institucional documentada con acta para Banco de Alimentos.'],
						['Destrucción controlada', 'text-dark', 'Desnaturalización y baja física certificada.']
					] as $cItem)
					<div class="col-md-6 col-12 mb-3">
						<div class="card p-3 h-100 bg-white border">
							<strong class="{{ $cItem[1] }}">{{ $cItem[0] }}</strong>
							<p class="text-muted small mt-1 mb-0">{{ $cItem[2] }}</p>
						</div>
					</div>
					@endforeach
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="ret-btn-primary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: CONCILIACIÓN DINÁMICA DE UNIDADES Y COSTOS (DESDE BD REAL)
     ==================================================================== -->
<div class="modal fade" id="modalReconciliation" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-scale-balanced mr-2 text-success"></i>Conciliación de Unidades e Inventario</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-white" style="font-size: 0.84rem;">
				<div class="table-responsive">
					<table class="table table-sm table-bordered mb-0">
						<thead class="thead-light">
							<tr>
								<th>RMA</th>
								<th>Producto</th>
								<th class="text-right">Solic.</th>
								<th class="text-right">Recib.</th>
								<th class="text-right">Reventa</th>
								<th class="text-right">Dañado</th>
								<th class="text-right">RTV</th>
								<th class="text-right">Repar.</th>
								<th class="text-right">Concil.</th>
								<th class="text-right">Dif. Rec-Con</th>
								<th class="text-right">Costo Inv.</th>
								<th>Etapa</th>
							</tr>
						</thead>
						<tbody>
							@foreach($returns as $row)
								@php $diffRC = round(floatval($row['received_quantity']) - floatval($row['conciliated_quantity']), 2); @endphp
								<tr>
									<td class="font-weight-bold">{{ $row['rma_number'] ?: $row['return_code'] }}</td>
									<td>{{ $row['product_name'] }}</td>
									<td class="text-right">{{ $qtyFmt($row['quantity']) }}</td>
									<td class="text-right">{{ $qtyFmt($row['received_quantity']) }}</td>
									<td class="text-right text-success">{{ $qtyFmt($row['resalable_quantity']) }}</td>
									<td class="text-right text-danger">{{ $qtyFmt($row['damaged_quantity']) }}</td>
									<td class="text-right text-info">{{ $qtyFmt($row['rtv_quantity']) }}</td>
									<td class="text-right text-primary">{{ $qtyFmt($row['repairable_quantity']) }}</td>
									<td class="text-right font-weight-bold">{{ $qtyFmt($row['conciliated_quantity']) }}</td>
									<td class="text-right @if($diffRC != 0 && $row['status'] === 'conciliacion') text-danger font-weight-bold @endif">{{ $qtyFmt($diffRC) }}</td>
									<td class="text-right">{{ $money($row['total_cost']) }}</td>
									<td><span class="badge badge-light border">{{ $row['stage_meta']['name'] }}</span></td>
								</tr>
							@endforeach
						</tbody>
						<tfoot class="thead-light font-weight-bold">
							<tr>
								<td colspan="2" class="text-right">Totales:</td>
								<td class="text-right">{{ $qtyFmt($logisticsSummary['requested_units']) }}</td>
								<td class="text-right">{{ $qtyFmt($logisticsSummary['received_units']) }}</td>
								<td class="text-right text-success">{{ $qtyFmt($logisticsSummary['resalable_units']) }}</td>
								<td class="text-right text-danger">{{ $qtyFmt($logisticsSummary['damaged_units']) }}</td>
								<td class="text-right text-info">{{ $qtyFmt($logisticsSummary['rtv_units']) }}</td>
								<td class="text-right text-primary">{{ $qtyFmt($logisticsSummary['repairable_units']) }}</td>
								<td class="text-right">{{ $qtyFmt($logisticsSummary['conciliated_units']) }}</td>
								<td class="text-right">{{ $qtyFmt($logisticsSummary['unit_variance']) }}</td>
								<td class="text-right">{{ $money($financialSummary['total_cost']) }}</td>
								<td>{{ number_format(floatval($logisticsSummary['reconcile_pct']), 1) }}%</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light border-top py-2">
				<button type="button" class="ret-btn-primary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ====================================================================
     MODAL: REGISTRO FOTOGRÁFICO PERSISTENTE
     ==================================================================== -->
<div class="modal fade" id="modalRegisterEvidence" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-camera mr-2 text-success"></i>Registro Fotográfico de Evidencia</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4 bg-light">
				<input type="hidden" id="evidenceReturnId">
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Expediente RMA:</label>
					<input type="text" class="form-control form-control-sm font-weight-bold" id="evidenceCaseFolio" readonly>
				</div>
				<div class="form-group mb-3">
					<label class="font-weight-bold small text-dark">Captura o archivo de imagen:</label>
					<input type="file" class="form-control-file border p-2 bg-white rounded small" id="evidenceFileInput" accept="image/*" capture="environment">
					<div id="evidencePreviewBox" class="mt-2 d-none">
						<img id="evidencePreviewImg" src="" alt="Vista previa" style="max-height: 140px; border-radius: 8px; border: 1px solid #cbd5e1;">
					</div>
				</div>
				<div class="form-group mb-0">
					<label class="font-weight-bold small text-dark">Observaciones visuales:</label>
					<textarea class="form-control form-control-sm" id="evidenceNotesInput" rows="2" placeholder="Sello de garantía, avería exterior, etiqueta de lote..."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
				<button type="button" class="ret-btn-primary" id="btnSaveEvidence"><i class="fa-solid fa-cloud-arrow-up mr-1"></i>Guardar Evidencia</button>
			</div>
		</div>
	</div>
</div>

@endsection

@push('pageScripts')
<script>
	window.RETURNS_DATA = {!! $returnsJson !!};
</script>
<script src="{{ $U('/viewjs/returns.js?v=', true) }}{{ daily_asset_version('/viewjs/returns.js') }}"></script>
@endpush
