<?php
use Api\Lending\LendingController;

$app->get('/lendings', LendingController::class . ':getAll');
$app->get('/lendings/{lendingId}', LendingController::class . ':getByID');

$app->post('/lendings', LendingController::class . ':create');
$app->put('/lendings/{lendingId}', LendingController::class . ':update');

//$app->delete('/lendings/{paymentId}', LendingController::class . ':delete');

