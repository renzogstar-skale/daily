<?php

namespace Grocy\Services;

/**
 * DAILY — Reglas laborales puras, sin base de datos: ciclo dominical 3×1, derecho a compensatorio y clasificación legal de horas.
 *
 * Decisión sobre ausencias: el ciclo 3×1 corre por calendario desde el último domingo libre. Vacaciones, incapacidad o permiso no cuentan
 * como domingo trabajado ni reinician el ciclo; si el domingo libre cae dentro de la ausencia, se consume allí y el ciclo sigue desde esa
 * fecha. Así nadie supera tres domingos seguidos al regresar y los grupos no se amontonan el mismo domingo.
 */
class WorkforceLaborRulesService
{
	const CYCLE_WEEKS = 4;
	const MAX_SUNDAYS = 3;
	const NIGHT_PREMIUM = 0.35;
	// Ley 2466 de 2025: la noche empieza a las 19:00 desde el 25/12/2025
	const NIGHT_START_CHANGE = '2025-12-25';
	// Ley 2466 de 2025: recargo dominical y festivo escalonado
	const SUNDAY_PREMIUM = [['2027-07-01', 1.0], ['2026-07-01', 0.9], ['2025-07-01', 0.8], ['0000-01-01', 0.75]];
	const OCCASIONAL_MAX_SUNDAYS = 2;

	public static function WeeksBetween(string $from, string $to): int
	{
		return intval(round((strtotime("$to 12:00 UTC") - strtotime("$from 12:00 UTC")) / 604800));
	}

	public static function NewCycle(?string $anchor = null, int $streak = 0): array
	{
		return ['anchor' => $anchor, 'streak' => $streak];
	}

	/** ¿A esta persona le corresponde descansar el domingo $sunday? */
	public static function IsFreeDue(array $cycle, string $sunday): bool
	{
		return $cycle['streak'] >= self::MAX_SUNDAYS || ($cycle['anchor'] !== null && self::WeeksBetween($cycle['anchor'], $sunday) % self::CYCLE_WEEKS === 0);
	}

	/** Avanza el ciclo un domingo. $state: WORKED, FREE o ABSENT */
	public static function Step(array $cycle, string $sunday, string $state): array
	{
		if ($state === 'FREE' || ($state === 'ABSENT' && $cycle['anchor'] !== null && self::WeeksBetween($cycle['anchor'], $sunday) % self::CYCLE_WEEKS === 0))
		{
			return ['anchor' => $sunday, 'streak' => 0];
		}
		if ($state === 'WORKED')
		{
			$cycle['streak']++;
		}
		return $cycle;
	}

	public static function NextFreeSunday(array $cycle, string $after): ?string
	{
		if ($cycle['anchor'] === null)
		{
			return null;
		}
		$weeks = self::WeeksBetween($cycle['anchor'], $after);
		$steps = max(1, intdiv($weeks, self::CYCLE_WEEKS) + 1);
		return gmdate('Y-m-d', strtotime("{$cycle['anchor']} 12:00 UTC") + $steps * self::CYCLE_WEEKS * 604800);
	}

	/** Art. 179 CST: hasta dos domingos en el mes calendario es ocasional; tres o más, habitual */
	public static function SundayEntitlement(int $workedInMonth): string
	{
		return $workedInMonth <= self::OCCASIONAL_MAX_SUNDAYS ? 'OCASIONAL' : 'HABITUAL';
	}

	/** Viernes de la semana siguiente al domingo: último día hábil de la política lunes a viernes */
	public static function CompensatoryDueDate(string $sunday): string
	{
		return gmdate('Y-m-d', strtotime("$sunday 12:00 UTC") + 5 * 86400);
	}

	public static function SundayPremium(string $date): float
	{
		foreach (self::SUNDAY_PREMIUM as [$from, $pct])
		{
			if ($date >= $from)
			{
				return $pct;
			}
		}
		return 0.75;
	}

	public static function NightStartHour(string $date): int
	{
		return $date >= self::NIGHT_START_CHANGE ? 19 : 21;
	}

