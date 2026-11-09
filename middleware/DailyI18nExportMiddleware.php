<?php

namespace Grocy\Middleware;

use Grocy\Helpers\DailyI18n;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Exportaciones del servidor (CSV y XLSX) en el idioma activo: cuando el usuario trabaja en inglés,
 * traduce los textos de encabezados, estados y descripciones con el catálogo DAILY. Las cifras,
 * códigos e identificadores no coinciden con el catálogo y se conservan tal cual.
 */
class DailyI18nExportMiddleware extends BaseMiddleware
{
	public function __invoke(Request $request, RequestHandler $handler): Response
	{
		$response = $handler->handle($request);
		if (!DailyI18n::IsEnglish())
		{
			return $response;
		}
		$type = strtolower($response->getHeaderLine('Content-Type'));
		$disposition = strtolower($response->getHeaderLine('Content-Disposition'));
		try
		{
			if (str_contains($type, 'text/csv') || (str_contains($disposition, 'attachment') && preg_match('/\.(csv|tsv)"?$/', $disposition)))
			{
				return $this->Replace($response, $this->TranslateCsv((string) $response->getBody()));
			}
			if (str_contains($type, 'spreadsheetml') || preg_match('/\.xlsx"?$/', $disposition))
			{
				$xlsx = $this->TranslateXlsx((string) $response->getBody());
				if ($xlsx !== null)
				{
					return $this->Replace($response, $xlsx);
				}
			}
		}
		catch (\Throwable $e)
		{
			// Ante cualquier problema se entrega el archivo original
		}
		return $response;
	}

	private function Replace(Response $response, string $content): Response
	{
		$body = (new \Slim\Psr7\Factory\StreamFactory())->createStream($content);
		return $response->withBody($body)->withHeader('Content-Length', (string) strlen($content));
	}

	private function TranslateCsv(string $csv): string
	{
		$bom = str_starts_with($csv, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
		$csv = $bom ? substr($csv, 3) : $csv;
		$firstLine = strtok($csv, "\n");
		$sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : (substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',');
		$in = fopen('php://temp', 'r+');
		fwrite($in, $csv);
		rewind($in);
		$out = fopen('php://temp', 'r+');
		while (($row = fgetcsv($in, 0, $sep, '"', '\\')) !== false)
		{
			fputcsv($out, array_map(fn ($c) => is_string($c) ? DailyI18n::T($c, 'en') : $c, $row), $sep, '"', '\\');
		}
		rewind($out);
		return $bom . stream_get_contents($out);
	}

	private function TranslateXlsx(string $bin): ?string
	{
		$tmp = tempnam(sys_get_temp_dir(), 'dxl');
		file_put_contents($tmp, $bin);
		$zip = new \ZipArchive();
		if ($zip->open($tmp) !== true)
		{
			@unlink($tmp);
			return null;
		}
		for ($i = 0; $i < $zip->numFiles; $i++)
		{
			$name = $zip->getNameIndex($i);
			if ($name !== 'xl/sharedStrings.xml' && !preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name))
			{
				continue;
			}
			$xml = $zip->getFromIndex($i);
			$new = preg_replace_callback('#(<t(?:\s[^>]*)?>)([^<]*)(</t>)#u', function ($m) {
				$txt = html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
				return $m[1] . htmlspecialchars(DailyI18n::T($txt, 'en'), ENT_QUOTES | ENT_XML1, 'UTF-8') . $m[3];
			}, $xml);
			if ($new !== null && $new !== $xml)
			{
				$zip->addFromString($name, $new);
			}
		}
		$zip->close();
		$out = file_get_contents($tmp);
		@unlink($tmp);
		return $out ?: null;
	}
}
