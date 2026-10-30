@php
	// Fila estándar de 6 KPI (mismo tamaño en todos los dashboards).
	// Cada KPI: ['label', 'value', 'sub', 'icon', 'tone' => primary|success|danger|warning|info|purple|secondary, 'href' => opcional, 'alert' => bool]
	$dairyKpiTones = [
		'primary' => ['var(--dairy-primary-subtle)', 'var(--dairy-primary)'],
		'success' => ['var(--dairy-success-bg)', 'var(--dairy-success)'],
		'danger' => ['var(--dairy-danger-bg)', 'var(--dairy-danger)'],
		'warning' => ['var(--dairy-warning-bg)', 'var(--dairy-warning)'],
		'info' => ['var(--dairy-info-bg)', 'var(--dairy-info)'],
		'purple' => ['var(--dairy-purple-bg)', 'var(--dairy-purple)'],
		'secondary' => ['var(--dairy-border-subtle)', 'var(--dairy-text-secondary)'],
	];
@endphp
<div class="row dairy-kpi-row">
	@foreach($kpis as $kpi)
	@php $tone = $dairyKpiTones[$kpi['tone'] ?? 'primary'] ?? $dairyKpiTones['primary']; @endphp
	<div class="col-xl-2 col-md-4 col-6">
		@if(!empty($kpi['href']))<a href="{{ $kpi['href'] }}" class="dairy-kpi-link">@endif
		<div class="dairy-kpi-card dairy-kpi-uniform" style="--kpi-color: {{ $tone[1] }};">
			<div class="d-flex align-items-center mb-2">
				<div class="dairy-kpi-icon-box mr-2" style="background: {{ $tone[0] }};"><i class="fa-solid {{ $kpi['icon'] }}" style="color: {{ $tone[1] }};"></i></div>
				<div class="dairy-kpi-title mb-0" title="{{ $kpi['label'] }}">{{ $kpi['label'] }}</div>
			</div>
			<div class="dairy-kpi-value"@if(!empty($kpi['alert'])) style="color: {{ $tone[1] }};"@endif>{{ $kpi['value'] }}</div>
			<div class="dairy-kpi-footer"><span title="{{ $kpi['sub'] }}">{{ $kpi['sub'] }}</span></div>
		</div>
		@if(!empty($kpi['href']))</a>@endif
	</div>
	@endforeach
</div>
