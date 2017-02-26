<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;

$app->get('/tools', function ($request, $response, $args) {
	/* Check if token has needed scope. */
	if (false === $this->token->hasScope(["tools.all", "tools.list"])) {
		throw new ForbiddenException("Token not allowed to list tools.", 403);
	}
	
	// Sample log message
	$this->logger->info("Klusbib '/tools' route");
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
	// 	$data = array(array("id" => "wood-1",
	// 				"name" => "wipzaag",
	// 				"description" => "Simpele wipzaag",
	// 				"link" => null,
	// 				"category" => "wood"
	// 		)
	// 	);
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
	$this->logger->info("Klusbib '/tools/id' route");
	$tools = Capsule::table('tools')->where('tool_id', $args['toolid'])->get();
	if (null == $tools || count($tools) == 0) {
		return $response->withStatus(404);
	}
	$tool = $tools[0];

	$data = array("id" => $tool->tool_id,
			"name" => $tool->name,
			"description" => $tool->description,
			"link" => $tool->link,
			"category" => $tool->category,
			"reservations" => array()
	);

	// lookup reservations for this tool
	$reservations = Capsule::table('reservations')->where('tool_id', $args['toolid'])->get();
	if (null == $tools) {
		return $response->withStatus(500);
	}

	// 	$data["reservations"] = getSampleReservations();
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
	$this->logger->info("Klusbib put '/tools/id' route");
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
	$tool->save();
	return $response->withJson($data);
});

function getSampleReservations() {
	$reservations = array();
	$startdate = new DateTime();
	$enddate = clone $startdate;
	$enddate->add(new DateInterval('P7D'));
	$startdate2 = new DateTime();
	$startdate2->add(new DateInterval('P14D'));
	$enddate2 = clone $startdate2;
	$enddate2->add(new DateInterval('P7D'));

	// supported colours:
	// 	"darkblue":"#00008b","darkcyan":"#008b8b","darkgoldenrod":"#b8860b","darkgray":"#a9a9a9","darkgreen":"#006400","darkkhaki":"#bdb76b","darkmagenta":"#8b008b","darkolivegreen":"#556b2f",
	// 	"darkorange":"#ff8c00","darkorchid":"#9932cc","darkred":"#8b0000","darksalmon":"#e9967a","darkseagreen":"#8fbc8f","darkslateblue":"#483d8b","darkslategray":"#2f4f4f","darkturquoise":"#00ced1",
	// 	"darkviolet":"#9400d3"

			$reservations = array(
					array(
							"id" => "tool1-reservation1",
							"title" => "My Reservation",
							"color" => "yellow",
							"startsAt" => $startdate->format('Y-m-d'),
							"endsAt" => $enddate->format('Y-m-d'),
							"draggable" => true,
							"resizable" => true,
							"actions" => "actions"
					),
					array(
							"id" => "tool1-reservation2",
							"title" => "My Second Reservation",
							"color" => "red",
							"startsAt" => $startdate2->format('Y-m-d'),
							"endsAt" => $enddate2->format('Y-m-d'),
							"draggable" => true,
							"resizable" => true,
							"actions" => "actions"
					)
			);
			return $reservations;
}

$app->post('/tools/{toolid}/reservations/new', function ($request, $response, $args) {
	$reservation = new \Api\Model\Reservation();
	$tool->name = 'test';
	$tool->description = 'my new tool';
	$tool->save();
	echo 'created';
});
