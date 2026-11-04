@extends('layout.default')

@section('title', 'Expediente ' . $d['case']['case_code'] . ' — Prevención de Pérdidas 360°')

@section('content')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
@php
	$money = fn($v) => '$' . number_format(floatval($v), 2, '.', ',');
	$c = $d['case'];
	$p = $d['product'];
	$f = $d['formula'];
	$pos = $d['positions'];
	$last = $d['last_known'];
	$flow = ['detectado' => 'Detectado', 'reconteo' => 'Reconteo', 'en_investigacion' => 'En investigación', 'pendiente_aprobacion' => 'Pendiente de aprobación'];
	$order = array_keys($flow);
	$at = array_search($c['workflow_status'], $order);
	$final = $at === false;
	$levelLabel = ['danger' => ['Crítico', 'fa-circle-exclamation'], 'warning' => ['Advertencia', 'fa-triangle-exclamation'], 'success' => ['Controlado', 'fa-circle-check']];
	$signed = fn($v) => ($v > 0 ? '+' : '') . floatval($v);
@endphp

<div class="lp-page">
	{{-- Encabezado con Botón Principal "Resolver Incidencia" --}}
	<div class="lp-head mb-3">
		<div>
			<div class="d-flex align-items-center gap-2 mb-1">
				<span class="badge badge-dark font-weight-bold p-1 px-2">{{ $c['case_code'] }}</span>
				@if($c['is_demo'])<span class="lp-badge lp-demo">Demostración</span>@endif
				<span class="lp-badge lp-{{ $c['level'] }}"><i class="fa-solid {{ $levelLabel[$c['level']][1] }}"></i> {{ $levelLabel[$c['level']][0] }}</span>
				@if(($c['escalation_level'] ?? 0) > 0)
				<span class="lp-badge lp-danger"><i class="fa-solid fa-arrow-trend-up mr-1"></i> Escalado Nivel {{ $c['escalation_level'] }}</span>
				@endif
			</div>
			<h1 class="m-0 font-weight-bold">{{ $p['name'] }}</h1>
			<p class="text-muted m-0">{{ $d['branch'] }} · {{ $c['department'] }} · Zona: {{ $c['zone'] ?: 'Piso de venta' }} · UPC {{ $p['upc'] ?: '—' }} · SKU {{ $p['sku'] ?: '—' }}</p>
		</div>
		<div class="d-flex flex-wrap align-items-center gap-2">
			@if(!$final)
			<button type="button" class="lp-btn lp-btn-resolve" onclick="launchCaseResolverFlow()">
				<i class="fa-solid fa-bolt"></i> Resolver incidencia
			</button>
			<button type="button" class="lp-btn lp-btn-call" onclick="openCaseCallModal()">
				<i class="fa-solid fa-phone"></i> LLAMAR
			</button>
			<button type="button" class="lp-btn lp-btn-msg" onclick="openCaseMsgModal()">
				<i class="fa-solid fa-comment-dots"></i> MENSAJE
			</button>
			<button type="button" class="lp-btn" onclick="openCaseTaskModal()">
				<i class="fa-solid fa-list-check"></i> ASIGNAR TAREA
			</button>
			<button type="button" class="lp-btn lp-btn-escalate" onclick="openCaseEscalateModal()">
				<i class="fa-solid fa-arrow-trend-up"></i> ESCALAR
			</button>
			@endif
			<a class="lp-btn" href="{{ $U('/loss-prevention') }}"><i class="fa-solid fa-arrow-left"></i> Centro de Comando</a>
		</div>
	</div>

	{{-- Banner de Relevo de Turno Activo (Handover para el cambio de guardia) --}}
	@if(!empty($d['latest_handover']))
	<div class="lp-handover-banner">
		<div class="d-flex align-items-center gap-3">
			<div style="font-size: 1.8rem; color: #0284c7;"><i class="fa-solid fa-people-arrows"></i></div>
			<div>
				<div class="font-weight-bold" style="color: #0369a1; font-size: 0.95rem;">
					Relevo de Turno Gerencial Activo:
				</div>
				<div class="lp-handover-text mt-1">
					"{{ $d['latest_handover']['note'] }}"
				</div>
				<small class="text-muted">
					Registrado por <strong>{{ $d['latest_handover']['user'] }}</strong> · {{ substr($d['latest_handover']['at'], 0, 16) }} · No necesita reiniciar la investigación.
				</small>
			</div>
		</div>
		<button type="button" class="lp-btn" style="min-height: 32px; padding: 4px 12px; font-size: 0.8rem;" onclick="openHandoverModal()">
			<i class="fa-solid fa-pen-to-square mr-1"></i> Actualizar Relevo
		</button>
	</div>
	@else
	<div class="d-flex justify-content-end mb-2">
		<button type="button" class="lp-btn" style="min-height: 30px; padding: 3px 10px; font-size: 0.8rem;" onclick="openHandoverModal()">
			<i class="fa-solid fa-people-arrows mr-1"></i> Dejar Nota de Relevo para el Siguiente Turno
		</button>
	</div>
	@endif

	{{-- Barra de Etapas del Flujo Operativo --}}
	<ol class="lp-steps mb-3">
		@foreach($flow as $code => $label)
		<li class="{{ $final || $loop->index < $at ? 'done' : ($loop->index === $at ? 'now' : '') }}">{{ $label }}</li>
		@endforeach
		<li class="{{ $final ? ($c['workflow_status'] === 'resuelto' ? 'done' : 'now') : '' }}">{{ $final ? $c['status_label'] : 'Resuelto / No resuelto' }}</li>
	</ol>

	{{-- 4 KPIs Clave del Expediente --}}
	<div class="lp-kpis">
		<div class="lp-kpi"><small>Inventario esperado</small><strong>{{ floatval($c['expected_stock']) }} u.</strong></div>
		<div class="lp-kpi"><small>Inventario físico</small><strong>{{ floatval($c['physical_count']) }} u.</strong></div>
		<div class="lp-kpi"><small>Diferencia faltante</small><strong class="{{ floatval($c['difference_qty']) < 0 ? 'text-danger' : 'text-success' }}">{{ $signed($c['difference_qty']) }} u.</strong></div>
		<div class="lp-kpi"><small>Valor en riesgo</small><strong class="text-danger">{{ $money($c['financial_impact']) }}</strong></div>
	</div>

	<div class="lp-grid-3">
		<div>
			{{-- LÍNEA DE TIEMPO UNIFICADA 360° (Comunicación, Inspección, Evidencias, Aprobación, Relevo) --}}
			<div class="lp-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div>
						<h2 class="m-0"><i class="fa-solid fa-timeline mr-1 text-primary"></i> Línea de Tiempo Unificada 360°</h2>
						<small class="text-muted">Registro consolidado de llamadas, mensajes, check-ins, escaneos, evidencias, tareas y relevos de guardia.</small>
					</div>
					<button type="button" class="lp-btn" style="min-height: 28px; padding: 2px 8px; font-size: 0.75rem;" onclick="openHandoverModal()">
						<i class="fa-solid fa-people-arrows mr-1"></i> Relevo
					</button>
				</div>

				<div class="lp-unified-feed">
					@forelse($d['unified_timeline'] ?? [] as $item)
					<div class="lp-feed-item">
						<div class="lp-feed-icon icon-{{ $item['color'] }}">
							<i class="fa-solid {{ $item['icon'] }}"></i>
						</div>
						<div class="lp-feed-body">
							<div class="lp-feed-header">
								<h4 class="lp-feed-title">{{ $item['title'] }}</h4>
								<span class="lp-feed-time">{{ substr($item['at'], 0, 16) }}</span>
							</div>
							<p class="lp-feed-detail">{{ $item['detail'] }}</p>
							<span class="lp-feed-user"><i class="fa-solid fa-user-circle mr-1"></i> Por: {{ $item['user'] }}</span>
						</div>
					</div>
					@empty
					<p class="text-muted small p-3 text-center">Sin eventos registrados aún en la línea de tiempo unificada.</p>
					@endforelse
				</div>
			</div>

			{{-- Tareas Multi-Área del Expediente --}}
			<div class="lp-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h2 class="m-0"><i class="fa-solid fa-list-check mr-1 text-primary"></i> Tareas Multi-Área Asignadas</h2>
					@if(!$final)
					<button type="button" class="lp-btn" style="min-height: 28px; padding: 2px 8px; font-size: 0.75rem;" onclick="openCaseTaskModal()">
						<i class="fa-solid fa-plus"></i> Nueva Tarea
					</button>
					@endif
				</div>
				<div class="lp-scroll">
					<table class="lp-table">
						<thead><tr><th>Área / Depto</th><th>Tarea</th><th>Responsable</th><th>Límite</th><th>Estado</th><th></th></tr></thead>
						<tbody>
							@forelse($d['tasks'] ?? [] as $t)
							<tr>
								<td><span class="lp-task-dept-badge dept-{{ strtolower(str_replace(' ', '', $t['department'] ?? 'digital')) }}">{{ $t['department'] }}</span></td>
								<td>
									<strong>{{ $t['title'] }}</strong>
									@if($t['description'])<small class="d-block text-muted">{{ $t['description'] }}</small>@endif
									@if($t['result_notes'])<small class="d-block text-success font-weight-bold">Resultado: {{ $t['result_notes'] }}</small>@endif
								</td>
								<td>{{ $t['assigned_employee_name'] ?: $t['assigned_username'] ?: 'Sin asignar' }}</td>
								<td>{{ substr($t['due_at'], 0, 16) }}</td>
								<td><span class="badge badge-{{ $t['status'] === 'completed' ? 'success' : ($t['status'] === 'in_progress' ? 'primary' : 'warning') }}">{{ ucfirst($t['status']) }}</span></td>
								<td>
									@if($t['status'] !== 'completed')
									<button type="button" class="lp-btn lp-btn-primary" style="min-height: 26px; padding: 2px 6px; font-size: 0.72rem;" onclick="completeCaseTaskDirect({{ $t['id'] }})">Listo</button>
									@endif
								</td>
							</tr>
							@empty
							<tr><td colspan="6" class="text-center text-muted p-3">Sin tareas asignadas aún. Use "ASIGNAR TAREA" para coordinar con Digital, Inventario, POS o Despachos.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>

			{{-- Conciliación y Fórmula Esperada --}}
			<div class="lp-card">
				<h2>Conciliación <small>stock esperado al momento del conteo</small></h2>
				<table class="lp-table"><tbody>
					<tr><td>Stock inicial</td><td class="lp-num">{{ $signed($f['opening']) }}</td></tr>
					@foreach($d['bucket_labels'] as $code => $label)
					<tr><td>{{ $label }}</td><td class="lp-num">{{ $signed($f['buckets'][$code]) }}</td></tr>
					@endforeach
					<tr><td><strong>Stock esperado</strong></td><td class="lp-num"><strong>{{ floatval($f['expected']) }}</strong></td></tr>
				</tbody></table>
				@if(abs($f['book_gap']) > 0.0001)
				<p class="lp-note">Incluye {{ floatval($f['book_gap']) }} u. cargadas sin movimiento en el historial.</p>
				@endif
			</div>

			{{-- Historial Cronológico Reconstruido 360° (Kardex Audit) --}}
			<div class="lp-card">
				<h2>Reconstrucción Cronológica de Kardex <small>Ventas POS, Recepciones, Despachos, Devoluciones y Traslados</small></h2>
				@if(count($d['timeline']) === 0)
				<p class="lp-note">Sin movimientos registrados.</p>
				@else
				<ul class="lp-timeline">
					@foreach($d['timeline'] as $t)
					<li>
						<span>{{ $t['at'] }}</span>
						<span><strong>{{ $t['label'] }}</strong>@if($t['late']) <span class="lp-badge lp-neutral">Sincronizado después</span>@endif<small>{{ $t['document'] }} · {{ $t['location'] }} · Registró: {{ $t['user'] ?: '—' }}</small></span>
						<span class="lp-num font-weight-bold">{{ $t['kind'] === 'count' ? floatval($t['qty']) . ' contadas' : $signed($t['qty']) }}</span>
					</li>
					@endforeach
				</ul>
				@endif
			</div>

			{{-- Bitácora de Auditoría Hash Inalterable --}}
			<div class="lp-card">
				<h2>Bitácora Encadenada de Auditoría <span class="lp-badge {{ $d['chain_ok'] ? 'lp-success' : 'lp-danger' }}"><i class="fa-solid {{ $d['chain_ok'] ? 'fa-lock' : 'fa-circle-exclamation' }}"></i> {{ $d['chain_ok'] ? 'Cadena Íntegra' : 'Alterada' }}</span></h2>
				<ul class="lp-timeline">
					@foreach($d['events'] as $e)
					<li><span>{{ $e['created_at'] }}</span><span><strong>{{ $e['to_status'] && $e['to_status'] !== $e['from_status'] ? ($flow[$e['to_status']] ?? ucfirst(str_replace('_', ' ', $e['to_status']))) : ucfirst(str_replace('_', ' ', $e['action'])) }}</strong><small>{{ $e['note'] }}</small></span><span>{{ $e['username'] ?: 'Sistema' }}</span></li>
					@endforeach
				</ul>
			</div>
		</div>

		{{-- PANEL LATERAL --}}
		<div class="lp-side">
			{{-- Acciones Correctivas y Segregación de Funciones --}}
			<div class="lp-card">
				<h2>Acciones Correctivas y Resolución</h2>
				<p class="m-0 mb-2">
					<span class="lp-badge lp-neutral">{{ \Grocy\Services\MerchandiseService::Classify($c) }}</span>
					@if($c['confirmed_cause']) <span class="lp-badge lp-neutral">Causa: {{ collect($d['causes'])->firstWhere('code', $c['confirmed_cause'])['name'] ?? $c['confirmed_cause'] }}</span>@endif
				</p>
				@if($final)
				<div class="alert alert-secondary p-2 small mb-2">
					<strong>{{ $c['status_label'] }}:</strong> {{ $c['resolution_notes'] }}
				</div>
				@if($d['can']['supervise'] && empty($c['device_id']))
				<textarea id="lpReopenNote" class="lp-field mb-2" rows="2" placeholder="Evidencia nueva para reabrir..." aria-label="Evidencia nueva"></textarea>
				<button class="lp-btn btn-block" id="lpReopen" type="button">Reabrir expediente</button>
				@endif
				@else
				@if($d['can']['supervise'] && in_array($c['workflow_status'], ['en_investigacion', 'pendiente_aprobacion'], true))
				<select id="lpCause" class="lp-field mb-2" aria-label="Causa confirmada"><option value="">Seleccione causa confirmada…</option>@foreach($d['causes'] as $cz)<option value="{{ $cz['code'] }}">{{ $cz['name'] }}</option>@endforeach</select>
				<button class="lp-btn mb-2 btn-block" id="lpCauseSave" type="button">Registrar causa confirmada</button>
				@endif
				<div class="d-flex flex-column gap-2 mb-2">
					@if($d['can']['supervise'] && $c['workflow_status'] === 'en_investigacion')
					<button class="lp-btn btn-block" data-act="recount"><i class="fa-solid fa-rotate mr-1"></i> Solicitar reconteo</button>
					<button class="lp-btn lp-btn-primary btn-block" data-act="propose"><i class="fa-solid fa-file-pen mr-1"></i> Proponer ajuste patrimonial</button>
					@endif
					@if($d['can']['approve'] && $c['workflow_status'] === 'pendiente_aprobacion')
					<button class="lp-btn lp-btn-primary btn-block" data-act="approve"><i class="fa-solid fa-signature mr-1"></i> Aprobar ajuste (Gerente)</button>
					<button class="lp-btn lp-btn-danger btn-block" data-act="reject"><i class="fa-solid fa-ban mr-1"></i> Rechazar propuesta</button>
					@endif
					@if($c['workflow_status'] === 'reconteo')
					<a class="lp-btn lp-btn-primary btn-block text-center" href="{{ $U('/loss-prevention/counts') }}"><i class="fa-solid fa-barcode mr-1"></i> Ir al reconteo</a>
					@endif
					@if($d['can']['approve'] && in_array($c['workflow_status'], ['en_investigacion', 'pendiente_aprobacion'], true))
					<button class="lp-btn btn-block" data-act="resolve"><i class="fa-solid fa-check mr-1"></i> Cerrar: resuelto</button>
					<button class="lp-btn lp-btn-danger btn-block" data-act="unresolved"><i class="fa-solid fa-xmark mr-1"></i> Cerrar: no resuelto (pérdida)</button>
					@endif
				</div>
				@if($c['proposed_reason'])<div class="alert alert-info small p-2 m-0 mt-2"><strong>Propuesta:</strong> {{ $c['proposed_reason'] }}</div>@endif
				<textarea id="lpActionNote" class="lp-field mt-2" rows="2" placeholder="Justificación (obligatoria para ajustes y cierres)"></textarea>
				@endif
			</div>

			{{-- Acciones correctivas formalizadas --}}
			<div class="lp-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h2 class="m-0">Acciones Registradas</h2>
					@if(!$final)
					<button type="button" class="lp-btn" style="min-height: 26px; padding: 2px 6px; font-size: 0.72rem;" onclick="openNewActionDialog()"><i class="fa-solid fa-plus"></i></button>
					@endif
				</div>
				<div class="lp-scroll">
					<table class="lp-table">
						<thead><tr><th>Acción</th><th>Estado</th></tr></thead>
						<tbody>
							@forelse($d['actions'] ?? [] as $act)
							<tr>
								<td>
									<strong>{{ $act['title'] }}</strong>
									<small class="d-block text-muted">{{ $act['action_type'] }} · {{ $act['responsible_name'] }}</small>
								</td>
								<td><span class="badge badge-{{ $act['status'] === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($act['status']) }}</span></td>
							</tr>
							@empty
							<tr><td colspan="2" class="text-muted small">Sin acciones adicionales registradas.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>

			{{-- Contactos Telefónicos --}}
			<div class="lp-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h2 class="m-0"><i class="fa-solid fa-phone text-success mr-1"></i> Contactos Telefónicos</h2>
					@if(!$final)
					<button type="button" class="lp-btn lp-btn-call" style="min-height: 26px; padding: 2px 6px; font-size: 0.72rem;" onclick="openCaseCallModal()"><i class="fa-solid fa-phone"></i></button>
					@endif
				</div>
				<div class="lp-scroll">
					@forelse($d['calls'] ?? [] as $cl)
					<div class="p-2 border-bottom small">
						<strong>{{ $cl['contact_name'] }}</strong> ({{ $cl['contact_phone'] }})
						<div>Resultado: <span class="badge badge-{{ $cl['call_result'] === 'answered' ? 'success' : 'secondary' }}">{{ $cl['call_result'] }}</span></div>
						@if($cl['notes'])<div class="text-muted">{{ $cl['notes'] }}</div>@endif
						<small class="text-muted">{{ $cl['caller_name'] }} · {{ substr($cl['created_at'], 0, 16) }}</small>
					</div>
					@empty
					<p class="text-muted small m-0">No se han registrado llamadas a responsables.</p>
					@endforelse
				</div>
			</div>

			{{-- Llegadas al Lugar / Check-in in-situ --}}
			<div class="lp-card">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h2 class="m-0"><i class="fa-solid fa-shoe-prints text-primary mr-1"></i> Llegadas In-Situ</h2>
					@if(!$final)
					<button type="button" class="lp-btn" style="min-height: 26px; padding: 2px 6px; font-size: 0.72rem;" onclick="recordInSituCheckin()"><i class="fa-solid fa-location-dot"></i> Check-in</button>
					@endif
				</div>
				<div class="lp-scroll">
					@forelse($d['checkins'] ?? [] as $ck)
					<div class="p-2 border-bottom small">
						<strong>{{ $ck['zone_name'] }}</strong> · {{ substr($ck['arrived_at'], 0, 16) }}
						<div class="text-muted">Por {{ $ck['username'] }} @if($ck['notes'])· {{ $ck['notes'] }}@endif</div>
					</div>
					@empty
					<p class="text-muted small m-0">Sin registros de llegada in-situ.</p>
					@endforelse
				</div>
			</div>

			{{-- Evidencias --}}
			<div class="lp-card">
				<h2>Evidencia Autorizada</h2>
				@foreach($d['evidence'] as $e)
				<p class="m-0 mb-2" style="font-size:.85rem"><strong>{{ $e['title'] }}</strong>
					@if($e['file_name'])· <a href="{{ $U('/api/loss-control/evidence/' . $e['id']) }}" target="_blank" rel="noopener">Ver archivo</a>@endif
					<small class="d-block text-muted">{{ $e['description'] }}@if($e['camera_ref']) Cámara: {{ $e['camera_ref'] }} · Autorizó: {{ $e['authorized_by'] }}@endif · {{ $e['username'] }} · {{ $e['created_at'] }}</small></p>
				@endforeach
				@if(!$final)
				<form id="lpEvidence">
					<input class="lp-field mb-2" name="title" placeholder="Título de la evidencia" required aria-label="Título">
					<select class="lp-field mb-2" name="type" id="lpEvType" aria-label="Tipo"><option value="note">Nota</option><option value="document">Foto o documento</option><option value="camera">Referencia de cámara CCTV</option></select>
					<textarea class="lp-field mb-2" name="description" rows="2" placeholder="Descripción de los hechos observados" aria-label="Descripción"></textarea>
					<input class="lp-field mb-2" name="file" type="file" accept="image/*,application/pdf" hidden id="lpEvFile" aria-label="Archivo">
					<div id="lpEvCamera" hidden>
						<input class="lp-field mb-2" name="camera_ref" placeholder="Cámara, fecha y hora del video" aria-label="Referencia">
						<input class="lp-field mb-2" name="authorized_by" placeholder="Quién autorizó la consulta de CCTV" aria-label="Autorización">
						<p class="lp-note">DAILY guarda la referencia auditada de quién y cuándo autorizó la consulta.</p>
					</div>
					<button class="lp-btn btn-block" type="submit"><i class="fa-solid fa-paperclip mr-1"></i> Adjuntar Evidencia</button>
				</form>
				@endif
			</div>
		</div>
	</div>
