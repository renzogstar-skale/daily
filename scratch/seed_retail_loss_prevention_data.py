import sqlite3
import datetime
import random
import json

db_path = "data/grocy_es.db"
conn = sqlite3.connect(db_path)
c = conn.cursor()

print("Seeding Retail Loss Prevention, Dispatch, Returns & Bakery data...")

# -------------------------------------------------------------------
# 1. SEED EXPIRY PREDICTIVE ALERTS
# -------------------------------------------------------------------
c.execute("DELETE FROM expiry_predictive_alerts")

# Find actual products in DB or use product details
products_db = c.execute("SELECT id, name, sku, qu_id_stock FROM products WHERE active = 1").fetchall()
product_map = {p[1]: p[0] for p in products_db}

# Realistic batch items
expiry_samples = [
    {
        "code": "EXP-2026-0001",
        "name": "Baguette Rústica Artesanal",
        "category": "Bakery / Panadería",
        "stock_id": "LOT-PAN-20261025-A",
        "best_before": "2026-10-27",
        "days_left": 2,
        "amount": 85.0,
        "cost": 1.20,
        "daily_sales": 25.0,
        "action": "fefo_rotation",
        "status": "validacion",
        "location": "Góndola Panadería Central",
        "photo": "/img/control_center/baguette.jpg"
    },
    {
        "code": "EXP-2026-0002",
        "name": "Pechuga de Pollo Fresca 1kg",
        "category": "Pollo fresco",
        "stock_id": "LOT-POL-20261024-C",
        "best_before": "2026-10-26",
        "days_left": 1,
        "amount": 42.0,
        "cost": 6.80,
        "daily_sales": 18.0,
        "action": "markdown_discount",
        "status": "accion_asignada",
        "location": "Nevera Carnes 02",
        "photo": "/img/control_center/pollo.jpg"
    },
    {
        "code": "EXP-2026-0003",
        "name": "Carne Molida Especial 500g",
        "category": "Carnes frescas",
        "stock_id": "LOT-RES-20261023-B",
        "best_before": "2026-10-26",
        "days_left": 1,
        "amount": 30.0,
        "cost": 5.40,
        "daily_sales": 12.0,
        "action": "fefo_rotation",
        "status": "ejecucion",
        "location": "Cámara Fría Carnes",
        "photo": "/img/control_center/carne.jpg"
    },
    {
        "code": "EXP-2026-0004",
        "name": "Leche Entera Larga Vida 1L",
        "category": "Dairy / Lácteos",
        "stock_id": "LOT-LAC-20261010-09",
        "best_before": "2026-10-30",
        "days_left": 5,
        "amount": 210.0,
        "cost": 1.45,
        "daily_sales": 32.0,
        "action": "markdown_discount",
        "status": "alerta",
        "location": "Pasillo 4 Lácteos",
        "photo": "/img/control_center/card_leche.jpg"
    },
    {
        "code": "EXP-2026-0005",
        "name": "Tomate Chonto Primera",
        "category": "Frutas y verduras",
        "stock_id": "LOT-FRU-20261022-T1",
        "best_before": "2026-10-27",
        "days_left": 2,
        "amount": 120.0,
        "cost": 0.90,
        "daily_sales": 35.0,
        "action": "shelf_reposition",
        "status": "verificacion",
        "location": "Isla Central Frutas",
        "photo": "/img/control_center/card_tomate.jpg"
    },
    {
        "code": "EXP-2026-0006",
        "name": "Filete de Salmón Congelado 500g",
        "category": "Congelados",
        "stock_id": "LOT-CONG-20260615-S4",
        "best_before": "2026-11-09",
        "days_left": 15,
        "amount": 48.0,
        "cost": 11.50,
        "daily_sales": 1.8,
        "action": "branch_transfer",
        "status": "alerta",
        "location": "Freezer Isla 01",
        "photo": "/img/control_center/salmon.jpg"
    },
    {
        "code": "EXP-2026-0007",
        "name": "Arroz Blanco Premium 1kg",
        "category": "Productos secos",
        "stock_id": "LOT-SEC-20251101-A",
        "best_before": "2026-11-25",
        "days_left": 31,
        "amount": 350.0,
        "cost": 1.80,
        "daily_sales": 8.0,
        "action": "supplier_return",
        "status": "cerrada",
        "location": "Bodega Principal Racks B",
        "photo": "/img/control_center/arroz.jpg"
    }
]

