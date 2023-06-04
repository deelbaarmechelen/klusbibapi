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

echo "Syncing users\n";
$userManager = new UserManager(SnipeitInventory::instance($logger), $logger, new \Api\Mail\MailManager(null, null, $logger));
if ($force) {
    $users = \Api\Model\Contact::all(); // sync all users
} else {
    $users = \Api\Model\Contact::outOfSync()->get(); // filter users to be synced
}
foreach($users as $user) {
    echo "Syncing user with id " . $user->id . "\n";
    $userManager->getById($user->id); // sync with inventory
    $user->last_sync_date = $today; // sync with lend engine
    $user->timestamps = false; // do not change updated_at column value
    $user->save();
}
// FIXME: also cleanup all users on inventory with employee_nbr not matching an active user on api
$userManager->validateInventoryUsers();

echo "Syncing tools\n";
$toolManager = new \Api\Tool\ToolManager(SnipeitInventory::instance($logger), $logger);
$tools = $toolManager->sync();

// For remaining models: just update last_sync_date to activate update trigger, which will take care of sync with Lend Engine
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

echo "Syncing payments\n";
if ($force) {
    $payments = \Api\Model\Payment::all(); // sync all
} else {
    $payments = \Api\Model\Payment::outOfSync()->get(); // filter objects to be synced
}
foreach($payments as $payment) {
    echo "Syncing payment with id " . $payment->payment_id . "\n";
    $payment->last_sync_date = $today;
    $payment->timestamps = false; // do not change updated_at column value
    $payment->save();
}

echo "Syncing deliveries\n";
if ($force) {
    $deliveries = \Api\Model\Delivery::all(); // sync all
} else {
    $deliveries = \Api\Model\Delivery::outOfSync()->get(); // filter objects to be synced
}
foreach($deliveries as $delivery) {
    echo "Syncing delivery with id " . $delivery->id . "\n";
    $delivery->last_sync_date = $today;
    $delivery->timestamps = false; // do not change updated_at column value
    $delivery->save();
}

