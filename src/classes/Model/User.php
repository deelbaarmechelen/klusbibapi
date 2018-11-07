<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
	protected $primaryKey = "user_id";
	public $incrementing = false;
	
	static protected $fieldArray = ['user_id', 'state', 'firstname', 'lastname', 'role', 'email', 'email_state',
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

    public function scopeMembers($query)
    {
        return $query->where('role', '=', 'member')
            ->orWhere('role', '=', 'admin');
    }
    public function scopeSupporters($query)
    {
        return $query->where('role', '=', 'supporter');
    }
    public function scopeActive($query)
    {
        return $query->where('state', '=', UserState::ACTIVE);
    }
    public function scopeExpired($query)
    {
        return $query->where('state', '=', UserState::EXPIRED);
    }
    public function scopePending($query)
    {
        return $query->where('state', '=', UserState::CHECK_PAYMENT)
            ->orWhere('state', '=', UserState::CONFIRM_EMAIL);
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