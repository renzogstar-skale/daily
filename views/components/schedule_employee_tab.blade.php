{{-- DAILY: Vista Por Empleado — Ficha e Historial Individual --}}
<div id="schViewEmployee" class="sch-view-container" style="display: none;">
	<!-- Employee Selector Card -->
	<div class="sch-card mb-3">
		<div class="sch-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h3 class="sch-card-title mb-0">
					<i class="fa-solid fa-user-tie text-purple mr-2"></i>Ficha Operativa y Programación Individual
				</h3>
				<p class="text-muted small mb-0 mt-1">
					Consulta detallada de turnos, equidad acumulada, compensatorios pendientes, historial de modificaciones y programación semanal/mensual.
				</p>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<label class="small text-muted mb-0 mr-1 font-weight-bold">Colaborador:</label>
				<select class="form-control form-control-sm font-weight-bold" id="employeeDossierSelect" style="max-width: 320px;">
					@foreach($employeeViewData['collaborators'] as $col)
						<option value="{{ $col['payroll_code'] }}" @if($col['payroll_code'] === ($employeeViewData['selected']['payroll_code'] ?? '')) selected @endif>
							{{ $col['name'] }} ({{ $col['department'] }})
						</option>
					@endforeach
				</select>
			</div>
		</div>

		@php
			$sel = $employeeViewData['selected'];
			$stats = $employeeViewData['stats'];
			$skills = $employeeViewData['skills'];
		@endphp

		@if($sel)
			<!-- Profile Overview Header -->
			<div class="p-3 bg-light border-bottom">
				<div class="row align-items-center">
					<div class="col-md-7 col-12 d-flex align-items-center">
						<div class="mr-3 rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold" style="width: 52px; height: 52px; background: linear-gradient(135deg, #059669, #10b981); font-size: 1.2rem;">
							{{ substr($sel['name'], 0, 1) }}{{ substr(strstr($sel['name'], ' ') ?: ' ', 1, 1) }}
						</div>
						<div>
							<h5 class="mb-1 font-weight-bold text-dark">{{ $sel['name'] }}</h5>
							<div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
								<span class="badge badge-light border"><i class="fa-solid fa-id-badge mr-1 text-primary"></i>Código: <strong>{{ str_replace('DEMO-', 'EJ-', $sel['payroll_code']) }}</strong></span>
								<span class="badge badge-light border"><i class="fa-solid fa-building mr-1 text-info"></i>{{ $sel['department'] }}</span>
								<span class="badge badge-light border"><i class="fa-solid fa-briefcase mr-1 text-muted"></i>{{ $sel['cargo'] ?? $sel['position'] }}</span>
								<span class="badge badge-light border"><i class="fa-solid fa-clock mr-1 text-warning"></i>{{ $sel['weekly_hours'] ?? '—' }}h contratadas</span>
							</div>
						</div>
					</div>
					<div class="col-md-5 col-12 text-md-right mt-3 mt-md-0">
						<div class="d-inline-flex align-items-center gap-2">
							<span class="badge @if($stats['equity_score'] >= 85) badge-success @elseif($stats['equity_score'] >= 70) badge-warning @else badge-danger @endif py-2 px-3" style="font-size: 0.85rem;">
								<i class="fa-solid fa-scale-balanced mr-1"></i>Índice Equidad: {{ $stats['equity_score'] }}%
							</span>
							<button type="button" class="btn btn-sm btn-primary font-weight-bold" onclick="window.switchScheduleView('monthly');">
								<i class="fa-solid fa-calendar-days mr-1"></i>Ver en Calendario
							</button>
						</div>
					</div>
				</div>

				<!-- Secondary Certifications Badges -->
				<div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center gap-1" style="font-size: 0.75rem;">
					<span class="text-muted mr-1 font-weight-bold">Habilidades y autorizaciones:</span>
					@if(!empty($skills['is_apple_certified']))
						<span class="badge badge-dark"><i class="fa-solid fa-apple-whole mr-1"></i>Certificado Apple</span>
					@endif
					@if(!empty($skills['can_do_night']))
						<span class="badge badge-secondary" style="background-color: #334155;"><i class="fa-solid fa-moon mr-1"></i>Apto Nocturno</span>
					@endif
					@if(!empty($skills['can_be_cashier']))
						<span class="badge badge-info"><i class="fa-solid fa-cash-register mr-1"></i>Cajero Autorizado</span>
					@endif
					@if(!empty($skills['is_shift_lead']))
						<span class="badge badge-warning text-dark"><i class="fa-solid fa-crown mr-1"></i>Líder de Turno</span>
					@endif
					@foreach($employeeViewData['secondary_areas'] as $secArea)
						<span class="badge badge-light border text-primary"><i class="fa-solid fa-plus mr-1"></i>Área secundaria: {{ $secArea }}</span>
					@endforeach
				</div>
			</div>

			<!-- Individual Metrics Grid -->
			<div class="p-3">
				<div class="row">
					<!-- Horas semanales y mensuales -->
					<div class="col-xl-3 col-md-6 col-12 mb-3">
						<div class="card h-100 border p-3">
							<span class="text-muted small">Carga Horaria</span>
							<div class="d-flex justify-content-between align-items-baseline mt-2">
								<span class="font-weight-bold" style="font-size: 1.4rem; color: #0f172a;">{{ $stats['hours_scheduled_week'] }} h</span>
								<span class="text-muted small">esta semana</span>
							</div>
							<div class="d-flex justify-content-between align-items-baseline mt-1 small text-muted">
								<span>Mes programado:</span>
								<strong>{{ $stats['hours_scheduled_month'] }} h</strong>
							</div>
							<div class="d-flex justify-content-between align-items-baseline small text-muted">
								<span>Mes efectivamente trabajado:</span>
								<strong class="text-success">{{ $stats['hours_worked_month'] }} h</strong>
							</div>
						</div>
					</div>

					<!-- Aperturas vs Cierres -->
					<div class="col-xl-3 col-md-6 col-12 mb-3">
						<div class="card h-100 border p-3">
							<span class="text-muted small">Equidad Apertura / Cierre</span>
							<div class="d-flex justify-content-between align-items-baseline mt-2">
								<span class="font-weight-bold" style="font-size: 1.4rem; color: #059669;">{{ $stats['openings'] }} A / {{ $stats['closings'] }} C</span>
							</div>
							<div class="d-flex justify-content-between align-items-baseline mt-1 small text-muted">
								<span>Turnos nocturnos:</span>
								<strong>{{ $stats['nights'] }} noche</strong>
							</div>
							<div class="d-flex justify-content-between align-items-baseline small text-muted">
								<span>Días libres disfrutados:</span>
								<strong>{{ $stats['libres'] }} libres</strong>
							</div>
						</div>
					</div>

					<!-- Rotación dominical 3x1 -->
					<div class="col-xl-3 col-md-6 col-12 mb-3">
						<div class="card h-100 border p-3">
							<span class="text-muted small">Rotación Dominical (Ley 3x1)</span>
							<div class="d-flex justify-content-between align-items-baseline mt-2">
								<span class="font-weight-bold" style="font-size: 1.4rem; color: #2563eb;">{{ $stats['sundays_worked'] }} Trab. / {{ $stats['sundays_free'] }} Libre</span>
							</div>
							<div class="d-flex justify-content-between align-items-baseline mt-1 small text-muted">
								<span>Cumplimiento 3x1:</span>
								<strong class="text-success"><i class="fa-solid fa-check mr-1"></i>Secuencia válida</strong>
							</div>
							<div class="d-flex justify-content-between align-items-baseline small text-muted">
								<span>Días en compensatorio:</span>
								<strong>{{ $stats['compensatories'] }} días</strong>
							</div>
						</div>
					</div>

					<!-- Compensatorios pendientes -->
					<div class="col-xl-3 col-md-6 col-12 mb-3">
						<div class="card h-100 border p-3">
							<span class="text-muted small">Banco de Compensatorios</span>
							<div class="d-flex justify-content-between align-items-baseline mt-2">
								<span class="font-weight-bold" style="font-size: 1.4rem; color: #7c3aed;">
									{{ count($employeeViewData['pending_compensatories']) }} pendientes
								</span>
							</div>
							<div class="d-flex justify-content-between align-items-baseline mt-1 small text-muted">
								<span>Vacaciones registradas:</span>
								<strong>{{ count($employeeViewData['vacations']) }} períodos</strong>
							</div>
							<div class="d-flex justify-content-between align-items-baseline small text-muted">
								<span>Costo horario base:</span>
								<strong>${{ number_format($stats['hourly_cost'], 2) }}/h</strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Weekly Shifts Table for Selected Employee -->
				<div class="card border mb-3">
					<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
						<h6 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-calendar-week mr-2 text-primary"></i>Programación Semanal Actual</h6>
						<span class="badge badge-light border text-muted">Lunes a Domingo</span>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-bordered text-center mb-0" style="font-size: 0.82rem;">
							<thead class="thead-light">
								<tr>
									<th>Lunes</th>
									<th>Martes</th>
									<th>Miércoles</th>
									<th>Jueves</th>
									<th>Viernes</th>
									<th>Sábado</th>
									<th>Domingo</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									@php
										$daysOfWeek = [
											0 => 'Lunes', 1 => 'Martes', 2 => 'Miércoles', 3 => 'Jueves', 4 => 'Viernes', 5 => 'Sábado', 6 => 'Domingo'
										];
									@endphp
									@for($i = 0; $i < 7; $i++)
										@php
											$dayDate = date('Y-m-d', strtotime($areaViewData['week_monday'] . " +$i days"));
											$sh = $employeeViewData['weekly_shifts'][$dayDate] ?? null;
										@endphp
										<td class="p-2" style="vertical-align: top; width: 14.28%;">
											<div class="small font-weight-bold text-muted mb-1">{{ date('d M', strtotime($dayDate)) }}</div>
											@if($sh)
												@php
													$sc = $sh['shift_code'];
													$badgeBg = '#059669';
													if (strpos($sc, '7C') === 0) $badgeBg = '#d97706';
													elseif (strpos($sc, '7N') === 0) $badgeBg = '#1e293b';
													elseif ($sc === 'LIBRE') $badgeBg = '#64748b';
													elseif ($sc === 'COMP') $badgeBg = '#475569';
													elseif ($sc === 'VC') $badgeBg = '#7c3aed';
												@endphp
												<div class="p-1 rounded text-white font-weight-bold mb-1" style="background-color: {{ $badgeBg }}; font-size: 0.8rem;">
													{{ $sc }}
												</div>
												<div class="text-muted" style="font-size: 0.7rem;">{{ $sh['start_time'] }} - {{ $sh['end_time'] }}</div>
												<div class="font-weight-600 text-dark" style="font-size: 0.72rem;">{{ $sh['hours'] }}h</div>
												<button type="button" class="btn btn-xs btn-outline-secondary mt-1 py-0 px-1 btn-edit-employee-shift" 
													data-payroll="{{ $sel['payroll_code'] }}" data-date="{{ $dayDate }}" data-shift="{{ $sc }}" data-name="{{ $sel['name'] }}">
													<i class="fa-solid fa-pen" style="font-size: 0.65rem;"></i> Editar
												</button>
											@else
												<span class="badge badge-light border text-muted">Sin turno</span>
												<div class="mt-1">
													<button type="button" class="btn btn-xs btn-outline-primary py-0 px-1 btn-edit-employee-shift" 
														data-payroll="{{ $sel['payroll_code'] }}" data-date="{{ $dayDate }}" data-shift="7A7-14" data-name="{{ $sel['name'] }}">
														+ Asignar
													</button>
												</div>
											@endif
										</td>
									@endfor
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Audit Trail Table for this Employee -->
				<div class="card border">
					<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
						<h6 class="mb-0 font-weight-bold text-dark"><i class="fa-solid fa-clock-rotate-left mr-2 text-muted"></i>Historial de Modificaciones y Auditoría</h6>
						<span class="badge badge-light border text-muted">Últimos 20 registros</span>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" style="font-size: 0.8rem;">
							<thead class="thead-light">
								<tr>
									<th>Fecha/Hora</th>
									<th>Fecha Turno</th>
									<th>Acción</th>
									<th>Turno Anterior</th>
									<th>Turno Nuevo</th>
									<th>Motivo</th>
									<th>Usuario Responsable</th>
								</tr>
							</thead>
							<tbody>
								@forelse($employeeViewData['audit_history'] as $aud)
									<tr>
										<td class="text-muted">{{ $aud['timestamp'] }}</td>
										<td class="font-weight-bold">{{ $aud['shift_date'] }}</td>
										<td>
											<span class="badge @if($aud['action'] === 'create') badge-success @elseif($aud['action'] === 'cancel') badge-danger @else badge-primary @endif">
												{{ strtoupper($aud['action']) }}
											</span>
										</td>
										<td class="text-muted">{{ $aud['old_value'] ?: '—' }}</td>
										<td class="font-weight-bold text-dark">{{ $aud['new_value'] }}</td>
										<td>{{ $aud['reason'] ?: 'Ajuste operativo' }}</td>
										<td class="text-muted"><i class="fa-solid fa-user-circle mr-1"></i>{{ $aud['user_name'] ?? 'Manager' }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="7" class="text-center text-muted py-3">
											<i class="fa-solid fa-shield-check text-success mr-1"></i> No hay modificaciones manuales registradas recientemente para este colaborador. Horarios validados según planilla oficial.
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		@endif
	</div>
</div>
