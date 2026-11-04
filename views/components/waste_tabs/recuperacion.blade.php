@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$pctFmt = fn($v) => number_format(floatval($v), 1) . '%';

	$supplierReturns = $supplierReturns ?? [];
	
	// Cálculos de indicadores de recuperación financiera
	$potencial = 42850.00;
	$reclamado = 36420.00;
	$confirmado = 28150.00;
	$rechazado = 3120.00;
	$pendiente = 5150.00;
	$tasaGlobal = $reclamado > 0 ? ($confirmado / $reclamado) * 100 : 77.3;
	$tiempoPromedio = 8.4; // días

	// Casos enriquecidos con tipo de tercero y etapa en el flujo de 10 pasos
	$recCases = [
		[
			'id' => 101,
			'case_number' => 'REC-2026-0089',
			'type' => 'proveedor',
			'type_label' => 'Proveedor · Nota de Crédito',
			'type_badge' => 'badge-primary',
			'third_party' => 'Colanta Lácteos S.A.',
			'product' => 'Queso Doble Crema 500g (Lote Q-882)',
			'claim_amount' => 4820.00,
			'recovered_amount' => 4820.00,
			'status' => 'confirmed',
			'status_label' => 'Recuperado confirmado',
			'status_badge' => 'badge-success',
			'step' => 10,
			'step_name' => '10. Cierre de expediente',
			'lead_days' => 6,
			'evidence_doc' => 'NC-COL-99214.pdf',
			'responsible' => 'Carlos Mendoza (Control Calidad)',
			'dates' => ['created' => '2026-10-18', 'acknowledged' => '2026-10-20', 'resolved' => '2026-10-24']
		],
		[
			'id' => 102,
			'case_number' => 'REC-2026-0092',
			'type' => 'transportista',
			'type_label' => 'Transportista · Avería Tránsito',
			'type_badge' => 'badge-warning',
			'third_party' => 'Logística Refrigerada del Norte',
			'product' => 'Pechuga de Pollo Fresca 1kg (Cajas estibadas)',
			'claim_amount' => 6450.00,
			'recovered_amount' => 5200.00,
			'status' => 'confirmed',
			'status_label' => 'Recuperado confirmado',
			'status_badge' => 'badge-success',
			'step' => 9,
			'step_name' => '9. Ajuste contable y de stock',
			'lead_days' => 9,
			'evidence_doc' => 'Póliza-TR-8812.pdf',
			'responsible' => 'Lucía Restrepo (Fulfillment)',
			'dates' => ['created' => '2026-10-21', 'acknowledged' => '2026-10-23', 'resolved' => '2026-10-30']
		],
		[
			'id' => 103,
			'case_number' => 'REC-2026-0095',
			'type' => 'aseguradora',
			'type_label' => 'Aseguradora · Siniestro Cadena Frío',
			'type_badge' => 'badge-danger',
			'third_party' => 'Seguros Comerciales Bolívar',
			'product' => 'Cámara Frigorífica #2 (Lácteos y Postres)',
			'claim_amount' => 14800.00,
			'recovered_amount' => 0.00,
			'status' => 'pending',
			'status_label' => 'Pendiente respuesta',
			'status_badge' => 'badge-warning',
			'step' => 6,
			'step_name' => '6. Evaluación técnica pericial',
			'lead_days' => 12,
			'evidence_doc' => 'Peritaje-TERM-402.pdf',
			'responsible' => 'Renzo Giovine (Gerencia Ops)',
			'dates' => ['created' => '2026-10-22', 'acknowledged' => '2026-10-25', 'resolved' => null]
		],
		[
			'id' => 104,
			'case_number' => 'REC-2026-0098',
			'type' => 'proveedor',
			'type_label' => 'Proveedor · Defecto Fábrica',
			'type_badge' => 'badge-primary',
			'third_party' => 'Alquería Productos Lácteos',
			'product' => 'Yogurt Fresa 1.5L (Sellado deficiente)',
			'claim_amount' => 2850.00,
			'recovered_amount' => 2850.00,
			'status' => 'confirmed',
			'status_label' => 'Recuperado confirmado',
			'status_badge' => 'badge-success',
			'step' => 10,
			'step_name' => '10. Cierre de expediente',
			'lead_days' => 5,
			'evidence_doc' => 'NC-ALQ-34112.pdf',
			'responsible' => 'Carlos Mendoza (Control Calidad)',
			'dates' => ['created' => '2026-10-26', 'acknowledged' => '2026-10-27', 'resolved' => '2026-10-31']
		],
		[
			'id' => 105,
			'case_number' => 'REC-2026-0101',
			'type' => 'transportista',
			'type_label' => 'Transportista · Rotura en Descargue',
			'type_badge' => 'badge-warning',
			'third_party' => 'Transportes Rápidos Andinos',
			'product' => 'Aceite de Oliva Extra Virgen 500ml',
			'claim_amount' => 3120.00,
			'recovered_amount' => 0.00,
			'status' => 'rejected',
			'status_label' => 'Rechazado (No imputable)',
			'status_badge' => 'badge-secondary',
			'step' => 7,
			'step_name' => '7. Conciliación final',
			'lead_days' => 14,
			'evidence_doc' => 'Informe-Rechazo-TRA-99.pdf',
			'responsible' => 'Felipe Guzmán (Recepción Bodega)',
			'dates' => ['created' => '2026-10-15', 'acknowledged' => '2026-10-17', 'resolved' => '2026-10-29']
		],
		[
			'id' => 106,
			'case_number' => 'REC-2026-0104',
			'type' => 'proveedor',
			'type_label' => 'Proveedor · Faltante Oculto ASN',
			'type_badge' => 'badge-primary',
			'third_party' => 'Nutresa Galletas y Dulces',
			'product' => 'Galletas Selección Familiar Pack x12',
			'claim_amount' => 4380.00,
			'recovered_amount' => 0.00,
			'status' => 'negotiating',
			'status_label' => 'En negociación',
			'status_badge' => 'badge-info',
			'step' => 7,
			'step_name' => '7. Negociación y acuerdo',
			'lead_days' => 7,
			'evidence_doc' => 'Acta-ASN-DIF-801.pdf',
			'responsible' => 'Mateo Valencia (Auditoría)',
			'dates' => ['created' => '2026-10-27', 'acknowledged' => '2026-10-28', 'resolved' => null]
		]
	];
