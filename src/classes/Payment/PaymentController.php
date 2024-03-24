<?php

namespace Api\Payment;

use Api\Model\PaymentState;
use Api\Token\Token;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Model\PaymentMode;
use Api\Model\Payment;
use Api\Model\UserState;
use Api\Mail\MailManager;
use Api\Authorisation;
use Api\ModelMapper\PaymentMapper;

class PaymentController implements PaymentControllerInterface
{
    protected $logger;
    protected $token;
    protected $mailManager;
    protected $mollieClient;

    public function __construct($logger, Token $token, MailManager $mailManager, $mollieClient)
    {
        $this->logger = $logger;
        $this->token = $token;
        $this->mailManager = $mailManager;
        $this->mollieClient = $mollieClient;
    }

    public function getAll($request, $response, $args)
    {
        $this->logger->info("Klusbib GET '/payments' route");

        // FIXME: no token scopes due to passthrough settings?
//    $authorised = Authorisation::checkPaymentAccess($this->token, "list", null);
//    if (!$authorised) {
//        $this->logger->warn("Access denied (available scopes: " . json_encode($this->token->getScopes()) );
//        return $response->withStatus(403);
//    }

        parse_str($request->getUri()->getQuery(), $queryParams);
        $sortdir = $queryParams['_sortDir'] ?? null;
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $queryParams['_sortField'] ?? null;
        if (!Payment::canBeSortedOn($sortfield)) {
            $sortfield = 'id';
        }
        $page = $queryParams['_page'] ?? null;
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $queryParams['_perPage'] ?? null;
        if (!isset($perPage)) {
            $perPage = '100';
        }
        $orderId = $queryParams['orderId'] ?? null;
        if (!isset($orderId)) {
            $builder = Payment::any();
        } else {
            $builder = Payment::forOrder($orderId);
        }
        $payments = $builder->orderBy($sortfield, $sortdir)->get();
        $payments_page = array_slice($payments->all(), ($page - 1) * $perPage, $perPage);
        $data = [];
        foreach ($payments_page as $payment) {
            array_push($data, PaymentMapper::mapPaymentToArray($payment));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($payments));
    }

    public function getByID($request, $response, $args) {
        $this->logger->info("Klusbib GET '/payments/:paymentId' route (" . $args['paymentId'] . ")");
        $payment = \Api\Model\Payment::find($args['paymentId']);
        if (empty($payment)) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND) // Not found
            ->withJson(["message" => "No payment found for provided paymentId"]);
        }
