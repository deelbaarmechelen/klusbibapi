<?php
namespace Api\Validator;

use Api\Model\ReservationState;
use Api\Model\Tool;
use Api\Tool\ToolManager;

class ReservationValidator 
{
	static function isValidReservationData($reservation, $logger, ToolManager $toolManager, &$errors) {
        if (empty($reservation)) {
			return false;
		}
		if (!isset($reservation["user_id"])) {
            $message = "Missing user_id";
			$logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (empty($reservation["tool_id"])) {
            $message = "Missing tool_id";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (!UserValidator::userExists($reservation["user_id"], $logger)) {
            $message = "Inexistant user " . $reservation["user_id"];
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
//		if (!self::toolExists($reservation["tool_id"], $logger)) {
		if (!$toolManager->toolExists($reservation["tool_id"])) {
            $message = "Inexistant tool " . $reservation["tool_id"];
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($reservation["startsAt"]) &&
				(FALSE == ReservationValidator::cnvStrToDateTime($reservation["startsAt"], $logger))) {
            $message = "End date (". $reservation["startsAt"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($reservation["endsAt"]) &&
				(FALSE == ReservationValidator::cnvStrToDateTime($reservation["endsAt"], $logger))) {
            $message = "End date (". $reservation["endsAt"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($reservation["startsAt"]) && isset($reservation["endsAt"]) 
				&& new \DateTime($reservation["endsAt"]) < new \DateTime($reservation["startsAt"])) {
            $message = "End date (". $reservation["endsAt"] . " cannot be smaller than start date (" . $reservation["startsAt"] . ")";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (isset($reservation["state"]) &&
            (FALSE == ReservationValidator::isValidState($reservation["state"], $logger))) {
            $message = "State (". $reservation["state"] . " is invalid (expected "
                . ReservationState::REQUESTED . "," . ReservationState::CANCELLED . ", "
                . ReservationState::CONFIRMED . "," . ReservationState::CLOSED . ")";
            $logger->info($message);
            array_push($errors, $message);
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
