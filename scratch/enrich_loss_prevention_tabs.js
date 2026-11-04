const fs = require('fs');
const filePath = 'views/loss_prevention.blade.php';
let content = fs.readFileSync(filePath, 'utf8');

// 1. Update tab navigation buttons
const oldTabs = `<nav class="lp-tabs-bar" aria-label="Secciones de Prevención de Pérdidas">
		<button type="button" class="lp-tab-item active" id="tab-btn-expedientes" onclick="switchLpTab('expedientes')">
			<i class="fa-regular fa-clipboard"></i> Incidencias y Expedientes (8)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-mapa" onclick="switchLpTab('mapa')">
			<i class="fa-solid fa-map-location-dot"></i> Mapa de Sucursales
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-disponibilidad" onclick="switchLpTab('disponibilidad')">
			<i class="fa-solid fa-users"></i> Disponibilidad (112)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-tareas" onclick="switchLpTab('tareas')">
			<i class="fa-solid fa-list-check"></i> Tareas Multi-Área
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-planes" onclick="switchLpTab('planes')">
			<i class="fa-solid fa-shield"></i> Planes Preventivos (0)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-anomalias" onclick="switchLpTab('anomalias')" style="color: #dc2626;">
			<i class="fa-solid fa-triangle-exclamation"></i> Detección de Anomalías (4)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-custodia-imei" onclick="switchLpTab('custodia-imei')" style="color: #2563eb;">
			<i class="fa-solid fa-mobile-screen-button"></i> Cadena de Custodia IMEI
		</button>
	</nav>`;

const newTabs = `<nav class="lp-tabs-bar" aria-label="Secciones de Prevención de Pérdidas">
		<button type="button" class="lp-tab-item active" id="tab-btn-expedientes" onclick="switchLpTab('expedientes')">
			<i class="fa-regular fa-clipboard"></i> Incidencias y Expedientes (8)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-mapa" onclick="switchLpTab('mapa')">
			<i class="fa-solid fa-map-location-dot"></i> Mapa de Sucursales (10)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-disponibilidad" onclick="switchLpTab('disponibilidad')">
			<i class="fa-solid fa-users"></i> Disponibilidad (112)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-tareas" onclick="switchLpTab('tareas')">
			<i class="fa-solid fa-list-check"></i> Tareas Multi-Área (8)
		</button>
		<button type="button" class="lp-tab-item" id="tab-btn-planes" onclick="switchLpTab('planes')">
			<i class="fa-solid fa-shield"></i> Planes Preventivos (6)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-anomalias" onclick="switchLpTab('anomalias')" style="color: #dc2626;">
			<i class="fa-solid fa-triangle-exclamation"></i> Detección de Anomalías (6)
		</button>
		<button type="button" class="lp-tab-item font-weight-bold" id="tab-btn-custodia-imei" onclick="switchLpTab('custodia-imei')" style="color: #2563eb;">
			<i class="fa-solid fa-mobile-screen-button"></i> Cadena de Custodia IMEI
		</button>
	</nav>`;

content = content.replace(oldTabs.replace(/\r\n/g, '\n'), newTabs.replace(/\r\n/g, '\n'));

