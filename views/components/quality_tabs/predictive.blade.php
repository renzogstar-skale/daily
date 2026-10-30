{{-- DAILY: Tab de Prevención Predictiva de Vencimientos y Control FEFO --}}
@php
	$alertsList = $predictiveAlerts ?? [];
	$rulesList = $categoryRules ?? [];
	$totalUnitsAtRisk = 0;
	$totalValueAtRisk = 0.0;
	$pendingResolution = 0;
	$closedCount = 0;
	foreach ($alertsList as $al) {
		$totalUnitsAtRisk += intval($al['units_at_risk'] ?? 0);
		$totalValueAtRisk += floatval($al['total_value_risk'] ?? ($al['financial_loss_projected'] ?? 0.0));
		if (($al['resolution_status'] ?? '') === 'cerrada') {
			$closedCount++;
		} else {
			$pendingResolution++;
		}
	}
	$resolutionRate = count($alertsList) > 0 ? round(($closedCount / count($alertsList)) * 100, 1) : 0;
	$money = fn($v) => '$' . number_format(floatval($v), 2);
@endphp

<div class="tab-pane fade" id="tab-predictive" role="tabpanel" aria-labelledby="tab-predictive-link">

	<!-- Header de Prevención Predictiva -->
	<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
		<div class="d-flex align-items-center">
			<div class="mr-3" style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #ef4444, #b91c1c); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
				<i class="fa-solid fa-triangle-exclamation"></i>
			</div>
			<div>
				<h5 class="font-weight-bold text-dark mb-0">Motor Predictivo de Vencimientos y Control FEFO</h5>
				<span class="text-muted small">Cálculo de riesgo: <code>Unidades en Riesgo = máx(0, Stock Lote − Demanda Estimada antes de vencer)</code></span>
			</div>
		</div>
		<div class="d-flex gap-2">
			<button class="btn btn-sm btn-outline-dark font-weight-bold shadow-xs mr-2" data-toggle="modal" data-target="#modal-category-rules">
				<i class="fa-solid fa-sliders mr-1 text-primary"></i>Reglas de Anticipación
			</button>
			<span class="badge badge-danger px-3 py-2 font-weight-bold d-flex align-items-center" style="font-size: 0.84rem;">
				<i class="fa-solid fa-shield-halved mr-1.5"></i>Algoritmo FEFO Activo
			</span>
		</div>
	</div>

	<!-- 1. KPIS PREDICTIVOS -->
	<div class="row mb-3">
		<div class="col-xl-3 col-md-6 col-12 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white" style="border-radius: 10px; border-left: 4px solid #ef4444 !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Lotes con Riesgo Detectado</span>
				<h4 class="font-weight-bold text-danger mb-0 mt-1">{{ count($alertsList) }} lotes</h4>
				<span class="small text-muted">{{ $pendingResolution }} activos · {{ $closedCount }} mitigados</span>
			</div>
		</div>
		<div class="col-xl-3 col-md-6 col-12 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white" style="border-radius: 10px; border-left: 4px solid #f59e0b !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Unidades en Riesgo de Vencer</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ number_format($totalUnitsAtRisk) }} un.</h4>
				<span class="small text-danger font-weight-bold"><i class="fa-solid fa-arrow-trend-up mr-1"></i>Supera demanda proyectada</span>
			</div>
		</div>
		<div class="col-xl-3 col-md-6 col-12 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white" style="border-radius: 10px; border-left: 4px solid #b91c1c !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Valor Económico en Riesgo</span>
				<h4 class="font-weight-bold text-danger mb-0 mt-1">{{ $money($totalValueAtRisk) }}</h4>
				<span class="small text-muted">Impacto antes de mitigación</span>
			</div>
		</div>
		<div class="col-xl-3 col-md-6 col-12 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white" style="border-radius: 10px; border-left: 4px solid #10b981 !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Tasa de Mitigación / Cierre</span>
				<h4 class="font-weight-bold text-success mb-0 mt-1">{{ $resolutionRate }}%</h4>
				<span class="small text-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i>Acciones ejecutadas y verificadas</span>
			</div>
		</div>
	</div>

	<!-- 2. REGLAS DE ANTICIPACIÓN POR CATEGORÍA (CARD RESUMEN) -->
	<div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 10px;">
		<div class="card-body p-3">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
					<i class="fa-solid fa-clock-rotate-left mr-1.5 text-primary"></i>Intervalos de Anticipación de Vencimiento Configurados
				</h6>
				<button class="btn btn-xs btn-link text-primary font-weight-bold p-0" data-toggle="modal" data-target="#modal-category-rules">
					<i class="fa-solid fa-pen-to-square mr-1"></i>Modificar intervalos
				</button>
			</div>
			<div class="d-flex flex-wrap gap-2 align-items-center">
				@forelse($rulesList as $rl)
					@php
						$daysArr = $rl['alert_days'] ?? (json_decode($rl['alert_days_json'] ?? '[]', true) ?: []);
						$daysStr = is_array($daysArr) ? implode(', ', $daysArr) : $daysArr;
					@endphp
					<div class="px-2.5 py-1.5 rounded border bg-light d-flex align-items-center" style="font-size: 0.8rem;">
						<span class="font-weight-bold text-dark mr-1.5">{{ $rl['category_name'] }}:</span>
						<span class="badge badge-warning text-dark font-weight-bold mr-1">{{ $daysStr }} días</span>
						@if(!empty($rl['requires_temp_check']))
							<i class="fa-solid fa-snowflake text-info ml-1" title="Requiere inspección de frío"></i>
						@endif
					</div>
				@empty
					<span class="text-muted small">Cargando intervalos de categorías...</span>
				@endforelse
			</div>
		</div>
	</div>

	<!-- 3. TABLA DE ALERTAS PREDICTIVAS INTELIGENTES -->
	<div class="card border-0 shadow-xs bg-white" style="border-radius: 10px;">
		<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
					<i class="fa-solid fa-list-check mr-2 text-danger"></i>Matriz de Alertas Predictivas — Resolución y Flujo Operativo
				</h6>
				<span class="text-muted small">Ciclo completo: <strong>ALERTA &rarr; VALIDACIÓN &rarr; ACCIÓN ASIGNADA &rarr; EJECUCIÓN &rarr; VERIFICACIÓN &rarr; CERRADA</strong></span>
			</div>
			<div class="d-flex align-items-center gap-2">
				<span class="badge badge-light border text-muted px-2 py-1 small">
					<i class="fa-solid fa-info-circle mr-1"></i>No se cierran alertas sin verificación de evidencia
				</span>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover table-striped align-middle mb-0" id="predictiveAlertsTable" style="font-size: 0.84rem;">
				<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem; letter-spacing: 0.03em;">
					<tr>
						<th class="border-0">Producto / UPC</th>
						<th class="border-0">Lote & Ubicación</th>
						<th class="border-0 text-center">Vencimiento / Días</th>
						<th class="border-0 text-right">Stock Lote</th>
						<th class="border-0 text-right">Demanda Proy.</th>
						<th class="border-0 text-right text-danger">En Riesgo</th>
						<th class="border-0 text-right">Valor en Riesgo</th>
						<th class="border-0">Acción Recomendada</th>
						<th class="border-0 text-center">Flujo de Resolución</th>
						<th class="border-0 text-center">Acciones</th>
					</tr>
				</thead>
				<tbody>
					@forelse($alertsList as $al)
						@php
							$daysLeft = intval($al['days_left'] ?? ($al['days_remaining'] ?? 0));
							$urgencyClass = $daysLeft <= 1 ? 'badge-danger' : ($daysLeft <= 3 ? 'badge-warning text-dark' : 'badge-info');
							$st = $al['resolution_status'] ?? 'alerta';
							$stMap = [
								'alerta' => ['label' => '1. Alerta', 'pct' => 16, 'cls' => 'badge-danger'],
								'validacion' => ['label' => '2. Validación', 'pct' => 33, 'cls' => 'badge-warning text-dark'],
								'accion_asignada' => ['label' => '3. Asignada', 'pct' => 50, 'cls' => 'badge-primary'],
								'ejecucion' => ['label' => '4. Ejecución', 'pct' => 66, 'cls' => 'badge-info'],
								'verificacion' => ['label' => '5. Verificación', 'pct' => 83, 'cls' => 'badge-secondary'],
								'cerrada' => ['label' => '6. Cerrada', 'pct' => 100, 'cls' => 'badge-success']
							];
							$stInfo = $stMap[$st] ?? $stMap['alerta'];
							$recLabels = [
								'priorizar_fefo' => 'Priorizar Rotación FEFO',
								'fefo_rotation' => 'Priorizar Rotación FEFO',
								'reubicar_producto' => 'Reubicar en Góndola Frontal',
								'shelf_reposition' => 'Reubicar en Góndola Frontal',
								'evaluar_descuento' => 'Evaluar Descuento Autorizado',
								'markdown_discount' => 'Evaluar Descuento Autorizado',
								'trasladar_sucursal' => 'Trasladar a Sucursal Mayor Rotación',
								'branch_transfer' => 'Trasladar a Sucursal Mayor Rotación',
								'retirar_no_apto' => 'Retirar Producto No Apto',
								'withdraw_unfit' => 'Retirar Producto No Apto',
								'devolucion_proveedor' => 'Devolución al Proveedor (RTV)',
								'supplier_return' => 'Devolución al Proveedor (RTV)',
								'donacion' => 'Donación Permitida',
								'donation' => 'Donación Permitida'
							];
							$recKey = $al['recommended_action'] ?? '';
							$recText = $recLabels[$recKey] ?? $recKey;
							$lotCode = $al['stock_id'] ?? ($al['lot_number'] ?? 'LOTE-001');
							$expDate = $al['best_before_date'] ?? ($al['expiration_date'] ?? date('Y-m-d'));
							$availStock = floatval($al['available_amount'] ?? ($al['available_stock'] ?? 0));
							$estDemand = floatval($al['estimated_demand_before_expiry'] ?? ($al['projected_demand'] ?? 0));
							$valRisk = floatval($al['total_value_risk'] ?? ($al['financial_loss_projected'] ?? 0));
							$dailySales = floatval($al['avg_daily_sales'] ?? ($al['average_daily_sales'] ?? 0));
						@endphp
						<tr>
							<td>
								<div class="d-flex align-items-center">
									<img src="{{ $U($al['image_url'] ?? '/img/control_center/prod_leche_galon.jpg') }}" alt="{{ $al['product_name'] }}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 6px; margin-right: 8px; border: 1px solid #e2e8f0;">
									<div>
										<div class="font-weight-bold text-dark">{{ $al['product_name'] }}</div>
										<span class="text-muted small">UPC: <code>{{ $al['upc'] ?? '—' }}</code> · SKU: {{ $al['sku'] ?? '—' }}</span>
									</div>
								</div>
							</td>
							<td>
								<div class="font-weight-bold font-monospace text-dark">{{ $lotCode }}</div>
								<span class="text-muted small"><i class="fa-solid fa-location-dot mr-1"></i>{{ $al['location_name'] ?? 'Piso de Venta' }}</span>
							</td>
							<td class="text-center">
								<span class="badge {{ $urgencyClass }} px-2 py-1 font-weight-bold">
									@if($daysLeft <= 0)
										¡Vence HOY!
									@else
										{{ $daysLeft }} días
									@endif
								</span>
								<div class="small text-muted mt-0.5">{{ $expDate }}</div>
							</td>
							<td class="text-right font-weight-bold text-dark">
								{{ number_format($availStock) }} un.
							</td>
							<td class="text-right text-muted">
								{{ number_format($estDemand) }} un.
								<div class="text-xs">Venta: {{ $dailySales }}/día</div>
							</td>
							<td class="text-right font-weight-bold text-danger" style="font-size: 0.95rem;">
								{{ number_format($al['units_at_risk']) }} un.
							</td>
							<td class="text-right font-weight-bold text-danger">
								{{ $money($valRisk) }}
								<div class="text-xs text-muted">Costo: {{ $money($al['unit_cost']) }}</div>
							</td>
							<td>
								<span class="badge badge-light border text-dark font-weight-600 px-2 py-1" style="font-size: 0.78rem;">
									<i class="fa-solid fa-bolt text-warning mr-1"></i>{{ $recText }}
								</span>
								@if(!empty($al['assigned_user']))
									<div class="small text-muted mt-0.5"><i class="fa-solid fa-user-check mr-1"></i>{{ $al['assigned_user'] }}</div>
								@endif
							</td>
							<td class="text-center" style="min-width: 130px;">
								<span class="badge {{ $stInfo['cls'] }} px-2 py-1 font-weight-bold">{{ $stInfo['label'] }}</span>
								<div class="progress mt-1" style="height: 5px; border-radius: 4px;">
									<div class="progress-bar @if($st==='cerrada') bg-success @else bg-primary @endif" role="progressbar" style="width: {{ $stInfo['pct'] }}%;"></div>
								</div>
							</td>
							<td class="text-center text-nowrap">
								<button class="btn btn-xs btn-outline-danger font-weight-bold" onclick="DairyQuality.OpenAdvanceAlertModal({{ json_encode($al) }})">
									<i class="fa-solid fa-arrow-right mr-1"></i>Gestionar
								</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="10" class="text-center py-4 text-muted">
								<i class="fa-solid fa-shield-cat fa-2x mb-2 text-success"></i>
								<div>No hay alertas predictivas críticas en este momento. Todos los lotes tienen demanda proyectada suficiente.</div>
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

