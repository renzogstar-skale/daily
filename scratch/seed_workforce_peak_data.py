import sqlite3
import json
import datetime
import math

conn = sqlite3.connect('data/grocy_es.db')
cur = conn.cursor()

print("Seeding Workforce Peak & Intelligent Staffing data...")

# 1. Seed Area Productivity Standards
areas = [
    {
        'area_name': 'Ventas',
        'target_splh': 165.0,
        'target_uph': 55.0,
        'service_time_min_per_ticket': 2.8,
        'restock_min_per_unit': 0.8,
        'dispatch_min_per_order': 4.0,
        'min_staff_open': 2,
        'min_staff_peak': 7,
        'shrinkage_factor_pct': 15.0,
        'hourly_cost_avg': 13.5,
        'sales_share_pct': 35.0
    },
    {
        'area_name': 'Operaciones',
        'target_splh': 140.0,
        'target_uph': 45.0,
        'service_time_min_per_ticket': 3.5,
        'restock_min_per_unit': 1.5,
        'dispatch_min_per_order': 6.0,
        'min_staff_open': 1,
        'min_staff_peak': 4,
        'shrinkage_factor_pct': 12.0,
        'hourly_cost_avg': 12.0,
        'sales_share_pct': 20.0
    },
    {
        'area_name': 'Digital',
        'target_splh': 150.0,
        'target_uph': 48.0,
        'service_time_min_per_ticket': 2.2,
        'restock_min_per_unit': 1.0,
        'dispatch_min_per_order': 8.0,
        'min_staff_open': 1,
        'min_staff_peak': 3,
        'shrinkage_factor_pct': 10.0,
        'hourly_cost_avg': 14.0,
        'sales_share_pct': 15.0
    },
    {
        'area_name': 'Despachos',
        'target_splh': 130.0,
        'target_uph': 38.0,
        'service_time_min_per_ticket': 4.0,
        'restock_min_per_unit': 1.8,
        'dispatch_min_per_order': 9.5,
        'min_staff_open': 1,
        'min_staff_peak': 2,
        'shrinkage_factor_pct': 10.0,
        'hourly_cost_avg': 12.5,
        'sales_share_pct': 10.0
    },
    {
        'area_name': 'Accesorios',
        'target_splh': 145.0,
        'target_uph': 42.0,
        'service_time_min_per_ticket': 2.5,
        'restock_min_per_unit': 0.9,
        'dispatch_min_per_order': 3.5,
        'min_staff_open': 1,
        'min_staff_peak': 2,
        'shrinkage_factor_pct': 15.0,
        'hourly_cost_avg': 13.0,
        'sales_share_pct': 10.0
    },
    {
        'area_name': 'Apple',
        'target_splh': 210.0,
        'target_uph': 25.0,
        'service_time_min_per_ticket': 6.5,
        'restock_min_per_unit': 1.2,
        'dispatch_min_per_order': 5.0,
        'min_staff_open': 1,
        'min_staff_peak': 2,
        'shrinkage_factor_pct': 12.0,
        'hourly_cost_avg': 16.5,
        'sales_share_pct': 10.0
    },
    {
        'area_name': 'Bakery',
        'target_splh': 115.0,
        'target_uph': 32.0,
        'service_time_min_per_ticket': 2.0,
        'restock_min_per_unit': 1.4,
        'dispatch_min_per_order': 3.0,
        'min_staff_open': 1,
        'min_staff_peak': 3,
        'shrinkage_factor_pct': 15.0,
        'hourly_cost_avg': 11.5,
        'sales_share_pct': 8.0
    },
    {
        'area_name': 'Carnes',
        'target_splh': 125.0,
        'target_uph': 34.0,
        'service_time_min_per_ticket': 3.8,
        'restock_min_per_unit': 1.6,
        'dispatch_min_per_order': 4.0,
        'min_staff_open': 1,
        'min_staff_peak': 3,
        'shrinkage_factor_pct': 14.0,
        'hourly_cost_avg': 13.0,
        'sales_share_pct': 12.0
    },
    {
        'area_name': 'Dairy',
        'target_splh': 155.0,
        'target_uph': 52.0,
        'service_time_min_per_ticket': 2.0,
        'restock_min_per_unit': 1.1,
        'dispatch_min_per_order': 4.5,
        'min_staff_open': 1,
        'min_staff_peak': 3,
        'shrinkage_factor_pct': 12.0,
        'hourly_cost_avg': 12.0,
        'sales_share_pct': 16.0
    },
    {
        'area_name': 'Frutas y Verduras',
        'target_splh': 120.0,
        'target_uph': 40.0,
        'service_time_min_per_ticket': 2.5,
        'restock_min_per_unit': 1.5,
        'dispatch_min_per_order': 4.0,
        'min_staff_open': 1,
        'min_staff_peak': 3,
        'shrinkage_factor_pct': 15.0,
        'hourly_cost_avg': 11.5,
        'sales_share_pct': 14.0
    }
]

