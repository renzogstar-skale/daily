{{-- Filtros comunes del Centro de Control (GET; conserva la pestaña). $f, $lookups, $startDate, $endDate; opcional $extra (HTML), $noClass --}}
<div class="dlx-filter-wrap">
	<div class="d-flex justify-content-between align-items-center flex-wrap dairy-gap-2">
		<button type="button" class="btn btn-light border dlx-filter-btn" onclick="DairyLoss.toggleSheet(this)" aria-label="Abrir filtros"><i class="fa-solid fa-filter mr-1"></i>Filtros</button>
	</div>
	<form class="dlx-filters" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros">
		<div class="form-group"><label for="lf-from-{{ $uid = uniqid() }}">Desde</label><input id="lf-from-{{ $uid }}" type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}"></div>
		<div class="form-group"><label for="lf-to-{{ $uid }}">Hasta</label><input id="lf-to-{{ $uid }}" type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}"></div>
		<div class="form-group"><label for="lf-b-{{ $uid }}">Sucursal</label>
			<select id="lf-b-{{ $uid }}" name="branch_id" class="form-control form-control-sm"><option value="">Todas</option>
				@foreach($lookups['branches'] as $b)<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>@endforeach
			</select></div>
		@if(count($lookups['formats']))
		<div class="form-group"><label for="lf-fm-{{ $uid }}">Formato</label>
			<select id="lf-fm-{{ $uid }}" name="format" class="form-control form-control-sm"><option value="">Todos</option>
				@foreach($lookups['formats'] as $fm)<option @if(($f['format'] ?? null) === $fm) selected @endif>{{ $fm }}</option>@endforeach
			</select></div>
		@endif
		<div class="form-group"><label for="lf-g-{{ $uid }}">Departamento</label>
			<select id="lf-g-{{ $uid }}" name="group_id" class="form-control form-control-sm"><option value="">Todos</option>
				@foreach($lookups['groups'] as $g)<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>@endforeach
			</select></div>
		@if(empty($noClass))
		<div class="form-group"><label for="lf-c-{{ $uid }}">Clase</label>
			<select id="lf-c-{{ $uid }}" name="loss_class" class="form-control form-control-sm"><option value="">Todas</option>
				@foreach(\Grocy\Services\LossIntelligenceService::CLASSES as $k => $lbl)<option value="{{ $k }}" @if(($f['loss_class'] ?? null) === $k) selected @endif>{{ $lbl }}</option>@endforeach
			</select></div>
		@endif
		<div class="form-group"><label for="lf-s-{{ $uid }}">Proveedor</label>
			<select id="lf-s-{{ $uid }}" name="supplier_id" class="form-control form-control-sm"><option value="">Todos</option>
				@foreach($lookups['suppliers'] as $s)<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>@endforeach
			</select></div>
		<div class="form-group"><label for="lf-p-{{ $uid }}">SKU</label>
			<select id="lf-p-{{ $uid }}" name="product_id" class="form-control form-control-sm"><option value="">Todos</option>
				@foreach($lookups['products'] as $p)<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}</option>@endforeach
			</select></div>
		@if(!empty($f['cause']))
		<div class="form-group"><label>Causal</label><div><span class="dlx-chip is-amber">{{ $f['cause'] }}</span> <a href="#" class="small" onclick="$(this).closest('form').find('[name=cause]').val(''); return DairyLoss.applyFilters($(this).closest('form'));">quitar</a></div>
			<input type="hidden" name="cause" value="{{ $f['cause'] }}"></div>
		@endif
		{!! $extra ?? '' !!}
		<div class="form-group" style="flex: 0 0 auto; min-width: 0;">
			<button type="submit" class="btn btn-sm btn-dark"><i class="fa-solid fa-check mr-1"></i>Aplicar</button>
			<button type="button" class="btn btn-sm btn-light border d-md-none" onclick="DairyLoss.toggleSheet(this)">Cerrar</button>
		</div>
	</form>
</div>
