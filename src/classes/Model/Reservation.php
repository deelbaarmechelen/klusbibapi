<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
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
		return $this->belongsTo('Api\Model\User', 'user_id');
	}
	public function tool()
	{
		return $this->belongsTo('Api\Model\Tool', 'tool_id');
	}
    public function deliveryItem()
    {
        return $this->hasOne('Api\Model\DeliveryItem', 'reservation_id');
    }
    public function scopeIsDeleted($query)
    {
        return $query->where('state', '=', ReservationState::DELETED);
    }

    public function isCancelled() : bool {
	    return $this->state == ReservationState::CANCELLED;
    }
}