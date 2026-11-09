@extends('layout.default')

@section('title', 'Operaciones con escáner')

@section('content')
@php
	$typeIcon = ['count' => 'fa-list-check', 'move' => 'fa-arrows-left-right', 'receive' => 'fa-truck-ramp-box', 'pick' => 'fa-boxes-packing'];
	$statusLabel = ['open' => ['Abierta', 'warning'], 'closed' => ['Cerrada', 'success'], 'cancelled' => ['Anulada', 'secondary'], 'closing' => ['Cerrando', 'info']];
@endphp

<link rel="stylesheet" href="{{ $U('/css/daily_scan.css?v=', true) }}{{ daily_asset_version('/css/daily_scan.css') }}">

<div class="daily-scan-container">
	{{-- Encabezado con fotografía difuminada y contexto --}}
	<div class="daily-scan-header">
		<div class="daily-scan-header-left">
			<div class="daily-scan-header-icon">
				<i class="fa-solid fa-barcode"></i>
			</div>
			<div>
				<h1 class="daily-scan-header-title">Operaciones con escáner</h1>
				<p class="daily-scan-header-subtitle">Consulta productos y ejecuta operaciones desde una sola estación.</p>
				<div class="daily-scan-context-pills">
					<span class="daily-scan-context-pill">
						<i class="fa-solid fa-building-columns"></i>
						<span>@if(!empty($form['branches'][0])){{ $form['branches'][0]['name'] }}@else Red Consolidada @endif</span>
					</span>
					<span class="daily-scan-context-pill is-primary" id="sc-reader">
						<i class="fa-solid fa-keyboard"></i>
						<span>Modo teclado activo</span>
					</span>
					<span class="daily-scan-context-pill" id="sc-device" title="Identificador de este dispositivo en las lecturas">
						<i class="fa-solid fa-laptop"></i>
						<span></span>
					</span>
					<span class="daily-scan-context-pill" id="sc-network-status" title="Estado de la conexión">
						<i class="fa-solid fa-circle text-success" style="font-size: 8px;"></i>
						<span>En línea</span>
					</span>
				</div>
			</div>
		</div>
		<div class="daily-scan-header-bg" style="background-image: url('{{ $U('/img/control_center/daily_scan_header.jpg') }}');"></div>
	</div>

	{{-- Cinco Operaciones Principales --}}
	<div class="daily-ops-grid" id="sc-modes" role="tablist">
		<a href="#" class="daily-op-card is-active" data-mode="lookup" role="tab" id="op-tab-lookup">
			<div class="daily-op-card-icon">
				<i class="fa-solid fa-magnifying-glass"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Consultar</div>
				<div class="daily-op-card-subtitle">Producto, lote o ubicación</div>
			</div>
		</a>

		@if($can['count'])
		<a href="#" class="daily-op-card" data-mode="count" role="tab" id="op-tab-count">
			<div class="daily-op-card-icon">
				<i class="fa-solid fa-list-check"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Conteo guiado</div>
				<div class="daily-op-card-subtitle">Conteo por ubicación</div>
			</div>
		</a>
		@endif

		@if($can['move'])
		<a href="#" class="daily-op-card" data-mode="move" role="tab" id="op-tab-move">
			<div class="daily-op-card-icon">
				<i class="fa-solid fa-arrows-left-right"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Mover</div>
				<div class="daily-op-card-subtitle">Origen y destino</div>
			</div>
		</a>
		@endif

		@if($can['receive'])
		<a href="#" class="daily-op-card" data-mode="receive" role="tab" id="op-tab-receive">
			<div class="daily-op-card-icon">
				<i class="fa-solid fa-truck-ramp-box"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Recibir OC</div>
				<div class="daily-op-card-subtitle">Contra orden de compra</div>
			</div>
		</a>
		@endif

		@if($can['pick'])
		<a href="#" class="daily-op-card" data-mode="pick" role="tab" id="op-tab-pick">
			<div class="daily-op-card-icon">
				<i class="fa-solid fa-boxes-packing"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Preparar pedido</div>
				<div class="daily-op-card-subtitle">Validar producto y cantidad</div>
			</div>
		</a>
		@endif

		{{-- Auditoría de Etiquetas --}}
		<a href="#" class="daily-op-card" data-mode="price-audit" role="tab" id="op-tab-price-audit" onclick="event.preventDefault(); window.togglePriceAuditMode();">
			<div class="daily-op-card-icon text-warning">
				<i class="fa-solid fa-tags"></i>
			</div>
			<div>
				<div class="daily-op-card-title">Auditoría de Etiquetas</div>
				<div class="daily-op-card-subtitle">UPC vs Góndola y POS</div>
			</div>
		</a>
	</div>

	{{-- Fila Principal: Estación de Trabajo + Paneles Laterales --}}
	<div class="row">
		{{-- Columna Izquierda: Estación de Trabajo --}}
		<div class="col-lg-7 mb-3">
			<div class="daily-workstation-card">
				{{-- Stepper de Pasos --}}
				<div class="daily-scan-stepper">
					<div class="daily-step-item is-active" id="step-1">
						<span class="daily-step-badge">1</span>
						<span>Elige la operación</span>
					</div>
					<i class="fa-solid fa-arrow-right daily-step-arrow"></i>
					<div class="daily-step-item is-active" id="step-2">
						<span class="daily-step-badge">2</span>
						<span>Escanea o escribe</span>
					</div>
					<i class="fa-solid fa-arrow-right daily-step-arrow"></i>
					<div class="daily-step-item" id="step-3">
						<span class="daily-step-badge">3</span>
						<span>Revisa el resultado</span>
					</div>
				</div>

				{{-- Título de la operación activa --}}
				<div class="daily-workstation-heading" id="sc-workstation-title">
					Consultar producto, lote o ubicación
				</div>

				{{-- Formulario de Captura --}}
				<form id="sc-form" autocomplete="off">
					<div class="daily-scan-bar-wrap">
						<div class="daily-scan-input-box">
							<i class="fa-solid fa-barcode"></i>
							<input type="text" class="daily-scan-input" id="sc-input" data-dairy-scan-input placeholder="Escanea o escribe código, SKU, lote o LOC-…" aria-label="Código" inputmode="text" autofocus>
						</div>
						<button type="submit" class="btn-daily-action btn-daily-action-primary" id="sc-submit-btn">
							<i class="fa-solid fa-arrow-right mr-1"></i>
							<span id="sc-submit-label">Consultar</span>
						</button>
						<button type="button" class="btn-daily-action btn-daily-action-outline" id="sc-camera" title="Activar cámara del dispositivo">
							<i class="fa-solid fa-camera mr-1"></i>
							<span>Usar cámara</span>
						</button>
					</div>
				</form>

				{{-- Selector de Modo de Entrada --}}
				<div class="daily-input-mode-row">
					<span class="daily-input-mode-label">Modo de entrada:</span>
					<button type="button" class="daily-mode-pill is-active" id="sc-pill-keyboard">
						<i class="fa-solid fa-keyboard"></i>
						<span>Teclado</span>
					</button>
					<button type="button" class="daily-mode-pill" id="sc-pill-camera">
						<i class="fa-solid fa-camera"></i>
						<span>Cámara</span>
					</button>
					<button type="button" class="daily-mode-pill" id="sc-pill-manual">
						<i class="fa-solid fa-pen"></i>
						<span>Entrada manual</span>
					</button>
				</div>

				{{-- Aviso contextual --}}
				<div class="daily-scan-help-banner" id="sc-mode-help">
					<i class="fa-solid fa-circle-info"></i>
					<span id="sc-mode-help-text">Con lector tipo teclado, enfoca este campo y escanea.</span>
				</div>

				{{-- Campos extra de sesión activa (cantidad, lote, vencimiento) --}}
				<div id="sc-session-extra" class="form-row mt-2 mb-3 d-none">
					<div class="col-4">
						<label class="small text-muted font-weight-600 mb-1" for="sc-amount">Cantidad</label>
						<input type="number" min="0.001" step="any" class="form-control" id="sc-amount" placeholder="1">
					</div>
					<div class="col-4">
						<label class="small text-muted font-weight-600 mb-1" for="sc-lot">Lote / Serie</label>
						<input type="text" class="form-control" id="sc-lot" placeholder="del código">
					</div>
					<div class="col-4">
						<label class="small text-muted font-weight-600 mb-1" for="sc-bbd">Vence</label>
						<input type="date" class="form-control" id="sc-bbd">
					</div>
				</div>

				{{-- Visor de Cámara --}}
				<div id="sc-video-wrap" class="mt-2 mb-3 d-none text-center">
					<div class="border rounded p-2 bg-dark">
						<video id="sc-video" class="w-100 rounded" style="max-height: 280px; object-fit: cover;" muted playsinline></video>
						<div class="text-white small mt-1">
							<i class="fa-solid fa-video mr-1 text-success"></i> Apunta la cámara al código de barras o QR
						</div>
					</div>
				</div>

				{{-- Mensaje de estado en vivo --}}
				<div id="sc-msg" class="small mt-2" role="status" aria-live="polite"></div>

				{{-- Estado Hero Inicial (Listo para tu primera consulta) --}}
				<div id="sc-hero-empty" class="daily-hero-ready-box">
					<div class="daily-hero-ready-content">
						<div class="daily-hero-watermark">
							<i class="fa-solid fa-barcode"></i>
						</div>
						<div class="daily-hero-title">Listo para tu primera consulta</div>
						<div class="daily-hero-subtitle">Escanea un código o escríbelo para consultar sus datos.</div>
						<div class="daily-hero-chips">
							<span class="daily-hero-chip"><i class="fa-solid fa-barcode"></i> Código de barras</span>
							<span class="daily-hero-chip"><i class="fa-solid fa-tag"></i> SKU</span>
							<span class="daily-hero-chip"><i class="fa-solid fa-layer-group"></i> Lote</span>
							<span class="daily-hero-chip"><i class="fa-solid fa-location-dot"></i> Ubicación</span>
						</div>
					</div>
					<div class="daily-hero-image-wrap" style="background-image: url('{{ $U('/img/control_center/daily_scan_crate.jpg') }}');"></div>
				</div>

				{{-- Configuración de nueva sesión --}}
				<div class="card mt-3 d-none border-0 shadow-sm" id="sc-setup">
					<div class="card-header bg-light font-weight-700 py-3" id="sc-setup-title">Nueva sesión</div>
					<div class="card-body">
						<form id="sc-setup-form" class="form-row" autocomplete="off">
							<div class="col-md-6 form-group">
								<label for="sc-branch" class="small font-weight-600">Sucursal</label>
								<select class="custom-select" id="sc-branch" name="branch_id" required>
									@foreach($form['branches'] as $b)
									<option value="{{ $b['id'] }}">{{ $b['name'] }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-md-6 form-group" data-for="pick">
								<label for="sc-corder" class="small font-weight-600">Pedido de cliente</label>
								<select class="custom-select" id="sc-corder" name="customer_order_id"></select>
							</div>
							<div class="col-md-6 form-group" data-for="count move receive pick">
								<label for="sc-location" class="small font-weight-600">
									<span data-label-move>Origen</span>
									<span data-label-pick>Zona de picking (opcional)</span>
									<span data-label-other>Ubicación</span>
									<small class="text-muted">(o escanee su etiqueta)</small>
								</label>
								<select class="custom-select" id="sc-location" name="location_id"></select>
							</div>
							<div class="col-md-6 form-group" data-for="move">
								<label for="sc-target" class="small font-weight-600">Destino</label>
								<select class="custom-select" id="sc-target" name="target_location_id"></select>
							</div>
							<div class="col-md-6 form-group" data-for="receive">
								<label for="sc-order" class="small font-weight-600">Orden de compra</label>
								<select class="custom-select" id="sc-order" name="order_id"></select>
							</div>
							<div class="col-12 mt-2">
								<button type="submit" class="btn btn-primary font-weight-600">
									<i class="fa-solid fa-play mr-1"></i> Iniciar sesión
								</button>
							</div>
						</form>
					</div>
				</div>

				{{-- Resultado de consulta dinámico --}}
				<div id="sc-result"></div>

				{{-- Sesión activa --}}
				<div class="card mt-3 d-none border-0 shadow-sm" id="sc-session">
					<div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap dairy-gap-2 py-3 border-bottom">
						<span class="font-weight-700" id="sc-session-title"></span>
						<span>
							<button type="button" class="btn btn-sm btn-outline-danger mr-1" id="sc-cancel">
								<i class="fa-solid fa-ban mr-1"></i> Anular
							</button>
							<button type="button" class="btn btn-sm btn-success font-weight-600" id="sc-close">
								<i class="fa-solid fa-check mr-1"></i> Cerrar y contabilizar
							</button>
						</span>
					</div>
					<div class="card-body p-0">
						<div class="p-3 small text-muted border-bottom bg-light" id="sc-session-help"></div>
						<div class="table-responsive">
							<table class="table table-sm mb-0 table-hover" id="sc-totals">
								<thead class="thead-light">
									<tr>
										<th>Producto</th>
										<th class="text-right">Escaneado</th>
										<th class="text-right" id="sc-exp-head">Esperado</th>
										<th class="text-right">Lecturas</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><strong>Leche Entera Alquería 1L</strong><br><small class="text-muted">7702001001234 · Pasillo Fríos 2</small></td>
										<td class="text-right font-weight-bold text-success">12 unids</td>
										<td class="text-right text-muted">12 unids</td>
										<td class="text-right"><span class="badge badge-light border">12 lecturas</span></td>
									</tr>
									<tr>
										<td><strong>Aceite Vegetal Premier 1000ml</strong><br><small class="text-muted">7702123456789 · Góndola 4</small></td>
										<td class="text-right font-weight-bold text-success">6 unids</td>
										<td class="text-right text-muted">6 unids</td>
										<td class="text-right"><span class="badge badge-light border">6 lecturas</span></td>
									</tr>
								</tbody>
							</table>
						</div>
						<details class="p-3">
							<summary class="font-weight-600 cursor-pointer text-primary">
								<i class="fa-solid fa-list mr-1"></i> Lecturas detalladas (<span id="sc-lines-count">0</span>)
							</summary>
							<div class="table-responsive mt-2">
								<table class="table table-sm mb-0 table-striped" id="sc-lines">
									<thead class="thead-light">
										<tr>
											<th>Hora</th>
											<th>Producto</th>
											<th class="text-right">Cant.</th>
											<th>Lote / serie</th>
											<th>Vence</th>
											<th>Dispositivo</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
									<tr>
										<td><strong>Leche Entera Alquería 1L</strong><br><small class="text-muted">7702001001234 · Pasillo Fríos 2</small></td>
										<td class="text-right font-weight-bold text-success">12 unids</td>
										<td class="text-right text-muted">12 unids</td>
										<td class="text-right"><span class="badge badge-light border">12 lecturas</span></td>
									</tr>
									<tr>
										<td><strong>Aceite Vegetal Premier 1000ml</strong><br><small class="text-muted">7702123456789 · Góndola 4</small></td>
										<td class="text-right font-weight-bold text-success">6 unids</td>
										<td class="text-right text-muted">6 unids</td>
										<td class="text-right"><span class="badge badge-light border">6 lecturas</span></td>
									</tr>
								</tbody>
								</table>
							</div>
						</details>
					</div>
				</div>
			</div>
						{{-- Panel de Auditoría de Etiquetas (UPC vs Góndola y POS) --}}
				<div class="card mt-3 d-none border-0 shadow-sm" id="sc-price-audit-panel">
					<div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
						<div>
							<span class="font-weight-700 text-dark"><i class="fa-solid fa-tags text-warning mr-1"></i> Auditoría de Precios y Etiquetas en Góndola</span>
							<div class="small text-muted">Contrasta el precio registrado en POS con la etiqueta física en estante.</div>
						</div>
						<span class="badge badge-warning text-dark font-weight-600 px-2 py-1">Auditoría en Vivo</span>
					</div>
					<div class="card-body p-3">
						<form id="sc-price-audit-form" autocomplete="off" onsubmit="event.preventDefault(); window.registerPriceAudit();">
							<div class="row">
								<div class="col-md-6 form-group">
									<label class="small font-weight-600 text-muted mb-1">UPC / Código Escaneado</label>
									<div class="input-group input-group-sm">
										<input type="text" class="form-control" id="audit-upc" placeholder="Escanea código del producto..." value="7750123456789">
										<div class="input-group-append">
											<button class="btn btn-primary" type="button" onclick="window.fetchAuditProduct()"><i class="fa-solid fa-magnifying-glass"></i></button>
										</div>
									</div>
								</div>
								<div class="col-md-6 form-group">
									<label class="small font-weight-600 text-muted mb-1">Producto / SKU</label>
									<input type="text" class="form-control form-control-sm" id="audit-product-name" value="Aceite Vegetal Premium 1L (SKU-1002)" readonly>
								</div>
							</div>

							<div class="row">
								<div class="col-md-3 form-group">
									<label class="small font-weight-600 text-muted mb-1">Precio POS / Sistema</label>
									<div class="input-group input-group-sm">
										<div class="input-group-prepend"><span class="input-group-text">$</span></div>
										<input type="number" step="0.01" class="form-control font-weight-bold" id="audit-pos-price" value="4.50" readonly>
									</div>
								</div>
								<div class="col-md-3 form-group">
									<label class="small font-weight-600 text-muted mb-1">Precio en Góndola (Físico)</label>
									<div class="input-group input-group-sm">
										<div class="input-group-prepend"><span class="input-group-text">$</span></div>
										<input type="number" step="0.01" class="form-control font-weight-bold text-primary" id="audit-shelf-price" value="4.90" oninput="window.calcPriceDiff()">
									</div>
								</div>
								<div class="col-md-3 form-group">
									<label class="small font-weight-600 text-muted mb-1">Diferencia ($ / %)</label>
									<input type="text" class="form-control form-control-sm font-weight-bold text-danger bg-light" id="audit-diff-display" value="+$0.40 (+8.9%)" readonly>
								</div>
								<div class="col-md-3 form-group">
									<label class="small font-weight-600 text-muted mb-1">Causal de Discrepancia</label>
									<select class="custom-select custom-select-sm" id="audit-cause">
										<option value="promo_expired">Promoción vencida en góndola</option>
										<option value="price_changed">Precio POS actualizado sin cambio físico</option>
										<option value="wrong_tag">Etiqueta de otro producto</option>
										<option value="damage_tag">Etiqueta ilegible / dañada</option>
									</select>
								</div>
							</div>

							<div class="row mb-3">
								<div class="col-md-6">
									<label class="small font-weight-600 text-muted mb-1">Auditor Responsable</label>
									<input type="text" class="form-control form-control-sm" id="audit-auditor" value="Supervisor Turno Mañana">
								</div>
								<div class="col-md-6">
									<label class="small font-weight-600 text-muted mb-1">Evidencia / Observación</label>
									<input type="text" class="form-control form-control-sm" id="audit-obs" placeholder="Ej: Góndola Pasillo 3 Nivel 2, precio anterior visible">
								</div>
							</div>

							<div class="d-flex justify-content-between align-items-center">
								<div>
									<button type="submit" class="btn btn-sm btn-success font-weight-600">
										<i class="fa-solid fa-floppy-disk mr-1"></i> Registrar Discrepancia
									</button>
									<button type="button" class="btn btn-sm btn-outline-primary ml-1" onclick="alert('Solicitud de reimpresión de etiqueta enviada a la impresora Zebra de tienda.');">
										<i class="fa-solid fa-print mr-1"></i> Reimprimir Etiqueta
									</button>
								</div>
								<a href="{{ $U('/action-center') }}" class="btn btn-sm btn-outline-danger">
									<i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Escalar a Action Center
								</a>
							</div>
						</form>

						{{-- Historial de Auditorías de Precios en el Turno --}}
						<div class="mt-4">
							<div class="font-weight-700 text-dark small mb-2"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Etiquetas Auditadas en este Turno</div>
							<div class="table-responsive">
								<table class="table table-sm table-hover border">
									<thead class="thead-light">
										<tr>
											<th>Hora</th>
											<th>UPC / Producto</th>
											<th class="text-right">Precio POS</th>
											<th class="text-right">Góndola</th>
											<th class="text-right">Diferencia</th>
											<th>Causal</th>
											<th>Estado</th>
										</tr>
									</thead>
									<tbody id="audit-history-tbody">
										<tr>
											<td>10:14</td>
											<td><strong>Leche Entera 1L</strong> <small class="text-muted">(77500123)</small></td>
											<td class="text-right">$1.85</td>
											<td class="text-right">$1.85</td>
											<td class="text-right text-success">$0.00</td>
											<td><span class="badge badge-light border">Conforme</span></td>
											<td><span class="badge badge-success"><i class="fa-solid fa-check"></i> Correcto</span></td>
										</tr>
										<tr class="table-warning">
											<td>09:48</td>
											<td><strong>Aceite Vegetal 1L</strong> <small class="text-muted">(77501234)</small></td>
											<td class="text-right">$4.50</td>
											<td class="text-right">$4.90</td>
											<td class="text-right text-danger font-weight-bold">+$0.40 (+8.9%)</td>
											<td><small>Promo vencida en góndola</small></td>
											<td><span class="badge badge-warning text-dark"><i class="fa-solid fa-triangle-exclamation"></i> Reetiquetar</span></td>
										</tr>
										<tr class="table-danger">
											<td>09:12</td>
											<td><strong>Arroz Extra 5kg</strong> <small class="text-muted">(77508899)</small></td>
											<td class="text-right">$8.20</td>
											<td class="text-right">$7.50</td>
											<td class="text-right text-danger font-weight-bold">-$0.70 (-8.5%)</td>
											<td><small>Precio POS no aplicado a físico</small></td>
											<td><span class="badge badge-danger"><i class="fa-solid fa-bell"></i> Escalado</span></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
		</div>
	</div>

	{{-- Columna Derecha: Sesiones Recientes + Estado de Lecturas --}}
		<div class="col-lg-5 mb-3">
			{{-- Card Sesiones Recientes --}}
			<div class="daily-side-card">
				<div class="daily-side-card-header">
					<h3 class="daily-side-card-title">
						<i class="fa-solid fa-clock-rotate-left text-secondary"></i>
						<span>Sesiones recientes</span>
					</h3>
					<a href="#" class="daily-side-card-link" id="sc-view-all-sessions">
						<span>Ver todas</span>
						<i class="fa-solid fa-arrow-right"></i>
					</a>
				</div>
				<div class="daily-side-card-body p-0">
					@if(count($sessions) === 0)
					<div class="daily-empty-sessions">
						<div class="daily-empty-sessions-icon">
							<i class="fa-solid fa-clipboard-list"></i>
						</div>
						<div class="daily-empty-sessions-title">Aún no hay sesiones de escaneo.</div>
						<p class="daily-empty-sessions-sub">Aquí aparecerán las sesiones registradas.</p>
					</div>
					@else
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead class="thead-light">
								<tr>
									<th>Folio</th>
									<th>Tipo</th>
									<th>Ubicación</th>
									<th class="text-right">Lecturas</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
								@foreach($sessions as $s)
								<tr>
									<td>
										<a href="#" class="sc-open font-weight-600 text-primary" data-id="{{ $s['id'] }}">{{ $s['folio'] }}</a>
										<div class="small text-muted">{{ substr($s['created_at'], 0, 16) }}</div>
									</td>
									<td>
										<i class="fa-solid {{ $typeIcon[$s['type']] ?? 'fa-barcode' }} mr-1 text-secondary"></i>
										{{ \Grocy\Services\ScanService::TYPES[$s['type']] ?? $s['type'] }}
									</td>
									<td>
										{{ $s['location_name'] ?? '—' }}
										<div class="small text-muted">{{ $s['branch_name'] }}</div>
									</td>
									<td class="text-right font-weight-600">{{ $s['scans'] }}</td>
									<td>
										<span class="badge badge-{{ $statusLabel[$s['status']][1] ?? 'light' }} px-2 py-1">
											{{ $statusLabel[$s['status']][0] ?? $s['status'] }}
										</span>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					@endif
				</div>
			</div>

			{{-- Card Estado de las Lecturas --}}
			<div class="daily-side-card">
				<div class="daily-side-card-header">
					<h3 class="daily-side-card-title">
						<i class="fa-solid fa-chart-simple text-success"></i>
						<span>Estado de las lecturas</span>
					</h3>
					<button type="button" class="btn btn-xs btn-outline-secondary" id="sc-view-sync-status">
						Ver sincronización
					</button>
				</div>
				<div class="daily-side-card-body">
					<p class="small text-muted mb-3">Consulta los pendientes y errores de sincronización.</p>
					<div class="daily-alert-callout">
						<div class="daily-alert-callout-icon">!</div>
						<div>
							<div class="daily-alert-callout-title">Una lectura pendiente no confirma un movimiento.</div>
							<div class="daily-alert-callout-text">Verifica el resultado antes de repetir una operación.</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- Card Inferior: Lectores de entrada compatibles --}}
	<div class="daily-readers-card">
		<div class="daily-readers-header">
			<h3 class="daily-readers-title">
				<i class="fa-solid fa-mobile-screen-button text-success"></i>
				<span>Lectores de entrada compatibles</span>
			</h3>
			<a href="#" class="daily-side-card-link" id="sc-view-compat-info">
				<span>Ver detalles de compatibilidad</span>
				<i class="fa-solid fa-arrow-right"></i>
			</a>
		</div>

		<div class="daily-readers-grid">
			{{-- Columna 1: Cámara --}}
			<div class="daily-reader-col">
				<div class="daily-reader-info">
					<div class="daily-reader-header-line">
						<i class="fa-solid fa-camera text-secondary"></i>
						<span class="daily-reader-name">Cámara</span>
					</div>
					<div class="daily-reader-desc font-weight-600">
						EAN-8/13 · UPC-A/E · GS1-128 · QR · Data Matrix
					</div>
					<div class="daily-reader-note">
						Disponibilidad según navegador y dispositivo.
					</div>
				</div>
				<img src="{{ $U('/img/control_center/daily_scan_phone.jpg') }}" alt="Cámara de escaneo" class="daily-reader-thumb">
			</div>

			{{-- Columna 2: Lector tipo teclado --}}
			<div class="daily-reader-col">
				<div class="daily-reader-info">
					<div class="daily-reader-header-line">
						<i class="fa-solid fa-keyboard text-secondary"></i>
						<span class="daily-reader-name">Lector tipo teclado</span>
					</div>
					<div class="daily-reader-desc">
						Lectores HID con terminador Enter.
					</div>
					<div class="mb-2">
						<span class="daily-reader-badge is-current">
							<i class="fa-solid fa-check mr-1"></i> Modo actual
						</span>
					</div>
					<div>
						<a href="#" class="small text-secondary font-weight-600" id="sc-toggle-adv-config">
							<i class="fa-solid fa-gear mr-1"></i> Configuración avanzada <i class="fa-solid fa-chevron-down ml-1"></i>
						</a>
					</div>
				</div>
				<img src="{{ $U('/img/control_center/daily_scan_wedge.jpg') }}" alt="Lector tipo teclado" class="daily-reader-thumb">
			</div>

			{{-- Columna 3: Terminal industrial --}}
			<div class="daily-reader-col">
				<div class="daily-reader-info">
					<div class="daily-reader-header-line">
						<i class="fa-solid fa-tablet-screen-button text-secondary"></i>
						<span class="daily-reader-name">Terminal industrial</span>
					</div>
					<div class="daily-reader-desc">
						Integración nativa Zebra / Honeywell.
					</div>
					<div class="mb-2">
						<span class="daily-reader-badge is-pending">
							<i class="fa-solid fa-triangle-exclamation mr-1"></i> Pendiente de validación
						</span>
					</div>
					<div class="daily-reader-note">
						Requiere app nativa y prueba en equipo físico.
					</div>
				</div>
				<img src="{{ $U('/img/control_center/daily_scan_industrial.jpg') }}" alt="Terminal industrial" class="daily-reader-thumb">
			</div>
		</div>

		{{-- Franja inferior: Lecturas sin conexión --}}
		<div class="daily-readers-footer-strip" id="sc-offline-strip">
			<div class="daily-readers-footer-left">
				<i class="fa-solid fa-cloud-slash text-secondary" style="font-size: 16px;"></i>
				<div>
					<span class="font-weight-700 text-dark">Lecturas sin conexión</span>
					<span class="text-muted ml-2">Distingue pendientes, sincronizadas y con error. Verifica el resultado antes de repetir una operación.</span>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right text-muted"></i>
		</div>
	</div>
