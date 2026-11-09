// Miniaturas con cuadrícula cada 200 px CSS para elegir recortes. Uso: node thumbs.js <capsDir> <outDir> [filtro]
const sharp = require('sharp'), fs = require('fs'), path = require('path');
const [CAPS, OUT, only] = process.argv.slice(2);
(async () => {
  for (const d of fs.readdirSync(CAPS)) {
    const dir = path.join(CAPS, d); if (!fs.statSync(dir).isDirectory()) continue;
    for (const f of fs.readdirSync(dir).filter(x => x.endsWith('.png'))) {
      if (only && !new RegExp(only).test(d + '/' + f)) continue;
      const src = path.join(dir, f); const m = await sharp(src, { limitInputPixels: false }).metadata();
      const sc = m.width > 1000 ? 2 : 3; const cssW = m.width / sc, cssH = Math.min(m.height / sc, 2400);
      const k = 0.5; const W = Math.round(cssW * k), H = Math.round(cssH * k);
      let g = ''; for (let y = 0; y < cssH; y += 200) g += `<line x1="0" y1="${y * k}" x2="${W}" y2="${y * k}" stroke="red" stroke-width="1"/><text x="2" y="${y * k + 12}" font-size="12" fill="red">${y}</text>`;
      for (let x = 0; x < cssW; x += 200) g += `<line x1="${x * k}" y1="0" x2="${x * k}" y2="${H}" stroke="blue" stroke-width="0.5"/><text x="${x * k + 2}" y="${H - 4}" font-size="11" fill="blue">${x}</text>`;
      const svg = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">${g}</svg>`);
      await sharp(src, { limitInputPixels: false }).extract({ left: 0, top: 0, width: m.width, height: Math.round(cssH * sc) }).resize({ width: W, height: H }).composite([{ input: svg }]).jpeg({ quality: 70 }).toFile(path.join(OUT, d + '__' + f.replace('.png', '.jpg')));
    }
  }
})();
