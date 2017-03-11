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
	
	static public function generateToken($scopes, $sub) {
		$now = new \DateTime();
		$future = new \DateTime("now +2 hours");
		
		$jti = Base62::encode(random_bytes(16));
		
		$payload = [
				"iat" => $now->getTimeStamp(), 		// issued at
				"exp" => $future->getTimeStamp(),	// expiration
				"jti" => $jti,						// JWT ID
				"sub" => $sub,
				"scope" => $scopes
		];
		
		$secret = getenv("JWT_SECRET");
		return JWT::encode($payload, $secret, "HS256");
	}
}