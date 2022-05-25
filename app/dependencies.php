<?php
// DIC configuration
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\HttpBasicAuthentication;
use Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator;
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

$container->set('settings', function (ContainerInterface $c) {
    require __DIR__ . '/env.php';
    $settings = require __DIR__ . '/settings.php';
    return $settings['settings'];
});

// Register Twig View helper
$container->set('view', function (ContainerInterface $c) {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $view = new \Slim\Views\Twig($loader);
    $view->parserOptions = array(
        'cache' => __DIR__ . '/../cache'
    );

    // Instantiate and add Slim specific extension
//    $router = $c->get('router');
//    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
//    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));
    $view->parserExtensions = array(
        new \Slim\Views\TwigExtension(),
    );

    return $view;
});

// view renderer
$container->set('renderer', function (ContainerInterface $c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
});

// monolog
$container->set('logger', function (ContainerInterface $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
//    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    $logger->pushHandler(new Monolog\Handler\RotatingFileHandler($settings['path'], $settings['maxFiles'], $settings['level']));
    return $logger;
});

// PDO
$container->set('db', function (ContainerInterface $c) {
	$db = $c->get('settings')['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
			$db['user'], $db['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
});

// Configure error handler
$container->set('errorHandler', function (ContainerInterface $container) {
    return function (Psr\Http\Message\RequestInterface $request, Psr\Http\Message\ResponseInterface $response, $exception) use ($container) {
        $logger = $container->get("logger"); // retrieve the 'logger' from the container
        $logger->error("Unexpected error on request " . $request->getMethod() . " " . $request->getRequestTarget()
            . ". Body: " . $request->getBody()->read(100)
            . ($request->getBody()->getSize() > 100 ? "..." : ""));
        $logger->error($exception);
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Something went wrong!');
    };
});
$container->set('phpErrorHandler', function (ContainerInterface $container) {
    return function (Psr\Http\Message\RequestInterface $request, Psr\Http\Message\ResponseInterface $response, $exception) use ($container) {
        $logger = $container->get("logger"); // retrieve the 'logger' from the container
        $logger->error("PHP runtime error on request " . $request->getMethod() . " " . $request->getRequestTarget()
            . ". Body: " . $request->getBody()->read(100)
            . ($request->getBody()->getSize() > 100 ? "..." : ""));
        $logger->error($exception);
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Something went wrong!');
    };
});

$container->set("token", function (ContainerInterface $container) {
	return new Token;
});

$container->set("user", function (ContainerInterface $container) {
	return "";
});

$container->set("HttpBasicAuthentication", function (ContainerInterface $container) {
	return new HttpBasicAuthentication([
			"path" => "/token",
			"ignore" => "/token/guest",
			"secure" => false,
			"relaxed" => ["admin"],
			"authenticator" => new PdoAuthenticator([
					"pdo" => $container->get('db'),
					"table" => "users",
					"user" => "email",
					"hash" => "hash"
			]),
			"before" => function (ServerRequestInterface $request, $arguments) use ($container) {
				$container->set("user", $arguments["user"]);
// 				print_r($arguments);
// 				print_r($container->get("user"));
			}
	]);
});

$container->set("JwtAuthentication", function (ContainerInterface $container) {
	return new JwtAuthentication([
            "path" => "/",
			"secret" => getenv("JWT_SECRET"),
			"logger" => $container->get("logger"),
//			"secure" => (APP_ENV == "development" ? false : true), // force HTTPS for production
			"secure" => false, // disable -> scheme not always correctly set on request!
			"relaxed" => ["admin"], // list hosts allowed without HTTPS for DEV
			"error" => function (ResponseInterface $response, $arguments) {
				$data = array("error" => array( "status" => 401, "message" => $arguments["message"]));
				return $response
					->withHeader("Content-Type", "application/json")
					->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			},
			"rules" => [
                new \Api\Middleware\Jwt\JwtCustomRule([
//							"getignore" => ["/tools", "/consumers", "/auth/confirm"]
                        "getignore" => ["/tools", "/consumers"]
                ]),
                new RequestMethodRule([
                    "ignore" => ["OPTIONS"]
                ]),
                new JwtAuthentication\RequestPathRule([
                    "path" => "/",
                    "ignore" => ["/token", "/welcome", "/upload", "/enrolment", "/payments", "/stats",
                        "/auth/reset", "/auth/verifyemail"]
                ])
			],
			"before" => function (ServerRequestInterface $request, $arguments) use ($container) {
				$container->get('logger')->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
				$container->get("token")->hydrate($arguments["decoded"]);
			}
	]);
});

$container->set("Api\Enrolment\EnrolmentFactory", function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    $inventory = $container->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $userMgr = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Enrolment\EnrolmentFactory($mailMgr
        , new MollieApiClient(), $userMgr);
});
$container->set("Api\Inventory", function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return SnipeitInventory::instance($logger);
});

