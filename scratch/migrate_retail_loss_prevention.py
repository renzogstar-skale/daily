import sqlite3
import datetime
import random
import json

db_path = "data/grocy_es.db"
conn = sqlite3.connect(db_path)
c = conn.cursor()

print("Applying migrations for Retail Loss Prevention, Expirations, Dispatch Variances, Returns & Bakery...")

# 1. EXPIRY CATEGORY RULES
c.execute("""
CREATE TABLE IF NOT EXISTS expiry_category_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_name TEXT UNIQUE NOT NULL,
    alert_days_json TEXT NOT NULL,
    shelf_life_days INTEGER NOT NULL,
    recommended_action TEXT NOT NULL,
    auto_markdown_discount REAL NOT NULL DEFAULT 0.20,
    escalate_hours INTEGER NOT NULL DEFAULT 24,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
""")

# Seed default category rules as required by prompt
default_cat_rules = [
    ("Bakery / Panadería", "[1, 2, 3]", 2, "fefo_rotation", 0.30, 12),
    ("Carnes frescas", "[1, 2, 3]", 5, "fefo_rotation", 0.25, 12),
    ("Pollo fresco", "[1, 2, 3]", 4, "fefo_rotation", 0.25, 12),
    ("Dairy / Lácteos", "[3, 5, 7]", 21, "markdown_discount", 0.20, 24),
    ("Frutas y verduras", "[2, 4]", 6, "shelf_reposition", 0.35, 12),
    ("Congelados", "[7, 15, 30]", 180, "branch_transfer", 0.15, 48),
    ("Productos secos", "[15, 30, 60]", 365, "supplier_return", 0.10, 72),
]
for cat, days_json, shelf, act, disc, esc in default_cat_rules:
    c.execute("""
        INSERT OR REPLACE INTO expiry_category_rules 
        (category_name, alert_days_json, shelf_life_days, recommended_action, auto_markdown_discount, escalate_hours)
        VALUES (?, ?, ?, ?, ?, ?)
    """, (cat, days_json, shelf, act, disc, esc))

# 2. EXPIRY PREDICTIVE ALERTS
c.execute("""
CREATE TABLE IF NOT EXISTS expiry_predictive_alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    alert_code TEXT UNIQUE NOT NULL,
    branch_id INTEGER NOT NULL DEFAULT 1,
    product_id INTEGER NOT NULL,
    stock_id TEXT NOT NULL,
    location_id INTEGER,
    category_name TEXT NOT NULL,
    best_before_date DATE NOT NULL,
    days_left INTEGER NOT NULL,
    available_amount REAL NOT NULL,
    unit_cost REAL NOT NULL,
    total_value_risk REAL NOT NULL,
    avg_daily_sales REAL NOT NULL,
    estimated_demand_before_expiry REAL NOT NULL,
    units_at_risk REAL NOT NULL,
    turnover_days REAL NOT NULL DEFAULT 0.0,
    recommended_action TEXT NOT NULL,
    assigned_to_user_id INTEGER,
    resolution_status TEXT NOT NULL DEFAULT 'alerta',
    resolution_action_taken TEXT,
    evidence_notes TEXT,
    evidence_photo_url TEXT,
    resolved_at DATETIME,
    resolution_time_hours REAL,
    alert_center_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
""")

# 3. EXTEND customer_orders with Delivery Type, Carrier, Pick-up PIN & Conciliation
order_cols = [col[1] for col in c.execute("PRAGMA table_info(customer_orders)").fetchall()]
new_order_cols = [
    ("delivery_type", "TEXT DEFAULT 'pickup'"),
    ("pickup_pin", "TEXT DEFAULT '123456'"),
    ("pickup_qr_token", "TEXT"),
    ("pickup_verified_by", "INTEGER"),
    ("pickup_verified_at", "DATETIME"),
    ("carrier_name", "TEXT"),
    ("carrier_vehicle", "TEXT"),
    ("carrier_tracking_number", "TEXT"),
    ("carrier_phone", "TEXT"),
    ("departure_time", "DATETIME"),
    ("delivery_time", "DATETIME"),
    ("delivery_status", "TEXT DEFAULT 'pending'"),
    ("delivery_evidence_url", "TEXT"),
    ("delivery_signature_name", "TEXT"),
    ("route_incidents", "TEXT"),
    ("cold_chain_temp_ok", "INTEGER DEFAULT 1"),
    ("preparation_accuracy_pct", "REAL DEFAULT 100.0"),
    ("delivery_accuracy_pct", "REAL DEFAULT 100.0"),
    ("variance_total_cost", "REAL DEFAULT 0.0"),
    ("conciliated_by_user_id", "INTEGER"),
    ("conciliated_at", "DATETIME")
]
for col_name, col_def in new_order_cols:
    if col_name not in order_cols:
        c.execute(f"ALTER TABLE customer_orders ADD COLUMN {col_name} {col_def}")
        print(f"Added column customer_orders.{col_name}")

