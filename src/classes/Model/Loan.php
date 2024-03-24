<?php

namespace Api\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property mixed $id
 * @property mixed $created_by
 * @property mixed $contact_id
 * @property mixed $status
 * @property mixed $datetime_out
 * @property mixed $datetime_in
 * @property mixed $reference
 * @property mixed $total_fee
 * @property mixed $created_at_site
 * @property mixed $collect_from
 * @property mixed $created_at
 * 
 * @method static Builder isActive()
 * @method static Builder isOverdue()
 * @method static Builder isReserved()
 * @method static Builder isOpen()
 * @method static Builder isReservation()
 * @method static Builder isLending()
 * @method static Builder validLending()
 * @method static Builder activeLending()
 * @method static Builder withContact($contact)
 * @method static Builder withInventoryItem($inventoryItemId)
 * @method static Builder withCheckoutDate($checkoutDate)
 * @method static Builder withSearchQuery($search)
 * 
 * Laravel/Eloquent methods
 * @method static Builder whereDate($column, $compare, $date)
 * @method static Loan find($id)
 */
class Loan extends Model
{
	public const STATUS_PENDING = "PENDING";
	public const STATUS_ACTIVE = "ACTIVE";
	public const STATUS_OVERDUE = "OVERDUE";
	public const STATUS_RESERVED = "RESERVED";
	public const STATUS_CANCELLED = "CANCELLED";
	public const STATUS_CLOSED = "CLOSED";

    protected $table = 'loan';
    //protected $primaryKey = "id";
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    //protected $fillable = [];
	static protected $fieldArray = ['id', 'contact_id', 'created_by', 'status', 'datetime_out', 'datetime_in', 'reference', 'total_fee',
        'created_at_site', 'collect_from', 'created_at'
	];
    public const UPDATED_AT = null;
    public $timestamps = true;

    // public static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($model) {
    //         $model->created_at = $model->freshTimestamp();
    //     });
    // }
    
    public function rows() {
        return $this->hasMany(LoanRow::class);
    }

    public function notes() {
        return $this->hasMany(Note::class);
    }
    public function contact() {
        return $this->belongsTo(\Api\Model\Contact::class, 'contact_id', 'id');
    }

    public function scopeIsActive($query)
    {
        return $query->where('status', '=', Loan::STATUS_ACTIVE);
    }
    public function scopeIsOverdue($query)
    {
        return $query->where('status', '=', Loan::STATUS_OVERDUE);
    }
    public function scopeIsReserved($query)
    {
        return $query->where('status', '=', Loan::STATUS_RESERVED);
    }
    public function scopeIsOpen($query)
    {
        return $query->where('status', '=', Loan::STATUS_PENDING)
            ->orWhere('status', '=', Loan::STATUS_RESERVED);
    }
    public function scopeIsReservation($query)
    {
        return $query->whereHas('rows', function (Builder $query) {
            $query->whereNull('checked_out_at');
        });
    }
    public function scopeIsLending($query)
    {
        return $query->whereHas('rows', function (Builder $query) {
            $query->whereNotNull('checked_out_at');
        });
    }
    public function scopeValidLending($query)
    {
        return $query->isLending()
            ->has('contact')
            ->whereHas('rows', function (Builder $query) {
                $query->has('inventoryItem');
            });
    }
    public function scopeActiveLending($query)
    {
        return $query->isLending()
            ->whereHas('rows', function (Builder $query) {
                $query->whereNull('checked_in_at');
        });
    }
    public function scopeWithContact($query, $contactId)
    {
        return $query->where('contact_id', '=', $contactId);
    }

    public function scopeWithInventoryItem($query, $inventoryItemId)
    {
        return $query->whereHas('rows', function (Builder $query) use ($inventoryItemId) {
            $query->where('inventory_item_id', '=', $inventoryItemId);
        });
    }
    public function scopeWithCheckoutDate($query, $checkoutDate)
    {
        return $query->whereHas('rows', function (Builder $query) use ($checkoutDate) {
            $query->where('checked_out_at', '=', $checkoutDate);
        });
    }
    public function scopeWithSearchQuery($query, $search)
    {
        return $query->filter(function (Loan $loan, int $key) use ($search){
            $firstName = $loan->contact->first_name;
            $lastName = $loan->contact->last_name;
            return str_contains($firstName,$search) || str_contains($lastName, $search);
        });
    }

}