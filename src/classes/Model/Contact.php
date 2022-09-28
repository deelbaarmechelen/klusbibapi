<?php
namespace Api\Model;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Api\Model\UserRole;
use Illuminate\Database\Eloquent\SoftDeletes;

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
			'membership_start_date', 'membership_end_date', 'address', 'postal_code', 'city',
			'phone', 'telephone', 'registration_number', 'payment_mode', 'accept_terms_date', 'user_ext_id',
            'last_sync_date', 'active_membership', 'company', 'comment', 'last_login', 'created_at', 'updated_at'
	];


    /**
     * id Primaire sleutel	int(11)			Nee	Geen		AUTO_INCREMENT	Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    2	created_by Index	int(11)			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    3	active_membership Index	int(11)			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    4	enabled	tinyint(1)			Nee	Geen			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    5	salt	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    6	password	varchar(255)	utf8mb4_unicode_ci		Nee	Geen			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    7	last_login	datetime			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    8	confirmation_token Index	varchar(180)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    9	password_requested_at	datetime			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    10	roles	longtext	utf8mb4_unicode_ci		Nee	Geen	(DC2Type:array)		Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    11	first_name	varchar(32)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    12	last_name	varchar(32)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    13	telephone	varchar(64)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    14	address_line_1	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    15	address_line_2	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    16	address_line_3	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    17	address_line_4	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    18	country_iso_code	varchar(3)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    19	latitude	varchar(32)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    20	longitude	varchar(32)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    21	gender	varchar(1)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    22	created_at	datetime			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    23	balance	decimal(10,2)			Nee	Geen			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    24	stripe_customer_id	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    25	subscriber	tinyint(1)			Nee	Geen			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    26	email	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    27	email_canonical	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    28	username	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    29	username_canonical	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    30	active_site Index	int(11)			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    31	created_at_site Index	int(11)			Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    32	locale	varchar(255)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    33	is_active	tinyint(1)			Nee	1			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    34	membership_number	varchar(64)	utf8mb4_unicode_ci		Ja	NULL			Veranderen Veranderen	Verwijderen Verwijderen
    Meer Meer
    35	secure_access_token	varchar(255)	utf8mb4_unicode_ci		Ja	NULL
     */
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
	
	public function reservations()
	{
		return $this->hasMany('Api\Model\Reservation', 'user_id');
	}
    public function deliveries()
    {
        return $this->hasMany('Api\Model\Delivery', 'user_id');
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
        return $this->hasMany('Api\Model\Membership', 'contact_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany('Api\Model\Payment', 'user_id', 'id');
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
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}