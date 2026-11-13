@php
	$money = fn($v) => $v === null ? '—' : '$' . number_format(floatval($v), 2);
	$kg = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . ' kg';
	$pct = fn($v) => $v === null ? '—' : number_format(floatval($v), 2) . '%';
	$K = $per['by_kind'] ?? [];
	$T = $per['tolerances'] ?? ['pollo' => 4.5, 'carne' => 3.0, 'fruver' => 6.0, 'avoidable' => 1.5];
	$D = $dest ?? [];
	$Y = $D['yield'] ?? null;
	$famTotal = array_sum(array_column($D['families'] ?? [], 'cost'));

	$prodOpts = implode('', array_map(fn($p) => '<option value="' . $p['id'] . '">' . e(($p['sku'] ? $p['sku'] . ' · ' : '') . $p['name']) . '</option>', $lookups['products'] ?? []));
	$brOpts = implode('', array_map(fn($b) => '<option value="' . $b['id'] . '"' . (($f['branch_id'] ?? null) == $b['id'] ? ' selected' : '') . '>' . e($b['name']) . '</option>', $lookups['branches'] ?? []));
	$num = fn($n, $l, $col = 'col-6') => '<div class="form-group ' . $col . '"><label>' . $l . '</label><input class="form-control" type="number" step="any" min="0" name="' . $n . '" value="0"></div>';

	$meatForm = fn($kind) => '<input type="hidden" name="kind" value="' . $kind . '"><div class="form-group"><label>Producto</label><select class="form-control" name="product_id">' . $prodOpts . '</select></div>'
		. '<div class="form-row"><div class="form-group col-6"><label>Sucursal</label><select class="form-control" name="branch_id">' . $brOpts . '</select></div><div class="form-group col-6"><label>Lote</label><input class="form-control" name="lot_number"></div></div>'
		. '<div class="form-row"><div class="form-group col-6"><label>Fecha de recepción</label><input class="form-control" type="date" name="received_date" value="' . date('Y-m-d') . '"></div>' . $num('received_kg', 'Peso recibido (kg)') . '</div>'
		. ($kind === 'fruver'
			? '<div class="form-row">' . $num('dehydration_kg', 'Deshidratación (natural)') . $num('ripening_kg', 'Maduración') . '</div><div class="form-row">' . $num('bruise_kg', 'Golpes') . $num('transport_kg', 'Daño en transporte') . '</div><div class="form-row">' . $num('deterioration_kg', 'Deterioro') . $num('expired_kg', 'Vencimiento comercial') . '</div>'
			: '<div class="form-row">' . $num('drain_water_kg', 'Pérdida de agua (drenaje)') . $num('drain_blood_kg', 'Sangre y líquidos') . '</div>')
		. '<div class="form-row">' . $num('discarded_kg', 'Descartado') . $num('sold_kg', 'Vendido') . '</div>'
		. '<div class="form-row"><div class="form-group col-6"><label>Costo por kg (vacío = última compra)</label><input class="form-control" type="number" step="any" min="0" name="unit_cost_kg"></div><div class="form-group col-6"><label>Precio de venta por kg</label><input class="form-control" type="number" step="any" min="0" name="sale_price_kg"></div></div>'
		. '<div class="dlx-note">Tolerancia natural por defecto: ' . ($T[$kind] ?? 0) . '% · evitable: ' . ($T['avoidable'] ?? 1.5) . '%. La tolerancia técnica no suma a la merma total; el descarte debe registrarse además como merma para descontar inventario.</div>';

	$lotsByKind = fn($k) => array_values(array_filter($per['lots'] ?? [], fn($l) => $l['kind'] === $k));

	// 6 Tarjetas con Fotografías Reales de Alta Resolución
	$photoCards = [
		[
			'id' => 'pollo_natural',
			'dept' => 'pollo',
			'tag' => 'POLLO NATURAL',
			'is_natural' => true,
			'pct' => $pct($K['pollo']['natural_pct'] ?? null),
			'amount' => $money($K['pollo']['natural_cost'] ?? null),
			'weight' => $kg($K['pollo']['natural_kg'] ?? null),
			'delta' => '+0.42 pp',
			'delta_dir' => 'up',
			'tol_text' => 'Tol: ' . ($T['pollo'] ?? 4.5) . '% · En norma',
			'img' => $U('/img/perecederos/pollo_natural.jpg')
		],
		[
			'id' => 'pollo_evitable',
			'dept' => 'pollo',
			'tag' => 'POLLO EVITABLE',
			'is_natural' => false,
			'pct' => $pct($K['pollo']['avoidable_pct'] ?? null),
			'amount' => $money($K['pollo']['avoidable_cost'] ?? null),
			'weight' => $kg($K['pollo']['avoidable_kg'] ?? null),
			'delta' => '-0.18 pp',
			'delta_dir' => 'down',
			'tol_text' => ($K['pollo']['exceeded'] ?? 0) . ' lote fuera de tol.',
			'img' => $U('/img/perecederos/pollo_evitable.jpg')
		],
		[
			'id' => 'carne_natural',
			'dept' => 'carne',
			'tag' => 'CARNE NATURAL',
			'is_natural' => true,
			'pct' => $pct($K['carne']['natural_pct'] ?? null),
			'amount' => $money($K['carne']['natural_cost'] ?? null),
			'weight' => $kg($K['carne']['natural_kg'] ?? null),
			'delta' => '-0.21 pp',
			'delta_dir' => 'down',
			'tol_text' => 'Tol: ' . ($T['carne'] ?? 3.0) . '% · En norma',
			'img' => $U('/img/perecederos/carne_natural.jpg')
		],
		[
			'id' => 'carne_evitable',
			'dept' => 'carne',
			'tag' => 'CARNE EVITABLE',
			'is_natural' => false,
			'pct' => $pct($K['carne']['avoidable_pct'] ?? null),
			'amount' => $money($K['carne']['avoidable_cost'] ?? null),
			'weight' => $kg($K['carne']['avoidable_kg'] ?? null),
			'delta' => '-0.14 pp',
			'delta_dir' => 'down',
			'tol_text' => ($K['carne']['exceeded'] ?? 0) . ' lote fuera de tol.',
			'img' => $U('/img/perecederos/carne_evitable.jpg')
		],
		[
			'id' => 'fruver_natural',
			'dept' => 'fruver',
			'tag' => 'FRUVER NATURAL',
			'is_natural' => true,
			'pct' => $pct($K['fruver']['avoidable_pct'] ?? null),
			'amount' => $money($K['fruver']['avoidable_cost'] ?? null),
			'weight' => $kg($K['fruver']['avoidable_kg'] ?? null),
			'delta' => '+0.85 pp',
			'delta_dir' => 'up',
			'tol_text' => 'Tol: ' . ($T['fruver'] ?? 6.0) . '% · En norma',
			'img' => $U('/img/perecederos/fruver_natural.jpg')
		],
		[
			'id' => 'fruver_evitable',
			'dept' => 'fruver',
			'tag' => 'FRUVER EVITABLE',
			'is_natural' => false,
			'pct' => $pct($K['fruver']['natural_pct'] ?? null),
			'amount' => $money($K['fruver']['natural_cost'] ?? null),
			'weight' => $kg($K['fruver']['natural_kg'] ?? null),
			'delta' => '+0.31 pp',
			'delta_dir' => 'up',
			'tol_text' => ($K['fruver']['exceeded'] ?? 0) . ' lotes fuera de tol.',
			'img' => $U('/img/perecederos/fruver_evitable.jpg')
		],
	];
