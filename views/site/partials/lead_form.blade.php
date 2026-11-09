@php $isDemo = ($type ?? 'demo') === 'demo'; @endphp
<form class="s-form" action="{{ $U('/site/lead') }}" method="post" novalidate data-lead-form
	data-msg-required="{{ $L('Este campo es obligatorio.', 'This field is required.') }}"
	data-msg-email="{{ $L('Escribe un correo válido.', 'Enter a valid email.') }}"
	data-msg-phone="{{ $L('Escribe un teléfono válido (7 a 25 dígitos).', 'Enter a valid phone (7 to 25 digits).') }}"
	data-msg-short="{{ $L('Escribe al menos 10 caracteres.', 'Write at least 10 characters.') }}"
	data-msg-sending="{{ $L('Enviando…', 'Sending…') }}"
	data-msg-ok="{{ $L('Recibimos tu solicitud. El equipo comercial te contactará al correo indicado.', 'We received your request. The sales team will contact you at the email provided.') }}"
	data-msg-error="{{ $L('No pudimos enviar la solicitud. Revisa tu conexión e inténtalo de nuevo.', 'We could not send your request. Check your connection and try again.') }}"
	data-msg-invalid="{{ $L('Revisa los campos marcados.', 'Please check the highlighted fields.') }}">
	<input type="hidden" name="type" value="{{ $isDemo ? 'demo' : 'contact' }}">
	<div class="s-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
	<div class="s-form__grid">
		<label class="s-field"><span>{{ $L('Nombre', 'Name') }} *</span><input name="name" required minlength="2" maxlength="120" autocomplete="name"><small class="s-err" aria-live="polite"></small></label>
		<label class="s-field"><span>{{ $L('Empresa', 'Company') }}{{ $isDemo ? ' *' : '' }}</span><input name="company" @if($isDemo) required minlength="2" @endif maxlength="160" autocomplete="organization"><small class="s-err" aria-live="polite"></small></label>
		<label class="s-field"><span>{{ $L('Correo', 'Email') }} *</span><input type="email" name="email" required maxlength="160" autocomplete="email"><small class="s-err" aria-live="polite"></small></label>
		<label class="s-field"><span>{{ $L('Teléfono', 'Phone') }}{{ $isDemo ? ' *' : '' }}</span><input type="tel" name="phone" @if($isDemo) required @endif pattern="[0-9+()\s.\-]{7,25}" maxlength="25" autocomplete="tel"><small class="s-err" aria-live="polite"></small></label>
		@if($isDemo)
		<label class="s-field"><span>{{ $L('País', 'Country') }} *</span>
			<select name="country" required autocomplete="country-name"><option value="">{{ $L('Selecciona…', 'Select…') }}</option>@foreach($c['countries'] as $country)<option>{{ $country }}</option>@endforeach</select><small class="s-err" aria-live="polite"></small></label>
		<label class="s-field"><span>{{ $L('Industria', 'Industry') }} *</span>
			<select name="industry" required><option value="">{{ $L('Selecciona…', 'Select…') }}</option>@foreach($c['industries'] as $slug => $ind)<option value="{{ $slug }}" @if(($preset ?? '') === $slug) selected @endif>{{ $ind['name'] }}</option>@endforeach<option value="other">{{ $L('Otra', 'Other') }}</option></select><small class="s-err" aria-live="polite"></small></label>
		<label class="s-field"><span>{{ $L('Número de sucursales', 'Number of branches') }} *</span>
			<select name="branches" required><option value="">{{ $L('Selecciona…', 'Select…') }}</option><option>1</option><option>2-5</option><option>6-20</option><option>21-50</option><option>50+</option></select><small class="s-err" aria-live="polite"></small></label>
		@endif
		<label class="s-field s-field--full"><span>{{ $L('Mensaje', 'Message') }}{{ $isDemo ? '' : ' *' }}</span><textarea name="message" rows="4" maxlength="3000" @if(!$isDemo) required minlength="10" @endif placeholder="{{ $isDemo ? $L('¿Qué procesos te gustaría ver en la demostración?', 'Which processes would you like to see in the demo?') : $L('¿En qué podemos ayudarte?', 'How can we help?') }}"></textarea><small class="s-err" aria-live="polite"></small></label>
	</div>
	<p class="s-form__privacy">{{ $L('Usaremos estos datos solo para responder tu solicitud.', 'We will only use this data to answer your request.') }} <a href="{{ $U('/privacy') }}">{{ $L('Aviso de privacidad', 'Privacy notice') }}</a></p>
	<div class="s-form__foot">
		<button class="s-btn s-btn--primary s-btn--lg" type="submit">{{ $isDemo ? $L('Solicitar demostración', 'Request a demo') : $L('Enviar mensaje', 'Send message') }}<svg class="s-ico"><use href="#i-arrow"/></svg></button>
		<p class="s-form__status" role="status" aria-live="polite"></p>
	</div>
</form>
