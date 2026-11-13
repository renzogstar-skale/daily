@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$fmtRange = function($from, $to) {
		if (!$from || !$to) return '';
		$months = [1=>'ene', 2=>'feb', 3=>'mar', 4=>'abr', 5=>'may', 6=>'jun', 7=>'jul', 8=>'ago', 9=>'sep', 10=>'oct', 11=>'nov', 12=>'dic'];
		$t1 = strtotime($from);
		$t2 = strtotime($to);
		$d1 = date('j', $t1);
		$d2 = date('j', $t2);
		$m1 = $months[intval(date('n', $t1))] ?? date('M', $t1);
		$m2 = $months[intval(date('n', $t2))] ?? date('M', $t2);
		$y1 = date('Y', $t1);
		$y2 = date('Y', $t2);
		if ($m1 === $m2 && $y1 === $y2) {
			return "$d1 – $d2 $m2 $y2";
		}
		return "$d1 $m1 – $d2 $m2 $y2";
	};
	
	// Datos Financieros (LossIntelligenceService::Financial)
	$S = $fin['summary'] ?? [];
	$R = $S['recovery'] ?? ['recoverable' => 0, 'recovered' => 0, 'pct' => null, 'supplier' => ['requested' => 0, 'recovered' => 0, 'count' => 0], 'internal' => ['recoverable' => 0, 'recovered' => 0, 'count' => 0]];
	$Lb = $fin['labor'] ?? ['hours' => null, 'source' => 'turnos programados', 'employees' => 0, 'per_employee' => null, 'per_hour' => null];
	$marginImpact = $S['margin_impact'] ?? ['margin_before_pct' => 0, 'margin_after_pct' => 0, 'pp' => 0];
	$byClass = $S['by_class'] ?? [];
	$totalLossValue = floatval($S['total']['value'] ?? 0);
	$netSales = floatval($S['net_sales'] ?? 0);
	$grossMargin = floatval($fin['gross_margin'] ?? 0);
	$opProfitEst = floatval($fin['operating_profit_est'] ?? ($grossMargin - $totalLossValue));
	$stockValue = floatval($fin['stock_value'] ?? 0);
	$immobilizedCapital = floatval($fin['immobilized_capital'] ?? 0);
	$saleValue = floatval($S['sale_value'] ?? 0);
	$vis = $fin['vis'] ?? ['defined' => false, 'definition' => null, 'source' => null, 'value' => null];

	// Datos de Pérdida Total Clasificada (LossPreventionService::TotalLoss)
	$L = $loss ?? [
		'from' => $startDate,
		'to' => min($endDate, date('Y-m-d')),
		'sales' => $netSales,
		'known' => ['total' => 0, 'by_class' => []],
		'unknown' => ['loss' => 0, 'gain' => 0, 'net' => 0, 'adjustments' => 0],
		'unexplained_outflows' => ['total' => 0, 'count' => 0, 'rows' => []],
		'pos_gap' => ['total' => 0, 'count' => 0, 'sales' => 0, 'rows' => []],
		'receiving_rejections' => 0,
		'recovered' => 0,
		'gross_loss' => 0,
		'net_loss' => 0,
		'net_loss_pct_sales' => null
	];
	$knownTotal = floatval($L['known']['total'] ?? 0);
	$grossLoss = floatval($L['gross_loss'] ?? 0);
	$netLoss = floatval($L['net_loss'] ?? 0);
	$knownPct = $grossLoss > 0 ? round(($knownTotal / $grossLoss) * 100) : 0;

	// Reclamos a Proveedor
	$returns = $supplierReturns ?? [];

	// KPIs de Control Center (AkcalMetricsService)
	$pk = $photoKpis ?? ['recovered_amount' => $R['internal']['recovered'] ?? 0, 'recovery_rate' => 32.7];

	// Permisos
	$canCfg = $canConfig ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_WASTE_REASONS_EDIT, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
	$canNewClaim = $canClaim ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_PROCUREMENT_MANAGE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
@endphp

<!-- ==========================================================================
     ESTILOS ESPECÍFICOS DE LA PESTAÑA: IMPACTO FINANCIERO Y RECUPERACIÓN
     DAILY Look & Feel Enterprise: Bordes discretos, tarjetas blancas, sombras suaves
     ========================================================================== -->
<style>
/* Ocultar bloque duplicado del layout global al renderizar la pestaña */
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyImpactoTabContainer) .dairy-related {
	display: none !important;
}

/* Contenedor general */
.daily-impacto-tab {
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
	padding: 18px 20px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03), 0 4px 12px rgba(0, 0, 0, 0.02);
	position: relative;
	transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.daily-cc-card:hover {
	border-color: #cbd5e1;
}

/* Encabezado de la pestaña con foto banner */
.daily-impacto-header-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 14px 20px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	position: relative;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.daily-impacto-header-text {
	z-index: 2;
	position: relative;
	max-width: 75%;
}
.daily-impacto-header-title {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 4px 0;
	letter-spacing: -0.015em;
}
.daily-impacto-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.daily-impacto-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 250px;
	z-index: 1;
	pointer-events: none;
	overflow: hidden;
}
.daily-impacto-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-impacto-header-photo-mask {
	position: absolute;
	top: 0; bottom: 0; left: 0; right: 0;
	background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0.85) 20%, rgba(255, 255, 255, 0) 100%);
}

