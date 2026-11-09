/**
 * DAILY — Almacén y Bodega (WMS) Client Script
 */

document.addEventListener('DOMContentLoaded', () => {
	// 1. Manejo de Pestañas WMS
	const tabBtns = document.querySelectorAll('.wms-tab-btn');
	const tabPanels = document.querySelectorAll('.wms-tab-panel');

	function activateTab(tabId) {
		tabBtns.forEach(btn => {
			if (btn.getAttribute('data-tab') === tabId) {
				btn.classList.add('active');
			} else {
				btn.classList.remove('active');
			}
		});

		tabPanels.forEach(panel => {
			if (panel.id === 'tab-' + tabId) {
				panel.classList.add('active');
			} else {
				panel.classList.remove('active');
			}
		});

		if (history.pushState) {
			history.pushState(null, null, '#tab-' + tabId);
		}
	}

	tabBtns.forEach(btn => {
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const tabId = btn.getAttribute('data-tab');
			activateTab(tabId);
		});
	});

	// Activar por hash inicial
	const hash = window.location.hash.replace('#tab-', '').replace('#', '');
	const urlParams = new URLSearchParams(window.location.search);
	const tabFromUrl = urlParams.get('tab') || hash;
	if (tabFromUrl && document.getElementById('tab-' + tabFromUrl)) {
		activateTab(tabFromUrl);
	}

	// 2. Confirmación de Putaway (Ubicación en Rack)
	window.confirmPutawayTask = async function(taskId, defaultLocId) {
		const targetLoc = prompt('Confirma ID de Ubicación / Rack de destino (Enter para ubicación sugerida):', defaultLocId || '');
		if (targetLoc === null) return; // Cancelado

		try {
			const res = await fetch(U('/api/warehouse/putaway/' + taskId + '/confirm'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ location_id: targetLoc ? parseInt(targetLoc) : defaultLocId })
			});
			const data = await res.json();
			if (data.success) {
				alert('Ubicación (Putaway) confirmada con éxito. Inventario actualizado.');
				window.location.reload();
			} else {
				alert(data.error_message || 'Error al confirmar ubicación');
			}
		} catch (err) {
			console.error('Error confirming putaway:', err);
		}
	};

	// 3. Actualizar Estado de Muelle
	window.changeDockStatus = async function(dockId, newStatus) {
		try {
			const res = await fetch(U('/api/warehouse/dock/' + dockId + '/status'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ status: newStatus })
			});
			const data = await res.json();
			if (data.success) {
				window.location.reload();
			} else {
				alert('No fue posible actualizar el muelle');
			}
		} catch (err) {
			console.error('Error changing dock status:', err);
		}
	};

	// 4. Modal y Formulario de Nueva Inspección
	const inspectionModal = document.getElementById('wmsInspectionModal');
	const openInspModalBtn = document.getElementById('openInspModalBtn');
	const closeInspModalBtn = document.getElementById('closeInspModalBtn');
	const inspectionForm = document.getElementById('wmsInspectionForm');

	if (openInspModalBtn && inspectionModal) {
		openInspModalBtn.addEventListener('click', () => {
			inspectionModal.classList.add('active');
		});
	}

	if (closeInspModalBtn && inspectionModal) {
		closeInspModalBtn.addEventListener('click', () => {
			inspectionModal.classList.remove('active');
		});
	}

	if (inspectionModal) {
		inspectionModal.addEventListener('click', (e) => {
			if (e.target === inspectionModal) {
				inspectionModal.classList.remove('active');
			}
		});
	}

	if (inspectionForm) {
		inspectionForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const branchId = document.getElementById('inspBranchId').value;
			const poCode = document.getElementById('inspPoCode').value;
			const sku = document.getElementById('inspSku').value;
			const prodName = document.getElementById('inspProdName').value;
			const expectedQty = parseFloat(document.getElementById('inspExpectedQty').value);
			const receivedQty = parseFloat(document.getElementById('inspReceivedQty').value);
			const temp = document.getElementById('inspTemp').value ? parseFloat(document.getElementById('inspTemp').value) : null;
			const lot = document.getElementById('inspLot').value;
			const bbd = document.getElementById('inspBbd').value;
			const status = document.getElementById('inspStatus').value;
			const notes = document.getElementById('inspNotes').value;

			try {
				const res = await fetch(U('/api/warehouse/inspection'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						branch_id: branchId,
						po_code: poCode,
						product_id: 35, // Demo product ID
						sku: sku,
						product_name: prodName,
						expected_units: expectedQty,
						received_units: receivedQty,
						temp_celsius: temp,
						lot_number: lot,
						best_before_date: bbd,
						inspection_status: status,
						notes: notes
					})
				});
				const data = await res.json();
				if (data.success) {
					alert('Inspección registrada con éxito');
					window.location.reload();
				} else {
					alert(data.error_message || 'Error al guardar inspección');
				}
			} catch (err) {
				console.error('Error saving inspection:', err);
			}
		});
	}

	// 5. Búsqueda rápida de SKU/Lote en tabla de stock
	const wmsStockSearch = document.getElementById('wmsStockSearch');
	if (wmsStockSearch) {
		wmsStockSearch.addEventListener('input', (e) => {
			const q = e.target.value.toLowerCase().trim();
			const rows = document.querySelectorAll('#wmsStockTableBody tr');
			rows.forEach(r => {
				const txt = r.textContent.toLowerCase();
				r.style.display = (!q || txt.includes(q)) ? '' : 'none';
			});
		});
	}
});

