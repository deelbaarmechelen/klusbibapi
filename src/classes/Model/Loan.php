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
 * @method static Builder isReserved()
 * @method static Builder isOpen()
 * @method static Builder isReservation()
 * @method static Builder withSearchQuery($search)
 * 
 * Laravel/Eloquent methods
 * @method static Builder whereDate($column, $compare, $date)
 * @method static Loan find($id)
 */
class Loan extends Model
{
	const STATUS_PENDING = "PENDING";
	const STATUS_RESERVED = "RESERVED";
	const STATUS_CANCELLED = "CANCELLED";
	const STATUS_CLOSED = "CLOSED";

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
    const UPDATED_AT = null;
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
    public function scopeWithSearchQuery($query, $search)
    {
        return $query->filter(function (Loan $loan, int $key) {
            $firstName = $loan->contact->first_name;
            $lastName = $loan->contact->last_name;
            return str_contains($firstName,$search) || str_contains($lastName, $search);
        });
    }

}