<?php

use Api\Payment\PaymentController;

$app->post('/payments', PaymentController::class . ':create');

$app->post('/payments/{orderId}', PaymentController::class . ':createWithOrderId') ;

$app->get('/payments/{paymentId}', PaymentController::class . ':getByID');

$app->get('/payments', PaymentController::class . ':getAll');