@extends('layout.default')

@section('title', 'Inteligencia Predictiva, MRP y Simulador Financiero')

@section('content')
<div class="row align-items-center mb-3 dairy-page-header">
	<div class="col-md-8 d-flex align-items-center">
		<div class="dairy-page-icon is-info"><i class="fa-solid fa-brain"></i></div>
		<div>
			<h4 class="dairy-page-title">Inteligencia Predictiva, MRP & Simulador</h4>
			<p class="dairy-page-subtitle">Detección de anomalías, cálculo determinístico de reabastecimiento (VMD /
				ROP) y modelado financiero de pérdidas evitables.</p>
		</div>
	</div>
	<div class="col-md-4 text-md-right mt-2 mt-md-0 dairy-page-actions">
		<a href="{{ $U('/kpis') }}" class="btn btn-outline-primary btn-sm shadow-sm mr-1">
			<i class="fa-solid fa-chart-line mr-1"></i>Panel Ejecutivo
		</a>
		<a href="{{ $U('/pos') }}" class="btn btn-success btn-sm shadow-sm">
			<i class="fa-solid fa-cash-register mr-1"></i>Punto de Venta
		</a>
	</div>
</div>

<!-- Filtro de Sucursal -->
<div class="card shadow-sm border-0 mb-3 bg-light">
	<div class="card-body py-2">
		<form method="GET" action="{{ $U('/ai-advisor') }}" class="row align-items-center">
			<div class="col-md-6">
				<div class="input-group input-group-sm">
					<div class="input-group-prepend">
						<span class="input-group-text bg-white"><i
								class="fa-solid fa-building text-muted"></i>&nbsp;Sucursal</span>
					</div>
					<select name="branch_id" id="advisor-branch-filter" class="custom-select custom-select-sm"
						onchange="this.form.submit()">
						<option value="">Todas las sucursales</option>
						@foreach($branches as $b)
							<option value="{{ $b->id }}" @if($selectedBranchId == $b->id) selected @endif>{{ $b->name }}
							</option>
						@endforeach
					</select>
				</div>
			</div>
			<div class="col-md-6 text-md-right small text-muted mt-1 mt-md-0">
				<i class="fa-solid fa-bolt text-warning mr-1"></i>Modelo determinístico activo: VMD ponderada a 30 días
				+ Restricción antimermas
			</div>
		</form>
	</div>
</div>

<!-- Pestañas de Navegación del Módulo de Inteligencia -->
<ul class="nav nav-pills dairy-subnav-container dairy-subnav-pills flex-nowrap mb-4" id="advisorTabs" role="tablist">
	<li class="nav-item">
		<a class="nav-link active font-weight-bold" id="tab-alerts-btn" data-toggle="pill" href="#tab-alerts"
			role="tab">
			<i class="fa-solid fa-bell mr-2"></i>1. Diagnóstico IA & Alertas
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" href="{{ $U('/mrp') }}" title="Abre la vista de Planificación MRP">
			<i class="fa-solid fa-boxes-packing mr-2"></i>2. MRP & Reabastecimiento <i
				class="fa-solid fa-arrow-up-right-from-square ml-1 small"></i>
		</a>
	</li>
	<li class="nav-item">
		<a class="nav-link font-weight-bold" id="tab-simulator-btn" data-toggle="pill" href="#tab-simulator" role="tab">
			<i class="fa-solid fa-calculator mr-2"></i>3. Simulador What-If (Merma vs. FEFO)
		</a>
	</li>
</ul>

