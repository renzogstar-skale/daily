"""Vistas comerciales de los 7 módulos: solo recorta y une zonas reales de capturas de DAILY.
Escritorio: proporción fija R (relleno con el fondo de la propia captura). Móvil: piezas apiladas a ~1600 px."""
import sys, json
import numpy as np
from PIL import Image

V3 = r'C:\Users\renzo\grocy\grocy\docs\estrategia\17_presentacion_comercial_v3\capturas\\'
LIVE = r'C:\Users\renzo\AppData\Local\Temp\claude\C--Users-renzo-grocy-grocy\9d551951-2cb5-4408-8992-98500ab351e7\scratchpad\\'
OUT = r'C:\Users\renzo\grocy\grocy\public\img\site\\'
R, M, G, MW = 1.9, 28, 32, 1600

# Cada módulo: filas de piezas (archivo, caja x0,y0,x1,y1); split = cortar la pieza en móvil por el canal más cercano
MODS = {
	'exec': [[(V3 + r'01_ejecutivo\today.png', (536, 708, 3768, 1154), 2152)], [(V3 + r'01_ejecutivo\today.png', (536, 1184, 2294, 1968), None), (V3 + r'01_ejecutivo\today.png', (2326, 1184, 3768, 1968), None)]],
	'sales': [[(V3 + r'05_ventas\sales.png', (520, 1062, 3786, 1342), 2156)], [(V3 + r'05_ventas\sales.png', (520, 1372, 2404, 2600), None), (V3 + r'05_ventas\sales.png', (1904, 2640, 3000, 3500), None)]],
	'inventory': [[(V3 + r'03_inventario\stockoverview.png', (536, 470, 3770, 1272), 2152)], [(V3 + r'03_inventario\stockoverview.png', (536, 1336, 2640, 2524), 1500)]],
	'shrink': [[(V3 + r'02_mermas\waste_centro-control.png', (520, 1182, 3786, 1790), 2156)], [(V3 + r'02_mermas\waste_centro-control.png', (520, 1830, 3786, 2590), 1904)], [(V3 + r'02_mermas\waste_centro-control.png', (520, 2606, 3786, 2970), 2240)]],
	'dispatch': [[(LIVE + 'live_dispatches.png', (556, 528, 3746, 760), None)], [(LIVE + 'live_dispatches.png', (556, 796, 3746, 1252), 2152)], [(LIVE + 'live_returns.png', (4360 - 3840, 256, 7600 - 3840, 440), 2140)], [(LIVE + 'live_dispatches.png', (556, 1400, 3746, 2520), 1350)]],
	'workforce': [[(V3 + r'06_personal\attendance.png', (520, 820, 3790, 1090), 2150)], [(V3 + r'06_personal\scheduling.png', (512, 778, 3792, 1130), 2152)], [(V3 + r'06_personal\scheduling.png', (512, 1190, 3792, 1782), 1760)]],
	'loss-prevention': [[(V3 + r'02_mermas\loss_executive.png', (4360 - 3840, 500, 7600 - 3840, 772), 2140)], [(V3 + r'02_mermas\loss_executive.png', (4360 - 3840, 790, 7600 - 3840, 1232), 1740)], [(V3 + r'02_mermas\loss_executive.png', (4360 - 3840, 1832, 7600 - 3840, 2690), None)]],
}

def is_bg(a, bg, tol=10):
	return np.abs(a.astype(int) - np.array(bg)).sum(axis=-1) <= tol

def gutter(img, approx, bg):
	# Canal entre tarjetas = columna con color más uniforme (sirve también con fondos en degradado)
	a = np.asarray(img).astype(float)
	lo, hi = max(1, approx - 140), min(img.width - 1, approx + 140)
	std = a[:, lo:hi].std(axis=0).sum(axis=-1)
	i = int(np.argmin(std + np.abs(np.arange(lo, hi) - approx) * 0.01))
	return lo + i, round(float(std[i]), 2)

report = {}
which = sys.argv[1:] or list(MODS)
for key in which:
	rows = MODS[key]
	pieces = []
	for row in rows:
		r = []
		for f, box, split in row:
			im = Image.open(f).convert('RGB')
			bg = im.getpixel((box[0] - 8, box[1] + 4)) if box[0] > 8 else (248, 250, 252)
			r.append((im.crop(box), split, box, bg))
		pieces.append(r)
	bg = pieces[0][0][3]
	# Escritorio
	W = max(sum(p[0].width for p in row) + G * (len(row) - 1) for row in pieces)
	H = sum(max(p[0].height for p in row) for row in pieces) + G * (len(pieces) - 1)
	cw, ch = W + 2 * M, H + 2 * M
	if cw / ch > R:
		ch = round(cw / R)
	else:
		cw = round(ch * R)
	canvas = Image.new('RGB', (cw, ch), bg)
	y = (ch - H) // 2
	for row in pieces:
		rw = sum(p[0].width for p in row) + G * (len(row) - 1)
		x = (cw - rw) // 2
		for p in row:
			canvas.paste(p[0], (x, y)); x += p[0].width + G
		y += max(p[0].height for p in row) + G
	for w in (2400, 1400):
		canvas.resize((w, round(ch * w / cw)), Image.LANCZOS).save(f'{OUT}mod_{key}_{w}.webp', 'WEBP', quality=84, method=6)
	# Móvil: piezas apiladas, las anchas se cortan por el canal entre tarjetas
	mob = []
	splits = []
	for row in pieces:
		for img, split, box, pbg in row:
			if split:
				gx, frac = gutter(img, split - box[0], pbg)
				splits.append((split, box[0] + gx, round(frac, 3)))
				mob += [img.crop((0, 0, gx, img.height)), img.crop((gx, 0, img.width, img.height))]
			else:
				mob.append(img)
	mob = [m if m.width <= MW else m.resize((MW, round(m.height * MW / m.width)), Image.LANCZOS) for m in mob]
	mh = sum(m.height for m in mob) + G * (len(mob) - 1) + 2 * M
	stack = Image.new('RGB', (MW + 2 * M, mh), bg)
	y = M
	for m in mob:
		stack.paste(m, (M + (MW - m.width) // 2, y)); y += m.height + G
	stack.resize((1000, round(mh * 1000 / stack.width)), Image.LANCZOS).save(f'{OUT}mod_{key}_m.webp', 'WEBP', quality=84, method=6)
	report[key] = {'content': (W, H), 'canvas': (cw, ch), 'fill': round(W * H / (cw * ch), 2), 'mobile': (1000, round(mh * 1000 / stack.width)), 'splits': splits}
print(json.dumps(report, indent=0))