</div>

{{-- MODAL FLUIDO "RESOLVER INCIDENCIA" (Mobile First: El Problema, Persona, Ubicación, Verificaciones y Acciones) --}}
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

{{-- MODAL RELEVO DE TURNO RÁPIDO --}}
<div id="modalHandoverDialog" class="lp-modal-overlay" hidden>
	<div class="lp-modal-box">
		<div class="lp-modal-header">
			<h3 class="lp-modal-title"><i class="fa-solid fa-people-arrows text-primary mr-2"></i> Relevo de Turno Gerencial</h3>
			<button type="button" class="lp-modal-close" onclick="closeModal('modalHandoverDialog')">&times;</button>
		</div>
		<form id="formHandoverNote" onsubmit="submitHandoverNote(event)">
			<div class="alert alert-info small p-2 mb-3">
				<i class="fa-solid fa-circle-info mr-1"></i> Esta nota quedará destacada en la parte superior del expediente para que el manager del siguiente turno continúe la investigación sin empezar desde cero.
			</div>
			<div class="form-group mb-3">
				<label class="small font-weight-bold">Novedad / Estado de la Investigación para el Relevo:</label>
				<textarea id="handoverNoteText" class="lp-field" rows="4" placeholder="Ej: Se realizó conteo en vitrina física. El supervisor Carlos verificará las ventas en POS antes de las 16:00. Dejo la mercancía bajo custodia." required></textarea>
			</div>
			<div class="d-flex justify-content-end gap-2">
				<button type="button" class="lp-btn" onclick="closeModal('modalHandoverDialog')">Cancelar</button>
				<button type="submit" class="lp-btn lp-btn-primary"><i class="fa-solid fa-check mr-1"></i> Guardar Relevo</button>
			</div>
		</form>
	</div>
