<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;

class MembershipType extends Model
{
    protected $table = "membership_type";
	static protected $fieldArray = ['id', 'name', 'price', 'duration', 'discount', 'description',
        'self_serve', 'credit_limit', 'max_items', 'is_active', 'next_subscription_id', 'created_at', 'updated_at'
	];

	static public function regular() {
	    return MembershipType::where('name', '=', 'Regular')->firstOrFail();
    }
    static public function renewal() {
        return MembershipType::where('name', '=', 'Renewal')->firstOrFail();
    }
    static public function stroom() {
        return MembershipType::where('name', '=', 'Stroom')->firstOrFail();
    }

    // public methods
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, User::$fieldArray);
	}
	
	public function memberships()
	{
		return $this->hasMany('Api\Model\Membership', 'subscription_id');
	}

    public function nextMembershipType()
    {
        return $this->hasOne('Api\Model\MembershipType', 'next_subscription_id');
    }

	// Query helpers
    public function scopeActive($query)
    {
        return $query->where('is_active', '=', true);
    }
}