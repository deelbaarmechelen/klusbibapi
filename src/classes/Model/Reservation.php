<?php
namespace Api\Model;

use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $reservation_id
 * @property mixed $user_id
 * @property mixed $tool_id
 * @property mixed $state
 * @property mixed $startsAt
 * @property mixed $endsAt
 * @property mixed $type
 * @property mixed $comment
 * @property mixed $created_at
 * @property mixed $updated_at
 * 
 * Laravel/Eloquent methods
 * @method static Builder whereDate($column, $compare, $date)
 * @method static find($id)
 */
class Reservation extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return ReservationFactory::new();
    }
    protected $table = 'kb_reservations';
	protected $primaryKey = "reservation_id";
	
	static protected $fieldArray = ['reservation_id', 'user_id', 'tool_id', 'state', 'startsAt', 'endsAt', 
			'type', 'comment', 'created_at', 'updated_at'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Reservation::$fieldArray);
	}
	
	/**
	 * Get the user that owns the reservation.
	 */
	public function user()
	{
		return $this->belongsTo('Api\Model\Contact', 'user_id');
	}
	public function item()
	{
		return $this->belongsTo('Api\Model\InventoryItem', 'tool_id');
	}
	public function tool()
	{
		return $this->belongsTo('Api\Model\Tool', 'tool_id');
	}
    public function deliveryItem()
    {
        return $this->hasOne('Api\Model\DeliveryItem', 'reservation_id');
    }
    public function scopeIsRequested($query)
    {
        return $query->where('state', '=', ReservationState::REQUESTED);
    }
    public function scopeIsConfirmed($query)
    {
        return $query->where('state', '=', ReservationState::CONFIRMED);
    }
    public function scopeIsDeleted($query)
    {
        return $query->where('state', '=', ReservationState::DELETED);
    }
    public function scopeNotExpired($query)
    {
        $currentDate = new \DateTime();
        return $query->where('endsAt', '>=', $currentDate);
    }

    public function isCancelled() : bool {
	    return $this->state == ReservationState::CANCELLED;
    }
    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }
}