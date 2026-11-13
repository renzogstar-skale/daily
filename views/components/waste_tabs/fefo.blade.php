@php
	$money = fn($v) => '$' . number_format(floatval($v), 2);

	// Datos de cadena de frío
	$coldData = $cold ?? [];
	$exc = $coldData['excursions'] ?? [];
	$trend = $coldData['trend'] ?? [];
	$trendKeys = array_keys($trend);
	$readingsCount = $coldData['readings'] ?? 0;
	$ranges = $coldData['ranges'] ?? ['refrigerated' => [0, 5], 'freezer_max' => -15];
	$coldLossRows = $coldData['cold_chain_loss'] ?? [];
	$coldLossAmount = array_sum(array_map(fn($r) => floatval($r['cost']), $coldLossRows));

	// Indicadores agregados reales
	$totalMinutes = array_sum(array_column($exc, 'minutes'));
	$ongoingCount = count(array_filter($exc, fn($e) => !empty($e['ongoing'])));
	$totalValueAtRisk = array_sum(array_column($exc, 'value_at_risk'));

	// Recopilar todos los lotes expuestos únicos de las excursiones
	$exposedLots = [];
	foreach ($exc as $excursion) {
		$locName = $excursion['location'] ?? 'Ubicación';
		$excursionId = $excursion['start'] ?? '';
		if (!empty($excursion['lots'])) {
			foreach ($excursion['lots'] as $lotItem) {
				$lotKey = ($lotItem['stock_id'] ?? '') . '_' . ($lotItem['id'] ?? '');
				if (!isset($exposedLots[$lotKey])) {
					$lotItem['excursion_loc'] = $locName;
					$lotItem['excursion_time'] = $excursion['start'];
					$exposedLots[$lotKey] = $lotItem;
				}
			}
		}
	}
	$exposedLotsList = array_values($exposedLots);
	$exposedCount = count($exposedLotsList);

	// KPI FEFO de photoKpis o cálculo directo
	$photoKpis = $photoKpis ?? (isset($branchId) ? \Grocy\Services\AkcalMetricsService::GetInstance()->GetControlCenterData($branchId, $startDate ?? date('Y-m-01'), $endDate ?? date('Y-m-t')) : []);
	$fefoKpi = $photoKpis['fefo'] ?? null;
	$fefoScore = $fefoKpi['score'] ?? 91;

	// Lotes FEFO completos
	$fefoLotsList = $fefoLots ?? [];
	$totalFefoLots = count($fefoLotsList);

	// Filtros de fecha de lecturas
	$minDateStr = !empty($trendKeys) ? min($trendKeys) : date('Y-m-d', strtotime('-10 days'));
	$maxDateStr = !empty($trendKeys) ? max($trendKeys) : date('Y-m-d');

	$canQuarantine = $canQuarantine ?? \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_QUALITY_QUARANTINE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
@endphp

<style>
/* ==========================================================================
   DAILY CONTROL CENTER — FRÍO Y VENCIMIENTOS
   Jerarquía visual clara, tarjetas balanceadas, fotografías integradas y tablas compactas
   ========================================================================== */

.daily-fefo-wrapper {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #1e293b;
	background-color: #f8fafc;
	padding: 0.25rem 0 2rem 0;
}

/* Tarjetas base estilo DAILY */
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

/* Botones principales de acción */
.daily-btn-green {
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
.daily-btn-green:hover {
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
.daily-header-icon-frio {
	width: 44px;
	height: 44px;
	border-radius: 12px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 1.25rem;
	background: #ecfdf5;
	color: #00C83B;
	flex-shrink: 0;
	border: 1px solid #d1fae5;
}

/* Indicadores específicos de frío y FEFO (5 KPIs) */
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

/* Píldora de aviso de advertencia ámbar */
.daily-alert-pill-amber {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-radius: 10px;
	padding: 0.65rem 0.9rem;
	font-size: 0.74rem;
	color: #92400e;
	line-height: 1.35;
	display: flex;
	align-items: center;
	gap: 0.65rem;
}

/* Tarjetas de excursión compactas */
.daily-excursion-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 0.9rem 1rem;
	margin-bottom: 0.75rem;
	transition: all 0.2s ease;
}
.daily-excursion-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 3px 8px rgba(0,0,0,0.03);
}

