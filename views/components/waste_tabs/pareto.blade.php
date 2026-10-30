@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$pctFmt = fn($v) => ($v !== null && $v !== '') ? number_format(floatval($v), 1) . '%' : '—';
	
	$causes = $matrix['causes'] ?? [];
	$posCauses = array_values(array_filter($causes, fn($c) => floatval($c['cost']) > 0));
	$total = floatval($matrix['total'] ?? 0);
	
	// Causal de mayor impacto
	$topCausal = !empty($posCauses) ? $posCauses[0] : null;
	
	// Cálculo dinámico de concentración Pareto (cuántas causales acumulan ~80%)
	$vitalCount = 0;
	$vitalCumPct = 0;
	foreach ($posCauses as $c) {
		$vitalCount++;
		$vitalCumPct = floatval($c['cum_pct'] ?? 0);
		if ($vitalCumPct >= 80.0) {
			break;
		}
	}
	
	// Top 3 causales para tarjetas ilustrativas con foto
	$top1 = $posCauses[0] ?? null;
	$top2 = $posCauses[1] ?? null;
	$top3 = $posCauses[2] ?? null;
	
	// Top 5 para ranking horizontal
	$top5 = array_slice($posCauses, 0, 5);
	$maxTop5Cost = !empty($top5) ? max(array_column($top5, 'cost')) : 1;
	
	// Catálogo estándar de causales (36 causales del catálogo real)
	$stdReasons = $standardReasons ?? $reasons ?? [];
	
	// Motivos de registro mapeados
	$mappedReasons = $matrix['reasons'] ?? [];
	
	// Mapeo amigable de clases a nombres y estilos legibles
	$classMeta = [
		'operativa' => [
			'label' => 'Merma operativa',
			'badge_style' => 'background: #fee2e2; color: #dc2626; border: 1px solid #fecaca;',
			'color' => '#ef4444'
		],
		'comercial' => [
			'label' => 'Merma comercial',
			'badge_style' => 'background: #fef3c7; color: #d97706; border: 1px solid #fde68a;',
			'color' => '#f59e0b'
		],
		'averia' => [
			'label' => 'Averías y roturas',
			'badge_style' => 'background: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff;',
			'color' => '#8b5cf6'
		],
		'ajuste' => [
			'label' => 'Ajustes de inventario',
			'badge_style' => 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;',
			'color' => '#64748b'
		],
		'investigacion' => [
			'label' => 'Pendiente de investigación',
			'badge_style' => 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a;',
			'color' => '#d97706'
		],
		'robo_interno' => [
			'label' => 'Robo interno',
			'badge_style' => 'background: #f8fafc; color: #1e293b; border: 1px solid #cbd5e1;',
			'color' => '#334155'
		],
		'robo_externo' => [
			'label' => 'Robo externo',
			'badge_style' => 'background: #f8fafc; color: #1e293b; border: 1px solid #cbd5e1;',
			'color' => '#334155'
		],
	];

	// Paleta de degradado cálido para barras de causales
	$warmBarColors = [
		'#ef4444', '#f87171', '#fb923c', '#f97316', '#ea580c',
		'#eab308', '#ca8a04', '#d97706', '#b45309', '#a16207',
		'#78716c', '#57534e', '#44403c'
	];
@endphp

<!-- ESTILOS ESPECÍFICOS DE LA PESTAÑA CAUSALES (DAILY LOOK & FEEL) -->
<style>
/* Contenedor general */
.daily-causales-tab {
	display: flex;
	flex-direction: column;
	gap: 20px;
	color: #0f172a;
	font-family: inherit;
}

/* Tarjeta base */
.daily-cc-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 20px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
	position: relative;
	transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.daily-cc-card:hover {
	border-color: #cbd5e1;
}

/* Header de la pestaña */
.daily-causales-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	flex-wrap: wrap;
	gap: 12px;
}
.daily-causales-title {
	font-size: 1.15rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 3px 0;
	letter-spacing: -0.015em;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-causales-subtitle {
	font-size: 0.84rem;
	color: #64748b;
	margin: 0;
	font-weight: 400;
}

/* Franja de Indicadores de Impacto */
.daily-impact-strip {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
}
@media (max-width: 900px) {
	.daily-impact-strip {
		grid-template-columns: 1fr;
	}
}
.daily-impact-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 18px;
	display: flex;
	align-items: center;
	gap: 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.daily-impact-card:hover {
	transform: translateY(-1.5px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
	border-color: #cbd5e1;
}
.daily-impact-icon {
	width: 44px;
	height: 44px;
	border-radius: 10px;
	background: #dcfce7;
	color: #16a34a;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.2rem;
	flex-shrink: 0;
}
.daily-impact-body {
	flex: 1;
	min-width: 0;
}
.daily-impact-label {
	font-size: 0.76rem;
	font-weight: 600;
	color: #64748b;
	margin-bottom: 2px;
}
.daily-impact-val {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
}
.daily-impact-sub {
	font-size: 0.74rem;
	color: #64748b;
	margin-top: 1px;
}

/* Tarjetas Top 3 con fotografía y degradado a blanco */
.daily-top3-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
}
@media (max-width: 992px) {
	.daily-top3-grid {
		grid-template-columns: 1fr;
	}
}
.daily-top3-card {
	position: relative;
	height: 148px;
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
	display: flex;
	cursor: pointer;
	transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.daily-top3-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
	border-color: #cbd5e1;
}
.daily-top3-card.active-selected {
	border-color: var(--daily-primary, #00C83B);
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.25);
}
.daily-top3-info {
	position: relative;
	z-index: 3;
	width: 65%;
	height: 100%;
	padding: 14px 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-top3-tag {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.daily-top3-code-badge {
	font-size: 0.68rem;
	font-weight: 800;
	padding: 2.5px 7px;
	border-radius: 6px;
	text-transform: uppercase;
	font-family: var(--font-mono, monospace);
	letter-spacing: 0.04em;
}
.daily-top3-code-badge.tone-red { background: #ef4444; color: #ffffff; }
.daily-top3-code-badge.tone-amber { background: #fef08a; color: #854d0e; border: 1px solid #fde047; }
.daily-top3-name {
	font-size: 0.88rem;
	font-weight: 700;
	color: #1e293b;
	line-height: 1.25;
	margin-top: 4px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-top3-metrics {
	display: flex;
	align-items: baseline;
	gap: 8px;
	margin-top: 2px;
}
.daily-top3-cost {
	font-size: 1.4rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1;
}
.daily-top3-pct {
	font-size: 0.88rem;
	font-weight: 700;
	color: #64748b;
}
.daily-top3-footer {
	font-size: 0.74rem;
	color: #94a3b8;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-top3-photo {
	position: absolute;
	top: 0;
	right: 0;
	width: 55%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
	overflow: hidden;
}
.daily-top3-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center right;
	-webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.15) 15%, rgba(0,0,0,0.85) 60%, black 100%);
	mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.15) 15%, rgba(0,0,0,0.85) 60%, black 100%);
	transition: transform 0.3s ease;
}
.daily-top3-card:hover .daily-top3-photo img {
	transform: scale(1.04);
}

/* Layout Pareto + Top 5 Ranking */
.daily-charts-row {
	display: grid;
	grid-template-columns: 1.6fr 1fr;
	gap: 16px;
}
@media (max-width: 992px) {
	.daily-charts-row {
		grid-template-columns: 1fr;
	}
}
.daily-chart-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12px;
	flex-wrap: wrap;
	gap: 8px;
}
.daily-chart-title {
	font-size: 0.92rem;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 7px;
}
.daily-chart-legend {
	display: flex;
	align-items: center;
	gap: 12px;
	font-size: 0.72rem;
	color: #64748b;
	font-weight: 600;
}
.daily-chart-legend-item {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}
.daily-legend-box {
	width: 10px;
	height: 10px;
	border-radius: 2px;
}
.daily-legend-line {
	width: 14px;
	height: 2.5px;
	border-radius: 2px;
	background: #1e293b;
	position: relative;
}
.daily-legend-line::after {
	content: '';
	position: absolute;
	top: -2px;
	left: 4px;
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: #1e293b;
}
.daily-legend-dash {
	width: 16px;
	height: 0;
	border-top: 2px dashed #94a3b8;
}

