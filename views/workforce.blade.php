@extends('layout.default')

@section('title', 'Piso de Tienda')

@section('content')
@php $roleLabels = ['clerk' => 'Auxiliar de piso', 'supervisor' => 'Supervisor', 'manager' => 'Gerente', 'warehouse' => 'Bodeguero', 'stocker' => 'Reponedor']; @endphp
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-success"><i class="fa-solid fa-mobile-screen-button"></i></div>
		<div>
			<h4 class="dairy-page-title">Piso de Tienda</h4>
			<p class="dairy-page-subtitle">Gestión operativa móvil: checklists de apertura, barrido preventivo FEFO y captura express de averías con evidencia.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right mt-2 mt-md-0 dairy-page-actions">
		<a href="{{ $U('/equipment') }}" class="btn btn-outline-secondary btn-sm shadow-sm mr-1">
			<i class="fa-solid fa-temperature-low mr-1"></i>Equipos de Frío
		</a>
		<a href="{{ $U('/waste') }}" class="btn btn-outline-danger btn-sm shadow-sm mr-1">
			<i class="fa-solid fa-trash-can mr-1"></i>Control de Mermas
		</a>
		<a href="{{ $U('/pos') }}" class="btn btn-success btn-sm shadow-sm">
			<i class="fa-solid fa-cash-register mr-1"></i>Punto de Venta
		</a>
	</div>
</div>

<!-- Selector de Sucursal y Modo Móvil -->
<div class="card shadow-sm border-0 mb-3 bg-light">
	<div class="card-body py-2 d-flex justify-content-between align-items-center">
		<div class="d-flex align-items-center">
			<i class="fa-solid fa-store text-primary mr-2 fa-lg"></i>
			<span class="font-weight-bold text-dark mr-2">Sucursal Activa:</span>
			<select id="wf-branch-select" class="custom-select custom-select-sm" style="width: 220px;" onchange="window.location.href='{{ $U('/workforce') }}?branch_id=' + this.value">
				@foreach($branches as $b)
				<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}</option>
				@endforeach
			</select>
		</div>
		<div>
			<span class="badge badge-success px-2 py-1" id="wf-net-status" aria-live="polite"><i class="fa-solid fa-wifi mr-1"></i>En línea</span>
		</div>
	</div>
</div>

<!-- Indicadores Métricos de Tareas Operativas -->
@if(isset($taskMetrics))
@include('components.dairy_kpi_row', ['kpis' => [
	['label' => 'Tareas Vencidas', 'value' => $taskMetrics['overdue'], 'sub' => 'requieren atención urgente', 'icon' => 'fa-clock-rotate-left', 'tone' => $taskMetrics['overdue'] > 0 ? 'danger' : 'success', 'alert' => $taskMetrics['overdue'] > 0],
	['label' => 'Tareas Bloqueadas', 'value' => $taskMetrics['blocked'], 'sub' => 'en espera de insumo / técnico', 'icon' => 'fa-lock', 'tone' => $taskMetrics['blocked'] > 0 ? 'warning' : 'secondary', 'alert' => $taskMetrics['blocked'] > 0],
	['label' => 'En Progreso', 'value' => $taskMetrics['in_progress'], 'sub' => 'en ejecución en piso', 'icon' => 'fa-spinner', 'tone' => 'primary'],
	['label' => 'Pendientes', 'value' => $taskMetrics['pending'], 'sub' => 'por iniciar en el turno', 'icon' => 'fa-list-check', 'tone' => 'secondary'],
	['label' => 'Completadas', 'value' => $taskMetrics['completed'], 'sub' => 'de ' . $taskMetrics['total'] . ' tareas totales', 'icon' => 'fa-circle-check', 'tone' => 'success']
]])
@endif

