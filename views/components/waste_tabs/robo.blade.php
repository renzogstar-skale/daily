@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$T = $theft ?? [];
	$I = $intel ?? [];
	$BT = $I['by_type'] ?? [
		'interno' => ['cost' => 0, 'incidents' => 0, 'units' => 0, 'label' => 'Robo interno confirmado'],
		'externo' => ['cost' => 0, 'incidents' => 0, 'units' => 0, 'label' => 'Robo externo confirmado'],
		'fraude_pos' => ['cost' => 0, 'incidents' => 0, 'units' => 0, 'label' => 'Fraude POS'],
		'fraude_electronico' => ['cost' => 0, 'incidents' => 0, 'units' => 0, 'label' => 'Fraude digital'],
	];
	$Rc = $I['recovery'] ?? ['goods' => 0, 'financial' => 0, 'total' => 0, 'pct' => null, 'bench_pct' => 17.47];
	$mods = $I['catalog']['modalities'] ?? [];
	$chans = $I['catalog']['channels'] ?? [];
	
	$totalTheft = floatval($T['total'] ?? 0);
	$internalCost = floatval($T['internal'] ?? 0);
	$externalCost = floatval($T['external'] ?? 0);
	$internalCount = intval($BT['interno']['incidents'] ?? 0);
	$externalCount = intval($BT['externo']['incidents'] ?? 0);
	$totalIncidents = intval($T['incidents'] ?? 0);
	$totalUnits = floatval($T['units'] ?? 0);
	$recoveredTotal = floatval($T['recovered'] ?? ($Rc['total'] ?? 0));
	$hasConfirmedTheft = $totalIncidents > 0;
	
	$invCount = intval($T['investigation']['count'] ?? 0);
	$invCost = floatval($T['investigation']['cost'] ?? 0);
	
	$incidentsList = $T['incidents_list'] ?? [];
	$investigationsList = $T['investigation_list'] ?? [];
	$events = $I['events'] ?? [];
	
	$recoveryEvents = array_values(array_filter($events, fn($e) => (floatval($e['goods'] ?? 0) + floatval($e['recovered'] ?? 0)) > 0));
	
	// Helper para fotografía de productos con reemplazo visual seguro
	$productPhoto = function($item) use ($U) {
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
		return $U('/img/control_center/robo_tienda_gondolas.jpg');
	};
@endphp

<!-- ==========================================================================
     ESTILOS ESPECÍFICOS DE LA PESTAÑA: PREVENCIÓN DE ROBO INTERNO Y EXTERNO
     Look & Feel DAILY Enterprise: Bordes sutiles, tarjetas blancas, sombras suaves
     ========================================================================== -->
<style>
/* Contenedor general de la pestaña */
.daily-theft-tab {
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

/* Encabezado de la pestaña */
.daily-theft-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	flex-wrap: wrap;
	gap: 14px;
}
.daily-theft-header-left {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}
.daily-theft-icon-badge {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	background: #fee2e2;
	color: #dc2626;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.25rem;
	flex-shrink: 0;
	margin-top: 2px;
}
.daily-theft-title-row {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
	margin-bottom: 2px;
}
.daily-theft-title {
	font-size: 1.25rem;
	font-weight: 800;
	color: #0f172a;
	margin: 0;
	letter-spacing: -0.015em;
}
.daily-theft-restricted-tag {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: #fef2f2;
	color: #dc2626;
	border: 1px solid #fecaca;
	border-radius: 6px;
	font-size: 0.72rem;
	font-weight: 700;
	padding: 2.5px 8px;
	letter-spacing: 0.02em;
}
.daily-theft-subtitle {
	font-size: 0.84rem;
	color: #64748b;
	margin: 0;
	font-weight: 400;
	line-height: 1.4;
}

