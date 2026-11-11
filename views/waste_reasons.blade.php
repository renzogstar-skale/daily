@php require_frontend_packages(['datatables']); @endphp

@extends('layout.default')

@section('title', 'Matriz Inteligente de Causales de Merma')

@section('content')
<!-- Encabezado y Navegación del Módulo -->
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-table-list"></i></div>
		<div>
			<h4 class="dairy-page-title">Matriz Inteligente de Causales de Merma</h4>
			<p class="dairy-page-subtitle">Estandarización empresarial de pérdidas: tipificación, prevención, riesgos, áreas responsables e impacto económico.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right mt-2 mt-md-0 dairy-page-actions">
		<button type="button" class="btn btn-primary shadow-sm mr-1" id="btn-create-reason">
			<i class="fa-solid fa-plus-circle mr-1"></i>Nueva Causal
		</button>
		<a href="{{ $U('/waste') }}" class="btn btn-outline-secondary shadow-sm">
			<i class="fa-solid fa-arrow-left mr-1"></i>Panel de Mermas
		</a>
	</div>
</div>

<!-- Barra de Sub-Navegación del Módulo Control de Mermas -->
<div class="card shadow-sm border-0 mb-3 bg-white">
	<div class="card-body py-2 px-3">
		<ul class="nav nav-pills nav-fill dairy-subnav-pills">
			<li class="nav-item">
				<a class="nav-link py-2" href="{{ $U('/waste') }}">
					<i class="fa-solid fa-chart-pie mr-1"></i>Panel & Casos
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link active py-2 font-weight-bold" href="{{ $U('/waste/reasons') }}">
					<i class="fa-solid fa-table-list mr-1"></i>Matriz de Causales
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link py-2" href="{{ $U('/waste/report') }}">
					<i class="fa-solid fa-file-circle-plus mr-1"></i>Registrar Merma
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link py-2" href="{{ $U('/waste/approvals') }}">
					<i class="fa-solid fa-user-check mr-1"></i>Autorizaciones
				</a>
			</li>
		</ul>
	</div>
</div>

<!-- Tarjetas Resumen de la Matriz -->
<div class="alert alert-light border d-flex align-items-center justify-content-between py-2 px-3 mb-3 small">
	<span><i class="fa-solid fa-tags text-primary mr-1"></i> Catálogo exclusivo de <strong>causales físicas de merma y errores operativos</strong>. Las incidencias de seguridad, hurto o custodia IMEI se gestionan en Prevención de Pérdidas.</span>
	<a href="{{ $U('/loss-prevention') }}" class="btn btn-xs btn-outline-primary font-weight-600 ml-2">Ir a Seguridad</a>
</div>
@php
	$totalReasons = count($reasons);
	$criticasCount = 0;
	$altasCount = 0;
	$aprobacionCount = 0;
	$totalHistoricoPerdida = 0;
	foreach($reasons as $r) {
		if ($r->risk_level === 'Crítico') $criticasCount++;
		if ($r->risk_level === 'Alto') $altasCount++;
		if ($r->requires_approval == 1) $aprobacionCount++;
		$totalHistoricoPerdida += floatval($r->total_loss_cost ?? 0);
	}
@endphp

@php
	$conocidaCount = count(array_filter(is_array($reasons) ? $reasons : iterator_to_array($reasons), function($r) { return ($r->shrink_type ?? 'conocida') === 'conocida'; }));
