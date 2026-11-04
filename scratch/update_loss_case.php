<?php
$file = 'views/loss_case.blade.php';
$content = file_get_contents($file);

$p1 = strpos($content, '<div id="caseModalResolverWizard"');
$p2 = strpos($content, '{{-- MODAL RELEVO DE TURNO RÁPIDO --}}');

if ($p1 === false || $p2 === false) {
    echo "Markers not found: p1=" . ($p1 !== false ? 'yes' : 'no') . ", p2=" . ($p2 !== false ? 'yes' : 'no') . "\n";
    exit(1);
}

$wizardHtml = <<<'HTML'
{{-- MODAL RESOLVER INCIDENCIA — FLUJO OPERATIVO GUIADO EN 15 PASOS CON PERSISTENCIA DE ESTADO --}}
<div id="caseModalResolverWizard" class="lp-modal-overlay" hidden>
	<div class="lp-modal-box" style="max-width: 900px;">
		<div class="lp-modal-header">
			<div>
				<h3 class="lp-modal-title"><i class="fa-solid fa-bolt text-warning mr-2"></i> Resolver Incidencia — Flujo Guiado 360°</h3>
				<small class="text-muted">{{ $c['case_code'] }} · {{ $p['name'] }} · Paso <span id="cWizCurrentStepBadge">1</span> de 15: <span id="cWizCurrentStepName">Ver Problema</span></small>
			</div>
			<div class="d-flex align-items-center gap-2">
				<button type="button" class="lp-btn" style="min-height: 28px; padding: 2px 10px; font-size: 0.75rem;" onclick="pauseWizForNextShift()">
					<i class="fa-solid fa-pause mr-1"></i> Pausar / Guardar Turno
				</button>
				<button type="button" class="lp-modal-close" onclick="closeModal('caseModalResolverWizard')">&times;</button>
			</div>
		</div>

		{{-- Stepper de 15 Pasos Horizontal y Dinámico --}}
		<div class="lp-stepper-progress" id="caseWizStepperBar">
			@php
				$stepsList = [
					1 => '1. Problema',
					2 => '2. Ubicación',
					3 => '3. Contactar',
					4 => '4. Asignar Tarea',
					5 => '5. Ir a Zona',
					6 => '6. Inspección',
					7 => '7. Escanear',
					8 => '8. Movimientos',
					9 => '9. Hallazgos',
					10 => '10. Evidencias',
					11 => '11. Causa & 5P',
					12 => '12. Aprobación',
					13 => '13. Verificar',
					14 => '14. Cerrar Caso',
					15 => '15. Plan Futuro'
				];
			@endphp
			@foreach($stepsList as $sNum => $sLbl)
			<div class="lp-step-pill @if($sNum === 1) active @endif" id="cSt{{ $sNum }}" onclick="switchCaseWizStep({{ $sNum }})" title="{{ $sLbl }}">
				<span class="lp-step-num">{{ $sNum }}</span>
				<span>{{ $sLbl }}</span>
			</div>
			@endforeach
		</div>

		<div class="lp-modal-body-scroll">
			{{-- PASO 1: VER PROBLEMA --}}
			<div id="cWizPane1" class="cwiz-pane">
				<div class="lp-card p-3 mb-3">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<span class="badge badge-danger p-1 px-2 font-weight-bold">DIFERENCIA OPERATIVA PATRIMONIAL</span>
						<span class="text-muted small">Registrado: {{ substr($c['created_at'], 0, 16) }}</span>
					</div>
					<h4 class="font-weight-bold mb-1">{{ $p['name'] }}</h4>
					<p class="text-muted small mb-3">SKU: {{ $p['sku'] ?: '—' }} · UPC: {{ $p['upc'] ?: '—' }} · Departamento: {{ $c['department'] }} · Sucursal: {{ $d['branch'] }}</p>
					
					<div class="lp-command-kpis mb-3" style="grid-template-columns: repeat(3, 1fr);">
						<div class="lp-cmd-card p-2 text-center">
							<span class="cmd-label">Stock Esperado</span>
							<div class="cmd-val" style="font-size: 1.3rem;">{{ floatval($c['expected_stock']) }} u.</div>
						</div>
						<div class="lp-cmd-card p-2 text-center">
							<span class="cmd-label">Físico en Conteo</span>
							<div class="cmd-val" style="font-size: 1.3rem;">{{ floatval($c['physical_count']) }} u.</div>
						</div>
						<div class="lp-cmd-card is-danger p-2 text-center">
							<span class="cmd-label">Diferencia Faltante</span>
							<div class="cmd-val" style="font-size: 1.3rem;">{{ floatval($c['difference_qty']) }} u.</div>
						</div>
					</div>

					<div class="alert alert-warning small p-2 m-0 d-flex justify-content-between align-items-center">
						<div><i class="fa-solid fa-coins text-warning mr-1"></i> <strong>Valor Económico en Riesgo:</strong> {{ $money($c['financial_impact']) }}</div>
						<span class="badge badge-dark">SLA: 24 horas</span>
					</div>
				</div>
				<div class="d-flex justify-content-end gap-2">
					<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
					<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(1, true)">Continuar: Consultar Ubicación <i class="fa-solid fa-arrow-right ml-1"></i></button>
				</div>
			</div>

			{{-- PASO 2: CONSULTAR UBICACIÓN --}}
			<div id="cWizPane2" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-location-dot text-danger mr-1"></i> Ubicación y Puntos de Custodia</h4>
					<p class="text-muted small mb-3">Verifique la zona asignada en góndola o vitrina de seguridad de la sucursal.</p>
					<div class="p-3 bg-light rounded border mb-3">
						<div class="row">
							<div class="col-md-6 mb-2">
								<small class="text-muted font-weight-bold d-block">ZONA REGISTRADA</small>
								<div class="h6 font-weight-bold text-dark m-0">{{ $c['zone'] ?: 'Piso de Venta / Vitrina Principal' }}</div>
							</div>
							<div class="col-md-6 mb-2">
								<small class="text-muted font-weight-bold d-block">SUCURSAL</small>
								<div class="h6 font-weight-bold text-dark m-0">{{ $d['branch'] }}</div>
							</div>
							<div class="col-md-6">
								<small class="text-muted font-weight-bold d-block">NIVEL DE CUSTODIA</small>
								<span class="badge badge-warning text-dark font-weight-bold">Alta Sensibilidad · Llave Gerencial</span>
							</div>
							<div class="col-md-6">
								<small class="text-muted font-weight-bold d-block">PLANO DE PISO</small>
								<a href="{{ $U('/locations/map') }}" target="_blank" class="small text-primary font-weight-bold"><i class="fa-solid fa-map mr-1"></i> Abrir Mapa de Tienda</a>
							</div>
						</div>
					</div>
					<div class="form-group m-0">
						<label class="small font-weight-bold">Observaciones de Ubicación:</label>
						<input type="text" id="cWizStep2LocationNotes" class="lp-field" placeholder="Ej: Vitrina de tecnología A-03, vitrina cerrada con precinto.">
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(1)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(2, true)">Continuar: Contactar Responsable <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 3: CONTACTAR RESPONSABLE --}}
			<div id="cWizPane3" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-1"><i class="fa-solid fa-phone text-success mr-1"></i> Contactar a Personal de Turno</h4>
					<p class="text-muted small mb-3">Llame al supervisor o asociado asignado. Debe confirmar el resultado objetivo de la llamada antes de registrarla:</p>
					
					<div class="d-flex flex-column gap-2 mb-3">
						@forelse($d['authorized_contacts'] ?? [] as $st)
						<div class="p-2 border rounded d-flex align-items-center justify-content-between bg-light">
							<div>
								<strong class="d-block">{{ $st['name'] }}</strong>
								<small class="text-muted">{{ $st['position'] }} · {{ $st['department'] }} · Tel: {{ $st['phone'] }}</small>
							</div>
							<div class="d-flex gap-2">
								<a href="tel:{{ $st['phone'] }}" class="lp-btn lp-btn-call" style="min-height: 30px; padding: 3px 8px; font-size: 0.78rem;" onclick="logQuickCallDirect('{{ addslashes($st['name']) }}', '{{ $st['phone'] }}', '{{ addslashes($st['position']) }}')">
									<i class="fa-solid fa-phone"></i> Llamar
								</a>
								<button type="button" class="lp-btn lp-btn-msg" style="min-height: 30px; padding: 3px 8px; font-size: 0.78rem;" onclick="openCaseMsgModal()">
									<i class="fa-solid fa-comment-dots"></i> Chat
								</button>
							</div>
						</div>
						@empty
						<p class="text-muted small">No hay personal de turno registrado.</p>
						@endforelse
					</div>

					<div class="row">
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Resultado Confirmado de la Llamada:</label>
							<select id="cWizStep3CallOutcome" class="lp-field">
								<option value="contestada">Contestó y coordinó inspección</option>
								<option value="no_contesta">No contestó (Se programa reintento)</option>
								<option value="ocupado">Línea ocupada</option>
								<option value="buzon">Buzón de voz</option>
							</select>
						</div>
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Nota de Compromiso:</label>
							<input type="text" id="cWizStep3CallNote" class="lp-field" placeholder="Ej: Acude a vitrina en 5 minutos.">
						</div>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(2)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(3, true)">Continuar: Asignar Tarea <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 4: ASIGNAR TAREA --}}
			<div id="cWizPane4" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-list-check text-primary mr-1"></i> Asignar Tarea Operativa</h4>
					<p class="text-muted small mb-3">Crea una orden de trabajo directa para el colaborador en piso de tienda:</p>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Título de la Tarea:</label>
						<input type="text" id="cWizTaskTitle" class="lp-field" value="Reconteo e inspección física de {{ $p['name'] }}">
					</div>
					<div class="row">
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Colaborador Asignado:</label>
							<select id="cWizTaskAssignee" class="lp-field">
								@foreach($d['authorized_contacts'] ?? [] as $st)
								<option value="{{ $st['user_id'] ?? 1 }}">{{ $st['name'] }} ({{ $st['position'] }})</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Prioridad Operativa:</label>
							<select id="cWizTaskPriority" class="lp-field">
								<option value="alta" selected>Alta (Intervención Inmediata)</option>
								<option value="media">Media</option>
								<option value="baja">Baja</option>
							</select>
						</div>
					</div>
					<div class="form-group m-0">
						<label class="small font-weight-bold">Instrucciones Específicas:</label>
						<textarea id="cWizTaskInstructions" class="lp-field" rows="2" placeholder="Ej: Realizar conteo doble en vitrina y verificar si hay unidades en custodia técnica."></textarea>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(3)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(4, true)">Continuar: Ir a la Zona <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 5: IR A LA ZONA (CHECK-IN) --}}
			<div id="cWizPane5" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3 text-center">
					<div class="mb-2"><i class="fa-solid fa-shoe-prints fa-3x text-success"></i></div>
					<h4 class="font-weight-bold">Confirmar Llegada In-Situ</h4>
					<p class="text-muted small mb-3">Certifique su presencia física en la zona <strong>{{ $c['zone'] ?: 'Piso de Venta / Vitrina' }}</strong> para iniciar el protocolo oficial:</p>
					<button type="button" class="lp-btn lp-btn-primary py-2 px-4 font-weight-bold" onclick="recordInSituCheckinDirect()">
						<i class="fa-solid fa-location-crosshairs mr-1"></i> REGISTRAR LLEGADA IN-SITU (CHECK-IN)
					</button>
					<div id="cWizCheckinDone" class="mt-3 p-2 bg-light border border-success rounded text-success small font-weight-bold" hidden>
						<i class="fa-solid fa-circle-check mr-1"></i> Llegada confirmada y estampada con hora y georreferencia en la línea de tiempo.
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(4)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(5, true)">Continuar: Iniciar Inspección <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 6: INICIAR INSPECCIÓN (HERRAMIENTAS) --}}
			<div id="cWizPane6" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-toolbox text-primary mr-1"></i> Checklist de Herramientas y Seguridad</h4>
					<p class="text-muted small mb-3">Confirme la disponibilidad de los instrumentos necesarios antes del conteo físico:</p>
					<div class="d-flex flex-column gap-2 mb-3">
						<label class="p-2 border rounded bg-light d-flex align-items-center gap-2 m-0 cursor-pointer">
							<input type="checkbox" id="chkTool1" checked>
							<span class="small font-weight-bold"><i class="fa-solid fa-barcode text-primary mr-1"></i> Terminal Escáner Láser / Lector Industrial</span>
						</label>
						<label class="p-2 border rounded bg-light d-flex align-items-center gap-2 m-0 cursor-pointer">
							<input type="checkbox" id="chkTool2" checked>
							<span class="small font-weight-bold"><i class="fa-solid fa-key text-warning mr-1"></i> Llave Autorizada de Vitrina de Seguridad</span>
						</label>
						<label class="p-2 border rounded bg-light d-flex align-items-center gap-2 m-0 cursor-pointer">
							<input type="checkbox" id="chkTool3" checked>
							<span class="small font-weight-bold"><i class="fa-solid fa-tag text-success mr-1"></i> Precintos de Custodia y Bolsas de Evidencia</span>
						</label>
						<label class="p-2 border rounded bg-light d-flex align-items-center gap-2 m-0 cursor-pointer">
							<input type="checkbox" id="chkTool4" checked>
							<span class="small font-weight-bold"><i class="fa-solid fa-clipboard-list text-info mr-1"></i> Hoja de Conteo Ciego Oficial de Tienda</span>
						</label>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(5)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(6, true)">Continuar: Escanear Mercancía <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 7: ESCANEAR MERCANCÍA --}}
			<div id="cWizPane7" class="cwiz-pane" hidden>
				<div class="lp-scanner-display mb-3">
					<small class="text-uppercase tracking-wider opacity-75">Diferencia Dinámica de Conteo Físico</small>
					<div class="lp-diff-live" id="cWizDiffDisplay">{{ $signed($c['difference_qty']) }} u.</div>
					<div class="small">Stock esperado: <strong>{{ floatval($c['expected_stock']) }} u.</strong></div>
				</div>

				<div class="form-group mb-2">
					<label class="small font-weight-bold">Escanear Código UPC / Serie / IMEI 1 o IMEI 2:</label>
					<div class="d-flex gap-2">
						<input type="text" id="cWizScanInput" class="lp-field" placeholder="Escanee con el lector o ingrese el código..." onkeydown="if(event.key==='Enter'){event.preventDefault();execCaseScan();}">
						<button type="button" class="lp-btn lp-btn-primary" onclick="execCaseScan()"><i class="fa-solid fa-barcode"></i> Validar</button>
					</div>
				</div>

				<div class="form-group mb-3">
					<label class="small font-weight-bold">Series / IMEIs Validados:</label>
					<div class="p-2 border rounded bg-light" id="cWizChips" style="min-height: 48px;">
						<span class="text-muted small">Escanee cada unidad para incorporarla.</span>
					</div>
				</div>

				<div class="form-group mb-3">
					<label class="small font-weight-bold">Unidades Físicas Ratificadas:</label>
					<input type="number" id="cWizCountInput" class="lp-field" min="0" step="any" value="{{ floatval($c['physical_count']) }}" oninput="updateCaseLiveDiff()">
				</div>

				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(6)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(7, true)">Continuar: Revisar Movimientos <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 8: REVISAR MOVIMIENTOS --}}
			<div id="cWizPane8" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-arrow-right-arrow-left text-info mr-1"></i> Conciliación Cruzada de 5 Flujos</h4>
					<p class="text-muted small mb-3">DAILY audita automáticamente si las unidades faltantes salieron por otro canal comercial:</p>
					
					<div class="table-responsive">
						<table class="table table-sm table-bordered text-center m-0">
							<thead class="thead-light">
								<tr>
									<th>Flujo Operativo</th>
									<th>Registros Detectados</th>
									<th>Impacto</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td class="text-left font-weight-bold"><i class="fa-solid fa-cash-register mr-1 text-success"></i> Ventas POS de Caja</td>
									<td>{{ count($d['pos_sales'] ?? []) }} transacciones</td>
									<td>FEFO verificado</td>
									<td><span class="badge badge-success">Auditado</span></td>
								</tr>
								<tr>
									<td class="text-left font-weight-bold"><i class="fa-solid fa-boxes-packing mr-1 text-primary"></i> Pedidos Pickup / Despacho</td>
									<td>{{ count($d['orders'] ?? []) }} reservas</td>
									<td>En bodega técnica</td>
									<td><span class="badge badge-info">Revisado</span></td>
								</tr>
								<tr>
									<td class="text-left font-weight-bold"><i class="fa-solid fa-truck mr-1 text-warning"></i> Traslados Inter-tiendas</td>
									<td>{{ count($d['transfers'] ?? []) }} guías</td>
									<td>Sin envíos abiertos</td>
									<td><span class="badge badge-success">Conciliado</span></td>
								</tr>
								<tr>
									<td class="text-left font-weight-bold"><i class="fa-solid fa-arrow-rotate-left mr-1 text-danger"></i> Devoluciones / RTV</td>
									<td>{{ count($d['returns_adjustments'] ?? []) }} notas</td>
									<td>Sin devoluciones pendientes</td>
									<td><span class="badge badge-secondary">Conforme</span></td>
								</tr>
								<tr>
									<td class="text-left font-weight-bold"><i class="fa-solid fa-trash-can mr-1 text-muted"></i> Bajas y Mermas</td>
									<td>0 bajas previas</td>
									<td>Sin registro previo</td>
									<td><span class="badge badge-success">OK</span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(7)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(8, true)">Continuar: Registrar Hallazgos <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 9: REGISTRAR HALLAZGOS --}}
			<div id="cWizPane9" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-clipboard-check text-success mr-1"></i> Registrar Hallazgos de Campo</h4>
					<p class="text-muted small mb-3">Detalle las observaciones físicas en la vitrina, empaques o testimonios del turno:</p>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Estado Físico del Punto de Venta / Vitrina:</label>
						<select id="cWizFindingState" class="lp-field">
							<option value="intact">Vitrina intacta, cerraduras sin forzamiento</option>
							<option value="irregular">Cerradura con holgura o llave dejada puesta</option>
							<option value="forced">Cerradura o precinto forzado</option>
						</select>
					</div>
					<div class="form-group m-0">
						<label class="small font-weight-bold">Descripción Pormenorizada de Hallazgos:</label>
						<textarea id="cWizFindingDetails" class="lp-field" rows="3" placeholder="Ej: Se identificó que las unidades fueron movidas temporalmente a custodia técnica durante el relevo de la mañana sin el escaneo de salida."></textarea>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(8)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(9, true)">Continuar: Adjuntar Evidencia <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 10: ADJUNTAR EVIDENCIA --}}
			<div id="cWizPane10" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-paperclip text-primary mr-1"></i> Respaldo Documental y Evidencias</h4>
					<p class="text-muted small mb-3">Vincule referencias fotográficas, videos de CCTV o actas de entrega:</p>
					<div class="row">
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Cámara de Seguridad / Referencia CCTV:</label>
							<input type="text" id="cWizCctvRef" class="lp-field" placeholder="Ej: CAM-14_Vitrina_Telefonía_10:30">
						</div>
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Número de Acta o Remisión:</label>
							<input type="text" id="cWizDocRef" class="lp-field" placeholder="Ej: ACTA-CUSTODIA-004">
						</div>
					</div>
					<div class="form-group m-0">
						<label class="small font-weight-bold">Notas de la Evidencia:</label>
						<input type="text" id="cWizEvidenceNotes" class="lp-field" placeholder="Ej: Video muestra traslado de bulto sellado a bodega interna por supervisor.">
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(9)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(10, true)">Continuar: Causa Raíz & 5 Porqués <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 11: CAUSA RAÍZ & 5 PORQUÉS --}}
			<div id="cWizPane11" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-diagram-project text-warning mr-1"></i> Análisis de Causa Raíz y Método de los 5 Porqués</h4>
					<p class="text-muted small mb-3">Clasifique la causal separando hipótesis de hechos confirmados:</p>
					
					<div class="row mb-2">
						<div class="col-md-6 form-group">
							<label class="small font-weight-bold">Estado de la Causal:</label>
							<select id="cWizCauseStatus" class="lp-field font-weight-bold">
								<option value="sospechada">Causa Sospechada (Hipótesis de trabajo)</option>
								<option value="investigada">Causa Investigada (Con pruebas parciales)</option>
								<option value="confirmada" selected>Causa Confirmada (Hecho plenamente demostrado)</option>
							</select>
						</div>
						<div class="col-md-6 form-group">
							<label class="small font-weight-bold">Clasificación Estándar:</label>
							<select id="cWizCauseClass" class="lp-field">
								<option value="Error de recepción">Error de recepción</option>
								<option value="Error de conteo">Error de conteo</option>
								<option value="Error de venta POS">Error de venta POS</option>
								<option value="Error de despacho">Error de despacho</option>
								<option value="Transferencia no conciliada" selected>Transferencia no conciliada</option>
								<option value="Devolución incorrecta">Devolución incorrecta</option>
								<option value="Ubicación incorrecta">Ubicación incorrecta</option>
								<option value="Merma no registrada">Merma no registrada</option>
								<option value="Vencimiento de producto">Vencimiento de producto</option>
								<option value="Avería física">Avería física</option>
								<option value="Diferencia no explicada">Diferencia no explicada</option>
								<option value="Hurto confirmado mediante proceso">Hurto confirmado mediante proceso</option>
							</select>
						</div>
					</div>

					<div class="p-3 bg-light rounded border mb-2">
						<span class="small font-weight-bold text-primary d-block mb-2"><i class="fa-solid fa-arrows-split-up-and-left mr-1"></i> Método de los 5 Porqués:</span>
						<div class="form-group mb-1">
							<input type="text" id="cWizWhy1" class="lp-field small mb-1" placeholder="1. ¿Por qué ocurrió la diferencia?: Ej. Los celulares no se hallaron en la vitrina.">
							<input type="text" id="cWizWhy2" class="lp-field small mb-1" placeholder="2. ¿Por qué no estaban?: Ej. Fueron movidos a custodia de bodega técnica.">
							<input type="text" id="cWizWhy3" class="lp-field small mb-1" placeholder="3. ¿Por qué se movieron sin registro?: Ej. Faltó el segundo escaneo de confirmación.">
							<input type="text" id="cWizWhy4" class="lp-field small mb-1" placeholder="4. ¿Por qué falló el escaneo?: Ej. La sesión del escáner no cerró con validación de bulto.">
							<input type="text" id="cWizWhy5" class="lp-field small m-0 font-weight-bold text-danger" placeholder="5. Causa Raíz Fundamental: Procedimiento de transferencia no exigía confirmación mutua obligatoria.">
						</div>
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(10)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(11, true)">Continuar: Solicitar Aprobación <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 12: SOLICITAR APROBACIÓN --}}
			<div id="cWizPane12" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-signature text-primary mr-1"></i> Segregación de Funciones y Aprobación Gerencial</h4>
					<p class="text-muted small mb-3">Toda regularización patrimonial requiere autorización del Gerente de Tienda o Prevención:</p>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Propuesta de Resolución:</label>
						<select id="cWizApprovalProposal" class="lp-field">
							<option value="reconciliation_no_loss" selected>Reubicación y Reconciliación (Sin pérdida económica: Mercancía recuperada)</option>
							<option value="stock_adjustment">Ajuste de Stock Faltante (Asumir merma patrimonial)</option>
							<option value="supplier_rtv">Devolución y Cargo a Proveedor</option>
							<option value="insurance_claim">Reclamación de Cobertura de Seguros</option>
						</select>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold">Justificación Oficial para Gerencia:</label>
						<textarea id="cWizApprovalJustification" class="lp-field" rows="2" placeholder="Explique las evidencias que respaldan esta resolución..."></textarea>
					</div>
					<div class="alert alert-info small m-0">
						<i class="fa-solid fa-shield-halved mr-1"></i> Trazabilidad inalterable: Se registrará el usuario proponente y el firmante con hash SHA-256.
					</div>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(11)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(12, true)">Continuar: Verificar Resultado <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 13: VERIFICAR RESULTADO --}}
			<div id="cWizPane13" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3 text-center">
					<div class="mb-2"><i class="fa-solid fa-check-double fa-3x text-success"></i></div>
					<h4 class="font-weight-bold">Verificación Post-Intervención</h4>
					<p class="text-muted small mb-3">Comprobación del balance de existencias y cierre de diferencias operativas:</p>
					<div class="p-3 bg-light rounded border mb-3">
						<div class="row">
							<div class="col-4">
								<small class="text-muted font-weight-bold">STOCK ESPERADO</small>
								<div class="h5 font-weight-bold m-0">{{ floatval($c['expected_stock']) }} u.</div>
							</div>
							<div class="col-4">
								<small class="text-muted font-weight-bold">FÍSICO VERIFICADO</small>
								<div class="h5 font-weight-bold text-success m-0" id="cWizVerifiedQty">{{ floatval($c['expected_stock']) }} u.</div>
							</div>
							<div class="col-4">
								<small class="text-muted font-weight-bold">SALDO DIFERENCIA</small>
								<div class="h5 font-weight-bold text-success m-0" id="cWizVerifiedDiff">0 u. (CONCILIADO)</div>
							</div>
						</div>
					</div>
					<span class="badge badge-success p-2 px-3 font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i> Inventario Conciliado 100% en Toda la Red</span>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(12)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(13, true)">Continuar: Cerrar Caso <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 14: CERRAR CASO --}}
			<div id="cWizPane14" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-lock text-success mr-1"></i> Cierre Formal de la Incidencia</h4>
					<p class="text-muted small mb-3">El cierre formal sincroniza el estado en HOY, Panel Ejecutivo, Conciliación y Centro de Alertas:</p>
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Dictamen Final:</label>
						<select id="cWizFinalOutcomeSelect" class="lp-field font-weight-bold">
							<option value="resuelto" selected>RESUELTO (Mercancía recuperada y conciliada)</option>
							<option value="no_resuelto">NO RESUELTO (Pérdida patrimonial confirmada)</option>
						</select>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold">Dictamen Ejecutivo de Cierre:</label>
						<textarea id="cWizFinalClosingNote" class="lp-field" rows="2" placeholder="Ej: Se localizó la totalidad de los equipos en custodia técnica. Stock regularizado sin pérdida económica."></textarea>
					</div>
					<button type="button" class="lp-btn lp-btn-resolve btn-block py-2 font-weight-bold" onclick="executeFullCaseClose()">
						<i class="fa-solid fa-check-double mr-1"></i> FIRMAR Y APLICAR CIERRE DE EXPEDIENTE
					</button>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(13)">Atrás</button>
					<div class="d-flex gap-2">
						<button type="button" class="lp-btn" onclick="pauseWizForNextShift()"><i class="fa-solid fa-pause mr-1"></i> Guardar y pausar</button>
						<button type="button" class="lp-btn lp-btn-primary" onclick="saveCaseWizStep(14, true)">Continuar: Crear Plan Futuro <i class="fa-solid fa-arrow-right ml-1"></i></button>
					</div>
				</div>
			</div>

			{{-- PASO 15: CREAR PREVENCIÓN FUTURA --}}
			<div id="cWizPane15" class="cwiz-pane" hidden>
				<div class="lp-card p-3 mb-3">
					<h4 class="font-weight-bold mb-2"><i class="fa-solid fa-bullseye text-primary mr-1"></i> Plan de Acción Preventivo Contra Reincidencias</h4>
					<p class="text-muted small mb-3">Cada investigación resuelta debe generar un control permanente para blindar la operación:</p>
					
					<div class="form-group mb-2">
						<label class="small font-weight-bold">Título del Plan Preventivo:</label>
						<input type="text" id="cWizPlanTitle" class="lp-field" value="Implementar doble escaneo obligatorio en traslados internos de tecnología">
					</div>
					<div class="row">
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Responsable del Cumplimiento:</label>
							<select id="cWizPlanOwner" class="lp-field">
								@foreach($d['authorized_contacts'] ?? [] as $st)
								<option value="{{ $st['user_id'] ?? 1 }}">{{ $st['name'] }} ({{ $st['position'] }})</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6 form-group mb-2">
							<label class="small font-weight-bold">Fecha Límite de Implementación:</label>
							<input type="date" id="cWizPlanTargetDate" class="lp-field" value="{{ date('Y-m-d', strtotime('+7 days')) }}">
						</div>
					</div>
					<div class="form-group mb-3">
						<label class="small font-weight-bold">Frecuencia de Auditoría de Verificación:</label>
						<select id="cWizPlanFrequency" class="lp-field">
							<option value="7">Semanal (7 días)</option>
							<option value="15">Quincenal (15 días)</option>
							<option value="30">Mensual (30 días)</option>
						</select>
					</div>
					<button type="button" class="lp-btn lp-btn-primary btn-block py-2 font-weight-bold" onclick="executeCreatePreventivePlan()">
						<i class="fa-solid fa-shield mr-1"></i> ACTIVAR PLAN PREVENTIVO PERMANENTE
					</button>
				</div>
				<div class="d-flex justify-content-between">
					<button type="button" class="lp-btn" onclick="switchCaseWizStep(14)">Atrás</button>
					<button type="button" class="lp-btn" onclick="closeModal('caseModalResolverWizard')">Finalizar y Cerrar Asistente</button>
				</div>
			</div>
		</div>
	</div>
</div>

HTML;

$newContent = substr($content, 0, $p1) . $wizardHtml . "\n" . substr($content, $p2);
file_put_contents($file, $newContent);
echo "Successfully updated loss_case.blade.php with 15-step wizard!\n";

