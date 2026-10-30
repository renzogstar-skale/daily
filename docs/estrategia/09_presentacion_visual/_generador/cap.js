const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = process.argv[2]; const list = JSON.parse(fs.readFileSync(process.argv[3]));
const NAMES = JSON.parse(fs.readFileSync(path.join(__dirname,'names.json'))).concat(['Renzo']);
function anon(names){
  const norm=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  const esc=s=>s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
  const list=[...new Set(names.concat(names.map(norm)))].sort((a,b)=>b.length-a.length);
  const re=new RegExp('('+list.map(esc).join('|')+')','gi'); const map={};let k=0;
  const sub=t=>{const n=t; if(!re.test(n))return null; re.lastIndex=0; return n.replace(re,m=>{const key=norm(m).toLowerCase(); if(!map[key])map[key]='Colaborador '+String(++k).padStart(2,'0'); return map[key];});};
  const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let x;
  while((x=w.nextNode())){ re.lastIndex=0; const r=sub(x.nodeValue); if(r!==null) x.nodeValue=r; }
  document.querySelectorAll('input,textarea').forEach(i=>{re.lastIndex=0;const r=i.value&&sub(i.value); if(r) i.value=r;});
  const pn=document.querySelector('.dairy-hprofile-name'); if(pn) pn.textContent='Usuario Demo';
  const pr=document.querySelector('.dairy-hprofile-role'); if(pr) pr.textContent='Gerente de tienda';
  const av=document.querySelector('.dairy-havatar'); if(av) av.textContent='UD';
  document.querySelectorAll('li.nav-item.dropdown').forEach(li=>{ if(/Rol:/.test(li.textContent)) li.style.visibility='hidden'; });
}
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  for (const item of list) {
    const mobile = item.m;
    const ctx = await b.newContext(mobile ? { viewport:{width:390,height:844}, deviceScaleFactor:3, isMobile:true, hasTouch:true } : { viewport:{width:1920,height:1080}, deviceScaleFactor:1 });
    const p = await ctx.newPage();
    try {
      await p.goto('http://localhost:8080'+item.u, { waitUntil:'networkidle', timeout:45000 });
      await p.waitForTimeout(item.w||2500);
      if (item.click) { await p.click(item.click); await p.waitForTimeout(3500);
        await p.evaluate(s=>{const e=document.querySelector(s); const nav=e.closest('ul,nav,.nav')||e; window.scrollTo(0, nav.getBoundingClientRect().top+window.scrollY-80);}, item.click); await p.waitForTimeout(800); }
      if (item.scroll) { await p.evaluate(y=>window.scrollTo(0,y), item.scroll); await p.waitForTimeout(1000); }
      await p.evaluate(anon, NAMES);
      fs.mkdirSync(path.join(OUT, item.d), { recursive:true });
      await p.screenshot({ path:path.join(OUT, item.d, item.f+'.png'), fullPage: !!item.full });
      console.log('OK', item.f);
    } catch(e) { console.log('FAIL', item.f, e.message.slice(0,100)); }
    await ctx.close();
  }
  await b.close();
})();
