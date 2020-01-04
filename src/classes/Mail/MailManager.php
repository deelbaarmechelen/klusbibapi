<?php
namespace Api\Mail;

use Api\Model\User;
use Api\Token\Token;
use PHPMailer;
use Twig_Environment;
use DateTime;
use DateInterval;
use Api\Settings;

/**
 * Class MailManager
 * Triggers send of email messages
 * @package Api\Mail
 */
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
                'cache' => __DIR__ . '/../../../public/cache/twig_compilations',
                'auto_reload' => true
            ));
        }
    }

	public function sendEmailVerification($userId, $userName, $to, $token) {
		$link = PROJECT_HOME . "auth/confirm/" . $userId . "?token=" . $token . "&email=" . $to . "&name=" . $userName;
		$body = "<h1>Welkom bij Klusbib</h1>"
				. "<div>Beste " . $userName . ",<br><br>"
				. "<p>Om uw inschrijving te vervolledigen dien je dit email adres te bevestigen door op onderstaande link te klikken:<br>"
				. "<a href='$link'>$link</a><br><br></p>"
				. "<p>Herken je deze actie niet, dan kan je dit bericht veilig negeren<br></p>"
				. "Groetjes,<br> Klusbib Team.</div>";
        $parameters = array(
            'userName' => $userName,
            'link' => $link,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($to, 'email_verification', $parameters);
	}
	public function sendPwdRecoveryMail($userId, $userName, $userEmail, $token) {
		$link = PROJECT_HOME . "auth/reset/" . $userId . "?token=" . $token . "&name=" . $userName;
        $parameters = array(
            'userName' => $userName,
            'link' => $link,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($userEmail, 'password_recovery', $parameters);
	}
	public function sendReservationRequest($to, $user, $tool, $reservation) {
		if (empty($tool->code)) {
			$toolCode = "zonder code (Naam/beschrijving/merk/type: "
					. $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
		} else {
			$toolCode = "met code " . $tool->code;
		}
        $parameters = array(
            'user' => $user,
            'toolCode' => $toolCode,
            'reservation' => $reservation);
        return $this->sendTwigTemplate($to, 'reservation_request', $parameters);
	}
    // Send notification to Klusbib team
    public function sendEnrolmentNotification($userEmail, $newUser) {
        $parameters = array(
            'newUser' => $newUser);
        return $this->sendTwigTemplate($userEmail, 'enrolment_new_notif', $parameters);
    }

    /**
     * @param $userEmail email adres the notification should be sent to
     * @param $newUser newly created user in enrolment
     * @param null $token generated token to allow confirmation
     * @return bool
     */
    public function sendEnrolmentStroomNotification($userEmail, $newUser, $token = null) {
//        $link = PROJECT_HOME . "enrolment_confirm/" . $userId . "?token=" . $token . "&name=" . $userName;
        $parameters = array(
            'newUser' => $newUser,
//            'confirmLink' => $link
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($userEmail, 'enrolment_stroom_notif', $parameters);
    }
    public function sendEnrolmentSuccessNotification($userEmail, $newUser, $isRenewal = false) {
        $parameters = array(
            'newUser' => $newUser,
            'isRenewal' => $isRenewal);
        return $this->sendTwigTemplate($userEmail, 'enrolment_success_notif', $parameters);
    }
    public function sendEnrolmentFailedNotification($userEmail, $newUser, $payment, $isRenewal = false) {
        $parameters = array(
            'newUser' => $newUser,
            'payment' => $payment,
            'isRenewal' => $isRenewal);
        return $this->sendTwigTemplate($userEmail, 'enrolment_failed_notif', $parameters);
    }

    public function sendEnrolmentConfirmation(User $user, $paymentMode) {
        $scopes = ["auth.confirm"];
        $token = Token::generateToken($scopes, $user->user_id, new \DateTime("now +2 days"));
        $link = PROJECT_HOME . "auth/confirm/" . $user->user_id . "?token=" . $token . "&email=" . $user->email . "&name=" . $user->firstname;
        $membership_year = $this->getMembershipYear(date('Y-m-d'));
        $parameters = array(
            'user' => $user,
            'paymentMode' => $paymentMode,
            'account' => Settings::ACCOUNT_NBR,
            'amount' => Settings::ENROLMENT_AMOUNT,
            'membership_year' => $membership_year,
            'confirmEmail' => !$user->isEmailConfirmed(),
            'link' => $link,
            'enqueteLink' => Settings::ENQUETE_LINK,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($user->email, 'enrolment_confirmation', $parameters);
    }
    public function sendEnrolmentPaymentConfirmation($user, $paymentMode) {
        $parameters = array(
            'user' => $user,
            'paymentMode' => $paymentMode,
            'reservationEmail' => Settings::RESERVATION_EMAIL,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($user->email, 'enrolment_confirm_payment', $parameters);
    }

    /**
     * @param $user the user to which a renewal reminder should be sent
     * @param $daysToExpiry number of days before this users membership will expire.
     *          if negative, the membership has already expired
     * @param $token temporary token to access user profile without login
     * @return bool TRUE if message successfully sent
     */
    public function sendRenewal($user, $daysToExpiry, $token) {
        $membership_year = $this->getMembershipYear($user->membership_end_date);
        $link = Settings::PROFILE_LINK . $user->user_id . "?token=" . $token;
        $parameters = array('user' => $user,
            'profileLink' => $link,
            'amount' => Settings::RENEWAL_AMOUNT,
            'account' => Settings::ACCOUNT_NBR,
            'currentDate' => date('Y-m-d'),
            'emailLink' => Settings::EMAIL_LINK,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'evaluationLink' => Settings::EVALUATION_LINK,
            'membership_year' => $membership_year,
            'daysToExpiry' => $daysToExpiry);
        return $this->sendTwigTemplate($user->email, 'renewal', $parameters);
    }
    public function sendResumeEnrolmentReminder($user, $token) {
        echo "Resume enrolment reminder called with token $token\n";
        $membership_year = $this->getMembershipYear(date('Y-m-d'));
        $link = Settings::PROFILE_LINK . $user->user_id . "?token=" . $token;
        $parameters = array('user' => $user,
            'link' => $link,
            'amount' => Settings::RENEWAL_AMOUNT,
            'enrolmentAmount' => Settings::ENROLMENT_AMOUNT,
            'enrolmentLink' => Settings::ENROLMENT_LINK,
            'account' => Settings::ACCOUNT_NBR,
            'currentDate' => date('Y-m-d'),
            'emailLink' => Settings::EMAIL_LINK,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'evaluationLink' => Settings::EVALUATION_LINK,
            'membership_year' => $membership_year);
        return $this->sendTwigTemplate($user->email, 'resume_enrolment_reminder', $parameters);
    }
    public function sendRenewalConfirmation($user, $paymentMode) {
        $membership_year = $this->getMembershipYear($user->membership_end_date);
        $parameters = array(
            'user' => $user,
            'paymentMode' => $paymentMode,
            'account' => Settings::ACCOUNT_NBR,
            'amount' => Settings::RENEWAL_AMOUNT,
            'membership_year' => $membership_year,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        return $this->sendTwigTemplate($user->email, 'renewal_confirmation', $parameters);
    }

    public function sendUsersReport($active_users, $expired_users, $pending_users) {
	    $reportEmail = 'info@klusbib.be';
	    $current_day = date('Y-m-d');
        $parameters = array('users_count' => count($active_users) + count($expired_users),
            'active_users' => $active_users,
            'expired_users' => $expired_users,
            'pending_users' => $pending_users,
            'current_day' => $current_day);
        return $this->sendTwigTemplate($reportEmail, 'users_report', $parameters);
    }

    public function sendNewGeneralConditionsNotification($user) {
        $parameters = array(
            'user' => $user,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK);
        $attachments = array('KlusbibAfspraken.pdf' => Settings::GEN_CONDITIONS_URL,
            'PrivacyVerklaring.pdf' => Settings::PRIVACY_STATEMENT_URL);
        return $this->sendTwigTemplate($user->email, 'changed_general_conditions_notification', $parameters, $attachments);
    }
	protected function sendTwigTemplate($to, $identifier, $parameters = array(), $attachments = array()) {
        setlocale(LC_ALL, 'nl_BE');
        $template = $this->twig->loadTemplate('/mail/'.$identifier.'.twig');
        $subject  = $template->renderBlock('subject',   $parameters);
        $body = $template->renderBlock('body', $parameters);
        if (empty($attachments)) {
            return $this->send($subject, $body, $to);
        } else {
            return $this->sendWithAttachments($subject, $body, $to, $attachments);
        }
    }

	private function sendWithAttachments($subject, $body, $to, $files)
    {
        $this->resetMailer();
        foreach ($files as $filename => $url) {
            $this->mailer->addStringAttachment(file_get_contents($url), $filename);
        }
        return $this->realSend($subject, $body, $to);
    }
	private function send($subject, $body, $to) {
        $this->resetMailer();

        return $this->realSend($subject, $body, $to);
    }
    /**
     * @param $subject
     * @param $body
     * @param $to
     * @return bool
     * @throws \phpmailerException
     */
    private function realSend($subject, $body, $to): bool
    {
        $this->mailer->SetFrom(SENDER_EMAIL, SENDER_NAME);
        $this->mailer->AddReplyTo(SENDER_EMAIL, SENDER_NAME);
        $this->mailer->ReturnPath = SENDER_EMAIL;
        $this->mailer->AddAddress($to);
        $this->mailer->Subject = $subject;
        $this->mailer->MsgHTML($body);
        $this->mailer->IsHTML(true);

        if (!$this->mailer->Send()) {
            $this->message = 'Problem in Sending Email. Mailer Error: ' . $this->mailer->ErrorInfo;
            return FALSE;
        } else {
            $this->message = 'Email verstuurd!';
            return TRUE;
        }
    }

    public function getLastMessage() {
		return $this->message;
	}

    /**
     * @param $startDateMembership
     * @return string
     * @throws \Exception
     */
    protected function getMembershipYear($startDateMembership): string
    {
        $startDate = DateTime::createFromFormat('Y-m-d', $startDateMembership);
        $pivotDate = DateTime::createFromFormat('Y-m-d', $startDate->format('Y') . '-07-01');
        $membership_year = $startDate->format('Y');

        if ($startDate > $pivotDate) {
            $nextYear = $startDate->add(new DateInterval('P1Y'));
            $pivotDateEOY = DateTime::createFromFormat('Y-m-d', $startDate->format('Y') . '-12-15');
            if ($startDate > $pivotDateEOY) {
                $membership_year = $nextYear->format('Y');
            } else {
                $membership_year = $membership_year . '-' . $nextYear->format('Y');
            }
        }
        return $membership_year;
    }

    /**
     * @return PHPMailer
     */
    private function resetMailer()
    {
        $this->message = '';

        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->setLanguage('nl');

        $this->mailer->IsSMTP();
        $this->mailer->SMTPDebug = 0;
        // 		$this->mailer->SMTPDebug = \SMTP::DEBUG_SERVER;
        $this->mailer->SMTPAuth = TRUE;
        $this->mailer->SMTPSecure = "tls";
        $this->mailer->Port = MAIL_PORT;
        $this->mailer->Username = MAIL_USERNAME;
        $this->mailer->Password = MAIL_PASSWORD;
        $this->mailer->Host = MAIL_HOST;
        $this->mailer->Mailer = MAILER;
    }

}
