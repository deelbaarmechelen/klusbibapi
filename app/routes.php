<?php
/** @var mixed $app */

use Api\Upload\UploadHandler;
use Api\Statistics\StatController;

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

$app->post('/upload', function ($request, $response, $args) {
	$this->logger->info("Klusbibapi '/upload' route");
    $files = $request->getUploadedFiles();

	$uploader = new UploadHandler($this->logger);
	$uploader->uploadFiles($files);
	return $this->renderer->render($response, 'welcome.phtml', ['filename' => $uploader->getUploadedFileName()]);
});

require __DIR__ . '/routes/token.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/enrolment.php';
require __DIR__ . '/routes/tools.php';
require __DIR__ . '/routes/deliveries.php';
require __DIR__ . '/routes/users.php';
require __DIR__ . '/routes/reservations.php';
require __DIR__ . '/routes/payments.php';
require __DIR__ . '/routes/lendings.php';
require __DIR__ . '/routes/membership.php';

$app->get('/stats/monthly', StatController::class . ':monthly');
$app->get('/stats/yearly', StatController::class . ':yearly');