<div class="row">
	<!-- Columna Izquierda: Reporte Express de Avería / Merma (Mobile First) -->
	<div class="col-lg-5 mb-4">
		<div class="card shadow-sm border-0 h-100">
			<div class="card-header bg-danger text-white py-3">
				<h5 class="mb-0 font-weight-bold">
					<i class="fa-solid fa-camera mr-2"></i>Reporte Express de Avería en Piso
				</h5>
				<small class="text-white-50">Capture de inmediato productos rotos, golpeados o vencidos</small>
			</div>
			<div class="card-body p-3">
				<form id="quick-waste-form">
					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Producto</label>
						<select id="quick-waste-product" class="custom-select custom-select-sm" required>
							<option value="">Seleccione el producto...</option>
							@foreach($products as $p)
							<option value="{{ $p->id }}">{{ $p->name }} (${{ number_format($p->default_sale_price, 2) }})</option>
							@endforeach
						</select>
					</div>

					<div class="form-row mb-2">
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Cantidad Afectada</label>
							<div class="input-group input-group-sm">
								<input type="number" id="quick-waste-amount" class="form-control" value="1" min="0.1" step="any" required>
								<div class="input-group-append">
									<span class="input-group-text">u.</span>
								</div>
							</div>
						</div>
						<div class="col-6">
							<label class="small font-weight-bold text-muted mb-1">Causal Auditada</label>
							<select id="quick-waste-reason" class="custom-select custom-select-sm">
								@foreach($reasons as $r)
								<option value="{{ $r->code }}">{{ $r->code }} - {{ $r->name }}</option>
								@endforeach
							</select>
						</div>
					</div>

					<div class="form-group mb-2">
						<label class="small font-weight-bold text-muted mb-1">Evidencia / Foto (obligatoria según la causal)</label>
						<div class="custom-file custom-file-sm">
							<input type="file" class="custom-file-input" id="quick-waste-photo" accept="image/*">
							<label class="custom-file-label" for="quick-waste-photo" id="quick-waste-photo-label">Tomar o elegir foto...</label>
						</div>
						<div id="quick-waste-photo-preview" class="mt-2 d-none align-items-center">
							<img class="dairy-radius-sm" src="" id="quick-waste-photo-img" style="max-height: 60px; border: 1px solid var(--dairy-border-strong);" alt="Evidencia">
							<span class="small text-muted ml-2" id="quick-waste-photo-name"></span>
						</div>
					</div>

					<div class="form-group mb-3">
						<label class="small font-weight-bold text-muted mb-1">Observaciones de Piso</label>
						<input type="text" id="quick-waste-notes" class="form-control form-control-sm" placeholder="Ej.: Empaque roto durante descarga en vitrina">
					</div>

					<button type="submit" class="btn btn-danger btn-block font-weight-bold btn-lg shadow-sm" id="btn-submit-waste">
						<i class="fa-solid fa-triangle-exclamation mr-1"></i>Registrar merma
					</button>
				</form>
			</div>
		</div>
	</div>

	<!-- Columna Derecha: Checklists y Tareas Operativas del Turno -->
	<div class="col-lg-7 mb-4">
		<div class="card shadow-sm border-0 h-100">
			<div class="card-header bg-primary text-white py-3 d-flex flex-wrap justify-content-between align-items-center">
				<div>
					<h5 class="mb-0 font-weight-bold">
						<i class="fa-solid fa-list-check mr-2"></i>Checklists y Tareas Operativas
					</h5>
					<small class="text-white-50">Rutinas preventivas, mermas, frío y acuerdos de reunión</small>
				</div>
				<div class="d-flex align-items-center mt-2 mt-sm-0">
					<button type="button" class="btn btn-sm btn-light font-weight-bold mr-2 text-primary shadow-sm" data-toggle="modal" data-target="#modal-agreements" title="Convertir acuerdos de comités operativos en tareas asignadas">
						<i class="fa-solid fa-handshake mr-1"></i>Acuerdos
						@if(isset($meetingAgreements))
						<span class="badge badge-primary ml-1">{{ count($meetingAgreements) }}</span>
						@endif
					</button>
					<span class="badge badge-light text-primary" id="tasks-pending-badge">{{ count($tasks) }} tareas</span>
				</div>
			</div>
			<div class="card-body p-3">
				<!-- Filtro por Estado (Pills) -->
				<div class="d-flex flex-wrap align-items-center mb-2 pb-2 border-bottom" style="gap: 6px;">
					<span class="small font-weight-bold text-muted mr-1">Estado:</span>
					<button type="button" class="btn btn-sm btn-primary wf-status-pill active" data-status="all" style="padding: 2px 10px;">Todas ({{ count($tasks) }})</button>
					<button type="button" class="btn btn-sm btn-light border wf-status-pill text-danger font-weight-bold" data-status="overdue" style="padding: 2px 10px;">
						<i class="fa-solid fa-triangle-exclamation mr-1"></i>Vencidas ({{ $taskMetrics['overdue'] ?? 0 }})
					</button>
					<button type="button" class="btn btn-sm btn-light border wf-status-pill" data-status="pending" style="padding: 2px 10px;">Pendientes ({{ $taskMetrics['pending'] ?? 0 }})</button>
					<button type="button" class="btn btn-sm btn-light border wf-status-pill text-info font-weight-bold" data-status="in_progress" style="padding: 2px 10px;">En progreso ({{ $taskMetrics['in_progress'] ?? 0 }})</button>
					<button type="button" class="btn btn-sm btn-light border wf-status-pill text-warning font-weight-bold" data-status="blocked" style="padding: 2px 10px;">
						<i class="fa-solid fa-lock mr-1"></i>Bloqueadas ({{ $taskMetrics['blocked'] ?? 0 }})
					</button>
					<button type="button" class="btn btn-sm btn-light border wf-status-pill text-success" data-status="completed" style="padding: 2px 10px;">Completadas ({{ $taskMetrics['completed'] ?? 0 }})</button>
				</div>

				<!-- Filtro por Rol Operativo -->
				<div class="d-flex flex-wrap align-items-center mb-3" style="gap: 6px;">
					<span class="small font-weight-bold text-muted mr-1">Filtrar Rol:</span>
					<button type="button" class="btn btn-sm btn-primary wf-role-chip active" data-role="all" style="padding: 2px 10px;">Todos</button>
					@foreach(collect($tasks)->pluck('assigned_role')->map(fn($r) => strtolower($r))->unique()->sort() as $role)
					<button type="button" class="btn btn-sm btn-light border wf-role-chip" data-role="{{ $role }}" style="padding: 2px 10px;">{{ $roleLabels[$role] ?? ucfirst($role) }}</button>
					@endforeach
				</div>

				<div class="list-group list-group-flush" id="tasks-list-container">
					@forelse($tasks as $task)
					@php
						$st = strtolower($task->status ?? 'pending');
						$isOverdue = ($st !== 'completed' && !empty($task->due_time) && strtotime($task->due_time) < time());
						$priority = in_array($task->task_type, ['corrective_action', 'agreement']) ? 'Alta' : ($task->task_type === 'fefo_sweep' ? 'Media' : 'Normal');
						$prioBadge = $priority === 'Alta' ? 'badge-danger' : ($priority === 'Media' ? 'badge-warning text-dark' : 'badge-light border text-muted');
						$processName = 'Operaciones de Piso';
						$processLink = null;
						if (($task->source_type ?? null) === 'waste') {
							$processName = 'Mermas';
							$processLink = $U('/document/waste/' . $task->source_id);
						} elseif ($task->task_type === 'cold_chain_check' || ($task->source_type ?? null) === 'equipment') {
							$processName = 'Equipos de Frío';
							$processLink = $U('/equipment');
						} elseif ($task->task_type === 'fefo_sweep') {
							$processName = 'Rotación FEFO';
							$processLink = $U('/quality');
						} elseif (($task->source_type ?? null) === 'agreement') {
							$processName = 'Acuerdo de Reunión #' . $task->source_id;
						} elseif ($task->task_type === 'stock_count') {
							$processName = 'Inventario & Conteos';
							$processLink = $U('/reconciliation');
						}
					@endphp
					<div class="list-group-item p-3 border rounded mb-2 task-card {{ $st === 'completed' ? 'bg-light' : '' }}" 
					     id="task-card-{{ $task->id }}" 
					     data-role="{{ strtolower($task->assigned_role) }}"
					     data-status="{{ $st }}"
					     data-overdue="{{ $isOverdue ? '1' : '0' }}">
						<div class="d-flex justify-content-between align-items-start mb-1">
							<h6 class="font-weight-bold text-dark mb-0">
								@if($task->task_type === 'cold_chain_check')
								<i class="fa-solid fa-temperature-arrow-down text-info mr-2"></i>
								@elseif($task->task_type === 'fefo_sweep')
								<i class="fa-solid fa-tag text-warning mr-2"></i>
								@elseif($task->task_type === 'corrective_action')
								<i class="fa-solid fa-screwdriver-wrench text-danger mr-2"></i>
								@elseif($task->task_type === 'agreement')
								<i class="fa-solid fa-handshake text-purple mr-2"></i>
								@else
								<i class="fa-solid fa-clipboard-check text-primary mr-2"></i>
								@endif
								{{ $task->title }}
							</h6>
							<div class="d-flex align-items-center" style="gap: 5px;">
								<span class="badge {{ $prioBadge }}" title="Prioridad operativa">{{ $priority }}</span>
								@if($st === 'completed')
									<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Completada</span>
								@elseif($st === 'blocked')
									<span class="badge badge-warning text-dark"><i class="fa-solid fa-lock mr-1"></i>Bloqueada</span>
								@elseif($st === 'in_progress')
									<span class="badge badge-info"><i class="fa-solid fa-spinner fa-spin mr-1"></i>En progreso</span>
								@else
									<span class="badge badge-secondary">Pendiente</span>
								@endif
								@if($task->due_time)
									<span class="badge {{ $isOverdue ? 'badge-danger' : 'badge-light border text-muted' }}" 
									      @if($isOverdue) title="Vencida: escalada al gerente en Hoy después de {{ GROCY_STORE_TASK_ESCALATION_MINUTES }} min" @endif>
										@if($isOverdue)<i class="fa-solid fa-triangle-exclamation mr-1"></i>Vencida · @endif{{ date(date('Y-m-d', strtotime($task->due_time)) === date('Y-m-d') ? 'H:i' : 'd/m H:i', strtotime($task->due_time)) }}
									</span>
								@endif
							</div>
						</div>
						<p class="text-muted small mb-2">
							{{ $task->description }}
						</p>
						<div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top" style="font-size: 12px;">
							<div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
								<span class="text-muted">
									<i class="fa-solid fa-user-tag mr-1 text-primary"></i>Rol: <strong>{{ $roleLabels[strtolower($task->assigned_role)] ?? ucfirst($task->assigned_role) }}</strong>
								</span>
								<span class="text-muted">
									<i class="fa-solid fa-diagram-project mr-1 text-secondary"></i>Proceso:
									@if($processLink)
										<a href="{{ $processLink }}" class="font-weight-bold text-primary ml-1" title="Ver registro origen del proceso">{{ $processName }} <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size: 10px;"></i></a>
									@else
										<strong class="text-dark ml-1">{{ $processName }}</strong>
									@endif
								</span>
							</div>
							<div class="d-flex align-items-center mt-2 mt-sm-0" style="gap: 5px;">
								@if($st === 'pending')
									<button type="button" class="btn btn-xs btn-outline-info btn-task-status" data-id="{{ $task->id }}" data-status="in_progress" title="Iniciar tarea">
										<i class="fa-solid fa-play mr-1"></i>Iniciar
									</button>
									<button type="button" class="btn btn-xs btn-outline-warning btn-task-status" data-id="{{ $task->id }}" data-status="blocked" title="Marcar como bloqueada">
										<i class="fa-solid fa-lock mr-1"></i>Bloquear
									</button>
								@elseif($st === 'in_progress')
									<button type="button" class="btn btn-xs btn-outline-warning btn-task-status" data-id="{{ $task->id }}" data-status="blocked" title="Bloquear tarea">
										<i class="fa-solid fa-lock mr-1"></i>Bloquear
									</button>
								@elseif($st === 'blocked')
									<button type="button" class="btn btn-xs btn-outline-success btn-task-status" data-id="{{ $task->id }}" data-status="in_progress" title="Desbloquear e iniciar">
										<i class="fa-solid fa-lock-open mr-1"></i>Desbloquear
									</button>
								@endif

								@if($st !== 'completed')
								<button class="btn btn-sm btn-success px-3 btn-complete-task" data-id="{{ $task->id }}">
									<i class="fa-solid fa-check mr-1"></i>Completar
								</button>
								@endif
							</div>
						</div>
					</div>
					@empty
					<div class="text-center py-5 text-muted">
						<i class="fa-solid fa-circle-check fa-3x text-success mb-2"></i>
						<p class="mb-0">Todas las tareas del turno han sido completadas con éxito.</p>
					</div>
					@endforelse
				</div>
			</div>
		</div>
	</div>
