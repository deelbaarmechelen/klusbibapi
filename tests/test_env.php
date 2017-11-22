<?php
use \AD7six\Dsn\Dsn;

if (file_exists(__DIR__ . '/../.env')) {
	$dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
	$dotenv->load();
}

// define a defaultUrl to avoid warnings on undefined $dsn and dsntst variables
$defaultTstUrl = "mysql://root:@127.0.0.1:3306/klusbibapi_tst";
$urltst = getenv('TEST_DATABASE_URL');
if (!isset($urltst) || empty($urltst)) {
	$urltst = $defaultTstUrl;
}
$dsntst = Dsn::parse($urltst);
$hosttst = $dsntst->host;
$databasetst = substr($dsntst->path, 1);
$usertst = $dsntst->user;
$passtst = $dsntst->pass;
$porttst = $dsntst->port;

defined('PROJECT_HOME') or define("PROJECT_HOME",getenv('PROJECT_HOME'));

defined('MAIL_PORT') or define("MAIL_PORT", "26"); // smtp port number
defined('MAIL_USERNAME') or define("MAIL_USERNAME", "myUser"); // smtp username
defined('MAIL_PASSWORD') or define("MAIL_PASSWORD", "myPassword"); // smtp password
defined('MAIL_HOST') or define("MAIL_HOST", "localhost"); // smtp host
defined('MAILER') or define("MAILER", "sendmail");

defined('SENDER_NAME') or define("SENDER_NAME", "Unit tester");
defined('SENDER_EMAIL') or define("SENDER_EMAIL", "test@klusbib.be");
defined('ENROLMENT_NOTIF_EMAIL') or define("ENROLMENT_NOTIF_EMAIL", "test@klusbib.be");
defined('RESERVATION_NOTIF_EMAIL') or define("RESERVATION_NOTIF_EMAIL", "test@klusbib.be");
