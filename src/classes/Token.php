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
	
	static public function validScopes () {
		$valid_scopes = [
				"tools.create",
				"tools.read",
				"tools.update",
				"tools.delete",
				"tools.list",
				"tools.all",
				"reservations.create",
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
				"users.create",
				"users.read",
				"users.update",
				"users.update.owner",
				"users.delete",
				"users.list",
				"users.all"
		];
		return $valid_scopes;
	}
	static public function allowedScopes($role) {
		if ($role == 'admin') {
			return [
					"tools.all",
					"reservations.all",
					"consumers.all",
					"users.all"
			];
		}
		if ($role = 'member') {
			return [
					"tools.read",
					"tools.list",
					"reservations.create",
					"reservations.read",
					"reservations.update.owner",
					"reservations.delete.owner",
					"reservations.list",
					"consumers.read",
					"consumers.list",
					"users.read",
					"users.update.owner",
					"users.list",
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
		];
	}
}