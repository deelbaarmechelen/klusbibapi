<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Api\Model\PaymentMode;

$container = $app->getContainer();

/*
 * Add Authentication middleware
 */
$mw = function (Request $request, RequestHandler $handler) use ($container) {
    $data = $request->getParsedBody();
    $paymentMode = $data["paymentMode"];
    if (empty($data["startMembershipDate"])) {
        $startMembershipDate = null;
    } else {
        $startMembershipDate = \Carbon\Carbon::createFromFormat('Y-m-d', $data["startMembershipDate"]);
    }

    $paymentCompleted = false;
    if ($paymentMode == \Api\Model\PaymentMode::CASH
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
        && ($data["paymentCompleted"] === true || strcasecmp ($data["paymentCompleted"], 'true') == 0 )) {
        // boolean true or string value "true"
        $paymentCompleted = true;
    }

    // registering a completed payment requires admin rights
    // Only admin can specify explicit membership start date
    // we still need to do authentication, as this is skipped for /enrolment route
    if ($paymentCompleted || !empty($startMembershipDate)) {
        $jwtAuth = new \Tuupola\Middleware\JwtAuthentication([
            "path" => "/",
            "ignore" => ["/token", "/welcome", "/upload", "/payments", "/stats",
                "/auth/reset", "/auth/verifyemail"],
            "secret" => getenv("JWT_SECRET"),
            "logger" => $container->get("logger"),
//			"secure" => (APP_ENV == "development" ? false : true), // force HTTPS for production
            "secure" => false, // disable -> scheme not always correctly set on request!
            "relaxed" => ["admin"], // list hosts allowed without HTTPS for DEV
            "error" => function (\Psr\Http\Message\ResponseInterface $response, $arguments) {
                $data = array("error" => array( "status" => 401, "message" => $arguments["message"]));
                return $response
                    ->withHeader("Content-Type", "application/json")
                    ->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            },
            "rules" => [
                new \Api\Middleware\Jwt\JwtCustomRule([
                    "getignore" => ["/tools", "/consumers", "/auth/confirm"]
                ]),
                new \Tuupola\Middleware\JwtAuthentication\RequestMethodRule([
                    "ignore" => ["OPTIONS"]
                ])
            ],
            "before" => function (\Psr\Http\Message\ServerRequestInterface $request, $arguments) use ($container) {
                $container->get('logger')->debug("Authentication ok for token: " . json_encode($arguments["decoded"]));
                $container->get("token")->hydrate($arguments["decoded"]);
            }
        ]);
        $response = $jwtAuth->process($request, $handler);
    } else {
        $response = $handler->handle($request);
    }

    return $response;
};
/**
 * Launches the enrolment operation
 * The user needs to be created prior to this operation
 * This operation is normally terminated by a POST to /enrolment_confirm
 *
 * For online payment with MOLLIE an extra notification is received to process the enrolment
 * as POST to /enrolment/{orderId}
 */
$app->post('/enrolment', \Api\Enrolment\EnrolmentController::class . ':postEnrolment')
    ->add($mw);

/**
 * Manual confirmation for enrolments by TRANSFER or STROOM
 */
$app->post('/enrolment_confirm', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentConfirm');

/**
 * Manual decline for invalid enrolments by STROOM
 */
$app->post('/enrolment_decline', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentDecline');

/**
 * Confirmation from payment processor (Mollie) on enrolment order
 */
$app->post('/enrolment/{orderId}', \Api\Enrolment\EnrolmentController::class . ':postEnrolmentOrder');
