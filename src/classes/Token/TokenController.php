<?php

namespace Api\Token;

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
        $this->logger->info("Klusbib POST '/token' route");
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
                return $response->withStatus(404);
            }
            if ('ACTIVE' != $user->state) {
                return $response->withStatus(403);
            }
            $sub = $user->user_id;
            $requested_scopes = Token::allowedScopes($user->role);
        }
        $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
            return in_array($needle, $valid_scopes);
        });
        $token = Token::generateToken($scopes, $sub);
        $this->logger->info("Token generated with scopes " . json_encode($scopes) . " and sub " . json_encode($sub));

        $data = array();
        $data["status"] = "ok";
        $data["token"] = $token;

        return $response->withStatus(201)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}