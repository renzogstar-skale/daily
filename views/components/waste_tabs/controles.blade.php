@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$pct1 = fn($v) => $v === null ? '—' : number_format(floatval($v), 1) . '%';
	$E = $eco;
	$K = $E['controls'];
	$So = $E['social'];
	$R = $E['recovery'];
	
	// Recuperación con respecto a recuperable
	$recPct = $R['pct'] ?? null;
	$recOver100 = $recPct !== null && $recPct > 100;
	$hasMeasures = count($K['controls']) > 0;
	
	// Formato de margen
	$marginDiff = $E['margin']['pp'] ?? null;
	$marginBefore = $E['margin']['margin_before_pct'] ?? null;
	$marginAfter = $E['margin']['margin_after_pct'] ?? null;
	
	// Departamentos canónicos
	$deptIcons = [
		'carnicería' => ['icon' => 'fa-drumstick-bite', 'bg' => '#fee2e2', 'color' => '#dc2626'],
		'carniceria' => ['icon' => 'fa-drumstick-bite', 'bg' => '#fee2e2', 'color' => '#dc2626'],
		'frutas' => ['icon' => 'fa-leaf', 'bg' => '#dcfce7', 'color' => '#16a34a'],
		'verduras' => ['icon' => 'fa-leaf', 'bg' => '#dcfce7', 'color' => '#16a34a'],
		'fruver' => ['icon' => 'fa-leaf', 'bg' => '#dcfce7', 'color' => '#16a34a'],
		'lácteos' => ['icon' => 'fa-bottle-water', 'bg' => '#e0f2fe', 'color' => '#0284c7'],
		'lacteos' => ['icon' => 'fa-bottle-water', 'bg' => '#e0f2fe', 'color' => '#0284c7'],
		'leches' => ['icon' => 'fa-bottle-water', 'bg' => '#e0f2fe', 'color' => '#0284c7'],
		'quesos' => ['icon' => 'fa-cheese', 'bg' => '#fef3c7', 'color' => '#d97706'],
		'yogures' => ['icon' => 'fa-bowl-food', 'bg' => '#fce7f3', 'color' => '#db2777'],
		'huevos' => ['icon' => 'fa-egg', 'bg' => '#fef9c3', 'color' => '#ca8a04'],
		'granja' => ['icon' => 'fa-egg', 'bg' => '#fef9c3', 'color' => '#ca8a04'],
		'bebidas' => ['icon' => 'fa-glass-water', 'bg' => '#ede9fe', 'color' => '#7c3aed']
	];
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER: ESTILOS - PESTAÑA CONTROLES E IMPACTO ECONÓMICO
   ========================================================================== */
.daily-controles-tab {
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
.daily-controles-header-card {
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
.daily-controles-header-left {
	display: flex;
	align-items: center;
	gap: 16px;
	z-index: 2;
	max-width: 62%;
}
.daily-controles-header-icon {
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
.daily-controles-header-title {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	margin: 0 0 4px 0;
}
.daily-controles-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-controles-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 46%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
}
.daily-controles-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: right center;
}
.daily-controles-header-photo-mask {
	position: absolute;
	inset: 0;
	background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.82) 20%, rgba(255, 255, 255, 0) 100%);
}

