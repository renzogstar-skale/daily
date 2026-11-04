<?php

namespace Grocy\Services;

/**
 * Exporta la programación del mes a .xlsx con la estructura de la planilla original: columnas por día en semanas de lunes a domingo,
 * total de horas (TH) por semana con fórmula sobre la hoja Turnos SAP, contadores por tipo, colores y celdas combinadas.
 * Se escribe el OOXML directamente, como el resto de exportaciones de DAILY; cada fórmula lleva su valor calculado.
 */
class WorkforceScheduleXlsxService extends BaseService
{
	const MONTHS = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
	const WEEKDAYS = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
	const OFF_LABEL = ['LIBRE' => 'LBRE'];

	// Índice de cada estilo en cellXfs
	const S_DEFAULT = 0, S_TITLE = 1, S_HEADER = 2, S_SUNDAY_HEADER = 3, S_OPEN = 4, S_CLOSE = 5, S_NIGHT = 6, S_COMP = 7, S_FREE = 8, S_VAC = 9, S_INC = 10, S_MID = 11, S_TOTAL = 12, S_TEXT = 13, S_NUM = 14, S_RED = 15, S_YELLOW = 16, S_GREEN = 17, S_WRAP = 18;

	private static ?WorkforceScheduleXlsxService $instance = null;

	public static function GetInstance(): WorkforceScheduleXlsxService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function FileName(int $year, int $month): string
	{
		return sprintf('PROGRAMACION_DAILY_%s_%d.xlsx', self::MONTHS[$month], $year);
	}

	/** Devuelve el contenido del archivo. No hay descarga mientras el mes incumpla una restricción obligatoria */
	public function Export(int $branchId, int $year, int $month): string
	{
		$data = WorkforceScheduleEngineService::GetInstance()->ExportData($branchId, $year, $month);
		if (!$data['report']['can_approve'])
		{
			throw new \Exception($data['report']['counts']['legal'] . ' incumplimiento(s) obligatorio(s) sin resolver: corríjalos antes de descargar');
		}
		$sheets = [
			'Programación mensual' => $this->ScheduleSheet($data, $year, $month),
			'Empleados dominical' => $this->SundaySheet($data),
			'Turnos SAP' => $this->SapSheet($data),
			'Trasnocho y Festivos' => $this->NightSheet($data),
			'Resumen de cobertura' => $this->CoverageSheet($data),
			'Alertas y conflictos' => $this->AlertSheet($data),
			'Resumen de horas' => $this->HoursSheet($data),
			'Compensatorios' => $this->CompensatorySheet($data),
			'Historial dominical' => $this->SundayLedgerSheet($data),
		];
		return self::Package($sheets);
	}

	private static function Column(int $i): string
	{
		$s = '';
		for ($i++; $i > 0; $i = intdiv($i - 1, 26))
		{
			$s = chr(65 + ($i - 1) % 26) . $s;
		}
		return $s;
	}

	private static function ShiftStyle(array $r): int
	{
		return match ($r['shift_type'])
		{
			'NOCHE' => self::S_NIGHT,
			'COMPENSATORIO' => self::S_COMP,
			'LIBRE' => self::S_FREE,
			'VACACIONES' => self::S_VAC,
			'INCAPACIDAD', 'PERMISO' => self::S_INC,
			'INTERMEDIO' => self::S_MID,
			default => $r['start_time'] < '12:00' ? self::S_OPEN : self::S_CLOSE,
		};
	}

	private static function CodeOf(array $r): string
	{
		return self::OFF_LABEL[$r['shift_code']] ?? $r['shift_code'];
	}

	private static function Grid(array $rows): array
	{
		$out = [];
		foreach ($rows as $r)
		{
			$out[$r['payroll_code']][$r['shift_date']] = $r;
		}
		return $out;
	}

	private static function Days(string $start, string $end): array
	{
		$out = [];
		for ($d = $start; $d <= $end; $d = gmdate('Y-m-d', strtotime("$d 12:00 UTC") + 86400))
		{
			$out[] = $d;
		}
		return $out;
	}

