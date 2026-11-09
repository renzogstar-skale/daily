/**
 * DAILY — MOTOR DE INTERACTIVIDAD DE INICIO DE SESIÓN
 * Autenticación segura, mostrar/ocultar contraseña, conmutador de tema, i18n y SSO
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initIcons();
		initTheme();
		initFocus();
		initErrorHandling();
		initPasswordToggle();
		initFormSubmit();
	});

	function initIcons() {
		if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
			lucide.createIcons();
		}
	}

	function initFocus() {
		setTimeout(function () {
			var u = document.getElementById('username');
			if (u) {
				u.focus();
			}
		}, (window.Grocy && Grocy.FormFocusDelay) ? Grocy.FormFocusDelay : 200);
	}

	function initErrorHandling() {
		if (typeof GetUriParam === 'function' && GetUriParam('invalid') === 'true') {
			var err = document.getElementById('login-error');
			var errTxt = document.getElementById('login-error-text');
			if (err) {
				if (errTxt) {
					errTxt.textContent = (typeof __t === 'function') ? __t('Invalid credentials, please try again') : 'Credenciales incorrectas, por favor intenta nuevamente';
				}
				err.classList.remove('d-none');
				var pwd = document.getElementById('password_input');
				if (pwd) {
					pwd.focus();
				}
			}
		}
	}

	function initPasswordToggle() {
		var btn = document.getElementById('togglePasswordBtn');
		var input = document.getElementById('password_input');
		if (!btn || !input) return;

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var isPwd = input.getAttribute('type') === 'password';
			input.setAttribute('type', isPwd ? 'text' : 'password');

			btn.innerHTML = isPwd
				? '<i data-lucide="eye-off" id="pwdEyeIcon"></i>'
				: '<i data-lucide="eye" id="pwdEyeIcon"></i>';

			initIcons();
		});
	}

	function initFormSubmit() {
		var btn = document.getElementById('login-button');
		var form = document.getElementById('login-form');
		var userInp = document.getElementById('username');
		var pwdInp = document.getElementById('password_input');
		var pwdB64 = document.getElementById('password_base64');
		var errDiv = document.getElementById('login-error');
		var errTxt = document.getElementById('login-error-text');

		if (!btn || !form) return;

		btn.addEventListener('click', function (e) {
			e.preventDefault();

			var uVal = (userInp ? userInp.value : '').trim();
			var pVal = (pwdInp ? pwdInp.value : '');

			if (!uVal || !pVal) {
				if (errDiv) {
					if (errTxt) {
						errTxt.textContent = 'Por favor ingresa tu nombre de usuario y contraseña.';
					}
					errDiv.classList.remove('d-none');
				}
				if (!uVal && userInp) userInp.focus();
				else if (!pVal && pwdInp) pwdInp.focus();
				return;
			}

			// Indicador de carga activo
			btn.disabled = true;
			var btnTxt = document.getElementById('loginBtnText');
			var btnIcn = document.getElementById('loginBtnIcon');
			if (btnTxt) btnTxt.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true" style="width:14px;height:14px;vertical-align:-2px;"></span> Verificando...';
			if (btnIcn) btnIcn.style.display = 'none';

			// Codificación base64 segura UTF-8 nativa de Grocy
			try {
				if (pwdB64) {
					pwdB64.value = btoa(String.fromCharCode(...new TextEncoder().encode(pVal)));
				}
			} catch (err) {
				if (pwdB64) {
					pwdB64.value = btoa(pVal);
				}
			}

			form.submit();
		});
	}

	/* ==========================================================================
	   CONMUTADOR DE TEMA CLARO / OSCURO
	   ========================================================================== */
	function initTheme() {
		var toggle = document.getElementById('dailyLoginThemeToggle');
		if (!toggle) return;

		// Mismo tema global que el resto de DAILY (DailyTheme, daily_mode.js)
		var sync = function () { toggle.classList.toggle('is-dark', window.DailyTheme ? DailyTheme.mode() === 'dark' : false); };
		sync();
		document.addEventListener('daily:modechange', sync);

		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			if (window.DailyTheme) DailyTheme.toggle();
		});
	}

	/* ==========================================================================
	   RECUPERACIÓN DE CONTRASEÑA
	   ========================================================================== */
	window.openForgotPasswordModal = function (e) {
		if (e && e.preventDefault) e.preventDefault();
		var alertBox = document.getElementById('recoverySuccessAlert');
		if (alertBox) alertBox.classList.add('d-none');
		var inp = document.getElementById('recoveryUserOrEmail');
		if (inp) {
			var curUser = document.getElementById('username');
			inp.value = curUser ? curUser.value : '';
		}
		if (window.$ && typeof $('#modalForgotPassword').modal === 'function') {
			$('#modalForgotPassword').modal('show');
		}
	};

	window.handleRecoverySubmit = function (e) {
		if (e && e.preventDefault) e.preventDefault();
		var inp = document.getElementById('recoveryUserOrEmail');
		var alertBox = document.getElementById('recoverySuccessAlert');
		var btn = document.getElementById('btnSubmitRecovery');

		if (!inp || !inp.value.trim()) return;

		if (btn) btn.disabled = true;

		setTimeout(function () {
			if (alertBox) alertBox.classList.remove('d-none');
			if (btn) btn.disabled = false;
			initIcons();
		}, 400);
	};

	/* ==========================================================================
	   AUTENTICACIÓN SSO CORPORATIVA (MICROSOFT, GOOGLE, APPLE)
	   ========================================================================== */
	window.handleSsoClick = function (provider) {
		// Verifica si existe configuración de SSO activa
		var ssoConfig = window.DAILY_SSO_CONFIG || {};
		var targetUrl = ssoConfig[provider.toLowerCase()] || null;

		if (targetUrl) {
			window.location.href = targetUrl;
			return;
		}

		// Modal informativo institucional
		var titleEl = document.getElementById('ssoModalTitle');
		var provNameEl = document.getElementById('ssoModalProviderName');
		var descEl = document.getElementById('ssoModalDesc');

		var formalNames = {
			'Microsoft': 'Microsoft Entra ID (Azure AD)',
			'Google': 'Google Workspace Enterprise',
			'Apple': 'Apple Business Manager'
		};

		if (titleEl) titleEl.textContent = 'Acceso Corporativo ' + provider;
		if (provNameEl) provNameEl.textContent = 'Proveedor: ' + (formalNames[provider] || provider);
		if (descEl) {
			descEl.textContent = 'El inicio de sesión mediante SSO empresarial para ' + provider +
				' requiere configurar el conector OAuth 2.0 / SAML correspondiente en esta instancia de DAILY.';
		}

		if (window.$ && typeof $('#modalSsoProvider').modal === 'function') {
			$('#modalSsoProvider').modal('show');
		}
	};

})();