	/**
	 * Reparte un turno en horas ordinarias, nocturnas y dominicales o festivas, minuto a minuto y por fecha calendario: la parte posterior a la
	 * medianoche pertenece al día siguiente. El almuerzo no paga: como la planilla no dice a qué hora se toma, se descuenta en proporción.
	 * $isRestDay recibe una fecha y dice si es domingo o festivo.
	 */
	public static function ClassifyShift(string $date, string $start, string $end, float $paidHours, callable $isRestDay): array
	{
		$out = ['day' => 0.0, 'night' => 0.0, 'rest_day' => 0.0, 'rest_night' => 0.0, 'premium_hours' => 0.0];
		$from = intval(substr($start, 0, 2)) * 60 + intval(substr($start, 3, 2));
		$to = intval(substr($end, 0, 2)) * 60 + intval(substr($end, 3, 2));
		if ($to <= $from)
		{
			$to += 1440;
		}
		$span = $to - $from;
		if ($span <= 0 || $paidHours <= 0 || $span === 1440)
		{
			return $out;
		}
		$next = gmdate('Y-m-d', strtotime("$date 12:00 UTC") + 86400);
		$scale = $paidHours / ($span / 60);
		for ($m = $from; $m < $to; $m++)
		{
			$d = $m >= 1440 ? $next : $date;
			$minute = $m % 1440;
			$night = $minute < 360 || $minute >= self::NightStartHour($d) * 60;
			$rest = $isRestDay($d);
			$out[$rest ? ($night ? 'rest_night' : 'rest_day') : ($night ? 'night' : 'day')] += 1 / 60;
			$out['premium_hours'] += (($night ? self::NIGHT_PREMIUM : 0) + ($rest ? self::SundayPremium($d) : 0)) / 60;
		}
		foreach ($out as $k => $v)
		{
			$out[$k] = round($v * $scale, 2);
		}
		return $out;
	}

	/* =========================================================================
	 * MOTOR MULTIJURISDICCIÓN (CO, MX, PE, CL, ES, US-FED, US-CA)
	 * ========================================================================= */

