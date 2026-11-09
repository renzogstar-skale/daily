// Controles heredados de Grocy (Encendido / Sistema / Apagado y horario automático):
// ahora solo alimentan el tema global DailyTheme (daily_mode.js), que es la única fuente de verdad.
(function ()
{
	if (!window.DailyTheme)
	{
		return;
	}

	var toPref = { "on": "dark", "off": "light", "follow-system": "system" };
	var fromPref = { "dark": "on", "light": "off", "system": "follow-system" };

	function syncRadios()
	{
		$("input.user-setting-control:radio[name=night-mode][value=" + fromPref[DailyTheme.preference()] + "]").prop("checked", true);
	}

	$("input.user-setting-control:radio[name=night-mode]").on("change", function ()
	{
		var value = $("input.user-setting-control:radio[name=night-mode]:checked").val();
		if (toPref[value])
		{
			DailyTheme.set(toPref[value]);
		}
	});

	$("#auto-night-mode-enabled").on("change", function ()
	{
		var value = $(this).is(":checked");
		$("#auto-night-mode-time-range-from").prop("readonly", !value);
		$("#auto-night-mode-time-range-to").prop("readonly", !value);
		CheckNightMode();
	});

	$(document).on("keyup", "#auto-night-mode-time-range-from, #auto-night-mode-time-range-to", function ()
	{
		$(this).toggleClass("bg-danger", !moment($(this).val(), "HH:mm", true).isValid());
		CheckNightMode();
	});

	$("#auto-night-mode-time-range-goes-over-midgnight").on("change", function ()
	{
		CheckNightMode();
	});

	document.addEventListener("daily:modechange", syncRadios);

	// Horario automático: aplica Día/Noche según la hora sin cambiar la preferencia guardada
	function CheckNightMode()
	{
		if (Grocy.UserId === -1 || !BoolVal(Grocy.UserSettings.auto_night_mode_enabled))
		{
			return;
		}

		var start = moment(Grocy.UserSettings.auto_night_mode_time_range_from, "HH:mm", true);
		var end = moment(Grocy.UserSettings.auto_night_mode_time_range_to, "HH:mm", true);
		if (!start.isValid() || !end.isValid())
		{
			return;
		}
		if (BoolVal(Grocy.UserSettings.auto_night_mode_time_range_goes_over_midnight))
		{
			end.add(1, "day");
		}

		var wanted = moment().isBetween(start, end) ? "dark" : "light";
		if (DailyTheme.mode() !== wanted)
		{
			DailyTheme.set(wanted, { persist: false });
		}
	}
	window.CheckNightMode = CheckNightMode;

	if (Grocy.UserId !== -1)
	{
		syncRadios();
		$("#auto-night-mode-enabled").prop("checked", BoolVal(Grocy.UserSettings.auto_night_mode_enabled));
		$("#auto-night-mode-time-range-goes-over-midgnight").prop("checked", BoolVal(Grocy.UserSettings.auto_night_mode_time_range_goes_over_midnight));
		$("#auto-night-mode-time-range-from").val(Grocy.UserSettings.auto_night_mode_time_range_from).prop("readonly", !BoolVal(Grocy.UserSettings.auto_night_mode_enabled));
		$("#auto-night-mode-time-range-to").val(Grocy.UserSettings.auto_night_mode_time_range_to).prop("readonly", !BoolVal(Grocy.UserSettings.auto_night_mode_enabled));
	}

	setInterval(CheckNightMode, 60000);
	CheckNightMode();
})();
