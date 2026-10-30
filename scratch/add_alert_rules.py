import sqlite3

conn = sqlite3.connect('data/grocy_es.db')
cur = conn.cursor()

# Check existing codes
existing = [r[0] for r in cur.execute("SELECT code FROM dairy_alert_rules").fetchall()]

if 'dispatch_variance' not in existing:
    cur.execute("""
        INSERT INTO dairy_alert_rules (
            code, name, category, severity, enabled, threshold, due_hours,
            requires_approval, cooldown_hours, escalate_after_hours,
            notify_channel, recommended_action, frequency_minutes, department
        ) VALUES (
            'dispatch_variance', 'Variación no conciliada en despacho / entrega',
            'operations', 'warning', 1, 0, 12,
            0, 6, 24,
            'ui', 'Auditar cantidades escaneadas vs solicitadas y registrar resolución o ajuste en /dispatch', 15, 'logistica'
        )
    """)
    print("Added dispatch_variance rule")

if 'predictive_expiry_risk' not in existing:
    cur.execute("""
        INSERT INTO dairy_alert_rules (
            code, name, category, severity, enabled, threshold, due_hours,
            requires_approval, cooldown_hours, escalate_after_hours,
            notify_channel, recommended_action, frequency_minutes, department
        ) VALUES (
            'predictive_expiry_risk', 'Riesgo predictivo de vencimiento FEFO',
            'quality', 'danger', 1, 0, 24,
            1, 6, 48,
            'ui', 'Priorizar rotación FEFO, descuento autorizado o traslado intersucursal en /quality', 15, 'perecederos'
        )
    """)
    print("Added predictive_expiry_risk rule")

conn.commit()
conn.close()
print("Migration completed successfully.")
