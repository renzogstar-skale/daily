@php
	$cur = $L('$', '$');
	$inputs = [
		['sales', $L('Ventas mensuales', 'Monthly sales'), 500000, 1000, $cur],
		['branches', $L('Número de sucursales', 'Number of branches'), 5, 1, ''],
		['employees', $L('Número de empleados', 'Number of employees'), 80, 1, ''],
		['inventory', $L('Valor del inventario', 'Inventory value'), 800000, 1000, $cur],
		['shrink', $L('Merma sobre ventas (%)', 'Shrink as % of sales'), 2.5, 0.1, '%'],
		['opex', $L('Costos operativos mensuales', 'Monthly operating costs'), 120000, 1000, $cur],
	];
	$assume = [
		['a_shrink', $L('Reducción de merma', 'Shrink reduction'), 15, '%'],
		['a_opex', $L('Ahorro en costos operativos', 'Operating cost savings'), 2, '%'],
		['a_stock', $L('Reducción de inventario inmovilizado', 'Reduction of idle inventory'), 5, '%'],
		['a_capital', $L('Costo anual del capital', 'Annual cost of capital'), 12, '%'],
		['a_hours', $L('Horas ahorradas por empleado al mes', 'Hours saved per employee per month'), 2, 'h'],
		['a_rate', $L('Costo por hora de un empleado', 'Hourly cost per employee'), 5, $cur],
		['a_license', $L('Inversión mensual estimada en DAILY (opcional)', 'Estimated monthly investment in DAILY (optional)'), 0, $cur],
	];
@endphp
<section class="s-section s-roi" id="roi" aria-labelledby="roi-title">
	<div class="s-wrap s-roi__grid">
		<div class="s-roi__intro" data-reveal>
			<span class="s-eyebrow"><svg class="s-ico"><use href="#i-calc"/></svg>{{ $L('Calculadora de rentabilidad', 'ROI calculator') }}</span>
			<h2 id="roi-title">{{ $L('¿Cuánto podría recuperar tu operación?', 'How much could your operation recover?') }}</h2>
			<p>{{ $L('Ingresa tus cifras y ajusta los supuestos. Es una estimación de escenarios, no una promesa de resultados: cada supuesto está a la vista y puedes cambiarlo.', 'Enter your figures and adjust the assumptions. It is a scenario estimate, not a promise of results: every assumption is visible and editable.') }}</p>
			<form class="s-calc" id="roi-form" novalidate>
				<fieldset>
					<legend>{{ $L('Tu operación', 'Your operation') }}</legend>
					<div class="s-calc__fields">
						@foreach($inputs as [$id, $label, $val, $step, $unit])
						<label class="s-field"><span>{{ $label }}</span>
							<span class="s-input"><input type="number" name="{{ $id }}" value="{{ $val }}" min="0" step="{{ $step }}" inputmode="decimal">@if($unit)<em>{{ $unit }}</em>@endif</span>
						</label>
						@endforeach
					</div>
				</fieldset>
				<details class="s-calc__assume">
					<summary>{{ $L('Supuestos editables', 'Editable assumptions') }}<svg class="s-ico s-ico--sm"><use href="#i-down"/></svg></summary>
					<div class="s-calc__fields">
						@foreach($assume as [$id, $label, $val, $unit])
						<label class="s-field"><span>{{ $label }}</span>
							<span class="s-input"><input type="number" name="{{ $id }}" value="{{ $val }}" min="0" step="any" inputmode="decimal"><em>{{ $unit }}</em></span>
						</label>
						@endforeach
					</div>
				</details>
			</form>
		</div>
		<div class="s-roi__result" data-reveal aria-live="polite">
			<div class="s-roi__card">
				<span class="s-roi__label">{{ $L('Ahorro potencial anual — escenario base', 'Potential annual savings — base scenario') }}</span>
				<strong class="s-roi__big" id="roi-total">—</strong>
				<div class="s-roi__bars" id="roi-bars">
					<div data-k="shrink"><span>{{ $L('Merma evitada', 'Avoided shrink') }}</span><b></b><i><u></u></i></div>
					<div data-k="opex"><span>{{ $L('Costos operativos', 'Operating costs') }}</span><b></b><i><u></u></i></div>
					<div data-k="capital"><span>{{ $L('Capital liberado (costo financiero)', 'Freed capital (financing cost)') }}</span><b></b><i><u></u></i></div>
					<div data-k="hours"><span>{{ $L('Horas de personal', 'Staff hours') }}</span><b></b><i><u></u></i></div>
				</div>
				<div class="s-roi__scen">
					<div><small>{{ $L('Conservador (×0,5)', 'Conservative (×0.5)') }}</small><strong id="roi-low">—</strong></div>
					<div class="is-base"><small>{{ $L('Base (×1)', 'Base (×1)') }}</small><strong id="roi-base">—</strong></div>
					<div><small>{{ $L('Optimista (×1,5)', 'Optimistic (×1.5)') }}</small><strong id="roi-high">—</strong></div>
				</div>
				<p class="s-roi__roi" id="roi-return" hidden></p>
				<p class="s-roi__note">{{ $L('Fórmulas: merma evitada = ventas × % merma × reducción × 12; costos = costos operativos × ahorro × 12; capital = inventario × reducción × costo del capital; horas = empleados × horas × costo por hora × 12. Las sucursales se usan para mostrar el promedio por sucursal.', 'Formulas: avoided shrink = sales × shrink % × reduction × 12; costs = operating costs × savings × 12; capital = inventory × reduction × cost of capital; hours = employees × hours × hourly cost × 12. Branches are used to show the per-branch average.') }}</p>
				<p class="s-roi__per" id="roi-per"></p>
			</div>
		</div>
	</div>
</section>
