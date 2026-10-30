const fs = require('fs');

// 1. localization/es/strings.po
let po = fs.readFileSync('localization/es/strings.po', 'utf8');
po = po.replace(/Acerca de DAIRY/g, 'Acerca de DAILY');
po = po.replace(/¿Le resulta útil DAIRY\?/g, '¿Le resulta útil DAILY?');
po = po.replace(/accede a DAIRY a través/g, 'accede a DAILY a través');
po = po.replace(/msgstr "Dairycode"/g, 'msgstr "Dailycode"');
po = po.replace(/Descargar Dairycode de %s/g, 'Descargar Dailycode de %s');
po = po.replace(/El código DAIRY es una referencia única a este %s en su instancia de DAIRY/g, 'El código DAILY es una referencia única a este %s en su instancia de DAILY');
po = po.replace(/Imprimir Dairycode de %s/g, 'Imprimir Dailycode de %s');
fs.writeFileSync('localization/es/strings.po', po, 'utf8');
console.log('localization/es/strings.po updated');

// 2. views/about.blade.php
let about = fs.readFileSync('views/about.blade.php', 'utf8');
about = about.replace('>DAIRY</a> is an ERP platform', '>DAILY</a> is an ERP platform');
fs.writeFileSync('views/about.blade.php', about, 'utf8');
console.log('views/about.blade.php updated');

// 3. views/ai_advisor.blade.php
let advisor = fs.readFileSync('views/ai_advisor.blade.php', 'utf8');
advisor = advisor.replace('Arturito solo lee datos de DAIRY', 'Arturito solo lee datos de DAILY');
fs.writeFileSync('views/ai_advisor.blade.php', advisor, 'utf8');
console.log('views/ai_advisor.blade.php updated');

// 4. views/dairy_document.blade.php
let doc = fs.readFileSync('views/dairy_document.blade.php', 'utf8');
doc = doc.replace('| DAIRY</title>', '| DAILY</title>');
doc = doc.replace('DAIRY ERP</div>', 'DAILY ERP</div>');
fs.writeFileSync('views/dairy_document.blade.php', doc, 'utf8');
console.log('views/dairy_document.blade.php updated');

// 5. views/openapiui.blade.php
let openapi = fs.readFileSync('views/openapiui.blade.php', 'utf8');
openapi = openapi.replace('| DAIRY</title>', '| DAILY</title>');
fs.writeFileSync('views/openapiui.blade.php', openapi, 'utf8');
console.log('views/openapiui.blade.php updated');

// 6. views/schedule.blade.php
let sched = fs.readFileSync('views/schedule.blade.php', 'utf8');
sched = sched.replace('optimización diaria de DAIRY en 10', 'optimización diaria de DAILY en 10');
fs.writeFileSync('views/schedule.blade.php', sched, 'utf8');
console.log('views/schedule.blade.php updated');

// 7. views/system_map.blade.php
let sm = fs.readFileSync('views/system_map.blade.php', 'utf8');
sm = sm.replace('DAIRY — Mapa del Sistema', 'DAILY — Mapa del Sistema');
sm = sm.replace('aria-label="Mapa del sistema DAIRY"', 'aria-label="Mapa del sistema DAILY"');
fs.writeFileSync('views/system_map.blade.php', sm, 'utf8');
console.log('views/system_map.blade.php updated');

// 8. views/waste_overview.blade.php
let waste = fs.readFileSync('views/waste_overview.blade.php', 'utf8');
waste = waste.replace("'DAIRY_Control_Mermas_'", "'DAILY_Control_Mermas_'");
fs.writeFileSync('views/waste_overview.blade.php', waste, 'utf8');
console.log('views/waste_overview.blade.php updated');

// 9. views/components/waste_tabs/censo.blade.php
let censo = fs.readFileSync('views/components/waste_tabs/censo.blade.php', 'utf8');
censo = censo.replace('no son datos de DAIRY)', 'no son datos de DAILY)');
fs.writeFileSync('views/components/waste_tabs/censo.blade.php', censo, 'utf8');
console.log('views/components/waste_tabs/censo.blade.php updated');

// 10. Dailycode string replaces in views
const codeViews = [
  'views/recipes.blade.php',
  'views/stockentries.blade.php',
  'views/stockjournal.blade.php',
  'views/stockoverview.blade.php',
  'views/batteriesoverview.blade.php',
  'views/choresoverview.blade.php'
];
codeViews.forEach(v => {
  if (fs.existsSync(v)) {
    let content = fs.readFileSync(v, 'utf8');
    content = content.replace(/Dairycode/g, 'Dailycode');
    fs.writeFileSync(v, content, 'utf8');
    console.log(v + ' updated');
  }
});
