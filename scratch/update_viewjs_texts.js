const fs = require('fs');

// alert_center.js
let ac = fs.readFileSync('public/viewjs/alert_center.js', 'utf8');
ac = ac.replace('en DAIRY Mobile,', 'en DAILY Mobile,');
ac = ac.replace('Se aplican en DAIRY y DAIRY Mobile.', 'Se aplican en DAILY y DAILY Mobile.');
ac = ac.replace("name: 'DAIRY + móvil'", "name: 'DAILY + móvil'");
ac = ac.replace("name: 'DAIRY + correo*'", "name: 'DAILY + correo*'");
ac = ac.replace('DAIRY no tiene servidor de correo configurado.', 'DAILY no tiene servidor de correo configurado.');
fs.writeFileSync('public/viewjs/alert_center.js', ac, 'utf8');
console.log('alert_center.js updated');

// pos_integration.js
let pos = fs.readFileSync('public/viewjs/pos_integration.js', 'utf8');
pos = pos.replace('solo del POS de DAIRY.', 'solo del POS de DAILY.');
fs.writeFileSync('public/viewjs/pos_integration.js', pos, 'utf8');
console.log('pos_integration.js updated');

// scan.js
let sc = fs.readFileSync('public/viewjs/scan.js', 'utf8');
sc = sc.replace('DAIRY no gestiona reservas de clientes', 'DAILY no gestiona reservas de clientes');
fs.writeFileSync('public/viewjs/scan.js', sc, 'utf8');
console.log('scan.js updated');
