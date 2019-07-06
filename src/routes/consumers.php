<?php

use Api\Consumer\ConsumerController;

$app->get('/consumers', ConsumerController::class . ':get');
$app->get('/consumers/{consumerid}', ConsumerController::class . ':getById');
$app->post('/consumers', ConsumerController::class . ':create');
$app->put('/consumers/{consumerid}', ConsumerController::class . ':update');
$app->delete('/consumers/{consumerid}', ConsumerController::class . ':delete');
