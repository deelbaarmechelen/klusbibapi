<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\ModelMapper\ToolMapper;
use Api\Authorisation;
use Api\Model\Tool;
use Api\Tool\ToolController;
use Api\ModelMapper\ReservationMapper;
use Api\Upload\UploadHandler;

$app->get('/tools', ToolController::class . ':getAll');
$app->get('/tools/{toolid}', ToolController::class . ':getById');
$app->post('/tools', ToolController::class . ':create');
$app->post('/tools/{toolid}/upload', ToolController::class . ':uploadProductImage');
$app->put('/tools/{toolid}', ToolController::class . ':update');
$app->delete('/tools/{toolid}', ToolController::class . ':delete');
