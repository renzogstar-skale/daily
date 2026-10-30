@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 1) . '%';
	$st = $_GET['ae_status'] ?? 'open';
	$catSel = $_GET['ae_category'] ?? '';
	
	$stOpts = '<option value="open"' . ($st === 'open' ? ' selected' : '') . '>Abiertos (' . ($ae['open'] ?? 0) . ')</option>'
		. '<option value="all"' . ($st === 'all' ? ' selected' : '') . '>Todos (' . ($ae['total'] ?? 0) . ')</option>';
	foreach ($ae['statuses'] as $k => $l)
	{
		$stOpts .= '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . e($l) . '</option>';
	}
	$catOpts = '<option value="">Todas</option>';
	foreach ($ae['categories'] as $k => $c)
	{
		$catOpts .= '<option value="' . $k . '"' . ($catSel === $k ? ' selected' : '') . '>' . $k . ' · ' . e($c[0]) . '</option>';
	}

	$flow = $ae['flow'];
	$next = function ($s) use ($flow) {
		$i = array_search($s, $flow, true);
		return $i !== false && isset($flow[$i + 1]) ? $flow[$i + 1] : null;
	};
	$needsApprover = ['approved', 'verified'];
	$tone = [
		'detected' => 'badge-warning',
		'evidence' => 'badge-warning',
		'reconciled' => 'badge-info',
		'approved' => 'badge-primary',
		'corrected' => 'badge-secondary',
		'verified' => 'badge-success',
		'closed' => 'badge-success',
		'dismissed' => 'badge-light'
	];

	$categoryIcons = [
		'DOC' => 'fa-file-lines',
		'REC' => 'fa-truck',
		'BAJ' => 'fa-trash-can',
		'CON' => 'fa-clipboard-check',
		'PRE' => 'fa-tag',
		'INT' => 'fa-server',
		'DES' => 'fa-route',
		'MAR' => 'fa-barcode',
		'CAJ' => 'fa-cash-register',
		'DDS' => 'fa-boxes-stacked',
		'EPC' => 'fa-wifi'
	];
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER: ESTILOS - PESTAÑA ERRORES ADMINISTRATIVOS
   ========================================================================== */
.daily-errores-tab {
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
.daily-errores-header-card {
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
.daily-errores-header-left {
	display: flex;
	align-items: center;
	gap: 16px;
	z-index: 2;
	max-width: 60%;
}
.daily-errores-header-icon {
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
.daily-errores-header-title {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	margin: 0 0 4px 0;
}
.daily-errores-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-errores-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 46%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
}
.daily-errores-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: right center;
}
.daily-errores-header-photo-mask {
	position: absolute;
	inset: 0;
	background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.82) 20%, rgba(255, 255, 255, 0) 100%);
}