	private function ScheduleSheet(array $data, int $year, int $month): array
	{
		$grid = self::Grid($data['rows']);
		$days = self::Days($data['start'], $data['end']);
		$sapCount = count($data['sap']) + 2;
		// Columnas: código, nombre, cargo, días (con TH tras cada domingo y al cerrar el mes), contadores y promedio
		$cols = [];
		foreach ($days as $i => $d)
		{
			$cols[] = ['day' => $d];
			if (date('w', strtotime($d)) === '0' || $i === count($days) - 1)
			{
				$cols[] = ['th' => true];
			}
		}
		$first = 3;
		$counters = [['A', '7A*'], ['i', '7i*'], ['C', '7C*'], ['N', '7N*'], ['COMP', 'COMP'], ['LBRE', 'LBRE'], ['VC', 'VC'], ['INC', 'INC']];
		$rows = [];
		$merges = [];
		$status = $data['draft_id'] ? 'BORRADOR — pendiente de aprobación' : 'OFICIAL';
		$rows[2] = [0 => ['v' => 'PROGRAMACIÓN', 's' => self::S_TITLE]];
		$merges[] = 'A2:C2';
		$rows[3] = [0 => ['v' => 'Año', 's' => self::S_HEADER], 1 => ['v' => 'Mes', 's' => self::S_HEADER], 2 => ['v' => $status, 's' => self::S_HEADER]];
		$rows[4] = [0 => ['v' => $year, 's' => self::S_TEXT], 1 => ['v' => $month, 's' => self::S_TEXT], 2 => ['v' => self::MONTHS[$month], 's' => self::S_TEXT]];
		$rows[6] = [0 => ['v' => 'TODOS', 's' => self::S_HEADER]];
		$rows[7] = [0 => ['v' => 'CODIGO', 's' => self::S_HEADER], 1 => ['v' => 'NOMBRE', 's' => self::S_HEADER], 2 => ['v' => 'CARGO', 's' => self::S_HEADER]];
		$weekStart = $first;
		$week = 1;
		foreach ($cols as $i => $col)
		{
			$c = $first + $i;
			if (isset($col['th']))
			{
				$rows[7][$c] = ['v' => 'TH', 's' => self::S_HEADER];
				$rows[6][$c] = ['v' => '', 's' => self::S_HEADER];
				$rows[5][$weekStart] = ['v' => "Semana $week", 's' => self::S_HEADER];
				for ($x = $weekStart + 1; $x <= $c; $x++)
				{
					$rows[5][$x] = ['v' => '', 's' => self::S_HEADER];
				}
				if ($c > $weekStart)
				{
					$merges[] = self::Column($weekStart) . '5:' . self::Column($c) . '5';
				}
				$weekStart = $c + 1;
				$week++;
				continue;
			}
			$sunday = date('w', strtotime($col['day'])) === '0';
			$style = ($sunday || isset(array_flip(array_column($data['holidays'], 'holiday_date'))[$col['day']])) ? self::S_SUNDAY_HEADER : self::S_HEADER;
			$rows[6][$c] = ['v' => self::WEEKDAYS[intval(date('w', strtotime($col['day'])))], 's' => $style];
			$rows[7][$c] = ['v' => intval(substr($col['day'], 8)), 's' => $style];
		}
		$tail = $first + count($cols);
		foreach ($counters as $k => [$label])
		{
			$rows[7][$tail + $k] = ['v' => $label, 's' => self::S_HEADER];
		}
		$rows[7][$tail + count($counters)] = ['v' => 'Prom. Total', 's' => self::S_HEADER];

		$r = 8;
		$firstData = $r;
		foreach ($data['collaborators'] as $c)
		{
			$code = $c['payroll_code'];
			$rows[$r] = [0 => ['v' => $code, 's' => self::S_TEXT], 1 => ['v' => $c['name'], 's' => self::S_TEXT], 2 => ['v' => $c['cargo'], 's' => self::S_TEXT]];
			$rowStart = $first;
			$weekCells = 0.0;
			$ths = [];
			$totalHours = 0.0;
			foreach ($cols as $i => $col)
			{
				$x = $first + $i;
				$ref = self::Column($x) . $r;
				if (isset($col['th']))
				{
					$range = self::Column($rowStart) . $r . ':' . self::Column($x - 1) . $r;
					$rows[$r][$x] = ['f' => "SUMPRODUCT(SUMIF('Turnos SAP'!\$A\$3:\$A\$$sapCount,$range,'Turnos SAP'!\$I\$3:\$I\$$sapCount))", 'v' => $weekCells, 's' => self::S_TOTAL];
					$ths[] = $ref;
					$totalHours += $weekCells;
					$rowStart = $x + 1;
					$weekCells = 0.0;
					continue;
				}
				$cell = $grid[$code][$col['day']] ?? null;
				$rows[$r][$x] = $cell ? ['v' => self::CodeOf($cell), 's' => self::ShiftStyle($cell)] : ['v' => '', 's' => self::S_TEXT];
				if ($cell && !in_array($cell['shift_type'], ['LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'], true))
				{
					$weekCells += floatval($cell['hours']);
				}
				elseif ($cell && self::CodeOf($cell) === 'LIC')
				{
					$weekCells += 7.0;
				}
			}
			$range = self::Column($first) . $r . ':' . self::Column($tail - 1) . $r;
			foreach ($counters as $k => [$label, $pattern])
			{
				$rows[$r][$tail + $k] = ['f' => "COUNTIF($range,\"$pattern\")", 'v' => self::CountCells($grid[$code] ?? [], $pattern), 's' => self::S_NUM];
			}
			$weeks = round(count($days) / 7, 2);
			$rows[$r][$tail + count($counters)] = ['f' => '(' . implode('+', $ths) . ")/$weeks", 'v' => round($totalHours / $weeks, 2), 's' => self::S_TOTAL];
			$r++;
		}
		$lastData = $r - 1;
		foreach ([['Apertura', '7A*'], ['Intermedio', '7i*'], ['Cierre', '7C*'], ['Noche', '7N*']] as [$label, $pattern])
		{
			$rows[$r] = [1 => ['v' => $label, 's' => self::S_HEADER]];
			foreach ($cols as $i => $col)
			{
				if (isset($col['day']))
				{
					$x = self::Column($first + $i);
					$n = 0;
					foreach ($grid as $byDay)
					{
						$n += isset($byDay[$col['day']]) ? self::CountCells([$byDay[$col['day']]], $pattern) : 0;
					}
					$rows[$r][$first + $i] = ['f' => "COUNTIF($x$firstData:$x$lastData,\"$pattern\")", 'v' => $n, 's' => self::S_NUM];
				}
			}
			$r++;
		}
		$widths = [0 => 11, 1 => 36, 2 => 24];
		foreach ($cols as $i => $col)
		{
			$widths[$first + $i] = isset($col['th']) ? 5 : 11;
		}
		return ['rows' => $rows, 'merges' => $merges, 'widths' => $widths, 'freeze' => ['x' => 3, 'y' => 7]];
	}

