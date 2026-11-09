const fs = require('fs');
const content = fs.readFileSync('helpers/DairyNavigation.php', 'utf8');
const lines = content.split(/\r?\n/);
lines.forEach(l => {
  if (l.includes("'id' =>") && l.includes("'label' =>")) {
    console.log(l.trim());
  }
});
