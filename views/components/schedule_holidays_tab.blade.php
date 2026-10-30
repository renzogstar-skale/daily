@php
	$hols = $colombianHolidays ?? [];
	$vacs = $vacationsList ?? [];
@endphp

<div id="schHolidaysSection" class="sch-holidays-section" style="display: none;">

	<!-- Top Header -->
	<div class="mb-3 p-3 bg-white rounded-lg border shadow-sm d-flex flex-wrap align-items-center justify-content-between">
		<div>
			<h5 class="mb-1 font-weight-bold text-dark">
				<i class="fa-solid fa-calendar-check mr-2 text-danger"></i> Festivos Colombianos (Ley Emiliani) & Gestión de Vacaciones
			</h5>
			<p class="small text-muted mb-0">
				Motor normativo conforme a la Ley 51 de 1983. Los días festivos bloquean automáticamente la asignación de compensatorios dominicales. Las vacaciones bloquean asignación de turnos sin descontar al colaborador del catálogo.
			</p>
		</div>
		<div class="mt-2 mt-md-0">
			<button type="button" class="btn btn-sm btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#modalAddVacation">
				<i class="fa-solid fa-plus mr-1"></i> Registrar Vacaciones
			</button>
		</div>
	</div>

	<div class="row">
		<!-- Left: Vacations (4 Obligatorias de Noviembre destacadas) -->
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm border-0 h-100">
				<div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
					<h6 class="mb-0 font-weight-bold text-dark">
						<i class="fa-solid fa-umbrella-beach mr-2 text-purple"></i> Vacaciones Aprobadas (Noviembre 2026 en adelante)
					</h6>
					<span class="badge badge-purple px-2 py-1 font-weight-bold">{{ count($vacs) }} registradas</span>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0" style="font-size: 0.8rem;">
							<thead class="bg-light">
								<tr>
									<th class="font-weight-700 py-2">Colaborador</th>
									<th class="font-weight-700 py-2">Período de Vacaciones</th>
									<th class="font-weight-700 py-2 text-center">Días</th>
									<th class="font-weight-700 py-2">Estado</th>
								</tr>
							</thead>
							<tbody>
								@forelse($vacs as $v)
									@php
										$sTs = strtotime($v['start_date']);
										$eTs = strtotime($v['end_date']);
										$daysCount = intdiv($eTs - $sTs, 86400) + 1;
									@endphp
									<tr>
										<td>
											<div class="font-weight-bold text-dark">{{ $v['collaborator_name'] }}</div>
											<code class="small text-muted">{{ str_replace('DEMO-', 'EJ-', $v['payroll_code']) }}</code> · <span class="text-muted">{{ $v['department'] }}</span>
										</td>
										<td>
											<span class="font-weight-600 text-dark">{{ date('d/m/Y', $sTs) }}</span> al <span class="font-weight-600 text-dark">{{ date('d/m/Y', $eTs) }}</span>
											<div class="small text-muted font-italic">{{ $v['notes'] ?? 'Vacaciones autorizadas' }}</div>
										</td>
										<td class="text-center font-weight-bold text-purple">
											{{ $daysCount }} d
										</td>
										<td>
											<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i> Aprobadas</span>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="4" class="text-center py-4 text-muted">No hay vacaciones registradas</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Right: Colombian Holidays Calendar (Ley 51 de 1983) -->
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm border-0 h-100">
				<div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
					<h6 class="mb-0 font-weight-bold text-dark">
						<i class="fa-solid fa-flag mr-2 text-danger"></i> Calendario de Festivos Oficiales (Colombia)
					</h6>
					<span class="badge badge-danger px-2 py-1 font-weight-bold">{{ count($hols) }} festivos anuales</span>
				</div>
				<div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0" style="font-size: 0.8rem;">
							<thead class="bg-light sticky-top">
								<tr>
									<th class="font-weight-700 py-2">Fecha</th>
									<th class="font-weight-700 py-2">Nombre de la Festividad</th>
									<th class="font-weight-700 py-2 text-center">Tipo</th>
									<th class="font-weight-700 py-2 text-center">Bloquea Compensatorio</th>
								</tr>
							</thead>
							<tbody>
								@foreach($hols as $h)
									@php
										$isNov = str_starts_with($h['holiday_date'], '2026-11');
									@endphp
									<tr class="@if($isNov) bg-warning-light @endif">
										<td>
											<span class="font-weight-bold @if($isNov) text-danger @else text-dark @endif">
												{{ date('d/m/Y', strtotime($h['holiday_date'])) }}
											</span>
											<div class="small text-muted">{{ date('l', strtotime($h['holiday_date'])) }}</div>
										</td>
										<td class="font-weight-600 @if($isNov) text-dark @else text-secondary @endif">
											{{ $h['name'] }}
											@if($isNov)
												<span class="badge badge-danger ml-1">Noviembre 2026</span>
											@endif
										</td>
										<td class="text-center">
											<span class="badge badge-light border text-uppercase" style="font-size: 0.68rem;">
												{{ $h['holiday_type'] }}
											</span>
										</td>
										<td class="text-center">
											<span class="badge badge-danger px-2 py-1 font-weight-bold">
												<i class="fa-solid fa-ban mr-1"></i> Bloqueado
											</span>
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Modal Registrar Vacaciones -->
<div class="modal fade" id="modalAddVacation" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-purple text-white py-2 px-3" style="background-color: #7c3aed;">
				<h6 class="modal-title font-weight-bold mb-0 text-white"><i class="fa-solid fa-umbrella-beach mr-1"></i> Registrar Vacaciones</h6>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="form-group mb-2">
					<label class="small font-weight-bold text-dark mb-1">Colaborador:</label>
					<select class="custom-select custom-select-sm" id="vacPayrollCodeSelect">
						<option value="">Seleccione el colaborador...</option>
						@foreach($sMatrix as $m)
							<option value="{{ $m['payroll_code'] }}">{{ $m['name'] }} ({{ $m['primary_area'] }})</option>
						@endforeach
					</select>
				</div>
				<div class="form-row mb-2">
					<div class="col-6">
						<label class="small font-weight-bold text-dark mb-1">Fecha de Inicio:</label>
						<input type="date" class="form-control form-control-sm" id="vacStartDate" required>
					</div>
					<div class="col-6">
						<label class="small font-weight-bold text-dark mb-1">Fecha de Fin (Inclusive):</label>
						<input type="date" class="form-control form-control-sm" id="vacEndDate" required>
					</div>
				</div>
				<div class="form-group mb-0">
					<label class="small font-weight-bold text-dark mb-1">Motivo / Observaciones:</label>
					<input type="text" class="form-control form-control-sm" id="vacNotes" placeholder="Período vacacional anual aprobado">
				</div>
			</div>
			<div class="modal-footer p-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm text-white font-weight-bold" style="background-color: #7c3aed;" id="btnSubmitVacation">
					<i class="fa-solid fa-save mr-1"></i> Guardar Vacaciones
				</button>
			</div>
		</div>
	</div>
</div>
