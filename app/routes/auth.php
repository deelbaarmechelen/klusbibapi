<?php
/** @var mixed $app */
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$container = $app->getContainer();

$mwParamToHeader = function (Request $request, RequestHandler $handler) {
    // Retrieve token from query string and add it as Authorization header
    parse_str($request->getUri()->getQuery(), $queryParams);
    $token = $queryParams['token'] ?? null;
    if ($token != null) {
        $header = $request->getHeaderLine("Authorization");
        if ($header === "") {
            $request = $request->withAddedHeader("Authorization", "Bearer " . $token);
            $header = $request->getHeaderLine("Authorization");
        }
    }
    return $handler->handle($request);
};

$mw401To200 = function (Request $request, RequestHandler $handler) use ($container) {
    // Convert Authentication error into an OK with error message
    $response = $handler->handle($request);
    if ($response->getStatusCode() == \Api\Util\HttpResponseCode::UNAUTHORIZED) {
        $data = json_decode((string) $response->getBody());
        $view = $container->get('view');
        $result = "INVALID_TOKEN";
        if (strcasecmp($data->message, "Expired token") == 0) {
            $result = "INVALID_TOKEN";
        } else if (strncasecmp($data->message, "Token not found", 15) == 0) {
            $result = "MISSING_TOKEN_OR_EMAIL";
        }
        return $view->render($response->withStatus(\Api\Util\HttpResponseCode::OK),
            'confirm_email.twig', [
            'result' => $result
        ]);
    }

    return $response;
};

$app->post("/auth/reset", \Api\Authentication\PasswordResetController::class . ":postResetPassword");
$app->get("/auth/reset/{userId}", \Api\Authentication\PasswordResetController::class . ":getResetPassword");

$app->post('/auth/verifyemail', \Api\Authentication\VerifyEmailController::class . ":verifyEmail");
$app->get('/auth/confirm/{userId}', \Api\Authentication\VerifyEmailController::class . ":confirmEmail")
    ->add($container->get('JwtAuthentication'))
    ->add($mwParamToHeader)
    ->add($mw401To200);

// $app->get("/auth/password/reset", "PasswordResetController:getResetPassword")->setName("auth.password.reset");
// $app->post("/auth/password/reset", "PasswordResetController:postResetPassword");

