@extends('site.layout')

@section('content')
<section class="s-phero s-phero--form">
	<div class="s-hero__bg" aria-hidden="true"><i></i><i></i></div>
	<div class="s-wrap s-demo__grid">
		<div>
			<nav class="s-crumbs" aria-label="{{ $L('Ruta', 'Breadcrumb') }}"><a href="{{ $U('/') }}">{{ $L('Inicio', 'Home') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $L('Contacto', 'Contact') }}</span></nav>
			<span class="s-eyebrow">{{ $L('Contacto', 'Contact') }}</span>
			<h1>{{ $L('Hablemos', 'Let\'s talk') }}</h1>
			<p class="s-hero__sub">{{ $L('Escríbenos por consultas comerciales, alianzas o soporte para clientes.', 'Write to us about sales, partnerships or customer support.') }}</p>
			<div class="s-contact">
				<a href="{{ $U('/demo') }}"><span class="s-badge s-badge--green"><svg class="s-ico"><use href="#i-play"/></svg></span><span><strong>{{ $L('¿Quieres ver el producto?', 'Want to see the product?') }}</strong><small>{{ $L('Solicita una demostración guiada.', 'Request a guided demo.') }}</small></span></a>
				<a href="{{ $loginUrl }}" data-noprefetch><span class="s-badge s-badge--dark"><svg class="s-ico"><use href="#i-lock"/></svg></span><span><strong>{{ $L('¿Ya eres cliente?', 'Already a customer?') }}</strong><small>{{ $L('Ingresa a DAILY.', 'Sign in to DAILY.') }}</small></span></a>
			</div>
		</div>
		<div class="s-card s-card--form">
			<h2>{{ $L('Envíanos un mensaje', 'Send us a message') }}</h2>
			@include('site.partials.lead_form', ['type' => 'contact'])
		</div>
	</div>
</section>
@endsection
