<?php
/**
 * follow JSON API conventions?
 * http://jsonapi.org/format
 */

use Api\User\UserController;

$app->get('/users', UserController::class . ':get');
$app->get('/users/{userid}', UserController::class . ':getById');
$app->post('/users', UserController::class . ':create');
$app->put('/users/{userid}', UserController::class . ':update');
$app->delete('/users/{userid}', UserController::class . ':delete');

		
	