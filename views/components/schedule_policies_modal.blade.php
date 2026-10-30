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
							<h6 class="font-weight-bold text-dark mb-0" id="currentPolicyTitle">
								Política Oficial Colombia — Ley 2101 / Ley 51 (3x1)
							</h6>
							<span class="text-muted small">
								Empresa: <strong>DAILY Enterprise (Matriz)</strong> · Vigencia desde: <strong>01/01/2026</strong>
							</span>
						</div>
						<div class="text-right">
							<span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem;">
								Aislamiento Multi-Tenant Activo
							</span>
						</div>
					</div>
				</div>

				<!-- Policy Form -->
				<form id="formCompanyLaborPolicy" class="bg-white p-3 rounded border">
					<input type="hidden" id="policyCompanyId" name="company_id" value="1">

					<div class="row">
						<div class="col-md-7 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Nombre o referencia de la política:</label>
							<input type="text" class="form-control form-control-sm" id="policyNameInput" name="policy_name" 
								value="Política Oficial Colombia Ley 2101 / Ley 51 (3x1)" required>
						</div>
						<div class="col-md-5 col-12 mb-3">
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
								<input type="number" step="0.5" class="form-control font-weight-bold" id="policyMaxHoursInput" name="max_weekly_hours" value="42.0" required>
								<div class="input-group-append">
									<span class="input-group-text">horas</span>
								</div>
							</div>
							<small class="text-muted d-block mt-1">Límite legal colombiano Ley 2101 (42h vigentes).</small>
						</div>

						<div class="col-md-4 col-12 mb-3">
							<label class="font-weight-bold text-dark small mb-1">Descanso entre turnos:</label>
							<div class="input-group input-group-sm">
								<input type="number" step="0.5" class="form-control font-weight-bold" id="policyMinRestInput" name="min_rest_hours" value="12.0" required>
								<div class="input-group-append">
									<span class="input-group-text">horas</span>
								</div>
							</div>
							<small class="text-muted d-block mt-1">Descanso mínimo de seguridad entre salida y entrada.</small>
						</div>
					</div>

					<div class="p-2 rounded bg-light border text-muted small mb-0">
						<i class="fa-solid fa-circle-info text-info mr-1"></i>
						<strong>Regla de compensatorios:</strong> Los turnos de domingo laborados generan un compensatorio automático programado de lunes a viernes, excluyendo festivos oficiales de Colombia y sin solapar períodos de vacaciones.
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
