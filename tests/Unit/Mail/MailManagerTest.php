<?php
use PHPUnit\Framework\TestCase;
use Api\Mail\MailManager;
use PHPMailer;
use Tests\Mock\PHPMailerMock;

require __DIR__ . '/../../../src/env.php';

final class MailManagerTest extends TestCase
{
	public function testSendPwdReset()
	{
// 		$mailer = new PHPMailer ();
		$mailer = new PHPMailerMock ();
		$mailmgr = new MailManager($mailer);
		$result = $mailmgr->sendPwdRecoveryMail("Test", "info@klusbib.be");
		$this->assertEquals("Email om paswoord te resetten werd verstuurd!", $mailmgr->getLastMessage());
		$this->assertTrue($result);
	}
}