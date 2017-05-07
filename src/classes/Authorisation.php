<?php
namespace Api;

use Api\Exception\ForbiddenException;

class Authorisation {
	
	static function checkAccessByToken($token, $allowedScopes) {
		if (false === $token->hasScope($allowedScopes)) {
			throw new ForbiddenException("Missing authorisation for scopes " . json_encode($allowedScopes), 403);
		}
	}
	/**
	 * 
	 * @param Token $token
	 * @param string $operation: possible values read, create, update, delete or list 
	 * @param string $resourceId: for user access this is user_id of requested resource
	 */
	static function checkUserAccess($token, $operation, $resourceId) {
// 		echo "check user access";
		if (!isset($token)) {
			return false;
		}
// 		echo "$token set";
		switch ($operation) {
			case "list":
				return $token->hasScope(["users.all", "users.list"]);
			case "read":
				if ($token->hasScope(["users.all", "users.read"])) {
					return true;
				}
				if ($token->hasScope(["users.read.owner"]) && ($resourceId == $token->decoded->sub)) {
					return true;
				}
				return false;
			case "create":
				return $token->hasScope(["users.all", "users.create"]);
			case "update":
				if ($token->hasScope(["users.all", "users.update"])) {
					return true;
				}
				if ($token->hasScope(["users.update.owner"]) && ($resourceId == $token->decoded->sub)) {
					return true;
				}
				return false;
			case "delete":
				return $token->hasScope(["users.all", "users.delete"]);
		}
		
	}
}