<?php
use \AD7six\Dsn\Dsn;

if (file_exists(__DIR__ . '/.env')) {
	$dotenv = new Dotenv\Dotenv(__DIR__);
	$dotenv->load();
}
// define a defaultUrl to avoid warnings on undefined $dsn and dsntst variables
$defaultUrl = "mysql://root:@127.0.0.1:3306/klusbibapi";
$url = getenv('DATABASE_URL');
if (!isset($url) || empty($url)) {
	$url = $defaultUrl;
}
$dsn = Dsn::parse($url);
$host = $dsn->host;
$database = substr($dsn->path, 1);
$user = $dsn->user;
$pass = $dsn->pass;
$port = $dsn->port;


require __DIR__ . '/tests/test_env.php';

$settings = require __DIR__ . '/src/settings.php';

return array(
		"paths" => array(
				"migrations" => "%%PHINX_CONFIG_DIR%%/db/migrations",
				"seeds" => "%%PHINX_CONFIG_DIR%%/db/seeds"
		),
		"templates" => array(
				"file" => "%%PHINX_CONFIG_DIR%%/templates/migration.template.php.dist",
		),
		// 		'migration_base_class' => '\Api\Migration\Migration',
		"environments" => array(
				"default_migration_table" => "phinxlog",
				"default_database" => "dev",
				"dev" => array(
						"adapter" => "mysql",
						"host" => $dsn->host,
						"name" => $database,
						"user" => $dsn->user,
						"pass" => $dsn->pass,
						"port" => $dsn->port
				),
				"ut" => array(
						"adapter" => "sqlite",
						"memory" => true,
						"name" => ":memory:"
				),
				"test" => array(
						"adapter" => "mysql",
						"host" => $dsntst->host,
						"name" => $databasetst,
						"user" => $dsntst->user,
						"pass" => $dsntst->pass,
						"port" => $dsntst->port
				),
				"ci" => array(
						"adapter" => "mysql",
						"host" => $settings['settings']['db']['host'],
						"name" => $settings['settings']['db']['dbname'],
						"user" => $settings['settings']['db']['user'],
						"pass" => $settings['settings']['db']['pass']
				),
				"dokku" => array(
						"adapter" => "mysql",
						"host" => $dsn->host,
						"name" => $database,
						"user" => $dsn->user,
						"pass" => $dsn->pass,
						"port" => $dsn->port
				)
		)
);
