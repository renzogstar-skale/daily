<?php

namespace Grocy\Helpers;

/**
 * Idioma global DAILY (ES fuente / EN). El catálogo vive en localization/daily/en/*.json
 * (un archivo por módulo, clave = texto en español) y se compila a public/i18n/daily_en.js
 * con bin/daily_i18n_build.php. En el navegador lo aplica public/js/daily_i18n.js; aquí se usa
 * para lo que genera el servidor (CSV/Excel, documentos e impresión).
 */
class DailyI18n
{
	private static ?array $catalog = null;
	private static ?array $lower = null;

	public static function Lang(): string
	{
		$l = $_COOKIE['daily_lang'] ?? null;
		if ($l === null)
		{
			try
			{
				if (defined('GROCY_USER_ID'))
				{
					$l = \Grocy\Services\UsersService::GetInstance()->GetUserSetting(GROCY_USER_ID, 'daily_lang');
				}
			}
			catch (\Throwable $e)
			{
			}
		}
		return $l === 'en' ? 'en' : 'es';
	}

	public static function IsEnglish(): bool
	{
		return self::Lang() === 'en';
	}

	public static function Catalog(): array
	{
		if (self::$catalog === null)
		{
			self::$catalog = [];
			foreach (glob(__DIR__ . '/../localization/daily/en/*.json') ?: [] as $file)
			{
				$part = json_decode((string) file_get_contents($file), true);
				if (is_array($part))
				{
					self::$catalog += $part;
				}
			}
			self::$lower = [];
			foreach (self::$catalog as $k => $v)
			{
				self::$lower[mb_strtolower($k)] = $v;
			}
		}
		return self::$catalog;
	}

	/** Traduce un texto en español al idioma activo (o al indicado). Las cifras pasan como {0},{1}… */
	public static function T(?string $text, ?string $lang = null): ?string
	{
		if ($text === null || ($lang ?? self::Lang()) !== 'en' || !preg_match('/\pL{2}/u', $text))
		{
			return $text;
		}
		$s = trim(preg_replace('/\s+/u', ' ', $text));
		$r = self::Lookup($s);
		if ($r === null)
		{
			$nums = [];
			$tpl = preg_replace_callback('/[-+]?\d+(?:[.,:\/]\d+)*/', function ($m) use (&$nums) {
				$nums[] = $m[0];
				return '{' . (count($nums) - 1) . '}';
			}, $s);
			if ($nums && ($tr = self::Lookup($tpl)) !== null)
			{
				$r = preg_replace_callback('/\{(\d+)\}/', fn ($m) => $nums[(int) $m[1]] ?? $m[0], $tr);
			}
		}
		if ($r === null && preg_match('/^([^\pL¿¡{]*)(.*?)([^\pL}]*)$/u', $s, $m) && $m[2] !== '' && $m[2] !== $s)
		{
			$core = self::T($m[2], 'en');
			$r = $core !== $m[2] ? str_replace(['¿', '¡'], '', $m[1]) . $core . $m[3] : null;
		}
		if ($r === null)
		{
			// Por segmentos, igual que public/js/daily_i18n.js: «Severidad crítica (+60) · Fuera de SLA»
			$parts = preg_split('/(\s+[·•|—–\/→]\s+|\s+-\s+|:\s+|\s*\(\s*|\s*\)\s*|,\s+|;\s+|\s+\+\s+|\s*=\s*)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
			if (count($parts) > 1)
			{
				$changed = false;
				for ($i = 0; $i < count($parts); $i += 2)
				{
					if ($parts[$i] !== '' && preg_match('/\pL{2}/u', $parts[$i]))
					{
						$tp = self::T($parts[$i], 'en');
						if ($tp !== $parts[$i])
						{
							$parts[$i] = $tp;
							$changed = true;
						}
					}
				}
				if ($changed)
				{
					$r = implode('', $parts);
				}
			}
		}
		return $r ?? $text;
	}

	/** Traduce cada valor de texto de un arreglo (encabezados de exportación, etiquetas). */
	public static function TArray(array $values, ?string $lang = null): array
	{
		return array_map(fn ($v) => is_string($v) ? self::T($v, $lang) : $v, $values);
	}

	private static function Lookup(string $s): ?string
	{
		$c = self::Catalog();
		if (isset($c[$s]))
		{
			return $c[$s];
		}
		$l = mb_strtolower($s);
		if (isset(self::$lower[$l]))
		{
			$out = self::$lower[$l];
			if (mb_strlen($s) > 1 && mb_strtoupper($s) === $s && mb_strtolower($s) !== $s)
			{
				return mb_strtoupper($out);
			}
			$f = mb_substr($s, 0, 1);
			if ($f !== mb_strtolower($f))
			{
				return mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1);
			}
			return $out;
		}
		return null;
	}
}
