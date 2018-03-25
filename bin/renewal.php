#!/app/.heroku/php/bin/php
<?php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Start renewal cron\n";
$mailmgr = new \Api\Mail\MailManager();

$fromDate = date('Y-m-d' . ' 00:00:00', strtotime("+1 week"));
$toDate   = date('Y-m-d' . ' 23:59:59', strtotime("+1 week"));
echo "selecting active users between $fromDate and $toDate \n";
$users = \Api\Model\User::active()->whereBetween('membership_end_date' , [$fromDate, $toDate])->get();
echo "selected users: " . count($users) . "\n";
// TODO: log renewal events
foreach ($users as $user) {
    echo "renewal required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
    $mailmgr->sendRenewal($user);
}
echo "End of renewal cron\n";
