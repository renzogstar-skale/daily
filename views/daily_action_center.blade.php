@extends('layout.default')

@section('title', 'DAILY Action Center · Centro de Acciones Inteligentes')

@section('content')
<div class="container-fluid px-4 py-3">

	<!-- Header Principal -->
	<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
		<div class="d-flex align-items-center gap-3">
			<div class="p-3 bg-warning text-dark rounded-circle shadow-sm" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
				<i class="fa-solid fa-bolt-lightning fa-lg"></i>
			</div>
			<div>
				<div class="d-flex align-items-center gap-2">
					<h1 class="h3 font-weight-bold text-dark mb-0">DAILY Action Center</h1>
					<span class="badge badge-warning font-weight-bold px-2 py-1">Supervisión & Control</span>
				</div>
				<p class="text-muted small mb-0">Centro unificado de excepciones operativas, aprobaciones multinivel e intervenciones inteligentes.</p>
			</div>
		</div>
		<div class="d-flex gap-2">
			<a href="{{ $U('/today') }}" class="btn btn-outline-secondary btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-list-check mr-1"></i> Operación del Día
			</a>
			<a href="{{ $U('/alerts') }}" class="btn btn-outline-danger btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-bell mr-1"></i> Centro de Alertas
			</a>
			<button class="btn btn-primary btn-sm font-weight-600 shadow-sm" onclick="window.location.reload()">
				<i class="fa-solid fa-rotate mr-1"></i> Actualizar Bandeja
			</button>
		</div>
	</div>

	<!-- KPIs de Acciones -->
	<div class="row mb-4">
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-warning h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Acciones Pendientes</div>
					<div class="h4 mb-0 font-weight-bold text-gray-800">{{ $kpiTotal }}</div>
					<small class="text-muted">Requieren intervención humana</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-danger h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Prioridad Crítica</div>
					<div class="h4 mb-0 font-weight-bold text-danger">{{ $kpiCritical }}</div>
					<small class="text-muted">Impacto financiero o sanitario</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-secondary h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Vencidas / Fuera de Plazo</div>
					<div class="h4 mb-0 font-weight-bold text-danger">{{ $kpiOverdue }}</div>
					<small class="text-muted">Superaron fecha de compromiso</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-success h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resueltas Hoy</div>
					<div class="h4 mb-0 font-weight-bold text-success">{{ $kpiResolvedToday }}</div>
					<small class="text-muted">Acciones cerradas con éxito</small>
				</div>
			</div>
		</div>
	</div>

	<!-- Pestañas Principales -->
	<ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded border" id="actionCenterTabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active font-weight-600" id="tab-inbox-link" data-toggle="pill" href="#tab-inbox" role="tab">
				<i class="fa-solid fa-inbox mr-1"></i> 1. Bandeja de Acciones ({{ count($items) }})
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-motor-link" data-toggle="pill" href="#tab-motor" role="tab">
				<i class="fa-solid fa-gears mr-1"></i> 2. Motor de Acciones Automáticas (FASE 4)
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-impacto-link" data-toggle="pill" href="#tab-impacto" role="tab">
				<i class="fa-solid fa-chart-line mr-1"></i> 3. Indicadores de Impacto (FASE 5)
			</a>
		</li>
	</ul>

	<div class="tab-content" id="actionCenterTabsContent">

		<!-- TAB 1: BANDEJA DE ACCIONES -->
		<div class="tab-pane fade show active" id="tab-inbox" role="tabpanel">
			
			<!-- Barra de Filtros -->
			<div class="card border-0 shadow-sm mb-4">
				<div class="card-body py-2">
					<form method="GET" action="{{ $U('/action-center') }}" class="form-row align-items-center">
						<div class="col-md-3 my-1">
							<label class="sr-only">Prioridad</label>
							<select name="priority" class="form-control form-control-sm">
								<option value="">-- Todas las Prioridades --</option>
								<option value="critical" {{ $filterPriority === 'critical' ? 'selected' : '' }}>Crítica (Inmediata)</option>
								<option value="high" {{ $filterPriority === 'high' ? 'selected' : '' }}>Alta</option>
								<option value="medium" {{ $filterPriority === 'medium' ? 'selected' : '' }}>Media</option>
								<option value="low" {{ $filterPriority === 'low' ? 'selected' : '' }}>Baja</option>
							</select>
						</div>

						<div class="col-md-3 my-1">
							<label class="sr-only">Estado</label>
							<select name="status" class="form-control form-control-sm">
								<option value="">-- Todos los Estados --</option>
								<option value="pending" {{ $filterStatus === 'pending' ? 'selected' : '' }}>Pendiente</option>
								<option value="in_progress" {{ $filterStatus === 'in_progress' ? 'selected' : '' }}>En Proceso</option>
								<option value="blocked" {{ $filterStatus === 'blocked' ? 'selected' : '' }}>Bloqueado</option>
								<option value="resolved" {{ $filterStatus === 'resolved' ? 'selected' : '' }}>Resuelto</option>
								<option value="closed" {{ $filterStatus === 'closed' ? 'selected' : '' }}>Cerrado</option>
							</select>
						</div>

						<div class="col-md-3 my-1">
							<label class="sr-only">Sucursal</label>
							<select name="branch_id" class="form-control form-control-sm">
								<option value="">-- Todas las Sucursales --</option>
								@foreach($branches as $b)
								<option value="{{ $b['id'] }}" {{ $filterBranch == $b['id'] ? 'selected' : '' }}>{{ $b['name'] }}</option>
								@endforeach
							</select>
						</div>

						<div class="col-md-3 my-1 d-flex gap-2">
							<button type="submit" class="btn btn-primary btn-sm px-3 font-weight-600">
								<i class="fa-solid fa-filter mr-1"></i> Filtrar
							</button>
							<a href="{{ $U('/action-center') }}" class="btn btn-outline-secondary btn-sm px-3">
								<i class="fa-solid fa-rotate-left mr-1"></i> Reestablecer
							</a>
						</div>
					</form>
				</div>
			</div>

			<!-- Tabla de Acciones -->
			<div class="card border-0 shadow-sm">
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th style="width: 130px;">Código</th>
								<th style="width: 90px;">Prioridad</th>
								<th>Título de Acción / Problema</th>
								<th style="width: 170px;">Módulo / Origen</th>
								<th style="width: 150px;">Sucursal / Área</th>
								<th style="width: 140px;">Responsable</th>
								<th style="width: 110px;">Límite</th>
								<th style="width: 100px;">Estado</th>
								<th style="width: 130px;" class="text-right">Intervención</th>
							</tr>
						</thead>
						<tbody>
							@forelse($items as $it)
							<tr>
								<td>
									<strong class="text-dark">{{ $it['code'] }}</strong>
									@if(!empty($it['evidence_url']))
									<i class="fa-solid fa-paperclip text-muted ml-1" title="Evidencia adjunta"></i>
									@endif
								</td>
								<td>
									@if($it['priority'] === 'critical')
										<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Crítica</span>
									@elseif($it['priority'] === 'high')
										<span class="badge badge-warning px-2 py-1"><i class="fa-solid fa-circle-exclamation mr-1"></i> Alta</span>
									@elseif($it['priority'] === 'medium')
										<span class="badge badge-info px-2 py-1">Media</span>
									@else
										<span class="badge badge-secondary px-2 py-1">Baja</span>
									@endif
								</td>
								<td>
									<div class="font-weight-600 text-dark">{{ $it['title'] }}</div>
									<div class="small text-muted text-truncate" style="max-width: 380px;">{{ $it['description'] }}</div>
								</td>
								<td>
									<span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
										<i class="fa-solid fa-folder-tree mr-1 text-primary"></i> {{ $it['source_module'] }}
									</span>
								</td>
								<td>
									<span class="small font-weight-600 text-dark">{{ $it['branch_name'] }}</span>
									<div class="small text-muted">{{ $it['department'] }}</div>
								</td>
								<td>
									<span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
										<i class="fa-solid fa-user mr-1 text-secondary"></i> {{ $it['assigned_user_name'] }}
									</span>
								</td>
								<td class="small {{ ($it['due_date'] < date('Y-m-d') && $it['status'] !== 'closed') ? 'text-danger font-weight-bold' : '' }}">
									{{ $it['due_date'] }}
								</td>
								<td>
									@if($it['status'] === 'pending')
										<span class="badge badge-warning px-2 py-1">Pendiente</span>
									@elseif($it['status'] === 'in_progress')
										<span class="badge badge-primary px-2 py-1">En Curso</span>
									@elseif($it['status'] === 'blocked')
										<span class="badge badge-danger px-2 py-1">Bloqueado</span>
									@elseif($it['status'] === 'resolved')
										<span class="badge badge-success px-2 py-1">Resuelto</span>
									@else
										<span class="badge badge-secondary px-2 py-1">Cerrado</span>
									@endif
								</td>
								<td class="text-right">
									<button class="btn btn-outline-primary btn-xs font-weight-600 py-1 px-2 btn-manage-action"
										data-id="{{ $it['id'] }}"
										data-code="{{ $it['code'] }}"
										data-title="{{ $it['title'] }}"
										data-desc="{{ $it['description'] }}"
										data-priority="{{ $it['priority'] }}"
										data-status="{{ $it['status'] }}"
										data-module="{{ $it['source_module'] }}"
										data-branch="{{ $it['branch_name'] }}"
										data-evidence="{{ $it['evidence_url'] }}">
										<i class="fa-solid fa-hand mr-1"></i> Gestionar
									</button>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center py-5 text-muted">
									<i class="fa-solid fa-circle-check fa-3x mb-3 text-success opacity-50"></i>
									<p class="mb-0 font-weight-600">No hay acciones pendientes con los filtros seleccionados.</p>
									<small>Todas las operaciones marchan dentro de los umbrales normales.</small>
								</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- TAB 2: MOTOR DE ACCIONES AUTOMÁTICAS (FASE 4) -->
		<div class="tab-pane fade" id="tab-motor" role="tabpanel">
			<div class="card border-0 shadow-sm mb-4">
				<div class="card-header bg-white py-3 border-bottom">
					<h5 class="h6 font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-gears mr-2 text-primary"></i> Reglas del Motor de Acciones Automáticas (FASE 4)
					</h5>
					<p class="text-muted small mb-0">Reglas configurables que supervisan el clúster transaccional y disparan acciones inmediatas con trazabilidad completa.</p>
				</div>
				<div class="table-responsive">
					<table class="table table-hover table-striped mb-0">
						<thead class="thead-light">
							<tr>
								<th style="width: 280px;">Disparador / Evento de Origen</th>
								<th>Cadena de Acción Automática Disparada</th>
								<th style="width: 110px;">Estado</th>
								<th style="width: 120px;">Ejecuciones</th>
								<th style="width: 140px;" class="text-right">Evaluación</th>
							</tr>
						</thead>
						<tbody>
							@foreach($autoRules as $rule)
							<tr>
								<td class="font-weight-600 text-dark">
									<i class="fa-solid fa-bolt text-warning mr-1"></i> {{ $rule['trigger'] }}
								</td>
								<td class="small text-secondary">
									{{ $rule['action'] }}
								</td>
								<td>
									<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i> {{ $rule['status'] }}</span>
								</td>
								<td class="font-weight-bold text-dark">
									{{ $rule['executions'] }} veces
								</td>
								<td class="text-right">
									<button class="btn btn-outline-dark btn-xs font-weight-600 py-1 px-2 btn-eval-rule" data-rule="{{ $rule['trigger'] }}">
										<i class="fa-solid fa-play mr-1"></i> Evaluar en Vivo
									</button>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- TAB 3: INDICADORES DE IMPACTO (FASE 5) -->
		<div class="tab-pane fade" id="tab-impacto" role="tabpanel">
			<div class="card border-0 shadow-sm mb-4">
				<div class="card-header bg-white py-3 border-bottom">
					<h5 class="h6 font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-chart-line mr-2 text-success"></i> Indicadores de Impacto y Retorno de Inversión (FASE 5)
					</h5>
					<p class="text-muted small mb-0">Métricas comparativas verificables que certifican la reducción de pérdidas, recuperaciones y eficiencia de DAILY.</p>
				</div>
				<div class="card-body">
					<div class="row">
						@foreach($impactMetrics as $m)
						<div class="col-md-4 mb-3">
							<div class="card border h-100 shadow-xs">
								<div class="card-body p-3">
									<div class="d-flex justify-content-between align-items-center mb-2">
										<span class="text-xs font-weight-bold text-muted text-uppercase">{{ $m['label'] }}</span>
										<span class="badge badge-{{ $m['badge'] }} px-2 py-1 font-weight-bold">{{ $m['value'] }}</span>
									</div>
									<div class="h3 font-weight-bold text-dark mb-1">{{ $m['value'] }}</div>
									<p class="small text-muted mb-0">{{ $m['desc'] }}</p>
								</div>
							</div>
						</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>

	</div>

