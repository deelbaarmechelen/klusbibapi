<?php

use Api\Events\EventsController;

$app->get('/events', EventsController::class . ':getAll');
$app->post('/events', EventsController::class . ':create');
