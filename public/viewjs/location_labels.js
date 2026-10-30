/* DAIRY: dibuja las etiquetas de ubicación con bwip-js (sin servicios externos) */
document.querySelectorAll('canvas[data-code]').forEach(function (c) {
	try {
		var linear = c.dataset.type === 'code128';
		var opts = { bcid: c.dataset.type, text: c.dataset.code, scale: 3 };
		if (linear) {
			// bwip-js rechaza opciones con valor undefined: solo las lineales llevan alto y texto
			Object.assign(opts, { height: 10, includetext: true, textxalign: 'center' });
		}
		bwipjs.toCanvas(c, opts);
	} catch (e) {
		console.error('Etiqueta ' + c.dataset.code + ': ' + e.message);
		c.replaceWith(Object.assign(document.createElement('span'), { className: 'text-danger small', textContent: 'No se pudo generar el código' }));
	}
});
