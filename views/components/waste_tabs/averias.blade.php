@php
	$money = $money ?? fn($v) => '$' . number_format(floatval($v), 2);
	$pct = $pct ?? fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
	$causeCatalog = $causeCatalog ?? ($cls['cause_names'] ?? []);
	$names = $names ?? ($cls['names'] ?? []);

	// Helper para fotografía de productos con degradado y reemplazo visual seguro
	$productPhoto = $productPhoto ?? function($item) use ($U) {
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
		if ($sku === 'DAIRY-YOG-002' || str_contains($n, 'yogur') || str_contains($n, 'griego')) {
			return $U('/img/control_center/yogur_griego.jpg');
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
		if (preg_match('/\b(leche|crema|lacteo)\b/u', $n)) {
			return $U('/img/control_center/leche_descremada.jpg');
		}
		return $U('/img/control_center/operativa_banner.jpg');
	};

	$avData = $cls['classes']['averia'] ?? ['cost' => 0, 'pct_sales' => 0, 'share' => 0, 'events' => 0, 'skus' => [], 'causes' => [], 'recent' => []];
	$avSumm = $summary['averia'] ?? ['value' => 0, 'pct_sales' => 0, 'share' => 0];

	$netSales = floatval($cls['net_sales'] ?? 0);
	$totalCost = floatval($avSumm['value'] ?? $avData['cost'] ?? 0);
	$pctSales = floatval($avSumm['pct_sales'] ?? $avData['pct_sales'] ?? 0);
	$shareVal = floatval($avSumm['share'] ?? $avData['share'] ?? 0);

	$skuList = $avData['skus'] ?? [];
	$recentList = $avData['recent'] ?? [];
	$causesList = $avData['causes'] ?? [];

	$eventsCount = intval($avData['events'] ?? count($recentList));
	$skuCount = count($skuList);

	// Ranking de causales
	$maxCauseCost = max(0.01, ...array_values($causesList) ?: [1]);

	// Top 5 productos por impacto económico
	$top5Skus = array_slice($skuList, 0, 5);
	$maxSkuCost = max(0.01, ...array_column($top5Skus, 'cost') ?: [1]);

	// Consultar incidentes de avería para auditoría y trazabilidad
	$damageDetails = [];
	try {
		$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $db->query("SELECT di.*, p.name AS product_name, p.sku, u1.username AS reporter_name 
			FROM damage_incidents di 
			LEFT JOIN products p ON p.id = di.product_id 
			LEFT JOIN users u1 ON u1.id = di.reported_by_user_id");
		if ($stmt) {
			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$damageDetails[$row['id']] = $row;
			}
		}
	} catch (\Throwable $t) {}
@endphp

<style>
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyAvTabContainer) .dairy-related { display: none !important; }
.daily-av-section { display: flex; flex-direction: column; gap: 18px; }
.daily-av-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.02); position: relative; transition: box-shadow 0.2s ease, border-color 0.2s ease; }
.daily-av-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.daily-av-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.daily-av-card-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; letter-spacing: -0.01em; }

