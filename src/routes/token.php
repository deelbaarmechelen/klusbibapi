<?php

use Api\Token;

$app->post("/token", function ($request, $response, $arguments) {
	$this->logger->info("Klusbib POST '/token' route");
	$requested_scopes = $request->getParsedBody();
	$valid_scopes = [
			"tools.create",
			"tools.read",
			"tools.update",
			"tools.delete",
			"tools.list",
			"tools.all",
			"reservations.create",
			"reservations.read",
			"reservations.update",
			"reservations.delete",
			"reservations.list",
			"reservations.all",
			"consumers.create",
			"consumers.read",
			"consumers.update",
			"consumers.delete",
			"consumers.list",
			"consumers.all",
			"users.create",
			"users.read",
			"users.update",
			"users.delete",
			"users.list",
			"users.all"
	];

	$scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
		return in_array($needle, $valid_scopes);
	});
		
	$server = $request->getServerParams();
	$sub = $server["PHP_AUTH_USER"];
	
	$token = Token::generateToken($scopes, $sub); 
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

