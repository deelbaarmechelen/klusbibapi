<?php
/** @var mixed $app */

use Api\Tool\ToolController;
use Api\Tool\AccessoryController;

$app->get('/tools', ToolController::class . ':getAll');
$app->get('/tools/{toolid}', ToolController::class . ':getById');
$app->post('/tools', ToolController::class . ':create');
$app->post('/tools/{toolid}/upload', ToolController::class . ':uploadProductImage');
$app->put('/tools/{toolid}', ToolController::class . ':update');
$app->delete('/tools/{toolid}', ToolController::class . ':delete');

$app->get('/accessories', AccessoryController::class . ':getAll');
$app->get('/accessories/{accessoryId}', AccessoryController::class . ':getById');
$app->post('/accessories', AccessoryController::class . ':create');
$app->put('/accessories/{accessoryId}', AccessoryController::class . ':update');
$app->delete('/accessories/{accessoryId}', AccessoryController::class . ':delete');
