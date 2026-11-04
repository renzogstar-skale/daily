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
}
