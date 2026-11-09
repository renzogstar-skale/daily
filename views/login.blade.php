@extends('layout.default')

@section('title', $__t('Login'))

@push('pageStyles')
<link href="{{ $U('/css/daily_login.css?v=', true) }}{{ daily_asset_version('/css/daily_login.css') }}" rel="stylesheet">
<link rel="preload" as="image" type="image/webp"
	href="{{ $U('/img/daily_login/supermarket_hero_1x.webp') }}"
	imagesrcset="{{ $U('/img/daily_login/supermarket_hero_mob.webp') }} 480w,
	            {{ $U('/img/daily_login/supermarket_hero_1x.webp') }} 768w,
	            {{ $U('/img/daily_login/supermarket_hero_2k.webp') }} 1200w,
	            {{ $U('/img/daily_login/supermarket_hero_4k.webp') }} 2160w"
	imagesizes="(max-width: 767px) 0px, (max-width: 991px) 40vw, (min-width: 1920px) 520px, 490px"
	fetchpriority="high">
@endpush

@push('pageScripts')
<script src="{{ $U('/js/lucide.min.js?v=', true) }}{{ daily_asset_version('/js/lucide.min.js') }}"></script>
@endpush

@section('content')
@php
	$dailyLang = \Grocy\Helpers\DailyI18n::Lang();
@endphp

<!-- Canvas de fondo Liquid Glass con degradados verdes suaves -->
<div class="daily-login-canvas" aria-hidden="true">
	<!-- Ondas SVG con degradados fluidos esmeralda -->
	<svg class="daily-wave-svg" viewBox="0 0 1440 900" fill="none" preserveAspectRatio="none">
		<defs>
			<linearGradient id="waveGrad1" x1="0%" y1="0%" x2="100%" y2="80%">
				<stop offset="0%" stop-color="#059669" stop-opacity="0.45"/>
				<stop offset="40%" stop-color="#10b981" stop-opacity="0.28"/>
				<stop offset="85%" stop-color="#6ee7b7" stop-opacity="0.08"/>
				<stop offset="100%" stop-color="#a7f3d0" stop-opacity="0"/>
			</linearGradient>
			<linearGradient id="waveGrad2" x1="100%" y1="100%" x2="20%" y2="20%">
				<stop offset="0%" stop-color="#047857" stop-opacity="0.40"/>
				<stop offset="45%" stop-color="#10b981" stop-opacity="0.22"/>
				<stop offset="85%" stop-color="#34d399" stop-opacity="0.06"/>
				<stop offset="100%" stop-color="#ecfdf5" stop-opacity="0"/>
			</linearGradient>
			<linearGradient id="waveStroke1" x1="0%" y1="0%" x2="100%" y2="100%">
				<stop offset="0%" stop-color="#059669" stop-opacity="0.55"/>
				<stop offset="70%" stop-color="#10b981" stop-opacity="0.25"/>
				<stop offset="100%" stop-color="#34d399" stop-opacity="0"/>
			</linearGradient>
			<linearGradient id="waveStroke2" x1="100%" y1="100%" x2="0%" y2="0%">
				<stop offset="0%" stop-color="#059669" stop-opacity="0.50"/>
				<stop offset="65%" stop-color="#10b981" stop-opacity="0.20"/>
				<stop offset="100%" stop-color="#34d399" stop-opacity="0"/>
			</linearGradient>
		</defs>
		<!-- Cinta superior izquierda orgánica -->
		<path d="M -80 180 C 60 180 140 140 280 80 C 420 20 600 40 760 -40 L 760 -80 L -80 -80 Z" fill="url(#waveGrad1)" />
		<path d="M -80 230 C 80 230 180 180 340 110 C 500 40 680 50 820 -20" stroke="url(#waveStroke1)" stroke-width="2.5" fill="none" opacity="0.8" />
		<path d="M -80 270 C 100 270 210 210 390 140 C 560 70 730 70 880 0 L 880 -80 L -80 -80 Z" fill="url(#waveGrad1)" opacity="0.4" />
		<!-- Cinta inferior derecha orgánica -->
		<path d="M 640 980 C 820 900 960 820 1120 710 C 1280 600 1380 490 1520 400 L 1520 980 Z" fill="url(#waveGrad2)" />
		<path d="M 580 980 C 780 890 920 800 1090 690 C 1260 580 1370 470 1520 380" stroke="url(#waveStroke2)" stroke-width="2.5" fill="none" opacity="0.85" />
		<path d="M 740 980 C 900 920 1020 850 1180 750 C 1320 660 1420 570 1520 480 L 1520 980 Z" fill="url(#waveGrad2)" opacity="0.5" />
	</svg>
