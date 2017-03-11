<?php
namespace Api\ModelMapper;

use \Api\Model\User;

class UserMapper
{
	static public function mapUserToArray($user) {
	
		$userArray = array("user_id" => $user->user_id,
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
}