# 4. DISPATCH VARIANCES TABLE (7-stage comparison & preparation vs delivery diffs)
c.execute("""
CREATE TABLE IF NOT EXISTS dispatch_variances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    item_id INTEGER,
    product_id INTEGER NOT NULL,
    stage TEXT NOT NULL, -- 'preparation', 'delivery', 'reception'
    variance_type TEXT NOT NULL, -- 'faltante', 'sobrante', 'producto_equivocado', 'upc_incorrecto', 'producto_dañado', 'producto_vencido', 'producto_sustituido', 'diferencia_peso', 'diferencia_unidades', 'error_preparacion', 'error_entrega', 'error_recepcion'
    requested_qty REAL NOT NULL DEFAULT 0.0,
    prepared_qty REAL NOT NULL DEFAULT 0.0,
    scanned_qty REAL NOT NULL DEFAULT 0.0,
    dispatched_qty REAL NOT NULL DEFAULT 0.0,
    received_qty REAL NOT NULL DEFAULT 0.0,
    returned_qty REAL NOT NULL DEFAULT 0.0,
    difference_qty REAL NOT NULL DEFAULT 0.0,
    unit_cost REAL NOT NULL DEFAULT 0.0,
    financial_impact REAL NOT NULL DEFAULT 0.0,
    resolution_notes TEXT,
    authorized_by_user_id INTEGER,
    resolution_status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'authorized_exception', 'waste_recorded', 'credited', 'resolved'
    waste_record_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
""")

# 5. DISPATCH SCANS (Immediate Barcode Verification)
c.execute("""
CREATE TABLE IF NOT EXISTS dispatch_scans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    barcode_scanned TEXT NOT NULL,
    product_id INTEGER,
    stock_id TEXT,
    expected_product_id INTEGER,
    expected_qty REAL DEFAULT 1.0,
    scanned_qty REAL DEFAULT 1.0,
    match_status TEXT NOT NULL, -- 'match', 'warning', 'mismatch', 'overage'
    operator_user_id INTEGER DEFAULT 1,
    scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
""")

# 6. CUSTOMER RETURNS & EXCHANGES (Full 8-step lifecycle & financial control)
c.execute("""
CREATE TABLE IF NOT EXISTS customer_returns_exchanges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    return_code TEXT UNIQUE NOT NULL,
    order_id INTEGER,
    branch_id INTEGER NOT NULL DEFAULT 1,
    customer_id INTEGER,
    product_id INTEGER NOT NULL,
    stock_id TEXT,
    return_type TEXT NOT NULL, -- 'cambio', 'devolucion_cliente', 'devolucion_proveedor', 'rechazo_recepcion', 'daño_transporte', 'producto_incorrecto', 'calidad', 'vencido', 'diferencia_despacho'
    quantity REAL NOT NULL DEFAULT 1.0,
    unit_cost REAL NOT NULL DEFAULT 0.0,
    total_cost REAL NOT NULL DEFAULT 0.0,
    recoverable_value REAL NOT NULL DEFAULT 0.0,
    definitive_loss_value REAL NOT NULL DEFAULT 0.0,
    supplier_credit_amount REAL NOT NULL DEFAULT 0.0,
    customer_refund_amount REAL NOT NULL DEFAULT 0.0,
    classification TEXT, -- 'reincorporacion', 'cuarentena', 'devolucion_proveedor', 'recuperacion', 'donacion', 'merma_definitiva', 'destruccion'
    is_perishable INTEGER NOT NULL DEFAULT 0,
    inspection_result TEXT,
    inspection_notes TEXT,
    inspected_by_user_id INTEGER,
    inspected_at DATETIME,
    approval_status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    approved_by_user_id INTEGER,
    approved_at DATETIME,
    disposition_notes TEXT,
    evidence_photo_url TEXT,
    status TEXT NOT NULL DEFAULT 'solicitud', -- 'solicitud', 'identificacion', 'inspeccion', 'clasificacion', 'aprobada', 'disposicion', 'conciliada', 'cerrada'
    waste_record_id INTEGER,
    stock_booking_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
""")

# 7. BAKERY PRODUCTION & WASTE INTELLIGENCE
c.execute("""
CREATE TABLE IF NOT EXISTS bakery_production_waste (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL DEFAULT 1,
    product_id INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    category_subtype TEXT NOT NULL, -- 'Baguettes', 'Donas', 'Croissant', 'Muffin', 'Pan Francés', 'Pan de Molde', 'Tortas', 'Pasteles', 'Galletas'
    batch_lot TEXT NOT NULL,
    production_date DATE NOT NULL,
    shift TEXT NOT NULL, -- 'mañana', 'tarde', 'noche'
    hour_of_day INTEGER NOT NULL,
    day_of_week INTEGER NOT NULL, -- 1=Lunes ... 7=Domingo
    day_name TEXT NOT NULL,
    produced_qty REAL NOT NULL DEFAULT 0.0,
    sold_qty REAL NOT NULL DEFAULT 0.0,
    waste_qty REAL NOT NULL DEFAULT 0.0,
    unit_cost REAL NOT NULL DEFAULT 0.0,
    waste_cost REAL NOT NULL DEFAULT 0.0,
    waste_pct_production REAL NOT NULL DEFAULT 0.0,
    waste_pct_sales REAL NOT NULL DEFAULT 0.0,
    cause TEXT NOT NULL, -- 'vencimiento', 'sobreproduccion', 'manipulacion', 'daño_horneo', 'calidad_textura', 'devolucion'
    suggested_production_qty REAL,
    uncertainty_range_qty REAL,
    forecast_reasoning TEXT,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
""")

conn.commit()
print("Base schema created successfully.")
conn.close()
