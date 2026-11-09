/**
 * DAILY — Recursos Humanos (HCM) Frontend Script
 */

document.addEventListener('DOMContentLoaded', () => {
	// 1. Manejo de pestañas
	const tabBtns = document.querySelectorAll('.hr-tab-btn');
	const tabPanels = document.querySelectorAll('.hr-tab-panel');

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

		// Actualizar hash sin salto brusco
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

	// Activar según hash inicial o parámetro URL
	const hash = window.location.hash.replace('#tab-', '').replace('#', '');
	const urlParams = new URLSearchParams(window.location.search);
	const tabFromUrl = urlParams.get('tab') || hash;
	if (tabFromUrl && document.getElementById('tab-' + tabFromUrl)) {
		activateTab(tabFromUrl);
	}

	// 2. Filtro en vivo de colaboradores
	const empSearchInput = document.getElementById('empSearchInput');
	const empDeptFilter = document.getElementById('empDeptFilter');
	const empTableBody = document.getElementById('empTableBody');

	function filterEmployees() {
		if (!empTableBody) return;
		const query = (empSearchInput ? empSearchInput.value : '').toLowerCase().trim();
		const dept = (empDeptFilter ? empDeptFilter.value : '').toLowerCase().trim();

		const rows = empTableBody.querySelectorAll('tr');
		rows.forEach(row => {
			const name = (row.getAttribute('data-name') || '').toLowerCase();
			const rowDept = (row.getAttribute('data-dept') || '').toLowerCase();
			const pos = (row.getAttribute('data-position') || '').toLowerCase();
			const code = (row.getAttribute('data-code') || '').toLowerCase();

			const matchesQuery = !query || name.includes(query) || pos.includes(query) || code.includes(query);
			const matchesDept = !dept || rowDept === dept || dept === 'all';

			if (matchesQuery && matchesDept) {
				row.style.display = '';
			} else {
				row.style.display = 'none';
			}
		});
	}

	if (empSearchInput) empSearchInput.addEventListener('input', filterEmployees);
	if (empDeptFilter) empDeptFilter.addEventListener('change', filterEmployees);

	// 3. Modal de Expediente de Colaborador
	const profileModal = document.getElementById('hrProfileModal');
	const closeProfileBtn = document.getElementById('closeProfileBtn');

	window.openEmployeeProfile = function(empData) {
		if (!profileModal) return;

		document.getElementById('profName').innerText = empData.name || 'Colaborador';
		document.getElementById('profCode').innerText = empData.payroll_code || 'EMP-0000';
		document.getElementById('profPosition').innerText = empData.position || 'Asociado';
		document.getElementById('profDept').innerText = empData.department || 'General';
		document.getElementById('profBranch').innerText = empData.branch_name || 'Sucursal Principal';
		document.getElementById('profSupervisor').innerText = empData.supervisor_name || 'No asignado';
		document.getElementById('profPhone').innerText = empData.phone || '+1 305 555 0100';
		document.getElementById('profSeniority').innerText = empData.seniority || '1a 2m';
		document.getElementById('profContract').innerText = empData.contract_type || 'Indefinido';
		document.getElementById('profHours').innerText = (empData.weekly_hours || 40) + ' hrs/semana';
		document.getElementById('profWage').innerText = '$' + Number(empData.hourly_cost || 16.5).toFixed(2) + ' /h';
		document.getElementById('profBurden').innerText = '$' + Number(empData.hourly_cost_burden || 20.13).toFixed(2) + ' /h (con cargas)';

		profileModal.classList.add('active');
	};

	if (closeProfileBtn) {
		closeProfileBtn.addEventListener('click', () => {
			profileModal.classList.remove('active');
		});
	}

	if (profileModal) {
		profileModal.addEventListener('click', (e) => {
			if (e.target === profileModal) {
				profileModal.classList.remove('active');
			}
		});
	}

	// 4. Cambiar Etapa de Candidato
	window.changeCandidateStage = async function(candId, newStage) {
		try {
			const res = await fetch(U('/api/hr/candidate/' + candId + '/stage'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ stage: newStage })
			});
			const data = await res.json();
			if (data.success) {
				window.location.reload();
			} else {
				alert('No fue posible actualizar la etapa del candidato');
			}
		} catch (err) {
			console.error('Error al actualizar etapa:', err);
		}
	};

	// 5. Toggle de tareas de Onboarding
	window.toggleOnboardingTask = async function(taskId, checkbox) {
		const completed = checkbox.checked;
		try {
			const res = await fetch(U('/api/hr/onboarding/' + taskId + '/toggle'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ completed: completed })
			});
			const data = await res.json();
			if (!data.success) {
				checkbox.checked = !completed;
				alert('Error al guardar el estado de la tarea');
			}
		} catch (err) {
			checkbox.checked = !completed;
			console.error('Error toggling onboarding:', err);
		}
	};

	// 6. Decidir solicitud (cambio de turno)
	window.decideRequest = async function(reqId, decision) {
		if (!confirm(`¿Confirmas ${decision === 'approved' ? 'APROBAR' : 'RECHAZAR'} esta solicitud?`)) {
			return;
		}

		try {
			const res = await fetch(U('/api/hr/request/' + reqId + '/decide'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ decision: decision })
			});
			const data = await res.json();
			if (data.success) {
				window.location.reload();
			} else {
				alert('Error al procesar la decisión');
			}
		} catch (err) {
			console.error('Error deciding request:', err);
		}
	};

	// 7. Publicar nuevo mensaje interno
	const newCommForm = document.getElementById('newCommForm');
	if (newCommForm) {
		newCommForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const title = document.getElementById('commTitle').value;
			const message = document.getElementById('commMsg').value;
			const priority = document.getElementById('commPriority').value;
			const branchId = document.getElementById('commBranchId') ? document.getElementById('commBranchId').value : 1;

			try {
				const res = await fetch(U('/api/hr/communication'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						title: title,
						message: message,
						priority: priority,
						branch_id: branchId
					})
				});
				const data = await res.json();
				if (data.success) {
					alert('Mensaje publicado con éxito');
					window.location.reload();
				} else {
					alert(data.error_message || 'Error al publicar mensaje');
				}
			} catch (err) {
				console.error('Error enviando comunicación:', err);
			}
		});
	}
});

