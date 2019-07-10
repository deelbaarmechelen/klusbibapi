<?php

namespace Api\Enrolment;

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
     * This operation is normally terminated by a POST to /enrolment_confirm
     *
     * For online payment with MOLLIE an extra notification is received to process the enrolment
     * as POST to /enrolment/{orderId}
     */
    public function postEnrolment($request, $response, $args) {
        $this->logger->info("Klusbib POST '/enrolment' route");

        // Get data
        $data = $request->getParsedBody();
        if (empty($data["paymentMode"]) || !isset($data["userId"]) ) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(400) // Bad request
            ->withJson("Missing or invalid data: $message");
        }
        if (empty($data["orderId"])  ) {
            $message = "no orderId provided";
            return $response->withStatus(400) // Bad request
            ->withJson("Missing or invalid data: $message");
        }

        $paymentMode = $data["paymentMode"];
        if ($paymentMode == PaymentMode::MOLLIE && empty($data["redirectUrl"])  ) {
            $message = "no redirectUrl provided (required for online payment)";
            return $response->withStatus(400) // Bad request
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
            return $response->withStatus(400)
                ->withJson("No user found with id $userId");;
        }
        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger, $user);
        // Check payment mode
        if ($paymentMode == PaymentMode::TRANSFER) {
            try {
                if ($renewal) {
                    $payment = $enrolmentManager->renewalByTransfer($orderId);
                } else {
                    $payment = $enrolmentManager->enrolmentByTransfer($orderId);
                }
            } catch (\Api\Exception\EnrolmentException $e) {
                if ($e->getCode() == \Api\Exception\EnrolmentException::ALREADY_ENROLLED) {
                    $response_data = array("message" => $e->getMessage(),
                        membership_end_date => $user->membership_end_date);
                    return $response->withStatus(208) // 208 = Already Reported
                    ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == \Api\Exception\EnrolmentException::NOT_ENROLLED) {
                    $response_data = array("message" => "User not yet active (" . $user->state . "), please proceed to enrolment");
                    return $response->withStatus(403)
                        ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == \Api\Exception\EnrolmentException::UNSUPPORTED_STATE) {
                    $response_data = array("message" => "Enrolment not supported for user state " . $user->state);
                    return $response->withStatus(501)
                        ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }

            $data = array();
            $data["orderId"] = $orderId;
            $data["paymentMode"] = $payment->mode;
            $data["paymentState"] = $payment->state;
            return $response->withStatus(200)
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
                return $response->withStatus(200)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } catch (EnrolmentException $e) {
                if ($e->getCode() == EnrolmentException::ALREADY_ENROLLED) {
                    $response_data = array("message" => $e->getMessage(),
                        membership_end_date => $user->membership_end_date,
                        orderId => $orderId);
                    return $response->withStatus(208)// 208 = Already Reported
                    ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == EnrolmentException::NOT_ENROLLED) {
                    $response_data = array("message" => "User not yet active (" . $user->state . "), please proceed to enrolment");
                    return $response->withStatus(403)
                        ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == EnrolmentException::UNSUPPORTED_STATE) {
                    $response_data = array("message" => "Enrolment not supported for user state " . $user->state);
                    return $response->withStatus(501)// Unsupported
                    ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == EnrolmentException::MOLLIE_EXCEPTION) {
                    return $response->withStatus(500)// Internal error
                    ->withJson($e->getMessage());
                }
            }
        }
        // manual enrolment (cash or lets or ..., arranged with volunteer in klusbib)
        if ($paymentMode == PaymentMode::CASH
            || $paymentMode == PaymentMode::PAYCONIQ
            || $paymentMode == PaymentMode::LETS
            || $paymentMode == PaymentMode::OVAM
            || $paymentMode == PaymentMode::MBON
            || $paymentMode == PaymentMode::SPONSORING
            || $paymentMode == PaymentMode::OTHER
        ) { // those payment modes require admin rights
            // we still need to do authentication, as this is skipped for /enrolment route
            /* If token cannot be found return with 401 Unauthorized. */
            if (false === $token = $this->jwtAuthentication->fetchToken($request)) {
                return $this->jwtAuthentication->error($request, $response->withStatus(401), [
                    "message" => $this->jwtAuthentication->getMessage()
                ]);
            }

            /* If token cannot be decoded return with 401 Unauthorized. */
            if (false === $decoded = $this->jwtAuthentication->decodeToken($token)) {
                return $this->jwtAuthentication->error($request, $response->withStatus(401), [
                    "message" => $this->jwtAuthentication->getMessage(),
                    "token" => $token
                ]);
            }
            $this->logger->info("Authentication ok for token: " . json_encode($decoded));
            $this->token->hydrate($decoded);

            $currentUser = User::find($this->token->getSub());
            if (!isset($currentUser)) {
                $this->logger->warn("No user found for token " . $this->token->getSub());
                return $response->withStatus(403)
                    ->withJson("{ message: 'Not allowed, please login with an admin user'}");
            }
            if (!$currentUser->isAdmin()) {
                $this->logger->warn("Enrolment attempt for payment mode $paymentMode by user "
                    . $currentUser->firstName ." " . $currentUser->lastName . "("
                    . $this->token->getSub() . ")");
                return $response->withStatus(403)
                    ->withJson("{ message: 'Not allowed, please login with an admin user'}");
            }
            try {
                if ($renewal) {
                    $payment = $enrolmentManager->renewalByVolunteer($orderId, $paymentMode);
                } else {
                    $payment = $enrolmentManager->enrolmentByVolunteer($orderId, $paymentMode);
                }
                $data = array();
                $data["orderId"] = $orderId;
                $data["paymentMode"] = $payment->mode;
                $data["paymentState"] = $payment->state;
                return $response->withStatus(200)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            } catch (EnrolmentException $e) {
                if ($e->getCode() == EnrolmentException::ALREADY_ENROLLED) {
                    $response_data = array("message" => $e->getMessage(),
                        membership_end_date => $user->membership_end_date);
                    return $response->withStatus(208) // 208 = Already Reported
                    ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == EnrolmentException::NOT_ENROLLED) {
                    $response_data = array("message" => "User not yet active (" . $user->state . "), please proceed to enrolment");
                    return $response->withStatus(403)
                        ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                } else if ($e->getCode() == EnrolmentException::UNSUPPORTED_STATE) {
                    $response_data = array("message" => "Enrolment not supported for user state " . $user->state);
                    return $response->withStatus(501)
                        ->withHeader("Content-Type", "application/json")
                        ->write(json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                }
            }

        }

        $message = "Unsupported payment mode ($paymentMode)";
        $this->logger->warn("Invalid POST request on /enrolment received: $message");
        return $response->withStatus(400) // Bad request
        ->withJson($message);
    }

    public function postEnrolmentConfirm($request, $response, $args) {
        $this->logger->info("Klusbib POST '/enrolment_confirm' route");

        $access = Authorisation::checkEnrolmentAccess($this->token, "confirm");
        if ($access === AccessType::NO_ACCESS) {
            return $response->withStatus(403); // Unauthorized
        }

        // Get data
        $data = $request->getParsedBody();
        if (empty($data["paymentMode"]) || !isset($data["userId"])) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(400)// Bad request
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
            return $response->withStatus(400)
                ->withJson("No user found with id $userId");;
        }

        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger, $user);
        try {
            $enrolmentManager->confirmPayment($paymentMode,$user);
            $data = array();
            return $response->withStatus(200)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (EnrolmentException $e) {
            if ($e->getCode() == EnrolmentException::UNEXPECTED_PAYMENT_MODE) {
                $message = "Unsupported payment mode ($paymentMode)";
                $this->logger->warn("Invalid POST request on /enrolment/confirm received: $message");
                return $response->withStatus(400) // Bad request
                ->withJson($message);
            } else if ($e->getCode() == EnrolmentException::UNEXPECTED_CONFIRMATION) {
                $message = "Unexpected confirmation for payment mode ($paymentMode)";
                return $response->withStatus(400)// Bad request
                ->withJson($message);
            } else {
                return $response->withStatus(500)// Internal error
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
            return $response->withStatus(400) // Bad request
            ->withJson("Missing or empty orderId");
        }
        // Get data
        $data = $request->getParsedBody();
        $paymentId = $_POST["id"];
        $orderId = $args['orderId'];
        if (empty($paymentId)) {
            $this->logger->error("POST /enrolment/{orderId} failed due to missing id param (orderId=" . $orderId . "; parsed body=" . json_encode($data));
            return $response->withStatus(400) // Bad request
            ->withJson("Missing or empty paymentId");
        }
            // FIXME: remove $user from EnrolmentManager constructor
        $enrolmentManager = $this->enrolmentFactory->createEnrolmentManager($this->logger);
        try {
            $enrolmentManager->processMolliePayment($paymentId);
        } catch (EnrolmentException $e) {

            if ($e->getCode() == EnrolmentException::UNKNOWN_USER) {
                return $response->withStatus(400)
                    ->withJson($e->getMessage());;
            } elseif ($e->getCode() == EnrolmentException::MOLLIE_EXCEPTION) {
                return $response->withStatus(500)// Internal error
                ->withJson($e->getMessage());
            }
        }

        return $response->withStatus(200)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode([], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }
}