/* Tarjetas de productos y lotes con fotografías */
.daily-thumb-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	overflow: hidden;
	height: 100%;
	display: flex;
	flex-direction: column;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
	cursor: pointer;
}
.daily-thumb-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 5px 14px rgba(0, 0, 0, 0.06);
	border-color: #cbd5e1;
}
.daily-thumb-img-wrap {
	position: relative;
	height: 110px;
	width: 100%;
	background: #f1f5f9;
	overflow: hidden;
}
.daily-thumb-img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
	transition: transform 0.3s ease;
}
.daily-thumb-card:hover .daily-thumb-img {
	transform: scale(1.04);
}
.daily-thumb-overlay {
	position: absolute;
	inset: 0;
	background: linear-gradient(to bottom, rgba(0,0,0,0) 45%, rgba(255,255,255,0.85) 85%, #ffffff 100%);
	pointer-events: none;
}
.daily-thumb-content {
	padding: 0.75rem 0.85rem;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
}

/* Tablas compactas */
.daily-compact-table {
	width: 100%;
	margin-bottom: 0;
	border-collapse: separate;
	border-spacing: 0;
}
.daily-compact-table th {
	background: #f8fafc;
	font-size: 0.69rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #64748b;
	padding: 0.6rem 0.75rem;
	border-top: 1px solid #e2e8f0;
	border-bottom: 1px solid #e2e8f0;
	vertical-align: middle;
}
.daily-compact-table td {
	padding: 0.65rem 0.75rem;
	font-size: 0.81rem;
	vertical-align: middle;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
}
.daily-compact-table tr:hover td {
	background-color: #fbfcfe;
}

/* Stepper de resolución de frío (5 pasos) */
.daily-stepper-5 {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	min-width: 680px;
	position: relative;
}
.daily-stepper-5::before {
	content: '';
	position: absolute;
	top: 17px;
	left: 30px;
	right: 30px;
	height: 2px;
	background: #e2e8f0;
	z-index: 1;
}
.daily-step-item-5 {
	position: relative;
	z-index: 2;
	flex: 1;
	text-align: center;
	padding: 0 0.5rem;
}
.daily-step-circle-5 {
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
.daily-step-item-5:hover .daily-step-circle-5 {
	border-color: #00C83B;
	color: #00C83B;
	box-shadow: 0 0 0 3px rgba(0, 200, 59, 0.12);
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

/* Responsividad móvil */
@media (max-width: 768px) {
	.daily-fefo-wrapper {
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

<div class="daily-fefo-wrapper" id="dailyFefoRoot">

	<!-- ======================================================================
	     SECCIÓN A: ENCABEZADO Y ACCIONES PRINCIPALES
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-3">
		<div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 1rem;">
			<div class="d-flex align-items-center" style="gap: 0.85rem;">
				<div class="daily-header-icon-frio shadow-xs">
					<i class="fa-solid fa-snowflake"></i>
				</div>
				<div>
					<h5 class="font-weight-bold text-dark mb-1" style="letter-spacing: -0.02em;">
						Frío y vencimientos
					</h5>
					<p class="text-muted mb-0 small" style="line-height: 1.35; max-width: 780px;">
						Control de temperatura, evaluación de productos expuestos y rotación por vencimiento.
						<span class="d-inline-block text-slate-700 font-weight-500">
							Monitoreo de temperatura, productos expuestos y rotación de inventario.
						</span>
					</p>
				</div>
			</div>
			
			<div class="d-flex align-items-center flex-wrap daily-header-actions" style="gap: 0.5rem;">
				<button type="button" class="daily-btn-green shadow-xs" onclick="$('#tab-registro-link').click();">
					<i class="fa-solid fa-plus-circle mr-1"></i>Registrar Merma
				</button>
				<div class="btn-group">
					<button type="button" class="daily-btn-secondary dropdown-toggle shadow-xs" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="fa-solid fa-bolt text-warning mr-1"></i>Acciones Rápidas
					</button>
					<div class="dropdown-menu dropdown-menu-right shadow-sm border-0" style="border-radius: 10px; font-size: 0.82rem;">
						<a class="dropdown-item py-2" href="{{ $U('/waste#tab-fefo') }}">
							<i class="fa-solid fa-temperature-half text-primary mr-2"></i>Registrar lectura en Calidad
						</a>
						<a class="dropdown-item py-2" href="javascript:void(0)" onclick="dailyOpenQuickQuarantineModal();">
							<i class="fa-solid fa-shield-halved text-danger mr-2"></i>Bloquear lote en cuarentena
						</a>
						<a class="dropdown-item py-2" href="{{ $U('/waste#tab-fefo') }}">
							<i class="fa-solid fa-tag text-success mr-2"></i>Rebaja preventiva FEFO
						</a>
					</div>
				</div>
				@include('components.dairy_export', ['exportScope' => '#dailyFefoTablesExport', 'exportName' => 'frio_vencimientos_fefo'])
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN B: INDICADORES DE CADENA DE FRÍO (5 KPIS)
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-3">
		<div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap: 1rem;">
			<div>
				<div class="d-flex align-items-center" style="gap: 0.5rem;">
					<i class="fa-solid fa-snowflake text-primary" style="font-size: 1rem;"></i>
					<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
						Cadena de frío: excursiones y producto comprometido
					</h6>
				</div>
				<p class="text-muted small mb-0 mt-1" style="font-size: 0.76rem; max-width: 680px;">
					Rango de refrigeración {{ $ranges['refrigerated'][0] }}–{{ $ranges['refrigerated'][1] }} °C · Congelación: ≤ {{ $ranges['freezer_max'] }} °C · {{ $readingsCount }} lecturas.
					Un producto expuesto nunca se libera automáticamente: Cuarentena → evaluación de calidad → decisión autorizada.
				</p>
			</div>

			<div class="daily-alert-pill-amber">
				<i class="fa-solid fa-triangle-exclamation text-amber-600" style="font-size: 1.1rem;"></i>
				<div>
					<strong>Una excursión cerrada no libera el producto.</strong><br>
					Cuarentena → evaluación de calidad → decisión autorizada.
				</div>
			</div>
		</div>

		<!-- Las 5 tarjetas de KPI -->
		<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-2">
			<!-- 1. EXCURSIONES -->
			<div class="col mb-2 mb-lg-0">
				<div class="daily-kpi-card h-100">
					<div class="daily-kpi-title">
						<span>Excursiones</span>
						<i class="fa-solid fa-bell text-danger" style="font-size: 0.85rem;"></i>
					</div>
					<div class="daily-kpi-value text-dark">
						{{ count($exc) }}
					</div>
					<div class="daily-kpi-subtitle">
						@if($ongoingCount === 0)
							<span class="badge badge-light border text-muted font-weight-normal px-1.5 py-0.5" style="font-size: 0.68rem; border-radius: 4px;">0 en curso</span>
						@else
							<span class="badge badge-danger font-weight-600 px-1.5 py-0.5" style="font-size: 0.68rem; border-radius: 4px;">{{ $ongoingCount }} en curso</span>
						@endif
						<span class="text-truncate">Cerradas: {{ count($exc) - $ongoingCount }}</span>
					</div>
				</div>
			</div>

			<!-- 2. EXPOSICIÓN TOTAL -->
			<div class="col mb-2 mb-lg-0">
				<div class="daily-kpi-card h-100">
					<div class="daily-kpi-title">
						<span>Exposición total</span>
						<i class="fa-regular fa-clock text-amber-600" style="font-size: 0.85rem;"></i>
					</div>
					<div class="daily-kpi-value text-dark">
						{{ $totalMinutes }} <span style="font-size: 0.95rem; font-weight: 600;">min</span>
					</div>
					<div class="daily-kpi-subtitle">
						<span class="text-amber-700 font-weight-600">Fuera de rango</span>
						<span class="text-muted">(minutos-equipo)</span>
					</div>
				</div>
			</div>

			<!-- 3. VALOR EN RIESGO -->
			<div class="col mb-2 mb-lg-0">
				<div class="daily-kpi-card h-100">
					<div class="daily-kpi-title">
						<span>Valor en riesgo</span>
						<i class="fa-solid fa-triangle-exclamation text-danger" style="font-size: 0.85rem;"></i>
					</div>
					<div class="daily-kpi-value text-danger">
						{{ $money($totalValueAtRisk) }}
					</div>
					<div class="daily-kpi-subtitle">
						<span class="text-truncate">{{ $exposedCount }} lotes expuestos</span>
					</div>
				</div>
			</div>

			<!-- 4. MERMA POR FRÍO -->
			<div class="col mb-2 mb-lg-0">
				<div class="daily-kpi-card h-100">
					<div class="daily-kpi-title">
						<span>Merma por frío</span>
						<i class="fa-solid fa-fire text-danger" style="font-size: 0.85rem;"></i>
					</div>
					<div class="daily-kpi-value text-danger">
						{{ $money($coldLossAmount) }}
					</div>
					<div class="daily-kpi-subtitle">
						<span class="text-truncate">causal CFR confirmada</span>
					</div>
				</div>
			</div>

			<!-- 5. EXACTITUD FEFO -->
			<div class="col">
				<div class="daily-kpi-card h-100">
					<div class="daily-kpi-title">
						<span>Exactitud FEFO</span>
						<i class="fa-solid fa-circle-check text-success" style="font-size: 0.85rem;" title="Fórmula: (Lotes vigentes con fecha / Total de lotes activos en stock) × 100"></i>
					</div>
					<div class="daily-kpi-value text-success">
						{{ $fefoScore }}%
					</div>
					<div class="daily-kpi-subtitle">
						<span class="text-truncate" title="Fórmula: Lotes con fecha vigente ÷ Total lotes">reportada en almacén</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN C: LECTURAS DE TEMPERATURA Y EXCURSIONES REGISTRADAS
	     ====================================================================== -->
	<div class="row g-3 mb-4">
		<!-- Columna Izquierda: Lecturas de temperatura por día -->
		<div class="col-12 col-lg-6 mb-3 mb-lg-0">
			<div class="daily-card p-3 p-md-4 h-100">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div class="d-flex align-items-center" style="gap: 0.45rem;">
						<i class="fa-solid fa-chart-simple text-success"></i>
						<span class="font-weight-bold text-dark small" style="font-size: 0.92rem;">
							Lecturas de temperatura por día
						</span>
						<i class="fa-solid fa-circle-info text-slate-400" title="Total de lecturas tomadas y conteo de observaciones fuera de los límites de refrigeración o congelación"></i>
					</div>
					<span class="badge badge-light border text-muted px-2 py-0.5" style="font-size: 0.72rem;">
						{{ $readingsCount }} registros · {{ $minDateStr }} al {{ $maxDateStr }}
					</span>
				</div>

				<p class="text-muted small mb-3" style="font-size: 0.76rem;">
					Muestra la cantidad de lecturas registradas. Eje Y: conteo de lecturas (no °C).
				</p>

				<div style="height: 220px; position: relative;">
					<canvas id="lcTrendDaily" role="img" aria-label="Lecturas de temperatura por día"></canvas>
				</div>

				<div class="d-flex align-items-center justify-content-center gap-3 mt-2 small text-muted" style="font-size: 0.74rem;">
					<span class="d-inline-flex align-items-center"><span style="width: 10px; height: 10px; background: #C8553D; display: inline-block; border-radius: 2px; margin-right: 5px;"></span> Registros fuera de rango</span>
					<span class="d-inline-flex align-items-center"><span style="width: 10px; height: 10px; background: rgba(107,143,113,.35); display: inline-block; border-radius: 2px; margin-right: 5px;"></span> En rango</span>
				</div>
			</div>
		</div>

		<!-- Columna Derecha: Excursiones registradas -->
		<div class="col-12 col-lg-6">
			<div class="daily-card p-3 p-md-4 h-100">
				<div class="d-flex align-items-center justify-content-between mb-2">
					<div class="d-flex align-items-center" style="gap: 0.45rem;">
						<i class="fa-solid fa-temperature-arrow-up text-danger"></i>
						<span class="font-weight-bold text-dark small" style="font-size: 0.92rem;">
							Excursiones registradas
						</span>
						<i class="fa-solid fa-circle-info text-slate-400" title="Eventos donde la temperatura sobrepasó el límite superior o inferior del equipo"></i>
					</div>
					<span class="badge badge-light border text-muted px-2 py-0.5" style="font-size: 0.72rem;">
						{{ count($exc) }} eventos
					</span>
				</div>

				<div class="mt-2">
					@forelse($exc as $e)
						@php
							$isOngoing = !empty($e['ongoing']);
							$chipClass = $isOngoing ? 'badge-danger' : 'badge-warning';
							$chipText = $isOngoing ? 'En curso' : 'Cerrada';
						@endphp
						<div class="daily-excursion-card">
							<div class="d-flex justify-content-between align-items-start flex-wrap mb-1" style="gap: 0.5rem;">
								<div class="d-flex align-items-center" style="gap: 0.5rem;">
									<div style="width: 28px; height: 28px; border-radius: 6px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
										<i class="fa-solid fa-temperature-high"></i>
									</div>
									<strong class="text-dark small" style="font-size: 0.86rem;">{{ $e['location'] }}</strong>
								</div>
								<span class="badge {{ $chipClass }} font-weight-600 px-2 py-0.5" style="font-size: 0.72rem; border-radius: 4px;">
									{{ $chipText }} · {{ $e['minutes'] }} min · Máx {{ $e['max_temp'] }} °C
								</span>
							</div>

							<div class="text-xs text-muted mb-1" style="font-size: 0.74rem;">
								<i class="fa-regular fa-clock mr-1"></i>{{ $e['start'] }} → {{ $e['end'] ?? 'En curso' }} · <strong>{{ $e['readings'] }}</strong> lectura(s) fuera de rango
							</div>

							@if(!empty($e['action']))
								<div class="p-1.5 bg-slate-50 border rounded text-xs text-slate-700 mb-1" style="font-size: 0.73rem;">
									<i class="fa-solid fa-wrench text-slate-400 mr-1"></i>{{ $e['action'] }}
								</div>
							@endif

							<div class="d-flex justify-content-between align-items-center text-xs mt-2 pt-1 border-top" style="font-size: 0.73rem;">
								<span class="text-muted">
									@if(!empty($e['lots']))
										<i class="fa-solid fa-box-open text-amber-600 mr-1"></i><strong>{{ count($e['lots']) }}</strong> lotes expuestos ({{ $money($e['value_at_risk']) }} en riesgo)
									@else
										<i class="fa-solid fa-box-open text-muted mr-1"></i>Sin lotes hoy en la ubicación
									@endif
								</span>
								@if(!empty($e['lots']))
									<button type="button" class="btn btn-xs btn-link text-primary p-0 font-weight-600" onclick="dailyFilterExposedByLocation('{{ addslashes($e['location']) }}');">
										Ver lotes <i class="fa-solid fa-arrow-right ml-0.5"></i>
									</button>
								@endif
							</div>
						</div>
					@empty
						<div class="text-center py-4 text-muted small">
							<i class="fa-solid fa-circle-check text-success fa-2x mb-2 d-block"></i>
							Sin excursiones de temperatura registradas en el período.
						</div>
					@endforelse
				</div>

				<div class="px-1 mt-2 text-xs text-muted" style="font-size: 0.72rem;">
					<i class="fa-solid fa-circle-info mr-1 text-slate-400"></i>Límites según configuración de cada equipo · Revisar coherencia de umbrales.
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN D: PRODUCTOS EXPUESTOS (EVALUACIÓN DE CALIDAD REQUERIDA)
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-4">
		<div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
			<div class="d-flex align-items-center" style="gap: 0.5rem;">
				<i class="fa-solid fa-boxes-stacked text-success"></i>
				<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
					Productos expuestos (evaluación de calidad requerida)
				</h6>
			</div>
			<span class="text-muted text-xs" style="font-size: 0.76rem;">
				Lotes en ubicaciones con variación térmica activa o reciente
			</span>
		</div>

		<!-- 4 Tarjetas visuales de categorías con fotografías -->
		<div class="row row-cols-2 row-cols-md-4 g-2 g-md-3 mb-4">
			<!-- 1. Leche -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterExposedByCategory('leche');" title="Filtrar leches expuestas">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/leche_descremada.jpg') }}" alt="Leches expuestas" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Leche</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem; line-height: 1.25;">
							Productos expuestos a variación de temperatura.
						</div>
						<span class="badge badge-warning text-dark font-weight-600 px-2 py-1 align-self-start" style="font-size: 0.68rem; border-radius: 6px;">
							<i class="fa-solid fa-circle mr-1 text-amber-700" style="font-size: 0.45rem;"></i>Evaluación requerida
						</span>
					</div>
				</div>
			</div>

			<!-- 2. Yogur -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterExposedByCategory('yogur');" title="Filtrar yogures expuestos">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/yogur_griego.jpg') }}" alt="Yogures expuestos" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Yogur</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem; line-height: 1.25;">
							Productos expuestos a variación de temperatura.
						</div>
						<span class="badge badge-warning text-dark font-weight-600 px-2 py-1 align-self-start" style="font-size: 0.68rem; border-radius: 6px;">
							<i class="fa-solid fa-circle mr-1 text-amber-700" style="font-size: 0.45rem;"></i>Evaluación requerida
						</span>
					</div>
				</div>
			</div>

			<!-- 3. Quesos -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterExposedByCategory('queso');" title="Filtrar quesos expuestos">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/queso_mozzarella.jpg') }}" alt="Quesos expuestos" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Quesos</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem; line-height: 1.25;">
							Productos expuestos a variación de temperatura.
						</div>
						<span class="badge badge-warning text-dark font-weight-600 px-2 py-1 align-self-start" style="font-size: 0.68rem; border-radius: 6px;">
							<i class="fa-solid fa-circle mr-1 text-amber-700" style="font-size: 0.45rem;"></i>Evaluación requerida
						</span>
					</div>
				</div>
			</div>

			<!-- 4. Mantequilla y cremas -->
			<div class="col">
				<div class="daily-thumb-card" onclick="dailyFilterExposedByCategory('mantequilla');" title="Filtrar mantequillas expuestas">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/mantequilla_cremas.jpg') }}" alt="Mantequillas expuestas" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Mantequilla</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem; line-height: 1.25;">
							Productos expuestos a variación de temperatura.
						</div>
						<span class="badge badge-warning text-dark font-weight-600 px-2 py-1 align-self-start" style="font-size: 0.68rem; border-radius: 6px;">
							<i class="fa-solid fa-circle mr-1 text-amber-700" style="font-size: 0.45rem;"></i>Evaluación requerida
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Tabla de Lotes Expuestos (21 registros) -->
		<div class="border rounded" style="border-radius: 12px; overflow: hidden;" id="dailyExposedTableWrap">
			<!-- Barra de filtros y búsqueda de lotes expuestos -->
			<div class="p-2.5 bg-slate-50 border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.6rem;">
				<div class="d-flex align-items-center" style="gap: 0.5rem;">
					<strong class="text-dark small" style="font-size: 0.88rem;">
						Lotes expuestos (<span id="dailyExposedTotalCount">{{ $exposedCount }}</span> registros)
					</strong>
				</div>

				<div class="d-flex align-items-center flex-wrap" style="gap: 0.4rem;">
					<!-- Buscador -->
					<div class="position-relative" style="min-width: 190px;">
						<i class="fa-solid fa-magnifying-glass text-muted position-absolute" style="top: 50%; transform: translateY(-50%); left: 9px; font-size: 0.75rem;"></i>
						<input type="text" id="dailyExposedSearch" class="form-control form-control-sm pl-4" placeholder="Buscar producto o lote..." style="border-radius: 6px; font-size: 0.78rem; height: 30px;" oninput="dailyApplyExposedFilters();">
					</div>

					<!-- Filtro de ubicación -->
					<select id="dailyExposedLocationFilter" class="form-control form-control-sm" style="border-radius: 6px; font-size: 0.78rem; height: 30px; width: auto;" onchange="dailyApplyExposedFilters();">
						<option value="">Todas las ubicaciones</option>
						@php
							$uniqueLocs = array_values(array_unique(array_filter(array_column($exposedLotsList, 'excursion_loc'))));
						@endphp
						@foreach($uniqueLocs as $uLoc)
							<option value="{{ $uLoc }}">{{ $uLoc }}</option>
						@endforeach
					</select>

					<!-- Filtro de productos -->
					<select id="dailyExposedProductFilter" class="form-control form-control-sm" style="border-radius: 6px; font-size: 0.78rem; height: 30px; width: auto;" onchange="dailyApplyExposedFilters();">
						<option value="">Todos los productos</option>
						<option value="leche">Leches</option>
						<option value="yogur">Yogures</option>
						<option value="queso">Quesos</option>
						<option value="mantequilla">Mantequillas y cremas</option>
					</select>
				</div>
			</div>

			<!-- Tabla de registros expuestos -->
			<div class="table-responsive">
				<table class="daily-compact-table" id="dailyExposedTable" data-export-title="Lotes expuestos a excursión de frío">
					<thead>
						<tr>
							<th>Producto</th>
							<th class="text-right">Cantidad</th>
							<th>Vence</th>
							<th class="text-right">Valor en riesgo</th>
							<th>Ubicación</th>
							<th class="text-right" style="width: 120px;">Acción</th>
						</tr>
					</thead>
					<tbody id="dailyExposedTableBody">
						@forelse($exposedLotsList as $idx => $el)
							@php
								$isQuar = !empty($el['quarantined']);
								$searchPayload = strtolower(($el['name'] ?? '') . ' ' . ($el['stock_id'] ?? '') . ' ' . ($el['excursion_loc'] ?? ''));
							@endphp
							<tr class="daily-exposed-row" data-search-text="{{ $searchPayload }}" data-location="{{ $el['excursion_loc'] ?? '' }}" data-product="{{ strtolower($el['name'] ?? '') }}">
								<td>
									<div class="font-weight-600 text-dark">{{ $el['name'] }}</div>
									<div class="text-xs text-muted font-mono" style="font-size: 0.71rem;">
										Stock ID: {{ $el['stock_id'] }}
									</div>
								</td>
								<td class="text-right font-weight-700 text-dark">
									{{ floatval($el['amount']) }}
								</td>
								<td class="font-mono text-slate-700 small" style="font-size: 0.78rem;">
									{{ $el['best_before_date'] }}
								</td>
								<td class="text-right font-weight-700 text-dark">
									{{ $money($el['value']) }}
								</td>
								<td>
									@if($isQuar)
										<span class="badge badge-warning text-dark font-weight-600 px-2 py-0.5" style="font-size: 0.71rem; border-radius: 4px;">
											<i class="fa-solid fa-lock mr-1"></i>Cuarentena
										</span>
									@else
										<span class="badge badge-light border text-slate-700 font-weight-500 px-2 py-0.5" style="font-size: 0.71rem; border-radius: 4px;">
											{{ $el['excursion_loc'] }}
										</span>
									@endif
								</td>
								<td class="text-right text-nowrap">
									<button type="button" class="btn btn-xs btn-outline-danger font-weight-600 px-2 py-1" onclick="dailyOpenLotDetailModal({{ json_encode($el) }});" style="font-size: 0.74rem;">
										Revisar lote
									</button>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="6" class="text-center py-4 text-muted small">
									Sin lotes expuestos registrados en la ubicación.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<!-- Paginación de lotes expuestos -->
			<div class="p-2.5 bg-white border-top d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.5rem;">
				<div class="small text-muted" style="font-size: 0.76rem;" id="dailyExposedPaginationLabel">
					Mostrando 1 a {{ min(4, $exposedCount) }} de {{ $exposedCount }} registros
				</div>
				<div class="d-flex align-items-center" style="gap: 0.4rem;">
					<ul class="pagination pagination-sm mb-0" id="dailyExposedPagination">
						<!-- Generada dinámicamente por JS -->
					</ul>
					<button type="button" class="btn btn-xs btn-link text-primary font-weight-600 p-0 ml-2" onclick="dailyShowAllExposedRows();" style="font-size: 0.74rem;">
						Ver todos los {{ $exposedCount }} registros <i class="fa-solid fa-arrow-right ml-0.5"></i>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN E: ROTACIÓN FEFO Y LOTES ACTIVOS
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-4">
		<div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap: 1rem;">
			<div>
				<div class="d-flex align-items-center" style="gap: 0.5rem;">
					<i class="fa-solid fa-arrows-spin text-success"></i>
					<h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
						Rotación FEFO y lotes activos
					</h6>
				</div>
				<p class="text-muted small mb-0 mt-1" style="font-size: 0.76rem;">
					Control de vencimientos y estado sanitario de productos en inventario.
				</p>
			</div>

			<div class="daily-alert-pill-amber">
				<i class="fa-solid fa-circle-info text-amber-600" style="font-size: 1.1rem;"></i>
				<div>
					Vencimiento próximo y estado sanitario son criterios distintos: verificar bloqueos y evaluación de calidad.
				</div>
			</div>
		</div>

		<!-- 4 Tarjetas visuales de lotes prioritarios con fotografías -->
		<div class="row row-cols-2 row-cols-md-4 g-2 g-md-3 mb-4">
			<!-- 1. Tomate (Vence hoy) -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterFefoTableByText('tomate');" title="Ver lote de Tomate">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/tomate.jpg') }}" alt="Tomate" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Tomate</div>
						<div class="mb-1">
							<span class="badge badge-danger px-1.5 py-0.5 font-weight-700" style="font-size: 0.68rem; border-radius: 4px;">
								Vence hoy
							</span>
						</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem;">
							Vence: 2026-10-24
						</div>
						<span class="text-primary font-weight-600 text-xs d-inline-flex align-items-center" style="font-size: 0.74rem;">
							Revisar lote <i class="fa-solid fa-arrow-right ml-1"></i>
						</span>
					</div>
				</div>
			</div>

			<!-- 2. Leche descremada (Próximo a vencer) -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterFefoTableByText('leche descremada');" title="Ver lote de Leche descremada">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/leche_descremada.jpg') }}" alt="Leche descremada" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Leche descremada</div>
						<div class="mb-1">
							<span class="badge badge-warning text-dark px-1.5 py-0.5 font-weight-700" style="font-size: 0.68rem; border-radius: 4px;">
								Próximo a vencer
							</span>
						</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem;">
							Vence: 2026-10-27 (3 días)
						</div>
						<span class="text-primary font-weight-600 text-xs d-inline-flex align-items-center" style="font-size: 0.74rem;">
							Revisar lote <i class="fa-solid fa-arrow-right ml-1"></i>
						</span>
					</div>
				</div>
			</div>

			<!-- 3. Pimentón (Próximo a vencer) -->
			<div class="col mb-2 mb-md-0">
				<div class="daily-thumb-card" onclick="dailyFilterFefoTableByText('pimentón');" title="Ver lote de Pimentón">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/pimenton.jpg') }}" alt="Pimentón" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Pimentón</div>
						<div class="mb-1">
							<span class="badge badge-warning text-dark px-1.5 py-0.5 font-weight-700" style="font-size: 0.68rem; border-radius: 4px;">
								Próximo a vencer
							</span>
						</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem;">
							Vence: 2026-10-26 (2 días)
						</div>
						<span class="text-primary font-weight-600 text-xs d-inline-flex align-items-center" style="font-size: 0.74rem;">
							Revisar lote <i class="fa-solid fa-arrow-right ml-1"></i>
						</span>
					</div>
				</div>
			</div>

			<!-- 4. Pepino (Otros lotes) -->
			<div class="col">
				<div class="daily-thumb-card" onclick="dailyFilterFefoTableByText('pepino');" title="Ver lote de Pepino">
					<div class="daily-thumb-img-wrap">
						<img src="{{ $U('/img/control_center/pepino.jpg') }}" alt="Pepino" class="daily-thumb-img" loading="lazy">
						<div class="daily-thumb-overlay"></div>
					</div>
					<div class="daily-thumb-content">
						<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.88rem;">Pepino</div>
						<div class="mb-1">
							<span class="badge badge-light border text-muted px-1.5 py-0.5 font-weight-600" style="font-size: 0.68rem; border-radius: 4px;">
								Otros lotes
							</span>
						</div>
						<div class="text-muted text-xs mb-2 flex-grow-1" style="font-size: 0.72rem;">
							Vence: 2026-10-29 (5 días)
						</div>
						<span class="text-primary font-weight-600 text-xs d-inline-flex align-items-center" style="font-size: 0.74rem;">
							Revisar lote <i class="fa-solid fa-arrow-right ml-1"></i>
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Tabla FEFO completa con filtros integrales -->
		<div class="border rounded" style="border-radius: 12px; overflow: hidden;" id="dailyFefoTablesExport">
			<!-- Barra de filtros superiores -->
			<div class="p-2.5 bg-slate-50 border-bottom d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.6rem;">
				<!-- Buscador -->
				<div class="position-relative" style="min-width: 220px; flex-grow: 1; max-width: 340px;">
					<i class="fa-solid fa-magnifying-glass text-muted position-absolute" style="top: 50%; transform: translateY(-50%); left: 9px; font-size: 0.75rem;"></i>
					<input type="text" id="dailyFefoSearch" class="form-control form-control-sm pl-4" placeholder="Buscar producto, SKU o lote..." style="border-radius: 6px; font-size: 0.78rem; height: 30px;" oninput="dailyApplyFefoFilters();">
				</div>

				<div class="d-flex align-items-center flex-wrap" style="gap: 0.4rem;">
					<!-- Filtro Categoría -->
					<select id="dailyFefoCatFilter" class="form-control form-control-sm" style="border-radius: 6px; font-size: 0.78rem; height: 30px; width: auto;" onchange="dailyApplyFefoFilters();">
						<option value="">Todas las categorías</option>
						@php
							$uniqueCats = array_values(array_unique(array_filter(array_map(fn($l) => $l->category_name ?? null, $fefoLotsList))));
						@endphp
						@foreach($uniqueCats as $c)
							<option value="{{ $c }}">{{ $c }}</option>
						@endforeach
					</select>

					<!-- Filtro Estado Sanitario -->
					<select id="dailyFefoSanitaryFilter" class="form-control form-control-sm" style="border-radius: 6px; font-size: 0.78rem; height: 30px; width: auto;" onchange="dailyApplyFefoFilters();">
						<option value="">Todos los estados sanitarios</option>
						<option value="apto">Apto para Venta</option>
						<option value="cuarentena">En cuarentena</option>
						<option value="critico">Crítico / Bloqueo</option>
						<option value="rotacion">Prioridad Rotación</option>
					</select>

					<!-- Filtro Ubicación -->
					<select id="dailyFefoLocFilter" class="form-control form-control-sm" style="border-radius: 6px; font-size: 0.78rem; height: 30px; width: auto;" onchange="dailyApplyFefoFilters();">
						<option value="">Todas las ubicaciones</option>
						@php
							$uniqueFefoLocs = array_values(array_unique(array_filter(array_map(fn($l) => $l->location_name ?? null, $fefoLotsList))));
						@endphp
						@foreach($uniqueFefoLocs as $fLoc)
							<option value="{{ $fLoc }}">{{ $fLoc }}</option>
						@endforeach
					</select>
				</div>
			</div>

			<!-- Tabla de registros FEFO -->
			<div class="table-responsive">
				<table class="daily-compact-table" id="dailyFefoTable" data-export-title="Rotación FEFO y Vencimientos">
					<thead>
						<tr>
							<th>Producto</th>
							<th>SKU</th>
							<th>Lote</th>
							<th>Vence</th>
							<th class="text-right">Días</th>
							<th class="text-right">Stock</th>
							<th>Ubicación</th>
							<th>Prioridad FEFO</th>
							<th>Estado sanitario</th>
							<th class="text-right" style="width: 110px;">Acción</th>
						</tr>
					</thead>
					<tbody id="dailyFefoTableBody">
						@forelse($fefoLotsList as $lot)
							@php
								$dLeft = intval($lot->days_left);
								$isQuarantined = !empty($lot->is_quarantined);

								// Prioridad FEFO
								if ($dLeft < 0) {
									$fefoPrioBadge = '<span class="badge badge-danger font-weight-600 px-1.5 py-0.5" style="font-size:0.71rem;">Vencido</span>';
									$prioKey = 'vencido';
								} elseif ($dLeft === 0) {
									$fefoPrioBadge = '<span class="badge badge-danger font-weight-700 px-1.5 py-0.5" style="font-size:0.71rem;">Vence hoy</span>';
									$prioKey = 'vence_hoy';
								} elseif ($dLeft <= 5) {
									$fefoPrioBadge = '<span class="badge badge-warning text-dark font-weight-700 px-1.5 py-0.5" style="font-size:0.71rem;">Próximo a vencer</span>';
									$prioKey = 'proximo';
								} else {
									$fefoPrioBadge = '<span class="badge badge-light border text-muted font-weight-500 px-1.5 py-0.5" style="font-size:0.71rem;">Otros lotes</span>';
									$prioKey = 'otros';
								}

								// Estado sanitario
								if ($isQuarantined) {
									$sanitaryBadge = '<span class="badge badge-warning text-dark font-weight-600 px-2 py-0.5" style="font-size:0.71rem;"><i class="fa-solid fa-lock mr-1"></i>En cuarentena</span>';
									$sanitaryKey = 'cuarentena';
								} elseif ($dLeft < 0) {
									$sanitaryBadge = '<span class="badge badge-danger font-weight-600 px-2 py-0.5" style="font-size:0.71rem;">No apto (Vencido)</span>';
									$sanitaryKey = 'critico';
								} elseif ($dLeft <= 2) {
									$sanitaryBadge = '<span class="badge badge-danger font-weight-600 px-2 py-0.5" style="font-size:0.71rem;">Crítico / Bloqueo</span>';
									$sanitaryKey = 'critico';
								} elseif ($dLeft <= 5) {
									$sanitaryBadge = '<span class="badge badge-light border text-slate-700 font-weight-600 px-2 py-0.5" style="font-size:0.71rem;">Prioridad Rotación</span>';
									$sanitaryKey = 'rotacion';
								} else {
									$sanitaryBadge = '<span class="badge badge-success font-weight-600 px-2 py-0.5" style="font-size:0.71rem;">Apto para Venta</span>';
									$sanitaryKey = 'apto';
								}

								$searchTxt = strtolower($lot->product_name . ' ' . $lot->product_sku . ' ' . ($lot->lot_number ?? 'L-DEFAULT') . ' ' . ($lot->category_name ?? '') . ' ' . $lot->location_name);
							@endphp
							<tr class="daily-fefo-row" data-search-text="{{ $searchTxt }}" data-category="{{ $lot->category_name ?? '' }}" data-sanitary="{{ $sanitaryKey }}" data-location="{{ $lot->location_name }}">
								<td>
									<div class="font-weight-600 text-dark">{{ $lot->product_name }}</div>
								</td>
								<td class="font-mono text-muted small" style="font-size: 0.76rem;">
									{{ $lot->product_sku }}
								</td>
								<td class="font-mono font-weight-600 text-dark small" style="font-size: 0.76rem;">
									{{ $lot->lot_number ?? 'L-DEFAULT' }}
								</td>
								<td class="font-mono text-slate-700 small" style="font-size: 0.78rem;">
									{{ $lot->best_before_date }}
								</td>
								<td class="text-right font-weight-700 {{ $dLeft <= 2 ? 'text-danger' : ($dLeft <= 5 ? 'text-amber-700' : 'text-slate-700') }}">
									{{ $dLeft }}
								</td>
								<td class="text-right font-weight-700 text-dark">
									{{ floatval($lot->amount) }}
								</td>
								<td class="text-slate-600 small" style="font-size: 0.78rem;">
									{{ $lot->location_name }}
								</td>
								<td>
									{!! $fefoPrioBadge !!}
								</td>
								<td>
									{!! $sanitaryBadge !!}
								</td>
								<td class="text-right text-nowrap">
									<button type="button" class="btn btn-xs btn-outline-danger font-weight-600 px-2 py-1" onclick="dailyOpenLotDetailModal({{ json_encode($lot) }});" style="font-size: 0.74rem;">
										Revisar lote
									</button>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="10" class="text-center py-4 text-muted small">
									No se registran lotes en stock para la selección.
								</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<!-- Paginación FEFO -->
			<div class="p-2.5 bg-white border-top d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.5rem;">
				<div class="small text-muted" style="font-size: 0.76rem;" id="dailyFefoPaginationLabel">
					Mostrando 1 a {{ min(4, $totalFefoLots) }} de {{ $totalFefoLots }} registros
				</div>
				<div class="d-flex align-items-center" style="gap: 0.4rem;">
					<ul class="pagination pagination-sm mb-0" id="dailyFefoPagination">
						<!-- Generada por JS -->
					</ul>
					<button type="button" class="btn btn-xs btn-link text-primary font-weight-600 p-0 ml-2" onclick="dailyShowAllFefoRows();" style="font-size: 0.74rem;">
						Ver todos los {{ $totalFefoLots }} registros <i class="fa-solid fa-arrow-right ml-0.5"></i>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================================================================
	     SECCIÓN F: FLUJO DE CONTROL DE FRÍO Y VENCIMIENTOS (5 PASOS)
	     ====================================================================== -->
	<div class="daily-card p-3 p-md-4 mb-4">
		<div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap: 0.5rem;">
			<div class="d-flex align-items-center" style="gap: 0.45rem;">
				<i class="fa-solid fa-route text-success"></i>
				<span class="font-weight-bold text-dark" style="font-size: 0.92rem;">
					Flujo de control de Frío y Vencimientos
				</span>
				<i class="fa-solid fa-circle-info text-slate-400" title="Guía de acción ante alertas de temperatura y rotación FEFO obligatoria"></i>
			</div>
			<div class="small text-muted font-weight-500" style="font-size: 0.76rem;">
				<i class="fa-solid fa-shield-halved text-success mr-1"></i>
				Protocolo estándar de calidad e inocuidad alimentaria
			</div>
		</div>

		<div class="daily-stepper-container" style="overflow-x: auto; padding: 0.5rem 0.25rem 0.25rem 0.25rem;">
			<div class="daily-stepper-5">
				<div class="daily-step-item-5">
					<div class="daily-step-circle-5">1</div>
					<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.8rem;">Detectar</div>
					<div class="text-muted text-xs" style="font-size: 0.69rem; line-height: 1.25;">Monitoreo de temperatura y revisión de vencimientos</div>
				</div>
				<div class="daily-step-item-5">
					<div class="daily-step-circle-5">2</div>
					<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.8rem;">Contener</div>
					<div class="text-muted text-xs" style="font-size: 0.69rem; line-height: 1.25;">Cuarentena preventiva de productos expuestos</div>
				</div>
				<div class="daily-step-item-5">
					<div class="daily-step-circle-5">3</div>
					<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.8rem;">Evaluar</div>
					<div class="text-muted text-xs" style="font-size: 0.69rem; line-height: 1.25;">Análisis de calidad y trazabilidad</div>
				</div>
				<div class="daily-step-item-5">
					<div class="daily-step-circle-5">4</div>
					<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.8rem;">Autorizar</div>
					<div class="text-muted text-xs" style="font-size: 0.69rem; line-height: 1.25;">Decisión técnica de liberación o descarte</div>
				</div>
				<div class="daily-step-item-5">
					<div class="daily-step-circle-5">5</div>
					<div class="font-weight-bold text-dark small mb-0.5" style="font-size: 0.8rem;">Verificar</div>
					<div class="text-muted text-xs" style="font-size: 0.69rem; line-height: 1.25;">Registro y cierre en el sistema</div>
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

		<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-5 g-2">
			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/waste#tab-fefo') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.65rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-snowflake"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Calidad & Vencimientos</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Rebajas por vencimiento</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>

			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/workforce') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.65rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-list-check"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Tareas y Roles</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Mermas desde Piso de Tienda</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>

			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/executive-dashboard') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.65rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-chart-line"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Panel Ejecutivo</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Frío y vencimientos</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>

			<div class="col mb-2 mb-lg-0">
				<a href="{{ $U('/cost-centers') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.65rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-building-columns"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Centros de Costo</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Merma por centro de costo</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>

			<div class="col">
				<a href="{{ $U('/reports-hub') }}" class="daily-conn-card">
					<div class="d-flex align-items-center" style="gap: 0.65rem;">
						<div style="color: #00C83B; font-size: 1.15rem;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
						<div>
							<div class="font-weight-bold text-dark small">Reportes & Analítica</div>
							<div class="text-muted text-xs" style="font-size: 0.72rem;">Desglose de merma (consolidado)</div>
						</div>
					</div>
					<i class="fa-solid fa-arrow-right text-slate-400 small"></i>
				</a>
			</div>
		</div>
	</div>

</div>

<!-- ==========================================================================
     MODAL INTERACTIVO DE DETALLE Y ACCIONES DE LOTE
     ========================================================================== -->
<div class="modal fade" id="modalDailyLotDetail" tabindex="-1" role="dialog" aria-labelledby="modalDailyLotDetailLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
			<div class="modal-header bg-white border-bottom py-3">
				<h6 class="modal-title font-weight-bold text-dark" id="modalDailyLotDetailLabel">
					<i class="fa-solid fa-box-open text-primary mr-2"></i>Detalle de Lote e Inocuidad
				</h6>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body py-3">
				<div class="d-flex align-items-center mb-3 p-2 bg-slate-50 border rounded" style="gap: 0.75rem;">
					<div style="width: 40px; height: 40px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
						<i class="fa-solid fa-cube"></i>
					</div>
					<div>
						<h6 class="font-weight-bold text-dark mb-0" id="mldProductName">Producto</h6>
						<div class="text-xs text-muted font-mono" id="mldProductSku">SKU: — · Lote: —</div>
					</div>
				</div>

				<div class="row row-cols-2 g-2 mb-3 small">
					<div class="col">
						<div class="text-muted text-xs">Vencimiento</div>
						<div class="font-weight-bold text-dark" id="mldBbd">—</div>
					</div>
					<div class="col">
						<div class="text-muted text-xs">Días restantes</div>
						<div class="font-weight-bold" id="mldDaysLeft">—</div>
					</div>
					<div class="col">
						<div class="text-muted text-xs">Stock disponible</div>
						<div class="font-weight-bold text-dark" id="mldStock">—</div>
					</div>
					<div class="col">
						<div class="text-muted text-xs">Ubicación actual</div>
						<div class="font-weight-bold text-dark" id="mldLocation">—</div>
					</div>
				</div>

				<div class="alert alert-warning py-2 px-3 text-xs mb-3" id="mldExcursionNotice">
					<i class="fa-solid fa-triangle-exclamation mr-1"></i>
					<strong>Producto comprometido:</strong> Este lote estuvo ubicado en una zona sujeta a excursión térmica.
					Se requiere evaluación de calidad antes de liberar para la venta.
				</div>

				<div class="form-group mb-0">
					<label class="small font-weight-700 text-dark mb-1">Motivo / Acción requerida</label>
					<textarea class="form-control form-control-sm" id="mldActionNotes" rows="2" placeholder="Observaciones de inspección o motivo de cuarentena preventiva..." style="border-radius: 6px;"></textarea>
				</div>
			</div>
			<div class="modal-footer bg-white border-top py-2 d-flex justify-content-between">
				<button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cerrar</button>
				<div class="d-flex" style="gap: 0.4rem;">
					@if($canQuarantine)
						<button type="button" class="btn btn-sm btn-danger font-weight-600" id="mldBtnQuarantine" onclick="dailyExecuteLotQuarantine();">
							<i class="fa-solid fa-lock mr-1"></i>Poner en Cuarentena
						</button>
					@endif
					<a href="{{ $U('/waste#tab-fefo') }}" class="btn btn-sm btn-outline-primary font-weight-600">
						<i class="fa-solid fa-microscope mr-1"></i>Ir a Calidad
					</a>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     SCRIPTS DE CONTROL Y PAGINACIÓN
     ========================================================================== -->
<script>
/* ==========================================================================
   VARIABLES GLOBALES DE ESTADO Y PAGINACIÓN
   ========================================================================== */
var currentExposedPage = 1;
var exposedPageSize = 4;
var exposedMatchingRows = [];

var currentFefoPage = 1;
var fefoPageSize = 4;
var fefoMatchingRows = [];

(function() {
	// Inicializar gráfico de lecturas de temperatura por día
	var trendData = {!! json_encode($trend) !!};
	var trendKeys = {!! json_encode($trendKeys) !!};

	if (document.getElementById('lcTrendDaily') && typeof Chart !== 'undefined') {
		var ctx = document.getElementById('lcTrendDaily').getContext('2d');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: trendKeys,
				datasets: [
					{
						label: 'Registros fuera de rango',
						data: trendKeys.map(function(k) { return trendData[k].out; }),
						backgroundColor: '#C8553D',
						borderWidth: 0
					},
					{
						label: 'En rango',
						data: trendKeys.map(function(k) { return trendData[k].readings - trendData[k].out; }),
						backgroundColor: 'rgba(107,143,113,.35)',
						borderWidth: 0
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: {
					mode: 'index',
					intersect: false,
					callbacks: {
						label: function(item, data) {
							return data.datasets[item.datasetIndex].label + ': ' + item.yLabel + ' lecturas';
						}
					}
				},
				scales: {
					xAxes: [{
						stacked: true,
						gridLines: { display: false },
						ticks: { fontSize: 10, fontColor: '#64748b' }
					}],
					yAxes: [{
						stacked: true,
						ticks: {
							beginAtZero: true,
							stepSize: 2,
							fontSize: 10,
							fontColor: '#64748b',
							callback: function(v) { return v + ' lecturas'; }
						},
						gridLines: { color: 'rgba(0,0,0,.05)' }
					}]
				}
			}
		});
	}
})();

/* --- Paginación y Filtrado de Lotes Expuestos --- */
function dailyApplyExposedFilters() {
	var search = ($('#dailyExposedSearch').val() || '').toLowerCase().trim();
	var loc = ($('#dailyExposedLocationFilter').val() || '').trim();
	var prodCat = ($('#dailyExposedProductFilter').val() || '').toLowerCase().trim();

	exposedMatchingRows = [];
	$('.daily-exposed-row').each(function() {
		var row = $(this);
		var text = row.data('search-text') || '';
		var rowLoc = row.data('location') || '';
		var rowProd = row.data('product') || '';

		var matchesSearch = !search || text.indexOf(search) !== -1;
		var matchesLoc = !loc || rowLoc === loc;
		var matchesProd = !prodCat || rowProd.indexOf(prodCat) !== -1;

		if (matchesSearch && matchesLoc && matchesProd) {
			exposedMatchingRows.push(row);
		} else {
			row.hide();
		}
	});

	$('#dailyExposedTotalCount').text(exposedMatchingRows.length);
	currentExposedPage = 1;
	dailyRenderExposedPage();
}

function dailyFilterExposedByCategory(cat) {
	$('#dailyExposedProductFilter').val(cat);
	dailyApplyExposedFilters();
	// Scroll suave hacia la tabla
	var target = document.getElementById('dailyExposedTableWrap');
	if (target) {
		target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
}

function dailyFilterExposedByLocation(loc) {
	$('#dailyExposedLocationFilter').val(loc);
	dailyApplyExposedFilters();
	var target = document.getElementById('dailyExposedTableWrap');
	if (target) {
		target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
}

function dailyInitExposedPagination() {
	dailyApplyExposedFilters();
}

function dailyRenderExposedPage() {
	var total = exposedMatchingRows.length;
	var totalPages = Math.ceil(total / exposedPageSize) || 1;
	if (currentExposedPage > totalPages) currentExposedPage = totalPages;
	if (currentExposedPage < 1) currentExposedPage = 1;

	var startIdx = (currentExposedPage - 1) * exposedPageSize;
	var endIdx = startIdx + exposedPageSize;

	exposedMatchingRows.forEach(function(row, idx) {
		if (idx >= startIdx && idx < endIdx) {
			row.show();
		} else {
			row.hide();
		}
	});

	var showingStart = total === 0 ? 0 : startIdx + 1;
	var showingEnd = Math.min(endIdx, total);
	$('#dailyExposedPaginationLabel').text('Mostrando ' + showingStart + ' a ' + showingEnd + ' de ' + total + ' registros');

	var pagUl = $('#dailyExposedPagination');
	pagUl.empty();

	if (totalPages > 1) {
		pagUl.append('<li class="page-item ' + (currentExposedPage === 1 ? 'disabled' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetExposedPage(' + (currentExposedPage - 1) + ');">&laquo;</a></li>');
		for (var p = 1; p <= totalPages; p++) {
			if (p === 1 || p === totalPages || (p >= currentExposedPage - 1 && p <= currentExposedPage + 1)) {
				pagUl.append('<li class="page-item ' + (currentExposedPage === p ? 'active' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetExposedPage(' + p + ');">' + p + '</a></li>');
			} else if (p === currentExposedPage - 2 || p === currentExposedPage + 2) {
				pagUl.append('<li class="page-item disabled"><span class="page-link py-1 px-2">…</span></li>');
			}
		}
		pagUl.append('<li class="page-item ' + (currentExposedPage === totalPages ? 'disabled' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetExposedPage(' + (currentExposedPage + 1) + ');">&raquo;</a></li>');
	}
}

function dailySetExposedPage(p) {
	currentExposedPage = p;
	dailyRenderExposedPage();
}

function dailyShowAllExposedRows() {
	exposedPageSize = 9999;
	currentExposedPage = 1;
	dailyRenderExposedPage();
}

/* --- Paginación y Filtrado de Tabla FEFO --- */
function dailyApplyFefoFilters() {
	var search = ($('#dailyFefoSearch').val() || '').toLowerCase().trim();
	var cat = ($('#dailyFefoCatFilter').val() || '').trim();
	var sanitary = ($('#dailyFefoSanitaryFilter').val() || '').trim();
	var loc = ($('#dailyFefoLocFilter').val() || '').trim();

	fefoMatchingRows = [];
	$('.daily-fefo-row').each(function() {
		var row = $(this);
		var text = row.data('search-text') || '';
		var rowCat = row.data('category') || '';
		var rowSanitary = row.data('sanitary') || '';
		var rowLoc = row.data('location') || '';

		var matchesSearch = !search || text.indexOf(search) !== -1;
		var matchesCat = !cat || rowCat === cat;
		var matchesSanitary = !sanitary || rowSanitary === sanitary;
		var matchesLoc = !loc || rowLoc === loc;

		if (matchesSearch && matchesCat && matchesSanitary && matchesLoc) {
			fefoMatchingRows.push(row);
		} else {
			row.hide();
		}
	});

	currentFefoPage = 1;
	dailyRenderFefoPage();
}

function dailyFilterFefoTableByText(txt) {
	$('#dailyFefoSearch').val(txt);
	dailyApplyFefoFilters();
	var target = document.getElementById('dailyFefoTablesExport');
	if (target) {
		target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
}

var fefoUrlApplied = false;
function dailyInitFefoPagination() {
	// Enlaces desde HOY, Agenda y Alertas: ?fefo_q=<producto o lote>&fefo_estado=<cuarentena|rotacion|critico>#tab-fefo
	var p = new URLSearchParams(window.location.search), deep = !fefoUrlApplied && (p.get('fefo_q') || p.get('fefo_estado'));
	if (deep) {
		fefoUrlApplied = true;
		if (p.get('fefo_q')) $('#dailyFefoSearch').val(p.get('fefo_q'));
		if (p.get('fefo_estado')) $('#dailyFefoSanitaryFilter').val(p.get('fefo_estado'));
	}
	dailyApplyFefoFilters();
	if (deep) {
		var t = document.getElementById('dailyFefoTablesExport');
		if (t) t.scrollIntoView({ block: 'start' });
	}
}

function dailyRenderFefoPage() {
	var total = fefoMatchingRows.length;
	var totalPages = Math.ceil(total / fefoPageSize) || 1;
	if (currentFefoPage > totalPages) currentFefoPage = totalPages;
	if (currentFefoPage < 1) currentFefoPage = 1;

	var startIdx = (currentFefoPage - 1) * fefoPageSize;
	var endIdx = startIdx + fefoPageSize;

	fefoMatchingRows.forEach(function(row, idx) {
		if (idx >= startIdx && idx < endIdx) {
			row.show();
		} else {
			row.hide();
		}
	});

	var showingStart = total === 0 ? 0 : startIdx + 1;
	var showingEnd = Math.min(endIdx, total);
	$('#dailyFefoPaginationLabel').text('Mostrando ' + showingStart + ' a ' + showingEnd + ' de ' + total + ' registros');

	var pagUl = $('#dailyFefoPagination');
	pagUl.empty();

	if (totalPages > 1) {
		pagUl.append('<li class="page-item ' + (currentFefoPage === 1 ? 'disabled' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetFefoPage(' + (currentFefoPage - 1) + ');">&laquo;</a></li>');
		for (var p = 1; p <= totalPages; p++) {
			if (p === 1 || p === totalPages || (p >= currentFefoPage - 1 && p <= currentFefoPage + 1)) {
				pagUl.append('<li class="page-item ' + (currentFefoPage === p ? 'active' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetFefoPage(' + p + ');">' + p + '</a></li>');
			} else if (p === currentFefoPage - 2 || p === currentFefoPage + 2) {
				pagUl.append('<li class="page-item disabled"><span class="page-link py-1 px-2">…</span></li>');
			}
		}
		pagUl.append('<li class="page-item ' + (currentFefoPage === totalPages ? 'disabled' : '') + '"><a class="page-link py-1 px-2" href="javascript:void(0)" onclick="dailySetFefoPage(' + (currentFefoPage + 1) + ');">&raquo;</a></li>');
	}
}

function dailySetFefoPage(p) {
	currentFefoPage = p;
	dailyRenderFefoPage();
}

function dailyShowAllFefoRows() {
	fefoPageSize = 9999;
	currentFefoPage = 1;
	dailyRenderFefoPage();
}

/* --- Modal Interactivo de Lote --- */
var currentInspectedLot = null;

function dailyOpenLotDetailModal(lot) {
	currentInspectedLot = lot;
	$('#mldProductName').text(lot.product_name || lot.name || 'Producto');
	$('#mldProductSku').text('SKU: ' + (lot.product_sku || lot.sku || '—') + ' · Stock ID: ' + (lot.stock_id || '—'));
	$('#mldBbd').text(lot.best_before_date || 'Sin fecha');

	var days = lot.days_left !== undefined ? lot.days_left : '—';
	$('#mldDaysLeft').text(days + (days !== '—' ? ' días' : '')).css('color', days <= 2 ? '#dc2626' : (days <= 5 ? '#b45309' : '#1e293b'));
	$('#mldStock').text((lot.amount || 0) + ' ' + (lot.qu_name || 'u.'));
	$('#mldLocation').text(lot.location_name || lot.excursion_loc || 'Sin ubicación');

	if (lot.excursion_loc || lot.value_at_risk) {
		$('#mldExcursionNotice').show();
	} else {
		$('#mldExcursionNotice').hide();
	}

	$('#mldActionNotes').val('');
	$('#modalDailyLotDetail').modal('show');
}

function dailyExecuteLotQuarantine() {
	if (!currentInspectedLot) return;
	var reason = ($('#mldActionNotes').val() || '').trim();
	if (!reason) {
		reason = 'Riesgo de inocuidad o excursión de temperatura detectada';
	}

	var lot = currentInspectedLot;
	$('#modalDailyLotDetail').modal('hide');

	DairyLoss.post('quality/quarantine', {
		product_id: lot.product_id,
		stock_id: lot.stock_id,
		amount: lot.amount,
		location_id: lot.location_id || 8,
		branch_id: lot.branch_id || (window.DairyMaster ? DairyMaster.branchId : 1),
		reason: reason
	}, function() {
		toastr.success('Lote bloqueado en cuarentena para evaluación');
		if (typeof dairyRefreshActiveTab === 'function') {
			dairyRefreshActiveTab();
		} else {
			location.reload();
		}
	});
}

function dailyOpenQuickQuarantineModal() {
	bootbox.prompt({
		title: 'Bloquear lote en cuarentena: motivo',
		value: 'Excursión térmica preventiva',
		callback: function(reason) {
			if (reason === null) return;
			toastr.info('Seleccione el lote específico en la tabla para confirmar el bloqueo');
			var target = document.getElementById('dailyFefoTablesExport');
			if (target) {
				target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}
	});
}

// Inicializar paginadores de tablas expuesta y FEFO
$(document).ready(function() {
	dailyInitExposedPagination();
	dailyInitFefoPagination();
});

// En caso de que se cargue dinámicamente vía AJAX en la pestaña #tab-fefo
setTimeout(function() {
	dailyInitExposedPagination();
	dailyInitFefoPagination();
}, 50);

$(document).on('shown.bs.tab', 'a[href="#tab-fefo"], #tab-fefo-link', function() {
	dailyInitExposedPagination();
	dailyInitFefoPagination();
});
</script>
