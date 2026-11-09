# DAILY — English Translation QA Report

**Scope:** `DAILY_Enterprise_Commercial_Presentation_EN.pptx/.pdf` (39 slides), `DAILY_Executive_Summary_EN.pdf` (5 slides), `DAILY_Sales_Presentation_Script_EN.md`.
**Date:** 11/03/2026 · **Language:** English (United States) · **Brand:** DAILY (unchanged)

## 1. Verdict

All visible content in the deliverables is in English. A handful of short strings were edited before capture (presentation-only retouch). The footer of every slide discloses this, and section 4 lists what was changed.

| Check | Result |
|---|---|
| Editable PPTX text (titles, body, tables, chart labels, notes): 782 items, 4,722 words | 0 Spanish words. The only automated flag is "EN interface", a false positive |
| Executive Summary PPTX text: 122 items | 0 flags |
| Speaker notes and script (`.md`) | English. Same notes in the PowerPoint notes pane and the script |
| Screenshots (34 captures used in the deck) | Captured with DAILY's own English interface. Residual Spanish was retouched (section 4) |
| PPTX vs. PDF | Built from one slide spec. The PDF is rendered from the same content, and slide-by-slide renders were inspected |
| Visual inspection | All 39 slides rendered and reviewed. No clipped or overlapping text. Callout pins sit outside the screenshot text |
| Terminology | Standardized (section 5) |

## 2. How the screenshots were produced

1. DAILY supports English natively: language selector (EN/ES) in the top bar, saved per user (`daily_lang`). Every screen was captured with English selected.
2. Captures came from an isolated copy of the demo database on separate local servers. The production application and its data were not modified.
3. The demo company is fictitious (stores in South Florida). Personal names were anonymized ("Demo User", "Store Manager", "Employee").
4. During capture, every text node, placeholder and input value was scanned for Spanish. The scan used a vocabulary of 3,733 Spanish-only words taken from DAILY's own translation catalog, plus a stop-word pattern. The final pass reports 0 Spanish strings, apart from the codes and proper nouns listed below.

Remaining flagged tokens that are **not** Spanish text (kept as is):
- Product and lot codes: `CON-HEL-001`, `CON-PIZ-001`, `CON-VEG-001`, `MEA-RES-001`, `DEMO-RES-KG`, `CERT-BPM-2025-01`
- Fictitious person names: Débora Lima, Martina Fuentes
- Brand-style supplier names: NexTech Electronics USA, WhirlMaster Appliances USA, La Criolla Preserves & Spices (proper noun)
- "Y axis" in a chart caption (English)

## 3. Issues found in the product (document only; the app was not changed)

These are gaps in DAILY's English catalog or locale formatting. In the deck they are fixed only in the screenshots:

| # | Issue | Where | Recommendation |
|---|---|---|---|
| 1 | Some strings in data and templates have no catalog entry (alert texts, investigation titles, placeholders, cause names, demo supplier and location names) | Shrinkage & Waste, Process Flow, Mobile, Purchasing, Quality | Add them to `localization/daily/en/*.json` and run `bin/daily_i18n_build.php` |
| 2 | "Ajustes" translated as **Settings** where it means **Adjustments** | Shrinkage & Waste board, Stock & Movements, labor cost | Add context-specific catalog entries |
| 3 | Dates in DD/MM/YYYY and YYYY-MM-DD; weekday headers as DD/MM; "23 Oct. 2026" | Most screens | Format dates by locale (MM/DD/YYYY for en-US) |
| 4 | Spanish number format on mobile ("14,2 M US$", "170,7 mil US$", "1,51 %") | Mobile app | Use `Intl.NumberFormat('en-US')` in the mobile views |
| 5 | Spanish word "a" in date ranges ("2026-11-01 a 2026-11-30") | Shrink causes context bar | Translate in the Blade view or the catalog |
| 6 | "Kardex" kept in English UI labels | Ledger screens, Process Flow | Use "Inventory Ledger" in the English catalog |
| 7 | "VMD" column (average daily sales) | MRP | Use "ADS" in English |
| 8 | "Valle" (trough) label in sales charts | TODAY heat map, Sales curve | Translate to "Low" |
| 9 | One labor policy name references Colombian law | Workforce scheduling (not visible in the deck crops) | Configure a U.S. policy for U.S. prospects |

