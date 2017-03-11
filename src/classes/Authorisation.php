<?php
namespace Api;

class Authorisation {
	
	static function checkAccessByToken($token, $allowedScopes) {
		if (false === $token->hasScope($allowedScopes)) {
			throw new ForbiddenException("Token not allowed to create users.", 403);
		}
	}
	
}