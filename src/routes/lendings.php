<?php
use Api\Lending\LendingController;

$app->get('/lendings', LendingController::class . ':getAll');