## 4. Presentation-only retouch (disclosed)

Rules live in `_generator/overlay.json` and are applied by `_generator/cap_en.js` before each screenshot. Every replacement is counted per screen in `_generator/retouch_stats.json`.

- **79 exact-string rules and 182 substring rules.** They translate the residual Spanish labels, placeholders and demo master data (e.g., "Bodega Seca" → "Dry Storage", "Lácteos del Valle S.A." → "Valley Dairy Inc.").
- **Scoped rules:** "Settings" → "Adjustments" applies only on Shrinkage & Waste, Stock & Movements and the labor cost card. Weekday headers DD/MM → MM/DD apply only on Scheduling.
- **Locale formatting:** DD/MM/YYYY and YYYY-MM-DD → MM/DD/YYYY; "1.234,56 US$" → "$1,234.56"; "14,2 M US$" → "$14.2M"; "12,5 %" → "12.5%"; "23 Oct. 2026" → "Oct 23, 2026".
- **No numbers, layouts, charts or statuses were changed.** Currency is the demo company's own dollar figures. No conversion or exchange rate was applied.
- Every slide footer states: *"Screens: real DAILY UI in English with demo data; a few residual labels retouched (see QA report)."* The cover says *"Real screens · English interface · demo data."*

## 5. Terminology applied

| Spanish | English used in the deck |
|---|---|
| Panel Ejecutivo | Executive Dashboard |
| Centro de Alertas | Alert Center |
| Operación del Día (HOY) | TODAY · Daily Operations |
| Ventas y Rentabilidad | Sales & Profitability |
| Planeación y Compras | Planning & Procurement |
| Inventario y Almacenes | Inventory & Warehousing |
| Control de Mercancías | Merchandise Control |
| Prevención de Pérdidas | Loss Prevention |
| Control de Celulares | Mobile Device Control |
| Mermas | Shrinkage & Waste (module); "shrink" in running text, the standard U.S. retail term |
| Calidad y Vencimientos | Quality & Expiration Management |
| Despachos y Devoluciones | Dispatches & Returns |
| Operaciones y Personal | Workforce Operations |
| Kardex Valorizado | Valuated Inventory Ledger |
| Conteos Físicos | Physical Inventory Counts |
| Conciliación | Inventory Reconciliation |
| Disponibilidad en Bodega | Warehouse Stock Availability (the UI card reads "Backroom availability") |
| Cumplimiento de Meta | Target Achievement |
| Recuperación Financiera | Financial Recovery |
| Trazabilidad | Traceability |

Other changes for a U.S. audience:
- The capability comparison now cites Walmart and Costco only. Alkosto, a Colombian retailer, was removed.
- "English-language interface" moved from *Proposed* to *Available today* ("English and Spanish interface").

## 6. Localization conventions

- Dates are MM/DD/YYYY in screenshots. Narrative dates are written out, e.g., "November 2026".
- Numbers use the en-US format: 1,250,000.50, and percentages look like 95.5%.
- Currency is USD ($). The financial example ($1,000,000 monthly sales, 2.0% → 1.5% shrink) is labeled illustrative.

## 7. How to reproduce

```bash
# isolated servers on :8082-8084 with copies of data/ (see memory: deck-v2-capture)
NAME_MAP=<name_map.json> BASE=http://localhost:8082 node _generator/cap_en.js captures _generator/list.json "<regex>"
node _generator/build.js captures .            # PPTX + PDF + per-slide PNG renders in _generator/qa/
node _generator/guion.js DAILY_Sales_Presentation_Script_EN.md
python _generator/qa_text.py DAILY_Enterprise_Commercial_Presentation_EN.pptx out.json
```
