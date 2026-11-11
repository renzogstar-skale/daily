@extends('layout.default')

@section('title', 'Punto de Venta (POS) · DAILY')

@push('pageStyles')
<link href="{{ $U('/css/daily_sales_dashboard.css?v=', true) }}{{ daily_asset_version('/css/daily_sales_dashboard.css') }}" rel="stylesheet">
<link href="{{ $U('/css/daily_pos.css?v=', true) }}{{ daily_asset_version('/css/daily_pos.css') }}" rel="stylesheet">
@endpush

@section('content')
{{--
	DAILY — Punto de venta. Escaneo → carrito → descuento autorizado → impuesto → cobro (efectivo, tarjeta, transferencia,
	mixto) → recibo → anulación/reimpresión autorizadas → arqueo y cierre. Lógica en public/js/daily_pos.js; reglas y
	cálculo autoritativo en services/PosService.php. Kardex, conteos, recepción, mermas, compras, CCTV y asistencia viven
	en sus propios tableros.
--}}
@php
	$selectedBranchName = '';
	foreach ($branches as $b) {
		if (intval(is_object($b) ? $b->id : $b['id']) === intval($selectedBranchId)) {
			$selectedBranchName = is_object($b) ? $b->name : $b['name'];
		}
	}
	$fm = fn($v) => '$' . number_format((float) $v, 2);
	$methodNames = \Grocy\Services\PosService::METHODS;
	$posBoot = [
		'branchId' => intval($selectedBranchId), 'branchName' => $selectedBranchName, 'register' => $register, 'shift' => $shift, 'settings' => $posSettings,
		'held' => array_map(fn($h) => ['id' => intval($h->id), 'label' => $h->label, 'total' => floatval($h->total_amount), 'items' => intval($h->item_count), 'created_at' => $h->created_at], $heldSales)
	];
@endphp

