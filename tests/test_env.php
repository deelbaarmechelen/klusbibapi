<?php
if (file_exists(__DIR__ . '/../.env.dev')) {
    $dotenv = \Dotenv\Dotenv::createMutable(__DIR__ . '/../', '.env.dev');
    $envs = $dotenv->safeLoad();
}
if (file_exists(__DIR__ . '/../.env.test')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.test');
    $dotenv->load();
}

// define a defaultUrl to avoid warnings on undefined $dsn and dsntst variables
$defaultTstUrl = "mysql://root:@127.0.0.1:3306/lendenginetst";
$urltst = getenv('TEST_DATABASE_URL');
if (!isset($urltst) || empty($urltst)) {
	$urltst = $defaultTstUrl;
}
$hosttst = parse_url($urltst, PHP_URL_HOST);
$databasetst = substr(parse_url($urltst, PHP_URL_PATH), 1);
$usertst = parse_url($urltst, PHP_URL_USER);
$passtst = parse_url($urltst, PHP_URL_PASS);
$porttst = parse_url($urltst, PHP_URL_PORT);

defined('PROJECT_HOME') or define("PROJECT_HOME",$_ENV['PROJECT_HOME']);
defined('JWT_SECRET') or define("JWT_SECRET",$_ENV['JWT_SECRET']);
defined('APP_ENV') or define("APP_ENV",'development');

defined('MAIL_PORT') or define("MAIL_PORT", "26"); // smtp port number
defined('MAIL_USERNAME') or define("MAIL_USERNAME", "myUser"); // smtp username
defined('MAIL_PASSWORD') or define("MAIL_PASSWORD", "myPassword"); // smtp password
defined('MAIL_HOST') or define("MAIL_HOST", "localhost"); // smtp host
defined('MAILER') or define("MAILER", "sendmail");
defined('OAUTH_CLIENT_ID') or define("OAUTH_CLIENT_ID", "oauthclientid");
defined('OAUTH_CLIENT_SECRET') or define("OAUTH_CLIENT_SECRET", "oauthsecret");
defined('OAUTH_TOKEN') or define("OAUTH_TOKEN", "oauthtoken");

defined('SENDER_NAME') or define("SENDER_NAME", "Unit tester");
defined('SENDER_EMAIL') or define("SENDER_EMAIL", "test@klusbib.be");
if (!defined('REPLYTO_NAME')) define("REPLYTO_NAME", "ReplyTo tester");
if (!defined('REPLYTO_EMAIL')) define("REPLYTO_EMAIL", "replyto@klusbib.be");
if (!defined('SUPPORT_NOTIF_EMAIL')) define("SUPPORT_NOTIF_EMAIL", "support@klusbib.be");
defined('ENROLMENT_NOTIF_EMAIL') or define("ENROLMENT_NOTIF_EMAIL", "test@klusbib.be");
defined('RESERVATION_NOTIF_EMAIL') or define("RESERVATION_NOTIF_EMAIL", "test@klusbib.be");
if (!defined('DELIVERY_NOTIF_EMAIL')) define("DELIVERY_NOTIF_EMAIL", "delivery-test@klusbib.be");
defined('STROOM_NOTIF_EMAIL') or define("STROOM_NOTIF_EMAIL", "test@klusbib.be");

defined('MOLLIE_API_KEY') or define("MOLLIE_API_KEY", "test_EaVzmHexdwwThxbQp6qn3rqjDdDAbA");
if (!defined('INVENTORY_API_KEY')) define("INVENTORY_API_KEY", "dummy");
if (!defined('INVENTORY_URL')) define("INVENTORY_URL", "http://snipeit");
defined('TEST_INVENTORY_API_KEY') or define("TEST_INVENTORY_API_KEY",getenv('TEST_INVENTORY_API_KEY'));
defined('TEST_INVENTORY_URL') or define("TEST_INVENTORY_URL",getenv('TEST_INVENTORY_URL'));
defined('SSL_CERTIFICATE_VERIFICATION') or define("SSL_CERTIFICATE_VERIFICATION", "false");
