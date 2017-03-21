<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

$app->add("HttpBasicAuthentication");
$app->add("JwtAuthentication");

$app->add(function ($req, $res, $next) {
	$response = $next($req, $res);
	return $response
		->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
		->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->add(new Api\Middleware\ImageResize([
		"extensions" => ["jpg", "jpeg"],
		"quality" => 90,
		"sizes" => ["x400", "400x200", "x200", "200x", "100x100"]
]));