<div class="ded-wrapper pos-dash-wrapper" id="posRetailDashboard" data-testid="pos-page">
	<header class="ded-header">
		<div class="ded-header-left">
			<h1 class="ded-title">Punto de Venta (POS)</h1>
			<p class="ded-subtitle">Escaneo, cobro, recibos y control de caja · {{ $selectedBranchName }}</p>
		</div>
		<div class="ded-header-right">
			<form method="get" class="d-flex flex-wrap align-items-center" style="gap:6px;" id="pos-context-form">
				<select name="branch_id" class="ded-select-btn" aria-label="Sucursal" onchange="this.form.submit()">
					@foreach($branches as $b)
						@php $bid = intval(is_object($b) ? $b->id : $b['id']); @endphp
						<option value="{{ $bid }}" @if($bid === intval($selectedBranchId)) selected @endif>{{ is_object($b) ? $b->name : $b['name'] }}</option>
					@endforeach
				</select>
				<select name="register" class="ded-select-btn" aria-label="Caja" onchange="this.form.submit()" id="pos-register-select">
					@foreach($registers as $r)
						<option value="{{ $r }}" @if($r === $register) selected @endif>{{ $r }}</option>
					@endforeach
				</select>
			</form>
			<span class="pos-shift-pill {{ $shift ? 'is-open' : 'is-closed' }}" id="pos-shift-pill" data-testid="pos-shift-pill">
				<i data-lucide="{{ $shift ? 'unlock' : 'lock' }}"></i>
				<span id="pos-shift-pill-text">{{ $shift ? 'Turno abierto · ' . substr($shift['opened_at'], 11, 5) . ' · ' . $shift['opened_by'] : 'Caja cerrada' }}</span>
			</span>
			<span class="pos-net-pill" id="pos-net-pill" title="Estado de la conexión"><i data-lucide="wifi"></i><span>En línea</span></span>
		</div>
	</header>

	<nav class="nav ded-nav-tabs-bar mb-3" role="tablist" aria-label="Punto de venta">
		<button type="button" class="ded-nav-tab active" data-toggle="pill" data-target="#pos-tab-terminal" role="tab" id="pill-tab-terminal" aria-selected="true">
			<i data-lucide="shopping-bag" class="mr-1"></i><span>Venta</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-caja" role="tab" id="pill-tab-caja" aria-selected="false">
			<i data-lucide="scale" class="mr-1"></i><span>Caja y arqueo</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-transacciones" role="tab" id="pill-tab-transacciones" aria-selected="false">
			<i data-lucide="receipt" class="mr-1"></i><span>Transacciones</span>
		</button>
		<button type="button" class="ded-nav-tab" data-toggle="pill" data-target="#pos-tab-supervision" role="tab" id="pill-tab-supervision" aria-selected="false">
			<i data-lucide="shield-check" class="mr-1"></i><span>Autorizaciones</span>
		</button>
		<a class="ded-nav-tab" href="{{ $U('/pos/returns') }}?branch_id={{ $selectedBranchId }}"><i data-lucide="undo-2" class="mr-1"></i><span>Devoluciones</span></a>
	</nav>

	<div class="tab-content" id="posDashboardTabContent">
		<div class="tab-pane fade show active" id="pos-tab-terminal" role="tabpanel">
			<!-- AVISO SI LA CAJA ESTÁ CERRADA -->
			<div class="pos-closed-banner {{ $shift ? 'd-none' : '' }}" id="pos-closed-banner">
				<div>
					<i data-lucide="lock" style="width:16px;height:16px;margin-right:6px;"></i>
					<strong>{{ $register }} está cerrada:</strong> <span>las ventas están bloqueadas hasta abrir el turno con el fondo de caja contado.</span>
				</div>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btn-banner-open-shift" data-toggle="modal" data-target="#modal-pos-open-shift">
					<i data-lucide="unlock" style="width:14px;height:14px;margin-right:4px;"></i> Abrir Caja Ahora
				</button>
			</div>

			<!-- Espacio de Trabajo Principal: Catálogo (Izq) + Ticket (Der) -->
			<div class="pos-workspace-grid">

				<!-- Panel Izquierdo: Catálogo y Búsqueda -->
				<div class="pos-catalog-panel">
					<!-- Fila de Búsqueda y Escáner -->
					<div class="pos-search-row">
						<div class="pos-search-input-wrap">
							<i data-lucide="search" class="search-icon"></i>
							<input type="text"
								id="pos-search-input"
								class="pos-search-input"
								placeholder="Buscar producto por nombre, SKU, código de barras o escanear..."
								autocomplete="off">
							<button type="button" class="pos-search-clear" id="pos-search-clear" title="Limpiar búsqueda">
								<i data-lucide="x" style="width:14px;height:14px;"></i>
							</button>
						</div>
						<button type="button" class="pos-barcode-btn" id="btn-focus-scanner" title="Modo lector láser de código de barras: presiona Enter tras escanear">
							<i data-lucide="scan-barcode"></i>
							<span>Lector UPC/EAN</span>
						</button>
					</div>

					<!-- Píldoras de Categorías -->
					<div class="pos-categories-wrap" id="pos-categories-bar">
						<button type="button" class="pos-cat-pill active" data-category="ALL">Todos los Departamentos</button>
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
								data-tax-rate="{{ $product->tax_rate ?? 0 }}"
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
												<i data-lucide="zap" style="width:10px;height:10px;"></i>FEFO
											</span>
											@if($markdownPct > 0)
												<span class="pos-badge-promo" title="Rebaja automática autorizada">-{{ number_format($markdownPct, 0) }}%</span>
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
										<span>Sin disponibilidad</span>
									@else
										<i data-lucide="plus" style="width:13px;height:13px;"></i>
										<span>Agregar</span>
									@endif
								</button>
							</div>
						@endforeach
					</div>

					<div id="pos-catalog-empty" class="text-center py-5 d-none">
						<div class="mb-2 text-muted" style="font-size:2rem;"><i data-lucide="package-search"></i></div>
						<h6 class="font-weight-bold text-dark mb-1">No se encontraron productos coincidentes</h6>
						<p class="text-muted small mb-3">Verifica el código buscado o cambia la categoría.</p>
						<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" id="btn-reset-catalog-filters">
							Mostrar todo el catálogo
						</button>
					</div>
				</div>

				<!-- Panel Derecho: Ticket de Venta -->
				<div class="pos-ticket-panel">
					<!-- Cabecera Verde del Ticket -->
					<div class="pos-ticket-header">
						<div class="pos-ticket-header-left">
							<i data-lucide="shopping-cart" style="width:18px;height:18px;"></i>
							<span id="pos-ticket-title">Ticket nuevo</span>
						</div>
						<div class="pos-ticket-header-right">
							<span class="pos-ticket-count-pill" id="pos-item-count-badge">0 ítems</span>
							<button type="button" class="pos-btn-clear-ticket" id="btn-clear-cart" title="Vaciar ticket">
								<i data-lucide="trash-2" style="width:14px;height:14px;"></i>
							</button>
						</div>
					</div>

					<!-- Selector de Cliente -->
					<div class="pos-customer-section">
						<div class="pos-customer-label">
							<span>Cliente comercial (opcional)</span>
							<span class="text-muted" id="pos-selected-customer-tag">Mostrador / Público General</span>
						</div>
						<div class="pos-customer-input-group">
							<i data-lucide="user" class="cust-icon"></i>
							<select id="pos-customer-select" class="pos-customer-select" aria-label="Seleccionar cliente">
								@foreach($customers as $c)
									<option value="{{ $c->id }}" data-taxid="{{ $c->tax_id ?? '' }}" data-name="{{ $c->name }}" data-anon="{{ intval($c->is_anonymous ?? 0) }}" {{ intval($c->id) === intval($preselectedCustomerId ?? 1) ? 'selected' : '' }}>
										{{ $c->name }}{{ !empty($c->tax_id) ? ' (' . $c->tax_id . ')' : '' }}
									</option>
								@endforeach
							</select>
							<button type="button" class="pos-customer-reset" id="btn-reset-customer" title="Restablecer cliente">
								<i data-lucide="x" style="width:12px;height:12px;"></i>
							</button>
						</div>
						<div id="pos-customer-loyalty-badge" class="small text-success font-weight-bold d-none" style="margin-top:4px;font-size:11px;"></div>
					</div>

					<!-- Encabezado de Columnas del Ticket -->
					<div class="pos-ticket-table-head">
						<div>Producto</div>
						<div class="text-right">Precio</div>
						<div class="text-center">Cant.</div>
						<div class="text-right">Subtotal</div>
						<div></div>
					</div>

					<!-- Lista de Ítems en Carrito -->
					<div class="pos-ticket-items-list" id="pos-cart-items">
						<div class="text-center py-5 px-3 text-muted" id="pos-empty-cart-msg">
							<i data-lucide="shopping-bag" style="width:36px;height:36px;margin:0 auto 8px;opacity:0.4;display:block;"></i>
							<div class="font-weight-bold text-dark" style="font-size:12px;">El ticket está vacío</div>
							<div class="small mt-1">Haz clic en <strong>Agregar</strong> sobre un producto o escanea su código de barras.</div>
						</div>
					</div>

					<!-- Desglose de Totales -->
					<div class="pos-totals-section">
						<div class="pos-total-row">
							<span id="pos-subtotal-label">Subtotal (0 ítems)</span>
							<strong id="pos-subtotal-display">$0.00</strong>
						</div>

						<div class="pos-total-row">
							<div class="d-flex align-items-center gap-1">
								<span>Descuento</span>
								<input type="number" id="pos-discount-input" value="0" min="0" step="0.01" aria-label="Valor del descuento" style="width:55px;height:24px;font-size:11px;padding:2px 4px;border:1px solid #cbd5e1;border-radius:4px;">
								<select id="pos-discount-type" aria-label="Tipo de descuento" style="height:24px;font-size:10px;border:1px solid #cbd5e1;border-radius:4px;">
									<option value="pct">%</option>
									<option value="amt">$</option>
								</select>
								<span class="pos-auth-chip d-none" id="pos-discount-auth" title="Descuento autorizado por supervisor"><i data-lucide="shield-check" style="width:11px;height:11px;"></i><span></span></span>
							</div>
							<strong id="pos-discount-display" class="text-danger">-$0.00</strong>
						</div>
						<div class="pos-total-row d-none" id="pos-discount-reason-row">
							<input type="text" id="pos-discount-reason" class="form-control form-control-sm" maxlength="120" placeholder="Motivo del descuento (obligatorio)" aria-label="Motivo del descuento">
						</div>

						<div class="pos-total-row">
							<span>Impuesto <small class="text-muted" id="pos-tax-note">(según departamento)</small></span>
							<strong id="pos-tax-display">$0.00</strong>
						</div>

						<div class="pos-grand-total-row">
							<span class="pos-grand-total-label">TOTAL A COBRAR:</span>
							<span class="pos-grand-total-value" id="pos-total-display">$0.00</span>
						</div>
					</div>

					<!-- Medios de Pago y Cobro -->
					<div class="pos-payment-section">
						<div class="pos-payment-tabs" id="pos-payment-tabs">
							<button type="button" class="pos-pay-tab active" data-method="cash">
								<i data-lucide="banknote" style="width:14px;height:14px;"></i>Efectivo
							</button>
							<button type="button" class="pos-pay-tab" data-method="card">
								<i data-lucide="credit-card" style="width:14px;height:14px;"></i>Tarjeta
							</button>
							<button type="button" class="pos-pay-tab" data-method="transfer">
								<i data-lucide="arrow-right-left" style="width:14px;height:14px;"></i>Transfer.
							</button>
							<button type="button" class="pos-pay-tab" data-method="mixed">
								<i data-lucide="layers" style="width:14px;height:14px;"></i>Mixto
							</button>
							<button type="button" class="pos-pay-tab" data-method="credit_note">
								<i data-lucide="ticket" style="width:14px;height:14px;"></i>Nota
							</button>
							<button type="button" class="pos-pay-tab {{ empty($loyaltySettings['loyalty_enabled']) ? 'd-none' : '' }}" data-method="points" id="pos-tab-points" title="Canjear saldo de puntos del cliente">
								<i data-lucide="award" style="width:14px;height:14px;"></i>Puntos
							</button>
						</div>

						<!-- Caja de Efectivo Recibido y Cambio -->
						<div class="pos-cash-tender-box" id="pos-panel-cash">
							<div class="pos-cash-grid">
								<div class="pos-cash-field">
									<label for="pos-cash-received">Efectivo Recibido</label>
									<div class="pos-cash-input-wrap">
										<span class="cash-icon">$</span>
										<input type="number" id="pos-cash-received" value="0.00" min="0" step="0.01">
									</div>
								</div>
								<div class="pos-cash-field">
									<label>Cambio / Vuelto</label>
									<div class="pos-change-pill" id="pos-change-pill">
										<i data-lucide="coins" style="width:14px;height:14px;"></i>
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

						<!-- Panel Tarjeta / Referencia -->
						<div class="pos-cash-tender-box d-none" id="pos-panel-noncash">
							<label style="font-size:10px;font-weight:700;color:#64748b;margin-bottom:3px;display:block;">Código de Autorización / Voucher PinPad</label>
							<input type="text" id="pos-payment-ref" class="form-control form-control-sm" placeholder="Código de autorización del datáfono (ej. AUT-8941)" maxlength="40" autocomplete="off">
							<small class="text-muted d-block mt-1">Nunca escriba el número de la tarjeta: DAILY no guarda datos de tarjetas.</small>
							<small class="text-muted d-block mt-1" style="font-size:10px;">Validación de pago exitoso simulado con idempotencia garantizada.</small>
						</div>

						<!-- Panel Cobro Mixto -->
						<div class="pos-cash-tender-box d-none" id="pos-panel-credit">
							<label class="small font-weight-bold mb-1" for="pos-credit-code">Código de la nota de crédito</label>
							<div class="d-flex" style="gap:6px;"><input type="text" id="pos-credit-code" class="form-control form-control-sm" placeholder="NC-…" autocomplete="off"><button type="button" class="btn btn-sm btn-light border" id="pos-credit-check">Consultar</button></div>
							<small class="text-muted d-block mt-1" id="pos-credit-info">El saldo que no cubra la nota se cobra en efectivo.</small>
						</div>

						<div class="pos-cash-tender-box d-none" id="pos-panel-points">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<label class="small font-weight-bold mb-0" for="pos-points-amount">Monto a cubrir con puntos ({{ $regional['currency_symbol'] ?? '$' }})</label>
								<button type="button" class="btn btn-xs btn-link p-0 font-weight-bold" id="pos-points-refresh">Actualizar saldo</button>
							</div>
							<div class="d-flex" style="gap:6px;"><input type="number" id="pos-points-amount" class="form-control form-control-sm" value="0.00" min="0" step="0.01"><button type="button" class="btn btn-sm btn-light border" id="pos-points-max">Usar máximo</button></div>
							<small class="text-muted d-block mt-1" id="pos-points-info">Seleccione un cliente registrado para consultar sus puntos disponibles. El resto se cobra en efectivo.</small>
						</div>

						<div class="pos-cash-tender-box d-none" id="pos-panel-mixed">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span style="font-size:11px;font-weight:700;">Desglose Cobro Mixto</span>
								<span class="badge badge-warning" id="pos-mixed-status-badge" style="font-size:10px;">Pendiente: $0.00</span>
							</div>
							<div class="row">
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Efectivo</label>
									<input type="number" id="pos-mixed-cash" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Tarjeta</label>
									<input type="number" id="pos-mixed-card" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
								<div class="col-4">
									<label style="font-size:10px;font-weight:700;margin-bottom:2px;">Transfer.</label>
									<input type="number" id="pos-mixed-transfer" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
								</div>
							</div>
						</div>

						<!-- Botones de Acción de Venta -->
						<div class="pos-ticket-actions">
							<button type="button" class="pos-btn-hold" id="btn-hold-sale" title="Guardar venta en espera">
								<i data-lucide="clock" style="width:13px;height:13px;"></i>
								<span>Suspender</span>
							</button>
							<button type="button" class="pos-btn-discount" id="btn-recall-sale" title="Recuperar ticket suspendido">
								<i data-lucide="history" style="width:13px;height:13px;"></i>
								<span>Recuperar (<span id="pos-held-count">{{ count($heldSales) }}</span>)</span>
							</button>
							<button type="button" class="pos-btn-checkout" id="btn-submit-sale" disabled>
								<i data-lucide="check-circle" style="width:16px;height:16px;"></i>
								<span id="btn-submit-sale-label">Cobrar $0.00</span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		{{-- ============================================================ CAJA Y ARQUEO --}}
		<div class="tab-pane fade" id="pos-tab-caja" role="tabpanel" data-testid="pos-cash-tab">
			<div class="ded-row-2">
				<article class="ded-card">
					<header class="ded-card-header"><h2 class="ded-card-title">Turno de {{ $register }}</h2><span class="srep-note mb-0" id="pos-shift-status-note"></span></header>
					<div id="pos-shift-summary"></div>
					<div class="d-flex flex-wrap mt-2" style="gap:6px;">
						<button type="button" class="ded-select-btn" id="btn-open-shift" data-toggle="modal" data-target="#modal-pos-open-shift"><i data-lucide="unlock"></i><span>Abrir turno</span></button>
						<button type="button" class="ded-select-btn" id="btn-cash-drop"><i data-lucide="arrow-up-from-line"></i><span>Retiro a bóveda</span></button>
						<button type="button" class="ded-select-btn" id="btn-cash-payin"><i data-lucide="arrow-down-to-line"></i><span>Ingreso de efectivo</span></button>
					</div>
				</article>
				<article class="ded-card" data-testid="pos-close-card">
					<header class="ded-card-header"><h2 class="ded-card-title">Arqueo y cierre</h2></header>
					<p class="srep-note mt-0">Cuente el efectivo de la gaveta por denominación. Diferencias mayores a {{ $fm($posSettings['cash_diff_tolerance']) }} requieren motivo y autorización de un supervisor.</p>
					<div class="pos-denoms" id="pos-denoms">
						@foreach([100, 50, 20, 10, 5, 2, 1, 0.25, 0.1, 0.05, 0.01] as $d)
						<label class="pos-denom"><span>{{ $d >= 1 ? '$' . $d : number_format($d * 100) . '¢' }}</span><input type="number" min="0" step="1" value="0" data-denom="{{ $d }}" aria-label="Cantidad de {{ $d }}"></label>
						@endforeach
					</div>
					<div class="ded-comp-col mt-2">
						<div class="ded-comp-row"><span class="ded-comp-label">Efectivo contado</span><span class="ded-comp-val" id="pos-counted-display">$0.00</span></div>
						<div class="ded-comp-row"><span class="ded-comp-label">Efectivo esperado</span><span class="ded-comp-val" id="pos-expected-display">—</span></div>
						<div class="ded-comp-row"><span class="ded-comp-label">Diferencia</span><span class="ded-comp-val" id="pos-diff-display">—</span></div>
					</div>
					<input type="text" class="form-control form-control-sm mt-2" id="pos-close-reason" maxlength="200" placeholder="Motivo de la diferencia (si la hay)" aria-label="Motivo de la diferencia">
					<button type="button" class="pos-btn-checkout mt-2" id="btn-close-shift" disabled><i data-lucide="lock" style="width:16px;height:16px;"></i><span>Cerrar turno</span></button>
				</article>
			</div>
		</div>

		{{-- ============================================================ TRANSACCIONES --}}
		<div class="tab-pane fade" id="pos-tab-transacciones" role="tabpanel">
			<article class="ded-card">
				<header class="ded-card-header">
					<h2 class="ded-card-title">Consulta de transacciones</h2>
					<div class="ded-card-tools" style="gap:6px;">
						<input type="date" class="ded-select-btn" id="pos-tx-date" value="{{ date('Y-m-d') }}" aria-label="Fecha">
						<select class="ded-select-btn" id="pos-tx-status" aria-label="Estado"><option value="">Todas</option><option value="completed">Completadas</option><option value="cancelled">Anuladas</option></select>
						<input type="search" class="ded-select-btn" id="pos-tx-q" placeholder="Ticket, cliente o monto" aria-label="Buscar">
					</div>
				</header>
				<div class="srep-scroll">
					<table class="ded-table-top5" id="pos-tx-table" data-testid="pos-tx-table">
						<thead><tr><th>Hora</th><th>Ticket</th><th>Caja</th><th>Cajero</th><th>Cliente</th><th>Pago</th><th class="text-right">Total</th><th>Estado</th><th class="text-right">Recibos</th><th></th></tr></thead>
						<tbody id="pos-tx-tbody"></tbody>
					</table>
				</div>
			</article>
		</div>

		{{-- ============================================================ AUTORIZACIONES --}}
		<div class="tab-pane fade" id="pos-tab-supervision" role="tabpanel">
			<article class="ded-card">
				<header class="ded-card-header"><h2 class="ded-card-title">Autorizaciones de supervisor</h2><span class="srep-note mb-0">Descuentos, anulaciones, reimpresiones y diferencias de caja</span></header>
				@if(!$authorizations)
					<p class="ded-text-muted-italics mb-0">Aún no hay autorizaciones registradas en esta sucursal.</p>
				@else
				<div class="srep-scroll"><table class="ded-table-top5">
					<thead><tr><th>Fecha</th><th>Tipo</th><th>Ticket</th><th>Solicitó</th><th>Autorizó</th><th class="text-right">Valor</th><th>Motivo</th><th>Estado</th></tr></thead>
					<tbody>
					@foreach($authorizations as $a)
						<tr><td>{{ date('d/m/Y H:i', strtotime($a['created_at'])) }}</td>
							<td>{{ ['discount' => 'Descuento', 'void' => 'Anulación', 'reprint' => 'Reimpresión', 'cash_difference' => 'Diferencia de caja'][$a['kind']] ?? $a['kind'] }}</td>
							<td>{{ $a['sale_number'] ?: '—' }}</td><td>{{ $a['requested_name'] }}</td><td>{{ $a['approved_name'] }}</td>
							<td class="text-right">{{ $a['pct'] !== null ? number_format($a['pct'], 1) . ' %' : ($a['amount'] !== null ? $fm($a['amount']) : '—') }}</td>
							<td>{{ $a['reason'] }}</td><td>{{ $a['status'] === 'used' ? 'Aplicada' : ($a['status'] === 'granted' ? 'Sin usar' : $a['status']) }}</td></tr>
					@endforeach
					</tbody>
				</table></div>
				@endif
			</article>
		</div>
	</div>
