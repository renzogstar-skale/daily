# Language QA: extracts every editable text (shapes, tables, charts, notes) from a PPTX and flags Spanish.
# Usage: python qa_text.py <deck.pptx> <out.json>
import json, re, sys
from pptx import Presentation

SPANISH = re.compile(r"[áéíóúñ¿¡]|\b(de|del|los|las|para|con|sin|por|una|que|hoy|ventas|merma|mermas|sucursal|sucursales|existencias|pendiente|guardar|cancelar|buscar|cerrar|nuevo|nueva|fecha|cantidad|motivo|responsable|producto|productos|tienda|turno|conteo|lote|estado|alerta|alertas|y|el|la|en|es|como|más|tablero|inventario|compras|calidad|vencimiento|conciliación|bodega|meta)\b", re.I)
TERMS_BAD = re.compile(r"\b(Kardex|Shrink Control Board|Alert center|Valued stock ledger|Planning & purchasing|Inventory & warehouses|Dispatch & returns|Quality & expiry|Operations & staff|Alkosto|in Spanish)\b")

def texts(prs):
    for i, s in enumerate(prs.slides, 1):
        for sh in s.shapes:
            if sh.has_text_frame and sh.text_frame.text.strip():
                yield i, 'shape', sh.text_frame.text
            if getattr(sh, 'has_table', False) and sh.has_table:
                for r in sh.table.rows:
                    for c in r.cells:
                        yield i, 'table', c.text
            if getattr(sh, 'has_chart', False) and sh.has_chart:
                ch = sh.chart
                for p in ch.plots:
                    for c in p.categories:
                        yield i, 'chart-category', str(c)
                    for se in p.series:
                        yield i, 'chart-series', se.name
            if sh.shape_type == 13:
                yield i, 'image-alt', sh._element.xpath('.//p:cNvPr/@descr')[0] if sh._element.xpath('.//p:cNvPr/@descr') else ''
        if s.has_notes_slide:
            yield i, 'notes', s.notes_slide.notes_text_frame.text

prs = Presentation(sys.argv[1])
rows, flags = [], []
for i, kind, t in texts(prs):
    rows.append({'slide': i, 'kind': kind, 'text': t})
    for line in t.splitlines():
        if kind != 'image-alt' and (SPANISH.search(line) or TERMS_BAD.search(line)):
            flags.append({'slide': i, 'kind': kind, 'text': line[:200]})
json.dump({'items': len(rows), 'words': sum(len(r['text'].split()) for r in rows), 'flags': flags, 'rows': rows}, open(sys.argv[2], 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('items', len(rows), 'flags', len(flags))
for f in flags[:60]:
    print(f['slide'], f['kind'], '|', f['text'])
