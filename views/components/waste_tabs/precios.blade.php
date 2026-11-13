@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);
	$S = $price['summary'];
	$rows = $price['rows'] ?? [];

	$flowLbl = [
		'detected' => 'Detección',
		'validated' => 'Validación',
		'evidence' => 'Evidencia',
		'corrected' => 'Corrección',
		'approved' => 'Aprobación',
		'pos_synced' => 'Sincronización POS',
		'verified' => 'Verificación',
		'closed' => 'Cierre'
	];

	$flowDesc = [
		'detected' => 'Identifica posibles discrepancias',
		'validated' => 'Revisa y confirma la información',
		'evidence' => 'Adjunta soporte (fotos, tickets, etc.)',
		'corrected' => 'Propone ajuste de precio',
		'approved' => 'Revisión de otra persona',
		'pos_synced' => 'Actualiza precio en sistemas',
		'verified' => 'Confirma en tienda',
		'closed' => 'Caso resuelto y auditado'
	];

	$impLbl = [
		'sobrecobro' => 'Sobrecobro',
		'subcobro' => 'Subcobro',
		'devolucion' => 'Devolución',
		'perdida_comercial' => 'Pérdida comercial',
		'sin_impacto' => 'Sin impacto'
	];

	$kinds = $price['kinds'] ?? [];
	$branches = $lookups['branches'] ?? [];
	$products = $lookups['products'] ?? [];
	$selectedBranchId = $f['branch_id'] ?? null;

	// Contadores por pestaña
	$countTodos = count($rows);
	$countPorValidar = 0;
	$countEnCorreccion = 0;
	$countCerrados = 0;

	foreach ($rows as $r) {
		if ($r['status'] === 'detected') {
			$countPorValidar++;
		} elseif (in_array($r['status'], ['validated', 'evidence', 'corrected', 'approved', 'pos_synced', 'verified'], true)) {
			$countEnCorreccion++;
		} elseif (in_array($r['status'], ['closed', 'rejected'], true)) {
			$countCerrados++;
		}
	}

	$canApprove = $canApprove ?? false;
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER — CONTROL DE PRECIOS, MARCACIÓN Y EPC
   Diseño de alta jerarquía visual, tonos armónicos y micro-interacciones
   ========================================================================== */

.daily-precios-wrapper {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #1e293b;
	background-color: #f8fafc;
	padding: 0.25rem 0 2rem 0;
}

/* Tarjetas base con esquinas de 14px y sombras sutiles */
.daily-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.daily-card:hover {
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

/* Botones con estilo DAILY */
.daily-action-btn-green {
	background-color: #00C83B;
	border: 1px solid #00C83B;
	color: #ffffff !important;
	font-weight: 600;
	border-radius: 8px;
	padding: 0.45rem 1rem;
	font-size: 0.85rem;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	cursor: pointer;
	text-decoration: none !important;
	transition: background-color 0.15s ease, transform 0.1s ease;
}
.daily-action-btn-green:hover {
	background-color: #00a832;
	border-color: #00a832;
	transform: translateY(-1px);
}
.daily-btn-secondary {
	background-color: #ffffff;
	border: 1px solid #cbd5e1;
	color: #334155 !important;
	font-weight: 600;
	border-radius: 8px;
	padding: 0.45rem 0.95rem;
	font-size: 0.85rem;
	display: inline-flex;
	align-items: center;
	gap: 0.45rem;
	cursor: pointer;
	text-decoration: none !important;
	transition: all 0.15s ease;
}
.daily-btn-secondary:hover {
	background-color: #f1f5f9;
	border-color: #94a3b8;
}

/* Iconos de cabecera */
.daily-header-icon {
	width: 44px;
	height: 44px;
	border-radius: 12px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.25rem;
	background: #fee2e2;
	color: #ef4444;
	flex-shrink: 0;
}

/* Indicadores específicos de precios (5 KPIs) */
.daily-kpi-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 0.9rem 1rem;
	position: relative;
	transition: all 0.2s ease;
}
.daily-kpi-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.daily-kpi-title {
	font-size: 0.68rem;
	font-weight: 700;
	letter-spacing: 0.05em;
	text-transform: uppercase;
	color: #64748b;
	margin-bottom: 0.35rem;
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.daily-kpi-value {
	font-size: 1.45rem;
	font-weight: 800;
	letter-spacing: -0.02em;
	color: #0f172a;
	line-height: 1.15;
	margin-bottom: 0.25rem;
}
.daily-kpi-subtitle {
	font-size: 0.73rem;
	color: #64748b;
	display: flex;
	align-items: center;
	gap: 0.3rem;
}

/* Badges de estado en KPIs */
.daily-badge-neutral {
	background: #f1f5f9;
	color: #475569;
	font-size: 0.68rem;
	font-weight: 600;
	padding: 2px 7px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
}
.daily-badge-red {
	background: #fee2e2;
	color: #dc2626;
	font-size: 0.68rem;
	font-weight: 600;
	padding: 2px 7px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
}
.daily-badge-amber {
	background: #fef3c7;
	color: #b45309;
	font-size: 0.68rem;
	font-weight: 600;
	padding: 2px 7px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
}
.daily-badge-purple {
	background: #f3e8ff;
	color: #7e22ce;
	font-size: 0.68rem;
	font-weight: 600;
	padding: 2px 7px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
}
.daily-badge-slate {
	background: #e2e8f0;
	color: #334155;
	font-size: 0.68rem;
	font-weight: 600;
	padding: 2px 7px;
	border-radius: 6px;
	display: inline-flex;
	align-items: center;
}

/* Stepper visual de resolución (8 pasos) */
.daily-stepper-container {
	position: relative;
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	padding: 0.75rem 0.25rem 0.25rem 0.25rem;
}
.daily-stepper {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	min-width: 780px;
	position: relative;
}
.daily-stepper::before {
	content: '';
	position: absolute;
	top: 17px;
	left: 24px;
	right: 24px;
	height: 2px;
	background: #e2e8f0;
	z-index: 1;
}
.daily-step-item {
	position: relative;
	z-index: 2;
	flex: 1;
	text-align: center;
	padding: 0 0.35rem;
}
.daily-step-circle {
	width: 34px;
	height: 34px;
	border-radius: 50%;
	background: #ffffff;
	border: 2px solid #cbd5e1;
	color: #64748b;
	font-weight: 700;
	font-size: 0.82rem;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 0.45rem auto;
	transition: all 0.2s ease;
}
.daily-step-item:hover .daily-step-circle {
	border-color: #00C83B;
	color: #00C83B;
	box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12);
}
.daily-step-title {
	font-size: 0.78rem;
	font-weight: 700;
	color: #1e293b;
	margin-bottom: 0.15rem;
}
.daily-step-desc {
	font-size: 0.69rem;
	color: #64748b;
	line-height: 1.25;
}

