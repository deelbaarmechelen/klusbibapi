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
	}
	
	static function isValidUserData($user, $logger) {
		if (!empty($user["registration_number"])) {
			return UserValidator::isValidRegistrationNumber($user["registration_number"]);
		}
		
		return true;
	}
	
	static function isValidRegistrationNumber($registrationNumber) {
		if (!is_numeric($registrationNumber)) {
			return false;
		}
		if (strlen($registrationNumber) != 11) {
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
