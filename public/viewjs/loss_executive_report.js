$(function ()
{
	var errMsg = function (xhr) { try { return JSON.parse(xhr.responseText).error_message; } catch (e) { return 'Error ' + xhr.status; } };

	$('#ler-generate').on('click', function ()
	{
		var btn = $(this).prop('disabled', true);
		Grocy.Api.Post('reports/loss-executive/generate', { period_type: btn.data('type') }, function (r)
		{
			if (r.status !== 'publicado')
			{
				toastr.error('La generación falló: ' + (r.error || ''));
				btn.prop('disabled', false);
				return;
			}
			window.location.href = U('/reports/loss-executive?type=' + btn.data('type') + '&id=' + r.id);
		}, function (xhr) { toastr.error(errMsg(xhr)); btn.prop('disabled', false); });
	});

	$('.ler-save-schedule').on('click', function ()
	{
		var row = $(this).closest('tr');
		Grocy.Api.Post('reports/loss-executive/schedule/' + row.data('type'), { run_time: row.find('.ler-time').val(), active: row.find('.ler-active').is(':checked') ? 1 : 0 },
			function () { toastr.success('Programación guardada'); window.location.reload(); },
			function (xhr) { toastr.error(errMsg(xhr)); });
	});

	$('#ler-nov-filter').on('click', 'button', function ()
	{
		var t = $(this).data('type');
		$('#ler-nov-filter button').removeClass('btn-primary').addClass('btn-outline-secondary');
		$(this).removeClass('btn-outline-secondary').addClass('btn-primary');
		$('#ler-novelties tbody tr[data-type]').each(function ()
		{
			var show = !t || $(this).data('type') === t;
			$(this).toggleClass('d-none', !show || (!t && $(this).hasClass('ler-more')));
		});
		$('#ler-show-more').toggle(!t);
	});
	$('#ler-show-more').on('click', function () { $('#ler-novelties .ler-more').removeClass('d-none ler-more'); $(this).remove(); });

	$('#ler-branches thead th[data-sort]').css('cursor', 'pointer').on('click', function ()
	{
		var idx = $(this).index(), num = $(this).data('sort') === 'num', dir = $(this).data('dir') === 'desc' ? 'asc' : 'desc';
		$(this).data('dir', dir);
		var rows = $('#ler-branches tbody tr').get().sort(function (a, b)
		{
			var x = $(a).children().eq(idx).data('v'), y = $(b).children().eq(idx).data('v');
			var c = num ? parseFloat(x) - parseFloat(y) : String(x).localeCompare(String(y));
			return dir === 'asc' ? c : -c;
		});
		$('#ler-branches tbody').append(rows);
	});
});
