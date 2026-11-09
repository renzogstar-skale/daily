// DAILY — commercial presentation (English). One slide spec -> PPTX + PDF + presenter script
const path = require('path');
const sharp = require('sharp');
const { Slide, C } = require('./engine');

const LOGO = path.join(__dirname, 'assets', 'logo.png').replace(/\\/g, '/');
const S = {};

function content(key, section, kicker, title, status, notes) {
  const s = new Slide({ section, title });
  s.text(0.6, 0.36, 8, 0.28, kicker.toUpperCase(), { size: 11, bold: true, color: C.G3, spacing: 1.5 });
  s.status(status);
  s.notes = notes;
  S[key] = s; return s;
}
function sub(s, text) { s.text(0.6, 1.26, 12.1, 0.36, text, { size: 15, color: C.MUTED }); }
function callouts(s, x, y, w, items, color = C.R2, gap = 1.3) {
  items.forEach((it, i) => {
    const yy = y + i * gap;
    s.badge(x + 0.2, yy + 0.18, i + 1, color);
    s.text(x + 0.55, yy, w - 0.55, 0.32, it[0], { size: 15, bold: true });
    s.text(x + 0.55, yy + 0.34, w - 0.55, 0.75, it[1], { size: 13, color: C.MUTED });
  });
}
function iconCircle(s, x, y, d, name, fg, bg) {
  s.ellipse(x, y, d, d, { fill: bg });
  s.icon(x + d * 0.27, y + d * 0.27, d * 0.46, name, fg);
}
function flowRow(s, x, y, w, steps, o = {}) {
  const n = steps.length, gap = o.gap || 0.3, bw = (w - gap * (n - 1)) / n, h = o.h || 0.9;
  steps.forEach((st, i) => {
    const xx = x + i * (bw + gap), dark = i === n - 1 && o.lastDark;
    s.rect(xx, y, bw, h, { fill: dark ? C.G3 : C.WHITE, line: C.LINE, r: 0.1, shadow: true });
    iconCircle(s, xx + 0.12, y + (h - 0.5) / 2, 0.5, st[1], dark ? C.G3 : C.WHITE, dark ? C.WHITE : (st[2] || C.G2));
    s.text(xx + 0.7, y + 0.06, bw - 0.76, h - 0.12, st[0], { size: o.size || 13, bold: true, valign: 'middle', color: dark ? C.WHITE : C.INK });
    if (i < n - 1) s.line(xx + bw + 0.03, y + h / 2, xx + bw + gap - 0.03, y + h / 2, { color: C.G3, lw: 2, arrow: true });
  });
}
function stepper(s, active) {
  const T = ['Detect', 'Review', 'Investigate', 'Resolve'];
  T.forEach((t, i) => {
    const on = i === active, x = 0.6 + i * 1.72;
    s.pill(x, 1.3, 1.6, (i + 1) + '. ' + t, { fill: on ? C.G3 : C.WHITE, color: on ? C.WHITE : C.MUTED, size: 12, h: 0.34 });
  });
}
function stat(s, x, y, w, big, label, color = C.INK) {
  s.rect(x, y, w, 1.12, { fill: C.WHITE, line: C.LINE, r: 0.1, shadow: true });
  s.text(x + 0.15, y + 0.08, w - 0.3, 0.6, big, { size: 28, bold: true, color, valign: 'middle' });
  s.text(x + 0.15, y + 0.68, w - 0.3, 0.36, label, { size: 12, color: C.MUTED });
}
function label(s, x, y, w, t) { s.text(x, y, w, 0.28, t, { size: 13, bold: true }); }
function note(s, x, y, w, t, kind = 'Y') {
  const f = kind === 'Y' ? [C.YT, C.Y3] : kind === 'R' ? [C.RT, C.R3] : [C.GT, C.GD];
  s.rect(x, y, w, 0.62, { fill: f[0], r: 0.08 });
  s.text(x + 0.15, y, w - 0.3, 0.62, t, { size: 12, bold: true, color: f[1], valign: 'middle' });
}
function logo(s) {
  s.add({ t: 'img', abs: LOGO, x: 0.72, y: 0.8, w: 0.72, h: 0.72, alt: 'DAILY logo' });
  s.text(1.58, 0.85, 3, 0.62, 'DAILY', { size: 30, bold: true, color: C.WHITE, valign: 'middle', spacing: 2 });
}
const FULLW = [250, 40, 1650];

