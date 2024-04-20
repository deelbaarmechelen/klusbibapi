<?php

namespace Api\Enrolment;

use Api\Exception\EnrolmentException;
use Api\Mail\MailManager;
use Api\Model\Membership;
use Api\Model\MembershipState;
use Api\Model\MembershipType;
use Api\Model\PaymentState;
use Api\Model\Product;
use Api\Model\Contact;
use Api\Model\PaymentMode;
use Api\Model\Payment;
use Api\Model\UserRole;
use Api\Model\UserState;
use Api\ModelMapper\MembershipMapper;
use Api\Settings;
use Api\User\UserManager;
use Carbon\Carbon;
use DateTime;
use DateInterval;
use Illuminate\Database\Capsule\Manager as DB;
//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mollie\Api\MollieApiClient;
use Psr\Http\Message\UriInterface;

class EnrolmentManager
{
    private $user;
    private $mailMgr;
    private $logger;
    private $mollie;
    private $userMgr;

    function __construct($logger, Contact $user = null, MailManager $mailMgr = null, MollieApiClient $mollie = null,
                         UserManager $userMgr = null) {
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
        if (is_null($userMgr)) {
            $this->userMgr = UserManager::instance($logger);
        } else {
            $this->userMgr = $userMgr;
        }
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param $membershipTypeName
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolmentByVolunteer($orderId, $paymentMode, $membershipTypeName, $startMembershipDate = null
        , $acceptTermsDate = null){
        $this->logger->info("Enrolment by volunteer: order id $orderId, payment mode $paymentMode, membership type name $membershipTypeName");
        if (strcasecmp ($membershipTypeName, MembershipType::REGULAR) == 0) {
            $membershipType = MembershipType::regular();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::TEMPORARY) == 0) {
            $membershipType = MembershipType::temporary();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::REGULARREDUCED) == 0) {
            $membershipType = MembershipType::regularReduced();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::REGULARORG) == 0) {
            $membershipType = MembershipType::regularOrg();
        } else {
            throw new EnrolmentException("Unexpected membership type " . $membershipTypeName, EnrolmentException::UNEXPECTED_MEMBERSHIP_TYPE);
        }
        return $this->enrolment($orderId, $paymentMode, $membershipType, true, $startMembershipDate
            , $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @param $membershipTypeName
     * @param $paymentCompleted true if payment has already been done
     * @param $startMembershipDate
     * @return Payment
     * @throws EnrolmentException
     */
    // TODO: test enrolment by transfer to check correct creation of payment
    // TODO: replace other usages of payment_mode and Settings for enrolment/renewal amount
    function enrolmentByTransfer($orderId, $membershipTypeName, $paymentCompleted = false,
                                 $startMembershipDate = null, $acceptTermsDate = null){
        $this->logger->info("Enrolment by transfer: order id $orderId, payment completed $paymentCompleted, membership type name $membershipTypeName");
        if (strcasecmp ($membershipTypeName, MembershipType::REGULAR) == 0) {
            $membershipType = MembershipType::regular();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::TEMPORARY) == 0) {
            $membershipType = MembershipType::temporary();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::REGULARREDUCED) == 0) {
            $membershipType = MembershipType::regularReduced();
        } elseif (strcasecmp ($membershipTypeName, MembershipType::REGULARORG) == 0) {
            $membershipType = MembershipType::regularOrg();
        } else {
            throw new EnrolmentException("Unexpected membership type " . $membershipTypeName, EnrolmentException::UNEXPECTED_MEMBERSHIP_TYPE);
        }
        return $this->enrolment($orderId, PaymentMode::TRANSFER, $membershipType, $paymentCompleted,
            $startMembershipDate, $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @param $startMembershipDate
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolmentByStroom($orderId, $startMembershipDate = null, $acceptTermsDate = null){
        $this->logger->info("Enrolment by stroom: order id $orderId");
        return $this->enrolment($orderId, PaymentMode::STROOM, MembershipType::stroom(),
            false, $startMembershipDate, $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param $membershipType
     * @param bool $paymentCompleted
     * @param $startMembershipDate
     * @return Payment
     * @throws EnrolmentException
     */
    function enrolment($orderId, $paymentMode, $membershipType, $paymentCompleted = false,
                       $startMembershipDate = null, $acceptTermsDate = null){
        $userStateUpdated = false;
        if ($this->user->state == UserState::DISABLED) {
            // enrolment for a disabled user -> enable the user and check payment as first step of enrolment
            $this->user->state = UserState::CHECK_PAYMENT;
            $userStateUpdated = true;
        }
        // Validations
        $this->checkMembershipStateEnrolment();

        // check user info is complete
        $this->checkUserInfo();

        // check accept_terms_date is set to value between last terms update and current date
        $this->checkTermsAccepted($acceptTermsDate);

        // Prevent processing same request twice
        $this->checkDuplicateRequest($orderId);

        // Block requests for enrolments more than 1 year in future
        $this->blockFutureRequests($startMembershipDate);

        $payment = $this->lookupPaymentByOrderId($orderId, $paymentMode);
        if ($payment != null) { // payment already exists, check its state
            if ($paymentCompleted) {
                if ($payment->kb_state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::SUCCESS . " but was $payment->kb_state (orderId: $payment->kb_order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            } else {
                if ($payment->kb_state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::OPEN . " but was $payment->kb_state (orderId: $payment->kb_order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            }
        }

        // Create membership
        // FIXME: membership not created if an active membership exists... -> nok in case of e.g. temporary -> regular or stroom -> stroom enrolments
        //        but in case of multiple renewal requests, we don't want to create extra memberships with advanced end date
        //        only reuse active membership if same type and same end date?
        //        OR never reuse active membership, but only make it active when payment is completed? + only allow 1 future pending membership?

        // FIXME: status should no longer be based on user state...
//        $status = MembershipMapper::getMembershipStatus($this->user->state, $this->user->id);
        $status = MembershipState::STATUS_PENDING;
        if (empty($startMembershipDate) ) {
            $start_date = strftime('%Y-%m-%d', time());
        } else {
            $start_date = $startMembershipDate->format('Y-m-d');
        }
        $end_date = self::getMembershipEndDate($start_date, $membershipType);

        // cancel eventual pending memberships
        $pendingMemberships = $this->user->memberships()->pending()->get();
        foreach($pendingMemberships as $pending) {
            $pending->status = MembershipState::STATUS_CANCELLED;
            $pending->save();
        }
        $membership = static::createMembership($membershipType, $start_date, $end_date, $this->user, $status);
//        $membershipId = $this->createUserMembership($membershipType, $startMembershipDate);
//        $membership = Membership::findOrFail($membershipId);
        $membership->last_payment_mode = $paymentMode;
        $membership->save();
        $this->user->membership_start_date = $membership->starts_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->user->payment_mode = $membership->last_payment_mode;
        // check member role, if no member yet (eg. supporter), convert it to member
        if ($this->user->role != UserRole::ADMIN && $this->user->role != UserRole::MEMBER) {
            $this->user->role = UserRole::MEMBER;
        }
        $this->userMgr->update($this->user, false, false, false, $userStateUpdated);

        // Create payment
        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, $paymentMode, $membership->subscription->price, Settings::CURRENCY,
                PaymentState::OPEN, $membership);
        }

        if ($paymentCompleted) {
            // TODO: immediately confirm payment, but avoid too much extra mails!
            // + customize email message based on payment mode
            $this->confirmPayment($paymentMode, $payment, false, false);
        }

        // Send emails
        $this->mailMgr->sendEnrolmentConfirmation($this->user, $paymentMode, $paymentCompleted, $membership);
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
    function enrolmentByMollie($orderId, $redirectUrl, $requestedPaymentMean, UriInterface $requestUri) {
        $this->checkMembershipStateEnrolment();
        $membershipType = MembershipType::regular();
        $payment = $this->lookupPaymentByOrderId($orderId, PaymentMode::MOLLIE);
        if ($payment == null) {
            // Create new payment
            $payment = $this->createNewPayment($orderId,PaymentMode::MOLLIE, $membershipType->price,
                Settings::CURRENCY, PaymentState::OPEN);

            // Always create new membership, and only make it the active membership when payment completes
            // Note: active membership could be a temporary membership
            if ($this->user->active_membership != null && $this->user->activeMembership->status == MembershipState::STATUS_ACTIVE
                && $this->isTempMembership($this->user->activeMembership)) {
                //$activeMembership = $this->user->activeMembership()->first();
                $start_date = $this->user->activeMembership->expires_at;
            } else {
                $start_date = strftime('%Y-%m-%d', time());
            }
            $end_date = self::getMembershipEndDate($start_date, $membershipType);
            $membership = self::createMembership($membershipType, $start_date, $end_date, $this->user, MembershipState::STATUS_PENDING);

//            $membershipId = $this->createUserMembership($membershipType);
//            $membership = Membership::findOrFail($membershipId);
            DB::transaction(function() use ($payment, $membership) {
                $membership->last_payment_mode = PaymentMode::MOLLIE;
                $membership->payment()->save($payment);
                $membership->save();

                $this->user->payments()->save($payment);
            });

        } else {
            $membership = $payment->membership;
        }
        $this->user->payment_mode = $membership->last_payment_mode;
        $this->user->membership_start_date = $membership->starts_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->userMgr->update($this->user, false, false, false, false);
        //$this->user->save();
        $this->user->refresh();

        return $this->initiateMolliePayment($orderId, $membership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri, Product::ENROLMENT, $membership->expires_at);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByVolunteer($orderId, $paymentMode, $acceptTermsDate = null) {
        $this->logger->info("Renewal by volunteer: order id $orderId, payment mode $paymentMode");
        return $this->renewal($orderId, $paymentMode, true, $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByTransfer($orderId, $paymentCompleted = false, $acceptTermsDate = null) {
        $this->logger->info("Renewal by transfer: order id $orderId, payment completed $paymentCompleted");
        return $this->renewal($orderId, PaymentMode::TRANSFER, $paymentCompleted, $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @return Payment
     * @throws EnrolmentException
     */
    function renewalByStroom($orderId, $acceptTermsDate = null) {
        $this->logger->info("Renewal by stroom: order id $orderId");
        // check not yet enrolled via Stroom: Stroom membership can only be requested once
        if ($this->user->isStroomParticipant()) {
            throw new EnrolmentException("Stroom membership can only be requested once", EnrolmentException::UNEXPECTED_MEMBERSHIP_TYPE);
        }
        return $this->renewal($orderId, PaymentMode::STROOM, false, $acceptTermsDate);
    }

    /**
     * @param $orderId
     * @param $paymentMode
     * @param bool $paymentCompleted
     * @param $acceptTermsDate
     * @return Payment
     * @throws EnrolmentException
     * @throws \Exception
     */
    function renewal($orderId, $paymentMode, $paymentCompleted = false, $acceptTermsDate = null) {
        $this->checkUserStateRenewal();

        // check accept_terms_date is set to value between last terms update and current date
        $this->checkTermsAccepted($acceptTermsDate);

        // Prevent processing same request twice
        $this->checkDuplicateRequest($orderId);

        // Block requests for renewals more than 1 year in future
        $this->blockFutureRequests();

        $payment = $this->lookupPaymentByOrderId($orderId, $paymentMode);
        if ($payment != null) { // payment already exists -> check its state
            if ($paymentCompleted) {
                if ($payment->kb_state != PaymentState::SUCCESS) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::SUCCESS . " but was $payment->kb_state (orderId: $payment->kb_order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            } else {
                if ($payment->kb_state != PaymentState::OPEN) {
                    throw new EnrolmentException("Unexpected payment state: should be " . PaymentState::OPEN . " but was $payment->kb_state (orderId: $payment->kb_order_id)",
                        EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                }
            }
        }

        // identify next membership type
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
        // create payment and new membership
        if ($payment == null) {
            $payment = $this->createNewPayment($orderId, $paymentMode,$nextMembershipType->price,
                Settings::CURRENCY, PaymentState::OPEN);

            // Making sure "renew membership" is executed only once
            // -> only when a new payment is created and linked to the new membership
            $renewalMembership = $this->renewMembership($nextMembershipType, $this->user->activeMembership);
            $renewalMembership->last_payment_mode = $paymentMode;
            $renewalMembership->payment()->save($payment);
            $this->user->memberships()->save($renewalMembership);

            // Direct activation if payment is already completed
//            if ($paymentCompleted) {
//                // FIXME: already done in confirmPayment, so could be removed?
//                $this->activateRenewalMembership($membership, $renewalMembership);
//            }
            $renewalMembership->save();
        }

        if ($paymentCompleted) {
            // TODO: check amount paid against enrolment amount
            // TODO: immediately confirm payment, but avoid too much extra mails! -> set email notif to false
            // + customize email message based on payment mode
            $this->confirmPayment($paymentMode, $payment, true, false);
        }
        $activeMembership = Membership::find($this->user->active_membership);
        $this->user->payment_mode = $activeMembership->last_payment_mode;
        $this->user->membership_start_date = $activeMembership->starts_at;
        $this->user->membership_end_date = $activeMembership->expires_at;
        $this->userMgr->update($this->user, false, false, false, false);
        //$this->user->save();

        $this->mailMgr->sendRenewalConfirmation($this->user, $paymentMode, $paymentCompleted);
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
    function renewalByMollie($orderId, $redirectUrl, $requestedPaymentMean, UriInterface $requestUri) {
        $this->checkUserStateRenewal(); // deprecated, to be removed? (replaced by check on membership status)
        $membership = Membership::find($this->user->active_membership);
        if (!$membership) {
            throw new EnrolmentException("No active membership found", EnrolmentException::NOT_ENROLLED);
        }
        $this->checkMembershipStateRenewal();
        if (isset($membership->subscription->next_subscription_id)) {
            $nextMembershipType = MembershipType::find($membership->subscription->next_subscription_id);
        } else {
            $nextMembershipType = $membership->subscription;
        }

        $payment = $this->lookupPaymentByOrderId($orderId, PaymentMode::MOLLIE);
        if ($payment == null) {
            // Create new payment
            $payment = $this->createNewPayment($orderId,PaymentMode::MOLLIE, $nextMembershipType->price,
                Settings::CURRENCY, PaymentState::OPEN);
        };

        // Create renewal membership with status PENDING
        $renewalMembership = $this->renewMembership($nextMembershipType, $this->user->activeMembership);
        $renewalMembership->last_payment_mode = PaymentMode::MOLLIE;
        $renewalMembership->payment()->save($payment);
        $this->user->memberships()->save($renewalMembership);
        $renewalMembership->save();

        $this->userMgr->update($this->user, false, false, false, false);
        //$this->user->save();

        return $this->initiateMolliePayment($orderId, $renewalMembership->subscription->price, $redirectUrl,
            $requestedPaymentMean, $requestUri, Product::RENEWAL, $renewalMembership->expires_at);
    }

    function confirmMembershipPayment($paymentMode, $membershipId, $renewal = false)
    {
        $payments = Payment::forMembership()->where([
            ['membership_id', '=', $membershipId],
            ['kb_mode', '=', $paymentMode],
            ['kb_state', '=', PaymentState::OPEN]
        ])->get();

        if (empty($payments) || (is_countable($payments) ? count($payments) : 0) == 0) {
            $message = "Unexpected confirmation, no payment found for membership " . $membershipId .
                " and payment mode (" . $paymentMode . ")";
            $this->logger->warning($message);
            // note: no payment, so unable to send 'enrolment failed' notification
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        } else if ((is_countable($payments) ? count($payments) : 0) > 1) {
            $message = "Unable to process confirmation, more than 1 open payments found (first payment is ["
                . \json_encode($payments[0], JSON_THROW_ON_ERROR) . "] - second payment is [" . \json_encode($payments[1], JSON_THROW_ON_ERROR) . "])";
            $this->logger->warning($message);
            $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payments[0], $renewal, $message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_STATE);
        } else {
            $this->confirmPayment($paymentMode, $payments[0], $renewal);
        }
    }

    /**
     * Confirm an open payment
     * This method can be called directly after payment creation when the payment was already received (e.g. CASH or PAYCONIQ payment)
     * or through a separate API call after validating the payment completed (e.g. TRANSFER, STROOM)
     * MOLLIE payment should not be confirmed, as they are processed through webhook (see processMolliePayment)
     * @param $paymentMode
     * @param $renewal true if confirming a renewal
     * @param $payment payment to be confirmed. When null, payment is looked up based on user id and payment mode
     * @param $sendEmailNotif when true, an email notification will be sent to user as payment confirmation
     * @throws EnrolmentException
     * @throws \Api\Mail\EnrolmentException
     * @throws \Exception
     */
    function confirmPayment($paymentMode, Payment $payment = null, $renewal = false, $sendEmailNotif = true) {
        if ($paymentMode == PaymentMode::MOLLIE) {
            $message = "Unexpected confirmation for payment mode ($paymentMode)";
            $this->logger->warning($message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        }
        $this->checkPaymentMode($paymentMode);
        $this->checkUserStateAtPayment($this->user);

        // lookup payment
        if ($payment == null) {
            $payment = $this->lookupPaymentByPaymentMode($paymentMode, $renewal);
        }
        if ($payment->kb_state == PaymentState::SUCCESS || $payment->kb_state == PaymentState::FAILED) {
            // payment already confirmed/declined
            $message = "Unable to process confirmation, payment already confirmed/declined (payment is ["
                . \json_encode($payment, JSON_THROW_ON_ERROR) . ")";
            $this->logger->warning($message);
            $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payment, $renewal, $message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_STATE);

        }

        $membership = Membership::find($payment->membership_id);
        if ($membership->last_payment_mode != $paymentMode) {
            $message = "Unexpected confirmation for payment mode ($paymentMode), expected payment mode $membership->last_payment_mode";
            $this->logger->warning($message);
            $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payment, $renewal, $message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_MODE);
        }

        // update payment
        $payment->kb_state = PaymentState::SUCCESS;
        if ($payment->payment_method_id != null) {
            $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
        }
        $payment->save();

        // update membership status
        $currentMembership = Membership::find($this->user->active_membership);
        $this->activateMembership($currentMembership, $membership);

        // update project participants
        if ($paymentMode == PaymentMode::STROOM) {
            $this->user->addToStroomProject();
        }

        // update user
        $this->user->payment_mode = $membership->last_payment_mode;
        $this->user->membership_start_date = $membership->starts_at;
        $this->user->membership_end_date = $membership->expires_at;
        $this->user->state = $this->getUserState($membership->status);
        // update user through user manager to also sync inventory!
        $this->userMgr->update($this->user, false, false, false, true);

        // send email notifications
        if ($sendEmailNotif &&
            ($paymentMode == PaymentMode::TRANSFER || $paymentMode == PaymentMode::STROOM) ) {
            $this->mailMgr->sendEnrolmentPaymentConfirmation($this->user, $paymentMode);
        }
    }

    function getUserState($membershipStatus) {
        if ($membershipStatus == MembershipState::STATUS_ACTIVE) {
            $state = UserState::ACTIVE;
        } elseif ($membershipStatus == MembershipState::STATUS_EXPIRED) {
            $state = UserState::EXPIRED;
        } elseif ($membershipStatus == MembershipState::STATUS_PENDING) {
            $state = UserState::CHECK_PAYMENT;
        } elseif ($membershipStatus == MembershipState::STATUS_CANCELLED) {
            $state = UserState::DISABLED;
        } else {
            throw new \Exception("Invalid user state value $membershipStatus for user with id $this->user->id");
        }
        return $state;

    }

    function declineMembershipPayment($paymentMode, $user, $membershipId, $renewal = false)
    {
        $payments = Payment::forMembership()->where([
            ['membership_id', '=', $membershipId],
            ['kb_mode', '=', $paymentMode],
            ['kb_state', '=', PaymentState::OPEN]
        ])->get();

        if (empty($payments) || (is_countable($payments) ? count($payments) : 0) == 0) {
            $message = "Unexpected decline, no payment found for membership " . $membershipId .
                " and payment mode (" . $paymentMode . ")";
            $this->logger->warning($message);
            // note: no payment, so unable to send 'enrolment failed' notification
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        } else if ((is_countable($payments) ? count($payments) : 0) > 1) {
            $message = "Unable to process decline, more than 1 open payments found (first payment is ["
                . \json_encode($payments[0], JSON_THROW_ON_ERROR) . "] - second payment is [" . \json_encode($payments[1], JSON_THROW_ON_ERROR) . "])";
            $this->logger->warning($message);
            $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payments[0], $renewal, $message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_STATE);
        } else {
            $this->declinePayment($paymentMode, $user, $payments[0], $renewal);
        }
    }

    /**
     * @param $paymentMode
     * @param $user
     * @throws EnrolmentException
     */
    function declinePayment($paymentMode, $user, Payment $payment = null, $renewal = false)
    {
        $this->checkPaymentMode($paymentMode);
        $this->checkUserStateAtPayment($user);

        // update payment
        if ($payment == null) {
            $payment = $this->lookupPaymentByPaymentMode($paymentMode, false);
        }

        $payment->kb_state = PaymentState::FAILED;
        if ($payment->payment_method_id != null) {
            $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
        }
        $payment->save();

        // update status
        $membership = Membership::find($payment->membership_id);
        if ($membership->last_payment_mode != $paymentMode) {
            $message = "Unexpected decline for payment mode ($paymentMode), expected payment mode $membership->last_payment_mode";
            $this->logger->warning($message);
            $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payment, $renewal, $message);
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_MODE);
        }

        $membership->status = MembershipState::STATUS_CANCELLED; // keep a cancelled membership for history
        $membership->save();
        $user->state = UserState::DELETED;
        // update user through user manager to also sync inventory!
        $this->userMgr->update($user, false, false, false, true);
        if ($paymentMode == PaymentMode::STROOM) {
            $this->mailMgr->sendEnrolmentPaymentDecline($user, $paymentMode);
        }
    }

    /**
     * @param MembershipType $type
     * @return mixed
     * @throws \Exception
     */
    function createUserMembership(MembershipType $type, $startMembershipDate = null) {
        if (is_null($this->user->active_membership)) {
            $status = MembershipMapper::getMembershipStatus($this->user->state, $this->user->id);
            if (!empty($startMembershipDate) ) {
                $start_date = $startMembershipDate->format('Y-m-d');
            } else {
                $start_date = strftime('%Y-%m-%d', time());
            }
            $end_date = self::getMembershipEndDate($start_date, $type);
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
    function renewMembership(MembershipType $newType, Membership $membership) : Membership {
        $end_date = self::getMembershipEndDate($membership->expires_at->format('Y-m-d'), $newType);

        $renewalMembership = self::createMembership($newType, $membership->starts_at, $end_date, null, MembershipState::STATUS_PENDING);
        return $renewalMembership;
    }

    function activateMembership(?Membership $currentMembership, Membership $renewedMembership) {
        if ($currentMembership != null) {
            $currentMembership->status = MembershipState::STATUS_EXPIRED;
            $currentMembership->save();
        }

        $renewedMembership->status = MembershipState::STATUS_ACTIVE;
        $renewedMembership->save();
        if (isset($this->user)) {
            $this->logger->info("associating new membership");
            $this->user->activeMembership()->associate($renewedMembership);
            // update user through user manager to also sync inventory!
            $this->userMgr->update($this->user,
                false, false, false, false);
        }
    }

    /**
     * For yearly subscription, this method computes the end date of membership
     * Subscriptions ending in december are automatically extended until end of year
     * @param $startDateMembership (expected in 'YYYY-MM-DD' format)
     * @return string
     * @throws \Exception
     */
    public static function getMembershipEndDate($startDateMembership, $membershipType = null): string
    {
        if (! isset($membershipType) ) {
            $membershipType = MembershipType::regular();
        }
        $startDate = DateTime::createFromFormat('Y-m-d', $startDateMembership);
        if ($startDate == false) {
            throw new \InvalidArgumentException("Invalid date format (expecting 'YYYY-MM-DD'): " . $startDateMembership);
        }
        if ($membershipType->isYearlySubscription()) {
            $pivotDate = new DateTime('first day of december next year');
            $membershipEndDate = $startDate->add(new DateInterval('P1Y')); //$endDate->format('Y');
            if ($membershipEndDate > $pivotDate) { // extend membership until end of year
                $extendedEndDate = new DateTime('last day of december next year');
                if ($membershipEndDate < $extendedEndDate) {
                    $membershipEndDate = $extendedEndDate;
                }
            }
        } else {
            $membershipEndDate = $startDate->add(new DateInterval('P' . $membershipType->duration . 'D'));
        }
        return $membershipEndDate->format('Y-m-d');
    }

    /**
     * @param MembershipType $type
     * @param $start_date
     * @param $end_date
     * @param Contact $user
     * @param $status
     * @return created membership
     * @throws \Exception
     */
    public static function createMembership(MembershipType $type, $start_date, $end_date, ?Contact $user, $status) : Membership {
        $membership = new Membership();
        $membership->subscription_id = $type->id;
        $membership->starts_at = $start_date;
        $membership->expires_at = $end_date;
        if (isset($user)) {
            $membership->contact_id = $user->id;
        }
        Membership::isValidStatus($status);
        $membership->status = $status;
        $membership->save();

        if (isset($user) && $status == MembershipState::STATUS_ACTIVE) {
            $user->activeMembership()->associate($membership);
            //$this->userMgr->update($user); FIXME: -> not accessible, static method...
            $user->save();
        }
        return $membership;
    }

    /**
     * @param $orderId
     * @return Payment|null
     */
    protected function lookupPaymentByOrderId($orderId, $paymentMode, $userId = null) : Payment|null
    {
        if ($userId == null) {
            $userId = $this->user->id;
        }
        // use first() rather than get()
        // there should be only 1 result, but first returns a Model
        return Payment::where([
                ['kb_order_id', '=', $orderId],
                ['contact_id', '=', $userId],
                ['kb_mode', '=', $paymentMode],
            ])->first();
    }

    /**
     * @param $orderId
     * @return Payment
     */
    protected function createNewPayment($orderId, $mode, $amount, $currency, $state = PaymentState::OPEN,
        Membership $membership = null): Payment
    {
        $payment = new Payment();
        $payment->kb_mode = $mode;
        $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
        $payment->kb_order_id = $orderId;
        $payment->contact_id = $this->user->id;
        $payment->kb_payment_timestamp = new \DateTime();
        $payment->payment_date = new \DateTime();
        $payment->amount = $amount;
        $payment->kb_state = $state;
        $payment->type = "PAYMENT";
        if (isset($membership)) {
            $membership->payment()->save($payment);
        } else {
            $payment->save();
        }
        return $payment;
    }

    /**
     * Check if active membership is a temporary free membership
     * @param Membership $membership
     * @return bool true if active membership is a temporary free membership
     */
    protected function isTempMembership(?Membership $membership) : bool {
        if ($membership == null) {
            return false;
        }
        $this->logger->info(\json_encode($membership, JSON_THROW_ON_ERROR));
        return MembershipType::temporary()->id == $membership->subscription_id;
    }

    /**
     * Check if the enrolment request was already processed
     * @param $orderId
     */
    function checkDuplicateRequest($orderId) {
        // check if an enrolment with same order id was already processed
        if (Payment::where('kb_order_id', $orderId)->exists()) {
            throw new EnrolmentException("An enrolment with order id " . $orderId . " was already processed",
                EnrolmentException::DUPLICATE_REQUEST);
        }
    }

    /**
     * Prevent creation of an enrolment with start date more than 1 year in the future
     * @param $startMembershipDate
     * @throws EnrolmentException
     */
    function blockFutureRequests($startMembershipDate = null) {
        $pivotDate = Carbon::now()->add(1, 'year');
        if (isset($startMembershipDate) && $pivotDate->lt($startMembershipDate)) {
            throw new EnrolmentException("Enrolment start date not allowed: "
                . $startMembershipDate->format('Y-m-d') . " > max allowed date (" . $pivotDate->format('Y-m-d') . ")",
                EnrolmentException::UNEXPECTED_START_DATE);
        }
        if (isset($this->user->activeMembership)
            && $pivotDate->lt($this->user->activeMembership->expires_at)) {
            throw new EnrolmentException("Enrolment start date not allowed: "
                . $this->user->activeMembership->expires_at->format('Y-m-d') . " > max allowed date (" . $pivotDate->format('Y-m-d') . ")",
                EnrolmentException::UNEXPECTED_START_DATE);
        }
    }

    /**
     * Check all mandatory user information is available for an enrolment
     * @throws EnrolmentException
     */
    protected function checkUserInfo() {
        if (empty($this->user->first_name) ) {
            throw new EnrolmentException("User first_name is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->last_name) ) {
            throw new EnrolmentException("User last_name is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->role) ) {
            throw new EnrolmentException("User role is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->email) ) {
            throw new EnrolmentException("User email is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->address_line_1) || empty($this->user->address_line_2)) {
            throw new EnrolmentException("User address is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->address_line_4) ) {
            throw new EnrolmentException("User postal code is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
        if (empty($this->user->address_line_2) ) {
            throw new EnrolmentException("User city is missing", EnrolmentException::INCOMPLETE_USER_DATA);
        }
    }

    /**
     * check accept_terms_date is set to value between last terms update and current date
     */
    protected function checkTermsAccepted($acceptTermsDate = null) {
        if (!empty($acceptTermsDate)
         && Carbon::now()->gte($acceptTermsDate)                 // exclude future dates
         && (empty($this->user->accept_terms_date)
                || (new Carbon($this->user->accept_terms_date))->lt($acceptTermsDate)) // only update if more recent than current value
        ) {
            $this->user->accept_terms_date = $acceptTermsDate;
        }
        if (empty($this->user->accept_terms_date) ) {
            throw new EnrolmentException("User did not accept terms yet", EnrolmentException::ACCEPT_TERMS_MISSING);
        }
        if ($this->user->accept_terms_date->gt(Carbon::now())) {
            throw new EnrolmentException("Invalid accept terms date (" . $this->user->accept_terms_date->format('Y-m-d') . " is a future date)",
                EnrolmentException::ACCEPT_TERMS_MISSING);
        }
        $terms_date = Carbon::createFromFormat('Y-m-d', Settings::LAST_TERMS_DATE_UPDATE);
        if ($this->user->accept_terms_date->lt($terms_date)) {
            throw new EnrolmentException("Terms have been updated and need reapproval "
            . "(last terms update on : " .Settings::LAST_TERMS_DATE_UPDATE. ", last approval on " .$this->user->accept_terms_date->format('Y-m-d') .")",
                EnrolmentException::ACCEPT_TERMS_MISSING);
        }
    }

    /**
     * Check user state allows enrolment
     * @throws EnrolmentException
     */
    protected function checkMembershipStateEnrolment()
    {
        $this->logger->info(\json_encode($this->user->activeMembership, JSON_THROW_ON_ERROR));
        // normal case, no other checks needed
        if (!isset($this->user->activeMembership)) {
            return;
        }
        // normal case, no other checks needed
        // deprecated, kept for backward compatibility, but might no longer be needed
        // Note: DISABLED users should get a state update to CHECK_PAYMENT prior to this call
        if ($this->user->state == UserState::CHECK_PAYMENT) {
            return;
        }

        // temporary membership already active or expired
        if ($this->user->activeMembership->status == MembershipState::STATUS_ACTIVE || $this->user->state == MembershipState::STATUS_EXPIRED) {
            if ($this->isTempMembership($this->user->activeMembership) ) {
                return;
            } else {
                throw new EnrolmentException("User already enrolled, consider a renewal", EnrolmentException::ALREADY_ENROLLED);
            }
        }
        // deprecated, kept for backward compatibility, but might no longer be needed
        if ($this->user->state == UserState::ACTIVE || $this->user->state == UserState::EXPIRED) {
            if ($this->isTempMembership($this->user->activeMembership) ) {
                return;
            } else {
                throw new EnrolmentException("User already enrolled, consider a renewal", EnrolmentException::ALREADY_ENROLLED);
            }
        }
        throw new EnrolmentException("Membership status unsupported for enrolment", EnrolmentException::UNSUPPORTED_STATE);
    }

    /**
     * Check membership status allows RENEWAL
     * @throws EnrolmentException
     */
    protected function checkMembershipStateRenewal()
    {
        if (!isset($this->user->activeMembership) ||
            $this->user->activeMembership->status == MembershipState::STATUS_PENDING) {
            throw new EnrolmentException("Enrolment not yet complete, consider an enrolment", EnrolmentException::NOT_ENROLLED);
        }
        if ($this->user->activeMembership->status != MembershipState::STATUS_ACTIVE && $this->user->activeMembership->status != MembershipState::STATUS_EXPIRED) {
            throw new EnrolmentException("Membership status unsupported for renewal", EnrolmentException::UNSUPPORTED_STATE);
        }
    }

    /**
     * Check user state allows RENEWAL
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
    protected function initiateMolliePayment($orderId, $amount, $redirectUrl, $requestedPaymentMean, $requestUri, $productId, $membershipEndDate = null)
    {
        $hostname = $requestUri->getHost();
        $protocol = $requestUri->getScheme();
        $userName = "{$this->user->first_name} {$this->user->last_name}";
        if (isset($this->user->activeMembership) && $this->user->activeMembership->subscription->isCompanySubscription()) {
            $userName = $this->user->company;
        }
        if ($productId == Product::ENROLMENT) {
            $description = "Klusbib inschrijving $userName";
        } elseif ($productId == Product::RENEWAL) {
            $description = "Klusbib verlenging lidmaatschap $userName";
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
                    "user_id" => $this->user->id,
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
     * Payment confirmation from Mollie payment processor
     * Activate new membership if successful or trigger notification for manual follow up in case of failure
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
            $this->logger->info('Mollie payment:' . json_encode($paymentMollie, JSON_THROW_ON_ERROR));
            $orderId = $paymentMollie->metadata->order_id;
            $userId = $paymentMollie->metadata->user_id;
            $productId = $paymentMollie->metadata->product_id;
            $newMembershipEndDate = $paymentMollie->metadata->membership_end_date;

            $payment = $this->lookupPaymentByOrderId($orderId, PaymentMode::MOLLIE, $userId);
            if ($payment == null) { // should no longer happen, payment is created at "POST enrolment"
                $this->logger->error("POST /enrolment/$orderId failed: payment with orderid $orderId, payment mode " . PaymentMode::MOLLIE . " and user id $userId is not found");
                throw new EnrolmentException("No payment found with orderid $orderId, payment mode " . PaymentMode::MOLLIE . " and user id $userId",
                    EnrolmentException::UNKNOWN_PAYMENT);
            };
            $currentPaymentState = $payment->kb_state;
            if ($paymentMollie->isPaid() && !$paymentMollie->hasRefunds() && !$paymentMollie->hasChargebacks()) {
                /*
                 * The payment is paid and isn't refunded or charged back.
                 * At this point you'd probably want to start the process of delivering the product to the customer.
                 */
                $payment->kb_state = PaymentState::SUCCESS;
            } elseif ($paymentMollie->isOpen()) {
                /*
                 * The payment is open.
                 */
                $payment->kb_state = PaymentState::OPEN;
            } elseif ($paymentMollie->isPending()) {
                /*
                 * The payment is pending.
                 */
                $payment->kb_state = PaymentState::PENDING;
            } elseif ($paymentMollie->isFailed()) {
                /*
                 * The payment has failed.
                 */
                $payment->kb_state = PaymentState::FAILED;
            } elseif ($paymentMollie->isExpired()) {
                /*
                 * The payment is expired.
                 */
                $payment->kb_state = PaymentState::EXPIRED;
            } elseif ($paymentMollie->isCanceled()) {
                /*
                 * The payment has been canceled.
                 */
                $payment->kb_state = PaymentState::CANCELED;
            } elseif ($paymentMollie->hasRefunds()) {
                /*
                 * The payment has been (partially) refunded.
                 * The status of the payment is still "paid"
                 */
                $payment->kb_state = PaymentState::REFUND;
            } elseif ($paymentMollie->hasChargebacks()) {
                /*
                 * The payment has been (partially) charged back.
                 * The status of the payment is still "paid"
                 */
                $payment->kb_state = PaymentState::CHARGEBACK;
            }
            $this->logger->info("Saving payment for orderId $orderId with state $payment->kb_state (Mollie payment id=$paymentId / Internal payment id = $payment->id)");

            if ($currentPaymentState == $payment->kb_state) {
                $this->logger->info("Payment with id $payment->id and state $payment->kb_state already up to date");
                // no change in state -> no need to reprocess Mollie payment (and avoid to resend notifications)
                // FIXME: payment is saved before membership/user. In case of problems processing request (e.g. http error 502)
                //        inconsistencies can be introduced -> should be executed in a dabatase transaction!
                //if ($payment->membership->status == MembershipState::STATUS_ACTIVE && $payment->kb_state == PaymentState::SUCCESS)
                //{
                //    $this->logger->info("Successful payment with id $payment->id consistent with membership state "
                //        . "-> skip activation and email notifications");
                //    return;
                //}
                return;
            }
            //$payment->save();

            // Lookup user and update state
            $user = \Api\Model\Contact::find($userId);
            if (null == $user) {
                $this->logger->error("POST /enrolment/$orderId failed: user $userId is not found");
                throw new EnrolmentException("No user found with id $userId", EnrolmentException::UNKNOWN_USER);
            } else {
                $this->user = $user;
            }

            $membership = $payment->membership;
            //if ($user->activeMembership != null) {
            //    $membership = $user->activeMembership()->first(); // lookup active membership
            //} else {
            //    $membership = $user->memberships()->pending()->where('last_payment_mode', PaymentMode::MOLLIE)->first();
            //}
            if (null == $membership) {
                $this->logger->error("POST /enrolment/$orderId failed: user $userId is not enrolled");
                throw new EnrolmentException("User with id $userId is not enrolled", EnrolmentException::NOT_ENROLLED);
            }

            if ($productId == \Api\Model\Product::ENROLMENT) {
                if ($payment->kb_state == PaymentState::SUCCESS) {
                    if ($membership->status != MembershipState::STATUS_ACTIVE) {

                        DB::transaction(function() use ($payment, $user, $membership) {
                            if ($payment->payment_method_id != null) {
                                $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
                            }                    
                            $payment->save();

                            $currentMembership = null;
                            // new enrolment can also be initiated for users already having a membership (causes membership to start on current day instead of expiry day)
                            if (isset($user->active_membership)) {
                                $currentMembership = Membership::find($user->active_membership);
                            }
                            $this->activateMembership($currentMembership, $membership);

                            // update user data
                            $user->state = UserState::ACTIVE;
                            $user->payment_mode = $membership->last_payment_mode;
                            $user->membership_start_date = $membership->starts_at;
                            $user->membership_end_date = $membership->expires_at;
                            $this->userMgr->update($user, false, false, false, true);

                            // send confirmation to new member
                            $this->mailMgr->sendEnrolmentConfirmation($user, PaymentMode::MOLLIE, $membership);
                            // send notification to Klusbib team
                            $this->mailMgr->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL,$user, false);
                        });
                    }
                } else if ($payment->kb_state == PaymentState::FAILED
                    || $payment->kb_state == PaymentState::EXPIRED
                    || $payment->kb_state == PaymentState::CANCELED
                    || $payment->kb_state == PaymentState::REFUND
                    || $payment->kb_state == PaymentState::CHARGEBACK) {
                    // Permanent failure, or special case -> send notification for manual follow up
                    $this->mailMgr->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL, $user, $payment, false, "payment failed");
                }
            } else if ($productId == \Api\Model\Product::RENEWAL) {
                if ($payment->kb_state == PaymentState::SUCCESS) {
                    // FIXME: should be based on membership status instead of user state!
                    if ($user->state == UserState::ACTIVE
                        || $user->state == UserState::EXPIRED) {

                        DB::transaction(function() use ($payment, $user) {
                            if ($payment->payment_method_id != null) {
                                $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
                            }
                            $payment->save();

                            $renewalMembership = $payment->membership()->first();

                            if (isset($renewalMembership)) {
                                $currentMembership = null;
                                if (isset($user->active_membership)) {
                                    $currentMembership = Membership::find($user->active_membership);
                                }
                                $this->activateMembership($currentMembership, $renewalMembership);
                            } else {
                                $errorMsg = "Successful mollie payment received, but no linked membership (payment=" . \json_encode($payment, JSON_THROW_ON_ERROR) . ")";
                                $this->logger->warning($errorMsg);
                                $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $user, $payment, true, $errorMsg);
                                throw new EnrolmentException( $errorMsg, EnrolmentException::UNEXPECTED_CONFIRMATION);
                            }
                            $user->state = UserState::ACTIVE;
                            $user->payment_mode = $renewalMembership->last_payment_mode;
                            $user->membership_start_date = $renewalMembership->starts_at;
                            $user->membership_end_date = $renewalMembership->expires_at;
                            $this->userMgr->update($user, false, false, false, true);

                            // send confirmation to new member
                            $this->mailMgr->sendRenewalConfirmation($user, PaymentMode::MOLLIE);
                            // send notification to Klusbib team
                            $this->mailMgr->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL, $user, true);
                        });
                    } else {
                        $errorMsg = "Successful mollie payment received, but unexpected user state " . $user->state;
                        $this->logger->warning($errorMsg);
                        $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $user, true, $errorMsg);
                        throw new EnrolmentException( $errorMsg, EnrolmentException::UNEXPECTED_CONFIRMATION);
                    }
                } else if ($payment->kb_state == PaymentState::FAILED
                    || $payment->kb_state == PaymentState::EXPIRED
                    || $payment->kb_state == PaymentState::CANCELED
                    || $payment->kb_state == PaymentState::REFUND
                    || $payment->kb_state == PaymentState::CHARGEBACK) {
                    // update renewal membership status
                    DB::transaction(function() use ($payment, $user) {

                        if ($payment->payment_method_id != null) {
                            $payment->payment_method_id = PaymentMode::getPaymentMethodId($payment->kb_mode);
                        }
                        $payment->save();

                        // TODO: check if previous membership needs to be reactivated? (is automatically expired when renewal starts)
                        $renewalMembership = $payment->membership()->first();
                        $renewalMembership->status = MembershipState::STATUS_CANCELLED;
                        $renewalMembership->save();

                        // Permanent failure, or special case -> send notification for manual follow up
                        $this->mailMgr->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL,$user, $payment, true, "payment failed");
                        // FIXME: to check: no exception thrown, failed confirmation should be accepted??
                    });
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
        if (!PaymentMode::isValidPaymentMode($paymentMode)
            || $paymentMode == PaymentMode::UNKNOWN
            || $paymentMode == PaymentMode::NONE
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

    /**
     * @param $paymentMode
     * @param $renewal
     * @param Payment $payment
     * @return Payment|null
     * @throws EnrolmentException
     */
    private function lookupPaymentByPaymentMode($paymentMode, $renewal)
    {
        $payments = Payment::forMembership()->where([
            ['contact_id', '=', $this->user->id],
            ['kb_mode', '=', $paymentMode]
        ])->get();

        if (empty($payments) || (is_countable($payments) ? count($payments) : 0) == 0) {
            $message = "Unexpected confirmation, no payment found for user " . $this->user->first_name .
                " (" . $this->user->id . ") for payment mode (" . $paymentMode . ")";
            $this->logger->warning($message);
            // note: no payment, so unable to send 'enrolment failed' notification
            throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_CONFIRMATION);
        } else {
            $this->logger->info("payments found: " . \json_encode($payments, JSON_THROW_ON_ERROR));
        }
        if ((is_countable($payments) ? count($payments) : 0) == 1) {
            $payment = $payments[0];
        } else {
            // more than 1 payment, search OPEN payment
            $payment = null;
            foreach ($payments as $p) {
                if ($p->kb_state == PaymentState::OPEN) {
                    if ($payment == null) {
                        $payment = $p;
                    } else { // more than 1 OPEN payment
                        $message = "Unable to process confirmation, more than 1 open payments found (first payment is ["
                            . \json_encode($payment, JSON_THROW_ON_ERROR) . "] - second payment is [" . \json_encode($p, JSON_THROW_ON_ERROR) . "])";
                        $this->logger->warning($message);
                        $this->mailMgr->sendEnrolmentFailedNotification(ENROLMENT_NOTIF_EMAIL, $this->user, $payment, $renewal, $message);
                        throw new EnrolmentException($message, EnrolmentException::UNEXPECTED_PAYMENT_STATE);
                    }
                }
            }
        }
        return $payment;
    }
}