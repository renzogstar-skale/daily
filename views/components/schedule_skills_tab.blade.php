@php
	$sMatrix = $skillsMatrix ?? [];
@endphp

<div id="schSkillsSection" class="sch-skills-section" style="display: none;">

	<!-- Top Header & Summary -->
	<div class="mb-3 p-3 bg-white rounded-lg border shadow-sm d-flex flex-wrap align-items-center justify-content-between">
		<div>
			<h5 class="mb-1 font-weight-bold text-dark">
				<i class="fa-solid fa-user-gear mr-2 text-primary"></i> Matriz de Habilidades, Cobertura Apple y Áreas Secundarias
			</h5>
			<p class="small text-muted mb-0">
				Configure competencias de los 23 colaboradores para habilitar soporte en subáreas (Apple, Bodega Digital, Despachos), elegibilidad nocturna y autorización de cobertura cruzada sin inventar asignaciones.
			</p>
		</div>
		<div class="mt-2 mt-md-0">
			<span class="badge badge-success px-3 py-2 font-weight-bold">
				<i class="fa-solid fa-shield-halved mr-1"></i> Control de Habilitaciones Activo
			</span>
		</div>
	</div>

	<!-- Skills Matrix Table -->
	<div class="card shadow-sm border-0 mb-4 overflow-hidden">
		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0" id="tableSkillsMatrix" style="font-size: 0.82rem;">
				<thead class="bg-light">
					<tr>
						<th class="font-weight-700 py-3 text-dark">Colaborador</th>
						<th class="font-weight-700 py-3 text-dark">Área Principal</th>
						<th class="font-weight-700 py-3 text-dark">Cargo Oficial</th>
						<th class="font-weight-700 py-3 text-dark">Cobertura Secundaria & Apple</th>
						<th class="font-weight-700 py-3 text-center text-dark">Nocturnidad</th>
						<th class="font-weight-700 py-3 text-center text-dark">Liderazgo</th>
						<th class="font-weight-700 py-3 text-center text-dark">Caja</th>
						<th class="font-weight-700 py-3 text-center text-dark">Acciones</th>
					</tr>
				</thead>
				<tbody>
					@foreach($sMatrix as $m)
						<tr data-pcode="{{ $m['payroll_code'] }}">
							<td>
								<div class="font-weight-bold text-dark">{{ $m['name'] }}</div>
								<code class="small text-muted">{{ str_replace('DEMO-', 'EJ-', $m['payroll_code']) }}</code>
							</td>
							<td>
								<span class="badge badge-light border font-weight-600 px-2 py-1">
									{{ $m['primary_area'] }}
								</span>
							</td>
							<td>
								<span class="text-muted">{{ $m['cargo'] }}</span>
							</td>
							<td>
								@php $sec = (array)$m['secondary_areas']; @endphp
								@if(!empty($sec))
									@foreach($sec as $sa)
										<span class="badge @if($sa === 'Apple') badge-dark @else badge-info @endif px-2 py-1 mr-1">
											@if($sa === 'Apple') <i class="fa-brands fa-apple mr-1"></i> @endif {{ $sa }}
										</span>
									@endforeach
								@else
									<span class="text-muted small">Solo área principal</span>
								@endif
							</td>
							<td class="text-center">
								@if($m['can_night'])
									<span class="badge badge-dark px-2 py-1 font-weight-bold"><i class="fa-solid fa-moon text-warning mr-1"></i> Habilitado</span>
								@else
									<span class="badge badge-light text-muted border">No</span>
								@endif
							</td>
							<td class="text-center">
								@if($m['can_lead'])
									<span class="badge badge-purple px-2 py-1"><i class="fa-solid fa-star mr-1"></i> Lead</span>
								@else
									<span class="text-muted">—</span>
								@endif
							</td>
							<td class="text-center">
								@if($m['can_cashier'])
									<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-cash-register mr-1"></i> Sí</span>
								@else
									<span class="text-muted">—</span>
								@endif
							</td>
							<td class="text-center">
								<button type="button" class="btn btn-sm btn-outline-secondary font-weight-600 py-1 px-2" onclick="window.editCollaboratorSkill('{{ $m['payroll_code'] }}', '{{ addslashes($m['name']) }}', '{{ $m['primary_area'] }}', {{ json_encode($m['secondary_areas']) }}, {{ $m['can_night'] }}, {{ $m['can_lead'] }}, {{ $m['can_cashier'] }})">
									<i class="fa-solid fa-pen-to-square"></i> Configurar
								</button>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>

