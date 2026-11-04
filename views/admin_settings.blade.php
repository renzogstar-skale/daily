@extends('layout.default')

@section('title', 'Configuración del Sistema | DAILY Enterprise')

@section('content')
<div class="container-fluid px-4 py-3">

	<!-- Header Principal -->
	<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
		<div class="d-flex align-items-center gap-3">
			<div class="p-3 bg-primary text-white rounded-circle shadow-sm" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
				<i class="fa-solid fa-sliders fa-lg"></i>
			</div>
			<div>
				<h1 class="h3 font-weight-bold text-dark mb-0">Administración y Configuración del Sistema</h1>
				<p class="text-muted small mb-0">Parámetros corporativos, gobernanza multiempresa, matriz de aprobaciones e integraciones.</p>
			</div>
		</div>
		<div class="d-flex gap-2">
			<a href="{{ $U('/audit-log') }}" class="btn btn-outline-secondary btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-clock-rotate-left mr-1"></i> Bitácora de Auditoría
			</a>
			<a href="{{ $U('/users') }}" class="btn btn-outline-primary btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-users-gear mr-1"></i> Gestión de Usuarios
			</a>
			<button type="submit" form="formSystemSettings" class="btn btn-primary btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-floppy-disk mr-1"></i> Guardar Cambios
			</button>
		</div>
	</div>

	<!-- Pestañas de Navegación de Configuración -->
	<ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded border" id="settingsTab" role="tablist">
		<li class="nav-item">
			<a class="nav-link active font-weight-600" id="tab-general-link" data-toggle="pill" href="#tab-general" role="tab">
				<i class="fa-solid fa-building mr-1"></i> 1. Empresa y Sucursales
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-roles-link" data-toggle="pill" href="#tab-roles" role="tab">
				<i class="fa-solid fa-user-shield mr-1"></i> 2. Usuarios y Roles (IAM)
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-approvals-link" data-toggle="pill" href="#tab-approvals" role="tab">
				<i class="fa-solid fa-clipboard-check mr-1"></i> 3. Flujos de Aprobación
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-integrations-link" data-toggle="pill" href="#tab-integrations" role="tab">
				<i class="fa-solid fa-network-wired mr-1"></i> 4. Integraciones (POS / ERP)
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link font-weight-600" id="tab-security-link" data-toggle="pill" href="#tab-security" role="tab">
				<i class="fa-solid fa-shield-halved mr-1"></i> 5. Seguridad y Multi-Tenant
			</a>
		</li>
	</ul>

	<form id="formSystemSettings" method="post" action="{{ $U('/api/admin/settings') }}" onsubmit="saveSettings(event)">
		<div class="tab-content" id="settingsTabContent">

			<!-- 1. EMPRESA Y SUCURSALES -->
			<div class="tab-pane fade show active" id="tab-general" role="tabpanel">
				<div class="row">
					<div class="col-lg-8">
						<div class="card border shadow-sm mb-4">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-building text-primary mr-2"></i>Parámetros Institucionales de la Organización
							</div>
							<div class="card-body">
								<div class="row g-3">
									<div class="col-md-6 mb-3">
										<label class="form-label font-weight-600 small text-dark">Razón Social o Nombre Legal</label>
										<input type="text" class="form-control" name="setting_company_name" value="{{ $settings['company_name'] ?? 'DAILY Retail Enterprise S.A.S.' }}" required>
									</div>
									<div class="col-md-6 mb-3">
										<label class="form-label font-weight-600 small text-dark">Identificación Tributaria (NIT / RFC / CIF)</label>
										<input type="text" class="form-control" name="setting_company_tax_id" value="{{ $settings['company_tax_id'] ?? 'NIT 900.842.119-4' }}" required>
									</div>
									<div class="col-md-4 mb-3">
										<label class="form-label font-weight-600 small text-dark">Moneda Principal</label>
										<select class="form-control" name="setting_default_currency">
											<option value="USD" @if(($settings['default_currency'] ?? '') === 'USD') selected @endif>USD ($ - Dólar Estadounidense)</option>
											<option value="COP" @if(($settings['default_currency'] ?? '') === 'COP') selected @endif>COP ($ - Peso Colombiano)</option>
											<option value="EUR" @if(($settings['default_currency'] ?? '') === 'EUR') selected @endif>EUR (€ - Euro)</option>
										</select>
									</div>
									<div class="col-md-4 mb-3">
										<label class="form-label font-weight-600 small text-dark">Símbolo Monetario</label>
										<input type="text" class="form-control" name="setting_currency_symbol" value="{{ $settings['currency_symbol'] ?? '$' }}">
									</div>
									<div class="col-md-4 mb-3">
										<label class="form-label font-weight-600 small text-dark">Zona Horaria Operativa</label>
										<select class="form-control" name="setting_timezone">
											<option value="America/Bogota" @if(($settings['timezone'] ?? '') === 'America/Bogota') selected @endif>America/Bogota (UTC-5)</option>
											<option value="America/New_York" @if(($settings['timezone'] ?? '') === 'America/New_York') selected @endif>America/New_York (UTC-5/UTC-4)</option>
											<option value="America/Mexico_City" @if(($settings['timezone'] ?? '') === 'America/Mexico_City') selected @endif>America/Mexico_City (UTC-6)</option>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-4">
						<div class="card border shadow-sm mb-4">
							<div class="card-header bg-white font-weight-bold py-3 text-dark d-flex justify-content-between align-items-center">
								<span><i class="fa-solid fa-store text-success mr-2"></i>Sucursales Activas ({{ count($branches) }})</span>
								<a href="{{ $U('/branches') }}" class="btn btn-xs btn-outline-primary small">Ver Red</a>
							</div>
							<div class="card-body p-0">
								<div class="list-group list-group-flush small" style="max-height: 280px; overflow-y: auto;">
									@foreach($branches as $b)
										<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
											<div>
												<strong class="text-dark">{{ $b->name }}</strong>
												<div class="text-muted text-xs">ID: #{{ $b->id }}</div>
											</div>
											<span class="badge badge-success px-2 py-1">Operativa</span>
										</div>
									@endforeach
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- 2. USUARIOS Y ROLES (IAM) -->
			<div class="tab-pane fade" id="tab-roles" role="tabpanel">
				<div class="row">
					<div class="col-lg-7">
						<div class="card border shadow-sm mb-4">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-users text-primary mr-2"></i>Estructura Jerárquica de Roles y Responsabilidades
							</div>
							<div class="card-body p-0">
								<div class="table-responsive">
									<table class="table table-hover align-middle mb-0 small">
										<thead class="bg-light">
											<tr>
												<th>Rol Estándar</th>
												<th>Descripción y Responsabilidad</th>
												<th>Permisos Clave del Backend</th>
											</tr>
										</thead>
										<tbody>
											@foreach($rolesMatrix as $roleName => $r)
												<tr>
													<td>
														<span class="badge {{ $r['badge'] }} px-2 py-1 font-weight-bold">{{ $roleName }}</span>
													</td>
													<td>{{ $r['desc'] }}</td>
													<td>
														@foreach($r['permissions'] as $p)
															<span class="badge badge-light border text-monospace text-xs mb-1">{{ $p }}</span>
														@endforeach
													</td>
												</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-5">
						<div class="card border shadow-sm mb-4">
							<div class="card-header bg-white font-weight-bold py-3 text-dark d-flex justify-content-between align-items-center">
								<span><i class="fa-solid fa-user-check text-info mr-2"></i>Usuarios del Sistema ({{ count($users) }})</span>
								<a href="{{ $U('/users') }}" class="btn btn-xs btn-outline-primary small">Administrar</a>
							</div>
							<div class="card-body p-0">
								<div class="list-group list-group-flush small" style="max-height: 320px; overflow-y: auto;">
									@foreach($users as $u)
										<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
											<div>
												<strong class="text-dark">{{ $u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] : $u['username'] }}</strong>
												<div class="text-muted text-xs">Usuario interno: {{ '@' . $u['username'] }}</div>
											</div>
											<span class="badge badge-info px-2 py-1">{{ $u['permission_count'] }} permisos</span>
										</div>
									@endforeach
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- 3. FLUJOS DE APROBACIÓN -->
			<div class="tab-pane fade" id="tab-approvals" role="tabpanel">
				<div class="card border shadow-sm mb-4">
					<div class="card-header bg-white font-weight-bold py-3 text-dark">
						<i class="fa-solid fa-clipboard-check text-success mr-2"></i>Matriz de Aprobaciones y Límites de Autorización
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0 small">
								<thead class="bg-light">
									<tr>
										<th>Operación / Flujo Crítico</th>
										<th>Rol con Autoridad</th>
										<th>Permiso en Servidor</th>
										<th>Regla de Control y Política Empresarial</th>
									</tr>
								</thead>
								<tbody>
									@foreach($approvalFlows as $af)
										<tr>
											<td><strong class="text-dark">{{ $af['flow'] }}</strong></td>
											<td><span class="badge badge-light border text-dark font-weight-600">{{ $af['role'] }}</span></td>
											<td><code class="text-primary">{{ $af['perm'] }}</code></td>
											<td><span class="text-muted">{{ $af['rule'] }}</span></td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-4 mb-3">
						<div class="card border p-3 bg-white">
							<label class="font-weight-600 small text-dark mb-1">Umbral Aprobación Compras ($)</label>
							<input type="number" step="100" class="form-control" name="setting_approval_po_threshold" value="{{ $settings['approval_po_threshold'] ?? '5000' }}">
							<small class="text-muted mt-1 d-block">Montos superiores exigen doble visto bueno.</small>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="card border p-3 bg-white">
							<label class="font-weight-600 small text-dark mb-1">Umbral Aprobación Mermas ($)</label>
							<input type="number" step="10" class="form-control" name="setting_approval_waste_threshold" value="{{ $settings['approval_waste_threshold'] ?? '100' }}">
							<small class="text-muted mt-1 d-block">Pérdidas superiores exigen revisión de Prevención.</small>
						</div>
					</div>
					<div class="col-md-4 mb-3">
						<div class="card border p-3 bg-white">
							<label class="font-weight-600 small text-dark mb-1">Tolerancia Diferencias de Conteo (%)</label>
							<input type="number" step="0.001" class="form-control" name="setting_approval_inventory_diff_tolerance" value="{{ $settings['approval_inventory_diff_tolerance'] ?? '0.01' }}">
							<small class="text-muted mt-1 d-block">Desvíos mayores abren expediente CAS- obligatorio.</small>
						</div>
					</div>
				</div>
			</div>

			<!-- 4. INTEGRACIONES -->
			<div class="tab-pane fade" id="tab-integrations" role="tabpanel">
				<div class="row">
					<div class="col-lg-6 mb-4">
						<div class="card border shadow-sm h-100">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-cash-register text-primary mr-2"></i>Conector Punto de Venta (POS & Cajas)
							</div>
							<div class="card-body">
								<div class="custom-control custom-switch mb-3">
									<input type="checkbox" class="custom-control-input" id="switchPosActive" name="setting_pos_integration_active" value="1" @if(($settings['pos_integration_active'] ?? '1') === '1') checked @endif>
									<label class="custom-control-label font-weight-bold text-dark" for="switchPosActive">Integración POS Activa en Tiempo Real</label>
								</div>
								<p class="text-muted small">Cada ticket confirmado en caja valida stock libre, reserva y aplica salida FEFO inmutable con emisión de Kardex.</p>
								<div class="alert alert-info py-2 px-3 small mb-0">
									<i class="fa-solid fa-circle-check mr-1"></i> Idempotencia activa: prevención de tickets duplicados vía hash transaccional.
								</div>
							</div>
						</div>
					</div>

					<div class="col-lg-6 mb-4">
						<div class="card border shadow-sm h-100">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-network-wired text-success mr-2"></i>Conector ERP & Finanzas
							</div>
							<div class="card-body">
								<label class="font-weight-600 small text-dark mb-1">Intervalo de Sincronización ERP (minutos)</label>
								<input type="number" class="form-control mb-3" name="setting_erp_sync_interval_mins" value="{{ $settings['erp_sync_interval_mins'] ?? '15' }}">
								<p class="text-muted small">Sincroniza compras recibidas, costo de mercancía vendida (CMV), mermas y depreciaciones hacia el libro mayor contable.</p>
								<div class="d-flex gap-2">
									<a href="{{ $U('/manageapikeys') }}" class="btn btn-outline-secondary btn-sm font-weight-600">
										<i class="fa-solid fa-key mr-1"></i> Administrar API Keys
									</a>
									<a href="{{ $U('/data-import') }}" class="btn btn-outline-primary btn-sm font-weight-600">
										<i class="fa-solid fa-file-excel mr-1"></i> Carga Masiva Maestros
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- 5. SEGURIDAD Y MULTI-TENANT -->
			<div class="tab-pane fade" id="tab-security" role="tabpanel">
				<div class="row">
					<div class="col-lg-6 mb-4">
						<div class="card border shadow-sm">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-shield-halved text-danger mr-2"></i>Políticas de Seguridad y Control de Sesión
							</div>
							<div class="card-body">
								<div class="mb-3">
									<label class="font-weight-600 small text-dark mb-1">Duración Máxima de Sesión Inactiva (Horas)</label>
									<input type="number" class="form-control" name="setting_auth_session_lifetime_hours" value="{{ $settings['auth_session_lifetime_hours'] ?? '12' }}">
								</div>
								<div class="custom-control custom-switch mb-3">
									<input type="checkbox" class="custom-control-input" id="switchMultiTenant" name="setting_multi_tenant_isolation" value="1" @if(($settings['multi_tenant_isolation'] ?? '1') === '1') checked @endif>
									<label class="custom-control-label font-weight-bold text-dark" for="switchMultiTenant">Aislamiento Estricto Multi-Tenant por Empresa</label>
								</div>
								<p class="text-muted small">Impide visualización cruzada de catálogos, costos, existencias y personal entre razones sociales independientes.</p>
							</div>
						</div>
					</div>

					<div class="col-lg-6 mb-4">
						<div class="card border shadow-sm">
							<div class="card-header bg-white font-weight-bold py-3 text-dark">
								<i class="fa-solid fa-fingerprint text-teal mr-2"></i>Criptografía y Auditoría Inmutable
							</div>
							<div class="card-body">
								<div class="mb-3">
									<label class="font-weight-600 small text-dark mb-1">Algoritmo de Sellado de Informes Ejecutivos</label>
									<input type="text" class="form-control bg-light" name="setting_bi_export_encryption" value="SHA-256 + HMAC Inalterable" readonly>
								</div>
								<p class="text-muted small">Cada informe de cierre (DCR), acta de merma y traspaso de custodia celular genera una huella criptográfica verificable protegida por triggers de base de datos.</p>
								<a href="{{ $U('/audit-log') }}" class="btn btn-outline-primary btn-sm font-weight-600">
									<i class="fa-solid fa-clock-rotate-left mr-1"></i> Abrir Bitácora de Auditoría
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>
	</form>

</div>

<script>
function saveSettings(e) {
	e.preventDefault();
	var formData = new FormData(document.getElementById('formSystemSettings'));
	var data = {};
	formData.forEach(function(value, key){
		data[key] = value;
	});

	fetch('{{ $U('/api/admin/settings') }}', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(data)
	})
	.then(function(res){ return res.json(); })
	.then(function(json){
		if (json.success) {
			toastr.success(json.message || 'Configuración guardada correctamente');
		} else {
			toastr.error(json.error_message || 'Error al guardar la configuración');
		}
	})
	.catch(function(err){
		toastr.error('Error de comunicación con el servidor');
	});
}
</script>
@endsection
