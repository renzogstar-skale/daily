<?php
$css = file_get_contents('public/css/daily_executive_panel.css');
// Append the photo replica styles to daily_executive_panel.css
$replicaCss = <<<'CSS'

/* ==========================================================================
   DAILY — RÉPLICA EXACTA PANEL EJECUTIVO FOTO MAESTRA
   ========================================================================== */

.ep-photo-replica-wrap {
	width: 100%;
	max-width: 1440px;
	margin: 0 auto;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}

/* 1. FILA DE 6 INDICADORES EJECUTIVOS */
.ep-kpis-grid-6 {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 12px;
	margin-bottom: 14px;
}

.ep-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	text-decoration: none;
	color: inherit;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 116px;
	transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
	cursor: pointer;
}

.ep-kpi-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
	border-color: #cbd5e1;
	text-decoration: none;
	color: inherit;
}

.ep-kpi-card-header {
	display: flex;
	align-items: flex-start;
	gap: 10px;
}

.ep-kpi-icon-box {
	width: 36px;
	height: 36px;
	border-radius: 9px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1rem;
	color: #ffffff;
	flex-shrink: 0;
}

.ep-kpi-icon-box.bg-green { background: #16a34a; }
.ep-kpi-icon-box.bg-red { background: #dc2626; }
.ep-kpi-icon-box.bg-amber { background: #f59e0b; }

.ep-kpi-text-box {
	flex: 1;
	min-width: 0;
}

.ep-kpi-title {
	font-size: 0.78rem;
	font-weight: 600;
	color: #64748b;
	margin-bottom: 1px;
}

.ep-kpi-value {
	font-size: 1.3rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	letter-spacing: -0.02em;
}

.ep-kpi-sub {
	font-size: 0.7rem;
	color: #64748b;
	margin-top: 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.ep-kpi-card-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 10px;
}

.ep-kpi-delta {
	font-size: 0.72rem;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 3px;
}

.ep-kpi-delta.text-green { color: #16a34a; }
.ep-kpi-delta.text-red { color: #dc2626; }
.ep-kpi-delta.text-amber { color: #d97706; }

.ep-sparkline-svg {
	width: 52px;
	height: 20px;
	display: block;
}

/* 2. FILA CENTRAL DE 3 PANELES */
.ep-mid-grid-3 {
	display: grid;
	grid-template-columns: 3.8fr 3.7fr 2.5fr;
	gap: 14px;
	margin-bottom: 14px;
}

.ep-card-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.ep-card-title {
	font-size: 1rem;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 12px;
	display: flex;
	align-items: center;
	justify-content: space-between;
}

/* 2.A ESTADO DE SUCURSALES */
.ep-branches-layout {
	display: grid;
	grid-template-columns: 46% 54%;
	gap: 12px;
	align-items: flex-start;
}

.ep-map-container {
	width: 100%;
	position: relative;
	background: #f8fafc;
	border-radius: 10px;
	padding: 6px;
	border: 1px solid #f1f5f9;
}

.ep-colombia-svg {
	width: 100%;
	height: auto;
	max-height: 290px;
	display: block;
}

.ep-branch-pin {
	cursor: pointer;
	transition: transform 0.2s ease;
}

.ep-branch-pin:hover {
	transform: scale(1.25);
}

.ep-branches-list-wrap {
	display: flex;
	flex-direction: column;
}

.ep-branches-header-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
	padding-bottom: 6px;
	border-bottom: 1px solid #f1f5f9;
}

.ep-company-name {
	font-size: 0.82rem;
	font-weight: 700;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 6px;
}

.ep-status-pills-row {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.68rem;
	font-weight: 700;
}

.ep-branch-item-row {
	display: grid;
	grid-template-columns: 1fr 68px 34px;
	align-items: center;
	gap: 6px;
	padding: 4px 6px;
	border-radius: 6px;
	cursor: pointer;
	transition: background 0.15s ease;
}

.ep-branch-item-row:hover, .ep-branch-item-row.selected {
	background: #f1f5f9;
}

.ep-branch-name-col {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.74rem;
	font-weight: 600;
	color: #1e293b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.ep-branch-badge {
	font-size: 0.65rem;
	font-weight: 700;
	padding: 2px 6px;
	border-radius: 10px;
	text-align: center;
	display: inline-block;
}

.ep-branch-badge.operativa { background: #dcfce7; color: #15803d; }
.ep-branch-badge.atencion { background: #fef9c3; color: #a16207; }
.ep-branch-badge.critica { background: #fee2e2; color: #b91c1c; }

.ep-branch-pct {
	font-size: 0.74rem;
	font-weight: 700;
	color: #0f172a;
	text-align: right;
}

/* 2.B VENTAS TENDENCIA DEL DÍA */
.ep-sales-trend-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 8px;
}

.ep-sales-trend-val {
	display: flex;
	align-items: center;
	gap: 6px;
}

.ep-sales-cur-headline {
	font-size: 1.15rem;
	font-weight: 800;
	color: #0f172a;
}

.ep-sales-delta-tag {
	font-size: 0.76rem;
	font-weight: 700;
	color: #16a34a;
	background: #dcfce7;
	padding: 2px 6px;
	border-radius: 6px;
}

.ep-sales-chart-wrap {
	height: 180px;
	position: relative;
	width: 100%;
}

.ep-top-depts-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 14px;
	margin-bottom: 8px;
	padding-top: 10px;
	border-top: 1px solid #f1f5f9;
}

.ep-top-depts-title {
	font-size: 0.85rem;
	font-weight: 700;
	color: #0f172a;
}

.ep-top-depts-link {
	font-size: 0.74rem;
	font-weight: 600;
	color: #16a34a;
	text-decoration: none;
}

.ep-top-depts-link:hover {
	text-decoration: underline;
}

.ep-dept-row {
	display: grid;
	grid-template-columns: 120px 65px 1fr 45px;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
	font-size: 0.74rem;
}

.ep-dept-name {
	font-weight: 600;
	color: #1e293b;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.ep-dept-val {
	font-weight: 700;
	color: #0f172a;
	text-align: right;
}

.ep-dept-bar-track {
	height: 6px;
	background: #e2e8f0;
	border-radius: 3px;
	overflow: hidden;
}

.ep-dept-bar-fill {
	height: 100%;
	background: #16a34a;
	border-radius: 3px;
}

.ep-dept-share {
	font-weight: 700;
	color: #64748b;
	text-align: right;
}

/* 2.C ALERTAS Y ACCIONES */
.ep-alerts-col {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.ep-alerts-card, .ep-urgent-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.ep-alert-item-row {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	padding: 7px 0;
	border-bottom: 1px solid #f8fafc;
	cursor: pointer;
	transition: background 0.15s ease;
}

.ep-alert-item-row:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.ep-alert-icon-square {
	width: 26px;
	height: 26px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.78rem;
	flex-shrink: 0;
	margin-top: 1px;
}

.ep-alert-icon-square.danger { background: #fee2e2; color: #dc2626; }
.ep-alert-icon-square.warning { background: #fef9c3; color: #ca8a04; }

.ep-alert-text-body {
	flex: 1;
	min-width: 0;
}

.ep-alert-headline {
	font-size: 0.76rem;
	font-weight: 700;
	color: #0f172a;
	line-height: 1.2;
}

.ep-alert-source {
	font-size: 0.68rem;
	color: #64748b;
	margin-top: 1px;
}

.ep-alert-time {
	font-size: 0.66rem;
	color: #94a3b8;
	white-space: nowrap;
	margin-left: 4px;
}

.ep-urgent-action-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 7px 0;
	border-bottom: 1px solid #f8fafc;
	cursor: pointer;
	transition: color 0.15s ease;
}

.ep-urgent-action-row:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.ep-urgent-check {
	width: 16px;
	height: 16px;
	border-radius: 4px;
	accent-color: #16a34a;
	cursor: pointer;
}

.ep-urgent-label {
	flex: 1;
	font-size: 0.76rem;
	font-weight: 600;
	color: #1e293b;
}

.ep-urgent-chevron {
	color: #94a3b8;
	font-size: 0.72rem;
}

.ep-urgent-action-row:hover .ep-urgent-label {
	color: #16a34a;
}

/* 3. FILA INFERIOR (RESUMEN POR PROCESO Y ACCESOS RÁPIDOS) */
.ep-bottom-grid-2 {
	display: grid;
	grid-template-columns: 7.5fr 2.5fr;
	gap: 14px;
	margin-bottom: 14px;
}

.ep-process-subgrid-7 {
	display: grid;
	grid-template-columns: repeat(7, 1fr);
	gap: 10px;
	margin-top: 10px;
}

.ep-process-mini-card {
	background: #fbfcfd;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 110px;
	text-decoration: none;
	color: inherit;
	transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.ep-process-mini-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
	border-color: #cbd5e1;
	text-decoration: none;
	color: inherit;
}

.ep-process-card-icon {
	font-size: 1.15rem;
}

.ep-process-card-icon.success { color: #16a34a; }
.ep-process-card-icon.warning { color: #d97706; }
.ep-process-card-icon.danger { color: #dc2626; }

.ep-process-card-title {
	font-size: 0.8rem;
	font-weight: 700;
	color: #0f172a;
	margin-top: 4px;
}

.ep-process-badge {
	font-size: 0.65rem;
	font-weight: 700;
	padding: 1px 6px;
	border-radius: 8px;
	display: inline-block;
	width: fit-content;
	margin: 3px 0;
}

.ep-process-badge.atencion { background: #fef3c7; color: #b45309; }
.ep-process-badge.ok { background: #dcfce7; color: #15803d; }
.ep-process-badge.critico { background: #fee2e2; color: #b91c1c; }

.ep-process-desc {
	font-size: 0.68rem;
	color: #64748b;
	line-height: 1.25;
}

.ep-process-arrow {
	text-align: right;
	font-size: 0.72rem;
	color: #94a3b8;
	margin-top: 4px;
}

.ep-quick-buttons-4 {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 8px;
	margin-top: 10px;
}

.ep-quick-btn-card {
	background: #fbfcfd;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 4px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	text-decoration: none;
	color: #0f172a;
	min-height: 85px;
	transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}

.ep-quick-btn-card:hover {
	transform: translateY(-2px);
	background: #f1f5f9;
	border-color: #cbd5e1;
	text-decoration: none;
	color: #0f172a;
}

.ep-quick-btn-icon {
	font-size: 1.35rem;
	margin-bottom: 6px;
}

.ep-quick-btn-label {
	font-size: 0.68rem;
	font-weight: 600;
	line-height: 1.15;
	color: #0f172a;
}

/* RESPONSIVE */
@media (max-width: 1200px) {
	.ep-kpis-grid-6 {
		grid-template-columns: repeat(3, 1fr);
	}
	.ep-mid-grid-3 {
		grid-template-columns: 1fr 1fr;
	}
	.ep-alerts-col {
		grid-column: span 2;
		display: grid;
		grid-template-columns: 1fr 1fr;
	}
	.ep-bottom-grid-2 {
		grid-template-columns: 1fr;
	}
	.ep-process-subgrid-7 {
		grid-template-columns: repeat(4, 1fr);
	}
}

@media (max-width: 768px) {
	.ep-kpis-grid-6 {
		grid-template-columns: repeat(2, 1fr);
	}
	.ep-mid-grid-3 {
		grid-template-columns: 1fr;
	}
	.ep-branches-layout {
		grid-template-columns: 1fr;
	}
	.ep-alerts-col {
		grid-column: span 1;
		grid-template-columns: 1fr;
	}
	.ep-process-subgrid-7 {
		grid-template-columns: repeat(2, 1fr);
	}
	.ep-quick-buttons-4 {
		grid-template-columns: repeat(2, 1fr);
	}
}
CSS;

if (strpos($css, 'ep-photo-replica-wrap') === false) {
    file_put_contents('public/css/daily_executive_panel.css', $css . "\n" . $replicaCss);
    echo "Successfully appended replica CSS!\n";
} else {
    echo "Replica CSS already present.\n";
}

