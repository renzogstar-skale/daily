@extends('layout.default')

@section('title', 'Recursos Humanos (HCM) — Gestión Integral de Personas')

@push('pageStyles')
<link href="{{ $U('/css/daily_hr.css?v=', true) }}{{ daily_asset_version('/css/daily_hr.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="hr-container">

	<!-- 1. HEADER & SUCURSAL -->
	<div class="hr-header-box">
		<div class="hr-title-wrap">
			<h1>
				<i class="fa-solid fa-users-gear text-primary"></i>
				Recursos Humanos & Gestión Laboral (HCM)
			</h1>
			<p>
				Expedientes, contratación, programación, asistencia en vivo, pre-liquidación de nómina, productividad y bienestar.
			</p>
		</div>

		<div class="d-flex align-items-center flex-wrap gap-2">
			<span class="hr-badge-live">
				<span class="hr-badge-dot"></span> Motor HCM Activo · {{ $payroll['jurisdiction'] ?? 'CO-GEN' }}
			</span>

			<form method="GET" action="{{ $U('/hr') }}" class="d-inline-flex align-items-center gap-1">
				<input type="hidden" name="tab" value="{{ $activeTab }}">
				<select name="branch_id" class="form-control form-control-sm font-weight-bold" onchange="this.form.submit()" style="max-width: 200px;">
					@foreach($branches as $b)
						@php $bid = is_object($b) ? $b->id : $b['id']; $bname = is_object($b) ? $b->name : $b['name']; @endphp
						<option value="{{ $bid }}" @if($bid == $selectedBranchId) selected @endif>
							{{ $bname }}
						</option>
					@endforeach
				</select>
			</form>

			<a href="{{ $U('/hr/payroll/export') }}?branch_id={{ $selectedBranchId }}" class="btn btn-sm btn-outline-success font-weight-bold" title="Descargar nómina en Excel/CSV">
				<i class="fa-solid fa-file-excel mr-1"></i> Exportar Nómina CSV
			</a>
		</div>
	</div>

	<!-- 2. CICLO HCM (8 FASES INTERACTIVAS) -->
	<div class="hr-cycle-card">
		<div class="hr-cycle-title">
			<span><i class="fa-solid fa-arrows-spin text-primary mr-1"></i> Ciclo de Vida del Colaborador (DAILY HCM)</span>
			<span class="text-muted small">8 Etapas Sincronizadas con Operaciones</span>
		</div>
		<div class="hr-cycle-stepper">
			@foreach($dashboard['cycle_steps'] as $cs)
				<div class="hr-cycle-step @if($loop->first) active @endif" onclick="document.querySelector('[data-tab=\'{{ $loop->index == 0 ? 'resumen' : ($loop->index == 1 ? 'contratacion' : ($loop->index == 2 ? 'horarios' : ($loop->index == 3 ? 'asistencia' : ($loop->index == 5 ? 'nomina' : ($loop->index == 6 ? 'productividad' : 'capacitacion'))))) }}\'])?.click()">
					<div class="hr-step-num">{{ $cs['num'] }}</div>
					<div class="hr-step-name">{{ $cs['name'] }}</div>
					<div class="hr-step-desc">{{ $cs['desc'] }}</div>
				</div>
			@endforeach
		</div>
	</div>

	<!-- 3. INDICADORES PRINCIPALES (8 KPIS HCM) -->
	<div class="hr-kpi-grid">
		<!-- KPI 1: Empleados Activos -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['active_employees']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-blue"><i class="fa-solid fa-users"></i></div>
			</div>
			<div class="hr-kpi-value">{{ $dashboard['kpis']['active_employees']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['active_employees']['subtext'] }}</span>
				<span class="hr-kpi-trend"><i class="fa-solid fa-arrow-up"></i> {{ $dashboard['kpis']['active_employees']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 2: Cobertura de Turnos -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['coverage']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-green"><i class="fa-solid fa-calendar-check"></i></div>
			</div>
			<div class="hr-kpi-value text-success">{{ $dashboard['kpis']['coverage']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['coverage']['subtext'] }}</span>
				<span class="hr-kpi-trend"><i class="fa-solid fa-circle-check"></i> {{ $dashboard['kpis']['coverage']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 3: Tasa de Ausentismo -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['absenteeism']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-amber"><i class="fa-solid fa-user-xmark"></i></div>
			</div>
			<div class="hr-kpi-value text-warning">{{ $dashboard['kpis']['absenteeism']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['absenteeism']['subtext'] }}</span>
				<span class="hr-kpi-trend text-success"><i class="fa-solid fa-arrow-down"></i> {{ $dashboard['kpis']['absenteeism']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 4: Horas Extras -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['overtime']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-purple"><i class="fa-solid fa-business-time"></i></div>
			</div>
			<div class="hr-kpi-value">{{ $dashboard['kpis']['overtime']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['overtime']['subtext'] }}</span>
				<span class="badge badge-light font-weight-bold">{{ $dashboard['kpis']['overtime']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 5: SPLH (Ventas por hora trabajada) -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['splh']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-teal"><i class="fa-solid fa-chart-line"></i></div>
			</div>
			<div class="hr-kpi-value text-primary">{{ $dashboard['kpis']['splh']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['splh']['subtext'] }}</span>
				<span class="hr-kpi-trend"><i class="fa-solid fa-arrow-up"></i> {{ $dashboard['kpis']['splh']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 6: Costo Laboral sobre Ventas -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['labor_cost']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-blue"><i class="fa-solid fa-hand-holding-dollar"></i></div>
			</div>
			<div class="hr-kpi-value text-info">{{ $dashboard['kpis']['labor_cost']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['labor_cost']['subtext'] }}</span>
				<span class="hr-kpi-trend"><i class="fa-solid fa-shield-check"></i> {{ $dashboard['kpis']['labor_cost']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 7: Rotación de Personal -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['turnover']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-rose"><i class="fa-solid fa-arrows-rotate"></i></div>
			</div>
			<div class="hr-kpi-value">{{ $dashboard['kpis']['turnover']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['turnover']['subtext'] }}</span>
				<span class="text-success font-weight-bold">{{ $dashboard['kpis']['turnover']['trend'] }}</span>
			</div>
		</div>

		<!-- KPI 8: Capacitación Completada -->
		<div class="hr-kpi-card">
			<div class="hr-kpi-head">
				<span class="hr-kpi-label">{{ $dashboard['kpis']['training']['label'] }}</span>
				<div class="hr-kpi-icon hr-kpi-icon-green"><i class="fa-solid fa-graduation-cap"></i></div>
			</div>
			<div class="hr-kpi-value text-success">{{ $dashboard['kpis']['training']['value'] }}</div>
			<div class="hr-kpi-footer">
				<span>{{ $dashboard['kpis']['training']['subtext'] }}</span>
				<span class="hr-kpi-trend"><i class="fa-solid fa-check"></i> {{ $dashboard['kpis']['training']['trend'] }}</span>
			</div>
		</div>
	</div>

	<!-- 4. PESTAÑAS DE NAVEGACIÓN HCM -->
	<div class="hr-nav-tabs">
		<button class="hr-tab-btn @if($activeTab == 'resumen') active @endif" data-tab="resumen">
			<i class="fa-solid fa-chart-pie"></i> Resumen
		</button>
		<button class="hr-tab-btn @if($activeTab == 'colaboradores') active @endif" data-tab="colaboradores">
			<i class="fa-solid fa-id-card"></i> Colaboradores <span class="hr-tab-badge">{{ count($collaborators) }}</span>
		</button>
		<button class="hr-tab-btn @if($activeTab == 'contratacion') active @endif" data-tab="contratacion">
			<i class="fa-solid fa-user-plus"></i> Contratación & Onboarding <span class="hr-tab-badge">{{ count($hiring['candidates']) }}</span>
		</button>
		<button class="hr-tab-btn @if($activeTab == 'horarios') active @endif" data-tab="horarios">
			<i class="fa-solid fa-calendar-days"></i> Horarios & Cobertura
		</button>
		<button class="hr-tab-btn @if($activeTab == 'asistencia') active @endif" data-tab="asistencia">
			<i class="fa-solid fa-fingerprint"></i> Asistencia en Vivo <span class="hr-tab-badge">{{ count($attendance['records']) }}</span>
		</button>
		<button class="hr-tab-btn @if($activeTab == 'nomina') active @endif" data-tab="nomina">
			<i class="fa-solid fa-money-check-dollar"></i> Pre-Nómina <span class="hr-tab-badge">${{ number_format($payroll['totals']['grand_total']) }}</span>
		</button>
		<button class="hr-tab-btn @if($activeTab == 'productividad') active @endif" data-tab="productividad">
			<i class="fa-solid fa-gauge-high"></i> Productividad & SPLH
		</button>
		<button class="hr-tab-btn @if($activeTab == 'capacitacion') active @endif" data-tab="capacitacion">
			<i class="fa-solid fa-certificate"></i> Capacitación & BPM
		</button>
		<button class="hr-tab-btn @if($activeTab == 'comunicaciones') active @endif" data-tab="comunicaciones">
			<i class="fa-solid fa-comments"></i> Comunicaciones & Solicitudes @if($dashboard['pending_requests_count'] > 0)<span class="badge badge-danger">{{ $dashboard['pending_requests_count'] }}</span>@endif
		</button>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 1: RESUMEN EJECUTIVO              -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'resumen') active @endif" id="tab-resumen">
		<div class="row">
			<div class="col-lg-8">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-layer-group text-primary"></i>
							Rendimiento Laboral y Eficiencia por Departamento
						</h2>
						<span class="badge badge-light font-weight-bold">Semana Actual</span>
					</div>

					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Departamento</th>
									<th class="text-center">Colaboradores</th>
									<th class="text-right">Meta SPLH</th>
									<th class="text-right">SPLH Real</th>
									<th class="text-center">Cumplimiento</th>
									<th class="text-right">Costo Laboral</th>
								</tr>
							</thead>
							<tbody>
								@foreach($dashboard['departments'] as $d)
									<tr>
										<td><strong>{{ $d['department'] }}</strong></td>
										<td class="text-center"><span class="badge badge-secondary">{{ $d['employees_count'] }} asociados</span></td>
										<td class="text-right text-muted">${{ number_format($d['target_splh'], 1) }}/h</td>
										<td class="text-right font-weight-bold text-primary">${{ number_format($d['real_splh'], 1) }}/h</td>
										<td class="text-center">
											<div class="d-flex align-items-center justify-content-center gap-1">
												<div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
													<div class="progress-bar @if($d['compliance_pct'] >= 100) bg-success @else bg-warning @endif" style="width: {{ min(100, $d['compliance_pct']) }}%;"></div>
												</div>
												<span class="font-weight-bold small">{{ $d['compliance_pct'] }}%</span>
											</div>
										</td>
										<td class="text-right font-weight-bold">{{ $d['labor_cost_pct'] }}%</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-bell text-warning"></i>
							Alertas y Novedades de Personas
						</h2>
					</div>

					<div class="d-flex flex-column gap-2">
						<div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-triangle-exclamation text-danger mr-1"></i> 2 Turnos de Cierre sin Cubrir</strong>
								<span class="text-muted small">Cajas y Fruver requieren refuerzo sábado 18:00</span>
							</div>
							<a href="{{ $U('/operations/scheduling') }}" class="btn btn-xs btn-outline-primary">Resolver</a>
						</div>

						<div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-certificate text-warning mr-1"></i> 3 Certificaciones BPM por Vencer</strong>
								<span class="text-muted small">Vencimiento en los próximos 15 días</span>
							</div>
							<button class="btn btn-xs btn-outline-warning" onclick="document.querySelector('[data-tab=\'capacitacion\']').click()">Revisar</button>
						</div>

						<div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
							<div>
								<strong class="d-block small text-dark"><i class="fa-solid fa-right-left text-info mr-1"></i> 1 Solicitud de Cambio de Turno</strong>
								<span class="text-muted small">Mateo Gómez $\leftrightarrow$ Carlos Silva</span>
							</div>
							<button class="btn btn-xs btn-outline-info" onclick="document.querySelector('[data-tab=\'comunicaciones\']').click()">Aprobar</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 2: GESTIÓN DE COLABORADORES       -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'colaboradores') active @endif" id="tab-colaboradores">
		<div class="hr-card">
			<div class="hr-card-header">
				<div class="d-flex align-items-center flex-wrap gap-2">
					<h2 class="hr-card-title mr-3">
						<i class="fa-solid fa-id-badge text-primary"></i>
						Nómina de Colaboradores y Expedientes Digitales
					</h2>
					<input type="text" id="empSearchInput" class="form-control form-control-sm" placeholder="Buscar por nombre, cargo o código..." style="max-width: 260px;">
					<select id="empDeptFilter" class="form-control form-control-sm" style="max-width: 180px;">
						<option value="all">Todos los departamentos</option>
						@foreach($dashboard['departments'] as $dp)
							<option value="{{ $dp['department'] }}">{{ $dp['department'] }}</option>
						@endforeach
					</select>
				</div>
				<div>
					<span class="text-muted small font-weight-bold">Total listados: {{ count($collaborators) }}</span>
				</div>
			</div>

			<div class="table-responsive">
				<table class="hr-table">
					<thead>
						<tr>
							<th>Colaborador</th>
							<th>Código</th>
							<th>Departamento</th>
							<th>Cargo</th>
							<th>Sucursal</th>
							<th>Antigüedad</th>
							<th>Contrato</th>
							<th class="text-right">Costo Base</th>
							<th class="text-center">Estado</th>
							<th class="text-center">Acciones</th>
						</tr>
					</thead>
					<tbody id="empTableBody">
						@foreach($collaborators as $emp)
							<tr data-name="{{ $emp['name'] }}" data-dept="{{ $emp['department'] }}" data-position="{{ $emp['position'] }}" data-code="{{ $emp['payroll_code'] }}">
								<td>
									<div class="d-flex align-items-center gap-2">
										<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center font-weight-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
											{{ mb_substr($emp['name'], 0, 1) }}
										</div>
										<div>
											<strong class="d-block text-dark">{{ $emp['name'] }}</strong>
											<span class="text-muted small">{{ $emp['phone'] ?? '+1 305 555 0100' }}</span>
										</div>
									</div>
								</td>
								<td><code>{{ $emp['payroll_code'] ?? ('EMP-' . str_pad($emp['id'], 4, '0', STR_PAD_LEFT)) }}</code></td>
								<td><span class="badge badge-light font-weight-bold">{{ $emp['department'] }}</span></td>
								<td>{{ $emp['position'] }}</td>
								<td>{{ $emp['branch_name'] ?? 'Sucursal' }}</td>
								<td>{{ $emp['seniority'] }}</td>
								<td><span class="badge badge-outline-secondary">{{ $emp['contract_type'] }}</span></td>
								<td class="text-right font-weight-bold">${{ number_format($emp['hourly_cost'], 2) }}/h</td>
								<td class="text-center">
									@if($emp['active'])
										<span class="badge badge-success font-weight-bold">Activo</span>
									@else
										<span class="badge badge-secondary">Inactivo</span>
									@endif
								</td>
								<td class="text-center">
									<button type="button" class="btn btn-xs btn-outline-primary" onclick="openEmployeeProfile({{ json_encode($emp) }})">
										<i class="fa-solid fa-folder-open mr-1"></i> Expediente
									</button>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 3: CONTRATACIÓN & ONBOARDING       -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'contratacion') active @endif" id="tab-contratacion">
		<div class="row">
			<!-- Vacantes Activas -->
			<div class="col-12 mb-3">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-briefcase text-primary"></i>
							Vacantes Activas de Tienda
						</h2>
						<span class="badge badge-primary">{{ count($hiring['vacancies']) }} Abiertas</span>
					</div>

					<div class="row">
						@foreach($hiring['vacancies'] as $vac)
							<div class="col-md-4 mb-2">
								<div class="p-3 border rounded bg-light h-100 d-flex flex-column justify-content-between">
									<div>
										<div class="d-flex justify-content-between align-items-center mb-1">
											<span class="badge badge-info">{{ $vac['department'] }}</span>
											<span class="text-muted small">${{ number_format($vac['salary_min'], 0) }} - ${{ number_format($vac['salary_max'], 0) }}/h</span>
										</div>
										<strong class="d-block text-dark font-weight-bold mb-1">{{ $vac['title'] }}</strong>
										<p class="text-muted small mb-2">{{ $vac['description'] }}</p>
									</div>
									<div class="d-flex justify-content-between align-items-center pt-2 border-top">
										<span class="small font-weight-bold text-primary">{{ $vac['candidates_count'] }} Candidatos</span>
										<span class="badge badge-success">{{ $vac['positions_count'] }} puesto(s)</span>
									</div>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>

			<!-- Pipeline Kanban de Candidatos -->
			<div class="col-12 mb-3">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-diagram-project text-primary"></i>
							Pipeline de Selección y Reclutamiento
						</h2>
						<span class="text-muted small">Arrastra o cambia la etapa del candidato en un clic</span>
					</div>

					<div class="hr-kanban-board">
						@foreach($hiring['stages'] as $stKey => $stData)
							<div class="hr-kanban-col">
								<div class="hr-kanban-header" style="border-color: {{ $stData['color'] }};">
									<span class="hr-kanban-title" style="color: {{ $stData['color'] }};">{{ $stData['title'] }}</span>
									<span class="badge badge-light font-weight-bold">{{ count($stData['items']) }}</span>
								</div>

								<div class="d-flex flex-column gap-2 flex-grow-1">
									@forelse($stData['items'] as $cand)
										<div class="hr-candidate-card">
											<div class="hr-candidate-name">{{ $cand['full_name'] }}</div>
											<div class="hr-candidate-role">{{ $cand['vacancy_title'] }}</div>
											<div class="hr-candidate-meta">
												<span><i class="fa-solid fa-phone mr-1"></i> {{ $cand['phone'] }}</span>
												<span>
													@for($i = 0; $i < $cand['rating']; $i++)
														<i class="fa-solid fa-star text-warning" style="font-size: 0.65rem;"></i>
													@endfor
												</span>
											</div>
											@if(!empty($cand['interview_date']))
												<div class="mt-1 badge badge-light text-primary small">
													<i class="fa-regular fa-calendar-clock mr-1"></i> {{ date('d/m H:i', strtotime($cand['interview_date'])) }}
												</div>
											@endif

											<!-- Selector rápido de etapa -->
											<div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center">
												<span class="text-muted" style="font-size: 0.68rem;">Mover:</span>
												<select class="form-control form-control-sm py-0 px-1" style="height: 22px; font-size: 0.7rem; max-width: 110px;" onchange="changeCandidateStage({{ $cand['id'] }}, this.value)">
													@foreach($hiring['stages'] as $k => $s)
														<option value="{{ $k }}" @if($k == $cand['stage']) selected @endif>{{ $s['title'] }}</option>
													@endforeach
												</select>
											</div>
										</div>
									@empty
										<div class="text-muted text-center py-4 small">Sin candidatos</div>
									@endforelse
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>

			<!-- Incorporación y Onboarding Checklist -->
			<div class="col-12">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-list-check text-success"></i>
							Checklist de Incorporación & Documentos Pendientes
						</h2>
						<span class="badge badge-light font-weight-bold">Control de Ingreso</span>
					</div>

					<div class="row">
						@foreach($hiring['onboarding'] as $task)
							<div class="col-md-6 mb-2">
								<div class="hr-onboarding-item">
									<div class="hr-onboarding-left">
										<input type="checkbox" @if($task['completed']) checked @endif onchange="toggleOnboardingTask({{ $task['id'] }}, this)">
										<div>
											<strong class="d-block small text-dark @if($task['completed']) text-muted text-decoration-line-through @endif">{{ $task['task_name'] }}</strong>
											<span class="text-muted small">Colaborador: {{ $task['collaborator_name'] }} &bull; Vence: {{ date('d/m/Y', strtotime($task['due_date'])) }}</span>
										</div>
									</div>
									<span class="badge badge-light text-uppercase" style="font-size: 0.68rem;">{{ $task['category'] }}</span>
								</div>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 4: HORARIOS & COBERTURA           -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'horarios') active @endif" id="tab-horarios">
		<div class="hr-card">
			<div class="hr-card-header">
				<h2 class="hr-card-title">
					<i class="fa-solid fa-calendar-days text-primary"></i>
					Malla Horaria y Cobertura de Turnos (Semana {{ date('d/m', strtotime($schedules['week'])) }} - {{ date('d/m/Y', strtotime($schedules['week_end'])) }})
				</h2>
				<div class="d-flex align-items-center gap-2">
					<span class="badge badge-success font-weight-bold">Aperturas: {{ $schedules['counts']['Apertura'] }}</span>
					<span class="badge badge-primary font-weight-bold">Intermedios: {{ $schedules['counts']['Intermedio'] }}</span>
					<span class="badge badge-dark font-weight-bold">Cierres: {{ $schedules['counts']['Cierre'] }}</span>
					<a href="{{ $U('/operations/scheduling') }}" class="btn btn-sm btn-primary ml-2">
						<i class="fa-solid fa-sliders mr-1"></i> Abrir Motor de Horarios
					</a>
				</div>
			</div>

			<div class="table-responsive">
				<table class="hr-table">
					<thead>
						<tr>
							<th>Fecha</th>
							<th>Colaborador</th>
							<th>Departamento</th>
							<th>Tipo de Turno</th>
							<th>Horario</th>
							<th>Descanso</th>
							<th>Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach(array_slice($schedules['shifts'], 0, 25) as $sh)
							<tr>
								<td><strong>{{ date('d/m/Y', strtotime($sh['shift_date'])) }}</strong></td>
								<td>{{ $sh['employee_name'] }}</td>
								<td><span class="badge badge-light">{{ $sh['employee_department'] }}</span></td>
								<td>
									@if($sh['shift_type'] === 'Apertura')
										<span class="badge badge-success">Apertura</span>
									@elseif($sh['shift_type'] === 'Cierre')
										<span class="badge badge-dark">Cierre</span>
									@else
										<span class="badge badge-primary">Intermedio</span>
									@endif
								</td>
								<td><code>{{ $sh['start_time'] }} - {{ $sh['end_time'] }}</code></td>
								<td>{{ $sh['break_minutes'] }} min</td>
								<td><span class="badge badge-outline-success">Publicado</span></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 5: ASISTENCIA EN VIVO             -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'asistencia') active @endif" id="tab-asistencia">
		<div class="hr-card">
			<div class="hr-card-header">
				<h2 class="hr-card-title">
					<i class="fa-solid fa-fingerprint text-success"></i>
					Registro de Fichajes y Reloj en Vivo (Hoy)
				</h2>
				<a href="{{ $U('/operations/attendance') }}" class="btn btn-sm btn-outline-primary">
					<i class="fa-solid fa-clock-rotate-left mr-1"></i> Centro de Asistencia Completo
				</a>
			</div>

			<div class="table-responsive">
				<table class="hr-table">
					<thead>
						<tr>
							<th>Colaborador</th>
							<th>Departamento</th>
							<th>Entrada (Clock-In)</th>
							<th>Salida (Clock-Out)</th>
							<th>Horas Efectivas</th>
							<th>Estado en Vivo</th>
						</tr>
					</thead>
					<tbody>
						@foreach(array_slice($attendance['records'], 0, 20) as $att)
							<tr>
								<td>
									<strong>{{ $att['employee_name'] }}</strong>
									<span class="text-muted d-block small">{{ $att['employee_position'] }}</span>
								</td>
								<td><span class="badge badge-light">{{ $att['employee_department'] }}</span></td>
								<td class="text-success font-weight-bold">
									<i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> {{ date('H:i', strtotime($att['clock_in'])) }}
								</td>
								<td class="text-muted">
									@if(!empty($att['clock_out']))
										<i class="fa-solid fa-arrow-right-from-bracket mr-1 text-danger"></i> {{ date('H:i', strtotime($att['clock_out'])) }}
									@else
										<span class="badge badge-light">En turno</span>
									@endif
								</td>
								<td><strong>{{ $att['worked_hours'] }}</strong></td>
								<td>
									@if($att['live_status'] === 'En Turno')
										<span class="badge badge-success"><span class="hr-badge-dot"></span> En Tienda</span>
									@else
										<span class="badge badge-secondary">Completado</span>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 6: NÓMINA & PRE-LIQUIDACIÓN       -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'nomina') active @endif" id="tab-nomina">
		<div class="hr-card">
			<div class="hr-card-header">
				<h2 class="hr-card-title">
					<i class="fa-solid fa-money-check-dollar text-primary"></i>
					Pre-Liquidación de Nómina y Recargos Laborales (Período {{ $payroll['period'] }})
				</h2>
				<div class="d-flex align-items-center gap-2">
					<span class="badge badge-success font-weight-bold py-1 px-2">Costo Total: ${{ number_format($payroll['totals']['grand_total'], 2) }}</span>
					<a href="{{ $U('/hr/payroll/export') }}?branch_id={{ $selectedBranchId }}&period={{ $payroll['period'] }}" class="btn btn-sm btn-success">
						<i class="fa-solid fa-file-excel mr-1"></i> Descargar Nómina CSV
					</a>
				</div>
			</div>

			<div class="table-responsive">
				<table class="hr-table">
					<thead>
						<tr>
							<th>Código</th>
							<th>Colaborador</th>
							<th>Departamento</th>
							<th class="text-right">Salario Base</th>
							<th class="text-right">Horas Extras</th>
							<th class="text-right">Recargos</th>
							<th class="text-right">Bonos</th>
							<th class="text-right">Devengado</th>
							<th class="text-right">Cargas Sociales</th>
							<th class="text-right">Costo Total Empleador</th>
							<th class="text-center">Estado</th>
						</tr>
					</thead>
					<tbody>
						@foreach($payroll['rows'] as $pr)
							<tr>
								<td><code>{{ $pr['payroll_code'] }}</code></td>
								<td><strong>{{ $pr['name'] }}</strong></td>
								<td><span class="badge badge-light">{{ $pr['department'] }}</span></td>
								<td class="text-right">${{ number_format($pr['base_pay'], 2) }}</td>
								<td class="text-right text-primary">+${{ number_format($pr['overtime_pay'], 2) }}</td>
								<td class="text-right text-warning">+${{ number_format($pr['surcharges'], 2) }}</td>
								<td class="text-right text-success">+${{ number_format($pr['bonuses'], 2) }}</td>
								<td class="text-right font-weight-bold">${{ number_format($pr['gross_pay'], 2) }}</td>
								<td class="text-right text-muted">${{ number_format($pr['benefits_cost'], 2) }}</td>
								<td class="text-right font-weight-bold text-dark">${{ number_format($pr['total_cost'], 2) }}</td>
								<td class="text-center"><span class="badge badge-info">{{ $pr['status'] }}</span></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>

		<div class="row mt-3">
			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100" id="hr-compensatory-panel">
					<div class="hr-card-header">
						<h3 class="hr-card-title"><i class="fa-solid fa-calendar-plus text-warning"></i> Libro de Compensatorios ({{ $compensatoryLedger['jurisdiction'] ?? ($payroll['jurisdiction'] ?? 'CO-GEN') }})</h3>
					</div>
					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Colaborador</th>
									<th class="text-center">Pendientes</th>
									<th class="text-center">Tomados</th>
									<th class="text-right">Pago Vencido/Ocasional</th>
									<th>Próximo Límite</th>
								</tr>
							</thead>
							<tbody>
								@forelse(($compensatoryLedger['balances'] ?? []) as $cb)
									<tr>
										<td><strong>{{ $cb['name'] }}</strong> <small class="text-muted">({{ $cb['department'] }})</small></td>
										<td class="text-center"><span class="badge {{ $cb['pending_days'] > 0 ? 'badge-warning text-dark' : 'badge-light' }}">{{ $cb['pending_days'] }}</span></td>
										<td class="text-center">{{ $cb['taken_days'] }}</td>
										<td class="text-right">${{ number_format($cb['total_payout_due'] ?? 0, 2) }}</td>
										<td class="small">{{ $cb['next_due_date'] ?: '—' }}</td>
									</tr>
								@empty
									<tr><td colspan="5" class="text-center text-muted py-3">Sin saldos compensatorios registrados en la sucursal.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100" id="hr-vacations-panel">
					<div class="hr-card-header">
						<h3 class="hr-card-title"><i class="fa-solid fa-umbrella-beach text-success"></i> Control de Saldos de Vacaciones</h3>
					</div>
					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Colaborador</th>
									<th>Departamento</th>
									<th class="text-center">Acumulados</th>
									<th class="text-center">Tomados</th>
									<th class="text-center">Disponibles</th>
								</tr>
							</thead>
							<tbody>
								@forelse(($vacationBalances ?? []) as $vb)
									<tr>
										<td><strong>{{ $vb['name'] }}</strong></td>
										<td><span class="badge badge-light">{{ $vb['department'] }}</span></td>
										<td class="text-center">{{ $vb['accrued_days'] }}d</td>
										<td class="text-center">{{ $vb['taken_days'] }}d</td>
										<td class="text-center font-weight-bold text-success">{{ $vb['available_days'] }}d</td>
									</tr>
								@empty
									<tr><td colspan="5" class="text-center text-muted py-3">Sin saldos de vacaciones cargados.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 7: PRODUCTIVIDAD & SPLH           -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'productividad') active @endif" id="tab-productividad">
		<div class="row">
			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-chart-column text-primary"></i>
							Comparativa de Eficiencia por Turno
						</h2>
					</div>

					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Turno</th>
									<th class="text-right">SPLH</th>
									<th class="text-center">Tráfico</th>
									<th class="text-right">Unid./Hora</th>
									<th class="text-center">Eficiencia</th>
								</tr>
							</thead>
							<tbody>
								@foreach($productivity['shifts_comparison'] as $sc)
									<tr>
										<td><strong>{{ $sc['shift'] }}</strong></td>
										<td class="text-right font-weight-bold text-primary">${{ number_format($sc['splh'], 1) }}</td>
										<td class="text-center">{{ $sc['traffic_pct'] }}%</td>
										<td class="text-right">{{ $sc['units_hour'] }} u/h</td>
										<td class="text-center"><span class="badge badge-success">{{ $sc['efficiency'] }}</span></td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-bullseye text-success"></i>
							Metas de Rendimiento por Departamento
						</h2>
					</div>

					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Departamento</th>
									<th class="text-right">Meta SPLH</th>
									<th class="text-right">Unid/Hora</th>
									<th class="text-right">Costo Laboral</th>
								</tr>
							</thead>
							<tbody>
								@foreach($productivity['departments'] as $pdept)
									<tr>
										<td><strong>{{ $pdept['department'] }}</strong></td>
										<td class="text-right font-weight-bold">${{ number_format($pdept['target_splh'], 1) }}/h</td>
										<td class="text-right">{{ $pdept['units_per_hour'] }} u/h</td>
										<td class="text-right text-info font-weight-bold">{{ $pdept['labor_cost_pct'] }}%</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 8: CAPACITACIÓN & BPM             -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'capacitacion') active @endif" id="tab-capacitacion">
		<div class="row">
			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-graduation-cap text-primary"></i>
							Catálogo de Cursos & Programas de Formación
						</h2>
						<span class="badge badge-success font-weight-bold">{{ $training['overall_compliance_pct'] }}% Cumplimiento</span>
					</div>

					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Curso</th>
									<th>Área</th>
									<th>Duración</th>
									<th>Obligatorio</th>
								</tr>
							</thead>
							<tbody>
								@foreach($training['trainings'] as $tr)
									<tr>
										<td><strong>{{ $tr['title'] }}</strong></td>
										<td><span class="badge badge-light">{{ $tr['department'] }}</span></td>
										<td>{{ $tr['duration_hours'] }} horas</td>
										<td>
											@if($tr['is_mandatory'])
												<span class="badge badge-danger">Obligatorio</span>
											@else
												<span class="badge badge-secondary">Optativo</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="col-lg-6 mb-3">
				<div class="hr-card h-100">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-certificate text-warning"></i>
							Certificaciones y Renovaciones por Vencer
						</h2>
						<span class="badge badge-warning">{{ $training['expiring_count'] }} Por vencer</span>
					</div>

					<div class="table-responsive">
						<table class="hr-table">
							<thead>
								<tr>
									<th>Colaborador</th>
									<th>Certificación</th>
									<th>Vencimiento</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody>
								@forelse($training['certifications'] as $cert)
									<tr>
										<td><strong>{{ $cert['employee_name'] }}</strong></td>
										<td>{{ $cert['course_name'] }}</td>
										<td>{{ date('d/m/Y', strtotime($cert['expiry_date'])) }}</td>
										<td>
											@if($cert['status'] === 'valid')
												<span class="badge badge-success">Vigente</span>
											@elseif($cert['status'] === 'expiring')
												<span class="badge badge-warning">Por Vencer</span>
											@else
												<span class="badge badge-danger">Vencida</span>
											@endif
										</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center py-3 text-muted">Todas las certificaciones al día</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- PESTAÑA 9: COMUNICACIONES & SOLICITUDES   -->
	<!-- ========================================== -->
	<div class="hr-tab-panel @if($activeTab == 'comunicaciones') active @endif" id="tab-comunicaciones">
		<div class="row">
			<div class="col-lg-7 mb-3">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-bullhorn text-primary"></i>
							Tablón de Anuncios y Avisos Internos
						</h2>
					</div>

					<div class="d-flex flex-column gap-2 mb-3">
						@foreach($communications['communications'] as $comm)
							<div class="p-3 border rounded @if($comm['priority'] === 'urgente') bg-warning-subtle border-warning @else bg-light @endif">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<strong class="text-dark">{{ $comm['title'] }}</strong>
									<span class="badge @if($comm['priority'] === 'urgente') badge-danger @else badge-secondary @endif text-uppercase">{{ $comm['priority'] }}</span>
								</div>
								<p class="text-muted small mb-1">{{ $comm['message'] }}</p>
								<span class="text-muted" style="font-size: 0.7rem;"><i class="fa-regular fa-clock mr-1"></i> Publicado por {{ $comm['sender_name'] }} &bull; {{ date('d/m/Y H:i', strtotime($comm['created_at'])) }}</span>
							</div>
						@endforeach
					</div>

					<!-- Formulario de nuevo aviso -->
					<div class="border-top pt-3">
						<h3 class="h6 font-weight-bold mb-2">Publicar Aviso Oficial</h3>
						<form id="newCommForm">
							<input type="hidden" id="commBranchId" value="{{ $selectedBranchId }}">
							<div class="form-group mb-2">
								<input type="text" id="commTitle" class="form-control form-control-sm" placeholder="Título del aviso..." required>
							</div>
							<div class="form-group mb-2">
								<textarea id="commMsg" class="form-control form-control-sm" rows="2" placeholder="Contenido del mensaje..." required></textarea>
							</div>
							<div class="d-flex justify-content-between align-items-center">
								<select id="commPriority" class="form-control form-control-sm" style="max-width: 140px;">
									<option value="normal">Prioridad Normal</option>
									<option value="urgente">Urgente</option>
								</select>
								<button type="submit" class="btn btn-sm btn-primary">
									<i class="fa-solid fa-paper-plane mr-1"></i> Publicar Mensaje
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div class="col-lg-5 mb-3">
				<div class="hr-card">
					<div class="hr-card-header">
						<h2 class="hr-card-title">
							<i class="fa-solid fa-right-left text-info"></i>
							Solicitudes de Cambio de Turno
						</h2>
					</div>

					<div class="d-flex flex-column gap-2">
						@forelse($communications['swap_requests'] as $swap)
							<div class="p-3 border rounded bg-light">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<strong class="small text-dark">{{ $swap['from_employee_name'] }} $\rightarrow$ {{ $swap['to_employee_name'] }}</strong>
									<span class="badge @if($swap['status'] === 'approved') badge-success @elseif($swap['status'] === 'pending') badge-warning @else badge-danger @endif">
										{{ $swap['status'] }}
									</span>
								</div>
								<div class="small text-muted mb-2">
									Turno: {{ date('d/m/Y', strtotime($swap['shift_date'] ?? '2026-11-04')) }} ({{ $swap['start_time'] ?? '08:00' }} - {{ $swap['end_time'] ?? '16:00' }})
								</div>
								@if($swap['status'] === 'pending')
									<div class="d-flex gap-2">
										<button class="btn btn-xs btn-success flex-grow-1" onclick="decideRequest({{ $swap['id'] }}, 'approved')">
											<i class="fa-solid fa-check mr-1"></i> Aprobar
										</button>
										<button class="btn btn-xs btn-outline-danger flex-grow-1" onclick="decideRequest({{ $swap['id'] }}, 'rejected')">
											<i class="fa-solid fa-xmark mr-1"></i> Rechazar
										</button>
									</div>
								@endif
							</div>
						@empty
							<div class="text-muted text-center py-4 small">Sin solicitudes pendientes</div>
						@endforelse
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- MODAL: EXPEDIENTE DIGITAL DE COLABORADOR -->
<div class="hr-modal-overlay" id="hrProfileModal">
	<div class="hr-modal-box">
		<div class="hr-modal-header">
			<div class="d-flex align-items-center gap-2">
				<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center font-weight-bold" style="width: 40px; height: 40px; font-size: 1rem;">
					<i class="fa-solid fa-user"></i>
				</div>
				<div>
					<h3 class="h5 font-weight-bold m-0" id="profName">Nombre del Colaborador</h3>
					<span class="text-muted small">Expediente Digital Laboral &bull; Código <code id="profCode">EMP-0000</code></span>
				</div>
			</div>
			<button type="button" class="hr-modal-close" id="closeProfileBtn">&times;</button>
		</div>

		<div class="row">
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Cargo / Puesto</span>
				<strong class="d-block" id="profPosition">Asociado</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Departamento</span>
				<strong class="d-block" id="profDept">General</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Sucursal Asignada</span>
				<strong class="d-block" id="profBranch">Sucursal</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Supervisor Responsable</span>
				<strong class="d-block" id="profSupervisor">Carlos M.</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Teléfono / Contacto</span>
				<strong class="d-block" id="profPhone">+1 305 555 0100</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Antigüedad en la Empresa</span>
				<strong class="d-block" id="profSeniority">1a 2m</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Tipo de Contrato</span>
				<strong class="d-block text-primary" id="profContract">Indefinido</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Jornada Laboral</span>
				<strong class="d-block" id="profHours">40 hrs/semana</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Salario Base por Hora</span>
				<strong class="d-block text-success font-weight-bold" id="profWage">$16.50 /h</strong>
			</div>
			<div class="col-md-6 mb-2">
				<span class="text-muted small d-block">Costo Empresa con Cargas</span>
				<strong class="d-block text-dark font-weight-bold" id="profBurden">$20.13 /h</strong>
			</div>
		</div>

		<div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
			<span class="badge badge-success font-weight-bold"><i class="fa-solid fa-shield-check mr-1"></i> Documentación y Exámenes al Día</span>
			<button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('hrProfileModal').classList.remove('active')">Cerrar</button>
		</div>
	</div>
</div>

@push('pageScripts')
<script src="{{ $U('/js/daily_hr.js?v=', true) }}{{ daily_asset_version('/js/daily_hr.js') }}"></script>
@endpush
@endsection