@endphp

<div class="card border-0 shadow-xs bg-white mb-3 dairy-bordered">
	<div class="card-body p-4">
		<!-- HEADER CON CONCEPTO Y ACCIONES -->
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-3 border-bottom">
			<div>
				<div class="d-flex align-items-center mb-1">
					<div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mr-2" style="width:36px; height:36px;">
						<i class="fa-solid fa-hand-holding-dollar"></i>
					</div>
					<h5 class="font-weight-bold text-dark mb-0">
						Recuperación Financiera de Mermas y Siniestros
					</h5>
				</div>
				<p class="text-muted mb-0 dairy-fs-base">
					Gestión de reclamos a proveedores (NC), transportistas y aseguradoras. Distinción explícita entre <span class="text-primary font-weight-bold">montos reclamados</span> y <span class="text-success font-weight-bold">montos recuperados confirmados</span>.
				</p>
			</div>
			<div class="mt-2 mt-md-0 d-flex align-items-center" style="gap:8px;">
				<button class="btn btn-sm btn-outline-secondary font-weight-600" data-toggle="modal" data-target="#modalRecuperacionWorkflow">
					<i class="fa-solid fa-diagram-project mr-1 text-primary"></i>Ver Flujo de 10 Etapas
				</button>
				<button class="btn btn-sm btn-success font-weight-600" onclick="dairyNewClaimDialog();">
					<i class="fa-solid fa-plus-circle mr-1"></i>Generar Reclamo Formal
				</button>
			</div>
		</div>

		<!-- ============================================================== -->
		<!-- 7 INDICADORES CLAVE DE RECUPERACIÓN FINANCIERA                  -->
		<!-- ============================================================== -->
		<div class="row mb-4">
			<!-- 1. Potencialmente recuperable -->
			<div class="col-xl-3 col-md-6 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>1. POTENCIALMENTE RECUPERABLE</span>
						<i class="fa-solid fa-magnifying-glass-dollar text-primary"></i>
					</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ $money($potencial) }}</div>
					<small class="text-muted">Causales atribuibles a terceros</small>
				</div>
			</div>

			<!-- 2. Monto reclamado formalmente -->
			<div class="col-xl-3 col-md-6 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>2. RECLAMADO A TERCEROS</span>
						<i class="fa-solid fa-file-invoice text-info"></i>
					</div>
					<div class="h4 font-weight-bold text-info mb-0">{{ $money($reclamado) }}</div>
					<small class="text-muted">6 expedientes radicados</small>
				</div>
			</div>

			<!-- 3. Monto efectivamente recuperado (confirmado) -->
			<div class="col-xl-3 col-md-6 mb-2">
				<div class="p-3 border rounded bg-white h-100" style="border-left: 4px solid #10b981 !important;">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>3. RECUPERADO CONFIRMADO</span>
						<i class="fa-solid fa-circle-check text-success"></i>
					</div>
					<div class="h4 font-weight-bold text-success mb-0">{{ $money($confirmado) }}</div>
					<small class="text-success font-weight-bold">✓ Notas de crédito aplicadas</small>
				</div>
			</div>

			<!-- 4. Rechazado o no recuperable -->
			<div class="col-xl-3 col-md-6 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>4. RECHAZADO / NO RECUPERABLE</span>
						<i class="fa-solid fa-circle-xmark text-danger"></i>
					</div>
					<div class="h4 font-weight-bold text-danger mb-0">{{ $money($rechazado) }}</div>
					<small class="text-muted">Fuera de tiempo o no imputable</small>
				</div>
			</div>

			<!-- 5. Pendiente de respuesta -->
			<div class="col-xl-4 col-md-6 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>5. PENDIENTE DE RESPUESTA</span>
						<i class="fa-solid fa-clock text-warning"></i>
					</div>
					<div class="h4 font-weight-bold text-warning mb-0">{{ $money($pendiente) }}</div>
					<small class="text-muted">En evaluación pericial y conciliación</small>
				</div>
			</div>

			<!-- 6. Tasa global de recuperación -->
			<div class="col-xl-4 col-md-6 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>6. TASA GLOBAL RECUPERACIÓN</span>
						<i class="fa-solid fa-chart-line text-success"></i>
					</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ $pctFmt($tasaGlobal) }}</div>
					<small class="text-muted">Confirmado / Reclamado (Meta ≥ 75%)</small>
				</div>
			</div>

			<!-- 7. Tiempo promedio de resolución -->
			<div class="col-xl-4 col-md-12 mb-2">
				<div class="p-3 border rounded bg-light h-100">
					<div class="d-flex justify-content-between align-items-center text-muted small font-weight-bold mb-1">
						<span>7. TIEMPO PROMEDIO RESOLUCIÓN</span>
						<i class="fa-solid fa-stopwatch text-primary"></i>
					</div>
					<div class="h4 font-weight-bold text-primary mb-0">{{ $tiempoPromedio }} días</div>
					<small class="text-muted">SLA objetivo ≤ 10 días hábiles</small>
				</div>
			</div>
		</div>

		<!-- ============================================================== -->
		<!-- TABLA DE RECLAMOS CON AUDITORÍA Y TRAZABILIDAD                  -->
		<!-- ============================================================== -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<span class="font-weight-bold text-dark"><i class="fa-solid fa-table-list mr-1 text-primary"></i>Expedientes de Reclamación y Recuperación Activos</span>
			<div class="btn-group btn-group-sm">
				<button class="btn btn-outline-secondary active" onclick="recFilterType('all')">Todos (6)</button>
				<button class="btn btn-outline-primary" onclick="recFilterType('proveedor')">Proveedores (3)</button>
				<button class="btn btn-outline-warning" onclick="recFilterType('transportista')">Transporte (2)</button>
				<button class="btn btn-outline-danger" onclick="recFilterType('aseguradora')">Seguros (1)</button>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-hover dairy-fs-sm mb-0">
				<thead class="thead-light">
					<tr>
						<th>FOLIO / CANAL</th>
						<th>TERCERO / PROVEEDOR</th>
						<th>PRODUCTO / CAUSAL</th>
						<th class="text-right">RECLAMADO</th>
						<th class="text-right">CONFIRMADO</th>
						<th class="text-center">ETAPA PROCESO (1-10)</th>
						<th>RESPONSABLE / SLA</th>
						<th class="text-center">ESTADO</th>
						<th class="text-center">ACCIONES</th>
					</tr>
				</thead>
				<tbody>
					@foreach($recCases as $rc)
					<tr class="rec-row" data-type="{{ $rc['type'] }}">
						<td>
							<div class="font-mono font-weight-bold text-dark">{{ $rc['case_number'] }}</div>
							<span class="badge {{ $rc['type_badge'] }}" style="font-size:0.68rem;">{{ $rc['type_label'] }}</span>
						</td>
						<td>
							<div class="font-weight-bold text-dark">{{ $rc['third_party'] }}</div>
							<small class="text-muted"><i class="fa-regular fa-calendar mr-1"></i>Radicado: {{ $rc['dates']['created'] }}</small>
						</td>
						<td>
							<div class="text-truncate font-weight-500" style="max-width: 200px;" title="{{ $rc['product'] }}">{{ $rc['product'] }}</div>
							<small class="text-muted"><i class="fa-solid fa-paperclip mr-1"></i>{{ $rc['evidence_doc'] }}</small>
						</td>
						<td class="text-right font-weight-bold text-info">{{ $money($rc['claim_amount']) }}</td>
						<td class="text-right font-weight-bold @if($rc['recovered_amount'] > 0) text-success @else text-muted @endif">
							{{ $money($rc['recovered_amount']) }}
						</td>
						<td class="text-center">
							<div class="d-flex align-items-center justify-content-center" style="gap:4px;">
								<div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
									<div class="progress-bar @if($rc['step'] == 10) bg-success @elseif($rc['step'] >= 7) bg-info @else bg-warning @endif" style="width: {{ $rc['step'] * 10 }}%;"></div>
								</div>
								<span class="font-weight-bold small ml-1">{{ $rc['step'] }}/10</span>
							</div>
							<small class="text-muted d-block text-truncate" style="max-width: 140px;" title="{{ $rc['step_name'] }}">{{ $rc['step_name'] }}</small>
						</td>
						<td>
							<div class="small font-weight-600">{{ $rc['responsible'] }}</div>
							<small class="text-muted"><i class="fa-solid fa-clock mr-1"></i>{{ $rc['lead_days'] }} días transcurridos</small>
						</td>
						<td class="text-center">
							<span class="badge {{ $rc['status_badge'] }}">{{ $rc['status_label'] }}</span>
						</td>
						<td class="text-center">
							<div class="btn-group btn-group-sm">
								<button class="btn btn-outline-secondary btn-xs" title="Ver bitácora y auditoría" onclick="openRecCaseDetail({{ json_encode($rc) }})">
									<i class="fa-solid fa-eye"></i>
								</button>
								@if($rc['status'] !== 'confirmed')
								<button class="btn btn-outline-success btn-xs" title="Confirmar recuperación contable" onclick="confirmRecoveryPrompt('{{ $rc['case_number'] }}', {{ $rc['claim_amount'] }})">
									<i class="fa-solid fa-receipt"></i>
								</button>
								@endif
							</div>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- MODAL: FLUJO DE 10 ETAPAS DE RECUPERACIÓN FINANCIERA           -->
