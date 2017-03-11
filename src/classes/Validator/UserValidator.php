<?php
namespace Api\Validator;

class UserValidator 
{
	static function isValidUserData($user, $logger) {
		if (empty($user->firstname)) {
			$logger->info("Missing user firstname");
			return false;
		}
		if (empty($user->lastname)) {
			$logger->info("Missing user lastname");
			return false;
		}
		if (empty($user->role)) {
			$logger->info("Missing user role");
			return false;
		}
	
		return true;
	}
	
}
