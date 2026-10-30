const fs = require('fs');
let c = fs.readFileSync('public/js/dairy_charts.js', 'utf8');

c = c.replace(
  "var v = css.getPropertyValue('--dairy-' + name);",
  "var v = css.getPropertyValue('--daily-' + name) || css.getPropertyValue('--dairy-' + name);"
);

c = c.replace(
  "primary: token('primary', '#2563eb'),",
  "primary: token('primary', '#00C83B'),"
);

c = c.replace(
  "window.DairyPalette = {",
  `window.DailyUpdatePalette = function() {
		var css2 = getComputedStyle(document.documentElement);
		var p = (css2.getPropertyValue('--daily-primary') || css2.getPropertyValue('--dairy-primary') || '#00C83B').trim();
		if (window.DairyPalette) {
			window.DairyPalette.primary = p;
			window.DairyPalette.series[0] = p;
		}
	};

	window.DairyPalette = {`
);

fs.writeFileSync('public/js/dairy_charts.js', c, 'utf8');
console.log('dairy_charts.js successfully updated');
