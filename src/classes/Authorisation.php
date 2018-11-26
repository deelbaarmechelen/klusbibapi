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
            case "read.state":
                return $token->hasScope(["users.all", "users.list", "users.read.state"]);
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
	
	static function checkReservationAccess($token, $operation, $reservation, $toolOwner = NULL) {
		if (!isset($token)) {
			return AccessType::NO_ACCESS;
		}
		switch ($operation) {
			case "list":
				if ($token->hasScope(["reservations.all", "reservations.list"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case "read":
				if ($token->hasScope(["reservations.all", "reservations.read"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case "create":
				if ($token->hasScope(["reservations.all", "reservations.create"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["reservations.create.owner"])
						&& ($reservation->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				if ($token->hasScope(["reservations.create.owner.donation_only"])
						&& ($reservation->user_id == $token->decoded->sub)
						&& ($toolOwner == $token->decoded->sub)) {
							return AccessType::TOOL_OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case "update":
				if ($token->hasScope(["reservations.all", "reservations.update"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["reservations.update.owner"]) && ($reservation->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case "delete":
				if ($token->hasScope(["reservations.all", "reservations.delete"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["reservations.delete.owner"]) && ($reservation->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
		}
	}

    static function checkPaymentAccess($token, $operation)
    {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case "list":
                if ($token->hasScope(["payments.all", "payments.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
        }
    }

    static function checkEventAccess ($token, $operation) {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case "list":
                if ($token->hasScope(["events.all", "events.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case "create":
                if ($token->hasScope(["events.all", "events.create"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
        }
    }
}