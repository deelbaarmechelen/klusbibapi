<?php

use Api\Token;
use Illuminate\Database\Capsule\Manager as Capsule;

$app->post("/token", function ($request, $response, $arguments) use ($app) {
	$this->logger->info("Klusbib POST '/token' route");
	$valid_scopes = Token::validScopes();
		
	$container = $app->getContainer();
	$user = Capsule::table('users')->where('email', $container["user"])->first();
	if (null == $user) {
		return $response->withStatus(404);
	}
	$sub = $user->user_id;
	$requested_scopes = Token::allowedScopes($user->role);
	$scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
		return in_array($needle, $valid_scopes);
	});
	$token = Token::generateToken($scopes, $sub); 
	$this->logger->info("Token generated with scopes " . json_encode($scopes) . " and sub " .  json_encode($sub));
	
	$data["status"] = "ok";
	$data["token"] = $token;

	return $response->withStatus(201)
		->withHeader("Content-Type", "application/json")
		->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

/* This is just for debugging, not usefull in real life. */
$app->get("/dump", function ($request, $response, $arguments) {
	print_r($this->token);
});

// $app->post("/dump", function ($request, $response, $arguments) {
// 	print_r($this->token);
// });

