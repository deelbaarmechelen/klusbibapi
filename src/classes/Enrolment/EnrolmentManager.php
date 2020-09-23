<?php

namespace Api\Enrolment;

use Api\Exception\EnrolmentException;
use Api\Mail\MailManager;
use Api\Model\Membership;
use Api\Model\MembershipType;
use Api\Model\PaymentState;
use Api\Model\Product;
use Api\Model\User;
use Api\Model\PaymentMode;
use Api\Model\Payment;
use Api\Model\UserRole;
use Api\Model\UserState;
use Api\ModelMapper\MembershipMapper;
use Api\Settings;
use DateTime;
use DateInterval;
use Mollie\Api\MollieApiClient;

class EnrolmentManager
{
    private $user;
    private $mailMgr;
    private $logger;
    private $mollie;

    function __construct($logger, User $user = null, MailManager $mailMgr = null, MollieApiClient $mollie = null) {
        $this->user = $user;
        $this->logger = $logger;
        if (is_null($mailMgr)) {
            $this->mailMgr = new MailManager(null, null, $logger);
        } else {
            $this->mailMgr = $mailMgr;
        }
        if (is_null($mollie)) {
            $this->mollie = new MollieApiClient();
        } else {
            $this->mollie = $mollie;
        }
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param $membershipType
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolmentByVolunteer($orderId, $paymentMode, $membershipType){
        return $this->enrolment($orderId, $paymentMode, $membershipType, true);
    }

    /**
     * @param $orderId
     * @param $membershipType
     * @return Payment
     * @throws EnrolmentException
     */
    // TODO: test enrolment by transfer to check correct creation of payment
    // TODO: replace other usages of payment_mode and Settings for enrolment/renewal amount
    function enrolmentByTransfer($orderId, $membershipType){
        return $this->enrolment($orderId, PaymentMode::TRANSFER, $membershipType, false);
    }

    /**
     * @param $orderId
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolmentByStroom($orderId){
        return $this->enrolment($orderId, PaymentMode::STROOM, MembershipType::STROOM, false);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param $membershipType
     * @param bool $paymentCompleted
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolment($orderId, $paymentMode, $membershipType, $paymentCompleted = false){
        // Validations
        $this->checkUserStateEnrolment();
        $payment = $this->lookupPayment($orderId, $paymentMode);
        if ($payment != null) { // payment already exists, check its state
            if ($paymentCompleted) {
                if ($payment->state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::SUCCESS . " but was $payment->state (orderId: $payment->order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            } else {
                if ($payment->state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::OPEN . " but was $payment->state (orderId: $payment->order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            }
        }

        // Create membership
        if (strcasecmp ($membershipType, MembershipType::REGULAR) == 0) {
            $membershipId = $this->createUserMembership(MembershipType::regular());
        } elseif (strcasecmp ($membershipType, MembershipType::STROOM) == 0) {
            $membershipId = $this->createUserMembership(MembershipType::stroom());
        } elseif (strcasecmp ($membershipType, MembershipType::TEMPORARY) == 0) {
            $membershipId = $this->createUserMembership(MembershipType::temporary());
        } else {
            throw new EnrolmentException("Unexpected membership type " . $membershipType, EnrolmentException::UNEXPECTED_MEMBERSHIP_TYPE);
        }
        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = $paymentMode;
        $membership->save();
        $this->user->membership_start_date = $membership->start_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->user->payment_mode = $membership->last_payment_mode;
        // check member role, if no member yet (eg. supporter), convert it to member
        if ($this->user->role != UserRole::ADMIN && $this->user->role != UserRole::MEMBER) {
            $this->user->role = UserRole::MEMBER;
        }
        $this->user->save();

        // Create payment
        if ($payment == null) {
            if ($paymentCompleted) {
                $payment = $this->createNewPayment($orderId, $paymentMode, PaymentState::SUCCESS,
                    $membership->subscription->price, Settings::CURRENCY, $membership);
            } else {
                $payment = $this->createNewPayment($orderId, $membership->last_payment_mode, PaymentState::OPEN,
                    $membership->subscription->price, Settings::CURRENCY, $membership);
            }
        }

        if ($paymentCompleted) {
            // TODO: immediately confirm payment, but avoid too much extra mails!
            // + customize email message based on payment mode
            $this->confirmPayment($paymentMode);
        }

        // Send emails
        $this->mailMgr->sendEnrolmentConfirmation($this->user, $paymentMode);
        if ($paymentMode == PaymentMode::STROOM) {
            $this->logger->info("Sending enrolment notification to " . ENROLMENT_NOTIF_EMAIL . "(user: " . $this->user->full_name . ")");
            $this->mailMgr->sendEnrolmentStroomNotification(ENROLMENT_NOTIF_EMAIL, $this->user, false);
            $this->logger->info("Sending enrolment notification to " . STROOM_NOTIF_EMAIL . "(user: " . $this->user->full_name . ")");
            $this->mailMgr->sendEnrolmentStroomNotification(STROOM_NOTIF_EMAIL, $this->user, false);
        }
        return $payment;
    }

    /**
     * @param $orderId
     * @param $redirectUrl
     * @param $requestedPaymentMean
     * @param $requestUri
     * @return \Mollie\Api\Resources\Payment
     * @throws EnrolmentException
     */
    function enrolmentByMollie($orderId, $redirectUrl, $requestedPaymentMean, $requestUri) {
        $this->checkUserStateEnrolment();
        $membershipType = MembershipType::regular();
        $payment = $this->lookupPayment($orderId, PaymentMode::MOLLIE);
        if ($payment == null) {
            // Create new payment
            $payment = $this->createNewPayment($orderId,PaymentMode::MOLLIE, PaymentState::NEW,
                $membershipType->price, Settings::CURRENCY);

            $membershipId = $this->createUserMembership($membershipType);
            $membership = Membership::findOrFail($membershipId);
            $membership->last_payment_mode = PaymentMode::MOLLIE;
            $membership->payment()->save($payment);
            $membership->save();
        }
        $this->user->payment_mode = $membership->last_payment_mode;
        $this->user->membership_start_date = $membership->start_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->user->save();

        return $this->initiateMolliePayment($orderId, $membership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::ENROLMENT);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByVolunteer($orderId, $paymentMode) {
        return $this->renewal($orderId, $paymentMode, true);
    }

    /**
     * @param $orderId
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByTransfer($orderId) {
        return $this->renewal($orderId, PaymentMode::TRANSFER, false);
    }

    /**
     * @param $orderId
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByStroom($orderId) {
        return $this->renewal($orderId, PaymentMode::STROOM, false);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param bool $paymentCompleted
     * @return Payment
     * @throws EnrolmentException
     * @throws \Exception
     */
    function renewal($orderId, $paymentMode, $paymentCompleted = false) {
        $this->checkUserStateRenewal();
        $payment = $this->lookupPayment($orderId, $paymentMode);
        if ($payment != null) { // payment already exists -> check its state
            if ($paymentCompleted) {
                if ($payment->state != PaymentState::SUCCESS) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::SUCCESS . " but was $payment->state (orderId: $payment->order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            } else {
                if ($payment->state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::OPEN . " but was $payment->state (orderId: $payment->order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            }
        }

        if ($paymentMode == PaymentMode::STROOM) {
            $nextMembershipType = MembershipType::stroom();
        } else {
            $membership = Membership::findOrFail($this->user->active_membership);
            if (isset($membership->subscription->next_subscription_id)) {
                $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
            } else {
                $nextMembershipType = $membership->subscription;
            }
        }
        if ($payment == null) {
            if ($paymentCompleted) {
                $payment = $this->createNewPayment($orderId, $paymentMode, PaymentState::SUCCESS,
                    $nextMembershipType->price, Settings::CURRENCY);
            } else {
                $payment = $this->createNewPayment($orderId,PaymentMode::TRANSFER, PaymentState::OPEN,
                    $nextMembershipType->price, Settings::CURRENCY);
            }
            // Making sure "renew membership" is executed only once
            // -> only when a new payment is created and linked to the new membership
            $this->renewMembership($nextMembershipType, $this->user->membership);
            $newMembership = Membership::find($this->user->active_membership);
            $newMembership->last_payment_mode = $paymentMode;
            $newMembership->payment()->save($payment);
            $newMembership->save();
        }
        $this->user->payment_mode = $newMembership->last_payment_mode;
        $this->user->membership_start_date = $newMembership->start_at;
        $this->user->membership_end_date = $newMembership->expires_at;
        $this->user->save();

        if ($paymentCompleted) {
            // TODO: immediately confirm payment, but avoid too much extra mails!
            // + customize email message based on payment mode
            $this->confirmPayment($paymentMode);
        }
        $this->mailMgr->sendRenewalConfirmation($this->user, $paymentMode);
        if ($paymentMode == PaymentMode::STROOM) {
            $this->mailMgr->sendEnrolmentStroomNotification(ENROLMENT_NOTIF_EMAIL, $this->user, true);
            $this->mailMgr->sendEnrolmentStroomNotification(STROOM_NOTIF_EMAIL, $this->user, true);
        }
        return $payment;
    }

    /**
     * @param $orderId
     * @param $redirectUrl
     * @param $requestedPaymentMean
     * @param $requestUri
     * @return \Mollie\Api\Resources\Payment
     * @throws EnrolmentException
     * @throws \Exception
     */
    function renewalByMollie($orderId, $redirectUrl, $requestedPaymentMean, $requestUri) {
        $this->checkUserStateRenewal();
        $membership = Membership::findOrFail($this->user->active_membership);
        if (isset($membership->subscription->next_subscription_id)) {
            $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
        } else {
            $nextMembershipType = $membership->subscription;
        }

        $payment = $this->lookupPayment($orderId, PaymentMode::MOLLIE);
        if ($payment == null) {
            // Create new payment
            $payment = $this->createNewPayment($orderId,PaymentMode::MOLLIE, PaymentState::NEW,
                $nextMembershipType->price, Settings::CURRENCY);
            $this->renewMembership($nextMembershipType, $this->user->membership);

            $newMembership = Membership::find($this->user->active_membership);
            $newMembership->last_payment_mode = PaymentMode::MOLLIE;
            $newMembership->payment()->save($payment);
            $newMembership->save();
        };

        $this->user->payment_mode = $newMembership->last_payment_mode;
        $this->user->membership_start_date = $newMembership->start_at;
        $this->user->membership_end_date = $newMembership->expires_at;
        $this->user->save();
//        $membershipEndDate = $this->getMembershipEndDate($this->user->membership_end_date);

        return $this->initiateMolliePayment($orderId, $newMembership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::RENEWAL, $newMembership->expires_at);
    }

    /**
     * @param $paymentMode
     * @param $user
     * @throws EnrolmentException
     * @throws \Api\Mail\EnrolmentException
     * @throws \Exception
     */
    function confirmPayment($paymentMode) {
        if ($paymentMode == PaymentMode::MOLLIE) {
            $message = "Unexpected confirmation for payment mode ($paymentMode)";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        }
        $this->checkPaymentMode($paymentMode);
        $this->checkUserStateAtPayment($this->user);
        $membership = Membership::find($this->user->active_membership);
        if ($membership->last_payment_mode != $paymentMode) {
            $message = "Unexpected confirmation for payment mode ($paymentMode), expected payment mode $membership->last_payment_mode";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_MODE);
        }

        // set end date
//        if ($this->user->state == UserState::CHECK_PAYMENT) {
        if ($membership->status == Membership::STATUS_PENDING) {
            // end_date already set at enrolment initiation, no need to update it
        } else {
            // If end_date more than 6 months in future, assume it has already been updated
            $pivotDate = new DateTime('now');
            $pivotDate->add(new DateInterval('P6M'));
//            $currentEndDate = DateTime::createFromFormat('Y-m-d', $this->user->membership_end_date);
//            if ($currentEndDate < $pivotDate) {
//                $this->user->membership_end_date = self::getMembershipEndDate($this->user->membership_end_date);
//            }
            $currentEndDate = DateTime::createFromFormat('Y-m-d', $membership->expires_at);
            if ($currentEndDate < $pivotDate) {
                $membership->expires_at = self::getMembershipEndDate($membership->expires_at->format('Y-m-d'));
            }
        }

        // update payment
        $payment = Payment::find($membership->payment->payment_id);
        if (isset($payment)) {
            $payment->state = PaymentState::SUCCESS;
            $payment->save();
        } else {
            $this->logger->error("Payment missing for user $user->firstname $user->lastname "
            . "(recvd payment confirmation for payment mode " . $paymentMode);
        }

        // update membership status
        $membership = Membership::find($this->user->active_membership);
        $membership->status = Membership::STATUS_ACTIVE;
        $membership->save();

        // update project participants
        if ($paymentMode == PaymentMode::STROOM) {
            $this->user->addToStroomProject();
        }

        // update user
        $this->user->payment_mode = $membership->last_payment_mode;
        $this->user->membership_start_date = $membership->start_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->user->state = UserState::ACTIVE;
        $this->user->save();

        // send email notifications
        if ($paymentMode == PaymentMode::TRANSFER) {
            $this->mailMgr->sendEnrolmentPaymentConfirmation($this->user, $paymentMode);
        }
        if ($paymentMode == PaymentMode::STROOM) {
            $this->mailMgr->sendEnrolmentPaymentConfirmation($this->user, $paymentMode);
        }
    }

    /**
     * @param $paymentMode
     * @param $user
     * @throws EnrolmentException
     */
    function declinePayment($paymentMode, $user)
    {
        $this->checkPaymentMode($paymentMode);
        $this->checkUserStateAtPayment($user);

        // update payment
        $membership = $user->membership()->first();
        $payment = Payment::find($membership->payment->payment_id);
        $payment->state = PaymentState::FAILED;
        $payment->save();

        // update status
        $user->state = UserState::DELETED;
        $membership->status = Membership::STATUS_CANCELLED; // keep a cancelled membership for history
        $membership->save();
        $user->save();
        if ($paymentMode == PaymentMode::STROOM) {
            $this->mailMgr->sendEnrolmentPaymentDecline($user, $paymentMode);
        }
    }

    /**
     * @param MembershipType $type
     * @return mixed
     * @throws \Exception
     */
    function createUserMembership(MembershipType $type) {
        if (is_null($this->user->active_membership)) {
            $status = MembershipMapper::getMembershipStatus($this->user->state, $this->user->user_id);
            $start_date = strftime('%Y-%m-%d', time());
            $end_date = self::getMembershipEndDate($start_date);
            self::createMembership($type, $start_date, $end_date, $this->user, $status);
        }
        return $this->user->active_membership;
    }

    /**
     * Create a new membership keeping start date and extending end date
     * The current membership is set to expired
     * New membership is set to active (assume payment has already been checked when calling this method)
     * @param MembershipType $newType new memberhip type
     * @param Membership $membership current membership
     * @return Membership active membership (copy of actual membership, eventual changes will be lost)
     * @throws \Exception
     */
    function renewMembership(MembershipType $newType, Membership $membership) {
        $membership->status = Membership::STATUS_EXPIRED;
        $membership->save();
        if ($newType->duration == 365 || $newType->duration == 366) { // extend with 1 year
            $end_date = self::getMembershipEndDate($membership->expires_at->format('Y-m-d'));
        } else {
            $end_date = $membership->expires_at->add(new DateInterval('P'. $newType->duration .'D'));
        }

        self::createMembership($newType, $membership->start_at, $end_date, $this->user, Membership::STATUS_ACTIVE);
        return $this->user->membership;
    }

    /**
     * For yearly subscription, this method computes the end date of membership
     * Subscriptions ending in december are automatically extended until end of year
     * @param $startDateMembership (expected in 'YYYY-MM-DD' format)
     * @return string
     * @throws \Exception
     */
    public static function getMembershipEndDate($startDateMembership): string
    {
        $startDate = DateTime::createFromFormat('Y-m-d', $startDateMembership);
        if ($startDate == false) {
            throw new \InvalidArgumentException("Invalid date format (expecting 'YYYY-MM-DD'): " . $startDateMembership);
        }
        $pivotDate = new DateTime('first day of december this year');
        $membershipEndDate = $startDate->add(new DateInterval('P1Y')); //$endDate->format('Y');
        $currentDate = new DateTime();
        if ($currentDate > $pivotDate) { // extend membership until end of year
            $extendedEndDate = $currentDate->modify('last day of december next year');
            if ($membershipEndDate < $extendedEndDate) {
                $membershipEndDate = $extendedEndDate;
            }
        }
        return $membershipEndDate->format('Y-m-d');
    }

    /**
     * @param MembershipType $type
     * @param $start_date
     * @param $end_date
     * @param User $user
     * @param $status
     * @throws \Exception
     */
    public static function createMembership(MembershipType $type, $start_date, $end_date, User $user, $status) {
        $membership = new Membership();
        $membership->subscription_id = $type->id;
        $membership->start_at = $start_date;
        $membership->expires_at = $end_date;
        $membership->contact_id = $user->user_id;
        Membership::isValidStatus($status);
        $membership->status = $status;
        $membership->save();

        $user->membership()->associate($membership);
        $user->save();
    }
    /**
     * @param $orderId
     * @return Payment
     */
    protected function lookupPayment($orderId, $paymentMode)
    {
        // use first() rather than get()
        // there should be only 1 result, but first returns a Model
        return Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $this->user->user_id],
            ['mode', '=', $paymentMode],
        ])->first();
    }

