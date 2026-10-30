<?php

namespace Grocy\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DAIRY Mobile (empleado, supervisor y gerente): aplicación de una página en /m. No usa el layout web (sin sidebar);
 * los datos llegan de /api/m/* y las acciones de las APIs de cada módulo.
 * assetV = fecha de modificación de JS/CSS: el service worker sirve estáticos desde caché y la misma ?v= tras un
 * despliegue cargaría el JS anterior con la plantilla nueva.
 */
class MobileController extends BaseController
{
	public function App(Request $request, Response $response, array $args)
	{
		$pub = __DIR__ . '/../public';
		return $this->Render($response, 'mobile_app', ['assetV' => max(intval(@filemtime($pub . '/js/dairy_m.js')), intval(@filemtime($pub . '/css/dairy_m.css')))]);
	}
}
