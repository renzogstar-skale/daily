<footer class="s-footer">
	<div class="s-wrap">
		<div class="s-footer__cta">
			<div>
				<h2>{{ $L('¿DAILY encaja en tu operación?', 'Is DAILY a fit for your operation?') }}</h2>
				<p>{{ $L('Agenda una demostración con tus propios procesos y resuelve tus dudas con el equipo comercial.', 'Book a demo around your own processes and get your questions answered by the sales team.') }}</p>
			</div>
			<div class="s-actions">
				<a class="s-btn s-btn--primary s-btn--lg" href="{{ $U('/demo') }}">{{ $L('Solicitar una demo', 'Request a demo') }}</a>
				<a class="s-btn s-btn--light s-btn--lg" href="{{ $U('/pricing') }}">{{ $L('Ver planes', 'See plans') }}</a>
			</div>
		</div>
		<div class="s-footer__grid">
			<div class="s-footer__brand">
				<img src="{{ $U('/img/logo.svg') }}" alt="DAILY" width="122" height="32" loading="lazy">
				<p>{{ $L('Inteligencia y control para cada operación de tu negocio.', 'Intelligence and control for every operation in your business.') }}</p>
				<a class="s-footer__login" href="{{ $loginUrl }}" data-noprefetch><svg class="s-ico"><use href="#i-lock"/></svg>{{ $L('Acceso a DAILY', 'Sign in to DAILY') }}</a>
			</div>
			<div>
				<h3>{{ $L('Plataforma', 'Platform') }}</h3>
				<ul>
					@foreach($c['modules'] as $slug => $m)
					<li><a href="{{ $U('/features/' . $slug) }}">{{ $m['name'] }}</a></li>
					@endforeach
				</ul>
			</div>
			<div>
				<h3>{{ $L('Industrias', 'Industries') }}</h3>
				<ul>
					@foreach($c['industries'] as $slug => $ind)
					<li><a href="{{ $U('/industries/' . $slug) }}">{{ $ind['name'] }}</a></li>
					@endforeach
				</ul>
			</div>
			<div>
				<h3>{{ $L('Empresa', 'Company') }}</h3>
				<ul>
					<li><a href="{{ $U('/pricing') }}">{{ $L('Planes y precios', 'Plans & pricing') }}</a></li>
					<li><a href="{{ $U('/solutions') }}">{{ $L('Soluciones', 'Solutions') }}</a></li>
					<li><a href="{{ $U('/demo') }}">{{ $L('Solicitar demostración', 'Request a demo') }}</a></li>
					<li><a href="{{ $U('/contact') }}"><svg class="s-ico s-ico--sm"><use href="#i-mail"/></svg>{{ $L('Contacto', 'Contact') }}</a></li>
				</ul>
			</div>
		</div>
		<div class="s-footer__legal">
			<span>© {{ date('Y') }} DAILY. {{ $L('Todos los derechos reservados.', 'All rights reserved.') }}</span>
			<nav aria-label="Legal">
				<a href="{{ $U('/privacy') }}">{{ $L('Privacidad', 'Privacy') }}</a>
				<a href="{{ $U('/terms') }}">{{ $L('Términos', 'Terms') }}</a>
				<a href="?lang={{ $lang === 'en' ? 'es' : 'en' }}" data-lang="{{ $lang === 'en' ? 'es' : 'en' }}">{{ $lang === 'en' ? 'Español' : 'English' }}</a>
			</nav>
		</div>
	</div>
</footer>