</div>

{{-- ============================================================ DIÁLOGOS --}}
<div class="modal fade" id="modal-pos-open-shift" tabindex="-1" role="dialog" aria-labelledby="pos-open-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<form class="modal-content" id="form-open-shift">
			<div class="modal-header"><h5 class="modal-title" id="pos-open-title">Abrir turno · {{ $register }}</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
			<div class="modal-body">
				<div class="form-group"><label for="shift-register">Caja</label><input type="text" class="form-control" id="shift-register" value="{{ $register }}" maxlength="40" required></div>
				<div class="form-group"><label for="shift-name">Turno</label><input type="text" class="form-control" id="shift-name" value="{{ intval(date('H')) < 14 ? 'Mañana' : 'Tarde' }}" maxlength="30"></div>
				<div class="form-group mb-0"><label for="shift-opening">Fondo de caja contado</label><input type="number" class="form-control" id="shift-opening" min="0" step="0.01" required></div>
			</div>
			<div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Abrir turno</button></div>
		</form>
	</div>
</div>

<div class="modal fade" id="modal-pos-auth" tabindex="-1" role="dialog" aria-labelledby="pos-auth-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm" role="document">
		<form class="modal-content" id="form-pos-auth" autocomplete="off">
			<div class="modal-header"><h5 class="modal-title" id="pos-auth-title">Autorización de supervisor</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
			<div class="modal-body">
				<p class="small text-muted" id="pos-auth-desc"></p>
				<div class="form-group"><label for="auth-reason">Motivo</label><input type="text" class="form-control" id="auth-reason" maxlength="200" required></div>
				<div class="form-group"><label for="auth-user">Usuario supervisor</label><input type="text" class="form-control" id="auth-user" autocomplete="off" required></div>
				<div class="form-group mb-0"><label for="auth-pass">Clave</label><input type="password" class="form-control" id="auth-pass" autocomplete="new-password" required></div>
			</div>
			<div class="modal-footer"><button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Autorizar</button></div>
		</form>
	</div>
</div>

<div class="modal fade" id="modal-pos-receipt" tabindex="-1" role="dialog" aria-labelledby="pos-receipt-title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header"><h5 class="modal-title" id="pos-receipt-title">Recibo</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
			<div class="modal-body"><div class="pos-receipt" id="pos-receipt" data-testid="pos-receipt"></div></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light border mr-auto" id="btn-receipt-void"><i data-lucide="ban" style="width:14px;height:14px;"></i> Anular</button>
				<button type="button" class="btn btn-light border" id="btn-receipt-reprint"><i data-lucide="printer" style="width:14px;height:14px;"></i> Reimprimir</button>
				<button type="button" class="btn btn-success" id="btn-receipt-print"><i data-lucide="printer" style="width:14px;height:14px;"></i> Imprimir</button>
			</div>
		</div>
	</div>
</div>

<script type="application/json" id="pos-boot">{!! json_encode($posBoot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@stop

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js') }}"></script>
<script src="{{ $U('/js/daily_pos.js?v=', true) }}{{ daily_asset_version('/js/daily_pos.js') }}"></script>
@endpush