</div>

<!-- ==========================================================================
     MODAL: GESTIONAR Y AVANZAR ETAPA DE ALERTA PREDICTIVA
     ========================================================================== -->
<div class="modal fade" id="modal-advance-predictive-alert" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-arrows-rotate text-danger mr-2"></i>Avanzar Etapa de Alerta Predictiva
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-advance-predictive-alert" onsubmit="DairyQuality.SubmitAdvanceAlert(event)">
				<input type="hidden" id="advance-alert-id" name="id">
				<div class="modal-body p-4">
					<div class="alert alert-light border mb-3">
						<div class="d-flex align-items-center mb-1">
							<strong id="advance-alert-product-name" class="text-dark font-weight-bold mr-2"></strong>
							<span id="advance-alert-lot" class="badge badge-light border font-monospace"></span>
						</div>
						<div class="small text-muted">
							Riesgo: <strong id="advance-alert-units-risk" class="text-danger"></strong> un. (<span id="advance-alert-value-risk" class="text-danger font-weight-bold"></span>)
						</div>
					</div>

					<div class="form-group">
						<label class="font-weight-bold text-dark small">Avanzar a Siguiente Etapa *</label>
						<select class="custom-select font-weight-bold" id="advance-alert-stage" name="resolution_status" required>
							<option value="validacion">2. Validación Física (Lote en piso y conteo)</option>
							<option value="accion_asignada">3. Acción Asignada (Plan definido con responsable)</option>
							<option value="ejecucion">4. En Ejecución (Reubicación, Rebaja o FEFO aplicado)</option>
							<option value="verificacion">5. Verificación (Supervisor valida cumplimiento)</option>
							<option value="cerrada">6. Cerrada (Riesgo mitigado / Despachado con éxito)</option>
						</select>
					</div>

					<div class="form-group">
						<label class="font-weight-bold text-dark small">Acción Operativa Aplicada *</label>
						<select class="custom-select" id="advance-alert-action" name="action_taken" required>
							<option value="priorizar_fefo">Priorizar rotación FEFO (Frente de góndola)</option>
							<option value="reubicar_producto">Reubicar producto en cabecera de alto tráfico</option>
							<option value="evaluar_descuento">Aplicar descuento autorizado por proximidad</option>
							<option value="trasladar_sucursal">Traslado intersucursal a punto con alta rotación</option>
							<option value="retirar_no_apto">Retiro de producto por no apto / Cuarentena</option>
							<option value="devolucion_proveedor">Devolución al proveedor con nota de crédito</option>
							<option value="donacion">Donación a banco de alimentos autorizado</option>
						</select>
					</div>

					<div class="row">
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">Responsable de Ejecución *</label>
							<input type="text" class="form-control" id="advance-alert-assignee" name="assigned_to_name" required placeholder="Nombre del colaborador">
						</div>
						<div class="col-md-6 form-group">
							<label class="font-weight-bold text-dark small">URL de Evidencia / Foto</label>
							<input type="text" class="form-control" id="advance-alert-evidence" name="evidence_url" placeholder="https://... o foto">
						</div>
					</div>

					<div class="form-group mb-0">
						<label class="font-weight-bold text-dark small">Observaciones / Verificación</label>
						<textarea class="form-control" id="advance-alert-notes" name="notes" rows="2" placeholder="Detalles de la comprobación en góndola o almacén..."></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light border-top py-2 px-4">
					<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-danger font-weight-bold shadow-sm" id="btn-submit-advance-alert">
						<i class="fa-solid fa-check mr-1"></i>Actualizar Estado
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL: CONFIGURAR INTERVALOS DE ANTICIPACIÓN POR CATEGORÍA
     ========================================================================== -->
