<?php

use Api\Delivery\DeliveryController;

$app->get('/deliveries', DeliveryController::class . ':getAll');



