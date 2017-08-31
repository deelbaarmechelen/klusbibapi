<?php
namespace Api\Validator;

use Api\Model\Tool;

class ReservationValidator 
{
	static function isValidReservationData($reservation, $logger) {
		if (empty($reservation)) {
			return false;
		}
		if (empty($reservation["user_id"])) {
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
		if (isset($data["startsAt"]) && (FALSE == new \DateTime($data["startsAt"]))) {
			$logger->info("End date (". $data["startsAt"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($data["endsAt"]) && (FALSE == new \DateTime($data["endsAt"]))) {
			$logger->info("End date (". $data["endsAt"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($data["startsAt"]) && isset($data["endsAt"]) && new \DateTime($data["endsAt"]) < new \DateTime($data["startsAt"])) {
			$logger->info("End date (". $data["endsAt"] . " cannot be smaller than start date (" . $data["startsAt"] . ")");
			return false;
		}
		return true;
	}
	static function toolExists($toolid, $logger) {
		$toolCount = Tool::where('tool_id', $toolid)->count();
		if ($toolCount == 0) {
			return false;
		}
	
		return true;
	}
	
}
