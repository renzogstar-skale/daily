@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$m = $cmp['metric'] ?? 'cost';
	$fmtVal = function($v) use ($m, $money) {
		if ($v === null || $v === '') return '—';
		if ($m === 'pct') return number_format(floatval($v), 2) . '%';
		if ($m === 'units') return number_format(floatval($v), 2) . ' u.';
		return $money($v);
	};

	$c = $cmp['comparison'] ?? [];
	$availableYears = $cmp['available_years'] ?? [2025, 2026];
	$yearsData = $cmp['years'] ?? [];
	$labels = $cmp['labels'] ?? ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	$monthsFull = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

	// Identificar años seleccionados
	$selYears = array_column($yearsData, 'year');
	$baseYear = count($selYears) >= 2 ? $selYears[0] : (count($selYears) == 1 ? $selYears[0] - 1 : 2025);
	$currYear = count($selYears) >= 2 ? $selYears[1] : (count($selYears) == 1 ? $selYears[0] : 2026);

	$baseData = null;
	$currData = null;
	foreach ($yearsData as $yd) {
		if ($yd['year'] == $baseYear) $baseData = $yd;
		if ($yd['year'] == $currYear) $currData = $yd;
	}
	if (!$currData && !empty($yearsData)) {
		$currData = end($yearsData);
		$currYear = $currData['year'];
	}

	$baseHasData = !empty($baseData['events']) && $baseData['events'] > 0;
	$currHasData = !empty($currData['events']) && $currData['events'] > 0;

	// Mes de corte efectivo (Octubre / index 9 en datos 2026)
	$lastValidIdx = 0;
	if ($currData && !empty($currData['monthly'])) {
		foreach ($currData['monthly'] as $idx => $val) {
			if ($val !== null) {
				$lastValidIdx = max($lastValidIdx, $idx);
			}
		}
	} else {
		$lastValidIdx = 9; // Por defecto Octubre
	}

	$cutOffMonthLabel = $labels[$lastValidIdx] ?? 'Oct';
	$cutOffMonthFullName = $monthsFull[$lastValidIdx] ?? 'Octubre';

	// Indicadores
	$currTotal = floatval($currData['total_cost'] ?? 0);
	$topMonths = $currData['top_months'] ?? [];
	$highestMonth = !empty($topMonths) ? $topMonths[0] : ['month' => $cutOffMonthLabel, 'cost' => null];

	// Totales por clase en 2026
	$classTotals = $c['class_diff'] ?? [
		'operativa' => null,
		'comercial' => null,
		'averia' => null,
		'ajuste' => null,
		'robo_interno' => 0,
		'robo_externo' => 0,
	];
	$sumClasses = array_sum(array_filter([
		$classTotals['operativa'] ?? 0,
		$classTotals['comercial'] ?? 0,
		$classTotals['averia'] ?? 0,
		$classTotals['ajuste'] ?? 0,
		$classTotals['robo_interno'] ?? 0,
		$classTotals['robo_externo'] ?? 0,
	]));
	if ($sumClasses <= 0) $sumClasses = $currTotal > 0 ? $currTotal : 1;

	$pctOperativa = round(($classTotals['operativa'] ?? 0) / $sumClasses * 100, 1);
	$pctComercial = round(($classTotals['comercial'] ?? 0) / $sumClasses * 100, 1);
	$pctAveria = round(($classTotals['averia'] ?? 0) / $sumClasses * 100, 1);
	$pctAjuste = round(($classTotals['ajuste'] ?? 0) / $sumClasses * 100, 1);

	// Datos para gráficos en JS
	$currMonthlyVals = $currData['monthly'] ?? array_fill(0, 12, null); // sin datos del año: meses vacíos, no montos de ejemplo
	$currCumulVals = $currData['cumulative'] ?? array_fill(0, 12, null);
	$currByClass = $currData['by_class'] ?? [];
@endphp

<style>
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyAnTabContainer) .dairy-related { display: none !important; }
.daily-an-section { display: flex; flex-direction: column; gap: 18px; }
.daily-an-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.02); position: relative; transition: box-shadow 0.2s ease, border-color 0.2s ease; }
.daily-an-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.daily-an-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.daily-an-card-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; letter-spacing: -0.01em; }

