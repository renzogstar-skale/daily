@php
	// Misma fuente en todos los módulos: SensitiveCaseService::Summary
	$lpSummary = null;
	if (\Grocy\Helpers\DairyAccess::CanOpen('/loss-prevention'))
	{
		try { $lpSummary = \Grocy\Services\SensitiveCaseService::GetInstance()->Summary(); } catch (\Throwable $e) { }
	}
@endphp
@if($lpSummary && ($lpSummary['open_cases'] > 0 || $lpSummary['pending_counts'] > 0))
<link href="{{ $U('/css/daily_loss_control.css?v=', true) }}{{ daily_asset_version('/css/daily_loss_control.css') }}" rel="stylesheet">
<div class="lp-strip" data-testid="loss-strip">
	<span class="lp-badge {{ $lpSummary['open_cases'] > 0 ? 'lp-warning' : 'lp-success' }}"><i class="fa-solid fa-shield-halved"></i> Prevención de Pérdidas</span>
	<span><strong>{{ $lpSummary['open_cases'] }}</strong> investigaciones abiertas</span>
	<span><strong>${{ number_format($lpSummary['open_value'], 2, '.', ',') }}</strong> en diferencias</span>
	<span><strong>{{ $lpSummary['pending_counts'] }}</strong> conteos pendientes</span>
	<a href="{{ $U('/loss-prevention') }}">Ver expedientes</a>
</div>
@endif
