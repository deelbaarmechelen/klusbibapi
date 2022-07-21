<?php
namespace Api\Validator;

use Api\Model\MembershipState;
use Api\Model\ReservationState;
use Api\Model\Tool;
use Api\Tool\ToolManager;

class MembershipValidator
{
	static function isValidMembershipData($membership, $logger, &$errors) {
        if (empty($membership)) {
			return false;
		}
        if (!isset($membership["subscription_id"])) {
            $message = "Missing subscription_id";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
        // FIXME: Is payment mode a mandatory field?
//        if (!isset($membership["last_payment_mode"])) {
//            $message = "Missing last_payment_mode";
//            $logger->info($message);
//            array_push($errors, $message);
//            return false;
//        }
        if (isset($membership["contact_id"]) && !UserValidator::userExists($membership["contact_id"], $logger)) {
            $message = "Inexistant user " . $membership["contact_id"];
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($membership["startsAt"]) &&
				(FALSE == MembershipValidator::cnvStrToDateTime($membership["startsAt"], $logger))) {
            $message = "End date (". $membership["startsAt"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($membership["endsAt"]) &&
				(FALSE == MembershipValidator::cnvStrToDateTime($membership["endsAt"], $logger))) {
            $message = "End date (". $membership["endsAt"] . " has invalid date format (expected YYYY-MM-DD)";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
		if (isset($membership["startsAt"]) && isset($membership["endsAt"])
				&& new \DateTime($membership["endsAt"]) < new \DateTime($membership["startsAt"])) {
            $message = "End date (". $membership["endsAt"] . " cannot be smaller than start date (" . $membership["startsAt"] . ")";
            $logger->info($message);
            array_push($errors, $message);
			return false;
		}
        if (isset($membership["status"]) &&
            (FALSE == MembershipValidator::isValidState($membership["status"], $logger))) {
            $message = "State (". $membership["status"] . " is invalid (expected "
                . MembershipState::STATUS_PENDING . "," . MembershipState::STATUS_CANCELLED . ", "
                . MembershipState::STATUS_ACTIVE . "," . MembershipState::STATUS_EXPIRED . ")";
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
	static private function isValidState($state) {
	    return $state == MembershipState::STATUS_PENDING
            || $state == MembershipState::STATUS_CANCELLED
            || $state == MembershipState::STATUS_ACTIVE
            || $state == MembershipState::STATUS_EXPIRED;
    }
}
