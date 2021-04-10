<?php

namespace Api\Token;

use Api\Model\User;
use Api\Model\UserState;
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
        $this->logger->info("Klusbib POST '/token' route (for user " . $this->container["user"] . ")");
        return $this->createToken($response, $this->container["user"]);
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
            $user = Capsule::table('users')->where('email', $userEmail)->first();
            if (null == $user) {
                $this->logger->info("User with email $userEmail could not be found");
                return $response->withStatus(HttpResponseCode::NOT_FOUND);
            }
            if (UserState::ACTIVE != $user->state && UserState::EXPIRED != $user->state) {
                $this->logger->info("Token creation denied for user with state " . $user->state);
                return $response->withStatus(HttpResponseCode::FORBIDDEN);
            }
            $sub = $user->user_id;
            $requested_scopes = Token::allowedScopes($user->role);
        }
        $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
            return in_array($needle, $valid_scopes);
        });
        $this->logger->info("Generating token with scopes " . json_encode($scopes) . " and sub " . json_encode($sub));
        $token = Token::generateToken($scopes, $sub);

        // update last_login timestamp
        if ($sub >= 0) { // not guest login
            $user = User::find($sub);
            $user->last_login = new \DateTime('now');
            $user->save();
        }

        $data = array();
        $data["status"] = "ok";
        $data["token"] = $token;

        return $response->withStatus(HttpResponseCode::CREATED)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}