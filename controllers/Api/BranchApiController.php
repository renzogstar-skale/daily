<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\BranchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BranchApiController extends BaseApiController
{
	public function GetUserAccess(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_BRANCH_MANAGE);
		return $this->ApiResponse($response, BranchService::GetInstance()->GetUserBranchAccess());
	}

	public function SetUserAccess(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_BRANCH_MANAGE);
		try
		{
			$body = $request->getParsedBody() ?? [];
			$ids = array_map('intval', (array) ($body['branch_ids'] ?? []));
			// Un usuario restringido solo puede asignar sus propias sucursales y no puede quitar restricciones (lista vacía)
			$own = \Grocy\Helpers\DairyAccess::AllowedBranches();
			if ($own !== null && (count($ids) === 0 || array_diff($ids, $own)))
			{
				throw new \Exception('Solo puede asignar sus propias sucursales');
			}
			BranchService::GetInstance()->SetUserBranches(intval($args['userId']), $ids);
			return $this->ApiResponse($response, ['success' => true]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