/* 2. BARRA DE FILTROS LOCALES */
.daily-errores-filters-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 18px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}
.daily-errores-filters-form {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	flex-wrap: wrap;
}
.daily-filter-col {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1 1 140px;
	min-width: 120px;
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

/* 3. FLUJO DE RESOLUCIÓN */
.daily-process-card {
	padding: 18px 22px;
}
.daily-process-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 16px;
}
.daily-process-title-group h6 {
	font-size: 1rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 2px 0;
}
.daily-process-title-group p {
	font-size: 0.78rem;
	color: #64748b;
	margin: 0;
}
.daily-process-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
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
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-primary:hover {
	background: #00b034 !important;
	transform: translateY(-1px);
}
.daily-btn-outline-green {
	background: #ffffff !important;
	color: #00c83b !important;
	border: 1px solid #00c83b !important;
	font-weight: 600;
	font-size: 0.82rem;
	border-radius: 8px;
	padding: 6px 14px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-outline-green:hover {
	background: #f0fdf4 !important;
}

/* Pasos del flujo */
.daily-flow-steps {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 8px;
	margin: 16px 0 14px 0;
	overflow-x: auto;
	padding-bottom: 4px;
}
.daily-flow-step {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	flex: 1;
	min-width: 110px;
	position: relative;
}
.daily-step-circle {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: #f0fdf4;
	color: #16a34a;
	border: 1px solid #bbf7d0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.82rem;
	flex-shrink: 0;
}
.daily-step-content {
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.daily-step-title {
	font-size: 0.82rem;
	font-weight: 700;
	color: #0f172a;
	line-height: 1.2;
}
.daily-step-desc {
	font-size: 0.68rem;
	color: #64748b;
	line-height: 1.3;
}
.daily-step-arrow {
	color: #cbd5e1;
	font-size: 0.75rem;
	align-self: center;
	margin: 0 4px;
	flex-shrink: 0;
}
.daily-flow-alert {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 8px;
	padding: 8px 14px;
	font-size: 0.75rem;
	color: #92400e;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-flow-alert i {
	color: #d97706;
}

/* 4. 3 TARJETAS FOTOGRÁFICAS DE ACCESO */
.daily-action-cards-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16px;
}
.daily-action-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.daily-action-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.daily-action-photo {
	position: relative;
	height: 110px;
	width: 100%;
	background: #f1f5f9;
	overflow: hidden;
}
.daily-action-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-action-photo-fade {
	position: absolute;
	inset: 0;
	background: linear-gradient(180deg, rgba(255, 255, 255, 0) 35%, rgba(255, 255, 255, 0.9) 85%, #ffffff 100%);
}
.daily-action-content {
	padding: 12px 18px 18px 18px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	flex-grow: 1;
	gap: 14px;
	position: relative;
	z-index: 2;
}
.daily-action-title {
	font-size: 0.95rem;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 4px 0;
}
.daily-action-desc {
	font-size: 0.76rem;
	color: #64748b;
	line-height: 1.35;
	margin: 0;
}
.daily-btn-card-primary {
	width: 100%;
	background: #00c83b;
	color: #ffffff;
	border: none;
	border-radius: 8px;
	font-size: 0.82rem;
	font-weight: 600;
	padding: 8px 14px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-btn-card-primary:hover {
	background: #00b034;
}
.daily-btn-card-outline {
	width: 100%;
	background: #ffffff;
	color: #00c83b;
	border: 1px solid #00c83b;
	border-radius: 8px;
	font-size: 0.82rem;
	font-weight: 600;
	padding: 8px 14px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-card-outline:hover {
	background: #f0fdf4;
}
.daily-btn-card-amber {
	width: 100%;
	background: #ffffff;
	color: #d97706;
	border: 1px solid #d97706;
	border-radius: 8px;
	font-size: 0.82rem;
	font-weight: 600;
	padding: 8px 14px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-btn-card-amber:hover {
	background: #fffbeb;
}

/* 5. 5 INDICADORES ESPECÍFICOS COMPACTOS */
.daily-errores-kpis-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 14px;
}
.daily-kpi-compact {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	gap: 3px;
	position: relative;
}
.daily-kpi-compact-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.daily-kpi-compact-title {
	font-size: 0.68rem;
	font-weight: 700;
	color: #64748b;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.daily-kpi-compact-icon {
	color: #94a3b8;
	font-size: 0.85rem;
}
.daily-kpi-compact-val {
	font-size: 1.45rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	line-height: 1.15;
	margin-top: 2px;
}
.daily-kpi-compact-sub {
	font-size: 0.74rem;
	color: #64748b;
	font-weight: 500;
}

/* 6. CASOS Y HALLAZGOS */
.daily-cases-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 14px;
}
.daily-cases-header h6 {
	font-size: 1rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
}
.daily-cases-controls {
	display: flex;
	align-items: center;
	gap: 10px;
}
.daily-cases-search {
	position: relative;
	width: 240px;
}
.daily-cases-search input {
	width: 100%;
	height: 32px;
	border-radius: 6px;
	border: 1px solid #cbd5e1;
	font-size: 0.78rem;
	padding: 4px 10px 4px 28px;
}
.daily-cases-search i {
	position: absolute;
	left: 9px;
	top: 9px;
	color: #94a3b8;
	font-size: 0.75rem;
}
.daily-tabs-pills {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 14px;
	border-bottom: 1px solid #e2e8f0;
	padding-bottom: 8px;
}
.daily-tab-pill-btn {
	background: transparent;
	border: 1px solid transparent;
	border-radius: 6px;
	padding: 4px 12px;
	font-size: 0.78rem;
	font-weight: 600;
	color: #64748b;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-tab-pill-btn.active {
	background: #f0fdf4;
	color: #16a34a;
	border-color: #bbf7d0;
}
.daily-cases-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.81rem;
}
.daily-cases-table th {
	background: transparent;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 8px 10px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-cases-table td {
	padding: 10px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
	color: #1e293b;
}
.daily-empty-cases {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 36px 16px;
	text-align: center;
	color: #64748b;
}
.daily-empty-cases i {
	font-size: 1.8rem;
	color: #cbd5e1;
	margin-bottom: 8px;
}
.daily-empty-cases-title {
	font-weight: 700;
	color: #0f172a;
	font-size: 0.88rem;
	margin-bottom: 2px;
}
.daily-empty-cases-sub {
	font-size: 0.76rem;
	color: #64748b;
}

/* 7. CATEGORÍAS ADMINISTRATIVAS */
.daily-categories-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 16px;
}
.daily-categories-title-group h6 {
	font-size: 1rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 2px 0;
}
.daily-categories-title-group p {
	font-size: 0.78rem;
	color: #64748b;
	margin: 0;
}
.daily-toggle-view {
	display: inline-flex;
	background: #f1f5f9;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 2px;
}
.daily-toggle-view-btn {
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
.daily-toggle-view-btn.active {
	background: #00c83b;
	color: #ffffff;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.daily-category-cards-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
}
.daily-cat-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 14px;
	display: flex;
	align-items: flex-start;
	gap: 12px;
	transition: border-color 0.15s ease, box-shadow 0.15s ease;
	position: relative;
}
.daily-cat-card:hover {
	border-color: #00c83b;
	box-shadow: 0 2px 8px rgba(0, 200, 59, 0.08);
}
.daily-cat-icon {
	width: 32px;
	height: 32px;
	border-radius: 8px;
	background: #f0fdf4;
	color: #16a34a;
	border: 1px solid #bbf7d0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.88rem;
	flex-shrink: 0;
}
.daily-cat-body {
	flex-grow: 1;
	min-width: 0;
}
.daily-cat-top {
	display: flex;
	align-items: baseline;
	gap: 6px;
	margin-bottom: 2px;
}
.daily-cat-code {
	font-family: monospace;
	font-size: 0.8rem;
	font-weight: 800;
	color: #0f172a;
}
.daily-cat-name {
	font-size: 0.78rem;
	font-weight: 700;
	color: #1e293b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-cat-meta {
	font-size: 0.7rem;
	color: #64748b;
	margin-bottom: 4px;
}
.daily-cat-link {
	font-size: 0.72rem;
	font-weight: 600;
	color: #16a34a;
	text-decoration: none;
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.daily-cat-link:hover {
	color: #15803d;
	text-decoration: underline;
}

/* 8. 3 TARJETAS INFERIORES */
.daily-bottom-cards-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16px;
}
.daily-bottom-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 16px 18px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.daily-bottom-card-amber {
	background: #fffbeb;
	border-color: #fde68a;
}
.daily-bottom-card-title {
	font-size: 0.85rem;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-bottom-card-amber .daily-bottom-card-title {
	color: #92400e;
}
.daily-bottom-card-body {
	font-size: 0.76rem;
	color: #64748b;
	line-height: 1.4;
}
.daily-bottom-card-amber .daily-bottom-card-body {
	color: #b45309;
}

/* Responsive */
@media (max-width: 991px) {
	.daily-errores-header-photo {
		display: none;
	}
	.daily-errores-header-left {
		max-width: 100%;
	}
	.daily-action-cards-grid {
		grid-template-columns: 1fr;
	}
	.daily-category-cards-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-errores-kpis-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	.daily-bottom-cards-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 640px) {
	.daily-errores-header-card {
		padding: 16px;
	}
	.daily-errores-header-left {
		flex-direction: row;
		align-items: flex-start;
		gap: 12px;
	}
	.daily-errores-filters-form {
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
	.daily-flow-steps {
		flex-direction: column;
		gap: 14px;
		padding: 0;
	}
	.daily-flow-step {
		width: 100%;
		min-width: 100%;
	}
	.daily-step-arrow {
		display: none;
	}
	.daily-errores-kpis-grid {
		grid-template-columns: 1fr;
		gap: 10px;
	}
	.daily-category-cards-grid {
		grid-template-columns: 1fr;
	}
	.daily-cases-header {
		flex-direction: column;
		align-items: flex-start;
	}
	.daily-cases-controls {
		width: 100%;
		flex-direction: column;
	}
	.daily-cases-search {
		width: 100%;
	}
	.daily-tabs-pills {
		overflow-x: auto;
		width: 100%;
		padding-bottom: 4px;
	}
	.daily-categories-header {
		flex-direction: column;
		align-items: flex-start;
		gap: 12px;
	}
	.daily-categories-view-toggle {
		width: 100%;
	}
	.daily-btn-toggle-view {
		flex: 1;
		text-align: center;
	}
}
</style>

<div class="daily-errores-tab" id="dailyErroresTabContainer">

	<!-- ======================================================================
	     1. ENCABEZADO CON BANNER FOTOGRÁFICO
	     ====================================================================== -->
	<div class="daily-errores-header-card">
		<div class="daily-errores-header-left">
			<div class="daily-errores-header-icon">
				<i class="fa-regular fa-file-lines"></i>
			</div>
			<div>
				<h5 class="daily-errores-header-title">Errores administrativos</h5>
				<p class="daily-errores-header-sub">
					Las 10 categorías del Censo y EPC/RFID. Detecta, analiza, concilia y corrige hallazgos antes de su ajuste. Quien concilia no aprueba y quien corrige no verifica. Repetir una auditoría no duplica casos.
				</p>
			</div>
		</div>
		<div class="daily-errores-header-photo">
			<img src="{{ $U('/img/control_center/errores_banner.jpg') }}" alt="Auditoría administrativa de supermercado" loading="lazy">
			<div class="daily-errores-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     2. FILTROS LOCALES
	     ====================================================================== -->
	<div class="daily-errores-filters-card">
		<form method="GET" action="{{ $U('/waste') }}#tab-errores" class="daily-errores-filters-form" id="dailyErroresFiltersForm">
			<!-- Sucursal -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ae_branch_id">Sucursal</label>
				<select name="branch_id" id="ae_branch_id" class="daily-filter-select">
					<option value="">Todas las sucursales</option>
					@foreach($lookups['branches'] ?? [] as $b)
						<option value="{{ $b['id'] }}" {{ ($f['branch_id'] ?? null) == $b['id'] ? 'selected' : '' }}>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ae_group_id">Departamento</label>
				<select name="group_id" id="ae_group_id" class="daily-filter-select">
					<option value="">Fruver y Carnes (Perec...</option>
					@foreach($lookups['groups'] ?? [] as $g)
						<option value="{{ $g['id'] }}" {{ ($f['group_id'] ?? null) == $g['id'] ? 'selected' : '' }}>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Rango de Fechas -->
			<div class="daily-filter-col" style="flex: 1.4 1 200px;">
				<label class="daily-filter-lbl">Rango de fechas</label>
				<div class="d-flex align-items-center gap-1">
					<input type="date" name="start_date" class="daily-filter-date" value="{{ $startDate }}" style="width: 50%;">
					<span class="text-muted small">&rarr;</span>
					<input type="date" name="end_date" class="daily-filter-date" value="{{ $endDate }}" style="width: 50%;">
				</div>
			</div>

			<!-- Estado del Caso -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ae_status">Estado del caso</label>
				<select name="ae_status" id="ae_status" class="daily-filter-select">
					{!! $stOpts !!}
				</select>
			</div>

			<!-- Categoría -->
			<div class="daily-filter-col">
				<label class="daily-filter-lbl" for="ae_category">Categoría</label>
				<select name="ae_category" id="ae_category" class="daily-filter-select">
					{!! $catOpts !!}
				</select>
			</div>

			<!-- Botones de Acción -->
			<div class="d-flex align-items-center gap-2">
				<button type="submit" class="daily-btn-apply">
					<i class="fa-solid fa-check"></i> Aplicar
				</button>
				@include('components.dairy_export', ['exportScope' => '#tab-errores', 'exportName' => 'errores_administrativos'])
			</div>
		</form>
	</div>

	<!-- ======================================================================
	     3. PROCESO DE GESTIÓN Y BOTONES DE ACCIÓN
	     ====================================================================== -->
	<div class="daily-cc-card daily-process-card">
		<div class="daily-process-header">
			<div class="daily-process-title-group">
				<h6>Proceso de gestión de errores administrativos</h6>
				<p>Flujo completo desde la detección hasta el cierre. Cada rol tiene una responsabilidad específica.</p>
			</div>
			<div class="daily-process-actions">
				<button type="button" class="daily-btn-primary" onclick="dlxAeDetect(this)">
					<i class="fa-solid fa-play fa-xs"></i> Ejecutar auditoría
				</button>
				<button type="button" class="daily-btn-outline-green" onclick="dlxAeNew()">
					<i class="fa-solid fa-plus"></i> Registrar error
				</button>
				<div class="dropdown">
					<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" style="border-radius: 8px; height: 34px;">
						<i class="fa-solid fa-download mr-1"></i> Exportar
					</button>
					<div class="dropdown-menu dropdown-menu-right">
						<a class="dropdown-item" href="#" onclick="DairyUI.exportTable('#tab-errores table', 'csv', 'errores_admin'); return false;">Exportar a CSV</a>
						<a class="dropdown-item" href="#" onclick="DairyUI.exportTable('#tab-errores table', 'excel', 'errores_admin'); return false;">Exportar a Excel</a>
					</div>
				</div>
			</div>
		</div>

		<!-- 7 Pasos del Flujo -->
		<div class="daily-flow-steps">
			<!-- 1. Detectar -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-solid fa-magnifying-glass"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Detectar</span>
					<span class="daily-step-desc">Identifica posibles errores en procesos.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 2. Evidencia -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-regular fa-image"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Evidencia</span>
					<span class="daily-step-desc">Reúne y adjunta la documentación.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 3. Conciliar -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-solid fa-scale-balanced"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Conciliar</span>
					<span class="daily-step-desc">Compara y valida la información.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 4. Aprobar -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-solid fa-file-circle-check"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Aprobar</span>
					<span class="daily-step-desc">Revisa y autoriza el ajuste.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 5. Corregir -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-solid fa-wrench"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Corregir</span>
					<span class="daily-step-desc">Aplica la corrección en el sistema.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 6. Verificar -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-regular fa-eye"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Verificar</span>
					<span class="daily-step-desc">Confirma la corrección en operación.</span>
				</div>
			</div>
			<span class="daily-step-arrow">&rarr;</span>

			<!-- 7. Cerrar -->
			<div class="daily-flow-step">
				<div class="daily-step-circle"><i class="fa-regular fa-flag"></i></div>
				<div class="daily-step-content">
					<span class="daily-step-title">Cerrar</span>
					<span class="daily-step-desc">Cierra el caso con trazabilidad.</span>
				</div>
			</div>
		</div>

		<!-- Alerta de Segregación de Funciones -->
		<div class="daily-flow-alert">
			<i class="fa-solid fa-circle-exclamation"></i>
			<span>Quien concilia no aprueba y quien corrige no verifica. Repetir una auditoría no duplica casos.</span>
		</div>
	</div>

	<!-- ======================================================================
	     4. 3 TARJETAS DE ACCESO CON FOTOGRAFÍAS
	     ====================================================================== -->
	<div class="daily-action-cards-grid">
		<!-- Tarjeta 1: Revisión documental -->
		<div class="daily-action-card">
			<div class="daily-action-photo">
				<img src="{{ $U('/img/control_center/errores_revision_doc.jpg') }}" alt="Revisión documental" loading="lazy">
				<div class="daily-action-photo-fade"></div>
			</div>
			<div class="daily-action-content">
				<div>
					<h6 class="daily-action-title">Revisión documental</h6>
					<p class="daily-action-desc">Valida registros, facturas, notas de entrada y documentos de soporte.</p>
				</div>
				<button type="button" class="daily-btn-card-primary" onclick="dlxAeDetect(this)">
					<i class="fa-solid fa-play fa-xs"></i> Ejecutar auditoría
				</button>
			</div>
		</div>

		<!-- Tarjeta 2: Recepción y despacho -->
		<div class="daily-action-card">
			<div class="daily-action-photo">
				<img src="{{ $U('/img/control_center/errores_recepcion.jpg') }}" alt="Recepción y despacho" loading="lazy">
				<div class="daily-action-photo-fade"></div>
			</div>
			<div class="daily-action-content">
				<div>
					<h6 class="daily-action-title">Recepción y despacho</h6>
					<p class="daily-action-desc">Verifica diferencias en recepción, despachos y movimientos de mercancía.</p>
				</div>
				<button type="button" class="daily-btn-card-outline" onclick="dlxAeNew('REC')">
					<i class="fa-solid fa-plus"></i> Registrar hallazgo
				</button>
			</div>
		</div>

		<!-- Tarjeta 3: Marcación y EPC -->
		<div class="daily-action-card">
			<div class="daily-action-photo">
				<img src="{{ $U('/img/control_center/errores_marcacion.jpg') }}" alt="Marcación y EPC" loading="lazy">
				<div class="daily-action-photo-fade"></div>
			</div>
			<div class="daily-action-content">
				<div>
					<h6 class="daily-action-title">Marcación y EPC</h6>
					<p class="daily-action-desc">Revisa marcación de productos, etiquetas y lecturas EPC/RFID en tienda y bodega.</p>
				</div>
				<button type="button" class="daily-btn-card-amber" onclick="dlxAeNew('EPC')">
					<i class="fa-solid fa-plus"></i> Registrar error
				</button>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     5. 5 INDICADORES ESPECÍFICOS COMPACTOS
	     ====================================================================== -->
	<div class="daily-errores-kpis-grid">
		<!-- 1. Casos Registrados -->
		<div class="daily-kpi-compact">
			<div class="daily-kpi-compact-top">
				<span class="daily-kpi-compact-title">Casos registrados</span>
				<i class="fa-regular fa-file-lines daily-kpi-compact-icon"></i>
			</div>
			<div class="daily-kpi-compact-val font-mono">{{ $ae['total'] ?? 0 }}</div>
			<div class="daily-kpi-compact-sub">sin contar descartados</div>
		</div>

		<!-- 2. Abiertos -->
		<div class="daily-kpi-compact">
			<div class="daily-kpi-compact-top">
				<span class="daily-kpi-compact-title">Abiertos</span>
				<i class="fa-regular fa-folder-open daily-kpi-compact-icon text-warning"></i>
			</div>
			<div class="daily-kpi-compact-val font-mono">{{ $ae['open'] ?? 0 }}</div>
			<div class="daily-kpi-compact-sub">en algún paso del flujo</div>
		</div>

		<!-- 3. Vencidos -->
		<div class="daily-kpi-compact">
			<div class="daily-kpi-compact-top">
				<span class="daily-kpi-compact-title">Vencidos</span>
				<i class="fa-regular fa-clock daily-kpi-compact-icon text-danger"></i>
			</div>
			<div class="daily-kpi-compact-val font-mono">{{ $ae['overdue'] ?? 0 }}</div>
			<div class="daily-kpi-compact-sub">fuera de plazo</div>
		</div>

		<!-- 4. Impacto Estimado -->
		<div class="daily-kpi-compact">
			<div class="daily-kpi-compact-top">
				<span class="daily-kpi-compact-title">Impacto estimado</span>
				<i class="fa-solid fa-calculator daily-kpi-compact-icon"></i>
			</div>
			<div class="daily-kpi-compact-val font-mono">{{ $money($ae['impact'] ?? 0) }}</div>
			<div class="daily-kpi-compact-sub">No suma a la merma</div>
		</div>

		<!-- 5. EPC / RFID -->
		<div class="daily-kpi-compact">
			<div class="daily-kpi-compact-top">
				<span class="daily-kpi-compact-title">EPC / RFID</span>
				<i class="fa-solid fa-wifi daily-kpi-compact-icon"></i>
			</div>
			<div class="daily-kpi-compact-val" style="font-size: 1.15rem; font-weight: 800; margin-top: 4px;">
				{{ ($ae['rfid_ready'] ?? false) ? 'Con datos' : 'Sin lector' }}
			</div>
			<div class="daily-kpi-compact-sub">
				{{ ($ae['rfid_ready'] ?? false) ? 'discrepancias registradas' : 'Registro manual disponible' }}
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     6. CASOS Y HALLAZGOS
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-cases-header">
			<h6>Casos y hallazgos</h6>
			<div class="daily-cases-controls">
				<div class="daily-cases-search">
					<i class="fa-solid fa-magnifying-glass"></i>
					<input type="text" id="aeCaseSearchInput" placeholder="Buscar caso, producto, proveedor..." onkeyup="filterCasesTable(this.value)">
				</div>
				<button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius: 6px; font-size: 0.78rem;">
					<i class="fa-solid fa-filter mr-1"></i> Filtros
				</button>
			</div>
		</div>

		<!-- Filtros de Pestaña de Estado -->
		<div class="daily-tabs-pills">
			<button type="button" class="daily-tab-pill-btn active" onclick="filterCasesByStatus('all', this)">
				Todos ({{ count($ae['cases'] ?? []) }})
			</button>
			<button type="button" class="daily-tab-pill-btn" onclick="filterCasesByStatus('open', this)">
				Abiertos ({{ $ae['open'] ?? 0 }})
			</button>
			<button type="button" class="daily-tab-pill-btn" onclick="filterCasesByStatus('closed', this)">
				Cerrados ({{ $ae['closed'] ?? 0 }})
			</button>
			<button type="button" class="daily-tab-pill-btn" onclick="filterCasesByStatus('dismissed', this)">
				Descartados ({{ $ae['dismissed'] ?? 0 }})
			</button>
		</div>

		<!-- Tabla de Casos -->
		<div class="table-responsive">
			<table class="daily-cases-table" id="aeCasesTable" data-export-title="Casos de error administrativo">
				<thead>
					<tr>
						<th>CASO</th>
						<th>CATEGORÍA</th>
						<th>HALLAZGO</th>
						<th>ESPERADO / REAL</th>
						<th class="text-right">IMPACTO</th>
						<th>ESTADO</th>
						<th>PLAZO</th>
						<th>ACCIÓN</th>
					</tr>
				</thead>
				<tbody>
					@forelse(($ae['cases'] ?? []) as $c)
						@php
							$nx = $next($c['status']);
							$closed = in_array($c['status'], ['closed', 'dismissed']);
						@endphp
						<tr class="ae-case-row" data-status="{{ $c['status'] }}" data-closed="{{ $closed ? '1' : '0' }}">
							<td class="font-mono font-weight-700 text-nowrap">
								{{ $c['case_number'] }}
								<div class="small text-muted font-weight-normal">{{ $c['source'] === 'auto' ? 'automático' : 'manual' }}</div>
							</td>
							<td>
								<span class="badge badge-light border font-weight-600">{{ $c['category'] }}</span>
								<div class="small text-muted">{{ $ae['categories'][$c['category']][0] ?? $c['category'] }}</div>
							</td>
							<td>
								<div class="font-weight-600">{{ $c['title'] }}</div>
								<div class="small text-muted">{{ $c['detail'] }}</div>
								<div class="small mt-1">
									@if($c['ref_type'] === 'waste' && $c['ref_id'])
										<a href="{{ $U('/document/waste/' . $c['ref_id']) }}">{{ $c['ref_doc'] }}</a>
									@elseif($c['ref_doc'])
										{{ $c['ref_doc'] }}
									@endif
									@if($c['product_id'])
										· <a href="{{ $U('/kardex?product_id=' . $c['product_id']) }}">{{ $c['product_name'] ?? ('#' . $c['product_id']) }}</a>
									@endif
									@if($c['branch_name'])
										· {{ $c['branch_name'] }}
									@endif
									@if($c['evidence_path'])
										· <a href="{{ $U($c['evidence_path']) }}" target="_blank" rel="noopener">evidencia</a>
									@endif
								</div>
							</td>
							<td class="small">
								{{ $c['expected_value'] }}<br>
								<span class="text-muted">{{ $c['actual_value'] }}</span>
							</td>
							<td class="text-right font-mono font-weight-600">
								{{ $money($c['impact_cost']) }}
							</td>
							<td>
								<span class="badge {{ $tone[$c['status']] ?? 'badge-secondary' }} font-weight-600">
									{{ $ae['statuses'][$c['status']] ?? $c['status'] }}
								</span>
							</td>
							<td class="small font-mono {{ !$closed && $c['due_date'] && $c['due_date'] < date('Y-m-d') ? 'text-danger font-weight-700' : '' }}">
								{{ $c['due_date'] ?? '—' }}
							</td>
							<td class="text-nowrap">
								@if(!$closed && $nx && (!in_array($nx, $needsApprover) || ($canApprove ?? false)))
									<button type="button" class="btn btn-xs btn-success font-weight-600" onclick="dlxAeAdvance({{ $c['id'] }}, '{{ $nx }}', '{{ $ae['statuses'][$nx] }}')">
										{{ $ae['statuses'][$nx] }}
									</button>
								@elseif(!$closed && $nx)
									<span class="small text-muted">Espera aprobador</span>
								@endif
								@if(!$closed && ($canApprove ?? false) && in_array($c['status'], ['detected', 'evidence', 'reconciled']))
									<button type="button" class="btn btn-xs btn-light border" onclick="dlxAeAdvance({{ $c['id'] }}, 'dismissed', 'Descartar')" title="No es un error">
										Descartar
									</button>
								@endif
								<button type="button" class="btn btn-xs btn-light border" onclick="dlxAeHistory({{ $c['id'] }}, '{{ $c['case_number'] }}')" title="Historial">
									<i class="fa-solid fa-clock-rotate-left"></i>
								</button>
							</td>
						</tr>
					@empty
						<tr id="aeEmptyRow">
							<td colspan="8" class="p-0 border-0">
								<div class="daily-empty-cases">
									<i class="fa-regular fa-file-lines"></i>
									<div class="daily-empty-cases-title">No hay casos con los filtros actuales.</div>
									<div class="daily-empty-cases-sub">No confirma ausencia de errores. Ejecuta una auditoría o registra un hallazgo.</div>
								</div>
							</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<!-- ======================================================================
	     7. CATEGORÍAS ADMINISTRATIVAS (11 CATEGORÍAS + EPC/RFID)
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-categories-header">
			<div class="daily-categories-title-group">
				<h6>11 categorías administrativas + EPC/RFID</h6>
				<p>Clasificación según el Censo y mejores prácticas. Selecciona una categoría para ver sus casos.</p>
			</div>
			<div class="daily-toggle-view">
				<button type="button" class="daily-toggle-view-btn active" id="btnViewCards" onclick="toggleCategoriesView('cards')">
					Vista tarjetas
				</button>
				<button type="button" class="daily-toggle-view-btn" id="btnViewTable" onclick="toggleCategoriesView('table')">
					Vista tabla
				</button>
			</div>
		</div>

		<!-- Vista en Cuadrícula de Tarjetas (3 columnas) -->
		<div class="daily-category-cards-grid" id="aeCategoriesCardsGrid">
			@foreach(($ae['stats'] ?? []) as $s)
				<div class="daily-cat-card" title="{{ $s['rule'] }} · Responsable: {{ $s['area'] }}">
					<div class="daily-cat-icon">
						<i class="fa-solid {{ $categoryIcons[$s['code']] ?? 'fa-folder' }}"></i>
					</div>
					<div class="daily-cat-body">
						<div class="daily-cat-top">
							<span class="daily-cat-code">{{ str_replace('DEMO-', 'EJ-', $s['code']) }}</span>
							<span class="daily-cat-name" title="{{ $s['name'] }}">{{ $s['name'] }}</span>
						</div>
						<div class="daily-cat-meta">
							<span>{{ $s['total'] }} casos</span>
							<span>· {{ $s['open'] }} abiertos</span>
							<span>· Frecuencia: {{ $s['freq_pct'] !== null ? $pct($s['freq_pct']) : '—' }}</span>
						</div>
						<a href="{{ $U('/waste') }}?start_date={{ $startDate }}&end_date={{ $endDate }}&branch_id={{ $f['branch_id'] ?? '' }}&ae_status=all&ae_category={{ $s['code'] }}#tab-errores" class="daily-cat-link">
							Ver categoría &rarr;
						</a>
					</div>
				</div>
			@endforeach
		</div>

		<!-- Vista Alternativa en Tabla -->
		<div class="table-responsive mt-2" id="aeCategoriesTableView" style="display: none;">
			<table class="daily-cases-table" data-export-title="Categorías administrativas">
				<thead>
					<tr>
						<th>CÓDIGO</th>
						<th>CATEGORÍA</th>
						<th>ÁREA RESPONSABLE</th>
						<th class="text-right">CASOS</th>
						<th class="text-right">ABIERTOS</th>
						<th class="text-right">VENCIDOS</th>
						<th class="text-right">FRECUENCIA</th>
						<th class="text-right">IMPACTO ESTIMADO</th>
					</tr>
				</thead>
				<tbody>
					@foreach(($ae['stats'] ?? []) as $s)
						<tr>
							<td class="font-mono font-weight-700">{{ str_replace('DEMO-', 'EJ-', $s['code']) }}</td>
							<td class="font-weight-600">{{ $s['name'] }}</td>
							<td class="text-muted small">{{ $s['area'] }}</td>
							<td class="text-right font-mono">{{ $s['total'] }}</td>
							<td class="text-right font-mono">{{ $s['open'] }}</td>
							<td class="text-right font-mono text-danger">{{ $s['overdue'] }}</td>
							<td class="text-right font-mono">{{ $s['freq_pct'] !== null ? $pct($s['freq_pct']) : '—' }}</td>
							<td class="text-right font-mono">{{ $money($s['impact']) }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>

	<!-- ======================================================================
	     8. 3 TARJETAS INFERIORES: FRECUENCIA, REFERENCIAS E IMPACTO
	     ====================================================================== -->
	<div class="daily-bottom-cards-grid">
		<!-- Tarjeta 1: Frecuencia por categoría -->
		<div class="daily-bottom-card">
			<div class="daily-bottom-card-title">
				<i class="fa-solid fa-chart-column text-success"></i>
				<span>Frecuencia por categoría</span>
			</div>
			@if(($ae['total'] ?? 0) > 0)
				<div style="height: 140px; position: relative;">
					<canvas id="aeFreqChart"></canvas>
				</div>
			@else
				<div class="d-flex align-items-center gap-3 my-auto py-2">
					<div class="text-muted" style="font-size: 1.5rem; opacity: 0.5;">
						<i class="fa-solid fa-chart-simple"></i>
					</div>
					<div class="daily-bottom-card-body">
						<div class="font-weight-700 text-dark">Sin datos para elaborar ranking.</div>
						Las frecuencias se calculan sobre el total de casos registrados (0). Frecuencia: —
					</div>
				</div>
			@endif
		</div>

		<!-- Tarjeta 2: Referencias y contexto -->
		<div class="daily-bottom-card">
			<div class="daily-bottom-card-title">
				<i class="fa-solid fa-book-open text-success"></i>
				<span>Referencias y contexto</span>
			</div>
			<div class="daily-bottom-card-body">
				<div class="font-weight-700 text-dark mb-1">Referencia del Censo 2022</div>
				Clasificación de categorías basada en el Censo y en mejores prácticas de la industria. Solo como referencia externa.
			</div>
		</div>

		<!-- Tarjeta 3: Impacto estimado ≠ pérdida confirmada -->
		<div class="daily-bottom-card daily-bottom-card-amber">
			<div class="daily-bottom-card-title">
				<i class="fa-solid fa-triangle-exclamation"></i>
				<span>Impacto estimado ≠ pérdida confirmada</span>
			</div>
			<div class="daily-bottom-card-body">
				El impacto estimado requiere evidencia, conciliación y aprobación antes de su ajuste a la merma.
			</div>
		</div>
	</div>

</div>

<!-- ==========================================================================
     SCRIPTS DE CONTROL Y GESTIÓN DE ERRORES ADMINISTRATIVOS
     ========================================================================== -->
<script>
(function() {
	'use strict';

	var AE_SCOPE = {
		branch_id: {!! json_encode($f['branch_id'] ?? null) !!},
		from: '{{ $startDate }}',
		to: '{{ min($endDate, date('Y-m-d')) }}'
	};
	var AE_CATS = {!! json_encode(array_map(fn($c) => $c[0], $ae['categories'] ?? [])) !!};

	// 1. Alternador de vista Tarjetas / Tabla para categorías
	window.toggleCategoriesView = function(view) {
		if (view === 'table') {
			$('#aeCategoriesCardsGrid').hide();
			$('#aeCategoriesTableView').show();
			$('#btnViewTable').addClass('active');
			$('#btnViewCards').removeClass('active');
		} else {
			$('#aeCategoriesTableView').hide();
			$('#aeCategoriesCardsGrid').show();
			$('#btnViewCards').addClass('active');
			$('#btnViewTable').removeClass('active');
		}
	};

	// 2. Filtro rápido de casos por estado
	window.filterCasesByStatus = function(status, btn) {
		$('.daily-tab-pill-btn').removeClass('active');
		$(btn).addClass('active');

		var rows = $('.ae-case-row');
		if (!rows.length) return;

		var visible = 0;
		rows.each(function() {
			var row = $(this);
			var st = row.data('status');
			var closed = row.data('closed') == 1;

			if (status === 'all') {
				row.show();
				visible++;
			} else if (status === 'open' && !closed) {
				row.show();
				visible++;
			} else if (status === 'closed' && st === 'closed') {
				row.show();
				visible++;
			} else if (status === 'dismissed' && st === 'dismissed') {
				row.show();
				visible++;
			} else {
				row.hide();
			}
		});

		$('#aeEmptyRow').toggle(visible === 0);
	};

	// 3. Buscador en tiempo real de la tabla de casos
	window.filterCasesTable = function(q) {
		q = (q || '').toLowerCase().trim();
		var rows = $('.ae-case-row:visible');
		rows.each(function() {
			var text = $(this).text().toLowerCase();
			$(this).toggle(!q || text.indexOf(q) !== -1);
		});
	};

	// 4. Ejecutar Auditoría Automática
	window.dlxAeDetect = function(btn) {
		if (window.DairyLoss && DairyLoss.post) {
			DairyLoss.post('loss/census/admin-errors/detect', AE_SCOPE, function(r) {
				toastr.success(r.created + ' caso(s) nuevo(s) de ' + r.found + ' hallazgo(s)' + (r.found > r.created ? '; los demás ya estaban registrados' : ''));
				if (window.dairyRefreshActiveTab) {
					dairyRefreshActiveTab();
				} else {
					location.reload();
				}
			}, btn);
		} else {
			toastr.info('Iniciando auditoría de consistencia documental...');
		}
	};

	// 5. Registrar nuevo error administrativo
	window.dlxAeNew = function(prefCat) {
		var cats = Object.keys(AE_CATS).map(function(k) {
			var sel = (prefCat && prefCat === k) ? ' selected' : '';
			return '<option value="' + k + '"' + sel + '>' + k + ' · ' + DairyUI.esc(AE_CATS[k]) + '</option>';
		}).join('');

		var html = '<div class="form-group"><label class="small font-weight-700">Categoría</label><select name="category" class="form-control form-control-sm">' + cats + '</select></div>'
			+ '<div class="form-group"><label class="small font-weight-700">Error encontrado</label><input type="text" name="title" class="form-control form-control-sm" required minlength="5" placeholder="Descripción breve del hallazgo"></div>'
			+ '<div class="form-group"><label class="small font-weight-700">Detalle observado</label><textarea name="detail" class="form-control form-control-sm" rows="2" placeholder="Hechos concretos observados"></textarea></div>'
			+ '<div class="form-row">'
			+ '<div class="form-group col-6"><label class="small font-weight-700">Valor esperado</label><input type="text" name="expected_value" class="form-control form-control-sm" placeholder="Ej: 100 unidades / $50.00"></div>'
			+ '<div class="form-group col-6"><label class="small font-weight-700">Valor real</label><input type="text" name="actual_value" class="form-control form-control-sm" placeholder="Ej: 95 unidades / $47.50"></div>'
			+ '</div>'
			+ '<div class="form-row">'
			+ '<div class="form-group col-6"><label class="small font-weight-700">Documento / Remisión</label><input type="text" name="ref_doc" class="form-control form-control-sm" placeholder="Ej: FAC-98765"></div>'
			+ '<div class="form-group col-6"><label class="small font-weight-700">Impacto estimado ($)</label><input type="number" step="0.01" min="0" name="impact_cost" class="form-control form-control-sm" placeholder="0.00"></div>'
			+ '</div>'
			+ (AE_SCOPE.branch_id ? '<input type="hidden" name="branch_id" value="' + AE_SCOPE.branch_id + '">' : '');

		if (window.DairyLoss && DairyLoss.dialog) {
			DairyLoss.dialog('Registrar error administrativo', html, 'loss/census/admin-errors', 'Caso registrado');
		} else if (window.bootbox) {
			bootbox.dialog({
				title: 'Registrar error administrativo',
				message: '<form id="aeForm">' + html + '</form>',
				buttons: {
					cancel: { label: 'Cancelar', className: 'btn-secondary btn-sm' },
					ok: {
						label: 'Registrar',
						className: 'btn-success btn-sm',
						callback: function() {
							toastr.success('Caso registrado');
						}
					}
				}
			});
		}
	};

	// 6. Avanzar paso del flujo
	window.dlxAeAdvance = function(id, to, label) {
		var help = {
			evidence: 'Adjunte foto o documento, o describa la evidencia física.',
			reconciled: 'Indique qué se comparó (documento, sistema, físico) y qué resultó.',
			approved: 'Aprobación de la corrección (segregación: no puede ser quien concilió).',
			corrected: 'Corrección aplicada: documento, ajuste o cambio en el inventario.',
			verified: 'Verificación independiente de que la corrección quedó aplicada.',
			closed: 'Resolución final del caso con auditoría conforme.',
			dismissed: 'Justifique por qué no constituye error (mínimo 10 caracteres).'
		};

		var html = '<p class="small text-muted">' + (help[to] || '') + '</p>'
			+ (to === 'evidence' ? '<div class="form-group"><label class="small font-weight-700">Foto o documento</label><input type="file" class="form-control-file" accept="image/*" id="ae-ev-file"></div>' : '')
			+ '<div class="form-group"><label class="small font-weight-700">Notas de gestión</label><textarea name="notes" class="form-control form-control-sm" rows="3" required></textarea></div>'
			+ (to === 'reconciled' ? '<div class="form-group"><label class="small font-weight-700">Impacto conciliado ($, opcional)</label><input type="number" step="0.01" min="0" name="impact_cost" class="form-control form-control-sm"></div>' : '');

		DairyUI.formDialog(label, html, function(vals, dlg) {
			var file = dlg.find('#ae-ev-file')[0];
			var send = function() {
				vals.to = to;
				DairyLoss.post('loss/census/admin-errors/' + id + '/advance', vals, function() {
					DairyLoss.closeDialog(dlg);
					toastr.success('Paso registrado: ' + label);
					if (window.dairyRefreshActiveTab) dairyRefreshActiveTab();
				});
			};
			if (file && file.files[0]) {
				var fd = new FormData();
				fd.append('photo', file.files[0]);
				fetch(Grocy.BaseUrl + '/api/waste/upload-photo', { method: 'POST', credentials: 'same-origin', body: fd })
					.then(function(r) { return r.json().then(function(j) { if (!r.ok) throw new Error(j.error_message || 'Error al subir'); return j; }); })
					.then(function(j) { vals.evidence_path = j.relative_url; send(); })
					.catch(function(e) { toastr.error(e.message); });
			} else {
				send();
			}
		}, 'Registrar');
	};

	// 7. Historial de transiciones
	window.dlxAeHistory = function(id, num) {
		$.getJSON(Grocy.BaseUrl + '/api/loss/census/admin-errors/' + id + '/history').done(function(r) {
			var st = {!! json_encode($ae['statuses'] ?? []) !!};
			var rows = (r.history || []).map(function(x) {
				return '<tr><td class="small font-mono">' + DairyUI.esc(x.created_timestamp) + '</td><td><span class="badge badge-light border">' + DairyUI.esc(st[x.to_status] || x.to_status) + '</span></td><td class="small">' + DairyUI.esc(x.user_name || x.user || '') + '</td><td class="small text-muted">' + DairyUI.esc(x.notes || '') + '</td></tr>';
			}).join('');
			bootbox.alert({
				title: '<i class="fa-solid fa-clock-rotate-left mr-2"></i>Historial de caso ' + DairyUI.esc(num),
				message: '<div class="table-responsive"><table class="table table-sm daily-cases-table"><thead><tr><th>Fecha</th><th>Estado</th><th>Usuario</th><th>Notas</th></tr></thead><tbody>' + (rows || '<tr><td colspan="4" class="text-muted">Sin movimientos registrados.</td></tr>') + '</tbody></table></div>'
			});
		}).fail(function(x) {
			toastr.error(DairyUI.errMsg ? DairyUI.errMsg(x) : 'No se pudo cargar el historial');
		});
	};

})();
</script>
