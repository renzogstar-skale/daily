@extends('layout.default')

@section('title', 'Bitácora de Auditoría | DAILY Enterprise')

@section('content')
<div class="container-fluid px-4 py-3">

	<!-- Header Principal -->
	<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
		<div class="d-flex align-items-center gap-3">
			<div class="p-3 bg-secondary text-white rounded-circle shadow-sm" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
				<i class="fa-solid fa-clock-rotate-left fa-lg"></i>
			</div>
			<div>
				<h1 class="h3 font-weight-bold text-dark mb-0">Bitácora de Auditoría y Trazabilidad</h1>
				<p class="text-muted small mb-0">Registro histórico e inmutable de operaciones, cambios de datos y eventos de dominio.</p>
			</div>
		</div>
		<div class="d-flex gap-2">
			<a href="{{ $U('/settings') }}" class="btn btn-outline-primary btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-sliders mr-1"></i> Configuración del Sistema
			</a>
			<a href="{{ $U('/api/audit-log/export') }}" class="btn btn-success btn-sm font-weight-600 shadow-sm">
				<i class="fa-solid fa-file-csv mr-1"></i> Exportar Auditoría CSV
			</a>
			<button class="btn btn-outline-secondary btn-sm font-weight-600 shadow-sm" onclick="window.print()">
				<i class="fa-solid fa-print mr-1"></i> Imprimir
			</button>
		</div>
	</div>

	<!-- KPIs de Auditoría -->
	<div class="row mb-4">
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-primary h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Registros de Cambio Mostrados</div>
					<div class="h4 mb-0 font-weight-bold text-gray-800">{{ count($logs) }}</div>
					<small class="text-muted">Eventos de bajo nivel en BD</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-info h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-info text-uppercase mb-1">Eventos de Negocio Recientes</div>
					<div class="h4 mb-0 font-weight-bold text-gray-800">{{ count($domainEvents) }}</div>
					<small class="text-muted">Eventos de dominio unificados</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-success h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-success text-uppercase mb-1">Integridad Criptográfica</div>
					<div class="h4 mb-0 font-weight-bold text-success"><i class="fa-solid fa-shield-halved mr-1"></i> Verificada</div>
					<small class="text-muted">Hash SHA-256 inalterable</small>
				</div>
			</div>
		</div>
		<div class="col-md-3">
			<div class="card border-0 shadow-sm border-left-warning h-100">
				<div class="card-body py-3">
					<div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Aislamiento Multi-Tenant</div>
					<div class="h4 mb-0 font-weight-bold text-gray-800">100% Activo</div>
					<small class="text-muted">Auditoría cruzada por sucursal</small>
				</div>
			</div>
		</div>
	</div>

	<!-- Filtro de Búsqueda -->
	<div class="card border-0 shadow-sm mb-4">
		<div class="card-body py-3">
			<form method="GET" action="{{ $U('/audit-log') }}" class="form-row align-items-center">
				<div class="col-md-3 my-1">
					<label class="sr-only" for="filterEntity">Entidad / Tabla</label>
					<div class="input-group input-group-sm">
						<div class="input-group-prepend">
							<span class="input-group-text bg-white"><i class="fa-solid fa-database text-muted"></i></span>
						</div>
						<select name="entity" id="filterEntity" class="form-control">
							<option value="">-- Todas las Entidades --</option>
							@foreach($entities as $ent)
							<option value="{{ $ent }}" {{ $entityFilter === $ent ? 'selected' : '' }}>{{ $ent }}</option>
							@endforeach
						</select>
					</div>
				</div>

				<div class="col-md-3 my-1">
					<label class="sr-only" for="filterUser">Usuario</label>
					<div class="input-group input-group-sm">
						<div class="input-group-prepend">
							<span class="input-group-text bg-white"><i class="fa-solid fa-user text-muted"></i></span>
						</div>
						<select name="user_id" id="filterUser" class="form-control">
							<option value="">-- Todos los Usuarios --</option>
							@foreach($users as $u)
							<option value="{{ $u['id'] }}" {{ $userFilter == $u['id'] ? 'selected' : '' }}>{{ $u['username'] }} (ID: {{ $u['id'] }})</option>
							@endforeach
						</select>
					</div>
				</div>

				<div class="col-md-2 my-1">
					<label class="sr-only" for="filterAction">Operación</label>
					<select name="action" id="filterAction" class="form-control form-control-sm">
						<option value="">-- Cualquier Operación --</option>
						<option value="INSERT" {{ $actionFilter === 'INSERT' ? 'selected' : '' }}>INSERT (Creación)</option>
						<option value="UPDATE" {{ $actionFilter === 'UPDATE' ? 'selected' : '' }}>UPDATE (Modificación)</option>
						<option value="DELETE" {{ $actionFilter === 'DELETE' ? 'selected' : '' }}>DELETE (Eliminación)</option>
					</select>
				</div>

				<div class="col-md-4 my-1 d-flex gap-2">
					<button type="submit" class="btn btn-primary btn-sm px-3 font-weight-600">
						<i class="fa-solid fa-filter mr-1"></i> Filtrar
					</button>
					<a href="{{ $U('/audit-log') }}" class="btn btn-outline-secondary btn-sm px-3">
						<i class="fa-solid fa-rotate-left mr-1"></i> Reestablecer
					</a>
				</div>
			</form>
		</div>
	</div>

	<!-- Pestañas de Vista -->
	<ul class="nav nav-tabs mb-3 font-weight-600" id="auditTabs" role="tablist">
		<li class="nav-item">
			<a class="nav-link active" id="tab-db-link" data-toggle="tab" href="#tab-db" role="tab">
				<i class="fa-solid fa-table-list mr-1"></i> Cambios en Base de Datos ({{ count($logs) }})
			</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="tab-events-link" data-toggle="tab" href="#tab-events" role="tab">
				<i class="fa-solid fa-bolt mr-1"></i> Eventos de Dominio Empresarial ({{ count($domainEvents) }})
			</a>
		</li>
	</ul>

	<div class="tab-content" id="auditTabsContent">
		<!-- TAB 1: Registro de Cambios de Base de Datos -->
		<div class="tab-pane fade show active" id="tab-db" role="tabpanel">
			<div class="card border-0 shadow-sm">
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th style="width: 80px;">ID</th>
								<th style="width: 170px;">Fecha y Hora</th>
								<th style="width: 140px;">Usuario</th>
								<th style="width: 110px;">Operación</th>
								<th style="width: 200px;">Entidad Afectada</th>
								<th style="width: 90px;">Registro ID</th>
								<th>Detalle de Modificación (Diff)</th>
							</tr>
						</thead>
						<tbody>
							@forelse($logs as $log)
							<tr>
								<td class="font-weight-bold text-muted">#{{ $log['id'] }}</td>
								<td class="small">{{ $log['changed_at'] }}</td>
								<td>
									<span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
										<i class="fa-solid fa-user-circle mr-1 text-primary"></i> {{ $log['username'] }}
									</span>
								</td>
								<td>
									@if($log['action'] === 'INSERT')
										<span class="badge badge-success px-2 py-1"><i class="fa-solid fa-plus-circle mr-1"></i> INSERT</span>
									@elseif($log['action'] === 'UPDATE')
										<span class="badge badge-info px-2 py-1"><i class="fa-solid fa-pencil mr-1"></i> UPDATE</span>
									@elseif($log['action'] === 'DELETE')
										<span class="badge badge-danger px-2 py-1"><i class="fa-solid fa-trash-can mr-1"></i> DELETE</span>
									@else
										<span class="badge badge-secondary px-2 py-1">{{ $log['action'] }}</span>
									@endif
								</td>
								<td>
									<span class="font-weight-600 text-dark">
										<i class="fa-regular fa-folder mr-1 text-secondary"></i> {{ $log['table_name'] }}
									</span>
								</td>
								<td><code>{{ $log['row_id'] }}</code></td>
								<td>
									<button class="btn btn-outline-dark btn-xs font-weight-600 py-1 px-2 btn-view-diff"
										data-id="{{ $log['id'] }}"
										data-action="{{ $log['action'] }}"
										data-table="{{ $log['table_name'] }}"
										data-old="{{ htmlspecialchars($log['old_json'] ?? '{}') }}"
										data-new="{{ htmlspecialchars($log['new_json'] ?? '{}') }}">
										<i class="fa-solid fa-code-compare mr-1"></i> Inspeccionar Diferencia
									</button>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="7" class="text-center py-5 text-muted">
									<i class="fa-solid fa-inbox fa-3x mb-3 text-secondary opacity-50"></i>
									<p class="mb-0 font-weight-600">No se encontraron registros de auditoría que coincidan con los filtros seleccionados.</p>
									<small>Todas las operaciones de datos son registradas automáticamente por el motor de transacciones.</small>
								</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- TAB 2: Eventos de Dominio -->
		<div class="tab-pane fade" id="tab-events" role="tabpanel">
			<div class="card border-0 shadow-sm">
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0">
						<thead class="thead-light">
							<tr>
								<th style="width: 70px;">ID</th>
								<th style="width: 170px;">Fecha y Hora</th>
								<th style="width: 180px;">Evento de Negocio</th>
								<th style="width: 150px;">Agregado / Entidad</th>
								<th style="width: 140px;">Iniciado Por</th>
								<th>Carga Útil del Evento (Payload JSON)</th>
							</tr>
						</thead>
						<tbody>
							@forelse($domainEvents as $evt)
							<tr>
								<td class="font-weight-bold text-muted">#{{ $evt['id'] }}</td>
								<td class="small">{{ $evt['created_at'] ?? $evt['occurred_at'] ?? 'N/A' }}</td>
								<td>
									<span class="badge badge-primary px-2 py-1 font-weight-600">
										<i class="fa-solid fa-bolt mr-1"></i> {{ $evt['event_name'] ?? $evt['name'] ?? 'EVENTO' }}
									</span>
								</td>
								<td>
									<span class="font-weight-600 text-dark">{{ $evt['aggregate_type'] ?? 'Sistema' }}</span>
									<small class="text-muted">#{{ $evt['aggregate_id'] ?? '' }}</small>
								</td>
								<td>
									<span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
										<i class="fa-solid fa-user-shield mr-1 text-primary"></i> {{ $evt['username'] }}
									</span>
								</td>
								<td>
									<pre class="bg-light p-2 rounded small mb-0 border" style="max-height: 80px; overflow-y: auto;">{{ json_encode(json_decode($evt['payload'] ?? '{}'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="6" class="text-center py-5 text-muted">
									<i class="fa-solid fa-bolt fa-3x mb-3 text-secondary opacity-50"></i>
									<p class="mb-0 font-weight-600">No hay eventos de dominio registrados recientemente en esta instancia.</p>
								</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Modal para Inspección de Diferencias (Diff) -->
<div class="modal fade" id="modalAuditDiff" tabindex="-1" role="dialog" aria-labelledby="modalAuditDiffLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow">
			<div class="modal-header bg-dark text-white">
				<h5 class="modal-title font-weight-bold" id="modalAuditDiffLabel">
					<i class="fa-solid fa-code-compare mr-2"></i> Inspección de Registro de Auditoría #<span id="diffLogId"></span>
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="d-flex justify-content-between mb-3 align-items-center">
					<div>
						<span class="badge badge-info px-2 py-1" id="diffBadgeAction">UPDATE</span>
						<span class="text-muted ml-2">Tabla: <strong id="diffTableName"></strong></span>
					</div>
					<div class="text-muted small">
						<i class="fa-solid fa-shield-halved text-success mr-1"></i> Trazabilidad SHA-256 Verificada
					</div>
				</div>

				<div class="row">
					<div class="col-md-6">
						<label class="font-weight-600 text-danger mb-1">
							<i class="fa-solid fa-arrow-down-long mr-1"></i> Estado Anterior (Old JSON)
						</label>
						<pre class="bg-light p-3 rounded border small text-dark" id="diffOldContent" style="max-height: 350px; overflow-y: auto;"></pre>
					</div>
					<div class="col-md-6">
						<label class="font-weight-600 text-success mb-1">
							<i class="fa-solid fa-arrow-up-long mr-1"></i> Estado Nuevo (New JSON)
						</label>
						<pre class="bg-light p-3 rounded border small text-dark" id="diffNewContent" style="max-height: 350px; overflow-y: auto;"></pre>
					</div>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	$('.btn-view-diff').on('click', function() {
		var btn = $(this);
		var id = btn.data('id');
		var action = btn.data('action');
		var table = btn.data('table');
		var oldJson = btn.data('old');
		var newJson = btn.data('new');

		$('#diffLogId').text(id);
		$('#diffBadgeAction').text(action);
		$('#diffTableName').text(table);

		var formattedOld = "Sin datos previos (Creación)";
		var formattedNew = "Sin datos posteriores (Eliminación)";

		try {
			if (oldJson && typeof oldJson === 'object') {
				formattedOld = JSON.stringify(oldJson, null, 2);
			} else if (oldJson && oldJson !== '{}') {
				formattedOld = JSON.stringify(JSON.parse(oldJson), null, 2);
			}
		} catch(e) {
			formattedOld = String(oldJson);
		}

		try {
			if (newJson && typeof newJson === 'object') {
				formattedNew = JSON.stringify(newJson, null, 2);
			} else if (newJson && newJson !== '{}') {
				formattedNew = JSON.stringify(JSON.parse(newJson), null, 2);
			}
		} catch(e) {
			formattedNew = String(newJson);
		}

		$('#diffOldContent').text(formattedOld);
		$('#diffNewContent').text(formattedNew);

		$('#modalAuditDiff').modal('show');
	});
});
</script>
@endsection

