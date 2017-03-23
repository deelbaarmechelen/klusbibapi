<?php
// DIC configuration
use Api\Token;
use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

$container = $app->getContainer();

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
			"passthrough" => ["/token", "/welcome", "/upload"],
			"secret" => getenv("JWT_SECRET"),
			"logger" => $container["logger"],
			"secure" => false, // FIXME: enable HTTPS and switch this to true
			"relaxed" => ["admin", "klusbib.deeleco"], // list hosts allowed without HTTPS for DEV
			"error" => function ($request, $response, $arguments) {
				$data["status"] = "error";
				$data["message"] = $arguments["message"];
				return $response
					->withHeader("Content-Type", "application/json")
					->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
			},
			"rules" => [
// 					new RequestPathRule([
// 							"path" => "/api",
// 							"passthrough" => ["/api/token", "/api/ping"]
// 					]),
					new \Slim\Middleware\JwtAuthentication\RequestMethodRule([
							"passthrough" => ["OPTIONS", "GET"]
					])
			],
			"callback" => function ($request, $response, $arguments) use ($container) {
				$container["token"]->hydrate($arguments["decoded"]);
			}
	]);
};
