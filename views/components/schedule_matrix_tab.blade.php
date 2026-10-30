{{-- DAILY: Vista Matriz de Cobertura en Intervalos de 30 Minutos (Requisito 7 & 8) --}}
<div id="schViewMatrix" class="sch-view-container" style="display: none;">
	<div class="sch-card mb-3">
		<div class="sch-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h3 class="sch-card-title mb-0">
					<i class="fa-solid fa-table-cells text-success mr-2"></i>Matriz de Cobertura Operativa en Intervalos de 30 Minutos
				</h3>
				<p class="text-muted small mb-0 mt-1">
					Distribución precisa de personal cada 30 minutos: disponible, programado, en descanso/pausa y trabajando efectivamente vs. requerimiento estándar editable.
				</p>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<span class="badge badge-light border text-muted px-2 py-1">
					<i class="fa-solid fa-calendar mr-1 text-primary"></i> <span id="matrixDisplayDate">{{ date('d/m/Y', strtotime($currentMatrixDate ?? '2026-11-04')) }}</span>
				</span>
				<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" onclick="window.onMatrixParamsChanged()">
					<i class="fa-solid fa-arrows-rotate mr-1"></i> Recalcular Matriz
				</button>
			</div>
		</div>

		<!-- Control Toolbar for 30-Min Matrix -->
		<div class="p-3 border-bottom bg-light">
			<div class="row align-items-center g-2">
				<!-- Selector de Fecha -->
				<div class="col-lg-3 col-md-4 col-12 mb-2 mb-md-0">
					<label class="small font-weight-bold text-muted mb-1 d-block"><i class="fa-solid fa-calendar-day mr-1"></i>Fecha de Operación:</label>
					<input type="date" class="form-control form-control-sm font-weight-600" id="matrixDatePicker" value="{{ $currentMatrixDate ?? '2026-11-04' }}" min="2026-10-26" max="2026-12-06" onchange="window.onMatrixParamsChanged()">
				</div>

				<!-- Selector de Área / Departamento -->
				<div class="col-lg-3 col-md-4 col-12 mb-2 mb-md-0">
					<label class="small font-weight-bold text-muted mb-1 d-block"><i class="fa-solid fa-layer-group mr-1"></i>Departamento / Área:</label>
					<select class="form-control form-control-sm font-weight-600" id="matrixDeptSelector" onchange="window.onMatrixParamsChanged()">
						<option value="all" @if(($currentMatrixDept ?? 'all') === 'all') selected @endif>Todas las áreas (Consolidado)</option>
						@foreach($demoAreas as $aName => $aConf)
							<option value="{{ $aName }}" @if(($currentMatrixDept ?? '') === $aName) selected @endif>{{ $aName }} ({{ $aConf['target_staff'] }} personas)</option>
						@endforeach
					</select>
				</div>

				<!-- Selector de Escenario de Demanda Simulado -->
				<div class="col-lg-4 col-md-4 col-12 mb-2 mb-md-0">
					<label class="small font-weight-bold text-muted mb-1 d-block"><i class="fa-solid fa-chart-line mr-1 text-info"></i>Escenario de Demanda:</label>
					<select class="form-control form-control-sm font-weight-600 border-info" id="matrixScenarioSelector" onchange="window.onMatrixParamsChanged()">
						@foreach($simulatedScenarios as $scKey => $scLabel)
							<option value="{{ $scKey }}" @if(($currentDemandScenario ?? 'normal') === $scKey) selected @endif>{{ $scLabel }}</option>
						@endforeach
					</select>
				</div>

				<!-- Quick Filter Buttons -->
				<div class="col-lg-2 col-12 text-lg-right">
					<label class="small font-weight-bold text-muted mb-1 d-block">Filtrar tabla:</label>
					<div class="btn-group btn-group-sm w-100" role="group">
						<button type="button" class="btn btn-outline-secondary active btn-sm" id="btnMatrixFilterAll" onclick="window.filterMatrixRows('all')">Todos</button>
						<button type="button" class="btn btn-outline-danger btn-sm" id="btnMatrixFilterDeficit" onclick="window.filterMatrixRows('deficit')">Déficit</button>
						<button type="button" class="btn btn-outline-warning btn-sm" id="btnMatrixFilterBreak" onclick="window.filterMatrixRows('break')">Pausas</button>
					</div>
				</div>
			</div>
		</div>

		<!-- 4 Summary Stat Badges -->
		@php
			$matSummary = $coverageMatrix30Min['summary'] ?? [];
			$compPct = $matSummary['coverage_compliance_pct'] ?? null;
			$uncovered = $matSummary['uncovered_slots'] ?? 1;
			$defHours = $matSummary['total_deficit_hours'] ?? 0.5;
			$surHours = $matSummary['total_surplus_hours'] ?? 4.0;
		@endphp
		<div class="p-3 bg-white border-bottom">
			<div class="row text-center g-2" id="matrixKpiRow">
				<div class="col-lg-3 col-6 mb-2 mb-lg-0">
					<div class="p-2 rounded border bg-light">
						<span class="text-muted small d-block">Cumplimiento Cobertura</span>
						<span class="font-weight-bold" style="font-size: 1.25rem; color: @if($compPct === null) #64748b @elseif($compPct >= 95) #059669 @else #ef4444 @endif;" id="matrixKpiCompliance">
							{{ $compPct === null ? 'Sin información' : $compPct . '%' }}
						</span>
						<span class="text-muted d-block" style="font-size: 0.7rem;">Franjas cubiertas / total</span>
					</div>
				</div>
				<div class="col-lg-3 col-6 mb-2 mb-lg-0">
					<div class="p-2 rounded border bg-light">
						<span class="text-muted small d-block">Franjas Descubiertas</span>
						<span class="font-weight-bold @if($uncovered > 0) text-danger @else text-success @endif" style="font-size: 1.25rem;" id="matrixKpiUncovered">
							{{ $uncovered }} franjas
						</span>
						<span class="text-muted d-block" style="font-size: 0.7rem;">Con déficit de personal</span>
					</div>
				</div>
				<div class="col-lg-3 col-6 mb-2 mb-lg-0">
					<div class="p-2 rounded border bg-light">
						<span class="text-muted small d-block">Déficit Acumulado</span>
						<span class="font-weight-bold text-danger" style="font-size: 1.25rem;" id="matrixKpiDeficitHours">
							{{ $defHours }} h
						</span>
						<span class="text-muted d-block" style="font-size: 0.7rem;">Horas-hombre faltantes</span>
					</div>
				</div>
				<div class="col-lg-3 col-6 mb-2 mb-lg-0">
					<div class="p-2 rounded border bg-light">
						<span class="text-muted small d-block">Holgura / Capacidad Extra</span>
						<span class="font-weight-bold text-success" style="font-size: 1.25rem;" id="matrixKpiSurplusHours">
							+{{ $surHours }} h
						</span>
						<span class="text-muted d-block" style="font-size: 0.7rem;">Disponible para absorción</span>
					</div>
				</div>
			</div>
		</div>

		<!-- The Interactive 30-Minute Interval Table -->
		<div class="table-responsive p-0" style="max-height: 600px; overflow-y: auto;">
			<table class="table table-hover table-sm mb-0 text-center" id="tableCoverageMatrix30Min" style="font-size: 0.82rem;">
				<thead class="bg-light text-dark sticky-top" style="z-index: 5;">
					<tr>
						<th style="width: 120px;" class="text-left pl-3">Franja (30 min)</th>
						<th style="width: 90px;" title="Total personal activo en la plantilla que no está de baja ni vacaciones">Disponibles</th>
						<th style="width: 90px;" title="Colaboradores asignados a un turno en esta hora">Programados</th>
						<th style="width: 100px;" title="Colaboradores en descanso/almuerzo durante esta franja">En Pausa <i class="fa-solid fa-mug-saucer text-warning"></i></th>
						<th style="width: 120px;" title="Colaboradores en piso trabajando efectivamente (Programados - En Pausa)">Efectivo Trabajando</th>
						<th style="width: 120px;" title="Dotación requerida según demanda. Haga clic para editar y simular">Requerido <i class="fa-solid fa-pen-to-square text-muted"></i></th>
						<th style="width: 90px;">Balance</th>
						<th style="width: 100px;">Estado</th>
						<th class="text-left pl-3">Personal en Piso / En Pausa</th>
					</tr>
				</thead>
				<tbody id="matrixTableBody">
					@foreach($coverageMatrix30Min['intervals'] as $idx => $slot)
						@php
							$isDeficit = ($slot['balance'] < 0);
							$hasBreak = ($slot['on_break'] > 0);
							$rowClass = $isDeficit ? 'table-danger' : ($hasBreak ? 'bg-amber-light' : '');
						@endphp
						<tr class="matrix-row {{ $rowClass }}" data-status="{{ $slot['status'] }}" data-has-break="{{ $hasBreak ? '1' : '0' }}">
							<td class="text-left pl-3 font-weight-bold">
								<span class="badge badge-light border text-dark">{{ $slot['slot_label'] }}</span>
							</td>
							<td>
								<span class="font-weight-600 text-muted">{{ $slot['available'] }}</span>
							</td>
							<td>
								<span class="font-weight-600 text-primary">{{ $slot['scheduled'] }}</span>
							</td>
							<td>
								@if($slot['on_break'] > 0)
									<span class="badge badge-warning px-2 font-weight-bold" title="{{ implode(', ', $slot['break_workers']) }}">
										<i class="fa-solid fa-mug-saucer mr-1"></i>{{ $slot['on_break'] }}
									</span>
								@else
									<span class="text-muted">0</span>
								@endif
							</td>
							<td>
								<span class="badge @if($isDeficit) badge-danger @else badge-success @endif px-2 font-weight-bold" style="font-size: 0.82rem;">
									{{ $slot['working'] }} personas
								</span>
							</td>
							<td>
								<div class="input-group input-group-sm d-inline-flex justify-content-center" style="width: 80px;">
									<input type="number" class="form-control form-control-sm text-center font-weight-bold matrix-req-input" 
										   value="{{ $slot['required'] }}" min="0" max="25"
										   data-slot-idx="{{ $idx }}" data-working="{{ $slot['working'] }}"
										   onchange="window.updateMatrixSlotRequirement(this, {{ $idx }})">
								</div>
							</td>
							<td>
								<strong class="@if($slot['balance'] < 0) text-danger @elseif($slot['balance'] === 0) text-success @else text-primary @endif">
									{{ $slot['balance'] > 0 ? '+' : '' }}{{ $slot['balance'] }}
								</strong>
							</td>
							<td>
								@if($slot['status'] === 'deficit')
									<span class="badge badge-danger px-2"><i class="fa-solid fa-triangle-exclamation mr-1"></i>DÉFICIT</span>
								@elseif($slot['status'] === 'optimal')
									<span class="badge badge-success px-2"><i class="fa-solid fa-check mr-1"></i>ÓPTIMO</span>
								@else
									<span class="badge badge-info px-2">+{{ $slot['balance'] }} HOLGURA</span>
								@endif
							</td>
							<td class="text-left pl-3">
								<div class="d-flex flex-wrap align-items-center gap-1">
									@foreach($slot['workers'] as $wName)
										<span class="badge badge-light border text-dark" style="font-size: 0.72rem;">
											<i class="fa-solid fa-user text-success mr-1"></i>{{ $wName }}
										</span>
									@endforeach
									@foreach($slot['break_workers'] as $bName)
										<span class="badge badge-warning text-dark border-warning" style="font-size: 0.72rem;" title="En colación/descanso reglamentario">
											<i class="fa-solid fa-mug-saucer mr-1"></i>{{ $bName }} (pausa)
										</span>
									@endforeach
									@if(empty($slot['workers']) && empty($slot['break_workers']))
										<span class="text-muted font-italic" style="font-size: 0.72rem;">Sin personal programado</span>
									@endif
								</div>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<!-- Footer Explanations & Standard Times -->
		<div class="card-footer bg-white border-top p-3">
			<div class="row align-items-center">
				<div class="col-lg-8 col-12">
					<h6 class="font-weight-bold text-dark mb-1">
						<i class="fa-solid fa-calculator text-primary mr-2"></i>Cálculo de Dotación Efectiva y Tiempos Estándar
					</h6>
					<p class="text-muted small mb-0">
						La dotación requerida no se calcula dividiendo ventas entre empleados de forma plana. Integra:
						<strong>Recepción y FEFO</strong> (1.8 min/unidad), <strong>Reposición</strong> (2.5 min/caja), 
						<strong>Atención en Caja</strong> (3.5 min/transacción), <strong>Picking en Bodega</strong> (4.0 min/pedido) y 
						<strong>Pausas no remuneradas</strong> (1 hora en turnos de 9h de presencia = 8h efectivas).
					</p>
				</div>
				<div class="col-lg-4 col-12 text-lg-right mt-2 mt-lg-0">
					<button type="button" class="btn btn-sm btn-outline-info font-weight-bold" data-toggle="modal" data-target="#modalSimulateScenario">
						<i class="fa-solid fa-sliders mr-1"></i>Simulador What-If
					</button>
					<button type="button" class="btn btn-sm btn-outline-danger font-weight-bold ml-1" id="btnRestoreDemoFromMatrix" onclick="window.triggerRestoreDemo()">
						<i class="fa-solid fa-rotate-left mr-1"></i>Restaurar escenario de ejemplo
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.bg-amber-light {
	background-color: #fffbeb !important;
}
.gap-1 {
	gap: 0.25rem;
}
.gap-2 {
	gap: 0.5rem;
}
</style>
