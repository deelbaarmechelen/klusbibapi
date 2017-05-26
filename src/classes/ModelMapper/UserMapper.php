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
				"birth_date" => $user->birth_date,
				"address" => $user->address,
				"postal_code" => $user->postal_code,
				"city" => $user->city,
				"phone" => $user->phone,
				"mobile" => $user->mobile,
				"created_at" => $user->created_at,
				"updated_at" => $user->updated_at,
		);
		
		return $userArray;
	}
	static public function mapArrayToUser($data, $user, $isAdmin = false, $logger = null) {
		if (isset($data["user_id"]) && $isAdmin) {
			$user->user_id= $data["user_id"];
		}
		if (isset($data["state"]) && $isAdmin) {
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
		if (isset($data["role"]) && $isAdmin) {
			$user->role = $data["role"];
		}
		if (isset($data["password"])) {
			if (isset($logger)) {
				$logger->info("Updating password for user " . $user->user_id . " - " . $user->firstname . " " . $user->lastname);
			}
			$user->hash = password_hash($data["password"], PASSWORD_DEFAULT);
		}
		if (!empty($data["membership_start_date"]) && $isAdmin) {
			$user->membership_start_date = $data["membership_start_date"];
			if (!empty($data["membership_end_date"])) {
				$user->membership_end_date = $data["membership_end_date"];
			} else { // default to 1 year membership
				$user->membership_end_date = date('Ymd', strtotime("+1 year", strtotime($data["membership_start_date"])));
			}
		}
		if (isset($data["birth_date"])) {
			$user->birth_date = $data["birth_date"];
		}
		if (isset($data["address"])) {
			$user->address = $data["address"];
		}
		if (isset($data["postal_code"])) {
			$user->postal_code = $data["postal_code"];
		}
		if (isset($data["city"])) {
			$user->city = $data["city"];
		}
		if (isset($data["phone"])) {
			$user->phone = $data["phone"];
		}
		if (isset($data["mobile"])) {
			$user->mobile = $data["mobile"];
		}
		
	}
}