/* Grid de KPIs */
.daily-an-kpis-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.daily-an-kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.18s ease, box-shadow 0.18s ease; }
.daily-an-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.daily-an-kpi-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.daily-an-kpi-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; }
.daily-an-kpi-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
.daily-an-kpi-icon.tone-green { background: #dcfce7; color: #16a34a; }
.daily-an-kpi-icon.tone-amber { background: #fef3c7; color: #d97706; }
.daily-an-kpi-icon.tone-slate { background: #f1f5f9; color: #475569; }
.daily-an-kpi-icon.tone-red { background: #fee2e2; color: #dc2626; }
.daily-an-kpi-val { font-size: 1.6rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 4px; }
.daily-an-kpi-sub { font-size: 0.74rem; color: #64748b; font-weight: 500; }

/* Aviso Ámbar */
.daily-an-alert-strip { background: #fefce8; border: 1px solid #fef08a; border-radius: 12px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(234,179,8,0.05); }
.daily-an-alert-content { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; color: #854d0e; }
.daily-an-alert-content strong { color: #713f12; }

/* Grid de Evolución y Ranking */
.daily-an-charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }

/* Toggle Acumulado / Mensual */
.daily-an-btn-toggle { display: inline-flex; background: #f1f5f9; border-radius: 8px; padding: 3px; border: 1px solid #e2e8f0; }
.daily-an-toggle-btn { padding: 4px 12px; font-size: 0.74rem; font-weight: 700; border-radius: 6px; border: none; background: transparent; color: #64748b; cursor: pointer; transition: all 0.15s ease; }
.daily-an-toggle-btn.is-active { background: #16a34a; color: #ffffff; box-shadow: 0 1px 4px rgba(22,163,74,0.25); }

/* SVG Chart Container */
.daily-an-chart-wrap { position: relative; width: 100%; height: 260px; overflow: hidden; }
.daily-an-chart-svg { width: 100%; height: 100%; overflow: visible; }

/* Ranking Meses */
.daily-an-rank-list { display: flex; flex-direction: column; gap: 14px; padding-top: 6px; }
.daily-an-rank-row { display: grid; grid-template-columns: 42px 1fr 65px; align-items: center; gap: 12px; }
.daily-an-rank-lbl { font-size: 0.82rem; font-weight: 700; color: #0f172a; }
.daily-an-rank-track { height: 18px; background: #f1f5f9; border-radius: 6px; overflow: hidden; width: 100%; position: relative; }
.daily-an-rank-fill { height: 100%; border-radius: 6px; transition: width 0.4s ease; }
.daily-an-rank-fill.is-red { background: #ef4444; }
.daily-an-rank-fill.is-amber { background: #f59e0b; }
.daily-an-rank-val { font-size: 0.84rem; font-weight: 800; color: #ef4444; text-align: right; font-variant-numeric: tabular-nums; }

/* Grid de Impacto por Clase */
.daily-an-class-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.daily-an-class-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 14px; transition: transform 0.18s ease; }
.daily-an-class-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.daily-an-class-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.daily-an-class-icon.tone-green { background: #dcfce7; color: #16a34a; }
.daily-an-class-icon.tone-amber { background: #fef3c7; color: #d97706; }
.daily-an-class-icon.tone-purple { background: #f3e8ff; color: #8b5cf6; }
.daily-an-class-icon.tone-slate { background: #f1f5f9; color: #475569; }
.daily-an-class-name { font-size: 0.76rem; font-weight: 700; color: #64748b; line-height: 1.1; margin-bottom: 2px; }
.daily-an-class-val { font-size: 1.25rem; font-weight: 800; color: #0f172a; line-height: 1.15; }
.daily-an-class-pct { font-size: 0.72rem; color: #64748b; font-weight: 500; }

/* Barras Apiladas SVG */
.daily-an-stacked-wrap { position: relative; width: 100%; height: 230px; }
.daily-an-legend { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 18px; margin-top: 14px; font-size: 0.74rem; font-weight: 600; color: #475569; }
.daily-an-legend-item { display: inline-flex; align-items: center; gap: 6px; }
.daily-an-legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

/* Tabla Compacta */
.daily-an-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem; }
.daily-an-table th { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; padding: 9px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.daily-an-table th:first-child { border-top-left-radius: 8px; }
.daily-an-table th:last-child { border-top-right-radius: 8px; }
.daily-an-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.daily-an-table tr:hover td { background: #f8fafc; }

/* Tag de Años en Filtro */
.daily-an-year-tag { display: inline-flex; align-items: center; gap: 4px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 2px 7px; font-size: 0.74rem; font-weight: 700; color: #1e293b; margin-right: 4px; }

/* Nota Azul */
.daily-an-info-note { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 8px 12px; font-size: 0.74rem; color: #1e40af; display: flex; align-items: center; gap: 6px; }

@media (max-width: 1199px) {
	.daily-an-kpis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.daily-an-charts-grid { grid-template-columns: 1fr; }
	.daily-an-class-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767px) {
	.daily-an-kpis-grid { grid-template-columns: 1fr; }
	.daily-an-class-grid { grid-template-columns: 1fr; }
	.daily-oc-filters-form { flex-direction: column; align-items: stretch; }
	.daily-oc-filter-group { width: 100% !important; flex: 1 1 100% !important; }
}
</style>

<div class="daily-an-section" id="dailyAnTabContainer">

	<!-- A. ENCABEZADO Y FILTROS -->
	<div class="daily-oc-header">
		<div class="daily-oc-title-block">
			<div class="d-flex align-items-center">
				<div class="mr-2.5" style="width: 32px; height: 32px; border-radius: 8px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
					<i class="fa-solid fa-chart-line"></i>
				</div>
				<h5 class="mb-0">Comparativo anual acumulado</h5>
			</div>
			<p class="mt-1">
				Evolución mensual y acumulada de la merma confirmada por año. Los meses futuros no se proyectan.
			</p>
		</div>

		<!-- Exportar -->
		<div class="d-flex align-items-center">
			@include('components.dairy_export', ['exportScope' => '#tab-anual', 'exportName' => 'comparativo_anual'])
		</div>
	</div>

	<!-- FILTROS SINCRONIZADOS -->
	<div class="daily-oc-filters">
		<form class="daily-oc-filters-form" id="dailyAnFilterForm" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros comparativo anual">
			<input type="hidden" name="years" id="an-f-years-hidden" value="{{ implode(',', $selYears) }}">

			<div class="daily-oc-filter-group" style="flex: 0 1 110px; min-width: 100px;">
				<label class="daily-oc-filter-label" for="an-f-from-month">Desde</label>
				<select name="start_month" id="an-f-from-month" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					@foreach($monthsFull as $mIdx => $mName)
						<option value="{{ $mIdx + 1 }}" {{ $mIdx == 0 ? 'selected' : '' }}>{{ $mName }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group" style="flex: 0 1 110px; min-width: 100px;">
				<label class="daily-oc-filter-label" for="an-f-to-month">Hasta</label>
				<select name="end_month" id="an-f-to-month" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					@foreach($monthsFull as $mIdx => $mName)
						<option value="{{ $mIdx + 1 }}" {{ $mIdx == $lastValidIdx ? 'selected' : '' }}>{{ $mName }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group" style="flex: 1 1 170px; min-width: 150px;">
				<label class="daily-oc-filter-label">Años de análisis</label>
				<div class="d-flex align-items-center flex-wrap" style="height: 31px; background: #ffffff; border: 1px solid #ced4da; border-radius: 6px; padding: 2px 6px;">
					@foreach($selYears as $yTag)
						<span class="daily-an-year-tag">
							{{ $yTag }} <i class="fa-solid fa-xmark text-muted" style="cursor: pointer; font-size: 0.65rem;" onclick="dailyRemoveYearTag({{ $yTag }})" title="Quitar año"></i>
						</span>
					@endforeach
				</div>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="an-f-branch">Sucursal</label>
				<select name="branch_id" id="an-f-branch" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todas</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="an-f-dept">Departamento</label>
				<select name="group_id" id="an-f-dept" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group" style="flex: 0 1 110px; min-width: 100px;">
				<label class="daily-oc-filter-label" for="an-f-class">Clase</label>
				<select name="loss_class" id="an-f-class" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todas</option>
					@foreach(\Grocy\Services\LossIntelligenceService::CLASSES as $kCls => $lblCls)
						<option value="{{ $kCls }}" @if(($f['loss_class'] ?? null) == $kCls) selected @endif>{{ $lblCls }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="an-f-supp">Proveedor</label>
				<select name="supplier_id" id="an-f-supp" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="an-f-sku">SKU</label>
				<select name="product_id" id="an-f-sku" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['products'] as $p)
						<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group" style="flex: 0 1 110px; min-width: 100px;">
				<label class="daily-oc-filter-label" for="an-f-unit">Unidad</label>
				<select name="metric" id="an-f-unit" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="cost" {{ $m === 'cost' ? 'selected' : '' }}>Dólares</option>
					<option value="pct" {{ $m === 'pct' ? 'selected' : '' }}>% de ventas</option>
					<option value="units" {{ $m === 'units' ? 'selected' : '' }}>Unidades</option>
				</select>
			</div>

			<div class="d-flex align-items-end gap-1" style="flex: 0 0 auto;">
				<button type="submit" class="btn btn-sm btn-success font-weight-600 shadow-xs px-3" style="border-radius: 6px; height: 31px; background-color: #16a34a; border-color: #16a34a;">
					<i class="fa-solid fa-check mr-1"></i>Aplicar
				</button>
				<a href="{{ $U('/waste#tab-anual') }}" class="btn btn-sm btn-light border text-muted px-2.5" style="border-radius: 6px; height: 31px;" title="Limpiar filtros">
					<i class="fa-solid fa-rotate-left"></i>
				</a>
			</div>
		</form>
	</div>

	<!-- B. INDICADORES PRINCIPALES (4 TARJETAS) -->
	<div class="daily-an-kpis-grid">
		<!-- 1. Acumulado 2026 -->
		<div class="daily-an-kpi-card">
			<div class="daily-an-kpi-head">
				<div>
					<span class="daily-an-kpi-lbl">Acumulado {{ $currYear }}</span>
					<div class="text-muted" style="font-size: 0.68rem; font-weight: 600;">Ene – {{ $cutOffMonthLabel }}</div>
				</div>
				<div class="daily-an-kpi-icon tone-green">
					<i class="fa-solid fa-chart-line"></i>
				</div>
			</div>
			<div>
				<div class="daily-an-kpi-val text-dark">{{ $fmtVal($currTotal) }}</div>
				<div class="daily-an-kpi-sub">Total acumulado del año</div>
			</div>
		</div>

		<!-- 2. Año base 2025 -->
		<div class="daily-an-kpi-card">
			<div class="daily-an-kpi-head">
				<span class="daily-an-kpi-lbl">Año base {{ $baseYear }}</span>
				<div class="daily-an-kpi-icon tone-amber">
					<i class="fa-solid fa-calendar"></i>
				</div>
			</div>
			<div>
				@if($baseHasData)
					<div class="daily-an-kpi-val text-dark">{{ $fmtVal($baseData['total_cost'] ?? 0) }}</div>
					<div class="daily-an-kpi-sub">Total acumulado a {{ $cutOffMonthLabel }}</div>
				@else
					<div class="daily-an-kpi-val" style="color: #d97706;">Sin datos</div>
					<div class="daily-an-kpi-sub">Histórico no disponible</div>
				@endif
			</div>
		</div>

		<!-- 3. Variación interanual -->
		<div class="daily-an-kpi-card">
			<div class="daily-an-kpi-head">
				<span class="daily-an-kpi-lbl">Variación interanual</span>
				<div class="daily-an-kpi-icon tone-slate">
					<i class="fa-solid fa-scale-balanced"></i>
				</div>
			</div>
			<div>
				@if($baseHasData)
					@php
						$diff = $currTotal - floatval($baseData['total_cost'] ?? 0);
						$pctDiff = floatval($baseData['total_cost'] ?? 0) > 0 ? ($diff / floatval($baseData['total_cost'] ?? 0) * 100) : null;
					@endphp
					<div class="daily-an-kpi-val {{ $diff > 0 ? 'text-danger' : 'text-success' }}">
						{{ $diff > 0 ? '+' : '' }}{{ $fmtVal($diff) }}
					</div>
					<div class="daily-an-kpi-sub">
						{{ $pctDiff !== null ? (($pctDiff > 0 ? '+' : '') . number_format($pctDiff, 1) . '%') : 'Base cero' }}
					</div>
				@else
					<div class="daily-an-kpi-val text-dark" style="font-size: 1.45rem;">No comparable</div>
					<div class="daily-an-kpi-sub">No hay datos históricos de {{ $baseYear }}</div>
				@endif
			</div>
		</div>

		<!-- 4. Mayor pérdida mensual -->
		<div class="daily-an-kpi-card">
			<div class="daily-an-kpi-head">
				<div>
					<span class="daily-an-kpi-lbl">Mayor pérdida mensual {{ $currYear }}</span>
					<div class="text-danger font-weight-700" style="font-size: 0.72rem;">{{ $highestMonth['month'] === 'Oct' ? 'Octubre' : ($monthsFull[array_search($highestMonth['month'], $labels)] ?? $highestMonth['month']) }}</div>
				</div>
				<div class="daily-an-kpi-icon tone-red">
					<i class="fa-solid fa-arrow-trend-up"></i>
				</div>
			</div>
			<div>
				<div class="daily-an-kpi-val text-dark">{{ $fmtVal($highestMonth['cost']) }}</div>
				<div class="daily-an-kpi-sub">Mes con mayor impacto</div>
			</div>
		</div>
	</div>

	<!-- C. AVISO DE HISTÓRICO PENDIENTE -->
	@if(!$baseHasData)
		<div class="daily-an-alert-strip">
			<div class="daily-an-alert-content">
				<i class="fa-solid fa-circle-exclamation text-warning mr-1" style="font-size: 1.1rem;"></i>
				<div>
					<strong>Histórico {{ $baseYear }} pendiente</strong> · Importa los datos de {{ $baseYear }} para habilitar la comparación anual (importación desde tu sistema o archivo).
				</div>
			</div>
			<div>
				<a href="{{ $U('/data-import') }}" class="btn btn-sm btn-outline-dark bg-white font-weight-600 px-3 py-1" style="border-radius: 6px; font-size: 0.78rem;">
					<i class="fa-solid fa-upload mr-1.5"></i>Importar histórico
				</a>
			</div>
		</div>
	@endif

	<!-- D. GRÁFICO PRINCIPAL DE EVOLUCIÓN Y RANKING DE MESES -->
	<div class="daily-an-charts-grid">
		<!-- D.1 Gráfico de Evolución -->
		<div class="daily-an-card">
			<div class="daily-an-card-header">
				<h6 class="daily-an-card-title" id="dailyAnChartTitle">
					Evolución acumulada por mes
				</h6>
				<div class="daily-an-btn-toggle" role="group" aria-label="Modo de visualización">
					<button type="button" class="daily-an-toggle-btn is-active" id="btnModeCumul" onclick="dailySetEvolutionMode('cumulative')">Acumulado</button>
					<button type="button" class="daily-an-toggle-btn" id="btnModeMonth" onclick="dailySetEvolutionMode('monthly')">Mensual</button>
				</div>
			</div>

			<div class="daily-an-chart-wrap">
				<svg class="daily-an-chart-svg" id="svgEvolutionChart" viewBox="0 0 740 240" preserveAspectRatio="none">
					<!-- Se inyecta dinámicamente por JavaScript -->
				</svg>
			</div>

			<!-- Leyenda del gráfico -->
			<div style="display: flex; align-items: center; justify-content: center; gap: 24px; margin-top: 10px; font-size: 0.74rem; font-weight: 600; color: #475569;">
				<div style="display: inline-flex; align-items: center; gap: 6px;">
					<span style="width: 10px; height: 10px; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
					<span id="lblLegendCurrYear">{{ $currYear }} (acumulado)</span>
				</div>
				<div style="display: inline-flex; align-items: center; gap: 6px;" class="text-muted">
					<span style="width: 14px; height: 2px; border-top: 2px dashed #94a3b8; display: inline-block;"></span>
					<span>{{ $baseYear }} ({{ $baseHasData ? 'acumulado' : 'sin datos' }})</span>
				</div>
			</div>
		</div>

		<!-- D.2 Ranking Meses con Mayor Pérdida -->
		<div class="daily-an-card">
			<div class="daily-an-card-header">
				<div>
					<h6 class="daily-an-card-title">Meses con mayor pérdida</h6>
					<div class="text-muted" style="font-size: 0.7rem; font-weight: 500;">
						Ranking de meses por monto de merma en {{ $currYear }} (Ene – {{ $cutOffMonthLabel }})
					</div>
				</div>
			</div>

			<div class="daily-an-rank-list">
				@php
					$rankMonths = array_slice($topMonths, 0, 3);
					$maxRankCost = !empty($rankMonths) ? floatval($rankMonths[0]['cost']) : 537.20;
				@endphp
				@forelse($rankMonths as $rIdx => $rm)
					@php
						$rCost = floatval($rm['cost']);
						$barWidthPct = $maxRankCost > 0 ? max(8, round(($rCost / $maxRankCost) * 100, 1)) : 0;
						$colorClass = $rIdx === 0 ? 'is-red' : 'is-amber';
					@endphp
					<div class="daily-an-rank-row">
						<span class="daily-an-rank-lbl">{{ $rm['month'] }}</span>
						<div class="daily-an-rank-track">
							<div class="daily-an-rank-fill {{ $colorClass }}" style="width: {{ $barWidthPct }}%;"></div>
						</div>
						<span class="daily-an-rank-val">{{ $money($rCost) }}</span>
					</div>
				@empty
					<div class="text-muted small py-4 text-center">Sin datos de meses en el período.</div>
				@endforelse
			</div>

			<div class="daily-an-info-note mt-4">
				<i class="fa-solid fa-circle-info mr-1 text-primary"></i>
				<span>Solo se muestran meses con datos disponibles de {{ $currYear }}. No se incluyen los meses sin datos.</span>
			</div>
		</div>
	</div>

	<!-- E. TARJETAS DE IMPACTO POR CLASE (ACUMULADO 2026 · ENE - OCT) -->
	<div class="daily-an-card">
		<div class="daily-an-card-header mb-3">
			<h6 class="daily-an-card-title">
				Impacto por clase de merma (acumulado {{ $currYear }} · Ene – {{ $cutOffMonthLabel }})
			</h6>
		</div>

		<div class="daily-an-class-grid">
			<!-- 1. Merma Operativa -->
			<div class="daily-an-class-card">
				<div class="daily-an-class-icon tone-green">
					<i class="fa-solid fa-cart-shopping"></i>
				</div>
				<div>
					<div class="daily-an-class-name">Merma operativa</div>
					<div class="daily-an-class-val">{{ $money($classTotals['operativa'] ?? null) }}</div>
					<div class="daily-an-class-pct">{{ $pctOperativa }}% del total</div>
				</div>
			</div>

			<!-- 2. Merma Comercial -->
			<div class="daily-an-class-card">
				<div class="daily-an-class-icon tone-amber">
					<i class="fa-solid fa-tag"></i>
				</div>
				<div>
					<div class="daily-an-class-name">Merma comercial</div>
					<div class="daily-an-class-val">{{ $money($classTotals['comercial'] ?? null) }}</div>
					<div class="daily-an-class-pct">{{ $pctComercial }}% del total</div>
				</div>
			</div>

			<!-- 3. Averías y Roturas -->
			<div class="daily-an-class-card">
				<div class="daily-an-class-icon tone-purple">
					<i class="fa-solid fa-box-open"></i>
				</div>
				<div>
					<div class="daily-an-class-name">Averías y roturas</div>
					<div class="daily-an-class-val">{{ $money($classTotals['averia'] ?? null) }}</div>
					<div class="daily-an-class-pct">{{ $pctAveria }}% del total</div>
				</div>
			</div>

			<!-- 4. Ajustes de Inventario -->
			<div class="daily-an-class-card">
				<div class="daily-an-class-icon tone-slate">
					<i class="fa-solid fa-gear"></i>
				</div>
				<div>
					<div class="daily-an-class-name">Ajustes</div>
					<div class="daily-an-class-val">{{ $money($classTotals['ajuste'] ?? null) }}</div>
					<div class="daily-an-class-pct">{{ $pctAjuste }}% del total</div>
				</div>
			</div>
		</div>
	</div>

	<!-- F. DISTRIBUCIÓN MENSUAL POR CLASE (BARRAS APILADAS) -->
	<div class="daily-an-card">
		<div class="daily-an-card-header mb-2">
			<h6 class="daily-an-card-title">
				Distribución mensual por clase ({{ $currYear }})
			</h6>
		</div>

		<div class="daily-an-stacked-wrap">
			<svg id="svgStackedChart" viewBox="0 0 740 210" preserveAspectRatio="none" style="width: 100%; height: 100%; overflow: visible;">
				<!-- Se inyecta dinámicamente por JavaScript -->
			</svg>
		</div>

		<!-- Leyenda -->
		<div class="daily-an-legend">
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #ef4444;"></span> Merma operativa</div>
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #f59e0b;"></span> Merma comercial</div>
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #64748b;"></span> Ajustes de inventario</div>
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #8b5cf6;"></span> Averías y roturas</div>
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #3b82f6;"></span> Robo interno</div>
			<div class="daily-an-legend-item"><span class="daily-an-legend-dot" style="background: #1e293b;"></span> Robo externo</div>
		</div>
	</div>

	<!-- G. TABLA MENSUAL COMPARATIVA -->
	<div class="daily-an-card">
		<div class="daily-an-card-header">
			<h6 class="daily-an-card-title">
				Detalle mensual comparativo
			</h6>
		</div>

		<div class="table-responsive">
			<table class="daily-an-table" id="tableAnualComparison" data-export-title="Comparativo mensual y acumulado {{ $baseYear }} vs {{ $currYear }}">
				<thead>
					<tr>
						<th>Mes</th>
						<th class="text-right">{{ $baseYear }} mensual</th>
						<th class="text-right">{{ $baseYear }} acumulado</th>
						<th class="text-right">{{ $currYear }} mensual</th>
						<th class="text-right">{{ $currYear }} acumulado</th>
						<th class="text-right">Diferencia (comparable)</th>
						<th class="text-right">Variación %</th>
					</tr>
				</thead>
				<tbody>
					@foreach($labels as $i => $lbl)
						@php
							$mValCurr = $currMonthlyVals[$i] ?? null;
							$cValCurr = $currCumulVals[$i] ?? null;
							$mValBase = $baseData['monthly'][$i] ?? null;
							$cValBase = $baseData['cumulative'][$i] ?? null;
							$isFuture = ($i > $lastValidIdx);
						@endphp
						<tr>
							<td class="font-weight-700 text-dark">{{ $lbl }}</td>

							<!-- Base Year Mensual -->
							<td class="text-right text-muted">
								{{ $baseHasData ? $fmtVal($mValBase) : 'Sin datos' }}
							</td>

							<!-- Base Year Acumulado -->
							<td class="text-right text-muted">
								{{ $baseHasData ? $fmtVal($cValBase) : 'Sin datos' }}
							</td>

							<!-- Curr Year Mensual -->
							<td class="text-right font-weight-600 text-dark">
								{{ $isFuture ? '—' : $fmtVal($mValCurr) }}
							</td>

							<!-- Curr Year Acumulado -->
							<td class="text-right font-weight-700 text-dark">
								{{ $isFuture ? 'No proyectado' : $fmtVal($cValCurr) }}
							</td>

							<!-- Diferencia comparable -->
							<td class="text-right">
								@if(!$baseHasData || $isFuture)
									<span class="text-muted">—</span>
								@else
									@php $diffRow = floatval($cValCurr) - floatval($cValBase); @endphp
									<span class="{{ $diffRow > 0 ? 'text-danger font-weight-700' : 'text-success font-weight-700' }}">
										{{ $diffRow > 0 ? '+' : '' }}{{ $fmtVal($diffRow) }}
									</span>
								@endif
							</td>

							<!-- Variación % -->
							<td class="text-right">
								@if(!$baseHasData || $isFuture)
									<span class="text-muted">—</span>
								@else
									@if(floatval($cValBase) > 0)
										@php $pctRow = ($cValCurr - $cValBase) / floatval($cValBase) * 100; @endphp
										<span class="{{ $pctRow > 0 ? 'text-danger font-weight-600' : 'text-success font-weight-600' }}">
											{{ $pctRow > 0 ? '+' : '' }}{{ number_format($pctRow, 1) }}%
										</span>
									@else
										<span class="text-muted">Base cero</span>
									@endif
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>

</div>

<script>
(function() {
	var labels = @json($labels);
	var currMonthly = @json($currMonthlyVals);
	var currCumul = @json($currCumulVals);
	var currByClass = @json($currByClass);
	var lastValidIdx = {{ $lastValidIdx }};
	var currYear = {{ $currYear }};
	var baseYear = {{ $baseYear }};
	var baseHasData = {{ $baseHasData ? 'true' : 'false' }};

	var currentMode = 'cumulative';

	// 1. Renderizar Gráfico de Evolución Principal (SVG)
	function renderEvolutionSvg(mode) {
		var svg = document.getElementById('svgEvolutionChart');
		if (!svg) return;

		var width = 740;
		var height = 240;
		var padL = 60;
		var padR = 40;
		var padT = 30;
		var padB = 35;
		var plotW = width - padL - padR; // 640
		var plotH = height - padT - padB; // 175
		var yZero = padT + plotH; // 205

		var data = (mode === 'cumulative') ? currCumul : currMonthly;
		var maxVal = 0;
		for (var i = 0; i <= lastValidIdx; i++) {
			if (data[i] !== null && data[i] > maxVal) maxVal = data[i];
		}
		var yMax = (mode === 'cumulative') ? Math.max(2400, Math.ceil(maxVal / 400) * 400) : Math.max(600, Math.ceil(maxVal / 100) * 100);
		var yTicksCount = (mode === 'cumulative') ? 6 : 6;
		var step = yMax / yTicksCount;

		var html = '';

		// Ejes y líneas horizontales de guía
		for (var t = 0; t <= yTicksCount; t++) {
			var tickVal = Math.round(t * step);
			var yPos = yZero - ((tickVal / yMax) * plotH);
			html += '<line x1="' + padL + '" y1="' + yPos + '" x2="' + (padL + plotW) + '" y2="' + yPos + '" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />';
			html += '<text x="' + (padL - 10) + '" y="' + (yPos + 4) + '" fill="#94a3b8" font-size="10" font-family="sans-serif" text-anchor="end">$' + tickVal.toLocaleString() + '</text>';
		}

		// Coordenadas de los meses
		var points = [];
		var xStep = plotW / 11; // 11 intervalos para 12 meses

		for (var i = 0; i < 12; i++) {
			var x = padL + (i * xStep);
			var isPastCutoff = (i > lastValidIdx);

			// Etiqueta del mes en eje X
			html += '<text x="' + x + '" y="' + (yZero + 20) + '" fill="#64748b" font-size="11" font-weight="600" font-family="sans-serif" text-anchor="middle">' + labels[i] + '</text>';

			if (!isPastCutoff && data[i] !== null) {
				var val = parseFloat(data[i]);
				var y = yZero - ((val / yMax) * plotH);
				points.push({ x: x, y: y, val: val, month: labels[i] });
			}
		}

		// Dibujar Línea Continua de 2026
		if (points.length > 1) {
			var pathD = 'M ' + points[0].x + ' ' + points[0].y;
			for (var p = 1; p < points.length; p++) {
				pathD += ' L ' + points[p].x + ' ' + points[p].y;
			}
			html += '<path d="' + pathD + '" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />';
		}

		// Dibujar Puntos y Etiquetas de Valores
		points.forEach(function(pt, idx) {
			html += '<circle cx="' + pt.x + '" cy="' + pt.y + '" r="4" fill="#ffffff" stroke="#16a34a" stroke-width="2.5" />';
			
			// Texto sobre el punto
			var valLabel = '$' + pt.val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			var labelY = pt.y - 8;
			var fontWeight = (idx === points.length - 1) ? '800' : '600';
			var fontSize = (idx === points.length - 1) ? '11' : '9.5';
			var fontColor = (idx === points.length - 1) ? '#0f172a' : '#334155';

			html += '<text x="' + pt.x + '" y="' + labelY + '" fill="' + fontColor + '" font-size="' + fontSize + '" font-weight="' + fontWeight + '" font-family="sans-serif" text-anchor="middle">' + valLabel + '</text>';
		});

		// Meses posteriores al corte: "Sin datos"
		if (lastValidIdx < 11) {
			var noDataX = padL + (((lastValidIdx + 1 + 11) / 2) * xStep);
			html += '<text x="' + noDataX + '" y="' + (yZero - (plotH * 0.45)) + '" fill="#94a3b8" font-size="11" font-weight="600" font-family="sans-serif" text-anchor="middle">Sin datos</text>';
		}

		svg.innerHTML = html;
	}

	// 2. Renderizar Gráfico de Barras Apiladas por Clase (SVG)
	function renderStackedSvg() {
		var svg = document.getElementById('svgStackedChart');
		if (!svg) return;

		var width = 740;
		var height = 210;
		var padL = 50;
		var padR = 30;
		var padT = 25;
		var padB = 30;
		var plotW = width - padL - padR;
		var plotH = height - padT - padB; // 155
		var yZero = padT + plotH; // 180

		var yMax = 600;
		var yTicks = [0, 200, 400, 600];
		var html = '';

		// Líneas de guía y eje Y
		yTicks.forEach(function(tVal) {
			var yPos = yZero - ((tVal / yMax) * plotH);
			html += '<line x1="' + padL + '" y1="' + yPos + '" x2="' + (padL + plotW) + '" y2="' + yPos + '" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="3,3" />';
			html += '<text x="' + (padL - 10) + '" y="' + (yPos + 4) + '" fill="#94a3b8" font-size="10" font-family="sans-serif" text-anchor="end">$' + tVal + '</text>';
		});

		var colWidth = 28;
		var xStep = plotW / 12;

		var classColors = {
			operativa: '#ef4444',
			comercial: '#f59e0b',
			ajuste: '#64748b',
			averia: '#8b5cf6',
			robo_interno: '#3b82f6',
			robo_externo: '#1e293b'
		};
		var classOrder = ['operativa', 'comercial', 'ajuste', 'averia', 'robo_interno', 'robo_externo'];

		for (var m = 0; m < 12; m++) {
			var xCenter = padL + (m * xStep) + (xStep / 2);
			var xLeft = xCenter - (colWidth / 2);
			var isPastCutoff = (m > lastValidIdx);

			// Etiqueta del mes
			html += '<text x="' + xCenter + '" y="' + (yZero + 18) + '" fill="#64748b" font-size="10.5" font-weight="600" font-family="sans-serif" text-anchor="middle">' + labels[m] + '</text>';

			if (isPastCutoff) {
				// Recuadro punteado "Sin datos"
				var boxH = plotH * 0.75;
				var boxY = yZero - boxH;
				html += '<rect x="' + xLeft + '" y="' + boxY + '" width="' + colWidth + '" height="' + boxH + '" fill="transparent" stroke="#cbd5e1" stroke-dasharray="4,4" rx="4" />';
				html += '<text x="' + xCenter + '" y="' + (boxY + (boxH / 2) + 3) + '" fill="#94a3b8" font-size="8.5" font-family="sans-serif" text-anchor="middle">Sin datos</text>';
			} else {
				var monthClasses = currByClass[m] || {};
				var currentY = yZero;

				classOrder.forEach(function(ck) {
					var cVal = parseFloat(monthClasses[ck] || 0);
					if (cVal > 0) {
						var segH = Math.max(1, (cVal / yMax) * plotH);
						var segY = currentY - segH;
						html += '<rect x="' + xLeft + '" y="' + segY + '" width="' + colWidth + '" height="' + segH + '" fill="' + classColors[ck] + '">';
						html += '<title>' + labels[m] + ' · ' + ck + ': $' + cVal.toFixed(2) + '</title></rect>';
						currentY = segY;
					}
				});
			}
		}

		svg.innerHTML = html;
	}

	window.dailySetEvolutionMode = function(mode) {
		currentMode = mode;
		var btnCumul = document.getElementById('btnModeCumul');
		var btnMonth = document.getElementById('btnModeMonth');
		var titleEl = document.getElementById('dailyAnChartTitle');
		var lblLegend = document.getElementById('lblLegendCurrYear');

		if (mode === 'cumulative') {
			btnCumul.classList.add('is-active');
			btnMonth.classList.remove('is-active');
			titleEl.innerText = 'Evolución acumulada por mes';
			if (lblLegend) lblLegend.innerText = currYear + ' (acumulado)';
		} else {
			btnMonth.classList.add('is-active');
			btnCumul.classList.remove('is-active');
			titleEl.innerText = 'Evolución mensual por mes';
			if (lblLegend) lblLegend.innerText = currYear + ' (mensual)';
		}

		renderEvolutionSvg(mode);
	};

	window.dailyRemoveYearTag = function(year) {
		var hidden = document.getElementById('an-f-years-hidden');
		if (!hidden) return;
		var years = hidden.value.split(',').filter(function(y) { return parseInt(y) !== year; });
		hidden.value = years.join(',');
		DairyLoss.applyFilters(document.getElementById('dailyAnFilterForm'));
	};

	// Inicializar gráficos
	renderEvolutionSvg('cumulative');
	renderStackedSvg();
})();
</script>
