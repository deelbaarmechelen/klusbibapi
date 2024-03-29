<?php
/** @var mixed $app */
use Api\Lending\LendingController;

$app->get('/lendings', LendingController::class . ':getAll');
$app->get('/lendings/{lendingId}', LendingController::class . ':getByID');

// deprecated, update of lendings through sync_inventory (sync_loans) batch
$app->post('/lendings', LendingController::class . ':create');
$app->put('/lendings/{lendingId}', LendingController::class . ':update');

//$app->delete('/lendings/{paymentId}', LendingController::class . ':delete');

