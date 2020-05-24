<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $primaryKey = "delivery_id";
    static protected $fieldArray = ['delivery_id', 'user_id', 'reservation_id', 'state_id', 'pick_up_address',
        'drop_off_address', 'comment', 'date', 'created_at', 'updated_at'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Delivery::$fieldArray);
	}
	public function toolDeliveries()
	{
		return $this->hasMany('Api\Model\ToolDelivery', 'delivery_id');
	}
}