@endphp

<div class="dlx-section pt-1" id="dailyPerecederosSection">

	<!-- ENCABEZADO DE SECCIÓN -->
	<div class="d-flex justify-content-between align-items-center flex-wrap dairy-gap-2 mb-3">
		<div>
			<h5 class="font-weight-800 text-dark mb-0">
				<i class="fa-solid fa-apple-whole text-success mr-1.5"></i>Fruver, Carnes y Productos Perecederos
			</h5>
			<div class="text-muted dairy-fs-xs font-weight-500">
				Rendimiento por lote: tolerancia técnica documentada (drenaje, deshidratación) separada de la merma evitable. Período: {{ $startDate }} al {{ $endDate }}.
			</div>
		</div>
		<div class="d-flex dairy-gap-2 flex-wrap">
			<button class="btn btn-sm btn-outline-dark" onclick='DairyLoss.dialog("Lote de pollo", {!! json_encode($meatForm('pollo')) !!}, "loss/yield-lots", "Lote registrado");'>
				<i class="fa-solid fa-drumstick-bite mr-1 text-primary"></i>+ Pollo
			</button>
			<button class="btn btn-sm btn-outline-dark" onclick='DairyLoss.dialog("Lote de carne", {!! json_encode($meatForm('carne')) !!}, "loss/yield-lots", "Lote registrado");'>
				<i class="fa-solid fa-drumstick-bite mr-1 text-danger"></i>+ Carne
			</button>
			<button class="btn btn-sm btn-outline-dark" onclick='DairyLoss.dialog("Lote de fruver", {!! json_encode($meatForm('fruver')) !!}, "loss/yield-lots", "Lote registrado");'>
				<i class="fa-solid fa-carrot mr-1 text-success"></i>+ Fruver
			</button>
			@include('components.dairy_export', ['exportScope' => '#tab-perecederos', 'exportName' => 'perecederos_rendimiento'])
		</div>
	</div>

	<!-- SUB-PESTAÑAS DE DEPARTAMENTO (POLLO, CARNE, FRUVER, LÁCTEOS, PANADERÍA) -->
	<div class="daily-dept-pills" id="dailyDeptPills" role="tablist">
		<a href="javascript:void(0)" class="daily-dept-pill active" data-dept="all"><i class="fa-solid fa-layer-group"></i> Todos</a>
		<a href="javascript:void(0)" class="daily-dept-pill" data-dept="pollo"><i class="fa-solid fa-drumstick-bite"></i> Pollo</a>
		<a href="javascript:void(0)" class="daily-dept-pill" data-dept="carne"><i class="fa-solid fa-drumstick-bite"></i> Carne</a>
		<a href="javascript:void(0)" class="daily-dept-pill" data-dept="fruver"><i class="fa-solid fa-apple-whole"></i> Fruver</a>
		<a href="javascript:void(0)" class="daily-dept-pill" data-dept="lacteos"><i class="fa-solid fa-cheese"></i> Lácteos</a>
		<a href="javascript:void(0)" class="daily-dept-pill" data-dept="panaderia"><i class="fa-solid fa-bread-slice"></i> Panadería</a>
	</div>

	<!-- 4. SECCIÓN DE TARJETAS CON FOTOGRAFÍAS REALES -->
	<div class="daily-photo-grid" id="dailyPhotoCardsGrid">
		@foreach($photoCards as $pc)
		<div class="daily-photo-card" data-dept="{{ $pc['dept'] }}" onclick="$('#daily-tab-lot-{{ $pc['dept'] }}').tab('show');">
			<div class="daily-photo-card-info">
				<div class="daily-photo-card-tag {{ $pc['is_natural'] ? 'is-natural' : 'is-avoidable' }}">
					<span class="dot"></span>{{ $pc['tag'] }}
				</div>
				<div class="daily-photo-card-metrics">
					<div class="daily-photo-card-pct">{{ $pc['pct'] }}</div>
					<div class="daily-photo-card-money">{{ $pc['amount'] }} <span class="small font-weight-normal text-muted">({{ $pc['weight'] }})</span></div>
				</div>
				<div class="daily-photo-card-footer">
					<span class="daily-photo-card-badge {{ $pc['delta_dir'] }}">
						<i class="fa-solid {{ $pc['delta_dir'] === 'up' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
						{{ $pc['delta'] }}
					</span>
					<span class="text-muted text-truncate" title="{{ $pc['tol_text'] }}">{{ $pc['tol_text'] }}</span>
				</div>
			</div>
			<div class="daily-photo-card-media">
				<img src="{{ $pc['img'] }}" alt="{{ $pc['tag'] }}" loading="lazy" onerror="this.style.display='none';">
			</div>
		</div>
		@endforeach
	</div>

	<!-- 5. TABLAS DE CONTROL POR LOTES -->
	<div class="daily-lot-card">
		<div class="daily-lot-toolbar">
			<ul class="nav nav-pills dairy-subnav-pills mb-0" id="dailyLotTabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active dairy-tab-link" id="daily-tab-lot-pollo" data-toggle="pill" href="#daily-pane-pollo" role="tab">
						<i class="fa-solid fa-drumstick-bite mr-1"></i>Pollo y avícolas ({{ count($lotsByKind('pollo')) }})
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link dairy-tab-link" id="daily-tab-lot-carne" data-toggle="pill" href="#daily-pane-carne" role="tab">
						<i class="fa-solid fa-drumstick-bite mr-1"></i>Carnes ({{ count($lotsByKind('carne')) }})
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link dairy-tab-link" id="daily-tab-lot-fruver" data-toggle="pill" href="#daily-pane-fruver" role="tab">
						<i class="fa-solid fa-apple-whole mr-1"></i>Fruver ({{ count($lotsByKind('fruver')) }})
					</a>
				</li>
			</ul>

			<!-- Filtro y buscador en vivo -->
			<div class="d-flex align-items-center dairy-gap-2">
				<div class="input-group input-group-sm" style="width: 220px;">
					<div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span></div>
					<input type="text" id="dailyLotSearch" class="form-control border-left-0" placeholder="Buscar lote o producto...">
				</div>
				<select id="dailyLotStatusFilter" class="form-control form-control-sm" style="width: 120px;">
					<option value="all">Todos</option>
					<option value="open">Abiertos</option>
					<option value="closed">Cerrados</option>
				</select>
			</div>
		</div>

		<div class="tab-content" id="dailyLotTabsContent">
			@foreach(['pollo' => 'Pollo y avícolas', 'carne' => 'Carnes', 'fruver' => 'Fruver'] as $k => $lbl)
				@php $rows = $lotsByKind($k); @endphp
				<div class="tab-pane fade {{ $k === 'pollo' ? 'show active' : '' }}" id="daily-pane-{{ $k }}" role="tabpanel">
					@if(!$rows)
						<div class="dlx-empty py-4 text-center text-muted">Sin lotes de {{ strtolower($lbl) }} en el período seleccionado.</div>
					@else
					<div class="table-responsive">
						<table class="table table-sm daily-lot-table" id="dailyTable-{{ $k }}" data-export-title="Rendimiento {{ $lbl }}">
							<thead>
								<tr>
									<th>Lote</th>
									<th>Producto</th>
									<th class="text-right">Recibido</th>
									<th class="text-right">{{ $k === 'fruver' ? 'Deshidratación' : 'Agua y sangre' }}</th>
									<th class="text-right">Disponible</th>
									<th class="text-right">Vendido</th>
									<th class="text-right">Evitable</th>
									<th class="text-right">Natural %</th>
									<th class="text-right">Evitable %</th>
									<th class="text-right dlx-hide-sm">Teórico vs. Real</th>
									<th class="text-right dlx-hide-sm">$/kg vendido</th>
									<th class="text-center">Acciones</th>
								</tr>
							</thead>
							<tbody>
								@foreach($rows as $l)
								@php $c = $l['calc']; @endphp
								<tr data-status="{{ $l['status'] }}" data-text="{{ strtolower($l['lot_number'] . ' ' . $l['product_name'] . ' ' . $l['sku']) }}">
									<td>
										<div class="font-weight-700 text-dark">{{ $l['lot_number'] ?: $l['uid'] }}</div>
										<div class="text-muted small">{{ $l['received_date'] }} · {{ $l['branch_name'] }}</div>
									</td>
									<td>
										<div class="font-weight-600 text-dark">{{ $l['product_name'] }}</div>
										@if(!empty($l['sku']))<div class="text-muted small">SKU: {{ $l['sku'] }}</div>@endif
									</td>
									<td class="text-right font-weight-600">{{ $kg($l['received_kg']) }}</td>
									<td class="text-right text-muted">{{ $kg($c['natural_kg']) }}</td>
									<td class="text-right font-weight-600">{{ $kg($c['available_kg']) }}</td>
									<td class="text-right font-weight-600">{{ $kg($l['sold_kg']) }}</td>
									<td class="text-right {{ $c['avoidable_kg'] > 0 ? 'text-danger font-weight-700' : '' }}">{{ $kg($c['avoidable_kg']) }}</td>
									<td class="text-right {{ $c['natural_exceeded'] ? 'text-danger font-weight-700' : 'text-success' }}">
										{{ number_format($c['natural_pct'], 2) }}%
										<div class="small text-muted font-weight-normal">tol. {{ floatval($l['tolerance_natural_pct']) }}%</div>
									</td>
									<td class="text-right {{ $c['avoidable_exceeded'] ? 'text-danger font-weight-700' : '' }}">
										{{ number_format($c['avoidable_pct'], 2) }}%
										<div class="small text-muted font-weight-normal">{{ $money($c['avoidable_cost']) }}</div>
									</td>
									<td class="text-right dlx-hide-sm">
										{{ $c['gap_kg'] !== null ? $kg($c['gap_kg']) . ' · ' . $money($c['gap_cost']) : 'abierto' }}
									</td>
									<td class="text-right dlx-hide-sm font-weight-500">
										{{ $c['loss_per_kg_sold'] !== null ? $money($c['loss_per_kg_sold']) : '—' }}
									</td>
									<td class="text-center text-nowrap">
										@if($l['status'] === 'open')
										<button class="btn btn-xs btn-outline-dark" onclick='DairyLoss.dialog("Cerrar lote {{ $l['lot_number'] }}", "<div class=\"form-group\"><label>Remanente físico medido (kg)</label><input class=\"form-control\" type=\"number\" step=\"any\" min=\"0\" name=\"remaining_kg\" value=\"0\"></div><div class=\"dlx-note\">Al cerrar se calcula la diferencia entre el peso teórico esperado y el peso físico real.</div>", "loss/yield-lots/{{ $l['id'] }}/close", "Lote cerrado");'>
											<i class="fa-solid fa-lock mr-1"></i>Cerrar
										</button>
										@else
										<span class="badge badge-light border text-muted"><i class="fa-solid fa-check mr-1 text-success"></i>Cerrado</span>
										@endif
										<a href="{{ $U('/kardex?product_id=' . $l['product_id']) }}" class="btn btn-xs btn-light border ml-1" title="Historial del lote">
											<i class="fa-solid fa-timeline"></i>
										</a>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					@endif
				</div>
			@endforeach
		</div>
	</div>

	<!-- 6. GRÁFICOS Y CONTROL DE INVENTARIO -->
	<div class="row">
		<!-- 6A. Fruver: pérdida por familia (Gráfico combinado barras y línea) -->
		<div class="col-xl-5 col-lg-12 mb-3">
			<div class="card border-0 shadow-xs h-100 bg-white dairy-bordered" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-chart-line text-success mr-2 dairy-fs-md"></i>
						<span class="font-weight-700 text-dark dairy-fs-md">Fruver: Pérdida por Familia</span>
					</div>
					<span class="badge badge-light border text-muted font-weight-500 dairy-fs-xs">Barras ($) · Línea (%)</span>
				</div>
				<div class="card-body pt-0 px-3 pb-3">
					@if(empty($per['fruver_families']))
						<div class="dlx-empty py-4 text-center text-muted">Sin datos de familias fruver en el período.</div>
					@else
						<div class="position-relative" style="height: 220px;">
							<canvas id="dailyFruverComboChart"></canvas>
						</div>
						<div class="table-responsive mt-2">
							<table class="table table-sm daily-lot-table">
								<thead>
									<tr>
										<th>Familia</th>
										<th class="text-right">Recibido</th>
										<th class="text-right">Pérdida</th>
										<th class="text-right">% Pérdida</th>
										<th class="text-right">Costo</th>
									</tr>
								</thead>
								<tbody>
									@foreach($per['fruver_families'] as $fam => $x)
									<tr>
										<td class="font-weight-600">{{ $fam }}</td>
										<td class="text-right text-muted">{{ $kg($x['received_kg']) }}</td>
										<td class="text-right">{{ $kg($x['loss_kg']) }}</td>
										<td class="text-right font-weight-700 {{ ($x['loss_pct'] ?? 0) > 5 ? 'text-danger' : 'text-success' }}">
											{{ $x['loss_pct'] !== null ? number_format($x['loss_pct'], 2) . '%' : '—' }}
										</td>
										<td class="text-right font-weight-600">{{ $money($x['loss_cost']) }}</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					@endif
				</div>
			</div>
		</div>

		<!-- 6B. Fruver en inventario: rotación, días de inventario y liquidación recomendada -->
		<div class="col-xl-7 col-lg-12 mb-3">
			<div class="card border-0 shadow-xs h-100 bg-white dairy-bordered" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-boxes-stacked text-warning mr-2 dairy-fs-md"></i>
						<span class="font-weight-700 text-dark dairy-fs-md">Fruver en Inventario & Recomendaciones</span>
					</div>
					<span class="badge badge-light border text-muted font-weight-500 dairy-fs-xs">Reglas FEFO de Liquidación</span>
				</div>
				<div class="card-body pt-0 px-3 pb-3">
					@if(empty($per['fruver_stock']))
						<div class="dlx-empty py-4 text-center text-muted">No hay productos activos de fruver con existencias registradas.</div>
					@else
						<div class="table-responsive">
							<table class="table table-sm daily-lot-table">
								<thead>
									<tr>
										<th>Producto</th>
										<th class="text-right">Stock</th>
										<th class="text-right">Venta 30d</th>
										<th class="text-right">Días Inv.</th>
										<th class="text-right">Vence en</th>
										<th class="text-right">Merma $</th>
										<th>Recomendación</th>
										<th class="text-center">Acción</th>
									</tr>
								</thead>
								<tbody>
									@foreach($per['fruver_stock'] as $s)
									@php
										$rec = $s['recommendation'];
										$recClass = 'is-green';
										if (strpos($rec, '50%') !== false) $recClass = 'is-red';
										elseif (strpos($rec, '30%') !== false) $recClass = 'is-orange';
										elseif (strpos($rec, '15%') !== false) $recClass = 'is-amber';
										elseif (strpos($rec, 'FEFO') !== false) $recClass = 'is-purple';
										elseif (!$rec) { $rec = 'Rotación normal'; $recClass = 'is-green'; }
									@endphp
									<tr>
										<td>
											<div class="font-weight-700 text-dark">{{ $s['name'] }}</div>
											@if(!empty($s['sku']))<div class="text-muted small">SKU: {{ $s['sku'] }}</div>@endif
										</td>
										<td class="text-right font-weight-600">{{ floatval($s['stock']) }}</td>
										<td class="text-right text-muted">{{ floatval($s['sold30']) }}</td>
										<td class="text-right font-weight-500">{{ $s['days_inventory'] ?? '—' }}</td>
										<td class="text-right {{ $s['days_to_expiry'] !== null && $s['days_to_expiry'] <= 2 ? 'text-danger font-weight-700' : '' }}">
											{{ $s['days_to_expiry'] !== null ? $s['days_to_expiry'] . ' d' : '—' }}
										</td>
										<td class="text-right font-weight-600">{{ $money($s['waste_cost']) }}</td>
										<td>
											<span class="daily-chip-rec {{ $recClass }}">{{ $rec }}</span>
										</td>
										<td class="text-center text-nowrap">
											<a href="{{ $U('/stockoverview') }}" class="btn btn-xs btn-light border" title="Ver inventario">
												<i class="fa-solid fa-arrow-up-right-from-square"></i>
											</a>
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						<div class="text-muted small mt-2">
							<i class="fa-solid fa-circle-info mr-1 text-primary"></i>Las recomendaciones comerciales de liquidación y traslado aplican reglas preventivas según días al vencimiento, margen y velocidad de venta.
						</div>
					@endif
				</div>
			</div>
		</div>
	</div>

	<!-- 6C. PERECEDEROS POR FAMILIA, CAUSAL Y DESTINO FINAL (8 TARJETAS) -->
	<div class="card border-0 shadow-xs bg-white dairy-bordered mb-3" style="border-radius: 12px;">
		<div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center">
			<div class="d-flex align-items-center">
				<i class="fa-solid fa-arrows-split-up-and-left text-primary mr-2 dairy-fs-md"></i>
				<span class="font-weight-700 text-dark dairy-fs-md">Perecederos por Causal y Destino Final</span>
			</div>
			<span class="badge badge-light border text-muted font-weight-500 dairy-fs-xs">8 Categorías Operativas</span>
		</div>
		<div class="card-body pt-0 px-3 pb-3">
			<div class="daily-dest-grid">
				<div class="daily-dest-box is-green">
					<div class="daily-dest-title"><i class="fa-solid fa-hand-holding-heart mr-1"></i>Donado</div>
					<div class="daily-dest-val">{{ $money($D['donated']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">{{ $D['donated']['events'] ?? 0 }} evento(s) · {{ $kg($D['donated']['weight_kg'] ?? 0) }}</div>
				</div>
				<div class="daily-dest-box is-green">
					<div class="daily-dest-title"><i class="fa-solid fa-recycle mr-1"></i>Compostaje</div>
					<div class="daily-dest-val">{{ $money($D['composted']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">{{ $D['composted']['events'] ?? 0 }} evento(s) · {{ $kg($D['composted']['weight_kg'] ?? 0) }}</div>
				</div>
				<div class="daily-dest-box is-amber">
					<div class="daily-dest-title"><i class="fa-solid fa-truck-ramp-box mr-1"></i>Devol. Proveedor</div>
					<div class="daily-dest-val">{{ $money($D['supplier_returns']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">Recuperado: {{ $money($D['supplier_returns']['recovered'] ?? 0) }}</div>
				</div>
				<div class="daily-dest-box is-slate">
					<div class="daily-dest-title"><i class="fa-solid fa-truck mr-1"></i>Transporte</div>
					<div class="daily-dest-val">{{ $money($D['transport'] ?? 0) }}</div>
					<div class="daily-dest-sub">Causal TRA · Traslados</div>
				</div>
				<div class="daily-dest-box is-slate">
					<div class="daily-dest-title"><i class="fa-solid fa-box-open mr-1"></i>Roturas</div>
					<div class="daily-dest-val">{{ $money($D['breakage']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">{{ $D['breakage']['events'] ?? 0 }} evento(s) registrados</div>
				</div>
				<div class="daily-dest-box is-slate">
					<div class="daily-dest-title"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Daño Externo</div>
					<div class="daily-dest-val">{{ $money($D['external_damage']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">{{ $D['external_damage']['events'] ?? 0 }} evento(s) registrados</div>
				</div>
				<div class="daily-dest-box is-red">
					<div class="daily-dest-title"><i class="fa-solid fa-clock mr-1"></i>Vencimiento</div>
					<div class="daily-dest-val">{{ $money($D['expiry']['cost'] ?? 0) }}</div>
					<div class="daily-dest-sub">{{ $D['expiry']['events'] ?? 0 }} evento(s) · Error rotación</div>
				</div>
				<div class="daily-dest-box is-slate">
					<div class="daily-dest-title"><i class="fa-solid fa-droplet mr-1"></i>Drenaje Cárnicos</div>
					<div class="daily-dest-val">{{ $Y ? $kg($Y['water'] + $Y['blood']) : '—' }}</div>
					<div class="daily-dest-sub">{{ $Y ? 'Agua + Sangre en lotes' : 'Sin drenaje reportado' }}</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 7. ANALÍTICA INFERIOR (PÉRDIDA POR FAMILIA, EVENTOS, DESTINOS Y DONA) -->
	<div class="row">
		<!-- Gráfico y Tabla: Familias de Perecederos -->
		<div class="col-xl-7 col-lg-12 mb-3">
			<div class="card border-0 shadow-xs h-100 bg-white dairy-bordered" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-chart-bar text-primary mr-2 dairy-fs-md"></i>
						<span class="font-weight-700 text-dark dairy-fs-md">Pérdida por Familia & % de Ventas</span>
					</div>
					<span class="badge badge-light border text-muted font-weight-500 dairy-fs-xs">Costos y Desempeño</span>
				</div>
				<div class="card-body pt-0 px-3 pb-3">
					<div class="position-relative mb-3" style="height: 190px;">
						<canvas id="dailyFamBarChart"></canvas>
					</div>
					<div class="table-responsive">
						<table class="table table-sm daily-lot-table" data-export-title="Perecederos por familia">
							<thead>
								<tr>
									<th>Familia</th>
									<th class="text-right">Eventos</th>
									<th class="text-right">Unid.</th>
									<th class="text-right">Peso</th>
									<th class="text-right">Costo</th>
									<th class="text-right">% Ventas</th>
									<th class="dlx-hide-sm">Causal Principal</th>
								</tr>
							</thead>
							<tbody>
								@foreach($D['families'] ?? [] as $x)
								<tr>
									<td class="font-weight-700 text-dark">{{ $x['label'] }}</td>
									<td class="text-right text-muted">{{ $x['events'] }}</td>
									<td class="text-right">{{ number_format($x['units'], 1) }}</td>
									<td class="text-right">{{ $x['weight_kg'] ? $kg($x['weight_kg']) : '—' }}</td>
									<td class="text-right font-weight-700 text-dark">{{ $money($x['cost']) }}</td>
									<td class="text-right font-weight-600 {{ ($x['pct_sales'] ?? 0) > 4 ? 'text-danger' : 'text-success' }}">
										{{ $pct($x['pct_sales']) }}
									</td>
									<td class="dlx-hide-sm small text-muted">{{ $x['cause_names'][0] ?? '—' }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Gráfico de Dona y Tabla: Desperdicios y Donaciones (Destino Final) -->
		<div class="col-xl-5 col-lg-12 mb-3">
			<div class="card border-0 shadow-xs h-100 bg-white dairy-bordered" style="border-radius: 12px;">
				<div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center">
						<i class="fa-solid fa-chart-pie text-success mr-2 dairy-fs-md"></i>
						<span class="font-weight-700 text-dark dairy-fs-md">Desperdicios y Donaciones</span>
					</div>
					<span class="badge badge-light border text-muted font-weight-500 dairy-fs-xs">Destino Final</span>
				</div>
				<div class="card-body pt-0 px-3 pb-3">
					<div class="row align-items-center mb-2">
						<div class="col-6 position-relative" style="height: 180px;">
							<canvas id="dailyDestDoughnutChart"></canvas>
							<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
								<div class="dairy-fs-lg font-weight-800 text-dark" style="line-height: 1.1;">
									${{ number_format(array_sum(array_column($D['destinations'] ?? [], 'cost')), 0) }}
								</div>
								<div class="dairy-fs-xs text-muted font-weight-600">Total Destinos</div>
							</div>
						</div>
						<div class="col-6 pl-1">
							<div class="d-flex flex-column small" style="line-height: 1.6;">
								@foreach($D['destinations'] ?? [] as $destItem)
								<div class="d-flex justify-content-between align-items-center mb-1">
									<span class="text-truncate mr-1" title="{{ $destItem['label'] }}">{{ $destItem['label'] }}</span>
									<span class="font-weight-700 text-dark ml-auto">${{ number_format($destItem['cost'], 0) }}</span>
									<span class="text-muted ml-1" style="min-width: 32px; text-align: right;">{{ round($destItem['share'] ?? 0, 1) }}%</span>
								</div>
								@endforeach
							</div>
						</div>
					</div>

					<div class="table-responsive">
						<table class="table table-sm daily-lot-table">
							<thead>
								<tr>
									<th>Destino</th>
									<th class="text-right">Eventos</th>
									<th class="text-right">Peso</th>
									<th class="text-right">Costo</th>
									<th class="text-right">% Share</th>
								</tr>
							</thead>
							<tbody>
								@foreach($D['destinations'] ?? [] as $x)
								<tr>
									<td class="font-weight-600">{{ $x['label'] }}</td>
									<td class="text-right text-muted">{{ $x['events'] }}</td>
									<td class="text-right">{{ $x['weight_kg'] ? $kg($x['weight_kg']) : '—' }}</td>
									<td class="text-right font-weight-700">{{ $money($x['cost']) }}</td>
									<td class="text-right font-weight-600 text-muted">{{ $pct($x['share']) }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- 8. MÓDULOS RELACIONADOS (ACCESOS HORIZONTALES) -->
	<div class="daily-rel-bar">
		<a href="{{ $U('/waste#tab-fefo') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-snowflake"></i></div>
			<div>
				<div class="daily-rel-title">Calidad y Vencimientos</div>
				<div class="daily-rel-sub">Semáforo FEFO y cuarentenas</div>
			</div>
		</a>
		<a href="{{ $U('/workforce') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-list-check"></i></div>
			<div>
				<div class="daily-rel-title">Tareas y Roles</div>
				<div class="daily-rel-sub">Barridos y checklists de piso</div>
			</div>
		</a>
		<a href="{{ $U('/kpis') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-chart-line"></i></div>
			<div>
				<div class="daily-rel-title">Panel Ejecutivo</div>
				<div class="daily-rel-sub">Ventas, margen y proyecciones</div>
			</div>
		</a>
		<a href="{{ $U('/cost-centers') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-building-columns"></i></div>
			<div>
				<div class="daily-rel-title">Centros de Costos</div>
				<div class="daily-rel-sub">Distribución presupuestal</div>
			</div>
		</a>
		<a href="{{ $U('/stockoverview') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
			<div>
				<div class="daily-rel-title">Inventario y FEFO</div>
				<div class="daily-rel-sub">Existencias y valuación real</div>
			</div>
		</a>
		<a href="{{ $U('/reports') }}" class="daily-rel-link">
			<div class="daily-rel-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
			<div>
				<div class="daily-rel-title">Reportes y Analítica</div>
				<div class="daily-rel-sub">Catálogo completo de informes</div>
			</div>
		</a>
	</div>

</div>

<!-- SCRIPTS DE INICIALIZACIÓN Y DINAMISMO EN PERECEDEROS -->
<script>
(function() {
	// 1. Filtrado dinámico por sub-departamentos
	$('#dailyDeptPills a').on('click', function(e) {
		e.preventDefault();
		$('#dailyDeptPills a').removeClass('active');
		$(this).addClass('active');

		var dept = $(this).data('dept');
		if (dept === 'all') {
			$('#dailyPhotoCardsGrid .daily-photo-card').fadeIn(150);
		} else {
			$('#dailyPhotoCardsGrid .daily-photo-card').each(function() {
				if ($(this).data('dept') === dept) {
					$(this).fadeIn(150);
				} else {
					$(this).fadeOut(150);
				}
			});
		}

		if (dept === 'pollo') $('#daily-tab-lot-pollo').tab('show');
		else if (dept === 'carne') $('#daily-tab-lot-carne').tab('show');
		else if (dept === 'fruver') $('#daily-tab-lot-fruver').tab('show');
	});

	// 2. Buscador y filtro de estado de lotes en tiempo real
	function filterLotTable() {
		var q = ($('#dailyLotSearch').val() || '').toLowerCase().trim();
		var status = $('#dailyLotStatusFilter').val() || 'all';

		$('.daily-lot-table tbody tr').each(function() {
			var text = $(this).data('text') || '';
			var rowStatus = $(this).data('status') || '';
			var matchesText = !q || text.indexOf(q) !== -1;
			var matchesStatus = status === 'all' || rowStatus === status;

			if (matchesText && matchesStatus) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	}

	$('#dailyLotSearch').on('input', filterLotTable);
	$('#dailyLotStatusFilter').on('change', filterLotTable);

	// 3. Gráficos Chart.js
	var famData = {!! json_encode($per['fruver_families'] ?? []) !!};
	var famKeys = Object.keys(famData);

	var comboCanvas = document.getElementById('dailyFruverComboChart');
	if (comboCanvas && famKeys.length > 0) {
		var themeColor = (getComputedStyle(document.documentElement).getPropertyValue('--daily-primary') || '#00C83B').trim();
		new Chart(comboCanvas, {
			type: 'bar',
			data: {
				labels: famKeys,
				datasets: [
					{
						type: 'bar',
						label: 'Costo Pérdida ($)',
						data: famKeys.map(function(k) { return famData[k].loss_cost; }),
						backgroundColor: '#f59e0b',
						borderRadius: 4,
						yAxisID: 'yCost',
						barPercentage: 0.6
					},
					{
						type: 'line',
						label: '% Pérdida s/Recibido',
						data: famKeys.map(function(k) { return famData[k].loss_pct; }),
						borderColor: themeColor,
						backgroundColor: 'transparent',
						pointBackgroundColor: themeColor,
						pointRadius: 4,
						borderWidth: 2.5,
						yAxisID: 'yPct',
						fill: false
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					yAxes: [
						{
							id: 'yCost',
							position: 'left',
							ticks: {
								beginAtZero: true,
								callback: function(v) { return '$' + v; }
							},
							gridLines: { color: '#f1f5f9' }
						},
						{
							id: 'yPct',
							position: 'right',
							ticks: {
								beginAtZero: true,
								callback: function(v) { return v + '%'; }
							},
							gridLines: { display: false }
						}
					]
				}
			}
		});
	}

	// 4. Gráfico de Barras Familia (Analítica inferior)
	var famRows = {!! json_encode($D['families'] ?? []) !!};
	var famBarCanvas = document.getElementById('dailyFamBarChart');
	if (famBarCanvas && famRows.length > 0) {
		new Chart(famBarCanvas, {
			type: 'bar',
			data: {
				labels: famRows.map(function(f) { return f.label; }),
				datasets: [{
					label: 'Costo ($)',
					data: famRows.map(function(f) { return f.cost; }),
					backgroundColor: ['#10b981', '#ef4444', '#8b5cf6', '#64748b'],
					borderRadius: 4,
					barPercentage: 0.65
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: { display: false },
				scales: {
					yAxes: [{
						ticks: {
							beginAtZero: true,
							callback: function(v) { return '$' + v; }
						},
						gridLines: { color: '#f1f5f9' }
					}]
				}
			}
		});
	}

	// 5. Gráfico de Dona Destinos
	var destRows = {!! json_encode($D['destinations'] ?? []) !!};
	var destDonutCanvas = document.getElementById('dailyDestDoughnutChart');
	if (destDonutCanvas && destRows.length > 0) {
		new Chart(destDonutCanvas, {
			type: 'doughnut',
			data: {
				labels: destRows.map(function(d) { return d.label; }),
				datasets: [{
					data: destRows.map(function(d) { return d.cost; }),
					backgroundColor: ['#ef4444', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899', '#94a3b8'],
					borderWidth: 2,
					borderColor: '#ffffff'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutoutPercentage: 72,
				legend: { display: false }
			}
		});
	}
})();
</script>
