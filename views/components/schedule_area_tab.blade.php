{{-- DAILY: Vista Por Área — Cobertura Operativa y Dotación Mínima --}}
<div id="schViewArea" class="sch-view-container" style="display: none;">
	<!-- Area Header -->
	<div class="sch-card mb-3">
		<div class="sch-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h3 class="sch-card-title mb-0">
					<i class="fa-solid fa-layer-group text-warning mr-2"></i>Cobertura Operativa por Área y Departamento
				</h3>
				<p class="text-muted small mb-0 mt-1">
					Monitoreo de dotación mínima de seguridad, personal disponible, vacaciones, compensatorios y validación de cobertura cruzada según matriz de habilidades.
				</p>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2">
				<span class="badge badge-light border text-muted px-2 py-1">
					Semana del {{ date('d/m/Y', strtotime($areaViewData['week_monday'])) }} al {{ date('d/m/Y', strtotime($areaViewData['week_sunday'])) }}
				</span>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-secondary active" id="btnFilterAreaAll">Todas (7 Áreas)</button>
					<button type="button" class="btn btn-outline-secondary" id="btnFilterAreaDeficit">Con Déficit</button>
					<button type="button" class="btn btn-outline-secondary" id="btnFilterAreaOptimal">Óptimas</button>
				</div>
			</div>
		</div>

		<!-- Area Cards Row -->
		<div class="p-3">
			<div class="row">
				@foreach($areaViewData['areas'] as $a)
					<div class="col-xl-4 col-lg-6 col-12 mb-3 area-card-col" data-is-deficit="{{ $a['is_deficit'] ? '1' : '0' }}">
						<div class="card h-100 border @if($a['is_deficit']) border-danger @else border-light @endif shadow-sm" style="border-radius: 8px;">
							<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
								<div class="d-flex align-items-center">
									<span class="mr-2 d-inline-flex align-items-center justify-content-center rounded" style="width: 32px; height: 32px; background-color: {{ $a['color'] }}20; color: {{ $a['color'] }}; font-size: 1rem;">
										<i class="fa-solid {{ $a['icon'] }}"></i>
									</span>
									<div>
										<h6 class="mb-0 font-weight-bold text-dark">{{ $a['name'] }}</h6>
										<span class="text-muted" style="font-size: 0.72rem;">Dotación mín. seguridad: {{ $a['min_safety'] }} personas/día</span>
									</div>
								</div>
								<div>
									@if($a['is_deficit'])
										<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>DÉFICIT</span>
									@else
										<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-check-circle mr-1"></i>ÓPTIMA</span>
									@endif
								</div>
							</div>

							<div class="card-body p-3">
								<!-- Cobertura KPI Box -->
								<div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
									<div>
										<span class="text-muted small d-block">Cobertura Programada</span>
										<span class="font-weight-bold" style="font-size: 1.25rem; color: @if($a['is_deficit']) #ef4444 @else #059669 @endif;">
											{{ $a['coverage_pct'] }}%
										</span>
									</div>
									<div class="text-right">
										<span class="text-muted small d-block">Fórmula Oficial</span>
										<span class="badge badge-light border text-muted" style="font-size: 0.7rem;">
											{{ $a['scheduled'] }} prog. / {{ $a['required_weekly'] }} req.
										</span>
									</div>
								</div>

								<div class="progress mb-3" style="height: 6px;">
									<div class="progress-bar @if($a['is_deficit']) bg-danger @else bg-success @endif" 
										 role="progressbar" style="width: {{ min(100, $a['coverage_pct']) }}%;"></div>
								</div>

								<!-- Key Metrics Grid -->
								<div class="row text-center mb-3" style="font-size: 0.8rem;">
									<div class="col-4 border-right pb-1">
										<span class="text-muted d-block small">Asignados</span>
										<span class="font-weight-bold text-dark" style="font-size: 1rem;">{{ $a['assigned'] }}</span>
									</div>
									<div class="col-4 border-right pb-1">
										<span class="text-muted d-block small">Disponibles</span>
										<span class="font-weight-bold text-success" style="font-size: 1rem;">{{ $a['available'] }}</span>
									</div>
									<div class="col-4 pb-1">
										<span class="text-muted d-block small">Programados</span>
										<span class="font-weight-bold text-primary" style="font-size: 1rem;">{{ $a['scheduled'] }}</span>
									</div>
								</div>

								<div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.78rem;">
									<span class="text-muted"><i class="fa-solid fa-umbrella-beach text-purple mr-1"></i>En Vacaciones:</span>
									<span class="font-weight-bold @if($a['on_vacation'] > 0) text-purple @else text-muted @endif">{{ $a['on_vacation'] }} turnos</span>
								</div>

								<div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.78rem;">
									<span class="text-muted"><i class="fa-solid fa-calendar-day text-secondary mr-1"></i>En Compensatorio:</span>
									<span class="font-weight-bold text-dark">{{ $a['on_compensatory'] }} turnos</span>
								</div>

								<div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.78rem;">
									<span class="text-muted"><i class="fa-solid fa-sun text-warning mr-1"></i>Aperturas / <i class="fa-solid fa-moon text-dark mr-1"></i>Cierres:</span>
									<span class="font-weight-bold text-dark">{{ $a['openings'] }} A / {{ $a['closings'] }} C</span>
								</div>

								<div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="font-size: 0.78rem;">
									<span class="text-muted"><i class="fa-solid fa-user-shield text-info mr-1"></i>Apoyo cruzado autorizado:</span>
									<span class="badge badge-info" title="Colaboradores con competencia secundaria para esta área en la Matriz de Habilidades">
										{{ $a['cross_authorized_count'] }} colaboradores
									</span>
								</div>

								<div class="d-flex justify-content-between align-items-center pt-2 mt-2">
									<span class="small font-weight-bold @if($a['surplus_deficit'] < 0) text-danger @else text-success @endif">
										<i class="fa-solid @if($a['surplus_deficit'] < 0) fa-circle-minus @else fa-circle-plus @endif mr-1"></i>
										Balance: {{ $a['surplus_deficit'] >= 0 ? '+' : '' }}{{ $a['surplus_deficit'] }} turnos
									</span>
									<button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="window.switchScheduleView('weekly'); $('#filterArea').val('{{ strtolower($a['name']) }}').trigger('change');">
										<i class="fa-solid fa-filter mr-1"></i>Ver en Grilla
									</button>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Summary & Cross-Coverage Rules Card -->
		<div class="card-footer bg-white border-top p-3">
			<div class="row align-items-center">
				<div class="col-md-8 col-12">
					<h6 class="font-weight-bold text-dark mb-1"><i class="fa-solid fa-shield-halved text-success mr-2"></i>Reglas operativas de Cobertura y Competencias</h6>
					<p class="text-muted small mb-0">
						Para cubrir turnos en <strong>Apple</strong> u otras áreas especializadas, el colaborador debe poseer la certificación activa en la Matriz de Habilidades. El sistema bloquea automáticamente asignaciones no autorizadas.
					</p>
				</div>
				<div class="col-md-4 col-12 text-md-right mt-2 mt-md-0">
					<button type="button" class="btn btn-sm btn-outline-purple font-weight-bold" onclick="window.switchScheduleView('skills');">
						<i class="fa-solid fa-user-gear mr-1"></i>Ver Matriz de Habilidades
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
