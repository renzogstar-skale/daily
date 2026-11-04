<?php
$file = 'views/loss_case.blade.php';
$content = file_get_contents($file);

$p1 = strpos($content, 'function launchCaseResolverFlow()');
$p2 = strpos($content, 'function openHandoverModal()');

if ($p1 === false || $p2 === false) {
    echo "JS markers not found\n";
    exit(1);
}

$newJs = <<<'JS'
var cWizStep = {{ intval($d['wizard_step'] ?? 1) }};
var cWizSerials = {!! json_encode($d['serials']['scanned'] ?? []) !!};
var cWizState = {!! json_encode($d['wizard_state'] ?? []) !!};

var cWizStepNames = {
	1: 'Ver Problema', 2: 'Consultar Ubicación', 3: 'Contactar Responsable',
	4: 'Asignar Tarea', 5: 'Ir a la Zona', 6: 'Iniciar Inspección',
	7: 'Escanear Mercancía', 8: 'Revisar Movimientos', 9: 'Registrar Hallazgos',
	10: 'Adjuntar Evidencia', 11: 'Seleccionar Corrección & 5P', 12: 'Solicitar Aprobación',
	13: 'Verificar Resultado', 14: 'Cerrar Caso', 15: 'Crear Prevención Futura'
};

function launchCaseResolverFlow() {
	switchCaseWizStep(cWizStep || 1);
	document.getElementById('caseModalResolverWizard').hidden = false;
	restoreCaseWizInputs();
}

function switchCaseWizStep(step) {
	cWizStep = Math.max(1, Math.min(15, step));
	var badge = document.getElementById('cWizCurrentStepBadge');
	var name = document.getElementById('cWizCurrentStepName');
	if (badge) badge.textContent = cWizStep;
	if (name) name.textContent = cWizStepNames[cWizStep] || ('Paso ' + cWizStep);

	for (var i = 1; i <= 15; i++) {
		var p = document.getElementById('cWizPane' + i);
		var t = document.getElementById('cSt' + i);
		if (p) p.hidden = (i !== cWizStep);
		if (t) {
			t.classList.toggle('active', i === cWizStep);
			t.classList.toggle('done', i < cWizStep);
		}
	}
	var activeEl = document.getElementById('cSt' + cWizStep);
	if (activeEl && activeEl.scrollIntoView) {
		activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
	}
}

function saveCaseWizStep(step, andNext) {
	var stepData = collectStepData(step);
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/wizard-step', {
		step: step,
		data: stepData,
		notes: 'Paso ' + step + ': ' + (cWizStepNames[step] || '')
	}, function(res) {
		toastr.success('Progreso guardado (Paso ' + step + ')');
		if (andNext) {
			switchCaseWizStep(step + 1);
		}
	});
}

function pauseWizForNextShift() {
	var stepData = collectStepData(cWizStep);
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/wizard-step', {
		step: cWizStep,
		data: stepData,
		notes: 'Guardado para relevo en paso ' + cWizStep
	}, function() {
		toastr.info('Progreso guardado. El siguiente manager continuará desde el Paso ' + cWizStep);
		closeModal('caseModalResolverWizard');
	});
}