</div>

<!-- Modal Configurar Habilidad -->
<div class="modal fade" id="modalEditSkill" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-light py-2 px-3">
				<h6 class="modal-title font-weight-bold mb-0"><i class="fa-solid fa-user-gear mr-1 text-primary"></i> Configurar Competencias y Cobertura</h6>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-3">
				<input type="hidden" id="skillEditPayrollCode">
				<div class="form-group mb-2">
					<label class="small text-muted mb-0">Colaborador:</label>
					<div class="font-weight-bold text-dark h6 mb-0" id="skillEditName">—</div>
				</div>
				<div class="form-group mb-3">
					<label class="small text-muted mb-0">Área Principal:</label>
					<div class="badge badge-light border p-2 d-block font-weight-bold" id="skillEditArea">—</div>
				</div>

				<div class="form-group mb-3">
					<label class="small font-weight-bold text-dark mb-1">Subáreas y Áreas Secundarias Habilitadas:</label>
					<div class="d-flex flex-wrap" style="gap: 0.5rem;" id="skillCheckboxesArea">
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkApple" value="Apple">
							<label class="custom-control-label font-weight-600" for="chkApple"><i class="fa-brands fa-apple mr-1"></i> Apple</label>
						</div>
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkAccesorios" value="Accesorios">
							<label class="custom-control-label font-weight-600" for="chkAccesorios">Accesorios</label>
						</div>
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkBodega" value="Bodega Digital">
							<label class="custom-control-label font-weight-600" for="chkBodega">Bodega Digital</label>
						</div>
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkDespachos" value="Despachos">
							<label class="custom-control-label font-weight-600" for="chkDespachos">Despachos</label>
						</div>
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkVentas" value="Ventas">
							<label class="custom-control-label font-weight-600" for="chkVentas">Ventas</label>
						</div>
						<div class="custom-control custom-checkbox mr-3">
							<input type="checkbox" class="custom-control-input chk-sec-area" id="chkOperaciones" value="Operaciones">
							<label class="custom-control-label font-weight-600" for="chkOperaciones">Operaciones</label>
						</div>
					</div>
				</div>

				<div class="form-group mb-3 border-top pt-2">
					<label class="small font-weight-bold text-dark mb-2">Permisos Especiales:</label>
					<div class="custom-control custom-switch mb-2">
						<input type="checkbox" class="custom-control-input" id="chkCanNight">
						<label class="custom-control-label font-weight-600" for="chkCanNight">
							<i class="fa-solid fa-moon text-warning mr-1"></i> Habilitado para Turnos Nocturnos (7N22-6)
						</label>
					</div>
					<div class="custom-control custom-switch mb-2">
						<input type="checkbox" class="custom-control-input" id="chkCanLead">
						<label class="custom-control-label font-weight-600" for="chkCanLead">
							<i class="fa-solid fa-star text-purple mr-1"></i> Habilitado para Liderazgo / Apertura Llaves
						</label>
					</div>
					<div class="custom-control custom-switch mb-0">
						<input type="checkbox" class="custom-control-input" id="chkCanCashier">
						<label class="custom-control-label font-weight-600" for="chkCanCashier">
							<i class="fa-solid fa-cash-register text-success mr-1"></i> Habilitado para Cuadre de Caja
						</label>
					</div>
				</div>
			</div>
			<div class="modal-footer p-2 bg-light">
				<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnSaveSkillSettings">
					<i class="fa-solid fa-save mr-1"></i> Guardar Habilidades
				</button>
			</div>
		</div>
	</div>
</div>
