#!/app/.heroku/php/bin/php
<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\User\UserManager;
use Api\Inventory\SnipeitInventory;
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

$val = getopt("f", ["force"]);

$force = false;
if (isset($val["force"]) || isset($val["f"])) {
    echo "Force sync enabled\n";
    $force = true;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/env.php';
$settings = require __DIR__ . '/../app/settings.php';
$logger_settings = $settings['settings']['logger'];

$logger = new Monolog\Logger($logger_settings['name']);
$logger->pushProcessor(new Monolog\Processor\UidProcessor());
$logger->pushHandler(new Monolog\Handler\RotatingFileHandler($logger_settings['path'], $logger_settings['maxFiles'], $logger_settings['level']));
$today = new DateTime();

// echo "Syncing loans\n";
// $mailManager = new \Api\Mail\MailManager(null, null, $logger);
// $loanManager = \Api\Loan\LoanManager::instance($logger, $mailManager);
// $loanManager->sync();

// For remaining models: just update last_sync_date to activate update trigger, which will take care of sync with Lend Engine
/*
echo "Syncing reservations\n";
if ($force) {
    $reservations = \Api\Model\Reservation::all(); // sync all
} else {
    $reservations = \Api\Model\Reservation::outOfSync()->get(); // filter objects to be synced
}
foreach($reservations as $reservation) {
    echo "Syncing reservation with id " . $reservation->reservation_id . "\n";
    $reservation->last_sync_date = $today;
    $reservation->timestamps = false; // do not change updated_at column value
    $reservation->save();
}

echo "Syncing lendings\n";
if ($force) {
    $lendings = \Api\Model\Lending::all(); // sync all
} else {
    $lendings = \Api\Model\Lending::outOfSync()->get(); // filter objects to be synced
}
foreach($lendings as $lending) {
    echo "Syncing lending with id " . $lending->lending_id . "\n";
    $lending->last_sync_date = $today;
    $lending->timestamps = false; // do not change updated_at column value
    $lending->save();
}
*/