@extends('layout.default')

@section('title', 'Conteos sensibles')

@section('content')
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">

<div class="lp-page">
	<div class="lp-head">
		<div>
			<h1>Conteos sensibles</h1>
			<p>Abra una tarea, escanee el producto y cuente. DAILY compara con el stock esperado.</p>
		</div>
		@if(count($branches) > 1)
		<form method="get" class="lp-filters">
			<select name="branch_id" onchange="this.form.submit()" aria-label="Sucursal">
				<option value="">Todas mis sucursales</option>
				@foreach($branches as $b)<option value="{{ $b->id }}" @if($selectedBranchId === intval($b->id)) selected @endif>{{ $b->name }}</option>@endforeach
			</select>
		</form>
		@endif
	</div>

	<div id="lpList">
		@if(count($tasks) === 0)
		<div class="lp-card"><p class="lp-note m-0">No tiene conteos pendientes.</p></div>
		@endif
		@foreach($tasks as $t)
		<button type="button" class="lp-task" data-task="{{ json_encode(['id' => $t['id'], 'name' => $t['product_name'], 'serial' => intval($t['is_serialized']), 'recount' => $t['status'] === 'recount', 'control' => $t['control_type'], 'unit' => $t['unit_name']]) }}">
			<span>
				<strong>{{ $t['product_name'] }}</strong>
				<small>{{ $t['branch_name'] }} · {{ $t['category_name'] ?: 'Sensible' }} · antes de {{ substr($t['due_at'], 0, 16) }}</small>
			</span>
			@if($t['status'] === 'recount')
			<span class="lp-badge lp-warning"><i class="fa-solid fa-rotate"></i> Reconteo</span>
			@elseif($t['overdue'])
			<span class="lp-badge lp-danger"><i class="fa-solid fa-clock"></i> Vencido</span>
			@else
			<span class="lp-badge lp-success"><i class="fa-solid fa-circle-check"></i> A tiempo</span>
			@endif
		</button>
		@endforeach
	</div>

	<form id="lpCount" class="lp-card lp-count" hidden>
		<h2 id="lpCountTitle"></h2>
		<div id="lpRecountNote" hidden class="lp-badge lp-warning mb-2"><i class="fa-solid fa-rotate"></i> Cuente de nuevo sin mirar el conteo anterior</div>
		<label for="lpBarcode">Escanee el UPC</label>
		<input id="lpBarcode" class="lp-field" autocomplete="off" inputmode="text" required>
		<label for="lpQty">Cantidad contada <span id="lpUnit"></span></label>
		<input id="lpQty" class="lp-field" type="number" min="0" step="any" inputmode="decimal" required>
		<div id="lpLotBox" hidden>
			<label for="lpLot">Lote</label>
			<input id="lpLot" class="lp-field" autocomplete="off">
		</div>
		<div id="lpSerialBox" hidden>
			<label for="lpSerial">Escanee cada serie o IMEI</label>
			<input id="lpSerial" class="lp-field" autocomplete="off">
			<div class="lp-chips" id="lpSerials"></div>
		</div>
		<label for="lpNotes">Observación (opcional)</label>
		<input id="lpNotes" class="lp-field" autocomplete="off">
		<div class="lp-actions mt-3">
			<button class="lp-btn lp-btn-primary" type="submit">Confirmar conteo</button>
			<button class="lp-btn" type="button" id="lpCancel">Volver</button>
		</div>
	</form>
</div>

<script>
(function ()
{
	var $id = function (x) { return document.getElementById(x); };
	var list = $id('lpList'), form = $id('lpCount'), task = null, serials = [], op = null;
	var uuid = function () { return 'lp-' + Date.now() + '-' + Math.random().toString(36).slice(2); };
	function drawSerials()
	{
		$id('lpSerials').innerHTML = '';
		serials.forEach(function (s)
		{
			var c = document.createElement('span');
			c.className = 'lp-badge lp-success';
			c.textContent = s;
			$id('lpSerials').appendChild(c);
		});
		if (task && task.serial)
		{
			$id('lpQty').value = serials.length;
		}
	}
	function open(t)
	{
		task = t;
		serials = [];
		op = uuid();
		form.reset();
		drawSerials();
		$id('lpCountTitle').textContent = t.name;
		$id('lpRecountNote').hidden = !t.recount;
		$id('lpSerialBox').hidden = !t.serial;
		$id('lpQty').readOnly = !!t.serial;
		$id('lpLotBox').hidden = t.control !== 'lot';
		$id('lpUnit').textContent = t.unit ? '(' + t.unit + ')' : '';
		list.hidden = true;
		form.hidden = false;
		$id('lpBarcode').focus();
	}
	document.querySelectorAll('.lp-task').forEach(function (b)
	{
		b.addEventListener('click', function () { open(JSON.parse(b.dataset.task)); });
	});
	$id('lpCancel').addEventListener('click', function ()
	{
		form.hidden = true;
		list.hidden = false;
	});
	// El lector HID termina con Enter: pasa al siguiente campo sin enviar el formulario
	$id('lpBarcode').addEventListener('keydown', function (e)
	{
		if (e.key === 'Enter')
		{
			e.preventDefault();
			(task.serial ? $id('lpSerial') : $id('lpQty')).focus();
		}
	});
	$id('lpSerial').addEventListener('keydown', function (e)
	{
		if (e.key === 'Enter')
		{
			e.preventDefault();
			var v = this.value.trim();
			this.value = '';
			if (v && serials.indexOf(v) === -1)
			{
				serials.push(v);
				drawSerials();
			}
		}
	});
	form.addEventListener('submit', function (e)
	{
		e.preventDefault();
		Grocy.Api.Post('loss-control/tasks/' + task.id + '/count', { barcode: $id('lpBarcode').value, qty: $id('lpQty').value, serials: serials, lot: $id('lpLot').value, notes: $id('lpNotes').value, operation_id: op }, function (r)
		{
			toastr[r.recount_required ? 'warning' : 'success'](r.message);
			setTimeout(function () { location.reload(); }, 900);
		}, function (x)
		{
			toastr.error(JSON.parse(x.responseText || '{}').error_message || 'No se pudo registrar');
		});
	});
})();
</script>
@stop
