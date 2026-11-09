@php $live = \Grocy\Helpers\DailySiteLive::Get($lang); @endphp
<link rel="stylesheet" href="{{ $A('/css/daily_site_live.css') }}">
<section class="s-section s-tour" id="tour" aria-labelledby="tour-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal>
			<span class="s-eyebrow"><svg class="s-ico"><use href="#i-play"/></svg>{{ $L('DAILY en acción', 'DAILY in action') }}</span>
			<h2 id="tour-title">{{ $L('Explora las pantallas reales de la plataforma', 'Explore the real screens of the platform') }}</h2>
			<p>{{ $L('Indicadores y gráficos tomados de la empresa de demostración, con datos ficticios.', 'KPIs and charts taken from the demo company, with fictitious data.') }}</p>
		</div>
		<div class="s-tour__tabs" role="tablist" aria-label="{{ $L('Módulos', 'Modules') }}">
			@foreach($c['tour'] as $i => $t)
			<button role="tab" id="tt-{{ $i }}" aria-controls="tp-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}">{{ $t['label'] }}</button>
			@endforeach
		</div>
		<div class="s-tour__tools">
			<button class="s-tour__auto" type="button" aria-pressed="false"><i aria-hidden="true"></i>{{ $L('Recorrido automático', 'Auto tour') }}</button>
		</div>
		<div class="s-tour__stage" data-tour>
			@foreach($c['tour'] as $i => $t)
			@php $m = $c['modules'][$t['key']]; @endphp
			<div class="s-tour__panel{{ $i === 0 ? ' is-active' : '' }}" role="tabpanel" id="tp-{{ $i }}" aria-labelledby="tt-{{ $i }}" @if($i !== 0) hidden @endif>
				<div class="s-tour__text">
					<span class="s-badge s-badge--{{ $m['tone'] }}"><svg class="s-ico"><use href="#i-{{ $m['icon'] }}"/></svg></span>
					<h3>{{ $t['title'] }}</h3>
					<p>{{ $t['text'] }}</p>
					<ul class="s-checks s-checks--sm">
						@foreach($t['points'] as $pt)
						<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $pt }}</li>
						@endforeach
					</ul>
					<a class="s-btn s-btn--primary" href="{{ $U($t['href'] ?? '/features/' . $t['key']) }}">{{ $L('Conocer el módulo', 'Explore the module') }}<svg class="s-ico"><use href="#i-arrow"/></svg></a>
				</div>
				@include('site.partials.live', ['key' => $t['preview'], 'board' => $live[$t['preview']], 'alt' => $L('Indicadores y gráficos de DAILY: ', 'DAILY KPIs and charts: ') . $t['title']])
			</div>
			@endforeach
		</div>
	</div>
</section>
<script src="{{ $A('/js/daily_site_live.js') }}" defer></script>