	public const JURISDICTION_DEFAULTS = [
		'CO-GEN' => [
			'country_code' => 'CO',
			'jurisdiction_code' => 'CO-GEN',
			'country_name' => 'Colombia',
			'jurisdiction_name' => 'Nacional (CST / Ley 2101 / Ley 2466)',
			'legal_reference' => 'CST Art. 160-179, Ley 2101/2021, Ley 2466/2025',
			'max_weekly_hours' => 42.0,
			'max_daily_hours' => 10.0,
			'min_rest_hours' => 12.0,
			'max_daily_overtime' => 2.0,
			'max_weekly_overtime' => 12.0,
			'night_start_hour' => 19,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.35,
			'sunday_holiday_premium_pct' => 0.90,
			'overtime_day_pct' => 0.25,
			'overtime_night_pct' => 0.75,
			'sunday_rule_type' => '3x1',
			'annual_vacation_days' => 15.0,
			'compensatory_expire_days' => 14,
			'currency_code' => 'COP'
		],
		'MX-LFT' => [
			'country_code' => 'MX',
			'jurisdiction_code' => 'MX-LFT',
			'country_name' => 'México',
			'jurisdiction_name' => 'Federal (Ley Federal del Trabajo)',
			'legal_reference' => 'LFT Art. 58-73',
			'max_weekly_hours' => 48.0,
			'max_daily_hours' => 11.0,
			'min_rest_hours' => 12.0,
			'max_daily_overtime' => 3.0,
			'max_weekly_overtime' => 9.0,
			'night_start_hour' => 20,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.00,
			'sunday_holiday_premium_pct' => 0.25,
			'overtime_day_pct' => 1.00,
			'overtime_night_pct' => 1.00,
			'sunday_rule_type' => '1x1',
			'annual_vacation_days' => 12.0,
			'compensatory_expire_days' => 30,
			'currency_code' => 'MXN'
		],
		'PE-DLEG728' => [
			'country_code' => 'PE',
			'jurisdiction_code' => 'PE-DLEG728',
			'country_name' => 'Perú',
			'jurisdiction_name' => 'Régimen General (D.Leg. 728)',
			'legal_reference' => 'Constitución Art. 25, D.Leg. 728',
			'max_weekly_hours' => 48.0,
			'max_daily_hours' => 10.0,
			'min_rest_hours' => 12.0,
			'max_daily_overtime' => 4.0,
			'max_weekly_overtime' => 16.0,
			'night_start_hour' => 22,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.35,
			'sunday_holiday_premium_pct' => 1.00,
			'overtime_day_pct' => 0.25,
			'overtime_night_pct' => 0.35,
			'sunday_rule_type' => '2x1',
			'annual_vacation_days' => 30.0,
			'compensatory_expire_days' => 30,
			'currency_code' => 'PEN'
		],
		'CL-DT' => [
			'country_code' => 'CL',
			'jurisdiction_code' => 'CL-DT',
			'country_name' => 'Chile',
			'jurisdiction_name' => 'Comercio y Retail (Ley 21.561)',
			'legal_reference' => 'Código del Trabajo Chile & Ley 21.561',
			'max_weekly_hours' => 40.0,
			'max_daily_hours' => 10.0,
			'min_rest_hours' => 12.0,
			'max_daily_overtime' => 2.0,
			'max_weekly_overtime' => 12.0,
			'night_start_hour' => 21,
			'night_end_hour' => 7,
			'night_premium_pct' => 0.30,
			'sunday_holiday_premium_pct' => 0.30,
			'overtime_day_pct' => 0.50,
			'overtime_night_pct' => 0.50,
			'sunday_rule_type' => '2x1',
			'annual_vacation_days' => 15.0,
			'compensatory_expire_days' => 15,
			'currency_code' => 'CLP'
		],
		'ES-ET' => [
			'country_code' => 'ES',
			'jurisdiction_code' => 'ES-ET',
			'country_name' => 'España',
			'jurisdiction_name' => 'Estatuto de los Trabajadores',
			'legal_reference' => 'ET Art. 34-37',
			'max_weekly_hours' => 40.0,
			'max_daily_hours' => 9.0,
			'min_rest_hours' => 12.0,
			'max_daily_overtime' => 2.0,
			'max_weekly_overtime' => 8.0,
			'night_start_hour' => 22,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.25,
			'sunday_holiday_premium_pct' => 0.75,
			'overtime_day_pct' => 0.25,
			'overtime_night_pct' => 0.50,
			'sunday_rule_type' => '2x1',
			'annual_vacation_days' => 22.0,
			'compensatory_expire_days' => 120,
			'currency_code' => 'EUR'
		],
		'US-FED' => [
			'country_code' => 'US',
			'jurisdiction_code' => 'US-FED',
			'country_name' => 'Estados Unidos',
			'jurisdiction_name' => 'Federal (FLSA)',
			'legal_reference' => 'Fair Labor Standards Act',
			'max_weekly_hours' => 40.0,
			'max_daily_hours' => 12.0,
			'min_rest_hours' => 8.0,
			'max_daily_overtime' => 4.0,
			'max_weekly_overtime' => 20.0,
			'night_start_hour' => 22,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.00,
			'sunday_holiday_premium_pct' => 0.00,
			'overtime_day_pct' => 0.50,
			'overtime_night_pct' => 0.50,
			'sunday_rule_type' => '1x1',
			'annual_vacation_days' => 10.0,
			'compensatory_expire_days' => 30,
			'currency_code' => 'USD'
		],
		'US-CA' => [
			'country_code' => 'US',
			'jurisdiction_code' => 'US-CA',
			'country_name' => 'Estados Unidos',
			'jurisdiction_name' => 'California (CA Labor Code)',
			'legal_reference' => 'CA Labor Code Sec. 510',
			'max_weekly_hours' => 40.0,
			'max_daily_hours' => 12.0,
			'min_rest_hours' => 10.0,
			'max_daily_overtime' => 4.0,
			'max_weekly_overtime' => 20.0,
			'night_start_hour' => 22,
			'night_end_hour' => 6,
			'night_premium_pct' => 0.10,
			'sunday_holiday_premium_pct' => 0.50,
			'overtime_day_pct' => 0.50,
			'overtime_night_pct' => 1.00,
			'sunday_rule_type' => '1x1',
			'annual_vacation_days' => 10.0,
			'compensatory_expire_days' => 30,
			'currency_code' => 'USD'
		]
	];

	public static function GetJurisdictionProfiles(): array
	{
		return array_values(self::JURISDICTION_DEFAULTS);
	}

