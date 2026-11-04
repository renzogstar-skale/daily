@php
	// Preparar datos si no vienen seteados
	$products = $products ?? [];
	$reasons = $reasons ?? [];
	$branches = $branches ?? [];
	$locations = $locations ?? [];
	$users = $users ?? [];
	$destinations = \Grocy\Services\LossCensusService::DESTINATIONS;

	// Stock lots agrupados por product_id para llenado reactivo
	$dbRaw = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
	$rawLots = $dbRaw->query("
		SELECT s.id, s.product_id, s.amount, s.best_before_date, s.location_id
		FROM stock s
		ORDER BY s.best_before_date ASC
	")->fetchAll(\PDO::FETCH_ASSOC);

	$lotsByProduct = [];
	foreach ($rawLots as $l) {
		$lotsByProduct[$l['product_id']][] = [
			'id' => $l['id'],
			'amount' => floatval($l['amount']),
			'date' => $l['best_before_date'],
			'location_id' => $l['location_id']
		];
	}
@endphp

<div class="daily-registro-wrap" id="daily-registro-container">
	<style>
		.daily-registro-wrap {
			font-family: inherit;
			color: #1e293b;
		}
		.daily-registro-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			margin-bottom: 20px;
			overflow: hidden;
		}
		.daily-reg-header {
			padding: 16px 20px;
			display: flex;
			align-items: center;
			gap: 12px;
			border-bottom: 1px solid #f1f5f9;
		}
		.daily-reg-icon {
			width: 32px;
			height: 32px;
			border-radius: 8px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1rem;
			flex-shrink: 0;
		}
		.daily-reg-icon.green {
			background: rgba(0, 200, 59, 0.1);
			color: #00c83b;
		}
		.daily-reg-icon.red {
			background: rgba(220, 38, 38, 0.1);
			color: #dc2626;
		}
		.daily-reg-icon.amber {
			background: rgba(217, 119, 6, 0.1);
			color: #d97706;
		}
		.daily-reg-title {
			font-size: 1rem;
			font-weight: 700;
			color: #0f172a;
			margin: 0;
		}
		.daily-reg-sub {
			font-size: 0.78rem;
			color: #64748b;
			margin: 2px 0 0 0;
		}
		.daily-reg-body {
			padding: 20px;
		}

		/* Stepper */
		.daily-stepper-bar {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 14px 20px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 20px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			flex-wrap: wrap;
			gap: 12px;
		}
		.daily-step-item {
			display: flex;
			align-items: center;
			gap: 10px;
			cursor: pointer;
			user-select: none;
		}
		.daily-step-num {
			width: 28px;
			height: 28px;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 0.85rem;
			font-weight: 700;
			background: #f1f5f9;
			color: #64748b;
			transition: all 0.2s ease;
		}
		.daily-step-item.active .daily-step-num {
			background: #00c83b;
			color: #ffffff;
			box-shadow: 0 2px 6px rgba(0, 200, 59, 0.3);
		}
		.daily-step-item.completed .daily-step-num {
			background: rgba(0, 200, 59, 0.15);
			color: #00c83b;
		}
		.daily-step-title {
			font-size: 0.88rem;
			font-weight: 700;
			color: #0f172a;
			line-height: 1.2;
		}
		.daily-step-sub {
			font-size: 0.72rem;
			color: #64748b;
		}
		.daily-step-chevron {
			color: #cbd5e1;
			font-size: 0.85rem;
		}

		/* Categorías visuales */
		.daily-cat-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 12px;
			margin-bottom: 18px;
		}
		.daily-cat-btn {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 10px;
			overflow: hidden;
			text-align: center;
			cursor: pointer;
			transition: all 0.18s ease;
			padding: 0;
			position: relative;
		}
		.daily-cat-btn:hover {
			border-color: #00c83b;
			transform: translateY(-2px);
			box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
		}
		.daily-cat-btn.active {
			border-color: #00c83b;
			background: rgba(0, 200, 59, 0.04);
			box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.2);
		}
		.daily-cat-img {
			width: 100%;
			height: 52px;
			object-fit: cover;
			display: block;
		}
		.daily-cat-label {
			padding: 6px 4px;
			font-size: 0.78rem;
			font-weight: 700;
			color: #1e293b;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 4px;
		}

		/* Form inputs */
		.daily-form-label {
			font-size: 0.8rem;
			font-weight: 600;
			color: #334155;
			margin-bottom: 5px;
			display: flex;
			align-items: center;
			gap: 4px;
		}
		.daily-form-label .req {
			color: #dc2626;
			font-weight: 700;
		}
		.daily-form-control {
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 8px 12px;
			font-size: 0.88rem;
			color: #0f172a;
			background: #ffffff;
			width: 100%;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}
		.daily-form-control:focus {
			border-color: #00c83b;
			outline: none;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.15);
		}
		.daily-form-control[readonly],
		.daily-form-control:disabled {
			background: #f8fafc;
			color: #64748b;
			border-color: #e2e8f0;
			cursor: not-allowed;
		}

		/* Dropzone Evidencia */
		.daily-dropzone {
			border: 2px dashed #00c83b;
			background: rgba(0, 200, 59, 0.02);
			border-radius: 12px;
			padding: 24px 16px;
			text-align: center;
			cursor: pointer;
			transition: all 0.2s ease;
			position: relative;
		}
		.daily-dropzone:hover {
			background: rgba(0, 200, 59, 0.05);
			border-color: #00a832;
		}
		.daily-dropzone-icon {
			width: 44px;
			height: 44px;
			border-radius: 50%;
			background: rgba(0, 200, 59, 0.1);
			color: #00c83b;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-size: 1.25rem;
			margin-bottom: 8px;
		}
		.daily-dropzone-title {
			font-size: 0.88rem;
			font-weight: 700;
			color: #00c83b;
			margin-bottom: 2px;
		}
		.daily-dropzone-sub {
			font-size: 0.76rem;
			color: #64748b;
			margin-bottom: 4px;
		}
		.daily-dropzone-status {
			font-size: 0.74rem;
			color: #94a3b8;
		}
		.daily-dropzone-limit {
			font-size: 0.70rem;
			color: #94a3b8;
			margin-top: 8px;
		}

		/* Resumen Lateral */
		.daily-resumen-prod-box {
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 10px;
			padding: 12px;
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 16px;
		}
		.daily-resumen-icon-box {
			width: 42px;
			height: 42px;
			border-radius: 8px;
			background: #e2e8f0;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.2rem;
			color: #64748b;
			flex-shrink: 0;
			overflow: hidden;
		}
		.daily-resumen-icon-box img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}
		.daily-resumen-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 7px 0;
			border-bottom: 1px dashed #e2e8f0;
			font-size: 0.82rem;
		}
		.daily-resumen-row:last-child {
			border-bottom: none;
		}
		.daily-resumen-label {
			color: #64748b;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.daily-resumen-val {
			font-weight: 600;
			color: #0f172a;
			text-align: right;
		}

		/* Botones de acción */
		.daily-btn-green {
			background: #00c83b;
			color: #ffffff;
			border: none;
			border-radius: 8px;
			padding: 10px 22px;
			font-size: 0.88rem;
			font-weight: 700;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			transition: background 0.15s ease, transform 0.15s ease;
		}
		.daily-btn-green:hover:not(:disabled) {
			background: #00a832;
			transform: translateY(-1px);
			color: #ffffff;
			text-decoration: none;
		}
		.daily-btn-green:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}
		.daily-btn-outline {
			background: #ffffff;
			color: #475569;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 10px 20px;
			font-size: 0.88rem;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.daily-btn-outline:hover {
			background: #f8fafc;
			border-color: #94a3b8;
			color: #0f172a;
			text-decoration: none;
		}

		/* Autocomplete drop */
		.daily-autocomplete-results {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			background: #ffffff;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
			max-height: 240px;
			overflow-y: auto;
			z-index: 1050;
			display: none;
			margin-top: 4px;
		}
		.daily-autocomplete-item {
			padding: 8px 12px;
			border-bottom: 1px solid #f1f5f9;
			cursor: pointer;
			font-size: 0.84rem;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.daily-autocomplete-item:hover,
		.daily-autocomplete-item.selected {
			background: #f0fdf4;
			color: #166534;
		}

		/* Responsive */
		@media (max-width: 991px) {
			.daily-cat-grid {
				grid-template-columns: repeat(2, 1fr);
			}
			.daily-stepper-bar {
				flex-direction: column;
				align-items: flex-start;
				gap: 10px;
			}
			.daily-step-chevron {
				display: none;
			}
		}
	</style>

	<!-- BANNER DE DISTINCIÓN DE PROCESO (PROCESO A vs PROCESO B) -->
	<div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius:12px; border-left:4px solid #ef4444 !important;">
		<div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
			<div>
				<span class="badge badge-danger text-uppercase font-weight-bold px-2 py-1 mb-1" style="font-size:0.72rem;">Proceso A: Registro de Merma</span>
				<h5 class="font-weight-bold text-dark mb-0">Baja Definitiva y Reclasificación por Pérdida de Valor</h5>
				<small class="text-muted">
					Flujo operativo: Registro con evidencia → Validación de Supervisor → Aprobación de Gerente → Movimiento automático de stock → Afectación a Kardex → Cuadro de Control.
				</small>
			</div>
			<div class="mt-2 mt-md-0 d-flex align-items-center" style="gap:8px;">
				<a href="{{ $U('/merchandise') }}" class="btn btn-sm btn-outline-secondary font-weight-600" title="Ir a entradas, salidas, traslados y conteos habituales de stock">
					<i class="fa-solid fa-boxes-packing mr-1 text-primary"></i>Ir a Proceso B: Registro de Inventario
				</a>
				<a href="{{ $U('/kardex') }}" class="btn btn-sm btn-outline-info font-weight-600">
					<i class="fa-solid fa-book-open mr-1"></i>Ver Kardex
				</a>
			</div>
		</div>
	</div>

	<!-- BARRA DE PASOS (STEPPER) -->
	<div class="daily-stepper-bar">
		<div class="daily-step-item active" id="step-tab-1" onclick="dlxGoToStep(1)">
			<div class="daily-step-num" id="step-num-1">1</div>
			<div>
				<div class="daily-step-title">Producto y ubicación</div>
				<div class="daily-step-sub">Selecciona el producto y completa los datos</div>
			</div>
		</div>

		<div class="daily-step-chevron"><i class="fa-solid fa-angle-right"></i></div>

		<div class="daily-step-item" id="step-tab-2" onclick="dlxGoToStep(2)">
			<div class="daily-step-num" id="step-num-2">2</div>
			<div>
				<div class="daily-step-title">Causa y evidencia</div>
				<div class="daily-step-sub">Documenta el incidente</div>
			</div>
		</div>

		<div class="daily-step-chevron"><i class="fa-solid fa-angle-right"></i></div>

		<div class="daily-step-item" id="step-tab-3" onclick="dlxGoToStep(3)">
			<div class="daily-step-num" id="step-num-3">3</div>
			<div>
				<div class="daily-step-title">Revisión</div>
				<div class="daily-step-sub">Valida la información y registra</div>
			</div>
		</div>
	</div>

	<!-- VISTA PRINCIPAL (PASOS 1 Y 2) -->
	<div id="dlx-steps-container">
		<form id="dailyWasteReportForm" onsubmit="event.preventDefault(); dlxGoToStep(3);">
			<!-- Clave de idempotencia oculta -->
			<input type="hidden" id="reg-idempotency-key" name="idempotency_key" value="">
			<input type="hidden" id="reg-product-id" name="product_id" value="">
			<input type="hidden" id="reg-evidence-path" name="evidence_photo_path" value="">

			<div class="row">
				<!-- COLUMNA IZQUIERDA: FORMULARIOS 1 Y 2 -->
				<div class="col-lg-8">
					<!-- PASO 1: PRODUCTO Y UBICACIÓN -->
					<div class="daily-registro-card" id="card-step-1">
						<div class="daily-reg-header">
							<div class="daily-reg-icon green"><i class="fa-solid fa-box"></i></div>
							<div>
								<h6 class="daily-reg-title">1. Producto y ubicación</h6>
								<p class="daily-reg-sub">Selecciona el producto, la completa afectada y su ubicación.</p>
							</div>
						</div>
						<div class="daily-reg-body">
							<!-- Buscar producto o SKU -->
							<div class="form-group position-relative mb-3">
								<label class="daily-form-label">
									Buscar producto o SKU <span class="req">*</span>
								</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text bg-white border-right-0" style="border-radius: 8px 0 0 8px; border-color: #cbd5e1;">
											<i class="fa-solid fa-magnifying-glass text-muted"></i>
										</span>
									</div>
									<input type="text" id="reg-product-search" class="daily-form-control border-left-0" style="border-radius: 0 8px 8px 0;" placeholder="Buscar producto o SKU..." autocomplete="off">
								</div>
								<!-- Dropdown de resultados autocomplete -->
								<div class="daily-autocomplete-results" id="reg-autocomplete-list"></div>
							</div>

							<!-- Buscar por categoría -->
							<div class="mb-3">
								<label class="daily-form-label" style="font-size: 0.75rem; color: #64748b; margin-bottom: 6px;">
									Buscar por categoría
								</label>
								<div class="daily-cat-grid">
									<!-- Carne -->
									<div class="daily-cat-btn" onclick="dlxFilterCategory('carne', this)" title="Filtrar carnicería">
										<img src="{{ $U('/img/control_center/cat_carne.jpg') }}" class="daily-cat-img" alt="Carne">
										<div class="daily-cat-label">
											<i class="fa-solid fa-drumstick-bite text-danger"></i> Carne
										</div>
									</div>
									<!-- Pollo -->
									<div class="daily-cat-btn" onclick="dlxFilterCategory('pollo', this)" title="Filtrar pollo">
										<img src="{{ $U('/img/control_center/cat_pollo.jpg') }}" class="daily-cat-img" alt="Pollo">
										<div class="daily-cat-label">
											<i class="fa-solid fa-feather-pointed" style="color: #f59e0b;"></i> Pollo
										</div>
									</div>
									<!-- Fruver -->
									<div class="daily-cat-btn" onclick="dlxFilterCategory('fruver', this)" title="Filtrar frutas y verduras">
										<img src="{{ $U('/img/control_center/cat_fruver.jpg') }}" class="daily-cat-img" alt="Fruver">
										<div class="daily-cat-label">
											<i class="fa-solid fa-apple-whole text-success"></i> Fruver
										</div>
									</div>
									<!-- Lácteos -->
									<div class="daily-cat-btn" onclick="dlxFilterCategory('lacteos', this)" title="Filtrar lácteos">
										<img src="{{ $U('/img/control_center/cat_lacteos.jpg') }}" class="daily-cat-img" alt="Lácteos">
										<div class="daily-cat-label">
											<i class="fa-solid fa-cow" style="color: #8b5cf6;"></i> Lácteos
										</div>
									</div>
								</div>
							</div>

							<!-- Fila 1: Sucursal y Lote -->
							<div class="row">
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Sucursal <span class="req">*</span>
									</label>
									<select id="reg-branch-id" class="daily-form-control" onchange="dlxUpdateSummary();">
										@foreach($branches as $b)
											<option value="{{ $b->id }}">{{ $b->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Lote involucrado
									</label>
									<select id="reg-lot-select" class="daily-form-control mb-1" onchange="dlxOnLotSelected(this);">
										<option value="">Selecciona un producto primero</option>
									</select>
									<div class="d-flex align-items-center justify-content-between mt-1 mb-1">
										<small class="text-muted" style="font-size: 0.72rem;">o ingresa un lote manualmente</small>
									</div>
									<input type="text" id="reg-lot-manual" class="daily-form-control" placeholder="Ej: L-00123" oninput="dlxOnManualLotInput(this);">
								</div>
							</div>

							<!-- Fila 2: Cantidad y Peso -->
							<div class="row">
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Cantidad afectada <span class="req">*</span>
									</label>
									<div class="d-flex" style="gap: 8px;">
										<input type="number" step="any" min="0.001" id="reg-amount" class="daily-form-control font-weight-700" value="1" style="flex: 1;" oninput="dlxRecalculate();" required>
										<select id="reg-qu-select" class="daily-form-control" style="width: 120px;" onchange="dlxRecalculate();">
											<option value="Unidades">Unidades</option>
											<option value="kg">kg</option>
											<option value="litros">litros</option>
											<option value="paquetes">paquetes</option>
										</select>
									</div>
									<small class="form-text text-muted" id="reg-stock-hint" style="font-size: 0.72rem; margin-top: 3px;">
										Stock en sistema: —
									</small>
								</div>
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Peso (kg) <i class="fa-solid fa-circle-info text-muted ml-1" title="Requerido para pesables (carnes y fruver) o como dato complementario"></i>
									</label>
									<input type="number" step="0.001" min="0" id="reg-weight-kg" class="daily-form-control" placeholder="Ej: 2.50" oninput="dlxRecalculate();">
								</div>
							</div>

							<!-- Fila 3: Costo Unitario y Pérdida Total -->
							<div class="row">
								<div class="col-md-6 mb-2">
									<label class="daily-form-label">
										Costo unitario ($) <i class="fa-solid fa-circle-info text-muted ml-1" title="Costo unitario registrado según catálogo y última recepción"></i>
									</label>
									<input type="text" id="reg-unit-cost" class="daily-form-control" value="Pendiente de producto" readonly>
								</div>
								<div class="col-md-6 mb-2">
									<label class="daily-form-label">
										Pérdida total ($) <i class="fa-solid fa-circle-info text-muted ml-1" title="Cálculo monetario = cantidad × costo unitario (o peso para granel)"></i>
									</label>
									<input type="text" id="reg-total-cost" class="daily-form-control font-weight-700 text-danger" value="—" readonly>
								</div>
							</div>
						</div>
					</div>

					<!-- PASO 2: CAUSA Y EVIDENCIA -->
					<div class="daily-registro-card" id="card-step-2">
						<div class="daily-reg-header">
							<div class="daily-reg-icon green"><i class="fa-solid fa-file-lines"></i></div>
							<div>
								<h6 class="daily-reg-title">2. Causa y evidencia</h6>
								<p class="daily-reg-sub">Selecciona la causal, describe lo ocurrido y adjunta evidencia.</p>
							</div>
						</div>
						<div class="daily-reg-body">
							<!-- Fila 1: Causal Primaria y Destino Final -->
							<div class="row">
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Causal primaria <span class="req">*</span>
									</label>
									<select id="reg-reason-id" class="daily-form-control" onchange="dlxOnReasonChange(this);" required>
										<option value="">Selecciona una causal</option>
										@foreach($reasons as $r)
											<option value="{{ $r->id }}"
													data-code="{{ $r->code }}"
													data-category="{{ $r->category }}"
													data-evidence="{{ $r->requires_evidence }}"
													data-approval="{{ $r->requires_approval }}"
													data-risk="{{ $r->risk_level }}">
												[{{ $r->code }}] {{ $r->name }} ({{ $r->category }})
											</option>
										@endforeach
									</select>
									<div id="reg-reason-info" class="mt-1" style="display: none;">
										<span class="badge badge-light border" id="reg-reason-cat" style="font-size: 0.72rem;"></span>
										<span class="badge badge-warning" id="reg-reason-ev-badge" style="font-size: 0.72rem; display: none;">Evidencia obligatoria</span>
										<span class="badge badge-info" id="reg-reason-appr-badge" style="font-size: 0.72rem; display: none;">Requiere aprobación</span>
									</div>
								</div>
								<div class="col-md-6 mb-3">
									<label class="daily-form-label">
										Destino final <span class="req">*</span>
									</label>
									<select id="reg-destination" class="daily-form-control" onchange="dlxUpdateSummary();" required>
										<option value="">Pendiente de definir</option>
										@foreach($destinations as $dk => $dl)
											<option value="{{ $dk }}">{{ $dl }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<!-- Descripción detallada del incidente -->
							<div class="form-group mb-3">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<label class="daily-form-label mb-0">
										Descripción detallada del incidente <span class="req">*</span>
									</label>
									<span class="text-muted" style="font-size: 0.72rem;" id="reg-notes-counter">0/500</span>
								</div>
								<textarea id="reg-notes" class="daily-form-control" rows="3" maxlength="500" placeholder="Describe qué ocurrió y los hechos observados..." oninput="dlxOnNotesInput(this); dlxUpdateSummary();" required></textarea>
							</div>

							<!-- Evidencia fotográfica -->
							<div class="form-group mb-1">
								<label class="daily-form-label">
									Evidencia fotográfica
								</label>
								<div class="daily-dropzone" id="reg-dropzone" onclick="document.getElementById('reg-photo-input').click();">
									<input type="file" id="reg-photo-input" accept="image/jpeg,image/png,image/heic,image/webp" style="display: none;" onchange="dlxOnPhotoSelected(this);">
									<div class="daily-dropzone-icon">
										<i class="fa-solid fa-camera"></i>
									</div>
									<div class="daily-dropzone-title">Adjuntar fotos</div>
									<div class="daily-dropzone-sub">Arrastra y suelta archivos aquí o haz clic para seleccionar</div>
									<div class="daily-dropzone-status" id="reg-photo-status">Sin archivos adjuntos</div>
									<div class="daily-dropzone-limit">Formatos: JPG, PNG, HEIC. Tamaño máximo por archivo: 10 MB.</div>
								</div>

								<!-- Vista previa de foto cargada -->
								<div id="reg-photo-preview-wrap" style="display: none; margin-top: 10px; align-items: center; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px;">
									<img id="reg-photo-preview-img" src="" alt="Evidencia" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1;">
									<div style="flex: 1;">
										<div id="reg-photo-name" style="font-size: 0.8rem; font-weight: 600; color: #0f172a;"></div>
										<span class="badge badge-success" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-check mr-1"></i>Foto adjunta</span>
									</div>
									<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="dlxRemovePhoto();" title="Eliminar evidencia">
										<i class="fa-solid fa-trash-can mr-1"></i>Eliminar
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- BARRA INFERIOR DE ACCIONES -->
					<div class="d-flex flex-wrap justify-content-between align-items-center pt-2 pb-4">
						<div class="text-muted" style="font-size: 0.76rem; display: flex; flex-direction: column; gap: 2px;">
							<span><span class="text-danger font-weight-700">*</span> Campos obligatorios identificados con *</span>
							<span><i class="fa-solid fa-circle-info mr-1 text-primary"></i>Valida el lote y la existencia antes de enviar.</span>
						</div>
						<div class="d-flex align-items-center mt-2 mt-sm-0" style="gap: 10px;">
							<a href="{{ $U('/waste') }}" class="daily-btn-outline">Cancelar</a>
							<button type="submit" class="daily-btn-green" id="btn-goto-step-3">
								Revisar registro <i class="fa-solid fa-arrow-right ml-1"></i>
							</button>
						</div>
					</div>
				</div>

				<!-- COLUMNA DERECHA: RESUMEN LATERAL Y SIGUIENTE PASO -->
				<div class="col-lg-4">
					<!-- Card 1: Resumen del registro -->
					<div class="daily-registro-card">
						<div class="daily-reg-header">
							<div class="daily-reg-icon green"><i class="fa-solid fa-receipt"></i></div>
							<div>
								<h6 class="daily-reg-title">Resumen del registro</h6>
								<p class="daily-reg-sub">Revisa la información antes de continuar.</p>
							</div>
						</div>
						<div class="daily-reg-body">
							<!-- Caja de producto seleccionado -->
							<div class="daily-resumen-prod-box">
								<div class="daily-resumen-icon-box" id="reg-sum-icon">
									<i class="fa-solid fa-box"></i>
								</div>
								<div style="flex: 1; min-width: 0;">
									<div style="font-size: 0.72rem; color: #64748b;">Producto</div>
									<div class="font-weight-700 text-truncate" id="reg-sum-prod-name" style="font-size: 0.88rem; color: #0f172a;">
										Sin seleccionar
									</div>
									<small class="text-muted" id="reg-sum-prod-sku" style="font-size: 0.72rem;"></small>
								</div>
							</div>

							<!-- Filas de detalle -->
							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-store"></i> Sucursal</span>
								<span class="daily-resumen-val" id="reg-sum-branch">—</span>
							</div>

							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-barcode"></i> Lote</span>
								<span class="daily-resumen-val" id="reg-sum-lot">Pendiente de definir</span>
							</div>

							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-tag"></i> Cantidad</span>
								<span class="daily-resumen-val" id="reg-sum-amount">1 Unidades</span>
							</div>

							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-weight-scale"></i> Peso (kg)</span>
								<span class="daily-resumen-val" id="reg-sum-weight">—</span>
							</div>

							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-dollar-sign"></i> Costo unitario</span>
								<span class="daily-resumen-val" id="reg-sum-unit-cost">—</span>
							</div>

							<div class="daily-resumen-row">
								<span class="daily-resumen-label"><i class="fa-solid fa-calculator"></i> Pérdida total</span>
								<span class="daily-resumen-val text-danger font-weight-800" id="reg-sum-total-cost">—</span>
							</div>

							<!-- Alerta de datos pendientes -->
							<div class="mt-3 p-3 rounded" id="reg-sum-alert" style="background: #fffbeb; border: 1px solid #fde68a;">
								<div class="d-flex align-items-start" style="gap: 8px;">
									<div style="width: 20px; height: 20px; border-radius: 50%; background: #d97706; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 800; flex-shrink: 0;">!</div>
									<div>
										<div class="font-weight-700" style="font-size: 0.8rem; color: #92400e;">Faltan producto, causal y valoración</div>
										<div style="font-size: 0.74rem; color: #b45309;">No se ha registrado ningún movimiento.</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Card 2: Siguiente paso -->
					<div class="daily-registro-card">
						<div class="daily-reg-header">
							<div class="daily-reg-icon green"><i class="fa-solid fa-shield-halved"></i></div>
							<div>
								<h6 class="daily-reg-title">Siguiente paso</h6>
							</div>
						</div>
						<div class="daily-reg-body">
							<div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px;">
								<div class="d-flex align-items-center" style="gap: 10px;">
									<div class="daily-step-num" style="width: 24px; height: 24px; font-size: 0.75rem; background: #00c83b; color: #fff;">1</div>
									<span style="font-size: 0.82rem; font-weight: 600; color: #334155;">Revisa los datos del registro</span>
								</div>
								<div class="d-flex align-items-center" style="gap: 10px;">
									<div class="daily-step-num" style="width: 24px; height: 24px; font-size: 0.75rem; background: #e2e8f0; color: #64748b;">2</div>
									<span style="font-size: 0.82rem; font-weight: 600; color: #64748b;">Envía según tus permisos</span>
								</div>
								<div class="d-flex align-items-center" style="gap: 10px;">
									<div class="daily-step-num" style="width: 24px; height: 24px; font-size: 0.75rem; background: #e2e8f0; color: #64748b;">3</div>
									<span style="font-size: 0.82rem; font-weight: 600; color: #64748b;">Consulta el estado del caso</span>
								</div>
							</div>

							<div class="p-2.5 rounded d-flex align-items-center" style="background: #f0fdf4; border: 1px solid #bbf7d0; gap: 8px;">
								<i class="fa-solid fa-circle-info" style="color: #00c83b; font-size: 0.9rem;"></i>
								<span style="font-size: 0.74rem; color: #166534; line-height: 1.35;">
									El efecto en inventario depende de la validación y aprobación configuradas.
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- VISTA DEL PASO 3: REVISIÓN Y CONFIRMACIÓN -->
	<div id="dlx-step-3-review" style="display: none;">
		<div class="daily-registro-card p-4">
			<div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
				<div>
					<h5 class="font-weight-800 text-dark mb-1">
						<i class="fa-solid fa-clipboard-check text-success mr-2"></i>3. Revisión y Confirmación del Registro
					</h5>
					<p class="text-muted small mb-0">Verifica minuciosamente los datos antes de procesar el registro.</p>
				</div>
				<button type="button" class="daily-btn-outline" onclick="dlxGoToStep(1);">
					<i class="fa-solid fa-pen-to-square mr-1"></i>Modificar datos
				</button>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<div class="p-3 bg-light rounded border h-100">
						<h6 class="font-weight-700 text-dark mb-3"><i class="fa-solid fa-box text-primary mr-1"></i>Detalles del Producto</h6>
						<div class="d-flex align-items-center mb-3" style="gap: 12px;">
							<div class="daily-resumen-icon-box" id="rev-prod-icon" style="width: 50px; height: 50px;">
								<i class="fa-solid fa-box"></i>
							</div>
							<div>
								<div class="font-weight-700 text-dark" id="rev-prod-name" style="font-size: 1rem;">—</div>
								<div class="text-muted small" id="rev-prod-sku">SKU: —</div>
							</div>
						</div>
						<table class="table table-sm table-borderless small mb-0">
							<tr>
								<td class="text-muted pl-0" style="width: 40%;">Sucursal:</td>
								<td class="font-weight-600 text-dark" id="rev-branch">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Lote identificado:</td>
								<td class="font-weight-600 text-dark" id="rev-lot">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Cantidad a mermar:</td>
								<td class="font-weight-700 text-dark" id="rev-amount">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Peso registrado:</td>
								<td class="font-weight-600 text-dark" id="rev-weight">—</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="col-md-6 mb-3">
					<div class="p-3 bg-light rounded border h-100">
						<h6 class="font-weight-700 text-dark mb-3"><i class="fa-solid fa-tags text-warning mr-1"></i>Causal y Valoración</h6>
						<table class="table table-sm table-borderless small mb-2">
							<tr>
								<td class="text-muted pl-0" style="width: 40%;">Causal primaria:</td>
								<td class="font-weight-600 text-dark" id="rev-reason">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Destino propuesto:</td>
								<td class="font-weight-600 text-dark" id="rev-destination">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Costo unitario:</td>
								<td class="font-weight-600 text-dark" id="rev-unit-cost">—</td>
							</tr>
							<tr>
								<td class="text-muted pl-0">Impacto monetario:</td>
								<td class="font-weight-800 text-danger" style="font-size: 1.15rem;" id="rev-total-cost">—</td>
							</tr>
						</table>

						<div id="rev-approval-box" class="p-2 rounded mt-2 small" style="background: #f1f5f9;">
							<i class="fa-solid fa-shield-halved text-success mr-1"></i>
							<span id="rev-approval-text">Aprobación automática según causal.</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Descripción y Evidencia adjunta -->
			<div class="p-3 bg-light rounded border mb-4">
				<div class="row">
					<div class="col-md-8">
						<h6 class="font-weight-700 text-dark mb-2"><i class="fa-solid fa-align-left text-muted mr-1"></i>Hechos Circunstanciales</h6>
						<p class="small text-muted mb-0" id="rev-notes" style="white-space: pre-wrap; font-style: italic;">
							Sin descripción adicional.
						</p>
					</div>
					<div class="col-md-4 text-center border-left">
						<h6 class="font-weight-700 text-dark mb-2"><i class="fa-solid fa-camera text-muted mr-1"></i>Evidencia</h6>
						<div id="rev-photo-wrap">
							<span class="text-muted small">Sin fotografía</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Botones de Confirmación Final -->
			<div class="d-flex justify-content-between align-items-center pt-2">
				<button type="button" class="daily-btn-outline" onclick="dlxGoToStep(1);">
					<i class="fa-solid fa-arrow-left mr-1"></i>Volver a editar
				</button>

				<button type="button" class="daily-btn-green" id="btn-submit-final-waste" onclick="dlxSubmitRegistration();">
					<i class="fa-solid fa-check mr-1"></i>Registrar caso de merma
				</button>
			</div>
		</div>
	</div>

	<!-- CONFIRMACIÓN DE ÉXITO -->
	<div id="dlx-success-card" style="display: none;">
		<div class="daily-registro-card p-5 text-center">
			<div style="width: 60px; height: 60px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 16px;">
				<i class="fa-solid fa-check"></i>
			</div>
			<h4 class="font-weight-800 text-dark mb-2">¡Caso de Merma Registrado Exitosamente!</h4>
			<p class="text-muted mb-4" id="suc-message">El caso ha sido registrado en el sistema con trazabilidad completa.</p>

			<div class="d-inline-flex flex-wrap justify-content-center p-3 bg-light rounded border mb-4" style="gap: 24px; text-align: left;">
				<div>
					<span class="text-muted small">Número de Caso:</span><br>
					<strong class="text-dark font-weight-800" style="font-size: 1.1rem;" id="suc-case-num">CASO-2026-XXXX</strong>
				</div>
				<div>
					<span class="text-muted small">Estado del Flujo:</span><br>
					<span class="badge badge-success px-2 py-1" id="suc-status-badge">Aprobado</span>
				</div>
				<div>
					<span class="text-muted small">Pérdida Registrada:</span><br>
					<strong class="text-danger font-weight-800" style="font-size: 1.1rem;" id="suc-cost">$0.00</strong>
				</div>
			</div>

			<div>
				<a href="{{ $U('/waste') }}" class="daily-btn-outline mr-2">
					<i class="fa-solid fa-table-cells mr-1"></i>Ver en Centro de Control
				</a>
				<button type="button" class="daily-btn-green" onclick="dlxResetRegistration();">
					<i class="fa-solid fa-plus mr-1"></i>Registrar otra merma
				</button>
			</div>
		</div>
	</div>
</div>

<!-- DATA STORE & SCRIPTING -->
<script>
(function() {
	// Catálogo de productos en memoria
	var rawProducts = @json($products);
	var stockLots = @json($lotsByProduct);
	var currentSelectedProduct = null;
	var currentActiveCategory = null;
	var uploadedPhotoUrl = null;
	var currentStep = 1;

	// Generar UUID v4 para idempotencia
	function generateUUID() {
		return 'idemp-' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
			var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
			return v.toString(16);
		}) + '-' + Date.now();
	}

	function initIdempotency() {
		var keyInput = document.getElementById('reg-idempotency-key');
		if (keyInput) {
			keyInput.value = generateUUID();
		}
	}

	// Filtrado y Autocomplete de productos
	var searchInput = document.getElementById('reg-product-search');
	var resultsList = document.getElementById('reg-autocomplete-list');

	function renderProductResults(list) {
		resultsList.innerHTML = '';
		if (!list || list.length === 0) {
			resultsList.innerHTML = '<div class="p-3 text-muted text-center small">No se encontraron productos coincidentes.</div>';
			resultsList.style.display = 'block';
			return;
		}
		list.slice(0, 15).forEach(function(p) {
			var item = document.createElement('div');
			item.className = 'daily-autocomplete-item';
			var priceTxt = (p.last_price > 0) ? '$' + Number(p.last_price).toFixed(2) : 'Valoración pendiente';
			item.innerHTML = '<div>'
				+ '<strong>' + p.name + '</strong>'
				+ '<div class="text-muted" style="font-size: 0.72rem;">SKU: ' + p.sku + ' · Stock: ' + p.current_stock + ' ' + p.qu_name + '</div>'
				+ '</div>'
				+ '<span class="badge badge-light border font-weight-700">' + priceTxt + '</span>';
			item.onclick = function() {
				dlxSelectProduct(p);
				resultsList.style.display = 'none';
			};
			resultsList.appendChild(item);
		});
		resultsList.style.display = 'block';
	}

	if (searchInput) {
		searchInput.addEventListener('input', function() {
			var term = this.value.trim().toLowerCase();
			var filtered = rawProducts.filter(function(p) {
				var matchesTerm = !term || p.name.toLowerCase().indexOf(term) !== -1 || p.sku.toLowerCase().indexOf(term) !== -1;
				var matchesCat = true;
				if (currentActiveCategory) {
					var g = (p.group_name || '').toLowerCase();
					if (currentActiveCategory === 'carne') matchesCat = g.indexOf('carnicería') !== -1 || p.name.toLowerCase().indexOf('res') !== -1 || p.name.toLowerCase().indexOf('carne') !== -1;
					else if (currentActiveCategory === 'pollo') matchesCat = p.name.toLowerCase().indexOf('pollo') !== -1;
					else if (currentActiveCategory === 'fruver') matchesCat = g.indexOf('verduras') !== -1 || g.indexOf('frutas') !== -1 || p.name.toLowerCase().indexOf('tomate') !== -1 || p.name.toLowerCase().indexOf('pimentón') !== -1;
					else if (currentActiveCategory === 'lacteos') matchesCat = g.indexOf('lácteo') !== -1 || g.indexOf('leche') !== -1 || g.indexOf('queso') !== -1 || g.indexOf('yogur') !== -1;
				}
				return matchesTerm && matchesCat;
			});
			renderProductResults(filtered);
		});

		searchInput.addEventListener('focus', function() {
			if (!this.value && !currentActiveCategory) {
				renderProductResults(rawProducts.slice(0, 10));
			} else {
				this.dispatchEvent(new Event('input'));
			}
		});

		document.addEventListener('click', function(e) {
			if (!resultsList.contains(e.target) && e.target !== searchInput) {
				resultsList.style.display = 'none';
			}
		});
	}

	window.dlxFilterCategory = function(cat, btn) {
		var buttons = document.querySelectorAll('.daily-cat-btn');
		if (currentActiveCategory === cat) {
			currentActiveCategory = null;
			buttons.forEach(function(b) { b.classList.remove('active'); });
		} else {
			currentActiveCategory = cat;
			buttons.forEach(function(b) { b.classList.remove('active'); });
			btn.classList.add('active');
		}
		if (searchInput) {
			searchInput.dispatchEvent(new Event('input'));
			searchInput.focus();
		}
	};

	window.dlxSelectProduct = function(p) {
		currentSelectedProduct = p;
		document.getElementById('reg-product-id').value = p.id;
		document.getElementById('reg-product-search').value = p.name + ' (' + p.sku + ')';

		// Qu select
		var quSel = document.getElementById('reg-qu-select');
		if (quSel) {
			var optExists = Array.from(quSel.options).some(function(o) { return o.value.toLowerCase() === (p.qu_name || '').toLowerCase(); });
			if (!optExists && p.qu_name) {
				var newOpt = new Option(p.qu_name, p.qu_name, true, true);
				quSel.add(newOpt);
			}
			quSel.value = p.qu_name || 'Unidades';
		}

		// Hint stock
		var stockHint = document.getElementById('reg-stock-hint');
		if (stockHint) {
			stockHint.innerHTML = 'Stock disponible en sistema: <strong class="text-dark">' + p.current_stock + ' ' + (p.qu_name || 'un.') + '</strong>';
		}

		// Llenar lotes disponibles
		var lotSel = document.getElementById('reg-lot-select');
		lotSel.innerHTML = '<option value="">-- Seleccionar lote disponible --</option>';
		var lots = stockLots[p.id] || [];
		if (lots.length > 0) {
			lots.forEach(function(l, i) {
				var opt = document.createElement('option');
				opt.value = 'L-STOCK-' + l.id;
				opt.setAttribute('data-date', l.date || '');
				opt.setAttribute('data-amount', l.amount);
				opt.textContent = 'Lote #' + l.id + ' — Vence: ' + (l.date || 'S/F') + ' (' + l.amount + ' ' + p.qu_name + ')';
				lotSel.appendChild(opt);
			});
			// Preseleccionar el primer lote FEFO
			lotSel.selectedIndex = 1;
			dlxOnLotSelected(lotSel);
		} else {
			lotSel.innerHTML = '<option value="">Sin lotes registrados (ingreso manual abajo)</option>';
			document.getElementById('reg-lot-manual').value = 'L-' + (Math.floor(1000 + Math.random() * 9000));
			dlxOnManualLotInput(document.getElementById('reg-lot-manual'));
		}

		// Preseleccionar peso sugerido si es pesable (ej: carne, fruver)
		var g = (p.group_name || '').toLowerCase();
		var isPesable = (p.qu_name && p.qu_name.toLowerCase().indexOf('kg') !== -1) || g.indexOf('carnicería') !== -1 || g.indexOf('verduras') !== -1;
		if (isPesable && !document.getElementById('reg-weight-kg').value) {
			document.getElementById('reg-weight-kg').value = '1.000';
		}

		dlxRecalculate();
		dlxUpdateSummary();
	};

	window.dlxOnLotSelected = function(sel) {
		var opt = sel.options[sel.selectedIndex];
		if (opt && opt.value) {
			document.getElementById('reg-lot-manual').value = '';
		}
		dlxUpdateSummary();
	};

	window.dlxOnManualLotInput = function(inp) {
		if (inp.value) {
			document.getElementById('reg-lot-select').value = '';
		}
		dlxUpdateSummary();
	};

	window.dlxRecalculate = function() {
		if (!currentSelectedProduct) {
			document.getElementById('reg-unit-cost').value = 'Pendiente de producto';
			document.getElementById('reg-total-cost').value = '—';
			dlxUpdateSummary();
			return;
		}

		var price = parseFloat(currentSelectedProduct.last_price) || 0;
		var amount = parseFloat(document.getElementById('reg-amount').value) || 0;
		var total = amount * price;

		if (price > 0) {
			document.getElementById('reg-unit-cost').value = '$' + price.toFixed(2) + ' / ' + (currentSelectedProduct.qu_name || 'u');
			document.getElementById('reg-total-cost').value = '$' + total.toFixed(2);
		} else {
			document.getElementById('reg-unit-cost').value = 'Valoración pendiente ($0.00)';
			document.getElementById('reg-total-cost').value = 'Valoración pendiente';
		}

		dlxUpdateSummary();
	};

	window.dlxOnReasonChange = function(sel) {
		var opt = sel.options[sel.selectedIndex];
		var infoBox = document.getElementById('reg-reason-info');
		var catBadge = document.getElementById('reg-reason-cat');
		var evBadge = document.getElementById('reg-reason-ev-badge');
		var appBadge = document.getElementById('reg-reason-appr-badge');

		if (opt && opt.value) {
			infoBox.style.display = 'block';
			catBadge.textContent = opt.getAttribute('data-category') || 'General';
			evBadge.style.display = (opt.getAttribute('data-evidence') === '1') ? 'inline-block' : 'none';
			appBadge.style.display = (opt.getAttribute('data-approval') === '1') ? 'inline-block' : 'none';
		} else {
			infoBox.style.display = 'none';
		}
		dlxUpdateSummary();
	};

	window.dlxOnNotesInput = function(ta) {
		document.getElementById('reg-notes-counter').textContent = ta.value.length + '/500';
	};

	// Manejo de Dropzone y Carga de Fotos
	var dropzone = document.getElementById('reg-dropzone');
	if (dropzone) {
		['dragenter', 'dragover'].forEach(function(eventName) {
			dropzone.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); dropzone.style.background = 'rgba(0,200,59,0.08)'; }, false);
		});
		['dragleave', 'drop'].forEach(function(eventName) {
			dropzone.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); dropzone.style.background = 'rgba(0,200,59,0.02)'; }, false);
		});
		dropzone.addEventListener('drop', function(e) {
			var dt = e.dataTransfer;
			var files = dt.files;
			if (files && files[0]) {
				document.getElementById('reg-photo-input').files = files;
				dlxOnPhotoSelected(document.getElementById('reg-photo-input'));
			}
		});
	}

	window.dlxOnPhotoSelected = function(inp) {
		if (inp.files && inp.files[0]) {
			var file = inp.files[0];
			if (file.size > 10 * 1024 * 1024) {
				toastr.error('El archivo supera el límite de 10 MB');
				inp.value = '';
				return;
			}

			// Previsualización inmediata
			var reader = new FileReader();
			reader.onload = function(e) {
				document.getElementById('reg-photo-preview-img').src = e.target.result;
				document.getElementById('reg-photo-name').textContent = file.name;
				document.getElementById('reg-photo-preview-wrap').style.display = 'flex';
				document.getElementById('reg-photo-status').textContent = '1 archivo seleccionado (' + (file.size / 1024).toFixed(0) + ' KB)';
			};
			reader.readAsDataURL(file);

			// Subida automática al servidor
			var formData = new FormData();
			formData.append('photo', file);

			Grocy.FrontendHelpers.BeginUiBusy();
			fetch(Grocy.BaseUrl + '/api/waste/upload-photo', {
				method: 'POST',
				body: formData
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				Grocy.FrontendHelpers.EndUiBusy();
				if (data.success) {
					uploadedPhotoUrl = data.relative_url;
					document.getElementById('reg-evidence-path').value = uploadedPhotoUrl;
					toastr.success('Fotografía de evidencia subida correctamente');
					dlxUpdateSummary();
				} else {
					toastr.error(data.error_message || 'Error al subir la fotografía');
				}
			})
			.catch(function(err) {
				Grocy.FrontendHelpers.EndUiBusy();
				toastr.error('Error al conectar para subir foto: ' + err);
			});
		}
	};

	window.dlxRemovePhoto = function() {
		uploadedPhotoUrl = null;
		document.getElementById('reg-evidence-path').value = '';
		document.getElementById('reg-photo-input').value = '';
		document.getElementById('reg-photo-preview-wrap').style.display = 'none';
		document.getElementById('reg-photo-status').textContent = 'Sin archivos adjuntos';
		dlxUpdateSummary();
	};

	// Actualización reactiva del Resumen Lateral
	window.dlxUpdateSummary = function() {
		var branchSel = document.getElementById('reg-branch-id');
		var branchName = branchSel.options[branchSel.selectedIndex] ? branchSel.options[branchSel.selectedIndex].text : '—';
		document.getElementById('reg-sum-branch').textContent = branchName;

		var lotSel = document.getElementById('reg-lot-select');
		var lotManual = document.getElementById('reg-lot-manual').value.trim();
		var lotTxt = lotManual || (lotSel.value ? (lotSel.options[lotSel.selectedIndex] ? lotSel.options[lotSel.selectedIndex].text.split('—')[0].trim() : lotSel.value) : 'Pendiente de definir');
		document.getElementById('reg-sum-lot').textContent = lotTxt;

		var amountVal = document.getElementById('reg-amount').value || 1;
		var quVal = document.getElementById('reg-qu-select').value || 'Unidades';
		document.getElementById('reg-sum-amount').textContent = amountVal + ' ' + quVal;

		var weightVal = document.getElementById('reg-weight-kg').value;
		document.getElementById('reg-sum-weight').textContent = weightVal ? weightVal + ' kg' : '—';

		if (currentSelectedProduct) {
			document.getElementById('reg-sum-prod-name').textContent = currentSelectedProduct.name;
			document.getElementById('reg-sum-prod-sku').textContent = 'SKU: ' + currentSelectedProduct.sku;
			var iconBox = document.getElementById('reg-sum-icon');
			if (currentSelectedProduct.picture_file_name) {
				iconBox.innerHTML = '<img src="' + Grocy.BaseUrl + '/api/files/productpictures/' + btoa(currentSelectedProduct.picture_file_name) + '">';
			} else {
				iconBox.innerHTML = '<i class="fa-solid fa-box text-success"></i>';
			}

			var price = parseFloat(currentSelectedProduct.last_price) || 0;
			document.getElementById('reg-sum-unit-cost').textContent = price > 0 ? '$' + price.toFixed(2) : 'Valoración pendiente';
			document.getElementById('reg-sum-total-cost').textContent = price > 0 ? '$' + (amountVal * price).toFixed(2) : 'Valoración pendiente';
		} else {
			document.getElementById('reg-sum-prod-name').textContent = 'Sin seleccionar';
			document.getElementById('reg-sum-prod-sku').textContent = '';
			document.getElementById('reg-sum-icon').innerHTML = '<i class="fa-solid fa-box"></i>';
			document.getElementById('reg-sum-unit-cost').textContent = '—';
			document.getElementById('reg-sum-total-cost').textContent = '—';
		}

		// Chequear alert box
		var rId = document.getElementById('reg-reason-id').value;
		var alertBox = document.getElementById('reg-sum-alert');
		if (!currentSelectedProduct || !rId) {
			alertBox.style.display = 'block';
		} else {
			alertBox.style.display = 'none';
		}
	};

	// Navegación entre pasos
	window.dlxGoToStep = function(step) {
		if (step === 3) {
			// Validaciones antes de ir a revisión
			if (!currentSelectedProduct) {
				toastr.warning('Por favor selecciona un producto afectado.');
				document.getElementById('reg-product-search').focus();
				return;
			}
			var reasonSel = document.getElementById('reg-reason-id');
			if (!reasonSel.value) {
				toastr.warning('Por favor selecciona la causal primaria.');
				reasonSel.focus();
				return;
			}
			var opt = reasonSel.options[reasonSel.selectedIndex];
			var reqEv = opt && opt.getAttribute('data-evidence') === '1';
			if (reqEv && !uploadedPhotoUrl) {
				toastr.error('Esta causal exige fotografía de evidencia obligatoria.');
				document.getElementById('reg-dropzone').scrollIntoView({ behavior: 'smooth', block: 'center' });
				return;
			}
			var notesVal = document.getElementById('reg-notes').value.trim();
			if (!notesVal) {
				toastr.warning('Por favor proporciona una descripción detallada del incidente.');
				document.getElementById('reg-notes').focus();
				return;
			}

			// Llenar panel de revisión
			dlxPopulateReview();
			document.getElementById('dlx-steps-container').style.display = 'none';
			document.getElementById('dlx-step-3-review').style.display = 'block';
			document.getElementById('dlx-success-card').style.display = 'none';
		} else {
			document.getElementById('dlx-steps-container').style.display = 'block';
			document.getElementById('dlx-step-3-review').style.display = 'none';
			document.getElementById('dlx-success-card').style.display = 'none';

			if (step === 1) {
				document.getElementById('card-step-1').scrollIntoView({ behavior: 'smooth', block: 'start' });
			} else if (step === 2) {
				document.getElementById('card-step-2').scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		// Actualizar estado visual de los tabs del stepper
		currentStep = step;
		for (var s = 1; s <= 3; s++) {
			var tab = document.getElementById('step-tab-' + s);
			var num = document.getElementById('step-num-' + s);
			tab.classList.remove('active', 'completed');
			if (s === step) {
				tab.classList.add('active');
				num.innerHTML = s;
			} else if (s < step) {
				tab.classList.add('completed');
				num.innerHTML = '<i class="fa-solid fa-check"></i>';
			} else {
				num.innerHTML = s;
			}
		}
	};

	function dlxPopulateReview() {
		document.getElementById('rev-prod-name').textContent = currentSelectedProduct.name;
		document.getElementById('rev-prod-sku').textContent = 'SKU: ' + currentSelectedProduct.sku;

		var revIcon = document.getElementById('rev-prod-icon');
		if (currentSelectedProduct.picture_file_name) {
			revIcon.innerHTML = '<img src="' + Grocy.BaseUrl + '/api/files/productpictures/' + btoa(currentSelectedProduct.picture_file_name) + '">';
		} else {
			revIcon.innerHTML = '<i class="fa-solid fa-box text-success"></i>';
		}

		var bSel = document.getElementById('reg-branch-id');
		document.getElementById('rev-branch').textContent = bSel.options[bSel.selectedIndex].text;

		var lotSel = document.getElementById('reg-lot-select');
		var lotManual = document.getElementById('reg-lot-manual').value.trim();
		document.getElementById('rev-lot').textContent = lotManual || (lotSel.value ? lotSel.options[lotSel.selectedIndex].text : 'Sin lote específico');

		var amountVal = document.getElementById('reg-amount').value;
		var quVal = document.getElementById('reg-qu-select').value;
		document.getElementById('rev-amount').textContent = amountVal + ' ' + quVal;

		var weightVal = document.getElementById('reg-weight-kg').value;
		document.getElementById('rev-weight').textContent = weightVal ? weightVal + ' kg' : 'No especificado';

		var reasonSel = document.getElementById('reg-reason-id');
		var rOpt = reasonSel.options[reasonSel.selectedIndex];
		document.getElementById('rev-reason').textContent = rOpt ? rOpt.text : '—';

		var destSel = document.getElementById('reg-destination');
		var dOpt = destSel.options[destSel.selectedIndex];
		document.getElementById('rev-destination').textContent = dOpt && dOpt.value ? dOpt.text : 'Pendiente de definir';

		var price = parseFloat(currentSelectedProduct.last_price) || 0;
		document.getElementById('rev-unit-cost').textContent = price > 0 ? '$' + price.toFixed(2) : 'Valoración pendiente';
		document.getElementById('rev-total-cost').textContent = price > 0 ? '$' + (amountVal * price).toFixed(2) : 'Valoración pendiente';

		var reqApproval = rOpt && rOpt.getAttribute('data-approval') === '1';
		var apprBox = document.getElementById('rev-approval-box');
		var apprText = document.getElementById('rev-approval-text');
		if (reqApproval) {
			apprBox.style.background = '#fffbeb';
			apprBox.style.color = '#92400e';
			apprText.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1 text-warning"></i><strong>Requiere aprobación de supervisor</strong> antes de que el inventario sea ajustado contablemente.';
		} else {
			apprBox.style.background = '#f0fdf4';
			apprBox.style.color = '#166534';
			apprText.innerHTML = '<i class="fa-solid fa-circle-check mr-1 text-success"></i><strong>Aprobación inmediata:</strong> Las existencias se descontarán automáticamente tras el envío.';
		}

		document.getElementById('rev-notes').textContent = document.getElementById('reg-notes').value || 'Sin notas.';

		var photoWrap = document.getElementById('rev-photo-wrap');
		if (uploadedPhotoUrl) {
			photoWrap.innerHTML = '<img src="' + Grocy.BaseUrl + uploadedPhotoUrl + '" class="img-thumbnail" style="max-height: 80px; border-radius: 6px;">';
		} else {
			photoWrap.innerHTML = '<span class="text-muted small">Sin fotografía de evidencia</span>';
		}
	}

	// Envío final mediante API
	window.dlxSubmitRegistration = function() {
		var submitBtn = document.getElementById('btn-submit-final-waste');
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Registrando Caso...';

		var lotSel = document.getElementById('reg-lot-select');
		var lotManual = document.getElementById('reg-lot-manual').value.trim();
		var batchVal = lotManual || (lotSel.value ? lotSel.value.replace('L-STOCK-', '') : null);

		var payload = {
			product_id: parseInt(document.getElementById('reg-product-id').value),
			amount: parseFloat(document.getElementById('reg-amount').value),
			branch_id: parseInt(document.getElementById('reg-branch-id').value),
			waste_reason_id: parseInt(document.getElementById('reg-reason-id').value),
			batch_number: batchVal,
			final_destination: document.getElementById('reg-destination').value || null,
			weight_kg: document.getElementById('reg-weight-kg').value ? parseFloat(document.getElementById('reg-weight-kg').value) : null,
			notes: document.getElementById('reg-notes').value,
			evidence_photo_path: uploadedPhotoUrl,
			idempotency_key: document.getElementById('reg-idempotency-key').value,
			confirm_duplicate: !!window.dairyConfirmDuplicate
		};
		window.dairyConfirmDuplicate = false;

		Grocy.FrontendHelpers.BeginUiBusy();
		fetch(Grocy.BaseUrl + '/api/waste/report', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json'
			},
			body: JSON.stringify(payload)
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			Grocy.FrontendHelpers.EndUiBusy();
			submitBtn.disabled = false;
			submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Registrar caso de merma';

			if (data.success) {
				toastr.success(data.message || 'Caso de merma registrado correctamente');
				document.getElementById('dlx-step-3-review').style.display = 'none';
				document.getElementById('dlx-success-card').style.display = 'block';

				document.getElementById('suc-message').textContent = data.message;
				document.getElementById('suc-case-num').textContent = data.case_number || ('CASO #' + data.waste_id);
				document.getElementById('suc-cost').textContent = '$' + Number(data.total_cost || 0).toFixed(2);

				var badge = document.getElementById('suc-status-badge');
				if (data.status === 'approved') {
					badge.className = 'badge badge-success px-2 py-1';
					badge.textContent = 'Aprobado y descontado';
				} else {
					badge.className = 'badge badge-warning px-2 py-1';
					badge.textContent = 'Pendiente de aprobación';
				}
			} else if ((data.error_message || '').indexOf('Posible duplicado') === 0) {
				bootbox.confirm({
					title: '¿Confirmar registro duplicado?',
					message: $('<div>').text(data.error_message).html(),
					buttons: {
						confirm: { label: 'Registrar de todos modos', className: 'btn-danger' },
						cancel: { label: 'Cancelar' }
					},
					callback: function(ok) {
						if (ok) {
							window.dairyConfirmDuplicate = true;
							dlxSubmitRegistration();
						}
					}
				});
			} else {
				toastr.error(data.error_message || 'Error al registrar el caso');
			}
		})
		.catch(function(err) {
			Grocy.FrontendHelpers.EndUiBusy();
			submitBtn.disabled = false;
			submitBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Registrar caso de merma';
			toastr.error('Error de comunicación con el servidor: ' + err);
		});
	};

	window.dlxResetRegistration = function() {
		initIdempotency();
		currentSelectedProduct = null;
		uploadedPhotoUrl = null;
		document.getElementById('dailyWasteReportForm').reset();
		dlxRemovePhoto();
		dlxGoToStep(1);
		dlxUpdateSummary();
	};

	// Inicializar al cargar
	document.addEventListener('DOMContentLoaded', function() {
		initIdempotency();
		dlxUpdateSummary();

		// Si viene product_id por query string
		var pid = new URLSearchParams(window.location.search).get('product_id');
		if (pid) {
			var p = rawProducts.find(function(item) { return item.id == pid; });
			if (p) {
				dlxSelectProduct(p);
			}
		}
	});

	// Iniciar de inmediato también por si ya está cargado el DOM
	initIdempotency();
	dlxUpdateSummary();
})();
</script>
