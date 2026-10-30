@php
	$facLbl = [
		'rotation' => 'Alta rotación',
		'value' => 'Alto valor',
		'theft' => 'Hurto',
		'waste' => 'Merma histórica',
		'expiry' => 'Vence ≤ 7 días',
		'temperature' => 'Sensible a temp.',
		'differences' => 'Diferencias frecuentes',
		'reliability' => 'Stock poco confiable'
	];
	$facFullLbl = [
		'rotation' => 'Alta rotación en ventas (90 días)',
		'value' => 'Alto valor monetario / costo unitario',
		'theft' => 'Vulnerabilidad / eventos históricos de hurto',
		'waste' => 'Merma histórica acumulada',
		'expiry' => 'Existencias con vencimiento ≤ 7 días',
		'temperature' => 'Sensibilidad a cadena de frío (refrigerado/congelado)',
		'differences' => 'Diferencias frecuentes en conteos previos',
		'reliability' => 'Stock poco confiable (rotación activa sin conteo reciente)'
	];
	$weights = $rank['weights'] ?? ['rotation' => 10, 'value' => 15, 'theft' => 20, 'waste' => 15, 'expiry' => 10, 'temperature' => 10, 'differences' => 10, 'reliability' => 10];
	$prioCls = ['urgente' => 'badge-danger', 'alta' => 'badge-warning', 'media' => 'badge-secondary', 'baja' => 'badge-success'];
	$products = $rank['products'] ?? [];
	$evaluatedCount = $rank['evaluated'] ?? count($products);
	$counts = array_count_values(array_column($products, 'priority'));
	$countUrg = $counts['urgente'] ?? 0;
	$countAlt = $counts['alta'] ?? 0;
	$countMed = $counts['media'] ?? 0;
	$countBaj = $counts['baja'] ?? 0;
	$totalEvaluated = max(1, $evaluatedCount);

	$pctUrg = round(($countUrg / $totalEvaluated) * 100, 1);
	$pctAlt = round(($countAlt / $totalEvaluated) * 100, 1);
	$pctMed = round(($countMed / $totalEvaluated) * 100, 1);
	$pctBaj = round(($countBaj / $totalEvaluated) * 100, 1);

	$canSchedule = $canSchedule ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_STOCK_INVENTORY, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
	$canApprove = $canApprove ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_INVENTORY_ADJUST_APPROVE, \Grocy\Controllers\Users\User::PERMISSION_WASTE_APPROVE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
	$brOpts = !empty($branchesList) ? implode('', array_map(fn($b) => '<option value="' . $b['id'] . '"' . ($branchId == $b['id'] ? ' selected' : '') . '>' . e($b['name']) . '</option>', $branchesList)) : '';

	// Mapeo inteligente de fotografías para perecederos y productos clave
	$productImgMap = [
		30 => 'control_center/leche_entera_galon.jpg',
		15 => 'control_center/tomate.jpg',
		41 => 'control_center/queso_mozzarella.jpg',
		31 => 'control_center/leche_descremada.jpg',
		35 => 'control_center/huevos_grandes.jpg',
		34 => 'control_center/huevos_grandes.jpg',
		37 => 'control_center/yogur_griego.jpg',
		32 => 'control_center/leche_descremada.jpg',
		33 => 'control_center/leche_entera_galon.jpg',
		12 => 'perecederos/fruver_natural.jpg',
	];

	function dailyResolveProductImg($prod, $imgMap) {
		$id = $prod['product_id'] ?? 0;
		if (isset($imgMap[$id])) return $imgMap[$id];
		$n = mb_strtolower($prod['name'] ?? '');
		if (str_contains($n, 'leche entera')) return 'control_center/leche_entera_galon.jpg';
		if (str_contains($n, 'descremada')) return 'control_center/leche_descremada.jpg';
		if (str_contains($n, 'tomate')) return 'control_center/tomate.jpg';
		if (str_contains($n, 'mozzarella')) return 'control_center/queso_mozzarella.jpg';
		if (str_contains($n, 'huevo')) return 'control_center/huevos_grandes.jpg';
		if (str_contains($n, 'yogur') || str_contains($n, 'k\u00e9fir')) return 'control_center/yogur_griego.jpg';
		if (str_contains($n, 'pollo')) return 'control_center/pechuga_pollo.jpg';
		if (str_contains($n, 'carne') || str_contains($n, 'res')) return 'control_center/carne_picada.jpg';
		if (str_contains($n, 'queso') || str_contains($n, 'cheddar')) return 'control_center/queso_cheddar.jpg';
		if (str_contains($n, 'piment\u00f3n') || str_contains($n, 'fruta') || str_contains($n, 'verdura')) return 'perecederos/fruver_natural.jpg';
		return 'control_center/bebida_proteica.jpg';
	}

	$top4Products = array_slice($products, 0, 4);

	// Departamentos únicos para el filtro
	$allDepts = array_values(array_unique(array_filter(array_column($products, 'department'))));
	sort($allDepts);

	// KPIs de conteos
	$iraPct = $cycleCountsKpi['ira_pct'] ?? $cycleCountsKpi['ira_percentage'] ?? null;
	$completedCounts = $cycleCountsKpi['completed'] ?? 2;
	$discrepanciesCounts = $cycleCountsKpi['discrepancies'] ?? 2;
	$pendingCounts = $cycleCountsKpi['pending'] ?? 2;
	$evaluatedCountsTotal = $completedCounts + $discrepanciesCounts;
	$diffCostVal = $cycleCountsKpi['discrepancy_cost'] ?? null;
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER - PESTAÑA SELECTIVOS Y SENSIBLES
   ========================================================================== */
