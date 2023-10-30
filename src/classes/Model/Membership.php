<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Config\Definition\Builder\MergeBuilder;

/**
 * @method static Builder anyStatus()
 * @method static Builder active()
 * @method static Builder pending()
 * @method static Builder open()
 * @method static Builder expired()
 * @method static Builder cancelled()
 * @method static Builder withStatus($status)
 * @method static Builder withSubscriptionId($id)
 * @method static Builder withStartAt($startAt)
 * @method static Builder withUser($user)
 * @method static Builder createdBetweenDates($startDate, $endDate)
 */
class Membership extends Model
{
    use SoftDeletes;

    protected $table = "membership";
    protected $casts = [
        'starts_at'  => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
    ];
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'starts_at','expires_at'
    ];

	static protected $fieldArray = ['id', 'status', 'starts_at', 'expires_at', 'subscription_id', 'contact_id',
        'last_payment_mode', 'comment', 'created_at', 'updated_at', 'deleted_at'
	];
	
    // public methods
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Membership::$fieldArray);
	}
	
	public function members() {
		return $this->hasMany('Api\Model\Contact', 'active_membership');
	}

    public function subscription() {
        return $this->belongsTo('Api\Model\MembershipType', 'subscription_id');
    }

    public function payment() {
//        return $this->hasOne('Api\Model\Payment', 'membership_id', 'payment_id');
        return $this->hasOne('Api\Model\Payment');
    }

    public function contact() {
        return $this->belongsTo('Api\Model\Contact', 'contact_id', 'id');
    }

    // Query helpers
    /**
     * This dummy query helper allows to use query builder
     * When using the equivalent 'all()' method, a Collection is returned making it difficult to use fluent query builder interface
     * @param $query
     * @return mixed
     */
    public function scopeAnyStatus($query)
    {
        return $query;
    }
    public function scopeActive($query)
    {
        return $query->where('status', '=', MembershipState::STATUS_ACTIVE);
    }
    public function scopePending($query)
    {
        return $query->where('status', '=', MembershipState::STATUS_PENDING);
    }
    public function scopeOpen($query)
    {
        return $query->where('status', '=', MembershipState::STATUS_ACTIVE)
            ->orWhere('status', '=', MembershipState::STATUS_PENDING);
    }
    public function scopeExpired($query)
    {
        return $query->where('status', '=', MembershipState::STATUS_EXPIRED);
    }
    public function scopeCancelled($query)
    {
        return $query->where('status', '=', MembershipState::STATUS_CANCELLED);
    }
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', '=', $status);
    }
    public function scopeWithSubscriptionId($query, $subscriptionId)
    {
        return $query->where('subscription_id', '=', $subscriptionId);
    }
    public function scopeWithStartAt($query, $startAt)
    {
        return $query->where('starts_at', '=', $startAt);
    }
    public function scopeWithUser($query, $userId)
    {
        return $query->where('contact_id', '=', $userId);
    }
    public function scopeCreatedBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
    }

    // Validation
    public static function isValidStatus($status) {
	    if ($status == MembershipState::STATUS_PENDING
         || $status == MembershipState::STATUS_ACTIVE
         || $status == MembershipState::STATUS_CANCELLED
         || $status == MembershipState::STATUS_EXPIRED
        ) {
	        return true;
        }
        throw new \Exception("Invalid membership status $status");
    }
}