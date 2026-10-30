const fs = require('fs');
let file = fs.readFileSync('public/js/dairy_m.js', 'utf8');

// Replace visible text in guide / assistant
file = file.replace('disponibles en DAIRY;', 'disponibles en DAILY;');
file = file.replace('Guía de uso de DAIRY.', 'Guía de uso de DAILY.');

// Update PROFILE.settings
const oldSettings = `PROFILE.settings = async function () {
		setTitle('Preferencias'); crumbs([['Perfil', '#/profile'], ['Preferencias', location.hash]]);
		var nd = S.notif || await get('/m/notifications', { branch_id: S.branch }, { fresh: true });
		var perm = 'Notification' in window ? Notification.permission : 'unsupported';
		var html = section('Avisos en la app')`;

const newSettings = `PROFILE.settings = async function () {
		setTitle('Preferencias'); crumbs([['Perfil', '#/profile'], ['Preferencias', location.hash]]);
		var nd = S.notif || await get('/m/notifications', { branch_id: S.branch }, { fresh: true });
		var perm = 'Notification' in window ? Notification.permission : 'unsupported';
		var currentTheme = localStorage.getItem('daily_theme') || 'green';
		var html = section('Tema de Identidad Visual') + '<div class="m-card">'
			+ '<div class="m-row" style="flex-direction:column;align-items:flex-start;gap:8px">'
			+ '<select id="m-theme-select" class="m-input" style="font-weight:600">'
			+ '<option value="green"' + (currentTheme === 'green' ? ' selected' : '') + '>DAILY Verde — predeterminado</option>'
			+ '<option value="red"' + (currentTheme === 'red' ? ' selected' : '') + '>DAILY Rojo</option>'
			+ '<option value="yellow"' + (currentTheme === 'yellow' ? ' selected' : '') + '>DAILY Amarillo</option>'
			+ '</select></div></div>';
		html += section('Avisos en la app')`;

file = file.replace(oldSettings.replace(/\r?\n/g, '\r\n'), newSettings.replace(/\n/g, '\r\n'));
file = file.replace(oldSettings.replace(/\r?\n/g, '\n'), newSettings);

// Wire up the theme select listener
const oldListeners = `document.getElementById('m-sound').onchange = function (e) { store('sound', e.target.checked); if (e.target.checked) { buzz(true); } };
	};`;

const newListeners = `document.getElementById('m-sound').onchange = function (e) { store('sound', e.target.checked); if (e.target.checked) { buzz(true); } };
		var ts = document.getElementById('m-theme-select');
		if (ts) {
			ts.onchange = function() {
				var val = ts.value;
				localStorage.setItem('daily_theme', val);
				if (val === 'red' || val === 'yellow') {
					document.documentElement.setAttribute('data-theme', val);
				} else {
					document.documentElement.removeAttribute('data-theme');
				}
				toast('Tema ' + (val === 'red' ? 'Rojo' : (val === 'yellow' ? 'Amarillo' : 'Verde')) + ' aplicado', 'ok');
			};
		}
	};`;

file = file.replace(oldListeners.replace(/\r?\n/g, '\r\n'), newListeners.replace(/\n/g, '\r\n'));
file = file.replace(oldListeners.replace(/\r?\n/g, '\n'), newListeners);

fs.writeFileSync('public/js/dairy_m.js', file, 'utf8');
console.log('public/js/dairy_m.js updated successfully');
