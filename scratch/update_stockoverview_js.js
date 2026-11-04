const fs = require('fs');
let content = fs.readFileSync('public/viewjs/stockoverview.js', 'utf8');

const target = `\tvar DEPT_META = {
\t\t'electronica': { icon: 'fa-laptop', emoji: '🖥️', bg: '#e8f8ee', border: '#bbf7d0', trend: 5.1 },
\t\t'carnes': { icon: 'fa-drumstick-bite', emoji: '🥩', bg: '#fee2e2', border: '#fecaca', trend: -1.8 },
\t\t'bakery': { icon: 'fa-bread-slice', emoji: '🥐', bg: '#f3e8ff', border: '#e9d5ff', trend: 3.9 },
\t\t'fruver': { icon: 'fa-apple-whole', emoji: '🍎', bg: '#ffedd5', border: '#fed7aa', trend: 2.1 },
\t\t'dairy': { icon: 'fa-bottle-water', emoji: '🥛', bg: '#fef3c7', border: '#fde68a', trend: -6.4 },
\t\t'congelados': { icon: 'fa-snowflake', emoji: '❄️', bg: '#e0f2fe', border: '#bae6fd', trend: 0.8 },
\t\t'abarrotes': { icon: 'fa-cart-shopping', emoji: '🛒', bg: '#fef3c7', border: '#fde68a', trend: -2.3 },
\t\t'bebidas': { icon: 'fa-cup-straw', emoji: '🥤', bg: '#dbeafe', border: '#bfdbfe', trend: -4.7 }
\t};`;

const replacement = `\tvar DEPT_META = {
\t\t'tecnologia': { icon: 'fa-laptop', emoji: '💻', bg: '#eff6ff', border: '#bfdbfe', trend: 5.2 },
\t\t'electrodomesticos': { icon: 'fa-tv', emoji: '📺', bg: '#f0fdfa', border: '#99f6e4', trend: 3.4 },
\t\t'alimentos': { icon: 'fa-cart-shopping', emoji: '🛒', bg: '#fffbeb', border: '#fde68a', trend: 2.8 },
\t\t'hogar': { icon: 'fa-couch', emoji: '🛋️', bg: '#fefce8', border: '#fef08a', trend: -1.2 },
\t\t'accesorios': { icon: 'fa-headphones', emoji: '🎧', bg: '#eef2ff', border: '#c7d2fe', trend: 4.1 },
\t\t'dairy': { icon: 'fa-bottle-water', emoji: '🥛', bg: '#fef3c7', border: '#fde68a', trend: -3.4 },
\t\t'cuidado_personal': { icon: 'fa-pump-soap', emoji: '🧴', bg: '#fdf2f8', border: '#fbcfe8', trend: 1.5 },
\t\t'bebidas': { icon: 'fa-cup-straw', emoji: '🥤', bg: '#dbeafe', border: '#bfdbfe', trend: -2.1 },
\t\t'carnes': { icon: 'fa-drumstick-bite', emoji: '🥩', bg: '#fee2e2', border: '#fecaca', trend: -1.8 },
\t\t'fruver': { icon: 'fa-apple-whole', emoji: '🍎', bg: '#dcfce7', border: '#bbf7d0', trend: 2.1 },
\t\t'congelados': { icon: 'fa-snowflake', emoji: '❄️', bg: '#e0f2fe', border: '#bae6fd', trend: 0.8 },
\t\t'bakery': { icon: 'fa-bread-slice', emoji: '🥐', bg: '#f3e8ff', border: '#e9d5ff', trend: 3.9 },
\t\t'electronica': { icon: 'fa-laptop', emoji: '💻', bg: '#eff6ff', border: '#bfdbfe', trend: 5.1 },
\t\t'abarrotes': { icon: 'fa-cart-shopping', emoji: '🛒', bg: '#fffbeb', border: '#fde68a', trend: 2.8 },
\t\t'otros': { icon: 'fa-box', emoji: '📦', bg: '#f1f5f9', border: '#e2e8f0', trend: 0.0 }
\t};`;

const hasCRLF = content.includes('\r\n');
const normalizedTarget = target.replace(/\r\n/g, '\n');
const normalizedContent = content.replace(/\r\n/g, '\n');
if (!normalizedContent.includes(normalizedTarget)) {
    console.error('Target not found!');
    process.exit(1);
}
let updated = normalizedContent.replace(normalizedTarget, replacement.replace(/\r\n/g, '\n'));
if (hasCRLF) updated = updated.replace(/\n/g, '\r\n');
fs.writeFileSync('public/viewjs/stockoverview.js', updated);
console.log('Successfully updated DEPT_META in public/viewjs/stockoverview.js');
