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
	
	public function sendEnrolmentNotification($userEmail, $newUser) {
		$subject = "Nieuwe inschrijving";
		$body = "<div>Beste,<br><br>"
				. "<p>Via de website werd een aanvraag tot lidmaatschap geregistreerd<br>"
				. "Er werd een nieuwe gebruiker ". $newUser->firstname . " " . $newUser->lastname
				. " aangemaakt met status '" . $newUser->state . "' (user id: " . $newUser->user_id . ")<br>"
				. "Deze gebruiker koos ervoor om te betalen via " . $newUser->payment_mode . "<br>"
				. "Gelieve deze gebruiker te activeren van zodra het lidgeld ontvangen is</p>"
				. "Groetjes,<br> Admin.</div>";

		return $this->send($subject, $body, $userEmail);
	}
	
	public function sendEmailVerification($userId, $userName, $to, $token) {
		$subject = "Klusbib - Bevestig email adres";
		$link = PROJECT_HOME . "auth/confirm/" . $userId . "?token=" . $token . "&email=" . $to . "&name=" . $userName;
		$body = "<h1>Welkom bij Klusbib</h1>"
				. "<div>Beste " . $userName . ",<br><br>"
				. "<p>Om uw inschrijving te vervolledigen dient je dit email adres te bevestigen door op onderstaande link te klikken:<br>"
				. "<a href='$link'>$link</a><br><br></p>"
				. "<p>Herken je deze actie niet, dan kan je dit bericht veilig negeren<br></p>"
				. "Groetjes,<br> Klusbib Team.</div>";
		return $this->send($subject, $body, $to);
		
	}
	
	public function sendPwdRecoveryMail($userId, $userName, $userEmail, $token) {
		$subject = "Paswoord Vergeten";
		$link = PROJECT_HOME . "auth/reset/" . $userId . "?token=" . $token . "&name=" . $userName;
		$body = "<div>" . $userName . ",<br><br>"
		. "<p>Klik op deze link om je paswoord te herstellen<br>"
		. "<a href='$link'>$link</a><br><br></p>"
		. "<p>Heb je geen paswoord reset aangevraagd, dan kan je dit bericht veilig negeren<br></p>"
		. "Groetjes,<br> Admin.</div>";
		
		return $this->send($subject, $body, $userEmail);
	}
	
	private function send($subject, $body, $to) {
		$this->message = '';
		
		$mail = $this->mailer;
		$mail->setLanguage('nl');
		
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
		$mail->AddAddress ( $to );
		$mail->Subject = $subject;
		$mail->MsgHTML ( $body );
		$mail->IsHTML ( true );

		if (! $mail->Send ()) {
			$this->message = 'Problem in Sending Email. Mailer Error: ' . $mail->ErrorInfo;
			return FALSE;
		} else {
			$this->message = 'Email verstuurd!';
			return TRUE;
		}
	}
	
	public function getLastMessage() {
		return $this->message;
	}
}
