const fs = require('fs');
let file = fs.readFileSync('views/layout/default.blade.php', 'utf8');

// 1. Meta theme color
file = file.replace(
  '<meta name="theme-color"\r\n\t\tcontent="#0284c7">',
  '<meta name="theme-color"\r\n\t\tcontent="#00C83B"\r\n\t\tid="metaThemeColor">'
);
file = file.replace(
  '<meta name="theme-color"\n\t\tcontent="#0284c7">',
  '<meta name="theme-color"\n\t\tcontent="#00C83B"\n\t\tid="metaThemeColor">'
);

// 2. Apple mobile title
file = file.replace(
  '<meta name="apple-mobile-web-app-title"\r\n\t\tcontent="DAIRY">',
  '<meta name="apple-mobile-web-app-title"\r\n\t\tcontent="DAILY">'
);
file = file.replace(
  '<meta name="apple-mobile-web-app-title"\n\t\tcontent="DAIRY">',
  '<meta name="apple-mobile-web-app-title"\n\t\tcontent="DAILY">'
);

// 3. Title tag
file = file.replace(
  '<title>@yield(\'title\') | DAIRY</title>',
  '<title>@yield(\'title\') | DAILY</title>'
);

// 4. Zero flicker theme script in head
const headScript = `\t<script>
\t\t(function() {
\t\t\ttry {
\t\t\t\tvar t = localStorage.getItem('daily_theme') || 'green';
\t\t\t\tif (t === 'red' || t === 'yellow') {
\t\t\t\t\tdocument.documentElement.setAttribute('data-theme', t);
\t\t\t\t} else {
\t\t\t\t\tdocument.documentElement.removeAttribute('data-theme');
\t\t\t\t}
\t\t\t} catch(e) {}
\t\t})();
\t</script>\n`;

if (!file.includes('localStorage.getItem(\'daily_theme\')')) {
  file = file.replace('<head>', '<head>\n' + headScript);
}

// 5. CSS link daily_theme.css
const cssLink = `\t<link href="{{ $U('/css/daily_theme.css?v=', true) }}{{ $version }}"\n\t\trel="stylesheet">\n`;
if (!file.includes('daily_theme.css')) {
  file = file.replace(
    /(\t<link href="\{\{ \$U\('\/css\/dairy_glass\.css\?v=', true\) \}\}\{\{ \$version \}}"\s*rel="stylesheet">)/,
    '$1\n' + cssLink
  );
}

// 6. Inline SVG Brand Logo
const oldLogoTarget = `<div class="header-brand-zone">
			<a class="navbar-brand py-0 mr-0"
				href="{{ $U('/') }}">
				<img src="{{ $U('/img/logo.svg?v=', true) }}{{ $version }}"
					width="114"
					height="30"
					alt="DAIRY">
			</a>`;

const newLogo = `<div class="header-brand-zone">
			<a class="navbar-brand py-0 mr-0 d-flex align-items-center"
				href="{{ $U('/') }}"
				aria-label="DAILY">
				<svg class="daily-logo-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 152 40" width="114" height="30" role="img" aria-label="DAILY">
					<defs>
						<radialGradient id="dailyBadgeGrad" cx="50%" cy="50%" r="50%">
							<stop offset="0%" stop-color="var(--daily-light, #39F500)"/>
							<stop offset="48%" stop-color="var(--daily-primary, #00C83B)"/>
							<stop offset="100%" stop-color="var(--daily-dark, #008E48)"/>
						</radialGradient>
						<linearGradient id="dailyDLetterGrad" x1="0%" y1="0%" x2="0%" y2="100%">
							<stop offset="0%" stop-color="#ffffff"/>
							<stop offset="100%" stop-color="#f0fdf4"/>
						</linearGradient>
						<filter id="dailyBadgeShadow" x="-20%" y="-20%" width="140%" height="140%">
							<feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#091e42" flood-opacity="0.15"/>
						</filter>
					</defs>
					<g filter="url(#dailyBadgeShadow)">
						<rect x="2" y="2" width="36" height="36" rx="9" fill="url(#dailyBadgeGrad)"/>
						<rect x="2.5" y="2.5" width="35" height="35" rx="8.5" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1"/>
						<path d="M 148 116 L 262 116 C 336 116 376 172 376 256 C 376 340 336 396 262 396 L 148 396 C 142 396 138 392 138 386 L 138 126 C 138 120 142 116 148 116 Z M 210 178 L 254 178 C 298 178 306 214 306 256 C 306 298 298 334 254 334 L 210 334 C 206 334 204 332 204 328 L 204 184 C 204 180 206 178 210 178 Z"
							fill="url(#dailyDLetterGrad)"
							fill-rule="evenodd"
							transform="translate(5.8, 5.8) scale(0.055)"/>
					</g>
					<text x="46" y="27"
						font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
						font-size="24"
						font-weight="800"
						letter-spacing="-0.01em"
						fill="url(#dailyBadgeGrad)"
						class="daily-brand-text">DAILY</text>
				</svg>
			</a>`;

