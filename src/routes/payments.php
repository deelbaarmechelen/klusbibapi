<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Model\PaymentMode;
use Api\Model\UserState;
use Api\Mail\MailManager;

$app->post('/payments', function ($request, $response, $args) {
    $this->logger->info("Klusbib POST '/payments' route");

    // Get data
    $data = $request->getParsedBody();
    if (empty($data["paymentMode"]) || empty($data["userId"]) ) {
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
    if (null == $user) {
        return $response->withStatus(400)
            ->withJson("No user found with id $userId");;
    }
    // Check payment mode
    if ($paymentMode == PaymentMode::TRANSFER) {
        $user->payment_mode = PaymentMode::TRANSFER;
        $user->save();

        // use first() rather than get()
        // there should be only 1 result, but first returns a Model
        $payment = \Api\Model\Payment::where([
            ['order_id', '=', $orderId],
            ['user_id', '=', $userId],
            ['mode', '=', PaymentMode::TRANSFER],
        ])->first();
        if ($payment == null) {
            // Create new payment
            $payment = new \Api\Model\Payment();
            $payment->mode = PaymentMode::TRANSFER;
            $payment->order_id = $orderId;
            $payment->user_id = $userId;
            $payment->payment_date = new \DateTime();
            $payment->amount = \Api\Settings::ENROLMENT_AMOUNT;
            $payment->currency = "EUR";
            $payment->state = "OPEN";
            $payment->save();
        };
        $mailmgr = new MailManager();
        $mailmgr->sendEnrolmentConfirmation($user, $paymentMode);

        $data = array();
        $data["orderId"] = $orderId;
        $data["paymentMode"] = $payment->mode;
        $data["paymentState"] = $payment->state;
        return $response->withStatus(200)
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
                (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : "not set") . ")");
            $this->logger->warn("request uri scheme=". $request->getUri()->getScheme()
                . ";host=" . $request->getUri()->getHost() . ";port=" . $request->getUri()->getPort());
        }
        $hostname = $_SERVER['HTTP_HOST'];

        try {

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey(MOLLIE_API_KEY);
            $paymentData = [
                "amount" => [
                    "currency" => "EUR",
                    "value" => \Api\Settings::ENROLMENT_AMOUNT_STRING
                ],
                "description" => "Klusbib inschrijving {$user->firstname} {$user->lastname}",
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
            $payment = $mollie->payments->create($paymentData);
            $this->logger->info("Payment created with order id {$orderId} webhook {$protocol}://{$hostname}/payments/{$orderId} and redirectUrl {$redirectUrl}");
            // store payment id -> needed?

            $data = array();
            $data["checkoutUrl"] = $payment->getCheckoutUrl();
            $data["orderId"] = $orderId;
            return $response->withStatus(200)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            echo "API call failed: " . htmlspecialchars($e->getMessage());
            $this->logger->error("API call failed: " . htmlspecialchars($e->getMessage()));
            return $response->withStatus(500) // Internal error
            ->withJson("API call failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    $message = "Unsupported payment mode ($paymentMode)";
    $this->logger->warn("Invalid POST request on /payments received: $message");
    return $response->withStatus(400) // Bad request
        ->withJson($message);

});

$app->post('/payments/{orderId}', function ($request, $response, $args) {
    $this->logger->info("Klusbib POST '/payments/{$args['orderId']}' route");
    if (empty($args['orderId'])) {
        $this->logger->error("POST /payments/{orderId} failed due to missing orderId param");
        return $response->withStatus(400) // Bad request
        ->withJson("Missing or empty orderId");
    }
    // Get data
    $data = $request->getParsedBody();
    $paymentId = $_POST["id"];
    if (empty($paymentId)) {
        $this->logger->error("POST /payments/{orderId} failed due to missing id param (orderId=" . $args['orderId'] . "; parsed body=" . json_encode($data));
        return $response->withStatus(400) // Bad request
        ->withJson("Missing or empty paymentId");
    }
    try {
        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey(MOLLIE_API_KEY);

        /*
         * Retrieve the payment's current state.
         * See also https://docs.mollie.com/payments/status-changes
         */
        $paymentMollie = $mollie->payments->get($paymentId);
        $this->logger->info('Mollie payment:' . json_encode($paymentMollie));
        $orderId = $paymentMollie->metadata->order_id;
        $userId = $paymentMollie->metadata->user_id;

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
        $payment->save();

        // Lookup user and update state
        $user = \Api\Model\User::find($userId);
        if (null == $user) {
            $this->logger->error("POST /payments/$orderId failed: user $userId is not found");
            return $response->withStatus(400)
                ->withJson("No user found with id $userId");;
        }
        $mailmgr = new MailManager();
        if ($payment->state == "SUCCESS") {
            if ($user->state == UserState::CHECK_PAYMENT) {
                $user->state = UserState::ACTIVE;
                $user->save();
                // send confirmation to new member
                $mailmgr->sendEnrolmentConfirmation($user, PaymentMode::MOLLIE);
                // send notification to Klusbib team
                $mailmgr->sendEnrolmentSuccessNotification( ENROLMENT_NOTIF_EMAIL,$user);
            }
        } else if ($payment->state == "FAILED"
                || $payment->state == "EXPIRED"
                || $payment->state == "CANCELED"
                || $payment->state == "REFUND"
                || $payment->state == "CHARGEBACK") {
            // Permanent failure, or special case -> send notification for manual follow up
            $mailmgr->sendEnrolmentFailedNotification( ENROLMENT_NOTIF_EMAIL,$user, $payment);
        }

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        echo "Webhook call failed: " . htmlspecialchars($e->getMessage());
        return $response->withStatus(500);
    }
    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode([], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->get('/payments/{orderId}', function ($request, $response, $args) {
    $this->logger->info("Klusbib GET '/payments/:orderId' route (" . $args['orderId'] . ")");
    $payment = \Api\Model\Payment::where([
        ['order_id', '=', $args['orderId']],
    ])->first();
    if (empty($payment)) {
        return $response->withStatus(404) // Not found
        ->withJson(array(message => "No payment found for provided orderId"));
    }
    $data = array();
    if ($payment->state == 'SUCCESS') {
        $data["message"] = "Betaling geslaagd!";
    } elseif ($payment->state == 'FAILED' || $payment->state == 'CANCELED' || $payment->state == 'EXPIRED') {
        $data["message"] = "Betaling mislukt";
    } else {
        $data["message"] = "Betaling nog niet afgerond";
    }

    $data["paymentStatus"] = $payment->state; // NEW, SUCCESS, FAILED, OPEN
    $data["paymentMode"] = $payment->mode;
    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

});