/* Tarjetas de acción con fotografías integradas */
.daily-photo-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	height: 100%;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.daily-photo-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 16px rgba(0, 0, 0, 0.07);
}
.daily-photo-wrapper {
	position: relative;
	height: 145px;
	width: 100%;
	background: #f1f5f9;
	overflow: hidden;
}
.daily-photo-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
	transition: transform 0.3s ease;
}
.daily-photo-card:hover .daily-photo-img {
	transform: scale(1.03);
}
.daily-photo-overlay {
	position: absolute;
	inset: 0;
	background: linear-gradient(to bottom, rgba(0,0,0,0) 40%, rgba(255,255,255,0.85) 85%, #ffffff 100%);
	pointer-events: none;
}
.daily-photo-icon-bubble {
	position: absolute;
	bottom: 8px;
	left: 14px;
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background: #ffffff;
	box-shadow: 0 2px 8px rgba(0,0,0,0.12);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 3;
}
.daily-photo-badge-top {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 3;
}
.daily-photo-content {
	padding: 0.85rem 1rem 1rem 1rem;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
}

/* Estado sin casos */
.daily-empty-box {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 2.25rem 1.5rem;
	text-align: center;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
.daily-empty-icon-wrap {
	width: 56px;
	height: 56px;
	border-radius: 50%;
	background: #f1f5f9;
	color: #64748b;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 1.6rem;
}

/* Tabs de filtrado de casos */
.daily-filter-pills {
	display: flex;
	gap: 0.4rem;
	flex-wrap: wrap;
}
.daily-filter-pill {
	background: #ffffff;
	border: 1px solid #cbd5e1;
	color: #475569;
	font-size: 0.78rem;
	font-weight: 600;
	padding: 0.35rem 0.8rem;
	border-radius: 20px;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	transition: all 0.15s ease;
	user-select: none;
}
.daily-filter-pill:hover {
	background: #f8fafc;
	border-color: #94a3b8;
}
.daily-filter-pill.is-active {
	background: #00C83B;
	border-color: #00C83B;
	color: #ffffff;
}

/* Tabla de casos */
.daily-cases-table {
	width: 100%;
	margin-bottom: 0;
	border-collapse: separate;
	border-spacing: 0;
}
.daily-cases-table th {
	background: #f8fafc;
	font-size: 0.69rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #64748b;
	padding: 0.65rem 0.75rem;
	border-top: 1px solid #e2e8f0;
	border-bottom: 1px solid #e2e8f0;
	vertical-align: middle;
}
.daily-cases-table td {
	padding: 0.75rem;
	font-size: 0.82rem;
	vertical-align: middle;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
}
.daily-cases-table tr:hover td {
	background-color: #fbfcfe;
}

/* Tarjetas explicativas de impacto económico */
.daily-impact-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 1rem;
	display: flex;
	gap: 0.75rem;
	align-items: flex-start;
	height: 100%;
	transition: all 0.2s ease;
}
.daily-impact-card:hover {
	box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
}
.daily-impact-icon-circle {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1rem;
	flex-shrink: 0;
}

/* Módulos conectados */
.daily-conn-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	padding: 0.75rem 0.85rem;
	display: flex;
	align-items: center;
	justify-content: space-between;
	text-decoration: none !important;
	color: inherit;
	transition: all 0.15s ease;
	margin-bottom: 0.5rem;
}
.daily-conn-card:hover {
	border-color: #cbd5e1;
	background: #fbfcfe;
	transform: translateX(2px);
	box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

/* Barra de progreso de caso en tabla */
.daily-step-bar-wrap {
	width: 100px;
	height: 5px;
	background: #e2e8f0;
	border-radius: 3px;
	overflow: hidden;
	margin-top: 3px;
}
.daily-step-bar-fill {
	height: 100%;
	background: #00C83B;
	border-radius: 3px;
}

/* Responsividad móvil */
@media (max-width: 768px) {
	.daily-precios-wrapper {
		padding: 0.25rem 0 1.5rem 0;
	}
	.daily-header-actions {
		width: 100%;
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		margin-top: 0.75rem;
	}
	.daily-header-actions button,
	.daily-header-actions .btn-group {
		width: 100%;
	}
	.daily-kpi-card {
		padding: 0.75rem;
	}
	.daily-kpi-value {
		font-size: 1.25rem;
	}
}
</style>

<div class="daily-precios-wrapper" id="dailyPreciosRoot">

	<!-- ======================================================================
	     SECCIÓN A: ENCABEZADO Y ACCIONES PRINCIPALES
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-3">
		<div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 1rem;">
			<div class="d-flex align-items-center" style="gap: 0.85rem;">
				<div class="daily-header-icon shadow-xs">
					<i class="fa-solid fa-arrow-trend-up"></i>
				</div>
				<div>
					<h5 class="font-weight-bold text-dark mb-1" style="letter-spacing: -0.02em;">
						Control de precios, marcación y EPC
					</h5>
					<p class="text-muted mb-0 small" style="line-height: 1.35; max-width: 780px;">
						Detecta diferencias de precios, valida su impacto y gestiona la corrección hasta su verificación y cierre.
						<span class="d-inline-block text-slate-700 font-weight-500">
							El precio autorizado solo cambia tras aprobación de otra persona y queda auditado.
						</span>
					</p>
				</div>
			</div>
			
			<div class="d-flex align-items-center flex-wrap daily-header-actions" style="gap: 0.5rem;">
				<button type="button" class="daily-action-btn-green shadow-xs" onclick="dailyOpenDetectModal();">
					<i class="fa-solid fa-radar mr-1"></i>Detectar discrepancias
				</button>
				<button type="button" class="daily-btn-secondary shadow-xs" onclick="dailyOpenRegisterModal();">
					<i class="fa-solid fa-circle-plus mr-1 text-slate-600"></i>Registrar observación
				</button>
				@include('components.dairy_export', ['exportScope' => '#dailyPreciosTableWrap', 'exportName' => 'control_precios_marcacion_epc'])
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN B: CINCO INDICADORES ESPECÍFICOS DE PRECIOS
	     Conservados con base en los datos reales (distingue cero confirmado)
	     ====================================================================== -->
	<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-2 mb-3">
		<!-- 1. CASOS ABIERTOS -->
		<div class="col mb-2 mb-lg-0">
			<div class="daily-kpi-card h-100">
				<div class="daily-kpi-title">
					<span>Casos abiertos</span>
					<i class="fa-regular fa-folder-open text-amber-600" style="font-size: 0.85rem;"></i>
				</div>
				<div class="daily-kpi-value text-dark">
					{{ $S['open'] }}
				</div>
				<div class="daily-kpi-subtitle">
					@if($S['open'] === 0)
						<span class="daily-badge-neutral"><i class="fa-solid fa-check mr-1 text-success"></i>0 activos</span>
						<span class="text-truncate">Flujo al día</span>
					@else
						<span class="daily-badge-amber"><i class="fa-solid fa-clock mr-1"></i>En resolución</span>
						<span class="text-truncate">Pendientes</span>
					@endif
				</div>
			</div>
		</div>

		<!-- 2. SUBCOBRO COMPROBADO -->
		<div class="col mb-2 mb-lg-0">
			<div class="daily-kpi-card h-100">
				<div class="daily-kpi-title">
					<span>Subcobro comprobado</span>
					<i class="fa-solid fa-arrow-down text-danger" style="font-size: 0.85rem;"></i>
				</div>
				<div class="daily-kpi-value text-danger">
					{{ $money($S['verified_impact']['subcobro']) }}
				</div>
				<div class="daily-kpi-subtitle">
					@if($S['verified_impact']['subcobro'] == 0)
						<span class="daily-badge-neutral"><i class="fa-solid fa-circle-check mr-1 text-muted"></i>$0.00 confirmado</span>
					@else
						<span class="daily-badge-red"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Pérdida real</span>
					@endif
					<span class="text-truncate">Empresa</span>
				</div>
			</div>
		</div>

		<!-- 3. SOBRECOBRO COMPROBADO -->
		<div class="col mb-2 mb-lg-0">
			<div class="daily-kpi-card h-100">
				<div class="daily-kpi-title">
					<span>Sobrecobro comprobado</span>
					<i class="fa-solid fa-arrow-up text-amber-600" style="font-size: 0.85rem;"></i>
				</div>
				<div class="daily-kpi-value text-amber-700">
					{{ $money($S['verified_impact']['sobrecobro']) }}
				</div>
				<div class="daily-kpi-subtitle">
					@if($S['verified_impact']['sobrecobro'] == 0)
						<span class="daily-badge-neutral"><i class="fa-solid fa-circle-check mr-1 text-muted"></i>$0.00 confirmado</span>
					@else
						<span class="daily-badge-amber"><i class="fa-solid fa-user-shield mr-1"></i>Riesgo cliente</span>
					@endif
					<span class="text-truncate">(No es merma)</span>
				</div>
			</div>
		</div>

		<!-- 4. DEVOLUCIONES / PÉRDIDA COMERCIAL -->
		<div class="col mb-2 mb-lg-0">
			<div class="daily-kpi-card h-100">
				<div class="daily-kpi-title">
					<span>Devoluciones / Pérdida com.</span>
					<i class="fa-solid fa-rotate-left text-purple-600" style="font-size: 0.85rem;"></i>
				</div>
				<div class="daily-kpi-value text-purple-700">
					{{ $money($S['verified_impact']['devolucion'] + $S['verified_impact']['perdida_comercial']) }}
				</div>
				<div class="daily-kpi-subtitle">
					@if(($S['verified_impact']['devolucion'] + $S['verified_impact']['perdida_comercial']) == 0)
						<span class="daily-badge-neutral"><i class="fa-solid fa-circle-check mr-1 text-muted"></i>$0.00 comprobado</span>
					@else
						<span class="daily-badge-purple"><i class="fa-solid fa-receipt mr-1"></i>Documentado</span>
					@endif
					<span class="text-truncate">Criterio vigente</span>
				</div>
			</div>
		</div>

		<!-- 5. IMPACTO SIN VALIDAR -->
		<div class="col">
			<div class="daily-kpi-card h-100">
				<div class="daily-kpi-title">
					<span>Impacto sin validar</span>
					<i class="fa-solid fa-clock-rotate-left text-slate-500" style="font-size: 0.85rem;"></i>
				</div>
				<div class="daily-kpi-value text-slate-700">
					{{ $money($S['unverified_impact']) }}
				</div>
				<div class="daily-kpi-subtitle">
					@if($S['unverified_impact'] == 0)
						<span class="daily-badge-neutral"><i class="fa-solid fa-circle-info mr-1 text-muted"></i>Sin pendientes</span>
					@else
						<span class="daily-badge-slate"><i class="fa-solid fa-hourglass-half mr-1"></i>Estimación</span>
					@endif
					<span class="text-truncate">No confirmado</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Nota metodológica sutil de indicadores -->
	<div class="px-2 mb-3 d-flex align-items-center justify-content-between text-muted text-xs" style="font-size: 0.72rem;">
		<div>
			<i class="fa-solid fa-circle-info mr-1 text-slate-400"></i>
			Los indicadores distinguen entre cero confirmado y falta de detección. Las fuentes no conectadas (ej. lector EPC/RFID) se indican explícitamente como registro manual.
		</div>
		<div class="d-none d-md-block text-slate-500">
			Criterio contable: segregación estricta de merma vs sobrecobros
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN C: FLUJO DE RESOLUCIÓN (8 ETAPAS)
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap mb-2" style="gap: 0.5rem;">
			<div class="d-flex align-items-center" style="gap: 0.45rem;">
				<span class="font-weight-bold text-dark" style="font-size: 0.92rem;">Flujo de resolución</span>
				<i class="fa-solid fa-circle-info text-slate-400" title="Ruta secuencial obligatoria de auditoría para toda discrepancia"></i>
			</div>
			<div class="small text-muted font-weight-500" style="font-size: 0.76rem;">
				<i class="fa-solid fa-shield-halved text-success mr-1"></i>
				El precio autorizado solo cambia tras aprobación de otra persona y queda auditado.
			</div>
		</div>

		<div class="daily-stepper-container">
			<div class="daily-stepper">
				@php
					$steps = [
						1 => ['key' => 'detected', 'title' => 'Detección', 'desc' => 'Identifica posibles discrepancias'],
						2 => ['key' => 'validated', 'title' => 'Validación', 'desc' => 'Revisa y confirma la información'],
						3 => ['key' => 'evidence', 'title' => 'Evidencia', 'desc' => 'Adjunta soporte (fotos, tickets)'],
						4 => ['key' => 'corrected', 'title' => 'Corrección', 'desc' => 'Propone ajuste de precio'],
						5 => ['key' => 'approved', 'title' => 'Aprobación', 'desc' => 'Revisión de otra persona'],
						6 => ['key' => 'pos_synced', 'title' => 'Sincronización POS', 'desc' => 'Actualiza precio en sistemas'],
						7 => ['key' => 'verified', 'title' => 'Verificación', 'desc' => 'Confirma en tienda'],
						8 => ['key' => 'closed', 'title' => 'Cierre', 'desc' => 'Caso resuelto y auditado']
					];
				@endphp
				@foreach($steps as $num => $st)
					<div class="daily-step-item" title="{{ $st['title'] }}: {{ $st['desc'] }}">
						<div class="daily-step-circle">{{ $num }}</div>
						<div class="daily-step-title">{{ $st['title'] }}</div>
						<div class="daily-step-desc">{{ $st['desc'] }}</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN D: TARJETAS CON FOTOGRAFÍAS CONTEXTUALES (3 MÓDULOS)
	     ====================================================================== -->
	<div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
		<!-- 1. Detectar en ventas -->
		<div class="col mb-3 mb-md-0">
			<div class="daily-photo-card">
				<div class="daily-photo-wrapper">
					<img src="{{ $U('/img/control_center/precios_detectar_pos.jpg') }}" alt="Escaneo en punto de venta" class="daily-photo-img" loading="lazy">
					<div class="daily-photo-overlay"></div>
					<div class="daily-photo-icon-bubble">
						<i class="fa-solid fa-chart-simple text-success" style="font-size: 1rem;"></i>
					</div>
				</div>
				<div class="daily-photo-content">
					<h6 class="font-weight-bold text-dark mb-1" style="font-size: 0.95rem;">Detectar en ventas</h6>
					<p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.78rem; line-height: 1.35;">
						Compara operaciones con el precio autorizado vigente al momento de la transacción.
					</p>
					<button type="button" class="daily-action-btn-green w-100 justify-content-center" onclick="dailyOpenDetectModal();">
						<i class="fa-solid fa-magnifying-glass"></i>Detectar
					</button>
				</div>
			</div>
		</div>

		<!-- 2. Revisar góndola -->
		<div class="col mb-3 mb-md-0">
			<div class="daily-photo-card">
				<div class="daily-photo-wrapper">
					<img src="{{ $U('/img/control_center/precios_revisar_gondola.jpg') }}" alt="Etiquetas en góndola de productos" class="daily-photo-img" loading="lazy">
					<div class="daily-photo-overlay"></div>
					<div class="daily-photo-icon-bubble">
						<i class="fa-solid fa-tag text-success" style="font-size: 0.95rem;"></i>
					</div>
				</div>
				<div class="daily-photo-content">
					<h6 class="font-weight-bold text-dark mb-1" style="font-size: 0.95rem;">Revisar góndola</h6>
					<p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.78rem; line-height: 1.35;">
						Registra diferencias de etiqueta y exhibición contra el precio de lista aplicable.
					</p>
					<button type="button" class="daily-btn-secondary w-100 justify-content-center" onclick="dailyOpenRegisterModal('shelf_vs_pos');">
						<i class="fa-solid fa-circle-plus text-slate-500"></i>Registrar observación
					</button>
				</div>
			</div>
		</div>

		<!-- 3. Registrar EPC / RFID -->
		<div class="col">
			<div class="daily-photo-card">
				<div class="daily-photo-wrapper">
					<img src="{{ $U('/img/control_center/precios_registrar_epc.jpg') }}" alt="Identificación de productos con EPC RFID" class="daily-photo-img" loading="lazy">
					<div class="daily-photo-overlay"></div>
					<div class="daily-photo-badge-top">
						<span class="badge badge-warning text-dark font-weight-700 px-2 py-1 shadow-xs" style="font-size: 0.68rem; border-radius: 6px;">
							<i class="fa-solid fa-triangle-exclamation mr-1"></i>Registro manual
						</span>
					</div>
					<div class="daily-photo-icon-bubble">
						<i class="fa-solid fa-barcode text-amber-600" style="font-size: 0.95rem;"></i>
					</div>
				</div>
				<div class="daily-photo-content">
					<h6 class="font-weight-bold text-dark mb-1" style="font-size: 0.95rem;">Registrar EPC / RFID</h6>
					<p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.78rem; line-height: 1.35;">
						Lector no integrado. Registra discrepancias manualmente hasta conectar la fuente.
					</p>
					<button type="button" class="daily-btn-secondary w-100 justify-content-center" onclick="dailyOpenRegisterModal('epc');">
						<i class="fa-solid fa-file-pen text-slate-500"></i>Registrar discrepancia
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN E: GESTIÓN DE CASOS Y ESTADO SIN CASOS
	     ====================================================================== -->
	@if(count($rows) === 0)
		<!-- Estado Sin Casos centralizado -->
		<div class="daily-empty-box mb-4">
			<div class="daily-empty-icon-wrap mb-3 shadow-xs">
				<i class="fa-regular fa-file-lines text-slate-500"></i>
			</div>
			<h5 class="font-weight-bold text-dark mb-1" style="font-size: 1.15rem;">
				Sin discrepancias registradas
			</h5>
			<p class="text-muted mb-2 mx-auto" style="max-width: 540px; font-size: 0.86rem; line-height: 1.4;">
				Ejecuta Detectar sobre ventas disponibles o registra una observación de góndola.
			</p>
			<div class="small text-slate-500 mb-4 font-weight-500" style="font-size: 0.76rem;">
				<i class="fa-solid fa-circle-info mr-1 text-slate-400"></i>
				Sin casos registrados no confirma que todos los precios sean correctos.
			</div>
			<div class="d-flex justify-content-center align-items-center flex-wrap" style="gap: 0.6rem;">
				<button type="button" class="daily-action-btn-green" onclick="dailyOpenDetectModal();">
					<i class="fa-solid fa-radar mr-1"></i>Detectar
				</button>
				<button type="button" class="daily-btn-secondary" onclick="dailyOpenRegisterModal('shelf_vs_pos');">
					<i class="fa-solid fa-plus mr-1 text-slate-600"></i>Registrar observación
				</button>
			</div>
		</div>
	@endif

	<!-- Bloque de Casos de Precios y Marcación (Tabla y Filtros) -->
	<div class="daily-card mb-4" id="dailyPreciosTableWrap">
		<div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.75rem;">
			<div>
				<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
					Casos de precios y marcación
				</h6>
			</div>

			<!-- Buscador en tiempo real -->
			<div class="position-relative" style="min-width: 240px; max-width: 340px; width: 100%;">
				<i class="fa-solid fa-magnifying-glass text-muted position-absolute" style="top: 50%; transform: translateY(-50%); left: 10px; font-size: 0.78rem;"></i>
				<input type="text" id="dailyPreciosSearch" class="form-control form-control-sm pl-4" placeholder="Buscar por producto, código, caso..." style="border-radius: 8px; font-size: 0.8rem; height: 32px;" oninput="dailyFilterCasesTable();">
			</div>
		</div>

		<!-- Pestañas de filtrado de estado -->
		<div class="px-3 pt-2 pb-1 border-bottom bg-slate-50 d-flex align-items-center justify-content-between flex-wrap" style="gap: 0.5rem;">
			<div class="daily-filter-pills py-1">
				<span class="daily-filter-pill is-active" data-status-filter="all" onclick="dailySetCaseTabFilter('all', this);">
					<i class="fa-solid fa-layer-group"></i>Todos ({{ $countTodos }})
				</span>
				<span class="daily-filter-pill" data-status-filter="por_validar" onclick="dailySetCaseTabFilter('por_validar', this);">
					<i class="fa-solid fa-clock"></i>Por validar ({{ $countPorValidar }})
				</span>
				<span class="daily-filter-pill" data-status-filter="en_correccion" onclick="dailySetCaseTabFilter('en_correccion', this);">
					<i class="fa-solid fa-wrench"></i>En corrección ({{ $countEnCorreccion }})
				</span>
				<span class="daily-filter-pill" data-status-filter="cerrados" onclick="dailySetCaseTabFilter('cerrados', this);">
					<i class="fa-solid fa-check"></i>Cerrados ({{ $countCerrados }})
				</span>
			</div>
			<div class="small text-muted py-1" style="font-size: 0.74rem;">
				<span id="dailyCasesShownCount">{{ $countTodos }}</span> registros
			</div>
		</div>

		<!-- Tabla de casos -->
		<div class="table-responsive">
			<table class="daily-cases-table" id="dailyCasesTable" data-export-title="Casos de precios y marcación">
				<thead>
					<tr>
						<th style="width: 70px;">Caso</th>
						<th>Producto</th>
						<th>Origen</th>
						<th class="text-right">Precio autorizado</th>
						<th class="text-right">Precio observado</th>
						<th>Impacto</th>
						<th>Estado</th>
						<th>Responsable</th>
						<th class="text-right" style="width: 140px;">Acción</th>
					</tr>
				</thead>
				<tbody id="dailyCasesTableBody">
					@if(count($rows) === 0)
						<tr id="dailyNoCasesRow">
							<td colspan="9" class="text-center py-5 text-muted">
								<i class="fa-regular fa-folder-open fa-2x mb-2 d-block text-slate-300"></i>
								<div class="font-weight-600 text-dark small">Sin casos para mostrar</div>
								<div class="text-xs text-muted">Ejecuta una detección en ventas o registra una observación de góndola.</div>
							</td>
						</tr>
					@else
						@foreach($rows as $r)
							@php
								$i = array_search($r['status'], $price['flow'], true);
								$next = $i !== false && $i < count($price['flow']) - 1 ? $price['flow'][$i + 1] : null;
								$canStep = $next && (!in_array($next, ['approved', 'closed']) || $canApprove);

								// Mapeo de categoría de tab
								$tabCat = 'en_correccion';
								if ($r['status'] === 'detected') {
									$tabCat = 'por_validar';
								} elseif (in_array($r['status'], ['closed', 'rejected'], true)) {
									$tabCat = 'cerrados';
								}

								// Origen descriptivo
								$sourceMap = [
									'pos_vs_authorized' => 'Ventas POS',
									'shelf_vs_pos' => 'Góndola',
									'epc' => 'EPC / RFID',
									'barcode_sku' => 'Marcación / Barcode',
									'branch_diff' => 'Multi-sucursal',
									'expired_promo' => 'Promoción',
									'discount' => 'Descuento'
								];
								$origenLbl = $sourceMap[$r['kind']] ?? ($r['source'] === 'auto' ? 'Automático' : 'Manual');
							@endphp
							<tr class="daily-case-row" data-tab-cat="{{ $tabCat }}" data-search-text="{{ strtolower($r['id'] . ' ' . $r['product_name'] . ' ' . $r['sku'] . ' ' . $r['barcode'] . ' ' . $origenLbl . ' ' . ($kinds[$r['kind']] ?? '')) }}">
								<td class="font-mono small font-weight-700 text-dark">
									#{{ $r['id'] }}
									<div class="text-xs text-muted font-sans font-weight-normal">{{ $r['source'] === 'auto' ? 'Auto' : 'Manual' }}</div>
								</td>
								<td>
									<div class="font-weight-600 text-dark">{{ $r['product_name'] }}</div>
									<div class="small text-muted font-mono" style="font-size: 0.74rem;">
										{{ $r['sku'] ?: '—' }}{{ $r['barcode'] ? ' · ' . $r['barcode'] : '' }}
									</div>
								</td>
								<td>
									<span class="badge badge-light border text-slate-700 font-weight-600 px-2 py-1" style="font-size: 0.72rem; border-radius: 6px;">
										{{ $origenLbl }}
									</span>
									<div class="text-xs text-muted mt-0.5">{{ $r['branch_name'] ?? 'Todas las sucursales' }}</div>
								</td>
								<td class="text-right">
									<div class="font-weight-600 text-dark">
										{{ $r['authorized_price'] !== null ? $money($r['authorized_price']) : '—' }}
									</div>
									@if($r['proposed_price'] !== null)
										<div class="text-xs font-weight-600 text-amber-700" title="Precio propuesto (pendiente de aprobación)">
											→ {{ $money($r['proposed_price']) }}
										</div>
									@endif
								</td>
								<td class="text-right font-weight-600 text-dark">
									@if($r['pos_price'] !== null)
										{{ $money($r['pos_price']) }} <span class="text-xs text-muted font-weight-normal">(POS)</span>
									@elseif($r['shelf_price'] !== null)
										{{ $money($r['shelf_price']) }} <span class="text-xs text-muted font-weight-normal">(Góndola)</span>
									@else
										—
									@endif
								</td>
								<td>
									@php
										$impClass = 'badge-secondary';
										if ($r['impact_type'] === 'subcobro') $impClass = 'badge-danger';
										elseif ($r['impact_type'] === 'sobrecobro') $impClass = 'badge-warning';
										elseif ($r['impact_type'] === 'devolucion' || $r['impact_type'] === 'perdida_comercial') $impClass = 'badge-purple';
									@endphp
									<span class="badge {{ $impClass }} font-weight-600 px-2 py-0.5" style="font-size: 0.72rem; border-radius: 4px;">
										{{ $impLbl[$r['impact_type']] ?? '—' }}
									</span>
									<div class="small font-weight-700 text-dark mt-0.5">
										{{ $money($r['impact_amount']) }}
										<span class="text-xs font-weight-normal text-muted">{{ $r['impact_verified'] ? '✓ verificado' : '(estimado)' }}</span>
									</div>
								</td>
								<td>
									@if($r['status'] === 'rejected')
										<span class="badge badge-danger px-2 py-0.5 font-weight-600" style="font-size: 0.72rem; border-radius: 4px;">
											<i class="fa-solid fa-ban mr-1"></i>Rechazada
										</span>
									@elseif($r['status'] === 'closed')
										<span class="badge badge-success px-2 py-0.5 font-weight-600" style="font-size: 0.72rem; border-radius: 4px;">
											<i class="fa-solid fa-check mr-1"></i>Cerrado
										</span>
									@else
										<div class="small font-weight-600 text-dark" style="font-size: 0.74rem;">
											{{ $i + 1 }}/{{ count($price['flow']) }} · {{ $flowLbl[$r['status']] ?? $r['status'] }}
										</div>
										<div class="daily-step-bar-wrap" title="Progreso {{ round(($i + 1) / count($price['flow']) * 100) }}%">
											<div class="daily-step-bar-fill" style="width: {{ round(($i + 1) / count($price['flow']) * 100) }}%;"></div>
										</div>
									@endif
								</td>
								<td class="small text-muted" style="font-size: 0.76rem;">
									@if($r['approved_by_user_id'])
										<i class="fa-solid fa-user-check text-success mr-1"></i>Aprobado
									@elseif($r['detected_by_user_id'])
										<i class="fa-solid fa-user text-slate-400 mr-1"></i>Usuario #{{ $r['detected_by_user_id'] }}
									@else
										<span class="text-slate-400">Sistema</span>
									@endif
								</td>
								<td class="text-right text-nowrap">
									@if($canStep)
										<button type="button" class="btn btn-xs btn-outline-dark font-weight-600 px-2 py-1" onclick="dailyOpenAdvanceDialog({{ $r['id'] }}, '{{ $next }}', '{{ $flowLbl[$next] }}', '{{ $r['status'] }}', {{ json_encode($r) }});">
											{{ $flowLbl[$next] }}
										</button>
									@elseif($next)
										<span class="small text-muted font-italic" style="font-size: 0.72rem;">Requiere supervisor</span>
									@endif

									@if(!in_array($r['status'], ['closed', 'rejected']))
										<button type="button" class="btn btn-xs btn-link text-danger p-0 ml-2" title="Rechazar discrepancia" onclick="dailyRejectPriceIssue({{ $r['id'] }});">
											Rechazar
										</button>
									@endif
								</td>
							</tr>
						@endforeach
					@endif
				</tbody>
			</table>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN F: EXPLICACIÓN DEL IMPACTO ECONÓMICO
	     Separación estricta entre subcobro, sobrecobro y estimado
	     ====================================================================== -->
	<div class="mb-4">
		<div class="d-flex align-items-center mb-2 px-1" style="gap: 0.45rem;">
			<span class="font-weight-bold text-dark small" style="font-size: 0.88rem;">Cómo se clasifica el impacto</span>
			<i class="fa-solid fa-circle-info text-slate-400" title="Definiciones económicas para evitar doble contabilización y distinguir merma de riesgo comercial"></i>
		</div>
		<div class="row row-cols-1 row-cols-md-3 g-3">
			<!-- Subcobro -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-impact-card">
					<div class="daily-impact-icon-circle bg-red-100 text-danger" style="background: #fee2e2; color: #dc2626;">
						<i class="fa-solid fa-arrow-down"></i>
					</div>
					<div>
						<div class="font-weight-bold text-dark small mb-1" style="font-size: 0.88rem;">Subcobro</div>
						<div class="text-muted text-xs" style="font-size: 0.76rem; line-height: 1.35;">
							Pérdida comercial comprobada para la empresa (ventas por debajo del precio autorizado).
						</div>
					</div>
				</div>
			</div>

			<!-- Sobrecobro -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-impact-card">
					<div class="daily-impact-icon-circle bg-amber-100 text-amber-700" style="background: #fef3c7; color: #b45309;">
						<i class="fa-solid fa-arrow-up"></i>
					</div>
					<div>
						<div class="font-weight-bold text-dark small mb-1" style="font-size: 0.88rem;">Sobrecobro</div>
						<div class="text-muted text-xs" style="font-size: 0.76rem; line-height: 1.35;">
							Diferencia al cliente, no es merma (ventas por encima del precio autorizado).
						</div>
					</div>
				</div>
			</div>

			<!-- Estimado -->
			<div class="col">
				<div class="daily-impact-card">
					<div class="daily-impact-icon-circle bg-slate-100 text-slate-600" style="background: #f1f5f9; color: #475569;">
						<i class="fa-solid fa-ellipsis"></i>
					</div>
					<div>
						<div class="font-weight-bold text-dark small mb-1" style="font-size: 0.88rem;">Estimado</div>
						<div class="text-muted text-xs" style="font-size: 0.76rem; line-height: 1.35;">
							Impacto pendiente de validación. Aún no confirmado como pérdida o diferencia.
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN G: MÓDULOS CONECTADOS CON CENTRO DE CONTROL
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-2">
		<div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
			<div class="d-flex align-items-center" style="gap: 0.5rem;">
				<i class="fa-solid fa-network-wired text-success" style="font-size: 1rem;"></i>
				<span class="font-weight-bold text-dark small" style="font-size: 0.92rem;">
					Módulos conectados con Centro de Control
				</span>
			</div>
			<a href="{{ $U('/reports-hub') }}" class="small text-muted font-weight-600" style="font-size: 0.76rem; text-decoration: none;">
				Ver mapa del sistema <i class="fa-solid fa-arrow-right ml-1"></i>
			</a>
		</div>

		<div class="row">
			<!-- Columna 1: RECIBE INFORMACIÓN DE -->
			<div class="col-12 col-lg-6 mb-3 mb-lg-0">
				<div class="text-uppercase text-muted font-weight-700 text-xs mb-2" style="font-size: 0.69rem; letter-spacing: 0.05em;">
					RECIBE INFORMACIÓN DE
				</div>
				
				<a href="{{ $U('/waste#tab-fefo') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.75rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-gear"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Calidad & Vencimientos</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Rebajas por vencimiento (prevención antes de la merma)</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>

				<a href="{{ $U('/workforce') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.75rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-list-check"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Tareas y Roles</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma exprés desde Piso de Tienda</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>

			<!-- Columna 2: ACTUALIZA / ALIMENTA A -->
			<div class="col-12 col-lg-6">
				<div class="text-uppercase text-muted font-weight-700 text-xs mb-2" style="font-size: 0.69rem; letter-spacing: 0.05em;">
					ACTUALIZA / ALIMENTA A
				</div>

				<a href="{{ $U('/executive-dashboard') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.75rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-chart-line"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Panel Ejecutivo</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma en el Panel Ejecutivo</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>

				<a href="{{ $U('/cost-centers') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.75rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-building-columns"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Centros de Costo</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma por centro de costo</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>

				<a href="{{ $U('/reports-hub') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.75rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Reportes & Analítica</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Desglose de merma (censo) en Reportes</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>
		</div>
	</div>

</div>

<!-- ==========================================================================
     MODALS Y SCRIPTS INTERACTIVOS DE DETECCIÓN, REGISTRO Y AVANCE
     ========================================================================== -->

<!-- Modal 1: Detectar discrepancias en ventas -->
<div class="modal fade" id="modalDailyDetectPrecios" tabindex="-1" role="dialog" aria-labelledby="modalDailyDetectPreciosLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white border-bottom py-3">
				<h6 class="modal-title font-weight-bold text-dark" id="modalDailyDetectPreciosLabel">
					<i class="fa-solid fa-radar text-success mr-2"></i>Detectar discrepancias de precios
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="formDailyDetectPrecios" onsubmit="return dailySubmitDetect(event, this);">
				<div class="modal-body py-3">
					<div class="form-row">
						<div class="form-group col-6">
							<label class="small font-weight-700 text-dark mb-1">Desde</label>
							<input class="form-control form-control-sm" type="date" name="from" value="{{ date('Y-m-d', strtotime('-30 days')) }}" required style="border-radius: 6px;">
						</div>
						<div class="form-group col-6">
							<label class="small font-weight-700 text-dark mb-1">Hasta</label>
							<input class="form-control form-control-sm" type="date" name="to" value="{{ date('Y-m-d') }}" required style="border-radius: 6px;">
						</div>
					</div>

					<div class="form-group">
						<label class="small font-weight-700 text-dark mb-1">Sucursal</label>
						<select class="form-control form-control-sm" name="branch_id" style="border-radius: 6px;">
							<option value="">Todas las sucursales</option>
							@foreach($branches as $b)
								<option value="{{ $b['id'] }}" {{ ($selectedBranchId == $b['id']) ? 'selected' : '' }}>
									{{ $b['name'] }}
								</option>
							@endforeach
						</select>
					</div>

					<div class="p-3 bg-slate-50 border rounded text-xs text-muted" style="border-radius: 8px; line-height: 1.4;">
						<div class="font-weight-700 text-dark mb-1"><i class="fa-solid fa-info-circle text-slate-500 mr-1"></i>Reglas de comparación de ventas:</div>
						Compara cada línea de venta completada contra el precio autorizado vigente en esa fecha exacta, detecta códigos de barras vinculados a otro SKU y analiza diferencias entre sucursales el mismo día. 
						<div class="text-slate-700 font-weight-600 mt-1">No modifica precios existentes ni altera catálogos.</div>
					</div>
				</div>
				<div class="modal-footer bg-white border-top py-2">
					<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="daily-action-btn-green py-1.5 px-3" id="btnDailySubmitDetect">
						<i class="fa-solid fa-play mr-1"></i>Ejecutar detección
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal 2: Registrar observación de precio / góndola / EPC -->
<div class="modal fade" id="modalDailyRegisterPrecio" tabindex="-1" role="dialog" aria-labelledby="modalDailyRegisterPrecioLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white border-bottom py-3">
				<h6 class="modal-title font-weight-bold text-dark" id="modalDailyRegisterPrecioLabel">
					<i class="fa-solid fa-circle-plus text-success mr-2"></i>Registrar observación de precio / marcación
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="formDailyRegisterPrecio" onsubmit="return dailySubmitRegister(event, this);">
				<div class="modal-body py-3">
					<div class="form-row">
						<div class="form-group col-md-6">
							<label class="small font-weight-700 text-dark mb-1">Tipo de discrepancia</label>
							<select class="form-control form-control-sm" name="kind" id="registerKindSelect" required style="border-radius: 6px;">
								@foreach($kinds as $k => $lbl)
									<option value="{{ $k }}">{{ $lbl }}</option>
								@endforeach
							</select>
						</div>
						<div class="form-group col-md-6">
							<label class="small font-weight-700 text-dark mb-1">Sucursal</label>
							<select class="form-control form-control-sm" name="branch_id" style="border-radius: 6px;">
								<option value="">Todas las sucursales</option>
								@foreach($branches as $b)
									<option value="{{ $b['id'] }}" {{ ($selectedBranchId == $b['id']) ? 'selected' : '' }}>
										{{ $b['name'] }}
									</option>
								@endforeach
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="small font-weight-700 text-dark mb-1">Producto</label>
						<select class="form-control form-control-sm" name="product_id" required style="border-radius: 6px;">
							<option value="">Seleccione un producto...</option>
							@foreach($products as $p)
								<option value="{{ $p['id'] }}">
									{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['name'] }}
								</option>
							@endforeach
						</select>
					</div>

					<div class="form-row">
						<div class="form-group col-4">
							<label class="small font-weight-700 text-dark mb-1">Precio autorizado ($)</label>
							<input class="form-control form-control-sm" type="number" step="0.01" min="0" name="authorized_price" placeholder="0.00" style="border-radius: 6px;">
						</div>
						<div class="form-group col-4">
							<label class="small font-weight-700 text-dark mb-1">Precio en POS ($)</label>
							<input class="form-control form-control-sm" type="number" step="0.01" min="0" name="pos_price" placeholder="0.00" style="border-radius: 6px;">
						</div>
						<div class="form-group col-4">
							<label class="small font-weight-700 text-dark mb-1">Precio en góndola ($)</label>
							<input class="form-control form-control-sm" type="number" step="0.01" min="0" name="shelf_price" placeholder="0.00" style="border-radius: 6px;">
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-6">
							<label class="small font-weight-700 text-dark mb-1">Unidades afectadas</label>
							<input class="form-control form-control-sm" type="number" step="any" min="0" name="units_affected" value="0" style="border-radius: 6px;">
							<small class="text-muted text-xs">0 si aún es una estimación sin conteo físico</small>
						</div>
						<div class="form-group col-6">
							<label class="small font-weight-700 text-dark mb-1">Código de barras / EPC RFID</label>
							<input class="form-control form-control-sm" type="text" name="barcode" placeholder="Ej: 7701234567890 o EPC-01" style="border-radius: 6px;">
							<small class="text-muted text-xs" id="epcHelpText">Identificación leída o visible</small>
						</div>
					</div>

					<div class="form-group mb-2">
						<label class="small font-weight-700 text-dark mb-1">Detalle / Evidencia observada</label>
						<textarea class="form-control form-control-sm" name="detail" rows="2" placeholder="Describa la discrepancia observada (etiqueta rota, precio diferente en caja, etc.)" style="border-radius: 6px;"></textarea>
					</div>

					<div class="p-2.5 bg-slate-50 border rounded text-xs text-muted" style="border-radius: 8px;">
						<i class="fa-solid fa-lock text-amber-600 mr-1"></i>
						<strong>Segregación y auditoría:</strong> La observación se registrará en el flujo y no altera precios en ningún sistema hasta su validación formal y aprobación de un supervisor.
					</div>
				</div>
				<div class="modal-footer bg-white border-top py-2">
					<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
					<button type="submit" class="daily-action-btn-green py-1.5 px-3" id="btnDailySubmitRegister">
						<i class="fa-solid fa-check mr-1"></i>Registrar observación
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
/* ==========================================================================
   DAILY CONTROL DE PRECIOS — LÓGICA DE INTERFAZ Y ACCIONES AJAX
   ========================================================================== */

