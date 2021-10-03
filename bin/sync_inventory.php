#!/app/.heroku/php/bin/php
<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\User\UserManager;
use Api\Inventory\SnipeitInventory;
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
$logger_settings = $settings['settings']['logger'];

$logger = new Monolog\Logger($logger_settings['name']);
$logger->pushProcessor(new Monolog\Processor\UidProcessor());
$logger->pushHandler(new Monolog\Handler\RotatingFileHandler($logger_settings['path'], $logger_settings['maxFiles'], $logger_settings['level']));

echo "Syncing users\n";
$userManager = new UserManager(SnipeitInventory::instance($logger), $logger, new \Api\Mail\MailManager(null, null, $logger));
$users = \Api\Model\User::outOfSync()->get(); // filter users to be synced
foreach($users as $user) {
    echo "Syncing user with id " . $user->user_id . "\n";
    $userManager->getById($user->user_id);
}
echo "Syncing tools\n";
$toolManager = new \Api\Tool\ToolManager(SnipeitInventory::instance($logger), $logger);
$tools = $toolManager->sync();
