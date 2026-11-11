@php require_frontend_packages(['chartjs', 'daterangepicker']); @endphp
@extends('layout.default')

@section('title', 'Asistencia — Control de entradas, salidas y novedades')

@push('pageStyles')
<link href="{{ $U('/css/daily_attendance.css') }}" rel="stylesheet">
<style>
/* Estilos específicos de componentes y estados */
.emp-cell { display: flex; align-items: center; gap: 0.65rem; }
.emp-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
.emp-avatar-initials { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.emp-name-box { display: flex; flex-direction: column; }
.emp-name { font-size: 0.88rem; font-weight: 600; color: #0f172a; line-height: 1.2; }
.emp-code { font-size: 0.72rem; color: #64748b; }
.time-green { color: #059669; font-weight: 700; }
.time-red { color: #dc2626; font-weight: 700; }
.time-muted { color: #94a3b8; }
.badge-status { font-size: 0.74rem; font-weight: 600; padding: 0.25rem 0.65rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: 0.35rem; }
.badge-status-presente { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.badge-status-tardanza { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.badge-status-ausente { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.badge-status-pendiente { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.daily-table-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.daily-table-header { padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 0.75rem; }
.daily-table-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.daily-table-responsive { overflow-x: auto; width: 100%; }
.daily-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.daily-table th { background: #f8fafc; color: #64748b; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; }
.daily-table td { padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.daily-table tbody tr:hover { background-color: #f8fafc; }
.daily-bottom-grid { display: grid; grid-template-columns: 3fr 2fr; gap: 1rem; margin-bottom: 1.5rem; }
.daily-chart-card, .daily-alerts-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.daily-chart-header, .daily-alerts-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; }
.daily-chart-legend { display: flex; align-items: center; gap: 0.75rem; font-size: 0.78rem; font-weight: 600; color: #64748b; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
.daily-alerts-list { display: flex; flex-direction: column; gap: 0.65rem; }
.alert-row-item { display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; }
.alert-row-item:hover { background: #f1f5f9; border-color: #cbd5e1; }
.alert-left-box { display: flex; align-items: center; gap: 0.65rem; }
.alert-icon-circle { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
.icon-danger { background: #fef2f2; color: #ef4444; }
.icon-warning { background: #fffbeb; color: #f59e0b; }
.icon-info { background: #f0f9ff; color: #0284c7; }
.alert-text-box { display: flex; flex-direction: column; }
.alert-person { font-size: 0.84rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
.alert-desc { font-size: 0.74rem; color: #64748b; }
.alert-right-box { display: flex; align-items: center; gap: 0.4rem; font-size: 0.74rem; color: #94a3b8; font-weight: 600; }
.daily-dept-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.85rem; margin-bottom: 1.25rem; }
.daily-dept-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; }
.daily-dept-card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.06); border-color: #cbd5e1; }
.daily-dept-card.is-active { border-color: #00C83B; box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.2); }
.daily-dept-img-wrap { height: 84px; position: relative; background-color: #e2e8f0; overflow: hidden; }
.daily-dept-img { width: 100%; height: 100%; object-fit: cover; }
.daily-dept-body { padding: 0.75rem 0.85rem; display: flex; flex-direction: column; flex-grow: 1; }
.daily-dept-name { font-size: 0.86rem; font-weight: 700; color: #0f172a; margin: 0 0 0.35rem 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.daily-dept-nums { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem; }
.daily-dept-count { font-size: 0.82rem; font-weight: 700; color: #0f172a; }
.daily-dept-pct { font-size: 0.78rem; font-weight: 700; }
.pct-green { color: #059669; }
.pct-amber { color: #d97706; }
.daily-progress { height: 5px; background: #e2e8f0; border-radius: 9999px; overflow: hidden; }
.daily-progress-bar { height: 100%; border-radius: 9999px; }
.bar-green { background: #10b981; }
.bar-amber { background: #f59e0b; }
.subnav-tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem; padding-bottom: 0.5rem; }
.subnav-tab-btn { background: transparent; border: none; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 600; color: #64748b; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; }
.subnav-tab-btn:hover { color: #0f172a; background: #f1f5f9; }
.subnav-tab-btn.is-active { background: #ecfdf5; color: #059669; font-weight: 700; }
@media (max-width: 991px) {
	.daily-dept-grid { grid-template-columns: repeat(2, 1fr); }
	.daily-bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 576px) {
	.daily-dept-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
@php $canSupervise = ($canAttendance ?? false) || ($canManage ?? false); @endphp
<div class="daily-att-wrapper">

	<!-- Ciclo Operativo Laboral de 7 Etapas (Etapa 4 activa: Registrar asistencia) -->
	<div class="card shadow-sm border-0 mb-3 bg-white" id="att-cycle-banner">
		<div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between" style="gap: 8px;">
			<span class="small font-weight-bold text-uppercase text-muted"><i class="fa-solid fa-arrows-spin mr-1 text-success"></i>Ciclo de Operaciones y Personal</span>
			<div class="d-flex flex-wrap align-items-center small" style="gap: 6px;">
				<span class="badge badge-light border text-muted">1. Planificar</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-light border text-muted">2. Programar</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-light border text-muted">3. Asignar</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-fingerprint mr-1"></i>4. Registrar asistencia</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-light border text-muted">5. Ejecutar</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-light border text-muted">6. Evaluar</span>
				<i class="fa-solid fa-chevron-right text-muted" style="font-size: .65rem;"></i>
				<span class="badge badge-light border text-muted">7. Ajustar</span>
			</div>
		</div>
	</div>

	<!-- 1. BARRA DE SELECCIÓN DE ROL (EMPLEADO VS SUPERVISOR) -->
	<div class="daily-role-bar">
		<div class="d-flex align-items-center gap-3">
			<span class="small font-weight-700 text-muted text-uppercase tracking-wider">
				<i class="fa-solid fa-user-gear mr-1"></i> Modo de Operación:
			</span>
			<div class="role-switcher-pills">
				<a href="?role_view=employee&branch_id={{ $overview['branch_id'] }}&employee_id={{ $selectedEmployeeId }}&date={{ $overview['selected_date'] }}" 
				   class="role-pill-btn {{ $roleView === 'employee' ? 'is-active' : '' }}">
					<i class="fa-solid fa-id-card"></i> Vista Empleado (Reloj de Fichaje)
				</a>
				@if($canSupervise)
				<a href="?role_view=supervisor&branch_id={{ $overview['branch_id'] }}&date={{ $overview['selected_date'] }}" 
				   class="role-pill-btn {{ $roleView === 'supervisor' ? 'is-active' : '' }}">
					<i class="fa-solid fa-chart-pie"></i> Vista Supervisor (Control WFM)
				</a>
				@endif
			</div>
		</div>

		@if($roleView === 'employee')
			@if($canSupervise && count($employees) > 1)
			<!-- Selector interactivo de colaborador para gerencia/supervisión -->
			<div class="d-flex align-items-center gap-2">
				<label class="small text-muted font-weight-600 mb-0">Simular como:</label>
				<select class="form-control form-control-sm daily-filter-input" style="width: 250px; height: 32px;" onchange="switchPortalEmployee(this.value)">
					@foreach($employees as $emp)
						<option value="{{ $emp['id'] }}" {{ $emp['id'] == $selectedEmployeeId ? 'selected' : '' }}>
							{{ $emp['name'] }} ({{ str_replace('DEMO-', 'EJ-', $emp['payroll_code']) }})
						</option>
					@endforeach
				</select>
			</div>
			@else
			<div class="d-flex align-items-center gap-2">
				<span class="badge badge-light border px-2 py-1" style="font-size: 0.76rem;">
					<i class="fa-solid fa-user-shield mr-1 text-primary"></i> Mi Portal de Fichaje ({{ $currentUserRole }})
				</span>
			</div>
			@endif
		@else
			<div class="d-flex align-items-center gap-2">
				<span class="badge badge-success px-2 py-1" style="font-size: 0.76rem;">
					<i class="fa-solid fa-shield-halved mr-1"></i> Sesión: {{ $currentUserRole }}
				</span>
			</div>
		@endif
	</div>

	<!-- ============================================================== -->
	<!-- CASO 1: VISTA EMPLEADO / RELOJ DE FICHAJE                       -->
	<!-- ============================================================== -->
	@if($roleView === 'employee')
		<div id="employeePortalView">
			<!-- Header del Empleado -->
			<div class="daily-att-header">
				<div class="daily-att-title-box">
					<div class="daily-att-icon-badge">
						<i class="fa-solid fa-user-clock"></i>
					</div>
					<div>
						<h1 class="daily-att-title">{{ $employeePortal['employee']['name'] }}</h1>
						<p class="daily-att-subtitle">
							Código: <strong class="text-dark">{{ str_replace('DEMO-', 'EJ-', $employeePortal['employee']['payroll_code']) }}</strong> &nbsp;|&nbsp; 
							Puesto: <strong class="text-dark">{{ $employeePortal['employee']['position'] ?: 'Colaborador' }}</strong> &nbsp;|&nbsp; 
							Área: <strong class="text-dark">{{ $employeePortal['employee']['department'] }}</strong> &nbsp;|&nbsp; 
							Sucursal: <strong class="text-dark">{{ $employeePortal['employee']['branch_name'] ?: 'Sucursal Asignada' }}</strong>
						</p>
					</div>
				</div>
				<div class="daily-att-actions">
					<button type="button" class="btn btn-daily-outline" onclick="openEmployeeCorrectionModal()">
						<i class="fa-solid fa-clock-rotate-left mr-1 text-warning"></i> Solicitar corrección
					</button>
				</div>
			</div>

			<!-- Terminal Central de Marcación y Estado -->
			<div class="employee-terminal-card">
				<div class="terminal-clock-box">
					<div class="terminal-live-badge">
						<div class="live-pulse-dot"></div> Reloj Oficial de Asistencia en Tiempo Real
					</div>
					<div class="terminal-clock-time" id="terminalLiveClock">--:--:--</div>
					<div class="terminal-clock-date" id="terminalLiveDate">Cargando fecha...</div>
				</div>

				<!-- Estado Actual del Empleado -->
				<div class="text-center mb-4">
					<span class="text-muted small text-uppercase font-weight-700 tracking-wider d-block mb-1">Estado de la Jornada Hoy</span>
					<span class="badge {{ $employeePortal['state_badge_class'] }} px-3 py-2" style="font-size: 1.05rem; font-weight: 700; border-radius: 9999px;">
						● {{ $employeePortal['state_label'] }}
					</span>
					@if($employeePortal['attendance'])
						<div class="mt-2 text-muted small">
							@if($employeePortal['attendance']['clock_in'])
								<span class="mr-3"><i class="fa-solid fa-arrow-right-to-bracket text-success mr-1"></i> Entrada: <strong>{{ date('H:i', strtotime($employeePortal['attendance']['clock_in'])) }}</strong></span>
							@endif
							@if(!empty($employeePortal['attendance']['break_start']))
								<span class="mr-3"><i class="fa-solid fa-utensils text-warning mr-1"></i> Descanso: <strong>{{ date('H:i', strtotime($employeePortal['attendance']['break_start'])) }}</strong> @if(!empty($employeePortal['attendance']['break_end'])) a <strong>{{ date('H:i', strtotime($employeePortal['attendance']['break_end'])) }}</strong> @endif</span>
							@endif
							@if(!empty($employeePortal['attendance']['clock_out']))
								<span><i class="fa-solid fa-arrow-right-from-bracket text-danger mr-1"></i> Salida: <strong>{{ date('H:i', strtotime($employeePortal['attendance']['clock_out'])) }}</strong></span>
							@endif
						</div>
					@endif
				</div>

				<!-- 4 Botones de Acción con Validación Secuencial -->
				<div class="terminal-actions-grid">
					<!-- 1. Entrada -->
					<button type="button" class="btn-punch-action btn-punch-in {{ !$employeePortal['can_clock_in'] ? 'is-disabled' : '' }}" 
							onclick="doClockIn({{ $employeePortal['employee']['id'] }})" {{ !$employeePortal['can_clock_in'] ? 'disabled' : '' }}>
						<i class="fa-solid fa-right-to-bracket"></i>
						<span>Registrar Entrada</span>
						<small class="text-muted font-weight-normal">Inicio de jornada</small>
					</button>

					<!-- 2. Iniciar Descanso -->
					<button type="button" class="btn-punch-action btn-punch-break-start {{ !$employeePortal['can_start_break'] ? 'is-disabled' : '' }}" 
							onclick="doStartBreak({{ $employeePortal['employee']['id'] }})" {{ !$employeePortal['can_start_break'] ? 'disabled' : '' }}>
						<i class="fa-solid fa-coffee"></i>
						<span>Iniciar Descanso</span>
						<small class="text-muted font-weight-normal">Almuerzo / Colación</small>
					</button>

					<!-- 3. Terminar Descanso -->
					<button type="button" class="btn-punch-action btn-punch-break-end {{ !$employeePortal['can_end_break'] ? 'is-disabled' : '' }}" 
							onclick="doEndBreak({{ $employeePortal['employee']['id'] }})" {{ !$employeePortal['can_end_break'] ? 'disabled' : '' }}>
						<i class="fa-solid fa-mug-hot"></i>
						<span>Terminar Descanso</span>
						<small class="text-muted font-weight-normal">Retomar actividades</small>
					</button>

					<!-- 4. Salida -->
					<button type="button" class="btn-punch-action btn-punch-out {{ !$employeePortal['can_clock_out'] ? 'is-disabled' : '' }}" 
							onclick="doClockOut({{ $employeePortal['employee']['id'] }})" {{ !$employeePortal['can_clock_out'] ? 'disabled' : '' }}>
						<i class="fa-solid fa-right-from-bracket"></i>
						<span>Registrar Salida</span>
						<small class="text-muted font-weight-normal">Fin de turno</small>
					</button>
				</div>

				<!-- Tarjeta de Turno Programado de Hoy -->
				<div class="bg-light p-3 rounded border">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<span class="small font-weight-700 text-dark text-uppercase">
							<i class="fa-solid fa-calendar-check text-success mr-1"></i> Programación Oficial de Hoy
						</span>
						<span class="badge badge-success px-2 py-1">Turno Publicado</span>
					</div>
					@if($employeePortal['shift'])
						<div class="row text-center font-size-13">
							<div class="col-md-3 col-6 mb-2 mb-md-0 border-right">
								<span class="text-muted d-block small">Horario Programado</span>
								<strong class="text-dark font-size-15">{{ $employeePortal['shift']['start_time'] }} – {{ $employeePortal['shift']['end_time'] }}</strong>
							</div>
							<div class="col-md-3 col-6 mb-2 mb-md-0 border-right">
								<span class="text-muted d-block small">Pausa para Descanso</span>
								<strong class="text-dark font-size-15">{{ $employeePortal['shift']['break_minutes'] ?: 60 }} min</strong>
							</div>
							<div class="col-md-3 col-6 border-right">
								<span class="text-muted d-block small">Área / Rol</span>
								<strong class="text-dark">{{ $employeePortal['shift']['department'] }} · {{ $employeePortal['shift']['role'] ?: 'Colaborador' }}</strong>
							</div>
							<div class="col-md-3 col-6">
								<span class="text-muted d-block small">Observaciones</span>
								<span class="text-muted">{{ $employeePortal['shift']['notes'] ?: 'Sin novedades de asignación' }}</span>
							</div>
						</div>
					@else
						<p class="text-muted small mb-0">No se encuentra un turno específico publicado para esta fecha.</p>
					@endif
				</div>
			</div>

			<!-- Historial de Fichajes Recientes (Últimos 7 días) -->
			<div class="daily-table-card">
				<div class="daily-table-header">
					<h2 class="daily-table-title">
						<i class="fa-solid fa-clock-rotate-left text-success"></i> Mis Registros Recientes (Últimos 7 días)
					</h2>
					<button type="button" class="btn btn-daily-outline btn-sm" onclick="openEmployeeCorrectionModal()">
						<i class="fa-solid fa-pen-to-square mr-1 text-warning"></i> Reportar inconsistencia
					</button>
				</div>
				<div class="daily-table-responsive">
					<table class="daily-table">
						<thead>
							<tr>
								<th>FECHA</th>
								<th>TURNO PROGRAMADO</th>
								<th>ENTRADA</th>
								<th>DESCANSO</th>
								<th>SALIDA</th>
								<th>ESTADO</th>
								<th>OBSERVACIONES</th>
								<th style="width: 140px; text-align: center;">ACCIÓN</th>
							</tr>
						</thead>
						<tbody>
							@forelse($employeePortal['history'] as $h)
								<tr>
									<td>
										<strong class="text-dark">{{ date('d/m/Y', strtotime($h['clock_in'])) }}</strong>
									</td>
									<td>
										<span class="text-muted">{{ $h['start_time'] ? ($h['start_time'] . ' – ' . $h['end_time']) : 'Turno Operativo' }}</span>
									</td>
									<td>
										<span class="font-weight-600 text-success">{{ date('H:i', strtotime($h['clock_in'])) }}</span>
									</td>
									<td>
										@if(!empty($h['break_start']))
											<span class="text-dark small">{{ date('H:i', strtotime($h['break_start'])) }} - {{ !empty($h['break_end']) ? date('H:i', strtotime($h['break_end'])) : 'En pausa' }}</span>
										@else
											<span class="text-muted">—</span>
										@endif
									</td>
									<td>
										@if(!empty($h['clock_out']))
											<span class="font-weight-600 text-dark">{{ date('H:i', strtotime($h['clock_out'])) }}</span>
										@else
											<span class="badge badge-warning px-2 py-1">Abierta</span>
										@endif
									</td>
									<td>
										@if($h['pending_adj_id'])
											<span class="badge badge-warning px-2 py-1"><i class="fa-solid fa-clock-rotate-left mr-1"></i> Corrección Pendiente</span>
										@elseif($h['clock_out'])
											<span class="badge badge-success px-2 py-1">● Completado</span>
										@else
											<span class="badge badge-info px-2 py-1">● En curso</span>
										@endif
									</td>
									<td>
										<span class="text-muted small">{{ $h['notes'] ?: 'Marcación biométrica validada' }}</span>
									</td>
									<td style="text-align: center;">
										<button type="button" class="btn btn-outline-secondary btn-sm font-size-12" 
												onclick="openCorrectionModal({{ $employeePortal['employee']['id'] }}, '{{ $employeePortal['employee']['name'] }}', '{{ date('H:i', strtotime($h['clock_in'])) }}', '{{ !empty($h['clock_out']) ? date('H:i', strtotime($h['clock_out'])) : '17:00' }}', {{ $h['id'] }})">
											<i class="fa-solid fa-pen-to-square mr-1 text-warning"></i> Corregir
										</button>
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="8" class="text-center py-4 text-muted">
										No hay registros de marcación en los últimos 7 días.
									</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

	<!-- ============================================================== -->
	<!-- CASO 2: VISTA SUPERVISOR / GERENCIA (CONTROL WFM)              -->
	<!-- ============================================================== -->
	@else
		<div id="supervisorWfmView">

			<!-- Header del Supervisor -->
			<div class="daily-att-header">
				<div class="daily-att-title-box">
					<div class="daily-att-icon-badge">
						<i class="fa-solid fa-clipboard-user"></i>
					</div>
					<div>
						<h1 class="daily-att-title">Control de Asistencia y Dotación</h1>
						<p class="daily-att-subtitle">Supervisión operativa de entradas, salidas, puntualidad y correcciones</p>
					</div>
				</div>
				<div class="daily-att-actions">
					<button type="button" class="btn btn-daily-green" data-toggle="modal" data-target="#modalRecordAttendance">
						<i class="fa-solid fa-plus mr-1"></i> Registrar asistencia manual
					</button>
					<div class="dropdown">
						<button type="button" class="btn btn-daily-outline dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							<i class="fa-solid fa-bolt mr-1 text-warning"></i> Acciones rápidas
						</button>
						<div class="dropdown-menu dropdown-menu-right shadow-sm">
							<a class="dropdown-item" href="javascript:void(0)" onclick="openMassCheckoutModal()">
								<i class="fa-solid fa-arrow-right-from-bracket mr-2 text-primary"></i> Registrar salida masiva de fin de turno
							</a>
							<a class="dropdown-item" href="javascript:void(0)" onclick="triggerExport('csv')">
								<i class="fa-solid fa-file-csv mr-2 text-success"></i> Exportar nómina de asistencia (CSV)
							</a>
							<div class="dropdown-divider"></div>
							<a class="dropdown-item text-danger" href="javascript:void(0)" onclick="triggerResetDemoAttendance()">
								<i class="fa-solid fa-rotate-left mr-2"></i> Restaurar escenario de ejemplo (25 colaboradores)
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- Pestañas de los 3 Niveles: Resumen → Análisis → Detalle -->
			<div class="att-level-nav">
				<button type="button" class="att-level-tab is-active" id="tabBtnLevelA" onclick="switchAttLevel('A')">
					<i class="fa-solid fa-gauge-high"></i> Nivel A: RESUMEN (KPIs & Alertas)
				</button>
				<button type="button" class="att-level-tab" id="tabBtnLevelB" onclick="switchAttLevel('B')">
					<i class="fa-solid fa-chart-column"></i> Nivel B: ANÁLISIS (Cobertura & Franjas)
				</button>
				<button type="button" class="att-level-tab" id="tabBtnLevelC" onclick="switchAttLevel('C')">
					<i class="fa-solid fa-table-list"></i> Nivel C: DETALLE (Personal & Correcciones)
					@if(count($overview['pending_adjustments']) > 0)
						<span class="badge badge-warning ml-1">{{ count($overview['pending_adjustments']) }}</span>
					@endif
				</button>
			</div>

			<!-- Filtros Comunes -->
			<div class="daily-att-filters-card">
				<form id="attendanceFilterForm" method="GET" action="{{ $U('/operations/attendance') }}">
					<input type="hidden" name="role_view" value="supervisor">
					<div class="row align-items-end">
						<!-- Fecha -->
						<div class="col-lg-2 col-md-4 col-sm-6 mb-2 mb-lg-0">
							<label class="daily-filter-label" for="filterDate">Fecha</label>
							<input type="date" class="form-control daily-filter-input" id="filterDate" name="date" value="{{ $overview['selected_date'] }}">
						</div>
						<!-- Sucursal -->
						<div class="col-lg-3 col-md-4 col-sm-6 mb-2 mb-lg-0">
							<label class="daily-filter-label" for="filterBranch">Sucursal</label>
							<select class="form-control daily-filter-input" id="filterBranch" name="branch_id">
								@foreach($overview['branches'] as $b)
									<option value="{{ $b['id'] }}" {{ $b['id'] == $overview['branch_id'] ? 'selected' : '' }}>
										{{ $b['name'] }}
									</option>
								@endforeach
							</select>
						</div>
						<!-- Departamento -->
						<div class="col-lg-2 col-md-4 col-sm-6 mb-2 mb-lg-0">
							<label class="daily-filter-label" for="filterDept">Departamento</label>
							<select class="form-control daily-filter-input" id="filterDept" name="department">
								<option value="all" {{ $overview['selected_dept'] == 'all' ? 'selected' : '' }}>Todos los departamentos</option>
								@foreach($overview['departments'] as $d)
									<option value="{{ $d }}" {{ $overview['selected_dept'] == $d ? 'selected' : '' }}>
										{{ $d }}
									</option>
								@endforeach
							</select>
						</div>
						<!-- Estado -->
						<div class="col-lg-2 col-md-4 col-sm-6 mb-2 mb-lg-0">
							<label class="daily-filter-label" for="filterStatus">Estado</label>
							<select class="form-control daily-filter-input" id="filterStatus" name="status">
								<option value="all" {{ $overview['selected_status'] == 'all' ? 'selected' : '' }}>Todos los estados</option>
								<option value="presente" {{ $overview['selected_status'] == 'presente' ? 'selected' : '' }}>Presente</option>
								<option value="tardanza" {{ $overview['selected_status'] == 'tardanza' ? 'selected' : '' }}>Tardanza</option>
								<option value="ausente" {{ $overview['selected_status'] == 'ausente' ? 'selected' : '' }}>Ausente</option>
								<option value="pendiente" {{ $overview['selected_status'] == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
							</select>
						</div>
						<!-- Acciones de Filtro y Badge -->
						<div class="col-lg-3 col-md-8 col-sm-12 d-flex align-items-center justify-content-lg-end justify-content-between gap-2 mt-2 mt-lg-0">
							@if($overview['is_demo'])
								<span class="badge-demo-pill mr-2" title="Escenario de ejemplo con 25 colaboradores (sucursal Doral)">
									<i class="fa-solid fa-circle-info"></i> Datos de ejemplo
								</span>
							@endif
							<button type="submit" class="btn btn-daily-outline mr-1" title="Aplicar filtros">
								<i class="fa-solid fa-filter mr-1"></i> Filtrar
							</button>
							<a href="{{ $U('/operations/attendance?role_view=supervisor') }}" class="btn btn-daily-outline" title="Limpiar filtros">
								<i class="fa-solid fa-arrows-rotate"></i>
							</a>
						</div>
					</div>
				</form>
			</div>

			<!-- ============================================== -->
			<!-- CONTENIDO NIVEL A: RESUMEN                     -->
			<!-- ============================================== -->
			<div id="attContentLevelA">
				<!-- 5 KPI Cards -->
				<div class="daily-kpi-grid">
					<!-- Programados -->
					<div class="daily-kpi-card kpi-sched" onclick="applyStatusFilter('all')">
						<div class="daily-kpi-header">
							<div class="daily-kpi-icon"><i class="fa-solid fa-users"></i></div>
							<span class="daily-kpi-label">Personal Programado</span>
						</div>
						<div class="daily-kpi-val">{{ $overview['kpis']['scheduled_count'] }}</div>
						<div class="daily-kpi-sub">100% dotación programada</div>
					</div>

					<!-- Presentes -->
					<div class="daily-kpi-card kpi-pres {{ $overview['selected_status'] == 'presente' ? 'is-active' : '' }}" onclick="applyStatusFilter('presente')">
						<div class="daily-kpi-header">
							<div class="daily-kpi-icon"><i class="fa-solid fa-check"></i></div>
							<span class="daily-kpi-label">Presentes</span>
						</div>
						<div class="daily-kpi-val">{{ $overview['kpis']['present_count'] }}</div>
						<div class="daily-kpi-sub">{{ $overview['kpis']['attendance_pct'] }}% de asistencia efectiva</div>
					</div>

					<!-- Tardanzas -->
					<div class="daily-kpi-card kpi-late {{ $overview['selected_status'] == 'tardanza' ? 'is-active' : '' }}" onclick="applyStatusFilter('tardanza')">
						<div class="daily-kpi-header">
							<div class="daily-kpi-icon"><i class="fa-solid fa-clock"></i></div>
							<span class="daily-kpi-label">Tardanzas</span>
						</div>
						<div class="daily-kpi-val">{{ $overview['kpis']['late_count'] }}</div>
						<div class="daily-kpi-sub">{{ $overview['kpis']['scheduled_count'] > 0 ? round(($overview['kpis']['late_count'] / $overview['kpis']['scheduled_count']) * 100) : 0 }}% del personal</div>
					</div>

					<!-- Ausencias -->
					<div class="daily-kpi-card kpi-abs {{ $overview['selected_status'] == 'ausente' ? 'is-active' : '' }}" onclick="applyStatusFilter('ausente')">
						<div class="daily-kpi-header">
							<div class="daily-kpi-icon"><i class="fa-solid fa-xmark"></i></div>
							<span class="daily-kpi-label">Ausencias</span>
						</div>
						<div class="daily-kpi-val">{{ $overview['kpis']['absent_count'] }}</div>
						<div class="daily-kpi-sub">Permiso o sin fichaje tras gracia ({{ $overview['kpis']['scheduled_count'] > 0 ? round(($overview['kpis']['absent_count'] / $overview['kpis']['scheduled_count']) * 100) : 0 }}%)</div>
					</div>

					<!-- Pendientes -->
					<div class="daily-kpi-card kpi-pend {{ $overview['selected_status'] == 'pendiente' ? 'is-active' : '' }}" onclick="applyStatusFilter('pendiente')">
						<div class="daily-kpi-header">
							<div class="daily-kpi-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
							<span class="daily-kpi-label">Entrada Pendiente</span>
						</div>
						<div class="daily-kpi-val">{{ $overview['kpis']['pending_count'] }}</div>
						<div class="daily-kpi-sub">Turno próximo o dentro de tolerancia</div>
					</div>
				</div>

				<!-- Top 3 Situaciones Críticas de Asistencia -->
				<div class="supervisor-highlights-grid">
					@foreach($overview['supervisor_highlights'] as $sh)
						<div class="highlight-card highlight-{{ $sh['type'] }}">
							<div>
								<div class="highlight-top">
									<div class="highlight-icon">
										<i class="fa-solid {{ $sh['icon'] }}"></i>
									</div>
									<div>
										<h4 class="highlight-title">{{ $sh['title'] }}</h4>
										<p class="highlight-detail">{{ $sh['detail'] }}</p>
									</div>
								</div>
							</div>
							<a href="javascript:void(0)" class="highlight-action-btn" onclick="goToDetailSubtab('{{ $sh['action_tab'] }}')">
								{{ $sh['action_label'] }} <i class="fa-solid fa-arrow-right"></i>
							</a>
						</div>
					@endforeach
				</div>

				<!-- Gráfico y Alertas del Día -->
				<div class="daily-bottom-grid">
					<!-- Gráfico Stacked -->
					<div class="daily-chart-card">
						<div class="daily-chart-header">
							<h2 class="daily-section-title font-size-15">
								<i class="fa-solid fa-chart-column text-success mr-1"></i> Asistencia por Departamento
							</h2>
							<div class="daily-chart-legend">
								<span><span class="legend-dot" style="background: #10b981;"></span> Presentes</span>
								<span><span class="legend-dot" style="background: #f59e0b;"></span> Tardanzas</span>
								<span><span class="legend-dot" style="background: #ef4444;"></span> Ausencias</span>
								<span><span class="legend-dot" style="background: #94a3b8;"></span> Pendiente</span>
							</div>
						</div>
						<div style="height: 240px; position: relative;">
							<canvas id="attendanceStackedChart"></canvas>
						</div>
					</div>

					<!-- Alertas de Asistencia -->
					<div class="daily-alerts-card">
						<div class="daily-alerts-header">
							<h2 class="daily-section-title font-size-15">
								<i class="fa-solid fa-bell text-danger mr-1"></i> Novedades de Asistencia Hoy
							</h2>
							<a href="{{ $U('/alerts') }}" class="small font-weight-600 text-success">
								Centro de Alertas <i class="fa-solid fa-chevron-right"></i>
							</a>
						</div>
						<div class="daily-alerts-list">
							@foreach($overview['alerts'] as $al)
								<div class="alert-row-item" onclick="onAlertClicked('{{ $al['title'] }}')">
									<div class="alert-left-box">
										<div class="alert-icon-circle {{ $al['severity'] == 'danger' ? 'icon-danger' : ($al['severity'] == 'warning' ? 'icon-warning' : 'icon-info') }}">
											@if($al['severity'] == 'danger')
												<i class="fa-solid fa-exclamation"></i>
											@elseif($al['severity'] == 'warning')
												<i class="fa-solid fa-clock"></i>
											@else
												<i class="fa-solid fa-circle-info"></i>
											@endif
										</div>
										<div class="alert-text-box">
											<span class="alert-person">{{ $al['title'] }}</span>
											<span class="alert-desc">{{ $al['detail'] }}</span>
										</div>
									</div>
									<div class="alert-right-box">
										<span>{{ $al['time'] }}</span>
										<i class="fa-solid fa-chevron-right"></i>
									</div>
								</div>
							@endforeach
						</div>
					</div>
				</div>

				@if($canSupervise && !empty($coverageByDeptHour['departments']))
				<!-- Resumen de Cobertura por Departamento, Franjas (Apertura/Cierre/Nocturno) y Brechas por Hora -->
				<div class="daily-table-card mb-3" id="att-dept-hour-coverage">
					<div class="daily-table-header">
						<h3 class="daily-table-title">
							<i class="fa-solid fa-layer-group text-primary"></i> Cobertura por Departamento y Bandas Operativas
							<span class="badge {{ ($coverageByDeptHour['gap_count'] ?? 0) > 0 ? 'badge-warning text-dark' : 'badge-success' }} ml-2">
								{{ $coverageByDeptHour['gap_count'] ?? 0 }} franjas con déficit
							</span>
						</h3>
						<span class="small text-muted">Jurisdicción: <strong>{{ $coverageByDeptHour['jurisdiction'] ?? 'CO-GEN' }}</strong> · Apertura: {{ $coverageByDeptHour['bands']['apertura'] ?? 0 }} · Cierre: {{ $coverageByDeptHour['bands']['cierre'] ?? 0 }} · Nocturno: {{ $coverageByDeptHour['bands']['nocturno'] ?? 0 }}</span>
					</div>
					<div class="daily-table-responsive">
						<table class="daily-table">
							<thead>
								<tr>
									<th>Departamento</th>
									<th class="text-center">Apertura</th>
									<th class="text-center">Intermedio</th>
									<th class="text-center">Cierre</th>
									<th class="text-center">Nocturno</th>
									<th class="text-center">Horas Efectivas (días-h)</th>
									<th>Brechas Horarias</th>
								</tr>
							</thead>
							<tbody>
								@foreach($coverageByDeptHour['departments'] as $dCov)
								<tr>
									<td class="font-weight-bold text-dark">{{ $dCov['department'] }}</td>
									<td class="text-center">{{ $dCov['bands']['apertura'] ?? 0 }}</td>
									<td class="text-center">{{ $dCov['bands']['intermedio'] ?? 0 }}</td>
									<td class="text-center">{{ $dCov['bands']['cierre'] ?? 0 }}</td>
									<td class="text-center">{{ $dCov['bands']['nocturno'] ?? 0 }}</td>
									<td class="text-center font-weight-bold text-primary">{{ number_format(array_sum($dCov['effective_hourly'] ?? []), 1) }} h</td>
									<td>
										@if(empty($dCov['gaps']))
											<span class="badge badge-success">Cobertura completa</span>
										@else
											<span class="badge badge-danger">{{ count($dCov['gaps']) }} déficit(s)</span>
											<small class="text-muted ml-1">ej. {{ $dCov['gaps'][0]['hour_label'] }} (-{{ $dCov['gaps'][0]['deficit'] }})</small>
										@endif
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
				@endif

				<!-- Acceso claro a Análisis y Detalle -->
				<div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border">
					<div class="d-flex align-items-center gap-2">
						<i class="fa-solid fa-circle-info text-primary"></i>
						<span class="small text-muted">¿Desea explorar la dotación por franja horaria o auditar los fichajes individuales?</span>
					</div>
					<div class="d-flex gap-2">
						<button type="button" class="btn btn-daily-outline btn-sm" onclick="switchAttLevel('B')">
							<i class="fa-solid fa-chart-pie mr-1"></i> Ver Cobertura y Franjas (Nivel B)
						</button>
						<button type="button" class="btn btn-daily-green btn-sm" onclick="switchAttLevel('C')">
							<i class="fa-solid fa-list-check mr-1"></i> Ver Registros y Correcciones (Nivel C)
						</button>
					</div>
				</div>
			</div>

			<!-- ============================================== -->
			<!-- CONTENIDO NIVEL B: ANÁLISIS                    -->
			<!-- ============================================== -->
			<div id="attContentLevelB" style="display: none;">
				<!-- Cobertura por Departamento (Tarjetas Visuales) -->
				<div class="mb-4">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<h3 class="daily-section-title font-size-15">
							<i class="fa-solid fa-store text-success mr-1"></i> Cobertura Operativa por Departamento
						</h3>
						<span class="text-muted small">Dotación física presente frente a dotación programada</span>
					</div>
					<div class="daily-dept-grid">
						@foreach($overview['dept_cards'] as $card)
							<div class="daily-dept-card {{ $overview['selected_dept'] == $card['raw_name'] ? 'is-active' : '' }}" onclick="filterByDepartment('{{ $card['raw_name'] }}')">
								<div class="daily-dept-img-wrap">
									<img src="{{ $U($card['image']) }}" alt="{{ $card['short_name'] }}" class="daily-dept-img" onerror="this.src='{{ $U('/img/control_center/depto_lacteos.jpg') }}'">
								</div>
								<div class="daily-dept-body">
									<h4 class="daily-dept-name">{{ $card['short_name'] }}</h4>
									<div class="daily-dept-nums">
										<span class="daily-dept-count">{{ $card['present'] }}/{{ $card['scheduled'] }} pers.</span>
										<span class="daily-dept-pct {{ $card['coverage_pct'] >= 90 ? 'pct-green' : ($card['coverage_pct'] >= 75 ? 'pct-green' : 'pct-amber') }}">
											{{ $card['coverage_pct'] }}%
										</span>
									</div>
									<div class="daily-progress">
										<div class="daily-progress-bar {{ $card['coverage_pct'] >= 75 ? 'bar-green' : 'bar-amber' }}" style="width: {{ $card['coverage_pct'] }}%;"></div>
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>

				<!-- Cobertura por Franja Horaria (8 slots de 2 horas) -->
				<div class="daily-table-card mb-4">
					<div class="daily-table-header">
						<h3 class="daily-table-title font-size-15">
							<i class="fa-solid fa-clock text-primary mr-1"></i> Cobertura y Dotación por Franja Horaria
						</h3>
						<span class="text-muted small">Identificación de franjas pico y valles de personal</span>
					</div>
					<div class="p-3">
						<div class="hourly-slots-grid">
							@foreach($overview['hourly_coverage'] as $slot)
								<div class="hourly-slot-card">
									<div class="slot-header">
										<span class="slot-name">{{ $slot['slot'] }}</span>
										<span class="slot-badge slot-{{ $slot['status'] }}">{{ ucfirst($slot['status']) }}</span>
									</div>
									<div class="slot-numbers">
										<span class="slot-count">{{ $slot['present'] }} / {{ $slot['scheduled'] }}</span>
										<span class="slot-pct {{ $slot['coverage_pct'] >= 90 ? 'text-success' : ($slot['coverage_pct'] >= 70 ? 'text-warning' : 'text-danger') }}">
											{{ $slot['coverage_pct'] }}%
										</span>
									</div>
									<div class="daily-progress">
										<div class="daily-progress-bar {{ $slot['coverage_pct'] >= 90 ? 'bar-green' : 'bar-amber' }}" style="width: {{ $slot['coverage_pct'] }}%;"></div>
									</div>
								</div>
							@endforeach
						</div>
					</div>
				</div>
			</div>

			<!-- ============================================== -->
			<!-- CONTENIDO NIVEL C: DETALLE                     -->
			<!-- ============================================== -->
			<div id="attContentLevelC" style="display: none;">
				<!-- Sub-pestañas de Detalle -->
				<div class="subnav-tabs">
					<button type="button" class="subnav-tab-btn is-active" id="subtabBtnRecords" onclick="switchDetailSubtab('records')">
						<i class="fa-solid fa-users mr-1"></i> Registros de Asistencia ({{ count($overview['records']) }})
					</button>
					<button type="button" class="subnav-tab-btn" id="subtabBtnAdjustments" onclick="switchDetailSubtab('adjustments')">
						<i class="fa-solid fa-clock-rotate-left mr-1"></i> Correcciones por Revisar 
						@if(count($overview['pending_adjustments']) > 0)
							<span class="badge badge-warning ml-1">{{ count($overview['pending_adjustments']) }}</span>
						@endif
					</button>
				</div>

				<!-- Sub-panel 1: Registros de Asistencia -->
				<div id="subpanelRecords">
					<div class="daily-table-card">
						<div class="daily-table-header">
							<h3 class="daily-table-title font-size-15">
								<i class="fa-solid fa-list-check text-success mr-1"></i> Fichajes del Personal del Día
							</h3>
							<div class="d-flex align-items-center gap-2">
								<div class="input-group input-group-sm mr-2" style="width: 220px; max-width: 100%;">
									<input type="text" id="tableSearchInput" class="form-control" placeholder="Buscar colaborador..." value="{{ $overview['search'] }}" onkeyup="onSearchInput(event)">
									<div class="input-group-append">
										<button class="btn btn-outline-secondary" type="button" onclick="executeSearch()">
											<i class="fa-solid fa-magnifying-glass"></i>
										</button>
									</div>
								</div>
								<button class="btn btn-daily-outline btn-sm" type="button" onclick="triggerExport('csv')">
									<i class="fa-solid fa-download mr-1"></i> Exportar CSV
								</button>
							</div>
						</div>

						<div class="daily-table-responsive">
							<table class="daily-table" id="attendanceTable">
								<thead>
									<tr>
										<th style="width: 40px;"><input type="checkbox" id="checkAllRows" onchange="toggleSelectAllRows(this)"></th>
										<th>COLABORADOR</th>
										<th>DEPARTAMENTO</th>
										<th>TURNO PROGRAMADO</th>
										<th>ENTRADA</th>
										<th>SALIDA</th>
										<th>ESTADO</th>
										<th>OBSERVACIONES</th>
										<th style="width: 50px; text-align: center;">ACCIÓN</th>
									</tr>
								</thead>
								<tbody>
									@forelse($overview['records'] as $row)
										<tr id="row-emp-{{ $row['employee_id'] }}">
											<td><input type="checkbox" class="row-checkbox" value="{{ $row['employee_id'] }}"></td>
											<td>
												<div class="emp-cell">
													@if(!empty($row['avatar_url']))
														<img src="{{ $row['avatar_url'] }}" alt="{{ $row['name'] }}" class="emp-avatar" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
														<div class="emp-avatar-initials" style="display: none; background-color: {{ $row['avatar_color'] }};">{{ $row['initials'] }}</div>
													@else
														<div class="emp-avatar-initials" style="background-color: {{ $row['avatar_color'] }};">{{ $row['initials'] }}</div>
													@endif
													<div class="emp-name-box">
														<span class="emp-name">{{ $row['name'] }}</span>
														<span class="emp-code">{{ str_replace('DEMO-', 'EJ-', $row['payroll_code']) }} · {{ $row['position'] }}</span>
													</div>
												</div>
											</td>
											<td><span class="text-secondary font-weight-500">{{ $row['department'] }}</span></td>
											<td><strong class="text-dark">{{ $row['shift_text'] }}</strong></td>
											<td>
												@if($row['clock_in'] !== '—')
													<span class="{{ $row['status'] == 'tardanza' ? 'time-red' : 'time-green' }}">{{ $row['clock_in'] }}</span>
												@else
													<span class="time-muted">—</span>
												@endif
											</td>
											<td>
												@if($row['clock_out'] !== '—')
													<span class="text-dark font-weight-500">{{ $row['clock_out'] }}</span>
												@else
													<span class="time-muted">—</span>
												@endif
											</td>
											<td>
												<span class="badge-status badge-status-{{ $row['status'] }}">● {{ ucfirst($row['status']) }}</span>
											</td>
											<td><span class="text-muted small">{{ $row['observations'] }}</span></td>
											<td style="text-align: center;">
												<div class="dropdown">
													<button class="btn btn-sm btn-link text-muted p-0" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
														<i class="fa-solid fa-ellipsis-vertical"></i>
													</button>
													<div class="dropdown-menu dropdown-menu-right shadow-sm">
														@if($row['can_clock_out'])
															<a class="dropdown-item" href="javascript:void(0)" onclick="quickClockOut({{ $row['employee_id'] }}, '{{ $row['name'] }}')">
																<i class="fa-solid fa-arrow-right-from-bracket mr-2 text-warning"></i> Registrar salida
															</a>
														@endif
														<a class="dropdown-item" href="javascript:void(0)" onclick="openCorrectionModal({{ $row['employee_id'] }}, '{{ $row['name'] }}', '{{ $row['clock_in'] }}', '{{ $row['clock_out'] }}', {{ $row['attendance_id'] ?: 'null' }})">
															<i class="fa-solid fa-clock-rotate-left mr-2 text-primary"></i> Solicitar corrección
														</a>
														<a class="dropdown-item" href="javascript:void(0)" onclick="openAbsenceModal({{ $row['employee_id'] }}, '{{ $row['name'] }}')">
															<i class="fa-solid fa-file-medical mr-2 text-danger"></i> Justificar inasistencia
														</a>
														<div class="dropdown-divider"></div>
														<a class="dropdown-item" href="{{ $U('/operations/scheduling?branch_id=' . $overview['branch_id']) }}">
															<i class="fa-solid fa-calendar-days mr-2 text-secondary"></i> Ver turno en Horarios
														</a>
													</div>
												</div>
											</td>
										</tr>
									@empty
										<tr>
											<td colspan="9" class="text-center py-4 text-muted">
												No se encontraron registros de asistencia para los filtros seleccionados.
											</td>
										</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Sub-panel 2: Correcciones por Revisar (Bandeja de Aprobación) -->
				<div id="subpanelAdjustments" style="display: none;">
					<div class="daily-table-card">
						<div class="daily-table-header">
							<h3 class="daily-table-title font-size-15">
								<i class="fa-solid fa-clock-rotate-left text-warning mr-1"></i> Solicitudes de Corrección Pendientes de Decisión
							</h3>
							<span class="text-muted small">Auditoría estricta: los fichajes se actualizan únicamente tras la aprobación del supervisor</span>
						</div>
						<div class="daily-table-responsive">
							<table class="daily-table">
								<thead>
									<tr>
										<th>ID</th>
										<th>COLABORADOR</th>
										<th>HORARIO ORIGINAL</th>
										<th>HORARIO PROPUESTO</th>
										<th>MOTIVO DEL AJUSTE</th>
										<th>ESTADO</th>
										<th style="width: 180px; text-align: center;">ACCIONES</th>
									</tr>
								</thead>
								<tbody>
									@forelse($overview['pending_adjustments'] as $adj)
										<tr id="adj-row-{{ $adj['id'] }}">
											<td><strong>#ADJ-{{ $adj['id'] }}</strong></td>
											<td>
												<strong class="text-dark">{{ $adj['employee_name'] }}</strong>
												<span class="d-block text-muted small">{{ str_replace('DEMO-', 'EJ-', $adj['payroll_code']) }} · {{ $adj['department'] }}</span>
											</td>
											<td>
												<span class="text-muted">{{ $adj['original_clock_in'] ? date('H:i', strtotime($adj['original_clock_in'])) : 'Sin entrada' }}</span>
												@if($adj['original_clock_out'])
													– <span class="text-muted">{{ date('H:i', strtotime($adj['original_clock_out'])) }}</span>
												@endif
											</td>
											<td>
												<span class="font-weight-700 text-success">{{ $adj['new_clock_in'] ? date('H:i', strtotime($adj['new_clock_in'])) : '—' }}</span>
												@if($adj['new_clock_out'])
													– <span class="font-weight-700 text-dark">{{ date('H:i', strtotime($adj['new_clock_out'])) }}</span>
												@endif
											</td>
											<td>
												<span class="text-dark small">{{ $adj['reason'] }}</span>
											</td>
											<td>
												<span class="badge badge-adj-requested px-2 py-1">Pendiente de revisión</span>
											</td>
											<td style="text-align: center;">
												<div class="d-flex justify-content-center gap-1">
													<button type="button" class="btn btn-success btn-sm font-size-12" onclick="decideAdjustment({{ $adj['id'] }}, true)">
														<i class="fa-solid fa-check mr-1"></i> Aprobar
													</button>
													<button type="button" class="btn btn-outline-danger btn-sm font-size-12" onclick="decideAdjustment({{ $adj['id'] }}, false)">
														<i class="fa-solid fa-xmark mr-1"></i> Rechazar
													</button>
												</div>
											</td>
										</tr>
									@empty
										<tr>
											<td colspan="7" class="text-center py-4 text-muted">
												<i class="fa-solid fa-circle-check fa-2x text-success mb-2 d-block"></i>
												No hay correcciones de fichaje pendientes de aprobación.
											</td>
										</tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>

			</div>

		</div>
	@endif

</div>

<!-- MODAL: REGISTRAR ASISTENCIA MANUAL -->
<div class="modal fade" id="modalRecordAttendance" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-clipboard-user text-success mr-2"></i> Registrar Asistencia Manual
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="recordAttendanceForm" onsubmit="submitRecordAttendance(event)">
				<div class="modal-body p-4">
					<div class="form-group">
						<label class="font-weight-600 text-dark small">EMPLEADO</label>
						<select class="form-control" name="employee_id" required>
							<option value="">Seleccione un empleado...</option>
							@foreach($employees as $emp)
								<option value="{{ $emp['id'] }}">
									{{ $emp['name'] }} ({{ str_replace('DEMO-', 'EJ-', $emp['payroll_code']) }}) — {{ $emp['department'] }}
								</option>
							@endforeach
						</select>
					</div>

					<div class="row">
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-600 text-dark small">FECHA</label>
								<input type="date" class="form-control" name="date" value="{{ $overview['selected_date'] }}" required>
							</div>
						</div>
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-600 text-dark small">HORA</label>
								<input type="time" class="form-control" name="time" value="{{ date('H:i') }}" required>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label class="font-weight-600 text-dark small">TIPO DE REGISTRO</label>
						<select class="form-control" name="type" required onchange="onRecordTypeChanged(this.value)">
							<option value="clock_in">Entrada</option>
							<option value="clock_out">Salida</option>
							<option value="absence">Novedad / Ausencia</option>
						</select>
					</div>

					<div class="form-group d-none" id="absenceTypeGroup">
						<label class="font-weight-600 text-dark small">TIPO DE NOVEDAD</label>
						<select class="form-control" name="absence_type">
							<option value="permission">Permiso laboral</option>
							<option value="sick">Incapacidad médica EPS</option>
							<option value="vacation">Vacaciones</option>
							<option value="unjustified">Ausencia injustificada</option>
						</select>
					</div>

					<div class="form-group">
						<label class="font-weight-600 text-dark small">MOTIVO / OBSERVACIONES</label>
						<textarea class="form-control" name="notes" rows="2" placeholder="Describa el motivo o justificación..." required></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light border-top">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-daily-green btn-sm">
						<i class="fa-solid fa-check mr-1"></i> Guardar registro
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- MODAL: SOLICITAR CORRECCIÓN DE FICHAJE -->
<div class="modal fade" id="modalAdjustment" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-clock-rotate-left text-warning mr-2"></i> Solicitar Corrección de Fichaje
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="adjustmentForm" onsubmit="submitAdjustment(event)">
				<input type="hidden" name="employee_id" id="adjEmpId">
				<input type="hidden" name="attendance_id" id="adjAttId">
				<div class="modal-body p-4">
					<p class="text-muted small mb-3">
						Las modificaciones de marcas de tiempo requieren aprobación de supervisor según las políticas de control interno de DAILY.
					</p>
					<div class="form-group">
						<label class="font-weight-600 text-dark small">COLABORADOR</label>
						<input type="text" class="form-control" id="adjEmpName" readonly>
					</div>

					<div class="row">
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-600 text-dark small">HORA ENTRADA CORREGIDA</label>
								<input type="time" class="form-control" name="new_clock_in" id="adjClockIn" required>
							</div>
						</div>
						<div class="col-6">
							<div class="form-group">
								<label class="font-weight-600 text-dark small">HORA SALIDA CORREGIDA</label>
								<input type="time" class="form-control" name="new_clock_out" id="adjClockOut">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label class="font-weight-600 text-dark small">MOTIVO DEL AJUSTE</label>
						<textarea class="form-control" name="reason" rows="3" placeholder="Explique la causa de la discrepancia (olvido de marcación, fallo de sensor, etc.)..." required></textarea>
					</div>
				</div>
				<div class="modal-footer bg-light border-top">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-warning btn-sm font-weight-600">
						<i class="fa-solid fa-paper-plane mr-1"></i> Enviar a Aprobación
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

@endsection

@push('pageScripts')
<script>
// Inicialización del Reloj Digital y Chart
var chartData = @json($overview['chart_data']);

$(document).ready(function() {
	if (document.getElementById('attendanceStackedChart')) {
		renderStackedAttendanceChart();
	}
	startLiveTerminalClock();
});

// Reloj digital en tiempo real para el portal del empleado
function startLiveTerminalClock() {
	function tick() {
		var now = new Date();
		var timeStr = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
		var dateStr = now.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
		
		var elClock = document.getElementById('terminalLiveClock');
		var elDate = document.getElementById('terminalLiveDate');
		if (elClock) elClock.innerText = timeStr;
		if (elDate) elDate.innerText = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
	}
	tick();
	setInterval(tick, 1000);
}

// Conmutador de Niveles para Supervisor (A: Resumen, B: Análisis, C: Detalle)
function switchAttLevel(level) {
	$('#attContentLevelA, #attContentLevelB, #attContentLevelC').hide();
	$('.att-level-tab').removeClass('is-active');

	if (level === 'A') {
		$('#attContentLevelA').show();
		$('#tabBtnLevelA').addClass('is-active');
	} else if (level === 'B') {
		$('#attContentLevelB').show();
		$('#tabBtnLevelB').addClass('is-active');
	} else if (level === 'C') {
		$('#attContentLevelC').show();
		$('#tabBtnLevelC').addClass('is-active');
	}
}

// Sub-pestañas de Detalle (Registros vs Correcciones)
function switchDetailSubtab(sub) {
	$('.subnav-tab-btn').removeClass('is-active');
	if (sub === 'records') {
		$('#subpanelRecords').show();
		$('#subpanelAdjustments').hide();
		$('#subtabBtnRecords').addClass('is-active');
	} else {
		$('#subpanelRecords').hide();
		$('#subpanelAdjustments').show();
		$('#subtabBtnAdjustments').addClass('is-active');
	}
}

function goToDetailSubtab(sub) {
	switchAttLevel('C');
	switchDetailSubtab(sub);
}

// Gráfico Stacked
function renderStackedAttendanceChart() {
	var ctx = document.getElementById('attendanceStackedChart');
	if (!ctx) return;

	new Chart(ctx, {
		type: 'bar',
		data: {
			labels: chartData.labels,
			datasets: [
				{ label: 'Presentes', data: chartData.present, backgroundColor: '#10b981', borderRadius: 4 },
				{ label: 'Tardanzas', data: chartData.late, backgroundColor: '#f59e0b', borderRadius: 4 },
				{ label: 'Ausencias', data: chartData.absent, backgroundColor: '#ef4444', borderRadius: 4 },
				{ label: 'Pendiente', data: chartData.pending, backgroundColor: '#94a3b8', borderRadius: 4 }
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				x: { stacked: true, grid: { display: false } },
				y: { stacked: true, beginAtZero: true, max: 6, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } }
			},
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						afterBody: function(items) {
							var total = 0;
							items.forEach(function(i) { total += i.raw; });
							return 'Dotación programada: ' + total;
						}
					}
				}
			}
		}
	});
}

// Acciones de Marcación del Colaborador
function doClockIn(empId) {
	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/clock-in',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ employee_id: empId }),
		success: function(resp) {
			toastr.success('Entrada registrada correctamente');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al registrar entrada');
		}
	});
}

function doStartBreak(empId) {
	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/break-start',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ employee_id: empId }),
		success: function(resp) {
			toastr.success(resp.message || 'Descanso iniciado');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al iniciar descanso');
		}
	});
}

function doEndBreak(empId) {
	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/break-end',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ employee_id: empId }),
		success: function(resp) {
			toastr.success(resp.message || 'Descanso finalizado');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al finalizar descanso');
		}
	});
}

function doClockOut(empId) {
	if (!confirm('¿Confirmar marcación de salida de fin de jornada?')) return;
	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/clock-out',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ employee_id: empId }),
		success: function(resp) {
			toastr.success('Salida registrada con éxito');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al registrar salida');
		}
	});
}

function switchPortalEmployee(empId) {
	var url = new URL(window.location.href);
	url.searchParams.set('role_view', 'employee');
	url.searchParams.set('employee_id', empId);
	window.location.href = url.toString();
}

function openEmployeeCorrectionModal() {
	var empId = "{{ $selectedEmployeeId }}";
	var empName = "{{ $employeePortal['employee']['name'] }}";
	openCorrectionModal(empId, empName, '08:00', '17:00', null);
}

// Filtros y búsquedas
// Conserva la búsqueda al enviar el formulario de filtros (el buscador está fuera del <form>)
$('#attendanceFilterForm').on('submit', function() {
	var q = $('#tableSearchInput').val();
	$(this).find('input[name=search]').remove();
	if (q) $('<input type="hidden" name="search">').val(q).appendTo(this);
});

// "Acciones rápidas → Solicitar corrección": la corrección es por fichaje, se elige en la tabla
function openAdjustmentModal() {
	var table = document.getElementById('tableSearchInput');
	if (table) table.scrollIntoView({ behavior: 'smooth', block: 'center' });
	toastr.info('Seleccione el empleado en la tabla y use la acción "Corregir" de su fila.');
}

function applyStatusFilter(status) {
	$('#filterStatus').val(status);
	$('#attendanceFilterForm').submit();
}

function filterByDepartment(dept) {
	$('#filterDept').val(dept);
	$('#attendanceFilterForm').submit();
}

function onSearchInput(e) {
	if (e.key === 'Enter') executeSearch();
}

function executeSearch() {
	var q = $('#tableSearchInput').val();
	var url = new URL(window.location.href);
	if (q) url.searchParams.set('search', q);
	else url.searchParams.delete('search');
	window.location.href = url.toString();
}

function toggleSelectAllRows(master) {
	$('.row-checkbox').prop('checked', $(master).is(':checked'));
}

function onRecordTypeChanged(val) {
	if (val === 'absence') $('#absenceTypeGroup').removeClass('d-none');
	else $('#absenceTypeGroup').addClass('d-none');
}

function submitRecordAttendance(e) {
	e.preventDefault();
	var form = $(e.target);
	var data = form.serializeArray().reduce(function(obj, item) {
		obj[item.name] = item.value;
		return obj;
	}, {});

	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/record',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(data),
		success: function(resp) {
			$('#modalRecordAttendance').modal('hide');
			toastr.success(resp.message || 'Asistencia registrada');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al registrar asistencia');
		}
	});
}

function openCorrectionModal(empId, empName, inTime, outTime, attId) {
	$('#adjEmpId').val(empId);
	$('#adjAttId').val(attId || '');
	$('#adjEmpName').val(empName);
	$('#adjClockIn').val(inTime && inTime !== '—' ? inTime : '08:00');
	$('#adjClockOut').val(outTime && outTime !== '—' ? outTime : '17:00');
	$('#modalAdjustment').modal('show');
}

function submitAdjustment(e) {
	e.preventDefault();
	var form = $(e.target);
	var date = $('#filterDate').val() || '2026-11-04';
	var inVal = $('#adjClockIn').val();
	var outVal = $('#adjClockOut').val();

	var payload = {
		employee_id: $('#adjEmpId').val(),
		attendance_id: $('#adjAttId').val() || null,
		new_clock_in: inVal ? (date + ' ' + inVal + ':00') : null,
		new_clock_out: outVal ? (date + ' ' + outVal + ':00') : null,
		reason: form.find('[name="reason"]').val()
	};

	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/adjustment',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify(payload),
		success: function(resp) {
			$('#modalAdjustment').modal('hide');
			toastr.success('Solicitud enviada a supervisión');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al enviar corrección');
		}
	});
}

function decideAdjustment(adjId, approve) {
	var notes = null;
	if (!approve) {
		notes = prompt('Indique el motivo del rechazo de la corrección:');
		if (!notes || notes.trim() === '') {
			toastr.warning('Debe especificar un motivo para rechazar.');
			return;
		}
	} else {
		if (!confirm('¿Aprobar corrección de fichaje? Esto actualizará la marca de tiempo oficial del colaborador.')) {
			return;
		}
	}

	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/adjustments/' + adjId + '/decide',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ approve: approve, notes: notes }),
		success: function(resp) {
			toastr.success(approve ? 'Corrección aprobada exitosamente' : 'Corrección rechazada');
			$('#adj-row-' + adjId).fadeOut(400, function() { $(this).remove(); });
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al procesar decisión');
		}
	});
}

function openAbsenceModal(empId, empName) {
	$('#modalRecordAttendance').modal('show');
	if (empId) $('#modalRecordAttendance select[name="employee_id"]').val(empId);
	$('#modalRecordAttendance select[name="type"]').val('absence').trigger('change');
}

function quickClockOut(empId, empName) {
	if (!confirm('¿Registrar la salida de ' + empName + ' en este momento?')) return;
	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/clock-out',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ employee_id: empId }),
		success: function(resp) {
			toastr.success('Salida registrada con éxito');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error((err.responseJSON && err.responseJSON.error_message) || 'Error al registrar salida');
		}
	});
}

function openMassCheckoutModal() {
	if (!confirm('¿Registrar salida masiva para todos los colaboradores con turno finalizado?')) return;
	var branchId = $('#filterBranch').val() || 9;
	var date = $('#filterDate').val() || '2026-11-04';

	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/quick-checkout-all',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ branch_id: branchId, date: date }),
		success: function(resp) {
			toastr.success(resp.message || 'Salida masiva completada');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error('Error al ejecutar salida masiva');
		}
	});
}

function triggerResetDemoAttendance() {
	if (!confirm('¿Restaurar el escenario de asistencia de ejemplo (25 colaboradores, 20 presentes, 2 tardanzas, 2 ausencias, 1 pendiente)?')) return;
	var branchId = $('#filterBranch').val() || 9;
	var date = $('#filterDate').val() || '2026-11-04';

	$.ajax({
		url: Grocy.BaseUrl + '/api/attendance/demo/reset',
		method: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({ branch_id: branchId, date: date }),
		success: function(resp) {
			toastr.success(resp.message || 'Escenario restaurado con éxito');
			setTimeout(function() { window.location.reload(); }, 600);
		},
		error: function(err) {
			toastr.error('No se pudo restaurar el escenario de ejemplo');
		}
	});
}

function triggerExport(format) {
	var table = document.getElementById('attendanceTable');
	if (!table) return;

	var csv = [];
	for (var i = 0; i < table.rows.length; i++) {
		var row = [], cols = table.rows[i].querySelectorAll('td, th');
		for (var j = 1; j < cols.length - 1; j++) {
			var text = cols[j].innerText.replace(/"/g, '""').trim();
			row.push('"' + text + '"');
		}
		csv.push(row.join(','));
	}

	var csvFile = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
	var downloadLink = document.createElement('a');
	downloadLink.download = 'DAILY_Asistencia_' + ($('#filterDate').val() || '2026-11-04') + '.csv';
	downloadLink.href = window.URL.createObjectURL(csvFile);
	downloadLink.style.display = 'none';
	document.body.appendChild(downloadLink);
	downloadLink.click();
	document.body.removeChild(downloadLink);
}

function onAlertClicked(title) {
	switchAttLevel('C');
	switchDetailSubtab('records');
	toastr.info('Resaltando colaborador en la lista');
	var rows = $('#attendanceTable tbody tr');
	rows.removeClass('table-warning');
	rows.each(function() {
		if ($(this).text().indexOf(title) !== -1) {
			$(this).addClass('table-warning')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	});
}
</script>
@endpush
