<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
	protected $primaryKey = "user_id";
	public $incrementing = false;
	
	static protected $fieldArray = ['user_id', 'state', 'firstname', 'lastname', 'role', 'email', 
			'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'city',
			'phone', 'mobile', 'registration_number', 'payment_mode', 'created_at', 'updated_at'
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
	
	public function isAdmin() {
		if ($this->role == 'admin') {
			return true;
		}
		return false;
	}

    public function scopeActive($query)
    {
        return $query->where('state', '=', 'ACTIVE');
    }
    public function scopeAdmin($query)
    {
        return $query->where('role', '=', 'admin');
    }
    public function scopeNotAdmin($query)
    {
        return $query->where('role', '<>', 'admin');
    }
}