	/**
	 * Resuelve el perfil laboral vigente para un país, jurisdicción y fecha calendario.
	 * Aplica automáticamente transiciones legales graduales (ej. Colombia Ley 2101 y Ley 2466).
	 */
	public static function ResolveJurisdictionProfile(string $countryCode = 'CO', ?string $jurisdictionCode = null, ?string $date = null): array
	{
		$cc = strtoupper(trim($countryCode ?: 'CO'));
		$jc = $jurisdictionCode ? strtoupper(trim($jurisdictionCode)) : '';
		$d = $date ? substr($date, 0, 10) : date('Y-m-d');

		if ($jc !== '' && isset(self::JURISDICTION_DEFAULTS[$jc]))
		{
			$p = self::JURISDICTION_DEFAULTS[$jc];
		}
		else
		{
			$p = self::JURISDICTION_DEFAULTS['CO-GEN'];
			foreach (self::JURISDICTION_DEFAULTS as $cand)
			{
				if ($cand['country_code'] === $cc)
				{
					$p = $cand;
					break;
				}
			}
		}

		// Transiciones estatutarias graduales de Colombia
		if ($p['country_code'] === 'CO')
		{
			if ($d < '2024-07-15') $p['max_weekly_hours'] = 47.0;
			elseif ($d < '2025-07-15') $p['max_weekly_hours'] = 46.0;
			elseif ($d < '2026-07-15') $p['max_weekly_hours'] = 44.0;
			else $p['max_weekly_hours'] = 42.0;

			$p['night_start_hour'] = self::NightStartHour($d);
			$p['sunday_holiday_premium_pct'] = self::SundayPremium($d);
		}
		// Transición gradual Chile Ley 21.561 (44h -> 42h desde 2026-04-26 -> 40h desde 2028-04-26)
		elseif ($p['country_code'] === 'CL')
		{
			if ($d < '2026-04-26') $p['max_weekly_hours'] = 44.0;
			elseif ($d < '2028-04-26') $p['max_weekly_hours'] = 42.0;
			else $p['max_weekly_hours'] = 40.0;
		}

		return $p;
	}

	/**
	 * Clasifica un turno considerando reglas del país/jurisdicción seleccionada.
	 */
	public static function ClassifyShiftByJurisdiction(string $date, string $start, string $end, float $paidHours, callable $isRestDay, array $profile): array
	{
		$nightStartMin = intval($profile['night_start_hour'] ?? 19) * 60;
		$nightEndMin = intval($profile['night_end_hour'] ?? 6) * 60;
		$nightPremium = floatval($profile['night_premium_pct'] ?? 0.35);
		$restPremium = floatval($profile['sunday_holiday_premium_pct'] ?? 0.90);

		$out = ['day' => 0.0, 'night' => 0.0, 'rest_day' => 0.0, 'rest_night' => 0.0, 'premium_hours' => 0.0];
		$from = intval(substr($start, 0, 2)) * 60 + intval(substr($start, 3, 2));
		$to = intval(substr($end, 0, 2)) * 60 + intval(substr($end, 3, 2));
		if ($to <= $from)
		{
			$to += 1440;
		}
		$span = $to - $from;
		if ($span <= 0 || $paidHours <= 0 || $span === 1440)
		{
			return $out;
		}
		$next = gmdate('Y-m-d', strtotime("$date 12:00 UTC") + 86400);
		$scale = $paidHours / ($span / 60);
		for ($m = $from; $m < $to; $m++)
		{
			$d = $m >= 1440 ? $next : $date;
			$minute = $m % 1440;
			$night = ($nightStartMin > $nightEndMin)
				? ($minute >= $nightStartMin || $minute < $nightEndMin)
				: ($minute >= $nightStartMin && $minute < $nightEndMin);
			$rest = $isRestDay($d);
			$out[$rest ? ($night ? 'rest_night' : 'rest_day') : ($night ? 'night' : 'day')] += 1 / 60;
			$pctRest = ($profile['country_code'] ?? 'CO') === 'CO' ? self::SundayPremium($d) : $restPremium;
			$out['premium_hours'] += (($night ? $nightPremium : 0) + ($rest ? $pctRest : 0)) / 60;
		}
		foreach ($out as $k => $v)
		{
			$out[$k] = round($v * $scale, 2);
		}
		return $out;
	}