cur.execute("DELETE FROM workforce_area_productivity_standards WHERE branch_id = 1")
for a in areas:
    cur.execute("""
        INSERT INTO workforce_area_productivity_standards (
            branch_id, area_name, target_splh, target_uph, service_time_min_per_ticket,
            restock_min_per_unit, dispatch_min_per_order, min_staff_open, min_staff_peak,
            shrinkage_factor_pct, hourly_cost_avg, sales_share_pct
        ) VALUES (
            1, :area_name, :target_splh, :target_uph, :service_time_min_per_ticket,
            :restock_min_per_unit, :dispatch_min_per_order, :min_staff_open, :min_staff_peak,
            :shrinkage_factor_pct, :hourly_cost_avg, :sales_share_pct
        )
    """, a)

print("Area productivity standards seeded.")

# 2. Seed Hourly Demand Matrix (7 days x 24 hours) for week 2026-11-02 to 2026-11-08
cur.execute("DELETE FROM workforce_hourly_demand WHERE branch_id = 1")

base_monday = datetime.date(2026, 11, 2)
# Hour profiles (hourly distribution weight across 06:00 to 22:00)
# Peak indicators:
# Lunes: peak 12:00-14:00
# Viernes: peak 17:00-20:00
# Sábado: peak 11:00-15:00

