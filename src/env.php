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

define("PROJECT_HOME",getenv('PROJECT_HOME'));

define("MAIL_PORT", getenv('MAIL_PORT')); // smtp port number
define("MAIL_USERNAME", getenv('MAIL_USERNAME')); // smtp username
define("MAIL_PASSWORD", getenv('MAIL_PASSWORD')); // smtp password
define("MAIL_HOST", getenv('MAIL_HOST')); // smtp host
define("MAILER", getenv('MAILER'));

define("SENDER_NAME", getenv('SENDER_NAME'));
define("SENDER_EMAIL", getenv('SENDER_EMAIL'));
define("ENROLMENT_NOTIF_EMAIL", getenv('ENROLMENT_NOTIF_EMAIL'));
define("RESERVATION_NOTIF_EMAIL", getenv('RESERVATION_NOTIF_EMAIL'));
