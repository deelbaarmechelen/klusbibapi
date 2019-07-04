<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;

class User extends Model
{
	protected $primaryKey = "user_id";
	public $incrementing = false;
	
	static protected $fieldArray = ['user_id', 'state', 'firstname', 'lastname', 'role', 'email', 'email_state',
			'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'city',
			'phone', 'mobile', 'registration_number', 'payment_mode', 'user_ext_id', 'created_at', 'updated_at'
	];
	
    // Accessors and mutators
    public function setEmailAttribute($value) {
        if (isset($value) && !empty($value)
            && (!isset($this->attributes['email']) || $this->attributes['email'] != $value)) {
            $this->attributes['email_state'] = EmailState::CONFIRM_EMAIL;
        }
        $this->attributes['email'] = $value;
    }
    // public methods
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, User::$fieldArray);
	}
	
	public function reservations()
	{
		return $this->hasMany('Api\Model\Reservation', 'user_id');
	}
	
	public function isAdmin() {
		if ($this->role == 'admin') {
			return true;
		}
		return false;
	}

	public function isEmailConfirmed() {
        if ($this->email_state == EmailState::CONFIRMED) {
            return true;
        }
        return false;
    }

	// Query helpers
    public function scopeMembers($query)
    {
        return $query->where('role', '=', UserRole::MEMBER)
            ->orWhere('role', '=', UserRole::ADMIN);
    }
    public function scopeSupporters($query)
    {
        return $query->where('role', '=', UserRole::SUPPORTER);
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
        return $query->where('state', '=', UserState::CHECK_PAYMENT);
    }
    public function scopeAdmin($query)
    {
        return $query->where('role', '=', UserRole::ADMIN);
    }
    public function scopeNotAdmin($query)
    {
        return $query->where('role', '<>', UserRole::ADMIN);
    }
}