	private static function CountCells(array $cells, string $pattern): int
	{
		$n = 0;
		foreach ($cells as $cell)
		{
			$code = self::CodeOf($cell);
			$n += fnmatch($pattern, $code, FNM_CASEFOLD) && (!str_contains($pattern, '*') || str_starts_with($code, substr($pattern, 0, 2))) ? 1 : 0;
		}
		return $n;
	}

	private function Table(array $title, array $header, array $body, array $widths): array
	{
		$rows = [1 => [0 => ['v' => $title[0], 's' => self::S_TITLE]]];
		foreach ($header as $i => $h)
		{
			$rows[3][$i] = ['v' => $h, 's' => self::S_HEADER];
		}
		$r = 4;
		foreach ($body as $line)
		{
			foreach (array_values($line) as $i => $v)
			{
				$rows[$r][$i] = is_array($v) ? $v : ['v' => $v, 's' => is_numeric($v) && !is_string($v) ? self::S_NUM : self::S_TEXT];
			}
			$r++;
		}
		return ['rows' => $rows, 'merges' => [], 'widths' => $widths, 'freeze' => ['x' => 0, 'y' => 3]];
	}

	private function CompensatorySheet(array $data): array
	{
		$body = [];
		foreach ($data['control']['compensatories'] as $c)
		{
			foreach ($c['items'] as $i)
			{
				$body[] = [$c['payroll_code'], $c['name'], $c['department'], $i['origin_sunday'] ?? 'Sin origen en planilla', $i['date'] ?? '', $i['status'], $i['due_date'] ?? '', $i['previous_date'] ?? '', $i['entitlement'] ?? ''];
			}
		}
		return $this->Table(['Compensatorios: programados, disfrutados, reprogramados y pendientes'], ['Cód. Nómina', 'Colaborador', 'Área', 'Domingo de origen', 'Fecha', 'Estado', 'Fecha límite', 'Fecha anterior', 'Domingo'], $body, [0 => 11, 1 => 36, 2 => 22, 3 => 15, 4 => 12, 5 => 15, 6 => 12, 7 => 12, 8 => 12]);
	}

