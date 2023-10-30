<?php
/**
 * follow JSON API conventions?
 * http://jsonapi.org/format
 */
/** @var mixed $app */

use Api\User\UserController;

$app->get('/users/{userid}', UserController::class . ':getById');
$app->get('/users', UserController::class . ':getAll');
$app->post('/users', UserController::class . ':create');
$app->put('/users/{userid}', UserController::class . ':update');
$app->put('/users/{userid}/terms', UserController::class . ':updateTerms');
$app->delete('/users/{userid}', UserController::class . ':delete');

		
	