<div class="tab-content" id="advisorTabContent">
	<!-- PESTAÑA 1: Diagnóstico IA & Alertas -->
	<div class="tab-pane fade show active" id="tab-alerts" role="tabpanel">
		{{-- Preguntar a Arturito: respuestas solo con datos del sistema (observado / estimado / recomendación) --}}
		<div class="card mb-4" id="arturito-ask">
			<div class="card-body">
				<form id="arturito-form" class="d-flex dairy-gap-2" autocomplete="off">
					<input type="text" id="arturito-q" class="form-control" maxlength="500"
						placeholder="Pregúntale a Arturito, p. ej. ¿Por qué aumentaron las mermas este mes?"
						aria-label="Pregunta">
					<button class="btn btn-primary text-nowrap" type="submit"><i
							class="fa-solid fa-robot mr-1"></i>Preguntar</button>
				</form>
				<div class="mt-2 d-flex flex-wrap dairy-gap-2" id="arturito-suggestions">
					@foreach(\Grocy\Services\AdvisorQaService::SUGGESTIONS as $s)
						<button type="button" class="btn btn-sm btn-light border arturito-chip">{{ $s }}</button>
					@endforeach
				</div>
				<div id="arturito-answer" class="mt-3 d-none"></div>
				<div class="small text-muted mt-2">Arturito solo lee datos de DAILY y no ejecuta acciones: cada
					recomendación enlaza a la página donde un responsable la aprueba.</div>
			</div>
		</div>
		<div class="row">
			@forelse($alerts as $alert)
				<div class="col-lg-6 mb-4">
					<div class="card shadow-sm border-0 h-100"
						style="border-left: 5px solid @if($alert['severity'] === 'danger') var(--dairy-danger) @elseif($alert['severity'] === 'warning') var(--dairy-warning) @else var(--dairy-success) @endif !important;">
						<div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
							<h5
								class="mb-0 font-weight-bold @if($alert['severity'] === 'danger') text-danger @elseif($alert['severity'] === 'warning') text-warning @else text-success @endif">
								<i class="fa-solid {{ $alert['icon'] }} mr-2"></i>{{ $alert['title'] }}
							</h5>
							<span
								class="badge @if($alert['severity'] === 'danger') badge-danger @elseif($alert['severity'] === 'warning') badge-warning @else badge-success @endif px-2 py-1">
								@if($alert['severity'] === 'danger') Crítico @elseif($alert['severity'] === 'warning')
								Advertencia @else Normal @endif
							</span>
						</div>
						<div class="card-body p-4 pt-0">
							<div class="bg-light rounded p-3 mb-3 d-flex justify-content-between align-items-center">
								<div>
									<small
										class="text-muted d-block text-uppercase font-weight-bold">{{ $alert['metric_label'] }}</small>
									<span
										class="h4 mb-0 font-weight-bold @if($alert['severity'] === 'danger') text-danger @elseif($alert['severity'] === 'warning') text-warning @else text-success @endif">
										{{ $alert['metric_value'] }}
									</span>
								</div>
								<i class="fa-solid fa-chart-pie fa-2x text-muted opacity-50"></i>
							</div>

							<p class="text-muted mb-3">{{ $alert['description'] }}</p>

							<h6 class="font-weight-bold text-dark small text-uppercase mb-2"><i
									class="fa-solid fa-list-check mr-1"></i>Artículos Involucrados:</h6>
							<ul class="list-group list-group-flush border rounded mb-3 small"
								style="max-height: 140px; overflow-y: auto;">
								@foreach($alert['details'] as $det)
									<li class="list-group-item py-2 px-3">
										<i class="fa-solid fa-angle-right text-muted mr-1"></i>{{ $det }}
									</li>
								@endforeach
							</ul>

							<div class="alert alert-info py-2 px-3 mb-0 small">
								<strong class="d-block mb-1"><i
										class="fa-solid fa-lightbulb text-warning mr-1"></i>Recomendación
									Operativa:</strong>
								{{ $alert['recommendation'] }}
							</div>
						</div>
						<div class="card-footer bg-white border-0 pt-0 pb-3">
							@if($alert['type'] === 'risk')
								<a href="{{ $U('/pos') }}" class="btn btn-sm btn-outline-success font-weight-bold">
									<i class="fa-solid fa-cash-register mr-1"></i>Venta FEFO
								</a>
								<a href="{{ $U('/waste/report') }}" class="btn btn-sm btn-outline-danger font-weight-bold ml-1">
									<i class="fa-solid fa-trash-can mr-1"></i>Declarar Merma
								</a>
							@elseif($alert['type'] === 'anomaly')
								<a href="{{ $U('/waste') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
									<i class="fa-solid fa-magnifying-glass mr-1"></i>Auditar Pareto
								</a>
							@else
								<a href="{{ $U('/stockoverview') }}" class="btn btn-sm btn-outline-primary font-weight-bold">
									<i class="fa-solid fa-boxes-stacked mr-1"></i>Ver Inventario
								</a>
							@endif
						</div>
					</div>
				</div>
			@empty
				<div class="col-12 text-center py-5 text-muted">
					<i class="fa-solid fa-shield-check fa-4x text-success mb-3"></i>
					<h4>Operaciones en Estado Saludable</h4>
					<p>No se encontraron riesgos de inventario, caducidades críticas ni anomalías en las sucursales
						evaluadas.</p>
				</div>
			@endforelse
		</div>
	</div>

	<!-- PESTAÑA 3: Simulador Financiero What-If -->
	<div class="tab-pane fade" id="tab-simulator" role="tabpanel">
		<div class="row">
			<div class="col-lg-5 mb-4">
				<div class="card shadow-sm border-0 h-100">
					<div class="card-header bg-dark text-white py-3">
						<h5 class="mb-0 font-weight-bold">
							<i class="fa-solid fa-sliders mr-2 text-warning"></i>Parámetros del Escenario What-If
						</h5>
						<small class="text-white-50">Simule la rentabilidad de liquidación FEFO vs. pérdida
							total</small>
					</div>
					<div class="card-body p-3">
						<form id="simulator-form">
							<div class="form-group mb-3">
								<label class="small font-weight-bold text-muted mb-1">Seleccionar Producto en
									Riesgo</label>
								<select id="sim-product-select" class="custom-select custom-select-sm" required>
									@foreach($products as $p)
										<option value="{{ $p->id }}" data-price="{{ $p->sale_price ?? 0 }}">{{ $p->name }}
											({{ $p->sale_price ? '$' . number_format($p->sale_price, 2) : 'sin precio de venta' }})
										</option>
									@endforeach
								</select>
							</div>

							<div class="form-group mb-3">
								<label class="small font-weight-bold text-muted mb-1">Cantidad del Lote Próximo a
									Vencer</label>
								<div class="input-group input-group-sm">
									<input type="number" id="sim-lot-amount" class="form-control" value="50" min="1"
										max="1000" required>
									<div class="input-group-append">
										<span class="input-group-text">unidades</span>
									</div>
								</div>
							</div>

							<div class="form-group mb-4">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<label class="small font-weight-bold text-muted mb-0">Descuento de Liquidación
										Preventiva FEFO</label>
									<span class="badge badge-warning text-dark font-weight-bold"
										id="sim-discount-badge">30% OFF</span>
								</div>
								<input type="range" class="custom-range" id="sim-discount-slider" min="0.10" max="0.50"
									step="0.05" value="0.30">
								<div class="d-flex justify-content-between small text-muted">
									<span>10% (Leve)</span>
									<span>30% (Recomendado FEFO)</span>
									<span>50% (Agresivo)</span>
								</div>
							</div>

							<div class="form-row mb-3">
								<div class="col-6"><label for="sim-sell-through" class="small font-weight-bold text-muted mb-1">% del lote vendido con rebaja (supuesto)</label><input type="number" id="sim-sell-through" class="form-control form-control-sm" value="85" min="1" max="100"></div>
								<div class="col-6"><label for="sim-days-left" class="small font-weight-bold text-muted mb-1">Días para vencer</label><input type="number" id="sim-days-left" class="form-control form-control-sm" min="0" max="365" placeholder="del lote más próximo"></div>
							</div>
							<button type="button" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm"
								id="btn-run-simulation">
								<i class="fa-solid fa-bolt mr-1"></i>Calcular Impacto Financiero
							</button>
						</form>
					</div>
				</div>
			</div>

			<!-- Resultados Comparativos de la Simulación -->
			<div class="col-lg-7 mb-4">
				<div class="card shadow-sm border-0 h-100" id="sim-results-card">
					<div class="card-header bg-white py-3">
						<h5 class="mb-0 font-weight-bold text-dark">
							<i class="fa-solid fa-chart-line text-success mr-2"></i>Evaluación Comparativa de
							Rentabilidad y Pérdida Evitada
						</h5>
					</div>
					<div class="card-body p-3">
						<div class="row mb-3">
							<!-- Escenario A: Descarte -->
							<div class="col-md-6 mb-2">
								<div class="card border-danger h-100 p-3 bg-light">
									<h6 class="font-weight-bold text-danger mb-2">
										<i class="fa-solid fa-trash-can mr-1"></i>Escenario A: Sin Intervención
									</h6>
									<small class="text-muted d-block mb-2">Vende a su ritmo histórico (28 días) hasta vencer; el resto se descarta a costo.</small>
									<hr class="my-1">
									<div class="d-flex justify-content-between mb-1">
										<span class="small text-muted">Ingreso recuperado:</span>
										<strong class="text-dark" id="sim-res-cash-a">$0.00</strong>
									</div>
									<div class="d-flex justify-content-between mb-1">
										<span class="small text-muted">Pérdida neta de merma:</span>
										<strong class="text-danger" id="sim-res-loss-a">-$0.00</strong>
									</div>
									<div class="d-flex justify-content-between">
										<span class="small text-muted">Tasa de recuperación:</span>
										<span class="badge badge-danger" id="sim-res-recovery-a">0%</span>
									</div>
								</div>
							</div>

							<!-- Escenario B: FEFO -->
							<div class="col-md-6 mb-2">
								<div class="card border-success h-100 p-3 bg-light">
									<h6 class="font-weight-bold text-success mb-2">
										<i class="fa-solid fa-tag mr-1"></i>Escenario B: Liquidación FEFO
									</h6>
									<small class="text-muted d-block mb-2">Rebaja preventiva con rotación acelerada (85%
										venta).</small>
									<hr class="my-1">
									<div class="d-flex justify-content-between mb-1">
										<span class="small text-muted">Flujo de caja protegido:</span>
										<strong class="text-success" id="sim-res-cash-b">+$0.00</strong>
									</div>
									<div class="d-flex justify-content-between mb-1">
										<span class="small text-muted">Merma residual mínima:</span>
										<strong class="text-warning" id="sim-res-loss-b">-$0.00</strong>
									</div>
									<div class="d-flex justify-content-between">
										<span class="small text-muted">Tasa de recuperación:</span>
										<span class="badge badge-success" id="sim-res-recovery-b">—</span>
									</div>
								</div>
							</div>
						</div>

						<!-- Diagnóstico del Simulador -->
						<div class="alert alert-success py-2 px-3 mb-0" id="sim-roi-banner">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<strong class="d-block"><i
											class="fa-solid fa-piggy-bank mr-1 text-success"></i>Merma evitada (estimación):</strong>
									<span class="small" id="sim-roi-text">Haga clic en 'Calcular Impacto Financiero'
										para evaluar los escenarios.</span>
								</div>
								<span class="h4 mb-0 font-weight-bold text-success" id="sim-saved-usd">$0.00</span>
							</div>
						</div>
						<div class="row small mt-2 d-none" id="sim-basis">
							<div class="col-md-6"><strong class="text-primary"><i class="fa-solid fa-database mr-1"></i>Datos observados</strong><ul class="pl-3 mb-0" id="sim-facts"></ul></div>
							<div class="col-md-6"><strong class="text-warning"><i class="fa-solid fa-calculator mr-1"></i>Supuestos</strong><ul class="pl-3 mb-0" id="sim-assumptions"></ul><span class="text-muted">La simulación no modifica datos.</span></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	// Abrir pestaña desde enlace con hash (#tab-simulator)
	window.addEventListener('load', function () {
		if (window.location.hash) { $('a[data-toggle="pill"][href="' + window.location.hash + '"]').trigger('click'); }
	});
	// Mismo enlace desde el sidebar estando ya en la página: solo cambia el hash, sin recarga
	window.addEventListener('hashchange', function () {
		if (window.location.hash) { $('a[data-toggle="pill"][href="' + window.location.hash + '"]').trigger('click'); }
	});
	document.addEventListener("DOMContentLoaded", function () {
		// Simulator Slider
		var slider = document.getElementById('sim-discount-slider');
		var badge = document.getElementById('sim-discount-badge');
		slider.addEventListener('input', function () {
			badge.textContent = Math.round(this.value * 100) + '% OFF';
		});

		// Run Simulation Button
		document.getElementById('btn-run-simulation').addEventListener('click', function () {
			var prodId = document.getElementById('sim-product-select').value;
			var lotAmount = parseFloat(document.getElementById('sim-lot-amount').value);
			var discountPct = parseFloat(document.getElementById('sim-discount-slider').value);

			fetch(Grocy.BaseUrl + '/api/simulator/evaluate', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify({
					product_id: parseInt(prodId),
					lot_amount: lotAmount,
					markdown_discount_pct: discountPct,
					sell_through_pct: document.getElementById('sim-sell-through').value,
					days_left: document.getElementById('sim-days-left').value,
					branch_id: new URLSearchParams(location.search).get('branch_id')
				})
			})
				.then(function (res) { return res.json().then(function (j) { if (!res.ok) { throw new Error(j.error_message || 'Error al ejecutar simulación'); } return j; }); })
				.then(function (res) {
					var li = function (a) { return a.map(function (t) { return '<li>' + $('<div>').text(t).html() + '</li>'; }).join(''); };
					document.getElementById('sim-res-cash-a').textContent = '+$' + res.scenario_a_waste.revenue_recovered.toFixed(2);
					document.getElementById('sim-res-recovery-a').textContent = res.scenario_a_waste.recovery_rate_pct + '%';
					$('#sim-facts').html(li(res.facts));
					$('#sim-assumptions').html(li(res.assumptions));
					$('#sim-basis').removeClass('d-none');
					document.getElementById('sim-res-loss-a').textContent = '-$' + res.scenario_a_waste.total_cogs_lost.toFixed(2);
					document.getElementById('sim-res-cash-b').textContent = '+$' + res.scenario_b_markdown.cash_protected.toFixed(2);
					document.getElementById('sim-res-loss-b').textContent = '-$' + res.scenario_b_markdown.total_cogs_lost.toFixed(2);
					document.getElementById('sim-res-recovery-b').textContent = res.scenario_b_markdown.recovery_rate_pct + '%';
					document.getElementById('sim-saved-usd').textContent = '+$' + res.financial_roi.loss_avoided_usd.toFixed(2);
					document.getElementById('sim-roi-text').textContent = res.financial_roi.recommendation;
					toastr.success('Simulación completada con éxito.');
				})
				.catch(function (err) {
					toastr.error($('<div>').text(err.message || 'Error al ejecutar simulación').html());
				});
		});
	});
