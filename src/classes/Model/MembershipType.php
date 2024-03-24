<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;

/**
 * @property mixed $id
 * @property mixed $name
 * @property mixed $price
 * @property mixed $duration
 * @property mixed $discount
 * @property mixed $description
 * @property mixed $self_serve
 * @property mixed $credit_limit
 * @property mixed $max_items
 * @property mixed $is_active
 * @property mixed $next_subscription_id
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class MembershipType extends Model
{
    protected $table = "membership_type";
	static protected $fieldArray = ['id', 'name', 'price', 'duration', 'discount', 'description',
        'self_serve', 'credit_limit', 'max_items', 'is_active', 'next_subscription_id', 'created_at', 'updated_at'
	];

    const REGULAR = 'Regular';
    const RENEWAL = 'Renewal';
    const REGULARREDUCED = 'RegularReduced';
    const RENEWALREDUCED = 'RenewalReduced';
    const REGULARORG = 'RegularOrg';
    const RENEWALORG = 'RenewalOrg';
    const STROOM = 'Stroom';
    const TEMPORARY = 'Temporary';

    static public function regular() {
	    return MembershipType::where('name', '=', self::REGULAR)->firstOrFail();
    }
    static public function renewal() {
        return MembershipType::where('name', '=', self::RENEWAL)->firstOrFail();
    }
    static public function regularReduced() {
	    return MembershipType::where('name', '=', self::REGULARREDUCED)->firstOrFail();
    }
    static public function renewalReduced() {
        return MembershipType::where('name', '=', self::RENEWALREDUCED)->firstOrFail();
    }
    static public function regularOrg() {
        return MembershipType::where('name', '=', self::REGULARORG)->firstOrFail();
    }
    static public function renewalOrg() {
        return MembershipType::where('name', '=', self::RENEWALORG)->firstOrFail();
    }
    static public function stroom() {
        return MembershipType::where('name', '=', self::STROOM)->firstOrFail();
    }
    static public function temporary() {
        return MembershipType::where('name', '=', self::TEMPORARY)->firstOrFail();
    }

    // public methods
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, MembershipType::$fieldArray);
	}
	
	public function memberships()
	{
		return $this->hasMany(\Api\Model\Membership::class, 'subscription_id');
	}

    public function nextMembershipType()
    {
        return $this->belongsTo(\Api\Model\MembershipType::class, 'next_subscription_id');
    }

	// Query helpers
    public function scopeActive($query)
    {
        return $query->where('is_active', '=', true);
    }

    public function isYearlySubscription() {
        return $this->duration == 365 || $this->duration == 366;
    }
    public function isReducedSubscription() : bool {
        return $this->id == MembershipType::regularReduced()->id || $this->id == MembershipType::renewalReduced()->id;
    }
    public function isCompanySubscription() : bool {
        return $this->id == MembershipType::regularOrg()->id || $this->id == MembershipType::renewalOrg()->id;
    }
}