/* Ranking Top 5 Horizontal Rows (3 columns: Name | Bar | Values) */
.daily-ranking-list {
	display: flex;
	flex-direction: column;
	gap: 14px;
	padding-top: 8px;
}
.daily-ranking-row {
	display: grid;
	grid-template-columns: 180px 1fr 100px;
	align-items: center;
	gap: 12px;
	cursor: pointer;
	padding: 4px 6px;
	border-radius: 8px;
	transition: background 0.15s ease;
}
@media (max-width: 1200px) {
	.daily-ranking-row {
		grid-template-columns: 150px 1fr 90px;
	}
}
@media (max-width: 576px) {
	.daily-ranking-row {
		grid-template-columns: 120px 1fr 80px;
	}
}
.daily-ranking-row:hover {
	background: #f8fafc;
}
.daily-ranking-row.active-selected {
	background: #f1f5f9;
}
.daily-ranking-name {
	font-size: 0.77rem;
	font-weight: 600;
	color: #1e293b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-ranking-track {
	width: 100%;
	height: 14px;
	background: #f1f5f9;
	border-radius: 3px;
	overflow: hidden;
	position: relative;
}
.daily-ranking-fill {
	height: 100%;
	border-radius: 3px;
	transition: width 0.4s ease;
}
.daily-ranking-vals {
	font-size: 0.78rem;
	font-weight: 700;
	color: #0f172a;
	text-align: right;
	white-space: nowrap;
}
.daily-ranking-pct {
	color: #64748b;
	font-weight: 500;
	margin-left: 3px;
	font-size: 0.72rem;
}

/* Switcher de Vistas tipo Píldoras */
.daily-tab-pills {
	display: inline-flex;
	background: #f1f5f9;
	padding: 3px;
	border-radius: 8px;
	gap: 3px;
}
.daily-pill-btn {
	border: none;
	background: transparent;
	padding: 5px 14px;
	border-radius: 6px;
	font-size: 0.78rem;
	font-weight: 600;
	color: #64748b;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-pill-btn:hover {
	color: #0f172a;
}
.daily-pill-btn.active {
	background: var(--daily-primary, #00C83B);
	color: #ffffff;
	box-shadow: 0 1px 3px rgba(0, 200, 59, 0.3);
}

/* Barra de Filtros / Búsqueda para Tablas */
.daily-table-toolbar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 10px;
	margin-bottom: 12px;
}
.daily-search-box {
	position: relative;
	min-width: 240px;
}
.daily-search-box input {
	padding-left: 32px;
	font-size: 0.8rem;
	border-radius: 7px;
	border-color: #cbd5e1;
	height: 32px;
}
.daily-search-box .search-icon {
	position: absolute;
	left: 10px;
	top: 8px;
	color: #94a3b8;
	font-size: 0.8rem;
	pointer-events: none;
}

/* Estilos de Tabla Analítica */
.daily-table-container {
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	overflow: hidden;
}
.daily-analytics-table {
	margin-bottom: 0;
	font-size: 0.81rem;
	width: 100%;
}
.daily-analytics-table th {
	background: #f8fafc;
	color: #475569;
	font-size: 0.72rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 10px 12px;
	border-bottom: 1px solid #e2e8f0;
	vertical-align: middle;
	white-space: nowrap;
}
.daily-analytics-table td {
	padding: 10px 12px;
	vertical-align: middle;
	border-bottom: 1px solid #f1f5f9;
}
.daily-analytics-table tbody tr {
	cursor: pointer;
	transition: background 0.12s ease;
}
.daily-analytics-table tbody tr:hover {
	background: #f8fafc;
}
.daily-analytics-table tbody tr.row-selected {
	background: #ecfdf5 !important;
}

/* Badges de clase */
.daily-class-badge {
	display: inline-block;
	padding: 2.5px 8px;
	border-radius: 999px;
	font-size: 0.71rem;
	font-weight: 600;
	white-space: nowrap;
}

/* Paginación */
.daily-table-pagination {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 4px 0 4px;
	font-size: 0.77rem;
	color: #64748b;
	flex-wrap: wrap;
	gap: 8px;
}
.daily-page-btns {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
.daily-page-btn {
	min-width: 28px;
	height: 28px;
	padding: 0 7px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: 6px;
	border: 1px solid #e2e8f0;
	background: #ffffff;
	font-size: 0.76rem;
	font-weight: 600;
	color: #334155;
	cursor: pointer;
	transition: all 0.15s ease;
}
.daily-page-btn:hover:not(:disabled) {
	background: #f8fafc;
	border-color: #cbd5e1;
	color: #0f172a;
}
.daily-page-btn.active {
	background: #0f172a;
	border-color: #0f172a;
	color: #ffffff;
}
.daily-page-btn:disabled {
	opacity: 0.4;
	cursor: not-allowed;
}

/* Tarjeta Detalle de Causal Seleccionada (Responsable, Aprobación, Tratamiento) */
.daily-causal-detail-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 16px 18px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 16px;
}
.daily-cdd-left {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 220px;
}
.daily-cdd-badge {
	font-size: 0.85rem;
	font-weight: 800;
	padding: 6px 10px;
	border-radius: 8px;
	background: #ef4444;
	color: #ffffff;
	font-family: var(--font-mono, monospace);
}
.daily-cdd-name {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin-bottom: 2px;
}
.daily-cdd-vals {
	font-size: 0.82rem;
	font-weight: 700;
	color: #334155;
}
.daily-cdd-events {
	font-size: 0.74rem;
	color: #64748b;
	font-weight: 500;
}
.daily-cdd-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16px;
	flex: 1;
	min-width: 320px;
}
@media (max-width: 850px) {
	.daily-cdd-grid {
		grid-template-columns: 1fr;
	}
}
.daily-cdd-col {
	display: flex;
	flex-direction: column;
	gap: 2px;
}
.daily-cdd-label {
	font-size: 0.71rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	display: flex;
	align-items: center;
	gap: 5px;
}
.daily-cdd-desc {
	font-size: 0.79rem;
	color: #1e293b;
	font-weight: 500;
	line-height: 1.3;
}

/* Callout INV Pendiente de investigación */
.daily-inv-callout {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 10px;
	font-size: 0.8rem;
	color: #92400e;
}
.daily-inv-badge {
	background: #f59e0b;
	color: #ffffff;
	font-weight: 800;
	font-size: 0.68rem;
	padding: 2px 6px;
	border-radius: 4px;
	font-family: var(--font-mono, monospace);
}