.daily-sensibles-wrapper {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #1e293b;
	background-color: #f8fafc;
	padding: 1rem 0 2rem 0;
}
.daily-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
	transition: all 0.2s ease;
}
.daily-card:hover {
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.daily-action-btn-green {
	background-color: #00C83B;
	border-color: #00C83B;
	color: #ffffff !important;
	font-weight: 600;
	border-radius: 8px;
	padding: 0.45rem 1rem;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	transition: background-color 0.15s ease-in-out, transform 0.1s ease;
}
.daily-action-btn-green:hover {
	background-color: #00a832;
	border-color: #00a832;
	transform: translateY(-1px);
}
.daily-btn-secondary {
	background-color: #ffffff;
	border: 1px solid #cbd5e1;
	color: #334155 !important;
	font-weight: 600;
	border-radius: 8px;
	padding: 0.45rem 0.9rem;
	display: inline-flex;
	align-items: center;
	gap: 0.4rem;
	transition: all 0.15s ease;
}
.daily-btn-secondary:hover {
	background-color: #f1f5f9;
	border-color: #94a3b8;
}

/* Priority Cards */
.daily-prio-box {
	border-radius: 10px;
	padding: 0.75rem 0.5rem;
	text-align: center;
	cursor: pointer;
	transition: all 0.2s ease;
	user-select: none;
}
.daily-prio-box:hover {
	transform: translateY(-2px);
}
.daily-prio-box.active-filter {
	box-shadow: 0 0 0 2px #00C83B !important;
}
.daily-prio-box.urgente {
	background: #fef2f2;
	border: 1px solid #fca5a5;
	color: #dc2626;
}
.daily-prio-box.alta {
	background: #fffbeb;
	border: 1px solid #fcd34d;
	color: #d97706;
}
.daily-prio-box.media {
	background: #f8fafc;
	border: 1px solid #cbd5e1;
	color: #475569;
}
.daily-prio-box.baja {
	background: #f0fdf4;
	border: 1px solid #86efac;
	color: #16a34a;
}

/* Risk segmented bar */
.daily-segmented-bar {
	height: 14px;
	border-radius: 7px;
	display: flex;
	overflow: hidden;
	background-color: #e2e8f0;
}
.daily-seg-urgente { background-color: #ef4444; }
.daily-seg-alta { background-color: #f59e0b; }
.daily-seg-media { background-color: #94a3b8; }
.daily-seg-baja { background-color: #10b981; }

/* Product Cards */
.daily-prod-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.daily-prod-card:hover {
	transform: translateY(-3px);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}
.daily-prod-img-fade {
	position: absolute;
	right: -5px;
	top: 0;
	height: 105px;
	width: 145px;
	object-fit: contain;
	pointer-events: none;
	opacity: 0.88;
	-webkit-mask-image: linear-gradient(to left, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
	mask-image: linear-gradient(to left, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
	z-index: 1;
}
.daily-tag-pill {
	display: inline-block;
	font-size: 0.72rem;
	font-weight: 500;
	padding: 0.2rem 0.5rem;
	border-radius: 6px;
	margin-right: 0.3rem;
	margin-bottom: 0.3rem;
	background-color: #f1f5f9;
	color: #475569;
	border: 1px solid #e2e8f0;
}

/* Table styling */
.daily-sku-table th {
	background-color: #f8fafc;
	color: #64748b;
	font-size: 0.75rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	border-top: none;
	border-bottom: 1px solid #e2e8f0;
	padding: 0.75rem 0.6rem;
}
.daily-sku-table td {
	font-size: 0.83rem;
	vertical-align: middle;
	padding: 0.75rem 0.6rem;
	border-bottom: 1px solid #f1f5f9;
}
.daily-sku-table tbody tr:hover {
	background-color: #f8fafc;
}

/* KPI Chips */
.daily-kpi-chip {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 0.75rem 1rem;
	display: flex;
	align-items: center;
	gap: 0.85rem;
	flex: 1;
	min-width: 180px;
}
.daily-chip-icon {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.15rem;
}

/* Connected module cards */
.daily-conn-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 0.85rem 1rem;
	display: flex;
	align-items: center;
	gap: 0.75rem;
	text-decoration: none !important;
	color: inherit !important;
	transition: all 0.15s ease;
}
.daily-conn-card:hover {
	border-color: #00C83B;
	background: #f0fdf4;
	transform: translateY(-2px);
}
</style>

<div class="daily-sensibles-wrapper">
	<!-- ======================================================================
	     A. ENCABEZADO Y ACCIONES PRINCIPALES
	     ====================================================================== -->
	<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 px-1">
		<div class="d-flex align-items-center gap-3">
			<div class="p-2 rounded-lg bg-white border shadow-xs d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; border-radius: 12px;">
				<i class="fa-solid fa-clipboard-check text-success" style="font-size: 1.4rem;"></i>
			</div>
			<div>
				<h4 class="font-weight-bold text-dark mb-0" style="letter-spacing: -0.02em;">Inventarios selectivos y sensibles</h4>
				<p class="text-muted small mb-0">Prioriza productos, programa conteos y concilia diferencias.</p>
			</div>
		</div>

		<div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
			@if($canSchedule)
			<button class="daily-action-btn-green" onclick="dailyOpenScheduleModal();">
				<i class="fa-solid fa-calendar-check"></i>
				<span>Programar conteo</span>
			</button>
			@endif
			<a class="daily-btn-secondary" href="{{ $U('/scan') }}">
				<i class="fa-solid fa-barcode text-secondary"></i>
				<span>Contar con escáner</span>
			</a>
			<div class="bg-white border rounded-lg px-3 py-1 text-center shadow-xs ml-1" style="border-radius: 10px;">
				<div class="font-weight-bold text-dark h5 mb-0" style="line-height: 1.1;">{{ $totalEvaluated }}</div>
				<div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">SKU evaluados</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     B & C. PRIORIDADES, DISTRIBUCIÓN DE RIESGO Y MODELO DE PUNTUACIÓN
	     ====================================================================== -->
	<div class="row row-cols-1 row-cols-lg-3 mb-4">
		<!-- CARD 1: Prioridades de conteo -->
		<div class="col mb-3 mb-lg-0">
			<div class="daily-card p-3 h-100 d-flex flex-column justify-content-between">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div class="d-flex align-items-center gap-2">
						<i class="fa-solid fa-shield-halved text-success"></i>
						<span class="font-weight-bold text-dark small">Prioridades de conteo</span>
					</div>
					<button class="btn btn-link btn-xs text-muted p-0" onclick="dailyFilterByPriority('');" title="Restablecer filtro">Limpiar</button>
				</div>
				<div class="d-flex gap-2 justify-content-between">
					<div class="daily-prio-box urgente flex-fill px-1" id="prio-box-urgente" onclick="dailyFilterByPriority('urgente');">
						<div class="h3 font-weight-bold mb-0" style="line-height: 1;">{{ $countUrg }}</div>
						<div class="font-weight-bold text-xs" style="font-size: 0.78rem;">Urgente</div>
						<div class="text-xs opacity-75 mt-1" style="font-size: 0.68rem;">Cada 7 días</div>
					</div>
					<div class="daily-prio-box alta flex-fill px-1" id="prio-box-alta" onclick="dailyFilterByPriority('alta');">
						<div class="h3 font-weight-bold mb-0" style="line-height: 1;">{{ $countAlt }}</div>
						<div class="font-weight-bold text-xs" style="font-size: 0.78rem;">Alta</div>
						<div class="text-xs opacity-75 mt-1" style="font-size: 0.68rem;">Cada 14 días</div>
					</div>
					<div class="daily-prio-box media flex-fill px-1" id="prio-box-media" onclick="dailyFilterByPriority('media');">
						<div class="h3 font-weight-bold mb-0" style="line-height: 1;">{{ $countMed }}</div>
						<div class="font-weight-bold text-xs" style="font-size: 0.78rem;">Media</div>
						<div class="text-xs opacity-75 mt-1" style="font-size: 0.68rem;">Cada 30 días</div>
					</div>
					<div class="daily-prio-box baja flex-fill px-1" id="prio-box-baja" onclick="dailyFilterByPriority('baja');">
						<div class="h3 font-weight-bold mb-0" style="line-height: 1;">{{ $countBaj }}</div>
						<div class="font-weight-bold text-xs" style="font-size: 0.78rem;">Baja</div>
						<div class="text-xs opacity-75 mt-1" style="font-size: 0.68rem;">Cada 90 días</div>
					</div>
				</div>
			</div>
		</div>

		<!-- CARD 2: Distribución de riesgo -->
		<div class="col mb-3 mb-lg-0">
			<div class="daily-card p-3 h-100 d-flex flex-column justify-content-between">
				<div>
					<div class="d-flex align-items-center gap-2 mb-2">
						<i class="fa-solid fa-chart-simple text-success"></i>
						<span class="font-weight-bold text-dark small">Distribución de riesgo ({{ $totalEvaluated }} SKU)</span>
					</div>

					<!-- Segmented bar -->
					<div class="daily-segmented-bar my-3">
						<div class="daily-seg-urgente" style="width: {{ $pctUrg }}%;" title="Urgente: {{ $countUrg }} SKU ({{ $pctUrg }}%)"></div>
						<div class="daily-seg-alta" style="width: {{ $pctAlt }}%;" title="Alta: {{ $countAlt }} SKU ({{ $pctAlt }}%)"></div>
						<div class="daily-seg-media" style="width: {{ $pctMed }}%;" title="Media: {{ $countMed }} SKU ({{ $pctMed }}%)"></div>
						<div class="daily-seg-baja" style="width: {{ $pctBaj }}%;" title="Baja: {{ $countBaj }} SKU ({{ $pctBaj }}%)"></div>
					</div>
				</div>

				<!-- Legend -->
				<div class="row text-center small text-muted font-weight-600 g-1">
					<div class="col-3">
						<div class="d-flex align-items-center justify-content-center gap-1">
							<span style="color: #ef4444; font-size: 1.1rem; line-height: 0;">●</span>
							<span class="text-dark">{{ $countUrg }}</span>
						</div>
						<div class="text-xs text-muted" style="font-size: 0.72rem;">Urgente</div>
					</div>
					<div class="col-3">
						<div class="d-flex align-items-center justify-content-center gap-1">
							<span style="color: #f59e0b; font-size: 1.1rem; line-height: 0;">●</span>
							<span class="text-dark">{{ $countAlt }}</span>
						</div>
						<div class="text-xs text-muted" style="font-size: 0.72rem;">Alta</div>
					</div>
					<div class="col-3">
						<div class="d-flex align-items-center justify-content-center gap-1">
							<span style="color: #94a3b8; font-size: 1.1rem; line-height: 0;">●</span>
							<span class="text-dark">{{ $countMed }}</span>
						</div>
						<div class="text-xs text-muted" style="font-size: 0.72rem;">Media</div>
					</div>
					<div class="col-3">
						<div class="d-flex align-items-center justify-content-center gap-1">
							<span style="color: #10b981; font-size: 1.1rem; line-height: 0;">●</span>
							<span class="text-dark">{{ $countBaj }}</span>
						</div>
						<div class="text-xs text-muted" style="font-size: 0.72rem;">Baja</div>
					</div>
				</div>
			</div>
		</div>

		<!-- CARD 3: Modelo de riesgo del inventario -->
		<div class="col">
			<div class="daily-card p-3 h-100 d-flex flex-column justify-content-between">
				<div class="d-flex align-items-center justify-content-between mb-1">
					<div class="d-flex align-items-center gap-2">
						<i class="fa-solid fa-gauge-high text-success"></i>
						<span class="font-weight-bold text-dark small">Modelo de riesgo del inventario</span>
					</div>
					<button class="btn btn-link btn-xs text-muted p-0" onclick="$('#modalExplainModel').modal('show');" title="Ver explicación completa">
						<i class="fa-solid fa-circle-info"></i>
					</button>
				</div>

				<div class="d-flex align-items-center justify-content-between">
					<!-- Gauge SVG representation -->
					<div class="text-center pr-2" style="min-width: 95px;">
						<svg viewBox="0 0 100 55" width="85" height="48">
							<defs>
								<linearGradient id="dailyRiskGaugeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
									<stop offset="0%" stop-color="#10b981" />
									<stop offset="45%" stop-color="#f59e0b" />
									<stop offset="100%" stop-color="#ef4444" />
								</linearGradient>
							</defs>
							<path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#e2e8f0" stroke-width="8" stroke-linecap="round" />
							<path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="url(#dailyRiskGaugeGrad)" stroke-width="8" stroke-linecap="round" stroke-dasharray="125" stroke-dashoffset="30" />
						</svg>
						<div class="mt-n2">
							<div class="text-muted" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">Riesgo</div>
							<div class="font-weight-bold text-dark small" style="line-height: 1;">0 – 100</div>
						</div>
					</div>

					<!-- Factor list -->
					<div class="flex-grow-1 pl-2 border-left" style="font-size: 0.70rem; line-height: 1.3;">
						<div class="font-weight-bold text-dark mb-1">Factores del puntaje</div>
						<div class="d-flex justify-content-between text-muted"><span>Alta rotación</span> <strong class="text-dark">10%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Alto valor</span> <strong class="text-dark">15%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Hurto</span> <strong class="text-dark">20%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Merma histórica</span> <strong class="text-dark">15%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Vence ≤ 7 días</span> <strong class="text-dark">10%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Sensible a temperatura</span> <strong class="text-dark">10%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Diferencias frecuentes</span> <strong class="text-dark">10%</strong></div>
						<div class="d-flex justify-content-between text-muted"><span>Stock poco confiable</span> <strong class="text-dark">10%</strong></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     D. PRODUCTOS CON MAYOR PRIORIDAD DE CONTEO (TOP 4 CARDS)
	     ====================================================================== -->
	<div class="d-flex justify-content-between align-items-center mb-3 px-1">
		<div class="d-flex align-items-center gap-2">
			<i class="fa-solid fa-boxes-stacked text-success"></i>
			<h6 class="font-weight-bold text-dark mb-0">Productos en mayor prioridad de conteo</h6>
		</div>
		<a href="#dailySkuTableSection" class="text-success small font-weight-600 text-decoration-none">
			Ver todos los {{ $totalEvaluated }} SKU <i class="fa-solid fa-arrow-right ml-1"></i>
		</a>
	</div>

	<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 mb-4">
		@foreach($top4Products as $p)
		@php
			$imgSrc = dailyResolveProductImg($p, $productImgMap);
			$prioBadgeClass = $p['priority'] === 'urgente' ? 'bg-danger text-white' : ($p['priority'] === 'alta' ? 'bg-warning text-dark font-weight-bold' : 'bg-secondary text-white');
		@endphp
		<div class="col mb-3 mb-lg-0">
			<div class="daily-prod-card p-3 shadow-xs">
				<!-- Fade Image -->
				<img src="{{ $U('/img/' . $imgSrc) }}" alt="{{ $p['name'] }}" class="daily-prod-img-fade" onerror="this.style.display='none';">

				<div style="position: relative; z-index: 2;">
					<!-- Score and Priority Badge -->
					<div class="d-flex justify-content-between align-items-start mb-2">
						<div>
							<span class="h4 font-weight-bold text-dark mb-0">{{ number_format($p['score'], 1) }}</span>
							<span class="text-muted small">/ 100</span>
						</div>
						<span class="badge {{ $prioBadgeClass }} text-capitalize px-2 py-1" style="border-radius: 6px; font-size: 0.72rem;">
							● {{ $p['priority'] }}
						</span>
					</div>

					<!-- Product Title and Dept -->
					<div class="mb-3 pr-4">
						<h6 class="font-weight-bold text-dark mb-0 text-truncate" title="{{ $p['name'] }}">{{ $p['name'] }}</h6>
						<div class="text-muted text-xs text-truncate" style="font-size: 0.74rem;">
							@if(!empty($p['sku']))<span class="font-mono">{{ $p['sku'] }}</span> · @endif{{ $p['department'] }}
						</div>
					</div>

					<!-- Stock and Last Count -->
					<div class="row g-2 mb-2 py-1 px-2 rounded bg-light border-0" style="background-color: #f8fafc !important;">
						<div class="col-6">
							<div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Stock</div>
							<div class="font-weight-bold text-dark small">{{ floatval($p['stock_units']) }} <span class="text-muted font-weight-normal">{{ $p['qu_name'] ?? 'u.' }}</span></div>
						</div>
						<div class="col-6">
							<div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Último conteo</div>
							<div class="small font-weight-500">
								@if(!empty($p['last_count']))
									<span class="text-dark">{{ $p['last_count'] }}</span>
								@else
									<span class="text-danger" style="font-size: 0.72rem;">Sin conteo registrado</span>
								@endif
							</div>
						</div>
					</div>

					<!-- Reasons tags -->
					<div class="mb-3" style="min-height: 48px;">
						@forelse($p['reasons'] as $rKey)
							<span class="daily-tag-pill">{{ $facLbl[$rKey] ?? $rKey }}</span>
						@empty
							<span class="text-muted small">Sin factores críticos</span>
						@endforelse
					</div>
				</div>

				<!-- Actions -->
				<div class="d-flex gap-2 pt-2 border-top mt-auto" style="position: relative; z-index: 2;">
					<button class="btn btn-sm btn-outline-secondary flex-grow-1 font-weight-600" style="font-size: 0.75rem; border-radius: 8px;" onclick='dailyShowProductRiskDetail({!! json_encode($p) !!});'>
						Ver detalle
					</button>
					@if($canSchedule)
					<button class="btn btn-sm daily-action-btn-green px-3" style="font-size: 0.75rem; border-radius: 8px;" onclick="dailyOpenScheduleModal({{ $p['product_id'] }}, '{{ addslashes($p['name']) }}');">
						<i class="fa-solid fa-plus mr-1"></i>Programar
					</button>
					@endif
				</div>
			</div>
		</div>
		@endforeach
	</div>

	<!-- ======================================================================
	     E. LISTADO COMPLETO DE SKU CON BÚSQUEDA Y PAGINACIÓN
	     ====================================================================== -->
	<div class="daily-card p-3 mb-4" id="dailySkuTableSection">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
			<div class="d-flex align-items-center gap-2">
				<i class="fa-solid fa-clipboard-list text-success"></i>
				<h6 class="font-weight-bold text-dark mb-0">Listado de inventarios selectivos y sensibles</h6>
			</div>

			<div class="d-flex align-items-center flex-wrap gap-2">
				<!-- Search -->
				<div class="input-group input-group-sm" style="width: 220px;">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
					</div>
					<input type="text" class="form-control border-left-0" id="dailySkuSearchInput" placeholder="Buscar SKU o producto..." oninput="dailyApplySkuFilters();">
				</div>

				<!-- Filter priority -->
				<select class="form-control form-control-sm" id="dailySkuPriorityFilter" style="width: 160px;" onchange="dailyApplySkuFilters();">
					<option value="">Todas las prioridades</option>
					<option value="urgente">Urgente ({{ $countUrg }})</option>
					<option value="alta">Alta ({{ $countAlt }})</option>
					<option value="media">Media ({{ $countMed }})</option>
					<option value="baja">Baja ({{ $countBaj }})</option>
				</select>

				<!-- Filter department -->
				<select class="form-control form-control-sm" id="dailySkuDeptFilter" style="width: 180px;" onchange="dailyApplySkuFilters();">
					<option value="">Todos los departamentos</option>
					@foreach($allDepts as $dept)
					<option value="{{ $dept }}">{{ $dept }}</option>
					@endforeach
				</select>

				<!-- Table counter -->
				<div class="text-muted small ml-1" id="dailySkuCountDisplay">
					Mostrando <span id="dailySkuDisplayRange">1-7</span> de {{ $totalEvaluated }} SKU
				</div>

				<!-- Pagination buttons -->
				<div class="btn-group btn-group-sm ml-1" id="dailySkuPaginationControls">
					<!-- Injected dynamically via JS -->
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table daily-sku-table mb-0" id="dailySkuMainTable">
				<thead>
					<tr>
						<th style="width: 40px;">#</th>
						<th style="width: 120px;">SKU</th>
						<th>Producto</th>
						<th>Depto.</th>
						<th class="text-center" style="width: 110px;">Puntaje</th>
						<th style="width: 95px;">Prioridad</th>
						<th style="width: 115px;">Frecuencia</th>
						<th>Principales Motivos</th>
						<th class="text-right" style="width: 90px;">Stock</th>
						<th style="width: 130px;">Último Conteo</th>
						<th class="text-right" style="width: 95px;">Acción</th>
					</tr>
				</thead>
				<tbody id="dailySkuTableBody">
					<!-- Injected via dailyRenderSkuRows() -->
				</tbody>
			</table>
		</div>
	</div>

	<!-- ======================================================================
	     F. CONTEOS CÍCLICOS SENSIBLES Y CONCILIACIÓN IRA
	     ====================================================================== -->
	<div class="daily-card p-3 mb-4">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
			<div class="d-flex align-items-center gap-2">
				<i class="fa-solid fa-rotate text-success" style="font-size: 1.1rem;"></i>
				<h6 class="font-weight-bold text-dark mb-0">Conteos Cíclicos Sensibles y Conciliación IRA</h6>
			</div>

			<div class="d-flex align-items-center gap-2">
				@if($canSchedule)
				<button class="daily-btn-secondary" onclick="dailyOpenAbcScheduleModal();">
					<i class="fa-solid fa-calendar-days text-success"></i>
					<span>Programar por ABC</span>
				</button>
				<button class="daily-action-btn-green py-1 px-3" onclick="dailyOpenScheduleModal();">
					<i class="fa-solid fa-plus"></i>
					<span>Programar conteo</span>
				</button>
				@endif
			</div>
		</div>

		<!-- 5 KPI Chips -->
		<div class="d-flex flex-wrap gap-2 mb-3">
			<!-- Exactitud IRA -->
			<div class="daily-kpi-chip">
				<div class="daily-chip-icon bg-success-light text-success" style="background-color: #dcfce7; color: #16a34a;">
					<i class="fa-solid fa-circle-check"></i>
				</div>
				<div>
					<div class="text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem; font-weight: 600;">
						<span>Exactitud</span>
						<i class="fa-solid fa-circle-info text-muted" data-toggle="tooltip" title="IRA = Conteos exactos ({{ $completedCounts }}) ÷ Conteos completados ({{ $evaluatedCountsTotal }}). Excluye pendientes del denominador."></i>
					</div>
					<div class="h5 font-weight-bold text-dark mb-0">{{ $iraPct === null ? 'Sin información' : number_format($iraPct, 0) . '%' }}</div>
					<div class="text-muted" style="font-size: 0.68rem;">Base: {{ $evaluatedCountsTotal }} conteos completados, {{ $completedCounts }} sin diferencias</div>
				</div>
			</div>

			<!-- Diferencias detectadas -->
			<div class="daily-kpi-chip">
				<div class="daily-chip-icon bg-danger-light text-danger" style="background-color: #fee2e2; color: #dc2626;">
					<i class="fa-solid fa-triangle-exclamation"></i>
				</div>
				<div>
					<div class="text-muted" style="font-size: 0.72rem; font-weight: 600;">Diferencias detectadas</div>
					<div class="h5 font-weight-bold text-danger mb-0">{{ $diffCostVal === null ? 'Sin información' : '-$ ' . number_format($diffCostVal, 2) }}</div>
					<div class="text-danger font-weight-500" style="font-size: 0.68rem;">
						<i class="fa-solid fa-circle-exclamation mr-1"></i>Pendientes de aprobación
					</div>
				</div>
			</div>

			<!-- Pendientes -->
			<div class="daily-kpi-chip">
				<div class="daily-chip-icon bg-warning-light text-warning" style="background-color: #fef3c7; color: #d97706;">
					<i class="fa-solid fa-clock"></i>
				</div>
				<div>
					<div class="h5 font-weight-bold text-dark mb-0">{{ $pendingCounts }} <span class="font-weight-600 text-muted small">Pendientes</span></div>
					<div class="text-muted" style="font-size: 0.68rem;">Programados</div>
				</div>
			</div>

			<!-- Con diferencias -->
			<div class="daily-kpi-chip">
				<div class="daily-chip-icon bg-danger-light text-danger" style="background-color: #fee2e2; color: #dc2626;">
					<i class="fa-solid fa-magnifying-glass"></i>
				</div>
				<div>
					<div class="h5 font-weight-bold text-danger mb-0">{{ $discrepanciesCounts }} <span class="font-weight-600 text-muted small">Con diferencias</span></div>
					<div class="text-muted" style="font-size: 0.68rem;">Requieren aprobación</div>
				</div>
			</div>

			<!-- Conciliados -->
			<div class="daily-kpi-chip">
				<div class="daily-chip-icon bg-success-light text-success" style="background-color: #dcfce7; color: #16a34a;">
					<i class="fa-solid fa-check"></i>
				</div>
				<div>
					<div class="h5 font-weight-bold text-success mb-0">{{ $completedCounts }} <span class="font-weight-600 text-muted small">Conciliados</span></div>
					<div class="text-muted" style="font-size: 0.68rem;">Sin diferencias</div>
				</div>
			</div>
		</div>

		<!-- Cycle Counts Table -->
		<div class="table-responsive">
			<table class="table daily-sku-table mb-0">
				<thead>
					<tr>
						<th style="width: 100px;">Nº CONTEO</th>
						<th style="width: 110px;">FECHA</th>
						<th>PRODUCTO</th>
						<th class="text-right" style="width: 120px;">STOCK SISTEMA</th>
						<th class="text-right" style="width: 110px;">STOCK FÍSICO</th>
						<th class="text-right" style="width: 100px;">DIFERENCIA</th>
						<th class="text-right" style="width: 110px;">IMPACTO ($)</th>
						<th style="width: 110px;">ESTADO</th>
						<th class="text-right" style="width: 140px;">ACCIONES</th>
					</tr>
				</thead>
				<tbody>
					@forelse($cycleCounts as $cc)
					@php
						$isPending = ($cc['status'] === 'pending');
						$isDiscrepancy = in_array($cc['status'], ['discrepancy']);
						$isCompleted = ($cc['status'] === 'completed');
						$isAdjusted = ($cc['status'] === 'adjusted');
						$diff = floatval($cc['difference_amount'] ?? 0);
						$cost = floatval($cc['difference_cost'] ?? 0);
					@endphp
					<tr>
						<td class="font-mono font-weight-600 text-dark">CC-{{ str_pad($cc['id'], 4, '0', STR_PAD_LEFT) }}</td>
						<td class="text-muted small">{{ $cc['scheduled_date'] }}</td>
						<td>
							<span class="font-weight-600 text-dark">{{ $cc['product_name'] }}</span>
							@if(!empty($cc['location_name']))<span class="text-muted small ml-1">({{ $cc['location_name'] }})</span>@endif
						</td>

						<!-- Stock Sistema (Conteo Ciego para pendientes) -->
						<td class="text-right font-mono">
							@if($isPending)
								<span class="text-muted small"><i class="fa-solid fa-eye-slash mr-1"></i>Oculto</span>
							@else
								<span class="font-weight-600 text-dark">{{ floatval($cc['theoretical_stock']) }}</span>
							@endif
						</td>

						<!-- Stock Físico -->
						<td class="text-right font-mono">
							@if($isPending)
								<span class="text-muted">—</span>
							@else
								<span class="font-weight-600 text-dark">{{ floatval($cc['counted_stock']) }}</span>
							@endif
						</td>

						<!-- Diferencia -->
						<td class="text-right font-mono">
							@if($isPending)
								<span class="text-muted">—</span>
							@elseif(abs($diff) < 0.001)
								<span class="text-success font-weight-bold">0</span>
							@else
								<span class="text-danger font-weight-bold">{{ $diff > 0 ? '+' : '' }}{{ $diff }}</span>
							@endif
						</td>

						<!-- Impacto ($) -->
						<td class="text-right font-mono">
							@if($isPending)
								<span class="text-muted">—</span>
							@elseif(abs($cost) < 0.001)
								<span class="text-muted">$ 0.00</span>
							@else
								<span class="text-danger font-weight-bold">$ {{ number_format($cost, 2) }}</span>
							@endif
						</td>

						<!-- Estado -->
						<td>
							@if($isPending)
								<span class="badge badge-warning px-2 py-1 font-weight-600">Pendiente</span>
							@elseif($isDiscrepancy)
								<span class="badge badge-danger px-2 py-1 font-weight-600">Diferencia</span>
							@elseif($isCompleted)
								<span class="badge badge-success px-2 py-1 font-weight-600">Conciliado</span>
							@elseif($isAdjusted)
								<span class="badge badge-info px-2 py-1 font-weight-600">Ajustado</span>
							@endif
						</td>

						<!-- Acciones -->
						<td class="text-right">
							@if($isPending)
								<button class="btn btn-xs btn-outline-success font-weight-600" onclick="dailyOpenExecuteCount({{ $cc['id'] }}, '{{ addslashes($cc['product_name']) }}', '{{ $cc['qu_name'] ?? 'u.' }}');">
									<i class="fa-solid fa-pen-to-square mr-1"></i>Registrar conteo
								</button>
							@elseif($isDiscrepancy)
								@if($canApprove)
								<button class="btn btn-xs btn-outline-danger font-weight-600" onclick="dailyOpenApproveAdjustment({{ $cc['id'] }}, '{{ addslashes($cc['product_name']) }}', {{ $diff }}, {{ $cost }});">
									Aprobar ajuste <i class="fa-solid fa-chevron-down ml-1"></i>
								</button>
								@else
								<span class="text-muted small">Requiere supervisor</span>
								@endif
							@else
								<span class="text-muted" title="Conteo cerrado y conciliado"><i class="fa-solid fa-lock"></i></span>
							@endif
						</td>
					</tr>
					@empty
					<tr>
						<td colspan="9" class="text-center text-muted py-4">No hay conteos cíclicos registrados en la sucursal activa.</td>
					</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<!-- Footer Alerts -->
		<div class="row g-2 mt-3 pt-2 border-top">
			<div class="col-md-6">
				<div class="d-flex align-items-center small text-muted">
					<i class="fa-solid fa-circle-info text-warning mr-2" style="font-size: 1rem;"></i>
					<span><strong>Conteo ciego:</strong> stock del sistema oculto hasta completar el registro.</span>
				</div>
			</div>
			<div class="col-md-6">
				<div class="d-flex align-items-center small text-muted">
					<i class="fa-solid fa-circle-info text-secondary mr-2" style="font-size: 1rem;"></i>
					<span>Diferencia de conteo no implica merma física; ajustes requieren aprobación.</span>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     G. MÓDULOS CONECTADOS CON CENTRO DE CONTROL
	     ====================================================================== -->
	<div class="mb-2 px-1">
		<div class="d-flex align-items-center gap-2 mb-2">
			<i class="fa-solid fa-network-wired text-success"></i>
			<span class="font-weight-bold text-dark small">Módulos conectados con Centro de Control</span>
		</div>
		<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-5 g-2">
			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/quality-expiry') }}" class="daily-conn-card">
					<i class="fa-solid fa-snowflake text-primary" style="font-size: 1.25rem;"></i>
					<div>
						<div class="font-weight-bold text-dark small">Calidad & Vencimientos</div>
						<div class="text-muted text-xs" style="font-size: 0.72rem;">Rebajas por vencimiento (prevención)</div>
					</div>
				</a>
			</div>
			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/workforce') }}" class="daily-conn-card">
					<i class="fa-solid fa-list-check text-success" style="font-size: 1.25rem;"></i>
					<div>
						<div class="font-weight-bold text-dark small">Tareas y Roles</div>
						<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma exprés desde Piso de Tienda</div>
					</div>
				</a>
			</div>
			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/executive-dashboard') }}" class="daily-conn-card">
					<i class="fa-solid fa-chart-pie text-warning" style="font-size: 1.25rem;"></i>
					<div>
						<div class="font-weight-bold text-dark small">Panel Ejecutivo</div>
						<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma, margen y proyecciones</div>
					</div>
				</a>
			</div>
			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/cost-centers') }}" class="daily-conn-card">
					<i class="fa-solid fa-building text-info" style="font-size: 1.25rem;"></i>
					<div>
						<div class="font-weight-bold text-dark small">Centros de Costo</div>
						<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma por centro de costo</div>
					</div>
				</a>
			</div>
			<div class="col">
				<a href="{{ $U('/reports-hub') }}" class="daily-conn-card">
					<i class="fa-solid fa-file-lines text-secondary" style="font-size: 1.25rem;"></i>
					<div>
						<div class="font-weight-bold text-dark small">Reportes & Analítica</div>
						<div class="text-muted text-xs" style="font-size: 0.72rem;">Desglose de merma (censo)</div>
					</div>
				</a>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODALS INTERACTIVOS
     ========================================================================== -->

<!-- 1. Modal Explicación del Modelo de Riesgo -->
<div class="modal fade" id="modalExplainModel" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-circle-info text-success mr-2"></i>Modelo de Evaluación y Riesgo de Inventario
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<p class="text-muted">
					El algoritmo del Centro de Control DAILY evalúa diariamente cada SKU mediante una escala objetiva de <strong>0 a 100 puntos</strong> basada en 8 factores de riesgo medidos sobre datos reales (ventas, recepciones, mermas, temperaturas y conteos previos).
				</p>

				<div class="table-responsive mb-3">
					<table class="table table-sm border">
						<thead class="bg-light">
							<tr>
								<th>Factor de Riesgo</th>
								<th class="text-center">Peso</th>
								<th>Regla de Medición</th>
							</tr>
						</thead>
						<tbody>
							<tr><td><strong>Alta rotación</strong></td><td class="text-center font-weight-bold">10%</td><td class="small text-muted">Unidades vendidas en los últimos 90 días respecto al máximo del catálogo.</td></tr>
							<tr><td><strong>Alto valor</strong></td><td class="text-center font-weight-bold">15%</td><td class="small text-muted">Costo unitario de compra / reposición respecto al producto más costoso.</td></tr>
							<tr><td><strong>Hurto</strong></td><td class="text-center font-weight-bold">20%</td><td class="small text-muted">Frecuencia histórica de eventos de robo interno (RIN), externo (REX) o pendiente (INV).</td></tr>
							<tr><td><strong>Merma histórica</strong></td><td class="text-center font-weight-bold">15%</td><td class="small text-muted">Costo total de mermas aprobadas en los últimos 90 días.</td></tr>
							<tr><td><strong>Vence ≤ 7 días</strong></td><td class="text-center font-weight-bold">10%</td><td class="small text-muted">Lotes activos con fecha de vencimiento dentro de los próximos 7 días.</td></tr>
							<tr><td><strong>Sensible a temperatura</strong></td><td class="text-center font-weight-bold">10%</td><td class="small text-muted">Productos refrigerados o congelados sujetos a cadena de frío.</td></tr>
							<tr><td><strong>Diferencias frecuentes</strong></td><td class="text-center font-weight-bold">10%</td><td class="small text-muted">Historial de correcciones de inventario y descuadres en conteos anteriores.</td></tr>
							<tr><td><strong>Stock poco confiable</strong></td><td class="text-center font-weight-bold">10%</td><td class="small text-muted">Tiene stock disponible con rotación activa pero sin conteo físico registrado en los últimos 60 días.</td></tr>
						</tbody>
					</table>
				</div>

				<h6 class="font-weight-bold text-dark mb-2">Umbrales y Frecuencia de Conteo Sugerida</h6>
				<div class="row text-center g-2">
					<div class="col-3"><div class="p-2 border rounded bg-danger-light text-danger"><strong>Urgente (≥ 50 pts)</strong><br><small>Cada 7 días</small></div></div>
					<div class="col-3"><div class="p-2 border rounded bg-warning-light text-warning"><strong>Alta (≥ 30 pts)</strong><br><small>Cada 14 días</small></div></div>
					<div class="col-3"><div class="p-2 border rounded bg-light text-secondary"><strong>Media (≥ 15 pts)</strong><br><small>Cada 30 días</small></div></div>
					<div class="col-3"><div class="p-2 border rounded bg-success-light text-success"><strong>Baja (< 15 pts)</strong><br><small>Cada 90 días</small></div></div>
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Entendido</button>
			</div>
		</div>
	</div>
</div>

<!-- 2. Modal Detalle de Factores del Producto -->
<div class="modal fade" id="modalProductRiskDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<div>
					<h5 class="modal-title font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-chart-pie text-success mr-2"></i><span id="dtlModalProdName">Producto</span>
					</h5>
					<div class="text-muted small mt-1">
						<span id="dtlModalSku" class="font-mono"></span> · <span id="dtlModalDept"></span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="row align-items-center mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
					<div class="col-md-4 text-center border-right">
						<div class="text-muted small text-uppercase font-weight-bold">Puntaje Global</div>
						<div class="h2 font-weight-bold text-dark mb-0"><span id="dtlModalScore">0.0</span> <span class="text-muted small">/ 100</span></div>
						<div class="mt-1"><span class="badge px-3 py-1 font-weight-bold" id="dtlModalPrioBadge"></span></div>
					</div>
					<div class="col-md-4 text-center border-right">
						<div class="text-muted small text-uppercase font-weight-bold">Frecuencia Sugerida</div>
						<div class="h4 font-weight-bold text-dark mb-0" id="dtlModalFrequency">Cada 14 días</div>
						<div class="text-muted small mt-1">Ciclo de auditoría</div>
					</div>
					<div class="col-md-4 text-center">
						<div class="text-muted small text-uppercase font-weight-bold">Stock en Sucursal</div>
						<div class="h4 font-weight-bold text-dark mb-0" id="dtlModalStock">0</div>
						<div class="text-muted small mt-1" id="dtlModalLastCount">Sin conteo</div>
					</div>
				</div>

				<h6 class="font-weight-bold text-dark mb-2">Desglose de los 8 Factores Ponderados</h6>
				<div class="table-responsive">
					<table class="table table-sm border mb-0">
						<thead class="bg-light">
							<tr>
								<th>Factor</th>
								<th class="text-center" style="width: 80px;">Peso</th>
								<th class="text-center" style="width: 140px;">Intensidad Relativa</th>
								<th class="text-right" style="width: 100px;">Puntos Obtenidos</th>
							</tr>
						</thead>
						<tbody id="dtlModalFactorsTbody">
							<!-- Injected via dailyShowProductRiskDetail -->
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer bg-light py-2 d-flex justify-content-between">
				<a href="#" id="dtlModalKardexLink" class="btn btn-outline-secondary btn-sm font-weight-600">
					<i class="fa-solid fa-clock-rotate-left mr-1"></i>Consultar Kardex
				</a>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
					<button type="button" class="daily-action-btn-green btn-sm" id="dtlModalScheduleBtn">
						<i class="fa-solid fa-plus mr-1"></i>Programar Conteo
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- 3. Modal Programar Conteo Ciego -->
<div class="modal fade" id="modalScheduleRiskCount" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-calendar-plus text-success mr-2"></i>Programar Conteo Ciego de Inventario
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="alert alert-light border small text-muted mb-3">
					<i class="fa-solid fa-shield-halved text-success mr-1"></i>
					Los conteos se registran bajo modalidad <strong>ciega</strong>: el personal de conteo no ve la existencia teórica del sistema para evitar sesgos de confirmación.
				</div>

				<form id="dailyScheduleForm">
					<div class="form-group mb-3">
						<label class="small font-weight-bold">Sucursal</label>
						<select class="form-control form-control-sm" id="schBranchSelect">
							{!! $brOpts !!}
						</select>
					</div>

					<div class="form-group mb-3" id="schScopeGroup">
						<label class="small font-weight-bold">Alcance de Programación</label>
						<select class="form-control form-control-sm" id="schPrioritySelect" onchange="dailyToggleScheduleScope(this.value);">
							<option value="single" id="schSingleProductOpt">Producto individual seleccionado</option>
							<option value="urgente">Solo productos Urgentes (≥ 50 pts)</option>
							<option value="urgente,alta" selected>Urgentes y Altas (≥ 30 pts)</option>
							<option value="urgente,alta,media">Urgentes, Altas y Medias</option>
						</select>
					</div>

					<div class="form-group mb-3" id="schSpecificProductContainer">
						<label class="small font-weight-bold">Producto</label>
						<input type="text" class="form-control form-control-sm bg-light" id="schSpecificProductName" readonly>
						<input type="hidden" id="schSpecificProductId" value="">
					</div>

					<div class="form-group mb-0">
						<label class="small font-weight-bold">Nota / Justificación</label>
						<input type="text" class="form-control form-control-sm" id="schNoteInput" value="Conteo selectivo por riesgo (Centro de Control DAILY)">
					</div>
				</form>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="daily-action-btn-green btn-sm" onclick="dailySubmitSchedule();" id="btnSubmitSchedule">
					<i class="fa-solid fa-check mr-1"></i>Confirmar Programación
				</button>
			</div>
		</div>
	</div>
</div>

<!-- 4. Modal Registrar Conteo Físico -->
<div class="modal fade" id="modalExecuteCount" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-pen-to-square text-success mr-2"></i>Registrar Conteo Físico
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="mb-3">
					<div class="text-muted small">Producto a auditar:</div>
					<h5 class="font-weight-bold text-dark mb-0" id="execModalProdTitle"></h5>
				</div>

				<div class="alert alert-light border small mb-3">
					<i class="fa-solid fa-eye-slash text-secondary mr-1"></i>
					<strong>Conteo Ciego Activo:</strong> Ingrese el recuento físico verificado en piso de tienda o cámara frigorífica. El sistema contrastará el stock registrado una vez confirmado.
				</div>

				<input type="hidden" id="execModalCountId" value="">

				<div class="form-group mb-3">
					<label class="small font-weight-bold text-dark">Cantidad física contada (<span id="execModalQuName">u.</span>) *</label>
					<input type="number" step="any" min="0" class="form-control form-control-lg font-weight-bold text-center" id="execModalCountedQty" placeholder="0" required autofocus>
				</div>

				<div class="form-group mb-0">
					<label class="small font-weight-bold text-dark">Observaciones / Ubicación verificada</label>
					<textarea class="form-control form-control-sm" id="execModalNotes" rows="2" placeholder="Ej: Conteo verificado en góndola principal y estiba posterior."></textarea>
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="daily-action-btn-green btn-sm" onclick="dailySubmitExecution();" id="btnSubmitExecution">
					<i class="fa-solid fa-save mr-1"></i>Guardar Recuento
				</button>
			</div>
		</div>
	</div>
</div>

<!-- 5. Modal Aprobar Ajuste de Inventario -->
<div class="modal fade" id="modalApproveAdjustment" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-gavel text-danger mr-2"></i>Aprobar Ajuste de Inventario
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<div class="mb-3">
					<div class="text-muted small">Producto con descuadre:</div>
					<h6 class="font-weight-bold text-dark mb-0" id="adjModalProdTitle"></h6>
				</div>

				<div class="row g-2 mb-3 text-center">
					<div class="col-6">
						<div class="p-2 border rounded bg-light">
							<div class="text-muted small">Diferencia Unidades</div>
							<div class="h4 font-weight-bold text-danger mb-0" id="adjModalDiffAmount">-1</div>
						</div>
					</div>
					<div class="col-6">
						<div class="p-2 border rounded bg-light">
							<div class="text-muted small">Impacto Valorizado</div>
							<div class="h4 font-weight-bold text-danger mb-0" id="adjModalDiffCost">$ -3.00</div>
						</div>
					</div>
				</div>

				<div class="alert alert-warning border small mb-0">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i>
					<strong>Segregación de funciones:</strong> Esta aprobación creará un movimiento contable de ajuste en el Kardex. Requiere rol de supervisor y usuario independiente al auditor que contó.
				</div>
				<input type="hidden" id="adjModalCountId" value="">
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-danger btn-sm font-weight-bold" onclick="dailySubmitApproval();" id="btnSubmitApproval">
					<i class="fa-solid fa-check mr-1"></i>Aprobar y Contabilizar Ajuste
				</button>
			</div>
		</div>
	</div>
</div>

<!-- 6. Modal Programación por ABC -->
<div class="modal fade" id="modalPlanAbc" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white py-3 border-bottom">
				<h5 class="modal-title font-weight-bold text-dark">
					<i class="fa-solid fa-calendar-days text-success mr-2"></i>Conteo Cíclico por Pareto ABC (SAP MM)
				</h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body p-4">
				<p class="text-muted small mb-3">
					Clasifica automáticamente los productos con stock activo de la sucursal según las ventas de los últimos 90 días (Pareto 80/95) y programa conteos para aquellos cuya frecuencia venció:
				</p>
				<div class="row text-center g-2 mb-3">
					<div class="col-4"><div class="p-2 border rounded bg-light"><strong class="text-dark">Clase A</strong><br><small class="text-muted">Cada 7 días</small></div></div>
					<div class="col-4"><div class="p-2 border rounded bg-light"><strong class="text-dark">Clase B</strong><br><small class="text-muted">Cada 30 días</small></div></div>
					<div class="col-4"><div class="p-2 border rounded bg-light"><strong class="text-dark">Clase C</strong><br><small class="text-muted">Cada 90 días</small></div></div>
				</div>
				<div class="form-group mb-0">
					<label class="small font-weight-bold">Sucursal</label>
					<select class="form-control form-control-sm" id="abcBranchSelect">
						{!! $brOpts !!}
					</select>
				</div>
			</div>
			<div class="modal-footer bg-light py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
				<button type="button" class="daily-action-btn-green btn-sm" onclick="dailySubmitPlanAbc();" id="btnSubmitAbc">
					<i class="fa-solid fa-play mr-1"></i>Ejecutar Programación ABC
				</button>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	// Dataset de productos completo desde el backend
	var allProducts = {!! json_encode($products) !!};
	var filteredProducts = allProducts.slice();
	var currentPage = 1;
	var pageSize = 7;
	var factorLabels = {!! json_encode($facLbl) !!};
	var factorFullLabels = {!! json_encode($facFullLbl) !!};
	var factorWeights = {!! json_encode($weights) !!};

	// 1. Inicialización y Renderizado de la tabla de SKU
	window.dailyRenderSkuRows = function() {
		var tbody = document.getElementById('dailySkuTableBody');
		if (!tbody) return;

		var total = filteredProducts.length;
		var totalPages = Math.ceil(total / pageSize) || 1;
		if (currentPage > totalPages) currentPage = totalPages;
		if (currentPage < 1) currentPage = 1;

		var startIdx = (currentPage - 1) * pageSize;
		var endIdx = Math.min(startIdx + pageSize, total);
		var slice = filteredProducts.slice(startIdx, endIdx);

		var html = '';
		if (slice.length === 0) {
			html = '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fa-solid fa-inbox mr-2"></i>No se encontraron productos coincidentes con los filtros seleccionados.</td></tr>';
		} else {
			for (var i = 0; i < slice.length; i++) {
				var p = slice[i];
				var globalIdx = startIdx + i + 1;
				var prioBadge = '';
				if (p.priority === 'urgente') {
					prioBadge = '<span class="badge badge-danger px-2 py-1 text-capitalize">Urgente</span>';
				} else if (p.priority === 'alta') {
					prioBadge = '<span class="badge badge-warning px-2 py-1 text-capitalize font-weight-bold">Alta</span>';
				} else if (p.priority === 'media') {
					prioBadge = '<span class="badge badge-secondary px-2 py-1 text-capitalize">Media</span>';
				} else {
					prioBadge = '<span class="badge badge-success px-2 py-1 text-capitalize">Baja</span>';
				}

				var barColor = (p.priority === 'urgente') ? '#ef4444' : (p.priority === 'alta' ? '#f59e0b' : (p.priority === 'media' ? '#94a3b8' : '#10b981'));
				var reasonsText = (p.reasons && p.reasons.length) ? p.reasons.map(function(r) { return factorLabels[r] || r; }).join(', ') : '—';
				var lastCountText = p.last_count ? p.last_count : '<span class="text-danger" style="font-size: 0.76rem;">Sin conteo registrado</span>';
				var skuText = p.sku ? '<span class="font-mono text-dark">' + escapeHtml(p.sku) + '</span>' : '<span class="text-muted">—</span>';

				html += '<tr>' +
					'<td class="text-muted font-weight-600">' + globalIdx + '</td>' +
					'<td>' + skuText + '</td>' +
					'<td><a href="' + Grocy.BaseUrl + '/kardex?product_id=' + p.product_id + '" class="font-weight-600 text-dark text-decoration-none hover-underline">' + escapeHtml(p.name) + '</a></td>' +
					'<td class="text-muted small">' + escapeHtml(p.department || '—') + '</td>' +
					'<td class="text-center">' +
						'<div class="d-flex align-items-center justify-content-center gap-2">' +
							'<strong class="text-dark">' + parseFloat(p.score).toFixed(1) + '</strong>' +
							'<div style="width: 36px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;" title="' + p.score + '/100">' +
								'<div style="width: ' + Math.min(100, p.score) + '%; height: 100%; background: ' + barColor + ';"></div>' +
							'</div>' +
						'</div>' +
					'</td>' +
					'<td>' + prioBadge + '</td>' +
					'<td class="text-muted small">Cada ' + (p.frequency_days || 30) + ' días</td>' +
					'<td class="small text-muted text-truncate" style="max-width: 200px;" title="' + escapeHtml(reasonsText) + '">' + escapeHtml(reasonsText) + '</td>' +
					'<td class="text-right font-weight-600">' + parseFloat(p.stock_units || 0) + ' <span class="text-muted font-weight-normal small">' + escapeHtml(p.qu_name || 'u.') + '</span></td>' +
					'<td class="small">' + lastCountText + '</td>' +
					'<td class="text-right">' +
						'<button class="btn btn-xs btn-outline-secondary font-weight-600" onclick=\'dailyShowProductRiskDetail(' + JSON.stringify(p).replace(/'/g, "&#39;") + ')\'>Ver detalle</button>' +
					'</td>' +
				'</tr>';
			}
		}
		tbody.innerHTML = html;

		// Actualizar contador y controles de paginación
		var rangeDisplay = document.getElementById('dailySkuDisplayRange');
		if (rangeDisplay) {
			rangeDisplay.textContent = total > 0 ? (startIdx + 1) + '-' + endIdx : '0';
		}

		var countDisplay = document.getElementById('dailySkuCountDisplay');
		if (countDisplay) {
			countDisplay.innerHTML = 'Mostrando <span id="dailySkuDisplayRange">' + (total > 0 ? (startIdx + 1) + '-' + endIdx : '0') + '</span> de ' + total + ' SKU';
		}

		var paginationControls = document.getElementById('dailySkuPaginationControls');
		if (paginationControls) {
			var pagHtml = '<button type="button" class="btn btn-outline-secondary ' + (currentPage <= 1 ? 'disabled' : '') + '" onclick="dailySetSkuPage(' + (currentPage - 1) + ');">&lt;</button>';
			for (var pNo = 1; pNo <= totalPages; pNo++) {
				pagHtml += '<button type="button" class="btn ' + (pNo === currentPage ? 'btn-success text-white' : 'btn-outline-secondary') + '" onclick="dailySetSkuPage(' + pNo + ');">' + pNo + '</button>';
			}
			pagHtml += '<button type="button" class="btn btn-outline-secondary ' + (currentPage >= totalPages ? 'disabled' : '') + '" onclick="dailySetSkuPage(' + (currentPage + 1) + ');">&gt;</button>';
			paginationControls.innerHTML = pagHtml;
		}
	};

	window.dailySetSkuPage = function(page) {
		currentPage = page;
		dailyRenderSkuRows();
	};

	window.dailyApplySkuFilters = function() {
		var q = (document.getElementById('dailySkuSearchInput') ? document.getElementById('dailySkuSearchInput').value : '').toLowerCase().trim();
		var prio = document.getElementById('dailySkuPriorityFilter') ? document.getElementById('dailySkuPriorityFilter').value : '';
		var dept = document.getElementById('dailySkuDeptFilter') ? document.getElementById('dailySkuDeptFilter').value : '';

		filteredProducts = allProducts.filter(function(p) {
			if (q) {
				var matchName = (p.name || '').toLowerCase().indexOf(q) !== -1;
				var matchSku = (p.sku || '').toLowerCase().indexOf(q) !== -1;
				if (!matchName && !matchSku) return false;
			}
			if (prio && p.priority !== prio) return false;
			if (dept && p.department !== dept) return false;
			return true;
		});

		currentPage = 1;
		dailyRenderSkuRows();
	};

	window.dailyFilterByPriority = function(prio) {
		var select = document.getElementById('dailySkuPriorityFilter');
		if (select) {
			select.value = prio;
		}

		// Highlight visual en cajas de prioridad
		['urgente', 'alta', 'media', 'baja'].forEach(function(p) {
			var box = document.getElementById('prio-box-' + p);
			if (box) {
				if (p === prio) box.classList.add('active-filter');
				else box.classList.remove('active-filter');
			}
		});

		dailyApplySkuFilters();

		// Scroll suave hacia la tabla
		var target = document.getElementById('dailySkuTableSection');
		if (target && prio) {
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	// 2. Modal Detalle de Factores del Producto
	window.dailyShowProductRiskDetail = function(p) {
		if (!p) return;
		document.getElementById('dtlModalProdName').textContent = p.name;
		document.getElementById('dtlModalSku').textContent = p.sku ? 'SKU: ' + p.sku : 'Sin SKU';
		document.getElementById('dtlModalDept').textContent = p.department || 'Sin departamento';
		document.getElementById('dtlModalScore').textContent = parseFloat(p.score).toFixed(1);

		var badge = document.getElementById('dtlModalPrioBadge');
		badge.className = 'badge px-3 py-1 text-capitalize ' + (p.priority === 'urgente' ? 'badge-danger' : (p.priority === 'alta' ? 'badge-warning font-weight-bold text-dark' : (p.priority === 'media' ? 'badge-secondary' : 'badge-success')));
		badge.textContent = 'Prioridad ' + p.priority;

		document.getElementById('dtlModalFrequency').textContent = 'Cada ' + (p.frequency_days || 30) + ' días';
		document.getElementById('dtlModalStock').innerHTML = parseFloat(p.stock_units || 0) + ' <span class="text-muted small font-weight-normal">' + escapeHtml(p.qu_name || 'u.') + '</span>';
		document.getElementById('dtlModalLastCount').innerHTML = p.last_count ? '<i class="fa-solid fa-clock-rotate-left mr-1"></i>Último conteo: ' + p.last_count : '<span class="text-danger">Sin conteo registrado</span>';

		document.getElementById('dtlModalKardexLink').href = Grocy.BaseUrl + '/kardex?product_id=' + p.product_id;

		var scheduleBtn = document.getElementById('dtlModalScheduleBtn');
		if (scheduleBtn) {
			scheduleBtn.onclick = function() {
				$('#modalProductRiskDetail').modal('hide');
				dailyOpenScheduleModal(p.product_id, p.name);
			};
		}

		// Desglose de factores
		var tbody = document.getElementById('dtlModalFactorsTbody');
		var fHtml = '';
		var factors = p.factors || {};
		for (var k in factorWeights) {
			var w = factorWeights[k];
			var intensity = factors[k] !== undefined ? factors[k] : 0.0;
			var pts = (intensity * w).toFixed(1);
			var barPct = Math.min(100, Math.round(intensity * 100));
			var isHigh = intensity >= 0.5;

			fHtml += '<tr>' +
				'<td>' +
					'<div class="font-weight-600 ' + (isHigh ? 'text-dark' : 'text-muted') + '">' + (factorLabels[k] || k) + '</div>' +
					'<div class="text-xs text-muted" style="font-size: 0.72rem;">' + (factorFullLabels[k] || '') + '</div>' +
				'</td>' +
				'<td class="text-center font-weight-bold text-muted">' + w + '%</td>' +
				'<td class="text-center">' +
					'<div class="progress" style="height: 6px;" title="' + (intensity * 100).toFixed(0) + '%">' +
						'<div class="progress-bar ' + (isHigh ? 'bg-danger' : 'bg-success') + '" style="width: ' + barPct + '%;"></div>' +
					'</div>' +
					'<div class="text-xs text-muted mt-1">' + (intensity * 100).toFixed(0) + '%</div>' +
				'</td>' +
				'<td class="text-right font-weight-bold ' + (isHigh ? 'text-danger' : 'text-dark') + '">+' + pts + '</td>' +
			'</tr>';
		}
		tbody.innerHTML = fHtml;

		$('#modalProductRiskDetail').modal('show');
	};

	// 3. Programación de conteos
	window.dailyOpenScheduleModal = function(productId, productName) {
		var select = document.getElementById('schPrioritySelect');
		var optSingle = document.getElementById('schSingleProductOpt');
		var singleContainer = document.getElementById('schSpecificProductContainer');

		if (productId) {
			optSingle.style.display = '';
			optSingle.selected = true;
			singleContainer.style.display = '';
			document.getElementById('schSpecificProductName').value = productName;
			document.getElementById('schSpecificProductId').value = productId;
		} else {
			optSingle.style.display = 'none';
			select.value = 'urgente,alta';
			singleContainer.style.display = 'none';
			document.getElementById('schSpecificProductId').value = '';
		}
		$('#modalScheduleRiskCount').modal('show');
	};

	window.dailyToggleScheduleScope = function(val) {
		var singleContainer = document.getElementById('schSpecificProductContainer');
		if (val === 'single') {
			singleContainer.style.display = '';
		} else {
			singleContainer.style.display = 'none';
		}
	};

	window.dailySubmitSchedule = function() {
		var btn = document.getElementById('btnSubmitSchedule');
		var branchId = document.getElementById('schBranchSelect').value;
		var scope = document.getElementById('schPrioritySelect').value;
		var note = document.getElementById('schNoteInput').value;
		var specificPid = document.getElementById('schSpecificProductId').value;

		btn.disabled = true;

		var url = '';
		var payload = {};

		if (scope === 'single' && specificPid) {
			url = Grocy.BaseUrl + '/api/loss/counts/schedule';
			payload = {
				branch_id: branchId,
				product_ids: [parseInt(specificPid)],
				note: note
			};
		} else {
			url = Grocy.BaseUrl + '/api/loss/ranking/schedule';
			payload = {
				branch_id: branchId,
				priorities: scope.split(','),
				note: note
			};
		}

		fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			btn.disabled = false;
			$('#modalScheduleRiskCount').modal('hide');
			if (res.success || res.scheduled !== undefined) {
				toastr.success(res.message || 'Conteo ciego programado correctamente');
				setTimeout(function() { dairyRefreshActiveTab(); }, 600);
			} else {
				toastr.error(res.error_message || 'No se pudo programar el conteo');
			}
		})
		.catch(function(err) {
			btn.disabled = false;
			toastr.error('Error de conexión al programar conteo');
		});
	};

	// 4. Registrar conteo físico
	window.dailyOpenExecuteCount = function(countId, productTitle, quName) {
		document.getElementById('execModalCountId').value = countId;
		document.getElementById('execModalProdTitle').textContent = productTitle;
		document.getElementById('execModalQuName').textContent = quName || 'u.';
		document.getElementById('execModalCountedQty').value = '';
		document.getElementById('execModalNotes').value = '';
		$('#modalExecuteCount').modal('show');
	};

	window.dailySubmitExecution = function() {
		var countId = document.getElementById('execModalCountId').value;
		var qty = document.getElementById('execModalCountedQty').value;
		var notes = document.getElementById('execModalNotes').value;
		var btn = document.getElementById('btnSubmitExecution');

		if (qty === '' || isNaN(qty) || parseFloat(qty) < 0) {
			toastr.warning('Por favor ingrese una cantidad contada válida (0 o mayor)');
			return;
		}

		btn.disabled = true;
		fetch(Grocy.BaseUrl + '/api/stock/cycle-counts/' + countId + '/execute', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ counted_stock: parseFloat(qty), notes: notes })
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			btn.disabled = false;
			$('#modalExecuteCount').modal('hide');
			if (res.success) {
				toastr.success(res.message || 'Conteo registrado');
				setTimeout(function() { dairyRefreshActiveTab(); }, 600);
			} else {
				toastr.error(res.error_message || 'Error al registrar conteo');
			}
		})
		.catch(function() {
			btn.disabled = false;
			toastr.error('Error de comunicación');
		});
	};

	// 5. Aprobar ajuste de inventario
	window.dailyOpenApproveAdjustment = function(countId, productTitle, diffAmount, diffCost) {
		document.getElementById('adjModalCountId').value = countId;
		document.getElementById('adjModalProdTitle').textContent = productTitle;
		document.getElementById('adjModalDiffAmount').textContent = (diffAmount > 0 ? '+' : '') + diffAmount;
		document.getElementById('adjModalDiffCost').textContent = '$ ' + diffCost.toFixed(2);
		$('#modalApproveAdjustment').modal('show');
	};

	window.dailySubmitApproval = function() {
		var countId = document.getElementById('adjModalCountId').value;
		var btn = document.getElementById('btnSubmitApproval');
		btn.disabled = true;

		fetch(Grocy.BaseUrl + '/api/stock/cycle-counts/' + countId + '/approve-adjustment', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' }
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			btn.disabled = false;
			$('#modalApproveAdjustment').modal('hide');
			if (res.success) {
				toastr.success(res.message || 'Ajuste de inventario contabilizado correctamente');
				setTimeout(function() { dairyRefreshActiveTab(); }, 600);
			} else {
				toastr.error(res.error_message || 'No se pudo aprobar el ajuste');
			}
		})
		.catch(function() {
			btn.disabled = false;
			toastr.error('Error de red al aprobar ajuste');
		});
	};

	// 6. Programar por ABC
	window.dailyOpenAbcScheduleModal = function() {
		$('#modalPlanAbc').modal('show');
	};

	window.dailySubmitPlanAbc = function() {
		var branchId = document.getElementById('abcBranchSelect').value;
		var btn = document.getElementById('btnSubmitAbc');
		btn.disabled = true;

		fetch(Grocy.BaseUrl + '/api/stock/cycle-counts/auto-abc', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ branch_id: parseInt(branchId), dry_run: false })
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			btn.disabled = false;
			$('#modalPlanAbc').modal('hide');
			if (res.scheduled !== undefined) {
				toastr.success(res.message || (res.scheduled + ' conteos programados por Pareto ABC'));
				setTimeout(function() { dairyRefreshActiveTab(); }, 600);
			} else {
				toastr.error(res.error_message || 'No se pudo programar por ABC');
			}
		})
		.catch(function() {
			btn.disabled = false;
			toastr.error('Error al ejecutar programación ABC');
		});
	};

	function escapeHtml(text) {
		if (!text) return '';
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	// Inicializar la tabla al cargar
	dailyRenderSkuRows();
})();
</script>
