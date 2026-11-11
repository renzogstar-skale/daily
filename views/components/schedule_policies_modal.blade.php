{{-- DAILY: Modal de Configuración Universal de Políticas Laborales por Empresa --}}
<div class="modal fade" id="modalCompanyLaborPolicies" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
			<div class="modal-header text-white" style="background: linear-gradient(135deg, #059669, #047857);">
				<div>
					<h5 class="modal-title font-weight-bold mb-0">
						<i class="fa-solid fa-building-shield mr-2"></i>Políticas Laborales de Empresa y Motor de Planificación
					</h5>
					<span class="small opacity-90">Configuración universal multiempresa, ciclos dominicales (3x1/2x1), rotación y descansos</span>
				</div>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="modal-body p-4 bg-light">
				<!-- Current Policy Active Badge -->
				<div class="card border p-3 mb-3 bg-white">
					<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
						<div>
							<span class="badge badge-success px-2 py-1 mb-1 font-weight-bold">
								<i class="fa-solid fa-shield-halved mr-1"></i>VERSIÓN ACTIVA VIGENTE
							</span>
@php
	$jurProfiles = $jurisdictionProfiles ?? \Grocy\Services\WorkforceLaborRulesService::GetJurisdictionProfiles();
	$activeJur = $jurisdictionPolicy ?? [];
	$activeJurCode = $activeJur['jurisdiction_code'] ?? 'CO-GEN';