for s in expiry_samples:
    # Match or find product id
    pid = 1
    for pname, prod_id in product_map.items():
        if s["name"].lower() in pname.lower() or pname.lower() in s["name"].lower():
            pid = prod_id
            break

    # Mathematical predictive calculation:
    # demand before expiry = avg_daily_sales * days_left
    # units at risk = max(0, amount - demand_before_expiry)
    est_demand = round(s["daily_sales"] * max(0, s["days_left"]), 1)
    units_risk = round(max(0.0, s["amount"] - est_demand), 1)
    total_risk = round(units_risk * s["cost"], 2)
    turnover = round(s["amount"] / s["daily_sales"], 1) if s["daily_sales"] > 0 else 99.0

    c.execute("""
        INSERT INTO expiry_predictive_alerts
        (alert_code, branch_id, product_id, stock_id, location_id, category_name, best_before_date,
         days_left, available_amount, unit_cost, total_value_risk, avg_daily_sales,
         estimated_demand_before_expiry, units_at_risk, turnover_days, recommended_action,
         assigned_to_user_id, resolution_status, resolution_action_taken, evidence_notes, evidence_photo_url,
         created_at)
        VALUES (?, 1, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, datetime('now', 'localtime'))
    """, (
        s["code"], pid, s["stock_id"], s["category"], s["best_before"],
        s["days_left"], s["amount"], s["cost"], total_risk, s["daily_sales"],
        est_demand, units_risk, turnover, s["action"],
        s["status"], 
        f"Acción preventiva: {s['action']} aplicada con éxito" if s["status"] in ["verificacion", "cerrada"] else None,
        f"Verificación de lote {s['stock_id']} en {s['location']}. Se coordinó rotación." if s["status"] != "alerta" else None,
        s["photo"]
    ))

print("Seeded 7 predictive expiry alerts.")

# -------------------------------------------------------------------
# 2. SEED CUSTOMER ORDERS (PICKUP VS DELIVERY) & 7-STAGE VARIANCES
# -------------------------------------------------------------------
c.execute("DELETE FROM dispatch_variances")
c.execute("DELETE FROM dispatch_scans")

# Update customer orders with delivery types, pins, carriers and variances
orders = c.execute("SELECT id, order_code FROM customer_orders ORDER BY id").fetchall()
if not orders:
    # Create sample customer orders if none exist
    c.execute("""
        INSERT INTO customer_orders 
        (order_code, branch_id, customer_id, status, requested_date, delivery_type, pickup_pin, carrier_name, carrier_vehicle, carrier_phone, packages)
        VALUES 
        ('PED-2026-001', 1, 1, 'dispatched', '2026-10-25', 'delivery', '849201', 'Logística Express - Juan Pérez', 'Camión Thermo-04 (ABC-123)', '3109876543', 4),
        ('PED-2026-002', 1, 2, 'packed', '2026-10-25', 'pickup', '593812', NULL, NULL, NULL, 2),
        ('PED-2026-003', 1, 1, 'picked', '2026-10-26', 'delivery', '441029', 'Envíos Rápidos - Carlos Ruiz', 'Van Refrigerada (XYZ-789)', '3151234567', 3),
        ('PED-2026-004', 1, 3, 'confirmed', '2026-10-26', 'pickup', '710492', NULL, NULL, NULL, 1)
    """)
    conn.commit()
    orders = c.execute("SELECT id, order_code FROM customer_orders ORDER BY id").fetchall()

