<?php

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
$host = parse_url($url, PHP_URL_HOST);
$database = substr(parse_url($url, PHP_URL_PATH), 1);
$user = parse_url($url, PHP_URL_USER);
$pass = parse_url($url, PHP_URL_PASS);
$port = parse_url($url, PHP_URL_PORT);

require __DIR__ . '/tests/test_env.php';

$config = array(
		"paths" => array(
				"migrations" => "%%PHINX_CONFIG_DIR%%/db/migrations",
				"seeds" => "%%PHINX_CONFIG_DIR%%/db/seeds"
		),
		"templates" => array(
				"file" => "%%PHINX_CONFIG_DIR%%/templates/migration.template.php.dist",
		),
        "migration_base_class" => "\AbstractCapsuleMigration",
// 		'migration_base_class' => '\Api\Migration\Migration',
//        "seeder_base_class" => "\AbstractCapsuleSeeder",
		"environments" => array(
				"default_migration_table" => "phinxlog",
				"default_database" => "dev",
				"dev" => array(
						"adapter" => "mysql",
						"host" => parse_url($url, PHP_URL_HOST),
						"name" => $database,
						"user" => parse_url($url, PHP_URL_USER),
						"pass" => parse_url($url, PHP_URL_PASS),
						"port" => parse_url($url, PHP_URL_PORT)
				),
				"test" => array(
						"adapter" => "mysql",
                        "host" => parse_url($urltst, PHP_URL_HOST),
                        "name" => $databasetst,
                        "user" => parse_url($urltst, PHP_URL_USER),
                        "pass" => parse_url($urltst, PHP_URL_PASS),
                        "port" => parse_url($urltst, PHP_URL_PORT)
				),
				"ci" => array(
						"adapter" => "mysql",
						"host" => $host,
						"name" => $database,
						"user" => $user,
						"pass" => $pass
				),
				"dokku" => array(
						"adapter" => "mysql",
                        "host" => parse_url($url, PHP_URL_HOST),
                        "name" => $database,
                        "user" => parse_url($url, PHP_URL_USER),
                        "pass" => parse_url($url, PHP_URL_PASS),
                        "port" => parse_url($url, PHP_URL_PORT)
				)
		)
);
$host = $config["environments"]["dev"]["host"];
$database = substr(parse_url($url, PHP_URL_PATH), 1);
$user = parse_url($url, PHP_URL_USER);
$pass = parse_url($url, PHP_URL_PASS);
$port = parse_url($url, PHP_URL_PORT);

$settings = require __DIR__ . '/src/settings.php';

return $config;
