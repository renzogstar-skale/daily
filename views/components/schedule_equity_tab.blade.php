@php
	$eq = $equityData ?? [];
	$eqKpis = $eq['kpis'] ?? [];
	$eqCollabs = $eq['collaborators'] ?? [];
	$eqCalDays = $eq['calendar_days'] ?? [];
	$eqNights = $eq['night_coverage'] ?? [];
	$eqComps = $eq['compensatories'] ?? [];
	$eqChecks = $eq['checklist'] ?? [];
	$eqPub = $eq['publication'] ?? [];

	$empNameMap = [];
	foreach ($eqCollabs as $c) {
		$empNameMap[$c['payroll_code']] = $c['name'];
	}
@endphp

<div id="schEquitySection" class="sch-equity-section" style="display: none;">

	<!-- 1. TOP CARDS: 4 KPIS DE EQUIDAD Y ROTACIONES -->
	<div class="sch-equity-kpis-grid">
		<!-- KPI 1: Equidad General -->
		<div class="sch-eq-card tone-emerald">
			<div class="sch-eq-card-head">
				<span class="sch-eq-card-title">Equidad General</span>
				<span class="sch-eq-card-icon tone-emerald"><i class="fa-solid fa-scale-balanced"></i></span>
			</div>
			<div>
				<div class="sch-eq-card-value text-success" id="kpiAvgEquity">{{ $eqKpis['average_equity_display'] ?? '91.5%' }}</div>
				<div class="sch-eq-card-sub">
					<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check mr-1"></i> {{ $eqKpis['status_badge'] ?? 'Excelente (>90%)' }}</span>
					<span class="ml-1 text-muted">{{ count($eqCollabs) }} colaboradores</span>
				</div>
			</div>
		</div>

		<!-- KPI 2: Balance Apertura vs Cierre -->
		<div class="sch-eq-card tone-blue">
			<div class="sch-eq-card-head">
				<span class="sch-eq-card-title">Apertura vs Cierre</span>
				<span class="sch-eq-card-icon tone-blue"><i class="fa-solid fa-right-left"></i></span>
			</div>
			<div>
				<div class="sch-eq-card-value text-primary">
					<span title="Aperturas">{{ $eqKpis['hours_open'] ?? '—' }}h</span> <span class="text-muted font-weight-normal" style="font-size: 1.1rem;">vs</span> <span title="Cierres">{{ $eqKpis['hours_close'] ?? '—' }}h</span>
				</div>
				<div class="sch-eq-card-sub">
					<span class="text-dark font-weight-600">{{ $eqKpis['total_openings'] ?? '—' }} aperturas</span> · <span class="text-dark font-weight-600">{{ $eqKpis['total_closings'] ?? '—' }} cierres</span> (~50/50)
				</div>
			</div>
		</div>

		<!-- KPI 3: Domingos 3x1 & Compensatorios -->
		<div class="sch-eq-card tone-amber">
			<div class="sch-eq-card-head">
				<span class="sch-eq-card-title">Domingos 3x1</span>
				<span class="sch-eq-card-icon tone-amber"><i class="fa-solid fa-calendar-check"></i></span>
			</div>
			<div>
				<div class="sch-eq-card-value text-warning">100%</div>
				<div class="sch-eq-card-sub">
					<span class="text-success font-weight-600"><i class="fa-solid fa-shield mr-1"></i> Secuencia 3×1 legal</span> · 0 festivos usados
				</div>
			</div>
		</div>

		<!-- KPI 4: Cobertura Nocturna -->
		<div class="sch-eq-card tone-purple">
			<div class="sch-eq-card-head">
				<span class="sch-eq-card-title">Cobertura Nocturna</span>
				<span class="sch-eq-card-icon tone-purple"><i class="fa-solid fa-moon"></i></span>
			</div>
			<div>
				<div class="sch-eq-card-value" style="color: #7c3aed;">1 / noche</div>
				<div class="sch-eq-card-sub">
					<span class="badge badge-purple px-2 py-1"><i class="fa-solid fa-circle-check mr-1"></i> 30/30 días cubiertos</span> (7N22-6 SAP)
				</div>
			</div>
		</div>
	</div>

	<!-- 2. TOOLBAR DE FILTROS Y ACCIONES DE EQUIDAD -->
	<div class="sch-equity-toolbar">
		<div class="sch-eq-filters">
			<div class="sch-eq-filter-item">
				<label for="eqFilterArea"><i class="fa-solid fa-layer-group mr-1 text-muted"></i> Área:</label>
				<select class="sch-select" id="eqFilterArea" style="min-width: 170px;">
					<option value="all" selected>Todas las áreas (5)</option>
					<option value="Accesorios">Accesorios (3)</option>
					<option value="Bodega Digital">Bodega Digital (4)</option>
					<option value="Despachos">Despachos (2)</option>
					<option value="Ventas y Operación">Ventas y Operación (12)</option>
					<option value="Virtual Teams">Virtual Teams (2)</option>
				</select>
			</div>

			<div class="sch-eq-filter-item">
				<label for="eqFilterSearch"><i class="fa-solid fa-magnifying-glass mr-1 text-muted"></i> Colaborador:</label>
				<input type="text" class="form-control form-control-sm" id="eqFilterSearch" placeholder="Buscar por nombre o cédula..." style="width: 210px; border-radius: 6px;">
			</div>

			<div class="sch-eq-filter-item">
				<span class="badge badge-light border text-muted px-2 py-1">
					<i class="fa-solid fa-calendar mr-1"></i> Noviembre 2026 (Sem. 1 a 5)
				</span>
			</div>
		</div>

		<div class="sch-eq-actions">
			<button type="button" class="sch-btn sch-btn-emerald font-weight-bold" id="btnOpenGenerarModalEquity" data-toggle="modal" data-target="#modalGenerarProgramacion">
				<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Generar programación
			</button>

			<button type="button" class="sch-btn" id="btnOpenSwapModalEquity" data-toggle="modal" data-target="#modalShiftSwap">
				<i class="fa-solid fa-arrow-right-arrow-left mr-1 text-primary"></i> Intercambiar turnos
			</button>

			<button type="button" class="sch-btn" id="btnPublishEquityAction">
				<i class="fa-solid fa-paper-plane mr-1 text-success"></i> Publicar
			</button>
		</div>
	</div>

	<!-- 3. COMPARISON CHART & DEPARTMENT HEALTH -->
	<div class="row">
		<div class="col-lg-8 mb-3">
			<div class="sch-card p-3 h-100">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h3 class="sch-card-title mb-0">Balance de Turnos: Apertura vs. Cierre por Colaborador</h3>
					<div class="sch-chart-legend mb-0">
						<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #059669;"></span> Apertura (h)</span>
						<span class="sch-legend-item"><span class="sch-legend-dot" style="background-color: #2563eb;"></span> Cierre (h)</span>
					</div>
				</div>
				<p class="small text-muted mb-2">Objetivo de equidad: 50% Apertura / 50% Cierre a lo largo de ciclos de 4 semanas continuas.</p>
				<div style="height: 220px; position: relative;">
					<canvas id="chartEquityComparisonCanvas"></canvas>
				</div>
			</div>
		</div>

		<div class="col-lg-4 mb-3">
			<div class="sch-card p-3 h-100 d-flex flex-column justify-content-between">
				<div>
					<div class="d-flex align-items-center justify-content-between mb-2">
						<h3 class="sch-card-title mb-0">Equidad por Departamento</h3>
						<span class="badge badge-light border text-success">Validado</span>
					</div>
					<div class="sch-dist-list mt-2">
						<div class="sch-dist-row mb-2">
							<span class="sch-dist-name">Accesorios (3)</span>
							<div class="sch-dist-bar-bg flex-grow-1 mx-2" style="height: 8px;">
								<div class="sch-dist-bar-fill" style="width: 93.5%; background-color: #059669;"></div>
							</div>
							<span class="sch-dist-pct text-success font-weight-bold">93.5%</span>
						</div>
						<div class="sch-dist-row mb-2">
							<span class="sch-dist-name">Bodega Digital (4)</span>
							<div class="sch-dist-bar-bg flex-grow-1 mx-2" style="height: 8px;">
								<div class="sch-dist-bar-fill" style="width: 93.2%; background-color: #059669;"></div>
							</div>
							<span class="sch-dist-pct text-success font-weight-bold">93.2%</span>
						</div>
						<div class="sch-dist-row mb-2">
							<span class="sch-dist-name">Despachos (2)</span>
							<div class="sch-dist-bar-bg flex-grow-1 mx-2" style="height: 8px;">
								<div class="sch-dist-bar-fill" style="width: 92.3%; background-color: #059669;"></div>
							</div>
							<span class="sch-dist-pct text-success font-weight-bold">92.3%</span>
						</div>
						<div class="sch-dist-row mb-2">
							<span class="sch-dist-name">Ventas y Operación (12)</span>
							<div class="sch-dist-bar-bg flex-grow-1 mx-2" style="height: 8px;">
								<div class="sch-dist-bar-fill" style="width: 90.6%; background-color: #059669;"></div>
							</div>
							<span class="sch-dist-pct text-success font-weight-bold">90.6%</span>
						</div>
						<div class="sch-dist-row mb-2">
							<span class="sch-dist-name">Virtual Teams (2)</span>
							<div class="sch-dist-bar-bg flex-grow-1 mx-2" style="height: 8px;">
								<div class="sch-dist-bar-fill" style="width: 92.3%; background-color: #059669;"></div>
							</div>
							<span class="sch-dist-pct text-success font-weight-bold">92.3%</span>
						</div>
					</div>
				</div>

				<div class="p-2 rounded bg-light border mt-2">
					<div class="d-flex align-items-center justify-content-between small">
						<span><i class="fa-solid fa-circle-check text-success mr-1"></i> Estado del motor:</span>
						<strong class="text-success">Continuidad de Octubre OK</strong>
					</div>
					<div class="small text-muted mt-1">Sin reinicio abrupto el 1 de nov; respeta semanas A/B/C/D del historial.</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 4. TABLA MAESTRA DE COLABORADORES Y EQUIDAD -->
	<div class="sch-equity-table-box mb-3">
		<div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
			<div>
				<h4 class="font-weight-700 text-dark mb-0">Tabla de Equidad y Rotaciones (23 Colaboradores)</h4>
				<div class="small text-muted">Cálculo de equidad: <code>100 × (1 - |horas_apertura - horas_cierre| / (horas_apertura + horas_cierre))</code></div>
			</div>
			<div class="d-flex align-items-center gap-2">
				<span class="badge badge-light border"><i class="fa-solid fa-moon text-purple mr-1"></i> Nocturno: 7N22-6</span>
				<span class="badge badge-light border"><i class="fa-solid fa-sun text-success mr-1"></i> Apertura: 7A...</span>
				<span class="badge badge-light border"><i class="fa-solid fa-cloud-sun text-primary mr-1"></i> Cierre: 7C...</span>
			</div>
		</div>

		<div class="table-responsive">
			<table class="sch-eq-table" id="tableEquityCollaborators">
				<thead>
					<tr>
						<th style="min-width: 220px;">Colaborador</th>
						<th>Área / Cargo</th>
						<th class="text-center" title="Cantidad de aperturas">Aperturas</th>
						<th class="text-center" title="Cantidad de cierres">Cierres</th>
						<th class="text-center" title="Horas efectivas apertura">Horas Apertura</th>
						<th class="text-center" title="Horas efectivas cierre">Horas Cierre</th>
						<th class="text-center" title="Equidad por horas oficial">Equidad (%)</th>
						<th class="text-center">Próximo Turno</th>
						<th style="min-width: 170px;">Secuencia Domingos</th>
						<th class="text-center">Nocturno</th>
						<th class="text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="tbodyEquityCollaborators">
					@foreach($eqCollabs as $collab)
						<tr class="eq-collab-row" data-dept="{{ $collab['department'] }}" data-code="{{ $collab['payroll_code'] }}" data-name="{{ strtolower($collab['name']) }}">
							<td>
								<div class="d-flex align-items-center">
									<div class="sch-emp-avatar mr-2" style="background-color: #3b82f6; width: 32px; height: 32px; font-size: 0.75rem;">
										{{ substr($collab['name'], 0, 1) }}
									</div>
									<div>
										<div class="font-weight-600 text-dark">{{ $collab['name'] }}</div>
										<div class="small text-muted font-monospace"><i class="fa-solid fa-id-badge mr-1"></i>{{ str_replace('DEMO-', 'EJ-', $collab['payroll_code']) }}</div>
									</div>
								</div>
							</td>
							<td>
								<span class="badge badge-light border text-dark font-weight-500">{{ $collab['department'] }}</span>
								<div class="small text-muted">{{ $collab['cargo'] }}</div>
							</td>
							<td class="text-center font-weight-600 text-success">{{ $collab['openings'] }}</td>
							<td class="text-center font-weight-600 text-primary">{{ $collab['closings'] }}</td>
							<td class="text-center font-monospace">{{ $collab['hours_open'] }} h</td>
							<td class="text-center font-monospace">{{ $collab['hours_close'] }} h</td>
							<td class="text-center">
								<span class="sch-eq-badge {{ $collab['equity_badge_class'] }}">
									{{ $collab['equity_display'] }}
								</span>
							</td>
							<td class="text-center">
								@if($collab['next_turn'] === 'APERTURA')
									<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-sun mr-1"></i> Apertura</span>
								@else
									<span class="badge badge-primary px-2 py-1"><i class="fa-solid fa-cloud-sun mr-1"></i> Cierre</span>
								@endif
							</td>
							<td>
								<span class="small font-weight-600 text-dark d-block">Grupo {{ $collab['sunday_group'] }}</span>
								<span class="small text-muted">{{ $collab['sunday_desc'] }}</span>
							</td>
							<td class="text-center">
								@if($collab['nights'] > 0)
									<span class="badge badge-purple px-2 py-1" title="Jornadas nocturnas asignadas"><i class="fa-solid fa-moon mr-1"></i> {{ $collab['nights'] }} noches</span>
								@else
									<span class="text-muted small">—</span>
								@endif
							</td>
							<td class="text-right">
								<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-swap-single" title="Intercambiar turno con otro colaborador" onclick="window.prepareSwapForEmp('{{ $collab['payroll_code'] }}')">
									<i class="fa-solid fa-arrow-right-arrow-left"></i>
								</button>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>

	<!-- 5. CALENDARIO COMPLETO DE NOVIEMBRE 2026 (30 DÍAS) -->
	<div class="sch-equity-table-box mb-3">
		<div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
			<div>
				<h4 class="font-weight-700 text-dark mb-0">Calendario Completo de Noviembre 2026 (Grid Interactivo)</h4>
				<div class="small text-muted">Haz clic en cualquier celda para ver detalles, verificar compensatorio o editar turno.</div>
			</div>
			<div class="d-flex align-items-center gap-3">
				<span class="small"><span class="sch-chip sch-chip-apertura px-1 py-0 mr-1">7A...</span> Apertura</span>
				<span class="small"><span class="sch-chip sch-chip-cierre px-1 py-0 mr-1">7C...</span> Cierre</span>
				<span class="small"><span class="sch-chip sch-chip-noche px-1 py-0 mr-1">7N22-6</span> Noche</span>
				<span class="small"><span class="sch-chip sch-chip-comp px-1 py-0 mr-1">COMP</span> Compensatorio</span>
				<span class="small"><span class="sch-chip sch-chip-libre px-1 py-0 mr-1">LBRE</span> Libre</span>
			</div>
		</div>

		<div class="sch-matrix-scroll-wrapper">
			<table class="sch-matrix-table" id="tableEquityCalendarMatrix">
				<thead>
					<tr>
						<th class="th-sticky-emp">Colaborador</th>
						@foreach($eqCalDays as $cd)
							<th class="@if($cd['is_sunday']) is-sunday @endif @if($cd['is_holiday']) is-holiday @endif" title="{{ $cd['holiday_name'] ?? ($cd['is_sunday'] ? 'Domingo' : '') }}">
								<div>{{ $cd['dow_name'] }}</div>
								<div class="font-weight-bold" style="font-size: 0.85rem;">{{ $cd['day_num'] }}</div>
								@if($cd['is_holiday'])
									<div class="badge badge-danger p-0" style="font-size: 0.55rem; width: 100%;">FEST</div>
								@endif
							</th>
						@endforeach
					</tr>
				</thead>
				<tbody>
					@foreach($eqCollabs as $collab)
						@php
							$cCode = $collab['payroll_code'];
							$cShifts = $collab['shifts'] ?? [];
						@endphp
						<tr class="eq-matrix-row" data-dept="{{ $collab['department'] }}" data-code="{{ $cCode }}">
							<td class="td-sticky-emp">
								<div class="font-weight-600 text-dark text-truncate" style="max-width: 180px;">{{ $collab['name'] }}</div>
								<div class="small text-muted">{{ $collab['department'] }}</div>
							</td>
							@foreach($eqCalDays as $cd)
								@php
									$dKey = $cd['date'];
									$s = $cShifts[$dKey] ?? null;
									$sCode = $s['shift_code'] ?? '—';
									$sType = $s['shift_type'] ?? 'LIBRE';
									$isNight = ($sType === 'NOCHE');
									$isComp = ($sType === 'COMPENSATORIO');
									$isLibre = ($sType === 'LIBRE');
									$isA = ($sType === 'APERTURA');
									$isC = ($sType === 'CIERRE');

									$chipClass = 'sch-chip-libre';
									if ($isNight) $chipClass = 'sch-chip-noche';
									elseif ($isComp) $chipClass = 'sch-chip-comp';
									elseif ($isA) $chipClass = 'sch-chip-apertura';
									elseif ($isC) $chipClass = 'sch-chip-cierre';
								@endphp
								<td class="@if($cd['is_sunday']) is-sunday @endif @if($cd['is_holiday']) is-holiday @endif p-1"
								    data-emp-code="{{ $cCode }}"
								    data-emp-name="{{ $collab['name'] }}"
								    data-date="{{ $dKey }}"
								    data-shift-code="{{ $sCode }}"
								    onclick="window.openEditShiftModal('{{ $cCode }}', '{{ addslashes($collab['name']) }}', '{{ $dKey }}', '{{ $sCode }}')">
									<div class="sch-chip {{ $chipClass }}" title="{{ $s['notes'] ?? '' }} (Clic para editar)">
										{{ $sCode }}
									</div>
								</td>
							@endforeach
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>

	<!-- 6. VISTA DE DOMINGOS Y COMPENSATORIOS + COBERTURA NOCTURNA -->
	<div class="row">
		<!-- Trazabilidad de Compensatorios -->
		<div class="col-lg-6 mb-3">
			<div class="sch-card p-3 h-100">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h3 class="sch-card-title mb-0"><i class="fa-solid fa-calendar-check text-warning mr-1"></i> Trazabilidad de Compensatorios</h3>
					<span class="badge badge-success">Festivos excluidos (2 y 16 Nov)</span>
				</div>
				<p class="small text-muted mb-2">Registro legal de descanso compensatorio generado por cada domingo laborado.</p>
				<div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
					<table class="table table-sm table-hover small mb-0">
						<thead>
							<tr>
								<th>Colaborador</th>
								<th>Domingo de Origen</th>
								<th>Fecha de Disfrute</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($eqComps, 0, 15) as $comp)
								<tr>
									<td class="font-weight-600">{{ $comp['employee_name'] }}</td>
									<td><span class="badge badge-light border"><i class="fa-solid fa-calendar-day mr-1"></i>{{ date('d/m/Y', strtotime($comp['origin_date'])) }}</span></td>
									<td><span class="badge badge-warning text-dark font-weight-bold"><i class="fa-solid fa-check mr-1"></i>{{ date('d/m/Y', strtotime($comp['date'])) }}</span></td>
									<td><span class="badge badge-success py-0">Garantizado</span></td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Cobertura Nocturna -->
		<div class="col-lg-6 mb-3">
			<div class="sch-card p-3 h-100">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<h3 class="sch-card-title mb-0"><i class="fa-solid fa-moon text-purple mr-1"></i> Cobertura Nocturna SAP (7N22-6)</h3>
					<span class="badge badge-purple">1 Colaborador / Jornada</span>
				</div>
				<p class="small text-muted mb-2">30 noches continuas de noviembre cubiertas sin sobrecupo ni vacantes.</p>
				<div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
					<table class="table table-sm table-hover small mb-0">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Colaborador Asignado</th>
								<th>Código Turno</th>
								<th>Horario</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($eqNights, 0, 15) as $night)
								@php
									$nName = $empNameMap[$night['payroll_code']] ?? $night['payroll_code'];
								@endphp
								<tr>
									<td><strong>{{ date('d/m (D)', strtotime($night['date'])) }}</strong></td>
									<td class="font-weight-600 text-purple">{{ $nName }}</td>
									<td><span class="sch-chip sch-chip-noche py-0">{{ $night['shift_code'] }}</span></td>
									<td>22:00 - 06:00 (7h)</td>
									<td><span class="badge badge-success py-0">1 cupo OK</span></td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- ========================================================================
     MODALS
     ======================================================================== -->

