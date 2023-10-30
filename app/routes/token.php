<?php
/** @var mixed $app */

use Api\Token\TokenController;

$app->post("/token", TokenController::class . ':create');
$app->post("/token/guest", TokenController::class . ':createForGuest');
	
/* This is just for debugging, not usefull in real life. */
$app->get("/dump", function ($request, $response, $arguments) {
	print_r($this->token);
});

