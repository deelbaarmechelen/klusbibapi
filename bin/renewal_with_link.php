<?php
use Api\Mail\MailManager;
use Api\Token;
//Aanpassing lidgeld + aansporen tot hernieuwing lidmaatschap
//Hier willen we natuurlijk wel de leden van het eerste uur erbij betrekken en die 15 mensen die van plan waren om in te schrijven maar dat uiteindelijk niet voor elkaar kregen.
// Het zou ook fijn zijn om te polsen naar redenen voor eventueel niet verlengen van lidmaatschap
//=> Ik stel voor om een mailtje te sturen naar alle leden waarvan lidmaatschap vervalt voor 31 december 2018 + de 15 die inschrijving niet voltooiden
//Deadline: 16/12
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Generating renewal reminders\n";
$mailMgr = new MailManager();

$fromDate = new DateTime();
$fromDate->setDate(2018, 1, 1);
$toDate = new DateTime();
$toDate->setDate(2018, 12, 31);
echo "selecting members with expiration between " . $fromDate->format('Y-m-d') . " and " . $toDate->format('Y-m-d') ."\n";
$users = \Api\Model\User::members()->whereIn('state', ['ACTIVE', 'EXPIRED'])->whereBetween('membership_end_date' , [$fromDate, $toDate])->get();
echo "selected users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "renewal reminder for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
//    $token = generateProfileToken($user);
//    $result = $mailMgr->sendRenewalReminder($user, $token);

//    if (!$result) { // error in mail send
//        $error = $mailMgr->getLastMessage();
//        echo "Error from mail manager: $error\n";
//    }
    // TODO: remove when going live. Break assures only 1 user is processed in test mode
//    break;

}
echo "\n\n";
echo "selecting members with CHECK_PAYMENT state\n";
$users = \Api\Model\User::members()->where('state', 'CHECK_PAYMENT')
    ->where('membership_start_date', '<', '2018-12-01')->get();
echo "selected users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "enrolment unfinished for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
    $token = generateProfileToken($user);
    $result = $mailMgr->sendResumeEnrolmentReminder($user, $token);

    if (!$result) { // error in mail send
        $error = $mailMgr->getLastMessage();
        echo "Error from mail manager: $error\n";
    }
}

echo "End of renewal reminder\n";

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

