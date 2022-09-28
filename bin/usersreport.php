#!/app/.heroku/php/bin/php
<?php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/env.php';
$settings = require __DIR__ . '/../app/settings.php';
echo "Generating report on users\n";

// Expected result:
// - email with CSV or PDF
// - listing all active users with id, name, email, phone, start and enddate membership (order by name)
// - listing all expired users with id, name, email, start and enddate membership (order by  name)
// - preparing enrolment printed form for online enrolled users (to complete enrolments map)
// - payments follow  up: list of users with date + mode of first payment, renewal(s) (track payments within klusbibapi?)

$mailmgr = new \Api\Mail\MailManager();
$active_users = \Api\Model\Contact::active()->members()->orderBy('first_name', 'asc')->get();
$expired_users = \Api\Model\Contact::expired()->orderBy('first_name', 'asc')->get();
$pending_users = \Api\Model\Contact::pending()->orderBy('first_name', 'asc')->get();
echo "Active users count: " . count($active_users) . "\n";
echo "calling sendUsersReport\n";
try {
    $mailmgr->sendUsersReport($active_users, $expired_users, $pending_users);
} catch (\Exception $ex) {
    echo "A problem occurred $ex";
}
//}
echo "End of users report cron\n";
