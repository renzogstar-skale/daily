@extends('layout.default')

@section('title', 'Importar y exportar maestros')

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-primary"><i class="fa-solid fa-file-import"></i></div>
		<div>
			<h4 class="dairy-page-title">Importar y exportar maestros</h4>
			<p class="dairy-page-subtitle">Cargue productos, proveedores, clientes y ubicaciones desde Excel (guardado como CSV). Primero se valida fila por fila; nada se guarda hasta confirmar y, si una fila falla, no se guarda ninguna.</p>
		</div>
	</div>
</div>

@include('components.dairy_process_steps', ['steps' => [['Plantilla', 'descargue CSV o Excel', 'current'], ['Completar', 'en Excel u otra hoja', 'pending'], ['Validar', 'errores y duplicados', 'pending'], ['Confirmar', 'una sola transacción', 'pending']]])

<div class="row">
	<div class="col-lg-7 mb-3">
		<div class="card">
			<div class="card-body">
				<form id="di-form" autocomplete="off">
					<div class="form-row">
						<div class="col-md-6 form-group">
							<label for="di-entity">Tipo de registro</label>
							<select class="custom-select" id="di-entity" name="entity">
								@foreach($entities as $key => $e)<option value="{{ $key }}">{{ $e['label'] }}</option>@endforeach
							</select>
						</div>
						<div class="col-md-6 form-group">
							<label for="di-mode">Si el registro ya existe</label>
							<select class="custom-select" id="di-mode" name="mode">
								<option value="create">Rechazar la fila (solo altas)</option>
								<option value="upsert">Actualizar con los datos del archivo</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label for="di-file">Archivo CSV (UTF-8 o Excel «CSV delimitado»)</label>
						<input type="file" class="form-control-file" id="di-file" accept=".csv,.txt,text/csv" required>
						<small class="form-text text-muted">Columnas: <span id="di-columns"></span>. <b>*</b> obligatoria. Separador coma, punto y coma o tabulador.</small>
					</div>
					<div class="d-flex flex-wrap dairy-gap-2">
						<button type="submit" class="btn btn-primary" id="di-validate"><i class="fa-solid fa-magnifying-glass mr-1"></i>Validar</button>
						<button type="button" class="btn btn-success d-none" id="di-commit"><i class="fa-solid fa-check mr-1"></i>Confirmar importación</button>
						<a class="btn btn-outline-secondary" id="di-template-csv" href="#"><i class="fa-solid fa-file-csv mr-1"></i>Plantilla CSV</a>
						<a class="btn btn-outline-secondary" id="di-template-xls" href="#"><i class="fa-solid fa-file-excel mr-1"></i>Plantilla Excel</a>
						<a class="btn btn-outline-secondary" id="di-export-xls" href="#"><i class="fa-solid fa-download mr-1"></i>Exportar datos actuales</a>
					</div>
				</form>
			</div>
		</div>
		<div id="di-result" class="mt-3"></div>
	</div>
	<div class="col-lg-5 mb-3">
		<div class="card">
			<div class="card-header font-weight-700"><i class="fa-solid fa-clock-rotate-left mr-2 text-secondary"></i>Importaciones realizadas</div>
			<div class="card-body p-0">
				@if(count($jobs) === 0)
				<p class="small text-muted p-3 mb-0">Sin importaciones.</p>
				@else
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead><tr><th>Fecha</th><th>Tipo</th><th class="text-right">Altas</th><th class="text-right">Cambios</th><th>Usuario</th></tr></thead>
						<tbody>
							@foreach($jobs as $j)
							<tr><td>{{ substr($j['created_at'], 0, 16) }}<div class="small text-muted">{{ $j['file_name'] }}</div></td><td>{{ $entities[$j['entity']]['label'] ?? $j['entity'] }}</td><td class="text-right">{{ $j['rows_created'] }}</td><td class="text-right">{{ $j['rows_updated'] }}</td><td>{{ $j['username'] }}</td></tr>
							@endforeach
						</tbody>
					</table>
				</div>
				@endif
			</div>
		</div>
		<div class="card mt-3"><div class="card-body small text-muted">
			Cada alta o cambio (también los hechos en pantalla) queda en el historial del registro con usuario, fecha y valores anterior/nuevo. Excel abre las plantillas .xls directamente; para importar, guárdelas como CSV.
		</div></div>
	</div>
</div>

<script>window.DairyImportEntities = {!! json_encode($entities, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};</script>
@stop
