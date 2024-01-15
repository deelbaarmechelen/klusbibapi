<?php
namespace Api\Validator;
use Api\Model\Contact;

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
     * @param $user Contact to be validated as received from POST or PUT request
     * @param $logger to sent messages to
     * @param $errors array containing previous and new errors
     * @return bool
     */
	static function isValidUserData($user, $logger, &$errors) {
        $logger->info("Validating user");
        // if (!empty($user["registration_number"])) {
        //     $logger->info("Validating registration number " . $user["registration_number"]);
        //     return UserValidator::isValidRegistrationNumber($user["registration_number"], $logger, $errors);
        // }

		return true;
	}
	
	static function userExists($userid, $logger) {
		$userCount = Contact::where('id', $userid)->count();
		if ($userCount == 0) {
			return false;
		}
		
		return true;
	}
}