function collectStepData(step) {
	var data = {};
	if (step === 2) {
		data.location_notes = document.getElementById('cWizStep2LocationNotes') ? document.getElementById('cWizStep2LocationNotes').value : '';
	} else if (step === 3) {
		data.call_outcome = document.getElementById('cWizStep3CallOutcome') ? document.getElementById('cWizStep3CallOutcome').value : '';
		data.call_notes = document.getElementById('cWizStep3CallNote') ? document.getElementById('cWizStep3CallNote').value : '';
	} else if (step === 4) {
		data.task_title = document.getElementById('cWizTaskTitle') ? document.getElementById('cWizTaskTitle').value : '';
		data.task_assignee = document.getElementById('cWizTaskAssignee') ? document.getElementById('cWizTaskAssignee').value : '';
		data.task_priority = document.getElementById('cWizTaskPriority') ? document.getElementById('cWizTaskPriority').value : '';
		data.task_instructions = document.getElementById('cWizTaskInstructions') ? document.getElementById('cWizTaskInstructions').value : '';
	} else if (step === 7) {
		data.scanned_serials = cWizSerials;
		data.counted_qty = parseFloat(document.getElementById('cWizCountInput').value) || 0;
	} else if (step === 9) {
		data.finding_state = document.getElementById('cWizFindingState') ? document.getElementById('cWizFindingState').value : '';
		data.finding_details = document.getElementById('cWizFindingDetails') ? document.getElementById('cWizFindingDetails').value : '';
	} else if (step === 10) {
		data.cctv_ref = document.getElementById('cWizCctvRef') ? document.getElementById('cWizCctvRef').value : '';
		data.doc_ref = document.getElementById('cWizDocRef') ? document.getElementById('cWizDocRef').value : '';
		data.evidence_notes = document.getElementById('cWizEvidenceNotes') ? document.getElementById('cWizEvidenceNotes').value : '';
	} else if (step === 11) {
		data.cause_status = document.getElementById('cWizCauseStatus') ? document.getElementById('cWizCauseStatus').value : '';
		data.cause_class = document.getElementById('cWizCauseClass') ? document.getElementById('cWizCauseClass').value : '';
		data.five_whys = {
			why1: document.getElementById('cWizWhy1') ? document.getElementById('cWizWhy1').value : '',
			why2: document.getElementById('cWizWhy2') ? document.getElementById('cWizWhy2').value : '',
			why3: document.getElementById('cWizWhy3') ? document.getElementById('cWizWhy3').value : '',
			why4: document.getElementById('cWizWhy4') ? document.getElementById('cWizWhy4').value : '',
			why5: document.getElementById('cWizWhy5') ? document.getElementById('cWizWhy5').value : ''
		};
		Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/root-cause', {
			status: data.cause_status,
			confirmed_cause: data.cause_class,
			five_whys: data.five_whys
		}, function() {});
	} else if (step === 12) {
		data.approval_proposal = document.getElementById('cWizApprovalProposal') ? document.getElementById('cWizApprovalProposal').value : '';
		data.approval_justification = document.getElementById('cWizApprovalJustification') ? document.getElementById('cWizApprovalJustification').value : '';
	} else if (step === 14) {
		data.final_outcome = document.getElementById('cWizFinalOutcomeSelect') ? document.getElementById('cWizFinalOutcomeSelect').value : '';
		data.closing_note = document.getElementById('cWizFinalClosingNote') ? document.getElementById('cWizFinalClosingNote').value : '';
	}
	return data;
}

function restoreCaseWizInputs() {
	if (!cWizState) return;
	for (var key in cWizState) {
		var s = cWizState[key];
		if (!s || !s.data) continue;
		if (s.step === 2 && s.data.location_notes && document.getElementById('cWizStep2LocationNotes')) {
			document.getElementById('cWizStep2LocationNotes').value = s.data.location_notes;
		}
		if (s.step === 9 && s.data.finding_details && document.getElementById('cWizFindingDetails')) {
			document.getElementById('cWizFindingDetails').value = s.data.finding_details;
		}
		if (s.step === 11 && s.data.five_whys) {
			if (document.getElementById('cWizWhy1')) document.getElementById('cWizWhy1').value = s.data.five_whys.why1 || '';
			if (document.getElementById('cWizWhy2')) document.getElementById('cWizWhy2').value = s.data.five_whys.why2 || '';
			if (document.getElementById('cWizWhy3')) document.getElementById('cWizWhy3').value = s.data.five_whys.why3 || '';
			if (document.getElementById('cWizWhy4')) document.getElementById('cWizWhy4').value = s.data.five_whys.why4 || '';
			if (document.getElementById('cWizWhy5')) document.getElementById('cWizWhy5').value = s.data.five_whys.why5 || '';
		}
	}
}

function recordInSituCheckinDirect() {
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/checkin', { zone_name: '{{ addslashes($c['zone'] ?: 'Piso de Venta') }}' }, function() {
		document.getElementById('cWizCheckinDone').hidden = false;
		toastr.success('Llegada in-situ confirmada y estampada con georreferencia');
	});
}

