<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Exception\ForbiddenException;
use Api\ModelMapper\ReservationMapper;
use Api\Authorisation;
use Api\Validator\ReservationValidator;

$app->get('/reservations', function ($request, $response, $args) {
	
	$this->logger->info("Klusbib GET '/reservations' route");
	$reservations = Capsule::table('reservations')->orderBy('startsAt', 'desc')->get();
	$data = array();
	foreach ($reservations as $reservation) {
		array_push($data, ReservationMapper::mapReservationToArray($reservation));
	}
	return $response->withJson($data);
});

$app->post('/reservations', function ($request, $response, $args) {
	$this->logger->info("Klusbib POST '/reservations' route");
	Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.create"]);

	$data = $request->getParsedBody();
	if (!ReservationValidator::isValidReservationData($data, $this->logger)) {
		return $response->withStatus(400); // Bad request
	}
	$reservation = new \Api\Model\Reservation();
	// 	$reservation->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
	$reservation->tool_id = $data["tool_id"];
	$reservation->user_id = $data["user_id"];
	if (isset($data["title"])) {
		$reservation->title = $data["title"];
	}
	if (isset($data["type"])) {
		$reservation->type = $data["type"];
	}
	if (isset($data["startsAt"])) {
		$reservation->startsAt = $data["startsAt"];
	}
	if (isset($data["endsAt"])) {
		$reservation->endsAt = $data["endsAt"];
	}
	$reservation->save();
	return $response->withJson(ReservationMapper::mapReservationToArray($reservation));
});

$app->get('/reservations/{reservationid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib GET '/reservations/id' route");
	$reservation = \Api\Model\Reservation::find($args['reservationid']);
	if (null == $reservation) {
		return $response->withStatus(404);
	}

	$data = ReservationMapper::mapReservationToArray($reservation);
	return $response->withJson($data);
});

$app->put('/reservations/{reservationid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib PUT '/reservations/id' route");
	Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.update", "reservations.update.owner"]);
	$reservation = \Api\Model\Reservation::find($args['reservationid']);
	if (null == $reservation) {
		return $response->withStatus(404);
	}
	$data = $request->getParsedBody();
	$this->logger->info("Klusbib PUT body: ". json_encode($data));
	if (isset($data["title"])) {
		$this->logger->info("Klusbib PUT updating title from " . $reservation->title . " to " . $data["title"]);
		$reservation->title = $data["title"];
	}
	if (isset($data["type"])) {
		$this->logger->info("Klusbib PUT updating type from " . $reservation->type . " to " . $data["type"]);
		$reservation->type = $data["type"];
	}
	if (isset($data["startsAt"])) {
		$this->logger->info("Klusbib PUT updating startsAt from " . $reservation->startsAt . " to " . $data["startsAt"]);
		$reservation->startsAt = $data["startsAt"];
	}
	if (isset($data["endsAt"])) {
		$this->logger->info("Klusbib PUT updating endsAt from " . $reservation->endsAt . " to " . $data["endsAt"]);
		$reservation->endsAt = $data["endsAt"];
	}
	$reservation->save();
	return $response->withJson(ReservationMapper::mapReservationToArray($reservation));
});

$app->delete('/reservations/{reservationid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib DELETE '/reservations/id' route");
	Authorisation::checkAccessByToken($this->token, ["reservations.all", "reservations.delete", "reservations.delete.owner"]);
	$reservation = \Api\Model\Reservation::find($args['reservationid']);
	if (null == $reservation) {
		return $response->withStatus(204);
	}
	$reservation->delete();
	return $response->withStatus(200);
});
	
// $app->post('/reservations/{toolid}/reservations/new', function ($request, $response, $args) {
// 	$this->logger->info("Klusbib POST '/reservations/{reservationid}/reservations/new' route");
// 	$reservation = new \Api\Model\Reservation();
// 	$reservation->name = 'test';
// 	$reservation->description = 'my new reservation';
// 	$reservation->save();
// 	echo 'created';
// });
