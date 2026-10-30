@php
	$money = $money ?? fn($v) => '$' . number_format(floatval($v), 2);
	$pct = $pct ?? fn($v) => $v === null ? '—' : number_format($v, 2) . '%';
	$causeCatalog = $causeCatalog ?? ($cls['cause_names'] ?? []);
	$names = $names ?? ($cls['names'] ?? []);

	// Helper para fotografía de productos con reemplazo visual seguro para ajustes
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

	$ajData = $cls['classes']['ajuste'] ?? ['cost' => 0, 'pct_sales' => 0, 'share' => 0, 'events' => 0, 'skus' => [], 'causes' => [], 'recent' => []];
	$ajSumm = $summary['ajuste'] ?? ['value' => 0, 'pct_sales' => 0, 'share' => 0, 'loss' => 0, 'gain' => 0];

	$netSales = floatval($cls['net_sales'] ?? 0);
	$netValue = floatval($ajSumm['value'] ?? $ajData['cost'] ?? 0);
	$lossValue = floatval($ajSumm['loss'] ?? 0);
	$gainValue = floatval($ajSumm['gain'] ?? 0);
	$pctSales = floatval($ajSumm['pct_sales'] ?? $ajData['pct_sales'] ?? 0);
	$shareVal = floatval($ajSumm['share'] ?? $ajData['share'] ?? 0);

	$skuList = $ajData['skus'] ?? [];
	$recentList = $ajData['recent'] ?? [];

	// Contadores de faltantes y sobrantes
	$countFaltantes = 0;
	$countSobrantes = 0;
	foreach ($skuList as $s) {
		if ($s['cost'] >= 0) {
			$countFaltantes++;
		} else {
			$countSobrantes++;
		}
	}

	// Cálculo del gráfico de cascada
	$maxVal = max(40, ceil(max($lossValue, $netValue, $gainValue, 10) / 10) * 10);
	$svgW = 540;
	$svgH = 220;
	$padL = 50;
	$padR = 25;
	$padT = 32;
	$padB = 52;
	$plotW = $svgW - $padL - $padR; // 465
	$plotH = $svgH - $padT - $padB; // 136
	$yZero = $padT + $plotH; // 168
	$scaleY = $maxVal > 0 ? ($plotH / $maxVal) : 0;

	// Coordenadas Barra 1: Faltantes
	$b1W = 74;
	$b1X = $padL + ($plotW * 0.18) - ($b1W / 2);
	$b1H = max(2, $lossValue * $scaleY);
	$b1Y = $yZero - $b1H;

	// Coordenadas Barra 2: Sobrantes (flotante descendente)
	$b2W = 74;
	$b2X = $padL + ($plotW * 0.50) - ($b2W / 2);
	$b2H = max(2, $gainValue * $scaleY);
	$b2TopY = $yZero - ($lossValue * $scaleY);
	$b2BottomY = $yZero - (($lossValue - $gainValue) * $scaleY);

	// Coordenadas Barra 3: Ajuste neto
	$b3W = 74;
	$b3X = $padL + ($plotW * 0.82) - ($b3W / 2);
	$b3H = max(2, abs($netValue) * $scaleY);
	$b3Y = $netValue >= 0 ? ($yZero - $b3H) : $yZero;
@endphp

<style>
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyAjTabContainer) .dairy-related { display: none !important; }
.daily-aj-section { display: flex; flex-direction: column; gap: 18px; }
.daily-aj-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.02); position: relative; transition: box-shadow 0.2s ease, border-color 0.2s ease; }
.daily-aj-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
.daily-aj-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.daily-aj-card-title { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; letter-spacing: -0.01em; }

