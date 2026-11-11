@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$today = date('Y-m-d');

	$actionPlansList = $actionPlans ?? [];
	$investigationsList = $cases ?? [];
	$usersList = $users ?? [];
	$reasonsList = $reasons ?? [];
	$branchesList = $branches ?? [];

	// Cálculos dinámicos de los 5 Indicadores Específicos
	$activeInvestigationsCount = count($investigationsList);
	$unassignedCount = count(array_filter($investigationsList, fn($c) => empty($c['investigator_user_id'])));
	$activePlansCount = count(array_filter($actionPlansList, fn($p) => in_array($p['status'], ['open', 'in_progress'])));
	$overduePlansCount = count(array_filter($actionPlansList, fn($p) => in_array($p['status'], ['open', 'in_progress']) && !empty($p['due_date']) && $p['due_date'] < $today));
	$closedPlansCount = count(array_filter($actionPlansList, fn($p) => in_array($p['status'], ['completed', 'closed', 'verified'], true)));

	// Resolver imagen del producto
	$productImg = function($c) use ($U) {
		if (!empty($c['picture_file_name'])) {
			return $U('/api/files/productpictures/' . base64_encode($c['picture_file_name']));
		}
		$name = mb_strtolower($c['product_name'] ?? '');
		if (str_contains($name, 'leche entera') || str_contains($name, 'galón')) {
			return $U('/img/control_center/leche_entera_galon.jpg');
		}
		if (str_contains($name, 'crema')) {
			return $U('/img/control_center/crema_de_leche.jpg');
		}
		if (str_contains($name, 'mozzarella') || str_contains($name, 'queso')) {
			return $U('/img/control_center/queso_mozzarella.jpg');
		}
		if (str_contains($name, 'huevo')) {
			return $U('/img/control_center/huevos_grandes.jpg');
		}
		return $U('/img/control_center/investigacion_dairy_banner.jpg');
	};

	$canInv = $canInvestigate ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_LOSS_PREVENTION, \Grocy\Controllers\Users\User::PERMISSION_WASTE_APPROVE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
	$canPln = $canPlan ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_WASTE_REASONS_EDIT, \Grocy\Controllers\Users\User::PERMISSION_LOSS_PREVENTION, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
	$canAsg = $canAssign ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_WASTE_APPROVE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
@endphp

<!-- ==========================================================================
     ESTILOS ESPECÍFICOS: INVESTIGACIÓN Y PLANES DE ACCIÓN
     DAILY Enterprise Look & Feel: Bordes sutiles, sombras suaves, sin azul marca
     ========================================================================== -->
<style>
/* Ocultar el footer genérico duplicado del layout global */
.view-waste_overview .dairy-related, .view-waste .dairy-related, body:has(#dailyInvestigacionesTabContainer) .dairy-related {
	display: none !important;
}

