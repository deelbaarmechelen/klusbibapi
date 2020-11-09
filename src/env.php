<?php
if (file_exists(__DIR__ . '/../.env')) {
	$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
	$dotenv->load();
}

use \AD7six\Dsn\Dsn;
$url = getenv('DATABASE_URL');
if (isset($url) && !empty($url)) {
//	$dsn = Dsn::parse($url);
//	$host = $dsn->host;
//	$database = substr($dsn->path, 1);
//	$user = $dsn->user;
//	$pass = $dsn->pass;
//	$port = $dsn->port;
    $host = parse_url($url, PHP_URL_HOST);
    $database = substr(parse_url($url, PHP_URL_PATH), 1);
    $user = parse_url($url, PHP_URL_USER);
    $pass = parse_url($url, PHP_URL_PASS);
    $port = parse_url($url, PHP_URL_PORT);

}
else {
	$host = getenv('DB_HOST');
	$database = getenv('DB_NAME');
	$user = getenv('DB_USER');
	$pass = getenv('DB_PASS');
	$port = getenv('DB_PORT');
}

if (!defined('APP_ENV')) {
    $appEnv = getenv('APP_ENV');
    define('APP_ENV',(isset($appEnv) ? $appEnv : 'production') ); // defaults to production
}
if (!defined('PROJECT_HOME')) define("PROJECT_HOME",getenv('PROJECT_HOME'));

if (!defined('MAIL_PORT')) define("MAIL_PORT", getenv('MAIL_PORT')); // smtp port number
if (!defined('MAIL_USERNAME')) define("MAIL_USERNAME", getenv('MAIL_USERNAME')); // smtp username
if (!defined('MAIL_PASSWORD')) define("MAIL_PASSWORD", getenv('MAIL_PASSWORD')); // smtp password
if (!defined('MAIL_HOST')) define("MAIL_HOST", getenv('MAIL_HOST')); // smtp host
if (!defined('MAILER')) define("MAILER", getenv('MAILER'));
if (!defined('OAUTH_CLIENT_ID')) define("OAUTH_CLIENT_ID", getenv('OAUTH_CLIENT_ID'));
if (!defined('OAUTH_CLIENT_SECRET')) define("OAUTH_CLIENT_SECRET", getenv('OAUTH_CLIENT_SECRET'));
if (!defined('OAUTH_TOKEN')) define("OAUTH_TOKEN", getenv('OAUTH_TOKEN'));

if (!defined('SENDER_NAME')) define("SENDER_NAME", getenv('SENDER_NAME'));
if (!defined('SENDER_EMAIL')) define("SENDER_EMAIL", getenv('SENDER_EMAIL'));
if (!defined('REPLYTO_NAME')) define("REPLYTO_NAME", getenv('REPLYTO_NAME'));
if (!defined('REPLYTO_EMAIL')) define("REPLYTO_EMAIL", getenv('REPLYTO_EMAIL'));
if (!defined('SUPPORT_NOTIF_EMAIL')) define("SUPPORT_NOTIF_EMAIL", getenv('SUPPORT_NOTIF_EMAIL'));
if (!defined('ENROLMENT_NOTIF_EMAIL')) define("ENROLMENT_NOTIF_EMAIL", getenv('ENROLMENT_NOTIF_EMAIL'));
if (!defined('RESERVATION_NOTIF_EMAIL')) define("RESERVATION_NOTIF_EMAIL", getenv('RESERVATION_NOTIF_EMAIL'));
if (!defined('STROOM_NOTIF_EMAIL')) define("STROOM_NOTIF_EMAIL", getenv('STROOM_NOTIF_EMAIL'));

if (!defined('MOLLIE_API_KEY')) define("MOLLIE_API_KEY", getenv('MOLLIE_API_KEY'));
if (!defined('INVENTORY_API_KEY')) define("INVENTORY_API_KEY", getenv('INVENTORY_API_KEY'));
if (!defined('INVENTORY_URL')) define("INVENTORY_URL", getenv('INVENTORY_URL'));
