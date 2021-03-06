#!/app/.heroku/php/bin/php
<?php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

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
    if ($user->activeMembership()->exists()) {
        $membership = $user->activeMembership()->first();
        $membership->status = \Api\Model\Membership::STATUS_EXPIRED;
        $membership->save();
        $user->state = \Api\Model\UserState::EXPIRED;
        $user->save();
    }
}

$activeCount = \Api\Model\User::active()->members()->count();
$expiredCount = \Api\Model\User::expired()->count();
$deletedCount = \Api\Model\User::isDeleted()->count();
echo "Active users: $activeCount\n";
echo "Expired users: $expiredCount\n";
echo "Deleted users: $deletedCount\n";

echo "End of expiry cron\n";
