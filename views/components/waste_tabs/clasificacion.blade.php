@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
	$sections = ['clasificacion' => ['operativa', 'comercial'], 'ajustes' => ['ajuste'], 'averias' => ['averia']][$section];
	$titles = ['clasificacion' => 'Merma operativa y comercial', 'ajustes' => 'Ajustes de inventario', 'averias' => 'Averías y roturas'];
	$purpose = [
		'clasificacion' => 'Pérdidas físicas por proceso (operativa) y por decisión comercial (comercial), en $ y % de ventas netas, hasta el SKU.',
		'ajustes' => 'Diferencias de inventario (conteos y correcciones). Son movimientos administrativos: se separan de la merma física y no se suman dos veces.',
		'averias' => 'Producto dañado o roto. Incluye averías registradas sin merma; las que ya generaron merma cuentan una sola vez.',
	];
	$names = $cls['names'] ?? [];
	$causeCatalog = $cls['cause_names'] ?? [];
	$branchQ = $f['branch_id'] ? '&branch_id=' . $f['branch_id'] : '';

	// Helper para fotografía de productos con reemplazo visual seguro
	$productPhoto = function($item) use ($U) {
		if (!empty($item['picture_file_name'])) {
			return $U('/api/files/productpictures/' . base64_encode($item['picture_file_name']) . '?force_serve_as=picture&best_fit_width=400');
		}
		$sku = strtoupper($item['sku'] ?? '');
		$n = mb_strtolower($item['name'] ?? '');

		if ($sku === 'DAIRY-MLK-001' || str_contains($n, 'leche entera')) {
			return $U('/img/control_center/leche_entera_galon.jpg');
		}
		if ($sku === 'DAIRY-CHE-003' || str_contains($n, 'mozzarella')) {
			return $U('/img/control_center/queso_mozzarella.jpg');
		}
		if ($sku === 'DAIRY-CHS-001' || str_contains($n, 'cheddar')) {
			return $U('/img/control_center/queso_cheddar.jpg');
		}
		if ($sku === 'DAIRY-EGG-001' || str_contains($n, 'huevo')) {
			return $U('/img/control_center/huevos_grandes.jpg');
		}
		if ($sku === 'DAIRY-BEV-002' || str_contains($n, 'proteica')) {
			return $U('/img/control_center/bebida_proteica.jpg');
		}

		if (preg_match('/\b(pollo|pechuga|ave|gallina)\b/u', $n)) {
			return $U('/img/control_center/pechuga_pollo.jpg');
		}
		if (preg_match('/\b(carne|res|vacuno|bovino|molida)\b/u', $n)) {
			return $U('/img/control_center/carne_picada.jpg');
		}
		if (preg_match('/\b(tomate|jitomate)\b/u', $n)) {
			return $U('/img/control_center/tomate.jpg');
		}
		if (preg_match('/\b(yogur|yoghurt|probiot)\b/u', $n)) {
			return $U('/img/control_center/yogur_griego.jpg');
		}
		if (preg_match('/\b(leche|crema|lacteo)\b/u', $n)) {
			return $U('/img/control_center/leche_descremada.jpg');
		}
		return $U('/img/control_center/operativa_banner.jpg');
	};

	$opData = $cls['classes']['operativa'] ?? ['cost' => 0, 'pct_sales' => 0, 'share' => 0, 'events' => 0, 'skus' => [], 'causes' => [], 'recent' => []];
	$comData = $cls['classes']['comercial'] ?? ['cost' => 0, 'pct_sales' => 0, 'share' => 0, 'events' => 0, 'skus' => [], 'causes' => [], 'recent' => []];
	$totalEvents = array_sum(array_map(fn($c) => $cls['classes'][$c]['events'] ?? 0, $sections));
@endphp

