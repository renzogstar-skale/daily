@extends('layout.default')

@section('title', 'Piso de Tienda')

@section('content')
@php
$roleLabels = ['clerk' => 'Auxiliar de piso', 'supervisor' => 'Supervisor', 'manager' => 'Gerente', 'warehouse' => 'Bodeguero', 'stocker' => 'Reponedor'];
$canManage = $canManage ?? false;
$wfTotal = max(1, intval($taskMetrics['total'] ?? count($tasks ?? [])));
$wfCompliancePct = round((intval($taskMetrics['completed'] ?? 0) / $wfTotal) * 100, 1);
@endphp
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-7 d-flex align-items-center">
		<div class="dairy-page-icon is-success"><i class="fa-solid fa-mobile-screen-button"></i></div>
		<div>
			<h4 class="dairy-page-title">Piso de Tienda y Ejecución Operativa</h4>
			<p class="dairy-page-subtitle">Ejecución del personal en tienda: checklists por turno, cumplimiento de tareas asignadas, capacitaciones e incidentes operativos.</p>
		</div>
	</div>
	<div class="col-md-5 text-md-right mt-2 mt-md-0 dairy-page-actions">
		<a href="{{ $U('/operations/scheduling') }}" class="btn btn-outline-primary btn-sm shadow-sm mr-1">
			<i class="fa-solid fa-calendar-days mr-1"></i>Horarios y Turnos
		</a>
		<a href="{{ $U('/operations/attendance') }}" class="btn btn-outline-success btn-sm shadow-sm mr-1">
			<i class="fa-solid fa-fingerprint mr-1"></i>Asistencia
		</a>
		@if($canManage)
		<a href="{{ $U('/hr') }}" class="btn btn-success btn-sm shadow-sm">
			<i class="fa-solid fa-users-gear mr-1"></i>Portal RR.HH.
		</a>
		@endif
	</div>
</div>

<!-- Ciclo Operativo Laboral de 7 Etapas -->
<div class="card shadow-sm border-0 mb-3 bg-white" id="wf-cycle-banner">
	<div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
		<span class="small font-weight-bold text-uppercase text-muted"><i class="fa-solid fa-arrows-spin mr-1 text-primary"></i>Ciclo de Operaciones y Personal</span>
		<div class="d-flex flex-wrap align-items-center small" style="gap: 6px;">
			<span class="badge badge-light border text-muted">1. Planificar</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-light border text-muted">2. Programar</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-light border text-muted">3. Asignar</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-light border text-muted">4. Registrar asistencia</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-play mr-1"></i>5. Ejecutar</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-light border text-muted">6. Evaluar ({{ $wfCompliancePct }}%)</span>
			<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
			<span class="badge badge-light border text-muted">7. Ajustar</span>
		</div>
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
	['label' => 'Tareas Bloqueadas', 'value' => $taskMetrics['blocked'], 'sub' => 'en espera de autorización / insumo', 'icon' => 'fa-lock', 'tone' => $taskMetrics['blocked'] > 0 ? 'warning' : 'secondary', 'alert' => $taskMetrics['blocked'] > 0],
	['label' => 'En Progreso', 'value' => $taskMetrics['in_progress'], 'sub' => 'en ejecución en piso', 'icon' => 'fa-spinner', 'tone' => 'primary'],
	['label' => 'Pendientes', 'value' => $taskMetrics['pending'], 'sub' => 'por iniciar en el turno', 'icon' => 'fa-list-check', 'tone' => 'secondary'],
	['label' => 'Cumplimiento', 'value' => $wfCompliancePct . '%', 'sub' => $taskMetrics['completed'] . ' de ' . $taskMetrics['total'] . ' tareas completadas', 'icon' => 'fa-circle-check', 'tone' => $wfCompliancePct >= 80 ? 'success' : 'warning']
]])
@endif

<!-- NAVEGACIÓN EN PESTAÑAS: TAREAS, CERTIFICACIONES E INCIDENTES DE PERSONAL -->
<div class="mb-3">
	<ul class="nav nav-pills bg-white shadow-sm p-1 rounded border" id="workforceTabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active font-weight-bold" id="wf-tab-floor-link" data-toggle="pill" href="#wf-tab-floor" role="tab">
				<i class="fa-solid fa-list-check mr-1 text-primary"></i>1. Ejecución & Tareas de Turno
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-bold" id="wf-tab-certifications-link" data-toggle="pill" href="#wf-tab-certifications" role="tab">
				<i class="fa-solid fa-graduation-cap mr-1 text-success"></i>2. Capacitaciones & Certificaciones
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-bold text-danger" id="wf-tab-incidents-link" data-toggle="pill" href="#wf-tab-incidents" role="tab">
				<i class="fa-solid fa-triangle-exclamation mr-1"></i>3. Incidentes Operativos
			</a>
		</li>
	</ul>
</div>

