<?php

use Firebase\JWT\JWT;
use Tuupola\Base62;

$app->post("/token", function ($request, $response, $arguments) {
	$requested_scopes = $request->getParsedBody();
	$valid_scopes = [
			"tools.create",
			"tools.read",
			"tools.update",
			"tools.delete",
			"tools.list",
			"tools.all"
	];

	$scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
		return in_array($needle, $valid_scopes);
	});
		
	$now = new DateTime();
	$future = new DateTime("now +2 hours");
	$server = $request->getServerParams();

	$jti = Base62::encode(random_bytes(16));

	$payload = [
			"iat" => $now->getTimeStamp(), 		// issued at
			"exp" => $future->getTimeStamp(),	// expiration
			"jti" => $jti,						// JWT ID
			"sub" => $server["PHP_AUTH_USER"],	
			"scope" => $scopes
	];

	$secret = getenv("JWT_SECRET");
	$token = JWT::encode($payload, $secret, "HS256");
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