	private function SundayLedgerSheet(array $data): array
	{
		$body = [];
		foreach ($data['control']['sundays'] as $s)
		{
			foreach ($s['ledger'] as $l)
			{
				$body[] = [$s['payroll_code'], $s['name'], $s['department'], $l['date'], $l['status'], $l['holiday'] ? 'Sí' : 'No', $l['streak'], $l['notes'] ?? ''];
			}
		}
		return $this->Table(['Historial dominical por persona: trabajados, libres, festivos, vacaciones e incapacidades'], ['Cód. Nómina', 'Colaborador', 'Área', 'Domingo', 'Estado', 'Festivo', 'Seguidos', 'Observación'], $body, [0 => 11, 1 => 36, 2 => 22, 3 => 12, 4 => 15, 5 => 8, 6 => 9, 7 => 44]);
	}

	private function SundaySheet(array $data): array
	{
		$grid = self::Grid($data['rows']);
		$sundays = array_values(array_filter(self::Days($data['start'], $data['end']), fn($d) => date('w', strtotime($d)) === '0'));
		$header = array_merge(['Cód. Nómina', 'Nombres de los empleados', 'Actividad/Cargo', 'Grupo hoja', 'Último domingo libre', 'Últimos domingos trabajados'], array_map(fn($d) => substr($d, 8) . '/' . substr($d, 5, 2), $sundays), ['Libres del mes']);
		$body = [];
		foreach ($data['collaborators'] as $c)
		{
			$h = $data['history'][$c['payroll_code']];
			$line = [$c['payroll_code'], $c['name'], $c['cargo'], $c['sunday_group'], $h['last_free_sunday'], implode(', ', $h['last_worked_sundays'])];
			$free = 0;
			foreach ($sundays as $d)
			{
				$cell = $grid[$c['payroll_code']][$d] ?? null;
				$free += $cell && $cell['shift_type'] === 'LIBRE' ? 1 : 0;
				$line[] = $cell ? ['v' => self::CodeOf($cell), 's' => self::ShiftStyle($cell)] : '';
			}
			$line[] = $free;
			$body[] = $line;
		}
		return $this->Table(['Empleados dominical — ciclo 3×1 continuo desde el último domingo libre real'], $header, $body, [0 => 11, 1 => 36, 2 => 24, 3 => 7, 4 => 13, 5 => 34]);
	}

	private function SapSheet(array $data): array
	{
		$rows = [1 => [0 => ['v' => 'Turnos SAP', 's' => self::S_TITLE]]];
		foreach (['Código PHTD', 'Hora Inicial', 'Hora Final', 'Pausa Saludable', 'Clasificación', 'Tiempo Almuerzo', '', '', 'Horas Trabajadas'] as $i => $h)
		{
			$rows[2][$i] = ['v' => $h, 's' => self::S_HEADER];
		}
		$r = 3;
		foreach ($data['sap'] as $s)
		{
			$rows[$r++] = [0 => ['v' => $s['code'], 's' => self::S_TEXT], 1 => ['v' => $s['start_time'], 's' => self::S_TEXT], 2 => ['v' => $s['end_time'], 's' => self::S_TEXT], 3 => ['v' => (string) $s['description'], 's' => self::S_TEXT],
				4 => ['v' => $s['classification'], 's' => self::S_TEXT], 5 => ['v' => floatval($s['lunch_hours']), 's' => self::S_NUM], 8 => ['v' => round(floatval($s['worked_hours']), 2), 's' => self::S_NUM]];
		}
		return ['rows' => $rows, 'merges' => [], 'widths' => [0 => 14, 1 => 10, 2 => 10, 3 => 34, 4 => 16], 'freeze' => ['x' => 0, 'y' => 2]];
	}

	private function NightSheet(array $data): array
	{
		$names = array_column($data['collaborators'], 'name', 'payroll_code');
		$blocks = [];
		foreach ($data['rows'] as $r)
		{
			if ($r['shift_type'] === 'NOCHE')
			{
				$blocks[$r['shift_date']] = $r['payroll_code'];
			}
		}
		ksort($blocks);
		$body = [];
		$last = null;
		$k = -1;
		foreach ($blocks as $d => $code)
		{
			if ($code !== $last)
			{
				$k++;
				$body[$k] = [$names[$code] ?? $code, $d, $d, 1];
				$last = $code;
			}
			$body[$k][2] = $d;
			$body[$k][3]++;
		}
		foreach ($body as &$b)
		{
			$b[3]--;
		}
		unset($b);
		foreach ($data['holidays'] as $h)
		{
			if ($h['holiday_date'] >= $data['start'] && $h['holiday_date'] <= $data['end'])
			{
				$working = array_filter($data['rows'], fn($r) => $r['shift_date'] === $h['holiday_date'] && !in_array($r['shift_type'], ['LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'], true));
				$body[] = ['FESTIVO: ' . $h['name'], $h['holiday_date'], $h['holiday_date'], count($working)];
			}
		}
		return $this->Table(['Trasnocho y Festivos — bloques nocturnos del mes y personal en festivo'], ['Persona / Festivo', 'Desde', 'Hasta', 'Días o personas'], $body, [0 => 44, 1 => 12, 2 => 12, 3 => 14]);
	}