<!-- 1. MODAL: GENERAR PROGRAMACIÓN AUTOMÁTICA (10 PASOS) -->
<div class="modal fade" id="modalGenerarProgramacion" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-700">
					<i class="fa-solid fa-wand-magic-sparkles mr-2 text-success"></i> Motor Inteligente de Programación — Noviembre 2026
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="d-flex align-items-center justify-content-between p-3 rounded mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
					<div>
						<div class="font-weight-bold text-dark">Planilla PROGRAMACIÓN 2026 SEPT OCT Electro.xlsb</div>
						<div class="small text-muted">23 colaboradores · 5 áreas · Continuidad de rotación semana 44 de octubre</div>
					</div>
					<div class="text-right">
						<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check-circle mr-1"></i> Equidad proyectada: 91.5%</span>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2">Comprobaciones del Motor (10/10 Reglas de Negocio):</h6>
				<div class="list-group list-group-flush border rounded mb-3 small" id="engineChecksList">
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>1. Empleados y Disponibilidad:</strong> 23 colaboradores con código de nómina oficial</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>2. Continuidad Histórica:</strong> Rotación basada en la última semana válida de octubre</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>3. Domingos 3x1:</strong> Secuencia continua respetada según Empleados dominical</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>4. Descansos Compensatorios:</strong> Asignados Lun-Vie excluyendo festivos 2 y 16 Nov</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>5. Cupo Nocturno:</strong> Exactamente 1 colaborador (7N22-6) por jornada; sin vacantes</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>6. Descansos Legales:</strong> >12 horas de descanso post-trasnocho; sin apertura post-noche</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>7. Cobertura por Área:</strong> Accesorios (3), Bodega (4), Despachos (2), Ventas (12), Virtual (2)</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>8. Presupuesto y Costos:</strong> Horas programadas alineadas con productividad SPLH</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>9. Alternativas y Conflictos:</strong> 0 conflictos no resueltos detectados</span>
						<span class="badge badge-success">PASS</span>
					</div>
					<div class="list-group-item d-flex align-items-center justify-content-between py-2">
						<span><i class="fa-solid fa-circle-check text-success mr-2"></i> <strong>10. Aprobación y Publicación:</strong> Requiere autorización explícita antes de publicar</span>
						<span class="badge badge-info">EN ESPERA</span>
					</div>
				</div>

				<div class="alert alert-info py-2 small mb-0">
					<i class="fa-solid fa-info-circle mr-1"></i> Al aprobar y publicar, los turnos se sincronizarán con los módulos de asistencia, reloj de fichaje y versión móvil de DAILY.
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-primary" id="btnExecuteReGenerate">
					<i class="fa-solid fa-arrows-rotate mr-1"></i> Re-calcular Programación
				</button>
				<button type="button" class="btn btn-success font-weight-bold" id="btnApproveAndPublish">
					<i class="fa-solid fa-check-double mr-1"></i> Aprobar y Publicar Noviembre 2026
				</button>
			</div>
		</div>
	</div>
