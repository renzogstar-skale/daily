#!/usr/bin/env python3
"""Convierte la planilla .xlsb de programación en el JSON que carga tools/planilla/importar.php.

Uso: python tools/planilla/extraer_planilla.py <planilla.xlsb> <salida.json>   (requiere pyxlsb)

Solo toma como historia las hojas mensuales realmente diligenciadas: una hoja con menos del 80 % de sus
celdas llenas es una plantilla (julio, agosto, noviembre y diciembre en el archivo de sept-oct) y se omite.
Cuando dos hojas se solapan manda la posterior, que es la que refleja lo que finalmente se trabajó.
"""
import datetime as dt
import json
import re
import sys

from pyxlsb import open_workbook

MONTHS = ['JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE']
MIN_FILLED = 0.8


def excel_date(v):
    if isinstance(v, (int, float)) and v > 40000:
        return dt.date(1899, 12, 30) + dt.timedelta(days=int(v))
    if isinstance(v, str):
        m = re.fullmatch(r'\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*', v)
        if m:
            return dt.date(int(m[3]), int(m[2]), int(m[1]))
    return None


def excel_time(v):
    if not isinstance(v, (int, float)):
        return ''
    minutes = round(v * 1440) % 1440
    return f'{minutes // 60:02d}:{minutes % 60:02d}'


def code_of(v):
    if isinstance(v, float):
        v = int(v)
    return str(v or '').strip()


def read_sheets(path):
    out = {}
    with open_workbook(path) as wb:
        for name in wb.sheets:
            with wb.get_sheet(name) as sheet:
                out[name] = [[c.v for c in row] for row in sheet.rows()]
    return out


def month_sheet(rows):
    head = next((i for i, r in enumerate(rows) if r and str(r[0]).strip().upper() == 'CODIGO'), None)
    if head is None:
        return None
    date_row = max(rows[max(0, head - 6):head], key=lambda r: sum(1 for v in r if excel_date(v)))
    cols = {j: excel_date(v) for j, v in enumerate(date_row) if excel_date(v)}
    data, filled, total = {}, 0, 0
    for r in rows[head + 1:]:
        code = code_of(r[0] if r else None)
        if not re.match(r'^(\d{6,}|TEMP\d+)$', code):
            continue
        for j, d in cols.items():
            total += 1
            v = str(r[j]).strip() if j < len(r) and r[j] not in (None, '') else ''
            if v:
                filled += 1
                data.setdefault(code, {})[d.isoformat()] = v
    return data, filled / total if total else 0


def main(src, dst):
    sheets = read_sheets(src)
    schedule, used, skipped = {}, [], []
    for name in MONTHS:
        parsed = month_sheet(sheets.get(name, []))
        if parsed is None or parsed[1] < MIN_FILLED:
            skipped.append({'sheet': name, 'filled_pct': round((parsed[1] if parsed else 0) * 100, 1)})
            continue
        used.append(name)
        for code, days in parsed[0].items():
            schedule.setdefault(code, {}).update(days)

    sap = []
    for r in sheets['Turnos SAP'][2:]:
        code = code_of(r[0] if r else None)
        if code:
            sap.append({'code': code, 'start': excel_time(r[1]), 'end': excel_time(r[2]), 'pause': str(r[3] or '').strip(), 'pause_length': str(r[4] or '').strip(),
                'lunch': r[5] if isinstance(r[5], (int, float)) else 0, 'hours': round(r[8], 2) if isinstance(r[8], (int, float)) else 0})

    json.dump({'source': src, 'sheets_used': used, 'sheets_skipped': skipped, 'sap': sap, 'schedule': schedule},
        open(dst, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(f'Hojas usadas: {used} | omitidas: {skipped} | empleados: {len(schedule)} | turnos SAP: {len(sap)}')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
