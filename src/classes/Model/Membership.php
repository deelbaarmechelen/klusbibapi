<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Config\Definition\Builder\MergeBuilder;

class Membership extends Model
{
    use SoftDeletes;

    protected $table = "membership";
    protected $casts = [
        'start_at'  => 'date:Y-m-d',
        'expires_at' => 'date:Y-m-d',
    ];
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_at','expires_at'
    ];

	static protected $fieldArray = ['id', 'status', 'start_at', 'expires_at', 'subscription_id', 'contact_id',
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
		return $this->hasMany('Api\Model\User', 'active_membership');
	}

    public function subscription() {
        return $this->belongsTo('Api\Model\MembershipType', 'subscription_id');
    }

    public function payment() {
//        return $this->hasOne('Api\Model\Payment', 'membership_id', 'payment_id');
        return $this->hasOne('Api\Model\Payment');
    }

    public function contact() {
        return $this->belongsTo('Api\Model\User', 'contact_id', 'user_id');
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
        return $query->where('start_at', '=', $startAt);
    }
    public function scopeWithUser($query, $userId)
    {
        return $query->where('contact_id', '=', $userId);
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at')
            ->orWhere(function($query)
            {
                $query->whereNotNull('deleted_at')
                    ->where('last_sync_date', '<', 'deleted_at');
            });
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