    /**
     * @param $orderId
     * @return Payment
     */
    protected function createNewPayment($orderId, $mode, $state = PaymentState::OPEN, $amount, $currency,
        Membership $membership = null): Payment
    {
        $payment = new Payment();
        $payment->mode = $mode;
        $payment->order_id = $orderId;
        $payment->user_id = $this->user->user_id;
        $payment->payment_date = new \DateTime();
        $payment->amount = $amount;
        $payment->currency = $currency;
        $payment->state = $state;
        if (isset($membership)) {
            $membership->payment()->save($payment);
        } else {
            $payment->save();
        }
        return $payment;
    }

    /**
     * @throws EnrolmentException
     */
    protected function checkUserStateEnrolment()
    {
        if ($this->user->state == UserState::ACTIVE || $this->user->state == UserState::EXPIRED) {
            throw new EnrolmentException("User already enrolled, consider a renewal", EnrolmentException::ALREADY_ENROLLED);
        }
        if ($this->user->state != UserState::CHECK_PAYMENT) {
            throw new EnrolmentException("User state unsupported for enrolment", EnrolmentException::UNSUPPORTED_STATE);
        }
    }

    /**
     * @throws EnrolmentException
     */
    protected function checkUserStateRenewal()
    {
        if ($this->user->state == UserState::CHECK_PAYMENT) {
            throw new EnrolmentException("Enrolment not yet complete, consider an enrolment", EnrolmentException::NOT_ENROLLED);
        }
        if ($this->user->state != UserState::ACTIVE && $this->user->state != UserState::EXPIRED) {
            throw new EnrolmentException("User state unsupported for renewal", EnrolmentException::UNSUPPORTED_STATE);
        }
    }