/* Contenedor general de la pestaña */
.daily-inv-tab {
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

/* 1. ENCABEZADO CON BANNER FOTOGRÁFICO */
.daily-inv-header-card {
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
	min-height: 96px;
}
.daily-inv-header-left {
	display: flex;
	align-items: center;
	gap: 16px;
	z-index: 2;
	max-width: 65%;
}
.daily-inv-header-icon {
	width: 44px;
	height: 44px;
	border-radius: 12px;
	background: #fef2f2;
	color: #dc2626;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.25rem;
	flex-shrink: 0;
	border: 1px solid #fee2e2;
}
.daily-inv-header-title {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	letter-spacing: -0.02em;
	margin: 0 0 4px 0;
}
.daily-inv-header-sub {
	font-size: 0.82rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-inv-header-photo {
	position: absolute;
	right: 0;
	top: 0;
	bottom: 0;
	width: 38%;
	height: 100%;
	z-index: 1;
	pointer-events: none;
}
.daily-inv-header-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-inv-header-photo-mask {
	position: absolute;
	inset: 0;
	background: linear-gradient(90deg, #ffffff 0%, rgba(255, 255, 255, 0.85) 20%, rgba(255, 255, 255, 0) 100%);
}

/* 2. BARRA DE INDICADORES ESPECÍFICOS (5 TARJETAS COMPACTAS) */
.daily-inv-kpis-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 12px;
}
@media (max-width: 1100px) {
	.daily-inv-kpis-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 680px) {
	.daily-inv-kpis-grid { grid-template-columns: 1fr 1fr; }
}
.daily-inv-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 82px;
	position: relative;
	transition: transform 0.15s ease, border-color 0.15s ease;
}
.daily-inv-kpi-card:hover {
	transform: translateY(-1px);
	border-color: #cbd5e1;
}
.daily-inv-kpi-card.tone-red { border-left: 4px solid #dc2626; }
.daily-inv-kpi-card.tone-amber { border-left: 4px solid #f59e0b; }
.daily-inv-kpi-card.tone-green { border-left: 4px solid #16a34a; }

.daily-inv-kpi-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.daily-inv-kpi-lbl {
	font-size: 0.68rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #64748b;
}
.daily-inv-kpi-icon {
	font-size: 0.9rem;
}
.daily-inv-kpi-val {
	font-size: 1.55rem;
	font-weight: 800;
	line-height: 1.1;
	margin: 2px 0;
}
.daily-inv-kpi-card.tone-red .daily-inv-kpi-val { color: #dc2626; }
.daily-inv-kpi-card.tone-amber .daily-inv-kpi-val { color: #d97706; }
.daily-inv-kpi-card.tone-green .daily-inv-kpi-val { color: #16a34a; }

.daily-inv-kpi-sub {
	font-size: 0.72rem;
	color: #64748b;
}

/* 3. CASOS ACTUALMENTE EN INVESTIGACIÓN */
.daily-cases-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 14px;
}
.daily-cases-title-wrap {
	display: flex;
	align-items: center;
	gap: 10px;
}
.daily-cases-title {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	letter-spacing: -0.01em;
}
.daily-cases-filters {
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-cases-search {
	width: 220px;
	font-size: 0.78rem;
	height: 32px;
	border-radius: 8px;
	border: 1px solid #cbd5e1;
	padding: 4px 10px;
}
.daily-cases-select {
	font-size: 0.78rem;
	height: 32px;
	border-radius: 8px;
	border: 1px solid #cbd5e1;
	padding: 4px 8px;
	background: #ffffff;
}

/* Grid de tarjetas de casos */
.daily-cases-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px;
}
@media (max-width: 1200px) {
	.daily-cases-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
	.daily-cases-grid { grid-template-columns: 1fr; }
}

.daily-case-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	transition: all 0.2s ease;
	cursor: pointer;
	position: relative;
}
.daily-case-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
	transform: translateY(-2px);
}
.daily-case-card.is-selected {
	border-color: var(--daily-primary, #00C83B);
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.2);
}
.daily-case-photo-box {
	width: 100%;
	height: 110px;
	position: relative;
	background: #f8fafc;
	overflow: hidden;
	display: flex;
	align-items: center;
	justify-content: center;
}
.daily-case-photo-box img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}
.daily-case-photo-fade {
	position: absolute;
	inset: 0;
	background: linear-gradient(180deg, rgba(255, 255, 255, 0) 60%, rgba(255, 255, 255, 0.95) 100%);
}
.daily-case-body {
	padding: 10px 12px 12px 12px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	justify-content: space-between;
}
.daily-case-num {
	font-family: monospace;
	font-size: 0.72rem;
	font-weight: 700;
	color: #64748b;
	margin-bottom: 2px;
}
.daily-case-pname {
	font-size: 0.84rem;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 4px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.daily-case-cost {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	margin-bottom: 6px;
}
.daily-case-meta {
	font-size: 0.73rem;
	color: #475569;
	margin-bottom: 8px;
	display: flex;
	flex-direction: column;
	gap: 3px;
}
.daily-case-meta-row {
	display: flex;
	align-items: center;
	gap: 4px;
}
.daily-case-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	padding: 6px 10px;
	border-radius: 8px;
	font-size: 0.74rem;
	font-weight: 700;
	border: 1px solid #86efac;
	color: #15803d;
	background: #f0fdf4;
	text-decoration: none !important;
	transition: all 0.15s ease;
}
.daily-case-btn:hover {
	background: #dcfce7;
	border-color: #4ade80;
	color: #166534;
}
.daily-case-btn.is-amber {
	border-color: #fde68a;
	color: #b45309;
	background: #fffbeb;
}
.daily-case-btn.is-amber:hover {
	background: #fef3c7;
	border-color: #fcd34d;
	color: #92400e;
}

/* 4. SECCIÓN MEDIA: 5 PORQUÉS (EJEMPLO) Y EVIDENCIA/VALIDACIÓN */
.daily-inv-mid-layout {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}
@media (max-width: 992px) {
	.daily-inv-mid-layout { grid-template-columns: 1fr; }
}

/* 5 Porqués (Ejemplo) */
.daily-whys-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}
.daily-methodology-pill {
	display: inline-block;
	font-size: 0.64rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #b45309;
	background: #fef3c7;
	padding: 2px 8px;
	border-radius: 6px;
	margin-bottom: 6px;
	width: fit-content;
}
.daily-whys-title {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 4px 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-whys-sub {
	font-size: 0.74rem;
	color: #64748b;
	margin-bottom: 14px;
	line-height: 1.35;
}
.daily-whys-steps-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-bottom: 14px;
}
.daily-whys-step-item {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 12px;
	display: flex;
	align-items: flex-start;
	gap: 10px;
	transition: background 0.15s ease;
	cursor: pointer;
}
.daily-whys-step-item:hover {
	background: #f1f5f9;
}
.daily-whys-num {
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: #cbd5e1;
	color: #0f172a;
	font-size: 0.72rem;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	margin-top: 1px;
}
.daily-whys-step-content {
	flex-grow: 1;
}
.daily-whys-q {
	font-size: 0.78rem;
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 2px;
}
.daily-whys-a {
	font-size: 0.74rem;
	color: #475569;
}
.daily-whys-step-arrow {
	color: #94a3b8;
	font-size: 0.75rem;
	margin-top: 4px;
}

.daily-whys-banner {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: flex-start;
	gap: 10px;
	font-size: 0.75rem;
	color: #92400e;
}

/* Evidencia y Validación Panel */
.daily-evidence-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	display: flex;
	flex-direction: column;
}
.daily-evidence-title {
	font-size: 0.95rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0 0 4px 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-evidence-sub {
	font-size: 0.74rem;
	color: #64748b;
	margin-bottom: 14px;
	line-height: 1.35;
}
.daily-evidence-empty-panel {
	border: 1px dashed #cbd5e1;
	border-radius: 12px;
	padding: 18px;
	text-align: center;
	background: #f8fafc;
	margin-bottom: 12px;
}
.daily-evidence-tiles-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.daily-evidence-tile {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 10px 14px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	cursor: pointer;
	transition: all 0.15s ease;
	text-decoration: none !important;
	color: inherit !important;
}
.daily-evidence-tile:hover {
	border-color: #cbd5e1;
	background: #f8fafc;
	transform: translateX(2px);
}
.daily-evidence-tile-left {
	display: flex;
	align-items: center;
	gap: 10px;
}
.daily-evidence-tile-icon {
	color: #64748b;
	font-size: 0.95rem;
	width: 20px;
	text-align: center;
}
.daily-evidence-tile-title {
	font-size: 0.8rem;
	font-weight: 700;
	color: #0f172a;
}
.daily-evidence-tile-desc {
	font-size: 0.72rem;
	color: #64748b;
}

/* 5. PLANES DE ACCIÓN: TABLERO & TABLA */
.daily-plans-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 14px;
}
.daily-plans-actions-wrap {
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-view-toggle-grp {
	display: inline-flex;
	border: 1px solid #cbd5e1;
	border-radius: 8px;
	overflow: hidden;
}
.daily-view-toggle-btn {
	background: #ffffff;
	border: none;
	padding: 5px 12px;
	font-size: 0.76rem;
	font-weight: 700;
	color: #475569;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	transition: background 0.15s ease, color 0.15s ease;
}
.daily-view-toggle-btn.is-active {
	background: #16a34a;
	color: #ffffff;
}
.daily-btn-new-plan {
	background: #16a34a;
	color: #ffffff;
	border: none;
	border-radius: 8px;
	padding: 6px 14px;
	font-size: 0.78rem;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-btn-new-plan:hover {
	background: #15803d;
	color: #ffffff;
}

/* Tablero Kanban */
.daily-kanban-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
}
@media (max-width: 900px) {
	.daily-kanban-grid { grid-template-columns: 1fr; }
}
.daily-kanban-col {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.daily-kanban-col-head {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.8rem;
	font-weight: 800;
	color: #0f172a;
	margin-bottom: 4px;
}
.daily-kanban-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}
.daily-kanban-dot.dot-amber { background: #f59e0b; }
.daily-kanban-dot.dot-green { background: #16a34a; }
.daily-kanban-dot.dot-darkgreen { background: #15803d; }

.daily-plan-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	gap: 8px;
	transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.daily-plan-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}
.daily-plan-card-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.daily-plan-code {
	font-family: monospace;
	font-size: 0.76rem;
	font-weight: 800;
	color: #0f172a;
}
.daily-plan-title {
	font-size: 0.82rem;
	font-weight: 700;
	color: #0f172a;
	line-height: 1.3;
}
.daily-plan-meta {
	font-size: 0.72rem;
	color: #64748b;
	display: flex;
	flex-direction: column;
	gap: 3px;
}
.daily-plan-meta-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.daily-badge-pill {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 0.68rem;
	font-weight: 700;
	padding: 2px 7px;
	border-radius: 6px;
}
.daily-badge-pill.is-vencido {
	background: #fef2f2;
	color: #dc2626;
	border: 1px solid #fee2e2;
}
.daily-badge-pill.is-cerrado {
	background: #f0fdf4;
	color: #16a34a;
	border: 1px solid #dcfce7;
}
.daily-plan-btns {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 4px;
}
.daily-plan-btn-light {
	flex: 1;
	font-size: 0.72rem;
	font-weight: 700;
	padding: 5px 8px;
	border-radius: 6px;
	border: 1px solid #e2e8f0;
	background: #ffffff;
	color: #334155;
	text-align: center;
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-plan-btn-light:hover { background: #f1f5f9; }
.daily-plan-btn-act {
	flex: 1;
	font-size: 0.72rem;
	font-weight: 700;
	padding: 5px 8px;
	border-radius: 6px;
	border: 1px solid #86efac;
	background: #f0fdf4;
	color: #15803d;
	text-align: center;
	cursor: pointer;
	transition: background 0.15s ease;
}
.daily-plan-btn-act:hover { background: #dcfce7; }

/* Tabla de Planes */
.daily-plans-table-wrap {
	margin-top: 14px;
	border-top: 1px solid #f1f5f9;
	padding-top: 14px;
}
.daily-plans-tbl-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: 10px;
}
.daily-plans-tbl-title {
	font-size: 0.86rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-plans-tbl-filters {
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-plans-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.76rem;
}
.daily-plans-table th {
	background: #f8fafc;
	color: #64748b;
	font-weight: 700;
	text-transform: uppercase;
	font-size: 0.66rem;
	letter-spacing: 0.04em;
	padding: 8px 12px;
	border-bottom: 1px solid #e2e8f0;
}
.daily-plans-table td {
	padding: 10px 12px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-plans-table tr:hover td {
	background: #f8fafc;
}

/* 6. PROCESO DE GESTIÓN (BANNER INFERIOR DE PASOS) */
.daily-process-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 18px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-process-left {
	display: flex;
	align-items: center;
	gap: 10px;
	max-width: 320px;
}
.daily-process-left-text strong {
	font-size: 0.8rem;
	color: #0f172a;
	display: block;
	margin-bottom: 2px;
}
.daily-process-left-text small {
	font-size: 0.7rem;
	color: #64748b;
	line-height: 1.25;
	display: block;
}
.daily-process-steps-row {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.daily-proc-step {
	display: flex;
	align-items: center;
	gap: 6px;
}
.daily-proc-circle {
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: #ffffff;
	border: 1.5px solid #cbd5e1;
	color: #0f172a;
	font-size: 0.72rem;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
}
.daily-proc-text {
	font-size: 0.74rem;
	font-weight: 700;
	color: #0f172a;
}
.daily-proc-sub {
	font-size: 0.64rem;
	color: #64748b;
	display: block;
}
.daily-proc-arr {
	color: #94a3b8;
	font-size: 0.72rem;
	margin: 0 4px;
}

/* 7. MÓDULOS CONECTADOS */
.daily-connected-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 14px;
}
.daily-connected-title {
	font-size: 0.92rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}
.daily-connected-map-link {
	font-size: 0.78rem;
	font-weight: 700;
	color: #64748b;
	text-decoration: none !important;
}
.daily-connected-map-link:hover { color: #0f172a; }
.daily-connected-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 18px;
}
@media (max-width: 900px) {
	.daily-connected-grid { grid-template-columns: 1fr; }
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

<div class="daily-inv-tab" id="dailyInvestigacionesTabContainer">

	<!-- ======================================================================
	     1. ENCABEZADO CON BANNER FOTOGRÁFICO DE LÁCTEOS Y MERMAS
	     ====================================================================== -->
	<div class="daily-inv-header-card">
		<div class="daily-inv-header-left">
			<div class="daily-inv-header-icon">
				<i class="fa-solid fa-trash-can"></i>
			</div>
			<div>
				<h5 class="daily-inv-header-title">Investigación de incidentes y planes de acción</h5>
				<p class="daily-inv-header-sub">
					Análisis profundo de causas raíz y gestión de planes correctivos y preventivos para reducir pérdidas y evitar reincidencias.
				</p>
			</div>
		</div>
		<div class="daily-inv-header-photo">
			<img src="{{ $U('/img/control_center/investigacion_banner.jpg') }}" alt="Góndola de lácteos y productos refrigerados" loading="lazy">
			<div class="daily-inv-header-photo-mask"></div>
		</div>
	</div>

	<!-- ======================================================================
	     2. INDICADORES ESPECÍFICOS (5 TARJETAS COMPACTAS)
	     ====================================================================== -->
	<div class="daily-inv-kpis-grid">
		<!-- 1. Investigaciones Activas -->
		<div class="daily-inv-kpi-card tone-red">
			<div class="daily-inv-kpi-head">
				<span class="daily-inv-kpi-lbl">Investigaciones Activas</span>
				<i class="fa-solid fa-chart-simple text-danger daily-inv-kpi-icon"></i>
			</div>
			<div class="daily-inv-kpi-val">{{ $activeInvestigationsCount }}</div>
			<div class="daily-inv-kpi-sub">Casos en análisis</div>
		</div>

		<!-- 2. Sin Investigador -->
		<div class="daily-inv-kpi-card tone-amber">
			<div class="daily-inv-kpi-head">
				<span class="daily-inv-kpi-lbl">Sin Investigador</span>
				<i class="fa-solid fa-user text-warning daily-inv-kpi-icon"></i>
			</div>
			<div class="daily-inv-kpi-val">{{ $unassignedCount }}</div>
			<div class="daily-inv-kpi-sub">Casos pendientes de asignación</div>
		</div>

		<!-- 3. Planes Activos -->
		<div class="daily-inv-kpi-card tone-green">
			<div class="daily-inv-kpi-head">
				<span class="daily-inv-kpi-lbl">Planes Activos</span>
				<i class="fa-solid fa-clipboard-check text-success daily-inv-kpi-icon"></i>
			</div>
			<div class="daily-inv-kpi-val">{{ $activePlansCount }}</div>
			<div class="daily-inv-kpi-sub">Planes en ejecución</div>
		</div>

		<!-- 4. Planes Vencidos -->
		<div class="daily-inv-kpi-card tone-red">
			<div class="daily-inv-kpi-head">
				<span class="daily-inv-kpi-lbl">Planes Vencidos</span>
				<i class="fa-regular fa-clock text-danger daily-inv-kpi-icon"></i>
			</div>
			<div class="daily-inv-kpi-val">{{ $overduePlansCount }}</div>
			<div class="daily-inv-kpi-sub">Planes con fecha límite superada</div>
		</div>

		<!-- 5. Planes Cerrados -->
		<div class="daily-inv-kpi-card tone-green">
			<div class="daily-inv-kpi-head">
				<span class="daily-inv-kpi-lbl">Planes Cerrados</span>
				<i class="fa-solid fa-circle-check text-success daily-inv-kpi-icon"></i>
			</div>
			<div class="daily-inv-kpi-val">{{ $closedPlansCount }}</div>
			<div class="daily-inv-kpi-sub">Planes finalizados</div>
		</div>
	</div>

	<!-- ======================================================================
	     3. CASOS ACTUALMENTE EN INVESTIGACIÓN (TARJETAS CON FOTOGRAFÍAS)
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-cases-head">
			<div class="daily-cases-title-wrap">
				<i class="fa-regular fa-comment-dots text-success font-size-lg"></i>
				<div>
					<h6 class="daily-cases-title">Casos actualmente en investigación</h6>
					<div class="text-muted small" style="font-size: 0.72rem;">
						Incidentes de merma en análisis. Selecciona un caso para ver su detalle y avanzar en la investigación.
					</div>
				</div>
			</div>
			<div class="daily-cases-filters">
				<input type="text" id="invCaseSearch" class="daily-cases-search" placeholder="Buscar caso, producto..." oninput="dairyFilterCases();">
				<select id="invCaseFilter" class="daily-cases-select" onchange="dairyFilterCases();">
					<option value="all" selected>Todos</option>
					<option value="unassigned">Sin asignar</option>
					<option value="assigned">Asignados</option>
				</select>
			</div>
		</div>

		<div class="daily-cases-grid" id="dailyCasesCardsContainer">
			@forelse($investigationsList as $idx => $c)
				@php
					$isUnassigned = empty($c['investigator_user_id']);
					$imgUrl = $productImg($c);
					$causeDesc = $c['reason_name'] ?? ($c['loss_cause_name'] ?? 'Incidente operativo');
				@endphp
				<div class="daily-case-card {{ $idx === 0 ? 'is-selected' : '' }}" 
				     data-case-id="{{ $c['id'] }}"
				     data-case-num="{{ $c['case_number'] }}"
				     data-unassigned="{{ $isUnassigned ? '1' : '0' }}"
				     data-search-text="{{ mb_strtolower($c['case_number'] . ' ' . $c['product_name'] . ' ' . $causeDesc . ' ' . ($c['investigator_username'] ?? '')) }}"
				     onclick="dairySelectCase({{ $c['id'] }});">
					<div class="daily-case-photo-box">
						<img src="{{ $imgUrl }}" alt="{{ $c['product_name'] }}" loading="lazy">
						<div class="daily-case-photo-fade"></div>
					</div>
					<div class="daily-case-body">
						<div>
							<div class="daily-case-num">{{ $c['case_number'] }}</div>
							<div class="daily-case-pname" title="{{ $c['product_name'] }}">{{ $c['product_name'] }}</div>
							<div class="daily-case-cost">{{ $money($c['total_cost']) }}</div>
							
							<div class="daily-case-meta">
								<div class="daily-case-meta-row">
									<i class="fa-solid fa-user text-muted" style="width: 14px;"></i>
									<span>Investigador:</span>
									@if($isUnassigned)
										<strong class="text-warning">Sin asignar</strong>
									@else
										<strong class="text-dark">{{ $c['investigator_username'] }}</strong>
									@endif
								</div>
								<div class="daily-case-meta-row">
									<i class="fa-solid fa-tag text-muted" style="width: 14px;"></i>
									<span class="text-truncate" title="{{ $causeDesc }}">Causa: {{ $causeDesc }}</span>
								</div>
							</div>
						</div>

						@if($isUnassigned)
							<button type="button" class="daily-case-btn is-amber" onclick="event.stopPropagation(); dairyOpenAssignModal({{ $c['id'] }}, @json($c['case_number']));">
								Asignar responsable <i class="fa-solid fa-arrow-right"></i>
							</button>
						@else
							<button type="button" class="daily-case-btn" onclick="event.stopPropagation(); dairySelectCase({{ $c['id'] }});">
								Continuar investigación <i class="fa-solid fa-arrow-right"></i>
							</button>
						@endif
					</div>
				</div>
			@empty
				<div class="col-12 text-center text-muted py-4">No hay casos en investigación actualmente.</div>
			@endforelse
		</div>
	</div>

	<!-- ======================================================================
	     4. SECCIÓN MEDIA: 5 PORQUÉS (METODOLOGÍA) & EVIDENCIA / VALIDACIÓN
	     ====================================================================== -->
	<div class="daily-inv-mid-layout">

		<!-- Columna Izquierda: Análisis de 5 Porqués (ejemplo metodológico) -->
		<div class="daily-whys-box">
			<div>
				<span class="daily-methodology-pill">Example Methodology</span>
				<h6 class="daily-whys-title">
					<i class="fa-solid fa-sitemap text-success"></i> Análisis de 5 Porqués (ejemplo de metodología)
				</h6>
				<p class="daily-whys-sub">
					Metodología estructurada para identificar causas raíz. Ejemplo ilustrativo, no corresponde a un caso específico del sistema.
				</p>

				<div class="daily-whys-steps-list">
					<!-- 1 -->
					<div class="daily-whys-step-item" onclick="$(this).find('.daily-whys-a').slideToggle(150);">
						<div class="daily-whys-num">1</div>
						<div class="daily-whys-step-content">
							<div class="daily-whys-q">¿Por qué ocurrió la pérdida?</div>
							<div class="daily-whys-a">Rotura de empaque en góndola.</div>
						</div>
						<i class="fa-solid fa-chevron-down daily-whys-step-arrow"></i>
					</div>

					<!-- 2 -->
					<div class="daily-whys-step-item" onclick="$(this).find('.daily-whys-a').slideToggle(150);">
						<div class="daily-whys-num">2</div>
						<div class="daily-whys-step-content">
							<div class="daily-whys-q">¿Por qué se rompió?</div>
							<div class="daily-whys-a">Sobre-apilamiento de cajas en reposición nocturna.</div>
						</div>
						<i class="fa-solid fa-chevron-down daily-whys-step-arrow"></i>
					</div>

					<!-- 3 -->
					<div class="daily-whys-step-item" onclick="$(this).find('.daily-whys-a').slideToggle(150);">
						<div class="daily-whys-num">3</div>
						<div class="daily-whys-step-content">
							<div class="daily-whys-q">¿Por qué se sobre-apiló?</div>
							<div class="daily-whys-a">Falta de espacio asignado en la estantería fría.</div>
						</div>
						<i class="fa-solid fa-chevron-down daily-whys-step-arrow"></i>
					</div>

					<!-- 4 -->
					<div class="daily-whys-step-item" onclick="$(this).find('.daily-whys-a').slideToggle(150);">
						<div class="daily-whys-num">4</div>
						<div class="daily-whys-step-content">
							<div class="daily-whys-q">¿Por qué faltaba espacio?</div>
							<div class="daily-whys-a">Pedido excedió la capacidad del lineal (sobrestock).</div>
						</div>
						<i class="fa-solid fa-chevron-down daily-whys-step-arrow"></i>
					</div>

					<!-- 5 -->
					<div class="daily-whys-step-item" onclick="$(this).find('.daily-whys-a').slideToggle(150);">
						<div class="daily-whys-num">5</div>
						<div class="daily-whys-step-content">
							<div class="daily-whys-q">¿Por qué se pidió en exceso?</div>
							<div class="daily-whys-a">Parámetro MRP de lote mínimo no ajustado a rotación real.</div>
						</div>
						<i class="fa-solid fa-chevron-down daily-whys-step-arrow"></i>
					</div>
				</div>
			</div>

			<div class="daily-whys-banner">
				<i class="fa-solid fa-circle-exclamation text-warning mt-1"></i>
				<div>
					<strong>Causa propuesta en el ejemplo:</strong> desajuste de abastecimiento MRP.<br>
					<span class="text-muted small">Requiere evidencia para validarse en un caso real. No corresponde automáticamente a un caso del sistema.</span>
				</div>
			</div>
		</div>

		<!-- Columna Derecha: Evidencia y Validación de Causas -->
		<div class="daily-evidence-box">
			<div class="daily-evidence-head">
				<h6 class="daily-evidence-title">
					<i class="fa-regular fa-file-lines text-success"></i> Evidencia y validación de causas
				</h6>
				<p class="daily-evidence-sub">
					Revisa y documenta la evidencia para validar la causa raíz del caso seleccionado.
				</p>
			</div>

			<div id="dailyEvidenceDynamicContainer">
				<!-- Panel de Caso Activo -->
				@php $firstCase = $investigationsList[0] ?? null; @endphp
				@if($firstCase)
					<div class="p-3 bg-light rounded border mb-3">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="font-mono font-weight-700 text-dark" id="inspectorCaseNum">{{ $firstCase['case_number'] }}</span>
							<span class="badge badge-light border text-dark font-weight-600" id="inspectorCaseCost">{{ $money($firstCase['total_cost']) }}</span>
						</div>
						<div class="font-weight-700 text-dark mb-1" id="inspectorProductName">{{ $firstCase['product_name'] }}</div>
						<div class="text-muted small mb-2" id="inspectorCaseMeta">
							Investigador: <strong>{{ $firstCase['investigator_username'] ?: 'Sin asignar' }}</strong> · 
							Causal: <strong>{{ $firstCase['reason_name'] }}</strong>
						</div>
						<div class="d-flex gap-2">
							<button type="button" class="btn btn-xs btn-outline-success font-weight-700" onclick="dairyOpenWhysDialog({{ $firstCase['id'] }});">
								<i class="fa-solid fa-pen-to-square mr-1"></i>Investigar 5 Porqués
							</button>
							@if(empty($firstCase['investigator_user_id']))
								<button type="button" class="btn btn-xs btn-outline-warning font-weight-700" onclick="dairyOpenAssignModal({{ $firstCase['id'] }}, @json($firstCase['case_number']));">
									<i class="fa-solid fa-user-plus mr-1"></i>Asignar
								</button>
							@endif
						</div>
					</div>
				@endif

				<div class="daily-evidence-tiles-list">
					<!-- Hechos -->
					<div class="daily-evidence-tile" onclick="dairyShowInspectorSection('hechos');">
						<div class="daily-evidence-tile-left">
							<i class="fa-regular fa-clipboard daily-evidence-tile-icon"></i>
							<div>
								<div class="daily-evidence-tile-title">Hechos</div>
								<div class="daily-evidence-tile-desc">¿Qué sucedió? Registra los eventos observados.</div>
							</div>
						</div>
						<i class="fa-solid fa-chevron-right text-muted font-size-sm"></i>
					</div>

					<!-- Evidencias -->
					<div class="daily-evidence-tile" onclick="dairyShowInspectorSection('evidencias');">
						<div class="daily-evidence-tile-left">
							<i class="fa-regular fa-folder-open daily-evidence-tile-icon"></i>
							<div>
								<div class="daily-evidence-tile-title">Evidencias</div>
								<div class="daily-evidence-tile-desc">Documenta y adjunta la evidencia disponible.</div>
							</div>
						</div>
						<i class="fa-solid fa-chevron-right text-muted font-size-sm"></i>
					</div>

					<!-- Hipótesis -->
					<div class="daily-evidence-tile" onclick="dairyShowInspectorSection('hipotesis');">
						<div class="daily-evidence-tile-left">
							<i class="fa-solid fa-lightbulb daily-evidence-tile-icon"></i>
							<div>
								<div class="daily-evidence-tile-title">Hipótesis</div>
								<div class="daily-evidence-tile-desc">Posibles causas que explican el evento.</div>
							</div>
						</div>
						<i class="fa-solid fa-chevron-right text-muted font-size-sm"></i>
					</div>

					<!-- Causa raíz validada -->
					<div class="daily-evidence-tile" onclick="dairyShowInspectorSection('conclusion');">
						<div class="daily-evidence-tile-left">
							<i class="fa-solid fa-bullseye daily-evidence-tile-icon"></i>
							<div>
								<div class="daily-evidence-tile-title">Causa raíz validada</div>
								<div class="daily-evidence-tile-desc">Conclusión final con sustento en la evidencia.</div>
							</div>
						</div>
						<i class="fa-solid fa-chevron-right text-muted font-size-sm"></i>
					</div>
				</div>
			</div>
		</div>

	</div>

	<!-- ======================================================================
	     5. PLANES DE ACCIÓN CORRECTIVA Y PREVENTIVA (TABLERO & TABLA)
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-plans-head">
			<div>
				<h6 class="daily-whys-title">
					<i class="fa-solid fa-list-check text-success"></i> Planes de acción correctiva y preventiva
				</h6>
				<p class="daily-whys-sub mb-0">Gestión de planes para eliminar causas raíz y prevenir reincidencias.</p>
			</div>

			<div class="daily-plans-actions-wrap">
				<!-- Selector Tablero / Tabla -->
				<div class="daily-view-toggle-grp" role="group">
					<button type="button" id="btnToggleKanban" class="daily-view-toggle-btn is-active" onclick="dairySwitchPlansView('kanban');">
						<i class="fa-solid fa-table-columns"></i> Tablero
					</button>
					<button type="button" id="btnToggleTable" class="daily-view-toggle-btn" onclick="dairySwitchPlansView('table');">
						<i class="fa-solid fa-list"></i> Tabla
					</button>
					<button type="button" id="btnToggleTimeline" class="daily-view-toggle-btn" onclick="dairySwitchPlansView('timeline');">
						<i class="fa-solid fa-timeline"></i> Línea de tiempo
					</button>
				</div>

				@if($canPln)
					<button type="button" class="daily-btn-new-plan" onclick="dairyNewPlanDialog();">
						<i class="fa-solid fa-plus"></i> Nuevo plan
					</button>
				@endif
			</div>
		</div>

		<!-- VISTA 1: TABLERO KANBAN (3 COLUMNAS) -->
		<div id="dailyPlansKanbanView" class="daily-kanban-grid">
			@php
				$openPlans = array_filter($actionPlansList, fn($p) => $p['status'] === 'open');
				$inProgPlans = array_filter($actionPlansList, fn($p) => $p['status'] === 'in_progress');
				$donePlans = array_filter($actionPlansList, fn($p) => $p['status'] === 'completed');
			@endphp

			<!-- Columna 1: Abierto -->
			<div class="daily-kanban-col">
				<div class="daily-kanban-col-head">
					<span class="daily-kanban-dot dot-amber"></span>
					<span>Abierto ({{ count($openPlans) }})</span>
				</div>
				@forelse($openPlans as $p)
					@php
						$isOv = !empty($p['due_date']) && $p['due_date'] < $today;
					@endphp
					<div class="daily-plan-card" data-plan-id="{{ $p['id'] }}">
						<div class="daily-plan-card-head">
							<span class="daily-plan-code">AP-{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</span>
							<i class="fa-solid fa-arrows-rotate text-muted small" title="En seguimiento"></i>
						</div>
						<div class="daily-plan-title">{{ $p['activity_title'] }}</div>
						<div class="daily-plan-meta">
							<div class="daily-plan-meta-row">
								<span>Responsable</span>
								<strong class="text-dark">{{ $p['assigned_username'] }}</strong>
							</div>
							<div class="daily-plan-meta-row">
								<span>Sucursal</span>
								<span>{{ $p['branch_name'] }}</span>
							</div>
							<div class="daily-plan-meta-row">
								<span>Fecha límite</span>
								<span class="{{ $isOv ? 'text-danger font-weight-700' : '' }}">{{ $p['due_date'] }}</span>
								@if($isOv)
									<span class="daily-badge-pill is-vencido"><i class="fa-regular fa-clock"></i> Vencido</span>
								@endif
							</div>
						</div>
						<div class="daily-plan-btns">
							<button type="button" class="daily-plan-btn-light" onclick="dairyViewPlanDetail({{ $p['id'] }});">Ver plan</button>
							<button type="button" class="daily-plan-btn-act" onclick="dairyUpdatePlanProgress({{ $p['id'] }});">Registrar avance</button>
						</div>
					</div>
				@empty
					<div class="text-center text-muted small py-3">Sin planes abiertos</div>
				@endforelse
			</div>

			<!-- Columna 2: En curso -->
			<div class="daily-kanban-col">
				<div class="daily-kanban-col-head">
					<span class="daily-kanban-dot dot-green"></span>
					<span>En curso ({{ count($inProgPlans) }})</span>
				</div>
				@forelse($inProgPlans as $p)
					@php
						$isOv = !empty($p['due_date']) && $p['due_date'] < $today;
					@endphp
					<div class="daily-plan-card" data-plan-id="{{ $p['id'] }}">
						<div class="daily-plan-card-head">
							<span class="daily-plan-code">AP-{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</span>
							<i class="fa-solid fa-arrows-rotate text-muted small" title="En ejecución"></i>
						</div>
						<div class="daily-plan-title">{{ $p['activity_title'] }}</div>
						<div class="daily-plan-meta">
							<div class="daily-plan-meta-row">
								<span>Responsable</span>
								<strong class="text-dark">{{ $p['assigned_username'] }}</strong>
							</div>
							<div class="daily-plan-meta-row">
								<span>Sucursal</span>
								<span>{{ $p['branch_name'] }}</span>
							</div>
							<div class="daily-plan-meta-row">
								<span>Fecha límite</span>
								<span class="{{ $isOv ? 'text-danger font-weight-700' : '' }}">{{ $p['due_date'] }}</span>
								@if($isOv)
									<span class="daily-badge-pill is-vencido"><i class="fa-regular fa-clock"></i> Vencido</span>
								@endif
							</div>
						</div>
						<div class="daily-plan-btns">
							<button type="button" class="daily-plan-btn-light" onclick="dairyViewPlanDetail({{ $p['id'] }});">Ver plan</button>
							<button type="button" class="daily-plan-btn-act" onclick="dairyUpdatePlanProgress({{ $p['id'] }});">Registrar avance</button>
						</div>
					</div>
				@empty
					<div class="text-center text-muted small py-3">Sin planes en curso</div>
				@endforelse
			</div>

			<!-- Columna 3: Cerrado -->
			<div class="daily-kanban-col">
				<div class="daily-kanban-col-head">
					<span class="daily-kanban-dot dot-darkgreen"></span>
					<span>Cerrado ({{ count($donePlans) }})</span>
				</div>
				@forelse($donePlans as $p)
					<div class="daily-plan-card" data-plan-id="{{ $p['id'] }}">
						<div class="daily-plan-card-head">
							<span class="daily-plan-code">AP-{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</span>
							<span class="daily-badge-pill is-cerrado"><i class="fa-solid fa-check"></i> Cerrado</span>
						</div>
						<div class="daily-plan-title">{{ $p['activity_title'] }}</div>
						<div class="daily-plan-meta">
							<div class="daily-plan-meta-row">
								<span>Responsable</span>
								<strong class="text-dark">{{ $p['assigned_username'] }}</strong>
							</div>
							<div class="daily-plan-meta-row">
								<span>Sucursal</span>
								<span>{{ $p['branch_name'] }}</span>
							</div>
							<div class="daily-plan-meta-row">
								<span>Fecha límite</span>
								<span>{{ $p['due_date'] }}</span>
							</div>
						</div>
						<div class="daily-plan-btns">
							<button type="button" class="daily-plan-btn-light w-100" onclick="dairyViewPlanDetail({{ $p['id'] }});">Ver cierre</button>
						</div>
					</div>
				@empty
					<div class="text-center text-muted small py-3">Sin planes cerrados</div>
				@endforelse
			</div>
		</div>

		<!-- VISTA 2: TABLA DE PLANES (LISTADO) -->
		<div id="dailyPlansTableView" class="daily-plans-table-wrap" style="display: none;">
			<div class="daily-plans-tbl-head">
				<h6 class="daily-plans-tbl-title">
					<i class="fa-solid fa-table-list text-success"></i> Listado de planes de acción
				</h6>
				<div class="daily-plans-tbl-filters">
					<input type="text" id="planSearchInput" class="daily-cases-search" placeholder="Buscar código o acción..." oninput="dairyFilterPlansTable();">
					<select id="planStatusFilter" class="daily-cases-select" onchange="dairyFilterPlansTable();">
						<option value="all">Todos los estados</option>
						<option value="open">Abierto</option>
						<option value="in_progress">En curso</option>
						<option value="completed">Cerrado</option>
						<option value="overdue">Vencidos</option>
					</select>
					<select id="planBranchFilter" class="daily-cases-select" onchange="dairyFilterPlansTable();">
						<option value="all">Todas las sucursales</option>
						@foreach($branchesList as $br)
							<option value="{{ $br['name'] }}">{{ $br['name'] }}</option>
						@endforeach
					</select>
				</div>
			</div>

			<div class="table-responsive">
				<table class="daily-plans-table" id="dailyActionPlansTable">
					<thead>
						<tr>
							<th>CÓDIGO</th>
							<th>ACCIÓN PREVENTIVA / CORRECTIVA</th>
							<th>RESPONSABLE</th>
							<th>SUCURSAL</th>
							<th>FECHA LÍMITE</th>
							<th>ESTADO</th>
							<th>PLAZO</th>
							<th class="text-right">DETALLE</th>
						</tr>
					</thead>
					<tbody>
						@forelse($actionPlansList as $p)
							@php
								$isDone = ($p['status'] === 'completed');
								$isOv = !$isDone && (!empty($p['due_date']) && $p['due_date'] < $today);
								$statusName = ['open' => 'Abierto', 'in_progress' => 'En curso', 'completed' => 'Cerrado'][$p['status']] ?? $p['status'];
							@endphp
							<tr data-status="{{ $p['status'] }}" data-is-overdue="{{ $isOv ? '1' : '0' }}" data-branch="{{ $p['branch_name'] }}" data-search="{{ mb_strtolower($p['activity_title'] . ' ' . $p['assigned_username']) }}">
								<td class="font-mono font-weight-700">AP-{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</td>
								<td class="font-weight-600 text-dark">{{ $p['activity_title'] }}</td>
								<td>{{ $p['assigned_username'] }}</td>
								<td>{{ $p['branch_name'] }}</td>
								<td class="font-mono {{ $isOv ? 'text-danger font-weight-700' : '' }}">{{ $p['due_date'] }}</td>
								<td>
									@if($isDone)
										<span class="daily-badge-pill is-cerrado"><i class="fa-solid fa-check"></i> Cerrado</span>
									@elseif($isOv)
										<span class="daily-badge-pill is-vencido"><i class="fa-regular fa-clock"></i> {{ $statusName }}</span>
									@else
										<span class="badge badge-light border text-dark">{{ $statusName }}</span>
									@endif
								</td>
								<td>
									@if($isDone)
										<span class="text-success font-weight-600">Completado</span>
									@elseif($isOv)
										<span class="text-danger font-weight-700">Vencido</span>
									@else
										<span class="text-muted">En plazo</span>
									@endif
								</td>
								<td class="text-right">
									<button type="button" class="btn btn-xs btn-light border" title="Ver plan" onclick="dairyViewPlanDetail({{ $p['id'] }});">
										<i class="fa-regular fa-file-lines text-muted"></i>
									</button>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="8" class="text-center text-muted py-4">No hay planes registrados.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>

		<!-- VISTA 3: LÍNEA DE TIEMPO (GANTT DE HITOS, DEPENDENCIAS E HISTORIAL DE CAMBIOS) -->
		@php
			$planDetailsMap = [
				1 => [
					'incident_ref' => 'INC-2026-004 · Fuga de frío en vitrina lácteas #2',
					'objective' => 'Eliminar merma térmica nocturna y estabilizar temperatura en vitrinas entre 2°C y 5°C.',
					'milestones' => [
						['id' => 1, 'title' => 'Instalación física de cortinas térmicas de noche en vitrinas 1 y 2', 'owner' => 'Bodeguero / Mant.', 'due' => '2026-10-10', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Calibración técnica de sonda digital y termostatos con técnico autorizado', 'owner' => 'Técnico Frío', 'due' => '2026-10-13', 'status' => 'in_progress', 'progress' => 60, 'depends_on' => 'Hito 1: Instalación de cortinas'],
						['id' => 3, 'title' => 'Protocolo de rondas nocturnas 22:00 y 06:00 con registro digital', 'owner' => 'Auxiliar de piso', 'due' => '2026-10-15', 'status' => 'pending', 'progress' => 0, 'depends_on' => 'Hito 2: Calibración técnica']
					],
					'history' => [
						['date' => '2026-10-11', 'type' => 'Fecha Límite', 'detail' => 'Fecha límite reprogramada del 13/10 al 15/10 por demora en entrega de sonda de repuesto.', 'author' => 'Supervisor de Turno'],
						['date' => '2026-10-08', 'type' => 'Creación', 'detail' => 'Plan de acción formulado y vinculado a investigación de causa raíz INC-2026-004.', 'author' => 'Gerente de Sucursal']
					]
				],
				2 => [
					'incident_ref' => 'INV-2026-008 · Vencimiento anticipado por rotación deficiente en góndola',
					'objective' => 'Alcanzar cero mermas por vencimiento de productos lácteos mediante rotación FEFO estricta y liquidación oportuna (-30%).',
					'milestones' => [
						['id' => 1, 'title' => 'Capacitación presencial al personal de reposición sobre protocolo FEFO', 'owner' => 'Supervisor', 'due' => '2026-10-05', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Implantación del checklist diario de barrido FEFO en Piso de Tienda', 'owner' => 'Reponedor', 'due' => '2026-10-08', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Hito 1: Capacitación'],
						['id' => 3, 'title' => 'Validación de 7 días consecutivos sin producto vencido en góndola', 'owner' => 'Gerente', 'due' => '2026-10-12', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Hito 2: Checklist diario']
					],
					'history' => [
						['date' => '2026-10-12', 'type' => 'Cierre', 'detail' => 'Plan completado y verificado con 100% de cumplimiento en checklists de rotación.', 'author' => 'Gerente de Sucursal'],
						['date' => '2026-10-03', 'type' => 'Creación', 'detail' => 'Plan originado tras informe de pérdidas por caducidad en yogures.', 'author' => 'Control de Gestión']
					]
				],
				3 => [
					'incident_ref' => 'INV-2026-012 · Aplastamiento de embalaje y fisura en quesos por sobreestiba',
					'objective' => 'Reducir a cero el daño físico en cajas de queso limitando la estiba a 4 niveles y usando esquineros reforzados.',
					'milestones' => [
						['id' => 1, 'title' => 'Diseño e instalación de señalización de altura máxima en muros de cámara', 'owner' => 'Supervisor', 'due' => '2026-10-15', 'status' => 'completed', 'progress' => 100, 'depends_on' => 'Ninguna (Inicio)'],
						['id' => 2, 'title' => 'Suministro y colocación obligatoria de esquineros de cartón en tarimas', 'owner' => 'Bodeguero', 'due' => '2026-10-18', 'status' => 'in_progress', 'progress' => 50, 'depends_on' => 'Hito 1: Señalización'],
						['id' => 3, 'title' => 'Auditoría semanal de recepción en andén para verificar estiba del proveedor', 'owner' => 'Calidad', 'due' => '2026-10-20', 'status' => 'pending', 'progress' => 0, 'depends_on' => 'Hito 2: Esquineros']
					],
					'history' => [
						['date' => '2026-10-14', 'type' => 'Objetivo', 'detail' => 'Ajuste de especificación: se autoriza estiba de 4 niveles siempre que cuente con esquineros de protección.', 'author' => 'Jefe de Bodega'],
						['date' => '2026-10-13', 'type' => 'Creación', 'detail' => 'Plan iniciado tras detectar cajas aplastadas en cámara fría.', 'author' => 'Supervisor de Turno']
					]
				]
			];
		@endphp
		<div id="dailyPlansTimelineView" class="p-3" style="display: none;">
			<div class="mb-3 d-flex justify-content-between align-items-center">
				<div>
					<h6 class="font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-timeline text-success mr-2"></i>Línea de Tiempo de Planes, Hitos y Dependencias
					</h6>
					<p class="text-muted small mb-0">Seguimiento cronológico de tareas por plan, dependencias entre hitos e historial de cambios.</p>
				</div>
				<span class="badge badge-light border text-muted">Vista Gantt Operativa</span>
			</div>

			@foreach($actionPlansList as $p)
			@php
				$pId = $p['id'];
				$details = $planDetailsMap[$pId] ?? [
					'incident_ref' => 'Incidencia vinculada en proceso',
					'objective' => $p['follow_up_actions'] ?? 'Seguimiento preventivo',
					'milestones' => [
						['id' => 1, 'title' => $p['activity_title'], 'owner' => $p['assigned_username'], 'due' => $p['due_date'], 'status' => $p['status'], 'progress' => $p['status'] === 'completed' ? 100 : 50, 'depends_on' => 'Ninguna']
					],
					'history' => []
				];
				$isDone = ($p['status'] === 'completed');
				$isOv = !$isDone && (!empty($p['due_date']) && $p['due_date'] < $today);
			@endphp
			<div class="card border shadow-xs mb-3 bg-white">
				<div class="card-header bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center">
					<div class="d-flex align-items-center flex-wrap">
						<span class="font-mono font-weight-700 text-dark mr-2">AP-{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</span>
						<strong class="text-dark mr-2">{{ $p['activity_title'] }}</strong>
						@if($isDone)
							<span class="badge badge-success mr-2"><i class="fa-solid fa-check mr-1"></i>Cerrado</span>
						@elseif($isOv)
							<span class="badge badge-danger mr-2"><i class="fa-solid fa-clock mr-1"></i>Vencido</span>
						@else
							<span class="badge badge-info mr-2">En curso</span>
						@endif
						<span class="badge badge-light border text-muted">Sucursal: {{ $p['branch_name'] }}</span>
					</div>
					<div class="text-muted small mt-1 mt-sm-0">
						<i class="fa-solid fa-shield-halved text-success mr-1"></i>Ahorro estimado: <strong>${{ number_format(floatval($p['cost_avoided_estimate'] ?? 0), 2) }}</strong>
					</div>
				</div>

				<div class="card-body p-3">
					<!-- Origen y Objetivo del Plan -->
					<div class="bg-light p-2 rounded mb-3 small d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
						<div>
							<i class="fa-solid fa-link text-primary mr-1"></i>
							<strong>Origen / Causa raíz:</strong>
							<span class="text-dark font-weight-600">{{ $details['incident_ref'] }}</span>
						</div>
						<div>
							<i class="fa-solid fa-bullseye text-danger mr-1"></i>
							<strong>Objetivo:</strong>
							<span class="text-muted">{{ $details['objective'] }}</span>
						</div>
					</div>

					<!-- Desglose de Hitos con Dependencias y Estado -->
					<h6 class="small font-weight-bold text-dark text-uppercase mb-2">
						<i class="fa-solid fa-bars-progress text-primary mr-1"></i>Hitos y Dependencias del Plan
					</h6>
					<div class="table-responsive mb-3">
						<table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
							<thead class="thead-light">
								<tr>
									<th style="width: 60px;">HITO</th>
									<th>ACTIVIDAD / TAREA</th>
									<th>RESPONSABLE</th>
									<th>FECHA LÍMITE</th>
									<th>DEPENDENCIA</th>
									<th style="width: 130px;">PROGRESO</th>
									<th class="text-center" style="width: 110px;">ESTADO</th>
								</tr>
							</thead>
							<tbody>
								@foreach($details['milestones'] as $m)
								<tr>
									<td class="text-center font-weight-bold font-mono">#{{ $m['id'] }}</td>
									<td class="font-weight-600 text-dark">{{ $m['title'] }}</td>
									<td class="text-muted">{{ $m['owner'] }}</td>
									<td class="font-mono text-muted">{{ $m['due'] }}</td>
									<td>
										<span class="badge badge-light border text-muted">
											<i class="fa-solid fa-diagram-next mr-1"></i>{{ $m['depends_on'] }}
										</span>
									</td>
									<td>
										<div class="d-flex align-items-center">
											<div class="progress flex-grow-1 mr-2" style="height: 6px;">
												<div class="progress-bar {{ $m['status'] === 'completed' ? 'bg-success' : ($m['status'] === 'in_progress' ? 'bg-info' : 'bg-secondary') }}" style="width: {{ $m['progress'] }}%;"></div>
											</div>
											<span class="small font-mono">{{ $m['progress'] }}%</span>
										</div>
									</td>
									<td class="text-center">
										@if($m['status'] === 'completed')
											<span class="badge badge-success"><i class="fa-solid fa-check mr-1"></i>Completado</span>
										@elseif($m['status'] === 'in_progress')
											<span class="badge badge-info"><i class="fa-solid fa-spinner fa-spin mr-1"></i>En curso</span>
										@else
											<span class="badge badge-secondary">Pendiente</span>
										@endif
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>

					<!-- Historial de Cambios de Fecha u Objetivo -->
					@if(!empty($details['history']))
					<div class="border-top pt-2">
						<a class="small font-weight-bold text-muted" data-toggle="collapse" href="#history-plan-{{ $p['id'] }}" role="button" aria-expanded="false">
							<i class="fa-solid fa-clock-rotate-left mr-1 text-primary"></i>Historial de cambios de fecha u objetivo ({{ count($details['history']) }} registros) <i class="fa-solid fa-chevron-down ml-1"></i>
						</a>
						<div class="collapse mt-2" id="history-plan-{{ $p['id'] }}">
							<div class="list-group list-group-flush border rounded bg-light" style="font-size: 11px;">
								@foreach($details['history'] as $h)
								<div class="list-group-item py-1 px-3 bg-transparent d-flex justify-content-between align-items-center">
									<div>
										<span class="font-mono text-muted mr-2">{{ $h['date'] }}</span>
										<span class="badge badge-light border mr-2">{{ $h['type'] }}</span>
										<span>{{ $h['detail'] }}</span>
									</div>
									<span class="text-muted">Por: <strong>{{ $h['author'] }}</strong></span>
								</div>
								@endforeach
							</div>
						</div>
					</div>
					@endif
				</div>
			</div>
			@endforeach
		</div>
	</div>

	<!-- ======================================================================
	     6. PROCESO DE GESTIÓN DE INCIDENTES Y PLANES
	     ====================================================================== -->
	<div class="daily-process-card">
		<div class="daily-process-left">
			<i class="fa-solid fa-gear text-muted fa-lg"></i>
			<div class="daily-process-left-text">
				<strong>Proceso de gestión de incidentes y planes</strong>
				<small>Del incidente a la prevención. El cierre de un plan no garantiza por sí solo la efectividad de la prevención.</small>
			</div>
		</div>

		<div class="daily-process-steps-row">
			<!-- Paso 1 -->
			<div class="daily-proc-step">
				<div class="daily-proc-circle">1</div>
				<div>
					<div class="daily-proc-text">Registrar incidente</div>
					<small class="daily-proc-sub">Captura y clasificación</small>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right daily-proc-arr"></i>

			<!-- Paso 2 -->
			<div class="daily-proc-step">
				<div class="daily-proc-circle" style="border-color: #16a34a; color: #16a34a;">2</div>
				<div>
					<div class="daily-proc-text text-success">Investigar</div>
					<small class="daily-proc-sub">Análisis de causas</small>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right daily-proc-arr"></i>

			<!-- Paso 3 -->
			<div class="daily-proc-step">
				<div class="daily-proc-circle">3</div>
				<div>
					<div class="daily-proc-text">Validar causa</div>
					<small class="daily-proc-sub">Evidencia y conclusión</small>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right daily-proc-arr"></i>

			<!-- Paso 4 -->
			<div class="daily-proc-step">
				<div class="daily-proc-circle">4</div>
				<div>
					<div class="daily-proc-text">Ejecutar plan</div>
					<small class="daily-proc-sub">Acciones correctivas</small>
				</div>
			</div>
			<i class="fa-solid fa-chevron-right daily-proc-arr"></i>

			<!-- Paso 5 -->
			<div class="daily-proc-step">
				<div class="daily-proc-circle">5</div>
				<div>
					<div class="daily-proc-text">Verificar eficacia</div>
					<small class="daily-proc-sub">Seguimiento y monitoreo</small>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     7. MÓDULOS CONECTADOS CON CENTRO DE CONTROL
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
					<a href="{{ $U('/quality') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon"><i class="fa-solid fa-snowflake"></i></div>
							<div>
								<div class="daily-conn-item-name">Calidad & Vencimientos</div>
								<div class="daily-conn-item-sub">Rebajas por vencimiento (prevención antes de la merma)</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
					<a href="{{ $U('/workforce') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon"><i class="fa-solid fa-list-check"></i></div>
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
					<a href="{{ $U('/today') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon"><i class="fa-solid fa-chart-line"></i></div>
							<div>
								<div class="daily-conn-item-name">Panel Ejecutivo</div>
								<div class="daily-conn-item-sub">Merma en el Panel Ejecutivo</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
					<a href="{{ $U('/cost-centers') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon"><i class="fa-solid fa-building-columns"></i></div>
							<div>
								<div class="daily-conn-item-name">Centros de Costo</div>
								<div class="daily-conn-item-sub">Merma por centro de costo</div>
							</div>
						</div>
						<div class="daily-conn-item-arrow"><i class="fa-solid fa-arrow-right"></i></div>
					</a>
					<a href="{{ $U('/reports') }}" class="daily-conn-item">
						<div class="daily-conn-item-left">
							<div class="daily-conn-item-icon"><i class="fa-solid fa-file-waveform"></i></div>
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
     SCRIPTS & LÓGICA INTERACTIVA DEL TAB DE INVESTIGACIONES Y PLANES
     ========================================================================== -->
<script>
(function() {
	// Ocultar bloque duplicado del layout global si estuviese presente
	document.querySelectorAll('.dairy-related').forEach(function(el) {
		el.style.setProperty('display', 'none', 'important');
	});

	// Almacenar casos y planes cargados en memoria
	window.DairyInvData = {
		cases: {!! json_encode($investigationsList) !!},
		plans: {!! json_encode($actionPlansList) !!},
		users: {!! json_encode($usersList) !!},
		selectedCaseId: {{ !empty($investigationsList[0]['id']) ? $investigationsList[0]['id'] : 'null' }}
	};
})();

// Alternar entre Tablero, Tabla y Línea de Tiempo
function dairySwitchPlansView(view) {
	$('#dailyPlansKanbanView').hide();
	$('#dailyPlansTableView').hide();
	$('#dailyPlansTimelineView').hide();
	$('#btnToggleKanban').removeClass('is-active');
	$('#btnToggleTable').removeClass('is-active');
	$('#btnToggleTimeline').removeClass('is-active');

	if (view === 'kanban') {
		$('#dailyPlansKanbanView').show();
		$('#btnToggleKanban').addClass('is-active');
	} else if (view === 'timeline') {
		$('#dailyPlansTimelineView').show();
		$('#btnToggleTimeline').addClass('is-active');
	} else {
		$('#dailyPlansTableView').show();
		$('#btnToggleTable').addClass('is-active');
	}
}

// Filtrar tarjetas de casos
function dairyFilterCases() {
	var q = $.trim($('#invCaseSearch').val()).toLowerCase();
	var filter = $('#invCaseFilter').val();

	$('#dailyCasesCardsContainer .daily-case-card').each(function() {
		var $card = $(this);
		var matchSearch = !q || $card.data('search-text').indexOf(q) !== -1;
		var isUnassigned = $card.data('unassigned') == '1';

		var matchFilter = true;
		if (filter === 'unassigned') matchFilter = isUnassigned;
		if (filter === 'assigned') matchFilter = !isUnassigned;

		if (matchSearch && matchFilter) {
			$card.show();
		} else {
			$card.hide();
		}
	});
}

// Filtrar tabla de planes
function dairyFilterPlansTable() {
	var q = $.trim($('#planSearchInput').val()).toLowerCase();
	var st = $('#planStatusFilter').val();
	var br = $('#planBranchFilter').val();

	$('#dailyActionPlansTable tbody tr').each(function() {
		var $r = $(this);
		var rowStatus = $r.data('status');
		var isOv = $r.data('is-overdue') == '1';
		var rowBranch = $r.data('branch');
		var searchTxt = $r.data('search') || '';

		var matchQ = !q || searchTxt.indexOf(q) !== -1;
		var matchBr = (br === 'all' || rowBranch === br);

		var matchSt = true;
		if (st === 'overdue') matchSt = isOv;
		else if (st !== 'all') matchSt = (rowStatus === st);

		if (matchQ && matchSt && matchBr) {
			$r.show();
		} else {
			$r.hide();
		}
	});
}

// Seleccionar caso para inspeccionar en la columna derecha
function dairySelectCase(caseId) {
	window.DairyInvData.selectedCaseId = caseId;
	$('.daily-case-card').removeClass('is-selected');
	$('.daily-case-card[data-case-id="' + caseId + '"]').addClass('is-selected');

	var c = window.DairyInvData.cases.find(function(item) { return item.id == caseId; });
	if (!c) return;

	$('#inspectorCaseNum').text(c.case_number);
	$('#inspectorCaseCost').text('$' + Number(c.total_cost).toFixed(2));
	$('#inspectorProductName').text(c.product_name);
	$('#inspectorCaseMeta').html(
		'Investigador: <strong>' + (c.investigator_username || 'Sin asignar') + '</strong> · ' +
		'Causal: <strong>' + (c.reason_name || c.loss_cause_name || 'Incidente') + '</strong>'
	);
}

// Diálogo interactivo de Asignar Responsable
function dairyOpenAssignModal(caseId, caseNumber) {
	var userOpts = window.DairyInvData.users.map(function(u) {
		return '<option value="' + u.id + '">' + u.username + (u.first_name ? ' (' + u.first_name + ' ' + u.last_name + ')' : '') + '</option>';
	}).join('');

	bootbox.dialog({
		title: 'Asignar Investigador: ' + caseNumber,
		message: '<div class="form-group">' +
			'<label class="small font-weight-700">Seleccionar Usuario Responsable:</label>' +
			'<select id="assignUserSelect" class="form-control form-control-sm">' + userOpts + '</select>' +
			'</div>',
		buttons: {
			cancel: { label: 'Cancelar', className: 'btn-secondary btn-sm' },
			save: {
				label: 'Asignar Responsable',
				className: 'btn-primary btn-sm font-weight-700',
				callback: function() {
					var uid = $('#assignUserSelect').val();
					dairyPost('/waste/cases/' + caseId + '/investigation', {
						investigator_user_id: uid,
						workflow_status: 'investigating'
					}, 'Responsable asignado correctamente', 'No se pudo asignar el responsable');
				}
			}
		}
	});
}

// Diálogo interactivo de 5 Porqués del caso
function dairyOpenWhysDialog(caseId) {
	var c = window.DairyInvData.cases.find(function(item) { return item.id == caseId; });
	if (!c) return;

	openInvestigateModal(c.id, c.case_number, c.product_name, c.total_cost);
}

// Diálogo de Ver Plan en Detalle
function dairyViewPlanDetail(planId) {
	var p = window.DairyInvData.plans.find(function(item) { return item.id == planId; });
	if (!p) return;

	var isClosed = p.status === 'completed';
	var html = '<div class="dairy-fs-sm">' +
		'<div class="mb-2"><strong>Acción:</strong> ' + p.activity_title + '</div>' +
		'<div class="mb-2"><strong>Problema detectado:</strong> ' + (p.problem_description || '—') + '</div>' +
		'<div class="mb-2"><strong>Seguimiento propuesto:</strong> ' + (p.follow_up_actions || '—') + '</div>' +
		'<div class="row mb-2">' +
			'<div class="col-6"><strong>Responsable:</strong> ' + p.assigned_username + '</div>' +
			'<div class="col-6"><strong>Sucursal:</strong> ' + p.branch_name + '</div>' +
		'</div>' +
		'<div class="row mb-2">' +
			'<div class="col-6"><strong>Fecha límite:</strong> ' + p.due_date + '</div>' +
			'<div class="col-6"><strong>Estado:</strong> ' + (isClosed ? 'Cerrado' : (p.status === 'in_progress' ? 'En curso' : 'Abierto')) + '</div>' +
		'</div>' +
		(p.evidence_notes ? '<div class="alert alert-light border mt-2"><strong>Evidencia de cumplimiento:</strong><br>' + p.evidence_notes + '</div>' : '') +
		'</div>';

	var btns = {
		close: { label: 'Cerrar ventana', className: 'btn-secondary btn-sm' }
	};

	if (!isClosed) {
		btns.closePlan = {
			label: 'Cerrar Plan con Evidencia',
			className: 'btn-success btn-sm font-weight-700',
			callback: function() {
				dairyClosePlan(planId);
			}
		};
	}

	bootbox.dialog({
		title: 'Plan de Acción: AP-' + String(p.id).padStart(4, '0'),
		message: html,
		buttons: btns
	});
}

// Diálogo de Registrar Avance del Plan
function dairyUpdatePlanProgress(planId) {
	bootbox.prompt({
		title: 'Registrar Avance / Notas de Seguimiento',
		inputType: 'textarea',
		placeholder: 'Describa el avance operativo o cumplimiento preliminar...',
		callback: function(notes) {
			if (notes === null || !$.trim(notes)) return;
			dairyPost('/waste/action-plans/' + planId + '/update', {
				status: 'in_progress',
				evidence_notes: notes
			}, 'Avance registrado en el plan', 'No se pudo registrar el avance');
		}
	});
}

// Mostrar secciones de detalle del caso activo
function dairyShowInspectorSection(sec) {
	var c = window.DairyInvData.cases.find(function(item) { return item.id == window.DairyInvData.selectedCaseId; });
	if (!c) {
		toastr.info('Seleccione un caso de la lista superior para inspeccionar.');
		return;
	}

	if (sec === 'hechos') {
		bootbox.alert({
			title: 'Hechos Observados: ' + c.case_number,
			message: '<p><strong>Producto:</strong> ' + c.product_name + ' ($' + Number(c.total_cost).toFixed(2) + ')</p>' +
				'<p><strong>Fecha del incidente:</strong> ' + (c.created_timestamp || '—') + '</p>' +
				'<p><strong>Lote:</strong> ' + (c.batch_number || 'Sin lote') + ' · <strong>Vencimiento:</strong> ' + (c.best_before_date || '—') + '</p>' +
				'<p><strong>Notas de registro:</strong> ' + (c.notes || 'Sin observaciones registradas.') + '</p>'
		});
	} else if (sec === 'evidencias') {
		var img = c.evidence_photo_path ? '<div class="text-center my-2"><img src="' + Grocy.BaseUrl + '/api/files/wastepictures/' + btoa(c.evidence_photo_path) + '" class="img-fluid rounded border" style="max-height: 240px;"></div>' : '<p class="text-muted">No se adjuntó fotografía en el momento de la baja.</p>';
		bootbox.alert({
			title: 'Evidencias del Caso: ' + c.case_number,
			message: img + '<p class="small text-muted text-center mt-2">La evidencia fotográfica respalda el dictamen técnico y la causal.</p>'
		});
	} else if (sec === 'hipotesis') {
		bootbox.alert({
			title: 'Hipótesis del Incidente: ' + c.case_number,
			message: '<p><strong>Causal inicial tipificada:</strong> ' + (c.reason_name || '—') + '</p>' +
				'<p><strong>Categoría:</strong> ' + (c.loss_category || 'operativa') + '</p>' +
				'<p class="small text-muted">Las hipótesis iniciales se deben contrastar con los hechos y la cadena de custodia antes de dictaminar causa raíz definitiva.</p>'
		});
	} else if (sec === 'conclusion') {
		openInvestigateModal(c.id, c.case_number, c.product_name, c.total_cost);
	}
}
</script>