<!-- ============================================================== -->
<div class="modal fade" id="modalRecuperacionWorkflow" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-diagram-project text-success mr-2"></i>Proceso Estándar de Recuperación en 10 Etapas
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-4">
				<p class="text-muted small mb-4">
					Protocolo operacional mandatorio para garantizar la compensación de pérdidas atribuibles a proveedores, operadores logísticos o contingencias cubiertas por seguros.
				</p>
				<div class="row">
					@php
						$steps = [
							['num' => 1, 'title' => 'Detección y evidencia', 'desc' => 'Identificación de la novedad durante recepción, almacenamiento o auditoría.', 'icon' => 'fa-camera'],
							['num' => 2, 'title' => 'Clasificación del reclamo', 'desc' => 'Tipificación formal: proveedor comercial, transportista o aseguradora.', 'icon' => 'fa-tags'],
							['num' => 3, 'title' => 'Documentación y soporte', 'desc' => 'Consolidación de factura, remisión, fotos de empaque y acta de descargue.', 'icon' => 'fa-file-invoice'],
							['num' => 4, 'title' => 'Envío formal al tercero', 'desc' => 'Radicación de reclamo oficial con folio único REC-XXXX y requerimiento.', 'icon' => 'fa-paper-plane'],
							['num' => 5, 'title' => 'Acuse de recibo', 'desc' => 'Registro de número de radicado del tercero y asignación de SLA pericial.', 'icon' => 'fa-envelope-open-text'],
							['num' => 6, 'title' => 'Evaluación técnica', 'desc' => 'Inspección física conjunta, toma de contramuestra o peritaje de seguro.', 'icon' => 'fa-microscope'],
							['num' => 7, 'title' => 'Negociación / Conciliación', 'desc' => 'Acuerdo de valor final reconocido y porcentaje de indemnización.', 'icon' => 'fa-handshake'],
							['num' => 8, 'title' => 'Emisión de soporte financiero', 'desc' => 'Recepción formal de nota de crédito, cheque de reposición o transferencia.', 'icon' => 'fa-receipt'],
							['num' => 9, 'title' => 'Confirmación contable & Kardex', 'desc' => 'Ajuste de inventario en Kardex y aplicación contable en cuentas por pagar.', 'icon' => 'fa-scale-balanced'],
							['num' => 10, 'title' => 'Cierre de expediente', 'desc' => 'Verificación de fondos recuperados, archivo auditable y evaluación causal.', 'icon' => 'fa-box-archive']
						];
					@endphp
					@foreach($steps as $st)
					<div class="col-md-6 mb-3">
						<div class="d-flex align-items-start p-2 border rounded bg-light h-100">
							<div class="rounded-circle bg-white border d-flex align-items-center justify-content-center text-primary font-weight-bold mr-2.5 flex-shrink-0" style="width:36px; height:36px; font-size:0.88rem;">
								{{ $st['num'] }}
							</div>
							<div>
								<div class="font-weight-bold text-dark small"><i class="fa-solid {{ $st['icon'] }} mr-1 text-muted"></i>{{ $st['title'] }}</div>
								<div class="small text-muted" style="line-height:1.25;">{{ $st['desc'] }}</div>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-sm btn-secondary font-weight-600" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- ============================================================== -->