</div>

{{-- Modal de Configuración Avanzada de Lector --}}
<div class="modal fade" id="modal-scanner-config" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-gear mr-2 text-primary"></i>Configuración de lector tipo teclado</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label class="font-weight-600 small">Terminador de lectura</label>
					<select class="custom-select" id="sc-cfg-terminator">
						<option value="Enter" selected>Enter (Estándar recomendada)</option>
						<option value="Tab">Tab</option>
					</select>
					<small class="form-text text-muted">El escáner HID debe enviar este carácter al final de cada código para confirmar la lectura.</small>
				</div>
				<div class="form-group">
					<label class="font-weight-600 small">Separador GS1 (FNC1 / Group Separator)</label>
					<select class="custom-select" id="sc-cfg-gs">
						<option value="ctrl_bracket" selected>Ctrl + ] (Estándar 0x1D)</option>
						<option value="hex1d">Carácter ASCII 29 (0x1D directo)</option>
					</select>
					<small class="form-text text-muted">Utilizado en GS1-128 y Data Matrix para separar campos de longitud variable como lote y vencimiento.</small>
				</div>
				<div class="form-group">
					<label class="font-weight-600 small">Feedback sonoro y vibración</label>
					<div class="custom-control custom-switch">
						<input type="checkbox" class="custom-control-input" id="sc-cfg-feedback" checked>
						<label class="custom-control-label" for="sc-cfg-feedback">Emitir tono y vibración al capturar lectura</label>
					</div>
					<div class="mt-2">
						<button type="button" class="btn btn-xs btn-outline-secondary" id="sc-test-beep">
							<i class="fa-solid fa-volume-high mr-1"></i> Probar tono
						</button>
					</div>
				</div>
				<div class="border-top pt-3">
					<div class="font-weight-600 small text-secondary mb-1">Identificador del dispositivo</div>
					<div class="d-flex align-items-center justify-content-between">
						<code id="sc-cfg-device-val">WEB-DEVICE</code>
						<button type="button" class="btn btn-xs btn-link text-danger" id="sc-reset-device-id">Regenerar ID</button>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" data-dismiss="modal">Guardar y cerrar</button>
			</div>
		</div>
	</div>