</script>
<script>
	document.addEventListener('DOMContentLoaded', function () {
		var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
		var labels = { observed: ['Datos observados', 'fa-database', 'text-primary'], estimate: ['Estimaciones', 'fa-calculator', 'text-warning'], recommendation: ['Recomendaciones (requieren aprobación)', 'fa-lightbulb', 'text-success'] };
		var branch = new URLSearchParams(location.search).get('branch_id');
		function render(a) {
			var html = '<h6 class="font-weight-bold mb-2"><i class="fa-solid fa-robot text-purple mr-1"></i>' + esc(a.title) + '</h6>';
			(a.sections || []).forEach(function (s) {
				if (!(s.items || []).length && !s.table) { return; }
				var l = labels[s.type] || [s.type, 'fa-circle', ''];
				html += '<div class="mb-2"><div class="small font-weight-bold text-uppercase ' + l[2] + '"><i class="fa-solid ' + l[1] + ' mr-1"></i>' + l[0] + '</div><ul class="mb-1 pl-3">';
				(s.items || []).forEach(function (i) {
					html += typeof i === 'string' ? '<li>' + esc(i) + '</li>' : '<li>' + esc(i.text) + (i.href ? ' <a href="' + U(i.href) + '">Abrir</a>' : '')
						+ (i.action ? ' <button type="button" class="btn btn-sm btn-outline-primary ml-1 arturito-action" data-type="' + esc(i.action) + '"><i class="fa-solid fa-hand-pointer mr-1"></i>Revisar y confirmar</button>' : '') + '</li>';
				});
				html += '</ul>';
				if (s.table) {
					html += '<div class="table-responsive"><table class="table table-sm mb-1"><thead><tr>' + s.table.columns.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>'
						+ s.table.rows.map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>';
				}
				html += '</div>';
			});
			if ((a.sources || []).length) { html += '<div class="small text-muted">Fuentes: ' + esc(a.sources.join(', ')) + ' · ' + esc(a.generated_at) + '</div>'; }
			$('#arturito-answer').html(html).removeClass('d-none');
		}
		function ask(q) {
			$('#arturito-q').val(q);
			$('#arturito-answer').removeClass('d-none').html('<span class="text-muted"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Consultando datos…</span>');
			Grocy.Api.Post('ai/ask', { question: q, branch_id: branch || null }, render, function (xhr) {
				var m = 'No se pudo responder'; try { m = JSON.parse(xhr.responseText).error_message || m; } catch (e) { }
				$('#arturito-answer').html('<span class="text-danger">' + esc(m) + '</span>');
			});
		}
		$('#arturito-form').on('submit', function (e) { e.preventDefault(); ask($('#arturito-q').val()); });
		$(document).on('click', '.arturito-chip', function () { ask($(this).text().trim()); });
		// Acción propuesta: vista previa sin escribir → confirmación explícita → el servidor valida permiso y registra en bitácora
		var errMsg = function (xhr, d) { try { return JSON.parse(xhr.responseText).error_message || d; } catch (e) { return xhr.status === 403 ? 'No tiene permiso para esta acción' : d; } };
		$(document).on('click', '.arturito-action', function () {
			var type = $(this).data('type');
			var body = { type: type, branch_id: branch || null };
			Grocy.Api.Post('ai/actions/preview', body, function (p) {
				var html = '<p class="mb-2"><strong>' + esc(p.summary) + '</strong></p>' + (p.lines && p.lines.length ? '<ul class="small mb-2">' + p.lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' : '')
					+ (p.note ? '<p class="small text-muted mb-0"><i class="fa-solid fa-circle-info mr-1"></i>' + esc(p.note) + '</p>' : '');
				bootbox.dialog({ title: esc(p.label) + ' · propuesta de Arturito', message: html, buttons: {
					cancel: { label: 'Cancelar', className: 'btn-light border' },
					ok: { label: '<i class="fa-solid fa-check mr-1"></i>Confirmar y crear', className: 'btn-primary' + (p.can_execute ? '' : ' disabled'), callback: function () {
						if (!p.can_execute) { return false; }
						Grocy.Api.Post('ai/actions/execute', $.extend({ confirm: true, question: $('#arturito-q').val() }, body), function (r) {
							toastr.success(esc(r.message) + (r.href ? ' · <a href="' + U(r.href) + '">Abrir</a>' : ''));
						}, function (xhr) { toastr.error(esc(errMsg(xhr, 'No se pudo ejecutar la acción'))); });
					} }
				} });
			}, function (xhr) { toastr.error(esc(errMsg(xhr, 'No se pudo preparar la acción'))); });
		});
	});
</script>
@stop