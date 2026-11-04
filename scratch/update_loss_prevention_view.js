const fs = require('fs');
let html = fs.readFileSync('views/loss_prevention.blade.php', 'utf8');

// 1. Update lp-case-code to include link
const oldRowCode = '<td class="lp-case-code">{{ $c[\'case_code\'] }}</td>';
const newRowCode = '<td class="lp-case-code"><a href="{{ $U(\'/loss-prevention/case/\' . $c[\'id\']) }}" class="font-weight-bold" style="color:#0f172a; text-decoration:none;" title="Abrir expediente 360°" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">{{ $c[\'case_code\'] }} <i class="fa-solid fa-arrow-up-right-from-square ml-1" style="font-size:0.68rem; color:#64748b;"></i></a></td>';

if (html.includes(oldRowCode)) {
    html = html.replace(oldRowCode, newRowCode);
    console.log('Updated lp-case-code with direct dossier links');
} else {
    console.log('oldRowCode not found verbatim');
}

// 2. Add drawerFullCaseLink in the slideover drawer
const drawerNeedle = '<div class="small text-muted" id="drawerCustody">\n\t\t\t\t\t\t<i class="fa-solid fa-shield mr-1 text-success"></i> Custodia asignada: <strong>rgiovine</strong> (Gerente / Administrador)\n\t\t\t\t\t</div>\n\t\t\t\t</div>';
const drawerReplacement = `<div class="small text-muted" id="drawerCustody">
						<i class="fa-solid fa-shield mr-1 text-success"></i> Custodia asignada: <strong>rgiovine</strong> (Gerente / Administrador)
					</div>
				</div>

				<!-- Acceso directo al Expediente Completo (15 Pasos) -->
				<div class="mt-2 mb-2">
					<a href="#" id="drawerFullCaseLink" class="btn btn-sm btn-block text-white font-weight-bold shadow-sm" style="background:#16a34a; border-radius:8px; height:38px; display:flex; align-items:center; justify-content:center; gap:8px;">
						<i class="fa-solid fa-folder-open"></i> Abrir Expediente Central 360° (15 Pasos)
					</a>
				</div>`;

if (html.includes(drawerNeedle)) {
    html = html.replace(drawerNeedle, drawerReplacement);
    console.log('Added drawerFullCaseLink button to slideover drawer');
} else {
    console.log('drawerNeedle not found verbatim, checking normalized whitespace');
}

// 3. Update openResolveDrawer in JS to set drawerFullCaseLink href
const jsDrawerNeedle = "document.getElementById('drawerCustody').innerHTML = '<i class=\"fa-solid fa-shield mr-1 text-success\"></i> Custodia asignada: <strong>' + (caseData.responsible || 'rgiovine') + '</strong>';";
const jsDrawerReplacement = `document.getElementById('drawerCustody').innerHTML = '<i class="fa-solid fa-shield mr-1 text-success"></i> Custodia asignada: <strong>' + (caseData.responsible || 'rgiovine') + '</strong>';
	var fullCaseLink = document.getElementById('drawerFullCaseLink');
	if (fullCaseLink) {
		fullCaseLink.href = '{{ $U('/loss-prevention/case/') }}' + caseData.id;
	}`;

if (html.includes(jsDrawerNeedle)) {
    html = html.replace(jsDrawerNeedle, jsDrawerReplacement);
    console.log('Updated openResolveDrawer to set fullCaseLink');
}

// 4. Update KPI card links and add filterCasesTable
const kpi1Old = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="switchLpTab(\'expedientes\')">';
const kpi1New = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable(\'all\'); return false;" title="Ver todos los incidentes">';
html = html.replace(kpi1Old, kpi1New);

const kpi3Old = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="switchLpTab(\'expedientes\')">';
const kpi3New = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable(\'differences\'); return false;" title="Filtrar incidencias con diferencias de conteo">';
html = html.replace(kpi3Old, kpi3New);

const kpi4Old = '<a href="#tab-expedientes" class="lp-kpi-card">';
const kpi4New = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable(\'theft\'); return false;" title="Filtrar casos de alta criticidad / robo">';
html = html.replace(kpi4Old, kpi4New);

const kpi5Old = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="switchLpTab(\'expedientes\')">';
const kpi5New = '<a href="#tab-expedientes" class="lp-kpi-card" onclick="filterCasesTable(\'investigacion\'); return false;" title="Filtrar casos en investigación">';
html = html.replace(kpi5Old, kpi5New);

// 5. Inject filterCasesTable into script
const filterCasesJs = `
function filterCasesTable(criteria) {
	switchLpTab('expedientes');
	var rows = document.querySelectorAll('.lp-cases-table tbody tr');
	var matchCount = 0;
	rows.forEach(function(r) {
		var show = true;
		var diffEl = r.querySelector('.lp-diff-red');
		var diffText = diffEl ? diffEl.textContent.trim() : '';
		var statusEl = r.querySelector('.lp-badge-status');
		var statusText = statusEl ? statusEl.textContent.trim().toLowerCase() : '';
		var prioEl = r.querySelector('.lp-badge-priority');
		var prioText = prioEl ? prioEl.textContent.trim().toLowerCase() : '';

		if (criteria === 'all') {
			show = true;
		} else if (criteria === 'differences') {
			show = diffText.indexOf('-') !== -1;
		} else if (criteria === 'theft') {
			show = prioText.indexOf('alta') !== -1 || prioText.indexOf('media') !== -1;
		} else if (criteria === 'investigacion') {
			show = statusText.indexOf('investigaci') !== -1;
		} else if (criteria === 'reconteo') {
			show = statusText.indexOf('reconteo') !== -1;
		}

		r.style.display = show ? '' : 'none';
		if (show) matchCount++;
	});

	var titleP = document.querySelector('.lp-table-title-group p');
	if (titleP) {
		var label = criteria === 'all' ? 'Todos los casos' :
					criteria === 'differences' ? 'Casos con Diferencia de Conteo' :
					criteria === 'theft' ? 'Pérdidas Críticas / Estimadas' :
					criteria === 'investigacion' ? 'Expedientes En Investigación' : criteria;
		titleP.innerHTML = 'Filtro activo: <span class="badge badge-warning text-dark font-weight-bold">' + label + '</span> (' + matchCount + ' casos) · <a href="#" onclick="filterCasesTable(\\'all\\'); return false;" style="color:#16a34a; font-weight:bold; text-decoration:underline;">Restablecer filtro</a>';
	}
}
`;

const endScriptIdx = html.lastIndexOf('</script>');
if (endScriptIdx !== -1) {
    html = html.substring(0, endScriptIdx) + '\n' + filterCasesJs + '\n' + html.substring(endScriptIdx);
    console.log('Injected filterCasesTable into loss_prevention script');
}

fs.writeFileSync('views/loss_prevention.blade.php', html, 'utf8');
console.log('loss_prevention.blade.php successfully updated!');