<div class="modal fade" id="modal-category-rules" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg rounded-lg">
			<div class="modal-header bg-white border-bottom py-3">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-sliders text-primary mr-2"></i>Configuración de Alertas por Anticipación
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<form id="form-category-rules" onsubmit="DairyQuality.SubmitCategoryRules(event)">
				<div class="modal-body p-4">
					<p class="text-muted small mb-3">
						Ajusta los días de anticipación con los que el motor predictivo detonará alertas antes de la caducidad según las normas sanitarias y la política de merma.
					</p>

					<div class="table-responsive">
						<table class="table table-bordered table-sm mb-0">
							<thead class="bg-light text-muted uppercase" style="font-size: 0.72rem;">
								<tr>
									<th>Categoría</th>
									<th>Días de Anticipación (separados por coma)</th>
									<th class="text-center">Control de Frío</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Bakery / Panadería</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[bakery]" value="1, 2, 3"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[bakery]"></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Carnes frescas</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[carnes]" value="1, 2, 3"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[carnes]" checked></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Pollo fresco</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[pollo]" value="1, 2, 3"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[pollo]" checked></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Dairy / Lácteos</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[lacteos]" value="3, 5, 7"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[lacteos]" checked></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Frutas y verduras</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[fruver]" value="2, 4"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[fruver]"></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Congelados</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[congelados]" value="7, 15, 30"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[congelados]" checked></td>
								</tr>
								<tr>
									<td class="font-weight-bold text-dark align-middle">Productos secos</td>
									<td><input type="text" class="form-control form-control-sm" name="rules[secos]" value="15, 30, 60"></td>
									<td class="text-center align-middle"><input type="checkbox" name="temp[secos]"></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer bg-light border-top py-2 px-4">
					<button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary font-weight-bold shadow-sm">
						<i class="fa-solid fa-save mr-1"></i>Guardar Configuración
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