# Set up order 1 as the exact prompt example:
# Solicitadas: 100, Preparadas: 98, Escaneadas: 98, Despachadas: 98, Recibidas: 96
# Variación preparación: -2, Variación entrega: -2
order_id_1 = orders[0][0]
c.execute("""
    UPDATE customer_orders 
    SET delivery_type = 'delivery',
        status = 'dispatched',
        carrier_name = 'Transportes Andinos - Conductor Hernán Gómez',
        carrier_vehicle = 'Camión C-08 ThermoKing (WXY-882)',
        carrier_tracking_number = 'TRK-2026-9948',
        carrier_phone = '+57 312 884 9921',
        departure_time = '2026-10-25 08:30:00',
        delivery_time = '2026-10-25 10:45:00',
        delivery_status = 'delivered',
        delivery_signature_name = 'Ana María Restrepo (Recepción Cliente)',
        route_incidents = 'Tráfico pesado en zona industrial. Cadena de frío preservada a 3.4°C.',
        cold_chain_temp_ok = 1,
        preparation_accuracy_pct = 98.0,
        delivery_accuracy_pct = 97.9,
        variance_total_cost = 45.60,
        conciliated_by_user_id = 1,
        conciliated_at = '2026-10-25 11:30:00'
    WHERE id = ?
""", (order_id_1,))

# Variance line 1: Preparation variance (requested 100, prepared 98 -> -2 faltante en bodega)
c.execute("""
    INSERT INTO dispatch_variances
    (order_id, item_id, product_id, stage, variance_type, requested_qty, prepared_qty, scanned_qty, dispatched_qty, received_qty, returned_qty, difference_qty, unit_cost, financial_impact, resolution_notes, authorized_by_user_id, resolution_status)
    VALUES (?, 1, 1, 'preparation', 'faltante', 100.0, 98.0, 98.0, 98.0, 98.0, 0.0, -2.0, 11.40, 22.80, 'Faltante en picking por desabastecimiento momentáneo en pasillo. Autorizado por supervisor de despacho.', 1, 'authorized_exception')
""", (order_id_1,))

# Variance line 2: Delivery variance (dispatched 98, received 96 -> -2 daño durante transporte)
c.execute("""
    INSERT INTO dispatch_variances
    (order_id, item_id, product_id, stage, variance_type, requested_qty, prepared_qty, scanned_qty, dispatched_qty, received_qty, returned_qty, difference_qty, unit_cost, financial_impact, resolution_notes, authorized_by_user_id, resolution_status)
    VALUES (?, 1, 1, 'delivery', 'producto_dañado', 98.0, 98.0, 98.0, 98.0, 96.0, 2.0, -2.0, 11.40, 22.80, 'Avería durante transporte por frenado brusco. Se emitió nota de crédito al cliente y se registró merma operativa.', 1, 'waste_recorded')
""", (order_id_1,))

# Order 2: Pickup order (Cliente recoge)
if len(orders) > 1:
    order_id_2 = orders[1][0]
    c.execute("""
        UPDATE customer_orders 
        SET delivery_type = 'pickup',
            status = 'packed',
            pickup_pin = '389201',
            pickup_qr_token = 'QR-COLLECT-389201-P2',
            pickup_verified_by = 1,
            pickup_verified_at = '2026-10-25 14:00:00',
            cold_chain_temp_ok = 1,
            preparation_accuracy_pct = 100.0,
            delivery_accuracy_pct = 100.0,
            packages = 2
        WHERE id = ?
    """, (order_id_2,))

    # Perfect verification scan
    c.execute("""
        INSERT INTO dispatch_scans (order_id, barcode_scanned, product_id, stock_id, expected_product_id, expected_qty, scanned_qty, match_status, operator_user_id)
        VALUES (?, '770200100201', 1, 'LOT-A1', 1, 10.0, 10.0, 'match', 1)
    """, (order_id_2,))