/* Botones de acción del header */
.daily-theft-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
.daily-btn-primary-green {
	background: var(--daily-primary, #00C83B) !important;
	border: 1px solid var(--daily-primary, #00C83B) !important;
	color: #ffffff !important;
	font-weight: 700;
	font-size: 0.82rem;
	padding: 7px 15px;
	border-radius: 8px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
	transition: filter 0.15s ease, transform 0.15s ease;
	text-decoration: none !important;
}
.daily-btn-primary-green:hover {
	filter: brightness(0.93);
	transform: translateY(-1px);
	color: #ffffff !important;
}
.daily-btn-white {
	background: #ffffff !important;
	border: 1px solid #e2e8f0 !important;
	color: #334155 !important;
	font-weight: 600;
	font-size: 0.82rem;
	padding: 7px 13px;
	border-radius: 8px;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
	transition: background 0.15s ease, border-color 0.15s ease;
	text-decoration: none !important;
}
.daily-btn-white:hover {
	background: #f8fafc !important;
	border-color: #cbd5e1 !important;
	color: #0f172a !important;
}

/* Barra de Filtros Integrada */
.daily-theft-filters-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 12px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-theft-filters-form {
	display: flex;
	align-items: flex-end;
	flex-wrap: wrap;
	gap: 10px;
}
.daily-filter-field {
	flex: 1 1 130px;
	min-width: 120px;
}
.daily-filter-field label {
	display: block;
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
	margin-bottom: 3px;
}
.daily-filter-field select,
.daily-filter-field input {
	font-size: 0.82rem;
	font-weight: 600;
	color: #1e293b;
	border-radius: 6px;
	border: 1px solid #cbd5e1;
	padding: 4px 8px;
	height: 33px;
	background-color: #ffffff;
}
.daily-filter-field select:focus,
.daily-filter-field input:focus {
	border-color: var(--daily-primary, #00C83B);
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.15);
	outline: none;
}
.daily-filter-actions {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-shrink: 0;
	margin-bottom: 1px;
}
.daily-btn-apply {
	background: #0f172a !important;
	color: #ffffff !important;
	border: 1px solid #0f172a !important;
	font-weight: 700;
	font-size: 0.82rem;
	padding: 6px 14px;
	border-radius: 6px;
	height: 33px;
	display: inline-flex;
	align-items: center;
	gap: 5px;
	transition: background 0.15s ease;
}
.daily-btn-apply:hover {
	background: #1e293b !important;
}
.daily-btn-reset {
	color: #64748b;
	font-size: 0.82rem;
	padding: 6px 8px;
	height: 33px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
	border: 1px solid #e2e8f0;
	background: #ffffff;
}
.daily-btn-reset:hover {
	background: #f1f5f9;
	color: #0f172a;
}

/* B. Fila de los Cinco Indicadores Específicos */
.daily-theft-kpis-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 12px;
}
@media (max-width: 1200px) {
	.daily-theft-kpis-grid {
		grid-template-columns: repeat(3, 1fr);
	}
}
@media (max-width: 768px) {
	.daily-theft-kpis-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
@media (max-width: 480px) {
	.daily-theft-kpis-grid {
		grid-template-columns: 1fr;
	}
}
.daily-theft-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	position: relative;
	transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
	text-decoration: none !important;
	color: inherit !important;
}
.daily-theft-kpi-card:hover {
	transform: translateY(-1.5px);
	box-shadow: 0 5px 14px rgba(0, 0, 0, 0.05);
	border-color: #cbd5e1;
}
.daily-theft-kpi-card.tone-graphite {
	border-left: 4px solid #334155;
}
.daily-theft-kpi-card.tone-red {
	border-left: 4px solid #ef4444;
}
.daily-theft-kpi-card.tone-slate {
	border-left: 4px solid #64748b;
}
.daily-theft-kpi-card.tone-green {
	border-left: 4px solid #16a34a;
}
.daily-theft-kpi-card.tone-amber {
	border-left: 4px solid #f59e0b;
}
.daily-theft-kpi-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}
.daily-theft-kpi-title {
	font-size: 0.72rem;
	font-weight: 700;
	text-transform: uppercase;
	color: #64748b;
	letter-spacing: 0.04em;
}
.daily-theft-kpi-icon {
	width: 26px;
	height: 26px;
	border-radius: 6px;
	background: #f8fafc;
	color: #64748b;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.78rem;
}
.daily-theft-kpi-card.tone-graphite .daily-theft-kpi-icon { background: #f1f5f9; color: #1e293b; }
.daily-theft-kpi-card.tone-red .daily-theft-kpi-icon { background: #fee2e2; color: #dc2626; }
.daily-theft-kpi-card.tone-slate .daily-theft-kpi-icon { background: #f1f5f9; color: #475569; }
.daily-theft-kpi-card.tone-green .daily-theft-kpi-icon { background: #dcfce7; color: #16a34a; }
.daily-theft-kpi-card.tone-amber .daily-theft-kpi-icon { background: #fef3c7; color: #d97706; }

.daily-theft-kpi-val {
	font-size: 1.45rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	letter-spacing: -0.02em;
	margin-bottom: 4px;
}
.daily-theft-kpi-sub {
	font-size: 0.74rem;
	color: #64748b;
	font-weight: 500;
}
.daily-theft-kpi-foot {
	font-size: 0.72rem;
	color: #94a3b8;
	margin-top: 2px;
}

/* C. Tarjetas de Navegación con Fotografía Contextual */
.daily-theft-photos-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 14px;
}
@media (max-width: 992px) {
	.daily-theft-photos-grid {
		grid-template-columns: 1fr;
	}
}
.daily-photo-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	display: flex;
	flex-direction: column;
	transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
	position: relative;
}
.daily-photo-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
	border-color: #cbd5e1;
}
.daily-photo-img-wrap {
	width: 100%;
	height: 135px;
	position: relative;
	overflow: hidden;
	background: #f1f5f9;
}
.daily-photo-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
	transition: transform 0.3s ease;
}
.daily-photo-card:hover .daily-photo-img {
	transform: scale(1.03);
}
.daily-photo-mask {
	position: absolute;
	left: 0; right: 0; bottom: 0; top: 0;
	background: linear-gradient(to bottom, rgba(255,255,255,0) 50%, rgba(255,255,255,0.85) 85%, #ffffff 100%);
	pointer-events: none;
}
.daily-photo-body {
	padding: 16px 18px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	flex: 1;
	background: #ffffff;
}
.daily-photo-header-row {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 12px;
}
.daily-photo-action-icon {
	width: 38px;
	height: 38px;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.1rem;
	flex-shrink: 0;
}
.daily-photo-action-icon.is-green {
	background: var(--daily-primary, #00C83B);
	color: #ffffff;
}
.daily-photo-action-icon.is-blue {
	background: #2563eb;
	color: #ffffff;
}
.daily-photo-action-icon.is-orange {
	background: #ea580c;
	color: #ffffff;
}
.daily-photo-title {
	font-size: 1.05rem;
	font-weight: 700;
	color: #0f172a;
	margin: 0 0 2px 0;
	letter-spacing: -0.01em;
}
.daily-photo-desc {
	font-size: 0.78rem;
	color: #64748b;
	margin: 0;
	line-height: 1.4;
}
.daily-photo-btn-link {
	align-self: center;
	width: 100%;
	text-align: center;
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	padding: 7px 12px;
	font-size: 0.82rem;
	font-weight: 700;
	color: #1e293b;
	text-decoration: none !important;
	transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
}
.daily-photo-btn-link:hover {
	background: #f8fafc;
	border-color: #cbd5e1;
	color: var(--daily-primary, #00C83B);
}

/* D. Franja de Alerta y Flujo de Gestión */
.daily-theft-alert-strip {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 10px 16px;
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 0.82rem;
	color: #92400e;
	font-weight: 500;
}
.daily-theft-alert-icon {
	color: #d97706;
	font-size: 1.05rem;
	flex-shrink: 0;
}
.daily-theft-flow-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 18px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.daily-theft-flow-steps {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	flex-wrap: wrap;
}
.daily-flow-step-item {
	display: flex;
	align-items: center;
	gap: 10px;
	flex: 1 1 150px;
	min-width: 140px;
}
.daily-flow-num {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: #f1f5f9;
	color: #475569;
	font-size: 0.78rem;
	font-weight: 800;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.daily-flow-info {
	display: flex;
	flex-direction: column;
}
.daily-flow-label {
	font-size: 0.82rem;
	font-weight: 700;
	color: #0f172a;
	line-height: 1.15;
}
.daily-flow-sub {
	font-size: 0.72rem;
	color: #64748b;
	line-height: 1.25;
}
.daily-flow-sep {
	color: #cbd5e1;
	font-size: 0.85rem;
	margin: 0 4px;
	flex-shrink: 0;
}
@media (max-width: 768px) {
	.daily-flow-sep {
		display: none;
	}
	.daily-flow-step-item {
		margin-bottom: 8px;
	}
}

/* E. Casos e Investigaciones */
.daily-cases-section-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 12px;
	margin-bottom: 14px;
}
.daily-cases-title {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}
.daily-cases-tabs-nav {
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.daily-cases-tab-btn {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	color: #64748b;
	font-size: 0.8rem;
	font-weight: 700;
	padding: 6px 14px;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.15s ease;
	outline: none !important;
}
.daily-cases-tab-btn:hover {
	background: #f8fafc;
	color: #0f172a;
	border-color: #cbd5e1;
}
.daily-cases-tab-btn.is-active {
	background: #dcfce7;
	border-color: #86efac;
	color: #15803d;
}
.daily-cases-toolbar {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}
.daily-cases-search-box {
	position: relative;
	min-width: 220px;
	flex: 1;
}
.daily-cases-search-box i {
	position: absolute;
	left: 10px;
	top: 50%;
	transform: translateY(-50%);
	color: #94a3b8;
	font-size: 0.78rem;
}
.daily-cases-search-box input {
	padding-left: 30px;
	font-size: 0.82rem;
	border-radius: 7px;
	border: 1px solid #cbd5e1;
	height: 33px;
	width: 100%;
}
.daily-cases-search-box input:focus {
	border-color: var(--daily-primary, #00C83B);
	box-shadow: 0 0 0 2px rgba(0, 200, 59, 0.15);
	outline: none;
}

/* Tabla de casos */
.daily-cases-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	font-size: 0.8rem;
}
.daily-cases-table thead th {
	background: #f8fafc;
	color: #64748b;
	font-size: 0.7rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 10px 12px;
	border-bottom: 1px solid #e2e8f0;
	border-top: 1px solid #e2e8f0;
	white-space: nowrap;
}
.daily-cases-table tbody td {
	padding: 10px 12px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.daily-cases-table tbody tr:hover td {
	background: #f8fafc;
}

/* Estado Vacío de Alta Fidelidad (Idéntico a la imagen) */
.daily-theft-empty-state {
	padding: 36px 20px;
	text-align: center;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
.daily-theft-empty-icon {
	width: 52px;
	height: 52px;
	border-radius: 12px;
	background: #f1f5f9;
	color: #94a3b8;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.5rem;
	margin-bottom: 12px;
}
.daily-theft-empty-title {
	font-size: 0.92rem;
	font-weight: 700;
	color: #334155;
	margin-bottom: 3px;
}
.daily-theft-empty-sub {
	font-size: 0.78rem;
	color: #64748b;
	margin: 0;
}

/* F. Inteligencia de Hurto y Fraude */
.daily-intel-header-row {
	margin-bottom: 14px;
}
.daily-intel-title {
	font-size: 1.05rem;
	font-weight: 800;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 2px 0;
}
.daily-intel-subtitle {
	font-size: 0.8rem;
	color: #64748b;
	margin: 0;
}
.daily-intel-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 12px;
}
@media (max-width: 992px) {
	.daily-intel-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
@media (max-width: 480px) {
	.daily-intel-grid {
		grid-template-columns: 1fr;
	}
}
.daily-intel-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 14px 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	position: relative;
}
.daily-intel-kpi-head {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}
.daily-intel-kpi-icon {
	width: 24px;
	height: 24px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.8rem;
}
.daily-intel-kpi-icon.is-blue { background: #eff6ff; color: #2563eb; }
.daily-intel-kpi-icon.is-purple { background: #faf5ff; color: #9333ea; }
.daily-intel-kpi-icon.is-indigo { background: #e0e7ff; color: #4338ca; }
.daily-intel-kpi-icon.is-gray { background: #f1f5f9; color: #64748b; }
.daily-intel-kpi-label {
	font-size: 0.74rem;
	font-weight: 700;
	color: #64748b;
}
.daily-intel-kpi-val {
	font-size: 1.4rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
	margin-bottom: 2px;
}
.daily-intel-kpi-sub {
	font-size: 0.74rem;
	color: #94a3b8;
}

/* G y H: Dos Columnas (Ranking de Productos y Recuperación) */
.daily-theft-two-col {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 14px;
}
@media (max-width: 992px) {
	.daily-theft-two-col {
		grid-template-columns: 1fr;
	}
}
.daily-col-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
}
.daily-col-title {
	font-size: 0.98rem;
	font-weight: 800;
	color: #0f172a;
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
}
.daily-col-action-link {
	font-size: 0.76rem;
	font-weight: 700;
	color: var(--daily-primary, #00C83B);
	text-decoration: none !important;
}
.daily-col-action-link:hover {
	text-decoration: underline !important;
}

/* 3 bloques de recuperación */
.daily-rec-metrics-row {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;
	margin-bottom: 12px;
}
@media (max-width: 576px) {
	.daily-rec-metrics-row {
		grid-template-columns: 1fr;
	}
}
.daily-rec-metric-box {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 12px 14px;
}
.daily-rec-metric-lbl {
	font-size: 0.7rem;
	font-weight: 700;
	color: #64748b;
	margin-bottom: 4px;
}
.daily-rec-metric-val {
	font-size: 1.3rem;
	font-weight: 800;
	color: #0f172a;
	line-height: 1.15;
}
.daily-rec-metric-sub {
	font-size: 0.72rem;
	color: #94a3b8;
	margin-top: 2px;
}
.daily-rec-benchmark-note {
	background: #ffffff;
	border: 1px dashed #cbd5e1;
	border-radius: 8px;
	padding: 8px 12px;
	font-size: 0.72rem;
	color: #64748b;
	line-height: 1.35;
}

/* I. Módulos Conectados con Centro de Control */
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
.daily-connected-map-link:hover {
	text-decoration: underline !important;
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

/* Badges y chips de estado */
.daily-badge-chip {
	display: inline-flex;
	align-items: center;
	padding: 2.5px 7px;
	border-radius: 5px;
	font-size: 0.72rem;
	font-weight: 700;
}
.daily-badge-chip.is-green { background: #dcfce7; color: #15803d; }
.daily-badge-chip.is-red { background: #fee2e2; color: #b91c1c; }
.daily-badge-chip.is-amber { background: #fef3c7; color: #b45309; }
.daily-badge-chip.is-slate { background: #f1f5f9; color: #475569; }

/* Responsive adjustments */
@media (max-width: 768px) {
	.daily-cc-card {
		padding: 14px 16px;
	}
	.daily-theft-header {
		flex-direction: column;
		align-items: stretch;
	}
	.daily-theft-actions {
		justify-content: flex-start;
	}
	.daily-theft-filters-form {
		flex-direction: column;
		align-items: stretch;
	}
	.daily-filter-field {
		width: 100%;
	}
	.daily-cases-section-head {
		flex-direction: column;
		align-items: stretch;
	}
	.daily-cases-toolbar {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>

<div class="daily-theft-tab" id="dailyRoboTabContainer">

	<!-- ======================================================================
	     SECCIÓN A: ENCABEZADO Y FILTROS
	     ====================================================================== -->
	<div class="daily-theft-header">
		<div class="daily-theft-header-left">
			<div class="daily-theft-icon-badge" aria-hidden="true">
				<i class="fa-solid fa-shield-halved"></i>
			</div>
			<div>
				<div class="daily-theft-title-row">
					<h5 class="daily-theft-title">Prevención de robo interno y externo</h5>
					<span class="daily-theft-restricted-tag">
						<i class="fa-solid fa-lock" style="font-size: 0.65rem;"></i> Acceso restringido
					</span>
				</div>
				<p class="daily-theft-subtitle">
					Solo casos confirmados y merma aprobada. Las anomalías requieren revisión humana.
				</p>
			</div>
		</div>

		<div class="daily-theft-actions">
			<a href="{{ $U('/waste/report') }}" class="daily-btn-primary-green">
				<i class="fa-solid fa-plus"></i> Registrar Merma
			</a>

			<div class="dropdown">
				<button type="button" class="daily-btn-white dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa-solid fa-bolt text-warning"></i> Acciones Rápidas
				</button>
				<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 dairy-radius-md" style="font-size: 0.82rem;">
					<a class="dropdown-item py-2" href="#tab-conteos" onclick="$('#tab-conteos-link').click(); return false;">
						<i class="fa-solid fa-clipboard-check text-success mr-2"></i>Programar Conteo Selectivo
					</a>
					<a class="dropdown-item py-2" href="{{ $U('/sales') }}">
						<i class="fa-solid fa-cash-register text-primary mr-2"></i>Ver Excepciones POS
					</a>
					<a class="dropdown-item py-2" href="#tab-investigaciones" onclick="$('#tab-investigaciones-link').click(); return false;">
						<i class="fa-solid fa-magnifying-glass text-warning mr-2"></i>Ir a Investigación y Planes
					</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item py-2" href="{{ $U('/system-map') }}">
						<i class="fa-solid fa-network-wired text-muted mr-2"></i>Mapa del Sistema
					</a>
				</div>
			</div>

			<div class="dropdown">
				@include('components.dairy_export', ['exportScope' => '#dailyRoboTabContainer', 'exportName' => 'hurto_prevencion'])
			</div>
		</div>
	</div>

	<!-- Barra de Filtros Sincronizada -->
	<div class="daily-theft-filters-card">
		<form class="daily-theft-filters-form" onsubmit="return DairyLoss.applyFilters(this);" aria-label="Filtros de prevención de hurto">
			<!-- Fechas Desde / Hasta -->
			<div class="daily-filter-field">
				<label for="tf-from">Desde</label>
				<input id="tf-from" type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
			</div>
			<div class="daily-filter-field">
				<label for="tf-to">Hasta</label>
				<input id="tf-to" type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
			</div>

			<!-- Sucursal -->
			<div class="daily-filter-field">
				<label for="tf-branch">Sucursal</label>
				<select id="tf-branch" name="branch_id" class="form-control form-control-sm">
					<option value="">Todas</option>
					@foreach($lookups['branches'] as $b)
						<option value="{{ $b['id'] }}" @if(($f['branch_id'] ?? null) == $b['id']) selected @endif>{{ $b['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Departamento -->
			<div class="daily-filter-field">
				<label for="tf-group">Departamento</label>
				<select id="tf-group" name="group_id" class="form-control form-control-sm">
					<option value="">Todos</option>
					@foreach($lookups['groups'] as $g)
						<option value="{{ $g['id'] }}" @if(($f['group_id'] ?? null) == $g['id']) selected @endif>{{ $g['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- Proveedor -->
			<div class="daily-filter-field">
				<label for="tf-supplier">Proveedor</label>
				<select id="tf-supplier" name="supplier_id" class="form-control form-control-sm">
					<option value="">Todos</option>
					@foreach($lookups['suppliers'] as $s)
						<option value="{{ $s['id'] }}" @if(($f['supplier_id'] ?? null) == $s['id']) selected @endif>{{ $s['name'] }}</option>
					@endforeach
				</select>
			</div>

			<!-- SKU / Producto -->
			<div class="daily-filter-field">
				<label for="tf-product">SKU</label>
				<select id="tf-product" name="product_id" class="form-control form-control-sm">
					<option value="">Todos</option>
					@foreach($lookups['products'] as $p)
						<option value="{{ $p['id'] }}" @if(($f['product_id'] ?? null) == $p['id']) selected @endif>
							{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}
						</option>
					@endforeach
				</select>
			</div>

			<!-- Acciones de Filtro -->
			<div class="daily-filter-actions">
				<button type="submit" class="btn btn-sm daily-btn-apply">
					<i class="fa-solid fa-check"></i> Aplicar
				</button>
				<button type="button" class="btn btn-sm daily-btn-reset" title="Restablecer filtros" onclick="dailyResetTheftFilters(this)">
					<i class="fa-solid fa-rotate-left"></i>
				</button>
			</div>
		</form>
	</div>

	<!-- ======================================================================
	     SECCIÓN B: CINCO INDICADORES ESPECÍFICOS (CONSERVADOS Y FORMULADOS)
	     ====================================================================== -->
	<div class="daily-theft-kpis-grid">
		<!-- 1. ROBO INTERNO CONFIRMADO -->
		<div class="daily-theft-kpi-card tone-graphite">
			<div>
				<div class="daily-theft-kpi-head">
					<span class="daily-theft-kpi-title">Robo interno confirmado</span>
					<div class="daily-theft-kpi-icon"><i class="fa-solid fa-user-shield"></i></div>
				</div>
				<div class="daily-theft-kpi-val">{{ $money($internalCost) }}</div>
			</div>
			<div>
				<div class="daily-theft-kpi-sub">
					{{ $internalCount }} {{ $internalCount === 1 ? 'evento único' : 'eventos únicos' }}
				</div>
				<div class="daily-theft-kpi-foot">
					@if($totalTheft > 0)
						{{ number_format(($internalCost / $totalTheft) * 100, 1) }}% del hurto
					@else
						<span class="text-muted">— No calculable</span>
					@endif
				</div>
			</div>
		</div>

		<!-- 2. ROBO EXTERNO CONFIRMADO -->
		<div class="daily-theft-kpi-card tone-red">
			<div>
				<div class="daily-theft-kpi-head">
					<span class="daily-theft-kpi-title">Robo externo confirmado</span>
					<div class="daily-theft-kpi-icon"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
				</div>
				<div class="daily-theft-kpi-val">{{ $money($externalCost) }}</div>
			</div>
			<div>
				<div class="daily-theft-kpi-sub">
					{{ $externalCount }} {{ $externalCount === 1 ? 'evento único' : 'eventos únicos' }}
				</div>
				<div class="daily-theft-kpi-foot">
					@if($totalTheft > 0)
						{{ number_format(($externalCost / $totalTheft) * 100, 1) }}% del hurto
					@else
						<span class="text-muted">— No calculable</span>
					@endif
				</div>
			</div>
		</div>

		<!-- 3. UNIDADES PERDIDAS -->
		<div class="daily-theft-kpi-card tone-slate">
			<div>
				<div class="daily-theft-kpi-head">
					<span class="daily-theft-kpi-title">Unidades perdidas</span>
					<div class="daily-theft-kpi-icon"><i class="fa-solid fa-cubes"></i></div>
				</div>
				<div class="daily-theft-kpi-val">{{ number_format($totalUnits, 2) }} <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">u.</span></div>
			</div>
			<div>
				<div class="daily-theft-kpi-sub">
					{{ $totalIncidents }} {{ $totalIncidents === 1 ? 'incidente confirmado' : 'incidentes confirmados' }}
				</div>
				<div class="daily-theft-kpi-foot">Unidades físicas registradas</div>
			</div>
		</div>

		<!-- 4. RECUPERADO -->
		<div class="daily-theft-kpi-card tone-green">
			<div>
				<div class="daily-theft-kpi-head">
					<span class="daily-theft-kpi-title">Recuperado</span>
					<div class="daily-theft-kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
				</div>
				<div class="daily-theft-kpi-val">{{ $money($recoveredTotal) }}</div>
			</div>
			<div>
				<div class="daily-theft-kpi-sub">
					Mercancía: {{ $money($Rc['goods'] ?? 0) }}
				</div>
				<div class="daily-theft-kpi-foot">
					Financiera: {{ $money($Rc['financial'] ?? 0) }}
				</div>
			</div>
		</div>

		<!-- 5. BAJO INVESTIGACIÓN -->
		<a href="#tab-investigaciones" class="daily-theft-kpi-card tone-amber" onclick="$('#tab-investigaciones-link').click(); return false;">
			<div>
				<div class="daily-theft-kpi-head">
					<span class="daily-theft-kpi-title">Bajo investigación</span>
					<div class="daily-theft-kpi-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
				</div>
				<div class="daily-theft-kpi-val">{{ $invCount }} <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">{{ $invCount === 1 ? 'caso' : 'casos' }}</span></div>
			</div>
			<div>
				<div class="daily-theft-kpi-sub">
					{{ $money($invCost) }} sin confirmar
				</div>
				<div class="daily-theft-kpi-foot">
					<span class="daily-badge-chip is-amber">No es robo confirmado</span>
				</div>
			</div>
		</a>
	</div>

	<!-- ======================================================================
	     SECCIÓN C: TARJETAS DE NAVEGACIÓN CON FOTOGRAFÍAS CONTEXTUALES
	     ====================================================================== -->
	<div class="daily-theft-photos-grid">
		<!-- Tarjeta 1: Prevención en tienda -->
		<div class="daily-photo-card">
			<div class="daily-photo-img-wrap">
				<img src="{{ $U('/img/control_center/robo_tienda_gondolas.jpg') }}" 
				     alt="Góndolas y lineales de supermercado" 
				     class="daily-photo-img" 
				     loading="lazy"
				     onerror="this.src='{{ $U('/img/control_center/operativa_banner.jpg') }}';">
				<div class="daily-photo-mask"></div>
			</div>
			<div class="daily-photo-body">
				<div class="daily-photo-header-row">
					<div class="daily-photo-action-icon is-green">
						<i class="fa-solid fa-cart-shopping"></i>
					</div>
					<div>
						<h6 class="daily-photo-title">Prevención en tienda</h6>
						<p class="daily-photo-desc">Consulta modalidades y casos confirmados de robo interno y externo.</p>
					</div>
				</div>
				<a href="#dailyTheftCasesSection" class="daily-photo-btn-link" onclick="document.getElementById('dailyTheftCasesSection').scrollIntoView({behavior: 'smooth'}); return false;">
					Ver casos <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>
		</div>

		<!-- Tarjeta 2: Control POS -->
		<div class="daily-photo-card">
			<div class="daily-photo-img-wrap">
				<img src="{{ $U('/img/control_center/robo_control_pos.jpg') }}" 
				     alt="Terminal POS y escáner de códigos de barra" 
				     class="daily-photo-img" 
				     loading="lazy"
				     onerror="this.src='{{ $U('/img/control_center/precios_detectar_pos.jpg') }}';">
				<div class="daily-photo-mask"></div>
			</div>
			<div class="daily-photo-body">
				<div class="daily-photo-header-row">
					<div class="daily-photo-action-icon is-blue">
						<i class="fa-solid fa-barcode"></i>
					</div>
					<div>
						<h6 class="daily-photo-title">Control POS</h6>
						<p class="daily-photo-desc">Revisa excepciones con evidencia de transacciones y movimientos.</p>
					</div>
				</div>
				<a href="#tab-precios" class="daily-photo-btn-link" onclick="$('#tab-precios-link').click(); return false;">
					Ver análisis POS <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>
		</div>

		<!-- Tarjeta 3: Recuperación -->
		<div class="daily-photo-card">
			<div class="daily-photo-img-wrap">
				<img src="{{ $U('/img/control_center/robo_recuperacion.jpg') }}" 
				     alt="Mercancía recuperada en bodega" 
				     class="daily-photo-img" 
				     loading="lazy"
				     onerror="this.src='{{ $U('/img/control_center/comercial_banner.jpg') }}';">
				<div class="daily-photo-mask"></div>
			</div>
			<div class="daily-photo-body">
				<div class="daily-photo-header-row">
					<div class="daily-photo-action-icon is-orange">
						<i class="fa-solid fa-box-open"></i>
					</div>
					<div>
						<h6 class="daily-photo-title">Recuperación</h6>
						<p class="daily-photo-desc">Recuperación de mercancía y recuperación financiera.</p>
					</div>
				</div>
				<a href="#dailyTheftRecoveryCard" class="daily-photo-btn-link" onclick="document.getElementById('dailyTheftRecoveryCard').scrollIntoView({behavior: 'smooth'}); return false;">
					Ver recuperación <i class="fa-solid fa-arrow-right ml-1"></i>
				</a>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN D: ALERTA Y FLUJO DE GESTIÓN (5 PASOS NEUTRALES)
	     ====================================================================== -->
	<div class="daily-theft-alert-strip" role="alert">
		<i class="fa-solid fa-triangle-exclamation daily-theft-alert-icon"></i>
		<div>
			<strong>Una alerta no prueba culpabilidad.</strong> Clasificación definitiva solo tras revisión humana; no se muestran datos personales.
		</div>
	</div>

	<div class="daily-theft-flow-card">
		<div class="daily-theft-flow-steps">
			<!-- Paso 1 -->
			<div class="daily-flow-step-item">
				<div class="daily-flow-num">1</div>
				<div class="daily-flow-info">
					<span class="daily-flow-label">Registro</span>
					<span class="daily-flow-sub">Detección y reporte de anomalías</span>
				</div>
			</div>

			<i class="fa-solid fa-arrow-right daily-flow-sep"></i>

			<!-- Paso 2 -->
			<div class="daily-flow-step-item">
				<div class="daily-flow-num">2</div>
				<div class="daily-flow-info">
					<span class="daily-flow-label">Investigación</span>
					<span class="daily-flow-sub">Análisis y validación de información</span>
				</div>
			</div>

			<i class="fa-solid fa-arrow-right daily-flow-sep"></i>

			<!-- Paso 3 -->
			<div class="daily-flow-step-item">
				<div class="daily-flow-num">3</div>
				<div class="daily-flow-info">
					<span class="daily-flow-label">Confirmación</span>
					<span class="daily-flow-sub">Revisión humana y dictamen final</span>
				</div>
			</div>

			<i class="fa-solid fa-arrow-right daily-flow-sep"></i>

			<!-- Paso 4 -->
			<div class="daily-flow-step-item">
				<div class="daily-flow-num">4</div>
				<div class="daily-flow-info">
					<span class="daily-flow-label">Recuperación</span>
					<span class="daily-flow-sub">Distingue mercancía y/o valor financiero</span>
				</div>
			</div>

			<i class="fa-solid fa-arrow-right daily-flow-sep"></i>

			<!-- Paso 5 -->
			<div class="daily-flow-step-item">
				<div class="daily-flow-num">5</div>
				<div class="daily-flow-info">
					<span class="daily-flow-label">Cierre</span>
					<span class="daily-flow-sub">Registro y seguimiento del caso</span>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN E: CASOS E INVESTIGACIONES
	     ====================================================================== -->
	<div class="daily-cc-card" id="dailyTheftCasesSection">
		<div class="daily-cases-section-head">
			<div class="d-flex align-items-center gap-3 flex-wrap">
				<h6 class="daily-cases-title">
					<i class="fa-regular fa-file-lines text-dark"></i> Casos e investigaciones
				</h6>

				<!-- Pestañas de sub-filtro de casos -->
				<div class="daily-cases-tabs-nav" role="tablist">
					<button type="button" class="daily-cases-tab-btn is-active" data-cases-filter="confirmados" onclick="dailySwitchCasesTab(this, 'confirmados')">
						Confirmados ({{ count($incidentsList) }})
					</button>
					<button type="button" class="daily-cases-tab-btn" data-cases-filter="investigacion" onclick="dailySwitchCasesTab(this, 'investigacion')">
						En investigación ({{ $invCount }})
					</button>
					<button type="button" class="daily-cases-tab-btn" data-cases-filter="recuperaciones" onclick="dailySwitchCasesTab(this, 'recuperaciones')">
						Recuperaciones ({{ count($recoveryEvents) }})
					</button>
					<button type="button" class="daily-cases-tab-btn" data-cases-filter="descartados" onclick="dailySwitchCasesTab(this, 'descartados')">
						Descartados (0)
					</button>
				</div>
			</div>

			<div class="daily-cases-toolbar">
				<div class="daily-cases-search-box">
					<i class="fa-solid fa-magnifying-glass"></i>
					<input type="text" id="dailyTheftSearchInput" placeholder="Buscar caso, SKU, lote, producto..." onkeyup="dailyFilterCasesTable(this.value)">
				</div>

				<div class="dropdown">
					<button type="button" class="daily-btn-white dropdown-toggle" data-toggle="dropdown">
						<i class="fa-solid fa-download text-muted"></i> Exportar
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm border-0 dairy-radius-md" style="font-size: 0.82rem;">
						<a class="dropdown-item py-2" href="javascript:void(0)" onclick="DairyLoss.exportTable('dailyCasesMasterTable', 'csv', 'casos_hurto_confirmado');">
							<i class="fa-solid fa-file-csv text-success mr-2"></i>Exportar a CSV
						</a>
						<a class="dropdown-item py-2" href="javascript:void(0)" onclick="DairyLoss.exportTable('dailyCasesMasterTable', 'xls', 'casos_hurto_confirmado');">
							<i class="fa-solid fa-file-excel text-success mr-2"></i>Exportar a Excel
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Contenedor de la Tabla -->
		<div class="table-responsive" style="min-height: 180px;">
			<table class="daily-cases-table" id="dailyCasesMasterTable">
				<thead>
					<tr>
						<th>Caso</th>
						<th>Fecha</th>
						<th>Tipo</th>
						<th>Modalidad</th>
						<th>Canal</th>
						<th class="text-right">Impacto</th>
						<th class="text-right">Recuperación</th>
						<th class="text-center">Estado</th>
						<th class="text-right">Detalle</th>
					</tr>
				</thead>
				<tbody id="dailyCasesTableBody">
					{{-- FILAS DE CASOS CONFIRMADOS --}}
					@if(count($incidentsList) > 0)
						@foreach($incidentsList as $item)
							@php
								$evRef = intval($item['ref'] ?? 0);
								$detRow = array_values(array_filter($events, fn($e) => intval($e['ref'] ?? 0) === $evRef))[0] ?? null;
								$modality = $detRow['modality'] ?? null;
								$channel = $detRow['channel'] ?? ($detRow['detection_channel'] ?? '—');
								$goodsVal = floatval($detRow['goods'] ?? 0);
								$finVal = floatval($item['recovered'] ?? 0);
								$recTot = $goodsVal + $finVal;
							@endphp
							<tr class="case-row case-type-confirmados" 
							    data-case-type="confirmados"
							    data-search="{{ strtolower(($item['doc'] ?? '') . ' ' . ($item['product'] ?? '') . ' ' . ($item['sku'] ?? '') . ' ' . ($modality ?? '') . ' ' . ($channel ?? '')) }}">
								<td class="font-weight-700">
									<a href="{{ $U('/document/waste/' . $item['ref']) }}" class="text-dark font-mono text-decoration-none" title="Ver documento oficial">
										{{ $item['doc'] ?? ('CASO-' . $item['ref']) }}
									</a>
								</td>
								<td class="text-nowrap text-muted">{{ $item['d'] ?? '—' }}</td>
								<td>
									<span class="daily-badge-chip {{ ($item['type'] ?? '') === 'Interno' ? 'is-slate' : 'is-red' }}">
										{{ $item['type'] ?? 'Confirmado' }}
									</span>
								</td>
								<td>
									@if($modality)
										<span class="text-dark font-weight-600">{{ $modality }}</span>
									@else
										<span class="daily-badge-chip is-amber">sin registrar</span>
									@endif
								</td>
								<td class="text-muted small">{{ $channel }}</td>
								<td class="text-right font-weight-700 text-dark">
									{{ $money($item['cost'] ?? 0) }}
									<div class="small text-muted font-weight-400">{{ number_format($item['units'] ?? 0, 2) }} {{ $item['qu_name'] ?? 'u.' }}</div>
								</td>
								<td class="text-right font-weight-600 {{ $recTot > 0 ? 'text-success' : 'text-muted' }}">
									{{ $money($recTot) }}
								</td>
								<td class="text-center">
									<span class="daily-badge-chip is-green">Confirmado</span>
								</td>
								<td class="text-right">
									<button type="button" class="btn btn-xs btn-light border font-weight-600" 
									        onclick="dailyOpenCaseModal({{ json_encode($item) }}, {{ json_encode($detRow) }})">
										Ver detalle
									</button>
								</td>
							</tr>
						@endforeach
					@endif

					{{-- FILAS DE INVESTIGACIONES --}}
					@if(count($investigationsList) > 0)
						@foreach($investigationsList as $invItem)
							<tr class="case-row case-type-investigacion" 
							    data-case-type="investigacion"
							    style="display: none;"
							    data-search="{{ strtolower(($invItem['doc'] ?? '') . ' ' . ($invItem['product'] ?? '') . ' ' . ($invItem['sku'] ?? '')) }}">
								<td class="font-weight-700 font-mono">
									{{ $invItem['doc'] ?? ('INV-' . $invItem['ref']) }}
								</td>
								<td class="text-nowrap text-muted">{{ $invItem['d'] ?? '—' }}</td>
								<td>
									<span class="daily-badge-chip is-amber">Bajo investigación</span>
								</td>
								<td class="text-muted italic">Análisis pendiente</td>
								<td class="text-muted small">Revisión de conteo</td>
								<td class="text-right font-weight-700 text-warning">
									{{ $money($invItem['cost'] ?? 0) }}
									<div class="small text-muted font-weight-400">{{ number_format($invItem['units'] ?? 0, 2) }} {{ $invItem['qu_name'] ?? 'u.' }}</div>
								</td>
								<td class="text-right text-muted">—</td>
								<td class="text-center">
									<span class="daily-badge-chip is-amber">En revisión</span>
								</td>
								<td class="text-right">
									<a href="#tab-investigaciones" class="btn btn-xs btn-light border font-weight-600" onclick="$('#tab-investigaciones-link').click(); return false;">
										Investigar
									</a>
								</td>
							</tr>
						@endforeach
					@endif
				</tbody>
			</table>

			<!-- Estado Vacío Cuando No Hay Registros en la Pestaña Activa -->
			<div class="daily-theft-empty-state" id="dailyTheftEmptyStateBox" style="{{ count($incidentsList) > 0 ? 'display: none;' : '' }}">
				<div class="daily-theft-empty-icon" aria-hidden="true">
					<i class="fa-regular fa-folder-open"></i>
				</div>
				<div class="daily-theft-empty-title">Sin hurtos confirmados en el período y filtros elegidos.</div>
				<p class="daily-theft-empty-sub">La ausencia de registros no demuestra ausencia de incidentes.</p>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN F: INTELIGENCIA DE HURTO Y FRAUDE
	     ====================================================================== -->
	<div class="daily-cc-card">
		<div class="daily-intel-header-row">
			<h6 class="daily-intel-title">
				<i class="fa-solid fa-chart-simple text-dark"></i> Inteligencia de hurto y fraude
			</h6>
			<p class="daily-intel-subtitle">
				Métricas sobre casos confirmados. Los sospechosos permanecen en investigación con revisión humana.
			</p>
		</div>

		<!-- 4 Métricas de Fraude e Indicadores Operativos -->
		<div class="daily-intel-grid">
			<!-- Fraude POS -->
			<div class="daily-intel-kpi-card">
				<div>
					<div class="daily-intel-kpi-head">
						<div class="daily-intel-kpi-icon is-blue"><i class="fa-regular fa-file-lines"></i></div>
						<span class="daily-intel-kpi-label">Fraude POS</span>
					</div>
					<div class="daily-intel-kpi-val">{{ $money($BT['fraude_pos']['cost'] ?? 0) }}</div>
				</div>
				<div class="daily-intel-kpi-sub">
					{{ intval($BT['fraude_pos']['incidents'] ?? 0) }} casos
				</div>
			</div>

			<!-- Fraude digital -->
			<div class="daily-intel-kpi-card">
				<div>
					<div class="daily-intel-kpi-head">
						<div class="daily-intel-kpi-icon is-purple"><i class="fa-solid fa-microchip"></i></div>
						<span class="daily-intel-kpi-label">Fraude digital</span>
					</div>
					<div class="daily-intel-kpi-val">{{ $money($BT['fraude_electronico']['cost'] ?? 0) }}</div>
				</div>
				<div class="daily-intel-kpi-sub">
					{{ intval($BT['fraude_electronico']['incidents'] ?? 0) }} casos
				</div>
			</div>

			<!-- Incidentes por 1,000 tickets -->
			<div class="daily-intel-kpi-card">
				<div>
					<div class="daily-intel-kpi-head">
						<div class="daily-intel-kpi-icon is-indigo"><i class="fa-solid fa-receipt"></i></div>
						<span class="daily-intel-kpi-label">Incidentes por 1,000 tickets</span>
					</div>
					<div class="daily-intel-kpi-val">
						@if(($I['rate']['per_1000_tickets'] ?? null) !== null)
							{{ number_format($I['rate']['per_1000_tickets'], 2) }}
						@else
							<span class="text-muted">—</span>
						@endif
					</div>
				</div>
				<div class="daily-intel-kpi-sub">
					@if(!empty($I['rate']['tickets']) && ($I['rate']['per_1000_tickets'] ?? null) !== null)
						{{ number_format($I['rate']['tickets']) }} tickets analizados
					@else
						Base de tickets no disponible
					@endif
				</div>
			</div>

			<!-- Sin modalidad -->
			<div class="daily-intel-kpi-card">
				<div>
					<div class="daily-intel-kpi-head">
						<div class="daily-intel-kpi-icon is-gray"><i class="fa-solid fa-circle-info"></i></div>
						<span class="daily-intel-kpi-label">Sin modalidad</span>
					</div>
					<div class="daily-intel-kpi-val">{{ intval($I['pending_details'] ?? 0) }}</div>
				</div>
				<div class="daily-intel-kpi-sub">
					casos por completar
				</div>
			</div>
		</div>

		{{-- Gráficos cuando hay casos confirmados reales --}}
		@if($hasConfirmedTheft)
			<div class="row mt-4">
				<div class="col-lg-4 mb-3">
					<h6 class="font-weight-700 mb-1 small text-muted text-uppercase">Interno vs. Externo</h6>
					<div class="dlx-chart is-sm"><canvas id="dailyTheftDonut" role="img" aria-label="Distribución interno externo"></canvas></div>
				</div>
				<div class="col-lg-8 mb-3">
					<h6 class="font-weight-700 mb-1 small text-muted text-uppercase">Evolución mensual de hurto</h6>
					<div class="dlx-chart is-sm"><canvas id="dailyTheftMonthly" role="img" aria-label="Evolución mensual de hurto"></canvas></div>
				</div>
			</div>

			<div class="row mt-2">
				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-700 mb-1 small text-muted text-uppercase">Pérdida por Modalidad</h6>
					<div class="dlx-chart is-sm mb-2"><canvas id="dailyTheftMods" role="img" aria-label="Pérdida por modalidad"></canvas></div>
				</div>
				<div class="col-lg-6 mb-3">
					<h6 class="font-weight-700 mb-1 small text-muted text-uppercase">Canal de Detección</h6>
					<div class="dlx-chart is-sm mb-2"><canvas id="dailyTheftChan" role="img" aria-label="Casos por canal de detección"></canvas></div>
				</div>
			</div>
		@endif
	</div>

	<!-- ======================================================================
	     SECCIÓN G Y H: PRODUCTOS CON HURTO CONFIRMADO Y RECUPERACIÓN
	     ====================================================================== -->
	<div class="daily-theft-two-col">

		<!-- G. PRODUCTOS CON HURTO CONFIRMADO -->
		<div class="daily-cc-card" id="dailyTheftProductsCard">
			<div class="daily-col-header">
				<h6 class="daily-col-title">
					<i class="fa-solid fa-box text-success"></i> Productos con hurto confirmado
				</h6>
				@if(!empty($T['top_units']))
					<a href="javascript:void(0)" class="daily-col-action-link" id="dailyTheftRankingToggle" onclick="dailyToggleRankingCriterion()">
						Ver top productos por costo →
					</a>
				@endif
			</div>

			@if(empty($T['top_units']) || count($T['top_units']) === 0)
				<!-- Estado Vacío cuando no hay productos hurtados -->
				<div class="daily-theft-empty-state py-4">
					<div class="daily-theft-empty-icon" style="width: 44px; height: 44px; font-size: 1.25rem;">
						<i class="fa-solid fa-chart-column"></i>
					</div>
					<div class="daily-theft-empty-title">Sin datos para elaborar ranking</div>
					<p class="daily-theft-empty-sub">No hay casos confirmados en el período y filtros elegidos.</p>
				</div>
			@else
				<!-- Ranking de Productos con Fotografía y Datos Reales -->
				<div class="table-responsive">
					<table class="table table-sm dlx-table mb-0" id="dailyTheftTopProductsTable">
						<thead>
							<tr>
								<th>#</th>
								<th>Producto</th>
								<th class="text-right">Cantidad</th>
								<th class="text-right">Costo</th>
								<th class="text-right">Tipo</th>
							</tr>
						</thead>
						<tbody>
							@foreach(array_slice($T['top_units'], 0, 10) as $idx => $prod)
								<tr>
									<td class="font-weight-700 text-muted">{{ $idx + 1 }}</td>
									<td>
										<div class="d-flex align-items-center gap-2">
											<img src="{{ $productPhoto($prod) }}" 
											     alt="{{ $prod['name'] }}" 
											     style="width: 30px; height: 30px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0;"
											     onerror="this.src='{{ $U('/img/control_center/robo_tienda_gondolas.jpg') }}';">
											<div>
												<a href="{{ $U('/kardex?product_id=' . $prod['product_id']) }}" class="text-dark font-weight-700 text-decoration-none">
													{{ $prod['name'] }}
												</a>
												<div class="font-mono text-muted small" style="font-size: 0.68rem;">{{ $prod['sku'] ?: '—' }}</div>
											</div>
										</div>
									</td>
									<td class="text-right font-weight-600">
										{{ number_format($prod['units'], 2) }} {{ $prod['qu_name'] ?? 'u.' }}
									</td>
									<td class="text-right font-weight-700 text-dark">
										{{ $money($prod['cost']) }}
									</td>
									<td class="text-right">
										<span class="daily-badge-chip {{ $prod['type'] === 'Interno' ? 'is-slate' : 'is-red' }}">
											{{ $prod['type'] }}
										</span>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif
		</div>

		<!-- H. RECUPERACIÓN DE MERCANCÍA Y VALOR -->
		<div class="daily-cc-card" id="dailyTheftRecoveryCard">
			<div class="daily-col-header">
				<h6 class="daily-col-title">
					<i class="fa-solid fa-rotate text-success"></i> Recuperación de mercancía y valor
				</h6>
			</div>

			<!-- 3 Bloques de Recuperación -->
			<div class="daily-rec-metrics-row">
				<!-- Mercancía recuperada -->
				<div class="daily-rec-metric-box">
					<div class="daily-rec-metric-lbl">Mercancía recuperada</div>
					<div class="daily-rec-metric-val">{{ $money($Rc['goods'] ?? 0) }}</div>
					<div class="daily-rec-metric-sub">Física valorizada</div>
				</div>

				<!-- Recuperación financiera -->
				<div class="daily-rec-metric-box">
					<div class="daily-rec-metric-lbl">Recuperación financiera</div>
					<div class="daily-rec-metric-val">{{ $money($Rc['financial'] ?? 0) }}</div>
					<div class="daily-rec-metric-sub">Compensada / abonada</div>
				</div>

				<!-- Tasa de recuperación -->
				<div class="daily-rec-metric-box">
					<div class="daily-rec-metric-lbl">Tasa de recuperación</div>
					<div class="daily-rec-metric-val">
						@if($totalTheft > 0 && ($Rc['pct'] ?? null) !== null)
							{{ number_format($Rc['pct'], 1) }}%
						@else
							<span class="text-muted">—</span>
						@endif
					</div>
					<div class="daily-rec-metric-sub">
						@if($totalTheft > 0)
							sobre hurto confirmado
						@else
							Sin base de cálculo
						@endif
					</div>
				</div>
			</div>

			<!-- Referencia Externa Sectorial (Punto 13: Bloque secundario no operativo) -->
			<div class="daily-rec-benchmark-note mt-3">
				<i class="fa-solid fa-info-circle text-muted mr-1"></i>
				<strong>Referencia sectorial (Censo 2022):</strong> La recuperación promedio en retail redujo la merma en un 17.47% (datos de benchmarking externo del sector, presentados con fines comparativos; no constituyen meta automática de DAILY).
			</div>
		</div>

	</div>

	<!-- ======================================================================
	     SECCIÓN I: MÓDULOS CONECTADOS CON CENTRO DE CONTROL
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
					<a href="{{ $U('/quality') }}" class="daily-conn-item">
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
								<div class="daily-conn-item-sub">Desglose de merma (censo) en Reportes</div>
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
     MODAL DE DETALLE DE CASO SEGURO (SIN DATOS PERSONALES)
     ========================================================================== -->
<div class="modal fade" id="dailyCaseDetailModal" tabindex="-1" role="dialog" aria-labelledby="dailyCaseModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header border-bottom py-3 px-4">
				<div class="d-flex align-items-center gap-2">
					<div class="daily-theft-icon-badge" style="width: 32px; height: 32px; font-size: 1rem;">
						<i class="fa-solid fa-file-shield"></i>
					</div>
					<div>
						<h6 class="modal-title font-weight-800 text-dark mb-0" id="dailyCaseModalTitle">Detalle del Caso</h6>
						<span class="text-muted small" id="dailyCaseModalSub">Información confidencial de prevención</span>
					</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" id="dailyCaseModalBody">
				<!-- Contenido dinámico inyectado por JS -->
			</div>
			<div class="modal-footer border-top py-2 px-4 bg-light d-flex justify-content-between">
				<span class="small text-muted"><i class="fa-solid fa-shield mr-1"></i>Revisión humana auditada</span>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-sm btn-success" id="dailyCaseModalEditBtn" style="display: none;">
						Completar Registro
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     SCRIPTS INTERACTIVOS DE LA PESTAÑA: NAVEGACIÓN, FILTRADO Y GRÁFICOS
     ========================================================================== -->
<script>
(function() {
	var T = {!! json_encode($T) !!};
	var I = {!! json_encode($I) !!};
	var C = (window.DairyLoss && window.DairyLoss.colors) ? window.DairyLoss.colors : { graphite: '#334155', red: '#ef4444', teal: '#0d9488' };
	var monthsK = {!! json_encode(array_keys($T['monthly'] ?? [])) !!};

	// Inicialización de Gráficos cuando hay datos confirmados
	if (T && T.incidents > 0 && window.DairyLoss) {
		if (document.getElementById('dailyTheftDonut')) {
			DairyLoss.donut('dailyTheftDonut', ['Interno', 'Externo'], [T.internal, T.external], [C.graphite, C.red]);
		}
		if (document.getElementById('dailyTheftMonthly') && monthsK && monthsK.length > 0) {
			DairyLoss.bars('dailyTheftMonthly', monthsK, [
				{ label: 'Interno', data: monthsK.map(function(k) { return (T.monthly[k] && T.monthly[k].internal) || 0; }), backgroundColor: C.graphite },
				{ label: 'Externo', data: monthsK.map(function(k) { return (T.monthly[k] && T.monthly[k].external) || 0; }), backgroundColor: C.red }
			], 'cost', true);
		}
		if (document.getElementById('dailyTheftMods') && I && I.modalities && I.modalities.length > 0) {
			DairyLoss.ranked('dailyTheftMods', I.modalities.map(function(m) { return { label: m.label, value: m.cost, key: m.key }; }), C.graphite, 'cost');
		}
		if (document.getElementById('dailyTheftChan') && I && I.channels && I.channels.length > 0) {
			DairyLoss.ranked('dailyTheftChan', I.channels.map(function(c) { return { label: c.label, value: c.incidents, key: c.key }; }), C.teal, 'units');
		}
	}

	// Cambio de sub-pestaña de casos (Confirmados, En investigación, Recuperaciones, Descartados)
	window.dailySwitchCasesTab = function(btn, filterType) {
		$('.daily-cases-tab-btn').removeClass('is-active');
		$(btn).addClass('is-active');

		var rows = $('#dailyCasesTableBody tr.case-row');
		var visibleCount = 0;

		rows.each(function() {
			var row = $(this);
			var cType = row.data('case-type');
			var match = false;

			if (filterType === 'confirmados' && cType === 'confirmados') {
				match = true;
			} else if (filterType === 'investigacion' && cType === 'investigacion') {
				match = true;
			} else if (filterType === 'recuperaciones') {
				var recText = row.find('td:nth-child(7)').text();
				if (recText && recText.indexOf('$0.00') === -1 && recText.indexOf('—') === -1) {
					match = true;
				}
			} else if (filterType === 'descartados') {
				match = false; // Sin descartados registrados
			}

			if (match) {
				row.show();
				visibleCount++;
			} else {
				row.hide();
			}
		});

		var emptyBox = $('#dailyTheftEmptyStateBox');
		if (visibleCount === 0) {
			emptyBox.show();
			$('#dailyCasesMasterTable').hide();
		} else {
			emptyBox.hide();
			$('#dailyCasesMasterTable').show();
		}
	};

	// Búsqueda en tiempo real en la tabla de casos
	window.dailyFilterCasesTable = function(query) {
		var q = (query || '').toLowerCase().trim();
		var activeFilter = $('.daily-cases-tab-btn.is-active').data('cases-filter') || 'confirmados';
		var rows = $('#dailyCasesTableBody tr.case-row');
		var visibleCount = 0;

		rows.each(function() {
			var row = $(this);
			var cType = row.data('case-type');
			var sText = row.data('search') || '';

			var typeMatch = (activeFilter === 'confirmados' && cType === 'confirmados') ||
			                (activeFilter === 'investigacion' && cType === 'investigacion') ||
			                (activeFilter === 'recuperaciones' && row.find('td:nth-child(7)').text().indexOf('$0.00') === -1);

			if (typeMatch && (q === '' || sText.indexOf(q) !== -1)) {
				row.show();
				visibleCount++;
			} else {
				row.hide();
			}
		});

		var emptyBox = $('#dailyTheftEmptyStateBox');
		if (visibleCount === 0) {
			emptyBox.show();
			$('#dailyCasesMasterTable').hide();
		} else {
			emptyBox.hide();
			$('#dailyCasesMasterTable').show();
		}
	};

	// Restablecer filtros de prevención
	window.dailyResetTheftFilters = function(btn) {
		var form = $(btn).closest('form');
		form.find('select').val('');
		form.find('#tf-from').val('{{ date("Y-m-01") }}');
		form.find('#tf-to').val('{{ date("Y-m-t") }}');
		DairyLoss.applyFilters(form[0]);
	};

	// Modal de inspección de caso seguro
	window.dailyOpenCaseModal = function(item, det) {
		if (!item) return;
		var docNumber = item.doc || ('CASO-' + item.ref);
		var modalTitle = 'Detalle de Caso: ' + docNumber;
		var modalSub = (item.product || 'Producto') + ' · ' + (item.sku || 'Sin SKU');
		
		$('#dailyCaseModalTitle').text(modalTitle);
		$('#dailyCaseModalSub').text(modalSub);

		var costFmt = '$' + (parseFloat(item.cost || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
		var unitsFmt = (parseFloat(item.units || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })) + ' ' + (item.qu_name || 'u.');
		var recFmt = '$' + ((parseFloat(det ? det.goods : 0) + parseFloat(item.recovered || 0)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

		var html = '<div class="row mb-3">'
			+ '<div class="col-md-6 mb-2"><strong>ID / Documento:</strong> <span class="font-mono">' + DairyUI.esc(docNumber) + '</span></div>'
			+ '<div class="col-md-6 mb-2"><strong>Fecha de registro:</strong> ' + DairyUI.esc(item.d || '—') + '</div>'
			+ '<div class="col-md-6 mb-2"><strong>Sucursal:</strong> ' + DairyUI.esc(item.branch || '—') + '</div>'
			+ '<div class="col-md-6 mb-2"><strong>Departamento:</strong> ' + DairyUI.esc(item.department || '—') + '</div>'
			+ '<div class="col-md-6 mb-2"><strong>Clasificación:</strong> <span class="daily-badge-chip ' + (item.type === 'Interno' ? 'is-slate' : 'is-red') + '">' + DairyUI.esc(item.type || 'Confirmado') + '</span></div>'
			+ '<div class="col-md-6 mb-2"><strong>Modalidad:</strong> ' + DairyUI.esc(det && det.modality ? det.modality : 'Sin registrar') + '</div>'
			+ '<div class="col-md-6 mb-2"><strong>Canal de detección:</strong> ' + DairyUI.esc(det && det.channel ? det.channel : '—') + '</div>'
			+ '<div class="col-md-6 mb-2"><strong>Impacto confirmado:</strong> <span class="text-danger font-weight-700">' + costFmt + ' (' + unitsFmt + ')</span></div>'
			+ '<div class="col-md-6 mb-2"><strong>Recuperación lograda:</strong> <span class="text-success font-weight-700">' + recFmt + '</span></div>'
			+ '<div class="col-md-6 mb-2"><strong>Documento de soporte:</strong> <a href="' + Grocy.BaseUrl + '/document/waste/' + item.ref + '" target="_blank" class="text-primary"><i class="fa-solid fa-file-pdf mr-1"></i>Ver comprobante</a></div>'
			+ '</div>'
			+ '<div class="alert alert-light border small text-muted mb-0">'
			+ '<i class="fa-solid fa-user-shield text-success mr-1"></i>'
			+ 'Registro verificado por revisión humana y dictamen de prevención. Cumplimiento de políticas de confidencialidad y segregación de funciones.'
			+ '</div>';

		$('#dailyCaseModalBody').html(html);

		// Botón completar detalle si existe función dlxTheftDetail
		var editBtn = $('#dailyCaseModalEditBtn');
		if (det && window.dlxTheftDetail) {
			editBtn.show().off('click').on('click', function() {
				$('#dailyCaseDetailModal').modal('hide');
				window.dlxTheftDetail(item.ref, det.type_key || 'interno', { m: det.theft_modality, c: det.detection_channel, g: det.goods, f: det.recovered });
			});
		} else {
			editBtn.hide();
		}

		$('#dailyCaseDetailModal').modal('show');
	};

	// Diálogo para completar modalidad y canal (Preserva dlxTheftDetail existente)
	window.dlxTheftDetail = function(id, type, cur) {
		var mods = {!! json_encode($mods) !!}[type] || {};
		var chans = {!! json_encode($chans) !!};
		var o = function(obj, sel) {
			return '<option value="">Sin registrar</option>' + Object.keys(obj).map(function(k) {
				return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + DairyUI.esc(obj[k]) + '</option>';
			}).join('');
		};
		var html = '<div class="form-group"><label>Modalidad de hurto</label><select name="theft_modality" class="form-control form-control-sm">' + o(mods, cur.m) + '</select></div>'
			+ '<div class="form-group"><label>Canal de detección</label><select name="detection_channel" class="form-control form-control-sm">' + o(chans, cur.c) + '</select></div>'
			+ '<div class="form-row">'
			+ '<div class="form-group col-6"><label>Mercancía recuperada ($)</label><input type="number" step="0.01" min="0" name="recovered_goods_value" class="form-control form-control-sm" value="' + (cur.g || '') + '"></div>'
			+ '<div class="form-group col-6"><label>Recuperación financiera ($)</label><input type="number" step="0.01" min="0" name="financial_recovery_amount" class="form-control form-control-sm" value="' + (cur.f || '') + '"></div>'
			+ '</div>'
			+ '<p class="small text-muted mb-0"><i class="fa-solid fa-lock mr-1"></i>No registre nombres ni datos de personas. La investigación se gestiona con revisión humana en Prevención de pérdidas.</p>';
		
		if (window.DairyLoss && DairyLoss.detailsDialog) {
			DairyLoss.detailsDialog(id, 'Detalle de modalidad y recuperación', html);
		}
	};
})();
</script>