/* Cabeceras de Sección A y B */
.daily-sec-title-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 2px;
}
.daily-sec-title {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-sec-sub {
	font-size: 0.8rem;
	color: #64748b;
	margin: 0 0 14px 0;
	line-height: 1.4;
}

/* Fila de 4 KPI Cards principales + Contexto Económico */
.daily-fin-kpi-layout {
	display: grid;
	grid-template-columns: 2fr 1fr;
	gap: 14px;
	margin-bottom: 14px;
}
@media (max-width: 1100px) {
	.daily-fin-kpi-layout {
		grid-template-columns: 1fr;
	}
}
.daily-fin-kpis-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 12px;
}
@media (max-width: 768px) {
	.daily-fin-kpis-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
@media (max-width: 480px) {
	.daily-fin-kpis-grid {
		grid-template-columns: 1fr;
	}
}
.daily-fin-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 14px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	transition: transform 0.15s ease, border-color 0.15s ease;
}
.daily-fin-kpi-card:hover {
	transform: translateY(-1.5px);
	border-color: #cbd5e1;
}
.daily-fin-kpi-card.tone-red { border-left: 4px solid #ef4444; }
.daily-fin-kpi-card.tone-green { border-left: 4px solid #16a34a; }
.daily-fin-kpi-card.tone-amber { border-left: 4px solid #f59e0b; }
.daily-fin-kpi-card.tone-graphite { border-left: 4px solid #475569; }

.daily-fin-kpi-lbl {
	font-size: 0.72rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
	margin-bottom: 6px;
}
.daily-fin-kpi-val {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	margin-bottom: 3px;
}
.daily-fin-kpi-sub {
	font-size: 0.73rem;
	color: #64748b;
	font-weight: 500;
}

/* Card Contexto Económico (Derecha) */
.daily-eco-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-eco-head {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.75rem;
	font-weight: 700;
	color: #16a34a;
	margin-bottom: 10px;
}
.daily-eco-items {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}
.daily-eco-item-lbl {
	font-size: 0.7rem;
	font-weight: 700;
	color: #64748b;
	margin-bottom: 2px;
}
.daily-eco-item-val {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
}
.daily-eco-item-sub {
	font-size: 0.72rem;
	color: #94a3b8;
}

/* Fila de Productividad */
.daily-prod-subhead {
	font-size: 0.72rem;
	font-weight: 800;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
	margin-bottom: 8px;
}
.daily-prod-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr) 1.5fr;
	gap: 12px;
}
@media (max-width: 900px) {
	.daily-prod-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
@media (max-width: 576px) {
	.daily-prod-grid {
		grid-template-columns: 1fr;
	}
}
.daily-prod-card {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-prod-lbl {
	font-size: 0.7rem;
	font-weight: 700;
	color: #64748b;
	margin-bottom: 2px;
}
.daily-prod-val {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	margin-bottom: 2px;
}
.daily-prod-sub {
	font-size: 0.72rem;
	color: #64748b;
}
.daily-prod-disclaimer-card {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 0.76rem;
	color: #64748b;
	line-height: 1.35;
}

/* Sección Recuperación Financiera: 4 Cards + VIS */
.daily-rec-top-layout {
	display: grid;
	grid-template-columns: 3fr 1.2fr;
	gap: 14px;
	margin-bottom: 14px;
}
@media (max-width: 992px) {
	.daily-rec-top-layout {
		grid-template-columns: 1fr;
	}
}
.daily-rec-cards-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 10px;
}
@media (max-width: 768px) {
	.daily-rec-cards-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
.daily-rec-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 14px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-rec-kpi-card.tone-green { border-left: 4px solid #16a34a; }
.daily-rec-kpi-card.tone-amber { border-left: 4px solid #f59e0b; }
.daily-rec-kpi-card.is-alert {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 12px;
}
.daily-rec-kpi-lbl {
	font-size: 0.7rem;
	font-weight: 700;
	color: #64748b;
	margin-bottom: 4px;
}
.daily-rec-kpi-val {
	font-size: 1.35rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	margin-bottom: 3px;
}
.daily-rec-kpi-val.text-green { color: #15803d; }
.daily-rec-kpi-sub {
	font-size: 0.72rem;
	color: #64748b;
}

/* Indicador VIS Card */
.daily-vis-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 16px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-vis-title {
	font-size: 0.85rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 4px 0;
}
.daily-vis-state {
	font-size: 0.74rem;
	font-weight: 700;
	color: #475569;
	margin-bottom: 4px;
}
.daily-vis-desc {
	font-size: 0.71rem;
	color: #64748b;
	line-height: 1.35;
	margin-bottom: 8px;
}

/* Gráfico Recuperación vs Tabla Clase */
.daily-rec-chart-layout {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}
@media (max-width: 900px) {
	.daily-rec-chart-layout {
		grid-template-columns: 1fr;
	}
}
.daily-subbox-title {
	font-size: 0.88rem;
	font-weight: 800;
	color: #0f172a;
	margin-bottom: 8px;
}
.daily-rec-legend {
	display: flex;
	align-items: center;
	gap: 14px;
	font-size: 0.72rem;
	color: #64748b;
	margin-bottom: 10px;
}
.daily-rec-legend-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	display: inline-block;
}
.daily-rec-legend-dot.is-stone { background: #94a3b8; }
.daily-rec-legend-dot.is-green { background: #16a34a; }

/* Tabla Distribución por Clase */
.daily-class-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.78rem;
}
.daily-class-table thead th {
	background: #f8fafc;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 7px 10px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-class-table tbody td {
	padding: 8px 10px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-class-table tbody tr:hover td {
	background: #f8fafc;
}

/* Sección Reclamos a Proveedor */
.daily-claims-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12px;
	flex-wrap: wrap;
	gap: 8px;
}
.daily-claims-title {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}
.daily-btn-green-action {
	background: var(--daily-primary, #00C83B) !important;
	color: #ffffff !important;
	border: 1px solid var(--daily-primary, #00C83B) !important;
	font-size: 0.8rem;
	font-weight: 700;
	padding: 6px 14px;
	border-radius: 8px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	transition: filter 0.15s ease;
	text-decoration: none !important;
}
.daily-btn-green-action:hover {
	filter: brightness(0.92);
	color: #ffffff !important;
}

/* Tabla de Reclamos */
.daily-claims-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.8rem;
}
.daily-claims-table thead th {
	background: #f8fafc;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 9px 12px;
	border-bottom: 1px solid #e2e8f0;
	border-top: 1px solid #e2e8f0;
	white-space: nowrap;
}
.daily-claims-table tbody td {
	padding: 9px 12px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-claims-table tbody tr:hover td {
	background: #f8fafc;
}

/* Chips de estado */
.daily-chip-pill {
	display: inline-flex;
	align-items: center;
	padding: 2.5px 8px;
	border-radius: 6px;
	font-size: 0.72rem;
	font-weight: 700;
}
.daily-chip-pill.is-green { background: #dcfce7; color: #15803d; }
.daily-chip-pill.is-amber { background: #fef3c7; color: #b45309; }
.daily-chip-pill.is-slate { background: #f1f5f9; color: #475569; }

/* Sección B: Pérdida Total Clasificada Layout */
.daily-loss-total-layout {
	display: grid;
	grid-template-columns: 1.15fr 1fr;
	gap: 16px;
}
@media (max-width: 992px) {
	.daily-loss-total-layout {
		grid-template-columns: 1fr;
	}
}

/* Tabla Cascada de Pérdida Total */
.daily-waterfall-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.78rem;
}
.daily-waterfall-table thead th {
	background: #f8fafc;
	color: #64748b;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 8px 10px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-waterfall-table tbody td {
	padding: 7px 10px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-waterfall-table tr.row-highlight {
	background: #f8fafc;
	font-weight: 700;
}
.daily-waterfall-table tr.row-sub td {
	padding-left: 24px;
	font-size: 0.74rem;
	color: #475569;
}

/* Cuadros de Partidas Fuera del Total (Derecha de Sección B) */
.daily-outside-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
	margin-bottom: 12px;
}
@media (max-width: 576px) {
	.daily-outside-grid {
		grid-template-columns: 1fr;
	}
}
.daily-outside-box {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 10px 12px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-outside-lbl {
	font-size: 0.72rem;
	font-weight: 700;
	color: #92400e;
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 2px;
}
.daily-outside-val {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
}
.daily-outside-sub {
	font-size: 0.7rem;
	color: #b45309;
}

/* Mini Tablas Salidas Sin Documento y POS */
.daily-mini-tables-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}
@media (max-width: 576px) {
	.daily-mini-tables-grid {
		grid-template-columns: 1fr;
	}
}
.daily-mini-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 12px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-mini-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 6px;
}
.daily-mini-title {
	font-size: 0.76rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
}
.daily-mini-link {
	font-size: 0.7rem;
	font-weight: 700;
	color: var(--daily-primary, #00C83B);
	text-decoration: none !important;
}
.daily-mini-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.72rem;
}
.daily-mini-table thead th {
	background: #f8fafc;
	color: #64748b;
	font-size: 0.65rem;
	font-weight: 700;
	padding: 4px 6px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-mini-table tbody td {
	padding: 5px 6px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-mini-pagination {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 8px;
	font-size: 0.68rem;
	color: #64748b;
	margin-top: 6px;
}
.daily-mini-page-btn {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	color: #475569;
	border-radius: 4px;
	padding: 1px 6px;
	cursor: pointer;
	font-size: 0.68rem;
}
.daily-mini-page-btn:hover {
	background: #e2e8f0;
	color: #0f172a;
}

/* Alerta inferior */
.daily-bottom-alert {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 8px;
	padding: 8px 12px;
	font-size: 0.74rem;
	color: #92400e;
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 12px;
}

/* Módulos Conectados */
.daily-connected-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
	flex-wrap: wrap;
	gap: 8px;
}
.daily-connected-title {
	font-size: 0.98rem;
	font-weight: 800;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}
.daily-connected-map-link {
	font-size: 0.76rem;
	font-weight: 700;
	color: var(--daily-primary, #00C83B);
	text-decoration: none !important;
}
.daily-connected-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}
@media (max-width: 900px) {
	.daily-connected-grid {
		grid-template-columns: 1fr;
	}
}
.daily-conn-col-title {
	font-size: 0.68rem;
	font-weight: 800;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.05em;
	margin-bottom: 8px;
}
.daily-conn-items-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.daily-conn-item {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	text-decoration: none !important;
	color: inherit !important;
	transition: all 0.15s ease;
}
.daily-conn-item:hover {
	border-color: #cbd5e1;
	transform: translateX(2px);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.daily-conn-item-left {
	display: flex;
	align-items: center;
	gap: 12px;
}
.daily-conn-item-icon {
	width: 32px;
	height: 32px;
	border-radius: 8px;
	background: #dcfce7;
	color: #16a34a;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.88rem;
	flex-shrink: 0;
}
.daily-conn-item-name {
	font-size: 0.82rem;
	font-weight: 700;
	color: #0f172a;
}
.daily-conn-item-sub {
	font-size: 0.72rem;
	color: #64748b;
}
.daily-conn-item-arrow {
	color: #94a3b8;
	font-size: 0.8rem;
	transition: transform 0.15s ease;
}
.daily-conn-item:hover .daily-conn-item-arrow {
	transform: translateX(2px);
	color: var(--daily-primary, #00C83B);
}
</style>

<div class="daily-impacto-tab" id="dailyImpactoTabContainer">

	<!-- ======================================================================
	     ENCABEZADO DE LA PESTAÑA CON FOTOGRAFÍA CONTEXTUAL INTEGRADA
	     ====================================================================== -->
	<div class="daily-impacto-header-card">
		<div class="daily-impacto-header-text">
			<h5 class="daily-impacto-header-title">Impacto financiero y recuperación</h5>
			<p class="daily-impacto-header-sub">
				<i class="fa-solid fa-chart-line text-muted"></i>
				Análisis de pérdidas, recuperación, impacto en margen y productividad. Los períodos pueden ser diferentes por fuente, no son directamente comparables.
			</p>
		</div>
		<div class="daily-impacto-header-photo">
			<img src="{{ $U('/img/control_center/impacto_quesos_banner.jpg') }}" alt="Productos gourmet y lácteos" loading="lazy">
			<div class="daily-impacto-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN A: IMPACTO DEL PERÍODO (1 – 31 OCT 2026)
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-sec-title-row">
			<h6 class="daily-sec-title">
				<i class="fa-regular fa-calendar-days text-muted"></i>
				A. Impacto del período ({{ $fmtRange($startDate, $endDate) }})
			</h6>
		</div>
		<p class="daily-sec-sub">
			Costo de la pérdida, impacto en margen y valor de venta potencial. La dotación se relaciona con la merma sin atribuirla a personas.
		</p>

		<!-- 4 KPI Cards + Contexto Económico -->
		<div class="daily-fin-kpi-layout">
			<!-- Fila de 4 Cards -->
			<div class="daily-fin-kpis-grid">
				<!-- Costo de la pérdida -->
				<div class="daily-fin-kpi-card tone-red">
					<div class="daily-fin-kpi-lbl">Costo de la pérdida</div>
					<div class="daily-fin-kpi-val text-danger">{{ $money($totalLossValue) }}</div>
					<div class="daily-fin-kpi-sub">{{ $pct($S['total']['pct_sales'] ?? null) }} del total en el período</div>
				</div>

				<!-- Margen bruto -->
				<div class="daily-fin-kpi-card tone-green">
					<div class="daily-fin-kpi-lbl">Margen bruto</div>
					<div class="daily-fin-kpi-val">{{ $pct($marginImpact['margin_before_pct']) }} → {{ $pct($marginImpact['margin_after_pct']) }}</div>
					<div class="daily-fin-kpi-sub text-danger">−{{ $pct($marginImpact['pp']) }} pp por mermas</div>
				</div>

				<!-- Utilidad operativa estimada -->
				<div class="daily-fin-kpi-card tone-amber">
					<div class="daily-fin-kpi-lbl">Utilidad operativa estimada</div>
					<div class="daily-fin-kpi-val">{{ $money($opProfitEst) }}</div>
					<div class="daily-fin-kpi-sub">vs. {{ $money($grossMargin) }} sin merma</div>
				</div>

				<!-- Capital inmovilizado -->
				<div class="daily-fin-kpi-card tone-graphite" title="Stock en sucursal con antigüedad ≥ 30 días sin rotación ni salidas">
					<div class="daily-fin-kpi-lbl">Capital inmovilizado</div>
					<div class="daily-fin-kpi-val">{{ $money($immobilizedCapital) }}</div>
					<div class="daily-fin-kpi-sub">lotes sin salida</div>
				</div>
			</div>

			<!-- Contexto económico del período -->
			<div class="daily-eco-card">
				<div class="daily-eco-head">
					<i class="fa-solid fa-basket-shopping"></i> Contexto económico del período
				</div>
				<div class="daily-eco-items">
					<div>
						<div class="daily-eco-item-lbl">Costo de inventario</div>
						<div class="daily-eco-item-val">{{ $money($stockValue) }}</div>
						<div class="daily-eco-item-sub">merma = {{ $pct($fin['loss_over_stock_pct'] ?? null) }} del inv.</div>
					</div>
					<div>
						<div class="daily-eco-item-lbl">Valor de venta perdido</div>
						<div class="daily-eco-item-val">{{ $money($saleValue) }}</div>
						<div class="daily-eco-item-sub">potencial a precio de venta</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Impacto en productividad -->
		<div>
			<div class="daily-prod-subhead">Impacto en productividad</div>
			<div class="daily-prod-grid">
				<div class="daily-prod-card">
					<div class="daily-prod-lbl">Merma por hora</div>
					<div class="daily-prod-val">{{ $money($Lb['per_hour']) }}</div>
					<div class="daily-prod-sub">Base: {{ $Lb['hours'] !== null ? number_format($Lb['hours'], 0) . ' h ' . $Lb['source'] : 'sin horas' }}</div>
				</div>
				<div class="daily-prod-card">
					<div class="daily-prod-lbl">Merma por empleado</div>
					<div class="daily-prod-val">{{ $money($Lb['per_employee']) }}</div>
					<div class="daily-prod-sub">Base: {{ $Lb['employees'] ? $Lb['employees'] . ' empleados activos' : 'sin dotación' }}</div>
				</div>
				<div class="daily-prod-card">
					<div class="daily-prod-lbl">Conteos del período</div>
					<div class="daily-prod-val">{{ $fin['cycle_counts'] ?? 0 }}</div>
					<div class="daily-prod-sub">horas de reprocesos: sin fuente</div>
				</div>
				<div class="daily-prod-disclaimer-card">
					<i class="fa-solid fa-circle-info text-muted"></i>
					<div>Indicadores agregados, no atribuyen responsabilidad individual.</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN: RECUPERACIÓN FINANCIERA & INDICADOR VIS
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-sec-title-row">
			<h6 class="daily-sec-title">
				<i class="fa-solid fa-chart-column text-success"></i> Recuperación financiera
			</h6>
		</div>
		<p class="daily-sec-sub">
			Según registros de la fuente. El porcentaje puede superar 100% si incluye conceptos adicionales a la pérdida.
		</p>

		<!-- 4 Cards de Recuperación + VIS -->
		<div class="daily-rec-top-layout">
			<!-- 4 Cards en fila -->
			<div class="daily-rec-cards-grid">
				<!-- Reporte financiero -->
				<div class="daily-rec-kpi-card tone-green">
					<div class="daily-rec-kpi-lbl">Reporte financiero</div>
					<div class="daily-rec-kpi-val text-green">{{ $money($R['recovered']) }}</div>
					<div class="daily-rec-kpi-sub">
						Base: {{ $money($R['recoverable']) }} · 
						<strong class="{{ ($R['pct'] ?? 0) > 100 ? 'text-warning' : 'text-success' }}">{{ $pct($R['pct']) }}</strong>
					</div>
				</div>

				<!-- Registro de recuperación -->
				<div class="daily-rec-kpi-card tone-green">
					<div class="daily-rec-kpi-lbl">Registro de recuperación</div>
					<div class="daily-rec-kpi-val text-green">{{ $money($pk['recovered_amount']) }}</div>
					<div class="daily-rec-kpi-sub">{{ $pk['recovery_rate'] }}% reportado</div>
				</div>

				<!-- Notas de crédito proveedor -->
				<div class="daily-rec-kpi-card tone-amber">
					<div class="daily-rec-kpi-lbl">Notas de crédito proveedor</div>
					<div class="daily-rec-kpi-val">{{ $money($R['supplier']['recovered']) }}</div>
					<div class="daily-rec-kpi-sub">{{ $R['supplier']['count'] }} evento(s)</div>
				</div>

				<!-- Alerta Conciliación pendiente -->
				<div class="daily-rec-kpi-card is-alert">
					<div class="daily-rec-kpi-lbl text-warning font-weight-800">
						<i class="fa-solid fa-triangle-exclamation mr-1"></i> Conciliación de recuperación pendiente
					</div>
					<div class="small text-muted" style="font-size: 0.72rem; line-height: 1.3;">
						Existen diferentes períodos y fuentes. Bases por validar, no sumar.
					</div>
				</div>
			</div>

			<!-- Indicador VIS -->
			<div class="daily-vis-card">
				<div>
					<h6 class="daily-vis-title">Indicador VIS</h6>
					<div class="daily-vis-state text-muted">Pendiente de definición.</div>
					<p class="daily-vis-desc">
						El significado exacto de «VIS» y su fuente de datos deben venir del negocio; el sistema no lo inventa ni lo calcula hasta tenerlos.
					</p>
				</div>
				@if($canCfg)
					<button type="button" class="btn btn-xs btn-outline-dark font-weight-700" 
					        onclick='DairyLoss.dialog("Definir indicador VIS", "<div class=\"form-group\"><label>Definición exacta (fórmula y unidades)</label><textarea class=\"form-control\" rows=\"3\" name=\"settings[vis_definition]\"></textarea></div><div class=\"form-group\"><label>Fuente de datos</label><input class=\"form-control\" name=\"settings[vis_source]\"></div>", "loss/settings", "Definición guardada");'>
						Registrar definición
					</button>
				@endif
			</div>
		</div>

		<!-- Gráficos Side-by-side: Recuperación por origen vs Distribución por clase -->
		<div class="daily-rec-chart-layout">
			<!-- Izquierda: Recuperación por origen -->
			<div>
				<div class="daily-subbox-title">Recuperación por origen</div>
				<div class="daily-rec-legend">
					<span><span class="daily-rec-legend-dot is-stone"></span> Recuperable estimado</span>
					<span><span class="daily-rec-legend-dot is-green"></span> Recuperado</span>
				</div>
				<div class="dlx-chart is-sm mb-2" style="height: 180px;">
					<canvas id="liRecBarChart" role="img" aria-label="Gráfico de recuperación financiera"></canvas>
				</div>
				<div class="small text-muted mt-1" style="font-size: 0.72rem;">
					Montos reportados: validar período y relación con la pérdida de origen. El porcentaje puede superar 100%.
				</div>
			</div>

			<!-- Derecha: Distribución de impacto por clase -->
			<div>
				<div class="daily-subbox-title">Distribución de impacto por clase</div>
				<div class="table-responsive">
					<table class="daily-class-table">
						<thead>
							<tr>
								<th>Clase</th>
								<th class="text-right">Costo</th>
								<th class="text-right">% del total</th>
								<th class="text-right">% ventas</th>
							</tr>
						</thead>
						<tbody>
							@foreach(['operativa' => 'Merma operativa', 'comercial' => 'Merma comercial', 'ajuste' => 'Ajustes de inventario', 'averia' => 'Averías y roturas'] as $ck => $clabel)
								@php
									$costVal = floatval($byClass[$ck] ?? 0);
									$totPct = $totalLossValue > 0 ? round(($costVal / $totalLossValue) * 100, 1) : 0;
									$salesPct = $netSales > 0 ? round(($costVal / $netSales) * 100, 2) : 0;
								@endphp
								<tr>
									<td class="font-weight-600">{{ $clabel }}</td>
									<td class="text-right font-weight-700">{{ $money($costVal) }}</td>
									<td class="text-right text-muted">{{ $totPct }}%</td>
									<td class="text-right text-muted">{{ $salesPct }}%</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN: RECLAMOS A PROVEEDOR Y RECUPERACIÓN
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-claims-head">
			<div>
				<h6 class="daily-claims-title">
					<i class="fa-solid fa-hand-holding-dollar text-success"></i> Reclamos a proveedor y recuperación
				</h6>
				<p class="text-muted small mb-0">Gestión de notas de crédito y recuperaciones con proveedores.</p>
			</div>
			@if($canNewClaim)
				<button type="button" class="daily-btn-green-action" onclick="dairyNewClaimDialog();">
					<i class="fa-solid fa-plus"></i> Generar Reclamo a Proveedor
				</button>
			@endif
		</div>

		<div class="table-responsive">
			<table class="daily-claims-table">
				<thead>
					<tr>
						<th>Nº Reclamo</th>
						<th>Proveedor</th>
						<th>Producto</th>
						<th class="text-right">Valor reclamado</th>
						<th class="text-right">Valor recuperado</th>
						<th>Doc. Respaldo</th>
						<th>Estado</th>
						<th class="text-right">Acción</th>
					</tr>
				</thead>
				<tbody>
					@forelse($returns as $ret)
						<tr>
							<td class="font-mono font-weight-700">{{ $ret['case_number'] }}</td>
							<td class="font-weight-600">{{ $ret['supplier_name'] }}</td>
							<td>{{ $ret['product_name'] }}</td>
							<td class="text-right">{{ $money($ret['credit_requested_amount']) }}</td>
							<td class="text-right font-weight-700 {{ floatval($ret['credit_recovered_amount']) > 0 ? 'text-success' : 'text-danger' }}">
								{{ $money($ret['credit_recovered_amount']) }}
							</td>
							<td class="font-mono text-muted small">
								{{ $ret['resolved_timestamp'] ? substr($ret['resolved_timestamp'], 0, 10) : '—' }}
							</td>
							<td>
								@if($ret['status'] === 'credit_recovered')
									<span class="daily-chip-pill is-green">Recuperado</span>
								@else
									<span class="daily-chip-pill is-amber">Pendiente NC</span>
								@endif
							</td>
							<td class="text-right">
								@if($ret['status'] === 'pending_credit')
									<button type="button" class="btn btn-xs btn-outline-warning font-weight-700" 
									        onclick='dairyRecordCreditDialog({{ $ret['id'] }}, @json($ret['case_number']), {{ floatval($ret['credit_requested_amount']) }});'>
										Registrar NC
									</button>
								@else
									<a class="btn btn-xs btn-light border font-weight-600" href="{{ $U('/document/supplier_return/' . $ret['id']) }}" target="_blank" title="Ver comprobante de respaldo">
										Ver respaldo
									</a>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="8" class="text-center py-4 text-muted">Sin reclamos registrados en este período.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN B: PÉRDIDA TOTAL CLASIFICADA (1 – 24 OCT 2026)
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-sec-title-row">
			<h6 class="daily-sec-title">
				<i class="fa-regular fa-calendar-days text-muted"></i>
				B. Pérdida total clasificada ({{ $fmtRange($L['from'], $L['to']) }})
			</h6>
		</div>
		<p class="daily-sec-sub">
			Clasificación detallada de la pérdida. Este período y montos corresponden a eventos transcurridos hasta la fecha actual (corte: {{ $L['to'] }}).
		</p>

		<div class="daily-loss-total-layout">
			<!-- Columna Izquierda: Tabla Cascada Conciliatoria -->
			<div>
				<div class="table-responsive">
					<table class="daily-waterfall-table">
						<thead>
							<tr>
								<th>Componente</th>
								<th class="text-right">Monto</th>
								<th>Detalle</th>
							</tr>
						</thead>
						<tbody>
							<!-- Merma conocida -->
							<tr class="font-weight-700">
								<td><i class="fa-solid fa-chevron-down mr-1 small text-muted"></i> Merma conocida</td>
								<td class="text-right text-danger">{{ $money($L['known']['total']) }}</td>
								<td class="small text-muted">Casos aprobados con causal ({{ $knownPct }}% de la pérdida bruta)</td>
							</tr>
							@foreach($L['known']['by_class'] as $kc)
								<tr class="row-sub">
									<td>↳ {{ ucfirst($kc['class']) }}</td>
									<td class="text-right">{{ $money($kc['cost']) }}</td>
									<td class="small text-muted">{{ $kc['records'] }} registro(s)</td>
								</tr>
							@endforeach

							<!-- Diferencias por investigar -->
							<tr>
								<td class="font-weight-600"><i class="fa-solid fa-chevron-right mr-1 small text-muted"></i> Diferencias por investigar</td>
								<td class="text-right">{{ $money(max(0, $L['unknown']['net'])) }}</td>
								<td class="small text-muted">Ajustes de inventario: faltantes {{ $money($L['unknown']['loss']) }} − sobrantes {{ $money($L['unknown']['gain']) }} ({{ $L['unknown']['adjustments'] }} ajuste)</td>
							</tr>

							<!-- Salidas sin documento -->
							<tr>
								<td class="font-weight-600"><i class="fa-solid fa-chevron-right mr-1 small text-muted"></i> Salidas sin documento</td>
								<td class="text-right text-danger font-weight-700">{{ $money($L['unexplained_outflows']['total']) }}</td>
								<td class="small text-muted">{{ $L['unexplained_outflows']['count'] }} consumo(s) sin venta, merma ni traslado: investigar</td>
							</tr>

							<!-- Pérdida bruta -->
							<tr class="row-highlight">
								<td><i class="fa-solid fa-chevron-right mr-1 small text-muted"></i> Pérdida bruta</td>
								<td class="text-right font-weight-800">{{ $money($L['gross_loss']) }}</td>
								<td class="small text-muted">Suma de conocida + desconocida + salidas</td>
							</tr>

							<!-- Recuperado notas de crédito -->
							<tr>
								<td class="font-weight-600"><i class="fa-solid fa-chevron-right mr-1 small text-muted"></i> Recuperado (notas de crédito)</td>
								<td class="text-right text-success font-weight-700">−{{ $money($L['recovered']) }}</td>
								<td class="small text-muted">Créditos cobrados a proveedores</td>
							</tr>

							<!-- Pérdida neta -->
							<tr class="row-highlight" style="background: #fef2f2;">
								<td class="text-danger"><i class="fa-solid fa-chevron-right mr-1 small text-danger"></i> Pérdida neta</td>
								<td class="text-right text-danger font-weight-800">{{ $money($L['net_loss']) }}</td>
								<td class="small text-muted font-weight-600">{{ $L['net_loss_pct_sales'] !== null ? $L['net_loss_pct_sales'] . '% de la venta' : '—' }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="small text-muted mt-2">
					<i class="fa-solid fa-calculator mr-1"></i>
					Conciliación matemática: {{ $money($L['known']['total']) }} + {{ $money(max(0, $L['unknown']['net'])) }} + {{ $money($L['unexplained_outflows']['total']) }} − {{ $money($L['recovered']) }} = {{ $money($L['net_loss']) }}.
				</div>
			</div>

			<!-- Columna Derecha: Otros Fuera del Total y Mini Tablas -->
			<div>
				<!-- Otros fuera del total -->
				<div class="text-uppercase small font-weight-700 text-muted mb-2" style="font-size: 0.68rem; letter-spacing: 0.04em;">
					Otros fuera del total <span class="font-weight-400 text-muted">(no suman directamente a la pérdida)</span>
				</div>
				<div class="daily-outside-grid">
					<div class="daily-outside-box">
						<div class="daily-outside-lbl">
							<i class="fa-solid fa-triangle-exclamation"></i> Rechazos en recepción
						</div>
						<div class="daily-outside-val text-warning">{{ $money($L['receiving_rejections']) }}</div>
						<div class="daily-outside-sub">No ingresaron al inventario, no suma al total.</div>
					</div>
					<div class="daily-outside-box">
						<div class="daily-outside-lbl">
							<i class="fa-solid fa-triangle-exclamation"></i> Ventas sin descargo
						</div>
						<div class="daily-outside-val text-danger">{{ $money($L['pos_gap']['total']) }}</div>
						<div class="daily-outside-sub">{{ $L['pos_gap']['sales'] }} ventas · Sin movimiento de stock, no suma directamente.</div>
					</div>
				</div>

				<!-- Mini Tablas con Paginación -->
				<div class="daily-mini-tables-grid">
					<!-- Salidas sin documento -->
					<div class="daily-mini-card">
						<div>
							<div class="daily-mini-head">
								<h6 class="daily-mini-title">Salidas sin documento</h6>
								<a href="{{ $U('/kardex') }}" class="daily-mini-link">Ver detalle →</a>
							</div>
							<div class="table-responsive">
								<table class="daily-mini-table" id="dailyTableOutflows">
									<thead>
										<tr>
											<th>Fecha</th>
											<th>Producto</th>
											<th class="text-right">Cant.</th>
											<th class="text-right">Valor</th>
										</tr>
									</thead>
									<tbody>
										@forelse($L['unexplained_outflows']['rows'] as $r)
											<tr>
												<td class="text-muted">{{ substr($r['row_created_timestamp'], 5, 5) }}</td>
												<td class="font-weight-600 text-truncate" style="max-width: 90px;" title="{{ $r['product_name'] }}">{{ $r['product_name'] }}</td>
												<td class="text-right">{{ number_format($r['amount'], 0) }}</td>
												<td class="text-right font-weight-700">{{ $money($r['value']) }}</td>
											</tr>
										@empty
											<tr><td colspan="4" class="text-center py-2 text-muted">Sin salidas</td></tr>
										@endforelse
									</tbody>
								</table>
							</div>
						</div>
						<div class="daily-mini-pagination">
							<span>1-{{ count($L['unexplained_outflows']['rows']) }} de {{ $L['unexplained_outflows']['count'] }}</span>
							<button type="button" class="daily-mini-page-btn" disabled>&lt;</button>
							<button type="button" class="daily-mini-page-btn" disabled>&gt;</button>
						</div>
					</div>

					<!-- Conciliación POS ↔ inventario -->
					<div class="daily-mini-card">
						<div>
							<div class="daily-mini-head">
								<h6 class="daily-mini-title">Conciliación POS ↔ inventario</h6>
								<a href="{{ $U('/sales') }}" class="daily-mini-link">Ver detalle →</a>
							</div>
							<div class="table-responsive">
								<table class="daily-mini-table" id="dailyTablePosGap">
									<thead>
										<tr>
											<th>Producto</th>
											<th class="text-right">Cant.</th>
											<th class="text-right">Costo</th>
										</tr>
									</thead>
									<tbody>
										@forelse(array_slice($L['pos_gap']['rows'], 0, 6) as $r)
											<tr>
												<td class="font-weight-600 text-truncate" style="max-width: 95px;" title="{{ $r['product_name'] }}">{{ $r['product_name'] }}</td>
												<td class="text-right">{{ number_format($r['amount'], 1) }}</td>
												<td class="text-right font-weight-700">{{ $money($r['cost']) }}</td>
											</tr>
										@empty
											<tr><td colspan="3" class="text-center py-2 text-muted">Sin desvíos</td></tr>
										@endforelse
									</tbody>
								</table>
							</div>
						</div>
						<div class="daily-mini-pagination">
							<span>1-{{ min(6, count($L['pos_gap']['rows'])) }} de {{ $L['pos_gap']['count'] }}</span>
							<button type="button" class="daily-mini-page-btn" disabled>&lt;</button>
							<button type="button" class="daily-mini-page-btn" disabled>&gt;</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Alerta inferior Centro de Alertas -->
		<div class="daily-bottom-alert">
			<i class="fa-solid fa-triangle-exclamation text-warning"></i>
			<div>
				Los hallazgos de salidas sin documento y ventas sin descargo generan una alerta con responsable en el <a href="{{ $U('/alerts') }}" class="font-weight-700 text-dark">Centro de Alertas</a> (reglas: Salidas sin documento y Venta sin descargo).
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN: MÓDULOS CONECTADOS CON CENTRO DE CONTROL
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-connected-head">
			<h6 class="daily-connected-title">
				<i class="fa-solid fa-network-wired text-success"></i> Módulos conectados con Centro de Control
			</h6>
			<a href="{{ $U('/system-map') }}" class="daily-connected-map-link">
				Ver mapa del sistema <i class="fa-solid fa-arrow-right ml-1"></i>
			</a>
		</div>

		<div class="daily-connected-grid">
			<!-- RECIBE INFORMACIÓN DE -->
			<div>
				<div class="daily-conn-col-title">Recibe información de</div>
				<div class="daily-conn-items-list">
					<!-- Calidad & Vencimientos -->
					<a href="{{ $U('/waste#tab-fefo') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon">
								<i class="fa-solid fa-snowflake"></i>
							</div>
							<div>
								<div class="daily-conn-item-name">Calidad & Vencimientos</div>
								<div class="daily-conn-item-sub">Rebajas por vencimiento (prevención antes de la merma)</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<!-- Tareas y Roles -->
					<a href="{{ $U('/workforce') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon">
								<i class="fa-solid fa-list-check"></i>
							</div>
							<div>
								<div class="daily-conn-item-name">Tareas y Roles</div>
								<div class="daily-conn-item-sub">Merma exprés desde Piso de Tienda</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
				</div>
			</div>

			<!-- ACTUALIZA / ALIMENTA A -->
			<div>
				<div class="daily-conn-col-title">Actualiza / Alimenta a</div>
				<div class="daily-conn-items-list">
					<!-- Panel Ejecutivo -->
					<a href="{{ $U('/today') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon">
								<i class="fa-solid fa-chart-line"></i>
							</div>
							<div>
								<div class="daily-conn-item-name">Panel Ejecutivo</div>
								<div class="daily-conn-item-sub">Merma en el Panel Ejecutivo</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<!-- Centros de Costo -->
					<a href="{{ $U('/cost-centers') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon">
								<i class="fa-solid fa-building-columns"></i>
							</div>
							<div>
								<div class="daily-conn-item-name">Centros de Costo</div>
								<div class="daily-conn-item-sub">Merma por centro de costo</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>

					<!-- Reportes & Analítica -->
					<a href="{{ $U('/reports') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon">
								<i class="fa-solid fa-file-waveform"></i>
							</div>
							<div>
								<div class="daily-conn-item-name">Reportes & Analítica</div>
								<div class="daily-conn-item-sub">Desglose de merma en Reportes</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- ==========================================================================
     SCRIPTS INTERACTIVOS: GRÁFICOS Y ACCIONES DE RECUPERACIÓN
     ========================================================================== -->
<script>
(function() {
	// Ocultar bloque duplicado del layout global
	document.querySelectorAll('.dairy-related').forEach(function(el) {
		el.style.setProperty('display', 'none', 'important');
	});

	var R = {!! json_encode($R) !!};

	if (window.DairyLoss && document.getElementById('liRecBarChart')) {
		var supReq = (R && R.supplier && R.supplier.requested) || 0;
		var supRec = (R && R.supplier && R.supplier.recovered) || 0;
		var intReq = (R && R.internal && R.internal.recoverable) || 0;
		var intRec = (R && R.internal && R.internal.recovered) || 0;

		DairyLoss.bars('liRecBarChart', ['Proveedor', 'Recuperación interna'], [
			{ label: 'Recuperable estimado', data: [supReq, intReq], backgroundColor: '#94a3b8' },
			{ label: 'Recuperado', data: [supRec, intRec], backgroundColor: '#16a34a' }
		], 'cost');
	}
})();
</script>
