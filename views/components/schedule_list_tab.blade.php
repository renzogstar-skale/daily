{{-- DAILY: Vista Lista de Turnos — Administración Completa y Maestra --}}
<div id="schViewList" class="sch-view-container" style="display: none;">
	<div class="sch-card mb-3">
		<!-- List Header and Filters Toolbar -->
		<div class="sch-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h3 class="sch-card-title mb-0">
					<i class="fa-solid fa-list-check text-teal mr-2"></i>Lista Maestra de Turnos y Administración Operativa
				</h3>
				<p class="text-muted small mb-0 mt-1">
					Tabla centralizada de turnos con búsqueda en tiempo real, filtros avanzados por área/tipo, edición individual, duplicación y exportación.
				</p>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnListCreateShift" data-toggle="modal" data-target="#modalCreateShift">
					<i class="fa-solid fa-plus mr-1"></i>Crear turno
				</button>
				<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" id="btnListExportCsv">
					<i class="fa-solid fa-file-csv mr-1"></i>Exportar CSV
				</button>
				<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="btnListPublish" data-toggle="modal" data-target="#modalPublishSchedule">
					<i class="fa-solid fa-paper-plane mr-1"></i>Publicar
				</button>
			</div>
		</div>

		<!-- KPI Summary Pills for Shift List -->
		<div class="p-3 bg-light border-bottom">
			<div class="d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 0.82rem;">
				<div class="d-flex flex-wrap align-items-center gap-3">
					<span><strong>Total registros:</strong> <span class="badge badge-light border font-weight-bold text-dark">{{ $shiftListViewData['total_count'] }}</span></span>
					<span><strong>Horas programadas:</strong> <span class="badge badge-light border font-weight-bold text-primary">{{ number_format($shiftListViewData['summary']['total_hours'], 1) }} h</span></span>
					<span><strong>Aperturas:</strong> <span class="badge badge-success">{{ $shiftListViewData['summary']['openings'] }}</span></span>
					<span><strong>Cierres:</strong> <span class="badge badge-warning text-dark">{{ $shiftListViewData['summary']['closings'] }}</span></span>
					<span><strong>Nocturnos:</strong> <span class="badge badge-dark">{{ $shiftListViewData['summary']['nights'] }}</span></span>
					<span><strong>Compensatorios:</strong> <span class="badge badge-secondary" style="background-color: #64748b;">{{ $shiftListViewData['summary']['compensatories'] }}</span></span>
					<span><strong>Vacaciones:</strong> <span class="badge badge-purple" style="background-color: #7c3aed; color: #fff;">{{ $shiftListViewData['summary']['vacations'] }}</span></span>
				</div>
			</div>

			<!-- Filter Inputs Row -->
			<div class="row mt-3 align-items-center">
				<div class="col-md-3 col-12 mb-2 mb-md-0">
					<div class="input-group input-group-sm">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
						</div>
						<input type="text" class="form-control" id="shiftMasterSearch" placeholder="Buscar colaborador, código...">
					</div>
				</div>

				<div class="col-md-3 col-6 mb-2 mb-md-0">
					<select class="form-control form-control-sm" id="shiftMasterFilterDept">
						<option value="">Todas las áreas</option>
						@php
							$allListDepts = array_values(array_unique(array_filter(array_column($shiftListViewData['shifts'] ?? [], 'department'))));
							sort($allListDepts);
						@endphp
						@foreach($allListDepts as $ld)
							<option value="{{ $ld }}">{{ $ld }}</option>
						@endforeach
					</select>
				</div>

				<div class="col-md-3 col-6 mb-2 mb-md-0">
					<select class="form-control form-control-sm" id="shiftMasterFilterType">
						<option value="">Todos los tipos</option>
						<option value="APERTURA">Apertura (7A)</option>
						<option value="CIERRE">Cierre (7C)</option>
						<option value="NOCHE">Noche (7N)</option>
						<option value="COMPENSATORIO">Compensatorio</option>
						<option value="LIBRE">Libre</option>
						<option value="VACACIONES">Vacaciones</option>
					</select>
				</div>

				<div class="col-md-3 col-12 text-md-right">
					<button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetShiftListFilters">
						<i class="fa-solid fa-arrow-rotate-left mr-1"></i>Limpiar filtros
					</button>
				</div>
			</div>
		</div>

		<!-- Main Shifts Table -->
		<div class="table-responsive">
			<table class="table table-sm table-hover table-striped mb-0" id="tableShiftMasterList" style="font-size: 0.82rem;">
				<thead class="thead-light">
					<tr>
						<th>Fecha</th>
						<th>Colaborador</th>
						<th>Código Nómina</th>
						<th>Sucursal</th>
						<th>Área</th>
						<th class="text-center">Código SAP</th>
						<th>Tipo de Turno</th>
						<th class="text-center">Entrada</th>
						<th class="text-center">Salida</th>
						<th class="text-center">Descanso</th>
						<th class="text-center">Horas Netas</th>
						<th class="text-center">Estado</th>
						<th class="text-center" style="width: 140px;">Acciones</th>
					</tr>
				</thead>
				<tbody id="tbodyShiftMasterList">
					@forelse($shiftListViewData['shifts'] as $s)
						<tr class="shift-master-row" 
							data-name="{{ strtolower($s['collaborator_name']) }}" 
							data-code="{{ strtolower($s['payroll_code']) }}" 
							data-dept="{{ $s['department'] }}" 
							data-type="{{ $s['shift_type'] }}">
							<td class="font-weight-bold text-dark whitespace-nowrap">
								{{ date('d/m/Y', strtotime($s['shift_date'])) }}
								<span class="text-muted small d-block font-weight-normal">{{ strftime('%A', strtotime($s['shift_date'])) }}</span>
							</td>
							<td class="font-weight-bold">
								<a href="#" class="text-dark btn-view-emp-dossier" data-payroll="{{ $s['payroll_code'] }}">
									{{ $s['collaborator_name'] }}
								</a>
							</td>
							<td class="text-muted font-mono">{{ str_replace('DEMO-', 'EJ-', $s['payroll_code']) }}</td>
							<td><span class="badge badge-light border text-muted">{{ $s['branch_name'] }}</span></td>
							<td>
								<span class="badge badge-light border" style="font-weight: 500;">
									{{ $s['department'] }}
								</span>
							</td>
							<td class="text-center">
								@php
									$sc = $s['shift_code'];
									$badgeClass = 'badge-success';
									if (strpos($sc, '7C') === 0) $badgeClass = 'badge-warning text-dark';
									elseif (strpos($sc, '7N') === 0) $badgeClass = 'badge-dark';
									elseif ($sc === 'LIBRE') $badgeClass = 'badge-secondary';
									elseif ($sc === 'COMP') $badgeClass = 'badge-secondary';
									elseif ($sc === 'VC') $badgeClass = 'badge-purple';
								@endphp
								<span class="badge {{ $badgeClass }} px-2 py-1 font-weight-bold">
									{{ $sc }}
								</span>
							</td>
							<td>
								<span class="small font-weight-bold">
									@if($s['shift_type'] === 'APERTURA') <i class="fa-solid fa-sun text-warning mr-1"></i>Apertura
									@elseif($s['shift_type'] === 'CIERRE') <i class="fa-solid fa-cloud-sun text-primary mr-1"></i>Cierre
									@elseif($s['shift_type'] === 'NOCHE') <i class="fa-solid fa-moon text-dark mr-1"></i>Noche
									@elseif($s['shift_type'] === 'COMPENSATORIO') <i class="fa-solid fa-calendar-check text-info mr-1"></i>Compensatorio
									@elseif($s['shift_type'] === 'VACACIONES') <i class="fa-solid fa-umbrella-beach text-purple mr-1"></i>Vacaciones
									@else <i class="fa-solid fa-bed text-muted mr-1"></i>Libre
									@endif
								</span>
							</td>
							<td class="text-center">{{ $s['start_time'] ?: '—' }}</td>
							<td class="text-center">{{ $s['end_time'] ?: '—' }}</td>
							<td class="text-center text-muted">{{ $s['hours'] > 0 ? '60 min' : '—' }}</td>
							<td class="text-center font-weight-bold @if($s['hours'] > 0) text-primary @else text-muted @endif">
								{{ $s['hours'] }} h
							</td>
							<td class="text-center">
								<span class="badge badge-light text-success border border-success">
									<i class="fa-solid fa-check mr-1"></i>{{ ucfirst($s['status']) }}
								</span>
							</td>
							<td class="text-center whitespace-nowrap">
								<button type="button" class="btn btn-xs btn-outline-primary btn-list-edit-shift" 
									data-payroll="{{ $s['payroll_code'] }}" 
									data-date="{{ $s['shift_date'] }}" 
									data-shift="{{ $s['shift_code'] }}" 
									data-name="{{ $s['collaborator_name'] }}"
									title="Editar turno">
									<i class="fa-solid fa-pen"></i>
								</button>

								<button type="button" class="btn btn-xs btn-outline-secondary btn-list-duplicate-shift" 
									data-payroll="{{ $s['payroll_code'] }}" 
									data-date="{{ $s['shift_date'] }}" 
									data-shift="{{ $s['shift_code'] }}"
									title="Duplicar turno al día siguiente">
									<i class="fa-solid fa-copy"></i>
								</button>

								<button type="button" class="btn btn-xs btn-outline-danger btn-list-delete-shift" 
									data-payroll="{{ $s['payroll_code'] }}" 
									data-date="{{ $s['shift_date'] }}" 
									data-name="{{ $s['collaborator_name'] }}"
									title="Anular turno">
									<i class="fa-solid fa-trash-can"></i>
								</button>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="13" class="text-center py-4 text-muted">
								<i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i> No se encontraron turnos con los filtros seleccionados.
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<!-- Footer / Pagination bar -->
		<div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center flex-wrap" style="font-size: 0.8rem;">
			<span class="text-muted">
				Mostrando <strong>{{ count($shiftListViewData['shifts']) }}</strong> de <strong>{{ $shiftListViewData['total_count'] }}</strong> turnos registrados.
			</span>
			<div class="btn-group btn-group-sm">
				<button type="button" class="btn btn-outline-secondary" disabled><i class="fa-solid fa-chevron-left"></i> Anterior</button>
				<button type="button" class="btn btn-outline-secondary active">1</button>
				<button type="button" class="btn btn-outline-secondary" disabled>Siguiente <i class="fa-solid fa-chevron-right"></i></button>
			</div>
		</div>
	</div>
</div>
