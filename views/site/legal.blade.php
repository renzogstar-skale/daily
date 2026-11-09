@extends('site.layout')

@section('content')
@include('site.partials.pagehero', ['title' => $doc === 'terms' ? $L('Términos de uso', 'Terms of use') : $L('Aviso de privacidad', 'Privacy notice'), 'crumbs' => [[$doc === 'terms' ? $L('Términos', 'Terms') : $L('Privacidad', 'Privacy'), null]], 'noCta' => true])
<section class="s-section s-section--tight">
	<div class="s-wrap s-prose">
		<p class="s-notice">{{ $L('Borrador pendiente de revisión jurídica. El texto definitivo debe ser aprobado antes de publicar el sitio.', 'Draft pending legal review. The final text must be approved before the site goes live.') }}</p>
		@if($doc === 'terms')
		<h2>{{ $L('Uso del sitio', 'Use of the site') }}</h2>
		<p>{{ $L('Este sitio presenta información comercial sobre DAILY. Las capturas muestran una empresa de demostración con datos ficticios.', 'This site presents commercial information about DAILY. Screenshots show a demo company with fictitious data.') }}</p>
		<h2>{{ $L('Estimaciones', 'Estimates') }}</h2>
		<p>{{ $L('La calculadora de rentabilidad entrega escenarios basados en los supuestos que ingresa el usuario. No constituye una garantía de resultados.', 'The ROI calculator provides scenarios based on the assumptions entered by the user. It is not a guarantee of results.') }}</p>
		<h2>{{ $L('Precios', 'Pricing') }}</h2>
		<p>{{ $L('Los planes y precios se confirman en una propuesta comercial formal.', 'Plans and prices are confirmed in a formal commercial proposal.') }}</p>
		@else
		<h2>{{ $L('Datos que recogemos', 'Data we collect') }}</h2>
		<p>{{ $L('Los formularios de demostración y contacto recogen nombre, empresa, correo, teléfono, país, industria, número de sucursales y mensaje.', 'The demo and contact forms collect name, company, email, phone, country, industry, number of branches and message.') }}</p>
		<h2>{{ $L('Finalidad', 'Purpose') }}</h2>
		<p>{{ $L('Usamos esos datos únicamente para responder tu solicitud y preparar una propuesta.', 'We use that data only to answer your request and prepare a proposal.') }}</p>
		<h2>{{ $L('Preferencias', 'Preferences') }}</h2>
		<p>{{ $L('El sitio guarda una cookie con el idioma elegido. No usa cookies de publicidad.', 'The site stores a cookie with your chosen language. It does not use advertising cookies.') }}</p>
		<h2>{{ $L('Tus derechos', 'Your rights') }}</h2>
		<p>{{ $L('Puedes pedir acceso, corrección o eliminación de tus datos mediante el formulario de contacto.', 'You can request access, correction or deletion of your data through the contact form.') }}</p>
		@endif
	</div>
</section>
@endsection
