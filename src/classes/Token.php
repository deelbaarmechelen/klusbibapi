<?php
namespace Api;

use Firebase\JWT\JWT;
use Tuupola\Base62;

class Token
{
	public $decoded;
	public function hydrate($decoded)
	{
		$this->decoded = $decoded;
	}
	public function hasScope(array $scope)
	{
		if (empty($this->decoded)) {
			return false;
		}
		return !!count(array_intersect($scope, $this->decoded->scope));
	}
	
	public function getScopes() {
		return $this->decoded->scope;
	}
	public function getSub() {
		return $this->decoded->sub;
	}
	
	static private function generatePayload($scopes, $sub) {
		$now = new \DateTime();
		$future = new \DateTime("now +2 hours");
		
		$jti = Base62::encode(random_bytes(16));
		
		$payload = [
				"iat" => $now->getTimeStamp(), 		// issued at
				"exp" => $future->getTimeStamp(),	// expiration
				"jti" => $jti,						// JWT ID
				"sub" => $sub,
				"scope" => array_values($scopes)    // drop keys of scopes array
		];
		return $payload;
	}
	
	static public function createToken($scopes, $sub) {
		$token = new Token();
		$payload = Token::generatePayload($scopes, $sub);
		// convert $payload from array to object and add to token object
		$token->hydrate(json_decode(json_encode($payload), false, 512, JSON_BIGINT_AS_STRING));
		return $token;
	}
	
	static public function generateToken($scopes, $sub) {
		$payload = Token::generatePayload($scopes, $sub);
		
		$secret = getenv("JWT_SECRET");
		return JWT::encode($payload, $secret, "HS256");
	}
	
	static public function validScopes () {
		$valid_scopes = [
				"tools.create",
				"tools.read",
				"tools.update",
				"tools.delete",
				"tools.list",
				"tools.all",
				"reservations.create",
				"reservations.create.owner",
				"reservations.create.owner.donation_only",
				"reservations.read",
				"reservations.update",
				"reservations.update.owner",
				"reservations.delete",
				"reservations.delete.owner",
				"reservations.list",
				"reservations.all",
				"consumers.create",
				"consumers.read",
				"consumers.update",
				"consumers.delete",
				"consumers.list",
				"consumers.all",
                "events.create",
                "events.read",
                "events.update",
                "events.delete",
                "events.list",
                "events.all",
				"users.create",
				"users.read",
				"users.read.owner",
				"users.read.state",
				"users.update",
				"users.update.owner",
				"users.update.password",
				"users.delete",
				"users.list",
				"users.all",
                "payments.all",
                "payments.list",
				"auth.confirm"
		];
		return $valid_scopes;
	}
	static public function resetPwdScopes () {
		$reset_pwd_scopes = [
				"users.update.password"
		];
		return $reset_pwd_scopes;
	}
	
	static public function allowedScopes($role) {
		if ($role == 'admin') {
			return [
				"tools.all",
				"reservations.all",
				"consumers.all",
				"users.all",
                "events.all",
                "payments.all",
				"users.update.password",
			];
		}
		if ($role == 'member') {
			return [
					"tools.read",
					"tools.list",
					"reservations.create.owner",
					"reservations.read",
					"reservations.update.owner",
					"reservations.delete.owner",
					"reservations.list",
					"consumers.read",
					"consumers.list",
					"users.read.owner", // not allowed to consult other users info
					"users.update.password",
					"users.update.owner", // not allowed to update other users info
			];
		}
		if ($role == 'supporter') {
			return [
					"tools.read",
					"tools.list",
					"reservations.create.owner.donation_only", // allow reservations on donated tools only
					"reservations.read",
					"reservations.update.owner",
					"reservations.delete.owner",
					"reservations.list",
					"consumers.read",
					"consumers.list",
					"users.read.owner", // not allowed to consult other users info
					"users.update.password",
					"users.update.owner", // not allowed to update other users info
			];
		}
		// unknown role / guest
		return [
				"tools.read",
				"tools.list",
				"reservations.read",
				"reservations.list",
				"consumers.read",
				"consumers.list",
				"auth.confirm",
                "users.read.state"
		];
	}
}