</div>

<!-- Estructura principal -->
<div class="daily-login-layout">

	<!-- 1. HEADER SUPERIOR: LOGO A LA IZQUIERDA, IDIOMA Y TEMA A LA DERECHA -->
	<header class="daily-login-header">
		<a href="{{ $U('/') }}" class="daily-login-brand-link" title="DAILY — Retail Operations Platform">
			<span class="daily-brand-badge">D</span>
			<span class="daily-brand-text">DAILY<span class="daily-brand-reg">®</span></span>
		</a>

		<div class="daily-login-header-tools">
			<!-- Selector de idioma español/inglés -->
			<div class="daily-pill-lang" id="dailyLoginLangPill" title="Cambiar idioma / Switch language">
				<i data-lucide="globe" class="globe-icon"></i>
				<button type="button" class="lang-btn @if($dailyLang === 'es') is-active @endif" data-daily-lang="es">ES</button>
				<span class="lang-sep">|</span>
				<button type="button" class="lang-btn @if($dailyLang === 'en') is-active @endif" data-daily-lang="en">EN</button>
			</div>

			<!-- Selector global de tema claro/oscuro -->
			<button type="button" class="daily-pill-theme" id="dailyLoginThemeToggle" title="Alternar tema claro / oscuro" aria-label="Cambiar tema">
				<span class="daily-pill-theme-thumb"></span>
				<span class="theme-btn-icon sun"><i data-lucide="sun"></i></span>
				<span class="theme-btn-icon moon"><i data-lucide="moon"></i></span>
			</button>
		</div>
	</header>

	<!-- 2. MAIN: TARJETA CENTRAL LIQUID GLASS -->
	<main class="daily-login-main">
		<article class="daily-glass-card" aria-label="Inicio de sesión en DAILY">

			<!-- Panel Izquierdo: Visual de Supermercado 4K Ultra HD con Glass Badges -->
			<div class="daily-card-left">
				<picture class="daily-supermarket-picture">
					<source type="image/webp"
						srcset="{{ $U('/img/daily_login/supermarket_hero_mob.webp') }} 480w,
						        {{ $U('/img/daily_login/supermarket_hero_1x.webp') }} 768w,
						        {{ $U('/img/daily_login/supermarket_hero_2k.webp') }} 1200w,
						        {{ $U('/img/daily_login/supermarket_hero_4k.webp') }} 2160w"
						sizes="(max-width: 767px) 0px, (max-width: 991px) 40vw, (min-width: 1920px) 520px, 490px">
					<source type="image/jpeg"
						srcset="{{ $U('/img/daily_login/supermarket_hero_mob.jpg') }} 480w,
						        {{ $U('/img/daily_login/supermarket_hero_1x.jpg') }} 768w,
						        {{ $U('/img/daily_login/supermarket_hero_2k.jpg') }} 1200w,
						        {{ $U('/img/daily_login/supermarket_hero_4k.jpg') }} 2160w"
						sizes="(max-width: 767px) 0px, (max-width: 991px) 40vw, (min-width: 1920px) 520px, 490px">
					<img src="{{ $U('/img/daily_login/supermarket_hero_1x.jpg') }}"
						alt="Supermercado DAILY Retail Operations — Vista de Operaciones"
						class="daily-supermarket-img"
						width="768"
						height="1376"
						loading="eager"
						fetchpriority="high"
						decoding="async"
						draggable="false">
				</picture>

				<!-- 4 Insignias Flotantes Liquid Glass (Glassmorphism Nativo 4K Vectorial) -->
				<div class="daily-hero-badge daily-badge-indicators" title="Indicadores de Rendimiento (DRI)" aria-hidden="true">
					<i data-lucide="bar-chart-3"></i>
				</div>
				<div class="daily-hero-badge daily-badge-inventory" title="Control y Rotación de Inventario" aria-hidden="true">
					<i data-lucide="package"></i>
				</div>
				<div class="daily-hero-badge daily-badge-staff" title="Gestión Eficiente del Personal" aria-hidden="true">
					<i data-lucide="users"></i>
				</div>
				<div class="daily-hero-badge daily-badge-prevention" title="Prevención de Pérdidas y Merma" aria-hidden="true">
					<i data-lucide="shield-check"></i>
				</div>
			</div>

			<!-- Panel Derecho: Formulario Profesional -->
			<div class="daily-card-right">

				<!-- Encabezado del Formulario -->
				<div class="daily-form-header">
					<div class="daily-form-logo-box">
						<span class="daily-form-badge">D</span>
						<span class="daily-form-logo-text">DAILY<span class="daily-brand-reg">®</span></span>
					</div>
					<div class="daily-form-subtitle-top">Retail Operations Platform</div>
					<h1 class="daily-form-title">Iniciar sesión</h1>
					<p class="daily-form-tagline">Gestión, Control y Beneficios para Retail</p>
				</div>

				<!-- Formulario Auténtico de Inicio de Sesión -->
				<form method="post"
					action="{{ $U('/login') }}"
					id="login-form"
					class="daily-auth-form"
					novalidate>

					<!-- Mensaje de error dinámico -->
					<div id="login-error" class="daily-login-alert d-none" role="alert">
						<i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
						<span id="login-error-text">Credenciales incorrectas, por favor intenta nuevamente</span>
					</div>

					<!-- Campo: Nombre de usuario -->
					<div class="daily-input-group">
						<label for="username" class="daily-input-label">Nombre de usuario</label>
						<div class="daily-input-wrap">
							<i data-lucide="user" class="daily-input-icon"></i>
							<input type="text"
								class="daily-text-input"
								required
								id="username"
								name="username"
								placeholder="Ingresa tu usuario"
								autocomplete="username"
								spellcheck="false"
								autocapitalize="none">
						</div>
					</div>

					<!-- Campo oculto password_base64 para autenticación nativa -->
					<input type="hidden"
						id="password_base64"
						name="password_base64">

					<!-- Campo: Contraseña con mostrar/ocultar -->
					<div class="daily-input-group">
						<label for="password_input" class="daily-input-label">Contraseña</label>
						<div class="daily-input-wrap">
							<i data-lucide="lock" class="daily-input-icon"></i>
							<input type="password"
								class="daily-text-input"
								required
								id="password_input"
								placeholder="Ingresa tu contraseña"
								autocomplete="current-password">
							<button type="button"
								class="daily-btn-toggle-pwd"
								id="togglePasswordBtn"
								title="Mostrar u ocultar contraseña"
								aria-label="Mostrar u ocultar contraseña">
								<i data-lucide="eye" id="pwdEyeIcon"></i>
							</button>
						</div>
					</div>

					<!-- Fila: Recordarme & ¿Olvidaste tu contraseña? -->
					<div class="daily-form-options-row">
						<label class="daily-checkbox-label" for="remember_me">
							<input type="checkbox"
								class="daily-custom-checkbox"
								id="remember_me"
								name="remember_me"
								checked>
							<span>Recordarme</span>
						</label>
						<a href="#recuperar-contrasena"
							class="daily-link-forgot"
							id="linkForgotPassword"
							onclick="openForgotPasswordModal(event);">¿Olvidaste tu contraseña?</a>
					</div>

					<!-- Botón principal de Envío -->
					<button type="submit"
						id="login-button"
						class="daily-btn-submit">
						<span id="loginBtnText">Iniciar sesión</span>
						<i data-lucide="arrow-right" id="loginBtnIcon"></i>
					</button>

					<!-- Divisor "o inicia con" -->
					<div class="daily-login-divider">
						<span>o inicia con</span>
					</div>

					<!-- Botones SSO Corporativos (Microsoft, Google, Apple) -->
					<div class="daily-sso-grid" role="group" aria-label="Proveedores de inicio de sesión SSO">
						<!-- Microsoft -->
						<button type="button"
							class="daily-btn-sso"
							data-sso-provider="microsoft"
							onclick="handleSsoClick('Microsoft');"
							title="Iniciar sesión corporativa con Microsoft">
							<svg class="daily-sso-icon" viewBox="0 0 21 21">
								<rect x="1" y="1" width="9" height="9" fill="#f25022"/>
								<rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
								<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
								<rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
							</svg>
							<span>Microsoft</span>
						</button>

						<!-- Google -->
						<button type="button"
							class="daily-btn-sso"
							data-sso-provider="google"
							onclick="handleSsoClick('Google');"
							title="Iniciar sesión corporativa con Google Workspace">
							<svg class="daily-sso-icon" viewBox="0 0 24 24">
								<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
								<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
								<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
								<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
							</svg>
							<span>Google</span>
						</button>

						<!-- Apple -->
						<button type="button"
							class="daily-btn-sso"
							data-sso-provider="apple"
							onclick="handleSsoClick('Apple');"
							title="Iniciar sesión con Apple ID">
							<svg class="daily-sso-icon" viewBox="0 0 170 170" fill="currentColor">
								<path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.7-3.08-7.7-7.85-12.01-14.3-6.53-9.79-11.75-20.9-15.65-33.34-3.9-12.43-5.86-24.16-5.86-35.19 0-14.5 3.75-26.68 11.24-36.56 7.5-9.87 16.92-14.86 28.27-14.98 4.91 0 10.37 1.25 16.38 3.74 6.01 2.49 9.8 3.8 11.38 3.93 1.34 0 5.42-1.42 12.24-4.27 6.82-2.85 12.78-4.14 17.89-3.87 13.25.64 23.82 5.69 31.7 15.15-11.55 6.99-17.18 16.48-16.9 28.47.28 9.5 3.97 17.5 11.08 24 7.11 6.5 15.42 10.15 24.94 10.96-2.12 6.53-4.66 13.25-7.61 20.16zM119.22 31.84c0-7.72 2.76-14.94 8.28-21.65 5.53-6.72 12.38-10.74 20.57-12.08.14 1.11.21 2.08.21 2.92 0 7.73-2.9 15.08-8.71 22.06-5.8 6.98-12.82 10.99-21.05 12.02-.38-.97-.58-2.07-.6-3.27z"/>
							</svg>
							<span>Apple</span>
						</button>
					</div>

				</form>

			</div>

		</article>
	</main>

	<!-- 3. FOOTER: BARRA INFERIOR DE CARACTERÍSTICAS / PILARES RETAIL -->
	<footer class="daily-login-footer" aria-label="Módulos de la plataforma">
		<div class="daily-footer-pill" title="Tablero de ventas, facturación comercial y punto de venta POS">
			<i data-lucide="store"></i>
			<span>Ventas</span>
		</div>
		<div class="daily-footer-sep"></div>
		<div class="daily-footer-pill" title="Control de stock en tiempo real, lotes FEFO y transferencias">
			<i data-lucide="package"></i>
			<span>Inventario</span>
		</div>
		<div class="daily-footer-sep"></div>
		<div class="daily-footer-pill" title="Gestión de turnos, asistencia y productividad en piso de tienda">
			<i data-lucide="users"></i>
			<span>Personal</span>
		</div>
		<div class="daily-footer-sep"></div>
		<div class="daily-footer-pill" title="Control de mermas, auditorías operativas y prevención de pérdidas">
			<i data-lucide="shield-check"></i>
			<span>Prevención</span>
		</div>
		<div class="daily-footer-sep"></div>
		<div class="daily-footer-pill" title="KPIs ejecutivos, márgenes y rendimiento departamental (DRI)">
			<i data-lucide="bar-chart-2"></i>
			<span>Indicadores</span>
		</div>
	</footer>

