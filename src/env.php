<?php
if (file_exists(__DIR__ . '/../.env')) {
	$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
	$dotenv->load();
}

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

if (!defined('PROJECT_HOME')) define("PROJECT_HOME",getenv('PROJECT_HOME'));

if (!defined('MAIL_PORT')) define("MAIL_PORT", getenv('MAIL_PORT')); // smtp port number
if (!defined('MAIL_USERNAME')) define("MAIL_USERNAME", getenv('MAIL_USERNAME')); // smtp username
if (!defined('MAIL_PASSWORD')) define("MAIL_PASSWORD", getenv('MAIL_PASSWORD')); // smtp password
if (!defined('MAIL_HOST')) define("MAIL_HOST", getenv('MAIL_HOST')); // smtp host
if (!defined('MAILER')) define("MAILER", getenv('MAILER'));

if (!defined('SENDER_NAME')) define("SENDER_NAME", getenv('SENDER_NAME'));
if (!defined('SENDER_EMAIL')) define("SENDER_EMAIL", getenv('SENDER_EMAIL'));
if (!defined('ENROLMENT_NOTIF_EMAIL')) define("ENROLMENT_NOTIF_EMAIL", getenv('ENROLMENT_NOTIF_EMAIL'));
if (!defined('RESERVATION_NOTIF_EMAIL')) define("RESERVATION_NOTIF_EMAIL", getenv('RESERVATION_NOTIF_EMAIL'));

if (!defined('MOLLIE_API_KEY')) define("MOLLIE_API_KEY", getenv('MOLLIE_API_KEY'));