// ===== COVER =====
{
  const s = new Slide({ cover: true, section: 'Opening' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 2.0, 5.3, 2.0, 'DAILY — Operational supervision, control and management for retail', { size: 34, bold: true, color: C.WHITE, lh: 0.95 });
  s.text(0.75, 4.2, 5.0, 1.0, 'Inventory, sales, shrink, loss prevention, staff and purchasing in a single system.', { size: 17, color: 'D7F5DF' });
  s.pill(0.75, 5.45, 4.6, 'Real screens · English interface · demo data', { fill: '0E4A2B', color: 'B9F3C9', size: 11, h: 0.36 });
  s.text(6.55, 0.42, 3, 0.28, 'TODAY · Daily Operations', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(6.55, 0.72, 6.2, 3.55, '01_ejecutivo/today', [250, 60, 1650]);
  s.shot(6.0, 3.05, 4.4, 2.52, '03_inventario/stockoverview', [250, 60, 1650]);
  s.text(6.0, 5.65, 2.4, 0.28, 'Inventory', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(8.65, 4.35, 4.1, 2.35, '02_mermas/waste_centro-control', [250, 440, 1650]);
  s.text(8.65, 6.78, 3, 0.28, 'Shrinkage & Waste', { size: 12, bold: true, color: 'B9F3C9' });
  s.notes = 'SAY: "DAILY brings into one platform what many chains keep in spreadsheets and separate systems: inventory, sales, shrink, loss prevention, staff and purchasing." SHOW: the three screens; they are real, running a demo company with ten branches. The interface is shown in English; DAILY runs in English or Spanish per user. BENEFIT: one version of the numbers for the whole operation. COMMON QUESTION: "Is it an ERP?" → It does not replace your ERP or POS; it adds operational supervision and control, and receives data through import or API.';
  S.cover = s;
}

// ===== PROBLEMS =====
{
  const s = content('problems', 'Opening', 'The problem', 'What erodes margin in a retail chain today', 'demo',
    'SAY: walk through the cards and ask which ones the client recognizes. SHOW: the red card sums up the consequence. BENEFIT: name the problem before talking about software. ASK THE CLIENT: "When do you find out about an inventory difference today: the same day or at month end?"');
  const P = [
    ['faEyeSlash', 'Undetected losses', 'Found too late to act on'],
    ['faScaleUnbalanced', 'Physical ≠ system', 'Stockouts, overbuying and lost sales'],
    ['faCalendarXmark', 'Expired products', 'Waste and customer risk'],
    ['faUsers', 'Mismatched staffing', 'Queues at peak, idle hours off-peak'],
    ['faFileLines', 'Manual processes', 'Spreadsheets, signatures, supervision hours'],
    ['faPuzzlePiece', 'Fragmented information', 'Every area with its own numbers'],
    ['faUserSlash', 'Cases with no owner', 'Alerts nobody closes']
  ];
  const tw = 2.88, th = 2.42, gx = 0.2;
  P.forEach((p, i) => {
    const x = 0.6 + (i % 4) * (tw + gx), y = 1.6 + Math.floor(i / 4) * (th + 0.22);
    s.rect(x, y, tw, th, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.25, y + 0.25, 0.72, p[0], C.R2, C.RT);
    s.text(x + 0.25, y + 1.1, tw - 0.5, 0.4, p[1], { size: 16, bold: true });
    s.text(x + 0.25, y + 1.5, tw - 0.5, 0.7, p[2], { size: 14, color: C.MUTED });
  });
  const x = 0.6 + 3 * (tw + gx), y = 1.6 + th + 0.22;
  s.rect(x, y, tw, th, { fill: C.R3, r: 0.12, shadow: true });
  iconCircle(s, x + 0.25, y + 0.25, 0.72, 'faArrowTrendDown', C.R3, 'FFD9D6');
  s.text(x + 0.25, y + 1.1, tw - 0.5, 1.2, 'Margin lost without knowing where or why', { size: 17, bold: true, color: C.WHITE });
}

// ===== FLOW =====
{
  const s = content('flow', 'Opening', 'The solution', 'How DAILY connects the operation', 'real',
    'SAY: "Every step of the merchandise leaves a movement in a single stock ledger. When something does not reconcile, DAILY turns it into an alert or a case with an owner." SHOW: follow the ring from Receiving to Corrective actions. BENEFIT: end-to-end traceability. COMMON QUESTION: "Do I need to change my POS?" → No. Sales can be recorded in the DAILY POS or loaded through import or API; the exact integration is validated during the assessment.');
  const N = [['Receiving', 'faTruckRampBox'], ['Inventory', 'faBoxesStacked'], ['Storage', 'faWarehouse'], ['Replenishment', 'faDolly'], ['Sale', 'faCashRegister'], ['Reconciliation', 'faScaleBalanced'], ['Alerts', 'faBell'], ['Corrective actions', 'faListCheck']];
  const cx = 6.67, cy = 4.2, rx = 5.0, ry = 2.0, d = 0.95;
  const pos = N.map((n, i) => { const t = Math.PI - i * Math.PI / 4; return [cx + rx * Math.cos(t), cy - ry * Math.sin(t)]; });
  pos.forEach((p, i) => {
    const q = pos[(i + 1) % 8], dx = q[0] - p[0], dy = q[1] - p[1], L = Math.hypot(dx, dy), k = (d / 2 + 0.12) / L;
    s.line(p[0] + dx * k, p[1] + dy * k, q[0] - dx * k, q[1] - dy * k, { color: i === 7 ? C.Y3 : C.G3, lw: 2, arrow: true, dash: i === 7 });
  });
  pos.forEach((p, i) => {
    const alert = i >= 6;
    s.ellipse(p[0] - d / 2, p[1] - d / 2, d, d, { fill: C.WHITE, line: alert ? C.R2 : C.G2, lineW: 2.5, shadow: true });
    s.icon(p[0] - 0.22, p[1] - 0.22, 0.44, N[i][1], alert ? C.R2 : C.G3);
    s.text(p[0] - 1.1, p[1] + d / 2 + 0.06, 2.2, 0.3, N[i][0], { size: 13, bold: true, align: 'center' });
  });
  s.rect(cx - 1.85, cy - 0.72, 3.7, 1.44, { fill: C.G3, r: 0.16, shadow: true });
  s.text(cx - 1.7, cy - 0.62, 3.4, 0.5, 'DAILY', { size: 24, bold: true, color: C.WHITE, align: 'center', spacing: 2 });
  s.text(cx - 1.7, cy - 0.1, 3.4, 0.7, 'One stock ledger · alerts · cases · KPIs', { size: 13, color: 'D7F5DF', align: 'center' });
}

// ===== MODULE MAP =====
{
  const s = content('modules', 'Opening', 'The platform', 'Ten work areas, one shared data set', 'dev',
    'SAY: "This is the real DAILY menu, grouped the way a chain works: plan and buy, receive and store, sell, dispatch, protect quality, control shrink, prevent loss, organize staff, report and administer." SHOW: the ten areas; the yellow tag marks the parts still in development. BENEFIT: the client sees the full scope before the detail. CLARIFY: what is available, in development and proposed is on the feature status slide.');
  const M = [
    ['faCartShopping', '1. Planning & Procurement', 'MRP, suppliers, orders', 0],
    ['faBoxesStacked', '2. Inventory & Warehousing', 'Stock, ledger, reconciliation, locations', 0],
    ['faCashRegister', '3. Sales & Profitability', 'Sales, margin, POS, orders', 0],
    ['faTruckFast', '4. Dispatches & Returns', 'Dispatches, customer returns', 0],
    ['faAward', '5. Quality & Expiration', 'Lots, FEFO, cold chain, recalls', 0],
    ['faTrashCan', '6. Shrinkage & Waste', 'Recording, approval, causes', 0],
    ['faShieldHalved', '7. Loss Prevention', 'Cases, mobile device control, counts', 1],
    ['faUsers', '8. Workforce Operations', 'Schedules, attendance, tasks', 0],
    ['faChartPie', '9. Reports & Analytics', 'Executive report, reports', 1],
    ['faGears', '10. Administration', 'Branches, users, access, audit', 0]
  ];
  M.forEach((m, i) => {
    const x = 0.6 + (i % 5) * 2.45, y = 1.55 + Math.floor(i / 5) * 2.65;
    s.rect(x, y, 2.3, 2.45, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.22, y + 0.22, 0.66, m[0], C.WHITE, C.G2);
    s.text(x + 0.22, y + 1.0, 1.95, 0.62, m[1], { size: 14, bold: true });
    s.text(x + 0.22, y + 1.62, 1.95, 0.62, m[2], { size: 11.5, color: C.MUTED });
    if (m[3]) s.pill(x + 1.0, y + 0.3, 1.18, 'Partly in development', { fill: C.YT, color: C.Y3, size: 8.5, h: 0.26 });
  });
}

// ===== TODAY =====
{
  const s = content('today', 'Supervision', 'Executive view of the day', 'TODAY: how the operation is doing, hour by hour', 'real',
    'SAY: "A manager opens TODAY and sees sales, target attainment, margin, average ticket and shrink as a share of sales, with the actual curve against the hourly target and the notices that need attention." SHOW: (1) critical decisions for the shift; (2) KPIs against the previous period; (3) actual sales vs. target; (4) notices with direct access. BENEFIT: decide during the day, not at close. CLARIFY: the monthly Executive Dashboard is being redesigned; for the demo we use TODAY and the Executive Loss Report, which read recorded data.');
  const e = s.shot(0.6, 1.5, 8.5, 5.3, '01_ejecutivo/today', [260, 60, 1640]);
  s.pin(e, 248, 175, 1); s.pin(e, 248, 380, 2); s.pin(e, 248, 625, 3); s.pin(e, 1262, 1025, 4);
  callouts(s, 9.4, 1.55, 3.33, [
    ['Critical decisions', 'What needs intervention this shift.'],
    ['KPIs', 'Sales, target, margin, ticket and shrink.'],
    ['Actual vs. target by hour', 'With a projected close for the day.'],
    ['Actionable notices', 'Shrink to approve, alerts and overdue tasks.']
  ], C.R2, 1.3);
}

// ===== ALERTS =====
{
  const s = content('alerts', 'Supervision', 'Alert Center', 'Every deviation with an owner, a deadline and an impact', 'real',
    'SAY: "Each alert has a severity, an owner, a time limit and an estimated impact. The right panel shows progress: detected, assigned, investigation, validation and closure." SHOW: the summary at the top, the list and the panel of the selected alert. BENEFIT: nothing is left without an owner and delays are visible. COMMON QUESTION: "Who receives the alerts?" → They are assigned to an owner and also appear in the mobile web app.');
  flowRow(s, 0.6, 1.4, 12.13, [['Detection', 'faSatelliteDish'], ['Assignment', 'faUserCheck'], ['Investigation', 'faMagnifyingGlass'], ['Validation', 'faClipboardCheck'], ['Closure', 'faCircleCheck']], { size: 13, h: 0.8, gap: 0.26 });
  s.shot(0.6, 2.42, 12.13, 4.38, '08_alertas/alerts', [260, 60, 1630]);
}

// ===== CASES & PROCESSES =====
{
  const s = content('process', 'Supervision', 'Cases and processes', 'Every open document: which step it is in and who has it', 'real',
    'SAY: "DAILY tracks every operational document —purchase order, customer order, shrink, transfer, return, adjustment, count, task— and shows its current step, the next one, the owner and the due date. At the top, the Exception Center groups what got blocked or inconsistent: a cash shift left open, shrink deducted without approval, a transfer never received." SHOW: the exceptions and the counters by case type. BENEFIT: processes stop depending on people\'s memory. CLARIFY: progress is derived from the real state of each document; it is not a manual log.');
  s.shot(0.6, 1.5, 7.6, 5.3, '12_procesos/process_flow', [255, 160, 1645]);
  callouts(s, 8.5, 1.55, 4.23, [
    ['Exception Center', 'Blocked or inconsistent operations, with value at stake.'],
    ['Ten case types', 'Purchase, order, shrink, transfer, return, adjustment, count, task and more.'],
    ['Step, owner and deadline', 'Overdue items are flagged in red.']
  ], C.G3, 1.45);
  note(s, 8.5, 6.1, 4.23, 'Progress comes from the real state of each document.', 'G');
}

// ===== CASE 1: STEP 1 =====
{
  const s = content('step1', 'End-to-end case: inventory difference', 'Case 1 · High-value product', '8 phones expected; the count found 6', 'real',
    'SAY: "Let\'s follow a case from start to finish. The system expects 8 units of a high-value phone in the store; the physical count records 6. When the count is saved, DAILY calculates the difference and its value and opens case DEMO-001." SHOW: the red notice on the product record: difference, value, audit interval, priority and owner. BENEFIT: the difference appears when counting, not at close. CLARIFY: demo case on fictitious data, executed with the real logic. The difference appears when the count is recorded; it is not automatic real-time tracking.');
  stepper(s, 0);
  stat(s, 0.6, 1.85, 1.75, '8', 'expected');
  stat(s, 0.6, 3.1, 1.75, '6', 'counted');
  stat(s, 0.6, 4.35, 1.75, '−2', 'difference', C.R2);
  stat(s, 0.6, 5.6, 1.75, '$1,040', 'value at risk', C.R2);
  const e = s.shot(2.65, 1.85, 10.08, 4.87, '03_inventario/conciliacion', [560, 455, 1340]);
  s.ring(e, 566, 612, 1894, 736);
  s.demoTag(9.55, 2.0);
}

// ===== CASE 1: STEP 2 =====
{
  const s = content('step2', 'End-to-end case: inventory difference', 'Case 1 · High-value product', 'Every product movement, with document and user', 'real',
    'SAY: "The owner does not search through spreadsheets: they open the product ledger. They see receipts, POS sales, transfers and adjustments, with date, document, lot, user and valued balance, plus the reconciliation for the period." SHOW: the period summary and the ledger lines. BENEFIT: the investigation starts from recorded movements. COMMON QUESTION: "Can movements be altered?" → The history is sealed and its integrity can be verified on demand.');
  stepper(s, 1);
  s.shot(0.6, 1.85, 8.3, 4.95, '03_inventario/kardex_producto', [260, 1350, 1640]);
  callouts(s, 9.2, 1.9, 3.53, [
    ['Valuated Inventory Ledger', 'Inflows, outflows and balance, at cost.'],
    ['Source document', 'POS ticket, receipt or adjustment: one click.'],
    ['Findings', 'Possible duplicates, shrink with no case, off-hours.'],
    ['Sealed history', 'Integrity verification on demand.']
  ], C.G3, 1.2);
}

// ===== CASE 1: STEP 3 =====
{
  const s = content('step3', 'End-to-end case: inventory difference', 'Case 1 · High-value product', 'Investigation record: interval, causes and recount', 'real',
    'SAY: "The record bounds the audited interval between the last correct count and the current one, counts the movements in that window and evaluates causes against the evidence. It does not presume theft. It asks for an independent recount and, if confirmed, a different supervisor authorizes the adjustment." SHOW: the interval, the cause evaluation and the two resolution blocks. BENEFIT: documented causes instead of assumptions, with segregation of duties. CLARIFY: DAILY guides the investigation; a person decides the cause and the adjustment.');
  stepper(s, 2);
  s.shot(0.6, 1.85, 6.3, 4.95, '03_inventario/conciliacion_ficha', [512, 28, 900]);
  callouts(s, 7.2, 1.9, 5.53, [
    ['Audited interval', 'From the last verified count to the current one, with the movements in that window.'],
    ['Evidence-based evaluation', 'Cause hypotheses; it does not presume theft.'],
    ['Independent recount', 'Confirms or rules out the difference.'],
    ['Separate authorization', 'A different supervisor approves the adjustment.']
  ], C.G3, 1.2);
}

// ===== CASE 1: STEP 4 =====
{
  const s = content('step4', 'End-to-end case: inventory difference', 'Case 1 · High-value product', 'The case stays in a file until it is closed', 'real',
    'SAY: "The case enters the Loss Prevention Center along with the others: product, store, zone, difference, value at risk, priority, status and owner. It can be resolved, sent to recount or escalated, and every step is kept in the case history." SHOW: the incidents and cases table; on the right, the progress of an alert. BENEFIT: nobody adjusts inventory without leaving a trail. CLARIFY: the header KPIs of the Loss Prevention Center still show sample values; the cases table does read the recorded cases.');
  stepper(s, 3);
  s.shot(0.6, 1.85, 8.0, 4.95, '11_prevencion/lp', [275, 648, 1600]);
  label(s, 8.9, 1.85, 3.8, 'Progress of each alert');
  s.shot(8.9, 2.17, 3.83, 3.9, '08_alertas/alerts', [1362, 560, 560]);
  s.text(8.9, 6.2, 3.83, 0.6, 'Detected → assigned → investigation → validation → closed.', { size: 12, color: C.MUTED });
}

// ===== CASE 1: SUMMARY =====
{
  const s = content('case1sum', 'End-to-end case: inventory difference', 'Case 1 · Summary', 'From count to closure: six steps, four modules', 'real',
    'SAY: "In short: the count detects, reconciliation calculates, the ledger explains, the record investigates, the supervisor authorizes and the case closes. Four modules working on the same data." SHOW: the sequence and the module involved in each step. BENEFIT: a repeatable, auditable process for every difference. ASK THE CLIENT: "How many steps of this process do you do outside the system today?"');
  const T = [['Physical count', 'faClipboardCheck', 'Physical Inventory Counts'], ['Difference & value', 'faScaleUnbalanced', 'Inventory Reconciliation'], ['Movements', 'faRightLeft', 'Inventory Ledger'], ['Investigation', 'faMagnifyingGlass', 'Case record'], ['Authorization', 'faUserShield', 'Different supervisor'], ['Closure', 'faCircleCheck', 'Loss Prevention']];
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.05, last = i === T.length - 1;
    s.rect(x, 1.8, 1.85, 3.2, { fill: last ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.55, 2.05, 0.75, t[1], last ? C.G3 : C.WHITE, last ? C.WHITE : C.G2);
    s.text(x + 0.12, 3.0, 1.61, 0.3, String(i + 1), { size: 22, bold: true, color: last ? C.WHITE : 'B8C2BC', align: 'center' });
    s.text(x + 0.12, 3.45, 1.61, 0.7, t[0], { size: 15, bold: true, align: 'center', color: last ? C.WHITE : C.INK });
    s.text(x + 0.12, 4.2, 1.61, 0.6, t[2], { size: 12, align: 'center', color: last ? 'D7F5DF' : C.G3, bold: true });
    if (!last) s.line(x + 1.88, 3.4, x + 2.02, 3.4, { color: C.G3, lw: 2, arrow: true });
  });
  stat(s, 0.6, 5.4, 3.9, 'DEMO-001', 'case file');
  stat(s, 4.72, 5.4, 3.9, '−2 u. · $1,040', 'difference and value', C.R2);
  stat(s, 8.83, 5.4, 3.9, '2 controls', 'recount and approval before adjusting', C.G3);
}

// ===== SHRINK =====
{
  const s = content('waste', 'Shrinkage & Waste', 'Shrinkage & Waste', 'How much is lost, where and why', 'real',
    'SAY: "The Shrinkage & Waste control board shows total shrink and shrink as a share of sales against target, by type, department, product and cause, plus the trend by branch." SHOW: KPIs, evolution against target, breakdown by type and by department. BENEFIT: attack the causes that cost the most. COMMON QUESTION: "Who approves shrink?" → Someone other than the person who records it; until then, stock is not deducted.');
  s.shot(0.6, 1.5, 8.5, 5.3, '02_mermas/waste_centro-control', [260, 440, 1640]);
  callouts(s, 9.4, 1.55, 3.33, [
    ['Shrink vs. target', 'Total, as % of sales and avoidable.'],
    ['By type and area', 'Operational, commercial, damage, adjustments.'],
    ['Products and causes', 'Top 10 and main causes.'],
    ['By branch', 'Trend against the previous period.']
  ], C.R2, 1.3);
}

// ===== SHRINK: TOOLS =====
{
  const s = content('waste2', 'Shrinkage & Waste', 'Shrinkage & Waste', 'Approval, causes, investigation and prevention', 'real',
    'SAY: "Around the board there are four tools: the approvals inbox, the cause matrix with Pareto, investigations with action plans and expiry prevention with FEFO." SHOW: the four screens. BENEFIT: shrink records turn into decisions. ASK THE CLIENT: "Who approves shrink today, and how long does it take?"');
  const G = [['02_mermas/waste_approvals', 'Approvals', FULLW], ['02_mermas/waste_causales', 'Causes and Pareto', [250, 420, 1650]], ['02_mermas/waste_investigaciones', 'Investigations and action plans', [250, 420, 1650]], ['02_mermas/waste_fefo', 'Expiry prevention (FEFO)', [250, 420, 1650]]];
  G.forEach((g, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.45 + Math.floor(i / 2) * 2.74;
    s.shot(x, y, 5.98, 2.28, g[0], g[2]);
    s.text(x, y + 2.33, 5.98, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== INVENTORY =====
{
  const s = content('stock', 'Inventory', 'Inventory & Warehousing', 'What is on hand, what it is worth and what is at risk', 'real',
    'SAY: "Stock & Movements shows value, units, turnover and coverage; below, items under minimum, close to expiry, out of stock and value at risk, each with a suggested action." SHOW: the risk cards and recent movements. BENEFIT: the inventory owner prioritizes with data. COMMON QUESTION: "Can I see it by branch?" → Yes, with filters by branch, department and category.');
  s.shot(0.6, 1.5, 7.9, 5.3, '03_inventario/stockoverview', [260, 90, 1640]);
  label(s, 8.8, 1.5, 3.9, 'Valuated Inventory Ledger');
  s.shot(8.8, 1.82, 3.93, 2.2, '03_inventario/kardex', [260, 290, 1000]);
  label(s, 8.8, 4.2, 3.9, 'Inventory control');
  s.shot(8.8, 4.52, 3.93, 2.28, '03_inventario/inventory_control', FULLW);
}

// ===== WAREHOUSE =====
{
  const s = content('wms', 'Inventory', 'Warehousing', 'Locations, transfers, counts and scanning', 'real',
    'SAY: "The warehouse is run by location: zone map, store-to-store transfers with verification, physical counts and scanner operations." SHOW: the four tools. BENEFIT: every movement is tied to a location and an owner. COMMON QUESTION: "Which scanners does it support?" → Phone camera and keyboard-mode scanners; the exact model is validated in the pilot.');
  const G = [['03_inventario/location_map', 'Location map'], ['03_inventario/transfers', 'Store-to-store transfers'], ['03_inventario/scan', 'Scanner operations'], ['03_inventario/inventory', 'Physical Inventory Counts']];
  G.forEach((g, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.45 + Math.floor(i / 2) * 2.74;
    s.shot(x, y, 5.98, 2.28, g[0], FULLW);
    s.text(x, y + 2.33, 5.98, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== LOSS PREVENTION =====
{
  const s = content('prevention', 'Loss Prevention', 'Loss Prevention · Mobile Device Control', 'High-value products, controlled unit by unit', 'dev',
    'SAY: "For phones and other high-value products, DAILY tracks each unit by IMEI or serial number: where it is, its status and who holds it. Units not located open an investigation. It also schedules sensitive counts with due dates and recounts." SHOW: the device statuses and the table with custody and last movement. BENEFIT: unit-level control where a single loss is costly. CLARIFY: cases accept CCTV references as evidence, but there is no camera integration. Some header KPIs of the Loss Prevention Center still show sample values.');
  s.shot(0.6, 1.5, 7.9, 5.3, '11_prevencion/lp_devices', [275, 320, 1600]);
  callouts(s, 8.8, 1.55, 3.93, [
    ['Custody by IMEI', 'Location, status and owner of each device.'],
    ['Not located', 'Flagged and sent to investigation.'],
    ['Sensitive counts', 'Tasks with due dates; overdue ones in view.'],
    ['Evidence in the case', 'Notes, photos and CCTV references.']
  ], C.G3, 1.12);
  note(s, 8.8, 6.18, 3.93, 'In development: header KPIs of the Loss Prevention Center.');
}

// ===== LOSS REPORT =====
{
  const s = content('lossreport', 'Loss Prevention', 'Reports', 'Executive Loss Report: a daily snapshot for leadership', 'real',
    'SAY: "Every day a consolidated loss report is produced from the same sources as Shrink and Inventory: confirmed loss, shrink as a share of sales, differences under investigation, priorities with owners and branch comparison. It states whether the cut is preliminary and which stores did not sync." SHOW: the KPIs, the priorities table and the sync status. BENEFIT: leadership gets the same number the operation sees. COMMON QUESTION: "Can it be exported?" → Yes, as PDF and Excel; daily, weekly, monthly or yearly.');
  s.shot(0.6, 1.5, 8.3, 5.3, '02_mermas/loss_executive', [260, 60, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Traceable snapshot', 'States source, time and synced stores.'],
    ['Loss by component', 'Operational, commercial, adjustments, theft.'],
    ['Priorities of the day', 'What happened, cause, evidence and owner.']
  ], C.G3, 1.45);
  note(s, 9.2, 6.18, 3.53, 'Daily · weekly · monthly · yearly · PDF and Excel', 'G');
}

// ===== SALES =====
{
  const s = content('sales', 'Sales', 'Sales & Profitability', 'What sells, what it earns and where the gap is', 'real',
    'SAY: "Net sales, target attainment, profit, margin, transactions and ticket, against the previous period; below, the curve against target, the share by department and performance by branch." SHOW: KPIs, curve and departments. BENEFIT: see the gap to target in time. COMMON QUESTION: "Does it reconcile with inventory?" → Yes; the data quality block shows whether each sale has a cost and a reconciled movement.');
  s.shot(0.6, 1.5, 8.3, 5.3, '05_ventas/sales', [260, 520, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Sales and margin', 'Against previous period and target.'],
    ['Curve and progress', 'Daily evolution and gap to target.'],
    ['Departments', 'What drives sales and what does not.']
  ], C.G3, 1.4);
  note(s, 9.2, 5.95, 3.53, 'Sales reconciled with cost and inventory movement.', 'G');
}

// ===== PURCHASING =====
{
  const s = content('purchasing', 'Procurement', 'Planning & Procurement', 'From need to purchase order, in a 12-phase cycle', 'real',
    'SAY: "The MRP calculates, product by product, net stock, daily sales, coverage, safety stock and reorder point, and suggests quantity, cost and supplier. Orders go through approval, issue, follow-up and receiving, and supplier performance is measured on lead time and fulfilment." SHOW: the cycle KPIs, the 12 phases and the list of needs. BENEFIT: buy what is needed, on time. CLARIFY: suggested orders require approval by a person; forecast accuracy is shown as is, to be improved with the client\'s data.');
  s.shot(0.6, 1.5, 8.3, 5.3, '09_compras/mrp', [260, 60, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['MRP needs', 'Coverage, safety stock and reorder point.'],
    ['Orders with approval', 'Draft, approval, issue and follow-up.'],
    ['Measured suppliers', 'On-time delivery, lead time and fill rate.']
  ], C.G3, 1.45);
}

// ===== STAFF =====
{
  const s = content('staff', 'Workforce', 'Workforce Operations', 'Shifts, coverage and labor cost on one board', 'real',
    'SAY: "Schedules & Coverage compares projected sales with labor hours, measures productivity by department and labor cost against budget, and shows the weekly and monthly schedule." SHOW: the chart and the schedule. BENEFIT: spot overstaffing and coverage gaps before publishing the schedule. CLARIFY: the generator rules are configurable and must be validated with the client\'s legal team.');
  label(s, 0.6, 1.45, 7.6, 'Projected sales vs. hours, productivity and labor cost');
  s.shot(0.6, 1.76, 7.6, 2.62, '06_personal/scheduling', [260, 590, 1640]);
  label(s, 0.6, 4.5, 7.6, 'Weekly schedule');
  s.shot(0.6, 4.82, 7.6, 1.98, '06_personal/scheduling', [260, 1060, 1640]);
  callouts(s, 8.6, 1.55, 4.13, [
    ['Demand vs. hours', 'Projected sales against scheduled hours.'],
    ['Cost vs. budget', 'Wages, burden and overtime.'],
    ['Coverage by area', 'Minimum staffing alerts.'],
    ['Configurable rules', 'To be validated with legal.']
  ], C.G3, 1.25);
}

// ===== PRODUCTIVITY =====
{
  const s = content('staff2', 'Workforce', 'Productivity', 'Attendance, tasks and demand-based planning', 'mix',
    'SAY: "Today DAILY records attendance, late arrivals and floor tasks, and compares projected sales with labor hours. What does not exist yet is a time-and-motion study or automatic shift generation straight from demand." SHOW: on the left what is available; on the right the concept. BENEFIT: daily follow-up of the real work. CLARIFY: the projected column is a concept; do not present it as a finished feature.');
  label(s, 0.6, 1.5, 4, 'Attendance and clock-ins');
  s.shot(0.6, 1.82, 4.05, 2.3, '06_personal/attendance', [260, 60, 1620]);
  label(s, 0.6, 4.25, 4, 'Floor tasks');
  s.shot(0.6, 4.57, 4.05, 2.23, '06_personal/workforce', [260, 60, 1620]);
  s.rect(4.95, 1.5, 3.65, 5.3, { fill: C.GT, r: 0.12 });
  s.text(5.15, 1.62, 3.3, 0.35, 'Available today', { size: 16, bold: true, color: C.GD });
  s.text(5.15, 2.05, 3.3, 4.6, [{ text: 'Scheduled and worked hours', bullet: true }, { text: 'Late arrivals and absences', bullet: true }, { text: 'Tasks with owners', bullet: true }, { text: 'Productivity by department', bullet: true }, { text: 'Projected sales vs. hours', bullet: true }], { size: 15, paraAfter: 8 });
  s.rect(8.85, 1.5, 3.88, 5.3, { fill: C.WHITE, line: C.Y2, lineW: 1.5, dash: true, r: 0.12 });
  s.text(9.05, 1.62, 3.5, 0.35, 'Projected (concept)', { size: 16, bold: true, color: C.Y3 });
  s.text(9.05, 2.05, 3.5, 2.3, [{ text: 'Time-and-motion study', bullet: true }, { text: 'Shifts generated from demand', bullet: true }], { size: 15, paraAfter: 8 });
  const days = [[9, 55], [11, 90], [13, 120], [15, 80], [17, 110], [19, 70]];
  days.forEach((d, i) => {
    const x = 9.1 + i * 0.57, base = 6.3, h = d[1] / 120 * 1.5;
    s.rect(x, base - h, 0.42, h, { fill: d[1] >= 110 ? C.Y2 : 'E5E7EB' });
    s.text(x - 0.05, base + 0.05, 0.52, 0.25, d[0] + 'h', { size: 10, color: C.MUTED, align: 'center' });
  });
  s.text(9.05, 4.3, 3.5, 0.3, 'Example: extra staff at peak hours', { size: 12, color: C.MUTED, italic: true });
}

// ===== QUALITY =====
{
  const s = content('quality', 'Quality', 'Quality & Expiration Management', 'Act before the product expires', 'real',
    'SAY: "DAILY identifies expired or soon-to-expire lots, with location and value at risk, and follows a handling path: review, consider markdown, isolate, decide disposition and log temperature." SHOW: lots that need attention, the matrix and the path. BENEFIT: less waste and better customer protection. COMMON QUESTION: "Does it monitor the cold chain?" → It records temperatures and excursions per cold room; automatic capture with sensors is defined per project.');
  flowRow(s, 0.6, 1.45, 12.13, [['Close to expiry', 'faHourglassHalf', C.Y2], ['Identification', 'faMagnifyingGlass', C.Y2], ['Markdown or isolation', 'faTags', C.Y2], ['Authorized disposition', 'faUserCheck', C.G2], ['Shrink record', 'faListCheck', C.G2]], { size: 13 });
  s.shot(0.6, 2.55, 7.6, 4.25, '04_calidad/quality', [250, 78, 1650]);
  label(s, 8.5, 2.55, 4.2, 'Lot matrix and cold chain');
  s.shot(8.5, 2.87, 4.23, 3.93, '04_calidad/quality', [260, 800, 1640]);
}

// ===== CASE 2 =====
{
  const s = content('case2', 'End-to-end case: expiry', 'Case 2 · Perishable product', 'From a lot about to expire to an approved shrink record', 'real',
    'SAY: "Second case: a lot of milk expires. Quality flags it in the matrix; TODAY and the Alert Center show it as a notice with an owner; if it does not sell at a markdown, shrink is recorded; a different person approves it and only then is it deducted from stock; the shrink board and the executive report reflect it." SHOW: the four screens, left to right. BENEFIT: expiry is managed as a process, not a surprise. CLARIFY: automatic blocking of expired lots at the POS exists as a rule, but it is disabled until defined with each client.');
  const T = [['1. Expired lot', 'Quality', '04_calidad/quality', [260, 820, 1120]], ['2. Notice with owner', 'TODAY', '01_ejecutivo/today', [1270, 1010, 630]], ['3. Shrink to approve', 'Approvals', '02_mermas/waste_approvals', [250, 40, 1000]], ['4. Decision and cause', 'Shrinkage & Waste', '02_mermas/waste_centro-control', [1330, 1490, 580]]];
  T.forEach((t, i) => {
    const x = 0.6 + i * 3.08, w = 2.86;
    s.rect(x, 1.45, w, 0.62, { fill: i === 3 ? C.G3 : C.WHITE, line: C.LINE, r: 0.1 });
    s.text(x + 0.15, 1.45, w - 0.3, 0.62, t[0], { size: 15, bold: true, valign: 'middle', color: i === 3 ? C.WHITE : C.INK });
    s.shot(x, 2.3, w, 3.6, t[2], t[3]);
    s.text(x, 6.0, w, 0.3, t[1], { size: 13, bold: true, color: C.G3, align: 'center' });
    if (i < 3) s.line(x + w + 0.03, 1.76, x + 3.05, 1.76, { color: C.G3, lw: 2, arrow: true });
  });
  s.text(0.6, 6.4, 12.1, 0.35, 'One rule: shrink does not reduce stock until someone other than the recorder approves it.', { size: 13, color: C.MUTED, align: 'center', italic: true });
}

// ===== MULTI-BRANCH =====
{
  const s = content('branches', 'Multi-branch', 'Multi-branch Operations', 'Every store, measured with the same KPIs', 'real',
    'SAY: "Each branch is compared on the same KPIs: sales, margin, ticket, shrink as a share of sales, open cases and value about to expire. A branch above the corporate shrink cap is flagged in red." SHOW: the cards; the demo network has ten stores. BENEFIT: management sees the network on one screen. CLARIFY: fictitious branches and figures; these are not client results.');
  s.shot(0.6, 1.5, 8.9, 5.3, '07_multisucursal/branches', [260, 180, 1640]);
  callouts(s, 9.8, 1.55, 2.93, [
    ['Same metrics', 'Sales, margin, shrink, cases and expiry.'],
    ['Corporate cap', 'Stores above it are flagged.'],
    ['From total to detail', 'From branch to stock, shrink and sales.']
  ], C.R2, 1.5);
  s.pill(9.8, 6.32, 2.93, 'Fictitious demo branches', { fill: C.YT, color: C.Y3, size: 10.5, h: 0.32 });
}

// ===== MOBILE =====
{
  const s = content('mobile', 'Mobile', 'Mobile experience', 'The operation, from the team\'s phone', 'real',
    'SAY: "Employees see their priorities, tasks and alerts, scan products, check inventory and handle cases from the mobile web app." SHOW: the six screens. BENEFIT: records are made where the work happens. COMMON QUESTION: "Is it in the app stores?" → Today it runs in the phone\'s browser; app store publishing is not included.');
  const P = [['m_home', 'Today'], ['m_tasks', 'Tasks'], ['m_scan', 'Scanner'], ['m_notifications', 'Alerts'], ['m_inv', 'Inventory'], ['m_waste', 'Shrink']];
  P.forEach((p, i) => {
    const x = 0.68 + i * 2.05, w = 1.9;
    s.phone(x, 1.5, w, '10_movil/' + p[0]);
    s.text(x, 5.72, w, 0.3, p[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.2, 12.1, 0.6, 'Subject to the features enabled for each role. Mobile web app; not published in app stores.', { size: 13, color: C.MUTED, align: 'center' });
}

// ===== CONNECTED MODULES =====
{
  const s = content('connect', 'Integration', 'Connected modules', 'One movement feeds every module', 'real',
    'SAY: "What sets DAILY apart is that modules are not islands. Receiving enters the ledger; a sale deducts it; approved shrink adjusts stock and appears in the report; a difference opens a case; projected sales feed the comparison with hours; and everything reaches TODAY and the reports." SHOW: the diagram and the six connections. BENEFIT: one data set, without manual reconciliations between areas. ASK THE CLIENT: "How many spreadsheets do you cross-check today to close the month?"');
  const N = [['Procurement', 'faCartShopping'], ['Inventory', 'faBoxesStacked'], ['Sales', 'faCashRegister'], ['Shrinkage', 'faTrashCan'], ['Loss Prev.', 'faShieldHalved'], ['Workforce', 'faUsers'], ['Reports', 'faChartPie']];
  const cx = 3.75, cy = 4.15, r = 2.25, d = 0.92;
  const pos = N.map((n, i) => { const t = -Math.PI / 2 + i * 2 * Math.PI / N.length; return [cx + r * Math.cos(t), cy + r * Math.sin(t) * 0.98]; });
  pos.forEach(p => { const dx = p[0] - cx, dy = p[1] - cy, L = Math.hypot(dx, dy); s.line(cx + dx * 0.9 / L, cy + dy * 0.62 / L, p[0] - dx * (d / 2 + 0.05) / L, p[1] - dy * (d / 2 + 0.05) / L, { color: C.G2, lw: 2 }); });
  s.rect(cx - 1.05, cy - 0.6, 2.1, 1.2, { fill: C.G3, r: 0.14, shadow: true });
  s.text(cx - 1.0, cy - 0.55, 2.0, 1.1, 'One stock ledger\n+ alerts and cases', { size: 13, bold: true, color: C.WHITE, align: 'center', valign: 'middle' });
  pos.forEach((p, i) => {
    s.ellipse(p[0] - d / 2, p[1] - d / 2, d, d, { fill: C.WHITE, line: C.G2, lineW: 2.5, shadow: true });
    s.icon(p[0] - 0.21, p[1] - 0.3, 0.42, N[i][1], C.G3);
    s.text(p[0] - 0.8, p[1] + 0.12, 1.6, 0.25, N[i][0], { size: 10.5, bold: true, align: 'center' });
  });
  const E = [
    ['Procurement → Inventory', 'The order receipt enters stock and the ledger.'],
    ['Sales → Inventory', 'Each ticket deducts its lot; FEFO at the POS.'],
    ['Inventory → Shrinkage', 'Approved shrink adjusts stock and enters the report.'],
    ['Inventory → Loss Prevention', 'A count difference opens a case.'],
    ['Sales → Workforce', 'Projected sales against scheduled hours.'],
    ['All → Reports', 'TODAY, alerts and the executive report read the same data.']
  ];
  E.forEach((e, i) => {
    const y = 1.55 + i * 0.88;
    s.rect(7.2, y, 5.53, 0.76, { fill: C.WHITE, line: C.LINE, r: 0.1 });
    s.text(7.38, y + 0.04, 2.3, 0.68, e[0], { size: 13, bold: true, color: C.G3, valign: 'middle' });
    s.text(9.6, y + 0.04, 3.05, 0.68, e[1], { size: 12, color: C.INK, valign: 'middle' });
  });
}

// ===== MARKET COMPARISON =====
{
  const s = content('compare', 'Integration', 'Market reference', 'Capabilities large chains rely on, and where DAILY stands', 'mix',
    'SAY: "Large chains —for example Walmart or Costco— operate with well-known practices: perpetual inventory, cycle counts, loss prevention teams, forecast-based replenishment, workforce planning and freshness control. This table compares capabilities, not products: it shows what DAILY covers today and what is missing." SHOW: the status column. BENEFIT: an honest conversation about scope. CLARIFY: DAILY has no integration, partnership or relationship with those companies; we make no claims about how their internal systems are implemented. Their trademarks belong to their owners.');
  sub(s, 'Common practices in large-scale chains (e.g., Walmart, Costco), based on general public information.');
  const R = [
    ['Perpetual inventory by store', 'Stock and valued ledger per branch, with verifiable history', 'D'],
    ['Cycle and high-value counts', 'Physical counts, sensitive counts and IMEI custody', 'D'],
    ['Loss prevention', 'Cases, recount and authorization; CCTV as reference only', 'P'],
    ['Forecast-based replenishment', 'MRP with reorder point and suggested orders with approval', 'P'],
    ['Demand-based staffing', 'Hours vs. projected sales; shifts from demand: not yet', 'P'],
    ['Freshness and expiry', 'Lots, FEFO, quarantine and recalls', 'D'],
    ['Leadership dashboards', 'TODAY and Executive Report; Executive Dashboard in redesign', 'P'],
    ['Scale and integrations', '10 demo branches; import and API', 'P']
  ];
  const y0 = 1.8, rh = 0.52;
  s.rect(0.6, y0, 12.13, 0.46, { fill: C.G3, r: 0.06 });
  s.text(0.8, y0, 3.9, 0.46, 'Capability', { size: 13, bold: true, color: C.WHITE, valign: 'middle' });
  s.text(4.8, y0, 5.9, 0.46, 'How DAILY handles it today', { size: 13, bold: true, color: C.WHITE, valign: 'middle' });
  s.text(10.8, y0, 1.8, 0.46, 'Status', { size: 13, bold: true, color: C.WHITE, valign: 'middle', align: 'center' });
  R.forEach((r, i) => {
    const y = y0 + 0.52 + i * rh;
    s.rect(0.6, y, 12.13, rh - 0.06, { fill: i % 2 ? C.WHITE : 'FAFBFA', line: C.LINE, r: 0.04 });
    s.text(0.8, y, 3.9, rh - 0.06, r[0], { size: 13, bold: true, valign: 'middle' });
    s.text(4.8, y, 5.9, rh - 0.06, r[1], { size: 12, color: C.MUTED, valign: 'middle' });
    const k = r[2] === 'D' ? ['Available', C.GT, C.GD] : ['Partial', C.YT, C.Y3];
    s.pill(11.05, y + 0.1, 1.4, k[0], { fill: k[1], color: k[2], size: 11, h: 0.3 });
  });
  s.text(0.6, 6.62, 12.1, 0.25, 'DAILY is not integrated, partnered or affiliated with the companies mentioned. Trademarks belong to their respective owners.', { size: 10.5, color: C.MUTED, italic: true });
}

// ===== FEATURE STATUS =====
{
  const s = content('status', 'Integration', 'Transparency', 'What exists, what is in development and what is proposed', 'mix',
    'SAY: "So the decision is informed: on the left what works today and you saw on screen; in the middle what is in development; on the right what we propose to build with the client." SHOW: the three columns. BENEFIT: trust; we do not sell what does not exist. CLARIFY: nothing in the right column should be presented as available.');
  const COLS = [
    ['Available today', C.GT, C.GD, C.G3, ['TODAY, Alert Center, Cases', 'Stock, ledger, reconciliation, warehouse', 'Shrinkage & Waste: board, approval, causes', 'Loss Prevention: cases, IMEI, counts', 'Executive loss report', 'Sales, POS, MRP and suppliers', 'Schedules, attendance and tasks', 'Quality, FEFO and recalls', 'Multi-branch and mobile web', 'English and Spanish interface']],
    ['In development', C.YT, C.Y3, C.Y2, ['Monthly Executive Dashboard (redesign)', 'Header KPIs: Loss Prevention, Merchandise Control', 'Workflow engine (no UI yet)', 'Unified Action Center', 'Expired-lot blocking at POS (rule inactive)', 'Live POS integration (today import and API)']],
    ['Proposed', C.RT, C.R3, C.R2, ['Occupational health and safety', 'Time-and-motion study', 'Shifts generated from demand', 'Automatic cold-chain sensors', 'CCTV integration', 'App-store mobile app']]
  ];
  COLS.forEach((c, i) => {
    const x = 0.6 + i * 4.11, w = 3.91;
    s.rect(x, 1.5, w, 5.3, { fill: c[1], r: 0.14 });
    s.ellipse(x + 0.25, 1.7, 0.3, 0.3, { fill: c[3] });
    s.text(x + 0.68, 1.62, w - 0.8, 0.45, c[0], { size: 19, bold: true, color: c[2] });
    s.text(x + 0.25, 2.25, w - 0.45, 4.45, c[4].map(t => ({ text: t, bullet: true })), { size: 14, paraAfter: 7 });
  });
}

// ===== A DAY =====
{
  const s = content('day', 'Value', 'Illustrative scenario', 'A day with DAILY', 'demo',
    'SAY: walk through the day left to right, one screen per moment. SHOW: that the manager and the team use the same information. BENEFIT: DAILY supports the whole working day, not just the close. CLARIFY: this is an illustrative scenario; times and sequence vary in each company.');
  const T = [['07:00', 'Start of operations', '01_ejecutivo/today', [260, 60, 640]], ['09:00', 'Receiving and scanning', '03_inventario/scan', [260, 60, 640]], ['11:00', 'Inventory at risk', '03_inventario/stockoverview', [260, 110, 640]], ['13:00', 'Shifts and coverage', '06_personal/scheduling', [260, 380, 640]], ['15:00', 'Alert review', '08_alertas/alerts', [260, 60, 640]], ['18:00', 'Daily report', '02_mermas/loss_executive', [260, 60, 640]]];
  s.line(0.8, 1.95, 12.5, 1.95, { color: C.G2, lw: 3 });
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.04, w = 1.93;
    s.ellipse(x + w / 2 - 0.11, 1.84, 0.22, 0.22, { fill: C.G3, line: C.WHITE, lineW: 2 });
    s.text(x, 1.4, w, 0.36, t[0], { size: 17, bold: true, color: C.G3, align: 'center' });
    s.shot(x, 2.3, w, 3.3, t[2], t[3]);
    s.text(x, 5.7, w, 0.6, t[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.45, 12.1, 0.3, 'Illustrative scenario.', { size: 13, color: C.MUTED, align: 'center', italic: true });
}

// ===== BENEFITS =====
{
  const s = content('benefits', 'Value', 'Benefits', 'Six benefits, six real tools', 'real',
    'SAY: each benefit rests on a tool already shown: loss reduction, traceability, productivity, financial control, expiry prevention and KPI-driven decisions. SHOW: the screen on each card. BENEFIT: value tied to the product, without abstract promises. ASK THE CLIENT: "Which of these six is most urgent for you?"');
  const B = [
    ['Loss reduction', 'Shrinkage and Loss Prevention', '02_mermas/waste_centro-control', [260, 440, 1640]],
    ['End-to-end traceability', 'Ledger and Reconciliation', '03_inventario/kardex_producto', [260, 1350, 1640]],
    ['Team productivity', 'Workforce Operations', '06_personal/scheduling', [260, 590, 1640]],
    ['Financial control', 'Sales and Loss report', '05_ventas/sales', [260, 520, 1640]],
    ['Expiration prevention', 'Quality and FEFO', '04_calidad/quality', [250, 78, 1650]],
    ['KPI-driven decisions', 'TODAY and alerts', '01_ejecutivo/today', [260, 60, 1640]]
  ];
  B.forEach((b, i) => {
    const x = 0.6 + (i % 3) * 4.11, y = 1.5 + Math.floor(i / 3) * 2.72;
    s.rect(x, y, 3.91, 2.58, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.img(x + 0.1, y + 0.1, 3.71, 1.5, b[2], b[3]);
    s.text(x + 0.15, y + 1.67, 3.61, 0.5, b[0], { size: 15, bold: true });
    s.text(x + 0.15, y + 2.17, 3.61, 0.3, b[1], { size: 13, color: C.G3, bold: true });
  });
}

// ===== BEFORE AND AFTER =====
{
  const s = content('beforeafter', 'Value', 'Before and after', 'From scattered records to operational control', 'demo',
    'SAY: "We do not promise a figure. We show the change in the way of working: from separate records to centralized information, with owners." SHOW: the two columns, row by row. BENEFIT: a process change that is then measured in the pilot. CLARIFY: no financial results are guaranteed.');
  const A = [['faFileLines', 'Separate records'], ['faPenToSquare', 'Manual validations'], ['faEyeSlash', 'Differences at month end'], ['faUserSlash', 'Cases with no owner']];
  const D = [['faDatabase', 'One stock ledger'], ['faRoute', 'Traceability by document'], ['faBell', 'Differences when counting'], ['faUserCheck', 'Cases with owner and deadline']];
  s.rect(0.6, 1.5, 5.9, 5.3, { fill: C.RT, r: 0.14 });
  s.rect(6.83, 1.5, 5.9, 5.3, { fill: C.GT, r: 0.14 });
  s.text(0.9, 1.65, 5.3, 0.5, 'Before', { size: 24, bold: true, color: C.R3 });
  s.text(7.13, 1.65, 5.3, 0.5, 'With DAILY', { size: 24, bold: true, color: C.GD });
  A.forEach((a, i) => {
    iconCircle(s, 0.95, 2.4 + i * 1.08, 0.72, a[0], C.WHITE, C.R2);
    s.text(1.9, 2.4 + i * 1.08, 4.4, 0.72, a[1], { size: 20, bold: true, valign: 'middle' });
  });
  D.forEach((a, i) => {
    iconCircle(s, 7.18, 2.4 + i * 1.08, 0.72, a[0], C.WHITE, C.G3);
    s.text(8.13, 2.4 + i * 1.08, 4.4, 0.72, a[1], { size: 20, bold: true, valign: 'middle' });
  });
}

// ===== IMPACT =====
{
  const s = content('impact', 'Value', 'Financial impact', 'Half a point of shrinkage: $5,000 a month in this example', 'demo',
    'SAY: "A hypothetical company with $1,000,000 in monthly sales. At 2% shrink it loses $20,000; at 1.5%, $15,000. The difference is $5,000 a month." SHOW: the chart; it is editable in PowerPoint to use the client\'s figures. BENEFIT: a simple way to estimate what a point of shrink is worth. CLARIFY: this is an illustrative example; it does not represent real results or guarantee savings.');
  s.chart(0.6, 1.5, 7.4, 5.3, { series: [{ name: 'Shrink 2.0%', labels: ['Estimated monthly loss'], values: [20000] }, { name: 'Shrink 1.5%', labels: ['Estimated monthly loss'], values: [15000] }], colors: [C.R2, C.G3], max: 25000 });
  stat(s, 8.3, 1.5, 4.43, '$1,000,000', 'monthly sales in the example');
  stat(s, 8.3, 2.8, 4.43, '$20,000 → $15,000', 'estimated monthly loss', C.R2);
  stat(s, 8.3, 4.1, 4.43, '$5,000', 'estimated monthly difference', C.G3);
  s.rect(8.3, 5.45, 4.43, 1.35, { fill: C.YT, r: 0.1 });
  s.text(8.45, 5.5, 4.13, 1.25, 'Illustrative example. Does not represent real results or guarantee savings.', { size: 15, bold: true, color: C.Y3, valign: 'middle' });
}

// ===== OFFER =====
{
  const s = content('offer', 'Next step', 'Commercial proposal', 'Four ways to start working together', 'prop',
    'SAY: "There are four ways to work together; we choose after the assessment." SHOW: the four options. BENEFIT: flexibility according to the size and stage of the company. COMMON QUESTION: "How much does it cost?" → It depends on branches, users and modules; it is quoted after the assessment.');
  const O = [['faCloud', 'SaaS licensing', 'Subscription by branches, users and modules'], ['faFlask', 'Enterprise pilot', 'One or two stores, measured against a baseline'], ['faStore', 'Multi-branch rollout', 'Deployment by stages and regions'], ['faHandshake', 'Strategic partnership', 'Joint development of proposed modules']];
  O.forEach((o, i) => {
    const x = 0.6 + i * 3.08;
    s.rect(x, 1.6, 2.89, 4.6, { fill: C.WHITE, line: C.LINE, r: 0.14, shadow: true });
    iconCircle(s, x + 0.3, 1.9, 1.0, o[0], C.WHITE, i === 1 ? C.G3 : C.G2);
    s.text(x + 0.3, 3.15, 2.35, 1.2, o[1], { size: 20, bold: true });
    s.text(x + 0.3, 4.45, 2.35, 1.4, o[2], { size: 15, color: C.MUTED });
  });
  s.text(0.6, 6.4, 12.1, 0.32, 'Terms and pricing are defined after the assessment.', { size: 13, color: C.MUTED, italic: true });
}

// ===== DEMO =====
{
  const s = content('demo', 'Next step', 'Demonstration', 'A 60-minute demo built around your questions', 'prop',
    'SAY: "We propose a live guided demo on the demo company, following the two end-to-end cases and answering your questions." SHOW: the agenda. BENEFIT: the client sees the product working, not just slides. ASK THE CLIENT: "Which process would you like to see first?"');
  const A = [['10 min', 'TODAY and Alert Center', 'faGaugeHigh'], ['15 min', 'Case 1: inventory difference', 'faScaleUnbalanced'], ['10 min', 'Case 2: expiration and shrinkage', 'faCalendarXmark'], ['10 min', 'Sales, procurement and workforce', 'faChartLine'], ['15 min', 'Client questions', 'faComments']];
  A.forEach((a, i) => {
    const y = 1.55 + i * 1.03;
    s.rect(0.6, y, 7.2, 0.88, { fill: C.WHITE, line: C.LINE, r: 0.1, shadow: true });
    iconCircle(s, 0.75, y + 0.14, 0.6, a[2], C.WHITE, C.G2);
    s.text(1.5, y, 1.2, 0.88, a[0], { size: 16, bold: true, color: C.G3, valign: 'middle' });
    s.text(2.7, y, 5.0, 0.88, a[1], { size: 17, bold: true, valign: 'middle' });
  });
  s.rect(8.1, 1.55, 4.63, 5.0, { fill: C.G3, r: 0.14, shadow: true });
  s.text(8.4, 1.75, 4.1, 0.5, 'What we need', { size: 20, bold: true, color: C.WHITE });
  s.text(8.4, 2.4, 4.1, 3.9, [{ text: 'One hour with operations, inventory and loss prevention', bullet: true }, { text: 'Your top three problems', bullet: true }, { text: 'Optional: an anonymized data sample for the assessment', bullet: true }], { size: 15, color: C.WHITE, paraAfter: 10 });
}

// ===== IMPLEMENTATION =====
{
  const s = content('roadmap', 'Next step', 'Implementation', 'From assessment to expansion, in eight stages', 'prop',
    'SAY: "We start by understanding the operation and finish by measuring." SHOW: the eight stages. BENEFIT: contained risk: expansion happens only if results justify it. CLARIFY: these are proposed stages; there are no client implementations to date.');
  const R = [['Operational assessment', 'faStethoscope'], ['Scope definition', 'faBullseye'], ['Configuration', 'faGears'], ['Data load and integration', 'faDatabase'], ['Training', 'faChalkboardUser'], ['Pilot', 'faFlask'], ['Results measurement', 'faChartLine'], ['Branch expansion', 'faStore']];
  R.forEach((r, i) => {
    const x = 0.6 + (i % 4) * 3.08, y = 1.6 + Math.floor(i / 4) * 2.45, pilot = i === 5;
    s.rect(x, y, 2.89, 2.2, { fill: pilot ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.25, y + 0.25, 0.72, r[1], pilot ? C.G3 : C.WHITE, pilot ? C.WHITE : C.G2);
    s.text(x + 1.1, y + 0.25, 1.6, 0.72, String(i + 1), { size: 30, bold: true, color: pilot ? C.WHITE : 'B8C2BC', align: 'right', valign: 'middle' });
    s.text(x + 0.25, y + 1.15, 2.4, 0.85, r[0], { size: 17, bold: true, color: pilot ? C.WHITE : C.INK });
  });
  s.text(0.6, 6.5, 12.1, 0.32, 'Proposed stages. No client implementations to date.', { size: 13, color: C.MUTED, italic: true });
}

// ===== PILOT =====
{
  const s = content('pilot', 'Next step', 'Pilot', 'A 30-day pilot, measured with six KPIs', 'prop',
    'SAY: "We propose a 30-day pilot in one or two stores, subject to agreement, with a baseline and six KPIs." SHOW: the KPIs. BENEFIT: decide with data from your own operation. COMMON QUESTION: "Do you have success stories?" → We propose a measured pilot as the first step; we do not cite testimonials.');
  s.rect(0.6, 1.5, 3.7, 5.3, { fill: C.G3, r: 0.14, shadow: true });
  s.text(0.85, 1.75, 3.2, 1.1, '30', { size: 66, bold: true, color: C.WHITE });
  s.text(0.85, 2.95, 3.2, 0.5, 'day pilot', { size: 22, bold: true, color: C.WHITE });
  s.text(0.85, 3.65, 3.2, 1.6, 'Baseline, supported operation and final measurement.', { size: 16, color: 'D7F5DF' });
  s.text(0.85, 5.7, 3.2, 0.8, 'Subject to agreement. No testimonials or prior results.', { size: 13, color: 'D7F5DF' });
  const I = [['faBullseye', 'Inventory accuracy'], ['faScaleUnbalanced', 'Differences detected and closed'], ['faStopwatch', 'Investigation time'], ['faTrashCan', 'Shrink as % of sales'], ['faCalendarXmark', 'Expiries handled on time'], ['faListCheck', 'Task completion']];
  I.forEach((a, i) => {
    const x = 4.6 + (i % 2) * 4.15, y = 1.5 + Math.floor(i / 2) * 1.82;
    s.rect(x, y, 3.98, 1.66, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.22, y + 0.47, 0.72, a[0], C.WHITE, C.G2);
    s.text(x + 1.12, y + 0.2, 2.7, 1.26, a[1], { size: 17, bold: true, valign: 'middle' });
  });
}

// ===== NEXT STEPS =====
{
  const s = content('next', 'Next step', 'Next steps', 'From this meeting to a decision, in nine weeks', 'prop',
    'SAY: "If you are interested, this is the path: demo, assessment, configuration, 30-day pilot and a results meeting to decide." SHOW: the timeline. BENEFIT: a limited commitment with a decision date. CALL TO ACTION: agree on the demo date today.');
  const T = [['Week 1', 'Guided demo', 'faDisplay'], ['Week 2', 'Assessment and scope', 'faStethoscope'], ['Weeks 3–4', 'Configuration and data load', 'faGears'], ['Weeks 5–8', '30-day pilot', 'faFlask'], ['Week 9', 'Results and decision', 'faHandshake']];
  s.line(0.9, 3.0, 12.4, 3.0, { color: C.G2, lw: 3 });
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.45, w = 2.3, last = i === T.length - 1;
    iconCircle(s, x + w / 2 - 0.45, 2.55, 0.9, t[2], C.WHITE, last ? C.G3 : C.G2);
    s.text(x, 1.65, w, 0.4, t[0], { size: 16, bold: true, color: C.G3, align: 'center' });
    s.rect(x, 3.75, w, 1.5, { fill: last ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.text(x + 0.15, 3.75, w - 0.3, 1.5, t[1], { size: 17, bold: true, align: 'center', valign: 'middle', color: last ? C.WHITE : C.INK });
  });
  s.rect(0.6, 5.65, 12.13, 0.95, { fill: C.GT, r: 0.12 });
  s.text(0.85, 5.65, 11.6, 0.95, 'Today: agree on the demo date and the participants.', { size: 18, bold: true, color: C.GD, valign: 'middle' });
}

// ===== CLOSING =====
{
  const s = new Slide({ cover: true, section: 'Next step' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 1.95, 5.8, 2.3, 'DAILY — From data to decisions. From decisions to results.', { size: 32, bold: true, color: C.WHITE, lh: 0.95 });
  s.rect(0.75, 4.55, 5.4, 1.1, { fill: C.G1, r: 0.14, shadow: true });
  s.text(0.95, 4.55, 5.0, 1.1, 'Request a demo or start a pilot.', { size: 18, bold: true, color: '06301B', valign: 'middle' });
  s.text(0.75, 5.95, 5.6, 0.4, 'DAILY | Enterprise Commercial Presentation', { size: 11, color: 'B9F3C9' });
  const G = [['02_mermas/waste_centro-control', [260, 440, 1640]], ['05_ventas/sales', [260, 520, 1640]], ['04_calidad/quality', [250, 78, 1650]], ['12_procesos/process_flow', [255, 160, 1645]], ['07_multisucursal/branches', [260, 180, 1640]], ['01_ejecutivo/today', [260, 60, 1640]]];
  G.forEach((g, i) => s.shot(7.0 + (i % 2) * 2.98, 0.75 + Math.floor(i / 2) * 1.89, 2.75, 1.66, g[0], g[1]));
  s.notes = 'SAY: "DAILY turns operational data into daily decisions, and those decisions into results you can measure." CALL TO ACTION: propose a date for the guided demo. BENEFIT: the next step is limited and measurable.';
  S.closing = s;
}

const FULL = ['cover', 'problems', 'flow', 'modules', 'today', 'alerts', 'process', 'step1', 'step2', 'step3', 'step4', 'case1sum', 'waste', 'waste2', 'stock', 'wms', 'prevention', 'lossreport', 'sales', 'purchasing', 'staff', 'staff2', 'quality', 'case2', 'branches', 'mobile', 'connect', 'compare', 'status', 'day', 'benefits', 'beforeafter', 'impact', 'offer', 'demo', 'roadmap', 'pilot', 'next', 'closing'];
const EXEC = ['cover', 'today', 'case1sum', 'connect', 'next'];

async function coverBg(file) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs>
<radialGradient id="g" cx="78%" cy="30%" r="85%"><stop offset="0" stop-color="#0E8A45"/><stop offset="0.55" stop-color="#075A2E"/><stop offset="1" stop-color="#042614"/></radialGradient>
<radialGradient id="h" cx="8%" cy="95%" r="45%"><stop offset="0" stop-color="#39F500" stop-opacity="0.22"/><stop offset="1" stop-color="#39F500" stop-opacity="0"/></radialGradient></defs>
<rect width="1920" height="1080" fill="url(#g)"/><rect width="1920" height="1080" fill="url(#h)"/></svg>`;
  await sharp(Buffer.from(svg)).png().toFile(file);
}

module.exports = { S, FULL, EXEC, coverBg };