</div>

<!-- ==========================================================================
     MODAL 1: RECUPERACIÓN DE CONTRASEÑA
     ========================================================================== -->
<div class="modal fade daily-modal-glass" id="modalForgotPassword" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 440px;">
		<div class="modal-content">
			<div class="modal-header d-flex align-items-center justify-content-between">
				<div class="d-flex align-items-center gap-2">
					<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:rgba(16,185,129,0.12);color:#008736;">
						<i data-lucide="key-round" style="width:16px;height:16px;"></i>
					</span>
					<h5 class="modal-title font-weight-bold mb-0" style="font-size: 15px;">Recuperar contraseña</h5>
				</div>
				<button type="button" class="close text-muted" data-dismiss="modal" aria-label="Cerrar" style="outline:none;">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="text-muted mb-3" style="font-size: 12.5px; line-height: 1.5;">
					Ingresa tu nombre de usuario o correo corporativo registrado en DAILY. Te enviaremos instrucciones seguras o notificaremos al Administrador de TI de tu sucursal para restaurar tu acceso.
				</p>
				<form id="formRecoveryPassword" onsubmit="handleRecoverySubmit(event);">
					<div class="form-group mb-3">
						<label for="recoveryUserOrEmail" class="font-weight-600 mb-1" style="font-size: 12px; color: #334155;">Usuario o Correo corporativo</label>
						<input type="text"
							class="form-control"
							id="recoveryUserOrEmail"
							required
							placeholder="ej: usuario o maria@empresa.com"
							style="border-radius: 8px; font-size: 13px;">
					</div>
					<div id="recoverySuccessAlert" class="alert alert-success d-none mb-3 py-2 px-3" style="font-size: 12px;">
						<i data-lucide="check-circle" style="width:14px;height:14px;margin-right:4px;"></i>
						<span>Instrucciones enviadas. Si el usuario existe en el sistema, se ha emitido un enlace seguro o notificación de restablecimiento.</span>
					</div>
					<button type="submit" class="btn btn-block font-weight-bold" id="btnSubmitRecovery" style="background:#008736;color:#ffffff;border-radius:8px;font-size:13px;height:38px;">
						Enviar instrucciones
					</button>
				</form>
				<div class="mt-3 p-2 rounded bg-light" style="font-size: 11px; color: #64748b;">
					<i data-lucide="info" style="width:13px;height:13px;margin-right:4px;vertical-align:-2px;"></i>
					Para colaboradores en piso de tienda, el Supervisor o Manager de tienda puede restablecer la contraseña al instante desde el módulo de Personal (HCM).
				</div>
			</div>
		</div>
	</div>