</div>

<!-- Modal para Gestionar Acción -->
<div class="modal fade" id="modalManageAction" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold">
					<i class="fa-solid fa-bolt-lightning mr-2 text-warning"></i> Intervención en Acción: <span id="mActionCode"></span>
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<h5 class="h6 font-weight-bold text-dark mb-1" id="mActionTitle"></h5>
					<p class="text-muted small mb-0" id="mActionDesc"></p>
				</div>

				<div class="d-flex flex-wrap gap-2 mb-3">
					<span class="badge badge-light border text-dark font-weight-normal px-2 py-1" id="mActionModule"></span>
					<span class="badge badge-light border text-dark font-weight-normal px-2 py-1" id="mActionBranch"></span>
					<span class="badge badge-warning px-2 py-1" id="mActionPriority"></span>
				</div>

				<div id="mEvidenceContainer" class="mb-3 p-2 border rounded bg-light d-none">
					<label class="font-weight-600 small mb-1"><i class="fa-solid fa-paperclip mr-1"></i> Evidencia Asociada:</label>
					<div><img src="" id="mEvidenceImg" class="rounded border" style="max-height: 120px;" alt="Evidencia"></div>
				</div>

				<form id="formExecuteAction">
					<input type="hidden" id="mActionId" name="action_id">
					
					<div class="form-group mb-3">
						<label class="font-weight-600 small mb-1">Acción a Ejecutar:</label>
						<select class="form-control form-control-sm font-weight-600" id="mActionType" required>
							<option value="assign">Asignar Responsable</option>
							<option value="create_task">Crear Tarea de Piso de Venta</option>
							<option value="recount">Solicitar Reconteo Ciego de Inventario</option>
							<option value="approve">Aprobar Excepción / Descuadre</option>
							<option value="reject">Rechazar / Bloquear Salida</option>
							<option value="open_case">Abrir Expediente de Investigación Formativo</option>
							<option value="escalate">Escalar a Gerencia General</option>
							<option value="close">Cerrar y Archivar Caso Conforme</option>
						</select>
					</div>

					<div class="form-group mb-3" id="mUserAssignGroup">
						<label class="font-weight-600 small mb-1">Asignar a Colaborador:</label>
						<select class="form-control form-control-sm" id="mAssignedUserId">
							@foreach($users as $u)
							<option value="{{ $u['id'] }}">{{ $u['username'] }} ({{ $u['first_name'] }} {{ $u['last_name'] }})</option>
							@endforeach
						</select>
					</div>

					<div class="form-group mb-3">
						<label class="font-weight-600 small mb-1">Notas de Intervención / Justificación:</label>
						<textarea class="form-control form-control-sm" id="mActionNotes" rows="3" placeholder="Indique la justificación operativa, evidencia revisada y directrices..."></textarea>
					</div>
				</form>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary btn-sm font-weight-600" id="btnSubmitAction">
					<i class="fa-solid fa-check mr-1"></i> Ejecutar y Guardar
				</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	$('.btn-manage-action').on('click', function() {
		var btn = $(this);
		$('#mActionId').val(btn.data('id'));
		$('#mActionCode').text(btn.data('code'));
		$('#mActionTitle').text(btn.data('title'));
		$('#mActionDesc').text(btn.data('desc'));
		$('#mActionModule').html('<i class="fa-solid fa-folder mr-1"></i> ' + btn.data('module'));
		$('#mActionBranch').html('<i class="fa-solid fa-store mr-1"></i> ' + btn.data('branch'));
		$('#mActionPriority').text('Prioridad: ' + btn.data('priority'));

		var ev = btn.data('evidence');
		if (ev) {
			$('#mEvidenceImg').attr('src', '{{ $U("") }}' + ev.replace(/^\//, ''));
			$('#mEvidenceContainer').removeClass('d-none');
		} else {
			$('#mEvidenceContainer').addClass('d-none');
		}

		$('#modalManageAction').modal('show');
	});

	$('#mActionType').on('change', function() {
		if ($(this).val() === 'assign') {
			$('#mUserAssignGroup').show();
		} else {
			$('#mUserAssignGroup').hide();
		}
	});

	$('#btnSubmitAction').on('click', function() {
		var id = $('#mActionId').val();
		var action = $('#mActionType').val();
		var assignedUser = $('#mAssignedUserId').val();
		var notes = $('#mActionNotes').val();

		$.ajax({
			url: '{{ $U("/api/action-center/") }}' + id + '/action',
			method: 'POST',
			data: {
				action: action,
				assigned_user_id: assignedUser,
				notes: notes
			},
			success: function(resp) {
				toastr.success(resp.message || 'Acción ejecutada correctamente');
				$('#modalManageAction').modal('hide');
				setTimeout(function() { window.location.reload(); }, 800);
			},
			error: function(xhr) {
				toastr.error('Error al procesar la acción: ' + (xhr.responseJSON ? xhr.responseJSON.error_message : 'Error interno'));
			}
		});
	});

	$('.btn-eval-rule').on('click', function() {
		var rule = $(this).data('rule');
		$.ajax({
			url: '{{ $U("/api/action-center/rules/run") }}',
			method: 'POST',
			data: { rule: rule },
			success: function(resp) {
				toastr.info(resp.message);
			}
		});
	});
});
</script>
@endsection

