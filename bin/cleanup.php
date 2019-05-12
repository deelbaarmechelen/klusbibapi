#!/app/.heroku/php/bin/php
<?php

use Api\Model\ReservationState;

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
echo "Start cleanup cron\n";

$lastYear = date('Y-m-d' . ' 00:00:00', strtotime("-1 year"));

// Delete reservations older than 1 year
echo "selecting reservations older than 1 year (on $lastYear)\n";
$reservations = \Api\Model\Reservation::whereDate('startsAt' , '<', $lastYear)->get();
echo "selected reservations: " . count($reservations) . "\n";

foreach ($reservations as $reservation) {
    echo "Removal required for reservation $reservation->reservation_id\n";
    echo "state: " . $reservation->state . "\n";
    echo "reservation start: " . $reservation->startsAt . "\n";
    echo "reservation end: " . $reservation->endsAt . "\n";
    $reservation->state = ReservationState::DELETED;
    $reservation->save();
}

$requestedCount = \Api\Model\Reservation::where('state', ReservationState::REQUESTED)->count();
$confirmedCount = \Api\Model\Reservation::where('state', ReservationState::CONFIRMED)->count();
$cancelledCount = \Api\Model\Reservation::where('state', ReservationState::CANCELLED)->count();
$closedCount = \Api\Model\Reservation::where('state', ReservationState::CLOSED)->count();
$deletedCount = \Api\Model\Reservation::where('state', ReservationState::DELETED)->count();
echo "Requested reservations: $requestedCount\n";
echo "Confirmed reservations: $confirmedCount\n";
echo "Cancelled reservations: $cancelledCount\n";
echo "Closed reservations: $closedCount\n";
echo "Deleted reservations: $deletedCount\n";


// Delete users when membership expired for more than 1 year
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
echo "End of cleanup cron\n";
