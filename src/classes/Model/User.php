<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
	protected $primaryKey = "user_id";
	static protected $fieldArray = ['user_id', 'firstname', 'lastname', 'role', 'email', 
			'membership_start_date', 'membership_end_date', 'created_at', 'updated_at'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, User::$fieldArray);
	}
}