	private function CoverageSheet(array $data): array
	{
		$body = [];
		foreach ($data['report']['coverage']['days'] as $d => $areas)
		{
			foreach ($areas as $dept => $c)
			{
				$style = ['red' => self::S_RED, 'yellow' => self::S_YELLOW, 'green' => self::S_GREEN][$c['status']];
				$body[] = [$d, self::WEEKDAYS[intval(date('w', strtotime($d)))], $dept, $c['open'], $c['close'], $c['night'], $c['peak'], ['v' => ['red' => 'Sin cubrir', 'yellow' => 'Bajo hora pico', 'green' => 'Cubierto'][$c['status']], 's' => $style]];
			}
		}
		return $this->Table(['Resumen de cobertura'], ['Fecha', 'Día', 'Área', 'Apertura', 'Cierre', 'Noche', 'Hora pico', 'Estado'], $body, [0 => 12, 1 => 6, 2 => 22, 7 => 16]);
	}

	private function AlertSheet(array $data): array
	{
		$body = [];
		foreach ($data['confirmations'] as $c)
		{
			$body[] = [['v' => 'Por confirmar', 's' => self::S_YELLOW], '', '', ['v' => $c['detail'], 's' => self::S_WRAP]];
		}
		foreach ($data['report']['checks'] as $c)
		{
			$body[] = [['v' => $c['bad'] ? 'Revisar' : 'Validado', 's' => $c['bad'] ? self::S_YELLOW : self::S_GREEN], '', '', ['v' => $c['label'] . ($c['bad'] ? ": {$c['bad']} caso(s)" : ''), 's' => self::S_WRAP]];
		}
		$severity = ['legal' => 'Obligatorio', 'coverage' => 'Cobertura', 'warn' => 'Advertencia'];
		foreach ($data['report']['issues'] as $i)
		{
			$body[] = [['v' => $severity[$i['severity']], 's' => $i['severity'] === 'warn' ? self::S_YELLOW : self::S_RED], $i['date'], (string) $i['name'], ['v' => $i['detail'], 's' => self::S_WRAP]];
		}
		return $this->Table(['Alertas y conflictos'], ['Tipo', 'Fecha', 'Persona', 'Detalle'], $body, [0 => 14, 1 => 12, 2 => 34, 3 => 90]);
	}

	private function HoursSheet(array $data): array
	{
		$grid = self::Grid($data['rows']);
		$eq = $data['report']['equity']['by_employee'];
		$body = [];
		foreach ($data['collaborators'] as $c)
		{
			$code = $c['payroll_code'];
			$hours = 0.0;
			$nights = 0;
			foreach ($grid[$code] ?? [] as $r)
			{
				if (!in_array($r['shift_type'], ['LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'], true))
				{
					$hours += floatval($r['hours']);
					$nights += $r['shift_type'] === 'NOCHE' ? 1 : 0;
				}
			}
			$body[] = [$code, $c['name'], $c['department'], round($hours, 1), round($hours / (count(self::Days($data['start'], $data['end'])) / 7), 1), $eq[$code]['openings'] ?? 0, $eq[$code]['closings'] ?? 0, $nights, $eq[$code]['equity_pct'] ?? ''];
		}
		return $this->Table(['Resumen de horas'], ['Código', 'Nombre', 'Área', 'Horas del mes', 'Promedio semanal', 'Aperturas', 'Cierres', 'Noches', 'Equidad %'], $body, [0 => 11, 1 => 36, 2 => 22, 3 => 12, 4 => 12]);
	}

