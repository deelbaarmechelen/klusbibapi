<?php
use PHPUnit\Framework\TestCase;
use Api\Mail\MailManager;
use Tests\Mock\PHPMailerMock;
use Api\Model\User;
use Api\Model\Tool;
use Api\Model\Reservation;

require_once __DIR__ . '/../../test_env.php';

final class MailManagerTest extends TestCase
{
	public function testSendPwdRecoveryMail()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$token = "12345678901234567890";
		$result = $mailmgr->sendPwdRecoveryMail("11", "Test", "info@klusbib.be", $token);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Paswoord Vergeten", $get_sent->subject);
	}

	public function testSendEnrolmentNotification()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$newUser = new User();
		$newUser->user_id = 11;
		$newUser->firstName = "firstName";
		$newUser->lastName = "lastName";
		
		$result = $mailmgr->sendEnrolmentNotification("info@klusbib.be", $newUser);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Nieuwe inschrijving", $get_sent->subject);
	}
	public function testSendEmailVerification()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$token = "12345678901234567890";
		$result = $mailmgr->sendEmailVerification("11", "testUser", "info@klusbib.be", $token);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Klusbib - Bevestig email adres", $get_sent->subject);
	}
	public function testSendReservationRequest()
	{
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$user = new User();
		$tool = new Tool();
		$reservation = new Reservation();
		$reservation->startsAt = new DateTime();
		$reservation->endsAt = clone $reservation->startsAt;
		$reservation->endsAt->add(new DateInterval('P7D'));
		$result = $mailmgr->sendReservationRequest("info@klusbib.be", $user, $tool, $reservation);
		$this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
		$get_sent = $mailer->get_sent(0);
		$this->assertNotNull($get_sent);
		$this->assertEquals("Nieuwe reservatie", $get_sent->subject);
	}
    public function testSendRenewal()
    {
        $user = new UserTest();
        $user->email = "info@klusbib.be";
        $user->firstname = "tester";
        $user->lastname = "de mock";
        $user->membership_end_date = date('Y-m-d');
        $mailer = new PHPMailerMock ();
        $mailmgr = new MailManager($mailer);
        $result = $mailmgr->sendRenewal($user);
        $this->assertEquals("Email verstuurd!", $mailmgr->getLastMessage());
        $this->assertTrue($result);
        $get_sent = $mailer->get_sent(0);
        $this->assertNotNull($get_sent);
        $this->assertEquals("Klusbib lidmaatschap", $get_sent->subject);
        print_r($get_sent->body);
    }

}

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