@extends('layout.default')

@section('title', $__t('Punto de Venta Rápido (POS)'))

@push('pageStyles')
<link href="{{ $U('/css/daily_pos.css?v=', true) }}{{ $version }}" rel="stylesheet">
@endpush

@section('content')
@php
	$isShiftOpen = ($currentShift->status ?? 'open') === 'open';
	$shiftRegisterName = $currentShift->register_name ?? 'Caja 1';
	$shiftName = $currentShift->shift_name ?? 'Mañana';
	$shiftOpenedTime = $currentShift->opened_time_short ?? '08:00';
	$shiftSalesCount = intval($currentShift->live_sales_count ?? 0);
	$shiftTotalSales = floatval($currentShift->live_total_sales ?? 0);
	$heldCount = is_array($heldSales) ? count($heldSales) : 0;
@endphp

<div class="pos-page-wrapper">
	<!-- CABECERA SUPERIOR DEL POS -->
	<div class="pos-top-header">
		<div class="pos-title-group">
			<div class="pos-title-icon">
				<i class="fa-solid fa-cash-register"></i>
			</div>
			<div class="pos-title-text">
				<h1>Punto de Venta Rápido (POS)</h1>
				<p>Emisión de tickets de venta con deducción automática de inventario (FEFO) y cómputo de costos.</p>
			</div>
		</div>

		<div class="pos-header-controls">
			<!-- Selector de Sucursal -->
			<div class="pos-context-pill" title="Sucursal operativa activa">
				<span class="pill-icon"><i class="fa-solid fa-store"></i></span>
				<div class="pill-meta">
					<span class="pill-label">Sucursal</span>
					<select id="pos-branch-select" aria-label="Seleccionar sucursal">
						@foreach($branches as $b)
							@php
								$bid = intval(is_object($b) ? $b->id : $b['id']);
								$bname = is_object($b) ? $b->name : $b['name'];
							@endphp
							<option value="{{ $bid }}" {{ $bid === intval($selectedBranchId) ? 'selected' : '' }}>{{ $bname }}</option>
						@endforeach
					</select>
				</div>
			</div>

			<!-- Estado de la Caja -->
			<div class="pos-context-pill is-clickable {{ $isShiftOpen ? '' : 'is-closed' }}" id="btn-header-shift" title="Clic para administrar apertura, arqueo y cierre de caja">
				<span class="pill-dot" id="header-shift-dot"></span>
				<div class="pill-meta">
					<span class="pill-label">Caja</span>
					<span class="pill-value" id="header-shift-label">
						{{ $shiftRegisterName }} ({{ $isShiftOpen ? 'Abierta' : 'Cerrada' }})
					</span>
				</div>
				<i class="fa-solid fa-chevron-down text-muted" style="font-size:0.68rem;"></i>
			</div>

			<!-- Selector de Turno -->
			<div class="pos-context-pill" title="Turno operativo de caja">
				<span class="pill-icon" style="color:#f59e0b;"><i class="fa-solid fa-sun" id="header-shift-icon"></i></span>
				<div class="pill-meta">
					<span class="pill-label">Turno</span>
					<select id="pos-shift-select" aria-label="Turno operativo">
						<option value="Mañana" {{ $shiftName === 'Mañana' ? 'selected' : '' }}>Mañana</option>
						<option value="Tarde" {{ $shiftName === 'Tarde' ? 'selected' : '' }}>Tarde</option>
						<option value="Noche" {{ $shiftName === 'Noche' ? 'selected' : '' }}>Noche</option>
					</select>
				</div>
			</div>

			<!-- Historial de Ventas -->
			<button type="button" class="pos-btn-history" id="btn-open-history">
				<i class="fa-solid fa-list-check text-slate-600"></i>
				<span>Historial de Ventas</span>
			</button>
		</div>
	</div>

	<!-- AVISO SI LA CAJA ESTÁ CERRADA -->
	<div class="pos-closed-banner {{ $isShiftOpen ? 'd-none' : '' }}" id="pos-closed-banner">
		<div>
			<i class="fa-solid fa-lock mr-2"></i>
			<strong>Caja cerrada en esta sucursal:</strong> Las ventas están bloqueadas hasta que inicies un nuevo turno de caja con su monto de apertura.
		</div>
		<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btn-banner-open-shift">
			<i class="fa-solid fa-unlock-keyhole mr-1"></i> Abrir Caja Ahora
		</button>
	</div>

	<!-- ZONA PRINCIPAL: CATÁLOGO (IZQUIERDA) + TICKET DE VENTA (DERECHA) -->
	<div class="pos-workspace-grid">
		<!-- PANEL IZQUIERDO: BÚSQUEDA, CATEGORÍAS Y CATÁLOGO -->
		<div class="pos-catalog-panel">
			<!-- Buscador + Escáner tipo teclado -->
			<div class="pos-search-row">
				<div class="pos-search-input-wrap">
					<i class="fa-solid fa-magnifying-glass search-icon"></i>
					<input type="text"
						id="pos-search-input"
						class="pos-search-input"
						placeholder="Buscar producto por nombre, código de barras o escanear..."
						autocomplete="off">
					<button type="button" class="pos-search-clear" id="pos-search-clear" title="Limpiar búsqueda">
						<i class="fa-solid fa-xmark"></i>
					</button>
				</div>
				<button type="button" class="pos-barcode-btn is-active" id="btn-focus-scanner" title="Modo lector de código de barras (teclado): presiona Enter tras escanear o escribir SKU/EAN para agregar directo al ticket">
					<i class="fa-solid fa-barcode"></i>
				</button>
			</div>

			<!-- Píldoras de Categorías -->
			<div class="pos-categories-wrap" id="pos-categories-bar">
				<button type="button" class="pos-cat-pill active" data-category="ALL">Todos</button>
				@foreach($categories as $catName)
					<button type="button" class="pos-cat-pill" data-category="{{ $catName }}">{{ $catName }}</button>
				@endforeach
			</div>

			<!-- Cuadrícula de Productos -->
			<div class="pos-products-grid" id="pos-products-grid">
				@foreach($products as $product)
					@php
						$pid = intval($product->id);
						$branchAvail = isset($product->branch_stocks[$selectedBranchId])
							? floatval($product->branch_stocks[$selectedBranchId])
							: 0.0;
						$branchBbd = $product->branch_bbds[$selectedBranchId] ?? null;
						$branchLotsCount = intval($product->branch_lots[$selectedBranchId] ?? 0);
						$hasFefoBranch = !empty($branchBbd) && $branchLotsCount > 0 && $branchAvail > 0;
						$minStock = floatval($product->min_stock_amount ?? 0);
						$isLowStock = $branchAvail > 0 && ($branchAvail <= max(5.0, $minStock));
						$isOut = $branchAvail <= 0.0001;
						$listPrice = floatval($product->default_sale_price);
						$markdownPct = floatval($product->markdown_pct ?? 0);
						$effectivePrice = floatval($product->effective_price ?? $listPrice);
						$skuDisplay = !empty($product->sku) ? $product->sku : ('SKU-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT));
						$unitShort = mb_strtolower((string)($product->qu_name ?? 'unidad'), 'UTF-8');
						$searchBlob = mb_strtolower(
							$product->name . ' ' . $skuDisplay . ' ' . ($product->category_name ?? '') . ' ' . ($product->barcodes_str ?? ''),
							'UTF-8'
						);
					@endphp
					<div class="pos-product-card {{ $isOut ? 'is-out-of-stock' : '' }}"
						data-id="{{ $pid }}"
						data-name="{{ $product->name }}"
						data-sku="{{ $skuDisplay }}"
						data-barcodes="{{ $product->barcodes_str }}"
						data-category="{{ $product->category_name ?? 'General' }}"
						data-search="{{ $searchBlob }}"
						data-list-price="{{ number_format($listPrice, 2, '.', '') }}"
						data-price="{{ number_format($effectivePrice, 2, '.', '') }}"
						data-markdown-pct="{{ $markdownPct }}"
						data-unit="{{ $product->qu_name ?? 'Unidad' }}"
						data-min-stock="{{ $minStock }}"
						data-image="{{ $U($product->image_url) }}"
						data-branch-stocks='@json($product->branch_stocks)'
						data-branch-bbds='@json($product->branch_bbds)'
						data-branch-lots='@json($product->branch_lots)'
						data-stock="{{ $branchAvail }}"
						data-has-fefo="{{ $hasFefoBranch ? '1' : '0' }}"
						data-next-bbd="{{ $branchBbd ?? '' }}">

						<div class="pos-card-top">
							<div class="pos-card-thumb">
								<img src="{{ $U($product->image_url) }}" alt="{{ $product->name }}" loading="lazy">
							</div>
							<div class="pos-card-info">
								<h4 class="pos-card-title" title="{{ $product->name }}">{{ $product->name }}</h4>
								<div class="pos-card-sku">{{ $skuDisplay }}</div>
								<div class="pos-card-cat">{{ $product->category_name ?? 'General' }}</div>
								<div class="pos-card-badges">
									<span class="pos-badge-fefo {{ $hasFefoBranch ? '' : 'd-none' }}" title="Lote con control FEFO activo (Próx. venc: {{ $branchBbd ?? 'N/A' }})">
										<i class="fa-solid fa-bolt"></i>FEFO
									</span>
									@if($markdownPct > 0)
										<span class="pos-badge-promo" title="Rebaja automática FEFO autorizada">-{{ number_format($markdownPct, 0) }}%</span>
									@endif
									<span class="pos-badge-low {{ $isLowStock ? '' : 'd-none' }}">Bajo mín.</span>
								</div>
							</div>
						</div>

						<div class="pos-card-mid">
							<span class="pos-stock-pill {{ $isOut ? 'is-zero' : ($isLowStock ? 'is-low' : '') }}">
								<span class="stock-dot"></span>
								<span class="stock-text">
									@if($isOut)
										Agotado
									@else
										{{ number_format($branchAvail, ($branchAvail == intval($branchAvail) ? 0 : 1)) }} {{ $unitShort }}
									@endif
								</span>
							</span>

							<div class="pos-price-block">
								<div>
									<span class="pos-price-main">${{ number_format($effectivePrice, 2) }}</span>
									@if($markdownPct > 0 && $listPrice > $effectivePrice)
										<span class="pos-price-old">${{ number_format($listPrice, 2) }}</span>
									@endif
								</div>
								<span class="pos-price-unit">por {{ $unitShort }}</span>
							</div>
						</div>

						<button type="button" class="pos-btn-add" {{ $isOut ? 'disabled' : '' }}>
							@if($isOut)
								<i class="fa-solid fa-ban"></i> Sin disponibilidad
							@else
								<i class="fa-solid fa-cart-plus"></i> Agregar
							@endif
						</button>
					</div>
				@endforeach
			</div>

			<!-- Estado sin resultados de búsqueda/categoría -->
			<div id="pos-catalog-empty" class="text-center py-5 d-none">
				<div class="mb-2 text-muted" style="font-size:2.2rem;">
					<i class="fa-solid fa-box-open"></i>
				</div>
				<h6 class="font-weight-bold text-slate-800 mb-1">No se encontraron productos coincidentes</h6>
				<p class="text-muted small mb-3">Verifica el término buscado, código de barras o cambia la categoría seleccionada.</p>
				<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" id="btn-reset-catalog-filters">
					<i class="fa-solid fa-rotate-left mr-1"></i> Mostrar todo el catálogo
				</button>
			</div>
		</div>

		<!-- PANEL DERECHO: TICKET DE VENTA -->
		<div class="pos-ticket-panel">
			<!-- Cabecera Verde -->
			<div class="pos-ticket-header">
				<div class="pos-ticket-header-left">
					<i class="fa-solid fa-cart-shopping"></i>
					<span>Ticket de Venta</span>
				</div>
				<div class="pos-ticket-header-right">
					<button type="button" class="pos-btn-clear-ticket" id="btn-clear-cart" title="Vaciar ticket de venta">
						<i class="fa-solid fa-trash-can"></i>
					</button>
					<span class="pos-ticket-count-pill" id="pos-item-count-badge">0 ítems</span>
				</div>
			</div>

			<!-- Selector y Buscador de Cliente -->
			<div class="pos-customer-section">
				<div class="pos-customer-label">
					<span>Cliente (opcional)</span>
					<span class="text-muted" id="pos-selected-customer-tag" style="font-weight:600; font-size:0.69rem;">Público General / Mostrador</span>
				</div>
				<div class="pos-customer-input-group">
					<i class="fa-solid fa-magnifying-glass cust-icon"></i>
					<select id="pos-customer-select" class="pos-customer-select" aria-label="Seleccionar cliente">
						@foreach($customers as $c)
							<option value="{{ $c->id }}" data-taxid="{{ $c->tax_id ?? '' }}" data-name="{{ $c->name }}">
								{{ $c->name }}{{ !empty($c->tax_id) ? ' (' . $c->tax_id . ')' : '' }}
							</option>
						@endforeach
					</select>
					<button type="button" class="pos-customer-reset" id="btn-reset-customer" title="Restablecer a Cliente General / Mostrador">
						<i class="fa-solid fa-xmark"></i>
					</button>
				</div>
			</div>

			<!-- Cabecera de Columnas del Ticket -->
			<div class="pos-ticket-table-head">
				<div>Producto</div>
				<div class="text-right">Precio</div>
				<div class="text-center">Cant.</div>
				<div class="text-right">Subtotal</div>
				<div></div>
			</div>

			<!-- Lista de Ítems en el Carrito -->
			<div class="pos-ticket-items-list" id="pos-cart-items">
				<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg">
					<i class="fa-solid fa-basket-shopping fa-2x mb-2 d-block text-slate-300" style="opacity:0.45;"></i>
					<div class="font-weight-bold text-slate-700" style="font-size:0.82rem;">El ticket está vacío</div>
					<div class="small mt-1">Haz clic en <strong>Agregar</strong> sobre cualquier producto o escanea un código de barras para iniciar la venta.</div>
				</div>
			</div>

			<!-- Nota Opcional de la Venta -->
			<div class="pos-note-wrap">
				<div class="pos-note-input-box">
					<i class="fa-regular fa-file-lines"></i>
					<input type="text" id="pos-sale-notes" placeholder="Agregar nota a la venta (opcional)..." maxlength="220">
				</div>
			</div>

			<!-- Desglose de Importes: Subtotal, Descuento, Impuesto y Total -->
			<div class="pos-totals-section">
				<div class="pos-total-row">
					<span id="pos-subtotal-label">Subtotal (0 ítems)</span>
					<strong id="pos-subtotal-display">$0.00</strong>
				</div>

				<div class="pos-total-row">
					<div class="pos-discount-inline">
						<span>Descuento</span>
						<span class="disc-badge" id="pos-disc-symbol">$</span>
						<input type="number" id="pos-discount-input" value="0.00" min="0" step="0.01" aria-label="Valor de descuento">
						<select id="pos-discount-type" aria-label="Tipo de descuento">
							<option value="pct">%</option>
							<option value="amt">$</option>
						</select>
						<span class="badge badge-light border text-muted d-none" id="pos-discount-reason-pill" style="font-size:0.65rem;"></span>
					</div>
					<strong id="pos-discount-display" class="text-danger">$0.00</strong>
				</div>

				<div class="pos-total-row">
					<div>
						<span>IVA / Impuesto</span>
						<select id="pos-tax-rate" class="pos-tax-select" title="Regla de impuesto aplicable al ticket">
							<option value="15" selected>(15%)</option>
							<option value="0">(0% Exento / Sin regla)</option>
							<option value="8">(8% Reducido)</option>
							<option value="16">(16% General)</option>
						</select>
					</div>
					<strong id="pos-tax-display">$0.00</strong>
				</div>

				<div class="pos-grand-total-row">
					<span class="pos-grand-total-label">TOTAL A COBRAR:</span>
					<span class="pos-grand-total-value" id="pos-total-display">$0.00</span>
				</div>
			</div>

			<!-- Selección de Medio de Pago y Acciones -->
			<div class="pos-payment-section">
				<div class="pos-payment-tabs" id="pos-payment-tabs">
					<button type="button" class="pos-pay-tab active" data-method="cash">
						<i class="fa-solid fa-money-bill-wave"></i> Efectivo
					</button>
					<button type="button" class="pos-pay-tab" data-method="card">
						<i class="fa-solid fa-credit-card"></i> Tarjeta
					</button>
					<button type="button" class="pos-pay-tab" data-method="transfer">
						<i class="fa-solid fa-building-columns"></i> Transferencia
					</button>
					<button type="button" class="pos-pay-tab" data-method="mixed">
						<i class="fa-solid fa-table-cells-large"></i> Cobro mixto
					</button>
				</div>

				<!-- Panel Efectivo: Efectivo recibido y Cambio -->
				<div class="pos-cash-tender-box" id="pos-panel-cash">
					<div class="pos-cash-grid">
						<div class="pos-cash-field">
							<label for="pos-cash-received">Efectivo recibido</label>
							<div class="pos-cash-input-wrap">
								<span class="cash-icon"><i class="fa-solid fa-dollar-sign"></i></span>
								<input type="number" id="pos-cash-received" value="0.00" min="0" step="0.01">
							</div>
						</div>
						<div class="pos-cash-field">
							<label>Cambio</label>
							<div class="pos-change-pill" id="pos-change-pill">
								<i class="fa-solid fa-coins"></i>
								<span id="pos-change-display">$0.00</span>
							</div>
						</div>
					</div>
					<div class="pos-quick-cash-chips">
						<button type="button" class="pos-cash-chip" data-quick-cash="exact">Exacto</button>
						<button type="button" class="pos-cash-chip" data-quick-cash="5">$5</button>
						<button type="button" class="pos-cash-chip" data-quick-cash="10">$10</button>
						<button type="button" class="pos-cash-chip" data-quick-cash="20">$20</button>
						<button type="button" class="pos-cash-chip" data-quick-cash="50">$50</button>
						<button type="button" class="pos-cash-chip" data-quick-cash="100">$100</button>
					</div>
				</div>

				<!-- Panel Tarjeta / Transferencia (Simulado sin hardware bancario externo) -->
				<div class="pos-cash-tender-box d-none" id="pos-panel-noncash" style="background:#f8fafc; border-color:#e2e8f0;">
					<div class="d-flex align-items-center justify-content-between mb-1">
						<label class="mb-0 font-weight-bold text-slate-700" style="font-size:0.72rem;" id="pos-noncash-label">Referencia de autorización (simulado)</label>
						<span class="badge badge-light border text-muted" style="font-size:0.64rem;">Sin conexión a caja física</span>
					</div>
					<input type="text" id="pos-payment-ref" class="form-control form-control-sm" placeholder="Ej. AUT-4829 / Últimos 4 dígitos (opcional)">
					<small class="text-muted d-block mt-1" style="font-size:0.67rem;">
						<i class="fa-solid fa-circle-info mr-1"></i> Registra el medio de pago en el ticket sin conexión a terminal bancaria física.
					</small>
				</div>

				<!-- Panel Cobro Mixto -->
				<div class="pos-cash-tender-box d-none" id="pos-panel-mixed" style="background:#f8fafc; border-color:#cbd5e1;">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<span class="font-weight-bold text-slate-800" style="font-size:0.73rem;">Desglose de Cobro Mixto</span>
						<span class="badge badge-warning font-weight-bold" id="pos-mixed-status-badge" style="font-size:0.7rem;">Saldo pendiente: $0.00</span>
					</div>
					<div class="form-row">
						<div class="col-4">
							<label class="small font-weight-bold mb-1" style="font-size:0.68rem;">Efectivo ($)</label>
							<input type="number" id="pos-mixed-cash" class="form-control form-control-sm font-weight-bold" value="0.00" min="0" step="0.01">
						</div>
						<div class="col-4">
							<label class="small font-weight-bold mb-1" style="font-size:0.68rem;">Tarjeta ($)</label>
							<input type="number" id="pos-mixed-card" class="form-control form-control-sm font-weight-bold" value="0.00" min="0" step="0.01">
						</div>
						<div class="col-4">
							<label class="small font-weight-bold mb-1" style="font-size:0.68rem;">Transferencia ($)</label>
							<input type="number" id="pos-mixed-transfer" class="form-control form-control-sm font-weight-bold" value="0.00" min="0" step="0.01">
						</div>
					</div>
				</div>

				<!-- Botones Principales de Venta -->
				<div class="pos-ticket-actions">
					<button type="button" class="pos-btn-hold" id="btn-hold-sale" title="Guardar ticket actual como venta en espera">
						<i class="fa-solid fa-file-invoice"></i> Guardar Venta
					</button>
					<button type="button" class="pos-btn-discount" id="btn-open-discount-modal" title="Configurar descuento por producto o al total con motivo">
						<i class="fa-solid fa-percent"></i> Descuento
					</button>
					<button type="button" class="pos-btn-checkout" id="btn-submit-sale" disabled>
						<i class="fa-solid fa-circle-check"></i>
						<span id="btn-submit-sale-label">Cobrar $0.00</span>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- BARRA INFERIOR DE OPERACIONES Y MÉTRICAS DE TURNO -->
	<div class="pos-bottom-bar">
		<div class="pos-bottom-actions">
			<button type="button" class="pos-bottom-btn" id="btn-open-held-modal">
				<i class="fa-solid fa-clock-rotate-left text-primary"></i>
				<span>Recuperar Venta</span>
				<span class="hold-badge {{ $heldCount > 0 ? '' : 'd-none' }}" id="pos-held-count-badge">{{ $heldCount }}</span>
			</button>

			<button type="button" class="pos-bottom-btn" id="btn-open-returns-modal">
				<i class="fa-solid fa-rotate-left text-warning"></i>
				<span>Devoluciones</span>
			</button>

			<button type="button" class="pos-bottom-btn" id="btn-bottom-shift">
				<i class="fa-solid {{ $isShiftOpen ? 'fa-lock text-danger' : 'fa-lock-open text-success' }}" id="bottom-shift-icon"></i>
				<span id="bottom-shift-btn-text">{{ $isShiftOpen ? 'Cerrar Caja' : 'Abrir Caja' }}</span>
			</button>
		</div>

		<div class="pos-shift-metrics">
			<span class="pos-shift-metric-item">
				<i class="fa-solid fa-briefcase text-slate-500"></i>
				<span id="bottom-shift-open-text">
					@if($isShiftOpen)
						Caja abierta desde <strong>{{ $shiftOpenedTime }}</strong>
					@else
						Caja actualmente <strong>Cerrada</strong>
					@endif
				</span>
			</span>
			<span class="pos-shift-divider">|</span>
			<span class="pos-shift-metric-item">
				<i class="fa-solid fa-receipt text-slate-500"></i>
				<span>Ventas del turno: <strong id="bottom-shift-sales-count">{{ $shiftSalesCount }}</strong></span>
			</span>
			<span class="pos-shift-divider">|</span>
			<span class="pos-shift-metric-item">
				<i class="fa-solid fa-sack-dollar text-success"></i>
				<span>Total del turno: <strong id="bottom-shift-total-sales">${{ number_format($shiftTotalSales, 2) }}</strong></span>
			</span>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 1: COMPROBANTE DE VENTA SIMULADO (RECIBO POS)
     ========================================================================= -->
<div class="modal fade" id="modal-pos-receipt" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius:14px; overflow:hidden;">
			<div class="modal-header bg-success text-white border-0 py-3">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-circle-check mr-2"></i>Venta Registrada Exitosamente
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" id="pos-receipt-printable">
				<div class="text-center border-bottom pb-3 mb-3">
					<div class="font-weight-bold text-uppercase text-success" style="letter-spacing:0.06em; font-size:0.78rem;">DAILY RETAIL & INVENTORY</div>
					<h5 class="font-weight-bold mb-1" id="rcpt-branch-name">—</h5>
					<div class="small text-muted" id="rcpt-sale-number">Ticket #VTA-0000</div>
					<div class="small text-muted" id="rcpt-datetime"></div>
				</div>

				<div class="d-flex justify-content-between small mb-2">
					<span class="text-muted">Cliente:</span>
					<strong id="rcpt-customer-name">Público General / Mostrador</strong>
				</div>
				<div class="d-flex justify-content-between small mb-3">
					<span class="text-muted">Medio de pago:</span>
					<strong id="rcpt-payment-method">Efectivo</strong>
				</div>

				<div class="table-responsive mb-3">
					<table class="table table-sm mb-0" style="font-size:0.8rem;">
						<thead class="bg-light">
							<tr>
								<th>Producto</th>
								<th class="text-center">Cant.</th>
								<th class="text-right">P. Unit</th>
								<th class="text-right">Subtotal</th>
							</tr>
						</thead>
						<tbody id="rcpt-items-tbody"></tbody>
					</table>
				</div>

				<div class="border-top pt-2" style="font-size:0.84rem;">
					<div class="d-flex justify-content-between mb-1">
						<span class="text-muted">Subtotal:</span>
						<strong id="rcpt-subtotal">$0.00</strong>
					</div>
					<div class="d-flex justify-content-between mb-1">
						<span class="text-muted">Descuento aplicado:</span>
						<strong class="text-danger" id="rcpt-discount">-$0.00</strong>
					</div>
					<div class="d-flex justify-content-between mb-1">
						<span class="text-muted">IVA / Impuesto:</span>
						<strong id="rcpt-tax">$0.00</strong>
					</div>
					<div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2" style="font-size:1.05rem;">
						<span class="font-weight-bold">TOTAL COBRADO:</span>
						<strong class="text-success" id="rcpt-total">$0.00</strong>
					</div>
					<div class="d-flex justify-content-between small text-muted mt-1" id="rcpt-cash-row">
						<span>Recibido / Cambio:</span>
						<span id="rcpt-cash-detail">$0.00 / Cambio $0.00</span>
					</div>
				</div>

				<div class="alert alert-light border small text-muted mt-3 mb-0 py-2">
					<i class="fa-solid fa-boxes-stacked text-success mr-1"></i>
					Inventario deducido automáticamente siguiendo orden <strong>FEFO</strong> y registrado en Kardex y Ventas. Comprobante interno: no es un documento fiscal.
				</div>
			</div>
			<div class="modal-footer bg-light border-0 d-flex justify-content-between">
				<a href="{{ $U('/returns') }}" class="btn btn-sm btn-outline-secondary" id="rcpt-link-returns">
					<i class="fa-solid fa-rotate-left mr-1"></i> Ir a Devoluciones
				</a>
				<div>
					<button type="button" class="btn btn-outline-dark font-weight-bold mr-2" id="btn-print-receipt">
						<i class="fa-solid fa-print mr-1"></i> Imprimir Comprobante
					</button>
					<button type="button" class="btn btn-success font-weight-bold" data-dismiss="modal">
						Nueva Venta
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 2: APLICAR DESCUENTO (POR PRODUCTO O AL TOTAL CON MOTIVO)
     ========================================================================= -->
<div class="modal fade" id="modal-pos-discount" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-percent text-success mr-2"></i>Aplicar Descuento Comercial
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label class="font-weight-bold small">Alcance del descuento</label>
					<select id="modal-disc-target" class="form-control">
						<option value="TICKET">Descuento general al total del ticket</option>
					</select>
				</div>

				<div class="form-row">
					<div class="form-group col-6">
						<label class="font-weight-bold small">Tipo</label>
						<select id="modal-disc-type" class="form-control">
							<option value="pct">Porcentaje (%)</option>
							<option value="amt">Importe fijo ($)</option>
						</select>
					</div>
					<div class="form-group col-6">
						<label class="font-weight-bold small">Valor</label>
						<input type="number" id="modal-disc-value" class="form-control font-weight-bold" value="10" min="0" step="0.5">
					</div>
				</div>

				<div class="form-group mb-2">
					<label class="font-weight-bold small">Accesos rápidos</label>
					<div class="d-flex flex-wrap" style="gap:6px;">
						<button type="button" class="btn btn-xs btn-outline-success modal-quick-disc" data-type="pct" data-val="5">5%</button>
						<button type="button" class="btn btn-xs btn-outline-success modal-quick-disc" data-type="pct" data-val="10">10%</button>
						<button type="button" class="btn btn-xs btn-outline-success modal-quick-disc" data-type="pct" data-val="15">15%</button>
						<button type="button" class="btn btn-xs btn-outline-success modal-quick-disc" data-type="pct" data-val="20">20%</button>
						<button type="button" class="btn btn-xs btn-outline-secondary modal-quick-disc" data-type="pct" data-val="0">Quitar (0%)</button>
					</div>
				</div>

				<div class="form-group mb-0">
					<label class="font-weight-bold small">Motivo del descuento</label>
					<select id="modal-disc-reason" class="form-control">
						<option value="Promoción comercial en tienda">Promoción comercial en tienda</option>
						<option value="Impulso FEFO / Próximo a vencer">Impulso FEFO / Próximo a vencer</option>
						<option value="Cliente frecuente / Convenio">Cliente frecuente / Convenio</option>
						<option value="Empaque con detalle estético">Empaque con detalle estético</option>
						<option value="Autorización de gerencia">Autorización de gerencia</option>
					</select>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-success font-weight-bold" id="btn-apply-modal-discount">
					<i class="fa-solid fa-check mr-1"></i> Aplicar al Ticket
				</button>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 3: RECUPERAR VENTA EN ESPERA (HELD SALES)
     ========================================================================= -->
<div class="modal fade" id="modal-held-sales" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-clock-rotate-left text-primary mr-2"></i>Ventas en Espera (Tickets Guardados)
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="table-responsive">
					<table class="table table-sm table-hover mb-0" style="font-size:0.83rem;">
						<thead class="bg-light">
							<tr>
								<th>Referencia / Etiqueta</th>
								<th>Cliente</th>
								<th class="text-center">Ítems</th>
								<th class="text-right">Total Est.</th>
								<th>Guardado</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody id="held-sales-tbody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 4: GESTIÓN DE CAJA Y TURNO (APERTURA, ARQUEO Y CIERRE)
     ========================================================================= -->
<div class="modal fade" id="modal-pos-shift" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-cash-register text-success mr-2"></i>Control de Caja y Turno Operativo
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<!-- Vista cuando la caja está ABIERTA (Formulario de Arqueo y Cierre) -->
				<div id="shift-modal-open-view" class="{{ $isShiftOpen ? '' : 'd-none' }}">
					<div class="d-flex align-items-center justify-content-between p-2 mb-3 rounded" style="background:#f0fdf4; border:1px solid #bbf7d0;">
						<div>
							<span class="badge badge-success mr-1">CAJA ABIERTA</span>
							<strong id="shift-modal-reg-title">{{ $shiftRegisterName }} · Turno {{ $shiftName }}</strong>
						</div>
						<small class="text-muted" id="shift-modal-opened-at">Desde {{ $shiftOpenedTime }}</small>
					</div>

					<h6 class="font-weight-bold text-slate-800 mb-2" style="font-size:0.82rem;">Resumen de Ventas del Turno por Medio de Pago</h6>
					<div class="row text-center mb-3">
						<div class="col-3">
							<div class="border rounded p-2 bg-light">
								<div class="small text-muted">Fondo Inicial</div>
								<strong id="shift-sum-opening">${{ number_format(floatval($currentShift->opening_amount ?? 0), 2) }}</strong>
							</div>
						</div>
						<div class="col-3">
							<div class="border rounded p-2 bg-light">
								<div class="small text-muted">Efectivo</div>
								<strong class="text-success" id="shift-sum-cash">${{ number_format(floatval($currentShift->live_total_cash ?? 0), 2) }}</strong>
							</div>
						</div>
						<div class="col-3">
							<div class="border rounded p-2 bg-light">
								<div class="small text-muted">Tarjeta</div>
								<strong class="text-primary" id="shift-sum-card">${{ number_format(floatval($currentShift->live_total_card ?? 0), 2) }}</strong>
							</div>
						</div>
						<div class="col-3">
							<div class="border rounded p-2 bg-light">
								<div class="small text-muted">Transferencia</div>
								<strong class="text-info" id="shift-sum-transfer">${{ number_format(floatval($currentShift->live_total_transfer ?? 0), 2) }}</strong>
							</div>
						</div>
					</div>

					<div class="p-3 rounded border mb-3" style="background:#f8fafc;">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<span class="font-weight-bold small">Efectivo esperado en caja (Fondo + Ventas Efectivo):</span>
							<strong class="text-slate-900" style="font-size:1rem;" id="shift-expected-cash">
								${{ number_format(floatval($currentShift->live_expected_cash ?? 0), 2) }}
							</strong>
						</div>
						<div class="form-row align-items-center">
							<div class="col-7">
								<label class="small font-weight-bold mb-1">Efectivo físico contado al cierre ($):</label>
								<input type="number" id="shift-counted-cash" class="form-control form-control-sm font-weight-bold" step="0.01" min="0"
									value="{{ number_format(floatval($currentShift->live_expected_cash ?? 0), 2, '.', '') }}">
							</div>
							<div class="col-5">
								<label class="small font-weight-bold mb-1">Diferencia de arqueo:</label>
								<div id="shift-diff-badge" class="badge badge-success d-block py-2" style="font-size:0.82rem;">$0.00 (Cuadre exacto)</div>
							</div>
						</div>
						<div class="form-group mt-2 mb-0">
							<label class="small font-weight-bold mb-1">Observaciones de cierre (opcional):</label>
							<input type="text" id="shift-close-notes" class="form-control form-control-sm" placeholder="Ej. Arqueo conforme sin novedades">
						</div>
					</div>

					<div class="d-flex justify-content-end">
						<button type="button" class="btn btn-danger font-weight-bold" id="btn-confirm-close-shift">
							<i class="fa-solid fa-lock mr-1"></i> Confirmar Cierre de Caja
						</button>
					</div>
				</div>

				<!-- Vista cuando la caja está CERRADA (Formulario de Apertura) -->
				<div id="shift-modal-closed-view" class="{{ $isShiftOpen ? 'd-none' : '' }}">
					<div class="alert alert-warning small">
						<i class="fa-solid fa-triangle-exclamation mr-1"></i>
						La caja se encuentra cerrada. Configura los datos del nuevo turno para habilitar el cobro de tickets.
					</div>
					<div class="form-row">
						<div class="form-group col-6">
							<label class="small font-weight-bold">Caja</label>
							<select id="shift-open-register" class="form-control form-control-sm">
								<option value="Caja 1">Caja 1 (Principal)</option>
								<option value="Caja 2">Caja 2 (Rápida)</option>
							</select>
						</div>
						<div class="form-group col-6">
							<label class="small font-weight-bold">Turno</label>
							<select id="shift-open-name" class="form-control form-control-sm">
								<option value="Mañana">Mañana</option>
								<option value="Tarde">Tarde</option>
								<option value="Noche">Noche</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="small font-weight-bold">Fondo inicial de efectivo en caja ($)</label>
						<input type="number" id="shift-open-amount" class="form-control font-weight-bold" value="200.00" min="0" step="10">
					</div>
					<div class="form-group">
						<label class="small font-weight-bold">Nota de apertura (opcional)</label>
						<input type="text" id="shift-open-notes" class="form-control form-control-sm" placeholder="Ej. Inicio de turno con fondo verificado">
					</div>
					<div class="d-flex justify-content-end">
						<button type="button" class="btn btn-success font-weight-bold" id="btn-confirm-open-shift">
							<i class="fa-solid fa-unlock-keyhole mr-1"></i> Abrir Caja e Iniciar Turno
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 5: HISTORIAL DE VENTAS Y DEVOLUCIONES DEL POS
     ========================================================================= -->
<div class="modal fade" id="modal-pos-history" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow" style="border-radius:14px;">
			<div class="modal-header bg-light border-bottom d-flex align-items-center justify-content-between">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-list-check text-success mr-2"></i>Historial de Tickets y Devoluciones en Caja
				</h5>
				<div>
					<a href="{{ $U('/sales?tab=detalle') }}" class="btn btn-xs btn-outline-primary font-weight-bold mr-2">
						<i class="fa-solid fa-chart-line mr-1"></i> Ir a Ventas y Rentabilidad
					</a>
					<a href="{{ $U('/returns') }}" class="btn btn-xs btn-outline-warning font-weight-bold mr-2">
						<i class="fa-solid fa-rotate-left mr-1"></i> Módulo de Devoluciones
					</a>
					<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
			</div>
			<div class="modal-body">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<input type="text" id="pos-history-search" class="form-control form-control-sm" style="max-width:320px;" placeholder="Filtrar por N° de ticket, cliente o método...">
					<span class="small text-muted">Al anular/devolver un ticket se reintegra su stock al lote original (FEFO).</span>
				</div>
				<div class="table-responsive" style="max-height:420px; overflow-y:auto;">
					<table class="table table-sm table-hover mb-0" style="font-size:0.81rem;">
						<thead class="bg-light">
							<tr>
								<th>Ticket</th>
								<th>Fecha / Hora</th>
								<th>Cliente</th>
								<th>Pago</th>
								<th class="text-center">Ítems</th>
								<th class="text-right">Total</th>
								<th>Estado</th>
								<th class="text-right">Acciones</th>
							</tr>
						</thead>
						<tbody id="pos-history-tbody"></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

@push('pageScripts')
<script>
$(document).ready(function() {
	// Estado inicial inyectado desde el servidor
	var currentBranchId = parseInt($('#pos-branch-select').val(), 10) || 1;
	var shiftState = @json($currentShift);
	var heldSalesList = @json($heldSales);
	var recentSalesList = @json($recentSales);

	// Estado del carrito activo
	var cart = [];
	var selectedCategory = 'ALL';
	var paymentMethod = 'cash';
	var discountReason = '';
	var cashManuallyEdited = false;

	function fmtMoney(val) {
		var n = parseFloat(val) || 0;
		return '$' + n.toFixed(2);
	}

	function notifyToast(type, msg) {
		if (typeof toastr !== 'undefined') {
			toastr[type](msg);
		}
	}

	// =========================================================================
	// 1. SINCRONIZACIÓN DE SUCURSAL, TURNO Y DISPONIBILIDAD POR SUCURSAL
	// =========================================================================
	function refreshBranchCatalogStock() {
		$('.pos-product-card').each(function() {
			var $card = $(this);
			var stocks = $card.data('branch-stocks') || {};
			var bbds = $card.data('branch-bbds') || {};
			var lots = $card.data('branch-lots') || {};
			var minStock = parseFloat($card.data('min-stock')) || 0;
			var unitName = String($card.data('unit') || 'unidad').toLowerCase();

			var avail = parseFloat(stocks[currentBranchId] || 0);
			var bbd = bbds[currentBranchId] || '';
			var lotCount = parseInt(lots[currentBranchId] || 0, 10);
			var hasFefo = (bbd !== '' && lotCount > 0 && avail > 0);
			var isOut = avail <= 0.0001;
			var isLow = !isOut && avail <= Math.max(5, minStock);

			$card.attr('data-stock', avail).data('stock', avail);
			$card.attr('data-has-fefo', hasFefo ? '1' : '0').data('has-fefo', hasFefo ? 1 : 0);
			$card.attr('data-next-bbd', bbd).data('next-bbd', bbd);

			$card.toggleClass('is-out-of-stock', isOut);
			$card.find('.pos-badge-fefo').toggleClass('d-none', !hasFefo);
			$card.find('.pos-badge-low').toggleClass('d-none', !isLow);

			var $pill = $card.find('.pos-stock-pill');
			$pill.removeClass('is-zero is-low');
			if (isOut) {
				$pill.addClass('is-zero');
				$pill.find('.stock-text').text('Agotado');
				$card.find('.pos-btn-add').prop('disabled', true).html('<i class="fa-solid fa-ban"></i> Sin disponibilidad');
			} else {
				if (isLow) $pill.addClass('is-low');
				var fmtQty = (avail % 1 === 0) ? avail.toFixed(0) : avail.toFixed(1);
				$pill.find('.stock-text').text(fmtQty + ' ' + unitName);
				$card.find('.pos-btn-add').prop('disabled', false).html('<i class="fa-solid fa-cart-plus"></i> Agregar');
			}
		});
	}

	function renderShiftStateUI() {
		var isOpen = (shiftState && shiftState.status === 'open');
		var regName = (shiftState && shiftState.register_name) ? shiftState.register_name : 'Caja 1';
		var sName = (shiftState && shiftState.shift_name) ? shiftState.shift_name : 'Mañana';
		var openShort = (shiftState && shiftState.opened_time_short) ? shiftState.opened_time_short : '08:00';
		var sCount = (shiftState && shiftState.live_sales_count) ? parseInt(shiftState.live_sales_count, 10) : 0;
		var sTotal = (shiftState && shiftState.live_total_sales) ? parseFloat(shiftState.live_total_sales) : 0.0;

		$('#btn-header-shift').toggleClass('is-closed', !isOpen);
		$('#header-shift-label').text(regName + ' (' + (isOpen ? 'Abierta' : 'Cerrada') + ')');
		$('#pos-shift-select').val(sName);
		$('#pos-closed-banner').toggleClass('d-none', isOpen);

		$('#bottom-shift-icon').attr('class', 'fa-solid ' + (isOpen ? 'fa-lock text-danger' : 'fa-lock-open text-success'));
		$('#bottom-shift-btn-text').text(isOpen ? 'Cerrar Caja' : 'Abrir Caja');
		$('#bottom-shift-open-text').html(isOpen
			? ('Caja abierta desde <strong>' + openShort + '</strong>')
			: 'Caja actualmente <strong>Cerrada</strong>');
		$('#bottom-shift-sales-count').text(sCount);
		$('#bottom-shift-total-sales').text(fmtMoney(sTotal));

		// Modal de turno
		$('#shift-modal-open-view').toggleClass('d-none', !isOpen);
		$('#shift-modal-closed-view').toggleClass('d-none', isOpen);
		if (isOpen && shiftState) {
			var openAmt = parseFloat(shiftState.opening_amount || 200);
			var cashAmt = parseFloat(shiftState.live_total_cash || 0);
			var cardAmt = parseFloat(shiftState.live_total_card || 0);
			var transAmt = parseFloat(shiftState.live_total_transfer || 0);
			var expCash = parseFloat(shiftState.live_expected_cash || (openAmt + cashAmt));

			$('#shift-modal-reg-title').text(regName + ' · Turno ' + sName);
			$('#shift-modal-opened-at').text('Desde ' + openShort);
			$('#shift-sum-opening').text(fmtMoney(openAmt));
			$('#shift-sum-cash').text(fmtMoney(cashAmt));
			$('#shift-sum-card').text(fmtMoney(cardAmt));
			$('#shift-sum-transfer').text(fmtMoney(transAmt));
			$('#shift-expected-cash').text(fmtMoney(expCash)).data('expected', expCash);
			$('#shift-counted-cash').val(expCash.toFixed(2));
			updateShiftDiffBadge();
		}

		updateCartTotals();
	}

	function fetchPosStateForBranch(branchId) {
		$.get(U('/api/pos/state'), { branch_id: branchId }, function(res) {
			if (res && res.shift) {
				shiftState = res.shift;
				heldSalesList = res.held_sales || [];
				recentSalesList = res.recent_sales || [];
				updateHeldBadge();
				renderShiftStateUI();
			}
		});
	}

	$('#pos-branch-select').on('change', function() {
		var newBranchId = parseInt($(this).val(), 10) || 1;
		if (cart.length > 0) {
			if (!confirm('Cambiar de sucursal vaciará el ticket actual para validar existencias de la nueva sucursal. ¿Deseas continuar?')) {
				$(this).val(currentBranchId);
				return;
			}
			cart = [];
			cashManuallyEdited = false;
		}
		currentBranchId = newBranchId;
		refreshBranchCatalogStock();
		renderCart();
		fetchPosStateForBranch(currentBranchId);
	});

	$('#pos-shift-select').on('change', function() {
		var sVal = $(this).val();
		var iconClass = sVal === 'Noche' ? 'fa-moon' : (sVal === 'Tarde' ? 'fa-cloud-sun' : 'fa-sun');
		$('#header-shift-icon').attr('class', 'fa-solid ' + iconClass);
		if (shiftState) {
			shiftState.shift_name = sVal;
		}
	});

	// =========================================================================
	// 2. BÚSQUEDA, CATEGORÍAS Y ESCÁNER DE CÓDIGO DE BARRAS (MODO TECLADO)
	// =========================================================================
	function filterCatalog() {
		var query = $.trim($('#pos-search-input').val()).toLowerCase();
		$('#pos-search-clear').toggle(query.length > 0);

		var visibleCount = 0;
		$('.pos-product-card').each(function() {
			var $card = $(this);
			var cat = String($card.data('category') || 'General');
			var searchBlob = String($card.data('search') || '');

			var matchesCat = (selectedCategory === 'ALL' || cat === selectedCategory);
			var matchesQuery = (query === '' || searchBlob.indexOf(query) !== -1);

			if (matchesCat && matchesQuery) {
				$card.removeClass('d-none');
				visibleCount++;
			} else {
				$card.addClass('d-none');
			}
		});

		$('#pos-catalog-empty').toggleClass('d-none', visibleCount > 0);
	}

	$('#pos-search-input').on('input', function() {
		filterCatalog();
	});

	// Soporte para lector de códigos de barras que actúa como teclado (Enter)
	$('#pos-search-input').on('keydown', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			var code = $.trim($(this).val()).toLowerCase();
			if (!code) return;

			var $exactCard = null;
			$('.pos-product-card').each(function() {
				var $c = $(this);
				var sku = String($c.data('sku') || '').toLowerCase();
				var barcodes = String($c.data('barcodes') || '').toLowerCase().split(/\s+/);
				if (sku === code || barcodes.indexOf(code) !== -1) {
					$exactCard = $c;
					return false;
				}
			});

			if (!$exactCard) {
				var $visible = $('.pos-product-card:not(.d-none):not(.is-out-of-stock)');
				if ($visible.length === 1) {
					$exactCard = $visible.first();
				}
			}

			if ($exactCard && $exactCard.length) {
				addProductCardToCart($exactCard);
				$(this).val('');
				filterCatalog();
			} else {
				notifyToast('warning', 'Código o producto sin coincidencia directa: ' + code);
			}
		}
	});

	$('#pos-search-clear').on('click', function() {
		$('#pos-search-input').val('').focus();
		filterCatalog();
	});

	$('#btn-focus-scanner').on('click', function() {
		$('#pos-search-input').focus().select();
		notifyToast('info', 'Buscador listo para recibir lectura de código de barras o SKU.');
	});

	$('#pos-categories-bar').on('click', '.pos-cat-pill', function() {
		$('#pos-categories-bar .pos-cat-pill').removeClass('active');
		$(this).addClass('active');
		selectedCategory = $(this).data('category');
		filterCatalog();
	});

	$('#btn-reset-catalog-filters').on('click', function() {
		selectedCategory = 'ALL';
		$('#pos-categories-bar .pos-cat-pill').removeClass('active');
		$('#pos-categories-bar .pos-cat-pill[data-category="ALL"]').addClass('active');
		$('#pos-search-input').val('');
		filterCatalog();
	});

	// =========================================================================
	// 3. CARRITO / TICKET DE VENTA
	// =========================================================================
	function addProductCardToCart($card) {
		var id = parseInt($card.data('id'), 10);
		var name = String($card.data('name'));
		var sku = String($card.data('sku'));
		var price = parseFloat($card.data('price')) || 0;
		var listPrice = parseFloat($card.data('list-price')) || price;
		var maxStock = parseFloat($card.data('stock')) || 0;
		var unit = String($card.data('unit') || 'Unidad');
		var image = String($card.data('image') || '');
		var hasFefo = parseInt($card.data('has-fefo'), 10) === 1;

		if (maxStock <= 0) {
			notifyToast('error', 'El producto "' + name + '" no tiene unidades disponibles en esta sucursal.');
			return;
		}

		var existing = cart.find(function(it) { return it.product_id === id; });
		if (existing) {
			if (existing.amount + 1 > maxStock) {
				notifyToast('warning', 'Stock máximo disponible alcanzado para "' + name + '" (' + maxStock + ' ' + unit + ').');
				return;
			}
			existing.amount += 1;
		} else {
			cart.push({
				product_id: id,
				name: name,
				sku: sku,
				list_price: listPrice,
				unit_price: price,
				amount: 1,
				max_stock: maxStock,
				unit: unit,
				image: image,
				has_fefo: hasFefo,
				item_discount_note: ''
			});
		}

		renderCart();
	}

	$(document).on('click', '.pos-product-card .pos-btn-add', function(e) {
		e.stopPropagation();
		var $card = $(this).closest('.pos-product-card');
		addProductCardToCart($card);
	});

	function renderCart() {
		var $container = $('#pos-cart-items');
		$container.empty();

		if (cart.length === 0) {
			$container.html(
				'<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg">' +
					'<i class="fa-solid fa-basket-shopping fa-2x mb-2 d-block text-slate-300" style="opacity:0.45;"></i>' +
					'<div class="font-weight-bold text-slate-700" style="font-size:0.82rem;">El ticket está vacío</div>' +
					'<div class="small mt-1">Haz clic en <strong>Agregar</strong> sobre cualquier producto o escanea un código de barras para iniciar la venta.</div>' +
				'</div>'
			);
			updateCartTotals();
			return;
		}

		cart.forEach(function(item, idx) {
			var lineSub = item.amount * item.unit_price;
			var fefoHtml = item.has_fefo
				? '<span class="pos-badge-fefo"><i class="fa-solid fa-bolt"></i>FEFO</span>'
				: '';
			var discBadgeHtml = (item.unit_price < item.list_price - 0.005)
				? '<span class="pos-badge-promo">Desc.</span>'
				: '';

			var rowHtml =
				'<div class="pos-ticket-item-row" data-idx="' + idx + '">' +
					'<div class="pos-ticket-item-prod">' +
						'<img src="' + item.image + '" class="pos-ticket-item-thumb" alt="">' +
						'<div class="pos-ticket-item-meta">' +
							'<div class="pos-ticket-item-name" title="' + item.name + '">' + item.name + '</div>' +
							'<div class="pos-ticket-item-sub">' +
								'<span>' + item.sku + '</span> ' + fefoHtml + ' ' + discBadgeHtml +
							'</div>' +
						'</div>' +
					'</div>' +
					'<div class="text-right font-weight-bold">' + fmtMoney(item.unit_price) + '</div>' +
					'<div class="text-center">' +
						'<div class="pos-qty-stepper">' +
							'<button type="button" class="pos-qty-btn btn-qty-minus" data-idx="' + idx + '">&minus;</button>' +
							'<input type="number" class="pos-qty-input input-qty-row" data-idx="' + idx + '" value="' + item.amount + '" min="0.1" max="' + item.max_stock + '" step="1">' +
							'<button type="button" class="pos-qty-btn btn-qty-plus" data-idx="' + idx + '">+</button>' +
						'</div>' +
					'</div>' +
					'<div class="text-right font-weight-bold">' + fmtMoney(lineSub) + '</div>' +
					'<div class="text-right">' +
						'<button type="button" class="pos-item-remove-btn btn-remove-row" data-idx="' + idx + '" title="Quitar del ticket">' +
							'<i class="fa-solid fa-trash-can"></i>' +
						'</button>' +
					'</div>' +
				'</div>';
			$container.append(rowHtml);
		});

		updateCartTotals();
	}

	$(document).on('click', '.btn-qty-minus', function() {
		var idx = parseInt($(this).data('idx'), 10);
		if (!cart[idx]) return;
		if (cart[idx].amount > 1) {
			cart[idx].amount = Math.round((cart[idx].amount - 1) * 100) / 100;
		} else {
			cart.splice(idx, 1);
		}
		renderCart();
	});

	$(document).on('click', '.btn-qty-plus', function() {
		var idx = parseInt($(this).data('idx'), 10);
		if (!cart[idx]) return;
		if (cart[idx].amount + 1 <= cart[idx].max_stock) {
			cart[idx].amount = Math.round((cart[idx].amount + 1) * 100) / 100;
			renderCart();
		} else {
			notifyToast('warning', 'No puedes superar el stock disponible (' + cart[idx].max_stock + ').');
		}
	});

	$(document).on('change', '.input-qty-row', function() {
		var idx = parseInt($(this).data('idx'), 10);
		if (!cart[idx]) return;
		var val = parseFloat($(this).val());
		if (isNaN(val) || val <= 0) {
			cart.splice(idx, 1);
		} else if (val > cart[idx].max_stock) {
			notifyToast('warning', 'Cantidad ajustada al máximo disponible (' + cart[idx].max_stock + ').');
			cart[idx].amount = cart[idx].max_stock;
		} else {
			cart[idx].amount = Math.round(val * 100) / 100;
		}
		renderCart();
	});

	$(document).on('click', '.btn-remove-row', function() {
		var idx = parseInt($(this).data('idx'), 10);
		cart.splice(idx, 1);
		renderCart();
	});

	$('#btn-clear-cart').on('click', function() {
		if (cart.length === 0) return;
		if (confirm('¿Estás seguro de que deseas vaciar todos los productos del ticket actual?')) {
			cart = [];
			discountReason = '';
			$('#pos-discount-input').val('0.00');
			$('#pos-discount-reason-pill').addClass('d-none').text('');
			$('#pos-sale-notes').val('');
			cashManuallyEdited = false;
			renderCart();
		}
	});

	// Cliente
	$('#pos-customer-select').on('change', function() {
		var name = $(this).find('option:selected').data('name') || 'Público General / Mostrador';
		$('#pos-selected-customer-tag').text(name);
	});

	$('#btn-reset-customer').on('click', function() {
		$('#pos-customer-select').val('1').trigger('change');
	});

	// =========================================================================
	// 4. CÁLCULO DE TOTALES, DESCUENTOS, IMPUESTOS Y CAMBIO / COBRO MIXTO
	// =========================================================================
	function getTicketCalculation() {
		var itemUnitsCount = 0;
		var subtotal = 0.0;
		var itemLevelDiscounts = 0.0;

		cart.forEach(function(item) {
			itemUnitsCount += Math.ceil(item.amount);
			subtotal += (item.amount * item.unit_price);
			if (item.list_price > item.unit_price) {
				itemLevelDiscounts += (item.list_price - item.unit_price) * item.amount;
			}
		});
		subtotal = Math.round(subtotal * 100) / 100;

		var discType = $('#pos-discount-type').val() || 'pct';
		var discRaw = Math.max(0, parseFloat($('#pos-discount-input').val()) || 0);
		var ticketDiscount = 0.0;
		if (discType === 'pct') {
			var pct = Math.min(100, discRaw);
			ticketDiscount = Math.round((subtotal * (pct / 100.0)) * 100) / 100;
		} else {
			ticketDiscount = Math.min(subtotal, Math.round(discRaw * 100) / 100);
		}

		var netAfterDiscount = Math.max(0, Math.round((subtotal - ticketDiscount) * 100) / 100);
		var taxRate = Math.max(0, parseFloat($('#pos-tax-rate').val()) || 0);
		var taxAmount = Math.round((netAfterDiscount * (taxRate / 100.0)) * 100) / 100;
		var grandTotal = Math.round((netAfterDiscount + taxAmount) * 100) / 100;

		return {
			itemUnitsCount: itemUnitsCount,
			subtotal: subtotal,
			itemLevelDiscounts: Math.round(itemLevelDiscounts * 100) / 100,
			ticketDiscount: ticketDiscount,
			totalDiscountDisplay: Math.round((ticketDiscount + itemLevelDiscounts) * 100) / 100,
			netAfterDiscount: netAfterDiscount,
			taxRate: taxRate,
			taxAmount: taxAmount,
			grandTotal: grandTotal
		};
	}

	function updateCartTotals() {
		var calc = getTicketCalculation();
		var itemsWord = calc.itemUnitsCount === 1 ? '1 ítem' : (calc.itemUnitsCount + ' ítems');

		$('#pos-item-count-badge').text(itemsWord);
		$('#pos-subtotal-label').text('Subtotal (' + itemsWord + ')');
		$('#pos-subtotal-display').text(fmtMoney(calc.subtotal));
		$('#pos-discount-display').text(calc.ticketDiscount > 0 ? ('-' + fmtMoney(calc.ticketDiscount)) : '$0.00');
		$('#pos-tax-display').text(fmtMoney(calc.taxAmount));
		$('#pos-total-display').text(fmtMoney(calc.grandTotal));
		$('#btn-submit-sale-label').text('Cobrar ' + fmtMoney(calc.grandTotal));

		// Si el usuario no ha editado manualmente el efectivo recibido, sugerir billete redondeado o monto exacto
		if (!cashManuallyEdited) {
			if (calc.grandTotal <= 0) {
				$('#pos-cash-received').val('0.00');
			} else {
				var suggested = calc.grandTotal <= 10 ? Math.ceil(calc.grandTotal) : Math.ceil(calc.grandTotal / 5) * 5;
				if (suggested < calc.grandTotal) suggested = calc.grandTotal;
				$('#pos-cash-received').val(suggested.toFixed(2));
			}
		}

		updatePaymentCoverageUI(calc);
	}

	function updatePaymentCoverageUI(calc) {
		calc = calc || getTicketCalculation();
		var isShiftOpen = (shiftState && shiftState.status === 'open');
		var hasItems = cart.length > 0 && calc.grandTotal >= 0;
		var paymentValid = true;

		if (paymentMethod === 'cash') {
			var received = parseFloat($('#pos-cash-received').val()) || 0;
			var diff = Math.round((received - calc.grandTotal) * 100) / 100;
			var $pill = $('#pos-change-pill');
			if (calc.grandTotal > 0 && diff < -0.005) {
				$pill.addClass('is-insufficient');
				$('#pos-change-display').text('Faltan ' + fmtMoney(Math.abs(diff)));
				paymentValid = false;
			} else {
				$pill.removeClass('is-insufficient');
				$('#pos-change-display').text(fmtMoney(Math.max(0, diff)));
			}
		} else if (paymentMethod === 'mixed') {
			var mCash = Math.max(0, parseFloat($('#pos-mixed-cash').val()) || 0);
			var mCard = Math.max(0, parseFloat($('#pos-mixed-card').val()) || 0);
			var mTrans = Math.max(0, parseFloat($('#pos-mixed-transfer').val()) || 0);
			var sumMixed = Math.round((mCash + mCard + mTrans) * 100) / 100;
			var pending = Math.round((calc.grandTotal - sumMixed) * 100) / 100;
			var $mBadge = $('#pos-mixed-status-badge');

			if (pending > 0.005) {
				$mBadge.attr('class', 'badge badge-danger font-weight-bold').text('Saldo pendiente: ' + fmtMoney(pending));
				paymentValid = false;
			} else if (pending < -0.005) {
				$mBadge.attr('class', 'badge badge-success font-weight-bold').text('Cubierto · Cambio efectivo: ' + fmtMoney(Math.abs(pending)));
			} else {
				$mBadge.attr('class', 'badge badge-success font-weight-bold').text('Total cubierto exactamente ($0.00)');
			}
		}

		$('#btn-submit-sale').prop('disabled', !(isShiftOpen && hasItems && paymentValid));
	}

	$('#pos-discount-input, #pos-discount-type, #pos-tax-rate').on('input change', function() {
		updateCartTotals();
	});

	$('#pos-cash-received').on('input', function() {
		cashManuallyEdited = true;
		updatePaymentCoverageUI();
	});

	$('.pos-cash-chip').on('click', function() {
		var mode = $(this).data('quick-cash');
		var calc = getTicketCalculation();
		cashManuallyEdited = true;
		if (mode === 'exact') {
			$('#pos-cash-received').val(calc.grandTotal.toFixed(2));
		} else {
			$('#pos-cash-received').val(parseFloat(mode).toFixed(2));
		}
		updatePaymentCoverageUI(calc);
	});

	$('#pos-mixed-cash, #pos-mixed-card, #pos-mixed-transfer').on('input', function() {
		updatePaymentCoverageUI();
	});

	// Cambio de pestaña de medio de pago
	$('#pos-payment-tabs').on('click', '.pos-pay-tab', function() {
		$('#pos-payment-tabs .pos-pay-tab').removeClass('active');
		$(this).addClass('active');
		paymentMethod = $(this).data('method');

		$('#pos-panel-cash').toggleClass('d-none', paymentMethod !== 'cash');
		$('#pos-panel-noncash').toggleClass('d-none', !(paymentMethod === 'card' || paymentMethod === 'transfer'));
		$('#pos-panel-mixed').toggleClass('d-none', paymentMethod !== 'mixed');

		if (paymentMethod === 'card') {
			$('#pos-noncash-label').text('Referencia de Tarjeta / Voucher (simulado)');
		} else if (paymentMethod === 'transfer') {
			$('#pos-noncash-label').text('N° de Operación / Transferencia (simulado)');
		} else if (paymentMethod === 'mixed') {
			var calc = getTicketCalculation();
			var half = Math.round((calc.grandTotal / 2) * 100) / 100;
			$('#pos-mixed-cash').val(half.toFixed(2));
			$('#pos-mixed-card').val(Math.max(0, calc.grandTotal - half).toFixed(2));
			$('#pos-mixed-transfer').val('0.00');
		}
		updatePaymentCoverageUI();
	});

	// =========================================================================
	// 5. MODAL DE DESCUENTO (POR PRODUCTO O AL TOTAL CON MOTIVO)
	// =========================================================================
	$('#btn-open-discount-modal').on('click', function() {
		var $sel = $('#modal-disc-target');
		$sel.empty();
		$sel.append('<option value="TICKET">Descuento general al total del ticket</option>');
		cart.forEach(function(it, idx) {
			$sel.append('<option value="ITEM_' + idx + '">Producto: ' + it.name + ' (P. lista ' + fmtMoney(it.list_price) + ')</option>');
		});
		$('#modal-disc-type').val($('#pos-discount-type').val() || 'pct');
		$('#modal-disc-value').val($('#pos-discount-input').val() || '0');
		$('#modal-pos-discount').modal('show');
	});

	$('.modal-quick-disc').on('click', function() {
		$('#modal-disc-type').val($(this).data('type'));
		$('#modal-disc-value').val($(this).data('val'));
	});

	$('#btn-apply-modal-discount').on('click', function() {
		var target = $('#modal-disc-target').val();
		var dtype = $('#modal-disc-type').val();
		var dval = Math.max(0, parseFloat($('#modal-disc-value').val()) || 0);
		var reason = $('#modal-disc-reason').val() || '';

		if (target === 'TICKET') {
			$('#pos-discount-type').val(dtype);
			$('#pos-discount-input').val(dval.toFixed(2));
			discountReason = dval > 0 ? reason : '';
			if (discountReason) {
				$('#pos-discount-reason-pill').removeClass('d-none').text(discountReason);
			} else {
				$('#pos-discount-reason-pill').addClass('d-none').text('');
			}
		} else if (target && target.indexOf('ITEM_') === 0) {
			var idx = parseInt(target.replace('ITEM_', ''), 10);
			if (cart[idx]) {
				var base = cart[idx].list_price;
				var newPrice = base;
				if (dtype === 'pct') {
					newPrice = Math.max(0.01, Math.round(base * (1 - Math.min(95, dval) / 100) * 100) / 100);
				} else {
					newPrice = Math.max(0.01, Math.round((base - dval) * 100) / 100);
				}
				cart[idx].unit_price = newPrice;
				cart[idx].item_discount_note = reason;
			}
		}

		$('#modal-pos-discount').modal('hide');
		renderCart();
		notifyToast('success', 'Descuento actualizado en el ticket.');
	});

	// =========================================================================
	// 6. GUARDAR Y RECUPERAR VENTA EN ESPERA (HELD SALES)
	// =========================================================================
	function updateHeldBadge() {
		var count = heldSalesList ? heldSalesList.length : 0;
		$('#pos-held-count-badge').text(count).toggleClass('d-none', count === 0);
	}

	$('#btn-hold-sale').on('click', function() {
		if (cart.length === 0) {
			notifyToast('warning', 'Agrega al menos un producto al ticket antes de guardarlo en espera.');
			return;
		}
		var customerName = $('#pos-customer-select option:selected').data('name') || 'Público General / Mostrador';
		var defaultLabel = 'Venta en espera - ' + customerName + ' (' + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + ')';
		var label = prompt('Etiqueta o referencia para recuperar esta venta:', defaultLabel);
		if (label === null) return;

		var payload = {
			branch_id: currentBranchId,
			customer_id: parseInt($('#pos-customer-select').val(), 10) || 1,
			customer_name: customerName,
			label: label,
			notes: $('#pos-sale-notes').val(),
			discount_type: $('#pos-discount-type').val(),
			discount_value: parseFloat($('#pos-discount-input').val()) || 0,
			discount_reason: discountReason,
			tax_rate: parseFloat($('#pos-tax-rate').val()) || 15,
			items: cart
		};

		$.ajax({
			url: U('/api/pos/hold'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				heldSalesList = res.held_sales || [];
				updateHeldBadge();
				cart = [];
				discountReason = '';
				$('#pos-discount-input').val('0.00');
				$('#pos-discount-reason-pill').addClass('d-none').text('');
				$('#pos-sale-notes').val('');
				cashManuallyEdited = false;
				renderCart();
				notifyToast('info', 'Venta guardada en espera. Puedes recuperarla desde "Recuperar Venta".');
			},
			error: function(xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al guardar venta en espera';
				notifyToast('error', msg);
			}
		});
	});

	function renderHeldSalesModal() {
		var $tbody = $('#held-sales-tbody');
		$tbody.empty();
		if (!heldSalesList || heldSalesList.length === 0) {
			$tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No hay ventas en espera en esta sucursal.</td></tr>');
			return;
		}
		heldSalesList.forEach(function(h) {
			var tr =
				'<tr>' +
					'<td class="font-weight-bold">' + $('<div>').text(h.label).html() + '</td>' +
					'<td>' + $('<div>').text(h.customer_name).html() + '</td>' +
					'<td class="text-center"><span class="badge badge-light border">' + h.item_count + '</span></td>' +
					'<td class="text-right font-weight-bold text-success">' + fmtMoney(h.total_amount) + '</td>' +
					'<td class="small text-muted">' + h.created_at + '</td>' +
					'<td class="text-right">' +
						'<button type="button" class="btn btn-xs btn-success font-weight-bold mr-1 btn-recover-held" data-id="' + h.id + '">' +
							'<i class="fa-solid fa-rotate-left mr-1"></i> Recuperar' +
						'</button>' +
						'<button type="button" class="btn btn-xs btn-outline-danger btn-delete-held" data-id="' + h.id + '">' +
							'<i class="fa-solid fa-trash-can"></i>' +
						'</button>' +
					'</td>' +
				'</tr>';
			$tbody.append(tr);
		});
	}

	$('#btn-open-held-modal').on('click', function() {
		renderHeldSalesModal();
		$('#modal-held-sales').modal('show');
	});

	$(document).on('click', '.btn-recover-held', function() {
		var holdId = parseInt($(this).data('id'), 10);
		var found = (heldSalesList || []).find(function(h) { return parseInt(h.id, 10) === holdId; });
		if (!found) return;

		cart = Array.isArray(found.items) ? found.items : [];
		$('#pos-customer-select').val(found.customer_id || 1).trigger('change');
		$('#pos-sale-notes').val(found.notes || '');
		$('#pos-discount-type').val(found.discount_type || 'pct');
		$('#pos-discount-input').val(parseFloat(found.discount_value || 0).toFixed(2));
		$('#pos-tax-rate').val(parseFloat(found.tax_rate || 15));
		discountReason = found.discount_reason || '';
		if (discountReason) {
			$('#pos-discount-reason-pill').removeClass('d-none').text(discountReason);
		} else {
			$('#pos-discount-reason-pill').addClass('d-none').text('');
		}

		// Eliminar de la cola en espera tras recuperarla al ticket activo
		$.post(U('/api/pos/hold/' + holdId + '/delete'), function() {
			heldSalesList = heldSalesList.filter(function(h) { return parseInt(h.id, 10) !== holdId; });
			updateHeldBadge();
		});

		$('#modal-held-sales').modal('hide');
		renderCart();
		notifyToast('success', 'Venta en espera recuperada al ticket activo.');
	});

	$(document).on('click', '.btn-delete-held', function() {
		var holdId = parseInt($(this).data('id'), 10);
		$.post(U('/api/pos/hold/' + holdId + '/delete'), function() {
			heldSalesList = heldSalesList.filter(function(h) { return parseInt(h.id, 10) !== holdId; });
			updateHeldBadge();
			renderHeldSalesModal();
			notifyToast('info', 'Venta en espera descartada.');
		});
	});

	// =========================================================================
	// 7. PROCESAR VENTA Y EMITIR COMPROBANTE SIMULADO
	// =========================================================================
	$('#btn-submit-sale').on('click', function() {
		if (cart.length === 0) return;
		var calc = getTicketCalculation();
		var $btn = $(this);

		// Construir nota enriquecida con motivo de descuento y detalle de cobro mixto o referencia
		var notesParts = [];
		var userNote = $.trim($('#pos-sale-notes').val());
		if (userNote) notesParts.push(userNote);
		if (discountReason && calc.ticketDiscount > 0) {
			notesParts.push('[Descuento: ' + discountReason + ' (-' + fmtMoney(calc.ticketDiscount) + ')]');
		}
		if (paymentMethod === 'mixed') {
			var mCash = parseFloat($('#pos-mixed-cash').val()) || 0;
			var mCard = parseFloat($('#pos-mixed-card').val()) || 0;
			var mTrans = parseFloat($('#pos-mixed-transfer').val()) || 0;
			notesParts.push('[Pago Mixto: Efectivo $' + mCash.toFixed(2) + ' | Tarjeta $' + mCard.toFixed(2) + ' | Transferencia $' + mTrans.toFixed(2) + ']');
		} else if (paymentMethod === 'card' || paymentMethod === 'transfer') {
			var ref = $.trim($('#pos-payment-ref').val());
			if (ref) notesParts.push('[Ref. ' + paymentMethod + ': ' + ref + ']');
		}

		var payload = {
			branch_id: currentBranchId,
			customer_id: parseInt($('#pos-customer-select').val(), 10) || 1,
			payment_method: paymentMethod,
			notes: notesParts.join(' | '),
			allow_pos_discount: 1,
			enforce_open_shift: 1,
			ticket_discount_amount: calc.ticketDiscount,
			tax_amount: calc.taxAmount,
			items: cart.map(function(it) {
				return {
					product_id: it.product_id,
					amount: it.amount,
					unit_price: it.unit_price,
					allow_pos_discount: 1
				};
			})
		};

		var snapshotCart = cart.slice();
		var snapshotCustomer = $('#pos-customer-select option:selected').data('name') || 'Público General / Mostrador';
		var snapshotBranch = $('#pos-branch-select option:selected').text();
		var snapshotCashReceived = parseFloat($('#pos-cash-received').val()) || calc.grandTotal;

		$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Procesando...');

		$.ajax({
			url: U('/api/sales'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				// Descontar existencias locales en las tarjetas de la sucursal
				snapshotCart.forEach(function(sold) {
					var $card = $('.pos-product-card[data-id="' + sold.product_id + '"]');
					if ($card.length) {
						var stocks = $card.data('branch-stocks') || {};
						stocks[currentBranchId] = Math.max(0, (parseFloat(stocks[currentBranchId]) || 0) - sold.amount);
						$card.data('branch-stocks', stocks);
					}
				});
				refreshBranchCatalogStock();

				if (res.shift) {
					shiftState = res.shift;
				}
				fetchPosStateForBranch(currentBranchId);

				// Poblar y abrir comprobante simulado
				var payLabelMap = { cash: 'Efectivo', card: 'Tarjeta (Simulado)', transfer: 'Transferencia (Simulado)', mixed: 'Cobro Mixto' };
				$('#rcpt-branch-name').text(snapshotBranch);
				$('#rcpt-sale-number').text('Ticket #' + res.sale_number);
				$('#rcpt-datetime').text(res.created_timestamp || new Date().toLocaleString());
				$('#rcpt-customer-name').text(snapshotCustomer);
				$('#rcpt-payment-method').text(payLabelMap[paymentMethod] || paymentMethod);

				var $rcptBody = $('#rcpt-items-tbody');
				$rcptBody.empty();
				snapshotCart.forEach(function(it) {
					$rcptBody.append(
						'<tr>' +
							'<td>' + $('<div>').text(it.name).html() + '</td>' +
							'<td class="text-center">' + it.amount + '</td>' +
							'<td class="text-right">' + fmtMoney(it.unit_price) + '</td>' +
							'<td class="text-right font-weight-bold">' + fmtMoney(it.amount * it.unit_price) + '</td>' +
						'</tr>'
					);
				});

				$('#rcpt-subtotal').text(fmtMoney(calc.subtotal));
				$('#rcpt-discount').text('-' + fmtMoney(calc.ticketDiscount));
				$('#rcpt-tax').text(fmtMoney(calc.taxAmount));
				$('#rcpt-total').text(fmtMoney(calc.grandTotal));

				if (paymentMethod === 'cash') {
					var changeAmt = Math.max(0, snapshotCashReceived - calc.grandTotal);
					$('#rcpt-cash-row').removeClass('d-none');
					$('#rcpt-cash-detail').text('Recibido ' + fmtMoney(snapshotCashReceived) + ' · Cambio ' + fmtMoney(changeAmt));
				} else {
					$('#rcpt-cash-row').addClass('d-none');
				}

				// Limpiar carrito para la siguiente venta
				cart = [];
				discountReason = '';
				$('#pos-discount-input').val('0.00');
				$('#pos-discount-reason-pill').addClass('d-none').text('');
				$('#pos-sale-notes').val('');
				$('#pos-payment-ref').val('');
				cashManuallyEdited = false;
				renderCart();
				renderShiftStateUI();

				$('#modal-pos-receipt').modal('show');
			},
			error: function(xhr) {
				var errMsg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'No se pudo completar la venta.';
				notifyToast('error', errMsg);
				updateCartTotals();
			}
		});
	});

	$('#btn-print-receipt').on('click', function() {
		window.print();
	});

	// =========================================================================
	// 8. GESTIÓN DE CAJA Y TURNO (ABRIR / ARQUEO Y CERRAR CAJA)
	// =========================================================================
	function updateShiftDiffBadge() {
		var exp = parseFloat($('#shift-expected-cash').data('expected')) || 0;
		var counted = parseFloat($('#shift-counted-cash').val()) || 0;
		var diff = Math.round((counted - exp) * 100) / 100;
		var $b = $('#shift-diff-badge');
		if (Math.abs(diff) < 0.005) {
			$b.attr('class', 'badge badge-success d-block py-2').text('$0.00 (Cuadre exacto)');
		} else if (diff > 0) {
			$b.attr('class', 'badge badge-info d-block py-2').text('+' + fmtMoney(diff) + ' (Sobrante)');
		} else {
			$b.attr('class', 'badge badge-danger d-block py-2').text(fmtMoney(diff) + ' (Faltante)');
		}
	}

	$('#shift-counted-cash').on('input', updateShiftDiffBadge);

	$('#btn-header-shift, #btn-bottom-shift, #btn-banner-open-shift').on('click', function() {
		renderShiftStateUI();
		$('#modal-pos-shift').modal('show');
	});

	$('#btn-confirm-close-shift').on('click', function() {
		var payload = {
			branch_id: currentBranchId,
			counted_cash: parseFloat($('#shift-counted-cash').val()) || 0,
			notes: $('#shift-close-notes').val()
		};
		$.ajax({
			url: U('/api/pos/shift/close'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				shiftState = res.shift;
				renderShiftStateUI();
				$('#modal-pos-shift').modal('hide');
				notifyToast('info', 'Caja cerrada y arqueo registrado exitosamente.');
			},
			error: function(xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al cerrar caja';
				notifyToast('error', msg);
			}
		});
	});

	$('#btn-confirm-open-shift').on('click', function() {
		var payload = {
			branch_id: currentBranchId,
			register_name: $('#shift-open-register').val(),
			shift_name: $('#shift-open-name').val(),
			opening_amount: parseFloat($('#shift-open-amount').val()) || 200,
			notes: $('#shift-open-notes').val()
		};
		$.ajax({
			url: U('/api/pos/shift/open'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(res) {
				shiftState = res.shift;
				renderShiftStateUI();
				$('#modal-pos-shift').modal('hide');
				notifyToast('success', 'Caja abierta y turno iniciado.');
			},
			error: function(xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'Error al abrir caja';
				notifyToast('error', msg);
			}
		});
	});

	// =========================================================================
	// 9. HISTORIAL DE VENTAS Y DEVOLUCIONES / ANULACIÓN DE TICKETS
	// =========================================================================
	function renderHistoryTable() {
		var q = $.trim($('#pos-history-search').val()).toLowerCase();
		var $tbody = $('#pos-history-tbody');
		$tbody.empty();

		var rows = (recentSalesList || []).filter(function(s) {
			if (!q) return true;
			var blob = (s.sale_number + ' ' + (s.customer_name || '') + ' ' + (s.payment_method || '')).toLowerCase();
			return blob.indexOf(q) !== -1;
		});

		if (rows.length === 0) {
			$tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron tickets recientes para este filtro.</td></tr>');
			return;
		}

		var payNames = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia', mixed: 'Mixto' };
		rows.forEach(function(s) {
			var isCancelled = s.status === 'cancelled';
			var statusBadge = isCancelled
				? '<span class="badge badge-secondary">Anulada / Devuelta</span>'
				: '<span class="badge badge-success">Completada</span>';
			var actionBtns =
				'<button type="button" class="btn btn-xs btn-outline-primary mr-1 btn-view-sale-detail" data-id="' + s.id + '" title="Ver comprobante">' +
					'<i class="fa-solid fa-eye"></i> Detalle' +
				'</button>';
			if (!isCancelled) {
				actionBtns +=
					'<button type="button" class="btn btn-xs btn-outline-danger btn-cancel-return-sale" data-id="' + s.id + '" data-num="' + s.sale_number + '" title="Devolver productos al stock y anular ticket">' +
						'<i class="fa-solid fa-rotate-left"></i> Devolver' +
					'</button>';
			}

			$tbody.append(
				'<tr>' +
					'<td class="font-weight-bold">' + s.sale_number + '</td>' +
					'<td class="small text-muted">' + s.created_timestamp + '</td>' +
					'<td>' + $('<div>').text(s.customer_name || 'Público General').html() + '</td>' +
					'<td><span class="badge badge-light border">' + (payNames[s.payment_method] || s.payment_method) + '</span></td>' +
					'<td class="text-center">' + (s.item_count || 1) + '</td>' +
					'<td class="text-right font-weight-bold">' + fmtMoney(s.total_amount) + '</td>' +
					'<td>' + statusBadge + '</td>' +
					'<td class="text-right">' + actionBtns + '</td>' +
				'</tr>'
			);
		});
	}

	$('#btn-open-history, #btn-open-returns-modal').on('click', function() {
		$.get(U('/api/pos/state'), { branch_id: currentBranchId }, function(res) {
			if (res && res.recent_sales) {
				recentSalesList = res.recent_sales;
			}
			renderHistoryTable();
			$('#modal-pos-history').modal('show');
		});
	});

	$('#pos-history-search').on('input', renderHistoryTable);

	$(document).on('click', '.btn-view-sale-detail', function() {
		var saleId = parseInt($(this).data('id'), 10);
		$.get(U('/api/sales/' + saleId), function(res) {
			if (!res || !res.sale) return;
			var s = res.sale;
			var items = res.items || [];
			$('#rcpt-branch-name').text(s.branch_name || 'Sucursal');
			$('#rcpt-sale-number').text('Ticket #' + s.sale_number);
			$('#rcpt-datetime').text(s.created_timestamp);
			$('#rcpt-customer-name').text(s.customer_name || 'Público General / Mostrador');
			$('#rcpt-payment-method').text(s.payment_method);

			var $rcptBody = $('#rcpt-items-tbody');
			$rcptBody.empty();
			items.forEach(function(it) {
				$rcptBody.append(
					'<tr>' +
						'<td>' + $('<div>').text(it.product_name).html() + '</td>' +
						'<td class="text-center">' + it.amount + '</td>' +
						'<td class="text-right">' + fmtMoney(it.unit_price) + '</td>' +
						'<td class="text-right font-weight-bold">' + fmtMoney(it.subtotal) + '</td>' +
					'</tr>'
				);
			});

			$('#rcpt-subtotal').text(fmtMoney(s.subtotal));
			$('#rcpt-discount').text('-' + fmtMoney(s.discount_amount));
			$('#rcpt-tax').text(fmtMoney(s.tax_amount));
			$('#rcpt-total').text(fmtMoney(s.total_amount));
			$('#rcpt-cash-row').addClass('d-none');

			$('#modal-pos-history').modal('hide');
			$('#modal-pos-receipt').modal('show');
		});
	});

	$(document).on('click', '.btn-cancel-return-sale', function() {
		var saleId = parseInt($(this).data('id'), 10);
		var saleNum = $(this).data('num');
		if (!confirm('¿Confirmas la devolución y anulación del ticket ' + saleNum + '? Las unidades vendidas se reintegrarán automáticamente a su lote original en inventario.')) {
			return;
		}
		$.post(U('/api/sales/' + saleId + '/cancel'), function(res) {
			notifyToast('success', res.message || 'Ticket devuelto y stock reintegrado.');
			fetchPosStateForBranch(currentBranchId);
			renderHistoryTable();
		}).fail(function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error_message) ? xhr.responseJSON.error_message : 'No se pudo procesar la devolución.';
			notifyToast('error', msg);
		});
	});

	// Inicialización
	var urlParams = new URLSearchParams(window.location.search);
	var preselectCustId = urlParams.get('customer_id');
	if (preselectCustId && $('#pos-customer-select option[value="' + preselectCustId + '"]').length > 0) {
		$('#pos-customer-select').val(preselectCustId).trigger('change');
	}
	refreshBranchCatalogStock();
	renderShiftStateUI();
});
</script>
@endpush
@endsection
