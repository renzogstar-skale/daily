@php
	$auditList = $auditLogs ?? [];
	$totalCount = $totalAuditCount ?? count($auditList);
	$tz = $timezone ?? (defined('GROCY_TIMEZONE') && GROCY_TIMEZONE !== '' ? GROCY_TIMEZONE : date_default_timezone_get());
	$userList = $users ?? [];
	
	// Fechas iniciales para resumen
	$timestamps = [];
	foreach ($auditList as $log) {
		if (!empty($log->created_timestamp)) {
			$timestamps[] = $log->created_timestamp;
		}
	}
	sort($timestamps);
	$firstDate = !empty($timestamps) ? date('d M Y', strtotime($timestamps[0])) : '—';
	$lastDate = !empty($timestamps) ? date('d M Y', strtotime(end($timestamps))) : '—';
	$latestTimestamp = !empty($timestamps) ? date('d M Y H:i:s', strtotime(end($timestamps))) : '—';
	
	// Verificación de referencias sistema
	$hasSystemRef = false;
	foreach ($auditList as $log) {
		if (empty($log->case_number)) {
			$hasSystemRef = true;
			break;
		}
	}
@endphp

<div class="daily-audit-module" id="dailyAuditModule">
	<style>
		/* Estilos generales del módulo Auditoría y Trazabilidad */
		.daily-audit-module {
			font-family: inherit;
			color: #1e293b;
		}
		
		/* 1. Header Banner Card con fotografía degradada */
		.daily-audit-banner-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			margin-bottom: 18px;
			overflow: hidden;
			position: relative;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
			padding: 20px 24px;
		}
		.daily-audit-banner-top {
			display: flex;
			align-items: center;
			justify-content: space-between;
			position: relative;
			z-index: 2;
			margin-bottom: 20px;
		}
		.daily-audit-banner-left {
			display: flex;
			align-items: center;
			gap: 14px;
		}
		.daily-audit-banner-icon {
			width: 44px;
			height: 44px;
			border-radius: 12px;
			background: #eafaf1;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
		}
		.daily-audit-banner-title {
			font-size: 1.25rem;
			font-weight: 800;
			color: #0f172a;
			margin: 0;
			letter-spacing: -0.01em;
		}
		.daily-audit-banner-sub {
			font-size: 0.84rem;
			color: #64748b;
			margin: 2px 0 0 0;
		}
		.daily-audit-banner-img {
			position: absolute;
			right: 0;
			top: 0;
			bottom: 0;
			width: 45%;
			max-width: 400px;
			background-image: url('{{ $U('/img/control_center/auditoria_banner.jpg') }}');
			background-size: cover;
			background-position: right center;
			background-repeat: no-repeat;
			pointer-events: none;
			mask-image: linear-gradient(to right, transparent, black 35%);
			-webkit-mask-image: linear-gradient(to right, transparent, black 35%);
			z-index: 1;
		}
		
		/* Barra de Filtros integrada */
		.daily-audit-filters-row {
			position: relative;
			z-index: 3;
			display: grid;
			grid-template-columns: 1.2fr 1fr 1fr;
			gap: 12px;
			margin-bottom: 12px;
		}
		.daily-audit-filters-row-2 {
			position: relative;
			z-index: 3;
			display: grid;
			grid-template-columns: 1.2fr 1fr 1.3fr auto auto;
			gap: 12px;
			align-items: end;
		}
		.daily-audit-filter-item label {
			font-size: 0.74rem;
			font-weight: 700;
			color: #64748b;
			margin-bottom: 4px;
			display: block;
			text-transform: uppercase;
			letter-spacing: 0.02em;
		}
		.daily-audit-input-group {
			position: relative;
			display: flex;
			align-items: center;
		}
		.daily-audit-input-group i {
			position: absolute;
			left: 12px;
			color: #94a3b8;
			font-size: 0.85rem;
			pointer-events: none;
		}
		.daily-audit-input {
			height: 38px;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 6px 12px;
			font-size: 0.84rem;
			color: #1e293b;
			background: #ffffff;
			width: 100%;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}
		.daily-audit-input.has-icon {
			padding-left: 34px;
		}
		.daily-audit-input:focus {
			border-color: #00C83B;
			outline: none;
			box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12);
		}
		.daily-audit-range-group {
			display: flex;
			align-items: center;
			gap: 8px;
			background: #ffffff;
			border: 1px solid #cbd5e1;
			border-radius: 8px;
			padding: 2px 10px;
			height: 38px;
		}
		.daily-audit-range-group input {
			border: none;
			outline: none;
			font-size: 0.82rem;
			width: 90px;
			background: transparent;
			color: #1e293b;
		}
		.daily-audit-range-sep {
			color: #94a3b8;
			font-size: 0.8rem;
		}
		.daily-audit-btn-clean {
			height: 38px;
			border: 1px solid #e2e8f0;
			background: #f8fafc;
			color: #475569;
			border-radius: 8px;
			padding: 0 16px;
			font-size: 0.82rem;
			font-weight: 600;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			transition: all 0.15s ease;
			white-space: nowrap;
		}
		.daily-audit-btn-clean:hover {
			background: #f1f5f9;
			color: #0f172a;
			border-color: #cbd5e1;
		}
		.daily-audit-btn-export {
			height: 38px;
			border: 1px solid #00C83B;
			background: #ffffff;
			color: #00C83B;
			border-radius: 8px;
			padding: 0 18px;
			font-size: 0.84rem;
			font-weight: 700;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			transition: all 0.15s ease;
			white-space: nowrap;
		}
		.daily-audit-btn-export:hover {
			background: #00C83B;
			color: #ffffff;
			box-shadow: 0 2px 6px rgba(0, 200, 59, 0.2);
		}

		/* 2. Resumen del Alcance (4 metric pill cards) */
		.daily-audit-scope-grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 12px;
			margin-bottom: 18px;
		}
		.daily-audit-scope-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 14px 16px;
			display: flex;
			align-items: center;
			gap: 14px;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
		}
		.daily-audit-scope-icon {
			width: 40px;
			height: 40px;
			border-radius: 10px;
			background: #f8fafc;
			border: 1px solid #edf2f7;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1rem;
			color: #64748b;
			flex-shrink: 0;
		}
		.daily-audit-scope-meta {
			display: flex;
			flex-direction: column;
			min-width: 0;
		}
		.daily-audit-scope-label {
			font-size: 0.74rem;
			font-weight: 600;
			color: #64748b;
			margin-bottom: 2px;
			text-transform: uppercase;
			letter-spacing: 0.02em;
		}
		.daily-audit-scope-val {
			font-size: 1.05rem;
			font-weight: 800;
			color: #0f172a;
			line-height: 1.2;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.daily-audit-scope-sub {
			font-size: 0.72rem;
			color: #94a3b8;
			margin-top: 2px;
		}

		/* 3. Estructura principal en dos columnas */
		.daily-audit-main-layout {
			display: grid;
			grid-template-columns: 1.6fr 1fr;
			gap: 18px;
			margin-bottom: 18px;
			align-items: start;
		}
		
		/* Tarjetas blancas estándar */
		.daily-audit-card {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 18px 20px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
			position: relative;
		}
		.daily-audit-card-title {
			font-size: 1rem;
			font-weight: 800;
			color: #0f172a;
			margin-bottom: 12px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-audit-card-title i {
			color: #00C83B;
		}
		.daily-audit-info-banner {
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 10px 14px;
			font-size: 0.8rem;
			color: #475569;
			display: flex;
			align-items: center;
			margin-bottom: 16px;
			line-height: 1.4;
		}

		/* Tabla de eventos */
		.daily-audit-table-wrap {
			border: 1px solid #edf2f7;
			border-radius: 10px;
			overflow: hidden;
			margin-bottom: 16px;
		}
		.daily-audit-table {
			width: 100%;
			border-collapse: separate;
			border-spacing: 0;
			font-size: 0.82rem;
		}
		.daily-audit-table th {
			background: #f8fafc;
			color: #64748b;
			font-weight: 700;
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.03em;
			padding: 10px 12px;
			border-bottom: 1px solid #e2e8f0;
			white-space: nowrap;
		}
		.daily-audit-table td {
			padding: 12px;
			border-bottom: 1px solid #f1f5f9;
			color: #1e293b;
			vertical-align: middle;
			background: #ffffff;
			transition: background 0.12s ease;
		}
		.daily-audit-row {
			cursor: pointer;
		}
		.daily-audit-row:hover td {
			background: #f8fafc;
		}
		.daily-audit-row.active td {
			background: #f0fdf4 !important;
			color: #0f172a;
		}
		.daily-audit-row.active td:first-child {
			border-left: 3px solid #00C83B;
		}

		/* Badges de transición de estado */
		.daily-status-badge {
			display: inline-flex;
			align-items: center;
			padding: 2px 8px;
			border-radius: 6px;
			font-size: 0.72rem;
			font-weight: 700;
			white-space: nowrap;
		}
		.badge-detected { background: #dcfce7; color: #15803d; }
		.badge-investigating { background: #fef3c7; color: #b45309; }
		.badge-action { background: #f1f5f9; color: #475569; }
		.badge-corrected { background: #ecfdf5; color: #059669; }
		.badge-verified { background: #dcfce7; color: #16a34a; }
		.badge-closed { background: #f1f5f9; color: #64748b; }
		.badge-pending { background: #fef3c7; color: #d97706; }
		.badge-approved { background: #dcfce7; color: #15803d; }
		.badge-none { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

		.daily-transition-cell {
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}
		.daily-transition-arrow {
			color: #94a3b8;
			font-size: 0.75rem;
		}

		/* Píldoras de filtro rápido debajo de la tabla */
		.daily-pill-filters {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		.daily-pill-btn {
			border: 1px solid #e2e8f0;
			background: #ffffff;
			color: #64748b;
			border-radius: 20px;
			padding: 4px 14px;
			font-size: 0.78rem;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.daily-pill-btn:hover {
			background: #f8fafc;
			color: #0f172a;
			border-color: #cbd5e1;
		}
		.daily-pill-btn.active {
			background: #00C83B;
			color: #ffffff;
			border-color: #00C83B;
			box-shadow: 0 1px 3px rgba(0, 200, 59, 0.25);
		}

		/* 4. Panel lateral de Detalle */
		.daily-audit-detail-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			border-bottom: 1px solid #f1f5f9;
			padding-bottom: 12px;
			margin-bottom: 14px;
		}
		.daily-audit-detail-case {
			font-size: 1.05rem;
			font-weight: 800;
			color: #0f172a;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		.daily-audit-detail-case i {
			color: #64748b;
		}
		.daily-audit-detail-date {
			font-size: 0.78rem;
			color: #64748b;
			margin-top: 2px;
		}
		.daily-audit-tabs {
			display: flex;
			border-bottom: 1px solid #e2e8f0;
			margin-bottom: 14px;
			gap: 16px;
		}
		.daily-audit-tab-btn {
			padding: 6px 4px;
			border: none;
			background: none;
			font-size: 0.82rem;
			font-weight: 600;
			color: #64748b;
			cursor: pointer;
			position: relative;
			transition: color 0.15s ease;
		}
		.daily-audit-tab-btn:hover {
			color: #0f172a;
		}
		.daily-audit-tab-btn.active {
			color: #00C83B;
			font-weight: 700;
		}
		.daily-audit-tab-btn.active::after {
			content: '';
			position: absolute;
			bottom: -1px;
			left: 0;
			right: 0;
			height: 2px;
			background: #00C83B;
			border-radius: 2px;
		}
		.daily-audit-field-row {
			display: grid;
			grid-template-columns: 100px 1fr;
			gap: 10px;
			padding: 6px 0;
			font-size: 0.82rem;
			border-bottom: 1px dashed #f8fafc;
		}
		.daily-audit-field-lbl {
			color: #64748b;
			font-weight: 600;
		}
		.daily-audit-field-val {
			color: #0f172a;
			word-break: break-word;
		}

		/* 5. Línea de tiempo */
		.daily-audit-timeline-wrap {
			position: relative;
			padding-left: 20px;
			margin-top: 10px;
		}
		.daily-audit-timeline-wrap::before {
			content: '';
			position: absolute;
			top: 8px;
			bottom: 12px;
			left: 6px;
			width: 2px;
			background: #e2e8f0;
		}
		.daily-timeline-item {
			position: relative;
			padding-bottom: 16px;
			cursor: pointer;
		}
		.daily-timeline-item:last-child {
			padding-bottom: 0;
		}
		.daily-timeline-dot {
			position: absolute;
			left: -20px;
			top: 4px;
			width: 14px;
			height: 14px;
			border-radius: 50%;
			background: #ffffff;
			border: 3px solid #cbd5e1;
			z-index: 2;
			transition: transform 0.15s ease, border-color 0.15s ease;
		}
		.daily-timeline-dot.tone-green { border-color: #00C83B; }
		.daily-timeline-dot.tone-amber { border-color: #f59e0b; }
		.daily-timeline-dot.tone-grey { border-color: #94a3b8; }
		.daily-timeline-item:hover .daily-timeline-dot {
			transform: scale(1.2);
		}
		.daily-timeline-content {
			background: #f8fafc;
			border: 1px solid #edf2f7;
			border-radius: 8px;
			padding: 8px 12px;
			transition: all 0.15s ease;
		}
		.daily-timeline-item:hover .daily-timeline-content {
			background: #ffffff;
			border-color: #cbd5e1;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
		}
		.daily-timeline-time {
			font-size: 0.72rem;
			color: #64748b;
			margin-bottom: 2px;
			font-family: monospace;
		}
		.daily-timeline-action {
			font-size: 0.82rem;
			font-weight: 700;
			color: #0f172a;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 6px;
		}
		.daily-timeline-meta {
			font-size: 0.74rem;
			color: #64748b;
			margin-top: 2px;
		}

		/* 6. Alerta inferior de revisión de referencias */
		.daily-audit-bottom-alert {
			background: #fffbeb;
			border: 1px solid #fde68a;
			border-radius: 12px;
			padding: 14px 18px;
			display: flex;
			align-items: flex-start;
			gap: 14px;
			margin-top: 18px;
		}
		.daily-audit-bottom-alert i {
			color: #f59e0b;
			font-size: 1.25rem;
			margin-top: 2px;
			flex-shrink: 0;
		}
		.daily-audit-bottom-alert-title {
			font-size: 0.92rem;
			font-weight: 800;
			color: #92400e;
			margin: 0 0 2px 0;
		}
		.daily-audit-bottom-alert-text {
			font-size: 0.82rem;
			color: #b45309;
			margin: 0;
			line-height: 1.4;
		}

		/* Adaptación Móvil */
		@media (max-width: 992px) {
			.daily-audit-main-layout {
				grid-template-columns: 1fr;
			}
			.daily-audit-scope-grid {
				grid-template-columns: repeat(2, 1fr);
			}
			.daily-audit-filters-row {
				grid-template-columns: 1fr;
			}
			.daily-audit-filters-row-2 {
				grid-template-columns: 1fr;
			}
			.daily-audit-banner-img {
				display: none;
			}
		}
		@media (max-width: 576px) {
			.daily-audit-scope-grid {
				grid-template-columns: 1fr;
			}
			.daily-audit-table-wrap {
				overflow-x: auto;
			}
		}
	</style>

	<!-- 1. ENCABEZADO Y FILTROS -->
	<div class="daily-audit-banner-card">
		<div class="daily-audit-banner-top">
			<div class="daily-audit-banner-left">
				<div class="daily-audit-banner-icon">
					<i class="fa-solid fa-clock-rotate-left" style="color: #00C83B; font-size: 1.25rem;"></i>
				</div>
				<div>
					<h4 class="daily-audit-banner-title">Auditoría y trazabilidad</h4>
					<p class="daily-audit-banner-sub">Consulta eventos, modificaciones y aprobaciones con su registro de origen.</p>
				</div>
			</div>
			<div class="daily-audit-banner-img"></div>
		</div>

		<!-- Formulario de Filtros Funcionales -->
		<form id="dailyAuditFilterForm" onsubmit="return false;">
			<div class="daily-audit-filters-row">
				<!-- Rango de fechas -->
				<div class="daily-audit-filter-item">
					<label>Rango de fechas</label>
					<div class="daily-audit-range-group">
						<input type="text" id="auditDateStart" value="10/01/2026" placeholder="DD/MM/AAAA">
						<span class="daily-audit-range-sep"><i class="fa-solid fa-arrow-right"></i></span>
						<input type="text" id="auditDateEnd" value="10/31/2026" placeholder="DD/MM/AAAA">
						<i class="fa-regular fa-calendar ml-auto text-muted" style="cursor: pointer;"></i>
					</div>
				</div>

				<!-- Usuario -->
				<div class="daily-audit-filter-item">
					<label>Usuario</label>
					<select class="daily-audit-input" id="auditUserFilter">
						<option value="">Todos los usuarios</option>
						@foreach($userList as $u)
						<option value="{{ $u->username }}">{{ $u->first_name ? $u->first_name . ' ' . $u->last_name : $u->username }}</option>
						@endforeach
					</select>
				</div>

				<!-- Acción -->
				<div class="daily-audit-filter-item">
					<label>Acción</label>
					<select class="daily-audit-input" id="auditActionFilter">
						<option value="">Todas las acciones</option>
						<option value="registro">Incidente de merma registrado</option>
						<option value="actualizacion">Actualización de investigación</option>
						<option value="transicion">Transición de estado</option>
						<option value="aprobacion">Merma aprobada y descontada</option>
					</select>
				</div>
			</div>

			<div class="daily-audit-filters-row-2">
				<!-- Caso / Referencia -->
				<div class="daily-audit-filter-item">
					<label>Caso / Referencia</label>
					<input type="text" class="daily-audit-input" id="auditCaseFilter" placeholder="Buscar número de caso...">
				</div>

				<!-- Estado -->
				<div class="daily-audit-filter-item">
					<label>Estado</label>
					<select class="daily-audit-input" id="auditStatusFilter">
						<option value="">Todos los estados</option>
						<option value="detected">Detectado</option>
						<option value="investigating">En investigación</option>
						<option value="action_assigned">Acción asignada</option>
						<option value="corrected">Corregido</option>
						<option value="verified">Verificado</option>
						<option value="closed">Cerrado</option>
						<option value="pending_approval">Pendiente de aprobación</option>
						<option value="approved">Aprobado</option>
					</select>
				</div>

				<!-- Búsqueda en eventos -->
				<div class="daily-audit-filter-item">
					<label>Búsqueda libre</label>
					<div class="daily-audit-input-group">
						<i class="fa-solid fa-magnifying-glass"></i>
						<input type="text" class="daily-audit-input has-icon" id="auditSearchInput" placeholder="Buscar en eventos...">
					</div>
				</div>

				<!-- Botones -->
				<div>
					<button type="button" class="daily-audit-btn-clean" id="auditBtnReset">
						<i class="fa-solid fa-rotate-left"></i> Limpiar
					</button>
				</div>
				<div>
					<button type="button" class="daily-audit-btn-export" id="auditBtnExport">
						<i class="fa-solid fa-download"></i> Exportar
					</button>
				</div>
			</div>

			<div class="mt-2 text-right">
				<span class="small text-muted font-weight-500">
					<i class="fa-regular fa-clock mr-1"></i>Zona horaria de auditoría: <strong>{{ $tz }}</strong>
				</span>
			</div>
		</form>
	</div>

	<!-- 2. RESUMEN DEL ALCANCE (4 Métricas) -->
	<div class="daily-audit-scope-grid">
		<!-- Eventos visibles -->
		<div class="daily-audit-scope-card">
			<div class="daily-audit-scope-icon text-primary">
				<i class="fa-regular fa-file-lines"></i>
			</div>
			<div class="daily-audit-scope-meta">
				<span class="daily-audit-scope-label">Eventos visibles</span>
				<div class="daily-audit-scope-val"><span id="metricVisibleCount">{{ count($auditList) }}</span> <span style="font-size: 0.76rem; font-weight: 500; color: #64748b;">de {{ $totalCount }}</span></div>
				<span class="daily-audit-scope-sub">En esta vista</span>
			</div>
		</div>

		<!-- Fechas visibles -->
		<div class="daily-audit-scope-card">
			<div class="daily-audit-scope-icon text-muted">
				<i class="fa-regular fa-calendar"></i>
			</div>
			<div class="daily-audit-scope-meta">
				<span class="daily-audit-scope-label">Fechas visibles</span>
				<div class="daily-audit-scope-val" id="metricDateRange">{{ $firstDate }} – {{ $lastDate }}</div>
				<span class="daily-audit-scope-sub">Resultados consultados</span>
			</div>
		</div>

		<!-- Último evento visible -->
		<div class="daily-audit-scope-card">
			<div class="daily-audit-scope-icon text-info">
				<i class="fa-regular fa-clock"></i>
			</div>
			<div class="daily-audit-scope-meta">
				<span class="daily-audit-scope-label">Último evento visible</span>
				<div class="daily-audit-scope-val" id="metricLatestEvent">{{ $latestTimestamp }}</div>
				<span class="daily-audit-scope-sub">Dentro del alcance</span>
			</div>
		</div>

		<!-- Cobertura de trazabilidad -->
		<div class="daily-audit-scope-card">
			<div class="daily-audit-scope-icon text-muted">
				<i class="fa-solid fa-book-open"></i>
			</div>
			<div class="daily-audit-scope-meta">
				<span class="daily-audit-scope-label">Cobertura de trazabilidad</span>
				<div class="daily-audit-scope-val" style="color: {{ $hasSystemRef ? '#d97706' : '#15803d' }};">
					{{ $hasSystemRef ? 'Revisar referencias' : 'Completa' }}
				</div>
				<span class="daily-audit-scope-sub">{{ $hasSystemRef ? 'Existen referencias SISTEMA' : '100% asociadas a expediente' }}</span>
			</div>
		</div>
	</div>

	<!-- 3. ÁREA PRINCIPAL EN DOS COLUMNAS -->
	<div class="daily-audit-main-layout">
		<!-- COLUMNA IZQUIERDA: REGISTRO DE AUDITORÍA (TABLA) -->
		<div class="daily-audit-card">
			<div class="daily-audit-card-title">
				<i class="fa-regular fa-file-lines"></i> Registro de auditoría
			</div>

			<div class="daily-audit-info-banner">
				<i class="fa-solid fa-circle-info mr-2 text-primary" style="font-size: 1rem;"></i>
				<div>
					Visualización de solo lectura. Consultar detalle de cada evento para ver su registro de origen. El evento mostrado conserva la información original.
				</div>
			</div>

			<div class="daily-audit-table-wrap">
				<table class="daily-audit-table" id="auditTable">
					<thead>
						<tr>
							<th>FECHA / HORA</th>
							<th>REFERENCIA</th>
							<th>ACCIÓN</th>
							<th>USUARIO</th>
							<th>ANTES → DESPUÉS</th>
							<th>DETALLE</th>
						</tr>
					</thead>
					<tbody id="auditTableBody">
						@forelse($auditList as $idx => $log)
						@php
							$caseNum = !empty($log->case_number) ? $log->case_number : 'SISTEMA';
							$userName = !empty($log->first_name) ? $log->first_name : $log->username;
							$prevStatus = $log->previous_status;
							$newStatus = $log->new_status;
							
							// Clasificación de acción
							$actionType = 'otro';
							$titleLower = mb_strtolower($log->action_title ?? '');
							if (strpos($titleLower, 'registrado') !== false || strpos($titleLower, 'creado') !== false) {
								$actionType = 'registro';
							} elseif (strpos($titleLower, 'actualiz') !== false) {
								$actionType = 'actualizacion';
							} elseif (strpos($titleLower, 'transici') !== false) {
								$actionType = 'transicion';
							} elseif (strpos($titleLower, 'aprob') !== false || strpos($titleLower, 'descont') !== false) {
								$actionType = 'aprobacion';
							}
						@endphp
						<tr class="daily-audit-row {{ $idx === 0 ? 'active' : '' }}" 
						    data-index="{{ $idx }}"
						    data-id="{{ $log->id }}"
						    data-case="{{ $caseNum }}"
						    data-user="{{ $userName }}"
						    data-username="{{ $log->username }}"
						    data-action-title="{{ $log->action_title }}"
						    data-action-type="{{ $actionType }}"
						    data-status-prev="{{ $prevStatus ?? '' }}"
						    data-status-new="{{ $newStatus }}"
						    data-timestamp="{{ $log->created_timestamp }}"
						    data-details="{{ $log->action_details }}"
						    data-product="{{ $log->product_name ?? '' }}"
						    data-sku="{{ $log->sku ?? '' }}"
						    data-cost="{{ $log->total_cost ?? '' }}"
						    data-raw='{{ json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) }}'>
							<td class="font-mono text-muted" style="white-space: nowrap;">
								{{ $log->created_timestamp }}
							</td>
							<td class="font-mono font-weight-bold" style="white-space: nowrap;">
								{{ $caseNum }}
							</td>
							<td class="font-weight-600 text-dark">
								{{ $log->action_title }}
							</td>
							<td>
								{{ $userName }}
							</td>
							<td>
								<div class="daily-transition-cell">
									<span class="daily-status-badge {{ $prevStatus ? 'badge-' . $prevStatus : 'badge-none' }}">
										@switch($prevStatus)
											@case('detected') Detectado @break
											@case('investigating') En investigación @break
											@case('action_assigned') Acción asignada @break
											@case('corrected') Corregido @break
											@case('verified') Verificado @break
											@case('closed') Cerrado @break
											@case('pending_approval') Pendiente aprobación @break
											@case('approved') Aprobado @break
											@default Sin estado previo
										@endswitch
									</span>
									<span class="daily-transition-arrow"><i class="fa-solid fa-arrow-right"></i></span>
									<span class="daily-status-badge badge-{{ $newStatus }}">
										@switch($newStatus)
											@case('detected') Detectado @break
											@case('investigating') En investigación @break
											@case('action_assigned') Acción asignada @break
											@case('corrected') Corregido @break
											@case('verified') Verificado @break
											@case('closed') Cerrado @break
											@case('pending_approval') Pendiente de aprobación @break
											@case('approved') Aprobado @break
											@default {{ $newStatus }}
										@endswitch
									</span>
								</div>
							</td>
							<td class="text-muted" style="max-width: 200px;">
								<div class="d-flex align-items-center">
									<i class="fa-regular fa-file-lines text-muted mr-2 flex-shrink-0"></i>
									<span class="text-truncate" title="{{ $log->action_details }}">{{ $log->action_details }}</span>
								</div>
							</td>
						</tr>
						@empty
						<tr id="auditEmptyRow">
							<td colspan="6" class="text-center py-4 text-muted">
								<i class="fa-regular fa-folder-open mr-2"></i>Sin registros de auditoría encontrados para este alcance.
							</td>
						</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<!-- Píldoras de Filtro Rápido -->
			<div class="daily-pill-filters mt-2">
				<button type="button" class="daily-pill-btn active" data-pill-type="all">
					<i class="fa-solid fa-list-check mr-1"></i> Todos los eventos
				</button>
				<button type="button" class="daily-pill-btn" data-pill-type="registro">
					<i class="fa-regular fa-file-lines mr-1"></i> Registro
				</button>
				<button type="button" class="daily-pill-btn" data-pill-type="actualizacion">
					<i class="fa-solid fa-pen mr-1"></i> Actualización
				</button>
				<button type="button" class="daily-pill-btn" data-pill-type="transicion">
					<i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Transición
				</button>
				<button type="button" class="daily-pill-btn" data-pill-type="aprobacion">
					<i class="fa-solid fa-shield-halved mr-1"></i> Aprobación
				</button>
			</div>
		</div>

		<!-- COLUMNA DERECHA: DETALLE + LÍNEA DE TIEMPO -->
		<div class="d-flex flex-column gap-3" style="gap: 16px;">
			<!-- TARJETA 1: DETALLE DEL EVENTO SELECCIONADO -->
			<div class="daily-audit-card" id="auditDetailCard">
				<div class="daily-audit-card-title">
					<i class="fa-regular fa-id-card"></i> Detalle del evento seleccionado
				</div>

				<div class="daily-audit-detail-head">
					<div class="d-flex align-items-center gap-2">
						<div class="p-2 bg-light rounded text-muted mr-2">
							<i class="fa-regular fa-file-lines" style="font-size: 1.1rem;"></i>
						</div>
						<div>
							<div class="daily-audit-detail-case" id="detailCaseTitle">MRM-202610-0089</div>
							<div class="daily-audit-detail-date" id="detailTimestamp">22 oct 2026 18:44:49</div>
						</div>
					</div>
					<div>
						<span class="daily-status-badge badge-detected" id="detailStatusBadge">Detectado</span>
					</div>
				</div>

				<!-- Pestañas internas de detalle -->
				<div class="daily-audit-tabs">
					<button type="button" class="daily-audit-tab-btn active" data-tab="resumen">Resumen</button>
					<button type="button" class="daily-audit-tab-btn" data-tab="cambios">Cambios</button>
					<button type="button" class="daily-audit-tab-btn" data-tab="original">Registro original</button>
				</div>

				<!-- Contenido Pestaña Resumen -->
				<div id="detailTabResumen">
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Acción</span>
						<span class="daily-audit-field-val font-weight-700" id="detailActionTitle">Incidente de merma registrado</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Usuario</span>
						<span class="daily-audit-field-val" id="detailUser">Renzo</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Fecha y hora</span>
						<span class="daily-audit-field-val font-mono" id="detailDateTime">2026-10-22 18:44:49</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Referencia</span>
						<span class="daily-audit-field-val font-mono font-weight-700" id="detailReference">MRM-202610-0089</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Estado anterior</span>
						<span class="daily-audit-field-val" id="detailPrevStatus">Sin estado previo</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Estado nuevo</span>
						<span class="daily-audit-field-val" id="detailNewStatusWrap">
							<span class="daily-status-badge badge-detected" id="detailNewStatus">Detectado</span>
						</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Detalle</span>
						<span class="daily-audit-field-val" id="detailText" style="line-height: 1.4;">
							Reporte creado como 'MRM-202610-0089'.<br>
							Causal: A01 - Producto dañado.<br>
							Costo estimado: $16.80.
						</span>
					</div>
					<div class="daily-audit-field-row">
						<span class="daily-audit-field-lbl">Evidencia / doc</span>
						<span class="daily-audit-field-val text-muted" id="detailEvidence">No disponible</span>
					</div>
				</div>

				<!-- Contenido Pestaña Cambios -->
				<div id="detailTabCambios" style="display: none;">
					<div class="p-3 bg-light rounded mb-2">
						<div class="small font-weight-700 text-muted uppercase mb-2">Transición de Ciclo de Vida</div>
						<div class="d-flex align-items-center gap-2 mb-2" id="detailChangesTransition">
							<span class="daily-status-badge badge-none" id="changePrev">Sin estado previo</span>
							<i class="fa-solid fa-arrow-right text-muted mx-1"></i>
							<span class="daily-status-badge badge-detected" id="changeNew">Detectado</span>
						</div>
					</div>
					<div class="p-3 bg-white border rounded">
						<div class="small font-weight-700 text-muted uppercase mb-2">Campos Modificados / Registrados</div>
						<ul class="pl-3 mb-0 small text-dark" id="detailChangesList">
							<li>Creación del caso formal de merma.</li>
							<li>Valoración inicial y asignación de causal.</li>
						</ul>
					</div>
				</div>

				<!-- Contenido Pestaña Registro Original -->
				<div id="detailTabOriginal" style="display: none;">
					<div class="small text-muted mb-2 font-weight-600">Atributos sin procesar en base de datos:</div>
					<pre class="p-2 bg-light border rounded small mb-0 font-mono" id="detailJsonView" style="max-height: 220px; overflow-y: auto; font-size: 0.76rem;"></pre>
				</div>
			</div>

			<!-- TARJETA 2: SECUENCIA DE EVENTOS VISIBLES (LÍNEA DE TIEMPO) -->
			<div class="daily-audit-card" id="auditTimelineCard">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div class="daily-audit-card-title mb-0">
						<i class="fa-solid fa-timeline"></i> Secuencia de eventos visibles
					</div>
					<div>
						<button type="button" class="btn btn-sm btn-link text-success p-0 font-weight-600 small" id="btnToggleTimelineScope" title="Alternar secuencia">
							Ver historial de este caso
						</button>
					</div>
				</div>

				<div class="daily-audit-timeline-wrap" id="auditTimelineContainer">
					<!-- Los nodos de la línea de tiempo se generan reactivamente -->
				</div>
			</div>
		</div>
	</div>

	<!-- 4. ALERTA DE REVISIÓN DE REFERENCIAS -->
	<div class="daily-audit-bottom-alert" id="auditBottomAlert" style="{{ $hasSystemRef ? '' : 'display: none;' }}">
		<i class="fa-solid fa-triangle-exclamation"></i>
		<div>
			<h6 class="daily-audit-bottom-alert-title">Revisión de referencias</h6>
			<p class="daily-audit-bottom-alert-text">
				Algunos eventos muestran <strong>'SISTEMA'</strong> como referencia. Consultar el detalle de cada evento para identificar la entidad asociada.
			</p>
		</div>
	</div>
</div>

<!-- ==========================================================================
     LÓGICA REACTIVA DE AUDITORÍA (FILTROS, SELECCIÓN, EXPORTACIÓN, TIMELINE)
     ========================================================================== -->
<script>
(function() {
	// Diccionario de traducción canónica de estados
	var STATUS_MAP = {
		'detected': { text: 'Detectado', cls: 'badge-detected', tone: 'tone-green' },
		'investigating': { text: 'En investigación', cls: 'badge-investigating', tone: 'tone-amber' },
		'action_assigned': { text: 'Acción asignada', cls: 'badge-action', tone: 'tone-grey' },
		'corrected': { text: 'Corregido', cls: 'badge-corrected', tone: 'tone-green' },
		'verified': { text: 'Verificado', cls: 'badge-verified', tone: 'tone-green' },
		'closed': { text: 'Cerrado', cls: 'badge-closed', tone: 'tone-grey' },
		'pending_approval': { text: 'Pendiente aprobación', cls: 'badge-pending', tone: 'tone-amber' },
		'approved': { text: 'Aprobado', cls: 'badge-approved', tone: 'tone-green' }
	};

	var currentTimelineMode = 'all'; // 'all' o 'case'
	var activeRowElement = null;

	function initAuditModule() {
		var rows = document.querySelectorAll('.daily-audit-row');
		if (!rows.length) return;

		// 1. Manejo de selección de fila
		rows.forEach(function(row) {
			row.addEventListener('click', function() {
				selectAuditRow(this);
			});
		});

		// Seleccionar la primera fila por defecto
		selectAuditRow(rows[0]);

		// 2. Filtros de entrada
		var userFilter = document.getElementById('auditUserFilter');
		var actionFilter = document.getElementById('auditActionFilter');
		var caseFilter = document.getElementById('auditCaseFilter');
		var statusFilter = document.getElementById('auditStatusFilter');
		var searchInput = document.getElementById('auditSearchInput');
		var btnReset = document.getElementById('auditBtnReset');
		var btnExport = document.getElementById('auditBtnExport');
		var toggleTimelineBtn = document.getElementById('btnToggleTimelineScope');

		if (userFilter) userFilter.addEventListener('change', applyFilters);
		if (actionFilter) actionFilter.addEventListener('change', applyFilters);
		if (statusFilter) statusFilter.addEventListener('change', applyFilters);
		if (caseFilter) caseFilter.addEventListener('input', applyFilters);
		if (searchInput) searchInput.addEventListener('input', applyFilters);

		if (btnReset) {
			btnReset.addEventListener('click', function() {
				if (userFilter) userFilter.value = '';
				if (actionFilter) actionFilter.value = '';
				if (caseFilter) caseFilter.value = '';
				if (statusFilter) statusFilter.value = '';
				if (searchInput) searchInput.value = '';
				document.querySelectorAll('.daily-pill-btn').forEach(function(b) { b.classList.remove('active'); });
				var allPill = document.querySelector('.daily-pill-btn[data-pill-type="all"]');
				if (allPill) allPill.classList.add('active');
				applyFilters();
			});
		}

		// Píldoras de filtro rápido
		document.querySelectorAll('.daily-pill-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				document.querySelectorAll('.daily-pill-btn').forEach(function(b) { b.classList.remove('active'); });
				this.classList.add('active');
				var type = this.getAttribute('data-pill-type');
				if (type === 'all') {
					if (actionFilter) actionFilter.value = '';
				} else {
					if (actionFilter) actionFilter.value = type;
				}
				applyFilters();
			});
		});

		// Alternar alcance de la línea de tiempo
		if (toggleTimelineBtn) {
			toggleTimelineBtn.addEventListener('click', function() {
				if (currentTimelineMode === 'all') {
					currentTimelineMode = 'case';
					this.textContent = 'Ver secuencia general';
				} else {
					currentTimelineMode = 'all';
					this.textContent = 'Ver historial de este caso';
				}
				renderTimeline();
			});
		}

		// Pestañas de detalle
		document.querySelectorAll('.daily-audit-tab-btn').forEach(function(tabBtn) {
			tabBtn.addEventListener('click', function() {
				document.querySelectorAll('.daily-audit-tab-btn').forEach(function(b) { b.classList.remove('active'); });
				this.classList.add('active');
				var tab = this.getAttribute('data-tab');
				document.getElementById('detailTabResumen').style.display = tab === 'resumen' ? 'block' : 'none';
				document.getElementById('detailTabCambios').style.display = tab === 'cambios' ? 'block' : 'none';
				document.getElementById('detailTabOriginal').style.display = tab === 'original' ? 'block' : 'none';
			});
		});

		// Exportación
		if (btnExport) {
			btnExport.addEventListener('click', exportAuditData);
		}

		renderTimeline();
	}

	// Selecciona una fila y puebla el panel de detalle
	function selectAuditRow(row) {
		document.querySelectorAll('.daily-audit-row').forEach(function(r) { r.classList.remove('active'); });
		row.classList.add('active');
		activeRowElement = row;

		var id = row.getAttribute('data-id');
		var caseNum = row.getAttribute('data-case');
		var user = row.getAttribute('data-user');
		var timestamp = row.getAttribute('data-timestamp');
		var actionTitle = row.getAttribute('data-action-title');
		var prevStatus = row.getAttribute('data-status-prev');
		var newStatus = row.getAttribute('data-status-new');
		var details = row.getAttribute('data-details') || '';
		var rawData = row.getAttribute('data-raw');

		// Actualizar tarjeta de detalle
		var titleEl = document.getElementById('detailCaseTitle');
		var timeEl = document.getElementById('detailTimestamp');
		var actionEl = document.getElementById('detailActionTitle');
		var userEl = document.getElementById('detailUser');
		var dateEl = document.getElementById('detailDateTime');
		var refEl = document.getElementById('detailReference');
		var prevStatusEl = document.getElementById('detailPrevStatus');
		var newStatusEl = document.getElementById('detailNewStatus');
		var textEl = document.getElementById('detailText');
		var statusBadge = document.getElementById('detailStatusBadge');
		var jsonView = document.getElementById('detailJsonView');

		if (titleEl) titleEl.textContent = caseNum;
		if (timeEl) timeEl.textContent = formatDateHuman(timestamp);
		if (actionEl) actionEl.textContent = actionTitle;
		if (userEl) userEl.textContent = user;
		if (dateEl) dateEl.textContent = timestamp;
		if (refEl) refEl.textContent = caseNum;

		var prevInfo = STATUS_MAP[prevStatus] || { text: 'Sin estado previo', cls: 'badge-none' };
		var newInfo = STATUS_MAP[newStatus] || { text: newStatus, cls: 'badge-' + newStatus };

		if (prevStatusEl) {
			prevStatusEl.innerHTML = '<span class="daily-status-badge ' + prevInfo.cls + '">' + prevInfo.text + '</span>';
		}
		if (newStatusEl) {
			newStatusEl.className = 'daily-status-badge ' + newInfo.cls;
			newStatusEl.textContent = newInfo.text;
		}
		if (statusBadge) {
			statusBadge.className = 'daily-status-badge ' + newInfo.cls;
			statusBadge.textContent = newInfo.text;
		}

		// Formatear texto con saltos de línea amigables
		if (textEl) {
			var formatted = details.replace(/\. /g, '.<br>');
			textEl.innerHTML = formatted;
		}

		// Cambios tab
		var changePrev = document.getElementById('changePrev');
		var changeNew = document.getElementById('changeNew');
		if (changePrev) {
			changePrev.className = 'daily-status-badge ' + prevInfo.cls;
			changePrev.textContent = prevInfo.text;
		}
		if (changeNew) {
			changeNew.className = 'daily-status-badge ' + newInfo.cls;
			changeNew.textContent = newInfo.text;
		}

		var changesList = document.getElementById('detailChangesList');
		if (changesList) {
			var lis = '';
			if (prevStatus === newStatus && prevStatus) {
				lis += '<li>Actualización interna sin transición de estado (' + newInfo.text + ' &rarr; ' + newInfo.text + ').</li>';
			} else {
				lis += '<li>Transición de estado: <strong>' + prevInfo.text + '</strong> &rarr; <strong>' + newInfo.text + '</strong>.</li>';
			}
			if (details.indexOf('Causal:') !== -1) {
				lis += '<li>Causal registrada en expediente.</li>';
			}
			if (details.indexOf('Costo') !== -1 || details.indexOf('$') !== -1) {
				lis += '<li>Imputación y valoración financiera estimada.</li>';
			}
			if (details.indexOf('Existencias') !== -1 || details.indexOf('descontadas') !== -1) {
				lis += '<li>Descarga o afectación en existencias de inventario contable.</li>';
			}
			changesList.innerHTML = lis;
		}

		// Registro original JSON
		if (jsonView && rawData) {
			try {
				var parsed = JSON.parse(rawData);
				jsonView.textContent = JSON.stringify(parsed, null, 2);
			} catch(e) {
				jsonView.textContent = rawData;
			}
		}

		if (currentTimelineMode === 'case') {
			renderTimeline();
		}
	}

	// Filtra filas dinámicamente y recalcula métricas
	function applyFilters() {
		var userVal = (document.getElementById('auditUserFilter') ? document.getElementById('auditUserFilter').value : '').toLowerCase();
		var actionVal = (document.getElementById('auditActionFilter') ? document.getElementById('auditActionFilter').value : '').toLowerCase();
		var caseVal = (document.getElementById('auditCaseFilter') ? document.getElementById('auditCaseFilter').value : '').toLowerCase().trim();
		var statusVal = (document.getElementById('auditStatusFilter') ? document.getElementById('auditStatusFilter').value : '').toLowerCase();
		var searchVal = (document.getElementById('auditSearchInput') ? document.getElementById('auditSearchInput').value : '').toLowerCase().trim();

		var rows = document.querySelectorAll('.daily-audit-row');
		var visibleCount = 0;
		var visibleTimestamps = [];
		var firstVisibleRow = null;

		rows.forEach(function(row) {
			var u = (row.getAttribute('data-username') || '').toLowerCase();
			var uName = (row.getAttribute('data-user') || '').toLowerCase();
			var c = (row.getAttribute('data-case') || '').toLowerCase();
			var aTitle = (row.getAttribute('data-action-title') || '').toLowerCase();
			var aType = (row.getAttribute('data-action-type') || '').toLowerCase();
			var sNew = (row.getAttribute('data-status-new') || '').toLowerCase();
			var sPrev = (row.getAttribute('data-status-prev') || '').toLowerCase();
			var dText = (row.getAttribute('data-details') || '').toLowerCase();
			var ts = row.getAttribute('data-timestamp') || '';

			var matchUser = !userVal || u === userVal || uName.indexOf(userVal) !== -1;
			var matchAction = !actionVal || aType === actionVal || aTitle.indexOf(actionVal) !== -1;
			var matchCase = !caseVal || c.indexOf(caseVal) !== -1;
			var matchStatus = !statusVal || sNew === statusVal || sPrev === statusVal;
			var matchSearch = !searchVal || aTitle.indexOf(searchVal) !== -1 || dText.indexOf(searchVal) !== -1 || c.indexOf(searchVal) !== -1 || uName.indexOf(searchVal) !== -1;

			if (matchUser && matchAction && matchCase && matchStatus && matchSearch) {
				row.style.display = '';
				visibleCount++;
				visibleTimestamps.push(ts);
				if (!firstVisibleRow) firstVisibleRow = row;
			} else {
				row.style.display = 'none';
			}
		});

		// Actualizar contadores de métricas
		var countEl = document.getElementById('metricVisibleCount');
		if (countEl) countEl.textContent = visibleCount;

		visibleTimestamps.sort();
		var rangeEl = document.getElementById('metricDateRange');
		var latestEl = document.getElementById('metricLatestEvent');

		if (visibleTimestamps.length > 0) {
			if (rangeEl) rangeEl.textContent = formatDateHuman(visibleTimestamps[0]) + ' – ' + formatDateHuman(visibleTimestamps[visibleTimestamps.length - 1]);
			if (latestEl) latestEl.textContent = formatDateTimeHuman(visibleTimestamps[visibleTimestamps.length - 1]);
		} else {
			if (rangeEl) rangeEl.textContent = '—';
			if (latestEl) latestEl.textContent = '—';
		}

		if (firstVisibleRow) {
			selectAuditRow(firstVisibleRow);
		}

		renderTimeline();
	}

	// Renderiza la línea de tiempo vertical
	function renderTimeline() {
		var container = document.getElementById('auditTimelineContainer');
		if (!container) return;

		var rows = document.querySelectorAll('.daily-audit-row');
		var items = [];
		var activeCase = activeRowElement ? activeRowElement.getAttribute('data-case') : null;

		rows.forEach(function(row) {
			if (row.style.display === 'none') return;
			var c = row.getAttribute('data-case');
			if (currentTimelineMode === 'case' && activeCase && c !== activeCase) {
				return;
			}
			items.push({
				id: row.getAttribute('data-id'),
				case: c,
				user: row.getAttribute('data-user'),
				timestamp: row.getAttribute('data-timestamp'),
				action: row.getAttribute('data-action-title'),
				statusNew: row.getAttribute('data-status-new'),
				element: row
			});
		});

		if (items.length === 0) {
			container.innerHTML = '<div class="text-muted small py-3 text-center">No hay eventos para la línea de tiempo.</div>';
			return;
		}

		var html = '';
		// Agrupación si varios eventos comparten el mismo segundo
		var timestampCounts = {};
		items.forEach(function(it) {
			timestampCounts[it.timestamp] = (timestampCounts[it.timestamp] || 0) + 1;
		});

		var processedSameSecond = {};

		items.forEach(function(it, idx) {
			var isDuplicateSecond = timestampCounts[it.timestamp] > 3;
			
			// Si son más de 3 eventos en el mismo segundo y ya mostramos el primero, podemos mostrar badge agrupado
			if (isDuplicateSecond) {
				if (!processedSameSecond[it.timestamp]) {
					processedSameSecond[it.timestamp] = 1;
					html += buildTimelineNode(it);
				} else if (processedSameSecond[it.timestamp] === 1) {
					processedSameSecond[it.timestamp]++;
					var rest = timestampCounts[it.timestamp] - 1;
					html += '<div class="daily-timeline-item text-muted small pl-2 my-1" style="font-size: 0.72rem;">' +
						'<i class="fa-solid fa-ellipsis-vertical mr-2 text-muted"></i>' +
						'<strong>' + rest + ' eventos adicionales</strong> del ' + formatDateTimeHuman(it.timestamp) +
						'</div>';
				}
				return;
			}

			html += buildTimelineNode(it);
		});

		container.innerHTML = html;

		// Añadir listeners para seleccionar fila al hacer clic en el timeline
		container.querySelectorAll('.daily-timeline-item').forEach(function(node, i) {
			node.addEventListener('click', function() {
				var targetId = this.getAttribute('data-log-id');
				var targetRow = document.querySelector('.daily-audit-row[data-id="' + targetId + '"]');
				if (targetRow) {
					selectAuditRow(targetRow);
					targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			});
		});
	}

	function buildTimelineNode(it) {
		var st = STATUS_MAP[it.statusNew] || { text: it.statusNew, cls: 'badge-' + it.statusNew, tone: 'tone-grey' };
		return '<div class="daily-timeline-item" data-log-id="' + it.id + '">' +
			'<div class="daily-timeline-dot ' + st.tone + '"></div>' +
			'<div class="daily-timeline-content">' +
				'<div class="daily-timeline-time">' + it.timestamp + '</div>' +
				'<div class="daily-timeline-action">' +
					'<span>' + it.action + '</span>' +
					'<span class="daily-status-badge ' + st.cls + '" style="font-size: 0.68rem;">' + st.text + '</span>' +
				'</div>' +
				'<div class="daily-timeline-meta">' + it.case + ' &middot; ' + it.user + '</div>' +
			'</div>' +
		'</div>';
	}

	function formatDateHuman(ts) {
		if (!ts) return '';
		var parts = ts.split(' ')[0].split('-');
		if (parts.length === 3) {
			var months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
			var mIdx = parseInt(parts[1], 10) - 1;
			return parseInt(parts[2], 10) + ' ' + (months[mIdx] || parts[1]) + ' ' + parts[0];
		}
		return ts;
	}

	function formatDateTimeHuman(ts) {
		if (!ts) return '';
		var p = ts.split(' ');
		return formatDateHuman(p[0]) + ' ' + (p[1] || '');
	}

	// Exportación a archivo CSV con cabeceras y trazabilidad
	function exportAuditData() {
		var rows = document.querySelectorAll('.daily-audit-row');
		var csv = "ID,FECHA_HORA,REFERENCIA_CASO,ACCION,USUARIO,ESTADO_PREVIO,NUEVO_ESTADO,DETALLE,ZONA_HORARIA\n";
		var tz = "{{ $tz }}";

		rows.forEach(function(row) {
			if (row.style.display === 'none') return;
			var id = row.getAttribute('data-id');
			var ts = row.getAttribute('data-timestamp');
			var c = row.getAttribute('data-case');
			var act = row.getAttribute('data-action-title');
			var u = row.getAttribute('data-user');
			var sp = row.getAttribute('data-status-prev') || 'N/A';
			var sn = row.getAttribute('data-status-new');
			var det = (row.getAttribute('data-details') || '').replace(/"/g, '""');

			csv += '"' + id + '","' + ts + '","' + c + '","' + act + '","' + u + '","' + sp + '","' + sn + '","' + det + '","' + tz + '"\n';
		});

		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement("a");
		var url = URL.createObjectURL(blob);
		link.setAttribute("href", url);
		link.setAttribute("download", "auditoria_merma_" + new Date().toISOString().slice(0,10) + ".csv");
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		if (typeof toastr !== 'undefined') {
			toastr.success('Exportación de auditoría generada');
		}
	}

	// Ejecutar inicialización tras carga DOM o lazy load
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAuditModule);
	} else {
		initAuditModule();
	}
})();
</script>