</div>

{{-- MODAL DE LLAMADA --}}
<div id="caseModalCall" class="lp-modal-overlay" hidden>
	<div class="lp-modal-box">
		<div class="lp-modal-header">
			<h3 class="lp-modal-title"><i class="fa-solid fa-phone text-success mr-2"></i> Contactar a Responsable</h3>
			<button type="button" class="lp-modal-close" onclick="closeModal('caseModalCall')">&times;</button>
		</div>
		<form id="formCaseCallRecord" onsubmit="submitCaseCall(event)">
			<div class="alert alert-warning p-2 small mb-3">
				<i class="fa-solid fa-shield-halved mr-1"></i> No grabar llamadas sin consentimiento y autorización aplicable. No se registra como contestada si solo se abrió el marcador.
			</div>
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Seleccionar Contacto:</label>
				<select id="caseCallContactSelect" class="lp-field" onchange="onCaseSelectCallContact(this)" required>
					<option value="">Seleccione personal...</option>
					@foreach($d['authorized_contacts'] ?? [] as $st)
					<option value="{{ $st['phone'] }}" data-name="{{ $st['name'] }}" data-role="{{ $st['position'] }}" data-empid="{{ $st['employee_id'] }}" data-userid="{{ $st['user_id'] }}">
						{{ $st['name'] }} ({{ $st['position'] }} - {{ $st['department'] }}) · {{ $st['phone'] }}
					</option>
					@endforeach
				</select>
			</div>
			<div class="mb-3">
				<a id="caseBtnTriggerDialer" href="#" class="lp-btn lp-btn-call btn-block text-center" target="_blank">
					<i class="fa-solid fa-phone-volume mr-1"></i> Iniciar Llamada Telefónica
				</a>
			</div>
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Resultado de la Llamada:</label>
				<select name="call_result" class="lp-field" required id="caseCallResultField">
					<option value="">Seleccione el resultado real...</option>
					<option value="answered">Contestó</option>
					<option value="no_answer">No contestó</option>
					<option value="busy">Ocupado</option>
					<option value="voicemail">Buzón de voz</option>
					<option value="callback">Devolución programada</option>
					<option value="wrong_number">Número no disponible</option>
				</select>
			</div>
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Notas de la Conversación:</label>
				<textarea name="notes" class="lp-field" rows="2" placeholder="Resumen del contacto..."></textarea>
			</div>
			<div class="d-flex justify-content-end gap-2">
				<button type="button" class="lp-btn" onclick="closeModal('caseModalCall')">Cancelar</button>
				<button type="submit" class="lp-btn lp-btn-primary">Registrar Llamada</button>
			</div>
		</form>
	</div>
