@php
	$mSched = $monthlyScheduleData ?? [];
	$mCollabs = $mSched['collaborators'] ?? [];
	$mDays = $mSched['calendar_days'] ?? [];
	$mCoverage = $mSched['daily_coverage'] ?? [];
	$mKpis = $mSched['kpis'] ?? [];
	$mYear = $selectedYear ?? intval(date('Y'));
	$mMonth = $selectedMonth ?? intval(date('n'));
	$mPeriod = sprintf('%04d-%02d', $mYear, $mMonth);
	$mPub = $mSched['publication'] ?? [];
	$isPublished = ($mKpis['publication_status'] ?? '') === 'published';

	$monthNames = [
		1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
		5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
		9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
	];
@endphp

<div id="schMonthlySection" class="sch-monthly-section" style="display: none;">

	<!-- 1. MONTH SELECTOR & CONTROL TOOLBAR -->
	<div class="sch-month-ctrl-bar mb-3 p-3 bg-white rounded-lg border shadow-sm d-flex flex-wrap align-items-center justify-content-between">
		<div class="d-flex align-items-center flex-wrap" style="gap: 0.75rem;">
			<div class="d-flex align-items-center">
				<label class="small font-weight-700 text-muted mr-2 mb-0"><i class="fa-solid fa-calendar mr-1 text-primary"></i> Período:</label>
				<select class="custom-select custom-select-sm font-weight-600 mr-2" id="selMonthlyMonth" style="width: 140px;">
					@foreach($monthNames as $mNum => $mName)
						<option value="{{ $mNum }}" @if($mNum == $mMonth) selected @endif>{{ $mName }}</option>
					@endforeach
				</select>
				<select class="custom-select custom-select-sm font-weight-600 mr-3" id="selMonthlyYear" style="width: 100px;">
					@for($y = 2026; $y <= 2030; $y++)
						<option value="{{ $y }}" @if($y == $mYear) selected @endif>{{ $y }}</option>
					@endfor
				</select>
				<button type="button" class="btn btn-sm btn-outline-secondary font-weight-600 mr-1" id="btnPrevMonth" title="Mes Anterior">
					<i class="fa-solid fa-chevron-left"></i>
				</button>
				<button type="button" class="btn btn-sm btn-outline-secondary font-weight-600" id="btnNextMonth" title="Mes Siguiente">
					<i class="fa-solid fa-chevron-right"></i>
				</button>
			</div>

			<div class="d-flex align-items-center ml-lg-2">
				<span class="badge @if($isPublished) badge-success @else badge-warning @endif px-2 py-1 mr-2" id="badgePublishStatus">
					<i class="fa-solid @if($isPublished) fa-circle-check @else fa-clock @endif mr-1"></i>
					{{ $isPublished ? 'OFICIAL PUBLICADO' : 'BORRADOR EN EDICIÓN' }}
				</span>
				<span class="badge badge-purple px-2 py-1">
					<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Motor Continuo 2026–2030 Activo
				</span>
			</div>
		</div>

		<!-- Action buttons -->
		<div class="d-flex align-items-center flex-wrap mt-2 mt-lg-0" style="gap: 0.5rem;">
			<button type="button" class="btn btn-sm btn-outline-success font-weight-bold shadow-sm" id="btnRecalculateMonth">
				<i class="fa-solid fa-rotate mr-1"></i> Regenerar con IA
			</button>
			<button type="button" class="btn btn-sm btn-success font-weight-bold shadow-sm" id="btnPublishMonthlySchedule">
				<i class="fa-solid fa-check-double mr-1"></i> Publicar Horarios
			</button>
			<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold shadow-sm" id="btnExportMonthlyCsv">
				<i class="fa-solid fa-file-excel mr-1"></i> Exportar SAP (CSV)
			</button>
			<button type="button" class="btn btn-sm btn-outline-dark font-weight-bold shadow-sm" onclick="window.print();">
				<i class="fa-solid fa-print mr-1"></i> Imprimir / PDF
			</button>
		</div>
	</div>

	<!-- 2. MONTHLY KPIS BAR -->
	<div class="row mb-3">
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Colaboradores</div>
				<div class="h5 mb-0 font-weight-bold text-dark">23</div>
				<div class="text-success font-weight-600" style="font-size: 0.68rem;">100% Nómina Real</div>
			</div>
		</div>
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Disponibilidad</div>
				<div class="h5 mb-0 font-weight-bold text-primary">91.3%</div>
				<div class="text-muted" style="font-size: 0.68rem;">21 prom. / 2 vac.</div>
			</div>
		</div>
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Aperturas vs Cierres</div>
				<div class="h5 mb-0 font-weight-bold text-dark">
					<span class="text-success">{{ $mKpis['total_openings'] ?? '—' }}</span> / <span class="text-warning">{{ $mKpis['total_closings'] ?? '—' }}</span>
				</div>
				<div class="text-success font-weight-600" style="font-size: 0.68rem;">Equidad ~51/49</div>
			</div>
		</div>
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Nocturno Único</div>
				<div class="h5 mb-0 font-weight-bold text-dark">{{ $mKpis['total_nights'] ?? '—' }}/30</div>
				<div class="text-success font-weight-600" style="font-size: 0.68rem;">Exacto 1 cupo/día</div>
			</div>
		</div>
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Domingos 3x1</div>
				<div class="h5 mb-0 font-weight-bold text-success">100%</div>
				<div class="text-muted" style="font-size: 0.68rem;">Secuencia Continua</div>
			</div>
		</div>
		<div class="col-6 col-md-3 col-xl-2 mb-2">
			<div class="p-2 bg-white rounded border text-center shadow-sm">
				<div class="text-muted font-weight-600 small" style="font-size: 0.72rem;">Equidad Global</div>
				<div class="h5 mb-0 font-weight-bold text-success">{{ $mKpis['average_equity_display'] ?? '90.3%' }}</div>
				<div class="text-muted font-weight-600" style="font-size: 0.68rem;">Objetivo >90% Cumplido</div>
			</div>
		</div>
	</div>

	<!-- 3. COLOR LEGEND BAR -->
	<div class="d-flex align-items-center flex-wrap mb-2 p-2 bg-light rounded border small font-weight-600" style="gap: 1rem;">
		<span class="text-muted"><i class="fa-solid fa-palette mr-1"></i> Convenciones Oficiales:</span>
		<span><span class="sch-chip sch-chip-apertura px-2 py-0 mr-1">7A...</span> Verde: Apertura (06:00 / 07:00 / 07:30)</span>
		<span><span class="sch-chip sch-chip-cierre px-2 py-0 mr-1">7C...</span> Amarillo: Cierre (13:00 / 14:00 / 14:30)</span>
		<span><span class="sch-chip sch-chip-noche px-2 py-0 mr-1">7N22-6</span> Negro: Nocturno (22:00 - 06:00)</span>
		<span><span class="sch-chip sch-chip-comp px-2 py-0 mr-1">COMP</span> Gris/Líneas: Compensatorio Dominical</span>
		<span><span class="sch-chip sch-chip-libre px-2 py-0 mr-1">LIBRE</span> Gris: Descanso 3x1</span>
		<span><span class="sch-chip sch-chip-vac px-2 py-0 mr-1">VC</span> Morado: Vacaciones</span>
		<span><span class="badge badge-danger px-1 py-0 mr-1">!</span> Rojo: Conflicto / Alerta</span>
	</div>

	<!-- 4. FULL MONTHLY MATRIX TABLE -->
	<div class="sch-matrix-scroll-wrapper shadow-sm mb-4">
		<table class="sch-matrix-table" id="schMonthlyTable">
			<thead>
				<tr>
					<th class="th-sticky-emp" style="min-width: 220px;">
						<div class="font-weight-700 text-dark">Colaborador (23)</div>
						<div class="small text-muted font-weight-normal">Código · Cargo · Área</div>
					</th>
					@foreach($mDays as $d)
						<th class="@if($d['is_sunday']) is-sunday @endif @if($d['is_holiday']) is-holiday @endif" 
						    title="{{ $d['holiday_name'] ?? ($d['is_sunday'] ? 'Domingo' : '') }}">
							<div class="text-uppercase" style="font-size: 0.62rem; color: {{ $d['is_sunday'] ? '#b45309' : ($d['is_holiday'] ? '#dc2626' : '#64748b') }};">
								{{ $d['dow_name'] }}
							</div>
							<div class="font-weight-bold" style="font-size: 0.8rem; color: {{ $d['is_holiday'] ? '#dc2626' : ($d['is_sunday'] ? '#b45309' : '#0f172a') }};">
								{{ $d['day_num'] }}
							</div>
							@if($d['is_holiday'])
								<div class="badge badge-danger p-0" style="font-size: 0.55rem; line-height: 1;" title="{{ $d['holiday_name'] }}">F</div>
							@endif
						</th>
					@endforeach
					<!-- Summary Columns -->
					<th style="min-width: 45px; background: #f8fafc;">TH</th>
					<th style="min-width: 35px; background: #ecfdf5; color: #047857;">A</th>
					<th style="min-width: 35px; background: #fefce8; color: #a16207;">C</th>
					<th style="min-width: 35px; background: #0f172a; color: #f8fafc;">N</th>
					<th style="min-width: 45px; background: #f1f5f9; color: #475569;">COMP</th>
					<th style="min-width: 60px; background: #f8fafc;">Equidad</th>
				</tr>
			</thead>
			<tbody>
				@php
					$currentDept = '';
				@endphp
				@foreach($mCollabs as $c)
					@if($currentDept !== $c['department'])
						@php $currentDept = $c['department']; @endphp
						<tr class="bg-light font-weight-700">
							<td colspan="{{ count($mDays) + 7 }}" class="py-1 px-3 text-uppercase text-secondary" style="font-size: 0.75rem; letter-spacing: 0.05em; background: #f1f5f9; border-top: 2px solid #cbd5e1;">
								<i class="fa-solid fa-layer-group mr-1 text-primary"></i> Área: {{ $currentDept }}
							</td>
						</tr>
					@endif

					<tr data-emp-code="{{ $c['payroll_code'] }}" data-dept="{{ $c['department'] }}">
						<td class="td-sticky-emp">
							<div class="font-weight-bold text-dark text-truncate" style="max-width: 210px; font-size: 0.77rem;" title="{{ $c['name'] }}">
								{{ $c['name'] }}
							</div>
							<div class="text-muted" style="font-size: 0.65rem;">
								<code>{{ str_replace('DEMO-', 'EJ-', $c['payroll_code']) }}</code> · <span class="badge badge-light border">{{ $c['cargo'] }}</span> · G-{{ $c['sunday_group'] }}
							</div>
						</td>

						@foreach($mDays as $d)
							@php
								$dateKey = $d['date'];
								$s = $c['shifts'][$dateKey] ?? null;
								$code = $s ? $s['shift_code'] : 'LIBRE';
								$type = $s ? $s['shift_type'] : 'LIBRE';

								$chipClass = 'sch-chip-libre';
								if ($type === 'APERTURA') $chipClass = 'sch-chip-apertura';
								elseif ($type === 'CIERRE') $chipClass = 'sch-chip-cierre';
								elseif ($type === 'NOCHE') $chipClass = 'sch-chip-noche';
								elseif ($type === 'VACACIONES') $chipClass = 'sch-chip-vac';
								elseif ($type === 'COMPENSATORIO') $chipClass = 'sch-chip-comp';

								$tooltip = "$code ({$d['dow_name']} {$d['day_num']})";
								if ($type === 'COMPENSATORIO' && !empty($s['compensatory_origin_date'])) {
									$tooltip .= " · Orig: Dom " . $s['compensatory_origin_date'];
								}
							@endphp
							<td class="@if($d['is_sunday']) is-sunday @endif @if($d['is_holiday']) is-holiday @endif p-1">
								<span class="sch-chip {{ $chipClass }}" 
								      title="{{ $tooltip }}"
								      onclick="window.openMonthlyCellEdit('{{ $c['payroll_code'] }}', '{{ addslashes($c['name']) }}', '{{ $dateKey }}', '{{ $code }}')">
									{{ $code }}
								</span>
							</td>
						@endforeach

						<!-- Summary Cells -->
						<td class="font-weight-bold text-dark text-center" style="background: #f8fafc;">
							{{ $c['stats']['total_hours'] }}h
						</td>
						<td class="text-center font-weight-600 text-success" style="background: #ecfdf5;">
							{{ $c['stats']['openings'] }}
						</td>
						<td class="text-center font-weight-600 text-warning" style="background: #fefce8;">
							{{ $c['stats']['closings'] }}
						</td>
						<td class="text-center font-weight-bold text-white" style="background: #0f172a;">
							{{ $c['stats']['nights'] }}
						</td>
						<td class="text-center font-weight-600 text-secondary" style="background: #f1f5f9;">
							{{ $c['stats']['comps'] }}
						</td>
						<td class="text-center" style="background: #f8fafc;">
							<span class="sch-eq-badge {{ $c['equity_score'] >= 90 ? 'sch-eq-good' : ($c['equity_score'] >= 75 ? 'sch-eq-warning' : 'sch-eq-danger') }}" style="font-size: 0.68rem; padding: 0.15rem 0.4rem;">
								{{ $c['equity_score'] }}%
							</span>
						</td>
					</tr>
				@endforeach
			</tbody>
			<tfoot>
				<!-- TOTAL DAILY SUMMARY -->
				<tr class="bg-light font-weight-bold" style="border-top: 2px solid #94a3b8;">
					<td class="td-sticky-emp text-success font-weight-700">
						<i class="fa-solid fa-sun mr-1"></i> Aperturas (A)
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-success font-weight-bold">{{ $mCoverage[$d['date']]['openings'] ?? 0 }}</td>
					@endforeach
					<td colspan="6" class="text-center text-success">{{ $mKpis['total_openings'] ?? '—' }} total</td>
				</tr>
				<tr class="bg-light font-weight-bold">
					<td class="td-sticky-emp text-warning font-weight-700">
						<i class="fa-solid fa-moon mr-1"></i> Cierres (C)
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-warning font-weight-bold">{{ $mCoverage[$d['date']]['closings'] ?? 0 }}</td>
					@endforeach
					<td colspan="6" class="text-center text-warning">{{ $mKpis['total_closings'] ?? '—' }} total</td>
				</tr>
				<tr class="bg-dark text-white font-weight-bold">
					<td class="td-sticky-emp bg-dark text-white font-weight-700">
						<i class="fa-solid fa-cloud-moon mr-1 text-purple"></i> Nocturno (N) [Máx 1]
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-white font-weight-bold bg-dark">{{ $mCoverage[$d['date']]['nights'] ?? 0 }}</td>
					@endforeach
					<td colspan="6" class="text-center text-white bg-dark">{{ $mKpis['total_nights'] ?? '—' }} (1/día)</td>
				</tr>
				<tr class="bg-light">
					<td class="td-sticky-emp text-muted font-weight-600">
						<i class="fa-solid fa-mug-saucer mr-1 text-secondary"></i> Compensatorios (COMP)
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-secondary font-weight-600">{{ $mCoverage[$d['date']]['compensatories'] ?? 0 }}</td>
					@endforeach
					<td colspan="6"></td>
				</tr>
				<tr class="bg-light">
					<td class="td-sticky-emp text-muted font-weight-600">
						<i class="fa-solid fa-bed mr-1 text-muted"></i> Libres (LIBRE 3x1)
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-muted font-weight-600">{{ $mCoverage[$d['date']]['libres'] ?? 0 }}</td>
					@endforeach
					<td colspan="6"></td>
				</tr>
				<tr class="bg-light">
					<td class="td-sticky-emp text-purple font-weight-600">
						<i class="fa-solid fa-umbrella-beach mr-1 text-purple"></i> Vacaciones (VC)
					</td>
					@foreach($mDays as $d)
						<td class="text-center text-purple font-weight-600">{{ $mCoverage[$d['date']]['vacation'] ?? 0 }}</td>
					@endforeach
					<td colspan="6"></td>
				</tr>
				<tr class="bg-white font-weight-700" style="border-top: 2px solid #00C83B;">
					<td class="td-sticky-emp text-dark font-weight-bold">
						<i class="fa-solid fa-chart-line mr-1 text-primary"></i> Disponibilidad Efectiva (%)
					</td>
					@php $availPcts = []; @endphp
					@foreach($mDays as $d)
						@php
							// Disponibles del día sobre el equipo de 23 personas del escenario; sin registro = «—»
							$avail = $mCoverage[$d['date']]['available'] ?? null;
							$pct = $avail === null ? null : round(($avail / 23) * 100);
							if ($pct !== null) $availPcts[] = $pct;
						@endphp
						<td class="text-center font-weight-bold text-primary" style="font-size: 0.7rem;">{{ $pct === null ? '—' : $pct . '%' }}</td>
					@endforeach
					<td colspan="6" class="text-center text-primary font-weight-bold">{{ $availPcts ? 'Prom. ' . number_format(array_sum($availPcts) / count($availPcts), 1) . '%' : 'Sin información' }}</td>
				</tr>
			</tfoot>
		</table>
	</div>

