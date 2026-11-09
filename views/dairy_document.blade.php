<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	@include('components.daily_i18n_head')
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>{{ $doc['folio'] }} · {{ $doc['type_label'] }} | DAILY</title>
	<link href="{{ $U('/packages/bootstrap/dist/css/bootstrap.min.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/fontawesome.min.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="{{ $U('/packages/@fortawesome/fontawesome-free/css/solid.min.css?v=', true) }}{{ $version }}" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=fallback" rel="stylesheet">
	<link href="{{ $U('/css/dairy_enterprise_theme.css?v=', true) }}{{ daily_asset_version('/css/dairy_enterprise_theme.css') }}" rel="stylesheet">
	<style>
		body { background: var(--dairy-bg-page); color: var(--dairy-text-body); }
		.doc-sheet { max-width: 960px; margin: 24px auto; background: var(--dairy-surface); border: 1px solid var(--dairy-border); border-radius: var(--dairy-radius-md); padding: 32px; }
		.doc-head { border-bottom: 2px solid var(--dairy-primary); padding-bottom: 16px; margin-bottom: 20px; }
		.doc-type { font-size: var(--dairy-fs-xs); text-transform: uppercase; letter-spacing: .06em; color: var(--dairy-text-muted); font-weight: 600; }
		.doc-folio { font-size: var(--dairy-fs-2xl); font-weight: 700; color: var(--dairy-text-main); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
		.doc-label { font-size: var(--dairy-fs-2xs); text-transform: uppercase; color: var(--dairy-text-muted); font-weight: 600; letter-spacing: .04em; }
		.doc-value { font-size: var(--dairy-fs-md); color: var(--dairy-text-main); }
		.doc-table th { font-size: var(--dairy-fs-xs); color: var(--dairy-text-secondary); background: var(--dairy-bg-base); }
		.doc-table td { font-size: var(--dairy-fs-sm); white-space: pre-line; }
		.doc-sign { border-top: 1px solid var(--dairy-border-strong); padding-top: 6px; margin-top: 48px; font-size: var(--dairy-fs-xs); }
		@media (max-width: 575.98px) { .doc-sheet { margin: 0; border: 0; border-radius: 0; padding: 16px; } }
		@media print {
			body { background: #fff; }
			.doc-sheet { margin: 0; border: 0; padding: 0; max-width: none; }
			.doc-actions { display: none !important; }
			@page { margin: 14mm; }
		}
	</style>
</head>

<body>
	<div class="doc-actions d-flex justify-content-end flex-wrap mx-auto px-3 pt-3" style="max-width: 960px;">
		<a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mr-2"><i class="fa-solid fa-arrow-left mr-1"></i>Volver</a>
		<button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print mr-1"></i>Imprimir / PDF</button>
	</div>

	<div class="doc-sheet">
		<div class="doc-head d-flex justify-content-between align-items-start flex-wrap">
			<div>
				<div class="doc-type">{{ $doc['type_label'] }}</div>
				<div class="doc-folio">{{ $doc['folio'] }}</div>
			</div>
			<div class="text-right">
				<div class="font-weight-bold text-dark">DAILY ERP</div>
				<div class="doc-value">{{ $doc['date'] }}</div>
				<span class="badge badge-light border mt-1">{{ $doc['status'] }}</span>
			</div>
		</div>

		@include('components.dairy_process_steps', ['steps' => $doc['steps'], 'title' => 'Flujo del proceso'])

		<div class="row mb-3">
			@foreach($doc['parties'] as [$label, $value])
			<div class="col-sm-6 col-md-3 mb-2">
				<div class="doc-label">{{ $label }}</div>
				<div class="doc-value">{{ $value ?: '—' }}</div>
			</div>
			@endforeach
		</div>

		<div class="table-responsive">
			<table class="table table-sm doc-table">
				<thead>
					<tr>
						@foreach($doc['columns'] as $c => $col)
						<th class="{{ $c >= $doc['numeric_from'] ? 'text-right' : '' }}">{{ $col }}</th>
						@endforeach
					</tr>
				</thead>
				<tbody>
					@forelse($doc['rows'] as $row)
					<tr>
						@foreach($row as $c => $cell)
						<td class="{{ $c >= $doc['numeric_from'] ? 'text-right text-nowrap' : '' }}">{{ $cell }}</td>
						@endforeach
					</tr>
					@empty
					<tr><td colspan="{{ count($doc['columns']) }}" class="text-center text-muted">Sin líneas</td></tr>
					@endforelse
				</tbody>
				@if(!empty($doc['totals']))
				<tfoot>
					@foreach($doc['totals'] as [$label, $value])
					<tr>
						<td colspan="{{ count($doc['columns']) - 1 }}" class="text-right font-weight-600">{{ $label }}</td>
						<td class="text-right font-weight-bold text-nowrap">{{ $value }}</td>
					</tr>
					@endforeach
				</tfoot>
				@endif
			</table>
		</div>

		@if(!empty($doc['refs']))
		<div class="mb-3">
			<div class="doc-label mb-1">Trazabilidad</div>
			@foreach($doc['refs'] as $ref)
			<div class="doc-value small">
				<span class="text-muted">{{ $ref[0] }}:</span>
				@if(isset($ref[2]))
				<a href="{{ $U('/document/' . $ref[2][0] . '/' . $ref[2][1]) }}" class="text-monospace">{{ $ref[1] }}</a>
				@else
				<span class="text-monospace">{{ $ref[1] }}</span>
				@endif
			</div>
			@endforeach
		</div>
		@endif

		@if($doc['notes'])
		<div class="mb-3">
			<div class="doc-label mb-1">Observaciones</div>
			<div class="doc-value small" style="white-space: pre-line;">{{ $doc['notes'] }}</div>
		</div>
		@endif

		<div class="row">
			@foreach($doc['people'] as [$role, $name, $when])
			<div class="col-6 col-md-3">
				<div class="doc-sign">
					<div class="font-weight-600">{{ $name ?: '—' }}</div>
					<div class="text-muted">{{ $role }}@if($when) · {{ $when }}@endif</div>
				</div>
			</div>
			@endforeach
		</div>
	</div>
</body>

</html>