</div>

{{-- MODAL DE MENSAJERÍA --}}
<div id="caseModalMsg" class="lp-modal-overlay" hidden>
	<div class="lp-modal-box">
		<div class="lp-modal-header">
			<h3 class="lp-modal-title"><i class="fa-solid fa-comment-dots text-primary mr-2"></i> Mensaje Interno</h3>
			<button type="button" class="lp-modal-close" onclick="closeModal('caseModalMsg')">&times;</button>
		</div>
		<form id="formCaseMsgSend" onsubmit="submitCaseDirectMsg(event)">
			<div class="lp-chat-container mb-3">
				<div class="lp-chat-msgs" id="caseDirectChatList">
					@forelse($d['thread_data']['messages'] ?? [] as $m)
					<div class="lp-chat-bubble {{ $m['sender_user_id'] == GROCY_USER_ID ? 'mine' : 'theirs' }}">
						@if($m['is_priority'])<span class="badge badge-danger mr-1">PRIORITARIO</span>@endif
						<strong>{{ $m['username'] ?: 'Usuario' }}:</strong> {{ $m['body'] }}
						<div class="lp-chat-meta">{{ substr($m['created_at'], 11, 5) }}</div>
					</div>
					@empty
					<div class="text-center text-muted small p-2">Sin mensajes en el hilo de esta incidencia.</div>
					@endforelse
				</div>
			</div>
			<div class="form-group mb-2">
				<textarea id="caseDirectMsgBody" class="lp-field" rows="3" placeholder="Escriba el mensaje para el equipo..." required></textarea>
			</div>
			<div class="d-flex align-items-center justify-content-between mb-3">
				<label class="m-0 d-flex align-items-center small font-weight-bold text-danger gap-1">
					<input type="checkbox" id="caseDirectMsgPrio" value="1"> Mensaje Prioritario
				</label>
			</div>
			<div class="d-flex justify-content-end gap-2">
				<button type="button" class="lp-btn" onclick="closeModal('caseModalMsg')">Cerrar</button>
				<button type="submit" class="lp-btn lp-btn-msg"><i class="fa-solid fa-paper-plane mr-1"></i> Enviar</button>
			</div>
		</form>
	</div>