function execCaseScan() {
	var code = document.getElementById('cWizScanInput').value.trim();
	if (!code) return;
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/scan-verify', { query: code, scanned_serials: cWizSerials }, function(res) {
		if (res.is_matching_product) {
			toastr.success('Código validado: ' + (res.scanned_product ? res.scanned_product.name : 'Correcto'));
			if (!cWizSerials.includes(code)) {
				cWizSerials.push(code);
			}
			renderCaseWizChips();
			document.getElementById('cWizScanInput').value = '';
			document.getElementById('cWizCountInput').value = cWizSerials.length;
			updateCaseLiveDiff();
		} else {
			toastr.warning('Código escaneado no coincide con el producto');
		}
	});
}

function renderCaseWizChips() {
	var box = document.getElementById('cWizChips');
	if (!box) return;
	box.innerHTML = '';
	cWizSerials.forEach(function(s) {
		var sp = document.createElement('span');
		sp.className = 'badge badge-success mr-1 mb-1 p-1 px-2 font-weight-bold';
		sp.innerHTML = '<i class="fa-solid fa-mobile-screen-button mr-1"></i> ' + s;
		box.appendChild(sp);
	});
}

function updateCaseLiveDiff() {
	var exp = {{ floatval($c['expected_stock']) }};
	var cur = parseFloat(document.getElementById('cWizCountInput').value) || 0;
	var d = cur - exp;
	var disp = document.getElementById('cWizDiffDisplay');
	if (disp) {
		if (Math.abs(d) < 0.0001) {
			disp.className = 'lp-diff-live matched';
			disp.textContent = 'CONCILIADO (0 u.)';
		} else if (d < 0) {
			disp.className = 'lp-diff-live has-gap';
			disp.textContent = 'Faltan ' + Math.abs(d) + ' u.';
		} else {
			disp.className = 'lp-diff-live matched';
			disp.textContent = 'Sobrante +' + d + ' u.';
		}
	}
}

function executeFullCaseClose() {
	var outcome = document.getElementById('cWizFinalOutcomeSelect').value;
	var reason = document.getElementById('cWizFinalClosingNote').value || 'Inspección física y reconciliación completada en 15 pasos.';
	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/close', { outcome: outcome, justification: reason }, function() {
		Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/financial-recovery', {
			pending_diff: 0,
			confirmed_loss: (outcome === 'resuelto' ? 0 : {{ floatval($c['financial_impact']) }}),
			recovered_merchandise: (outcome === 'resuelto' ? {{ floatval($c['financial_impact']) }} : 0),
			document_refs: ['EXP-{{ $c['case_code'] }}'],
			notes: 'Cierre formal ejecutado: ' + outcome
		}, function() {});

		toastr.success('Incidencia firmada y cerrada exitosamente');
		switchCaseWizStep(15);
	});
}

function executeCreatePreventivePlan() {
	var title = document.getElementById('cWizPlanTitle').value.trim();
	var owner = document.getElementById('cWizPlanOwner').value;
	var date = document.getElementById('cWizPlanTargetDate').value;
	var freq = document.getElementById('cWizPlanFrequency').value;

	if (!title) {
		toastr.warning('Indique el título del plan preventivo');
		return;
	}

	Grocy.Api.Post('loss-control/cases/{{ $c['id'] }}/preventive-plan', {
		title: title,
		responsible_user_id: owner,
		target_date: date,
		frequency_days: freq,
		plan_type: 'custody_chain',
		notes: 'Generado desde el Asistente de Resolución de la incidencia {{ $c['case_code'] }}'
	}, function() {
		toastr.success('Plan de acción preventivo activado permanentemente');
		closeModal('caseModalResolverWizard');
		setTimeout(function() { location.reload(); }, 800);
	});
}

JS;

$newContent = substr($content, 0, $p1) . $newJs . "\n" . substr($content, $p2);
file_put_contents($file, $newContent);
echo "Successfully updated loss_case.blade.php JS with 15-step wizard logic!\n";