    /**
     * @param $orderId
     * @param $amount
     * @param $redirectUrl url to be called after Mollie payment
     * @param $requestedPaymentMean payment mean that will be used in Mollie transaction (when null, a choice screen is shown)
     * @param $hostname hostname to use in webhook url
     * @param $protocol protocol used in webhook url (http or https)
     * @throws EnrolmentException
     */
    protected function initiateMolliePayment($orderId, $amount, $redirectUrl, $requestedPaymentMean, $hostname, $protocol, $productId, $membershipEndDate = null)
    {
        if ($productId == Product::ENROLMENT) {
            $description = "Klusbib inschrijving {$this->user->firstname} {$this->user->lastname}";
        } elseif ($productId == Product::RENEWAL) {
            $description = "Klusbib verlenging lidmaatschap {$this->user->firstname} {$this->user->lastname}";
        }
        try {
            $this->mollie->setApiKey(MOLLIE_API_KEY);
            $paymentData = [
                "amount" => [
                    "currency" => Settings::CURRENCY,
                    "value" => number_format($amount, 2, '.', ',')
                ],
                "description" => $description,
                "redirectUrl" => "{$redirectUrl}?orderId={$orderId}",
//                "webhookUrl" => "{$protocol}://{$hostname}/Enrolment/{$orderId}",
                "webhookUrl" => "https://{$hostname}/enrolment/{$orderId}",
                "locale" => Settings::MOLLIE_LOCALE,
                "metadata" => [
                    "order_id" => $orderId,
                    "user_id" => $this->user->user_id,
                    "product_id" => $productId,
                    "membership_end_date" => $membershipEndDate
                ],
            ];

            if (isset($requestedPaymentMean) && !empty($requestedPaymentMean)) {
                $paymentData["method"] = $requestedPaymentMean;
            }
//            $this->logger->info("payment data = " . print_r($paymentData, TRUE));
            $payment = $this->mollie->payments->create($paymentData);
            $this->logger->info("Payment (Mollie) created with order id {$orderId} webhook {$protocol}://{$hostname}/enrolment/{$orderId} and redirectUrl {$redirectUrl}"
                . "-productId=$productId;membership_end_date=$membershipEndDate");
            // store payment id -> needed?
            return $payment;

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            echo "API call failed: " . htmlspecialchars($e->getMessage());
            $this->logger->error("API call failed: " . htmlspecialchars($e->getMessage()));
            throw new EnrolmentException("API call failed: " . htmlspecialchars($e->getMessage()), EnrolmentException::MOLLIE_EXCEPTION, $e);
        }
    }