@endphp
							<h6 class="font-weight-bold text-dark mb-0" id="currentPolicyTitle">
								{{ $activeJur['policy_name'] ?? 'Política Oficial Colombia — Ley 2101 / Ley 51 (3x1)' }}
							</h6>
							<span class="text-muted small">
								Jurisdicción: <strong id="currentPolicyJurBadge">{{ $activeJurCode }}</strong> · Tope semanal: <strong>{{ $activeJur['max_weekly_hours'] ?? 42 }}h</strong> · Descanso mínimo: <strong>{{ $activeJur['min_rest_hours'] ?? 12 }}h</strong>
							</span>
						</div>
						<div class="text-right">
							<span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem;">
								Reglas Laborales por País
							</span>
						</div>
					</div>
				</div>

				<!-- Policy Form -->
				<form id="formCompanyLaborPolicy" class="bg-white p-3 rounded border">
					<input type="hidden" id="policyCompanyId" name="company_id" value="{{ $activeJur['company_id'] ?? 1 }}">
					<input type="hidden" id="policyCountryCode" name="country_code" value="{{ $activeJur['country_code'] ?? 'CO' }}">
					<input type="hidden" id="policyBranchId" name="branch_id" value="{{ $branchId ?? 1 }}">

					<div class="row">
						<div class="col-md-5 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">País y Jurisdicción Laboral:</label>
							<select class="form-control form-control-sm font-weight-bold" id="policyJurisdictionSelect" name="jurisdiction_code">
								@foreach($jurProfiles as $jp)
									@php $jpArr = is_array($jp) ? $jp : (array)$jp; @endphp
									<option value="{{ $jpArr['jurisdiction_code'] }}"
									        data-country="{{ $jpArr['country_code'] }}"
									        data-weekly="{{ $jpArr['max_weekly_hours'] }}"
									        data-daily="{{ $jpArr['max_daily_hours'] ?? 8 }}"
									        data-rest="{{ $jpArr['min_rest_hours'] }}"
									        data-sunday="{{ $jpArr['sunday_rule_type'] ?? '3x1' }}"
									        data-night-start="{{ $jpArr['night_start_hour'] ?? 21 }}"
									        data-night-end="{{ $jpArr['night_end_hour'] ?? 6 }}"
									        data-ref="{{ $jpArr['legal_reference'] ?? '' }}"
									        {{ $jpArr['jurisdiction_code'] === $activeJurCode ? 'selected' : '' }}>
										{{ $jpArr['country_name'] }} — {{ $jpArr['jurisdiction_name'] }} ({{ $jpArr['max_weekly_hours'] }}h)
									</option>
								@endforeach
							</select>
							<small class="text-muted d-block mt-1" id="policyJurLegalRef">{{ $activeJur['legal_reference'] ?? 'Ley 2101 / Ley 2466 / CST' }}</small>
						</div>
						<div class="col-md-4 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Nombre o referencia de la política:</label>
							<input type="text" class="form-control form-control-sm" id="policyNameInput" name="policy_name" 
								value="{{ $activeJur['policy_name'] ?? 'Política Oficial Colombia Ley 2101 / Ley 51 (3x1)' }}" required>
						</div>
						<div class="col-md-3 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Fecha de vigencia:</label>
							<input type="date" class="form-control form-control-sm" id="policyEffectiveDateInput" name="effective_date" 
								value="{{ date('Y-m-d') }}" required>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Regla de rotación dominical:</label>
							<select class="form-control form-control-sm font-weight-bold" id="policySundayTypeSelect" name="sunday_rule_type">
								<option value="3x1" selected>3 domingos trabajados y 1 libre (Ley Colombia 3x1)</option>
								<option value="2x1">2 domingos trabajados y 1 libre (Ciclo 2x1)</option>
								<option value="1x1">1 domingo trabajado y 1 libre (Alternado 50/50)</option>
							</select>
							<small class="text-muted d-block mt-1">Cálculo matemático modular continuo por colaborador sin reseteo mensual.</small>
						</div>

						<div class="col-md-6 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Rotación apertura / cierre:</label>
							<select class="form-control form-control-sm font-weight-bold" id="policyRotationTypeSelect" name="rotation_rule_type">
								<option value="weekly_apertura_cierre" selected>1 semana Apertura / 1 semana Cierre (Secuencia continua)</option>
								<option value="fixed_shifts">Horarios fijos según contrato laboral</option>
							</select>
							<small class="text-muted d-block mt-1">Equidad acumulada y alternancia automática entre meses y años.</small>
						</div>
					</div>

					<div class="row">
						<div class="col-md-4 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Límite nocturno diario:</label>
							<input type="number" class="form-control form-control-sm font-weight-bold" id="policyMaxNightsInput" name="max_nights_per_day" value="1" min="1" max="10" required>
							<small class="text-muted d-block mt-1">Máximo colaboradores nocturnos por jornada (1 en cliente actual).</small>
						</div>

						<div class="col-md-4 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Jornada semanal máxima:</label>
							<div class="input-group input-group-sm">
								<input type="number" step="0.5" class="form-control font-weight-bold" id="policyMaxHoursInput" name="max_weekly_hours" value="{{ $activeJur['max_weekly_hours'] ?? 42.0 }}" required>
								<div class="input-group-append">
									<span class="input-group-text">horas</span>
								</div>
							</div>
							<small class="text-muted d-block mt-1">Tope semanal de la jurisdicción activa.</small>
						</div>

						<div class="col-md-4 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Descanso entre turnos:</label>
							<div class="input-group input-group-sm">
								<input type="number" step="0.5" class="form-control font-weight-bold" id="policyMinRestInput" name="min_rest_hours" value="{{ $activeJur['min_rest_hours'] ?? 12.0 }}" required>
								<div class="input-group-append">
									<span class="input-group-text">horas</span>
								</div>
							</div>
							<small class="text-muted d-block mt-1">Descanso mínimo de seguridad entre salida y entrada.</small>
						</div>
					</div>

					<div class="row">
						<div class="col-md-4 col-12 mb-2">
							<label class="font-weight-bold text-dark small mb-1">Máx. horas diarias ordinarias:</label>
							<input type="number" step="0.5" class="form-control form-control-sm font-weight-bold" id="policyMaxDailyInput" name="max_daily_hours" value="{{ $activeJur['max_daily_hours'] ?? 8.0 }}" required>
						</div>
						<div class="col-md-4 col-12 mb-2">
							<label class="font-weight-bold text-dark small mb-1">Inicio franja nocturna (0-23h):</label>
							<input type="number" class="form-control form-control-sm font-weight-bold" id="policyNightStartInput" name="night_start_hour" value="{{ $activeJur['night_start_hour'] ?? 21 }}" min="18" max="23">
						</div>
						<div class="col-md-4 col-12 mb-2">
							<label class="font-weight-bold text-dark small mb-1">Fin franja nocturna (0-23h):</label>
							<input type="number" class="form-control form-control-sm font-weight-bold" id="policyNightEndInput" name="night_end_hour" value="{{ $activeJur['night_end_hour'] ?? 6 }}" min="4" max="8">
						</div>
					</div>

					<div class="p-2 rounded bg-light border text-muted small mb-0">
						<i class="fa-solid fa-circle-info text-info mr-1"></i>
						<strong>Regla de compensatorios y jurisdicción:</strong> Los parámetros legales (tope semanal, jornada diaria, descanso mínimo entre turnos, franja nocturna y dominical) se aplican automáticamente a validación de conflictos, asistencia y nómina de esta sucursal.
					</div>
				</form>

				<!-- Versioning History Table -->
				<div class="mt-3 bg-white p-3 rounded border">
					<h6 class="font-weight-bold text-dark mb-2" style="font-size: 0.85rem;">
						<i class="fa-solid fa-code-branch mr-1 text-primary"></i>Historial de Políticas y Versionamiento
					</h6>
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0" style="font-size: 0.78rem;">
							<thead class="thead-light">
								<tr>
									<th>Versión</th>
									<th>Nombre</th>
									<th>Vigencia</th>
									<th>Ciclo Dominical</th>
									<th>Rotación</th>
									<th>Tope Nocturno</th>
									<th>Estado</th>
								</tr>
							</thead>
							<tbody id="policyHistoryTableBody">
								<tr>
									<td class="font-weight-bold">v1.0</td>
									<td>Política Oficial Colombia Ley 2101 / Ley 51 (3x1)</td>
									<td>01/01/2026</td>
									<td><span class="badge badge-success">3x1 Modular</span></td>
									<td>Semanal Apertura/Cierre</td>
									<td>1 persona/noche</td>
									<td><span class="badge badge-primary">Activa</span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="modal-footer bg-white border-top py-2 px-3 d-flex justify-content-between">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">
					Cerrar
				</button>
				<button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnSaveCompanyPolicyAction">
					<i class="fa-solid fa-floppy-disk mr-1"></i>Guardar y Versionar Política
				</button>
			</div>
		</div>
	</div>
</div>
