<?php
namespace Api\Validator;
use Api\Model\User;

class UserValidator 
{
	static function containsMandatoryData($user, $logger) {
		if (empty($user["firstname"])) {
			$logger->info("Missing user firstname");
			return false;
		}
		if (empty($user["lastname"])) {
			$logger->info("Missing user lastname");
			return false;
		}
		if (empty($user["role"])) {
			$logger->info("Missing user role");
			return false;
		}
		return true;
	}
	
	static function isValidUserData($user, $logger) {
		$logger->info("Validating user");
		if (!empty($user["registration_number"])) {
			$logger->info("Validating registration number");
			return UserValidator::isValidRegistrationNumber($user["registration_number"], $logger);
		}
		
		return true;
	}
	
	static function isValidRegistrationNumber($registrationNumber, $logger) {
		if (!is_numeric($registrationNumber)) {
			$logger->info("Registration number not numeric");
			return false;
		}
		if (strlen($registrationNumber) != 11) {
			$logger->info("Registration number has invalid length (expected 11 chars)");
			return false;
		}
		$base = substr($registrationNumber, 0, strlen($registrationNumber) - 2);
		$verificationNumber = substr($registrationNumber, -2);
		
		// born after year 2000 -> prefix with 2
		$current_year = intval(date('y'));
		$birthyear = intval(substr($registrationNumber, 0, 2));
		if ($birthyear <= $current_year) {
			$base = '2' . $base;
		}
		$mod97 = 97 - (intval($base) % 97);
		if ($mod97 != intval($verificationNumber)) {
			$logger->info("Invalid check digit for registration number:" + $verificationNumber);
			return false;
		}
		return true;
	}
	
	static function userExists($userid, $logger) {
		$userCount = User::where('user_id', $userid)->count();
		if ($userCount == 0) {
			return false;
		}
		
		return true;
	}
}
