{{-- DAILY: Modal de Comprobación de Escenarios de Validación Laboral (Requisito 12) --}}
<div class="modal fade" id="modalValidationScenarios" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered" role="document">
		<div class="modal-content shadow-lg border-0" style="border-radius: 12px;">
			<div class="modal-header bg-light border-bottom py-3 px-4">
				<div class="d-flex align-items-center">
					<span class="mr-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 44px; height: 44px; background-color: #ecfdf5; color: #059669; font-size: 1.3rem;">
						<i class="fa-solid fa-vial-circle-check"></i>
					</span>
					<div>
						<h5 class="modal-title font-weight-bold text-dark mb-0">Comprobación de Escenarios — Doral</h5>
						<p class="text-muted small mb-0 mt-1">Explora las 6 situaciones clave de planificación laboral, cobertura, habilidades y detección estricta de conflictos.</p>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="modal-body p-4 bg-light">
				<div class="row g-3">
					@foreach($validationScenarios as $key => $sc)
						<div class="col-lg-6 col-12 mb-3">
							<div class="card h-100 border shadow-sm" style="border-radius: 8px;">
								<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
									<h6 class="mb-0 font-weight-bold text-dark" style="font-size: 0.95rem;">
										{{ $sc['title'] }}
									</h6>
									<span class="badge {{ $sc['badge'] }} px-2 py-1">{{ $sc['status'] }}</span>
								</div>
								<div class="card-body p-3">
									<div class="d-flex align-items-center text-muted small mb-2">
										<span class="mr-3"><i class="fa-solid fa-calendar-day mr-1 text-primary"></i>Fecha: <strong>{{ date('d/m/Y', strtotime($sc['date'])) }}</strong></span>
										<span><i class="fa-solid fa-layer-group mr-1 text-warning"></i>Área: <strong>{{ $sc['department'] }}</strong></span>
									</div>
									<p class="text-secondary small mb-3" style="line-height: 1.45;">
										{{ $sc['description'] }}
									</p>
									<div class="d-flex justify-content-between align-items-center pt-2 border-top">
										<span class="text-muted" style="font-size: 0.72rem;">Validación DAILY garantizada</span>
										<button type="button" class="btn btn-sm btn-outline-success font-weight-bold" onclick="window.applyValidationScenario('{{ $key }}', '{{ $sc['date'] }}', '{{ $sc['department'] }}')">
											<i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> {{ $sc['action_label'] }}
										</button>
									</div>
								</div>
							</div>
						</div>
					@endforeach
				</div>

				<!-- Info Alert Box -->
				<div class="alert alert-info border-info mt-2 mb-0 d-flex align-items-start" style="border-radius: 8px;">
					<i class="fa-solid fa-circle-info fa-lg mr-3 mt-1 text-info"></i>
					<div class="small">
						<strong>Escenario de ejemplo aislado:</strong> Todos los datos, turnos y colaboraciones corresponden al escenario 
						<em>“Doral”</em>. Las ausencias y disponibilidades no tocan personal real ni generan notificaciones a terceros. 
						Si desea reiniciar las asignaciones a su estado óptimo original, utilice la opción inferior.
					</div>
				</div>
			</div>

			<div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
				<button type="button" class="btn btn-outline-danger font-weight-bold btn-sm" onclick="window.triggerRestoreDemo()">
					<i class="fa-solid fa-rotate-left mr-1"></i> Restaurar únicamente el escenario de ejemplo
				</button>
				<button type="button" class="btn btn-secondary btn-sm font-weight-600 px-3" data-dismiss="modal">
					Cerrar
				</button>
			</div>
		</div>
	</div>
</div>
