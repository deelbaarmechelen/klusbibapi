<?php
// DIC configuration
use Api\Token\Token;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\HttpBasicAuthentication;
use Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Tuupola\Base62;
use Api\Mail\MailManager;
use Api\User\UserController;
use Api\User\UserManager;
use Api\Tool\ToolManager;
use Api\Tool\ToolController;
use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Statistics\StatController;
use Tests\Mock\InventoryMock;
use Tests\Mock\PHPMailerMock;
use Tests\Mock\MollieApiClientMock;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$container = $app->getContainer();

$container->set('settings', function (ContainerInterface $c) {
    $settings = require __DIR__ . '/test_settings.php';
    return $settings['settings'];
});

// Register Twig View helper
$container->set('view', function (ContainerInterface $c) {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
//    $this->twig = new \Twig\Environment($loader, array(
//        'cache' => __DIR__ . '/../cache'
//    ));
    $view = new \Slim\Views\Twig($loader);
    $view->parserOptions = array(
        'cache' => __DIR__ . '/../cache'
    );

    // Instantiate and add Slim specific extension
//    $router = $c->get('router');
//    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
//    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));
    $view->parserExtensions = array(
        new \Slim\Views\TwigExtension()
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
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
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

// force creation of token with required access rights
$container->set('token', function (ContainerInterface $container) {
	$token = new Token;
	$now = new \DateTime();
	$future = new \DateTime("now +2 hours");

	$base62 = new Base62;
	$jti = $base62->encode(random_bytes(16));
	$scopes = [
			"tools.create",
			"tools.read",
			"tools.update",
			"tools.delete",
			"tools.list",
			"tools.all",
			"reservations.create",
			"reservations.read",
			"reservations.update",
			"reservations.delete",
			"reservations.list",
			"reservations.all",
			"consumers.create",
			"consumers.read",
			"consumers.update",
			"consumers.delete",
			"consumers.list",
			"consumers.all",
			"users.create",
			"users.read",
			"users.update",
			"users.delete",
			"users.list",
			"users.all"
	];
	$payload = [
			"iat" => $now->getTimeStamp(), 		// issued at
			"exp" => $future->getTimeStamp(),	// expiration
			"jti" => $jti,						// JWT ID
			"sub" => 'test',
			"scope" => $scopes
	];
	$token->decoded = json_decode(json_encode($payload));
	return $token;
});

$container->set('user', function (ContainerInterface $container) {
	return "";
});

$container->set('HttpBasicAuthentication', function (ContainerInterface $container) {
	return new HttpBasicAuthentication([
			"path" => "/token",
			"secure" => false,
			"relaxed" => ["admin", "klusbib.deeleco"],
			"authenticator" => new PdoAuthenticator([
					"pdo" => $container->get('db'),
					"table" => "users",
					"user" => "email",
					"hash" => "hash"
			]),
			"before" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) use ($container) {
				$container->get('logger')->info("User " . $arguments['user'] . " authenticated");
				echo "User is " . $arguments["user"];
				$container->set('user', $arguments["user"]);
			}
	]);
});

$container->set("JwtAuthentication", function (ContainerInterface $container) {
	return new JwtAuthentication([
			"path" => "/",
			"ignore" => ["/token", "/welcome", "/upload", "/enrolment", "/payments", "/stats",
                "/auth/reset", "/auth/verifyemail"],
			"secret" => getenv("JWT_SECRET"),
			"logger" => $container->get("logger"),
			"secure" => false, // FIXME: enable HTTPS and switch this to true
			"relaxed" => ["admin"], // list hosts allowed without HTTPS for DEV
			"rules" => [
                new \Api\Middleware\Jwt\JwtCustomRule([
                        "getignore" => ["/tools", "/consumers"]
                ]),
                new RequestMethodRule([
                        "ignore" => ["OPTIONS"]
                ])
			],
			"error" => function (ResponseInterface $response, $arguments) {
	            echo "jwt error args: " . json_encode($arguments) . "\n";
                $data["status"] = "error";
                $data["message"] = $arguments["message"];
                return $response
                    ->withHeader("Content-Type", "application/json")
                    ->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			},
			"before" => function (ServerRequestInterface $request, $arguments) use ($container) {
                $container->get('logger')->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
// 				$container->get("token")->hydrate($arguments["decoded"]);
			}
	]);
});

$container->set("Api\Enrolment\EnrolmentFactory", function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    $inventory = $container->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $userMgr = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Enrolment\EnrolmentFactory(new MailManager(new PHPMailerMock()),
        new MollieApiClientMock(), $userMgr);
});
$container->set("Api\Inventory", function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return new InventoryMock(null, null, $logger);
});

$container->set('Api\User\UserController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    return new UserController($logger, new UserManager($inventory, $logger, $mailMgr),new ToolManager($inventory, $logger),$token);
});

$container->set('Api\Tool\ToolController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new ToolController($logger, new ToolManager($inventory, $logger), $token);
});

$container->set('Api\Tool\AccessoryController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $logger->debug("creating accessory controller");
    $inventory = $c->get("Api\Inventory");
    return new \Api\Tool\AccessoryController($logger, new ToolManager($inventory, $logger),$token);
});
$container->set('Api\Statistics\StatController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    return new StatController($inventory, $logger);
});
$container->set('Api\Consumer\ConsumerController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    $token = $c->get("token"); // retrieve the 'token' from the container
    return new \Api\Consumer\ConsumerController($inventory, $logger, $token);
});
$container->set('Api\Authentication\PasswordResetController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    return new \Api\Authentication\PasswordResetController($logger);
});
$container->set('Api\Authentication\VerifyEmailController', function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $jwtAuthentication = $c->get("JwtAuthentication");
    $view = $c->get("view");
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
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token");
    $mailManager = new MailManager(new PHPMailerMock());
    $mollieClient = new MollieApiClientMock();
    $mollieClient->setApiKey(MOLLIE_API_KEY);
    return new \Api\Payment\PaymentController($logger, $token, $mailManager, $mollieClient);
});
$container->set('Api\Reservation\ReservationController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(new PHPMailerMock());
    $inventory = $c->get("Api\Inventory");
    $toolManager =  new ToolManager($inventory, $logger);
    return new \Api\Reservation\ReservationController($logger, $token, $mailManager, $toolManager);
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
$container->set('Api\Delivery\DeliveryController', function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(null, null, $logger);
    return new \Api\Delivery\DeliveryController($logger, $token, $mailManager);
});