/* Grid de KPIs */
.daily-av-kpis-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.daily-av-kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.18s ease, box-shadow 0.18s ease; }
.daily-av-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.daily-av-kpi-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.daily-av-kpi-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; }
.daily-av-kpi-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
.daily-av-kpi-icon.tone-red { background: #fee2e2; color: #ef4444; }
.daily-av-kpi-icon.tone-green { background: #dcfce7; color: #16a34a; }
.daily-av-kpi-icon.tone-purple { background: #f3e8ff; color: #8b5cf6; }
.daily-av-kpi-val { font-size: 1.6rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 4px; }
.daily-av-kpi-sub { font-size: 0.74rem; color: #64748b; font-weight: 500; }

/* Grid de Gráficos Comparativos */
.daily-av-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Barras de Causales */
.daily-av-causes-list { display: flex; flex-direction: column; gap: 12px; padding-top: 6px; }
.daily-av-cause-row { display: grid; grid-template-columns: 60px 1fr 90px; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px; border: 1px solid transparent; transition: background 0.15s ease, border-color 0.15s ease; cursor: pointer; }
.daily-av-cause-row:hover { background: #f8fafc; border-color: #e2e8f0; }
.daily-av-cause-row.is-active { background: #fefce8; border-color: #fde047; }
.daily-av-cause-code { font-size: 0.85rem; font-weight: 800; color: #0f172a; font-family: monospace; }
.daily-av-bar-wrap { position: relative; width: 100%; display: flex; flex-direction: column; gap: 4px; }
.daily-av-bar-track { height: 18px; background: #f1f5f9; border-radius: 6px; overflow: hidden; position: relative; width: 100%; }
.daily-av-bar-fill { height: 100%; border-radius: 6px; transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.daily-av-bar-fill.tone-amber { background: #f59e0b; }
.daily-av-bar-fill.tone-red { background: #ef4444; }
.daily-av-bar-fill.tone-salmon-1 { background: #f87171; }
.daily-av-bar-fill.tone-salmon-2 { background: #fca5a5; }
.daily-av-bar-fill.tone-salmon-3 { background: #fecaca; }
.daily-av-cause-stats { display: flex; flex-direction: column; align-items: flex-end; }
.daily-av-cause-money { font-size: 0.88rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
.daily-av-cause-pct { font-size: 0.74rem; color: #64748b; font-weight: 600; font-variant-numeric: tabular-nums; }

/* Ranking Top 5 Productos */
.daily-av-prod-ranking { display: flex; flex-direction: column; gap: 10px; padding-top: 4px; }
.daily-av-prod-row { display: grid; grid-template-columns: 160px 1fr 65px; align-items: center; gap: 12px; padding: 6px 10px; border-radius: 8px; transition: background 0.15s ease; cursor: pointer; }
.daily-av-prod-row:hover { background: #f8fafc; }
.daily-av-prod-name { font-size: 0.78rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.daily-av-prod-money { font-size: 0.84rem; font-weight: 800; color: #0f172a; text-align: right; font-variant-numeric: tabular-nums; }

/* Grid de Productos con mayor impacto */
.daily-av-sku-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
.daily-av-sku-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; cursor: pointer; display: flex; flex-direction: column; text-decoration: none !important; color: inherit !important; position: relative; }
.daily-av-sku-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.06); border-color: #cbd5e1; }
.daily-av-sku-img-wrap { height: 110px; max-height: 110px; width: 100%; position: relative; background: #f8fafc; overflow: hidden; }
.daily-av-sku-img { width: 100% !important; height: 110px !important; max-height: 110px !important; object-fit: cover !important; transition: transform 0.25s ease; }
.daily-av-sku-card:hover .daily-av-sku-img { transform: scale(1.04); }
.daily-av-sku-mask { position: absolute; bottom: 0; left: 0; right: 0; height: 40px; background: linear-gradient(to top, #ffffff 0%, rgba(255,255,255,0.85) 50%, rgba(255,255,255,0) 100%); pointer-events: none; }
.daily-av-sku-body { padding: 8px 12px 12px 12px; display: flex; flex-direction: column; flex: 1; justify-content: space-between; gap: 4px; }
.daily-av-sku-name { font-size: 0.84rem; font-weight: 700; color: #0f172a; line-height: 1.25; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.daily-av-sku-code { font-size: 0.68rem; color: #64748b; font-family: monospace; }
.daily-av-sku-units { font-size: 0.74rem; color: #475569; font-weight: 600; margin-top: 2px; }
.daily-av-sku-cost { font-size: 1.05rem; font-weight: 800; color: #dc2626; line-height: 1.1; }
.daily-av-sku-dept { font-size: 0.68rem; color: #94a3b8; margin-top: 1px; }
.daily-av-sku-action { font-size: 0.72rem; font-weight: 700; color: #16a34a; display: flex; align-items: center; gap: 4px; margin-top: 4px; }

/* Tablas compactas */
.daily-av-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem; }
.daily-av-table th { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; padding: 9px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.daily-av-table th:first-child { border-top-left-radius: 8px; }
.daily-av-table th:last-child { border-top-right-radius: 8px; }
.daily-av-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.daily-av-table tr:hover td { background: #f8fafc; }

/* Chips de Causal */
.daily-av-chip { display: inline-flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 800; font-family: monospace; padding: 2px 7px; border-radius: 6px; }
.daily-av-chip.is-ave { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.daily-av-chip.is-dex { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

/* Nota informativa inferior */
.daily-av-info-note { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 10px; padding: 10px 16px; font-size: 0.76rem; color: #1e40af; display: flex; align-items: center; gap: 8px; }

/* Paginación */
.daily-av-pagination-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.daily-av-page-btns { display: flex; align-items: center; gap: 4px; }
.daily-av-page-btn { min-width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 0.72rem; font-weight: 600; color: #475569; background: #ffffff; border: 1px solid #e2e8f0; cursor: pointer; text-decoration: none !important; transition: all 0.15s ease; padding: 0 6px; }
.daily-av-page-btn:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
.daily-av-page-btn.is-active { background: #0f172a; color: #ffffff; border-color: #0f172a; }
.daily-av-page-btn.disabled { opacity: 0.4; pointer-events: none; }

/* Tooltip nativo personalizado */
[data-daily-tooltip] { position: relative; }
[data-daily-tooltip]:hover::after {
	content: attr(data-daily-tooltip);
	position: absolute;
	bottom: 100%;
	left: 50%;
	transform: translateX(-50%);
	margin-bottom: 6px;
	background: #0f172a;
	color: #ffffff;
	font-size: 0.7rem;
	padding: 4px 8px;
	border-radius: 6px;
	white-space: nowrap;
	z-index: 100;
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
	pointer-events: none;
}

@media (max-width: 1199px) {
	.daily-av-kpis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.daily-av-charts-grid { grid-template-columns: 1fr; }
	.daily-av-sku-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}
@media (max-width: 767px) {
	.daily-av-kpis-grid { grid-template-columns: 1fr; }
	.daily-oc-filters-form { flex-direction: column; align-items: stretch; }
	.daily-oc-filter-group { width: 100% !important; flex: 1 1 100% !important; }
	.daily-av-prod-row { grid-template-columns: 120px 1fr 55px; }
}
</style>

<div class="daily-av-section" id="dailyAvTabContainer">

	<!-- A. ENCABEZADO Y FILTROS -->
	<div class="daily-oc-header">
		<div class="daily-oc-title-block">
			<div class="d-flex align-items-center">
				<div class="mr-2.5" style="width: 32px; height: 32px; border-radius: 8px; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
					<i class="fa-solid fa-heart-crack"></i>
				</div>
				<h5 class="mb-0">Averías y roturas</h5>
			</div>
			<p class="mt-1">
				Producto dañado o roto · Incluye registros sin merma; los vinculados se contabilizan una sola vez.
			</p>
		</div>

		<!-- Exportar -->
		<div class="d-flex align-items-center">
			@include('components.dairy_export', ['exportScope' => '#tab-averias', 'exportName' => 'averias_roturas'])
		</div>
	</div>

	<!-- FILTROS SINCRONIZADOS -->
	<div class="daily-oc-filters">
		<form class="daily-oc-filters-form" id="dailyAvFilterForm" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros de averías y roturas">
			<div class="daily-oc-filter-group" style="flex: 0 1 135px; min-width: 120px;">
				<label class="daily-oc-filter-label" for="av-f-from">Desde</label>
				<input type="date" name="start_date" id="av-f-from" class="form-control form-control-sm border font-weight-600 text-dark" value="{{ $startDate }}" style="border-radius: 6px;">
			</div>

			<div class="daily-oc-filter-group" style="flex: 0 1 135px; min-width: 120px;">
				<label class="daily-oc-filter-label" for="av-f-to">Hasta</label>
				<input type="date" name="end_date" id="av-f-to" class="form-control form-control-sm border font-weight-600 text-dark" value="{{ $endDate }}" style="border-radius: 6px;">
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="av-f-branch">Sucursal</label>
				<select name="branch_id" id="av-f-branch" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todas</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="av-f-dept">Departamento</label>
				<select name="group_id" id="av-f-dept" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="av-f-supp">Proveedor</label>
				<select name="supplier_id" id="av-f-supp" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="av-f-sku">SKU</label>
				<select name="product_id" id="av-f-sku" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['products'] as $p)
						<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="d-flex align-items-end gap-1" style="flex: 0 0 auto;">
				<button type="submit" class="btn btn-sm btn-dark font-weight-600 shadow-xs px-3" style="border-radius: 6px; height: 31px;">
					<i class="fa-solid fa-check mr-1"></i>Aplicar
				</button>
				<a href="{{ $U('/waste#tab-averias') }}" class="btn btn-sm btn-light border text-muted px-2.5" style="border-radius: 6px; height: 31px;" title="Limpiar filtros">
					<i class="fa-solid fa-rotate-left"></i>
				</a>
			</div>
		</form>
	</div>

	<!-- B. INDICADORES PRINCIPALES (4 TARJETAS) -->
	<div class="daily-av-kpis-grid">
		<!-- 1. Impacto registrado -->
		<div class="daily-av-kpi-card">
			<div class="daily-av-kpi-head">
				<span class="daily-av-kpi-lbl">Impacto registrado</span>
				<div class="daily-av-kpi-icon tone-red">
					<i class="fa-solid fa-tag"></i>
				</div>
			</div>
			<div>
				<div class="daily-av-kpi-val text-dark">{{ $money($totalCost) }}</div>
				<div class="daily-av-kpi-sub">{{ number_format($pctSales, 2) }}% de ventas · {{ number_format($shareVal, 2) }}% del total</div>
			</div>
		</div>

		<!-- 2. Eventos registrados -->
		<div class="daily-av-kpi-card">
			<div class="daily-av-kpi-head">
				<span class="daily-av-kpi-lbl">Eventos registrados</span>
				<div class="daily-av-kpi-icon tone-green">
					<i class="fa-solid fa-file-invoice"></i>
				</div>
			</div>
			<div>
				<div class="daily-av-kpi-val text-dark">{{ $eventsCount }}</div>
				<div class="daily-av-kpi-sub">Registros de averías y roturas</div>
			</div>
		</div>

		<!-- 3. SKU afectados -->
		<div class="daily-av-kpi-card">
			<div class="daily-av-kpi-head">
				<span class="daily-av-kpi-lbl">SKU afectados</span>
				<div class="daily-av-kpi-icon tone-purple">
					<i class="fa-solid fa-box-open"></i>
				</div>
			</div>
			<div>
				<div class="daily-av-kpi-val text-dark">{{ $skuCount }}</div>
				<div class="daily-av-kpi-sub">Productos con eventos</div>
			</div>
		</div>

		<!-- 4. Ventas netas -->
		<div class="daily-av-kpi-card">
			<div class="daily-av-kpi-head">
				<span class="daily-av-kpi-lbl">Ventas netas</span>
				<div class="daily-av-kpi-icon tone-green">
					<i class="fa-solid fa-chart-column"></i>
				</div>
			</div>
			<div>
				<div class="daily-av-kpi-val text-dark">{{ $money($netSales) }}</div>
				<div class="daily-av-kpi-sub">base de los %</div>
			</div>
		</div>
	</div>

	<!-- C. DISTRIBUCIÓN POR CAUSAL Y RANKING POR PRODUCTO -->
	<div class="daily-av-charts-grid">
		<!-- C.1 Averías por causal -->
		<div class="daily-av-card">
			<div class="daily-av-card-header">
				<h6 class="daily-av-card-title">Averías por causal</h6>
				<div id="badgeActiveCauseFilter" class="badge badge-warning font-weight-600 px-2.5 py-1" style="display: none; cursor: pointer;" onclick="dailyFilterByCause('')">
					<span id="labelActiveCauseFilter"></span> <i class="fa-solid fa-xmark ml-1"></i>
				</div>
			</div>

			<div class="daily-av-causes-list">
				@forelse($causesList as $code => $cCost)
					@php
						$cPct = $totalCost > 0 ? ($cCost / $totalCost * 100) : 0;
						$barWidth = $totalCost > 0 ? max(4, round($cCost / $totalCost * 100, 1)) : 0;
						$cName = $causeCatalog[$code] ?? $code;
						$toneClass = ($code === 'AVE') ? 'tone-amber' : 'tone-red';
					@endphp
					<div class="daily-av-cause-row" data-cause="{{ $code }}" onclick="dailyFilterByCause('{{ $code }}')" data-daily-tooltip="{{ $cName }}: {{ $money($cCost) }} ({{ number_format($cPct, 1) }}%)">
						<div class="daily-av-cause-code">{{ $code }}</div>
						<div class="daily-av-bar-wrap">
							<div class="daily-av-bar-track">
								<div class="daily-av-bar-fill {{ $toneClass }}" style="width: {{ $barWidth }}%;"></div>
							</div>
						</div>
						<div class="daily-av-cause-stats">
							<span class="daily-av-cause-money">{{ $money($cCost) }}</span>
							<span class="daily-av-cause-pct">{{ number_format($cPct, 1) }}%</span>
						</div>
					</div>
				@empty
					<div class="text-muted small py-4 text-center">Sin causales registradas en el período.</div>
				@endforelse
			</div>
		</div>

		<!-- C.2 Costo por producto (Top 5) -->
		<div class="daily-av-card">
			<div class="daily-av-card-header">
				<h6 class="daily-av-card-title">
					<i class="fa-solid fa-chart-column text-success mr-2"></i>Costo por producto (Top 5)
				</h6>
			</div>

			<div class="daily-av-prod-ranking">
				@php
					$colorTones = ['tone-amber', 'tone-red', 'tone-salmon-1', 'tone-salmon-2', 'tone-salmon-3'];
				@endphp
				@forelse($top5Skus as $idx => $r)
					@php
						$barW = $maxSkuCost > 0 ? max(4, round(($r['cost'] / $maxSkuCost) * 100, 1)) : 0;
						$tone = $colorTones[$idx] ?? 'tone-salmon-3';
						$tooltipText = ($r['name'] ?? '') . ' · ' . number_format($r['units'], 2) . ' ' . ($r['qu_name'] ?? 'unid.') . ' · ' . $money($r['cost']);
					@endphp
					<div class="daily-av-prod-row" onclick="dailyScrollToSku('{{ $r['sku'] }}')" data-daily-tooltip="{{ $tooltipText }}">
						<div class="daily-av-prod-name" title="{{ $r['name'] ?? '' }}">{{ $r['name'] ?? '' }}</div>
						<div class="daily-av-bar-wrap">
							<div class="daily-av-bar-track" style="height: 14px;">
								<div class="daily-av-bar-fill {{ $tone }}" style="width: {{ $barW }}%;"></div>
							</div>
						</div>
						<div class="daily-av-prod-money">{{ $money($r['cost']) }}</div>
					</div>
				@empty
					<div class="text-muted small py-4 text-center">Sin productos afectados en el período.</div>
				@endforelse
			</div>
		</div>
	</div>

	<!-- D. PRODUCTOS CON MAYOR IMPACTO (TARJETAS CON FOTOGRAFÍA) -->
	<div class="daily-av-card">
		<div class="daily-av-card-header">
			<h6 class="daily-av-card-title">
				<i class="fa-solid fa-cart-shopping text-success mr-2"></i>Productos con mayor impacto
			</h6>
			<span class="text-muted small">Top de SKU por costo acumulado de averías y roturas</span>
		</div>

		<div class="daily-av-sku-grid" id="dailyAvSkuGrid">
			@forelse($top5Skus as $r)
				@php
					$photo = $productPhoto($r);
					$prodCauses = implode(',', array_keys($r['causes'] ?? []));
				@endphp
				<div class="daily-av-sku-card" data-sku="{{ $r['sku'] }}" data-causes="{{ $prodCauses }}" onclick="dailyScrollToSku('{{ $r['sku'] }}')">
					<div class="daily-av-sku-img-wrap">
						<img src="{{ $photo }}" alt="{{ $r['name'] }}" class="daily-av-sku-img" loading="lazy" onerror="this.src='{{ $U('/img/control_center/operativa_banner.jpg') }}';">
						<div class="daily-av-sku-mask"></div>
					</div>
					<div class="daily-av-sku-body">
						<div>
							<div class="daily-av-sku-name" title="{{ $r['name'] }}">{{ $r['name'] }}</div>
							<div class="daily-av-sku-code">{{ $r['sku'] }}</div>
							<div class="daily-av-sku-units">{{ intval($r['units']) == $r['units'] ? intval($r['units']) : number_format($r['units'], 2) }} unid.</div>
						</div>
						<div>
							<div class="daily-av-sku-cost">{{ $money($r['cost']) }}</div>
							<div class="daily-av-sku-dept">{{ $r['department'] ?? '—' }}</div>
							<div class="daily-av-sku-action">
								Ver detalle <i class="fa-solid fa-arrow-right ml-0.5" style="font-size: 0.65rem;"></i>
							</div>
						</div>
					</div>
				</div>
			@empty
				<div class="text-muted small py-4 text-center col-12">No hay productos con averías registradas.</div>
			@endforelse
		</div>
	</div>

	<!-- E. DETALLE COMPLETO POR SKU -->
	<div class="daily-av-card" id="sectionDetalleSku">
		<div class="daily-av-card-header">
			<h6 class="daily-av-card-title">
				<i class="fa-solid fa-boxes-stacked text-success mr-2"></i>Detalle por SKU
			</h6>
			<div class="d-flex align-items-center gap-2">
				<button class="btn btn-xs btn-outline-secondary font-weight-600 px-2 py-0.5" style="font-size: 0.72rem; border-radius: 5px;" onclick="dailyToggleAllRows('tableAvSku', this)">
					Ver todos
				</button>
			</div>
		</div>

		<div class="table-responsive">
			<table class="daily-av-table" id="tableAvSku" data-page-size="5" data-export-title="Averías y roturas por SKU">
				<thead>
					<tr>
						<th>SKU</th>
						<th>Producto</th>
						<th>Código de barras</th>
						<th>Depto.</th>
						<th class="text-right">Unid.</th>
						<th class="text-right">Costo</th>
						<th class="text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="tbodyAvSku">
					@forelse($skuList as $r)
						@php
							$pCauses = implode(',', array_keys($r['causes'] ?? []));
						@endphp
						<tr class="daily-av-sku-row" data-sku="{{ $r['sku'] }}" data-causes="{{ $pCauses }}" data-product="{{ mb_strtolower($r['name']) }}">
							<td class="font-mono font-weight-600 text-dark" style="font-size: 0.76rem;">{{ $r['sku'] }}</td>
							<td class="font-weight-600 text-dark">{{ $r['name'] }}</td>
							<td class="font-mono text-muted" style="font-size: 0.74rem;">{{ $r['barcode'] ?: 'No disponible' }}</td>
							<td class="text-muted">{{ $r['department'] ?? '—' }}</td>
							<td class="text-right font-weight-600">{{ number_format($r['units'], 2) }}</td>
							<td class="text-right font-weight-700 text-dark">{{ $money($r['cost']) }}</td>
							<td class="text-right">
								<a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" class="btn btn-xs btn-link font-weight-600 p-0" title="Ver Kardex del SKU">
									Kardex
								</a>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="7" class="text-center text-muted py-4">Sin registros por SKU.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="daily-av-pagination-wrap">
			<span class="text-muted small font-weight-500" id="counterAvSku">Mostrando 1–{{ min(5, $skuCount) }} de {{ $skuCount }} SKU</span>
			<div class="daily-av-page-btns" id="pagAvSku"></div>
		</div>
	</div>

	<!-- F. MOVIMIENTOS DE ORIGEN (ÚLTIMOS) -->
	<div class="daily-av-card" id="sectionMovimientos">
		<div class="daily-av-card-header">
			<h6 class="daily-av-card-title">
				<i class="fa-solid fa-file-lines text-success mr-2"></i>Movimientos de origen (últimos)
			</h6>

			<!-- Buscador y Filtro por Causal -->
			<div class="d-flex align-items-center flex-wrap gap-2">
				<div class="input-group input-group-sm" style="width: 250px;">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white border-right-0 text-muted" style="border-radius: 6px 0 0 6px;">
							<i class="fa-solid fa-magnifying-glass" style="font-size: 0.75rem;"></i>
						</span>
					</div>
					<input type="text" id="searchAvMovements" class="form-control form-control-sm border-left-0 font-weight-500" placeholder="Buscar por ID, producto o documento..." onkeyup="dailyFilterAvMovements()" style="border-radius: 0 6px 6px 0; font-size: 0.78rem;">
				</div>

				<div class="d-flex align-items-center gap-1.5">
					<label for="filterCauseAvMovements" class="text-muted small font-weight-600 mb-0">Causal:</label>
					<select id="filterCauseAvMovements" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px; width: 110px; font-size: 0.78rem;" onchange="dailyFilterAvMovements()">
						<option value="">Todas</option>
						@foreach(array_keys($causesList) as $cKey)
							<option value="{{ $cKey }}">{{ $cKey }}</option>
						@endforeach
					</select>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="daily-av-table" id="tableAvMovements" data-page-size="10" data-export-title="Movimientos de origen de averías">
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
						<th class="text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="tbodyAvMovements">
					@forelse($recentList as $m)
						@php
							$pInfo = $names[$m['product_id']] ?? [];
							$pName = $pInfo['name'] ?? ('#' . $m['product_id']);
							$pSku = $pInfo['sku'] ?? '';
							$chipClass = ($m['cause'] === 'AVE') ? 'is-ave' : 'is-dex';
						@endphp
						<tr class="daily-av-mov-row" data-uid="{{ $m['uid'] }}" data-cause="{{ $m['cause'] }}" data-product="{{ mb_strtolower($pName) }}" data-sku="{{ mb_strtolower($pSku) }}" data-doc="{{ mb_strtolower($m['doc']) }}">
							<td class="font-mono font-weight-700 text-dark" style="font-size: 0.78rem;">{{ $m['uid'] }}</td>
							<td class="text-muted text-nowrap" style="font-size: 0.76rem;">{{ $m['d'] }}</td>
							<td>Averías y roturas</td>
							<td>
								<span class="daily-av-chip {{ $chipClass }}" title="{{ $causeCatalog[$m['cause']] ?? $m['cause'] }}">{{ $m['cause'] }}</span>
							</td>
							<td class="font-weight-600 text-dark">{{ $pName }}</td>
							<td class="text-right font-weight-600">{{ number_format($m['units'], 2) }}</td>
							<td class="text-right font-weight-700 text-dark">{{ $money($m['cost']) }}</td>
							<td class="font-mono text-nowrap" style="font-size: 0.76rem;">
								@if($m['src'] === 'waste')
									<a href="{{ $U('/document/waste/' . $m['ref']) }}" class="font-weight-600 text-primary" title="Ver documento de merma">
										{{ $m['doc'] }}
									</a>
								@elseif($m['src'] === 'damage')
									<a href="javascript:void(0);" onclick="dailyShowDamageModal({{ $m['ref'] }})" class="font-weight-600 text-purple" style="color: #7c3aed;" title="Ver incidencia de avería">
										{{ $m['doc'] }}
									</a>
								@elseif($m['src'] === 'adjustment')
									<a href="{{ $U('/kardex?product_id=' . $m['product_id']) }}" class="font-weight-600 text-primary">
										Ajuste de stock
									</a>
								@elseif($m['src'] === 'inventory_adjustment')
									<a href="{{ $U('/document/adjustment/' . $m['ref']) }}" class="font-weight-600 text-primary">
										{{ $m['doc'] }}
									</a>
								@else
									<span class="text-dark">{{ $m['doc'] }}</span>
								@endif
							</td>
							<td class="text-right">
								<a href="{{ $U('/kardex?product_id=' . $m['product_id']) }}" class="btn btn-xs btn-link font-weight-600 p-0" title="Kardex del SKU">
									Kardex
								</a>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="9" class="text-center text-muted py-4">Sin movimientos recientes de averías.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="daily-av-pagination-wrap">
			<span class="text-muted small font-weight-500" id="counterAvMovements">Mostrando 1–{{ min(10, count($recentList)) }} de {{ count($recentList) }} movimientos</span>
			<div class="daily-av-page-btns" id="pagAvMovements"></div>
		</div>

		<!-- Nota informativa anti doble conteo -->
		<div class="daily-av-info-note mt-3">
			<i class="fa-solid fa-circle-info mr-1 text-primary" style="font-size: 0.95rem;"></i>
			<span>Los registros vinculados a una merma se contabilizan una sola vez. Incluye registros sin merma. No se incluyen ajustes.</span>
		</div>
	</div>

</div>

<!-- MODAL AUDITORÍA DE AVERÍA / DAMAGE INCIDENT -->
<div class="modal fade" id="modalDamageIncident" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content" style="border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
			<div class="modal-header py-3 px-4" style="border-bottom: 1px solid #f1f5f9; background: #faf5ff;">
				<div class="d-flex align-items-center">
					<div class="mr-2.5" style="width: 28px; height: 28px; border-radius: 6px; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
						<i class="fa-solid fa-heart-crack"></i>
					</div>
					<div>
						<h6 class="modal-title font-weight-800 text-dark mb-0" id="modalDmgTitle">Incidencia de avería</h6>
						<span class="text-muted small" id="modalDmgSubtitle">Trazabilidad y registro de origen</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" id="modalDmgBody">
				<!-- Contenido dinámico inyectado por JS -->
			</div>
			<div class="modal-footer py-2 px-4" style="border-top: 1px solid #f1f5f9;">
				<button type="button" class="btn btn-sm btn-dark px-3 font-weight-600" data-dismiss="modal" style="border-radius: 6px;">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	// Diccionario de incidencias para visualización modal
	var damageData = @json($damageDetails);

	window.dailyShowDamageModal = function(id) {
		var item = damageData[id];
		if (!item) {
			alert('Detalles de la incidencia no disponibles.');
			return;
		}

		var statusLabel = {
			'closed': '<span class="badge badge-secondary">Cerrado (Sin merma generada)</span>',
			'discarded_to_waste': '<span class="badge badge-info">Merma vinculada</span>',
			'in_inspection': '<span class="badge badge-warning">En inspección</span>'
		}[item.status] || ('<span class="badge badge-light">' + item.status + '</span>');

		var html = '<div class="d-flex flex-column gap-2" style="font-size: 0.84rem;">' +
			'<div class="row mb-2">' +
				'<div class="col-6"><span class="text-muted small d-block">Número de documento</span><strong class="font-mono text-dark">' + (item.incident_number || 'AVR-' + item.id) + '</strong></div>' +
				'<div class="col-6"><span class="text-muted small d-block">Estado</span>' + statusLabel + '</div>' +
			'</div>' +
			'<div class="row mb-2">' +
				'<div class="col-6"><span class="text-muted small d-block">Producto</span><strong class="text-dark">' + (item.product_name || 'SKU ' + item.sku) + '</strong></div>' +
				'<div class="col-6"><span class="text-muted small d-block">Lote</span><span class="font-mono">' + (item.batch_number || 'No especificado') + '</span></div>' +
			'</div>' +
			'<div class="row mb-2">' +
				'<div class="col-4"><span class="text-muted small d-block">Unid. afectadas</span><strong>' + (item.units_affected || 0) + '</strong></div>' +
				'<div class="col-4"><span class="text-muted small d-block">Unid. recuperadas</span><strong class="text-success">' + (item.units_recovered || 0) + '</strong></div>' +
				'<div class="col-4"><span class="text-muted small d-block">Unid. descartadas</span><strong class="text-danger">' + (item.units_discarded || 0) + '</strong></div>' +
			'</div>' +
			'<div class="row mb-2">' +
				'<div class="col-6"><span class="text-muted small d-block">Pérdida por descarte</span><strong class="text-danger font-weight-800">$' + parseFloat(item.discard_loss_cost || 0).toFixed(2) + '</strong></div>' +
				'<div class="col-6"><span class="text-muted small d-block">Valor recuperado</span><strong class="text-success font-weight-800">$' + parseFloat(item.recovered_value || 0).toFixed(2) + '</strong></div>' +
			'</div>' +
			'<div class="mb-2"><span class="text-muted small d-block">Tipo de daño / Origen</span><span>' + (item.damage_type || '—') + ' · Origen ' + (item.origin || 'operativo') + '</span></div>' +
			(item.action_plan ? ('<div class="mb-2 p-2 rounded bg-light border"><span class="text-muted small d-block font-weight-700">Plan de acción:</span><span class="text-dark">' + item.action_plan + '</span></div>') : '') +
			(item.notes ? ('<div class="p-2 rounded bg-light border"><span class="text-muted small d-block font-weight-700">Notas de inspección:</span><span class="text-dark">' + item.notes + '</span></div>') : '') +
		'</div>';

		document.getElementById('modalDmgBody').innerHTML = html;
		$('#modalDamageIncident').modal('show');
	};

	// 1. Filtrado interactivo por Causal (AVE, DEX)
	var activeCause = '';
	window.dailyFilterByCause = function(code) {
		if (activeCause === code || !code) {
			activeCause = '';
		} else {
			activeCause = code;
		}

		// Actualizar filas activas en el gráfico
		document.querySelectorAll('.daily-av-cause-row').forEach(function(row) {
			if (activeCause && row.getAttribute('data-cause') === activeCause) {
				row.classList.add('is-active');
			} else {
				row.classList.remove('is-active');
			}
		});

		// Actualizar badge de causal activa
		var badge = document.getElementById('badgeActiveCauseFilter');
		var lbl = document.getElementById('labelActiveCauseFilter');
		if (activeCause) {
			badge.style.display = 'inline-block';
			lbl.innerText = 'Causal activa: ' + activeCause;
		} else {
			badge.style.display = 'none';
		}

		// Sincronizar select en Movimientos
		var selMov = document.getElementById('filterCauseAvMovements');
		if (selMov) {
			selMov.value = activeCause;
		}

		// Filtrar tarjetas de SKU
		document.querySelectorAll('#dailyAvSkuGrid .daily-av-sku-card').forEach(function(card) {
			var cList = (card.getAttribute('data-causes') || '').split(',');
			if (!activeCause || cList.indexOf(activeCause) !== -1) {
				card.style.display = '';
			} else {
				card.style.display = 'none';
			}
		});

		// Filtrar tabla Detalle SKU
		document.querySelectorAll('#tbodyAvSku .daily-av-sku-row').forEach(function(row) {
			var cList = (row.getAttribute('data-causes') || '').split(',');
			if (!activeCause || cList.indexOf(activeCause) !== -1) {
				row.classList.remove('d-none-cause');
			} else {
				row.classList.add('d-none-cause');
				row.style.display = 'none';
			}
		});
		initTablePagination('tableAvSku', 'pagAvSku', 'counterAvSku', 'SKU');

		// Filtrar tabla Movimientos
		dailyFilterAvMovements();
	};

	// 2. Desplazar hacia un SKU
	window.dailyScrollToSku = function(sku) {
		var target = document.getElementById('sectionDetalleSku');
		if (target) {
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		// Resaltar fila temporalmente
		document.querySelectorAll('#tbodyAvSku tr').forEach(function(row) {
			if (row.getAttribute('data-sku') === sku) {
				row.style.backgroundColor = '#fefce8';
				setTimeout(function() { row.style.backgroundColor = ''; }, 2000);
			}
		});
	};

	// 3. Filtrar Movimientos (Búsqueda + Causal)
	window.dailyFilterAvMovements = function() {
		var q = (document.getElementById('searchAvMovements').value || '').toLowerCase().trim();
		var cause = (document.getElementById('filterCauseAvMovements').value || '').trim();
		var rows = document.querySelectorAll('#tbodyAvMovements .daily-av-mov-row');

		rows.forEach(function(row) {
			var rCause = row.getAttribute('data-cause') || '';
			var rProd = row.getAttribute('data-product') || '';
			var rSku = row.getAttribute('data-sku') || '';
			var rDoc = row.getAttribute('data-doc') || '';
			var rUid = (row.getAttribute('data-uid') || '').toLowerCase();

			var matchCause = !cause || (rCause === cause);
			var matchQ = !q || (rProd.indexOf(q) !== -1 || rSku.indexOf(q) !== -1 || rDoc.indexOf(q) !== -1 || rUid.indexOf(q) !== -1);

			if (matchCause && matchQ) {
				row.classList.remove('d-none-filtered');
			} else {
				row.classList.add('d-none-filtered');
				row.style.display = 'none';
			}
		});

		initTablePagination('tableAvMovements', 'pagAvMovements', 'counterAvMovements', 'movimientos');
	};

	// 4. Paginación de tablas
	function initTablePagination(tableId, pagId, counterId, itemLabel) {
		var table = document.getElementById(tableId);
		if (!table) return;
		var pageSize = parseInt(table.getAttribute('data-page-size') || 5);
		var rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => !r.classList.contains('d-none-filtered') && !r.classList.contains('d-none-cause'));
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

				var prev = document.createElement('a');
				prev.className = 'daily-av-page-btn' + (curPage === 1 ? ' disabled' : '');
				prev.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size: 0.65rem;"></i>';
				prev.onclick = function() { if (curPage > 1) render(curPage - 1); };
				pagEl.appendChild(prev);

				for (var p = 1; p <= totalPages; p++) {
					(function(pageNum) {
						var btn = document.createElement('a');
						btn.className = 'daily-av-page-btn' + (pageNum === curPage ? ' is-active' : '');
						btn.innerText = pageNum;
						btn.onclick = function() { render(pageNum); };
						pagEl.appendChild(btn);
					})(p);
				}

				var next = document.createElement('a');
				next.className = 'daily-av-page-btn' + (curPage === totalPages ? ' disabled' : '');
				next.innerHTML = '<i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>';
				next.onclick = function() { if (curPage < totalPages) render(curPage + 1); };
				pagEl.appendChild(next);
			}
		}

		render(1);
		table.__dailyRender = render;
	}

	window.dailyToggleAllRows = window.dailyToggleAllRows || function(tableId, btn) {
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

	initTablePagination('tableAvSku', 'pagAvSku', 'counterAvSku', 'SKU');
	initTablePagination('tableAvMovements', 'pagAvMovements', 'counterAvMovements', 'movimientos');
})();
</script>
