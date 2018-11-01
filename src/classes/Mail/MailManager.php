<?php
namespace Api\Mail;

use PHPMailer;
use Twig_Environment;
use DateTime;
use DateInterval;

class MailManager {
	
	private $message;
	private $mailer;
    private $twig;

	function __construct(PHPMailer $mailer = null, Twig_Environment $twig = null) {
		if (is_null($mailer)) {
			$this->mailer = new PHPMailer ();
		} else {
			$this->mailer = $mailer;
		}
        $this->twig = $twig;
		if (is_null($this->twig)) {
            $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../../../templates');
            $this->twig = new Twig_Environment($loader, array(
                // 'cache' => '/path/to/compilation_cache',
            ));
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
				. "<p>Om uw inschrijving te vervolledigen dien je dit email adres te bevestigen door op onderstaande link te klikken:<br>"
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
	
	public function sendReservationRequest($to, $user, $tool, $reservation) {
		$subject = "Nieuwe reservatie";
		if (empty($tool->code)) {
			$toolCode = "zonder code (Naam/beschrijving/merk/type: " 
					. $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
		} else {
			$toolCode = "met code " . $tool->code;
		}
		$body = "<div>Beste,<br><br>"
				. "<p>Via de website werd een aanvraag voor een reservatie geregistreerd<br>"
				. "Gebruiker ". $user->firstname . " " . $user->lastname
				. " (user id: " . $user->user_id . ") "
				. "wenst toestel " . $toolCode . " te reserveren van " 
				. $reservation->startsAt->format('Y-m-d') . " tot " . $reservation->endsAt->format('Y-m-d') . "</p>"
				. "Groetjes,<br> Admin.</div>";
		return $this->send($subject, $body, $to);
	}

    public function sendRenewal($user) {
        $endDate = DateTime::createFromFormat('Y-m-d',$user->membership_end_date);
	    $pivotDate = DateTime::createFromFormat('Y-m-d', date('Y') . '-07-01');
	    $membership_year = $endDate->format('Y');
	    if ($endDate > $pivotDate) {
	        $nextYear = $endDate->add(new DateInterval('P1Y'));
            $membership_year = $membership_year . '-' . $nextYear->format('Y');
        }
        echo '$membership_year: ' . $membership_year;
        $parameters = array('user' => $user,
            'amount' => 20,
            'account' => 'BE79 5230 8088 4133',
            'membership_year' => $membership_year);
        return $this->sendTwigTemplate($user->email, 'renewal', $parameters);
    }

    public function sendUsersReport($active_users, $expired_users) {
	    $reportEmail = 'info@klusbib.be';
	    $current_day = date('Y-m-d');
        $parameters = array('active_users' => $active_users,
            'expired_users' => $expired_users,
            'current_day' => $current_day);
        return $this->sendTwigTemplate($reportEmail, 'users_report', $parameters);
    }

	protected function sendTwigTemplate($to, $identifier, $parameters = array()) {
        $template = $this->twig->loadTemplate('/mail/'.$identifier.'.twig');
        $subject  = $template->renderBlock('subject',   $parameters);
        $body = $template->renderBlock('body', $parameters);
        return $this->send($subject, $body, $to);
    }

	private function send($subject, $body, $to) {
		$this->message = '';
		
		$mail = $this->mailer;
		$mail->clearAllRecipients();
		$mail->setLanguage('nl');
		
		$mail->IsSMTP ();
		$mail->SMTPDebug = 0;
		// 		$mail->SMTPDebug = \SMTP::DEBUG_SERVER;
		$mail->SMTPAuth = TRUE;
		$mail->SMTPSecure = "tls";
		$mail->Port = MAIL_PORT;
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
