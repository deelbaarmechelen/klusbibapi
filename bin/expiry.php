#!/app/.heroku/php/bin/php
<?php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');
//
//if (isset($argc)) {
//    for ($i = 0; $i < $argc; $i++) {
//        echo "Argument #" . $i . " - " . $argv[$i] . "\n";
//    }
//}
//else {
//    echo "argc and argv disabled\n";
//}

$val = getopt("d", ["delete"]);

$delete = false;
if (isset($val["delete"]) || isset($val["d"])) {
    echo "Effective delete enabled\n";
    $delete = true;
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Start expiry cron\n";
// deactivate users when membership expired for more than 1 week
$lastWeek = date('Y-m-d' . ' 00:00:00', strtotime("-1 week"));
echo "selecting active users with expired membership (on $lastWeek)\n";
$users = \Api\Model\User::active()->notAdmin()->whereDate('membership_end_date' , '<', $lastWeek)->get();
echo "selected users: " . count($users) . "\n";

foreach ($users as $user) {
    echo "Expiration required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
    $user->state = 'EXPIRED';
    $user->save();
}

// Delete users when membership expired for more than 1 year
$lastYear = date('Y-m-d' . ' 00:00:00', strtotime("-1 year"));
echo "selecting expired users for more than 1 year (on $lastYear)\n";
$users = \Api\Model\User::expired()->notAdmin()->whereDate('membership_end_date' , '<', $lastYear)->get();
echo "selected users: " . count($users) . "\n";

foreach ($users as $user) {
    echo "Removal required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
    $user->state = 'DELETED';
    $user->save();
}
$activeCount = \Api\Model\User::active()->members()->count();
$expiredCount = \Api\Model\User::where('state', 'EXPIRED')->count();
$deletedCount = \Api\Model\User::where('state', 'DELETED')->count();
echo "Active users: $activeCount\n";
echo "Expired users: $expiredCount\n";
echo "Deleted users: $deletedCount\n";

if ($delete) {
    $usersToDelete = \Api\Model\User::where('state', '=', \Api\Model\UserState::DELETED)->get();
    foreach ($usersToDelete as $user) {
        echo "Real delete for user $user->user_id\n";
        echo "name: " . $user->firstname . " " . $user->lastname . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        $user->delete();
    }
}
echo "End of expiry cron\n";
