<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DailySiteContent;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * Sitio comercial público de DAILY. No usa el layout de la aplicación ni datos operativos.
 */
class SiteController extends BaseController
{
	public function Home(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'home', ['es' => 'DAILY — Inteligencia y control para cada operación de tu negocio', 'en' => 'DAILY — Intelligence and control for every operation in your business']);
	}

	public function Features(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'features', ['es' => 'Funcionalidades de DAILY', 'en' => 'DAILY features']);
	}

	public function Feature(Request $request, Response $response, array $args)
	{
		$c = DailySiteContent::Get($this->Lang($request));
		$slug = $args['slug'] ?? '';
		if (!isset($c['modules'][$slug]))
		{
			throw new HttpNotFoundException($request);
		}
		$name = $c['modules'][$slug]['name'];
		return $this->Page($request, $response, 'feature', ['es' => $name . ' — DAILY', 'en' => $name . ' — DAILY'], ['slug' => $slug, 'module' => $c['modules'][$slug]]);
	}

	public function Solutions(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'solutions', ['es' => 'Soluciones — DAILY', 'en' => 'Solutions — DAILY']);
	}

	public function Industries(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'industries', ['es' => 'Industrias — DAILY', 'en' => 'Industries — DAILY']);
	}

	public function Industry(Request $request, Response $response, array $args)
	{
		$c = DailySiteContent::Get($this->Lang($request));
		$slug = $args['slug'] ?? '';
		if (!isset($c['industries'][$slug]))
		{
			throw new HttpNotFoundException($request);
		}
		$name = $c['industries'][$slug]['name'];
		return $this->Page($request, $response, 'industry', ['es' => 'DAILY para ' . $name, 'en' => 'DAILY for ' . $name], ['slug' => $slug, 'industry' => $c['industries'][$slug]]);
	}

	public function Pricing(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'pricing', ['es' => 'Planes y precios — DAILY', 'en' => 'Plans and pricing — DAILY']);
	}

	public function Demo(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'demo', ['es' => 'Solicitar demostración — DAILY', 'en' => 'Request a demo — DAILY']);
	}

	public function Contact(Request $request, Response $response, array $args)
	{
		return $this->Page($request, $response, 'contact', ['es' => 'Contacto — DAILY', 'en' => 'Contact — DAILY']);
	}

	public function Legal(Request $request, Response $response, array $args)
	{
		$doc = str_ends_with($request->getUri()->getPath(), '/terms') ? 'terms' : 'privacy';
		$title = $doc === 'terms' ? ['es' => 'Términos de uso — DAILY', 'en' => 'Terms of use — DAILY'] : ['es' => 'Privacidad — DAILY', 'en' => 'Privacy — DAILY'];
		return $this->Page($request, $response, 'legal', $title, ['doc' => $doc]);
	}

	/**
	 * Recibe solicitudes de demostración/contacto y las guarda en data/site_leads.jsonl (sin tocar la base de datos).
	 */
	public function SubmitLead(Request $request, Response $response, array $args)
	{
		$in = $request->getParsedBody();
		if (!is_array($in))
		{
			$in = json_decode((string)$request->getBody(), true) ?: [];
		}
		$field = fn (string $k, int $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

		// Campo trampa para bots: si viene lleno se responde como aceptado sin guardar
		if ($field('website', 200) !== '')
		{
			return $this->Json($response, 200, ['ok' => true]);
		}

		$lead = [
			'type' => $field('type', 20) === 'contact' ? 'contact' : 'demo',
			'name' => $field('name', 120),
			'company' => $field('company', 160),
			'email' => $field('email', 160),
			'phone' => $field('phone', 40),
			'country' => $field('country', 80),
			'industry' => $field('industry', 80),
			'branches' => $field('branches', 20),
			'message' => $field('message', 3000),
			'lang' => $this->Lang($request),
		];

		$errors = [];
		if (mb_strlen($lead['name']) < 2)
		{
			$errors[] = 'name';
		}
		if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL))
		{
			$errors[] = 'email';
		}
		if ($lead['type'] === 'demo')
		{
			if (mb_strlen($lead['company']) < 2)
			{
				$errors[] = 'company';
			}
			if (!preg_match('/^[0-9+()\s.-]{7,25}$/', $lead['phone']))
			{
				$errors[] = 'phone';
			}
			foreach (['country', 'industry', 'branches'] as $k)
			{
				if ($lead[$k] === '')
				{
					$errors[] = $k;
				}
			}
		}
		elseif (mb_strlen($lead['message']) < 10)
		{
			$errors[] = 'message';
		}
		if ($errors)
		{
			return $this->Json($response, 422, ['ok' => false, 'errors' => $errors]);
		}

		$lead['received_at'] = date('c');
		$lead['ip_hash'] = hash('sha256', ($request->getServerParams()['REMOTE_ADDR'] ?? '') . GROCY_DATAPATH);
		$written = @file_put_contents(GROCY_DATAPATH . '/site_leads.jsonl', json_encode($lead, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
		if ($written === false)
		{
			return $this->Json($response, 503, ['ok' => false, 'errors' => ['storage']]);
		}

		return $this->Json($response, 201, ['ok' => true]);
	}

	private function Json(Response $response, int $status, array $data): Response
	{
		$response->getBody()->write(json_encode($data));
		return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
	}

	private function Lang(Request $request): string
	{
		return DailySiteContent::Lang($request->getQueryParams()['lang'] ?? null, $request->getCookieParams()['daily_lang'] ?? null);
	}

	private function Page(Request $request, Response $response, string $view, array $title, array $data = []): Response
	{
		$lang = $this->Lang($request);
		$urlManager = $this->AppContainer->get('UrlManager');
		$path = '/' . ltrim(substr($request->getUri()->getPath(), strlen(rtrim(GROCY_BASE_PATH, '/'))), '/');
		$authDisabled = in_array(GROCY_MODE, ['dev', 'demo', 'prerelease'], true) || GROCY_IS_EMBEDDED_INSTALL || GROCY_DISABLE_AUTH;

		$query = $request->getQueryParams()['lang'] ?? null;
		if ($query !== null && $query === $lang)
		{
			setcookie('daily_lang', $lang, ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax']);
		}

		$this->View->set('U', fn ($p) => $urlManager->ConstructUrl($p));
		$this->View->set('A', fn ($p) => $urlManager->ConstructUrl($p, true) . '?v=' . @filemtime(__DIR__ . '/../public' . $p));
		$response = $this->View->Render($response, 'site.' . $view, array_merge([
			'c' => DailySiteContent::Get($lang),
			'lang' => $lang,
			'L' => fn (string $es, string $en) => $lang === 'en' ? $en : $es,
			'page' => $view,
			'path' => $path,
			'pageTitle' => $title[$lang],
			'canonical' => $urlManager->ConstructUrl($path),
			'loginUrl' => $urlManager->ConstructUrl($authDisabled ? '/today' : '/login'),
		], $data));

		return $response->withHeader('Content-Language', $lang)->withHeader('Vary', 'Cookie');
	}
}
