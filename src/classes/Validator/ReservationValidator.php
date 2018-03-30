<?php
namespace Api\Validator;

use Api\Model\ReservationState;
use Api\Model\Tool;

class ReservationValidator 
{
	static function isValidReservationData($reservation, $logger) {
		if (empty($reservation)) {
			return false;
		}
		if (!isset($reservation["user_id"])) {
			$logger->info("Missing user_id");
			return false;
		}
		if (empty($reservation["tool_id"])) {
			$logger->info("Missing tool_id");
			return false;
		}
		if (!UserValidator::userExists($reservation["user_id"], $logger)) {
			$logger->info("Inexistant user " . $reservation["user_id"]);
			return false;
		}
		if (!self::toolExists($reservation["tool_id"], $logger)) {
			$logger->info("Inexistant tool " . $reservation["tool_id"]);
			return false;
		}
		if (isset($reservation["startsAt"]) && 
				(FALSE == ReservationValidator::cnvStrToDateTime($reservation["startsAt"], $logger))) {
			$logger->info("End date (". $reservation["startsAt"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($reservation["endsAt"]) && 
				(FALSE == ReservationValidator::cnvStrToDateTime($reservation["endsAt"], $logger))) {
			$logger->info("End date (". $reservation["endsAt"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($reservation["startsAt"]) && isset($reservation["endsAt"]) 
				&& new \DateTime($reservation["endsAt"]) < new \DateTime($reservation["startsAt"])) {
			$logger->info("End date (". $reservation["endsAt"] . " cannot be smaller than start date (" . $reservation["startsAt"] . ")");
			return false;
		}
        if (isset($reservation["state"]) &&
            (FALSE == ReservationValidator::isValidState($reservation["state"], $logger))) {
            $logger->info("State (". $reservation["state"] . " is invalid (expected "
                . ReservationState::REQUESTED . "," . ReservationState::CANCELLED . ", "
                . ReservationState::CONFIRMED . "," . ReservationState::CLOSED . ")");
            return false;
        }
		return true;
	}
	static private function cnvStrToDateTime($str, $logger) {
		try {
			$date = new \DateTime($str);
		} catch (\Exception $e) {
			$logger->warn($e->getMessage());
			return false;
		}
		return $date;
	}
	static function toolExists($toolid, $logger) {
		$toolCount = Tool::where('tool_id', $toolid)->count();
		if ($toolCount == 0) {
			return false;
		}
	
		return true;
	}
	static private function isValidState($state) {
	    if ($state == ReservationState::REQUESTED
            || $state == ReservationState::CONFIRMED
            || $state == ReservationState::CANCELLED
            || $state == ReservationState::CLOSED) {
	        return true;
        }
	    return false;
    }
}