// 2. Build detailed sections for MAPA, DISPONIBILIDAD, TAREAS, PLANES
const newPanes = `	<!-- ====================================================================
	     PANEL TAB 2: MAPA Y ESTADO OPERATIVO DE SUCURSALES (10)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-mapa">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-map-location-dot text-success mr-2"></i>Mapa y Monitor de Sucursales en Red (10)</h3>
					<p class="text-muted small m-0">Monitoreo 360° en tiempo real de sucursales, nivel de merma, alertas de seguridad y cobertura de guardia.</p>
				</div>
				<div class="d-flex align-items-center gap-2">
					<span class="badge badge-success px-3 py-2 mr-2 font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i> 7 Operativas</span>
					<span class="badge badge-warning px-3 py-2 mr-2 font-weight-bold text-dark"><i class="fa-solid fa-triangle-exclamation mr-1"></i> 2 En Atención</span>
					<span class="badge badge-danger px-3 py-2 mr-2 font-weight-bold"><i class="fa-solid fa-circle-xmark mr-1"></i> 1 Crítica</span>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			<div class="row">
				@php
				$branchDetails = [
					['name' => 'Alkosto Calle 80', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.18%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$1,299.00', 'guard' => 'Enrique Peña (Líder)', 'phone' => '+57 311 405 9812', 'cctv' => '98.5% uptime'],
					['name' => 'Alkosto Envigado', 'city' => 'Medellín, Antioquia', 'status' => 'Atención', 'badge' => 'badge-warning text-dark', 'shrink' => '1.62%', 'target' => '1.50%', 'cases' => 2, 'risk_val' => '$3,420.00', 'guard' => 'Jorge Valencia (Muelle)', 'phone' => '+57 314 821 7701', 'cctv' => '94.0% uptime'],
					['name' => 'Alkosto Cali Sur', 'city' => 'Cali, Valle del Cauca', 'status' => 'Crítica', 'badge' => 'badge-danger', 'shrink' => '2.15%', 'target' => '1.50%', 'cases' => 3, 'risk_val' => '$4,850.00', 'guard' => 'Mario Ospina (CCTV)', 'phone' => '+57 310 933 1184', 'cctv' => '91.2% uptime'],
					['name' => 'Alkosto Barranquilla', 'city' => 'Barranquilla, Atlántico', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.05%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Andrea Torres (Supervisor)', 'phone' => '+57 318 644 2090', 'cctv' => '99.1% uptime'],
					['name' => 'Alkosto Bucaramanga', 'city' => 'Bucaramanga, Santander', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.24%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$450.00', 'guard' => 'Camilo Duarte (Piso)', 'phone' => '+57 312 550 4918', 'cctv' => '97.8% uptime'],
					['name' => 'Alkosto Tintal', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.30%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Diana Pineda (Bóveda)', 'phone' => '+57 315 771 8832', 'cctv' => '98.0% uptime'],
					['name' => 'Alkosto Villas', 'city' => 'Bogotá, D.C.', 'status' => 'Atención', 'badge' => 'badge-warning text-dark', 'shrink' => '1.58%', 'target' => '1.50%', 'cases' => 1, 'risk_val' => '$890.00', 'guard' => 'Felipe Gómez (Cajas)', 'phone' => '+57 317 220 5410', 'cctv' => '95.5% uptime'],
					['name' => 'Alkosto 170', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '0.98%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Claudia Morales (Seguridad)', 'phone' => '+57 313 449 0182', 'cctv' => '99.5% uptime'],
					['name' => 'Alkosto Suba', 'city' => 'Bogotá, D.C.', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.10%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Sandra Milena (CCTV)', 'phone' => '+57 316 880 7741', 'cctv' => '98.8% uptime'],
					['name' => 'Alkosto Cali Norte', 'city' => 'Cali, Valle del Cauca', 'status' => 'Operativa', 'badge' => 'badge-success', 'shrink' => '1.35%', 'target' => '1.50%', 'cases' => 0, 'risk_val' => '$0.00', 'guard' => 'Mauricio Vélez (Control)', 'phone' => '+57 319 332 5599', 'cctv' => '97.0% uptime']
				];
				@endphp

				@foreach($branchDetails as $bd)
					<div class="col-lg-4 col-md-6 mb-3">
						<div class="p-3 border rounded shadow-sm h-100 d-flex flex-column justify-content-between" style="background:#ffffff; border-radius:12px; border-color:#e2e8f0 !important;">
							<div>
								<div class="d-flex align-items-center justify-content-between mb-2">
									<div>
										<h5 class="font-weight-bold mb-0 text-dark" style="font-size: 1.05rem;">{{ $bd['name'] }}</h5>
										<span class="text-muted small"><i class="fa-solid fa-location-dot mr-1 text-danger"></i>{{ $bd['city'] }}</span>
									</div>
									<span class="badge {{ $bd['badge'] }} px-2 py-1 font-weight-bold">{{ $bd['status'] }}</span>
								</div>
								
								<div class="bg-light p-2 rounded mb-2" style="font-size: 0.82rem;">
									<div class="d-flex justify-content-between mb-1">
										<span class="text-muted">Merma actual:</span>
										<strong class="{{ floatval($bd['shrink']) > 1.5 ? 'text-danger' : 'text-success' }}">{{ $bd['shrink'] }} <span class="text-muted font-weight-normal">(meta {{ $bd['target'] }})</span></strong>
									</div>
									<div class="d-flex justify-content-between mb-1">
										<span class="text-muted">Expedientes activos:</span>
										<strong class="{{ $bd['cases'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $bd['cases'] }} ({{ $bd['risk_val'] }})</strong>
									</div>
									<div class="d-flex justify-content-between">
										<span class="text-muted">CCTV & Antenas EAS:</span>
										<span class="text-dark font-weight-600"><i class="fa-solid fa-video text-muted mr-1"></i>{{ $bd['cctv'] }}</span>
									</div>
								</div>

								<div class="small mb-3 text-muted">
									<i class="fa-solid fa-user-shield text-primary mr-1"></i> Oficial a cargo: <strong class="text-dark">{{ $bd['guard'] }}</strong><br>
									<i class="fa-solid fa-phone text-muted mr-1"></i> {{ $bd['phone'] }}
								</div>
							</div>

							<div class="d-flex gap-2">
								<button type="button" class="btn btn-xs btn-outline-success font-weight-bold flex-fill py-1" onclick="switchLpTab('expedientes')">
									<i class="fa-solid fa-folder-open mr-1"></i> Ver Casos
								</button>
								<button type="button" class="btn btn-xs btn-outline-primary font-weight-bold flex-fill py-1" onclick="openCallModal(1, '{{ $bd['name'] }}', 1, '{{ $bd['guard'] }}')">
									<i class="fa-solid fa-phone mr-1"></i> Contactar
								</button>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANEL TAB 3: DISPONIBILIDAD DE PERSONAL (112 COLABORADORES EN TURNO)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-disponibilidad">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-users text-primary mr-2"></i>Directorio de Personal y Disponibilidad Operativa (112)</h3>
					<p class="text-muted small m-0">Colaboradores en turno activo, guardias de seguridad, operadores CCTV y custodios de mercancía sensible.</p>
				</div>
				<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
			</div>

			{{-- KPIs de Personal --}}
			<div class="row mb-3">
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PERSONAL EN TURNO</span>
						<h4 class="font-weight-bold text-dark mb-0">112 activos</h4>
						<small class="text-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i> Cobertura 100% de puestos</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PREVENCIÓN Y SEGURIDAD</span>
						<h4 class="font-weight-bold text-success mb-0">24 oficiales</h4>
						<small class="text-muted">10 tiendas + centro monitoreo</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">BODEGA Y MUELLES WMS</span>
						<h4 class="font-weight-bold text-info mb-0">38 operadores</h4>
						<small class="text-muted">Recepciones y transferencias</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">CAJAS Y SERVICIO</span>
						<h4 class="font-weight-bold text-primary mb-0">50 cajeros/jefes</h4>
						<small class="text-muted">Líneas de cobro y devoluciones</small>
					</div>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th>Colaborador</th>
							<th>Cargo / Función</th>
							<th>Área Operativa</th>
							<th>Sucursal Asignada</th>
							<th>Horario / Turno</th>
							<th>Estado</th>
							<th class="text-center" style="width: 170px;">Acciones Rápidas</th>
						</tr>
					</thead>
					<tbody>
						@php
						$staffRoster = [
							['name' => 'Renzo Giovine', 'role' => 'Gerente de Prevención 360°', 'dept' => 'Dirección de Pérdidas', 'branch' => 'Corporativo · Calle 80', 'shift' => '08:00 – 18:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 311 000 1122'],
							['name' => 'Carlos Mendoza', 'role' => 'Custodio de Bóveda y Tecnología', 'dept' => 'Bóveda de Seguridad', 'branch' => 'Alkosto Calle 80', 'shift' => '07:00 – 16:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 311 405 9812'],
							['name' => 'Lucía Restrepo', 'role' => 'Jefe de Cajas y Arqueos', 'dept' => 'Cajas y Facturación', 'branch' => 'Alkosto Calle 80', 'shift' => '09:00 – 19:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 312 901 3344'],
							['name' => 'Jorge Valencia', 'role' => 'Supervisor de Recepción WMS', 'dept' => 'Muelle de Carga', 'branch' => 'Alkosto Envigado', 'shift' => '06:00 – 15:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 314 821 7701'],
							['name' => 'Mario Ospina', 'role' => 'Analista Central CCTV', 'dept' => 'Monitoreo y Alarmas', 'branch' => 'Alkosto Cali Sur', 'shift' => '14:00 – 22:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 310 933 1184'],
							['name' => 'Andrea Torres', 'role' => 'Auditora de Calidad y Devoluciones', 'dept' => 'Servicio al Cliente', 'branch' => 'Alkosto Barranquilla', 'shift' => '08:00 – 17:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 318 644 2090'],
							['name' => 'Camilo Duarte', 'role' => 'Oficial de Seguridad en Piso', 'dept' => 'Pasillos de Alta Gama', 'branch' => 'Alkosto Bucaramanga', 'shift' => '10:00 – 20:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 312 550 4918'],
							['name' => 'Diana Pineda', 'role' => 'Líder de Control FEFO', 'dept' => 'Cámaras de Frío / Carnes', 'branch' => 'Alkosto Tintal', 'shift' => '06:00 – 15:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 315 771 8832'],
							['name' => 'Felipe Gómez', 'role' => 'Supervisor de Cajas', 'dept' => 'POS y Descuentos', 'branch' => 'Alkosto Villas', 'shift' => '12:00 – 21:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 317 220 5410'],
							['name' => 'Claudia Morales', 'role' => 'Guardia de Muelle y Acceso', 'dept' => 'Control de Despachos', 'branch' => 'Alkosto 170', 'shift' => '07:00 – 16:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 313 449 0182'],
							['name' => 'Sandra Milena', 'role' => 'Operadora CCTV y Antenas EAS', 'dept' => 'Seguridad Electrónica', 'branch' => 'Alkosto Suba', 'shift' => '08:00 – 18:00', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 316 880 7741'],
							['name' => 'Mauricio Vélez', 'role' => 'Coordinador de Inventario Cíclico', 'dept' => 'Conteos Físicos', 'branch' => 'Alkosto Cali Norte', 'shift' => '07:00 – 16:30', 'status' => 'En turno activo', 'badge' => 'badge-success', 'phone' => '+57 319 332 5599']
						];
						@endphp

						@foreach($staffRoster as $st)
							<tr>
								<td>
									<strong>{{ $st['name'] }}</strong><br>
									<span class="text-muted small"><i class="fa-solid fa-phone mr-1" style="font-size:10px;"></i>{{ $st['phone'] }}</span>
								</td>
								<td>{{ $st['role'] }}</td>
								<td><span class="badge badge-light border text-dark">{{ $st['dept'] }}</span></td>
								<td>{{ $st['branch'] }}</td>
								<td class="small">{{ $st['shift'] }}</td>
								<td><span class="badge {{ $st['badge'] }} px-2 py-1">{{ $st['status'] }}</span></td>
								<td class="text-center">
									<div class="btn-group btn-group-sm">
										<button type="button" class="btn btn-outline-success btn-xs px-2" title="Llamada de voz inmediata" onclick="openCallModal(1, '{{ $st['name'] }}', 1, '{{ $st['dept'] }}')">
											<i class="fa-solid fa-phone"></i>
										</button>
										<button type="button" class="btn btn-outline-primary btn-xs px-2" title="Mensaje WhatsApp / Interno" onclick="openMsgModal(1, '{{ $st['name'] }}')">
											<i class="fa-solid fa-comment-dots"></i>
										</button>
										<button type="button" class="btn btn-outline-dark btn-xs px-2" title="Asignar tarea de prevención" onclick="switchLpTab('tareas')">
											<i class="fa-solid fa-list-check"></i>
										</button>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ====================================================================
	     PANEL TAB 4: TAREAS MULTI-ÁREA (8 ASIGNACIONES EN CURSO)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-tareas">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-list-check text-success mr-2"></i>Tareas Operativas Multi-Área de Prevención (8)</h3>
					<p class="text-muted small m-0">Verificaciones de stock en bóveda, auditoría de precintos de muelle, calibración de antenas EAS y reconteos ciegos.</p>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-primary font-weight-bold" onclick="alert('Formulario de nueva asignación express de prevención abierto.');">
						<i class="fa-solid fa-plus mr-1"></i> Nueva Asignación
					</button>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			{{-- Filtros Rápidos de Tareas --}}
			<div class="d-flex gap-2 mb-3">
				<button class="btn btn-xs btn-dark font-weight-bold px-3">Todas (8)</button>
				<button class="btn btn-xs btn-outline-danger font-weight-bold px-3">Urgentes (3)</button>
				<button class="btn btn-xs btn-outline-primary font-weight-bold px-3">En Proceso (3)</button>
				<button class="btn btn-xs btn-outline-success font-weight-bold px-3">Verificadas (2)</button>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th style="width: 120px;">Código</th>
							<th>Descripción de la Tarea</th>
							<th>Área / Proceso</th>
							<th>Sucursal</th>
							<th>Responsable</th>
							<th>Vencimiento</th>
							<th>Prioridad</th>
							<th>Estado</th>
							<th class="text-center" style="width: 140px;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@php
						$lpTasks = [
							['code' => 'TAR-SEC-0104', 'title' => 'Reconteo ciego de Smartphones en Bóveda', 'sub' => 'Cotejo físico de 28 equipos iPhone 15 vs. ledger serial', 'area' => 'Tecnología · Bóveda', 'branch' => 'Alkosto Calle 80', 'resp' => 'Carlos Mendoza', 'due' => 'Hoy 18:00', 'prio' => 'Urgente', 'prio_badge' => 'badge-danger', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0105', 'title' => 'Auditoría de Precintos en Muelle de Descarga', 'sub' => 'Validación de candados satelitales en camión T-8821', 'area' => 'Logística WMS', 'branch' => 'Alkosto Envigado', 'resp' => 'Jorge Valencia', 'due' => 'Hoy 20:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0106', 'title' => 'Calibración y Prueba de Antenas EAS Sensormatic', 'sub' => 'Prueba con tag de prueba en acceso principal y cajas', 'area' => 'Seguridad Física', 'branch' => 'Alkosto Cali Sur', 'resp' => 'Mario Ospina', 'due' => 'Mañana 10:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0107', 'title' => 'Inspección de Jaula de Licores Importados', 'sub' => 'Verificación de collarines magnéticos y stock físico', 'area' => 'Bebidas Premium', 'branch' => 'Alkosto Tintal', 'resp' => 'Diana Pineda', 'due' => 'Hoy 19:30', 'prio' => 'Media', 'prio_badge' => 'badge-info', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0108', 'title' => 'Peritaje Técnico de Devoluciones de TV 65"', 'sub' => 'Comprobación de accesorios, pantalla y número de serie', 'area' => 'Servicio al Cliente', 'branch' => 'Alkosto Barranquilla', 'resp' => 'Andrea Torres', 'due' => 'Mañana 12:00', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'En Curso', 'status_badge' => 'badge-primary'],
							['code' => 'TAR-SEC-0109', 'title' => 'Arqueo Sorpresivo de Líneas de Cajas 01 a 08', 'sub' => 'Validación de efectivo, transferencias y notas de crédito', 'area' => 'Cajas y POS', 'branch' => 'Alkosto Bucaramanga', 'resp' => 'Camilo Duarte', 'due' => 'Hoy 17:00', 'prio' => 'Urgente', 'prio_badge' => 'badge-danger', 'status' => 'Verificada', 'status_badge' => 'badge-success'],
							['code' => 'TAR-SEC-0110', 'title' => 'Ajuste de Ángulo en Cámaras Domo Pasillo 4', 'sub' => 'Eliminar punto ciego en góndola de perfumes de lujo', 'area' => 'CCTV Electrónico', 'branch' => 'Alkosto Suba', 'resp' => 'Sandra Milena', 'due' => '05 Nov 14:00', 'prio' => 'Media', 'prio_badge' => 'badge-info', 'status' => 'Pendiente', 'status_badge' => 'badge-secondary'],
							['code' => 'TAR-SEC-0111', 'title' => 'Verificación de Registro Termógrafo Cámaras', 'sub' => 'Cotejo de temperatura continua (2.8 °C) en lácteos y carnes', 'area' => 'Cadena de Frío', 'branch' => 'Alkosto Villas', 'resp' => 'Felipe Gómez', 'due' => 'Hoy 16:30', 'prio' => 'Alta', 'prio_badge' => 'badge-warning text-dark', 'status' => 'Verificada', 'status_badge' => 'badge-success']
						];
						@endphp

						@foreach($lpTasks as $t)
							<tr>
								<td><strong>{{ $t['code'] }}</strong></td>
								<td>
									<strong class="text-dark">{{ $t['title'] }}</strong><br>
									<span class="text-muted small">{{ $t['sub'] }}</span>
								</td>
								<td><span class="badge badge-light border text-dark">{{ $t['area'] }}</span></td>
								<td>{{ $t['branch'] }}</td>
								<td><i class="fa-solid fa-user-check text-muted mr-1"></i>{{ $t['resp'] }}</td>
								<td class="small font-weight-bold text-dark">{{ $t['due'] }}</td>
								<td><span class="badge {{ $t['prio_badge'] }} px-2 py-1">{{ $t['prio'] }}</span></td>
								<td><span class="badge {{ $t['status_badge'] }} px-2 py-1">{{ $t['status'] }}</span></td>
								<td class="text-center">
									@if($t['status'] === 'Verificada')
										<button class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="alert('Tarea verificada satisfactoriamente con acta digital.');">
											<i class="fa-solid fa-file-circle-check mr-1 text-success"></i> Acta
										</button>
									@else
										<button class="btn btn-xs btn-success font-weight-bold" onclick="alert('Marcando tarea como completada y registrando auditoría.');">
											<i class="fa-solid fa-check mr-1"></i> Completar
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

	<!-- ====================================================================
	     PANEL TAB 5: PLANES PREVENTIVOS DE MITIGACIÓN 360° (6)
	     ==================================================================== -->
	<div class="lp-tab-pane d-none" id="pane-planes">
		<div class="lp-table-card p-4">
			<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
				<div>
					<h3 class="font-weight-bold mb-1"><i class="fa-solid fa-shield text-success mr-2"></i>Planes Preventivos y Matriz de Mitigación de Riesgos (6)</h3>
					<p class="text-muted small m-0">Estrategias operativas preventivas en red contra merma desconocida, hurto de alta gama y fallas de frío.</p>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-sm btn-success font-weight-bold" onclick="alert('Formulario de nuevo plan preventivo anual abierto.');">
						<i class="fa-solid fa-shield-plus mr-1"></i> Nuevo Plan Preventivo
					</button>
					<button class="btn btn-sm btn-outline-secondary" onclick="switchLpTab('expedientes')"><i class="fa-solid fa-arrow-left mr-1"></i> Volver a Expedientes</button>
				</div>
			</div>

			{{-- KPIs de Efectividad Preventiva --}}
			<div class="row mb-3">
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">PLANES ACTIVOS</span>
						<h4 class="font-weight-bold text-success mb-0">6 programas</h4>
						<small class="text-muted">Implementados en 10 sucursales</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">COBERTURA DE MITIGACIÓN</span>
						<h4 class="font-weight-bold text-primary mb-0">94.2%</h4>
						<small class="text-success font-weight-bold"><i class="fa-solid fa-shield-check mr-1"></i> Riesgos críticos cubiertos</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">REDUCCIÓN MENSUAL ESTIMADA</span>
						<h4 class="font-weight-bold text-dark mb-0">-$38,400</h4>
						<small class="text-success font-weight-bold">&darr; 28% merma evitada</small>
					</div>
				</div>
				<div class="col-md-3 col-sm-6 mb-2">
					<div class="card border-0 bg-light p-3">
						<span class="text-muted small font-weight-bold">AUDITORÍAS DE VERIFICACIÓN</span>
						<h4 class="font-weight-bold text-info mb-0">100% al día</h4>
						<small class="text-muted">Sin desviaciones normativas</small>
					</div>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table table-hover table-sm border">
					<thead class="thead-light">
						<tr>
							<th style="width: 140px;">Código Plan</th>
							<th>Programa Preventivo / Objetivo</th>
							<th>Riesgo Crítico Mitigado</th>
							<th>Medida Operativa Implementada</th>
							<th>Sucursales</th>
							<th>Meta de Merma</th>
							<th style="width: 110px;">Avance</th>
							<th>Estado</th>
							<th class="text-center" style="width: 120px;">Acción</th>
						</tr>
					</thead>
					<tbody>
						@php
						$preventivePlans = [
							[
								'code' => 'PLN-PREV-2026-01',
								'title' => 'Protocolo Anti-Hurto en Dispositivos Móviles y Bóveda',
								'risk' => 'Hurto interno y sustracción de smartphones de gama alta (IMEI)',
								'measure' => 'Bóveda con doble cerradura biométrica, precinto con serial único y escaneo obligatorio 1:1 en cada traslado.',
								'branches' => '10 Tiendas (Red Completa)',
								'target' => '0 faltantes en equipos celulares',
								'progress' => 96,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-02',
								'title' => 'Cadena de Frío Inteligente y Control FEFO Dinámico',
								'risk' => 'Descomposición prematura y rotura de empaque en lácteos y carnes',
								'measure' => 'Sensores termógrafos IoT con alarma acústica en cámaras y rebaja preventiva automatizada a 4 días de caducar.',
								'branches' => 'Calle 80, Envigado, Tintal',
								'target' => 'Merma perecederos ≤ 1.10%',
								'progress' => 92,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-03',
								'title' => 'Blindaje de Línea de Cajas contra "Sweethearting"',
								'risk' => 'Omisión intencional de escaneo y descuentos manuales no autorizados en POS',
								'measure' => 'Cámaras cenitales IA sobre balanza de caja y requerimiento obligatorio de token gerencial para descuentos >15%.',
								'branches' => '10 Tiendas (Red Completa)',
								'target' => 'Diferencias de arqueo ≤ $15/día',
								'progress' => 95,
								'status' => 'Activo',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-04',
								'title' => 'Recepción Ciega Estandarizada en Muelle de Proveedores',
								'risk' => 'Recepción de bultos incompletos o mercancía averiada no declarada',
								'measure' => 'Conteo ciego con terminales Zebra TC57 antes de cotejo con Orden de Compra; rechazo automático ante faltante.',
								'branches' => 'Envigado, Cali Sur, Barranquilla',
								'target' => '100% reclamaciones a proveedor conciliadas',
								'progress' => 88,
								'status' => 'En Ejecución',
								'badge' => 'badge-primary'
							],
							[
								'code' => 'PLN-PREV-2026-05',
								'title' => 'Trazabilidad y Peritaje Técnico en Devoluciones de Clientes',
								'risk' => 'Reingreso de electrodomésticos quemados, manipulados o con seriales falsos',
								'measure' => 'Protocolo de cuarentena técnica 24h con registro fotográfico antes de emitir nota crédito o reposición.',
								'branches' => 'Calle 80, Bucaramanga, Suba',
								'target' => 'Cero garantías falsas aprobadas',
								'progress' => 100,
								'status' => 'Verificado',
								'badge' => 'badge-success'
							],
							[
								'code' => 'PLN-PREV-2026-06',
								'title' => 'Protección Electrónica EAS en Pasillos de Licores y Conservas',
								'risk' => 'Hurto por ocultamiento de botellas premium y enlatados gourmet en piso de venta',
								'measure' => 'Collarines magnéticos blindados para botellas y etiquetas spider en cajas de alta gama con desactivador en caja.',
								'branches' => 'Villas, 170, Cali Norte',
								'target' => 'Merma en licores ≤ 0.70%',
								'progress' => 90,
								'status' => 'Activo',
								'badge' => 'badge-success'
							]
						];
						@endphp

						@foreach($preventivePlans as $pl)
							<tr>
								<td><strong>{{ $pl['code'] }}</strong></td>
								<td>
									<strong class="text-dark">{{ $pl['title'] }}</strong><br>
									<span class="text-muted small"><i class="fa-solid fa-bullseye mr-1 text-success"></i>Meta: {{ $pl['target'] }}</span>
								</td>
								<td><span class="badge badge-light border text-danger font-weight-bold">{{ $pl['risk'] }}</span></td>
								<td class="small text-dark">{{ $pl['measure'] }}</td>
								<td><span class="badge badge-secondary px-2 py-1">{{ $pl['branches'] }}</span></td>
								<td class="small font-weight-bold text-success">{{ $pl['target'] }}</td>
								<td>
									<div class="d-flex align-items-center">
										<div class="progress flex-fill mr-2" style="height: 6px;">
											<div class="progress-bar bg-success" style="width: {{ $pl['progress'] }}%;"></div>
										</div>
										<span class="small font-weight-bold">{{ $pl['progress'] }}%</span>
									</div>
								</td>
								<td><span class="badge {{ $pl['badge'] }} px-2 py-1">{{ $pl['status'] }}</span></td>
								<td class="text-center">
									<button class="btn btn-xs btn-outline-success font-weight-bold" onclick="alert('Detalle del plan {{ $pl['code'] }} y métricas de efectividad abiertas.');">
										<i class="fa-solid fa-chart-line mr-1"></i> Métricas
									</button>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>`;