	private static function Styles(): string
	{
		$fills = ['FFFFFFFF', 'FF1F3864', 'FFC00000', 'FFC6E0B4', 'FFBDD7EE', 'FF7030A0', 'FFF8CBAD', 'FFD9D9D9', 'FFFFE699', 'FFFF9999', 'FFE2EFDA', 'FFF2F2F2', 'FFFFC7CE', 'FFFFEB9C', 'FFC6EFCE'];
		$fillXml = '<fills count="' . (count($fills) + 2) . '"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
		foreach ($fills as $f)
		{
			$fillXml .= '<fill><patternFill patternType="solid"><fgColor rgb="' . $f . '"/></patternFill></fill>';
		}
		$fillXml .= '</fills>';
		$fonts = '<fonts count="4"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF1F3864"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts>';
		$xf = fn(int $font, int $fill, int $border, string $align = 'center', bool $wrap = false) => '<xf numFmtId="0" fontId="' . $font . '" fillId="' . ($fill + 2) . '" borderId="' . $border . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="' . $align . '" vertical="center"' . ($wrap ? ' wrapText="1"' : '') . '/></xf>';
		$xfs = [
			'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>', $xf(1, 0, 0, 'left'), $xf(2, 1, 1), $xf(2, 2, 1), $xf(0, 3, 1), $xf(0, 4, 1), $xf(2, 5, 1), $xf(0, 6, 1), $xf(0, 7, 1), $xf(0, 8, 1), $xf(0, 9, 1), $xf(0, 10, 1),
			$xf(3, 11, 1), $xf(0, 0, 1, 'left'), $xf(0, 0, 1), $xf(0, 12, 1), $xf(0, 13, 1), $xf(0, 14, 1), $xf(0, 0, 1, 'left', true),
		];
		$side = fn($n) => "<$n style=\"thin\"><color rgb=\"FFBFBFBF\"/></$n>";
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $fonts . $fillXml .
			'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $side('left') . $side('right') . $side('top') . $side('bottom') . '<diagonal/></border></borders>' .
			'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	private static function SheetXml(array $sheet): string
	{
		$esc = fn($v) => htmlspecialchars(strval($v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$f = $sheet['freeze'];
		$topLeft = self::Column($f['x']) . ($f['y'] + 1);
		$pane = $f['x'] > 0 ? 'bottomRight' : 'bottomLeft';
		$xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane' . ($f['x'] > 0 ? ' xSplit="' . $f['x'] . '"' : '') . ' ySplit="' . $f['y'] . '" topLeftCell="' . $topLeft . '" activePane="' . $pane . '" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
		if ($sheet['widths'])
		{
			ksort($sheet['widths']);
			$xml .= '<cols>';
			foreach ($sheet['widths'] as $i => $w)
			{
				$xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
			}
			$xml .= '</cols>';
		}
		$xml .= '<sheetData>';
		ksort($sheet['rows']);
		foreach ($sheet['rows'] as $r => $cells)
		{
			ksort($cells);
			$xml .= '<row r="' . $r . '">';
			foreach ($cells as $c => $cell)
			{
				$ref = self::Column($c) . $r;
				$s = $cell['s'] ?? 0;
				$v = $cell['v'] ?? '';
				if (isset($cell['f']))
				{
					$xml .= '<c r="' . $ref . '" s="' . $s . '"><f>' . $esc($cell['f']) . '</f><v>' . $v . '</v></c>';
				}
				elseif (is_int($v) || is_float($v))
				{
					$xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $v . '</v></c>';
				}
				elseif ($v === '' || $v === null)
				{
					$xml .= '<c r="' . $ref . '" s="' . $s . '"/>';
				}
				else
				{
					$xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData>';
		if ($sheet['merges'])
		{
			$xml .= '<mergeCells count="' . count($sheet['merges']) . '">' . implode('', array_map(fn($m) => '<mergeCell ref="' . $m . '"/>', $sheet['merges'])) . '</mergeCells>';
		}
		return $xml . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToHeight="0"/></worksheet>';
	}

	private static function Package(array $sheets): string
	{
		$file = tempnam(sys_get_temp_dir(), 'dsx');
		$zip = new \ZipArchive();
		$zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$esc = fn($v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$wb = $rels = $ct = '';
		$n = 0;
		foreach ($sheets as $name => $sheet)
		{
			$n++;
			$zip->addFromString("xl/worksheets/sheet$n.xml", self::SheetXml($sheet));
			$wb .= '<sheet name="' . $esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		$zip->addFromString('xl/styles.xml', self::Styles());
		$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $ct . '</Types>');
		$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
		$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb . '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>');
		$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
		$zip->close();
		$out = file_get_contents($file);
		@unlink($file);
		return $out;
	}
}
