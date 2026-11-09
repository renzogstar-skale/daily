const Database = require('better-sqlite3');
const db = new Database('data/grocy.db');
const rows = db.prepare('SELECT id, name, tax_id, email, phone, branch_id FROM customers LIMIT 8').all();
console.log(rows);