@endphp
@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Causales Activas', 'value' => $totalReasons, 'sub' => 'catálogo estandarizado', 'icon' => 'fa-list-ol', 'tone' => 'primary'],
	['label' => 'Merma Conocida', 'value' => $conocidaCount, 'sub' => 'causa identificada (Censo)', 'icon' => 'fa-magnifying-glass-chart', 'tone' => 'info'],
	['label' => 'Merma Desconocida', 'value' => $totalReasons - $conocidaCount, 'sub' => 'requieren investigación', 'icon' => 'fa-circle-question', 'tone' => 'warning'],
	['label' => 'Riesgo Crítico', 'value' => $criticasCount, 'sub' => 'cadena de frío y sanidad', 'icon' => 'fa-triangle-exclamation', 'tone' => 'danger', 'alert' => $criticasCount > 0],
	['label' => 'Riesgo Alto', 'value' => $altasCount, 'sub' => 'vencimiento y proveedores', 'icon' => 'fa-shield-halved', 'tone' => 'warning'],
	['label' => 'Con Aprobación', 'value' => $aprobacionCount, 'sub' => 'visto bueno de supervisor', 'icon' => 'fa-user-lock', 'tone' => 'purple'],
]])

<!-- Filtros de la Matriz -->
<div class="card shadow-sm border-0 mb-3 bg-light">
	<div class="card-body py-2 px-3">
		<form method="GET" action="{{ $U('/waste/reasons') }}" class="row align-items-center">
			<div class="col-md-3 mb-2 mb-md-0">
				<div class="input-group input-group-sm">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white"><i class="fa-solid fa-tags text-muted"></i>&nbsp;Categoría</span>
					</div>
					<select name="category" class="custom-select custom-select-sm">
						<option value="all">Todas las categorías</option>
						@foreach($categories as $cat)
						<option value="{{ $cat }}" @if($selectedCategory === $cat) selected @endif>{{ $cat }}</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="col-md-3 mb-2 mb-md-0">
				<div class="input-group input-group-sm">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white"><i class="fa-solid fa-shield text-muted"></i>&nbsp;Riesgo</span>
					</div>
					<select name="risk_level" class="custom-select custom-select-sm">
						<option value="all">Todos los niveles</option>
						<option value="Crítico" @if($selectedRiskLevel === 'Crítico') selected @endif>Crítico</option>
						<option value="Alto" @if($selectedRiskLevel === 'Alto') selected @endif>Alto</option>
						<option value="Medio" @if($selectedRiskLevel === 'Medio') selected @endif>Medio</option>
						<option value="Bajo" @if($selectedRiskLevel === 'Bajo') selected @endif>Bajo</option>
					</select>
				</div>
			</div>
			<div class="col-md-3 mb-2 mb-md-0">
				<div class="input-group input-group-sm">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white"><i class="fa-solid fa-building-user text-muted"></i>&nbsp;Área</span>
					</div>
					<select name="area" class="custom-select custom-select-sm">
						<option value="all">Todas las áreas</option>
						@foreach($areas as $ar)
						<option value="{{ $ar }}" @if($selectedArea === $ar) selected @endif>{{ $ar }}</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="col-md-3 text-md-right">
				<div class="custom-control custom-checkbox custom-control-inline mr-2">
					<input type="checkbox" class="custom-control-input" id="check-show-all" name="all" value="1" @if($showAll) checked @endif onchange="this.form.submit()">
					<label class="custom-control-label small" for="check-show-all">Ver inactivas</label>
				</div>
				<button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fa-solid fa-filter mr-1"></i>Filtrar</button>
				<a href="{{ $U('/waste/reasons') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-times"></i></a>
			</div>
		</form>
	</div>
</div>

