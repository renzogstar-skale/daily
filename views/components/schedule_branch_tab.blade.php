{{-- DAILY: Vista Por Sucursal — Gestión Multisucursal --}}
<div id="schViewBranch" class="sch-view-container" style="display: none;">
	<!-- Branch View Header & Filter Bar -->
	<div class="sch-card mb-3">
		<div class="sch-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h3 class="sch-card-title mb-0">
					<i class="fa-solid fa-building text-info mr-2"></i>Gestión Multisucursal de Horarios y Cobertura
				</h3>
				<p class="text-muted small mb-0 mt-1">
					Monitoreo comparativo de dotación, horas asignadas, productividad (SPLH), cobertura y detección de doble programación entre sedes.
				</p>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<span class="badge badge-light border text-muted px-2 py-1">
					<i class="fa-solid fa-code-compare mr-1"></i> 8 Sucursales en Red
				</span>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary active" id="btnFilterBranchAll">Todas (8)</button>
					<button type="button" class="btn btn-outline-secondary" id="btnFilterBranchReal">Real (Matriz)</button>
					<button type="button" class="btn btn-outline-secondary" id="btnFilterBranchDemo">Escenario de ejemplo (7)</button>
				</div>
			</div>
		</div>

		<!-- Cross-Branch Conflict Banner -->
		@if(!empty($branchViewData['cross_branch_conflicts']))
			<div class="alert alert-danger mx-3 mt-3 mb-1 d-flex align-items-center">
				<i class="fa-solid fa-triangle-exclamation fa-2x mr-3 text-danger"></i>
				<div>
					<strong>¡Alerta de doble programación entre sucursales detectada!</strong>
					<div class="small">
						@foreach($branchViewData['cross_branch_conflicts'] as $conf)
							Colaborador <strong>{{ $conf['name'] }}</strong> programado simultáneamente en Sucursal #{{ $conf['b1'] }} y #{{ $conf['b2'] }} el día {{ $conf['shift_date'] }}.
						@endforeach
					</div>
				</div>
			</div>
		@else
			<div class="mx-3 mt-2 mb-1 py-1 px-2 rounded bg-light border text-muted small d-flex align-items-center">
				<i class="fa-solid fa-circle-check text-success mr-2"></i>
				<span><strong>Integridad multisucursal verificada:</strong> Cero colaboradores con conflicto de programación simultánea entre sucursales en la semana.</span>
			</div>
		@endif

		<!-- Scorecards Grid for Branches -->
		<div class="p-3">
			<div class="row">
				@foreach($branchViewData['branches'] as $b)
					<div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3 branch-card-col" data-is-demo="{{ $b['is_demo'] ? '1' : '0' }}">
						<div class="card h-100 border @if($b['id'] == $branchId) border-primary shadow-sm @endif" style="border-radius: 8px; transition: transform 0.15s ease, box-shadow 0.15s ease;">
							<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
								<div>
									<h6 class="mb-0 font-weight-bold @if($b['id'] == $branchId) text-primary @else text-dark @endif">
										{{ $b['name'] }}
									</h6>
									<span class="text-muted" style="font-size: 0.72rem;">Código: {{ str_replace('DEMO-', 'EJ-', $b['code']) }}</span>
								</div>
								<div>
									@if(!$b['is_demo'])
										<span class="badge badge-success px-2 py-1" title="Sucursal operativa real con nómina activa">
											<i class="fa-solid fa-check-circle mr-1"></i>REAL
										</span>
									@else
										<span class="badge badge-secondary px-2 py-1" style="background-color: #64748b;" title="Sucursal con escenario de ejemplo para simular horarios">
											<i class="fa-solid fa-vial mr-1"></i>Ejemplo
										</span>
									@endif
								</div>
							</div>

							<div class="card-body p-3">
								<div class="d-flex justify-content-between align-items-baseline mb-2">
									<span class="text-muted small">Personal asignado:</span>
									<span class="font-weight-bold text-dark">{{ $b['staff_count'] }} colaboradores</span>
								</div>

								<div class="d-flex justify-content-between align-items-baseline mb-2">
									<span class="text-muted small">Horas programadas:</span>
									<span class="font-weight-bold text-dark">{{ number_format($b['scheduled_hours'], 1) }} h</span>
								</div>

								<div class="d-flex justify-content-between align-items-baseline mb-2">
									<span class="text-muted small">Cobertura de demanda:</span>
									<span class="font-weight-bold @if($b['coverage_pct'] >= 100) text-success @elseif($b['coverage_pct'] >= 90) text-primary @else text-danger @endif">
										{{ $b['coverage_pct'] }}%
									</span>
								</div>

								<div class="progress mb-2" style="height: 5px;">
									<div class="progress-bar @if($b['coverage_pct'] >= 100) bg-success @elseif($b['coverage_pct'] >= 90) bg-primary @else bg-danger @endif" 
										 role="progressbar" style="width: {{ min(100, $b['coverage_pct']) }}%;"></div>
								</div>

								<div class="d-flex justify-content-between align-items-baseline mb-1">
									<span class="text-muted small">SPLH (Venta/hora):</span>
									<span class="font-weight-bold text-dark" style="font-size: 0.85rem;">{{ $b['splh'] }}</span>
								</div>

								<div class="d-flex justify-content-between align-items-baseline mb-2">
									<span class="text-muted small">Costo proyectado:</span>
									<span class="text-dark small">{{ $b['scheduled_cost'] }} <span class="text-muted font-weight-normal">/ {{ $b['budget'] }}</span></span>
								</div>

								<div class="d-flex justify-content-between align-items-center pt-2 border-top">
									<span class="badge @if($b['risk_status'] === 'Óptimo') badge-light text-success border border-success @else badge-light text-warning border border-warning @endif">
										<i class="fa-solid fa-circle mr-1" style="font-size: 0.5rem;"></i>{{ $b['risk_status'] }}
									</span>
									@if($b['id'] == $branchId)
										<button type="button" class="btn btn-xs btn-primary font-weight-bold" disabled>
											<i class="fa-solid fa-eye mr-1"></i>Activa
										</button>
									@else
										<button type="button" class="btn btn-xs btn-outline-primary btn-select-branch-action font-weight-bold" data-branch-id="{{ $b['id'] }}">
											<i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Gestionar
										</button>
									@endif
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Comparative Table -->
		<div class="card-footer bg-white border-top p-3">
			<h6 class="font-weight-bold mb-2 text-dark"><i class="fa-solid fa-table-list mr-2 text-muted"></i>Matriz comparativa de rendimiento multisucursal</h6>
			<div class="table-responsive">
				<table class="table table-sm table-hover table-bordered mb-0" style="font-size: 0.82rem;">
					<thead class="thead-light">
						<tr>
							<th>Sucursal</th>
							<th>Tipo</th>
							<th class="text-center">Plantilla</th>
							<th class="text-center">Horas Semanales</th>
							<th class="text-center">Cobertura</th>
							<th class="text-center">SPLH</th>
							<th class="text-center">Costo Laboral</th>
							<th class="text-center">Horas Extra</th>
							<th class="text-center">Estado</th>
							<th class="text-center">Acción</th>
						</tr>
					</thead>
					<tbody>
						@foreach($branchViewData['branches'] as $b)
							<tr class="@if($b['id'] == $branchId) table-primary @endif">
								<td class="font-weight-bold">
									<i class="fa-solid fa-shop text-muted mr-1"></i> {{ $b['name'] }}
								</td>
								<td>
									@if(!$b['is_demo'])
										<span class="badge badge-success">REAL (Matriz)</span>
									@else
										<span class="badge badge-secondary" style="background-color: #64748b;">Ejemplo</span>
									@endif
								</td>
								<td class="text-center font-weight-bold">{{ $b['staff_count'] }}</td>
								<td class="text-center">{{ number_format($b['scheduled_hours'], 1) }} h</td>
								<td class="text-center font-weight-bold @if($b['coverage_pct'] >= 100) text-success @elseif($b['coverage_pct'] >= 90) text-primary @else text-danger @endif">
									{{ $b['coverage_pct'] }}%
								</td>
								<td class="text-center font-weight-600">{{ $b['splh'] }}</td>
								<td class="text-center">{{ $b['scheduled_cost'] }}</td>
								<td class="text-center @if($b['overtime_hours'] > 0) text-warning font-weight-bold @else text-muted @endif">
									{{ $b['overtime_hours'] > 0 ? $b['overtime_hours'] . ' h' : '0 h' }}
								</td>
								<td class="text-center">
									<span class="badge @if($b['risk_status'] === 'Óptimo') badge-success @else badge-warning @endif">
										{{ $b['risk_status'] }}
									</span>
								</td>
								<td class="text-center">
									@if($b['id'] == $branchId)
										<span class="badge badge-primary py-1 px-2"><i class="fa-solid fa-check mr-1"></i>Seleccionada</span>
									@else
										<button type="button" class="btn btn-xs btn-outline-primary btn-select-branch-action py-0 px-2" data-branch-id="{{ $b['id'] }}">
											Seleccionar
										</button>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