// Replace from line 676 to line 795
const regexOldPanes = /<!-- ====================================================================\s+PANEL TAB 2: MAPA DE SUCURSALES[\s\S]*?<!-- ====================================================================\s+9\. SLIDE-OVER DRAWER/m;

if (!regexOldPanes.test(content)) {
    // Try matching <div class="lp-tab-pane d-none" id="pane-mapa"> up to 9. SLIDE-OVER DRAWER
    const regexOldPanes2 = /<div class="lp-tab-pane d-none" id="pane-mapa">[\s\S]*?(?=\t<!-- ====================================================================\s+9\. SLIDE-OVER DRAWER)/m;
    if (regexOldPanes2.test(content)) {
        content = content.replace(regexOldPanes2, newPanes.trim() + '\n\n');
        console.log("Replaced using regexOldPanes2!");
    } else {
        console.error("Could not find old panes!");
        process.exit(1);
    }
} else {
    content = content.replace(regexOldPanes, newPanes.trim() + '\n\n\t<!-- ====================================================================\n\t     9. SLIDE-OVER DRAWER');
    console.log("Replaced using regexOldPanes!");
}

// 3. Enrich pane-anomalias with 2 additional high-value retail anomaly cases
const oldAnomaliesTbody = `						<tr class="table-danger">
							<td><span class="badge badge-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Ajuste Inusual</span></td>
							<td><strong>AJU-004118</strong><br><small class="text-muted">Ajuste de inventario negativo (-12 unids)</small></td>
							<td>DAILY Central · Bodega</td>
							<td>Frecuencia 4x superior al promedio semanal</td>
							<td class="text-right font-weight-bold text-danger">$1,040.00</td>
							<td><span class="badge badge-danger">CRÍTICO</span></td>
							<td class="small text-muted">02 nov 2026 14:11</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-eye mr-1"></i> Investigar
								</a>
							</td>
						</tr>`;