    /**
     * @param $paymentId
     * @throws EnrolmentException
     */
    public function processMolliePayment($paymentId) {
        try {
            $this->mollie->setApiKey(MOLLIE_API_KEY);

            /*
             * Retrieve the payment's current state.
             * See also https://docs.mollie.com/payments/status-changes
             */
            $paymentMollie = $this->mollie->payments->get($paymentId);
            $this->logger->info('Mollie payment:' . json_encode($paymentMollie));
            $orderId = $paymentMollie->metadata->order_id;
            $userId = $paymentMollie->metadata->user_id;
            $productId = $paymentMollie->metadata->product_id;
            $newMembershipEndDate = $paymentMollie->metadata->membership_end_date;

            $payment = $this->lookupPayment($orderId, PaymentMode::MOLLIE);
            if ($payment == null) { // should no longer happen, payment is created at "POST enrolment"
                // Create new payment
                $payment = new \Api\Model\Payment();
                $payment->mode = 'MOLLIE';
                $payment->order_id = $orderId;
                $payment->user_id = $userId;
                $payment->state = PaymentState::NEW;
                $payment->payment_date = new \DateTime();
                $payment->amount = $paymentMollie->amount->value;
                $payment->currency = $paymentMollie->amount->currency;
            };
            $currentPaymentState = $payment->state;
            if ($paymentMollie->isPaid() && !$paymentMollie->hasRefunds() && !$paymentMollie->hasChargebacks()) {
                /*
                 * The payment is paid and isn't refunded or charged back.
                 * At this point you'd probably want to start the process of delivering the product to the customer.
                 */
                $payment->state = "SUCCESS";
            } elseif ($paymentMollie->isOpen()) {
                /*
                 * The payment is open.
                 */
                $payment->state = "OPEN";
            } elseif ($paymentMollie->isPending()) {
                /*
                 * The payment is pending.
                 */
                $payment->state = "PENDING";
            } elseif ($paymentMollie->isFailed()) {
                /*
                 * The payment has failed.
                 */
                $payment->state = "FAILED";
            } elseif ($paymentMollie->isExpired()) {
                /*
                 * The payment is expired.
                 */
                $payment->state = "EXPIRED";
            } elseif ($paymentMollie->isCanceled()) {
                /*
                 * The payment has been canceled.
                 */
                $payment->state = "CANCELED";
            } elseif ($paymentMollie->hasRefunds()) {
                /*
                 * The payment has been (partially) refunded.
                 * The status of the payment is still "paid"
                 */
                $payment->state = "REFUND";
            } elseif ($paymentMollie->hasChargebacks()) {
                /*
                 * The payment has been (partially) charged back.
                 * The status of the payment is still "paid"
                 */
                $payment->state = "CHARGEBACK";
            }
            $this->logger->info("Saving payment for orderId $orderId with state $payment->state (Mollie payment id=$paymentId / Internal payment id = $payment->payment_id)");
            if ($currentPaymentState == $payment->state) {
                // no change in state -> no need to reprocess Mollie payment (and avoid to resend notifications)
                return;
            }

            // Lookup user and update state
            $user = \Api\Model\User::find($userId);
            if (null == $user) {
                $this->logger->error("POST /enrolment/$orderId failed: user $userId is not found");
                throw new EnrolmentException("No user found with id $userId", EnrolmentException::UNKNOWN_USER);
            }
            $user->payments()->save($payment);
            $user->save();
            $user->refresh();

            $membership = $user->membership()->first();
            $membership->payment()->save($payment);
            $membership->refresh();

            if ($productId == \Api\Model\Product::ENROLMENT) {
                if ($payment->state == "SUCCESS") {
                    if ($user->state != UserState::ACTIVE) {
                        $user->state = UserState::ACTIVE;
                        $user->save();
                        $membership->status = Membership::STATUS_ACTIVE;
                        $membership->save();

                        // send confirmation to new member
                        $this->mailMgr->sendEnrolmentConfirmation($user, PaymentMode::MOLLIE);
                        // send notification to Klusbib team
                        $this->mailMgr->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL,$user, false);
                    }
                } else if ($payment->state == "FAILED"
                    || $payment->state == "EXPIRED"
                    || $payment->state == "CANCELED"
                    || $payment->state == "REFUND"
                    || $payment->state == "CHARGEBACK") {
                    // Permanent failure, or special case -> send notification for manual follow up
                    $this->mailMgr->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL,$user, $payment, false);
                }
            } else if ($productId == \Api\Model\Product::RENEWAL) {
                if ($payment->state == "SUCCESS") {
                    if ($user->state == UserState::ACTIVE
                        || $user->state == UserState::EXPIRED) {

                        $user->state = UserState::ACTIVE;
                        $user->membership_end_date = $newMembershipEndDate;
                        $user->save();

                        $membership->status = Membership::STATUS_ACTIVE;
                        $membership->expires_at = $newMembershipEndDate;
                        $membership->save();

                        // send confirmation to new member
                        $this->mailMgr->sendRenewalConfirmation($user, PaymentMode::MOLLIE);
                        // send notification to Klusbib team
                        $this->mailMgr->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL,$user, true);
                    }
                } else if ($payment->state == "FAILED"
                    || $payment->state == "EXPIRED"
                    || $payment->state == "CANCELED"
                    || $payment->state == "REFUND"
                    || $payment->state == "CHARGEBACK") {
                    // Permanent failure, or special case -> send notification for manual follow up
                    $this->mailMgr->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL,$user, $payment, true);
                }
            }

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            echo "Webhook call failed: " . htmlspecialchars($e->getMessage());
            throw new EnrolmentException($e->getMessage(), EnrolmentException::MOLLIE_EXCEPTION);
        }
    }

    /**
     * @param $paymentMode
     * @throws EnrolmentException
     */
    private function checkPaymentMode($paymentMode): void
    {
        if ($paymentMode != PaymentMode::CASH &&
            $paymentMode != PaymentMode::TRANSFER &&
            $paymentMode != PaymentMode::MBON &&
            $paymentMode != PaymentMode::SPONSORING &&
            $paymentMode != PaymentMode::LETS &&
            $paymentMode != PaymentMode::PAYCONIQ &&
            $paymentMode != PaymentMode::OTHER &&
            $paymentMode != PaymentMode::STROOM &&
            $paymentMode != PaymentMode::OVAM
        ) {
            $message = "Unsupported payment mode ($paymentMode)";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_MODE);
        }
    }

    /**
     * @param $user
     * @throws EnrolmentException
     */
    private function checkUserStateAtPayment($user): void
    {
        if ($user->state != UserState::CHECK_PAYMENT &&
            $user->state != UserState::ACTIVE &&
            $user->state != UserState::EXPIRED) {
            $message = "Unexpected confirmation for user state ($user->state)";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        }
    }
}