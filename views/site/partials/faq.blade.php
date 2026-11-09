<section class="s-section s-faq" id="faq" aria-labelledby="faq-title">
	<div class="s-wrap s-faq__grid">
		<div data-reveal>
			<span class="s-eyebrow">{{ $L('Preguntas frecuentes', 'FAQ') }}</span>
			<h2 id="faq-title">{{ $L('Lo que suelen preguntarnos', 'What people usually ask') }}</h2>
			<p>{{ $L('¿No encuentras tu respuesta?', 'Can\'t find your answer?') }} <a class="s-link" href="{{ $U('/contact') }}">{{ $L('Escríbenos', 'Write to us') }}<svg class="s-ico s-ico--sm"><use href="#i-arrow"/></svg></a></p>
		</div>
		<div class="s-faq__list" data-reveal>
			@foreach($c['faq'] as [$q, $a])
			<details class="s-faq__item">
				<summary>{{ $q }}<svg class="s-ico"><use href="#i-down"/></svg></summary>
				<p>{{ $a }}</p>
			</details>
			@endforeach
		</div>
	</div>
</section>
