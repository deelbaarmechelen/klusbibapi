<?php
$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();

use \AD7six\Dsn\Dsn;

$url = getenv('DATABASE_URL');
if (isset($url) && !empty($url)) {
	$dsn = Dsn::parse($url);
	$host = $dsn->host;
	$database = substr($dsn->path, 1);
	$user = $dsn->user;
	$pass = $dsn->pass;
	$port = $dsn->port;

}
else {
	$host = getenv('DB_HOST');
	$database = getenv('DB_NAME');
	$user = getenv('DB_USER');
	$pass = getenv('DB_PASS');
	$port = getenv('DB_PORT');
}
