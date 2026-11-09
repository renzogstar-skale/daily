/**
 * Suscripción DAILY en el navegador. La seguridad está en el backend (DairyPermissionMiddleware); aquí solo:
 * 1. Retirar del DOM las herramientas internas fuera del plan (el CSS de <head> ya las ocultó antes del pintado),
 *    para que atajos como $('#tab-x-link').click() no abran paneles vacíos.
 * 2. Convertir respuestas 403 con code=plan_required / subscription_inactive en un aviso claro con "Ver planes".
 * 3. Detectar un cambio de plan confirmado (al volver a la pestaña) y ofrecer actualizar sin cerrar sesión.
 */
(function()
{
	var state = window.DAILY_SUBSCRIPTION;
	if (!state)
	{
		return;
	}
	var base = (window.Grocy && Grocy.BaseUrl) ? Grocy.BaseUrl.replace(/\/$/, '') : '';
	var t = function(s) { return (window.DailyI18n && DailyI18n.t) ? DailyI18n.t(s) : s; };

	function removeLocked(root)
	{
		if (!state.lockedSelectors || !state.lockedSelectors.length)
		{
			return;
		}
		var sel = state.lockedSelectors.join(',');
		try
		{
			(root || document).querySelectorAll(sel).forEach(function(el)
			{
				// Los enlaces del menú lateral ya vienen filtrados desde el servidor
				if (!el.closest('#dairySidenav'))
				{
					el.remove();
				}
			});
		}
		catch (e) { }
	}

	var shown = {};
	function notify(data)
	{
		var key = data.code + ':' + (data.capability || '');
		if (shown[key])
		{
			return;
		}
		shown[key] = true;
		var msg = data.code === 'subscription_inactive'
			? t('La suscripción no está activa: DAILY quedó en modo consulta.')
			: t('Esta función requiere el plan') + ' ' + (data.required_plan_name || '') + '.';
		var link = ' <a href="' + base + '/subscription#planes" class="font-weight-bold">' + t('Ver planes') + '</a>';
		if (window.toastr)
		{
			toastr.info(msg + link, '', { escapeHtml: false, timeOut: 6000 });
		}
		else
		{
			console.info('[DAILY plan]', msg);
		}
	}

	function inspect(status, body)
	{
		if (status !== 403 || !body)
		{
			return false;
		}
		try
		{
			var data = typeof body === 'string' ? JSON.parse(body) : body;
			if (data && (data.code === 'plan_required' || data.code === 'subscription_inactive'))
			{
				notify(data);
				return true;
			}
		}
		catch (e) { }
		return false;
	}
	window.DailySubscription = {
		has: function(cap) { return state.capabilities.indexOf(cap) !== -1; },
		isPlanError: function(xhr) { return xhr && inspect(xhr.status, xhr.responseText); },
		state: state
	};

	if (window.jQuery)
	{
		jQuery(document).ajaxError(function(e, xhr) { inspect(xhr.status, xhr.responseText); });
	}
	if (window.fetch)
	{
		var origFetch = window.fetch;
		window.fetch = function()
		{
			return origFetch.apply(this, arguments).then(function(res)
			{
				if (res.status === 403)
				{
					res.clone().text().then(function(txt) { inspect(403, txt); }).catch(function() { });
				}
				return res;
			});
		};
	}
	// Grocy.Api usa XMLHttpRequest directo
	var origOpen = XMLHttpRequest.prototype.open;
	XMLHttpRequest.prototype.open = function()
	{
		this.addEventListener('load', function()
		{
			if (this.status === 403 && (this.responseType === '' || this.responseType === 'text'))
			{
				inspect(403, this.responseText);
			}
		});
		return origOpen.apply(this, arguments);
	};

	if (document.readyState === 'loading')
	{
		document.addEventListener('DOMContentLoaded', function() { removeLocked(); });
	}
	else
	{
		removeLocked();
	}
	// Contenido cargado después (pestañas perezosas, modales)
	if (window.MutationObserver && state.lockedSelectors && state.lockedSelectors.length)
	{
		var pending = false;
		new MutationObserver(function()
		{
			if (!pending)
			{
				pending = true;
				requestAnimationFrame(function() { pending = false; removeLocked(); });
			}
		}).observe(document.documentElement, { childList: true, subtree: true });
	}

	// Cambio de plan confirmado por el backend mientras la pestaña estaba abierta
	var lastCheck = Date.now();
	document.addEventListener('visibilitychange', function()
	{
		if (document.visibilityState !== 'visible' || Date.now() - lastCheck < 60000)
		{
			return;
		}
		lastCheck = Date.now();
		origFetch.call(window, base + '/api/subscription/capabilities', { credentials: 'same-origin' })
			.then(function(r) { return r.ok ? r.json() : null; })
			.then(function(now)
			{
				if (now && (now.plan !== state.plan || now.active !== state.active) && window.toastr)
				{
					toastr.success(t('Tu plan cambió a') + ' ' + now.planName + '. <a href="javascript:location.reload()" class="font-weight-bold">' + t('Actualizar') + '</a>', '', { escapeHtml: false, timeOut: 0, extendedTimeOut: 0 });
					state.plan = now.plan;
					state.active = now.active;
				}
			})
			.catch(function() { });
	});
})();
