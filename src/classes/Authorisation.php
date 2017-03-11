<?php
namespace Api;

use Api\Exception\ForbiddenException;

class Authorisation {
	
	static function checkAccessByToken($token, $allowedScopes) {
		if (false === $token->hasScope($allowedScopes)) {
			throw new ForbiddenException("Missing authorisation for scopes " . json_encode($allowedScopes), 403);
		}
	}
	
}