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

$app->post('/tools', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/tools' route");
	/* Check if token has needed scope. */
	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.create"]);
// 	if (false === $this->token->hasScope(["tools.all", "tools.create"])) {
// 		throw new ForbiddenException("Token not allowed to create tools.", 403);
// 	}

	$data = $request->getParsedBody();
	if (empty($data) || empty($data["name"])) {
		return $response->withStatus(400); // Bad request
	}
	$tool = new \Api\Model\Tool();
	// 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
	$tool->name = $data["name"];
	if (isset($data["description"])) {
		$tool->description = $data["description"];
	}
	if (isset($data["category"])) {
		$tool->category = $data["category"];
	}
	if (isset($data["link"])) {
		$tool->link = $data["link"];
	}
	$tool->save();
	return $response->withJson(ToolMapper::mapToolToArray($tool));
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

$app->put('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT '/tools/id' route");
	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);
// 	if (false === $this->token->hasScope(["tools.all", "tools.update"])) {
// 		throw new ForbiddenException("Token not allowed to update tools.", 403);
// 	}
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(404);
	}
	$parsedBody = $request->getParsedBody();
	if (isset($parsedBody["name"])) {
		$tool->name = $parsedBody["name"];
	}
	if (isset($parsedBody["description"])) {
		$tool->description = $parsedBody["description"];
	}
	if (isset($parsedBody["category"])) {
		$tool->category = $parsedBody["category"];
	}
	if (isset($parsedBody["link"])) {
		$tool->link = $parsedBody["link"];
	}
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
	
// $app->post('/tools/{toolid}/reservations/new', function ($request, $response, $args) {
// 	$this->logger->info("Klusbib POST '/tools/{toolid}/reservations/new' route");
// 	$reservation = new \Api\Model\Reservation();
// 	$tool->name = 'test';
// 	$tool->description = 'my new tool';
// 	$tool->save();
// 	echo 'created';
// });
