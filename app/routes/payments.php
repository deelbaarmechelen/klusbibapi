<?php

use Api\Payment\PaymentController;
echo "Adding payments routes\n";
$app->post('/payments', PaymentController::class . ':create');

$app->post('/payments/{orderId}', PaymentController::class . ':createWithOrderId') ;

$app->delete('/payments/{paymentId}', PaymentController::class . ':delete');

$app->get('/payments/{paymentId}', PaymentController::class . ':getByID');

$app->get('/payments', PaymentController::class . ':getAll');