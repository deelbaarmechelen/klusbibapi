<?php

namespace Api\Token;

use Api\Model\Contact;
use Api\Model\UserRole;
use Api\Model\UserState;
use Api\Settings;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Container\ContainerInterface;

class TokenController implements TokenControllerInterface
{
    protected $logger;
    protected $token;
    protected $container;

    public function __construct($logger, $token, ContainerInterface $container) {
        $this->logger = $logger;
        $this->token = $token;
        $this->container = $container;
    }
    public function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/token' route (for user " . $this->container->get("user") . ")");
        return $this->createToken($response, $this->container->get("user"));
    }
    public function createForGuest($request, $response, $args) {
        $this->logger->info("Klusbib POST '/token/guest' route");
        return $this->createToken($response, null, true);
    }

    /**
     * @param $response
     * @param $data
     * @return mixed
     */
    private function createToken($response, $userEmail, $forGuest = false)
    {
        $valid_scopes = Token::validScopes();

        if ($forGuest) {
            $sub = -1; // guest uses sub -1
            $requested_scopes = Token::allowedScopes('guest');
        } else {
            // lookup user
            $contact = Capsule::table('contact')->where('email', $userEmail)->first();
            if (null == $contact) {
                $this->logger->info("User with email $userEmail could not be found");
                return $response->withStatus(HttpResponseCode::NOT_FOUND);
            }
            if (UserState::ACTIVE != $contact->state && UserState::EXPIRED != $contact->state) {
                $this->logger->info("Token creation denied for user with state " . $contact->state);
                return $response->withStatus(HttpResponseCode::FORBIDDEN);
            }
            // check terms have been accepted
            if ($contact->role == UserRole::MEMBER &&
                $contact->accept_terms_date < Settings::LAST_TERMS_DATE_UPDATE) {
                $this->logger->info("Token creation denied for user with id " . $contact->id
                    . ", Terms need to be approved!");
                $sub = $contact->id;
                $requested_scopes = Token::allowedScopes($contact->role);
                $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
                    return in_array($needle, $valid_scopes);
                });
                $this->logger->info("Generating token with scopes " . json_encode($scopes) . " and sub " . json_encode($sub));
                $token = Token::generateToken($scopes, $sub); // Token authorizing the update of terms
                $responseData = array("reason" => "Terms need to be approved", "code" => "ERR_TERMS_NOT_ACCEPTED",
                    "token" => $token);
                return $response->withStatus(HttpResponseCode::FORBIDDEN)
                    ->withJson($responseData);
            }

            $sub = $contact->id;
            $requested_scopes = Token::allowedScopes($contact->role);
        }
        $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
            return in_array($needle, $valid_scopes);
        });
        $this->logger->info("Generating token with scopes " . json_encode($scopes) . " and sub " . json_encode($sub));
        $future = new \DateTime("now +24 hours");
        $token = Token::generateToken($scopes, $sub, $future);

        // update last_login timestamp
        if ($sub >= 0) { // not guest login
            $contact = Contact::find($sub);
            $contact->last_login = new \DateTime('now');
            $contact->save();
        }

        $data = array();
        $data["status"] = "ok";
        $data["token"] = $token;

        return $response->withStatus(HttpResponseCode::CREATED)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}