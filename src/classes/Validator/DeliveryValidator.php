<?php
namespace Api\Validator;

use Api\Model\DeliveryState;
use Api\Model\Tool;
use Api\Tool\ToolManager;

class DeliveryValidator
{
	static function isValidDeliveryData($delivery, $logger, &$errors, bool $new = true) {
        if (empty($delivery)) {
            $message = "No data provided";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
		if (!isset($delivery["user_id"]) && $new) {
            $message = "Missing user_id";
			$logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (empty($delivery["state"]) && $new) {
            $message = "Missing state";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
        if (empty($delivery["pick_up_address"]) && $new) {
            $message = "Missing pick_up_address";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (empty($delivery["drop_off_address"]) && $new) {
            $message = "Missing drop_off_address";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (isset($delivery["user_id"]) && !UserValidator::userExists($delivery["user_id"], $logger)) {
            $message = "Inexistant user " . $delivery["user_id"];
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($delivery["pick_up_date"]) &&
				(FALSE == DeliveryValidator::cnvStrToDateTime($delivery["pick_up_date"], $logger))) {
            $message = "Pick up date (". $delivery["pick_up_date"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($delivery["drop_off_date"]) &&
				(FALSE == DeliveryValidator::cnvStrToDateTime($delivery["drop_off_date"], $logger))) {
            $message = "Drop off date (". $delivery["drop_off_date"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($delivery["pick_up_date"]) && isset($delivery["drop_off_date"])
				&& new \DateTime($delivery["drop_off_date"]) < new \DateTime($delivery["pick_up_date"])) {
            $message = "Drop off date (". $delivery["drop_off_date"] . " cannot be smaller than pick up date (" . $delivery["pick_up_date"] . ")";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (isset($delivery["state"]) &&
            (FALSE == DeliveryValidator::isValidState($delivery["state"], $logger))) {
            $message = "State (". $delivery["state"] . " is invalid (expected "
                . DeliveryState::REQUESTED . "," . DeliveryState::CANCELLED . ", "
                . DeliveryState::CONFIRMED . "," . DeliveryState::DELIVERED . ")";
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
	    if ($state == DeliveryState::REQUESTED
            || $state == DeliveryState::CANCELLED
            || $state == DeliveryState::CONFIRMED
            || $state == DeliveryState::DELIVERED) {
	        return true;
        }
	    return false;
    }
}