$container->set('Api\User\UserController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    return new UserController($logger, new UserManager($inventory, $logger, $mailMgr), new ToolManager($inventory, $logger),$token);
});

$container->set('Api\Tool\ToolController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new ToolController($logger, new ToolManager($inventory, $logger),$token);
});
$container->set('Api\Tool\AccessoryController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $logger->debug("creating accessory controller");
    $inventory = $c->get("Api\Inventory");
    return new \Api\Tool\AccessoryController($logger, new ToolManager($inventory, $logger),$token);
});
$container->set('Api\Consumer\ConsumerController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    $token = $c->get("token"); // retrieve the 'token' from the container
    return new \Api\Consumer\ConsumerController($inventory, $logger, $token);
});
$container->set('Api\Statistics\StatController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    return new StatController($inventory, $logger);
});
$container->set('Api\Authentication\PasswordResetController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $renderer = $c->get("renderer");
    return new \Api\Authentication\PasswordResetController($logger, $renderer);
});
$container->set('Api\Authentication\VerifyEmailController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $jwtAuthentication = $c->get("JwtAuthentication");
    $view = $c-> get("view");
    return new \Api\Authentication\VerifyEmailController($logger, $jwtAuthentication, $view);
});
$container->set('Api\Enrolment\EnrolmentController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $jwtAuthentication = $c->get("JwtAuthentication");
    $enrolmentFactory = $c->get("Api\Enrolment\EnrolmentFactory");
    $token = $c->get("token");
    return new \Api\Enrolment\EnrolmentController($logger, $enrolmentFactory, $jwtAuthentication, $token);
});
$container->set('Api\Events\EventsController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token");
    return new \Api\Events\EventsController($logger, $token);
});
$container->set('Api\Payment\PaymentController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(null, null, $logger);
    $mollieClient = new \Mollie\Api\MollieApiClient();
    $mollieClient->setApiKey(MOLLIE_API_KEY);
    return new \Api\Payment\PaymentController($logger, $token, $mailManager, $mollieClient);
});
$container->set('Api\Reservation\ReservationController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(null, null, $logger);
    $inventory = $c->get("Api\Inventory");
    $toolManager = new ToolManager($inventory, $logger);
    return new \Api\Reservation\ReservationController($logger, $token, $mailManager, $toolManager);
});
$container->set('Api\Delivery\DeliveryController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(null, null, $logger);
    return new \Api\Delivery\DeliveryController($logger, $token, $mailManager);
});
$container->set('Api\Token\TokenController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    return new \Api\Token\TokenController($logger, $token, $c);
});
$container->set('Api\Lending\LendingController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $toolManager = new ToolManager($inventory, $logger);
    $userManager = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Lending\LendingController($logger, $token, $toolManager, $userManager);
});
$container->set('Api\Membership\MembershipController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $userManager = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Membership\MembershipController($logger, $userManager, $token);
});
