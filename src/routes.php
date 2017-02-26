<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// handle options requests to return CORS headers
// See https://www.slimframework.com/docs/cookbook/enable-cors.html
$app->options('/{routes:.+}', function ($request, $response, $args) {
	return $response;
});

$app->get('/welcome', function ($request, $response, $args) {
	// Sample log message
	$this->logger->info("Slim-Skeleton '/' route");

	// Render index view
// 	return $this->renderer->render($response, 'index.phtml', $args);
	return $this->renderer->render($response, 'welcome.phtml', $args);
});

$app->get('/hello[/{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/hello' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
//     return $this->renderer->render($response, 'welcome.phtml', $args);
});

	
$app->get('/reservations', function ($request, $response, $args) {
	$this->logger->info("Klusbib '/reservations' route");
	$reservations = Capsule::table('reservations')->orderBy('startsAt', 'desc')->get();
	$data = array();
	foreach ($reservations as $reservation) {
		$item  = array(
				"reservation_id" => $reservation->reservation_id,
				"tool_id" => $reservation->tool_id,
				"user_id" => $reservation->user_id,
				"title" => $reservation->title,
				"startsAt" => $reservation->startsAt,
				"endsAt" => $reservation->endsAt,
				"type" => $reservation->type,
			);
		array_push($data, $item);
	}
	return $response->withJson($data);
});
