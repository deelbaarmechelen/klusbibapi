<?php
use Api\Mail\MailManager;
use Api\Token\Token;
// Aanpassing einddatum lidmaatschap wegens Corona sluiting
// To run:
// on dokku: dokku --rm run api /app/.heroku/php/bin/php bin/extend_memberships.php
// on local system: php bin/extend_memberships.php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
$settings = require __DIR__ . '/../src/settings.php';
echo "Extend memberships with 1 month\n";

echo "selecting active members\n";
$users = \Api\Model\User::members()->active()->get();
echo "selected users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "membership extend required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    $newMembershipEnd = DateTime::createFromFormat('Y-m-d', $user->membership_end_date);
    if ($newMembershipEnd != FALSE) {
        $newMembershipEnd->add(new DateInterval('P1M'));
        $user->membership_end_date = $newMembershipEnd->format('Y-m-d');
        echo "membership new end: " . $user->membership_end_date . "\n\n";
        $user->save();
    } else {
        echo "No membership end update needed!\n\n";
    }
}
$users = \Api\Model\User::expired()->members()->whereDate('membership_end_date', '<=', date('Y-m-d'))->get();
foreach ($users as $user) {
    echo "membership extend required for user $user->user_id\n";
    echo "name: " . $user->firstname . " " . $user->lastname . "\n";
    echo "state: " . $user->state . "\n";
    echo "membership start: " . $user->membership_start_date . "\n";
    echo "membership end: " . $user->membership_end_date . "\n";
    $newMembershipEnd = DateTime::createFromFormat('Y-m-d', $user->membership_end_date);

    if ($newMembershipEnd != FALSE) {
        $newMembershipEnd->add(new DateInterval('P1M'));
        $user->membership_end_date = $newMembershipEnd->format('Y-m-d');
        echo "membership new end: " . $user->membership_end_date . "\n\n";
    } else {
        echo "No membership end update needed!\n\n";
    }
    $user->state = 'ACTIVE';
    $user->save();
}

echo "End of extend memberships\n";

