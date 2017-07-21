<?php
namespace Api\Mail;

use \PHPMailer;

class MailManager {
	
	private $message;
	private $mailer;
	
	function __construct(PHPMailer $mailer = null) {
		if (is_null($mailer)) {
			$this->mailer = new PHPMailer ();
		} else {
			$this->mailer = $mailer;
		}
	}
	
	public function sendPwdRecoveryMail($userId, $userName, $userEmail, $token) {
		$this->message = '';
		
		$mail = $this->mailer;
		$mail->setLanguage('nl');
		$link = PROJECT_HOME . "auth/reset/" . $userId . "?token=" . $token . "&name=" . $userName;
		$emailBody = "<div>" . $userName . ",<br><br>"
		. "<p>Click this link to recover your password<br>"
		. "<a href='$link'>$link</a><br><br></p>".
		"Regards,<br> Admin.</div>";
		
		$mail->IsSMTP ();
		$mail->SMTPDebug = 0;
// 		$mail->SMTPDebug = \SMTP::DEBUG_SERVER;
		$mail->SMTPAuth = TRUE;
		$mail->SMTPSecure = "tls";
		$mail->Port = PORT;
		$mail->Username = MAIL_USERNAME;
		$mail->Password = MAIL_PASSWORD;
		$mail->Host = MAIL_HOST;
		$mail->Mailer = MAILER;
		
		$mail->SetFrom ( SENDER_EMAIL, SENDER_NAME );
		$mail->AddReplyTo ( SENDER_EMAIL, SENDER_NAME );
		$mail->ReturnPath = SENDER_EMAIL;
		$mail->AddAddress ( $userEmail );
		$mail->Subject = "Paswoord Vergeten";
		$mail->MsgHTML ( $emailBody );
		$mail->IsHTML ( true );
		
		if (! $mail->Send ()) {
			$this->message = 'Problem in Sending Password Recovery Email. Mailer Error: ' . $mail->ErrorInfo;
			return FALSE;
		} else {
			$this->message = 'Email om paswoord te resetten werd verstuurd!';
			return TRUE;
		}
	}
	
	public function getLastMessage() {
		return $this->message;
	}
}