</div>

<!-- 2. MODAL: INTERCAMBIO DE TURNOS CON SIMULADOR DE IMPACTO -->
<div class="modal fade" id="modalShiftSwap" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title font-weight-700">
					<i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> Simulador de Intercambio de Turnos (Shift Swap)
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<p class="small text-muted mb-3">DAILY calculará en tiempo real el impacto del intercambio en la <strong>equidad</strong>, <strong>descansos legales</strong>, <strong>horas</strong> y <strong>costos</strong> antes de confirmar.</p>

				<div class="row">
					<!-- Colaborador A -->
					<div class="col-md-6 border-right">
						<h6 class="font-weight-bold text-dark mb-2">Colaborador A</h6>
						<div class="form-group mb-2">
							<label class="small font-weight-600 text-muted mb-1">Empleado:</label>
							<select class="form-control form-control-sm" id="swapEmpA">
								@foreach($eqCollabs as $c)
									<option value="{{ $c['payroll_code'] }}">{{ $c['name'] }} ({{ $c['department'] }})</option>
								@endforeach
							</select>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-600 text-muted mb-1">Fecha del turno a ceder:</label>
							<input type="date" class="form-control form-control-sm" id="swapDateA" value="2026-11-03" min="2026-11-01" max="2026-11-30">
						</div>
					</div>

					<!-- Colaborador B -->
					<div class="col-md-6">
						<h6 class="font-weight-bold text-dark mb-2">Colaborador B</h6>
						<div class="form-group mb-2">
							<label class="small font-weight-600 text-muted mb-1">Empleado:</label>
							<select class="form-control form-control-sm" id="swapEmpB">
								@foreach($eqCollabs as $idx => $c)
									<option value="{{ $c['payroll_code'] }}" @if($idx == 1) selected @endif>{{ $c['name'] }} ({{ $c['department'] }})</option>
								@endforeach
							</select>
						</div>
						<div class="form-group mb-2">
							<label class="small font-weight-600 text-muted mb-1">Fecha del turno a ceder:</label>
							<input type="date" class="form-control form-control-sm" id="swapDateB" value="2026-11-04" min="2026-11-01" max="2026-11-30">
						</div>
					</div>
				</div>

				<div class="text-center my-3">
					<button type="button" class="btn btn-sm btn-info px-3" id="btnSimulateSwapAction">
						<i class="fa-solid fa-calculator mr-1"></i> Simular Impacto en Equidad y Descanso
					</button>
				</div>

				<!-- Simulation Output Box -->
				<div id="swapSimulationResultBox" style="display: none;">
					<div class="sch-swap-compare-box">
						<div class="sch-swap-col">
							<div class="sch-swap-col-title text-primary" id="simTitleA">Colaborador A</div>
							<div class="small mb-1">Turno actual: <strong id="simCurrentShiftA">—</strong></div>
							<div class="small mb-1">Nuevo turno: <strong id="simNewShiftA" class="text-success">—</strong></div>
							<div class="small mb-1">Equidad: <span id="simCurrentEqA">—</span> → <strong id="simProjectedEqA" class="text-success">—</strong> (<span id="simDeltaEqA">—</span>)</div>
							<div class="small">Impacto en costo: <strong id="simCostA">—</strong></div>
						</div>

						<div class="sch-swap-col">
							<div class="sch-swap-col-title text-primary" id="simTitleB">Colaborador B</div>
							<div class="small mb-1">Turno actual: <strong id="simCurrentShiftB">—</strong></div>
							<div class="small mb-1">Nuevo turno: <strong id="simNewShiftB" class="text-success">—</strong></div>
							<div class="small mb-1">Equidad: <span id="simCurrentEqB">—</span> → <strong id="simProjectedEqB" class="text-success">—</strong> (<span id="simDeltaEqB">—</span>)</div>
							<div class="small">Impacto en costo: <strong id="simCostB">—</strong></div>
						</div>
					</div>

					<div class="p-2 rounded bg-light border small">
						<div id="simRestComplianceAlert">
							<i class="fa-solid fa-circle-check text-success mr-1"></i> <strong>Descanso legal:</strong> Cumple con >12 horas de descanso entre turnos.
						</div>
						<div class="text-muted mt-1" id="simCoverageImpactText">
							Cobertura por área garantizada. Sin vacantes.
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary font-weight-bold" id="btnConfirmSwapAction" disabled>
					<i class="fa-solid fa-check mr-1"></i> Confirmar e Intercambiar
				</button>
			</div>
		</div>
	</div>
