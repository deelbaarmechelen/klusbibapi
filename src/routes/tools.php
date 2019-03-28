<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\ModelMapper\ToolMapper;
use Api\Authorisation;
use Api\Model\Tool;
use Api\Tool\ToolController;
use Api\ModelMapper\ReservationMapper;
use Api\Upload\UploadHandler;

$app->get('/tools', ToolController::class . ':get');
//$app->get('/tools', function ($request, $response, $args) {
//
//	$this->logger->info("Klusbib GET '/tools' route");
//	$sortdir = $request->getQueryParam('_sortDir');
//	if (!isset($sortdir)) {
//			$sortdir = 'asc';
//	}
//	$sortfield = $request->getQueryParam('_sortField');
//	if (!Tool::canBeSortedOn($sortfield) ) {
//		$sortfield = 'code';
//	}
//	$page = $request->getQueryParam('_page');
//	if (!isset($page)) {
//		$page = '1';
//	}
//	$perPage = $request->getQueryParam('_perPage');
//	if (!isset($perPage)) {
//		$perPage = '1000';
//	}
//    $showAll = $request->getQueryParam('_all');
//    if (isset($showAll) && $showAll == 'true') {
//        $showAll = true;
//    } else {
//        $showAll = false;
//    }
//    $category = $request->getQueryParam('category');
//    $toolManager = \Api\Tool\ToolManager::instance();
//    $tools = $toolManager->getAll($showAll, $category, $sortfield, $sortdir, $page, $perPage);
//
//	// TODO: if not admin, filter non visible tools
////    if ($showAll === true) {
////        $builder = Capsule::table('tools');
////    } else {
////        $builder = Capsule::table('tools')
////            ->where('visible', true);
////    }
////
////    if (isset($category)) {
////        $builder = $builder->where('category', $category);
////    }
////    $tools = $builder->orderBy($sortfield, $sortdir)->get();
//	$tools_page = array_slice($tools->all(), ($page - 1) * $perPage, $perPage);
//
//	$data = array();
//	foreach ($tools_page as $tool) {
//		array_push($data, ToolMapper::mapToolToArray($tool));
//	}
//	return $response->withJson($data)
//					->withHeader('X-Total-Count', count($tools));
//});

$app->get('/tools/{toolid}', ToolController::class . ':getById');
//$app->get('/tools/{toolid}', function ($request, $response, $args) {
//	$this->logger->info("Klusbib GET '/tools/id' route");
//
//    $toolManager = \Api\Tool\ToolManager::instance();
//    $tool = $toolManager->getById($args['toolid']);
//
//    if (null == $tool) {
//        return $response->withStatus(404);
//    }
//
//    $data = ToolMapper::mapToolToArray($tool);
//
//	// Add tool reservations
//	$reservations = $tool->reservations;
//	$reservationsArray = array();
//	foreach ($reservations as $reservation) {
//		array_push($reservationsArray, ReservationMapper::mapReservationToArray($reservation));
//	}
//	$data["reservations"] = $reservationsArray;
//	return $response->withJson($data);
//});

$app->post('/tools', ToolController::class . ':create');
//$app->post('/tools', function ($request, $response, $args) {
//	$this->logger->info("Klusbib POST '/tools' route");
//	/* Check if token has needed scope. */
//	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.create"]);
//
//	$data = $request->getParsedBody();
//	if (empty($data) || empty($data["name"])) {
//		return $response->withStatus(400); // Bad request
//	}
//	$tool = new \Api\Model\Tool();
//	// 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
//	ToolMapper::mapArrayToTool($data, $tool);
//	$tool->save();
//	return $response->withJson(ToolMapper::mapToolToArray($tool));
//});
$app->post('/tools/{toolid}/upload', ToolController::class . ':uploadToolImage');
//$app->post('/tools/{toolid}/upload', function ($request, $response, $args) {
//    $this->logger->info("Klusbib POST '/tools/{toolid}/upload' route");
//    /* Check if token has needed scope. */
//    Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);
//
//    $this->logger->info('$_FILES=' . json_encode($_FILES));
//    $files = $request->getUploadedFiles();
//    $this->logger->info('$files=' . json_encode($files));
//
//    $tool = \Api\Model\Tool::find($args['toolid']);
//    if (null == $tool) {
//        return $response->withStatus(404);
//    }
//    // upload file and save location to tool img url
//    $uploader = new UploadHandler($this->logger);
//    if (empty($tool->code) || $tool->code == 'not assigned') {
//        $uploader->uploadFile($files['newfile']);
//    } else {
//        $uploader->uploadFile($files['newfile'], $tool->code);
//    }
//
//    $tool->img = $uploader->getUploadPublicUrl();
//    $tool->save();
//    return $response->withJson(ToolMapper::mapToolToArray($tool));
//});

$app->put('/tools/{toolid}', ToolController::class . ':update');
//$app->put('/tools/{toolid}', function ($request, $response, $args) {
//	$this->logger->info("Klusbib PUT '/tools/id' route");
//	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);
//	$tool = \Api\Model\Tool::find($args['toolid']);
//	if (null == $tool) {
//		return $response->withStatus(404);
//	}
//	$data = $request->getParsedBody();
//	ToolMapper::mapArrayToTool($data, $tool);
//	$tool->save();
//	return $response->withJson(ToolMapper::mapToolToArray($tool));
//});

$app->delete('/tools/{toolid}', ToolController::class . ':delete');
//$app->delete('/tools/{toolid}', function ($request, $response, $args) {
//	$this->logger->info("Klusbib DELETE '/tools/id' route");
//	Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.delete"]);
//// 	if (false === $this->token->hasScope(["tools.all", "tools.delete"])) {
//// 		throw new ForbiddenException("Token not allowed to delete tools.", 403);
//// 	}
//	$tool = \Api\Model\Tool::find($args['toolid']);
//	if (null == $tool) {
//		return $response->withStatus(204);
//	}
//	$tool->delete();
//	return $response->withStatus(200);
//});