<!-- Tabla Matriz Interactiva -->
<div class="card shadow-sm border-0 mb-4 bg-white">
	<div class="card-body p-0">
		<div class="table-responsive">
			<table id="reasons-matrix-table" class="table table-hover table-striped mb-0 text-sm align-middle">
				<thead class="thead-light">
					<tr>
						<th style="width: 75px;">Código</th>
						<th style="min-width: 130px;">Categoría</th>
						<th style="min-width: 180px;">Nombre Causal</th>
						<th style="min-width: 220px;">Descripción y Ejemplos</th>
						<th style="min-width: 140px;">Área Responsable</th>
						<th style="min-width: 220px;">Acciones Preventivas</th>
						<th style="min-width: 140px;">KPI Asociado</th>
						<th class="text-center" style="width: 90px;">Riesgo</th>
						<th class="text-center" style="width: 100px;">Frecuencia</th>
						<th class="text-center" style="width: 110px;">Impacto</th>
						<th class="text-center" style="width: 110px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
					@foreach($reasons as $r)
					<tr id="reason-row-{{ $r->id }}" class="@if($r->active == 0) table-secondary text-muted @endif">
						<!-- Código -->
						<td>
							<span class="badge @if($r->risk_level === 'Crítico') badge-danger @elseif($r->risk_level === 'Alto') badge-warning text-dark @elseif($r->risk_level === 'Medio') badge-info @else badge-secondary @endif font-weight-bold px-2 py-1">
								<i class="{{ $r->icon ?? 'fa-solid fa-tag' }} mr-1"></i>{{ $r->code }}
							</span>
						</td>

						<!-- Categoría -->
						<td class="font-weight-bold text-dark">
							{{ $r->category }}
							<div class="mt-1">
								@if(($r->shrink_type ?? 'conocida') === 'desconocida')
								<span class="dairy-pill-badge dairy-c-danger" style="background: var(--dairy-danger-bg);"><i class="fa-solid fa-circle-question mr-1"></i>Desconocida</span>
								@else
								<span class="dairy-pill-badge dairy-c-success" style="background: var(--dairy-success-bg);"><i class="fa-solid fa-circle-check mr-1"></i>Conocida</span>
								@endif
							</div>
						</td>

						<!-- Nombre Causal -->
						<td>
							<div class="font-weight-bold text-dark">{{ $r->name }}</div>
							<div class="small mt-1">
								@if($r->requires_evidence == 1)
								<span class="badge badge-light border text-primary mr-1" title="Requiere foto"><i class="fa-solid fa-camera mr-1"></i>Foto</span>
								@endif
								@if($r->requires_approval == 1)
								<span class="badge badge-light border text-danger mr-1" title="Requiere autorización previa"><i class="fa-solid fa-user-lock mr-1"></i>Aprobación</span>
								@endif
								@if(!empty($r->case_count) && $r->case_count > 0)
								<a href="{{ $U('/waste?reason_id=' . $r->id) }}" class="badge badge-light border text-muted" title="Ver casos registrados">
									<i class="fa-solid fa-folder-open mr-1"></i>{{ $r->case_count }} casos (${{ number_format($r->total_loss_cost, 2) }})
								</a>
								@endif
							</div>
						</td>

						<!-- Descripción y Ejemplos -->
						<td class="small text-muted dairy-lh-14">
							{{ $r->description }}
						</td>

						<!-- Área Responsable -->
						<td>
							<span class="badge badge-light border px-2 py-1 text-secondary">
								<i class="fa-solid fa-building-user mr-1 text-muted"></i>{{ $r->responsible_area }}
							</span>
						</td>

						<!-- Acciones Preventivas -->
						<td class="small dairy-lh-14 dairy-c-success">
							<i class="fa-solid fa-shield-check mr-1 text-success"></i>{{ $r->preventive_actions ?: 'Protocolo operativo general' }}
						</td>

						<!-- KPI Asociado -->
						<td class="small font-weight-bold text-muted">
							<i class="fa-solid fa-chart-line mr-1 text-primary"></i>{{ $r->associated_kpi ?: 'Control de Merma %' }}
						</td>

						<!-- Nivel de Riesgo -->
						<td class="text-center">
							@if($r->risk_level === 'Crítico')
							<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-fire mr-1"></i>Crítico</span>
							@elseif($r->risk_level === 'Alto')
							<span class="badge badge-warning text-dark px-2 py-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Alto</span>
							@elseif($r->risk_level === 'Medio')
							<span class="badge badge-info px-2 py-1">Medio</span>
							@else
							<span class="badge badge-secondary px-2 py-1">Bajo</span>
							@endif
						</td>

						<!-- Frecuencia -->
						<td class="text-center small">
							<span class="badge badge-light border">{{ $r->frequency }}</span>
						</td>

						<!-- Impacto Económico -->
						<td class="text-center small">
							<span class="badge @if($r->economic_impact === 'Severo' || $r->economic_impact === 'Alto') badge-light border-danger text-danger @else badge-light border @endif font-weight-bold">
								{{ $r->economic_impact }}
							</span>
						</td>

						<!-- Acciones Disponibles -->
						<td class="text-center text-nowrap">
							<button type="button" class="btn btn-sm btn-outline-primary btn-edit-reason mr-1" 
									data-reason="{{ json_encode($r) }}" title="Editar causal">
								<i class="fa-solid fa-pen-to-square"></i>
							</button>
							<button type="button" class="btn btn-sm @if($r->active == 1) btn-outline-danger @else btn-outline-success @endif btn-toggle-reason" 
									data-id="{{ $r->id }}" data-active="{{ $r->active }}"
									title="@if($r->active == 1) Desactivar (conservando historial) @else Reactivar causal @endif">
								<i class="fa-solid @if($r->active == 1) fa-ban @else fa-check @endif"></i>
							</button>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Modal para Crear / Editar Causal de Merma -->
