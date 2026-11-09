// Hoja de contacto del QA: node sheet.js <qaDir> <out> <desde> <hasta>
const sharp=require('sharp'),fs=require('fs'),path=require('path');
const [D,O,a,b]=process.argv.slice(2);const fl=fs.readdirSync(D).filter(f=>f.endsWith('.png')).sort().slice(+a-1,+b);
(async()=>{const W=640,H=360,cols=2;const rows=Math.ceil(fl.length/cols);
const comp=await Promise.all(fl.map(async(f,i)=>({input:await sharp(path.join(D,f)).resize(W,H).toBuffer(),left:(i%cols)*(W+8),top:Math.floor(i/cols)*(H+8)})));
await sharp({create:{width:cols*(W+8),height:rows*(H+8),channels:3,background:'#888'}}).composite(comp).jpeg({quality:72}).toFile(O);})();
