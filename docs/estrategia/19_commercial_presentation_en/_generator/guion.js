// Builds the presenter script from each slide's notes
const fs = require('fs');
const { S, FULL } = require('./deck');
const LABELS = ['SAY', 'SHOW', 'BENEFIT', 'COMMON QUESTION', 'ASK THE CLIENT', 'CLARIFY', 'CALL TO ACTION'];
const re = new RegExp('(' + LABELS.join('|') + '):', 'g');
function fmt(notes) {
  const parts = notes.split(re).filter(Boolean); let out = '';
  for (let i = 0; i < parts.length - 1; i += 2) out += `- **${parts[i][0] + parts[i].slice(1).toLowerCase()}:** ${parts[i + 1].trim()}\n`;
  return out;
}
const title = k => S[k].o.title || (k === 'cover' ? 'Cover — DAILY: operational supervision and control for retail' : 'Closing — From data to decisions');
let md = `# DAILY — Sales Presentation Script

> Accompanies \`DAILY_Enterprise_Commercial_Presentation_EN.pptx/.pdf\` (39 slides, ~45 min) and \`DAILY_Executive_Summary_EN.pdf\` (5 slides, 5 min). The same notes are in the PowerPoint notes pane.

## Rules for presenting

- Screenshots are **real screens running a demo company**. Say so at the start and never present them as client results.
- **Screenshots were captured with the DAILY interface switched to English** (language selector, top bar). A small number of residual labels were translated at capture time; see DAILY_English_Translation_QA.md. Data and layout are unaltered.
- Currency on screen is the demo company's own currency, shown as $; no conversion was applied.
- Every slide carries a tag: **Available feature**, **Illustrative scenario**, **Available + projected**, **Available + in development** or **Commercial proposal**. Respect it when speaking.
- **Occupational health and safety does not exist today.** Neither does the time-and-motion study nor shift generation straight from demand.
- An inventory difference appears **when a count is recorded**. Do not say DAILY detects it on its own in real time.
- Recommendations are **explainable analytics** (rules and statistics), even if a screen name says "AI".
- **The monthly Executive Dashboard (/kpis) is being redesigned and currently shows sample values with an example brand: do not open it in a demo.** Use TODAY and the Executive Loss Report.
- The header KPIs of the Loss Prevention Center and Merchandise Control are sample values: do not quote them.
- The comparison with Walmart or Costco is about **capabilities**, not products: there is no integration or relationship with those companies.
- The financial example is **illustrative**: never promise savings.
- There are no final prices, clients or testimonials in this presentation.

## Script by slide (full version)

`;
FULL.forEach((k, i) => { md += `### ${i + 1}. ${title(k)}\n\n${fmt(S[k].notes)}\n`; });
md += `## 5-minute executive summary

| # | Slide | Time | One-sentence message |
|---|---|---|---|
| 1 | Cover | 30 s | "DAILY connects inventory, shrink, sales and staff in a single platform." |
| 2 | TODAY | 1 min | "The manager sees the day's operation hour by hour, with notices and owners." |
| 3 | Case 1: from count to closure | 1 min 30 s | "A 2-phone difference is detected when counting, investigated and closed with authorization." |
| 4 | Connected modules | 1 min | "One movement feeds inventory, sales, shrink, prevention, staff and reports." |
| 5 | Next steps | 1 min | "Demo, assessment, 30-day pilot and a decision in nine weeks." |

## Frequently asked questions

| Question | Suggested answer |
|---|---|
| Does it replace my ERP or POS? | Not necessarily. It adds operational control and receives data through CSV/Excel import or API. The exact integration is validated in the assessment. |
| Does it work across branches? | Yes. It filters by branch and management compares the network. |
| Does it detect theft? | It flags differences, unusual movements and cases to investigate. A person confirms the cause. |
| Does it use artificial intelligence? | It uses explainable analytics: rules, averages and deviations. Each recommendation shows where it comes from. |
| Is the interface available in English? | Yes. Each user can switch between English and Spanish from the top bar; exports follow the selected language. |
| Which barcode scanners does it support? | Phone camera and keyboard-mode scanners. The exact model is validated in the pilot. |
| Is there an app in the app stores? | Today it runs in the phone's browser. App store publishing is not included. |
| Does it comply with my country's labor law? | The generator rules are configurable and must be validated with your legal team. |
| How much does it cost? | It depends on branches, users and modules; it is quoted after the assessment. |
| Do you have clients using it? | We propose a measured pilot as the first step; we do not cite testimonials. |
| How long does implementation take? | It is defined in the assessment. The proposed pilot lasts 30 days once data is loaded. |
| What happens to my data? | It belongs to the client; backup, access and confidentiality are agreed by contract. |
`;
fs.writeFileSync(process.argv[2], md);
console.log('script ok');
