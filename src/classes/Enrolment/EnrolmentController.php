<?php

namespace Api\Enrolment;

use Api\Model\MembershipType;
use Api\Util\HttpResponseCode;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Model\PaymentMode;
use Api\Model\Payment;
use Api\Model\UserState;
use Api\Mail\MailManager;
use Api\Enrolment\EnrolmentManager;
use Api\Enrolment\EnrolmentFactory;
use Api\Authorisation;
use Api\AccessType;
use Api\Model\User;
use Api\Exception\EnrolmentException;
use Slim\Middleware\JwtAuthentication;

class EnrolmentController
{
    protected $logger;
    protected $enrolmentFactory;
    protected $jwtAuthentication;
    protected $token;

    public function __construct($logger, EnrolmentFactory $enrolmentFactory, JwtAuthentication $jwtAuthentication,
                                $token) {
        $this->logger = $logger;
        $this->enrolmentFactory = $enrolmentFactory;
        $this->jwtAuthentication = $jwtAuthentication;
        $this->token = $token;
    }

    /**
     * Launches the enrolment operation
     * The user needs to be created prior to this operation
     * This operation is normally terminated by a POST to /enrolment_confirm or /enrolment_decline
     *
     * For online payment with MOLLIE an extra notification is received to process the enrolment
     * as POST to /enrolment/{orderId}
     */
    public function postEnrolment($request, $response, $args)
    {
        $this->logger->info("Klusbib POST '/enrolment' route");

        // Get data
        $data = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($data));
        if (empty($data["paymentMode"]) || !isset($data["userId"])) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
            ->withJson("Missing or invalid data: $message");
        }
        if (empty($data["orderId"])) {
            $message = "no orderId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
            ->withJson("Missing or invalid data: $message");
        }

        $paymentMode = $data["paymentMode"];
        if ($paymentMode == PaymentMode::MOLLIE && empty($data["redirectUrl"])) {
            $message = "no redirectUrl provided (required for online payment)";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
            ->withJson("Missing or invalid data: $message");
        }

        $userId = $data["userId"];
        $user = \Api\Model\User::find($userId);
        $orderId = $data["orderId"];
        $renewal = false;
        if (isset($data["renewal"]) && $data["renewal"] == true) {
            $renewal = true;
        }
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withJson("No user found with id $userId");
        }

        if (!empty($data["acceptTermsDate"]) ) {
            $acceptTermsDate = Carbon::createFromFormat('Y-m-d', $data["acceptTermsDate"]);
            if (Carbon::now()->lt($acceptTermsDate)) {

                $message = "Not possible to accept future terms";
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
            }
        } else {
            $acceptTermsDate = null;
        }
        if (empty($data["startMembershipDate"])) {
            $startMembershipDate = null;
        } else {
            if ($renewal) {
                $message = "startMembershipDate not allowed for membership renewal";
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                ->withJson("Missing or invalid data: $message");
            }
            $startMembershipDate = Carbon::createFromFormat('Y-m-d', $data["startMembershipDate"]);
        }

        if (empty($data["membershipType"])) { // set default values for backward compatibility
            if ($paymentMode == PaymentMode::STROOM) {
                $membershipType = MembershipType::STROOM;
            } else {
                if ($renewal) {
                    $membershipType = MembershipType::RENEWAL;
                } else {
                    $membershipType = MembershipType::REGULAR;
                }
            }
        } else {
            $membershipType = $data["membershipType"];
            // Make case insensitive
            if (strtoupper($membershipType) == strtoupper(MembershipType::REGULAR)) {
                $membershipType = MembershipType::REGULAR;
            } elseif (strtoupper($membershipType) == strtoupper(MembershipType::RENEWAL)) {
                $membershipType = MembershipType::RENEWAL;
            } elseif (strtoupper($membershipType) == strtoupper(MembershipType::STROOM)) {
                $membershipType = MembershipType::STROOM;
            } elseif (strtoupper($membershipType) == strtoupper(MembershipType::TEMPORARY)) {
                $membershipType = MembershipType::TEMPORARY;
            }

            if ($membershipType != MembershipType::REGULAR
                && $membershipType != MembershipType::RENEWAL
                && $membershipType != MembershipType::STROOM
                && $membershipType != MembershipType::TEMPORARY) {
                $message = "Unknown/unsupported membership type: " . $membershipType;
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                ->withJson("Missing or invalid data: $message");
            }

            // Validate data consistency
            if ($renewal && !$user->activeMembership()->exists()) {
                $message = "Renewal flag set but no active membership";
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                ->withJson("Missing or invalid data: $message");
            }
            if ($membershipType == MembershipType::REGULAR) {
                // Renewal with REGULAR membership is possible when current membership is TEMPORARY
                // but only makes sense if membership is still active
                if (  $user->activeMembership()->exists()
                    // check next membership type is 'regular'?
                    && isset($user->activeMembership->subscription)
                    && MembershipType::regular()->id === $user->activeMembership->subscription->next_subscription_id) {

                    // FIXME: how to handle this if a start membership date is provided? Just skip and assume provided date is the one to use? (and thus disable renewal flag!)
                    if (Carbon::now()->lt($user->activeMembership->expires_at) ) {
                        // temporary membership still active -> force renewal to start new membership after expiration
                        $this->logger->info("Regular enrolment on active temporary membership -> processed as renewal (membership id " . $user->activeMembership->id . ")");
                        $renewal = true;
                    } else {
                        $this->logger->info("Regular enrolment on expired temporary membership -> processed as new enrolment (membership id " . $user->activeMembership->id . ")");
                        $renewal = false;
                    }
                }

                if ($paymentMode == PaymentMode::STROOM) {
                    $message = "Payment mode Stroom specified, but membership type is set to " . $membershipType;
                    return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
                }
            } else if ($membershipType == MembershipType::RENEWAL) {
                if (!$renewal) {
                    $renewal = true;
                }
                if ($paymentMode == PaymentMode::STROOM) {
                    $message = "Payment mode Stroom specified, but membership type is set to " . $membershipType;
                    return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
                }
            } else if ($membershipType == MembershipType::STROOM) {
                if ($paymentMode != PaymentMode::STROOM) {
                    $message = "Stroom membership type specified, but payment mode set to " . $paymentMode;
                    return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
                }
            } else if ($membershipType == MembershipType::TEMPORARY) {
                if ($renewal) {
                    $message = "Renewal flag set for temporary membership type";
                    return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
                }
                if ($paymentMode == PaymentMode::STROOM) {
                    $message = "Payment mode Stroom specified, but membership type is set to " . $membershipType;
                    return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                    ->withJson("Missing or invalid data: $message");
                }
            }
        }
        $paymentCompleted = false;
        if ($paymentMode == PaymentMode::CASH
            || $paymentMode == PaymentMode::PAYCONIQ
            || $paymentMode == PaymentMode::LETS
            || $paymentMode == PaymentMode::OVAM
            || $paymentMode == PaymentMode::MBON
            || $paymentMode == PaymentMode::KDOBON
            || $paymentMode == PaymentMode::SPONSORING
            || $paymentMode == PaymentMode::OTHER) {
            $paymentCompleted = true;
        } elseif ($paymentMode == PaymentMode::TRANSFER
            && isset($data["paymentCompleted"])
            && ($data["paymentCompleted"] || strcasecmp ($data["paymentCompleted"], 'true') == 0 )) {
            // boolean true or string value "true"
            $paymentCompleted = true;
        }

        // registering a completed payment requires admin rights
        // Only admin can specify explicit membership start date
        // we still need to do authentication, as this is skipped for /enrolment route
        if ($paymentCompleted || !empty($startMembershipDate)) {

            /* If token cannot be found return with 401 Unauthorized. */
            if (false === $token = $this->jwtAuthentication->fetchToken($request)) {
                return $this->jwtAuthentication->error($request, $response->withStatus(HttpResponseCode::UNAUTHORIZED), [
                    "message" => $this->jwtAuthentication->getMessage()
                ]);
            }

            /* If token cannot be decoded return with 401 Unauthorized. */
            if (false === $decoded = $this->jwtAuthentication->decodeToken($token)) {
                return $this->jwtAuthentication->error($request, $response->withStatus(HttpResponseCode::UNAUTHORIZED), [
                    "message" => $this->jwtAuthentication->getMessage(),
                    "token" => $token
                ]);
            }
            $this->logger->info("Authentication ok for token: " . json_encode($decoded));
            $this->token->hydrate($decoded);

            $currentUser = User::find($this->token->getSub());
            if (!isset($currentUser)) {
                $this->logger->warn("No user found for token " . $this->token->getSub());
                return $response->withStatus(HttpResponseCode::FORBIDDEN)
                    ->withJson("{ message: 'Not allowed, please login with an admin user'}");
            }
            if (!$currentUser->isAdmin()) {
                $this->logger->warn("Enrolment attempt for payment mode $paymentMode by user "
                    . $currentUser->firstName . " " . $currentUser->lastName . "("
                    . $this->token->getSub() . ")");
                return $response->withStatus(HttpResponseCode::FORBIDDEN)
                    ->withJson("{ message: 'Not allowed, please login with an admin user'}");
            }
        }

        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger, $user);
        // Check payment mode
        if ($paymentMode == PaymentMode::TRANSFER) {
            try {
                if ($renewal) {
                    $payment = $enrolmentManager->renewalByTransfer($orderId, $paymentCompleted, $acceptTermsDate);
                } else {
                    $payment = $enrolmentManager->enrolmentByTransfer($orderId, $membershipType, $paymentCompleted,
                        $startMembershipDate, $acceptTermsDate);
                }
            } catch (EnrolmentException $e) {
                return $this->handleEnrolmentException($response, $e, $user);
            }

            $data = array();
            $data["orderId"] = $orderId;
            $data["paymentMode"] = $payment->mode;
            $data["paymentState"] = $payment->state;
            return $response->withStatus(HttpResponseCode::OK)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        if ($paymentMode == PaymentMode::MOLLIE) {
            $orderId = $data["orderId"];
//        $orderId = $userId . "-" . date('YmdHis'); //YYYYMMDDhhmmss
            $redirectUrl = $data["redirectUrl"];
            $requestedPaymentMean = null;
            if (isset($data["paymentMean"])) {
                $requestedPaymentMean = $data["paymentMean"];
            }
            try {
                if ($renewal) {
                    $molliePayment = $enrolmentManager->renewalByMollie($orderId, $redirectUrl, $requestedPaymentMean, $request->getUri());
                } else {
                    $molliePayment = $enrolmentManager->enrolmentByMollie($orderId, $redirectUrl, $requestedPaymentMean, $request->getUri());
                }
                $data = array();
                $data["checkoutUrl"] = $molliePayment->getCheckoutUrl();
                $data["orderId"] = $orderId;
                return $response->withStatus(HttpResponseCode::OK)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } catch (EnrolmentException $e) {
                $this->logger->error("Enrolment exception: " . $e->getMessage());
                return $this->handleEnrolmentException($response, $e, $user);
            }
        }
        // manual enrolment (cash or lets or ..., arranged with volunteer in klusbib)

        // enrolment / renewal by volunteer
        if ($paymentMode == PaymentMode::CASH
            || $paymentMode == PaymentMode::PAYCONIQ
            || $paymentMode == PaymentMode::LETS
            || $paymentMode == PaymentMode::OVAM
            || $paymentMode == PaymentMode::MBON
            || $paymentMode == PaymentMode::KDOBON
            || $paymentMode == PaymentMode::SPONSORING
            || $paymentMode == PaymentMode::OTHER
        ) {

            try {
                if ($renewal) {
                    $payment = $enrolmentManager->renewalByVolunteer($orderId, $paymentMode, $acceptTermsDate);
                } else {
                    $payment = $enrolmentManager->enrolmentByVolunteer($orderId, $paymentMode, $membershipType,
                        $startMembershipDate, $acceptTermsDate);
                }
                $data = array();
                $data["orderId"] = $orderId;
                $data["paymentMode"] = $payment->mode;
                $data["paymentState"] = $payment->state;
                return $response->withStatus(HttpResponseCode::OK)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            } catch (EnrolmentException $e) {
                return $this->handleEnrolmentException($response, $e, $user);
            }

        }

        // enrolment in STROOM project
        if ($paymentMode == PaymentMode::STROOM) {
            $this->logger->info("enrolment for STROOM project; user=" . \json_encode($user) . ";orderId=" . $orderId);
            try {
                if ($renewal) {
                    $payment = $enrolmentManager->renewalByStroom($orderId, $acceptTermsDate);
                } else {
                    $payment = $enrolmentManager->enrolmentByStroom($orderId, $startMembershipDate, $acceptTermsDate);
                }
                $data = array();
                $data["orderId"] = $orderId;
                $data["paymentMode"] = $payment->mode;
                $data["paymentState"] = $payment->state;
                return $response->withStatus(HttpResponseCode::OK)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            } catch (EnrolmentException $e) {
                return $this->handleEnrolmentException($response, $e, $user);
            }

        }

        $message = "Unsupported payment mode ($paymentMode)";
        $this->logger->warn("Invalid POST request on /enrolment received: $message");
        return $response->withStatus(HttpResponseCode::BAD_REQUEST) // Bad request
        ->withJson($message);
    }

    public function postEnrolmentConfirm($request, $response, $args) {
        $this->logger->info("Klusbib POST '/enrolment_confirm' route");

        $access = Authorisation::checkEnrolmentAccess($this->token, "confirm");
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN); // Unauthorized
        }

        // Get data
        $data = $request->getParsedBody();
        if (empty($data["paymentMode"]) || !isset($data["userId"])) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
            ->withJson("Missing or invalid data: $message");
        }
        $paymentMode = $data["paymentMode"];
        $userId = $data["userId"];
        $user = \Api\Model\User::find($userId);
        $renewal = false;
        if (isset($data["renewal"]) && $data["renewal"] == true) {
            $renewal = true;
        }
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withJson("No user found with id $userId");
        }

        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger, $user);
        try {
            $enrolmentManager->confirmPayment($paymentMode, $renewal);
            $data = array();
            return $response->withStatus(HttpResponseCode::OK)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (EnrolmentException $e) {
            if ($e->getCode() == EnrolmentException::UNEXPECTED_PAYMENT_MODE) {
                $message = "Unexpected payment mode ($paymentMode)";
                $this->logger->warn("Invalid POST request on /enrolment/confirm received: $message");
                return $response->withStatus(HttpResponseCode::BAD_REQUEST) // Bad request
                ->withJson($message);
            } else if ($e->getCode() == EnrolmentException::UNEXPECTED_CONFIRMATION) {
                $message = "Unexpected confirmation for payment mode ($paymentMode): ". $e->getMessage();
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                ->withJson($message);
            } else {
                return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)// Internal error
                ->withJson($e->getMessage());
            }
        }
    }

    public function postEnrolmentDecline($request, $response, $args)
    {
        $this->logger->info("Klusbib POST '/enrolment_decline' route");

        $access = Authorisation::checkEnrolmentAccess($this->token, "decline");
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN); // Unauthorized
        }

        // Get data
        $data = $request->getParsedBody();
        if (empty($data["paymentMode"]) || !isset($data["userId"])) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
            ->withJson("Missing or invalid data: $message");
        }
        $paymentMode = $data["paymentMode"];
        $userId = $data["userId"];
        $user = \Api\Model\User::find($userId);
        $renewal = false;
        if (isset($data["renewal"]) && $data["renewal"] == true) {
            $renewal = true;
        }
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withJson("No user found with id $userId");
        }
        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger, $user);
        try {
            $enrolmentManager->declinePayment($paymentMode,$user);
            $data = array();
            return $response->withStatus(HttpResponseCode::OK)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (EnrolmentException $e) {
            if ($e->getCode() == EnrolmentException::UNEXPECTED_PAYMENT_MODE) {
                $message = "Unsupported payment mode ($paymentMode)";
                $this->logger->warn("Invalid POST request on /enrolment/decline received: $message");
                return $response->withStatus(HttpResponseCode::BAD_REQUEST) // Bad request
                ->withJson($message);
            } else if ($e->getCode() == EnrolmentException::UNEXPECTED_CONFIRMATION) {
                $message = "Unexpected (declined) confirmation for payment mode ($paymentMode)";
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)// Bad request
                ->withJson($message);
            } else {
                return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)// Internal error
                ->withJson($e->getMessage());
            }
        }
    }
    /**
     * Confirmation from payment processor (Mollie) on enrolment order
     */
    public function postEnrolmentOrder($request, $response, $args) {
        $this->logger->info("Klusbib POST '/enrolment/{$args['orderId']}' route");
        if (empty($args['orderId'])) {
            $this->logger->error("POST /enrolment/{orderId} failed due to missing orderId param");
            return $response->withStatus(HttpResponseCode::BAD_REQUEST) // Bad request
            ->withJson("Missing or empty orderId");
        }
        // Get data
        $data = $request->getParsedBody();
        $paymentId = $_POST["id"];
        $orderId = $args['orderId'];
        if (empty($paymentId)) {
            $this->logger->error("POST /enrolment/{orderId} failed due to missing id param (orderId=" . $orderId . "; parsed body=" . json_encode($data));
            return $response->withStatus(HttpResponseCode::BAD_REQUEST) // Bad request
            ->withJson("Missing or empty paymentId");
        }

        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger);
        try {
            $enrolmentManager->processMolliePayment($paymentId);
        } catch (EnrolmentException $e) {

            if ($e->getCode() == EnrolmentException::UNKNOWN_USER || $e->getCode() == EnrolmentException::UNKNOWN_PAYMENT) {
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->withJson($e->getMessage());;
            } elseif ($e->getCode() == EnrolmentException::MOLLIE_EXCEPTION) {
                return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)// Internal error
                ->withJson($e->getMessage());
            } else {
                return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)// Internal error
                ->withJson($e->getMessage());
            }
        }

        return $response->withStatus(HttpResponseCode::OK)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode([], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }

    /**
     * @param $response
     * @param $e
     * @param $user
     * @return mixed
     */
    private function handleEnrolmentException($response, $e, $user)
    {
        if ($e->getCode() == EnrolmentException::ALREADY_ENROLLED) {
            $response_data = array("message" => $e->getMessage(),
                "membership_end_date" => $user->membership_end_date);
            return $response->withStatus(HttpResponseCode::ALREADY_REPORTED)// 208 = Already Reported
            ->withHeader("Content-Type", "application/json")
                ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else if ($e->getCode() == EnrolmentException::NOT_ENROLLED) {
            $response_data = array("message" => "User not yet enrolled (state=" . $user->state . "), please proceed to enrolment");
            return $response->withStatus(HttpResponseCode::FORBIDDEN)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else if ($e->getCode() == EnrolmentException::UNSUPPORTED_STATE) {
            $response_data = array("message" => "Enrolment not supported for user state " . $user->state);
            return $response->withStatus(HttpResponseCode::NOT_IMPLEMENTED)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else if ($e->getCode() == EnrolmentException::INCOMPLETE_USER_DATA
            || $e->getCode() == EnrolmentException::ACCEPT_TERMS_MISSING
            || $e->getCode() == EnrolmentException::DUPLICATE_REQUEST
            || $e->getCode() == EnrolmentException::UNEXPECTED_START_DATE
            || $e->getCode() == EnrolmentException::UNEXPECTED_PAYMENT_MODE
            || $e->getCode() == EnrolmentException::UNEXPECTED_MEMBERSHIP_TYPE
        ) {
            $response_data = array("message" => "Invalid request: " . $e->getMessage());
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $response_data = array("message" => "Unexpected enrolment exception: " . $e->getMessage());
            return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }
}