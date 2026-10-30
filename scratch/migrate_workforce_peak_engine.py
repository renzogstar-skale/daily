import sqlite3
import json
import datetime
import math

conn = sqlite3.connect('data/grocy_es.db')
cur = conn.cursor()

print("Creating Workforce Peak & Intelligent Staffing tables...")

# 1. workforce_hourly_demand
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_hourly_demand (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    date DATE NOT NULL,
    hour INTEGER NOT NULL,
    minute_interval INTEGER DEFAULT 60,
    sales_amount REAL DEFAULT 0,
    transactions_count INTEGER DEFAULT 0,
    units_sold REAL DEFAULT 0,
    foot_traffic INTEGER DEFAULT 0,
    avg_ticket REAL DEFAULT 0,
    avg_service_time_seconds REAL DEFAULT 180,
    replenishment_units INTEGER DEFAULT 0,
    pending_dispatches INTEGER DEFAULT 0,
    demand_level TEXT DEFAULT 'normal',
    workload_minutes REAL DEFAULT 0,
    staff_required INTEGER DEFAULT 1,
    staff_scheduled INTEGER DEFAULT 1,
    coverage_gap INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(branch_id, date, hour, minute_interval)
)
""")

# 2. workforce_area_productivity_standards
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_area_productivity_standards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    area_name TEXT NOT NULL,
    target_splh REAL DEFAULT 140.0,
    target_uph REAL DEFAULT 40.0,
    service_time_min_per_ticket REAL DEFAULT 3.0,
    restock_min_per_unit REAL DEFAULT 1.2,
    dispatch_min_per_order REAL DEFAULT 8.0,
    min_staff_open INTEGER DEFAULT 1,
    min_staff_peak INTEGER DEFAULT 2,
    shrinkage_factor_pct REAL DEFAULT 15.0,
    hourly_cost_avg REAL DEFAULT 12.5,
    sales_share_pct REAL DEFAULT 10.0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(branch_id, area_name)
)
""")

# 3. workforce_commercial_events
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_commercial_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    event_name TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    event_type TEXT NOT NULL,
    sales_uplift_pct REAL DEFAULT 0,
    traffic_uplift_pct REAL DEFAULT 0,
    extended_hours TEXT DEFAULT '07:00-22:00',
    peak_hours_json TEXT,
    reinforcement_staff_needed INTEGER DEFAULT 0,
    normal_sales_base REAL DEFAULT 40000,
    projected_sales REAL DEFAULT 72000,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
""")

# 4. workforce_peak_staffing_simulations
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_peak_staffing_simulations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    simulation_name TEXT NOT NULL,
    target_daily_sales REAL NOT NULL,
    staff_required_total INTEGER NOT NULL,
    staff_by_area_json TEXT NOT NULL,
    hourly_distribution_json TEXT NOT NULL,
    projected_labor_cost REAL NOT NULL,
    labor_pct_of_sales REAL NOT NULL,
    splh_projected REAL NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
""")

# 5. workforce_demand_forecasts
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_demand_forecasts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    forecast_date DATE NOT NULL,
    horizon TEXT NOT NULL,
    scenario_conservative_sales REAL NOT NULL,
    scenario_base_sales REAL NOT NULL,
    scenario_high_demand_sales REAL NOT NULL,
    conservative_staff INTEGER NOT NULL,
    base_staff INTEGER NOT NULL,
    high_demand_staff INTEGER NOT NULL,
    uncertainty_range_pct REAL DEFAULT 10.0,
    recommended_shifts_json TEXT,
    version INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(branch_id, forecast_date, horizon)
)
""")

# 6. workforce_peak_recommendations
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_peak_recommendations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    branch_id INTEGER NOT NULL,
    date DATE NOT NULL,
    time_band TEXT NOT NULL,
    area TEXT NOT NULL,
    type TEXT NOT NULL,
    recommended_action TEXT NOT NULL,
    staff_delta INTEGER NOT NULL,
    suggested_employee_id INTEGER,
    suggested_employee_name TEXT,
    status TEXT DEFAULT 'pending',
    impact_cost REAL DEFAULT 0,
    decided_by_user_id INTEGER,
    decided_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
""")

conn.commit()
print("Tables created successfully.")
conn.close()