/* 2. BARRA DE FILTROS LOCALES */
.daily-controles-filters-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 18px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.daily-controles-filters-form {
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

/* 3. RESUMEN DE IMPACTO ECONÓMICO */
.daily-section-card {
	padding: 20px 22px;
}
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

/* 8 TARJETAS FINANCIERAS (GRID 4x2) */
.daily-eco-kpis-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
	margin-bottom: 14px;
}
.daily-eco-kpi-card {
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
.daily-eco-kpi-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.daily-eco-kpi-top {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}
.daily-eco-kpi-icon {
	width: 26px;
	height: 26px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.8rem;
	flex-shrink: 0;
}
.daily-eco-kpi-title {
	font-size: 0.72rem;
	font-weight: 600;
	color: #64748b;
	letter-spacing: 0.01em;
	margin: 0;
}
.daily-eco-kpi-val-row {
	display: flex;
	align-items: baseline;
	gap: 8px;
	flex-wrap: wrap;
}
.daily-eco-kpi-val {
	font-size: 1.45rem;
	font-weight: 800;
	letter-spacing: -0.02em;
	line-height: 1.15;
	margin: 2px 0;
}
.daily-eco-kpi-badge {
	font-size: 0.65rem;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 6px;
	letter-spacing: 0.02em;
	text-transform: uppercase;
}
.daily-badge-amber {
	background: #fef3c7;
	color: #b45309;
	border: 1px solid #fde68a;
}
.daily-badge-gray {
	background: #f1f5f9;
	color: #475569;
	border: 1px solid #e2e8f0;
}
.daily-eco-kpi-sub {
	font-size: 0.74rem;
	color: #64748b;
	margin: 2px 0 0 0;
}

/* ALERTA ÁMBAR DE RECUPERACIÓN */
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
	margin-top: 4px;
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

/* 4. COMPOSICIÓN DEL IMPACTO (WATERFALL) & EFECTIVIDAD */
.daily-two-col-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 18px;
}
.daily-waterfall-card {
	padding: 18px 20px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-waterfall-header h6 {
	font-size: 0.98rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-waterfall-header p {
	font-size: 0.76rem;
	color: #64748b;
	margin: 2px 0 16px 0;
}

/* Gráfico Waterfall de 3 barras */
.daily-wf-graphic {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding: 10px 0 0 0;
}
.daily-wf-chart-area {
	display: flex;
	align-items: flex-end;
	justify-content: space-around;
	height: 180px;
	border-bottom: 2px solid #e2e8f0;
	padding: 0 10px 0 45px;
	position: relative;
}
.daily-wf-y-axis {
	position: absolute;
	left: 0;
	top: 0;
	bottom: 0;
	width: 40px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	font-size: 0.68rem;
	font-weight: 600;
	color: #94a3b8;
	text-align: right;
	padding-right: 6px;
}
.daily-wf-gridlines {
	position: absolute;
	left: 45px;
	right: 0;
	top: 0;
	bottom: 0;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	pointer-events: none;
}
.daily-wf-gridline {
	border-bottom: 1px dashed #f1f5f9;
	width: 100%;
}
.daily-wf-col {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 26%;
	height: 100%;
	justify-content: flex-end;
	position: relative;
	z-index: 2;
}
.daily-wf-bar-val {
	font-size: 0.8rem;
	font-weight: 800;
	color: #0f172a;
	margin-bottom: 4px;
	text-align: center;
}
.daily-wf-bar {
	width: 100%;
	border-radius: 6px 6px 0 0;
	transition: height 0.3s ease;
	position: relative;
}
.daily-wf-bar-red {
	background: #dc2626;
	height: 89.5%; /* $537.20 / 600 */
}
.daily-wf-bar-green {
	background: #16a34a;
	height: 32.5%; /* $194.92 / 600 */
	margin-bottom: 57%; /* Waterfall drop offset */
	border-radius: 6px;
}
.daily-wf-bar-slate {
	background: #64748b;
	height: 57%; /* $342.28 / 600 */
}
.daily-wf-col-labels {
	display: flex;
	justify-content: space-around;
	padding: 10px 0 0 45px;
	text-align: center;
}
.daily-wf-lbl-item {
	width: 28%;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 3px;
}
.daily-wf-lbl-title {
	font-size: 0.74rem;
	font-weight: 700;
	color: #0f172a;
}
.daily-wf-lbl-sub {
	font-size: 0.68rem;
	color: #64748b;
}

/* Efectividad de controles preventivos */
.daily-controls-eff-card {
	padding: 18px 20px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-eff-empty-box {
	background: #f8fafc;
	border: 1px dashed #cbd5e1;
	border-radius: 12px;
	padding: 24px 20px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	margin: 10px 0 16px 0;
}
.daily-eff-empty-icon {
	font-size: 1.8rem;
	color: #94a3b8;
	margin-bottom: 8px;
}
.daily-eff-empty-title {
	font-size: 0.92rem;
	font-weight: 700;
	color: #334155;
	margin: 0 0 2px 0;
}
.daily-eff-empty-sub {
	font-size: 0.76rem;
	color: #64748b;
	margin: 0;
}

/* Stepper de 5 pasos para medir efectividad */
.daily-stepper-title {
	font-size: 0.76rem;
	font-weight: 700;
	color: #475569;
	margin-bottom: 10px;
}
.daily-eff-steps {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 6px;
	position: relative;
}
.daily-eff-step {
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
	flex: 1;
	position: relative;
}
.daily-eff-circle {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: #f1f5f9;
	border: 1px solid #cbd5e1;
	color: #475569;
	font-size: 0.7rem;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 6px;
	z-index: 2;
}
.daily-eff-step-name {
	font-size: 0.68rem;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 2px;
	line-height: 1.2;
}
.daily-eff-step-sub {
	font-size: 0.62rem;
	color: #64748b;
	line-height: 1.2;
}

/* 5. 3 TARJETAS CON FOTOGRAFÍAS (ÁMBITOS) */
.daily-ambitos-header h6 {
	font-size: 1rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-ambitos-header p {
	font-size: 0.78rem;
	color: #64748b;
	margin: 2px 0 14px 0;
}
.daily-ambitos-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16px;
}
.daily-ambito-card {
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
.daily-ambito-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
	border-color: #cbd5e1;
}
.daily-ambito-photo-wrap {
	width: 100%;
	height: 84px;
	position: relative;
	overflow: hidden;
	background: #f8fafc;
}
.daily-ambito-photo-wrap img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-ambito-photo-fade {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	height: 35px;
	background: linear-gradient(0deg, #ffffff 15%, rgba(255, 255, 255, 0) 100%);
}
.daily-ambito-body {
	padding: 12px 18px 18px 18px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	flex-grow: 1;
	gap: 12px;
}
.daily-ambito-title {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 4px 0;
}
.daily-ambito-desc {
	font-size: 0.74rem;
	color: #64748b;
	line-height: 1.35;
	margin: 0;
}
.daily-btn-config {
	width: 100%;
	background: #ffffff;
	color: #00c83b;
	border: 1px solid #00c83b;
	border-radius: 8px;
	font-size: 0.8rem;
	font-weight: 600;
	padding: 7px 12px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-config:hover {
	background: #f0fdf4;
	color: #00b034;
}

/* 6. TABLA DE MEDIDAS PREVENTIVAS */
.daily-medidas-card {
	padding: 20px 22px;
}
.daily-medidas-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 14px;
}
.daily-medidas-actions {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}
.daily-medidas-toggle-group {
	display: inline-flex;
	border: 1px solid #cbd5e1;
	border-radius: 8px;
	overflow: hidden;
}
.daily-btn-toggle {
	background: #ffffff;
	border: none;
	font-size: 0.78rem;
	font-weight: 600;
	color: #64748b;
	padding: 6px 12px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-toggle.active {
	background: #00c83b;
	color: #ffffff;
}
.daily-medidas-search {
	position: relative;
	width: 250px;
}
.daily-medidas-search input {
	width: 100%;
	height: 32px;
	border-radius: 6px;
	border: 1px solid #cbd5e1;
	font-size: 0.78rem;
	padding: 4px 10px 4px 28px;
}
.daily-medidas-search i {
	position: absolute;
	left: 9px;
	top: 9px;
	color: #94a3b8;
	font-size: 0.75rem;
}
.daily-table-wrap {
	overflow-x: auto;
}
.daily-table-clean {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.8rem;
}
.daily-table-clean th {
	background: #f8fafc;
	color: #475569;
	font-size: 0.7rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 10px 14px;
	border-bottom: 1px solid #e2e8f0;
	white-space: nowrap;
}
.daily-table-clean td {
	padding: 12px 14px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-empty-box-inline {
	text-align: center;
	padding: 36px 20px;
	color: #64748b;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
}
.daily-empty-icon-circle {
	width: 48px;
	height: 48px;
	border-radius: 50%;
	background: #f1f5f9;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #94a3b8;
	font-size: 1.25rem;
	margin-bottom: 4px;
}
.daily-empty-box-inline strong {
	color: #334155;
	font-size: 0.95rem;
}
.daily-empty-box-inline p {
	font-size: 0.78rem;
	margin: 0;
}
.daily-table-note {
	font-size: 0.72rem;
	color: #64748b;
	margin: 12px 0 0 0;
	line-height: 1.4;
}

/* 7. ANÁLISIS DEL IMPACTO (3 BLOQUES / PANELES) */
.daily-analysis-card {
	padding: 20px 22px;
}
.daily-analysis-tabs {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 16px;
	border-bottom: 1px solid #e2e8f0;
	padding-bottom: 8px;
}
.daily-analysis-tab-btn {
	background: transparent;
	border: none;
	font-size: 0.8rem;
	font-weight: 600;
	color: #64748b;
	padding: 5px 12px;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-analysis-tab-btn.active {
	background: #e2fbe8;
	color: #15803d;
	font-weight: 700;
}
.daily-analysis-grid {
	display: grid;
	grid-template-columns: 1.2fr 0.9fr 1.3fr;
	gap: 18px;
}
.daily-analysis-col {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-analysis-col-header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;
}
.daily-analysis-col-header h6 {
	font-size: 0.85rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
}
.daily-analysis-col-sub {
	font-size: 0.72rem;
	color: #64748b;
	margin: 0 0 12px 0;
}

/* Subtabla de departamentos */
.daily-dept-list-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.76rem;
}
.daily-dept-list-table th {
	font-size: 0.65rem;
	font-weight: 700;
	color: #64748b;
	text-transform: uppercase;
	padding: 6px 4px;
	border-bottom: 1px solid #cbd5e1;
}
.daily-dept-list-table td {
	padding: 6px 4px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
}
.daily-dept-name-cell {
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-dept-icon-mini {
	width: 20px;
	height: 20px;
	border-radius: 4px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 0.65rem;
}

/* Caja de Formato Comercial */
.daily-format-empty-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 24px 10px;
	gap: 6px;
	margin: auto 0;
}
.daily-format-icon {
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: #ffffff;
	border: 1px solid #cbd5e1;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #64748b;
	font-size: 1.1rem;
	margin-bottom: 4px;
}
.daily-format-title {
	font-size: 0.88rem;
	font-weight: 700;
	color: #1e293b;
	margin: 0;
}
.daily-format-desc {
	font-size: 0.72rem;
	color: #64748b;
	margin: 0 0 10px 0;
	max-width: 220px;
}

/* Grilla de causales (2 columnas) */
.daily-causes-reference-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 6px 12px;
	font-size: 0.72rem;
}
.daily-cause-ref-item {
	display: flex;
	align-items: baseline;
	gap: 6px;
	line-height: 1.3;
}
.daily-cause-code {
	font-weight: 800;
	color: #0f172a;
	font-size: 0.72rem;
	width: 26px;
	flex-shrink: 0;
}
.daily-cause-name {
	color: #475569;
	font-size: 0.7rem;
}

/* 8. EQUIVALENCIAS SOCIALES (OPCIONAL) */
.daily-social-details {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 18px;
}
.daily-social-summary {
	cursor: pointer;
	font-size: 0.84rem;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	user-select: none;
}
.daily-social-summary-sub {
	font-weight: 400;
	color: #64748b;
	font-size: 0.76rem;
	margin-left: 6px;
}
.daily-social-content {
	margin-top: 14px;
	padding-top: 12px;
	border-top: 1px solid #f1f5f9;
}
.daily-social-kpis {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;
	margin: 12px 0;
}
.daily-social-kpi-card {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 14px;
}

/* RESPONSIVE */
@media (max-width: 991px) {
	.daily-controles-header-photo {
		display: none;
	}
	.daily-controles-header-left {
		max-width: 100%;
	}
	.daily-eco-kpis-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-two-col-grid {
		grid-template-columns: 1fr;
	}
	.daily-ambitos-grid {
		grid-template-columns: 1fr;
	}
	.daily-analysis-grid {
		grid-template-columns: 1fr;
	}
	.daily-social-kpis {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 640px) {
	.daily-controles-header-card {
		padding: 16px;
	}
	.daily-controles-filters-form {
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
	.daily-eco-kpis-grid {
		grid-template-columns: 1fr;
	}
	.daily-medidas-header {
		flex-direction: column;
		align-items: flex-start;
	}
	.daily-medidas-actions {
		width: 100%;
		flex-direction: column;
	}
	.daily-medidas-search {
		width: 100%;
	}
	.daily-causes-reference-grid {
		grid-template-columns: 1fr;
	}
	.daily-eff-steps {
		flex-direction: column;
		gap: 12px;
	}
	.daily-eff-step {
		flex-direction: row;
		align-items: center;
		text-align: left;
		gap: 10px;
	}
	.daily-eff-circle {
		margin-bottom: 0;
	}
}
</style>

<div class="daily-controles-tab" id="dailyControlesTabContainer">

	<!-- ======================================================================
	     1. ENCABEZADO CON BANNER FOTOGRÁFICO
	     ====================================================================== -->
	<div class="daily-controles-header-card">
		<div class="daily-controles-header-left">
			<div class="daily-controles-header-icon">
				<i class="fa-solid fa-lock"></i>
			</div>
			<div>
				<h5 class="daily-controles-header-title">Controles preventivos e impacto económico</h5>
				<p class="daily-controles-header-sub">
					Gestión integral de pérdidas: registro, prevención, análisis, investigación, recuperación y control financiero.
				</p>
			</div>
		</div>
		<div class="daily-controles-header-photo">
			<img src="{{ $U('/img/control_center/controles_banner.jpg') }}" alt="Productos frescos de supermercado" loading="lazy">
			<div class="daily-controles-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     2. FILTROS LOCALES SINCRONIZADOS
	     ====================================================================== -->
	<div class="daily-controles-filters-card">
		<form method="GET" action="{{ $U('/waste') }}#tab-controles" class="daily-controles-filters-form" id="dailyControlesFiltersForm">
			<!-- Sucursal -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ct_branch_id">Sucursal</label>
				<select name="branch_id" id="ct_branch_id" class="daily-filter-select">
					<option value="">Todas las sucursales</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ct_group_id">Departamento</label>
				<select name="group_id" id="ct_group_id" class="daily-filter-select">
					<option value="">Todos los departamentos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
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

			<!-- Proveedor -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ct_supplier_id">Proveedor</label>
				<select name="supplier_id" id="ct_supplier_id" class="daily-filter-select">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- SKU -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ct_product_id">SKU</label>
				<select name="product_id" id="ct_product_id" class="daily-filter-select">
					<option value="">Todos</option>
					@foreach($lookups['products'] as $p)
						<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Botón Aplicar -->
			<div class="daily-filter-col" style="flex: 0 0 auto; min-width: auto;">
				<button type="submit" class="daily-btn-apply">
					<i class="fa-solid fa-check"></i> Aplicar
				</button>
			</div>
		</form>
	</div>

	<!-- ======================================================================
	     3. RESUMEN DE IMPACTO ECONÓMICO (8 TARJETAS + ALERTA ÁMBAR)
	     ====================================================================== -->
	<div class="daily-cc-card daily-section-card">
		<div class="daily-section-header">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-chart-column daily-section-icon-green"></i>
				<div>
					<h6>Resumen de impacto económico</h6>
					<p class="daily-section-sub">
						Pérdida económica por departamento, formato y causal; costo de la prevención y su retorno medido con la pérdida real antes y después de cada medida.
					</p>
				</div>
			</div>
			<div>
				@include('components.dairy_export', ['exportScope' => '#dailyControlesTabContainer', 'exportName' => 'controles_impacto_economico'])
			</div>
		</div>

		<!-- Fila 1 y 2: 8 Tarjetas Financieras -->
		<div class="daily-eco-kpis-grid">
			<!-- 1. Pérdida económica -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #fee2e2; color: #dc2626;">
						<i class="fa-regular fa-file-lines"></i>
					</div>
					<span class="daily-eco-kpi-title">Pérdida económica</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val" style="color: #dc2626;">{{ $money($E['total']) }}</span>
				</div>
				<p class="daily-eco-kpi-sub">{{ $pct($E['pct_sales_cost']) }} sobre ventas a costo</p>
			</div>

			<!-- 2. Recuperación reportada -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #dcfce7; color: #16a34a;">
						<i class="fa-regular fa-clipboard"></i>
					</div>
					<span class="daily-eco-kpi-title">Recuperación reportada</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val" style="color: #16a34a;">{{ $money($R['recovered']) }}</span>
					@if($recOver100)
						<span class="daily-eco-kpi-badge daily-badge-amber">Base por validar</span>
					@endif
				</div>
				<p class="daily-eco-kpi-sub">{{ $pct($R['pct']) }} de {{ $money($R['recoverable']) }} recuperable</p>
			</div>

			<!-- 3. Saldo (pérdida - recuperación) -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #f1f5f9; color: #475569;">
						<i class="fa-solid fa-percent"></i>
					</div>
					<span class="daily-eco-kpi-title">Saldo (pérdida − recuperación)</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $money($E['lost_capital']) }}</span>
					<span class="daily-eco-kpi-badge daily-badge-gray">Provisional</span>
				</div>
				<p class="daily-eco-kpi-sub">Importe y alcance por validar</p>
			</div>

			<!-- 4. Costo de prevención -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #f1f5f9; color: #475569;">
						<i class="fa-regular fa-circle-dot"></i>
					</div>
					<span class="daily-eco-kpi-title">Costo de prevención</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $money($E['prevention_cost']) }}</span>
				</div>
				<p class="daily-eco-kpi-sub">
					{{ $hasMeasures ? 'medidas activas en el período' : 'Sin medidas registradas' }}
				</p>
			</div>

			<!-- 5. A precio de venta -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #f1f5f9; color: #475569;">
						<i class="fa-solid fa-cart-shopping"></i>
					</div>
					<span class="daily-eco-kpi-title">A precio de venta</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $money($E['sale_value']) }}</span>
					<span class="daily-eco-kpi-badge daily-badge-gray">Diferente valoración</span>
				</div>
				<p class="daily-eco-kpi-sub">{{ $pct($E['pct_sales_sale']) }} de ventas netas</p>
			</div>

			<!-- 6. Impacto en margen -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #fef3c7; color: #d97706;">
						<i class="fa-solid fa-tag"></i>
					</div>
					<span class="daily-eco-kpi-title">Impacto en margen</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $marginDiff === null ? 'Sin información' : '−' . number_format($marginDiff, 2) . ' pp' }}</span>
				</div>
				<p class="daily-eco-kpi-sub">{{ $pct($marginBefore) }} → {{ $pct($marginAfter) }}</p>
			</div>

			<!-- 7. Pérdida por robo -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #dcfce7; color: #16a34a;">
						<i class="fa-solid fa-shield-halved"></i>
					</div>
					<span class="daily-eco-kpi-title">Pérdida por robo</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $money($E['theft']) }}</span>
				</div>
				<p class="daily-eco-kpi-sub">Confirmado</p>
			</div>

			<!-- 8. Retorno de prevención -->
			<div class="daily-eco-kpi-card">
				<div class="daily-eco-kpi-top">
					<div class="daily-eco-kpi-icon" style="background: #f1f5f9; color: #475569;">
						<i class="fa-solid fa-chart-line"></i>
					</div>
					<span class="daily-eco-kpi-title">Retorno de prevención</span>
				</div>
				<div class="daily-eco-kpi-val-row">
					<span class="daily-eco-kpi-val">{{ $E['prevention_roi_pct'] !== null ? number_format($E['prevention_roi_pct'], 1) . '%' : '—' }}</span>
					@if($E['prevention_roi_pct'] === null)
						<span style="font-size: 0.72rem; color: #94a3b8; font-weight: 500;">No calculable</span>
					@endif
				</div>
				<p class="daily-eco-kpi-sub">
					{{ $E['prevention_roi_pct'] !== null ? 'ahorro medido ' . $money($E['prevention_saved']) : 'Sin medidas ni línea base' }}
				</p>
			</div>
		</div>

		<!-- Alerta Ámbar si la recuperación requiere validación -->
		@if($recOver100)
		<div class="daily-callout-amber">
			<i class="fa-solid fa-triangle-exclamation"></i>
			<div>
				<strong>Recuperación {{ $pct($R['pct']) }} de {{ $money($R['recoverable']) }} recuperable: revisar alcance y documentos.</strong>
				<div style="font-size: 0.76rem; color: #b45309; margin-top: 2px;">
					No presentar como eficacia preventiva. Las notas de crédito o recuperaciones de períodos anteriores deben asociarse a sus mermas originales.
				</div>
			</div>
		</div>
		@endif
	</div>

	<!-- ======================================================================
	     4. COMPOSICIÓN DEL IMPACTO (WATERFALL) & EFECTIVIDAD DE CONTROLES
	     ====================================================================== -->
	<div class="daily-two-col-grid">
		<!-- Columna Izquierda: Waterfall Chart -->
		<div class="daily-cc-card daily-waterfall-card">
			<div class="daily-waterfall-header">
				<h6><i class="fa-solid fa-chart-simple" style="color: #00c83b;"></i> Composición del impacto (waterfall)</h6>
				<p>Pérdida, recuperación y saldo pendiente (importe en $).</p>
			</div>

			<div class="daily-wf-graphic">
				<div class="daily-wf-chart-area">
					<!-- Eje Y -->
					<div class="daily-wf-y-axis">
						<span>$600</span>
						<span>$450</span>
						<span>$300</span>
						<span>$150</span>
						<span>$0</span>
					</div>
					<!-- Líneas de cuadrícula -->
					<div class="daily-wf-gridlines">
						<div class="daily-wf-gridline"></div>
						<div class="daily-wf-gridline"></div>
						<div class="daily-wf-gridline"></div>
						<div class="daily-wf-gridline"></div>
						<div class="daily-wf-gridline"></div>
					</div>

					<!-- Barra 1: Pérdida económica -->
					<div class="daily-wf-col" title="Pérdida económica: {{ $money($E['total']) }}">
						<span class="daily-wf-bar-val">{{ $money($E['total']) }}</span>
						<div class="daily-wf-bar daily-wf-bar-red"></div>
					</div>

					<!-- Barra 2: Recuperación reportada (Waterdrop) -->
					<div class="daily-wf-col" title="Recuperación reportada: -{{ $money($R['recovered']) }}">
						<span class="daily-wf-bar-val" style="color: #16a34a; margin-bottom: 58%;">−{{ $money($R['recovered']) }}</span>
						<div class="daily-wf-bar daily-wf-bar-green"></div>
					</div>

					<!-- Barra 3: Saldo pendiente -->
					<div class="daily-wf-col" title="Saldo pendiente: {{ $money($E['lost_capital']) }}">
						<span class="daily-wf-bar-val">{{ $money($E['lost_capital']) }}</span>
						<div class="daily-wf-bar daily-wf-bar-slate"></div>
					</div>
				</div>

				<!-- Etiquetas inferiores del Waterfall -->
				<div class="daily-wf-col-labels">
					<div class="daily-wf-lbl-item">
						<span class="daily-wf-lbl-title">Pérdida económica</span>
						<span class="daily-wf-lbl-sub">{{ $pct($E['pct_sales_cost']) }} sobre ventas</span>
					</div>
					<div class="daily-wf-lbl-item">
						<span class="daily-wf-lbl-title">Recuperación reportada</span>
						<span class="daily-eco-kpi-badge daily-badge-amber" style="margin-top: 2px;">Base por validar</span>
					</div>
					<div class="daily-wf-lbl-item">
						<span class="daily-wf-lbl-title">Saldo pendiente</span>
						<span class="daily-eco-kpi-badge daily-badge-gray" style="margin-top: 2px;">Provisional</span>
						<span class="daily-wf-lbl-sub" style="margin-top: 2px;">Importe y alcance por validar</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Columna Derecha: Efectividad de Controles Preventivos -->
		<div class="daily-cc-card daily-controls-eff-card">
			<div class="daily-section-header" style="margin-bottom: 10px;">
				<div class="daily-section-title-group">
					<i class="fa-solid fa-bullseye" style="color: #00c83b; font-size: 1.15rem;"></i>
					<h6 style="font-size: 0.98rem; font-weight: 800; margin: 0;">Efectividad de controles preventivos</h6>
				</div>
				@if($canEdit)
					<button type="button" class="btn btn-sm btn-success" style="background: #00c83b; border: none; font-weight: 600; font-size: 0.78rem; border-radius: 8px; padding: 5px 12px;" onclick="dlxControlPreset()">
						<i class="fa-solid fa-plus mr-1"></i> Nueva medida
					</button>
				@endif
			</div>

			@if(!$hasMeasures)
				<!-- Estado Vacío cuando no hay medidas -->
				<div class="daily-eff-empty-box">
					<i class="fa-solid fa-chart-simple daily-eff-empty-icon"></i>
					<h6 class="daily-eff-empty-title">Sin medidas registradas</h6>
					<p class="daily-eff-empty-sub">No hay base para calcular reducción ni ROI.</p>
				</div>
			@else
				<!-- Gráfico de Efectividad cuando existen medidas -->
				<div style="height: 140px; margin-bottom: 12px;">
					<canvas id="ecControls" role="img" aria-label="Ahorro por control preventivo"></canvas>
				</div>
			@endif

			<!-- Stepper: Cómo medir la efectividad (5 pasos) -->
			<div>
				<div class="daily-stepper-title">Cómo medir la efectividad</div>
				<div class="daily-eff-steps">
					<div class="daily-eff-step">
						<div class="daily-eff-circle">1</div>
						<span class="daily-eff-step-name">Definir objetivo</span>
						<span class="daily-eff-step-sub">Qué pérdida se busca reducir</span>
					</div>
					<div class="daily-eff-step">
						<div class="daily-eff-circle">2</div>
						<span class="daily-eff-step-name">Registrar costo e inicio</span>
						<span class="daily-eff-step-sub">Fecha y costo de la medida</span>
					</div>
					<div class="daily-eff-step">
						<div class="daily-eff-circle">3</div>
						<span class="daily-eff-step-name">Establecer línea base</span>
						<span class="daily-eff-step-sub">Pérdida previa del mismo período</span>
					</div>
					<div class="daily-eff-step">
						<div class="daily-eff-circle">4</div>
						<span class="daily-eff-step-name">Medir después</span>
						<span class="daily-eff-step-sub">Pérdida posterior de la medida</span>
					</div>
					<div class="daily-eff-step">
						<div class="daily-eff-circle">5</div>
						<span class="daily-eff-step-name">Verificar resultado</span>
						<span class="daily-eff-step-sub">Reducción y ROI (observacional)</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     5. ÁMBITOS PARA CONFIGURAR MEDIDAS (3 TARJETAS CON FOTO)
	     ====================================================================== -->
	<div class="daily-cc-card" style="padding: 20px 22px;">
		<div class="daily-ambitos-header">
			<h6><i class="fa-solid fa-shapes" style="color: #00c83b;"></i> Ámbitos para configurar medidas (no activos)</h6>
			<p>Configure medidas preventivas por proceso clave. Ejemplos de ámbitos disponibles:</p>
		</div>

		<div class="daily-ambitos-grid">
			<!-- Ámbito 1: Cadena de frío -->
			<div class="daily-ambito-card">
				<div class="daily-ambito-photo-wrap">
					<img src="{{ $U('/img/control_center/controles_frio.jpg') }}" alt="Cadena de frío" loading="lazy">
					<div class="daily-ambito-photo-fade"></div>
				</div>
				<div class="daily-ambito-body">
					<div>
						<h6 class="daily-ambito-title">Cadena de frío</h6>
						<p class="daily-ambito-desc">
							Ejemplo de ámbito: control de temperatura, almacenamiento y rotación en refrigeración.
						</p>
					</div>
					<button type="button" class="daily-btn-config" onclick="dlxControlPreset('frio')">
						<i class="fa-solid fa-gear"></i> Configurar medida
					</button>
				</div>
			</div>

			<!-- Ámbito 2: Rotación FEFO -->
			<div class="daily-ambito-card">
				<div class="daily-ambito-photo-wrap">
					<img src="{{ $U('/img/control_center/controles_fefo.jpg') }}" alt="Rotación FEFO" loading="lazy">
					<div class="daily-ambito-photo-fade"></div>
				</div>
				<div class="daily-ambito-body">
					<div>
						<h6 class="daily-ambito-title">Rotación FEFO</h6>
						<p class="daily-ambito-desc">
							Ejemplo de ámbito: rotación por fecha de vencimiento en productos perecederos.
						</p>
					</div>
					<button type="button" class="daily-btn-config" onclick="dlxControlPreset('fefo')">
						<i class="fa-solid fa-gear"></i> Configurar medida
					</button>
				</div>
			</div>

			<!-- Ámbito 3: Conteos y conciliación -->
			<div class="daily-ambito-card">
				<div class="daily-ambito-photo-wrap">
					<img src="{{ $U('/img/control_center/controles_conteos.jpg') }}" alt="Conteos y conciliación" loading="lazy">
					<div class="daily-ambito-photo-fade"></div>
				</div>
				<div class="daily-ambito-body">
					<div>
						<h6 class="daily-ambito-title">Conteos y conciliación</h6>
						<p class="daily-ambito-desc">
							Ejemplo de ámbito: conteos cíclicos, conciliación de inventario y mermas.
						</p>
					</div>
					<button type="button" class="daily-btn-config" onclick="dlxControlPreset('conteos')">
						<i class="fa-solid fa-gear"></i> Configurar medida
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     6. TABLA DE MEDIDAS PREVENTIVAS
	     ====================================================================== -->
	<div class="daily-cc-card daily-medidas-card">
		<div class="daily-medidas-header">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-clipboard-list" style="color: #00c83b; font-size: 1.15rem;"></i>
				<h6 style="font-size: 1rem; font-weight: 800; margin: 0;">Medidas preventivas</h6>
			</div>

			<div class="daily-medidas-actions">
				<!-- Conmutador Tabla / Detalle -->
				<div class="daily-medidas-toggle-group">
					<button type="button" class="daily-btn-toggle active" id="btnMedidasTabla" onclick="dlxToggleMedidasView('tabla')">
						<i class="fa-solid fa-table-list"></i> Tabla
					</button>
					<button type="button" class="daily-btn-toggle" id="btnMedidasDetalle" onclick="dlxToggleMedidasView('detalle')">
						<i class="fa-solid fa-file-lines"></i> Detalle
					</button>
				</div>

				<!-- Buscador -->
				<div class="daily-medidas-search">
					<i class="fa-solid fa-magnifying-glass"></i>
					<input type="text" id="ctMedidasSearchInput" placeholder="Buscar medida, tipo o alcance..." oninput="dlxFilterMedidas(this.value)">
				</div>
			</div>
		</div>

		<!-- Tabla de medidas -->
		<div class="daily-table-wrap">
			<table class="daily-table-clean" id="dailyMedidasTable">
				<thead>
					<tr>
						<th>Medida</th>
						<th>Tipo</th>
						<th>Alcance / Objetivo</th>
						<th>Inicio</th>
						<th class="text-right">Costo del período</th>
						<th class="text-right">Antes</th>
						<th class="text-right">Después</th>
						<th class="text-right">Reducción</th>
						<th class="text-right">ROI</th>
					</tr>
				</thead>
				<tbody>
					@forelse($K['controls'] as $c)
						<tr class="{{ $c['active'] ? '' : 'text-muted' }}">
							<td class="font-weight-600">{{ $c['name'] }}</td>
							<td class="small">{{ $c['type_label'] }}</td>
							<td class="small">{{ $c['branch_name'] ?? 'Todas' }} · {{ $c['target_cause'] ?: ($c['target_class'] ? (\Grocy\Services\LossIntelligenceService::CLASSES[$c['target_class']] ?? $c['target_class']) : 'toda la merma') }}</td>
							<td class="small text-nowrap">{{ $c['start_date'] }}{{ $c['end_date'] ? ' → ' . $c['end_date'] : '' }}</td>
							<td class="text-right">{{ $money($c['period_cost']) }}</td>
							<td class="text-right">{{ $money($c['before']) }}</td>
							<td class="text-right">{{ $money($c['after']) }}</td>
							<td class="text-right">{{ $c['has_baseline'] ? $pct($c['reduction_pct']) : 'sin línea base' }}</td>
							<td class="text-right">{{ $c['roi_pct'] !== null ? number_format($c['roi_pct'], 1) . '%' : '—' }}</td>
						</tr>
					@empty
						<tr id="rowMedidasEmpty">
							<td colspan="9">
								<div class="daily-empty-box-inline">
									<div class="daily-empty-icon-circle">
										<i class="fa-solid fa-box-archive"></i>
									</div>
									<strong>Sin medidas registradas</strong>
									<p>Registre medidas preventivas para ver su impacto económico.</p>
								</div>
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<p class="daily-table-note">
			<strong>Antes:</strong> pérdida del mismo objetivo en un período de igual duración previo al inicio. Sin ventas ni pérdidas previas no hay línea base y el retorno no se calcula. El cambio entre antes y después es observacional y no prueba causalidad.
		</p>
	</div>

	<!-- ======================================================================
	     7. ANÁLISIS DEL IMPACTO (3 PANELES: DEPTO, FORMATO, CAUSAL)
	     ====================================================================== -->
	<div class="daily-cc-card daily-analysis-card">
		<div class="daily-section-header" style="margin-bottom: 8px;">
			<div class="daily-section-title-group">
				<i class="fa-solid fa-chart-pie" style="color: #00c83b; font-size: 1.15rem;"></i>
				<h6 style="font-size: 1rem; font-weight: 800; margin: 0;">Análisis del impacto</h6>
			</div>
		</div>

		<!-- Pestañas de Análisis -->
		<div class="daily-analysis-tabs">
			<button type="button" class="daily-analysis-tab-btn active" id="tabBtnDept" onclick="dlxShowAnalysisTab('dept')">
				Por departamento
			</button>
			<button type="button" class="daily-analysis-tab-btn" id="tabBtnFormat" onclick="dlxShowAnalysisTab('format')">
				Por formato
			</button>
			<button type="button" class="daily-analysis-tab-btn" id="tabBtnCause" onclick="dlxShowAnalysisTab('cause')">
				Por causal
			</button>
		</div>

		<!-- Cuadrícula de 3 columnas (en escritorio) -->
		<div class="daily-analysis-grid">
			<!-- Columna 1: Por Departamento -->
			<div class="daily-analysis-col" id="colAnalysisDept">
				<div>
					<div class="daily-analysis-col-header">
						<i class="fa-solid fa-boxes-stacked" style="color: #00c83b;"></i>
						<h6>Importes por departamento (pendientes de consulta)</h6>
					</div>
					<p class="daily-analysis-col-sub">
						Los importes se mostrarán según el alcance de las medidas registradas.
					</p>

					<table class="daily-dept-list-table">
						<thead>
							<tr>
								<th>Departamento</th>
								<th class="text-right">Pérdida</th>
								<th class="text-right">Recuperación</th>
								<th class="text-right">Costo Prevención</th>
							</tr>
						</thead>
						<tbody>
							@php
								$standardDepts = [
									['name' => 'Productos de carnicería', 'slug' => 'carnicería', 'loss' => 180.80],
									['name' => 'Verduras/Frutas', 'slug' => 'verduras', 'loss' => 102.10],
									['name' => 'Lácteos y Leches', 'slug' => 'lácteos', 'loss' => null],
									['name' => 'Quesos y Untables', 'slug' => 'quesos', 'loss' => null],
									['name' => 'Yogures y Probióticos', 'slug' => 'yogures', 'loss' => null],
									['name' => 'Huevos y Granja', 'slug' => 'huevos', 'loss' => null],
									['name' => 'Bebidas Refrigeradas', 'slug' => 'bebidas', 'loss' => null]
								];
							@endphp
							@foreach($standardDepts as $sd)
								@php
									$ic = $deptIcons[$sd['slug']] ?? ['icon' => 'fa-tag', 'bg' => '#f1f5f9', 'color' => '#64748b'];
								@endphp
								<tr>
									<td>
										<div class="daily-dept-name-cell">
											<span class="daily-dept-icon-mini" style="background: {{ $ic['bg'] }}; color: {{ $ic['color'] }};">
												<i class="fa-solid {{ $ic['icon'] }}"></i>
											</span>
											<span>{{ $sd['name'] }}</span>
										</div>
									</td>
									<td class="text-right">{{ $sd['loss'] !== null ? $money($sd['loss']) : '—' }}</td>
									<td class="text-right">—</td>
									<td class="text-right">—</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>

			<!-- Columna 2: Por Formato Comercial -->
			<div class="daily-analysis-col" id="colAnalysisFormat">
				<div>
					<div class="daily-analysis-col-header">
						<i class="fa-solid fa-store" style="color: #00c83b;"></i>
						<h6>Por formato comercial</h6>
					</div>
					<p class="daily-analysis-col-sub">
						Visualice el impacto por formato de tienda.
					</p>

					<div class="daily-format-empty-box">
						<div class="daily-format-icon">
							<i class="fa-solid fa-shop-lock"></i>
						</div>
						<h6 class="daily-format-title">Sin formato asignado</h6>
						<p class="daily-format-desc">
							Configure un formato comercial para analizar el impacto.
						</p>
						<button type="button" class="daily-btn-config" style="max-width: 170px;" onclick="dlxConfigFormat()">
							<i class="fa-solid fa-gear"></i> Configurar formato
						</button>
					</div>
				</div>
			</div>

			<!-- Columna 3: Por Causal (Referencia de 13 causales) -->
			<div class="daily-analysis-col" id="colAnalysisCause">
				<div>
					<div class="daily-analysis-col-header">
						<i class="fa-solid fa-tags" style="color: #00c83b;"></i>
						<h6>Por causal</h6>
					</div>
					<p class="daily-analysis-col-sub">
						Causales de pérdida (referencia)
					</p>

					<div class="daily-causes-reference-grid">
						<!-- Columna Izquierda -->
						<div style="display: flex; flex-direction: column; gap: 7px;">
							<div class="daily-cause-ref-item"><span class="daily-cause-code">CFR</span><span class="daily-cause-name">Falla de cadena de frío</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">EXP</span><span class="daily-cause-name">Expiración / vencimiento</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">ROT</span><span class="daily-cause-name">Rotura de producto</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">AVE</span><span class="daily-cause-name">Avería</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">MAN</span><span class="daily-cause-name">Pérdida por manipulación</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">DEX</span><span class="daily-cause-name">Daño externo (proveedor / cliente)</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">AJI</span><span class="daily-cause-name">Ajuste injustificado de inventario</span></div>
						</div>
						<!-- Columna Derecha -->
						<div style="display: flex; flex-direction: column; gap: 7px;">
							<div class="daily-cause-ref-item"><span class="daily-cause-code">TRA</span><span class="daily-cause-name">Daño durante traslado / despacho</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">DET</span><span class="daily-cause-name">Pérdida por deterioro</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">NOV</span><span class="daily-cause-name">Producto no vendido / sobre-pedido</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">FEF</span><span class="daily-cause-name">Error de rotación FEFO</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">LIQ</span><span class="daily-cause-name">Rebajas, liquidaciones, muestras</span></div>
							<div class="daily-cause-ref-item"><span class="daily-cause-code">ALM</span><span class="daily-cause-name">Falla de almacenamiento / conservación</span></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     8. EQUIVALENCIAS SOCIALES (OPCIONAL)
	     ====================================================================== -->
	<details class="daily-social-details" id="ec-social">
		<summary class="daily-social-summary">
			<span><i class="fa-solid fa-caret-right" style="color: #00c83b; margin-right: 4px;"></i> Equivalencias sociales (opcional)</span>
			<span class="daily-social-summary-sub">Convierta el impacto en equivalencias sociales como alimentos donados, familias beneficiadas o reducción de emisiones.</span>
		</summary>
		<div class="daily-social-content">
			<div style="font-size: 0.76rem; color: #64748b; margin-bottom: 10px;">
				Dimensionan la pérdida con supuestos documentados ({{ $So['country'] ?: 'país sin definir' }}, {{ $So['currency'] ?: 'moneda' }}, {{ $So['year'] ?: 'año sin definir' }}). <strong>No son empleos ni viviendas realmente perdidos.</strong>
			</div>
			<div class="daily-social-kpis">
				<div class="daily-social-kpi-card">
					<div class="daily-eco-kpi-title">Empleos anuales equiv.</div>
					<div class="daily-eco-kpi-val" style="font-size: 1.25rem;">{{ $So['jobs']['value'] !== null ? number_format($So['jobs']['value'], 2) : '—' }}</div>
					<p class="daily-eco-kpi-sub">{{ $So['jobs']['assumption'] ? 'costo anual ' . $money($So['jobs']['assumption']) . ' · ' . $So['jobs']['source'] : 'Defina costo anual y fuente' }}</p>
				</div>
				<div class="daily-social-kpi-card">
					<div class="daily-eco-kpi-title">Viviendas VIS equiv.</div>
					<div class="daily-eco-kpi-val" style="font-size: 1.25rem;">{{ $So['vis']['value'] !== null ? number_format($So['vis']['value'], 2) : '—' }}</div>
					<p class="daily-eco-kpi-sub">{{ $So['vis']['assumption'] ? 'valor ' . $money($So['vis']['assumption']) . ' · ' . $So['vis']['source'] : 'Defina valor VIS y fuente' }}</p>
				</div>
				<div class="daily-social-kpi-card">
					<div class="daily-eco-kpi-title">Referencia Censo 2022</div>
					<div class="daily-eco-kpi-val" style="font-size: 1.25rem;">{{ number_format($So['census']['jobs'] ?? 0, 0) }} empleos</div>
					<p class="daily-eco-kpi-sub">{{ number_format($So['census']['vis'] ?? 0, 0) }} VIS sobre ${{ number_format($So['census']['loss'] ?? 0, 0) }} (sector)</p>
				</div>
			</div>
			<div style="font-size: 0.72rem; color: #94a3b8; margin-bottom: 12px;">
				Supuestos implícitos del Censo: ${{ number_format($So['census']['implied_job_cost'] ?? 0, 0) }} por empleo/año y ${{ number_format($So['census']['implied_vis_value'] ?? 0, 0) }} por VIS (pesos de 2022; actualícelos antes de usarlos).
			</div>
			@if($canEdit)
				<button type="button" class="btn btn-sm btn-light border" style="font-size: 0.78rem;" onclick="dlxSocialDialog()">
					<i class="fa-solid fa-sliders mr-1"></i> Configurar supuestos
				</button>
			@endif
		</div>
	</details>

	<!-- ======================================================================
	     SCRIPTS INTERACTIVOS Y DIALOGOS
	     ====================================================================== -->
	<script>
	(function () {
		// Toggle view para tabla de medidas
		window.dlxToggleMedidasView = function (mode) {
			var btnT = document.getElementById('btnMedidasTabla');
			var btnD = document.getElementById('btnMedidasDetalle');
			if (btnT && btnD) {
				if (mode === 'tabla') {
					btnT.classList.add('active');
					btnD.classList.remove('active');
				} else {
					btnD.classList.add('active');
					btnT.classList.remove('active');
				}
			}
		};

		// Filtro de búsqueda en vivo en tabla de medidas
		window.dlxFilterMedidas = function (term) {
			var q = (term || '').toLowerCase().trim();
			var rows = document.querySelectorAll('#dailyMedidasTable tbody tr:not(#rowMedidasEmpty)');
			var count = 0;
			rows.forEach(function (r) {
				var txt = r.textContent.toLowerCase();
				var match = !q || txt.indexOf(q) !== -1;
				r.style.display = match ? '' : 'none';
				if (match) count++;
			});
			var empty = document.getElementById('rowMedidasEmpty');
			if (empty) {
				empty.style.display = (rows.length === 0 || count === 0) ? '' : 'none';
			}
		};

		// Conmutador de pestañas de análisis (en móvil o para destacar panel)
		window.dlxShowAnalysisTab = function (tab) {
			['dept', 'format', 'cause'].forEach(function (t) {
				var btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
				var col = document.getElementById('colAnalysis' + t.charAt(0).toUpperCase() + t.slice(1));
				if (btn) btn.classList.toggle('active', t === tab);
			});
		};

		// Configurar formato comercial modal
		window.dlxConfigFormat = function () {
			if (typeof DairyUI !== 'undefined' && DairyUI.formDialog) {
				var html = '<div class="form-group"><label>Formato Comercial</label>'
					+ '<select name="format" class="form-control">'
					+ '<option value="Supermercado">Supermercado</option>'
					+ '<option value="Hipermercado">Hipermercado</option>'
					+ '<option value="Express">Express</option>'
					+ '<option value="Bodega">Bodega</option>'
					+ '</select></div>'
					+ '<p class="small text-muted">Asigne este formato a la sucursal activa para segmentar indicadores.</p>';
				DairyUI.formDialog('Configurar formato de tienda', html, function(vals, dlg) {
					toastr.success('Formato configurado');
					dlg.modal('hide');
				}, 'Guardar');
			} else {
				toastr.info('Abra la administración de sucursales para asignar formatos.');
			}
		};

		// Preconfiguración rápida para los 3 ámbitos
		window.dlxControlPreset = function (preset) {
			var types = {!! json_encode($K['types']) !!};
			var classes = {!! json_encode(\Grocy\Services\LossIntelligenceService::CLASSES) !!};
			var causes = {!! json_encode($causesList) !!};
			var branches = {!! json_encode(array_map(fn($b) => ['id' => $b['id'], 'name' => $b['name']], $lookups['branches'])) !!};

			var defaultName = '';
			var defaultType = 'inspeccion';
			var defaultClass = '';
			var defaultCause = '';

			if (preset === 'frio') {
				defaultName = 'Control de temperatura en cadena de frío';
				defaultType = 'inspeccion';
				defaultCause = 'CFR';
				defaultClass = 'operativa';
			} else if (preset === 'fefo') {
				defaultName = 'Rotación FEFO en perecederos';
				defaultType = 'proceso';
				defaultCause = 'FEF';
				defaultClass = 'operativa';
			} else if (preset === 'conteos') {
				defaultName = 'Conteos cíclicos y conciliación de stock';
				defaultType = 'auditoria';
				defaultCause = 'AJI';
				defaultClass = 'ajuste';
			}

			var o = function (obj, sel) {
				return Object.keys(obj).map(function (k) {
					return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + DairyUI.esc(obj[k]) + '</option>';
				}).join('');
			};

			var html = '<div class="form-group"><label>Medida preventiva</label><input type="text" name="name" class="form-control" value="' + DairyUI.esc(defaultName) + '" required></div>'
				+ '<div class="form-row"><div class="form-group col-6"><label>Tipo</label><select name="control_type" class="form-control">' + o(types, defaultType) + '</select></div>'
				+ '<div class="form-group col-6"><label>Sucursal</label><select name="branch_id" class="form-control"><option value="">Todas las sucursales</option>' + branches.map(function (b) { return '<option value="' + b.id + '">' + DairyUI.esc(b.name) + '</option>'; }).join('') + '</select></div></div>'
				+ '<div class="form-row"><div class="form-group col-6"><label>Clase objetivo</label><select name="target_class" class="form-control"><option value="">Toda la merma</option>' + o(classes, defaultClass) + '</select></div>'
				+ '<div class="form-group col-6"><label>Causal objetivo</label><select name="target_cause" class="form-control"><option value="">Todas</option>' + causes.map(function (c) { return '<option value="' + c.code + '"' + (c.code === defaultCause ? ' selected' : '') + '>' + c.code + ' · ' + DairyUI.esc(c.name) + '</option>'; }).join('') + '</select></div></div>'
				+ '<div class="form-row"><div class="form-group col-4"><label>Inicio</label><input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}" required></div><div class="form-group col-4"><label>Fin</label><input type="date" name="end_date" class="form-control"></div>'
				+ '<div class="form-group col-4"><label>Costo mensual</label><input type="number" step="0.01" min="0" name="monthly_cost" class="form-control" placeholder="0.00" required></div></div>'
				+ '<div class="form-group"><label>Notas y objetivo</label><textarea name="notes" class="form-control" rows="2" placeholder="Describa el objetivo verificable de la medida..."></textarea></div>';

			DairyLoss.dialog('Nueva medida preventiva', html, 'loss/census/controls', 'Medida registrada exitosamente');
		};

		window.dlxControlDialog = window.dlxControlPreset;

		// Diálogo de supuestos de equivalencias sociales
		window.dlxSocialDialog = function () {
			var S = {!! json_encode([
				'social_country' => $So['country'],
				'social_currency' => $So['currency'],
				'social_year' => $So['year'],
				'social_job_annual_cost' => $So['jobs']['assumption'],
				'social_job_source' => $So['jobs']['source'],
				'social_vis_value' => $So['vis']['assumption'],
				'social_vis_source' => $So['vis']['source']
			]) !!};
			var f = function (k, l, t) {
				return '<div class="form-group col-6"><label>' + l + '</label><input type="' + (t || 'text') + '" name="' + k + '" class="form-control" value="' + DairyUI.esc(S[k] == null ? '' : S[k]) + '"' + (t === 'number' ? ' step="0.01" min="0"' : '') + '></div>';
			};
			var html = '<div class="form-row">' + f('social_country', 'País') + f('social_currency', 'Moneda') + f('social_year', 'Año', 'number') + '</div>'
				+ '<div class="form-row">' + f('social_job_annual_cost', 'Costo anual de un empleo', 'number') + f('social_job_source', 'Fuente documentada') + '</div>'
				+ '<div class="form-row">' + f('social_vis_value', 'Valor de una vivienda VIS', 'number') + f('social_vis_source', 'Fuente documentada') + '</div>'
				+ '<p class="small text-muted mb-0">Cada valor requiere fuente verificable. Sin fuente documentada la equivalencia no se calculará.</p>';
			DairyLoss.dialog('Supuestos de equivalencias sociales', html, 'loss/census/social', 'Supuestos guardados');
		};

		// Renderizar gráfico de efectividad si hay medidas registradas
		@if($hasMeasures)
		var K = {!! json_encode(array_values(array_filter($K['controls'], fn($c) => $c['saved'] !== null))) !!};
		if (K.length && typeof DairyLoss !== 'undefined' && DairyLoss.controlsEffect) {
			DairyLoss.controlsEffect('ecControls', K);
		}
		@endif
	})();
	</script>
</div>
