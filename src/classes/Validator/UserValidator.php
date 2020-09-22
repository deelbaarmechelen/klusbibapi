<?php
namespace Api\Validator;
use Api\Model\User;

class UserValidator 
{
	static function containsMandatoryData($user, $logger, &$errors) {
        if (empty($user["firstname"])) {
            $message = "Missing user firstname";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
        if (empty($user["lastname"])) {
            $message = "Missing user lastname";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
        if (empty($user["role"])) {
            $message = "Missing user role";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }

        if (empty($user["email"])) {
            $message = "Missing user email";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
		return true;
	}

    /**
     * @param $user user to be validated as received from POST or PUT request
     * @param $logger to sent messages to
     * @param $errors array containing previous and new errors
     * @return bool
     */
	static function isValidUserData($user, $logger, &$errors) {
        $logger->info("Validating user");
        if (!empty($user["registration_number"])) {
            $logger->info("Validating registration number " . $user["registration_number"]);
            return UserValidator::isValidRegistrationNumber($user["registration_number"], $logger, $errors);
        }

		return true;
	}

    /**
     * @param $registrationNumber
     * @param $logger
     * @param $errors array containing previous and new errors
     * @return bool
     */
	static function isValidRegistrationNumber($registrationNumber, $logger, &$errors) {
        if (!is_numeric($registrationNumber)) {
            $message = "Registration number not numeric";
            $logger->info($message);
            array_push($errors, $message);
            return false;
        }
        if (strlen($registrationNumber) != 11) {
            $message = "Registration number has invalid length (expected 11 chars)";
            $logger->info($message);
            array_push($errors, $message);
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
            $message = "Invalid check digit for registration number: " . $verificationNumber;
            $logger->info($message);
            array_push($errors, $message);
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