</div>

{{-- MODAL ASIGNAR TAREA --}}
<div id="caseModalTask" class="lp-modal-overlay" hidden>
	<div class="lp-modal-box">
		<div class="lp-modal-header">
			<h3 class="lp-modal-title"><i class="fa-solid fa-tasks text-info mr-2"></i> Asignar Tarea Operativa</h3>
			<button type="button" class="lp-modal-close" onclick="closeModal('caseModalTask')">&times;</button>
		</div>
		<form id="formCaseTaskCreate" onsubmit="submitCaseTask(event)">
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Título de la Tarea:</label>
				<input type="text" id="caseTaskTitle" class="lp-field" placeholder="Ej: Realizar reconteo en vitrina y confirmar IMEI" required>
			</div>
			<div class="lp-grid mb-2">
				<div>
					<label class="small font-weight-bold">Área / Departamento:</label>
					<select id="caseTaskDept" class="lp-field" required>
						<option value="Digital">Digital / Telefonía</option>
						<option value="Inventario">Inventario / Bodega</option>
						<option value="POS">POS / Cajas</option>
						<option value="Despachos">Despachos</option>
						<option value="Prevención de Pérdidas">Prevención de Pérdidas</option>
						<option value="Gerencia">Gerencia</option>
					</select>
				</div>
				<div>
					<label class="small font-weight-bold">Prioridad:</label>
					<select id="caseTaskPriority" class="lp-field">
						<option value="urgent">Urgente</option>
						<option value="high">Alta</option>
						<option value="medium" selected>Media</option>
					</select>
				</div>
			</div>
			<div class="form-group mb-2">
				<label class="small font-weight-bold">Responsable en Turno:</label>
				<select id="caseTaskAssignee" class="lp-field" required>
					<option value="">Seleccione personal...</option>
					@foreach($d['authorized_contacts'] ?? [] as $st)
					<option value="{{ $st['user_id'] ?: 1 }}" data-empid="{{ $st['employee_id'] }}">
						{{ $st['name'] }} ({{ $st['position'] }})
					</option>
					@endforeach
				</select>
			</div>
			<div class="form-group mb-3">
				<label class="small font-weight-bold">Descripción / Instrucciones:</label>
				<textarea id="caseTaskDesc" class="lp-field" rows="2" placeholder="Detalles de la verificación..."></textarea>
			</div>
			<div class="d-flex justify-content-end gap-2">
				<button type="button" class="lp-btn" onclick="closeModal('caseModalTask')">Cancelar</button>
				<button type="submit" class="lp-btn lp-btn-primary">Asignar Tarea</button>
			</div>
		</form>
	</div>
</div>

