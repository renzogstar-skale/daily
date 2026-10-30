@extends('layout.default')

@if($mode == 'edit')
@section('title', $__t('Edit location'))
@else
@section('title', $__t('Create location'))
@endif

@section('content')
<div class="row">
	<div class="col">
		<h2 class="title">@yield('title')</h2>
	</div>
</div>

<hr class="my-2">

<div class="row">
	<div class="col-lg-6 col-12">
		<script>
			Grocy.EditMode = '{{ $mode }}';
		</script>

		@if($mode == 'edit')
		<script>
			Grocy.EditObjectId = {{ $location->id }};
		</script>
		@endif

		<form id="location-form"
			novalidate>

			<div class="form-group">
				<label for="name">{{ $__t('Name') }}</label>
				<input type="text"
					class="form-control"
					required
					id="name"
					name="name"
					value="@if($mode == 'edit'){{ $location->name }}@endif">
				<div class="invalid-feedback">{{ $__t('A name is required') }}</div>
			</div>

			<div class="form-group">
				<div class="custom-control custom-checkbox">
					<input @if($mode=='create'
						)
						checked
						@elseif($mode=='edit'
						&&
						$location->active == 1) checked @endif class="form-check-input custom-control-input" type="checkbox" id="active" name="active" value="1">
					<label class="form-check-label custom-control-label"
						for="active">{{ $__t('Active') }}</label>
				</div>
			</div>

			<div class="form-group">
				<label for="description">{{ $__t('Description') }}</label>
				<textarea class="form-control"
					rows="2"
					id="description"
					name="description">@if($mode == 'edit'){{ $location->description }}@endif</textarea>
			</div>

			@if($mode == 'create' || !$location->is_transit)
			<div class="form-group">
				<label for="branch_id">Sucursal</label>
				<select class="custom-select" id="branch_id" name="branch_id">
					@foreach($branches as $branch)
					<option value="{{ $branch->id }}" @if(($mode == 'edit' ? $location->branch_id : 1) == $branch->id) selected @endif>{{ $branch->name }}</option>
					@endforeach
				</select>
				<small class="form-text text-muted">El stock de esta ubicación cuenta para la sucursal elegida (MRP, ventas, traslados).</small>
			</div>

			{{-- DAIRY Etapa 21: jerarquía sucursal › almacén › zona › pasillo › estante › posición, etiqueta y reglas --}}
			<div class="form-row">
				@foreach(['warehouse' => 'Almacén', 'zone' => 'Zona', 'aisle' => 'Pasillo', 'shelf' => 'Estante', 'bin' => 'Posición'] as $field => $label)
				<div class="form-group col-6 col-md-4">
					<label for="{{ $field }}">{{ $label }}</label>
					<input type="text" class="form-control" id="{{ $field }}" name="{{ $field }}" maxlength="40" value="@if($mode == 'edit'){{ $location->{$field} }}@endif">
				</div>
				@endforeach
				<div class="form-group col-6 col-md-4">
					<label for="label_code">Etiqueta (escaneable)</label>
					<input type="text" class="form-control text-uppercase" id="label_code" name="label_code" pattern="LOC-[A-Za-z0-9\-]{1,30}" title="Formato LOC-… (letras, números y guiones)" placeholder="se asigna sola" value="@if($mode == 'edit'){{ $location->label_code }}@endif">
				</div>
				<div class="form-group col-6 col-md-4">
					<label for="storage_class">Clase de almacenamiento</label>
					<select class="custom-select" id="storage_class" name="storage_class">
						@foreach(['' => '—', 'ambient' => 'Ambiente', 'chilled' => 'Refrigerado', 'frozen' => 'Congelado'] as $v => $l)
						<option value="{{ $v }}" @if($mode == 'edit' && $location->storage_class == $v) selected @endif>{{ $l }}</option>
						@endforeach
					</select>
				</div>
				<div class="form-group col-6 col-md-4">
					<label for="capacity_units">Capacidad (unidades)</label>
					<input type="number" min="0" step="any" class="form-control" id="capacity_units" name="capacity_units" value="@if($mode == 'edit'){{ $location->capacity_units }}@endif">
				</div>
			</div>
			@endif

			@if(GROCY_FEATURE_FLAG_STOCK_PRODUCT_FREEZING)
			<div class="form-group">
				<div class="custom-control custom-checkbox">
					<input @if($mode=='edit'
						&&
						$location->is_freezer == 1) checked @endif class="form-check-input custom-control-input" type="checkbox" id="is_freezer" name="is_freezer" value="1">
					<label class="form-check-label custom-control-label"
						for="is_freezer">{{ $__t('Is freezer') }}
						&nbsp;<i class="fa-solid fa-question-circle text-muted"
							data-toggle="tooltip"
							data-trigger="hover click"
							title="{{ $__t('When moving products from/to a freezer location, the products due date is automatically adjusted according to the product settings') }}"></i>
					</label>
				</div>
			</div>
			@else
			<input type="hidden"
				name="is_freezer"
				value="0">
			@endif

			@include('components.userfieldsform', array(
			'userfields' => $userfields,
			'entity' => 'locations'
			))

			<button id="save-location-button"
				class="btn btn-success">{{ $__t('Save') }}</button>

		</form>

		@include('components.dairy_change_history', ['table' => 'locations', 'rowId' => $mode == 'edit' ? $location->id : null])
	</div>
</div>
@stop