/* Grid de KPIs */
.daily-aj-kpis-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
.daily-aj-kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.18s ease, box-shadow 0.18s ease; }
.daily-aj-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
.daily-aj-kpi-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.daily-aj-kpi-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; }
.daily-aj-kpi-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
.daily-aj-kpi-icon.tone-green { background: #dcfce7; color: #15803d; }
.daily-aj-kpi-icon.tone-red { background: #fee2e2; color: #b91c1c; }
.daily-aj-kpi-icon.tone-slate { background: #f1f5f9; color: #475569; }
.daily-aj-kpi-val { font-size: 1.6rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 4px; }
.daily-aj-kpi-sub { font-size: 0.74rem; color: #64748b; font-weight: 500; }

/* Badges de producto */
.daily-aj-badge { position: absolute; top: 8px; left: 8px; background: rgba(255,255,255,0.95); backdrop-filter: blur(4px); font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: inline-flex; align-items: center; gap: 5px; z-index: 2; }
.daily-aj-badge.is-faltante { color: #dc2626; border: 1px solid #fecaca; }
.daily-aj-badge.is-sobrante { color: #16a34a; border: 1px solid #bbf7d0; }
.daily-aj-dot { width: 6px; height: 6px; border-radius: 50%; }

/* Waterfall SVG Container */
.daily-aj-waterfall-wrap { width: 100%; height: 220px; position: relative; }
.daily-aj-waterfall-svg { width: 100%; height: 100%; overflow: visible; }

/* Causal Registrada */
.daily-aj-cause-circle { width: 64px; height: 64px; border-radius: 16px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,200,59,0.12); }

@media (max-width: 1199px) {
	.daily-aj-kpis-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
	.daily-oc-sku-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}
@media (max-width: 767px) {
	.daily-aj-kpis-grid { grid-template-columns: 1fr; }
	.daily-aj-cause-body { flex-direction: column; align-items: flex-start !important; }
	.daily-oc-filters-form { flex-direction: column; align-items: stretch; }
	.daily-oc-filter-group { width: 100% !important; flex: 1 1 100% !important; }
	.daily-aj-waterfall-wrap { height: 190px; }
}
</style>

<div class="daily-aj-section" id="dailyAjTabContainer">

	<!-- A. ENCABEZADO Y FILTROS -->
	<div class="daily-oc-header">
		<div class="daily-oc-title-block">
			<div class="d-flex align-items-center">
				<div class="daily-oc-netsales-icon mr-2.5" style="width: 32px; height: 32px; border-radius: 8px;">
					<i class="fa-solid fa-sliders"></i>
				</div>
				<h5 class="mb-0">Ajustes de inventario</h5>
			</div>
			<p class="mt-1">
				Diferencias de conteo y correcciones administrativas · Separadas de la merma física.
			</p>
		</div>

		<!-- Exportar -->
		<div class="d-flex align-items-center">
			@include('components.dairy_export', ['exportScope' => '#tab-ajustes', 'exportName' => 'ajustes_inventario'])
		</div>
	</div>

	<!-- FILTROS SINCRONIZADOS -->
	<div class="daily-oc-filters">
		<form class="daily-oc-filters-form" id="dailyAjFilterForm" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros de ajustes de inventario">
			<div class="daily-oc-filter-group" style="flex: 0 1 135px; min-width: 120px;">
				<label class="daily-oc-filter-label" for="aj-f-from">Desde</label>
				<input type="date" name="start_date" id="aj-f-from" class="form-control form-control-sm border font-weight-600 text-dark" value="{{ $startDate }}" style="border-radius: 6px;">
			</div>

			<div class="daily-oc-filter-group" style="flex: 0 1 135px; min-width: 120px;">
				<label class="daily-oc-filter-label" for="aj-f-to">Hasta</label>
				<input type="date" name="end_date" id="aj-f-to" class="form-control form-control-sm border font-weight-600 text-dark" value="{{ $endDate }}" style="border-radius: 6px;">
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="aj-f-branch">Sucursal</label>
				<select name="branch_id" id="aj-f-branch" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todas</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="aj-f-dept">Departamento</label>
				<select name="group_id" id="aj-f-dept" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="aj-f-supp">Proveedor</label>
				<select name="supplier_id" id="aj-f-supp" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<div class="daily-oc-filter-group">
				<label class="daily-oc-filter-label" for="aj-f-sku">SKU</label>
				<select name="product_id" id="aj-f-sku" class="form-control form-control-sm border font-weight-600 text-dark" style="border-radius: 6px;">
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
				<a href="{{ $U('/waste#tab-ajustes') }}" class="btn btn-sm btn-light border text-muted px-2.5" style="border-radius: 6px; height: 31px;" title="Limpiar filtros">
					<i class="fa-solid fa-rotate-left"></i>
				</a>
			</div>
		</form>
	</div>

	<!-- B. INDICADORES PRINCIPALES (5 TARJETAS) -->
	<div class="daily-aj-kpis-grid">
		<!-- 1. Ajuste neto -->
		<div class="daily-aj-kpi-card">
			<div class="daily-aj-kpi-head">
				<span class="daily-aj-kpi-lbl">Ajuste neto</span>
				<div class="daily-aj-kpi-icon tone-green">
					<i class="fa-solid fa-scale-balanced"></i>
				</div>
			</div>
			<div>
				<div class="daily-aj-kpi-val text-dark">{{ $money($netValue) }}</div>
				<div class="daily-aj-kpi-sub">{{ number_format($pctSales, 2) }}% de ventas · {{ number_format($shareVal, 2) }}% del total</div>
			</div>
		</div>

		<!-- 2. Faltantes -->
		<div class="daily-aj-kpi-card">
			<div class="daily-aj-kpi-head">
				<span class="daily-aj-kpi-lbl">Faltantes</span>
				<div class="daily-aj-kpi-icon tone-red">
					<i class="fa-solid fa-arrow-trend-down"></i>
				</div>
			</div>
			<div>
				<div class="daily-aj-kpi-val text-danger">{{ $money($lossValue) }}</div>
				<div class="daily-aj-kpi-sub text-danger font-weight-500">pérdida por diferencias</div>
			</div>
		</div>

		<!-- 3. Sobrantes -->
		<div class="daily-aj-kpi-card">
			<div class="daily-aj-kpi-head">
				<span class="daily-aj-kpi-lbl">Sobrantes</span>
				<div class="daily-aj-kpi-icon tone-green">
					<i class="fa-solid fa-arrow-trend-up"></i>
				</div>
			</div>
			<div>
				<div class="daily-aj-kpi-val text-success">{{ $money($gainValue) }}</div>
				<div class="daily-aj-kpi-sub">neto {{ $money($netValue) }}</div>
			</div>
		</div>

		<!-- 4. Actividad -->
		<div class="daily-aj-kpi-card">
			<div class="daily-aj-kpi-head">
				<span class="daily-aj-kpi-lbl">Actividad</span>
				<div class="daily-aj-kpi-icon tone-slate">
					<i class="fa-solid fa-list-check"></i>
				</div>
			</div>
			<div class="d-flex align-items-baseline justify-content-between">
				<div>
					<div class="daily-aj-kpi-val text-dark mb-0">{{ $ajData['events'] }}</div>
					<div class="daily-aj-kpi-sub">movimientos</div>
				</div>
				<div class="text-right">
					<div class="daily-aj-kpi-val text-dark mb-0">{{ count($skuList) }}</div>
					<div class="daily-aj-kpi-sub">SKU afectados</div>
				</div>
			</div>
		</div>

		<!-- 5. Ventas netas -->
		<div class="daily-aj-kpi-card">
			<div class="daily-aj-kpi-head">
				<span class="daily-aj-kpi-lbl">Ventas netas</span>
				<div class="daily-aj-kpi-icon tone-slate">
					<i class="fa-solid fa-cash-register"></i>
				</div>
			</div>
			<div>
				<div class="daily-aj-kpi-val text-dark">{{ $money($netSales) }}</div>
				<div class="daily-aj-kpi-sub">base de los %</div>
			</div>
		</div>
	</div>

	<!-- C. GRÁFICO CASCADA + CAUSAL REGISTRADA -->
	<div class="row">
		<!-- GRÁFICO DE CASCADA (WATERFALL) -->
		<div class="col-lg-7 mb-3 mb-lg-0">
			<div class="daily-aj-card h-100">
				<div class="daily-aj-card-header">
					<div>
						<h6 class="daily-aj-card-title">
							<i class="fa-solid fa-chart-column text-success mr-2"></i>Impacto de los ajustes
						</h6>
						<div class="text-muted small mt-0.5">Composición del ajuste neto en el período seleccionado.</div>
					</div>
				</div>

				<div class="daily-aj-waterfall-wrap">
					<svg class="daily-aj-waterfall-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" preserveAspectRatio="xMidYMid meet">
						<defs>
							<!-- Sombras sutiles -->
							<filter id="ajDropShadow" x="-10%" y="-10%" width="120%" height="120%">
								<feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity="0.08"/>
							</filter>
						</defs>

						<!-- Gridlines horizontales y etiquetas Y -->
						@for($i = 0; $i <= 4; $i++)
							@php
								$valY = ($maxVal / 4) * (4 - $i);
								$yPos = $padT + ($plotH / 4) * $i;
							@endphp
							<line x1="{{ $padL }}" y1="{{ $yPos }}" x2="{{ $svgW - $padR }}" y2="{{ $yPos }}" stroke="#f1f5f9" stroke-width="1" />
							<text x="{{ $padL - 10 }}" y="{{ $yPos + 4 }}" fill="#94a3b8" font-size="11" font-weight="600" text-anchor="end">${{ number_format($valY, 0) }}</text>
						@endfor

						<!-- Líneas conectoras punteadas del Waterfall -->
						<!-- Conector 1: de Barra 1 (Faltantes) a Barra 2 (Sobrantes) -->
						<line x1="{{ $b1X + $b1W }}" y1="{{ $b1Y }}" x2="{{ $b2X }}" y2="{{ $b2TopY }}" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="3,3" />

						<!-- Conector 2: de Barra 2 (Sobrantes) a Barra 3 (Ajuste neto) -->
						<line x1="{{ $b2X + $b2W }}" y1="{{ $b2BottomY }}" x2="{{ $b3X }}" y2="{{ $b3Y }}" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="3,3" />

						<!-- Barra 1: Faltantes (Rojo) -->
						<rect x="{{ $b1X }}" y="{{ $b1Y }}" width="{{ $b1W }}" height="{{ $b1H }}" rx="5" fill="#ef4444" filter="url(#ajDropShadow)">
							<title>Faltantes: {{ $money($lossValue) }} (pérdida por diferencias)</title>
						</rect>
						<text x="{{ $b1X + ($b1W / 2) }}" y="{{ $b1Y - 8 }}" fill="#dc2626" font-size="12" font-weight="800" text-anchor="middle">+{{ $money($lossValue) }}</text>
						<text x="{{ $b1X + ($b1W / 2) }}" y="{{ $yZero + 18 }}" fill="#0f172a" font-size="11" font-weight="700" text-anchor="middle">Faltantes</text>
						<text x="{{ $b1X + ($b1W / 2) }}" y="{{ $yZero + 32 }}" fill="#64748b" font-size="9.5" font-weight="500" text-anchor="middle">pérdida por diferencias</text>

						<!-- Barra 2: Sobrantes (Verde - Flotante) -->
						<rect x="{{ $b2X }}" y="{{ $b2TopY }}" width="{{ $b2W }}" height="{{ $b2H }}" rx="4" fill="#10b981" filter="url(#ajDropShadow)">
							<title>Sobrantes: -{{ $money($gainValue) }} (ajustes a favor)</title>
						</rect>
						<text x="{{ $b2X + ($b2W / 2) }}" y="{{ $b2TopY - 8 }}" fill="#059669" font-size="12" font-weight="800" text-anchor="middle">-{{ $money($gainValue) }}</text>
						<text x="{{ $b2X + ($b2W / 2) }}" y="{{ $yZero + 18 }}" fill="#0f172a" font-size="11" font-weight="700" text-anchor="middle">Sobrantes</text>
						<text x="{{ $b2X + ($b2W / 2) }}" y="{{ $yZero + 32 }}" fill="#64748b" font-size="9.5" font-weight="500" text-anchor="middle">ajustes a favor</text>

						<!-- Barra 3: Ajuste neto (Verde oscuro / Neutro) -->
						<rect x="{{ $b3X }}" y="{{ $b3Y }}" width="{{ $b3W }}" height="{{ $b3H }}" rx="5" fill="#047857" filter="url(#ajDropShadow)">
							<title>Ajuste neto: {{ $money($netValue) }} (impacto en resultados)</title>
						</rect>
						<text x="{{ $b3X + ($b3W / 2) }}" y="{{ $b3Y - 8 }}" fill="#047857" font-size="12" font-weight="800" text-anchor="middle">{{ $money($netValue) }}</text>
						<text x="{{ $b3X + ($b3W / 2) }}" y="{{ $yZero + 18 }}" fill="#0f172a" font-size="11" font-weight="700" text-anchor="middle">Ajuste neto</text>
						<text x="{{ $b3X + ($b3W / 2) }}" y="{{ $yZero + 32 }}" fill="#64748b" font-size="9.5" font-weight="500" text-anchor="middle">impacto en resultados</text>
					</svg>
				</div>
			</div>
		</div>

		<!-- CAUSAL REGISTRADA -->
		<div class="col-lg-5">
			<div class="daily-aj-card h-100 d-flex flex-column justify-content-between">
				<div>
					<div class="daily-aj-card-header">
						<h6 class="daily-aj-card-title">
							<i class="fa-solid fa-clipboard-check text-success mr-2"></i>Causal registrada
						</h6>
						<a href="{{ $U('/kardex') }}" class="small font-weight-600 text-muted" title="Consultar Kardex general">
							Ver en Kardex <i class="fa-solid fa-arrow-right ml-1"></i>
						</a>
					</div>

					<div class="d-flex align-items-center gap-3 pt-2 daily-aj-cause-body">
						<div class="daily-aj-cause-circle">
							<i class="fa-solid fa-boxes-stacked"></i>
						</div>
						<div>
							<div class="d-flex align-items-baseline gap-2">
								<span class="daily-oc-cause-code font-weight-800" style="font-size: 0.85rem;">AJI</span>
								<span style="font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1;">100%</span>
							</div>
							<div style="font-size: 1.15rem; font-weight: 800; color: #15803d; margin-top: 2px;">
								{{ $money($netValue) }}
							</div>
						</div>
					</div>
				</div>

				<div class="mt-4 pt-3 border-top" style="border-color: #f1f5f9 !important;">
					<div class="font-weight-700 text-dark small mb-1">Código de causal del registro.</div>
					<p class="text-muted small mb-1" style="line-height: 1.45;">
						Son movimientos administrativos de ajustes de inventario (conteos y correcciones).
					</p>
					<p class="text-muted small mb-0" style="line-height: 1.45;">
						Se separan de la merma física y no se suman dos veces.
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- D. PRODUCTOS CON MAYOR IMPACTO -->
	<div class="daily-aj-card">
		<div class="daily-aj-card-header">
			<div>
				<h6 class="daily-aj-card-title">
					<i class="fa-solid fa-cubes text-success mr-2"></i>Productos con mayor impacto
				</h6>
				<div class="text-muted small mt-0.5">Top de SKU por valor absoluto de ajuste.</div>
			</div>

			<div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
				<div class="btn-group btn-group-sm" role="group" aria-label="Filtro de productos por diferencia">
					<button type="button" class="btn btn-outline-success font-weight-600 active" id="btnAjFilterAll" onclick="dailyFilterAjustesSku('all');">
						Todos ({{ count($skuList) }})
					</button>
					<button type="button" class="btn btn-outline-secondary font-weight-600" id="btnAjFilterLoss" onclick="dailyFilterAjustesSku('faltante');">
						Faltantes ({{ $countFaltantes }})
					</button>
					<button type="button" class="btn btn-outline-secondary font-weight-600" id="btnAjFilterGain" onclick="dailyFilterAjustesSku('sobrante');">
						Sobrantes ({{ $countSobrantes }})
					</button>
				</div>

				<div class="input-group input-group-sm" style="max-width: 220px;">
					<div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-search text-muted"></i></span></div>
					<input type="text" class="form-control form-control-sm border-left-0" id="searchAjustesSku" placeholder="Buscar producto o SKU..." onkeyup="dailySearchAjustesSku();">
				</div>
			</div>
		</div>

		<!-- GRID DE 5 PRODUCTOS -->
		<div class="daily-oc-sku-grid" id="gridAjustesSkus">
			@forelse($skuList as $s)
				@php
					$isLoss = ($s['cost'] >= 0);
					$costAbs = abs($s['cost']);
					$unitsAbs = abs($s['units']);
					$skuCode = $s['sku'] ?: ('ID #' . $s['product_id']);
					$quLabel = $s['qu_name'] ?? 'unid.';
				@endphp
				<a href="javascript:void(0)" class="daily-oc-sku-card daily-aj-sku-item" data-type="{{ $isLoss ? 'faltante' : 'sobrante' }}" data-name="{{ mb_strtolower($s['name']) }}" data-sku="{{ mb_strtolower($skuCode) }}" onclick="dailyFilterAjustesBySku({{ $s['product_id'] }});" title="Ver detalle de {{ $s['name'] }}">
					<div class="daily-oc-sku-img-wrap" style="height: 110px; max-height: 110px; overflow: hidden; position: relative;">
						<img src="{{ $productPhoto($s) }}" alt="{{ $s['name'] }}" class="daily-oc-sku-img" style="height: 110px; max-height: 110px; width: 100%; object-fit: cover !important;" onerror="this.src='{{ $U('/img/icon-32.png') }}'; this.style.objectFit='contain'; this.style.padding='20px';">
						
						@if($isLoss)
							<span class="daily-aj-badge is-faltante">
								<span class="daily-aj-dot bg-danger"></span>Faltante
							</span>
						@else
							<span class="daily-aj-badge is-sobrante">
								<span class="daily-aj-dot bg-success"></span>Sobrante
							</span>
						@endif
					</div>
					<div class="daily-oc-sku-body">
						<div>
							<div class="daily-oc-sku-name" title="{{ $s['name'] }}">{{ $s['name'] }}</div>
							<div class="daily-oc-sku-code">{{ $skuCode }}</div>
						</div>
						<div class="daily-oc-sku-footer">
							<span class="daily-oc-sku-units {{ $isLoss ? 'text-danger' : 'text-success' }} font-weight-600">
								{{ ($isLoss ? '' : '-') . number_format($unitsAbs, 0) }} {{ $quLabel }}
							</span>
							<span class="daily-oc-sku-cost {{ $isLoss ? 'text-danger' : 'text-success' }} font-weight-800">
								{{ ($isLoss ? '' : '-') . $money($costAbs) }}
							</span>
						</div>
					</div>
				</a>
			@empty
				<div class="text-muted small py-4 text-center w-100">Sin productos con ajustes registrados en este período.</div>
			@endforelse
		</div>
	</div>

	<!-- E. DETALLE POR SKU -->
	<div class="daily-aj-card">
		<div class="daily-aj-card-header">
			<h6 class="daily-aj-card-title">
				<i class="fa-solid fa-table-list text-success mr-2"></i>Detalle por SKU
			</h6>
			<a href="javascript:void(0)" class="small font-weight-600 text-primary" id="btnToggleAllAjSku" onclick="dailyToggleAllRows('tableAjSku', this);">
				Ver todos
			</a>
		</div>

		<div class="table-responsive">
			<table class="daily-oc-table" id="tableAjSku" data-page-size="5" data-export-title="Ajustes de inventario por SKU">
				<thead>
					<tr>
						<th>SKU</th>
						<th>Producto</th>
						<th class="dlx-hide-sm">Código de barras</th>
						<th class="dlx-hide-sm">Depto.</th>
						<th class="text-right">Unid.</th>
						<th class="text-right">Costo</th>
						<th class="text-right" style="width: 80px;">Kardex</th>
					</tr>
				</thead>
				<tbody>
					@forelse($skuList as $r)
						@php
							$isLoss = ($r['cost'] >= 0);
							$skuCode = $r['sku'] ?: ('ID #' . $r['product_id']);
						@endphp
						<tr class="daily-aj-sku-row" data-product-id="{{ $r['product_id'] }}">
							<td class="font-mono small font-weight-600 text-dark">{{ $skuCode }}</td>
							<td class="font-weight-600 text-dark">
								<a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" class="text-dark" title="Kardex de {{ $r['name'] }}">
									{{ $r['name'] }}
								</a>
							</td>
							<td class="dlx-hide-sm small text-muted font-mono">{{ $r['barcode'] ?? '—' }}</td>
							<td class="dlx-hide-sm small text-muted">{{ $r['department'] ?? '—' }}</td>
							<td class="text-right font-weight-600 {{ $isLoss ? 'text-danger' : 'text-success' }}">
								{{ ($isLoss ? '' : '-') . number_format(abs($r['units']), 2) }}
							</td>
							<td class="text-right font-weight-700 {{ $isLoss ? 'text-danger' : 'text-success' }}">
								{{ ($isLoss ? '' : '-') . $money(abs($r['cost'])) }}
							</td>
							<td class="text-right">
								<a href="{{ $U('/kardex?product_id=' . $r['product_id']) }}" class="small font-weight-600 text-primary" title="Ver kardex">
									Kardex
								</a>
							</td>
						</tr>
					@empty
						<tr><td colspan="7" class="text-muted text-center py-3">Sin registros de SKU.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="mt-2.5 text-muted small d-flex align-items-center">
			<i class="fa-solid fa-circle-info text-primary mr-1.5" style="font-size: 0.85rem;"></i>
			<span>Convención del registro: positivo = faltante (pérdida), negativo = sobrante (a favor).</span>
		</div>

		<div class="daily-oc-table-footer">
			<span id="counterAjSku">Mostrando {{ min(5, count($skuList)) }} de {{ count($skuList) }} SKU</span>
			<div class="daily-oc-pagination" id="pagAjSku"></div>
		</div>
	</div>

	<!-- F. MOVIMIENTOS DE ORIGEN (ÚLTIMOS) -->
	<div class="daily-aj-card">
		<div class="daily-aj-card-header">
			<h6 class="daily-aj-card-title">
				<i class="fa-solid fa-list-check text-success mr-2"></i>Movimientos de origen (últimos)
			</h6>
			<div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
				<div class="input-group input-group-sm" style="max-width: 220px;">
					<div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-search text-muted"></i></span></div>
					<input type="text" class="form-control form-control-sm border-left-0" id="searchAjMovements" placeholder="Buscar producto, SKU o doc..." onkeyup="dailyFilterAjMovements();">
				</div>
				<select class="form-control form-control-sm border font-weight-600 text-dark" id="filterTypeAjMovements" style="width: auto; height: 31px;" onchange="dailyFilterAjMovements();">
					<option value="">Todas las diferencias</option>
					<option value="faltante">Faltantes</option>
					<option value="sobrante">Sobrantes</option>
				</select>
				<a href="javascript:void(0)" class="small font-weight-600 text-primary px-2" id="btnToggleAllAjMovements" onclick="dailyToggleAllRows('tableAjMovements', this);">
					Ver todos
				</a>
			</div>
		</div>

		<div class="table-responsive">
			<table class="daily-oc-table" id="tableAjMovements" data-page-size="5" data-export-title="Movimientos de origen - Ajustes">
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
						<th class="text-right" style="width: 80px;">Kardex</th>
					</tr>
				</thead>
				<tbody id="tbodyAjMovements">
					@forelse($recentList as $e)
						@php
							$pName = $names[$e['product_id']]['name'] ?? ('#' . $e['product_id']);
							$pSku = $names[$e['product_id']]['sku'] ?? '';
							$cName = $causeCatalog[$e['cause']] ?? $e['cause'];
							$isLoss = ($e['cost'] >= 0);
							$docLabel = $e['doc'] ?? 'Ajuste de stock';
							if ($e['src'] === 'adjustment' || (strlen($docLabel) > 10 && ctype_xdigit(substr($docLabel, 0, 10)))) {
								$docLabel = 'Ajuste de stock';
							}
						@endphp
						<tr class="daily-aj-mov-row" data-type="{{ $isLoss ? 'faltante' : 'sobrante' }}" data-product="{{ mb_strtolower($pName) }}" data-sku="{{ mb_strtolower($pSku) }}" data-doc="{{ mb_strtolower($docLabel) }}" data-uid="{{ mb_strtolower($e['uid']) }}">
							<td class="font-mono small font-weight-600 text-dark">{{ $e['uid'] }}</td>
							<td class="small text-muted">{{ $e['d'] }}</td>
							<td>
								<span class="daily-oc-pill-tag is-green" style="padding: 2px 7px; font-size: 0.72rem;">
									Ajustes de inventario
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
							<td class="text-right font-weight-600 {{ $isLoss ? 'text-danger' : 'text-success' }}">
								{{ ($isLoss ? '' : '-') . number_format(abs($e['units']), 2) }}
							</td>
							<td class="text-right font-weight-700 {{ $isLoss ? 'text-danger' : 'text-success' }}">
								{{ ($isLoss ? '' : '-') . $money(abs($e['cost'])) }}
							</td>
							<td>
								@if($e['src'] === 'waste')
									<a href="{{ $U('/document/waste/' . $e['ref']) }}" class="font-weight-600 text-primary">
										{{ $docLabel }}
									</a>
								@elseif($e['src'] === 'inventory_adjustment')
									<a href="{{ $U('/document/adjustment/' . $e['ref']) }}" class="font-weight-600 text-primary">
										{{ $docLabel }}
									</a>
								@else
									<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}" class="text-muted font-weight-500">
										{{ $docLabel }}
									</a>
								@endif
							</td>
							<td class="text-right">
								<a href="{{ $U('/kardex?product_id=' . $e['product_id']) }}" class="small font-weight-600 text-primary">
									Kardex
								</a>
							</td>
						</tr>
					@empty
						<tr><td colspan="9" class="text-muted text-center py-3">Sin movimientos de origen registrados en el período.</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div class="daily-oc-table-footer">
			<span id="counterAjMovements">Mostrando {{ min(5, count($recentList)) }} de {{ count($recentList) }} movimientos</span>
			<div class="daily-oc-pagination" id="pagAjMovements"></div>
		</div>
	</div>

	<!-- G. MÓDULOS CONECTADOS CON CENTRO DE CONTROL -->
	<div class="daily-aj-card">
		<div class="daily-aj-card-header">
			<h6 class="daily-aj-card-title">
				<i class="fa-solid fa-network-wired text-success mr-2"></i>Módulos conectados con Centro de Control
			</h6>
			@if(\Grocy\Helpers\DairyAccess::CanOpen('/system-map'))
				<a href="{{ $U('/system-map') }}" class="small font-weight-600 text-muted" title="Ver arquitectura de conexiones">
					Ver mapa del sistema <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			@endif
		</div>

		<div class="row">
			<div class="col-lg-5 mb-3 mb-lg-0">
				<div class="text-muted font-weight-700 text-uppercase small mb-2" style="font-size: 0.68rem; letter-spacing: 0.04em;">
					Recibe información de
				</div>
				<div class="d-flex flex-column gap-2">
					<a href="{{ $U('/quality-expiry') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon"><i class="fa-solid fa-snowflake"></i></div>
							<div>
								<div class="daily-oc-module-name">Calidad &amp; Vencimientos</div>
								<div class="daily-oc-module-sub">Rebajas por vencimiento (prevención antes de la merma)</div>
							</div>
						</div>
						<i class="fa-solid fa-arrow-right daily-oc-module-arrow"></i>
					</a>
					<a href="{{ $U('/workforce') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon"><i class="fa-solid fa-clipboard-list"></i></div>
							<div>
								<div class="daily-oc-module-name">Tareas y Roles</div>
								<div class="daily-oc-module-sub">Mermas desde Piso de Tienda</div>
							</div>
						</div>
						<i class="fa-solid fa-arrow-right daily-oc-module-arrow"></i>
					</a>
				</div>
			</div>

			<div class="col-lg-7">
				<div class="text-muted font-weight-700 text-uppercase small mb-2" style="font-size: 0.68rem; letter-spacing: 0.04em;">
					Actualiza / Alimenta a
				</div>
				<div class="daily-oc-modules-grid">
					<a href="{{ $U('/executive') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon"><i class="fa-solid fa-chart-line"></i></div>
							<div>
								<div class="daily-oc-module-name">Panel Ejecutivo</div>
								<div class="daily-oc-module-sub">Merma en el Panel Ejecutivo</div>
							</div>
						</div>
						<i class="fa-solid fa-arrow-right daily-oc-module-arrow"></i>
					</a>
					<a href="{{ $U('/cost-centers') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon"><i class="fa-solid fa-landmark"></i></div>
							<div>
								<div class="daily-oc-module-name">Centros de Costo</div>
								<div class="daily-oc-module-sub">Merma por centro de costo</div>
							</div>
						</div>
						<i class="fa-solid fa-arrow-right daily-oc-module-arrow"></i>
					</a>
					<a href="{{ $U('/reports') }}" class="daily-oc-module-link">
						<div class="daily-oc-module-info">
							<div class="daily-oc-module-icon"><i class="fa-solid fa-file-invoice"></i></div>
							<div>
								<div class="daily-oc-module-name">Reportes &amp; Analítica</div>
								<div class="daily-oc-module-sub">Desglose de merma (censo) en Reportes</div>
							</div>
						</div>
						<i class="fa-solid fa-arrow-right daily-oc-module-arrow"></i>
					</a>
				</div>
			</div>
		</div>
	</div>

</div>

{{-- SCRIPT CLIENT-SIDE INTERACTIVO PARA AJUSTES --}}
<script>
(function() {
	var curFilter = 'all';

	// 1. Filtro interactivo de SKU (Todos / Faltantes / Sobrantes)
	window.dailyFilterAjustesSku = function(type) {
		curFilter = type;
		$('#btnAjFilterAll').toggleClass('active btn-outline-success', type === 'all').toggleClass('btn-outline-secondary', type !== 'all');
		$('#btnAjFilterLoss').toggleClass('active btn-outline-success', type === 'faltante').toggleClass('btn-outline-secondary', type !== 'faltante');
		$('#btnAjFilterGain').toggleClass('active btn-outline-success', type === 'sobrante').toggleClass('btn-outline-secondary', type !== 'sobrante');
		dailyApplyAjustesSkuFilter();
	};

	// 2. Búsqueda de SKU
	window.dailySearchAjustesSku = function() {
		dailyApplyAjustesSkuFilter();
	};

	function dailyApplyAjustesSkuFilter() {
		var q = (document.getElementById('searchAjustesSku').value || '').toLowerCase().trim();
		var items = document.querySelectorAll('#gridAjustesSkus .daily-aj-sku-item');

		items.forEach(function(el) {
			var type = el.getAttribute('data-type');
			var name = el.getAttribute('data-name') || '';
			var sku = el.getAttribute('data-sku') || '';

			var matchType = (curFilter === 'all') || (type === curFilter);
			var matchQ = !q || (name.indexOf(q) !== -1 || sku.indexOf(q) !== -1);

			if (matchType && matchQ) {
				el.style.display = '';
			} else {
				el.style.display = 'none';
			}
		});
	}

	// 3. Filtrar tablas por SKU al seleccionar tarjeta
	window.dailyFilterAjustesBySku = function(productId) {
		var sel = $('#aj-f-sku');
		if (sel.length) {
			sel.val(productId);
			DairyLoss.applyFilters($('#dailyAjFilterForm'));
		}
	};

	// 4. Filtrar Movimientos
	window.dailyFilterAjMovements = function() {
		var q = (document.getElementById('searchAjMovements').value || '').toLowerCase().trim();
		var type = (document.getElementById('filterTypeAjMovements').value || '').trim();
		var rows = document.querySelectorAll('#tbodyAjMovements .daily-aj-mov-row');

		rows.forEach(function(row) {
			var rType = row.getAttribute('data-type') || '';
			var rProd = row.getAttribute('data-product') || '';
			var rSku = row.getAttribute('data-sku') || '';
			var rDoc = row.getAttribute('data-doc') || '';
			var rUid = row.getAttribute('data-uid') || '';

			var matchType = !type || (rType === type);
			var matchQ = !q || (rProd.indexOf(q) !== -1 || rSku.indexOf(q) !== -1 || rDoc.indexOf(q) !== -1 || rUid.indexOf(q) !== -1);

			if (matchType && matchQ) {
				row.classList.remove('d-none-filtered');
			} else {
				row.classList.add('d-none-filtered');
				row.style.display = 'none';
			}
		});

		initTablePagination('tableAjMovements', 'pagAjMovements', 'counterAjMovements', 'movimientos');
	};

	// 5. Paginación de tablas
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

				var prev = document.createElement('a');
				prev.className = 'daily-oc-page-btn' + (curPage === 1 ? ' text-muted disabled' : '');
				prev.innerHTML = '<i class="fa-solid fa-chevron-left" style="font-size: 0.65rem;"></i>';
				prev.onclick = function() { if (curPage > 1) render(curPage - 1); };
				pagEl.appendChild(prev);

				for (var p = 1; p <= totalPages; p++) {
					(function(pageNum) {
						var btn = document.createElement('a');
						btn.className = 'daily-oc-page-btn' + (pageNum === curPage ? ' is-active' : '');
						btn.innerText = pageNum;
						btn.onclick = function() { render(pageNum); };
						pagEl.appendChild(btn);
					})(p);
				}

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

	initTablePagination('tableAjSku', 'pagAjSku', 'counterAjSku', 'SKU');
	initTablePagination('tableAjMovements', 'pagAjMovements', 'counterAjMovements', 'movimientos');
})();
</script>
