const fs = require('fs');
let file = fs.readFileSync('public/viewjs/system_map.js', 'utf8');

file = file.replace(
  "var root = { id: 'root', type: 'root', label: 'DAIRY', children: [] };",
  "var root = { id: 'root', type: 'root', label: 'DAILY', children: [] };"
);

file = file.replace(
  "<div class=\"sm-crumb\">DAIRY › ",
  "<div class=\"sm-crumb\">DAILY › "
);

file = file.replace(
  "Presente en DAIRY Mobile (/m)",
  "Presente en DAILY Mobile (/m)"
);

file = file.replace(
  "<div class=\"sm-crumb\">DAIRY</div>",
  "<div class=\"sm-crumb\">DAILY</div>"
);

file = file.replace(
  "<h5>DAIRY</h5>",
  "<h5>DAILY</h5>"
);

fs.writeFileSync('public/viewjs/system_map.js', file, 'utf8');
console.log('public/viewjs/system_map.js updated successfully');
