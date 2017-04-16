<?php
use \AD7six\Dsn\Dsn;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$url = getenv('DATABASE_URL');
if (isset($url) && !empty($url)) {
	$dsn = Dsn::parse($url);
	$host = $dsn->host;
	$database = substr($dsn->path, 1);
	$user = $dsn->user;
	$pass = $dsn->pass;
	$port = $dsn->port;
}
$urltst = getenv('TEST_DATABASE_URL');
if (isset($urltst) && !empty($urltst)) {
	$dsntst = Dsn::parse($urltst);
	$hosttst = $dsntst->host;
	$databasetst = substr($dsntst->path, 1);
	$usertst = $dsntst->user;
	$passtst = $dsntst->pass;
	$porttst = $dsntst->port;
}
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