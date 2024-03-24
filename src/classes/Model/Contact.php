<?php
namespace Api\Model;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $id
 * @property mixed $state
 * @property mixed $first_name
 * @property mixed $last_name
 * @property mixed $role
 * @property mixed $email
 * @property mixed $email_state
 * @property mixed $membership_start_date
 * @property mixed $membership_end_date
 * @property mixed $address_line_1
 * @property mixed $address_line_4
 * @property mixed $address_line_2
 * @property mixed $phone
 * @property mixed $telephone
 * @property mixed $payment_mode
 * @property mixed $accept_terms_date
 * @property mixed $user_ext_id
 * @property mixed $last_sync_date
 * @property mixed $active_membership
 * @property mixed $company
 * @property mixed $comment
 * @property mixed $last_login
 * @property mixed $created_at
 * @property mixed $updated_at
 * 
 * @method static Builder members ()
 * @method static Builder supporters ()
 * @method static Builder active ()
 * @method static Builder expired ()
 * @method static Builder isDeleted ()
 * @method static Builder notDeleted ()
 * @method static Builder pending ()
 * @method static Builder admin ()
 * @method static Builder notAdmin ()
 * @method static Builder stroom ()
 * @method static Builder overdue()
 * @method static Builder outOfSync ()
 *
 * @method static Builder where(...$params)
 */
class Contact extends Model
{
    //    use SoftDeletes;
    use HasFactory;

    protected $table = 'contact';

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected $primaryKey = "id";
	public $incrementing = false;

	static protected $fieldArray = ['id', 'state', 'first_name', 'last_name', 'role', 'email', 'email_state',
			'membership_start_date', 'membership_end_date', 'address_line_1', 'address_line_4', 'address_line_2',
			'phone', 'telephone', 'payment_mode', 'accept_terms_date', 'user_ext_id',
            'last_sync_date', 'active_membership', 'company', 'comment', 'last_login', 'created_at', 'updated_at'
	];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'accept_terms_date' => 'datetime:Y-m-d',
    ];

    // Accessors and mutators
    public function setEmailAttribute($value) {
        if (isset($value) && !empty($value)
            && (!isset($this->attributes['email']) || $this->attributes['email'] != $value)) {
            $this->attributes['email_state'] = EmailState::CONFIRM_EMAIL;
        }
        $this->attributes['email'] = $value;
    }

    public function getFullNameAttribute() {
        return "{$this->first_name} {$this->last_name}";
    }
    // public methods
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Contact::$fieldArray);
	}
	
	// public function reservations()
	// {
	// 	return $this->hasMany('Api\Model\Reservation', 'user_id');
	// }
    // public function deliveries()
    // {
    //     return $this->hasMany('Api\Model\Delivery', 'user_id');
    // }

    /**
     * The projects this user participates to.
     */
    public function projects()
    {
        return $this->belongsToMany(\Api\Model\Project::class, 'kb_project_user', 'user_id', 'project_id')
            ->as('membership')
            ->withTimestamps();
    }

    public function activeMembership() {
        return $this->belongsTo(\Api\Model\Membership::class, 'active_membership');
    }
    public function memberships() {
        return $this->hasMany(\Api\Model\Membership::class, 'contact_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(\Api\Model\Payment::class, 'user_id', 'id');
    }

    public function isAdmin() {
		if ($this->role == 'admin') {
			return true;
		}
		return false;
	}

    public function isStroomParticipant() {
        return $this->projects()->where('name', '=', 'STROOM')->count() > 0;
    }

    public function addToStroomProject() {
        $stroomProject = Project::where('name', 'STROOM')->first();
        if (!$stroomProject ) {
            return; // stroom project not defined
        }
        if ($this->isStroomParticipant()) {
            return;
        }
        $this->projects()->attach($stroomProject->id);
    }
    public function removeFromStroomProject() {
        $stroomProject = Project::where('name', 'STROOM')->first();
        if (!$stroomProject ) {
            return; // stroom project not defined
        }
        if (!$this->isStroomParticipant()) {
            return;
        }
        $this->projects()->detach($stroomProject->id);
    }

    public function isEmailConfirmed() {
        if ($this->email_state == EmailState::CONFIRMED) {
            return true;
        }
        return false;
    }

    public function hasLoggedAtLeastOnce() {
        return isset($this->last_login);
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
//        return $query->where('state', '=', UserState::ACTIVE);
        return $query->whereHas('activeMembership', function (Builder $query) {
            $query->where('status', '=', MembershipState::STATUS_ACTIVE);
        });
    }
    public function scopeExpired($query)
    {
//        return $query->where('state', '=', UserState::EXPIRED);
        return $query->whereHas('activeMembership', function (Builder $query) {
            $query->where('status', '=', MembershipState::STATUS_EXPIRED);
        });
    }
    public function scopeIsDeleted($query)
    {
        return $query->where('state', '=', UserState::DELETED);
    }
    public function scopeNotDeleted($query)
    {
        return $query->where('state', '<>', UserState::DELETED);
    }
    public function scopePending($query)
    {
//        return $query->where('state', '=', UserState::CHECK_PAYMENT);
        return $query->whereHas('activeMembership', function (Builder $query) {
            $query->where('status', '=', MembershipState::STATUS_PENDING);
        });
    }
    public function scopeAdmin($query)
    {
        return $query->where('role', '=', UserRole::ADMIN);
    }
    public function scopeNotAdmin($query)
    {
        return $query->where('role', '<>', UserRole::ADMIN);
    }
    public function scopeHasMembership($query, $membershipId)
    {
        return $query->where('active_membership', '=', $membershipId);
    }
    public function scopeSearchName($query, $search)
    {
        if ($search) {
            $query->where('first_name', 'LIKE', '%'.$search.'%' )
                  ->orWhere('last_name', 'LIKE', '%'.$search.'%' );
        }
    }
    public function scopeStroom($query)
    {
        return $query->whereHas('projects', function (Builder $query) {
            $query->where('name', '=', 'STROOM');
        });
    }
    public function scopeCreatedBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}