<?php
namespace Api\Validator;
use Api\Model\User;

class UserValidator 
{
	static function isValidUserData($user, $logger) {
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
	
	static function userExists($userid, $logger) {
		$userCount = User::where('user_id', $userid)->count();
		if ($userCount == 0) {
			return false;
		}
		
		return true;
	}
}
