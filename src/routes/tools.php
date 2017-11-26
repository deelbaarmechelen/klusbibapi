<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\ModelMapper\ToolMapper;
use Api\Authorisation;
use Api\Model\Tool;
use Api\ModelMapper\ReservationMapper;

$app->get('/tools', function ($request, $response, $args) {
	
	$this->logger->info("Klusbib GET '/tools' route");
	$sortdir = $request->getQueryParam('_sortDir');
	if (!isset($sortdir)) {
			$sortdir = 'asc';
	}
	$sortfield = $request->getQueryParam('_sortField');
	if (!Tool::canBeSortedOn($sortfield) ) {
		$sortfield = 'code';
	}
	$page = $request->getQueryParam('_page');
	if (!isset($page)) {
		$page = '1';
	}
	$perPage = $request->getQueryParam('_perPage');
	if (!isset($perPage)) {
		$perPage = '1000';
	}
    $showAll = $request->getQueryParam('_all');
    if (!isset($showAll)) {
        $showAll = false;
    }
	// TODO: if not admin, filter non visible tools
    if ($showAll) {
        $tools = Capsule::table('tools')
            ->orderBy($sortfield, $sortdir)->get();
    } else {
        $tools = Capsule::table('tools')
            ->where('visible', true)
            ->orderBy($sortfield, $sortdir)->get();
    }
	$tools = Capsule::table('tools')
		->where('visible', true)
		->orderBy($sortfield, $sortdir)->get();
	$tools_page = array_slice($tools, ($page - 1) * $perPage, $perPage);
	
	$data = array();
	foreach ($tools_page as $tool) {
		array_push($data, ToolMapper::mapToolToArray($tool));
	}
	return $response->withJson($data)
					->withHeader('X-Total-Count', count($tools));
});

$app->get('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET '/tools/id' route");
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(404);
	}

	$data = ToolMapper::mapToolToArray($tool);

	// Add tool reservations
	$reservations = $tool->reservations;
	$reservationsArray = array();
	foreach ($reservations as $reservation) {
		array_push($reservationsArray, ReservationMapper::mapReservationToArray($reservation));
	}
	$data["reservations"] = $reservationsArray;
	return $response->withJson($data);
});
	
$app->post('/tools', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/tools' route");
	/* Check if token has needed scope. */
	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.create"]);

	$data = $request->getParsedBody();
	if (empty($data) || empty($data["name"])) {
		return $response->withStatus(400); // Bad request
	}
	$tool = new \Api\Model\Tool();
	// 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
	ToolMapper::mapArrayToTool($data, $tool);
	$tool->save();
	return $response->withJson(ToolMapper::mapToolToArray($tool));
});

$app->put('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT '/tools/id' route");
	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(404);
	}
	$data = $request->getParsedBody();
	ToolMapper::mapArrayToTool($data, $tool);
	// TODO: add image??
	$tool->save();
	return $response->withJson(ToolMapper::mapToolToArray($tool));
});

$app->delete('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib DELETE '/tools/id' route");
	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.delete"]);
// 	if (false === $this->token->hasScope(["tools.all", "tools.delete"])) {
// 		throw new ForbiddenException("Token not allowed to delete tools.", 403);
// 	}
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(204);
	}
	$tool->delete();
	return $response->withStatus(200);
});

