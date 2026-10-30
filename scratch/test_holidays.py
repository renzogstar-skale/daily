import sqlite3
import datetime

def get_easter_date(year):
    """Anonymous Gregorian algorithm (Meeus/Jones/Butcher) for Easter Sunday."""
    a = year % 19
    b = year // 100
    c = year % 100
    d = b // 4
    e = b % 4
    f = (b + 8) // 25
    g = (b - f + 1) // 3
    h = (19 * a + b - d - g + 15) % 30
    i = c // 4
    k = c % 4
    l = (32 + 2 * e + 2 * i - h - k) % 7
    m = (a + 11 * h + 22 * l) // 451
    month = (h + l - 7 * m + 114) // 31
    day = ((h + l - 7 * m + 114) % 31) + 1
    return datetime.date(year, month, day)

def move_to_next_monday(dt):
    """If not already Monday (weekday() == 0), move to next Monday."""
    if dt.weekday() == 0:
        return dt
    days_to_add = (7 - dt.weekday()) % 7
    return dt + datetime.timedelta(days=days_to_add)

def get_colombian_holidays(year):
    easter = get_easter_date(year)
    holidays = []

    # Fixed (no Emiliani)
    holidays.append((datetime.date(year, 1, 1), "Año Nuevo", "fijo"))
    holidays.append((datetime.date(year, 5, 1), "Día del Trabajo", "fijo"))
    holidays.append((datetime.date(year, 7, 20), "Grito de Independencia", "fijo"))
    holidays.append((datetime.date(year, 8, 7), "Batalla de Boyacá", "fijo"))
    holidays.append((datetime.date(year, 12, 8), "Inmaculada Concepción", "fijo"))
    holidays.append((datetime.date(year, 12, 25), "Navidad", "fijo"))

    # Emiliani (move to following Monday)
    holidays.append((move_to_next_monday(datetime.date(year, 1, 6)), "Reyes Magos", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 3, 19)), "San José", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 6, 29)), "San Pedro y San Pablo", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 8, 15)), "Asunción de la Virgen", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 10, 12)), "Día de la Raza", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 11, 1)), "Todos los Santos", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 11, 11)), "Independencia de Cartagena", "emiliani"))

    # Easter dependent
    holidays.append((easter - datetime.timedelta(days=3), "Jueves Santo", "religioso"))
    holidays.append((easter - datetime.timedelta(days=2), "Viernes Santo", "religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=39)), "Ascensión del Señor", "emiliani_religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=60)), "Corpus Christi", "emiliani_religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=68)), "Sagrado Corazón de Jesús", "emiliani_religioso"))

    return sorted(holidays, key=lambda x: x[0])

# Verify 2026 holidays
hols2026 = get_colombian_holidays(2026)
for h in hols2026:
    print(f"{h[0]} ({h[0].strftime('%a')}): {h[1]} [{h[2]}]")
