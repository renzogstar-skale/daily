		@php
			$planDetailsMap = [
				1 => [
					'incident_ref' => 'INC-2026-004 · Fuga de frío en vitrina lácteas #2',
					'objective' => 'Eliminar merma térmica nocturna y estabilizar temperatura en vitrinas entre 2°C y 5°C.',
					'milestones' => [
						['id' => 1, 'title' => 'Instalación física de cortinas térmicas de noche en vitrinas 1 y 2', 'owner' => 'Bodeguero / Mant.', 'due' => '2026-10-10', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Calibración técnica de sonda digital y termostatos con técnico autorizado', 'owner' => 'Técnico Frío', 'due' => '2026-10-13', 'status' => 'in_progress', 'progress' => 60, 'depends_on' => 'Hito 1: Instalación de cortinas'],
						['id' => 3, 'title' => 'Protocolo de rondas nocturnas 22:00 y 06:00 con registro digital', 'owner' => 'Auxiliar de piso', 'due' => '2026-10-15', 'status' => 'pending', 'progress' => 0, 'depends_on' => 'Hito 2: Calibración técnica']
					],
					'history' => [
						['date' => '2026-10-11', 'type' => 'Fecha Límite', 'detail' => 'Fecha límite reprogramada del 13/10 al 15/10 por demora en entrega de sonda de repuesto.', 'author' => 'Supervisor de Turno'],
						['date' => '2026-10-08', 'type' => 'Creación', 'detail' => 'Plan de acción formulado y vinculado a investigación de causa raíz INC-2026-004.', 'author' => 'Gerente de Sucursal']
					]
				],
				2 => [
					'incident_ref' => 'INV-2026-008 · Vencimiento anticipado por rotación deficiente en góndola',
					'objective' => 'Alcanzar cero mermas por vencimiento de productos lácteos mediante rotación FEFO estricta y liquidación oportuna (-30%).',
					'milestones' => [
						['id' => 1, 'title' => 'Capacitación presencial al personal de reposición sobre protocolo FEFO', 'owner' => 'Supervisor', 'due' => '2026-10-05', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Implantación del checklist diario de barrido FEFO en Piso de Tienda', 'owner' => 'Reponedor', 'due' => '2026-10-08', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Hito 1: Capacitación'],
						['id' => 3, 'title' => 'Validación de 7 días consecutivos sin producto vencido en góndola', 'owner' => 'Gerente', 'due' => '2026-10-12', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Hito 2: Checklist diario']
					],
					'history' => [
						['date' => '2026-10-12', 'type' => 'Cierre', 'detail' => 'Plan completado y verificado con 100% de cumplimiento en checklists de rotación.', 'author' => 'Gerente de Sucursal'],
						['date' => '2026-10-03', 'type' => 'Creación', 'detail' => 'Plan originado tras informe de pérdidas por caducidad en yogures.', 'author' => 'Control de Gestión']
					]
				],
				3 => [
					'incident_ref' => 'INV-2026-012 · Aplastamiento de embalaje y fisura en quesos por sobreestiba',
					'objective' => 'Reducir a cero el daño físico en cajas de queso limitando la estiba a 4 niveles y usando esquineros reforzados.',
					'milestones' => [
						['id' => 1, 'title' => 'Diseño e instalación de señalización de altura máxima en muros de cámara', 'owner' => 'Supervisor', 'due' => '2026-10-15', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Suministro y colocación obligatoria de esquineros de cartón en tarimas', 'owner' => 'Bodeguero', 'due' => '2026-10-18', 'status' => 'in_progress', 'progress' => 50, 'depends_on' => 'Hito 1: Señalización'],
						['id' => 3, 'title' => 'Auditoría semanal de recepción en andén para verificar estiba del proveedor', 'owner' => 'Calidad', 'due' => '2026-10-20', 'status' => 'pending', 'progress' => 0, 'depends_on' => 'Hito 2: Esquineros']
					],
					'history' => [
						['date' => '2026-10-14', 'type' => 'Objetivo', 'detail' => 'Ajuste de especificación: se autoriza estiba de 4 niveles siempre que cuente con esquineros de protección.', 'author' => 'Jefe de Bodega'],
						['date' => '2026-10-13', 'type' => 'Creación', 'detail' => 'Plan iniciado tras detectar cajas aplastadas en cámara fría.', 'author' => 'Supervisor de Turno']
					]
				]
			];
		@endphp

		<div class="card border-0 shadow-xs bg-white mb-3 dairy-bordered">
			<div class="card-body p-4">
				<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
					<div>
						<h5 class="font-weight-bold text-dark mb-1">
							<i class="fa-solid fa-list-check text-primary mr-2"></i>Planes de Acción Correctiva y Preventiva
						</h5>
						<p class="text-muted mb-0 dairy-fs-base">Compromisos operativos vinculados a investigaciones con responsables, hitos y fechas límites.</p>
					</div>
					<div class="d-flex align-items-center mt-2 mt-sm-0" style="gap: 8px;">
						<div class="btn-group btn-group-sm" role="group">
							<button type="button" class="btn btn-outline-secondary active" id="btnPlanViewTable" onclick="$('#planViewTableWrap').show(); $('#planViewTimelineWrap').hide(); $(this).addClass('active'); $('#btnPlanViewTimeline').removeClass('active');">
								<i class="fa-solid fa-list mr-1"></i>Tabla
							</button>
							<button type="button" class="btn btn-outline-secondary" id="btnPlanViewTimeline" onclick="$('#planViewTableWrap').hide(); $('#planViewTimelineWrap').show(); $(this).addClass('active'); $('#btnPlanViewTable').removeClass('active');">
								<i class="fa-solid fa-timeline mr-1"></i>Línea de Tiempo
							</button>
						</div>
						<button class="btn btn-sm btn-primary" onclick="dairyNewPlanDialog();">
							<i class="fa-solid fa-plus mr-1"></i>Nuevo Plan
						</button>
					</div>
				</div>

				<!-- Vista 1: Tabla -->
				<div class="table-responsive" id="planViewTableWrap">
					<table class="table table-hover dairy-fs-sm">
						<thead class="dairy-bg-base">
							<tr>
								<th>CÓDIGO</th>
								<th>ACCIÓN PREVENTIVA / CORRECTIVA</th>
								<th>ORIGEN / INVESTIGACIÓN</th>
								<th>RESPONSABLE</th>
								<th>SUCURSAL</th>
								<th>FECHA LÍMITE</th>
								<th>ESTADO</th>
								<th>ACCIONES</th>
							</tr>
						</thead>
						<tbody>
							@forelse($actionPlans as $plan)
							@php
								$details = $planDetailsMap[$plan['id']] ?? null;
							@endphp
							<tr>
								<td class="font-mono font-weight-bold">AP-{{ str_pad($plan['id'], 4, '0', STR_PAD_LEFT) }}</td>
								<td class="font-weight-500 text-dark">
									{{ $plan['activity_title'] }}
									@if($details)
										<div class="small text-muted font-italic"><i class="fa-solid fa-bullseye mr-1 text-danger"></i>{{ $details['objective'] }}</div>
									@endif
								</td>
								<td>
									@if($details)
										<span class="badge badge-light border text-muted"><i class="fa-solid fa-link mr-1 text-primary"></i>{{ $details['incident_ref'] }}</span>
									@else
										<span class="text-muted">—</span>
									@endif
								</td>
								<td>{{ $plan['assigned_username'] }}</td>
								<td>{{ $plan['branch_name'] }}</td>
								<td class="font-mono">{{ $plan['due_date'] }}</td>
								<td>
									<span class="badge badge-{{ $plan['status'] == 'completed' ? 'success' : ($plan['status'] == 'in_progress' ? 'info' : 'warning') }}">
										{{ ['completed' => 'Cerrado', 'in_progress' => 'En curso', 'open' => 'Abierto'][$plan['status']] ?? $plan['status'] }}
									</span>
								</td>
								<td>
									@if($plan['status'] != 'completed')
									<button class="btn btn-xs btn-outline-success" title="Cerrar plan" onclick="dairyClosePlan({{ $plan['id'] }});"><i class="fa-solid fa-check"></i></button>
									@endif
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="8" class="text-center py-4 text-muted">No hay planes de acción pendientes.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>

				<!-- Vista 2: Línea de Tiempo (Gantt) -->
				<div id="planViewTimelineWrap" style="display: none;">
					@foreach($actionPlans as $plan)
					@php
						$details = $planDetailsMap[$plan['id']] ?? null;
					@endphp
					@if($details)
					<div class="card border rounded p-3 mb-3 bg-light">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<div>
								<span class="font-mono font-weight-bold text-dark mr-2">AP-{{ str_pad($plan['id'], 4, '0', STR_PAD_LEFT) }}</span>
								<strong class="text-dark">{{ $plan['activity_title'] }}</strong>
								<span class="badge badge-{{ $plan['status'] === 'completed' ? 'success' : 'info' }} ml-2">
									{{ ['completed' => 'Cerrado', 'in_progress' => 'En curso', 'open' => 'Abierto'][$plan['status']] ?? $plan['status'] }}
								</span>
							</div>
							<span class="small text-muted">Fecha límite: <strong class="font-mono">{{ $plan['due_date'] }}</strong></span>
						</div>
						<div class="small text-muted mb-2">
							<i class="fa-solid fa-link text-primary mr-1"></i><strong>Origen:</strong> {{ $details['incident_ref'] }}
							<span class="mx-2">·</span>
							<i class="fa-solid fa-bullseye text-danger mr-1"></i><strong>Objetivo:</strong> {{ $details['objective'] }}
						</div>

						<!-- Hitos -->
						<div class="table-responsive bg-white rounded border mb-2">
							<table class="table table-sm mb-0" style="font-size: 11px;">
								<thead class="thead-light">
									<tr>
										<th>Hito</th>
										<th>Tarea</th>
										<th>Responsable</th>
										<th>Límite</th>
										<th>Dependencia</th>
										<th style="width: 120px;">Progreso</th>
										<th class="text-center">Estado</th>
									</tr>
								</thead>
								<tbody>
									@foreach($details['milestones'] as $m)
									<tr>
										<td class="font-weight-bold font-mono">#{{ $m['id'] }}</td>
										<td class="font-weight-600">{{ $m['title'] }}</td>
										<td class="text-muted">{{ $m['owner'] }}</td>
										<td class="font-mono">{{ $m['due'] }}</td>
										<td><span class="badge badge-light border">{{ $m['depends_on'] }}</span></td>
										<td>
											<div class="progress" style="height: 5px;">
												<div class="progress-bar {{ $m['status'] === 'completed' ? 'bg-success' : 'bg-info' }}" style="width: {{ $m['progress'] }}%;"></div>
											</div>
										</td>
										<td class="text-center">
											<span class="badge badge-{{ $m['status'] === 'completed' ? 'success' : ($m['status'] === 'in_progress' ? 'info' : 'secondary') }}">
												{{ $m['status'] === 'completed' ? 'Completado' : ($m['status'] === 'in_progress' ? 'En curso' : 'Pendiente') }}
											</span>
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>

						<!-- Historial -->
						@if(!empty($details['history']))
						<div class="small text-muted">
							<strong><i class="fa-solid fa-clock-rotate-left mr-1"></i>Historial de cambios:</strong>
							@foreach($details['history'] as $h)
								<span class="ml-2">[{{ $h['date'] }}] {{ $h['detail'] }} (<em>{{ $h['author'] }}</em>)</span>
							@endforeach
						</div>
						@endif
					</div>
					@endif
					@endforeach
				</div>
			</div>
		</div>