</div>

<!-- MODAL: Acuerdos de Reunión para Convertir en Tareas -->
<div class="modal fade" id="modal-agreements" tabindex="-1" role="dialog" aria-labelledby="modal-agreements-title" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title font-weight-bold" id="modal-agreements-title">
					<i class="fa-solid fa-handshake mr-2"></i>Acuerdos de Comités Operativos y Reuniones
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-3">
				<p class="text-muted small mb-3">
					Los acuerdos surgidos en reuniones de comités de merma, alineación diaria y operaciones pueden convertirse en tareas asignadas directamente al personal de piso con fecha límite y prioridad.
				</p>
				<div class="list-group" id="agreements-list">
					@if(isset($meetingAgreements))
					@foreach($meetingAgreements as $agr)
					<div class="list-group-item p-3 border rounded mb-2 agreement-item" id="agreement-item-{{ $agr['id'] }}">
						<div class="d-flex justify-content-between align-items-start mb-1">
							<div>
								<span class="badge badge-purple text-purple font-mono mr-1" style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.3);">
									<i class="fa-solid fa-handshake mr-1"></i>Acuerdo #{{ $agr['id'] }}
								</span>
								<strong class="text-dark">{{ $agr['title'] }}</strong>
							</div>
							<span class="badge {{ $agr['priority'] === 'Alta' ? 'badge-danger' : 'badge-warning text-dark' }}">Prioridad {{ $agr['priority'] }}</span>
						</div>
						<div class="d-flex flex-wrap justify-content-between align-items-center mt-2 small text-muted">
							<div>
								<i class="fa-regular fa-calendar mr-1"></i>{{ $agr['meeting'] }} · {{ $agr['date'] }}
								<span class="mx-1">·</span>
								<i class="fa-solid fa-diagram-project mr-1"></i>Proceso: <strong>{{ $agr['process'] }}</strong>
								<span class="mx-1">·</span>
								<i class="fa-solid fa-user-tag mr-1"></i>Rol sugerido: <strong>{{ $roleLabels[$agr['role']] ?? ucfirst($agr['role']) }}</strong>
							</div>
							<div class="mt-2 mt-sm-0">
								@if(!empty($agr['linked_task_id']))
									<span class="badge badge-success px-2 py-1 mr-1" title="Ya convertido en tarea activa">
										<i class="fa-solid fa-link mr-1"></i>Enlazado a Tarea #{{ $agr['linked_task_id'] }}
									</span>
									<button type="button" class="btn btn-xs btn-outline-primary" data-dismiss="modal" onclick="highlightTask({{ $agr['linked_task_id'] }})">
										Ver tarea <i class="fa-solid fa-arrow-right ml-1"></i>
									</button>
								@else
									<button type="button" class="btn btn-sm btn-success font-weight-bold btn-convert-agr" 
									        data-id="{{ $agr['id'] }}" 
									        data-title="{{ $agr['title'] }}" 
									        data-role="{{ $agr['role'] }}"
									        data-hours="{{ $agr['due_hours'] }}">
										<i class="fa-solid fa-plus-circle mr-1"></i>Convertir en Tarea
									</button>
								@endif
							</div>
						</div>
					</div>
					@endforeach
					@endif
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
	// Estado de red real (la cola sin conexión guarda lo que se registre sin red)
	function wfNet() {
		var on = navigator.onLine !== false, el = document.getElementById('wf-net-status');
		if (!el) return;
		el.className = 'badge px-2 py-1 ' + (on ? 'badge-success' : 'badge-warning');
		el.innerHTML = '<i class="fa-solid fa-wifi mr-1"></i>' + (on ? 'En línea' : 'Sin conexión: se guarda y se envía al volver');
	}
	window.addEventListener('online', wfNet); window.addEventListener('offline', wfNet); wfNet();

	// Completar tarea (sin conexión queda en la cola y se envía una sola vez al volver la red)
	var BTN_DONE = '<i class="fa-solid fa-check mr-1"></i>Completar';
	document.querySelectorAll('.btn-complete-task').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var taskId = this.getAttribute('data-id');
			var card = document.getElementById('task-card-' + taskId);
			var title = (card.querySelector('h6') || {}).textContent || ('Tarea #' + taskId);

			btn.disabled = true;
			btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Guardando...';

			DairyOffline.send('/workforce/tasks/' + taskId + '/complete', { notes: 'Ejecutado conforme a protocolo de piso de tienda' }, { label: 'Completar: ' + title.trim() })
			.then(function(r) {
				if (!r.queued) { toastr.success('Tarea completada'); }
				card.classList.add('bg-light');
				card.setAttribute('data-status', 'completed');
				card.innerHTML = '<div class="font-weight-bold py-2 text-center ' + (r.queued ? 'text-warning' : 'text-success') + '"><i class="fa-solid ' + (r.queued ? 'fa-cloud-arrow-up' : 'fa-circle-check') + ' mr-2"></i>' + (r.queued ? 'Completada sin conexión: se enviará al recuperar la red' : 'Completada') + '</div>';
				if (!r.queued) { setTimeout(function() { card.remove(); applyTaskFilters(); }, 2000); }
			})
			.catch(function(err) {
				toastr.error(err.message || 'No se pudo completar la tarea');
				btn.disabled = false;
				btn.innerHTML = BTN_DONE;
			});
		});
	});

	// Transición de estado de tarea (Iniciar, Bloquear, Desbloquear)
	document.querySelectorAll('.btn-task-status').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var taskId = this.getAttribute('data-id');
			var nextStatus = this.getAttribute('data-status');
			var origHtml = this.innerHTML;
			this.disabled = true;
			this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

			fetch(Grocy.BaseUrl + '/api/workforce/tasks/' + taskId + '/status', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ status: nextStatus })
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.error_message) {
					toastr.error(res.error_message);
					btn.disabled = false;
					btn.innerHTML = origHtml;
					return;
				}
				toastr.success(res.message || 'Estado actualizado');
				window.location.reload();
			})
			.catch(function() {
				btn.disabled = false;
				btn.innerHTML = origHtml;
				toastr.error('Error de comunicación con el servidor');
			});
		});
	});

	// Convertir Acuerdo de Reunión en Tarea
	document.querySelectorAll('.btn-convert-agr').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var agrId = this.getAttribute('data-id');
			var title = this.getAttribute('data-title');
			var role = this.getAttribute('data-role');
			var hours = this.getAttribute('data-hours');
			var branchId = document.getElementById('wf-branch-select').value;
			var origHtml = this.innerHTML;

			this.disabled = true;
			this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Creando...';

			fetch(Grocy.BaseUrl + '/api/workforce/tasks/convert-agreement', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					agreement_id: parseInt(agrId),
					title: title,
					role: role,
					due_hours: parseInt(hours),
					branch_id: parseInt(branchId)
				})
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.error_message) {
					toastr.error(res.error_message);
					btn.disabled = false;
					btn.innerHTML = origHtml;
					return;
				}
				toastr.success(res.message);
				$('#modal-agreements').modal('hide');
				setTimeout(function() { window.location.reload(); }, 600);
			})
			.catch(function() {
				btn.disabled = false;
				btn.innerHTML = origHtml;
				toastr.error('Error de red al crear la tarea');
			});
		});
	});

	// Filtrado combinado por Rol y por Estado
	var currentRole = 'all';
	var currentStatus = 'all';

	function applyTaskFilters() {
		document.querySelectorAll('.task-card').forEach(function(card) {
			var role = card.getAttribute('data-role');
			var status = card.getAttribute('data-status');
			var overdue = card.getAttribute('data-overdue');

			var roleMatch = (currentRole === 'all' || role === currentRole);
			var statusMatch = true;

			if (currentStatus === 'overdue') {
				statusMatch = (overdue === '1');
			} else if (currentStatus !== 'all') {
				statusMatch = (status === currentStatus);
			}

			if (roleMatch && statusMatch) {
				card.style.display = 'block';
			} else {
				card.style.display = 'none';
			}
		});
	}

	// Status filter pills
	document.querySelectorAll('.wf-status-pill').forEach(function(pill) {
		pill.addEventListener('click', function() {
			document.querySelectorAll('.wf-status-pill').forEach(function(p) {
				p.classList.remove('btn-primary', 'active');
				p.classList.add('btn-light', 'border');
			});
			this.classList.remove('btn-light', 'border');
			this.classList.add('btn-primary', 'active');
			currentStatus = this.getAttribute('data-status');
			applyTaskFilters();
		});
	});

	// Role filter chips
	document.querySelectorAll('.wf-role-chip').forEach(function(chip) {
		chip.addEventListener('click', function() {
			document.querySelectorAll('.wf-role-chip').forEach(function(c) {
				c.classList.remove('btn-primary', 'active');
				c.classList.add('btn-light', 'border');
			});
			this.classList.remove('btn-light', 'border');
			this.classList.add('btn-primary', 'active');
			currentRole = this.getAttribute('data-role');
			applyTaskFilters();
		});
	});

	window.highlightTask = function(taskId) {
		var card = document.getElementById('task-card-' + taskId);
		if (card) {
			card.scrollIntoView({ behavior: 'smooth', block: 'center' });
			card.classList.add('border-primary');
			card.style.boxShadow = '0 0 10px rgba(0, 200, 59, 0.4)';
			setTimeout(function() {
				card.style.boxShadow = '';
				card.classList.remove('border-primary');
			}, 3000);
		} else {
			toastr.info('La tarea #' + taskId + ' se encuentra en otra vista o estado.');
		}
	};

	// Merma rápida: mismo flujo que Registrar merma
	var BTN_WASTE = '<i class="fa-solid fa-triangle-exclamation mr-1"></i>Registrar merma';
	var wasteForm = document.getElementById('quick-waste-form');
	wasteForm.addEventListener('submit', function(e) {
		e.preventDefault();
		var prodSel = document.getElementById('quick-waste-product');
		var prodId = prodSel.value;
		var amount = parseFloat(document.getElementById('quick-waste-amount').value);
		var reason = document.getElementById('quick-waste-reason').value;
		var notes = document.getElementById('quick-waste-notes').value;
		var branchId = document.getElementById('wf-branch-select').value;
		var photo = photoInput && photoInput.files && photoInput.files[0] ? photoInput.files[0] : null;

		if (!prodId || isNaN(amount) || amount <= 0) {
			toastr.warning('Ingrese producto y una cantidad mayor a 0');
			return;
		}
		if (!reason) {
			toastr.warning('Seleccione la causal');
			return;
		}

		var btnSubmit = document.getElementById('btn-submit-waste');
		btnSubmit.disabled = true;
		btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Registrando...';
		var prodName = prodSel.options[prodSel.selectedIndex] ? prodSel.options[prodSel.selectedIndex].text : ('#' + prodId);

		DairyOffline.send('/workforce/quick-waste', {
			product_id: parseInt(prodId),
			branch_id: parseInt(branchId),
			amount: amount,
			reason_code: reason,
			notes: notes
		}, { label: 'Merma: ' + amount + ' × ' + prodName.trim(), photo: photo })
		.then(function(r) {
			btnSubmit.disabled = false;
			btnSubmit.innerHTML = BTN_WASTE;
			if (!r.queued) { toastr.success(r.data.message || 'Merma registrada'); }
			wasteForm.reset();
			if (photoPreview) {
				photoPreview.classList.add('d-none');
				photoPreview.classList.remove('d-flex');
				photoLabel.textContent = 'Tomar o elegir foto...';
			}
		})
		.catch(function(err) {
			btnSubmit.disabled = false;
			btnSubmit.innerHTML = BTN_WASTE;
			toastr.error(err.message || 'No se pudo registrar la merma');
		});
	});

	// Photo file preview
	var photoInput = document.getElementById('quick-waste-photo');
	var photoPreview = document.getElementById('quick-waste-photo-preview');
	var photoImg = document.getElementById('quick-waste-photo-img');
	var photoName = document.getElementById('quick-waste-photo-name');
	var photoLabel = document.getElementById('quick-waste-photo-label');

	if (photoInput) {
		photoInput.addEventListener('change', function() {
			if (this.files && this.files[0]) {
				var file = this.files[0];
				photoLabel.textContent = file.name;
				photoName.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
				var reader = new FileReader();
				reader.onload = function(e) {
					photoImg.src = e.target.result;
					photoPreview.classList.remove('d-none');
					photoPreview.classList.add('d-flex');
				};
				reader.readAsDataURL(file);
			}
		});
	}
});
</script>
@stop
