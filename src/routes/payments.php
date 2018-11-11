<?php
use Illuminate\Database\Capsule\Manager as Capsule;

$app->post('/payments', function ($request, $response, $args) {
    $this->logger->info("Klusbib POST '/payments' route");

    /*
     * Determine the url parts to these example files.
     */
    $protocol = isset($_SERVER['HTTPS']) && strcasecmp('off', $_SERVER['HTTPS']) !== 0 ? "https" : "http";
    $hostname = $_SERVER['HTTP_HOST'];
    $data = $request->getParsedBody();
    $orderId = "myorder";
    if (isset($data["redirect_url"])) {
        $redirectUrl = $data["redirect_url"];
    } else {
        $redirectUrl = "{$protocol}://{$hostname}/payments/$orderId";
    }
    try {

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey(MOLLIE_API_KEY);
        $payment = $mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => "20.00"
            ],
            "description" => "Klusbib order #{$orderId}",
            "redirectUrl" => $redirectUrl,
            "webhookUrl" => "{$protocol}://{$hostname}/payments/{$orderId}",
            "metadata" => [
                "order_id" => $orderId,
            ],
        ]);

        $this->logger->info("Payment created with order id {$orderId}");
        // store payment id

        header("Location: " . $payment->getCheckoutUrl(), true, 303);

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        echo "API call failed: " . htmlspecialchars($e->getMessage());
        $this->logger->error("API call failed: " . htmlspecialchars($e->getMessage()));
        return $response->withStatus(500) // Internal error
        ->withJson("API call failed: " . htmlspecialchars($e->getMessage()));
    }
});

$app->post('/payments/{orderId}', function ($request, $response, $args) {
    $this->logger->info("Klusbib POST '/payments/{orderId}' route (" + $args['orderId'] + ")");
    $paymentId = $_POST["id"];
    if (empty($paymentId)) {
        return $response->withStatus(400) // Bad request
        ->withJson("Missing or empty payment_id");
    }
    try {
        $payment = new \Api\Model\Payment();

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey(MOLLIE_API_KEY);

        /*
         * Retrieve the payment's current state.
         * See also https://docs.mollie.com/payments/status-changes
         */
        $paymentMollie = $mollie->payments->get($_POST["id"]);
        $orderId = $paymentMollie->metadata->order_id;
        $payment->order_id = $orderId;

        if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
            /*
             * The payment is paid and isn't refunded or charged back.
             * At this point you'd probably want to start the process of delivering the product to the customer.
             */
            $payment->state = "SUCCESS";
        } elseif ($payment->isOpen()) {
            /*
             * The payment is open.
             */
            $payment->state = "OPEN";
        } elseif ($payment->isPending()) {
            /*
             * The payment is pending.
             */
            $payment->state = "PENDING";
        } elseif ($payment->isFailed()) {
            /*
             * The payment has failed.
             */
            $payment->state = "FAILED";
        } elseif ($payment->isExpired()) {
            /*
             * The payment is expired.
             */
            $payment->state = "EXPIRED";
        } elseif ($payment->isCanceled()) {
            /*
             * The payment has been canceled.
             */
            $payment->state = "CANCELED";
        } elseif ($payment->hasRefunds()) {
            /*
             * The payment has been (partially) refunded.
             * The status of the payment is still "paid"
             */
            $payment->state = "REFUND";
        } elseif ($payment->hasChargebacks()) {
            /*
             * The payment has been (partially) charged back.
             * The status of the payment is still "paid"
             */
            $payment->state = "CHARGEBACK";
        }

//        ConsumerMapper::mapArrayToPayment($data, $payment);
        $payment->save();

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        echo "Webhook call failed: " . htmlspecialchars($e->getMessage());
        return $response->withStatus(500);
    }
    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode([], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->get('/payments/{paymentId}', function ($request, $response, $args) {
    $this->logger->info("Klusbib GET '/payments/{paymentId}' route (" + $args['paymentId'] + ")");
    $data = array();
    $data["message"] = "success|failed";
    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

});