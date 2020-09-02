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

    function enrolmentByVolunteer($orderId, $paymentMode){
        $this->checkUserStateEnrolment();
        $membershipId = $this->createUserMembership(MembershipType::regular());
        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = $paymentMode;
        $membership->save();
        $this->user->payment_mode = $paymentMode;
        $this->user->save();
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, $paymentMode, PaymentState::SUCCESS,
                $membership->subscription->price, "EUR", $membership);
        };
        // TODO: immediately confirm payment, but avoid too much extra mails!
        // + customize email message based on payment mode
        $this->confirmPayment($paymentMode, $this->user);
        $this->mailMgr->sendEnrolmentConfirmation($this->user, $paymentMode);
        return $payment;
    }

    function renewalByVolunteer($orderId, $paymentMode) {
        $this->checkUserStateRenewal();
        $membership = Membership::findOrFail($this->user->active_membership);
        if (isset($membership->subscription->next_subscription_id)) {
            $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
        } else {
            $nextMembershipType = $membership->subscription;
        }
        $this->renewMembership($nextMembershipType, $this->user->membership);
        $newMembership = Membership::find($this->user->active_membership);
        $newMembership->last_payment_mode = $paymentMode;
        $newMembership->save();
        $this->user->payment_mode = $paymentMode;
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, $paymentMode, PaymentState::SUCCESS,
                $newMembership->subscription->price, "EUR", $newMembership);
        };
        // TODO: immediately confirm payment, but avoid too much extra mails!
        // + customize email message based on payment mode
        $this->confirmPayment($paymentMode, $this->user);
        $this->mailMgr->sendRenewalConfirmation($this->user, $paymentMode);
        $this->user->save();
        return $payment;
    }
    function enrolmentByStroom($orderId){
        $this->checkUserStateEnrolment();
        $membershipId = $this->createUserMembership(MembershipType::stroom());
        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = PaymentMode::STROOM;
        $membership->save();
        $this->user->payment_mode = PaymentMode::STROOM;
        $this->user->save();
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, PaymentMode::STROOM, PaymentState::OPEN,
                $membership->subscription->price, "EUR", $membership);
        };