	/**
	 * Calcula horas regulares y escalones de horas extras por jurisdicción, incluyendo alertas por tope legal.
	 */
	public static function ComputeOvertimeByJurisdiction(float $workedHours, float $contractWeeklyHours, array $profile, float $dailyHours = 0.0): array
	{
		$cc = $profile['country_code'] ?? 'CO';
		$jc = $profile['jurisdiction_code'] ?? 'CO-GEN';
		$weeklyCap = min($contractWeeklyHours > 0 ? $contractWeeklyHours : floatval($profile['max_weekly_hours'] ?? 42), floatval($profile['max_weekly_hours'] ?? 42));
		$maxWeeklyOt = floatval($profile['max_weekly_overtime'] ?? 12.0);
		$maxDailyOt = floatval($profile['max_daily_overtime'] ?? 2.0);

		$regular = min($workedHours, $weeklyCap);
		$rawOt = max(0.0, $workedHours - $weeklyCap);

		$tier1 = 0.0;
		$tier2 = 0.0;
		$tier1Pct = floatval($profile['overtime_day_pct'] ?? 0.25);
		$tier2Pct = floatval($profile['overtime_night_pct'] ?? 0.75);

		if ($cc === 'MX')
		{
			// LFT México: primeras 9 h extras semanales al doble (+100%), excedente al triple (+200%)
			$tier1 = min($rawOt, 9.0);
			$tier2 = max(0.0, $rawOt - 9.0);
			$tier1Pct = 1.00;
			$tier2Pct = 2.00;
		}
		elseif ($jc === 'US-CA')
		{
			// California: >8h hasta 12h es 1.5x (+0.50), >12h es 2.0x (+1.00)
			if ($dailyHours > 12.0)
			{
				$tier2 = max(0.0, $dailyHours - 12.0);
				$tier1 = max(0.0, $rawOt - $tier2);
			}
			else
			{
				$tier1 = $rawOt;
			}
			$tier1Pct = 0.50;
			$tier2Pct = 1.00;
		}
		else
		{
			$tier1 = $rawOt;
		}

		return [
			'jurisdiction_code' => $jc,
			'regular_hours' => round($regular, 2),
			'overtime_total_hours' => round($rawOt, 2),
			'overtime_tier1_hours' => round($tier1, 2),
			'overtime_tier1_pct' => $tier1Pct,
			'overtime_tier2_hours' => round($tier2, 2),
			'overtime_tier2_pct' => $tier2Pct,
			'exceeds_weekly_ot_cap' => $rawOt > $maxWeeklyOt,
			'exceeds_daily_ot_cap' => ($dailyHours > 0 && max(0.0, $dailyHours - 8.0) > $maxDailyOt)
		];
	}

	/**
	 * Clasifica la banda operativa de un turno: apertura | intermedio | cierre | nocturno
	 */
	public static function DetectShiftBand(string $start, string $end, array $profile = []): string
	{
		$nightStart = intval($profile['night_start_hour'] ?? 21);
		$sHour = intval(substr($start, 0, 2));
		if ($end <= $start || $sHour >= max(20, $nightStart) || $sHour < 4)
		{
			return 'nocturno';
		}
		if ($start < '09:30')
		{
			return 'apertura';
		}
		if ($end >= '20:00' || $start >= '13:30')
		{
			return 'cierre';
		}
		return 'intermedio';
	}

	/**
	 * Evalúa la regla de compensatorio vs retribución económica (Art. 179/180 CST y equivalentes).
	 */
	public static function EvaluateCompensatoryRule(string $sundayDate, int $sundaysWorkedInMonth, bool $choosePayout, float $hourlyCost, array $profile): array
	{
		$cc = $profile['country_code'] ?? 'CO';
		$entitlement = self::SundayEntitlement($sundaysWorkedInMonth);
		$premium = ($cc === 'CO') ? self::SundayPremium($sundayDate) : floatval($profile['sunday_holiday_premium_pct'] ?? 0.50);
		$expDays = intval($profile['compensatory_expire_days'] ?? 14);
		$dueDate = ($cc === 'CO') ? self::CompensatoryDueDate($sundayDate) : gmdate('Y-m-d', strtotime("$sundayDate 12:00 UTC") + $expDays * 86400);

		// En Colombia (Art. 180 CST), dominical ocasional permite elegir dinero o compensatorio; habitual exige compensatorio + recargo
		$canPayInstead = ($entitlement === 'OCASIONAL');
		$action = ($canPayInstead && $choosePayout) ? 'PAGO_RECARGO' : 'COMPENSATORIO';
		$payoutAmount = round(8.0 * $hourlyCost * (1.0 + $premium), 2);

		return [
			'origin_sunday' => $sundayDate,
			'entitlement' => $entitlement,
			'action' => $action,
			'requires_compensatory_day' => $action === 'COMPENSATORIO',
			'sunday_premium_pct' => $premium,
			'payout_amount' => $action === 'PAGO_RECARGO' ? $payoutAmount : round(8.0 * $hourlyCost * $premium, 2),
			'due_date' => $dueDate
		];
	}
}