<script>
(function ()
{
	var id = {{ intval($c['id']) }};
	var fail = function (x) { toastr.error(JSON.parse(x.responseText || '{}').error_message || 'No se pudo completar'); };
	var done = function () { location.reload(); };
	var note = document.getElementById('lpActionNote');
	var need = function ()
	{
		var v = note ? note.value.trim() : '';
		if (!v)
		{
			toastr.warning('Escriba la justificación');
			if (note) note.focus();
		}
		return v;
	};
	var actions = {
		recount: function () { Grocy.Api.Post('loss-control/cases/' + id + '/recount-request', { note: note ? note.value : '' }, done, fail); },
		propose: function () { var v = need(); if (v) Grocy.Api.Post('loss-control/cases/' + id + '/propose', { reason: v }, done, fail); },
		approve: function () { var v = need(); if (v) Grocy.Api.Post('loss-control/cases/' + id + '/decide', { decision: 'approved', reason: v }, done, fail); },
		reject: function () { var v = need(); if (v) Grocy.Api.Post('loss-control/cases/' + id + '/decide', { decision: 'rejected', reason: v }, done, fail); },
		resolve: function () { var v = need(); if (v) Grocy.Api.Post('loss-control/cases/' + id + '/close', { outcome: 'resuelto', justification: v }, done, fail); },
		unresolved: function () { var v = need(); if (v) Grocy.Api.Post('loss-control/cases/' + id + '/close', { outcome: 'no_resuelto', justification: v }, done, fail); }
	};
	document.querySelectorAll('[data-act]').forEach(function (b)
	{
		b.addEventListener('click', function () { actions[b.dataset.act](); });
	});
	document.querySelectorAll('.lp-hyp').forEach(function (b)
	{
		b.addEventListener('click', function ()
		{
			var v = need();
			if (v)
			{
				Grocy.Api.Post('loss-control/hypotheses/' + b.dataset.id, { status: b.dataset.status, note: v }, done, fail);
			}
		});
	});
	var causeBtn = document.getElementById('lpCauseSave');
	if (causeBtn)
	{
		causeBtn.addEventListener('click', function ()
		{
			var v = need();
			if (v)
			{
				Grocy.Api.Post('loss-control/cases/' + id + '/cause', { cause: document.getElementById('lpCause').value, note: v }, done, fail);
			}
		});
	}
	var reopen = document.getElementById('lpReopen');
	if (reopen)
	{
		reopen.addEventListener('click', function () { Grocy.Api.Post('loss-control/cases/' + id + '/reopen', { evidence: document.getElementById('lpReopenNote').value }, done, fail); });
	}
	var form = document.getElementById('lpEvidence');
	if (form)
	{
		var type = document.getElementById('lpEvType');
		type.addEventListener('change', function ()
		{
			document.getElementById('lpEvFile').hidden = type.value !== 'document';
			document.getElementById('lpEvCamera').hidden = type.value !== 'camera';
		});
		form.addEventListener('submit', function (e)
		{
			e.preventDefault();
			$.ajax({ url: U('/api/loss-control/cases/' + id + '/evidence'), method: 'POST', data: new FormData(form), processData: false, contentType: false, success: done, error: fail });
		});
	}
})();

function closeModal(mId) {
	var m = document.getElementById(mId);
	if (m) m.hidden = true;
}

/* FLUID RESOLVER WIZARD */
var cWizStep = 1;
var cWizSerials = [];

var cWizStep = {{ intval($d['wizard_step'] ?? 1) }};
var cWizSerials = {!! json_encode($d['serials']['scanned'] ?? []) !!};
var cWizState = {!! json_encode($d['wizard_state'] ?? []) !!};

var cWizStepNames = {
	1: 'Ver Problema', 2: 'Consultar Ubicación', 3: 'Contactar Responsable',
	4: 'Asignar Tarea', 5: 'Ir a la Zona', 6: 'Iniciar Inspección',
	7: 'Escanear Mercancía', 8: 'Revisar Movimientos', 9: 'Registrar Hallazgos',
	10: 'Adjuntar Evidencia', 11: 'Seleccionar Corrección & 5P', 12: 'Solicitar Aprobación',
	13: 'Verificar Resultado', 14: 'Cerrar Caso', 15: 'Crear Prevención Futura'
};

function launchCaseResolverFlow() {
	switchCaseWizStep(cWizStep || 1);
	document.getElementById('caseModalResolverWizard').hidden = false;
	restoreCaseWizInputs();
}

function switchCaseWizStep(step) {
	cWizStep = Math.max(1, Math.min(15, step));
	var badge = document.getElementById('cWizCurrentStepBadge');
	var name = document.getElementById('cWizCurrentStepName');
	if (badge) badge.textContent = cWizStep;
	if (name) name.textContent = cWizStepNames[cWizStep] || ('Paso ' + cWizStep);

	for (var i = 1; i <= 15; i++) {
		var p = document.getElementById('cWizPane' + i);
		var t = document.getElementById('cSt' + i);
		if (p) p.hidden = (i !== cWizStep);
		if (t) {
			t.classList.toggle('active', i === cWizStep);
			t.classList.toggle('done', i < cWizStep);
		}
	}
	var activeEl = document.getElementById('cSt' + cWizStep);
	if (activeEl && activeEl.scrollIntoView) {
		activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
	}
}

function saveCaseWizStep(step, andNext) {
	var stepData = collectStepData(step);
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/wizard-step', {
		step: step,
		data: stepData,
		notes: 'Paso ' + step + ': ' + (cWizStepNames[step] || '')
	}, function(res) {
		toastr.success('Progreso guardado (Paso ' + step + ')');
		if (andNext) {
			switchCaseWizStep(step + 1);
		}
	});
}

function pauseWizForNextShift() {
	var stepData = collectStepData(cWizStep);
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/wizard-step', {
		step: cWizStep,
		data: stepData,
		notes: 'Guardado para relevo en paso ' + cWizStep
	}, function() {
		toastr.info('Progreso guardado. El siguiente manager continuará desde el Paso ' + cWizStep);
		closeModal('caseModalResolverWizard');
	});
}

