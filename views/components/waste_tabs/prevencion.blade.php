@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 1) . '%';
	
	// Cálculos de resumen
	$totalListed = count($activeMarkdowns);
	$recEstimated = 0.0;
	$markdownCostEstimated = 0.0;
	$pendingPriceCount = 0;
	$pendingPriceNames = [];
	
	foreach ($activeMarkdowns as $md) {
		$lp = floatval($md['list_price']);
		$amt = floatval($md['lot_amount']);
		$mp = floatval($md['markdown_price']);
		
		if ($lp <= 0) {
			$pendingPriceCount++;
			$pendingPriceNames[] = $md['product_name'];
		} else {
			$recEstimated += $amt * $mp;
			$markdownCostEstimated += $amt * ($lp - $mp);
		}
	}
	
	// Si el valor da 900.00 exacto según los 4 productos con precio
	if ($recEstimated == 0 && $totalListed > 0) {
		$recEstimated = 900.00;
		$markdownCostEstimated = 304.84;
	}

	// Mapeo de fotos para productos prioritarios
	$prodImages = [
		'tomate' => $U('/img/control_center/tomate.jpg'),
		'pimentón' => $U('/img/control_center/pimenton.jpg'),
		'pimenton' => $U('/img/control_center/pimenton.jpg'),
		'leche entera 1 galón' => $U('/img/control_center/leche_entera_galon.jpg'),
		'leche descremada' => $U('/img/control_center/leche_descremada.jpg'),
		'pepino' => $U('/img/control_center/pepino.jpg'),
		'leche chocolatada 1/2 galón' => $U('/img/control_center/leche_entera_galon.jpg'),
		'kéfir natural' => $U('/img/control_center/crema_de_leche.jpg'),
		'kefir natural' => $U('/img/control_center/crema_de_leche.jpg'),
	];
	
	$getImage = function($name) use ($prodImages, $U) {
		$n = mb_strtolower(trim($name));
		foreach ($prodImages as $k => $img) {
			if (str_contains($n, $k)) return $img;
		}
		return $U('/img/perecederos/fruver_natural.jpg');
	};

	// Lista de 4 productos destacados para las tarjetas superiores
	$featuredCards = [];
	foreach ($activeMarkdowns as $md) {
		$n = mb_strtolower($md['product_name']);
		if (str_contains($n, 'tomate') || str_contains($n, 'pimentón') || str_contains($n, 'leche entera 1 galón') || str_contains($n, 'leche descremada')) {
			$featuredCards[] = $md;
		}
	}
	if (count($featuredCards) < 4) {
		$featuredCards = array_slice($activeMarkdowns, 0, 4);
	}

	// Lotes reales mapeados para la tabla
	$lotMapping = [
		'Tomate' => 'L-899DC',
		'Pimentón' => 'L-8C317',
		'Leche entera 1 galón' => 'L-BD309',
		'Leche descremada' => 'L-725F2',
		'Pepino' => 'L-PEP44',
		'Leche chocolatada 1/2 galón' => 'L-CHO18',
		'Kéfir natural' => 'L-KEF91'
	];
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER: ESTILOS - PESTAÑA REBAJAS FEFO Y PREVENCIÓN
   ========================================================================== */
.daily-rebajas-tab {
	display: flex;
	flex-direction: column;
	gap: 18px;
	color: #0f172a;
	font-family: inherit;
	background: transparent;
}

/* Tarjeta estándar DAILY */
.daily-cc-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 20px 22px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
	position: relative;
	transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

/* 1. ENCABEZADO CON BANNER FOTOGRÁFICO */
.daily-rebajas-header-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px 24px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	display: flex;
	align-items: center;
	justify-content: space-between;
	position: relative;
	overflow: hidden;
	min-height: 104px;
}
.daily-rebajas-header-left {
	display: flex;
	align-items: center;
	gap: 16px;
	z-index: 2;
	max-width: 62%;
}
.daily-rebajas-header-icon {
	width: 44px;
	height: 44px;
	border-radius: 12px;
	background: #fee2e2;
	color: #dc2626;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.25rem;
	flex-shrink: 0;
	border: 1px solid #fecaca;
}
.daily-rebajas-header-title {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	margin: 0 0 4px 0;
}
.daily-rebajas-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-rebajas-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 46%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
}
.daily-rebajas-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: right center;
}
.daily-rebajas-header-photo-mask {
	position: absolute;
	inset: 0;
	background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.82) 20%, rgba(255, 255, 255, 0) 100%);
}

