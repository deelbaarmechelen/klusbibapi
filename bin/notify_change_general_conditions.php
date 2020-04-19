<?php
use Api\Mail\MailManager;
use Api\Token\Token;
//Aanpassing Klusbib afspraken / privacy verklaring voor GDPR
//Dit is in principe enkel van belang voor de actieve leden
//=> mailtje naar alle actieve leden met verwijzing naar nieuwe afspraken?
//    Deadline: Klusbib afspraken en privacy verklaring zouden afgewerkt en beschikbaar via website moeten zijn voor we de lidkaarten ronddelen -> 9/12?
//    Deadline: mailtje sturen: 16/12
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Send notifications for new general conditions\n";
$mailMgr = new MailManager();

echo "selecting active members\n";
$users = \Api\Model\User::members()->active()->get();
echo "selected users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "notification required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    echo "email: " . $user->email . "\n";
    $result = $mailMgr->sendNewGeneralConditionsNotification($user);

    if (!$result) { // error in mail send
        $error = $mailMgr->getLastMessage();
        echo "Error from mail manager: $error";
    }
}
echo "End of changed general conditions notifications\n";

