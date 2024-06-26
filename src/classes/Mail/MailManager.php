<?php
namespace Api\Mail;

use Api\Model\Membership;
use Api\Model\MembershipType;
use Api\Model\Contact;
use Api\Token\Token;
use PHPMailer\PHPMailer\OAuth;
use PHPMailer\PHPMailer\PHPMailer;
use League\OAuth2\Client\Provider\Google;
use PHPMailer\PHPMailer\SMTP;
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
    private $logger;

    function __construct(PHPMailer $mailer = null, Twig_Environment $twig = null, $logger = null) {
		if (is_null($mailer)) {
			$this->mailer = new PHPMailer ();
		} else {
			$this->mailer = $mailer;
		}
        $this->twig = $twig;
        if (is_null($this->twig)) {
            $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../../templates');
            $this->twig = new \Twig\Environment($loader, [
                'cache' => __DIR__ . '/../../../public/cache/twig_compilations',
                 'auto_reload' => true
                ]);
        }
        if (is_null($logger)) {
            $this->logger = FALSE;
        } else {
            $this->logger = $logger;
        }
    }

    public function sendErrorNotif($message, $context = "") {
        $parameters = [
            'message' => $message,
            'context' => $context
        ];
        return $this->sendTwigTemplate(SUPPORT_NOTIF_EMAIL, 'error_notif', $parameters);
    }
	public function sendEmailVerification($userId, $userName, $to, $token) {
        if ($this->logger) {
            $this->logger->debug("Sending email verification " . $to . " (user name=" . $userName . "; id=" . $userId . ")");
        }

        $link = PROJECT_HOME . "auth/confirm/" . $userId . "?token=" . $token . "&email=" . $to . "&name=" . $userName;
		$body = "<h1>Welkom bij Klusbib</h1>"
				. "<div>Beste " . $userName . ",<br><br>"
				. "<p>Om uw inschrijving te vervolledigen dien je dit email adres te bevestigen door op onderstaande link te klikken:<br>"
				. "<a href='$link'>$link</a><br><br></p>"
				. "<p>Herken je deze actie niet, dan kan je dit bericht veilig negeren<br></p>"
				. "Groetjes,<br> Klusbib Team.</div>";
        $parameters = [
            'userName' => $userName,
            'link' => $link,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'emailLink' => Settings::EMAIL_LINK
        ];
        return $this->sendTwigTemplate($to, 'email_verification', $parameters);
	}
	public function sendPwdRecoveryMail($userId, $userName, $userEmail, $token, $redirectUrl = null) {
		if (isset($redirectUrl)) {
		    $link = $redirectUrl;
        } else {
            $link = PROJECT_HOME . "auth/reset/" . $userId;
        }
        $link .= "?token=" . $token . "&name=" . $userName . "&userId=" . $userId;
        $parameters = [
            'userName' => $userName,
            'link' => $link,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'emailLink' => Settings::EMAIL_LINK
        ];
        return $this->sendTwigTemplate($userEmail, 'password_recovery', $parameters);
	}
	public function sendReservationRequest($to, $user, $tool, $reservation, $notifyTeamAddress = null) {
		if (empty($tool->code)) {
			$toolCode = "zonder code"
					. $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
		} else {
			$toolCode = "met code " . $tool->code;
		}
		$toolCode .= " (Naam/beschrijving/merk/type: " . $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
        $parameters = [
            'user' => $user, 
            'toolCode' => $toolCode, 
            'reservation' => $reservation, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];
        if (isset($notifyTeamAddress)) {
            $this->sendTwigTemplate($notifyTeamAddress, 'reservation_request_notif', $parameters);
        }
        return $this->sendTwigTemplate($to, 'reservation_request', $parameters);
	}
	public function sendReservationConfirmation($to, $user, $tool, $reservation, $notifyTeamAddress) {
		if (empty($tool->code)) {
			$toolCode = "zonder code"
					. $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
		} else {
			$toolCode = "met code " . $tool->code;
		}
		$toolCode .= " (Naam/beschrijving/merk/type: " . $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
        $parameters = [
            'user' => $user, 
            'toolCode' => $toolCode, 
            'reservation' => $reservation, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        if (isset($notifyTeamAddress)) {
            $this->sendTwigTemplate($notifyTeamAddress, 'reservation_confirm_notif', $parameters);
        }
        return $this->sendTwigTemplate($to, 'reservation_confirm', $parameters);
	}

    /**
     * @param $to target email address
     * @param $user the user this reservation is for
     * @param $tool the reserved tool
     * @param $reservation reservation itself
     * @param $notifyTeamAddress email address of team for extra notification
     * @param $cancelledBy user_id of cancellation requester
     * @return bool
     */
	public function sendReservationCancellation($to, $user, $tool, $reservation, $notifyTeamAddress, $cancelledBy) {

	    if (empty($tool->code)) {
			$toolCode = "zonder code"
					. $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
		} else {
			$toolCode = "met code " . $tool->code;
		}
		$toolCode .= " (Naam/beschrijving/merk/type: " . $tool->name . "/" . $tool->description . "/" . $tool->brand . "/" . $tool->type . ")";
        $parameters = [
            'user' => $user, 
            'toolCode' => $toolCode, 
            'reservation' => $reservation, 
            'cancelledBy' => $cancelledBy, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        if (isset($notifyTeamAddress)) {
            $this->sendTwigTemplate($notifyTeamAddress, 'reservation_cancel_notif', $parameters);
        }
        if ($user->id == $cancelledBy) { // do not notify user if he/she requested the cancel
            if ($this->logger) {
                $this->logger->info("Send of reservation cancel notification has been skipped (user $user->first_name with id $cancelledBy requested cancel himself)");
            }
            return true;
        }
        return $this->sendTwigTemplate($to, 'reservation_cancel', $parameters);
	}

    public function sendDeliveryRequest($to, $delivery, $user) {
        $parameters = [
            'user' => $user, 
            'delivery' => $delivery, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        return $this->sendTwigTemplate($to, 'delivery_request', $parameters);
    }
    public function sendDeliveryRequestNotification($to, $delivery, $user) {
        $parameters = [
            'user' => $user, 
            'delivery' => $delivery, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        return $this->sendTwigTemplate($to, 'delivery_request_notif', $parameters);
    }

    public function sendDeliveryUpdateNotification($to, $delivery, $reason) {
        $parameters = [
            'user' => $delivery->user, 
            'delivery' => $delivery, 
            'reason' => $reason, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        return $this->sendTwigTemplate($to, 'delivery_update_notif', $parameters);
    }

    public function sendDeliveryConfirmation($to, $delivery, $user) {
        $parameters = [
            'user' => $user, 
            'delivery' => $delivery, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        return $this->sendTwigTemplate($to, 'delivery_confirm', $parameters);
    }

    public function sendDeliveryCancellation($to, $delivery, $user) {
        $parameters = [
            'user' => $user, 
            'delivery' => $delivery, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK, 
            'inventoryLink' => Settings::INVENTORY_LINK
        ];

        return $this->sendTwigTemplate($to, 'delivery_cancel', $parameters);
    }
    // Send enrolment notification to Klusbib team
    public function sendEnrolmentNotification($userEmail, $newUser) {
        $parameters = ['newUser' => $newUser];
        return $this->sendTwigTemplate($userEmail, 'enrolment_new_notif', $parameters);
    }

    /**
     * @param $userEmail email adres the notification should be sent to
     * @param $newUser newly created user in enrolment
     * @param null $token generated token to allow confirmation
     * @return bool
     */
    public function sendEnrolmentStroomNotification($userEmail, $newUser, $isRenewal = false) {
        $parameters = [
            'newUser' => $newUser, 
            'isRenewal' => $isRenewal, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'facebookLink' => Settings::FACEBOOK_LINK, 
            'emailLink' => Settings::EMAIL_LINK
        ];
        return $this->sendTwigTemplate($userEmail, 'enrolment_stroom_notif', $parameters);
    }
    public function sendEnrolmentSuccessNotification($userEmail, $newUser, $isRenewal = false) {
        $parameters = [
            'newUser' => $newUser, 
            'isRenewal' => $isRenewal
        ];
        return $this->sendTwigTemplate($userEmail, 'enrolment_success_notif', $parameters);
    }
    public function sendEnrolmentFailedNotification($userEmail, $newUser, $payment, $isRenewal = false, $errorMsg = "") {
        $parameters = [
            'newUser' => $newUser, 
            'payment' => $payment, 
            'isRenewal' => $isRenewal, 
            'message' => $errorMsg
        ];
        return $this->sendTwigTemplate($userEmail, 'enrolment_failed_notif', $parameters);
    }

    public function sendEnrolmentConfirmation(Contact $user, $paymentMode, $paymentCompleted = false, Membership $membership = null) {
        $confirmEmail = !$user->isEmailConfirmed();
        $resetPwd = !$user->hasLoggedAtLeastOnce();
        $dest = $user->email; // token destination

        $scopes = [];
        if ($confirmEmail) {
            array_push($scopes, "auth.confirm");
        }
        if ($resetPwd) {
            array_push($scopes, "users.update.password");
        }
        $token = Token::generateToken($scopes, $user->id, new \DateTime("now +2 days"), $dest);
        $confirmEmailLink = PROJECT_HOME . "auth/confirm/" . $user->id . "?token=" . $token . "&email=" . $user->email . "&name=" . $user->first_name;
        $resetPwdLink = Settings::RESET_PWD_LINK . "?token=" . $token . "&name=" . $user->first_name . "&userId=" . $user->id;

        $enrolmentAmount = isset($membership) && isset($membership->subscription) ? $membership->subscription->price : Settings::ENROLMENT_AMOUNT;
        $membership_year = $this->getMembershipYear(date('Y-m-d'));
        $parameters = [
            'user' => $user,
            'paymentMode' => $paymentMode,
            'account' => Settings::ACCOUNT_NBR,
            'amount' => $enrolmentAmount,
            'membership_year' => $membership_year,
            'confirmEmail' => $confirmEmail && !$resetPwd,
            // omit confirmEmail link if resetPwd link is included as email can be confirmed through pwd reset
            'confirmEmailLink' => $confirmEmailLink,
            'resetPwd' => $resetPwd,
            'resetPwdLink' => $resetPwdLink,
            'paymentCompleted' => $paymentCompleted,
            'enqueteLink' => Settings::ENQUETE_LINK,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK,
        ];
        return $this->sendTwigTemplate($user->email, 'enrolment_confirmation', $parameters);
    }

    /**
     * @param $user
     * @param $paymentMode
     * @return bool
     * @throws EnrolmentException
     */
    public function sendEnrolmentPaymentConfirmation($user, $paymentMode) {
        $parameters = [
            'user' => $user, 
            'paymentMode' => $paymentMode, 
            'reservationEmail' => Settings::RESERVATION_EMAIL, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'facebookLink' => Settings::FACEBOOK_LINK, 
            'emailLink' => Settings::EMAIL_LINK
        ];
        return $this->sendTwigTemplate($user->email, 'enrolment_confirm_payment', $parameters);
    }

    public function sendEnrolmentPaymentDecline($user, $paymentMode) {
        $parameters = [
            'user' => $user, 
            'paymentMode' => $paymentMode, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'emailLink' => Settings::EMAIL_LINK
        ];
        return $this->sendTwigTemplate($user->email, 'enrolment_decline_payment', $parameters);
    }

    /**
     * @param $user the user to which a renewal reminder should be sent
     * @param $daysToExpiry number of days before this users membership will expire.
     *          if negative, the membership has already expired
     * @param $token temporary token to access user profile without login
     * @return bool TRUE if message successfully sent
     */
    public function sendRenewal($user, $daysToExpiry, $token) {
        $membership_year = $this->getMembershipYear($user->activeMembership->expires_at->format('Y-m-d'));
        $link = Settings::PROFILE_LINK . $user->id . "?token=" . $token;
        $renewalAmount = $user->activeMembership->subscription->nextMembershipType != null
            ? $user->activeMembership->subscription->nextMembershipType->price : $user->activeMembership->subscription->price;
        $isCompany = $user->activeMembership->subscription->isCompanySubscription();
        $termsUpdateRequired = $user->accept_terms_date < Settings::getLatestTermsDate();

        $parameters = [
            'user' => $user, 
            'isCompany' => $isCompany, 
            'profileLink' => $link, 
            'amount' => $renewalAmount, 
            'account' => Settings::ACCOUNT_NBR, 
            'currentDate' => date('Y-m-d'), 
            'emailLink' => Settings::EMAIL_LINK, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'facebookLink' => Settings::FACEBOOK_LINK, 
            'evaluationLink' => Settings::EVALUATION_LINK, 
            'membership_year' => $membership_year, 
            'daysToExpiry' => $daysToExpiry, 
            'termsUpdate' => $termsUpdateRequired, 
            'termsLink' => Settings::GEN_CONDITIONS_URL, 
            'privacyTermsLink' => Settings::PRIVACY_STATEMENT_URL
        ];
        return $this->sendTwigTemplate($user->email, 'renewal', $parameters);
    }
    public function sendResumeEnrolmentReminder($user, $token) {
        echo "Resume enrolment reminder called with token $token\n";
        $membership_year = $this->getMembershipYear(date('Y-m-d'));
        $link = Settings::PROFILE_LINK . $user->id . "?token=" . $token;
        $parameters = [
            'user' => $user, 
            'link' => $link, 
            'enrolmentAmount' => Settings::ENROLMENT_AMOUNT, 
            'account' => Settings::ACCOUNT_NBR, 
            'currentDate' => date('Y-m-d'), 
            'emailLink' => Settings::EMAIL_LINK, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'facebookLink' => Settings::FACEBOOK_LINK, 
            'evaluationLink' => Settings::EVALUATION_LINK, 
            'membership_year' => $membership_year
        ];
        return $this->sendTwigTemplate($user->email, 'resume_enrolment_reminder', $parameters);
    }
    public function sendRenewalConfirmation($user, $paymentMode, $paymentCompleted = false) {
        $confirmEmail = !$user->isEmailConfirmed();
        $resetPwd = !$user->hasLoggedAtLeastOnce();
        $dest = $user->email; // token destination

        $scopes = [];
        if ($confirmEmail) {
            array_push($scopes, "auth.confirm");
        }
        if ($resetPwd) {
            array_push($scopes, "users.update.password");
        }
        $token = Token::generateToken($scopes, $user->id, new \DateTime("now +2 days"), $dest);
        $confirmEmailLink = PROJECT_HOME . "auth/confirm/" . $user->id . "?token=" . $token . "&email=" . $user->email . "&name=" . $user->first_name;
        $resetPwdLink = Settings::RESET_PWD_LINK . "?token=" . $token . "&name=" . $user->first_name . "&userId=" . $user->id;

        $membership_year = $this->getMembershipYear($user->activeMembership->expires_at->format('Y-m-d'));
        $renewalAmount = $user->activeMembership->subscription->nextMembershipType != null
            ? $user->activeMembership->subscription->nextMembershipType->price : $user->activeMembership->subscription->price;
        $isCompany = $user->activeMembership->subscription->isCompanySubscription();
        $parameters = [
            'user' => $user,
            'isCompany' => $isCompany,
            'confirmEmail' => $confirmEmail && !$resetPwd,
            // omit confirmEmail link if resetPwd link is included as email can be confirmed through pwd reset
            'confirmEmailLink' => $confirmEmailLink,
            'resetPwd' => $resetPwd,
            'resetPwdLink' => $resetPwdLink,
            'paymentMode' => $paymentMode,
            'account' => Settings::ACCOUNT_NBR,
            'amount' => $renewalAmount,
            'membership_year' => $membership_year,
            'paymentCompleted' => $paymentCompleted,
            'enqueteLink' => Settings::ENQUETE_LINK,
            'webpageLink' => Settings::WEBPAGE_LINK,
            'facebookLink' => Settings::FACEBOOK_LINK,
            'emailLink' => Settings::EMAIL_LINK,
        ];
        return $this->sendTwigTemplate($user->email, 'renewal_confirmation', $parameters);
    }

    public function sendUsersReport($active_users, $expired_users, $pending_users) {
	    $reportEmail = 'info@klusbib.be';
	    $current_day = date('Y-m-d');
        $parameters = [
            'users_count' => (is_countable($active_users) ? count($active_users) : 0) + (is_countable($expired_users) ? count($expired_users) : 0), 
            'active_users' => $active_users, 
            'expired_users' => $expired_users, 
            'pending_users' => $pending_users, 
            'current_day' => $current_day
        ];
        return $this->sendTwigTemplate($reportEmail, 'users_report', $parameters);
    }

    /**
     * @param $requested_reservations requested reservations enriched with user and item relations
     * @param $confirmed_reservations confirmed reservations enriched with user and item relations
     * @return bool true when report successfully sent, false otherwise
     */
    public function sendReservationsReport($requested_reservations, $confirmed_reservations) {
	    $reportEmail = 'info@klusbib.be';
	    $current_day = date('Y-m-d');
        $parameters = [
            'reservations_count' => (is_countable($requested_reservations) ? count($requested_reservations) : 0) + (is_countable($confirmed_reservations) ? count($confirmed_reservations) : 0), 
            'requested_reservations' => $requested_reservations, 
            'confirmed_reservations' => $confirmed_reservations, 
            'current_day' => $current_day
        ];
        return $this->sendTwigTemplate($reportEmail, 'reservations_report', $parameters);
    }

    public function sendNewGeneralConditionsNotification($user) {
        $parameters = [
            'user' => $user, 
            'webpageLink' => Settings::WEBPAGE_LINK, 
            'facebookLink' => Settings::FACEBOOK_LINK, 
            'emailLink' => Settings::EMAIL_LINK
        ];
        $attachments = ['KlusbibAfspraken.pdf' => Settings::GEN_CONDITIONS_URL, 'PrivacyVerklaring.pdf' => Settings::PRIVACY_STATEMENT_URL];
        return $this->sendTwigTemplate($user->email, 'changed_general_conditions_notification', $parameters, $attachments);
    }
	protected function sendTwigTemplate($to, $identifier, $parameters = [], $attachments = []) {
        if ($this->logger) {
            $this->logger->debug("Sending email with twig template to " . $to . " (identifier=" . $identifier . ")");
        }
        setlocale(LC_ALL, 'nl_BE');
        $template = $this->twig->load('/mail/'.$identifier.'.twig');
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
        $this->mailer->AddReplyTo(REPLYTO_EMAIL, REPLYTO_NAME);
        $this->mailer->ReturnPath = SENDER_EMAIL;
        $this->mailer->AddAddress($to);
        $this->mailer->Subject = $subject;
        $this->mailer->MsgHTML($body);
        $this->mailer->IsHTML(true);

        try {
            if (!$this->mailer->Send()) {

                $this->message = 'Problem in Sending Email. Mailer Error: ' . $this->mailer->ErrorInfo;
                if ($this->logger) {
                    $this->logger->error($this->message);
                }
                return FALSE;
            } else {
                $this->message = 'Email verstuurd!';
                if ($this->logger) {
                    $this->logger->info("Message successfully sent to " . $to . " (subject=" . $subject . ")");
                }
                return TRUE;
            }

        } catch (\Exception $ex) {
            if ($this->logger) {
                $token_expired_msg = "If you get an invalid_grant exception, the OAUTH token might be expired. This can happen if:\n"
                    . "The user has revoked your app's access.\n"
                    . "The refresh token has not been used for six months.\n"
                    . "The user changed passwords and the refresh token contains Gmail scopes.\n"
                    . "The user account has exceeded a maximum number of granted (live) refresh tokens.\n"
                    . "\nGenerate a new refresh token with /test/get_oauth_token.php to resolve this issue.\n"
                    . "See also https://developers.google.com/identity/protocols/OAuth2";
                $this->logger->error("Send exception: " . $ex->getMessage() . "\n" . $token_expired_msg);
            }
            throw $ex;
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

    private function resetMailer()
    {
        $this->message = '';

        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();
        $this->mailer->setLanguage('nl');
        if (MAILER == "smtp") {
            $this->initSmtp();
        } else {
            $this->initSendmail();
        }

    }

    private function getOAuth() : OAuth {
        //Fill in authentication details here
        //Either the gmail account owner, or the user that gave consent
        $email = SENDER_EMAIL;
        $clientId = OAUTH_CLIENT_ID;
        $clientSecret = OAUTH_CLIENT_SECRET;
        //Obtained by configuring and running get_oauth_token.php
        //after setting up an app in Google Developer Console.
        $refreshToken = OAUTH_TOKEN;

        //Create a new OAuth2 provider instance
        $provider = new Google(
            [
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
            ]
        );
        return new OAuth(
                [
                    'provider' => $provider,
                    'clientId' => $clientId,
                    'clientSecret' => $clientSecret,
                    'refreshToken' => $refreshToken,
                    'userName' => $email,
                ]
        );
    }

    private function initSmtp(): void
    {
//Tell PHPMailer to use SMTP
        $this->mailer->IsSMTP();
        //Enable SMTP debugging
        // SMTP::DEBUG_OFF = off (for production use)
        // SMTP::DEBUG_CLIENT (1): show client -> server messages only. Don't use this - it's very unlikely to tell you anything useful
        // SMTP::DEBUG_SERVER (2): show client -> server and server -> client messages - this is usually the setting you want
        // SMTP::DEBUG_CONNECTION (3): As 2, but also show details about the initial connection; only use this if you're having trouble connecting (e.g. connection timing out)
        // SMTP::DEBUG_LOWLEVEL (4): As 3, but also shows detailed low-level traffic. Only really useful for analyzing protocol-level bugs, very verbose, probably not what you need
        $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
//        $this->mailer->SMTPDebug = SMTP::DEBUG_CONNECTION;
        //Whether to use SMTP authentication
        $this->mailer->SMTPAuth = TRUE;
        //Set AuthType to use XOAUTH2
        $this->mailer->AuthType = MAIL_AUTH_TYPE;
        //Set the encryption mechanism to use - STARTTLS or SMTPS
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]; // security issue -> only to be used for localhost dev
        //Set the hostname of the mail server
        $this->mailer->Host = MAIL_HOST;
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $this->mailer->Port = MAIL_PORT;
        if ($this->mailer->AuthType == 'XOAUTH2') {
            $oauth = $this->getOAuth();
            $this->mailer->setOAuth($oauth);
        } else {
            $this->mailer->Username  = MAIL_USERNAME;
            $this->mailer->Password  = MAIL_PASSWORD;
        }
    }
    private function initSendmail(): void
    {
        $this->mailer->isSMTP();
        //Enable SMTP debugging
        // SMTP::DEBUG_OFF = off (for production use)
        // SMTP::DEBUG_CLIENT (1): show client -> server messages only. Don't use this - it's very unlikely to tell you anything useful
        // SMTP::DEBUG_SERVER (2): show client -> server and server -> client messages - this is usually the setting you want
        // SMTP::DEBUG_CONNECTION (3): As 2, but also show details about the initial connection; only use this if you're having trouble connecting (e.g. connection timing out)
        // SMTP::DEBUG_LOWLEVEL (4): As 3, but also shows detailed low-level traffic. Only really useful for analyzing protocol-level bugs, very verbose, probably not what you need
        $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
        //Set the hostname of the mail server
        $this->mailer->Host = MAIL_HOST;
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $this->mailer->Port = MAIL_PORT;
    }
}
