<?php
namespace Api\ModelMapper;

use Api\Model\Membership;
use Api\Model\Contact;

class UserMapper
{
	static public function mapUserToArrayMinimal(Contact $contact) {
		$userArray = array("user_id" => $contact->id,
            "user_ext_id" => $contact->user_ext_id,
            "state" => $contact->state,
            "firstname" => $contact->first_name,
            "lastname" => $contact->last_name,
            "email" => $contact->email,
            "email_state" => $contact->email_state,
            "role" => $contact->role,
        );
		
		return $userArray;
	}
	static public function mapUserToArray(Contact $contact) {
        $membership = Membership::find($user->active_membership);
		$userArray = array("user_id" => $contact->id,
            "user_ext_id" => $contact->user_ext_id,
            "state" => $contact->state,
            //"state" => !$membership ? $contact->state : $membership->status,
            "firstname" => $contact->first_name,
            "lastname" => $contact->last_name,
            "email" => $contact->email,
            "email_state" => $contact->email_state,
            "role" => $contact->role,
            "membership_start_date" => $contact->membership_start_date,
            //"membership_start_date" => !$membership ? $contact->membership_start_date : $membership->start_at,
            "membership_end_date" => $contact->membership_end_date,
            //"membership_end_date" => !$membership ? $contact->membership_end_date : $membership->expires_at,
            "address" => $contact->address_line_1,
            "postal_code" => $contact->address_line_4,
            "city" => $contact->address_line_2,
            "phone" => $contact->telephone,
            "mobile" => $contact->telephone,
            "registration_number" => $contact->registration_number,
            "payment_mode" => $contact->payment_mode,
            "accept_terms_date" => !$contact->accept_terms_date ? null : $contact->accept_terms_date->format('Y-m-d'),
            "last_sync_date" => $contact->last_sync_date,
            "active_membership" => !$membership ? array() : MembershipMapper::mapMembershipToArray($membership),
            "company" => $contact->company,
            "comment" => $contact->comment,
            "created_at" => $contact->created_at,
            "updated_at" => $contact->updated_at,
        );
		
		return $userArray;
	}
	static public function mapArrayToUser($data, Contact $user, $isAdmin = false, $logger = null) {
		if (isset($data["user_id"]) && !empty($data["user_id"]) && $isAdmin) {
			$user->id= $data["user_id"];
		}
		// No longer allow update of state -> should be set based on membership
//		if (isset($data["state"]) && $isAdmin) {
//			$user->state = $data["state"];
//		}
		if (isset($data["firstname"])) {
			$user->first_name = $data["firstname"];
		}
		if (isset($data["lastname"])) {
			$user->last_name = $data["lastname"];
		}
		if (isset($data["email"])) {
			$user->email = $data["email"];
		}
		if (isset($data["email_state"])) {
			$user->email_state = $data["email_state"];
		}
		if (isset($data["role"]) && $isAdmin) {
			$user->role = $data["role"];
		}
		if (isset($data["password"])) {
			if (isset($logger)) {
				$logger->info("Updating password for user " . $user->id . " - " . $user->first_name . " " . $user->last_name);
			}
			$user->password = password_hash($data["password"], PASSWORD_DEFAULT);
		}
		// No longer allow update of membership start and end date -> should be set based on active membership
//		if (!empty($data["membership_start_date"])
//            && ($isAdmin || empty($user->membership_end_date))) {
//		    // Once set, only admins can change end_date. Check for empty to allow set of initial value (defaults to 1 year membership)
//			$user->membership_start_date = $data["membership_start_date"];
//			if (!empty($data["membership_end_date"])) {
//				$user->membership_end_date = $data["membership_end_date"];
//			} else { // default to 1 year membership
//				$user->membership_end_date = date('Y-m-d', strtotime("+1 year", strtotime($data["membership_start_date"])));
//			}
//		}
		if (isset($data["address"])) {
			$user->address_line_1 = $data["address"];
		}
		if (isset($data["city"])) {
			$user->address_line_2 = $data["city"];
		}
		if (isset($data["postal_code"])) {
			$user->address_line_4 = $data["postal_code"];
		}
		if (isset($data["phone"])) {
			$user->telephone = $data["phone"];
		}
		if (isset($data["mobile"])) {
			$user->telephone = $data["mobile"];
		}
		if (isset($data["registration_number"])) {
			$user->registration_number = $data["registration_number"];
		}
		if (isset($data["payment_mode"])) {
			$user->payment_mode = $data["payment_mode"];
		}
		if (isset($data["accept_terms_date"])) {
			$user->accept_terms_date = $data["accept_terms_date"];
		}
        if (isset($data["company"])) {
            $user->company = $data["company"];
        }
        if (isset($data["comment"])) {
            $user->comment = $data["comment"];
        }
	}

}