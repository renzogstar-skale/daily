{{-- DAILY: Pestaña 10 - Horas Peak y Dotación Inteligente --}}
@php
	$pkData = $peakDashboardData ?? [];
	$kpis = $pkData['kpis'] ?? [];
	$heatmap = $pkData['heatmap'] ?? [];
	$detectedPeaks = $pkData['detected_peaks'] ?? [];
	$areaStandards = $pkData['area_standards'] ?? [];
	$simulations = $pkData['simulations'] ?? [];
	$forecasts = $pkData['forecasts'] ?? [];
	$events = $pkData['commercial_events'] ?? [];
	$recs = $pkData['recommendations'] ?? [];

	$money = fn($v) => $v === null ? 'Sin información' : '$' . number_format(floatval($v), 2);
	$dayHeaders = ['Lun 02/11', 'Mar 03/11', 'Mié 04/11', 'Jue 05/11', 'Vie 06/11', 'Sáb 07/11', 'Dom 08/11'];
@endphp

<div id="schPeaksSection" style="display: none;">

	<!-- 1. ENCABEZADO Y FILTRO DE HORAS PEAK -->
	<div class="card border-0 shadow-sm mb-3 bg-white" style="border-radius: 12px; border: 1px solid #e2e8f0;">
		<div class="card-body p-3">
			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
				<div class="d-flex align-items-center">
					<div class="mr-3" style="width: 46px; height: 46px; border-radius: 12px; background: linear-gradient(135deg, #ef4444, #f97316); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);">
						<i class="fa-solid fa-fire"></i>
					</div>
					<div>
						<div class="d-flex align-items-center">
							<h4 class="font-weight-bold text-dark mb-0 mr-2" style="letter-spacing: -0.02em;">Horas Peak y Dotación Inteligente</h4>
							<span class="badge badge-danger text-uppercase px-2 py-0.5" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em;">Motor Activo</span>
						</div>
						<p class="text-muted small mb-0 mt-0.5">
							Predicción de demanda laboral calculada por carga de trabajo: <code>Personal = ⌈Minutos de Trabajo / Minutos Productivos⌉</code> según ventas POS, transacciones, reposición y eventos comerciales.
						</p>
					</div>
				</div>

				<div class="d-flex align-items-center flex-wrap gap-2">
					<div class="btn-group mr-2" role="group">
						<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold active" id="btnInterval60" onclick="window.setPeakInterval(60, this)">60 min</button>
						<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" id="btnInterval30" onclick="window.setPeakInterval(30, this)">30 min</button>
						<button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" id="btnInterval15" onclick="window.setPeakInterval(15, this)">15 min</button>
					</div>
					<button type="button" class="btn btn-sm btn-outline-dark font-weight-bold mr-2" onclick="$('#simuladorSectionCard')[0].scrollIntoView({behavior: 'smooth'})">
						<i class="fa-solid fa-sliders mr-1 text-primary"></i>Simulador de Ventas
					</button>
					<button type="button" class="btn btn-sm btn-danger font-weight-bold shadow-xs" onclick="$('#blackFridaySectionCard')[0].scrollIntoView({behavior: 'smooth'})">
						<i class="fa-solid fa-tag mr-1"></i>Black Friday 2026
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- 2. TARJETAS KPIS SUPERIORES (5 INDICADORES CLAVE) -->
	<div class="row g-2 mb-3">
		<div class="col-xl col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white h-100" style="border-radius: 10px; border-left: 4px solid #0284c7 !important;">
				<span class="text-muted font-weight-bold text-uppercase" style="font-size: 0.68rem;">Ventas Proyectadas Semana</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ $money($kpis['total_week_sales'] ?? null) }}</h4>
				<span class="small text-muted">{{ isset($kpis['total_week_tickets']) ? number_format($kpis['total_week_tickets']) : '—' }} tickets · {{ $money($kpis['avg_ticket'] ?? null) }} prom.</span>
			</div>
		</div>
		<div class="col-xl col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white h-100" style="border-radius: 10px; border-left: 4px solid #ef4444 !important;">
				<span class="text-muted font-weight-bold text-uppercase" style="font-size: 0.68rem;">Horas Peak Detectadas</span>
				<h4 class="font-weight-bold text-danger mb-0 mt-1">{{ $kpis['peak_hours_count'] ?? '—' }} franjas</h4>
				<span class="small text-danger font-weight-bold"><i class="fa-solid fa-arrow-trend-up mr-1"></i>Concentran el 42% del volumen</span>
			</div>
		</div>
		<div class="col-xl col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white h-100" style="border-radius: 10px; border-left: 4px solid #10b981 !important;">
				<span class="text-muted font-weight-bold text-uppercase" style="font-size: 0.68rem;">Venta por Hora Laboral (SPLH)</span>
				<h4 class="font-weight-bold text-success mb-0 mt-1">{{ isset($kpis['splh_store']) ? '$' . number_format($kpis['splh_store'], 1) : 'Sin información' }} /h</h4>
				<span class="small text-success font-weight-bold"><i class="fa-solid fa-check mr-1"></i>Sobre meta ($160.0/h)</span>
			</div>
		</div>
		<div class="col-xl col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white h-100" style="border-radius: 10px; border-left: 4px solid #6366f1 !important;">
				<span class="text-muted font-weight-bold text-uppercase" style="font-size: 0.68rem;">Costo Laboral % sobre Ventas</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ $kpis['labor_cost_pct'] ?? '—' }}%</h4>
				<span class="small text-muted">Presupuesto máx: 8.5% (-0.4 pp)</span>
			</div>
		</div>
		<div class="col-xl col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white h-100" style="border-radius: 10px; border-left: 4px solid #f59e0b !important;">
				<span class="text-muted font-weight-bold text-uppercase" style="font-size: 0.68rem;">Alertas de Cobertura</span>
				<h4 class="font-weight-bold text-warning mb-0 mt-1">{{ $kpis['undercoverage_hours_count'] ?? '—' }} déficit</h4>
				<span class="small text-dark font-weight-600">Resolución con ajustes</span>
			</div>
		</div>
	</div>

	<!-- 3. MAPA DE CALOR SEMANAL DE HORAS PEAK (DÍAS EN COLUMNAS, HORAS EN FILAS) -->
	<div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 12px; border: 1px solid #e2e8f0;">
		<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-table-cells mr-2 text-danger"></i>Mapa de Calor Semanal de Demanda y Horas Peak (06:00 a 22:00)
				</h6>
				<span class="text-muted small">Descubrimiento automático de franjas críticas por afluencia de clientes y transacciones por hora.</span>
			</div>
			<div class="d-flex align-items-center flex-wrap gap-2 font-weight-bold" style="font-size: 0.76rem;">
				<span class="d-flex align-items-center mr-2"><span class="badge mr-1" style="width: 12px; height: 12px; background: #ecfdf5; border: 1px solid #a7f3d0;"></span> Baja (&lt; P30)</span>
				<span class="d-flex align-items-center mr-2"><span class="badge mr-1" style="width: 12px; height: 12px; background: #f0f9ff; border: 1px solid #bae6fd;"></span> Normal (P30-P70)</span>
				<span class="d-flex align-items-center mr-2"><span class="badge mr-1" style="width: 12px; height: 12px; background: #fef3c7; border: 1px solid #fde68a;"></span> Alta (P70-P90)</span>
				<span class="d-flex align-items-center"><span class="badge mr-1" style="width: 12px; height: 12px; background: #fee2e2; border: 1px solid #fca5a5;"></span> <strong>Peak (P90+)</strong></span>
			</div>
		</div>
		<div class="card-body p-2">
			<!-- Picos detectados resumen rápido -->
			<div class="row g-2 mb-2">
				@foreach($detectedPeaks as $dayName => $pk)
					<div class="col-md-4 col-12 mb-1">
						<div class="p-2 rounded bg-light border d-flex justify-content-between align-items-center" style="font-size: 0.8rem;">
							<div>
								<strong class="text-danger">{{ $dayName }}:</strong> <span class="badge badge-danger ml-1 font-weight-bold">{{ $pk['band'] }}</span>
								<div class="text-muted text-xs">{{ $pk['reason'] }}</div>
							</div>
							<div class="text-right">
								<span class="font-weight-bold text-dark">{{ $pk['staff'] }} colab.</span>
							</div>
						</div>
					</div>
				@endforeach
			</div>

			<div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
				<table class="table table-bordered table-sm text-center mb-0 align-middle" id="tablePeakHeatmap" style="font-size: 0.78rem;">
					<thead class="bg-light text-muted uppercase sticky-top" style="font-size: 0.72rem; z-index: 5;">
						<tr>
							<th style="width: 75px;" class="align-middle">Hora</th>
							@foreach($dayHeaders as $dh)
								<th class="align-middle py-2">{{ $dh }}</th>
							@endforeach
						</tr>
					</thead>
					<tbody>
						@for($h = 6; $h <= 22; $h++)
							@php
								$hLabel = sprintf('%02d:00', $h) . ' – ' . sprintf('%02d:00', $h + 1);
							@endphp
							<tr>
								<td class="bg-light font-weight-bold text-dark py-2 align-middle" style="font-size: 0.74rem;">{{ $hLabel }}</td>
								@for($d = 0; $d < 7; $d++)
									@php
										$cell = $heatmap[$h][$d] ?? ['sales' => 0, 'traffic' => 0, 'staff_required' => 1, 'staff_scheduled' => 1, 'gap' => 0, 'demand_level' => 'normal'];
										$lvl = $cell['demand_level'];
										$gap = $cell['gap'];
										
										$bgStyle = 'background-color: #f8fafc; color: #64748b;';
										$badgeClass = 'badge-secondary';
										if ($lvl === 'peak') {
											$bgStyle = 'background-color: #fee2e2; color: #991b1b; border: 1.5px solid #ef4444 !important; font-weight: bold;';
											$badgeClass = 'badge-danger';
										} elseif ($lvl === 'high') {
											$bgStyle = 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;';
											$badgeClass = 'badge-warning text-dark';
										} elseif ($lvl === 'normal') {
											$bgStyle = 'background-color: #f0f9ff; color: #0369a1;';
											$badgeClass = 'badge-info';
										} else {
											$bgStyle = 'background-color: #ecfdf5; color: #065f46;';
											$badgeClass = 'badge-success';
										}
									@endphp
									<td style="{{ $bgStyle }} padding: 5px;" class="position-relative">
										<div class="d-flex justify-content-between align-items-center mb-0.5">
											<span class="badge {{ $badgeClass }}" style="font-size: 0.68rem;">{{ strtoupper($lvl) }}</span>
											<span class="font-weight-bold" style="font-size: 0.78rem;">
												{{ $cell['staff_scheduled'] }}/{{ $cell['staff_required'] }} <i class="fa-solid fa-user-group ml-0.5 text-xs"></i>
											</span>
										</div>
										<div class="d-flex justify-content-between small" style="font-size: 0.70rem;">
											<span>${{ number_format($cell['sales'], 0) }}</span>
											<span>{{ $cell['traffic'] }} cl.</span>
										</div>
										@if($gap < 0)
											<div class="text-danger font-weight-bold text-xs mt-0.5">
												<i class="fa-solid fa-circle-exclamation mr-0.5"></i>Déficit {{ abs($gap) }}
											</div>
										@endif
									</td>
								@endfor
							</tr>
						@endfor
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- 4. DISTRIBUCIÓN POR ÁREA Y MODELO DE CARGA DE TRABAJO -->
	<div class="row mb-3">
		<!-- Distribución por Área -->
		<div class="col-xl-8 col-lg-7 col-12 mb-3 mb-xl-0">
			<div class="card border-0 shadow-xs bg-white h-100" style="border-radius: 12px; border: 1px solid #e2e8f0;">
				<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
					<h6 class="font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-layer-group mr-2 text-primary"></i>Dotación y Productividad por Área (Cálculo según Carga Operativa)
					</h6>
					<span class="badge badge-light border text-muted small">10 Áreas Especializadas</span>
				</div>
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.82rem;">
						<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem;">
							<tr>
								<th>Área</th>
								<th class="text-center">Cuota Venta</th>
								<th class="text-right">Meta SPLH</th>
								<th class="text-center">Atención (min/ticket)</th>
								<th class="text-center">Reposición (min/un)</th>
								<th class="text-center">Despacho (min/ped)</th>
								<th class="text-center">Mín. Apertura</th>
								<th class="text-center text-danger font-weight-bold">Peak Req.</th>
							</tr>
						</thead>
						<tbody>
							@forelse($areaStandards as $std)
								<tr>
									<td class="font-weight-bold text-dark">
										<i class="fa-solid fa-circle-dot mr-1 text-xs text-primary"></i>{{ $std['area_name'] }}
									</td>
									<td class="text-center font-weight-600">{{ $std['sales_share_pct'] }}%</td>
									<td class="text-right font-weight-bold text-success">${{ number_format($std['target_splh'], 1) }} /h</td>
									<td class="text-center">{{ $std['service_time_min_per_ticket'] }} min</td>
									<td class="text-center">{{ $std['restock_min_per_unit'] }} min</td>
									<td class="text-center">{{ $std['dispatch_min_per_order'] }} min</td>
									<td class="text-center"><span class="badge badge-light border">{{ $std['min_staff_open'] }} colab.</span></td>
									<td class="text-center"><span class="badge badge-danger font-weight-bold px-2 py-1" style="font-size: 0.8rem;">{{ $std['min_staff_peak'] }} colab.</span></td>
								</tr>
							@empty
								<tr>
									<td colspan="8" class="text-center py-3 text-muted">Cargando estándares de productividad por área...</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Alertas de Cobertura y Desviación -->
		<div class="col-xl-4 col-lg-5 col-12">
			<div class="card border-0 shadow-xs bg-white h-100" style="border-radius: 12px; border: 1px solid #e2e8f0;">
				<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
					<h6 class="font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-bell mr-2 text-warning"></i>Alertas de Brecha de Cobertura
					</h6>
					<span class="badge badge-warning text-dark font-weight-bold">2 Activas</span>
				</div>
				<div class="card-body p-3">
					<div class="alert alert-danger border mb-2.5 p-2.5" style="border-radius: 8px;">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<strong class="text-danger small"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Viernes 06/11 · 18:00–19:00</strong>
								<div class="font-weight-bold text-dark small mt-0.5">Déficit en Ventas y Cajas: -2 colaboradores</div>
								<div class="text-muted text-xs">Venta proyectada $7,020 · 265 clientes · Demanda 12, programados 10.</div>
							</div>
							<button class="btn btn-xs btn-danger font-weight-bold ml-2" onclick="window.applyPeakRec(1, this)">
								<i class="fa-solid fa-plus mr-0.5"></i>Reforzar
							</button>
						</div>
					</div>

					<div class="alert alert-warning border mb-3 p-2.5" style="border-radius: 8px;">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<strong class="text-warning small"><i class="fa-solid fa-clock mr-1"></i>Sábado 07/11 · 13:00–14:00</strong>
								<div class="font-weight-bold text-dark small mt-0.5">Descalce en Despachos: -1 colaborador</div>
								<div class="text-muted text-xs">32 pedidos Click & Collect en cola · Escalonar descanso de picking.</div>
							</div>
							<button class="btn btn-xs btn-warning text-dark font-weight-bold ml-2" onclick="window.applyPeakRec(2, this)">
								<i class="fa-solid fa-arrows-rotate mr-0.5"></i>Escalonar
							</button>
						</div>
					</div>

					<h6 class="font-weight-bold text-dark small mb-2"><i class="fa-solid fa-lightbulb text-primary mr-1"></i>Alternativas sin Romper Rotación</h6>
					<ul class="list-unstyled small text-muted mb-0 pl-1">
						<li class="mb-1"><i class="fa-solid fa-check text-success mr-1"></i>Reasignar 1 colaborador de Virtual Teams con certificación de Ventas.</li>
						<li class="mb-1"><i class="fa-solid fa-check text-success mr-1"></i>Desplazar turno 09:00–17:00 a 11:00–19:00 previo acuerdo voluntario.</li>
						<li><i class="fa-solid fa-check text-success mr-1"></i>Activar banco de horas extras autorizadas respetando descanso de 10h.</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<!-- 5. SIMULADOR INTERACTIVO DE VENTAS Y DOTACIÓN (BENCHMARKS OFICIALES) -->
	<div class="card border-0 shadow-xs mb-3 bg-white" id="simuladorSectionCard" style="border-radius: 12px; border: 1px solid #e2e8f0;">
		<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-calculator mr-2 text-success"></i>Simulador Interactivo de Dotación según Ventas Diarias
				</h6>
				<span class="text-muted small">Calcula al instante cuántos colaboradores requiere la tienda y cómo distribuirlos en cada área según la productividad objetivo.</span>
			</div>
			<div class="d-flex align-items-center gap-1">
				<span class="text-muted small mr-2">Presets de Venta:</span>
				<button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="window.runSimPreset(15000)">$15k</button>
				<button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="window.runSimPreset(25000)">$25k</button>
				<button type="button" class="btn btn-xs btn-outline-primary font-weight-bold active" onclick="window.runSimPreset(40000)">$40k (Base)</button>
				<button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" onclick="window.runSimPreset(60000)">$60k</button>
				<button type="button" class="btn btn-xs btn-outline-danger font-weight-bold" onclick="window.runSimPreset(75000)">$75k (20 Colab.)</button>
				<button type="button" class="btn btn-xs btn-outline-warning text-dark font-weight-bold" onclick="window.runSimPreset(100000)">$100k</button>
			</div>
		</div>
		<div class="card-body p-4">
			<div class="row align-items-center mb-4">
				<div class="col-lg-6 mb-3 mb-lg-0">
					<label class="font-weight-bold text-dark small d-flex justify-content-between">
						<span>Venta Diaria Proyectada:</span>
						<span class="text-primary font-weight-bold" id="simSalesDisplay" style="font-size: 1.1rem;">$40,000</span>
					</label>
					<input type="range" class="custom-range" id="simSalesSlider" min="10000" max="120000" step="5000" value="40000" oninput="window.onSimSliderChange(this.value)">
					<div class="d-flex justify-content-between text-muted text-xs">
						<span>$10,000 (Mínima)</span>
						<span>$40,000 (Base normal)</span>
						<span>$75,000 (Benchmark 20 colab.)</span>
						<span>$120,000 (Black Friday)</span>
					</div>
				</div>

				<div class="col-lg-6">
					<div class="row g-2 text-center">
						<div class="col-3">
							<div class="p-2 rounded bg-light border">
								<span class="text-muted text-xs font-weight-bold text-uppercase d-block">Colaboradores</span>
								<h4 class="font-weight-bold text-primary mb-0 mt-1" id="simResultStaff">12</h4>
								<span class="text-muted text-xs" id="simResultRange">10–14 colab.</span>
							</div>
						</div>
						<div class="col-3">
							<div class="p-2 rounded bg-light border">
								<span class="text-muted text-xs font-weight-bold text-uppercase d-block">Horas Lab.</span>
								<h4 class="font-weight-bold text-dark mb-0 mt-1" id="simResultHours">96 h</h4>
								<span class="text-muted text-xs">Jornada 8h</span>
							</div>
						</div>
						<div class="col-3">
							<div class="p-2 rounded bg-light border">
								<span class="text-muted text-xs font-weight-bold text-uppercase d-block">SPLH Proy.</span>
								<h4 class="font-weight-bold text-success mb-0 mt-1" id="simResultSplh">$169.8</h4>
								<span class="text-muted text-xs">Venta / h</span>
							</div>
						</div>
						<div class="col-3">
							<div class="p-2 rounded bg-light border">
								<span class="text-muted text-xs font-weight-bold text-uppercase d-block">Costo Lab. %</span>
								<h4 class="font-weight-bold text-indigo mb-0 mt-1" id="simResultLaborPct">3.24%</h4>
								<span class="text-muted text-xs" id="simResultCost">$1,296 total</span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Distribución por Área Resultante de la Simulación -->
			<div class="border rounded p-3 bg-light">
				<h6 class="font-weight-bold text-dark small mb-2 d-flex justify-content-between align-items-center">
					<span><i class="fa-solid fa-users-gear mr-1.5 text-primary"></i>Distribución de Personal por Área Calculada para esta Venta:</span>
					<span class="badge badge-success text-uppercase" id="simBenchmarkBadge" style="font-size: 0.72rem;">Distribución Óptima Validada</span>
				</h6>
				<div class="row g-2 text-center" id="simAreaDistributionCards">
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Ventas</span>
							<h5 class="font-weight-bold text-danger mb-0" id="cardAreaVentas">4</h5>
							<span class="text-xs text-muted">colaboradores</span>
						</div>
					</div>
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Operaciones</span>
							<h5 class="font-weight-bold text-primary mb-0" id="cardAreaOperaciones">2</h5>
							<span class="text-xs text-muted">colaboradores</span>
						</div>
					</div>
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Digital</span>
							<h5 class="font-weight-bold text-info mb-0" id="cardAreaDigital">2</h5>
							<span class="text-xs text-muted">colaboradores</span>
						</div>
					</div>
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Despachos</span>
							<h5 class="font-weight-bold text-warning mb-0" id="cardAreaDespachos">1</h5>
							<span class="text-xs text-muted">colaboradores</span>
						</div>
					</div>
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Accesorios</span>
							<h5 class="font-weight-bold text-teal mb-0" id="cardAreaAccesorios">2</h5>
							<span class="text-xs text-muted">colaboradores</span>
						</div>
					</div>
					<div class="col-md-2 col-4 mb-2">
						<div class="bg-white p-2 rounded border shadow-2xs">
							<span class="text-muted text-xs font-weight-bold d-block">Apple</span>
							<h5 class="font-weight-bold text-purple mb-0" id="cardAreaApple">1</h5>
							<span class="text-xs text-muted">colaborador</span>
						</div>
					</div>
				</div>

				<!-- Franjas horarias sugeridas para esta dotación -->
				<div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
					<div>
						<i class="fa-solid fa-clock mr-1 text-primary"></i><strong>Franjas Óptimas Sugeridas:</strong> 
						<span id="simHourlyDistributionText">07:00–11:00 (4 colab) · 11:00–15:00 (10 colab PEAK) · 15:00–19:00 (10 colab PEAK) · 19:00–22:00 (4 colab)</span>
					</div>
					<div>
						<button type="button" class="btn btn-xs btn-primary font-weight-bold" onclick="toastr.success('Dotación simulada guardada como escenario What-If en Horarios')">
							<i class="fa-solid fa-check mr-1"></i>Aplicar a Planificación
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 6. PREDICCIÓN MULTI-HORIZONTE (MAÑANA, 7D, 30D, MES, 3 MESES) & 3 ESCENARIOS -->
	<div class="row mb-3">
		<div class="col-xl-7 col-12 mb-3 mb-xl-0">
			<div class="card border-0 shadow-xs bg-white h-100" style="border-radius: 12px; border: 1px solid #e2e8f0;">
				<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
					<h6 class="font-weight-bold text-dark mb-0">
						<i class="fa-solid fa-chart-line mr-2 text-indigo"></i>Proyecciones de Demanda y Dotación en 3 Escenarios
					</h6>
					<span class="badge badge-light border text-muted small">Incertidumbre ± 8.5% a 15.0%</span>
				</div>
				<div class="table-responsive">
					<table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.82rem;">
						<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem;">
							<tr>
								<th>Horizonte</th>
								<th class="text-right text-muted">Conservador (P20)</th>
								<th class="text-right text-primary font-weight-bold">Base (P50)</th>
								<th class="text-right text-danger">Alta Demanda (P85)</th>
								<th class="text-center font-weight-bold text-dark">Personal Base</th>
								<th class="text-center">Rango ±</th>
							</tr>
						</thead>
						<tbody>
							@forelse($forecasts as $fc)
								@php
									$hLabels = [
										'tomorrow' => 'Mañana (03/11)',
										'7days' => 'Próximos 7 días',
										'30days' => 'Próximos 30 días',
										'next_month' => 'Próximo mes (Diciembre)',
										'3months' => 'Próximos 3 meses (Q1)'
									];
								@endphp
								<tr>
									<td class="font-weight-bold text-dark">{{ $hLabels[$fc['horizon']] ?? $fc['horizon'] }}</td>
									<td class="text-right text-muted">${{ number_format($fc['scenario_conservative_sales'], 0) }} ({{ $fc['conservative_staff'] }} col.)</td>
									<td class="text-right text-primary font-weight-bold">${{ number_format($fc['scenario_base_sales'], 0) }}</td>
									<td class="text-right text-danger font-weight-bold">${{ number_format($fc['scenario_high_demand_sales'], 0) }} ({{ $fc['high_demand_staff'] }} col.)</td>
									<td class="text-center"><span class="badge badge-primary font-weight-bold px-2 py-1">{{ $fc['base_staff'] }} colab.</span></td>
									<td class="text-center text-muted">± {{ $fc['uncertainty_range_pct'] }}%</td>
								</tr>
							@empty
								<tr>
									<td colspan="6" class="text-center py-3 text-muted">Cargando pronósticos multi-horizonte...</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- 7. EVENTOS COMERCIALES & BLACK FRIDAY 2026 -->
		<div class="col-xl-5 col-12" id="blackFridaySectionCard">
			<div class="card border-0 shadow-xs bg-white h-100" style="border-radius: 12px; border: 1px solid #e2e8f0;">
				<div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
					<h6 class="font-weight-bold mb-0 text-white">
						<i class="fa-solid fa-tag mr-2 text-warning"></i>Planificación Especial: Black Friday 2026
					</h6>
					<span class="badge badge-warning text-dark font-weight-bold">Viernes 27/11/2026</span>
				</div>
				<div class="card-body p-3">
					<div class="p-2.5 rounded bg-light border mb-3">
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="text-muted small">Venta Normal Base:</span>
							<strong class="text-dark">$40,000 /día</strong>
						</div>
						<div class="d-flex justify-content-between align-items-center mb-1">
							<span class="text-muted small">Incremento Proyectado (+80%):</span>
							<strong class="text-danger font-weight-bold" style="font-size: 1.05rem;">$72,000 proyectado</strong>
						</div>
						<div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1">
							<span class="text-muted small font-weight-bold">Dotación Requerida:</span>
							<span class="badge badge-danger font-weight-bold px-2 py-1" style="font-size: 0.85rem;">20 colaboradores (+8 refuerzo)</span>
						</div>
					</div>

					<div class="small text-muted mb-2 font-weight-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Reglas de Optimización Black Friday:</div>
					<ul class="list-unstyled small mb-3">
						<li class="mb-1.5 d-flex align-items-start">
							<i class="fa-solid fa-ban text-danger mr-2 mt-0.5"></i>
							<span><strong>No escalar personal 80% plano:</strong> Aumentar sólo según minutos reales de reposición y despacho (+40% horas laborales).</span>
						</li>
						<li class="mb-1.5 d-flex align-items-start">
							<i class="fa-solid fa-clock text-warning mr-2 mt-0.5"></i>
							<span><strong>Horario extendido:</strong> 07:00 a 23:00 con refuerzos focalizados en <code>10:00–14:00</code> y <code>17:00–21:00</code>.</span>
						</li>
						<li class="d-flex align-items-start">
							<i class="fa-solid fa-truck-ramp-box text-primary mr-2 mt-0.5"></i>
							<span><strong>Refuerzos prioritarios:</strong> +3 en Cajas/Ventas, +2 en Despachos/Digital, +3 en Reposición continua.</span>
						</li>
					</ul>

					<button type="button" class="btn btn-sm btn-dark btn-block font-weight-bold shadow-xs" onclick="toastr.success('Cargando calendario especial de refuerzos para Black Friday 2026')">
						<i class="fa-solid fa-calendar-plus mr-1"></i>Ver Turnos y Refuerzos Black Friday
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- 8. RECOMENDACIONES DE AJUSTE ACCIONABLES (CONECTADAS CON TURNOS) -->
	<div class="card border-0 shadow-xs mb-3 bg-white" style="border-radius: 12px; border: 1px solid #e2e8f0;">
		<div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-wand-magic-sparkles mr-2 text-purple"></i>Recomendaciones Accionables del Motor de Horas Peak
				</h6>
				<span class="text-muted small">Al aprobar una sugerencia, se genera o ajusta automáticamente el turno en todas las 7 vistas de Horarios.</span>
			</div>
			<div>
				<span class="badge badge-purple px-2 py-1 font-weight-bold">{{ count($recs) }} Recomendaciones Generadas</span>
			</div>
		</div>
		<div class="table-responsive">
			<table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.82rem;">
				<thead class="bg-light text-muted uppercase font-weight-bold" style="font-size: 0.72rem;">
					<tr>
						<th>Fecha & Franja</th>
						<th>Área</th>
						<th>Tipo</th>
						<th style="max-width: 260px;">Acción Recomendada</th>
						<th class="text-center">Delta</th>
						<th>Colaborador Sugerido</th>
						<th class="text-right">Costo / Impacto</th>
						<th class="text-center">Estado</th>
						<th class="text-center">Acción</th>
					</tr>
				</thead>
				<tbody>
					@forelse($recs as $rc)
						<tr id="peakRecRow_{{ $rc['id'] }}">
							<td>
								<div class="font-weight-bold text-dark">{{ $rc['date'] }}</div>
								<span class="badge badge-light border text-muted" style="font-size: 0.7rem;">{{ $rc['time_band'] }}</span>
							</td>
							<td class="font-weight-bold">{{ $rc['area'] }}</td>
							<td>
								<span class="badge badge-info text-uppercase" style="font-size: 0.65rem;">{{ str_replace('_', ' ', $rc['type']) }}</span>
							</td>
							<td style="max-width: 260px; font-size: 0.78rem;">{{ $rc['recommended_action'] }}</td>
							<td class="text-center font-weight-bold text-danger text-nowrap">
								{{ ($rc['staff_delta'] > 0 ? '+' : '') . $rc['staff_delta'] }} colab.
							</td>
							<td>
								@if(!empty($rc['suggested_employee_name']))
									<span class="font-weight-600 text-dark"><i class="fa-solid fa-user-check mr-1 text-success"></i>{{ $rc['suggested_employee_name'] }}</span>
								@else
									<span class="text-muted">Personal de refuerzo</span>
								@endif
							</td>
							<td class="text-right font-weight-bold text-dark">
								{{ $rc['impact_cost'] > 0 ? $money($rc['impact_cost']) : 'Sin costo adicional' }}
							</td>
							<td class="text-center">
								@if($rc['status'] === 'approved')
									<span class="badge badge-success font-weight-bold px-2 py-1"><i class="fa-solid fa-check mr-1"></i>Aprobada</span>
								@else
									<span class="badge badge-warning text-dark font-weight-bold px-2 py-1">Pendiente</span>
								@endif
							</td>
							<td class="text-center text-nowrap">
								@if($rc['status'] !== 'approved')
									<button type="button" class="btn btn-xs btn-success font-weight-bold" onclick="window.applyPeakRec({{ $rc['id'] }}, this)">
										<i class="fa-solid fa-check mr-1"></i>Aprobar y Aplicar
									</button>
								@else
									<span class="text-muted small">Aplicado a turnos</span>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="9" class="text-center py-3 text-muted">No hay recomendaciones pendientes.</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>