file = file.replace(oldLogoTarget.replace(/\r?\n/g, '\r\n'), newLogo.replace(/\n/g, '\r\n'));
file = file.replace(oldLogoTarget.replace(/\r?\n/g, '\n'), newLogo);

// 7. User dropdown theme picker
const oldDropdown = `<div class="dropdown-menu dropdown-menu-right">
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: var(--dairy-fs-xs);">Sesión Activa</div>
						<a class="dropdown-item discrete-link" href="{{ $U('/usersettings') }}"><i class="fa-solid fa-user-gear mr-2 text-primary"></i>Perfil de Usuario</a>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item logout-button discrete-link text-danger" href="{{ $U('/logout') }}"><i class="fa-solid fa-sign-out-alt mr-2"></i>Cerrar Sesión</a>
					</div>`;

const newDropdown = `<div class="dropdown-menu dropdown-menu-right">
						<div class="dropdown-header text-uppercase font-weight-bold" style="font-size: var(--dairy-fs-xs);">Sesión Activa</div>
						<a class="dropdown-item discrete-link" href="{{ $U('/usersettings') }}"><i class="fa-solid fa-user-gear mr-2 text-primary"></i>Perfil de Usuario</a>
						<div class="dropdown-divider"></div>
						<div class="px-3 py-1">
							<div class="small font-weight-bold text-muted mb-1">Tema de Identidad Visual</div>
							<div class="btn-group btn-group-toggle btn-group-sm w-100" data-toggle="buttons" id="dailyQuickThemePicker">
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-green" title="DAILY Verde (Predeterminado)" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="green"> <i class="fa-solid fa-circle text-success mr-1"></i>Verde
								</label>
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-red" title="DAILY Rojo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="red"> <i class="fa-solid fa-circle text-danger mr-1"></i>Rojo
								</label>
								<label class="btn btn-outline-secondary py-1 px-2" id="daily-btn-yellow" title="DAILY Amarillo" style="font-size: 11px;">
									<input type="radio" name="daily_quick_theme_opt" value="yellow"> <i class="fa-solid fa-circle text-warning mr-1"></i>Amarillo
								</label>
							</div>
						</div>
						<div class="dropdown-divider"></div>
						<a class="dropdown-item logout-button discrete-link text-danger" href="{{ $U('/logout') }}"><i class="fa-solid fa-sign-out-alt mr-2"></i>Cerrar Sesión</a>
					</div>`;

file = file.replace(oldDropdown.replace(/\r?\n/g, '\r\n'), newDropdown.replace(/\n/g, '\r\n'));
file = file.replace(oldDropdown.replace(/\r?\n/g, '\n'), newDropdown);

// 8. About Dairy in footer
file = file.replace("{{ $__t('About Dairy') }}", "Acerca de DAILY");

// 9. Script for setDailyTheme
const themeScript = `
\t<script>
\twindow.setDailyTheme = function(theme, persist) {
\t\tif (theme !== 'red' && theme !== 'yellow') {
\t\t\ttheme = 'green';
\t\t}
\t\tif (theme === 'red' || theme === 'yellow') {
\t\t\tdocument.documentElement.setAttribute('data-theme', theme);
\t\t} else {
\t\t\tdocument.documentElement.removeAttribute('data-theme');
\t\t}
\t\ttry {
\t\t\tlocalStorage.setItem('daily_theme', theme);
\t\t} catch (e) {}

\t\tvar colorMap = { green: '#00C83B', red: '#DC2626', yellow: '#FFB800' };
\t\tvar metaColor = document.getElementById('metaThemeColor');
\t\tif (metaColor) {
\t\t\tmetaColor.setAttribute('content', colorMap[theme] || '#00C83B');
\t\t}

\t\tif (window.DailyUpdatePalette) {
\t\t\twindow.DailyUpdatePalette();
\t\t}

\t\t$('#dailyQuickThemePicker label').removeClass('active');
\t\t$('#daily-btn-' + theme).addClass('active').find('input').prop('checked', true);
\t\t$('#daily_theme').val(theme);

\t\tif (persist !== false && window.Grocy && Grocy.FrontendHelpers && Grocy.FrontendHelpers.SaveUserSetting) {
\t\t\tGrocy.FrontendHelpers.SaveUserSetting('daily_theme', theme);
\t\t}
\t};

\t$(document).ready(function() {
\t\tvar curTheme = localStorage.getItem('daily_theme') || (window.Grocy && Grocy.UserSettings && Grocy.UserSettings.daily_theme) || 'green';
\t\twindow.setDailyTheme(curTheme, false);

\t\t$(document).on('change', 'input[name=\"daily_quick_theme_opt\"]', function() {
\t\t\twindow.setDailyTheme($(this).val(), true);
\t\t});
\t\t$(document).on('change', '#daily_theme', function() {
\t\t\twindow.setDailyTheme($(this).val(), true);
\t\t});
\t});
\t</script>
`;

if (!file.includes('window.setDailyTheme')) {
  file = file.replace('</body>', themeScript + '\n</body>');
}

fs.writeFileSync('views/layout/default.blade.php', file, 'utf8');
console.log('views/layout/default.blade.php updated successfully');
