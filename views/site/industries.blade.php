@extends('site.layout')

@section('content')
@include('site.partials.pagehero', ['title' => $L('DAILY para tu industria', 'DAILY for your industry'), 'eyebrow' => $L('Industrias', 'Industries'), 'sub' => $L('La misma plataforma, configurada para los problemas de cada tipo de operación.', 'The same platform, configured for the problems of each type of operation.'), 'crumbs' => [[$L('Industrias', 'Industries'), null]]])

<section class="s-section">
	<div class="s-wrap s-inds">
		@foreach($c['industries'] as $slug => $ind)
		<a class="s-ind" href="{{ $U('/industries/' . $slug) }}" data-reveal>
			<img src="{{ $U('/img/site/' . $ind['img'] . '.webp') }}" alt="" width="1600" height="900" loading="lazy" decoding="async">
			<div class="s-ind__body">
				<span class="s-badge s-badge--green"><svg class="s-ico"><use href="#i-{{ $ind['icon'] }}"/></svg></span>
				<h2>{{ $ind['name'] }}</h2>
				<p>{{ $ind['lead'] }}</p>
				<span class="s-link">{{ $L('Ver solución', 'See solution') }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></span>
			</div>
		</a>
		@endforeach
	</div>
</section>
@endsection
