#!/app/.heroku/php/bin/php
<?php
use Api\Mail\MailManager;
use Api\Token\Token;
use Api\Model\User;

# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');


$longopts  = array(
    "user:",     // Required value
    "date:",     // Required value
);
$options = getopt("", $longopts);

$delete = false;
if (isset($options["user"]) ) {
    echo "Renewal for single user\n";
    $singleuser = true;
    $useremail = $options["user"];
}

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Start renewal cron\n";
$mailmgr = new MailManager();
if ($singleuser) {
    $user = User::where('email', $useremail)->first();
    if (!$user) {
        echo "no user found with email " . $useremail . "\n";
    } else if ($user->state == \Api\Model\UserState::EXPIRED) {
        echo "renewal required for user $user->user_id\n";
        echo "name: " . $user->firstname . " " . $user->lastname . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        $today = new DateTime();
        $expiry = DateTime::createFromFormat('Y-m-d', $user->membership_end_date);
        $interval = $today->diff($expiry);
        $daysToExpiry = $interval->invert == 1 ? $interval->days * -1 : $intervale->days;
        echo "days to expiry: " . $daysToExpiry . "\n";
        $token = generateProfileToken($user);
        $mailmgr->sendRenewal($user, $daysToExpiry, $token);
    } else {
        echo "name: " . $user->firstname . " " . $user->lastname . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        echo "membership not yet expired -> nothing to be done\n";
    }

} else {
    echo "Renewal in 2 weeks\n";
    $fromDate = date('Y-m-d' . ' 00:00:00', strtotime("+2 weeks"));
    $toDate   = date('Y-m-d' . ' 23:59:59', strtotime("+2 weeks"));

    echo "selecting active users between $fromDate and $toDate \n";
    $users = User::active()->members()->whereBetween('membership_end_date', [$fromDate, $toDate])->get();
    sendRenewalReminder($users, $mailmgr, 14);

    echo "Renewal in 3 days\n";
    $fromDate = date('Y-m-d' . ' 00:00:00', strtotime("+3 days"));
    $toDate   = date('Y-m-d' . ' 23:59:59', strtotime("+3 days"));
    echo "selecting active users between $fromDate and $toDate \n";
    $users = User::active()->members()->whereBetween('membership_end_date', [$fromDate, $toDate])->get();

    sendRenewalReminder($users, $mailmgr,3);

    echo "Expired 7 days ago\n";
    $fromDate = date('Y-m-d' . ' 00:00:00', strtotime("-7 days"));
    $toDate   = date('Y-m-d' . ' 23:59:59', strtotime("-7 days"));
    echo "selecting active and expired users between $fromDate and $toDate \n";
    $users = User::active()->members()->whereBetween('membership_end_date', [$fromDate, $toDate])->get();
    sendRenewalReminder($users, $mailmgr,-7);
    $users = User::expired()->members()->whereBetween('membership_end_date', [$fromDate, $toDate])->get();
    sendRenewalReminder($users, $mailmgr,-7);
}

echo "End of renewal cron\n";

/**
 * @param $users
 * @param $mailmgr
 * @param $daysToExpiry
 */
function sendRenewalReminder($users, MailManager $mailmgr, $daysToExpiry)
{
    echo "selected users expiring in $daysToExpiry days: " . count($users) . "\n";
// TODO: log renewal events
    foreach ($users as $user) {
        echo "renewal required for user $user->user_id\n";
        echo "name: " . $user->firstname . " " . $user->lastname . "\n";
        echo "state: " . $user->state . "\n";
        echo "membership start: " . $user->membership_start_date . "\n";
        echo "membership end: " . $user->membership_end_date . "\n";
        echo "email: " . $user->email . "\n";
        $token = generateProfileToken($user);
        $mailmgr->sendRenewal($user, $daysToExpiry, $token);
    }
}
function generateProfileToken($user) {
    // generate temporary token allowing minimal user operations (renewal, read/update own user)
    $sub = $user->user_id;
    $requested_scopes = Token::allowedScopes($user->role);
    $scopes = array_filter($requested_scopes, function ($needle) {
        return in_array($needle, Token::emailLinkScopes());
    });
    $token = Token::generateToken($scopes, $sub, new DateTime("now +4 weeks"));
    echo "Token generated with scopes " . json_encode($scopes) . " and sub " .  json_encode($sub) . "\n";
    return $token;
}

