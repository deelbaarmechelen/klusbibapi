<?php
//if (file_exists(__DIR__ . '/../.env')) {
//	$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
//	$dotenv->load();
//}

$url = $_ENV['DATABASE_URL'];
if (isset($url) && !empty($url)) {
    $host = parse_url($url, PHP_URL_HOST);
    $database = substr(parse_url($url, PHP_URL_PATH), 1);
    $user = parse_url($url, PHP_URL_USER);
    $pass = parse_url($url, PHP_URL_PASS);
    $port = parse_url($url, PHP_URL_PORT);
}
else {
	$host = $_ENV['DB_HOST'] ;
	$database = $_ENV['DB_NAME'] ;
	$user = $_ENV['DB_USER'] ;
	$pass = $_ENV['DB_PASS'] ;
	$port = $_ENV['DB_PORT'] ;
}

if (!defined('APP_ENV')) {
    $appEnv = $_ENV['APP_ENV'];
    define('APP_ENV',(isset($appEnv) ? $appEnv : 'production') ); // defaults to production
}
if (!defined('PROJECT_HOME')) define("PROJECT_HOME", $_ENV['PROJECT_HOME']);

if (!defined('MAIL_PORT')) define("MAIL_PORT", $_ENV['MAIL_PORT']); // smtp port number
if (!defined('MAIL_USERNAME')) define("MAIL_USERNAME", $_ENV['MAIL_USERNAME'] ); // smtp username
if (!defined('MAIL_PASSWORD')) define("MAIL_PASSWORD", $_ENV['MAIL_PASSWORD'] ); // smtp password
if (!defined('MAIL_HOST')) define("MAIL_HOST", $_ENV['MAIL_HOST']); // smtp host
// smtp auth type
if (!defined('MAIL_AUTH_TYPE')) {
    $authType = $_ENV['MAIL_AUTH_TYPE'] ;
    define("MAIL_AUTH_TYPE", $authType != false ? $authType : 'XOAUTH2');
}
if (!defined('MAILER')) define("MAILER", $_ENV['MAILER'] );
if (!defined('OAUTH_CLIENT_ID')) define("OAUTH_CLIENT_ID", $_ENV['OAUTH_CLIENT_ID'] );
if (!defined('OAUTH_CLIENT_SECRET')) define("OAUTH_CLIENT_SECRET", $_ENV['OAUTH_CLIENT_SECRET'] );
if (!defined('OAUTH_TOKEN')) define("OAUTH_TOKEN", $_ENV['OAUTH_TOKEN'] );

if (!defined('SENDER_NAME')) define("SENDER_NAME", $_ENV['SENDER_NAME'] );
if (!defined('SENDER_EMAIL')) define("SENDER_EMAIL", $_ENV['SENDER_EMAIL'] );
if (!defined('REPLYTO_NAME')) define("REPLYTO_NAME", $_ENV['REPLYTO_NAME'] );
if (!defined('REPLYTO_EMAIL')) define("REPLYTO_EMAIL", $_ENV['REPLYTO_EMAIL'] );
if (!defined('SUPPORT_NOTIF_EMAIL')) define("SUPPORT_NOTIF_EMAIL", $_ENV['SUPPORT_NOTIF_EMAIL'] );
if (!defined('ENROLMENT_NOTIF_EMAIL')) define("ENROLMENT_NOTIF_EMAIL", $_ENV['ENROLMENT_NOTIF_EMAIL'] );
if (!defined('RESERVATION_NOTIF_EMAIL')) define("RESERVATION_NOTIF_EMAIL", $_ENV['RESERVATION_NOTIF_EMAIL'] );
if (!defined('DELIVERY_NOTIF_EMAIL')) define("DELIVERY_NOTIF_EMAIL", $_ENV['DELIVERY_NOTIF_EMAIL'] );
if (!defined('STROOM_NOTIF_EMAIL')) define("STROOM_NOTIF_EMAIL", $_ENV['STROOM_NOTIF_EMAIL'] );

if (!defined('MOLLIE_API_KEY')) define("MOLLIE_API_KEY", $_ENV['MOLLIE_API_KEY'] );
if (!defined('INVENTORY_API_KEY')) define("INVENTORY_API_KEY", $_ENV['INVENTORY_API_KEY'] );
if (!defined('INVENTORY_URL')) define("INVENTORY_URL", $_ENV['INVENTORY_URL'] );
if (!defined('WEB_URL')) {
    $webUrl = $_ENV['WEB_URL'] ;
    define("WEB_URL", $webUrl != false ? $webUrl : 'https://www.klusbib.be');
}
if (!defined('LAST_TERMS_DATE')) {
    $lastTermsDate = $_ENV['LAST_TERMS_DATE'] ;
    define("LAST_TERMS_DATE", $lastTermsDate != false ? $lastTermsDate : '2019-12-02');
}