<div class="modal fade" id="reasonModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white py-3">
				<h5 class="modal-title font-weight-bold" id="reason-modal-title">
					<i class="fa-solid fa-table-list mr-2"></i>Nueva Causal de Merma
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
			</div>
			<form id="reason-form">
				<input type="hidden" id="form-reason-id" name="id" value="">
				<div class="modal-body p-4">
					<div class="form-row">
						<!-- Código -->
						<div class="col-md-3 mb-3">
							<label class="font-weight-bold" for="form-code">Código Único <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="form-code" name="code" placeholder="Ej. D11 / M15" maxlength="10" required>
							<small class="form-text text-muted">Identificador corto único</small>
						</div>
						<!-- Nombre -->
						<div class="col-md-6 mb-3">
							<label class="font-weight-bold" for="form-name">Nombre de la Causal <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="form-name" name="name" placeholder="Ej. Fisura por impacto en tarima" required>
						</div>
						<!-- Categoría -->
						<div class="col-md-3 mb-3">
							<label class="font-weight-bold" for="form-category">Categoría <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="form-category" name="category" placeholder="Ej. Averías" list="category-suggestions" required>
							<datalist id="category-suggestions">
								@foreach($categories as $cat)
								<option value="{{ $cat }}">
								@endforeach
							</datalist>
						</div>
					</div>

					<!-- Descripción -->
					<div class="form-group mb-3">
						<label class="font-weight-bold" for="form-description">Descripción y Ejemplos de Ocurrencia <span class="text-danger">*</span></label>
						<textarea class="form-control" id="form-description" name="description" rows="2" placeholder="Detalle qué situación describe esta pérdida y en qué casos aplica..." required></textarea>
					</div>

					<div class="form-row">
						<!-- Área Responsable -->
						<div class="col-md-6 mb-3">
							<label class="font-weight-bold" for="form-area">Área Responsable <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="form-area" name="responsible_area" placeholder="Ej. Piso de Venta / Bodega" list="area-suggestions" required>
							<datalist id="area-suggestions">
								@foreach($areas as $ar)
								<option value="{{ $ar }}">
								@endforeach
							</datalist>
						</div>
						<!-- KPI Asociado -->
						<div class="col-md-6 mb-3">
							<label class="font-weight-bold" for="form-kpi">Indicador KPI Asociado</label>
							<input type="text" class="form-control" id="form-kpi" name="associated_kpi" placeholder="Ej. % Merma Manipulación Tienda">
						</div>
					</div>

					<!-- Acciones Preventivas -->
					<div class="form-group mb-3">
						<label class="font-weight-bold" for="form-preventive">Acciones Preventivas Protocolizadas <span class="text-danger">*</span></label>
						<textarea class="form-control" id="form-preventive" name="preventive_actions" rows="2" placeholder="Medidas y protocolos de mitigación obligatorios que el personal debe seguir..." required></textarea>
					</div>

					<div class="form-row">
						<!-- Nivel de Riesgo -->
						<div class="col-md-4 mb-3">
							<label class="font-weight-bold" for="form-risk">Nivel de Riesgo</label>
							<select class="custom-select" id="form-risk" name="risk_level">
								<option value="Crítico">Crítico</option>
								<option value="Alto">Alto</option>
								<option value="Medio" selected>Medio</option>
								<option value="Bajo">Bajo</option>
							</select>
						</div>
						<!-- Frecuencia -->
						<div class="col-md-4 mb-3">
							<label class="font-weight-bold" for="form-frequency">Frecuencia</label>
							<select class="custom-select" id="form-frequency" name="frequency">
								<option value="Muy Frecuente">Muy Frecuente</option>
								<option value="Frecuente">Frecuente</option>
								<option value="Ocasional" selected>Ocasional</option>
								<option value="Rara">Rara</option>
							</select>
						</div>
						<!-- Impacto Económico -->
						<div class="col-md-4 mb-3">
							<label class="font-weight-bold" for="form-impact">Impacto Económico</label>
							<select class="custom-select" id="form-impact" name="economic_impact">
								<option value="Severo">Severo</option>
								<option value="Alto">Alto</option>
								<option value="Medio" selected>Medio</option>
								<option value="Bajo">Bajo</option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<!-- Clasificación Censo Nacional de Mermas -->
						<div class="col-md-6 mb-3">
							<label class="font-weight-bold" for="form-shrink-type">Tipo de Merma (Censo)</label>
							<select class="custom-select" id="form-shrink-type" name="shrink_type">
								<option value="conocida" selected>Conocida — causa identificada</option>
								<option value="desconocida">Desconocida — origen no identificado</option>
							</select>
						</div>
						<div class="col-md-6 mb-3">
							<label class="font-weight-bold" for="form-censo-category">Categoría Censo</label>
							<select class="custom-select" id="form-censo-category" name="censo_category">
								<option value="vencimientos">Vencimientos</option>
								<option value="desperdicios" selected>Desperdicios / Averías</option>
								<option value="robo_externo">Robo externo</option>
								<option value="robo_interno">Robo interno</option>
								<option value="error_administrativo">Error administrativo</option>
								<option value="error_proveedor">Error de proveedor</option>
								<option value="otros">Otros</option>
							</select>
						</div>
					</div>

					<div class="form-row align-items-center">
						<div class="col-md-6 mb-2">
							<div class="custom-control custom-switch">
								<input type="checkbox" class="custom-control-input" id="form-evidence" name="requires_evidence" value="1">
								<label class="custom-control-label font-weight-bold" for="form-evidence">
									<i class="fa-solid fa-camera mr-1 text-primary"></i>Requiere Evidencia Fotográfica Obligatoria
								</label>
							</div>
						</div>
						<div class="col-md-6 mb-2">
							<div class="custom-control custom-switch">
								<input type="checkbox" class="custom-control-input" id="form-approval" name="requires_approval" value="1">
								<label class="custom-control-label font-weight-bold" for="form-approval">
									<i class="fa-solid fa-user-lock mr-1 text-danger"></i>Requiere Aprobación de Supervisor Antes de Ajuste
								</label>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer bg-light py-2 px-4">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold" id="btn-save-reason">
						<i class="fa-solid fa-save mr-1"></i>Guardar Causal
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
	var modal = $('#reasonModal');
	var form = document.getElementById('reason-form');

	// Open create modal
	document.getElementById('btn-create-reason').addEventListener('click', function() {
		form.reset();
		document.getElementById('form-reason-id').value = '';
		document.getElementById('reason-modal-title').innerHTML = '<i class="fa-solid fa-plus-circle mr-2"></i>Nueva Causal de Merma';
		modal.modal('show');
	});

	// Open edit modal
	document.querySelectorAll('.btn-edit-reason').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var r = JSON.parse(this.getAttribute('data-reason'));
			document.getElementById('form-reason-id').value = r.id;
			document.getElementById('form-code').value = r.code;
			document.getElementById('form-name').value = r.name;
			document.getElementById('form-category').value = r.category;
			document.getElementById('form-description').value = r.description;
			document.getElementById('form-area').value = r.responsible_area;
			document.getElementById('form-kpi').value = r.associated_kpi;
			document.getElementById('form-preventive').value = r.preventive_actions;
			document.getElementById('form-risk').value = r.risk_level;
			document.getElementById('form-frequency').value = r.frequency;
			document.getElementById('form-impact').value = r.economic_impact;
			document.getElementById('form-shrink-type').value = r.shrink_type || 'conocida';
			document.getElementById('form-censo-category').value = r.censo_category || 'desperdicios';
			document.getElementById('form-evidence').checked = (r.requires_evidence == 1);
			document.getElementById('form-approval').checked = (r.requires_approval == 1);

			document.getElementById('reason-modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square mr-2"></i>Editar Causal [' + r.code + ']';
			modal.modal('show');
		});
	});

	// Save causal
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		var btnSave = document.getElementById('btn-save-reason');
		btnSave.disabled = true;
		btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Guardando...';

		var data = {
			id: document.getElementById('form-reason-id').value,
			code: document.getElementById('form-code').value,
			name: document.getElementById('form-name').value,
			category: document.getElementById('form-category').value,
			description: document.getElementById('form-description').value,
			responsible_area: document.getElementById('form-area').value,
			associated_kpi: document.getElementById('form-kpi').value,
			preventive_actions: document.getElementById('form-preventive').value,
			risk_level: document.getElementById('form-risk').value,
			frequency: document.getElementById('form-frequency').value,
			economic_impact: document.getElementById('form-impact').value,
			shrink_type: document.getElementById('form-shrink-type').value,
			censo_category: document.getElementById('form-censo-category').value,
			requires_evidence: document.getElementById('form-evidence').checked ? 1 : 0,
			requires_approval: document.getElementById('form-approval').checked ? 1 : 0
		};

		fetch(Grocy.BaseUrl + '/api/waste/reasons', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(data)
		})
		.then(function(res) { return res.json(); })
		.then(function(resp) {
			btnSave.disabled = false;
			btnSave.innerHTML = '<i class="fa-solid fa-save mr-1"></i>Guardar Causal';
			if (resp.success) {
				toastr.success(resp.message || 'Causal guardada correctamente');
				modal.modal('hide');
				setTimeout(function() { window.location.reload(); }, 600);
			} else {
				toastr.error(resp.error_message || 'Error al guardar causal');
			}
		})
		.catch(function(err) {
			btnSave.disabled = false;
			btnSave.innerHTML = '<i class="fa-solid fa-save mr-1"></i>Guardar Causal';
			toastr.error('Error de comunicación con el servidor');
		});
	});

	// Toggle active
	document.querySelectorAll('.btn-toggle-reason').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = this.getAttribute('data-id');
			var active = this.getAttribute('data-active');
			var actionText = (active == '1') ? 'desactivar' : 'reactivar';

			bootbox.confirm('¿Está seguro de ' + actionText + ' esta causal? El historial registrado no se perderá.', function(ok) {
				if (!ok) return;
				fetch(Grocy.BaseUrl + '/api/waste/reasons/' + id + '/toggle-active', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' }
				})
				.then(function(res) { return res.json(); })
				.then(function(resp) {
					if (resp.success) {
						toastr.success(resp.message);
						setTimeout(function() { window.location.reload(); }, 500);
					} else {
						toastr.error(resp.error_message || 'Error al cambiar estado');
					}
				});
			});
		});
	});
});
</script>
@stop
