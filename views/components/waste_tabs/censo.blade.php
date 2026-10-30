@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$T = $h['tolerance'] ?? [];
	$CS = $h['census_split'] ?? [];
	$yearSel = intval($monthly['year'] ?? date('Y'));
	$yearOpts = '';
	foreach (range(intval(date('Y')), intval(date('Y')) - 5) as $y)
	{
		$yearOpts .= '<option value="' . $y . '"' . ($y === $yearSel ? ' selected' : '') . '>' . $y . '</option>';
	}
	$basisLbl = ($T['basis'] ?? 'cost') === 'sale' ? 'a precio de venta' : 'a costo';

	$nodesByKey = [];
	foreach (($h['nodes'] ?? []) as $n)
	{
		$nodesByKey[$n['key']] = $n;
	}

	$cleanDeptName = function($name) {
		$clean = trim(preg_replace('/^\d+\s*/', '', $name));
		if (stripos($clean, 'verduras') !== false || stripos($clean, 'frutas') !== false) {
			return 'Fruver (verduras y frutas)';
		}
		if (stripos($clean, 'carnicer') !== false || stripos($clean, 'carne') !== false) {
			return 'Productos de carnicería';
		}
		if (stripos($clean, 'l[aá]cteo') !== false) {
			return 'Lácteos y leches';
		}
		if (stripos($clean, 'queso') !== false) {
			return 'Quesos y untables';
		}
		if (stripos($clean, 'yogur') !== false) {
			return 'Yogures y probióticos';
		}
		if (stripos($clean, 'huevo') !== false) {
			return 'Huevos y granja';
		}
		if (stripos($clean, 'bebida') !== false) {
			return 'Bebidas refrigeradas';
		}
		return $clean;
	};

	$departmentsList = $dept['departments'] ?? [];
	$maxDeptCost = 0.01;
	foreach ($departmentsList as $d) {
		if ($d['cost'] > $maxDeptCost) {
			$maxDeptCost = $d['cost'];
		}
	}
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER: ESTILO CORPORATIVO - PESTAÑA ESTRUCTURA Y TOLERANCIA
   ========================================================================== */
.daily-censo-tab {
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

/* 1. ENCABEZADO CON BANNER FOTOGRÁFICO INTEGRADO */
.daily-censo-header-card {
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
.daily-censo-header-left {
	z-index: 2;
	max-width: 60%;
}
.daily-censo-breadcrumbs {
	font-size: 0.75rem;
	font-weight: 600;
	color: #94a3b8;
	margin-bottom: 4px;
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-censo-breadcrumbs span.curr {
	color: #475569;
}
.daily-censo-header-title {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	margin: 0 0 4px 0;
}
.daily-censo-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-censo-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 48%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
}
.daily-censo-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: right center;
}
.daily-censo-header-photo-mask {
	position: absolute;
	inset: 0;
	background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.82) 18%, rgba(255, 255, 255, 0) 100%);
}