function collectStepData(step) {
	var data = {};
	if (step === 2) {
		data.location_notes = document.getElementById('cWizStep2LocationNotes') ? document.getElementById('cWizStep2LocationNotes').value : '';
	} else if (step === 3) {
		data.call_outcome = document.getElementById('cWizStep3CallOutcome') ? document.getElementById('cWizStep3CallOutcome').value : '';
		data.call_notes = document.getElementById('cWizStep3CallNote') ? document.getElementById('cWizStep3CallNote').value : '';
	} else if (step === 4) {
		data.task_title = document.getElementById('cWizTaskTitle') ? document.getElementById('cWizTaskTitle').value : '';
		data.task_assignee = document.getElementById('cWizTaskAssignee') ? document.getElementById('cWizTaskAssignee').value : '';
		data.task_priority = document.getElementById('cWizTaskPriority') ? document.getElementById('cWizTaskPriority').value : '';
		data.task_instructions = document.getElementById('cWizTaskInstructions') ? document.getElementById('cWizTaskInstructions').value : '';
	} else if (step === 7) {
		data.scanned_serials = cWizSerials;
		data.counted_qty = parseFloat(document.getElementById('cWizCountInput').value) || 0;
	} else if (step === 9) {
		data.finding_state = document.getElementById('cWizFindingState') ? document.getElementById('cWizFindingState').value : '';
		data.finding_details = document.getElementById('cWizFindingDetails') ? document.getElementById('cWizFindingDetails').value : '';
	} else if (step === 10) {
		data.cctv_ref = document.getElementById('cWizCctvRef') ? document.getElementById('cWizCctvRef').value : '';
		data.doc_ref = document.getElementById('cWizDocRef') ? document.getElementById('cWizDocRef').value : '';
		data.evidence_notes = document.getElementById('cWizEvidenceNotes') ? document.getElementById('cWizEvidenceNotes').value : '';
	} else if (step === 11) {
		data.cause_status = document.getElementById('cWizCauseStatus') ? document.getElementById('cWizCauseStatus').value : '';
		data.cause_class = document.getElementById('cWizCauseClass') ? document.getElementById('cWizCauseClass').value : '';
		data.five_whys = {
			why1: document.getElementById('cWizWhy1') ? document.getElementById('cWizWhy1').value : '',
			why2: document.getElementById('cWizWhy2') ? document.getElementById('cWizWhy2').value : '',
			why3: document.getElementById('cWizWhy3') ? document.getElementById('cWizWhy3').value : '',
			why4: document.getElementById('cWizWhy4') ? document.getElementById('cWizWhy4').value : '',
			why5: document.getElementById('cWizWhy5') ? document.getElementById('cWizWhy5').value : ''
		};
		Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/root-cause', {
			status: data.cause_status,
			confirmed_cause: data.cause_class,
			five_whys: data.five_whys
		}, function() {});
	} else if (step === 12) {
		data.approval_proposal = document.getElementById('cWizApprovalProposal') ? document.getElementById('cWizApprovalProposal').value : '';
		data.approval_justification = document.getElementById('cWizApprovalJustification') ? document.getElementById('cWizApprovalJustification').value : '';
	} else if (step === 14) {
		data.final_outcome = document.getElementById('cWizFinalOutcomeSelect') ? document.getElementById('cWizFinalOutcomeSelect').value : '';
		data.closing_note = document.getElementById('cWizFinalClosingNote') ? document.getElementById('cWizFinalClosingNote').value : '';
	}
	return data;
}

function restoreCaseWizInputs() {
	if (!cWizState) return;
	for (var key in cWizState) {
		var s = cWizState[key];
		if (!s || !s.data) continue;
		if (s.step === 2 && s.data.location_notes && document.getElementById('cWizStep2LocationNotes')) {
			document.getElementById('cWizStep2LocationNotes').value = s.data.location_notes;
		}
		if (s.step === 9 && s.data.finding_details && document.getElementById('cWizFindingDetails')) {
			document.getElementById('cWizFindingDetails').value = s.data.finding_details;
		}
		if (s.step === 11 && s.data.five_whys) {
			if (document.getElementById('cWizWhy1')) document.getElementById('cWizWhy1').value = s.data.five_whys.why1 || '';
			if (document.getElementById('cWizWhy2')) document.getElementById('cWizWhy2').value = s.data.five_whys.why2 || '';
			if (document.getElementById('cWizWhy3')) document.getElementById('cWizWhy3').value = s.data.five_whys.why3 || '';
			if (document.getElementById('cWizWhy4')) document.getElementById('cWizWhy4').value = s.data.five_whys.why4 || '';
			if (document.getElementById('cWizWhy5')) document.getElementById('cWizWhy5').value = s.data.five_whys.why5 || '';
		}
	}
}

function recordInSituCheckinDirect() {
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/checkin', { zone_name: '{{ addslashes($c['zone'] ?: 'Piso de Venta') }}' }, function() {
		document.getElementById('cWizCheckinDone').hidden = false;
		toastr.success('Llegada in-situ confirmada y estampada con georreferencia');
	});
}

function execCaseScan() {
	var code = document.getElementById('cWizScanInput').value.trim();
	if (!code) return;
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/scan-verify', { query: code, scanned_serials: cWizSerials }, function(res) {
		if (res.is_matching_product) {
			toastr.success('Código validado: ' + (res.scanned_product ? res.scanned_product.name : 'Correcto'));
			if (!cWizSerials.includes(code)) {
				cWizSerials.push(code);
			}
			renderCaseWizChips();
			document.getElementById('cWizScanInput').value = '';
			document.getElementById('cWizCountInput').value = cWizSerials.length;
			updateCaseLiveDiff();
		} else {
			toastr.warning('Código escaneado no coincide con el producto');
		}
	});
}

function renderCaseWizChips() {
	var box = document.getElementById('cWizChips');
	if (!box) return;
	box.innerHTML = '';
	cWizSerials.forEach(function(s) {
		var sp = document.createElement('span');
		sp.className = 'badge badge-success mr-1 mb-1 p-1 px-2 font-weight-bold';
		sp.innerHTML = '<i class="fa-solid fa-mobile-screen-button mr-1"></i> ' + s;
		box.appendChild(sp);
	});
}

function updateCaseLiveDiff() {
	var exp = {{ floatval($c['expected_stock']) }};
	var cur = parseFloat(document.getElementById('cWizCountInput').value) || 0;
	var d = cur - exp;
	var disp = document.getElementById('cWizDiffDisplay');
	if (disp) {
		if (Math.abs(d) < 0.0001) {
			disp.className = 'lp-diff-live matched';
			disp.textContent = 'CONCILIADO (0 u.)';
		} else if (d < 0) {
			disp.className = 'lp-diff-live has-gap';
			disp.textContent = 'Faltan ' + Math.abs(d) + ' u.';
		} else {
			disp.className = 'lp-diff-live matched';
			disp.textContent = 'Sobrante +' + d + ' u.';
		}
	}
}

function executeFullCaseClose() {
	var outcome = document.getElementById('cWizFinalOutcomeSelect').value;
	var reason = document.getElementById('cWizFinalClosingNote').value || 'Inspección física y reconciliación completada en 15 pasos.';
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/close', { outcome: outcome, justification: reason }, function() {
		Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/financial-recovery', {
			pending_diff: 0,
			confirmed_loss: (outcome === 'resuelto' ? 0 : {{ floatval($c['financial_impact']) }}),
			recovered_merchandise: (outcome === 'resuelto' ? {{ floatval($c['financial_impact']) }} : 0),
			document_refs: ['EXP-{{ $c['case_code'] }}'],
			notes: 'Cierre formal ejecutado: ' + outcome
		}, function() {});

		toastr.success('Incidencia firmada y cerrada exitosamente');
		switchCaseWizStep(15);
	});
}

