/* Control de inventario: conteo ciego por stock fantasma y filtro de la matriz ABC-XYZ */
(function ($) {
	'use strict';
	var S = window.DairyInventoryControl || {};
	var UI = window.DairyUI;

	function schedule(ids, btn) {
		bootbox.confirm('Se programará un conteo ciego para hoy (el contador no verá el stock del sistema). ¿Continuar?', function (ok) {
			if (!ok) { return; }
			UI.post('inventory-control/counts', { branch_id: S.branchId, product_ids: ids }, function (r) { toastr.success(r.message); UI.reload(); }, btn);
		});
	}
	$('#ic-count-all').on('click', function () { schedule(S.pending, this); });
	$(document).on('click', '.ic-count', function () { schedule([$(this).data('product')], this); });

	function filter(cell) {
		$('#ic-items tbody tr[data-cell]').each(function () { $(this).toggle(!cell || $(this).data('cell') === cell); });
		$('.ic-cell').removeClass('table-active');
		if (cell) { $('.ic-cell[data-cell="' + cell + '"]').addClass('table-active'); }
		$('#ic-filter-label').text(cell ? '· clase ' + cell : '');
		$('#ic-clear').toggleClass('d-none', !cell);
		$('#ic-policy').html(cell ? '<strong>' + cell + ':</strong> ' + UI.esc(S.policies[cell]) : 'Seleccione una celda para ver la política recomendada.');
	}
	$(document).on('click keydown', '.ic-cell', function (e) {
		if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') { return; }
		e.preventDefault();
		filter($(this).data('cell'));
	});
	$('#ic-clear').on('click', function () { filter(null); });
})(jQuery);