const newAnomaliesTbody = `						<tr class="table-danger">
							<td><span class="badge badge-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Ajuste Inusual</span></td>
							<td><strong>AJU-004118</strong><br><small class="text-muted">Ajuste de inventario negativo (-12 unids)</small></td>
							<td>Alkosto Calle 80 · Bodega</td>
							<td>Frecuencia 4x superior al promedio semanal</td>
							<td class="text-right font-weight-bold text-danger">$1,040.00</td>
							<td><span class="badge badge-danger">CRÍTICO</span></td>
							<td class="small text-muted">03 nov 2026 14:11</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-eye mr-1"></i> Investigar
								</a>
							</td>
						</tr>
						<tr class="table-danger">
							<td><span class="badge badge-danger font-weight-bold"><i class="fa-solid fa-barcode mr-1"></i> Serial Duplicado en Venta</span></td>
							<td><strong>POS-TKT-9042</strong><br><small class="text-muted">IMEI 354892019948210</small></td>
							<td>Alkosto Cali Sur · Cajas</td>
							<td>Mismo IMEI vendido simultáneamente en 2 sucursales distintas</td>
							<td class="text-right font-weight-bold text-danger">$1,299.00</td>
							<td><span class="badge badge-danger">CRÍTICO</span></td>
							<td class="small text-muted">03 nov 2026 13:20</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-danger py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-shield-halved mr-1"></i> Bloquear Serial
								</a>
							</td>
						</tr>`;

