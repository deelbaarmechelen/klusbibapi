#!/app/.heroku/php/bin/php
<?php

use Api\Model\ReservationState;
use Api\Model\UserState;
use Api\Model\UserRole;

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
require __DIR__ . '/../app/env.php';
$settings = require __DIR__ . '/../app/settings.php';
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
$users = \Api\Model\Contact::expired()->notAdmin()->whereDate('membership_end_date' , '<', $lastYear)->get();
echo "selected expired users: " . count($users) . "\n";
markAsDeleted($users);

echo "selecting pending users for more than 1 year (on $lastYear)\n";
$pending_users = \Api\Model\Contact::pending()->notAdmin()->whereDate('membership_end_date' , '<', $lastYear)->get();
echo "selected pending users: " . count($pending_users) . "\n";
markAsDeleted($pending_users);

$activeCount = \Api\Model\Contact::active()->members()->count();
$expiredCount = \Api\Model\Contact::expired()->count();
$deletedCount = \Api\Model\Contact::isDeleted()->count();
echo "Active users: $activeCount\n";
echo "Expired users: $expiredCount\n";
echo "Deleted users: $deletedCount\n";

if ($delete) {
    $reservationsToDelete = \Api\Model\Reservation::isDeleted()->get();
    foreach ($reservationsToDelete as $reservation) {
        echo "Real delete for reservation $reservation->reservation_id\n";
        echo "state: " . $reservation->state . "\n";
        echo "user_id: " . $reservation->user_id . "\n";
        echo "tool_id: " . $reservation->tool_id . "\n";
        echo "reservation start: " . $reservation->startsAt . "\n";
        echo "reservation end: " . $reservation->endsAt . "\n";
        echo "comment: " . $reservation->comment . "\n";
        $reservation->delete();
    }
    $usersToDelete = \Api\Model\Contact::isDeleted()->get();
    foreach ($usersToDelete as $user) {
        echo "Archiving user $user->id\n";
        echo "name: " . $user->first_name . " " . $user->last_name . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        // delete causes integrity constraints -> replaced by anonymisation and archiving cfr behavior lend engine
        //  Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`klusbibdb`.`item_movement`, CONSTRAINT `FK_98D05D3C7AA06E72` FOREIGN KEY (`assigned_to_contact_id`) REFERENCES `contact` (`id`))
        //$user->delete();
        $user->first_name = "Anon";
        $user->last_name = "Anon";
        $user->address_line_1 = "-";
        $user->address_line_2 = "-";
        $user->address_line_3 = "-";
        $user->address_line_4 = "-";
        $user->telephone = "";
        $user->latitude = "";
        $user->longitude = "";
        $user->email = "";
        $user->email_canonical = "";
        $user->username = "";
        $user->username_canonical = "";
        $user->enabled = false;
        $user->is_active = false;
        $user->registration_number = "";
        $user->save();
    }
}
echo "End of cleanup cron\n";

/**
 * @param $users
 * @return nothing
 */
function markAsDeleted($users)
{
    foreach ($users as $user) {
        echo "Removal required for user $user->id\n";
        echo "name: " . $user->first_name . " " . $user->last_name . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        $toolsCount = \Api\Model\Tool::where('owner_id', '=', $user->id)->count();
        if ($toolsCount > 0) {
            // User donated tool(s) -> keep user active but switch role to supporter
            echo "Donated tools: " . $toolsCount . "\n";
            $user->state = UserState::ACTIVE;
            $user->role = UserRole::SUPPORTER;
        } else {
            // FIXME: should also cancel open loans? Should already be the case!
            // cancel membership
            if ($user->activeMembership()->exists()) {
                $membership = $user->activeMembership()->first();
                $membership->status = \Api\Model\MembershipState::STATUS_CANCELLED;
                $membership->save();
            }
            $user->state = UserState::DELETED;
        }
        $user->save();
    }
}
