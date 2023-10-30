<?php
/** @var mixed $app */

use Api\Delivery\DeliveryController;

$app->get('/deliveries', DeliveryController::class . ':getAll');
$app->get('/deliveries/{deliveryid}', DeliveryController::class . ':getByID');
$app->post('/deliveries', DeliveryController::class . ':create');
$app->put('/deliveries/{deliveryid}', DeliveryController::class . ':update');
$app->delete('/deliveries/{deliveryid}', DeliveryController::class . ':delete');
$app->post('/deliveries/{deliveryid}/items', DeliveryController::class . ':addItem');
$app->put('/deliveries/{deliveryid}/items/{itemid}', DeliveryController::class . ':updateItem');
$app->delete('/deliveries/{deliveryid}/items/{itemid}', DeliveryController::class . ':removeItem');



