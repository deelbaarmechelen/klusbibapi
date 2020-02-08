<?php
namespace Api\Validator;

use Api\Model\ReservationState;
use Api\Model\Tool;
use Api\Model\ToolType;
use Api\Tool\ToolManager;

class LendingValidator
{
	static function isValidLendingData($lendingData, $logger, ToolManager $toolManager, bool $new = true) {
		if (empty($lendingData)) {
			return false;
		}
		if (empty($lendingData["user_id"]) && $new) {
			$logger->info("Missing user_id");
			return false;
		}
		if (empty($lendingData["tool_id"]) && $new) {
			$logger->info("Missing tool_id");
			return false;
		}
		if (empty($lendingData["tool_type"]) && $new) {
			$logger->info("Missing tool_type");
			return false;
		}
        if (!empty($lendingData["tool_type"]) && !ToolType::isValid($lendingData["tool_type"])) {
            $logger->info("Invalid tool_type: " . $lendingData["tool_type"]);
            return false;
        }
		if (!empty($lendingData["user_id"]) && !UserValidator::userExists($lendingData["user_id"], $logger)) {
			$logger->info("Inexistant user " . $lendingData["user_id"]);
			return false;
		}
		if (!empty($lendingData["tool_id"])
            && (empty($lendingData["tool_type"]) || $lendingData["tool_type"] == "TOOL")
            && !$toolManager->toolExists($lendingData["tool_id"])) {
			$logger->info("Inexistant tool " . $lendingData["tool_id"]);
			return false;
		}
        if (!empty($lendingData["tool_id"])
            && ($lendingData["tool_type"] == "ACCESSORY")
            && !$toolManager->accessoryExists($lendingData["tool_id"])) {
            $logger->info("Inexistant accessory " . $lendingData["tool_id"]);
            return false;
        }
        if (isset($lendingData["start_date"]) &&
				(FALSE == self::cnvStrToDateTime($lendingData["start_date"], $logger))) {
			$logger->info("Start date (". $lendingData["start_date"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($lendingData["due_date"]) &&
				(FALSE == self::cnvStrToDateTime($lendingData["due_date"], $logger))) {
			$logger->info("Due date (". $lendingData["due_date"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($lendingData["return_date"]) &&
				(FALSE == self::cnvStrToDateTime($lendingData["return_date"], $logger))) {
			$logger->info("Return date (". $lendingData["return_date"] . " has invalid date format (expected YYYY-MM-DD)");
			return false;
		}
		if (isset($lendingData["start_date"]) && isset($lendingData["due_date"])
				&& new \DateTime($lendingData["due_date"]) < new \DateTime($lendingData["start_date"])) {
			$logger->info("Due date (". $lendingData["due_date"] . " cannot be smaller than start date (" . $lendingData["start_date"] . ")");
			return false;
		}
		if (isset($lendingData["start_date"]) && isset($lendingData["return_date"])
				&& new \DateTime($lendingData["return_date"]) < new \DateTime($lendingData["start_date"])) {
			$logger->info("Return date (". $lendingData["return_date"] . " cannot be smaller than start date (" . $lendingData["start_date"] . ")");
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
	static function toolExists($toolid) {
		$toolCount = Tool::where('tool_id', $toolid)->count();
		if ($toolCount == 0) {
			return false;
		}
	
		return true;
	}
}
