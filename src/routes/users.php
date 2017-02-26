<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$app->get('/users', function ($request, $response, $args) {
	$this->logger->info("Klusbib '/users' route");
	$users = Capsule::table('users')->orderBy('lastname', 'asc')->get();
	$data = array();
	foreach ($users as $user) {
		$item  = array(
				"user_id" => $user->user_id,
				"firstname" => $user->firstname,
				"lastname" => $user->lastname,
				"role" => $user->role,
				"membership_start_date" => $user->membership_start_date,
				"membership_end_date" => $user->membership_end_date
		);
		array_push($data, $item);
	}
	return $response->withJson($data);
});

$app->get('/users/{userid}', function ($request, $response, $args) {
	$this->logger->info("Klusbib '/users/id' route");
	$user = \Api\Model\User::find($args['userid']);
	if (null == $user) {
		return $response->withStatus(404);
	}

	$data = array("user_id" => $user->user_id,
			"firstname" => $user->firstname,
			"lastname" => $user->lastname,
			"role" => $user->role,
			"membership_start_date" => $user->membership_start_date,
			"membership_end_date" => $user->membership_end_date,
			"reservations" => array()
	);
	return $response->withJson($data);
});