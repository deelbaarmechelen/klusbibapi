#!/app/.heroku/php/bin/php
<?php
# Deny access from the web
if (isset($_SERVER['REMOTE_ADDR'])) die('Permission denied.');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
echo "test renewal cron";
$mailmgr = new \Api\Mail\MailManager();
$user = new UserTest();
$user->email = "bernard@klusbib.be";
$user->firstname = "tester";
$user->lastname = "de mock";
$user->membership_end_date = date('Y-m-d');

$mailmgr->sendRenewal($user);
echo "end of renewal cron";

class UserTest {
    //['user_id', 'state', 'firstname', 'lastname', 'role', 'email',
    //'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'city',
    //'phone', 'mobile', 'registration_number', 'payment_mode', 'created_at', 'updated_at'
    public $firstname;
    public $lastname;
    public $role;
    public $email;
    public $membership_start_date;
    public $membership_end_date;
}