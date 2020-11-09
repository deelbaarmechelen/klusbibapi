<?php
namespace Api;

use Api\Exception\ForbiddenException;
use Api\Util\HttpResponseCode;

class Authorisation {

    public const OPERATION_READ = "read";
    public const OPERATION_LIST = "list";
    public const OPERATION_CREATE = "create";
    public const OPERATION_UPDATE = "update";

    static function checkAccessByToken($token, $allowedScopes) {
		if (false === $token->hasScope($allowedScopes)) {
			throw new ForbiddenException("Missing authorisation for scopes " . json_encode($allowedScopes), HttpResponseCode::FORBIDDEN);
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
			case self::OPERATION_LIST:
				return $token->hasScope(["users.all", "users.list"]);
            case "read.state":
                return $token->hasScope(["users.all", "users.list", "users.read.state"]);
            case self::OPERATION_READ:
				if ($token->hasScope(["users.all", "users.read"])) {
					return true;
				}
				if ($token->hasScope(["users.read.owner"]) && ($resourceId == $token->decoded->sub)) {
					return true;
				}
				return false;
			case self::OPERATION_CREATE:
				return $token->hasScope(["users.all", "users.create"]);
			case self::OPERATION_UPDATE:
				if ($token->hasScope(["users.all", "users.update"])) {
					return true;
				}
				if ($token->hasScope(["users.update.owner"]) && ($resourceId == $token->decoded->sub)) {
					return true;
				}
				return false;
			case "delete":
				return $token->hasScope(["users.all", "users.delete"]);
            default:
                return AccessType::NO_ACCESS;
		}
		
	}
	
	static function checkReservationAccess($token, $operation, $reservation, $toolOwner = NULL, $logger = NULL) {
        if (isset($logger)) {
            $logger->info("Check reservation access for operation $operation");
        }
		if (!isset($token)) {
			return AccessType::NO_ACCESS;
		}
		switch ($operation) {
			case self::OPERATION_LIST:
				if ($token->hasScope(["reservations.all", "reservations.list"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case self::OPERATION_READ:
				if ($token->hasScope(["reservations.all", "reservations.read"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case self::OPERATION_CREATE:
				if ($token->hasScope(["reservations.all", "reservations.create"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["reservations.create.owner"])
						&& ($reservation->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				if ($token->hasScope(["reservations.create.owner.donation_only"])
						&& ($reservation->user_id == $token->decoded->sub)
						&& isset($toolOwner)
                        && ($toolOwner == $token->decoded->sub)) {
							return AccessType::TOOL_OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case self::OPERATION_UPDATE:
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
            default:
                return AccessType::NO_ACCESS;
		}
    }
    
    static function checkDeliveryAccess($token, $operation, $delivery, $logger = NULL) {
        if (isset($logger)) {
            $logger->info("Check delivery access for operation $operation");
        }
		if (!isset($token)) {
			return AccessType::NO_ACCESS;
		}
		switch ($operation) {
			case self::OPERATION_LIST:
				if ($token->hasScope(["deliveries.all", "deliveries.list"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case self::OPERATION_READ:
				if ($token->hasScope(["deliveries.all", "deliveries.read"])) {
					return AccessType::FULL_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case self::OPERATION_CREATE:
				if ($token->hasScope(["deliveries.all", "deliveries.create"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["deliveries.create.owner"])
						&& ($delivery->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
                }
                
                return AccessType::NO_ACCESS;
                
			case self::OPERATION_UPDATE:
				if ($token->hasScope(["deliveries.all", "deliveries.update"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["deliveries.update.owner"]) && ($delivery->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
			case "delete":
				if ($token->hasScope(["deliveries.all", "deliveries.delete"])) {
					return AccessType::FULL_ACCESS;
				}
				if ($token->hasScope(["deliveries.delete.owner"]) && ($delivery->user_id == $token->decoded->sub)) {
					return AccessType::OWNER_ACCESS;
				}
				return AccessType::NO_ACCESS;
            default:
                return AccessType::NO_ACCESS;
		}
	}

    static function checkPaymentAccess($token, $operation)
    {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case self::OPERATION_LIST:
                if ($token->hasScope(["payments.all", "payments.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case "delete":
                if ($token->hasScope(["payments.all", "payments.delete"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            default:
                return AccessType::NO_ACCESS;
        }
    }

    static function checkEventAccess ($token, $operation) {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case self::OPERATION_LIST:
                if ($token->hasScope(["events.all", "events.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_CREATE:
                if ($token->hasScope(["events.all", "events.create"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            default:
                return AccessType::NO_ACCESS;
        }
    }
    static function checkLendingAccess ($token, $operation) {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case self::OPERATION_LIST:
                if ($token->hasScope(["lendings.all", "lendings.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_READ:
                if ($token->hasScope(["lendings.all", "lendings.read"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_CREATE:
                if ($token->hasScope(["lendings.all", "lendings.create"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_UPDATE:
                if ($token->hasScope(["lendings.all", "lendings.update"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            default:
                return AccessType::NO_ACCESS;
        }
    }
    static function checkMembershipAccess ($token, $operation) {
        if (!isset($token)) {
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case self::OPERATION_LIST:
                if ($token->hasScope(["memberships.all", "memberships.list"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_READ:
                if ($token->hasScope(["memberships.all", "memberships.read"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_CREATE:
                if ($token->hasScope(["memberships.all", "memberships.create"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            case self::OPERATION_UPDATE:
                if ($token->hasScope(["memberships.all", "memberships.update"])) {
                    return AccessType::FULL_ACCESS;
                }
                return AccessType::NO_ACCESS;
            default:
                return AccessType::NO_ACCESS;
        }
    }
    /**
     *
     * @param Token $token
     * @param string $operation: possible values confirm
     */
    static function checkEnrolmentAccess($token, $operation)
    {
        if (!isset($token)) {
            echo "no token set";
            return AccessType::NO_ACCESS;
        }
        switch ($operation) {
            case "confirm":
                return $token->hasScope(["enrolment.confirm"]);
            case "decline":
                return $token->hasScope(["enrolment.decline"]);
        }
        return AccessType::NO_ACCESS;
    }
}