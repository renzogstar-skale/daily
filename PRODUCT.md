# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three roles with equal weight; access is scoped per role and per branch (`helpers/DairyAccess.php`):

- **Store manager / jefe de tienda:** starts the day in HOY (`/today`) to see what was lost, why, who must act, sales vs. goal, staffing coverage and alerts. Works on desktop in the office and on the phone on the sales floor.
- **Owner / regional direction:** compares stores, margin and shrink; needs one source of truth shared with the managers.
- **Floor staff / operario:** records shrink, blind counts, scans, receptions and tasks from the phone (`/m`), often one-handed, mid-shift.

## Product Purpose

DAILY is an operational intelligence platform for supermarkets and retail that turns every loss, expiry and labor hour into a measurable decision. It unifies what today lives in spreadsheets, the ERP, the scheduling app and paper checklists: shrink control, inventory, quality/expiry, purchasing, workforce, loss prevention and profitability, per store.

Success = less shrink, fewer expiries and better decisions, demonstrated with evidence in a measured pilot. Core message: *we don't sell modules; we sell less loss, fewer expiries and better decisions, with evidence.*

## Positioning

One system where each loss carries a cause, a photo, an approver and an owner, linked to inventory (immutable Kardex), labor and sales. Accountability is built in: who registered, who approved, when; segregation of duties.

## Operating Context

- Daily rituals: morning review in HOY, shrink approval, FEFO/markdown lists, short frequent blind counts from the phone, receptions against purchase orders, weekly shift planning.
- Environments: back office desktop, sales floor and warehouse on phones, scanner use.
- Public commercial site at `/` (`views/site/`, texts in `helpers/DailySiteContent.php`); the app lives behind `/app`.

## Capabilities and Constraints

- Stack: PHP (Grocy fork, Slim + Blade + Bootstrap 4), no React. Dev server `php -S localhost:8080 -t public router.php`. Internal code name is "DAIRY"; public brand is DAILY.
- Bilingual ES (source) / EN via runtime dictionary (`public/js/daily_i18n.js`, catalog `localization/daily/en`).
- Real vs. simulated features are tracked in `docs/estrategia/21_pagina_comercial/ESTADO_FUNCIONES.md`. `/kpis` is a mock-up (use HOY as the executive panel); header KPIs in `/loss-prevention` and `/merchandise` are simulated; purchase "simulations" and the AI advisor were removed and must not be advertised.
- Undecided: pricing (site shows "Precio por cotizar"); final brand line (DAILY Control 360 is a proposal, doc 11).

## Brand Commitments

- Binding name: **DAILY**. "DAILY Control 360" / DC360 is a pending proposal, not to be used publicly until approved. Never use "DAYLI" or "Retail 360".
- Voice: concrete, measurable, no guaranteed savings; benefits are always "potential" and proven with the client's own data.

## Evidence on Hand

- Demo company seed with anonymized screenshots (`public/img/site/*.webp`, `docs/estrategia/demo_img`, deck captures).
- Strategy docs 01–21 in `docs/estrategia/`.
- **No production customers yet**: no testimonials, logos, case studies or real savings figures may be shown or invented.

## Product Principles

1. Evidence over promise: every claim maps to a real, working function; simulated or mock screens are never presented as real.
2. Every loss has a cause and an owner: surfaces should make responsibility and next action obvious.
3. One source of truth: HOY, panels and reports show the same numbers.
4. Works where the work happens: floor tasks must be fast on a phone; analysis must scan well on desktop.
5. Bilingual by default: every new text is Spanish-first and must translate cleanly to English.

## Accessibility & Inclusion

WCAG 2.2 AA (contrast, focus, target size), in both ES and EN, for desktop and mobile web.
