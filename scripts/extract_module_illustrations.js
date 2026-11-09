const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const fs = require('fs');
const path = require('path');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  const imgPath = 'C:/Users/renzo/.gemini/antigravity/brain/2f30f71a-f6ef-4fe4-963d-b7b0bcb789ae/.user_uploaded/media_1794001849417_3248c457.jpg';
  const imgBase64 = fs.readFileSync(imgPath).toString('base64');
  
  await page.setContent('<!DOCTYPE html><html><body><img id="ref" src="data:image/jpeg;base64,' + imgBase64 + '"></body></html>');

  // Exact coordinates for 3D illustrations:
  // Row 1: y = 178, h = 122 (ends at 300, avoiding text at 308)
  // Row 2: y = 514, h = 126 (ends at 640, avoiding text at 648)
  const crops = [
    { name: 'mod_1_sales', x: 20, y: 178, w: 232, h: 122 },
    { name: 'mod_2_inventory', x: 270, y: 178, w: 232, h: 122 },
    { name: 'mod_3_waste', x: 520, y: 178, w: 232, h: 122 },
    { name: 'mod_4_lp', x: 770, y: 178, w: 232, h: 122 },
    { name: 'mod_5_dispatches', x: 20, y: 514, w: 232, h: 126 },
    { name: 'mod_6_hr', x: 270, y: 514, w: 232, h: 126 },
    { name: 'mod_7_planning', x: 520, y: 514, w: 232, h: 126 },
    { name: 'mod_8_analytics', x: 770, y: 514, w: 232, h: 126 }
  ];

  const outDir = path.join(__dirname, '../public/img/site/modules');
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }

  const results = await page.evaluate(async (crops) => {
    const img = document.getElementById('ref');
    await new Promise(r => { if (img.complete) r(); else img.onload = r; });
    const res = {};
    for (const c of crops) {
      const canvas = document.createElement('canvas');
      canvas.width = c.w;
      canvas.height = c.h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, c.x, c.y, c.w, c.h, 0, 0, c.w, c.h);
      res[c.name] = canvas.toDataURL('image/webp', 0.98);
    }
    return res;
  }, crops);

  for (const [name, dataUrl] of Object.entries(results)) {
    const base64 = dataUrl.split(',')[1];
    const filePath = path.join(outDir, `${name}.webp`);
    fs.writeFileSync(filePath, Buffer.from(base64, 'base64'));
    console.log(`Saved ${name}.webp`);
  }

  await browser.close();
  console.log('--- RE-EXTRACCIÓN COMPLETADA SIN TEXTO ---');
})();