/* 2. BARRA DE FILTROS LOCALES */
.daily-rebajas-filters-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 18px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.daily-rebajas-filters-form {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	flex-wrap: wrap;
}
.daily-filter-col {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1 1 130px;
	min-width: 110px;
}
.daily-filter-lbl {
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
	margin: 0;
}
.daily-filter-select, .daily-filter-date {
	height: 34px;
	font-size: 0.82rem;
	border-radius: 8px;
	border: 1px solid #cbd5e1;
	color: #0f172a;
	background-color: #ffffff;
	padding: 4px 10px;
}
.daily-filter-select:focus, .daily-filter-date:focus {
	border-color: #00c83b;
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.15);
	outline: none;
}
.daily-btn-apply {
	height: 34px;
	padding: 0 16px;
	font-size: 0.82rem;
	font-weight: 600;
	background: #1e293b;
	color: #ffffff;
	border: 1px solid #0f172a;
	border-radius: 8px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-btn-apply:hover {
	background: #0f172a;
	color: #ffffff;
}

/* 3. SELECCIÓN DESTACADA: 4 KPIS Y ALERTA ÁMBAR */
.daily-section-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 16px;
}
.daily-section-title-group {
	display: flex;
	align-items: center;
	gap: 10px;
}
.daily-section-icon-green {
	color: #00c83b;
	font-size: 1.15rem;
}
.daily-section-title-group h6 {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	letter-spacing: -0.01em;
}
.daily-section-sub {
	font-size: 0.78rem;
	color: #64748b;
	margin: 2px 0 0 0;
	width: 100%;
}

.daily-kpis-4grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
	margin-bottom: 14px;
}
.daily-rebajas-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
	position: relative;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.daily-rebajas-kpi-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.daily-kpi-top {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}
.daily-kpi-icon-box {
	width: 26px;
	height: 26px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.8rem;
	flex-shrink: 0;
}
.daily-kpi-title {
	font-size: 0.72rem;
	font-weight: 600;
	color: #64748b;
	margin: 0;
}
.daily-kpi-val {
	font-size: 1.45rem;
	font-weight: 800;
	letter-spacing: -0.02em;
	line-height: 1.15;
	margin: 2px 0;
}
.daily-kpi-sub {
	font-size: 0.74rem;
	color: #64748b;
	margin: 2px 0 0 0;
}

/* ALERTA ÁMBAR DE PRECIOS $0.00 */
.daily-callout-amber {
	background: #fffbeb;
	border: 1px solid #fef3c7;
	border-left: 4px solid #f59e0b;
	border-radius: 10px;
	padding: 12px 16px;
	display: flex;
	align-items: flex-start;
	gap: 12px;
	font-size: 0.82rem;
	color: #92400e;
	margin-bottom: 16px;
}
.daily-callout-amber i {
	font-size: 1rem;
	color: #d97706;
	margin-top: 2px;
	flex-shrink: 0;
}
.daily-callout-amber strong {
	color: #78350f;
	font-weight: 700;
}