<div class="tab-content" id="workforceTabContent">
	<!-- PESTAÑA 1: EJECUCIÓN DE TAREAS DE PERSONAL -->
	<div class="tab-pane fade show active" id="wf-tab-floor" role="tabpanel">


<div class="row">
	<!-- Columna Completa: Checklists y Tareas Operativas del Turno -->
	<div class="col-12 mb-4">
		<div class="card shadow-sm border-0 h-100">
			<div class="card-header bg-primary text-white py-3 d-flex flex-wrap justify-content-between align-items-center">
				<div>
					<h5 class="mb-0 font-weight-bold">
						<i class="fa-solid fa-list-check mr-2"></i>Checklists y Tareas Asignadas por Turno
					</h5>
					<small class="text-white-50">Rutinas operativas de apertura, cierre, piso de tienda y acuerdos de coordinación</small>
				</div>
				<div class="d-flex align-items-center mt-2 mt-sm-0">
					@if($canManage)
					<button type="button" class="btn btn-sm btn-light font-weight-bold mr-2 text-primary shadow-sm" data-toggle="modal" data-target="#modal-agreements" title="Convertir acuerdos de comités operativos en tareas asignadas">
						<i class="fa-solid fa-handshake mr-1"></i>Acuerdos
						@if(isset($meetingAgreements))
						<span class="badge badge-primary ml-1">{{ count($meetingAgreements) }}</span>
						@endif
					</button>
					@endif
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
						if (($task->source_type ?? null) === 'agreement') {
							$processName = 'Acuerdo Operativo #' . $task->source_id;
						} elseif (($task->task_type ?? '') === 'corrective_action') {
							$processName = 'Acción Correctiva de Turno';
						} elseif (($task->task_type ?? '') === 'shift_checklist') {
							$processName = 'Checklist de Turno';
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

@if($canManage)
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
@endif

	</div>{{-- Cierre wf-tab-floor --}}

	<!-- PESTAÑA 2: CAPACITACIONES & CERTIFICACIONES OPERATIVAS -->
	<div class="tab-pane fade" id="wf-tab-certifications" role="tabpanel">
		@php
			$certValidCount = count(array_filter($certifications ?? [], fn($c) => ($c['status'] ?? '') === 'valid'));
			$certExpiredCount = count(array_filter($certifications ?? [], fn($c) => ($c['status'] ?? '') !== 'valid'));
			$certTotalCount = max(1, count($certifications ?? []));
			$certCompliancePct = count($certifications ?? []) > 0 ? round(($certValidCount / $certTotalCount) * 100, 1) : 100.0;
		@endphp
		@if($canManage)
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">CERTIFICACIONES VIGENTES</div>
					<div class="h4 font-weight-bold text-success mb-0">{{ $certValidCount }} <span class="small font-weight-normal text-muted">colaboradores</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-circle-check text-success mr-1"></i> Habilitaciones al día</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">VENCIDAS / RESTRINGIDAS</div>
					<div class="h4 font-weight-bold text-danger mb-0">{{ $certExpiredCount }} <span class="small font-weight-normal text-muted">alertas</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-lock text-danger mr-1"></i> Requieren recertificación</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">CUMPLIMIENTO NORMATIVO</div>
					<div class="h4 font-weight-bold text-warning mb-0">{{ $certCompliancePct }}%</div>
					<div class="small text-muted mt-1">Sobre el personal asignado</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">CURSOS OBLIGATORIOS</div>
					<div class="h4 font-weight-bold text-info mb-0">3 materias</div>
					<div class="small text-muted mt-1">Operación de Tienda, SST y Atención</div>
				</div>
			</div>
		</div>
		@else
		<div class="alert alert-light border small mb-3"><i class="fa-solid fa-shield-halved text-primary mr-1"></i> Vista de colaborador: solo se muestran sus propias habilitaciones operativas.</div>
		@endif

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-graduation-cap text-success mr-2"></i>Matriz de Capacitación, Habilidades y Certificaciones Obligatorias</h5>
					<div class="small text-muted">Vigencias, entidades emisoras y restricciones automáticas de operación en piso y maquinaria.</div>
				</div>
				@if($canManage)
				<button class="btn btn-sm btn-success font-weight-bold" onclick="alert('Abriendo formulario de registro de nueva certificación laboral.');">
					<i class="fa-solid fa-plus mr-1"></i> Registrar Certificación
				</button>
				@endif
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Colaborador</th>
							<th>Código Cert.</th>
							<th>Curso / Competencia</th>
							<th>Fecha Emisión</th>
							<th>Vencimiento</th>
							<th>Ente Emisor</th>
							<th>Condición Operativa</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach(($certifications ?? []) as $cert)
						<tr class="@if($cert['status'] === 'expired') table-danger @endif">
							<td class="font-weight-bold text-dark"><i class="fa-solid fa-user mr-1 text-muted"></i> {{ $cert['employee_name'] }}</td>
							<td><code>{{ $cert['certification_code'] }}</code></td>
							<td><strong>{{ $cert['course_name'] }}</strong></td>
							<td class="small text-muted">{{ date('d/m/Y', strtotime($cert['issue_date'])) }}</td>
							<td class="small font-weight-bold @if($cert['status'] === 'expired') text-danger @else text-dark @endif">
								{{ date('d/m/Y', strtotime($cert['expiry_date'])) }}
							</td>
							<td class="small text-muted">{{ $cert['issuer'] }}</td>
							<td>
								@if($cert['status'] === 'expired')
									<span class="badge badge-danger"><i class="fa-solid fa-ban mr-1"></i> {{ $cert['operating_restriction'] }}</span>
								@else
									<span class="badge badge-success"><i class="fa-solid fa-circle-check mr-1"></i> {{ $cert['operating_restriction'] }}</span>
								@endif
							</td>
							<td class="text-center">
								@if($cert['status'] === 'valid')
									<span class="badge badge-success">Vigente</span>
								@else
									<span class="badge badge-danger">Vencida</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- PESTAÑA 3: GESTIÓN DE INCIDENTES OPERATIVOS -->
	<div class="tab-pane fade" id="wf-tab-incidents" role="tabpanel">
		@if($canManage)
		<div class="row mb-3">
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">INCIDENTES EN TURNO</div>
					<div class="h4 font-weight-bold text-dark mb-0">{{ count($operationalIncidents ?? []) }} <span class="small font-weight-normal text-muted">reportes</span></div>
					<div class="small text-muted mt-1"><i class="fa-solid fa-shield-halved text-success mr-1"></i> Protocolos de piso activados</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">GRAVEDAD MEDIA / ALTA</div>
					<div class="h4 font-weight-bold text-warning mb-0">{{ count(array_filter($operationalIncidents ?? [], fn($i) => in_array(strtolower($i['severity'] ?? ''), ['high', 'medium', 'alta', 'media']))) }} casos</div>
					<div class="small text-muted mt-1">Seguimiento de novedades en turno</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">TIEMPO MEDIO DE RESOLUCIÓN</div>
					<div class="h4 font-weight-bold text-success mb-0">35 min</div>
					<div class="small text-muted mt-1">Atención rápida en piso</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card border-0 shadow-sm p-3 bg-white">
					<div class="text-muted small font-weight-bold">ACCIONES PREVENTIVAS</div>
					<div class="h4 font-weight-bold text-info mb-0">100%</div>
					<div class="small text-muted mt-1">Con causa raíz identificada</div>
				</div>
			</div>
		</div>
		@endif

		<div class="card shadow-sm border-0 mb-4">
			<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div>
					<h5 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-triangle-exclamation text-danger mr-2"></i>Bitácora de Incidentes Operativos del Personal</h5>
					<div class="small text-muted">Registro de novedades en turno, responsables y acciones correctivas aplicadas.</div>
				</div>
				@if($canManage)
				<div>
					<button class="btn btn-sm btn-danger font-weight-bold" onclick="alert('Abriendo reporte de nuevo incidente operativo en tienda.');">
						<i class="fa-solid fa-plus mr-1"></i> Reportar Nuevo Incidente
					</button>
				</div>
				@endif
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-sm mb-0">
					<thead class="thead-light">
						<tr>
							<th>Código</th>
							<th>Título del Incidente</th>
							<th>Gravedad</th>
							<th>Sucursal y Depto</th>
							<th>Reportado Por</th>
							<th>Fecha y Hora</th>
							<th>Acción Correctiva Aplicada</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach(($operationalIncidents ?? []) as $inc)
						<tr>
							<td><code>{{ $inc['incident_code'] }}</code></td>
							<td class="font-weight-bold text-dark">{{ $inc['title'] }}</td>
							<td>
								@if($inc['severity'] === 'high')
									<span class="badge badge-danger">Grave</span>
								@elseif($inc['severity'] === 'medium')
									<span class="badge badge-warning text-dark">Media</span>
								@else
									<span class="badge badge-info">Leve</span>
								@endif
							</td>
							<td>{{ $inc['branch_name'] }} · {{ $inc['department'] }}</td>
							<td><i class="fa-solid fa-user-pen mr-1 text-muted"></i> {{ $inc['reported_by_name'] }}</td>
							<td class="small text-muted">{{ date('d/m/Y H:i', strtotime($inc['occurred_at'])) }}</td>
							<td class="small">{{ $inc['corrective_action'] }}</td>
							<td class="text-center">
								@if($inc['status'] === 'resolved' || $inc['status'] === 'closed')
									<span class="badge badge-success"><i class="fa-solid fa-check"></i> Resuelto</span>
								@else
									<span class="badge badge-warning text-dark"><i class="fa-solid fa-clock"></i> Abierto</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>{{-- Cierre workforceTabContent --}}
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
});
</script>
@stop