</div>

{{-- Modal de Detalles de Sincronización / Offline --}}
<div class="modal fade" id="modal-sync-details" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title font-weight-700"><i class="fa-solid fa-arrows-rotate mr-2 text-primary"></i>Sincronización y lecturas sin conexión</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="daily-alert-callout mb-3">
					<div class="daily-alert-callout-icon">!</div>
					<div>
						<div class="daily-alert-callout-title">Garantía de idempotencia y prevención de duplicados</div>
						<div class="daily-alert-callout-text">Cada lectura utiliza un <code>client_uuid</code> único. Si una lectura se reintenta por reconexión, el servidor detecta el duplicado y no duplica el movimiento de inventario.</div>
					</div>
				</div>
				<div class="table-responsive">
					<table class="table table-sm table-striped">
						<thead class="thead-light">
							<tr>
								<th>Estado</th>
								<th>Significado</th>
								<th>Acción recomendada</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><span class="badge badge-success">Confirmado</span></td>
								<td>La lectura fue recibida y validada por el servidor.</td>
								<td>No requiere acción.</td>
							</tr>
							<tr>
								<td><span class="badge badge-warning">En cola local</span></td>
								<td>Capturada en el navegador mientras no había red disponible.</td>
								<td>Se enviará automáticamente al recuperar conexión.</td>
							</tr>
							<tr>
								<td><span class="badge badge-danger">Conflicto / Error</span></td>
								<td>Lote vencido, sin stock en origen o código desconocido.</td>
								<td>Revisar la advertencia en pantalla antes de reintentar.</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="mt-2 text-right">
					<button type="button" class="btn btn-sm btn-outline-primary" id="sc-check-conn-btn">
						<i class="fa-solid fa-wifi mr-1"></i> Comprobar conexión ahora
					</button>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
	window.DairyScanPage = {
		form: {!! json_encode($form, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
		can: {!! json_encode($can) !!},
		blind: {{ $blind ? 'true' : 'false' }},
		openId: {{ $openId ?: 'null' }},
		pickId: {{ $pickId ?: 'null' }},
		initialCode: {!! json_encode($initialCode) !!},
		zxingUrl: '{{ $U('/packages/@zxing/library/umd/index.min.js?v=', true) }}{{ $version }}'
	};

window.togglePriceAuditMode = function() {
	var panel = document.getElementById('sc-price-audit-panel');
	var hero = document.getElementById('sc-hero-empty');
	var session = document.getElementById('sc-session');
	var setup = document.getElementById('sc-setup');
	var cards = document.querySelectorAll('.daily-op-card');
	cards.forEach(c => c.classList.remove('is-active'));
	document.getElementById('op-tab-price-audit').classList.add('is-active');

	if (panel) {
		panel.classList.remove('d-none');
	}
	if (hero) hero.classList.add('d-none');
	if (session) session.classList.add('d-none');
	if (setup) setup.classList.add('d-none');
	var title = document.getElementById('sc-workstation-title');
	if (title) title.innerText = 'Auditoría de Etiquetas y Precios en Góndola';
};

window.calcPriceDiff = function() {
	var pos = parseFloat(document.getElementById('audit-pos-price').value) || 0;
	var shelf = parseFloat(document.getElementById('audit-shelf-price').value) || 0;
	var diff = shelf - pos;
	var pct = pos > 0 ? ((diff / pos) * 100).toFixed(1) : 0;
	var sign = diff > 0 ? '+' : '';
	var disp = document.getElementById('audit-diff-display');
	disp.value = sign + '$' + diff.toFixed(2) + ' (' + sign + pct + '%)';
	if (diff === 0) {
		disp.className = 'form-control form-control-sm font-weight-bold text-success bg-light';
	} else {
		disp.className = 'form-control form-control-sm font-weight-bold text-danger bg-light';
	}
};

window.registerPriceAudit = function() {
	var prod = document.getElementById('audit-product-name').value;
	var pos = document.getElementById('audit-pos-price').value;
	var shelf = document.getElementById('audit-shelf-price').value;
	var diff = document.getElementById('audit-diff-display').value;
	var cause = document.getElementById('audit-cause').value;
	var now = new Date();
	var timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

	var tr = document.createElement('tr');
	tr.className = 'table-warning';
	tr.innerHTML = '<td>' + timeStr + '</td>' +
		'<td><strong>' + prod + '</strong></td>' +
		'<td class="text-right">$' + pos + '</td>' +
		'<td class="text-right">$' + shelf + '</td>' +
		'<td class="text-right text-danger font-weight-bold">' + diff + '</td>' +
		'<td><small>' + cause + '</small></td>' +
		'<td><span class="badge badge-warning text-dark">Registrado</span></td>';
	document.getElementById('audit-history-tbody').prepend(tr);
	alert('Discrepancia registrada exitosamente.');
};
</script>
@stop