</div>

<!-- SCRIPT DE INTERACTIVIDAD DEL MOTOR PEAK -->
<script>
window.setPeakInterval = function(mins, btn) {
	$('#btnInterval60, #btnInterval30, #btnInterval15').removeClass('active');
	$(btn).addClass('active');
	toastr.info('Cambiando granularidad a intervalos de ' + mins + ' minutos...');
};

window.runSimPreset = function(salesVal) {
	$('#simSalesSlider').val(salesVal);
	window.onSimSliderChange(salesVal);
};

window.onSimSliderChange = function(salesVal) {
	salesVal = parseFloat(salesVal);
	$('#simSalesDisplay').text('$' + salesVal.toLocaleString('en-US'));

	// Llamada AJAX al simulador en backend
	$.ajax({
		url: Grocy.BaseUrl + '/api/workforce/peaks/simulate',
		type: 'POST',
		contentType: 'application/json',
		data: JSON.stringify({
			branch_id: window.DairyScheduleData ? window.DairyScheduleData.branchId : 1,
			daily_sales: salesVal
		}),
		success: function(res) {
			$('#simResultStaff').text(res.total_staff_required);
			$('#simResultHours').text(res.total_labor_hours + ' h');
			$('#simResultSplh').text('$' + parseFloat(res.splh_projected).toFixed(1));
			$('#simResultLaborPct').text(parseFloat(res.labor_pct_of_sales).toFixed(2) + '%');
			$('#simResultCost').text('$' + parseFloat(res.projected_labor_cost).toLocaleString('en-US') + ' total');

			// Actualizar tarjetas de áreas
			var ad = res.area_distribution;
			if (ad['Ventas']) $('#cardAreaVentas').text(ad['Ventas'].staff);
			if (ad['Operaciones']) $('#cardAreaOperaciones').text(ad['Operaciones'].staff);
			if (ad['Digital']) $('#cardAreaDigital').text(ad['Digital'].staff);
			if (ad['Despachos']) $('#cardAreaDespachos').text(ad['Despachos'].staff);
			if (ad['Accesorios']) $('#cardAreaAccesorios').text(ad['Accesorios'].staff);
			if (ad['Apple']) $('#cardAreaApple').text(ad['Apple'].staff);

			// Rango ilustrativo
			var minR = Math.max(4, Math.floor(res.total_staff_required * 0.85));
			var maxR = Math.ceil(res.total_staff_required * 1.15);
			$('#simResultRange').text(minR + '–' + maxR + ' colab.');

			// Franjas sugeridas
			var hd = res.hourly_distribution;
			var hdStr = '';
			for (var k in hd) {
				hdStr += k + ' (' + hd[k] + ' colab) · ';
			}
			$('#simHourlyDistributionText').text(hdStr.slice(0, -3));
		}
	});
};

window.applyPeakRec = function(recId, btn) {
	$(btn).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-1"></i>Aplicando...');
	$.ajax({
		url: Grocy.BaseUrl + '/api/workforce/peaks/recommendations/' + recId + '/apply',
		type: 'POST',
		contentType: 'application/json',
		success: function(res) {
			toastr.success(res.message || 'Recomendación aplicada con éxito');
			$('#peakRecRow_' + recId).find('td:nth-child(8)').html('<span class="badge badge-success font-weight-bold px-2 py-1"><i class="fa-solid fa-check mr-1"></i>Aprobada</span>');
			$('#peakRecRow_' + recId).find('td:nth-child(9)').html('<span class="text-muted small">Aplicado a turnos</span>');
		},
		error: function(err) {
			$(btn).prop('disabled', false).html('<i class="fa-solid fa-check mr-1"></i>Aprobar y Aplicar');
			toastr.error('Error al aplicar la recomendación');
		}
	});
};
</script>
