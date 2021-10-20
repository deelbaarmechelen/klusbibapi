<?php
namespace Api\Model;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $primaryKey = "id";
	static protected $fieldArray = ['id', 'user_id', 
		'state', 'pick_up_address', 'reservation_id',
		'drop_off_address', 'comment', 'pick_up_date','drop_off_date', 
		'created_at', 'updated_at'
	];
	
	public static function canBeSortedOn($field) {
		if (!isset($field)) {
			return false;
		}
		return in_array($field, Delivery::$fieldArray);
	}

    public function deliveryItems() {
        return $this->hasMany('Api\Model\DeliveryItem', 'delivery_id',
            'id');
    }

    /**
     * Get the user that owns the delivery.
     */
    public function user()
    {
        return $this->belongsTo('Api\Model\User', 'user_id');
    }

    public function scopeOutOfSync($query)
    {
        return $query->whereNull('last_sync_date')
            ->orWhereColumn('last_sync_date', '<', 'updated_at');
    }

}