<div class="s-plans">
	@foreach($c['plans'] as $plan)
	<article class="s-plan{{ !empty($plan['featured']) ? ' is-featured' : '' }}" data-reveal>
		@if(!empty($plan['featured']))<span class="s-plan__flag">{{ $L('Recomendado para cadenas', 'Recommended for chains') }}</span>@endif
		<h3>{{ $plan['name'] }}</h3>
		<p class="s-plan__for">{{ $plan['for'] }}</p>
		<div class="s-plan__price">
			@if($plan['price'] === null)
			<strong>{{ $L('Precio por cotizar', 'Price on request') }}</strong>
			<small>{{ $L('Según sucursales y módulos', 'Based on branches and modules') }}</small>
			@else
			<strong>{{ $plan['price'] }}</strong>
			@endif
		</div>
		<ul class="s-checks">
			@foreach($plan['items'] as $item)
			<li><svg class="s-ico"><use href="#i-check"/></svg>{{ $item }}</li>
			@endforeach
		</ul>
		<a class="s-btn {{ !empty($plan['featured']) ? 's-btn--primary' : 's-btn--outline' }}" href="{{ $U('/demo') }}?plan={{ $plan['id'] }}">{{ $L('Solicitar cotización', 'Request a quote') }}</a>
	</article>
	@endforeach
</div>
