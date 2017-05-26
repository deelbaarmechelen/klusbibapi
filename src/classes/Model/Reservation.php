<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
	protected $primaryKey = "reservation_id";
	
	/**
	 * Get the user that owns the reservation.
	 */
	public function user()
	{
		return $this->belongsTo('Api\Model\User');
	}
}