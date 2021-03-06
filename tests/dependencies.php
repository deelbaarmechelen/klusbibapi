<?php
// DIC configuration
use Api\Token\Token;
use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
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

// force creation of token with required access rights
$container["token"] = function (ContainerInterface $container) {
	$token = new Token;
	$now = new \DateTime();
	$future = new \DateTime("now +2 hours");
	
	$jti = Base62::encode(random_bytes(16));
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
};

$container["user"] = function (ContainerInterface $container) {
	return "";
};

$container["HttpBasicAuthentication"] = function (ContainerInterface $container) {
	return new HttpBasicAuthentication([
			"path" => "/token",
			"secure" => false,
			"relaxed" => ["admin", "klusbib.deeleco"],
			"authenticator" => new PdoAuthenticator([
					"pdo" => $container['db'],
					"table" => "users",
					"user" => "email",
					"hash" => "hash"
			]),
			"callback" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) use ($container) {
				$container['logger']->info("User " . $arguments['user'] . " authenticated");
				echo "User is " . $arguments["user"];
				$container["user"] = $arguments["user"];
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
			"secure" => false, // FIXME: enable HTTPS and switch this to true
			"relaxed" => ["admin", "klusbib.deeleco"], // list hosts allowed without HTTPS for DEV
			"rules" => [
                new \Api\Middleware\Jwt\JwtCustomRule([
                        "getpassthrough" => ["/tools", "/consumers", "/auth/confirm"]
                ]),
                new \Slim\Middleware\JwtAuthentication\RequestMethodRule([
                        "passthrough" => ["OPTIONS"]
                ])
			],
			"error" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) {
                $data["status"] = "error";
                $data["message"] = $arguments["message"];
                return $response
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			},
			"callback" => function (ServerRequestInterface $request, ResponseInterface $response, $arguments) use ($container) {
                $container['logger']->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
// 				$container["token"]->hydrate($arguments["decoded"]);
			}
	]);
};

$container["Api\Enrolment\EnrolmentFactory"] = function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    $inventory = $container->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $userMgr = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Enrolment\EnrolmentFactory(new MailManager(new PHPMailerMock()),
        new MollieApiClientMock(), $userMgr);
};
$container["Api\Inventory"] = function (ContainerInterface $container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return new InventoryMock(null, null, $logger);
};

$container['Api\User\UserController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    return new UserController($logger, new UserManager($inventory, $logger, $mailMgr),new ToolManager($inventory, $logger),$token);
};

$container['Api\Tool\ToolController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new ToolController($logger, new ToolManager($inventory, $logger), $token);
};

$container['Api\Tool\AccessoryController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $logger->debug("creating accessory controller");
    $inventory = $c->get("Api\Inventory");
    return new \Api\Tool\AccessoryController($logger, new ToolManager($inventory, $logger),$token);
};
$container['Api\Statistics\StatController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    return new StatController($inventory, $logger);
};
$container['Api\Consumer\ConsumerController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    $token = $c->get("token"); // retrieve the 'token' from the container
    return new \Api\Consumer\ConsumerController($inventory, $logger, $token);
};
$container['Api\Authentication\PasswordResetController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    return new \Api\Authentication\PasswordResetController($logger);
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
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token");
    $mailManager = new MailManager(new PHPMailerMock());
    $mollieClient = new MollieApiClientMock();
    $mollieClient->setApiKey(MOLLIE_API_KEY);
    return new \Api\Payment\PaymentController($logger, $token, $mailManager, $mollieClient);
};
$container['Api\Reservation\ReservationController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    $mailManager = new MailManager(new PHPMailerMock());
    $inventory = $c->get("Api\Inventory");
    $toolManager =  new ToolManager($inventory, $logger);
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
    $inventory = $c->get("Api\Inventory");
    $mailMgr = new MailManager(null, null, $logger);
    $toolManager = new ToolManager($inventory, $logger);
    $userManager = new UserManager($inventory, $logger, $mailMgr);
    return new \Api\Lending\LendingController($logger, $token, $toolManager, $userManager);
};
$container['Api\Delivery\DeliveryController'] = function(ContainerInterface $c) {
    $logger = $c->get("logger");
    $token = $c->get("token");
    return new \Api\Delivery\DeliveryController($logger, $token);
};