/* Acordeón de Motivos de registro */
.daily-reasons-details {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 16px;
}
.daily-reasons-details summary {
	font-size: 0.84rem;
	font-weight: 700;
	color: #1e293b;
	cursor: pointer;
	user-select: none;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-reasons-details summary::-webkit-details-marker {
	display: none;
}
.daily-reasons-details[open] summary {
	margin-bottom: 12px;
}

/* Barra de contexto y sincronización de filtros */
.daily-context-strip {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 8px 14px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 8px;
	font-size: 0.76rem;
}
.daily-context-badges {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.daily-ctx-pill {
	background: #ffffff;
	border: 1px solid #cbd5e1;
	border-radius: 6px;
	padding: 2px 8px;
	color: #334155;
	font-weight: 600;
	display: inline-flex;
	align-items: center;
	gap: 4px;
}
</style>

<div class="daily-causales-tab" id="dailyCausalesRoot">

	<!-- ======================================================================
	     SECCIÓN A: ENCABEZADO Y FILTROS
	     ====================================================================== -->
	<div class="daily-causales-header">
		<div>
			<h4 class="daily-causales-title">
				Causales de merma: distribución y Pareto
			</h4>
			<p class="daily-causales-subtitle">
				Identifica las causas de mayor impacto y consulta sus responsables, reglas de aprobación y tratamiento.
			</p>
		</div>
		<div class="d-flex align-items-center gap-2">
			<!-- Exportar dropdown -->
			<div class="btn-group">
				<button type="button" class="btn btn-light bg-white border font-weight-600 shadow-xs dropdown-toggle" 
						data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
						style="font-size: 0.8rem; padding: 6px 12px; border-radius: 7px; border-color: #cbd5e1;">
					<i class="fa-solid fa-download text-muted mr-1.5"></i>Exportar
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 font-weight-500" style="font-size: 0.82rem; border-radius: 8px;">
					<a class="dropdown-item" href="javascript:void(0)" onclick="DairyUI.exportTables('csv', '#dailyCausalesTable', 'causales_merma');">
						<i class="fa-solid fa-file-excel text-success mr-2"></i>Exportar Causales (Excel/CSV)
					</a>
					<a class="dropdown-item" href="javascript:void(0)" onclick="DairyUI.exportTables('csv', '#dailyStandardMatrixTable', 'matriz_estandar_causales');">
						<i class="fa-solid fa-table text-primary mr-2"></i>Exportar Matriz Estándar (CSV)
					</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="javascript:void(0)" onclick="window.print();">
						<i class="fa-solid fa-print text-muted mr-2"></i>Imprimir / PDF
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Barra de Contexto Activo y Filtros Sincronizados -->
	<div class="daily-context-strip">
		<div class="daily-context-badges">
			<span class="text-muted font-weight-600 mr-1"><i class="fa-solid fa-sliders mr-1"></i>Contexto activo:</span>
			<span class="daily-ctx-pill" title="Rango de fechas">
				<i class="fa-regular fa-calendar text-muted"></i>{{ $startDate }} a {{ $endDate }}
			</span>
			<span class="daily-ctx-pill" title="Sucursal">
				<i class="fa-solid fa-store text-muted"></i>
				@php
					$bName = 'Todas las sucursales';
					if (!empty($f['branch_id']) && !empty($lookups['branches'])) {
						foreach ($lookups['branches'] as $lb) {
							if ($lb['id'] == $f['branch_id']) { $bName = $lb['name']; break; }
						}
					}
				@endphp
				{{ $bName }}
			</span>
			<span class="daily-ctx-pill" title="Departamento">
				<i class="fa-solid fa-layer-group text-muted"></i>Fruver y Carnes (Perecederos)
			</span>
			@if(!empty($f['loss_class']))
			<span class="daily-ctx-pill" style="border-color: #f59e0b; background: #fef3c7; color: #b45309;">
				Clase: {{ $classMeta[$f['loss_class']]['label'] ?? $f['loss_class'] }}
				<a href="javascript:void(0)" onclick="$('#dailyWasteFilterForm').find('[name=loss_class]').val(''); window.location.href = window.location.pathname + '?' + 'start_date={{ $startDate }}&end_date={{ $endDate }}#tab-causales';" class="ml-1 text-danger font-weight-700" title="Quitar filtro de clase">×</a>
			</span>
			@endif
			@if(!empty($f['cause']))
			<span class="daily-ctx-pill" style="border-color: #ef4444; background: #fee2e2; color: #b91c1c;">
				Causal: {{ $f['cause'] }}
				<a href="javascript:void(0)" onclick="dailyClearCausalFilter();" class="ml-1 text-danger font-weight-700" title="Quitar filtro de causal">×</a>
			</span>
			@endif
		</div>
		<div class="d-flex align-items-center gap-2">
			<button class="btn btn-sm btn-light border py-1 px-2 text-muted" type="button" onclick="DairyLoss.toggleSheet('#dailyLossFiltersWrapper');" style="font-size: 0.74rem; border-radius: 6px;">
				<i class="fa-solid fa-filter mr-1"></i>Filtros detallados
			</button>
			@if(!empty($f['branch_id']) || !empty($f['loss_class']) || !empty($f['cause']) || !empty($f['product_id']) || !empty($f['supplier_id']))
			<a href="{{ $U('/waste?start_date=' . $startDate . '&end_date=' . $endDate . '#tab-causales') }}" class="btn btn-sm btn-light border py-1 px-2 text-danger" style="font-size: 0.74rem; border-radius: 6px;" title="Limpiar filtros secundarios">
				<i class="fa-solid fa-rotate-left mr-1"></i>Limpiar
			</a>
			@endif
		</div>
	</div>

	<!-- Filtros avanzados desplegables (sincronizados con el formulario general) -->
	<div id="dailyLossFiltersWrapper" style="display: none;">
		@include('components.waste_tabs._loss_filters')
	</div>

	<!-- ======================================================================
	     SECCIÓN B: RESUMEN DEL IMPACTO
	     ====================================================================== -->
	<div class="daily-impact-strip">
		<!-- 1. Total del período -->
		<div class="daily-impact-card">
			<div class="daily-impact-icon">
				<i class="fa-solid fa-money-bill-wave"></i>
			</div>
			<div class="daily-impact-body">
				<div class="daily-impact-label">Total de merma (período)</div>
				<div class="daily-impact-val">{{ $money($total) }}</div>
				<div class="daily-impact-sub">Saldo neto conciliado en el período</div>
			</div>
		</div>

		<!-- 2. Causal de mayor impacto -->
		<div class="daily-impact-card">
			<div class="daily-impact-icon">
				<i class="fa-solid fa-file-lines"></i>
			</div>
			<div class="daily-impact-body">
				<div class="daily-impact-label">Principal causal</div>
				<div class="d-flex align-items-baseline justify-content-between flex-wrap">
					<div class="daily-impact-val" style="font-size: 1.05rem;" title="{{ $topCausal['name'] ?? '—' }}">
						{{ $topCausal ? $topCausal['name'] . ' (' . $topCausal['code'] . ')' : 'Sin registros' }}
					</div>
					<div class="font-weight-800 text-dark" style="font-size: 1.05rem;">
						{{ $topCausal ? $money($topCausal['cost']) : '$0.00' }}
						<span class="text-muted font-weight-600 ml-1" style="font-size: 0.8rem;">{{ $topCausal ? $pctFmt($topCausal['pct']) : '0%' }}</span>
					</div>
				</div>
				<div class="daily-impact-sub">{{ $topCausal ? $topCausal['events'] . ' eventos registrados' : 'No hay pérdidas' }}</div>
			</div>
		</div>

		<!-- 3. Concentración Pareto -->
		<div class="daily-impact-card">
			<div class="daily-impact-icon">
				<i class="fa-solid fa-chart-column"></i>
			</div>
			<div class="daily-impact-body">
				<div class="daily-impact-label">Concentración top {{ $vitalCount }}</div>
				<div class="daily-impact-val" style="color: #0f172a;">
					{{ $pctFmt($vitalCumPct) }}
				</div>
				<div class="daily-impact-sub">de la merma total (umbral del Pareto)</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN C: TRES CAUSALES CON MAYOR COSTO (TARJETAS CON FOTO)
	     ====================================================================== -->
	<div>
		<div class="d-flex align-items-center justify-content-between mb-2">
			<h6 class="font-weight-800 text-dark mb-0" style="font-size: 0.88rem; letter-spacing: -0.01em;">
				Top 3 causales por impacto
			</h6>
			<span class="small text-muted font-weight-500">Haz clic en una tarjeta para consultar su detalle</span>
		</div>
		<div class="daily-top3-grid">
			<!-- Tarjeta 1: CFR / Top 1 -->
			@if($top1)
			<div class="daily-top3-card" id="topCard-{{ $top1['code'] }}" onclick="dailySelectCausal('{{ $top1['code'] }}');">
				<div class="daily-top3-info">
					<div>
						<div class="daily-top3-tag">
							<span class="daily-top3-code-badge tone-red">{{ str_replace('DEMO-', 'EJ-', $top1['code']) }}</span>
						</div>
						<div class="daily-top3-name" title="{{ $top1['name'] }}">{{ $top1['name'] }}</div>
					</div>
					<div>
						<div class="daily-top3-metrics">
							<span class="daily-top3-cost">{{ $money($top1['cost']) }}</span>
							<span class="daily-top3-pct">{{ $pctFmt($top1['pct']) }}</span>
						</div>
						<div class="daily-top3-footer">
							<i class="fa-regular fa-clock text-muted"></i>{{ $top1['events'] }} eventos
						</div>
					</div>
				</div>
				<div class="daily-top3-photo">
					<img src="{{ $U('/img/control_center/pechuga_pollo.jpg') }}" alt="{{ $top1['name'] }}" loading="lazy" onerror="this.src='{{ $U('/img/perecederos/pollo_natural.jpg') }}';">
				</div>
			</div>
			@endif

			<!-- Tarjeta 2: EXP / Top 2 -->
			@if($top2)
			<div class="daily-top3-card" id="topCard-{{ $top2['code'] }}" onclick="dailySelectCausal('{{ $top2['code'] }}');">
				<div class="daily-top3-info">
					<div>
						<div class="daily-top3-tag">
							<span class="daily-top3-code-badge tone-amber">{{ str_replace('DEMO-', 'EJ-', $top2['code']) }}</span>
						</div>
						<div class="daily-top3-name" title="{{ $top2['name'] }}">{{ $top2['name'] }}</div>
					</div>
					<div>
						<div class="daily-top3-metrics">
							<span class="daily-top3-cost">{{ $money($top2['cost']) }}</span>
							<span class="daily-top3-pct">{{ $pctFmt($top2['pct']) }}</span>
						</div>
						<div class="daily-top3-footer">
							<i class="fa-regular fa-clock text-muted"></i>{{ $top2['events'] }} eventos
						</div>
					</div>
				</div>
				<div class="daily-top3-photo">
					<img src="{{ $U('/img/perecederos/fruver_natural.jpg') }}" alt="{{ $top2['name'] }}" loading="lazy" onerror="this.src='{{ $U('/img/perecederos/fruver_evitable.jpg') }}';">
				</div>
			</div>
			@endif

			<!-- Tarjeta 3: ROT / Top 3 -->
			@if($top3)
			<div class="daily-top3-card" id="topCard-{{ $top3['code'] }}" onclick="dailySelectCausal('{{ $top3['code'] }}');">
				<div class="daily-top3-info">
					<div>
						<div class="daily-top3-tag">
							<span class="daily-top3-code-badge tone-red">{{ str_replace('DEMO-', 'EJ-', $top3['code']) }}</span>
						</div>
						<div class="daily-top3-name" title="{{ $top3['name'] }}">{{ $top3['name'] }}</div>
					</div>
					<div>
						<div class="daily-top3-metrics">
							<span class="daily-top3-cost">{{ $money($top3['cost']) }}</span>
							<span class="daily-top3-pct">{{ $pctFmt($top3['pct']) }}</span>
						</div>
						<div class="daily-top3-footer">
							<i class="fa-regular fa-clock text-muted"></i>{{ $top3['events'] }} eventos
						</div>
					</div>
				</div>
				<div class="daily-top3-photo">
					<img src="{{ $U('/img/control_center/tomate.jpg') }}" alt="{{ $top3['name'] }}" loading="lazy" onerror="this.src='{{ $U('/img/control_center/operativa_banner.jpg') }}';">
				</div>
			</div>
			@endif
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN D: PARETO Y RANKING DE CAUSALES
	     ====================================================================== -->
	<div class="daily-charts-row">
		<!-- Gráfico de Pareto (Izquierda) -->
		<div class="daily-cc-card">
			<div class="daily-chart-header">
				<h5 class="daily-chart-title">
					<i class="fa-solid fa-chart-line text-success"></i>Distribución por causal y Pareto
				</h5>
				<div class="daily-chart-legend">
					<span class="daily-chart-legend-item">
						<span class="daily-legend-box" style="background: #ef4444;"></span>Costo de merma (US$)
					</span>
					<span class="daily-chart-legend-item">
						<span class="daily-legend-line"></span>% Acumulado
					</span>
					<span class="daily-chart-legend-item">
						<span class="daily-legend-dash"></span>Umbral 80%
					</span>
				</div>
			</div>
			<div style="position: relative; height: 260px; width: 100%;">
				<canvas id="dailyParetoCanvas" style="height: 260px; max-height: 260px;"></canvas>
			</div>
		</div>

		<!-- Ranking Top 5 (Derecha) -->
		<div class="daily-cc-card">
			<div class="daily-chart-header">
				<h5 class="daily-chart-title">
					<i class="fa-solid fa-chart-column text-success"></i>Top 5 causales por costo
				</h5>
				@if(!empty($f['cause']))
				<a href="javascript:void(0)" onclick="dailyClearCausalFilter();" class="small text-danger font-weight-600">
					Limpiar filtro
				</a>
				@endif
			</div>
			<div class="daily-ranking-list">
				@foreach($top5 as $idx => $r5)
				@php
					$barPct = ($maxTop5Cost > 0) ? round(($r5['cost'] / $maxTop5Cost) * 100, 1) : 0;
					$color = $warmBarColors[$idx] ?? '#ef4444';
				@endphp
				<div class="daily-ranking-row" id="rankingRow-{{ $r5['code'] }}" onclick="dailySelectCausal('{{ $r5['code'] }}');" title="Ver detalle de {{ $r5['name'] }}">
					<span class="daily-ranking-name" title="{{ $r5['name'] }} ({{ $r5['code'] }})">
						{{ $r5['name'] }} ({{ $r5['code'] }})
					</span>
					<div class="daily-ranking-track">
						<div class="daily-ranking-fill" style="width: {{ $barPct }}%; background: {{ $color }};"></div>
					</div>
					<div class="daily-ranking-vals">
						{{ $money($r5['cost']) }}
						<span class="daily-ranking-pct">{{ $pctFmt($r5['pct']) }}</span>
					</div>
				</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN E: TABLA ANALÍTICA
	     ====================================================================== -->
	<div class="daily-cc-card">
		<!-- Switcher de vistas + Filtros rápidos -->
		<div class="daily-table-toolbar">
			<div class="daily-tab-pills" role="tablist">
				<button type="button" class="daily-pill-btn active" data-tab-view="impact" onclick="dailySwitchTableTab('impact');">
					Impacto por causal
				</button>
				<button type="button" class="daily-pill-btn" data-tab-view="treatment" onclick="dailySwitchTableTab('treatment');">
					Responsables y tratamiento
				</button>
				<button type="button" class="daily-pill-btn" data-tab-view="catalog" onclick="dailySwitchTableTab('catalog');">
					Catálogo estándar
				</button>
			</div>

			<div class="d-flex align-items-center gap-2">
				<!-- Filtro por clase -->
				<select id="dailyTableClassFilter" class="form-control form-control-sm" style="font-size: 0.78rem; border-radius: 7px; height: 32px;" onchange="dailyFilterTable();">
					<option value="">Todas las clases</option>
					<option value="operativa">Merma operativa</option>
					<option value="comercial">Merma comercial</option>
					<option value="averia">Averías y roturas</option>
					<option value="ajuste">Ajustes de inventario</option>
				</select>

				<!-- Buscador rápido en tabla -->
				<div class="daily-search-box">
					<i class="fa-solid fa-magnifying-glass search-icon"></i>
					<input type="text" id="dailyTableSearch" class="form-control" placeholder="Buscar código o causal..." onkeyup="dailyFilterTable();">
				</div>
			</div>
		</div>

		<!-- Tabla de Causales -->
		<div class="table-responsive daily-table-container">
			<table class="table daily-analytics-table" id="dailyCausalesTable">
				<thead>
					<tr>
						<th style="width: 75px;">CÓDIGO</th>
						<th>CAUSAL</th>
						<th>CLASE</th>
						<th class="col-resp">RESPONSABLE</th>
						<th class="text-right" style="width: 80px;">EVENTOS</th>
						<th class="text-right" style="width: 105px;">COSTO (US$)</th>
						<th class="text-right" style="width: 95px;">PARTICIPACIÓN</th>
						<th class="text-right" style="width: 95px;">ACUMULADO</th>
						<th class="text-center" style="width: 60px;">DETALLE</th>
					</tr>
				</thead>
				<tbody id="dailyCausalesTbody">
					@foreach($causes as $c)
					@php
						$clKey = $c['loss_class'] ?? 'operativa';
						$meta = $classMeta[$clKey] ?? $classMeta['operativa'];
						$isAji = ($c['code'] === 'AJI');
					@endphp
					<tr class="daily-cause-row {{ $c['vital'] ? 'is-vital font-weight-600' : '' }}" 
						id="causeRow-{{ $c['code'] }}" 
						data-code="{{ $c['code'] }}" 
						data-name="{{ mb_strtolower($c['name']) }}" 
						data-class="{{ $clKey }}" 
						data-resp="{{ mb_strtolower($c['responsible_area'] ?? '') }}"
						data-cost="{{ $c['cost'] }}"
						onclick="dailySelectCausal('{{ $c['code'] }}');">
						<td class="font-mono font-weight-bold text-dark">{{ str_replace('DEMO-', 'EJ-', $c['code']) }}</td>
						<td>
							<span class="font-weight-600 text-dark">{{ $c['name'] }}</span>
							@if(!empty($c['is_definitive']) && $c['is_definitive'] == 0)
								<span class="badge badge-warning text-dark ml-1 font-weight-600" style="font-size: 0.68rem;">no definitiva</span>
							@endif
						</td>
						<td>
							<span class="daily-class-badge" style="{{ $meta['badge_style'] }}">
								{{ $isAji ? 'Ajustes de inventario' : $meta['label'] }}
							</span>
						</td>
						<td class="col-resp text-muted" style="font-size: 0.78rem;">{{ $c['responsible_area'] ?? '—' }}</td>
						<td class="text-right text-dark font-weight-500">{{ $c['events'] }}</td>
						<td class="text-right font-weight-800 text-dark">{{ $money($c['cost']) }}</td>
						<td class="text-right text-muted font-weight-600">{{ $pctFmt($c['pct']) }}</td>
						<td class="text-right text-muted font-weight-600">{{ $pctFmt($c['cum_pct']) }}</td>
						<td class="text-center">
							<button class="btn btn-sm btn-link p-0 text-muted" type="button" title="Ver detalle de {{ $c['code'] }}">
								<i class="fa-solid fa-chevron-right" style="font-size: 0.75rem;"></i>
							</button>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<!-- Paginación de la Tabla -->
		<div class="daily-table-pagination" id="dailyTablePaginationBar">
			<span id="dailyTablePaginationInfo">Mostrando 1–7 de 13 causales</span>
			<div class="d-flex align-items-center gap-3">
				<div class="daily-page-btns" id="dailyTablePageBtns">
					<!-- Botones generados por JS -->
				</div>
				<button class="btn btn-sm btn-link p-0 text-primary font-weight-600" style="font-size: 0.76rem;" onclick="dailyToggleShowAllCauses(this);">
					Ver todas las causales <i class="fa-solid fa-arrow-right ml-1"></i>
				</button>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN F: DETALLE DE RESPONSABLE, APROBACIÓN Y TRATAMIENTO
	     ====================================================================== -->
	@php
		$firstCause = $topCausal ?? ($causes[0] ?? null);
	@endphp
	<div class="daily-causal-detail-card" id="dailyCausalDetailPanel">
		<div class="daily-cdd-left">
			<div class="daily-cdd-badge" id="cddBadge">
				{{ $firstCause['code'] ?? 'CFR' }}
			</div>
			<div>
				<div class="daily-cdd-name" id="cddName">
					{{ $firstCause['name'] ?? 'Falla de cadena de frío' }}
				</div>
				<div class="daily-cdd-vals">
					<span id="cddCost">{{ $firstCause ? $money($firstCause['cost']) : '$0.00' }}</span>
					<span class="text-muted font-weight-600 ml-1" id="cddPct">{{ $firstCause ? $pctFmt($firstCause['pct']) : '0%' }}</span>
				</div>
				<div class="daily-cdd-events" id="cddEvents">
					{{ $firstCause['events'] ?? 0 }} eventos registrados
				</div>
			</div>
		</div>

		<div class="daily-cdd-grid">
			<!-- Responsable -->
			<div class="daily-cdd-col">
				<span class="daily-cdd-label text-muted">
					Responsable
				</span>
				<span class="daily-cdd-desc" id="cddResp">
					<i class="fa-solid fa-users text-muted mr-1.5"></i>{{ $firstCause['responsible_area'] ?? 'Mantenimiento / Calidad' }}
				</span>
			</div>

			<!-- Aprobación -->
			<div class="daily-cdd-col">
				<span class="daily-cdd-label text-muted">
					Aprobación
				</span>
				<span class="daily-cdd-desc" id="cddApproval">
					<i class="fa-solid fa-file-signature text-muted mr-1.5"></i>{{ $firstCause['approval_rule'] ?? 'Calidad evalúa; gerente autoriza la baja' }}
				</span>
			</div>

			<!-- Tratamiento -->
			<div class="daily-cdd-col">
				<span class="daily-cdd-label text-muted">
					Tratamiento
				</span>
				<span class="daily-cdd-desc" id="cddTreatment">
					<i class="fa-solid fa-gear text-muted mr-1.5"></i>{{ $firstCause['treatment'] ?? 'Cuarentena, evaluación de calidad y decisión autorizada' }}
				</span>
			</div>
		</div>

		<div class="d-none d-md-block text-right">
			<a href="{{ $U('/waste') }}?start_date={{ $startDate }}&end_date={{ $endDate }}&cause={{ $firstCause['code'] ?? 'CFR' }}#tab-clasificacion" 
			   id="cddDrillLink" 
			   class="btn btn-sm btn-outline-secondary font-weight-600" 
			   style="font-size: 0.76rem; border-radius: 6px;">
				Ver movimientos <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
			</a>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN G: PENDIENTES DE INVESTIGACIÓN & MOTIVOS DE REGISTRO
	     ====================================================================== -->
	<!-- Callout de Investigación -->
	<div class="daily-inv-callout">
		<div class="d-flex align-items-center gap-2">
			<i class="fa-solid fa-triangle-exclamation text-warning" style="font-size: 1rem;"></i>
			<span class="daily-inv-badge">INV</span>
			<span>
				<strong>Pendiente de investigación:</strong> origen no confirmado · Requiere plazo y responsable · No definitiva.
			</span>
		</div>
		<a href="javascript:void(0)" onclick="$('#tab-investigaciones-link').click();" class="text-dark font-weight-700 text-underline" style="font-size: 0.78rem;">
			Ir a investigaciones <i class="fa-solid fa-arrow-right ml-1"></i>
		</a>
	</div>

	<!-- Motivos de registro → causal canónica (36) -->
	<details class="daily-reasons-details">
		<summary>
			<i class="fa-solid fa-chevron-right text-muted transition-transform"></i>
			Motivos de registro → causal canónica ({{ count($mappedReasons) }})
		</summary>
		<p class="text-muted small mb-3">
			Cada motivo que usa el personal en tienda apunta a una causal canónica. Cambiar el mapeo reclasifica el histórico de forma consistente (la causal se resuelve al consultar).
		</p>
		
		<div class="table-responsive bg-white rounded border">
			<table class="table table-sm daily-analytics-table" style="font-size: 0.78rem;">
				<thead>
					<tr>
						<th style="width: 80px;">CÓDIGO</th>
						<th>MOTIVO DE ORIGEN</th>
						<th style="width: 140px;">CAUSAL CANÓNICA</th>
						<th class="text-right" style="width: 100px;">REGISTROS</th>
						<th class="text-right" style="width: 110px;">COSTO PERÍODO</th>
						<th style="width: 90px;">ESTADO</th>
						@if($canEditCauses)
						<th class="text-right" style="width: 80px;">ACCIÓN</th>
						@endif
					</tr>
				</thead>
				<tbody>
					@foreach($mappedReasons as $mr)
					<tr>
						<td class="font-mono text-muted font-weight-bold">{{ str_replace('DEMO-', 'EJ-', $mr['code']) }}</td>
						<td class="font-weight-600 text-dark">{{ $mr['name'] }}</td>
						<td>
							<span class="badge badge-light border font-mono font-weight-bold text-dark px-2 py-1">
								{{ $mr['loss_cause_code'] }}
							</span>
						</td>
						<td class="text-right text-dark font-weight-600">{{ $mr['record_count'] ?? 0 }}</td>
						<td class="text-right font-weight-700 text-dark">{{ $money($mr['period_cost'] ?? 0) }}</td>
						<td>
							<span class="badge badge-{{ $mr['active'] ? 'success' : 'secondary' }}">
								{{ $mr['active'] ? 'Activo' : 'Inactivo' }}
							</span>
						</td>
						@if($canEditCauses)
						<td class="text-right">
							<a href="javascript:void(0)" class="small text-primary font-weight-600" 
							   onclick='DairyLoss.dialog("Causal de «{{ e($mr['name']) }}»", "<div class=\"form-group\"><label>Causal canónica</label><select class=\"form-control form-control-sm\" name=\"cause\">@foreach($causes as $optC)<option value=\"{{ $optC['code'] }}\" {{ $optC['code'] == $mr['loss_cause_code'] ? 'selected' : '' }}>{{ $optC['code'] }} · {{ $optC['name'] }}</option>@endforeach</select></div>", "loss/reasons/{{ $mr['id'] }}/cause"); return false;'>
								Cambiar
							</a>
						</td>
						@endif
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</details>

	<!-- ======================================================================
	     SECCIÓN H: MATRIZ ESTÁNDAR DE CAUSALES DE MERMA (CATÁLOGO INFERIOR)
	     ====================================================================== -->
	<div class="daily-cc-card" id="matrizEstandarSection">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
			<div>
				<h5 class="font-weight-bold text-dark mb-0 d-flex align-items-center gap-2" style="font-size: 1rem;">
					<i class="fa-solid fa-table-cells text-success"></i>Matriz Estándar de Causales de Merma
				</h5>
				<p class="text-muted mb-0 small">Catálogo estandarizado sin duplicidad, clasificado por área y severidad.</p>
			</div>
			
			<div class="d-flex align-items-center gap-2">
				<!-- Buscador del catálogo estándar -->
				<div class="daily-search-box" style="min-width: 220px;">
					<i class="fa-solid fa-magnifying-glass search-icon"></i>
					<input type="text" id="dailyStdSearch" class="form-control" placeholder="Buscar código, nombre o categoría..." onkeyup="dailyFilterStandardMatrix();">
				</div>

				<!-- Filtro por severidad -->
				<select id="dailyStdRiskFilter" class="form-control form-control-sm" style="width: auto; font-size: 0.78rem; border-radius: 7px; height: 32px;" onchange="dailyFilterStandardMatrix();">
					<option value="">Severidad (Todas)</option>
					<option value="Crítico">Crítico</option>
					<option value="Alto">Alto / Alta</option>
					<option value="Medio">Medio / Media</option>
					<option value="Bajo">Bajo / Baja</option>
				</select>

				<!-- Botón Nueva Causal -->
				<button class="btn btn-sm btn-primary font-weight-600 shadow-xs" 
						style="background-color: var(--daily-primary, #00C83B); border-color: var(--daily-primary, #00C83B); font-size: 0.78rem; height: 32px; border-radius: 7px; padding: 5px 12px;" 
						onclick="window.location.href = U('/waste/reasons');">
					<i class="fa-solid fa-plus mr-1"></i>Nueva causal
				</button>
			</div>
		</div>

		<div class="table-responsive daily-table-container">
			<table class="table daily-analytics-table" id="dailyStandardMatrixTable">
				<thead>
					<tr>
						<th style="width: 80px;">CÓDIGO</th>
						<th>NOMBRE DE CAUSAL</th>
						<th>CATEGORÍA</th>
						<th>ÁREA RESPONSABLE</th>
						<th>SEVERIDAD</th>
						<th>IMPACTO</th>
						<th style="width: 85px;">ESTADO</th>
					</tr>
				</thead>
				<tbody id="dailyStdTbody">
					@foreach($stdReasons as $rs)
					@php
						$risk = trim($rs->risk_level ?? 'Medio');
						$isCrit = in_array(mb_strtolower($risk), ['crítico', 'critico']);
						$isHigh = in_array(mb_strtolower($risk), ['alto', 'alta']);
						$isMed = in_array(mb_strtolower($risk), ['medio', 'media']);
						$isLow = in_array(mb_strtolower($risk), ['bajo', 'baja']);
						
						$impact = trim($rs->economic_impact ?? 'Medio');
						$impactLower = mb_strtolower($impact);
						$impactColor = ($impactLower === 'severo') ? '#ef4444' : (($impactLower === 'alto') ? '#f97316' : (($impactLower === 'medio') ? '#eab308' : '#10b981'));
					@endphp
					<tr class="daily-std-row" 
						data-code="{{ $rs->code }}" 
						data-name="{{ mb_strtolower($rs->name) }}" 
						data-category="{{ mb_strtolower($rs->category) }}" 
						data-area="{{ mb_strtolower($rs->responsible_area) }}" 
						data-risk="{{ $risk }}"
						data-impact="{{ $impact }}">
						<td class="font-mono font-weight-bold text-success">{{ str_replace('DEMO-', 'EJ-', $rs->code) }}</td>
						<td class="font-weight-600 text-dark">{{ $rs->name }}</td>
						<td><span class="badge badge-light border text-muted px-2 py-1">{{ $rs->category }}</span></td>
						<td class="text-muted" style="font-size: 0.78rem;">{{ $rs->responsible_area }}</td>
						<td>
							@if($isCrit)
								<span class="font-weight-700 text-danger" style="font-size: 0.77rem;">
									<i class="fa-solid fa-circle mr-1" style="font-size: 0.55rem; color: #dc2626;"></i>Crítico
								</span>
							@elseif($isHigh)
								<span class="font-weight-700 text-danger" style="font-size: 0.77rem;">
									<i class="fa-solid fa-circle mr-1" style="font-size: 0.55rem; color: #ef4444;"></i>{{ $risk }}
								</span>
							@elseif($isMed)
								<span class="font-weight-700 text-warning" style="font-size: 0.77rem;">
									<i class="fa-solid fa-circle mr-1" style="font-size: 0.55rem; color: #f59e0b;"></i>{{ $risk }}
								</span>
							@else
								<span class="font-weight-700 text-success" style="font-size: 0.77rem;">
									<i class="fa-solid fa-circle mr-1" style="font-size: 0.55rem; color: #10b981;"></i>{{ $risk }}
								</span>
							@endif
						</td>
						<td>
							<span class="font-weight-600" style="color: {{ $impactColor }}; font-size: 0.77rem;">
								<i class="fa-solid fa-circle mr-1" style="font-size: 0.55rem; color: {{ $impactColor }};"></i>{{ $impact }}
							</span>
						</td>
						<td>
							<span class="badge badge-success px-2 py-1" style="font-size: 0.72rem; border-radius: 4px;">Activo</span>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<!-- Paginación Matriz Estándar -->
		<div class="daily-table-pagination" id="dailyStdPaginationBar">
			<span id="dailyStdPaginationInfo">Mostrando 1–6 de {{ count($stdReasons) }} causales</span>
			<div class="daily-page-btns" id="dailyStdPageBtns">
				<!-- Botones generados por JS -->
			</div>
		</div>
	</div>

</div>

<!-- SCRIPT COMPLETO DE LÓGICA E INTERACCIÓN DE LA PESTAÑA CAUSALES -->
<script>
(function() {
	var fullCauses = {!! json_encode($causes) !!};
	var posCauses = fullCauses.filter(function(c) { return parseFloat(c.cost) > 0; });
	var causesMap = {};
	fullCauses.forEach(function(c) { causesMap[c.code] = c; });

	var currentPage = 1;
	var pageSize = 7;
	var showAllCauses = false;
	var filteredCauseRows = [];

	var stdCurrentPage = 1;
	var stdPageSize = 6;
	var filteredStdRows = [];

	// 1. Inicializar Gráfico de Pareto (Chart.js)
	function initParetoChart() {
		var canvas = document.getElementById('dailyParetoCanvas');
		if (!canvas || typeof Chart === 'undefined') return;

		// Solo causales con costo > 0 para un Pareto riguroso
		var rows = posCauses.slice();
		var labels = rows.map(function(r) { return r.code; });
		var costs = rows.map(function(r) { return parseFloat(r.cost); });
		var cumPcts = rows.map(function(r) { return parseFloat(r.cum_pct); });
		var threshold = rows.map(function() { return 80; });

		// Paleta de degradado cálido
		var warmPalette = [
			'#ef4444', '#f87171', '#fb923c', '#f97316', '#ea580c',
			'#eab308', '#ca8a04', '#d97706', '#b45309', '#a16207',
			'#78716c', '#57534e', '#44403c'
		];
		var barColors = rows.map(function(r, idx) {
			return warmPalette[idx % warmPalette.length];
		});

		var ctx = canvas.getContext('2d');
		if (window._dailyParetoInstance) {
			window._dailyParetoInstance.destroy();
		}

		window._dailyParetoInstance = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						type: 'line',
						label: '% Acumulado',
						data: cumPcts,
						borderColor: '#1e293b',
						backgroundColor: '#1e293b',
						pointBackgroundColor: '#1e293b',
						pointBorderColor: '#1e293b',
						pointRadius: 3.5,
						pointHoverRadius: 5.5,
						fill: false,
						yAxisID: 'pctAxis',
						borderWidth: 2.2,
						order: 1
					},
					{
						type: 'line',
						label: 'Umbral 80%',
						data: threshold,
						borderColor: '#94a3b8',
						borderDash: [5, 4],
						pointRadius: 0,
						fill: false,
						yAxisID: 'pctAxis',
						borderWidth: 1.5,
						order: 2
					},
					{
						type: 'bar',
						label: 'Costo de merma (US$)',
						data: costs,
						backgroundColor: barColors,
						borderRadius: 4,
						yAxisID: 'costAxis',
						order: 3
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				animation: {
					duration: 350,
					onComplete: function() {
						var chartInstance = this.chart;
						var cctx = chartInstance.ctx;
						cctx.save();
						cctx.font = 'bold 8.5px sans-serif';
						cctx.textAlign = 'center';
						cctx.textBaseline = 'bottom';
						cctx.fillStyle = '#475569';

						this.data.datasets.forEach(function(dataset, i) {
							if (dataset.label === 'Costo de merma (US$)') {
								var meta = chartInstance.controller.getDatasetMeta(i);
								meta.data.forEach(function(bar, index) {
									var val = dataset.data[index];
									if (val > 0) {
										cctx.fillText('$' + Number(val).toFixed(2), bar._model.x, bar._model.y - 3);
									}
								});
							}
						});
						cctx.restore();
					}
				},
				tooltips: {
					mode: 'index',
					intersect: false,
					callbacks: {
						title: function(items) {
							var idx = items[0].index;
							var c = rows[idx];
							return c.code + ' · ' + c.name;
						},
						label: function(tooltipItem, data) {
							var dsLabel = data.datasets[tooltipItem.datasetIndex].label;
							if (dsLabel === 'Umbral 80%') return null;
							if (dsLabel === '% Acumulado') {
								return '% Acumulado: ' + Number(tooltipItem.yLabel).toFixed(1) + '%';
							}
							var idx = tooltipItem.index;
							var c = rows[idx];
							return [
								'Costo: $' + Number(c.cost).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
								'Participación: ' + (c.pct !== null ? Number(c.pct).toFixed(1) + '%' : '—'),
								'Eventos: ' + c.events
							];
						}
					}
				},
				scales: {
					xAxes: [{
						gridLines: { display: false },
						ticks: { fontColor: '#64748b', fontSize: 10, fontStyle: '600' }
					}],
					yAxes: [
						{
							id: 'costAxis',
							position: 'left',
							gridLines: { color: 'rgba(226, 232, 240, 0.6)' },
							ticks: {
								beginAtZero: true,
								fontColor: '#64748b',
								fontSize: 10,
								callback: function(v) { return '$' + v; }
							}
						},
						{
							id: 'pctAxis',
							position: 'right',
							gridLines: { display: false },
							ticks: {
								beginAtZero: true,
								max: 100,
								fontColor: '#64748b',
								fontSize: 10,
								callback: function(v) { return v + '%'; }
							}
						}
					]
				}
			}
		});

		// Clic en barra del Pareto selecciona la causal
		canvas.onclick = function(evt) {
			var activePts = window._dailyParetoInstance.getElementAtEvent(evt);
			if (activePts.length > 0) {
				var clickedIdx = activePts[0]._index;
				var clickedCause = rows[clickedIdx];
				if (clickedCause) {
					dailySelectCausal(clickedCause.code);
				}
			}
		};
	}

	// 2. Selección interactiva de Causal (sincroniza Detalle + Tabla + Ranking + Tarjeta)
	window.dailySelectCausal = function(code) {
		var c = causesMap[code];
		if (!c) return;

		// Actualizar panel de detalle
		var cddBadge = document.getElementById('cddBadge');
		var cddName = document.getElementById('cddName');
		var cddCost = document.getElementById('cddCost');
		var cddPct = document.getElementById('cddPct');
		var cddEvents = document.getElementById('cddEvents');
		var cddResp = document.getElementById('cddResp');
		var cddApproval = document.getElementById('cddApproval');
		var cddTreatment = document.getElementById('cddTreatment');
		var cddDrill = document.getElementById('cddDrillLink');

		if (cddBadge) {
			cddBadge.textContent = c.code;
			if (c.code === 'EXP') {
				cddBadge.style.background = '#f59e0b';
			} else if (c.code === 'AVE' || c.code === 'DEX') {
				cddBadge.style.background = '#8b5cf6';
			} else if (c.code === 'AJI') {
				cddBadge.style.background = '#475569';
			} else {
				cddBadge.style.background = '#ef4444';
			}
		}
		if (cddName) cddName.textContent = c.name;
		if (cddCost) cddCost.textContent = '$' + Number(c.cost).toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		if (cddPct) cddPct.textContent = (c.pct !== null) ? Number(c.pct).toFixed(1) + '%' : '—';
		if (cddEvents) cddEvents.textContent = c.events + ' eventos registrados';
		if (cddResp) cddResp.innerHTML = '<i class="fa-solid fa-users text-muted mr-1.5"></i>' + (c.responsible_area || 'Operaciones');
		if (cddApproval) cddApproval.innerHTML = '<i class="fa-solid fa-file-signature text-muted mr-1.5"></i>' + (c.approval_rule || 'Supervisor aprueba');
		if (cddTreatment) cddTreatment.innerHTML = '<i class="fa-solid fa-gear text-muted mr-1.5"></i>' + (c.treatment || 'Revisión y protocolo operativo');
		if (cddDrill) {
			cddDrill.href = Grocy.BaseUrl + '/waste?start_date={{ $startDate }}&end_date={{ $endDate }}&cause=' + encodeURIComponent(c.code) + '#tab-clasificacion';
		}

		// Resaltar tarjeta top 3 si corresponde
		document.querySelectorAll('.daily-top3-card').forEach(function(el) { el.classList.remove('active-selected'); });
		var topCard = document.getElementById('topCard-' + code);
		if (topCard) topCard.classList.add('active-selected');

		// Resaltar ranking row si corresponde
		document.querySelectorAll('.daily-ranking-row').forEach(function(el) { el.classList.remove('active-selected'); });
		var rankRow = document.getElementById('rankingRow-' + code);
		if (rankRow) rankRow.classList.add('active-selected');

		// Resaltar fila en la tabla
		document.querySelectorAll('.daily-cause-row').forEach(function(el) { el.classList.remove('row-selected'); });
		var tableRow = document.getElementById('causeRow-' + code);
		if (tableRow) {
			tableRow.classList.add('row-selected');
			// Si la fila está oculta por paginación, cambiar a la página donde está
			var targetIdx = filteredCauseRows.indexOf(tableRow);
			if (targetIdx !== -1 && !showAllCauses) {
				var neededPage = Math.floor(targetIdx / pageSize) + 1;
				if (neededPage !== currentPage) {
					currentPage = neededPage;
					renderTablePage();
				}
			}
		}

		// Scroll suave si el panel de detalle no está a la vista
		var detailPanel = document.getElementById('dailyCausalDetailPanel');
		if (detailPanel) {
			var rect = detailPanel.getBoundingClientRect();
			if (rect.top < 0 || rect.bottom > window.innerHeight) {
				detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}
	};

	// 3. Switcher de pestañas / vista de la tabla
	window.dailySwitchTableTab = function(view) {
		document.querySelectorAll('.daily-pill-btn').forEach(function(btn) {
			btn.classList.toggle('active', btn.getAttribute('data-tab-view') === view);
		});

		var table = document.getElementById('dailyCausalesTable');
		if (!table) return;

		if (view === 'impact') {
			table.querySelectorAll('.col-resp').forEach(function(el) { el.style.display = ''; });
		} else if (view === 'treatment') {
			table.querySelectorAll('.col-resp').forEach(function(el) { el.style.display = ''; });
			// Enfocar en el panel de detalle
			var detailPanel = document.getElementById('dailyCausalDetailPanel');
			if (detailPanel) detailPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
		} else if (view === 'catalog') {
			var catSec = document.getElementById('matrizEstandarSection');
			if (catSec) catSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	// 4. Filtrado y Paginación de la Tabla Analítica
	window.dailyFilterTable = function() {
		var q = (document.getElementById('dailyTableSearch').value || '').toLowerCase().trim();
		var cl = document.getElementById('dailyTableClassFilter').value;

		var allRows = Array.from(document.querySelectorAll('#dailyCausalesTbody .daily-cause-row'));
		filteredCauseRows = allRows.filter(function(row) {
			var code = (row.getAttribute('data-code') || '').toLowerCase();
			var name = (row.getAttribute('data-name') || '').toLowerCase();
			var rowClass = row.getAttribute('data-class') || '';
			var resp = (row.getAttribute('data-resp') || '').toLowerCase();
			var cost = parseFloat(row.getAttribute('data-cost') || '0');

			// Por defecto o con búsqueda: si no hay búsqueda ni filtro de clase, mostramos las causales activas del período (13 causales)
			var matchesActivity = (q || cl || showAllCauses) ? true : (cost > 0);
			var matchesQuery = !q || code.includes(q) || name.includes(q) || resp.includes(q);
			var matchesClass = !cl || (rowClass === cl);

			return matchesActivity && matchesQuery && matchesClass;
		});

		allRows.forEach(function(row) { row.style.display = 'none'; });

		currentPage = 1;
		renderTablePage();
	};

	function renderTablePage() {
		var total = filteredCauseRows.length;
		var totalPages = Math.max(1, Math.ceil(total / pageSize));

		if (currentPage > totalPages) currentPage = totalPages;

		var start = (currentPage - 1) * pageSize;
		var end = showAllCauses ? total : Math.min(start + pageSize, total);

		filteredCauseRows.forEach(function(row, idx) {
			if (showAllCauses || (idx >= start && idx < end)) {
				row.style.display = '';
			} else {
				row.style.display = 'none';
			}
		});

		// Actualizar información de paginación
		var info = document.getElementById('dailyTablePaginationInfo');
		if (info) {
			if (total === 0) {
				info.textContent = 'Sin causales coincidentes';
			} else if (showAllCauses) {
				info.textContent = 'Mostrando las ' + total + ' causales';
			} else {
				info.textContent = 'Mostrando ' + (start + 1) + '–' + end + ' de ' + total + ' causales';
			}
		}

		// Generar botones de páginas
		var btnsContainer = document.getElementById('dailyTablePageBtns');
		if (btnsContainer) {
			if (showAllCauses || totalPages <= 1) {
				btnsContainer.innerHTML = '';
				return;
			}
			var html = '';
			html += '<button type="button" class="daily-page-btn" onclick="dailyGoTablePage(' + (currentPage - 1) + ');" ' + (currentPage === 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left" style="font-size: 0.65rem;"></i></button>';
			for (var p = 1; p <= totalPages; p++) {
				html += '<button type="button" class="daily-page-btn ' + (p === currentPage ? 'active' : '') + '" onclick="dailyGoTablePage(' + p + ');">' + p + '</button>';
			}
			html += '<button type="button" class="daily-page-btn" onclick="dailyGoTablePage(' + (currentPage + 1) + ');" ' + (currentPage === totalPages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i></button>';
			btnsContainer.innerHTML = html;
		}
	}

	window.dailyGoTablePage = function(page) {
		currentPage = page;
		renderTablePage();
	};

	window.dailyToggleShowAllCauses = function(btn) {
		showAllCauses = !showAllCauses;
		if (btn) {
			btn.innerHTML = showAllCauses 
				? 'Paginar vista <i class="fa-solid fa-table-list ml-1"></i>' 
				: 'Ver todas las causales <i class="fa-solid fa-arrow-right ml-1"></i>';
		}
		dailyFilterTable();
	};

	// 5. Filtrado y Paginación de la Matriz Estándar
	window.dailyFilterStandardMatrix = function() {
		var q = (document.getElementById('dailyStdSearch').value || '').toLowerCase().trim();
		var risk = document.getElementById('dailyStdRiskFilter').value;

		var allRows = Array.from(document.querySelectorAll('#dailyStdTbody .daily-std-row'));
		filteredStdRows = allRows.filter(function(row) {
			var code = (row.getAttribute('data-code') || '').toLowerCase();
			var name = (row.getAttribute('data-name') || '').toLowerCase();
			var cat = (row.getAttribute('data-category') || '').toLowerCase();
			var area = (row.getAttribute('data-area') || '').toLowerCase();
			var rowRisk = row.getAttribute('data-risk') || '';

			var matchesQuery = !q || code.includes(q) || name.includes(q) || cat.includes(q) || area.includes(q);
			var matchesRisk = !risk || (rowRisk.toLowerCase().includes(risk.toLowerCase()));

			return matchesQuery && matchesRisk;
		});

		allRows.forEach(function(row) { row.style.display = 'none'; });

		stdCurrentPage = 1;
		renderStdPage();
	};

	function renderStdPage() {
		var total = filteredStdRows.length;
		var totalPages = Math.max(1, Math.ceil(total / stdPageSize));

		if (stdCurrentPage > totalPages) stdCurrentPage = totalPages;

		var start = (stdCurrentPage - 1) * stdPageSize;
		var end = Math.min(start + stdPageSize, total);

		filteredStdRows.forEach(function(row, idx) {
			if (idx >= start && idx < end) {
				row.style.display = '';
			} else {
				row.style.display = 'none';
			}
		});

		var info = document.getElementById('dailyStdPaginationInfo');
		if (info) {
			if (total === 0) {
				info.textContent = 'Sin causales coincidentes';
			} else {
				info.textContent = 'Mostrando ' + (start + 1) + '–' + end + ' de ' + total + ' causales';
			}
		}

		var btnsContainer = document.getElementById('dailyStdPageBtns');
		if (btnsContainer) {
			if (totalPages <= 1) {
				btnsContainer.innerHTML = '';
				return;
			}
			var html = '';
			html += '<button type="button" class="daily-page-btn" onclick="dailyGoStdPage(' + (stdCurrentPage - 1) + ');" ' + (stdCurrentPage === 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left" style="font-size: 0.65rem;"></i></button>';
			for (var p = 1; p <= totalPages; p++) {
				html += '<button type="button" class="daily-page-btn ' + (p === stdCurrentPage ? 'active' : '') + '" onclick="dailyGoStdPage(' + p + ');">' + p + '</button>';
			}
			html += '<button type="button" class="daily-page-btn" onclick="dailyGoStdPage(' + (stdCurrentPage + 1) + ');" ' + (stdCurrentPage === totalPages ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i></button>';
			btnsContainer.innerHTML = html;
		}
	}

	window.dailyGoStdPage = function(page) {
		stdCurrentPage = page;
		renderStdPage();
	};

	window.dailyClearCausalFilter = function() {
		var q = new URLSearchParams(window.location.search);
		q.delete('cause');
		window.location.href = window.location.pathname + '?' + q.toString() + '#tab-causales';
	};

	// Inicialización al cargar la vista
	setTimeout(function() {
		initParetoChart();
		dailyFilterTable();
		dailyFilterStandardMatrix();

		// Si hay una causal activa en la URL, seleccionarla
		var urlParams = new URLSearchParams(window.location.search);
		var activeCause = urlParams.get('cause') || '{{ $topCausal['code'] ?? ($causes[0]['code'] ?? '') }}';
		if (activeCause) {
			dailySelectCausal(activeCause);
		}
	}, 120);

})();
</script>
