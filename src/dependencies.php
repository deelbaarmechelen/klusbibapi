<?php
// DIC configuration
use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;
use Api\Token;
use Api\Mail\MailManager;
use Api\User\UserController;
use Api\Tool\ToolController;
use Api\User\UserManager;
use Api\Tool\ToolManager;
use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Statistics\StatController;
use Mollie\Api\MollieApiClient;

// Fetch DI Container
$container = $app->getContainer();

// Register Twig View helper
$container['view'] = function ($c) {
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
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// PDO
$container['db'] = function ($c) {
	$db = $c['settings']['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
			$db['user'], $db['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};

$container["token"] = function ($container) {
	return new Token;
};

$container["user"] = function ($container) {
	return "";
};

$container["HttpBasicAuthentication"] = function ($container) {
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
			"callback" => function ($request, $response, $arguments) use ($container) {
				$container["user"] = $arguments["user"];
// 				print_r($arguments);
// 				print_r($container["user"]);
			}
	]);
};

$container["JwtAuthentication"] = function ($container) {
	return new JwtAuthentication([
			"path" => "/",
			"passthrough" => ["/token", "/welcome", "/upload", "/enrolment", "/payments", "/stats",
                "/auth/reset", "/auth/verifyemail"],
			"secret" => getenv("JWT_SECRET"),
			"logger" => $container["logger"],
//			"secure" => (APP_ENV == "development" ? false : true), // force HTTPS for production
			"secure" => false, // disable -> scheme not always correctly set on request!
			"relaxed" => ["admin"], // list hosts allowed without HTTPS for DEV
			"error" => function ($request, $response, $arguments) {
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
			"callback" => function ($request, $response, $arguments) use ($container) {
				$container['logger']->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
				$container["token"]->hydrate($arguments["decoded"]);
			}
	]);
};

$container["enrolmentFactory"] = function ($container) {
    return new \Api\Enrolment\EnrolmentFactory(new MailManager(), new MollieApiClient());
};
$container["Api\Inventory"] = function ($container) {
    $logger = $container->get("logger"); // retrieve the 'logger' from the container
    return SnipeitInventory::instance($logger);
};

$container['Api\User\UserController'] = function($c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new UserController($logger, new UserManager($inventory, $logger),$token);
};

$container['Api\Tool\ToolController'] = function($c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $token = $c->get("token"); // retrieve the 'token' from the container
    $inventory = $c->get("Api\Inventory");
    return new ToolController($logger, new ToolManager($inventory, $logger),$token);
};
$container['Api\Statistics\StatController'] = function($c) {
    $logger = $c->get("logger"); // retrieve the 'logger' from the container
    $inventory = $c->get("Api\Inventory");
    return new StatController($inventory, $logger);
};
