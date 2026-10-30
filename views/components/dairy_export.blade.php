{{-- Menú «Exportar» estándar: CSV de las tablas visibles (pestaña activa) e impresión. Opcional: $exportName, $exportScope (selector CSS). --}}
<div class="btn-group dairy-export">
	<button type="button" class="btn btn-light bg-white border dropdown-toggle shadow-xs" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<i class="fa-solid fa-download text-muted mr-1"></i>Exportar
	</button>
	<div class="dropdown-menu dropdown-menu-right shadow-sm border-0">
		<a class="dropdown-item" href="#" onclick="DairyUI.exportTables('csv', {{ json_encode($exportScope ?? null) }}, {{ json_encode($exportName ?? null) }}); return false;">
			<i class="fa-solid fa-file-csv text-success mr-2"></i>Vista actual (CSV / Excel)
		</a>
		<a class="dropdown-item" href="#" onclick="DairyUI.exportTables('print', {{ json_encode($exportScope ?? null) }}); return false;">
			<i class="fa-solid fa-print text-muted mr-2"></i>Imprimir / PDF
		</a>
	</div>
</div>