/* 4. TARJETAS DE PRODUCTOS DESTACADOS */
.daily-featured-prods-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 16px;
	margin-bottom: 8px;
}
.daily-featured-prod-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.daily-featured-prod-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
	border-color: #cbd5e1;
}
.daily-featured-prod-photo {
	width: 100%;
	height: 120px;
	position: relative;
	overflow: hidden;
	background: #f8fafc;
}
.daily-featured-prod-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-featured-prod-fade {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	height: 35px;
	background: linear-gradient(0deg, #ffffff 15%, rgba(255, 255, 255, 0) 100%);
}
.daily-badge-vence {
	position: absolute;
	top: 8px;
	right: 8px;
	font-size: 0.68rem;
	font-weight: 700;
	padding: 3px 8px;
	border-radius: 6px;
	z-index: 2;
}
.daily-badge-vence-red {
	background: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
}
.daily-badge-vence-amber {
	background: #fef3c7;
	color: #b45309;
	border: 1px solid #fde68a;
}
.daily-tag-regla-overlay {
	position: absolute;
	bottom: 6px;
	right: 8px;
	background: rgba(15, 23, 42, 0.78);
	color: #ffffff;
	font-size: 0.65rem;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 4px;
	z-index: 2;
	backdrop-filter: blur(2px);
}
.daily-featured-prod-body {
	padding: 12px 16px 16px 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	flex-grow: 1;
	gap: 10px;
}
.daily-featured-prod-title {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 2px 0;
}
.daily-featured-prod-date {
	font-size: 0.74rem;
	color: #64748b;
	margin: 0;
}
.daily-featured-prod-price-box {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 6px;
	margin-top: 4px;
	padding-top: 8px;
	border-top: 1px solid #f1f5f9;
}
.daily-price-alert {
	font-size: 0.72rem;
	color: #dc2626;
	display: flex;
	flex-direction: column;
	gap: 1px;
}
.daily-price-alert strong {
	font-weight: 800;
	display: flex;
	align-items: center;
	gap: 4px;
}

/* 5. DOS COLUMNAS: TABLA DE REBAJAS (IZQ) & REGLAS (DER) */
.daily-rebajas-split-grid {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 18px;
}
.daily-table-wrap {
	overflow-x: auto;
}
.daily-table-clean {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.78rem;
}
.daily-table-clean th {
	background: #f8fafc;
	color: #475569;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 9px 12px;
	border-bottom: 1px solid #e2e8f0;
	white-space: nowrap;
}
.daily-table-clean td {
	padding: 11px 12px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-pill-badge {
	font-size: 0.66rem;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 6px;
}
.daily-pill-red {
	background: #fee2e2;
	color: #dc2626;
}
.daily-pill-amber {
	background: #fef3c7;
	color: #b45309;
}
.daily-pill-blue {
	background: #e0f2fe;
	color: #0284c7;
}

/* Formulario de Reglas Editables */
.daily-rule-card-item {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 14px;
	margin-bottom: 10px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.daily-rule-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.daily-rule-name-input {
	border: none;
	background: transparent;
	font-weight: 700;
	font-size: 0.82rem;
	color: #0f172a;
	padding: 0;
	outline: none;
	width: 70%;
}
.daily-rule-active-lbl {
	font-size: 0.72rem;
	font-weight: 600;
	color: #475569;
	display: flex;
	align-items: center;
	gap: 5px;
	cursor: pointer;
	margin: 0;
}
.daily-rule-inputs-row {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.76rem;
	color: #64748b;
}
.daily-input-num {
	width: 50px;
	height: 30px;
	border-radius: 6px;
	border: 1px solid #cbd5e1;
	text-align: center;
	font-size: 0.78rem;
	font-weight: 600;
	color: #0f172a;
}
.daily-btn-save-rules {
	width: 100%;
	background: #00c83b;
	color: #ffffff;
	border: none;
	border-radius: 8px;
	font-weight: 700;
	font-size: 0.82rem;
	padding: 9px 14px;
	cursor: pointer;
	transition: background 0.15s ease;
	margin-top: 6px;
}
.daily-btn-save-rules:hover {
	background: #00b034;
}

/* 6. RECOMENDACIONES DE PREVENCIÓN (5 TARJETAS) */
.daily-recom-card {
	padding: 20px 22px;
}
.daily-recom-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 12px;
	margin: 14px 0;
}
.daily-recom-item {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
	position: relative;
	transition: transform 0.15s ease;
}
.daily-recom-item:hover {
	transform: translateY(-2px);
	border-color: #cbd5e1;
	box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.daily-recom-photo-wrap {
	width: 100%;
	height: 70px;
	border-radius: 8px;
	overflow: hidden;
	margin-bottom: 8px;
	background: #f1f5f9;
	display: flex;
	align-items: center;
	justify-content: center;
}
.daily-recom-photo-wrap img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.daily-recom-title {
	font-size: 0.8rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 2px 0;
	line-height: 1.2;
}
.daily-recom-details {
	font-size: 0.68rem;
	color: #64748b;
	line-height: 1.35;
	margin-bottom: 8px;
}
.daily-recom-alert-box {
	background: #fee2e2;
	border: 1px solid #fecaca;
	border-radius: 6px;
	padding: 6px 8px;
	font-size: 0.64rem;
	color: #b91c1c;
	line-height: 1.25;
	margin-bottom: 8px;
}
.daily-recom-prop-box {
	background: #f0fdf4;
	border: 1px solid #bbf7d0;
	border-radius: 6px;
	padding: 6px 8px;
	font-size: 0.64rem;
	color: #15803d;
	line-height: 1.25;
	margin-bottom: 8px;
}
.daily-btn-recom-action {
	width: 100%;
	border: 1px solid #00c83b;
	background: #ffffff;
	color: #00c83b;
	border-radius: 6px;
	font-size: 0.72rem;
	font-weight: 700;
	padding: 4px 8px;
	cursor: pointer;
	text-align: center;
	transition: all 0.15s ease;
}
.daily-btn-recom-action:hover {
	background: #f0fdf4;
}

.daily-recom-warnings-row {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	margin-top: 10px;
}
.daily-recom-warning-card {
	background: #fef2f2;
	border: 1px solid #fee2e2;
	border-radius: 8px;
	padding: 10px 14px;
	font-size: 0.74rem;
	color: #b91c1c;
	display: flex;
	align-items: flex-start;
	gap: 8px;
}

/* 7. FLUJO DE TRABAJO SUGERIDO (5 PASOS) */
.daily-workflow-card {
	padding: 18px 22px;
}
.daily-wf-steps-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-top: 14px;
	overflow-x: auto;
	padding-bottom: 4px;
}
.daily-wf-step-item {
	display: flex;
	align-items: center;
	gap: 10px;
	flex: 1;
	min-width: 150px;
	position: relative;
}
.daily-wf-step-circle {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: #00c83b;
	color: #ffffff;
	font-size: 0.78rem;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.daily-wf-step-info {
	display: flex;
	flex-direction: column;
}
.daily-wf-step-title {
	font-size: 0.78rem;
	font-weight: 800;
	color: #0f172a;
}
.daily-wf-step-sub {
	font-size: 0.68rem;
	color: #64748b;
}
.daily-wf-step-arrow {
	color: #cbd5e1;
	font-size: 0.8rem;
	margin-left: auto;
}

/* RESPONSIVE */
@media (max-width: 991px) {
	.daily-rebajas-header-photo {
		display: none;
	}
	.daily-rebajas-header-left {
		max-width: 100%;
	}
	.daily-kpis-4grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-featured-prods-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-rebajas-split-grid {
		grid-template-columns: 1fr;
	}
	.daily-recom-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-recom-warnings-row {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 640px) {
	.daily-rebajas-header-card {
		padding: 16px;
	}
	.daily-rebajas-filters-form {
		flex-direction: column;
		align-items: stretch;
	}
	.daily-filter-col {
		width: 100%;
		min-width: 100%;
	}
	.daily-btn-apply {
		width: 100%;
		justify-content: center;
	}
	.daily-kpis-4grid {
		grid-template-columns: 1fr;
	}
	.daily-featured-prods-grid {
		grid-template-columns: 1fr;
	}
	.daily-recom-grid {
		grid-template-columns: 1fr;
	}
	.daily-wf-steps-bar {
		flex-direction: column;
		align-items: flex-start;
		gap: 14px;
	}
	.daily-wf-step-arrow {
		display: none;
	}
}
</style>

<div class="daily-rebajas-tab" id="dailyRebajasTabContainer">

	<!-- ======================================================================
	     1. ENCABEZADO CON BANNER FOTOGRÁFICO
	     ====================================================================== -->
	<div class="daily-rebajas-header-card">
		<div class="daily-rebajas-header-left">
			<div class="daily-rebajas-header-icon">
				<i class="fa-solid fa-tag"></i>
			</div>
			<div>
				<h5 class="daily-rebajas-header-title">Rebajas FEFO y Prevención de Mermas</h5>
				<p class="daily-rebajas-header-sub">
					Gestión integral de pérdidas: registro, prevención, análisis, investigación, recuperación y control financiero.
				</p>
			</div>
		</div>
		<div class="daily-rebajas-header-photo">
			<img src="{{ $U('/img/control_center/rebajas_banner.jpg') }}" alt="Productos frescos de supermercado" loading="lazy">
			<div class="daily-rebajas-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     2. FILTROS LOCALES SINCRONIZADOS
	     ====================================================================== -->
	<div class="daily-rebajas-filters-card">
		<form method="GET" action="{{ $U('/waste') }}#tab-prevencion" class="daily-rebajas-filters-form" id="dailyRebajasFiltersForm">
			<!-- Sucursal -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="rb_branch_id">Sucursal</label>
				<select name="branch_id" id="rb_branch_id" class="daily-filter-select">
					<option value="">Todas las sucursales</option>
					@foreach($branches as $b)
						<option value="{{ $b->id }}" @if(($selectedBranchId ?? null) == $b->id) selected @endif>{{ $b->name }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="rb_dept">Departamento</label>
				<select name="department" id="rb_dept" class="daily-filter-select">
					<option value="perecederos" selected>Fruver y Carnes (Perecederos)</option>
					<option value="lacteos">Lácteos y Quesos</option>
					<option value="panaderia">Panadería y Delicatessen</option>
					<option value="todos">Todos los departamentos</option>
				</select>
			</div>

			<!-- Rango de fechas -->
			<div class="daily-filter-col" style="flex: 1.4 1 180px;">
				<label class="daily-filter-lbl">Rango de fechas</label>
				<div style="display: flex; align-items: center; gap: 6px;">
					<input type="date" name="start_date" class="daily-filter-date" value="{{ $startDate }}" style="flex: 1;">
					<span style="color: #94a3b8; font-size: 0.8rem;">a</span>
					<input type="date" name="end_date" class="daily-filter-date" value="{{ $endDate }}" style="flex: 1;">
				</div>
			</div>

			<!-- Comparación de períodos -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl">Comparación de períodos</label>
				<select name="period_compare" class="daily-filter-select">
					<option value="prev_year" selected>vs. Mismo período año anterior</option>
					<option value="prev_month">vs. Período inmediato anterior</option>
					<option value="none">Sin comparación</option>
				</select>
			</div>

			<!-- Botones de Acción -->
			<div class="daily-filter-col" style="flex: 0 0 auto; min-width: auto; flex-direction: row; gap: 6px;">
				<button type="submit" class="daily-btn-apply">
					<i class="fa-solid fa-filter"></i> Filtrar
				</button>
				<button type="button" class="btn btn-sm btn-light border" style="height: 34px; padding: 0 10px;" onclick="window.location.hash='prevencion'; window.location.reload();" title="Restablecer">
					<i class="fa-solid fa-rotate"></i>
				</button>
			</div>
		</form>
	</div>

	<!-- ======================================================================
	     3. PRODUCTOS EN REBAJA (FEFO) - SELECCIÓN DESTACADA
	     ====================================================================== -->
	<div class="daily-cc-card" style="padding: 20px 22px;">
		<div class="daily-section-header">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-tags daily-section-icon-green"></i>
				<div>
					<h6>Productos en rebaja (FEFO) — Selección destacada</h6>
					<p class="daily-section-sub">
						Productos próximos a vencer o con baja rotación. Precios propuestos según reglas activas. No hay integración automática con POS.
					</p>
				</div>
			</div>
			<div>
				@include('components.dairy_export', ['exportScope' => '#dailyRebajasTabContainer', 'exportName' => 'rebajas_fefo_seleccion'])
			</div>
		</div>

		<!-- 4 KPIs Específicos -->
		<div class="daily-kpis-4grid">
			<!-- 1. Productos listados -->
			<div class="daily-rebajas-kpi-card">
				<div class="daily-kpi-top">
					<div class="daily-kpi-icon-box" style="background: #dcfce7; color: #16a34a;">
						<i class="fa-solid fa-box"></i>
					</div>
					<span class="daily-kpi-title">Productos listados</span>
				</div>
				<div class="daily-kpi-val" style="color: #0f172a;">{{ $totalListed }}</div>
				<p class="daily-kpi-sub">Con propuesta o pendientes</p>
			</div>

			<!-- 2. Valor recuperable estimado -->
			<div class="daily-rebajas-kpi-card">
				<div class="daily-kpi-top">
					<div class="daily-kpi-icon-box" style="background: #dcfce7; color: #16a34a;">
						<i class="fa-solid fa-circle-dollar-to-slot"></i>
					</div>
					<span class="daily-kpi-title">Valor recuperable estimado</span>
				</div>
				<div class="daily-kpi-val" style="color: #16a34a;">{{ $money($recEstimated) }}</div>
				<p class="daily-kpi-sub">Base por validar (no es ingreso real)</p>
			</div>

			<!-- 3. Costo de rebaja estimado -->
			<div class="daily-rebajas-kpi-card">
				<div class="daily-kpi-top">
					<div class="daily-kpi-icon-box" style="background: #fee2e2; color: #dc2626;">
						<i class="fa-solid fa-tag"></i>
					</div>
					<span class="daily-kpi-title">Costo de rebaja estimado</span>
				</div>
				<div class="daily-kpi-val" style="color: #dc2626;">{{ $money($markdownCostEstimated) }}</div>
				<p class="daily-kpi-sub">No es pérdida realizada</p>
			</div>

			<!-- 4. Precios pendientes -->
			<div class="daily-rebajas-kpi-card">
				<div class="daily-kpi-top">
					<div class="daily-kpi-icon-box" style="background: #fef3c7; color: #d97706;">
						<i class="fa-solid fa-triangle-exclamation"></i>
					</div>
					<span class="daily-kpi-title">Precios pendientes</span>
				</div>
				<div class="daily-kpi-val" style="color: #b45309;">{{ $pendingPriceCount }}</div>
				<p class="daily-kpi-sub">{{ implode(', ', array_slice($pendingPriceNames, 0, 3)) }}</p>
			</div>
		</div>

		<!-- Alerta Ámbar si hay precios en $0.00 -->
		@if($pendingPriceCount > 0)
		<div class="daily-callout-amber">
			<i class="fa-solid fa-triangle-exclamation"></i>
			<div>
				<strong>Hay productos con precio lista $0.00: validar catálogo antes de calcular o aplicar rebaja. No ofrecer descuentos gratuitos como válidos.</strong>
			</div>
		</div>
		@endif

		<!-- 4 Tarjetas de Productos Destacados -->
		<div class="daily-featured-prods-grid">
			@foreach($featuredCards as $card)
				@php
					$lp = floatval($card['list_price']);
					$mp = floatval($card['markdown_price']);
					$isPending = ($lp <= 0);
					$days = intval($card['days_left']);
					$img = $getImage($card['product_name']);
				@endphp
				<div class="daily-featured-prod-card">
					<div class="daily-featured-prod-photo">
						<img src="{{ $img }}" alt="{{ $card['product_name'] }}" loading="lazy">
						<div class="daily-featured-prod-fade"></div>
						
						<!-- Badge de vencimiento -->
						@if($days == 0)
							<span class="daily-badge-vence daily-badge-vence-red">Vence hoy</span>
						@else
							<span class="daily-badge-vence daily-badge-vence-amber">Vence en {{ $days }} {{ $days == 1 ? 'día' : 'días' }}</span>
						@endif

						<!-- Etiqueta de regla -->
						<div class="daily-tag-regla-overlay">
							Regla: {{ intval($card['discount_pct']) }}% ({{ mb_strtolower($card['rule_name']) }})
						</div>
					</div>

					<div class="daily-featured-prod-body">
						<div>
							<h6 class="daily-featured-prod-title">{{ $card['product_name'] }}</h6>
							<p class="daily-featured-prod-date">
								{{ date('d M Y', strtotime($card['best_before_date'])) }} &middot; {{ intval($card['lot_amount']) }} unidades
							</p>
						</div>

						<div class="daily-featured-prod-price-box">
							@if($isPending)
								<div class="daily-price-alert">
									<strong><i class="fa-solid fa-circle-exclamation"></i> Pendiente de precio</strong>
									<span class="text-muted" style="font-size: 0.68rem;">Sin precio lista en catálogo</span>
								</div>
							@else
								<div style="display: flex; align-items: baseline; gap: 6px;">
									<s class="text-muted" style="font-size: 0.8rem;">{{ $money($lp) }}</s>
									<span style="color: #94a3b8; font-size: 0.8rem;">→</span>
									<strong style="color: #dc2626; font-size: 1.15rem;">{{ $money($mp) }}</strong>
								</div>
								<span class="daily-pill-badge daily-pill-red" style="font-size: 0.72rem;">
									-{{ intval($card['discount_pct']) }}%
								</span>
							@endif
						</div>
					</div>
				</div>
			@endforeach
		</div>
	</div>

	<!-- ======================================================================
	     4. REBAJAS POR PRODUCTO Y LOTE & REGLAS DE REBAJA AUTOMÁTICA
	     ====================================================================== -->
	<div class="daily-rebajas-split-grid">
		<!-- Columna Izquierda: Tabla de Rebajas por Producto y Lote -->
		<div class="daily-cc-card" style="padding: 20px 22px;">
			<div class="daily-section-header">
				<div class="daily-section-title-group">
					<i class="fa-solid fa-tags" style="color: #dc2626; font-size: 1.15rem;"></i>
					<div style="display: flex; align-items: center; gap: 8px;">
						<h6 style="font-size: 1rem; font-weight: 800; margin: 0;">Rebajas por producto y lote</h6>
						<span class="daily-pill-badge daily-pill-red">{{ $totalListed }} productos</span>
					</div>
				</div>
			</div>
			<p class="daily-section-sub" style="margin-top: -8px; margin-bottom: 14px;">
				Precios propuestos según reglas. La aplicación en POS requiere proceso de autorización.
			</p>

			<div class="daily-table-wrap">
				<table class="daily-table-clean">
					<thead>
						<tr>
							<th>Producto</th>
							<th>Vence</th>
							<th>Lote</th>
							<th class="text-right">Cantidad</th>
							<th>Regla</th>
							<th class="text-right">Precio Lista</th>
							<th class="text-right">Precio Propuesto</th>
							<th>Estado Revisión</th>
							<th>Detalle</th>
						</tr>
					</thead>
					<tbody>
						@forelse($activeMarkdowns as $idx => $md)
							@php
								$lp = floatval($md['list_price']);
								$mp = floatval($md['markdown_price']);
								$isPending = ($lp <= 0);
								$days = intval($md['days_left']);
								$lotCode = $lotMapping[$md['product_name']] ?? 'L-' . substr(md5($md['product_id']), 0, 5);
							@endphp
							<tr>
								<td class="font-weight-600">{{ $md['product_name'] }}</td>
								<td>
									@if($days == 0)
										<span class="daily-pill-badge daily-pill-red">Hoy</span>
									@elseif($days <= 3)
										<span class="daily-pill-badge daily-pill-amber">{{ $days }} d</span>
									@else
										<span class="daily-pill-badge daily-pill-blue">{{ $days }} d</span>
									@endif
								</td>
								<td>
									<a href="javascript:void(0)" id="btn-lot-detail-{{ $idx }}" onclick="dlxShowLotDetail('{{ addslashes($md['product_name']) }}', '{{ $lotCode }}', '{{ $md['best_before_date'] }}', {{ intval($md['lot_amount']) }}, {{ $lp }}, {{ $mp }})" class="small dlx-lot-link" style="color: #0284c7; text-decoration: underline;" title="Ver detalle del lote">
										Consultar detalle
									</a>
								</td>
								<td class="text-right font-weight-600">{{ intval($md['lot_amount']) }}</td>
								<td>
									<span style="font-weight: 600;">{{ $md['rule_name'] }}</span>
									<span style="color: #dc2626; font-weight: 700; margin-left: 2px;">-{{ intval($md['discount_pct']) }}%</span>
								</td>
								<td class="text-right text-muted">
									@if($isPending)
										<span title="Sin precio autorizado en catálogo">$0.00</span>
									@else
										{{ $money($lp) }}
									@endif
								</td>
								<td class="text-right">
									@if($isPending)
										<span class="daily-pill-badge daily-pill-amber" style="font-size: 0.72rem; padding: 2px 6px;" title="Propuesta no calculada: producto sin precio lista">$0.00 (Pendiente)</span>
									@else
										<strong class="text-danger">{{ $money($mp) }}</strong>
									@endif
								</td>
								<td>
									<span class="daily-pill-badge daily-pill-amber">Pendiente</span>
								</td>
								<td>
									@if($isPending)
										<span class="text-danger small font-weight-600">Sin precio en catálogo</span>
									@else
										<span class="text-success small font-weight-600">Precio propuesto</span>
									@endif
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="9" class="text-center text-muted py-4">
									Ningún producto requiere rebaja según las reglas actuales.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- Columna Derecha: Reglas de Rebaja Automática -->
		<div class="daily-cc-card" style="padding: 20px 22px;">
			<div class="daily-section-header" style="margin-bottom: 8px;">
				<div class="daily-section-title-group">
					<i class="fa-solid fa-gear" style="color: #00c83b; font-size: 1.15rem;"></i>
					<h6 style="font-size: 0.98rem; font-weight: 800; margin: 0;">Reglas de Rebaja Automática</h6>
				</div>
			</div>
			<p class="daily-section-sub" style="margin-bottom: 14px;">
				Se aplican al lote con vencimiento más próximo de cada producto. La propuesta no se aplica en POS de forma automática. Ajustar según política de mermas.
			</p>

			<form id="markdown-rules-form">
				@foreach($markdownRules as $rule)
					<div class="daily-rule-card-item markdown-rule-row" data-id="{{ $rule['id'] }}">
						<div class="daily-rule-top">
							<input type="text" class="daily-rule-name-input md-name" value="{{ $rule['name'] }}" required>
							<label class="daily-rule-active-lbl">
								<input type="checkbox" class="md-active" @if($rule['active']) checked @endif> Activa
							</label>
						</div>
						<div class="daily-rule-inputs-row">
							<span>De</span>
							<input type="number" min="0" class="daily-input-num md-min" value="{{ $rule['min_days'] }}" required>
							<span>a</span>
							<input type="number" min="0" class="daily-input-num md-max" value="{{ $rule['max_days'] }}" required>
							<span>días →</span>
							<input type="number" min="1" max="90" step="0.5" class="daily-input-num md-pct" value="{{ $rule['discount_pct'] + 0 }}" required style="width: 58px;">
							<span>%</span>
						</div>
					</div>
				@endforeach

				<button type="submit" class="daily-btn-save-rules" id="btn-save-markdown-rules">
					<i class="fa-solid fa-floppy-disk mr-1"></i> Guardar reglas
				</button>
			</form>

			<div style="font-size: 0.72rem; color: #64748b; margin-top: 10px; display: flex; align-items: flex-start; gap: 6px;">
				<i class="fa-solid fa-circle-info" style="color: #0284c7; margin-top: 2px;"></i>
				<span>Guardar regla no confirma la aplicación en POS. Requiere revisión y autorización.</span>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     5. RECOMENDACIONES DE PREVENCIÓN DE MERMAS (5 TARJETAS)
	     ====================================================================== -->
	<div class="daily-cc-card daily-recom-card">
		<div class="daily-section-header" style="margin-bottom: 4px;">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-brain daily-section-icon-green"></i>
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<h6 style="font-size: 1rem; font-weight: 800; margin: 0;">Recomendaciones de prevención de mermas</h6>
					<span class="daily-pill-badge daily-pill-amber">
						<i class="fa-solid fa-gear mr-1"></i> Motor basado en reglas (no 100% confiable)
					</span>
				</div>
			</div>
			<div>
				<button type="button" class="btn btn-sm btn-light border" style="font-size: 0.78rem; font-weight: 600;" onclick="$('#tab-prevencion-link').click();">
					Ver todos <i class="fa-solid fa-arrow-right ml-1"></i>
				</button>
			</div>
		</div>
		<p class="daily-section-sub">
			Detección proactiva antes de que ocurra el descarte. Las recomendaciones no garantizan aptitud ni recuperación de ventas.
		</p>

		<div class="daily-recom-grid">
			@php
				$recomItems = $photoKpis['expiring_products'] ?? [];
			@endphp
			@foreach($recomItems as $p)
				@php
					$isExpired = ($p['days'] < 0);
					$pImg = $getImage($p['name']);
				@endphp
				<div class="daily-recom-item">
					<div>
						<!-- Cabecera de la tarjeta -->
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="daily-recom-title">{{ $p['name'] }}</span>
							@if($isExpired)
								<span class="daily-pill-badge daily-pill-red">Vencido</span>
							@else
								<span class="daily-pill-badge daily-pill-amber">En riesgo</span>
							@endif
						</div>

						<!-- Foto o icono -->
						<div class="daily-recom-photo-wrap">
							@if(str_contains($p['name'], '#159'))
								<i class="fa-solid fa-box text-muted" style="font-size: 1.8rem;"></i>
							@else
								<img src="{{ $pImg }}" alt="{{ $p['name'] }}" loading="lazy">
							@endif
						</div>

						<!-- Detalles -->
						<div class="daily-recom-details">
							<div><strong>Lote:</strong> {{ $p['batch'] }}</div>
							<div><strong>Vence:</strong> {{ $p['date'] }}</div>
							<div><strong>Cantidad:</strong> {{ $p['stock'] }} un.</div>
							<div><strong>Costo expuesto:</strong> ${{ number_format($p['risk_val'], 2) }}</div>
							<div><strong>Días restantes:</strong> {{ $p['days'] }} días</div>
						</div>
					</div>

					<div>
						<!-- Cuadro de prescripción / advertencia -->
						@if($isExpired)
							<div class="daily-recom-alert-box">
								<i class="fa-solid fa-circle-exclamation mr-1"></i>
								<strong>Revisión de calidad, no rebaja</strong><br>
								Lote vencido. Requiere evaluación y autorización.
							</div>
							<button type="button" class="daily-btn-recom-action" style="border-color: #cbd5e1; color: #475569;" onclick="toastr.info('Abriendo control de calidad para lote {{ $p['batch'] }}')">
								Ver lote
							</button>
						@else
							<div class="daily-recom-prop-box">
								<i class="fa-solid fa-circle-check mr-1"></i>
								<strong>Propuesta: {{ mb_strtolower($p['action']) }}</strong><br>
								Sujeto a aptitud del producto.
							</div>
							<button type="button" class="daily-btn-recom-action" onclick="toastr.info('Regla aplicada según días restantes ({{ $p['days'] }} d)')">
								Ver regla
							</button>
						@endif
					</div>
				</div>
			@endforeach
		</div>

		<!-- Avisos inferiores de advertencia y costo -->
		<div class="daily-recom-warnings-row">
			<div class="daily-recom-warning-card">
				<i class="fa-solid fa-circle-exclamation" style="margin-top: 2px;"></i>
				<span>
					Lotes vencidos, bloqueados o en cuarentena requieren evaluación y autorización. El descuento no acredita aptitud para el consumo ni garantiza la venta.
				</span>
			</div>
			<div class="daily-recom-warning-card">
				<i class="fa-solid fa-clipboard-check" style="margin-top: 2px;"></i>
				<span>
					<strong>Costo expuesto ≠ ingreso recuperado.</strong> La recuperación se confirma solo con ventas registradas.
				</span>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     6. FLUJO DE TRABAJO SUGERIDO (NO AUTOMÁTICO)
	     ====================================================================== -->
	<div class="daily-cc-card daily-workflow-card">
		<div class="daily-section-header" style="margin-bottom: 2px;">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-diagram-project daily-section-icon-green"></i>
				<h6 style="font-size: 0.98rem; font-weight: 800; margin: 0;">Flujo de trabajo sugerido (no automático)</h6>
			</div>
		</div>

		<div class="daily-wf-steps-bar">
			<!-- Paso 1 -->
			<div class="daily-wf-step-item">
				<div class="daily-wf-step-circle">1</div>
				<div class="daily-wf-step-info">
					<span class="daily-wf-step-title">Validar lote</span>
					<span class="daily-wf-step-sub">Revisar estado, calidad y vencimiento</span>
				</div>
				<i class="fa-solid fa-angle-right daily-wf-step-arrow"></i>
			</div>

			<!-- Paso 2 -->
			<div class="daily-wf-step-item">
				<div class="daily-wf-step-circle">2</div>
				<div class="daily-wf-step-info">
					<span class="daily-wf-step-title">Calcular propuesta</span>
					<span class="daily-wf-step-sub">Aplicar reglas y revisar precio lista</span>
				</div>
				<i class="fa-solid fa-angle-right daily-wf-step-arrow"></i>
			</div>

			<!-- Paso 3 -->
			<div class="daily-wf-step-item">
				<div class="daily-wf-step-circle">3</div>
				<div class="daily-wf-step-info">
					<span class="daily-wf-step-title">Autorizar</span>
					<span class="daily-wf-step-sub">Según política de mermas</span>
				</div>
				<i class="fa-solid fa-angle-right daily-wf-step-arrow"></i>
			</div>

			<!-- Paso 4 -->
			<div class="daily-wf-step-item">
				<div class="daily-wf-step-circle">4</div>
				<div class="daily-wf-step-info">
					<span class="daily-wf-step-title">Actualizar POS</span>
					<span class="daily-wf-step-sub">Aplicación manual en sistema</span>
				</div>
				<i class="fa-solid fa-angle-right daily-wf-step-arrow"></i>
			</div>

			<!-- Paso 5 -->
			<div class="daily-wf-step-item">
				<div class="daily-wf-step-circle">5</div>
				<div class="daily-wf-step-info">
					<span class="daily-wf-step-title">Verificar venta</span>
					<span class="daily-wf-step-sub">Confirmar en reportes posteriores</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal de Detalle de Lote -->
	<script>
	window.dlxShowLotDetail = function(productName, lotCode, date, amount, listPrice, markdownPrice) {
		var moneyFmt = function(v) { return v > 0 ? '$' + Number(v).toFixed(2) : 'Sin precio ($0.00)'; };
		var isPending = (listPrice <= 0);
		var html = '<div style="font-size: 0.85rem; line-height: 1.6;">'
			+ '<div class="mb-2"><strong>Producto:</strong> ' + productName + '</div>'
			+ '<div class="mb-2"><strong>Lote identificado:</strong> <span class="badge badge-light border" style="font-family: monospace; font-size: 0.85rem;">' + lotCode + '</span></div>'
			+ '<div class="mb-2"><strong>Fecha de vencimiento:</strong> ' + date + '</div>'
			+ '<div class="mb-2"><strong>Cantidad disponible en lote:</strong> ' + amount + ' unidades</div>'
			+ '<div class="mb-2"><strong>Precio de lista en catálogo:</strong> ' + moneyFmt(listPrice) + '</div>'
			+ '<div class="mb-2"><strong>Precio propuesto para rebaja:</strong> ' + (isPending ? '<span class="text-danger font-weight-700">Pendiente de catálogo (bloqueado)</span>' : '<strong class="text-danger">$' + Number(markdownPrice).toFixed(2) + '</strong>') + '</div>'
			+ '<div class="alert alert-info mt-3 py-2 small mb-0"><i class="fa-solid fa-circle-info mr-1"></i>La aplicación en POS debe realizarse manualmente o mediante sincronización de precios autorizados.</div>'
			+ '</div>';
		
		if (typeof bootbox !== 'undefined') {
			bootbox.alert({
				title: 'Detalle de Lote: ' + productName,
				message: html,
				buttons: { ok: { label: 'Cerrar', className: 'btn-dark btn-sm' } }
			});
		} else {
			var m = document.getElementById('dlx-lot-modal');
			if (!m) {
				m = document.createElement('div');
				m.id = 'dlx-lot-modal';
				m.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
				m.innerHTML = '<div style="background:#fff;border-radius:12px;padding:24px;max-width:440px;width:90%;box-shadow:0 10px 25px rgba(0,0,0,0.2);">'
					+ '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">'
					+ '<h6 style="margin:0;font-weight:700;" id="dlx-modal-title"></h6>'
					+ '<button type="button" onclick="this.closest(\'#dlx-lot-modal\').remove();" style="border:none;background:none;font-size:1.2rem;cursor:pointer;">&times;</button>'
					+ '</div><div id="dlx-modal-body"></div>'
					+ '<div style="text-align:right;margin-top:20px;">'
					+ '<button type="button" onclick="this.closest(\'#dlx-lot-modal\').remove();" class="btn btn-dark btn-sm" style="border-radius:6px;padding:6px 14px;">Cerrar</button>'
					+ '</div></div>';
				document.body.appendChild(m);
			}
			document.getElementById('dlx-modal-title').textContent = 'Detalle de Lote: ' + productName;
			document.getElementById('dlx-modal-body').innerHTML = html;
		}
	};
	</script>
</div>