function executeCreatePreventivePlan() {
	var title = document.getElementById('cWizPlanTitle').value.trim();
	var owner = document.getElementById('cWizPlanOwner').value;
	var date = document.getElementById('cWizPlanTargetDate').value;
	var freq = document.getElementById('cWizPlanFrequency').value;

	if (!title) {
		toastr.warning('Indique el título del plan preventivo');
		return;
	}

	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/preventive-plan', {
		title: title,
		responsible_user_id: owner,
		target_date: date,
		frequency_days: freq,
		plan_type: 'custody_chain',
		notes: 'Generado desde el Asistente de Resolución de la incidencia {{ $c['case_code'] }}'
	}, function() {
		toastr.success('Plan de acción preventivo activado permanentemente');
		closeModal('caseModalResolverWizard');
		setTimeout(function() { location.reload(); }, 800);
	});
}

function openHandoverModal() {
	document.getElementById('modalHandoverDialog').hidden = false;
}

function submitHandoverNote(e) {
	e.preventDefault();
	var note = document.getElementById('handoverNoteText').value.trim();
	if (!note) return;
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/handover', { note: note }, function() {
		toastr.success('Relevo de turno registrado');
		closeModal('modalHandoverDialog');
		location.reload();
	});
}

function openCaseCallModal() {
	document.getElementById('caseModalCall').hidden = false;
}

function onCaseSelectCallContact(sel) {
	var opt = sel.options[sel.selectedIndex];
	var phone = opt.value;
	var btn = document.getElementById('caseBtnTriggerDialer');
	if (phone) {
		btn.href = 'tel:' + phone;
	} else {
		btn.href = '#';
	}
}

function submitCaseCall(e) {
	e.preventDefault();
	var sel = document.getElementById('caseCallContactSelect');
	var opt = sel.options[sel.selectedIndex];
	var res = document.getElementById('caseCallResultField').value;
	if (!res) {
		toastr.warning('Seleccione el resultado de la llamada');
		return;
	}
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/call', {
		contact_name: opt.dataset.name || 'Contacto',
		contact_role: opt.dataset.role || 'Operativo',
		contact_phone: opt.value,
		contact_employee_id: opt.dataset.empid || null,
		contact_user_id: opt.dataset.userid || null,
		call_result: res,
		notes: e.target.notes.value
	}, function() {
		toastr.success('Llamada registrada');
		closeModal('caseModalCall');
		location.reload();
	});
}

function logQuickCallDirect(name, phone, role) {
	bootbox.prompt({
		title: 'Registrar resultado de llamada a ' + name + ' (' + phone + '):',
		inputType: 'select',
		inputOptions: [
			{ text: 'Contestó y atendió incidencia', value: 'answered' },
			{ text: 'No contestó', value: 'no_answer' },
			{ text: 'Ocupado', value: 'busy' },
			{ text: 'Buzón de voz', value: 'voicemail' },
			{ text: 'Programar llamada', value: 'callback' }
		],
		callback: function(res) {
			if (res) {
				Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/call', {
					contact_name: name,
					contact_role: role,
					contact_phone: phone,
					call_result: res,
					notes: 'Llamada rápida desde el asistente de resolución'
				}, function() {
					toastr.success('Llamada registrada en la línea de tiempo');
				});
			}
		}
	});
}

function openCaseMsgModal() {
	document.getElementById('caseModalMsg').hidden = false;
}

function submitCaseDirectMsg(e) {
	e.preventDefault();
	var body = document.getElementById('caseDirectMsgBody').value.trim();
	var isPrio = document.getElementById('caseDirectMsgPrio').checked;
	if (!body) return;
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/message', { body: body, is_priority: isPrio ? 1 : 0 }, function() {
		toastr.success('Mensaje enviado');
		document.getElementById('caseDirectMsgBody').value = '';
		location.reload();
	});
}

function openCaseTaskModal() {
	document.getElementById('caseModalTask').hidden = false;
}

function submitCaseTask(e) {
	e.preventDefault();
	var sel = document.getElementById('caseTaskAssignee');
	var opt = sel.options[sel.selectedIndex];
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/tasks', {
		title: document.getElementById('caseTaskTitle').value,
		department: document.getElementById('caseTaskDept').value,
		priority: document.getElementById('caseTaskPriority').value,
		assigned_user_id: sel.value || null,
		assigned_employee_id: opt ? (opt.dataset.empid || null) : null,
		description: document.getElementById('caseTaskDesc').value
	}, function() {
		toastr.success('Tarea asignada');
		closeModal('caseModalTask');
		location.reload();
	});
}

function completeCaseTaskDirect(tId) {
	bootbox.prompt('Resultado u observaciones de la tarea completada:', function(res) {
		if (res !== null) {
			Grocy.Api.Post('loss-control/tasks/' + tId + '/status', { status: 'completed', result_notes: res }, function() {
				toastr.success('Tarea completada');
				location.reload();
			});
		}
	});
}

function openCaseEscalateModal() {
	bootbox.confirm({
		title: '<i class="fa-solid fa-arrow-trend-up text-danger mr-1"></i> Escalar Incidencia a Gerencia',
		message: '¿Desea escalar esta incidencia al siguiente nivel de gerencia por falta de respuesta o riesgo crítico?',
		buttons: {
			confirm: { label: 'Confirmar Escalamiento', className: 'btn-danger' },
			cancel: { label: 'Cancelar', className: 'btn-secondary' }
		},
		callback: function(confirmed) {
			if (confirmed) {
				Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/escalate', { reason: 'Escalamiento manual solicitado por el manager' }, function(res) {
					toastr.success(res.message || 'Incidencia escalada a Gerencia');
					location.reload();
				});
			}
		}
	});
}

function recordInSituCheckin() {
	bootbox.prompt('Registrar llegada in-situ al lugar de los hechos (Check-in):', function(notes) {
		if (notes !== null) {
			Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/checkin', { zone_name: '{{ addslashes($c['zone'] ?: 'Piso de venta') }}', notes: notes }, function() {
				toastr.success('Llegada al lugar registrada');
				location.reload();
			});
		}
	});
}

document.addEventListener('DOMContentLoaded', function() {
	var params = new URLSearchParams(window.location.search);
	if (params.get('resolver') === '1' || params.get('resolve') === '1') {
		launchCaseResolverFlow();
	} else if (params.get('action') === 'call') {
		openCaseCallModal();
	} else if (params.get('action') === 'msg') {
		openCaseMsgModal();
	} else if (params.get('action') === 'handover') {
		openHandoverModal();
	}
});
</script>
@stop