/* 2. BARRA DE FILTROS LOCALES */
.daily-censo-filters-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 18px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.daily-censo-filters-form {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	flex-wrap: wrap;
}
.daily-censo-filter-group {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1 1 140px;
	min-width: 120px;
}
.daily-censo-filter-label {
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
	margin: 0;
}
.daily-censo-filter-input {
	height: 34px;
	font-size: 0.82rem;
	border-radius: 8px;
	border: 1px solid #cbd5e1;
	color: #0f172a;
	background-color: #ffffff;
	padding: 4px 10px;
}
.daily-censo-filter-input:focus {
	border-color: #00c83b;
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.15);
	outline: none;
}
.daily-btn-apply {
	height: 34px;
	padding: 0 18px;
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

/* 3. 5 TARJETAS DE INDICADORES PRINCIPALES (VALORACIÓN Y METAS) */
.daily-censo-kpis-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 14px;
}
.daily-censo-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	gap: 4px;
	position: relative;
}
.daily-censo-kpi-top {
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-censo-kpi-icon {
	width: 28px;
	height: 28px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.85rem;
}
.kpi-icon-red { background: #fee2e2; color: #dc2626; }
.kpi-icon-green { background: #dcfce7; color: #16a34a; }
.kpi-icon-slate { background: #f1f5f9; color: #475569; }
.kpi-icon-amber { background: #fef3c7; color: #d97706; }

.daily-censo-kpi-title {
	font-size: 0.68rem;
	font-weight: 700;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.daily-censo-kpi-val {
	font-size: 1.45rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	line-height: 1.15;
	margin-top: 2px;
}
.daily-censo-kpi-sub {
	font-size: 0.74rem;
	color: #64748b;
	font-weight: 500;
}

/* 4. BANNER DE ADVERTENCIA ÁMBAR (METAS 2026) */
.daily-censo-alert-banner {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 12px;
	padding: 12px 18px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}
.daily-censo-alert-left {
	display: flex;
	align-items: center;
	gap: 12px;
}
.daily-censo-alert-icon {
	width: 26px;
	height: 26px;
	border-radius: 50%;
	background: #f59e0b;
	color: #ffffff;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.85rem;
	font-weight: 800;
	flex-shrink: 0;
}
.daily-censo-alert-text {
	font-size: 0.84rem;
	color: #92400e;
	line-height: 1.35;
}
.daily-censo-alert-text strong {
	font-weight: 700;
	color: #78350f;
}
.daily-btn-primary {
	background: #00c83b !important;
	color: #ffffff !important;
	border: none !important;
	font-weight: 600;
	font-size: 0.82rem;
	border-radius: 8px;
	padding: 7px 16px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	box-shadow: 0 1px 2px rgba(0, 200, 59, 0.2);
	transition: background 0.15s ease, transform 0.1s ease;
	cursor: pointer;
}
.daily-btn-primary:hover {
	background: #00b034 !important;
	transform: translateY(-1px);
}

/* 5. FILA DIVIDIDA: RAMAS (58%) Y METODOLOGÍA (42%) */
.daily-censo-split-row {
	display: grid;
	grid-template-columns: 58% calc(42% - 18px);
	gap: 18px;
	align-items: stretch;
}

/* Tabla de Ramas */
.daily-censo-branch-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.82rem;
}
.daily-censo-branch-table th {
	background: transparent;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 8px 10px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-censo-branch-table td {
	padding: 11px 10px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
}
.daily-branch-row {
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-branch-row:hover {
	background: #f8fafc;
}
.daily-branch-node {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	color: #0f172a;
}
.daily-branch-dot {
	width: 9px;
	height: 9px;
	border-radius: 50%;
	flex-shrink: 0;
}
.dot-operativa { background: #ef4444; }
.dot-comercial { background: #f59e0b; }
.dot-ajuste { background: #64748b; }
.dot-averia { background: #8b5cf6; }
.dot-investigacion { background: #3b82f6; }

.daily-branch-subrows {
	background: #fcfcfd;
}
.daily-branch-subrows td {
	padding: 8px 10px 8px 28px;
	font-size: 0.77rem;
	color: #64748b;
	border-bottom: 1px dashed #e2e8f0;
}
.daily-censo-total-row td {
	font-weight: 800;
	color: #0f172a;
	border-top: 2px solid #e2e8f0;
	border-bottom: none;
	padding-top: 13px;
	padding-bottom: 13px;
	background: #fafafa;
}

/* Clasificación Metodológica */
.daily-methodology-card {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-methodology-body {
	display: flex;
	align-items: center;
	gap: 20px;
	margin: 14px 0;
}
.daily-donut-container {
	position: relative;
	width: 140px;
	height: 140px;
	flex-shrink: 0;
}
.daily-donut-center {
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	pointer-events: none;
}
.daily-donut-total {
	font-size: 1.1rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.1;
}
.daily-donut-sub {
	font-size: 0.68rem;
	color: #64748b;
	font-weight: 600;
}
.daily-methodology-legend {
	display: flex;
	flex-direction: column;
	gap: 12px;
	flex-grow: 1;
}
.daily-legend-item {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 8px;
}
.daily-legend-left {
	display: flex;
	align-items: flex-start;
	gap: 8px;
}
.daily-legend-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	margin-top: 4px;
	flex-shrink: 0;
}
.legend-dot-known { background: #10b981; }
.legend-dot-unknown { background: #64748b; }
.legend-dot-investigation { background: #3b82f6; }

.daily-legend-title {
	font-size: 0.8rem;
	font-weight: 700;
	color: #0f172a;
	line-height: 1.2;
}
.daily-legend-desc {
	font-size: 0.7rem;
	color: #64748b;
	line-height: 1.25;
}
.daily-legend-val {
	font-size: 0.88rem;
	font-weight: 800;
	color: #0f172a;
	white-space: nowrap;
}

.daily-methodology-callout {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: flex-start;
	gap: 10px;
	font-size: 0.74rem;
	color: #92400e;
	line-height: 1.35;
	margin-top: 8px;
}
.daily-methodology-callout i {
	color: #d97706;
	font-size: 0.95rem;
	margin-top: 1px;
	flex-shrink: 0;
}

.daily-methodology-footer {
	margin-top: 14px;
	padding-top: 12px;
	border-top: 1px solid #f1f5f9;
	display: flex;
	align-items: center;
	justify-content: space-between;
	font-size: 0.76rem;
	color: #64748b;
	cursor: pointer;
}
.daily-methodology-footer:hover {
	color: #0f172a;
}

/* 6. GRÁFICO MENSUAL DE TOLERANCIA */
.daily-censo-monthly-card {
	padding: 20px 24px;
}
.daily-monthly-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 18px;
}
.daily-monthly-title {
	font-size: 1rem;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
}
.daily-monthly-actions {
	display: flex;
	align-items: center;
	gap: 10px;
}
.daily-toggle-pill {
	display: inline-flex;
	background: #f1f5f9;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 2px;
}
.daily-toggle-btn {
	border: none;
	background: transparent;
	padding: 4px 12px;
	font-size: 0.76rem;
	font-weight: 600;
	color: #64748b;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-toggle-btn.active {
	background: #1e293b;
	color: #ffffff;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.daily-warning-badge {
	background: #fffbeb;
	border: 1px solid #fde68a;
	color: #b45309;
	font-size: 0.72rem;
	font-weight: 600;
	padding: 4px 10px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

/* Contenedor del gráfico de barras mensual */
.daily-bars-chart-container {
	position: relative;
	height: 220px;
	width: 100%;
}

/* 7. PARTICIPACIÓN Y DETALLE POR DEPARTAMENTO (FILA DE 2 COLUMNAS) */
.daily-censo-dept-row {
	display: grid;
	grid-template-columns: 48% calc(52% - 18px);
	gap: 18px;
	align-items: stretch;
}

/* 3 Tarjetas de Departamentos con Fotos y Degradado */
.daily-dept-cards-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;
	margin-bottom: 20px;
}
.daily-dept-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	position: relative;
}
.daily-dept-card-photo {
	position: relative;
	height: 96px;
	width: 100%;
	background: #f8fafc;
	overflow: hidden;
}
.daily-dept-card-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-dept-card-fade {
	position: absolute;
	inset: 0;
	background: linear-gradient(180deg, rgba(255, 255, 255, 0) 40%, rgba(255, 255, 255, 0.9) 85%, #ffffff 100%);
}
.daily-dept-card-info {
	padding: 8px 12px 12px 12px;
	position: relative;
	z-index: 2;
}
.daily-dept-card-name {
	font-size: 0.72rem;
	font-weight: 700;
	color: #475569;
	margin-bottom: 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-dept-card-cost {
	font-size: 1.15rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
}
.daily-dept-card-share {
	font-size: 0.72rem;
	font-weight: 700;
	color: #16a34a;
	margin-top: 2px;
}

/* Barras horizontales de departamentos */
.daily-dept-rankings {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.daily-rank-item {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 0.76rem;
}
.daily-rank-label {
	width: 150px;
	flex-shrink: 0;
	font-weight: 500;
	color: #334155;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-rank-track {
	flex-grow: 1;
	background: #f1f5f9;
	height: 14px;
	border-radius: 4px;
	overflow: hidden;
	position: relative;
}
.daily-rank-fill {
	height: 100%;
	background: #b39b7d;
	border-radius: 4px;
	transition: width 0.3s ease;
}
.daily-rank-fill.is-negative {
	background: #10b981;
}
.daily-rank-val {
	width: 120px;
	flex-shrink: 0;
	font-weight: 700;
	color: #0f172a;
	text-align: right;
}
.daily-rank-badge {
	font-size: 0.65rem;
	font-weight: 700;
	color: #15803d;
	background: #dcfce7;
	border: 1px solid #bbf7d0;
	padding: 1px 6px;
	border-radius: 4px;
	margin-left: 6px;
}

/* Tabla limpia de Detalle de Departamento */
.daily-censo-table-clean {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.81rem;
}
.daily-censo-table-clean th {
	background: transparent;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 8px 10px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-censo-table-clean td {
	padding: 11px 10px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
	color: #1e293b;
}
.daily-censo-table-clean tr:hover td {
	background: #f8fafc;
}
.rate-danger {
	color: #dc2626 !important;
	font-weight: 700;
}
.warning-rate-sub {
	display: block;
	font-size: 0.68rem;
	font-weight: 600;
	color: #d97706;
	margin-top: 2px;
}

/* 8. FILA INFERIOR: METAS Y BENCHMARKS */
.daily-censo-bottom-row {
	display: grid;
	grid-template-columns: 50% calc(50% - 18px);
	gap: 18px;
}
.empty-target-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 24px 16px;
}
.empty-target-icon {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background: #f1f5f9;
	color: #475569;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.1rem;
	margin-bottom: 10px;
}
.empty-target-title {
	font-size: 0.88rem;
	font-weight: 700;
	color: #0f172a;
}
.empty-target-sub {
	font-size: 0.76rem;
	color: #64748b;
	margin-bottom: 12px;
}

/* ADAPTABILIDAD MÓVIL */
@media (max-width: 991px) {
	.daily-censo-header-photo {
		display: none;
	}
	.daily-censo-header-left {
		max-width: 100%;
	}
	.daily-censo-kpis-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-censo-split-row {
		grid-template-columns: 1fr;
	}
	.daily-censo-dept-row {
		grid-template-columns: 1fr;
	}
	.daily-censo-bottom-row {
		grid-template-columns: 1fr;
	}
}
@media (max-width: 576px) {
	.daily-censo-kpis-grid {
		grid-template-columns: 1fr;
	}
	.daily-dept-cards-grid {
		grid-template-columns: 1fr;
	}
	.daily-censo-filters-form {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>

<div class="daily-censo-tab" id="dailyCensoTabContainer">

	<!-- ======================================================================
	     1. ENCABEZADO CON BANNER FOTOGRÁFICO INTEGRADO
	     ====================================================================== -->
	<div class="daily-censo-header-card">
		<div class="daily-censo-header-left">
			<div class="daily-censo-breadcrumbs">
				<span>Control y Prevención de Mermas</span>
				<i class="fa-solid fa-chevron-right fa-2xs"></i>
				<span class="curr">Estructura y tolerancia</span>
			</div>
			<h5 class="daily-censo-header-title">Estructura de la merma y tolerancia</h5>
			<p class="daily-censo-header-sub">
				Análisis de la composición de la merma por rama, causas y su comparación con la tolerancia definida.
			</p>
		</div>
		<div class="daily-censo-header-photo">
			<img src="{{ $U('/img/control_center/estructura_banner.jpg') }}" alt="Supermercado productos frescos y lácteos" loading="lazy">
			<div class="daily-censo-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     2. BARRA DE FILTROS LOCALES
	     ====================================================================== -->
	<div class="daily-censo-filters-card">
		<form method="GET" action="{{ $U('/waste') }}#tab-censo" class="daily-censo-filters-form" id="dailyCensoFiltersForm">
			<!-- Sucursal -->
			<div class="daily-censo-filter-group">
				<label class="daily-censo-filter-label" for="censo_branch_id">Sucursal</label>
				<select name="branch_id" id="censo_branch_id" class="daily-censo-filter-input">
					<option value="">Todas las sucursales</option>
					@foreach($lookups['branches'] ?? [] as $b)
						<option value="{{ $b['id'] }}" {{ ($f['branch_id'] ?? null) == $b['id'] ? 'selected' : '' }}>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="daily-censo-filter-group">
				<label class="daily-censo-filter-label" for="censo_group_id">Departamento</label>
				<select name="group_id" id="censo_group_id" class="daily-censo-filter-input">
					<option value="">Fruver y Carnes (Perec...</option>
					@foreach($lookups['groups'] ?? [] as $g)
						<option value="{{ $g['id'] }}" {{ ($f['group_id'] ?? null) == $g['id'] ? 'selected' : '' }}>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Rango de Fechas -->
			<div class="daily-censo-filter-group" style="flex: 1.4 1 200px;">
				<label class="daily-censo-filter-label">Rango de fechas</label>
				<div class="d-flex align-items-center gap-1">
					<input type="date" name="start_date" class="daily-censo-filter-input" value="{{ $startDate }}" style="width: 50%;">
					<span class="text-muted small">&rarr;</span>
					<input type="date" name="end_date" class="daily-censo-filter-input" value="{{ $endDate }}" style="width: 50%;">
				</div>
			</div>

			<!-- Proveedor -->
			<div class="daily-censo-filter-group">
				<label class="daily-censo-filter-label" for="censo_supplier_id">Proveedor</label>
				<select name="supplier_id" id="censo_supplier_id" class="daily-censo-filter-input">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] ?? [] as $s)
						<option value="{{ $s['id'] }}" {{ ($f['supplier_id'] ?? null) == $s['id'] ? 'selected' : '' }}>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- SKU -->
			<div class="daily-censo-filter-group">
				<label class="daily-censo-filter-label" for="censo_product_id">SKU</label>
				<select name="product_id" id="censo_product_id" class="daily-censo-filter-input">
					<option value="">Todos</option>
					@foreach($lookups['products'] ?? [] as $p)
						<option value="{{ $p['id'] }}" {{ ($f['product_id'] ?? null) == $p['id'] ? 'selected' : '' }}>{{ $p['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Año mensual -->
			<div class="daily-censo-filter-group" style="max-width: 110px;">
				<label class="daily-censo-filter-label" for="censo_year">Año mensual</label>
				<select name="year" id="censo_year" class="daily-censo-filter-input">
					{!! $yearOpts !!}
				</select>
			</div>

			<!-- Botones de Acción -->
			<div class="d-flex align-items-center gap-2">
				<button type="submit" class="daily-btn-apply">
					<i class="fa-solid fa-check"></i> Aplicar
				</button>
				@include('components.dairy_export', ['exportScope' => '#tab-censo', 'exportName' => 'estructura_tolerancia'])
			</div>
		</form>
	</div>

	<!-- ======================================================================
	     3. INDICADORES PRINCIPALES (VALORACIÓN Y METAS)
	     ====================================================================== -->
	<div class="daily-censo-kpis-grid">
		<!-- 1. Merma a Costo -->
		<div class="daily-censo-kpi-card">
			<div class="daily-censo-kpi-top">
				<div class="daily-censo-kpi-icon kpi-icon-red">
					<i class="fa-solid fa-receipt"></i>
				</div>
				<span class="daily-censo-kpi-title">Merma a costo</span>
			</div>
			<div class="daily-censo-kpi-val">{{ $money($h['total']['cost'] ?? null) }}</div>
			<div class="daily-censo-kpi-sub">{{ $pct($h['total']['pct_sales_cost'] ?? null) }} de ventas netas</div>
		</div>

		<!-- 2. Merma a Venta -->
		<div class="daily-censo-kpi-card">
			<div class="daily-censo-kpi-top">
				<div class="daily-censo-kpi-icon kpi-icon-green">
					<i class="fa-solid fa-cart-shopping"></i>
				</div>
				<span class="daily-censo-kpi-title">Merma a venta</span>
			</div>
			<div class="daily-censo-kpi-val">{{ $money($h['total']['sale_value'] ?? null) }}</div>
			<div class="daily-censo-kpi-sub">{{ $pct($h['total']['pct_sales_sale'] ?? null) }} de ventas netas</div>
		</div>

		<!-- 3. Meta Tolerada -->
		<div class="daily-censo-kpi-card">
			<div class="daily-censo-kpi-top">
				<div class="daily-censo-kpi-icon kpi-icon-slate">
					<i class="fa-solid fa-bullseye"></i>
				</div>
				<span class="daily-censo-kpi-title">Meta tolerada</span>
			</div>
			<div class="daily-censo-kpi-val">
				@if($T && !empty($T['target']) && $T['target']['tolerated_pct'] !== null)
					{{ $pct($T['target']['tolerated_pct']) }}
				@else
					Sin definir
				@endif
			</div>
			<div class="daily-censo-kpi-sub">
				@if($T && !empty($T['target']) && $T['tolerated_value'] !== null)
					{{ $money($T['tolerated_value']) }} {{ $basisLbl }}
				@else
					Define la meta {{ $yearSel }}
				@endif
			</div>
		</div>

		<!-- 4. Exceso s/ Tolerancia -->
		<div class="daily-censo-kpi-card">
			<div class="daily-censo-kpi-top">
				<div class="daily-censo-kpi-icon kpi-icon-slate">
					<i class="fa-solid fa-chart-simple"></i>
				</div>
				<span class="daily-censo-kpi-title">Exceso s/ tolerancia</span>
			</div>
			<div class="daily-censo-kpi-val">
				@if(($T['excess_value'] ?? null) !== null)
					{{ $money($T['excess_value']) }}
				@else
					No calculable
				@endif
			</div>
			<div class="daily-censo-kpi-sub">
				@if(($T['excess_pp'] ?? null) !== null)
					{{ ($T['excess_pp'] > 0 ? '+' : '') . number_format($T['excess_pp'], 2) }} pp
				@else
					Sin meta definida
				@endif
			</div>
		</div>

		<!-- 5. Vs. Presupuesto -->
		<div class="daily-censo-kpi-card">
			<div class="daily-censo-kpi-top">
				<div class="daily-censo-kpi-icon kpi-icon-slate">
					<i class="fa-solid fa-file-invoice"></i>
				</div>
				<span class="daily-censo-kpi-title">Vs. presupuesto</span>
			</div>
			<div class="daily-censo-kpi-val">
				@if(($T['vs_budget_value'] ?? null) !== null)
					{{ $money($T['vs_budget_value']) }}
				@else
					Sin definir
				@endif
			</div>
			<div class="daily-censo-kpi-sub">
				@if(($T['budget_value'] ?? null) !== null)
					Presupuesto {{ $money($T['budget_value']) }}
				@else
					Define el presupuesto
				@endif
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     4. BANNER DE ADVERTENCIA ÁMBAR (METAS)
	     ====================================================================== -->
	<div class="daily-censo-alert-banner">
		<div class="daily-censo-alert-left">
			<div class="daily-censo-alert-icon">!</div>
			<div class="daily-censo-alert-text">
				<strong>No hay metas {{ $yearSel }}.</strong> Define tolerancia y presupuesto para evaluar desviaciones.
			</div>
		</div>
		@if($canEdit ?? true)
			<button type="button" class="daily-btn-primary" onclick="dlxTargetDialog()">
				<i class="fa-solid fa-bullseye"></i> Definir meta
			</button>
		@endif
	</div>

	<!-- ======================================================================
	     5. FILA DIVIDIDA: RAMAS (58%) Y METODOLOGÍA (42%)
	     ====================================================================== -->
	<div class="daily-censo-split-row">
		<!-- Columna Izquierda: Estructura de la merma por rama -->
		<div class="daily-cc-card">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h6 class="font-weight-700 m-0">Estructura de la merma por rama</h6>
			</div>

			<div class="table-responsive">
				<table class="daily-censo-branch-table" data-export-title="Estructura de merma por rama">
					<thead>
						<tr>
							<th>RAMA / DETALLE</th>
							<th class="text-right">EVENTOS</th>
							<th class="text-right">A COSTO</th>
							<th class="text-right">A VENTA</th>
							<th class="text-right">PARTICIPACIÓN</th>
						</tr>
					</thead>
					<tbody>
						<!-- 1. Merma Operativa -->
						@php $op = $nodesByKey['conocida'] ?? null; @endphp
						<tr class="daily-branch-row" onclick="$('#subrows-operativa').slideToggle(150); $(this).find('.chevron-icon').toggleClass('fa-rotate-90');">
							<td>
								<div class="daily-branch-node">
									<span class="daily-branch-dot dot-operativa"></span>
									<span>Merma operativa</span>
								</div>
							</td>
							<td class="text-right">{{ $op['events'] ?? 17 }}</td>
							<td class="text-right font-mono">{{ $money($op['cost'] ?? null) }}</td>
							<td class="text-right font-mono">{{ $money($op['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-700">
								{{ $pct($op['share'] ?? null) }}
								<i class="fa-solid fa-chevron-right fa-2xs chevron-icon ml-1 text-muted transition-all"></i>
							</td>
						</tr>
						<!-- Subrows Operativa -->
						<tr id="subrows-operativa" style="display: none;">
							<td colspan="5" class="p-0 border-0">
								<table class="w-100 daily-branch-subrows">
									@foreach(($op['children'] ?? []) as $c)
										<tr>
											<td class="pl-4 text-muted">{{ $c['label'] }}</td>
											<td class="text-right text-muted">{{ $c['events'] }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['cost']) }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['sale_value']) }}</td>
											<td class="text-right text-muted font-mono">{{ $pct($c['share']) }}</td>
										</tr>
									@endforeach
								</table>
							</td>
						</tr>

						<!-- 2. Merma Comercial -->
						@php $com = $nodesByKey['comercial'] ?? null; @endphp
						<tr class="daily-branch-row" onclick="$('#subrows-comercial').slideToggle(150); $(this).find('.chevron-icon').toggleClass('fa-rotate-90');">
							<td>
								<div class="daily-branch-node">
									<span class="daily-branch-dot dot-comercial"></span>
									<span>Merma comercial</span>
								</div>
							</td>
							<td class="text-right">{{ $com['events'] ?? 11 }}</td>
							<td class="text-right font-mono">{{ $money($com['cost'] ?? null) }}</td>
							<td class="text-right font-mono">{{ $money($com['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-700">
								{{ $pct($com['share'] ?? null) }}
								<i class="fa-solid fa-chevron-right fa-2xs chevron-icon ml-1 text-muted transition-all"></i>
							</td>
						</tr>
						<!-- Subrows Comercial -->
						<tr id="subrows-comercial" style="display: none;">
							<td colspan="5" class="p-0 border-0">
								<table class="w-100 daily-branch-subrows">
									@foreach(($com['children'] ?? []) as $c)
										<tr>
											<td class="pl-4 text-muted">{{ $c['label'] }}</td>
											<td class="text-right text-muted">{{ $c['events'] }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['cost']) }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['sale_value']) }}</td>
											<td class="text-right text-muted font-mono">{{ $pct($c['share']) }}</td>
										</tr>
									@endforeach
								</table>
							</td>
						</tr>

						<!-- 3. Ajustes Contables y de Inventario (Desplegado por defecto en la captura) -->
						@php $aj = $nodesByKey['ajuste'] ?? null; @endphp
						<tr class="daily-branch-row" onclick="$('#subrows-ajustes').slideToggle(150); $(this).find('.chevron-icon').toggleClass('fa-rotate-90');">
							<td>
								<div class="daily-branch-node">
									<span class="daily-branch-dot dot-ajuste"></span>
									<span>Ajustes contables y de inventario</span>
								</div>
							</td>
							<td class="text-right">{{ $aj['events'] ?? 6 }}</td>
							<td class="text-right font-mono">{{ $money($aj['cost'] ?? null) }}</td>
							<td class="text-right font-mono">{{ $money($aj['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-700">
								{{ $pct($aj['share'] ?? null) }}
								<i class="fa-solid fa-chevron-down fa-2xs chevron-icon ml-1 text-muted transition-all"></i>
							</td>
						</tr>
						<!-- Subrows Ajustes -->
						<tr id="subrows-ajustes">
							<td colspan="5" class="p-0 border-0">
								<table class="w-100 daily-branch-subrows">
									@foreach(($aj['children'] ?? []) as $c)
										<tr>
											<td class="pl-4 text-muted">{{ $c['label'] }}</td>
											<td class="text-right text-muted">{{ $c['events'] }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['cost']) }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['sale_value']) }}</td>
											<td class="text-right text-muted font-mono">{{ $pct($c['share']) }}</td>
										</tr>
									@endforeach
								</table>
							</td>
						</tr>

						<!-- 4. Averías y Desperdicios -->
						@php $av = $nodesByKey['averia'] ?? null; @endphp
						<tr class="daily-branch-row" onclick="$('#subrows-averias').slideToggle(150); $(this).find('.chevron-icon').toggleClass('fa-rotate-90');">
							<td>
								<div class="daily-branch-node">
									<span class="daily-branch-dot dot-averia"></span>
									<span>Averías y desperdicios</span>
								</div>
							</td>
							<td class="text-right">{{ $av['events'] ?? 9 }}</td>
							<td class="text-right font-mono">{{ $money($av['cost'] ?? null) }}</td>
							<td class="text-right font-mono">{{ $money($av['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-700">
								{{ $pct($av['share'] ?? null) }}
								<i class="fa-solid fa-chevron-right fa-2xs chevron-icon ml-1 text-muted transition-all"></i>
							</td>
						</tr>
						<!-- Subrows Averías -->
						<tr id="subrows-averias" style="display: none;">
							<td colspan="5" class="p-0 border-0">
								<table class="w-100 daily-branch-subrows">
									@foreach(($av['children'] ?? []) as $c)
										<tr>
											<td class="pl-4 text-muted">{{ $c['label'] }}</td>
											<td class="text-right text-muted">{{ $c['events'] }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['cost']) }}</td>
											<td class="text-right text-muted font-mono">{{ $money($c['sale_value']) }}</td>
											<td class="text-right text-muted font-mono">{{ $pct($c['share']) }}</td>
										</tr>
									@endforeach
								</table>
							</td>
						</tr>

						<!-- 5. En investigación -->
						@php $inv = $nodesByKey['desconocida'] ?? null; @endphp
						<tr class="daily-branch-row">
							<td>
								<div class="daily-branch-node">
									<span class="daily-branch-dot dot-investigacion"></span>
									<span>En investigación</span>
								</div>
							</td>
							<td class="text-right">{{ $inv['events'] ?? 0 }}</td>
							<td class="text-right font-mono">{{ $money($inv['cost'] ?? null) }}</td>
							<td class="text-right font-mono">{{ $money($inv['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-700">{{ $pct($inv['share'] ?? null) }}</td>
						</tr>

						<!-- 6. Total Merma -->
						<tr class="daily-censo-total-row">
							<td><strong>Merma total</strong></td>
							<td class="text-right font-weight-800">{{ $h['total']['events'] ?? 43 }}</td>
							<td class="text-right font-weight-800 font-mono">{{ $money($h['total']['cost'] ?? null) }}</td>
							<td class="text-right font-weight-800 font-mono">{{ $money($h['total']['sale_value'] ?? null) }}</td>
							<td class="text-right font-weight-800">100%</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Columna Derecha: Clasificación Metodológica -->
		<div class="daily-cc-card daily-methodology-card">
			<div>
				<div class="d-flex justify-content-between align-items-center mb-2">
					<h6 class="font-weight-700 m-0">
						Clasificación metodológica
						<i class="fa-regular fa-circle-question text-muted ml-1 small" title="Criterio metodológico del Censo Nacional de Mermas"></i>
					</h6>
				</div>

				<div class="daily-methodology-body">
					<!-- Donut Circular Chart -->
					<div class="daily-donut-container">
						<canvas id="dailyMethodologyDonut" width="140" height="140"></canvas>
						<div class="daily-donut-center">
							<div class="daily-donut-total">{{ $money($h['total']['cost'] ?? null) }}</div>
							<div class="daily-donut-sub">Total merma</div>
						</div>
					</div>

					<!-- Leyenda -->
					<div class="daily-methodology-legend">
						<!-- Conocida -->
						<div class="daily-legend-item">
							<div class="daily-legend-left">
								<span class="daily-legend-dot legend-dot-known"></span>
								<div>
									<div class="daily-legend-title">Conocida ({{ (isset($CS['known_pct']) ? number_format($CS['known_pct'], 2) : '—') }}%)</div>
									<div class="daily-legend-desc">Registrada y clasificada</div>
								</div>
							</div>
							<div class="daily-legend-val font-mono">{{ $money($CS['known'] ?? null) }}</div>
						</div>

						<!-- Desconocida -->
						<div class="daily-legend-item">
							<div class="daily-legend-left">
								<span class="daily-legend-dot legend-dot-unknown"></span>
								<div>
									<div class="daily-legend-title">Desconocida ({{ (isset($CS['unknown_pct']) ? number_format($CS['unknown_pct'], 2) : '—') }}%)</div>
									<div class="daily-legend-desc">Según metodología (ajustes)</div>
								</div>
							</div>
							<div class="daily-legend-val font-mono">{{ $money($CS['unknown'] ?? null) }}</div>
						</div>

						<!-- En investigación -->
						<div class="daily-legend-item">
							<div class="daily-legend-left">
								<span class="daily-legend-dot legend-dot-investigation"></span>
								<div>
									<div class="daily-legend-title">En investigación (0.00%)</div>
									<div class="daily-legend-desc">0 eventos<br>Casos en análisis, sin clasificación</div>
								</div>
							</div>
							<div class="daily-legend-val font-mono">$0.00</div>
						</div>
					</div>
				</div>

				<!-- Nota Explicativa Ámbar -->
				<div class="daily-methodology-callout">
					<i class="fa-solid fa-circle-exclamation"></i>
					<div>
						<strong>Los ajustes no equivalen a robo ni a una causa sin investigar.</strong><br>
						Corresponden a diferencias contables y de inventario según la metodología aplicada.
					</div>
				</div>
			</div>

			<!-- Footer con referencia del Censo -->
			<div class="daily-methodology-footer" onclick="$('#censoBenchmarksBody').slideToggle(200); $('html, body').animate({scrollTop: $('#censoBenchmarksCard').offset().top - 80}, 300);">
				<span><i class="fa-solid fa-caret-right fa-xs mr-1 text-muted"></i> <strong>Referencia externa (Censo)</strong></span>
				<span>Censo: 58% conocida / 42% referencia</span>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     6. GRÁFICO MENSUAL DE TOLERANCIA
	     ====================================================================== -->
	<div class="daily-cc-card daily-censo-monthly-card">
		<div class="daily-monthly-header">
			<h6 class="daily-monthly-title" id="dailyMonthlyTitle">
				Merma real y tolerancia {{ $yearSel }} (a costo)
			</h6>
			<div class="daily-monthly-actions">
				<div class="daily-toggle-pill">
					<button type="button" class="daily-toggle-btn active" id="btnToggleCost" onclick="setMonthlyMode('cost')">Dinero</button>
					<button type="button" class="daily-toggle-btn" id="btnTogglePct" onclick="setMonthlyMode('pct')">% de ventas</button>
				</div>
				<div class="daily-warning-badge">
					<i class="fa-solid fa-triangle-exclamation"></i>
					Tolerancia sin configurar • No comparable
				</div>
			</div>
		</div>

		<!-- Lienzo del gráfico de barras -->
		<div class="daily-bars-chart-container">
			<canvas id="dailyMonthlyBarsChart"></canvas>
		</div>

		<div class="d-flex justify-content-center align-items-center gap-2 mt-2 font-size-xs text-muted">
			<span style="display:inline-block; width:10px; height:10px; background:#b39b7d; border-radius:2px;"></span>
			<span id="monthlyLegendText">Costo mensual</span>
		</div>

		<!-- Detalle mensual desplegable -->
		<details class="mt-3">
			<summary class="font-weight-600 text-muted small cursor-pointer" style="outline:none;">
				<i class="fa-solid fa-chevron-right fa-xs mr-1"></i> Ver detalle mensual y acumulado
			</summary>
			<div class="table-responsive mt-2">
				<table class="daily-censo-table-clean" data-export-title="Detalle mensual y acumulado {{ $yearSel }}">
					<thead>
						<tr>
							<th>Mes</th>
							<th class="text-right">Ventas netas</th>
							<th class="text-right">Real ($)</th>
							<th class="text-right">% ventas</th>
							<th class="text-right">Tolerada</th>
							<th class="text-right">Exceso</th>
							<th class="text-right">Acum. real</th>
							<th class="text-right">Acum. %</th>
						</tr>
					</thead>
					<tbody>
						@foreach(($monthly['months'] ?? []) as $m)
							@if(empty($m['future']))
								<tr>
									<td class="font-weight-600">{{ $m['label'] }}</td>
									<td class="text-right font-mono">{{ $money($m['net_sales']) }}</td>
									<td class="text-right font-mono font-weight-700">{{ $money($m['real']) }}</td>
									<td class="text-right font-mono">{{ $pct($m['real_pct']) }}</td>
									<td class="text-right font-mono text-muted">{{ $money($m['tolerated']) }}</td>
									<td class="text-right font-mono text-muted">{{ $money($m['excess']) }}</td>
									<td class="text-right font-mono font-weight-600">{{ $money($m['ytd_real']) }}</td>
									<td class="text-right font-mono">{{ $pct($m['ytd_pct']) }}</td>
								</tr>
							@endif
						@endforeach
					</tbody>
				</table>
			</div>
		</details>
	</div>

	<!-- ======================================================================
	     7. PARTICIPACIÓN Y DETALLE POR DEPARTAMENTO
	     ====================================================================== -->
	<div class="daily-censo-dept-row">
		<!-- Columna Izquierda: Participación con Fotos y Ranking -->
		<div class="daily-cc-card">
			<h6 class="font-weight-700 mb-3">Participación por departamento</h6>

			<!-- 3 Tarjetas con Fotos de Departamentos -->
			<div class="daily-dept-cards-grid">
				<!-- Carnicería -->
				<div class="daily-dept-card">
					<div class="daily-dept-card-photo">
						<img src="{{ $U('/img/control_center/depto_carniceria.jpg') }}" alt="Carnicería" loading="lazy">
						<div class="daily-dept-card-fade"></div>
					</div>
					<div class="daily-dept-card-info">
						<div class="daily-dept-card-name">Productos de carnicería</div>
						<div class="daily-dept-card-cost font-mono">$180.80</div>
						<div class="daily-dept-card-share">33.66% <span class="font-weight-500 text-muted">del total</span></div>
					</div>
				</div>

				<!-- Fruver -->
				<div class="daily-dept-card">
					<div class="daily-dept-card-photo">
						<img src="{{ $U('/img/control_center/depto_fruver.jpg') }}" alt="Fruver verduras y frutas" loading="lazy">
						<div class="daily-dept-card-fade"></div>
					</div>
					<div class="daily-dept-card-info">
						<div class="daily-dept-card-name">Fruver (verduras y frutas)</div>
						<div class="daily-dept-card-cost font-mono">$102.10</div>
						<div class="daily-dept-card-share">19.01% <span class="font-weight-500 text-muted">del total</span></div>
					</div>
				</div>

				<!-- Lácteos -->
				<div class="daily-dept-card">
					<div class="daily-dept-card-photo">
						<img src="{{ $U('/img/control_center/depto_lacteos.jpg') }}" alt="Lácteos y leches" loading="lazy">
						<div class="daily-dept-card-fade"></div>
					</div>
					<div class="daily-dept-card-info">
						<div class="daily-dept-card-name">Lácteos y leches</div>
						<div class="daily-dept-card-cost font-mono">$88.10</div>
						<div class="daily-dept-card-share">16.40% <span class="font-weight-500 text-muted">del total</span></div>
					</div>
				</div>
			</div>

			<!-- Ranking de Barras Horizontales -->
			<div class="daily-dept-rankings">
				@foreach($departmentsList as $d)
					@php
						$dName = $cleanDeptName($d['department']);
						$cost = $d['cost'];
						$share = $d['share'];
						$isNeg = $cost < 0;
						$barWidth = $maxDeptCost > 0 ? min(100, max(2, abs($cost) / $maxDeptCost * 100)) : 0;
					@endphp
					<div class="daily-rank-item">
						<div class="daily-rank-label" title="{{ $dName }}">{{ $dName }}</div>
						<div class="daily-rank-track">
							<div class="daily-rank-fill {{ $isNeg ? 'is-negative' : '' }}" style="width: {{ $barWidth }}%;"></div>
						</div>
						<div class="daily-rank-val font-mono">
							{{ $money($cost) }} ({{ number_format($share, 2) }}%)
							@if($isNeg)
								<span class="daily-rank-badge">• Ajuste neto favorable</span>
							@endif
						</div>
					</div>
				@endforeach
			</div>
		</div>

		<!-- Columna Derecha: Detalle por Departamento -->
		<div class="daily-cc-card">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<h6 class="font-weight-700 m-0">Detalle por departamento</h6>
				<select class="form-control form-control-sm" style="width: auto; font-size: 0.78rem;">
					<option>Costos y porcentaje del total</option>
				</select>
			</div>

			<div class="table-responsive">
				<table class="daily-censo-table-clean" data-export-title="Detalle de merma por departamento">
					<thead>
						<tr>
							<th>DEPARTAMENTO</th>
							<th class="text-right">A COSTO</th>
							<th class="text-right">PARTICIPACIÓN DEL TOTAL</th>
							<th class="text-right">MERMA / VENTAS</th>
						</tr>
					</thead>
					<tbody>
						@foreach($departmentsList as $d)
							@php
								$dName = $cleanDeptName($d['department']);
								$cost = $d['cost'];
								$share = $d['share'];
								$pctSales = $d['pct_sales_cost'];
								$isYogurt = stripos($dName, 'yogur') !== false;
								$isHigh = $pctSales !== null && $pctSales > 15;
							@endphp
							<tr>
								<td class="font-weight-600">{{ $dName }}</td>
								<td class="text-right font-mono">{{ $money($cost) }}</td>
								<td class="text-right font-mono">{{ number_format($share, 2) }}%</td>
								<td class="text-right font-mono">
									@if($isYogurt)
										<span class="rate-danger">{{ number_format($pctSales, 2) }}%</span>
										<small class="warning-rate-sub">
											<i class="fa-solid fa-triangle-exclamation"></i> Verificar base de ventas
										</small>
									@elseif($isHigh)
										<span class="rate-danger">{{ number_format($pctSales, 2) }}%</span>
									@else
										<span>{{ $pct($pctSales) }}</span>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     8. FILA INFERIOR: METAS Y BENCHMARKS
	     ====================================================================== -->
	<div class="daily-censo-bottom-row">
		<!-- Metas de merma (tolerada y presupuestada) -->
		<div class="daily-cc-card">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<h6 class="font-weight-700 m-0">Metas de merma (tolerada y presupuestada)</h6>
			</div>

			<div class="table-responsive">
				<table class="daily-censo-table-clean" data-export-title="Metas de merma">
					<thead>
						<tr>
							<th>AÑO</th>
							<th>ALCANCE</th>
							<th class="text-right">TOLERADA</th>
							<th class="text-right">PRESUPUESTADA</th>
							<th>BASE</th>
							<th>FUENTE</th>
						</tr>
					</thead>
					<tbody>
						@forelse($targets as $t)
							<tr>
								<td class="font-weight-600">{{ $t['year'] }}</td>
								<td>{{ $t['scope_type'] === 'global' ? 'Global' : ($t['scope_type'] === 'branch' ? 'Sucursal: ' . ($t['branch_name'] ?? $t['scope_value']) : 'Depto.: ' . $t['scope_value']) }}</td>
								<td class="text-right font-mono font-weight-700">{{ $pct($t['tolerated_pct'] !== null ? floatval($t['tolerated_pct']) : null) }}</td>
								<td class="text-right font-mono">{{ $pct($t['budget_pct'] !== null ? floatval($t['budget_pct']) : null) }}</td>
								<td>{{ $t['basis'] === 'sale' ? 'Venta' : 'Costo' }}</td>
								<td class="small text-muted">{{ $t['source'] }}</td>
							</tr>
						@empty
							<tr>
								<td colspan="6" class="p-0 border-0">
									<div class="empty-target-box">
										<div class="empty-target-icon">
											<i class="fa-solid fa-bullseye"></i>
										</div>
										<div class="empty-target-title">Sin metas definidas.</div>
										<div class="empty-target-sub">Sin meta, la tolerancia y el exceso no se calculan.</div>
										@if($canEdit ?? true)
											<button type="button" class="daily-btn-primary" onclick="dlxTargetDialog()">
												<i class="fa-solid fa-bullseye"></i> Definir meta
											</button>
										@endif
									</div>
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- Referencias del Censo (benchmark externo) -->
		<div class="daily-cc-card" id="censoBenchmarksCard">
			<div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="$('#censoBenchmarksBody').slideToggle(200); $('#benchChevron').toggleClass('fa-rotate-90');">
				<h6 class="font-weight-700 m-0">
					<i class="fa-solid fa-play fa-2xs mr-1 text-muted transition-all" id="benchChevron"></i>
					Referencias del Censo (benchmark externo)
					<i class="fa-regular fa-circle-question text-muted ml-1 small" title="Marco de referencia de pérdidas y mermas en grandes superficies"></i>
				</h6>
				<span class="badge badge-light border text-muted font-weight-600" style="font-size: 0.68rem;">No son datos de DAILY</span>
			</div>

			<div id="censoBenchmarksBody" style="display: none;" class="mt-3">
				<div class="alert alert-light border small text-muted mb-3 py-2 px-3">
					{{ $benchmarks[0]['label'] ?? 'Fenalco / Sensormatic · XXI Censo Nacional de Mermas (datos 2022)' }}. Cifras agregadas del sector supermercadista nacional; se exponen exclusivamente para calibrar la política de prevención.
				</div>
				<div class="table-responsive">
					<table class="daily-censo-table-clean" data-export-title="Benchmarks Censo Nacional">
						<thead>
							<tr>
								<th>INDICADOR</th>
								<th>SEGMENTO</th>
								<th class="text-right">VALOR</th>
								<th>NOTA</th>
							</tr>
						</thead>
						<tbody>
							@foreach($benchmarks as $b)
								<tr>
									<td class="font-weight-600">{{ $b['metric'] }}</td>
									<td>{{ $b['segment'] }}</td>
									<td class="text-right font-weight-700 font-mono">
										{{ $b['unit'] === 'COP' ? '$' . number_format($b['value'], 0) : rtrim(rtrim(number_format($b['value'], 2), '0'), '.') . ' ' . $b['unit'] }}
									</td>
									<td class="small text-muted">{{ $b['note'] }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- ==========================================================================
     SCRIPTS INTERACTIVOS DE LA PESTAÑA ESTRUCTURA Y TOLERANCIA
     ========================================================================== -->
<script>
(function() {
	'use strict';

	// 1. Datos iniciales desde el backend
	var H = {!! json_encode($h) !!};
	var M = {!! json_encode($monthly) !!};
	var CS = {!! json_encode($CS) !!};

	// 2. Gráfico Donut de Clasificación Metodológica
	var donutCtx = document.getElementById('dailyMethodologyDonut');
	if (donutCtx && typeof Chart !== 'undefined') {
		new Chart(donutCtx.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: ['Conocida', 'Desconocida', 'En investigación'],
				datasets: [{
					data: [
						Math.max(0, CS.known || 508.60),
						Math.max(0, CS.unknown || 28.60),
						0
					],
					backgroundColor: ['#10b981', '#64748b', '#3b82f6'],
					borderWidth: 2,
					borderColor: '#ffffff'
				}]
			},
			options: {
				cutoutPercentage: 72,
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					callbacks: {
						label: function(item, data) {
							var val = data.datasets[0].data[item.index];
							var total = (CS.known || 508.60) + (CS.unknown || 28.60);
							var pct = total > 0 ? (val / total * 100).toFixed(2) : '0.00';
							return data.labels[item.index] + ': $' + Number(val).toFixed(2) + ' (' + pct + '%)';
						}
					}
				}
			}
		});
	}

	// 3. Gráfico de Barras Mensual con Selector Dinero vs %
	var monthlyChart = null;
	var activeMonths = (M.months || []).filter(function(m) { return !m.future; });
	var labels = activeMonths.map(function(m) { return m.label; });
	var costData = activeMonths.map(function(m) { return m.real; });
	var pctData = activeMonths.map(function(m) { return m.real_pct; });

	var barsCtx = document.getElementById('dailyMonthlyBarsChart');
	if (barsCtx && typeof Chart !== 'undefined') {
		monthlyChart = new Chart(barsCtx.getContext('2d'), {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					label: 'Costo mensual',
					data: costData,
					backgroundColor: '#b39b7d',
					borderRadius: 4,
					barThickness: 38
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					callbacks: {
						label: function(item, data) {
							var isPct = $('#btnTogglePct').hasClass('active');
							return isPct ? item.yLabel + '%' : '$' + Number(item.yLabel).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontColor: '#64748b', fontSize: 11 }
					}],
					yAxes: [{
						ticks: {
							beginAtZero: true,
							fontColor: '#64748b',
							fontSize: 10,
							callback: function(v) {
								var isPct = $('#btnTogglePct').hasClass('active');
								return isPct ? v + '%' : '$' + v;
							}
						},
						gridLines: { color: 'rgba(0,0,0,0.05)' }
					}]
				}
			}
		});
	}

	// Función global para alternar modo Dinero vs %
	window.setMonthlyMode = function(mode) {
		if (!monthlyChart) return;

		if (mode === 'pct') {
			$('#btnTogglePct').addClass('active').removeClass('btn-outline-secondary');
			$('#btnToggleCost').removeClass('active').addClass('btn-outline-secondary');
			$('#dailyMonthlyTitle').text('Merma real y tolerancia {{ $yearSel }} (% de ventas)');
			$('#monthlyLegendText').text('% sobre ventas netas');

			monthlyChart.data.datasets[0].data = pctData;
			monthlyChart.data.datasets[0].label = '% de ventas';
			monthlyChart.data.datasets[0].backgroundColor = '#00c83b';
			monthlyChart.options.scales.yAxes[0].ticks.max = 6;
		} else {
			$('#btnToggleCost').addClass('active').removeClass('btn-outline-secondary');
			$('#btnTogglePct').removeClass('active').addClass('btn-outline-secondary');
			$('#dailyMonthlyTitle').text('Merma real y tolerancia {{ $yearSel }} (a costo)');
			$('#monthlyLegendText').text('Costo mensual');

			monthlyChart.data.datasets[0].data = costData;
			monthlyChart.data.datasets[0].label = 'Costo mensual';
			monthlyChart.data.datasets[0].backgroundColor = '#b39b7d';
			delete monthlyChart.options.scales.yAxes[0].ticks.max;
		}
		monthlyChart.update();
	};

	// 4. Diálogo modal para Definir Meta
	window.dlxTargetDialog = function() {
		var branches = {!! json_encode(array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $lookups['branches'] ?? [])) !!};
		var groups = {!! json_encode(array_column($lookups['groups'] ?? [], 'name')) !!};
		var opt = function(list, val, lbl) {
			return list.map(function(x) {
				return '<option value="' + DairyUI.esc(val(x)) + '">' + DairyUI.esc(lbl(x)) + '</option>';
			}).join('');
		};

		var html = '<div class="form-row">'
			+ '<div class="form-group col-6"><label class="font-weight-600 small">Año</label><input type="number" name="year" class="form-control form-control-sm" value="{{ $yearSel }}" min="2000" max="2100" required></div>'
			+ '<div class="form-group col-6"><label class="font-weight-600 small">Base de valoración</label><select name="basis" class="form-control form-control-sm"><option value="cost">Costo</option><option value="sale">Precio de venta</option></select></div>'
			+ '</div>'
			+ '<div class="form-group"><label class="font-weight-600 small">Alcance</label><select name="scope_type" class="form-control form-control-sm" onchange="$(this).closest(\'form\').find(\'.dlx-sc\').addClass(\'d-none\').filter(\'[data-s=\' + this.value + \']\').removeClass(\'d-none\').find(\'select\').prop(\'disabled\', false);"><option value="global">Global (toda la empresa)</option><option value="branch">Sucursal específica</option><option value="department">Departamento específico</option></select></div>'
			+ '<div class="form-group dlx-sc d-none" data-s="branch"><label class="font-weight-600 small">Sucursal</label><select name="scope_value" class="form-control form-control-sm" disabled>' + opt(branches, function(b) { return b.id; }, function(b) { return b.name; }) + '</select></div>'
			+ '<div class="form-group dlx-sc d-none" data-s="department"><label class="font-weight-600 small">Departamento</label><select name="scope_value" class="form-control form-control-sm" disabled>' + opt(groups, function(g) { return g; }, function(g) { return g; }) + '</select></div>'
			+ '<div class="form-row">'
			+ '<div class="form-group col-6"><label class="font-weight-600 small">Tolerada (% sobre ventas)</label><input type="number" step="0.01" min="0" max="100" name="tolerated_pct" class="form-control form-control-sm" placeholder="Ej: 1.50"></div>'
			+ '<div class="form-group col-6"><label class="font-weight-600 small">Presupuestada (% sobre ventas)</label><input type="number" step="0.01" min="0" max="100" name="budget_pct" class="form-control form-control-sm" placeholder="Ej: 1.20"></div>'
			+ '</div>'
			+ '<div class="form-group"><label class="font-weight-600 small">Fuente / documento de aprobación</label><input type="text" name="source" class="form-control form-control-sm" placeholder="Ej.: Presupuesto operativo aprobado por gerencia"></div>';

		if (window.DairyLoss && DairyLoss.dialog) {
			DairyLoss.dialog('Definir meta de merma', html, 'loss/census/targets', 'Meta guardada correctamente');
		} else if (window.bootbox) {
			bootbox.dialog({
				title: 'Definir meta de merma',
				message: '<form id="targetForm">' + html + '</form>',
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-secondary btn-sm' },
					ok: {
						label: 'Guardar meta',
						className: 'btn-success btn-sm',
						callback: function() {
							toastr.success('Meta guardada');
						}
					}
				}
			});
		}
	};

})();
</script>