function dailyOpenDetectModal() {
	$('#modalDailyDetectPrecios').modal('show');
}

function dailyOpenRegisterModal(preselectKind) {
	if (preselectKind) {
		$('#registerKindSelect').val(preselectKind);
		if (preselectKind === 'epc') {
			$('#epcHelpText').text('Lector no integrado: ingrese el código EPC / TAG manualmente');
		} else {
			$('#epcHelpText').text('Identificación leída o visible');
		}
	}
	$('#modalDailyRegisterPrecio').modal('show');
}

function dailySubmitDetect(e, form) {
	e.preventDefault();
	var btn = $('#btnDailySubmitDetect');
	var origHtml = btn.html();
	btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i>Detectando...');

	var data = {};
	$(form).serializeArray().forEach(function(item) {
		data[item.name] = item.value;
	});

	DairyLoss.post('loss/price-issues/detect', data, function(res) {
		$('#modalDailyDetectPrecios').modal('hide');
		btn.prop('disabled', false).html(origHtml);
		toastr.success('Detección ejecutada con éxito');
		if (typeof dairyRefreshActiveTab === 'function') {
			dairyRefreshActiveTab();
		} else {
			location.reload();
		}
	}, btn);
	return false;
}

function dailySubmitRegister(e, form) {
	e.preventDefault();
	var btn = $('#btnDailySubmitRegister');
	var origHtml = btn.html();
	btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i>Guardando...');

	var data = {};
	$(form).serializeArray().forEach(function(item) {
		data[item.name] = item.value;
	});

	DairyLoss.post('loss/price-issues', data, function(res) {
		$('#modalDailyRegisterPrecio').modal('hide');
		form.reset();
		btn.prop('disabled', false).html(origHtml);
		toastr.success('Discrepancia registrada correctamente');
		if (typeof dairyRefreshActiveTab === 'function') {
			dairyRefreshActiveTab();
		} else {
			location.reload();
		}
	}, btn);
	return false;
}

