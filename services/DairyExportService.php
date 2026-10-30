<?php

namespace Grocy\Services;

/**
 * Exportación tabular común: CSV (UTF-8 con BOM, separador «;» para Excel en español) y Excel (.xls SpreadsheetML 2003,
 * que Excel y LibreOffice abren de forma nativa sin librerías adicionales). El PDF se obtiene de las vistas imprimibles.
 */
class DairyExportService extends BaseService
{
	public function Render(array $columns, array $rows, string $format, string $title = 'DAIRY'): array
	{
		$format = strtolower($format);
		if ($format === 'xls' || $format === 'xlsx' || $format === 'excel')
		{
			return ['body' => $this->Xls($columns, $rows, $title), 'type' => 'application/vnd.ms-excel; charset=utf-8', 'ext' => 'xls'];
		}
		return ['body' => $this->Csv($columns, $rows), 'type' => 'text/csv; charset=utf-8', 'ext' => 'csv'];
	}

	public function Csv(array $columns, array $rows, string $sep = ';'): string
	{
		$fh = fopen('php://temp', 'r+');
		fwrite($fh, "\xEF\xBB\xBF");
		fputcsv($fh, array_values($columns), $sep, '"', '');
		foreach ($rows as $r)
		{
			$r = (array) $r;
			fputcsv($fh, array_map(fn($k) => $this->SafeCell($r[$k] ?? ''), array_keys($columns)), $sep, '"', '');
		}
		rewind($fh);
		$out = stream_get_contents($fh);
		fclose($fh);
		return $out;
	}

	// Evita inyección de fórmulas al abrir en hojas de cálculo (=, +, -, @ al inicio de texto)
	private function SafeCell($v)
	{
		if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"]) && !is_numeric($v))
		{
			return "'" . $v;
		}
		return $v;
	}

	public function Xls(array $columns, array $rows, string $title): string
	{
		$e = fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$x = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>'
			. '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
			. '<Styles><Style ss:ID="h"><Font ss:Bold="1"/><Interior ss:Color="#E8F0FE" ss:Pattern="Solid"/></Style></Styles>'
			. '<Worksheet ss:Name="' . $e(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title), 0, 31)) . '"><Table><Row>';
		foreach ($columns as $label)
		{
			$x .= '<Cell ss:StyleID="h"><Data ss:Type="String">' . $e($label) . '</Data></Cell>';
		}
		$x .= '</Row>';
		foreach ($rows as $r)
		{
			$r = (array) $r;
			$x .= '<Row>';
			foreach (array_keys($columns) as $k)
			{
				$v = $r[$k] ?? '';
				$num = is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && !preg_match('/^0\d/', $v) && strlen($v) < 15);
				$x .= '<Cell><Data ss:Type="' . ($num ? 'Number' : 'String') . '">' . $e($v) . '</Data></Cell>';
			}
			$x .= '</Row>';
		}
		return $x . '</Table></Worksheet></Workbook>';
	}
}
