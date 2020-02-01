<?php
// DIC configuration
use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Api\Token\Token;
use Api\Mail\MailManager;
use Api\User\UserController;
use Api\Tool\ToolController;
use Api\User\UserManager;
use Api\Tool\ToolManager;
use Api\Inventory\SnipeitInventory;
use Api\Statistics\StatController;
use Mollie\Api\MollieApiClient;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Fetch DI Container
$container = $app->getContainer();

// Register Twig View helper
$container['view'] = function (ContainerInterface $c) {
    $view = new \Slim\Views\Twig(__DIR__ . '/../templates', [
        'cache' => __DIR__ . '/../cache'
    ]);

    // Instantiate and add Slim specific extension
    $router = $c->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));

    return $view;
};

// view renderer
$container['renderer'] = function (ContainerInterface $c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function (ContainerInterface $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// PDO
$container['db'] = function (ContainerInterface $c) {
	$db = $c['settings']['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
			$db['user'], $db['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};

// Configure error handler
$container['errorHandler'] = function (ContainerInterface $container) {
    return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $exception) use ($container) {
        $logger = $container->get("logger"); // retrieve the 'logger' from the container
        $logger->error("Unexpected error on request " . $request->getMethod() . " " . $request->getRequestTarget()
            . ". Body: " . $request->getBody()->read(100)
            . ($request->getBody()->getSize() > 100 ? "..." : ""));
        $logger->error($exception);
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Something went wrong!');
    };
};

$container["token"] = function (ContainerInterface $container) {
	return new Token;
};

$container["user"] = function (ContainerInterface $container) {
	return "";
};

$container["HttpBasicAuthentication"] = function (ContainerInterface $container) {
	return new HttpBasicAuthentication([
			"path" => "/token",
			"passthrough" => "/token/guest",
			"secure" => false,
			"relaxed" => ["admin", "klusbib.deeleco"],
			"authenticator" => new PdoAuthenticator([
					"pdo" => $container['db'],
					"table" => "users",
					"user" => "email",
					"hash" => "hash"
			]),
			"callback" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) use ($container) {
				$container["user"] = $arguments["user"];
// 				print_r($arguments);
// 				print_r($container["user"]);
			}
	]);
};

$container["JwtAuthentication"] = function (ContainerInterface $container) {
	return new JwtAuthentication([
			"path" => "/",
			"passthrough" => ["/token", "/welcome", "/upload", "/enrolment", "/payments", "/stats",
                "/auth/reset", "/auth/verifyemail"],
			"secret" => getenv("JWT_SECRET"),
			"logger" => $container["logger"],
//			"secure" => (APP_ENV == "development" ? false : true), // force HTTPS for production
			"secure" => false, // disable -> scheme not always correctly set on request!
			"relaxed" => ["admin"], // list hosts allowed without HTTPS for DEV
			"error" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) {
				$data = array("error" => array( "status" => 401, "message" => $arguments["message"]));
				return $response
					->withHeader("Content-Type", "application/json")
					->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			},
			"rules" => [
					new \Api\Middleware\Jwt\JwtCustomRule([
							"getpassthrough" => ["/tools", "/consumers", "/auth/confirm"]
					]),
					new \Slim\Middleware\JwtAuthentication\RequestMethodRule([
							"passthrough" => ["OPTIONS"]
					])
			],
			"callback" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) use ($container) {
				$container['logger']->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
				$container["token"]->hydrate($arguments["decoded"]);
			}
	]);
};

$container["Api\Enrolment\EnrolmentFactory"] = function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return new \Api\Enrolment\EnrolmentFactory(new MailManager(null, null, $logger), new MollieApiClient());
};
$container["Api\Inventory"] = function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return SnipeitInventory::instance($logger);
};

$container['Api\User\UserController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new UserController($logger, new UserManager($inventory, $logger), new ToolManager($inventory, $logger),$token);
};

$container['Api\Tool\ToolController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new ToolController($logger, new ToolManager($inventory, $logger),$token);
};
$container['Api\Consumer\ConsumerController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    $token = $c->get("token"); // retrieve the 'token' from the container
    return new \Api\Consumer\ConsumerController($inventory, $logger, $token);
};
$container['Api\Statistics\StatController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    return new StatController($inventory, $logger);
};
$container['Api\Authentication\PasswordResetController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $renderer = $c->get("renderer");
    return new \Api\Authentication\PasswordResetController($logger, $renderer);
};
$container['Api\Authentication\VerifyEmailController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $jwtAuthentication = $c->get("JwtAuthentication");
    $view = $c-> get("view");
    return new \Api\Authentication\VerifyEmailController($logger, $jwtAuthentication, $view);
};
$container['Api\Enrolment\EnrolmentController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $jwtAuthentication = $c->get("JwtAuthentication");
    $enrolmentFactory = $c->get("Api\Enrolment\EnrolmentFactory");
    $token = $c->get("token");
    return new \Api\Enrolment\EnrolmentController($logger, $enrolmentFactory, $jwtAuthentication, $token);
};
$container['Api\Events\EventsController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token");
    return new \Api\Events\EventsController($logger, $token);
};
$container['Api\Payment\PaymentController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager();
    $mollieClient = new \Mollie\Api\MollieApiClient();
    $mollieClient->setApiKey(MOLLIE_API_KEY);
    return new \Api\Payment\PaymentController($logger, $token, $mailManager, $mollieClient);
};
$container['Api\Reservation\ReservationController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager();
    $inventory = $c->get("Api\Inventory");
    $toolManager = new ToolManager($inventory, $logger);
    return new \Api\Reservation\ReservationController($logger, $token, $mailManager, $toolManager);
};
$container['Api\Token\TokenController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    return new \Api\Token\TokenController($logger, $token, $c);
};
$container['Api\Lending\LendingController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $toolManager = new ToolManager($c->get("Api\Inventory"), $logger);
    return new \Api\Lending\LendingController($logger, $token, $toolManager);
};
