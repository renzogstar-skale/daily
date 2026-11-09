@php
	$nav = fn ($p) => $path === $p || ($p !== '/' && str_starts_with($path, $p)) ? ' is-active' : '';
	$other = $lang === 'en' ? 'es' : 'en';
@endphp
<div class="s-topbar">
	<div class="s-wrap s-topbar__in">
		<span class="s-topbar__msg"><svg class="s-ico"><use href="#i-spark"/></svg>{{ $L('Plataforma para supermercados, retail y cadenas multisucursal', 'Platform for supermarkets, retail and multi-branch chains') }}</span>
		<nav class="s-topbar__links" aria-label="{{ $L('Accesos rápidos', 'Quick links') }}">
			<a href="{{ $U('/contact') }}">{{ $L('Atención comercial', 'Sales support') }}</a>
			<a href="{{ $U('/pricing') }}">{{ $L('Cotizar', 'Get a quote') }}</a>
			<a class="s-lang" href="?lang={{ $other }}" data-lang="{{ $other }}" hreflang="{{ $other }}" lang="{{ $other }}"><svg class="s-ico"><use href="#i-globe"/></svg>{{ $other === 'en' ? 'English' : 'Español' }}</a>
		</nav>
	</div>
</div>
<header class="s-header" id="top">
	<div class="s-wrap s-header__in">
		<a class="s-logo" href="{{ $U('/') }}" aria-label="DAILY — {{ $L('Inicio', 'Home') }}"><img src="{{ $U('/img/logo.svg') }}" alt="DAILY" width="122" height="32"></a>
		<nav class="s-nav" id="s-nav" aria-label="{{ $L('Principal', 'Main') }}">
			<ul class="s-nav__list">
				<li><a class="s-nav__link{{ $path === '/' ? ' is-active' : '' }}" href="{{ $U('/') }}">{{ $L('Inicio', 'Home') }}</a></li>
				<li><a class="s-nav__link{{ $nav('/pricing') }}" href="{{ $U('/pricing') }}">{{ $L('Precios', 'Pricing') }}</a></li>
				<li><a class="s-nav__link{{ $nav('/contact') }}" href="{{ $U('/contact') }}">{{ $L('Contacto', 'Contact') }}</a></li>
			</ul>
			<div class="s-nav__cta">
				<a class="s-btn s-btn--ghost" href="{{ $loginUrl }}" data-noprefetch>{{ $L('Iniciar sesión', 'Log in') }}</a>
				<a class="s-btn s-btn--primary" href="{{ $U('/demo') }}">{{ $L('Solicitar demostración', 'Request a demo') }}</a>
			</div>
		</nav>
		<button class="s-burger" type="button" aria-expanded="false" aria-controls="s-nav" aria-label="{{ $L('Abrir menú', 'Open menu') }}" data-label-close="{{ $L('Cerrar menú', 'Close menu') }}"><svg class="s-ico s-ico--lg"><use href="#i-menu"/></svg></button>
	</div>
</header>