//    $data = array();
//    if ($payment->state == 'SUCCESS') {
//        $data["message"] = "Betaling geslaagd!";
//    } elseif ($payment->state == 'FAILED' || $payment->state == 'CANCELED' || $payment->state == 'EXPIRED') {
//        $data["message"] = "Betaling mislukt";
//    } else {
//        $data["message"] = "Betaling nog niet afgerond";
//    }
//
//    $data["paymentStatus"] = $payment->state; // NEW, SUCCESS, FAILED, OPEN
//    $data["paymentMode"] = $payment->mode;
        return $response->withStatus(HttpResponseCode::OK)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode(PaymentMapper::mapPaymentToArray($payment), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public function create($request, $response, $args)
    {
        $this->logger->info("Klusbib POST '/payments' route");
        $this->mailManager->sendErrorNotif("Unexpected call to PaymentController.create, method considered as not yet operational / deprecated (hardcoded enrolment amount)");

        // Get data
        $data = $request->getParsedBody();
        if (empty($data["paymentMode"]) || empty($data["userId"]) ) {
            $message = "no paymentMode and/or userId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson("Missing or invalid data: $message");
        }
        if (empty($data["orderId"])  ) {
            $message = "no orderId provided";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson("Missing or invalid data: $message");
        }

        $paymentMode = $data["paymentMode"];
        if ($paymentMode == PaymentMode::MOLLIE && empty($data["redirectUrl"])  ) {
            $message = "no redirectUrl provided (required for online payment)";
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson("Missing or invalid data: $message");
        }

        $userId = $data["userId"];
        $user = \Api\Model\Contact::find($userId);
        $orderId = $data["orderId"];
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                ->withJson("No user found with id $userId");;
        }
        // Check payment mode
        if ($paymentMode == PaymentMode::TRANSFER) {
            $user->payment_mode = PaymentMode::TRANSFER;
            $user->save();

            // use first() rather than get()
            // there should be only 1 result, but first returns a Model
            $payment = \Api\Model\Payment::where([
                ['kb_order_id', '=', $orderId],
                ['contact_id', '=', $userId],
                ['kb_mode', '=', PaymentMode::TRANSFER],
            ])->first();
            if ($payment == null) {
                // Create new payment
                $payment = new \Api\Model\Payment();
                $payment->kb_mode = PaymentMode::TRANSFER;
                $payment->kb_order_id = $orderId;
                $payment->contact_id = $userId;
                $payment->kb_payment_timestamp = new \DateTime();
                $payment->amount = \Api\Settings::ENROLMENT_AMOUNT;
                $payment->kb_state = PaymentState::OPEN;
                $payment->save();
            };
            $this->mailManager->sendEnrolmentConfirmation($user, $paymentMode);

            $data = [];
            $data["orderId"] = $orderId;
            $data["paymentMode"] = $payment->kb_mode;
            $data["paymentState"] = $payment->kb_state;
            $data["mode"] = $payment->kb_mode;
            $data["state"] = $payment->kb_state;
            return $response->withStatus(HttpResponseCode::OK)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        if ($paymentMode == PaymentMode::MOLLIE) {
            $user->payment_mode = PaymentMode::MOLLIE;
            $user->save();
            $orderId = $data["orderId"];
//        $orderId = $userId . "-" . date('YmdHis'); //YYYYMMDDhhmmss
            $redirectUrl = $data["redirectUrl"];
            $requestedPaymentMean = $data["paymentMean"];

            // Determine the url parts
            $protocol = isset($_SERVER['HTTPS']) && strcasecmp('off', $_SERVER['HTTPS']) !== 0 ? "https" : "http";
            if ($protocol == "http") {
                $this->logger->warn("payment triggered on unsecure connection (SERVER var HTTPS=" .
                    ($_SERVER['HTTPS'] ?? "not set") . ")");
                $this->logger->warn("request uri scheme=". $request->getUri()->getScheme()
                    . ";host=" . $request->getUri()->getHost() . ";port=" . $request->getUri()->getPort());
            }
            $hostname = $_SERVER['HTTP_HOST'];

            try {

                $paymentData = [
                    "amount" => [
                        "currency" => "EUR",
                        "value" => number_format(\Api\Settings::ENROLMENT_AMOUNT , 2,"." )
                    ],
                    "description" => "Klusbib inschrijving {$user->first_name} {$user->last_name}",
                    "redirectUrl" => "{$redirectUrl}?orderId={$orderId}",
//                "webhookUrl" => "{$protocol}://{$hostname}/payments/{$orderId}",
                    "webhookUrl" => "https://{$hostname}/payments/{$orderId}",
                    "metadata" => [
                        "order_id" => $orderId,
                        "user_id" => $userId,
                    ],
                ];

                if (isset($requestedPaymentMean) && !empty($requestedPaymentMean)) {
                    $paymentData["method"] = $requestedPaymentMean;
                }
//            $this->logger->info("payment data = " . print_r($paymentData, TRUE));
                $payment = $this->mollieClient->payments->create($paymentData);
                $this->logger->info("Payment created with order id {$orderId} webhook {$protocol}://{$hostname}/payments/{$orderId} and redirectUrl {$redirectUrl}");
                // store payment id -> needed?

                $data = [];
                $data["checkoutUrl"] = $payment->getCheckoutUrl();
                $data["orderId"] = $orderId;
                // FIXME: can we change this to 201? Impact on Mollie?
                return $response->withStatus(HttpResponseCode::OK)
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } catch (\Mollie\Api\Exceptions\ApiException $e) {
                echo "API call failed: " . htmlspecialchars($e->getMessage());
                $this->logger->error("API call failed: " . htmlspecialchars($e->getMessage()));
                return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)
                ->withJson("API call failed: " . htmlspecialchars($e->getMessage()));
            }
        }
        $message = "Unsupported payment mode ($paymentMode)";
        $this->logger->warn("Invalid POST request on /payments received: $message");
        return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                        ->withJson($message);
    }

    public function createWithOrderId($request, $response, $args){
        $this->logger->info("Klusbib POST '/payments/{$args['orderId']}' route");
        $this->mailManager->sendErrorNotif("Unexpected call to PaymentController.createWithOriderId, method considered as not yet operational / deprecated (hardcoded enrolment amount)");
        if (empty($args['orderId'])) {
            $this->logger->error("POST /payments/{orderId} failed due to missing orderId param");
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson("Missing or empty orderId");
        }
        // Get data
        $data = $request->getParsedBody();
        $paymentId = $_POST["id"];
        if (empty($paymentId)) {
            $this->logger->error("POST /payments/{orderId} failed due to missing id param (orderId=" . $args['orderId'] . "; parsed body=" . json_encode($data));
            return $response->withStatus(HttpResponseCode::BAD_REQUEST)
            ->withJson("Missing or empty paymentId");
        }
        try {
            /*
             * Retrieve the payment's current state.
             * See also https://docs.mollie.com/payments/status-changes
             */
            $paymentMollie = $this->mollieClient->payments->get($paymentId);
            $this->logger->info('Mollie payment:' . json_encode($paymentMollie));
            $orderId = $paymentMollie->metadata->order_id;
            $userId = $paymentMollie->metadata->user_id;

            // use first() rather than get()
            // there should be only 1 result, but first returns a Model
            $payment = \Api\Model\Payment::where([
                ['kb_order_id', '=', $orderId],
                ['contact_id', '=', $userId],
                ['kb_mode', '=', 'MOLLIE'],
            ])->first();
            if ($payment == null) {
                // Create new payment
                $payment = new \Api\Model\Payment();
                $payment->kb_mode = 'MOLLIE';
                $payment->kb_order_id = $orderId;
                $payment->contact_id = $userId;
                $payment->kb_payment_timestamp = new \DateTime();
                $payment->amount = $paymentMollie->amount->value;
            };

            if ($paymentMollie->isPaid() && !$paymentMollie->hasRefunds() && !$paymentMollie->hasChargebacks()) {
                /*
                 * The payment is paid and isn't refunded or charged back.
                 * At this point you'd probably want to start the process of delivering the product to the customer.
                 */
                $payment->kb_state = "SUCCESS";
            } elseif ($paymentMollie->isOpen()) {
                /*
                 * The payment is open.
                 */
                $payment->kb_state = "OPEN";
            } elseif ($paymentMollie->isPending()) {
                /*
                 * The payment is pending.
                 */
                $payment->kb_state = "PENDING";
            } elseif ($paymentMollie->isFailed()) {
                /*
                 * The payment has failed.
                 */
                $payment->kb_state = "FAILED";
            } elseif ($paymentMollie->isExpired()) {
                /*
                 * The payment is expired.
                 */
                $payment->kb_state = "EXPIRED";
            } elseif ($paymentMollie->isCanceled()) {
                /*
                 * The payment has been canceled.
                 */
                $payment->kb_state = "CANCELED";
            } elseif ($paymentMollie->hasRefunds()) {
                /*
                 * The payment has been (partially) refunded.
                 * The status of the payment is still "paid"
                 */
                $payment->kb_state = "REFUND";
            } elseif ($paymentMollie->hasChargebacks()) {
                /*
                 * The payment has been (partially) charged back.
                 * The status of the payment is still "paid"
                 */
                $payment->kb_state = "CHARGEBACK";
            }
            $this->logger->info("Saving payment for orderId $orderId with state $payment->state (Mollie payment id=$paymentId / Internal payment id = $payment->payment_id)");
            $payment->save();

            // Lookup user and update state
            $user = \Api\Model\Contact::find($userId);
            if (null == $user) {
                $this->logger->error("POST /payments/$orderId failed: user $userId is not found");
                return $response->withStatus(HttpResponseCode::BAD_REQUEST)
                    ->withJson("No user found with id $userId");;
            }
            if ($payment->kb_state == "SUCCESS") {
                if ($user->state == UserState::CHECK_PAYMENT) {
                    $user->state = UserState::ACTIVE;
                    $user->save();
                    // send confirmation to new member
                    $this->mailManager->sendEnrolmentConfirmation($user, PaymentMode::MOLLIE);
                    // send notification to Klusbib team
                    $this->mailManager->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL,$user);
                }
            } else if ($payment->kb_state == "FAILED"
                || $payment->kb_state == "EXPIRED"
                || $payment->kb_state == "CANCELED"
                || $payment->kb_state == "REFUND"
                || $payment->kb_state == "CHARGEBACK") {
                // Permanent failure, or special case -> send notification for manual follow up
                $this->mailManager->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL,$user, $payment, "payment failed");
            }

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            echo "Webhook call failed: " . htmlspecialchars($e->getMessage());
            return $response->withStatus(HttpResponseCode::INTERNAL_ERROR);
        }
        return $response->withStatus(HttpResponseCode::OK)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode([], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/payments' route (" . $args['paymentId'] . ")");
        // FIXME: no token scopes due to passthrough settings?
        $this->logger->info("token: " . \json_encode($this->token));
//            $authorised = Authorisation::checkPaymentAccess($this->token, "delete", $args['paymentId']);
//            if (!$authorised) {
//                $this->logger->warn("Access denied on payment delete with id " . $args['paymentId']);
//                return $response->withStatus(403);
//            }
        $payment = \Api\Model\Payment::find($args['paymentId']);
        if (empty($payment)) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND)
            ->withJson(["message" => "No payment found for provided paymentId"]);
        }
        $payment->delete();
        return $response->withStatus(HttpResponseCode::OK);

    }
}