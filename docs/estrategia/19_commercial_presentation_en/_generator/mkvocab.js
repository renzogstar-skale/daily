// Spanish-only vocabulary for the language QA: words that appear in the Spanish keys of the DAILY
// catalog (localization/daily/en/*.json) and never in its English values. Usage: node mkvocab.js -> es_vocab.json
const fs = require('fs'), path = require('path');
const dir = path.join(__dirname, '../../../../localization/daily/en');
const words = s => (String(s).toLowerCase().normalize('NFC').match(/[a-záéíóúüñ]{3,}/g) || []);
const es = new Map(), en = new Set();
for (const f of fs.readdirSync(dir).filter(f => f.endsWith('.json'))) {
  const j = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
  const walk = o => { for (const [k, v] of Object.entries(o)) { if (v && typeof v === 'object') { walk(v); continue; } words(k).forEach(w => es.set(w, (es.get(w) || 0) + 1)); words(v).forEach(w => en.add(w)); } };
  walk(j);
}
// common English words that also exist as Spanish keys' tokens (cognates, product terms) stay out
const out = [...es.entries()].filter(([w, n]) => !en.has(w) && n >= 2).map(([w]) => w).sort();
fs.writeFileSync(path.join(__dirname, 'es_vocab.json'), JSON.stringify(out));
console.log(out.length, out.slice(0, 40).join(' '));
