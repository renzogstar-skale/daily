{{-- DAILY: Pestaña 17 - Bakery & Panadería: ¿Qué productos se botan más? --}}
@php
	$bakerySvc = \Grocy\Services\RetailPreventionService::GetInstance();
	$bakeryData = $bakerySvc->GetBakeryDashboard($selectedBranchId ?? 1);
	$bTotals = $bakeryData['totals'];
	$topUnits = $bakeryData['top_units'];
	$topCost = $bakeryData['top_cost'];
	$byCause = $bakeryData['by_cause'];
	$byDay = $bakeryData['by_day'];
	$byShift = $bakeryData['by_shift'];
	$ai = $bakeryData['ai_insights'];
	$money = fn($v) => '$' . number_format(floatval($v), 2);
@endphp

<div class="tab-pane fade" id="tab-bakery" role="tabpanel" aria-labelledby="tab-bakery-link">
	
	<!-- Header de Bakery -->
	<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
		<div class="d-flex align-items-center">
			<div class="mr-3" style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #d97706, #f59e0b); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
				<i class="fa-solid fa-bread-slice"></i>
			</div>
			<div>
				<h5 class="font-weight-bold text-dark mb-0">Bakery & Panadería — Análisis Profundo de Mermas y Predicción de Producción</h5>
				<span class="text-muted small">Detección de productos críticos, causas de descarte, concentración por turno y tanda, y pronóstico inteligente con IA.</span>
			</div>
		</div>
		<div>
			<span class="badge badge-warning text-dark font-weight-bold px-3 py-2" style="font-size: 0.82rem;">
				<i class="fa-solid fa-brain mr-1"></i>Motor Predictivo de Producción Activo
			</span>
		</div>
	</div>

	<!-- 1. KPIS SUPERIORES DE PANADERÍA -->
	<div class="row mb-3">
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #f59e0b !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Total Producido</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ number_format($bTotals['total_produced']) }} un.</h4>
				<span class="small text-muted">Horneo consolidado</span>
			</div>
		</div>
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #10b981 !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Total Vendido</span>
				<h4 class="font-weight-bold text-success mb-0 mt-1">{{ number_format($bTotals['total_sold']) }} un.</h4>
				<span class="small text-success">Ventas reales registradas</span>
			</div>
		</div>
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #ef4444 !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Unidades Botadas</span>
				<h4 class="font-weight-bold text-danger mb-0 mt-1">{{ number_format($bTotals['total_waste_units']) }} un.</h4>
				<span class="small text-danger font-weight-bold">Merma física total</span>
			</div>
		</div>
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #b91c1c !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">Costo de Pérdida ($)</span>
				<h4 class="font-weight-bold text-danger mb-0 mt-1">{{ $money($bTotals['total_waste_cost']) }}</h4>
				<span class="small text-muted font-weight-bold">Valorizado a costo</span>
			</div>
		</div>
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #ea580c !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">% Merma / Producción</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ $bTotals['avg_waste_pct_production'] }}%</h4>
				<span class="small text-warning font-weight-bold">Meta panadería: ≤ 8.0%</span>
			</div>
		</div>
		<div class="col-xl-2 col-md-4 col-6 mb-2">
			<div class="card border-0 shadow-xs p-3 bg-white daily-bk-kpi-card" style="border-radius: 10px; border-left: 4px solid #8b5cf6 !important;">
				<span class="text-muted font-weight-bold small text-uppercase" style="font-size: 0.68rem;">% Merma / Ventas</span>
				<h4 class="font-weight-bold text-dark mb-0 mt-1">{{ $bTotals['avg_waste_pct_sales'] }}%</h4>
				<span class="small text-muted">Impacto sobre facturación</span>
			</div>
		</div>
	</div>

	<!-- 2. INSIGHTS INTELIGENTES DE PRODUCCIÓN Y ALERTAS -->
	<div class="p-3 mb-3 rounded bg-white border d-flex align-items-center gap-3 shadow-xs" style="border-left: 4px solid #f59e0b !important;">
		<div style="font-size: 2rem; color: #d97706;"><i class="fa-solid fa-lightbulb"></i></div>
		<div>
			<div class="d-flex align-items-center gap-2">
				<strong class="text-dark">Diagnóstico Operativo de Panadería:</strong>
				<span class="badge badge-danger">Producto con Mayor Pérdida: {{ $ai['highest_waste_product'] }}</span>
				<span class="badge badge-warning text-dark">Días Críticos: {{ $ai['critical_day'] }}</span>
			</div>
			<div class="small text-muted mt-1">
				<strong>Causa raíz identificada:</strong> {{ $ai['worst_shift'] }}. 
				<strong>Recomendación autorizada:</strong> {{ $ai['recommendation'] }}
			</div>
		</div>
	</div>

	<!-- 3. TABLA COMPARATIVA PRINCIPAL: TOP PRODUCTOS CON MAYOR MERMA & PRONÓSTICO IA -->
	<div class="card border-0 shadow-sm bg-white mb-3" style="border-radius: 10px; overflow: hidden;">
		<div class="card-header bg-white py-3 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
			<div>
				<h6 class="font-weight-bold text-dark mb-0">
					<i class="fa-solid fa-fire-burner mr-2 text-warning"></i>Top Productos de Bakery — Análisis de Mermas y Sugerencia de Producción
				</h6>
				<span class="small text-muted">Comparativa obligatoria: Producido, Vendido, Merma, % sobre producción, % sobre ventas y Pronóstico IA.</span>
			</div>
			<span class="badge badge-light border text-muted">Datos auditados de tandas y vitrina</span>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0" style="font-size: 0.83rem;">
					<thead class="thead-light">
						<tr>
							<th>Producto / Categoría</th>
							<th class="text-center">Producido</th>
							<th class="text-center">Vendido</th>
							<th class="text-center text-danger">Merma (un.)</th>
							<th class="text-center">% sobre Producción</th>
							<th class="text-center">% sobre Ventas</th>
							<th class="text-right">Costo Merma</th>
							<th class="text-center font-weight-bold text-primary">Sugerencia Producción (IA)</th>
							<th>Justificación Operativa</th>
						</tr>
					</thead>
					<tbody>
						@foreach($topUnits as $bRow)
							@php
								$pctProd = floatval($bRow['waste_pct_production']);
								$pctTone = ($pctProd > 12.0) ? 'badge-danger' : (($pctProd > 7.0) ? 'badge-warning text-dark' : 'badge-success');
							@endphp
							<tr>
								<td class="font-weight-bold text-dark">
									<i class="fa-solid fa-bread-slice text-warning mr-1"></i>{{ $bRow['category_subtype'] }}
									<div class="text-muted" style="font-size: 0.73rem;">{{ $bRow['product_name'] }}</div>
								</td>
								<td class="text-center font-weight-600">{{ number_format($bRow['total_produced']) }}</td>
								<td class="text-center font-weight-600 text-success">{{ number_format($bRow['total_sold']) }}</td>
								<td class="text-center font-weight-bold text-danger" style="font-size: 0.9rem;">{{ number_format($bRow['total_waste_units']) }}</td>
								<td class="text-center">
									<span class="badge {{ $pctTone }} px-2 py-1 font-weight-bold">{{ $bRow['waste_pct_production'] }}%</span>
								</td>
								<td class="text-center font-weight-600 text-muted">{{ $bRow['waste_pct_sales'] }}%</td>
								<td class="text-right font-weight-bold text-dark">{{ $money($bRow['total_waste_cost']) }}</td>
								<td class="text-center font-weight-bold text-primary" style="font-size: 0.9rem;">
									{{ $bRow['suggested_qty'] }} <span class="small font-weight-normal text-muted">± {{ $bRow['uncertainty_range'] }}</span>
								</td>
								<td class="small text-muted" style="max-width: 280px;">
									{{ $bRow['ai_reasoning'] }}
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- 4. MATRICES DE DESGLOSE: POR CAUSA, POR DÍA DE LA SEMANA Y POR TURNO -->
	<div class="row">
		<!-- Desglose por Causal -->
		<div class="col-lg-4 col-12 mb-3">
			<div class="card border-0 shadow-sm bg-white h-100" style="border-radius: 10px;">
				<div class="card-header bg-white py-2 px-3 border-bottom">
					<h6 class="font-weight-bold text-dark mb-0 small text-uppercase">
						<i class="fa-solid fa-tags mr-1 text-danger"></i>Mermas por Causal
					</h6>
				</div>
				<div class="card-body p-0">
					<ul class="list-group list-group-flush" style="font-size: 0.82rem;">
						@foreach($byCause as $cRow)
							<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
								<div>
									<span class="font-weight-bold text-dark text-capitalize">{{ str_replace('_', ' ', $cRow['cause']) }}</span>
									<div class="small text-muted">{{ $cRow['occurrences'] }} registros · {{ number_format($cRow['waste_units']) }} un.</div>
								</div>
								<div class="text-right">
									<span class="font-weight-bold text-danger">{{ $money($cRow['waste_cost']) }}</span>
									<span class="badge badge-light border text-muted ml-1">{{ $cRow['avg_pct_production'] }}%</span>
								</div>
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>

		<!-- Desglose por Día de la Semana -->
		<div class="col-lg-4 col-12 mb-3">
			<div class="card border-0 shadow-sm bg-white h-100" style="border-radius: 10px;">
				<div class="card-header bg-white py-2 px-3 border-bottom">
					<h6 class="font-weight-bold text-dark mb-0 small text-uppercase">
						<i class="fa-solid fa-calendar-week mr-1 text-info"></i>Merma por Día de Semana
					</h6>
				</div>
				<div class="card-body p-0">
					<ul class="list-group list-group-flush" style="font-size: 0.82rem;">
						@foreach($byDay as $dRow)
							<li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
								<div>
									<span class="font-weight-bold text-dark">{{ $dRow['day_name'] }}</span>
									<div class="small text-muted">Producido: {{ number_format($dRow['produced']) }} · Vendido: {{ number_format($dRow['sold']) }}</div>
								</div>
								<div class="text-right">
									<span class="font-weight-bold text-danger">{{ number_format($dRow['waste_units']) }} un.</span>
									<div class="small text-muted">{{ $money($dRow['waste_cost']) }} ({{ $dRow['waste_pct'] }}%)</div>
								</div>
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>

		<!-- Desglose por Turno de Horneo -->
		<div class="col-lg-4 col-12 mb-3">
			<div class="card border-0 shadow-sm bg-white h-100" style="border-radius: 10px;">
				<div class="card-header bg-white py-2 px-3 border-bottom">
					<h6 class="font-weight-bold text-dark mb-0 small text-uppercase">
						<i class="fa-solid fa-clock mr-1 text-primary"></i>Merma por Turno de Horneo
					</h6>
				</div>
				<div class="card-body p-0">
					<ul class="list-group list-group-flush" style="font-size: 0.82rem;">
						@foreach($byShift as $sRow)
							<li class="list-group-item d-flex justify-content-between align-items-center py-3 px-3">
								<div>
									<span class="font-weight-bold text-dark text-capitalize">
										@if($sRow['shift'] === 'mañana')
											<i class="fa-solid fa-sun text-warning mr-1"></i>Turno Mañana (06:00 - 13:00)
										@elseif($sRow['shift'] === 'tarde')
											<i class="fa-solid fa-cloud-sun text-orange mr-1"></i>Turno Tarde (13:00 - 20:00)
										@else
											<i class="fa-solid fa-moon text-dark mr-1"></i>Turno Noche / Horneo Previo
										@endif
									</span>
									<div class="small text-muted">Producción del turno: {{ number_format($sRow['produced']) }} un.</div>
								</div>
								<div class="text-right">
									<h6 class="font-weight-bold text-danger mb-0">{{ number_format($sRow['waste_units']) }} un.</h6>
									<span class="badge @if($sRow['waste_pct'] > 12) badge-danger @else badge-warning text-dark @endif">
										{{ $sRow['waste_pct'] }}% merma
									</span>
								</div>
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>
	</div>

</div>
