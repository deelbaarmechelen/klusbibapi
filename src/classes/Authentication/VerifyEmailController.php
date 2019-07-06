<?php

namespace Api\Authentication;

use Api\Token;
use Api\Model\UserState;
use Api\Model\EmailState;
use Api\Mail\MailManager;
use Illuminate\Database\Capsule\Manager as Capsule;

class VerifyEmailController
{
    protected $logger;
    protected $toolManager;

    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * Triggers an email to verify email validity
     * The email contains a link back to the confirmEmail path with a validity of 2 weeks
     *
     * @param $request body contains JSON message with email to be verified
     * @param $response
     * @param $args
     * @return mixed JSON msg with status and message.
     *  404 if email doesn't match an existing user
     *  412 if email is already confirmed/verified
     */
    public function verifyEmail($request, $response, $args) {
        // TODO: check who is allowed to request email verification
        // lookup email in users table
        $body = $request->getParsedBody();
        $this->logger->info("parsedbody=" . json_encode($body));
        $email = $body["email"];
        $this->logger->debug("email=" . $email);
        $user = Capsule::table('users')->where('email', $email)->first();
        if (null == $user) {
            return $response->withStatus(404);
        }
        if ($user->email_state == EmailState::CONFIRMED) {
            $data["status"] = "error";
            $data["message"] = "No email confirmation required for user";
            return $response->withStatus(412)
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
        $mailmgr = new MailManager();
        $sub = $user->user_id;
        $scopes = ["auth.confirm"];
        $future = new \DateTime("now +2 weeks");
        $result = $mailmgr->sendEmailVerification($user->user_id, $user->firstname, $user->email,
            Token::generateToken($scopes, $sub, $future));
        $message = $mailmgr->getLastMessage();
        $this->logger->info('Sending email verification result: ' . $message);

        $data["status"] = "ok";
        $data["message"] = $message;

        return $response->withStatus(200)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    }

    /**
     * Set the user's email state to confirmed and returns a basic confirmation page
     *
     * @param $request is expected to contain token as query param
     * @param $response
     * @param $args is expected to contain userId as path argument
     * @return mixed basic HTML confirmation page
     *  400 if token or user id is missing
     *  401 if token is not valid
     *  403 if user is not authorised to verify this email
     *  404 if no user can be found for the given email
     */
    public function confirmEmail($request, $response, $args) {
        $token = $request->getQueryParam("token", $default = null);
        if (is_null($token)) {
            $this->logger->warn("Missing token or user id in email confirmation");
            return $response->withStatus(400);
        }

        // Check token
        $decoded = $this->JwtAuthentication->decodeToken($token);
        $this->logger->debug("decoded token=" . json_encode($decoded));
        if (false === $decoded) {
            return $response->withStatus(401);
        }
        $token = new Token();
        $token->hydrate($decoded);
        if ($args["userId"] != $token->getSub()) {
            // not allowed to verify address for another user
            return $response->withStatus(403);
        }
        try {
            $user = \Api\Model\User::findOrFail($token->getSub());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $modelNotFoundException) {
            return $response->withStatus(404)
                ->write('User with id ' . $token->getSub() . ' could not be found. Make sure to register first or contact us in case of problems.');
        }

        // for backward compatibility (CONFIRM_EMAIL user state is deprecated)
        if ($user->state === "CONFIRM_EMAIL") {
            $user->state = UserState::CHECK_PAYMENT;
        }
        $user->email_state = EmailState::CONFIRMED;
        $user->save();

        // Render index view
        return $this->view->render($response, 'confirm_email.phtml',  [
            'userId' => $args['userId']
        ]);
    }
}