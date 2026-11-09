{{--
	Barra de filtros compartida (helpers/DailyFilters.php). Muestra solo los filtros que el tablero aplica (DailyFilters::ROUTES[$fbRoute]).
	Parámetros: fbRoute, fbFilters (FromQuery), fbOptions (Options), fbExtra (campos ocultos adicionales, opcional).
--}}
@php
	$fbKeys = \Grocy\Helpers\DailyFilters::ROUTES[$fbRoute] ?? [];
	$fbHas = fn($k) => in_array($k, $fbKeys, true);
	$fbF = $fbFilters;
	$fbO = $fbOptions;
@endphp
<form method="GET" action="{{ $U($fbRoute) }}" class="dfb" data-testid="daily-filter-bar">
	@foreach(($fbExtra ?? []) as $fbName => $fbVal)<input type="hidden" name="{{ $fbName }}" value="{{ $fbVal }}">@endforeach
	<div class="dfb-row">
		@if($fbHas('company_id') && count($fbO['companies']) > 1)
		<div><label for="dfbCompany">Empresa</label>
			<select name="company_id" id="dfbCompany" onchange="this.form.branch_id.value = ''; this.form.submit()">
				<option value="">Todas</option>
				@foreach($fbO['companies'] as $c)<option value="{{ $c['id'] }}" @if($fbF['company_id'] === intval($c['id'])) selected @endif>{{ $c['name'] }}</option>@endforeach
			</select></div>
		@endif
		@if($fbHas('branch_id'))
		<div><label for="dfbBranch">Sucursal</label>
			<select name="branch_id" id="dfbBranch" data-testid="filter-branch" onchange="this.form.submit()">
				<option value="">Todas las sucursales</option>
				@foreach($fbO['branches'] as $b)<option value="{{ $b['id'] }}" @if($fbF['branch_id'] === intval($b['id'])) selected @endif>{{ $b['name'] }}@if(!empty($b['code'])) ({{ $b['code'] }})@endif</option>@endforeach
			</select></div>
		@endif
		@if($fbHas('department'))
		<div><label for="dfbDept">Departamento</label>
			<select name="department" id="dfbDept" data-testid="filter-department" onchange="if (this.form.group_id) this.form.group_id.value = ''; this.form.submit()">
				<option value="">Todos los departamentos</option>
				@foreach($fbO['departments'] as $d)<option value="{{ $d['key'] }}" @if($fbF['department'] === $d['key']) selected @endif>{{ $d['name'] }}</option>@endforeach
			</select></div>
		@endif
		@if($fbHas('group_id'))
		<div><label for="dfbGroup">Categoría</label>
			<select name="group_id" id="dfbGroup" data-testid="filter-category" onchange="this.form.submit()">
				<option value="">Todas las categorías</option>
				@foreach($fbO['groups'] as $g)<option value="{{ $g['id'] }}" @if($fbF['group_id'] === intval($g['id'])) selected @endif>{{ $g['name'] }}</option>@endforeach
			</select></div>
		@endif
		@if($fbHas('subcategory') && count($fbO['subcategories']))
		<div><label for="dfbSub">Subcategoría</label>
			<select name="subcategory" id="dfbSub" onchange="this.form.submit()">
				<option value="">Todas</option>
				@foreach($fbO['subcategories'] as $s)<option value="{{ $s }}" @if($fbF['subcategory'] === $s) selected @endif>{{ $s }}</option>@endforeach
			</select></div>
		@endif
		@if($fbHas('product'))
		<div><label for="dfbProduct">Producto o SKU</label>
			<input type="search" name="product" id="dfbProduct" data-testid="filter-product" value="{{ $fbF['product'] }}" placeholder="SKU, código de barras o nombre"></div>
		@endif
		@if($fbHas('start_date'))
		<div><label for="dfbFrom">Desde</label><input type="date" name="start_date" id="dfbFrom" value="{{ $fbF['start_date'] }}"></div>
		<div><label for="dfbTo">Hasta</label><input type="date" name="end_date" id="dfbTo" value="{{ $fbF['end_date'] }}"></div>
		@endif
		<div class="dfb-actions">
			<button type="submit" class="btn btn-success" data-testid="filter-apply"><i class="fa-solid fa-magnifying-glass mr-1"></i>Aplicar</button>
			<a href="{{ $U($fbRoute) }}" class="daily-filters-reset" data-daily-filters-reset data-testid="filter-reset">Restablecer</a>
		</div>
	</div>
	@php $fbOff = array_diff(['branch_id', 'department', 'group_id', 'product', 'start_date'], $fbKeys); @endphp
	@if($fbOff)
	<div class="dfb-scope">Este tablero no aplica: {{ implode(', ', array_map(fn($k) => $k === 'start_date' ? 'rango de fechas' : mb_strtolower(\Grocy\Helpers\DailyFilters::LABELS[$k]), $fbOff)) }}.</div>
	@endif
</form>