@if($section === 'clasificacion')
<style>
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyOcTabContainer) .dairy-related { display: none !important; }
.daily-oc-section { display: flex; flex-direction: column; gap: 18px; }
.daily-oc-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.02); position: relative; transition: box-shadow 0.2s ease, border-color 0.2s ease; }
.daily-oc-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.daily-oc-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.daily-oc-card-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; letter-spacing: -0.01em; }
.daily-oc-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 4px; }
.daily-oc-title-block h5 { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; letter-spacing: -0.02em; }
.daily-oc-title-block p { font-size: 0.82rem; color: #64748b; margin: 0; line-height: 1.4; }
.daily-oc-netsales-badge { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 16px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
.daily-oc-netsales-icon { width: 36px; height: 36px; border-radius: 9px; background: #dcfce7; color: var(--daily-primary, #00C83B); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.daily-oc-netsales-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; line-height: 1.1; }
.daily-oc-netsales-value { font-size: 1.2rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1.15; }
.daily-oc-netsales-sub { font-size: 0.68rem; color: #94a3b8; }
.daily-oc-filters { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.daily-oc-filters-form { display: flex; align-items: flex-end; flex-wrap: wrap; gap: 10px; }
.daily-oc-filter-group { flex: 1 1 150px; min-width: 130px; }
.daily-oc-filter-label { display: block; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; margin-bottom: 4px; }
.daily-oc-hero-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; min-height: 180px; height: 180px; position: relative; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); display: flex; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; }
.daily-oc-hero-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); border-color: #cbd5e1; }
.daily-oc-hero-content { flex: 1; z-index: 2; position: relative; padding: 20px 22px; max-width: 66%; display: flex; flex-direction: column; justify-content: space-between; }
.daily-oc-hero-img-wrap { position: absolute; top: 0; right: 0; bottom: 0; width: 44%; max-width: 44%; z-index: 1; pointer-events: none; overflow: hidden; }
.daily-oc-hero-img { width: 100% !important; height: 100% !important; object-fit: cover !important; object-position: center right !important; }
.daily-oc-hero-mask { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0.95) 15%, rgba(255, 255, 255, 0.4) 60%, rgba(255, 255, 255, 0) 100%); }
.daily-oc-pill-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; width: fit-content; }
.daily-oc-pill-tag.is-green { background: #dcfce7; color: #15803d; }
.daily-oc-pill-tag.is-amber { background: #fef3c7; color: #b45309; }
.daily-oc-hero-amount { font-size: 2.1rem; font-weight: 800; color: #0f172a; letter-spacing: -0.03em; line-height: 1.1; margin: 8px 0 3px 0; }
.daily-oc-hero-sub { font-size: 0.8rem; color: #64748b; font-weight: 500; }
.daily-oc-hero-counters { display: flex; align-items: center; gap: 24px; margin-top: 14px; }
.daily-oc-counter-item { display: flex; flex-direction: column; }
.daily-oc-counter-num { font-size: 1.3rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
.daily-oc-counter-lbl { font-size: 0.72rem; color: #64748b; font-weight: 500; }
.daily-oc-cause-list { display: flex; flex-direction: column; gap: 6px; }
.daily-oc-cause-row { display: grid; grid-template-columns: 180px 1fr 85px 60px; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; transition: background 0.15s ease; cursor: pointer; text-decoration: none !important; color: inherit !important; }
.daily-oc-cause-row:hover { background: #f8fafc; }
.daily-oc-cause-name-wrap { display: flex; align-items: center; gap: 6px; overflow: hidden; }
.daily-oc-cause-code { font-size: 0.7rem; font-weight: 700; font-family: monospace; padding: 2px 6px; border-radius: 5px; background: #f1f5f9; color: #475569; flex-shrink: 0; }
.daily-oc-cause-name { font-size: 0.8rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.daily-oc-bar-track { height: 10px; background: #f1f5f9; border-radius: 6px; overflow: hidden; position: relative; width: 100%; }
.daily-oc-bar-fill { height: 100%; border-radius: 6px; transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.daily-oc-bar-fill.is-green { background: linear-gradient(90deg, #10b981 0%, var(--daily-primary, #00C83B) 100%); }
.daily-oc-bar-fill.is-amber { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
.daily-oc-cause-money { font-size: 0.82rem; font-weight: 700; color: #0f172a; text-align: right; font-variant-numeric: tabular-nums; }
.daily-oc-cause-pct { font-size: 0.78rem; color: #64748b; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
.daily-oc-sku-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
@media (max-width: 1199px) {
	.daily-oc-sku-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}
.daily-oc-sku-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; cursor: pointer; display: flex; flex-direction: column; text-decoration: none !important; color: inherit !important; position: relative; }
.daily-oc-sku-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.06); border-color: #cbd5e1; }
.daily-oc-sku-img-wrap { height: 110px; max-height: 110px; width: 100%; position: relative; background: #f8fafc; overflow: hidden; }
.daily-oc-sku-img { width: 100% !important; height: 110px !important; max-height: 110px !important; object-fit: cover !important; transition: transform 0.25s ease; }
.daily-oc-sku-card:hover .daily-oc-sku-img { transform: scale(1.04); }
.daily-oc-sku-badge { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,0.94); backdrop-filter: blur(4px); border: 1px solid rgba(0,0,0,0.06); font-size: 0.68rem; font-weight: 800; padding: 2px 7px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); color: #0f172a; }
.daily-oc-sku-body { padding: 10px 12px; display: flex; flex-direction: column; flex: 1; justify-content: space-between; }
.daily-oc-sku-name { font-size: 0.84rem; font-weight: 700; color: #0f172a; line-height: 1.25; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.daily-oc-sku-code { font-size: 0.7rem; color: #64748b; font-family: monospace; margin-bottom: 6px; }
.daily-oc-sku-footer { display: flex; align-items: baseline; justify-content: space-between; margin-top: 4px; padding-top: 6px; border-top: 1px dashed #f1f5f9; }
.daily-oc-sku-cost { font-size: 0.98rem; font-weight: 800; color: #0f172a; }
.daily-oc-sku-units { font-size: 0.72rem; color: #64748b; font-weight: 600; }
.daily-oc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem; }
.daily-oc-table th { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.daily-oc-table th:first-child { border-top-left-radius: 8px; }
.daily-oc-table th:last-child { border-top-right-radius: 8px; }
.daily-oc-table td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.daily-oc-table tr:hover td { background: #f8fafc; }
.daily-oc-table-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 10px; font-size: 0.78rem; color: #64748b; flex-wrap: wrap; gap: 8px; }
.daily-oc-pagination { display: inline-flex; align-items: center; gap: 4px; }
.daily-oc-page-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; color: #475569; font-size: 0.76rem; font-weight: 600; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease; text-decoration: none !important; }
.daily-oc-page-btn:hover { background: #f1f5f9; color: #0f172a; }
.daily-oc-page-btn.is-active { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.daily-oc-heatmap-table { width: 100%; border-collapse: separate; border-spacing: 3px; font-size: 0.8rem; }
.daily-oc-heatmap-table th { font-size: 0.7rem; font-weight: 700; color: #64748b; padding: 6px 8px; background: transparent; border: none; text-align: right; }
.daily-oc-heatmap-table th:first-child { text-align: left; }
.daily-oc-heatmap-table td { padding: 8px 10px; border-radius: 6px; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; font-size: 0.8rem; transition: transform 0.15s ease; }
.daily-oc-heatmap-table td:first-child { text-align: left; font-weight: 600; color: #1e293b; background: transparent !important; }
.daily-oc-heatmap-table td.cell-data:hover { transform: scale(1.05); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.daily-oc-heat-green { background: #d1fae5 !important; color: #065f46 !important; }
.daily-oc-heat-amber { background: #fef3c7 !important; color: #92400e !important; }
.daily-oc-heat-red { background: #fee2e2 !important; color: #991b1b !important; }
.daily-oc-heat-legend { display: flex; align-items: center; gap: 16px; font-size: 0.72rem; color: #64748b; margin-top: 10px; flex-wrap: wrap; }
.daily-oc-legend-item { display: flex; align-items: center; gap: 6px; }
.daily-oc-legend-dot { width: 10px; height: 10px; border-radius: 3px; }
.daily-oc-modules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.daily-oc-module-link { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; text-decoration: none !important; color: inherit !important; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.18s ease; }
.daily-oc-module-link:hover { border-color: var(--daily-primary, #00C83B); background: var(--daily-primary-light, #f0fdf4); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.daily-oc-module-info { display: flex; align-items: center; gap: 10px; }
.daily-oc-module-icon { width: 32px; height: 32px; border-radius: 8px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; transition: all 0.15s ease; }
.daily-oc-module-link:hover .daily-oc-module-icon { background: var(--daily-primary, #00C83B); color: #ffffff; }
.daily-oc-module-name { font-size: 0.82rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
.daily-oc-module-sub { font-size: 0.72rem; color: #64748b; line-height: 1.2; }
.daily-oc-module-arrow { color: #94a3b8; font-size: 0.8rem; transition: transform 0.15s ease; }
.daily-oc-module-link:hover .daily-oc-module-arrow { color: var(--daily-primary, #00C83B); transform: translateX(3px); }
@media (max-width: 991px) {
	.daily-oc-hero-card { min-height: auto; }
	.daily-oc-hero-content { max-width: 60%; }
	.daily-oc-cause-row { grid-template-columns: 120px 1fr 75px 55px; }
}
@media (max-width: 767px) {
	.daily-oc-hero-content { max-width: 100%; padding: 16px; }
	.daily-oc-hero-img-wrap { display: none; }
	.daily-oc-filters-form { flex-direction: column; align-items: stretch; }
	.daily-oc-filter-group { width: 100%; }
	.daily-oc-cause-row { grid-template-columns: 100px 1fr 70px 50px; font-size: 0.75rem; }
	.daily-oc-cause-name { font-size: 0.75rem; }
	.daily-oc-heatmap-table { font-size: 0.72rem; }
}
</style>
{{-- =========================================================================
     PESTAÑA 2: MERMA OPERATIVA Y COMERCIAL (DAILY CENTRO DE CONTROL)
     ========================================================================= --}}
<div class="daily-oc-section" id="dailyOcTabContainer">

	<!-- A. ENCABEZADO Y FILTROS -->
	<div class="daily-oc-header">
		<div class="daily-oc-title-block">
			<div class="d-flex align-items-center">
				<div class="daily-oc-netsales-icon mr-2.5" style="width: 32px; height: 32px; border-radius: 8px;">
					<i class="fa-solid fa-boxes-stacked"></i>
				</div>
				<h5 class="mb-0">Merma operativa y comercial</h5>
			</div>
			<p class="mt-1">
				{{ $purpose['clasificacion'] }} {{ $startDate }} a {{ $endDate }}.
			</p>
		</div>

		<!-- Contexto compacto: Ventas netas -->
		<div class="d-flex align-items-center gap-2">
			<div class="daily-oc-netsales-badge">
				<div class="daily-oc-netsales-icon">
					<i class="fa-solid fa-cash-register"></i>
				</div>
				<div>
					<div class="daily-oc-netsales-label">Ventas netas</div>
					<div class="daily-oc-netsales-value">{{ $money($cls['net_sales']) }}</div>
					<div class="daily-oc-netsales-sub">base de los %</div>
				</div>
			</div>
			@include('components.dairy_export', ['exportScope' => '#dailyOcTabContainer', 'exportName' => 'merma_operativa_y_comercial'])
		</div>
	</div>

	<!-- BARRA DE FILTROS SINCRONIZADA -->
	<div class="daily-oc-filters">
		<form class="daily-oc-filters-form" id="dailyOcFilterForm" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros de merma operativa y comercial">
			<div class="daily-oc-filter-group" style="flex: 1.2 1 180px;">
				<label class="daily-oc-filter-label" for="oc-f-dates">Rango de Fechas</label>
				<div class="input-group input-group-sm">
					<input type="date" name="start_date" id="oc-f-start" class="form-control form-control-sm border font-weight-500" value="{{ $startDate }}" style="border-radius: 6px 0 0 6px;">
					<div class="input-group-prepend input-group-append"><span class="input-group-text px-1 bg-light border-left-0 border-right-0" style="font-size: 0.72rem;">a</span></div>
					<input type="date" name="end_date" id="oc-f-end" class="form-control form-control-sm border font-weight-500" value="{{ $endDate }}" style="border-radius: 0 6px 6px 0;">
				</div>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="oc-f-branch">Sucursal</label>
				<select name="branch_id" id="oc-f-branch" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todas las sucursales</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="oc-f-dept">Departamento</label>
				<select name="group_id" id="oc-f-dept" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos los departamentos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="oc-f-supp">Proveedor</label>
				<select name="supplier_id" id="oc-f-supp" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos los proveedores</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="oc-f-sku">SKU / Producto</label>
				<select name="product_id" id="oc-f-sku" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos los SKU</option>
					@foreach($lookups['products'] as $p)
						<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}</option>
					@endforeach
				</select>
			</div>

			@if(!empty($f['cause']))
			<div class="daily-oc-filter-group" style="flex: 0 0 auto;">
				<label class="daily-oc-filter-label">Causal activa</label>
				<div class="d-flex align-items-center">
					<span class="daily-oc-cause-code mr-1">{{ $f['cause'] }}</span>
					<a href="#" class="small text-danger font-weight-600" onclick="$(this).closest('form').find('[name=cause]').val(''); return DairyLoss.applyFilters($(this).closest('form'));" title="Quitar filtro de causal"><i class="fa-solid fa-xmark"></i></a>
				</div>
				<input type="hidden" name="cause" value="{{ $f['cause'] }}">
			</div>
			@endif

			<div class="d-flex align-items-end gap-1" style="flex: 0 0 auto;">
				<button type="submit" class="btn btn-sm btn-dark font-weight-600 shadow-xs px-3" style="border-radius: 6px; height: 31px;">
					<i class="fa-solid fa-check mr-1"></i>Aplicar
				</button>
				<a href="{{ $U('/waste#tab-clasificacion') }}" class="btn btn-sm btn-light border text-muted px-2.5" style="border-radius: 6px; height: 31px;" title="Limpiar filtros">
					<i class="fa-solid fa-rotate-left"></i>
				</a>
			</div>
		</form>
	</div>

	@if($totalEvents === 0)
		<div class="daily-oc-card text-center py-5">
			<i class="fa-regular fa-folder-open fa-2x text-muted mb-2 d-block"></i>
			<h6 class="font-weight-700 text-dark">Sin eventos de merma confirmados</h6>
			<p class="text-muted small mb-3">No se registraron pérdidas físicas ni comerciales con los filtros y período seleccionados.</p>
			<div>
				<a class="btn btn-sm btn-outline-dark mr-1 font-weight-600" href="#tab-registro" onclick="$('#tab-registro-link').tab('show'); return false;">
					<i class="fa-solid fa-plus-circle mr-1"></i>Registrar merma
				</a>
				<a class="btn btn-sm btn-light border font-weight-600 text-muted" href="{{ $U('/waste') }}">
					<i class="fa-solid fa-rotate-left mr-1"></i>Restablecer filtros
				</a>
			</div>
		</div>
	@else

	<!-- B. RESUMEN VISUAL DE MERMA OPERATIVA Y COMERCIAL -->
	<div class="row">
		<!-- MERMA OPERATIVA -->
		<div class="col-lg-6 mb-3 mb-lg-0">
			<div class="daily-oc-hero-card" style="min-height: 180px; height: 180px; position: relative; overflow: hidden; display: flex; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
				<div class="daily-oc-hero-content" style="flex: 1; z-index: 2; position: relative; padding: 20px 22px; max-width: 66%; display: flex; flex-direction: column; justify-content: space-between;">
					<div>
						<div class="daily-oc-pill-tag is-green">
							<i class="fa-solid fa-boxes-stacked"></i> Merma operativa
						</div>
						<div class="daily-oc-hero-amount">
							{{ $money($opData['cost']) }}
						</div>
						<div class="daily-oc-hero-sub">
							<strong>{{ $pct($opData['pct_sales']) }}</strong> de ventas &nbsp;·&nbsp; <strong>{{ $pct($opData['share']) }}</strong> del total
						</div>
					</div>
					<div class="daily-oc-hero-counters">
						<div class="daily-oc-counter-item">
							<span class="daily-oc-counter-num">{{ $opData['events'] }}</span>
							<span class="daily-oc-counter-lbl">eventos de merma</span>
						</div>
						<div class="daily-oc-counter-item">
							<span class="daily-oc-counter-num">{{ count($opData['skus']) }}</span>
							<span class="daily-oc-counter-lbl">SKU afectados</span>
						</div>
					</div>
				</div>
				<div class="daily-oc-hero-img-wrap" style="position: absolute; top: 0; right: 0; bottom: 0; width: 44%; max-width: 44%; z-index: 1; pointer-events: none; overflow: hidden;">
					<img src="{{ $U('/img/control_center/operativa_banner.jpg') }}" alt="Merma operativa - Fruver y Carnes" class="daily-oc-hero-img" style="width: 100% !important; height: 100% !important; object-fit: cover !important; object-position: center right !important;" onerror="this.src='{{ $U('/img/perecederos/carne_natural.jpg') }}';">
					<div class="daily-oc-hero-mask" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0.95) 15%, rgba(255, 255, 255, 0.4) 60%, rgba(255, 255, 255, 0) 100%);"></div>
				</div>
			</div>
		</div>

		<!-- MERMA COMERCIAL -->
		<div class="col-lg-6">
			<div class="daily-oc-hero-card" style="min-height: 180px; height: 180px; position: relative; overflow: hidden; display: flex; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
				<div class="daily-oc-hero-content" style="flex: 1; z-index: 2; position: relative; padding: 20px 22px; max-width: 66%; display: flex; flex-direction: column; justify-content: space-between;">
					<div>
						<div class="daily-oc-pill-tag is-amber">
							<i class="fa-solid fa-tag"></i> Merma comercial
						</div>
						<div class="daily-oc-hero-amount">
							{{ $money($comData['cost']) }}
						</div>
						<div class="daily-oc-hero-sub">
							<strong>{{ $pct($comData['pct_sales']) }}</strong> de ventas &nbsp;·&nbsp; <strong>{{ $pct($comData['share']) }}</strong> del total
						</div>
					</div>
					<div class="daily-oc-hero-counters">
						<div class="daily-oc-counter-item">
							<span class="daily-oc-counter-num">{{ $comData['events'] }}</span>
							<span class="daily-oc-counter-lbl">eventos de merma</span>
						</div>
						<div class="daily-oc-counter-item">
							<span class="daily-oc-counter-num">{{ count($comData['skus']) }}</span>
							<span class="daily-oc-counter-lbl">SKU afectados</span>
						</div>
					</div>
				</div>
				<div class="daily-oc-hero-img-wrap" style="position: absolute; top: 0; right: 0; bottom: 0; width: 44%; max-width: 44%; z-index: 1; pointer-events: none; overflow: hidden;">
					<img src="{{ $U('/img/control_center/comercial_banner.jpg') }}" alt="Merma comercial - Lácteos y Perecederos" class="daily-oc-hero-img" style="width: 100% !important; height: 100% !important; object-fit: cover !important; object-position: center right !important;" onerror="this.src='{{ $U('/img/perecederos/fruver_natural.jpg') }}';">
					<div class="daily-oc-hero-mask" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0.95) 15%, rgba(255, 255, 255, 0.4) 60%, rgba(255, 255, 255, 0) 100%);"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- C. DISTRIBUCIÓN POR CAUSALES -->
	<div class="row">
		<!-- OPERATIVA POR CAUSAL -->
		<div class="col-lg-6 mb-3 mb-lg-0">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<h6 class="daily-oc-card-title">
						<i class="fa-solid fa-chart-simple text-success mr-2"></i>Merma operativa por causal
					</h6>
					<span class="text-muted small">Base: {{ $money($opData['cost']) }}</span>
				</div>
				<div class="daily-oc-cause-list">
					@php $maxOpCause = max(0.01, ...array_values($opData['causes'] ?: [0])); @endphp
					@forelse($opData['causes'] as $code => $val)
						@php
							$pClass = $opData['cost'] > 0 ? ($val / $opData['cost'] * 100) : 0;
							$pBar = min(100, max(4, ($val / $maxOpCause) * 100));
							$fullName = $causeCatalog[$code] ?? $code;
						@endphp
						<a href="javascript:void(0)" class="daily-oc-cause-row" onclick="dailyFilterByCause('{{ $code }}');" title="Filtrar por causal: {{ $fullName }} ({{ $code }})">
							<div class="daily-oc-cause-name-wrap">
								<span class="daily-oc-cause-code">{{ $code }}</span>
								<span class="daily-oc-cause-name" title="{{ $fullName }}">{{ $fullName }}</span>
							</div>
							<div class="daily-oc-bar-track">
								<div class="daily-oc-bar-fill is-green" style="width: {{ round($pBar, 1) }}%;"></div>
							</div>
							<div class="daily-oc-cause-money">{{ $money($val) }}</div>
							<div class="daily-oc-cause-pct">{{ number_format($pClass, 1) }}%</div>
						</a>
					@empty
						<div class="text-muted small py-3 text-center">Sin causales operativas en este período.</div>
					@endforelse
				</div>
			</div>
		</div>

		<!-- COMERCIAL POR CAUSAL -->
		<div class="col-lg-6">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<h6 class="daily-oc-card-title">
						<i class="fa-solid fa-chart-simple text-warning mr-2"></i>Merma comercial por causal
					</h6>
					<span class="text-muted small">Base: {{ $money($comData['cost']) }}</span>
				</div>
				<div class="daily-oc-cause-list">
					@php $maxComCause = max(0.01, ...array_values($comData['causes'] ?: [0])); @endphp
					@forelse($comData['causes'] as $code => $val)
						@php
							$pClass = $comData['cost'] > 0 ? ($val / $comData['cost'] * 100) : 0;
							$pBar = min(100, max(4, ($val / $maxComCause) * 100));
							$fullName = $causeCatalog[$code] ?? $code;
						@endphp
						<a href="javascript:void(0)" class="daily-oc-cause-row" onclick="dailyFilterByCause('{{ $code }}');" title="Filtrar por causal: {{ $fullName }} ({{ $code }})">
							<div class="daily-oc-cause-name-wrap">
								<span class="daily-oc-cause-code">{{ $code }}</span>
								<span class="daily-oc-cause-name" title="{{ $fullName }}">{{ $fullName }}</span>
							</div>
							<div class="daily-oc-bar-track">
								<div class="daily-oc-bar-fill is-amber" style="width: {{ round($pBar, 1) }}%;"></div>
							</div>
							<div class="daily-oc-cause-money">{{ $money($val) }}</div>
							<div class="daily-oc-cause-pct">{{ number_format($pClass, 1) }}%</div>
						</a>
					@empty
						<div class="text-muted small py-3 text-center">Sin causales comerciales en este período.</div>
					@endforelse
				</div>
			</div>
		</div>
	</div>

	<!-- D. PRODUCTOS CON MAYOR IMPACTO -->
	<div class="daily-oc-card">
		<div class="daily-oc-card-header">
			<h6 class="daily-oc-card-title">
				<i class="fa-solid fa-cubes text-success mr-2"></i>SKU con mayor impacto
			</h6>
			<div class="btn-group btn-group-sm" role="group" aria-label="Selector de clase de impacto">
				<button type="button" class="btn btn-outline-success font-weight-600 active" id="btnImpactOp" onclick="dailyToggleImpact('operativa');">
					Operativa
				</button>
				<button type="button" class="btn btn-outline-secondary font-weight-600" id="btnImpactCom" onclick="dailyToggleImpact('comercial');">
					Comercial
				</button>
			</div>
		</div>

		<!-- GRID DE CARDS OPERATIVA -->
		<div class="daily-oc-sku-grid" id="gridImpactOperativa">
			@forelse(array_slice($opData['skus'], 0, 5) as $s)
				<a href="javascript:void(0)" class="daily-oc-sku-card" onclick="dailyFilterBySku({{ $s['product_id'] }});" title="Ver detalle de {{ $s['name'] }}">
					<div class="daily-oc-sku-img-wrap">
						<img src="{{ $productPhoto($s) }}" alt="{{ $s['name'] }}" class="daily-oc-sku-img" onerror="this.src='{{ $U('/img/icon-32.png') }}'; this.style.objectFit='contain'; this.style.padding='20px';">
						@if(!empty($s['main_cause']) && $s['main_cause'] !== '—')
							<span class="daily-oc-sku-badge">{{ $s['main_cause'] }}</span>
						@endif
					</div>
					<div class="daily-oc-sku-body">
						<div>
							<div class="daily-oc-sku-name" title="{{ $s['name'] }}">{{ $s['name'] }}</div>
							<div class="daily-oc-sku-code">{{ !empty($s['sku']) ? $s['sku'] : 'ID #' . $s['product_id'] }}</div>
						</div>
						<div class="daily-oc-sku-footer">
							<span class="daily-oc-sku-cost">{{ $money($s['cost']) }}</span>
							<span class="daily-oc-sku-units">{{ number_format($s['units'], 2) }} {{ $s['qu_name'] ?? 'unid.' }}</span>
						</div>
					</div>
				</a>
			@empty
				<div class="text-muted small py-4 text-center w-100">Sin productos operativos en este período.</div>
			@endforelse
		</div>

		<!-- GRID DE CARDS COMERCIAL -->
		<div class="daily-oc-sku-grid d-none" id="gridImpactComercial">
			@forelse(array_slice($comData['skus'], 0, 5) as $s)
				<a href="javascript:void(0)" class="daily-oc-sku-card" onclick="dailyFilterBySku({{ $s['product_id'] }});" title="Ver detalle de {{ $s['name'] }}">
					<div class="daily-oc-sku-img-wrap">
						<img src="{{ $productPhoto($s) }}" alt="{{ $s['name'] }}" class="daily-oc-sku-img" onerror="this.src='{{ $U('/img/icon-32.png') }}'; this.style.objectFit='contain'; this.style.padding='20px';">
						@if(!empty($s['main_cause']) && $s['main_cause'] !== '—')
							<span class="daily-oc-sku-badge">{{ $s['main_cause'] }}</span>
						@endif
					</div>
					<div class="daily-oc-sku-body">
						<div>
							<div class="daily-oc-sku-name" title="{{ $s['name'] }}">{{ $s['name'] }}</div>
							<div class="daily-oc-sku-code">{{ !empty($s['sku']) ? $s['sku'] : 'ID #' . $s['product_id'] }}</div>
						</div>
						<div class="daily-oc-sku-footer">
							<span class="daily-oc-sku-cost">{{ $money($s['cost']) }}</span>
							<span class="daily-oc-sku-units">{{ number_format($s['units'], 2) }} {{ $s['qu_name'] ?? 'unid.' }}</span>
						</div>
					</div>
				</a>
			@empty
				<div class="text-muted small py-4 text-center w-100">Sin productos comerciales en este período.</div>
			@endforelse
		</div>
	</div>

	<!-- E. DETALLE COMPLETO POR SKU -->
	<div class="row">
		<!-- TABLA SKU OPERATIVA -->
		<div class="col-lg-6 mb-3 mb-lg-0">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<h6 class="daily-oc-card-title">
						<i class="fa-solid fa-boxes-stacked text-success mr-2"></i>Detalle de SKU – Merma operativa
					</h6>
					<a href="javascript:void(0)" class="small font-weight-600 text-primary" id="btnToggleAllOpSku" onclick="dailyToggleAllRows('tableSkuOp', this);">
						Ver todos
					</a>
				</div>
				<div class="table-responsive">
					<table class="daily-oc-table" id="tableSkuOp" data-page-size="5">
						<thead>
							<tr>
								<th style="width: 32px;">#</th>
								<th>Producto</th>
								<th>SKU</th>
								<th class="dlx-hide-sm">Código de barras</th>
								<th class="dlx-hide-sm">Depto.</th>
								<th class="text-right">Unid.</th>
								<th class="text-right">Costo</th>
							</tr>
						</thead>
						<tbody>
							@forelse($opData['skus'] as $idx => $r)
								<tr class="daily-sku-op-row" data-product-id="{{ $r['product_id'] }}">
									<td class="text-muted small">{{ $idx + 1 }}</td>
									<td class="font-weight-600 text-dark">
										<a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" class="text-dark" title="Ver kardex de {{ $r['name'] }}">
											{{ $r['name'] ?? '#' . $r['product_id'] }}
										</a>
									</td>
									<td class="font-mono small text-muted">{{ !empty($r['sku']) ? $r['sku'] : '—' }}</td>
									<td class="dlx-hide-sm font-mono small text-muted">{{ !empty($r['barcode']) ? $r['barcode'] : '—' }}</td>
									<td class="dlx-hide-sm small text-muted" title="{{ $r['department'] ?? '' }}">{{ \Illuminate\Support\Str::limit($r['department'] ?? '—', 14) }}</td>
									<td class="text-right font-variant-numeric">{{ number_format($r['units'], 2) }}</td>
									<td class="text-right font-weight-700 text-dark">{{ $money($r['cost']) }}</td>
								</tr>
							@empty
								<tr><td colspan="7" class="text-muted text-center py-3">Sin registros de merma operativa.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				<div class="daily-oc-table-footer" id="footerSkuOp">
					<span id="counterSkuOp">Mostrando {{ min(5, count($opData['skus'])) }} de {{ count($opData['skus']) }} SKU</span>
					<div class="daily-oc-pagination" id="pagSkuOp"></div>
				</div>
			</div>
		</div>

		<!-- TABLA SKU COMERCIAL -->
		<div class="col-lg-6">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<h6 class="daily-oc-card-title">
						<i class="fa-solid fa-tag text-warning mr-2"></i>Detalle de SKU – Merma comercial
					</h6>
					<a href="javascript:void(0)" class="small font-weight-600 text-primary" id="btnToggleAllComSku" onclick="dailyToggleAllRows('tableSkuCom', this);">
						Ver todos
					</a>
				</div>
				<div class="table-responsive">
					<table class="daily-oc-table" id="tableSkuCom" data-page-size="5">
						<thead>
							<tr>
								<th style="width: 32px;">#</th>
								<th>Producto</th>
								<th>SKU</th>
								<th class="dlx-hide-sm">Código de barras</th>
								<th class="dlx-hide-sm">Depto.</th>
								<th class="text-right">Unid.</th>
								<th class="text-right">Costo</th>
							</tr>
						</thead>
						<tbody>
							@forelse($comData['skus'] as $idx => $r)
								<tr class="daily-sku-com-row" data-product-id="{{ $r['product_id'] }}">
									<td class="text-muted small">{{ $idx + 1 }}</td>
									<td class="font-weight-600 text-dark">
										<a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" class="text-dark" title="Ver kardex de {{ $r['name'] }}">
											{{ $r['name'] ?? '#' . $r['product_id'] }}
										</a>
									</td>
									<td class="font-mono small text-muted">{{ !empty($r['sku']) ? $r['sku'] : '—' }}</td>
									<td class="dlx-hide-sm font-mono small text-muted">{{ !empty($r['barcode']) ? $r['barcode'] : '—' }}</td>
									<td class="dlx-hide-sm small text-muted" title="{{ $r['department'] ?? '' }}">{{ \Illuminate\Support\Str::limit($r['department'] ?? '—', 14) }}</td>
									<td class="text-right font-variant-numeric">{{ number_format($r['units'], 2) }}</td>
									<td class="text-right font-weight-700 text-dark">{{ $money($r['cost']) }}</td>
								</tr>
							@empty
								<tr><td colspan="7" class="text-muted text-center py-3">Sin registros de merma comercial.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				<div class="daily-oc-table-footer" id="footerSkuCom">
					<span id="counterSkuCom">Mostrando {{ min(5, count($comData['skus'])) }} de {{ count($comData['skus']) }} SKU</span>
					<div class="daily-oc-pagination" id="pagSkuCom"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- F. MOVIMIENTOS DE ORIGEN (ÚLTIMOS) -->
	@php
		$allRecent = array_merge($opData['recent'], $comData['recent']);
		usort($allRecent, fn($a, $b) => strcmp($b['d'], $a['d']) ?: strcmp($b['uid'], $a['uid']));
	@endphp
	<div class="daily-oc-card">
		<div class="daily-oc-card-header">
			<h6 class="daily-oc-card-title">
				<i class="fa-solid fa-list-check text-success mr-2"></i>Movimientos de origen (últimos)
			</h6>
			<div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
				<div class="input-group input-group-sm" style="max-width: 260px;">
					<div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-search text-muted"></i></span></div>
					<input type="text" class="form-control form-control-sm border-left-0" id="searchMovements" placeholder="Buscar por producto, SKU o documento..." onkeyup="dailyFilterMovements();">
				</div>
				<select class="form-control form-control-sm border font-weight-600 text-dark" id="filterClassMovements" style="width: auto; height: 31px;" onchange="dailyFilterMovements();">
					<option value="">Todas las clases</option>
					<option value="operativa">Merma operativa</option>
					<option value="comercial">Merma comercial</option>
				</select>
				<a href="javascript:void(0)" class="small font-weight-600 text-primary px-2" id="btnToggleAllMovements" onclick="dailyToggleAllMovements(this);">
					Ver todos
				</a>
			</div>
		</div>

		<div class="table-responsive">
			<table class="daily-oc-table" id="tableMovements" data-page-size="5">
				<thead>
					<tr>
						<th>ID Evento</th>
						<th>Fecha</th>
						<th>Clase</th>
						<th>Causal</th>
						<th>Producto</th>
						<th class="text-right">Unid.</th>
						<th class="text-right">Costo</th>
						<th>Documento</th>
					</tr>
				</thead>
				<tbody id="tbodyMovements">
					@forelse($allRecent as $e)
						@php
							$pName = $names[$e['product_id']]['name'] ?? ('#' . $e['product_id']);
							$pSku = $names[$e['product_id']]['sku'] ?? '';
							$cName = $causeCatalog[$e['cause']] ?? $e['cause'];
							$isOp = ($e['loss_class'] === 'operativa');
						@endphp
						<tr class="daily-mov-row" data-class="{{ $e['loss_class'] }}" data-product="{{ mb_strtolower($pName) }}" data-sku="{{ mb_strtolower($pSku) }}" data-doc="{{ mb_strtolower($e['doc'] ?? '') }}" data-cause="{{ $e['cause'] }}">
							<td class="font-mono small font-weight-600 text-dark">{{ $e['uid'] }}</td>
							<td class="small text-muted">{{ $e['d'] }}</td>
							<td>
								<span class="daily-oc-pill-tag {{ $isOp ? 'is-green' : 'is-amber' }}" style="padding: 2px 7px; font-size: 0.72rem;">
									{{ $isOp ? 'Merma operativa' : 'Merma comercial' }}
								</span>
							</td>
							<td>
								<span class="daily-oc-cause-code" title="{{ $cName }}">{{ $e['cause'] }}</span>
							</td>
							<td class="font-weight-600 text-dark">
								<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}" class="text-dark" title="Kardex de {{ $pName }}">
									{{ $pName }}
								</a>
							</td>
							<td class="text-right font-variant-numeric">{{ number_format($e['units'], 2) }}</td>
							<td class="text-right font-weight-700 text-dark">{{ $money($e['cost']) }}</td>
							<td class="small font-mono">
								@if($e['src'] === 'waste')
									<a href="{{ $U('/document/waste/' . $e['ref']) }}" class="text-primary font-weight-600" title="Ver documento de merma">
										{{ $e['doc'] }}
									</a>
								@elseif($e['src'] === 'inventory_adjustment')
									<a href="{{ $U('/document/adjustment/' . $e['ref']) }}" class="text-primary font-weight-600" title="Ver ajuste de inventario">
										{{ $e['doc'] }}
									</a>
								@else
									<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}" class="text-muted">
										{{ $e['doc'] ?? 'Kardex' }}
									</a>
								@endif
							</td>
						</tr>
					@empty
						<tr><td colspan="8" class="text-muted text-center py-3">Sin movimientos de origen registrados en el período.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>
		<div class="daily-oc-table-footer">
			<span id="counterMovements">Mostrando {{ min(5, count($allRecent)) }} de {{ count($allRecent) }} movimientos</span>
			<div class="daily-oc-pagination" id="pagMovements"></div>
		</div>
	</div>

	<!-- G. COMPARACIÓN POR FORMATO, SUCURSAL Y DEPARTAMENTO -->
	@if($formats)
	<div class="row">
		<!-- MERMA POR FORMATO Y SUCURSAL -->
		<div class="col-lg-7 mb-3 mb-lg-0">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<div>
						<h6 class="daily-oc-card-title">
							<i class="fa-solid fa-store text-success mr-2"></i>Merma por formato, sucursal y departamento
						</h6>
						<div class="text-muted small mt-0.5">
							Compare por % de ventas netas (desempeño relativo), no por $ (tamaño).
							@if(!$formats['has_format'])
								<span class="badge badge-warning ml-1 font-weight-600">Formato comercial no cargado</span>
							@endif
						</div>
					</div>
				</div>

				<div class="table-responsive">
					<table class="daily-oc-table" data-export-title="Merma por sucursal y formato">
						<thead>
							<tr>
								<th>Sucursal</th>
								<th>Formato</th>
								<th class="text-right">Ventas netas</th>
								<th class="text-right">Operativa $</th>
								<th class="text-right">Operativa %</th>
								<th class="text-right">Total %</th>
							</tr>
						</thead>
						<tbody>
							@php
								$totNetSales = 0; $totOpCost = 0; $totLoss = 0;
							@endphp
							@foreach($formats['branches'] as $b)
								@php
									$totNetSales += $b['net_sales'];
									$totOpCost += $b['operativa'];
									$totLoss += $b['total'];
								@endphp
								<tr>
									<td class="font-weight-600 text-dark">{{ $b['branch'] }}</td>
									<td>
										<span class="daily-oc-cause-code">{{ $b['format'] }}</span>
										@if(\Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_BRANCH_MANAGE]))
											<a href="#" class="small text-muted ml-1" onclick='DairyLoss.dialog("Formato de {{ e($b['branch']) }}", "<div class=\"form-group\"><label>Formato comercial</label><input class=\"form-control\" name=\"format\" value=\"{{ e($b['format'] === 'Sin formato' ? '' : $b['format']) }}\" placeholder=\"Hiper, Súper, Express…\"></div><div class=\"form-group\"><label>Superficie de venta (m²)</label><input class=\"form-control\" type=\"number\" step=\"any\" min=\"0\" name=\"sales_area_m2\" value=\"{{ $b['area_m2'] }}\"></div>", "loss/branches/{{ $b['branch_id'] }}/format"); return false;' aria-label="Editar formato">
												<i class="fa-solid fa-pen text-muted"></i>
											</a>
										@endif
									</td>
									<td class="text-right font-variant-numeric">{{ $money($b['net_sales']) }}</td>
									<td class="text-right font-variant-numeric">{{ $money($b['operativa']) }}</td>
									<td class="text-right font-weight-700 text-dark">{{ $pct($b['operativa_pct']) }}</td>
									<td class="text-right font-weight-700 {{ ($b['total_pct'] ?? 0) > 4 ? 'text-danger' : 'text-dark' }}">{{ $pct($b['total_pct']) }}</td>
								</tr>
							@endforeach
						</tbody>
						<tfoot>
							<tr class="font-weight-700" style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
								<td colspan="2">Sin formato {{ count($formats['branches']) }}</td>
								<td class="text-right font-variant-numeric">{{ $money($totNetSales) }}</td>
								<td class="text-right font-variant-numeric">{{ $money($totOpCost) }}</td>
								<td class="text-right font-weight-800 text-dark">{{ $totNetSales > 0 ? number_format($totOpCost / $totNetSales * 100, 2) . '%' : '—' }}</td>
								<td class="text-right font-weight-800 text-danger">{{ $totNetSales > 0 ? number_format($totLoss / $totNetSales * 100, 2) . '%' : '—' }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>

		<!-- MAPA DE CALOR: SUCURSAL X DEPARTAMENTO -->
		<div class="col-lg-5">
			<div class="daily-oc-card h-100">
				<div class="daily-oc-card-header">
					<h6 class="daily-oc-card-title">
						<i class="fa-solid fa-fire-flame-curved text-danger mr-2"></i>Mapa de calor: sucursal x departamento
					</h6>
					<select class="form-control form-control-sm border font-weight-600 text-dark" id="selHeatmapMode" style="width: auto; height: 30px;" onchange="dailyUpdateHeatmapMode(this.value);">
						<option value="pct" selected>Merma total %</option>
						<option value="money">Total de mermas ($)</option>
					</select>
				</div>

				@php
					$hv = $formats['heatmap']['values'] ?? [];
					$hRows = $formats['heatmap']['rows'] ?? [];
					$hCols = $formats['heatmap']['cols'] ?? [];
					$branchSalesMap = [];
					foreach ($formats['branches'] as $b) {
						$branchSalesMap[$b['branch']] = $b['net_sales'];
					}
				@endphp

				@if(!$hv || empty($hCols))
					<div class="text-muted small py-4 text-center">Sin pérdidas por departamento en el período.</div>
				@else
				<div class="table-responsive">
					<table class="daily-oc-heatmap-table" id="tableHeatmap" data-export-title="Mapa de calor sucursal x departamento">
						<thead>
							<tr>
								<th>Sucursal</th>
								@foreach($hCols as $c)
									@php
										$cleanCol = preg_replace('/^\d+\s*/', '', $c);
										$cleanCol = str_ireplace(['Productos de carnicería', 'Lácteos y Leches', 'Quesos y Untables', 'Bebidas Refrigeradas'], ['Carnes', 'Lácteos', 'Quesos', 'Bebidas'], $cleanCol);
									@endphp
									<th title="{{ $c }}">{{ $cleanCol }}</th>
								@endforeach
							</tr>
						</thead>
						<tbody>
							@foreach($hRows as $r)
								@php $bSales = $branchSalesMap[$r] ?? 0; @endphp
								<tr>
									<td>{{ $r }}</td>
									@foreach($hCols as $c)
										@php
											$val = $hv[$r][$c] ?? 0;
											$pSales = $bSales > 0 ? ($val / $bSales * 100) : 0;
											$heatClass = 'daily-oc-heat-green';
											if ($pSales >= 4.5 || $val > 80) {
												$heatClass = 'daily-oc-heat-red';
											} elseif ($pSales >= 1.5 || $val > 25) {
												$heatClass = 'daily-oc-heat-amber';
											}
										@endphp
										<td class="cell-data {{ $val > 0 ? $heatClass : '' }}" data-val="{{ $val }}" data-pct="{{ number_format($pSales, 1) }}%" title="{{ $r }} · {{ $c }}: {{ $money($val) }} ({{ number_format($pSales, 1) }}%)">
											<span class="heat-text">{{ $val > 0 ? number_format($pSales, 1) . '%' : '·' }}</span>
										</td>
									@endforeach
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="daily-oc-heat-legend">
					<div class="daily-oc-legend-item">
						<span class="daily-oc-legend-dot daily-oc-heat-green"></span>
						<span>&lt; 1.5% En norma</span>
					</div>
					<div class="daily-oc-legend-item">
						<span class="daily-oc-legend-dot daily-oc-heat-amber"></span>
						<span>1.5% – 4.5% En observación</span>
					</div>
					<div class="daily-oc-legend-item">
						<span class="daily-oc-legend-dot daily-oc-heat-red"></span>
						<span>&gt; 4.5% Crítico</span>
					</div>
				</div>
				@endif
			</div>
		</div>
	</div>
	@endif

	<!-- H. MÓDULOS CONECTADOS CON CENTRO DE CONTROL -->
	<div class="daily-oc-card">
		<div class="daily-oc-card-header">
			<h6 class="daily-oc-card-title">
				<i class="fa-solid fa-network-wired text-success mr-2"></i>Módulos conectados con Centro de Control
			</h6>
		</div>

		<div class="row">
			<!-- RECIBE INFORMACIÓN DE -->
			<div class="col-lg-5 mb-3 mb-lg-0">
				<div class="text-uppercase small font-weight-700 text-muted mb-2" style="font-size: 0.68rem; letter-spacing: 0.04em;">
					Recibe información de
				</div>
				<div class="d-flex flex-column gap-2">
					<a href="#tab-fefo" class="daily-oc-module-link" onclick="$('#tab-fefo-link').click(); return false;">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon">
								<i class="fa-solid fa-snowflake text-primary"></i>
							</div>
							<div>
								<div class="daily-oc-module-name">Calidad & Vencimientos</div>
								<div class="daily-oc-module-sub">Rebajas por vencimiento</div>
							</div>
						</div>
						<div class="daily-oc-module-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<a href="{{ $U('/workforce') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon">
								<i class="fa-solid fa-list-check text-success"></i>
							</div>
							<div>
								<div class="daily-oc-module-name">Tareas y Roles</div>
								<div class="daily-oc-module-sub">Mermas desde Piso de Tienda</div>
							</div>
						</div>
						<div class="daily-oc-module-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
				</div>
			</div>

			<!-- ACTUALIZA / ALIMENTA A -->
			<div class="col-lg-7">
				<div class="text-uppercase small font-weight-700 text-muted mb-2" style="font-size: 0.68rem; letter-spacing: 0.04em;">
					Actualiza / Alimenta a
				</div>
				<div class="daily-oc-modules-grid">
					<a href="{{ $U('/today') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon">
								<i class="fa-solid fa-chart-line text-info"></i>
							</div>
							<div>
								<div class="daily-oc-module-name">Panel Ejecutivo</div>
								<div class="daily-oc-module-sub">Merma en el Panel Ejecutivo</div>
							</div>
						</div>
						<div class="daily-oc-module-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<a href="{{ $U('/cost-centers') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon">
								<i class="fa-solid fa-building-columns text-warning"></i>
							</div>
							<div>
								<div class="daily-oc-module-name">Centros de Costo</div>
								<div class="daily-oc-module-sub">Merma por centro de costo</div>
							</div>
						</div>
						<div class="daily-oc-module-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<a href="#tab-censo" class="daily-oc-module-link" onclick="$('#tab-censo-link').click(); return false;">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon">
								<i class="fa-solid fa-file-invoice text-success"></i>
							</div>
							<div>
								<div class="daily-oc-module-name">Reportes & Analítica</div>
								<div class="daily-oc-module-sub">Desglose de merma (censo)</div>
							</div>
						</div>
						<div class="daily-oc-module-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
				</div>
			</div>
		</div>
	</div>

	@endif
</div>

{{-- SCRIPT INTERACTIVO CLIENT-SIDE PARA PAGINACIÓN, BÚSQUEDA Y DRILL-DOWN --}}
<script>
(function() {
	// 1. Alternar SKU con mayor impacto (Operativa / Comercial)
	window.dailyToggleImpact = function(type) {
		if (type === 'operativa') {
			$('#gridImpactOperativa').removeClass('d-none');
			$('#gridImpactComercial').addClass('d-none');
			$('#btnImpactOp').addClass('active btn-outline-success').removeClass('btn-outline-secondary');
			$('#btnImpactCom').removeClass('active btn-outline-success').addClass('btn-outline-secondary');
		} else {
			$('#gridImpactComercial').removeClass('d-none');
			$('#gridImpactOperativa').addClass('d-none');
			$('#btnImpactCom').addClass('active btn-outline-success').removeClass('btn-outline-secondary');
			$('#btnImpactOp').removeClass('active btn-outline-success').addClass('btn-outline-secondary');
		}
	};

	// 2. Filtro interactivo por causal
	window.dailyFilterByCause = function(code) {
		var form = $('#dailyOcFilterForm');
		if (!form.length) return;
		var inp = form.find('input[name=cause]');
		if (!inp.length) {
			inp = $('<input type="hidden" name="cause">').appendTo(form);
		}
		inp.val(code);
		DairyLoss.applyFilters(form);
	};

	// 3. Filtro interactivo por SKU
	window.dailyFilterBySku = function(productId) {
		var sel = $('#oc-f-sku');
		if (sel.length) {
			sel.val(productId);
			DairyLoss.applyFilters($('#dailyOcFilterForm'));
		}
	};

	// 4. Paginación genérica de tablas
	function initTablePagination(tableId, pagId, counterId, itemLabel) {
		var table = document.getElementById(tableId);
		if (!table) return;
		var pageSize = parseInt(table.getAttribute('data-page-size') || 5);
		var rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => !r.classList.contains('d-none-filtered'));
		var total = rows.length;
		var totalPages = Math.ceil(total / pageSize) || 1;
		var curPage = 1;

		function render(page) {
			curPage = page;
			rows.forEach(function(row, idx) {
				if (idx >= (page - 1) * pageSize && idx < page * pageSize) {
					row.style.display = '';
				} else {
					row.style.display = 'none';
				}
			});

			var counterEl = document.getElementById(counterId);
			if (counterEl) {
				var countShow = Math.min(total, page * pageSize);
				counterEl.innerText = 'Mostrando ' + (total > 0 ? (Math.min(total, (page - 1) * pageSize + 1) + '–' + countShow) : '0') + ' de ' + total + ' ' + itemLabel;
			}

			var pagEl = document.getElementById(pagId);
			if (pagEl) {
				pagEl.innerHTML = '';
				if (totalPages <= 1) return;

				// Botón anterior
				var prev = document.createElement('a');
				prev.className = 'daily-oc-page-btn' + (curPage === 1 ? ' text-muted disabled' : '');
				prev.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size: 0.65rem;"></i>';
				prev.onclick = function() { if (curPage > 1) render(curPage - 1); };
				pagEl.appendChild(prev);

				// Páginas
				for (var p = 1; p <= totalPages; p++) {
					(function(pageNum) {
						var btn = document.createElement('a');
						btn.className = 'daily-oc-page-btn' + (pageNum === curPage ? ' is-active' : '');
						btn.innerText = pageNum;
						btn.onclick = function() { render(pageNum); };
						pagEl.appendChild(btn);
					})(p);
				}

				// Botón siguiente
				var next = document.createElement('a');
				next.className = 'daily-oc-page-btn' + (curPage === totalPages ? ' text-muted disabled' : '');
				next.innerHTML = '<i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>';
				next.onclick = function() { if (curPage < totalPages) render(curPage + 1); };
				pagEl.appendChild(next);
			}
		}

		render(1);
		table.__dailyRender = render;
	}

	// 5. Ver todos los registros de una tabla
	window.dailyToggleAllRows = function(tableId, btn) {
		var table = document.getElementById(tableId);
		if (!table) return;
		var isAll = table.getAttribute('data-is-all') === 'true';
		if (isAll) {
			table.setAttribute('data-page-size', '5');
			table.setAttribute('data-is-all', 'false');
			btn.innerText = 'Ver todos';
		} else {
			table.setAttribute('data-page-size', '9999');
			table.setAttribute('data-is-all', 'true');
			btn.innerText = 'Paginado';
		}
		if (table.__dailyRender) table.__dailyRender(1);
	};

	// 6. Búsqueda y filtrado de Movimientos de origen
	window.dailyFilterMovements = function() {
		var q = (document.getElementById('searchMovements').value || '').toLowerCase().trim();
		var cls = (document.getElementById('filterClassMovements').value || '').trim();
		var rows = document.querySelectorAll('#tbodyMovements .daily-mov-row');
		var visibleCount = 0;

		rows.forEach(function(row) {
			var rCls = row.getAttribute('data-class') || '';
			var rProd = row.getAttribute('data-product') || '';
			var rSku = row.getAttribute('data-sku') || '';
			var rDoc = row.getAttribute('data-doc') || '';
			var rCause = (row.getAttribute('data-cause') || '').toLowerCase();

			var matchCls = !cls || (rCls === cls);
			var matchQ = !q || (rProd.indexOf(q) !== -1 || rSku.indexOf(q) !== -1 || rDoc.indexOf(q) !== -1 || rCause.indexOf(q) !== -1);

			if (matchCls && matchQ) {
				row.classList.remove('d-none-filtered');
				visibleCount++;
			} else {
				row.classList.add('d-none-filtered');
				row.style.display = 'none';
			}
		});

		initTablePagination('tableMovements', 'pagMovements', 'counterMovements', 'movimientos');
	};

	window.dailyToggleAllMovements = function(btn) {
		dailyToggleAllRows('tableMovements', btn);
	};

	// 7. Alternar modo del mapa de calor (% vs $)
	window.dailyUpdateHeatmapMode = function(mode) {
		var cells = document.querySelectorAll('#tableHeatmap td.cell-data');
		cells.forEach(function(c) {
			var span = c.querySelector('.heat-text');
			if (!span) return;
			if (mode === 'money') {
				var v = parseFloat(c.getAttribute('data-val') || 0);
				span.innerText = v > 0 ? ('$' + v.toFixed(2)) : '·';
			} else {
				span.innerText = c.getAttribute('data-pct') || '·';
			}
		});
	};

	// Inicializar paginadores
	initTablePagination('tableSkuOp', 'pagSkuOp', 'counterSkuOp', 'SKU');
	initTablePagination('tableSkuCom', 'pagSkuCom', 'counterSkuCom', 'SKU');
	initTablePagination('tableMovements', 'pagMovements', 'counterMovements', 'movimientos');
})();
</script>

@elseif($section === 'ajustes')
	@include('components.waste_tabs.ajustes')
@elseif($section === 'averias')
	@include('components.waste_tabs.averias')
@else
{{-- Fallback --}}
<div class="dlx-section">
	<div class="d-flex justify-content-between align-items-start flex-wrap dairy-gap-2">
		<div><h5>{{ $titles[$section] }}</h5><div class="dlx-sub">{{ $purpose[$section] }} {{ $startDate }} a {{ $endDate }}.</div></div>
		@include('components.dairy_export', ['exportScope' => '#tab-' . $section, 'exportName' => 'merma_' . $section])
	</div>
	@include('components.waste_tabs._loss_filters', ['noClass' => true])

	<div class="dlx-kpis">
		@foreach($sections as $c)
			@php $x = $cls['classes'][$c]; @endphp
			<div class="dlx-kpi dlx-tone-red"><div class="k-l">{{ $x['label'] }}</div><div class="k-v">{{ $money($x['cost']) }}</div><div class="k-s">{{ $pct($x['pct_sales']) }} de ventas · {{ $pct($x['share']) }} del total</div></div>
			<div class="dlx-kpi"><div class="k-l">Eventos {{ mb_strtolower($x['label']) }}</div><div class="k-v">{{ $x['events'] }}</div><div class="k-s">{{ count($x['skus']) }} SKU afectados</div></div>
		@endforeach
		@if($section === 'ajustes')
			<div class="dlx-kpi dlx-tone-red"><div class="k-l">Faltantes</div><div class="k-v">{{ $money($summary['ajuste']['loss'] ?? 0) }}</div><div class="k-s">pérdida por diferencias</div></div>
			<div class="dlx-kpi dlx-tone-sage"><div class="k-l">Sobrantes</div><div class="k-v">{{ $money($summary['ajuste']['gain'] ?? 0) }}</div><div class="k-s">neto {{ $money($summary['ajuste']['value'] ?? 0) }}</div></div>
		@endif
		<div class="dlx-kpi dlx-tone-graphite"><div class="k-l">Ventas netas</div><div class="k-v">{{ $money($cls['net_sales']) }}</div><div class="k-s">base de los %</div></div>
	</div>

	@if($totalEvents === 0)
		<div class="dlx-empty"><i class="fa-regular fa-folder-open fa-lg d-block mb-2"></i>Sin eventos confirmados en el período y filtros elegidos.
			<div><a class="btn btn-sm btn-outline-dark" href="#tab-registro" onclick="$('#tab-registro-link').tab('show'); return false;">Registrar merma</a>
			<a class="btn btn-sm btn-outline-dark" href="{{ $U('/data-import') }}">Importar histórico</a></div></div>
	@else
	<div class="row">
		@foreach($sections as $c)
			@php $x = $cls['classes'][$c]; @endphp
			<div class="col-lg-{{ count($sections) > 1 ? 6 : 12 }} mb-3">
				<h6 class="font-weight-700 mb-2">{{ $x['label'] }} por causal</h6>
				<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="{{ $x['label'] }} por causal">
					<thead><tr><th>Causal</th><th class="text-right">Costo</th><th class="text-right">% de la clase</th></tr></thead>
					<tbody>
						@forelse($x['causes'] as $code => $v)
						<tr><td><span class="dlx-chip">{{ $code }}</span></td><td class="text-right">{{ $money($v) }}</td><td class="text-right">{{ $x['cost'] != 0 ? number_format($v / $x['cost'] * 100, 1) . '%' : '—' }}</td></tr>
						@empty
						<tr><td colspan="3" class="text-muted">Sin eventos.</td></tr>
						@endforelse
					</tbody>
				</table></div>
				<h6 class="font-weight-700 mb-2 mt-2">SKU con mayor impacto</h6>
				<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="{{ $x['label'] }} por SKU">
					<thead><tr><th>SKU</th><th>Producto</th><th class="dlx-hide-sm">Código de barras</th><th class="dlx-hide-sm">Depto.</th><th class="text-right">Unid.</th><th class="text-right">Costo</th><th></th></tr></thead>
					<tbody>
						@foreach(array_slice($x['skus'], 0, 25) as $r)
						<tr><td class="font-mono small">{{ $r['sku'] ?? '' }}</td><td>{{ $r['name'] ?? '#' . $r['product_id'] }}</td><td class="dlx-hide-sm small">{{ $r['barcode'] ?? '—' }}</td><td class="dlx-hide-sm small">{{ $r['department'] ?? '' }}</td>
							<td class="text-right">{{ number_format($r['units'], 2) }}</td><td class="text-right font-weight-600">{{ $money($r['cost']) }}</td>
							<td class="text-right"><a class="small" href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" title="Kardex del SKU">Kardex</a></td></tr>
						@endforeach
					</tbody>
				</table></div>
			</div>
		@endforeach
	</div>
	<h6 class="font-weight-700 mb-2">Movimientos de origen (últimos)</h6>
	<div class="table-responsive"><table class="table table-sm dlx-table" data-export-title="Movimientos de origen">
		<thead><tr><th>ID evento</th><th>Fecha</th><th>Clase</th><th>Causal</th><th>Producto</th><th class="text-right">Unid.</th><th class="text-right">Costo</th><th>Documento</th></tr></thead>
		<tbody>
			@foreach($sections as $c)
				@foreach(array_slice($cls['classes'][$c]['recent'], 0, 15) as $e)
				<tr><td class="font-mono small">{{ $e['uid'] }}</td><td>{{ $e['d'] }}</td><td>{{ \Grocy\Services\LossIntelligenceService::CLASSES[$e['loss_class']] }}</td><td><span class="dlx-chip">{{ $e['cause'] }}</span></td>
					<td>{{ $names[$e['product_id']]['name'] ?? '#' . $e['product_id'] }}</td><td class="text-right">{{ number_format($e['units'], 2) }}</td><td class="text-right">{{ $money($e['cost']) }}</td>
					<td class="small">@if($e['src'] === 'waste')<a href="{{ $U('/document/waste/' . $e['ref']) }}">{{ $e['doc'] }}</a>@elseif($e['src'] === 'adjustment')<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}">Ajuste de stock</a>@elseif($e['src'] === 'inventory_adjustment')<a href="{{ $U('/document/adjustment/' . $e['ref']) }}">{{ $e['doc'] }}</a>@else{{ $e['doc'] }}@endif</td></tr>
				@endforeach
			@endforeach
		</tbody>
	</table></div>
	@endif
</div>
@endif
