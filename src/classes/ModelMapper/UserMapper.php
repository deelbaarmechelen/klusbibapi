<?php
namespace Api\ModelMapper;

use \Api\Model\User;

class UserMapper
{
	static public function mapUserToArray($user) {
	
		$userArray = array("user_id" => $user->user_id,
				"state" => $user->state,
				"firstname" => $user->firstname,
				"lastname" => $user->lastname,
				"email" => $user->email,
				"role" => $user->role,
				"membership_start_date" => $user->membership_start_date,
				"membership_end_date" => $user->membership_end_date,
				"reservations" => array()
		);
		return $userArray;
	}
	static public function mapArrayToUser($data, $user) {
		if (isset($data["user_id"])) {
			$user->user_id= $data["user_id"];
		}
		if (isset($data["state"])) {
			$user->state = $data["state"];
		}
		if (isset($data["firstname"])) {
			$user->firstname = $data["firstname"];
		}
		if (isset($data["lastname"])) {
			$user->lastname = $data["lastname"];
		}
		if (isset($data["email"])) {
			$user->email = $data["email"];
		}
		if (isset($data["role"])) {
			$user->role = $data["role"];
		}
		if (isset($data["password"])) {
			$this->logger->info("Updating password for user " . $user->user_id . " - " . $user->firstname . " " . $user->lastname);
			$user->hash = password_hash($data["password"], PASSWORD_DEFAULT);
		}
	}
}