content = content.replace(oldAnomaliesTbody.replace(/\r\n/g, '\n'), newAnomaliesTbody.replace(/\r\n/g, '\n'));

// Also add a 6th anomaly at the end of tbody
const oldAnomalyEnd = `								<button class="btn btn-xs btn-outline-secondary py-0 px-2 font-weight-bold" onclick="alert('Conteo selectivo programado en góndola.');">
									<i class="fa-solid fa-barcode mr-1"></i> Recuento
								</button>
							</td>
						</tr>
					</tbody>`;

const newAnomalyEnd = `								<button class="btn btn-xs btn-outline-secondary py-0 px-2 font-weight-bold" onclick="alert('Conteo selectivo programado en góndola.');">
									<i class="fa-solid fa-barcode mr-1"></i> Recuento
								</button>
							</td>
						</tr>
						<tr class="table-warning">
							<td><span class="badge badge-warning text-dark font-weight-bold"><i class="fa-solid fa-box-open mr-1"></i> Devolución Sin Accesorios</span></td>
							<td><strong>DEV-2026-104</strong><br><small class="text-muted">AirPods Pro 2da Gen</small></td>
							<td>Alkosto Envigado · Post-Venta</td>
							<td>Devolución aceptada con estuche vacío sin almohadillas</td>
							<td class="text-right font-weight-bold text-warning">$249.00</td>
							<td><span class="badge badge-warning text-dark">ALTO</span></td>
							<td class="small text-muted">02 nov 2026 16:40</td>
							<td class="text-center">
								<a href="{{ $U('/action-center') }}" class="btn btn-xs btn-outline-warning text-dark py-0 px-2 font-weight-bold">
									<i class="fa-solid fa-user-shield mr-1"></i> Ver Peritaje
								</a>
							</td>
						</tr>
					</tbody>`;

content = content.replace(oldAnomalyEnd.replace(/\r\n/g, '\n'), newAnomalyEnd.replace(/\r\n/g, '\n'));

fs.writeFileSync(filePath, content, 'utf8');
console.log("Successfully enriched all tabs in views/loss_prevention.blade.php!");

