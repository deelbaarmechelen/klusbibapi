<?php

namespace Api\Authentication;
use Api\Token\Token;
use Api\Mail\MailManager;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;


class PasswordResetController
{
    protected $logger;
    protected $renderer;

    public function __construct($logger, $renderer) {
        $this->logger = $logger;
        $this->renderer = $renderer;
    }

    /**
     * Checks if the received email matches an existing user
     * Triggers an email to that user to allow password change
     * @param $request body contains JSON message with email
     * @param $response
     * @param $arguments
     * @return mixed JSON msg with status, message and token. 404 if email doesn't match an existing user
     */
    function postResetPassword($request, $response, $arguments) {
        $this->logger->info("Klusbib POST '/auth/reset' route");
        // lookup email in users table
        $body = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($body));
        $email = $body["email"];
        $this->logger->debug("email=" . $email);
        $user = Capsule::table('contact')->where('email', $email)->first();
        if (null == $user) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        if (isset($body["redirect_url"])) {
            $redirectUrl = $body["redirect_url"];
        } else {
            $redirectUrl = null;
        }

        // generate temporary token allowing password change
        $sub = $user->id;
        $requested_scopes = Token::allowedScopes($user->role);
        $scopes = array_filter($requested_scopes, function ($needle) {
            return in_array($needle, Token::resetPwdScopes());
        });
        $this->logger->info("Generating token with scopes " . json_encode($scopes) . " and sub " .  json_encode($sub));
        $token = Token::generateToken($scopes, $sub);

        // generate email
        $mailMgr = new MailManager();
        $result = $mailMgr->sendPwdRecoveryMail($user->id, $user->first_name, $email, $token, $redirectUrl);

        if (!$result) { // error in mail send
            $error["message"] = $mailMgr->getLastMessage();
            return $response->withStatus(HttpResponseCode::INTERNAL_ERROR)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($error, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        $data["status"] = "ok";
        $data["message"] = $mailMgr->getLastMessage();
        $data["token"] = $token;

        return $response->withStatus(HttpResponseCode::CREATED)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }

    /**
     * Returns a basic interface to change user password
     * Typically triggered from a link in password reset email (see postResetPassword)
     *
     * Requires a valid token
     * @param $request expected to contain a token and name parameter
     * @param $response
     * @param $args
     * @return $mixed basic HTML page to reset password
     */
    public function getResetPassword($request, $response, $args) {
        $this->logger->info("Klusbibapi GET '/auth/reset/{userId}' route");

        // Render index view
        return $this->renderer->render($response, 'reset_pwd.phtml',  [
            'userId' => $args['userId']
        ]);

    }
}