# Additional realistic variances across other orders
variances_seed = [
    (order_id_1, 2, 'preparation', 'producto_sustituido', 50.0, 50.0, 50.0, 50.0, 50.0, 0.0, 0.0, 4.50, 0.0, 'Sustitución de marca autorizada por cliente telefónicamente.', 1, 'authorized_exception'),
    (orders[-1][0], 3, 'delivery', 'error_entrega', 20.0, 20.0, 20.0, 20.0, 18.0, 0.0, -2.0, 6.20, 12.40, 'Diferencia en conteo de entrega con chofer de ruta.', 1, 'pending')
]
for oid, pid, stg, vtype, req, prep, scan, disp, recv, ret, diff, cost, imp, notes, uid, stat in variances_seed:
    c.execute("""
        INSERT INTO dispatch_variances
        (order_id, item_id, product_id, stage, variance_type, requested_qty, prepared_qty, scanned_qty, dispatched_qty, received_qty, returned_qty, difference_qty, unit_cost, financial_impact, resolution_notes, authorized_by_user_id, resolution_status)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (oid, pid, stg, vtype, req, prep, scan, disp, recv, ret, diff, cost, imp, notes, uid, stat))

print("Seeded customer orders & dispatch variances.")

# -------------------------------------------------------------------
# 3. SEED CUSTOMER RETURNS & EXCHANGES
# -------------------------------------------------------------------
c.execute("DELETE FROM customer_returns_exchanges")

returns_data = [
    {
        "code": "DEV-2026-0001",
        "order_id": order_id_1,
        "product_id": 1,
        "type": "devolucion_cliente",
        "qty": 5.0,
        "cost": 11.40,
        "classification": "cuarentena",
        "is_perishable": 1,
        "reason": "Empaque roto reportado por cliente en recepción",
        "recov": 0.0,
        "loss": 57.0,
        "refund": 72.50,
        "credit": 0.0,
        "status": "inspeccion",
        "insp_notes": "Producto lácteo perecedero. Prohibida reincorporación directa por política sanitaria.",
        "evidence": "/img/control_center/merma_empaque.jpg"
    },
    {
        "code": "DEV-2026-0002",
        "order_id": order_id_1,
        "product_id": 2,
        "type": "cambio",
        "qty": 2.0,
        "cost": 15.20,
        "classification": "reincorporacion",
        "is_perishable": 0,
        "reason": "Cliente solicitó cambio de referencia de producto no perecedero",
        "recov": 30.40,
        "loss": 0.0,
        "refund": 0.0,
        "credit": 0.0,
        "status": "cerrada",
        "insp_notes": "Sello de fábrica intacto, código de barras legible, apto para venta.",
        "evidence": "/img/control_center/devolucion_ok.jpg"
    },
    {
        "code": "DEV-2026-0003",
        "order_id": None,
        "product_id": 3,
        "type": "rechazo_recepcion",
        "qty": 40.0,
        "cost": 3.80,
        "classification": "devolucion_proveedor",
        "is_perishable": 1,
        "reason": "Lote recibido con vida útil menor al 30% del convenio comercial",
        "recov": 152.0,
        "loss": 0.0,
        "refund": 0.0,
        "credit": 152.0,
        "status": "aprobada",
        "insp_notes": "Rechazo en muelle OTIF. Proveedor emite nota de crédito directa.",
        "evidence": "/img/control_center/rechazo_proveedor.jpg"
    },
    {
        "code": "DEV-2026-0004",
        "order_id": order_id_1,
        "product_id": 4,
        "type": "daño_transporte",
        "qty": 12.0,
        "cost": 4.50,
        "classification": "merma_definitiva",
        "is_perishable": 1,
        "reason": "Avería por estiba colapsada en camión de reparto",
        "recov": 0.0,
        "loss": 54.0,
        "refund": 65.0,
        "credit": 0.0,
        "status": "conciliada",
        "insp_notes": "Destrucción autorizada por gerencia de operaciones. Descargado de Kardex.",
        "evidence": "/img/control_center/merma_averia.jpg"
    },
    {
        "code": "DEV-2026-0005",
        "order_id": None,
        "product_id": 5,
        "type": "calidad",
        "qty": 15.0,
        "cost": 2.10,
        "classification": "donacion",
        "is_perishable": 0,
        "reason": "Defecto estético en etiqueta exterior, producto inocuo y apto para consumo",
        "recov": 31.50,
        "loss": 0.0,
        "refund": 0.0,
        "credit": 0.0,
        "status": "disposicion",
        "insp_notes": "Entregado a Banco de Alimentos bajo acta de donación con beneficio tributario.",
        "evidence": "/img/control_center/donacion_alimentos.jpg"
    },
    {
        "code": "DEV-2026-0006",
        "order_id": order_id_1,
        "product_id": 1,
        "type": "vencido",
        "qty": 8.0,
        "cost": 6.40,
        "classification": "destruccion",
        "is_perishable": 1,
        "reason": "Producto vencido en exhibición devuelto por reclamo de cliente",
        "recov": 0.0,
        "loss": 51.20,
        "refund": 68.0,
        "credit": 0.0,
        "status": "cerrada",
        "insp_notes": "Acta de desnaturalización ambiental firmada. Alerta de calidad generada.",
        "evidence": "/img/control_center/destruccion.jpg"
    }
]

for r in returns_data:
    tot_cost = round(r["qty"] * r["cost"], 2)
    c.execute("""
        INSERT INTO customer_returns_exchanges
        (return_code, order_id, branch_id, customer_id, product_id, stock_id, return_type,
         quantity, unit_cost, total_cost, recoverable_value, definitive_loss_value,
         supplier_credit_amount, customer_refund_amount, classification, is_perishable,
         inspection_result, inspection_notes, inspected_by_user_id, inspected_at,
         approval_status, approved_by_user_id, approved_at, disposition_notes,
         evidence_photo_url, status, created_at)
        VALUES (?, ?, 1, 1, ?, 'LOT-DEV-01', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inspeccionado', ?, 1, datetime('now', '-2 hours'), 'approved', 1, datetime('now', '-1 hours'), ?, ?, ?, datetime('now', '-1 days'))
    """, (
        r["code"], r["order_id"], r["product_id"], r["type"], r["qty"], r["cost"],
        tot_cost, r["recov"], r["loss"], r["credit"], r["refund"], r["classification"],
        r["is_perishable"], r["insp_notes"], r["insp_notes"], r["evidence"], r["status"]
    ))

print("Seeded 6 customer returns & exchanges with financial controls.")

# -------------------------------------------------------------------
# 4. SEED BAKERY PRODUCTION & WASTE INTELLIGENCE
# -------------------------------------------------------------------
c.execute("DELETE FROM bakery_production_waste")

# Prompt exact demonstration benchmark:
# Baguette: Producido 300, Vendido 245, Merma 55, % sobre producción 18,3%
# Donas: Producido 240, Vendido 205, Merma 35, % sobre producción 14,6%
# Croissant: Producido 200, Vendido 180, Merma 20, % sobre producción 10,0%
# Muffin: Producido 150, Vendido 140, Merma 10, % sobre producción 6,7%
bakery_catalog = [
    {"name": "Baguette Rústica", "subtype": "Baguettes", "cost": 0.85, "prod": 300, "sold": 245, "waste": 55, "cause": "sobreproduccion", "sug": 260, "range": 15, "reason": "Sobreproducción en turno tarde. Ventas caen 35% después de las 18:00."},
    {"name": "Donas Glaseadas", "subtype": "Donas", "cost": 0.65, "prod": 240, "sold": 205, "waste": 35, "cause": "vencimiento", "sug": 215, "range": 10, "reason": "Vida útil de 24 horas. Ajustar tanda de horneo de las 14:00."},
    {"name": "Croissant Francés Mantequilla", "subtype": "Croissant", "cost": 0.95, "prod": 200, "sold": 180, "waste": 20, "cause": "calidad_textura", "sug": 185, "range": 10, "reason": "Pérdida de crocancia a las 12 horas. Recomendar venta combo matutino."},
    {"name": "Muffin Arándanos & Vainilla", "subtype": "Muffin", "cost": 0.75, "prod": 150, "sold": 140, "waste": 10, "cause": "manipulacion", "sug": 145, "range": 8, "reason": "Merma controlada (<7%). Faltas leves por empaque en vitrina."},
    {"name": "Pan Francés Tradicional", "subtype": "Pan Francés", "cost": 0.35, "prod": 450, "sold": 390, "waste": 60, "cause": "sobreproduccion", "sug": 405, "range": 20, "reason": "Tanda de las 17:00 excede demanda promedio de lunes y martes."},
    {"name": "Pan de Molde Integral Artesanal", "subtype": "Pan de Molde", "cost": 1.40, "prod": 120, "sold": 102, "waste": 18, "cause": "vencimiento", "sug": 108, "range": 6, "reason": "Rotación lenta a mitad de semana. FEFO en góndola requiere ajuste."},
    {"name": "Torta Tres Leches Individual", "subtype": "Tortas", "cost": 2.20, "prod": 80, "sold": 68, "waste": 12, "cause": "vencimiento", "sug": 72, "range": 5, "reason": "Cadena de frío óptima pero vida útil corta (3 días). Recomendar promociones al día 2."},
    {"name": "Pastel Gloria Bocadillo y Queso", "subtype": "Pasteles", "cost": 0.55, "prod": 180, "sold": 162, "waste": 18, "cause": "daño_horneo", "sug": 170, "range": 10, "reason": "Temperatura excesiva en horno 2 genera 8% de merma por quemado."},
    {"name": "Galletas Chispas de Chocolate", "subtype": "Galletas", "cost": 0.40, "prod": 250, "sold": 235, "waste": 15, "cause": "manipulacion", "sug": 240, "range": 12, "reason": "Merma baja y alta rotación. Demanda estable en todos los turnos."}
]

days_names = {1: "Lunes", 2: "Martes", 3: "Miércoles", 4: "Jueves", 5: "Viernes", 6: "Sábado", 7: "Domingo"}
shifts_pool = ["mañana", "tarde", "noche"]

for item in bakery_catalog:
    # Insert benchmark row (October 25, 2026)
    w_cost = round(item["waste"] * item["cost"], 2)
    pct_prod = round((item["waste"] / item["prod"]) * 100.0, 1)
    pct_sales = round((item["waste"] / item["sold"]) * 100.0, 1) if item["sold"] > 0 else 0.0

    c.execute("""
        INSERT INTO bakery_production_waste
        (branch_id, product_id, product_name, category_subtype, batch_lot, production_date,
         shift, hour_of_day, day_of_week, day_name, produced_qty, sold_qty, waste_qty,
         unit_cost, waste_cost, waste_pct_production, waste_pct_sales, cause,
         suggested_production_qty, uncertainty_range_qty, forecast_reasoning, notes)
        VALUES (1, 1, ?, ?, ?, '2026-10-25', 'mañana', 7, 7, 'Domingo', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Producción matutina regular')
    """, (
        item["name"], item["subtype"], f"LOT-BAK-{item['subtype'][:3].upper()}-01",
        item["prod"], item["sold"], item["waste"], item["cost"], w_cost, pct_prod, pct_sales,
        item["cause"], item["sug"], item["range"], item["reason"]
    ))

    # Add historical points across previous days of week and shifts
    for d_offset in range(1, 14):
        p_date = (datetime.date(2026, 10, 25) - datetime.timedelta(days=d_offset))
        dow = p_date.isoweekday()
        shift = random.choice(shifts_pool)
        hour = 6 if shift == "mañana" else (14 if shift == "tarde" else 21)

        # Apply realistic weekday variability
        mult = 1.25 if dow in [5, 6, 7] else 0.85
        h_prod = int(round(item["prod"] * mult * random.uniform(0.9, 1.1)))
        h_waste = int(round(item["waste"] * mult * random.uniform(0.7, 1.3)))
        h_sold = max(0, h_prod - h_waste)
        hw_cost = round(h_waste * item["cost"], 2)
        hpct_prod = round((h_waste / h_prod) * 100.0, 1) if h_prod > 0 else 0
        hpct_sales = round((h_waste / h_sold) * 100.0, 1) if h_sold > 0 else 0

        c.execute("""
            INSERT INTO bakery_production_waste
            (branch_id, product_id, product_name, category_subtype, batch_lot, production_date,
             shift, hour_of_day, day_of_week, day_name, produced_qty, sold_qty, waste_qty,
             unit_cost, waste_cost, waste_pct_production, waste_pct_sales, cause,
             suggested_production_qty, uncertainty_range_qty, forecast_reasoning, notes)
            VALUES (1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Registro histórico de producción')
        """, (
            item["name"], item["subtype"], f"LOT-BAK-{item['subtype'][:3].upper()}-{d_offset:02d}",
            p_date.isoformat(), shift, hour, dow, days_names[dow],
            h_prod, h_sold, h_waste, item["cost"], hw_cost, hpct_prod, hpct_sales,
            random.choice(["vencimiento", "sobreproduccion", "manipulacion", "daño_horneo"]),
            item["sug"], item["range"], item["reason"]
        ))

print("Seeded bakery production, waste and AI forecast data.")

conn.commit()
conn.close()
print("All Retail Loss Prevention seeding completed successfully!")