//        $this->confirmPayment(PaymentMode::STROOM, $this->user);
        $this->logger->info("Sending enrolment confirmation for user " . $this->user->user_id . ": " . $this->user->full_name);
        $this->mailMgr->sendEnrolmentConfirmation($this->user, PaymentMode::STROOM);
        $this->logger->info("Sending enrolment notification to " . ENROLMENT_NOTIF_EMAIL . "(user: " . $this->user->full_name . ")");
        $this->mailMgr->sendEnrolmentStroomNotification(ENROLMENT_NOTIF_EMAIL, $this->user, false);
        $this->logger->info("Sending enrolment notification to " . STROOM_NOTIF_EMAIL . "(user: " . $this->user->full_name . ")");
        $this->mailMgr->sendEnrolmentStroomNotification(STROOM_NOTIF_EMAIL, $this->user, false);
        return $payment;
    }

    function renewalByStroom($orderId) {
        $this->checkUserStateRenewal();
        $this->renewMembership(MembershipType::stroom(), $this->user->membership);
        $newMembership = Membership::find($this->user->active_membership);
        $newMembership->last_payment_mode = PaymentMode::STROOM;
        $newMembership->save();
        $this->user->payment_mode = PaymentMode::STROOM;
        $this->user->save();
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
            $payment = $this->createNewPayment($orderId,PaymentMode::STROOM, PaymentState::OPEN,
                $newMembership->subscription->price, "EUR", $newMembership);
        };
        $this->mailMgr->sendRenewalConfirmation($this->user, PaymentMode::STROOM);
        $this->mailMgr->sendEnrolmentStroomNotification(ENROLMENT_NOTIF_EMAIL, $this->user, true);
        $this->mailMgr->sendEnrolmentStroomNotification(STROOM_NOTIF_EMAIL, $this->user, true);
        return $payment;
    }

    // TODO: test enrolment by transfer to check correct creation of payment
    // TODO: replace other usages of payment_mode and Settings for enrolment/renewal amount
    function enrolmentByTransfer($orderId){
        $this->checkUserStateEnrolment();
        $membershipId = $this->createUserMembership(MembershipType::regular());
        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = PaymentMode::TRANSFER;
        $membership->save();
        $this->user->payment_mode = PaymentMode::TRANSFER;
        $this->user->save();
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, $membership->last_payment_mode, "OPEN",
                $membership->subscription->price, "EUR", $membership);
        };
        $this->mailMgr->sendEnrolmentConfirmation($this->user, PaymentMode::TRANSFER);
        return $payment;
    }

    function renewalByTransfer($orderId) {
        $this->checkUserStateRenewal();
        $membership = Membership::findOrFail($this->user->active_membership);
        if (isset($membership->subscription->next_subscription_id)) {
            $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
        } else {
            $nextMembershipType = $membership->subscription;
        }
        $this->renewMembership($nextMembershipType, $this->user->membership);
        $newMembership = Membership::find($this->user->active_membership);
        $newMembership->last_payment_mode = PaymentMode::TRANSFER;
        $newMembership->save();

        $this->user->payment_mode = PaymentMode::TRANSFER; // deprecated
        $this->user->save();
        $payment = $this->lookupPayment($orderId);

        if ($payment == null) {
//            $payment = $this->createNewPayment($orderId,PaymentMode::TRANSFER, PaymentState::OPEN,
//                \Api\Settings::RENEWAL_AMOUNT, "EUR");
            $payment = $this->createNewPayment($orderId,PaymentMode::TRANSFER, PaymentState::OPEN,
                $newMembership->subscription->price, "EUR", $newMembership);
        };
        $this->mailMgr->sendRenewalConfirmation($this->user, PaymentMode::TRANSFER);
        return $payment;
    }

    function enrolmentByMollie($orderId, $redirectUrl, $requestedPaymentMean, $requestUri) {
        $this->checkUserStateEnrolment();
        $membershipId = $this->createUserMembership(MembershipType::regular());
        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = PaymentMode::MOLLIE;
        $membership->save();
        $this->user->payment_mode = PaymentMode::MOLLIE;
        $this->user->save();

//        return $this->initiateMolliePayment($orderId, \Api\Settings::ENROLMENT_AMOUNT_STRING, $redirectUrl,
//            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::ENROLMENT);
        return $this->initiateMolliePayment($orderId, $membership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::ENROLMENT);
    }
    function renewalByMollie($orderId, $redirectUrl, $requestedPaymentMean, $requestUri) {
        $this->checkUserStateRenewal();
        $membership = Membership::findOrFail($this->user->active_membership);
        if (isset($membership->subscription->next_subscription_id)) {
            $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
        } else {
            $nextMembershipType = $membership->subscription;
        }
        $this->renewMembership($nextMembershipType, $this->user->membership);
        $newMembership = Membership::find($this->user->active_membership);
        $newMembership->last_payment_mode = PaymentMode::MOLLIE;
        $newMembership->save();
        $this->user->payment_mode = PaymentMode::MOLLIE;
        $this->user->save();
//        $membershipEndDate = $this->getMembershipEndDate($this->user->membership_end_date);

//        return $this->initiateMolliePayment($orderId, \Api\Settings::RENEWAL_AMOUNT_STRING, $redirectUrl,
//            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::RENEWAL, $membershipEndDate);
        return $this->initiateMolliePayment($orderId, $newMembership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri->getHost(), $requestUri->getScheme(), Product::RENEWAL, $newMembership->expires_at);
    }

    function confirmPayment($paymentMode, $user) {
        if ($paymentMode == PaymentMode::MOLLIE) {
            $message = "Unexpected confirmation for payment mode ($paymentMode)";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        }
        $this->checkPaymentMode($paymentMode);
        $this->checkUserStateAtPayment($user);
        $user->payment_mode = $paymentMode;

        // set end date
        if ($user->state == UserState::CHECK_PAYMENT) {
            // end_date already set at enrolment initiation, no need to update it
        } else {
            // If end_date more than 6 months in future, assume it has already been updated
            $pivotDate = new DateTime('now');
            $pivotDate->add(new DateInterval('P6M'));
            $currentEndDate = DateTime::createFromFormat('Y-m-d', $user->membership_end_date);
            if ($currentEndDate < $pivotDate) {
                $user->membership_end_date = self::getMembershipEndDate($user->membership_end_date);
            }
        }

        // update payment
        $membership = Membership::find($user->active_membership);
        $payment = Payment::find($membership->payment->payment_id);
        $payment->state = PaymentState::SUCCESS;
        $payment->save();

        // update status
        $user->state = UserState::ACTIVE;
        $membership = Membership::find($user->active_membership);
        $membership->status = Membership::STATUS_ACTIVE;
        $membership->save();

        // update project participants
        if ($paymentMode == PaymentMode::STROOM) {
            $user->addToStroomProject();
        }
        $user->save();

        // send email notifications
        if ($paymentMode == PaymentMode::TRANSFER) {
            $this->mailMgr->sendEnrolmentPaymentConfirmation($user, $paymentMode);
        }
        if ($paymentMode == PaymentMode::STROOM) {
            $this->mailMgr->sendEnrolmentPaymentConfirmation($user, $paymentMode);
        }
    }
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
    protected function lookupPayment($orderId)
    {
        // use first() rather than get()
        // there should be only 1 result, but first returns a Model
        $payment = Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $this->user->user_id],
            ['mode', '=', PaymentMode::TRANSFER],
        ])->first();
        return $payment;
    }

    /**
     * @param $orderId
     * @return Payment
     */
    protected function createNewPayment($orderId, $mode = PaymentMode::TRANSFER, $state = "OPEN", $amount = \Api\Settings::ENROLMENT_AMOUNT, $currency = "EUR",
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

    protected function checkUserStateEnrolment()
    {
        if ($this->user->state == UserState::ACTIVE || $this->user->state == UserState::EXPIRED) {
            throw new EnrolmentException("User already enrolled, consider a renewal", EnrolmentException::ALREADY_ENROLLED);
        }
        if ($this->user->state != UserState::CHECK_PAYMENT) {
            throw new EnrolmentException("User state unsupported for enrolment", EnrolmentException::UNSUPPORTED_STATE);
        }
    }
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
                    "currency" => "EUR",
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
            // use first() rather than get()
            // there should be only 1 result, but first returns a Model
            $payment = \Api\Model\Payment::where([
                ['order_id', '=', $orderId],
                ['user_id', '=', $userId],
                ['mode', '=', 'MOLLIE'],
            ])->first();
            if ($payment == null) {
                // Create new payment
                $payment = new \Api\Model\Payment();
                $payment->mode = 'MOLLIE';
                $payment->order_id = $orderId;
                $payment->user_id = $userId;
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