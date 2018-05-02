<?php

use Api\Upload\UploadHandler;

// handle options requests to return CORS headers
// See https://www.slimframework.com/docs/cookbook/enable-cors.html
$app->options('/{routes:.+}', function ($request, $response, $args) {
	return $response;
});

$app->get('/welcome', function ($request, $response, $args) {
	// Sample log message
	$this->logger->info("Klusbibapi '/welcome' route");

	// Render index view
	return $this->renderer->render($response, 'welcome.phtml', $args);
});

$app->get('/hello[/{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Klusbibapi '/hello' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
//     return $this->renderer->render($response, 'welcome.phtml', $args);
});

$app->post('/upload', function ($request, $response, $args) {
	$this->logger->info("Klusbibapi '/upload' route");
	$files = $request->getUploadedFiles();
	$uploader = new UploadHandler($this->logger);
	$uploader->uploadFiles($files);
	return $this->renderer->render($response, 'welcome.phtml', ['filename' => $uploader->getUploadedFileName()]);
});

require __DIR__ . '/routes/token.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/tools.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/consumers.php';
require __DIR__ . '/routes/reservations.php';
require __DIR__ . '/routes/events.php';

	
