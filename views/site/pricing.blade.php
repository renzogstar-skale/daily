@extends('site.layout')

@section('content')
@include('site.partials.pagehero', ['title' => $L('Planes que crecen con tu operación', 'Plans that grow with your operation'), 'eyebrow' => $L('Planes y precios', 'Plans & pricing'), 'sub' => $L('Cuatro planes según el tamaño y la complejidad de tu operación. El precio se cotiza según sucursales y módulos.', 'Four plans based on the size and complexity of your operation. Price is quoted by branches and modules.'), 'crumbs' => [[$L('Precios', 'Pricing'), null]], 'noCta' => true])

<section class="s-section s-section--tight">
	<div class="s-wrap">@include('site.partials.plans')</div>
</section>

<section class="s-section" id="compare" aria-labelledby="cmp-title">
	<div class="s-wrap">
		<div class="s-head" data-reveal><h2 id="cmp-title">{{ $L('Comparativa de funcionalidades', 'Feature comparison') }}</h2><p>{{ $L('Propuesta de empaquetado sujeta a la configuración comercial final.', 'Proposed packaging, subject to final commercial configuration.') }}</p></div>
		<div class="s-table" role="region" aria-labelledby="cmp-title" tabindex="0">
			<table>
				<thead><tr><th scope="col">{{ $L('Funcionalidad', 'Feature') }}</th>@foreach($c['plans'] as $p)<th scope="col" class="{{ !empty($p['featured']) ? 'is-featured' : '' }}">{{ $p['name'] }}</th>@endforeach</tr></thead>
				<tbody>
					@foreach($c['compare'] as $row => $vals)
					<tr><th scope="row">{{ $row }}</th>@foreach($vals as $i => $v)<td class="{{ !empty($c['plans'][$i]['featured']) ? 'is-featured' : '' }}">@if($v)<svg class="s-ico s-yes" role="img" aria-label="{{ $L('Incluido', 'Included') }}"><use href="#i-check"/></svg>@else<span class="s-no" aria-label="{{ $L('No incluido', 'Not included') }}">—</span>@endif</td>@endforeach</tr>
					@endforeach
					<tr class="s-table__cta"><th scope="row">{{ $L('Precio', 'Price') }}</th>@foreach($c['plans'] as $p)<td><a class="s-btn s-btn--sm {{ !empty($p['featured']) ? 's-btn--primary' : 's-btn--outline' }}" href="{{ $U('/demo') }}?plan={{ $p['id'] }}">{{ $L('Cotizar', 'Quote') }}</a></td>@endforeach</tr>
				</tbody>
			</table>
		</div>
	</div>
</section>

@include('site.partials.calculator')
@include('site.partials.faq')
@endsection