</div>

<!-- ==========================================================================
     MODAL 2: ESTADO SSO CORPORATIVO (MICROSOFT / GOOGLE / APPLE)
     ========================================================================== -->
<div class="modal fade daily-modal-glass" id="modalSsoProvider" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 440px;">
		<div class="modal-content">
			<div class="modal-header d-flex align-items-center justify-content-between">
				<div class="d-flex align-items-center gap-2">
					<span id="ssoModalIconBox" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:6px;background:#f1f5f9;">
						<i data-lucide="shield" style="width:16px;height:16px;color:#008736;"></i>
					</span>
					<h5 class="modal-title font-weight-bold mb-0" id="ssoModalTitle" style="font-size: 15px;">Acceso Corporativo SSO</h5>
				</div>
				<button type="button" class="close text-muted" data-dismiss="modal" aria-label="Cerrar" style="outline:none;">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="text-muted mb-3" id="ssoModalDesc" style="font-size: 12.5px; line-height: 1.5;">
					El inicio de sesión mediante SSO empresarial requiere configurar el conector OAuth 2.0 / SAML correspondiente en esta instancia de DAILY.
				</p>
				<div class="p-3 rounded mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 12px; color: #475569;">
					<div class="font-weight-bold mb-1" id="ssoModalProviderName">Proveedor: Microsoft Entra ID</div>
					<div>Estado: <span class="badge badge-warning text-dark font-weight-normal px-2 py-1">No configurado en el servidor</span></div>
					<div class="mt-2 text-muted" style="font-size: 11px;">
						Define las credenciales en la configuración del servidor o utiliza tu usuario y contraseña locales de DAILY para ingresar de inmediato.
					</div>
				</div>
				<button type="button" class="btn btn-block font-weight-bold" data-dismiss="modal" style="background:#008736;color:#ffffff;border-radius:8px;font-size:13px;height:38px;">
					Continuar con usuario y contraseña
				</button>
			</div>
		</div>
	</div>
</div>
@stop