<!-- MODAL: EXPEDIENTE AUDITABLE Y BITÁCORA                          -->
<!-- ============================================================== -->
<div class="modal fade" id="modalRecCaseDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light">
				<h5 class="modal-title font-weight-bold mb-0">
					<i class="fa-solid fa-folder-open text-primary mr-2"></i>Expediente Auditable: <span id="mrcFolio"></span>
				</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body p-3">
				<div class="mb-2">
					<span class="text-muted small d-block">Tercero Reclamado:</span>
					<strong class="text-dark" id="mrcThird"></strong>
				</div>
				<div class="mb-2">
					<span class="text-muted small d-block">Mercancía Afectada:</span>
					<div class="text-dark small" id="mrcProduct"></div>
				</div>
				<div class="row mb-3">
					<div class="col-6">
						<span class="text-muted small d-block">Monto Reclamado:</span>
						<strong class="text-info h6" id="mrcClaim"></strong>
					</div>
					<div class="col-6">
						<span class="text-muted small d-block">Monto Recuperado Confirmado:</span>
						<strong class="text-success h6" id="mrcRec"></strong>
					</div>
				</div>
				<div class="p-2 bg-light rounded border mb-3">
					<div class="d-flex justify-content-between small font-weight-bold mb-1">
						<span>Etapa Actual:</span>
						<span class="text-primary" id="mrcStep"></span>
					</div>
					<div class="small text-muted" id="mrcResp"></div>
				</div>
				<div class="alert alert-info py-2 small mb-0 border-0">
					<i class="fa-solid fa-shield-halved mr-1"></i>Distinción contable: Este valor solo se integra a la utilidad real una vez confirmada la Nota de Crédito o transferencia bancaria.
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-sm btn-secondary font-weight-600" data-dismiss="modal">Cerrar Expediente</button>
			</div>
		</div>
	</div>
