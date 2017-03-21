<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// handle options requests to return CORS headers
// See https://www.slimframework.com/docs/cookbook/enable-cors.html
$app->options('/{routes:.+}', function ($request, $response, $args) {
	return $response;
});

$app->get('/welcome', function ($request, $response, $args) {
	// Sample log message
	$this->logger->info("Slim-Skeleton '/' route");

	// Render index view
// 	return $this->renderer->render($response, 'index.phtml', $args);
	return $this->renderer->render($response, 'welcome.phtml', $args);
});

$app->get('/hello[/{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/hello' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
//     return $this->renderer->render($response, 'welcome.phtml', $args);
});

$app->post('/upload', function ($request, $response, $args) {
	$files = $request->getUploadedFiles();
	if (empty($files['newfile'])) {
		throw new Exception('Expected a newfile');
	}

	$newfile = $files['newfile'];
	if ($newfile->getError() === UPLOAD_ERR_OK) {
		$uploadFileName = $newfile->getClientFilename();
		$newfile->moveTo(__DIR__ . "/../public/uploads/$uploadFileName");
	}
	return $this->renderer->render($response, 'welcome.phtml', ['filename' => $uploadFileName]);
});

require __DIR__ . '/routes/token.php';
require __DIR__ . '/routes/tools.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/consumers.php';
require __DIR__ . '/routes/reservations.php';
	
	