for day_offset in range(7):
    cur_date = base_monday + datetime.timedelta(days=day_offset)
    date_str = cur_date.strftime('%Y-%m-%d')
    day_of_week = cur_date.weekday() # 0 = Lun, 4 = Vie, 5 = Sab, 6 = Dom

    # Daily sales target base
    if day_of_week == 0: # Lunes
        daily_sales = 38000.0
    elif day_of_week == 1: # Martes
        daily_sales = 36000.0
    elif day_of_week == 2: # Miercoles
        daily_sales = 40000.0
    elif day_of_week == 3: # Jueves
        daily_sales = 42000.0
    elif day_of_week == 4: # Viernes
        daily_sales = 54000.0
    elif day_of_week == 5: # Sabado
        daily_sales = 58000.0
    else: # Domingo
        daily_sales = 46000.0

    for hour in range(24):
        if hour < 6 or hour > 22:
            # Store closed / night maintenance
            sales = 0.0
            tx = 0
            traffic = 0
            units = 0.0
            demand_level = 'low'
            req_staff = 0 if (hour < 5 or hour > 23) else 1
            sched_staff = 0
        else:
            # Weighting
            w = 0.04
            if day_of_week == 0 and hour in [12, 13]: # Lunes peak
                w = 0.12
            elif day_of_week == 4 and hour in [17, 18, 19]: # Viernes peak
                w = 0.13
            elif day_of_week == 5 and hour in [11, 12, 13, 14]: # Sabado peak
                w = 0.11
            elif hour in [11, 12, 13, 17, 18, 19]:
                w = 0.08
            elif hour in [8, 9, 10, 14, 15, 16]:
                w = 0.06
            elif hour in [6, 7, 21, 22]:
                w = 0.03

            sales = round(daily_sales * w, 2)
            avg_ticket = 26.5
            tx = max(1, int(round(sales / avg_ticket)))
            traffic = int(round(tx * 1.35))
            units = round(tx * 3.4, 1)

            # Workload calculation:
            # Service min = tx * 2.8 min
            # Restock min = units * 0.8 min
            # Operational min = 15 min fixed
            service_min = tx * 2.8
            restock_min = units * 0.8
            op_min = 20.0
            total_workload_min = service_min + restock_min + op_min

            # Staff required = ceil(total_workload_min / (60 * (1 - shrinkage)))
            eff_min_per_staff = 60.0 * 0.85 # 51 min
            req_staff = max(1, math.ceil(total_workload_min / eff_min_per_staff))

            # Scheduled staff estimation
            sched_staff = req_staff
            if day_of_week == 4 and hour == 18:
                sched_staff = req_staff - 2 # Simulate subcoverage for alert!
            elif day_of_week == 5 and hour == 13:
                sched_staff = req_staff - 1 # Simulate slight gap

            gap = sched_staff - req_staff

            if req_staff >= 8 or (w >= 0.10):
                demand_level = 'peak'
            elif req_staff >= 5 or (w >= 0.07):
                demand_level = 'high'
            elif req_staff >= 3:
                demand_level = 'normal'
            else:
                demand_level = 'low'

            avg_service_sec = 168.0

        cur.execute("""
            INSERT INTO workforce_hourly_demand (
                branch_id, date, hour, minute_interval, sales_amount,
                transactions_count, units_sold, foot_traffic, avg_ticket,
                avg_service_time_seconds, replenishment_units, pending_dispatches,
                demand_level, workload_minutes, staff_required, staff_scheduled, coverage_gap
            ) VALUES (?, ?, ?, 60, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            1, date_str, hour, sales, tx, units, traffic,
            round(sales/tx, 2) if tx > 0 else 0,
            avg_service_sec if 'avg_service_sec' in locals() else 180,
            int(units * 0.7), int(tx * 0.15),
            demand_level, round(total_workload_min, 1) if 'total_workload_min' in locals() else 0,
            req_staff, sched_staff, gap if 'gap' in locals() else 0
        ))

print("Hourly demand seeded for the full week.")

# 3. Seed Commercial Events & Special Black Friday
cur.execute("DELETE FROM workforce_commercial_events")
events = [
    (
        1, 'Black Friday 2026', '2026-11-27', '2026-11-29', 'black_friday',
        80.0, 115.0, '07:00-23:00', json.dumps(["10:00-14:00", "17:00-21:00"]),
        8, 40000.0, 72000.0,
        'Incremento proyectado del 80% en ventas ($72k vs $40k base). Refuerzo en cajas, despacho y reposición continua.'
    ),
    (
        1, 'Cyber Monday 2026', '2026-11-30', '2026-12-01', 'cyber',
        45.0, 30.0, '07:00-22:00', json.dumps(["08:00-12:00", "18:00-22:00"]),
        4, 40000.0, 58000.0,
        'Foco en Bodega Digital, Despachos y Click & Collect. Refuerzo en picking y verificación.'
    ),
    (
        1, 'Navidad & Fin de Año 2026', '2026-12-18', '2026-12-24', 'holiday',
        65.0, 90.0, '07:00-23:00', json.dumps(["11:00-15:00", "17:00-22:00"]),
        7, 40000.0, 66000.0,
        'Campaña navideña con alto tráfico de perecederos, panadería y carnes para cenas de temporada.'
    ),
    (
        1, 'Quincena y Pago de Salarios', '2026-11-14', '2026-11-16', 'payday',
        25.0, 35.0, '07:00-21:00', json.dumps(["12:00-14:00", "17:00-19:30"]),
        3, 40000.0, 50000.0,
        'Pico quincenal de canasta familiar y abarrotes.'
    )
]

for ev in events:
    cur.execute("""
        INSERT INTO workforce_commercial_events (
            branch_id, event_name, start_date, end_date, event_type,
            sales_uplift_pct, traffic_uplift_pct, extended_hours, peak_hours_json,
            reinforcement_staff_needed, normal_sales_base, projected_sales, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, ev)

print("Commercial events seeded.")

# 4. Seed Daily Staffing Simulations (Prompt Benchmarks)
cur.execute("DELETE FROM workforce_peak_staffing_simulations")
simulations = [
    (
        1, 'Día de Ventas Bajas ($15k)', 15000.0, 6,
        json.dumps({'Ventas': 2, 'Operaciones': 1, 'Digital': 1, 'Despachos': 1, 'Accesorios': 1, 'Apple': 0}),
        json.dumps({'07-11': 2, '11-15': 5, '15-19': 4, '19-22': 2}),
        648.0, 4.32, 173.6
    ),
    (
        1, 'Día de Ventas Normales ($25k)', 25000.0, 9,
        json.dumps({'Ventas': 3, 'Operaciones': 2, 'Digital': 1, 'Despachos': 1, 'Accesorios': 1, 'Apple': 1}),
        json.dumps({'07-11': 3, '11-15': 7, '15-19': 7, '19-22': 3}),
        972.0, 3.88, 171.4
    ),
    (
        1, 'Día de Ventas Base ($40k)', 40000.0, 12,
        json.dumps({'Ventas': 4, 'Operaciones': 2, 'Digital': 2, 'Despachos': 1, 'Accesorios': 2, 'Apple': 1}),
        json.dumps({'07-11': 4, '11-15': 10, '15-19': 10, '19-22': 4}),
        1296.0, 3.24, 169.8
    ),
    (
        1, 'Día de Ventas Altas ($60k)', 60000.0, 16,
        json.dumps({'Ventas': 5, 'Operaciones': 3, 'Digital': 2, 'Despachos': 2, 'Accesorios': 2, 'Apple': 2}),
        json.dumps({'07-11': 6, '11-15': 14, '15-19': 14, '19-22': 6}),
        1728.0, 2.88, 166.7
    ),
    (
        1, 'Día Extraordinario / Peak ($100k)', 100000.0, 24,
        json.dumps({'Ventas': 8, 'Operaciones': 5, 'Digital': 3, 'Despachos': 3, 'Accesorios': 3, 'Apple': 2}),
        json.dumps({'07-11': 8, '11-15': 22, '15-19': 22, '19-22': 8}),
        2592.0, 2.59, 160.2
    ),
    (
        1, 'Distribución Benchmark 20 Colaboradores', 75000.0, 20,
        json.dumps({'Ventas': 7, 'Operaciones': 4, 'Digital': 3, 'Despachos': 2, 'Accesorios': 2, 'Apple': 2}),
        json.dumps({'07-11': 6, '11-15': 18, '15-19': 18, '19-22': 7}),
        2160.0, 2.88, 166.7
    )
]

for s in simulations:
    cur.execute("""
        INSERT INTO workforce_peak_staffing_simulations (
            branch_id, simulation_name, target_daily_sales, staff_required_total,
            staff_by_area_json, hourly_distribution_json, projected_labor_cost,
            labor_pct_of_sales, splh_projected
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, s)

print("Staffing simulations seeded.")

# 5. Seed Demand Forecasts (Horizons and Scenarios)
cur.execute("DELETE FROM workforce_demand_forecasts WHERE branch_id = 1")
forecasts = [
    (
        1, '2026-11-03', 'tomorrow',
        32000.0, 38000.0, 44000.0,
        10, 12, 14, 8.5,
        json.dumps(['07:00-15:00', '09:00-17:00', '11:00-19:00', '13:00-21:00']), 1
    ),
    (
        1, '2026-11-09', '7days',
        260000.0, 314000.0, 365000.0,
        18, 21, 25, 10.0,
        json.dumps(['Turnos estándar 40h escalonados']), 1
    ),
    (
        1, '2026-12-01', '30days',
        1150000.0, 1380000.0, 1620000.0,
        20, 23, 28, 12.0,
        json.dumps(['Refuerzos por campaña de fin de año']), 1
    ),
    (
        1, '2026-12-01', 'next_month',
        1200000.0, 1450000.0, 1750000.0,
        22, 25, 30, 14.0,
        json.dumps(['Diciembre Navidad']), 1
    ),
    (
        1, '2027-01-31', '3months',
        3200000.0, 3850000.0, 4400000.0,
        20, 23, 27, 15.0,
        json.dumps(['Primer trimestre consolidado']), 1
    )
]

for fc in forecasts:
    cur.execute("""
        INSERT INTO workforce_demand_forecasts (
            branch_id, forecast_date, horizon, scenario_conservative_sales,
            scenario_base_sales, scenario_high_demand_sales, conservative_staff,
            base_staff, high_demand_staff, uncertainty_range_pct, recommended_shifts_json, version
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, fc)

print("Demand forecasts seeded.")

# 6. Seed Actionable Staffing Recommendations
cur.execute("DELETE FROM workforce_peak_recommendations WHERE branch_id = 1")
recs = [
    (
        1, '2026-11-06', '17:00 - 20:00', 'Ventas', 'reinforce_peak',
        'Reforzar franja peak de Viernes tarde (+2 colab). La demanda proyecta 12 colaboradores y sólo hay 10 programados.',
        2, 1, 'BRAYER RUBIO', 'pending', 75.0
    ),
    (
        1, '2026-11-07', '11:00 - 15:00', 'Despachos', 'shift_stagger',
        'Escalonar 2 descansos de picking para no dejar desprotegido el empaque en hora peak.',
        0, 2, 'CUBIDES EDDY', 'pending', 0.0
    ),
    (
        1, '2026-11-27', '10:00 - 21:00', 'Operaciones', 'temp_contract',
        'Contratación de refuerzo temporal Black Friday (+4 auxiliares de bodega y reposición continua).',
        4, None, 'Auxiliares Temporales Logística', 'pending', 320.0
    ),
    (
        1, '2026-11-08', '14:00 - 18:00', 'Accesorios', 'cross_reassign',
        'Reasignar 1 colaborador polifuncional de Virtual Teams a Accesorios durante el peak dominical.',
        1, 3, 'DELGADO LEAL KAREN PAOLA', 'pending', 0.0
    )
]

for rc in recs:
    cur.execute("""
        INSERT INTO workforce_peak_recommendations (
            branch_id, date, time_band, area, type, recommended_action,
            staff_delta, suggested_employee_id, suggested_employee_name, status, impact_cost
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, rc)

print("Peak recommendations seeded.")

conn.commit()
conn.close()
print("All Workforce Peak & Intelligent Staffing data seeded successfully.")