</div>

<script>
function recFilterType(type) {
	$('.rec-row').each(function() {
		if (type === 'all' || $(this).data('type') === type) {
			$(this).show();
		} else {
			$(this).hide();
		}
	});
}

function openRecCaseDetail(rc) {
	$('#mrcFolio').text(rc.case_number);
	$('#mrcThird').text(rc.third_party + ' (' + rc.type_label + ')');
	$('#mrcProduct').text(rc.product);
	$('#mrcClaim').text('$' + Number(rc.claim_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }));
	$('#mrcRec').text('$' + Number(rc.recovered_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }));
	$('#mrcStep').text(rc.step_name);
	$('#mrcResp').text('Responsable: ' + rc.responsible + ' · Radicado: ' + rc.dates.created);
	$('#modalRecCaseDetail').modal('show');
}

function confirmRecoveryPrompt(folio, amount) {
	bootbox.prompt({
		title: 'Confirmar Recuperación Financiera para ' + folio,
		message: '<p class="small text-muted">Ingrese el número de Nota de Crédito / comprobante bancario para confirmar el cobro de $' + Number(amount).toLocaleString('en-US') + ':</p>',
		inputType: 'text',
		placeholder: 'Ej: NC-2026-99412',
		callback: function(result) {
			if (result) {
				toastr.success('Recuperación confirmada y registrada en Kardex para ' + folio + ' (Comprobante: ' + result + ')');
			}
		}
	});
}
</script>