var currentCaseTab = 'all';

function dailySetCaseTabFilter(tab, el) {
	currentCaseTab = tab;
	$('.daily-filter-pill').removeClass('is-active');
	$(el).addClass('is-active');
	dailyFilterCasesTable();
}

function dailyFilterCasesTable() {
	var search = ($('#dailyPreciosSearch').val() || '').toLowerCase().trim();
	var rows = $('.daily-case-row');
	var visibleCount = 0;

	rows.each(function() {
		var row = $(this);
		var tabCat = row.data('tab-cat');
		var text = row.data('search-text') || '';

		var matchesTab = (currentCaseTab === 'all') || (tabCat === currentCaseTab);
		var matchesSearch = !search || text.indexOf(search) !== -1;

		if (matchesTab && matchesSearch) {
			row.show();
			visibleCount++;
		} else {
			row.hide();
		}
	});

	$('#dailyCasesShownCount').text(visibleCount);
	var noRowsTr = $('#dailyNoCasesRow');
	if (noRowsTr.length) {
		if (visibleCount === 0) {
			noRowsTr.show();
		} else {
			noRowsTr.hide();
		}
	}
}

function dailyOpenAdvanceDialog(id, next, nextLbl, currentStatus, caseData) {
	var html = '';
	if (next === 'validated') {
		html += '<div class="form-group">' +
			'<label class="small font-weight-700">Unidades afectadas (verificadas físicamente)</label>' +
			'<input class="form-control form-control-sm" type="number" step="any" min="0" name="units_affected" value="' + (caseData.units_affected || 0) + '">' +
			'</div>' +
			'<div class="form-group">' +
			'<label class="small font-weight-700">Tipo de impacto contable</label>' +
			'<select class="form-control form-control-sm" name="impact_type">' +
			'<option value="">Calcular automáticamente según diferencia</option>' +
			'<option value="subcobro">Subcobro (pérdida comercial para la empresa)</option>' +
			'<option value="sobrecobro">Sobrecobro (diferencia al cliente, no es merma)</option>' +
			'<option value="devolucion">Devolución al cliente</option>' +
			'<option value="perdida_comercial">Pérdida comercial directa</option>' +
			'</select>' +
			'</div>';
	} else if (next === 'evidence') {
		html += '<div class="form-group">' +
			'<label class="small font-weight-700">Evidencia verificable (foto góndola, ticket o acta)</label>' +
			'<input class="form-control form-control-sm" name="evidence_path" placeholder="Ej: Ticket #4592 / Foto góndola pasillo 3" required>' +
			'<small class="text-muted text-xs">Obligatoria para avanzar a corrección</small>' +
			'</div>';
	} else if (next === 'corrected') {
		html += '<div class="form-group">' +
			'<label class="small font-weight-700">Precio propuesto para actualización ($)</label>' +
			'<input class="form-control form-control-sm" type="number" step="0.01" min="0" name="proposed_price" value="' + (caseData.authorized_price || '') + '" required>' +
			'<small class="text-muted text-xs">Se registra solo como propuesta. No modifica el precio activo hasta la aprobación.</small>' +
			'</div>';
	} else if (next === 'approved') {
		html += '<div class="alert alert-warning py-2 px-3 text-xs mb-3">' +
			'<i class="fa-solid fa-triangle-exclamation mr-1"></i>' +
			'<strong>Segregación de funciones:</strong> Quien detectó o propuso la corrección no puede aprobar su propio cambio. Se actualizará el precio autorizado oficial en el sistema.' +
			'</div>';
	} else if (next === 'pos_synced') {
		html += '<div class="form-group">' +
			'<label class="small font-weight-700">Referencia de sincronización POS</label>' +
			'<input class="form-control form-control-sm" name="pos_sync_reference" placeholder="Ej: Lote POS-2026-042 / Ticket #9812" required>' +
			'<small class="text-muted text-xs">Indique el identificador de transmisión exitosa al punto de venta</small>' +
			'</div>';
	} else if (next === 'verified' || next === 'closed') {
		html += '<div class="alert alert-info py-2 px-3 text-xs mb-3">' +
			'<i class="fa-solid fa-circle-check mr-1"></i>' +
			'Confirme que el precio corregido fue verificado físicamente en tienda y caja antes del cierre final.' +
			'</div>';
	}

	html += '<div class="form-group mb-0">' +
		'<label class="small font-weight-700">Nota de auditoría</label>' +
		'<input class="form-control form-control-sm" name="note" placeholder="Detalle de la acción realizada...">' +
		'</div>';

	DairyLoss.advanceDialog(id, next, 'Avanzar a: ' + nextLbl, html);
}

function dailyRejectPriceIssue(id) {
	var html = '<div class="form-group">' +
		'<label class="small font-weight-700 text-danger">Motivo obligatorio del rechazo</label>' +
		'<textarea class="form-control form-control-sm" name="note" rows="3" placeholder="Explique por qué no aplica esta discrepancia (ej. descuento temporal autorizado por gerencia)..." required></textarea>' +
		'</div>';
	DairyLoss.advanceDialog(id, 'rejected', 'Rechazar discrepancia #' + id, html);
}
</script>