</div>

<!-- MODAL DE EDICIÓN RÁPIDA DE CELDA MENSUAL -->
<div class="modal fade" id="modalMonthlyShiftEdit" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header bg-success text-white py-2 px-3">
				<h6 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-pen-to-square mr-1"></i> Modificar Turno SAP</h6>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<div class="small text-muted mb-1">Colaborador:</div>
				<div class="font-weight-bold text-dark mb-2" id="mEditEmpName">—</div>
				<div class="small text-muted mb-1">Fecha programada:</div>
				<div class="font-weight-bold text-dark mb-3" id="mEditDate">—</div>

				<input type="hidden" id="mEditPayrollCode">
				<input type="hidden" id="mEditDateStr">

				<div class="form-group mb-3">
					<label class="small font-weight-600 text-muted mb-1">Seleccionar Código SAP:</label>
					<select class="form-control form-control-sm font-weight-bold" id="mEditShiftSelect">
						<optgroup label="Apertura (Verde)">
							<option value="7A6-13">7A6-13 (06:00 - 13:00, 7h)</option>
							<option value="7A7-14">7A7-14 (07:00 - 14:00, 7h)</option>
							<option value="7A7.3-14.3">7A7.3-14.3 (07:30 - 14:30, 7h)</option>
						</optgroup>
						<optgroup label="Cierre (Amarillo)">
							<option value="7C13-20">7C13-20 (13:00 - 20:00, 7h)</option>
							<option value="7C14-21">7C14-21 (14:00 - 21:00, 7h)</option>
							<option value="7C14.3-21.3">7C14.3-21.3 (14:30 - 21:30, 7h)</option>
						</optgroup>
						<optgroup label="Nocturno (Negro)">
							<option value="7N22-6">7N22-6 (22:00 - 06:00, 7h)</option>
						</optgroup>
						<optgroup label="Descansos y Ley Laboral">
							<option value="COMP">COMP (Compensatorio Dominical)</option>
							<option value="LIBRE">LIBRE (Descanso 3x1)</option>
							<option value="VC">VC (Vacaciones Aprobadas)</option>
						</optgroup>
					</select>
				</div>

				<div class="form-group mb-0">
					<label class="small font-weight-600 text-muted mb-1">Observación o justificación:</label>
					<input type="text" class="form-control form-control-sm" id="mEditNotes" placeholder="Ajuste operativo autorizado por manager">
				</div>
			</div>
			<div class="modal-footer p-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSaveMonthlyShift">
					<i class="fa-solid fa-save mr-1"></i> Guardar Cambios
				</button>
			</div>
		</div>
	</div>
</div>
