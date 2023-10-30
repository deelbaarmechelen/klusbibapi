<?php
/** @var mixed $app */
// Application middleware
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// e.g: $app->add(new \Slim\Csrf\Guard);
$streamFactory = new \Slim\Psr7\Factory\StreamFactory();
/**
 * The two modes available are
 * OutputBufferingMiddleware::APPEND (default mode) - Appends to existing response body
 * OutputBufferingMiddleware::PREPEND - Creates entirely new response body
 */
$mode = \Slim\Middleware\OutputBufferingMiddleware::APPEND;
$outputBufferingMiddleware = new \Slim\Middleware\OutputBufferingMiddleware($streamFactory, $mode);
$app->add($outputBufferingMiddleware);

$app->add("HttpBasicAuthentication");
$app->add("JwtAuthentication");

// Add Twig-View Middleware
$twig = \Slim\Views\Twig::create(__DIR__ . '/../templates', ['cache' => __DIR__ . '/../cache']);
$app->add(\Slim\Views\TwigMiddleware::create($app, $twig));

$app->add(function (Request $request, RequestHandler $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Expose-Headers', 'X-Total-Count')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->add(new Api\Middleware\ImageResize([
		"extensions" => ["jpg", "jpeg"],
		"quality" => 90,
		"sizes" => ["800x", "x800", "400x", "x400", "400x200", "x200", "200x", "100x100"]
]));

/**
 * The routing middleware should be added earlier than the ErrorMiddleware
 * Otherwise exceptions thrown from it will not be handled by the middleware
 */
$app->addRoutingMiddleware();

/**
 * Add Error Middleware
 *
 * @param bool                  $displayErrorDetails -> Should be set to false in production
 * @param bool                  $logErrors -> Parameter is passed to the default ErrorHandler
 * @param bool                  $logErrorDetails -> Display error details in error log
 * @param LoggerInterface|null  $logger -> Optional PSR-3 Logger
 *
 * Note: This middleware should be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */
//$displayErrorDetails = APP_ENV != 'production' ? true : false;
$displayErrorDetails = APP_ENV == 'development' ? true : false;
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// Add any middleware which may modify the response body before adding the ContentLengthMiddleware

$app->add(new \Slim\Middleware\ContentLengthMiddleware());
