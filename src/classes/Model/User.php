<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
	protected $primaryKey = "user_id";
	public $incrementing = false;
	
	static protected $fieldArray = ['user_id', 'state', 'firstname', 'lastname', 'role', 'email', 
			'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'ciy',
			'phone', 'mobile', 'created_at', 'updated_at'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, User::$fieldArray);
	}
	
	public function reservations()
	{
		return $this->hasMany('Api\Model\Reservation');
	}
}