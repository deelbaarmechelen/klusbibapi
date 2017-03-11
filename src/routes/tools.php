<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;

$app->get('/tools', function ($request, $response, $args) {
	
	$this->logger->info("Klusbib GET '/tools' route");
	$tools = Capsule::table('tools')->orderBy('name', 'asc')->get();
	$data = array();
	foreach ($tools as $tool) {
		$item  = array(
				"id" => $tool->tool_id,
				"name" => $tool->name,
				"description" => $tool->description,
				"link" => $tool->link,
				"category" => $tool->category
		);
		array_push($data, $item);
	}
	return $response->withJson($data);
});

$app->post('/tools', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/tools' route");
	/* Check if token has needed scope. */
	if (false === $this->token->hasScope(["tools.all", "tools.create"])) {
		throw new ForbiddenException("Token not allowed to create tools.", 403);
	}

	$data = $request->getParsedBody();
	if (empty($data) || empty($data["name"])) {
		return $response->withStatus(400); // Bad request
	}
	$tool = new \Api\Model\Tool();
	// 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
// 	$tool->tool_id = null;
	$tool->name = $data["name"];
	if (isset($data["description"])) {
		$tool->description = $data["description"];
	}
	$tool->save();
	// FIXME: returned data should reflect newly create tool (incl toolId)!
	return $response->withJson($data);
});

$app->post('/tools/new', function ($request, $response, $args) {
	/* Check if token has needed scope. */
	if (false === $this->token->hasScope(["tools.all", "tools.create"])) {
		throw new ForbiddenException("Token not allowed to create tools.", 403);
	}
	
	// 	$app->post('/tools/new', function (Request $request, Response $response) {
	// 	$data = $request->getParsedBody();
	// 	echo $args;
	$tool = new \Api\Model\Tool();
	// 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
	$tool->name = 'test';
	$tool->description = 'my new tool';
	$tool->save();
	echo 'created';
	// 	$tool->description = filter_var($data['description'], FILTER_SANITIZE_STRING);
	// 	$tools_data = [];
	// 	$tools_data['name'] = filter_var($data['name'], FILTER_SANITIZE_STRING);
	// 	$tools_data['description'] = filter_var($data['description'], FILTER_SANITIZE_STRING);
	// 	$tools_data['name'] = filter_var($data['name'], FILTER_SANITIZE_STRING);
	// ...
});

$app->get('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET '/tools/id' route");
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(404);
	}

	$data = array("id" => $tool->tool_id,
			"name" => $tool->name,
			"description" => $tool->description,
			"link" => $tool->link,
			"category" => $tool->category,
			"reservations" => array()
	);

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
		array_push($data["reservations"], $item);
	}

	return $response->withJson($data);
});

$app->put('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT '/tools/id' route");
	if (false === $this->token->hasScope(["tools.all", "tools.update"])) {
		throw new ForbiddenException("Token not allowed to update tools.", 403);
	}
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
	// TODO: return array containing tool data 
	return $response->withStatus(200);
});

$app->delete('/tools/{toolid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib DELETE '/tools/id' route");
	if (false === $this->token->hasScope(["tools.all", "tools.delete"])) {
		throw new ForbiddenException("Token not allowed to delete tools.", 403);
	}
	$tool = \Api\Model\Tool::find($args['toolid']);
	if (null == $tool) {
		return $response->withStatus(204);
	}
	$tool->delete();
	return $response->withStatus(200);
});
	
$app->post('/tools/{toolid}/reservations/new', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/tools/{toolid}/reservations/new' route");
	$reservation = new \Api\Model\Reservation();
	$tool->name = 'test';
	$tool->description = 'my new tool';
	$tool->save();
	echo 'created';
});
