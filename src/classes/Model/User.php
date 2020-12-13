<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
//    use SoftDeletes;

    protected $primaryKey = "user_id";
	public $incrementing = false;

	static protected $fieldArray = ['user_id', 'state', 'firstname', 'lastname', 'role', 'email', 'email_state',
			'membership_start_date', 'membership_end_date', 'birth_date', 'address', 'postal_code', 'city',
			'phone', 'mobile', 'registration_number', 'payment_mode', 'accept_terms_date', 'user_ext_id',
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
        return "{$this->firstname} {$this->lastname}";
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

    /**
     * The projects this user participates to.
     */
    public function projects()
    {
        return $this->belongsToMany('Api\Model\Project', 'project_user', 'user_id', 'project_id')
            ->as('membership')
            ->withTimestamps();
    }

    public function activeMembership() {
        return $this->belongsTo('Api\Model\Membership', 'active_membership');
    }
    public function memberships() {
        return $this->hasMany('Api\Model\Membership', 'contact_id', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany('Api\Model\Payment', 'user_id', 'user_id');
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
            $query->where('status', '=', Membership::STATUS_ACTIVE);
        });
    }
    public function scopeExpired($query)
    {
//        return $query->where('state', '=', UserState::EXPIRED);
        return $query->whereHas('activeMembership', function (Builder $query) {
            $query->where('status', '=', Membership::STATUS_EXPIRED);
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
            $query->where('status', '=', Membership::STATUS_PENDING);
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
    public function scopeSearchName($query, $search)
    {
        if ($search) {
            $query->where('firstname', 'LIKE', '%'.$search.'%' )
                  ->orWhere('lastname', 'LIKE', '%'.$search.'%' );
        }
    }
    public function scopeStroom($query)
    {
        return $query->whereHas('projects', function (Builder $query) {
            $query->where('name', '=', 'STROOM');
        });
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}