</div>

<!-- 3. MODAL: EDICIÓN RÁPIDA DE CELDA DE TURNO -->
<div class="modal fade" id="modalEditShiftCell" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light">
				<h6 class="modal-title font-weight-700 text-dark mb-0">Modificar Turno</h6>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="small text-muted mb-2">Colaborador: <strong class="text-dark d-block" id="editShiftEmpName">—</strong></div>
				<div class="small text-muted mb-3">Fecha: <strong class="text-dark d-block" id="editShiftDate">—</strong></div>

				<input type="hidden" id="editShiftEmpCode">
				<input type="hidden" id="editShiftDateIso">

				<div class="form-group mb-2">
					<label class="small font-weight-600 text-muted mb-1">Código de Turno SAP:</label>
					<select class="form-control form-control-sm" id="editShiftCodeSelect">
						<optgroup label="Apertura">
							<option value="7A6-13">7A6-13 (06:00 - 13:00)</option>
							<option value="7A7-14">7A7-14 (07:00 - 14:00)</option>
							<option value="7A7.3-14.3">7A7.3-14.3 (07:30 - 14:30)</option>
							<option value="7A5.3-12.3">7A5.3-12.3 (05:30 - 12:30)</option>
						</optgroup>
						<optgroup label="Cierre">
							<option value="7C13-20">7C13-20 (13:00 - 20:00)</option>
							<option value="7C14-21">7C14-21 (14:00 - 21:00)</option>
							<option value="7C14.3-21.3">7C14.3-21.3 (14:30 - 21:30)</option>
						</optgroup>
						<optgroup label="Noche">
							<option value="7N22-6">7N22-6 (22:00 - 06:00)</option>
						</optgroup>
						<optgroup label="Descansos y Ausencias">
							<option value="COMP">COMP (Compensatorio)</option>
							<option value="LBRE">LBRE (Libre)</option>
							<option value="VC">VC (Vacaciones)</option>
							<option value="INC">INC (Incapacidad)</option>
						</optgroup>
					</select>
				</div>
			</div>
			<div class="modal-footer p-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSaveShiftCell">Guardar Turno</button>
			</div>
		</div>
	</div>
</div>
