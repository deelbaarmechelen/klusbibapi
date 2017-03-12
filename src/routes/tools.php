<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\ToolMapper;
use Api\Authorisation;

$app->get('/tools', function ($request, $response, $args) {
	
	$this->logger->info("Klusbib GET '/tools' route");
	$tools = Capsule::table('tools')->orderBy('name', 'asc')->get();
	$data = array();
	foreach ($tools as $tool) {
		array_push($data, ToolMapper::mapToolToArray($tool));
	}
	return $response->withJson($data);
});

$app->get('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET '/tools/id' route");
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(404);
	}

	$data = ToolMapper::mapToolToArray($tool);

	// lookup reservations for this tool
	$reservations = Capsule::table('reservations')->where('tool_id', $args['toolid'])->get();
	// 	if (null == $reservations) {
	// 		return $response->withStatus(500);
	// 	}

	foreach ($reservations as $reservation) {
		$item  = array(
				"reservation_id" => $reservation->reservation_id,
				"tool_id" => $reservation->tool_id,
				"user_id" => $reservation->user_id,
				"title" => $reservation->title,
				// 				"color" => "blue",
				// 				"draggable" => true,
				// 				"resizable" => true,
				// 				"actions" => "actions",
				"startsAt" => $reservation->startsAt,
				"endsAt" => $reservation->endsAt,
				"type" => $reservation->type
		);
		// 		array_push($data["reservations"], $item);
	}

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

